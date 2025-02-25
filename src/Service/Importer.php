<?php

declare(strict_types=1);

namespace Drupal\lark\Service;

use Drupal\Component\Graph\Graph;
use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Utility\SortArray;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Installer\InstallerKernel;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\lark\Exception\LarkImportException;
use Drupal\lark\Model\ExportArray;
use Drupal\lark\Entity\LarkSourceInterface;
use Drupal\lark\Routing\EntityTypeInfo;
use Drupal\lark\Service\Utility\SourceUtility;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Finder\Finder as SymfonyFinder;

/**
 * Import entities and their dependencies.
 *
 * This service is aware of the export data structure and knows how to extract
 * the necessary information to create or update entities based on the data.
 */
class Importer implements ImporterInterface {

  use StringTranslationTrait;

  protected array $discoveryCache = [];

  /**
   * LarkEntityImporter constructor.
   *
   * @param \Drupal\lark\Service\EntityUpdaterInterface $upserter
   *   Service for upserting entities.
   * @param \Drupal\lark\Service\MetaOptionManager $metaOptionManager
   *   Meta options manager.
   * @param \Drupal\lark\Service\FieldTypeHandlerManagerInterface $fieldTypeManager
   *   The lark field type handler plugin manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityUpdaterInterface $upserter,
    protected ExportFileManager $exportFileManager,
    protected FieldTypeHandlerManagerInterface $fieldTypeManager,
    protected LanguageManagerInterface $languageManager,
    protected LoggerChannelInterface $logger,
    protected MetaOptionManager $metaOptionManager,
    protected MessengerInterface $messenger,
    protected SourceUtility $sourceUtility,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function importSourcesAll(bool $show_messages = TRUE): void {
    /** @var \Drupal\lark\Entity\LarkSourceInterface[] $sources */
    $sources = $this->sourceUtility->loadByProperties([
      'status' => 1,
    ]);

    foreach ($sources as $source) {
      $this->importSource($source->id(), $show_messages);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function importSource(string $source_id, bool $show_messages = TRUE): void {
    /** @var \Drupal\lark\Entity\LarkSourceInterface $source */
    $source = $this->sourceUtility->load($source_id);
    $exports = $this->discoverSourceExports($source);

    try {
      $this->upsertEntities($exports);
      $this->validateImportResults($exports, $show_messages);
    }
    catch (\Exception $exception) {
      if ($show_messages) {
        $this->messenger->addError($exception->getMessage());
      }
      $this->logger->error($exception->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function importSourceExport(string $source_id, string $uuid, bool $show_messages = TRUE): void {
    $source = $this->sourceUtility->load($source_id);
    $exports = $this->discoverSourceExport($source, $uuid);
    if (!isset($exports[$uuid])) {
      $message = $this->t('No export found with UUID @uuid in source @source.', [
        '@uuid' => $uuid,
        '@source' => $source->id(),
      ]);
      if ($show_messages) {
        $this->messenger->addError($message);
      }
      $this->logger->error($message);
      return;
    }

    $exports = $this->discoverSourceExport($source, $uuid);
    try {
      $this->upsertEntities($exports);
      $this->validateImportResults($exports, $show_messages);
    }
    catch (\Exception $exception) {
      if ($show_messages) {
        $this->messenger->addError($exception->getMessage());
      }
      $this->logger->error($exception->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function discoverSourceExports(LarkSourceInterface $source): array {
    if (array_key_exists($source->id(), $this->discoveryCache)) {
      return $this->discoveryCache[$source->id()];
    }

    $this->discoveryCache[$source->id()] = $this->exportFileManager->discoverExports($source->directoryProcessed());
    return $this->discoveryCache[$source->id()];
  }

  /**
   * {@inheritdoc}
   */
  public function discoverSourceExport(LarkSourceInterface $source, string $uuid): array {
    return $this->exportFileManager->filterExportWithDependencies($uuid, $this->discoverSourceExports($source));
  }

  /**
   * Validate import results.
   *
   * @param \Drupal\lark\Model\ExportArray[] $exports
   *   Lark source exports data.
   * @param bool $show_messages
   *   Whether to show messages.
   *
   * @return void
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function validateImportResults(array $exports, bool $show_messages): void {
    foreach ($exports as $uuid => $export) {
      $entity_type = $export->entityTypeId();
      $entity = $this->entityTypeManager->getStorage($entity_type)->loadByProperties(['uuid' => $uuid]);
      if ($entity) {
        $entity = reset($entity);
        $message = $this->t('Imported @entity_type "@label" as "@entity_id".', [
          '@entity_type' => $entity_type,
          '@label' => $entity->label(),
          '@entity_id' => $entity->id(),
        ]);
        if ($show_messages) {
          $this->messenger->addMessage($message);
        }
        $this->logger->notice($message);
      }
      else {
        $message = $this->t('Failed to import @entity_type with UUID @uuid.', [
          '@entity_type' => $entity_type,
          '@uuid' => $uuid,
        ]);
        if ($show_messages) {
          $this->messenger->addError($message);
        }
        $this->logger->error($message);
      }
    }
  }

  /**
   * Imports content entities from disk.
   *
   * @param \Drupal\lark\Model\ExportArray[] $exports
   *   The source exports data, which has information on the entities to create
   *   in the necessary dependency order.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function upsertEntities(array $exports): void {
    if (count($exports) === 0) {
      return;
    }

    foreach ($exports as $export) {
      $this->validateExport($export);
      $this->normalizeDefaultLanguage($export);
      $entity = $this->upserter->getOrCreateEntity(
        $export->uuid(),
        $export->entityTypeId(),
        $export->bundle(),
        $export->defaultLangcode(),
        $export->label(),
      );

      $this->processValuesForImport($entity, $export);
      $this->upserter->setEntityValues($entity, $export);

      // Allow meta options to act immediately before saving.
      foreach ($this->metaOptionManager->getInstances() as $meta_option) {
        if ($meta_option->applies($entity)) {
          $meta_option->preImportSave($entity, $export);
        }
      }

      /* @todo - Figure out "modified by another user" issue.
      $violations = $entity->validate();
      if (count($violations) > 0) {
        throw new LarkEntityInvalidException($violations, $path);
      }
      */
      $entity->save();
      foreach ($entity->getTranslationLanguages(FALSE) as $language) {
        $entity->getTranslation($language->getId())->save();
      }
    }
  }

  /**
   * Validate a single export array.
   *
   * @param \Drupal\lark\Model\ExportArray $export
   *   Decoded yaml export data.
   *
   * @return void
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function validateExport(ExportArray $export): void {
    if (empty($export->uuid())) {
      throw new LarkImportException('The uuid metadata must be specified as [_meta][uuid].');
    }
    if (empty($export->entityTypeId())) {
      throw new LarkImportException('The entity type metadata must be specified as [_meta][entity_type].');
    }
    if (empty($export->bundle())) {
      throw new LarkImportException('The bundle metadata must be specified as [_meta][bundle].');
    }
    if (empty($export->path())) {
      throw new LarkImportException('The export file yaml path must be specified as [_meta][path].');
    }
    if (empty($export->defaultLangcode())) {
      throw new LarkImportException('The default_langcode metadata must be specified as [_meta][default_langcode].');
    }

    $entity_type = $this->entityTypeManager->getDefinition($export->entityTypeId());
    /** @var \Drupal\Core\Entity\EntityTypeInterface $entity_type */
    if (!$entity_type->get(EntityTypeInfo::IS_EXPORTABLE)) {
      throw new LarkImportException("Only content entities can be imported. Export {$export->uuid()} is a '{$export->entityTypeId()}'.");
    }
  }

  /**
   * Verifies that the site knows the default language of the normalized entity.
   *
   * Will attempt to switch to an alternative translation or just import it
   * with the site default language.
   *
   * @param \Drupal\lark\Model\ExportArray $export
   *   The normalized entity data.
   */
  protected function normalizeDefaultLanguage(ExportArray $export) {
    $default_langcode = $export->defaultLangcode();
    $default_language = $this->languageManager->getDefaultLanguage();
    // Check the language. If the default language isn't known, import as one
    // of the available translations if one exists with those values. If none
    // exists, create the entity in the default language.
    // During the installer, when installing with an alternative language,
    // `en` is still the default when modules are installed so check the default language
    // instead.
    if (!$this->languageManager->getLanguage($default_langcode) || (InstallerKernel::installationAttempted() && $default_language->getId() !== $default_langcode)) {
      $use_default = TRUE;
      foreach ($export->translations() as $langcode => $translation_data) {
        if ($this->languageManager->getLanguage($langcode)) {
          $export->setDefaultLangcode($langcode);
          $export->setFields(\array_merge($export->fields('default'), $translation_data));
          $export->unsetTranslation($langcode);
          $use_default = FALSE;
          break;
        }
      }

      if ($use_default) {
        $export->setDefaultLangcode($default_language->getId());
      }
    }
  }

  /**
   * Allow lark field type plugins to alter the import values.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being imported.
   * @param ExportArray $export
   *   The export data.
   *
   * @return void
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function processValuesForImport(ContentEntityInterface $entity, ExportArray $export) {
    foreach ($export->fields('default') as $field_name => $values) {
      if (is_array($values) && $entity->hasField($field_name)) {
        $field = $entity->get($field_name);
        $value = $this->fieldTypeManager->alterImportValues($export->getField($field_name), $field);
        $export->setField($field_name, $value);
      }
    }

    foreach ($export->translations() as $langcode => $translation) {
      foreach ($translation as $field_name => $values) {
        if (is_array($values) && $entity->hasField($field_name)) {
          $field = $entity->get($field_name);
          $value = $this->fieldTypeManager->alterImportValues($export->getField($field_name, $langcode), $field);
          $export->setField($field_name, $value, $langcode);
        }
      }
    }
  }

}
