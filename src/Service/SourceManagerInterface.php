<?php

namespace Drupal\lark\Service;


use Drupal\lark\Entity\LarkSourceInterface;

/**
 * Defines a plugin manager to deal with lark_sources.
 *
 * Modules can define lark_sources in a MODULE_NAME.lark_sources.yml file
 * contained in the module's base directory. Each lark_source has the following
 * structure:
 *
 * @code
 *   MACHINE_NAME:
 *     label: STRING
 *     directory: STRING
 * @endcode
 *
 * @see \Drupal\lark\Plugin\Lark\Source\DefaultSource
 * @see \Drupal\lark\Entity\LarkSourceInterface
 */
interface SourceManagerInterface {

  /**
   * Get plugin instances.
   *
   * @return \Drupal\lark\Entity\LarkSourceInterface[]
   *   Array of plugin instances.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getInstances(): array;

  /**
   * Get the default source.
   *
   * @return \Drupal\lark\Entity\LarkSourceInterface
   *   The default source plugin.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\lark\Exception\LarkDefaultSourceNotFound
   */
  public function getDefaultSource(): LarkSourceInterface;

  /**
   * Get the sources as options.
   *
   * @return array
   *   The sources as options.
   */
  public function getOptions(): array;

  /**
   * Get a source plugin instance.
   *
   * @param string $source_plugin_id
   *   The source plugin id.
   *
   * @return \Drupal\lark\Entity\LarkSourceInterface
   *   The plugin instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getSourceInstance(string $source_plugin_id): LarkSourceInterface;

}
