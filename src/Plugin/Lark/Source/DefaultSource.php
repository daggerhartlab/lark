<?php

declare(strict_types=1);

namespace Drupal\lark\Plugin\Lark\Source;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\lark\Plugin\Lark\LarkSourceInterface;
use Drupal\lark\Plugin\Lark\SourceBase;

/**
 * Default class used for lark_source plugins.
 *
 * @deprecated in lark:2.0.0 and is removed from lark:3.0.0. Use the
 *   lark_source config entity, \Drupal\lark\Entity\LarkSourceInterface,
 *   instead.
 */
class DefaultSource extends SourceBase implements LarkSourceInterface, ContainerFactoryPluginInterface {}
