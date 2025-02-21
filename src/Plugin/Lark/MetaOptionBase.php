<?php

declare(strict_types=1);

namespace Drupal\lark\Plugin\Lark;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Utility\Html;
use Drupal\Core\Archiver\ArchiveTar;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\lark\Model\ExportableInterface;
use Drupal\lark\Model\LarkSettings;
use Drupal\lark\Routing\EntityTypeInfo;
use Drupal\lark\Service\AssetFileManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for lark_entity_export_form plugins.
 */
abstract class MetaOptionBase extends PluginBase implements MetaOptionInterface,  ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected LarkSettings $larkSettings,
    protected AssetFileManager $assetFileManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(LarkSettings::class),
      $container->get(AssetFileManager::class)
    );
  }

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return $this->getPluginId();
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    // Cast the label to a string since it is a TranslatableMarkup object.
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function description(): string {
    // Cast the label to a string since it is a TranslatableMarkup object.
    return (string) $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function applies(EntityInterface $entity): bool {
    return $entity->getEntityType()->get(EntityTypeInfo::IS_EXPORTABLE);
  }

  /**
   * {@inheritdoc}
   */
  //public function validateElement(array &$element, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function processFormValues(array $submitted_values, ExportableInterface $exportable, FormStateInterface $form_state): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function preExportWrite(ExportableInterface $exportable): void {}

  /**
   * {@inheritdoc}
   */
  public function preExportDownload(ArchiveTar $archive, ExportableInterface $exportable): void {}

  /**
   * Takes a normal render array for a radios element and makes it work within
   * a rendered table element. This solves a core Drupal bug where Radios are
   * not rendered at all within a table.
   *
   * @link https://www.drupal.org/project/drupal/issues/3246825
   *
   * @param array $radios
   *   Normal radios render array.
   *
   * @return array
   *   Fixed render array with child Radio (singular) elements.
   */
  protected function fixNestedRadios(array &$form, array $render_parents, string $render_name, array $radios): array {
    // First item in the parents array is the "tree name".
    $tree_name = reset($render_parents);

    // This container contains a hidden field that registers the $tree_name with
    // the $form_state. This trick allows our custom-rendered radios to be found
    // in $form_state->getValue($tree_name);
    if ($tree_name && !isset($form[$tree_name])) {
      $form[$tree_name] = [
        '#type' => 'hidden',
      ];
    }

    // Build the <input> element names and ids we'll need.
    $parent_names = $render_parents;
    $parent_name = array_shift($parent_names);
    if ($parent_name && $parent_names) {
      $parent_name .= '[' . implode('][', $parent_names) . ']';
    }

    $child_name = $parent_name ?
      $parent_name . "[$render_name]" :
      $render_name;

    $radios['#id'] = $radios['#id'] ?? Html::getUniqueId($child_name);
    $radios['#title_display'] = $radios['#title_display'] ?? 'visible';
    $radios['#description_display'] = $radios['#description_display'] ?? 'visible';
    $radios['#default_value'] = $radios['#default_value'] ?? FALSE;
    $radios['#attributes'] = $radios['#attributes'] ?? [];
    $radios['#parents'] = $render_parents;

    // Render each of the radios options as a single radio element. Neither
    // $form nor $form_state are actually used in this process, just required.
    $form_state = new FormState();
    $radios = Element\Radios::processRadios($radios, $form_state, $form);

    foreach (Element::children($radios) as $index) {
      // Radios::processRadios() doesn't set the #value field for the child
      // radio elements, but later the Radio::preRenderRadio() method will
      // expect it. We can set these values from the $radios #default_value if
      // needed.
      // - '#return_value' is the "value='123'" attribute for the form element.
      // - '#value' is the over-all value of the radios group of elements.
      $radios[$index]['#value'] = $radios[$index]['#value'] ?? $radios['#default_value'];

      // Some other part of the rendering process isn't working, and this field
      // rendered as an <input> ends up not having a "name" attribute.
      $radios[$index]['#name'] = $child_name;

      if ($radios['#disabled']) {
        $radios[$index]['#disabled'] = TRUE;
        $radios[$index]['#attributes']['disabled'] = 'disabled';
      }
    }

    return $radios;
  }

}
