<?php

namespace Drupal\lark\Service\Utility;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\lark\Entity\LarkSourceInterface;
use Drupal\lark\Model\ExportableInterface;
use Drupal\lark\Service\ExportableFactoryInterface;
use Drupal\lark\Service\ImporterInterface;

class SourceUtility {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ExportableFactoryInterface $exportableFactory,
    protected ImporterInterface $importer,
  ) {}

  /**
   * @param \Drupal\lark\Entity\LarkSourceInterface $source
   * @param string $root_uuid
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getRootDependencyExportables(LarkSourceInterface $source, string $root_uuid): array {
    $dependency_exports = $this->importer->discoverSourceExport($source, $root_uuid);
    $dependency_exports = array_reverse($dependency_exports);
    $dependency_exportables = [];
    foreach ($dependency_exports as $dependency_uuid => $dependency_export) {
      if ($dependency_uuid === $root_uuid) {
        continue;
      }

      $dependency_exportable = $this->exportableFactory->createFromSource($source->id(), $dependency_uuid);
      if ($dependency_exportable) {
        $dependency_exportables[] = $dependency_exportable;
      }
    }

    return $dependency_exportables;
  }

  /**
   * Build operation links for given exportable.
   *
   * @param \Drupal\lark\Entity\LarkSourceInterface $source
   *   Source plugin.
   * @param \Drupal\lark\Model\ExportableInterface $exportable
   *   Exportable entity.
   *
   * @return array
   *   Render array.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function getExportableOperations(LarkSourceInterface $source, ExportableInterface $exportable) {
    // Determine export status and possible operations.
    $operations = [];

    if ($exportable->entity()->isNew()) {
      $operations['import'] = [
        'title' => $this->t('Import'),
        'url' => Url::fromRoute('lark.action_import_source_entity', [
          'lark_source' => $source->id(),
          'uuid' => $exportable->entity()->uuid(),
        ]),
      ];
    }
    if (!$exportable->entity()->isNew()) {
      $entity_type = $this->entityTypeManager->getDefinition($exportable->entity()->getEntityTypeId());

      if ($entity_type->hasLinkTemplate('canonical')) {
        $operations['view'] = [
          'title' => $this->t('View'),
          'url' => $exportable->entity()->toUrl()->setRouteParameter('lark_source', $source->id()),
        ];
      }
      if ($entity_type->hasLinkTemplate('edit-form')) {
        $operations['edit_form'] = [
          'title' => $this->t('Edit'),
          'url' => $exportable->entity()->toUrl('edit-form'),
        ];
      }
      if ($entity_type->hasLinkTemplate('lark-load')) {
        $operations['lark'] = [
          'title' => $this->t('Export'),
          'url' => $exportable->entity()->toUrl('lark-load'),
        ];
      }
      if ($entity_type->hasLinkTemplate('lark-import')) {
        $operations['lark_import'] = [
          'title' => $this->t('Import'),
          'url' => $exportable->entity()->toUrl('lark-import'),
        ];
      }
      if ($entity_type->hasLinkTemplate('lark-download')) {
        $operations['lark_download'] = [
          'title' => $this->t('Download'),
          'url' => $exportable->entity()->toUrl('lark-download'),
        ];
      }
      if ($entity_type->hasLinkTemplate('lark-diff')) {
        $operations['lark_diff'] = [
          'title' => $this->t('Diff'),
          'url' => $exportable->entity()->toUrl('lark-diff'),
        ];
      }
    }

    return $operations;
  }

}
