<?php

declare(strict_types=1);

namespace Drupal\lark_transaction\Plugin;

/**
 * Interface for lark transaction plugins.
 *
 * @deprecated
 *   Remove in v2.
 */
interface LarkTransactionInterface {

  /**
   * Returns the plugin ID.
   */
  public function id(): string;

  /**
   * Returns the translated plugin label.
   */
  public function label(): string;

  /**
   * Returns the translated plugin description.
   */
  public function description(): string;

  /**
   * Returns the plugin weight.
   */
  public function weight(): int;

  /**
   * Returns whether the plugin is enabled.
   *
   * @return bool
   *   TRUE if the plugin is enabled, FALSE otherwise.
   */
  public function enabled(): bool;

  /**
   * Returns whether the plugin is repeatable.
   *
   * @return bool
   *   TRUE if the plugin is repeatable, FALSE otherwise.
   */
  public function repeatable(): bool;

  /**
   * Returns whether the plugin execution has been completed.
   *
   * @return bool
   *   TRUE if the plugin execution has been completed, FALSE otherwise.
   */
  public function executionCompleted(): bool;

  /**
   * Returns the plugin's history database row.
   *
   * @return array
   *   The plugin's history database row.
   */
  public function getHistory(): array;

  /**
   * Returns the number of times a transaction was executed.
   *
   * @return int
   *   The number of times a transaction was executed.
   */
  public function getHistoryTimesExecuted(): int;

  /**
   * Returns the Unix timestamp when the transaction was last executed.
   *
   * @return int
   *   The Unix timestamp when the transaction was last executed.
   */
  public function getHistoryLastExecuted(): int;

  /**
   * Returns the formatted date when the transaction was last executed.
   *
   * @return string
   *   The formatted date when the transaction was last executed.
   */
  public function getHistoryLastExecutedFormatted(): string;

  /**
   * Returns whether the transaction has been executed.
   *
   * @return bool
   *   TRUE if the transaction has been executed, FALSE otherwise.
   */
  public function hasBeenExecuted(): bool;

  /**
   * Pre-execution can be used to set up the content creation/updates.
   *
   * @return void
   */
  public function preExecute(): void;

  /**
   * Perform the content creation/updates.
   *
   * @return void
   */
  public function execute(): void;

  /**
   * Post-execution, such as validating the content creation/updates.
   *
   * @return void
   */
  public function postExecute(): void;

}
