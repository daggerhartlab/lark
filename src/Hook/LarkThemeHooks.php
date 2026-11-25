<?php

declare(strict_types=1);

namespace Drupal\lark\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\lark\Service\Render\ToggleRowTablePreprocess;

/**
 * Theme hook implementations for Lark.
 */
class LarkThemeHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'toggle_row_table' => [
        'variables' => [
          'header' => NULL,
          'rows' => NULL,
          'footer' => NULL,
          'attributes' => [],
          'caption' => NULL,
          'colgroups' => [],
          'sticky' => FALSE,
          'responsive' => TRUE,
          'empty' => '',
          'toggle_handle_header' => NULL,
          'toggle_handle_open' => NULL,
          'toggle_handle_close' => NULL,
        ],
        'initial preprocess' => ToggleRowTablePreprocess::class . ':preprocess',
      ],
    ];
  }

}
