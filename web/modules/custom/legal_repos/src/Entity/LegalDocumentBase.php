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

    if (!$this->field_resource_url->isEmpty()) {
      $this->field_resource_pdf_url = $this->getEcommonsPdfUrl($this->field_resource_url->uri);
    }

    if (!$this->field_state->isEmpty()) {
      $this->field_circuit = $this->getCircuitForState($this->field_state->value);
    }
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

  protected function getCircuitForState(string $state): string {
    switch ($state) {
      case 'District of Columbia':
        return 'District of Columbia';
      case 'Maine':
      case 'Massachusetts':
      case 'New Hampshire':
      case 'Rhode Island':
      case 'Puerto Rico':
        return '1st';
      case 'Connecticut':
      case 'New York':
      case 'Vermont':
        return '2nd';
      case 'Delaware':
      case 'New Jersey':
      case 'Pennsylvania':
      case 'Virgin Islands':
        return '3rd';
      case 'Maryland':
      case 'North Carolina':
      case 'South Carolina':
      case 'Virginia':
      case 'West Virginia':
        return '4th';
      case 'Louisiana':
      case 'Mississippi':
      case 'Texas':
      case 'Canal Zone':
        return '5th';
      case 'Kentucky':
      case 'Michigan':
      case 'Ohio':
      case 'Tennessee':
        return '6th';
      case 'Illinois':
      case 'Indiana':
      case 'Wisconsin':
        return '7th';
      case 'Arkansas':
      case 'Iowa':
      case 'Minnesota':
      case 'Missouri':
      case 'Nebraska':
      case 'North Dakota':
      case 'South Dakota':
        return '8th';
      case 'Alaska':
      case 'Arizona':
      case 'California':
      case 'Idaho':
      case 'Montana':
      case 'Nevada':
      case 'Oregon':
      case 'Washington':
      case 'Hawaii':
      case 'Guam':
      case 'Northern Mariana Islands':
        return '9th';
      case 'Colorado':
      case 'Kansas':
      case 'New Mexico':
      case 'Oklahoma':
      case 'Utah':
      case 'Wyoming':
        return '10th';
      case 'Alabama':
      case 'Florida':
      case 'Georgia':
        return '11th';
      default:
        return 'other';
    }
  }

}
