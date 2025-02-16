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

      $has_edit_path = $entity_type->hasLinkTemplate('edit-form');
      $has_canonical_path = $entity_type->hasLinkTemplate('canonical');

      if ($has_edit_path || $has_canonical_path) {
        $this->derivatives["$entity_type_id.lark_load"] = [
          'route_name' => "entity.$entity_type_id.lark_load",
          'title' => $this->t('Lark'),
          'base_route' => "entity.$entity_type_id." . ($has_canonical_path ? "canonical" : "edit_form"),
          'weight' => 100,
        ];

        $this->derivatives["$entity_type_id.lark_export"] = [
          'route_name' => "entity.$entity_type_id.lark_export",
          'title' => $this->t('Export'),
          'parent_id' => "lark.entities:$entity_type_id.lark_load",
        ];

        $this->derivatives["$entity_type_id.lark_import"] = [
          'route_name' => "entity.$entity_type_id.lark_import",
          'title' => $this->t('Import'),
          'parent_id' => "lark.entities:$entity_type_id.lark_load",
        ];

        $this->derivatives["$entity_type_id.lark_diff"] = [
          'route_name' => "entity.$entity_type_id.lark_diff",
          'title' => $this->t('Diff'),
          'parent_id' => "lark.entities:$entity_type_id.lark_load",
        ];

        $this->derivatives["$entity_type_id.lark_download"] = [
          'route_name' => "entity.$entity_type_id.lark_download",
          'title' => $this->t('Download'),
          'parent_id' => "lark.entities:$entity_type_id.lark_load",
        ];
      }
    }

    foreach ($this->derivatives as &$entry) {
      $entry += $base_plugin_definition;
    }

    return $this->derivatives;
  }

}
