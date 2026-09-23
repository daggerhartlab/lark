<?php

namespace Drupal\lark\Routing;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\lark\Plugin\Menu\ConfigPagesLocalTask;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides local task definitions for all entity bundles.
 */
class LocalTasks extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * Creates an LarkLocalTask object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity manager.
   */
  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): static {
    return new static(
      $container->get(EntityTypeManagerInterface::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = [];

    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if (!$entity_type->get(EntityTypeInfo::IS_EXPORTABLE)) {
        continue;
      }

      // Delete form seems to be the most common, so default to that.
      $base_route_form = 'delete_form';
      if ($entity_type->hasLinkTemplate('edit-form')) {
        $base_route_form = 'edit_form';
      }
      if ($entity_type->hasLinkTemplate('canonical')) {
        $base_route_form = 'canonical';
      }

      $template_instances = RouteTemplates::getRouteTemplates($entity_type_id);
      $parent = array_shift($template_instances);

      $this->derivatives["$entity_type_id.{$parent['name']}"] = [
        'route_name' => $parent['route']['name'],
        'title' => $this->t('@lark_link_label', [
          '@lark_link_label' => $parent['link']['label']
        ]),
        'base_route' => "entity.$entity_type_id." . $base_route_form,
        'weight' => 100,
      ];

      foreach ($template_instances as $instance) {
        $this->derivatives["$entity_type_id.{$instance['name']}"] = [
          'route_name' => $instance['route']['name'],
          'title' => $this->t('@lark_link_label', [
            '@lark_link_label' => $instance['link']['label']
          ]),
          'parent_id' => "lark.entities:$entity_type_id.{$parent['name']}",
        ];
      }
    }

    $this->derivatives += $this->getConfigPagesDerivatives();

    foreach ($this->derivatives as &$entry) {
      $entry += $base_plugin_definition;
    }

    return $this->derivatives;
  }

  /**
   * Gets local tasks for config_pages bundle routes, if config_pages exists.
   *
   * The config_pages module edits its entities on per-bundle routes
   * (config_pages.{bundle}) instead of the entity's canonical route, so the
   * generic tabs above never appear where config pages are actually edited.
   * Everything here is keyed off string ids, so nothing happens and nothing
   * breaks when config_pages is not installed.
   *
   * @return array[]
   *   Local task derivative definitions.
   *
   * @see \Drupal\config_pages\Routing\ConfigPagesRoutes::routes()
   */
  protected function getConfigPagesDerivatives(): array {
    if (
      !$this->entityTypeManager->hasDefinition('config_pages_type') ||
      !$this->entityTypeManager->getDefinition('config_pages', FALSE)?->get(EntityTypeInfo::IS_EXPORTABLE)
    ) {
      return [];
    }

    $template_instances = RouteTemplates::getRouteTemplates('config_pages');
    $parent = array_shift($template_instances);

    $derivatives = [];
    foreach ($this->entityTypeManager->getStorage('config_pages_type')->loadMultiple() as $bundle => $type) {
      $base_route = "config_pages.$bundle";

      // Core hides a tab row containing only one tab, so the config page edit
      // form needs a tab of its own for the Lark tab to be shown beside it.
      $derivatives["config_pages.$bundle.edit"] = [
        'route_name' => $base_route,
        'title' => $this->t('Edit'),
        'base_route' => $base_route,
        'weight' => -10,
      ];
      $derivatives["config_pages.$bundle.{$parent['name']}"] = [
        'class' => ConfigPagesLocalTask::class,
        'route_name' => $parent['route']['name'],
        'title' => $this->t('@lark_link_label', [
          '@lark_link_label' => $parent['link']['label'],
        ]),
        'base_route' => $base_route,
        'weight' => 100,
        'config_pages_bundle' => $bundle,
        // Show the tab as soon as the config page is first saved.
        'cache_tags' => ['config_pages_list'],
      ];
    }

    return $derivatives;
  }

}
