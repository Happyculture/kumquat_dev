<?php

namespace Drupal\kumquat_dev\Drush\Commands;

use Composer\InstalledVersions;
use DrupalFinder\DrupalFinderComposerRuntime;
use Drush\Commands\DrushCommands;

/**
 * Kumquat specific drush commands.
 */
class KumquatDrushCommands extends DrushCommands {

  /**
   * Update module.
   *
   * @param string $package
   *   A package machine name. (If vendor is not specified it will be
   *   transformed to drupal/$package).
   * @param array $options
   *   Command options.
   *
   * @command kumquat:update
   * @option with-dependencies Add soft dependencies (-w) to the composer update.
   * @option w Same as --with-dependencies.
   * @option with-all-dependencies Add hard dependencies (-W) to the composer update.
   * @option W Same as --with-all-dependencies.
   * @option auto-commit Run the commit command instead of outputing it. Only recommended if your workspace is clean.
   * @option AC Same as --auto-commit.
   * @aliases kup
   */
  #[CLI\Command(name: 'kumquat:update', aliases: ['kup', 'kumquat-update'])]
  #[CLI\Argument(name: 'package', description: 'The composer package to update.')]
  #[CLI\Option(name: 'with-dependencies', description: 'Add soft dependencies (-w) to the composer update.')]
  #[CLI\Option(name: 'w', description: 'Same as --with-dependencies.')]
  #[CLI\Option(name: 'with-all-dependencies', description: 'Add hard dependencies (-W) to the composer update.')]
  #[CLI\Option(name: 'W', description: 'Same as --with-all-dependencies.')]
  #[CLI\Option(name: 'auto-commit', description: 'Run the commit command instead of outputing it. Only recommended if your workspace is clean.')]
  #[CLI\Option(name: 'AC', description: 'Same as --auto-commit.')]
  #[CLI\Usage(name: 'drush kumquat:update drupal/admin_toolbar', description: 'Update the admin_toolbar module using composer.')]
  #[CLI\Usage(name: 'drush kumquat:update drupal/core* --with-dependencies', description: 'Update Drupal Core with its dependencies.')]
  public function update($package, array $options = [
    'with-dependencies' => FALSE,
    'w' => FALSE,
    'with-all-dependencies' => FALSE,
    'W' => FALSE,
    'auto-commit' => FALSE,
    'AC' => FALSE,
  ]) {
    $mem = ini_set('memory_limit', '-1');
    $composerRoot = method_exists($this->processManager(), 'getDrupalFinder') ?
      $this->processManager()->getDrupalFinder()->getComposerRoot() :
      (new DrupalFinderComposerRuntime())->getComposerRoot();

    if (strpos($package, '/') === FALSE) {
      $package = "drupal/$package";
    }

    $this->io()->section('Running composer update.');
    $command = "composer update $package";
    if (!empty($options['with-all-dependencies']) || !empty($options['W'])) {
      $command .= ' -W';
    }
    elseif (!empty($options['with-dependencies']) || !empty($options['w'])) {
      $command .= ' -w';
    }
    $process = $this->processManager()->shell($command, $composerRoot);
    $process->run($process->showRealtime());
    $this->io()->newLine();

    $this->io()->section('Running drush updb.');
    $process = $this->processManager()->shell("./vendor/bin/drush updb -y", $composerRoot);
    $process->run($process->showRealtime());
    $this->io()->newLine();

    $this->io()->section('Running drush cex.');
    $process = $this->processManager()->shell("./vendor/bin/drush cex -y", $composerRoot);
    $process->run($process->showRealtime());
    $this->io()->newLine();

    $this->io()->section('Running git add.');
    $process = $this->processManager()->shell("git add -v composer.* config/sync", $composerRoot);
    $process->run($process->showRealtime());
    $this->io()->newLine();

    InstalledVersions::reload([]);
    if ($package === 'drupal/core-*') {
      $version = InstalledVersions::getPrettyVersion('drupal/core');
    }
    else {
      $version = strpos($package, '*') === FALSE ? InstalledVersions::getPrettyVersion($package) : 'VERSION';
    }
    $commit_msg = "Update $package to version $version.";
    $command = "git commit -m '" . strtr($commit_msg, "'", "\\'") . "'";

    $autocommit = !empty($options['auto-commit']) || !empty($options['AC']);
    if (!$autocommit) {
      $this->io()->section('Review time.');
      $this->io()->text('Check your git working space and commit using the following command:');
      $this->io()->text("$ $command");
      $autocommit = $this->io()->confirm('…or do you want this to be commited for you?', FALSE);
    }
    if ($autocommit) {
      $this->io()->section('Commiting changes.');
      $process = $this->processManager()->shell($command, $composerRoot);
      $process->run($process->showRealtime());
    }
    $this->io()->newLine();

    ini_set('memory_limit', $mem);
  }

}
