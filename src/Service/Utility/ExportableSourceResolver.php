<?php

namespace Drupal\lark\Service\Utility;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\lark\Entity\LarkSourceInterface;
use Drupal\lark\Model\ExportableInterface;

class ExportableSourceResolver {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
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

}
