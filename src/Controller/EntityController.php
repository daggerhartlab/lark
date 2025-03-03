<?php

namespace Drupal\lark\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Diff\DiffFormatter;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\lark\Service\ExportableFactoryInterface;
use Drupal\lark\Service\Utility\StatusResolver;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class EntityController extends ControllerBase {

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
   * Negotiate which  tab the user should be on based on permissions.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect.
   */
  public function larkLoad(RouteMatchInterface $routeMatch): RedirectResponse {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity_type_id = $routeMatch->getRouteObject()->getOption('_lark_entity_type_id');
    $entity = $routeMatch->getParameter($entity_type_id);

    // Remove destination query parameter otherwise user will be redirected to
    // the destination instead of the entity page.
    \Drupal::request()->query->remove('destination');

    if ($this->currentUser()->hasPermission('lark export entity')) {
      return new RedirectResponse($entity->toUrl('lark-export')->toString());
    }

    if ($this->currentUser()->hasPermission('lark import entity')) {
      return new RedirectResponse($entity->toUrl('lark-import')->toString());
    }

    return new RedirectResponse($entity->toUrl()->toString());
  }

  /**
   * Display diff between exported and current entity.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *
   * @return array
   *   Render array.
   */
  public function viewDiff(RouteMatchInterface $routeMatch): array {
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
