<?php

namespace Drupal\kumquat_dev\Drush\Generators;

use DrupalCodeGenerator\Command\ThemeGenerator;
use DrupalCodeGenerator\Utils;

/**
 * Implements pattern generator.
 */
final class PatternGenerator extends ThemeGenerator {

  /**
   * {@inheritdoc}
   */
  protected string $name = 'kumquat:pattern';

  /**
   * {@inheritdoc}
   */
  protected string $alias = 'pattern';

  /**
   * {@inheritdoc}
   */
  protected string $description = 'Generates Pattern';

  /**
   * {@inheritdoc}
   */
  protected string $templatePath = __DIR__ . '/templates/pattern';

  /**
   * {@inheritdoc}
   */
  protected function generate(array &$vars): void {
    $this->collectDefault($vars);

    $vars['pattern_name'] = $this->ask('Pattern label', 'My pattern');
    $vars['pattern_machine_name'] = $this->ask('Pattern machine name', Utils::human2machine($vars['pattern_name']), '::validateRequiredMachineName');
    $vars['pattern_machine_name_dashed'] = str_replace('_', '-', $vars['pattern_machine_name']);
    $vars['pattern_machine_name_camel'] = str_replace(' ', '', ucwords(str_replace('_', ' ', $vars['pattern_machine_name'])));
    $types = ['atoms', 'molecules', 'organisms', '- none -', '- other -'];
    $vars['pattern_type'] = $this->choice('Pattern type', \array_combine($types, $types));
    if ($vars['pattern_type'] === '- other -') {
      $vars['pattern_type'] = $this->ask('Specify pattern type (subdirectory name)');
    }
    $vars['pattern_has_style'] = $this->confirm('Add style?');
    $vars['pattern_has_js'] = $this->confirm('Add javascript?');

    $vars['pattern_dir'] = 'templates/patterns';
    if (!empty($vars['pattern_type']) && $vars['pattern_type'] !== '- none -') {
      $vars['pattern_dir'] .= '/{pattern_type}';
    }
    $vars['pattern_dir'] .= '/{pattern_machine_name}';

    $this->addFile('{pattern_dir}/{pattern_machine_name}.pattern.yml', 'pattern-yml');
    $this->addFile('{pattern_dir}/pattern-{pattern_machine_name_dashed}.html.twig', 'pattern-twig');
    if ($vars['pattern_has_style']) {
      $this->addFile('{pattern_dir}/{pattern_machine_name}.scss', 'pattern-scss');
    }
    if ($vars['pattern_has_js']) {
      $this->addFile('{pattern_dir}/{pattern_machine_name}.js', 'pattern-js');
    }
  }

}
