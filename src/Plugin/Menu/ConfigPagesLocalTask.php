<?php

declare(strict_types=1);

namespace Drupal\lark\Plugin\Menu;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\LocalTaskDefault;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lark local task for config_pages bundle routes.
 *
 * The config_pages module edits its entities on per-bundle routes
 * (config_pages.{bundle}) that have no {config_pages} parameter, so the
 * entity must be resolved from the bundle. This deliberately references no
 * config_pages classes, so Lark does not depend on that module.
 *
 * @see \Drupal\lark\Routing\LocalTasks::getConfigPagesDerivatives()
 */
class ConfigPagesLocalTask extends LocalTaskDefault implements ContainerFactoryPluginInterface {

  /**
   * Constructs a ConfigPagesLocalTask object.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(EntityTypeManagerInterface::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteParameters(RouteMatchInterface $route_match) {
    // ConfigPagesStorage::load() accepts a bundle name and returns the entity
    // for the current context.
    $entity = $this->entityTypeManager
      ->getStorage('config_pages')
      ->load($this->pluginDefinition['config_pages_bundle']);

    // When the config page has not been saved yet there is no entity. An id
    // that does not exist fails param conversion, which the access manager
    // treats as forbidden, so the tab is hidden.
    return ['config_pages' => $entity ? $entity->id() : 0];
  }

}
