<?php

declare(strict_types=1);

namespace Drupal\Tests\lark\Kernel\FieldTypeHandler;

use Drupal\Component\Uuid\Php as PhpUuid;
use Drupal\KernelTests\KernelTestBase;
use Drupal\file\Entity\File;
use Drupal\lark\Service\FieldTypeHandlerManagerInterface;
use Drupal\lark\Service\Utility\EntityUtility;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Drupal\media\MediaTypeInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\lark\Traits\TextFieldsTrait;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * @coversDefaultClass \Drupal\lark\Plugin\Lark\FieldTypeHandler\MediaEmbedHandler
 * @group lark
 */
#[RunTestsInSeparateProcesses]
class MediaEmbedHandlerTest extends KernelTestBase {

  use MediaTypeCreationTrait;
  use TextFieldsTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'lark', 'node', 'user', 'system', 'field', 'text', 'filter',
    'file', 'image', 'media',
  ];

  /**
   * Media type used to create media.
   */
  protected MediaTypeInterface $mediaType;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['node', 'filter', 'media']);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    $this->createTextFields('node', 'article');
    $this->mediaType = $this->createMediaType('file');
  }

  /**
   * Create a saved media item backed by a real file.
   */
  protected function createMedia(string $name = 'test'): MediaInterface {
    $uri = 'public://' . $name . '-' . $this->randomMachineName() . '.txt';
    file_put_contents($uri, 'test');
    $file = File::create(['uri' => $uri]);
    $file->save();

    $source_field = $this->mediaType->getSource()->getSourceFieldDefinition($this->mediaType)->getName();
    $media = Media::create([
      'bundle' => $this->mediaType->id(),
      'name' => $name,
      $source_field => ['target_id' => $file->id()],
    ]);
    $media->save();
    return $media;
  }

  /**
   * Create a saved article with the given field values.
   */
  protected function createNode(array $values = []): NodeInterface {
    $node = Node::create($values + ['type' => 'article', 'title' => 'Host']);
    $node->save();
    return $node;
  }

  /**
   * Media embed markup as CKEditor 5 writes it.
   */
  protected function embed(string $uuid): string {
    return '<drupal-media data-entity-type="media" data-entity-uuid="' . $uuid . '" data-view-mode="default">&nbsp;</drupal-media>';
  }

  /**
   * Field type handler manager.
   */
  protected function manager(): FieldTypeHandlerManagerInterface {
    return $this->container->get(FieldTypeHandlerManagerInterface::class);
  }

  /**
   * An embed in any formatted text field type is a dependency.
   */
  public function testEmbedInEachTextFieldTypeBecomesDependency(): void {
    $media = $this->createMedia();
    $html = '<p>Intro</p>' . $this->embed($media->uuid());

    foreach (['field_text', 'field_text_long', 'field_text_summary'] as $field_name) {
      $node = $this->createNode([$field_name => ['value' => $html, 'format' => 'plain_text']]);
      $this->assertSame(
        [$media->uuid() => 'media'],
        $this->manager()->getFieldDependencies($node->get($field_name)),
        $field_name
      );
    }
  }

  /**
   * An embed in a summary is a dependency.
   */
  public function testEmbedInSummaryBecomesDependency(): void {
    $media = $this->createMedia();
    $node = $this->createNode([
      'field_text_summary' => [
        'value' => '<p>Body</p>',
        'summary' => $this->embed($media->uuid()),
        'format' => 'plain_text',
      ],
    ]);

    $this->assertSame(
      [$media->uuid() => 'media'],
      $this->manager()->getFieldDependencies($node->get('field_text_summary'))
    );
  }

  /**
   * Every embed across values and deltas is a dependency.
   */
  public function testMultipleEmbedsAreAllDependencies(): void {
    $first = $this->createMedia('first');
    $second = $this->createMedia('second');
    $third = $this->createMedia('third');
    $node = $this->createNode([
      'field_text_long' => [
        [
          'value' => $this->embed($first->uuid()) . '<p>Between</p>' . $this->embed($second->uuid()),
          'format' => 'plain_text',
        ],
        ['value' => $this->embed($third->uuid()), 'format' => 'plain_text'],
      ],
    ]);

    $dependencies = $this->manager()->getFieldDependencies($node->get('field_text_long'));
    ksort($dependencies);
    $expected = [
      $first->uuid() => 'media',
      $second->uuid() => 'media',
      $third->uuid() => 'media',
    ];
    ksort($expected);
    $this->assertSame($expected, $dependencies);
  }

  /**
   * Repeated embeds of one media item yield one dependency.
   */
  public function testDuplicateEmbedsCollapse(): void {
    $media = $this->createMedia();
    $html = $this->embed($media->uuid()) . $this->embed($media->uuid());
    $node = $this->createNode([
      'field_text_summary' => ['value' => $html, 'summary' => $html, 'format' => 'plain_text'],
    ]);

    $this->assertSame(
      [$media->uuid() => 'media'],
      $this->manager()->getFieldDependencies($node->get('field_text_summary'))
    );
  }

  /**
   * Fields without embeds have no dependencies.
   */
  public function testFieldsWithoutEmbedsHaveNoDependencies(): void {
    $empty = $this->createNode();
    $this->assertSame([], $this->manager()->getFieldDependencies($empty->get('field_text_long')));

    $plain = $this->createNode(['field_text_long' => ['value' => '<p>Plain</p>', 'format' => 'plain_text']]);
    $this->assertSame([], $this->manager()->getFieldDependencies($plain->get('field_text_long')));
    $this->assertSame([], $this->manager()->getFieldDependencies($plain->get('title')));
  }

  /**
   * Escaped embed markup shown as text is not an embed.
   */
  public function testEscapedMarkupIsIgnored(): void {
    $media = $this->createMedia();
    $html = '<p>&lt;drupal-media data-entity-type="media" data-entity-uuid="' . $media->uuid() . '"&gt;&lt;/drupal-media&gt;</p>';
    $node = $this->createNode(['field_text_long' => ['value' => $html, 'format' => 'plain_text']]);

    $this->assertSame([], $this->manager()->getFieldDependencies($node->get('field_text_long')));
  }

  /**
   * Embeds without a UUID or not typed as media are ignored.
   */
  public function testEmptyOrMismatchedAttributesAreIgnored(): void {
    $media = $this->createMedia();
    $html = '<drupal-media data-entity-type="media" data-entity-uuid="">&nbsp;</drupal-media>'
      . '<drupal-media data-entity-type="node" data-entity-uuid="' . $media->uuid() . '">&nbsp;</drupal-media>'
      . '<drupal-media data-entity-uuid="' . $media->uuid() . '">&nbsp;</drupal-media>';
    $node = $this->createNode(['field_text_long' => ['value' => $html, 'format' => 'plain_text']]);

    $this->assertSame([], $this->manager()->getFieldDependencies($node->get('field_text_long')));
  }

  /**
   * Embedded media and its file are export dependencies of the host.
   */
  public function testEmbeddedMediaBecomesExportDependency(): void {
    $media = $this->createMedia();
    $file = File::load($media->getSource()->getSourceFieldValue($media));
    $node = $this->createNode(['field_text_long' => ['value' => $this->embed($media->uuid()), 'format' => 'plain_text']]);

    $dependencies = $this->container->get(EntityUtility::class)->getEntityExportDependencies($node);

    $this->assertSame('media', $dependencies[$media->uuid()] ?? NULL);
    $this->assertSame('file', $dependencies[$file->uuid()] ?? NULL);
  }

  /**
   * Embeds of media that does not exist do not break the export.
   */
  public function testMissingMediaIsSkippedByEntityUtility(): void {
    $missing_uuid = (new PhpUuid())->generate();
    $node = $this->createNode(['field_text_long' => ['value' => $this->embed($missing_uuid), 'format' => 'plain_text']]);

    $this->assertSame([$missing_uuid => 'media'], $this->manager()->getFieldDependencies($node->get('field_text_long')));

    $dependencies = $this->container->get(EntityUtility::class)->getEntityExportDependencies($node);
    $this->assertArrayNotHasKey($missing_uuid, $dependencies);
  }

}
