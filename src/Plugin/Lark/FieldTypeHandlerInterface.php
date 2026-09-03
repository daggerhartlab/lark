<?php

declare(strict_types=1);

namespace Drupal\lark\Plugin\Lark;

use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Interface for lark_field_type_handler plugins.
 */
interface FieldTypeHandlerInterface extends PluginInspectionInterface, DerivativeInspectionInterface{

  /**
   * Returns the translated plugin label.
   */
  public function label(): string;

  /**
   * Returns the translated plugin description.
   *
   * @return string
   *   The description of the plugin.
   */
  public function description(): string;

  /**
   * Returns the field types handled by the plugin.
   *
   * @return array
   *   An array of field types handled by the plugin.
   */
  public function fieldTypes(): array;

  /**
   * Returns the weight of the plugin.
   *
   * @return int
   *   The weight of the plugin.
   */
  public function weight(): int;

  /**
   * Returns the export value of the field.
   *
   * @param array $values
   *   The current field values as an array.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to export.
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field to export.
   *
   * @return array
   *   The exported field value as an array.
   */
  public function alterExportValue(array $values, ContentEntityInterface $entity, FieldItemListInterface $field): array;

  /**
   * Returns the import value of the field.
   *
   * @param array $values
   *   The values to alter that will later be set as the field's value.
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field that will have its values set.
   *
   * @return array
   *   The value to be set on the field.
   */
  public function alterImportValue(array $values, FieldItemListInterface $field): array;

  /**
   * Hard dependencies this field contributes.
   *
   * A hard dependency travels with the entity: it is traversed during export
   * discovery, written to '_meta.depends', and imported before the entity that
   * needs it. Return targets the entity is incomplete or broken without.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field to inspect.
   *
   * @return array
   *   UUID : entity_type_id pairs.
   */
  public function getFieldDependencies(FieldItemListInterface $field): array;

  /**
   * Soft references this field contributes.
   *
   * A soft reference is recorded to '_meta.references' so a portable value can
   * be resolved back to a local ID on import, but it is never traversed and
   * never pulls another entity into an export set. Return targets the entity
   * merely points at - a link, for instance - and remains valid without.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field to inspect.
   *
   * @return array
   *   UUID : entity_type_id pairs.
   */
  public function getFieldReferences(FieldItemListInterface $field): array;

}
