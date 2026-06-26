<?php

namespace Drupal\Tests\lark\Kernel\Service;

use Drupal\KernelTests\KernelTestBase;
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

}
