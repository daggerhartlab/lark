<?php

declare(strict_types=1);

namespace Drupal\Tests\lark\Kernel\Service;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\lark\Entity\LarkSource;
use Drupal\lark\Exception\LarkEntityNotFoundException;
use Drupal\lark\Model\ExportableStatus;
use Drupal\lark\Service\ExportableFactory;
use Drupal\lark\Service\ExportableFactoryInterface;
use Drupal\lark\Service\ExporterInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests \Drupal\lark\Service\ExportableFactory.
 */
#[CoversClass(ExportableFactory::class)]
#[Group('lark')]
#[RunTestsInSeparateProcesses]
class ExportableFactoryTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'lark', 'node', 'user', 'system', 'field', 'text', 'filter',
  ];

  /**
   * Export directories, keyed by source ID.
   *
   * @var string[]
   */
  protected array $directories = [];

  /**
   * Whether the factory's services were reset since the test's exports.
   *
   * @var bool
   */
  protected bool $freshFactory = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('lark_source');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['node', 'filter', 'lark']);
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_related',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'node'],
      'cardinality' => -1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_related',
      'entity_type' => 'node',
      'bundle' => 'article',
    ])->save();

    $this->createSource('first');
    $this->config('lark.settings')->set('default_source', 'first')->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    foreach ($this->directories as $directory) {
      $this->container->get('file_system')->deleteRecursive($directory);
    }
    parent::tearDown();
  }

  /**
   * Tests that a dependency shared by several entities is listed once, first.
   */
  public function testEntitiesShareDependency(): void {
    $shared = $this->createNode('Shared');
    $first = $this->createNode('First', [$shared]);
    $second = $this->createNode('Second', [$shared]);

    $exportables = $this->factory()->createFromEntitiesWithDependencies([$first, $second]);

    $this->assertSame([$shared->uuid(), $first->uuid(), $second->uuid()], array_keys($exportables));
  }

  /**
   * Tests building an exportable from a source for content not on the site.
   */
  public function testCreateFromSourceForNewContent(): void {
    $dependency = $this->createNode('Dependency');
    $node = $this->createNode('Exported', [$dependency]);
    $this->export('first', $node);
    $node->delete();

    $exportable = $this->factory()->createFromSource('first', $node->uuid());

    $this->assertTrue($exportable->entity()->isNew());
    $this->assertSame('Exported', $exportable->entity()->label());
    $this->assertSame([$dependency->uuid() => 'node'], $exportable->getDependencies());
    $this->assertSame(ExportableStatus::NotImported, $exportable->getStatus());
  }

  /**
   * Tests that a source export is matched to the entity already on the site.
   */
  public function testCreateFromSourceForExistingContent(): void {
    $node = $this->createNode('Exported');
    $this->export('first', $node);

    $exportable = $this->factory()->createFromSource('first', $node->uuid());

    $this->assertEquals($node->id(), $exportable->entity()->id());
    $this->assertSame(ExportableStatus::InSync, $exportable->getStatus());
  }

  /**
   * Tests that a UUID missing from a source has no exportable.
   */
  public function testCreateFromSourceForUnknownUuid(): void {
    $this->assertNull($this->factory()->createFromSource('first', 'not-a-real-uuid'));
  }

  /**
   * Tests finding an entity on the site by UUID.
   */
  public function testCreateFromUuidFindsEntity(): void {
    $node = $this->createNode('On the site');
    $this->assertEquals($node->id(), $this->factory()->createFromUuid($node->uuid())->entity()->id());
  }

  /**
   * Tests finding content only in an enabled source by UUID.
   */
  public function testCreateFromUuidFindsSourceExport(): void {
    $node = $this->createNode('Only exported');
    $this->export('first', $node);
    $node->delete();

    $exportable = $this->factory()->createFromUuid($node->uuid());

    $this->assertTrue($exportable->entity()->isNew());
    $this->assertSame('Only exported', $exportable->entity()->label());
  }

  /**
   * Tests that disabled sources aren't searched by UUID.
   */
  public function testCreateFromUuidSkipsDisabledSources(): void {
    $node = $this->createNode('Disabled source');
    $this->export('first', $node);
    $node->delete();
    LarkSource::load('first')->disable()->save();

    $this->expectException(LarkEntityNotFoundException::class);
    $this->factory()->createFromUuid($node->uuid());
  }

  /**
   * Creates and saves a source with its own directory.
   */
  protected function createSource(string $id): void {
    $this->directories[$id] = sys_get_temp_dir() . '/lark-test-factory-' . $id . '-' . uniqid();
    LarkSource::create([
      'id' => $id,
      'label' => $id,
      'directory' => $this->directories[$id],
    ])->save();
  }

  /**
   * Creates and saves a node.
   *
   * @param string $title
   *   The title.
   * @param \Drupal\node\NodeInterface[] $related
   *   Nodes to reference.
   */
  protected function createNode(string $title, array $related = []): NodeInterface {
    $node = Node::create([
      'type' => 'article',
      'title' => $title,
      'field_related' => array_map(fn (NodeInterface $node) => ['target_id' => $node->id()], $related),
    ]);
    $node->save();
    return $node;
  }

  /**
   * Exports a node to a source.
   */
  protected function export(string $source_id, NodeInterface $node): void {
    $this->container->get(ExporterInterface::class)->exportEntity($source_id, 'node', (int) $node->id(), FALSE);
  }

  /**
   * Returns a fresh exportable factory, as a new request would have.
   */
  protected function factory(): ExportableFactoryInterface {
    if (!$this->freshFactory) {
      $this->container->get('kernel')->resetContainer();
      $this->container = \Drupal::getContainer();
      $this->freshFactory = TRUE;
    }
    return $this->container->get(ExportableFactoryInterface::class);
  }

}
