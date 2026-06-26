<?php

namespace Drupal\Tests\lark\Kernel\Utility;

use Drupal\KernelTests\KernelTestBase;
use Drupal\lark\Service\Utility\EntityUtility;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * @coversDefaultClass \Drupal\lark\Service\Utility\EntityUtility
 * @group lark
 */
#[RunTestsInSeparateProcesses]
class EntityUtilityTest extends KernelTestBase {

  protected static $modules = [
    'lark', 'node', 'user', 'system', 'field', 'text', 'filter',
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig(['node', 'filter']);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    $this->installSchema('node', ['node_access']);
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

}
