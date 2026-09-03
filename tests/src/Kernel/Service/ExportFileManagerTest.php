<?php

namespace Drupal\Tests\lark\Kernel\Service;

use Drupal\KernelTests\KernelTestBase;
use Drupal\lark\Model\ExportArray;
use Drupal\lark\Model\ExportCollection;
use Drupal\lark\Service\ExportFileManager;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * @coversDefaultClass \Drupal\lark\Service\ExportFileManager
 * @group lark
 */
#[RunTestsInSeparateProcesses]
class ExportFileManagerTest extends KernelTestBase {

  protected static $modules = ['lark', 'user', 'system'];

  public function testDiscoverExportsReturnsEmptyCollectionForMissingDirectory(): void {
    /** @var \Drupal\lark\Service\ExportFileManager $manager */
    $manager = $this->container->get(ExportFileManager::class);
    $collection = $manager->discoverExports('/nonexistent/path');
    $this->assertCount(0, $collection);
  }

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
   * @covers ::findExportsReferencingRemovalSet
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

}
