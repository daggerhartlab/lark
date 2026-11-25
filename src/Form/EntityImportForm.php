<?php

namespace Drupal\lark\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Imports an entity from a lark source.
 */
class EntityImportForm extends EntityBaseForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lark_entity_import_form';
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

    // Get a list of sources where this entity may be exported.
    $import_source_options = [];
    /** @var \Drupal\lark\Entity\LarkSourceInterface[] $sources */
    $sources = $this->sourceManager->loadByProperties([
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

    $form['entity_uuid'] = [
      '#type' => 'value',
      '#value' => $entity->uuid(),
    ];
    $form['flex'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['lark-flex-break', 'export-to-source-wrapper'],
      ],
      '#weight' => -101,
      'source' => [
        '#type' => 'select',
        '#title' => $this->t('Import Source'),
        '#options' => $import_source_options,
        '#default_value' => $exportable->getSource() ? $exportable->getSource()->id() : $this->larkSettings->defaultSource(),
        '#required' => TRUE,
        '#weight' => -101,
      ],
      'actions' => [
        '#type' => 'container',
        'import' => [
          '#type' => 'submit',
          '#value' => $this->t('Import from Source'),
        ],
      ],
    ];

    $exportables = array_reverse($exportables);
    $form['export_form_container'] = [
      '#type' => 'container',
      'divider' => [
        '#markup' => '<hr>',
      ],
      'summary' => $this->statusBuilder->getExportablesSummary($exportables),
      'table' => $this->exportablesTableBuilder->table($exportables, $form, $form_state, 'export_form_values'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $source_id = $form_state->getValue('source');
    $uuid = $form_state->getValue('entity_uuid');
    $this->importer->importSourceExport($source_id, $uuid);
  }

}
