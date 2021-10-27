<?php

namespace Drupal\legal_repos\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that the submitted case_number and version is a unique combination.
 *
 * @Constraint(
 *   id = "UniqueCaseNumberVersion",
 *   label = @Translation("Unique Case Number and Version", context = "Validation"),
 *   type = {"entity"}
 * )
 */
class UniqueCaseNumberVersion extends Constraint {

  // The message that will be shown if the case_number and version combo is not
  // unique.
  public $message = 'There is already a %type with the case number %case_number and version %version.';

}
