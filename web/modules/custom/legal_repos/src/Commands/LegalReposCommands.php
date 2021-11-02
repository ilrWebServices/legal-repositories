<?php

namespace Drupal\legal_repos\Commands;

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
   */
  public function import() {
    $result = $this->importer->import();
    $this->logger()->success(dt('Legal repos legacy content importer completed @result. Check logs for results.', [
      '@result' => $result ? 'successfully' : 'with issues',
    ]));
  }

}
