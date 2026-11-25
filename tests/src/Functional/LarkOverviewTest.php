<?php

declare(strict_types=1);

namespace Drupal\Tests\lark\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Lark overview page.
 */
#[Group('lark')]
#[RunTestsInSeparateProcesses]
class LarkOverviewTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['lark'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The overview page shows the README.
   */
  public function testOverviewShowsReadme(): void {
    $this->drupalLogin($this->drupalCreateUser(['lark administer configuration']));
    $this->drupalGet('/admin/lark');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('xpath', '//h2[normalize-space()="Requirements"]');
  }

}
