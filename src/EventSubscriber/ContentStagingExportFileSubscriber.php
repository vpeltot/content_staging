<?php

namespace Drupal\content_staging\EventSubscriber;

use Drupal\content_staging\ContentStagingManager;
use Drupal\content_staging\Event\ContentStagingBeforeExportEvent;
use Drupal\content_staging\Event\ContentStagingEvents;
use Drupal\Core\File\FileSystem;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribe to ContentStagingEvents::BEFORE_EXPORT events.
 *
 * Perform action before export file entities.
 */
class ContentStagingExportFileSubscriber implements EventSubscriberInterface {

  /**
   * The content staging manager service.
   *
   * @var \Drupal\content_staging\ContentStagingManager
   */
  protected $contentStagingManager;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * ContentStagingExportFileSubscriber constructor.
   *
   * @param \Drupal\content_staging\ContentStagingManager $content_staging_manager
   *   The content staging manager service.
   */
  public function __construct(ContentStagingManager $content_staging_manager, FileSystem $file_system) {
    $this->contentStagingManager = $content_staging_manager;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ContentStagingEvents::BEFORE_EXPORT][] = ['exportFiles', -10];

    return $events;
  }

  /**
   * Export all files.
   *
   * @param \Drupal\content_staging\Event\ContentStagingBeforeExportEvent $event
   */
  public function exportFiles(ContentStagingBeforeExportEvent $event) {
    if ($event->getEntityTypeId() == 'file') {
      $export_path = realpath(DRUPAL_ROOT . '/' . $this->contentStagingManager->getDirectory());

      /** @var \Drupal\file\Entity\File $file */
      foreach ($event->getEntities()['file'] as $file) {
        $folder = $export_path . '/files/' . dirname(file_uri_target($file->getFileUri()));
        if (!file_exists($folder)) {
          mkdir($folder, 0777, TRUE);
        }
        file_put_contents($folder . '/' . $this->fileSystem->basename($file->getFileUri()), file_get_contents($file->getFileUri()));
      }
    }
  }

}
