<?php

namespace Drupal\content_staging\EventSubscriber;

use Drupal\content_staging\Event\ContentStagingEvents;
use Drupal\content_staging\Event\ContentStagingProcessFieldDefinitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribe to ContentStagingEvents::PROCESS_FIELD_DEFINITION events.
 *
 * Get the migration definition for processing the block content uuid field.
 */
class ContentStagingProcessUuidFieldSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ContentStagingEvents::PROCESS_FIELD_DEFINITION][] = ['getProcessFieldDefinition', -10];

    return $events;
  }

  /**
   * Get the the process definition.
   *
   * @param \Drupal\content_staging\Event\ContentStagingProcessFieldDefinitionEvent $event
   */
  public function getProcessFieldDefinition(ContentStagingProcessFieldDefinitionEvent $event) {
    if ($event->getFieldDefinition()->getName() == 'uuid') {
      // It's necessary for a block content to keep the original uuid.
      if ($event->getEntityType()->id() == 'block_content') {
        $event->setProcessFieldDefinition([
          $event->getFieldDefinition()->getName() => $event->getFieldDefinition()->getName(),
        ]);
      }
      // For all cases, stop propagation.
      $event->stopPropagation();
    }
  }

}
