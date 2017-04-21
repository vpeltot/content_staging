<?php

/**
 * Hook to alter the list of unsetted fields.
 *
 * @param $unsetted_fields List of entity fields definitions that should be
 * excluded from the migration
 */
function hook_content_staging_unsetted_ENTITY_TYPE_fields_alter(&$unsetted_fields) {
  $unsetted_fields[] = 'vid';
}