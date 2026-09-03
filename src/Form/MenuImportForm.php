<?php

namespace Drupal\lark\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Import every menu link found for this menu in a Lark source.
 *
 * Source-driven: a link present in the source but missing from the site is
 * created. Local links absent from the source are never deleted.
 */
class MenuImportForm extends MenuBaseForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lark_menu_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $menu = $this->getMenu();

    // Only offer sources that actually hold links for this menu.
    $import_source_options = [];
    /** @var \Drupal\lark\Entity\LarkSourceInterface[] $sources */
    $sources = $this->sourceManager->loadByProperties(['status' => 1]);
    foreach ($sources as $source) {
      if ($this->menuLinkCollector->getSourceLinkUuids($source, $menu)) {
        $import_source_options[$source->id()] = $source->label();
      }
    }

    if (!$import_source_options) {
      $this->messenger()->addWarning($this->t("This menu's links are not exported to any sources."));
      return $form;
    }

    $default_source = $this->larkSettings->defaultSource();
    if (!isset($import_source_options[$default_source])) {
      $default_source = array_key_first($import_source_options);
    }

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
        '#default_value' => $default_source,
        '#required' => TRUE,
      ],
      'actions' => [
        '#type' => 'container',
        'import' => [
          '#type' => 'submit',
          '#value' => $this->t('Import from Source'),
        ],
      ],
    ];

    // Show the site's current links so the statuses are meaningful. Links that
    // exist only in the source show up once imported.
    $links = $this->menuLinkCollector->getMenuLinks($menu);
    if ($links) {
      $exportables = $this->exportableFactory->createFromEntitiesWithDependencies($links);
      $form['export_form_container'] = $this->buildExportablesContainer($exportables, $form, $form_state);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $source_id = $form_state->getValue('source');
    $source = $this->sourceManager->load($source_id);
    $menu = $this->getMenu();

    $uuids = $this->menuLinkCollector->getSourceLinkUuids($source, $menu);
    $this->importer->importSourceExports($source_id, $uuids, TRUE);
  }

}
