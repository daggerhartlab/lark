<?php

namespace Drupal\Tests\lark\Kernel\Service;

use Drupal\KernelTests\KernelTestBase;
use Drupal\lark\Service\FieldTypeHandlerManagerInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * @coversDefaultClass \Drupal\lark\Service\FieldTypeHandlerManager
 * @group lark
 */
#[RunTestsInSeparateProcesses]
class FieldTypeHandlerManagerTest extends KernelTestBase {

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

  public function testHandlersWithoutOverridesContributeNothing(): void {
    /** @var \Drupal\lark\Service\FieldTypeHandlerManagerInterface $manager */
    $manager = $this->container->get(FieldTypeHandlerManagerInterface::class);

    $node = Node::create(['type' => 'article', 'title' => 'Test']);
    $node->save();

    // 'title' is a plain string field with no handler contributing anything.
    $this->assertSame([], $manager->getFieldDependencies($node->get('title')));
    $this->assertSame([], $manager->getFieldReferences($node->get('title')));
  }

  public function testBaseClassDefaultsAreEmpty(): void {
    /** @var \Drupal\lark\Service\FieldTypeHandlerManagerInterface $manager */
    $manager = $this->container->get(FieldTypeHandlerManagerInterface::class);

    $node = Node::create(['type' => 'article', 'title' => 'Test']);
    $node->save();

    // DefaultHandler is registered for '*', so it is the one handler that
    // legitimately receives every field type. Handlers registered for a
    // specific type are only ever routed fields of that type, so calling them
    // with an arbitrary field would assert a contract that does not exist.
    $default = $manager->createInstance('default_field_type_handler');

    $this->assertSame([], $default->getFieldDependencies($node->get('title')));
    $this->assertSame([], $default->getFieldReferences($node->get('title')));
  }

}
