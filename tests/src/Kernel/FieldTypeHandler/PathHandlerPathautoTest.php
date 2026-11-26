<?php

declare(strict_types=1);

namespace Drupal\Tests\lark\Kernel\FieldTypeHandler;

use Drupal\KernelTests\KernelTestBase;
use Drupal\lark\Entity\LarkSource;
use Drupal\lark\Plugin\Lark\FieldTypeHandler\PathHandler;
use Drupal\lark\Service\ExporterInterface;
use Drupal\lark\Service\ImporterInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\pathauto\Entity\PathautoPattern;
use Drupal\Tests\lark\Traits\RequiresModuleTrait;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests exporting and importing path aliases with Pathauto installed.
 */
#[CoversClass(PathHandler::class)]
#[Group('lark')]
#[RunTestsInSeparateProcesses]
class PathHandlerPathautoTest extends KernelTestBase {

  use RequiresModuleTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'lark', 'node', 'user', 'system', 'field', 'text', 'filter',
    'path', 'path_alias', 'token', 'pathauto',
  ];

  /**
   * The absolute path of the export directory.
   *
   * @var string
   */
  protected string $exportDir;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->requireModules('token', 'pathauto');
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('lark_source');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'node', 'filter', 'pathauto', 'lark']);

    // The importer switches to user 1 to resolve entity owners.
    User::create(['uid' => 1, 'name' => 'admin', 'status' => 1])->save();
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    PathautoPattern::create([
      'id' => 'article',
      'type' => 'canonical_entities:node',
      'pattern' => '/article/[node:title]',
    ])->save();

    $this->exportDir = sys_get_temp_dir() . '/lark-test-pathauto-' . uniqid();
    LarkSource::create([
      'id' => 'test_source',
      'label' => 'Test Source',
      'directory' => $this->exportDir,
    ])->save();
    $this->config('lark.settings')->set('default_source', 'test_source')->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if (isset($this->exportDir) && is_dir($this->exportDir)) {
      $this->container->get('file_system')->deleteRecursive($this->exportDir);
    }
    parent::tearDown();
  }

  /**
   * Tests that a generated alias imports with Pathauto still in charge.
   */
  public function testGeneratedAliasRoundTrip(): void {
    $node = Node::create(['type' => 'article', 'title' => 'Generated']);
    $node->save();
    $this->assertSame('/article/generated', $this->aliasFor($node));

    $this->exportAndDelete($node);
    $this->import();

    $imported = $this->loadNode($node->uuid());
    $this->assertSame('/article/generated', $this->aliasFor($imported));
    $this->assertEquals(1, $imported->get('path')->pathauto);
  }

  /**
   * Tests that a manual alias isn't replaced by the pattern on import.
   */
  public function testManualAliasRoundTrip(): void {
    $node = Node::create([
      'type' => 'article',
      'title' => 'Manual',
      'path' => ['alias' => '/hand-written', 'pathauto' => 0],
    ]);
    $node->save();
    $this->assertSame('/hand-written', $this->aliasFor($node));

    $this->exportAndDelete($node);
    $this->import();

    $imported = $this->loadNode($node->uuid());
    $this->assertSame('/hand-written', $this->aliasFor($imported));
    $this->assertEquals(0, $imported->get('path')->pathauto);
  }

  /**
   * Exports a node and deletes it with its alias.
   */
  protected function exportAndDelete(NodeInterface $node): void {
    $this->container->get(ExporterInterface::class)
      ->exportEntity('test_source', 'node', (int) $node->id(), FALSE);
    $node->delete();
    $this->assertSame([], $this->container->get('entity_type.manager')->getStorage('path_alias')->loadMultiple());
  }

  /**
   * Imports the test source.
   */
  protected function import(): void {
    $this->container->get(ImporterInterface::class)->importSource('test_source', FALSE);
  }

  /**
   * Returns the alias stored for a node's path.
   */
  protected function aliasFor(NodeInterface $node): ?string {
    $alias = $this->container->get('path_alias.repository')->lookupBySystemPath('/node/' . $node->id(), 'en');
    return $alias['alias'] ?? NULL;
  }

  /**
   * Loads a node by UUID, bypassing the static cache.
   */
  protected function loadNode(string $uuid): NodeInterface {
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache();
    $node = $this->container->get('entity.repository')->loadEntityByUuid('node', $uuid);
    $this->assertInstanceOf(NodeInterface::class, $node);
    return $node;
  }

}
