<?php

declare(strict_types=1);

namespace Drupal\lark\Controller;

use Drupal\Core\Archiver\ArchiveTar;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\Url;
use Drupal\lark\Entity\LarkSourceInterface;
use Drupal\lark\Model\LarkSettings;
use Drupal\lark\Service\Render\ExportableStatusBuilder;
use Drupal\lark\Service\ExporterInterface;
use Drupal\lark\Service\ImporterInterface;
use Drupal\lark\Service\ExportableFactoryInterface;
use Drupal\lark\Service\Render\SourceViewBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Entity exports controller.
 */
class ExportsManager extends ControllerBase {

  /**
   * The controller constructor.
   *
   * @param \Drupal\lark\Service\Exporter $exporter
   *   The entity exporter service.
   * @param \Drupal\lark\Service\ImporterInterface $importer
   *   The entity importer service.
   * @param \Drupal\lark\Service\ExportableFactoryInterface $exportableFactory
   *   The Lark exportable factory service.
   */
  public function __construct(
    protected ExporterInterface $exporter,
    protected ImporterInterface $importer,
    protected ExportableFactoryInterface $exportableFactory,
    protected EntityRepositoryInterface $entityRepository,
    protected ExportableStatusBuilder $statusBuilder,
    protected LarkSettings $settings,
    protected SourceViewBuilder $sourceViewBuilder,
    protected DownloadController $downloadController,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get(ExporterInterface::class),
      $container->get(ImporterInterface::class),
      $container->get(ExportableFactoryInterface::class),
      $container->get(EntityRepositoryInterface::class),
      $container->get(ExportableStatusBuilder::class),
      $container->get(LarkSettings::class),
      $container->get(SourceViewBuilder::class),
      DownloadController::create($container),
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
    $source = $this->entityTypeManager()->getStorage('lark_source')->load($lark_source);

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
    $source = $this->entityTypeManager()->getStorage('lark_source')->load($lark_source);
    return $this->sourceViewBuilder->viewSource($source);
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
