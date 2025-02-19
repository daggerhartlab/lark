<?php

namespace Drupal\lark\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\lark\Model\LarkSettings;
use Drupal\lark\Service\ExportableFactoryInterface;
use Drupal\lark\Service\ImporterInterface;
use Drupal\lark\Service\Utility\ExportableStatusBuilder;
use Drupal\lark\Service\Utility\TableFormHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EntityImportForm extends FormBase {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LarkSettings $larkSettings,
    protected ExportableFactoryInterface $exportableFactory,
    protected ImporterInterface $importer,
    protected ExportableStatusBuilder $statusBuilder,
    protected TableFormHandler $tableFormHandler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get(EntityTypeManagerInterface::class),
      $container->get(LarkSettings::class),
      $container->get(ExportableFactoryInterface::class),
      $container->get(ImporterInterface::class),
      $container->get(ExportableStatusBuilder::class),
      $container->get(TableFormHandler::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lark_entity_import_form';
  }

  /**
   * @inheritDoc
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $entity_type_id = $this->getRouteMatch()->getRouteObject()->getOption('_lark_entity_type_id');
    $entity = $this->getRouteMatch()->getParameter($entity_type_id);
    if (is_numeric($entity)) {
      $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($entity);
    }

    // Get a list of sources where this entity may be exported.
    $import_source_options = [];
    /** @var \Drupal\lark\Entity\LarkSourceInterface[] $sources */
    $sources = $this->entityTypeManager->getStorage('lark_source')->loadByProperties([
      'status' => 1,
    ]);
    foreach ($sources as $source) {
      if ($source->exportExistsInSource($entity_type_id, $entity->bundle(), $entity->uuid())) {
        $import_source_options[$source->id()] = $source->label();
      }
    }

    if (!$import_source_options) {
      $this->messenger()->addWarning("This entity is not exported to any sources.");
      return [];
    }

    $exportables = $this->exportableFactory->createFromEntityWithDependencies($entity_type_id, $entity->id());
    $exportable = $exportables[$entity->uuid()];

    $form['source_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Import Source'),
      '#options' => $import_source_options,
      '#default_value' => $exportable->getSource() ? $exportable->getSource()->id() : $this->larkSettings->defaultSource(),
      '#required' => TRUE,
      '#weight' => -101,
    ];
    $form['entity_uuid'] = [
      '#type' => 'value',
      '#value' => $entity->uuid(),
    ];

    $form['actions'] = [
      '#type' => 'actions',
      '#weight' => -100,
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Import with Dependencies'),
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
   * @inheritDoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $source_id = $form_state->getValue('source_id');
    $uuid = $form_state->getValue('entity_uuid');
    $this->importer->importSourceExport($source_id, $uuid);
  }

}
