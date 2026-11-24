<?php

declare(strict_types=1);

namespace Drupal\lark\Service\Render;

use Drupal\Component\Utility\Html;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Theme\ThemePreprocess;

/**
 * Initial preprocess for the toggle_row_table theme hook.
 */
class ToggleRowTablePreprocess {

  use StringTranslationTrait;

  public function __construct(
    protected ThemePreprocess $themePreprocess,
  ) {}

  /**
   * Prepares variables for toggle_row_table templates.
   *
   * Pairs each table row with a details row built from its last cell, and adds
   * a handle to the row that shows and hides the details row.
   *
   * @param array $variables
   *   The table theme variables, plus:
   *   - toggle_handle_header: Content for the header cell above the handles.
   *   - toggle_handle_open: Render array for the open handle.
   *   - toggle_handle_close: Render array for the close handle.
   */
  public function preprocess(array &$variables): void {
    $this->themePreprocess->preprocessTable($variables);
    $variables['#attached']['library'][] = 'lark/toggle-row-table';

    // Header cell above the toggle handles.
    $variables['header']['toggle'] = [
      'tag' => $variables['header'][array_key_first($variables['header'])]['tag'] ?? 'th',
      'attributes' => new Attribute(),
      'content' => [
        '#markup' => $variables['toggle_handle_header'] ?? '',
      ],
    ];

    $toggle_handle_open = $variables['toggle_handle_open'] ?? ['#markup' => $this->t('Open')];
    $toggle_handle_close = $variables['toggle_handle_close'] ?? ['#markup' => $this->t('Close')];

    $rows = [];
    foreach ($variables['rows'] as $i => $toggle_row) {
      // Shared ID for the pair of rows.
      $uuid = Html::getUniqueId('toggle-row-table-' . $i);

      // The last cell becomes the content of the details row.
      $content = array_pop($toggle_row['cells']);

      $toggle_row['attributes'] = $toggle_row['attributes'] ?? new Attribute([]);
      $toggle_row['attributes']->addClass('toggle-row-table--toggle-row');
      $toggle_row['attributes']->offsetSet('data-uuid', $uuid);

      $toggle_row['cells']['toggle_handle'] = [
        'tag' => 'td',
        'content' => [
          'open' => [
            '#type' => 'container',
            '#attributes' => [
              'class' => ['toggle-row-table--toggle-handle', 'toggle-row-table--toggle-handle--handle-open'],
            ],
            'open' => $toggle_handle_open,
          ],
          'close' => [
            '#type' => 'container',
            '#attributes' => [
              'class' => ['toggle-row-table--toggle-handle', 'toggle-row-table--toggle-handle--handle-close'],
            ],
            'close' => $toggle_handle_close,
          ],
        ],
      ];

      $details_row = [
        'attributes' => new Attribute([
          'class' => ['toggle-row-table--details-row'],
        ]),
        'cells' => [
          'content' => [
            'attributes' => new Attribute([
              'colspan' => count($toggle_row['cells']),
              'class' => ['toggle-row-table--details', 'toggle-row-table--details--' . $uuid],
            ]),
          ] + $content,
        ],
      ];

      $rows[] = $toggle_row;
      $rows[] = $details_row;
    }

    $variables['rows'] = $rows;
  }

}
