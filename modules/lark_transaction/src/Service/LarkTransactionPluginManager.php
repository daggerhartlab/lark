<?php

declare(strict_types=1);

namespace Drupal\lark_transaction\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\lark_transaction\Plugin\LarkTransactionInterface;
use Drupal\lark_transaction\Attribute\LarkTransaction;

/**
 * Lark transactions plugin manager.
 */
class LarkTransactionPluginManager extends DefaultPluginManager {

  /**
   * LarkTransactionPluginManager constructor.
   *
   * @param \Traversable $namespaces
   *   The namespaces service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database service.
   * @param \Drupal\Component\Datetime\Time $time
   *   The time service.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
    protected Connection $database,
    protected TimeInterface $time,
  ) {
    parent::__construct(
      'Plugin/LarkTransaction',
      $namespaces,
      $module_handler,
      LarkTransactionInterface::class,
      LarkTransaction::class
    );
    $this->alterInfo('lark_transaction_info');
    $this->setCacheBackend($cache_backend, 'lark_transaction_plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    $definitions = parent::getDefinitions();

    // Sort by weight.
    uasort($definitions, function($a, $b) {
      return $a['weight'] ?? 0 <=> $b['weight'] ?? 0;
    });

    return $definitions;
  }

  /**
   * Get plugin instances.
   *
   * @param bool $include_disabled
   *   Whether to include disabled plugins.
   * @param bool $include_completed
   *   Whether to include completed plugins.
   *
   * @return \Drupal\lark_transaction\Plugin\LarkTransactionInterface[]
   *   Array of plugin instances.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getInstances(bool $include_disabled = FALSE, bool $include_completed = FALSE): array {
    $instances = [];

    foreach ($this->getDefinitions() as $key => $definition) {
      $instances[$key] = $this->createInstance($definition['id']);
    }

    if ($include_disabled && $include_completed) {
      return $instances;
    }

    if (!$include_disabled) {
      $instances = array_filter($instances, function($instance) {
        return $instance->enabled();
      }, ARRAY_FILTER_USE_BOTH);
    }

    if (!$include_completed) {
      $instances = array_filter($instances, function($instance) {
        return $instance->executionCompleted() === FALSE;
      }, ARRAY_FILTER_USE_BOTH);
    }

    return $instances;
  }

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\lark_transaction\Plugin\LarkTransactionInterface
   *   The plugin instance.
   */
  public function createInstance($plugin_id, array $configuration = []) {
    $configuration['history'] = $this->getPluginHistory($plugin_id);

    /** @var \Drupal\lark_transaction\Plugin\LarkTransactionInterface $plugin */
    $plugin = parent::createInstance($plugin_id, $configuration);
    return $plugin;
  }

  /**
   * Execute the given plugin.
   *
   * @param \Drupal\lark_transaction\Plugin\LarkTransactionInterface $plugin
   *   Transaction plugin.
   *
   * @return \Drupal\lark_transaction\Plugin\LarkTransactionInterface
   *   Executed plugin.
   */
  public function executePlugin(LarkTransactionInterface $plugin): LarkTransactionInterface {
    $plugin->preExecute();
    $plugin->execute();
    $plugin->postExecute();

    $this->upsertPluginHistory($plugin);

    return $plugin;
  }

  /**
   * Get the plugin's history from the database.
   *
   * @param string $plugin_id
   *   Plugin id.
   *
   * @return array
   *   Plugin history from database, or default values.
   */
  protected function getPluginHistory(string $plugin_id): array {
    $history = $this->database->select('lark_transaction', 'lt')
      ->fields('lt', ['plugin_id', 'times_executed', 'last_executed'])
      ->condition('plugin_id', $plugin_id)
      ->execute()
      ->fetchAssoc();

    if (empty($history)) {
      $history = [
        'plugin_id' => $plugin_id,
        'times_executed' => 0,
        'last_executed' => 0,
      ];
    }

    return $history;
  }

  /**
   * Update the plugin's history in the database.
   *
   * @param \Drupal\lark_transaction\Plugin\LarkTransactionInterface $plugin
   *   Plugin instance.
   *
   * @return void
   */
  protected function upsertPluginHistory(LarkTransactionInterface $plugin): void {
    $this->database->merge('lark_transaction')
      ->key('plugin_id', $plugin->id())
      ->fields([
        'times_executed' => $plugin->getHistory()['times_executed'] + 1,
        'last_executed' => $this->time->getCurrentTime(),
      ])
      ->execute();
  }

}
