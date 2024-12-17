<?php

declare(strict_types=1);

namespace Drupal\lark;

/**
 * Defines what to do if importing an entity that already exists (by UUID).
 *
 * @internal
 *   This API is experimental.
 */
enum EntityExisting {

  case Error;
  case Skip;
  case Update;

}
