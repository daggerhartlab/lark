<?php

namespace Drupal\lark\Service\Cache;

interface RuntimeCacheInterface {

  /**
   * Get the value of a dictionary item.
   *
   * @param string $name
   *   The name of the item.
   *
   * @return mixed
   *   The value of the item.
   */
  public function get(string $name): mixed;

  /**
   * Set the value of a dictionary item.
   *
   * @param string $name
   *   The name of the item.
   * @param mixed $value
   *   The value of the item.
   */
  public function set(string $name, mixed $value): void;

  /**
   * Remove an item from the dictionary.
   *
   * @param string $name
   *   The name of the item.
   */
  public function remove(string $name): void;

  /**
   * Check if an item exists in the dictionary.
   *
   * @param string $name
   *   The name of the item.
   *
   * @return bool
   *   TRUE if the item exists, FALSE otherwise.
   */
  public function has(string $name): bool;

  /**
   * Get all items in the dictionary that are not placeholders.
   *
   * @return array
   *   An array of items.
   */
  public function all(): array;

  /**
   * Return number of items in the dictionary.
   *
   * @return int
   */
  public function length(): int;

  /**
   * Combine items from another dictionary.
   *
   * @param array $items
   *   An array of items.
   */
  public function combine(array $items): void;

  /**
   * Create a placeholder for an item.
   *
   * @param string $name
   *   The name of the item.
   *
   * @return void
   */
  public function placeholder(string $name): void;

  /**
   * Check if an item is a placeholder.
   *
   * @param string $name
   *   The name of the item.
   *
   * @return bool
   *   TRUE if the item is a placeholder, FALSE otherwise.
   */
  public function isPlaceholder(string $name): bool;

}
