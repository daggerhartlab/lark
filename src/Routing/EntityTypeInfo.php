<?php

namespace Drupal\lark\Routing;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\lark\Entity\LarkSourceInterface;
use Drupal\lark\Service\Utility\EntityUtility;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EntityTypeInfo implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * EntityTypeInfo constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   Current user.
   */
  public function __construct(
    protected AccountInterface $currentUser,
    protected EntityUtility $entityUtility,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(AccountInterface::class),
      $container->get(EntityUtility::class),
    );
  }

  /**
   * Adds lark links to appropriate entity types.
   *
   * This is an alter hook bridge.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface[] &$entity_types
   *   The master entity type list to alter.
   *
   * @see hook_entity_type_alter()
   */
  public function entityTypeAlter(array &$entity_types): array {
    foreach ($entity_types as $entity_type_id => $entity_type) {
      // Only content entities.
      if (!($entity_type instanceof ContentEntityTypeInterface)) {
        $entity_type->set('_lark_exportable', FALSE);
        continue;
      }

      // Never export/import Users.
      if ($entity_type_id === 'user') {
        $entity_type->set('_lark_exportable', FALSE);
        continue;
      }

      $entity_type->set('_lark_exportable', TRUE);

      // Add our Lark links to each exportable content type.
      // The delete-form template is used to extract and set additional params
      // dynamically. If there is no 'delete-form' template still create the
      // link using 'entity_type_id/{entity_type_id}' as the link. This allows
      // lark info to be viewed for any entity, even if the url has to be typed manually.
      // @see https://gitlab.com/drupalspoons/devel/-/issues/377
      $link_template = $entity_type->getLinkTemplate('edit-form') ?: $entity_type_id . "/{{$entity_type_id}}";
      $template_instances = RouteTemplates::getRouteTemplates($entity_type_id);
      $parent = array_shift($template_instances);

      $key = $parent['link']['key'];
      $path = $parent['route']['path'] . $this->getTemplatePath($link_template);
      $entity_type->setLinkTemplate($key, $path);

      // Create subtasks.
      if ($entity_type->hasLinkTemplate($parent['link']['key'])) {
        // We use canonical template to extract and set additional parameters
        // dynamically.
        $link_template = $entity_type->getLinkTemplate($parent['link']['key']);
        foreach ($template_instances as $instance) {
          $key = $instance['link']['key'];
          $path = $instance['route']['path'] . $this->getTemplatePath($link_template);
          $entity_type->setLinkTemplate($key, $path);
        }
      }
    }

    return $entity_types;
  }

  /**
   * Sets entity type link template.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   Entity type.
   * @param string $link_template
   *   Entity link.
   * @param string $link_key
   *   Link key.
   * @param string $base_path
   *   Base path for link key.
   */
  protected function getTemplatePath(string $link_template) {
    // Extract all route parameters from the given template and set them to
    // the current template.
    // Some entity templates can contain not only entity id,
    // for example /user/{user}/documents/{document}
    // /group/{group}/content/{group_content}
    // We use canonical or edit-form templates to get these parameters and set
    // them for lark entity link templates.
    $path_parts = '';
    if (preg_match_all('/{\w*}/', $link_template, $matches)) {
      foreach ($matches[0] as $match) {
        $path_parts .= "/$match";
      }
    }

    return $path_parts;
  }

  /**
   * Adds lark operations on entity that supports it.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity on which to define an operation.
   *
   * @return array
   *   An array of operation definitions.
   *
   * @see hook_entity_operation()
   */
  public function entityOperation(EntityInterface $entity): array {
    $operations = [];
    if (
      $this->currentUser->hasPermission('lark export entity') ||
      $this->currentUser->hasPermission('lark import entity')
    ) {
      $template_instances = RouteTemplates::getRouteTemplates($entity->getEntityTypeId());
      $parent = array_shift($template_instances);
      if ($entity->hasLinkTemplate($parent['link']['key'])) {
        $operations['lark'] = [
          'title' => $this->t('@lark_link_label', [
            '@lark_link_label' => $parent['link']['label'],
          ]),
          'weight' => 100,
          'url' => $entity->toUrl($parent['link']['key']),
        ];
      }
    }

    if ($entity instanceof LarkSourceInterface && $entity->hasLinkTemplate('canonical')) {
      $operations['view'] = [
        'title' => $this->t('View'),
        'weight' => -10,
        'url' => $entity->toUrl('canonical')->setRouteParameter('lark_source', $entity->id())
      ];
    }

    return $operations;
  }

}
