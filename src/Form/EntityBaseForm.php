<?php

namespace Drupal\lark\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\lark\Controller\DownloadController;
use Drupal\lark\Model\LarkSettings;
use Drupal\lark\Service\ExportableFactoryInterface;
use Drupal\lark\Service\ExporterInterface;
use Drupal\lark\Service\ImporterInterface;
use Drupal\lark\Service\MetaOptionManager;
use Drupal\lark\Service\Render\ExportablesStatusBuilder;
use Drupal\lark\Service\Render\ExportablesTableBuilder;
use Drupal\lark\Service\Utility\SourceUtility;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class EntityBaseForm extends FormBase {
  public function __construct(
    protected DownloadController         $downloadController,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ExportableFactoryInterface $exportableFactory,
    protected ExportablesStatusBuilder   $statusBuilder,
    protected ExportablesTableBuilder    $exportablesTableBuilder,
    protected ExporterInterface          $exporter,
    protected FileSystemInterface        $fileSystem,
    protected ImporterInterface          $importer,
    protected LarkSettings               $larkSettings,
    protected MetaOptionManager          $metaOptionManager,
    protected SourceUtility              $sourceUtility,
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
      $container->get(SourceUtility::class),
    );
  }


  /**
   * @param string $tree_name
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getSubmittedOverrides(string $tree_name, FormStateInterface $form_state): array {
    $submitted_values = $form_state->getValue($tree_name) ?? [];
    if (!is_array($submitted_values)) {
      return [];
    }

    $overrides = [];
    foreach ($submitted_values as $uuid => $values) {
      $exportable = $this->exportableFactory->createFromUuid($uuid);

      foreach ($this->metaOptionManager->getInstances() as $meta_option) {
        // Ensure the plugin applies to the entity.
        if (!$meta_option->applies($exportable->entity())) {
          continue;
        }

        // Ensure it has submitted values.
        if (!array_key_exists($meta_option->id(), $values)) {
          $values[$meta_option->id()] = [];
        }

        // Allow the plugin to record the values to the export.
        $plugin_values = $meta_option->processFormValues($values[$meta_option->id()], $exportable, $form_state);
        if ($plugin_values) {
          $overrides[$uuid][$meta_option->id()] = $plugin_values;
        }
      }
    }

    return $overrides;
  }

}
