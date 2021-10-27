<?php

namespace Drupal\legal_repos\EventSubscriber;

use Drupal\entity_events\Event\EntityEvent;
use Drupal\entity_events\EventSubscriber\EntityEventPresaveSubscriber;
use Drupal\node\NodeInterface;

class EntityPresaveSubscriber extends EntityEventPresaveSubscriber {

  /**
   * {@inheritdoc}
   */
  public function onEntityPresave(EntityEvent $event) {
    $entity = $event->getEntity();

    if (!$entity instanceof NodeInterface) {
      return;
    }

    if ($entity->bundle() !== 'title_vii_consent_decree') {
      return;
    }

    // Set circuit court based on state.
    switch ($entity->field_state->value) {
      case 'District of Columbia':
        $entity->field_circuit = 'District of Columbia';
        break;
      case 'Maine':
      case 'Massachusetts':
      case 'New Hampshire':
      case 'Rhode Island':
      case 'Puerto Rico':
        $entity->field_circuit = '1st';
        break;
      case 'Connecticut':
      case 'New York':
      case 'Vermont':
        $entity->field_circuit = '2nd';
        break;
      case 'Delaware':
      case 'New Jersey':
      case 'Pennsylvania':
      case 'Virgin Islands':
        $entity->field_circuit = '3rd';
        break;
      case 'Maryland':
      case 'North Carolina':
      case 'South Carolina':
      case 'Virginia':
      case 'West Virginia':
        $entity->field_circuit = '4th';
        break;
      case 'Louisiana':
      case 'Mississippi':
      case 'Texas':
      case 'Canal Zone':
        $entity->field_circuit = '5th';
        break;
      case 'Kentucky':
      case 'Michigan':
      case 'Ohio':
      case 'Tennessee':
        $entity->field_circuit = '6th';
        break;
      case 'Illinois':
      case 'Indiana':
      case 'Wisconsin':
        $entity->field_circuit = '7th';
        break;
      case 'Arkansas':
      case 'Iowa':
      case 'Minnesota':
      case 'Missouri':
      case 'Nebraska':
      case 'North Dakota':
      case 'South Dakota':
        $entity->field_circuit = '8th';
        break;
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
        $entity->field_circuit = '9th';
        break;
      case 'Colorado':
      case 'Kansas':
      case 'New Mexico':
      case 'Oklahoma':
      case 'Utah':
      case 'Wyoming':
        $entity->field_circuit = '10th';
        break;
      case 'Alabama':
      case 'Florida':
      case 'Georgia':
        $entity->field_circuit = '11th';
        break;
      default:
        $entity->field_circuit = 'other';
    }
  }

}
