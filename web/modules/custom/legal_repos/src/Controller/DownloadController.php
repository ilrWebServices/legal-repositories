<?php

namespace Drupal\legal_repos\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\legal_repos\Entity\LegalDocumentBase;
use Drupal\paragraphs\Entity\Paragraph;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The download request controller.
 */
class DownloadController extends ControllerBase {

  /**
   * Returns a CSV export for the download request.
   */
  public function content(Request $request) {
    $hash = $request->get('hash');
    $nids = \Drupal::keyValue('results_download_hash')->get($hash);
    $download_type = $request->get('preview') ? 'inline' : 'attachment';
    $long_text_presence = $request->get('mini') ? TRUE : FALSE;

    // Load the nodes.
    if (empty($nids)) {
      // throw new BadRequestHttpException();
      throw new NotFoundHttpException();
    }
    else {
      $results = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple(explode('.', $nids));
    }

    // Create a CSV file in memory. See https://www.php.net/manual/en/wrappers.php.php
    $csv_file = fopen('php://temp', 'r+');

    // Create the header row dynamically from the fields available on node type.
    if ($results) {
      /** @var LegalDocumentBase $first_result */
      $first_result = reset($results);
      $field_definitions = $first_result->getFieldDefinitions();
      $row_info = [];

      foreach ($field_definitions as $field_name => $field_definition) {
        if ($field_name === 'nid') {
          $row_info['id'] = $field_definition;
        }
        else if ($field_name === 'title') {
          $row_info['case_name'] = $field_definition;
        }
        else if (strpos($field_name, 'field_') === 0) {
          // dump($field_name);
          // dump($field_definition->getType());
          $row_info[substr($field_name, 6)] = $field_definition;
        }
      }
    }

    // dump($row_info);

    // The first row.
    fputcsv($csv_file, array_keys($row_info));

    // Add rows to the CSV.
    foreach ($results as $nid => $node) {
      if (!$node instanceof LegalDocumentBase) {
        throw new BadRequestHttpException();
      }

      // Every result should be the same bundle as the first result. If it's
      // not, there was probably some tampering of the query param.
      if ($node->bundle() !== $first_result->bundle()) {
        throw new BadRequestHttpException();
      }

      $row = [];

      foreach ($row_info as $row_field_name => $field_definition) {
        // dump($row_field_name);
        // dump($field_definition->getType());

        // Use a 1 or 0 if only the presence of a value is requested.
        if ($long_text_presence && $field_definition->getType() === 'text_long') {
          $row[] = $node->get($field_definition->getName())->isEmpty() ? 0 : 1;
        }
        else if ($field_definition->getType() === 'entity_reference_revisions') {
          $counsel_data = [];

          foreach ($node->get($field_definition->getName())->referencedEntities() as $entity) {
            if ($entity instanceof Paragraph && $entity->bundle() === 'counsel') {
              $counsel_data[] = $entity->field_firm_name->getString();

              foreach (array_column($entity->field_attorneys->getValue(), 'value') as $attorney) {
                $counsel_data[] = "\t" . $attorney;
              }
            }
          }

          $row[] = implode("\n", $counsel_data);
        }
        // The getString() method works for string (e.g. `text_long`), `link`,
        // `datetime`, and other field types. Multiple items are comma
        // delimited.
        else {
          $row[] = $node->get($field_definition->getName())->getString();
        }
      }

      fputcsv($csv_file, $row);
    }

    rewind($csv_file);
    $content = stream_get_contents($csv_file);

    // Generate a unique file name.
    $filename_parts = [
      $first_result->bundle() . '-download',
      md5($nids),
      $long_text_presence ? 'mini' : 'full',
      date('Y-m-d'),
    ];

    // Return the file.
    return new Response($content, 200, [
      'Content-Type' => $download_type === 'inline' ? 'text/plain' : 'text/csv',
      'Content-Disposition' => sprintf('%s; filename="%s"', $download_type, implode('_', $filename_parts) . '.csv'),
    ]);
  }

}
