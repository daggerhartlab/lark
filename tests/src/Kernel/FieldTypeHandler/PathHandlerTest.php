<?php

declare(strict_types=1);

namespace Drupal\Tests\lark\Kernel\FieldTypeHandler;

use Drupal\KernelTests\KernelTestBase;
use Drupal\lark\Plugin\Lark\FieldTypeHandler\PathHandler;
use Drupal\lark\Service\FieldTypeHandlerManagerInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\path_alias\PathAliasInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests \Drupal\lark\Plugin\Lark\FieldTypeHandler\PathHandler.
 */
#[CoversClass(PathHandler::class)]
#[Group('lark')]
#[RunTestsInSeparateProcesses]
class PathHandlerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'lark', 'node', 'user', 'system', 'field', 'text', 'filter',
    'path', 'path_alias',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('path_alias');
    $this->installConfig(['node', 'filter']);
    $this->installSchema('node', ['node_access']);
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
  }

  /**
   * Tests that a path alias imports on a site without Pathauto.
   */
  public function testImportWithoutPathauto(): void {
    $uuid = $this->container->get('uuid')->generate();
    $node = Node::create(['type' => 'article', 'title' => 'Test']);

    /** @var \Drupal\lark\Service\FieldTypeHandlerManagerInterface $manager */
    $manager = $this->container->get(FieldTypeHandlerManagerInterface::class);
    $values = $manager->getInstances()['path_handler']->alterImportValue([
      ['target_uuid' => $uuid, 'alias' => '/imported', 'langcode' => 'en'],
    ], $node->get('path'));

    $alias = $this->container->get('entity.repository')->loadEntityByUuid('path_alias', $uuid);
    $this->assertInstanceOf(PathAliasInterface::class, $alias);
    $this->assertSame('/imported', $alias->getAlias());
    $this->assertSame('/node/' . $node->id(), $alias->getPath());
    $this->assertEquals($alias->id(), $values[0]['pid']);
    $this->assertArrayNotHasKey('pathauto', $values[0]);
  }

}
