Content Staging

This module provide a content staging for all Drupal 8 projects.

Exporting content:
--------------------

1. After enabling this module, go to /admin/config/system/content-staging :
     - Choose all entity types / bundles you want to export the content
     - Change the default content staging directory ('../staging' by default).
       This directory is relative to the drupal root.

2. Run the drush command :
    $ drush export-content


Importing content:
--------------------

1. Run the drush command to update migration entities
   regarding the previous configuration.
    $ drush update-migration-config

2. Run the migration
    $ drush mi --group content_staging


TODO:
--------------------

Add an alert if an entity reference exists
when an entity type is chosen in the admin page.
