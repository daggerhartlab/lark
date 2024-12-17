<?php

namespace Drupal\lark\Service;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\lark\Model\ExportableInterface;
use Drupal\lark\Plugin\Lark\SourceInterface;

/**
 * Factory for creating exportable entities.
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
   * Factory for creating from yaml export file.
   *
   * @param string $source_plugin_id
   *   Source plugin id.
   * @param string $uuid
   *   Export entity uuid.
   *
   * @return \Drupal\lark\Model\ExportableInterface
   *   Exportable.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function createFromSource(string $source_plugin_id, string $uuid): ExportableInterface;

  /**
   * Factory for creating exportables with dependencies.
   *
   * @param string $source_plugin_id
   *   Source plugin id.
   * @param string $root_uuid
   *   Root entity uuid.
   *
   * @return \Drupal\lark\Model\ExportableInterface[]
   */
  public function createFromSourceWithDependencies(string $source_plugin_id, string $root_uuid): array;

  /**
   * Get the entity and prepare it for export.
   *
   * @param string $entity_type_id
   *   Entity type id.
   * @param int $entity_id
   *   Entity id.
   * @param array $exportables
   *   Exportable entity models.
   *
   * @return \Drupal\lark\Model\ExportableInterface[]
   *   Exportable entity models.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\lark\Exception\LarkEntityNotFoundException
   */
  public function getEntityExportables(string $entity_type_id, int $entity_id, array &$exportables = []): array;

}
