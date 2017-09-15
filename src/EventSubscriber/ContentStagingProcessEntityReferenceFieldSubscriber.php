<?php

namespace Drupal\content_staging\EventSubscriber;

use Drupal\content_staging\ContentStagingManager;
use Drupal\content_staging\Event\ContentStagingEvents;
use Drupal\content_staging\Event\ContentStagingProcessFieldDefinitionEvent;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribe to ContentStagingEvents::PROCESS_FIELD_DEFINITION events.
 *
 * Get the migration definition for processing an entity reference field.
 */
class ContentStagingProcessEntityReferenceFieldSubscriber implements EventSubscriberInterface {

  /**
   * The content staging manager service.
   *
   * @var \Drupal\content_staging\ContentStagingManager
   */
  protected $contentStagingManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * ContentStagingProcessEntityReferenceFieldSubscriber constructor.
   *
   * @param \Drupal\content_staging\ContentStagingManager $content_staging_manager
   *   The content staging manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(ContentStagingManager $content_staging_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->contentStagingManager = $content_staging_manager;
    $this->entityTypeManager = $entity_type_manager;
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
    if ($event->getFieldDefinition()->getType() == 'entity_reference'
        && in_array($event->getFieldDefinition()->getSettings()['target_type'], array_keys($this->contentStagingManager->getContentEntityTypes(ContentStagingManager::ALLOWED_FOR_STAGING_ONLY)))) {

      $migration = [];
      $no_stub = FALSE;
      // Special case for taxonomy term parent;
      if ($event->getEntityType()->id() == 'taxonomy_term' && $event->getFieldDefinition()->getName() == 'parent') {
        $migration = 'staging_content_' . $event->getFieldDefinition()->getSettings()['target_type'] . '_' . $event->getBundleId() . '_default_language';
      }
      // Special case for entity types without bundle
      elseif (!$this->entityTypeManager->getDefinition($event->getFieldDefinition()->getSettings()['target_type'])->get('bundle_entity_type')) {
        if ($event->getFieldDefinition()->getSettings()['target_type'] === 'user') {
          $no_stub = TRUE;
        }
        $migration = 'staging_content_' . $event->getFieldDefinition()->getSettings()['target_type'] . '_' . $event->getFieldDefinition()->getSettings()['target_type'] . '_default_language';
      }
      // Spacial case for entity types with bundle but without bundles in field settings
      elseif (!isset($event->getFieldDefinition()->getSettings()['handler_settings']['target_bundles'])) {
        $bundles = $this->contentStagingManager->getBundles($event->getFieldDefinition()->getSettings()['target_type'], ContentStagingManager::ALLOWED_FOR_STAGING_ONLY);

        foreach ($bundles as $target_bundle_key => $target_bundle) {
          $migration[] = 'staging_content_' . $event->getFieldDefinition()->getSettings()['target_type'] . '_' . $target_bundle_key . '_default_language';
        }
      }
      else {
        foreach ($event->getFieldDefinition()->getSettings()['handler_settings']['target_bundles'] as $target_bundle) {
          $migration[] = 'staging_content_' . $event->getFieldDefinition()->getSettings()['target_type'] . '_' . $target_bundle . '_default_language';
        }
      }

      if (count($migration) == 1) {
        if (is_array($migration)) {
          $migration = $migration[0];
        }
      }

      $process_field = [
        'plugin' => 'migration_lookup',
        'migration' => $migration,
        'source' => $event->getFieldDefinition()->getName(),
      ];
      if ($event->getFieldDefinition()->isTranslatable()) {
        $process_field['language'] = '@langcode';
      }
      if ($no_stub) {
        $process_field['no_stub'] = TRUE;
      }
      $event->setProcessFieldDefinition([
        $event->getFieldDefinition()->getName() => $process_field
      ]);
      $event->stopPropagation();
    }
  }

}
