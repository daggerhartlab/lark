<?php

namespace Drupal\lark\Service;


use Drupal\lark\Plugin\Lark\SourceInterface;

/**
 * Import entities and their dependencies.
 */
interface ImporterInterface {

  /**
   * Import a single entity by its uuid.
   *
   * @param string $source_plugin_id
   *   The source plugin id.
   * @param string $uuid
   *   The UUID of the entity to import.
   * @param bool $show_messages
   *   Whether to show messages.
   *
   * @return void
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function importSingleEntityFromSource(string $source_plugin_id, string $uuid, bool $show_messages = TRUE): void;

  /**
   * Import all lark exported content.
   *
   * @param bool $show_messages
   *   Whether to show messages.
   *
   * @return void
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function importFromAllSources(bool $show_messages = TRUE): void;

  /**
   * Import all content from a single source.
   *
   * @param string $source_plugin_id
   *   The source plugin id.
   * @param bool $show_messages
   *   Whether to show messages.
   *
   * @return void
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function importFromSingleSource(string $source_plugin_id, bool $show_messages = TRUE): void;

  /**
   * Discover this source's exportables and dependencies.
   *
   * @param \Drupal\lark\Plugin\Lark\SourceInterface $source
   *   The source plugin.
   *
   * @return array
   *   Array of exports and dependencies found in this source directory.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function discoverSourceExports(SourceInterface $source): array;

  /**
   * Discover a single exportable and its dependencies in this source.
   *
   * @param \Drupal\lark\Plugin\Lark\SourceInterface $source
   *   The source plugin.
   * @param string $uuid
   *   The UUID of the exportable to discover.
   *
   * @return array
   *   Array of export and dependencies found in this source directory.
   */
  public function discoverSourceExport(SourceInterface $source, string $uuid): array;

}
