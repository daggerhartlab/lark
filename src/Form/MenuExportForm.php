<?php

declare(strict_types=1);

namespace Drupal\lark\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Export every content link in a menu to a Lark source.
 */
class MenuExportForm extends MenuBaseForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lark_menu_export_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $menu = $this->getMenu();
    $links = $this->menuLinkCollector->getMenuLinks($menu);

    if (!$links) {
      $this->messenger()->addWarning($this->t('This menu has no content links to export.'));
      return $form;
    }

    $exportables = $this->exportableFactory->createFromEntitiesWithDependencies($links);
    $first = reset($exportables);

    $form['#attributes']['class'][] = 'lark-export-form';
    $form['flex'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['lark-flex-break', 'export-to-source-wrapper'],
      ],
      '#weight' => -101,
      'source' => [
        '#type' => 'select',
        '#title' => $this->t('Export Source'),
        '#options' => $this->sourceManager->sourcesAsOptions(),
        '#default_value' => $first && $first->getSource() ? $first->getSource()->id() : $this->larkSettings->defaultSource(),
        '#required' => TRUE,
      ],
      'actions' => [
        '#type' => 'container',
        'export' => [
          '#type' => 'submit',
          '#value' => $this->t('Export to Source'),
        ],
        'download' => [
          '#type' => 'submit',
          '#value' => $this->t('Download'),
        ],
      ],
    ];

    $form['export_form_container'] = $this->buildExportablesContainer($exportables, $form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $menu = $this->getMenu();
    $links = $this->menuLinkCollector->getMenuLinks($menu);
    $action = str_replace('edit-', '', $form_state->getTriggeringElement()['#id']);
    $meta_option_overrides = $this->getSubmittedOverrides('export_form_values', $form_state);

    switch ($action) {
      case 'export':
        $this->exporter->exportEntities(
          $form_state->getValue('source'),
          $links,
          TRUE,
          $meta_option_overrides,
        );
        return;

      case 'download':
        $response = $this->downloadController->downloadEntitiesResponse(
          $links,
          $meta_option_overrides,
        );

        $form_state->setResponse($response);
        break;
    }
  }

}
