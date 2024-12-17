<?php

namespace Drupal\lark\Exception;

use Drupal\Core\Entity\EntityConstraintViolationListInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Thrown if an entity being imported has validation errors.
 *
 * @internal
 *   This API is experimental.
 */
final class LarkEntityInvalidException extends \RuntimeException {

  public function __construct(public readonly EntityConstraintViolationListInterface $violations, public readonly string $filePath) {
    $messages = [];

    foreach ($violations as $violation) {
      assert($violation instanceof ConstraintViolationInterface);
      $messages[] = $violation->getPropertyPath() . '=' . $violation->getMessage();
    }
    // Example: "/path/to/file.yml: field_a=Violation 1., field_b=Violation 2.".
    parent::__construct("$filePath: " . implode('||', $messages));
  }

}
