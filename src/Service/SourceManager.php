<?php

declare(strict_types=1);

namespace Drupal\lark\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Discovery\YamlDiscovery;
use Drupal\Core\Plugin\Factory\ContainerFactory;
use Drupal\lark\Exception\LarkDefaultSourceNotFound;
use Drupal\lark\Model\LarkSettings;
use Drupal\lark\Plugin\Lark\Source\DefaultSource;
use Drupal\lark\Plugin\Lark\SourceInterface;

/**
 * Defines a plugin manager to deal with lark_sources.
 *
 * Modules can define lark_sources in a MODULE_NAME.lark_sources.yml file contained
 * in the module's base directory. Each lark_source has the following structure:
 *
 * @code
 *   MACHINE_NAME:
 *     label: STRING
 *     description: STRING
 * @endcode
 *
 * @see \Drupal\lark\Plugin\Lark\Source\DefaultSource
 * @see \Drupal\lark\Plugin\Lark\SourceInterface
 */
class SourceManager extends DefaultPluginManager implements SourceManagerInterface {

  /**
   * Plugin instances.
   *
   * @var \Drupal\lark\Plugin\Lark\SourceInterface[]
   */
  protected array $instances = [];

  protected ThemeHandlerInterface $themeHandler;

  /**
   * {@inheritdoc}
   */
  protected $defaults = [
    // The lark_source id. Set by the plugin system based on the top-level YAML key.
    'id' => '',
    // The lark_source label.
    'label' => '',
    // The lark_source directory.
    'directory' => '',
    // Default plugin class.
    'class' => DefaultSource::class,
  ];

  /**
   * Constructs LarkSourcePluginManager object.
   */
  public function __construct(
    ModuleHandlerInterface $module_handler,
    ThemeHandlerInterface $theme_handler,
    CacheBackendInterface $cache_backend,
    protected ConfigFactoryInterface $configFactory,
    protected LarkSettings $settings,
  ) {
    $this->factory = new ContainerFactory($this);
    $this->moduleHandler = $module_handler;
    $this->themeHandler = $theme_handler;
    $this->alterInfo('lark_source_info');
    $this->setCacheBackend($cache_backend, 'lark_source_plugins');
  }

  /**
   * {@inheritdoc}
   */
  protected function getDiscovery(): YamlDiscovery {
    if (!isset($this->discovery)) {
      $directories = $this->moduleHandler->getModuleDirectories() + $this->themeHandler->getThemeDirectories();
      $this->discovery = new YamlDiscovery('lark_sources', $directories);
      $this->discovery->addTranslatableProperty('label', 'lark_source_label');
    }
    return $this->discovery;
  }

  /**
   * {@inheritdoc}
   */
  public function getInstances(): array {
    if (!empty($this->instances)) {
      return $this->instances;
    }

    $this->instances = [];

    foreach ($this->getDefinitions() as $key => $definition) {
      $this->instances[$key] = $this->createInstance($definition['id']);
    }

    return $this->instances;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSource(): SourceInterface {
    if (!$this->settings->defaultSource()) {
      $default_source_id = key($this->getDefinitions());
      if (!$default_source_id) {
        throw new LarkDefaultSourceNotFound('No default source plugin defined.');
      }
    }

    return $this->getSourceInstance($this->settings->defaultSource());
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions(): array {
    $options = [];
    foreach ($this->getDefinitions() as $source_id => $source) {
      $options[$source_id] = "({$source['provider']}) " . $source['label'];
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceInstance(string $source_plugin_id): SourceInterface {
    return $this->getInstances()[$source_plugin_id];
  }

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\lark\Plugin\Lark\SourceInterface
   *   The plugin instance.
   */
  public function createInstance($plugin_id, array $configuration = []) {
    /** @var \Drupal\lark\Plugin\Lark\SourceInterface $plugin */
    $plugin = parent::createInstance($plugin_id, $configuration);
    return $plugin;
  }

}
