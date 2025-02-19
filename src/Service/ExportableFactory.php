<?php

namespace Drupal\lark\Service;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\lark\Exception\LarkEntityNotFoundException;
use Drupal\lark\Model\Exportable;
use Drupal\lark\Model\ExportableInterface;
use Drupal\lark\Entity\LarkSourceInterface;
use Drupal\lark\Service\Utility\EntityUtility;
use Drupal\lark\Service\Utility\SourceResolver;
use Drupal\lark\Service\Utility\StatusResolver;

/**
 * Factory for creating exportable entities.
 */
class ExportableFactory implements ExportableFactoryInterface {

  /**
   * Cache single exportable items keyed by uuid.
   *
   * @var array
   */
  protected array $exportableCache = [];

  /**
   * Cache for arrays of exportables keyed by root exportabled uuid.
   *
   * @var array
   */
  protected array $collectionsCache = [];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityUtility $entityUtility,
    protected EntityRepositoryInterface $entityRepository,
    protected SourceResolver $sourceResolver,
    protected StatusResolver $statusResolver,
    protected FileSystemInterface $fileSystem,
    protected ImporterInterface $importer,
    protected MetaOptionManager $metaOptionManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function createFromEntity(ContentEntityInterface $entity): ExportableInterface {
    if (array_key_exists($entity->uuid(), $this->exportableCache)) {
      return $this->exportableCache[$entity->uuid()];
    }

    $exportable = new Exportable($entity);
    $this->prepareExportable($exportable);
    $this->exportableCache[$entity->uuid()] = $exportable;
    return $exportable;
  }

  /**
   * {@inheritdoc}
   */
  public function createFromEntityWithDependencies(string $entity_type_id, int $entity_id, ?LarkSourceInterface $source = NULL, array $meta_option_overrides = []): array {
    $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($entity_id);
    if (!$entity) {
      throw new LarkEntityNotFoundException("Entity of type {$entity_type_id} and ID {$entity_id} not found.");
    }

    if (!($entity instanceof ContentEntityInterface)) {
      throw new LarkEntityNotFoundException("Entity of type {$entity_type_id} and ID {$entity_id} is not a content entity.");
    }

    // Don't return cache if we're overriding meta_options.
    if (array_key_exists($entity->uuid(), $this->collectionsCache) && (empty($source) || empty($meta_option_overrides))) {
      return $this->collectionsCache[$entity->uuid()];
    }

    $items = [];
    $items = $this->entityUtility->getEntityUuidEntityTypePairs($entity, $items);
    foreach ($items as $item_uuid => $item_entity_type_id) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $item_entity */
      $item_entity = $this->entityRepository->loadEntityByUuid($item_entity_type_id, $item_uuid);
      $exportable = $this->createFromEntity($item_entity);
      if ($source || $meta_option_overrides) {
        $this->prepareExportable($exportable, $source, $meta_option_overrides);
      }
      $items[$item_uuid] = $exportable;
    }

    $this->collectionsCache[$entity->uuid()] = $items;
    return $items;
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
    if (array_key_exists($root_uuid, $this->collectionsCache)) {
      return $this->collectionsCache[$root_uuid];
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
      $this->prepareExportable($exportable, $source);

      // Override entity export values with source export values.
      $exportable
        ->setDependencies($export->dependencies())
        ->setOptions($export->options());

      // Set status in comparison to the sourceExportArray.
      $exportable->setStatus($this->statusResolver->resolveStatus($exportable, $export));
      $exportables[$uuid] = $exportable;
    };

    $this->collectionsCache[$root_uuid] = $exportables;
    return $this->collectionsCache[$root_uuid];
  }

  /**
   * {@inheritdoc}
   */
  public function createFromUuid(string $uuid): ExportableInterface {
    if (array_key_exists($uuid, $this->exportableCache)) {
      return $this->exportableCache[$uuid];
    }

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
      $this->exportableCache[$uuid] = $this->createFromEntity($found);
      return $this->exportableCache[$uuid];
    }

    /** @var \Drupal\lark\Entity\LarkSourceInterface[] $sources */
    $sources = $this->entityTypeManager->getStorage('lark_source')->loadByProperties([
      'status' => 1,
    ]);
    foreach ($sources as $source) {
      $exportable = $this->createFromSource($source->id(), $uuid);
      if ($exportable) {
        $this->exportableCache[$uuid] = $exportable;
        return $exportable;
      }
    }

    throw new LarkEntityNotFoundException("UUID not found in database nor source exports: {$uuid}");
  }

  /**
   * Prepares the exportable for use.
   *
   * @param \Drupal\lark\Model\ExportableInterface $exportable
   *   Exportable to prepare.
   * @param \Drupal\lark\Entity\LarkSourceInterface|null $source
   *   Pass in a Source to prepare the exportable specifically for that source.
   * @param array $meta_option_overrides
   *   Keyed by uuid.
   *
   * @return \Drupal\lark\Model\ExportableInterface
   */
  protected function prepareExportable(ExportableInterface $exportable, ?LarkSourceInterface $source = NULL, array $meta_option_overrides = []): ExportableInterface {
    $entity = $exportable->entity();

    // If no source was passed in, use the exportable's source.
    if (!$source && $exportable->getSource()) {
      $source = $exportable->getSource();
    }

    // If no source is set, attempt to find it amongst all sources.
    if (!$source) {
      $source = $this->sourceResolver->resolveSource($exportable);
    }

    // If no source found, use the default source.
    if (!$source) {
      $source = $this->sourceResolver->defaultSource();
    }

    // Set status once we have a source.
    $exportable->setSource($source);
    $exportable->setStatus($this->statusResolver->resolveStatus($exportable));

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

    // Override exportable options.
    $uuid = $entity->uuid();
    foreach ($this->metaOptionManager->getInstances() as $meta_option) {
      if (!$meta_option->applies($entity)) {
        continue;
      }

      // Override meta options for the exportable.
      if (
        array_key_exists($uuid, $meta_option_overrides) &&
        array_key_exists($meta_option->id(), $meta_option_overrides[$uuid]) &&
        !empty($meta_option_overrides[$uuid][$meta_option->id()])
      ) {
        $exportable->setOption($meta_option->id(), $meta_option_overrides[$uuid][$meta_option->id()]);
      }
    }

    return $exportable;
  }

}
