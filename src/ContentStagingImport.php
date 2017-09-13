<?php

namespace Drupal\content_staging;

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
   * ContentStagingImport constructor.
   *
   * @param \Drupal\content_staging\ContentStagingManager $content_staging_manager
   *   The content staging manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager service.
   */
  public function __construct(ContentStagingManager $content_staging_manager, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager) {
    $this->contentStagingManager = $content_staging_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypesAllowedForStaging = $content_staging_manager->getContentEntityTypes(ContentStagingManager::ALLOWED_FOR_STAGING_ONLY);
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

    // Unset All uuid definitions.
    unset($bundle_fields['uuid']);

    $config = [];
    $dependencies = [];
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
      elseif ($field_key == $entity_type->getKey('revision') && $entity_type_id == 'paragraph') {
        $config[$field_key] = $field_key;
      }
      elseif ($field_key == $entity_type->getKey('revision')) {
        continue;
      }
      elseif ($bundle_field->getType() == 'entity_reference'
        && in_array($bundle_field->getSettings()['target_type'], array_keys($this->entityTypesAllowedForStaging))) {

        $migration = [];
        // Special case for taxonomy term parent;
        if ($entity_type_id == 'taxonomy_term' && $field_key == 'parent') {
          $migration = 'staging_content_' . $bundle_field->getSettings()['target_type'] . '_' . $bundle_id . '_default_language';
        }
        // Special case for entity types without bundle
        elseif (!$this->entityTypeManager->getDefinition($bundle_field->getSettings()['target_type'])->get('bundle_entity_type')) {
          $migration = 'staging_content_' . $bundle_field->getSettings()['target_type'] . '_' . $bundle_field->getSettings()['target_type'] . '_default_language';
        }
        // Spacial case for entity types with bundle but without bundles in field settings
        elseif (!isset($bundle_field->getSettings()['handler_settings']['target_bundles'])) {
          $bundles = $this->contentStagingManager->getBundles($bundle_field->getSettings()['target_type'], ContentStagingManager::ALLOWED_FOR_STAGING_ONLY);

          foreach ($bundles as $target_bundle_key => $target_bundle) {
            $migration[] = 'staging_content_' . $bundle_field->getSettings()['target_type'] . '_' . $target_bundle_key . '_default_language';
          }
        }
        else {
          foreach ($bundle_field->getSettings()['handler_settings']['target_bundles'] as $target_bundle) {
            $migration[] = 'staging_content_' . $bundle_field->getSettings()['target_type'] . '_' . $target_bundle . '_default_language';
          }
        }

        if (count($migration) == 1) {
          if (is_array($migration)) {
            $migration = $migration[0];
          }
        }

        if (!is_array($migration)) {
          $dependencies = array_merge($dependencies, [$migration]);
        }
        else {
          $dependencies = array_merge($dependencies, $migration);
        }

        $process_field = [
          'plugin' => 'migration_lookup',
          'migration' => $migration,
          'source' => $field_key,
        ];
        if ($bundle_field->isTranslatable()) {
          $process_field['language'] = '@langcode';
        }
        $config[$field_key][] = $process_field;

      }
      elseif ($bundle_field->getType() == 'entity_reference_revisions'
        && in_array($bundle_field->getSettings()['target_type'], array_keys($this->entityTypesAllowedForStaging))) {

        $migration = [];
        foreach ($bundle_field->getSettings()['handler_settings']['target_bundles'] as $target_bundle) {
          $migration[] = 'staging_content_' . $bundle_field->getSettings()['target_type'] . '_' . $target_bundle . '_default_language';
        }

        $dependencies = array_merge($dependencies, $migration);
        $config[$field_key][] = [
          'plugin' => 'migration_lookup',
          'migration' => $migration,
          'source' => $field_key,
        ];
        $config[$field_key][] = [
          'plugin' => 'content_staging_iterator',
          'process' => [
            'target_id' => '0',
            'target_revision_id' => '1',
          ],
        ];
      }
      elseif ($entity_type_id == 'menu_link_content' && $field_key == 'parent') {
        $process_field = [
          'plugin' => 'content_staging_menu_link_parent',
          'source' => [
            $field_key,
            '@menu_name',
          ],
        ];
        if ($bundle_field->isTranslatable()) {
          $process_field['language'] = '@langcode';
        }
        $config[$field_key] = $process_field;
      }
      elseif ($entity_type_id == 'file' && $field_key == 'uri') {
        $process_field = [
          'plugin' => 'file_copy',
          'source' => [
            'filepath',
            $field_key,
          ],
        ];
        if ($bundle_field->isTranslatable()) {
          $process_field['language'] = '@langcode';
        }
        $config[$field_key] = $process_field;
      }
      elseif (in_array($bundle_field->getType(), ['image', 'file'])) {
        $process_field = [
          'plugin' => 'migration_lookup',
          'migration' => 'staging_content_file_file_default_language',
          'source' => $field_key,
        ];
        if ($bundle_field->isTranslatable()) {
          $process_field['language'] = '@langcode';
        }
        $config[$field_key . '/target_id'][] = $process_field;
        $config[$field_key . '/alt'] = $field_key . '_alt';
        $config[$field_key . '/title'] = $field_key . '_title';
      }
      else {
        if (!$bundle_field->isTranslatable()) {
          $config[$field_key] = $field_key;
        }
        else {
          $config[$field_key] = [
            'plugin' => 'get',
            'source' => $field_key,
            'language' => '@langcode',
          ];
        }
      }
    }
    if ($entity_type_id == 'entity_subqueue') {
      $config['name'] = 'name';
    }
    return [
      'process_definition' => $config,
      'dependencies' => $dependencies,
    ];
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
