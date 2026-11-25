<?php

namespace Drupal\Tests\lark\Kernel\FieldTypeHandler;

use Drupal\KernelTests\KernelTestBase;
use Drupal\lark\Model\ExportArray;
use Drupal\lark\Plugin\Lark\FieldTypeHandler\LinkHandler;
use Drupal\lark\Service\FieldTypeHandlerManagerInterface;
use Drupal\lark\Service\Utility\EntityUtility;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\system\Entity\Menu;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests \Drupal\lark\Plugin\Lark\FieldTypeHandler\LinkHandler.
 */
#[CoversClass(LinkHandler::class)]
#[Group('lark')]
#[RunTestsInSeparateProcesses]
class LinkHandlerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'lark', 'node', 'user', 'system', 'field', 'text', 'filter',
    'link', 'menu_link_content', 'path_alias',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('menu_link_content');
    $this->installEntitySchema('path_alias');
    $this->installConfig(['node', 'filter']);
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    $this->installSchema('node', ['node_access']);
    Menu::create(['id' => 'test-menu', 'label' => 'Test menu'])->save();
  }

  /**
   * Creates and saves an article node.
   *
   * @param string $title
   *   The node title.
   *
   * @return \Drupal\node\Entity\Node
   *   The saved node.
   */
  private function createNode(string $title = 'Target'): Node {
    $node = Node::create(['type' => 'article', 'title' => $title]);
    $node->save();
    return $node;
  }

  /**
   * Creates and saves a menu link with the given URI.
   *
   * @param string $uri
   *   The link URI.
   *
   * @return \Drupal\menu_link_content\Entity\MenuLinkContent
   *   The saved menu link.
   */
  private function createLink(string $uri): MenuLinkContent {
    $link = MenuLinkContent::create([
      'title' => 'A link',
      'menu_name' => 'test-menu',
      'link' => ['uri' => $uri],
    ]);
    $link->save();
    return $link;
  }

  /**
   * Gets the first exported link field item of a menu link.
   *
   * @param \Drupal\menu_link_content\Entity\MenuLinkContent $link
   *   The menu link to export.
   *
   * @return array
   *   The exported link field item.
   */
  private function exportedLinkItem(MenuLinkContent $link): array {
    /** @var \Drupal\lark\Service\Utility\EntityUtility $utility */
    $utility = $this->container->get(EntityUtility::class);
    return $utility->getEntityArray($link)['link'][0];
  }

  /**
   * Tests that an entity: URI exports with the target's UUID.
   */
  public function testEntitySchemeUriExportsAsUuid(): void {
    $node = $this->createNode();
    $link = $this->createLink('entity:node/' . $node->id());

    $item = $this->exportedLinkItem($link);

    $this->assertSame('entity:node/' . $node->uuid(), $item['uri']);
    $this->assertSame($node->uuid(), $item['target_uuid']);
    $this->assertSame('node', $item['target_entity_type']);
  }

  /**
   * Tests that an internal:/node URI exports with the UUID, keeping its scheme.
   */
  public function testInternalSchemeUriExportsAsUuidAndKeepsScheme(): void {
    $node = $this->createNode();
    $link = $this->createLink('internal:/node/' . $node->id());

    $item = $this->exportedLinkItem($link);

    $this->assertSame('internal:/node/' . $node->uuid(), $item['uri']);
    $this->assertSame($node->uuid(), $item['target_uuid']);
    $this->assertSame('node', $item['target_entity_type']);
  }

  /**
   * Tests that URIs not pointing at an entity export unchanged.
   */
  public function testNonEntityUrisAreUntouched(): void {
    foreach (['https://example.com', 'internal:/some/alias', 'route:<nolink>', 'internal:/'] as $uri) {
      $link = $this->createLink($uri);
      $item = $this->exportedLinkItem($link);

      $this->assertSame($uri, $item['uri'], "URI '$uri' must pass through unchanged");
      $this->assertArrayNotHasKey('target_uuid', $item);
    }
  }

  /**
   * Tests that an entity URI with an unknown ID exports unchanged.
   */
  public function testUnresolvableEntityIdIsUntouched(): void {
    $link = $this->createLink('entity:node/999999');
    $item = $this->exportedLinkItem($link);

    $this->assertSame('entity:node/999999', $item['uri']);
    $this->assertArrayNotHasKey('target_uuid', $item);
  }

  /**
   * Tests that a URI targeting a non-exportable entity type exports unchanged.
   */
  public function testNonExportableTargetIsUntouched(): void {
    // Users are explicitly excluded from export by EntityTypeInfo.
    $link = $this->createLink('entity:user/1');
    $item = $this->exportedLinkItem($link);

    $this->assertSame('entity:user/1', $item['uri']);
    $this->assertArrayNotHasKey('target_uuid', $item);
  }

  /**
   * Tests that a linked entity is a reference, not a dependency.
   */
  public function testTargetIsSoftReferenceNotDependency(): void {
    /** @var \Drupal\lark\Service\FieldTypeHandlerManagerInterface $manager */
    $manager = $this->container->get(FieldTypeHandlerManagerInterface::class);

    $node = $this->createNode();
    $link = $this->createLink('entity:node/' . $node->id());

    $this->assertSame(
      [$node->uuid() => 'node'],
      $manager->getFieldReferences($link->get('link'))
    );
    $this->assertSame([], $manager->getFieldDependencies($link->get('link')));
  }

  /**
   * Tests that a linked node does not join the export set.
   */
  public function testLinkedNodeIsNotPulledIntoTheExportSet(): void {
    /** @var \Drupal\lark\Service\Utility\EntityUtility $utility */
    $utility = $this->container->get(EntityUtility::class);

    $node = $this->createNode();
    $link = $this->createLink('entity:node/' . $node->id());

    $found = [];
    $pairs = $utility->getEntityUuidEntityTypePairs($link, $found);

    $this->assertArrayHasKey($link->uuid(), $pairs);
    $this->assertArrayNotHasKey(
      $node->uuid(),
      $pairs,
      'A linked node is a soft reference and must never join the export set.'
    );
  }

  /**
   * Tests that the export records the link target as a reference.
   */
  public function testExportRecordsTheReferenceInMeta(): void {
    $node = $this->createNode();
    $link = $this->createLink('entity:node/' . $node->id());

    $export = ExportArray::createFromEntity($link);

    $this->assertSame([$node->uuid() => 'node'], $export->references());
    $this->assertSame([], $export->dependencies());
  }

  /**
   * Tests that import rewrites a UUID entity URI to the local entity ID.
   */
  public function testImportRestoresTheLocalEntityId(): void {
    /** @var \Drupal\lark\Service\FieldTypeHandlerManagerInterface $manager */
    $manager = $this->container->get(FieldTypeHandlerManagerInterface::class);

    $node = $this->createNode();
    $link = $this->createLink('internal:/');

    $values = [[
      'uri' => 'entity:node/' . $node->uuid(),
      'target_uuid' => $node->uuid(),
      'target_entity_type' => 'node',
      'title' => '',
      'options' => [],
    ],
    ];

    $result = $manager->alterImportValues($values, $link->get('link'));

    $this->assertSame('entity:node/' . $node->id(), $result[0]['uri']);
    $this->assertArrayNotHasKey('target_uuid', $result[0]);
    $this->assertArrayNotHasKey('target_entity_type', $result[0]);
  }

  /**
   * Tests that import rewrites a UUID internal URI to the local entity ID.
   */
  public function testImportRestoresTheInternalScheme(): void {
    /** @var \Drupal\lark\Service\FieldTypeHandlerManagerInterface $manager */
    $manager = $this->container->get(FieldTypeHandlerManagerInterface::class);

    $node = $this->createNode();
    $link = $this->createLink('internal:/');

    $values = [[
      'uri' => 'internal:/node/' . $node->uuid(),
      'target_uuid' => $node->uuid(),
      'target_entity_type' => 'node',
      'title' => '',
      'options' => [],
    ],
    ];

    $result = $manager->alterImportValues($values, $link->get('link'));

    $this->assertSame('internal:/node/' . $node->id(), $result[0]['uri']);
  }

  /**
   * Tests that import keeps the UUID URI when the target is missing.
   */
  public function testImportWithMissingTargetDoesNotThrow(): void {
    /** @var \Drupal\lark\Service\FieldTypeHandlerManagerInterface $manager */
    $manager = $this->container->get(FieldTypeHandlerManagerInterface::class);

    $link = $this->createLink('internal:/');

    $values = [[
      'uri' => 'entity:node/11111111-2222-3333-4444-555555555555',
      'target_uuid' => '11111111-2222-3333-4444-555555555555',
      'target_entity_type' => 'node',
      'title' => '',
      'options' => [],
    ],
    ];

    $result = $manager->alterImportValues($values, $link->get('link'));

    $this->assertSame(
      'entity:node/11111111-2222-3333-4444-555555555555',
      $result[0]['uri'],
      'An unresolved target leaves the portable URI in place rather than throwing.'
    );
    $this->assertArrayNotHasKey('target_uuid', $result[0]);
  }

  /**
   * Tests that a UUID-form URI with an existing target exports unchanged.
   */
  public function testUuidFormUriWithExistingTargetExportsStably(): void {
    $node = $this->createNode();
    $link = $this->createLink('entity:node/' . $node->uuid());

    $item = $this->exportedLinkItem($link);

    $this->assertSame('entity:node/' . $node->uuid(), $item['uri']);
    $this->assertSame($node->uuid(), $item['target_uuid']);
    $this->assertSame('node', $item['target_entity_type']);
  }

  /**
   * Tests that a UUID-form URI with a missing target is still referenced.
   */
  public function testUuidFormUriWithAbsentTargetStillAnnotatesAndReferences(): void {
    $missing_uuid = '11111111-2222-3333-4444-555555555555';
    $link = $this->createLink('entity:node/' . $missing_uuid);

    $item = $this->exportedLinkItem($link);

    $this->assertSame('entity:node/' . $missing_uuid, $item['uri']);
    $this->assertSame($missing_uuid, $item['target_uuid']);
    $this->assertSame('node', $item['target_entity_type']);

    $export = ExportArray::createFromEntity($link);
    $this->assertSame([$missing_uuid => 'node'], $export->references());
  }

  /**
   * Tests that re-exporting an unresolved import restores its reference.
   */
  public function testDegradedRoundTripPreservesAnnotationsAndReference(): void {
    /** @var \Drupal\lark\Service\FieldTypeHandlerManagerInterface $manager */
    $manager = $this->container->get(FieldTypeHandlerManagerInterface::class);

    $missing_uuid = '11111111-2222-3333-4444-555555555555';

    // Simulate importing a link whose target is absent: alterImportValue()
    // strips the annotations and leaves the UUID-form URI in place.
    $link = $this->createLink('internal:/');
    $imported = $manager->alterImportValues([[
      'uri' => 'entity:node/' . $missing_uuid,
      'target_uuid' => $missing_uuid,
      'target_entity_type' => 'node',
      'title' => '',
      'options' => [],
    ],
    ], $link->get('link'));

    $this->assertArrayNotHasKey('target_uuid', $imported[0]);
    $link->set('link', $imported);
    $link->save();

    // Re-export must restore both annotations and the reference rather than
    // silently dropping them.
    $item = $this->exportedLinkItem($link);
    $this->assertSame('entity:node/' . $missing_uuid, $item['uri']);
    $this->assertSame($missing_uuid, $item['target_uuid']);
    $this->assertSame('node', $item['target_entity_type']);

    $export = ExportArray::createFromEntity($link);
    $this->assertSame([$missing_uuid => 'node'], $export->references());
    $this->assertSame([], $export->dependencies());
  }

  /**
   * Tests that imported values save cleanly to the link field.
   */
  public function testImportedValuesAreAcceptedByTheLinkField(): void {
    /** @var \Drupal\lark\Service\FieldTypeHandlerManagerInterface $manager */
    $manager = $this->container->get(FieldTypeHandlerManagerInterface::class);

    $node = $this->createNode();
    $link = $this->createLink('internal:/');

    $values = [[
      'uri' => 'entity:node/' . $node->uuid(),
      'target_uuid' => $node->uuid(),
      'target_entity_type' => 'node',
      'title' => '',
      'options' => [],
    ],
    ];

    // Setting the field must not raise on unknown properties - the handler is
    // responsible for stripping its own annotations.
    $link->set('link', $manager->alterImportValues($values, $link->get('link')));
    $link->save();

    $this->assertSame('entity:node/' . $node->id(), $link->get('link')->first()->uri);
  }

  /**
   * Creates a path alias pointing at an entity's canonical path.
   *
   * @param string $alias
   *   The alias path.
   * @param string $system_path
   *   The system path the alias points at.
   * @param string $langcode
   *   The alias language code.
   */
  private function createAlias(string $alias, string $system_path, string $langcode = 'en'): void {
    \Drupal::entityTypeManager()->getStorage('path_alias')->create([
      'path' => $system_path,
      'alias' => $alias,
      'langcode' => $langcode,
    ])->save();
  }

  /**
   * Tests that an alias link records its target as a reference.
   */
  public function testAliasLinkContributesReference(): void {
    /** @var \Drupal\lark\Service\FieldTypeHandlerManagerInterface $manager */
    $manager = $this->container->get(FieldTypeHandlerManagerInterface::class);

    $node = $this->createNode();
    $this->createAlias('/faqs', '/node/' . $node->id());
    $link = $this->createLink('internal:/faqs');

    $this->assertSame(
      [$node->uuid() => 'node'],
      $manager->getFieldReferences($link->get('link')),
      'An alias-form link must record its target as a soft reference.'
    );
  }

  /**
   * Tests that an alias link URI exports verbatim.
   */
  public function testAliasLinkUriIsNeverRewrittenOnExport(): void {
    $node = $this->createNode();
    $this->createAlias('/faqs', '/node/' . $node->id());
    $link = $this->createLink('internal:/faqs');

    $item = $this->exportedLinkItem($link);

    $this->assertSame(
      'internal:/faqs',
      $item['uri'],
      'The editor wrote an alias; the export must preserve it verbatim.'
    );
    $this->assertArrayNotHasKey('target_uuid', $item);
    $this->assertArrayNotHasKey('target_entity_type', $item);
  }

  /**
   * Tests that an alias link adds no dependency.
   */
  public function testAliasLinkStillContributesNoDependency(): void {
    /** @var \Drupal\lark\Service\FieldTypeHandlerManagerInterface $manager */
    $manager = $this->container->get(FieldTypeHandlerManagerInterface::class);

    $node = $this->createNode();
    $this->createAlias('/faqs', '/node/' . $node->id());
    $link = $this->createLink('internal:/faqs');

    $this->assertSame([], $manager->getFieldDependencies($link->get('link')));
  }

  /**
   * Tests that an alias link target does not join the export set.
   */
  public function testAliasTargetIsNotPulledIntoTheExportSet(): void {
    /** @var \Drupal\lark\Service\Utility\EntityUtility $utility */
    $utility = $this->container->get(EntityUtility::class);

    $node = $this->createNode();
    $this->createAlias('/faqs', '/node/' . $node->id());
    $link = $this->createLink('internal:/faqs');

    $found = [];
    $pairs = $utility->getEntityUuidEntityTypePairs($link, $found);

    $this->assertArrayHasKey($link->uuid(), $pairs);
    $this->assertArrayNotHasKey(
      $node->uuid(),
      $pairs,
      'An alias reference must never widen the export set.'
    );
  }

  /**
   * Tests that an unknown alias adds no reference.
   */
  public function testUnresolvableAliasContributesNothing(): void {
    /** @var \Drupal\lark\Service\FieldTypeHandlerManagerInterface $manager */
    $manager = $this->container->get(FieldTypeHandlerManagerInterface::class);

    $link = $this->createLink('internal:/no-such-page');

    $this->assertSame([], $manager->getFieldReferences($link->get('link')));
  }

  /**
   * Tests that an alias with a query string or fragment still resolves.
   */
  public function testAliasWithQueryStringOrFragmentStillResolves(): void {
    /** @var \Drupal\lark\Service\FieldTypeHandlerManagerInterface $manager */
    $manager = $this->container->get(FieldTypeHandlerManagerInterface::class);

    $node = $this->createNode();
    $this->createAlias('/faqs', '/node/' . $node->id());

    foreach (['internal:/faqs?utm=1', 'internal:/faqs#section'] as $uri) {
      $link = $this->createLink($uri);
      $this->assertSame(
        [$node->uuid() => 'node'],
        $manager->getFieldReferences($link->get('link')),
        "URI '$uri' must resolve to the /faqs alias target."
      );
    }
  }

  /**
   * Tests that alias resolution uses the link entity's language.
   */
  public function testAliasResolutionUsesTheEntityLanguage(): void {
    /** @var \Drupal\lark\Service\FieldTypeHandlerManagerInterface $manager */
    $manager = $this->container->get(FieldTypeHandlerManagerInterface::class);

    $english_target = $this->createNode('English target');
    $this->createAlias('/shared-alias', '/node/' . $english_target->id(), 'en');

    $link = $this->createLink('internal:/shared-alias');

    // The link entity is English, so the English alias must be the one used.
    $this->assertSame(
      [$english_target->uuid() => 'node'],
      $manager->getFieldReferences($link->get('link'))
    );
  }

  /**
   * Tests that an alias to a non-exportable entity type adds no reference.
   */
  public function testAliasPointingAtNonExportableTypeContributesNothing(): void {
    /** @var \Drupal\lark\Service\FieldTypeHandlerManagerInterface $manager */
    $manager = $this->container->get(FieldTypeHandlerManagerInterface::class);

    // Users are excluded from export by EntityTypeInfo.
    $this->createAlias('/me', '/user/1');
    $link = $this->createLink('internal:/me');

    $this->assertSame([], $manager->getFieldReferences($link->get('link')));
  }

}
