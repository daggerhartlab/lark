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
   * If the export exists, this is the array of values that was exported.
   *
   * @var \Drupal\lark\Model\ExportArray|null
   */
  protected ?ExportArray $exportArray = NULL;

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
    return $this->exportArray->metaOptions();
  }

  /**
   * {@inheritdoc}
   */
  public function setMetaOptions(array $options): self {
    $this->exportArray->setMeta('options', $options);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getMetaOption(string $name): mixed {
    return $this->exportArray->getMetaOption($name);
  }

  /**
   * {@inheritdoc}
   */
  public function hasMetaOption(string $name): bool {
    return $this->exportArray->hasMetaOption($name);
  }

  /**
   * {@inheritdoc}
   */
  public function setMetaOption(string $name, $value): self {
    $this->exportArray->setMetaOption($name, $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getExportArray(): ExportArray {
    return $this->exportArray;
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
      $this->exportArray = new ExportArray(Yaml::decode(\file_get_contents($filepath)));
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
      ],
      'default' => Exporter::getEntityExportArray($this->entity()),
    ];

    if ($this->getMetaOptions()) {
      $array['_meta']['options'] = $this->getMetaOptions();
    }

    foreach ($this->entity()->getTranslationLanguages() as $langcode => $language) {
      if ($langcode === $this->entity()->language()->getId()) {
        continue;
      }
      $array['translations'][$langcode] = Exporter::getEntityExportArray($this->entity()->getTranslation($langcode));
    }

    return $array;
  }

}
