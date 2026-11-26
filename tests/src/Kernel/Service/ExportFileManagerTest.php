<?php

namespace Drupal\Tests\lark\Kernel\Service;

use Drupal\Component\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use Drupal\lark\Model\ExportArray;
use Drupal\lark\Model\ExportCollection;
use Drupal\lark\Service\ExportFileManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests \Drupal\lark\Service\ExportFileManager.
 */
#[CoversClass(ExportFileManager::class)]
#[Group('lark')]
#[RunTestsInSeparateProcesses]
class ExportFileManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['lark', 'user', 'system'];

  /**
   * The absolute path of the export directory used by prune tests.
   *
   * @var string
   */
  protected string $exportDir;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->exportDir = sys_get_temp_dir() . '/lark-test-prune-' . uniqid();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if (is_dir($this->exportDir)) {
      $this->container->get('file_system')->deleteRecursive($this->exportDir);
    }
    parent::tearDown();
  }

  /**
   * Tests that a missing directory yields an empty collection.
   */
  public function testDiscoverExportsReturnsEmptyCollectionForMissingDirectory(): void {
    /** @var \Drupal\lark\Service\ExportFileManager $manager */
    $manager = $this->container->get(ExportFileManager::class);
    $collection = $manager->discoverExports('/nonexistent/path');
    $this->assertCount(0, $collection);
  }

  /**
   * Tests that discovered exports are sorted with dependencies first.
   */
  public function testDiscoverExportsSortsDependenciesFirst(): void {
    /** @var \Drupal\lark\Service\ExportFileManager $manager */
    $manager = $this->container->get(ExportFileManager::class);
    $fixtures = __DIR__ . '/../../../fixtures/exports';
    $collection = $manager->discoverExports($fixtures);

    $uuids = array_keys($collection->getArrayCopy());
    $dep_pos = array_search('uuid-dep-0000-0000-000000000001', $uuids);
    $root_pos = array_search('uuid-root-0000-0000-000000000002', $uuids);

    $this->assertNotFalse($dep_pos, 'Dependency UUID must appear in collection');
    $this->assertNotFalse($root_pos, 'Root UUID must appear in collection');
    $this->assertLessThan($root_pos, $dep_pos, 'Dependency must be sorted before the entity that depends on it');
  }

  /**
   * Tests that only exports referencing the removal set are reported.
   */
  public function testFindExportsReferencingRemovalSetReportsOnlyReferencers(): void {
    /** @var \Drupal\lark\Service\ExportFileManager $manager */
    $manager = $this->container->get(ExportFileManager::class);

    // A: the removal target.
    $a = new ExportArray([
      '_meta' => [
        'entity_type' => 'node',
        'bundle' => 'page',
        'uuid' => 'uuid-a',
        'label' => 'A',
      ],
    ]);

    // B: outside the removal set, references A. Must be reported.
    $b = new ExportArray([
      '_meta' => [
        'entity_type' => 'node',
        'bundle' => 'page',
        'uuid' => 'uuid-b',
        'label' => 'B',
        'references' => ['uuid-a' => 'node'],
      ],
    ]);

    // C: outside the removal set, merely depends on A. Must not be reported.
    $c = new ExportArray([
      '_meta' => [
        'entity_type' => 'node',
        'bundle' => 'page',
        'uuid' => 'uuid-c',
        'label' => 'C',
        'depends' => ['uuid-a' => 'node'],
      ],
    ]);

    // D: inside the removal set, references A. Must not be reported against
    // itself - it is already being removed.
    $d = new ExportArray([
      '_meta' => [
        'entity_type' => 'node',
        'bundle' => 'page',
        'uuid' => 'uuid-d',
        'label' => 'D',
        'depends' => ['uuid-a' => 'node'],
        'references' => ['uuid-a' => 'node'],
      ],
    ]);

    $all_exports = new ExportCollection([$a, $b, $c, $d]);
    $removal_candidates = new ExportCollection([$a, $d]);

    $result = $manager->findExportsReferencingRemovalSet($all_exports, $removal_candidates);

    $this->assertCount(1, $result);
    $this->assertTrue($result->has('uuid-b'));
    $this->assertFalse($result->has('uuid-c'), 'A dependency-only export must not be reported.');
    $this->assertFalse($result->has('uuid-d'), 'An export already in the removal set must not be reported against itself.');
  }

  /**
   * Tests that removing an export removes the dependencies only it uses.
   */
  public function testRemoveExportWithDependencies(): void {
    $this->writePruneExports();

    $removed = $this->manager()->removeExportWithDependencies($this->exportDir, 'uuid-root');

    // The root, its own node dependency, and the file with its asset.
    $this->assertSame(4, $removed);
    $this->assertFileDoesNotExist($this->exportDir . '/node/root.yml');
    $this->assertFileDoesNotExist($this->exportDir . '/node/own.yml');
    $this->assertFileDoesNotExist($this->exportDir . '/file/file.yml');
    $this->assertFileDoesNotExist($this->exportDir . '/file/uuid-file--photo.jpg');
    $this->assertFileExists($this->exportDir . '/node/shared.yml');
    $this->assertFileExists($this->exportDir . '/node/other.yml');
  }

  /**
   * Tests that an export another export depends on isn't removed.
   */
  public function testRemoveExportKeepsDependencyOfAnotherExport(): void {
    $this->writePruneExports();

    $removed = $this->manager()->removeExportWithDependencies($this->exportDir, 'uuid-own');

    $this->assertSame(0, $removed);
    $this->assertFileExists($this->exportDir . '/node/own.yml');
  }

  /**
   * Tests that a kept dependency keeps its own dependencies.
   */
  public function testRemoveExportKeepsDependenciesOfKeptExports(): void {
    // The root and another export both depend on a node, which depends on a
    // leaf. The shared node stays, so its leaf must stay too.
    $this->writeExport('node/root.yml', 'uuid-root', 'node', ['uuid-shared' => 'node']);
    $this->writeExport('node/other.yml', 'uuid-other', 'node', ['uuid-shared' => 'node']);
    $this->writeExport('node/shared.yml', 'uuid-shared', 'node', ['uuid-leaf' => 'node']);
    $this->writeExport('node/leaf.yml', 'uuid-leaf', 'node');

    $removed = $this->manager()->removeExportWithDependencies($this->exportDir, 'uuid-root');

    $this->assertSame(1, $removed);
    $this->assertFileDoesNotExist($this->exportDir . '/node/root.yml');
    $this->assertFileExists($this->exportDir . '/node/shared.yml');
    $this->assertFileExists($this->exportDir . '/node/leaf.yml');
  }

  /**
   * Tests that removing an unknown export removes nothing.
   */
  public function testRemoveUnknownExport(): void {
    $this->writePruneExports();
    $this->assertSame(0, $this->manager()->removeExportWithDependencies($this->exportDir, 'uuid-missing'));
    $this->assertCount(5, glob($this->exportDir . '/*/*.yml'));
  }

  /**
   * Writes exports for the prune tests.
   *
   * The root depends on its own node, a file with an exported asset, and a
   * node it shares with another export.
   */
  protected function writePruneExports(): void {
    $this->writeExport('node/root.yml', 'uuid-root', 'node', [
      'uuid-own' => 'node',
      'uuid-file' => 'file',
      'uuid-shared' => 'node',
    ]);
    $this->writeExport('node/own.yml', 'uuid-own', 'node');
    $this->writeExport('node/shared.yml', 'uuid-shared', 'node');
    $this->writeExport('node/other.yml', 'uuid-other', 'node', ['uuid-shared' => 'node']);
    $this->writeExport('file/file.yml', 'uuid-file', 'file', [], [
      'uri' => [['value' => 'public://photo.jpg']],
    ]);
    file_put_contents($this->exportDir . '/file/uuid-file--photo.jpg', 'asset');
  }

  /**
   * Writes an export file.
   *
   * @param string $path
   *   Path relative to the export directory.
   * @param string $uuid
   *   The export's UUID.
   * @param string $entity_type
   *   The entity type ID.
   * @param array $depends
   *   Dependencies, keyed by UUID.
   * @param array $fields
   *   Default-language field values.
   */
  protected function writeExport(string $path, string $uuid, string $entity_type, array $depends = [], array $fields = []): void {
    $filepath = $this->exportDir . '/' . $path;
    if (!is_dir(dirname($filepath))) {
      mkdir(dirname($filepath), 0777, TRUE);
    }
    file_put_contents($filepath, Yaml::encode([
      '_meta' => [
        'entity_type' => $entity_type,
        'bundle' => $entity_type,
        'uuid' => $uuid,
        'default_langcode' => 'en',
        'depends' => $depends,
      ],
      'default' => $fields,
    ]));
  }

  /**
   * Returns the export file manager.
   */
  protected function manager(): ExportFileManager {
    return $this->container->get(ExportFileManager::class);
  }

}
