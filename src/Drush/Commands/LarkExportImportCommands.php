<?php

namespace Drupal\lark\Drush\Commands;

use Drupal\lark\Service\ExporterInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drupal\lark\Service\ImporterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lark entity export and import commands.
 */
class LarkExportImportCommands extends DrushCommands {

  /**
   * Constructs a LarkExportImportCommands object.
   */
  public function __construct(
    protected ExporterInterface $entityExporter,
    protected ImporterInterface $entityImporter,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get(ExporterInterface::class),
      $container->get(ImporterInterface::class),
    );
  }

  /**
   * Import all entities exported by Lark.
   */
  #[CLI\Command(name: 'lark:import-all-entities', aliases: ['limpall'])]
  #[CLI\Usage(name: 'lark:import-all-entities', description: 'Import all entities exported by Lark.')]
  public function importAll(): void {
    $this->entityImporter->importSourcesAll(FALSE);
    $this->logger()->success(dt('Import complete.'));
  }

  /**
   * Import a single entity with its dependencies.
   *
   * @param string $uuid
   *   Entity UUID to import.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  #[CLI\Command(name: 'lark:import-entity', aliases: ['limpe'])]
  #[CLI\Argument(name: 'source_id', description: 'Source id.')]
  #[CLI\Argument(name: 'uuid', description: 'Entity UUID.')]
  #[CLI\Usage(name: 'lark:import-entity source_id entity_uuid', description: 'Import a single entity with its dependencies.')]
  public function importEntity(string $source_id, string $uuid): void {
    $this->entityImporter->importSourceEntity($source_id, $uuid, FALSE);
    $this->logger()->success(dt("Import of {$uuid} from {$source_id} complete."));
  }

  /**
   * Import all entities within a given source.
   *
   * @param string $source_id
   *   Source id.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  #[CLI\Command(name: 'lark:import-source', aliases: ['limpe'])]
  #[CLI\Argument(name: 'source_id', description: 'Source id.')]
  #[CLI\Usage(name: 'lark:import-source source_id', description: 'Import all entities within a given source.')]
  public function importSource(string $source_id): void {
    $this->entityImporter->importSource($source_id, FALSE);
    $this->logger()->success(dt("Import from {$source_id} complete."));
  }

  /**
   * Export a single entity and its dependencies.
   *
   * @param string $source_id
   *   Source id.
   * @param string $entity_type
   *   Entity type id.
   * @param string|int $entity_id
   *   Entity id.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\lark\Exception\LarkEntityNotFoundException
   */
  #[CLI\Command(name: 'lark:export-entity', aliases: ['lexpe'])]
  #[CLI\Argument(name: 'source_id', description: 'Source id.')]
  #[CLI\Argument(name: 'entity_type', description: 'Entity type id.')]
  #[CLI\Argument(name: 'entity_id', description: 'Entity id.')]
  #[CLI\Usage(name: 'lark:export-entity source_id node 123', description: 'Export a single entity with its dependencies.')]
  public function exportEntity(string $source_id, string $entity_type, int|string $entity_id): void {
    $this->entityExporter->exportEntity($source_id, $entity_type, (int) $entity_id, FALSE);
    $this->logger()->success(dt("Export {$entity_type} {$entity_id} to source {$source_id} complete."));
  }

}
