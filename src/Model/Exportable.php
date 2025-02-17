<?php

namespace Drupal\lark\Model;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\lark\ExportableStatus;
use Drupal\lark\Entity\LarkSourceInterface;
use Drupal\lark\Service\Exporter;

/**
 * Model for wrapping entities for export.
 */
class Exportable implements ExportableInterface {

  /**
   * If the export exists, this is the array of values that was exported.
   *
   * @var \Drupal\lark\Model\ExportArray
   */
  protected ExportArray $sourceExportedArray;

  /**
   * This is the new export array that we'll modify and use for export.
   *
   * @var \Drupal\lark\Model\ExportArray
   */
  protected ExportArray $exportArray;

  /**
   * Actual export file path.
   *
   * @var string|null
   */
  protected ?string $exportFilepath = NULL;

  /**
   * Source plugin for this exportable, if known.
   *
   * @var \Drupal\lark\Entity\LarkSourceInterface|null
   */
  protected ?LarkSourceInterface $source = NULL;

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
  protected array $metaOptions = [];

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
    $this->exportArray = ExportArray::createFromEntity($this->entity);
    $this->sourceExportedArray = new ExportArray();
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
    return $this->exportArray->dependencies();
  }

  /**
   * {@inheritdoc}
   */
  public function setDependencies(array $dependencies): self {
    $this->exportArray->setDependencies($dependencies);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getMetaOptions(): array {
    return $this->exportArray->options();
  }

  /**
   * {@inheritdoc}
   */
  public function setMetaOptions(array $options): self {
    $this->exportArray->setOptions($options);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getMetaOption(string $name): mixed {
    return $this->exportArray->getOption($name);
  }

  /**
   * {@inheritdoc}
   */
  public function hasMetaOption(string $name): bool {
    return $this->exportArray->hasOption($name);
  }

  /**
   * {@inheritdoc}
   */
  public function setMetaOption(string $name, $value): self {
    $this->exportArray->setOption($name, $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceExportedArray(): ExportArray {
    return $this->sourceExportedArray;
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
  public function getSource(): ?LarkSourceInterface {
    return $this->source;
  }

  /**
   * {@inheritdoc}
   */
  public function setSource(?LarkSourceInterface $source): self {
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
    if ($this->getExportExists()) {
      $this->sourceExportedArray = new ExportArray(Yaml::decode(\file_get_contents($filepath)));
    }

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
    // Use a new ExportArray instead of ::exportArray because this method is
    // called when generating a Diff.
    $export = clone $this->exportArray;
    $export->setEntityTypeId($this->entity()->getEntityTypeId());
    $export->setBundle($this->entity()->bundle());
    $export->setEntityId($this->entity()->id());
    $export->setLabel($this->entity()->label());
    $export->setPath($this->getExportFilepath());
    $export->setUuid($this->entity()->uuid());
    $export->setDefaultLangcode($this->entity->language()->getId());
    $export->setDependencies($this->dependencies);
    $export->setContent(Exporter::getEntityExportArray($this->entity()));
    $export->setOptions($this->getMetaOptions());

    foreach ($this->entity()->getTranslationLanguages() as $langcode => $language) {
      if ($langcode === $this->entity()->language()->getId()) {
        continue;
      }
      $export->setTranslation($langcode, Exporter::getEntityExportArray($this->entity()->getTranslation($langcode)));
    }

    return $export->cleanArray();
  }

}
