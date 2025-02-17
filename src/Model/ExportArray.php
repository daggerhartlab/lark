<?php

namespace Drupal\lark\Model;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Object for array of data exported to yaml.
 */
class ExportArray extends \ArrayObject {

  const SCHEMA = [
    '_meta' => [
      'entity_type' => '',
      'bundle' => '',
      'entity_id' => '',
      'label' => '',
      'path' => '',
      'uuid' => '',
      'default_langcode' => '',
      // Array of UUID : entity_type_id pairs.
      'depends' => [],
      'options' => [],
    ],
    // Content fields for the default translation.
    'default' => [],
    // Content fields for additional translations, keyed by langcode.
    'translations' => [],
  ];

  /**
   * @param object|array $array
   *   Array should follow the ::SCHEMA array.
   * @param int $flags
   * @param string $iteratorClass
   */
  public function __construct(object|array $array = [], int $flags = 0, string $iteratorClass = "ArrayIterator") {
    $array = array_replace_recursive(static::SCHEMA, $array);

    parent::__construct($array, $flags, $iteratorClass);
  }

  public static function createFromEntity(ContentEntityInterface $entity): static {
    $default = $entity->getTranslation(\Drupal::languageManager()->getDefaultLanguage()->getId());
    $export = new static();
    $export->setEntityTypeId($entity->getEntityTypeId());
    $export->setBundle($entity->bundle());
    $export->setEntityId($entity->id());
    $export->setLabel($entity->label());
    $export->setUuid($export->uuid());
    $export->setDefaultLangcode($default->language()->getId());
    return $export;
  }

  public function entityTypeId(): string {
    return $this->getMeta('entity_type');
  }

  public function setEntityTypeId(string $entity_type_id): void {
    $this->setMeta('entity_type', $entity_type_id);
  }

  public function bundle(): string {
    return $this->getMeta('bundle');
  }

  public function setBundle(string $bundle): void {
    $this->setMeta('bundle', $bundle);
  }

  public function entityId(): string|int {
    return $this->getMeta('entity_id', '0');
  }

  public function setEntityId(string|int $entity_id): void {
    $this->setMeta('entity_id', (string) $entity_id);
  }

  public function label(): string {
    return $this->getMeta('label');
  }

  public function setLabel(string $label): void {
    $this->setMeta('label', $label);
  }

  public function uuid(): string {
    return $this->getMeta('uuid');
  }

  public function setUuid(string $uuid): void {
    $this->setMeta('uuid', $uuid);
  }

  public function path(): string {
    return $this->getMeta('path');
  }

  public function setPath(string $path): void {
    $this->setMeta('path', $path);
  }

  public function defaultLangcode(): string {
    return $this->getMeta('default_langcode');
  }

  public function setDefaultLangcode(string $langcode): void {
    $this->setMeta('default_langcode', $langcode);
  }

  public function dependencies(): array {
    return $this->getMeta('depends', []);
  }

  public function hasDependency(string $uuid): bool {
    return array_key_exists($uuid, $this->dependencies());
  }

  public function setDependencies(array $dependencies): void {
    $this->setMeta('depends', $dependencies);
  }

  public function options(): array {
    return $this->getMeta('options', []);
  }

  public function setOptions(array $options): void {
    $this['_meta']['options'] = $options;
  }

  public function content(): array {
    return $this['default'] ?? [];
  }

  public function setContent(array $data): void {
    $this['default'] = $data;
  }

  public function getField(string $field_name, string $langcode = 'default') {
    if ($langcode === $this->defaultLangcode()) {
      $langcode = 'default';
    }

    return $langcode === 'default' ?
      $this['default'][$field_name] :
      ($this['translations'][$langcode][$field_name] ?? NULL);
  }

  public function setField(string $field_name, $value, string $langcode = 'default'): void {
    if ($langcode === $this->defaultLangcode()) {
      $langcode = 'default';
    }

    if ($langcode === 'default') {
      $this['default'][$field_name] = $value;
    }
  }

  public function translations(): array {
    return $this['translations'] ?? [];
  }

  public function translation(string $langcode = NULL): array {
    return $this['translations'][$langcode] ?? [];
  }

  public function setTranslation(string $langcode, array $data): void {
    $this['translations'][$langcode] = $data;
  }

  public function unsetTranslation(string $langcode): void {
    \unset($this['translations'][$langcode]);
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

  public function hasOption(string $name): bool {
    return \array_key_exists($name, $this->options());
  }

  public function getOption(string $name, $default_value = NULL) {
    return $this->hasOption($name) ? $this->options()[$name] : $default_value;
  }

  public function setOption(string $name, $value): void {
    $this['_meta']['options'][$name] = $value;
  }

  public function unsetOption(string $name): void {
    \unset($this['_meta']['options'][$name]);
  }

  /**
   * Provide a clean array for this object.
   *
   * @return array
   */
  public function cleanArray(): array {
    if (!$this->options()) {
      unset($this['_meta']['options']);
    }

    if (!$this->translations()) {
      unset($this['translations']);
    }

    return (array) $this;
  }

}
