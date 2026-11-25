<?php

namespace Drupal\Tests\lark\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Source entity CRUD via the admin UI.
 *
 * Runs in separate processes to avoid state leaks, which Drupal 11.3.0+
 * requires of BrowserTestBase tests and 12.0.0 will enforce.
 */
#[Group('lark')]
#[RunTestsInSeparateProcesses]
class LarkSourceAdminTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['lark', 'node', 'user'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $admin = $this->drupalCreateUser([
      'lark administer configuration',
      'access administration pages',
    ]);
    $this->drupalLogin($admin);
  }

  /**
   * Tests that the source list page loads.
   */
  public function testSourceListPageLoads(): void {
    $this->drupalGet('/admin/lark/source');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests that a source created via the add form appears in the list.
   */
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

  /**
   * Tests that the source list is denied without the admin permission.
   */
  public function testSourcePageRequiresPermission(): void {
    $this->drupalLogout();
    $anonymous = $this->drupalCreateUser([]);
    $this->drupalLogin($anonymous);
    $this->drupalGet('/admin/lark/source');
    $this->assertSession()->statusCodeEquals(403);
  }

}
