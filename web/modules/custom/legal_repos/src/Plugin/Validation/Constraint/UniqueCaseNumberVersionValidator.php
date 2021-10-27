<?php

namespace Drupal\legal_repos\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the UniqueCaseNumberVersion constraint.
 */
class UniqueCaseNumberVersionValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint) {
    if (!$entity->hasField('field_case_number') || !$entity->hasField('field_version_number')) {
      return;
    }

    // Look for an existing node of the same type with the same case_number and
    // version combo.
    $query = \Drupal::entityQuery($entity->getEntityTypeId());
    $query->condition('type', $entity->bundle());
    $query->condition('field_case_number', $entity->field_case_number->value);
    $query->condition('field_version_number', $entity->field_version_number->value);

    if (!$entity->isNew()) {
      $query->condition('nid', $entity->id(), '!=');
    }

    if ($query->count()->execute()) {
      $this->context->addViolation($constraint->message, [
        '%type' => $entity->type->entity->label(),
        '%case_number' => $entity->field_case_number->value,
        '%version' => $entity->field_version_number->value,
      ]);
    }
  }

}
