<?php

declare(strict_types=1);

namespace Drupal\lark\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Extension\Exception\UnknownExtensionException;
use Drupal\Core\Extension\ExtensionPathResolver;

/**
 * Defines the source entity type.
 *
 * @ConfigEntityType(
 *   id = "lark_source",
 *   label = @Translation("Source"),
 *   label_collection = @Translation("Sources"),
 *   label_singular = @Translation("source"),
 *   label_plural = @Translation("sources"),
 *   label_count = @PluralTranslation(
 *     singular = "@count source",
 *     plural = "@count sources",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\lark\Entity\LarkSourceListBuilder",
 *     "form" = {
 *       "add" = "Drupal\lark\Form\LarkSourceForm",
 *       "edit" = "Drupal\lark\Form\LarkSourceForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *   },
 *   config_prefix = "lark_source",
 *   admin_permission = "lark administer configuration",
 *   links = {
 *     "canonical" = "/admin/lark/source/{lark_source}",
 *     "collection" = "/admin/lark/source",
 *     "add-form" = "/admin/lark/source/add",
 *     "edit-form" = "/admin/lark/source/{lark_source}/edit",
 *     "delete-form" = "/admin/lark/source/{lark_source}/delete",
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "directory",
 *   },
 * )
 */
final class LarkSource extends ConfigEntityBase implements LarkSourceInterface {

  /**
   * @var string
   */
  protected string $id = '';

  /**
   * @var string
   */
  protected string $label = '';

  /**
   * @var string
   */
  protected string $description = '';

  /**
   * @var string
   */
  protected string $directory = '';

  /**
   * @var \Drupal\Core\Extension\ExtensionPathResolver|mixed
   */
  protected ExtensionPathResolver $extensionPathResolver;

  /**
   * @param array $values
   * @param $entity_type
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
