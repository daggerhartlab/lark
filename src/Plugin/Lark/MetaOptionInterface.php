<?php

declare(strict_types=1);

namespace Drupal\lark\Plugin\Lark;

use Drupal\Core\Archiver\ArchiveTar;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\lark\Model\ExportableInterface;
use Drupal\lark\Model\ExportArray;

/**
 * Interface for lark_entity_export_form plugins. These plugins are used when
 * building and handling the entity export form.
 */
interface MetaOptionInterface {

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
  public function formElement(ExportableInterface $exportable, array &$form, FormStateInterface $form_state, array $render_parents): array;

//  public function validateElement(array &$form, FormStateInterface $form_state): void;

  /**
   * @param array $submitted_values
   *   Values for this plugin for a single export entity.
   * @param \Drupal\lark\Model\ExportableInterface $exportable
   *   UUID of the entity.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current form state.
   *
   * @return array
   *   Processed values.
   */
  public function processFormValues(array $submitted_values, ExportableInterface $exportable, FormStateInterface $form_state): array;

  /**
   * Perform additional actions during the archiving of an exportable.
   *
   * @param \Drupal\Core\Archiver\ArchiveTar $archive
   * @param \Drupal\lark\Model\ExportableInterface $exportable
   *
   * @return void
   */
  public function preExportDownload(ArchiveTar $archive, ExportableInterface $exportable): void;

  /**
   * Perform additional actions and modifications to the exportable immediately
   * before it is written to yaml.
   *
   * @param \Drupal\lark\Model\ExportableInterface $exportable
   *   Exportable being written.
   *
   * @return void
   */
  public function preExportWrite(ExportableInterface $exportable): void;

  /**
   * Perform additional actions and modifications to the entity immediately
   * before is it saved to the database.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity being imported.
   * @param \Drupal\lark\Model\ExportArray $export
   *   Export array for the entity being imported.
   *
   * @return void
   */
  public function preImportSave(ContentEntityInterface $entity, ExportArray $export): void;

}
