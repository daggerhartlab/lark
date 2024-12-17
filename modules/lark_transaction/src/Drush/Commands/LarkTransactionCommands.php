<?php

namespace Drupal\lark_transaction\Drush\Commands;

use Drupal\lark_transaction\Service\LarkTransactionPluginManager;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lark transaction commands.
 */
class LarkTransactionCommands extends DrushCommands {

  /**
   * Constructs a LarkCommands object.
   *
   * @param \Drupal\lark_transaction\Service\LarkTransactionPluginManager $larkTransactionPluginManager
   *   The Lark transaction plugin manager service.
   */
  public function __construct(
    protected LarkTransactionPluginManager $larkTransactionPluginManager,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get(LarkTransactionPluginManager::class),
    );
  }

  /**
   * Executes the given plugin id.
   *
   * @param string $plugin_id
   *   The plugin ID to execute.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  #[CLI\Command(name: 'lark:execute-transaction', aliases: ['lex'])]
  #[CLI\Argument(name: 'plugin_id', description: 'The plugin ID to execute.')]
  #[CLI\Usage(name: 'lark:execute-transaction examples', description: 'Executes the given plugin id.')]
  public function executeTransactionPlugin(string $plugin_id): void {
    $plugin = $this->larkTransactionPluginManager->createInstance($plugin_id);
    if (!$plugin->enabled()) {
      $this->logger()->error(dt('Lark transaction plugin ' . $plugin->id() . 'is not enabled.'));
      return;
    }

    if ($plugin->executionCompleted()) {
      $this->logger()->error(dt('Lark transaction plugin ' . $plugin->id() . ' has already been executed.'));
      return;
    }

    $this->larkTransactionPluginManager->executePlugin($plugin);
  }

  /**
   * Executes all available transaction plugins.
   */
  #[CLI\Command(name: 'lark:execute-all-transactions', aliases: ['lexall'])]
  #[CLI\Usage(name: 'lark:execute-all-transactions', description: 'Executes all available transaction plugins.')]
  public function executeAllTransactionPlugins(): void {
    $plugins = $this->larkTransactionPluginManager->getInstances();

    foreach ($plugins as $plugin) {
      if (!$plugin->enabled()) {
        $this->logger()->error(dt('Lark transaction plugin ' . $plugin->id() . 'is not enabled.'));
        continue;
      }

      if ($plugin->executionCompleted()) {
        $this->logger()->error(dt('Lark transaction plugin ' . $plugin->id() . 'has already been executed.'));
        continue;
      }

      $this->larkTransactionPluginManager->executePlugin($plugin);

      $this->logger()->success('Lark transaction plugin ' . $plugin->id() . ' executed successfully.');
    }

    $this->logger()->success('All available lark transactions executed successfully.');
  }

}
