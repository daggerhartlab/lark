<?php

declare(strict_types=1);

namespace Drupal\lark\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Extension\Exception\UnknownExtensionException;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\lark\Form\LarkSourceForm;

/**
 * Defines the source entity type.
 */
#[ConfigEntityType(
  id: 'lark_source',
  label: new TranslatableMarkup('Source'),
  label_collection: new TranslatableMarkup('Sources'),
  label_singular: new TranslatableMarkup('source'),
  label_plural: new TranslatableMarkup('sources'),
  config_prefix: 'lark_source',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'uuid' => 'uuid',
  ],
  handlers: [
    'list_builder' => LarkSourceListBuilder::class,
    'form' => [
      'add' => LarkSourceForm::class,
      'edit' => LarkSourceForm::class,
      'delete' => EntityDeleteForm::class,
    ],
  ],
  links: [
    'canonical' => '/admin/lark/source/{lark_source}',
    'collection' => '/admin/lark/source',
    'add-form' => '/admin/lark/source/add',
    'edit-form' => '/admin/lark/source/{lark_source}/edit',
    'delete-form' => '/admin/lark/source/{lark_source}/delete',
  ],
  admin_permission: 'lark administer configuration',
  label_count: [
    'singular' => '@count source',
    'plural' => '@count sources',
  ],
  config_export: [
    'id',
    'label',
    'description',
    'directory',
  ],
)]
final class LarkSource extends ConfigEntityBase implements LarkSourceInterface {

  /**
   * The source id.
   *
   * @var string
   */
  protected string $id = '';

  /**
   * The source label.
   *
   * @var string
   */
  protected string $label = '';

  /**
   * The source description.
   *
   * @var string
   */
  protected string $description = '';

  /**
   * The unprocessed source directory.
   *
   * @var string
   */
  protected string $directory = '';

  /**
   * The extension path resolver.
   *
   * @var \Drupal\Core\Extension\ExtensionPathResolver
   */
  protected ExtensionPathResolver $extensionPathResolver;

  /**
   * Constructs a LarkSource object.
   *
   * @param array $values
   *   An array of values to set, keyed by property name.
   * @param string $entity_type
   *   The entity type id.
   */
  public function __construct(array $values, $entity_type) {
    parent::__construct($values, $entity_type);

    $this->extensionPathResolver = \Drupal::service(ExtensionPathResolver::class);
  }

  /**
   * {@inheritdoc}
   */
  public function provider(): string {
    return 'config';
  }

  /**
   * {@inheritdoc}
   */
  public function description(): string {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function directory(): string {
    return $this->directory;
  }

  /**
   * {@inheritdoc}
   */
  public function setDirectory(string $directory): void {
    $this->directory = $directory;
  }

  /**
   * {@inheritdoc}
   */
  public function directoryProcessed(bool $absolute = TRUE): string {
    $directory = $this->directory();
    $directory = preg_replace_callback('/\[\w+]/', function (array $matches) {
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
      "{$uuid}.yml",
    ]));
  }

}
