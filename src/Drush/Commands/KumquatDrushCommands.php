<?php

namespace Drupal\kumquat_dev\Drush\Commands;

use Composer\InstalledVersions;
use Drupal\Component\FileSecurity\FileSecurity;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Extension\ExtensionList;
use Drupal\Core\Extension\InfoParserInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\language\Config\LanguageConfigOverride;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\CommandFailedException;

/**
 * Kumquat specific drush commands.
 */
class KumquatDrushCommands extends DrushCommands {

  /**
   * The modules list service.
   *
   * @var \Drupal\Core\Extension\ExtensionList
   */
  protected ExtensionList $moduleExtensionList;

  /**
   * The configuration manager service.
   *
   * @var \Drupal\Core\Config\ConfigManagerInterface
   */
  protected ConfigManagerInterface $configManager;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The info file parser service.
   *
   * @var \Drupal\Core\Extension\InfoParserInterface
   */
  protected InfoParserInterface $infoParser;

  /**
   * The configuration storage service.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected StorageInterface $configStorage;

  /**
   * Class constructor.
   */
  public function __construct(ExtensionList $moduleExtensionList, ConfigManagerInterface $configManager, LanguageManagerInterface $languageManager, InfoParserInterface $infoParser, StorageInterface $configStorage) {
    $this->moduleExtensionList = $moduleExtensionList;
    $this->configManager = $configManager;
    $this->languageManager = $languageManager;
    $this->infoParser = $infoParser;
    $this->configStorage = $configStorage;
  }

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

  /**
   * Export configuration and config dependencies to the module/optional dir.
   *
   * Get the object to export or exclude from the module's info.yml file.
   *
   * For example:
   *
   * @code
   * kumquat_export:
   *   include:
   *     - paragraphs.paragraphs_type.rich_text
   *   exclude:
   *     - field.storage.paragraph.rich_text_style
   * @endcode
   *
   * @param string $module
   *   An existing module machine name.
   *
   * @command kumquat:export-config
   * @aliases kex
   */
  public function exportConfig($module) {
    if (!$this->moduleExtensionList->exists($module)) {
      throw new CommandFailedException('Module does not exists');
    }

    $module_dir = $this->moduleExtensionList->getPath($module);
    $info = $this->infoParser->parse(DRUPAL_ROOT . '/' . $module_dir . '/' . $module . '.info.yml');
    if (empty($info['kumquat_export']['include'])) {
      throw new CommandFailedException('Nothing to export! Check the kumquat_export.include key of your info.yml file.');
    }

    $languages = $this->languageManager->getLanguages();
    $directoryStorage = $this->prepareFileStorage(DRUPAL_ROOT . '/' . $module_dir . '/config/optional');

    $toExport = $info['kumquat_export']['include'];
    $toExclude = $info['kumquat_export']['exclude'] ?? [];

    $toExport = $this->getDependencies($toExport, $toExclude);
    $exported = [];

    // Purge existing config files.
    $directoryStorage->deleteAll();
    // Export all the selected configurations.
    foreach ($toExport as $config_name) {
      try {
        $data = $this->configStorage->read($config_name);
        unset($data['uuid'], $data['_core']);
        $directoryStorage->write($config_name, $data);
        $exported[] = $config_name;

        foreach ($languages as $language) {
          $langcode = $language->getId();
          if ($langcode === $data['langcode']) {
            continue;
          }

          $override = $this->languageManager->getLanguageConfigOverride($langcode, $config_name);
          if (!($override instanceof LanguageConfigOverride)) {
            continue;
          }
          $translation = $override->get();
          if (empty($translation)) {
            continue;
          }
          $languageDirectoryStorage = $this->prepareFileStorage(DRUPAL_ROOT . '/' . $module_dir . '/config/optional/language/' . $langcode);
          $languageDirectoryStorage->write($config_name, $translation);
          $exported[] = 'translation/' . $langcode . '/' . $config_name;
        }
      }
      catch (\TypeError $e) {
        throw new CommandFailedException(dt('Source not found for @name.', ['@name' => $config_name]));
      }
    }

    $this->io()->success(new FormattableMarkup('@count files exported in @directory', [
      '@count' => count($exported),
      '@directory' => $module_dir . '/config/optional',
    ]));
    sort($exported);
    $this->io()->listing($exported);
  }

  /**
   * Recursively get export dependencies but respect config to exclude.
   *
   * @param array $toExport
   *   Array of config object names to export.
   * @param array $toExclude
   *   Array of config object names to exclude.
   *
   * @return array
   *   Array of config object names to export including dependencies and
   *   dependents but excluding objects marked to exclude.
   */
  protected function getDependencies(array $toExport = [], array $toExclude = []) {
    $initialToExport = $toExport;
    $dependenciesList = [];

    // First use the ConfigManager to get dependent config objects.
    do {
      $oldToExport = $toExport;

      $dependenciesList += $this->configManager->findConfigEntityDependencies('config', $toExport);
      $toExport = array_unique(array_merge($toExport, array_keys($dependenciesList)));
      // Don't forget to exclude config objects we don't want.
      $toExport = array_diff($toExport, $toExclude);
    } while ($oldToExport !== $toExport);

    // Then parse objects to export to extract their config dependencies.
    foreach ($toExport as $config_name) {
      if (!empty($dependenciesList[$config_name])) {
        $dependencies = $dependenciesList[$config_name]->getDependencies('config');
      }
      else {
        $dependencies = $this->configManager->getConfigFactory()->get($config_name)->get('dependencies')['config'] ?? [];
      }
      $toExport = array_merge($toExport, $dependencies);
      // Don't forget to exclude config objects we don't want.
      $toExport = array_diff($toExport, $toExclude);
    }

    // Avoid duplicates.
    $toExport = array_unique($toExport);

    // If the list changed since the beginning of this method, loop.
    if ($initialToExport !== $toExport) {
      $toExport = $this->getDependencies($toExport, $toExclude);
    }
    return $toExport;
  }

  /**
   * Helper to retrieve the FileStorage object associated to our output dir.
   *
   * BONUS: this method prepares the destination dir to export the config files.
   * This code if mostly duplicated from
   * \Drupal\config_split\ConfigSplitManager::getSplitStorage()
   * with a focus on the directory target use case.
   *
   * @param string $directory
   *   Directory where the exported config will be located.
   *
   * @return \Drupal\Core\Config\FileStorage|null
   *   The FileStorage instance allowing to write in the directory.
   */
  protected function prepareFileStorage($directory) {
    // Here we could determine to use relative paths etc.
    if (!is_dir($directory)) {
      // If the directory doesn't exist, attempt to create it.
      // This might have some negative consequences, but we trust the user to
      // have properly configured their site.
      /* @noinspection MkdirRaceConditionInspection */
      @mkdir($directory, 0777, TRUE);
    }
    // The following is roughly: file_save_htaccess($directory, TRUE, TRUE);
    // But we can't use global drupal functions, and we want to write the
    // .htaccess file to ensure the configuration is protected and the
    // directory not empty.
    if (file_exists($directory) && is_writable($directory)) {
      $htaccess_path = rtrim($directory, '/\\') . '/.htaccess';
      if (!file_exists($htaccess_path)) {
        file_put_contents($htaccess_path, FileSecurity::htaccessLines(TRUE));
        @chmod($htaccess_path, 0444);
      }
    }

    if (file_exists($directory) || strpos($directory, 'vfs://') === 0) {
      // Allow virtual file systems even if file_exists is false.
      return new FileStorage($directory);
    }

    return NULL;
  }

}
