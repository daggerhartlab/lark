<?php

/**
 * @file
 * Post update functions for Lark.
 */

declare(strict_types=1);

/**
 * Store the lark.settings asset options as booleans.
 */
function lark_post_update_settings_asset_booleans(): void {
  $config = \Drupal::configFactory()->getEditable('lark.settings');
  foreach (['should_export_assets', 'should_import_assets'] as $key) {
    $config->set($key, (bool) $config->get($key));
  }
  $config->save();
}
