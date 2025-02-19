<?php

namespace Drupal\lark\Routing;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
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
      if (!$entity_type->get('_lark_exportable')) {
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

    foreach ($this->derivatives as &$entry) {
      $entry += $base_plugin_definition;
    }

    return $this->derivatives;
  }

}
