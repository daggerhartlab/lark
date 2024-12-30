<?php

namespace Drupal\lark\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\lark\Exception\LarkEntityNotFoundException;
use Drupal\lark\Model\Exportable;
use Drupal\lark\Model\ExportableInterface;
use Drupal\lark\Service\Utility\ExportableStatusResolver;
use Drupal\user\UserInterface;

/**
 * Factory for creating exportable entities.
 */
class ExportableFactory implements ExportableFactoryInterface {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityRepositoryInterface $entityRepository,
    protected FieldTypeHandlerManagerInterface $fieldTypeManager,
    protected SourceManagerInterface $sourceManager,
    protected ImporterInterface $importer,
    protected ExportableStatusResolver $statusResolver,
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function createFromEntity(ContentEntityInterface $entity): ExportableInterface {
    $exportables = $this->getEntityExportables($entity->getEntityTypeId(), (int) $entity->id());
    $exportable = $exportables[$entity->uuid()];
    $exportable->setStatus($this->statusResolver->getExportableStatus($exportable));
    return $exportable;
  }

  /**
   * {@inheritdoc}
   */
  public function createFromSource(string $source_plugin_id, string $uuid): ExportableInterface {
    $exportables = $this->createFromSourceWithDependencies($source_plugin_id, $uuid);
    return $exportables[$uuid];
  }

  /**
   * {@inheritdoc}
   */
  public function createFromSourceWithDependencies(string $source_plugin_id, string $root_uuid): array {
    $source = $this->sourceManager->createInstance($source_plugin_id);
    $exports = $this->importer->discoverSourceExport($source, $root_uuid);
    $exportables = [];
    foreach ($exports as $uuid => $export) {
      $entity = $this->entityRepository->loadEntityByUuid($export['_meta']['entity_type'], $export['_meta']['uuid']);

      if (!$entity) {
        $entity = $this->entityTypeManager->getStorage($export['_meta']['entity_type'])->create($export['default']);
      }

      $exportable = new Exportable($entity);
      $exportable
        ->setDependencies($export['_meta']['depends'] ?? [])
        ->setSource($source)
        ->setExportFilepath($export['_meta']['path'])
        ->setStatus($this->statusResolver->getExportableStatus($exportable, $export));

      $exportables[$uuid] = $exportable;
    };

    return $exportables;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityExportables(string $entity_type_id, int $entity_id, array &$exportables = []): array {
    $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($entity_id);
    if (!$entity) {
      throw new LarkEntityNotFoundException("Entity of type {$entity_type_id} and ID {$entity_id} not found.");
    }

    // Allow modules to prevent the entity from being exported.
    $should_export = $this->moduleHandler->invokeAll('lark_should_export_entity', [$entity]);
    if (!empty($should_export)) {
      $should_export = array_pop($should_export);
      if ($should_export === FALSE) {
        return $exportables;
      }
    }

    // Field definitions are lazy loaded and are populated only when needed.
    // By calling ::getFieldDefinitions() we are sure that field definitions
    // are populated and available in the dump output.
    // @see https://www.drupal.org/node/2311557
    if ($entity instanceof FieldableEntityInterface) {
      $entity->getFieldDefinitions();
    }

    // Track referenced entities as dependencies of this entity.
    $dependencies = [];
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

          $dependencies[$referenced_entity->uuid()] = $referenced_entity->getEntityTypeId();
          $exportables += $this->getEntityExportables($referenced_entity->getEntityTypeId(), (int) $referenced_entity->id(), $exportables);
        }
      }
    }

    // Exportable for the current entity.
    $exportable = new Exportable($entity);
    $exportable->setDependencies($dependencies);
    $exportable->setSource($this->statusResolver->getExportableSource($exportable));
    $exportable->setStatus($this->statusResolver->getExportableStatus($exportable));
    $exportables[$exportable->entity()->uuid()] = $exportable;

    return $exportables;
  }

}
