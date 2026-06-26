<?php

namespace Drupal\Tests\lark\Unit\Model;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\lark\Model\LarkSettings;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\lark\Model\LarkSettings
 * @group lark
 */
class LarkSettingsTest extends TestCase {

  private function makeSettings(array $values): LarkSettings {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(fn($key) => $values[$key] ?? NULL);
    return new LarkSettings($config);
  }

  public function testIgnoredComparisonKeysTrimsWhitespace(): void {
    $settings = $this->makeSettings([
      'ignored_comparison_keys' => " changed \r\n revision_timestamp \r\n content_translation_changed ",
    ]);
    $keys = $settings->ignoredComparisonKeysArray();
    $this->assertContains('changed', $keys);
    $this->assertContains('revision_timestamp', $keys);
    $this->assertContains('content_translation_changed', $keys);
    // Verify no whitespace-padded versions sneak in.
    $this->assertNotContains(' changed ', $keys);
  }

  public function testIgnoredComparisonKeysAlwaysIncludesOriginalValues(): void {
    $settings = $this->makeSettings(['ignored_comparison_keys' => '']);
    $keys = $settings->ignoredComparisonKeysArray();
    $this->assertContains('original_values', $keys);
  }

  public function testShouldExportAssetsDefaultsFalse(): void {
    $settings = $this->makeSettings([]);
    $this->assertFalse($settings->shouldExportAssets());
  }

  public function testShouldExportAssetsReturnsConfigValue(): void {
    $settings = $this->makeSettings(['should_export_assets' => 1]);
    $this->assertTrue($settings->shouldExportAssets());
  }

  public function testDefaultSourceReturnsEmptyStringWhenNotSet(): void {
    $settings = $this->makeSettings([]);
    $this->assertSame('', $settings->defaultSource());
  }

}