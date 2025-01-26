<?php

namespace Drupal\lark\Model;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\File\FileExists;

class LarkSettings {

  const NAME = 'lark.settings';

  /**
   * @param \Drupal\Core\Config\ImmutableConfig $settings
   */
  public function __construct(
    protected ImmutableConfig $settings,
  ) {}

  /**
   * Factory.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *
   * @return static
   */
  public static function create(ConfigFactoryInterface $configFactory) {
    return new static($configFactory->get(static::NAME));
  }

  /**
   * Get config object.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   Config object.
   */
  public function config(): ImmutableConfig {
    return $this->settings;
  }

  /**
   * Default source id.
   *
   * @return string
   *   Default source id.
   */
  public function defaultSource(): string {
    return $this->settings->get('default_source') ?? '';
  }

  /**
   * Raw ignored_comparison_keys value.
   *
   * @return string
   */
  public function ignoredComparisonKeys(): string {
    return $this->settings->get('ignored_comparison_keys') ?? '';
  }

  /**
   * Get ignored_comparison_keys as an array.
   *
   * @return array
   */
  public function ignoredComparisonKeysArray(): array {
    $ignored_keys = array_filter(preg_split("/[\r\n]+/", $this->ignoredComparisonKeys()));
    array_walk($ignored_keys, 'trim');

    // Ignore 'original_values' key added by the EntityReferenceUuidHandler.
    // @todo - convert to hook.
    $ignored_keys[] = 'original_values';
    return $ignored_keys;
  }

  /**
   * Whether assets should be exported.
   *
   * @return bool
   *   Whether assets should be exported.
   */
  public function shouldExportAssets(): bool {
    return (bool) $this->settings->get('should_export_assets') ?? FALSE;
  }

  /**
   * Whether assets should be imported.
   *
   * @return bool
   *   Whether assets should be imported.
   */
  public function shouldImportAssets(): bool {
    return (bool) $this->settings->get('should_import_assets') ?? FALSE;
  }

  /**
   * Get enum value for FileExists based on Drupal config.
   *
   * @return \Drupal\Core\File\FileExists
   */
  public function assetExportFileExists(): FileExists {
    $name = $this->settings->get('asset_export_file_exists') ?? FileExists::Replace->name;
    return $this->fileExistsEnumByName($name);
  }

  /**
   * Get enum value for FileExists based on Drupal config.
   *
   * @return \Drupal\Core\File\FileExists
   */
  public function assetImportFileExists(): FileExists {
    $name = $this->settings->get('asset_import_file_exists') ?? FileExists::Replace->name;
    return $this->fileExistsEnumByName($name);
  }

  /**
   * Get the FileExists enum value for the given value name.
   *
   * @param string $value_name
   *
   * @return \Drupal\Core\File\FileExists
   *   FileExists enum value.
   */
  private function fileExistsEnumByName(string $value_name): FileExists {
    foreach (FileExists::cases() as $value) {
      if ($value->name === $value_name) {
        return $value;
      }
    }

    return FileExists::Error;
  }

}
