<?php

namespace Drupal\Tests\lark\Kernel\Service;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Archiver\ArchiveTar;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\lark\Entity\LarkSource;
use Drupal\lark\Service\ExporterInterface;
use Drupal\lark\Service\Importer;
use Drupal\lark\Service\ImporterInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests \Drupal\lark\Service\Importer.
 */
#[CoversClass(Importer::class)]
#[Group('lark')]
#[RunTestsInSeparateProcesses]
class ImporterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'lark', 'node', 'user', 'system', 'field', 'text', 'filter',
  ];

  /**
   * The absolute path of the temporary export directory.
   *
   * @var string
   */
  protected string $exportDir;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('lark_source');
    $this->installConfig(['node', 'filter', 'lark']);
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    $this->installSchema('node', ['node_access']);

    // Use a real absolute temp directory (not vfs://) so that
    // LarkSource::directoryProcessed() returns a consistent absolute path.
    // Create user 1 (admin) so the importer's AdminAccountSwitcher can switch
    // to an administrative account during entity owner resolution.
    $admin = User::create([
      'uid' => 1,
      'name' => 'admin',
      'status' => 1,
    ]);
    $admin->save();

    $this->exportDir = sys_get_temp_dir() . '/lark-test-exports-' . uniqid();
    LarkSource::create([
      'id' => 'test_source',
      'label' => 'Test Source',
      'directory' => $this->exportDir,
    ])->save();

    \Drupal::configFactory()->getEditable('lark.settings')
      ->set('default_source', 'test_source')
      ->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    // Clean up the real temp directory created during the test.
    if (isset($this->exportDir) && is_dir($this->exportDir)) {
      \Drupal::service('file_system')->deleteRecursive($this->exportDir);
    }
    parent::tearDown();
  }

  /**
   * Tests that a deleted node is recreated by importing its export.
   */
  public function testExportThenImportRoundtrip(): void {
    // Create, export, delete, re-import.
    $node = Node::create(['type' => 'article', 'title' => 'Roundtrip Node']);
    $node->save();
    $original_uuid = $node->uuid();

    /** @var \Drupal\lark\Service\ExporterInterface $exporter */
    $exporter = $this->container->get(ExporterInterface::class);
    $exporter->exportEntity('test_source', 'node', (int) $node->id(), FALSE);

    // Delete the node.
    $node->delete();
    $this->assertEmpty(\Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['uuid' => $original_uuid]), 'Node must not exist after deletion');

    // Re-import.
    /** @var \Drupal\lark\Service\ImporterInterface $importer */
    $importer = $this->container->get(ImporterInterface::class);
    $importer->importSource('test_source', FALSE);

    // The node should exist again with the same UUID and title.
    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['uuid' => $original_uuid]);
    $this->assertNotEmpty($nodes, 'Node must be re-created by import');
    $reimported = reset($nodes);
    $this->assertNotFalse($reimported);
    $this->assertSame('Roundtrip Node', $reimported->label());
  }

  /**
   * Tests that an imported entity keeps its exported UUID.
   */
  public function testImportPreservesUuid(): void {
    $node = Node::create(['type' => 'article', 'title' => 'UUID Preserved']);
    $node->save();
    $original_uuid = $node->uuid();

    /** @var \Drupal\lark\Service\ExporterInterface $exporter */
    $exporter = $this->container->get(ExporterInterface::class);
    $exporter->exportEntity('test_source', 'node', (int) $node->id(), FALSE);
    $node->delete();

    /** @var \Drupal\lark\Service\ImporterInterface $importer */
    $importer = $this->container->get(ImporterInterface::class);
    $importer->importSource('test_source', FALSE);

    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['uuid' => $original_uuid]);
    $this->assertNotEmpty($nodes);
    $this->assertSame($original_uuid, reset($nodes)->uuid());
  }

  /**
   * Tests that import reverts an existing entity to its exported values.
   */
  public function testImportUpdatesExistingEntity(): void {
    $node = Node::create(['type' => 'article', 'title' => 'Original Title']);
    $node->save();

    /** @var \Drupal\lark\Service\ExporterInterface $exporter */
    $exporter = $this->container->get(ExporterInterface::class);
    $exporter->exportEntity('test_source', 'node', (int) $node->id(), FALSE);

    // Change the title after export so the DB diverges from the YAML.
    $node->set('title', 'Changed Title');
    $node->save();

    // Re-import: should revert to the exported title.
    /** @var \Drupal\lark\Service\ImporterInterface $importer */
    $importer = $this->container->get(ImporterInterface::class);
    $importer->importSourceExport('test_source', $node->uuid(), FALSE);

    $reimported = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['uuid' => $node->uuid()]);
    $this->assertSame('Original Title', reset($reimported)->label());
  }

  /**
   * Tests that importing a list of UUIDs imports each of them.
   */
  public function testImportSourceExportsImportsEveryUuid(): void {
    /** @var \Drupal\lark\Service\ExporterInterface $exporter */
    $exporter = $this->container->get(ExporterInterface::class);
    /** @var \Drupal\lark\Service\ImporterInterface $importer */
    $importer = $this->container->get(ImporterInterface::class);

    $one = Node::create(['type' => 'article', 'title' => 'One']);
    $one->save();
    $two = Node::create(['type' => 'article', 'title' => 'Two']);
    $two->save();
    $one_uuid = $one->uuid();
    $two_uuid = $two->uuid();

    $exporter->exportEntities('test_source', [$one, $two], FALSE);
    $one->delete();
    $two->delete();

    $importer->importSourceExports('test_source', [$one_uuid, $two_uuid], FALSE);

    $repository = $this->container->get('entity.repository');
    $this->assertNotNull($repository->loadEntityByUuid('node', $one_uuid));
    $this->assertNotNull($repository->loadEntityByUuid('node', $two_uuid));
  }

  /**
   * Tests that a UUID absent from the source is warned about and skipped.
   */
  public function testImportSourceExportsSkipsUuidsAbsentFromTheSource(): void {
    /** @var \Drupal\lark\Service\ImporterInterface $importer */
    $importer = $this->container->get(ImporterInterface::class);

    // A UUID that was never exported must be reported and skipped, not fatal.
    $missing_uuid = '11111111-2222-3333-4444-555555555555';
    $importer->importSourceExports('test_source', [$missing_uuid], TRUE);

    $warnings = \Drupal::messenger()->messagesByType(MessengerInterface::TYPE_WARNING);
    $this->assertNotEmpty($warnings, 'A warning must be raised for a UUID absent from the source.');
    $this->assertStringContainsString(
      $missing_uuid,
      (string) reset($warnings),
      'The warning must name the missing UUID.'
    );

    $this->assertNull(
      \Drupal::service('entity.repository')->loadEntityByUuid('node', $missing_uuid),
      'A missing UUID must be skipped, not fatal, and nothing is created for it.'
    );
  }

  /**
   * Tests that importing an empty UUID list emits no messages.
   */
  public function testImportSourceExportsAcceptsAnEmptyList(): void {
    /** @var \Drupal\lark\Service\ImporterInterface $importer */
    $importer = $this->container->get(ImporterInterface::class);

    $importer->importSourceExports('test_source', [], TRUE);

    // An empty UUID list must be a silent no-op: no warnings, errors, or
    // bulk summary message - verifying this catches a regression where a
    // "Imported 0 entities" summary is emitted unconditionally.
    $this->assertSame([], \Drupal::messenger()->all());
  }

  /**
   * Tests importing an uploaded archive and removing its temporary source.
   */
  public function testImportArchive(): void {
    $node = $this->createExportedNode('From archive');
    $archive_path = sys_get_temp_dir() . '/lark-test-archive-' . uniqid() . '.tar.gz';
    (new ArchiveTar($archive_path, 'gz'))->createModify([$this->exportDir], '', $this->exportDir);
    $node->delete();

    try {
      $this->importer()->importArchive($archive_path, FALSE);
    }
    finally {
      unlink($archive_path);
    }

    $this->assertNodeExists($node->uuid());
    $this->assertNull(LarkSource::load('_tmp'));
    $this->assertDirectoryDoesNotExist($this->container->get('file_system')->getTempDirectory() . '/lark-archive');
  }

  /**
   * Tests that importing all sources skips disabled sources.
   */
  public function testImportSourcesAllSkipsDisabledSources(): void {
    $enabled = $this->createExportedNode('Enabled source');

    $disabled_dir = $this->exportDir . '-disabled';
    LarkSource::create([
      'id' => 'disabled_source',
      'label' => 'Disabled Source',
      'directory' => $disabled_dir,
      'status' => FALSE,
    ])->save();
    $disabled = Node::create(['type' => 'article', 'title' => 'Disabled source']);
    $disabled->save();
    $this->container->get(ExporterInterface::class)->exportEntity('disabled_source', 'node', (int) $disabled->id(), FALSE);

    $enabled->delete();
    $disabled->delete();

    try {
      $this->importer()->importSourcesAll(FALSE);
    }
    finally {
      $this->container->get('file_system')->deleteRecursive($disabled_dir);
    }

    $this->assertNodeExists($enabled->uuid());
    $this->assertNodeDoesNotExist($disabled->uuid());
  }

  /**
   * Tests that importing one export leaves the source's other exports alone.
   */
  public function testImportSourceExportImportsOnlyThatExport(): void {
    $wanted = $this->createExportedNode('Wanted');
    $other = $this->createExportedNode('Other');
    $wanted->delete();
    $other->delete();

    $this->importer()->importSourceExport('test_source', $wanted->uuid(), FALSE);

    $this->assertNodeExists($wanted->uuid());
    $this->assertNodeDoesNotExist($other->uuid());
  }

  /**
   * Tests that importing an unknown UUID reports it and imports nothing.
   */
  public function testImportSourceExportReportsUnknownUuid(): void {
    $node = $this->createExportedNode('Not requested');
    $node->delete();

    $this->importer()->importSourceExport('test_source', 'not-a-real-uuid', TRUE);

    $this->assertNodeDoesNotExist($node->uuid());
    $this->assertCount(1, \Drupal::messenger()->messagesByType(MessengerInterface::TYPE_ERROR));
  }

  /**
   * Tests that an export missing required metadata is reported, not imported.
   */
  public function testImportRejectsExportWithoutBundle(): void {
    $this->writeExport('node/article/invalid.yml', [
      '_meta' => [
        'entity_type' => 'node',
        'uuid' => 'uuid-invalid-0000-0000-000000000001',
        'default_langcode' => 'en',
      ],
      'default' => ['title' => [['value' => 'Invalid']]],
    ]);

    $this->importer()->importSource('test_source', TRUE);

    $this->assertNodeDoesNotExist('uuid-invalid-0000-0000-000000000001');
    $errors = \Drupal::messenger()->messagesByType(MessengerInterface::TYPE_ERROR);
    $this->assertCount(1, $errors);
    $this->assertStringContainsString('[_meta][bundle]', (string) $errors[0]);
  }

  /**
   * Tests that an export of a config entity is reported, not imported.
   */
  public function testImportRejectsConfigEntityExport(): void {
    $this->writeExport('lark_source/lark_source/config.yml', [
      '_meta' => [
        'entity_type' => 'lark_source',
        'bundle' => 'lark_source',
        'uuid' => 'uuid-config-0000-0000-000000000001',
        'default_langcode' => 'en',
      ],
      'default' => ['id' => [['value' => 'imported_source']]],
    ]);

    $this->importer()->importSource('test_source', TRUE);

    $this->assertNull(LarkSource::load('imported_source'));
    $errors = \Drupal::messenger()->messagesByType(MessengerInterface::TYPE_ERROR);
    $this->assertCount(1, $errors);
    $this->assertStringContainsString('Only content entities can be imported', (string) $errors[0]);
  }

  /**
   * Tests that an export in an unknown language imports in the site default.
   */
  public function testImportUnknownLanguageUsesSiteDefault(): void {
    $this->writeExport('node/article/unknown-language.yml', [
      '_meta' => [
        'entity_type' => 'node',
        'bundle' => 'article',
        'uuid' => 'uuid-lang-0000-0000-000000000001',
        'default_langcode' => 'xx',
      ],
      'default' => ['title' => [['value' => 'Unknown language']]],
    ]);

    $this->importer()->importSource('test_source', FALSE);

    $node = $this->assertNodeExists('uuid-lang-0000-0000-000000000001');
    $this->assertSame('en', $node->language()->getId());
  }

  /**
   * Creates a node and exports it to the test source.
   */
  protected function createExportedNode(string $title): Node {
    $node = Node::create(['type' => 'article', 'title' => $title]);
    $node->save();
    $this->container->get(ExporterInterface::class)->exportEntity('test_source', 'node', (int) $node->id(), FALSE);
    return $node;
  }

  /**
   * Writes an export file into the test source.
   *
   * @param string $path
   *   Path relative to the source directory.
   * @param array $export
   *   The export data.
   */
  protected function writeExport(string $path, array $export): void {
    $filepath = $this->exportDir . '/' . $path;
    $export['_meta']['path'] = $filepath;
    $directory = dirname($filepath);
    $this->container->get('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
    file_put_contents($filepath, Yaml::encode($export));
  }

  /**
   * Returns the importer.
   */
  protected function importer(): ImporterInterface {
    return $this->container->get(ImporterInterface::class);
  }

  /**
   * Asserts that a node with the UUID exists, and returns it.
   */
  protected function assertNodeExists(string $uuid): Node {
    $node = $this->container->get('entity.repository')->loadEntityByUuid('node', $uuid);
    $this->assertInstanceOf(Node::class, $node);
    return $node;
  }

  /**
   * Asserts that no node with the UUID exists.
   */
  protected function assertNodeDoesNotExist(string $uuid): void {
    $this->assertNull($this->container->get('entity.repository')->loadEntityByUuid('node', $uuid));
  }

}
