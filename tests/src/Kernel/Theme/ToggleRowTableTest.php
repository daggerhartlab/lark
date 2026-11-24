<?php

namespace Drupal\Tests\lark\Kernel\Theme;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the toggle_row_table theme hook.
 */
#[Group('lark')]
#[RunTestsInSeparateProcesses]
class ToggleRowTableTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['lark', 'system', 'user'];

  /**
   * The hook's preprocess is registered without a template_preprocess_HOOK().
   */
  public function testPreprocessIsRegisteredAsInitialPreprocess(): void {
    $info = $this->container->get('theme.registry')->get()['toggle_row_table'];

    $this->assertNotEmpty($info['initial preprocess'] ?? NULL);
    $this->assertNotContains('template_preprocess_toggle_row_table', $info['preprocess functions'] ?? []);
  }

  /**
   * Each row renders as a toggle row followed by its details row.
   */
  public function testRowsRenderAsTogglePairs(): void {
    $build = [
      '#theme' => 'toggle_row_table',
      '#header' => [
        'name' => 'Name',
        'details' => 'Details',
      ],
      '#rows' => [
        ['name' => 'First row', 'details' => 'First details'],
        ['name' => 'Second row', 'details' => 'Second details'],
      ],
      '#toggle_handle_header' => 'Toggle',
    ];
    $this->render($build);

    $this->assertSame('Toggle', trim((string) $this->cssSelect('thead th')[2]));

    $rows = $this->cssSelect('tbody tr');
    $this->assertCount(4, $rows);

    foreach ([0 => 'First', 2 => 'Second'] as $i => $label) {
      $toggle_row = $rows[$i];
      $details_row = $rows[$i + 1];

      $this->assertStringContainsString('toggle-row-table--toggle-row', (string) $toggle_row['class']);
      $uuid = (string) $toggle_row['data-uuid'];
      $this->assertNotEmpty($uuid);
      $this->assertStringContainsString("$label row", (string) $toggle_row->td[0]);
      $this->assertNotEmpty($toggle_row->xpath('.//div[contains(@class, "toggle-row-table--toggle-handle--handle-open")]'));
      $this->assertNotEmpty($toggle_row->xpath('.//div[contains(@class, "toggle-row-table--toggle-handle--handle-close")]'));

      $this->assertStringContainsString('toggle-row-table--details-row', (string) $details_row['class']);
      $details = $details_row->td[0];
      $this->assertStringContainsString("toggle-row-table--details--$uuid", (string) $details['class']);
      $this->assertStringContainsString("$label details", (string) $details);
    }
  }

}
