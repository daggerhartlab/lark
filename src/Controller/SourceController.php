<?php

declare(strict_types=1);

namespace Drupal\lark\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\lark\Entity\LarkSourceInterface;
use Drupal\lark\Service\ImporterInterface;
use Drupal\lark\Service\Render\SourceViewBuilder;
use Drupal\lark\Service\LarkSourceManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Entity exports controller.
 *
 * @phpstan-consistent-constructor
 */
class SourceController extends ControllerBase {

  /**
   * Constructs a SourceController object.
   *
   * @param \Drupal\lark\Controller\DownloadController $downloadController
   *   The download controller.
   * @param \Drupal\lark\Service\ImporterInterface $importer
   *   The entity importer service.
   * @param \Drupal\lark\Service\LarkSourceManager $sourceManager
   *   The lark source manager.
   * @param \Drupal\lark\Service\Render\SourceViewBuilder $sourceViewBuilder
   *   The source view builder.
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
   * Imports a single entity from a source.
   *
   * @param \Drupal\lark\Entity\LarkSourceInterface $lark_source
   *   The source to import from.
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
   * Imports all entities from a single source.
   *
   * @param \Drupal\lark\Entity\LarkSourceInterface $lark_source
   *   The source to import.
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
   * Gets the source page title.
   *
   * @param string $lark_source
   *   The source id.
   *
   * @return string
   *   The page title.
   *
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
   * Builds the source page.
   *
   * @param string $lark_source
   *   The source id.
   *
   * @return array
   *   Render array.
   *
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
   * Downloads a source's exports as an archive.
   *
   * @param \Drupal\lark\Entity\LarkSourceInterface $lark_source
   *   The source to download.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   File download response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function downloadSource(LarkSourceInterface $lark_source): BinaryFileResponse {
    return $this->downloadController->downloadSourceResponse($lark_source);
  }

}
