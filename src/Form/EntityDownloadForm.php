<?php

namespace Drupal\lark\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\lark\Controller\DownloadController;
use Drupal\lark\Service\ExportableFactoryInterface;
use Drupal\lark\Service\MetaOptionManager;
use Drupal\lark\Service\Utility\ExportableStatusBuilder;
use Drupal\lark\Service\Utility\TableFormHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EntityDownloadForm extends FormBase {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FileSystemInterface $fileSystem,
    protected ExportableFactoryInterface $exportableFactory,
    protected MetaOptionManager $metaOptionManager,
    protected DownloadController $downloadController,
    protected ExportableStatusBuilder $statusBuilder,
    protected TableFormHandler $tableFormHandler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get(EntityTypeManagerInterface::class),
      $container->get(FileSystemInterface::class),
      $container->get(ExportableFactoryInterface::class),
      $container->get(MetaOptionManager::class),
      DownloadController::create($container),
      $container->get(ExportableStatusBuilder::class),
      $container->get(TableFormHandler::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lark_entity_download_form';
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
        '#value' => $this->t('Download with Dependencies'),
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
    $meta_options_overrides = $this->tableFormHandler->getSubmittedMetaOptionOverrides('export_form_values', $form_state);

    $response = $this->downloadController->downloadExportResponse(
      $form_state->getValue('entity_type_id'),
      (int) $form_state->getValue('entity_id'),
      $meta_options_overrides,
    );

    $form_state->setResponse($response);
  }

}
