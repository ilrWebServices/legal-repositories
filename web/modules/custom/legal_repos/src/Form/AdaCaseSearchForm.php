<?php

namespace Drupal\legal_repos\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\legal_repos\Form\SearchFormBase;

class AdaCaseSearchForm extends SearchFormBase {

  protected $nodeType = 'ada_case';

  protected $clauseFields = [
    'Disability - Physical Impairment' => [
      'dis_phys_allergies',
      'dis_phys_alzheimers',
      'dis_phys_asthma',
      'dis_phys_blindness',
      'dis_phys_cancer',
      'dis_phys_cerebral_palsy',
      'dis_phys_chemical_sensitivity',
      'dis_phys_cystic_fibrosis',
      'dis_phys_deafness',
      'dis_phys_diabetes',
      'dis_phys_disfigurement',
      'dis_phys_dwarfism',
      'dis_phys_epilepsy',
      'dis_phys_gastrointestinal',
      'dis_phys_genetic',
      'dis_phys_heart_disease',
      'dis_phys_hiv_infection',
      'dis_phys_kidney_impairment',
      'dis_phys_limbs_missing',
      'dis_phys_mobility_wheelchair',
      'dis_phys_multiple_sclerosis',
      'dis_phys_muscular_dystrophy',
      'dis_phys_ortho_impairment',
      'dis_phys_paralysis',
      'dis_phys_pregnancy_compl',
      'dis_phys_speech_impairmnt',
      'dis_phys_thyroid_disorders',
      'dis_phys_tuberculosis',
      'dis_phys_other',
    ],
    'Disability - Mental Impairment' => [
      'dis_mental_alcoholism',
      'dis_mental_autism_spectrum',
      'dis_mental_bipolar_disorder',
      'dis_mental_depression',
      'dis_mental_drug_addiction',
      'dis_mental_intellectual',
      'dis_mental_ld_adhd',
      'dis_mental_mdd',
      'dis_mental_ocd',
      'dis_mental_ptsd',
      'dis_mental_schizophrenia',
      'dis_mental_brain_injury',
      'dis_mental_other',
    ],
    'Disability - Major Life Activities' => [
      'dis_act_bending',
      'dis_act_breathing',
      'dis_act_caring_oneself',
      'dis_act_communicating',
      'dis_act_concentrating',
      'dis_act_eating',
      'dis_act_hearing',
      'dis_act_interacting',
      'dis_act_learning',
      'dis_act_lifting',
      'dis_act_major_bodily_func_ops',
      'dis_act_manual_tasks',
      'dis_act_reaching',
      'dis_act_reading',
      'dis_act_reproduction',
      'dis_act_seeing',
      'dis_act_sitting',
      'dis_act_sleeping',
      'dis_act_speaking',
      'dis_act_standing',
      'dis_act_thinking',
      'dis_act_walking',
      'dis_act_working',
      'dis_act_other',
    ],
    'Disability - Other' => [
      'dis_other_record',
      'dis_other_regarded',
      'dis_other_relationship_assoc',
    ],
    'Procedural Category Sections' => [
      'field_court_juris_retained',
      'field_fairness_hearings',
      'field_enforceability_procedures',
      'field_class_notice',
      'field_releases',
      'field_confidentiality',
    ],
    'Evidence Sections' => [
      'field_experts',
      'field_expert_selection_process',
      'field_expert_responsibilities',
      'field_expert_fees',
    ],
    'Remedy Sections' => [
      'field_ada_coordinator',
      'field_settlement_funds_lower',
      'field_settlement_funds_upper',
      'field_backpay_frontpay',
      'field_complaint_procedures',
      'field_diversity_awards',
      'field_eeo_postings_and_policies',
      'field_employee_compensation',
      'field_evaluation',
      'field_expunge_record',
      'field_hiring_promotions',
      'field_human_resource_policies',
      'field_injunction',
      'field_internship_prog_stud_disab',
      'field_new_staff_positions',
      'field_notice_of_rights',
      'field_print_media_notice',
      'field_recruitment',
      'field_reinstatement',
      'field_surplus_funds_non_profits',
      'field_training_progs_discrim',
      'field_training_progs_diversity',
      'field_work_assignment',
    ],
    'Enforcement Sections' => [
      'field_progress_performance_rpt',
      'field_successor_bligations',
      'field_record_retention',
      'field_administration_claims',
      'field_hearing_claims',
      'field_consultants_utilized',
      'field_consultant_selection_proc',
      'field_consultant_responsibility',
      'field_consultant_fees',
      'field_mediators_utilized',
      'field_mediator_selection_process',
      'field_mediator_responsibilities',
      'field_mediator_fees',
      'field_internal_monitors',
      'field_internal_monitor_selection',
      'field_internal_monitor_responsib',
      'field_ext_monitors',
      'field_ext_monitor_selection_proc',
      'field_ext_monitor_responsibility',
      'field_monitor_fees',
      'field_special_masters_utilized',
      'field_spmaster_selection_process',
      'field_spmaster_responsibilities',
      'field_special_master_fees',
      'field_training_providers',
    ],
    'Special Issues Sections' => [
      'field_coll_bargaining_agreement',
      'field_other_special_issues',
    ],
  ];

  public function getFormId() {
    return 'ada_case_search';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    // This is a unique field in the General Case Data
    $form['search']['general'] += $this->getListFieldSelectInput('field_type_of_decision');

    $form['search'] += $this->getListFieldInput('field_theory_discrim_pre_empl');
    $form['search'] += $this->getListFieldInput('field_theory_discrim_empl');
    $form['search'] += $this->getListFieldInput('field_type_discrim_pre_empl');
    $form['search'] += $this->getListFieldInput('field_type_of_discrimination_emp');

    $form['search']['clauses'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Additional Information'),
    ];

    $form['search']['clauses']['clause_condition'] = [
      '#type' => 'radios',
      '#options' => [
        'any' => $this->t('Match any'),
        'all' => $this->t('Match all'),
      ],
      '#default_value' => 'any',
    ];

    foreach ($this->clauseFields as $clause_group => $field_names) {
      $form['search']['clauses'][$clause_group] = [
        '#type' => 'fieldset',
        '#title' => $this->t($clause_group),
      ];

      foreach ($field_names as $field_name) {
        $form['search']['clauses'][$clause_group] += $this->getTextFieldInput($field_name);
      }
    }

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    /** @var \Drupal\Core\Entity\Query\Sql\Query $query */
    $query = $this->getQuery();

    $this->addQueryGeneralConditions($query, $form_state);

    $values = $form_state->getValues();

    // Type of decision is in the general case data section only for ADA cases.
    if ($values['field_type_of_decision']) {
      $query->condition('field_type_of_decision', $values['field_type_of_decision']);
    }

    $this->addQueryListCondition($query, $form_state, 'field_theory_discrim_pre_empl');
    $this->addQueryListCondition($query, $form_state, 'field_theory_discrim_empl');
    $this->addQueryListCondition($query, $form_state, 'field_type_discrim_pre_empl');
    $this->addQueryListCondition($query, $form_state, 'field_type_of_discrimination_emp');

    if ($values['clause_condition'] === 'any') {
      /** @var \Drupal\Core\Entity\Query\Sql\Condition $clause_group */
      $clause_group = $query->orConditionGroup();
    }
    else {
      /** @var \Drupal\Core\Entity\Query\Sql\Condition $clause_group */
      $clause_group = $query->andConditionGroup();
    }

    foreach ($this->clauseFields as $field_names) {
      foreach ($field_names as $field_name) {
        if ($values[$field_name]) {
          $clause_group->exists($field_name);
        }
      }
    }

    if ($clause_group->count() > 0) {
      $query->condition($clause_group);
    }

    $results_obj = $this->executeQuery($query);
    $form_state->set('results', $results_obj);
  }

}
