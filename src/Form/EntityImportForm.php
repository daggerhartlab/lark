<?php

namespace Drupal\lark\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\lark\Service\ExportableFactoryInterface;
use Drupal\lark\Service\ImporterInterface;
use Drupal\lark\Service\SourceManagerInterface;
use Drupal\lark\Service\Utility\ExportableStatusBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class EntityImportForm extends FormBase {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ExportableFactoryInterface $exportableFactory,
    protected SourceManagerInterface $sourceManager,
    protected ImporterInterface $importer,
    protected ExportableStatusBuilder $statusBuilder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get(EntityTypeManagerInterface::class),
      $container->get(ExportableFactoryInterface::class),
      $container->get(SourceManagerInterface::class),
      $container->get(ImporterInterface::class),
      $container->get(ExportableStatusBuilder::class),
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
    foreach ($this->sourceManager->getInstances() as $source) {
      if ($source->exportExistsInSource($entity_type_id, $entity->bundle(), $entity->uuid())) {
        $import_source_options[$source->id()] = $source->label();
      }
    }

    if (!$import_source_options) {
      $this->messenger()->addWarning("This entity is not exported to any sources.");
      return [];
    }

    $exportables = $this->exportableFactory->getEntityExportables($entity_type_id, $entity->id());
    $exportable = $exportables[$entity->uuid()];

    $form['status_summary'] = $this->statusBuilder->getExportablesSummary($exportables);
    $form['source_plugin_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Import Source'),
      '#options' => $import_source_options,
      '#default_value' => $exportable->getSource() ? $exportable->getSource()->id() : $this->sourceManager->getDefaultSource()->id(),
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

    return $form;
  }

  /**
   * @inheritDoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $source_plugin_id = $form_state->getValue('source_plugin_id');
    $uuid = $form_state->getValue('entity_uuid');
    $this->importer->importSingleEntityFromSource($source_plugin_id, $uuid);
  }

}
