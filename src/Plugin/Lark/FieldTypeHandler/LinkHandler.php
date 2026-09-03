<?php

declare(strict_types=1);

namespace Drupal\lark\Plugin\Lark\FieldTypeHandler;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\lark\Attribute\LarkFieldTypeHandler;
use Drupal\lark\Plugin\Lark\FieldTypeHandlerBase;
use Drupal\lark\Routing\EntityTypeInfo;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Makes link field URIs portable across environments.
 *
 * A link stored as 'entity:node/773' carries a local entity ID that means
 * nothing anywhere else. On export the ID is swapped for the target's UUID and
 * the target is annotated; on import it is swapped back for the local ID.
 *
 * The target is recorded as a *soft reference*, never a dependency. A node
 * whose link points at a page absent from this environment is still a complete,
 * valid node, so the target must not be dragged into the export set.
 */
#[LarkFieldTypeHandler(
  id: 'link_handler',
  label: new TranslatableMarkup('Link Handler'),
  description: new TranslatableMarkup('Handles link fields.'),
  fieldTypes: ['link'],
)]
class LinkHandler extends FieldTypeHandlerBase {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entityTypeManager,
    EntityRepositoryInterface $entityRepository,
    LoggerChannelFactoryInterface $loggerFactory,
    protected ?AliasManagerInterface $aliasManager = NULL,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entityTypeManager, $entityRepository, $loggerFactory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity.repository'),
      $container->get(LoggerChannelFactoryInterface::class),
      // path_alias is an optional core module, while this handler runs wherever
      // the 'link' field type exists. Without it there are no aliases to
      // resolve, so alias reference recording simply does not happen - the rest
      // of the handler is unaffected.
      $container->has(AliasManagerInterface::class) ? $container->get(AliasManagerInterface::class) : NULL,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function alterExportValue(array $values, ContentEntityInterface $entity, FieldItemListInterface $field): array {
    foreach ($values as $delta => $item_value) {
      if (!isset($item_value['uri']) || !is_string($item_value['uri'])) {
        continue;
      }

      $parsed = $this->parseUri($item_value['uri']);
      if (!$parsed) {
        continue;
      }

      $resolved = $this->resolveTarget($parsed);
      if (!$resolved) {
        continue;
      }

      $values[$delta]['uri'] = $this->buildUri($parsed['scheme'], $resolved['entity_type_id'], $resolved['uuid']);
      $values[$delta]['target_uuid'] = $resolved['uuid'];
      $values[$delta]['target_entity_type'] = $resolved['entity_type_id'];
    }

    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function alterImportValue(array $values, FieldItemListInterface $field): array {
    foreach ($values as $delta => $item_value) {
      if (empty($item_value['target_uuid']) || empty($item_value['target_entity_type'])) {
        continue;
      }

      $uuid = $item_value['target_uuid'];
      $entity_type_id = $item_value['target_entity_type'];
      $scheme = str_starts_with((string) ($item_value['uri'] ?? ''), 'internal:/') ? 'internal' : 'entity';

      // These are Lark's own annotations, not link field properties. They must
      // come off before the values reach the field or setting it will raise on
      // the unknown properties.
      unset($values[$delta]['target_uuid'], $values[$delta]['target_entity_type']);

      $target = $this->entityRepository->loadEntityByUuid($entity_type_id, $uuid);
      if (!$target) {
        // Deliberately not an exception. Link targets are soft references, so
        // an entity whose target has not been imported yet must still import.
        // The portable URI is left in place; a later import will resolve it
        // once the target exists.
        $this->loggerFactory->get('lark')->warning(
          'Unresolved link target: no @entity_type with UUID @uuid for field @field on @host_type @host_uuid. The link will not resolve until that entity is imported.',
          [
            '@entity_type' => $entity_type_id,
            '@uuid' => $uuid,
            '@field' => $field->getName(),
            '@host_type' => $field->getEntity()->getEntityTypeId(),
            '@host_uuid' => $field->getEntity()->uuid(),
          ]
        );
        continue;
      }

      $values[$delta]['uri'] = $this->buildUri($scheme, $entity_type_id, (string) $target->id());
    }

    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldReferences(FieldItemListInterface $field): array {
    $langcode = $field->getEntity()->language()->getId();

    $references = [];
    foreach ($field as $item) {
      $uri = (string) ($item->uri ?? '');
      $parsed = $this->parseUri($uri) ?? $this->parseAliasUri($uri, $langcode);
      if (!$parsed) {
        continue;
      }

      $resolved = $this->resolveTarget($parsed);
      if ($resolved) {
        $references[$resolved['uuid']] = $resolved['entity_type_id'];
      }
    }

    return $references;
  }

  /**
   * Resolve an alias-based internal URI to an entity, for reference recording.
   *
   * An 'internal:/faqs' link is already portable: PathHandler exports a node's
   * path alias keyed by the path_alias entity's UUID and recreates it on
   * import, so the alias travels with the content. Entity IDs, by contrast,
   * are auto-increment and diverge between environments - which makes an alias
   * link *more* portable than 'entity:node/762', not less.
   *
   * What an alias link lacks is a recorded reference. Without one, a menu's
   * content dependencies are invisible in its export, a missing target imports
   * as a silent dead link rather than a logged warning, and the prune warning
   * cannot see the link at all. This method closes that gap by resolving the
   * alias purely to identify the target.
   *
   * The stored URI is deliberately never rewritten - see that this is called
   * only from getFieldReferences(), never from alterExportValue(). The editor
   * wrote an alias because they meant an alias.
   *
   * @param string $uri
   *   The stored URI.
   * @param string $langcode
   *   Language of the entity holding the field, so a translation resolves
   *   against its own language's aliases.
   *
   * @return array|null
   *   A parsed target in the same shape parseUri() returns, or NULL when the
   *   URI is not an alias, the alias does not resolve, or it resolves to
   *   something that is not a single-segment entity path.
   */
  protected function parseAliasUri(string $uri, string $langcode): ?array {
    // path_alias is optional; with no alias manager there is nothing to resolve.
    if (!$this->aliasManager) {
      return NULL;
    }

    if (!str_starts_with($uri, 'internal:/') || $uri === 'internal:/') {
      return NULL;
    }

    $path = substr($uri, strlen('internal:'));

    // Strip any query string or fragment before resolving; '/faqs?x=1#y' is
    // still the /faqs alias.
    $path = preg_replace('/[?#].*$/', '', $path);
    if ($path === '' || $path === '/') {
      return NULL;
    }

    $internal_path = $this->aliasManager->getPathByAlias($path, $langcode);
    if ($internal_path === $path) {
      // No alias matched. Either a real internal path we already handle, or a
      // dead link - either way there is nothing new to resolve.
      return NULL;
    }

    // Re-use the existing, tested matcher on the resolved path. Multi-segment
    // canonical paths such as '/taxonomy/term/5' do not match, exactly as
    // 'internal:/taxonomy/term/5' does not match today - consistent rather
    // than surprising.
    return $this->parseUri('internal:' . $internal_path);
  }

  /**
   * Parse a URI that addresses an entity by local ID or by UUID.
   *
   * Handles 'entity:<entity_type>/<id>' and 'internal:/<entity_type>/<id>' in
   * both local-ID and UUID form. The UUID form is what a URI looks like after
   * export, and also what it still looks like after an import degrades
   * because its target was absent - recognising it here is what makes a
   * subsequent re-export idempotent instead of destructive. Everything else -
   * external URLs, 'route:' URIs, alias-based internal paths, and
   * multi-segment entity paths such as '/taxonomy/term/5' - returns NULL and
   * is left alone.
   *
   * @param string $uri
   *   The stored URI.
   *
   * @return array|null
   *   Keys 'scheme' ('entity'|'internal'), 'entity_type_id', 'identifier' and
   *   'identifier_type' ('id'|'uuid'), or NULL.
   */
  protected function parseUri(string $uri): ?array {
    $uuid = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

    if (preg_match('#^entity:([a-z0-9_]+)/(\d+)$#', $uri, $matches)) {
      return ['scheme' => 'entity', 'entity_type_id' => $matches[1], 'identifier' => $matches[2], 'identifier_type' => 'id'];
    }

    if (preg_match('#^internal:/([a-z0-9_]+)/(\d+)$#', $uri, $matches)) {
      return ['scheme' => 'internal', 'entity_type_id' => $matches[1], 'identifier' => $matches[2], 'identifier_type' => 'id'];
    }

    if (preg_match('#^entity:([a-z0-9_]+)/(' . $uuid . ')$#', $uri, $matches)) {
      return ['scheme' => 'entity', 'entity_type_id' => $matches[1], 'identifier' => $matches[2], 'identifier_type' => 'uuid'];
    }

    if (preg_match('#^internal:/([a-z0-9_]+)/(' . $uuid . ')$#', $uri, $matches)) {
      return ['scheme' => 'internal', 'entity_type_id' => $matches[1], 'identifier' => $matches[2], 'identifier_type' => 'uuid'];
    }

    return NULL;
  }

  /**
   * Rebuild a URI in the given scheme.
   *
   * @param string $scheme
   *   Either 'entity' or 'internal'.
   * @param string $entity_type_id
   *   Target entity type ID.
   * @param string $identifier
   *   A local entity ID when writing for Drupal, a UUID when writing an export.
   *
   * @return string
   *   The URI.
   */
  protected function buildUri(string $scheme, string $entity_type_id, string $identifier): string {
    return $scheme === 'internal'
      ? "internal:/{$entity_type_id}/{$identifier}"
      : "entity:{$entity_type_id}/{$identifier}";
  }

  /**
   * Resolve a parsed URI to the reference it should record.
   *
   * ID-form identifiers are resolved by loading the entity locally, exactly
   * as before. UUID-form identifiers are resolved with
   * EntityRepositoryInterface::loadEntityByUuid(); when that target is not
   * found locally the reference is still returned, built from the UUID and
   * entity type already in the URI, so that a link degraded by a prior
   * import keeps its annotations and its `_meta.references` entry across a
   * re-export instead of losing them.
   *
   * @param array $parsed
   *   The result of parseUri().
   *
   * @return array|null
   *   Keys 'uuid', 'entity_type_id' and 'entity' (the loaded entity, or NULL
   *   when a UUID-form URI's target could not be loaded), or NULL when the
   *   entity type is unknown, not exportable, or (for ID-form URIs) the ID
   *   does not resolve to an entity.
   */
  protected function resolveTarget(array $parsed): ?array {
    if (!$this->isExportableEntityType($parsed['entity_type_id'])) {
      return NULL;
    }

    if ($parsed['identifier_type'] === 'uuid') {
      $entity = $this->entityRepository->loadEntityByUuid($parsed['entity_type_id'], $parsed['identifier']);
      return [
        'uuid' => $parsed['identifier'],
        'entity_type_id' => $parsed['entity_type_id'],
        'entity' => $entity,
      ];
    }

    $entity = $this->entityTypeManager->getStorage($parsed['entity_type_id'])->load($parsed['identifier']);
    if (!$entity) {
      return NULL;
    }

    return [
      'uuid' => $entity->uuid(),
      'entity_type_id' => $entity->getEntityTypeId(),
      'entity' => $entity,
    ];
  }

  /**
   * Whether an entity type is one Lark exports.
   *
   * @param string $entity_type_id
   *   Entity type ID parsed from the URI.
   *
   * @return bool
   *   TRUE if the type is known to Drupal and flagged exportable by Lark.
   */
  protected function isExportableEntityType(string $entity_type_id): bool {
    if (!$this->entityTypeManager->hasDefinition($entity_type_id)) {
      return FALSE;
    }

    return (bool) $this->entityTypeManager->getDefinition($entity_type_id)->get(EntityTypeInfo::IS_EXPORTABLE);
  }

}
