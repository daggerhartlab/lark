<?php

declare(strict_types=1);

namespace Drupal\Tests\lark\Traits;

use Composer\InstalledVersions;

/**
 * Skips a test when an optional contrib module isn't installed.
 *
 * Lark integrates with contrib modules it doesn't require. They are in
 * require-dev, so CI always has them; a local checkout may not.
 */
trait RequiresModuleTrait {

  /**
   * Marks the test skipped unless all the modules are installed.
   *
   * Call before parent::setUp(), which fails on a missing module.
   *
   * @param string ...$modules
   *   Machine names of modules packaged as drupal/<name>.
   */
  protected function requireModules(string ...$modules): void {
    $missing = array_filter($modules, fn (string $module) => !InstalledVersions::isInstalled("drupal/$module"));
    if ($missing) {
      $this->markTestSkipped('Requires ' . implode(', ', $missing) . '.');
    }
  }

}
