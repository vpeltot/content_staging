<?php

namespace Drupal\content_staging\EventSubscriber;

use Drupal\content_staging\Event\ContentStagingEvents;
use Drupal\content_staging\Event\ContentStagingProcessFieldDefinitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribe to ContentStagingEvents::PROCESS_FIELD_DEFINITION events.
 *
 * Get the migration definition for processing a basic field.
 */
class ContentStagingProcessFieldSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ContentStagingEvents::PROCESS_FIELD_DEFINITION][] = ['getProcessFieldDefinition', -100];

    return $events;
  }

  /**
   * Get the the process definition.
   *
   * @param \Drupal\content_staging\Event\ContentStagingProcessFieldDefinitionEvent $event
   */
  public function getProcessFieldDefinition(ContentStagingProcessFieldDefinitionEvent $event) {
    if (!$event->getFieldDefinition()->isTranslatable()) {
      $event->setProcessFieldDefinition([
        $event->getFieldDefinition()->getName() => $event->getFieldDefinition()->getName(),
      ]);
    }
    else {
      $event->setProcessFieldDefinition([
        $event->getFieldDefinition()->getName() => [
          'plugin' => 'get',
          'source' => $event->getFieldDefinition()->getName(),
          'language' => '@langcode',
        ],
      ]);
    }
  }

}
