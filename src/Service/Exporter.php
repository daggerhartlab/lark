<?php

declare(strict_types=1);

namespace Drupal\lark\Service;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\FileInterface;
use Drupal\lark\Model\ExportableInterface;
use Drupal\lark\Model\LarkSettings;
use Drupal\lark\Plugin\Lark\SourceInterface;

/**
 * Export entities and their dependencies.
 */
class Exporter implements ExporterInterface {

  use StringTranslationTrait;

  /**
   * EntityExporter constructor.
   *
   * @param \Drupal\lark\Service\ExportableFactoryInterface $exportableFactory
   *   The lark exportable factory service.
   * @param \Drupal\lark\Service\SourceManagerInterface $sourceManager
   *   The lark source plugin manager service.
   * @param \Drupal\lark\Service\FieldTypeHandlerManagerInterface $fieldTypeManager
   *   The lark field type handler plugin manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(
    protected LarkSettings $settings,
    protected ExportableFactoryInterface $exportableFactory,
    protected SourceManagerInterface $sourceManager,
    protected FieldTypeHandlerManagerInterface $fieldTypeManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FileSystemInterface $fileSystem,
    protected LoggerChannelInterface $logger,
    protected MessengerInterface $messenger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function exportEntity(string $source_plugin_id, string $entity_type_id, int $entity_id, bool $show_messages = TRUE): void {
    $source = $this->sourceManager->getSourceInstance($source_plugin_id);
    $exportables = $this->exportableFactory->getEntityExportables($entity_type_id, $entity_id);
    foreach ($exportables as $exportable) {
      if ($this->writeToYaml($source, $exportable)) {
        $message = $this->t('Exported @entity_type_id : @entity_id : @label', [
          '@entity_type_id' => $exportable->entity()->getEntityTypeId(),
          '@entity_id' => $exportable->entity()->id(),
          '@label' => $exportable->entity()->label(),
        ]);

        if ($show_messages) {
          $this->messenger->addStatus($message);
        }
        $this->logger->notice($message);
      }
      else {
        $message = $this->t('Failed to export @entity_type_id : @entity_id : @label', [
          '@entity_type_id' => $exportable->entity()->getEntityTypeId(),
          '@entity_id' => $exportable->entity()->id(),
          '@label' => $exportable->entity()->label(),
        ]);
        if ($show_messages) {
          $this->messenger->addError($message);
        }
        $this->logger->error($message);
      }
    }
  }

  /**
   * Export an entity to YAML.
   *
   * @param \Drupal\lark\Plugin\Lark\SourceInterface $source
   *   Source plugin.
   * @param \Drupal\lark\Model\ExportableInterface $exportable
   *   Exportable entity model.
   *
   * @return bool
   *   Whether the export was successful.
   */
  protected function writeToYaml(SourceInterface $source, ExportableInterface $exportable): bool {
    // Prepare the export destination.
    $destination_directory = $source->getDestinationDirectory(
      $exportable->entity()->getEntityTypeId(),
      $exportable->entity()->bundle(),
    );
    $this->fileSystem->prepareDirectory($destination_directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    $destination_filepath = $source->getDestinationFilepath(
      $exportable->entity()->getEntityTypeId(),
      $exportable->entity()->bundle(),
      $exportable->getExportFilename(),
    );
    $exportable->setExportFilepath($destination_filepath);

    // If it's a file, export the file alongside the yaml.
    if (
      $exportable->entity() instanceof FileInterface &&
      $exportable->entity()->getFileUri() &&
      $this->settings->shouldExportAssets()
    ) {
      $asset_file = $exportable->entity()->getFileUri();
      $this->fileSystem->copy(
        $asset_file,
        $destination_directory . DIRECTORY_SEPARATOR . $exportable->entity()->uuid() . '--' . basename($asset_file),
        $this->settings->assetExportFileExists()
      );

      // If there's a file without the prefix, it is old and can be removed.
      // @todo Remove this in a future release.
      if (file_exists($destination_directory . DIRECTORY_SEPARATOR . basename($asset_file))) {
        $this->fileSystem->delete($destination_directory . DIRECTORY_SEPARATOR . basename($asset_file));
      }
    }

    return (bool) file_put_contents(
      $destination_filepath,
      $exportable->toYaml(),
    );
  }

  /**
   * Get entity export array.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity.
   *
   * @return array
   *   Entity export array.
   */
  public static function getEntityExportArray(ContentEntityInterface $entity): array {
    $array = $entity->toArray();
    $handler_manager = \Drupal::service(FieldTypeHandlerManagerInterface::class);

    // Remove keys that may not be unique across environments.
    $id_keys = array_filter([
      $entity->getEntityType()->getKey('id'),
      $entity->getEntityType()->getKey('revision'),
    ]);
    foreach ($id_keys as $id_key) {
      unset($array[$id_key]);
    }

    // Process the field values through the field type handlers.
    foreach ($array as $field_name => $default_values) {
      if (is_array($default_values)) {
        $array[$field_name] = $handler_manager->alterExportValues($default_values, $entity, $entity->get($field_name));
      }
    }

    return $array;
  }

}
