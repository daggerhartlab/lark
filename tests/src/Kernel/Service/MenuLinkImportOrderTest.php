<?php

namespace Drupal\Tests\lark\Kernel\Service;

use Drupal\KernelTests\KernelTestBase;
use Drupal\lark\Entity\LarkSource;
use Drupal\lark\Service\ExporterInterface;
use Drupal\lark\Service\ImporterInterface;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\system\Entity\Menu;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Menu links must be imported ancestors-first.
 *
 * This exists because the obvious assertions do not work. A menu link's own
 * 'parent' field is copied verbatim out of the YAML, so it reads correctly no
 * matter what order the links were created in; and the menu_tree table is
 * re-sorted parents-first by MenuLinkManager::rebuild(), so a flattened tree
 * heals on the next router rebuild. Both are repairable state.
 *
 * Entity IDs are not. They are assigned by auto-increment at save time and
 * never revised, so their relative order is permanent evidence of creation
 * order - which is exactly the guarantee under test.
 *
 * The order is forced hostile: importSourceExports() is handed the child's
 * UUID first. Only the parent-as-dependency mechanism can correct that.
 *
 * @coversDefaultClass \Drupal\lark\Service\Importer
 * @group lark
 */
#[RunTestsInSeparateProcesses]
class MenuLinkImportOrderTest extends KernelTestBase {

  /**
   * Disable strict config schema to avoid lark.settings integer/string mismatch.
   */
  protected $strictConfigSchema = FALSE;

  protected static $modules = [
    'lark', 'user', 'system', 'field', 'link', 'menu_link_content',
  ];

  protected string $exportDir;

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('menu_link_content');
    $this->installEntitySchema('lark_source');
    $this->installConfig(['lark']);

    // The importer's AdminAccountSwitcher needs user 1 to exist.
    User::create(['uid' => 1, 'name' => 'admin', 'status' => 1])->save();

    // A real absolute path, so LarkSource::directoryProcessed() is stable.
    $this->exportDir = sys_get_temp_dir() . '/lark-menu-order-' . uniqid();
    LarkSource::create([
      'id' => 'test_source',
      'label' => 'Test Source',
      'directory' => $this->exportDir,
    ])->save();

    \Drupal::configFactory()->getEditable('lark.settings')
      ->set('default_source', 'test_source')
      ->save();

    Menu::create(['id' => 'test-menu', 'label' => 'Test menu'])->save();
  }

  protected function tearDown(): void {
    if (isset($this->exportDir) && is_dir($this->exportDir)) {
      \Drupal::service('file_system')->deleteRecursive($this->exportDir);
    }
    parent::tearDown();
  }

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

  public function testParentIsCreatedBeforeItsChildDespiteHostileUuidOrder(): void {
    /** @var \Drupal\lark\Service\ExporterInterface $exporter */
    $exporter = $this->container->get(ExporterInterface::class);
    /** @var \Drupal\lark\Service\ImporterInterface $importer */
    $importer = $this->container->get(ImporterInterface::class);
    $repository = $this->container->get('entity.repository');

    $parent = $this->createLink('Parent');
    $child = $this->createLink('Child', $parent);
    $parent_uuid = $parent->uuid();
    $child_uuid = $child->uuid();

    $exporter->exportEntities('test_source', [$parent, $child], FALSE);

    // Delete the child first: deleting the parent first would make Drupal
    // reattach the child to the parent's parent, muddying what is under test.
    $child->delete();
    $parent->delete();
    $this->assertNull($repository->loadEntityByUuid('menu_link_content', $parent_uuid));
    $this->assertNull($repository->loadEntityByUuid('menu_link_content', $child_uuid));

    // Hostile order: the child is named first. Only the parent-as-dependency
    // mechanism can put the parent back in front of it.
    $importer->importSourceExports('test_source', [$child_uuid, $parent_uuid], FALSE);

    /** @var \Drupal\menu_link_content\MenuLinkContentInterface $restored_parent */
    $restored_parent = $repository->loadEntityByUuid('menu_link_content', $parent_uuid);
    /** @var \Drupal\menu_link_content\MenuLinkContentInterface $restored_child */
    $restored_child = $repository->loadEntityByUuid('menu_link_content', $child_uuid);

    $this->assertNotNull($restored_parent, 'The parent link was imported.');
    $this->assertNotNull($restored_child, 'The child link was imported.');

    // The load-bearing assertion. Auto-increment IDs are permanent, so a lower
    // parent ID proves the parent row was written first.
    $this->assertLessThan(
      (int) $restored_child->id(),
      (int) $restored_parent->id(),
      'The parent must be created before the child that depends on it, even when the child is listed first.'
    );
  }

  public function testAncestorsPrecedeDescendantsThroughAThreeLevelChain(): void {
    /** @var \Drupal\lark\Service\ExporterInterface $exporter */
    $exporter = $this->container->get(ExporterInterface::class);
    /** @var \Drupal\lark\Service\ImporterInterface $importer */
    $importer = $this->container->get(ImporterInterface::class);
    $repository = $this->container->get('entity.repository');

    $grandparent = $this->createLink('Grandparent');
    $parent = $this->createLink('Parent', $grandparent);
    $child = $this->createLink('Child', $parent);
    $uuids = [
      'grandparent' => $grandparent->uuid(),
      'parent' => $parent->uuid(),
      'child' => $child->uuid(),
    ];

    $exporter->exportEntities('test_source', [$grandparent, $parent, $child], FALSE);

    // Deepest first, so no reattaching happens on delete.
    $child->delete();
    $parent->delete();
    $grandparent->delete();

    // Fully reversed: deepest descendant first.
    $importer->importSourceExports('test_source', [
      $uuids['child'],
      $uuids['parent'],
      $uuids['grandparent'],
    ], FALSE);

    $ids = [];
    foreach ($uuids as $name => $uuid) {
      $entity = $repository->loadEntityByUuid('menu_link_content', $uuid);
      $this->assertNotNull($entity, "The $name link was imported.");
      $ids[$name] = (int) $entity->id();
    }

    $this->assertLessThan($ids['parent'], $ids['grandparent'], 'Grandparent created before parent.');
    $this->assertLessThan($ids['child'], $ids['parent'], 'Parent created before child.');
  }

}
