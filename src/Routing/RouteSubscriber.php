<?php

declare(strict_types=1);

namespace Drupal\lark\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Route subscriber.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * Constructs a RouteSubscriber object.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RouteProviderInterface $routeProvider,
  ) {}

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if ($route = $this->getEntityExportRoute($entity_type, 'lark-load')) {
        $collection->add("entity.$entity_type_id.lark_load", $route);
      }
      if ($route = $this->getEntityExportRoute($entity_type, 'lark-export')) {
        $collection->add("entity.$entity_type_id.lark_export", $route);
      }
      if ($route = $this->getEntityImportRoute($entity_type)) {
        $collection->add("entity.$entity_type_id.lark_import", $route);
      }
      if ($route = $this->getEntityDiffRoute($entity_type)) {
        $collection->add("entity.$entity_type_id.lark_diff", $route);
      }
      if ($route = $this->getEntityDownloadRoute($entity_type)) {
        $collection->add("entity.$entity_type_id.lark_download", $route);
      }
    }
  }

  /**
   * Gets the entity load route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getEntityExportRoute(EntityTypeInterface $entity_type, string $link_key): ?Route {
    if ($link = $entity_type->getLinkTemplate($link_key)) {
      $route = (new Route($link))
        ->addDefaults([
          '_form' => '\Drupal\lark\Form\EntityExportForm',
          '_title' => 'Lark Export',
        ])
        ->addRequirements([
          '_permission' => 'lark import export entities',
        ])
        ->setOption('_admin_route', TRUE)
        ->setOption('_lark_entity_type_id', $entity_type->id());

      // Set the parameters of the new route using the existing 'edit-form'
      // route parameters. If there are none then we need to set the basic
      // parameter [entity_type_id => [type => 'entity:entity_type_id']].
      // @see https://gitlab.com/drupalspoons/devel/-/issues/377
      $parameters = $this->getRouteParameters($entity_type, 'edit-form') ?: [$entity_type->id() => ['type' => 'entity:' . $entity_type->id()]];
      $route->setOption('parameters', $parameters);

      return $route;
    }
    return NULL;
  }

  /**
   * Gets the entity load route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getEntityImportRoute(EntityTypeInterface $entity_type): ?Route {
    if ($link = $entity_type->getLinkTemplate('lark-import')) {
      $route = (new Route($link))
        ->addDefaults([
          '_form' => '\Drupal\lark\Form\EntityImportForm',
          '_title' => 'Lark Import',
        ])
        ->addRequirements([
          '_permission' => 'lark import export entities',
        ])
        ->setOption('_admin_route', TRUE)
        ->setOption('_lark_entity_type_id', $entity_type->id());

      // Set the parameters of the new route using the existing 'edit-form'
      // route parameters. If there are none then we need to set the basic
      // parameter [entity_type_id => [type => 'entity:entity_type_id']].
      // @see https://gitlab.com/drupalspoons/devel/-/issues/377
      $parameters = $this->getRouteParameters($entity_type, 'edit-form') ?: [$entity_type->id() => ['type' => 'entity:' . $entity_type->id()]];
      $route->setOption('parameters', $parameters);

      return $route;
    }
    return NULL;
  }

  /**
   * Gets the entity load route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getEntityDiffRoute(EntityTypeInterface $entity_type): ?Route {
    if ($link = $entity_type->getLinkTemplate('lark-diff')) {
      $route = (new Route($link))
        ->addDefaults([
          '_title' => 'Lark Diff',
          '_controller'  => '\Drupal\lark\Controller\DiffViewer::build'
        ])
        ->addRequirements([
          '_permission' => 'lark view diffs',
        ])
        ->setOption('_admin_route', TRUE)
        ->setOption('_lark_entity_type_id', $entity_type->id());

      // Set the parameters of the new route using the existing 'edit-form'
      // route parameters. If there are none then we need to set the basic
      // parameter [entity_type_id => [type => 'entity:entity_type_id']].
      // @see https://gitlab.com/drupalspoons/devel/-/issues/377
      $parameters = $this->getRouteParameters($entity_type, 'edit-form') ?: [$entity_type->id() => ['type' => 'entity:' . $entity_type->id()]];
      $route->setOption('parameters', $parameters);

      return $route;
    }
    return NULL;
  }

  /**
   * Gets the entity load route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getEntityDownloadRoute(EntityTypeInterface $entity_type): ?Route {
    if ($link = $entity_type->getLinkTemplate('lark-download')) {
      $route = (new Route($link))
        ->addDefaults([
          '_title' => 'Lark Download',
          '_form'  => '\Drupal\lark\Form\EntityDownloadForm'
        ])
        ->addRequirements([
          '_permission' => 'lark view diffs',
        ])
        ->setOption('_admin_route', TRUE)
        ->setOption('_lark_entity_type_id', $entity_type->id());

      // Set the parameters of the new route using the existing 'edit-form'
      // route parameters. If there are none then we need to set the basic
      // parameter [entity_type_id => [type => 'entity:entity_type_id']].
      // @see https://gitlab.com/drupalspoons/devel/-/issues/377
      $parameters = $this->getRouteParameters($entity_type, 'edit-form') ?: [$entity_type->id() => ['type' => 'entity:' . $entity_type->id()]];
      $route->setOption('parameters', $parameters);

      return $route;
    }
    return NULL;
  }

  /**
   * Gets the route parameters from the template.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param string $link_template
   *   The link template.
   *
   * @return array[]
   *   A list of route of parameters.
   */
  protected function getRouteParameters(EntityTypeInterface $entity_type, string $link_template): array {
    $parameters = [];
    if (!$path = $entity_type->getLinkTemplate($link_template)) {
      return $parameters;
    }

    $original_route_parameters = [];
    $candidate_routes = $this->routeProvider->getRoutesByPattern($path);
    if ($candidate_routes->count()) {
      // Guess the best match. There could be more than one route sharing the
      // same path. Try first an educated guess based on the route name. If we
      // can't find one, pick-up the first from the list.
      $name = 'entity.' . $entity_type->id() . '.' . str_replace('-', '_', $link_template);
      if (!$original_route = $candidate_routes->get($name)) {
        $iterator = $candidate_routes->getIterator();
        $iterator->rewind();
        $original_route = $iterator->current();
      }
      $original_route_parameters = $original_route->getOption('parameters') ?? [];
    }

    if (preg_match_all('/{\w*}/', $path, $matches)) {
      foreach ($matches[0] as $match) {
        $match = str_replace(['{', '}'], '', $match);
        // This match has an original route parameter definition.
        if (isset($original_route_parameters[$match])) {
          $parameters[$match] = $original_route_parameters[$match];
        }
        // It could be an entity type?
        elseif ($this->entityTypeManager->hasDefinition($match)) {
          $parameters[$match] = ['type' => "entity:$match"];
        }
      }
    }

    return $parameters;
  }

}
