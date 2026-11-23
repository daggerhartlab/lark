<?php

declare(strict_types=1);

namespace Drupal\lark\Plugin\Lark\FieldTypeHandler;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\lark\Attribute\LarkFieldTypeHandler;
use Drupal\lark\Plugin\Lark\TextEmbedHandlerBase;

/**
 * Records media embedded with core's media embed as export dependencies.
 *
 * Markup: <drupal-media data-entity-type="media" data-entity-uuid="…">.
 */
#[LarkFieldTypeHandler(
  id: 'media_embed_handler',
  label: new TranslatableMarkup('Media Embed Handler'),
  description: new TranslatableMarkup('Records media embedded in formatted text as export dependencies.'),
  fieldTypes: ['text', 'text_long', 'text_with_summary'],
)]
class MediaEmbedHandler extends TextEmbedHandlerBase {

  /**
   * {@inheritdoc}
   */
  protected function needle(): string {
    return '<drupal-media';
  }

  /**
   * {@inheritdoc}
   */
  protected function xpath(): string {
    return '//drupal-media[@data-entity-type="media"][@data-entity-uuid]';
  }

  /**
   * {@inheritdoc}
   */
  protected function entityTypeId(\DOMElement $element): ?string {
    return 'media';
  }

}
