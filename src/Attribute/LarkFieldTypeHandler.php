<?php

declare(strict_types=1);

namespace Drupal\lark\Attribute;

use Drupal\Component\Plugin\Attribute\AttributeBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The lark_field_type_handler attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class LarkFieldTypeHandler extends AttributeBase {

  /**
   * Constructs a new LarkFieldHandler instance.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   (optional) The human-readable name of the plugin.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   (optional) A brief description of the plugin.
   * @param array $fieldTypes
   *   (optional) An array of field types handled by the plugin.
   * @param int $weight
   *   (optional) The weight of the plugin.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly array $fieldTypes = [],
    public readonly int $weight = 0,
    public readonly ?string $deriver = NULL,
  ) {}

}
