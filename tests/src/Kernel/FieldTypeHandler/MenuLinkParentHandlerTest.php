<?php

namespace Drupal\Tests\lark\Kernel\FieldTypeHandler;

use Drupal\KernelTests\KernelTestBase;
use Drupal\lark\Plugin\Lark\FieldTypeHandler\MenuLinkParentHandler;
use Drupal\lark\Service\FieldTypeHandlerManagerInterface;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\system\Entity\Menu;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests \Drupal\lark\Plugin\Lark\FieldTypeHandler\MenuLinkParentHandler.
 */
#[CoversClass(MenuLinkParentHandler::class)]
#[Group('lark')]
#[RunTestsInSeparateProcesses]
class MenuLinkParentHandlerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'lark', 'node', 'user', 'system', 'field', 'text', 'filter',
    'link', 'menu_link_content',
  ];

  /**
   * {@inheritdoc}
   */
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

  /**
   * Creates and saves a menu link, optionally under a parent link.
   *
   * @param string $title
   *   The link title.
   * @param \Drupal\menu_link_content\Entity\MenuLinkContent|null $parent
   *   The parent link, or NULL for a top-level link.
   *
   * @return \Drupal\menu_link_content\Entity\MenuLinkContent
   *   The saved menu link.
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
   * Tests that a content parent link is a dependency of its child.
   */
  public function testParentIsHardDependency(): void {
    /** @var \Drupal\lark\Service\FieldTypeHandlerManagerInterface $manager */
    $manager = $this->container->get(FieldTypeHandlerManagerInterface::class);

    $parent = $this->createLink('Parent');
    $child = $this->createLink('Child', $parent);

    $this->assertSame(
      [$parent->uuid() => 'menu_link_content'],
      $manager->getFieldDependencies($child->get('parent'))
    );
  }

  /**
   * Tests that a top-level link has no parent dependency.
   */
  public function testTopLevelLinkHasNoParentDependency(): void {
    /** @var \Drupal\lark\Service\FieldTypeHandlerManagerInterface $manager */
    $manager = $this->container->get(FieldTypeHandlerManagerInterface::class);

    $link = $this->createLink('Top level');

    $this->assertSame([], $manager->getFieldDependencies($link->get('parent')));
  }

  /**
   * Tests that a parent from a module-defined link adds no dependency.
   */
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

  /**
   * Tests that string fields other than the menu parent add no dependency.
   */
  public function testOtherStringFieldsAreIgnored(): void {
    /** @var \Drupal\lark\Service\FieldTypeHandlerManagerInterface $manager */
    $manager = $this->container->get(FieldTypeHandlerManagerInterface::class);

    $link = $this->createLink('A link');
    $this->assertSame([], $manager->getFieldDependencies($link->get('title')));

    $node = Node::create(['type' => 'article', 'title' => 'A node']);
    $node->save();
    $this->assertSame([], $manager->getFieldDependencies($node->get('title')));
  }

  /**
   * Tests that a parent link is not recorded as a reference.
   */
  public function testParentContributesNoSoftReference(): void {
    /** @var \Drupal\lark\Service\FieldTypeHandlerManagerInterface $manager */
    $manager = $this->container->get(FieldTypeHandlerManagerInterface::class);

    $parent = $this->createLink('Parent');
    $child = $this->createLink('Child', $parent);

    $this->assertSame([], $manager->getFieldReferences($child->get('parent')));
  }

}
