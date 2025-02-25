<?php

namespace Drupal\lark\Service;

use Drupal\Component\Graph\Graph;
use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Utility\SortArray;
use Drupal\lark\Exception\LarkImportException;
use Drupal\lark\Model\ExportArray;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Finder\Finder as SymfonyFinder;

class ExportFileManager {

  /**
   * Copy of core service functionality.
   *
   * @return \Drupal\lark\Model\ExportArray[]
   *   Array of exports with dependencies.
   *
   * @see \Drupal\Core\DefaultContent\Finder
   */
  public function discoverExports(string $directory): array {
    try {
      // Scan for all YAML files in the content directory.
      $finder = SymfonyFinder::create()
        ->in($directory)
        ->files()
        ->name('*.yml');
    }
    catch (DirectoryNotFoundException) {
      return [];
    }

    $graph = $files = [];
    /** @var \Symfony\Component\Finder\SplFileInfo $file */
    foreach ($finder as $file) {
      $export = new ExportArray(Yaml::decode($file->getContents()));
      $export->setPath($file->getPathname());
      $uuid = $export->uuid();
      $files[$uuid] = $export;

      // For the graph to work correctly, every entity must be mentioned in it.
      // This is inspired by
      // \Drupal\Core\Config\Entity\ConfigDependencyManager::getGraph().
      $graph += [
        $uuid => [
          'edges' => [],
          'uuid' => $uuid,
        ],
      ];

      foreach ($export->dependencies() as $dependency_uuid => $entity_type) {
        $graph[$dependency_uuid]['edges'][$uuid] = TRUE;
        $graph[$dependency_uuid]['uuid'] = $dependency_uuid;
      }
    }
    ksort($graph);

    // Sort the dependency graph. The entities that are dependencies of other
    // entities should come first.
    $graph_object = new Graph($graph);
    $sorted = $graph_object->searchAndSort();
    uasort($sorted, SortArray::sortByWeightElement(...));

    $exports = [];
    foreach ($sorted as ['uuid' => $uuid]) {
      if (array_key_exists($uuid, $files)) {
        $exports[$uuid] = $files[$uuid];
      }
    }
    return $exports;
  }

  /**
   * Discover an export and its dependencies.
   *
   * @param string $directory
   *   Directory to scan for exports.
   * @param string $uuid
   *   UUID of the export to discover.
   *
   * @return \Drupal\lark\Model\ExportArray[]
   *   Array of discovered exports.
   */
  public function discoverExportWithDependencies(string $directory, string $uuid): array {
    return $this->filterExportWithDependencies($uuid, $this->discoverExports($directory));
  }

  public function removeExportWithDependencies(string $directory, string $uuid) {
    $all_exports = $this->discoverExports($directory);
    if (!isset($all_exports, $uuid)) {
      return;
    }

    // Get our removal candidates and remove them from the list of all exports.
    $removal_candidates = $this->filterExportWithDependencies($uuid, $all_exports);
    $all_exports = array_diff_key($all_exports, $removal_candidates);

    // We need to filter out of the remove_exports array any item that is a
    // dependency of another item in the all_exports array.
    /** @var ExportArray[] $removal_safe */
    $removal_safe = array_filter($removal_candidates, function ($export) use ($all_exports) {
      foreach ($all_exports as $all_export) {
        if ($all_export->hasDependency($export->uuid())) {
          return FALSE;
        }
      }

      return TRUE;
    });

    // If the item we want to remove is not in the removal_safe array, we can't
    // remove it.
    if (!isset($removal_safe[$uuid])) {
      return;
    }

    foreach ($removal_safe as $export) {
      \unlink($export->path());

      if ($export->isFile() && $export->fileAssetIsExported(\dirname($export->path()))) {
        \unlink(\dirname($export->path()) . DIRECTORY_SEPARATOR . $export->fileAssetFilename());
      }
    }
  }

  /**
   * Gather dependencies for a single $uuid.
   *
   * @param string $uuid
   *   UUID of the export.
   * @param \Drupal\lark\Model\ExportArray[] $exports
   *   Array of exports that contain the dependencies for the given uuid.
   * @param array $found
   *   Reference array to track all dependencies to prevent duplicates.
   *
   * @return \Drupal\lark\Model\ExportArray[]
   *   Array of export with dependencies.
   */
  public function filterExportWithDependencies(string $uuid, array $exports, array &$found = []): array {
    if (!isset($exports[$uuid])) {
      throw new LarkImportException('Export with UUID ' . $uuid . ' not found.');
    }

    $export = $exports[$uuid];
    $dependencies = [];
    foreach ($export->dependencies() as $dependency_uuid => $entity_type) {
      // Look for the dependency export.
      // @todo - Handle missing dependencies?
      if (
        isset($exports[$dependency_uuid])
        // Don't recurse into dependency if it's already been registered.
        && !array_key_exists($dependency_uuid, $found)
      ) {
        // Recurse and get dependencies of this dependency.
        if (!empty($exports[$dependency_uuid]->dependencies())) {

          // Register the dependency to prevent redundant calls.
          $found[$dependency_uuid] = NULL;
          $dependencies += $this->filterExportWithDependencies($dependency_uuid, $exports, $found);
        }

        // Add the dependency itself.
        $dependencies[$dependency_uuid] = $exports[$dependency_uuid];
        $found[$dependency_uuid] = $exports[$dependency_uuid];
      }
    }

    // Add the entity itself last.
    $dependencies[$uuid] = $export;
    return $dependencies;
  }

}
