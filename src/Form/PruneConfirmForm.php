<?php

namespace Drupal\lark\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\lark\Service\ExportFileManager;
use Drupal\lark\Service\ImporterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PruneConfirmForm extends ConfirmFormBase {

  public function __construct(
    private ImporterInterface $importer,
    private ExportFileManager $exportFileManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get(ImporterInterface::class),
      $container->get(ExportFileManager::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    $source = $this->getRequest()->attributes->get('lark_source');
    $prune_target = $this->getRequest()->attributes->get('prune_target');

    if ($prune_target === 'all') {
      return $this->t('Are you sure you want to delete all content in source "%label"?', [
        '%label' => $source->label(),
      ]);
    }

    $collection = $this->importer->discoverSourceExports($source);
    $export = $collection->get($prune_target);

    return $this->t('Are you sure you want to delete "%prune_target" and its dependencies from the source "%label"?', [
      '%label' => $source->label(),
      '%prune_target' => $export->label(),
    ]);
  }

  /**
   * @inheritDoc
   */
  public function getCancelUrl() {
    $source = $this->getRequest()->attributes->get('lark_source');
    return $source->toUrl();
  }

  /**
   * @inheritDoc
   */
  public function getFormId() {
    return 'lark_source_prune_confirm_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $source = $this->getRequest()->attributes->get('lark_source');
    $prune_target = $this->getRequest()->attributes->get('prune_target');
    $collection = $this->importer->discoverSourceExports($source);

    if ($prune_target !== 'all') {
      $collection = $collection->getWithDependencies($prune_target);
      if (!$collection->count()) {
        $this->messenger()->addWarning($this->t('No content found for %prune_target.', [
          '%prune_target' => $prune_target,
        ]));

        return $this->redirect($source->toUrl()->toString());
      }
    }

    $form['items'] = [
      '#tree' => TRUE,
    ];
    foreach ($collection as $uuid => $export) {
      $form['items'][$uuid] = [
        '#type' => 'value',
        '#value' => $uuid,
      ];
    }

    $form['table'] = [
      '#theme' => 'table',
      '#header' => [
        $this->t('Entity Type'),
        $this->t('Bundle'),
        $this->t('Label'),
        $this->t('UUID'),
      ],
      '#rows' => $collection->map(function($export) {
        /** @var \Drupal\lark\Model\ExportArray $export */
        return [
          $export->entityTypeId(),
          $export->bundle(),
          $export->label(),
          $export->uuid(),
        ];
      }),
    ];

    return parent::buildForm($form, $form_state); // TODO: Change the autogenerated stub
  }

  /**
   * @inheritDoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $source = $this->getRequest()->attributes->get('lark_source');
    $prune_items = $form_state->getValue('items');
    foreach ($prune_items as $uuid) {
      $this->exportFileManager->removeExportWithDependencies($source->directory(), $uuid);
    }

    $form_state->setRedirectUrl($source->toUrl());
  }

}
