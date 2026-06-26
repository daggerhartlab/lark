<?php

namespace Drupal\Tests\lark\Kernel\Service;

use Drupal\KernelTests\KernelTestBase;
use Drupal\lark\Entity\LarkSource;
use Drupal\lark\Service\ExporterInterface;
use Drupal\lark\Service\ImporterInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * @coversDefaultClass \Drupal\lark\Service\Importer
 * @group lark
 */
#[RunTestsInSeparateProcesses]
class ImporterTest extends KernelTestBase {

  /**
   * Disable strict config schema to avoid lark.settings integer/string mismatch.
   */
  protected $strictConfigSchema = FALSE;

  protected static $modules = [
    'lark', 'node', 'user', 'system', 'field', 'text', 'filter',
  ];

  protected string $exportDir;

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

  protected function tearDown(): void {
    // Clean up the real temp directory created during the test.
    if (isset($this->exportDir) && is_dir($this->exportDir)) {
      \Drupal::service('file_system')->deleteRecursive($this->exportDir);
    }
    parent::tearDown();
  }

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
    $this->assertSame('Roundtrip Node', $reimported->label());
  }

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

}
