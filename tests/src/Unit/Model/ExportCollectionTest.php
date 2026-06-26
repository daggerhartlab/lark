<?php

namespace Drupal\Tests\lark\Unit\Model;

use Drupal\lark\Model\ExportArray;
use Drupal\lark\Model\ExportCollection;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\lark\Model\ExportCollection
 * @group lark
 */
class ExportCollectionTest extends TestCase {

  private function makeExport(string $uuid, array $deps = []): ExportArray {
    $export = new ExportArray(['_meta' => ['uuid' => $uuid, 'entity_type' => 'node', 'bundle' => 'article', 'default_langcode' => 'en', 'depends' => $deps]]);
    return $export;
  }

  public function testRejectsNonExportArrayValues(): void {
    $this->expectException(\InvalidArgumentException::class);
    new ExportCollection(['not-an-export-array']);
  }

  public function testAddAndHas(): void {
    $collection = new ExportCollection();
    $export = $this->makeExport('uuid-1');
    $collection->add($export);
    $this->assertTrue($collection->has('uuid-1'));
    $this->assertFalse($collection->has('uuid-999'));
  }

  public function testRemove(): void {
    $collection = new ExportCollection();
    $collection->add($this->makeExport('uuid-1'));
    $collection->remove('uuid-1');
    $this->assertFalse($collection->has('uuid-1'));
  }

  public function testDiff(): void {
    $a = new ExportCollection();
    $a->add($this->makeExport('uuid-1'));
    $a->add($this->makeExport('uuid-2'));
    $b = new ExportCollection();
    $b->add($this->makeExport('uuid-1'));
    $diff = $a->diff($b);
    $this->assertTrue($diff->has('uuid-2'));
    $this->assertFalse($diff->has('uuid-1'));
  }

  public function testFilter(): void {
    $collection = new ExportCollection();
    $collection->add($this->makeExport('uuid-1'));
    $collection->add($this->makeExport('uuid-2'));
    $filtered = $collection->filter(fn($export) => $export->uuid() === 'uuid-1');
    $this->assertTrue($filtered->has('uuid-1'));
    $this->assertFalse($filtered->has('uuid-2'));
  }

  public function testGetWithDependenciesReturnsCorrectOrder(): void {
    // A depends on B. B depends on C. Expected order: C, B, A.
    $collection = new ExportCollection();
    $collection->add($this->makeExport('uuid-c'));
    $collection->add($this->makeExport('uuid-b', ['uuid-c' => 'node']));
    $collection->add($this->makeExport('uuid-a', ['uuid-b' => 'node']));

    $result = $collection->getWithDependencies('uuid-a');
    $uuids = array_keys($result->getArrayCopy());
    // C must appear before B, B before A.
    $this->assertSame(['uuid-c', 'uuid-b', 'uuid-a'], $uuids);
  }

  public function testGetRootLevel(): void {
    $collection = new ExportCollection();
    $collection->add($this->makeExport('uuid-dep'));
    $collection->add($this->makeExport('uuid-root', ['uuid-dep' => 'node']));
    $roots = $collection->getRootLevel();
    $this->assertTrue($roots->has('uuid-root'));
    $this->assertFalse($roots->has('uuid-dep'));
  }

}