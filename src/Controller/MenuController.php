<?php

declare(strict_types=1);

namespace Drupal\lark\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Negotiates which menu Lark sub-tab a user lands on.
 *
 * @phpstan-consistent-constructor
 */
class MenuController extends ControllerBase {

  /**
   * Constructs a MenuController.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   */
  public function __construct(
    protected RequestStack $requestStack,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
    );
  }

  /**
   * Redirect to the sub-tab this user has permission for.
   *
   * Mirrors EntityController::larkLoad for menus.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect.
   */
  public function larkLoad(RouteMatchInterface $routeMatch): RedirectResponse {
    /** @var \Drupal\system\MenuInterface $menu */
    $menu = $routeMatch->getParameter('menu');

    // Remove the destination query parameter, otherwise the user is sent to
    // the destination instead of the tab they asked for.
    $this->requestStack->getCurrentRequest()->query->remove('destination');

    $route = $this->currentUser()->hasPermission('lark export entity')
      ? 'lark.menu_export'
      : 'lark.menu_import';

    return new RedirectResponse(
      Url::fromRoute($route, ['menu' => $menu->id()])->toString()
    );
  }

}
