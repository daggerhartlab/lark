<?php

declare(strict_types=1);

namespace Drupal\lark_transaction\Attribute;

use Drupal\Component\Plugin\Attribute\AttributeBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The lark transaction attribute.
 *
 * @deprecated
 *   Remove in v2.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class LarkTransaction extends AttributeBase {

  /**
   * Constructs a new LarkTransaction instance.
   *
   * @param string $id
   *   The plugin ID. There are some implementation bugs that make the plugin
   *   available only if the ID follows a specific pattern. It must be either
   *   identical to group or prefixed with the group. E.g. if the group is "foo"
   *   the ID must be either "foo" or "foo:bar".
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   (optional) The human-readable name of the plugin.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   (optional) A brief description of the plugin.
   * @param int|null $weight
   *  (optional) The weight of the plugin.
   * @param bool|null $enabled
   *  (optional) Whether the plugin is enabled.
   * @param bool|null $repeatable
   *  (optional) Whether the plugin is repeatable.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly ?int $weight = 0,
    public readonly ?bool $enabled = TRUE,
    public readonly ?bool $repeatable = FALSE,
  ) {}

}
