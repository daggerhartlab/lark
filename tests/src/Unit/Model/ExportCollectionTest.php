<?php

namespace Drupal\Tests\lark\Unit\Model;

use Drupal\lark\Model\ExportArray;
use Drupal\lark\Model\ExportCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests \Drupal\lark\Model\ExportCollection.
 */
#[CoversClass(ExportCollection::class)]
#[Group('lark')]
class ExportCollectionTest extends TestCase {

  /**
   * Creates a node export.
   *
   * @param string $uuid
   *   The export's UUID.
   * @param array $deps
   *   Dependencies, keyed by UUID with entity type id values.
   *
   * @return \Drupal\lark\Model\ExportArray
   *   The export.
   */
  private function makeExport(string $uuid, array $deps = []): ExportArray {
    $export = new ExportArray([
      '_meta' => [
        'uuid' => $uuid,
        'entity_type' => 'node',
        'bundle' => 'article',
        'default_langcode' => 'en',
        'depends' => $deps,
      ],
    ]);
    return $export;
  }

  /**
   * Tests that the constructor rejects values that aren't ExportArrays.
   */
  public function testRejectsNonExportArrayValues(): void {
    $this->expectException(\InvalidArgumentException::class);
    new ExportCollection(['not-an-export-array']);
  }

  /**
   * Tests that has() finds an added export and not a missing one.
   */
  public function testAddAndHas(): void {
    $collection = new ExportCollection();
    $export = $this->makeExport('uuid-1');
    $collection->add($export);
    $this->assertTrue($collection->has('uuid-1'));
    $this->assertFalse($collection->has('uuid-999'));
  }

  /**
   * Tests that remove() drops an export from the collection.
   */
  public function testRemove(): void {
    $collection = new ExportCollection();
    $collection->add($this->makeExport('uuid-1'));
    $collection->remove('uuid-1');
    $this->assertFalse($collection->has('uuid-1'));
  }

  /**
   * Tests that diff() keeps only exports missing from the other collection.
   */
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

  /**
   * Tests that filter() keeps only exports matching the callback.
   */
  public function testFilter(): void {
    $collection = new ExportCollection();
    $collection->add($this->makeExport('uuid-1'));
    $collection->add($this->makeExport('uuid-2'));
    $filtered = $collection->filter(fn($export) => $export->uuid() === 'uuid-1');
    $this->assertTrue($filtered->has('uuid-1'));
    $this->assertFalse($filtered->has('uuid-2'));
  }

  /**
   * Tests that getWithDependencies() orders dependencies before dependents.
   */
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

  /**
   * Tests that getRootLevel() excludes exports another export depends on.
   */
  public function testGetRootLevel(): void {
    $collection = new ExportCollection();
    $collection->add($this->makeExport('uuid-dep'));
    $collection->add($this->makeExport('uuid-root', ['uuid-dep' => 'node']));
    $roots = $collection->getRootLevel();
    $this->assertTrue($roots->has('uuid-root'));
    $this->assertFalse($roots->has('uuid-dep'));
  }

  /**
   * Tests that getWithDependencies() doesn't follow soft references.
   */
  public function testGetWithDependenciesIgnoresReferences(): void {
    $root = $this->makeExport('root-uuid');
    $root->addReference('referenced-uuid', 'node');
    $referenced = $this->makeExport('referenced-uuid');

    $collection = new ExportCollection();
    $collection->add($root);
    $collection->add($referenced);

    $result = $collection->getWithDependencies('root-uuid');

    $this->assertTrue($result->has('root-uuid'));
    $this->assertFalse(
      $result->has('referenced-uuid'),
      'A soft reference must never pull another export into the set.'
    );
  }

  /**
   * Tests that getWithDependencies() orders ancestors before descendants.
   */
  public function testGetWithDependenciesOrdersAncestorsFirst(): void {
    // Grandparent <- parent <- child, the shape a nested menu produces.
    $grandparent = $this->makeExport('gp-uuid');
    $parent = $this->makeExport('parent-uuid', ['gp-uuid' => 'menu_link_content']);
    $child = $this->makeExport('child-uuid', [
      'parent-uuid' => 'menu_link_content',
      'gp-uuid' => 'menu_link_content',
    ]);

    $collection = new ExportCollection();
    $collection->add($child);
    $collection->add($parent);
    $collection->add($grandparent);

    $order = array_keys($collection->getWithDependencies('child-uuid')->getArrayCopy());

    $this->assertSame(
      ['gp-uuid', 'parent-uuid', 'child-uuid'],
      $order,
      'Import order must create an ancestor before any descendant that depends on it.'
    );
  }

}
