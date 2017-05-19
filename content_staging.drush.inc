<?php

/**
 * @file
 * Define all drush command for content staging.
 */
use Drupal\content_staging\ContentStagingManager;
use Drupal\Core\Entity\ContentEntityTypeInterface;

/**
 * Implements hook_drush_command().
 */
function content_staging_drush_command() {
  return [
    'export-content' => [
      'description' => 'Export all contents.',
      'aliases' => array('ex'),
      'arguments' => []
    ],
    'update-migration-config' => [
      'description' => 'Update migration config according the exported entities.',
      'aliases' => array('umc'),
      'arguments' => []
    ]
  ];
}

/**
 * Callback function for 'export-content' drush command.
 */
function drush_content_staging_export_content() {
  /** @var \Drupal\content_staging\ContentStagingManager $content_staging_manager */
  $content_staging_manager = \Drupal::service('content_staging.manager');
  $entity_type_manager = \Drupal::entityTypeManager();

  $types = $content_staging_manager->getContentEntityTypes(ContentStagingManager::ALLOWED_FOR_STAGING_ONLY);
  foreach ($types as $entity_type => $entity_info) {
    $entities = [];
    if ($entity_info->hasKey('bundle')) {
      $bundles = $content_staging_manager->getContentEntityTypesBundles($entity_type, ContentStagingManager::ALLOWED_FOR_STAGING_ONLY);
      foreach ($bundles as $bundle => $entity_label) {
        $entities[$entity_type] = $entity_type_manager
          ->getStorage($entity_type)
          ->loadByProperties([$entity_info->getKey('bundle') => $bundle]);

        foreach ($entities as $type => $contents) {
          foreach ($contents as $id => &$entity) {
            $path_or_alias = $entity->toUrl('canonical')->toString();
            $alias_manager = Drupal::service('path.alias_manager');
            $alias_storage = Drupal::service('path.alias_storage');

            // Ensure we get the system path.
            $source_path = $alias_manager->getPathByAlias($path_or_alias);

            $entity->path = $alias_storage->load(
              array('source' => $source_path)
            );
          }
        }

        generate_staging($entities, $entity_type, $bundle);
        drush_print(t('Export @entity_type - @bundle entities', [
          '@entity_type' => $entity_type,
          '@bundle' => $bundle,
        ]));
      }
    }
    else {
      $entities[$entity_type] = \Drupal::entityTypeManager()
        ->getStorage($entity_type)
        ->loadMultiple();

      generate_staging($entities, $entity_type);
      drush_print(t('Export @entity_type entities', [
        '@entity_type' => $entity_type,
      ]));
    }
  }
}

function drush_content_staging_update_migration_config() {
  // First, remove existing migration configuration.
  $entity_type_manager = \Drupal::entityTypeManager();
  $existing_migrations = $entity_type_manager
    ->getStorage('migration')
    ->loadByProperties(['migration_group' => 'content_staging']);

  if (!empty($existing_migrations)) {
    foreach ($existing_migrations as $migration) {
      $migration->delete();
    }
  }

  // Second, create migration configuration
  // regarding the entity types allowed to export.
  /** @var \Drupal\content_staging\ContentStagingManager $content_staging_manager */
  $content_staging_manager = \Drupal::service('content_staging.manager');
  $types = $content_staging_manager->getContentEntityTypes(ContentStagingManager::ALLOWED_FOR_STAGING_ONLY);
  foreach ($types as $entity_type => $entity_info) {
    if ($entity_info->hasKey('bundle')) {
      $bundles = $content_staging_manager->getContentEntityTypesBundles($entity_type, ContentStagingManager::ALLOWED_FOR_STAGING_ONLY);
      foreach ($bundles as $bundle => $entity_label) {
        $dependencies = [];
        $id = 'staging_content_' . $entity_type . '_' . $bundle;
        $process = get_fields_definition($entity_info, $entity_type, $bundle, array_keys($types), $dependencies);
        $dependencies = array_unique($dependencies);
        unset($dependencies[array_search($id, $dependencies)]);
        $config = [
          'id' => $id,
          'label' => t('Import @entity_label @bundle_label', [
            '@entity_label' => $entity_info->getLabel(),
            '@bundle_label' => $entity_label['label'],
          ]),
          'migration_group' => 'content_staging',
          'source' => [
            'plugin' => 'content_staging_json',
            'input_path' => '../staging/' . $entity_type . '/' . $bundle . '.json',
          ],
          'process' => $process,
          'destination' => [
            'plugin' => ($entity_type == 'paragraph') ? 'entity_reference_revisions:paragraph' : 'entity:' . $entity_type
          ],
          'migration_dependencies' => [
            'required' => $dependencies,
          ],
        ];
        \Drupal\migrate_plus\Entity\Migration::create($config)->save();
      }
    }
    else {
      $dependencies = [];
      $id = 'staging_content_' . $entity_type . '_' . $entity_type;
      $process = get_fields_definition($entity_info, $entity_type, $entity_type, array_keys($types), $dependencies);
      $dependencies = array_unique($dependencies);
      unset($dependencies[array_search($id, $dependencies)]);
      $config = [
        'id' => $id,
        'label' => t('Import @entity_label', [
          '@entity_label' => $entity_info->getLabel()
        ]),
        'migration_group' => 'content_staging',
        'source' => [
          'plugin' => 'content_staging_json',
          'input_path' => '../staging/' . $entity_type . '/' . $entity_type . '.json',
        ],
        'process' => $process,
        'destination' => [
          'plugin' => ($entity_type == 'paragraph') ? 'entity_reference_revisions:paragraph' : 'entity:' . $entity_type
        ],
        'migration_dependencies' => [
          'required' => $dependencies,
        ],
      ];
      \Drupal\migrate_plus\Entity\Migration::create($config)->save();
    }
  }
}

function get_fields_definition(ContentEntityTypeInterface $entity_info, $entity_type, $bundle, $types, &$dependencies) {
  /** @var \Drupal\Core\Entity\EntityFieldManager $entity_field_manager */
  $entity_field_manager = \Drupal::service('entity_field.manager');
  $bundle_fields = $entity_field_manager->getFieldDefinitions($entity_type, $bundle);

  // Unset All IDs
  unset($bundle_fields[$entity_info->getKey('id')]);
  unset($bundle_fields[$entity_info->getKey('revision')]);
  unset($bundle_fields['uuid']);

  $config = [];
  foreach ($bundle_fields as $field_key => $bundle_field) {
    if ($bundle_field->getType() == 'entity_reference'
      && in_array($bundle_field->getSettings()['target_type'], $types)) {

      $migration = [];
      $entity_type_manager = \Drupal::entityTypeManager();
      $migration_source = [];
      // Special case for taxonomy term parent;
      if ($entity_type == 'taxonomy_term' && $field_key == 'parent') {
        $migration = 'staging_content_' . $bundle_field->getSettings()['target_type'] . '_' . $bundle;
      }
      // Special case for entity types without bundle
      elseif (!$entity_type_manager->getDefinition($bundle_field->getSettings()['target_type'])->get('bundle_entity_type')) {
        $migration = 'staging_content_' . $bundle_field->getSettings()['target_type'] . '_' . $bundle_field->getSettings()['target_type'];
      }
      // Spacial case for entity types with bundle but without bundles in field settings
      elseif (!isset($bundle_field->getSettings()['handler_settings']['target_bundles'])) {
        $content_staging_manager = \Drupal::service('content_staging.manager');
        $bundles = $content_staging_manager->getContentEntityTypesBundles($bundle_field->getSettings()['target_type'], ContentStagingManager::ALLOWED_FOR_STAGING_ONLY);

        foreach ($bundles as $target_bundle_key => $target_bundle) {
          $migration[] = 'staging_content_' . $bundle_field->getSettings()['target_type'] . '_' . $target_bundle_key;
        }
      }
      else {
        foreach ($bundle_field->getSettings()['handler_settings']['target_bundles'] as $target_bundle) {
          $migration[] = 'staging_content_' . $bundle_field->getSettings()['target_type'] . '_' . $target_bundle;
        }
      }

      if (count($migration) > 1) {
        foreach ($migration as $value) {
          $migration_source[$value][] = $field_key;
        }
        $source_key = 'source_ids';
      }
      else {
        if (is_array($migration)) {
          $migration = $migration[0];
        }
        $migration_source = $field_key;
        $source_key = 'source';
      }
      $config[$field_key][] = [
        'plugin' => 'migration',
        'migration' => $migration,
        $source_key => $migration_source,
      ];
    }
    elseif ($bundle_field->getType() == 'entity_reference_revisions'
      && in_array($bundle_field->getSettings()['target_type'], $types)) {

      $migration = [];
      foreach ($bundle_field->getSettings()['handler_settings']['target_bundles'] as $target_bundle) {
        $migration[] = 'staging_content_' . $bundle_field->getSettings()['target_type'] . '_' . $target_bundle;
      }

      $dependencies = array_merge($dependencies, $migration);
      $config[$field_key][] = [
        'plugin' => 'migration',
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
    elseif ($entity_type == 'menu_link_content' && $field_key == 'parent') {
      $config[$field_key] = [
        'plugin' => 'content_staging_menu_link_parent',
        'source' => [
          $field_key,
          '@menu_name',
        ],
      ];
    }
    elseif ($entity_type == 'file' && $field_key == 'uri') {
      $config[$field_key] = [
        'plugin' => 'file_copy',
        'source' => [
          'filepath',
          $field_key,
        ],
      ];
    }
    elseif (in_array($bundle_field->getType(), ['image', 'file'])) {
      $config[$field_key][] = [
        'plugin' => 'migration',
        'migration' => 'staging_content_file_file',
        'source' => $field_key,
      ];
    }
    else {
      $config[$field_key] = $field_key;
    }
  }
  if ($entity_type == 'entity_subqueue') {
    $config['name'] = 'name';
  }
  return $config;
}

/**
 * Helper function to generate and save entities in a YAML file.
 *
 * @param $entities
 *   All entities.
 * @param $entity_type
 *   The entities type.
 * @param string|null $bundle
 *   The entities bundle.
 */
function generate_staging($entities, $entity_type, $bundle = NULL) {
  /** @var \Drupal\content_staging\ContentStagingManager $content_staging_manager */
  $content_staging_manager = \Drupal::service('content_staging.manager');
  /** @var \Symfony\Component\Serializer\Serializer $serializer */
  $serializer = \Drupal::service('serializer');
  $json = $serializer->serialize($entities, 'json', [
    'json_encode_options' => JSON_PRETTY_PRINT,
  ]);

  $real = realpath(DRUPAL_ROOT . '/' . $content_staging_manager->getConfigStagingDirectory());

  if (!file_exists($real . '/' . $entity_type)) {
    mkdir($real . '/' . $entity_type, 0777, TRUE);
  }

  if ($bundle) {
    file_put_contents($real . '/' . $entity_type . '/' . $bundle . '.json', $json);
  }
  else {
    file_put_contents($real . '/' . $entity_type . '/' . $entity_type . '.json', $json);
  }

  if ($entity_type == 'file') {
    export_files($entities['file']);
  }
}

/**
 * Export files.
 *
 * @param \Drupal\file\Entity\File[] $entities
 */
function export_files($entities) {
  foreach ($entities as $file) {
    $real = realpath(DRUPAL_ROOT . '/' . '../staging/');
    $folder = $real . '/files/' . dirname(file_uri_target($file->getFileUri()));
    if (!file_exists($folder)) {
      mkdir($folder, 0777, TRUE);
    }

    /** @var \Drupal\Core\File\FileSystem $file_system */
    $file_system = \Drupal::service('file_system');
    file_put_contents($folder . '/' . $file_system->basename($file->getFileUri()), file_get_contents($file->getFileUri()));
  }
}
