<?php

namespace Drupal\content_staging;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Manage content staging configuration.
 */
class ContentStagingManager {

  const ALLOWED_FOR_STAGING_ONLY = TRUE;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The immutable content staging configuration entity.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The editable content staging configuration entity.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $editableConfig;

  /**
   * ContentStagingManager constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, ConfigFactoryInterface $config_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->config = $config_factory->get('content_staging.settings');
    $this->editableConfig = $config_factory->getEditable('content_staging.settings');
  }

  /**
   * Get all content entity types.
   *
   * @return \Drupal\Core\Entity\ContentEntityTypeInterface[]
   */
  public function getContentEntityTypes($allowed_only = FALSE) {
    $definitions = $this->entityTypeManager->getDefinitions();
    $ret = [];
    foreach ($definitions as $machine => $type) {
      if ($type instanceof ContentEntityTypeInterface) {
        if (!$allowed_only || ($allowed_only && $this->entityTypeAllowedForStaging($machine))) {
          $ret[$machine] = $type;
        }
      }
    }

    return $ret;
  }

  /**
   * Get all bundle of an entity type.
   *
   * @param string $entity_type
   *   The entity type id.
   *
   * @return array
   */
  public function getBundles($entity_type, $allowed_only = FALSE) {
    $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type);
    $ret = [];
    foreach ($bundles as $machine => $bundle) {
      if (!$allowed_only || ($allowed_only && $this->bundleAllowedForStaging($entity_type, $machine))) {
        $ret[$machine] = $bundle;
      }
    }
    return $ret;
  }

  /**
   * Get the staging directory from config entity.
   *
   * @return string|null
   */
  public function getDirectory() {
    return $this->config->get('staging_directory');
  }

  /**
   * Set the staging directory in config entity.
   *
   * @param string $staging_directory
   *   The staging directory.
   */
  public function setDirectory($staging_directory) {
    $this->editableConfig->set('staging_directory', $staging_directory);
    $this->editableConfig->save();
  }

  /**
   * Get the staging allowed entity types from config entity.
   *
   * @return array
   */
  public function getEntityTypes() {
    return $this->config->get('entity_types');
  }

  /**
   * Set the staging allowed entity types in config entity.
   *
   * @param array $entity_types
   *   The entity type list.
   */
  public function setEntityTypes($entity_types) {
    $this->editableConfig->set('entity_types', $entity_types);
    $this->editableConfig->save();
  }

  /**
   * Use to known if a specific entity_type is allowed for staging.
   *
   * @param $entity_type
   *   The entity type machine name.
   *
   * @return bool
   */
  public function entityTypeAllowedForStaging($entity_type) {
    $entity_types = $this->getEntityTypes();
    if (isset($entity_types[$entity_type]['enable'])
      && $entity_types[$entity_type]['enable']) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Use to known if a specific entity_type / bundle association is allowed for staging.
   *
   * @param $entity_type
   *   The entity type machine name.
   * @param $bundle
   *   The bundle machine name.
   *
   * @return bool
   */
  public function bundleAllowedForStaging($entity_type, $bundle) {
    $entity_types = $this->getEntityTypes();
    if (isset($entity_types[$entity_type]['bundles'][$bundle])
      && $entity_types[$entity_type]['bundles'][$bundle]) {
      return TRUE;
    }
    return FALSE;
  }

}
