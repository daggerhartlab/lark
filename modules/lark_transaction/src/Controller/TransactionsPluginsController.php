<?php

declare(strict_types=1);

namespace Drupal\lark_transaction\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\lark_transaction\Service\LarkTransactionPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for Transaction execution routes.
 *
 * @deprecated
 *   Remove in v2.
 */
class TransactionsPluginsController extends ControllerBase {

  const ROUTE_NAME_LIST_PLUGINS = 'lark.transactions_plugins_list';
  const ROUTE_NAME_EXECUTE_PLUGIN = 'lark.transactions_plugin_execute';

  /**
   * The controller constructor.
   */
  public function __construct(
    protected LarkTransactionPluginManager $larkTransactionPluginManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get(LarkTransactionPluginManager::class),
    );
  }

  /**
   * List all plugins.
   *
   * @return array
   *   The render array.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function listPlugins(): array {
    $header = [
      'name' => $this->t('Name'),
      'description' => $this->t('Description'),
      'weight' => $this->t('Weight'),
      'enabled' => $this->t('Enabled'),
      'repeatable' => $this->t('Repeatable'),
      'last_executed' => $this->t('Last executed'),
      'times_executed' => $this->t('Times executed'),
      'operations' => $this->t('Operations'),
    ];

    $available_rows = [];
    $disabled_rows = [];
    $completed_rows = [];
    foreach ($this->larkTransactionPluginManager->getInstances(TRUE, TRUE) as $instance) {
      $execute_link = Link::createFromRoute($this->t('Execute'), static::ROUTE_NAME_EXECUTE_PLUGIN, [
        'plugin_id' => $instance->id(),
      ]);
      $row = [
        'name' => $instance->label(),
        'description' => $instance->description(),
        'weight' => $instance->weight(),
        'enabled' => $instance->enabled() ? $this->t('Yes') : $this->t('No'),
        'repeatable' => $instance->repeatable() ? $this->t('Yes') : $this->t('No'),
        'last_executed' => $instance->getHistoryTimesExecuted() ? $instance->getHistoryLastExecutedFormatted() : '-',
        'times_executed' => $instance->getHistoryTimesExecuted(),
        'operations' => Markup::create($execute_link->toString()),
      ];

      if ($instance->executionCompleted()) {
        $completed_rows[] = $row;
        continue;
      }

      if (!$instance->enabled()) {
        $disabled_rows[] = $row;
        continue;
      }

      $available_rows[] = $row;
    }

    $build = [];
    $build['available'] = [
      '#type' => 'details',
      '#title' => $this->t('Available transactions'),
      '#open' => TRUE,
      'table' => [
        '#type' => 'table',
        '#empty' => 'No available transactions.',
        '#header' => $header,
        '#rows' => $available_rows,
      ],
    ];
    $build['completed'] = [
      '#type' => 'details',
      '#title' => $this->t('Completed transactions'),
      '#open' => FALSE,
      'table' => [
        '#type' => 'table',
        '#empty' => 'No completed transactions.',
        '#header' => $header,
        '#rows' => $completed_rows,
      ],
    ];
    $build['disabled'] = [
      '#type' => 'details',
      '#title' => $this->t('Disabled transactions'),
      '#open' => FALSE,
      'table' => [
        '#type' => 'table',
        '#empty' => 'No disabled transactions.',
        '#header' => $header,
        '#rows' => $disabled_rows,
      ],
    ];

    return $build;
  }

  /**
   * Execute the plugin and redirect back to the list.
   *
   * @param string $plugin_id
   *   The plugin ID to execute.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirect response.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function executePlugin(string $plugin_id): RedirectResponse {
    $plugin = $this->larkTransactionPluginManager->createInstance($plugin_id);

    if (!$plugin->enabled()) {
      $this->messenger()->addError($this->t('The plugin %plugin is not enabled.', ['%plugin' => $plugin->label()]));
    }
    else {
      $this->larkTransactionPluginManager->executePlugin($plugin);
    }

    return new RedirectResponse(Url::fromRoute(static::ROUTE_NAME_LIST_PLUGINS)->toString());
  }

}
