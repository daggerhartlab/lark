<?php

namespace Drupal\lark\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\lark\Service\Render\ReadmeRenderer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class LarkOverview extends ControllerBase {

  public function __construct(
    protected ReadmeRenderer $readmeRenderer,
  ) {}

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get(ReadmeRenderer::class)
    );
  }

  /**
   * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
   * @throws \League\CommonMark\Exception\CommonMarkException
   */
  public function build() {
    $readme = $this->readmeRenderer->toHtml();
    if ($readme) {
      return [
        '#markup' => $readme,
      ];
    }

    return new RedirectResponse(Url::fromRoute('lark.settings')->toString());
  }

}
