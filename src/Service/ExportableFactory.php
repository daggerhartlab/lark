<?php

namespace Drupal\lark\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\lark\Exception\LarkEntityNotFoundException;
use Drupal\lark\Model\Exportable;
use Drupal\lark\Model\ExportableInterface;
use Drupal\lark\Entity\LarkSourceInterface;
use Drupal\lark\Model\LarkSettings;
use Drupal\lark\Service\Utility\ExportableSourceResolver;
use Drupal\lark\Service\Utility\ExportableStatusResolver;
use Drupal\user\UserInterface;

/**
 * Factory for creating exportable entities.
 */
class ExportableFactory implements ExportableFactoryInterface {

  protected array $exportablesCache = [];

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityRepositoryInterface $entityRepository,
    protected LarkSettings $larkSettings,
    protected FieldTypeHandlerManagerInterface $fieldTypeManager,
    protected ImporterInterface $importer,
    protected ExportableSourceResolver $sourceResolver,
    protected ExportableStatusResolver $statusResolver,
    protected ModuleHandlerInterface $moduleHandler,
    protected FileSystemInterface $fileSystem,
    protected MetaOptionManager $metaOptionManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function createFromEntity(ContentEntityInterface $entity): ExportableInterface {
    $exportable = new Exportable($entity);
    $exportable->setSource($this->sourceResolver->resolveSource($exportable));
    $exportable->setStatus($this->statusResolver->resolveStatus($exportable));
    return $exportable;
  }

  /**
   * {@inheritdoc}
   */
  public function createFromUuid(string $uuid): ExportableInterface {
    $content_entity_types = array_filter($this->entityTypeManager->getDefinitions(), function($def) {
      return $def instanceof ContentEntityTypeInterface;
    });

    $found = NULL;
    foreach ($content_entity_types as $entity_type) {
      $found = $this->entityTypeManager->getStorage($entity_type->id())->loadByProperties([
        'uuid' => $uuid,
      ]);
      if ($found) {
        $found = reset($found);
        break;
      }
    }

    if ($found) {
      return $this->createFromEntity($found);
    }

    /** @var \Drupal\lark\Entity\LarkSourceInterface[] $sources */
    $sources = $this->entityTypeManager->getStorage('lark_source')->loadByProperties([
      'status' => 1,
    ]);
    foreach ($sources as $source) {
      $exportable = $this->createFromSource($source->id(), $uuid);
      if ($exportable) {
        return $exportable;
      }
    }

    throw new LarkEntityNotFoundException("UUID not found in database nor source exports: {$uuid}");
  }

  /**
   * {@inheritdoc}
   */
  public function createFromSource(string $source_id, string $uuid): ?ExportableInterface {
    $exportables = $this->createFromSourceWithDependencies($source_id, $uuid);
    return $exportables[$uuid] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function createFromSourceWithDependencies(string $source_id, string $root_uuid): array {
    if (array_key_exists($root_uuid, $this->exportablesCache)) {
      return $this->exportablesCache[$root_uuid];
    }

    /** @var \Drupal\lark\Entity\LarkSourceInterface $source */
    $source = $this->entityTypeManager->getStorage('lark_source')->load($source_id);
    $exports = $this->importer->discoverSourceExport($source, $root_uuid);
    $exportables = [];
    foreach ($exports as $uuid => $export) {
      $entity = $this->entityRepository->loadEntityByUuid($export->entityTypeId(), $export->uuid());

      if (!$entity) {
        $entity = $this->entityTypeManager->getStorage($export->entityTypeId())->create($export->fields('default'));
      }

      $exportable = new Exportable($entity);
      $exportable
        ->setDependencies($export->dependencies())
        ->setOptions($export->options())
        ->setSource($source)
        ->setFilepath($export->path())
        ->setStatus($this->statusResolver->resolveStatus($exportable, $export));

      $exportables[$uuid] = $exportable;
    };

    $this->prepareExportables($exportables, $source);
    $this->exportablesCache[$root_uuid] = $exportables;
    return $this->exportablesCache[$root_uuid];
  }

  /**
   * {@inheritdoc}
   * @param array $exports_meta_option_overrides
   * @param \Drupal\lark\Entity\LarkSourceInterface|null $source
   */
  public function getEntityExportables(string $entity_type_id, int $entity_id, ?LarkSourceInterface $source = NULL, array $exports_meta_option_overrides = []): array {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($entity_id);
    if (!$entity) {
      throw new LarkEntityNotFoundException("Entity of type {$entity_type_id} and ID {$entity_id} not found.");
    }

    // Don't return cache if we're overriding meta_options.
    if (array_key_exists($entity->uuid(), $this->exportablesCache) && (empty($source) || empty($exports_meta_option_overrides))) {
      return $this->exportablesCache[$entity->uuid()];
    }

    $exportables = [];
    $exportables = $this->getEntityExportablesRecursive($entity, $exportables, $source, $exports_meta_option_overrides);
    // Because we're registering the entities in hierarchical order, reverse the
    // array to ensure that dependent entities are after their dependencies.
    $exportables = array_reverse($exportables);
    $this->prepareExportables($exportables, $source, $exports_meta_option_overrides);
    $this->exportablesCache[$entity->uuid()] = $exportables;

    return $exportables;
  }

  /**
   * Get the entity and prepare it for export.
   *
   * @param ContentEntityInterface $entity
   *   The entity to get exportables for.
   * @param array $exportables
   *   The exportables array to populate.
   * @param \Drupal\lark\Entity\LarkSourceInterface|null $source
   *   Override the source for the exportables being gathered.
   * @param array $exports_meta_option_overrides
   *   Override meta option values for the exportables being gathered.
   *
   * @return array
   *   The exportables array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getEntityExportablesRecursive(ContentEntityInterface $entity, array &$exportables = [], ?LarkSourceInterface $source = NULL, array $exports_meta_option_overrides = []): array {
    // Allow modules to prevent the entity from being exported.
    $should_export = $this->moduleHandler->invokeAll('lark_should_export_entity', [$entity]);
    if (!empty($should_export)) {
      $should_export = array_pop($should_export);
      if ($should_export === FALSE) {
        return $exportables;
      }
    }

    // If the entity is already registered/processing, nothing to do.
    if (isset($exportables[$entity->uuid()])) {
      return $exportables;
    }
    // Register the entity to prevent circular recursion.
    $exportables[$entity->uuid()] = NULL;

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

          // If the referenced entity is already processing, do nothing.
          if (array_key_exists($referenced_entity->uuid(), $exportables)) {
            continue;
          }

          $dependencies[$referenced_entity->uuid()] = $referenced_entity->getEntityTypeId();
          $exportables += $this->getEntityExportablesRecursive($referenced_entity, $exportables, $source, $exports_meta_option_overrides);
        }
      }
    }

    // Exportable for the current entity.
    $exportable = new Exportable($entity);
    $exportable->setDependencies($dependencies);
    $exportable->setSource($source ?? $this->sourceResolver->resolveSource($exportable));
    $exportable->setStatus($this->statusResolver->resolveStatus($exportable));

    if ($exportable->getExportExists() && isset($exportable->getSourceExportArray()['_meta']['options'])) {
      $exportable->setOptions($exportable->getSourceExportArray()['_meta']['options']);
    }
    $this->overrideMetaValues($exportable, $exports_meta_option_overrides);

    $exportables[$exportable->entity()->uuid()] = $exportable;
    return $exportables;
  }

  /**
   * Perform file actions and adjustments on exportable.
   *
   * @param \Drupal\lark\Model\ExportableInterface[] $exportables
   * @param \Drupal\lark\Entity\LarkSourceInterface|null $source
   * @param array $exports_meta_option_overrides
   *
   * @return \Drupal\lark\Model\ExportableInterface[]
   */
  protected function prepareExportables(array $exportables, ?LarkSourceInterface $source = NULL, array $exports_meta_option_overrides = []): array {
    foreach ($exportables as $exportable) {
      $entity = $exportable->entity();

      if (!$source && $exportable->getSource()) {
        $source = $exportable->getSource();
      }

      if (!$source) {
        $source = $this->entityTypeManager->getStorage('lark_source')->load($this->larkSettings->defaultSource());
      }

      if ($source) {
        // Prepare the export destination.
        $destination_directory = $source->getDestinationDirectory(
          $entity->getEntityTypeId(),
          $entity->bundle(),
        );
        $this->fileSystem->prepareDirectory($destination_directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

        $destination_filepath = $source->getDestinationFilepath(
          $entity->getEntityTypeId(),
          $entity->bundle(),
          $exportable->getFilename(),
        );
        $exportable->setFilepath($destination_filepath);
      }

      $this->overrideMetaValues($exportable, $exports_meta_option_overrides);
    }

    return $exportables;
  }

  /**
   * @param \Drupal\lark\Model\ExportableInterface $exportable
   * @param array $exports_meta_option_overrides
   *
   * @return void
   */
  protected function overrideMetaValues(ExportableInterface $exportable, array $exports_meta_option_overrides): void {
    $entity = $exportable->entity();
    $uuid = $entity->uuid();
    foreach ($this->metaOptionManager->getInstances() as $meta_option) {
      if (!$meta_option->applies($entity)) {
        continue;
      }

      // Set and meta option overrides passed in from the caller.
      if (
        array_key_exists($uuid, $exports_meta_option_overrides) &&
        array_key_exists($meta_option->id(), $exports_meta_option_overrides[$uuid]) &&
        !empty($exports_meta_option_overrides[$uuid][$meta_option->id()])
      ) {
        $exportable->setOption($meta_option->id(), $exports_meta_option_overrides[$uuid][$meta_option->id()]);
      }

      // Allow meta option plugins to perform last minute changes or actions.
      $meta_option->preExportWrite($exportable);
    }
  }

}
