<?php

namespace Drupal\lark\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;
use Drupal\lark\Model\Exportable;
use Drupal\lark\Model\ExportableInterface;
use Drupal\lark\Model\LarkSettings;

/**
 * Handles the exporting/importing of assets attached to File entities.
 */
class AssetFileManager {

  /**
   * AssetFileManager constructor.
   *
   * @param \Drupal\lark\Model\LarkSettings $larkSettings
   *   Lark settings.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   File system service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   */
  public function __construct(
    protected LarkSettings $larkSettings,
    protected FileSystemInterface $fileSystem,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Export the asset attached to the given File entity.
   *
   * @param \Drupal\lark\Model\ExportableInterface $exportable
   *   Exportable that is a file.
   *
   * @return string
   *   Path to new file.
   */
  public function exportAsset(ExportableInterface $exportable): string {
    if (!$exportable->isFile()) {
      return '';
    }

    $file = $exportable->entity();
    $destination = \dirname($exportable->getFilepath());
    $destination = \rtrim($destination, DIRECTORY_SEPARATOR);
    $this->fileSystem->prepareDirectory($destination);

    $asset_file = $file->getFileUri();
    return $this->fileSystem->copy(
      $asset_file,
      $destination . DIRECTORY_SEPARATOR . $exportable->getFileAssetFilename(),
      $this->larkSettings->assetExportFileExists()
    );
  }

  /**
   * Copies a file from default content directory to the site's file system.
   *
   * @param \Drupal\file\FileInterface $entity
   *   The file entity.
   * @param string $source_directory
   *   The path to the file to copy.
   * @param string $destination_uri
   *   Where the file should be copied to.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function importAsset(FileInterface $entity, string $source_directory, string $destination_uri): void {
    $exportable = new Exportable($entity);
    if (!$exportable->isFile()) {
      return;
    }

    // If the source file doesn't exist, there's nothing we can do.
    $source = $source_directory . DIRECTORY_SEPARATOR . $exportable->getFileAssetFilename();
    $destination_directory = dirname($destination_uri);

    if (!file_exists($source)) {
      // Attempt to fall back without the uuid prefix.
      // @todo This functionality is legacy and will be removed in a future.
      $source = $source_directory . DIRECTORY_SEPARATOR . basename($destination_uri);
      if (!file_exists($source)) {
        return;
      }
    }

    $copy_file = TRUE;
    if (\file_exists($destination_uri)) {
      $source_hash = hash_file('sha256', $source);
      assert(is_string($source_hash));
      $destination_hash = hash_file('sha256', $destination_uri);
      assert(is_string($destination_hash));

      if (hash_equals($source_hash, $destination_hash) && $this->entityTypeManager->getStorage('file')->loadByProperties(['uri' => $destination_uri]) === []) {
        // If the file hashes match and the file is not already a managed file
        // then do not copy a new version to the file system. This prevents
        // re-installs during development from creating unnecessary duplicates.
        $copy_file = FALSE;
      }
    }

    $this->fileSystem->prepareDirectory($destination_directory, FileSystemInterface::CREATE_DIRECTORY);
    if ($copy_file) {
      $uri = $this->fileSystem->copy(
        $source,
        $destination_uri,
        $this->larkSettings->assetImportFileExists()
      );
      $entity->setFileUri($uri);
    }
  }

}
