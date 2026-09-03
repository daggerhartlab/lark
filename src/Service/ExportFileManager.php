<?php

namespace Drupal\lark\Service;

use Drupal\Component\Graph\Graph;
use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Utility\SortArray;
use Drupal\lark\Model\ExportArray;
use Drupal\lark\Model\ExportCollection;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Finder\Finder as SymfonyFinder;

class ExportFileManager {

  /**
   * Copy of core service functionality.
   *
   * @return \Drupal\lark\Model\ExportCollection
   *   Array of exports with dependencies.
   *
   * @see \Drupal\Core\DefaultContent\Finder
   */
  public function discoverExports(string $directory): ExportCollection {
    $collection = new ExportCollection();
    try {
      // Scan for all YAML files in the content directory.
      $finder = SymfonyFinder::create()
        ->in($directory)
        ->files()
        ->name('*.yml');
    }
    catch (DirectoryNotFoundException) {
      return $collection;
    }

    $graph = [];
    $files = new ExportCollection();
    /** @var \Symfony\Component\Finder\SplFileInfo $file */
    foreach ($finder as $file) {
      $export = new ExportArray(Yaml::decode($file->getContents()));
      $export->setPath($file->getPathname());
      $files->add($export);

      // For the graph to work correctly, every entity must be mentioned in it.
      // This is inspired by
      // \Drupal\Core\Config\Entity\ConfigDependencyManager::getGraph().
      $graph += [
        $export->uuid() => [
          'edges' => [],
          'uuid' => $export->uuid(),
        ],
      ];

      foreach ($export->dependencies() as $dependency_uuid => $entity_type) {
        $graph[$dependency_uuid]['edges'][$export->uuid()] = TRUE;
        $graph[$dependency_uuid]['uuid'] = $dependency_uuid;
      }
    }
    ksort($graph);

    // Sort the dependency graph. The entities that are dependencies of other
    // entities should come first.
    $graph_object = new Graph($graph);
    $sorted = $graph_object->searchAndSort();
    uasort($sorted, SortArray::sortByWeightElement(...));

    foreach ($sorted as ['uuid' => $uuid]) {
      if ($files->has($uuid)) {
        $collection->add($files->get($uuid));
      }
    }

    return $collection;
  }

  /**
   * Remove an export and its dependencies.
   *
   * @param string $directory
   *   The directory to scan for exports.
   * @param string $uuid
   *   The UUID of the export to remove.
   */
  public function removeExportWithDependencies(string $directory, string $uuid): int {
    $removed = 0;
    $collection = $this->discoverExports($directory);
    if (!$collection->has($uuid)) {
      return $removed;
    }

    $removal_candidates = $collection->getWithDependencies($uuid);
    // Remove our candidates from the collection to create a collection that
    // contains only the exports that are not being removed.
    $pruned_collection = $collection->diff($removal_candidates);

    // We need to filter out of the remove_exports array any item that is a
    // dependency of another item in the all_exports array. This produces a
    // collection of exports that can be safely removed.
    $removal_safe = $removal_candidates->filter(function ($removal_candidate) use ($pruned_collection) {
      foreach ($pruned_collection as $export) {
        if ($export->hasDependency($removal_candidate->uuid())) {
          return FALSE;
        }
      }

      return TRUE;
    });

    // If the item we want to remove is not in the "safe" array, we can't remove
    // it, and don't want to remove its dependencies either.
    if (!$removal_safe->has($uuid)) {
      return $removed;
    }

    foreach ($removal_safe as $export) {
      \unlink($export->path());
      $removed += 1;

      if ($export->isFile() && $export->fileAssetIsExported(\dirname($export->path()))) {
        \unlink(\dirname($export->path()) . DIRECTORY_SEPARATOR . $export->fileAssetFilename());
        $removed += 1;
      }
    }

    return $removed;
  }

  /**
   * Find exports that would be left with a dangling soft reference.
   *
   * A soft reference is never traversed, so removing its target does not
   * widen or block a prune - it only leaves the referencing export's
   * `target_uuid` unresolvable. This method does not decide whether a prune
   * proceeds; it only reports what would break so the caller can warn.
   *
   * @param \Drupal\lark\Model\ExportCollection $all_exports
   *   The full set of exports discovered for a source.
   * @param \Drupal\lark\Model\ExportCollection $removal_candidates
   *   The exports that would be removed.
   *
   * @return \Drupal\lark\Model\ExportCollection
   *   Exports outside the removal set that reference one of its members.
   */
  public function findExportsReferencingRemovalSet(ExportCollection $all_exports, ExportCollection $removal_candidates): ExportCollection {
    $remaining = $all_exports->diff($removal_candidates);

    return $remaining->filter(function (ExportArray $export) use ($removal_candidates) {
      foreach ($removal_candidates as $candidate) {
        if ($export->hasReference($candidate->uuid())) {
          return TRUE;
        }
      }

      return FALSE;
    });
  }

}
