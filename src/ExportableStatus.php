<?php

namespace Drupal\lark;

enum ExportableStatus {

  case InSync;
  case OutOfSync;
  case NotExported;
  case NotImported;

}
