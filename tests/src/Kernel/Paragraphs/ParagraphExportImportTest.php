<?php

namespace Drupal\Tests\lark\Kernel\Paragraphs;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\lark\Entity\LarkSource;
use Drupal\lark\Service\ExporterInterface;
use Drupal\lark\Service\ImporterInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests export/import of nodes containing paragraphs.
 *
 * Explicitly covers the parent_id self-correction behavior: paragraphs are
 * exported with the source environment's parent_id, and entity_reference_revisions
 * corrects it when the host entity is saved during import.
 *
 * @group lark
 */
#[RunTestsInSeparateProcesses]
class ParagraphExportImportTest extends KernelTestBase {

  /**
   * Disable strict config schema to avoid lark.settings integer/string mismatch.
   */
  protected $strictConfigSchema = FALSE;

  protected static $modules = [
    'lark', 'node', 'user', 'system', 'field', 'text', 'filter',
    'paragraphs', 'entity_reference_revisions', 'file',
  ];

  protected string $exportDir;

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('lark_source');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('file');
    $this->installConfig(['node', 'filter', 'lark']);
    $this->installSchema('node', ['node_access']);

    // Create user 1 (admin) so the importer's AdminAccountSwitcher can switch
    // to an administrative account during entity owner resolution.
    $admin = User::create([
      'uid' => 1,
      'name' => 'admin',
      'status' => 1,
    ]);
    $admin->save();

    // Create a paragraph type.
    ParagraphsType::create(['id' => 'text_block', 'label' => 'Text Block'])->save();

    // Create a text field on the paragraph type.
    FieldStorageConfig::create([
      'field_name' => 'field_text',
      'entity_type' => 'paragraph',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_text',
      'entity_type' => 'paragraph',
      'bundle' => 'text_block',
      'label' => 'Text',
    ])->save();

    // Create article node type with a paragraph field.
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_paragraphs',
      'entity_type' => 'node',
      'type' => 'entity_reference_revisions',
      'settings' => ['target_type' => 'paragraph'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_paragraphs',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Paragraphs',
    ])->save();

    // Use a real absolute temp directory (not vfs://) so that
    // LarkSource::directoryProcessed() returns a consistent absolute path.
    $this->exportDir = sys_get_temp_dir() . '/lark-paragraph-exports-' . uniqid();
    LarkSource::create([
      'id' => 'paragraph_test_source',
      'label' => 'Paragraph Test Source',
      'directory' => $this->exportDir,
    ])->save();

    \Drupal::configFactory()->getEditable('lark.settings')
      ->set('default_source', 'paragraph_test_source')
      ->save();
  }

  protected function tearDown(): void {
    // Clean up the real temp directory created during the test.
    if (isset($this->exportDir) && is_dir($this->exportDir)) {
      \Drupal::service('file_system')->deleteRecursive($this->exportDir);
    }
    parent::tearDown();
  }

  public function testNodeWithParagraphExportsAndImportsCleanly(): void {
    $paragraph = Paragraph::create([
      'type' => 'text_block',
      'field_text' => 'Hello from paragraph',
    ]);
    $paragraph->save();
    $paragraph_uuid = $paragraph->uuid();

    $node = Node::create([
      'type' => 'article',
      'title' => 'Node With Paragraph',
      'field_paragraphs' => [['target_id' => $paragraph->id(), 'target_revision_id' => $paragraph->getRevisionId()]],
    ]);
    $node->save();
    $node_uuid = $node->uuid();

    /** @var \Drupal\lark\Service\ExporterInterface $exporter */
    $exporter = $this->container->get(ExporterInterface::class);
    $exporter->exportEntity('paragraph_test_source', 'node', (int) $node->id(), FALSE);

    // Confirm both node and paragraph YAMLs exist.
    $this->assertFileExists($this->exportDir . '/node/article/' . $node_uuid . '.yml');
    $this->assertFileExists($this->exportDir . '/paragraph/text_block/' . $paragraph_uuid . '.yml');

    // Delete both.
    $node->delete();
    $paragraph->delete();

    // Re-import via source (imports in dependency order: paragraph first, node second).
    /** @var \Drupal\lark\Service\ImporterInterface $importer */
    $importer = $this->container->get(ImporterInterface::class);
    $importer->importSource('paragraph_test_source', FALSE);

    // The node must exist again.
    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['uuid' => $node_uuid]);
    $this->assertNotEmpty($nodes, 'Node must be re-created by import');
    $reimported_node = reset($nodes);
    $this->assertSame('Node With Paragraph', $reimported_node->label());

    // The paragraph must exist with content intact.
    $paragraphs = \Drupal::entityTypeManager()->getStorage('paragraph')->loadByProperties(['uuid' => $paragraph_uuid]);
    $this->assertNotEmpty($paragraphs, 'Paragraph must be re-created by import');
    $reimported_paragraph = reset($paragraphs);
    $this->assertSame('Hello from paragraph', $reimported_paragraph->get('field_text')->value);

    // Critical: the paragraph's parent_id must point to the re-imported node,
    // not the source environment's old numeric ID.
    $this->assertSame(
      (string) $reimported_node->id(),
      $reimported_paragraph->get('parent_id')->value,
      'Paragraph parent_id must be corrected to the destination node ID by entity_reference_revisions postSave'
    );
  }

  public function testParagraphParentIdIsCorrectAfterReimport(): void {
    // This test explicitly documents the implicit dependency on
    // entity_reference_revisions::postSave() for parent_id correction.
    $paragraph = Paragraph::create(['type' => 'text_block', 'field_text' => 'Content']);
    $paragraph->save();

    $node = Node::create([
      'type' => 'article',
      'title' => 'Parent Node',
      'field_paragraphs' => [['target_id' => $paragraph->id(), 'target_revision_id' => $paragraph->getRevisionId()]],
    ]);
    $node->save();

    /** @var \Drupal\lark\Service\ExporterInterface $exporter */
    $exporter = $this->container->get(ExporterInterface::class);
    $exporter->exportEntity('paragraph_test_source', 'node', (int) $node->id(), FALSE);

    // Verify the exported paragraph YAML has the source env's node ID.
    $yaml_path = $this->exportDir . '/paragraph/text_block/' . $paragraph->uuid() . '.yml';
    $content = Yaml::decode(file_get_contents($yaml_path));
    $exported_parent_id = $content['default']['parent_id'][0]['value'];
    $this->assertSame((string) $node->id(), $exported_parent_id, 'Exported paragraph must contain source node\'s numeric ID');

    // Delete and re-import.
    $original_node_id = $node->id();
    $node->delete();
    $paragraph->delete();

    /** @var \Drupal\lark\Service\ImporterInterface $importer */
    $importer = $this->container->get(ImporterInterface::class);
    $importer->importSource('paragraph_test_source', FALSE);

    $paragraphs = \Drupal::entityTypeManager()->getStorage('paragraph')->loadByProperties(['uuid' => $paragraph->uuid()]);
    $reimported_paragraph = reset($paragraphs);

    // The destination node might have a different ID, but parent_id should match it.
    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['uuid' => $node->uuid()]);
    $reimported_node = reset($nodes);

    $this->assertSame(
      (string) $reimported_node->id(),
      $reimported_paragraph->get('parent_id')->value,
      'parent_id must be corrected to the destination node ID even if the node got a different numeric ID'
    );
  }

}
