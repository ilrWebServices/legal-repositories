<?php

namespace Drupal\legal_repos;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Messenger\MessengerInterface;

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
   * Constructs a new legacy content importer.
   *
   * @param EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param ModuleHandlerInterface $module_handler
   *   The moduler handler service.
   * @param MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, MessengerInterface $messenger) {
    $this->entityTypeManager = $entity_type_manager;
    $this->dataPath = $module_handler->getModule('legal_repos')->getPath() . '/data/';
    $this->messenger = $messenger;
  }

  public function import() {
    $node_storage = $this->entityTypeManager->getStorage('node');
    $base_cd_file_handle = fopen($this->dataPath . 'consentDecree.csv', 'r+');
    $rows = 0;
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
          // Create a new consent decree.
          $node = $node_storage->create([
            'type' => 'title_vii_consent_decree',
            'field_legacy_id' => $record['cdid'],
          ]);
        }
        elseif (count($existing_nodes) === 1) {
          $node = reset($existing_nodes);
        }
        // More than one node was returned.
        else {
          // @todo Log error?
          continue;
        }

        $node->created = strtotime(substr($record['cdCreateDate'], 0, 20));
        $node->changed = strtotime(substr($record['lastModifiedDate'], 0, 20));
        $node->status = ($record['hidden'] === 'False') ? 1 : 0;
        $node->title = $record['caseName'];
        $node->field_case_number = $record['caseNumber'];
        $node->field_version_number = $record['versionNumber'];
        $node->field_state = $record['state'];
        $node->field_state_claims = $record['stateClaim'] === 'True' ? 1 : 0;
        $node->field_judge = $record['judgeFullName'];
        $node->field_digital_commons_link = $record['digitalCommonsURL'];
        $node->field_digital_commons_pdf_link = $record['digitalCommonsPdfURL'];
        $node->field_jurisdiction = $record['court'];
        $node->field_date_lawsuit_filed = substr($record['lawsuitFiledDate'], 0, 10);
        $node->field_date_consent_decree_filed = substr($record['cdFiledDate'], 0, 10);
        $node->field_date_consent_decree_signed = substr($record['cdSignedDate'], 0, 10);
        $node->field_effective_date = $record['effectiveDate'];
        $node->field_duration = $record['durationNumberOfYears'];
        $node->field_duration_text = $record['durationText'];
        $node->field_plaintiffs_attorneys_fees = $record['plaintiffsAttorneysFees'];
        $node->setRevisionLogMessage($record['internComments']);
        $node->save();

        if ($rows === 10) {
          break;
        }
      }
    }

    fclose($base_cd_file_handle);

    // Update the consent decrees with expanded data from the exported download
    // file.
  }
}
