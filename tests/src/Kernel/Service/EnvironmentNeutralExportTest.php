<?php

declare(strict_types=1);

namespace Drupal\Tests\lark\Kernel\Service;

use Drupal\Component\Serialization\Yaml;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\lark\Entity\LarkSource;
use Drupal\lark\Model\ExportableStatus;
use Drupal\lark\Plugin\Lark\FieldTypeHandler\EntityReferenceUuidHandler;
use Drupal\lark\Plugin\Lark\FieldTypeHandler\PathHandler;
use Drupal\lark\Service\ExportableFactoryInterface;
use Drupal\lark\Service\ExporterInterface;
use Drupal\lark\Service\ImporterInterface;
use Drupal\lark\Service\Utility\StatusResolver;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that exports hold no values that differ between environments.
 */
#[CoversClass(EntityReferenceUuidHandler::class)]
#[CoversClass(PathHandler::class)]
#[CoversClass(StatusResolver::class)]
#[Group('lark')]
#[RunTestsInSeparateProcesses]
class EnvironmentNeutralExportTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'lark', 'node', 'user', 'system', 'field', 'text', 'filter', 'file',
    'image', 'path', 'path_alias', 'paragraphs', 'entity_reference_revisions',
  ];

  /**
   * The absolute path of the export directory.
   *
   * @var string
   */
  protected string $exportDir;

  /**
   * The exported node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected NodeInterface $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('lark_source');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['node', 'filter', 'image', 'lark']);

    // The importer switches to user 1 to resolve entity owners.
    User::create(['uid' => 1, 'name' => 'admin', 'status' => 1])->save();
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    ParagraphsType::create(['id' => 'text_block', 'label' => 'Text block'])->save();
    $this->createField('field_image', 'image', ['target_type' => 'file']);
    $this->createField('field_file', 'file', ['target_type' => 'file']);
    $this->createField('field_related', 'entity_reference', ['target_type' => 'node']);
    $this->createField('field_paragraphs', 'entity_reference_revisions', ['target_type' => 'paragraph']);

    $this->exportDir = sys_get_temp_dir() . '/lark-test-neutral-' . uniqid();
    LarkSource::create([
      'id' => 'test_source',
      'label' => 'Test Source',
      'directory' => $this->exportDir,
    ])->save();
    $this->config('lark.settings')->set('default_source', 'test_source')->save();

    $related = Node::create(['type' => 'article', 'title' => 'Related']);
    $related->save();
    $paragraph = Paragraph::create(['type' => 'text_block']);
    $paragraph->save();
    $this->node = Node::create([
      'type' => 'article',
      'title' => 'Exported',
      'path' => ['alias' => '/exported'],
      'field_image' => [
        'target_id' => $this->createFile('image.png')->id(),
        'alt' => 'Original alt',
        'title' => 'Original title',
        'width' => 10,
        'height' => 20,
      ],
      'field_file' => [
        'target_id' => $this->createFile('file.txt')->id(),
        'description' => 'Original description',
        'display' => 1,
      ],
      'field_related' => ['target_id' => $related->id()],
      'field_paragraphs' => [$paragraph],
    ]);
    $this->node->save();

    // Start from fresh services, as a later request would: field definitions
    // created in this request are ordered differently until reloaded.
    $this->container->get('kernel')->resetContainer();
    $this->container = \Drupal::getContainer();
    $this->node = $this->container->get('entity_type.manager')->getStorage('node')->load($this->node->id());
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
   * Tests that the export holds no database IDs.
   */
  public function testExportHasNoDatabaseIds(): void {
    $this->export();
    $export = $this->readExport();

    $this->assertArrayNotHasKey('entity_id', $export['_meta']);
    foreach (['field_image', 'field_file', 'field_related', 'field_paragraphs', 'path'] as $field_name) {
      $item = $export['default'][$field_name][0];
      $original = $item['original_values'] ?? [];
      foreach (['target_id', 'target_revision_id', 'pid'] as $id_key) {
        $this->assertArrayNotHasKey($id_key, $item, "$field_name has $id_key");
        $this->assertArrayNotHasKey($id_key, $original, "$field_name original_values has $id_key");
      }
    }
  }

  /**
   * Tests that reference properties other than IDs are still exported.
   */
  public function testExportKeepsReferenceProperties(): void {
    $this->export();
    $export = $this->readExport();

    $this->assertEquals([
      'alt' => 'Original alt',
      'title' => 'Original title',
      'width' => 10,
      'height' => 20,
    ], $export['default']['field_image'][0]['original_values']);
    $this->assertSame('Original description', $export['default']['field_file'][0]['original_values']['description']);
  }

  /**
   * Tests that reference properties survive an export and import.
   */
  public function testReferencePropertiesRoundTrip(): void {
    $this->export();
    $uuid = $this->node->uuid();
    $this->node->delete();

    $this->container->get(ImporterInterface::class)->importSource('test_source', FALSE);

    $node = $this->container->get('entity.repository')->loadEntityByUuid('node', $uuid);
    $this->assertInstanceOf(NodeInterface::class, $node);
    $this->assertSame('Original alt', $node->get('field_image')->alt);
    $this->assertSame('Original title', $node->get('field_image')->title);
    $this->assertSame('Original description', $node->get('field_file')->description);
  }

  /**
   * Tests that changing an image's alt text marks the export out of sync.
   */
  public function testChangedAltTextIsOutOfSync(): void {
    $this->export();
    $this->assertSame(ExportableStatus::InSync, $this->exportStatus());

    $this->node->get('field_image')->alt = 'Changed alt';
    $this->node->save();

    $this->assertSame(ExportableStatus::OutOfSync, $this->exportStatus());
  }

  /**
   * Tests that an export written with database IDs still counts as in sync.
   */
  public function testExportWithDatabaseIdsIsInSync(): void {
    $this->export();
    $export = $this->readExport();
    $export['_meta']['entity_id'] = '999';
    $export['default']['field_image'][0]['original_values']['target_id'] = '999';
    $export['default']['field_paragraphs'][0]['original_values'] = [
      'target_id' => '999',
      'target_revision_id' => '999',
    ];
    $export['default']['path'][0]['original_values']['pid'] = '999';
    file_put_contents($this->exportPath(), Yaml::encode($export));

    $this->assertSame(ExportableStatus::InSync, $this->exportStatus());
  }

  /**
   * Exports the node.
   */
  protected function export(): void {
    $this->container->get(ExporterInterface::class)
      ->exportEntity('test_source', 'node', (int) $this->node->id(), FALSE);
  }

  /**
   * Returns the node's export file path.
   */
  protected function exportPath(): string {
    return $this->exportDir . '/node/article/' . $this->node->uuid() . '.yml';
  }

  /**
   * Reads the node's export.
   */
  protected function readExport(): array {
    return Yaml::decode(file_get_contents($this->exportPath()));
  }

  /**
   * Resolves the node's status against its export, as a new request would.
   */
  protected function exportStatus(): ExportableStatus {
    $this->container->get('kernel')->resetContainer();
    $this->container = \Drupal::getContainer();
    $node = $this->container->get('entity_type.manager')->getStorage('node')->load($this->node->id());
    $exportable = $this->container->get(ExportableFactoryInterface::class)->createFromEntity($node);
    return $this->container->get(StatusResolver::class)->resolveStatus($exportable);
  }

  /**
   * Creates a field on the article node type.
   */
  protected function createField(string $name, string $type, array $settings): void {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => 'node',
      'type' => $type,
      'settings' => $settings,
    ])->save();
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'node',
      'bundle' => 'article',
    ])->save();
  }

  /**
   * Creates a permanent file.
   */
  protected function createFile(string $name): FileInterface {
    file_put_contents("public://$name", 'contents');
    $file = File::create(['uri' => "public://$name"]);
    $file->setPermanent();
    $file->save();
    return $file;
  }

}
