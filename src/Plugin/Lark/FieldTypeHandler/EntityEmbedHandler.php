<?php

declare(strict_types=1);

namespace Drupal\lark\Plugin\Lark\FieldTypeHandler;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\lark\Attribute\LarkFieldTypeHandler;
use Drupal\lark\Plugin\Lark\TextEmbedHandlerBase;

/**
 * Records entities embedded with the Entity Embed module as dependencies.
 *
 * Markup: <drupal-entity data-entity-type="…" data-entity-uuid="…">. Works
 * from the markup alone, so Entity Embed need not be installed. Legacy embeds
 * with only data-entity-id are skipped; ids are not portable.
 */
#[LarkFieldTypeHandler(
  id: 'entity_embed_handler',
  label: new TranslatableMarkup('Entity Embed Handler'),
  description: new TranslatableMarkup('Records entities embedded with Entity Embed in formatted text as export dependencies.'),
  fieldTypes: ['text', 'text_long', 'text_with_summary'],
)]
class EntityEmbedHandler extends TextEmbedHandlerBase {

  /**
   * {@inheritdoc}
   */
  protected function needle(): string {
    return '<drupal-entity';
  }

  /**
   * {@inheritdoc}
   */
  protected function xpath(): string {
    return '//drupal-entity[@data-entity-type][@data-entity-uuid]';
  }

  /**
   * {@inheritdoc}
   */
  protected function entityTypeId(\DOMElement $element): ?string {
    return trim($element->getAttribute('data-entity-type')) ?: NULL;
  }

}
