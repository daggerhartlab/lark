<?php

declare(strict_types=1);

namespace Drupal\Tests\lark\Kernel\FieldTypeHandler;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\lark\Exception\LarkImportException;
use Drupal\lark\Plugin\Lark\FieldTypeHandler\DefaultHandler;
use Drupal\lark\Service\FieldTypeHandlerManagerInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests \Drupal\lark\Plugin\Lark\FieldTypeHandler\DefaultHandler.
 */
#[CoversClass(DefaultHandler::class)]
#[Group('lark')]
#[RunTestsInSeparateProcesses]
class DefaultHandlerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'lark', 'user', 'system', 'field', 'file',
    'paragraphs', 'entity_reference_revisions',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('paragraph');
    ParagraphsType::create(['id' => 'test', 'label' => 'Test'])->save();
  }

  /**
   * Tests that a serialized string property imports as the same data.
   */
  public function testSerializedPropertyFromString(): void {
    $settings = ['plugin' => ['key' => 'value']];
    $values = $this->alterImportValue(serialize($settings));
    $this->assertSame($settings, unserialize($values[0]['value'], ['allowed_classes' => FALSE]));
  }

  /**
   * Tests that a serialized property given as an array is serialized.
   */
  public function testSerializedPropertyFromArray(): void {
    $settings = ['plugin' => ['key' => 'value']];
    $values = $this->alterImportValue($settings);
    $this->assertSame(serialize($settings), $values[0]['value']);
  }

  /**
   * Tests that a serialized FALSE imports as FALSE.
   */
  public function testSerializedPropertyFalse(): void {
    $values = $this->alterImportValue(serialize(FALSE));
    $this->assertSame(serialize(FALSE), $values[0]['value']);
  }

  /**
   * Tests that a string that isn't serialized data is rejected.
   */
  public function testSerializedPropertyInvalidString(): void {
    $this->expectException(LarkImportException::class);
    $this->alterImportValue('not serialized');
  }

  /**
   * Tests that a serialized property must hold only scalars and arrays.
   */
  public function testSerializedPropertyMustBeData(): void {
    $this->expectException(LarkImportException::class);
    $this->alterImportValue(serialize(['plugin' => new \stdClass()]));
  }

  /**
   * Runs a behavior_settings value through the default handler's import.
   *
   * @param mixed $value
   *   The exported value of the behavior_settings "value" property.
   *
   * @return array
   *   The field values prepared for import.
   */
  protected function alterImportValue(mixed $value): array {
    $paragraph = Paragraph::create(['type' => 'test']);
    $field = $paragraph->get('behavior_settings');
    $this->assertInstanceOf(FieldItemListInterface::class, $field);

    /** @var \Drupal\lark\Service\FieldTypeHandlerManagerInterface $manager */
    $manager = $this->container->get(FieldTypeHandlerManagerInterface::class);
    return $manager->getInstances()['default_field_type_handler']
      ->alterImportValue([['value' => $value]], $field);
  }

}
