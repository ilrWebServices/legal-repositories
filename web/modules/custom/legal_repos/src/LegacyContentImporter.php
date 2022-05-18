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
    $this->importConsentDecrees();
    $this->importAdaCases();
    return TRUE;
  }

  public function importConsentDecrees() {
    $node_storage = $this->entityTypeManager->getStorage('node');
    $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');

    $json_file = file_get_contents($this->dataPath . 'consent_decrees.json');
    $data = json_decode($json_file, TRUE);

    foreach ($data as $record) {
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
      $node->status = !$record['hidden'];
      $node->title = $record['caseName'];
      $node->field_case_number = $record['caseNumber'];
      $node->field_version_number = $record['versionNumber'];
      $node->field_state = $record['state'];
      $node->field_state_claims = $record['stateClaim'] === 'True' ? 1 : 0;
      $node->field_judge = $record['judgeFullName'];
      $node->field_resource_url = $commons_url;
      $node->field_jurisdiction = $record['court'];
      $node->field_date_lawsuit_filed = substr($record['lawsuitFiledDate'], 0, 10);
      $node->field_date_filed = substr($record['cdFiledDate'], 0, 10);
      $node->field_date_signed = substr($record['cdSignedDate'], 0, 10);
      $node->field_effective_date = $record['effectiveDate'];
      $node->field_duration = $record['durationNumberOfYears'];
      $node->field_duration_text = $record['durationText'];
      $node->field_plaintiffs_attorneys_fees = $record['plaintiffsAttorneysFees'];
      $node->setRevisionLogMessage($record['internComments']);

      foreach ($record['plaintiffCounsel'] as $firm => $attorney_array) {
        $counsel = $paragraph_storage->create([
          'type' => 'counsel',
          'field_firm_name' => $firm,
          'field_attorneys' => $attorney_array,
        ]);
        $counsel->save();
        $node->field_plaintiff_counsel->appendItem($counsel);
      }

      foreach ($record['defendantCounsel'] as $firm => $attorney_array) {
        $counsel = $paragraph_storage->create([
          'type' => 'counsel',
          'field_firm_name' => $firm,
          'field_attorneys' => $attorney_array,
        ]);
        $counsel->save();
        $node->field_defendant_counsel->appendItem($counsel);
      }

      $node->field_industry = $record['fields']['Industry'] ?? '';
      $node->field_protected_classes = $record['fields']['Protected Classes'] ?? [];
      $node->field_number_of_named_plaintiffs = $record['fields']['Number of named plaintiff(s)'] ?? '';
      $node->field_theory_of_discrimination = $record['fields']['Theory of Discrimination'] ?? [];
      $node->field_type_of_discrimination = $record['fields']['Type of Discrimination'] ?? [];
      $node->field_settlement_funds_lower = $record['fields']['Settlement Funds (lower bound)'] ?? '';
      $node->field_settlement_funds_upper = $record['fields']['Settlement Funds (upper bound)'] ?? '';

      $node->field_class_definition_clause = $record['clauses']['Class Definition Clause'] ?? '';
      $node->field_court_juris_retained = $record['clauses']['Court Jurisdiction Retained'] ?? '';
      $node->field_fairness_hearings = $record['clauses']['Fairness Hearings'] ?? '';
      $node->field_enforceability_procedures = $record['clauses']['Enforceability / Enforcement Procedures'] ?? '';
      $node->field_class_notice = $record['clauses']['Notice to Class'] ?? '';
      $node->field_releases = $record['clauses']['Releases'] ?? '';
      $node->field_confidentiality = $record['clauses']['Confidentiality'] ?? '';
      $node->field_experts = $record['clauses']['Expert(s) Utilized During Discovery/Trial'] ?? '';
      $node->field_expert_selection_process = $record['clauses']['Expert Selection Process'] ?? '';
      $node->field_expert_responsibilities = $record['clauses']['Expert Responsibilities'] ?? '';
      $node->field_expert_fees = $record['clauses']['Expert Fees'] ?? '';
      $node->field_backpay_frontpay = $record['clauses']['Backpay/Frontpay'] ?? '';
      $node->field_complaint_procedures = $record['clauses']['Complaint Procedures'] ?? '';
      $node->field_eeo_postings_and_policies = $record['clauses']['EEO Postings and Policies'] ?? '';
      $node->field_employee_compensation = $record['clauses']['Employee Compensation'] ?? '';
      $node->field_evaluation = $record['clauses']['Evaluation'] ?? '';
      $node->field_expunge_record = $record['clauses']['Expunge Record'] ?? '';
      $node->field_hiring_promotions = $record['clauses']['Hiring/Promotions'] ?? '';
      $node->field_human_resource_policies = $record['clauses']['Human Resource Policies'] ?? '';
      $node->field_new_staff_positions = $record['clauses']['New permanent staff positions'] ?? '';
      $node->field_non_retaliation = $record['clauses']['Non-Retaliation'] ?? '';
      $node->field_recruitment = $record['clauses']['Recruitment'] ?? '';
      $node->field_training_progs_advancement = $record['clauses']['Training Programs (Advancement)'] ?? '';
      $node->field_training_progs_discrim = $record['clauses']['Training Programs (Discrimination)'] ?? '';
      $node->field_training_progs_diversity = $record['clauses']['Training Programs (Diversity)'] ?? '';
      $node->field_work_assignment = $record['clauses']['Work Assignment'] ?? '';
      $node->field_progress_performance_rpt = $record['clauses']['Progress and Performance Report(s)'] ?? '';
      $node->field_successor_bligations = $record['clauses']['Successor Obligations'] ?? '';
      $node->field_record_retention = $record['clauses']['Record Retention'] ?? '';
      $node->field_administration_claims = $record['clauses']['Administration'] ?? '';
      $node->field_hearing_claims = $record['clauses']['Hearing'] ?? '';
      $node->field_consultants_utilized = $record['clauses']['Consultant(s) Utilized'] ?? '';
      $node->field_consultant_selection_proc = $record['clauses']['Consultant Selection Process'] ?? '';
      $node->field_consultant_responsibility = $record['clauses']['Consultant Responsibilities'] ?? '';
      $node->field_consultant_fees = $record['clauses']['Consultant Fees'] ?? '';
      $node->field_mediators_utilized = $record['clauses']['Mediator(s) Utilized'] ?? '';
      $node->field_mediator_selection_process = $record['clauses']['Mediator Selection Process'] ?? '';
      $node->field_mediator_responsibilities = $record['clauses']['Mediator Responsibilities'] ?? '';
      $node->field_mediator_fees = $record['clauses']['Mediator Fees'] ?? '';
      $node->field_internal_monitors = $record['clauses']['Internal Monitors'] ?? '';
      $node->field_internal_monitor_selection = $record['clauses']['Internal Monitor Selection Process'] ?? '';
      $node->field_internal_monitor_responsib = $record['clauses']['Internal Monitor Responsibilities'] ?? '';
      $node->field_ext_monitors = $record['clauses']['External Monitors'] ?? '';
      $node->field_ext_monitor_selection_proc = $record['clauses']['External Monitor Selection Process'] ?? '';
      $node->field_ext_monitor_responsibility = $record['clauses']['External Monitor Responsibilities'] ?? '';
      $node->field_monitor_fees = $record['clauses']['Monitor Fees'] ?? '';
      $node->field_special_masters_utilized = $record['clauses']['Special Master(s) Utilized'] ?? '';
      $node->field_spmaster_selection_process = $record['clauses']['Special Master Selection Process'] ?? '';
      $node->field_spmaster_responsibilities = $record['clauses']['Special Master Responsibilities'] ?? '';
      $node->field_special_master_fees = $record['clauses']['Special Master Fees'] ?? '';
      $node->field_training_providers = $record['clauses']['Training Providers'] ?? '';
      $node->field_coll_bargaining_agreement = $record['clauses']['Collective Bargaining Agreement'] ?? '';
      $node->field_other_special_issues = $record['clauses']['Other Special Issues'] ?? '';
      $node->save();
    }

    return TRUE;
  }

  public function importAdaCases() {
    $node_storage = $this->entityTypeManager->getStorage('node');
    $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');

    $json_file = file_get_contents($this->dataPath . 'ada_cases.json');
    $data = json_decode($json_file, TRUE);

    foreach ($data as $record) {
      $existing_nodes = $node_storage->loadByProperties([
        'type' => 'ada_case',
        'field_legacy_id' => $record['cdid'],
      ]);

      if (empty($existing_nodes)) {
        /** @var \Drupal\node\NodeInterface $node */
        $node = $node_storage->create([
          'type' => 'ada_case',
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
      $node->status = !$record['hidden'];
      $node->title = $record['caseName'];
      $node->field_case_number = $record['caseNumber'];
      $node->field_version_number = $record['versionNumber'];
      $node->field_state = $record['state'];
      $node->field_state_claims = $record['stateClaim'] === 'True' ? 1 : 0;
      $node->field_judge = $record['judgeFullName'];
      $node->field_resource_url = $commons_url;
      $node->field_jurisdiction = $record['court'];
      $node->field_date_lawsuit_filed = substr($record['lawsuitFiledDate'], 0, 10);
      $node->field_date_filed = substr($record['cdFiledDate'], 0, 10);
      $node->field_date_signed = substr($record['cdSignedDate'], 0, 10);
      $node->field_effective_date = $record['effectiveDate'];
      $node->field_duration = $record['durationNumberOfYears'];
      $node->field_duration_text = $record['durationText'];
      $node->field_plaintiffs_attorneys_fees = $record['plaintiffsAttorneysFees'];
      $node->setRevisionLogMessage($record['internComments']);

      foreach ($record['plaintiffCounsel'] as $firm => $attorney_array) {
        $counsel = $paragraph_storage->create([
          'type' => 'counsel',
          'field_firm_name' => $firm,
          'field_attorneys' => $attorney_array,
        ]);
        $counsel->save();
        $node->field_plaintiff_counsel->appendItem($counsel);
      }

      foreach ($record['defendantCounsel'] as $firm => $attorney_array) {
        $counsel = $paragraph_storage->create([
          'type' => 'counsel',
          'field_firm_name' => $firm,
          'field_attorneys' => $attorney_array,
        ]);
        $counsel->save();
        $node->field_defendant_counsel->appendItem($counsel);
      }

      $node->field_industry = $record['fields']['Industry'] ?? '';

      // Review the following:
      $node->field_protected_classes = $record['fields']['Protected Classes'] ?? [];
      $node->field_number_of_named_plaintiffs = $record['fields']['Number of named plaintiff(s)'] ?? '';
      $node->field_theory_of_discrimination = $record['fields']['Theory of Discrimination'] ?? [];
      $node->field_type_of_discrimination = $record['fields']['Type of Discrimination'] ?? [];
      $node->field_settlement_funds_lower = $record['fields']['Settlement Funds (lower bound)'] ?? '';
      $node->field_settlement_funds_upper = $record['fields']['Settlement Funds (upper bound)'] ?? '';

      $node->field_class_definition_clause = $record['clauses']['Class Definition Clause'] ?? '';
      $node->field_court_juris_retained = $record['clauses']['Court Jurisdiction Retained'] ?? '';
      $node->field_fairness_hearings = $record['clauses']['Fairness Hearings'] ?? '';
      $node->field_enforceability_procedures = $record['clauses']['Enforceability / Enforcement Procedures'] ?? '';
      $node->field_class_notice = $record['clauses']['Notice to Class'] ?? '';
      $node->field_releases = $record['clauses']['Releases'] ?? '';
      $node->field_confidentiality = $record['clauses']['Confidentiality'] ?? '';
      $node->field_experts = $record['clauses']['Expert(s) Utilized During Discovery/Trial'] ?? '';
      $node->field_expert_selection_process = $record['clauses']['Expert Selection Process'] ?? '';
      $node->field_expert_responsibilities = $record['clauses']['Expert Responsibilities'] ?? '';
      $node->field_expert_fees = $record['clauses']['Expert Fees'] ?? '';
      $node->field_backpay_frontpay = $record['clauses']['Backpay/Frontpay'] ?? '';
      $node->field_complaint_procedures = $record['clauses']['Complaint Procedures'] ?? '';
      $node->field_eeo_postings_and_policies = $record['clauses']['EEO Postings and Policies'] ?? '';
      $node->field_employee_compensation = $record['clauses']['Employee Compensation'] ?? '';
      $node->field_evaluation = $record['clauses']['Evaluation'] ?? '';
      $node->field_expunge_record = $record['clauses']['Expunge Record'] ?? '';
      $node->field_hiring_promotions = $record['clauses']['Hiring/Promotions'] ?? '';
      $node->field_human_resource_policies = $record['clauses']['Human Resource Policies'] ?? '';
      $node->field_new_staff_positions = $record['clauses']['New permanent staff positions'] ?? '';
      $node->field_non_retaliation = $record['clauses']['Non-Retaliation'] ?? '';
      $node->field_recruitment = $record['clauses']['Recruitment'] ?? '';
      $node->field_training_progs_advancement = $record['clauses']['Training Programs (Advancement)'] ?? '';
      $node->field_training_progs_discrim = $record['clauses']['Training Programs (Discrimination)'] ?? '';
      $node->field_training_progs_diversity = $record['clauses']['Training Programs (Diversity)'] ?? '';
      $node->field_work_assignment = $record['clauses']['Work Assignment'] ?? '';
      $node->field_progress_performance_rpt = $record['clauses']['Progress and Performance Report(s)'] ?? '';
      $node->field_successor_bligations = $record['clauses']['Successor Obligations'] ?? '';
      $node->field_record_retention = $record['clauses']['Record Retention'] ?? '';
      $node->field_administration_claims = $record['clauses']['Administration'] ?? '';
      $node->field_hearing_claims = $record['clauses']['Hearing'] ?? '';
      $node->field_consultants_utilized = $record['clauses']['Consultant(s) Utilized'] ?? '';
      $node->field_consultant_selection_proc = $record['clauses']['Consultant Selection Process'] ?? '';
      $node->field_consultant_responsibility = $record['clauses']['Consultant Responsibilities'] ?? '';
      $node->field_consultant_fees = $record['clauses']['Consultant Fees'] ?? '';
      $node->field_mediators_utilized = $record['clauses']['Mediator(s) Utilized'] ?? '';
      $node->field_mediator_selection_process = $record['clauses']['Mediator Selection Process'] ?? '';
      $node->field_mediator_responsibilities = $record['clauses']['Mediator Responsibilities'] ?? '';
      $node->field_mediator_fees = $record['clauses']['Mediator Fees'] ?? '';
      $node->field_internal_monitors = $record['clauses']['Internal Monitors'] ?? '';
      $node->field_internal_monitor_selection = $record['clauses']['Internal Monitor Selection Process'] ?? '';
      $node->field_internal_monitor_responsib = $record['clauses']['Internal Monitor Responsibilities'] ?? '';
      $node->field_ext_monitors = $record['clauses']['External Monitors'] ?? '';
      $node->field_ext_monitor_selection_proc = $record['clauses']['External Monitor Selection Process'] ?? '';
      $node->field_ext_monitor_responsibility = $record['clauses']['External Monitor Responsibilities'] ?? '';
      $node->field_monitor_fees = $record['clauses']['Monitor Fees'] ?? '';
      $node->field_special_masters_utilized = $record['clauses']['Special Master(s) Utilized'] ?? '';
      $node->field_spmaster_selection_process = $record['clauses']['Special Master Selection Process'] ?? '';
      $node->field_spmaster_responsibilities = $record['clauses']['Special Master Responsibilities'] ?? '';
      $node->field_special_master_fees = $record['clauses']['Special Master Fees'] ?? '';
      $node->field_training_providers = $record['clauses']['Training Providers'] ?? '';
      $node->field_coll_bargaining_agreement = $record['clauses']['Collective Bargaining Agreement'] ?? '';
      $node->field_other_special_issues = $record['clauses']['Other Special Issues'] ?? '';
      $node->save();
    }

    return TRUE;
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

    try {
      $this->httpClient->request('HEAD', $url, [
        'allow_redirects' => [
          'referer' => true,
          'on_redirect' => function(RequestInterface $request, ResponseInterface $response, UriInterface $uri) use (&$final_redirected_url) {
            $final_redirected_url = (string) $uri;
          },
        ]
      ]);
    }
    catch(\Exception $e) {
      // echo $url . ': ' . $e->getMessage(); // Uncomment for debugging.
    }

    return $final_redirected_url ?? $url;
  }

}
