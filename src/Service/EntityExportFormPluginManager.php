<?php

declare(strict_types=1);

namespace Drupal\lark\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\lark\Attribute\LarkEntityExportForm;
use Drupal\lark\Plugin\Lark\EntityExportFormInterface;

/**
 * LarkEntityExportForm plugin manager.
 */
final class EntityExportFormPluginManager extends DefaultPluginManager {

  /**
   * Constructs the object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/Lark/EntityExportForm', $namespaces, $module_handler, EntityExportFormInterface::class, LarkEntityExportForm::class);
    $this->alterInfo('lark_entity_export_form_info');
    $this->setCacheBackend($cache_backend, 'lark_entity_export_form_plugins');
  }

  /**
   * Get all plugin instances.
   *
   * @return EntityExportFormInterface[]
   */
  public function getInstances(array $configuration = []): array {
    $instances = [];
    foreach ($this->getDefinitions() as $key => $definition) {
      $instances[$key] = $this->createInstance($definition['id'], $configuration);
    }

    return $instances;
  }

}
