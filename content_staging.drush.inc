<?php

/**
 * @file
 * Define all drush command for content staging.
 */

/**
 * Implements hook_drush_command().
 */
function content_staging_drush_command() {
  return [
    'export-content' => [
      'description' => 'Export all contents.',
      'aliases' => ['ex'],
      'arguments' => [],
    ],
    'update-migration-config' => [
      'description' => 'Update migration config according the exported entities.',
      'aliases' => ['umc'],
      'arguments' => [],
    ],
  ];
}

/**
 * Callback function for 'export-content' drush command.
 */
function drush_content_staging_export_content() {
  \Drupal::service('content_staging.export')->export();
  _drush_log_drupal_messages();
}

function drush_content_staging_update_migration_config() {
  \Drupal::service('content_staging.import')->createMigrations();
  _drush_log_drupal_messages();
}
