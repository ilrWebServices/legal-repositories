<?php

namespace Drupal\legal_repos\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\node\Entity\Node;

class LegalDocumentBase extends Node {

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    if ($this->field_resource_url->isEmpty()) {
      return;
    }

    $this->field_resource_pdf_url = $this->getEcommonsPdfUrl($this->field_resource_url->uri);
  }

  /**
   * Get the PDF URL from an eCommons resource page URL.
   *
   * @todo Consider using the DSpace REST API
   *   (https://wiki.lyrasis.org/display/DSDOC6x/REST+API). See DSpace_API.http.
   *
   * @param string $url
   *   The eCommons page containing a PDF link.
   *
   * @return string
   */
  protected function getEcommonsPdfUrl(string $url) {
    try {
      /** @var \Psr\Http\Message\ResponseInterface $response */
      $response = $this->httpClient()->get($url);
    }
    catch(\Exception $e) {
      return '';
    }

    $dom = new \DOMDocument;
    libxml_use_internal_errors(TRUE);
    $dom->loadHTML($response->getBody());
    libxml_clear_errors();

    /** @var \DOMElement $link */
    foreach ($dom->getElementsByTagName('a') as $link) {
      $href = $link->getAttribute('href');

      if (preg_match('|^/bitstream/handle.*\.pdf|', $href)) {
        $url_parts = parse_url($url);
        return $url_parts['scheme'] . '://' . $url_parts['host'] . $href;
      }
    }

    return '';
  }

  /**
   * Get the http_client service.
   *
   * @return \GuzzleHttp\Client
   */
  protected function httpClient() {
    return \Drupal::service('http_client');
  }

}
