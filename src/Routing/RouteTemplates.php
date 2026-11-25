<?php

namespace Drupal\lark\Routing;

use Drupal\Component\Serialization\Yaml;

/**
 * Builds lark route and link definitions from routeTemplates.yml.
 */
class RouteTemplates {

  /**
   * Gets the route templates for an entity type.
   *
   * @param string $entity_type_id
   *   The entity type id.
   *
   * @return array[]
   *   Route template definitions keyed by instance name.
   */
  public static function getRouteTemplates(string $entity_type_id): array {
    $yaml = Yaml::decode(file_get_contents(__DIR__ . '/routeTemplates.yml'));
    // The template for our templates.
    $template = $yaml['template'];
    $instances = $yaml['instances'];
    $routes = [];
    foreach ($instances as $name => $instance) {
      $route = array_replace_recursive($template, $instance);

      array_walk_recursive($route, function (&$value, $key) use ($entity_type_id, $name) {
        if (is_string($value)) {
          $value = strtr($value, [
            '__ENTITY_TYPE_ID__' => $entity_type_id,
            '__NAME__' => $name,
            '--NAME--' => str_replace('_', '-', $name),
          ]);
        }
      });

      $routes[$name] = $route;
    }

    return $routes;
  }

}
