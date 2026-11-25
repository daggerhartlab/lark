<?php

declare(strict_types=1);

namespace Drupal\Tests\lark\Kernel\Config;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the types of the lark.settings asset options.
 */
#[Group('lark')]
#[RunTestsInSeparateProcesses]
class LarkSettingsConfigTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['lark', 'user', 'system'];

  /**
   * The asset options are installed as booleans.
   */
  public function testInstalledAssetOptionsAreBooleans(): void {
    $this->installConfig(['lark']);
    $config = $this->config('lark.settings');

    $this->assertTrue($config->get('should_export_assets'));
    $this->assertTrue($config->get('should_import_assets'));
  }

  /**
   * Saving the asset options stores booleans.
   */
  public function testSavedAssetOptionsAreBooleans(): void {
    $this->installConfig(['lark']);
    $this->config('lark.settings')
      ->set('should_export_assets', '0')
      ->set('should_import_assets', '1')
      ->save();
    $config = $this->config('lark.settings');

    $this->assertFalse($config->get('should_export_assets'));
    $this->assertTrue($config->get('should_import_assets'));
  }

  /**
   * The post update converts existing asset options to booleans.
   */
  public function testPostUpdateConvertsAssetOptions(): void {
    $this->installConfig(['lark']);
    // Store values the way earlier versions did, bypassing schema casting.
    $this->container->get('config.storage')->write('lark.settings', [
      'should_export_assets' => '0',
      'should_import_assets' => 1,
    ] + $this->config('lark.settings')->getRawData());
    $this->container->get('config.factory')->reset('lark.settings');

    $this->container->get('module_handler')->loadInclude('lark', 'php', 'lark.post_update');
    lark_post_update_settings_asset_booleans();

    $config = $this->config('lark.settings');
    $this->assertFalse($config->get('should_export_assets'));
    $this->assertTrue($config->get('should_import_assets'));
  }

}
