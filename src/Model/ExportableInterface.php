<?php

namespace Drupal\lark\Model;


use Drupal\Core\Entity\EntityInterface;
use Drupal\lark\ExportableStatus;
use Drupal\lark\Entity\LarkSourceInterface;

/**
 * Model for wrapping entities for export.
 */
interface ExportableInterface {

  /**
   * Get entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   Entity if exists.
   */
  public function entity(): ?EntityInterface;

  /**
   * Get dependencies.
   *
   * @return array
   *   Dependencies array.
   */
  public function getDependencies(): array;

  /**
   * Set exportable dependencies.
   *
   * @param array $dependencies
   *   Array of dependencies where the key is an entity uuid and the value is
   *   the entity type id.
   *
   * @return $this
   */
  public function setDependencies(array $dependencies): self;

  /**
   * Set of arbitrary options for the export.
   *
   * @return array
   *   Stored options.
   */
  public function getMetaOptions(): array;

  /**
   * Set meta options for export.
   *
   * @param array $options
   *   New options.
   *
   * @return $this
   */
  public function setMetaOptions(array $options): self;

  /**
   * Get meta option by key.
   *
   * @param string $name
   *   Name of the metdata item.
   *
   * @return mixed
   *   The metadata stored, or null.
   */
  public function getMetaOption(string $name): mixed;

  /**
   * Whether a meta option exists.
   *
   * @param string $name
   *   Name of the metdata item.
   *
   * @return bool
   *   True if the meta option exists.
   */
  public function hasMetaOption(string $name): bool;

  /**
   * Set some meta option for the export.
   *
   * @param string $name
   *   Name of data.
   * @param $value
   *   Value.
   *
   * @return $this
   */
  public function setMetaOption(string $name, $value): self;

  /**
   * Get the exported values if we have them.
   *
   * @return \Drupal\lark\Model\ExportArray
   */
  public function getSourceExportedArray(): ExportArray;

  /**
   * Get status code.
   *
   * @return \Drupal\lark\ExportableStatus
   *   Status code.
   */
  public function getStatus(): ExportableStatus;

  /**
   * Get status name.
   *
   * @return string
   *   Status name.
   */
  public function getStatusName(): string;

  /**
   * Set status code.
   *
   * @param \Drupal\lark\ExportableStatus $status
   *   Status code.
   *
   * @return \Drupal\lark\Model\ExportableInterface
   *   Return self.
   */
  public function setStatus(ExportableStatus $status): ExportableInterface;

  /**
   * True if the export file for this entity exists.
   *
   * @return bool
   *   Export exists.
   */
  public function getExportExists(): bool;

  /**
   * Set export exists.
   *
   * @param bool $exportExists
   *   Export exists.
   *
   * @return $this
   *   Return self.
   */
  public function setExportExists(bool $exportExists): ExportableInterface;

  /**
   * Get source plugin.
   *
   * @return \Drupal\lark\Entity\LarkSourceInterface|null
   *   Source plugin.
   */
  public function getSource(): ?LarkSourceInterface;

  /**
   * Set source plugin.
   *
   * @param \Drupal\lark\Entity\LarkSourceInterface|null $source
   *   Source plugin.
   *
   * @return $this
   *   Return self.
   */
  public function setSource(?LarkSourceInterface $source): self;

  /**
   * Get actual export filepath.
   *
   * @return string|null
   *   Actual export filepath.
   */
  public function getExportFilepath(): ?string;

  /**
   * Set actual export filepath.
   *
   * @param string $filepath
   *   Actual export filepath.
   *
   * @return $this
   *   Return self.
   */
  public function setExportFilepath(string $filepath): ExportableInterface;

  /**
   * Get export filename.
   *
   * @return string
   *   Export target filename.
   */
  public function getExportFilename(): string;

  /**
   * Get exportable as YAML.
   *
   * @return string
   *   Exportable as YAML.
   */
  public function toYaml(): string;

  /**
   * Get exportable as array.
   *
   * @return array
   *   Exportable as array.
   */
  public function toArray(): array;

}
