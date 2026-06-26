<?php

namespace Drupal\Tests\lark\Kernel\Multilingual;

use Drupal\Core\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lark\Entity\LarkSource;
use Drupal\lark\Service\ExporterInterface;
use Drupal\lark\Service\ImporterInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that translated entities export and import correctly.
 *
 * @group lark
 */
#[RunTestsInSeparateProcesses]
class TranslationExportImportTest extends KernelTestBase {

  /**
   * Disable strict config schema to avoid lark.settings integer/string mismatch.
   */
  protected $strictConfigSchema = FALSE;

  protected static $modules = [
    'lark', 'node', 'user', 'system', 'field', 'text', 'filter',
    'language', 'content_translation',
  ];

  protected string $exportDir;

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('lark_source');
    $this->installEntitySchema('configurable_language');
    $this->installConfig(['node', 'filter', 'language', 'content_translation', 'lark']);
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    $this->installSchema('node', ['node_access']);

    // Create user 1 (admin) so the importer's AdminAccountSwitcher can switch
    // to an administrative account during entity owner resolution.
    $admin = User::create([
      'uid' => 1,
      'name' => 'admin',
      'status' => 1,
    ]);
    $admin->save();

    // Add French and Spanish languages.
    ConfigurableLanguage::createFromLangcode('fr')->save();
    ConfigurableLanguage::createFromLangcode('es')->save();

    // Enable content translation for node.article.
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);

    // Use a real absolute temp directory (not vfs://) so that
    // LarkSource::directoryProcessed() returns a consistent absolute path.
    $this->exportDir = sys_get_temp_dir() . '/lark-multilingual-' . uniqid();
    LarkSource::create([
      'id' => 'multilingual_source',
      'label' => 'Multilingual Test Source',
      'directory' => $this->exportDir,
    ])->save();

    \Drupal::configFactory()->getEditable('lark.settings')
      ->set('default_source', 'multilingual_source')
      ->save();
  }

  protected function tearDown(): void {
    // Clean up the real temp directory created during the test.
    if (isset($this->exportDir) && is_dir($this->exportDir)) {
      \Drupal::service('file_system')->deleteRecursive($this->exportDir);
    }
    parent::tearDown();
  }

  public function testExportIncludesTranslationsInYaml(): void {
    $node = Node::create(['type' => 'article', 'title' => 'English Title', 'langcode' => 'en']);
    $node->save();
    $node->addTranslation('fr', ['title' => 'French Title'])->save();

    /** @var \Drupal\lark\Service\ExporterInterface $exporter */
    $exporter = $this->container->get(ExporterInterface::class);
    $exporter->exportEntity('multilingual_source', 'node', (int) $node->id(), FALSE);

    $yaml_path = $this->exportDir . '/node/article/' . $node->uuid() . '.yml';
    $content = Yaml::decode(file_get_contents($yaml_path));

    $this->assertArrayHasKey('translations', $content, 'Exported YAML must contain a translations section');
    $this->assertArrayHasKey('fr', $content['translations'], 'French translation must be present in exported YAML');
  }

  public function testImportCreatesAllTranslations(): void {
    $node = Node::create(['type' => 'article', 'title' => 'English Title', 'langcode' => 'en']);
    $node->save();
    $node->addTranslation('fr', ['title' => 'French Title'])->save();
    $node->addTranslation('es', ['title' => 'Spanish Title'])->save();
    $original_uuid = $node->uuid();

    /** @var \Drupal\lark\Service\ExporterInterface $exporter */
    $exporter = $this->container->get(ExporterInterface::class);
    $exporter->exportEntity('multilingual_source', 'node', (int) $node->id(), FALSE);

    $node->delete();

    /** @var \Drupal\lark\Service\ImporterInterface $importer */
    $importer = $this->container->get(ImporterInterface::class);
    $importer->importSource('multilingual_source', FALSE);

    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['uuid' => $original_uuid]);
    $this->assertNotEmpty($nodes);
    $reimported = reset($nodes);

    $this->assertTrue($reimported->hasTranslation('fr'), 'French translation must exist after import');
    $this->assertTrue($reimported->hasTranslation('es'), 'Spanish translation must exist after import');
    $this->assertSame('French Title', $reimported->getTranslation('fr')->label());
    $this->assertSame('Spanish Title', $reimported->getTranslation('es')->label());
  }

  public function testImportUpdatesExistingTranslation(): void {
    $node = Node::create(['type' => 'article', 'title' => 'Original EN', 'langcode' => 'en']);
    $node->save();
    $node->addTranslation('fr', ['title' => 'Original FR'])->save();

    /** @var \Drupal\lark\Service\ExporterInterface $exporter */
    $exporter = $this->container->get(ExporterInterface::class);
    $exporter->exportEntity('multilingual_source', 'node', (int) $node->id(), FALSE);

    // Modify the French translation after export.
    $node->getTranslation('fr')->set('title', 'Modified FR')->save();

    // Re-import should revert the French title to the exported value.
    /** @var \Drupal\lark\Service\ImporterInterface $importer */
    $importer = $this->container->get(ImporterInterface::class);
    $importer->importSourceExport('multilingual_source', $node->uuid(), FALSE);

    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['uuid' => $node->uuid()]);
    $reimported = reset($nodes);
    $this->assertSame('Original FR', $reimported->getTranslation('fr')->label());
  }

  public function testOrphanedTranslationPersistsAfterReimport(): void {
    // This test documents the known gap: translations absent from the YAML
    // are NOT removed during import. If you fix this gap, this test should
    // be updated to assert the translation IS removed.
    $node = Node::create(['type' => 'article', 'title' => 'EN', 'langcode' => 'en']);
    $node->save();
    $node->addTranslation('fr', ['title' => 'FR'])->save();

    /** @var \Drupal\lark\Service\ExporterInterface $exporter */
    $exporter = $this->container->get(ExporterInterface::class);
    $exporter->exportEntity('multilingual_source', 'node', (int) $node->id(), FALSE);

    // Now delete the FR translation and re-export (so YAML no longer has FR).
    $node->removeTranslation('fr');
    $node->save();
    $exporter->exportEntity('multilingual_source', 'node', (int) $node->id(), FALSE);

    // Re-import. The YAML no longer has FR, but the entity in the DB still has no FR.
    // Let's add it back manually to simulate the orphan scenario.
    $node->addTranslation('fr', ['title' => 'Orphaned FR'])->save();

    /** @var \Drupal\lark\Service\ImporterInterface $importer */
    $importer = $this->container->get(ImporterInterface::class);
    $importer->importSourceExport('multilingual_source', $node->uuid(), FALSE);

    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['uuid' => $node->uuid()]);
    $reimported = reset($nodes);
    // KNOWN GAP: the orphaned FR translation is still present after import.
    // If/when this is fixed, change assertTrue to assertFalse.
    $this->assertTrue($reimported->hasTranslation('fr'), 'Known gap: orphaned translation is not removed during re-import');
  }

}
