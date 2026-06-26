<?php

namespace Drupal\Tests\lark\Kernel\Paragraphs;

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
 * Tests that nested paragraphs (paragraph referencing paragraph) export and
 * import correctly, with correct parent_id at every level.
 *
 * @group lark
 */
#[RunTestsInSeparateProcesses]
class NestedParagraphTest extends KernelTestBase {

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

    // Outer paragraph type.
    ParagraphsType::create(['id' => 'section', 'label' => 'Section'])->save();
    // Inner paragraph type.
    ParagraphsType::create(['id' => 'item', 'label' => 'Item'])->save();

    // Inner paragraph text field.
    FieldStorageConfig::create([
      'field_name' => 'field_item_text',
      'entity_type' => 'paragraph',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_item_text',
      'entity_type' => 'paragraph',
      'bundle' => 'item',
      'label' => 'Item Text',
    ])->save();

    // Outer paragraph references inner paragraph.
    FieldStorageConfig::create([
      'field_name' => 'field_items',
      'entity_type' => 'paragraph',
      'type' => 'entity_reference_revisions',
      'settings' => ['target_type' => 'paragraph'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_items',
      'entity_type' => 'paragraph',
      'bundle' => 'section',
      'label' => 'Items',
    ])->save();

    // Node type with section paragraph.
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_sections',
      'entity_type' => 'node',
      'type' => 'entity_reference_revisions',
      'settings' => ['target_type' => 'paragraph'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_sections',
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'Sections',
    ])->save();

    // Use a real absolute temp directory (not vfs://) so that
    // LarkSource::directoryProcessed() returns a consistent absolute path.
    $this->exportDir = sys_get_temp_dir() . '/lark-nested-exports-' . uniqid();
    LarkSource::create([
      'id' => 'nested_test_source',
      'label' => 'Nested Test Source',
      'directory' => $this->exportDir,
    ])->save();

    \Drupal::configFactory()->getEditable('lark.settings')
      ->set('default_source', 'nested_test_source')
      ->save();
  }

  protected function tearDown(): void {
    // Clean up the real temp directory created during the test.
    if (isset($this->exportDir) && is_dir($this->exportDir)) {
      \Drupal::service('file_system')->deleteRecursive($this->exportDir);
    }
    parent::tearDown();
  }

  public function testNestedParagraphsRoundtrip(): void {
    // Build: node → section paragraph → item paragraph (3 levels deep).
    $item = Paragraph::create(['type' => 'item', 'field_item_text' => 'Item content']);
    $item->save();

    $section = Paragraph::create([
      'type' => 'section',
      'field_items' => [['target_id' => $item->id(), 'target_revision_id' => $item->getRevisionId()]],
    ]);
    $section->save();

    $node = Node::create([
      'type' => 'page',
      'title' => 'Nested Page',
      'field_sections' => [['target_id' => $section->id(), 'target_revision_id' => $section->getRevisionId()]],
    ]);
    $node->save();

    $item_uuid = $item->uuid();
    $section_uuid = $section->uuid();
    $node_uuid = $node->uuid();

    // Export the node (should pull in section and item as dependencies).
    /** @var \Drupal\lark\Service\ExporterInterface $exporter */
    $exporter = $this->container->get(ExporterInterface::class);
    $exporter->exportEntity('nested_test_source', 'node', (int) $node->id(), FALSE);

    // All three YAML files must exist.
    $this->assertFileExists($this->exportDir . '/node/page/' . $node_uuid . '.yml');
    $this->assertFileExists($this->exportDir . '/paragraph/section/' . $section_uuid . '.yml');
    $this->assertFileExists($this->exportDir . '/paragraph/item/' . $item_uuid . '.yml');

    // Delete all.
    $node->delete();
    $section->delete();
    $item->delete();

    // Re-import.
    /** @var \Drupal\lark\Service\ImporterInterface $importer */
    $importer = $this->container->get(ImporterInterface::class);
    $importer->importSource('nested_test_source', FALSE);

    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['uuid' => $node_uuid]);
    $sections = \Drupal::entityTypeManager()->getStorage('paragraph')->loadByProperties(['uuid' => $section_uuid]);
    $items = \Drupal::entityTypeManager()->getStorage('paragraph')->loadByProperties(['uuid' => $item_uuid]);

    $this->assertNotEmpty($nodes, 'Node must be re-created');
    $this->assertNotEmpty($sections, 'Section paragraph must be re-created');
    $this->assertNotEmpty($items, 'Item paragraph must be re-created');

    $reimported_node = reset($nodes);
    $reimported_section = reset($sections);
    $reimported_item = reset($items);

    // Section's parent_id must point to the destination node.
    $this->assertSame(
      (string) $reimported_node->id(),
      $reimported_section->get('parent_id')->value,
      'Section paragraph parent_id must be corrected to destination node ID'
    );

    // Item's parent_id must point to the destination section paragraph.
    $this->assertSame(
      (string) $reimported_section->id(),
      $reimported_item->get('parent_id')->value,
      'Item paragraph parent_id must be corrected to destination section ID'
    );

    // Item content must be intact.
    $this->assertSame('Item content', $reimported_item->get('field_item_text')->value);
  }

}
