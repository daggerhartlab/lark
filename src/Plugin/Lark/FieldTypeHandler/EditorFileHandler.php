<?php

declare(strict_types=1);

namespace Drupal\lark\Plugin\Lark\FieldTypeHandler;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\lark\Attribute\LarkFieldTypeHandler;
use Drupal\lark\Plugin\Lark\TextEmbedHandlerBase;

/**
 * Records files uploaded through the text editor as export dependencies.
 *
 * Markup: <img data-entity-type="file" data-entity-uuid="…">. Matches any
 * element with those attributes, as core's editor file usage tracking does.
 */
#[LarkFieldTypeHandler(
  id: 'editor_file_handler',
  label: new TranslatableMarkup('Editor File Handler'),
  description: new TranslatableMarkup('Records files uploaded through the text editor as export dependencies.'),
  fieldTypes: ['text', 'text_long', 'text_with_summary'],
)]
class EditorFileHandler extends TextEmbedHandlerBase {

  /**
   * {@inheritdoc}
   */
  protected function needle(): string {
    return 'data-entity-type="file"';
  }

  /**
   * {@inheritdoc}
   */
  protected function xpath(): string {
    return '//*[@data-entity-type="file"][@data-entity-uuid]';
  }

  /**
   * {@inheritdoc}
   */
  protected function entityTypeId(\DOMElement $element): ?string {
    return 'file';
  }

}
