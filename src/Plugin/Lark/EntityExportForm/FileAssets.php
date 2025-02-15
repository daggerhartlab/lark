<?php

declare(strict_types=1);

namespace Drupal\lark\Plugin\Lark\EntityExportForm;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileInterface;
use Drupal\lark\Attribute\LarkEntityExportForm;
use Drupal\lark\Model\ExportableInterface;
use Drupal\lark\Plugin\Lark\EntityExportFormPluginBase;
use Drupal\lark\Service\AssetFileManager;

/**
 * Plugin implementation of the lark_entity_export_form.
 */
#[LarkEntityExportForm(
  id: "file_assets",
  label: new TranslatableMarkup("File Assets"),
  description: new TranslatableMarkup("Allows users to choose how file assets should be handled during export and import."),
)]
final class FileAssets extends EntityExportFormPluginBase {

  protected ?AssetFileManager $assetFileManager = NULL;

  /**
   * {@inheritdoc}
   */
  public function applies(EntityInterface $entity): bool {
    return $entity instanceof FileInterface;
  }

  private function assetFileManager(): AssetFileManager {
    if (!$this->assetFileManager) {
      $this->assetFileManager = \Drupal::service(AssetFileManager::class);
    }

    return $this->assetFileManager;
  }

  /**
   * {@inheritdoc}
   */
  public function buildElement(ExportableInterface $exportable, array &$form, FormStateInterface $form_state, array $render_parents): array {
    /** @var FileInterface $file */
    $file = $exportable->entity();
    $uuid = $file->uuid();

    if (!($file instanceof FileInterface)) {
      return [];
    }

    // Whether asset is exported.
    $is_exported_msg = $this->t('Asset not exported.');
    if ($exportable->getExportFilepath()) {
      $destination = dirname($exportable->getExportFilepath());
      if ($this->assetFileManager()->assetIsExported($file, $destination)) {
        $path = $destination . DIRECTORY_SEPARATOR . $this->assetFileManager()->assetExportFilename($file);
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

    $meta_option = $exportable->hasMetaOption($this->id()) ? $exportable->getMetaOption($this->id()) : [];

    // Should export.
    $should_export_value = $this->larkSettings->shouldExportAssets();
    if (isset($meta_option['should_export'])) {
      $should_export_value = $meta_option['should_export'];
    }

    $element['should_export'] = $this->fixNestedRadios($form, $render_parents, 'should_export', [
      '#type' => 'radios',
      '#title' => $this->t('Asset Export'),
      '#default_value' => (int) $should_export_value,
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
  public function processValues(array $submitted_values, string $uuid, FormStateInterface $form_state): array {
    $values = [];

    if ((bool) $submitted_values['should_export'] !== $this->larkSettings->shouldExportAssets()) {
      $values['should_export'] = (bool) $submitted_values['should_export'];
    }
    if ((bool) $submitted_values['should_import'] !== $this->larkSettings->shouldImportAssets()) {
      $values['should_import'] = (bool) $submitted_values['should_import'];
    }

    return $values;
  }

}
