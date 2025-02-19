<?php

namespace Drupal\lark\Service\Utility;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\lark\Entity\LarkSourceInterface;
use Drupal\lark\Model\ExportableInterface;
use Drupal\lark\Model\LarkSettings;

class SourceResolver {

  public function __construct(
    protected LarkSettings $larkSettings,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FileSystemInterface $fileSystem,
  ) {}

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
    /** @var \Drupal\lark\Entity\LarkSourceInterface[] $sources */
    $sources = $this->entityTypeManager->getStorage('lark_source')->loadByProperties([
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
    /** @var \Drupal\lark\Entity\LarkSourceInterface $source */
    $source = $this->entityTypeManager->getStorage('lark_source')->create([
      'id' => 'tmp',
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
  public function defaultSource(): LarkSourceInterface {
    /** @var \Drupal\lark\Entity\LarkSourceInterface $source */
    $source = $this->entityTypeManager->getStorage('lark_source')->load($this->larkSettings->defaultSource());

    if (!$source) {
      $source = $this->entityTypeManager->getStorage('lark_source')->create([
        'id' => '_default_source_missing',
        'label' => 'Default source not set',
        'directory' => $this->fileSystem->getTempDirectory(),
        'status' => 0,
      ]);
    }

    return $source;
  }

}
