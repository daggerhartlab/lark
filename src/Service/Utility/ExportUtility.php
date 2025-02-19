<?php

namespace Drupal\lark\Service\Utility;

class ExportUtility {

  /**
   * Get only the root-level exports.
   *
   * @param array $exports
   *   Exports array.
   *
   * @return array
   *   Root-level exports.
   */
  public function getRootLevelExports(array $exports): array {
    return array_filter($exports, function ($export, $uuid) use ($exports) {
      foreach ($exports as $other_export) {
        if (isset($other_export['_meta']['depends'][$uuid])) {
          return FALSE;
        }
      }

      return TRUE;
    }, ARRAY_FILTER_USE_BOTH);
  }

}
