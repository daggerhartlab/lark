<?php

declare(strict_types=1);

namespace Drupal\lark_transaction\Plugin;

use Drupal\Component\Datetime\Time;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\FileInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\lark\Entity\LarkSourceInterface;
use Drupal\media\MediaInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for lark transaction plugins.
 *
 * @deprecated
 *   Remove in v2.
 */
abstract class LarkTransactionPluginBase extends PluginBase implements LarkTransactionInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * LarkTransactionPluginBase constructor.
   *
   * @param array $configuration
   *   Configuration array.
   * @param $plugin_id
   *   Plugin ID.
   * @param $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager service.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory service.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   File system service.
   * @param \Drupal\file\FileUsage\FileUsageInterface $fileUsage
   *   File usage service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   Current user service.
   * @param \Drupal\Component\Datetime\Time $time
   *   Time service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   Date formatter service.
   */
  public function __construct(
    array                                $configuration,
                                         $plugin_id,
                                         $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerInterface            $logger,
    protected MessengerInterface         $messenger,
    protected ConfigFactoryInterface     $configFactory,
    protected FileSystemInterface        $fileSystem,
    protected FileUsageInterface         $fileUsage,
    protected AccountProxyInterface      $currentUser,
    protected Time                       $time,
    protected DateFormatterInterface     $dateFormatter,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('lark.transaction_logger'),
      $container->get('messenger'),
      $container->get('config.factory'),
      $container->get('file_system'),
      $container->get('file.usage'),
      $container->get('current_user'),
      $container->get('datetime.time'),
      $container->get('date.formatter'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return $this->pluginDefinition['id'];
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    // Cast the label to a string since it is a TranslatableMarkup object.
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function description(): string {
    // Cast the label to a string since it is a TranslatableMarkup object.
    return (string) $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function weight(): int {
    return (int) $this->pluginDefinition['weight'];
  }

  /**
   * {@inheritdoc}
   */
  public function enabled(): bool {
    return (bool) $this->pluginDefinition['enabled'];
  }

  /**
   * {@inheritdoc}
   */
  public function repeatable(): bool {
    return (bool) $this->pluginDefinition['repeatable'];
  }

  /**
   * {@inheritdoc}
   */
  public function executionCompleted(): bool {
    return (
      $this->getHistoryTimesExecuted() > 0
      &&
      !$this->repeatable()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function preExecute(): void {
    $this->logger->info("Starting import for plugin: " . $this->getPluginId());
  }

  /**
   * {@inheritdoc}
   */
  public function getHistory(): array {
    return $this->configuration['history'];
  }

  /**
   * {@inheritdoc}
   */
  public function getHistoryTimesExecuted(): int {
    return (int) $this->getHistory()['times_executed'];
  }

  /**
   * {@inheritdoc}
   */
  public function getHistoryLastExecuted(): int {
    return (int) $this->getHistory()['last_executed'];
  }

  /**
   * {@inheritdoc}
   */
  public function getHistoryLastExecutedFormatted(): string {
    return $this->dateFormatter->format($this->getHistoryLastExecuted(), 'short');
  }

  /**
   * {@inheritdoc}
   */
  public function hasBeenExecuted(): bool {
    return (bool) $this->getHistoryTimesExecuted();
  }

  /**
   * {@inheritdoc}
   */
  abstract public function execute(): void;

  /**
   * {@inheritdoc}
   */
  public function postExecute(): void {
    $this->logger->info("Completed import for plugin: " . $this->getPluginId());
  }

  /**
   * Get the source plugin instance.
   *
   * @param string $source_id
   *   Source plugin id.
   *
   * @return \Drupal\lark\Entity\LarkSourceInterface
   *   Source plugin instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function getSourcePluginInstance(string $source_id): LarkSourceInterface {
    return $this->entityTypeManager->getStorage('lark_source')->load($source_id);
  }

  /**
   * Get the source content directory.
   *
   * @param string $source_plugin_id
   *   Source plugin id.
   *
   * @return string
   *   The source content directory.
   */
  protected function sourceDirectory(string $source_plugin_id): string {
    return $this->getSourcePluginInstance($source_plugin_id)->directoryProcessed();
  }

  /**
   * Copy the given file to Drupal and create a File entity.
   *
   * @param string $source_file
   *   Absolute path where the file that should be copied can be found.
   * @param string $destination_directory
   *   Where the file should end up using Drupal file scheme.
   * @param int|null $owner_user_id
   *   Optionally change the file owner.
   *
   * @return \Drupal\file\FileInterface
   *   Created file entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function sideLoadFile(string $source_file, string $destination_directory = 'public://content-imported', int $owner_user_id = NULL): FileInterface {
    $owner_user_id = $owner_user_id ?? (int) $this->currentUser->id() ?: 1;
    $filename = basename($source_file);
    $destination_file = "{$destination_directory}/{$filename}";
    if (!file_exists($destination_directory)) {
      $this->fileSystem->mkdir($destination_directory, NULL, TRUE);
      $this->fileSystem->prepareDirectory($destination_directory);
    }
    $resulting_filepath = $this->fileSystem->copy($source_file, $destination_file, FileExists::Replace);

    $this->messenger->addMessage("Result: $resulting_filepath, Destination was: $destination_file");

    /** @var \Drupal\file\FileInterface $file_entity */
    $file_entity = $this->entityTypeManager->getStorage('file')->create([
      'filename' => $filename,
      'uri' => $destination_file,
      'status' => 1,
      'uid' => $owner_user_id,
    ]);
    $file_entity->save();
    $this->messenger->addStatus("Created file entity: {$file_entity->label()}");

    // Set this to be used by the node module in hopes it is never deleted.
    $this->fileUsage->add($file_entity, 'node', 'node', $owner_user_id);
    return $file_entity;
  }

  /**
   * Copy the given file to Drupal and create a File & Media image entity.
   *
   * @param string $media_title
   *   Title for the new image media entity.
   * @param string $alt_text
   *   Alt text and image title for the new image.
   * @param string $source_file
   *   Absolute path where the file that should be copied can be found.
   * @param string $destination_directory
   *   Where the file should end up using Drupal file scheme.
   * @param int|null $owner_user_id
   *   Optionally change the file owner.
   *
   * @return \Drupal\media\MediaInterface
   *   Created media entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function sideLoadImage(
    string $media_title,
    string $alt_text,
    string $source_file,
    string $destination_directory = 'public://content-imported',
    int $owner_user_id = NULL,
  ): MediaInterface {
    $owner_user_id = $owner_user_id ?? (int) $this->currentUser->id() ?: 1;
    $file_entity = $this->sideLoadFile($source_file, $destination_directory, $owner_user_id);
    /** @var \Drupal\media\MediaInterface $media_entity */
    $media_entity = $this->entityTypeManager->getStorage('media')->create([
      'name' => $media_title,
      'bundle' => 'image',
      'uid' => $owner_user_id,
      'langcode' => 'en',
      'status' => 1,
      'field_media_image' => [
        'target_id' => $file_entity->id(),
        'alt' => $alt_text,
        'title' => $alt_text,
      ],
    ]);
    $media_entity->save();
    $this->messenger->addStatus("Created media entity: {$media_entity->label()}");

    return $media_entity;
  }

}
