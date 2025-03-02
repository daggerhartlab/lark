<?php

namespace Drupal\lark\Service;

use Drupal\lark\Entity\LarkSourceInterface;
use Drupal\lark\Model\ExportCollection;

/**
 * Import entities and their dependencies.
 */
interface ImporterInterface {

  /**
   * Import a single entity by its uuid.
   *
   * @param string $source_id
   *   The Source id.
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
  public function importSourceExport(string $source_id, string $uuid, bool $show_messages = TRUE): void;

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
  public function importSourcesAll(bool $show_messages = TRUE): void;

  /**
   * Import all content from a single source.
   *
   * @param string $source_id
   *   The Source id.
   * @param bool $show_messages
   *   Whether to show messages.
   *
   * @return void
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function importSource(string $source_id, bool $show_messages = TRUE): void;

  /**
   * Discover this source's exportables and dependencies.
   *
   * @param \Drupal\lark\Entity\LarkSourceInterface $source
   *   The source plugin.
   *
   * @return \Drupal\lark\Model\ExportCollection
   *   Array of exports and dependencies found in this source directory.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function discoverSourceExports(LarkSourceInterface $source): ExportCollection;

  /**
   * Discover a single exportable and its dependencies in this source.
   *
   * @param \Drupal\lark\Entity\LarkSourceInterface $source
   *   The source plugin.
   * @param string $uuid
   *   The UUID of the exportable to discover.
   *
   * @return \Drupal\lark\Model\ExportCollection
   *   Array of export and dependencies found in this source directory.
   */
  public function discoverSourceExport(LarkSourceInterface $source, string $uuid): ExportCollection;

}
