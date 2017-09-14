<?php

namespace Drupal\content_staging;

use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Drupal\content_staging\Event\ContentStagingEvents;
use Drupal\content_staging\Event\ContentStagingProcessFieldDefinitionEvent;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\migrate_plus\Entity\Migration;

/**
 * Import content entities.
 */
class ContentStagingImport {

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
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity types allowed for staging.
   *
   * @var \Drupal\Core\Entity\ContentEntityTypeInterface[]
   */
  protected $entityTypesAllowedForStaging;

  /**
   * The event dispatcher service.
   *
   * @var \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher
   */
  protected $eventDispatcher;

  /**
   * ContentStagingImport constructor.
   *
   * @param \Drupal\content_staging\ContentStagingManager $content_staging_manager
   *   The content staging manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager service.
   * @param \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher $event_dispatcher
   *   The event dispatcher service.
   */
  public function __construct(ContentStagingManager $content_staging_manager, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, ContainerAwareEventDispatcher $event_dispatcher) {
    $this->contentStagingManager = $content_staging_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypesAllowedForStaging = $content_staging_manager->getContentEntityTypes(ContentStagingManager::ALLOWED_FOR_STAGING_ONLY);
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * Generate content staging migrations.
   */
  public function createMigrations() {
    // First, remove all existing migrations.
    $this->cleanExistingMigrations();

    foreach ($this->entityTypesAllowedForStaging as $entity_type_id => $entity_type) {
      if ($entity_type->hasKey('bundle')) {
        $bundles = $this->contentStagingManager->getBundles($entity_type_id, ContentStagingManager::ALLOWED_FOR_STAGING_ONLY);
        foreach ($bundles as $bundle_id => $bundle_label) {
          $this->createMigrationDefinition($entity_type, $bundle_id, $bundle_label['label']);
          if ($entity_type->isTranslatable()) {
            $this->createMigrationDefinition($entity_type, $bundle_id, $bundle_label['label'], 'translations');
          }
        }
      }
      else {
        $this->createMigrationDefinition($entity_type, $entity_type_id);
        if ($entity_type->isTranslatable()) {
          $this->createMigrationDefinition($entity_type, $entity_type_id, '', 'translations');
        }
      }
    }
  }

  /**
   * Create Migration entities.
   *
   * @param $bundle_id
   * @param \Drupal\Core\Entity\ContentEntityTypeInterface $entity_type
   * @param $bundle_label
   * @param string $language
   */
  protected function createMigrationDefinition(ContentEntityTypeInterface $entity_type, $bundle_id, $bundle_label = '', $language = 'default_language') {
    $entity_type_id = $entity_type->id();
    $export_path = realpath(DRUPAL_ROOT . '/' . $this->contentStagingManager->getDirectory());
    if (file_exists($export_path . '/' . $entity_type_id . '/' . $language . '/' . $bundle_id . '.json')) {
      $migration_id = 'staging_content_' . $entity_type_id . '_' . $bundle_id;

      $process = $this->getProcessDefinition($entity_type, $bundle_id, $migration_id, $language);
      $dependencies = array_unique($process['dependencies']);
      unset($dependencies[array_search($migration_id, $dependencies)]);

      $config = [
        'id' => $migration_id . '_' . $language,
        'migration_tags' => ['content_staging'],
        'label' => t('Import @entity_label @bundle_label @language', [
          '@entity_label' => $entity_type->getLabel(),
          '@bundle_label' => $bundle_label,
          '@language' => $language,
        ]),
        'migration_group' => 'content_staging',
        'source' => [
          'plugin' => 'content_staging_json',
          'input_path' => '../staging/' . $entity_type_id . '/' . $language . '/' . $bundle_id . '.json',
        ],
        'process' => $process['process_definition'],
        'destination' => [
          'plugin' => ($entity_type_id == 'paragraph') ? 'entity_reference_revisions:paragraph' : 'entity:' . $entity_type_id,
        ],
        'migration_dependencies' => [
          'required' => $dependencies,
        ],
      ];
      if ($language == 'translations') {
        $config['destination']['translations'] = TRUE;
      }
      Migration::create($config)->save();

      drupal_set_message(t('Migration for @entity_type - @langcode - @bundle created', [
        '@entity_type' => $entity_type_id,
        '@langcode' => $language,
        '@bundle' => $bundle_id,
      ]));
    }
  }

  /**
   * Get migration process definitions.
   *
   * @param \Drupal\Core\Entity\ContentEntityTypeInterface $entity_type
   *   The entity type.
   * @param $bundle_id
   *   The bundle id.
   * @param $migration_id
   *   The current migration id.
   * @param null $language
   *   The current language.
   *
   * @return array
   */
  protected function getProcessDefinition(ContentEntityTypeInterface $entity_type, $bundle_id, $migration_id, $language) {
    $entity_type_id = $entity_type->id();
    $bundle_fields = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle_id);

    $config = [];
    foreach ($bundle_fields as $field_key => $bundle_field) {
      if ($field_key == $entity_type->getKey('id')) {
        if ($language !== 'default_language') {
          $config[$field_key] = [
            'plugin' => 'migration_lookup',
            'source' => 'uuid',
            'migration' => $migration_id . '_default_language',
          ];
        }
      }
      else {
        $event = new ContentStagingProcessFieldDefinitionEvent($entity_type, $bundle_id, $bundle_field);
        /** @var ContentStagingProcessFieldDefinitionEvent $event */
        $event = $this->eventDispatcher->dispatch(ContentStagingEvents::PROCESS_FIELD_DEFINITION, $event);
        if ($event->getProcessFieldDefinition()) {
          $config = array_merge($config, $event->getProcessFieldDefinition());
        }
      }
    }
    if ($entity_type_id == 'entity_subqueue') {
      $config['name'] = 'name';
    }
    return [
      'process_definition' => $config,
      'dependencies' => $this->getMigrationDependencies($config),
    ];
  }

  /**
   * Calculate migration dependencies.
   *
   * @param mixed $config
   *  The migration config.
   *
   * @return array
   *   The list of all dependencies.
   */
  protected function getMigrationDependencies($config) {
    $dependencies = [];
    if (is_array($config)) {
      array_walk_recursive($config, function ($item, $key) use(&$dependencies) {
        if ($key == 'migration') {
          if (!is_array($item)) {
            $dependencies = array_merge($dependencies, [$item]);
          }
          else {
            $dependencies = array_merge($dependencies, $item);
          }
        }
      }, $dependencies);
    }

    return $dependencies;
  }

  /**
   * Remove all existing content staging migrations.
   */
  protected function cleanExistingMigrations() {
    $existing_migrations = $this->entityTypeManager
      ->getStorage('migration')
      ->loadByProperties(['migration_group' => 'content_staging']);

    if (!empty($existing_migrations)) {
      foreach ($existing_migrations as $migration) {
        $migration->delete();
      }
    }
  }

}
