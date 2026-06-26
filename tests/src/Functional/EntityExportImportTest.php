<?php

namespace Drupal\Tests\lark\Functional;

use Drupal\lark\Entity\LarkSource;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the export and import tab UI on content entities.
 *
 * @group lark
 */
// Required by Drupal 11.3.0+: BrowserTestBase tests must isolate to avoid state leaks.
// Will become a hard requirement in Drupal 12.0.0.
#[RunTestsInSeparateProcesses]
class EntityExportImportTest extends BrowserTestBase {

  protected static $modules = ['lark', 'node', 'user', 'field', 'text', 'filter', 'block'];
  protected $defaultTheme = 'stark';

  /**
   * Disable strict config schema to avoid lark.settings integer/string mismatch.
   */
  protected $strictConfigSchema = FALSE;

  protected function setUp(): void {
    parent::setUp();
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    $export_dir = $this->siteDirectory . '/lark-functional-test-exports';
    LarkSource::create([
      'id' => 'functional_test_source',
      'label' => 'Functional Test Source',
      'directory' => $export_dir,
    ])->save();

    \Drupal::configFactory()->getEditable('lark.settings')
      ->set('default_source', 'functional_test_source')
      ->save();

    // Place the local tasks block so tabs appear in rendered pages.
    $this->drupalPlaceBlock('local_tasks_block');

    $admin = $this->drupalCreateUser([
      'lark export entity',
      'lark import entity',
      'access content',
      'create article content',
      'edit any article content',
      'administer nodes',
    ]);
    $this->drupalLogin($admin);
  }

  public function testLarkTabAppearsOnNodeViewPage(): void {
    $node = $this->drupalCreateNode(['type' => 'article', 'title' => 'Tab Test']);
    // The Lark tab is attached to the canonical route (entity.node.canonical).
    $this->drupalGet('/node/' . $node->id());
    $this->assertSession()->statusCodeEquals(200);
    // The Lark tab should appear in the local tasks block.
    $this->assertSession()->linkExists('Lark');
  }

  public function testExportFormLoadsForNode(): void {
    $node = $this->drupalCreateNode(['type' => 'article', 'title' => 'Export Form Test']);
    // Navigate to the lark export tab for this node.
    $this->drupalGet('/lark/export/node/' . $node->id());
    $this->assertSession()->statusCodeEquals(200);
  }

  public function testExportFormSubmissionCreatesYamlFile(): void {
    $node = $this->drupalCreateNode(['type' => 'article', 'title' => 'Will Export']);
    $this->drupalGet('/lark/export/node/' . $node->id());

    // Submit the export form. The button label is 'Export to Source'.
    $this->submitForm([], 'Export to Source');

    // BrowserTestBase::$siteDirectory is an absolute path (set via realpath() in
    // the test harness) so LarkSource::directoryProcessed() won't prepend DRUPAL_ROOT.
    $export_dir = $this->siteDirectory . '/lark-functional-test-exports';
    $yaml_path = $export_dir . '/node/article/' . $node->uuid() . '.yml';
    $this->assertFileExists($yaml_path, 'Submitting the export form must create the YAML file on disk');
  }

  public function testExportRequiresPermission(): void {
    $this->drupalLogout();
    $limited_user = $this->drupalCreateUser(['access content']);
    $this->drupalLogin($limited_user);

    $node = $this->drupalCreateNode(['type' => 'article', 'title' => 'Forbidden Export']);
    $this->drupalGet('/lark/export/node/' . $node->id());
    $this->assertSession()->statusCodeEquals(403);
  }

}
