<?php

declare(strict_types=1);

namespace Drupal\Tests\lark\Kernel\FieldTypeHandler;

use Drupal\Component\Uuid\Php as PhpUuid;
use Drupal\Core\File\FileSystemInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\lark\Plugin\Lark\FieldTypeHandler\EditorFileHandler;
use Drupal\lark\Service\FieldTypeHandlerManagerInterface;
use Drupal\lark\Service\Utility\EntityUtility;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\lark\Traits\TextFieldsTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests \Drupal\lark\Plugin\Lark\FieldTypeHandler\EditorFileHandler.
 */
#[CoversClass(EditorFileHandler::class)]
#[Group('lark')]
#[RunTestsInSeparateProcesses]
class EditorFileHandlerTest extends KernelTestBase {

  use TextFieldsTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'lark', 'node', 'user', 'system', 'field', 'text', 'filter', 'file',
  ];

  /**
   * File uploaded through the editor.
   */
  protected FileInterface $file;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['node', 'filter']);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    $this->createTextFields('node', 'article');

    $directory = 'public://inline-images';
    $this->container->get(FileSystemInterface::class)->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
    file_put_contents($directory . '/test.png', 'test');
    $this->file = File::create(['uri' => $directory . '/test.png']);
    $this->file->save();
  }

  /**
   * Create a saved article whose field_text_long holds the given HTML.
   */
  protected function createNode(string $html): NodeInterface {
    $node = Node::create([
      'type' => 'article',
      'title' => 'Host',
      'field_text_long' => ['value' => $html, 'format' => 'plain_text'],
    ]);
    $node->save();
    return $node;
  }

  /**
   * Field type handler manager.
   */
  protected function manager(): FieldTypeHandlerManagerInterface {
    return $this->container->get(FieldTypeHandlerManagerInterface::class);
  }

  /**
   * An inline image uploaded through the editor is a dependency.
   */
  public function testInlineImageBecomesDependency(): void {
    $host = $this->createNode('<p><img src="/sites/default/files/inline-images/test.png" data-entity-type="file" data-entity-uuid="' . $this->file->uuid() . '"></p>');

    $this->assertSame(
      [$this->file->uuid() => 'file'],
      $this->manager()->getFieldDependencies($host->get('field_text_long'))
    );
  }

  /**
   * Any element carrying file attributes is a dependency, as core tracks it.
   */
  public function testAnyElementWithFileAttributesBecomesDependency(): void {
    $host = $this->createNode('<a href="/sites/default/files/inline-images/test.png" data-entity-type="file" data-entity-uuid="' . $this->file->uuid() . '">Download</a>');

    $this->assertSame(
      [$this->file->uuid() => 'file'],
      $this->manager()->getFieldDependencies($host->get('field_text_long'))
    );
  }

  /**
   * Attributes written outside the editor, single-quoted or spaced, match.
   */
  public function testUnusuallyQuotedAttributesBecomeDependency(): void {
    $host = $this->createNode("<img src='/x.png' data-entity-type = 'file' data-entity-uuid='" . $this->file->uuid() . "'>");

    $this->assertSame(
      [$this->file->uuid() => 'file'],
      $this->manager()->getFieldDependencies($host->get('field_text_long'))
    );
  }

  /**
   * Elements typed as something other than file are ignored.
   */
  public function testNonFileEntityTypeIsIgnored(): void {
    $host = $this->createNode('<img src="/x.png" data-entity-type="media" data-entity-uuid="' . (new PhpUuid())->generate() . '">');

    $this->assertSame([], $this->manager()->getFieldDependencies($host->get('field_text_long')));
  }

  /**
   * An inline image is an export dependency of the host.
   */
  public function testInlineImageBecomesExportDependency(): void {
    $host = $this->createNode('<img src="/sites/default/files/inline-images/test.png" data-entity-type="file" data-entity-uuid="' . $this->file->uuid() . '">');

    $dependencies = $this->container->get(EntityUtility::class)->getEntityExportDependencies($host);
    $this->assertSame('file', $dependencies[$this->file->uuid()] ?? NULL);
  }

}
