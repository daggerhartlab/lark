<?php

declare(strict_types=1);

namespace Drupal\lark_test\Hook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\State\StateInterface;

/**
 * Hook implementations for Lark tests.
 */
class LarkTestHooks {

  /**
   * State key holding the UUIDs that must not be exported.
   */
  public const string VETOED_UUIDS = 'lark_test.vetoed_uuids';

  public function __construct(
    protected StateInterface $state,
  ) {}

  /**
   * Implements hook_lark_should_export_entity().
   */
  #[Hook('lark_should_export_entity')]
  public function shouldExportEntity(ContentEntityInterface $entity): bool {
    return !in_array($entity->uuid(), $this->state->get(static::VETOED_UUIDS, []), TRUE);
  }

}
