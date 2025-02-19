<?php

namespace Drupal\lark\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Diff\DiffFormatter;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\lark\Service\ExportableFactoryInterface;
use Drupal\lark\Service\Utility\StatusResolver;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DiffViewer extends ControllerBase {

  public function __construct(
    protected ExportableFactoryInterface $exportableFactory,
    protected DiffFormatter $diffFormatter,
    protected StatusResolver $statusResolver,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get(ExportableFactoryInterface::class),
      $container->get(DiffFormatter::class),
      $container->get(StatusResolver::class),
    );
  }

  /**
   * Display diff between exported and current entity.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *
   * @return array
   *   Render array.
   */
  public function build(RouteMatchInterface $routeMatch): array {
    $entity_type_id = $routeMatch->getRouteObject()->getOption('_lark_entity_type_id');
    $entity = $routeMatch->getParameter($entity_type_id);
    $exportable = $this->exportableFactory->createFromEntity($entity);

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
