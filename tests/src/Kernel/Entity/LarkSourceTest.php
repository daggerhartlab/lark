<?php

namespace Drupal\Tests\lark\Kernel\Entity;

use Drupal\KernelTests\KernelTestBase;
use Drupal\lark\Entity\LarkSource;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * @coversDefaultClass \Drupal\lark\Entity\LarkSource
 * @group lark
 */
// Required by Drupal 11.3.0+: kernel tests must isolate to avoid state leaks.
// Will become a hard requirement in Drupal 12.0.0.
#[RunTestsInSeparateProcesses]
class LarkSourceTest extends KernelTestBase {

  /**
   * lark.settings config stores boolean fields as integers; suppress strict schema check.
   */
  protected $strictConfigSchema = FALSE;

  protected static $modules = ['lark', 'user', 'system'];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('lark_source');
    $this->installConfig(['lark']);
  }

  public function testDirectoryProcessedReturnsAbsolutePathForPlainDirectory(): void {
    /** @var \Drupal\lark\Entity\LarkSource $source */
    $source = LarkSource::create([
      'id' => 'test',
      'label' => 'Test',
      'directory' => '/tmp/absolute-path',
    ]);
    // Path starts with separator = already absolute.
    $this->assertStringStartsWith('/', $source->directoryProcessed());
    $this->assertSame('/tmp/absolute-path', $source->directoryProcessed());
  }

  public function testDirectoryProcessedExpandsModuleToken(): void {
    /** @var \Drupal\lark\Entity\LarkSource $source */
    $source = LarkSource::create([
      'id' => 'test_token',
      'label' => 'Token Source',
      'directory' => '[lark]/content',
    ]);
    $processed = $source->directoryProcessed();
    // Should expand [lark] to the real module path.
    $module_path = \Drupal::root() . '/' . \Drupal::service('module_handler')->getModule('lark')->getPath();
    $this->assertStringStartsWith($module_path, $processed, 'Token [lark] must expand to the lark module directory');
    $this->assertStringEndsWith('/content', $processed, 'Path after token must preserve the /content suffix');
    $this->assertStringNotContainsString('[lark]', $processed, 'Token must be fully expanded');
  }

  public function testGetDestinationFilepathBuildsCorrectPath(): void {
    /** @var \Drupal\lark\Entity\LarkSource $source */
    $source = LarkSource::create([
      'id' => 'path_test',
      'label' => 'Path Test',
      'directory' => '/tmp/exports',
    ]);
    $filepath = $source->getDestinationFilepath('node', 'article', 'my-uuid.yml');
    $this->assertSame('/tmp/exports/node/article/my-uuid.yml', $filepath);
  }

}
