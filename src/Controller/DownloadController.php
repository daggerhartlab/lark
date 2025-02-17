<?php

namespace Drupal\lark\Controller;

use Drupal\Core\Archiver\ArchiveTar;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\lark\Entity\LarkSourceInterface;
use Drupal\lark\Service\ExportableFactoryInterface;
use Drupal\lark\Service\ImporterInterface;
use Drupal\lark\Service\MetaOptionManager;
use Drupal\system\FileDownloadController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;

class DownloadController extends ControllerBase {

  public function __construct(
    protected FileDownloadController $fileDownloadController,
    protected FileSystemInterface $fileSystem,
    protected ExportableFactoryInterface $exportableFactory,
    protected MetaOptionManager $metaOptionManager,
    protected ImporterInterface $importer,
    EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->entityTypeManager = $entityTypeManager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      FileDownloadController::create($container),
      $container->get(FileSystemInterface::class),
      $container->get(ExportableFactoryInterface::class),
      $container->get(MetaOptionManager::class),
      $container->get(ImporterInterface::class),
      $container->get(EntityTypeManagerInterface::class),
    );
  }

  /**
   * Archive an entity and return a download response.
   *
   * @param string $entity_type_id
   *   Entity type id.
   * @param int|string $entity_id
   *   Entity id.
   * @param array $meta_option_overrides
   *   Meta option overrides.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   File download response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function downloadExportResponse(string $entity_type_id, int|string $entity_id, array $meta_option_overrides = []): BinaryFileResponse {
    // Make a source that acts in place of a filesystem source.
    /** @var \Drupal\lark\Entity\LarkSourceInterface $download_source */
    $download_source = $this->entityTypeManager->getStorage('lark_source')->create([
      'id' => 'download',
      'label' => 'Download',
      // Write to the /tmp directory as needed during export.
      'directory' => $this->fileSystem->getTempDirectory(),
      'status' => 1,
    ]);

    $exportables = $this->exportableFactory->getEntityExportables(
      $entity_type_id,
      (int) $entity_id,
      NULL,
      $meta_option_overrides,
    );

    $archive = $this->newArchive($download_source->directoryProcessed() . '/lark-export.tar.gz');
    $this->addExportablesToArchive($archive, $exportables);

    $request = new Request(['file' => 'lark-export.tar.gz']);
    return $this->fileDownloadController->download($request, 'temporary');
  }

  /**
   * Archive a source and return a download response.
   *
   * @param \Drupal\lark\Entity\LarkSourceInterface $source
   *   Source to export.
   * @param array $meta_option_overrides
   *   Meta option overrides.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   File download response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function downloadSourceResponse(LarkSourceInterface $source, array $meta_option_overrides = []): BinaryFileResponse {
    // Make a source that acts in place of a filesystem source.
    /** @var \Drupal\lark\Entity\LarkSourceInterface $download_source */
    $download_source = $this->entityTypeManager->getStorage('lark_source')->create([
      'id' => 'download',
      'label' => 'Download',
      // Write to the /tmp directory as needed during export.
      'directory' => $this->fileSystem->getTempDirectory(),
      'status' => 1,
    ]);

    $archive = $this->newArchive($download_source->directoryProcessed() . '/lark-source.tar.gz');

    // Load the source's exports as a list of uuids to download.
    $exports = $this->importer->discoverSourceExports($source);

    // If we export root level items, we'll get their dependencies.
    $root_level_exports = array_filter($exports, function ($export, $uuid) use ($exports) {
      foreach ($exports as $other_export) {
        if (isset($other_export['_meta']['depends'][$uuid])) {
          return FALSE;
        }
      }

      return TRUE;
    }, ARRAY_FILTER_USE_BOTH);

    // Loop through each root-level export and add its entity to the archive.
    foreach ($root_level_exports as $uuid => $export) {
      // Get the entity's id by its uuid.
      $entity_type_id = $export['_meta']['entity_type'];
      $entity = $this->entityTypeManager->getStorage($entity_type_id)->loadByProperties([
        'uuid' => $uuid,
      ]);
      if ($entity) {
        $entity = reset($entity);
      }
      if (!$entity) {
        $this->messenger->addError("Entity not found: {$entity_type_id} - uuid: {$uuid}");
        continue;
      }

      // Export entities from the database.
      $exportables = $this->exportableFactory->getEntityExportables(
        $entity_type_id,
        (int) $entity->id(),
        NULL,
        $meta_option_overrides,
      );

      $this->addExportablesToArchive($archive, $exportables);
    }


    $request = new Request(['file' => 'lark-source.tar.gz']);
    return $this->fileDownloadController->download($request, 'temporary');
  }

  /**
   * Create new archive.
   *
   * @param string $filepath
   *   Path to new archive.
   *
   * @return \Drupal\Core\Archiver\ArchiveTar
   *   New archive.
   */
  protected function newArchive(string $filepath): ArchiveTar {
    try {
      $this->fileSystem->delete($filepath);
    }
    catch (FileException $e) {
      // Ignore failed deletes.
    }

    return new ArchiveTar($filepath, 'gz');
  }

  /**
   * @param \Drupal\Core\Archiver\ArchiveTar $archive
   * @param \Drupal\lark\Model\ExportableInterface[] $exportables
   *
   * @return void
   */
  protected function addExportablesToArchive(ArchiveTar $archive, array $exportables) {
    // Add all contents of the export storage to the archive.
    foreach ($exportables as $exportable) {
      $source = $exportable->getSource();
      $filename = str_replace($source->directoryProcessed(FALSE), '', $exportable->getExportFilepath());
      $archive->addString($filename, Yaml::encode($exportable->toArray()));

      foreach ($this->metaOptionManager->getInstances() as $meta_option) {
        if ($meta_option->applies($exportable->entity())) {
          $meta_option->preExportDownload($archive, $exportable);
        }
      }
    }
  }

}
