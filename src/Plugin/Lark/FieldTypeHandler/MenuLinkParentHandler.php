<?php

declare(strict_types=1);

namespace Drupal\lark\Plugin\Lark\FieldTypeHandler;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\lark\Attribute\LarkFieldTypeHandler;
use Drupal\lark\Plugin\Lark\FieldTypeHandlerBase;

/**
 * Records a menu link's parent as a hard dependency.
 *
 * 'menu_link_content.parent' is a plain string field holding the parent's menu
 * link plugin ID, which for a content link is 'menu_link_content:<uuid>'. The
 * value is already portable, so nothing needs rewriting on export or import.
 * This handler exists only so the parent is discovered as a dependency and
 * therefore imported first - a child imported before its parent produces a
 * broken tree.
 *
 * The chain is bounded: it never leaves menu_link_content, never leaves the
 * menu, and Drupal caps menu depth at 9.
 */
#[LarkFieldTypeHandler(
  id: 'menu_link_parent_handler',
  label: new TranslatableMarkup('Menu Link Parent Handler'),
  description: new TranslatableMarkup('Records a menu link content parent as an export dependency.'),
  fieldTypes: ['string'],
)]
class MenuLinkParentHandler extends FieldTypeHandlerBase {

  /**
   * Prefix of a parent value that points at another content menu link.
   */
  protected const PARENT_PREFIX = 'menu_link_content:';

  /**
   * {@inheritdoc}
   */
  public function getFieldDependencies(FieldItemListInterface $field): array {
    // Registered on 'string', so this runs for every string field on every
    // entity. Bail immediately on anything but a menu link's parent.
    if ($field->getName() !== 'parent') {
      return [];
    }
    if ($field->getEntity()->getEntityTypeId() !== 'menu_link_content') {
      return [];
    }

    $value = (string) ($field->value ?? '');
    if (!str_starts_with($value, static::PARENT_PREFIX)) {
      // Empty (top level), or a module-defined link plugin such as
      // 'system.admin', which Lark does not export.
      return [];
    }

    $uuid = substr($value, strlen(static::PARENT_PREFIX));
    return $uuid === '' ? [] : [$uuid => 'menu_link_content'];
  }

}
