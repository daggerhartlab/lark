<?php

declare(strict_types=1);

namespace Drupal\lark\Plugin\Lark;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\lark\Model\ExportableInterface;

/**
 * Interface for lark_entity_export_form plugins. These plugins are used when
 * building and handling the entity export form.
 */
interface EntityExportFormInterface {

  /**
   * Plugin ID.
   *
   * @return string
   */
  public function id(): string;

  /**
   * Returns the translated plugin label.
   *
   * @return string
   */
  public function label(): string;

  /**
   * Returns the translated plugin description.
   *
   * @return string
   */
  public function description(): string;

  /**
   * Return whether this element applies to the given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return bool
   */
  public function applies(EntityInterface $entity): bool;

  /**
   * Build and return the form element.
   *
   * @param \Drupal\lark\Model\ExportableInterface $exportable
   *   Exportable this form is for.
   * @param array $form
   *   The form being built. There should be little need to modify this except
   *   for in special cases.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current form state.
   * @param array $render_parents
   *   Array for #parents for fields that need it.
   *
   * @return array
   *   Customized element render array.
   */
  public function buildElement(ExportableInterface $exportable, array &$form, FormStateInterface $form_state, array $render_parents): array;

//  public function validateElement(array &$form, FormStateInterface $form_state): void;

  /**
   * @param array $values
   *   Values for this plugin for a single export entity.
   * @param string $uuid
   *   UUID of the entity.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current form state.
   *
   * @return array
   *   Processed values.
   */
  public function processValues(array $submitted_values, string $uuid, FormStateInterface $form_state): array;

}
