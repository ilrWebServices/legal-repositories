<?php

namespace Drupal\legal_repos\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The redirect controller.
 */
class RedirectController extends ControllerBase {

  /**
   * Returns redirect for legacy paths.
   *
   * /consentdecree -> /title-vii-consent-decree
   * /ada-repository -> /ada-case
   * /consentdecree/index.html?action=displaySavedDecree&fn=04-C-2708-1.html -> /title-vii-consent-decree/04-C-2708-1
   * /ada-repository/index.html?action=displaySavedDecree&fn=1-99-cv-02060-1.html -> /ada-case/1-99-cv-02060-1
   *
   * @see legal_repos.routing.yml
   */
  public function legacyRedirect(Request $request) {
    $path = $request->getPathInfo();

    if (strpos($path, '/consentdecree') === 0) {
      $uri = '/title-vii-consent-decree';
    }
    elseif (strpos($path, '/ada-repository') === 0) {
      $uri = '/ada-case';
    }
    else {
      throw new NotFoundHttpException();
    }

    if ($doc = $request->query->get('fn')) {
      $uri .= '/' . str_replace('.html', '', $doc);
    }

    return new RedirectResponse($uri, 301);
  }

}
