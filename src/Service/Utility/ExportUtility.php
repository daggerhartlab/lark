<?php

namespace Drupal\lark\Service\Utility;

/**
 * Utility for ExportArray operations.
 */
class ExportUtility {

  /**
   * Get only the root-level exports.
   *
   * @param \Drupal\lark\Model\ExportArray[] $exports
   *   Exports array.
   *
   * @return array
   *   Root-level exports.
   */
  public function getRootLevelExports(array $exports): array {
    return array_filter($exports, function ($export, $uuid) use ($exports) {
      foreach ($exports as $other_export) {
        if ($other_export->hasDependency($uuid)) {
          return FALSE;
        }
      }

      return TRUE;
    }, ARRAY_FILTER_USE_BOTH);
  }

}
