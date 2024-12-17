<?php

declare(strict_types=1);

namespace Drupal\lark\Plugin\Lark\FieldTypeHandler;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\lark\Attribute\LarkFieldTypeHandler;
use Drupal\lark\Plugin\Lark\FieldTypeHandlerBase;

/**
 * Plugin implementation of the lark_field_type_handler.
 */
#[LarkFieldTypeHandler(
  id: 'link_handler',
  label: new TranslatableMarkup('Link Handler'),
  description: new TranslatableMarkup('Handles link fields.'),
  fieldTypes: ['link'],
)]
class LinkHandler extends FieldTypeHandlerBase {

  /**
   * {@inheritdoc}
   */
  public function alterImportValue(array $values, FieldItemListInterface $field): array {
    foreach ($values as $delta => $item_value) {
      // Handle reference fields for uuids.
      if (isset($item_value['target_uuid'])) {
        // Update the URI based on the target UUID for link fields.
        $values[$delta]['uri'] = 'entity:' . $field->getEntity()->getEntityTypeId() . '/' . $item_value['target_uuid'];
      }
    }

    return $values;
  }

}
