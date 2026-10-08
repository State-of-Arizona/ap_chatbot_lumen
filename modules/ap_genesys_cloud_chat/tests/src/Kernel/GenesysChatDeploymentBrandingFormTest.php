<?php

namespace Drupal\Tests\ap_genesys_cloud_chat\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\ap_genesys_cloud_chat\Entity\GenesysChatDeployment;
use Drupal\ap_genesys_cloud_chat\Form\GenesysChatDeploymentBrandingForm;
use Drupal\user\Entity\User;

/**
 * Tests GenesysChatDeploymentBrandingForm, a chat deployment's Branding tab.
 *
 * @group ap_genesys_cloud
 */
#[RunTestsInSeparateProcesses]
class GenesysChatDeploymentBrandingFormTest extends KernelTestBase {

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
   * A minimal set of values every submission needs.
   *
   * @var array
   */
  protected $baseValues = [
    'brand_color' => '#4A5568',
    'text_color' => '#ffffff',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['ap_genesys_cloud']);
    $this->installEntitySchema('file');
    $this->installEntitySchema('user');
    $this->installSchema('file', ['file_usage']);

    $user = User::create(['uid' => 1, 'name' => $this->randomMachineName()]);
    $user->enforceIsNew();
    $user->save();
    \Drupal::currentUser()->setAccount($user);

      GenesysChatDeployment::create([
      'id' => 'area_a',
      'label' => 'Area A',
      'environment_name' => 'fedramp-use2',
      'deployment_id' => '00000000-0000-0000-0000-000000000000',
      'bootstrap_url' => 'https://apps.use2.us-gov-pure.cloud/genesys-bootstrap/genesys.min.js',
    ])->save();
  }

  /**
   * Gets the Branding form object for a saved profile, freshly loaded.
   *
   * @param string $id
   *   The profile's machine name.
   *
   * @return \Drupal\Core\Entity\EntityFormInterface
   *   The form object.
   */
  protected function getFormObject($id = 'area_a') {
    $form_object = \Drupal::entityTypeManager()->getFormObject('ap_genesys_deployment', 'branding');
    $form_object->setEntity($this->loadDeployment($id));
    return $form_object;
  }

  /**
   * Loads a profile fresh from storage.
   *
   * @param string $id
   *   The profile's machine name.
   *
   * @return \Drupal\ap_genesys_cloud_chat\GenesysChatDeploymentInterface
   *   The profile.
   */
  protected function loadDeployment($id = 'area_a') {
    return \Drupal::entityTypeManager()->getStorage('ap_genesys_deployment')->loadUnchanged($id);
  }

  /**
   * Submits the branding form programmatically with the given raw values.
   *
   * @param array $values
   *   Values to merge on top of $this->baseValues, simulating raw
   *   submitted input.
   *
   * @return \Drupal\Core\Form\FormState
   *   The form state after submission, for assertions.
   */
  protected function submit(array $values = []) {
    // EntityForm attaches its save handlers to the Save button rather than
    // the form itself, so a programmatic submit has to name that button.
    $form_state = (new FormState())->setValues($values + $this->baseValues + ['op' => 'Save']);
    \Drupal::formBuilder()->submitForm($this->getFormObject(), $form_state);
    return $form_state;
  }

  /**
   * An invalid Button & Header Colour is rejected, not silently accepted.
   */
  public function testInvalidBrandColorRejected() {
    $form_state = $this->submit(['brand_color' => 'not-a-colour']);
    $this->assertNotEmpty($form_state->getErrors());
    $this->assertArrayHasKey('brand_color', $form_state->getErrors());
  }

  /**
   * A malformed Icon & Text Colour is rejected, not silently accepted.
   */
  public function testInvalidTextColorRejected() {
    $form_state = $this->submit(['text_color' => 'not-a-colour']);
    $this->assertNotEmpty($form_state->getErrors());
    $this->assertArrayHasKey('text_color', $form_state->getErrors());
  }

  /**
   * Icon & Text Colour is no longer restricted to white or black.
   *
   * Regression test for opening this field up to any valid hex colour —
   * a well-formed, sufficiently-contrasting non-white/black value must
   * save without error.
   */
  public function testNonWhiteBlackTextColorAccepted() {
    $form_state = $this->submit([
      'brand_color' => '#4A5568',
      // High-contrast against the default brand colour (~8.4:1), so this
      // passes regardless of the accessibility settings' default mode.
      'text_color' => '#FFD700',
    ]);
    $this->assertEmpty($form_state->getErrors());
  }

  /**
   * A low-contrast pairing is rejected when enforcement is set to "block".
   *
   * WCAG 2.2 AA (1.4.3) requires at least 4.5:1 contrast for normal-sized
   * text — light grey against white is nowhere near that, even though
   * both values are individually well-formed hex colours.
   */
  public function testLowContrastColorPairingRejectedInBlockMode() {
    $this->config('ap_genesys_cloud.accessibility')
      ->set('contrast_enforcement', 'block')
      ->save();

    $form_state = $this->submit([
      'brand_color' => '#eeeeee',
      'text_color' => '#ffffff',
    ]);
    $this->assertNotEmpty($form_state->getErrors());
    $this->assertArrayHasKey('brand_color', $form_state->getErrors());
  }

  /**
   * The default enforcement mode ("warning") allows the save to proceed.
   *
   * A low-contrast pairing should still be flagged to the admin, just not
   * block Save the way "block" mode does.
   */
  public function testContrastWarningModeAllowsSaveWithWarning() {
    // No accessibility config seeded — relies on the '?:' fallback default
    // ('warning') in GenesysChatDeploymentBrandingForm::validateForm(),
    // matching what a real site sees before ever visiting the
    // Accessibility tab.
    $form_state = $this->submit([
      'brand_color' => '#eeeeee',
      'text_color' => '#ffffff',
    ]);
    $this->assertEmpty($form_state->getErrors());

    $warnings = \Drupal::messenger()->messagesByType(MessengerInterface::TYPE_WARNING);
    $this->assertNotEmpty($warnings);
    $this->assertStringContainsString('contrast', (string) reset($warnings));
  }

  /**
   * Enforcement mode "none" skips the contrast check entirely.
   */
  public function testContrastNoneModeSkipsCheckEntirely() {
    $this->config('ap_genesys_cloud.accessibility')
      ->set('contrast_enforcement', 'none')
      ->save();

    $form_state = $this->submit([
      'brand_color' => '#eeeeee',
      'text_color' => '#ffffff',
    ]);
    $this->assertEmpty($form_state->getErrors());
    $this->assertEmpty(\Drupal::messenger()->messagesByType(MessengerInterface::TYPE_WARNING));
  }

  /**
   * The "use theme default colours" toggle skips the contrast check.
   *
   * Even in "block" mode, and even with a low-contrast pairing, the
   * colour fields aren't used for anything once the theme is providing
   * its own colours — checking their contrast against each other would
   * only produce a confusing warning/error about values that never
   * actually render. (Colours here are still well-formed hex — a real
   * browser's native colour picker can never submit anything else; this
   * test isn't about the swatch's own built-in format validation, which
   * Drupal core's #type 'color' element handles independently of this
   * form's own logic.)
   */
  public function testUseThemeColorsSkipsColorValidation() {
    $this->config('ap_genesys_cloud.accessibility')
      ->set('contrast_enforcement', 'block')
      ->save();

    $form_state = $this->submit([
      'use_theme_colors' => 1,
      'brand_color' => '#eeeeee',
      'text_color' => '#ffffff',
    ]);
    $this->assertEmpty($form_state->getErrors());
    $this->assertEmpty(\Drupal::messenger()->messagesByType(MessengerInterface::TYPE_WARNING));
  }

  /**
   * The "manage the chat launcher" toggle skips all other validation.
   *
   * Even in "block" mode, with a low-contrast colour pairing and a
   * malformed custom CSS class, nothing else on this form matters once
   * Genesys Cloud's own launcher is standing in for this module's own
   * icon/popup entirely.
   */
  public function testGenesysManagesLauncherSkipsAllValidation() {
    $this->config('ap_genesys_cloud.accessibility')
      ->set('contrast_enforcement', 'block')
      ->save();

    $form_state = $this->submit([
      'genesys_manages_launcher' => 1,
      'brand_color' => '#eeeeee',
      'text_color' => '#ffffff',
      'custom_css_class' => 'not a class',
    ]);
    $this->assertEmpty($form_state->getErrors());
    $this->assertEmpty(\Drupal::messenger()->messagesByType(MessengerInterface::TYPE_WARNING));

    $this->assertTrue($this->loadDeployment()->genesysManagesLauncher());
  }

  /**
   * A malformed Additional CSS Class is rejected, not silently accepted.
   */
  public function testInvalidCustomCssClassRejected() {
    $form_state = $this->submit(['custom_css_class' => 'not a class']);
    $this->assertNotEmpty($form_state->getErrors());
    $this->assertArrayHasKey('custom_css_class', $form_state->getErrors());
  }

  /**
   * A well-formed Additional CSS Class saves without error.
   */
  public function testValidCustomCssClassAccepted() {
    $form_state = $this->submit(['custom_css_class' => 'my-theme-chat']);
    $this->assertEmpty($form_state->getErrors());

    $this->assertSame('my-theme-chat', $this->loadDeployment()->getCustomCssClass());
  }

  /**
   * WCAG Level A has no numeric contrast requirement, even in "block" mode.
   */
  public function testNoContrastRequirementForWcagLevelA() {
    $this->config('ap_genesys_cloud.accessibility')
      ->set('contrast_enforcement', 'block')
      ->set('wcag_level', 'A')
      ->save();

    $form_state = $this->submit([
      'brand_color' => '#eeeeee',
      'text_color' => '#ffffff',
    ]);
    $this->assertEmpty($form_state->getErrors());
  }

  /**
   * Uploading a chat icon must mark it permanent and record its usage.
   *
   * Regression test: without this, an uploaded icon was left in Drupal's
   * default "temporary" file status with no file_usage record, so
   * file_cron() would silently delete it a few hours later even though
   * the profile still referenced it. Usage is recorded under the
   * profile's own config name, so each profile owns its icon.
   */
  public function testChatIconUploadMarksFilePermanentAndTracksUsage() {
    $file = $this->createTestFile();
    $this->assertFalse($file->isPermanent());

    // #type 'managed_file' expects raw input shaped like the hidden field
    // its widget actually renders: a space-separated string of fids under
    // a 'fids' key, not a plain array — see ManagedFile::valueCallback().
    $this->submit(['chat_icon' => ['fids' => (string) $file->id()]]);

    $file = File::load($file->id());
    $this->assertTrue($file->isPermanent());

    $usage = \Drupal::service('file.usage')->listUsage($file);
    $this->assertArrayHasKey('ap_genesys_cloud_chat.deployment.area_a', $usage['ap_genesys_cloud_chat'] ?? []);
    $this->assertSame([(int) $file->id()], $this->loadDeployment()->getChatIcon());
  }

  /**
   * Deleting a profile releases its icon's usage and deletes the file.
   *
   * Permanent files are never garbage-collected by file_cron(), so the
   * profile has to clean up after itself (GenesysChatDeployment::
   * postDelete()). This also covers module uninstall, which deletes the
   * module's config entities.
   */
  public function testDeletingProfileDeletesItsIcon() {
    $file = $this->createTestFile();
    $this->submit(['chat_icon' => ['fids' => (string) $file->id()]]);
    $this->assertNotNull(File::load($file->id()));

    $this->loadDeployment()->delete();

    $this->assertNull(File::load($file->id()));
  }

  /**
   * Deleting one profile leaves another profile's use of the same file.
   *
   * Two profiles can point at one file (e.g. one copied from the other via
   * config). Regression test: usage recorded with an id of 0 can't be
   * narrowed to one profile by core's file usage delete(), so deleting
   * either profile released both and deleted the file out from under the
   * other.
   */
  public function testSharedIconSurvivesDeletingOneProfile() {
    $file = $this->createTestFile();
    $this->submit(['chat_icon' => ['fids' => (string) $file->id()]]);

      GenesysChatDeployment::create([
      'id' => 'area_b',
      'label' => 'Area B',
      'environment_name' => 'fedramp-use2',
      'deployment_id' => '11111111-0000-0000-0000-000000000000',
      'bootstrap_url' => 'https://apps.use2.us-gov-pure.cloud/genesys-bootstrap/genesys.min.js',
    ])->save();
    $form_state = (new FormState())->setValues(['chat_icon' => ['fids' => (string) $file->id()], 'op' => 'Save'] + $this->baseValues);
    $form_object = \Drupal::entityTypeManager()->getFormObject('ap_genesys_deployment', 'branding');
    $form_object->setEntity($this->loadDeployment('area_b'));
    \Drupal::formBuilder()->submitForm($form_object, $form_state);

    $this->loadDeployment('area_a')->delete();

    $file = File::load($file->id());
    $this->assertNotNull($file);
    $usage = \Drupal::service('file.usage')->listUsage($file);
    $this->assertSame(['ap_genesys_cloud_chat.deployment.area_b'], array_keys($usage['ap_genesys_cloud_chat']));
  }

  /**
   * Saving the Branding tab changes only branding, never deployment values.
   */
  public function testBrandingSaveLeavesDeploymentValuesAlone() {
    $form_state = $this->submit([
      'brand_color' => '#123456',
      'text_color' => '#ffffff',
      'environment_name' => 'should-not-be-copied',
      'brand_color_hex' => '#123456',
    ]);
    $this->assertEmpty($form_state->getErrors());

    $deployment = $this->loadDeployment();
    $this->assertSame('#123456', $deployment->getBrandColor());
    $this->assertSame('fedramp-use2', $deployment->getEnvironmentName());
    $raw = $this->config('ap_genesys_cloud_chat.deployment.area_a')->getRawData();
    $this->assertArrayNotHasKey('brand_color_hex', $raw);
  }

  /**
   * Removing a previously-uploaded icon releases usage and deletes it.
   *
   * Permanent files are never touched by file_cron()'s garbage
   * collection, so this module has to clean up after itself when an icon
   * is replaced or removed — otherwise it's orphaned forever.
   */
  public function testRemovingChatIconDeletesFileAndUsage() {
    $file = $this->createTestFile();
    $this->submit(['chat_icon' => ['fids' => (string) $file->id()]]);
    $this->assertNotNull(File::load($file->id()));

    $this->submit(['chat_icon' => ['fids' => '']]);

    $this->assertNull(File::load($file->id()));
  }

  /**
   * An unparseable/DOCTYPE-entity SVG upload is rejected outright.
   *
   * Regression test for A11Y-SECURITY.md S2: the raw uploaded file is
   * reachable at its own public URL (not just rendered via <img>). The
   * sanitizer strips most dangerous content in place (see
   * testSvgIconWithScriptIsSanitizedOnSave() below), but a custom
   * DOCTYPE/DTD entity (classic XXE shape) is the one case it refuses to
   * merely clean — that file must never reach permanent status at all.
   */
  public function testUnparseableSvgIconRejected() {
    $svg = '<?xml version="1.0" standalone="yes"?>'
      . '<!DOCTYPE svg [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>'
      . '<svg xmlns="http://www.w3.org/2000/svg"><text>&xxe;</text></svg>';
    $file = $this->createTestFile($svg);

    $form_state = $this->submit(['chat_icon' => ['fids' => (string) $file->id()]]);

    $this->assertNotEmpty($form_state->getErrors());
    $file = File::load($file->id());
    $this->assertFalse($file->isPermanent());
    $usage = \Drupal::service('file.usage')->listUsage($file);
    $this->assertArrayNotHasKey('ap_genesys_cloud_chat', $usage);
    $this->assertSame([], $this->loadDeployment()->getChatIcon());
  }

  /**
   * An SVG containing a <script> is sanitized in place, then saved.
   *
   * Unlike the unparseable case above, the sanitizer can clean this one
   * (strips the <script> element and saves the rest) — proves the
   * sanitizer actually ran and the *stored* file no longer contains it,
   * not just that rendering happens to go through <img>.
   */
  public function testSvgIconWithScriptIsSanitizedOnSave() {
    $file = $this->createTestFile('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><circle cx="5" cy="5" r="4"/></svg>');

    $form_state = $this->submit(['chat_icon' => ['fids' => (string) $file->id()]]);

    $this->assertEmpty($form_state->getErrors());
    $file = File::load($file->id());
    $this->assertTrue($file->isPermanent());
    $contents = file_get_contents($file->getFileUri());
    $this->assertStringNotContainsString('<script', $contents);
    $this->assertStringContainsString('<circle', $contents);
  }

  /**
   * Only the extension allowlist runs at upload time, in core's own format.
   *
   * Drupal 10.2+ (file.validator service) takes the FileExtension
   * constraint; older cores take file_validate_extensions. Drupal 11
   * removed callable validators such as the old size callback entirely,
   * which is why the type and size checks now run in validateForm().
   */
  public function testChatIconUploadValidatorsOnlyRestrictExtensions() {
    $form_state = new FormState();
    $form = \Drupal::formBuilder()->buildForm($this->getFormObject(), $form_state);
    $validators = $form['customization']['chat_icon']['#upload_validators'];

    if (interface_exists('Drupal\file\Validation\FileValidatorInterface')) {
      $this->assertSame(['FileExtension' => ['extensions' => 'png jpg svg']], $validators);
    }
    else {
      $this->assertSame(['file_validate_extensions' => ['png jpg svg']], $validators);
    }
  }

  /**
   * Saving with an icon over its format's size limit is rejected.
   */
  public function testOversizedIconsRejectedOnSave() {
    $oversized_svg = $this->createTestFile(str_repeat('<!-- padding --> ', 5000), 'image/svg+xml', 'oversized.svg');
    $form_state = $this->submit(['chat_icon' => ['fids' => (string) $oversized_svg->id()]]);
    $this->assertArrayHasKey('chat_icon', $form_state->getErrors());
    $this->assertStringContainsString('exceeds', (string) $form_state->getErrors()['chat_icon']);
    $this->assertFalse(File::load($oversized_svg->id())->isPermanent());
    $this->assertSame([], $this->loadDeployment()->getChatIcon());

    $oversized_png = $this->createTestFile(str_repeat('x', GenesysChatDeploymentBrandingForm::MAX_RASTER_ICON_SIZE + 1), 'image/png', 'oversized.png');
    $form_state = $this->submit(['chat_icon' => ['fids' => (string) $oversized_png->id()]]);
    $this->assertArrayHasKey('chat_icon', $form_state->getErrors());
    $this->assertFalse(File::load($oversized_png->id())->isPermanent());
  }

  /**
   * Saving with a file that isn't a PNG, JPG, or SVG is rejected.
   */
  public function testWrongIconTypeRejectedOnSave() {
    $file = $this->createTestFile('plain text', 'text/plain', 'notes.txt');
    $form_state = $this->submit(['chat_icon' => ['fids' => (string) $file->id()]]);
    $this->assertArrayHasKey('chat_icon', $form_state->getErrors());
    $this->assertStringContainsString('PNG, JPG, or SVG', (string) $form_state->getErrors()['chat_icon']);
    $this->assertFalse(File::load($file->id())->isPermanent());
  }

  /**
   * Each format is checked against its own limit.
   *
   * An SVG well under the (larger) raster limit but over the (smaller)
   * SVG limit would wrongly pass if the two were conflated.
   */
  public function testIconSizeLimitsArePerFormat() {
    $form_object = $this->getFormObject();

    $small_svg = $this->createTestFile('<svg></svg>', 'image/svg+xml', 'small.svg');
    $this->assertEmpty($form_object->getIconFileErrors($small_svg));

    $small_png = $this->createTestFile('not really a png, just small', 'image/png', 'small.png');
    $this->assertEmpty($form_object->getIconFileErrors($small_png));

    $svg_between_limits = $this->createTestFile(str_repeat('a', GenesysChatDeploymentBrandingForm::MAX_SVG_ICON_SIZE + 1), 'image/svg+xml', 'between.svg');
    $this->assertNotEmpty($form_object->getIconFileErrors($svg_between_limits));
  }

  /**
   * Creates an unsaved-to-permanent test file, as managed_file would.
   *
   * @param string $contents
   *   The file's contents. Defaults to a minimal valid SVG.
   * @param string $filemime
   *   The file's MIME type. Defaults to 'image/svg+xml'.
   * @param string $filename
   *   The file's name/URI basename. Defaults to 'test-icon.svg'.
   *
   * @return \Drupal\file\FileInterface
   *   A saved, temporary file entity.
   */
  protected function createTestFile($contents = '<svg></svg>', $filemime = 'image/svg+xml', $filename = 'test-icon.svg') {
    file_put_contents('public://' . $filename, $contents);
    $file = File::create([
      'uid' => 1,
      'filename' => $filename,
      'uri' => 'public://' . $filename,
      'filemime' => $filemime,
      'status' => 0,
    ]);
    $file->save();
    return $file;
  }

}
