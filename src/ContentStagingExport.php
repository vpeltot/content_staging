<?php

namespace Drupal\content_staging;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Path\AliasStorageInterface;
use Symfony\Component\Serializer\Serializer;

/**
 * Export content entities.
 */
class ContentStagingExport {

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
   * The alias storage service.
   *
   * @var \Drupal\Core\Path\AliasStorageInterface
   */
  protected $aliasStorage;

  /**
   * The serializer service.
   *
   * @var \Symfony\Component\Serializer\Serializer
   */
  protected $serializer;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * ContentStagingExport constructor.
   *
   * @param \Drupal\content_staging\ContentStagingManager $content_staging_manager
   *   The content staging manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Path\AliasStorageInterface $alias_storage
   *   The alias storage service.
   * @param \Symfony\Component\Serializer\Serializer $serializer
   *   The serializer service.
   * @param \Drupal\Core\File\FileSystem $file_system
   *   The file system service.
   */
  public function __construct(ContentStagingManager $content_staging_manager, EntityTypeManagerInterface $entity_type_manager, AliasStorageInterface $alias_storage, Serializer $serializer, FileSystem $file_system) {
    $this->contentStagingManager = $content_staging_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->aliasStorage = $alias_storage;
    $this->serializer = $serializer;
    $this->fileSystem = $file_system;
  }

  /**
   * Export content entities.
   */
  public function export() {
    $types = $this->contentStagingManager->getContentEntityTypes(ContentStagingManager::ALLOWED_FOR_STAGING_ONLY);
    foreach ($types as $entity_type_id => $entity_info) {
      if ($entity_info->hasKey('bundle')) {
        $bundles = $this->contentStagingManager->getBundles($entity_type_id, ContentStagingManager::ALLOWED_FOR_STAGING_ONLY);
        foreach ($bundles as $bundle => $entity_label) {
          $entities = [];
          /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
          foreach ($this->entityTypeManager->getStorage($entity_type_id)->loadByProperties([$entity_info->getKey('bundle') => $bundle]) as $entity) {
            $entities = array_merge_recursive($entities, $this->getTranslatedEntity($entity));
          }

          $this->doExport($entities, $entity_type_id, $bundle);
        }
      }
      else {
        $entities = [];
        /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
        foreach ($this->entityTypeManager->getStorage($entity_type_id)->loadMultiple() as $entity) {
          $entities = array_merge_recursive($entities, $this->getTranslatedEntity($entity));
        }

        $this->doExport($entities, $entity_type_id);
      }
    }
  }

  /**
   * Get all translation for the given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity to retrieve associated translations.
   *
   * @return \Drupal\Core\Entity\EntityInterface[][][]
   */
  protected function getTranslatedEntity(ContentEntityInterface $entity) {
    $entities = [];
    foreach ($entity->getTranslationLanguages() as $content_language) {
      $translated_entity = $entity->getTranslation($content_language->getId());
      $translated_entity->path = NULL;
      if ($translated_entity->hasLinkTemplate('canonical')) {
        $entity_path = $translated_entity->toUrl('canonical')
          ->getInternalPath();

        $translated_entity->path = $this->aliasStorage->load([
          'source' => '/' . $entity_path,
        ]);
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

  /**
   * Proceed the export.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface[][][] $translated_entities
   *   All translated entities.
   * @param string $entity_type_id
   *   The entities type id.
   * @param string|null $bundle
   *   The entities bundle.
   */
  protected function doExport(array $translated_entities, $entity_type_id, $bundle = NULL) {
    $export_path = realpath(DRUPAL_ROOT . '/' . $this->contentStagingManager->getDirectory());

    foreach ($translated_entities as $langcode => $entities) {
      $serialized_entities = $this->serializer->serialize($entities, 'json', [
        'json_encode_options' => JSON_PRETTY_PRINT,
      ]);

      $entity_export_path = $export_path . '/' . $entity_type_id . '/' . $langcode;

      // Ensure the directory exists
      if (!file_exists($entity_export_path)) {
        mkdir($entity_export_path, 0777, TRUE);
      }

      if ($bundle) {
        file_put_contents($entity_export_path . '/' . $bundle . '.json', $serialized_entities);
      }
      else {
        file_put_contents($entity_export_path . '/' . $entity_type_id . '.json', $serialized_entities);
      }

      drupal_set_message(t('Export @entity_type - @langcode - @bundle entities', [
        '@entity_type' => $entity_type_id,
        '@langcode' => $langcode,
        '@bundle' => $bundle,
      ]));

      if ($entity_type_id == 'file') {
        $this->doExportFiles($entities['file']);
      }
    }
  }

  /**
   * Export files.
   *
   * @param \Drupal\file\FileInterface[] $entities
   */
  protected function doExportFiles(array $entities) {
    $export_path = realpath(DRUPAL_ROOT . '/' . $this->contentStagingManager->getDirectory());

    foreach ($entities as $file) {
      $folder = $export_path . '/files/' . dirname(file_uri_target($file->getFileUri()));
      if (!file_exists($folder)) {
        mkdir($folder, 0777, TRUE);
      }
      file_put_contents($folder . '/' . $this->fileSystem->basename($file->getFileUri()), file_get_contents($file->getFileUri()));
    }
  }

}
