<?php

declare(strict_types=1);

namespace Drupal\lark\Service\Utility;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\lark\Entity\LarkSourceInterface;
use Drupal\lark\Service\ImporterInterface;
use Drupal\system\MenuInterface;

/**
 * Finds the content menu links that belong to a menu.
 *
 * The only menu-aware piece of Lark's menu export. Everything it hands back is
 * an ordinary entity or UUID, so the bulk export and import services stay
 * menu-agnostic.
 */
class MenuLinkCollector {

  /**
   * Prefix of a parent value that points at another content menu link.
   */
  protected const PARENT_PREFIX = 'menu_link_content:';

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ImporterInterface $importer,
  ) {}

  /**
   * Get a menu's content links, parents before children.
   *
   * Import order matters: a child written before its parent produces a broken
   * tree. Siblings are ordered by weight, then title, so exports are stable
   * between runs.
   *
   * @param \Drupal\system\MenuInterface $menu
   *   The menu.
   *
   * @return \Drupal\menu_link_content\MenuLinkContentInterface[]
   *   Links keyed by UUID, ancestors first.
   */
  public function getMenuLinks(MenuInterface $menu): array {
    $storage = $this->entityTypeManager->getStorage('menu_link_content');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('menu_name', $menu->id())
      ->execute();

    if (!$ids) {
      return [];
    }

    /** @var \Drupal\menu_link_content\MenuLinkContentInterface[] $links */
    $links = $storage->loadMultiple($ids);

    // Key by UUID so parent values can be looked up directly.
    $by_uuid = [];
    foreach ($links as $link) {
      $by_uuid[$link->uuid()] = $link;
    }

    // Sibling order first, so the depth-first walk below emits a stable tree.
    uasort($by_uuid, function ($a, $b) {
      return [$a->getWeight(), (string) $a->getTitle()] <=> [$b->getWeight(), (string) $b->getTitle()];
    });

    // Group by parent UUID. A parent outside this set - an empty value, or a
    // module-defined plugin such as 'system.admin' - counts as top level, so
    // no link is ever dropped.
    $children = [];
    foreach ($by_uuid as $uuid => $link) {
      $parent_uuid = $this->parentUuid($link->getParentId());
      if ($parent_uuid === NULL || !isset($by_uuid[$parent_uuid])) {
        $parent_uuid = '';
      }
      $children[$parent_uuid][$uuid] = $link;
    }

    $flat = $this->flattenTree($children, '');
    // A cyclic or self-referencing parent leaves links unreachable; never drop one.
    return $flat + $by_uuid;
  }

  /**
   * Get the UUIDs of a menu's links as found in a source.
   *
   * This is what makes import source-driven: a link present in the source but
   * missing from the site is still returned, so it can be created.
   *
   * @param \Drupal\lark\Entity\LarkSourceInterface $source
   *   The source to search.
   * @param \Drupal\system\MenuInterface $menu
   *   The menu.
   *
   * @return string[]
   *   UUIDs of the menu links exported to this source.
   */
  public function getSourceLinkUuids(LarkSourceInterface $source, MenuInterface $menu): array {
    $uuids = [];
    foreach ($this->importer->discoverSourceExports($source) as $uuid => $export) {
      if ($export->entityTypeId() !== 'menu_link_content') {
        continue;
      }

      $menu_name_field = $export->getField('menu_name');
      $menu_name = is_array($menu_name_field) ? ($menu_name_field[0]['value'] ?? NULL) : NULL;
      if ($menu_name === $menu->id()) {
        $uuids[] = $uuid;
      }
    }

    return $uuids;
  }

  /**
   * Extract the UUID from a menu link plugin ID.
   *
   * @param string $parent_id
   *   The value of the link's 'parent' field.
   *
   * @return string|null
   *   The UUID, or NULL when the parent is empty or is not a content link.
   */
  protected function parentUuid(string $parent_id): ?string {
    if (!str_starts_with($parent_id, static::PARENT_PREFIX)) {
      return NULL;
    }

    $uuid = substr($parent_id, strlen(static::PARENT_PREFIX));
    return $uuid === '' ? NULL : $uuid;
  }

  /**
   * Walk the grouped tree depth-first, emitting ancestors before descendants.
   *
   * @param array $children
   *   Links grouped by parent UUID, '' for top level.
   * @param string $parent_uuid
   *   The parent to emit children for.
   *
   * @return array
   *   Links keyed by UUID.
   */
  protected function flattenTree(array $children, string $parent_uuid): array {
    $flat = [];
    foreach ($children[$parent_uuid] ?? [] as $uuid => $link) {
      $flat[$uuid] = $link;
      $flat += $this->flattenTree($children, $uuid);
    }

    return $flat;
  }

}
