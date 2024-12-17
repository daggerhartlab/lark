<?php

namespace Drupal\lark\Service;


use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\file\FileInterface;

interface EntityUpdaterInterface {

  /**
   * Get the entity if it exists or create it if it doesn't.
   *
   * @param string $uuid
   *   The UUID of the entity.
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle
   *   The bundle.
   * @param string $default_langcode
   *   The default langcode.
   * @param string|NULL $label
   *   The label.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function getOrCreateEntity(string $uuid, string $entity_type_id, string $bundle, string $default_langcode, string $label = NULL): ContentEntityInterface;

  /**
   * Ensure that the entity is not owned by the anonymous user.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity being imported.
   *
   * @return void
   */
  public function ensureEntityOwner(ContentEntityInterface $entity): void;

  /**
   * Set the field values on the entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity.
   * @param array $data
   *   Decoded export data.
   *
   * @return void
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public function setEntityValues(ContentEntityInterface $entity, array $data): void;

  /**
   * Sets field values based on the normalized data.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param string $field_name
   *   The name of the field.
   * @param array $values
   *   The normalized data for the field.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public function setFieldValues(ContentEntityInterface $entity, string $field_name, array $values): void;

  /**
   * Copies a file from default content directory to the site's file system.
   *
   * @param \Drupal\file\FileInterface $entity
   *   The file entity.
   * @param string $source_directory
   *   The path to the file to copy.
   * @param string $destination_uri
   *   Where the file should be copied to.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function copyFileAssociatedWithEntity(FileInterface $entity, string $source_directory, string $destination_uri): void;

}
