<?php

declare(strict_types=1);

namespace Drupal\lark\Plugin\Lark;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Base for handlers that find entities embedded in formatted text.
 *
 * Embeds reference entities by UUID, which is portable, so text values are
 * never rewritten. Embedded entities are only reported as dependencies so
 * they are exported, and imported, before the entity that embeds them.
 */
abstract class TextEmbedHandlerBase extends FieldTypeHandlerBase {

  /**
   * Text item properties that may contain embeds.
   */
  protected const array TEXT_PROPERTIES = ['value', 'summary'];

  /**
   * {@inheritdoc}
   */
  public function getFieldDependencies(FieldItemListInterface $field): array {
    $dependencies = [];
    foreach ($field as $item) {
      foreach (static::TEXT_PROPERTIES as $property) {
        $text = $item->getProperties()[$property] ?? NULL;
        $text = $text?->getValue();
        if (!is_string($text) || stripos($text, $this->needle()) === FALSE) {
          continue;
        }

        $dependencies += $this->findEmbeds($text);
      }
    }

    return $dependencies;
  }

  /**
   * Find embedded entities in a string of HTML.
   *
   * @param string $text
   *   HTML.
   *
   * @return array
   *   UUID => entity type id pairs.
   */
  protected function findEmbeds(string $text): array {
    $embeds = [];
    $xpath = new \DOMXPath(Html::load($text));
    foreach ($xpath->query($this->xpath()) as $element) {
      if (!$element instanceof \DOMElement) {
        continue;
      }

      $uuid = trim($element->getAttribute('data-entity-uuid'));
      $entity_type_id = $this->entityTypeId($element);
      if ($uuid === '' || !$entity_type_id) {
        continue;
      }

      // Markup can name entity types this site doesn't have, which would
      // fail when the dependency is loaded.
      if (!$this->entityTypeManager->hasDefinition($entity_type_id)) {
        continue;
      }

      $embeds[$uuid] = $entity_type_id;
    }

    return $embeds;
  }

  /**
   * Substring that must be present for the text to contain an embed.
   *
   * @return string
   *   Matched case-insensitively.
   */
  abstract protected function needle(): string;

  /**
   * XPath query selecting embed elements.
   *
   * @return string
   *   XPath query.
   */
  abstract protected function xpath(): string;

  /**
   * Entity type id of the entity an embed element references.
   *
   * @param \DOMElement $element
   *   Embed element.
   *
   * @return string|null
   *   Entity type id, or NULL when it can't be determined.
   */
  abstract protected function entityTypeId(\DOMElement $element): ?string;

}
