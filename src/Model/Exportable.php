<?php

namespace Drupal\lark\Model;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\lark\ExportableStatus;
use Drupal\lark\Plugin\Lark\SourceInterface;
use Drupal\lark\Service\Exporter;

/**
 * Model for wrapping entities for export.
 */
class Exportable implements ExportableInterface {

  /**
   * Actual export file path.
   *
   * @var string|null
   */
  protected ?string $exportFilepath = NULL;

  /**
   * Source plugin for this exportable, if known.
   *
   * @var \Drupal\lark\Plugin\Lark\SourceInterface|null
   */
  protected ?SourceInterface $source = NULL;

  /**
   * True if the export file for this entity exists.
   *
   * @var bool
   */
  protected bool $exportExists = FALSE;

  /**
   * Dependencies array keys are entity UUIDs, values are entity type IDs.
   *
   * @var string[]
   */
  protected array $dependencies = [];

  /**
   * Additional metadata to be added during export.
   *
   * @var array
   */
  protected array $additional_metadata = [];

  /**
   * Export status.
   *
   * @var \Drupal\lark\ExportableStatus
   */
  protected ExportableStatus $status = ExportableStatus::NotImported;

  /**
   * @param \Drupal\Core\Entity\ContentEntityInterface|null $entity
   *   Entity.
   */
  public function __construct(protected ContentEntityInterface $entity) {}

  /**
   * {@inheritdoc}
   */
  public function entity(): ContentEntityInterface {
    return $this->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getDependencies(): array {
    return $this->dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function setDependencies(array $dependencies): self {
    $this->dependencies = $dependencies;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAdditionalMetadata(string $key): mixed {
    return $this->additional_metadata[$key] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setAdditionalMetadata(string $key, $value): self {
    $this->additional_metadata[$key] = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus(): ExportableStatus {
    return $this->status;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatusName(): string {
    return $this->getStatus()->name;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus(ExportableStatus $status): self {
    $this->status = $status;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getExportExists(): bool {
    return $this->exportExists;
  }

  /**
   * {@inheritdoc}
   */
  public function setExportExists(bool $exportExists): self {
    $this->exportExists = $exportExists;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSource(): ?SourceInterface {
    return $this->source;
  }

  /**
   * {@inheritdoc}
   */
  public function setSource(?SourceInterface $source): self {
    $this->source = $source;
    if ($source && !$this->getExportFilepath()) {
      $this->setExportFilepath($source->getDestinationFilepath(
        $this->entity()->getEntityTypeId(),
        $this->entity()->bundle(),
        $this->getExportFilename()
      ));
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getExportFilepath(): ?string {
    return $this->exportFilepath;
  }

  /**
   * {@inheritdoc}
   */
  public function setExportFilepath(string $filepath): self {
    $this->exportFilepath = $filepath;
    $this->setExportExists(\file_exists($filepath));
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getExportFilename(): string {
    return $this->entity()->uuid() . '.yml';
  }

  /**
   * {@inheritdoc}
   */
  public function toYaml(): string {
    return Yaml::encode($this->toArray());
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    $array = [
      '_meta' => [
        'entity_type' => $this->entity()->getEntityTypeId(),
        'bundle' => $this->entity()->bundle(),
        'entity_id' => $this->entity()->id(),
        'label' => (string) $this->entity()->label(),
        'path' => $this->getExportFilepath(),
        'uuid' => $this->entity()->uuid(),
        'default_langcode' => $this->entity()->language()->getId(),
        'depends' => $this->getDependencies(),
      ] + $this->additional_metadata,
      'default' => Exporter::getEntityExportArray($this->entity()),
    ];

    foreach ($this->entity()->getTranslationLanguages() as $langcode => $language) {
      if ($langcode === $this->entity()->language()->getId()) {
        continue;
      }
      $array['translations'][$langcode] = Exporter::getEntityExportArray($this->entity()->getTranslation($langcode));
    }

    return $array;
  }

}
