<?php

namespace Drupal\lark\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class LarkOverview extends ControllerBase {

  public function __construct(
    protected ExtensionPathResolver $extensionPathResolver,
  ) {}

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get(ExtensionPathResolver::class)
    );
  }

  /**
   * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
   * @throws \League\CommonMark\Exception\CommonMarkException
   */
  public function build() {
    $readme = _lark_readme_to_html();
    if ($readme) {
      return [
        '#markup' => $readme,
      ];
    }

    return new RedirectResponse(Url::fromRoute('lark.settings')->toString());
  }

}
