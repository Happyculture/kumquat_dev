<?php

namespace Drupal\kumquat_dev\Drush\Generators;

use DrupalCodeGenerator\Asset\AssetCollection as Assets;
use DrupalCodeGenerator\Attribute\Generator;
use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\GeneratorType;
use DrupalCodeGenerator\Utils;
use DrupalCodeGenerator\Validator\Chained;
use DrupalCodeGenerator\Validator\MachineName;
use DrupalCodeGenerator\Validator\Required;

#[Generator(
  name: 'kumquat:pattern',
  description: 'Generates Pattern',
  aliases: ['pattern'],
  templatePath: __DIR__ . '/templates/pattern',
  type: GeneratorType::THEME_COMPONENT,
  label: 'Pattern (UI Patterns)',
)]
final class PatternGenerator extends BaseGenerator {

  /**
   * {@inheritdoc}
   */
  protected function generate(array &$vars, Assets $assets): void {
    $ir = $this->createInterviewer($vars);

    $vars['machine_name'] = $ir->askMachineName();

    $vars['pattern_name'] = $ir->ask('Pattern label', 'My pattern');

    $vars['pattern_machine_name'] = $ir->ask('Pattern machine name', Utils::human2machine($vars['pattern_name']), new Chained(new Required(), new MachineName()));
    $vars['pattern_machine_name_dashed'] = str_replace('_', '-', $vars['pattern_machine_name']);
    $vars['pattern_machine_name_camel'] = str_replace(' ', '', ucwords(str_replace('_', ' ', $vars['pattern_machine_name'])));

    $types = ['atoms', 'molecules', 'organisms', '- none -', '- other -'];
    $vars['pattern_type'] = $ir->choice('Pattern type', \array_combine($types, $types));
    if ($vars['pattern_type'] === '- other -') {
      $vars['pattern_type'] = $ir->ask('Specify pattern type (subdirectory name)');
    }

    $vars['pattern_has_style'] = $ir->confirm('Add style?');

    $vars['pattern_has_js'] = $ir->confirm('Add javascript?');

    $vars['pattern_dir'] = 'templates/patterns';
    if (!empty($vars['pattern_type']) && $vars['pattern_type'] !== '- none -') {
      $vars['pattern_dir'] .= '/{pattern_type}';
    }
    $vars['pattern_dir'] .= '/{pattern_machine_name}';

    $assets->addFile('{pattern_dir}/{pattern_machine_name}.pattern.yml', 'pattern-yml.twig');
    $assets->addFile('{pattern_dir}/pattern-{pattern_machine_name_dashed}.html.twig', 'pattern-twig.twig');
    if ($vars['pattern_has_style']) {
      $assets->addFile('{pattern_dir}/{pattern_machine_name}.scss', 'pattern-scss.twig');
    }
    if ($vars['pattern_has_js']) {
      $assets->addFile('{pattern_dir}/{pattern_machine_name}.js', 'pattern-js.twig');
    }
  }

}
