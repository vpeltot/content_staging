<?php

namespace Drupal\content_staging\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\migrate\Plugin\migrate\process\MenuLinkParent;

/**
 * This plugin figures out menu link parent plugin IDs.
 *
 * @MigrateProcessPlugin(
 *   id = "content_staging_menu_link_parent"
 * )
 */
class ContentStagingMenuLinkParent extends MenuLinkParent {

  /**
   * {@inheritdoc}
   *
   * Remove 'menu_link_content:' before find the parent link GUID.
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    return parent::transform(str_replace('menu_link_content:', '', $value), $migrate_executable, $row, $destination_property);
  }

}
