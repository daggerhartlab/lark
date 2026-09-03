<?php

namespace Drupal\Tests\lark\Kernel\Utility;

use Drupal\KernelTests\KernelTestBase;
use Drupal\lark\Model\ExportArray;
use Drupal\lark\Service\Utility\EntityUtility;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\system\Entity\Menu;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * @coversDefaultClass \Drupal\lark\Service\Utility\EntityUtility
 * @group lark
 */
#[RunTestsInSeparateProcesses]
class EntityUtilityTest extends KernelTestBase {

  protected static $modules = [
    'lark', 'node', 'user', 'system', 'field', 'text', 'filter',
    'link', 'menu_link_content',
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('menu_link_content');
    $this->installConfig(['node', 'filter']);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    $this->installSchema('node', ['node_access']);
    Menu::create(['id' => 'test-menu', 'label' => 'Test menu'])->save();
  }

  public function testGetEntityExportDependenciesExcludesRootEntity(): void {
    /** @var \Drupal\lark\Service\Utility\EntityUtility $utility */
    $utility = $this->container->get(EntityUtility::class);

    $node = Node::create(['type' => 'article', 'title' => 'Test']);
    $node->save();

    $deps = $utility->getEntityExportDependencies($node);
    $this->assertArrayNotHasKey($node->uuid(), $deps, 'The root entity should not appear in its own dependency list');
  }

  public function testGetEntityUuidEntityTypePairsIncludesRoot(): void {
    /** @var \Drupal\lark\Service\Utility\EntityUtility $utility */
    $utility = $this->container->get(EntityUtility::class);

    $node = Node::create(['type' => 'article', 'title' => 'Test']);
    $node->save();

    $found = [];
    $pairs = $utility->getEntityUuidEntityTypePairs($node, $found);
    $this->assertArrayHasKey($node->uuid(), $pairs);
    $this->assertSame('node', $pairs[$node->uuid()]);
  }

  public function testGetEntityArrayStripsIdAndRevisionKeys(): void {
    /** @var \Drupal\lark\Service\Utility\EntityUtility $utility */
    $utility = $this->container->get(EntityUtility::class);

    $node = Node::create(['type' => 'article', 'title' => 'Test']);
    $node->save();

    $array = $utility->getEntityArray($node);
    $this->assertArrayNotHasKey('nid', $array, 'Entity ID (nid) must be stripped for portability');
    $this->assertArrayNotHasKey('vid', $array, 'Revision ID (vid) must be stripped for portability');
    $this->assertArrayHasKey('uuid', $array);
  }

  private function createLink(string $title, ?MenuLinkContent $parent = NULL, string $uri = 'internal:/'): MenuLinkContent {
    $link = MenuLinkContent::create([
      'title' => $title,
      'menu_name' => 'test-menu',
      'link' => ['uri' => $uri],
      'parent' => $parent ? 'menu_link_content:' . $parent->uuid() : '',
    ]);
    $link->save();
    return $link;
  }

  public function testHandlerContributedDependencyIsDiscovered(): void {
    /** @var \Drupal\lark\Service\Utility\EntityUtility $utility */
    $utility = $this->container->get(EntityUtility::class);

    $parent = $this->createLink('Parent');
    $child = $this->createLink('Child', $parent);

    $deps = $utility->getEntityExportDependencies($child);
    $this->assertSame(['menu_link_content'], array_values($deps));
    $this->assertArrayHasKey($parent->uuid(), $deps);
  }

  public function testHandlerContributedDependencyIsRecursed(): void {
    /** @var \Drupal\lark\Service\Utility\EntityUtility $utility */
    $utility = $this->container->get(EntityUtility::class);

    $grandparent = $this->createLink('Grandparent');
    $parent = $this->createLink('Parent', $grandparent);
    $child = $this->createLink('Child', $parent);

    $deps = $utility->getEntityExportDependencies($child);
    $this->assertArrayHasKey($parent->uuid(), $deps);
    $this->assertArrayHasKey(
      $grandparent->uuid(),
      $deps,
      'Handler-contributed dependencies must be recursed into, not just recorded.'
    );
  }

  public function testRootEntityIsDiscoveredLast(): void {
    /** @var \Drupal\lark\Service\Utility\EntityUtility $utility */
    $utility = $this->container->get(EntityUtility::class);

    $grandparent = $this->createLink('Grandparent');
    $parent = $this->createLink('Parent', $grandparent);
    $child = $this->createLink('Child', $parent);

    $found = [];
    $order = array_keys($utility->getEntityUuidEntityTypePairs($child, $found));

    // Discovery marks an entity in $found before recursing into it, so the
    // order among ancestors is [parent, grandparent], not tree order. That is
    // fine: discovery order carries no guarantee. The ancestors-first
    // guarantee that import relies on comes from
    // ExportCollection::getWithDependencies(), covered in ExportCollectionTest.
    $this->assertSame($child->uuid(), end($order), 'The root entity is discovered last.');
    $this->assertContains($parent->uuid(), $order);
    $this->assertContains($grandparent->uuid(), $order);
  }

  public function testGetEntityExportReferencesIsEmptyWithoutContributors(): void {
    /** @var \Drupal\lark\Service\Utility\EntityUtility $utility */
    $utility = $this->container->get(EntityUtility::class);

    $node = Node::create(['type' => 'article', 'title' => 'Test']);
    $node->save();

    $this->assertSame([], $utility->getEntityExportReferences($node));
  }

  public function testCreateFromEntityHasEmptyReferencesWithoutContributors(): void {
    $node = Node::create(['type' => 'article', 'title' => 'Test']);
    $node->save();

    $export = ExportArray::createFromEntity($node);
    $this->assertSame([], $export->references());
  }

}
