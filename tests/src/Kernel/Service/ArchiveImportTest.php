<?php

declare(strict_types=1);

namespace Drupal\Tests\lark\Kernel\Service;

use Drupal\Core\Archiver\ArchiveTar;
use Drupal\KernelTests\KernelTestBase;
use Drupal\lark\Entity\LarkSource;
use Drupal\lark\Service\ExporterInterface;
use Drupal\lark\Service\Importer;
use Drupal\lark\Service\ImporterInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests importing archives.
 */
#[CoversClass(Importer::class)]
#[Group('lark')]
#[RunTestsInSeparateProcesses]
class ArchiveImportTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'lark', 'node', 'user', 'system', 'field', 'text', 'filter',
  ];

  /**
   * Temporary paths to remove after the test.
   *
   * @var string[]
   */
  protected array $cleanup = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('lark_source');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['node', 'filter', 'lark']);

    // The importer switches to user 1 to resolve entity owners.
    User::create(['uid' => 1, 'name' => 'admin', 'status' => 1])->save();
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    foreach ($this->cleanup as $path) {
      $this->container->get('file_system')->deleteRecursive($path);
    }
    parent::tearDown();
  }

  /**
   * Tests that an archive import leaves no source or directory behind.
   */
  public function testImportArchiveCleansUp(): void {
    $before = $this->archiveDirectories();
    [$archive, $uuid] = $this->createArchive('Archived');

    $this->importer()->importArchive($archive, FALSE);

    $this->assertNodeExists($uuid);
    $this->assertSame([], LarkSource::loadMultiple());
    $this->assertSame($before, $this->archiveDirectories());
  }

  /**
   * Tests that a source left by an unfinished import doesn't block imports.
   */
  public function testImportArchiveWithLeftoverTmpSource(): void {
    LarkSource::create([
      'id' => '_tmp',
      'label' => 'Leftover',
      'directory' => sys_get_temp_dir(),
      'status' => FALSE,
    ])->save();
    [$archive, $uuid] = $this->createArchive('Archived');

    $this->importer()->importArchive($archive, FALSE);

    $this->assertNodeExists($uuid);
  }

  /**
   * Tests importing two archives in one request.
   */
  public function testImportTwoArchives(): void {
    [$first, $first_uuid] = $this->createArchive('First');
    [$second, $second_uuid] = $this->createArchive('Second');

    $this->importer()->importArchive($first, FALSE);
    $this->importer()->importArchive($second, FALSE);

    $this->assertNodeExists($first_uuid);
    $this->assertNodeExists($second_uuid);
  }

  /**
   * Exports a new node to an archive, then deletes the node.
   *
   * @return array
   *   The archive path and the node's UUID.
   */
  protected function createArchive(string $title): array {
    $id = 'archive_' . strtolower($this->randomMachineName());
    $directory = sys_get_temp_dir() . '/lark-test-' . $id;
    $this->cleanup[] = $directory;
    LarkSource::create(['id' => $id, 'label' => $title, 'directory' => $directory])->save();

    $node = Node::create(['type' => 'article', 'title' => $title]);
    $node->save();
    $this->container->get(ExporterInterface::class)->exportEntity($id, 'node', (int) $node->id(), FALSE);

    $archive = $directory . '.tar.gz';
    $this->cleanup[] = $archive;
    (new ArchiveTar($archive, 'gz'))->createModify([$directory], '', $directory);

    $node->delete();
    LarkSource::load($id)->delete();
    return [$archive, $node->uuid()];
  }

  /**
   * Lists the archive extraction directories in the temporary directory.
   *
   * @return string[]
   *   Directory paths.
   */
  protected function archiveDirectories(): array {
    return glob($this->container->get('file_system')->getTempDirectory() . '/lark-archive*') ?: [];
  }

  /**
   * Returns the importer.
   */
  protected function importer(): ImporterInterface {
    return $this->container->get(ImporterInterface::class);
  }

  /**
   * Asserts that a node with the UUID exists.
   */
  protected function assertNodeExists(string $uuid): void {
    $this->assertInstanceOf(NodeInterface::class, $this->container->get('entity.repository')->loadEntityByUuid('node', $uuid));
  }

}
