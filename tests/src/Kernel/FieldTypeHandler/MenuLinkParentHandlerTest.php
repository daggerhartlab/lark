<?php

namespace Drupal\Tests\lark\Kernel\FieldTypeHandler;

use Drupal\KernelTests\KernelTestBase;
use Drupal\lark\Service\FieldTypeHandlerManagerInterface;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\system\Entity\Menu;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * @coversDefaultClass \Drupal\lark\Plugin\Lark\FieldTypeHandler\MenuLinkParentHandler
 * @group lark
 */
#[RunTestsInSeparateProcesses]
class MenuLinkParentHandlerTest extends KernelTestBase {

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

  public function testParentIsAHardDependency(): void {
    /** @var \Drupal\lark\Service\FieldTypeHandlerManagerInterface $manager */
    $manager = $this->container->get(FieldTypeHandlerManagerInterface::class);

    $parent = $this->createLink('Parent');
    $child = $this->createLink('Child', $parent);

    $this->assertSame(
      [$parent->uuid() => 'menu_link_content'],
      $manager->getFieldDependencies($child->get('parent'))
    );
  }

  public function testTopLevelLinkHasNoParentDependency(): void {
    /** @var \Drupal\lark\Service\FieldTypeHandlerManagerInterface $manager */
    $manager = $this->container->get(FieldTypeHandlerManagerInterface::class);

    $link = $this->createLink('Top level');

    $this->assertSame([], $manager->getFieldDependencies($link->get('parent')));
  }

  public function testNonContentParentIsIgnored(): void {
    /** @var \Drupal\lark\Service\FieldTypeHandlerManagerInterface $manager */
    $manager = $this->container->get(FieldTypeHandlerManagerInterface::class);

    // A parent pointing at a module-defined link plugin, not a content link.
    $link = MenuLinkContent::create([
      'title' => 'Child of a module link',
      'menu_name' => 'test-menu',
      'link' => ['uri' => 'internal:/'],
      'parent' => 'system.admin',
    ]);
    $link->save();

    $this->assertSame([], $manager->getFieldDependencies($link->get('parent')));
  }

  public function testOtherStringFieldsAreIgnored(): void {
    /** @var \Drupal\lark\Service\FieldTypeHandlerManagerInterface $manager */
    $manager = $this->container->get(FieldTypeHandlerManagerInterface::class);

    $link = $this->createLink('A link');
    $this->assertSame([], $manager->getFieldDependencies($link->get('title')));

    $node = Node::create(['type' => 'article', 'title' => 'A node']);
    $node->save();
    $this->assertSame([], $manager->getFieldDependencies($node->get('title')));
  }

  public function testParentContributesNoSoftReference(): void {
    /** @var \Drupal\lark\Service\FieldTypeHandlerManagerInterface $manager */
    $manager = $this->container->get(FieldTypeHandlerManagerInterface::class);

    $parent = $this->createLink('Parent');
    $child = $this->createLink('Child', $parent);

    $this->assertSame([], $manager->getFieldReferences($child->get('parent')));
  }

}
