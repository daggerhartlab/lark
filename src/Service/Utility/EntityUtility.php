<?php

namespace Drupal\lark\Service\Utility;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\lark\Routing\EntityTypeInfo;
use Drupal\lark\Service\FieldTypeHandlerManagerInterface;

/**
 * Utility for entity operations.
 */
class EntityUtility {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FieldTypeHandlerManagerInterface $fieldTypeHandlerManager,
  ) {}

  /**
   * Get dependencies as array of uuid -> entity type id pairs for the entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity.
   *
   * @return array
   *   Uuid and entity_type_id pairs.
   */
  public function getEntityExportDependencies(ContentEntityInterface $entity): array {
    $dependencies = [];
    $dependencies = $this->getEntityUuidEntityTypePairs($entity, $dependencies);
    // We only want dependencies. Remove this entity if found.
    if (array_key_last($dependencies) === $entity->uuid()) {
      unset($dependencies[$entity->uuid()]);
    }

    return $dependencies;
  }

  /**
   * Recursively find uuid -> entity type id pairs for the given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity.
   * @param array $found
   *   Array that is built on during the recursive process.
   *
   * @return array
   *   UUID => Entity type id pairs, including the given entity.
   */
  public function getEntityUuidEntityTypePairs(ContentEntityInterface $entity, array &$found): array {
    if (!$entity->getEntityType()->get(EntityTypeInfo::IS_EXPORTABLE)) {
      return [];
    }

    if (array_key_exists($entity->uuid(), $found) && !is_null($found[$entity->uuid()])) {
      return $found;
    }

    $entity->getFieldDefinitions();

    foreach ($entity->getFields() as $field) {
      if ($field instanceof EntityReferenceFieldItemListInterface) {
        foreach ($field->referencedEntities() as $referenced_entity) {
          if (!$referenced_entity->getEntityType()->get(EntityTypeInfo::IS_EXPORTABLE)) {
            continue;
          }

          // If the referenced entity is already processing, do nothing.
          if (array_key_exists($referenced_entity->uuid(), $found)) {
            continue;
          }

          $found[$referenced_entity->uuid()] = NULL;
          $found += $this->getEntityUuidEntityTypePairs($referenced_entity, $found);
        }
      }
    }

    $found[$entity->uuid()] = $entity->getEntityTypeId();
    return $found;
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
  public function getEntityArray(ContentEntityInterface $entity): array {
    $array = $entity->toArray();

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
        $array[$field_name] = $this->fieldTypeHandlerManager->alterExportValues($default_values, $entity, $entity->get($field_name));
      }
    }

    return $array;
  }

}
