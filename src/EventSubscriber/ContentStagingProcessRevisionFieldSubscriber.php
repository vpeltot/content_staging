<?php

namespace Drupal\content_staging\EventSubscriber;

use Drupal\content_staging\Event\ContentStagingEvents;
use Drupal\content_staging\Event\ContentStagingProcessFieldDefinitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribe to ContentStagingEvents::PROCESS_FIELD_DEFINITION events.
 *
 * Get the migration definition for processing a revision field.
 */
class ContentStagingProcessRevisionFieldSubscriber implements EventSubscriberInterface {

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
    // Do not process revision field and stop progation...
    if ($event->getFieldDefinition()->getName() == $event->getEntityType()->getKey('revision')) {
      // ... Except for paragraph revision field.
      if ($event->getEntityType()->id() == 'paragraph') {
        $event->setProcessFieldDefinition([
          $event->getFieldDefinition()->getName() => $event->getFieldDefinition()->getName()
        ]);
      }
      $event->stopPropagation();
    }
  }

}
