<?php

declare(strict_types=1);

namespace Drupal\lark\Service\Render;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Render\Markup;
use League\CommonMark\GithubFlavoredMarkdownConverter;

/**
 * Renders Lark's README as HTML.
 */
class ReadmeRenderer {

  public function __construct(
    protected ExtensionPathResolver $extensionPathResolver,
  ) {}

  /**
   * Converts the README to HTML, without its title.
   *
   * @return \Drupal\Component\Render\MarkupInterface|string
   *   The README as HTML, or an empty string if the file is missing.
   */
  public function toHtml(): MarkupInterface|string {
    $readme_path = $this->extensionPathResolver->getPath('module', 'lark') . '/README.md';
    if (!file_exists($readme_path)) {
      return '';
    }

    // Drop the title to avoid a second H1 on the page.
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
