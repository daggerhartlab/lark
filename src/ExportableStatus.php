<?php

namespace Drupal\lark;

enum ExportableStatus {

  case InSync;
  case OutOfSync;
  case NotImported;
  case NotExported;

}
