<?php

declare(strict_types=1);

namespace Drupal\Tests\lark\Kernel\MetaOption;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Archiver\ArchiveTar;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormState;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\lark\Entity\LarkSource;
use Drupal\lark\Form\EntityExportForm;
use Drupal\lark\Form\EntityImportForm;
use Drupal\lark\Model\Exportable;
use Drupal\lark\Plugin\Lark\FieldTypeHandler\FileUuidHandler;
use Drupal\lark\Plugin\Lark\MetaOption\FileAssets;
use Drupal\lark\Service\AssetFileManager;
use Drupal\lark\Service\ExportableFactoryInterface;
use Drupal\lark\Service\ExporterInterface;
use Drupal\lark\Service\ImporterInterface;
use Drupal\lark\Service\LarkSourceManager;
use Drupal\lark\Service\MetaOptionManager;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests exporting and importing file assets.
 */
#[CoversClass(FileAssets::class)]
#[CoversClass(AssetFileManager::class)]
#[CoversClass(FileUuidHandler::class)]
#[Group('lark')]
#[RunTestsInSeparateProcesses]
class FileAssetsTest extends KernelTestBase {

  /**
   * Contents of the test file.
   */
  protected const string CONTENTS = 'lark file asset';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'lark', 'node', 'user', 'system', 'field', 'text', 'filter', 'file',
  ];

  /**
   * The absolute path of the export directory.
   *
   * @var string
   */
  protected string $exportDir;

  /**
   * The file attached to the node.
   *
   * @var \Drupal\file\FileInterface
   */
  protected FileInterface $file;

  /**
   * The node referencing the file.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected NodeInterface $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('lark_source');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['node', 'filter', 'lark']);

    // The importer switches to user 1 to resolve entity owners.
    User::create(['uid' => 1, 'name' => 'admin', 'status' => 1])->save();

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_file',
      'entity_type' => 'node',
      'type' => 'file',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_file',
      'entity_type' => 'node',
      'bundle' => 'article',
    ])->save();

    $this->exportDir = sys_get_temp_dir() . '/lark-test-assets-' . uniqid();
    LarkSource::create([
      'id' => 'test_source',
      'label' => 'Test Source',
      'directory' => $this->exportDir,
    ])->save();
    $this->config('lark.settings')->set('default_source', 'test_source')->save();

    file_put_contents('public://test.txt', static::CONTENTS);
    $this->file = File::create(['uri' => 'public://test.txt']);
    $this->file->setPermanent();
    $this->file->save();

    $this->node = Node::create([
      'type' => 'article',
      'title' => 'With file',
      'field_file' => ['target_id' => $this->file->id()],
    ]);
    $this->node->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if (isset($this->exportDir) && is_dir($this->exportDir)) {
      $this->container->get(FileSystemInterface::class)->deleteRecursive($this->exportDir);
    }
    parent::tearDown();
  }

  /**
   * Tests that exporting writes the asset beside the file's export.
   */
  public function testExportWritesAsset(): void {
    $this->export();
    $this->assertFileExists($this->assetPath());
    $this->assertSame(static::CONTENTS, file_get_contents($this->assetPath()));
  }

  /**
   * Tests that no asset is written when the settings say not to.
   */
  public function testExportSkipsAssetWhenDisabled(): void {
    $this->setLarkSetting('should_export_assets', FALSE);
    $this->export();
    $this->assertFileExists($this->exportDir . '/file/file/' . $this->file->uuid() . '.yml');
    $this->assertFileDoesNotExist($this->assetPath());
  }

  /**
   * Tests that a per-file override exports the asset despite the settings.
   */
  public function testExportOverrideEnablesAsset(): void {
    $this->setLarkSetting('should_export_assets', FALSE);
    $this->export(['should_export' => TRUE]);
    $this->assertFileExists($this->assetPath());
  }

  /**
   * Tests that a per-file override skips the asset despite the settings.
   */
  public function testExportOverrideDisablesAsset(): void {
    $this->export(['should_export' => FALSE]);
    $this->assertFileDoesNotExist($this->assetPath());
  }

  /**
   * Tests that a download archive includes the asset.
   */
  public function testDownloadArchiveIncludesAsset(): void {
    $this->assertContains($this->archiveAssetName(), $this->downloadArchiveNames());
  }

  /**
   * Tests that a download archive leaves out the asset when disabled.
   */
  public function testDownloadArchiveSkipsAssetWhenDisabled(): void {
    $this->setLarkSetting('should_export_assets', FALSE);
    $this->assertNotContains($this->archiveAssetName(), $this->downloadArchiveNames());
  }

  /**
   * Tests that a per-file override adds the asset despite the settings.
   */
  public function testDownloadArchiveOverrideEnablesAsset(): void {
    $this->setLarkSetting('should_export_assets', FALSE);
    $names = $this->downloadArchiveNames(['should_export' => TRUE]);
    $this->assertContains($this->archiveAssetName(), $names);
  }

  /**
   * Tests that a per-file override leaves out the asset despite the settings.
   */
  public function testDownloadArchiveOverrideDisablesAsset(): void {
    $names = $this->downloadArchiveNames(['should_export' => FALSE]);
    $this->assertNotContains($this->archiveAssetName(), $names);
  }

  /**
   * Tests importing a file and its asset into a site that has neither.
   */
  public function testImportRestoresFileAndAsset(): void {
    $this->export();
    $uuid = $this->file->uuid();
    $this->deleteContent();

    // Take the next file ID so the imported file can't reuse the old one.
    file_put_contents('public://other.txt', 'other');
    File::create(['uri' => 'public://other.txt'])->save();

    $this->import();

    $file = $this->loadFile($uuid);
    $this->assertSame('public://test.txt', $file->getFileUri());
    $this->assertSame(static::CONTENTS, file_get_contents('public://test.txt'));
    $this->assertNotEquals($this->file->id(), $file->id());

    $node = $this->loadNode();
    $this->assertEquals($file->id(), $node->get('field_file')->target_id);
  }

  /**
   * Tests that the file entity imports without its asset when disabled.
   */
  public function testImportSkipsAssetWhenDisabled(): void {
    $this->export();
    $uuid = $this->file->uuid();
    $this->deleteContent();
    $this->setLarkSetting('should_import_assets', FALSE);

    $this->import();

    $this->assertSame('public://test.txt', $this->loadFile($uuid)->getFileUri());
    $this->assertFileDoesNotExist('public://test.txt');
  }

  /**
   * Tests that an exported override skips the asset despite the settings.
   */
  public function testImportOverrideDisablesAsset(): void {
    $this->export(['should_import' => FALSE]);
    $this->deleteContent();

    $this->import();

    $this->assertFileDoesNotExist('public://test.txt');
  }

  /**
   * Tests that an exported override imports the asset despite the settings.
   */
  public function testImportOverrideEnablesAsset(): void {
    $this->export(['should_import' => TRUE]);
    $this->deleteContent();
    $this->setLarkSetting('should_import_assets', FALSE);

    $this->import();

    $this->assertFileExists('public://test.txt');
  }

  /**
   * Tests that an identical unmanaged file at the destination is reused.
   */
  public function testImportReusesIdenticalUnmanagedFile(): void {
    $this->export();
    $uuid = $this->file->uuid();
    $this->deleteContent();
    file_put_contents('public://test.txt', static::CONTENTS);
    $this->setLarkSetting('asset_import_file_exists', 'Rename');

    $this->import();

    $this->assertSame('public://test.txt', $this->loadFile($uuid)->getFileUri());
    $this->assertFileDoesNotExist('public://test_0.txt');
  }

  /**
   * Tests re-importing a file whose entity already exists.
   */
  public function testReimportExistingFileKeepsUri(): void {
    $this->export();
    $this->setLarkSetting('asset_import_file_exists', 'Rename');

    $this->import();

    $this->assertSame('public://test.txt', $this->loadFile($this->file->uuid())->getFileUri());
    $this->assertFileDoesNotExist('public://test_0.txt');
  }

  /**
   * Tests that an identical file owned by another entity isn't shared.
   */
  public function testImportDoesNotShareAnotherEntitysFile(): void {
    $this->export();
    $uuid = $this->file->uuid();
    $this->deleteContent();
    file_put_contents('public://test.txt', static::CONTENTS);
    File::create(['uri' => 'public://test.txt'])->save();
    $this->setLarkSetting('asset_import_file_exists', 'Rename');

    $this->import();

    $this->assertSame('public://test_0.txt', $this->loadFile($uuid)->getFileUri());
  }

  /**
   * Tests that re-exporting replaces the asset by default.
   */
  public function testReexportReplacesAsset(): void {
    $this->export();
    file_put_contents('public://test.txt', 'changed');

    $this->export();

    $this->assertSame('changed', file_get_contents($this->assetPath()));
  }

  /**
   * Tests that re-exporting with Rename keeps the earlier asset.
   */
  public function testReexportWithRenameKeepsAsset(): void {
    $this->export();
    file_put_contents('public://test.txt', 'changed');
    $this->setLarkSetting('asset_export_file_exists', 'Rename');

    $this->export();

    $this->assertSame(static::CONTENTS, file_get_contents($this->assetPath()));
  }

  /**
   * Tests that an asset exported without the UUID prefix still imports.
   */
  public function testImportLegacyAssetFilename(): void {
    $this->export();
    $directory = dirname($this->assetPath());
    rename($this->assetPath(), $directory . '/test.txt');
    $this->deleteContent();

    $this->import();

    $this->assertSame(static::CONTENTS, file_get_contents('public://test.txt'));
  }

  /**
   * Tests that form values only keep choices that differ from the settings.
   */
  public function testProcessFormValuesKeepsOnlyOverrides(): void {
    $plugin = $this->fileAssets();
    $exportable = new Exportable($this->file);

    $this->assertSame([], $plugin->processFormValues(
      ['should_export' => '1', 'should_import' => '1'],
      $exportable,
      new FormState(),
    ));
    $this->assertSame(['should_export' => FALSE, 'should_import' => FALSE], $plugin->processFormValues(
      ['should_export' => '0', 'should_import' => '0'],
      $exportable,
      new FormState(),
    ));
  }

  /**
   * Tests form values against settings that disable both assets.
   */
  public function testProcessFormValuesWithAssetsDisabled(): void {
    $this->setLarkSetting('should_export_assets', FALSE);
    $this->setLarkSetting('should_import_assets', FALSE);
    $plugin = $this->fileAssets();
    $exportable = new Exportable($this->file);

    $this->assertSame([], $plugin->processFormValues(
      ['should_export' => '0', 'should_import' => '0'],
      $exportable,
      new FormState(),
    ));
    $this->assertSame(['should_export' => TRUE], $plugin->processFormValues(
      ['should_export' => '1', 'should_import' => '0'],
      $exportable,
      new FormState(),
    ));
  }

  /**
   * Tests that the form shows the settings when there is no override.
   */
  public function testFormElementDefaultsToSettings(): void {
    $this->setLarkSetting('should_import_assets', FALSE);
    $element = $this->buildFormElement(EntityExportForm::class);

    $this->assertSame(1, $element['should_export']['#default_value']);
    $this->assertSame(0, $element['should_import']['#default_value']);
    $this->assertStringStartsWith('(Default)', (string) $element['should_export']['#options'][1]);
    $this->assertStringStartsWith('(Override)', (string) $element['should_export']['#options'][0]);
    $this->assertStringStartsWith('(Default)', (string) $element['should_import']['#options'][0]);
    $this->assertStringStartsWith('(Override)', (string) $element['should_import']['#options'][1]);
  }

  /**
   * Tests that the form shows an exported override instead of the settings.
   */
  public function testFormElementShowsExportedOverride(): void {
    $this->export(['should_export' => FALSE, 'should_import' => FALSE]);
    $element = $this->buildFormElement(EntityExportForm::class);

    $this->assertSame(0, $element['should_export']['#default_value']);
    $this->assertSame(0, $element['should_import']['#default_value']);
  }

  /**
   * Tests that the choices can be changed on export forms only.
   */
  public function testFormElementDisabledOnImportForms(): void {
    $export = $this->buildFormElement(EntityExportForm::class);
    $this->assertFalse($export['should_export']['#disabled']);
    $this->assertFalse($export['should_import']['#disabled']);

    $import = $this->buildFormElement(EntityImportForm::class);
    $this->assertTrue($import['should_export']['#disabled']);
    $this->assertTrue($import['should_import']['#disabled']);
  }

  /**
   * Tests that an override survives a re-export that doesn't set one.
   */
  public function testOverrideSurvivesReexport(): void {
    $this->export(['should_import' => FALSE]);
    $this->resetServices();

    $this->export();

    $yaml = Yaml::decode(file_get_contents($this->exportDir . '/file/file/' . $this->file->uuid() . '.yml'));
    $this->assertSame(['should_import' => FALSE], $yaml['_meta']['options']['file_assets']);
  }

  /**
   * Tests that submitting the defaults in the export form clears an override.
   */
  public function testExportFormClearsOverride(): void {
    $this->export(['should_import' => FALSE]);
    $this->resetServices();

    $this->exportFromForm(['should_export' => '1', 'should_import' => '1']);

    $this->assertArrayNotHasKey('file_assets', $this->exportedOptions());
  }

  /**
   * Tests that the export form replaces an override with a different one.
   */
  public function testExportFormReplacesOverride(): void {
    $this->export(['should_import' => FALSE]);
    $this->resetServices();

    $this->exportFromForm(['should_export' => '0', 'should_import' => '1']);

    $this->assertSame(['should_export' => FALSE], $this->exportedOptions()['file_assets']);
  }

  /**
   * Exports the node with file asset choices submitted through the form.
   *
   * @param array $file_assets
   *   The submitted file assets values for the file.
   */
  protected function exportFromForm(array $file_assets): void {
    $form = $this->container->get('class_resolver')->getInstanceFromDefinition(EntityExportForm::class);
    $form_state = (new FormState())->setValue('export_form_values', [
      $this->file->uuid() => ['file_assets' => $file_assets],
    ]);
    $overrides = $form->getSubmittedOverrides('export_form_values', $form_state);
    $this->container->get(ExporterInterface::class)
      ->exportEntity('test_source', 'node', (int) $this->node->id(), FALSE, $overrides);
  }

  /**
   * Returns the options in the file's exported YAML.
   */
  protected function exportedOptions(): array {
    $yaml = Yaml::decode(file_get_contents($this->exportDir . '/file/file/' . $this->file->uuid() . '.yml'));
    return $yaml['_meta']['options'] ?? [];
  }

  /**
   * Exports the node and its file.
   *
   * @param array $file_assets
   *   File assets option values for the file.
   */
  protected function export(array $file_assets = []): void {
    $overrides = $file_assets ? [$this->file->uuid() => ['file_assets' => $file_assets]] : [];
    $this->container->get(ExporterInterface::class)
      ->exportEntity('test_source', 'node', (int) $this->node->id(), FALSE, $overrides);
  }

  /**
   * Builds a download archive of the node and lists its contents.
   *
   * @param array $file_assets
   *   File assets option values for the file.
   *
   * @return string[]
   *   The paths in the archive.
   */
  protected function downloadArchiveNames(array $file_assets = []): array {
    $overrides = $file_assets ? [$this->file->uuid() => ['file_assets' => $file_assets]] : [];
    $source = $this->container->get(LarkSourceManager::class)->load('test_source');
    $exportables = $this->container->get(ExportableFactoryInterface::class)
      ->createFromEntityWithDependencies('node', (int) $this->node->id(), $source, $overrides);

    // The archive always holds the file's YAML before any asset is added.
    $exportable = $exportables[$this->file->uuid()];
    $archive = new ArchiveTar($this->exportDir . '/archive.tar.gz', 'gz');
    $archive->addString('file/file/' . $this->file->uuid() . '.yml', $exportable->toYaml());
    $this->fileAssets()->preExportDownload($archive, $exportable);
    return array_column($archive->listContent(), 'filename');
  }

  /**
   * Returns the asset's path inside a download archive.
   */
  protected function archiveAssetName(): string {
    return 'file/file/' . $this->file->uuid() . '--test.txt';
  }

  /**
   * Builds the file assets form element as a form would.
   *
   * @param class-string<\Drupal\Core\Form\FormInterface> $form_class
   *   The form the element is built for.
   *
   * @return array
   *   The form element.
   */
  protected function buildFormElement(string $form_class): array {
    $this->resetServices();
    $source = $this->container->get(LarkSourceManager::class)->load('test_source');
    $exportables = $this->container->get(ExportableFactoryInterface::class)
      ->createFromEntityWithDependencies('node', (int) $this->node->id(), $source);

    $form_state = new FormState();
    $form_state->setFormObject($this->container->get('class_resolver')->getInstanceFromDefinition($form_class));
    $form = [];
    return $this->fileAssets()->formElement($exportables[$this->file->uuid()], $form, $form_state, ['export_form_values']);
  }

  /**
   * Replaces the services so nothing is cached from earlier exports.
   */
  protected function resetServices(): void {
    $this->container->get('kernel')->resetContainer();
    $this->container = \Drupal::getContainer();
  }

  /**
   * Imports the test source.
   */
  protected function import(): void {
    $this->container->get(ImporterInterface::class)->importSource('test_source', FALSE);
  }

  /**
   * Deletes the node, the file entity, and the file on disk.
   */
  protected function deleteContent(): void {
    $this->node->delete();
    $this->file->delete();
    $this->assertFileDoesNotExist('public://test.txt');
  }

  /**
   * Returns the path of the exported asset.
   */
  protected function assetPath(): string {
    return $this->exportDir . '/file/file/' . $this->file->uuid() . '--test.txt';
  }

  /**
   * Loads a file by UUID.
   */
  protected function loadFile(string $uuid): FileInterface {
    $file = $this->container->get('entity.repository')->loadEntityByUuid('file', $uuid);
    $this->assertInstanceOf(FileInterface::class, $file);
    return $file;
  }

  /**
   * Loads the imported node by UUID.
   */
  protected function loadNode(): NodeInterface {
    $node = $this->container->get('entity.repository')->loadEntityByUuid('node', $this->node->uuid());
    $this->assertInstanceOf(NodeInterface::class, $node);
    return $node;
  }

  /**
   * Returns the file assets meta option.
   */
  protected function fileAssets(): FileAssets {
    $plugin = $this->container->get(MetaOptionManager::class)->getInstances()['file_assets'];
    $this->assertInstanceOf(FileAssets::class, $plugin);
    return $plugin;
  }

  /**
   * Sets a lark setting.
   */
  protected function setLarkSetting(string $name, mixed $value): void {
    $this->config('lark.settings')->set($name, $value)->save();
  }

}
