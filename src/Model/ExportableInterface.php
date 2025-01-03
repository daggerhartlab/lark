<?php

namespace Drupal\lark\Model;


use Drupal\Component\Diff\Diff;
use Drupal\Core\Entity\EntityInterface;
use Drupal\lark\ExportableStatus;
use Drupal\lark\Plugin\Lark\SourceInterface;

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
   * @return \Drupal\lark\Plugin\Lark\SourceInterface|null
   *   Source plugin.
   */
  public function getSource(): ?SourceInterface;

  /**
   * Set source plugin.
   *
   * @param \Drupal\lark\Plugin\Lark\SourceInterface|null $source
   *   Source plugin.
   *
   * @return $this
   *   Return self.
   */
  public function setSource(?SourceInterface $source): self;

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
