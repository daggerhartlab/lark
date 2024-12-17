<?php

declare(strict_types=1);

namespace Drupal\lark\Plugin\Lark\FieldTypeHandler;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\lark\Attribute\LarkFieldTypeHandler;

/**
 * Plugin implementation of the lark_field_type_handler.
 */
#[LarkFieldTypeHandler(
  id: 'file_uuid_handler',
  label: new TranslatableMarkup('File & Image Field Type Handler'),
  description: new TranslatableMarkup('Handles basic file and image field types based on their UUIDs.'),
  fieldTypes: ['file', 'image'],
)]
class FileUuidHandler extends EntityReferenceUuidHandler {

}
