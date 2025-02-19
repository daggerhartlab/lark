<?php

declare(strict_types=1);

namespace Drupal\lark\Plugin\Lark\FieldTypeHandler;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\lark\Attribute\LarkFieldTypeHandler;
use Drupal\lark\Exception\LarkEntityNotFoundException;
use Drupal\lark\Plugin\Lark\FieldTypeHandlerBase;
use Drupal\lark\Routing\EntityTypeInfo;

/**
 * Plugin implementation of the lark_field_type_handler.
 */
#[LarkFieldTypeHandler(
  id: 'entity_reference_uuid_handler',
  label: new TranslatableMarkup('Entity Reference UUID Handler'),
  description: new TranslatableMarkup('Handles entity reference fields by uuid instead of entity id.'),
  fieldTypes: ['entity_reference', 'entity_reference_revisions'],
)]
class EntityReferenceUuidHandler extends FieldTypeHandlerBase {

  /**
   * {@inheritdoc}
   */
  public function alterExportValue(array $values, ContentEntityInterface $entity, FieldItemListInterface $field): array {
    foreach ($values as $delta => $item_value) {
      $item = $field->get($delta);
      // Record entity reference target UUID and entity type.
      if ($item instanceof EntityReferenceItem && $item->get('entity')?->getTarget()) {
        /** @var \Drupal\Core\Entity\EntityInterface $referenced_entity */
        $referenced_entity = $item->get('entity')->getTarget()->getValue();

        if (!$referenced_entity->getEntityType()->get(EntityTypeInfo::IS_EXPORTABLE)) {
          continue;
        }

        // Override values with uuid that we'll use on import.
        $values[$delta] = [
          'target_uuid' => $referenced_entity->uuid(),
          'target_entity_type' => $referenced_entity->getEntityTypeId(),
          // Add additional information for entity reference revisions.
          'target_bundle' => $referenced_entity->bundle(),
          'original_values' => [],
        ];

        foreach ($field->getFieldDefinition()->getFieldStorageDefinition()->getPropertyNames() as $property) {
          $values[$delta]['original_values'][$property] = $item->get($property)->getValue();
        }

        // Unset the entity property to avoid serialization issues.
        if (isset($values[$delta]['original_values']['entity'])) {
          unset($values[$delta]['original_values']['entity']);
        }
      }
    }

    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function alterImportValue(array $values, FieldItemListInterface $field): array {
    $storage_definition = $field->getFieldDefinition()->getFieldStorageDefinition();
    foreach ($values as $delta => $item_value) {
      // Handle reference fields for uuids.
      if (isset($item_value['target_uuid']) && isset($item_value['target_entity_type'])) {
        $target_entity = $this->entityRepository->loadEntityByUuid($item_value['target_entity_type'], $item_value['target_uuid']);
        if (!$target_entity) {
          throw new LarkEntityNotFoundException("Could not load entity with UUID {$item_value['target_uuid']} for field {$field->getName()} in entity {$field->getEntity()->getEntityTypeId()} : {$field->getEntity()->uuid()}.");
        }

        // Set values to those expected by the field.
        $values[$delta] = [];
        if (!empty($item_value['original_values'])) {
          $properties = array_diff($storage_definition->getPropertyNames(), [$storage_definition->getMainPropertyName()]);

          foreach ($properties as $property) {
            // Skip the entity property.
            if ($property === 'entity') {
              continue;
            }
            $values[$delta][$property] = $item_value['original_values'][$property];
          }
        }

        // Record the main property value last to ensure it is set.
        $values[$delta][$storage_definition->getMainPropertyName()] = $target_entity->id();

        // Get optional target revision id.
        if (in_array('target_revision_id', $storage_definition->getPropertyNames(), TRUE)) {
          $values[$delta]['target_revision_id'] = $target_entity->getRevisionId();
        }
      }
    }

    return $values;
  }

}
