<?php

use Drupal\Component\Utility\Html;
use Drupal\Core\Template\Attribute;

/**
 * Implements template_preprocess_HOOK().
 */
function template_preprocess_toggle_row_table(&$variables) {
  template_preprocess_table($variables);
  $variables['#attached']['library'][] = 'lark/toggle-row-table';

  // Add the header for the toggle handle cell.
  $variables['header']['toggle'] = [
    'tag' => $variables['header'][array_key_first($variables['header'])]['tag'] ?? 'th',
    'attributes' => new Attribute(),
    'content' => [
      '#markup' => $variables['toggle_handle_header'] ?? '',
    ],
  ];

  // Get our multiple handle state values.
  $toggle_handle_open = $variables['toggle_handle_open'] ?? ['#markup' => t('Open')];
  $toggle_handle_close = $variables['toggle_handle_close'] ?? ['#markup' => t('Close')];

  $rows = [];
  // Create a pair of rows for each row passed into the table. The row from the
  // table is the "toggle_row", the row we created is the "details_row". The
  // last cell of the toggle_row will become the only cell in the details_row.
  // We add a "handle" to the toggle_row, that when clicked will toggle
  // (show/hide) the details_row.
  foreach ($variables['rows'] as $i => $toggle_row) {
    // Create a unique ID for the pair of rows.
    $uuid = Html::getUniqueId('toggle-row-table-'. $i);

    // Last cell in the toggle row is the content for the details row.
    $content = array_pop($toggle_row['cells']);

    // The toggle row is adjusted to contain the details needed to toggle the
    // details row.
    $toggle_row['attributes'] = $toggle_row['attributes'] ?? new Attribute([]);
    $toggle_row['attributes']->addClass('toggle-row-table--toggle-row');
    $toggle_row['attributes']->offsetSet('data-uuid', $uuid);

    // Add the toggle handle at the end of the toggle row. Clicking on one of
    // the handles will toggle the details row.
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
      ]
    ];

    // Build the details row from the cell we pull out of the toggle_row.
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
      ]
    ];

    $rows[] = $toggle_row;
    $rows[] = $details_row;
  }

  $variables['rows'] = $rows;
}
