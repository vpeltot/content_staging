<?php

namespace Drupal\content_staging\Event;

/**
 * Defines events for the content_staging module.
 */
final class ContentStagingEvents {

  /**
   * Event fired when the migration field process definition is created.
   *
   * @var string
   */
  const PROCESS_FIELD_DEFINITION = 'content_staging.create_migration_process_field_definition';

  /**
   * Event fired before export doing.
   *
   * @var string
   */
  const BEFORE_EXPORT = 'content_staging.before_export';

}
