<?php

namespace App;

use Doctrine\ORM\Id\AbstractIdGenerator;
use Doctrine\ORM\EntityManager;
use App\Entity\LegalDocument;

class LegalDocumentPersistentIdGenerator extends AbstractIdGenerator {

  public function generate(EntityManager $em, $entity) {
    if (!$entity instanceof LegalDocument) {
      throw new \Exception('LegalDocumentPersistentIdGenerator only works on LegalDocument entities.');
    }
    /*
    <cfset revisedCaseNumber = getCaseNumber.casenumber>
		<cfset revisedCaseNumber = ReReplace(revisedCaseNumber,"[[:punct:]]"," ","ALL")>
		<cfset revisedCaseNumber = ReReplace(revisedCaseNumber,"[ ]+"," ","ALL")>
		<cfset revisedCaseNumber = trim(revisedCaseNumber)>
		<cfset revisedCaseNumber = replace(revisedCaseNumber," ", "-", "ALL")>
		<cfset revisedCaseNumber = revisedCaseNumber & "-" & getCaseNumber.versionNumber>
    */
    $clean_case_number = preg_replace(
      [
        '|[[:punct:]]|',
        '|[ ]+|',
        '| |'
      ],
      [
        ' ',
        ' ',
        '-',
      ],
      $entity->getCaseNumber()
    );

    return trim($clean_case_number, '-') . '-' . $entity->getVersion();
  }

}
