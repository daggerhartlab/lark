<?php

declare(strict_types=1);

namespace Drupal\lark\Plugin\Lark\Source;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\lark\Plugin\Lark\SourceBase;
use Drupal\lark\Plugin\Lark\SourceInterface;

/**
 * Default class used for lark_source plugins.
 */
class DefaultSource extends SourceBase implements SourceInterface, ContainerFactoryPluginInterface {}
