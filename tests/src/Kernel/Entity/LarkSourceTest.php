<?php

namespace Drupal\Tests\lark\Kernel\Entity;

use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\KernelTests\KernelTestBase;
use Drupal\lark\Entity\LarkSource;
use Drupal\lark\Entity\LarkSourceListBuilder;
use Drupal\lark\Form\LarkSourceForm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests \Drupal\lark\Entity\LarkSource.
 *
 * Process isolation is required by Drupal 11.3.0+ to avoid state leaks, and
 * will become a hard requirement in Drupal 12.0.0.
 */
#[CoversClass(LarkSource::class)]
#[Group('lark')]
#[RunTestsInSeparateProcesses]
class LarkSourceTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['lark', 'user', 'system'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('lark_source');
    $this->installConfig(['lark']);
  }

  /**
   * Tests that a plain absolute directory is returned unchanged.
   */
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

  /**
   * Tests that a [module] token expands to the module's absolute path.
   */
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

  /**
   * Tests that the destination path joins entity type, bundle, and filename.
   */
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

  /**
   * Tests that the entity type is declared by attribute with its definition.
   */
  public function testEntityTypeDefinition(): void {
    $this->assertNotEmpty((new \ReflectionClass(LarkSource::class))->getAttributes(ConfigEntityType::class));

    $definition = $this->container->get('entity_type.manager')->getDefinition('lark_source');
    $this->assertSame(LarkSource::class, $definition->getClass());
    $this->assertSame('Source', (string) $definition->getLabel());
    $this->assertSame('Sources', (string) $definition->getCollectionLabel());
    $this->assertSame('source', (string) $definition->getSingularLabel());
    $this->assertSame('sources', (string) $definition->getPluralLabel());
    $this->assertSame('2 sources', (string) $definition->getCountLabel(2));
    $this->assertSame(LarkSourceListBuilder::class, $definition->getListBuilderClass());
    $this->assertSame(LarkSourceForm::class, $definition->getFormClass('add'));
    $this->assertSame(LarkSourceForm::class, $definition->getFormClass('edit'));
    $this->assertSame(EntityDeleteForm::class, $definition->getFormClass('delete'));
    $this->assertSame('lark.lark_source', $definition->getConfigPrefix());
    $this->assertSame('lark administer configuration', $definition->getAdminPermission());
    $this->assertSame([
      'canonical' => '/admin/lark/source/{lark_source}',
      'collection' => '/admin/lark/source',
      'add-form' => '/admin/lark/source/add',
      'edit-form' => '/admin/lark/source/{lark_source}/edit',
      'delete-form' => '/admin/lark/source/{lark_source}/delete',
    ], array_intersect_key($definition->getLinkTemplates(), array_flip([
      'canonical',
      'collection',
      'add-form',
      'edit-form',
      'delete-form',
    ])));
    $this->assertSame('id', $definition->getKey('id'));
    $this->assertSame('label', $definition->getKey('label'));
    $this->assertSame('uuid', $definition->getKey('uuid'));
    $this->assertSame(['id', 'label', 'description', 'directory'], $definition->get('config_export'));
  }

}
