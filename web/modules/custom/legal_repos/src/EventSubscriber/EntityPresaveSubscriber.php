<?php

namespace Drupal\legal_repos\EventSubscriber;

use Drupal\entity_events\Event\EntityEvent;
use Drupal\entity_events\EventSubscriber\EntityEventPresaveSubscriber;
use Drupal\node\NodeInterface;
use Drupal\Core\Cache\Cache;

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

    // Hard-code the path alias based on the case_number and version. Path auto
    // is for the weak.
    $clean_case_number = preg_replace('/[[:punct:]]/',' ', $entity->field_case_number->value);
    $clean_case_number = preg_replace('/[ ]+/','-', trim($clean_case_number));
    $calculated_alias = '/' . str_replace('_', '-', $entity->bundle()) . '/' . $clean_case_number . '-' . $entity->field_version_number->value;

    $path_alias = $entity->path->first();
    $path_alias->alias = $calculated_alias;

    // Force cache invalidation for this node to pick up changes to the path.
    Cache::invalidateTags($entity->getCacheTagsToInvalidate());
  }

}
