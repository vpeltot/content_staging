<?php

namespace Drupal\content_staging\Event;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Wraps a process field definition event for event subscribers.
 */
class ContentStagingProcessFieldDefinitionEvent extends Event {

  /**
   * The entity type.
   *
   * @var \Drupal\Core\Entity\ContentEntityTypeInterface
   */
  protected $entityType;

  /**
   * The bundle ID.
   *
   * @var string
   */
  protected $bundleId;

  /**
   * The field definition.
   *
   * @var \Drupal\Core\Field\FieldDefinitionInterface
   */
  protected $fieldDefinition;

  /**
   * The process field definition;
   *
   * @var array;
   */
  protected $processFieldDefinition = NULL;

  /**
   * Constructs a process field definition event object.
   *
   * @param \Drupal\Core\Entity\ContentEntityTypeInterface $entity_type
   *   The entity type.
   * @param $bundle_id
   *   The bundle ID.
   */
  public function __construct(ContentEntityTypeInterface $entity_type, $bundle_id, FieldDefinitionInterface $field_definition) {
    $this->entityType = $entity_type;
    $this->bundleId = $bundle_id;
    $this->fieldDefinition = $field_definition;
  }

  /**
   * Get the entity type.
   *
   * @return \Drupal\Core\Entity\ContentEntityTypeInterface
   */
  public function getEntityType() {
    return $this->entityType;
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
   * Get the field definition.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface
   *   The field definition.
   */
  public function getFieldDefinition() {
    return $this->fieldDefinition;
  }

  /**
   * Get the field definition.
   *
   * @return array|string
   *   The field definition.
   */
  public function getProcessFieldDefinition() {
    return $this->processFieldDefinition;
  }

  /**
   * Set the field definition.
   *
   * @param array|string $process_field_definition
   *   The field definition.
   */
  public function setProcessFieldDefinition($process_field_definition) {
    $this->processFieldDefinition = $process_field_definition;
  }

}
