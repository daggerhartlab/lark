<?php

declare(strict_types=1);

namespace Drupal\lark\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\lark\Routing\EntityTypeInfo;

/**
 * Entity hook implementations for Lark.
 */
class LarkEntityHooks {

  public function __construct(
    protected EntityTypeInfo $entityTypeInfo,
  ) {}

  /**
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types): void {
    $this->entityTypeInfo->entityTypeAlter($entity_types);
  }

  /**
   * Implements hook_entity_operation().
   */
  #[Hook('entity_operation')]
  public function entityOperation(EntityInterface $entity, CacheableMetadata $cacheability): array {
    // The Lark operation depends on the user's permissions.
    $cacheability->addCacheContexts(['user.permissions']);
    return $this->entityTypeInfo->entityOperation($entity);
  }

}
