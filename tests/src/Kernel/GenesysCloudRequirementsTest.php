<?php

namespace Drupal\Tests\ap_genesys_cloud\Kernel;

use Drupal\Core\Extension\InstallRequirementsInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ap_genesys_cloud\Install\Requirements\GenesysCloudRequirements;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the enshrined/svg-sanitize requirement checks.
 *
 * The library is installed in the test environment, so these cover the
 * "present" path on every core. The "missing" path can't be forced without
 * removing the library; it was checked by hand (see SETUP-LOG.md).
 *
 * @group ap_genesys_cloud
 */
#[RunTestsInSeparateProcesses]
class GenesysCloudRequirementsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'ap_genesys_cloud'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    require_once $this->root . '/core/includes/install.inc';
    $this->container->get('module_handler')->loadInclude('ap_genesys_cloud', 'install');
  }

  /**
   * Gets the expected "OK" severity for the running core.
   */
  protected function okSeverity() {
    return class_exists(RequirementSeverity::class) ? RequirementSeverity::OK : REQUIREMENT_OK;
  }

  /**
   * The legacy hook doesn't block install, and reports OK at runtime.
   */
  public function testHookRequirementsWithLibraryPresent() {
    $this->assertSame([], ap_genesys_cloud_requirements('install'));

    $runtime = ap_genesys_cloud_requirements('runtime');
    $this->assertArrayHasKey('ap_genesys_cloud_svg_sanitize', $runtime);
    $this->assertSame($this->okSeverity(), $runtime['ap_genesys_cloud_svg_sanitize']['severity']);

    $this->assertSame([], ap_genesys_cloud_requirements('update'));
  }

  /**
   * The Drupal 11.2+ install class doesn't block install either.
   */
  public function testInstallRequirementsClassWithLibraryPresent() {
    if (!interface_exists(InstallRequirementsInterface::class)) {
      $this->markTestSkipped('InstallRequirementsInterface needs Drupal 11.2 or later.');
    }
    $this->assertSame([], GenesysCloudRequirements::getRequirements());
  }

  /**
   * On Drupal 11.2+, the Status report row comes from the runtime hook.
   */
  public function testRuntimeRequirementsHook() {
    if (!class_exists(RequirementSeverity::class)) {
      $this->markTestSkipped('hook_runtime_requirements() needs Drupal 11.2 or later.');
    }
    // Only this module's implementation: other modules' runtime checks
    // query tables this test doesn't install.
    $requirements = $this->container->get('module_handler')->invoke('ap_genesys_cloud', 'runtime_requirements');
    $this->assertArrayHasKey('ap_genesys_cloud_svg_sanitize', $requirements);
    $this->assertSame(RequirementSeverity::OK, $requirements['ap_genesys_cloud_svg_sanitize']['severity']);
  }

}
