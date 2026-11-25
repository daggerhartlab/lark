<?php

declare(strict_types=1);

namespace Drupal\lark\Hook;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Markup;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use League\CommonMark\GithubFlavoredMarkdownConverter;
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
    protected ExtensionPathResolver $extensionPathResolver,
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
      return $this->readmeToHtml();
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

  /**
   * Converts the README to HTML, without its title.
   */
  protected function readmeToHtml(): MarkupInterface|string {
    $readme_path = $this->extensionPathResolver->getPath('module', 'lark') . '/README.md';
    if (!file_exists($readme_path)) {
      return '';
    }

    // Drop the title to avoid a second H1 on the help page.
    $lines = explode("\n", file_get_contents($readme_path));
    array_shift($lines);
    $content = implode("\n", $lines);

    $converter = new GithubFlavoredMarkdownConverter([
      'html_input' => 'strip',
      'allow_unsafe_links' => FALSE,
    ]);
    return Markup::create((string) $converter->convert($content));
  }

}
