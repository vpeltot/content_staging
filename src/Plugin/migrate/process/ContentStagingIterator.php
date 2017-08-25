<?php

namespace Drupal\content_staging\Plugin\migrate\process;

use Drupal\migrate\Plugin\migrate\process\Iterator;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * This plugin iterates and processes an array.
 *
 * @link https://www.drupal.org/node/2135345 Online handbook documentation for iterator process plugin @endlink
 *
 * @MigrateProcessPlugin(
 *   id = "content_staging_iterator",
 *   handle_multiples = TRUE
 * )
 */
class ContentStagingIterator extends Iterator {

  /**
   * Runs a process pipeline on each destination property per list item.
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (isset($value) && !is_array($value[0])) {
      $new_value = [$value];
    }
    else {
      $new_value = $value;
    }
    return parent::transform($new_value, $migrate_executable, $row, $destination_property);
  }

}
