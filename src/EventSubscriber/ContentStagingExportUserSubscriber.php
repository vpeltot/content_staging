<?php

namespace Drupal\content_staging\EventSubscriber;

use Drupal\content_staging\Event\ContentStagingBeforeExportEvent;
use Drupal\content_staging\Event\ContentStagingEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribe to ContentStagingEvents::BEFORE_EXPORT events.
 *
 * Perform action before export user entities.
 */
class ContentStagingExportUserSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ContentStagingEvents::BEFORE_EXPORT][] = ['deleteAdminUser', -10];

    return $events;
  }

  /**
   * Don't export the anonymous and admin user.
   *
   * @param \Drupal\content_staging\Event\ContentStagingBeforeExportEvent $event
   */
  public function deleteAdminUser(ContentStagingBeforeExportEvent $event) {
    if ($event->getEntityTypeId() == 'user') {
      $entities = $event->getEntities();
      foreach ($entities[$event->getEntityTypeId()] as $entity_id => $entity) {
        /** @var \Drupal\user\Entity\User $entity */
        if (in_array($entity->id(), [0, 1])) {
          unset($entities[$event->getEntityTypeId()][$entity_id]);
        }
      }
      $event->setEntities($entities);
    }
  }

}
