<?php

namespace Drupal\lark\Model;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\lark\Service\Exporter;
use Drupal\lark\Service\Utility\EntityUtility;

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
   * Functionally the constructor is the '::createFromArray()' method.
   *
   * @param object|array $array
   *   Array should follow the ::SCHEMA array.
   * @param int $flags
   *   Flags to control the behaviour of the ArrayObject object.
   * @param string $iteratorClass
   *   Specify the class that will be used for iteration of the ArrayObject.
   */
  public function __construct(object|array $array = [], int $flags = 0, string $iteratorClass = "ArrayIterator") {
    $array = array_replace_recursive(static::SCHEMA, $array);

    parent::__construct($array, $flags, $iteratorClass);
  }

  /**
   * Create an ExportArray from a content entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity.
   *
   * @return static
   */
  public static function createFromEntity(ContentEntityInterface $entity): static {
    $default_translation = $entity->getTranslation(\Drupal::languageManager()->getDefaultLanguage()->getId());
    $export = new static();
    $dependencies = EntityUtility::getEntityExportDependencies($entity);

    $export
      ->setEntityTypeId($entity->getEntityTypeId())
      ->setBundle($entity->bundle())
      ->setEntityId($entity->id())
      ->setLabel($entity->label())
      // ->setPath('') // Can't know the path yet.
      ->setUuid($entity->uuid())
      ->setDefaultLangcode($default_translation->language()->getId())
      ->setDependencies($dependencies)
      // ->setOptions([]) // Can't know about meta options yet.
      ->setFields(EntityUtility::getEntityArray($default_translation));

    foreach ($entity->getTranslationLanguages(FALSE) as $langcode => $language) {
      $export->setFields(EntityUtility::getEntityArray($entity->getTranslation($langcode)), $langcode);
    }

    return $export;
  }

  public function isEmpty(): bool {
    return ((array) $this === static::SCHEMA);
  }

  public function entityTypeId(): string {
    return $this->getMeta('entity_type');
  }

  public function setEntityTypeId(string $entity_type_id): self {
    $this->setMeta('entity_type', $entity_type_id);
    return $this;
  }

  public function bundle(): string {
    return $this->getMeta('bundle');
  }

  public function setBundle(string $bundle): self {
    $this->setMeta('bundle', $bundle);
    return $this;
  }

  public function entityId(): string|int {
    return $this->getMeta('entity_id', '0');
  }

  public function setEntityId(string|int $entity_id): self {
    $this->setMeta('entity_id', (string) $entity_id);
    return $this;
  }

  public function label(): string {
    return $this->getMeta('label');
  }

  public function setLabel(string $label): self {
    $this->setMeta('label', $label);
    return $this;
  }

  public function uuid(): string {
    return $this->getMeta('uuid');
  }

  public function setUuid(string $uuid): self {
    $this->setMeta('uuid', $uuid);
    return $this;
  }

  public function path(): string {
    return $this->getMeta('path');
  }

  public function setPath(string $path): self {
    $this->setMeta('path', $path);
    return $this;
  }

  public function defaultLangcode(): string {
    return $this->getMeta('default_langcode');
  }

  public function setDefaultLangcode(string $langcode): self {
    $this->setMeta('default_langcode', $langcode);
    return $this;
  }

  public function dependencies(): array {
    return $this->getMeta('depends', []);
  }

  public function hasDependency(string $uuid): bool {
    return array_key_exists($uuid, $this->dependencies());
  }

  public function setDependencies(array $dependencies): self {
    $this->setMeta('depends', $dependencies);
    return $this;
  }

  public function options(): array {
    return $this->getMeta('options', []);
  }

  public function setOptions(array $options): self {
    $this['_meta']['options'] = $options;
    return $this;
  }

  public function translations(): array {
    return $this['translations'] ?? [];
  }

  public function unsetTranslation(string $langcode): void {
    \unset($this['translations'][$langcode]);
  }

  public function fields(string $langcode = 'default'): array {
    if ($langcode === 'default' || $langcode === $this->defaultLangcode()) {
      return $this['default'] ?? [];
    }

    return $this['translations'][$langcode] ?? [];
  }

  public function setFields(array $fields, string $langcode = 'default'): self {
    if ($langcode === 'default' || $langcode === $this->defaultLangcode()) {
      $this['default'] = $fields;
      return $this;
    }

    $this['translations'][$langcode] = $fields;
    return $this;
  }

  public function getField(string $field_name, string $langcode = 'default') {
    if ($langcode === 'default' || $langcode === $this->defaultLangcode()) {
      return $this['default'][$field_name];
    }

    return $this['translations'][$langcode][$field_name] ?? NULL;
  }

  public function setField(string $field_name, $value, string $langcode = 'default'): self {
    if ($langcode === 'default' || $langcode === $this->defaultLangcode()) {
      $this['default'][$field_name] = $value;
      return $this;
    }

    $this['translations'][$langcode][$field_name] = $value;
    return $this;
  }

  public function hasMeta(string $name): bool {
    return \array_key_exists($name, $this['_meta']);
  }

  public function getMeta(string $name, mixed $default_value = NULL): mixed {
    return $this->hasMeta($name) ? $this['_meta'][$name] : $default_value;
  }

  public function setMeta(string $name, $value): self {
    $this['_meta'][$name] = $value;
    return $this;
  }

  public function hasOption(string $name): bool {
    return \array_key_exists($name, $this->options());
  }

  public function getOption(string $name, $default_value = NULL) {
    return $this->hasOption($name) ? $this->options()[$name] : $default_value;
  }

  public function setOption(string $name, $value): self {
    $this['_meta']['options'][$name] = $value;
    return $this;
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
