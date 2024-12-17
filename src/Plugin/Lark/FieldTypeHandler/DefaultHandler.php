<?php

declare(strict_types=1);

namespace Drupal\lark\Plugin\Lark\FieldTypeHandler;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\lark\Attribute\LarkFieldTypeHandler;
use Drupal\lark\Exception\LarkImportException;
use Drupal\lark\Plugin\Lark\FieldTypeHandlerBase;

/**
 * Plugin implementation of the lark_field_type_handler.
 */
#[LarkFieldTypeHandler(
  id: 'default_field_type_handler',
  label: new TranslatableMarkup('Default Field Type Handler'),
  description: new TranslatableMarkup('Handles most simple fields.'),
  fieldTypes: ['*'],
)]
class DefaultHandler extends FieldTypeHandlerBase {

  /**
   * {@inheritdoc}
   */
  public function alterImportValue(array $values, FieldItemListInterface $field): array {
    $entity = $field->getEntity();
    $field_name = $field->getFieldDefinition()->getName();

    foreach ($values as $delta => $item_value) {
      if (!$field->get($delta)) {
        $field->appendItem();
      }
      /** @var \Drupal\Core\Field\FieldItemInterface $item */
      $item = $field->get($delta);

      $serialized_property_names = $this->getCustomSerializedPropertyNames($item);
      foreach ($item_value as $property_name => $value) {
        // Handle serialized properties.
        if (\in_array($property_name, $serialized_property_names)) {
          if (\is_string($value)) {
            $unserialized_value = \unserialize($value);
            if ($value === FALSE && $value !== 'b:0;') {
              throw new LarkImportException("Received string for serialized property for {$entity->getEntityTypeId()} with uuid {$entity->uuid()}: [$field_name][$delta][$property_name] '{$value}'.");
            }

            $value = $unserialized_value;
          }

          $values[$delta][$property_name] = \serialize($value);
        }
      }
    }

    return $values;
  }

  /**
   * Gets the names of all properties the plugin treats as serialized data.
   *
   * This allows the field storage definition or entity type to provide a
   * setting for serialized properties. This can be used for fields that
   * handle serialized data themselves and do not rely on the serialized schema
   * flag.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $field_item
   *   The field item.
   *
   * @return string[]
   *   The property names for serialized properties.
   *
   * @see \Drupal\serialization\Normalizer\SerializedColumnNormalizerTrait::getCustomSerializedPropertyNames
   */
  protected function getCustomSerializedPropertyNames(FieldItemInterface $field_item): array {
    if ($field_item instanceof PluginInspectionInterface) {
      $definition = $field_item->getPluginDefinition();
      $serialized_fields = $field_item->getEntity()->getEntityType()->get('serialized_field_property_names');
      $field_name = $field_item->getFieldDefinition()->getName();
      if (is_array($serialized_fields) && isset($serialized_fields[$field_name]) && is_array($serialized_fields[$field_name])) {
        return $serialized_fields[$field_name];
      }
      if (isset($definition['serialized_property_names']) && is_array($definition['serialized_property_names'])) {
        return $definition['serialized_property_names'];
      }
    }
    return [];
  }

}
