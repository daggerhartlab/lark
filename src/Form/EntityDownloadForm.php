<?php

namespace Drupal\lark\Form;

use Drupal\Core\Archiver\ArchiveTar;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\lark\Service\ExportableFactoryInterface;
use Drupal\lark\Service\MetaOptionManager;
use Drupal\lark\Service\SourceManagerInterface;
use Drupal\system\FileDownloadController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

class EntityDownloadForm extends FormBase {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FileSystemInterface $fileSystem,
    protected ExportableFactoryInterface $exportableFactory,
    protected SourceManagerInterface $sourceManager,
    protected MetaOptionManager $metaOptionManager,
    protected FileDownloadController $fileDownloadController,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get(EntityTypeManagerInterface::class),
      $container->get(FileSystemInterface::class),
      $container->get(ExportableFactoryInterface::class),
      $container->get(SourceManagerInterface::class),
      $container->get(MetaOptionManager::class),
      FileDownloadController::create($container),
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

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $exportables = $this->exportableFactory->getEntityExportables(
      $form_state->getValue('entity_type_id'),
      (int) $form_state->getValue('entity_id'), NULL, [],
    );

    try {
      $this->fileSystem->delete($this->fileSystem->getTempDirectory() . '/lark-export.tar.gz');
    }
    catch (FileException $e) {
      // Ignore failed deletes.
    }

    $archiver = new ArchiveTar($this->fileSystem->getTempDirectory() . '/lark-export.tar.gz', 'gz');

    // Add all contents of the export storage to the archive.
    foreach ($exportables as $exportable) {
      $archiver->addString($exportable->getExportFilepath(), Yaml::encode($exportable->toArray()));

      foreach ($this->metaOptionManager->getInstances() as $meta_option) {
        if ($meta_option->applies($exportable->entity())) {
          $meta_option->preExportDownload($archiver, $exportable);
        }
      }
    }

    $request = new Request(['file' => 'lark-export.tar.gz']);
    $response = $this->fileDownloadController->download($request, 'temporary');
    $form_state->setResponse($response);
  }

}
