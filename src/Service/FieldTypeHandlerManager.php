<?php

declare(strict_types=1);

namespace Drupal\lark\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\lark\Attribute\LarkFieldTypeHandler;
use Drupal\lark\Plugin\Lark\FieldTypeHandlerInterface;

/**
 * LarkFieldHandler plugin manager.
 */
final class FieldTypeHandlerManager extends DefaultPluginManager implements FieldTypeHandlerManagerInterface {

  /**
   * Constructs the object.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
    protected FieldTypePluginManagerInterface $fieldTypePluginManager,
  ) {
    parent::__construct('Plugin/Lark/FieldTypeHandler', $namespaces, $module_handler, FieldTypeHandlerInterface::class, LarkFieldTypeHandler::class);
    $this->alterInfo('lark_field_type_handler_info');
    $this->setCacheBackend($cache_backend, 'lark_field_type_handler_plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions(): array {
    $definitions = parent::getDefinitions();

    // Sort by weight.
    uasort($definitions, function($a, $b) {
      return $a['weight'] ?? 0 <=> $b['weight'] ?? 0;
    });

    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getInstances(): array {
    $instances = [];
    $enabled_field_types = array_keys($this->fieldTypePluginManager->getDefinitions());
    foreach ($this->getDefinitions() as $key => $definition) {
      // Wildcard handlers are always instantiated.
      if (in_array('*', $definition['fieldTypes'])) {
        $instances[$key] = $this->createInstance($definition['id']);
        continue;
      }

      // Otherwise, only instantiate if at least one field types the handler
      // supports are enabled field types in Drupal.
      // @todo - Should this check to see if _all_ field types are enabled?
      if (array_intersect($definition['fieldTypes'], $enabled_field_types)) {
        $instances[$key] = $this->createInstance($definition['id']);
      }
    }

    return $instances;
  }

  /**
   * {@inheritdoc}
   */
  public function getInstancesByFieldType(string $field_type): array {
    return array_filter($this->getInstances(), function (FieldTypeHandlerInterface $plugin) use ($field_type) {
      // Wildcard for all field types.
      if (in_array('*', $plugin->fieldTypes())) {
        return TRUE;
      }

      return in_array($field_type, $plugin->fieldTypes());
    });
  }

  /**
   * {@inheritdoc}
   */
  public function alterExportValues(array $values, ContentEntityInterface $entity, FieldItemListInterface $field): array {
    $instances = $this->getInstancesByFieldType($field->getFieldDefinition()->getType());
    $processed_field_values = $values;

    foreach ($instances as $instance) {
      $processed_field_values = $instance->alterExportValue($processed_field_values, $entity, $field);
    }

    return $processed_field_values;
  }

  /**
   * {@inheritdoc}
   */
  public function alterImportValues(array $values, FieldItemListInterface $field): array {
    $instances = $this->getInstancesByFieldType($field->getFieldDefinition()->getType());
    $processed_field_values = $values;

    foreach ($instances as $instance) {
      $processed_field_values = $instance->alterImportValue($processed_field_values, $field);
    }

    return $processed_field_values;
  }

}
