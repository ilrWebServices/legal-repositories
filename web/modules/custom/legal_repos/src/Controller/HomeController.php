<?php

namespace Drupal\legal_repos\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;

/**
 * A the home page controller.
 */
class HomeController extends ControllerBase {

  /**
   * Returns a render-able array for the home page.
   */
  public function content() {
    $build = [
      '#theme' => 'item_list',
      '#items' => [
        Link::createFromRoute($this->t('Consent Decree Search'), 'legal_repos.title-vii-consent-decree.search'),
      ],
    ];
    return $build;
  }

}
