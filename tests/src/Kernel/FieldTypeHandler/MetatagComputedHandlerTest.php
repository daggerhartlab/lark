<?php

declare(strict_types=1);

namespace Drupal\Tests\lark\Kernel\FieldTypeHandler;

use Drupal\Component\Serialization\Yaml;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\lark\Entity\LarkSource;
use Drupal\lark\Plugin\Lark\FieldTypeHandler\MetatagComputedHandler;
use Drupal\lark\Service\ExporterInterface;
use Drupal\lark\Service\ImporterInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\lark\Traits\RequiresModuleTrait;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests exporting and importing meta tags.
 */
#[CoversClass(MetatagComputedHandler::class)]
#[Group('lark')]
#[RunTestsInSeparateProcesses]
class MetatagComputedHandlerTest extends KernelTestBase {

  use RequiresModuleTrait;

  /**
   * Tags set on the test node.
   */
  protected const array TAGS = [
    'description' => 'Custom description',
    'title' => 'Custom title',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'lark', 'node', 'user', 'system', 'field', 'text', 'filter',
    'token', 'metatag',
  ];

  /**
   * The absolute path of the export directory.
   *
   * @var string
   */
  protected string $exportDir;

  /**
   * The node with meta tags.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected NodeInterface $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->requireModules('token', 'metatag');
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('lark_source');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'node', 'filter', 'metatag', 'lark']);

    // The importer switches to user 1 to resolve entity owners.
    User::create(['uid' => 1, 'name' => 'admin', 'status' => 1])->save();
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_meta_tags',
      'entity_type' => 'node',
      'type' => 'metatag',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_meta_tags',
      'entity_type' => 'node',
      'bundle' => 'article',
    ])->save();

    $this->exportDir = sys_get_temp_dir() . '/lark-test-metatag-' . uniqid();
    LarkSource::create([
      'id' => 'test_source',
      'label' => 'Test Source',
      'directory' => $this->exportDir,
    ])->save();
    $this->config('lark.settings')->set('default_source', 'test_source')->save();

    $this->node = Node::create([
      'type' => 'article',
      'title' => 'Meta',
      'field_meta_tags' => ['value' => json_encode(static::TAGS)],
    ]);
    $this->node->save();
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
   * Tests that the export holds the node's own tags, not the defaults.
   */
  public function testExportIncludesEntityTags(): void {
    $this->export();
    $this->assertSame([static::TAGS], $this->exportedFields()['metatag']);
  }

  /**
   * Tests that no tags are exported without defaults for the entity type.
   */
  public function testExportSkipsTagsWithoutDefaults(): void {
    $this->container->get('entity_type.manager')->getStorage('metatag_defaults')->load('node')->delete();
    $this->export();
    $this->assertEmpty($this->exportedFields()['metatag'] ?? []);
  }

  /**
   * Tests that the node's tags survive an export and import.
   */
  public function testRoundTrip(): void {
    $this->export();
    $uuid = $this->node->uuid();
    $this->node->delete();

    $this->container->get(ImporterInterface::class)->importSource('test_source', TRUE);

    $this->assertSame([], \Drupal::messenger()->messagesByType('error'));
    $node = $this->container->get('entity.repository')->loadEntityByUuid('node', $uuid);
    $this->assertInstanceOf(NodeInterface::class, $node);
    $this->assertSame(static::TAGS, json_decode($node->get('field_meta_tags')->value, TRUE));
  }

  /**
   * Exports the test node.
   */
  protected function export(): void {
    $this->container->get(ExporterInterface::class)
      ->exportEntity('test_source', 'node', (int) $this->node->id(), FALSE);
  }

  /**
   * Returns the default-language fields of the node's export.
   */
  protected function exportedFields(): array {
    $path = $this->exportDir . '/node/article/' . $this->node->uuid() . '.yml';
    return Yaml::decode(file_get_contents($path))['default'];
  }

}
