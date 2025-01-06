<?php

namespace Drupal\lark\Model;

class UniqueDictionary {

  protected array $items = [];

  /**
   * @param mixed|NULL $placeholder
   */
  public function __construct(
    protected mixed $placeholder = NULL,
  ) {}

  /**
   * Get the value of a dictionary item.
   *
   * @param string $name
   *   The name of the item.
   *
   * @return mixed
   *   The value of the item.
   */
  public function get(string $name): mixed {
    return $this->items[$name] ?? NULL;
  }

  /**
   * Set the value of a dictionary item.
   *
   * @param string $name
   *   The name of the item.
   * @param mixed $value
   *   The value of the item.
   */
  public function set(string $name, mixed $value): void {
    $this->items[$name] = $value;
  }

  /**
   * Remove an item from the dictionary.
   *
   * @param string $name
   *   The name of the item.
   */
  public function remove(string $name): void {
    unset($this->items[$name]);
  }

  /**
   * Check if an item exists in the dictionary.
   *
   * @param string $name
   *   The name of the item.
   *
   * @return bool
   *   TRUE if the item exists, FALSE otherwise.
   */
  public function has(string $name): bool {
    $has = array_key_exists($name, $this->items);
    return $has;
  }

  /**
   * Get all items in the dictionary that are not placeholders.
   *
   * @return array
   *   An array of items.
   */
  public function all(): array {
    return array_filter($this->items, function ($item) {
      return $item !== $this->placeholder;
    });
  }

  public function length(): int {
    return count($this->all());
  }

  /**
   * Combine items from another dictionary.
   *
   * @param array $items
   *   An array of items.
   */
  public function combine(array $items): void {
    foreach ($items as $name => $value) {
      $this->set($name, $value);
    }
  }

  /**
   * Create a placeholder for an item.
   *
   * @param string $name
   *   The name of the item.
   *
   * @return void
   */
  public function placeholder(string $name): void {
    $this->items[$name] = $this->placeholder;
  }

  /**
   * Check if an item is a placeholder.
   *
   * @param string $name
   *   The name of the item.
   *
   * @return bool
   *   TRUE if the item is a placeholder, FALSE otherwise.
   */
  public function isPlaceholder(string $name): bool {
    return $this->items[$name] === $this->placeholder;
  }

}
