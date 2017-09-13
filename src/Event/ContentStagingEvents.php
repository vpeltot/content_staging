<?php

namespace Drupal\content_staging\Event;

/**
 * Defines events for the content_staging module.
 */
final class ContentStagingEvents {

  /**
   * Name of the event fired when a new incident is reported.
   *
   * This event allows modules to perform an action whenever a new incident is
   * reported via the incident report form. The event listener method receives a
   * \Drupal\events_example\Event\IncidentReportEvent instance.
   *
   * @Event
   *
   * @see \Drupal\events_example\Event\IncidentReportEvent
   *
   * @var string
   */
  const PROCESS_FIELD_DEFINITION = 'content_staging.create_migration_process_field_definition';

}
