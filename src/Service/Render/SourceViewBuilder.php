<?php

namespace Drupal\lark\Service\Render;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\lark\Entity\LarkSourceInterface;
use Drupal\lark\Model\ExportableInterface;
use Drupal\lark\Service\ExportableFactoryInterface;
use Drupal\lark\Service\ImporterInterface;
use Drupal\lark\Service\Utility\ExportUtility;
use Drupal\lark\Service\Utility\SourceUtility;

/**
 * Build the render array for a single Source.
 */
class SourceViewBuilder {

  use StringTranslationTrait;

  /**
   * SourceViewBuilder constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\lark\Service\ImporterInterface $importer
   *   The importer.
   * @param \Drupal\lark\Service\Utility\ExportUtility $exportUtility
   *   Export utility.
   * @param \Drupal\lark\Service\ExportableFactoryInterface $exportableFactory
   *   Exportable factory.
   * @param \Drupal\lark\Service\Render\ExportablesStatusBuilder $statusBuilder
   *   Exportable status builder.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Module handler.
   * @param \Drupal\lark\Service\Render\SourceRootsViewBuilder $rootsViewBuilder
   *   Source roots view builder.
   * @param \Drupal\lark\Service\Utility\SourceUtility $sourceUtility
   *   Source utility.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ExportUtility $exportUtility,
    protected ExportableFactoryInterface $exportableFactory,
    protected ExportablesStatusBuilder $statusBuilder,
    protected ImporterInterface $importer,
    protected ModuleHandlerInterface $moduleHandler,
    protected SourceRootsViewBuilder $rootsViewBuilder,
    protected SourceUtility $sourceUtility,
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
  public function view(LarkSourceInterface $source): array {
    $import_link = Link::createFromRoute('Import All', 'lark.action_import_source', [
      'lark_source' => $source->id(),
    ])->toRenderable();
    $import_link['#attributes']['class'][] = 'button';

    $download_link = Link::createFromRoute('Download', 'lark.action_download_source', [
      'lark_source' => $source->id(),
    ])->toRenderable();
    $download_link['#attributes']['class'][] = 'button';

    $build = [
      'details' => $this->sourceDetails($source),
      'actions' => [
        'import' => $import_link,
        'download' => $download_link,
      ],
      'summary' => $this->sourceSummary($source),
      'table' => $this->table($source),
    ];

    return $build;
  }

  /**
   * Build the summary details for a single Source.
   *
   * @param \Drupal\lark\Entity\LarkSourceInterface $source
   *   Source config entity.
   *
   * @return array
   *   Render array.
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
   * Build the summary for a single Source.
   *
   * @param \Drupal\lark\Entity\LarkSourceInterface $source
   *   Source config entity.
   *
   * @return array
   *   Summary array.
   */
  public function sourceSummary(LarkSourceInterface $source): array {
    $exports = $this->importer->discoverSourceExports($source);

    $exportables = array_map(function($export) use ($source) {
      return $this->exportableFactory->createFromExportArray($export, $source);
    }, $exports);

    return $this->statusBuilder->getExportablesSummary($exportables);
  }

  /**
   * Build the table of exports for a single Source.
   *
   * @param \Drupal\lark\Entity\LarkSourceInterface $source
   *   Source config entity.
   * @param \Drupal\lark\Model\ExportableInterface $root_exportable
   *   The root-level exportable entity.
   *
   * @return array
   */
  public function table(LarkSourceInterface $source): array {
    $path = $this->moduleHandler->getModule('lark')->getPath();

    $table = [
      '#theme' => 'toggle_row_table',
      '#header' => $this->headers(),
      '#rows' => $this->rows($source),
      '#attributes' => ['class' => ['lark-source-table']],
      '#attached' => ['library' => ['lark/admin']],
      '#toggle_handle_open' => [
        '#theme' => 'image',
        '#alt' => 'Toggle row',
        '#attributes' => [
          'src' => Url::fromUri("base:/{$path}/assets/icons/folder-closed.png")->toString(),
          'width' => '35',
          'height' => '35',
        ],
      ],
      '#toggle_handle_close' => [
        '#theme' => 'image',
        '#alt' => 'Toggle row',
        '#attributes' => [
          'src' => Url::fromUri("base:/{$path}/assets/icons/folder-open.png")->toString(),
          'width' => '35',
          'height' => '35',
        ],
      ],
    ];

    return $table;
  }

  /**
   * Build the table headers.
   *
   * @return array
   *   Headers array.
   */
  protected function headers(): array {
    return [
      'status' => [
        'class' => ['status'],
        'data' => $this->t('Status'),
      ],
      'dependencies' => [
        'class' => ['dependencies'],
        'data' => $this->t('Deps.'),
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
   *   Source config entity.
   *
   * @return array
   *   Rows array.
   */
  protected function rows(LarkSourceInterface $source): array {
    $exports = $this->importer->discoverSourceExports($source);
    $exports = array_reverse($exports);
    $source_root_exports = $this->exportUtility->getRootLevelExports($exports);

    // The root export is a top-level table row
    $rows = [];
    foreach ($source_root_exports as $root_uuid => $root_export) {
      // Get the root_export's Exportable along with dependencies.
      $root_exportable = $this->exportableFactory->createFromSource($source->id(), $root_uuid);
      if (!$root_exportable) {
        continue;
      }

      $rows[] = $this->row($source, $root_exportable);
    }

    return $rows;
  }

  /**
   * Build a single table row.
   *
   * @param \Drupal\lark\Entity\LarkSourceInterface $source
   *   Source config entity.
   * @param \Drupal\lark\Model\ExportableInterface $exportable
   *   Exportable entity.
   *
   * @return array
   *   Row array.
   */
  protected function row(LarkSourceInterface $source, ExportableInterface $exportable): array {
    $relative = str_replace($source->directoryProcessed(FALSE) . DIRECTORY_SEPARATOR, '', $exportable->getFilepath());
    $status_details = $this->statusBuilder->getStatusRenderDetails($exportable->getStatus());
    return [
      'status' => [
        'class' => ['status'],
        'data' => $status_details['icon_render'],
      ],
      'dependencies' => [
        'class' => ['dependencies'],
        'data' => count($exportable->getDependencies()),
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
      'details' => [
        'data' => $this->rootsViewBuilder->view($source, $exportable),
      ],
    ];
  }

}
