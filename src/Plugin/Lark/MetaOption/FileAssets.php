<?php

declare(strict_types=1);

namespace Drupal\lark\Plugin\Lark\MetaOption;

use Drupal\Core\Archiver\ArchiveTar;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileInterface;
use Drupal\lark\Attribute\LarkMetaOption;
use Drupal\lark\Model\ExportableInterface;
use Drupal\lark\Model\ExportArray;
use Drupal\lark\Plugin\Lark\MetaOptionBase;

/**
 * Plugin implementation of the lark_entity_export_form.
 */
#[LarkMetaOption(
  id: "file_assets",
  label: new TranslatableMarkup("File Assets"),
  description: new TranslatableMarkup("Allows users to choose how file assets should be handled during export and import."),
)]
final class FileAssets extends MetaOptionBase {

  /**
   * {@inheritdoc}
   */
  public function applies(EntityInterface $entity): bool {
    return $entity instanceof FileInterface;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(ExportableInterface $exportable, array &$form, FormStateInterface $form_state, array $render_parents): array {
    /** @var FileInterface $file */
    $file = $exportable->entity();
    $uuid = $file->uuid();

    // Whether asset is exported.
    $is_exported_msg = $this->t('Asset not exported.');
    if ($exportable->getFilepath()) {
      $destination = dirname($exportable->getFilepath());
      if ($this->assetFileManager->assetIsExported($file, $destination)) {
        $path = $destination . DIRECTORY_SEPARATOR . $this->assetFileManager->assetExportFilename($file);
        $is_exported_msg = $this->t('Asset exported: @path', ['@path' => $path]);
      }
    }

    $render_parents[] = $uuid;
    $render_parents[] = $this->id();
    $element = [
      '#type' => 'container',
      '#attributes' => ['class' => ['lark-asset-details-container']],
      'is_exported' => [
        '#type' => 'container',
        'exists' => [
          '#markup' => "<p><em>$is_exported_msg</em></p>",
        ],
      ],
    ];

    $meta_option = $exportable->hasOption($this->id()) ? $exportable->getOption($this->id()) : [];

    // Should export.
    $should_export_value = $this->larkSettings->shouldExportAssets();
    if (isset($meta_option['should_export'])) {
      $should_export_value = $meta_option['should_export'];
    }

    // Disable changing values on the import form.
    $disabled = str_contains($form_state->getFormObject()->getFormId(), '_import_');
    $element['should_export'] = $this->fixNestedRadios($form, $render_parents, 'should_export', [
      '#type' => 'radios',
      '#title' => $this->t('Asset Export'),
      '#default_value' => (int) $should_export_value,
      '#disabled' => $disabled,
      '#options' => [
        0 => $this->t('(@default_desc) Do not export', [
          '@default_desc' => $this->larkSettings->shouldExportAssets() === FALSE ?
            $this->t('Default') :
            $this->t('Override')
        ]),
        1 => $this->t('(@default_desc) Export this asset along with the File entity', [
          '@default_desc' => $this->larkSettings->shouldExportAssets() === TRUE ?
            $this->t('Default') :
            $this->t('Override')
        ]),
      ],
    ]);

    // Should import.
    $should_import_value = $this->larkSettings->shouldImportAssets();
    if (isset($meta_option['should_import'])) {
      $should_import_value = $meta_option['should_export'];
    }

    $element['should_import'] = $this->fixNestedRadios($form, $render_parents, 'should_import', [
      '#type' => 'radios',
      '#title' => $this->t('Asset Import'),
      '#description' => $this->t('HEREHRE'),
      '#default_value' => (int) $should_import_value,
      '#disabled' => $disabled,
      '#options' => [
        '0' => $this->t('(@default_desc) Do not import', [
          '@default_desc' => $this->larkSettings->shouldImportAssets() === FALSE ? $this->t('Default') : $this->t('Override')
        ]),
        '1' => $this->t('(@default_desc) Import this asset along with the File entity', [
          '@default_desc' => $this->larkSettings->shouldImportAssets() === TRUE ? $this->t('Default') : $this->t('Override')
        ]),
      ],
    ]);

    // Thumbnail.
    if (str_starts_with($file->getMimeType(), 'image/') && $file->getSize() <= 2048000) {
      $element['thumbnail'] = [
        '#theme' => 'image',
        '#uri' => $file->createFileUrl(FALSE),
        '#title' => $file->label(),
        '#attributes' => ['class' => ['lark-asset-thumbnail-image']],
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function processFormValues(array $submitted_values, ExportableInterface $exportable, FormStateInterface $form_state): array {
    $values = [];

    if ((bool) $submitted_values['should_export'] !== $this->larkSettings->shouldExportAssets()) {
      $values['should_export'] = (bool) $submitted_values['should_export'];
    }
    if ((bool) $submitted_values['should_import'] !== $this->larkSettings->shouldImportAssets()) {
      $values['should_import'] = (bool) $submitted_values['should_import'];
    }

    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function preExportDownload(ArchiveTar $archive, ExportableInterface $exportable): void {
    // If it's a file, export the file alongside the yaml.
    /** @var FileInterface $entity */
    $entity = $exportable->entity();
    // Default to settings. Then, if an export override exists let it make the
    // decision about exporting.
    $should_export = $this->larkSettings->shouldExportAssets();
    $export_override = $exportable->getOption($this->id())['should_export'] ?? NULL;
    $export_override_exists = !is_null($exportable);
    if ($export_override_exists) {
      $should_export = (bool) $export_override;
    }

    if ($should_export) {
      $asset_archive_path = $this->assetFileManager->exportAsset($entity, \dirname($exportable->getFilepath()));
      $archive->addModify([$asset_archive_path], '', $exportable->getSource()->directoryProcessed());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preExportWrite(ExportableInterface $exportable): void {
    // If it's a file, export the file alongside the yaml.
    /** @var FileInterface $entity */
    $entity = $exportable->entity();

    // Default to settings. Then, if an export override exists let it make the
    // decision about exporting.
    $should_export = $this->larkSettings->shouldExportAssets();
    $export_override = $exportable->getOption($this->id())['should_export'] ?? NULL;
    $export_override_exists = !is_null($exportable);
    if ($export_override_exists) {
      $should_export = (bool) $export_override;
    }

    if ($should_export) {
      $this->assetFileManager->exportAsset($entity, \dirname($exportable->getFilepath()));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preImportSave(ContentEntityInterface $entity, ExportArray $export): void {
    /** @var \Drupal\file\FileInterface $entity */

    // Default to settings. Then, if an import override exists let it make
    // the decision about importing.
    $file_assets = $export->getOption($this->id());
    $should_import = $this->larkSettings->shouldImportAssets();
    $import_override_exists = isset($file_assets['should_import']);
    if ($import_override_exists) {
      $should_import = (bool) $file_assets['should_import'];
    }

    if ($should_import) {
      $this->assetFileManager->importAsset(
        $entity,
        dirname($export->path()),
        $export->fields('default')['uri'][0]['value']
      );
    }
  }

}
