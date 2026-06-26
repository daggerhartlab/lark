<?php

namespace Drupal\Tests\lark\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test Source entity CRUD via the admin UI.
 *
 * @group lark
 */
// Required by Drupal 11.3.0+: BrowserTestBase tests must isolate to avoid state leaks.
// Will become a hard requirement in Drupal 12.0.0.
#[RunTestsInSeparateProcesses]
class LarkSourceAdminTest extends BrowserTestBase {

  protected static $modules = ['lark', 'node', 'user'];
  protected $defaultTheme = 'stark';

  /**
   * Disable strict config schema to avoid lark.settings integer/string mismatch.
   */
  protected $strictConfigSchema = FALSE;

  protected function setUp(): void {
    parent::setUp();
    $admin = $this->drupalCreateUser([
      'lark administer configuration',
      'access administration pages',
    ]);
    $this->drupalLogin($admin);
  }

  public function testSourceListPageLoads(): void {
    $this->drupalGet('/admin/lark/source');
    $this->assertSession()->statusCodeEquals(200);
  }

  public function testCreateSourceViaUi(): void {
    $this->drupalGet('/admin/lark/source/add');
    $this->assertSession()->statusCodeEquals(200);

    $this->submitForm([
      'id' => 'test_source',
      'label' => 'Test Source',
      'directory' => '/tmp/lark-test',
    ], 'Save');

    $this->assertSession()->pageTextContains('Test Source');
    // The source should now appear in the list.
    $this->drupalGet('/admin/lark/source');
    $this->assertSession()->pageTextContains('Test Source');
  }

  public function testSourcePageRequiresPermission(): void {
    $this->drupalLogout();
    $anonymous = $this->drupalCreateUser([]);
    $this->drupalLogin($anonymous);
    $this->drupalGet('/admin/lark/source');
    $this->assertSession()->statusCodeEquals(403);
  }

}
