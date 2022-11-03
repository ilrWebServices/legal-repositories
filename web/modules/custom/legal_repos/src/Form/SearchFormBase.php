<?php

namespace Drupal\legal_repos\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class SearchFormBase extends FormBase {

  protected $entityFieldManager;

  protected $entityTypeManager;


  protected $fieldDefinitions = [];

  // Set this in classes that extend this class.
  protected $nodeType = '';

  public function __construct(EntityFieldManagerInterface $entity_field_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->fieldDefinitions = $entity_field_manager->getFieldDefinitions('node', $this->nodeType);
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager')
    );
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      '#button_type' => 'primary',
    ];

    $results = $form_state->get('results');

    if ($results) {
      $compare_field = $form_state->getValue('compare');

      $form['results'] = [
        '#type' => 'details',
        '#title' => $results->count . ' ' . $this->stringTranslation->formatPlural(count($results->nodes), ' Result', ' Results'),
        '#open' => (bool) $results->count,
      ];

      $form['results']['utils'] = [
        '#theme' => 'item_list',
        '#items' => [],
        '#list_type' => 'ul',
        '#attributes' => ['class' => 'result-utils'],
      ];

      $form['results']['utils']['#items'][] = [
        '#type' => 'link',
        '#url' => Url::fromRoute(\Drupal::routeMatch()->getRouteName()),
        '#title' => $this->t('New search'),
      ];

      $form['results']['utils']['#items'][] = [
        '#type' => 'dropbutton',
        '#dropbutton_type' => 'small',
        '#links' => [
          'download' => [
            'title' => $this->t('Download'),
            'url' => Url::fromRoute('legal_repos.download', [], [
              'query' => [
                'hash' => $results->download_hash,
                // 'preview' => 1,
              ],
            ]),
          ],
          'download_mini' => [
            'title' => $this->t('Download mini'),
            'url' => Url::fromRoute('legal_repos.download', [], [
              'query' => [
                'hash' => $results->download_hash,
                'mini' => 1,
              ],
            ]),
          ],
        ],
      ];

      $compare_opts = [
        '' => $this->t('--- Select ---'),
      ];

      foreach ($this->clauseFields as $clause_group => $field_names) {
        $compare_opts[$clause_group] = [];

        foreach ($field_names as $field_name) {
          if (isset($this->fieldDefinitions[$field_name])) {
            $compare_opts[$clause_group][$field_name] = $this->fieldDefinitions[$field_name]->getLabel();
          }
        }
      }

      $form['results']['compare_wrapper'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Compare results'),
        '#attributes' => ['class' => ['compare']],
      ];

      $form['results']['compare_wrapper']['compare'] = [
        '#type' => 'select',
        // '#title' => $this->t('Clause'),
        '#description' => $this->t('Select to compare across @things.', [
          '@thing' => $this->entityTypeManager->getStorage('node_type')->load($this->nodeType)->label(),
        ]),
        '#options' => $compare_opts,
        '#default_value' => $compare_field,
      ];

      $form['results']['compare_wrapper']['compare_submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Compare'),
      ];

      $form['results']['links'] = [
        '#theme' => 'item_list',
        '#items' => [],
        '#empty' => $this->t('Please refine your search criteria.'),
        '#list_type' => 'ol',
        '#attributes' => ['class' => ['search-items']],
      ];

      foreach ($results->nodes as $node) {
        $form['results']['links']['#items'][$node->id()] = [
          '#theme' => 'container',
          '#attributes' => ['class' => ['result-details']],
        ];

        $view_mode = $compare_field ? 'teaser' : 'mini';
        $form['results']['links']['#items'][$node->id()]['#children'][] = $this->entityTypeManager->getViewBuilder('node')->view($node, $view_mode);

        if ($node->hasField($compare_field)) {
          if (!$node->get($compare_field)->isEmpty()) {
            $form['results']['links']['#items'][$node->id()]['#children'][] = $node->get($compare_field)->view();
          }
          else {
            $form['results']['links']['#items'][$node->id()]['#children'][] = [
              '#type' => 'inline_template',
              '#template' => '<p class="empty-clause">{% trans %}Clause <strong><em>{{clause}}</em></strong> doesn\'t appear in this {{thingamabob}}.{% endtrans %}</p>',
              '#context' => [
                'clause' => $this->fieldDefinitions[$compare_field]->getLabel(),
                'thingamabob' => $node->type->entity->label(),
              ],
            ];
          }
        }
      }
    }

    $form['search'] = [
      '#type' => (bool) $results ? 'details' : 'container',
      '#title' => $this->t('Search form'),
      '#open' => (bool) !$results,
    ];

    $form['search']['general'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('General Case Data'),
    ];

    $form['search']['general']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Case name or company'),
    ];

    $form['search']['general'] += $this->getListFieldSelectInput('field_circuit');

    $form['search']['general']['field_jurisdiction'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Jurisdiction'),
    ];

    $form['search']['general'] += $this->getListFieldSelectInput('field_state');

    $form['search']['general']['field_state_claims'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Includes a state claim'),
    ];

    $form['search']['general']['year_filed'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Filing year of original lawsuit'),
    ];

    $form['search']['general']['year_filed']['year_filed_start'] = [
      '#type' => 'select',
      '#title' => $this->t('In or after'),
      '#options' => array_combine($r = range(date('Y'), 1975), $r),
      '#empty_option' => $this->t('-- Year --'),
    ];

    $form['search']['general']['year_filed']['year_filed_end'] = [
      '#type' => 'select',
      '#title' => $this->t('In or before'),
      '#options' => array_combine($r = range(date('Y'), 1975), $r),
      '#empty_option' => $this->t('-- Year --'),
    ];

    $form['search']['general']['year_signed'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Signing year'),
    ];

    $form['search']['general']['year_signed']['year_signed_start'] = [
      '#type' => 'select',
      '#title' => $this->t('In or after'),
      '#options' => array_combine($r = range(date('Y'), 1975), $r),
      '#empty_option' => $this->t('-- Year --'),
    ];

    $form['search']['general']['year_signed']['year_signed_end'] = [
      '#type' => 'select',
      '#title' => $this->t('In or before'),
      '#options' => array_combine($r = range(date('Y'), 1975), $r),
      '#empty_option' => $this->t('-- Year --'),
    ];

    $form['search']['general']['duration'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Duration'),
    ];

    $form['search']['general']['duration']['duration_start'] = [
      '#type' => 'number',
      '#title' => $this->t('At least'),
      '#min' => 1,
      '#max' => 10,
      '#field_suffix' => $this->t('year(s)'),
    ];

    $form['search']['general']['duration']['duration_end'] = [
      '#type' => 'number',
      '#title' => $this->t('Up to'),
      '#min' => 1,
      '#max' => 10,
      '#field_suffix' => $this->t('year(s)'),
    ];

    // @todo Confirm that we want this new search field
    $form['search']['general']['field_judge'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Judge'),
    ];

    $form['search']['general']['law_firm'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Law Firm or Agency'),
    ];

    $form['search']['general']['attorney'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Attorney'),
    ];

    $form['search']['general'] += $this->getListFieldSelectInput('field_industry');

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
  }

  protected function addQueryGeneralConditions(QueryInterface $query, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    // Case name or company.
    if ($values['title']) {
      $query->condition('title', $values['title'], 'CONTAINS');
    }

    if ($values['field_circuit']) {
      $query->condition('field_circuit', $values['field_circuit']);
    }

    if ($values['field_jurisdiction']) {
      $query->condition('field_jurisdiction', $values['field_jurisdiction'], 'CONTAINS');
    }

    if ($values['field_state']) {
      $query->condition('field_state', $values['field_state']);
    }

    if ($values['field_state_claims']) {
      $query->condition('field_state_claims', $values['field_state_claims']);
    }

    // Confusingly, there are two fields that might represent this value:
    // `field_date_lawsuit_filed` and `field_date_filed`.
    if ($values['year_filed_start'] || $values['year_filed_end']) {
      $year_filed_group = $query->andConditionGroup();

      if ($values['year_filed_start']) {
        $year_filed_group->condition('field_date_lawsuit_filed', $values['year_filed_start'] . '-01-01', '>=');
      }

      if ($values['year_filed_end']) {
        $year_filed_group->condition('field_date_lawsuit_filed', $values['year_filed_end'] . '-12-31', '<=');
      }

      $query->condition($year_filed_group);
    }

    if ($values['year_signed_start'] || $values['year_signed_end']) {
      $year_signed_group = $query->andConditionGroup();

      if ($values['year_signed_start']) {
        $year_signed_group->condition('field_date_signed', $values['year_signed_start'] . '-01-01', '>=');
      }

      if ($values['year_signed_end']) {
        $year_signed_group->condition('field_date_signed', $values['year_signed_end'] . '-12-31', '<=');
      }

      $query->condition($year_signed_group);
    }

    if ($values['duration_start'] || $values['duration_end']) {
      $duration_group = $query->andConditionGroup();

      if ($values['duration_start']) {
        $duration_group->condition('field_duration', $values['duration_start'], '>=');
      }

      if ($values['duration_end']) {
        $duration_group->condition('field_duration', $values['duration_end'], '<=');
      }

      $query->condition($duration_group);
    }

    if ($values['field_judge']) {
      $query->condition('field_judge', $values['field_judge'], 'CONTAINS');
    }

    if ($values['law_firm']) {
      // Note that we use an addtional `andConditionGroup` to force a second
      // join to the `paragraph__field_firm_name` table.
      $law_firm_group = $query->orConditionGroup();
      $law_firm_group->condition('field_defendant_counsel.entity:paragraph.field_firm_name', $values['law_firm'], 'CONTAINS');
      $new_join_group = $query->andConditionGroup();
      $new_join_group->condition('field_plaintiff_counsel.entity:paragraph.field_firm_name', $values['law_firm'], 'CONTAINS');
      $law_firm_group->condition($new_join_group);
      $query->condition($law_firm_group);
    }

    if ($values['attorney']) {
      // Note that we use an addtional `andConditionGroup` to force a second
      // join to the `paragraph__field_attorneys` table.
      $attorney_group = $query->orConditionGroup();
      $attorney_group->condition('field_defendant_counsel.entity:paragraph.field_attorneys', $values['attorney'], 'CONTAINS');
      $new_join_group = $query->andConditionGroup();
      $new_join_group->condition('field_plaintiff_counsel.entity:paragraph.field_attorneys', $values['attorney'], 'CONTAINS');
      $attorney_group->condition($new_join_group);
      $query->condition($attorney_group);
    }

    if ($values['field_industry']) {
      $query->condition('field_industry', $values['field_industry']);
    }
  }

  protected function getListFieldInput($field_name) {
    if (!isset($this->fieldDefinitions[$field_name])) {
      return [];
    }

    $element[$field_name] = [
      '#type' => 'fieldset',
      '#title' => $this->fieldDefinitions[$field_name]->getLabel(),
    ];

    $element[$field_name][$field_name . '_condition'] = [
      '#type' => 'radios',
      '#options' => [
        'any' => $this->t('Match any'),
        'all' => $this->t('Match all'),
      ],
      '#default_value' => 'any',
    ];

    $element[$field_name][$field_name . '_values'] = [
      '#type' => 'checkboxes',
      '#options' => options_allowed_values($this->fieldDefinitions[$field_name]->getFieldStorageDefinition()),
    ];

    return $element;
  }

  protected function getListFieldSelectInput($field_name) {
    if (!isset($this->fieldDefinitions[$field_name])) {
      return [];
    }

    $element[$field_name] = [
      '#type' => 'select',
      '#title' => $this->fieldDefinitions[$field_name]->getLabel(),
      '#options' => options_allowed_values($this->fieldDefinitions[$field_name]->getFieldStorageDefinition()),
      '#empty_option' => '-- Any --',
    ];

    return $element;
  }

  protected function getTextFieldInput($field_name) {
    if (!isset($this->fieldDefinitions[$field_name])) {
      return [];
    }

    $element[$field_name] = [
      '#title' => $this->fieldDefinitions[$field_name]->getLabel(),
      '#type' => 'checkbox',
    ];

    return $element;
  }

  protected function getQuery() {
    $query = $this->entityTypeManager->getStorage('node')->getQuery();
    $query->condition('type', $this->nodeType);
    $query->condition('status', 1);
    $query->exists('field_resource_url');
    return $query;
  }

  protected function addQueryListCondition(QueryInterface $query, FormStateInterface $form_state, $field_name) {
    if (!isset($this->fieldDefinitions[$field_name])) {
      return;
    }

    $condition = $form_state->getValue($field_name . '_condition');
    $values = array_filter($form_state->getValue($field_name . '_values'));

    if (empty($values)) {
      return;
    }

    if ($condition === 'any') {
      $query->condition($field_name, $values, 'IN');
    }
    else {
      foreach ($values as $value) {
        $group = $query->andConditionGroup();
        $group->condition($field_name, $value);
        $query->condition($group);
      }
    }
  }

  public function executeQuery(QueryInterface $query) {
    $results = $query->execute();
    $results_obj = new \stdClass();

    if ($results) {
      $results_obj->nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($results);
      $results_obj->count = count($results_obj->nodes);
      $results_obj->download_hash = md5(implode('.', array_keys($results_obj->nodes)));

      // Save the download hash in the key/value store.
      \Drupal::keyValue('results_download_hash')->set($results_obj->download_hash, implode('.', array_keys($results_obj->nodes)));
    }
    else {
      $results_obj->nodes = [];
      $results_obj->count = 0;
      $results_obj->download_hash = '';
    }

    return $results_obj;
  }

}
