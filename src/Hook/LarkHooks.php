<?php

declare(strict_types=1);

namespace Drupal\lark\Hook;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\lark\Service\Render\ReadmeRenderer;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Hook implementations for Lark.
 */
class LarkHooks {

  /**
   * Archives that Lark writes to the temporary directory for download.
   */
  protected const array DOWNLOAD_TARGETS = ['lark-export.tar.gz', 'lark-source.tar.gz'];

  public function __construct(
    protected ReadmeRenderer $readmeRenderer,
    protected AccountInterface $currentUser,
    protected RequestStack $requestStack,
    protected TimeInterface $time,
  ) {}

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): MarkupInterface|string|null {
    if ($route_name === 'help.page.lark') {
      return $this->readmeRenderer->toHtml();
    }
    return NULL;
  }

  /**
   * Implements hook_file_download().
   */
  #[Hook('file_download')]
  public function fileDownload(string $uri): array|int|null {
    $target = StreamWrapperManager::getTarget($uri);
    if (StreamWrapperManager::getScheme($uri) !== 'temporary' || !in_array($target, static::DOWNLOAD_TARGETS, TRUE)) {
      return NULL;
    }

    if (!$this->currentUser->hasPermission('lark export entity')) {
      return -1;
    }

    $date = gmdate('YmdHi', $this->time->getRequestTime());
    $hostname = str_replace('.', '-', $this->requestStack->getCurrentRequest()->getHttpHost());
    $prefix = str_replace('.tar.gz', '', $target);
    return [
      'Content-disposition' => "attachment; filename=\"{$prefix}--{$hostname}--{$date}.tar.gz\"",
    ];
  }

}
