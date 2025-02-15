<?php

namespace Drupal\lark\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Diff\DiffFormatter;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\lark\Model\ExportableInterface;
use Drupal\lark\Service\ExportableFactoryInterface;
use Drupal\lark\Service\Utility\ExportableStatusResolver;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DiffViewer extends ControllerBase {

  public function __construct(
    protected ExportableFactoryInterface $exportableFactory,
    protected DiffFormatter $diffFormatter,
    protected ExportableStatusResolver $statusResolver,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get(ExportableFactoryInterface::class),
      $container->get(DiffFormatter::class),
      $container->get(ExportableStatusResolver::class)
    );
  }

  /**
   * Display diff between exported and current entity.
   *
   * @param string $source_plugin_id
   *   The source plugin id.
   * @param string $uuid
   *   The UUID of the entity to import.
   *
   * @return array
   *   Render array.
   */
  public function build(string $source_plugin_id, string $uuid): array {
    $exportable = $this->exportableFactory->createFromSource($source_plugin_id, $uuid);
    if (!$exportable) {
      return [];
    }

    return [
      '#attached' => [
        'library' => [
          'system/diff',
          'lark/admin',
        ],
      ],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h1',
        '#value' => $this->t('%entity_type - @label', [
          '%entity_type' => $exportable->entity()->getEntityTypeId(),
          '@label' => $exportable->entity()->label(),
        ]),
      ],
      'diff' => [
        '#type' => 'table',
        '#attributes' => [
          'class' => ['diff', 'lark-diff-table'],
        ],
        '#header' => [
          [
            'data' => $this->t('File'),
            'colspan' => '2'
          ],
          [
            'data' => $this->t('Entity'),
            'colspan' => '2',
          ],
        ],
        '#rows' => $this->diffFormatter->format($this->statusResolver->exportableToDiff($exportable)),
      ],
    ];
  }

}
