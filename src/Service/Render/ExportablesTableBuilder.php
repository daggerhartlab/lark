<?php

namespace Drupal\lark\Service\Render;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\lark\Model\ExportableStatus;
use Drupal\lark\Model\ExportableInterface;
use Drupal\lark\Service\ExportableFactoryInterface;
use Drupal\lark\Service\MetaOptionManager;

/**
 * Build the render array for a table of Exportables.
 */
class ExportablesTableBuilder {

  use StringTranslationTrait;

  /**
   * ExportablesTableBuilder constructor.
   *
   * @param \Drupal\lark\Service\Render\ExportableStatusBuilder $statusBuilder
   *   Exportable status builder.
   * @param \Drupal\lark\Service\MetaOptionManager $metaOptionManager
   *   Meta option manager.
   * @param \Drupal\lark\Service\ExportableFactoryInterface $exportableFactory
   *   Exportable factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Module handler.
   */
  public function __construct(
    protected ExportableStatusBuilder $statusBuilder,
    protected MetaOptionManager $metaOptionManager,
    protected ExportableFactoryInterface $exportableFactory,
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Build the table render array.
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
   *   Render array for the table.
   */
  public function table(array $exportables, array &$form, FormStateInterface $form_state, string $tree_name): array {
    $path = $this->moduleHandler->getModule('lark')->getPath();

    return [
      '#theme' => 'toggle_row_table',
      '#header' => [
        'icon' => [
          'class' => ['status-icon'],
          'data' => $this->t('Status')
        ],
        'entity_id' => $this->t('Entity ID'),
        'entity_type' => $this->t('Entity Type'),
        'bundle' => $this->t('Bundle'),
        'label' => $this->t('Label'),
      ],
      '#rows' => $this->rows($exportables, $form, $form_state, $tree_name),
      '#attributes' => [
        'class' => ['lark-exportables-table-form'],
      ],
      '#attached' => [
        'library' => ['lark/admin']
      ],
      '#toggle_handle_header' => $this->t('Toggle'),
      '#toggle_handle_open' => [
        '#theme' => 'image',
        '#alt' => 'Toggle row',
        '#attributes' => [
          'src' => Url::fromUri("base:/{$path}/assets/icons/file-yaml.png")->toString(),
          'width' => '35',
          'height' => '35',
        ],
      ],
      '#toggle_handle_close' => [
        '#theme' => 'image',
        '#alt' => 'Toggle row',
        '#attributes' => [
          'src' => Url::fromUri("base:/{$path}/assets/icons/file-yaml.png")->toString(),
          'width' => '35',
          'height' => '35',
        ],
      ],
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
  protected function rows(array $exportables, array &$form, FormStateInterface $form_state, string $tree_name) {
    $rows = [];
    foreach ($exportables as $exportable) {
      $rows[] = $this->row($exportable, $form, $form_state, [$tree_name]);
    }

    return $rows;
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
  protected function row(ExportableInterface $exportable, array &$form, FormStateInterface $form_state, array $render_parents = []): array {
    $entity = $exportable->entity();
    $status_details = $this->statusBuilder->getStatusRenderDetails($exportable->getStatus());

    // Form & Yaml row.
    $details_row = [
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
    ];

    foreach ($this->metaOptionManager->getInstances() as $meta_option) {
      if ($meta_option->applies($exportable->entity())) {
        $details_row[$meta_option->id()] = $meta_option->formElement($exportable, $form, $form_state, $render_parents);
      }
    }

    // Toggle row with details row as the last cell.
    return [
      'data' => [
        'icon' => [
          'class' => ['status-icon'],
          'data' => $status_details['icon_render']
        ],
        'entity_id' => $entity->id(),
        'entity_type' => $entity->getEntityTypeId(),
        'bundle' => $entity->bundle(),
        'label' => $entity->label(),
        'details_row' => [
          'data' => $details_row,
        ],
      ],
    ];
  }

}
