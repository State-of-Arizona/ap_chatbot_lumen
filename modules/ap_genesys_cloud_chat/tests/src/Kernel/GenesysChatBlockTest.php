<?php

namespace Drupal\Tests\ap_genesys_cloud_chat\Kernel;

use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\ap_genesys_cloud_chat\Entity\GenesysChatDeployment;
use Psr\Log\AbstractLogger;

/**
 * Tests the Chat Icon block plugin.
 *
 * @group ap_genesys_cloud
 */
#[RunTestsInSeparateProcesses]
class GenesysChatBlockTest extends KernelTestBase {

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
    $this->installSchema('file', ['file_usage']);

    GenesysChatDeployment::create([
      'id' => 'area_a',
      'label' => 'Area A',
      'environment_name' => 'area-a-env',
      'deployment_id' => 'area-a-id',
      'bootstrap_url' => 'https://apps.usw2.pure.cloud/genesys-bootstrap/genesys.min.js',
    ])->save();
  }

  /**
   * Builds a fresh Chat Icon block plugin instance.
   *
   * @param array $configuration
   *   Block plugin configuration. Defaults to using the 'area_a' profile
   *   created in setUp().
   *
   * @return \Drupal\Core\Block\BlockPluginInterface
   *   The block plugin.
   */
  protected function createBlock(array $configuration = ['deployment' => 'area_a']) {
    return \Drupal::service('plugin.manager.block')->createInstance('ap_genesys_cloud_block', $configuration);
  }

  /**
   * Sets values on the 'area_a' profile, bypassing its forms.
   *
   * Like a config import or drush would, which is what the render-time
   * validation tests below rely on.
   *
   * @param array $values
   *   Property values, keyed by property name.
   */
  protected function updateDeployment(array $values) {
    $deployment = GenesysChatDeployment::load('area_a');
    foreach ($values as $key => $value) {
      $deployment->set($key, $value);
    }
    $deployment->save();
  }

  /**
   * Creates a saved file entity, as getIcon() would load via chat_icon.
   *
   * @param string $filemime
   *   The file's MIME type.
   * @param string $filename
   *   The file's name/URI basename.
   *
   * @return \Drupal\file\FileInterface
   *   The saved file entity.
   */
  protected function createTestIconFile($filemime, $filename) {
    file_put_contents('public://' . $filename, 'test icon contents');
    $file = File::create([
      'uid' => 1,
      'filename' => $filename,
      'uri' => 'public://' . $filename,
      'filemime' => $filemime,
      'status' => 1,
    ]);
    $file->save();
    return $file;
  }

  /**
   * With no custom icon uploaded, the bundled default SVG is inlined.
   */
  public function testDefaultIconIsInlinedSvg() {
    $build = $this->createBlock()->build();

    $this->assertNotEmpty($build['ap_genesys_cloud_block']['#icon_svg']);
    $this->assertNull($build['ap_genesys_cloud_block']['#icon_url']);
    $this->assertStringContainsString('apgc-chatbubble default', (string) $build['ap_genesys_cloud_block']['#attributes']);
  }

  /**
   * An uploaded SVG icon gets the default icon's bubble styling.
   *
   * Not the raster custom-icon treatment — see css/chat.css's
   * .apgc-chatbubble.custom-svg rules, which intentionally mirror
   * .apgc-chatbubble.default/.apgc-chatbubble.default svg exactly.
   */
  public function testCustomSvgIconUsesCustomSvgClass() {
    $file = $this->createTestIconFile('image/svg+xml', 'icon.svg');
    $this->updateDeployment([
      'chat_icon' => [$file->id()],
    ]);

    $build = $this->createBlock()->build();
    $attributes = (string) $build['ap_genesys_cloud_block']['#attributes'];

    $this->assertStringContainsString('custom-svg', $attributes);
    $this->assertStringNotContainsString('custom-icon', $attributes);
  }

  /**
   * An uploaded PNG/JPG icon keeps the raster custom-icon treatment.
   *
   * Guards against the SVG/raster branch in getIcon() being backwards.
   */
  public function testCustomRasterIconUsesCustomIconClass() {
    $file = $this->createTestIconFile('image/png', 'icon.png');
    $this->updateDeployment([
      'chat_icon' => [$file->id()],
    ]);

    $build = $this->createBlock()->build();
    $attributes = (string) $build['ap_genesys_cloud_block']['#attributes'];

    $this->assertStringContainsString('custom-icon', $attributes);
    $this->assertStringNotContainsString('custom-svg', $attributes);
  }

  /**
   * The popup form is omitted entirely when no custom fields are set.
   */
  public function testModalOmittedWithNoCustomFields() {
    $build = $this->createBlock()->build();
    $this->assertArrayNotHasKey('ap_genesys_cloud_modal', $build);
  }

  /**
   * The popup form is included once a custom field is configured.
   */
  public function testModalIncludedWithCustomFields() {
    $this->updateDeployment([
      'custom_fields' => [
        0 => [
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

    $build = $this->createBlock()->build();
    $this->assertArrayHasKey('ap_genesys_cloud_modal', $build);
    $this->assertCount(1, $build['ap_genesys_cloud_modal']['#custom_fields']);
  }

  /**
   * Regression test: an invalid stored colour must never reach raw CSS.
   *
   * Colours are validated in the Branding form, but config can be changed
   * outside it entirely (config import, drush config:set, direct API
   * use) — GenesysChatBlock re-validates independently at render time and
   * falls back to the safe default rather than trusting config, since
   * these values are written into a raw inline <style> block. This test
   * writes a CSS/JS-injection-shaped payload directly onto the profile
   * (bypassing the form) and asserts it never reaches the rendered
   * output.
   */
  public function testInvalidStoredColorsFallBackSafely() {
    $this->updateDeployment([
      'brand_color' => 'red; } body { display:none } /*',
      'text_color' => 'javascript:alert(1)',
    ]);

    $build = $this->createBlock()->build();
    $style = (string) $build['ap_genesys_cloud_block']['#attached']['html_head'][0][0]['#value'];

    $this->assertStringNotContainsString('javascript:', $style);
    $this->assertStringNotContainsString('display:none', $style);
    $this->assertStringContainsString('--apgc-brand-color:#257976', $style);
    $this->assertStringContainsString('--apgc-text-color:#ffffff', $style);
  }

  /**
   * A validly stored, sufficiently-contrasting colour pair is used as-is.
   */
  public function testValidStoredColorIsUsed() {
    $this->updateDeployment([
      // Dark navy on white — a real, non-default pairing that still
      // clears the 4.5:1 WCAG AA minimum (see ColorContrastTest).
      'brand_color' => '#1B4F72',
      'text_color' => '#ffffff',
    ]);

    $build = $this->createBlock()->build();
    $style = (string) $build['ap_genesys_cloud_block']['#attached']['html_head'][0][0]['#value'];

    $this->assertStringContainsString('--apgc-brand-color:#1B4F72', $style);
    $this->assertStringContainsString('--apgc-text-color:#ffffff', $style);
  }

  /**
   * A well-formed but low-contrast pairing falls back when mode is "block".
   *
   * Colour contrast is validated in the Branding form, but config can be
   * changed outside it entirely (config import, drush config:set, direct
   * API use) — GenesysChatBlock re-checks the *combined* contrast
   * independently at render time when accessibility enforcement is set to
   * "block", the same way it already re-checks each colour's own format
   * unconditionally (see testInvalidStoredColorsFallBackSafely).
   */
  public function testLowContrastStoredColorsFallBackSafelyInBlockMode() {
    $this->config('ap_genesys_cloud.accessibility')
      ->set('contrast_enforcement', 'block')
      ->save();
    $this->updateDeployment([
      // Dark navy on black — both individually well-formed hex colours,
      // but only ~2.4:1 contrast against each other.
      'brand_color' => '#1B4F72',
      'text_color' => '#000000',
    ]);

    $build = $this->createBlock()->build();
    $style = (string) $build['ap_genesys_cloud_block']['#attached']['html_head'][0][0]['#value'];

    $this->assertStringContainsString('--apgc-brand-color:#257976', $style);
    $this->assertStringContainsString('--apgc-text-color:#ffffff', $style);
  }

  /**
   * A low-contrast stored pairing renders unchanged outside "block" mode.
   *
   * The default accessibility enforcement is "warning" — the admin was
   * allowed to save this pairing on purpose, so the render layer must not
   * silently override it the way "block" mode does.
   */
  public function testLowContrastStoredColorsPassThroughInWarningMode() {
    // No accessibility config seeded — relies on the '?:' fallback default
    // ('warning') in GenesysChatBlock::buildColorStyle().
    $this->updateDeployment([
      'brand_color' => '#1B4F72',
      'text_color' => '#000000',
    ]);

    $build = $this->createBlock()->build();
    $style = (string) $build['ap_genesys_cloud_block']['#attached']['html_head'][0][0]['#value'];

    $this->assertStringContainsString('--apgc-brand-color:#1B4F72', $style);
    $this->assertStringContainsString('--apgc-text-color:#000000', $style);
  }

  /**
   * The "use theme default colours" toggle omits the colour style entirely.
   *
   * Not just an empty value — the 'html_head' key itself must be absent,
   * so nothing competes with whatever the active theme's own CSS defines
   * for --apgc-brand-color/--apgc-text-color (or chat.css's own hardcoded
   * fallback, if the theme defines nothing).
   */
  public function testUseThemeColorsOmitsColorStyleEntirely() {
    $this->updateDeployment([
      'use_theme_colors' => TRUE,
    ]);

    $build = $this->createBlock()->build();

    $this->assertArrayNotHasKey('html_head', $build['ap_genesys_cloud_block']['#attached']);
  }

  /**
   * A valid custom CSS class is applied to both the trigger and the popup.
   */
  public function testCustomCssClassAppliedToTriggerAndPopup() {
    $this->updateDeployment([
      'custom_css_class' => 'my-theme-chat',
      'custom_fields' => [
        0 => [
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

    $build = $this->createBlock()->build();

    $this->assertStringContainsString('my-theme-chat', (string) $build['ap_genesys_cloud_block']['#attributes']);
    $this->assertSame('my-theme-chat', $build['ap_genesys_cloud_modal']['#custom_css_class']);
  }

  /**
   * An invalid stored custom CSS class is omitted, not substituted.
   *
   * Same config-bypass defense-in-depth reasoning as
   * testInvalidStoredColorsFallBackSafely() — but unlike colours, there's
   * no sensible default class to fall back to, so a malformed value
   * should simply not appear anywhere rather than being replaced.
   */
  public function testInvalidStoredCustomCssClassOmitted() {
    $this->updateDeployment([
      'custom_css_class' => '"><script>alert(1)</script>',
    ]);

    $build = $this->createBlock()->build();

    $this->assertStringNotContainsString('script', (string) $build['ap_genesys_cloud_block']['#attributes']);
  }

  /**
   * The "manage the chat launcher" toggle renders nothing of its own.
   *
   * Only the Genesys bootstrap loader library is attached (still needed
   * so Genesys's own default launcher can appear) — no #theme, icon,
   * colour style, or popup, even with custom fields configured (proving
   * the whole icon/popup path is skipped, not just left empty).
   */
  public function testGenesysManagesLauncherRendersNothingOfItsOwn() {
    $this->updateDeployment([
      'genesys_manages_launcher' => TRUE,
      'custom_fields' => [
        0 => [
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

    $build = $this->createBlock()->build();

    $this->assertSame(['ap_genesys_cloud_chat/chatbot_init'], $build['ap_genesys_cloud_block']['#attached']['library']);
    $this->assertArrayNotHasKey('#theme', $build['ap_genesys_cloud_block']);
    $this->assertArrayNotHasKey('ap_genesys_cloud_modal', $build);
  }

  /**
   * The selected profile drives the deployment values and lead-capture form.
   */
  public function testSelectedDeploymentDrivesBuild() {
    $this->updateDeployment([
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

    $build = $this->createBlock()->build();
    $settings = $build['ap_genesys_cloud_block']['#attached']['drupalSettings']['apGenesysCloud'];
    $this->assertSame('area-a-id', $settings['deploymentId']);
    $this->assertSame('area-a-env', $settings['envName']);
    $this->assertSame('https://apps.usw2.pure.cloud/genesys-bootstrap/genesys.min.js', $settings['bootstrapUrl']);
    $this->assertSame(['area-a-id' => TRUE], $settings['instances']);
    $this->assertCount(1, $settings['customFields']);
    $this->assertSame('fullName', $build['ap_genesys_cloud_modal']['#custom_fields'][0]['id']);
  }

  /**
   * Each placement gets its own profile's branding.
   */
  public function testBrandingComesFromTheSelectedProfile() {
      GenesysChatDeployment::create([
      'id' => 'area_b',
      'label' => 'Area B',
      'environment_name' => 'area-b-env',
      'deployment_id' => 'area-b-id',
      'bootstrap_url' => 'https://apps.mypurecloud.com/genesys-bootstrap/genesys.min.js',
      'brand_color' => '#1B4F72',
      'custom_css_class' => 'area-b-chat',
    ])->save();

    $build_a = $this->createBlock()->build();
    $build_b = $this->createBlock(['deployment' => 'area_b'])->build();

    $this->assertStringContainsString('--apgc-brand-color:#257976', (string) $build_a['ap_genesys_cloud_block']['#attached']['html_head'][0][0]['#value']);
    $this->assertStringContainsString('--apgc-brand-color:#1B4F72', (string) $build_b['ap_genesys_cloud_block']['#attached']['html_head'][0][0]['#value']);
    $this->assertStringNotContainsString('area-b-chat', (string) $build_a['ap_genesys_cloud_block']['#attributes']);
    $this->assertStringContainsString('area-b-chat', (string) $build_b['ap_genesys_cloud_block']['#attributes']);
  }

  /**
   * The render cache follows the profile and the sitewide a11y policy.
   *
   * The colour style built for the block reads
   * ap_genesys_cloud.accessibility, so changing the enforcement mode
   * must invalidate rendered blocks too.
   */
  public function testCacheTags() {
    $tags = $this->createBlock()->build()['ap_genesys_cloud_block']['#cache']['tags'];
    $this->assertContains('config:ap_genesys_cloud_chat.deployment.area_a', $tags);
    $this->assertContains('config:ap_genesys_cloud.accessibility', $tags);
    $this->assertContains('config:system.site', $tags);
  }

  /**
   * The Genesys-managed launcher path also uses the selected profile.
   */
  public function testGenesysManagedLauncherUsesSelectedProfile() {
    $this->updateDeployment(['genesys_manages_launcher' => TRUE]);

    $build = $this->createBlock()->build();
    $settings = $build['ap_genesys_cloud_block']['#attached']['drupalSettings']['apGenesysCloud'];
    $this->assertSame('area-a-id', $settings['deploymentId']);
    $this->assertArrayNotHasKey('customFields', $settings);
    $this->assertContains('config:ap_genesys_cloud_chat.deployment.area_a', $build['ap_genesys_cloud_block']['#cache']['tags']);
  }

  /**
   * With no profile selected, the block renders nothing and logs it.
   */
  public function testNoDeploymentRendersNothing() {
    $logger = $this->attachTestLogger();

    $build = $this->createBlock([])->build();

    $this->assertSame(['#cache'], array_keys($build));
    $this->assertContains('config:ap_genesys_deployment_list', $build['#cache']['tags']);
    $this->assertCount(1, array_filter($logger->messages, function ($message) {
      return strpos($message, 'has no chat deployment selected') !== FALSE;
    }));
  }

  /**
   * A placement whose profile no longer exists renders nothing, and logs.
   *
   * Config could reference a missing profile if altered outside the block
   * form (config import, drush, direct API use). The list cache tag lets
   * the block reappear once the profile is (re)created.
   */
  public function testMissingDeploymentRendersNothing() {
    $logger = $this->attachTestLogger();

    $build = $this->createBlock(['deployment' => 'gone'])->build();

    $this->assertSame(['#cache'], array_keys($build));
    $this->assertContains('config:ap_genesys_deployment_list', $build['#cache']['tags']);
    $this->assertCount(1, array_filter($logger->messages, function ($message) {
      return strpos($message, 'missing chat deployment gone') !== FALSE;
    }));
  }

  /**
   * The plugin declares a dependency on its selected profile only.
   */
  public function testCalculateDependencies() {
    $this->assertSame(['config' => ['ap_genesys_cloud_chat.deployment.area_a']], $this->createBlock()->calculateDependencies());
    $this->assertSame([], $this->createBlock([])->calculateDependencies());
  }

  /**
   * Each field's autocomplete and input type reach the rendered popup.
   *
   * Text fields with a valid value expose their purpose (WCAG 1.3.5) and
   * get a matching input type. Blank, invalid (config changed outside the
   * form) and select fields all render autocomplete="off".
   */
  public function testFieldAutocompleteRendered() {
    $field = [
      'type' => 'text',
      'required' => FALSE,
      'mapping' => 'm',
      'options' => '',
      'weight' => 0,
    ];
    $this->updateDeployment([
      'custom_fields' => [
        ['label' => 'Full Name', 'id' => 'fullName', 'autocomplete' => 'name'] + $field,
        ['label' => 'Phone', 'id' => 'phoneNumber', 'autocomplete' => 'tel'] + $field,
        ['label' => 'Email', 'id' => 'email', 'autocomplete' => 'email'] + $field,
        ['label' => 'License', 'id' => 'license', 'autocomplete' => ''] + $field,
        ['label' => 'Bad', 'id' => 'bad', 'autocomplete' => '"><script>alert(1)</script>'] + $field,
        ['label' => 'Topic', 'id' => 'topic', 'type' => 'select', 'options' => 'A, B', 'autocomplete' => 'name'] + $field,
      ],
    ]);

    $build = $this->createBlock()->build();
    // renderInIsolation() replaced renderPlain() in Drupal 10.3.
    $renderer = \Drupal::service('renderer');
    $method = method_exists($renderer, 'renderInIsolation') ? 'renderInIsolation' : 'renderPlain';
    $html = (string) $renderer->$method($build['ap_genesys_cloud_modal']);

    $this->assertMatchesRegularExpression('/<form[^>]*id="apgc-contact-form"[^>]*autocomplete="off"/', $html);
    $this->assertMatchesRegularExpression('/type="text"[^>]*id="fullName"[^>]*autocomplete="name"/s', $html);
    $this->assertMatchesRegularExpression('/type="tel"[^>]*id="phoneNumber"[^>]*autocomplete="tel"/s', $html);
    $this->assertMatchesRegularExpression('/type="email"[^>]*id="email"[^>]*autocomplete="email"/s', $html);
    $this->assertMatchesRegularExpression('/type="text"[^>]*id="license"[^>]*autocomplete="off"/s', $html);
    $this->assertMatchesRegularExpression('/type="text"[^>]*id="bad"[^>]*autocomplete="off"/s', $html);
    $this->assertMatchesRegularExpression('/<select[^>]*id="topic"[^>]*autocomplete="off"/s', $html);
    $this->assertStringNotContainsString('<script>alert', $html);
  }

  /**
   * A stored bootstrap URL outside Genesys Cloud renders nothing, and logs.
   *
   * The form only accepts Genesys Cloud addresses, but config can be changed
   * outside it, and the URL becomes a <script src> on the page.
   */
  public function testDisallowedBootstrapUrlRendersNothing() {
    $logger = $this->attachTestLogger();
    $this->updateDeployment(['bootstrap_url' => 'https://evil.example/genesys.min.js']);

    $build = $this->createBlock()->build();

    $this->assertSame(['#cache'], array_keys($build));
    $this->assertContains('config:ap_genesys_cloud_chat.deployment.area_a', $build['#cache']['tags']);
    $this->assertCount(1, array_filter($logger->messages, function ($message) {
      return strpos($message, 'not an allowed Genesys Cloud HTTPS address') !== FALSE;
    }));
  }

  /**
   * A domain allowed in settings.php renders normally.
   */
  public function testSettingsAllowedBootstrapDomainRenders() {
    $this->setSetting('ap_genesys_cloud_chat_bootstrap_domains', ['new-region.example']);
    $this->updateDeployment(['bootstrap_url' => 'https://apps.new-region.example/genesys-bootstrap/genesys.min.js']);

    $build = $this->createBlock()->build();
    $settings = $build['ap_genesys_cloud_block']['#attached']['drupalSettings']['apGenesysCloud'];
    $this->assertSame('https://apps.new-region.example/genesys-bootstrap/genesys.min.js', $settings['bootstrapUrl']);
  }

  /**
   * Adds a logger that records formatted messages, for assertions.
   *
   * @return object
   *   The logger; its public $messages property holds what was logged.
   */
  protected function attachTestLogger() {
    $logger = new class() extends AbstractLogger {

      /**
       * Logged messages.
       *
       * @var array
       */
      public $messages = [];

      /**
       * {@inheritdoc}
       */
      public function log($level, $message, array $context = []): void {
        $this->messages[] = strtr((string) $message, $context);
      }

    };
    $this->container->get('logger.factory')->addLogger($logger);
    return $logger;
  }

}
