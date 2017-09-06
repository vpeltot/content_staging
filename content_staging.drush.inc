<?php

/**
 * @file
 * Define all drush command for content staging.
 */
use Drupal\content_staging\ContentStagingManager;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\language\Entity\ContentLanguageSettings;

/**
 * Implements hook_drush_command().
 */
function content_staging_drush_command() {
  return [
    'export-content' => [
      'description' => 'Export all contents.',
      'aliases' => array('ex'),
      'arguments' => [],
    ],
    'update-migration-config' => [
      'description' => 'Update migration config according the exported entities.',
      'aliases' => array('umc'),
      'arguments' => [],
    ],
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
        /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
        foreach($entity_type_manager
          ->getStorage($entity_type)
          ->loadByProperties([$entity_info->getKey('bundle') => $bundle]) as $entity) {
          $entities = array_merge_recursive($entities, get_translated_entity($entity));
        }

        generate_staging($entities, $entity_type, $bundle);
      }
    }
    else {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      foreach(\Drupal::entityTypeManager()
        ->getStorage($entity_type)
        ->loadMultiple() as $entity) {
        $entities = array_merge_recursive($entities, get_translated_entity($entity));
      }

      generate_staging($entities, $entity_type);
    }
  }
}

function get_translated_entity(\Drupal\Core\Entity\ContentEntityInterface $entity) {
  $entities = [];
  foreach($entity->getTranslationLanguages() as $content_language) {
    $translated_entity = $entity->getTranslation($content_language->getId());
    $translated_entity->path = NULL;
    if ($translated_entity->hasLinkTemplate('canonical')) {
      $entity_path = $translated_entity->toUrl('canonical')->getInternalPath();

      $alias_storage = Drupal::service('path.alias_storage');

      $translated_entity->path = $alias_storage->load(
        array('source' => '/' . $entity_path)
      );
    }
    if ($translated_entity->isDefaultTranslation()) {
      $entities['default_language'][$entity->getEntityTypeId()][] = $translated_entity;
    }
    else {
      $entities['translations'][$entity->getEntityTypeId()][] = $translated_entity;
    }
  }
  return $entities;
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
        create_migration_definition($entity_type, $bundle, $entity_info, $types, $entity_label['label']);
        if ($entity_info->isTranslatable()) {
          create_migration_definition($entity_type, $bundle, $entity_info, $types, $entity_label['label'], 'translations');
        }
      }
    }
    else {
      create_migration_definition($entity_type, $entity_type, $entity_info, $types, '');
      if ($entity_info->isTranslatable()) {
        create_migration_definition($entity_type, $entity_type, $entity_info, $types, '', 'translations');
      }
    }
  }
}

function create_migration_definition($entity_type, $bundle, ContentEntityTypeInterface $entity_info, $types, $entity_label, $lang = 'default_language') {
  /** @var \Drupal\content_staging\ContentStagingManager $content_staging_manager */
  $content_staging_manager = \Drupal::service('content_staging.manager');
  $real_path = realpath(DRUPAL_ROOT . '/' . $content_staging_manager->getConfigStagingDirectory());
  if (file_exists($real_path . '/' . $entity_type . '/' . $lang . '/' . $bundle . '.json')) {
    $dependencies = [];
    $id = 'staging_content_' . $entity_type . '_' . $bundle . '_' . $lang;
    $default_language_id = 'staging_content_' . $entity_type . '_' . $bundle . '_default_language';
    $process = get_fields_definition($entity_info, $entity_type, $bundle, array_keys($types), $dependencies, $id, $default_language_id);
    $dependencies = array_unique($dependencies);
    unset($dependencies[array_search($id, $dependencies)]);

    $config = [
      'id' => $id,
      'label' => t('Import @entity_label @bundle_label @language', [
        '@entity_label' => $entity_info->getLabel(),
        '@bundle_label' => $entity_label,
        '@language' => $lang,
      ]),
      'migration_group' => 'content_staging',
      'source' => [
        'plugin' => 'content_staging_json',
        'input_path' => '../staging/' . $entity_type . '/' . $lang . '/' . $bundle . '.json',
      ],
      'process' => $process,
      'destination' => [
        'plugin' => ($entity_type == 'paragraph') ? 'entity_reference_revisions:paragraph' : 'entity:' . $entity_type,
      ],
      'migration_dependencies' => [
        'required' => $dependencies,
      ],
    ];
    if ($lang == 'translations') {
      $config['destination']['translations'] = TRUE;
    }
    \Drupal\migrate_plus\Entity\Migration::create($config)->save();
  }
}

function get_fields_definition(ContentEntityTypeInterface $entity_info, $entity_type, $bundle, $types, &$dependencies, $migration_id, $default_language_migration_id = NULL) {
  /** @var \Drupal\Core\Entity\EntityFieldManager $entity_field_manager */
  $entity_field_manager = \Drupal::service('entity_field.manager');
  /** @var \Drupal\Core\Field\BaseFieldDefinition[] $bundle_fields */
  $bundle_fields = $entity_field_manager->getFieldDefinitions($entity_type, $bundle);

  // Unset All IDs
//  unset($bundle_fields[$entity_info->getKey('id')]);
  unset($bundle_fields[$entity_info->getKey('revision')]);
  unset($bundle_fields['uuid']);

  $config = [];
  foreach ($bundle_fields as $field_key => $bundle_field) {
    if ($field_key == $entity_info->getKey('id')) {
      if ($default_language_migration_id !== $migration_id) {
        $config[$field_key] = [
          'plugin' => 'migration',
          'source' => 'uuid',
          'migration' => $default_language_migration_id,
        ];
      }
    }
    elseif ($bundle_field->getType() == 'entity_reference'
      && in_array($bundle_field->getSettings()['target_type'], $types)) {

      $migration = [];
      $entity_type_manager = \Drupal::entityTypeManager();
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
        'plugin' => 'migration',
        'migration' => $migration,
        'source' => $field_key,
      ];
      if ($bundle_field->isTranslatable()) {
        $process_field['language'] = '@langcode';
      }
      $config[$field_key][] = $process_field;

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
    elseif ($entity_type == 'file' && $field_key == 'uri') {
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
        'plugin' => 'migration',
        'migration' => 'staging_content_file_file',
        'source' => $field_key,
      ];
      if ($bundle_field->isTranslatable()) {
        $process_field['language'] = '@langcode';
      }
      $config[$field_key][] = $process_field;
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
function generate_staging($translated_entities, $entity_type, $bundle = NULL) {
  /** @var \Drupal\content_staging\ContentStagingManager $content_staging_manager */
  $content_staging_manager = \Drupal::service('content_staging.manager');
  /** @var \Symfony\Component\Serializer\Serializer $serializer */
  $serializer = \Drupal::service('serializer');
  foreach ($translated_entities as $langcode => $entities) {
    $json = $serializer->serialize($entities, 'json', [
      'json_encode_options' => JSON_PRETTY_PRINT,
    ]);

    $real = realpath(DRUPAL_ROOT . '/' . $content_staging_manager->getConfigStagingDirectory());

    if (!file_exists($real . '/' . $entity_type. '/' . $langcode)) {
      mkdir($real . '/' . $entity_type . '/' . $langcode, 0777, TRUE);
    }

    if ($bundle) {
      file_put_contents($real . '/' . $entity_type . '/' . $langcode . '/' . $bundle . '.json', $json);
    }
    else {
      file_put_contents($real . '/' . $entity_type . '/' . $langcode . '/' . $entity_type . '.json', $json);
    }

    drush_print(t('Export @entity_type - @langcode - @bundle entities', [
      '@entity_type' => $entity_type,
      '@langcode' => $langcode,
      '@bundle' => $bundle,
    ]));

    if ($entity_type == 'file') {
      export_files($entities['file']);
    }

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
