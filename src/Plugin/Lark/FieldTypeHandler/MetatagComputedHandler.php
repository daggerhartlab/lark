<?php

namespace Drupal\lark\Plugin\Lark\FieldTypeHandler;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\lark\Attribute\LarkFieldTypeHandler;
use Drupal\lark\Plugin\Lark\FieldTypeHandlerBase;

/**
 * Plugin implementation of the lark_field_type_handler.
 */
#[LarkFieldTypeHandler(
  id: 'metatag_computed_handler',
  label: new TranslatableMarkup('Metatag Computed Handler'),
  description: new TranslatableMarkup('Exports non-default unprocessed values.'),
  fieldTypes: ['metatag_computed'],
)]
class MetatagComputedHandler extends FieldTypeHandlerBase {

  /**
   * {@inheritdoc}
   */
  public function alterExportValue(array $values, ContentEntityInterface $entity, FieldItemListInterface $field): array {
    $metatag_configured = $this->entityTypeManager->getStorage('metatag_defaults')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('id', [
        $field->getEntity()->getEntityTypeId(),
        "{$field->getEntity()->getEntityTypeId()}__{$field->getEntity()->bundle()}",
        ], 'IN')
      ->execute();

    /** @var \Drupal\metatag\MetatagManagerInterface $metatagManager */
    $metatagManager = \Drupal::service('metatag.manager');
    if ($metatag_configured) {
      return [
        0 => $metatagManager->tagsFromEntity($field->getEntity()),
      ];
    }

    return [];
  }

}
