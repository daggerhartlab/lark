<?php

namespace Drupal\Tests\lark\Kernel\FieldTypeHandler;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\lark\Plugin\Lark\FieldTypeHandler\EntityReferenceUuidHandler;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * @coversDefaultClass \Drupal\lark\Plugin\Lark\FieldTypeHandler\EntityReferenceUuidHandler
 * @group lark
 */
#[RunTestsInSeparateProcesses]
class EntityReferenceUuidHandlerTest extends KernelTestBase {

  protected static $modules = [
    'lark', 'node', 'user', 'system', 'field', 'text', 'filter',
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig(['node', 'filter']);
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    $this->installSchema('node', ['node_access']);

    // Create a node-to-node entity reference field so we have an exportable
    // reference (users are excluded from export by EntityTypeInfo).
    FieldStorageConfig::create([
      'field_name' => 'field_related',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'node'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_related',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Related',
    ])->save();
  }

  public function testAlterExportValueReplacesTargetIdWithUuid(): void {
    $referenced = Node::create(['type' => 'article', 'title' => 'Referenced']);
    $referenced->save();

    $parent = Node::create([
      'type' => 'article',
      'title' => 'Parent',
      'field_related' => [['target_id' => $referenced->id()]],
    ]);
    $parent->save();

    /** @var \Drupal\lark\Service\FieldTypeHandlerManagerInterface $manager */
    $manager = $this->container->get(\Drupal\lark\Service\FieldTypeHandlerManagerInterface::class);
    /** @var \Drupal\lark\Plugin\Lark\FieldTypeHandler\EntityReferenceUuidHandler $handler */
    $handler = $manager->getInstances()['entity_reference_uuid_handler'];
    $this->assertInstanceOf(EntityReferenceUuidHandler::class, $handler);

    $field = $parent->get('field_related');
    // Build the initial values array as the exporter would (target_id keyed).
    $values = [['target_id' => (string) $referenced->id()]];

    $result = $handler->alterExportValue($values, $parent, $field);

    // The handler must replace target_id with target_uuid.
    $this->assertArrayHasKey('target_uuid', $result[0], 'alterExportValue must produce a target_uuid key');
    $this->assertSame($referenced->uuid(), $result[0]['target_uuid'], 'target_uuid must match the referenced entity UUID');
    $this->assertArrayNotHasKey('target_id', $result[0], 'target_id must be removed from export values');
  }

  public function testAlterImportValueResolvesUuidToTargetId(): void {
    $referenced = Node::create(['type' => 'article', 'title' => 'Referenced']);
    $referenced->save();

    /** @var \Drupal\lark\Service\FieldTypeHandlerManagerInterface $manager */
    $manager = $this->container->get(\Drupal\lark\Service\FieldTypeHandlerManagerInterface::class);
    $handler = $manager->getInstances()['entity_reference_uuid_handler'];

    // Build the import value structure that alterExportValue produces.
    $import_values = [[
      'target_uuid' => $referenced->uuid(),
      'target_entity_type' => 'node',
      'target_bundle' => 'article',
      'original_values' => [],
    ]];

    // Use the real entity reference field (not uid, which points to users).
    $parent = Node::create(['type' => 'article', 'title' => 'Parent']);
    $field = $parent->get('field_related');

    $result = $handler->alterImportValue($import_values, $field);

    $this->assertIsArray($result);
    $this->assertArrayHasKey('target_id', $result[0], 'alterImportValue must resolve UUID to target_id');
    $this->assertSame((string) $referenced->id(), (string) $result[0]['target_id'], 'target_id must equal the referenced entity numeric ID');
  }

}
