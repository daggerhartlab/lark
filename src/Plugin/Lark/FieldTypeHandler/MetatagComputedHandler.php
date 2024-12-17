<?php

namespace Drupal\lark\Plugin\Lark\FieldTypeHandler;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\lark\Attribute\LarkFieldTypeHandler;
use Drupal\lark\Plugin\Lark\FieldTypeHandlerBase;
use Drupal\metatag\MetatagManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the lark_field_type_handler.
 */
#[LarkFieldTypeHandler(
  id: 'metatag_computed_handler',
  label: new TranslatableMarkup('Metatag Computed Handler'),
  description: new TranslatableMarkup('Exports non-default unprocessed values.'),
  fieldTypes: ['metatag_computed'],
)]
class MetatagComputedHandler extends FieldTypeHandlerBase {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entityTypeManager,
    EntityRepositoryInterface $entityRepository,
    protected MetatagManagerInterface $metatagManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entityTypeManager, $entityRepository);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity.repository'),
      $container->get('metatag.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function alterExportValue(array $values, ContentEntityInterface $entity, FieldItemListInterface $field): array {
    $metatag_configured = $this->entityTypeManager->getStorage('metatag_defaults')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('id', [
        $field->getEntity()->getEntityTypeId(),
        "{$field->getEntity()->getEntityTypeId()}__{$field->getEntity()->bundle()}",
        ], 'IN')
      ->execute();

    if ($metatag_configured) {
      return [
        0 => $this->metatagManager->tagsFromEntity($field->getEntity()),
      ];
    }

    return [];
  }

}
