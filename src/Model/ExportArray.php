<?php

namespace Drupal\lark\Model;

/**
 * Object for array of data exported to yaml.
 */
class ExportArray extends \ArrayObject {

  /**
   * @return string|null
   */
  public function label(): ?string {
    return $this->getMeta('label');
  }

  public function uuid(): string {
    return $this->getMeta('uuid');
  }

  public function entityTypeId(): string {
    return $this->getMeta('entity_type');
  }

  public function bundle(): string {
    return $this->getMeta('bundle');
  }

  public function path(): string {
    return $this->getMeta('path');
  }

  public function defaultLangcode(): string {
    return $this->getMeta('default_langcode');
  }

  public function dependencies(): array {
    return $this->getMeta('depends', []);
  }

  public function content(string $langcode = NULL): array {
    if (!\is_string($langcode)) {
      $langcode = 'default';
    }

    // The default langcode content is stored in the 'default' key.
    if ($langcode === $this->defaultLangcode()) {
      $langcode = 'default';
    }

    if (\isset($this[$langcode])) {
      return $this[$langcode];
    }

    return $this->translation($langcode);
  }

  public function translations(): array {
    return \isset($this['translations']) ? $this['translations'] : [];
  }

  public function translation(string $langcode = NULL): array {
    if (\isset($this['translations'][$langcode])) {
      return $this['translations'][$langcode];
    }

    return [];
  }

  public function hasMeta(string $name): bool {
    return \array_key_exists($name, $this['_meta']);
  }

  public function getMeta(string $name, mixed $default_value = NULL): mixed {
    return $this->hasMeta($name) ? $this['_meta'][$name] : $default_value;
  }

  public function setMeta(string $name, $value): void {
    $this['_meta'][$name] = $value;
  }

  public function metaOptions(): array {
    return $this->getMeta('options', []);
  }

  public function hasMetaOption(string $name): bool {
    return \array_key_exists($name, $this->metaOptions());
  }

  public function getMetaOption(string $name, $default_value = NULL) {
    return $this->hasMetaOption($name) ? $this->metaOptions()[$name] : $default_value;
  }

  public function setMetaOption(string $name, $value): void {
    $this['_meta']['options'][$name] = $value;
  }

}
