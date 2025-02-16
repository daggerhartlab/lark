<?php

declare(strict_types=1);

namespace Drupal\lark\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\lark\Entity\LarkSourceInterface;
use Drupal\lark\ExportableStatus;
use Drupal\lark\Model\LarkSettings;
use Drupal\lark\Service\Utility\ExportableStatusBuilder;
use Drupal\lark\Model\ExportableInterface;
use Drupal\lark\Plugin\Lark\SourceInterface;
use Drupal\lark\Service\ExporterInterface;
use Drupal\lark\Service\ImporterInterface;
use Drupal\lark\Service\ExportableFactoryInterface;
use Drupal\lark\Service\SourceManagerInterface;
use Drupal\lark\Service\Utility\SourceViewBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

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
   * @param \Drupal\lark\Service\SourceManagerInterface $sourceManager
   *   The Lark source plugin manager service.
   * @param \Drupal\lark\Service\ExportableFactoryInterface $exportableFactory
   *   The Lark exportable factory service.
   */
  public function __construct(
    protected ExporterInterface $exporter,
    protected ImporterInterface $importer,
    protected SourceManagerInterface $sourceManager,
    protected ExportableFactoryInterface $exportableFactory,
    protected EntityRepositoryInterface $entityRepository,
    protected ExportableStatusBuilder $statusBuilder,
    protected LarkSettings $settings,
    protected SourceViewBuilder $sourceViewBuilder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get(ExporterInterface::class),
      $container->get(ImporterInterface::class),
      $container->get(SourceManagerInterface::class),
      $container->get(ExportableFactoryInterface::class),
      $container->get(EntityRepositoryInterface::class),
      $container->get(ExportableStatusBuilder::class),
      $container->get(LarkSettings::class),
      $container->get(SourceViewBuilder::class),
    );
  }

  /**
   * Import single entity.
   *
   * @param string $source_plugin_id
   *   Source plugin id.
   * @param string $uuid
   *   The UUID of the entity to import.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function importEntity(string $source_plugin_id, string $uuid): RedirectResponse {
    $this->importer->importSingleEntityFromSource($source_plugin_id, $uuid);
    return new RedirectResponse(Url::fromRoute('lark.exports_list')->toString());
  }

  /**
   * Import all entities from a single source.
   *
   * @param string $source_plugin_id
   *   The source plugin id.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function importSource(string $source_plugin_id): RedirectResponse {
    $this->importer->importFromSingleSource($source_plugin_id);
    return new RedirectResponse(Url::fromRoute('lark.exports_list')->toString());
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
   * List exported entities.
   *
   * @return array
   *   Render array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function build(): array {
    /** @var \Drupal\lark\Entity\LarkSourceInterface[] $sources */
    $sources = $this->entityTypeManager()->getStorage('lark_source')->loadByProperties([
      'status' => 1,
    ]);

    $build = [];
    foreach ($sources as $source) {
      $build[$source->id()] = [
        '#type' => 'details',
        '#title' => $source->label(),
        '#open' => FALSE,
        'source' => $this->sourceViewBuilder->viewSource($source),
      ];
    }

    return $build;
  }

}
