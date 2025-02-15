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
   * @param \Drupal\lark\Service\AssetFileManager $assetFileManager
   *   The asset file manager.
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
    protected AssetFileManager $assetFileManager,
    protected MetaOptionManager $metaOptionManager,
    protected LoggerChannelInterface $logger,
    protected MessengerInterface $messenger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function exportEntity(string $source_plugin_id, string $entity_type_id, int $entity_id, bool $show_messages = TRUE, array $exports_meta_options_overrides = []): void {
    $source = $this->sourceManager->getSourceInstance($source_plugin_id);
    $exportables = $this->exportableFactory->getEntityExportables($entity_type_id, $entity_id);

    foreach ($exportables as $exportable) {
      // Add meta option overrides to the export.
      $meta_option_overrides = $exports_meta_options_overrides[$exportable->entity()->uuid()] ?? [];

      if ($this->writeToYaml($source, $exportable, $meta_option_overrides)) {
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
   * @param array $meta_option_overrides
   *
   * @return bool
   *   Whether the export was successful.
   */
  protected function writeToYaml(SourceInterface $source, ExportableInterface $exportable, array $meta_option_overrides = []): bool {
    // Set and meta option overrides passed in from the caller.
    foreach ($this->metaOptionManager->getInstances() as $meta_option) {
      if (!$meta_option->applies($exportable->entity())) {
        continue;
      }

      if (
        array_key_exists($meta_option->id(), $meta_option_overrides) &&
        !empty($meta_option_overrides[$meta_option->id()])
      ) {
        $exportable->setMetaOption($meta_option->id(), $meta_option_overrides[$meta_option->id()]);
      }
    }

    $entity = $exportable->entity();
    // Prepare the export destination.
    $destination_directory = $source->getDestinationDirectory(
      $entity->getEntityTypeId(),
      $entity->bundle(),
    );
    $this->fileSystem->prepareDirectory($destination_directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    $destination_filepath = $source->getDestinationFilepath(
      $entity->getEntityTypeId(),
      $entity->bundle(),
      $exportable->getExportFilename(),
    );
    $exportable->setExportFilepath($destination_filepath);

    // Allow meta option plugins to perform last minute changes or actions.
    foreach ($this->metaOptionManager->getInstances() as $meta_option) {
      if ($meta_option->applies($entity)) {
        $meta_option->preWriteToYaml($exportable);
      }
    }

    return (bool) \file_put_contents(
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
