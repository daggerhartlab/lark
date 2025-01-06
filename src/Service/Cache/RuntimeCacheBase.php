<?php

namespace Drupal\lark\Service\Cache;

abstract class RuntimeCacheBase implements RuntimeCacheInterface {

  protected array $items = [];

  /**
   * @param mixed|NULL $placeholder
   */
  public function __construct(
    protected mixed $placeholder = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function get(string $name): mixed {
    return $this->items[$name] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function set(string $name, mixed $value): void {
    $this->items[$name] = $value;
  }

  /**
   * {@inheritdoc}
   */
  public function remove(string $name): void {
    unset($this->items[$name]);
  }

  /**
   * {@inheritdoc}
   */
  public function has(string $name): bool {
    $has = array_key_exists($name, $this->items);
    return $has;
  }

  /**
   * {@inheritdoc}
   */
  public function all(): array {
    return array_filter($this->items, function ($item) {
      return $item !== $this->placeholder;
    });
  }

  /**
   * {@inheritdoc}
   */
  public function length(): int {
    return count($this->all());
  }

  /**
   * {@inheritdoc}
   */
  public function combine(array $items): void {
    foreach ($items as $name => $value) {
      $this->set($name, $value);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function placeholder(string $name): void {
    $this->items[$name] = $this->placeholder;
  }

  /**
   * {@inheritdoc}
   */
  public function isPlaceholder(string $name): bool {
    return $this->items[$name] === $this->placeholder;
  }

}
