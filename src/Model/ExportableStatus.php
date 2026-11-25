<?php

namespace Drupal\lark\Model;

/**
 * Describes how an entity compares to its export.
 */
enum ExportableStatus {

  case InSync;
  case OutOfSync;
  case NotImported;
  case NotExported;

}
