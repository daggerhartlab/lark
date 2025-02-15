<?php

namespace Drupal\lark\Routing;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
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
    private AccountInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(AccountInterface::class),
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
    foreach ($entity_types as $entity_type) {
      // Only content entities.
      if (!($entity_type instanceof ContentEntityTypeInterface)) {
        continue;
      }

      $entity_type_id = $entity_type->id();

      // The edit-form template is used to extract and set additional parameters
      // dynamically. If there is no 'edit-form' template then still create the
      // link using 'entity_type_id/{entity_type_id}' as the link. This allows
      // lark info to be viewed for any entity, even if the url has to be typed manually.
      // @see https://gitlab.com/drupalspoons/devel/-/issues/377
      $link_template = $entity_type->getLinkTemplate('edit-form') ?: $entity_type_id . "/{{$entity_type_id}}";
      $this->setEntityTypeLinkTemplate($entity_type, $link_template, 'lark-load', "/lark/$entity_type_id");

      // Create a subtask.
      if ($entity_type->hasViewBuilderClass() && $entity_type->hasLinkTemplate('canonical')) {
        // We use canonical template to extract and set additional parameters
        // dynamically.
        $link_template = $entity_type->getLinkTemplate('canonical');
        $this->setEntityTypeLinkTemplate($entity_type, $link_template, 'lark-export', "/lark/export/$entity_type_id");
      }

      if ($entity_type->hasLinkTemplate('lark-load') || $entity_type->hasLinkTemplate('lark-export')) {
        // We use canonical template to extract and set additional parameters
        // dynamically.
        $link_template = $entity_type->getLinkTemplate('lark-load');
        if (empty($link_template)) {
          $link_template = $entity_type->getLinkTemplate('lark-export');
        }

        //$this->setEntityTypeLinkTemplate($entity_type, $link_template, 'lark-import', "/lark/import/$entity_type_id");
        $this->setEntityTypeLinkTemplate($entity_type, $link_template, 'lark-diff', "/lark/diff/$entity_type_id");
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
  protected function setEntityTypeLinkTemplate(EntityTypeInterface $entity_type, string $link_template, string $link_key, string $base_path) {
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

    $entity_type->setLinkTemplate($link_key, $base_path . $path_parts);
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
    if ($this->currentUser->hasPermission('lark import export entities')) {
      if ($entity->hasLinkTemplate('lark-load')) {
        $operations['lark'] = [
          'title' => $this->t('Lark'),
          'weight' => 100,
          'url' => $entity->toUrl('lark-load'),
        ];
      }
    }
    return $operations;
  }

}
