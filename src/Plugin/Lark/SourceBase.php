<?php

namespace Drupal\lark\Plugin\Lark;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Extension\Exception\UnknownExtensionException;
use Drupal\Core\Extension\ExtensionPathResolver;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SourceBase extends PluginBase implements LarkSourceInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ExtensionPathResolver $extensionPathResolver,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('extension.path.resolver'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return $this->getPluginId();
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    // The title from YAML file discovery may be a TranslatableMarkup object.
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function directory(): string {
    return $this->pluginDefinition['directory'];
  }

  /**
   * {@inheritdoc}
   */
  public function directoryProcessed(bool $absolute = TRUE): string {
    $directory = $this->pluginDefinition['directory'];
    $directory = preg_replace_callback('/\[\w+]/', function(array $matches) {
      $name = str_replace(['[', ']'], '', $matches[0]);
      try {
        $path = $this->extensionPathResolver->getPath('module', $name);
      }
      catch (UnknownExtensionException $exception) {
        $path = $this->extensionPathResolver->getPath('theme', $name);
      }

      return $path;
    }, $directory);

    if ($absolute && !str_starts_with($directory, DIRECTORY_SEPARATOR)) {
      $path = DRUPAL_ROOT . DIRECTORY_SEPARATOR . $directory;
      if (\file_exists($path)) {
        return \realpath($path);
      }

      return $path;
    }

    return $directory;
  }

  /**
   * {@inheritdoc}
   */
  public function provider(): string {
    return $this->pluginDefinition['provider'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDestinationDirectory(string $entity_type_id, string $bundle, bool $absolute_path = FALSE): string {
    return implode(DIRECTORY_SEPARATOR, [
      $this->directoryProcessed($absolute_path),
      $entity_type_id,
      $bundle,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDestinationFilepath(string $entity_type_id, string $bundle, string $filename, bool $absolute_path = FALSE): string {
    return implode(DIRECTORY_SEPARATOR, [
      $this->getDestinationDirectory($entity_type_id, $bundle, $absolute_path),
      $filename,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function exportExistsInSource(string $entity_type_id, string $bundle, string $uuid): bool {
    return \file_exists(implode(DIRECTORY_SEPARATOR, [
      $this->getDestinationDirectory($entity_type_id, $bundle, TRUE),
      "{$uuid}.yml"
    ]));
  }

}
