<?php

namespace Drupal\lark\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ExtensionPathResolver;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LarkOverview extends ControllerBase {

  public function __construct(
    protected ExtensionPathResolver $extensionPathResolver,
  ) {}

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get(ExtensionPathResolver::class)
    );
  }

  public function build(): array {

    $readme_path = $this->extensionPathResolver->getPath('module', 'lark') . '/README.md';
    if (\file_exists($readme_path)) {

    }

    return [
      'hi' => [
        '#markup' => 'J!',
      ],
    ];
  }

}
