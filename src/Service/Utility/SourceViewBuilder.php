<?php

namespace Drupal\lark\Service\Utility;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\lark\Entity\LarkSourceInterface;
use Drupal\lark\Model\ExportableInterface;
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
    $import_link = Link::createFromRoute('Import All', 'lark.action_import_source', [
      'source_plugin_id' => $source->id(),
    ])->toRenderable();
    $import_link['#attributes']['class'][] = 'button';

    $build = [
      'details' => $this->sourceDetails($source),
      'actions' => [
        'import' => $import_link,
      ],
      'table' => $this->tablePopulated($source),
    ];

    return $build;
  }

  /**
   * @param \Drupal\lark\Entity\LarkSourceInterface $source
   *
   * @return array
   */
  public function sourceDetails(LarkSourceInterface $source): array {
    return [
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
        ],
      ],
      '#rows' => [
        [
          'heading' => ['header' => TRUE, 'data' => $this->t('ID')],
          'value' => $source->id(),
        ],
        [
          'heading' => ['header' => TRUE, 'data' => $this->t('Status')],
          'value' => $source->status() ? $this->t('Enabled') : $this->t('Disabled'),
        ],
        [
          'heading' => ['header' => TRUE, 'data' => $this->t('Directory')],
          'value' => ['class' => ['directory'], 'data' => $source->directory()]
        ],
        [
          'heading' => ['header' => TRUE, 'data' => $this->t('Absolute')],
          'value' => ['class' => ['directory'], 'data' => $source->directoryProcessed()],
        ],
        [
          'heading' => ['header' => TRUE, 'data' => $this->t('Description')],
          'value' => $source->description(),
        ],
      ],
      '#attributes' => [
        'class' => ['lark-source-view-summary'],
      ],
    ];
  }

  /**
   * @param \Drupal\lark\Entity\LarkSourceInterface $source
   * @param \Drupal\lark\Model\ExportableInterface $root_exportable
   *
   * @return array
   */
  public function tablePopulated(LarkSourceInterface $source): array {
    $exports = $this->importer->discoverSourceExports($source);
    $source_root_exports = $this->getRootLevelExports($exports);

    $table = $this->table();

    // The root export is a top-level table row
    foreach ($source_root_exports as $root_uuid => $root_export) {
      // Get the root_export's Exportable along with dependencies.
      $root_exportable = $this->exportableFactory->createFromSource($source->id(), $root_uuid);
      if (!$root_exportable) {
        continue;
      }

      $table['#rows'][] = $this->tableToggleRow($source, $root_exportable);
      $table['#rows'][] = $this->tableDetailsRow($source, $root_exportable);
    }

    return $table;
  }

  /**
   * @return array
   */
  protected function table(): array {
    return [
      '#type' => 'table',
      '#header' => $this->toggleHeaders(),
      '#rows' => [],
      '#attributes' => [
        'class' => ['lark-source-table'],
      ],
      '#attached' => [
        'library' => ['lark/admin'],
      ],
    ];
  }

  /**
   * @return array[]
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
   * @return array[]
   */
  protected function toggleHeaders(): array {
    return $this->headers() + [
        'toggle' => [
          'class' => ['toggle'],
          'data' => $this->t('Details'),
        ]
      ];
  }

  /**
   * Same as the data row, but with toggle instead of operations.
   *
   * @param \Drupal\lark\Entity\LarkSourceInterface $source
   *   Source plugin.
   * @param \Drupal\lark\Model\ExportableInterface $exportable
   *   Exportable entity.
   *
   * @return array
   *   Table row.
   */
  protected function tableToggleRow(LarkSourceInterface $source, ExportableInterface $exportable): array {
    $row = $this->tableDataRow($source, $exportable);
    //unset($row['data']['operations']);
    $row['data']['toggle'] = [
      'class' => ['lark-toggle-handle'],
      'data-uuid' => $exportable->entity()->uuid(),
      'data' => [
        'icon' => [
          '#theme' => 'image',
          '#alt' => 'Toggle row',
          '#attributes' => [
            'src' => '/modules/contrib/lark/assets/icons/file-yaml.png',
            'width' => '35',
            'height' => '35',
          ],
        ],
      ],
    ];
    return $row;
  }

  /**
   * Build single table row for exportable entity.
   *
   * @param \Drupal\lark\Entity\LarkSourceInterface $source
   *   Source plugin.
   * @param \Drupal\lark\Model\ExportableInterface $exportable
   *   Exportable entity.
   *
   * @return array
   *   Table row.
   */
  protected function tableDataRow(LarkSourceInterface $source, ExportableInterface $exportable): array {
    $relative = str_replace($source->directoryProcessed(FALSE) . DIRECTORY_SEPARATOR, '', $exportable->getExportFilepath());
    $status_details = $this->statusBuilder->getStatusRenderDetails($exportable->getStatus());
    return [
      'class' => [
        'lark-toggle-row',
        'lark-toggle-row--' . $status_details['class_name']
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
          'data' => $this->sourceExportableOperations($source, $exportable),
        ],
      ],
    ];
  }

  /**
   * @param \Drupal\lark\Entity\LarkSourceInterface $source
   * @param \Drupal\lark\Model\ExportableInterface $root_exportable
   *
   * @return array[]
   */
  protected function tableDetailsRow(LarkSourceInterface $source, ExportableInterface $root_exportable): array {
    $root_uuid = $root_exportable->entity()->uuid();
    $dependency_exportables = $this->getRootDependencyExportables($source, $root_uuid);

    return [
      'details' => [
        'colspan' => count($this->toggleHeaders()),
        'class' => ['lark-toggle-details-row', 'lark-toggle-details-row--' . $root_uuid],
        'data' => [
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
              return $this->tableDataRow($source, $exportable);
            }, $dependency_exportables),
          ],
        ],
      ],
    ];
  }

  /**
   * @param \Drupal\lark\Entity\LarkSourceInterface $source
   * @param string $root_uuid
   *
   * @return array
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
   * Build operation links for given exportable.
   *
   * @param \Drupal\lark\Entity\LarkSourceInterface $source
   *   Source plugin.
   * @param \Drupal\lark\Model\ExportableInterface $exportable
   *   Exportable entity.
   *
   * @return array
   *   Render array.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function sourceExportableOperations(LarkSourceInterface $source, ExportableInterface $exportable): array {
    // Determine export status and possible operations.
    $operations = [];

    if ($exportable->entity()->isNew()) {
      $operations['import'] = [
        'title' => $this->t('Import'),
        'url' => Url::fromRoute('lark.action_import_source_entity', [
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
        $operations['edit_form'] = [
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
