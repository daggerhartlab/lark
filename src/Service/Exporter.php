<?php

declare(strict_types=1);

namespace Drupal\lark\Service;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\lark\Model\ExportableInterface;
use Drupal\lark\Model\LarkSettings;
use Drupal\user\UserInterface;

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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\lark\Service\MetaOptionManager $metaOptionManager
   *   Meta options manager.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(
    protected ExportableFactoryInterface $exportableFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected MetaOptionManager $metaOptionManager,
    protected LoggerChannelInterface $logger,
    protected MessengerInterface $messenger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function exportEntity(string $source_id, string $entity_type_id, int $entity_id, bool $show_messages = TRUE, array $meta_options_overrides = []): void {
    $source = $this->entityTypeManager->getStorage('lark_source')->load($source_id);
    $exportables = $this->exportableFactory->createFromEntityWithDependencies($entity_type_id, $entity_id, $source, $meta_options_overrides);

    foreach ($exportables as $exportable) {
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

        if ($show_messages) {
          $this->messenger->addStatus($message);
        }
        $this->logger->notice($message);
      }
      else {
        $message = $this->t('Failed to export @entity_type_id : @entity_id : @label', [
          '@entity_type_id' => $exportable->entity()->getEntityTypeId(),
          '@entity_id' => $exportable->entity()->id(),
          '@label' => $exportable->entity()->label(),
        ]);
        if ($show_messages) {
          $this->messenger->addError($message);
        }
        $this->logger->error($message);
      }
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
