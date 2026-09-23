<?php

declare(strict_types=1);

namespace Drupal\lark\Plugin\Lark\FieldTypeHandler;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\lark\Attribute\LarkFieldTypeHandler;
use Drupal\path_alias\PathAliasInterface;
use Drupal\pathauto\PathautoState;

/**
 * Plugin implementation of the lark_field_type_handler.
 */
#[LarkFieldTypeHandler(
  id: 'path_handler',
  label: new TranslatableMarkup('Path Field Type Handler'),
  description: new TranslatableMarkup('Handles path field type based on the referenced path_alias entity UUIDs.'),
  fieldTypes: ['path'],
)]
class PathHandler extends DefaultHandler {

  /**
   * {@inheritdoc}
   */
  public function alterExportValue(array $values, ContentEntityInterface $entity, FieldItemListInterface $field): array {
    $storage = $this->entityTypeManager->getStorage('path_alias');
    $has_pathauto = in_array('pathauto', $field->getFieldDefinition()->getFieldStorageDefinition()->getPropertyNames());
    foreach ($values as $delta => $value) {
      if (isset($value['pid'])) {
        $path_alias = $storage->load($value['pid']);
        if (!$path_alias) {
          continue;
        }

        $values[$delta]['target_uuid'] = $path_alias->uuid();
        $values[$delta]['original_values']['pid'] = $path_alias->id();
        unset($values[$delta]['pid']);
      }

      // Record whether "Generate automatic URL alias" was checked so that
      // import can recreate the same behavior instead of assuming it was
      // always unchecked.
      if ($has_pathauto) {
        $values[$delta]['pathauto'] = (int) $field->get($delta)->pathauto;
      }
    }
    return parent::alterExportValue($values, $entity, $field);
  }

  /**
   * {@inheritdoc}
   */
  public function alterImportValue(array $values, FieldItemListInterface $field): array {
    $has_pathauto = in_array('pathauto', $field->getFieldDefinition()->getFieldStorageDefinition()->getPropertyNames());
    foreach ($values as $delta => $value) {
      if (isset($value['target_uuid'])) {
        // Respect the exported "Generate automatic URL alias" checkbox
        // state. If it wasn't exported (older export, or field doesn't
        // support pathauto), default to SKIP so the manual alias we're
        // about to create below isn't immediately overwritten.
        $pathauto_state = $value['pathauto'] ?? PathautoState::SKIP;

        // We need the entity's id, so we must ensure the entity has been saved
        // before attempting to create a path alias.
        if ($field->getEntity()->isNew()) {
          // Disable pathauto to prevent it from creating an alias. The
          // entity's other fields aren't populated yet at this point, so
          // this early save must never let pathauto generate an alias from
          // incomplete data. The real, exported pathauto state is applied
          // below via $values so it takes effect on the final save instead.
          if ($has_pathauto) {
            // Zero is the value of \Drupal\pathauto\PathautoState::SKIP.
            $field->pathauto = 0;
          }
          $field->getEntity()->save();
        }

        $path = $field->getEntity()->toUrl(NULL, ['path_processing' => FALSE])->toString();

        // If an existing alias can be found, update it.
        // Check for exact UUID, fallback to existing alias and langcode.
        $existing_alias = $this->entityRepository->loadEntityByUuid('path_alias', $value['target_uuid']);
        if (!$existing_alias && isset($value['alias'], $value['langcode'])) {
          $existing_alias = $this->entityTypeManager->getStorage('path_alias')->loadByProperties([
            'alias' => $value['alias'],
            'langcode' => $value['langcode'],
          ]);
          $existing_alias = reset($existing_alias);
        }

        if ($existing_alias instanceof PathAliasInterface) {
          /** @var \Drupal\path_alias\PathAliasInterface $existing_alias */
          // Update the existing alias to have the new target UUID.
          $existing_alias->set('uuid', $value['target_uuid']);
          $existing_alias->setPath($path);
          $existing_alias->setAlias($value['alias']);
          $existing_alias->save();
          $values[$delta]['pid'] = $existing_alias->id();
          if ($has_pathauto) {
            $values[$delta]['pathauto'] = $pathauto_state;
          }
          continue;
        }

        // No path alias exists that will conflict with our import, create it.
        $path_alias = $this->entityTypeManager->getStorage('path_alias')->create([
          'uuid' => $value['target_uuid'],
          'alias' => $value['alias'],
          'langcode' => $value['langcode'],
          'path' => $path,
          'status' => 1,
        ]);
        $path_alias->save();
        $values[$delta]['pid'] = $path_alias->id();
        if ($has_pathauto) {
          $values[$delta]['pathauto'] = $pathauto_state;
        }
      }
    }

    return parent::alterImportValue($values, $field);
  }

}
