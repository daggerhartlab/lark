<?php

declare(strict_types=1);

namespace Drupal\lark\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\lark\Service\Utility\EntityUtility;
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
    protected EntityUtility $entityUtility,
    protected RouteProviderInterface $routeProvider,
  ) {}

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if (!$entity_type->get(EntityTypeInfo::IS_EXPORTABLE)) {
        continue;
      }

      $template_instances = RouteTemplates::getRouteTemplates($entity_type_id);
      foreach ($template_instances as $instance) {
        $link = $entity_type->getLinkTemplate($instance['link']['key']);
        // Set the parameters of the new route using the existing 'delete-form'
        // route parameters. If there are none then we need to set the basic
        // parameter [entity_type_id => [type => 'entity:entity_type_id']].
        // @see https://gitlab.com/drupalspoons/devel/-/issues/377
        $parameters = $this->getRouteParameters($entity_type, 'delete-form') ?: [$entity_type->id() => ['type' => 'entity:' . $entity_type->id()]];

        $route = (new Route($link))
          ->addDefaults($instance['route']['defaults'])
          ->addRequirements($instance['route']['requirements'])
          ->addOptions($instance['route']['options'])
          ->setOption('parameters', $parameters);

        $collection->add($instance['route']['name'], $route);
      }
    }
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
