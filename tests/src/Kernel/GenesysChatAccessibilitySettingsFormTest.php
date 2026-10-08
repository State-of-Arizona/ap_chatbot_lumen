<?php

namespace Drupal\Tests\ap_genesys_cloud\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\ap_genesys_cloud_chat\Entity\GenesysChatDeployment;

/**
 * Tests GenesysAccessibilitySettingsForm.
 *
 * @group ap_genesys_cloud
 */
#[RunTestsInSeparateProcesses]
class GenesysChatAccessibilitySettingsFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'ap_genesys_cloud',
    'ap_genesys_cloud_chat',
    'block',
    'file',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['ap_genesys_cloud']);

    foreach (['area_a' => 'Area A', 'area_b' => 'Area B'] as $id => $label) {
      GenesysChatDeployment::create([
        'id' => $id,
        'label' => $label,
        'environment_name' => 'fedramp-use2',
        'deployment_id' => $id,
        'bootstrap_url' => 'https://apps.use2.us-gov-pure.cloud/genesys-bootstrap/genesys.min.js',
      ])->save();
    }
  }

  /**
   * Builds the form and returns its render array, for assertions.
   *
   * @return array
   *   The built form.
   */
  protected function buildForm() {
    $form_state = new FormState();
    return \Drupal::formBuilder()->buildForm('Drupal\ap_genesys_cloud\Form\GenesysAccessibilitySettingsForm', $form_state);
  }

  /**
   * No notice appears by default, when Genesys Cloud isn't managing it.
   */
  public function testNoNoticeByDefault() {
    $form = $this->buildForm();
    $this->assertArrayNotHasKey('genesys_manages_launcher_notice', $form);
  }

  /**
   * The notice lists only the profiles where Genesys manages the launcher.
   *
   * This form doesn't own or save any profile — it only reads them to
   * decide whether to show this notice.
   */
  public function testNoticeListsProfilesWhereGenesysManagesLauncher() {
    $deployment = GenesysChatDeployment::load('area_b');
    $deployment->set('genesys_manages_launcher', TRUE)->save();

    $form = $this->buildForm();
    $this->assertArrayHasKey('genesys_manages_launcher_notice', $form);
    $items = $form['genesys_manages_launcher_notice']['deployments']['#items'];
    $this->assertCount(1, $items);
    $this->assertSame('Area B', (string) $items[0]['#title']);
  }

}
