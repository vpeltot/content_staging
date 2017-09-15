<?php

namespace Drupal\content_staging\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Wraps a before export event for event subscribers.
 */
class ContentStagingBeforeExportEvent extends Event {

  /**
   * The entity type ID.
   *
   * @var \Drupal\Core\Entity\ContentEntityTypeInterface
   */
  protected $entityTypeId;

  /**
   * The bundle ID.
   *
   * @var string
   */
  protected $bundleId;

  /**
   * The entities list.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface[]
   */
  protected $entities;

  /**
   * Constructs a process field definition event object.
   *
   * @param $entity_type_id
   *   The entity type ID.
   * @param string $bundle_id
   *   The bundle ID.
   * @param array $entities
   *   The entity list
   */
  public function __construct($entity_type_id, $bundle_id, array $entities) {
    $this->entityTypeId = $entity_type_id;
    $this->bundleId = $bundle_id;
    $this->entities = $entities;
  }

  /**
   * Get the entity type ID..
   *
   * @return string
   *   The entity type ID.
   */
  public function getEntityTypeId() {
    return $this->entityTypeId;
  }

  /**
   * Get the bundle ID.
   *
   * @return string
   *   The bundle ID.
   */
  public function getBundleId() {
    return $this->bundleId;
  }

  /**
   * Get the entity list.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface[][]
   *   The entity list.
   */
  public function getEntities() {
    return $this->entities;
  }

  /**
   * Set the entity list.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface[][] $entities
   *   The entity list.
   */
  public function setEntities($entities) {
    $this->entities = $entities;
  }

}
