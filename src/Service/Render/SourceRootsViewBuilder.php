<?php

namespace Drupal\lark\Service\Render;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\lark\Entity\LarkSourceInterface;
use Drupal\lark\Model\ExportableInterface;
use Drupal\lark\Service\ExportableFactoryInterface;
use Drupal\lark\Service\ImporterInterface;
use Drupal\lark\Service\Utility\SourceUtility;

/**
 * Build the render array for a Source's root-level Exports.
 */
class SourceRootsViewBuilder {

  use StringTranslationTrait;

  /**
   * SourceRootsViewBuilder constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\lark\Service\ExportableFactoryInterface $exportableFactory
   *   Exportable factory.
   * @param \Drupal\lark\Service\Render\ExportablesStatusBuilder $statusBuilder
   *   Exportable status builder.
   * @param \Drupal\lark\Service\ImporterInterface $importer
   *   The importer.
   * @param \Drupal\lark\Service\Utility\SourceUtility $sourceUtility
   *   Source utility.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ExportableFactoryInterface $exportableFactory,
    protected ExportablesStatusBuilder   $statusBuilder,
    protected ImporterInterface          $importer,
    protected SourceUtility              $sourceUtility,
  ) {}

  /**
   * Build the View for a Source's root-level Exports.
   *
   * @param \Drupal\lark\Entity\LarkSourceInterface $source
   *   The source entity.
   *
   * @return array
   *   The render array.
   */
  public function view(LarkSourceInterface $source, ExportableInterface $exportable): array {
    $root_uuid = $exportable->entity()->uuid();
    $dependency_exportables = $this->getRootDependencyExportables($source, $root_uuid);

    return [
      'summary' => $this->statusBuilder->getExportablesSummary($dependency_exportables),
      'dependencies_heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h4',
        '#value' => $this->t('Dependencies'),
      ],
      'dependencies_description' => [
        '#type' => 'html_tag',
        '#tag' => 'small',
        '#value' => $this->t('All dependencies of an entity are imported along with the parent entities.'),
      ],
      'dependencies_table' => $this->table($source, $dependency_exportables),
    ];
  }


  /**
   * Get the root-level dependencies of an exportable.
   *
   * @param \Drupal\lark\Entity\LarkSourceInterface $source
   *   The source entity.
   * @param string $root_uuid
   *   The UUID of the root exportable.
   *
   * @return array
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getRootDependencyExportables(LarkSourceInterface $source, string $root_uuid): array {
    $dependency_exports = $this->importer->discoverSourceExport($source, $root_uuid);
    $dependency_exports = array_reverse($dependency_exports);
    $dependency_exportables = [];
    foreach ($dependency_exports as $dependency_uuid => $dependency_export) {
      if ($dependency_uuid === $root_uuid) {
        continue;
      }

      $dependency_exportable = $this->exportableFactory->createFromSource($source->id(), $dependency_uuid);
      if ($dependency_exportable) {
        $dependency_exportables[] = $dependency_exportable;
      }
    }

    return $dependency_exportables;
  }

  /**
   * Build the table render array.
   *
   * @param \Drupal\lark\Entity\LarkSourceInterface $source
   *   The source entity.
   * @param \Drupal\lark\Model\ExportableInterface[] $dependency_exportables
   *   The dependency exportables.
   *
   * @return array
   *   The render array.
   */
  public function table(LarkSourceInterface $source, array $dependency_exportables): array {
    return [
      '#theme' => 'table',
      '#header' => $this->headers(),
      '#rows' => $this->rows($source, $dependency_exportables),
      '#empty' => $this->t('No dependencies found.'),
      '#attributes' => [
        'class' => ['lark-exportables-table-form'],
      ],
      '#attached' => [
        'library' => ['lark/admin']
      ],
    ];
  }

  /**
   * Build the table headers.
   *
   * @return array
   *   The headers array.
   */
  protected function headers(): array {
    return [
      'status' => [
        'class' => ['status'],
        'data' => $this->t('Status'),
      ],
      'entity_type' => [
        'class' => ['entity-type'],
        'data' => $this->t('Entity type'),
      ],
      'label' => [
        'class' => ['label'],
        'data' => $this->t('Label'),
      ],
      'filepath' => [
        'class' => ['filepath'],
        'data' => $this->t('File'),
      ],
      'operations' => [
        'class' => ['operations'],
        'data' => $this->t('Operations'),
      ],
    ];
  }

  /**
   * Build the table rows.
   *
   * @param \Drupal\lark\Entity\LarkSourceInterface $source
   *   The source entity.
   * @param \Drupal\lark\Model\ExportableInterface[] $dependency_exportables
   *   The dependency exportables.
   *
   * @return array
   *   The rows array.
   */
  protected function rows(LarkSourceInterface $source, array $dependency_exportables): array {
    $rows = [];
    foreach ($dependency_exportables as $exportable) {
      $rows[] = $this->row($source, $exportable);
    }
    return $rows;
  }

  /**
   * Build a single table row.
   *
   * @param \Drupal\lark\Entity\LarkSourceInterface $source
   *   The source entity.
   * @param \Drupal\lark\Model\ExportableInterface $exportable
   *   The exportable entity.
   *
   * @return array
   *   The row array.
   */
  protected function row(LarkSourceInterface $source, ExportableInterface $exportable): array {
    $relative = str_replace($source->directoryProcessed(FALSE) . DIRECTORY_SEPARATOR, '', $exportable->getFilepath());
    $status_details = $this->statusBuilder->getStatusRenderDetails($exportable->getStatus());
    return [
      'status' => [
        'class' => ['status'],
        'data' => $status_details['icon_render'],
      ],
      'entity_type' => [
        'class' => ['entity-type'],
        'data' => $exportable->entity()->getEntityTypeId(),
      ],
      'label' => [
        'class' => ['label'],
        'data' => $exportable->entity()->label(),
      ],
      'filepath' => [
        'class' => ['filepath'],
        'data' => Markup::create("<small title='{$exportable->getFilepath()}'><code>{$relative}</code></small>"),
      ],
      'operations' => [
        'data' => [
          '#type' => 'operations',
          '#links' => $this->sourceUtility->getExportableOperations($source, $exportable),
        ],
      ],
    ];
  }

}
