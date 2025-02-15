<?php

declare(strict_types=1);

namespace Drupal\lark\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\lark\ExportableStatus;
use Drupal\lark\Model\LarkSettings;
use Drupal\lark\Service\Utility\ExportableStatusBuilder;
use Drupal\lark\Model\ExportableInterface;
use Drupal\lark\Plugin\Lark\SourceInterface;
use Drupal\lark\Service\ExporterInterface;
use Drupal\lark\Service\ImporterInterface;
use Drupal\lark\Service\ExportableFactoryInterface;
use Drupal\lark\Service\SourceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Entity exports controller.
 */
class ExportsManager extends ControllerBase {

  /**
   * The controller constructor.
   *
   * @param \Drupal\lark\Service\Exporter $exporter
   *   The entity exporter service.
   * @param \Drupal\lark\Service\ImporterInterface $importer
   *   The entity importer service.
   * @param \Drupal\lark\Service\SourceManagerInterface $sourceManager
   *   The Lark source plugin manager service.
   * @param \Drupal\lark\Service\ExportableFactoryInterface $exportableFactory
   *   The Lark exportable factory service.
   */
  public function __construct(
    protected ExporterInterface $exporter,
    protected ImporterInterface $importer,
    protected SourceManagerInterface $sourceManager,
    protected ExportableFactoryInterface $exportableFactory,
    protected EntityRepositoryInterface $entityRepository,
    protected ExportableStatusBuilder $statusBuilder,
    protected LarkSettings $settings,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get(ExporterInterface::class),
      $container->get(ImporterInterface::class),
      $container->get(SourceManagerInterface::class),
      $container->get(ExportableFactoryInterface::class),
      $container->get(EntityRepositoryInterface::class),
      $container->get(ExportableStatusBuilder::class),
      $container->get(LarkSettings::class),
    );
  }

  /**
   * Export single entity.
   *
   * @param string $source_plugin_id
   *   Source plugin id.
   * @param string $entity_type_id
   *   Entity type id.
   * @param string $entity_id
   *   Entity id.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\lark\Exception\LarkEntityNotFoundException
   */
  public function exportEntity(string $source_plugin_id, string $entity_type_id, string $entity_id): RedirectResponse {
    $this->exporter->exportEntity($source_plugin_id, $entity_type_id, (int) $entity_id);
    return new RedirectResponse(Url::fromRoute('lark.exports_list')->toString());
  }

  /**
   * Import single entity.
   *
   * @param string $source_plugin_id
   *   Source plugin id.
   * @param string $uuid
   *   The UUID of the entity to import.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function importEntity(string $source_plugin_id, string $uuid): RedirectResponse {
    $this->importer->importSingleEntityFromSource($source_plugin_id, $uuid);
    return new RedirectResponse(Url::fromRoute('lark.exports_list')->toString());
  }

  /**
   * Import all entities from a single source.
   *
   * @param string $source_plugin_id
   *   The source plugin id.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function importSource(string $source_plugin_id): RedirectResponse {
    $this->importer->importFromSingleSource($source_plugin_id);
    return new RedirectResponse(Url::fromRoute('lark.exports_list')->toString());
  }


  /**
   * List exported entities.
   *
   * @return array
   *   Render array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function build(): array {
    $source_plugins = $this->sourceManager->getInstances();
    $build = ['#attached' => ['library' => ['lark/admin']]];

    $table_header = [
      'status' => ['class' => ['status'], 'data' => $this->t('Status')],
      'entity_type' => ['class' => ['entity-type'], 'data' => $this->t('Entity type')],
      'label' => ['class' => ['label'], 'data' => $this->t('Label')],
      'filepath' => ['class' => ['filepath'], 'data' => $this->t('File')],
      'operations' => ['class' => ['operations'], 'data' => $this->t('Operations')],
    ];

    foreach ($source_plugins as $source) {
      $exports = $this->importer->discoverSourceExports($source);
      $source_root_exports = $this->getRootLevelExports($exports);

      $build['sources'][$source->id()] = [
        '#type' => 'details',
        '#title' => $this->t('Exports in @label', ['@label' => $source->label()]),
        '#description' => $this->t('Exports found in <code>@directory</code>', [
          '@directory' => $source->directory() . DIRECTORY_SEPARATOR,
        ]),
        '#open' => $source->id() === $this->settings->defaultSource(),
        'source_operations' => [
          '#type' => 'operations',
          '#access' => !empty($source_root_exports),
          '#links' => [
            'import_all' => [
              'title' => $this->t('Import all @source_label entities', [
                '@source_label' => $source->label(),
              ]),
              'url' => Url::fromRoute('lark.import_source', ['source_plugin_id' => $source->id()]),
            ],
          ],
        ],
        'no_exports' => [
          '#type' => 'markup',
          '#markup' => $this->t('No exports found.'),
          '#access' => empty($source_root_exports),
        ],
      ];

      foreach ($source_root_exports as $root_uuid => $root_export) {
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
        $build['sources'][$source->id()][$root_uuid] = [
          '#type' => 'details',
          '#title' => Markup::create(strtr("@entity_label <small>@entity_type</small> - @root_uuid", [
            '@entity_label' => $root_exportable->entity()->label(),
            '@entity_type' => $root_exportable->entity()->getEntityTypeId(),
            '@root_uuid' => $root_uuid,
          ])),
          'status_summary' => $status_summary,
          'root_table' => [
            '#type' => 'table',
            '#attributes' => ['class' => ['lark-exports-table']],
            '#header' => $table_header,
            '#rows' => [
              $this->buildExportableTableRow($source, $root_exportable),
            ],
          ],
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
            '#header' => $table_header,
            '#rows' => array_map(function (ExportableInterface $exportable) use ($source) {
              return $this->buildExportableTableRow($source, $exportable);
            }, $dependency_exportables),
          ],
        ];
      }
    }

    return $build;
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

    if (!$exportable->entity()->isNew()) {
      $entity_type = $this->entityTypeManager()->getDefinition($exportable->entity()->getEntityTypeId());
      if ($entity_type->hasLinkTemplate('canonical')) {
        $operations['view'] = [
          'title' => $this->t('View'),
          'url' => $exportable->entity()->toUrl(),
        ];
      }
      if ($exportable->getStatus() === ExportableStatus::OutOfSync) {
        $operations['diff'] = [
          'title' => $this->t('Diff'),
          'url' => Url::fromRoute('lark.diff_viewer', [
            'source_plugin_id' => $source->id(),
            'uuid' => $exportable->entity()->uuid(),
          ]),
        ];
      }
      $operations['export'] = [
        'title' => $this->t('Re-export'),
        'url' => Url::fromRoute('lark.export_single', [
          'source_plugin_id' => $source->id(),
          'entity_type_id' => $exportable->entity()->getEntityTypeId(),
          'entity_id' => $exportable->entity()->id(),
        ]),
      ];
    }

    $operations['import'] = [
      'title' => $exportable->entity()->isNew() ? $this->t('Import') : $this->t('Re-import'),
      'url' => Url::fromRoute('lark.import_single', [
        'source_plugin_id' => $source->id(),
        'uuid' => $exportable->entity()->uuid(),
      ]),
    ];

    return [
      '#type' => 'operations',
      '#links' => $operations,
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
    $relative = str_replace($source->directory() . DIRECTORY_SEPARATOR, '', $exportable->getExportFilepath());
    $status_details = $this->statusBuilder->getStatusRenderDetails($exportable->getStatus());
    return [
      'class' => [
        'lark-exports-row',
        'lark-exports-row--' . $status_details['class_name']
      ],
      'data' => [
        'status' => [
          'class' => ['status'],
          'data' => $status_details['render'],
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
   * Build rows for missing dependencies.
   *
   * @param array $exports
   *   Exports array.
   *
   * @return array
   *   Table rows.
   */
  protected function buildMissingDependencyRows(array $exports): array {
    $missing_dependencies = [];
    foreach ($exports as $export) {
      foreach ($export['_meta']['depends'] as $dependency_uuid => $entity_type) {
        if (!isset($exports[$dependency_uuid])) {
          $missing_dependencies[$dependency_uuid] = $entity_type;
        }
      }
    }

    $missing_dependencies = array_reverse($missing_dependencies);
    $rows = [];
    $status_details = $this->statusBuilder->getStatusRenderDetails(ExportableStatus::NotExported);
    foreach ($missing_dependencies as $uuid => $entity_type) {
      $rows[] = [
        'class' => [
          'lark-exports-row',
          'lark-exports-row--' . $status_details['class_name'],
        ],
        'data' => [
          'status' => [
            'class' => ['status'],
            'data' => $status_details['render'],
          ],
          'entity_type' => [
            'class' => ['entity-type'],
            'data' => $entity_type,
          ],
          'label' => [
            'class' => ['label'],
            'data' => $uuid . '.yml',
          ],
          'filepath' => [
            'class' => ['filepath'],
            'data' => Markup::create("<small><code>FILE NOT FOUND</code></small>"),
          ],
          'operations' => [],
        ],
      ];
    }

    return $rows;
  }

}
