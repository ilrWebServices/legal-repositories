<?php

namespace Drupal\legal_repos\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\legal_repos\Form\SearchFormBase;

class ConsentDecreeSearchForm extends SearchFormBase {

  protected $nodeType = 'title_vii_consent_decree';

  protected $clauseFields = [
    'Procedural Category Clauses' => [
      'field_court_juris_retained',
      'field_fairness_hearings',
      'field_enforceability_procedures',
      'field_class_notice',
      'field_releases',
      'field_confidentiality',
    ],
    'Evidence Clauses' => [
      'field_experts',
      'field_expert_selection_process',
      'field_expert_responsibilities',
      'field_expert_fees',
    ],
    'Remedy Clauses' => [
      'field_backpay_frontpay',
      'field_complaint_procedures',
      'field_eeo_postings_and_policies',
      'field_employee_compensation',
      'field_evaluation',
      'field_expunge_record',
      'field_hiring_promotions',
      'field_human_resource_policies',
      'field_new_staff_positions',
      'field_non_retaliation',
      'field_recruitment',
      'field_training_progs_advancement',
      'field_training_progs_discrim',
      'field_training_progs_diversity',
      'field_work_assignment',
    ],
    'Enforcement Clauses' => [
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
    'Special Issues Clauses' => [
      'field_coll_bargaining_agreement',
      'field_other_special_issues',
    ],
  ];

  public function getFormId() {
    return 'consent_decree_search';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $results = $form_state->get('results');

    if ($results) {
      dump($results);
      $view_builder = $this->entityTypeManager->getViewBuilder('node');

      $form['results'] = [
        '#type' => 'details',
        '#title' => $results->count . ' ' . $this->stringTranslation->formatPlural(count($results->nodes), ' Result', ' Results'),
        '#open' => (bool) $results->count,
      ];

      foreach ($results->nodes as $node) {
        $build = $view_builder->view($node, 'teaser');
        $form['results'][$node->id()] = $build;
      }
    }

    $form['search'] += $this->getListFieldInput('field_protected_classes');
    $form['search'] += $this->getListFieldInput('field_theory_of_discrimination');
    $form['search'] += $this->getListFieldInput('field_type_of_discrimination');

    $form['search']['clauses'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Clauses'),
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
    dump($values);

    $this->addQueryListCondition($query, $form_state, 'field_protected_classes');
    $this->addQueryListCondition($query, $form_state, 'field_theory_of_discrimination');
    $this->addQueryListCondition($query, $form_state, 'field_type_of_discrimination');

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
          $clause_group->condition($field_name, null, 'IS NOT NULL');
        }
      }
    }

    if ($clause_group->count() > 0) {
      $query->condition($clause_group);
    }

    dump($query->__toString());
    $results = $query->execute();
    $results_obj = new \stdClass();

    if ($results) {
      $results_obj->nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($results);
      $results_obj->count = count($results_obj->nodes);
    }
    else {
      $results_obj->nodes = [];
      $results_obj->count = 0;
    }

    $form_state->set('results', $results_obj);
  }

}
