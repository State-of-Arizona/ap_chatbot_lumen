<?php

namespace Drupal\Tests\ap_genesys_cloud_chat\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\ap_genesys_cloud_chat\Entity\GenesysChatDeployment;
use Drupal\user\Entity\User;

/**
 * Tests GenesysChatDeploymentForm.
 *
 * Covers the profile's Deployment tab: deployment values, lead-capture
 * fields, Import from Script, and saving only the entity's own keys.
 *
 * @group ap_genesys_cloud
 */
#[RunTestsInSeparateProcesses]
class GenesysChatDeploymentFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'file',
    'ap_genesys_cloud',
    'ap_genesys_cloud_chat',
  ];

  /**
   * A minimal set of required-field values every submission needs.
   *
   * @var array
   */
  protected $baseValues = [
    'label' => 'Benefits area',
    'id' => 'benefits',
    'environment_name' => 'fedramp-use2',
    'deployment_id' => '00000000-0000-0000-0000-000000000000',
    'bootstrap_url' => 'https://apps.use2.us-gov-pure.cloud/genesys-bootstrap/genesys.min.js',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['ap_genesys_cloud']);
    $this->installEntitySchema('user');

    $user = User::create(['uid' => 1, 'name' => $this->randomMachineName()]);
    $user->enforceIsNew();
    $user->save();
    \Drupal::currentUser()->setAccount($user);
  }

  /**
   * Gets the add form object for a new (unsaved) profile.
   *
   * @param array $fields
   *   Custom fields to seed the new profile with — the table only renders
   *   as many rows as the entity starts with, which raw input must match.
   *
   * @return \Drupal\Core\Entity\EntityFormInterface
   *   The form object.
   */
  protected function getAddForm(array $fields = []) {
    $form_object = $this->container->get('entity_type.manager')->getFormObject('ap_genesys_deployment', 'add');
    $form_object->setEntity(GenesysChatDeployment::create(['custom_fields' => $fields]));
    return $form_object;
  }

  /**
   * Submits the add form programmatically with the given raw values.
   *
   * @param array $values
   *   Values to merge on top of $this->baseValues.
   * @param array $fields
   *   Custom fields to seed the new profile with.
   *
   * @return \Drupal\Core\Form\FormState
   *   The form state after submission.
   */
  protected function submit(array $values = [], array $fields = []) {
    // EntityForm attaches its save handlers to the Save button rather than
    // the form itself, so a programmatic submit has to name that button.
    $form_state = (new FormState())->setValues($values + $this->baseValues + ['op' => 'Save']);
    $this->container->get('form_builder')->submitForm($this->getAddForm($fields), $form_state);
    return $form_state;
  }

  /**
   * Builds one stored custom field row.
   */
  protected function fieldRow($label, $id, $mapping, $weight) {
    return [
      'type' => 'text',
      'label' => $label,
      'required' => FALSE,
      'id' => $id,
      'mapping' => $mapping,
      'options' => '',
      'weight' => $weight,
    ];
  }

  /**
   * A valid submission saves a profile holding only its own keys.
   */
  public function testSubmitCreatesDeployment() {
    $fields = [
      $this->fieldRow('Full Name', 'fullName', 'customerFullName', 0),
      $this->fieldRow('', '', '', 1),
    ];
    $form_state = $this->submit([
      'script_paste' => 'not saved',
      'custom_fields' => [
        0 => ['required' => 1] + $this->fieldRow('Full Name', 'fullName', 'customerFullName', 0),
        1 => $this->fieldRow('', '', '', 1),
      ],
    ], $fields);
    $this->assertEmpty($form_state->getErrors());

    $deployment = GenesysChatDeployment::load('benefits');
    $this->assertNotNull($deployment);
    $this->assertSame('Benefits area', $deployment->label());
    $this->assertSame('fedramp-use2', $deployment->getEnvironmentName());

    // The blank row is dropped and each row holds only schema keys (no
    // stray 'remove' button value).
    $saved_fields = $deployment->getCustomFields();
    $this->assertCount(1, $saved_fields);
    $this->assertSame('fullName', $saved_fields[0]['id']);
    $this->assertArrayNotHasKey('remove', $saved_fields[0]);

    // Nothing the form uses internally leaks into exported config.
    $raw = $this->config('ap_genesys_cloud_chat.deployment.benefits')->getRawData();
    $this->assertArrayNotHasKey('script_paste', $raw);
  }

  /**
   * A non-HTTPS bootstrap URL is rejected, same as on the Settings tab.
   */
  public function testNonHttpsBootstrapUrlRejected() {
    $form_state = $this->submit([
      'bootstrap_url' => 'http://apps.use2.us-gov-pure.cloud/genesys-bootstrap/genesys.min.js',
    ]);
    $this->assertNotEmpty($form_state->getErrors());
    $this->assertNull(GenesysChatsDeployment::load('benefits'));
  }

  /**
   * Duplicate Field IDs are rejected, same as on the Settings tab.
   */
  public function testDuplicateFieldIdsRejected() {
    $fields = [
      $this->fieldRow('Full Name', 'fullName', 'customerFullName', 0),
      $this->fieldRow('Email', 'fullName', 'customerEmail', 1),
    ];
    $form_state = $this->submit(['custom_fields' => $fields], $fields);
    $this->assertNotEmpty($form_state->getErrors());
    $this->assertNull(GenesysChatDeployment::load('benefits'));
  }

  /**
   * Parse Script overrides stale submitted input on the entity form too.
   *
   * Regression test: Drupal's Form API redisplays whatever was already
   * submitted for a field that existed before, and only falls back to
   * #default_value with no prior input. A real browser always resubmits
   * the current field values alongside the pasted script, so setting
   * #default_value alone was silently a no-op. Rebuilding against a fresh
   * FormState() with no prior input would never catch this.
   */
  public function testParseScriptOverridesStaleSubmittedInput() {
    $form_state = new FormState();
    $form = $this->container->get('form_builder')->buildForm($this->getAddForm(), $form_state);
    $form_object = $form_state->getFormObject();

    $script = <<<'SCRIPT'
<script type="text/javascript" charset="utf-8">
    (function (g, e, n, es, ys) {})(window, 'Genesys', 'https://apps.use2.us-gov-pure.cloud/genesys-bootstrap/genesys.min.js', {
        environment: 'fedramp-use2-core',
        deploymentId: '10e75ed6-9ba1-4fda-fake-id8657b96a92'
    });
</script>
SCRIPT;

    $form_state->setUserInput([
      'label' => 'Benefits area',
      'environment_name' => 'stale-old-value',
      'deployment_id' => 'stale-old-deployment',
      'bootstrap_url' => 'https://apps.mypurecloud.de/genesys-bootstrap/genesys.min.js',
      'script_paste' => $script,
    ]);
    $form_state->setValue('script_paste', $script);

    $form_object->parseScript($form, $form_state);

    $user_input = $form_state->getUserInput();
    $this->assertSame('fedramp-use2-core', $user_input['environment_name']);
    $this->assertSame('10e75ed6-9ba1-4fda-fake-id8657b96a92', $user_input['deployment_id']);
    $this->assertNull(GenesysChatDeployment::load('benefits'));
  }

  /**
   * Builds one raw submitted-input row, matching the table's own fields.
   */
  protected function fieldRowInput($label, $id, $mapping, $weight) {
    return [
      'weight' => $weight,
      'required' => 0,
      'type' => 'text',
      'label' => $label,
      'id' => $id,
      'mapping' => $mapping,
      'options' => '',
    ];
  }

  /**
   * A new profile is sent to its Branding tab next.
   */
  public function testNewDeploymentRedirectsToBranding() {
    $form_state = $this->submit();
    $this->assertEmpty($form_state->getErrors());
    // getRedirect() always returns FALSE for a programmatic submission, so
    // read what save() set directly.
    $redirect = (new \ReflectionProperty($form_state, 'redirect'));
    $redirect->setAccessible(TRUE);
    $this->assertSame('entity.ap_genesys_deployment.branding_form', $redirect->getValue($form_state)->getRouteName());
  }

  /**
   * Regression test: zero custom fields must not crash the form.
   *
   * Submitting with no rows in the "Chat Bot Fields" table once saved
   * `custom_fields` as the string '' instead of an array, which then
   * crashed the form's uasort() on the next load.
   */
  public function testSubmitWithNoCustomFieldsDoesNotCrash() {
    $form_state = $this->submit();
    $this->assertEmpty($form_state->getErrors());

    $deployment = GenesysChatDeployment::load('benefits');
    $this->assertSame([], $this->config('ap_genesys_cloud_chat.deployment.benefits')->get('custom_fields'));

    // The real regression was a crash on the *next* load, so rebuild.
    $form_object = $this->container->get('entity_type.manager')->getFormObject('ap_genesys_deployment', 'edit');
    $form_object->setEntity($deployment);
    $build_state = new FormState();
    $form = $this->container->get('form_builder')->buildForm($form_object, $build_state);
    $this->assertArrayHasKey('custom_fields_wrapper', $form['fields_wrapper']);
  }

  /**
   * Editing a profile keeps its existing custom field.
   */
  public function testEditPreservesExistingCustomField() {
    $deployment = GenesysChatDeployment::create($this->baseValues + [
      'custom_fields' => [$this->fieldRow('Full Name', 'fullName', 'customerFullName', 0)],
    ]);
    $deployment->save();

    $form_object = $this->container->get('entity_type.manager')->getFormObject('ap_genesys_deployment', 'edit');
    $form_object->setEntity($deployment);
    $form_state = (new FormState())->setValues([
      'custom_fields' => [
        0 => ['required' => 1] + $this->fieldRowInput('Full Name', 'fullName', 'customerFullName', 0),
      ],
      'op' => 'Save',
    ] + $this->baseValues);
    $this->container->get('form_builder')->submitForm($form_object, $form_state);
    $this->assertEmpty($form_state->getErrors());

    $saved = GenesysChatDeployment::load('benefits')->getCustomFields();
    $this->assertCount(1, $saved);
    $this->assertSame('customerFullName', $saved[0]['mapping']);
    $this->assertTrue((bool) $saved[0]['required']);
  }

  /**
   * A row with an empty Field ID is rejected, not silently saved.
   *
   * Regression test for A11Y-SECURITY.md A2: a blank id becomes an empty
   * HTML id/name/for on the public popup for every visitor.
   */
  public function testEmptyFieldIdRejected() {
    $fields = [$this->fieldRow('Full Name', '', 'customerFullName', 0)];
    $form_state = $this->submit(['custom_fields' => [$this->fieldRowInput('Full Name', '', 'customerFullName', 0)]], $fields);
    $this->assertNotEmpty($form_state->getErrors());
  }

  /**
   * A Field ID containing a space (or other unsafe character) is rejected.
   *
   * Regression test for A11Y-SECURITY.md A2: this value becomes an HTML
   * id/name/for attribute and a JS object property key for every visitor.
   */
  public function testInvalidFieldIdFormatRejected() {
    $fields = [$this->fieldRow('Full Name', 'full name', 'customerFullName', 0)];
    $form_state = $this->submit(['custom_fields' => [$this->fieldRowInput('Full Name', 'full name', 'customerFullName', 0)]], $fields);
    $this->assertNotEmpty($form_state->getErrors());
  }

  /**
   * The Parse Script button pre-fills form state without saving anything.
   */
  public function testParseScriptPrefillsFormStateWithoutSaving() {
    $form_state = new FormState();
    $form = $this->container->get('form_builder')->buildForm($this->getAddForm(), $form_state);
    $form_object = $form_state->getFormObject();

    $script = <<<'SCRIPT'
<script type="text/javascript" charset="utf-8">
    (function (g, e, n, es, ys) {})(window, 'Genesys', 'https://apps.use2.us-gov-pure.cloud/genesys-bootstrap/genesys.min.js', {
        environment: 'fedramp-use2-core',
        deploymentId: '10e75ed6-9ba1-4fda-fake-id8657b96a92'
    });
</script>
<form id="contactForm">
    <div class="form-group">
        <label for="fullName">Full Name:</label>
        <input type="text" id="fullName" name="fullName" required />
    </div>
</form>
<script>
    Genesys("command", "Database.set", {
        messaging: { customAttributes: { customerFullName: formProps["fullName"] } },
    });
</script>
SCRIPT;

    $form_state->setValue('script_paste', $script);
    $form_object->parseScript($form, $form_state);
    $this->assertTrue($form_state->isRebuilding());

    $parsed = $form_state->get('parsed_values');
    $this->assertSame('fedramp-use2-core', $parsed['environment_name']);
    $this->assertSame('10e75ed6-9ba1-4fda-fake-id8657b96a92', $parsed['deployment_id']);

    $fields = $form_state->get('custom_fields');
    $this->assertCount(1, $fields);
    $this->assertSame('fullName', $fields[0]['id']);
    $this->assertSame('customerFullName', $fields[0]['mapping']);

    // Nothing is saved yet — only form_state.
    $this->assertEmpty(GenesysChatDeployment::loadMultiple());

    // A rebuild shows the parsed values as the visible defaults.
    $form2 = $this->container->get('form_builder')->buildForm($form_object, $form_state);
    $this->assertSame('fedramp-use2-core', $form2['fields_wrapper']['environment_name']['#default_value']);
  }

  /**
   * Autocomplete values are saved normalized; blank stays off.
   */
  public function testAutocompleteSavedNormalized() {
    $fields = [
      $this->fieldRow('Full Name', 'fullName', 'customerFullName', 0),
      $this->fieldRow('Email', 'email', 'customerEmail', 1),
      $this->fieldRow('License', 'license', 'customerLicense', 2),
    ];
    $form_state = $this->submit([
      'custom_fields' => [
      ['autocomplete' => 'name'] + $this->fieldRowInput('Full Name', 'fullName', 'customerFullName', 0),
      ['autocomplete' => ' Work  Email '] + $this->fieldRowInput('Email', 'email', 'customerEmail', 1),
      ['autocomplete' => ''] + $this->fieldRowInput('License', 'license', 'customerLicense', 2),
      ],
    ], $fields);
    $this->assertEmpty($form_state->getErrors());

    $saved = GenesysChatDeployment::load('benefits')->getCustomFields();
    $this->assertSame('name', $saved[0]['autocomplete']);
    $this->assertSame('work email', $saved[1]['autocomplete']);
    $this->assertSame('', $saved[2]['autocomplete']);
  }

  /**
   * A non-standard or disallowed autocomplete value is rejected.
   */
  public function testInvalidAutocompleteRejected() {
    foreach (['phone', 'cc-number'] as $value) {
      $fields = [$this->fieldRow('Phone', 'phoneNumber', 'customerPhone', 0)];
      $form_state = $this->submit([
        'custom_fields' => [
        ['autocomplete' => $value] + $this->fieldRowInput('Phone', 'phoneNumber', 'customerPhone', 0),
        ],
      ], $fields);
      $this->assertArrayHasKey('custom_fields][0][autocomplete', $form_state->getErrors(), $value);
    }
    $this->assertNull(GenesysChatDeployment::load('benefits'));
  }

  /**
   * Autocomplete on a select field is rejected rather than silently ignored.
   */
  public function testAutocompleteOnSelectRejected() {
    $fields = [['type' => 'select', 'options' => 'A, B'] + $this->fieldRow('Topic', 'topic', 'customerTopic', 0)];
    $form_state = $this->submit([
      'custom_fields' => [
      ['type' => 'select', 'options' => 'A, B', 'autocomplete' => 'name'] + $this->fieldRowInput('Topic', 'topic', 'customerTopic', 0),
      ],
    ], $fields);
    $this->assertArrayHasKey('custom_fields][0][autocomplete', $form_state->getErrors());
  }

  /**
   * A bootstrap URL that isn't a Genesys Cloud address is rejected.
   *
   * It becomes a <script src> on every page the block appears on.
   */
  public function testNonGenesysBootstrapUrlRejected() {
    foreach ([
      'https://evil.example/genesys-bootstrap/genesys.min.js',
      'https://apps.mypurecloud.com.evil.example/genesys-bootstrap/genesys.min.js',
      'https://apps.mypurecloud.com@evil.example/genesys-bootstrap/genesys.min.js',
    ] as $url) {
      $form_state = $this->submit(['bootstrap_url' => $url]);
      $this->assertArrayHasKey('bootstrap_url', $form_state->getErrors(), $url);
      $this->assertStringContainsString('Genesys Cloud address', (string) $form_state->getErrors()['bootstrap_url']);
    }
    $this->assertNull(GenesysChatDeployment::load('benefits'));
  }

}
