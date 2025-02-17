<?php

declare(strict_types=1);

namespace Drupal\lark;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Render\Markup;
use Drupal\lark\Service\SourceManagerInterface;

/**
 * Provides a listing of sources.
 */
final class LarkSourceListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['label'] = $this->t('Label');
    $header['id'] = $this->t('Machine name');
    $header['description'] = $this->t('Description');
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\lark\Entity\LarkSourceInterface $entity */
    $row['label'] = $entity->toLink($entity->label());
    $row['id'] = $entity->id();
    $row['description'] = Markup::create("<pre style='font-size: small;'>{$entity->directory()}</pre>{$entity->description()}");
    $row['status'] = $entity->status() ? $this->t('Enabled') : $this->t('Disabled');

    return $row + parent::buildRow($entity);
  }

}
