<?php

namespace Drupal\lark\Service\Utility;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\lark\ExportableStatus;

/**
 * Utility class for building exportable status render elements.
 */
class ExportableStatusBuilder {

  use StringTranslationTrait;

  private array $statusDetails = [];

  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Get array keyed by ExportableStatus names.
   *
   * @param mixed $default_value
   *   Default value for each item in the array.
   *
   * @return array
   *   Array keyed by ExportableStatus names.
   */
  public function getKeyedArray(mixed $default_value = NULL): array {
    return array_fill_keys(array_column(ExportableStatus::cases(), 'name'), $default_value);
  }

  /**
   * Get status render details.
   *
   * @param \Drupal\lark\ExportableStatus $status
   *
   * @return string[]
   *   Exportable status render details.
   */
  public function getStatusRenderDetails(ExportableStatus $status): array {
    return $this->getAllStatusRenderDetails()[$status->name];
  }

  /**
   * Get all status render details.
   *
   * @return array[]
   *   Exportable status render details. Each item is an array with keys:
   *     - class_name: CSS class name for the status.
   *     - label: Human-readable status label.
   *     - icon_url: URL to the status icon.
   *     - icon: Markup for the status icon.
   *     - render: Render array for the status icon.
   */
  public function getAllStatusRenderDetails(): array {
    if ($this->statusDetails) {
      return $this->statusDetails;
    }

    $statuses = $this->getKeyedArray([
      'class_name' => '',
      'label' => '',
      'icon_url' => '',
      'icon' => '',
      'render' => '',
    ]);

    foreach ($statuses as $status_name => $details) {
      // Break TitleCase into separate words array.
      $words = array_filter(preg_split('/(?=[A-Z])/', $status_name));
      $details['class_name'] = strtolower(implode('-', $words));
      $details['label'] = ucwords(strtolower(implode(' ', $words)));
      $details['label_render'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $details['label'],
        '#attributes' => [
          'class' => ['summary-label'],
        ],
      ];
      $path = $this->moduleHandler->getModule('lark')->getPath();
      $details['icon_url'] = Url::fromUri("base:/{$path}/assets/icons/status--{$details['class_name']}.png")->toString();
      $details['icon_render'] = [
        '#theme' => 'image',
        '#uri' => $details['icon_url'],
        '#alt' => $details['label'],
        '#attributes' => [
          'width' => '25px',
          'height' => '25px',
          'class' => ['summary-icon'],
        ],
      ];

      $statuses[$status_name] = $details;
    }

    $this->statusDetails = $statuses;
    return $this->statusDetails;
  }

  /**
   * Build status summary for root-level exports.
   *
   * @param \Drupal\lark\Model\ExportableInterface[] $exportables
   *   All exportables for a root-level export.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   Summary string.
   */
  public function getExportablesSummary(array $exportables): array {
    $summary = [
      '#theme' => 'table',
      '#header' => [
        'heading' => [
          'colspan' => 2,
          'class' => ['summary-heading'],
          'data' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#value' => $this->t('Exports Status Summary'),
          ],
        ]
      ],
      '#rows' => [],
      '#attributes' => [
        'class' => ['lark-status-summary-table'],
      ],
    ];

    $status_counts = $this->getKeyedArray(0);
    $status_details = $this->getAllStatusRenderDetails();

    foreach ($exportables as $exportable) {
      $status_counts[$exportable->getStatus()->name] += 1;
    }

    foreach ($status_counts as $status_name => $count) {
      $details = $status_details[$status_name];
      $summary['#rows'][] = [
        'label' => [
          'header' => TRUE,
          'data' => [
            '#type' => 'container',
            '#attributes' => [
              'class' => [
                'summary-container',
                ($count ? 'summary-container-active' : 'summary-container-empty'),
              ],
            ],
            'icon' => $details['icon_render'],
            'label' => $details['label_render'],
          ],
        ],
        'count' => [
          'data' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#value' => $count,
            '#attributes' => [
              'class' => [
                'summary-count',
                ($count ? 'summary-count-active' : 'summary-count-empty'),
              ],
            ],
          ],
        ],
      ];
    }

    return $summary;
  }

}
