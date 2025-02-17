<?php

declare(strict_types=1);

namespace Drupal\lark\Plugin\Lark\Source;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\lark\Plugin\Lark\LarkSourceInterface;
use Drupal\lark\Plugin\Lark\SourceBase;

/**
 * Default class used for lark_source plugins.
 *
 * @deprecated
 *   Replaced by lark_source config entity.
 */
class DefaultSource extends SourceBase implements LarkSourceInterface, ContainerFactoryPluginInterface {}
