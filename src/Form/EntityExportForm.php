<?php

namespace Drupal\lark\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\lark\ExportableStatus;
use Drupal\lark\Model\ExportableInterface;
use Drupal\lark\Model\LarkSettings;
use Drupal\lark\Service\AssetFileManager;
use Drupal\lark\Service\MetaOptionManager;
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
   * MetaOption constructor.
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
    protected MetaOptionManager $metaOptionManager,
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
      $container->get(MetaOptionManager::class),
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
    $entity_type_id = $this->getRouteMatch()->getRouteObject()->getOption('_lark_entity_type_id');
    $entity = $this->getRouteMatch()->getParameter($entity_type_id);
    if (is_numeric($entity)) {
      $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($entity);
    }

    $exportables = $this->exportableFactory->getEntityExportables($entity_type_id, $entity->id());
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
    $form['entity_type_id'] = [
      '#type' => 'value',
      '#value' => $entity_type_id,
    ];
    $form['entity_id'] = [
      '#type' => 'value',
      '#value' => $entity->id(),
    ];
    $form['actions'] = [
      '#type' => 'actions',
      '#weight' => -100,
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Export'),
      ],
    ];

    $form['export_form_container'] = [
      '#type' => 'container',
      'divider' => [
        '#markup' => '<hr>',
      ],
      'summary' => $this->statusBuilder->getExportablesSummary($exportables),
      'table' => $this->buildExportableYamls($exportables, $form, $form_state, 'export_form_values')
    ];

    return $form;
  }

  /**
   * Returns the loaded structure of the current entity.
   *
   * @param \Drupal\lark\Model\ExportableInterface[] $exportables
   *
   * @return array
   *   Array of page elements to render.
   */
  protected function buildExportableYamls(array $exportables, array &$form, FormStateInterface $form_state, string $tree_name): array {
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
      $new_rows = $this->exportableTableRows($exportable, $form, $form_state, [$tree_name]);
      $table['#rows'] = array_merge($table['#rows'], $new_rows);
    }

    return [
      '#type' => 'container',
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
  private function exportableTableRows(ExportableInterface $exportable, array &$form, FormStateInterface $form_state, array $render_parents = []): array {
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
          'yaml_export' => [
            '#type' => 'html_tag',
            '#tag' => 'pre',
            '#value' => Markup::create(\htmlentities($exportable->toYaml())),
            '#attributes' => [
              'class' => ['lark-yaml-export-pre'],
            ],
            '#weight' => 100,
          ]
        ],
      ],
    ];

    foreach ($this->metaOptionManager->getInstances() as $meta_option) {
      if ($meta_option->applies($exportable->entity())) {
        $form_row['form']['data'][$meta_option->id()] = $meta_option->formElement($exportable, $form, $form_state, $render_parents);
      }
    }

    return [
      $data_row,
      $form_row,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $submitted_values = $form_state->getValues()['export_form_values'];
    $export_values = [];
    foreach ($submitted_values as $uuid => $values) {
      $exportable = $this->exportableFactory->createFromUuid($uuid);

      foreach ($this->metaOptionManager->getInstances() as $form_plugin) {
        // Ensure the plugin applies to the entity.
        if (!$form_plugin->applies($exportable->entity())) {
          continue;
        }

        // Ensure it has submitted values.
        if (!array_key_exists($form_plugin->id(), $values)) {
          $values[$form_plugin->id()] = [];
        }

        // Allow the plugin to record the values to the export.
        $plugin_values = $form_plugin->processFormValues($values[$form_plugin->id()], $exportable, $form_state);
        if ($plugin_values) {
          $export_values[$uuid][$form_plugin->id()] = $plugin_values;
        }
      }
    }

    $this->entityExporter->exportEntity(
      $form_state->getValue('source'),
      $form_state->getValue('entity_type_id'),
      (int) $form_state->getValue('entity_id'),
      TRUE,
      $export_values,
    );
  }

}
