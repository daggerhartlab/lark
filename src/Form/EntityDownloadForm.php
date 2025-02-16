<?php

namespace Drupal\lark\Form;

use Drupal\Core\Archiver\ArchiveTar;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\lark\Plugin\Lark\SourceBase;
use Drupal\lark\Service\ExportableFactoryInterface;
use Drupal\lark\Service\MetaOptionManager;
use Drupal\lark\Service\SourceManagerInterface;
use Drupal\lark\Service\Utility\ExportableStatusBuilder;
use Drupal\lark\Service\Utility\TableFormHandler;
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
      $container->get(SourceManagerInterface::class),
      $container->get(MetaOptionManager::class),
      FileDownloadController::create($container),
      $container->get(ExportableStatusBuilder::class),
      $container->get(TableFormHandler::class)
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

    // Make a source that acts in place of a filesystem source.
    $source = SourceBase::create(\Drupal::getContainer(),  [], 'download', [
      'label' => 'Download',
      // Write to the /tmp directory as needed during export.
      'directory' => $this->fileSystem->getTempDirectory(),
    ]);

    $exportables = $this->exportableFactory->getEntityExportables(
      $form_state->getValue('entity_type_id'),
      (int) $form_state->getValue('entity_id'),
      $source,
      $meta_options_overrides,
    );

    //dd($exportables);

    try {
      $this->fileSystem->delete($source->directory() . '/lark-export.tar.gz');
    }
    catch (FileException $e) {
      // Ignore failed deletes.
    }

    $archiver = new ArchiveTar($source->directory() . '/lark-export.tar.gz', 'gz');

    // Add all contents of the export storage to the archive.
    foreach ($exportables as $exportable) {
      $filename = str_replace($source->directory(), '', $exportable->getExportFilepath());
      $archiver->addString($filename, Yaml::encode($exportable->toArray()));

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
