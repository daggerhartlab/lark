<?php

namespace Drupal\Tests\lark\Unit\Model;

use Drupal\lark\Model\ExportArray;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests \Drupal\lark\Model\ExportArray.
 */
#[CoversClass(ExportArray::class)]
#[Group('lark')]
class ExportArrayTest extends TestCase {

  /**
   * Tests that an empty export returns empty schema defaults.
   */
  public function testEmptyExportFollowsSchema(): void {
    $export = new ExportArray();
    $this->assertTrue($export->isEmpty());
    $this->assertSame('', $export->uuid());
    $this->assertSame('', $export->entityTypeId());
    $this->assertSame([], $export->dependencies());
    $this->assertSame([], $export->translations());
  }

  /**
   * Tests that the meta setters return the export for chaining.
   */
  public function testSettersReturnSelf(): void {
    $export = new ExportArray();
    $result = $export->setUuid('abc-123')
      ->setEntityTypeId('node')
      ->setBundle('article')
      ->setDefaultLangcode('en');
    $this->assertSame($export, $result);
  }

  /**
   * Tests setting, getting, and unsetting a default-language field.
   */
  public function testFieldRoundtrip(): void {
    $export = new ExportArray();
    $export->setFields(['title' => [['value' => 'Hello']]]);
    $this->assertSame([['value' => 'Hello']], $export->getField('title'));
    $export->unsetField('title');
    $this->assertNull($export->getField('title'));
  }

  /**
   * Tests that translation fields are kept apart from the default language.
   */
  public function testTranslationFieldRoundtrip(): void {
    $export = new ExportArray();
    $export->setDefaultLangcode('en');
    $export->setFields(['title' => [['value' => 'Bonjour']]], 'fr');
    $this->assertSame([['value' => 'Bonjour']], $export->getField('title', 'fr'));
    $this->assertNull($export->getField('title', 'en'));
  }

  /**
   * Tests that removing a translation keeps the others.
   */
  public function testUnsetTranslation(): void {
    $export = new ExportArray();
    $export->setFields(['title' => [['value' => 'Bonjour']]], 'fr');
    $export->setFields(['title' => [['value' => 'Hola']]], 'es');

    $export->unsetTranslation('fr');

    $this->assertSame(['es'], array_keys($export->translations()));
    $this->assertSame([['value' => 'Hola']], $export->getField('title', 'es'));
  }

  /**
   * Tests adding, reading, and removing a dependency.
   */
  public function testDependencyManagement(): void {
    $export = new ExportArray();
    $export->addDependency('uuid-1', 'node');
    $this->assertTrue($export->hasDependency('uuid-1'));
    $this->assertSame('node', $export->getDependencyEntityTypeId('uuid-1'));
    $export->removeDependency('uuid-1');
    $this->assertFalse($export->hasDependency('uuid-1'));
  }

  /**
   * Tests setting, reading, and unsetting an option.
   */
  public function testOptionManagement(): void {
    $export = new ExportArray();
    $export->setOption('file_assets', ['should_export' => TRUE]);
    $this->assertTrue($export->hasOption('file_assets'));
    $this->assertSame(['should_export' => TRUE], $export->getOption('file_assets'));
    $export->unsetOption('file_assets');
    $this->assertFalse($export->hasOption('file_assets'));
  }

  /**
   * Tests that cleanArray() omits empty translations and options.
   */
  public function testCleanArrayOmitsEmptyTranslationsAndOptions(): void {
    $export = new ExportArray();
    $export->setUuid('test-uuid')->setEntityTypeId('node')->setBundle('article')->setDefaultLangcode('en');
    $clean = $export->cleanArray();
    $this->assertArrayNotHasKey('translations', $clean);
    $this->assertArrayNotHasKey('options', $clean['_meta']);
  }

  /**
   * Tests that the file asset filename combines the UUID and file name.
   */
  public function testFileAssetFilenameBuildsCorrectly(): void {
    $export = new ExportArray([
      '_meta' => ['entity_type' => 'file', 'uuid' => 'abc-123', 'path' => '/some/path/abc-123.yml'],
      'default' => ['uri' => [['value' => 'public://image.jpg']]],
    ]);
    $this->assertSame('abc-123--image.jpg', $export->fileAssetFilename());
  }

  /**
   * Tests that an empty export has no references.
   */
  public function testEmptyExportHasNoReferences(): void {
    $export = new ExportArray();
    $this->assertSame([], $export->references());
  }

  /**
   * Tests setting references and reading them back.
   */
  public function testSetAndReadReferences(): void {
    $export = new ExportArray();
    $export->setReferences(['uuid-a' => 'node']);

    $this->assertSame(['uuid-a' => 'node'], $export->references());
    $this->assertTrue($export->hasReference('uuid-a'));
    $this->assertFalse($export->hasReference('uuid-b'));
    $this->assertSame('node', $export->getReferenceEntityTypeId('uuid-a'));
    $this->assertNull($export->getReferenceEntityTypeId('uuid-b'));
  }

  /**
   * Tests adding and removing individual references.
   */
  public function testAddAndRemoveReference(): void {
    $export = new ExportArray();
    $this->assertSame($export, $export->addReference('uuid-a', 'node'));
    $export->addReference('uuid-b', 'media');
    $this->assertSame(['uuid-a' => 'node', 'uuid-b' => 'media'], $export->references());

    $export->removeReference('uuid-a');
    $this->assertSame(['uuid-b' => 'media'], $export->references());
  }

  /**
   * Tests that references and dependencies are stored separately.
   */
  public function testReferencesAreSeparateFromDependencies(): void {
    $export = new ExportArray();
    $export->setDependencies(['dep-uuid' => 'paragraph']);
    $export->setReferences(['ref-uuid' => 'node']);

    $this->assertSame(['dep-uuid' => 'paragraph'], $export->dependencies());
    $this->assertSame(['ref-uuid' => 'node'], $export->references());
    $this->assertFalse($export->hasDependency('ref-uuid'));
    $this->assertFalse($export->hasReference('dep-uuid'));
  }

  /**
   * Tests that cleanArray() drops empty references.
   */
  public function testCleanArrayDropsEmptyReferences(): void {
    $export = new ExportArray();
    $this->assertArrayNotHasKey('references', $export->cleanArray()['_meta']);
  }

  /**
   * Tests that cleanArray() keeps populated references.
   */
  public function testCleanArrayKeepsPopulatedReferences(): void {
    $export = new ExportArray();
    $export->addReference('uuid-a', 'node');
    $this->assertSame(['uuid-a' => 'node'], $export->cleanArray()['_meta']['references']);
  }

}
