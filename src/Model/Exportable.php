<?php

namespace Drupal\lark\Model;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\lark\ExportableStatus;
use Drupal\lark\Entity\LarkSourceInterface;

/**
 * Model for wrapping entities for export.
 */
class Exportable implements ExportableInterface {

  /**
   * This is the new export array that we'll modify and use for export.
   *
   * @var \Drupal\lark\Model\ExportArray
   */
  protected ExportArray $entityExportArray;

  /**
   * If the export exists, this is the array of values that was exported.
   *
   * @var \Drupal\lark\Model\ExportArray
   */
  protected ExportArray $sourceExportArray;

  /**
   * Source plugin for this exportable, if known.
   *
   * @var \Drupal\lark\Entity\LarkSourceInterface|null
   */
  protected ?LarkSourceInterface $source = NULL;

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
  public function __construct(protected ContentEntityInterface $entity) {
    $this->entityExportArray = ExportArray::createFromEntity($this->entity);
    $this->sourceExportArray = new ExportArray();
  }

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
    return $this->entityExportArray->dependencies();
  }

  /**
   * {@inheritdoc}
   */
  public function setDependencies(array $dependencies): self {
    $this->entityExportArray->setDependencies($dependencies);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions(): array {
    return $this->entityExportArray->options();
  }

  /**
   * {@inheritdoc}
   */
  public function setOptions(array $options): self {
    $this->entityExportArray->setOptions($options);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOption(string $name): mixed {
    return $this->entityExportArray->getOption($name);
  }

  /**
   * {@inheritdoc}
   */
  public function hasOption(string $name): bool {
    return $this->entityExportArray->hasOption($name);
  }

  /**
   * {@inheritdoc}
   */
  public function setOption(string $name, $value): self {
    $this->entityExportArray->setOption($name, $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceExportArray(): ExportArray {
    return $this->sourceExportArray;
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
    return $this->getFilepath() && \file_exists($this->getFilepath());
  }

  /**
   * {@inheritdoc}
   */
  public function getSource(): ?LarkSourceInterface {
    return $this->source;
  }

  /**
   * {@inheritdoc}
   */
  public function setSource(?LarkSourceInterface $source): self {
    $this->source = $source;

    // If we're setting the source, then we have a knowable filepath.
    if ($source) {
      // Set the export filepath and attempt to load the source's ExportArray.
      $this->setFilepath($source->getDestinationFilepath(
        $this->entity()->getEntityTypeId(),
        $this->entity()->bundle(),
        $this->getFilename()
      ));
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilepath(): string {
    return $this->entityExportArray->path();
  }

  /**
   * {@inheritdoc}
   */
  public function setFilepath(string $filepath): self {
    $this->entityExportArray->setPath($filepath);

    // If we have an export, load its ExportArray.
    if ($this->getExportExists()) {
      $this->sourceExportArray = new ExportArray(Yaml::decode(\file_get_contents($filepath)));
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilename(): string {
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
    return $this->entityExportArray->cleanArray();
  }

}
