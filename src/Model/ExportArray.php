<?php

namespace Drupal\lark\Model;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\lark\Service\Utility\EntityUtility;

/**
 * Object for array of data exported to yaml. Ideally all knowledge of the
 * yaml schema is contained in this class.
 */
class ExportArray extends \ArrayObject {

  /**
   * Schema for the export array.
   */
  const SCHEMA = [
    '_meta' => [
      // Entity type ID.
      'entity_type' => '',
      // Bundle.
      'bundle' => '',
      // Exported Entity ID. Only used for debugging.
      'entity_id' => '',
      // Export entity label. Only used for debugging.
      'label' => '',
      // Path to the exported entity.
      'path' => '',
      // UUID of the exported entity.
      'uuid' => '',
      // Default langcode of the exported entity.
      'default_langcode' => '',
      // Array of UUID : entity_type_id pairs.
      'depends' => [],
      // Array of lark options for this export.
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

    /** @var \Drupal\lark\Service\Utility\EntityUtility $entity_utility */
    $entity_utility = \Drupal::service(EntityUtility::class);
    $dependencies = $entity_utility->getEntityExportDependencies($entity);

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
      ->setFields($entity_utility->getEntityArray($default_translation));

    foreach ($entity->getTranslationLanguages(FALSE) as $langcode => $language) {
      $export->setFields($entity_utility->getEntityArray($entity->getTranslation($langcode)), $langcode);
    }

    return $export;
  }

  /**
   * Export array is empty.
   *
   * @return bool
   *   Is empty.
   */
  public function isEmpty(): bool {
    return ((array) $this === static::SCHEMA);
  }

  /**
   * Get all metadata about the export.
   *
   * @return array
   *   All export metadata.
   */
  public function meta(): array {
    return $this->offsetGet('_meta');
  }

  /**
   * Check if a meta key exists.
   *
   * @param string $name
   *   Meta key name.
   */
  public function hasMeta(string $name): bool {
    return \array_key_exists($name, $this->meta());
  }

  /**
   * Get a meta value.
   *
   * @param string $name
   *   Meta key name.
   *
   * @return mixed
   *   Meta value.
   */
  public function getMeta(string $name): mixed {
    return $this->hasMeta($name) ? $this->meta()[$name] : NULL;
  }

  /**
   * Set a meta value.
   *
   * @param string $name
   *   Meta key name.
   * @param mixed $value
   *   Meta value.
   *
   * @return static
   */
  public function setMeta(string $name, $value): self {
    $_meta = $this->meta();
    $_meta[$name] = $value;
    $this->offsetSet('_meta', $_meta);
    return $this;
  }

  /**
   * Unset a meta value that is not part of the schema.
   *
   * @param string $name
   *   Meta key name.
   */
  public function unsetMeta(string $name): void {
    if (\in_array($name, array_keys(static::SCHEMA['_meta']))) {
      return;
    }

    $_meta = $this->meta();
    unset($_meta[$name]);
    $this->offsetSet('_meta', $_meta);
  }

  /**
   * Get the entity type ID.
   *
   * @return string
   *   Entity type ID.
   */
  public function entityTypeId(): string {
    return $this->getMeta('entity_type');
  }

  /**
   * Set the entity type ID.
   *
   * @param string $entity_type_id
   *   Entity type ID.
   *
   * @return static
   */
  public function setEntityTypeId(string $entity_type_id): self {
    $this->setMeta('entity_type', $entity_type_id);
    return $this;
  }

  /**
   * Get the bundle.
   *
   * @return string
   *   Bundle.
   */
  public function bundle(): string {
    return $this->getMeta('bundle');
  }

  /**
   * Set the bundle.
   *
   * @param string $bundle
   *   Bundle.
   *
   * @return static
   */
  public function setBundle(string $bundle): self {
    $this->setMeta('bundle', $bundle);
    return $this;
  }

  /**
   * Get the entity ID.
   *
   * @return string|int
   *   Entity ID.
   */
  public function entityId(): string|int {
    return $this->getMeta('entity_id') ?? '0';
  }

  /**
   * Set the entity ID.
   *
   * @param string|int $entity_id
   *   Entity ID.
   *
   * @return static
   */
  public function setEntityId(string|int $entity_id): self {
    $this->setMeta('entity_id', (string) $entity_id);
    return $this;
  }

  /**
   * Get the label.
   *
   * @return string
   *   Label.
   */
  public function label(): string {
    return $this->getMeta('label');
  }

  /**
   * Set the label.
   *
   * @param string $label
   *   Label.
   *
   * @return static
   */
  public function setLabel(string $label): self {
    $this->setMeta('label', $label);
    return $this;
  }

  /**
   * Get the UUID.
   *
   * @return string
   *   UUID.
   */
  public function uuid(): string {
    return $this->getMeta('uuid');
  }

  /**
   * Set the UUID.
   *
   * @param string $uuid
   *   UUID.
   *
   * @return static
   */
  public function setUuid(string $uuid): self {
    $this->setMeta('uuid', $uuid);
    return $this;
  }

  /**
   * Get the path.
   *
   * @return string
   *   Path.
   */
  public function path(): string {
    return $this->getMeta('path');
  }

  /**
   * Set the path.
   *
   * @param string $path
   *   Path.
   *
   * @return static
   */
  public function setPath(string $path): self {
    $this->setMeta('path', $path);
    return $this;
  }

  /**
   * Get the default langcode.
   *
   * @return string
   *   Default langcode.
   */
  public function defaultLangcode(): string {
    return $this->getMeta('default_langcode');
  }

  /**
   * Set the default langcode.
   *
   * @param string $langcode
   *   Default langcode.
   *
   * @return static
   */
  public function setDefaultLangcode(string $langcode): self {
    $this->setMeta('default_langcode', $langcode);
    return $this;
  }

  /**
   * Get the dependencies.
   *
   * @return array
   *   Dependencies.
   */
  public function dependencies(): array {
    return $this->getMeta('depends',) ?? [];
  }

  /**
   * Check if a dependency exists.
   *
   * @param string $uuid
   *   UUID.
   *
   * @return bool
   *   Exists.
   */
  public function hasDependency(string $uuid): bool {
    return array_key_exists($uuid, $this->dependencies());
  }

  /**
   * Get the entity type ID for a dependency.
   *
   * @param string $uuid
   *   UUID.
   *
   * @return string|null
   *   Entity type ID.
   */
  public function getDependencyEntityTypeId(string $uuid): ?string {
    return $this->dependencies()[$uuid] ?? NULL;
  }

  /**
   * Set the dependencies.
   *
   * @param array $dependencies
   *   Dependencies.
   *
   * @return static
   */
  public function setDependencies(array $dependencies): self {
    $this->setMeta('depends', $dependencies);
    return $this;
  }

  /**
   * Add a dependency.
   *
   * @param string $uuid
   *   UUID.
   * @param string $entity_type_id
   *   Entity type ID.
   *
   * @return static
   */
  public function addDependency(string $uuid, string $entity_type_id): self {
    $dependencies = $this->dependencies();
    $dependencies[$uuid] = $entity_type_id;
    $this->setMeta('depends', $dependencies);
    return $this;
  }

  /**
   * Remove a dependency.
   *
   * @param string $uuid
   *   UUID.
   */
  public function removeDependency(string $uuid): void {
    $dependencies = $this->dependencies();
    unset($dependencies[$uuid]);
    $this->setMeta('depends', $dependencies);
  }

  /**
   * Get the options for this export.
   *
   * @return array
   *   Options.
   */
  public function options(): array {
    return $this->getMeta('options') ?? [];
  }

  /**
   * Set the options for this export.
   *
   * @param array $options
   *   Options.
   *
   * @return static
   */
  public function setOptions(array $options): self {
    $this->setMeta('options', $options);
    return $this;
  }

  /**
   * Check if an option exists.
   *
   * @param string $name
   *   Option name.
   *
   * @return bool
   *   Exists.
   */
  public function hasOption(string $name): bool {
    return \array_key_exists($name, $this->options());
  }

  /**
   * Get an option value.
   *
   * @param string $name
   *   Option name.
   *
   * @return mixed
   *   Option value.
   */
  public function getOption(string $name) {
    return $this->hasOption($name) ? $this->options()[$name] : NULL;
  }

  /**
   * Set an option value.
   *
   * @param string $name
   *   Option name.
   * @param mixed $value
   *   Option value.
   *
   * @return static
   */
  public function setOption(string $name, $value): self {
    $options = $this->options();
    $options[$name] = $value;
    $this->setMeta('options', $options);
    return $this;
  }

  /**
   * Unset an option.
   *
   * @param string $name
   *   Option name.
   */
  public function unsetOption(string $name): void {
    $options = $this->options();
    unset($options[$name]);
    $this->setMeta('options', $options);
  }

  /**
   * Get the fields values for non-defualt translations, keyed by langcode.
   *
   * @return array[]
   *   Translations.
   */
  public function translations(): array {
    return $this->offsetGet('translations') ?: [];
  }

  /**
   * Check if a translation exists.
   *
   * @param string $langcode
   *   Langcode.
   *
   * @return bool
   *   Exists.
   */
  public function unsetTranslation(string $langcode): void {
    $translations = $this->translations();
    \unset($translations[$langcode]);
    $this->offsetSet('translations', $translations);
  }

  /**
   * Get the default translation fields.
   *
   * @return array
   *   Default translation fields.
   */
  protected function default(): array {
    return $this->offsetGet('default') ?: [];
  }

  /**
   * Get all fields values for the given langcode. If no langcode is provided,
   * the default translation fields are returned.
   *
   * @param string $langcode
   *   Langcode.
   *
   * @return array
   *   Fields.
   */
  public function fields(string $langcode = 'default'): array {
    if ($langcode === 'default' || $langcode === $this->defaultLangcode()) {
      return $this->default();
    }

    return $this->translations()[$langcode] ?? [];
  }

  /**
   * Set all field values for the given langcode. If no langcode is provided,
   * the default translation fields are set.
   *
   * @param array $fields
   *   Fields.
   * @param string $langcode
   *   Langcode.
   *
   * @return static
   */
  public function setFields(array $fields, string $langcode = 'default'): self {
    if ($langcode === 'default' || $langcode === $this->defaultLangcode()) {
      $this->offsetSet('default', $fields);
      return $this;
    }

    $translations = $this->translations();
    $translations[$langcode] = $fields;
    $this->offsetSet('translations', $translations);
    return $this;
  }

  /**
   * Get a field value for the given langcode. If no langcode is provided,
   * the default translation field is returned.
   *
   * @param string $field_name
   *   Field name.
   * @param string $langcode
   *   Langcode.
   *
   * @return mixed
   *   Field value.
   */
  public function getField(string $field_name, string $langcode = 'default') {
    if ($langcode === 'default' || $langcode === $this->defaultLangcode()) {
      return $this->fields()[$field_name] ?? NULL;
    }

    return $this->translations()[$langcode][$field_name] ?? NULL;
  }

  /**
   * Set a field value for the given langcode. If no langcode is provided,
   * the default translation field is set.
   *
   * @param string $field_name
   *   Field name.
   * @param mixed $value
   *   Field value.
   * @param string $langcode
   *   Langcode.
   *
   * @return static
   */
  public function setField(string $field_name, $value, string $langcode = 'default'): self {
    if ($langcode === 'default' || $langcode === $this->defaultLangcode()) {
      $default = $this->fields();
      $default[$field_name] = $value;
      $this->offsetSet('default', $default);
      return $this;
    }

    $translations = $this->translations();
    $translations[$langcode][$field_name] = $value;
    $this->offsetSet('translations', $translations);
    return $this;
  }

  /**
   * Unset a field value for the given langcode. If no langcode is provided,
   * the default translation field is unset.
   *
   * @param string $name
   *   Field name.
   * @param string $langcode
   *   Langcode.
   */
  public function unsetField(string $name, string $langcode = 'default'): void {
    if ($langcode === 'default' || $langcode === $this->defaultLangcode()) {
      $default = $this->fields();
      unset($default[$name]);
      $this->offsetSet('default', $default);
      return;
    }

    $translations = $this->translations();
    unset($translations[$langcode][$name]);
    $this->offsetSet('translations', $translations);
  }

  /**
   * Provide a clean array for this object.
   *
   * @return array
   *   Does not include empty translations or options.
   */
  public function cleanArray(): array {
    $array = $this->getArrayCopy();
    if (!$this->options()) {
      unset($array['_meta']['options']);
    }

    if (!$this->translations()) {
      unset($array['translations']);
    }

    return $array;
  }

}
