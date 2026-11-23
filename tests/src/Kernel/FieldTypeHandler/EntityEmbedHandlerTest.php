<?php

declare(strict_types=1);

namespace Drupal\Tests\lark\Kernel\FieldTypeHandler;

use Drupal\Component\Uuid\Php as PhpUuid;
use Drupal\KernelTests\KernelTestBase;
use Drupal\lark\Service\FieldTypeHandlerManagerInterface;
use Drupal\lark\Service\Utility\EntityUtility;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\lark\Traits\TextFieldsTrait;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * @coversDefaultClass \Drupal\lark\Plugin\Lark\FieldTypeHandler\EntityEmbedHandler
 * @group lark
 */
#[RunTestsInSeparateProcesses]
class EntityEmbedHandlerTest extends KernelTestBase {

  use TextFieldsTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'lark', 'node', 'user', 'system', 'field', 'text', 'filter',
    'entity_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['node', 'filter']);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    $this->createTextFields('node', 'article');
  }

  /**
   * Create a saved article whose field_text_long holds the given HTML.
   */
  protected function createNode(string $html = '', string $title = 'Host'): NodeInterface {
    $node = Node::create([
      'type' => 'article',
      'title' => $title,
      'field_text_long' => ['value' => $html, 'format' => 'plain_text'],
    ]);
    $node->save();
    return $node;
  }

  /**
   * Entity Embed markup as its CKEditor plugin writes it.
   */
  protected function embed(string $entity_type_id, string $uuid): string {
    return '<drupal-entity data-entity-type="' . $entity_type_id . '" data-entity-uuid="' . $uuid . '" data-embed-button="node" data-entity-embed-display="view_mode:node.teaser"></drupal-entity>';
  }

  /**
   * Field type handler manager.
   */
  protected function manager(): FieldTypeHandlerManagerInterface {
    return $this->container->get(FieldTypeHandlerManagerInterface::class);
  }

  /**
   * An embedded node is a dependency.
   */
  public function testEmbeddedNodeBecomesDependency(): void {
    $embedded = $this->createNode('', 'Embedded');
    $host = $this->createNode($this->embed('node', $embedded->uuid()));

    $this->assertSame(
      [$embedded->uuid() => 'node'],
      $this->manager()->getFieldDependencies($host->get('field_text_long'))
    );
  }

  /**
   * The entity type comes from the markup; non-exportable types drop later.
   */
  public function testEntityTypeComesFromAttribute(): void {
    $user = User::create(['name' => 'embedded']);
    $user->save();
    $host = $this->createNode($this->embed('user', $user->uuid()));

    $this->assertSame(
      [$user->uuid() => 'user'],
      $this->manager()->getFieldDependencies($host->get('field_text_long'))
    );

    $dependencies = $this->container->get(EntityUtility::class)->getEntityExportDependencies($host);
    $this->assertArrayNotHasKey($user->uuid(), $dependencies);
  }

  /**
   * Entity types this site doesn't have are ignored and don't break export.
   */
  public function testUnknownEntityTypeIsIgnored(): void {
    $host = $this->createNode($this->embed('not_a_type', (new PhpUuid())->generate()));

    $this->assertSame([], $this->manager()->getFieldDependencies($host->get('field_text_long')));
    $this->assertSame([], $this->container->get(EntityUtility::class)->getEntityExportDependencies($host));
  }

  /**
   * Entity types without UUIDs are ignored and don't break export.
   */
  public function testEntityTypeWithoutUuidIsIgnored(): void {
    $host = $this->createNode($this->embed('entity_test_no_uuid', (new PhpUuid())->generate()));

    $this->assertSame([], $this->manager()->getFieldDependencies($host->get('field_text_long')));
    $this->assertSame([], $this->container->get(EntityUtility::class)->getEntityExportDependencies($host));
  }

  /**
   * Legacy embeds carrying only an entity id are ignored.
   */
  public function testLegacyIdOnlyEmbedIsIgnored(): void {
    $embedded = $this->createNode('', 'Embedded');
    $host = $this->createNode('<drupal-entity data-entity-type="node" data-entity-id="' . $embedded->id() . '"></drupal-entity>');

    $this->assertSame([], $this->manager()->getFieldDependencies($host->get('field_text_long')));
  }

  /**
   * An embedded node is an export dependency of the host.
   */
  public function testEmbeddedNodeBecomesExportDependency(): void {
    $embedded = $this->createNode('', 'Embedded');
    $host = $this->createNode($this->embed('node', $embedded->uuid()));

    $dependencies = $this->container->get(EntityUtility::class)->getEntityExportDependencies($host);
    $this->assertSame('node', $dependencies[$embedded->uuid()] ?? NULL);
  }

}
