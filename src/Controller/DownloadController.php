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
use Drupal\lark\Service\LarkSourceManager;
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
    protected LarkSourceManager $sourceManager,
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
      $container->get(LarkSourceManager::class),
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
    $download_source = $this->sourceManager->getTmpSource();
    $exportables = $this->exportableFactory->createFromEntityWithDependencies(
      $entity_type_id,
      (int) $entity_id,
      $download_source,
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
    $download_source = $this->sourceManager->getTmpSource();
    $archive = $this->newArchive($download_source->directoryProcessed() . '/lark-source.tar.gz');

    // Load the source's exports as a list of uuids to download.
    $exports = $this->importer->discoverSourceExports($source);

    // Loop through each root-level export and add its entity to the archive.
    foreach ($exports->getRootLevel() as $uuid => $export) {
      // Get the entity's id by its uuid.
      $entity_type_id = $export->entityTypeId();
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
      $exportables = $this->exportableFactory->createFromEntityWithDependencies(
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
  protected function addExportablesToArchive(ArchiveTar $archive, array $exportables): void {
    // Add all contents of the export storage to the archive.
    foreach ($exportables as $exportable) {
      $source = $exportable->getSource();
      $filename = str_replace($source->directoryProcessed(FALSE), '', $exportable->getFilepath());
      $archive->addString($filename, Yaml::encode($exportable->toArray()));

      foreach ($this->metaOptionManager->getInstances() as $meta_option) {
        if ($meta_option->applies($exportable->entity())) {
          $meta_option->preExportDownload($archive, $exportable);
        }
      }
    }
  }

}
