<?php

namespace Drupal\Tests\lark\Unit\Model;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\lark\Model\LarkSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests \Drupal\lark\Model\LarkSettings.
 */
#[CoversClass(LarkSettings::class)]
#[Group('lark')]
class LarkSettingsTest extends TestCase {

  /**
   * Creates settings backed by a mocked config object.
   *
   * @param array $values
   *   Config values, keyed by config key.
   *
   * @return \Drupal\lark\Model\LarkSettings
   *   The settings object.
   */
  private function makeSettings(array $values): LarkSettings {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(fn($key) => $values[$key] ?? NULL);
    return new LarkSettings($config);
  }

  /**
   * Tests that ignored comparison keys are trimmed of whitespace.
   */
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

  /**
   * Tests that ignored comparison keys always include 'original_values'.
   */
  public function testIgnoredComparisonKeysAlwaysIncludesOriginalValues(): void {
    $settings = $this->makeSettings(['ignored_comparison_keys' => '']);
    $keys = $settings->ignoredComparisonKeysArray();
    $this->assertContains('original_values', $keys);
  }

  /**
   * Tests that exporting assets defaults to FALSE.
   */
  public function testShouldExportAssetsDefaultsFalse(): void {
    $settings = $this->makeSettings([]);
    $this->assertFalse($settings->shouldExportAssets());
  }

  /**
   * Tests that shouldExportAssets() reflects the config value.
   */
  public function testShouldExportAssetsReturnsConfigValue(): void {
    $settings = $this->makeSettings(['should_export_assets' => 1]);
    $this->assertTrue($settings->shouldExportAssets());
  }

  /**
   * Tests that the default source is an empty string when not set.
   */
  public function testDefaultSourceReturnsEmptyStringWhenNotSet(): void {
    $settings = $this->makeSettings([]);
    $this->assertSame('', $settings->defaultSource());
  }

}
