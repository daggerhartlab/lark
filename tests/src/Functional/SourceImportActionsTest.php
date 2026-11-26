<?php

declare(strict_types=1);

namespace Drupal\Tests\lark\Functional;

use Drupal\lark\Entity\LarkSource;
use Drupal\lark\Service\ExporterInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the import actions on a source's page.
 */
#[Group('lark')]
#[RunTestsInSeparateProcesses]
class SourceImportActionsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['lark', 'node', 'user', 'field', 'text', 'filter'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The UUID of the exported node, deleted from the site.
   *
   * @var string
   */
  protected string $uuid;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    LarkSource::create([
      'id' => 'test_source',
      'label' => 'Test Source',
      'directory' => $this->siteDirectory . '/lark-test-exports',
    ])->save();
    $this->config('lark.settings')->set('default_source', 'test_source')->save();

    $node = Node::create(['type' => 'article', 'title' => 'Exported']);
    $node->save();
    $this->container->get(ExporterInterface::class)->exportEntity('test_source', 'node', (int) $node->id(), FALSE);
    $this->uuid = $node->uuid();
    $node->delete();

    $this->drupalLogin($this->drupalCreateUser([
      'lark administer configuration',
      'lark import entity',
      'lark export entity',
    ]));
  }

  /**
   * Tests the source page's Import All button.
   */
  public function testImportAllLink(): void {
    $this->drupalGet('/admin/lark/source/test_source');
    $this->clickLink('Import All');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertNodeImported();
  }

  /**
   * Tests the Import operation for one export.
   */
  public function testImportOperationLink(): void {
    $this->drupalGet('/admin/lark/source/test_source');
    $link = $this->assertSession()->elementExists('xpath', '//a[contains(@href, "/admin/lark/action/import-source-entity/test_source/' . $this->uuid . '")]');
    $this->drupalGet($this->getAbsoluteUrl($link->getAttribute('href')));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertNodeImported();
  }

  /**
   * Tests that the import actions only run from the source page's links.
   */
  public function testImportActionsRequireLinkToken(): void {
    $this->drupalGet('/admin/lark/action/import-source/test_source');
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet('/admin/lark/action/import-source-entity/test_source/' . $this->uuid);
    $this->assertSession()->statusCodeEquals(403);
    $this->assertNull($this->loadImportedNode());
  }

  /**
   * Asserts that the exported node was imported.
   */
  protected function assertNodeImported(): void {
    $node = $this->loadImportedNode();
    $this->assertInstanceOf(NodeInterface::class, $node);
    $this->assertSame('Exported', $node->label());
  }

  /**
   * Loads the exported node from the site, if it was imported.
   */
  protected function loadImportedNode(): ?NodeInterface {
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache();
    $node = $this->container->get('entity.repository')->loadEntityByUuid('node', $this->uuid);
    return $node instanceof NodeInterface ? $node : NULL;
  }

}
