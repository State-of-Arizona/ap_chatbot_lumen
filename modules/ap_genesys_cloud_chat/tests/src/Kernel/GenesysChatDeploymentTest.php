<?php

namespace Drupal\Tests\ap_genesys_cloud_chat\Kernel;

use Drupal\block\Entity\Block;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\ap_genesys_cloud_chat\Entity\GenesysChatDeployment;

/**
 * Tests the chat deployment profile config entity and its delete guard.
 *
 * @group ap_genesys_cloud
 */
#[RunTestsInSeparateProcesses]
class GenesysChatDeploymentTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'file',
    'block',
    'ap_genesys_cloud',
    'ap_genesys_cloud_chat',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['ap_genesys_cloud', 'system']);
    $this->installEntitySchema('file');
    $this->installEntitySchema('user');
    $this->container->get('theme_installer')->install(['stark']);
  }

  /**
   * Creates and saves a deployment profile.
   *
   * @param string $id
   *   The machine name.
   *
   * @return \Drupal\ap_genesys_cloud_chat\GenesysChatDeploymentInterface
   *   The saved profile.
   */
  protected function createDeployment($id) {
    $deployment = GenesysChatDeployment::create([
      'id' => $id,
      'label' => 'Area ' . $id,
      'environment_name' => 'fedramp-use2',
      'deployment_id' => $id . '-0000-0000-0000-000000000000',
      'bootstrap_url' => 'https://apps.use2.us-gov-pure.cloud/genesys-bootstrap/genesys.min.js',
      'custom_fields' => [
        [
          'type' => 'text',
          'label' => 'Full Name',
          'required' => TRUE,
          'id' => 'fullName',
          'mapping' => 'customerFullName',
          'options' => '',
          'weight' => 0,
        ],
      ],
    ]);
    $deployment->save();
    return $deployment;
  }

  /**
   * Places a Chat Icon Block that uses the given deployment.
   *
   * @param string $block_id
   *   The block placement ID.
   * @param string $deployment
   *   The deployment profile machine name.
   *
   * @return \Drupal\block\BlockInterface
   *   The saved block.
   */
  protected function placeBlock($block_id, $deployment) {
    $block = Block::create([
      'id' => $block_id,
      'theme' => 'stark',
      'region' => 'content',
      'plugin' => 'ap_genesys_cloud_block',
      'settings' => ['deployment' => $deployment],
    ]);
    $block->save();
    return $block;
  }

  /**
   * A profile saves and loads with its values intact.
   *
   * KernelTestBase checks every saved config object against its schema,
   * so this also covers ap_genesys_cloud_chat.deployment.*.
   */
  public function testDeploymentRoundTrips() {
    $this->createDeployment('area_a');

    $loaded = GenesysChatDeployment::load('area_a');
    $this->assertSame('fedramp-use2', $loaded->getEnvironmentName());
    $this->assertSame('area_a-0000-0000-0000-000000000000', $loaded->getDeploymentId());
    $this->assertSame('https://apps.use2.us-gov-pure.cloud/genesys-bootstrap/genesys.min.js', $loaded->getBootstrapUrl());
    $this->assertCount(1, $loaded->getCustomFields());
    $this->assertSame('fullName', $loaded->getCustomFields()[0]['id']);
  }

  /**
   * A block placement declares a config dependency on its profile.
   */
  public function testBlockDependsOnDeployment() {
    $this->createDeployment('area_a');
    $block = $this->placeBlock('chat_a', 'area_a');

    $this->assertContains('ap_genesys_cloud_chat.deployment.area_a', $block->getDependencies()['config'] ?? []);

    $default_block = $this->placeBlock('chat_default', '');
    $this->assertNotContains('ap_genesys_cloud_chat.deployment.area_a', $default_block->getDependencies()['config'] ?? []);
  }

  /**
   * The usage finder returns only the blocks using a given profile.
   */
  public function testDeploymentUsage() {
    $this->createDeployment('area_a');
    $this->createDeployment('area_b');
    $this->placeBlock('chat_a', 'area_a');
    $this->placeBlock('chat_b', 'area_b');
    $this->placeBlock('chat_default', '');

    $usage = $this->container->get('ap_genesys_cloud_chat.deployment_usage');
    $this->assertSame(['chat_a'], array_keys($usage->getBlocks('area_a')));
    $this->assertSame([], $usage->getBlocks('unused'));
  }

  /**
   * A profile in use by a block can't be deleted, so the block survives.
   *
   * Core's Block entity doesn't implement onDependencyRemoval(), so
   * deleting a profile a block depends on would otherwise silently delete
   * that block placement too.
   */
  public function testDeleteRefusedWhileInUse() {
    $deployment = $this->createDeployment('area_a');
    $this->placeBlock('chat_a', 'area_a');

    $form_object = $this->container->get('entity_type.manager')->getFormObject('ap_genesys_deployment', 'delete');
    $form_object->setEntity($deployment);

    $build_state = new FormState();
    $form = $this->container->get('form_builder')->buildForm($form_object, $build_state);
    $this->assertArrayHasKey('in_use', $form);
    $this->assertFalse($form['actions']['submit']['#access']);

    // Even a forced submission is refused.
    $form_state = new FormState();
    $this->container->get('form_builder')->submitForm($form_object, $form_state);
    $this->assertNotEmpty($form_state->getErrors());
    $this->assertNotNull(GenesysChatDeployment::load('area_a'));
    $this->assertNotNull(Block::load('chat_a'));
  }

  /**
   * A profile no block uses can be deleted normally.
   */
  public function testDeleteAllowedWhenUnused() {
    $deployment = $this->createDeployment('area_a');

    $form_object = $this->container->get('entity_type.manager')->getFormObject('ap_genesys_deployment', 'delete');
    $form_object->setEntity($deployment);

    $build_state = new FormState();
    $form = $this->container->get('form_builder')->buildForm($form_object, $build_state);
    $this->assertArrayNotHasKey('in_use', $form);

    $form_state = new FormState();
    $this->container->get('form_builder')->submitForm($form_object, $form_state);
    $this->assertEmpty($form_state->getErrors());
    $this->assertNull(GenesysChatDeployment::load('area_a'));
  }

}
