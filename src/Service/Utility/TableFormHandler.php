<?php

namespace Drupal\lark\Service\Utility;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\lark\ExportableStatus;
use Drupal\lark\Model\ExportableInterface;
use Drupal\lark\Service\ExportableFactoryInterface;
use Drupal\lark\Service\MetaOptionManager;

class TableFormHandler {

  use StringTranslationTrait;

  public function __construct(
    protected ExportableStatusBuilder $statusBuilder,
    protected MetaOptionManager $metaOptionManager,
    protected ExportableFactoryInterface $exportableFactory,
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * @param array $exportables
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param string $tree_name
   *
   * @return array
   */
  public function tablePopulated(array $exportables, array &$form, FormStateInterface $form_state, string $tree_name): array {
    $table = $this->table();
    $table['#rows'] = $this->rows($exportables, $form, $form_state, $tree_name);
    return $table;
  }

  /**
   * @return array
   */
  public function table(): array {
    return [
      '#tree' => TRUE,
      '#theme' => 'table',
      '#header' => $this->headers(),
      '#rows' => [],
      '#attributes' => [
        'class' => ['lark-exportables-table-form'],
      ],
      '#attached' => [
        'library' => ['lark/admin']
      ],
    ];
  }

  /**
   * @return array
   */
  public function headers(): array {
    return [
      'icon' => [
        'class' => ['status-icon'],
        'data' => $this->t('Status')
      ],
      'entity_id' => $this->t('Entity ID'),
      'entity_type' => $this->t('Entity Type'),
      'bundle' => $this->t('Bundle'),
      'label' => $this->t('Label'),
      'toggle'  => $this->t('Toggle'),
    ];
  }

  /**
   * Get table rows for the give exportables.
   *
   * @param \Drupal\lark\Model\ExportableInterface[] $exportables
   *   Array of Exportables.
   * @param array $form
   *   Form this table is being added to.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current form state.
   * @param string $tree_name
   *   Name of the element this table will be placed within. This is needed for
   *   solving renderer bugs involving tables.
   *
   * @return array
   */
  public function rows(array $exportables, array &$form, FormStateInterface $form_state, string $tree_name) {
    $rows = [];
    foreach ($exportables as $exportable) {
      $new_rows = $this->exportableTableRows($exportable, $form, $form_state, [$tree_name]);
      $rows = array_merge($rows, $new_rows);
    }

    return $rows;
  }

  /**
   * @param string $tree_name
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getSubmittedMetaOptionOverrides(string $tree_name, FormStateInterface $form_state): array {
    $submitted_values = $form_state->getValues()[$tree_name] ?? [];
    $overrides = [];
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
          $overrides[$uuid][$form_plugin->id()] = $plugin_values;
        }
      }
    }
    return $overrides;
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
    $path = $this->moduleHandler->getModule('lark')->getPath();

    // Data row.
    $data_row = [
      'class' => ['lark-toggle-row'],
      'data' => [
        'icon' => [
          'class' => ['status-icon'],
          'data' => $status_details['icon_render']
        ],
        'entity_id' => $entity->id(),
        'entity_type' => $entity->getEntityTypeId(),
        'bundle' => $entity->bundle(),
        'label' => $entity->label(),
        'toggle'  => [
          'class' => ['lark-toggle-handle'],
          'data-uuid' => $uuid,
          'data' => [
            'icon' => [
              '#theme' => 'image',
              '#alt' => 'Toggle row',
              '#attributes' => [
                'src' => Url::fromUri("base:/{$path}/assets/icons/file-yaml.png")->toString(),
                'width' => '35',
                'height' => '35',
              ],
            ],
          ],
        ],
      ],
    ];

    // Form & Yaml row.
    $form_row = [
      'form' => [
        'class' => ['lark-toggle-details-row', 'lark-toggle-details-row--' . $uuid],
        'colspan' => count($this->headers()),
        'data' => [
          'heading' => [
            '#type' => 'html_tag',
            '#tag' => 'h2',
            '#value' => $entity->label(),
          ],
          'export_details' => [
            '#markup' => "<p><strong>Export Path: </strong> <code>{$exportable->getFilename()}</code></p>",
          ],
          'diff_link' => [
            '#type' => 'operations',
            '#access' => $exportable->getStatus() === ExportableStatus::OutOfSync,
            '#links' => [
              'import_all' => [
                'title' => $this->t('View diff'),
                'url' => $exportable->entity()->toUrl('lark-diff'),
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

}
