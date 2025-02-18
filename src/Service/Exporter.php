<?php

declare(strict_types=1);

namespace Drupal\lark\Service;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\FileInterface;
use Drupal\lark\Model\ExportableInterface;
use Drupal\lark\Model\LarkSettings;
use Drupal\lark\Entity\LarkSourceInterface;
use Drupal\user\UserInterface;

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
  public function exportEntity(string $source_id, string $entity_type_id, int $entity_id, bool $show_messages = TRUE, array $exports_meta_options_overrides = []): void {
    $source = $this->entityTypeManager->getStorage('lark_source')->load($source_id);
    $exportables = $this->exportableFactory->getEntityExportables($entity_type_id, $entity_id, $source, $exports_meta_options_overrides);

    foreach ($exportables as $exportable) {
      if ($this->writeToYaml($exportable)) {
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
   * @param \Drupal\lark\Model\ExportableInterface $exportable
   *   Exportable entity model.
   *
   * @return bool
   *   Whether the export was successful.
   */
  protected function writeToYaml(ExportableInterface $exportable): bool {
    return (bool) \file_put_contents(
      $exportable->getFilepath(),
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
  public static function getEntityArray(ContentEntityInterface $entity): array {
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

  /**
   * Get dependencies as array of uuid -> entity type id pairs for the entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity.
   * @param array $dependencies
   *   Array that is modified during recursion.
   *
   * @return array
   *   Uuid and entity_type_id pairs.
   */
  public static function getEntityExportDependencies(ContentEntityInterface $entity, array &$dependencies = []): array {
    $entity->getFieldDefinitions();

    foreach ($entity->getFields() as $field) {
      if ($field instanceof EntityReferenceFieldItemListInterface) {
        foreach ($field->referencedEntities() as $referenced_entity) {
          // Don't export config entities.
          if ($referenced_entity instanceof ConfigEntityInterface) {
            continue;
          }
          // Don't export users.
          if ($referenced_entity instanceof UserInterface) {
            continue;
          }

          // If the referenced entity is already processing, do nothing.
          if (array_key_exists($referenced_entity->uuid(), $dependencies)) {
            continue;
          }

          $dependencies += static::getEntityExportDependencies($referenced_entity, $dependencies);
        }
      }
    }

    $dependencies[$entity->uuid()] = $entity->getEntityTypeId();
    return $dependencies;
  }

}
