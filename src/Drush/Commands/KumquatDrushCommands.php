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
    $command = "composer --working-dir=$composerRoot update $package";
    if (!empty($options['with-all-dependencies']) || !empty($options['W'])) {
      $command .= ' -W';
    }
    elseif (!empty($options['with-dependencies']) || !empty($options['w'])) {
      $command .= ' -w';
    }
    $process = $this->processManager()->shell($command);
    $process->run($process->showRealtime());
    $this->io()->newLine();

    $this->io()->section('Running drush updb.');
    $process = $this->processManager()->shell("drush updb -y");
    $process->run($process->showRealtime());
    $this->io()->newLine();

    $this->io()->section('Running drush cex.');
    $process = $this->processManager()->shell("drush cex -y");
    $process->run($process->showRealtime());
    $this->io()->newLine();

    $this->io()->section('Running git add.');
    $process = $this->processManager()->shell("git add -v composer.* config/sync");
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
      $process = $this->processManager()->shell($command);
      $process->run($process->showRealtime());
    }
    $this->io()->newLine();

    ini_set('memory_limit', $mem);
  }

}
