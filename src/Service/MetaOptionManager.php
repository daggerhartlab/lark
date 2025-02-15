<?php

declare(strict_types=1);

namespace Drupal\lark\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\lark\Attribute\LarkMetaOption;
use Drupal\lark\Plugin\Lark\MetaOptionInterface;

/**
 * LarkMetaOption plugin manager.
 */
final class MetaOptionManager extends DefaultPluginManager {

  /**
   * Constructs the object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/Lark/MetaOption', $namespaces, $module_handler, MetaOptionInterface::class, LarkMetaOption::class);
    $this->alterInfo('lark_entity_export_form_info');
    $this->setCacheBackend($cache_backend, 'lark_entity_export_form_plugins');
  }

  /**
   * Get all plugin instances.
   *
   * @return MetaOptionInterface[]
   */
  public function getInstances(array $configuration = []): array {
    $instances = [];
    foreach ($this->getDefinitions() as $key => $definition) {
      $instances[$key] = $this->createInstance($definition['id'], $configuration);
    }

    return $instances;
  }

}
