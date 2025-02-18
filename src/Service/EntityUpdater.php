<?php

namespace Drupal\lark\Service;

use Drupal\Core\DefaultContent\AdminAccountSwitcher;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\file\FileInterface;
use Drupal\lark\Model\ExportArray;
use Drupal\lark\Model\LarkSettings;
use Drupal\user\EntityOwnerInterface;

/**
 * Responsible for creating and updating entities during the import process.
 *
 * This service is unaware of the export data structure and only knows how to
 * create or update entities based on the data provided.
 */
class EntityUpdater implements EntityUpdaterInterface {

  public function __construct(
    protected LarkSettings $settings,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityRepositoryInterface $entityRepository,
    protected LanguageManagerInterface $languageManager,
    protected FileSystemInterface $fileSystem,
    protected AdminAccountSwitcher $accountSwitcher,
    protected LoggerChannelInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getOrCreateEntity(
    string $uuid,
    string $entity_type_id,
    string $bundle,
    string $default_langcode,
    string $label = NULL
  ): ContentEntityInterface {
    // Check if the entity already exists.
    $entity = $this->entityRepository->loadEntityByUuid($entity_type_id, $uuid);
    if (!$entity) {
      // Create the entity if it doesn't exist so that it can be found later
      // in the import process.
      /** @var \Drupal\Core\Entity\EntityTypeInterface $entity_type */
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);

      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $entity = $this->entityTypeManager->getStorage($entity_type->id())->create([
        $entity_type->getKey('uuid') => $uuid,
        $entity_type->getKey('bundle') => $bundle,
        $entity_type->getKey('langcode') => $default_langcode,
        $entity_type->getKey('label') => $label ?? '',
      ]);
    }

    $this->ensureEntityOwner($entity);
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function ensureEntityOwner(ContentEntityInterface $entity): void {
    if (
      ($entity instanceof EntityOwnerInterface) &&
      // Paragraphs have the EntityOwnerInterface, but it's deprecated.
      // This causes the paragraph to attempt to get its parent entity,
      // which doesn't exist yet during the import and causes the import to
      // stop without an error.
      ($entity->getEntityTypeId() !== 'paragraph') &&
      !$entity->getOwnerId()
    ) {
      $account = $this->accountSwitcher->switchToAdministrator();
      $entity->setOwnerId($account->id());
      $this->accountSwitcher->switchBack();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setEntityValues(ContentEntityInterface $entity, ExportArray $export): void {
    foreach ($export->fields() as $field_name => $values) {
      if (!$entity->hasField($field_name)) {
        $this->logger->warning("Field $field_name does not exist on entity {$entity->getEntityTypeId()}, {$entity->uuid()}.");
        continue;
      }

      $this->setFieldValues($entity, $field_name, $values);
    }

    foreach ($export->translations() as $langcode => $translation_data) {
      if ($this->languageManager->getLanguage($langcode)) {
        $translation = $entity->hasTranslation($langcode) ? $entity->getTranslation($langcode) : $entity->addTranslation($langcode);
        foreach ($translation_data as $field_name => $values) {
          if (!$translation->hasField($field_name)) {
            $this->logger->warning("Field $field_name does not exist on translation entity {$entity->getEntityTypeId()}, {$entity->uuid()}, langcode - {$langcode}.");
            continue;
          }

          $this->setFieldValues($translation, $field_name, $values);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setFieldValues(ContentEntityInterface $entity, string $field_name, array $values): void {
    $property_names = $entity->getFieldDefinition($field_name)->getFieldStorageDefinition()->getPropertyNames();
    foreach ($values as $delta => $item_value) {
      if (!$entity->get($field_name)->get($delta)) {
        $entity->get($field_name)->appendItem();
      }
      /** @var \Drupal\Core\Field\FieldItemInterface $item */
      $item = $entity->get($field_name)->get($delta);

      foreach ($item_value as $property_name => $value) {
        if (!in_array($property_name, $property_names, TRUE)) {
          continue;
        }

        $item->get($property_name)->setValue($value);
      }
    }
  }

}
