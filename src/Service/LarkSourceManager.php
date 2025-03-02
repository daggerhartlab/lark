<?php

namespace Drupal\lark\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\lark\Entity\LarkSourceInterface;
use Drupal\lark\Model\ExportableInterface;
use Drupal\lark\Model\LarkSettings;

/**
 * Source utility.
 */
class LarkSourceManager {

  use StringTranslationTrait;

  /**
   * Source storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $storage;

  /**
   * LarkSourceManager constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FileSystemInterface $fileSystem,
    protected LarkSettings $larkSettings,
  ) {
    $this->storage = $this->entityTypeManager->getStorage('lark_source');
  }

  /**
   * Get source storage.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   Source storage.
   */
  public function storage(): EntityStorageInterface {
    return $this->storage;
  }

  /**
   * Load source by id.
   *
   * @param string $id
   *   Source id.
   *
   * @return \Drupal\lark\Entity\LarkSourceInterface|null
   *   Source entity or NULL if not found.
   */
  public function load(string $id): ?LarkSourceInterface {
    return $this->storage()->load($id);
  }

  /**
   * Load multiple sources by id.
   *
   * @param array $ids
   *   Source ids.
   *
   * @return \Drupal\lark\Entity\LarkSourceInterface[]
   *   Source entities.
   */
  public function loadMultiple(array $ids): array {
    return $this->storage()->loadMultiple($ids);
  }

  /**
   * Load sources by properties.
   *
   * @param array $properties
   *   Properties.
   *
   * @return \Drupal\lark\Entity\LarkSourceInterface[]
   *   Source entities.
   */
  public function loadByProperties(array $properties): array {
    return $this->storage()->loadByProperties($properties);
  }

  /**
   * Create a new source.
   *
   * @param array $values
   *   Values.
   *
   * @return \Drupal\lark\Entity\LarkSourceInterface
   *   Source entity.
   */
  public function create(array $values = []): LarkSourceInterface {
    return $this->storage()->create($values);
  }

  /**
   * Get source options.
   *
   * @return array
   *   Source options.
   */
  public function sourcesAsOptions(): array {
    $sources = $this->storage()->loadByProperties([
      'status' => 1,
    ]);
    $options = [];
    foreach ($sources as $source) {
      $options[$source->id()] = $source->label();
    }
    return $options;
  }



  /**
   * Get the source plugin for the given exportable.
   *
   * @param \Drupal\lark\Model\ExportableInterface $exportable
   *   The exportable entity.
   *
   * @return \Drupal\lark\Entity\LarkSourceInterface|null
   *   The source plugin or NULL if not found.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function resolveSource(ExportableInterface $exportable): ?LarkSourceInterface {
    $entity = $exportable->entity();
    $sources = $this->loadByProperties([
      'status' => 1,
    ]);

    foreach ($sources as $source) {
      if ($source->exportExistsInSource($entity->getEntityTypeId(), $entity->bundle(), $entity->uuid())) {
        return $source;
      }
    }

    return NULL;
  }

  /**
   * Source for temporary storage.
   *
   * @return \Drupal\lark\Entity\LarkSourceInterface
   *   Source configured to the filesystem's tmp storage.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getTmpSource(): LarkSourceInterface {
    $source = $this->create([
      'id' => '_tmp',
      'label' => 'Temporary Storage',
      'directory' => $this->fileSystem->getTempDirectory(),
      'status' => 0,
    ]);

    return $source;
  }

  /**
   * Get the default source.
   *
   * @return \Drupal\lark\Entity\LarkSourceInterface|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getDefaultSource(): LarkSourceInterface {
    $source = $this->load($this->larkSettings->defaultSource());

    if (!$source) {
      $source = $this->getTmpSource();
    }

    return $source;
  }

  /**
   * Build operation links for given exportable.
   *
   * @param \Drupal\lark\Entity\LarkSourceInterface $source
   *   Source plugin.
   * @param \Drupal\lark\Model\ExportableInterface $exportable
   *   Exportable entity.
   *
   * @return array
   *   Render array.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function getExportableOperations(LarkSourceInterface $source, ExportableInterface $exportable) {
    // Determine export status and possible operations.
    $operations = [];

    if ($exportable->entity()->isNew()) {
      $operations['import'] = [
        'title' => $this->t('Import'),
        'url' => Url::fromRoute('lark.action_import_source_entity', [
          'lark_source' => $source->id(),
          'uuid' => $exportable->entity()->uuid(),
        ]),
      ];
    }
    if (!$exportable->entity()->isNew()) {
      $entity_type = $this->entityTypeManager->getDefinition($exportable->entity()->getEntityTypeId());

      if ($entity_type->hasLinkTemplate('canonical')) {
        $operations['view'] = [
          'title' => $this->t('View'),
          'url' => $exportable->entity()->toUrl()->setRouteParameter('lark_source', $source->id()),
        ];
      }
      if ($entity_type->hasLinkTemplate('edit-form')) {
        $operations['edit_form'] = [
          'title' => $this->t('Edit'),
          'url' => $exportable->entity()->toUrl('edit-form'),
        ];
      }
      if ($entity_type->hasLinkTemplate('lark-load')) {
        $operations['lark'] = [
          'title' => $this->t('Export'),
          'url' => $exportable->entity()->toUrl('lark-load'),
        ];
      }
      if ($entity_type->hasLinkTemplate('lark-import')) {
        $operations['lark_import'] = [
          'title' => $this->t('Import'),
          'url' => $exportable->entity()->toUrl('lark-import'),
        ];
      }
      if ($entity_type->hasLinkTemplate('lark-download')) {
        $operations['lark_download'] = [
          'title' => $this->t('Download'),
          'url' => $exportable->entity()->toUrl('lark-download'),
        ];
      }
      if ($entity_type->hasLinkTemplate('lark-diff')) {
        $operations['lark_diff'] = [
          'title' => $this->t('Diff'),
          'url' => $exportable->entity()->toUrl('lark-diff'),
        ];
      }
      $operations['lark_prune'] = [
        'title' => $this->t('Prune'),
        'url' => Url::fromRoute('entity.lark_source.prune_confirm_form', [
          'lark_source' => $source->id(),
          'prune_target' => $exportable->entity()->uuid(),
        ]),
      ];
    }

    return $operations;
  }

}
