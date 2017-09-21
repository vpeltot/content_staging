<?php

namespace Drupal\content_staging\EventSubscriber;

use Drupal\content_staging\Event\ContentStagingBeforeExportEvent;
use Drupal\content_staging\Event\ContentStagingEvents;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribe to ContentStagingEvents::BEFORE_EXPORT events.
 *
 * Perform action before export user entities.
 */
class ContentStagingExportTaxonomyTermSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * ContentStagingExportTaxonomyTermSubscriber constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The content staging manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ContentStagingEvents::BEFORE_EXPORT][] = ['exportTaxonomyTerm', -10];

    return $events;
  }

  /**
   * Export the parent anonymous and admin user.
   *
   * @param \Drupal\content_staging\Event\ContentStagingBeforeExportEvent $event
   *   The event.
   */
  public function exportTaxonomyTerm(ContentStagingBeforeExportEvent $event) {
    if ($event->getEntityTypeId() == 'taxonomy_term') {
      $entities = $event->getEntities();
      foreach ($entities[$event->getEntityTypeId()] as $entity_id => $entity) {
        /** @var \Drupal\taxonomy\Entity\Term $entity */
        $parents = $this->entityTypeManager->getStorage("taxonomy_term")->loadParents($entity->id());
        if (!empty($parents)) {
          $entities[$event->getEntityTypeId()][$entity_id]->parent->setValue($parents);
        }
      }
      $event->setEntities($entities);

    }
  }

}
