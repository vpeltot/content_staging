<?php

namespace Drupal\content_staging\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;

/**
 * Drupal migrate source from JSON.
 *
 * @MigrateSource(
 *   id = "content_staging_json",
 * )
 */
class ContentStagingJson extends SourcePluginBase {

  /**
   * The source file path.
   *
   * @var string
   */
  protected $input_path;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, array $namespaces = array()) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
    $this->input_path = $configuration['input_path'];
    $this->iterator = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    return json_encode($this->iterator->current(), JSON_PRETTY_PRINT);
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'uuid' => 'The unique identifier',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['uuid']['type'] = 'string';
    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function initializeIterator() {
    if (!isset($this->iterator)) {
      $input = file_get_contents($this->input_path);

      $entities = current(json_decode($input, TRUE));

      unset($input);
      foreach ($entities as &$entitiy) {
        $uuid = $entitiy['uuid'];
        $uuid = reset($uuid);
        $uuid = $uuid['value'];
        $entitiy['uuid'] = $uuid;
      }
      $this->iterator = new \ArrayIterator($entities);
    }
    else {
      $this->iterator->rewind();
    }
    return $this->iterator;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $source = $row->getSource();
    foreach ($source as $key => &$item_list) {
      if (is_scalar($item_list)) {
        continue;
      }
      if (count($item_list) > 1) {
        $item = $item_list;
      }
      else {
        $item = reset($item_list);
      }

      if (in_array($key, [
          'type',
          'shortcut_set',
          'vid',
          'bundle',
          'queue',
        ]) && isset($item['target_id'])) {
        $value = $item['target_id'];
      }
      elseif (isset($item['target_uuid'])) {
        if (isset($item['alt']) && isset($item['title'])) {
          $row->setSourceProperty($key . '_alt', $item['alt']);
          $row->setSourceProperty($key . '_title', $item['title']);
        }
        $value = $item['target_uuid'];
      }
      elseif (is_scalar($item) || (count($item) != 1 && !isset($item['pid']))) {
        if (isset($item[0]) && isset($item[0]['target_uuid'])) {
          $value = [];
          foreach ($item as $it) {
            $value[] = $it['target_uuid'];
          }
        }
        else {
          $value = $item;
        }
      }
      elseif (isset($item['value'])) {
        $value = $item['value'];
      }
      elseif (isset($item['pid'])) {
        $value = $item['alias'];
      }
      else {
        $value = $item;
      }

      if ($key == 'uri') {
        $row->setSourceProperty('filepath', realpath('../staging/files') . '/' . str_replace('public://', '', $value));
      }

      if (empty($item)) {
        $value = NULL;
      }
      $row->setSourceProperty($key, $value);
    }
  }

}
