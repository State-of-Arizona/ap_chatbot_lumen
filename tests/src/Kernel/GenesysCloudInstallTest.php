<?php

namespace Drupal\Tests\ap_genesys_cloud\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests how the parent module and its hidden feature submodules relate.
 *
 * @group ap_genesys_cloud
 */
#[RunTestsInSeparateProcesses]
class GenesysCloudInstallTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
  }

  /**
   * Installing the parent installs the hidden chat submodule too.
   */
  public function testInstallingParentInstallsChat() {
    $this->container->get('module_installer')->install(['ap_genesys_cloud']);

    $module_handler = $this->container->get('module_handler');
    $this->assertTrue($module_handler->moduleExists('ap_genesys_cloud'));
    $this->assertTrue($module_handler->moduleExists('ap_genesys_cloud_chat'));

    $info = $this->container->get('extension.list.module')->getExtensionInfo('ap_genesys_cloud_chat');
    $this->assertTrue($info['hidden']);
    $this->assertContains('ap_genesys_cloud:ap_genesys_cloud', $info['dependencies']);
  }

  /**
   * The parent never ends up uninstalled while chat stays installed.
   *
   * The chat submodule uses shared code from the parent (ColorContrast, the
   * Accessibility route), so it must never be left installed without it.
   * The Uninstall page disables the parent's checkbox while chat is
   * installed, and the module installer's default (also what drush uses)
   * uninstalls chat along with it.
   */
  public function testParentNeverUninstalledWithoutChat() {
    $installer = $this->container->get('module_installer');
    $installer->install(['ap_genesys_cloud']);

    $form = \Drupal::formBuilder()->getForm('Drupal\system\Form\ModulesUninstallForm');
    $this->assertTrue($form['uninstall']['ap_genesys_cloud']['#disabled']);
    // Drupal 11 lists dependents as "Module name (machine_name)", earlier
    // cores as the bare machine name.
    $required_by = implode(' ', $form['modules']['ap_genesys_cloud']['#required_by']);
    $this->assertStringContainsString('ap_genesys_cloud_chat', $required_by);
    $this->assertEmpty($form['uninstall']['ap_genesys_cloud_chat']['#disabled'] ?? FALSE);

    $installer->uninstall(['ap_genesys_cloud']);
    $module_handler = $this->container->get('module_handler');
    $this->assertFalse($module_handler->moduleExists('ap_genesys_cloud'));
    $this->assertFalse($module_handler->moduleExists('ap_genesys_cloud_chat'));
  }

  /**
   * The landing page lists the chat and Accessibility sections.
   */
  public function testLandingPageMenuLinks() {
    $this->container->get('module_installer')->install(['ap_genesys_cloud']);

    $children = \Drupal::service('plugin.manager.menu.link')->getChildIds('ap_genesys_cloud.admin');
    $this->assertContains('ap_genesys_cloud_chat.deployments', $children);
    $this->assertContains('ap_genesys_cloud.accessibility', $children);
  }

}
