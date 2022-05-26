<?php

namespace Drupal\legal_repos\Commands;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drush\Commands\DrushCommands;
use Drupal\legal_repos\LegacyContentImporter;

class LegalReposCommands extends DrushCommands {
  /**
   * The importer service.
   *
   * @var \Drupal\legal_repos\LegacyContentImporter
   */
  protected $importer;

  /**
   * Creates a new legal repos commands object.
   *
   * @param LegacyContentImporter $importer
   *   The legacy importer service.
   */
  public function __construct(LegacyContentImporter $importer) {
    parent::__construct();
    $this->importer = $importer;
  }

  /**
   * Run the legacy content importer.
   *
   * @command legal_repos:import
   * @aliases lri
   * @options arr An option that takes multiple values
   * @options progress to display progress during importing.
   */
  public function import($options = ['progress' => FALSE]) {
    $result = $this->importer->import($options['progress']);
    $this->logger()->success(dt('Legal repos legacy content importer completed @result. Check logs for results.', [
      '@result' => $result ? 'successfully' : 'with issues',
    ]));
  }

  /**
   * Generate some ada case fields.
   *
   * @command legal_repos:ada_field_gen
   * @aliases lrafg
   */
  public function adaFieldGen() {
    $fields = [
      // Disability - Physical Impairment
      'dis_phys_allergies' => 'Allergies',
      'dis_phys_alzheimers' => 'Alzheimer\'s',
      'dis_phys_asthma' => 'Asthma',
      'dis_phys_blindness' => 'Blindness',
      'dis_phys_cancer' => 'Cancer',
      'dis_phys_cerebral_palsy' => 'Cerebral Palsy',
      'dis_phys_chemical_sensitivity' => 'Chemical Sensitivity',
      'dis_phys_cystic_fibrosis' => 'Cystic Fibrosis',
      'dis_phys_deafness' => 'Deafness',
      'dis_phys_diabetes' => 'Diabetes',
      'dis_phys_disfigurement' => 'Disfigurement',
      'dis_phys_dwarfism' => 'Dwarfism',
      'dis_phys_epilepsy' => 'Epilepsy',
      'dis_phys_gastrointestinal' => 'Gastrointestinal',
      'dis_phys_genetic' => 'Genetic Discrimination',
      'dis_phys_heart_disease' => 'Heart Disease',
      'dis_phys_hiv_infection' => 'HIV infection',
      'dis_phys_kidney_impairment' => 'Kidney Impairment',
      'dis_phys_limbs_missing' => 'Limbs partly or completely missing',
      'dis_phys_mobility_wheelchair' => 'Mobility impairments requiring use of wheelchair',
      'dis_phys_multiple_sclerosis' => 'Multiple Sclerosis',
      'dis_phys_muscular_dystrophy' => 'Muscular Dystrophy',
      'dis_phys_ortho_impairment' => 'Orthopedic Impairments',
      'dis_phys_paralysis' => 'Paralysis',
      'dis_phys_pregnancy_compl' => 'Pregnancy Complications',
      'dis_phys_speech_impairmnt' => 'Speech Impairment',
      'dis_phys_thyroid_disorders' => 'Thyroid Gland Disorders',
      'dis_phys_tuberculosis' => 'Tuberculosis',
      'dis_phys_other' => 'Other physical impairment disability',

      // Disability - Mental Impairment
      'dis_mental_alcoholism' => 'Alcoholism',
      'dis_mental_autism_spectrum' => 'Autism-Spectrum',
      'dis_mental_bipolar_disorder' => 'Bipolar Disorder',
      'dis_mental_depression' => 'Depression',
      'dis_mental_drug_addiction' => 'Drug Addiction',
      'dis_mental_intellectual' => 'Intellectual Disability',
      'dis_mental_ld_adhd' => 'Learning Disability/ADHD',
      'dis_mental_mdd' => 'Major Depressive Disorder',
      'dis_mental_ocd' => 'Obsessive Compulsive Disorder',
      'dis_mental_ptsd' => 'Post-Traumatic Stress Disorder',
      'dis_mental_schizophrenia' => 'Schizophrenia',
      'dis_mental_brain_injury' => 'Traumatic Brain Injury',
      'dis_mental_other' => 'Other mental impairment disability',

      // Disability - Major Life Activities
      'dis_act_bending' => 'Bending',
      'dis_act_breathing' => 'Breathing',
      'dis_act_caring_oneself' => 'Caring for Oneself',
      'dis_act_communicating' => 'Communicating',
      'dis_act_concentrating' => 'Concentrating',
      'dis_act_eating' => 'Eating',
      'dis_act_hearing' => 'Hearing',
      'dis_act_interacting' => 'Interacting with Others',
      'dis_act_learning' => 'Learning',
      'dis_act_lifting' => 'Lifting',
      'dis_act_major_bodily_func_ops' => 'Major Bodily Function Operations',
      'dis_act_manual_tasks' => 'Performing Manual Tasks',
      'dis_act_reaching' => 'Reaching',
      'dis_act_reading' => 'Reading',
      'dis_act_reproduction' => 'Reproduction',
      'dis_act_seeing' => 'Seeing',
      'dis_act_sitting' => 'Sitting',
      'dis_act_sleeping' => 'Sleeping',
      'dis_act_speaking' => 'Speaking',
      'dis_act_standing' => 'Standing',
      'dis_act_thinking' => 'Thinking',
      'dis_act_walking' => 'Walking',
      'dis_act_working' => 'Working',
      'dis_act_other' => 'Other major life activity disability',

      // Disability - Other
      'dis_other_record' => 'Disability - Record of Disability',
      'dis_other_regarded' => 'Disability - Regarded as Having a Disability',
      'dis_other_relationship_assoc' => 'Disability - Relationship/Association to a Person with a Disability',
    ];

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');

    foreach ($fields as $field_name => $label) {
      $field_storage = [
        'field_name' => $field_name,
        'entity_type' => 'node',
        'type' => 'text_long',
      ];
      FieldStorageConfig::create($field_storage)->save();
      $field = [
        'field_name' => $field_storage['field_name'],
        'entity_type' => 'node',
        'bundle' => 'ada_case',
        'label' => $label,
      ];
      FieldConfig::create($field)->save();

      // Assign widget settings for the default form mode.
      $display_repository->getFormDisplay('node', 'ada_case')
        ->setComponent($field_name, [
          'type' => 'text_textarea',
        ])
        ->save();
    }
  }

}
