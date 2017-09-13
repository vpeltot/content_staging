<?php

namespace Drupal\content_staging\EventSubscriber;

use Drupal\content_staging\ContentStagingManager;
use Drupal\content_staging\Event\ContentStagingEvents;
use Drupal\content_staging\Event\ContentStagingProcessFieldDefinitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribe to ContentStagingEvents::PROCESS_FIELD_DEFINITION events.
 *
 * Get the migration definition for processing an entity reference revision field.
 */
class ContentStagingProcessEntityReferenceRevisionFieldSubscriber implements EventSubscriberInterface {

  /**
   * The content staging manager service.
   *
   * @var \Drupal\content_staging\ContentStagingManager
   */
  protected $contentStagingManager;

  /**
   * ContentStagingProcessEntityReferenceRevisionFieldSubscriber constructor.
   *
   * @param \Drupal\content_staging\ContentStagingManager $content_staging_manager
   *   The content staging manager service.
   */
  public function __construct(ContentStagingManager $content_staging_manager) {
    $this->contentStagingManager = $content_staging_manager;
  }

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
    if ($event->getFieldDefinition()->getType() == 'entity_reference_revisions'
      && in_array($event->getFieldDefinition()->getSettings()['target_type'], array_keys($this->contentStagingManager->getContentEntityTypes(ContentStagingManager::ALLOWED_FOR_STAGING_ONLY)))) {

      $migration = [];
      foreach ($event->getFieldDefinition()->getSettings()['handler_settings']['target_bundles'] as $target_bundle) {
        $migration[] = 'staging_content_' . $event->getFieldDefinition()->getSettings()['target_type'] . '_' . $target_bundle . '_default_language';
      }

      $process_field[] = [
        'plugin' => 'migration_lookup',
        'migration' => $migration,
        'source' => $event->getFieldDefinition()->getName(),
      ];
      $process_field[] = [
        'plugin' => 'content_staging_iterator',
        'process' => [
          'target_id' => '0',
          'target_revision_id' => '1',
        ],
      ];
      $event->setProcessFieldDefinition([
        $event->getFieldDefinition()->getName() => $process_field
      ]);
      $event->stopPropagation();
    }
  }

}
