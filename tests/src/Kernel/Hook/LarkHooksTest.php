<?php

declare(strict_types=1);

namespace Drupal\Tests\lark\Kernel\Hook;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\lark\Routing\EntityTypeInfo;
use Drupal\lark\Routing\RouteTemplates;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the hooks Lark implements.
 */
#[Group('lark')]
#[RunTestsInSeparateProcesses]
class LarkHooksTest extends KernelTestBase {

  use UserCreationTrait;

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
    $this->installSchema('node', ['node_access']);
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
  }

  /**
   * The help page renders the README without its title.
   */
  public function testHelp(): void {
    $route_match = $this->createMock(RouteMatchInterface::class);
    $help = (string) $this->container->get('module_handler')
      ->invoke('lark', 'help', ['help.page.lark', $route_match]);

    $this->assertStringContainsString('<h2>Setup</h2>', $help);
    $this->assertStringNotContainsString('<h1>Lark</h1>', $help);

    $this->assertNull($this->container->get('module_handler')
      ->invoke('lark', 'help', ['system.admin', $route_match]));
  }

  /**
   * Exported archives download with a descriptive filename.
   */
  public function testFileDownload(): void {
    $module_handler = $this->container->get('module_handler');

    $this->setUpCurrentUser(['uid' => 2], ['lark export entity']);
    foreach (['lark-export', 'lark-source'] as $prefix) {
      $headers = $module_handler->invoke('lark', 'file_download', ["temporary://$prefix.tar.gz"]);
      $this->assertMatchesRegularExpression(
        '/^attachment; filename="' . $prefix . '--localhost--\d{12}\.tar\.gz"$/',
        $headers['Content-disposition'] ?? '',
      );
    }

    // Other files are not Lark's to control.
    $this->assertNull($module_handler->invoke('lark', 'file_download', ['temporary://other.tar.gz']));
    $this->assertNull($module_handler->invoke('lark', 'file_download', ['public://lark-export.tar.gz']));

    $this->setUpCurrentUser(['uid' => 3]);
    $this->assertSame(-1, $module_handler->invoke('lark', 'file_download', ['temporary://lark-export.tar.gz']));
  }

  /**
   * Content entity types other than users get Lark link templates.
   */
  public function testEntityTypeAlter(): void {
    $entity_type_manager = $this->container->get('entity_type.manager');
    $templates = RouteTemplates::getRouteTemplates('node');
    $parent = array_shift($templates);

    $node = $entity_type_manager->getDefinition('node');
    $this->assertTrue($node->get(EntityTypeInfo::IS_EXPORTABLE));
    $this->assertTrue($node->hasLinkTemplate($parent['link']['key']));

    $this->assertFalse($entity_type_manager->getDefinition('user')->get(EntityTypeInfo::IS_EXPORTABLE));
    $this->assertFalse($entity_type_manager->getDefinition('node_type')->get(EntityTypeInfo::IS_EXPORTABLE));
  }

  /**
   * Users who can export or import get a Lark operation on entities.
   */
  public function testEntityOperation(): void {
    $node = Node::create(['type' => 'article', 'title' => 'Test']);
    $node->save();
    $module_handler = $this->container->get('module_handler');

    $this->setUpCurrentUser(['uid' => 2], ['lark import entity']);
    $operations = $module_handler->invokeAll('entity_operation', [$node]);
    $this->assertArrayHasKey('lark', $operations);

    $this->setUpCurrentUser(['uid' => 3]);
    $operations = $module_handler->invokeAll('entity_operation', [$node]);
    $this->assertArrayNotHasKey('lark', $operations);
  }

}
