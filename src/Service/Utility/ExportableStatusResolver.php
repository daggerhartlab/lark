<?php

namespace Drupal\lark\Service\Utility;

use Drupal\Component\Diff\Diff;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\lark\ExportableStatus;
use Drupal\lark\Model\Exportable;
use Drupal\lark\Model\ExportableInterface;
use Drupal\lark\Plugin\Lark\SourceInterface;
use Drupal\lark\Service\ImporterInterface;
use Drupal\lark\Service\SourceManagerInterface;

/**
 * Used to determine the status of an exportable entity and prepare exports for
 * comparison.
 */
class ExportableStatusResolver {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected SourceManagerInterface $sourceManager,
    protected ImporterInterface $importer,
  ) {}

  /**
   * Get the source plugin for the given exportable.
   *
   * @param \Drupal\lark\Model\ExportableInterface $exportable
   *   The exportable entity.
   *
   * @return \Drupal\lark\Plugin\Lark\SourceInterface|null
   *   The source plugin or NULL if not found.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getExportableSource(ExportableInterface $exportable): ?SourceInterface {
    $entity = $exportable->entity();
    foreach ($this->sourceManager->getInstances() as $source) {
      if ($source->exportExistsInSource($entity->getEntityTypeId(), $entity->bundle(), $entity->uuid())) {
        return $source;
      }
    }

    return NULL;
  }

  /**
   * Determine the status of an exportable entity.
   *
   * @param \Drupal\lark\Model\ExportableInterface $exportable
   *   The exportable entity.
   * @param array $export
   *   The export array.
   *
   * @return \Drupal\lark\ExportableStatus
   *   The status code.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getExportableStatus(ExportableInterface $exportable, array $export = []): ExportableStatus {
    $entity = $exportable->entity();
    $source = $this->getExportableSource($exportable);

    if (!$source) {
      return ExportableStatus::NotExported;
    }

    // Set the source since we took the trouble of finding it.
    if (!$exportable->getSource()) {
      $exportable->setSource($source);
    }

    if ($entity->isNew()) {
      return ExportableStatus::NotImported;
    }

    // If we don't have an export array but the export has a source, then we can
    // load it for comparison.
    if (empty($export)) {
      $exports = $this->importer->discoverSourceExport($source, $entity->uuid());
      $export = $exports[$entity->uuid()];
    }

    $left = $export;
    $left = $this->processExportArrayForComparison($left);
    $right = $this->processExportArrayForComparison($exportable->toArray());
    if ($left === $right) {
      return ExportableStatus::InSync;
    }

    return ExportableStatus::OutOfSync;
  }

  /**
   * Convert an exportable entity to a diff object.
   *
   * @param \Drupal\lark\Model\ExportableInterface $exportable
   *   The exportable entity.
   *
   * @return \Drupal\Component\Diff\Diff
   *   The diff object.
   */
  public function exportableToDiff(ExportableInterface $exportable): Diff {
    $left_array = $exportable->getExportExists() ?
      Yaml::decode(\file_get_contents($exportable->getExportFilepath())) :
      [];

    // Process for comparison.
    $left_array = $this->processExportArrayForComparison($left_array);
    $right_array = $this->processExportArrayForComparison($exportable->toArray());

    return new Diff(
      explode("\n", Yaml::encode($left_array)),
      explode("\n", Yaml::encode($right_array))
    );
  }

  /**
   * Process the export array for comparison by removing values that diff from
   * one environment to another.
   *
   * @param array $export
   *   The export array.
   *
   * @return array
   *   The processed export array.
   */
  public function processExportArrayForComparison(array $export): array {
    $ignored_keys = $this->configFactory->get('lark.settings')->get('ignored_comparison_keys');
    $ignored_keys = array_filter(explode("\n", $ignored_keys));
    array_walk($ignored_keys, 'trim');

    // Ignore 'original_values' key added by the EntityReferenceUuidHandler.
    $ignored_keys[] = 'original_values';

    $this->deepUnsetAll($export, $ignored_keys);
    return $export;
  }

  /**
   * Unsets all array keys and object properties of the given names.
   *
   * @param array $data
   *   An iterable object or array to modify.
   * @param string[] $fields
   *   The names of the keys or properties to remove.
   */
  protected function deepUnsetAll(array &$data, array $fields): void {
    foreach ($fields as &$f) {
      unset($data[$f]);
    }

    foreach ($data as &$value) {
      if (is_array($value)) {
        $this->deepUnsetAll($value, $fields);
      }
    }
  }

}
