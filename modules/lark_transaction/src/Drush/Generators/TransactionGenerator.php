<?php

declare(strict_types=1);

namespace Drupal\lark_transaction\Drush\Generators;

use DrupalCodeGenerator\Asset\AssetCollection as Assets;
use DrupalCodeGenerator\Attribute\Generator;
use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\GeneratorType;
use Drush\Commands\AutowireTrait;

/**
 * @deprecated
 *   Remove in v2.
 */
#[Generator(
  name: 'lark:transaction:plugin',
  description: 'Generates a Lark Transaction plugin.',
  aliases: ['lark'],
  templatePath: __DIR__,
  type: GeneratorType::MODULE_COMPONENT,
)]
class TransactionGenerator extends BaseGenerator {

  use AutowireTrait;

  /**
   * {@inheritdoc}
   */
  protected function generate(array &$vars, Assets $assets): void {
    $ir = $this->createInterviewer($vars);
    $vars['machine_name'] = $ir->askMachineName();
    $vars['plugin_id'] = $ir->ask('Plugin ID', $vars['machine_name']);
    $vars['class'] = $ir->askClass('Class name', '{plugin_id|camelize}');
    $vars['label'] = $ir->ask('Label', '{plugin_id|m2t}');
    $vars['description'] = $ir->ask('Description', 'Transaction for {label}.');
    $vars['enabled'] = $ir->ask('Enabled', 'TRUE');
    $vars['repeatable'] = $ir->ask('Repeatable', 'FALSE');
    $vars['weight'] = $ir->ask('Weight', '0');

    $assets->addFile('src/Plugin/LarkTransaction/{class}.php', 'transaction-generator.twig');
  }

}
