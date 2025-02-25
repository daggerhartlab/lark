<?php

namespace Drupal\lark\Model;

/**
 * Class ExportCollection
 *
 * @package Drupal\lark\Model
 */
class ExportCollection extends \ArrayObject {

  /**
   * @param \Drupal\lark\Model\ExportArray[] $array
   * @param int $flags
   * @param string $iteratorClass
   */
  public function __construct(object|array $array = [], int $flags = 0, string $iteratorClass = "ArrayIterator") {
    $typed_keyed = [];

    foreach ($array as $export) {
      if (!($export instanceof ExportArray)) {
        throw new \InvalidArgumentException('Only ExportArray instances can be added to an ExportCollection, "' . get_debug_type($export) . '" given instead.');
      }

      $typed_keyed[$export->uuid()] = $export;
    }

    parent::__construct($typed_keyed, $flags, $iteratorClass);
  }

  /**
   * {@inheritdoc}
   */
  public function offsetSet(mixed $key, mixed $value): void {
    if (!($value instanceof ExportArray)) {
      throw new \InvalidArgumentException('Only ExportArray instances can be added to an ExportCollection, "' . get_debug_type($value) . '" given instead.');
    }

    parent::offsetSet($key, $value); // TODO: Change the autogenerated stub
  }

  /**
   * Check if the collection has an item with the given UUID.
   *
   * @param string $uuid
   *   The UUID to check for.
   *
   * @return bool
   *   Whether the collection has an item with the given UUID.
   */
  public function has(string $uuid): bool {
    return $this->offsetExists($uuid);
  }

  /**
   * Get an item by UUID.
   *
   * @param string $uuid
   *   The UUID of the item to get.
   *
   * @return \Drupal\lark\Model\ExportArray|false
   *   The item, or false if not found.
   */
  public function get(string $uuid): ExportArray|false {
    return $this->offsetGet($uuid);
  }

  /**
   * @param string $uuid
   * @param \Drupal\lark\Model\ExportArray $item
   *
   * @return void
   */
  public function set(string $uuid, ExportArray $item): void {
    $this->offsetSet($uuid, $item);
  }

  /**
   * @param \Drupal\lark\Model\ExportArray $export
   *
   * @return void
   */
  public function add(ExportArray $export): void {
    $this->set($export->uuid, $export);
  }

  /**
   * Filter the collection.
   *
   * @param callable $callback
   *   The callback to apply to each item.
   * @param int $mode
   *   The mode to pass to array_filter.
   *
   * @return \Drupal\lark\Model\ExportCollection
   */
  public function filter(callable $callback, int $mode = 0): ExportCollection {
    return new static(array_filter($this->getArrayCopy(), $callback, $mode));
  }

  /**
   * Map over the collection.
   *
   * @param callable $callback
   *   The callback to apply to each item.
   *
   * @return \Drupal\lark\Model\ExportCollection
   *   The new collection.
   */
  public function map(callable $callback): ExportCollection {
    return new static(array_map($callback, $this->getArrayCopy()));
  }

}
