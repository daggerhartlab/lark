<?php

namespace Drupal\lark\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\lark\Model\LarkSettings;
use Drupal\lark\Service\ExportableFactoryInterface;
use Drupal\lark\Service\ExporterInterface;
use Drupal\lark\Service\ImporterInterface;
use Drupal\lark\Service\LarkSourceManager;
use Drupal\lark\Service\MetaOptionManager;
use Drupal\lark\Service\Render\ExportablesStatusBuilder;
use Drupal\lark\Service\Render\ExportablesTableBuilder;
use Drupal\lark\Service\Utility\MenuLinkCollector;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shared plumbing for the menu Lark export and import forms.
 */
abstract class MenuBaseForm extends FormBase {

  use ExportablesOverridesTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ExportableFactoryInterface $exportableFactory,
    protected ExportablesStatusBuilder $statusBuilder,
    protected ExportablesTableBuilder $exportablesTableBuilder,
    protected ExporterInterface $exporter,
    protected ImporterInterface $importer,
    protected LarkSettings $larkSettings,
    protected MenuLinkCollector $menuLinkCollector,
    protected MetaOptionManager $metaOptionManager,
    protected LarkSourceManager $sourceManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get(EntityTypeManagerInterface::class),
      $container->get(ExportableFactoryInterface::class),
      $container->get(ExportablesStatusBuilder::class),
      $container->get(ExportablesTableBuilder::class),
      $container->get(ExporterInterface::class),
      $container->get(ImporterInterface::class),
      $container->get(LarkSettings::class),
      $container->get(MenuLinkCollector::class),
      $container->get(MetaOptionManager::class),
      $container->get(LarkSourceManager::class),
    );
  }

  /**
   * Get the menu this form is acting on.
   *
   * @return \Drupal\system\MenuInterface
   *   The menu.
   */
  protected function getMenu() {
    return $this->getRouteMatch()->getParameter('menu');
  }

  /**
   * Build the status summary and exportables table for a set of exportables.
   *
   * @param \Drupal\lark\Model\ExportableInterface[] $exportables
   *   Exportables to render.
   * @param array $form
   *   The form being built.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current form state.
   *
   * @return array
   *   Render array.
   */
  protected function buildExportablesContainer(array $exportables, array &$form, FormStateInterface $form_state): array {
    $exportables = array_reverse($exportables);

    return [
      '#type' => 'container',
      'divider' => [
        '#markup' => '<hr>',
      ],
      'summary' => $this->statusBuilder->getExportablesSummary($exportables),
      'table' => $this->exportablesTableBuilder->table($exportables, $form, $form_state, 'export_form_values'),
    ];
  }

}
