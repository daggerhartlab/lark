<?php

namespace Drupal\Tests\lark\Kernel\Utility;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use Drupal\lark\Entity\LarkSource;
use Drupal\lark\Service\ExporterInterface;
use Drupal\lark\Service\Utility\MenuLinkCollector;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\system\Entity\Menu;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * @coversDefaultClass \Drupal\lark\Service\Utility\MenuLinkCollector
 * @group lark
 */
#[RunTestsInSeparateProcesses]
class MenuLinkCollectorTest extends KernelTestBase {

  /**
   * Disable strict config schema to avoid lark.settings integer/string mismatch.
   */
  protected $strictConfigSchema = FALSE;

  protected static $modules = [
    'lark', 'node', 'user', 'system', 'field', 'text', 'filter',
    'link', 'menu_link_content',
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('menu_link_content');
    Menu::create(['id' => 'test-menu', 'label' => 'Test menu'])->save();
    Menu::create(['id' => 'other-menu', 'label' => 'Other menu'])->save();
  }

  private function createLink(string $title, string $menu = 'test-menu', ?MenuLinkContent $parent = NULL, int $weight = 0): MenuLinkContent {
    $link = MenuLinkContent::create([
      'title' => $title,
      'menu_name' => $menu,
      'link' => ['uri' => 'internal:/'],
      'weight' => $weight,
      'parent' => $parent ? 'menu_link_content:' . $parent->uuid() : '',
    ]);
    $link->save();
    return $link;
  }

  private function menu(string $id): Menu {
    return Menu::load($id);
  }

  public function testReturnsOnlyLinksInTheGivenMenu(): void {
    /** @var \Drupal\lark\Service\Utility\MenuLinkCollector $collector */
    $collector = $this->container->get(MenuLinkCollector::class);

    $mine = $this->createLink('Mine');
    $this->createLink('Theirs', 'other-menu');

    $links = $collector->getMenuLinks($this->menu('test-menu'));

    $this->assertCount(1, $links);
    $this->assertSame($mine->uuid(), reset($links)->uuid());
  }

  public function testReturnsEmptyArrayForAnEmptyMenu(): void {
    /** @var \Drupal\lark\Service\Utility\MenuLinkCollector $collector */
    $collector = $this->container->get(MenuLinkCollector::class);

    $this->assertSame([], $collector->getMenuLinks($this->menu('test-menu')));
  }

  public function testOrdersParentsBeforeChildren(): void {
    /** @var \Drupal\lark\Service\Utility\MenuLinkCollector $collector */
    $collector = $this->container->get(MenuLinkCollector::class);

    // Created children-first on purpose, and weighted so a naive weight sort
    // would put the child ahead of its parent.
    $parent = $this->createLink('Parent', 'test-menu', NULL, 10);
    $child = $this->createLink('Child', 'test-menu', $parent, -10);
    $this->createLink('Grandchild', 'test-menu', $child, -20);

    $order = array_map(fn ($link) => $link->getTitle(), array_values($collector->getMenuLinks($this->menu('test-menu'))));

    $this->assertSame(['Parent', 'Child', 'Grandchild'], $order);
  }

  public function testOrdersSiblingsByWeightThenTitle(): void {
    /** @var \Drupal\lark\Service\Utility\MenuLinkCollector $collector */
    $collector = $this->container->get(MenuLinkCollector::class);

    $this->createLink('Zebra', 'test-menu', NULL, -5);
    $this->createLink('Beta', 'test-menu', NULL, 5);
    $this->createLink('Alpha', 'test-menu', NULL, 5);

    $order = array_map(fn ($link) => $link->getTitle(), array_values($collector->getMenuLinks($this->menu('test-menu'))));

    $this->assertSame(['Zebra', 'Alpha', 'Beta'], $order);
  }

  public function testLinkWithAnUnknownParentIsTreatedAsTopLevel(): void {
    /** @var \Drupal\lark\Service\Utility\MenuLinkCollector $collector */
    $collector = $this->container->get(MenuLinkCollector::class);

    // A parent that is a module-defined plugin, not a content link. The link
    // must still be returned rather than silently dropped.
    $link = MenuLinkContent::create([
      'title' => 'Orphan',
      'menu_name' => 'test-menu',
      'link' => ['uri' => 'internal:/'],
      'parent' => 'system.admin',
    ]);
    $link->save();

    $links = $collector->getMenuLinks($this->menu('test-menu'));
    $this->assertCount(1, $links);
  }

  public function testSelfReferencingParentLinkIsStillReturned(): void {
    /** @var \Drupal\lark\Service\Utility\MenuLinkCollector $collector */
    $collector = $this->container->get(MenuLinkCollector::class);

    $link = $this->createLink('Cyclic');
    // Point the link's own parent at itself - a self-referencing parent must
    // never leave the link unreachable by the depth-first walk.
    $link->set('parent', 'menu_link_content:' . $link->uuid());
    $link->save();

    $links = $collector->getMenuLinks($this->menu('test-menu'));

    $this->assertCount(1, $links, 'A self-referencing parent must not make the link vanish from the export set.');
    $this->assertArrayHasKey($link->uuid(), $links);
  }

  public function testMutuallyCyclicParentsAreStillReturned(): void {
    /** @var \Drupal\lark\Service\Utility\MenuLinkCollector $collector */
    $collector = $this->container->get(MenuLinkCollector::class);

    $a = $this->createLink('A');
    $b = $this->createLink('B');
    $a->set('parent', 'menu_link_content:' . $b->uuid());
    $a->save();
    $b->set('parent', 'menu_link_content:' . $a->uuid());
    $b->save();

    $links = $collector->getMenuLinks($this->menu('test-menu'));

    $this->assertCount(2, $links, 'A mutually cyclic parent chain must not drop either link.');
    $this->assertArrayHasKey($a->uuid(), $links);
    $this->assertArrayHasKey($b->uuid(), $links);
  }

  public function testGetSourceLinkUuidsReturnsOnlyThisMenusLinks(): void {
    $this->installEntitySchema('lark_source');

    $export_dir = sys_get_temp_dir() . '/lark-menu-collector-test-' . uniqid();
    $source = LarkSource::create([
      'id' => 'test_source',
      'label' => 'Test Source',
      'directory' => $export_dir,
    ]);
    $source->save();

    $mine = $this->createLink('Mine');
    $theirs = $this->createLink('Theirs', 'other-menu');

    /** @var \Drupal\lark\Service\ExporterInterface $exporter */
    $exporter = $this->container->get(ExporterInterface::class);
    $exporter->exportEntities('test_source', [$mine, $theirs], FALSE);

    /** @var \Drupal\lark\Service\Utility\MenuLinkCollector $collector */
    $collector = $this->container->get(MenuLinkCollector::class);
    $uuids = $collector->getSourceLinkUuids($source, $this->menu('test-menu'));

    $this->assertSame([$mine->uuid()], $uuids);

    \Drupal::service('file_system')->deleteRecursive($export_dir);
  }

  public function testGetSourceLinkUuidsSkipsExportsMissingMenuName(): void {
    $this->installEntitySchema('lark_source');

    $export_dir = sys_get_temp_dir() . '/lark-menu-collector-test-' . uniqid();
    $source = LarkSource::create([
      'id' => 'test_source',
      'label' => 'Test Source',
      'directory' => $export_dir,
    ]);
    $source->save();

    // Write an export missing the menu_name field entirely - it must be
    // skipped rather than triggering a fatal.
    $uuid = \Drupal::service('uuid')->generate();
    $yaml = Yaml::encode([
      '_meta' => [
        'entity_type' => 'menu_link_content',
        'bundle' => 'menu_link_content',
        'uuid' => $uuid,
        'default_langcode' => 'en',
        'depends' => [],
      ],
      'default' => [
        'title' => [['value' => 'No menu name']],
      ],
    ]);

    $directory = $source->directoryProcessed();
    \Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    file_put_contents($directory . '/' . $uuid . '.yml', $yaml);

    /** @var \Drupal\lark\Service\Utility\MenuLinkCollector $collector */
    $collector = $this->container->get(MenuLinkCollector::class);
    $uuids = $collector->getSourceLinkUuids($source, $this->menu('test-menu'));

    $this->assertSame([], $uuids);

    \Drupal::service('file_system')->deleteRecursive($export_dir);
  }

}
