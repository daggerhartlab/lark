<?php

namespace Drupal\lark\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\lark\Model\LarkSettings;
use Drupal\lark\Service\AssetFileManager;
use Drupal\lark\Service\MetaOptionManager;
use Drupal\lark\Service\ExportableFactoryInterface;
use Drupal\lark\Service\Utility\ExportableStatusBuilder;
use Drupal\lark\Service\ExporterInterface;
use Drupal\lark\Service\SourceManagerInterface;
use Drupal\lark\Service\Utility\ExportableTableFormHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for exporting an entity.
 *
 * @see \Drupal\devel\Routing\RouteSubscriber
 * @see \Drupal\devel\Plugin\Derivative\DevelLocalTask
 */
class EntityExportForm extends FormBase {

  /**
   * EntityExportForm constructor.
   *
   * @param \Drupal\lark\Service\ExporterInterface $entityExporter
   *   The entity exporter service.
   * @param \Drupal\lark\Service\SourceManagerInterface $sourceManager
   *   The Lark source plugin manager service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ExporterInterface $entityExporter,
    protected SourceManagerInterface $sourceManager,
    protected ExportableFactoryInterface $exportableFactory,
    protected ExportableStatusBuilder $statusBuilder,
    protected LarkSettings $settings,
    protected AssetFileManager $assetFileManager,
    protected MetaOptionManager $metaOptionManager,
    protected ExportableTableFormHandler $tableFormHandler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get(EntityTypeManagerInterface::class),
      $container->get(ExporterInterface::class),
      $container->get(SourceManagerInterface::class),
      $container->get(ExportableFactoryInterface::class),
      $container->get(ExportableStatusBuilder::class),
      $container->get(LarkSettings::class),
      $container->get(AssetFileManager::class),
      $container->get(MetaOptionManager::class),
      $container->get(ExportableTableFormHandler::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lark_entity_export_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $entity_type_id = $this->getRouteMatch()->getRouteObject()->getOption('_lark_entity_type_id');
    $entity = $this->getRouteMatch()->getParameter($entity_type_id);
    if (is_numeric($entity)) {
      $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($entity);
    }

    $exportables = $this->exportableFactory->getEntityExportables($entity_type_id, $entity->id());
    $exportable = $exportables[$entity->uuid()];

    $form['#attached']['library'][] = 'lark/admin';
    $form['source'] = [
      '#type' => 'select',
      '#title' => $this->t('Export Source'),
      '#options' => $this->sourceManager->getOptions(),
      '#default_value' => $exportable->getSource() ? $exportable->getSource()->id() : $this->sourceManager->getDefaultSource()->id(),
      '#required' => TRUE,
      '#weight' => -101,
    ];
    $form['entity_type_id'] = [
      '#type' => 'value',
      '#value' => $entity_type_id,
    ];
    $form['entity_id'] = [
      '#type' => 'value',
      '#value' => $entity->id(),
    ];
    $form['actions'] = [
      '#type' => 'actions',
      '#weight' => -100,
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Export'),
      ],
    ];

    $exportables = array_reverse($exportables);
    $form['export_form_container'] = [
      '#type' => 'container',
      'divider' => [
        '#markup' => '<hr>',
      ],
      'summary' => $this->statusBuilder->getExportablesSummary($exportables),
      'table' => $this->tableFormHandler->tablePopulated($exportables, $form, $form_state, 'export_form_values')
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $exports_meta_options_overrides = $this->tableFormHandler->getSubmittedMetaOptionOverrides('export_form_values', $form_state);

    $this->entityExporter->exportEntity(
      $form_state->getValue('source'),
      $form_state->getValue('entity_type_id'),
      (int) $form_state->getValue('entity_id'),
      TRUE,
      $exports_meta_options_overrides,
    );
  }

}
