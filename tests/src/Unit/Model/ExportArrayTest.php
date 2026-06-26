<?php

namespace Drupal\Tests\lark\Unit\Model;

use Drupal\lark\Model\ExportArray;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\lark\Model\ExportArray
 * @group lark
 */
class ExportArrayTest extends TestCase {

  public function testEmptyExportFollowsSchema(): void {
    $export = new ExportArray();
    $this->assertTrue($export->isEmpty());
    $this->assertSame('', $export->uuid());
    $this->assertSame('', $export->entityTypeId());
    $this->assertSame([], $export->dependencies());
    $this->assertSame([], $export->translations());
  }

  public function testSettersReturnSelf(): void {
    $export = new ExportArray();
    $result = $export->setUuid('abc-123')
      ->setEntityTypeId('node')
      ->setBundle('article')
      ->setDefaultLangcode('en');
    $this->assertSame($export, $result);
  }

  public function testFieldRoundtrip(): void {
    $export = new ExportArray();
    $export->setFields(['title' => [['value' => 'Hello']]]);
    $this->assertSame([['value' => 'Hello']], $export->getField('title'));
    $export->unsetField('title');
    $this->assertNull($export->getField('title'));
  }

  public function testTranslationFieldRoundtrip(): void {
    $export = new ExportArray();
    $export->setDefaultLangcode('en');
    $export->setFields(['title' => [['value' => 'Bonjour']]], 'fr');
    $this->assertSame([['value' => 'Bonjour']], $export->getField('title', 'fr'));
    $this->assertNull($export->getField('title', 'en'));
  }

  public function testDependencyManagement(): void {
    $export = new ExportArray();
    $export->addDependency('uuid-1', 'node');
    $this->assertTrue($export->hasDependency('uuid-1'));
    $this->assertSame('node', $export->getDependencyEntityTypeId('uuid-1'));
    $export->removeDependency('uuid-1');
    $this->assertFalse($export->hasDependency('uuid-1'));
  }

  public function testOptionManagement(): void {
    $export = new ExportArray();
    $export->setOption('file_assets', ['should_export' => TRUE]);
    $this->assertTrue($export->hasOption('file_assets'));
    $this->assertSame(['should_export' => TRUE], $export->getOption('file_assets'));
    $export->unsetOption('file_assets');
    $this->assertFalse($export->hasOption('file_assets'));
  }

  public function testCleanArrayOmitsEmptyTranslationsAndOptions(): void {
    $export = new ExportArray();
    $export->setUuid('test-uuid')->setEntityTypeId('node')->setBundle('article')->setDefaultLangcode('en');
    $clean = $export->cleanArray();
    $this->assertArrayNotHasKey('translations', $clean);
    $this->assertArrayNotHasKey('options', $clean['_meta']);
  }

  public function testFileAssetFilenameBuildsCorrectly(): void {
    $export = new ExportArray([
      '_meta' => ['entity_type' => 'file', 'uuid' => 'abc-123', 'path' => '/some/path/abc-123.yml'],
      'default' => ['uri' => [['value' => 'public://image.jpg']]],
    ]);
    $this->assertSame('abc-123--image.jpg', $export->fileAssetFilename());
  }

}