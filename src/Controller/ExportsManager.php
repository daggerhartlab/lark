<?php

declare(strict_types=1);

namespace Drupal\lark\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\lark\Entity\LarkSourceInterface;
use Drupal\lark\Service\ImporterInterface;
use Drupal\lark\Service\Render\SourceViewBuilder;
use Drupal\lark\Service\LarkSourceManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Entity exports controller.
 */
class ExportsManager extends ControllerBase {

  /**
   * The controller constructor.
   *
   * @param \Drupal\lark\Controller\DownloadController $downloadController
   * @param \Drupal\lark\Service\ImporterInterface $importer
   *   The entity importer service.
   * @param \Drupal\lark\Service\LarkSourceManager $sourceManager
   * @param \Drupal\lark\Service\Render\SourceViewBuilder $sourceViewBuilder
   */
  public function __construct(
    protected DownloadController $downloadController,
    protected ImporterInterface $importer,
    protected LarkSourceManager $sourceManager,
    protected SourceViewBuilder $sourceViewBuilder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      DownloadController::create($container),
      $container->get(ImporterInterface::class),
      $container->get(LarkSourceManager::class),
      $container->get(SourceViewBuilder::class),
    );
  }

  /**
   * Import single entity.
   *
   * @param \Drupal\lark\Entity\LarkSourceInterface $lark_source
   * @param string $uuid
   *   The UUID of the entity to import.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function importEntity(LarkSourceInterface $lark_source, string $uuid): RedirectResponse {
    $this->importer->importSourceExport($lark_source->id(), $uuid);
    return new RedirectResponse($lark_source->toUrl()->toString());
  }

  /**
   * Import all entities from a single source.
   *
   * @param \Drupal\lark\Entity\LarkSourceInterface $lark_source
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function importSource(LarkSourceInterface $lark_source): RedirectResponse {
    $this->importer->importSource($lark_source->id());
    return new RedirectResponse($lark_source->toUrl()->toString());
  }

  /**
   * View source title.
   *
   * @param string $lark_source
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function viewSourceTitle(string $lark_source): string {
    $source = $this->sourceManager->load($lark_source);

    return strtr('Source: %label', [
      '%label' => $source->label(),
    ]);
  }

  /**
   * @param string $lark_source
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function viewSource(string $lark_source): array {
    /** @var \Drupal\lark\Entity\LarkSourceInterface $source */
    $source = $this->sourceManager->load($lark_source);
    return $this->sourceViewBuilder->view($source);
  }

  /**
   * @param string $source_id
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function downloadSource(LarkSourceInterface $lark_source): BinaryFileResponse {
    return $this->downloadController->downloadSourceResponse($lark_source);
  }

  /**
   * Negotiate which  tab the user should be on based on permissions.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect.
   */
  public function larkLoad(RouteMatchInterface $routeMatch): RedirectResponse {
    $entity_type_id = $routeMatch->getRouteObject()->getOption('_lark_entity_type_id');
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $routeMatch->getParameter($entity_type_id);

    if ($this->currentUser()->hasPermission('lark export entity')) {
      return new RedirectResponse($entity->toUrl('lark-export')->toString());
    }

    if ($this->currentUser()->hasPermission('lark import entity')) {
      return new RedirectResponse($entity->toUrl('lark-import')->toString());
    }

    return new RedirectResponse($entity->toUrl()->toString());
  }

}
