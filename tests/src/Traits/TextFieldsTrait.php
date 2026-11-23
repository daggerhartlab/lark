<?php

declare(strict_types=1);

namespace Drupal\Tests\lark\Traits;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Adds one field of each formatted text type to a bundle.
 */
trait TextFieldsTrait {

  /**
   * Create field_text, field_text_long and field_text_summary on a bundle.
   *
   * @param string $entity_type_id
   *   Entity type id.
   * @param string $bundle
   *   Bundle.
   */
  protected function createTextFields(string $entity_type_id, string $bundle): void {
    $fields = [
      'field_text' => 'text',
      'field_text_long' => 'text_long',
      'field_text_summary' => 'text_with_summary',
    ];

    foreach ($fields as $field_name => $field_type) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => $entity_type_id,
        'type' => $field_type,
        'cardinality' => -1,
      ])->save();
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => $entity_type_id,
        'bundle' => $bundle,
        'label' => $field_name,
      ])->save();
    }
  }

}
