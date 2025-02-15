<?php

namespace Drupal\lark\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\lark\ExportableStatus;
use Drupal\lark\Model\ExportableInterface;
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

    $form['exportable_values_container'] = [
      '#type' => 'container',
      'divider' => [
        '#markup' => '<hr>',
      ],
      'summary' => $this->statusBuilder->getExportablesSummary($exportables),
      'table' => $this->buildExportableYamls($exportables, 'exportable_values')
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    ddsm($form_state->getValues(), $form_state->getUserInput());
    return;
    // Simplify exportable_values.
    $exportable_values = [];
    foreach ($form_state->getValue('exportable_values') as $uuid => $values) {
      // File assets.
      if ((bool) $values['file_asset_should_export'] !== $this->settings->shouldExportAssets()) {
        $exportable_values[$uuid]['file_asset_should_export'] = (bool) $values['file_asset_should_export'];
      }
      if ((bool) $values['file_asset_should_import'] !== $this->settings->shouldImportAssets()) {
        $exportable_values[$uuid]['file_asset_should_import'] = (bool) $values['file_asset_should_import'];
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
  protected function buildExportableYamls(array $exportables, string $tree_name): array {
    $exportables = array_reverse($exportables);

    $table = [
      '#tree' => TRUE,
      '#theme' => 'table',
      '#header' => [
        'icon' => $this->t('Status'),
        'entity_id' => $this->t('Entity ID'),
        'entity_type' => $this->t('Entity Type'),
        'bundle' => $this->t('Bundle'),
        'label' => $this->t('Label'),
        'toggle'  => $this->t('Toggle'),
      ],
      '#rows' => [],
    ];

    foreach ($exportables as $exportable) {
      $table['#rows'] = array_merge($table['#rows'], $this->exportableTableRows($exportable, $exportables, [$tree_name]));
    }

    // This container contains a hidden field that registers the $tree_name with
    // the $form_state. This trick allows our custom-rendered radios to be found
    // in $form_state->getValue($tree_name);
    // @link https://www.drupal.org/project/drupal/issues/3246825
    // @see ::fixNestedRadios().
    return [
      '#type' => 'container',
      $tree_name => [
        '#type' => 'hidden',
      ],
      'table' => $table,
    ];
  }

  /**
   * Make the form rows for the given exports.
   *
   * @param \Drupal\lark\Model\ExportableInterface $exportable
   *   Exportable to create render rows for.
   * @param array $exportables
   *   All exportables in the current context.
   *
   * @return array
   */
  private function exportableTableRows(ExportableInterface $exportable, array $exportables = [], array $render_parents = []): array {
    $entity = $exportable->entity();
    $uuid = $exportable->entity()->uuid();

    $status_details = $this->statusBuilder->getStatusRenderDetails($exportable->getStatus());

    // Data row.
    $data_row = [
      'icon' => $status_details['icon'],
      'entity_id' => $entity->id(),
      'entity_type' => $entity->getEntityTypeId(),
      'bundle' => $entity->bundle(),
      'label' => $entity->label(),
      'toggle'  => [
        'class' => ['lark-toggle-row'],
        'data-uuid' => $uuid,
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
      ],
    ];

    // Form & Yaml row.
    $form_row = [
      'form' => [
        'class' => ['lark-yaml-row', 'lark-yaml-row--' . $uuid],
        'colspan' => count($data_row),
        'data' => [
          'heading' => [
            '#type' => 'html_tag',
            '#tag' => 'h2',
            '#value' => $entity->label(),
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
          'yaml' => [
            '#markup' => Markup::create("<hr><pre>" . \htmlentities($exportable->toYaml()) . "</pre>"),
            '#weight' => 100,
          ],
        ],
      ],
    ];

    // Handle file options.
    if ($exportable->entity() instanceof FileInterface) {
      $form_row['form']['data']['file_asset'] = $this->makeAssetForm($exportable, $render_parents);
    }

    return [
      $data_row,
      $form_row,
    ];
  }

  /**
   * Build the file asset management form.
   *
   * @param \Drupal\lark\Model\ExportableInterface $exportable
   * @param array $render_parents
   *
   * @return array
   */
  private function makeAssetForm(ExportableInterface $exportable, array $render_parents = []): array {
    /** @var FileInterface $file */
    $file = $exportable->entity();
    $uuid = $file->uuid();

    if (!($file instanceof FileInterface)) {
      return [];
    }

    // Whether asset is exported.
    $is_exported_msg = $this->t('Asset not exported.');
    if ($exportable->getExportFilepath()) {
      $destination = dirname($exportable->getExportFilepath());
      if ($this->assetFileManager->assetIsExported($file, $destination)) {
        $path = $destination . DIRECTORY_SEPARATOR . $this->assetFileManager->assetExportFilename($file);
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

    $render_parents[] = $uuid;
    $container = [
      '#type' => 'container',
      '#attributes' => ['class' => ['lark-asset-details-container']],
      'is_exported' => [
        '#type' => 'container',
        'exists' => [
          '#markup' => "<p><em>$is_exported_msg</em></p>",
        ],
      ],
      // Should export.
      'file_asset_should_export' => $this->fixNestedRadios([
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
      ], 'file_asset_should_export', $render_parents),
      // Should import.
      'file_asset_should_import' => $this->fixNestedRadios([
        '#type' => 'radios',
        '#title' => $this->t('Asset Import'),
        '#description' => $this->t('HEREHRE'),
        '#default_value' => (int) $should_import_value,
        '#options' => [
          '0' => $this->t('(@default_desc) Do not import', [
            '@default_desc' => $this->settings->shouldImportAssets() === FALSE ? $this->t('Default') : $this->t('Override')
          ]),
          '1' => $this->t('(@default_desc) Import this asset along with the File entity', [
            '@default_desc' => $this->settings->shouldImportAssets() === TRUE ? $this->t('Default') : $this->t('Override')
          ]),
        ],
      ], 'file_asset_should_import', $render_parents),
    ];

    // Thumbnail.
    if (str_starts_with($file->getMimeType(), 'image/') && $file->getSize() <= 2048000) {
      $container['thumbnail'] = [
        '#theme' => 'image',
        '#uri' => $file->createFileUrl(FALSE),
        '#title' => $file->label(),
        '#attributes' => ['class' => ['lark-asset-thumbnail-image']],
      ];
    }

    return $container;
  }

  /**
   * Takes a normal render array for a radios element and makes it work within
   * a rendered table element. This solves a core Drupal bug where Radios are
   * not rendered at all within a table.
   *
   * @link https://www.drupal.org/project/drupal/issues/3246825
   *
   * @param array $radios
   *   Normal radios render array.
   *
   * @return array
   *   Fixed render array with child Radio (singular) elements.
   */
  protected function fixNestedRadios(array $radios, string $render_name, array $render_parents = []): array {
    // Build the <input> element names and ids we'll need.
    $name_parents = $render_parents;
    $first_name = array_shift($name_parents);
    $parents_name = $first_name;
    if ($parents_name && $name_parents) {
      $parents_name .= '[' . implode('][', $name_parents) . ']';
    }
    $child_name = $parents_name ?
      $parents_name . "[$render_name]" :
      $render_name;

    $radios['#id'] = $radios['#id'] ?? Html::getUniqueId($child_name);
    $radios['#title_display'] = $radios['#title_display'] ?? 'visible';
    $radios['#description_display'] = $radios['#description_display'] ?? 'visible';
    $radios['#default_value'] = $radios['#default_value'] ?? FALSE;
    $radios['#attributes'] = $radios['#attributes'] ?? [];
    $radios['#parents'] = $render_parents;

    // Render each of the radios options as a single radio element. Neither
    // $form nor $form_state are actually used in this process, just required.
    $form = [];
    $form_state = new FormState();
    $radios = Element\Radios::processRadios($radios, $form_state, $form);

    foreach (Element::children($radios) as $index) {
      // Radios::processRadios() doesn't set the #value field for the child radio
      // elements, but later the Radio::preRenderRadio() method will expect it. We
      // can set these values from the $radios #default_value if needed.
      // - '#return_value' is the "value='123'" attribute for the form element.
      // - '#value' is the over-all value of the radios group of elements.
      $radios[$index]['#value'] = $radios[$index]['#value'] ?? $radios['#default_value'];

      // Some other part of the rendering process isn't working, and this field
      // rendered as an <input> ends up not having a "name" attribute.
      $radios[$index]['#name'] = $child_name;
    }

    return $radios;
  }

}
