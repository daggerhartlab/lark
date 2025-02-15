<?php

namespace Drupal\lark\Plugin\Lark\EntityExportForm;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\lark\Attribute\LarkEntityExportForm;
use Drupal\lark\Model\ExportableInterface;
use Drupal\lark\Plugin\Lark\EntityExportFormPluginBase;

#[LarkEntityExportForm(
  id: "yaml_export",
  label: new TranslatableMarkup("Yaml Export"),
  description: new TranslatableMarkup("Displays the exportable as Yaml."),
)]
class YamlExport extends EntityExportFormPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildElement(ExportableInterface $exportable, array &$form, FormStateInterface $form_state, array $render_parents): array {
    return  [
      '#type' => 'html_tag',
      '#tag' => 'pre',
      '#value' => Markup::create(\htmlentities($exportable->toYaml())),
      '#attributes' => [
        'class' => ['lark-yaml-export-pre'],
      ],
      '#weight' => 100,
    ];
  }

}
