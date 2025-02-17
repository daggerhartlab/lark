<?php

declare(strict_types=1);

namespace Drupal\lark\Plugin\Lark;

use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Interface for lark_source plugins.
 *
 * @deprecated
 *   Replaced by lark_source config entity.
 */
interface LarkSourceInterface {

  /**
   * Returns the plugin ID.
   *
   * @return string
   */
  public function id();

  /**
   * Returns the translated plugin label.
   *
   * @return string
   */
  public function label();

  /**
   * Unprocessed raw directory string.
   *
   * @return string
   */
  public function directory(): string;

  /**
   * Returns the directory where the source is located.
   *
   * @param bool $absolute
   *   Whether to return an absolute path.
   */
  public function directoryProcessed(bool $absolute = TRUE): string;

  /**
   * Returns the provider of the source plugin.
   *
   * @return string
   *   The provider (module or theme name) of the source plugin.
   */
  public function provider(): string;

  /**
   * Returns the source plugin's destination directory.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle
   *   The bundle.
   * @param bool $absolute_path
   *   Whether to return an absolute path.
   *
   * @return string
   *   The destination directory.
   */
  public function getDestinationDirectory(string $entity_type_id, string $bundle, bool $absolute_path = FALSE): string;

  /**
   * Returns the source plugin's destination filepath.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle
   *   The bundle.
   * @param string $filename
   *   The filename.
   * @param bool $absolute_path
   *   Whether to return an absolute path.
   *
   * @return string
   *   The destination filepath.
   */
  public function getDestinationFilepath(string $entity_type_id, string $bundle, string $filename, bool $absolute_path = FALSE): string;

  /**
   * Check if the export exists in the source.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle
   *   The bundle.
   * @param string $uuid
   *   The UUID.
   *
   * @return bool
   *   Whether the export exists in the source.
   */
  public function exportExistsInSource(string $entity_type_id, string $bundle, string $uuid): bool;

}
