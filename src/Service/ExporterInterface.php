<?php

namespace Drupal\lark\Service;

/**
 * Export entities and their dependencies.
 */
interface ExporterInterface {

  /**
   * Export a single entity and its dependencies.
   *
   * @param string $source_plugin_id
   *   Source plugin id.
   * @param string $entity_type_id
   *   Entity type id.
   * @param int $entity_id
   *   Entity id.
   * @param bool $show_messages
   *   Whether to show messages.
   * @param array $exports_meta_options_overrides
   *   An array of values for the export _meta array, key by entity uuuid.
   *
   * @return void
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\lark\Exception\LarkEntityNotFoundException
   */
  public function exportEntity(string $source_plugin_id, string $entity_type_id, int $entity_id, bool $show_messages = TRUE, array $exports_meta_options_overrides = []): void;

}
