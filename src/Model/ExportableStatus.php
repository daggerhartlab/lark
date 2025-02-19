<?php

namespace Drupal\lark\Model;

enum ExportableStatus {

  case InSync;
  case OutOfSync;
  case NotImported;
  case NotExported;

}
