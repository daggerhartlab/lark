<?php

namespace Drupal\lark\Model;


use Drupal\Core\Entity\EntityInterface;
use Drupal\lark\Model\ExportableStatus;
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
   * Get export array dependencies.
   *
   * @return array
   *   Dependencies array.
   */
  public function getDependencies(): array;

  /**
   * Set export array dependencies.
   *
   * @param array $dependencies
   *   Array of dependencies where the key is an entity uuid and the value is
   *   the entity type id.
   *
   * @return $this
   */
  public function setDependencies(array $dependencies): self;

  /**
   * Get of export array 'options'.
   *
   * @return array
   *   Stored options.
   */
  public function getOptions(): array;

  /**
   * Set export array options.
   *
   * @param array $options
   *   New options.
   *
   * @return $this
   */
  public function setOptions(array $options): self;

  /**
   * Get export array option by name.
   *
   * @param string $name
   *   Name of the option item.
   *
   * @return mixed
   *   The metadata stored, or null.
   */
  public function getOption(string $name): mixed;

  /**
   * Whether an export array option exists with the given name.
   *
   * @param string $name
   *   Name of the metadata item.
   *
   * @return bool
   *   True if the meta option exists.
   */
  public function hasOption(string $name): bool;

  /**
   * Set export array option by name.
   *
   * @param string $name
   *   Name of data.
   * @param $value
   *   Value.
   *
   * @return $this
   */
  public function setOption(string $name, $value): self;

  /**
   * Get the values already exported to this Exportable's Source's.
   *
   * @return \Drupal\lark\Model\ExportArray
   */
  public function getSourceExportArray(): ExportArray;

  /**
   * Get status code that indicates how the entity and export arrays compare.
   *
   * @return \Drupal\lark\Model\ExportableStatus
   *   Status code.
   */
  public function getStatus(): ExportableStatus;

  /**
   * Get the name of the status code.
   *
   * @return string
   *   Status name.
   */
  public function getStatusName(): string;

  /**
   * Set a status code that indicates how the entity and export arrays compare.
   *
   * @param \Drupal\lark\Model\ExportableStatus $status
   *   Status code.
   *
   * @return \Drupal\lark\Model\ExportableInterface
   *   Return self.
   */
  public function setStatus(ExportableStatus $status): ExportableInterface;

  /**
   * True if this Exportable has already been exported to a Source.
   *
   * @return bool
   *   Export exists.
   */
  public function getExportExists(): bool;

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
   * Get export array filepath.
   *
   * @return string
   *   Actual export filepath.
   */
  public function getFilepath(): string;

  /**
   * Set export array filepath.
   *
   * @param string $filepath
   *   Actual export filepath.
   *
   * @return $this
   *   Return self.
   */
  public function setFilepath(string $filepath): ExportableInterface;

  /**
   * Get the expected filename for this Exportable.
   *
   * @return string
   *   Exportable target filename.
   */
  public function getFilename(): string;

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
