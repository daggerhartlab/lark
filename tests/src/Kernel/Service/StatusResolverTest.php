<?php

namespace Drupal\Tests\lark\Kernel\Service;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\lark\Entity\LarkSource;
use Drupal\lark\Model\ExportableStatus;
use Drupal\lark\Service\ExportableFactory;
use Drupal\lark\Service\ExportableFactoryInterface;
use Drupal\lark\Service\ExporterInterface;
use Drupal\lark\Service\ImporterInterface;
use Drupal\lark\Service\LarkSourceManager;
use Drupal\lark\Service\MetaOptionManager;
use Drupal\lark\Service\Utility\EntityUtility;
use Drupal\lark\Service\Utility\StatusResolver;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * @coversDefaultClass \Drupal\lark\Service\Utility\StatusResolver
 * @group lark
 */
#[RunTestsInSeparateProcesses]
class StatusResolverTest extends KernelTestBase {

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

    // Create user 1 so AdminAccountSwitcher doesn't fail.
    User::create(['uid' => 1, 'name' => 'admin', 'status' => 1])->save();

    // Use an absolute path so LarkSource::directoryProcessed() doesn't prepend DRUPAL_ROOT.
    $this->exportDir = sys_get_temp_dir() . '/lark-status-test-' . uniqid();
    LarkSource::create([
      'id' => 'test_source',
      'label' => 'Test Source',
      'directory' => $this->exportDir,
    ])->save();

    \Drupal::configFactory()->getEditable('lark.settings')
      ->set('default_source', 'test_source')
      ->save();
  }

  protected function tearDown(): void {
    if (isset($this->exportDir) && is_dir($this->exportDir)) {
      \Drupal::service('file_system')->deleteRecursive($this->exportDir);
    }
    parent::tearDown();
  }

  public function testStatusIsNotExportedWhenNoYamlExists(): void {
    $node = Node::create(['type' => 'article', 'title' => 'Not Exported']);
    $node->save();

    // The container-cached factory is safe here because no export has been
    // performed yet; its internal exportableCache is empty.
    /** @var \Drupal\lark\Service\ExportableFactoryInterface $factory */
    $factory = $this->container->get(ExportableFactoryInterface::class);
    $exportable = $factory->createFromEntity($node);

    $this->assertSame(ExportableStatus::NotExported, $exportable->getStatus());
  }

  public function testStatusIsInSyncAfterExport(): void {
    $node = Node::create(['type' => 'article', 'title' => 'In Sync']);
    $node->save();

    /** @var \Drupal\lark\Service\ExporterInterface $exporter */
    $exporter = $this->container->get(ExporterInterface::class);
    $exporter->exportEntity('test_source', 'node', (int) $node->id(), FALSE);

    // Use a fresh factory (no request-level cache) so the status is re-computed
    // after the YAML file was written.
    $factory = $this->freshExportableFactory();
    // Reload the node from DB to ensure field values match what was exported.
    $node = \Drupal::entityTypeManager()->getStorage('node')->loadUnchanged($node->id());

    $exportable = $factory->createFromEntity($node);
    $this->assertSame(ExportableStatus::InSync, $exportable->getStatus());
  }

  public function testStatusIsOutOfSyncWhenEntityChangedAfterExport(): void {
    $node = Node::create(['type' => 'article', 'title' => 'Will Change']);
    $node->save();

    /** @var \Drupal\lark\Service\ExporterInterface $exporter */
    $exporter = $this->container->get(ExporterInterface::class);
    $exporter->exportEntity('test_source', 'node', (int) $node->id(), FALSE);

    $node->set('title', 'Changed After Export');
    $node->save();

    // Reload from DB so field value types match those in the exported YAML,
    // ensuring the only diff is the changed title.
    $node = \Drupal::entityTypeManager()->getStorage('node')->loadUnchanged($node->id());

    // A fresh factory instance (no request-level cache).
    $factory = $this->freshExportableFactory();

    $exportable = $factory->createFromEntity($node);
    $this->assertSame(ExportableStatus::OutOfSync, $exportable->getStatus());
  }

  private function freshExportableFactory(): ExportableFactory {
    return new ExportableFactory(
      $this->container->get(EntityRepositoryInterface::class),
      $this->container->get(EntityTypeManagerInterface::class),
      $this->container->get(EntityUtility::class),
      $this->container->get(FileSystemInterface::class),
      $this->container->get(ImporterInterface::class),
      $this->container->get(MetaOptionManager::class),
      $this->container->get(LarkSourceManager::class),
      $this->container->get(StatusResolver::class),
    );
  }

}
