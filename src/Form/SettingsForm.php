<?php

declare(strict_types=1);

namespace Drupal\lark\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\lark\Model\LarkSettings;
use Drupal\lark\Service\SourceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Lark settings for this site.
 */
final class SettingsForm extends ConfigFormBase {

  /**
   * The Lark source plugin manager.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param $typedConfigManager
   *   The typed config manager.
   * @param \Drupal\lark\Service\SourceManagerInterface $sourceManager
   *   The Lark source plugin manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    protected SourceManagerInterface $sourceManager,
    protected LarkSettings $settings,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get(SourceManagerInterface::class),
      $container->get(LarkSettings::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'lark_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [LarkSettings::NAME];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['default_source'] = [
      '#type' => 'select',
      '#title' => $this->t('Default Source'),
      '#description' => $this->t('The default source plugin to use for Lark.'),
      '#required' => TRUE,
      '#default_value' => $this->settings->defaultSource(),
      '#options' => $this->sourceManager->getOptions(),
    ];
    $form['ignored_comparison_keys'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Ignored Comparison Keys'),
      '#description' => $this->t('Provide a list of keys to ignore when determining their export status. Separate each key with a new line. This can be useful for mitigating false positives, such as the "changed" key on entities.'),
      '#default_value' => $this->settings->ignoredComparisonKeys(),
    ];

    $form['asset_management_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Asset Management Settings'),
      '#description' => $this->t('Each File entity in Drupal is associated with an another file asset, such as a jpg, pdf, xml. These settings provide some control over how assets are handled when importing/exporting File entities.'),
      '#open' => TRUE,
      'export_heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Asset Export Settings')
      ],
      'should_export_assets' => [
        '#type' => 'radios',
        '#title' => $this->t('Export asset action'),
        '#description' => $this->t('Choose the default action that should be taken when exporting an asset attached to a File entity.'),
        '#default_value' => (int) $this->settings->shouldExportAssets(),
        '#options' => [
          0 => $this->t('Do not export assets attached to File entities by default.'),
          1 => $this->t('Export assets.'),
        ],
      ],
      'asset_export_file_exists' => [
        '#type' => 'radios',
        '#title' => $this->t('Export asset exists resolution action'),
        '#description' => $this->t('When exporting an asset and the asset already exists in the Source, what should happen?'),
        '#default_value' => $this->settings->assetExportFileExists()->name,
        '#options' => [
          FileExists::Error->name => $this->t('Do nothing.'),
          //FileExists::Rename->name => $this->t('Rename the asset being exported and update the export.'),
          FileExists::Replace->name => $this->t('Replace the existing exported asset with the new asset.'),
        ],
      ],
      'import_heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Asset Import Settings')
      ],
      'should_import_assets' => [
        '#type' => 'radios',
        '#title' => $this->t('Import asset action'),
        '#description' => $this->t('Choose the default action that should be taken when importing an asset attached to a File entity.'),
        '#default_value' => (int) $this->settings->shouldImportAssets(),
        '#options' => [
          0 => $this->t('Do not import assets attached to File entities by default.'),
          1 => $this->t('Import assets.'),
        ],
      ],
      'asset_import_file_exists' => [
        '#type' => 'radios',
        '#title' => $this->t('Import asset exists resolution action'),
        '#description' => $this->t('When importing an asset and the asset already exists in the file system, what should happen?'),
        '#default_value' => $this->settings->assetImportFileExists()->name,
        '#options' => [
          FileExists::Error->name => $this->t('Do nothing.'),
          FileExists::Rename->name => $this->t('Rename the asset being imported and update the File entity.'),
          FileExists::Replace->name => $this->t('Replace the existing asset attached to the File entity with the new asset.'),
        ],
      ],
    ];

    $form['sources_list'] = [
      '#type' => 'details',
      '#title' => $this->t('Sources'),
      '#open' => TRUE,
      '#description' => $this->t('List of all export sources.'),
      '#weight' => 101,
      'table' => [
        '#type' => 'table',
        '#title' => $this->t('Sources'),
        '#empty' => $this->t('No sources found.'),
        '#header' => [
          'provider' => $this->t('Provider'),
          'id' => $this->t('Source ID'),
          'label' => $this->t('Label'),
          'directory' => $this->t('Directory'),
        ],
        '#rows' => array_map(function ($source) {
          return [
            'provider' => $source->provider(),
            'id' => $source->id(),
            'label' => $source->label(),
            'directory' => $source->directory(),
          ];
        }, $this->sourceManager->getInstances()),
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config(LarkSettings::NAME)
      ->set('default_source', $form_state->getValue('default_source'))
      ->set('ignored_comparison_keys', $form_state->getValue('ignored_comparison_keys'))
      ->set('should_export_assets', $form_state->getValue('should_export_assets'))
      ->set('asset_export_file_exists', $form_state->getValue('asset_export_file_exists'))
      ->set('should_import_assets', $form_state->getValue('should_import_assets'))
      ->set('asset_import_file_exists', $form_state->getValue('asset_import_file_exists'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
