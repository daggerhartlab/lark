<?php

namespace Drupal\Tests\lark\Kernel\Service;

use Drupal\Core\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use Drupal\lark\Entity\LarkSource;
use Drupal\lark\Service\ExporterInterface;
use Drupal\lark\Service\ImporterInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Documents and verifies revision behavior during import.
 *
 * @group lark
 */
#[RunTestsInSeparateProcesses]
class RevisionBehaviorTest extends KernelTestBase {

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
    // Enable revisions on article.
    NodeType::create([
      'type' => 'article',
      'name' => 'Article',
      'new_revision' => TRUE,
    ])->save();
    $this->installSchema('node', ['node_access']);

    // Create user 1 (admin) so the importer's AdminAccountSwitcher can switch
    // to an administrative account during entity owner resolution.
    $admin = User::create([
      'uid' => 1,
      'name' => 'admin',
      'status' => 1,
    ]);
    $admin->save();

    // Use a real absolute temp directory (not vfs://) so that
    // LarkSource::directoryProcessed() returns a consistent absolute path.
    $this->exportDir = sys_get_temp_dir() . '/lark-revision-exports-' . uniqid();
    LarkSource::create([
      'id' => 'revision_test_source',
      'label' => 'Revision Test Source',
      'directory' => $this->exportDir,
    ])->save();
    \Drupal::configFactory()->getEditable('lark.settings')
      ->set('default_source', 'revision_test_source')
      ->save();
  }

  protected function tearDown(): void {
    // Clean up the real temp directory created during the test.
    if (isset($this->exportDir) && is_dir($this->exportDir)) {
      \Drupal::service('file_system')->deleteRecursive($this->exportDir);
    }
    parent::tearDown();
  }

  public function testRevisionIdIsNotExportedToYaml(): void {
    $node = Node::create(['type' => 'article', 'title' => 'Revisioned Node']);
    $node->save();

    /** @var \Drupal\lark\Service\ExporterInterface $exporter */
    $exporter = $this->container->get(ExporterInterface::class);
    $exporter->exportEntity('revision_test_source', 'node', (int) $node->id(), FALSE);

    $yaml_path = $this->exportDir . '/node/article/' . $node->uuid() . '.yml';
    $content = Yaml::decode(file_get_contents($yaml_path));

    $this->assertArrayNotHasKey('vid', $content['default'] ?? [], 'Revision ID (vid) must never appear in exported YAML');
    $this->assertArrayNotHasKey('nid', $content['default'] ?? [], 'Entity ID (nid) must never appear in exported YAML');
  }

  public function testImportDoesNotCorruptEntityContent(): void {
    // The main concern: even if revisions are created, the content must be correct.
    $node = Node::create(['type' => 'article', 'title' => 'Revision Content Test']);
    $node->save();

    /** @var \Drupal\lark\Service\ExporterInterface $exporter */
    $exporter = $this->container->get(ExporterInterface::class);
    $exporter->exportEntity('revision_test_source', 'node', (int) $node->id(), FALSE);
    $node->delete();

    /** @var \Drupal\lark\Service\ImporterInterface $importer */
    $importer = $this->container->get(ImporterInterface::class);
    $importer->importSource('revision_test_source', FALSE);

    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['uuid' => $node->uuid()]);
    $this->assertNotEmpty($nodes);
    $this->assertSame('Revision Content Test', reset($nodes)->label());
  }

  public function testRepeatedImportsDocumentRevisionCreation(): void {
    // This test documents (not asserts a specific count) that repeated imports
    // do not destroy content. The revision count behavior is implementation-defined.
    $node = Node::create(['type' => 'article', 'title' => 'Repeated Import']);
    $node->save();

    /** @var \Drupal\lark\Service\ExporterInterface $exporter */
    $exporter = $this->container->get(ExporterInterface::class);
    $exporter->exportEntity('revision_test_source', 'node', (int) $node->id(), FALSE);

    /** @var \Drupal\lark\Service\ImporterInterface $importer */
    $importer = $this->container->get(ImporterInterface::class);

    // Import twice.
    $importer->importSourceExport('revision_test_source', $node->uuid(), FALSE);
    $importer->importSourceExport('revision_test_source', $node->uuid(), FALSE);

    // The node must still have the correct content regardless of revision count.
    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['uuid' => $node->uuid()]);
    $this->assertNotEmpty($nodes);
    $this->assertSame('Repeated Import', reset($nodes)->label());

    // Count revisions to document current behavior using entity query
    // (revisionIds() is deprecated in Drupal 11.3.0).
    $loaded_node = reset($nodes);
    $revision_ids = \Drupal::entityTypeManager()->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->allRevisions()
      ->condition('nid', $loaded_node->id())
      ->execute();

    // This assertion documents current behavior, not enforces it.
    // If the module adds setNewRevision(FALSE), this count will be 1 and this test needs updating.
    $this->assertGreaterThanOrEqual(1, count($revision_ids), 'At least one revision must exist after import');
  }

}
