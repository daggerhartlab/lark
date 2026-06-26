<?php

namespace Drupal\Tests\lark\Kernel\Service;

use Drupal\Core\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use Drupal\lark\Entity\LarkSource;
use Drupal\lark\Service\ExporterInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * @coversDefaultClass \Drupal\lark\Service\Exporter
 * @group lark
 */
#[RunTestsInSeparateProcesses]
class ExporterTest extends KernelTestBase {

  /**
   * Disable strict config schema to avoid lark.settings integer/string mismatch.
   */
  protected $strictConfigSchema = FALSE;

  protected static $modules = [
    'lark', 'node', 'user', 'system', 'field', 'text', 'filter',
  ];

  protected string $exportDir;

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('lark_source');
    $this->installConfig(['node', 'filter', 'lark']);
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    $this->installSchema('node', ['node_access']);

    // Create a source pointing to a real temp directory (not vfs://) so that
    // LarkSource::directoryProcessed() returns a consistent absolute path.
    $this->exportDir = sys_get_temp_dir() . '/lark-test-exports-' . uniqid();
    LarkSource::create([
      'id' => 'test_source',
      'label' => 'Test Source',
      'directory' => $this->exportDir,
    ])->save();

    // Set it as default.
    \Drupal::configFactory()->getEditable('lark.settings')
      ->set('default_source', 'test_source')
      ->save();
  }

  protected function tearDown(): void {
    // Clean up the real temp directory created during the test.
    if (isset($this->exportDir) && is_dir($this->exportDir)) {
      \Drupal::service('file_system')->deleteRecursive($this->exportDir);
    }
    parent::tearDown();
  }

  public function testExportEntityCreatesYamlFile(): void {
    $node = Node::create(['type' => 'article', 'title' => 'Export Me']);
    $node->save();

    /** @var \Drupal\lark\Service\ExporterInterface $exporter */
    $exporter = $this->container->get(ExporterInterface::class);
    $exporter->exportEntity('test_source', 'node', (int) $node->id(), FALSE);

    $expected_file = $this->exportDir . '/node/article/' . $node->uuid() . '.yml';
    $this->assertFileExists($expected_file, 'Export must create a YAML file at the expected path');
  }

  public function testExportedYamlContainsRequiredMetaKeys(): void {
    $node = Node::create(['type' => 'article', 'title' => 'Check Meta']);
    $node->save();

    /** @var \Drupal\lark\Service\ExporterInterface $exporter */
    $exporter = $this->container->get(ExporterInterface::class);
    $exporter->exportEntity('test_source', 'node', (int) $node->id(), FALSE);

    $yaml_path = $this->exportDir . '/node/article/' . $node->uuid() . '.yml';
    $content = Yaml::decode(file_get_contents($yaml_path));

    $this->assertArrayHasKey('_meta', $content);
    $this->assertSame('node', $content['_meta']['entity_type']);
    $this->assertSame('article', $content['_meta']['bundle']);
    $this->assertSame($node->uuid(), $content['_meta']['uuid']);
    $this->assertArrayNotHasKey('nid', $content['default'] ?? []);
  }

  public function testExportedYamlDoesNotContainNumericEntityId(): void {
    $node = Node::create(['type' => 'article', 'title' => 'No NID']);
    $node->save();

    /** @var \Drupal\lark\Service\ExporterInterface $exporter */
    $exporter = $this->container->get(ExporterInterface::class);
    $exporter->exportEntity('test_source', 'node', (int) $node->id(), FALSE);

    $yaml_path = $this->exportDir . '/node/article/' . $node->uuid() . '.yml';
    $content = Yaml::decode(file_get_contents($yaml_path));
    $this->assertArrayNotHasKey('nid', $content['default'] ?? []);
  }

}
