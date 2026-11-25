<?php

namespace Drupal\lark\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\lark\Controller\DownloadController;
use Drupal\lark\Model\LarkSettings;
use Drupal\lark\Service\ExportableFactoryInterface;
use Drupal\lark\Service\ExporterInterface;
use Drupal\lark\Service\ImporterInterface;
use Drupal\lark\Service\MetaOptionManager;
use Drupal\lark\Service\Render\ExportablesStatusBuilder;
use Drupal\lark\Service\Render\ExportablesTableBuilder;
use Drupal\lark\Service\LarkSourceManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class EntityBaseForm extends FormBase {

  use ExportablesOverridesTrait;

  public function __construct(
    protected DownloadController $downloadController,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ExportableFactoryInterface $exportableFactory,
    protected ExportablesStatusBuilder $statusBuilder,
    protected ExportablesTableBuilder $exportablesTableBuilder,
    protected ExporterInterface $exporter,
    protected FileSystemInterface $fileSystem,
    protected ImporterInterface $importer,
    protected LarkSettings $larkSettings,
    protected MetaOptionManager $metaOptionManager,
    protected LarkSourceManager $sourceManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      DownloadController::create($container),
      $container->get(EntityTypeManagerInterface::class),
      $container->get(ExportableFactoryInterface::class),
      $container->get(ExportablesStatusBuilder::class),
      $container->get(ExportablesTableBuilder::class),
      $container->get(ExporterInterface::class),
      $container->get(FileSystemInterface::class),
      $container->get(ImporterInterface::class),
      $container->get(LarkSettings::class),
      $container->get(MetaOptionManager::class),
      $container->get(LarkSourceManager::class),
    );
  }

}
