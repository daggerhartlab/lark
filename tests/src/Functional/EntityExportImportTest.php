<?php

namespace Drupal\Tests\lark\Functional;

use Drupal\lark\Entity\LarkSource;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the export and import tab UI on content entities.
 *
 * Runs in separate processes to avoid state leaks, which Drupal 11.3.0+
 * requires of BrowserTestBase tests and 12.0.0 will enforce.
 */
#[Group('lark')]
#[RunTestsInSeparateProcesses]
class EntityExportImportTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'lark',
    'node',
    'user',
    'field',
    'text',
    'filter',
    'block',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
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

  /**
   * Tests that the Lark tab appears on the node view page.
   */
  public function testLarkTabAppearsOnNodeViewPage(): void {
    $node = $this->drupalCreateNode(['type' => 'article', 'title' => 'Tab Test']);
    // The Lark tab is attached to the canonical route (entity.node.canonical).
    $this->drupalGet('/node/' . $node->id());
    $this->assertSession()->statusCodeEquals(200);
    // The Lark tab should appear in the local tasks block.
    $this->assertSession()->linkExists('Lark');
  }

  /**
   * Tests that the export form loads for a node.
   */
  public function testExportFormLoadsForNode(): void {
    $node = $this->drupalCreateNode(['type' => 'article', 'title' => 'Export Form Test']);
    // Navigate to the lark export tab for this node.
    $this->drupalGet('/lark/export/node/' . $node->id());
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests that submitting the export form writes the YAML file.
   */
  public function testExportFormSubmissionCreatesYamlFile(): void {
    $node = $this->drupalCreateNode(['type' => 'article', 'title' => 'Will Export']);
    $this->drupalGet('/lark/export/node/' . $node->id());

    // Submit the export form. The button label is 'Export to Source'.
    $this->submitForm([], 'Export to Source');

    // BrowserTestBase::$siteDirectory is an absolute path (set via realpath()
    // in the test harness) so LarkSource::directoryProcessed() won't prepend
    // DRUPAL_ROOT.
    $export_dir = $this->siteDirectory . '/lark-functional-test-exports';
    $yaml_path = $export_dir . '/node/article/' . $node->uuid() . '.yml';
    $this->assertFileExists($yaml_path, 'Submitting the export form must create the YAML file on disk');
  }

  /**
   * Tests that the export form is denied without the export permission.
   */
  public function testExportRequiresPermission(): void {
    $this->drupalLogout();
    $limited_user = $this->drupalCreateUser(['access content']);
    $this->drupalLogin($limited_user);

    $node = $this->drupalCreateNode(['type' => 'article', 'title' => 'Forbidden Export']);
    $this->drupalGet('/lark/export/node/' . $node->id());
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests that the export permission alone reaches the Lark tab.
   *
   * The route requirement uses '+' (OR). With ',' (AND) - which is what it
   * said until this test was written - a user holding only one of the two
   * permissions got a 403 here, and EntityController::larkLoad()'s import
   * branch was unreachable.
   */
  public function testLarkTabReachableWithOnlyTheExportPermission(): void {
    $this->drupalLogout();
    $this->drupalLogin($this->drupalCreateUser([
      'access content',
      'lark export entity',
    ]));

    $node = $this->drupalCreateNode(['type' => 'article', 'title' => 'Export Only']);
    $this->drupalGet('/lark/node/' . $node->id());

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->addressEquals('/lark/export/node/' . $node->id());
  }

  /**
   * Tests that the import permission alone reaches the Lark tab.
   */
  public function testLarkTabReachableWithOnlyTheImportPermission(): void {
    $this->drupalLogout();
    $this->drupalLogin($this->drupalCreateUser([
      'access content',
      'lark import entity',
    ]));

    $node = $this->drupalCreateNode(['type' => 'article', 'title' => 'Import Only']);
    $this->drupalGet('/lark/node/' . $node->id());

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->addressEquals('/lark/import/node/' . $node->id());
  }

  /**
   * Tests that the Lark tab is denied without either Lark permission.
   */
  public function testLarkTabDeniedWithNeitherPermission(): void {
    $this->drupalLogout();
    $this->drupalLogin($this->drupalCreateUser(['access content']));

    $node = $this->drupalCreateNode(['type' => 'article', 'title' => 'No Lark']);
    $this->drupalGet('/lark/node/' . $node->id());

    $this->assertSession()->statusCodeEquals(403);
  }

}
