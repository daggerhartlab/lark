<?php

namespace Drupal\lark\Service;


use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * LarkFieldTypeHandler plugin manager.
 */
interface FieldTypeHandlerManagerInterface {

  /**
   * {@inheritdoc}
   */
  public function getDefinitions();

  /**
   * Get plugin instances.
   *
   * @return \Drupal\lark\Plugin\Lark\FieldTypeHandlerInterface[]
   *   Array of plugin instances.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getInstances(): array;

  /**
   * Get plugin instances by field type.
   *
   * @param string $field_type
   *   The field type to get instances for.
   *
   * @return \Drupal\lark\Plugin\Lark\FieldTypeHandlerInterface[]
   *   Array of plugin instances.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getInstancesByFieldType(string $field_type): array;

  /**
   * Perform alterExportValue on appropriate field type handlers.
   *
   * @param array $values
   *   The current field values as an array.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being exported.
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field being exported.
   *
   * @return array
   *   The altered field values for export.
   */
  public function alterExportValues(array $values, ContentEntityInterface $entity, FieldItemListInterface $field): array;

  /**
   * Perform alterImportValue on appropriate field type handlers.
   *
   * @param array $values
   *   The current field values as an array.
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field to set import values for.
   *
   * @return array
   *   The altered field values for import.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function alterImportValues(array $values, FieldItemListInterface $field): array;

}
