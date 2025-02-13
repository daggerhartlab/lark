<?php

namespace Drupal\lark\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\lark\ExportableStatus;
use Drupal\lark\Model\LarkSettings;
use Drupal\lark\Service\AssetFileManager;
use Drupal\lark\Service\ExportableFactoryInterface;
use Drupal\lark\Service\Utility\ExportableStatusBuilder;
use Drupal\lark\Service\ExporterInterface;
use Drupal\lark\Service\SourceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for exporting an entity.
 *
 * @see \Drupal\devel\Routing\RouteSubscriber
 * @see \Drupal\devel\Plugin\Derivative\DevelLocalTask
 */
class EntityExportForm extends FormBase {

  /**
   * EntityExportForm constructor.
   *
   * @param \Drupal\lark\Service\ExporterInterface $entityExporter
   *   The entity exporter service.
   * @param \Drupal\lark\Service\SourceManagerInterface $sourceManager
   *   The Lark source plugin manager service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ExporterInterface $entityExporter,
    protected SourceManagerInterface $sourceManager,
    protected ExportableFactoryInterface $exportableFactory,
    protected ExportableStatusBuilder $statusBuilder,
    protected LarkSettings $settings,
    protected AssetFileManager $assetFileManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get(EntityTypeManagerInterface::class),
      $container->get(ExporterInterface::class),
      $container->get(SourceManagerInterface::class),
      $container->get(ExportableFactoryInterface::class),
      $container->get(ExportableStatusBuilder::class),
      $container->get(LarkSettings::class),
      $container->get(AssetFileManager::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lark_entity_export_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $entity_type_id = $this->getRouteMatch()->getParameters()->keys()[0];
    $entity_id = (int) $this->getRouteMatch()->getParameter($entity_type_id);
    $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($entity_id);
    $exportables = $this->exportableFactory->getEntityExportables($entity_type_id, $entity_id);
    $exportable = $exportables[$entity->uuid()];

    $form['#attached']['library'][] = 'lark/admin';
    $form['source'] = [
      '#type' => 'select',
      '#title' => $this->t('Export Source'),
      '#options' => $this->sourceManager->getOptions(),
      '#default_value' => $exportable->getSource() ? $exportable->getSource()->id() : $this->sourceManager->getDefaultSource()->id(),
      '#required' => TRUE,
      '#weight' => -101,
    ];
    $form['actions'] = [
      '#type' => 'actions',
      '#weight' => -100,
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Export'),
      ],
    ];
    $form['entity_type_id'] = [
      '#type' => 'value',
      '#value' => $entity_type_id,
    ];
    $form['entity_id'] = [
      '#type' => 'value',
      '#value' => $entity_id,
    ];

    $form['exportable_values'] = $this->buildExportableYamls($exportables);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Simplify exportable_values.
    $exportable_values = [];
    foreach ($form_state->getValue('exportable_values') as $uuid => $values) {
      // File assets.
      if ((bool) $values['file_asset']['should_export'] !== $this->settings->shouldExportAssets()) {
        $exportable_values[$uuid]['file_asset_should_export'] = (bool) $values['file_asset']['should_export'];
      }
      if ((bool) $values['file_asset']['should_import'] !== $this->settings->shouldImportAssets()) {
        $exportable_values[$uuid]['file_asset_should_import'] = (bool) $values['file_asset']['should_import'];
      }
    }

    $this->entityExporter->exportEntity(
      $form_state->getValue('source'),
      $form_state->getValue('entity_type_id'),
      (int) $form_state->getValue('entity_id'),
      TRUE,
      $exportable_values,
    );
  }

  /**
   * Returns the loaded structure of the current entity.
   *
   * @param \Drupal\lark\Model\ExportableInterface[] $exportables
   *
   * @return array
   *   Array of page elements to render.
   */
  protected function buildExportableYamls(array $exportables): array {
    $exportables = array_reverse($exportables);
    $build = [
      '#tree' => TRUE,
      'divider' => [
        '#markup' => '<hr>',
      ],
      'summary' => $this->statusBuilder->getExportablesSummary($exportables),
      'lark_exports_table' => [
        '#theme' => 'table',
        '#header' => [
          'icon' => [
            'data' => $this->t('Icon'),
          ],
          'entity_id' => [
            'data' => $this->t('Entity ID'),
          ],
          'entity_type'  => [
            'data' => $this->t('Entity Type'),
          ],
          'bundle' => [
            'data' => $this->t('Bundle'),
          ],
          'label' => [
            'data' => $this->t('Label'),
          ],
          'toggle'  => [
            'data' => $this->t('Toggle'),
          ],
        ],
        '#rows' => [],
      ],
    ];
    foreach ($exportables as $uuid => $exportable) {
      $status_details = $this->statusBuilder->getStatusRenderDetails($exportable->getStatus());

      $depth = $this->calculateExportableDepth($exportables, $uuid);
      $icon['#attributes']['style'] = "margin-left: {$depth}em";

      // Data row.
      $build['lark_exports_table']['#rows'][] = [
        'data' => [
          'icon' => [
            'data' => $status_details['icon'],
          ],
          'entity_id' => [
            'data' => $exportable->entity()->id(),
          ],
          'entity_type' => [
            'data' => $exportable->entity()->getEntityTypeId(),
          ],
          'bundle' => [
            'data' => $exportable->entity()->bundle(),
          ],
          'label' => $exportable->entity()->label(),
          'toggle'  => [
            'data' => [
              '#theme' => 'image',
              '#alt' => 'Toggle row',
              '#attributes' => [
                'src' => '/modules/contrib/lark/assets/icons/file-yaml.png',
                'width' => '35',
                'height' => '35',
              ],
            ],
            'class' => 'lark-toggle-row',
            'data-uuid' => $uuid,
          ],
        ],
      ];
      // Yaml row.
      $build['lark_exports_table']['#rows'][] = [
        'data' => [
          'yaml' => [
            'class' => ['lark-yaml-row', 'lark-yaml-row--' . $uuid],
            'colspan' => count($build['lark_exports_table']['#header']),
            'data' => [
              'heading' => [
                '#type' => 'html_tag',
                '#tag' => 'h2',
                '#value' => $exportable->entity()->label(),
              ],
              'export_details' => [
                '#markup' => "<p><strong>Export Path: </strong> <code>{$exportable->getExportFilename()}</code></p>",
              ],
              'diff_link' => [
                '#type' => 'operations',
                '#access' => $exportable->getStatus() === ExportableStatus::OutOfSync,
                '#links' => [
                  'import_all' => [
                    'title' => $this->t('View diff'),
                    'url' => Url::fromRoute('lark.diff_viewer', [
                      'source_plugin_id' => $exportable->getSource()?->id(),
                      'uuid' => $exportable->entity()->uuid(),
                    ]),
                  ],
                ],
              ],
              'content' => [
                '#markup' => Markup::create("<hr><pre>" . \htmlentities($exportable->toYaml()) . "</pre>"),
                '#weight' => 100,
              ],
            ],
          ],
        ],
      ];


      $build[$uuid] = [
        '#type' => 'details',
        '#title' => "{$status_details['icon']} {$exportable->entity()->getEntityTypeId()} : {$exportable->entity()->id()} : {$exportable->entity()->label()} - <small>{$exportable->entity()->uuid()}</small>",
        '#open' => FALSE,

      ];

      // Handle file options.
      if ($exportable->entity() instanceof FileInterface) {
        /** @var FileInterface $file */
        $file = $exportable->entity();

        // Whether asset is exported.
        $is_exported_msg = $this->t('Asset not exported.');
        if ($exportable->getExportFilepath()) {
          $destination = dirname($exportable->getExportFilepath());
          if ($this->assetFileManager->assetIsExported($exportable->entity(), $destination)) {
            $path = $destination . DIRECTORY_SEPARATOR . $this->assetFileManager->assetExportFilename($exportable->entity());
            $is_exported_msg = $this->t('Asset exported: @path', ['@path' => $path]);
          }
        }

        $should_import_value = $this->settings->shouldImportAssets();
        if ($exportable->hasMetaOption('file_asset_should_import')) {
          $should_import_value = $exportable->getMetaOption('file_asset_should_import');
        }
        $should_export_value = $this->settings->shouldExportAssets();
        if ($exportable->hasMetaOption('file_asset_should_export')) {
          $should_export_value = $exportable->getMetaOption('file_asset_should_export');
        }

        $build[$uuid]['file_asset'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['lark-asset-details-container']],
          'is_exported' => [
            '#type' => 'container',
            'exists' => [
              '#markup' => "<p><em>$is_exported_msg</em></p>",
            ],
          ],
          'should_export' => [
            '#type' => 'radios',
            '#title' => $this->t('Asset Export'),
            '#default_value' => (int) $should_export_value,
            '#options' => [
              0 => $this->t('(@default_desc) Do not export', [
                '@default_desc' => $this->settings->shouldExportAssets() === FALSE ?
                  $this->t('Default') :
                  $this->t('Override')
              ]),
              1 => $this->t('(@default_desc) Export this asset along with the File entity', [
                '@default_desc' => $this->settings->shouldExportAssets() === TRUE ?
                  $this->t('Default') :
                  $this->t('Override')
              ]),
            ],
          ],
          'should_import' => [
            '#type' => 'radios',
            '#title' => $this->t('Asset Import'),
            '#default_value' => (int) $should_import_value,
            '#options' => [
              0 => $this->t('(@default_desc) Do not import', [
                '@default_desc' => $this->settings->shouldImportAssets() === FALSE ? $this->t('Default') : $this->t('Override')
              ]),
              1 => $this->t('(@default_desc) Import this asset along with the File entity', [
                '@default_desc' => $this->settings->shouldImportAssets() === TRUE ? $this->t('Default') : $this->t('Override')
              ]),
            ],
          ],
        ];

        // Thumbnail.
        if (
          str_starts_with($file->getMimeType(), 'image/') &&
          $file->getSize() <= 2048000
        ) {
          $build[$uuid]['file_asset']['thumbnail'] = [
            '#theme' => 'image',
            '#uri' => $file->createFileUrl(FALSE),
            '#title' => $file->label(),
            '#attributes' => ['class' => ['lark-asset-thumbnail-image']],
          ];
        }
      }
    }

    return $build;
  }

  /**
   * Get the number of levels deep the given uuid is in the array of exportables.
   *
   * @param array $exportables
   * @param string $uuid
   *
   * @return int
   */
  private function calculateExportableDepth(array $exportables, string $uuid) : int {
    $depth = 0;
    foreach ($exportables as $exportable) {
      // Don't include itself.
      if ($exportable->entity()->uuid() === $uuid) {
        continue;
      }

      if (array_key_exists($uuid, $exportable->getDependencies())) {
        $depth += 1 + $this->calculateExportableDepth($exportables, $exportable->entity()->uuid());
      }
    }

    return $depth;
  }

}
