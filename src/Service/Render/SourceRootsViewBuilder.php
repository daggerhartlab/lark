<?php

namespace Drupal\lark\Service\Render;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\lark\Entity\LarkSourceInterface;
use Drupal\lark\Model\ExportableInterface;
use Drupal\lark\Service\ExportableFactoryInterface;
use Drupal\lark\Service\ImporterInterface;
use Drupal\lark\Service\Utility\SourceUtility;

class SourceRootsViewBuilder {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ExportableFactoryInterface $exportableFactory,
    protected ExportableStatusBuilder $statusBuilder,
    protected ImporterInterface $importer,
    protected SourceUtility $sourceUtility,
  ) {}

  public function view(LarkSourceInterface $source, ExportableInterface $exportable): array {
    $root_uuid = $exportable->entity()->uuid();
    $dependency_exportables = $this->sourceUtility->getRootDependencyExportables($source, $root_uuid);

    return [
      'summary' => $this->statusBuilder->getExportablesSummary($dependency_exportables),
      'dependencies_heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h4',
        '#value' => $this->t('Dependencies'),
      ],
      'dependencies_description' => [
        '#type' => 'html_tag',
        '#tag' => 'small',
        '#value' => $this->t('All dependencies of an entity are imported along with the parent entities.'),
      ],
      'dependencies_table' => $this->table($source, $dependency_exportables),
    ];
  }

  public function table(LarkSourceInterface $source, array $dependency_exportables): array {
    return [
      '#theme' => 'table',
      '#header' => $this->headers(),
      '#rows' => $this->rows($source, $dependency_exportables),
      '#empty' => $this->t('No dependencies found.'),
      '#attributes' => [
        'class' => ['lark-exportables-table-form'],
      ],
      '#attached' => [
        'library' => ['lark/admin']
      ],
    ];
  }

  protected function headers(): array {
    return [
      'status' => [
        'class' => ['status'],
        'data' => $this->t('Status'),
      ],
      'entity_type' => [
        'class' => ['entity-type'],
        'data' => $this->t('Entity type'),
      ],
      'label' => [
        'class' => ['label'],
        'data' => $this->t('Label'),
      ],
      'filepath' => [
        'class' => ['filepath'],
        'data' => $this->t('File'),
      ],
      'operations' => [
        'class' => ['operations'],
        'data' => $this->t('Operations'),
      ],
    ];
  }

  protected function rows(LarkSourceInterface $source, array $dependency_exportables): array {
    $rows = [];
    foreach ($dependency_exportables as $exportable) {
      $rows[] = $this->row($source, $exportable);
    }
    return $rows;
  }

  protected function row(LarkSourceInterface $source, ExportableInterface $exportable): array {
    $relative = str_replace($source->directoryProcessed(FALSE) . DIRECTORY_SEPARATOR, '', $exportable->getFilepath());
    $status_details = $this->statusBuilder->getStatusRenderDetails($exportable->getStatus());
    return [
      'status' => [
        'class' => ['status'],
        'data' => $status_details['icon_render'],
      ],
      'entity_type' => [
        'class' => ['entity-type'],
        'data' => $exportable->entity()->getEntityTypeId(),
      ],
      'label' => [
        'class' => ['label'],
        'data' => $exportable->entity()->label(),
      ],
      'filepath' => [
        'class' => ['filepath'],
        'data' => Markup::create("<small title='{$exportable->getFilepath()}'><code>{$relative}</code></small>"),
      ],
      'operations' => [
        'data' => [
          '#type' => 'operations',
          '#links' => $this->sourceUtility->getExportableOperations($source, $exportable),
        ],
      ],
    ];
  }

}
