<?php

namespace Drupal\lark\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\lark\Model\LarkSettings;
use Drupal\lark\Service\ExportableFactoryInterface;
use Drupal\lark\Service\Utility\ExportableStatusBuilder;
use Drupal\lark\Service\ExporterInterface;
use Drupal\lark\Service\Utility\ExportablesTableBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for exporting an entity.
 *
 * @see \Drupal\devel\Routing\RouteSubscriber
 * @see \Drupal\devel\Plugin\Derivative\DevelLocalTask
 */
class EntityExportForm extends EntityBaseForm {

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

    $exportables = $this->exportableFactory->createFromEntityWithDependencies($entity_type_id, $entity->id());
    $exportable = $exportables[$entity->uuid()];

    $sources = $this->entityTypeManager->getStorage('lark_source')->loadByProperties([
      'status' => 1,
    ]);
    $options = [];
    foreach ($sources as $source) {
      $options[$source->id()] = $source->label();
    }

    $form['source'] = [
      '#type' => 'select',
      '#title' => $this->t('Export Source'),
      '#options' => $options,
      '#default_value' => $exportable->getSource() ? $exportable->getSource()->id() : $this->larkSettings->defaultSource(),
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
      'table' => $this->tableFormHandler->table($exportables, $form, $form_state, 'export_form_values')
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $meta_option_overrides = $this->getSubmittedOverrides('export_form_values', $form_state);

    $this->exporter->exportEntity(
      $form_state->getValue('source'),
      $form_state->getValue('entity_type_id'),
      (int) $form_state->getValue('entity_id'),
      TRUE,
      $meta_option_overrides,
    );
  }

}
