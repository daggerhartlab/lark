<?php

namespace Drupal\Tests\lark\Kernel\Service;

use Drupal\KernelTests\KernelTestBase;
use Drupal\lark\Service\FieldTypeHandlerManager;
use Drupal\lark\Service\FieldTypeHandlerManagerInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests \Drupal\lark\Service\FieldTypeHandlerManager.
 */
#[CoversClass(FieldTypeHandlerManager::class)]
#[Group('lark')]
#[RunTestsInSeparateProcesses]
class FieldTypeHandlerManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'lark', 'node', 'user', 'system', 'field', 'text', 'filter',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig(['node', 'filter']);
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    $this->installSchema('node', ['node_access']);
  }

  /**
   * Tests that a plain field gets no dependencies or references.
   */
  public function testHandlersWithoutOverridesContributeNothing(): void {
    /** @var \Drupal\lark\Service\FieldTypeHandlerManagerInterface $manager */
    $manager = $this->container->get(FieldTypeHandlerManagerInterface::class);

    $node = Node::create(['type' => 'article', 'title' => 'Test']);
    $node->save();

    // 'title' is a plain string field with no handler contributing anything.
    $this->assertSame([], $manager->getFieldDependencies($node->get('title')));
    $this->assertSame([], $manager->getFieldReferences($node->get('title')));
  }

  /**
   * Tests that the default handler returns no dependencies or references.
   */
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
