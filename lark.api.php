<?php

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Determine if the entity should be exported.
 *
 * @param \Drupal\Core\Entity\ContentEntityInterface $entity
 *   The entity to check.
 *
 * @return bool
 *   TRUE if the entity should be exported, FALSE otherwise.
 */
function hook_lark_should_export_entity(ContentEntityInterface $entity) {
  // If the entity should be exported, return TRUE.
  if ($entity->uuid() === '12345678-1234-1234-1234-1234567890ab') {
    return FALSE;
  }

  return TRUE;
}
