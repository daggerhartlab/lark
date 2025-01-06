<?php

declare(strict_types=1);

namespace Drupal\lark\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
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
  ) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get(SourceManagerInterface::class)
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
    return ['lark.settings'];
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
      '#default_value' => $this->config('lark.settings')->get('default_source'),
      '#options' => $this->sourceManager->getOptions(),
    ];
    $form['ignored_comparison_keys'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Ignored Comparison Keys'),
      '#description' => $this->t('Provide a list of keys to ignore when determining their export status. Separate each key with a new line. This can be useful for mitigating false positives, such as the "changed" key on entities.'),
      '#default_value' => $this->config('lark.settings')->get('ignored_comparison_keys'),
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
    $this->config('lark.settings')
      ->set('default_source', $form_state->getValue('default_source'))
      ->set('ignored_comparison_keys', $form_state->getValue('ignored_comparison_keys'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
