<?php

namespace Drupal\lark\Service\Utility;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\lark\Entity\LarkSourceInterface;
use Drupal\lark\Model\ExportableInterface;
use Drupal\lark\Plugin\Lark\SourceInterface;
use Drupal\lark\Service\ExportableFactoryInterface;
use Drupal\lark\Service\ImporterInterface;

class SourceViewBuilder {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ImporterInterface $importer,
    protected ExportableFactoryInterface $exportableFactory,
    protected ExportableStatusBuilder $statusBuilder,
  ) {}

  /**
   * Build the View for a single Source.
   *
   * @param \Drupal\lark\Entity\LarkSourceInterface $source
   *   Source config entity.
   *
   * @return array
   *   Build array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function viewSource(LarkSourceInterface $source): array {
    $exports = $this->importer->discoverSourceExports($source);
    $source_root_exports = $this->getRootLevelExports($exports);

    $build = [
      'details' => [
        '#type' => 'table',
        '#header' => [
          'heading' => [
            'colspan' => 2,
            'class' => ['summary-heading'],
            'data' => [
              '#type' => 'html_tag',
              '#tag' => 'span',
              '#value' => $this->t('Source: %label', [
                '%label' => $source->label(),
              ]),
            ],
          ]
        ],
        '#rows' => [],
        '#attributes' => [
          'class' => ['lark-source-view-summary'],
        ],
      ],
    ];
    $build['details']['#rows'][] = [
      'heading' => ['header' => TRUE, 'data' => $this->t('ID')],
      'value' => $source->id(),
    ];
    $build['details']['#rows'][] = [
      'heading' => ['header' => TRUE, 'data' => $this->t('Status')],
      'value' => $source->status() ? $this->t('Enabled') : $this->t('Disabled'),
    ];
    $build['details']['#rows'][] = [
      'heading' => ['header' => TRUE, 'data' => $this->t('Directory')],
      'value' => ['class' => ['directory'], 'data' => $source->directory()]
    ];
    $build['details']['#rows'][] = [
      'heading' => ['header' => TRUE, 'data' => $this->t('Absolute')],
      'value' => ['class' => ['directory'], 'data' => $source->directoryProcessed()],
    ];
    $build['details']['#rows'][] = [
      'heading' => ['header' => TRUE, 'data' => $this->t('Description')],
      'value' => $source->description(),
    ];

    foreach ($source_root_exports as $root_uuid => $root_export) {
      // Get the root_export's Exportable along with dependencies.
      $root_exportable = $this->exportableFactory->createFromSource($source->id(), $root_uuid);
      if (!$root_exportable) {
        continue;
      }

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

      $status_summary = $this->statusBuilder->getExportablesSummary(array_merge([$root_uuid => $root_exportable], $dependency_exportables));
      $build[$root_uuid] = [
        '#type' => 'container',
        'status_summary' => $status_summary,
        'root_table' => $this->tablePopulated($source, $root_exportable),
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
        'dependencies_table' => [
          '#type' => 'table',
          '#attributes' => ['class' => ['lark-exports-table']],
          '#empty' => $this->t('No dependencies found.'),
          '#header' => $this->headers(),
          '#rows' => array_map(function (ExportableInterface $exportable) use ($source) {
            return $this->buildExportableTableRow($source, $exportable);
          }, $dependency_exportables),
        ],
      ];
    }

    return $build;
  }

  /**
   * @param \Drupal\lark\Entity\LarkSourceInterface $source
   * @param \Drupal\lark\Model\ExportableInterface $root_exportable
   *
   * @return array
   */
  public function tablePopulated(LarkSourceInterface $source, ExportableInterface $root_exportable): array {
    $table = $this->table();
    $table['#rows'][] = $this->buildExportableTableRow($source, $root_exportable);
    return $table;
  }

  /**
   * @return array[]
   */
  public function headers(): array {
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
   * @return array
   */
  public function table(): array {
    return [
      '#type' => 'table',
      '#header' => $this->headers(),
      '#rows' => [],
      '#attributes' => [
        'class' => ['lark-sources-table'],
      ],
      '#attached' => [
        'library' => ['lark/admin'],
      ],
    ];
  }

  /**
   * Build single table row for exportable entity.
   *
   * @param \Drupal\lark\Plugin\Lark\SourceInterface $source
   *   Source plugin.
   * @param \Drupal\lark\Model\ExportableInterface $exportable
   *   Exportable entity.
   *
   * @return array
   *   Table row.
   */
  protected function buildExportableTableRow(SourceInterface $source, ExportableInterface $exportable): array {
    $relative = str_replace($source->directoryProcessed() . DIRECTORY_SEPARATOR, '', $exportable->getExportFilepath());
    $status_details = $this->statusBuilder->getStatusRenderDetails($exportable->getStatus());
    return [
      'class' => [
        'lark-exports-row',
        'lark-exports-row--' . $status_details['class_name']
      ],
      'data' => [
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
          'data' => Markup::create("<small title='{$exportable->getExportFilepath()}'><code>{$relative}</code></small>"),
        ],
        'operations' => [
          'data' => $this->buildExportableOperations($source, $exportable),
        ],
      ],
    ];
  }

  /**
   * Build operation links for given exportable.
   *
   * @param \Drupal\lark\Plugin\Lark\SourceInterface $source
   *   Source plugin.
   * @param \Drupal\lark\Model\ExportableInterface $exportable
   *   Exportable entity.
   *
   * @return array
   *   Render array.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function buildExportableOperations(SourceInterface $source, ExportableInterface $exportable): array {
    // Determine export status and possible operations.
    $operations = [];

    if ($exportable->entity()->isNew()) {
      $operations['import'] = [
        'title' => $this->t('Import'),
        'url' => Url::fromRoute('lark.import_single', [
          'source_plugin_id' => $source->id(),
          'uuid' => $exportable->entity()->uuid(),
        ]),
      ];
    }
    if (!$exportable->entity()->isNew()) {
      $entity_type = $this->entityTypeManager->getDefinition($exportable->entity()->getEntityTypeId());

      if ($entity_type->hasLinkTemplate('canonical')) {
        $operations['view'] = [
          'title' => $this->t('View'),
          'url' => $exportable->entity()->toUrl()->setRouteParameter('lark_source', $source->id()),
        ];
      }
      if ($entity_type->hasLinkTemplate('edit-form')) {
        $operations['edit'] = [
          'title' => $this->t('Edit'),
          'url' => $exportable->entity()->toUrl('edit-form'),
        ];
      }
      if ($entity_type->hasLinkTemplate('lark-load')) {
        $operations['lark'] = [
          'title' => $this->t('Export'),
          'url' => $exportable->entity()->toUrl('lark-load'),
        ];
      }
      if ($entity_type->hasLinkTemplate('lark-import')) {
        $operations['lark_import'] = [
          'title' => $this->t('Import'),
          'url' => $exportable->entity()->toUrl('lark-import'),
        ];
      }
      if ($entity_type->hasLinkTemplate('lark-download')) {
        $operations['lark_download'] = [
          'title' => $this->t('Download'),
          'url' => $exportable->entity()->toUrl('lark-download'),
        ];
      }
      if ($entity_type->hasLinkTemplate('lark-diff')) {
        $operations['lark_diff'] = [
          'title' => $this->t('Diff'),
          'url' => $exportable->entity()->toUrl('lark-diff'),
        ];
      }

    }

    return [
      '#type' => 'operations',
      '#links' => $operations,
    ];
  }

  /**
   * Get only the root-level exports.
   *
   * @param array $exports
   *   Exports array.
   *
   * @return array
   *   Root-level exports.
   */
  protected function getRootLevelExports(array $exports): array {
    return array_filter($exports, function ($export, $uuid) use ($exports) {
      foreach ($exports as $other_export) {
        if (isset($other_export['_meta']['depends'][$uuid])) {
          return FALSE;
        }
      }

      return TRUE;
    }, ARRAY_FILTER_USE_BOTH);
  }

}
