<?php

declare(strict_types=1);

namespace Drupal\lark\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Reads meta option values submitted through an exportables table.
 *
 * Used by any form that renders ExportablesTableBuilder::table(). Expects the
 * using class to provide $exportableFactory and $metaOptionManager.
 */
trait ExportablesOverridesTrait {

  /**
   * Collect meta option overrides from a submitted exportables table.
   *
   * @param string $tree_name
   *   Name of the element the table was placed within.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current form state.
   *
   * @return array
   *   Meta option values keyed by entity UUID then plugin ID.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getSubmittedOverrides(string $tree_name, FormStateInterface $form_state): array {
    $submitted_values = $form_state->getValue($tree_name) ?? [];
    if (!is_array($submitted_values)) {
      return [];
    }

    $overrides = [];
    foreach ($submitted_values as $uuid => $values) {
      $exportable = $this->exportableFactory->createFromUuid($uuid);

      foreach ($this->metaOptionManager->getInstances() as $meta_option) {
        // Ensure the plugin applies to the entity.
        if (!$meta_option->applies($exportable->entity())) {
          continue;
        }

        // Ensure it has submitted values.
        if (!array_key_exists($meta_option->id(), $values)) {
          $values[$meta_option->id()] = [];
        }

        // Allow the plugin to record the values to the export.
        $plugin_values = $meta_option->processFormValues($values[$meta_option->id()], $exportable, $form_state);
        if ($plugin_values) {
          $overrides[$uuid][$meta_option->id()] = $plugin_values;
        }
      }
    }

    return $overrides;
  }

}
