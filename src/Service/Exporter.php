<?php

declare(strict_types=1);

namespace Drupal\lark\Service;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\lark\Model\ExportableInterface;
use Drupal\lark\Service\LarkSourceManager;

/**
 * Export entities and their dependencies to yaml.
 */
class Exporter implements ExporterInterface {

  use StringTranslationTrait;

  /**
   * EntityExporter constructor.
   *
   * @param \Drupal\lark\Service\ExportableFactoryInterface $exportableFactory
   *   The lark exportable factory service.
   * @param \Drupal\lark\Service\MetaOptionManager $metaOptionManager
   *   Meta options manager.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(
    protected ExportableFactoryInterface $exportableFactory,
    protected LoggerChannelInterface $logger,
    protected MetaOptionManager $metaOptionManager,
    protected MessengerInterface $messenger,
    protected LarkSourceManager $sourceManager,
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function exportEntity(string $source_id, string $entity_type_id, int $entity_id, bool $show_messages = TRUE, array $meta_options_overrides = []): void {
    $source = $this->sourceManager->load($source_id);
    $exportables = $this->exportableFactory->createFromEntityWithDependencies($entity_type_id, $entity_id, $source, $meta_options_overrides);
    $this->exportExportables($exportables, $show_messages);
  }

  /**
   * {@inheritdoc}
   */
  public function exportEntities(string $source_id, array $entities, bool $show_messages = TRUE, array $meta_options_overrides = []): void {
    if (!$entities) {
      return;
    }

    $source = $this->sourceManager->load($source_id);
    $exportables = $this->exportableFactory->createFromEntitiesWithDependencies($entities, $source, $meta_options_overrides);
    $this->exportExportables($exportables, $show_messages, TRUE, $source->label());
  }

  /**
   * Write a prepared set of exportables to their source.
   *
   * @param \Drupal\lark\Model\ExportableInterface[] $exportables
   *   Exportables to write, in dependency order.
   * @param bool $show_messages
   *   Whether to show messages.
   * @param bool $bulk
   *   Whether this is a bulk export (multiple starting entities, such as a
   *   menu's links). When TRUE, per-entity messenger output is replaced with
   *   a single summary message and per-entity logging drops to debug level,
   *   so exporting hundreds of entities from one button press does not flood
   *   the messages area and watchdog. Single-entity exports keep their
   *   existing per-entity messenger and logger output.
   * @param string|null $source_label
   *   The source label to use in the bulk summary message.
   */
  protected function exportExportables(array $exportables, bool $show_messages, bool $bulk = FALSE, ?string $source_label = NULL): void {
    $exported_count = 0;
    $failures = [];

    foreach ($exportables as $exportable) {
      // Allow modules to veto exporting this entity.
      $results = $this->moduleHandler->invokeAll('lark_should_export_entity', [$exportable->entity()]);
      if (in_array(FALSE, $results, TRUE)) {
        continue;
      }

      // Allow meta option plugins to perform last minute changes or actions.
      foreach ($this->metaOptionManager->getInstances() as $meta_option) {
        if ($meta_option->applies($exportable->entity())) {
          $meta_option->preExportWrite($exportable);
        }
      }

      if ($this->writeToYaml($exportable)) {
        $message = $this->t('Exported @entity_type_id : @entity_id : @label', [
          '@entity_type_id' => $exportable->entity()->getEntityTypeId(),
          '@entity_id' => $exportable->entity()->id(),
          '@label' => $exportable->entity()->label(),
        ]);

        $exported_count++;
        if ($bulk) {
          $this->logger->debug($message);
        }
        else {
          if ($show_messages) {
            $this->messenger->addStatus($message);
          }
          $this->logger->notice($message);
        }
      }
      else {
        $message = $this->t('Failed to export @entity_type_id : @entity_id : @label', [
          '@entity_type_id' => $exportable->entity()->getEntityTypeId(),
          '@entity_id' => $exportable->entity()->id(),
          '@label' => $exportable->entity()->label(),
        ]);

        if ($bulk) {
          $failures[] = $message;
          $this->logger->debug($message);
        }
        else {
          if ($show_messages) {
            $this->messenger->addError($message);
          }
          $this->logger->error($message);
        }
      }
    }

    if (!$bulk) {
      return;
    }

    if ($exported_count > 0) {
      $summary = $this->formatPlural(
        $exported_count,
        'Exported 1 entity to @source.',
        'Exported @count entities to @source.',
        ['@source' => $source_label ?? '']
      );
      if ($show_messages) {
        $this->messenger->addStatus($summary);
      }
      $this->logger->notice($summary);
    }

    if ($failures) {
      $error_summary = $this->formatPlural(
        count($failures),
        '1 entity failed to export to @source.',
        '@count entities failed to export to @source.',
        ['@source' => $source_label ?? '']
      );
      if ($show_messages) {
        $this->messenger->addError($error_summary);
      }
      $this->logger->error($error_summary . ' ' . implode(' ', $failures));
    }
  }

  /**
   * Export an entity to YAML.
   *
   * @param \Drupal\lark\Model\ExportableInterface $exportable
   *   Exportable entity model.
   *
   * @return bool
   *   Whether the export was successful.
   */
  protected function writeToYaml(ExportableInterface $exportable): bool {
    return (bool) \file_put_contents(
      $exportable->getFilepath(),
      $exportable->toYaml(),
    );
  }

}
