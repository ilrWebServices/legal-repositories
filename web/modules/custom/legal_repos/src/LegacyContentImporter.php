<?php

namespace Drupal\legal_repos;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drush\Drush;
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

  public function import($show_progress = FALSE) {
    $this->importConsentDecrees($show_progress);
    $this->importAdaCases($show_progress);
    return TRUE;
  }

  public function importConsentDecrees($show_progress) {
    $node_storage = $this->entityTypeManager->getStorage('node');

    $json_file = file_get_contents($this->dataPath . 'consent_decrees.json');
    $data = json_decode($json_file, TRUE);
    $case_count = count($data);

    foreach ($data as $index => $record) {
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

      if ($show_progress) {
        Drush::output()->writeln(strtr("{op} {index}" . " of {case_count} ADA cases", [
          '{op}' => $node->isNew() ? 'Importing' : 'Updating',
          '{index}' => $index + 1,
          '{case_count}' => $case_count,
        ]));
      }

      if ($node->field_resource_url->isEmpty()) {
        $node->field_resource_url = $this->getFinalRedirectUrl($record['digitalCommonsURL']);
      }

      $node->created = strtotime(substr($record['cdCreateDate'], 0, 20));
      $node->changed = strtotime(substr($record['lastModifiedDate'], 0, 20));
      $node->status = !$record['hidden'];
      $node->title = $record['caseName'];
      $node->field_case_number = $record['caseNumber'];
      $node->field_version_number = $record['versionNumber'];
      $node->field_state = $record['state'];
      $node->field_state_claims = $record['stateClaim'];
      $node->field_judge = $record['judgeFullName'];
      $node->field_jurisdiction = $record['court'];
      $node->field_date_lawsuit_filed = substr($record['lawsuitFiledDate'], 0, 10);
      $node->field_date_filed = substr($record['cdFiledDate'], 0, 10);
      $node->field_date_signed = substr($record['cdSignedDate'], 0, 10);
      $node->field_effective_date = $record['effectiveDate'];
      $node->field_duration = $record['durationNumberOfYears'];
      $node->field_duration_text = $record['durationText'];
      $node->field_plaintiffs_attorneys_fees = $record['plaintiffsAttorneysFees'];
      $node->setRevisionLogMessage($record['internComments']);

      foreach ($record['plaintiffCounsel'] as $firm_id => $firm_info) {
        if ($counsel = $this->getFirm('consent_decree', $firm_id, $firm_info)) {
          if ($counsel->isNew()) {
            $node->field_plaintiff_counsel->appendItem($counsel);
          }
          $counsel->save();
        }
      }

      foreach ($record['defendantCounsel'] as $firm_id => $firm_info) {
        if ($counsel = $this->getFirm('consent_decree', $firm_id, $firm_info)) {
          if ($counsel->isNew()) {
            $node->field_defendant_counsel->appendItem($counsel);
          }
          $counsel->save();
        }
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

  public function importAdaCases($show_progress) {
    $node_storage = $this->entityTypeManager->getStorage('node');

    $json_file = file_get_contents($this->dataPath . 'ada_cases.json');
    $data = json_decode($json_file, TRUE);
    $case_count = count($data);

    foreach ($data as $index => $record) {
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

      if ($show_progress) {
        Drush::output()->writeln(strtr("{op} {index}" . " of {case_count} ADA cases", [
          '{op}' => $node->isNew() ? 'Importing' : 'Updating',
          '{index}' => $index + 1,
          '{case_count}' => $case_count,
        ]));
      }

      if ($node->field_resource_url->isEmpty()) {
        $node->field_resource_url = $this->getFinalRedirectUrl($record['digitalCommonsURL']);
      }

      $node->created = strtotime(substr($record['cdCreateDate'], 0, 20));
      $node->changed = strtotime(substr($record['lastModifiedDate'], 0, 20));
      $node->status = !$record['hidden'];
      $node->title = $record['caseName'];
      $node->field_case_number = $record['caseNumber'];
      $node->field_version_number = $record['versionNumber'];
      $node->field_state = $record['state'];
      $node->field_state_claims = $record['stateClaim'];
      $node->field_judge = $record['judgeFullName'];
      $node->field_jurisdiction = $record['court'];
      $node->field_date_lawsuit_filed = substr($record['lawsuitFiledDate'], 0, 10);
      $node->field_date_filed = substr($record['cdFiledDate'], 0, 10);
      $node->field_date_signed = substr($record['cdSignedDate'], 0, 10);
      $node->field_effective_date = $record['effectiveDate'];
      $node->field_duration = $record['durationNumberOfYears'];
      $node->field_duration_text = $record['durationText'];
      $node->field_plaintiffs_attorneys_fees = $record['plaintiffsAttorneysFees'];
      $node->setRevisionLogMessage($record['internComments']);

      foreach ($record['plaintiffCounsel'] as $firm_id => $firm_info) {
        if ($counsel = $this->getFirm('ada_case', $firm_id, $firm_info)) {
          if ($counsel->isNew()) {
            $node->field_plaintiff_counsel->appendItem($counsel);
          }
          $counsel->save();
        }
      }

      foreach ($record['defendantCounsel'] as $firm_id => $firm_info) {
        if ($counsel = $this->getFirm('ada_case', $firm_id, $firm_info)) {
          if ($counsel->isNew()) {
            $node->field_defendant_counsel->appendItem($counsel);
          }
          $counsel->save();
        }
      }

      $node->field_industry = $record['fields']['Industry'] ?? '';
      $node->field_number_of_named_plaintiffs = $record['fields']['Number of named plaintiff(s)'] ?? '';
      $node->field_settlement_funds_lower = $record['fields']['Settlement Funds (lower bound)'] ?? '';
      $node->field_settlement_funds_upper = $record['fields']['Settlement Funds (upper bound)'] ?? '';
      $node->field_theory_discrim_empl = $record['fields']['Theory of Discrimination - Employment'] ?? '';
      $node->field_theory_discrim_pre_empl = $record['fields']['Theory of Discrimination - Pre-Employment'] ?? '';
      $node->field_type_of_decision = $record['fields']['Type of Decision'] ?? '';
      $node->field_type_of_discrimination_emp = $record['fields']['Type of Discrimination - Employment'] ?? '';
      $node->field_type_discrim_pre_empl = $record['fields']['Type of Discrimination - Pre-Employment'] ?? '';

      $node->field_ada_coordinator = $record['clauses']['ADA Coordinator'] ?? '';
      $node->field_administration_claims = $record['clauses']['Administration'] ?? '';
      $node->dis_mental_alcoholism = $record['clauses']['Alcoholism'] ?? '';
      $node->dis_phys_asthma = $record['clauses']['Asthma'] ?? '';
      $node->dis_mental_autism_spectrum = $record['clauses']['Autism-Spectrum'] ?? '';
      $node->field_backpay_frontpay = $record['clauses']['Backpay/Frontpay'] ?? '';
      $node->dis_act_bending = $record['clauses']['Bending'] ?? '';
      $node->dis_mental_bipolar_disorder = $record['clauses']['Bipolar Disorder'] ?? '';
      $node->dis_phys_blindness = $record['clauses']['Blindness'] ?? '';
      $node->dis_act_breathing = $record['clauses']['Breathing'] ?? '';
      $node->dis_phys_cancer = $record['clauses']['Cancer'] ?? '';
      $node->dis_act_caring_oneself = $record['clauses']['Caring for Oneself'] ?? '';
      $node->dis_phys_cerebral_palsy = $record['clauses']['Cerebral Palsy'] ?? '';
      $node->field_class_definition_clause = $record['clauses']['Class Definition Clause'] ?? '';
      $node->field_coll_bargaining_agreement = $record['clauses']['Collective Bargaining Agreement'] ?? '';
      $node->dis_act_communicating = $record['clauses']['Communicating'] ?? '';
      $node->field_complaint_procedures = $record['clauses']['Complaint Procedures'] ?? '';
      $node->dis_act_concentrating = $record['clauses']['Concentrating'] ?? '';
      $node->field_confidentiality = $record['clauses']['Confidentiality'] ?? '';
      $node->field_consultant_fees = $record['clauses']['Consultant Fees'] ?? '';
      $node->field_consultant_responsibility = $record['clauses']['Consultant Responsibilities'] ?? '';
      $node->field_consultant_selection_proc = $record['clauses']['Consultant Selection Process'] ?? '';
      $node->field_consultants_utilized = $record['clauses']['Consultant(s) Utilized'] ?? '';
      $node->field_court_juris_retained = $record['clauses']['Court Jurisdiction Retained'] ?? '';
      $node->dis_phys_deafness = $record['clauses']['Deafness'] ?? '';
      $node->dis_mental_depression = $record['clauses']['Depression'] ?? '';
      $node->dis_phys_diabetes = $record['clauses']['Diabetes'] ?? '';
      $node->dis_other_record = $record['clauses']['Disability - Record of Disability'] ?? '';
      $node->dis_other_regarded = $record['clauses']['Disability - Regarded as Having a Disability'] ?? '';
      $node->dis_other_relationship_assoc = $record['clauses']['Disability - Relationship/Association to a Person with a Disability'] ?? '';
      $node->dis_mental_drug_addiction = $record['clauses']['Drug Addiction'] ?? '';
      $node->field_eeo_postings_and_policies = $record['clauses']['EEO Postings and Policies'] ?? '';
      $node->dis_act_eating = $record['clauses']['Eating'] ?? '';
      $node->field_employee_compensation = $record['clauses']['Employee Compensation'] ?? '';
      $node->field_enforceability_procedures = $record['clauses']['Enforceability / Enforcement Procedures'] ?? '';
      $node->dis_phys_epilepsy = $record['clauses']['Epilepsy'] ?? '';
      $node->field_evaluation = $record['clauses']['Evaluation'] ?? '';
      $node->field_experts = $record['clauses']['Expert(s) Utilized During Discovery/Trial'] ?? '';
      $node->field_expunge_record = $record['clauses']['Expunge Record'] ?? '';
      $node->field_ext_monitor_responsibility = $record['clauses']['External Monitor Responsibilities'] ?? '';
      $node->field_ext_monitor_selection_proc = $record['clauses']['External Monitor Selection Process'] ?? '';
      $node->field_ext_monitors = $record['clauses']['External Monitors'] ?? '';
      $node->dis_phys_hiv_infection = $record['clauses']['HIV infection'] ?? '';
      $node->dis_act_hearing = $record['clauses']['Hearing'] ?? '';
      $node->dis_phys_heart_disease = $record['clauses']['Heart Disease'] ?? '';
      $node->field_hiring_promotions = $record['clauses']['Hiring/Promotions'] ?? '';
      $node->field_holding = $record['clauses']['Holding'] ?? '';
      $node->field_human_resource_policies = $record['clauses']['Human Resource Policies'] ?? '';
      $node->field_injunction = $record['clauses']['Injunction'] ?? '';
      $node->dis_act_interacting = $record['clauses']['Interacting with Others'] ?? '';
      $node->field_internal_monitor_responsib = $record['clauses']['Internal Monitor Responsibilities'] ?? '';
      $node->field_internal_monitor_selection = $record['clauses']['Internal Monitor Selection Process'] ?? '';
      $node->field_internal_monitors = $record['clauses']['Internal Monitors'] ?? '';
      $node->field_internship_prog_stud_disab = $record['clauses']['Internship Program for Students with Disabilities'] ?? '';
      $node->dis_phys_kidney_impairment = $record['clauses']['Kidney Impairment'] ?? '';
      $node->dis_mental_ld_adhd = $record['clauses']['Learning Disability/ADHD'] ?? '';
      $node->dis_act_learning = $record['clauses']['Learning'] ?? '';
      $node->dis_act_lifting = $record['clauses']['Lifting'] ?? '';
      $node->dis_phys_limbs_missing = $record['clauses']['Limbs partly or completely missing'] ?? '';
      $node->dis_act_major_bodily_func_ops = $record['clauses']['Major Bodily Function Operations'] ?? '';
      $node->dis_mental_mdd = $record['clauses']['Major Depressive Disorder'] ?? '';
      $node->field_monitor_fees = $record['clauses']['Monitor Fees'] ?? '';
      $node->dis_phys_multiple_sclerosis = $record['clauses']['Multiple Sclerosis'] ?? '';
      $node->field_new_staff_positions = $record['clauses']['New permanent staff positions'] ?? '';
      $node->field_notice_of_rights = $record['clauses']['Notice of ADA rights'] ?? '';
      $node->field_class_notice = $record['clauses']['Notice to Class'] ?? '';
      $node->dis_phys_ortho_impairment = $record['clauses']['Orthopedic Impairments'] ?? '';
      $node->field_other_special_issues = $record['clauses']['Other Special Issues'] ?? '';
      $node->dis_act_other = $record['clauses']['Other major life activity disability'] ?? '';
      $node->dis_mental_other = $record['clauses']['Other mental impairment disability'] ?? '';
      $node->dis_phys_other = $record['clauses']['Other physical impairment disability'] ?? '';
      $node->dis_phys_paralysis = $record['clauses']['Paralysis'] ?? '';
      $node->dis_act_manual_tasks = $record['clauses']['Performing Manual Tasks'] ?? '';
      $node->dis_mental_ptsd = $record['clauses']['Post-Traumatic Stress Disorder'] ?? '';
      $node->dis_phys_pregnancy_compl = $record['clauses']['Pregnancy Complications'] ?? '';
      $node->field_print_media_notice = $record['clauses']['Print Media Notice'] ?? '';
      $node->field_progress_performance_rpt = $record['clauses']['Progress and Performance Report(s)'] ?? '';
      $node->field_rationale = $record['clauses']['Rationale'] ?? '';
      $node->dis_act_reaching = $record['clauses']['Reaching'] ?? '';
      $node->dis_act_reading = $record['clauses']['Reading'] ?? '';
      $node->field_record_retention = $record['clauses']['Record Retention'] ?? '';
      $node->field_recruitment = $record['clauses']['Recruitment'] ?? '';
      $node->field_reinstatement = $record['clauses']['Reinstatement'] ?? '';
      $node->field_releases = $record['clauses']['Releases'] ?? '';
      $node->dis_act_reproduction = $record['clauses']['Reproduction'] ?? '';
      $node->dis_act_seeing = $record['clauses']['Seeing'] ?? '';
      $node->dis_act_sitting = $record['clauses']['Sitting'] ?? '';
      $node->dis_act_sleeping = $record['clauses']['Sleeping'] ?? '';
      $node->dis_act_speaking = $record['clauses']['Speaking'] ?? '';
      $node->field_special_master_fees = $record['clauses']['Special Master Fees'] ?? '';
      $node->field_spmaster_responsibilities = $record['clauses']['Special Master Responsibilities'] ?? '';
      $node->field_spmaster_selection_process = $record['clauses']['Special Master Selection Process'] ?? '';
      $node->field_special_masters_utilized = $record['clauses']['Special Master(s) Utilized'] ?? '';
      $node->dis_act_standing = $record['clauses']['Standing'] ?? '';
      $node->field_successor_bligations = $record['clauses']['Successor Obligations'] ?? '';
      $node->field_surplus_funds_non_profits = $record['clauses']['Surplus Funds to Relevant Non-Profit Organizations'] ?? '';
      $node->dis_act_thinking = $record['clauses']['Thinking'] ?? '';
      $node->field_training_progs_discrim = $record['clauses']['Training Programs (Discrimination)'] ?? '';
      $node->field_training_progs_diversity = $record['clauses']['Training Programs (Diversity)'] ?? '';
      $node->field_training_providers = $record['clauses']['Training Providers'] ?? '';
      $node->dis_mental_brain_injury = $record['clauses']['Traumatic Brain Injury'] ?? '';
      $node->dis_act_walking = $record['clauses']['Walking'] ?? '';
      $node->field_work_assignment = $record['clauses']['Work Assignment'] ?? '';
      $node->dis_act_working = $record['clauses']['Working'] ?? '';
      $node->save();
    }

    return TRUE;
  }

  /**
   * Load or create a counsel paragraph entity.
   *
   * @param string $source
   *   The source of this firm; e.g. ada_case or consent_decree
   * @param integer $firm_id
   *   The legacy firm id from the import source.
   * @param array $firm_info
   *   An array containing the string `firmName` and the array `attorneys`, a
   *   list of attorney string names.
   *
   * @return \Drupal\paragraphs\Entity\Paragraph|NULL
   *   The paragraph entity representing the firm.
   */
  protected function getFirm($source, $firm_id = 0, $firm_info = []) {
    $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');
    $firm = NULL;

    if ($firm_id) {
      $existing_firms = $paragraph_storage->loadByProperties([
        'type' => 'counsel',
        'field_legacy_id' => $firm_id,
        'field_legacy_source' => $source,
      ]);

      if (empty($existing_firms)) {
        $firm = $paragraph_storage->create([
          'type' => 'counsel',
          'field_legacy_id' => $firm_id,
          'field_legacy_source' => $source,
        ]);
      }
      else {
        $firm = reset($existing_firms);
      }
      $firm->field_firm_name = $firm_info['firmName'];
      $firm->field_attorneys = $firm_info['attorneys'];
    }

    return $firm;
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
