<?php

/**
 * Hook to alter the list of unsetted fields.
 *
 * @param $unsetted_fields List of entity fields definitions that should be
 * excluded from the migration
 */
function hook_content_staging_unsetted_ENTITY_TYPE_fields_alter(&$unsetted_fields) {
  foreach ($unsetted_fields as $index => $field_name) {
    if ($field_name === 'machine_name') {
      unset($unsetted_fields[$index]);
      break;
    }
  }
}
