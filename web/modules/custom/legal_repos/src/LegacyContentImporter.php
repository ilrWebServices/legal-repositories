<?php

namespace Drupal\legal_repos;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use GuzzleHttp\Client as GuzzleClient;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

class LegacyContentImporter {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The CSV data path.
   *
   * @var string
   */
  protected $dataPath;

  /**
   * The http client service.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * Constructs a new legacy content importer.
   *
   * @param EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param ModuleHandlerInterface $module_handler
   *   The moduler handler service.
   * @param MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, MessengerInterface $messenger, GuzzleClient $http_client) {
    $this->entityTypeManager = $entity_type_manager;
    $this->dataPath = $module_handler->getModule('legal_repos')->getPath() . '/data/';
    $this->messenger = $messenger;
    $this->httpClient = $http_client;
  }

  public function import() {
    $node_storage = $this->entityTypeManager->getStorage('node');
    $base_cd_file_handle = fopen($this->dataPath . 'consentDecree.csv', 'r+');
    $rows = 0;
    $nodes = [];
    $header = FALSE;

    while (($data = fgetcsv($base_cd_file_handle, 0, ',', '"')) !== FALSE) {
      if (!$header) {
        $header = $data;
      }
      else {
        $rows ++;
        $record = array_combine($header, $data);

        $existing_nodes = $node_storage->loadByProperties([
          'type' => 'title_vii_consent_decree',
          'field_legacy_id' => $record['cdid'],
        ]);

        if (empty($existing_nodes)) {
          /** @var \Drupal\node\NodeInterface $node */
          $node = $node_storage->create([
            'type' => 'title_vii_consent_decree',
            'field_legacy_id' => $record['cdid'],
          ]);
        }
        elseif (count($existing_nodes) === 1) {
          /** @var \Drupal\node\NodeInterface $node */
          $node = reset($existing_nodes);
        }
        // More than one node was returned.
        else {
          // @todo Log error?
          continue;
        }

        $commons_url = $this->getFinalRedirectUrl($record['digitalCommonsURL']);

        $node->created = strtotime(substr($record['cdCreateDate'], 0, 20));
        $node->changed = strtotime(substr($record['lastModifiedDate'], 0, 20));
        $node->status = ($record['hidden'] === 'False') ? 1 : 0;
        $node->title = $record['caseName'];
        $node->field_case_number = $record['caseNumber'];
        $node->field_version_number = $record['versionNumber'];
        $node->field_state = $record['state'];
        $node->field_state_claims = $record['stateClaim'] === 'True' ? 1 : 0;
        $node->field_judge = $record['judgeFullName'];
        $node->field_digital_commons_pdf_link = $record['digitalCommonsPdfURL'];
        $node->field_digital_commons_link = $commons_url;
        $node->field_jurisdiction = $record['court'];
        $node->field_date_lawsuit_filed = substr($record['lawsuitFiledDate'], 0, 10);
        $node->field_date_consent_decree_filed = substr($record['cdFiledDate'], 0, 10);
        $node->field_date_consent_decree_signed = substr($record['cdSignedDate'], 0, 10);
        $node->field_effective_date = $record['effectiveDate'];
        $node->field_duration = $record['durationNumberOfYears'];
        $node->field_duration_text = $record['durationText'];
        $node->field_plaintiffs_attorneys_fees = $record['plaintiffsAttorneysFees'];
        $node->setRevisionLogMessage($record['internComments']);

        if ($node->save()) {
          $nodes[$record['cdid']] = $node;
        }
      }
    }

    fclose($base_cd_file_handle);

    // Update the consent decrees with expanded data from the exported download
    // file.
    $extended_data_cd_file_handle = fopen($this->dataPath . 'downloadVersion.csv', 'r+');
    $rows = 0;
    $header = FALSE;
    $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');

    while (($data = fgetcsv($extended_data_cd_file_handle, 0, ',', '"')) !== FALSE) {
      if (!$header) {
        $header = $data;
      }
      else {
        $rows ++;
        $record = array_combine($header, $data);
        $node = $nodes[$record['cdid']] ?? FALSE;

        if (!$node) {
          continue;
        }

        $node->field_industry = $record['Industry'];

        $plaintiff_counsel = $this->parseCounselData($record['plaintiffCounsel']);

        if ($node->field_plaintiff_counsel->count() < count($plaintiff_counsel)) {
          foreach ($plaintiff_counsel as $firm => $attorney_array) {
            $counsel = $paragraph_storage->create([
              'type' => 'counsel',
              'field_firm_name' => $firm,
              'field_attorneys' => $attorney_array,
            ]);
            $counsel->save();
            $node->field_plaintiff_counsel->appendItem($counsel);
          }
        }

        $defendent_counsel = $this->parseCounselData($record['defendantCounsel']);

        if ($node->field_defendant_counsel->count() < count($defendent_counsel)) {
          foreach ($defendent_counsel as $firm => $attorney_array) {
            $counsel = $paragraph_storage->create([
              'type' => 'counsel',
              'field_firm_name' => $firm,
              'field_attorneys' => $attorney_array,
            ]);
            $counsel->save();
            $node->field_defendant_counsel->appendItem($counsel);
          }
        }

        $protected_class_keys = ['National Origin', 'Religion', 'Sex', 'Female', 'Male', 'Race', 'American Indian or Alaskan Native', 'Asian', 'African American or Black', 'Hispanic or Latino', 'Native Hawaiian or Other Pacific Islander', 'White', 'Age'];
        $protected_classes = [];

        foreach ($protected_class_keys as $protected_class) {
          if ($record[$protected_class] === '1') {
            $protected_classes[] = $protected_class;
          }
        }
        $node->field_protected_classes = $protected_classes;

        $node->field_number_of_named_plaintiffs = $record['Number of named plaintiffs'];
        $node->field_class_definition_clause = $record['Class Definition Clause'];


        $theory_discrimination_keys = ['Disparate Impact', 'Disparate Treatment', 'Failure to Accommodate', 'Hostile Work Environment (Theory of Discr.)', 'Retaliation', 'Sexual Harassment (Theory of Discr.)'];
        $discrimination_theories = [];

        foreach ($theory_discrimination_keys as $theory) {
          if ($record[$theory] === '1') {
            $discrimination_theories[] = str_replace(' (Theory of Discr.)', '', $theory);
          }
        }
        $node->field_theory_of_discrimination = $discrimination_theories;

        $type_discrimination_keys = ['Assignment', 'Compensation', 'Constructive Discharge', 'Evaluation (Type of Discr.)', 'Hiring', 'Hostile Work Environment (Type of Discr.)', 'Promotion', 'Sexual Harassment (Type of Discr.)', 'Subjective Decision Making', 'Termination', 'Terms and Conditions', 'Training / Advancement'];
        $discrimination_types = [];

        foreach ($type_discrimination_keys as $discrimination_type) {
          if ($record[$discrimination_type] === '1') {
            $discrimination_types[] = str_replace(' (Type of Discr.)', '', $discrimination_type);
          }
        }
        $node->field_type_of_discrimination = $discrimination_types;

        $node->field_court_juris_retained = $record['Court Jurisdiction Retained'];
        $node->field_fairness_hearings = $record['Fairness Hearings'];
        $node->field_enforceability_procedures = $record['Enforceability / Enforcement Procedures'];
        $node->field_class_notice = $record['Notice to Class'];
        $node->field_releases = $record['Releases'];
        $node->field_confidentiality = $record['Confidentiality'];
        $node->field_experts = $record['Expert(s) Utilized During Discovery/Trial'];
        $node->field_expert_selection_process = $record['Expert Selection Process'];
        $node->field_expert_responsibilities = $record['Expert Responsibilities'];
        $node->field_expert_fees = $record['Expert Fees'];
        $node->field_settlement_funds_lower = $record['Settlement Funds (lower bound)'];
        $node->field_settlement_funds_upper = $record['Settlement Funds (upper bound)'];
        $node->field_backpay_frontpay = $record['Backpay/Frontpay'];
        $node->field_complaint_procedures = $record['Complaint Procedures'];
        $node->field_eeo_postings_and_policies = $record['EEO Postings and Policies'];
        $node->field_employee_compensation = $record['Employee Compensation'];
        $node->field_evaluation = $record['Evaluation (Remedy)'];
        $node->field_expunge_record = $record['Expunge Record'];
        $node->field_hiring_promotions = $record['Hiring/Promotions'];
        $node->field_human_resource_policies = $record['Human Resource Policies'];
        $node->field_new_staff_positions = $record['New permanent staff positions'];
        $node->field_non_retaliation = $record['Non-Retaliation'];
        $node->field_recruitment = $record['Recruitment'];
        $node->field_training_progs_advancement = $record['Training Programs (Advancement)'];
        $node->field_training_progs_discrim = $record['Training Programs (Discrimination)'];
        $node->field_training_progs_diversity = $record['Training Programs (Diversity)'];
        $node->field_work_assignment = $record['Work Assignment'];

        $node->field_progress_performance_rpt = $record['Progress and Performance Report(s)'];
        $node->field_successor_bligations = $record['Successor Obligations'];
        $node->field_record_retention = $record['Record Retention'];
        $node->field_administration_claims = $record['Administration'];
        $node->field_hearing_claims = $record['Hearing'];
        $node->field_consultants_utilized = $record['Consultant(s) Utilized'];
        $node->field_consultant_selection_proc = $record['Consultant Selection Process'];
        $node->field_consultant_responsibility = $record['Consultant Responsibilities'];
        $node->field_consultant_fees = $record['Consultant Fees'];
        $node->field_mediators_utilized = $record['Mediator(s) Utilized'];
        $node->field_mediator_selection_process = $record['Mediator Selection Process'];
        $node->field_mediator_responsibilities = $record['Mediator Responsibilities'];
        $node->field_mediator_fees = $record['Mediator Fees'];
        $node->field_internal_monitors = $record['Internal Monitors'];
        $node->field_internal_monitor_selection = $record['Internal Monitor Selection Process'];
        $node->field_internal_monitor_responsib = $record['Internal Monitor Responsibilities'];
        $node->field_ext_monitors = $record['External Monitors'];
        $node->field_ext_monitor_selection_proc = $record['External Monitor Selection Process'];
        $node->field_ext_monitor_responsibility = $record['External Monitor Responsibilities'];
        $node->field_monitor_fees = $record['Monitor Fees'];
        $node->field_special_masters_utilized = $record['Special Master(s) Utilized'];
        $node->field_spmaster_selection_process = $record['Special Master Selection Process'];
        $node->field_spmaster_responsibilities = $record['Special Master Responsibilities'];
        $node->field_special_master_fees = $record['Special Master Fees'];
        $node->field_training_providers = $record['Training Providers'];
        $node->field_coll_bargaining_agreement = $record['Collective Bargaining Agreement'];
        $node->field_other_special_issues = $record['Other Special Issues'];
        $node->save();
      }
    }

    return TRUE;
  }

  /**
   * Parse the stored firm and attorney data.
   *
   * @param string $counsel_data
   *   The unparsed data from the spreadsheet.
   * @return array
   *   An array keyed by firm name with an array of attorneys as the value.
   */
  protected function parseCounselData($counsel_data) {
    $firm_attorney_array = explode('LAW FIRM OR AGENCY: ', $counsel_data);
    $counsel = [];

    foreach ($firm_attorney_array as $firm_and_attorney) {
      if (empty($firm_and_attorney)) {
        continue;
      }
      $firm_and_attorney = explode('- ATTORNEY: ', $firm_and_attorney);
      $counsel[trim($firm_and_attorney[0])][] = trim(preg_replace('/\s+;/', ' ', $firm_and_attorney[1]));
    }
    return $counsel;
  }

  /**
   * Get the final redirect location for a given URL.
   *
   * @param string $url
   *   A given URL.
   * @return string
   *   The final URL after following redirects, if any.
   */
  protected function getFinalRedirectUrl(string $url) {
    $final_redirected_url = NULL;

    $this->httpClient->request('HEAD', $url, [
      'allow_redirects' => [
        'referer' => true,
        'on_redirect' => function(RequestInterface $request, ResponseInterface $response, UriInterface $uri) use (&$final_redirected_url) {
          $final_redirected_url = (string) $uri;
        },
      ]
    ]);

    return $final_redirected_url ?? $url;
  }

}
