<?php

namespace Drupal\lark\Service;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\lark\Model\ExportableInterface;
use Drupal\lark\Entity\LarkSourceInterface;
use Drupal\lark\Model\ExportArray;

/**
 * Factory for creating Exportable instances from various sources.
 */
interface ExportableFactoryInterface {

  /**
   * Create exportable from entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity.
   *
   * @return \Drupal\lark\Model\ExportableInterface
   *   Exportable entity.
   */
  public function createFromEntity(ContentEntityInterface $entity): ExportableInterface;

  /**
   * Create an exportables collection for the given entity and its dependencies.
   *
   * @param string $entity_type_id
   *   Entity type id.
   * @param int $entity_id
   *   Entity id.
   * @param \Drupal\lark\Entity\LarkSourceInterface|null $source
   *   Source to prepare this exportable for if known.
   * @param array $meta_option_overrides
   *   Override values for exportable options, keyed by UUID.
   *
   * @return \Drupal\lark\Model\ExportableInterface[]
   *   Exportables collection.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createFromEntityWithDependencies(string $entity_type_id, int $entity_id, ?LarkSourceInterface $source = NULL, array $meta_option_overrides = []): array;

  /**
   * Create an exportable instance from the source export.
   *
   * @param string $source_id
   *   Source id.
   * @param string $uuid
   *   Export entity uuid.
   *
   * @return \Drupal\lark\Model\ExportableInterface|null
   *   Exportable. Null if not found in source.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function createFromSource(string $source_id, string $uuid): ?ExportableInterface;

  /**
   * Create exportables collection from the UUID's yaml in the source.
   *
   * @param string $source_id
   *   Source id.
   * @param string $root_uuid
   *   Root entity uuid.
   *
   * @return \Drupal\lark\Model\ExportableInterface[]
   *   Exportables collection.
   */
  public function createFromSourceWithDependencies(string $source_id, string $root_uuid): array;

  /**
   * Create an exportable from an export array.
   *
   * @param \Drupal\lark\Model\ExportArray $export
   *   Export array.
   * @param \Drupal\lark\Entity\LarkSourceInterface|null $source
   *   Source to prepare this exportable for if known.
   *
   * @return \Drupal\lark\Model\ExportableInterface
   *   Exportable entity.
   */
  public function createFromExportArray(ExportArray $export, ?LarkSourceInterface $source = NULL): ExportableInterface;

  /**
   * Create an exportable from only a uuid.
   *
   * Potentially expensive operation when we don't have any other data.
   *
   * @param string $uuid
   *   Uuid for a content entity of an unknown type.
   *
   * @return \Drupal\lark\Model\ExportableInterface
   *   Exportable created from found entity/export.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function createFromUuid(string $uuid): ExportableInterface;

}
