<?php

namespace Drupal\Tests\lark\Functional;

use Drupal\lark\Entity\LarkSource;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\system\Entity\Menu;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Lark tab on a menu.
 */
#[Group('lark')]
#[RunTestsInSeparateProcesses]
class MenuExportImportTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'lark', 'node', 'user', 'field', 'text', 'filter', 'block',
    'menu_ui', 'menu_link_content', 'link',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    Menu::create(['id' => 'test-menu', 'label' => 'Test menu'])->save();

    LarkSource::create([
      'id' => 'functional_test_source',
      'label' => 'Functional Test Source',
      'directory' => $this->siteDirectory . '/lark-menu-test-exports',
    ])->save();

    \Drupal::configFactory()->getEditable('lark.settings')
      ->set('default_source', 'functional_test_source')
      ->save();

    // Without this block the local tasks never render.
    $this->drupalPlaceBlock('local_tasks_block');
  }

  /**
   * Creates a link in the test menu.
   *
   * @param string $title
   *   The link title.
   * @param \Drupal\menu_link_content\Entity\MenuLinkContent|null $parent
   *   The parent link, or NULL for a top-level link.
   *
   * @return \Drupal\menu_link_content\Entity\MenuLinkContent
   *   The saved link.
   */
  private function createLink(string $title, ?MenuLinkContent $parent = NULL): MenuLinkContent {
    $link = MenuLinkContent::create([
      'title' => $title,
      'menu_name' => 'test-menu',
      'link' => ['uri' => 'internal:/'],
      'parent' => $parent ? 'menu_link_content:' . $parent->uuid() : '',
    ]);
    $link->save();
    return $link;
  }

  /**
   * Tests that the menu edit form links to the Lark tab.
   */
  public function testLarkTabAppearsOnTheMenuEditForm(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'administer menu', 'lark export entity', 'lark import entity',
    ]));

    $this->drupalGet('/admin/structure/menu/manage/test-menu');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkByHrefExists('/admin/structure/menu/manage/test-menu/lark');
  }

  /**
   * Tests that the export tab lists every link in the menu.
   */
  public function testExportTabListsEveryLinkInTheMenu(): void {
    $this->createLink('First');
    $this->createLink('Second');

    $this->drupalLogin($this->drupalCreateUser([
      'administer menu', 'lark export entity',
    ]));

    $this->drupalGet('/admin/structure/menu/manage/test-menu/lark/export');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('First');
    $this->assertSession()->pageTextContains('Second');
  }

  /**
   * Tests that the export tab is denied without the export permission.
   */
  public function testExportTabIsDeniedWithoutTheExportPermission(): void {
    $this->drupalLogin($this->drupalCreateUser(['administer menu']));

    $this->drupalGet('/admin/structure/menu/manage/test-menu/lark/export');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests that the import tab is denied without the import permission.
   */
  public function testImportTabIsDeniedWithoutTheImportPermission(): void {
    $this->drupalLogin($this->drupalCreateUser(['administer menu']));

    $this->drupalGet('/admin/structure/menu/manage/test-menu/lark/import');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests that the Lark tab redirects an export-only user to export.
   */
  public function testLarkTabRedirectsToExportForAnExportOnlyUser(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'administer menu', 'lark export entity',
    ]));

    $this->drupalGet('/admin/structure/menu/manage/test-menu/lark');
    $this->assertSession()->addressEquals('/admin/structure/menu/manage/test-menu/lark/export');
  }

  /**
   * Tests that the Lark tab redirects an import-only user to import.
   */
  public function testLarkTabRedirectsToImportForAnImportOnlyUser(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'administer menu', 'lark import entity',
    ]));

    $this->drupalGet('/admin/structure/menu/manage/test-menu/lark');
    $this->assertSession()->addressEquals('/admin/structure/menu/manage/test-menu/lark/import');
  }

  /**
   * Tests that an empty menu's export tab has no export button.
   */
  public function testEmptyMenuRendersWithoutAnExportButton(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'administer menu', 'lark export entity',
    ]));

    $this->drupalGet('/admin/structure/menu/manage/test-menu/lark/export');
    $this->assertSession()->statusCodeEquals(200);
    // Asserting on the absence of the button rather than on the warning text:
    // whether messages render depends on the theme's message block.
    $this->assertSession()->buttonNotExists('Export to Source');
  }

  /**
   * Tests that the export tab has a download button.
   */
  public function testDownloadButtonExistsOnTheExportTab(): void {
    $this->createLink('First');

    $this->drupalLogin($this->drupalCreateUser([
      'administer menu', 'lark export entity',
    ]));

    $this->drupalGet('/admin/structure/menu/manage/test-menu/lark/export');
    $this->assertSession()->buttonExists('Download');
  }

  /**
   * Tests that the download button returns a file attachment.
   */
  public function testDownloadButtonReturnsFileResponse(): void {
    $this->createLink('First');

    $this->drupalLogin($this->drupalCreateUser([
      'administer menu', 'lark export entity',
    ]));

    $this->drupalGet('/admin/structure/menu/manage/test-menu/lark/export');
    $this->submitForm([], 'Download');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderContains('Content-Disposition', 'attachment');
  }

  /**
   * Tests that a deleted child link is reimported under its parent.
   */
  public function testDeletedChildLinkIsRecreatedWithItsParentIntact(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'administer menu', 'lark export entity', 'lark import entity',
    ]));

    $parent = $this->createLink('Parent');
    $child = $this->createLink('Child', $parent);
    $child_uuid = $child->uuid();
    $parent_uuid = $parent->uuid();

    $this->drupalGet('/admin/structure/menu/manage/test-menu/lark/export');
    $this->submitForm([], 'Export to Source');

    $child->delete();
    $this->assertNull(\Drupal::service('entity.repository')->loadEntityByUuid('menu_link_content', $child_uuid));

    $this->drupalGet('/admin/structure/menu/manage/test-menu/lark/import');
    $this->submitForm([], 'Import from Source');

    $restored = \Drupal::service('entity.repository')->loadEntityByUuid('menu_link_content', $child_uuid);
    $this->assertInstanceOf(MenuLinkContentInterface::class, $restored, 'The deleted child link was recreated from the source.');
    $this->assertSame('menu_link_content:' . $parent_uuid, $restored->getParentId());

    // The entity's own 'parent' field is copied verbatim from the YAML and
    // is true regardless of import order, so it cannot detect a broken tree.
    // MenuTreeStorage::preSave() is what actually strands a child at top
    // level when its parent row does not exist yet at the moment the child
    // is saved: findParent() fails to resolve the parent id, so it writes
    // parent = '' and depth = 1 into the menu_tree table even though the
    // entity's own parent field still looks correct. Assert directly against
    // that table so a regression in import ordering (MenuLinkParentHandler,
    // MenuLinkCollector's tree ordering, or the single-pass
    // importSourceExports()) is actually caught.
    $tree_row = \Drupal::database()->select('menu_tree', 't')
      ->fields('t', ['parent', 'depth'])
      ->condition('id', 'menu_link_content:' . $child_uuid)
      ->execute()
      ->fetchAssoc();

    $this->assertNotFalse($tree_row, 'The restored child link has a row in the menu tree table.');
    $this->assertSame(
      'menu_link_content:' . $parent_uuid,
      $tree_row['parent'],
      'The child sits under its parent in the menu tree, not stranded at top level.'
    );
    $this->assertSame(2, (int) $tree_row['depth'], 'The child is one level deeper than its top-level parent.');
  }

}
