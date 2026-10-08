<?php

namespace Drupal\ap_genesys_cloud_chat\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\ap_genesys_cloud\ColorContrast;
use Drupal\ap_genesys_cloud_chat\GenesysChatDeploymentInterface;
use Drupal\ap_genesys_cloud_chat\SvgIconSanitizer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configures a deployment profile's chat icon, alt text, and colours.
 *
 * This is the profile's Branding tab; its deployment values are on the
 * Deployment tab (GenesysChatDeploymentForm).
 *
 * @property \Drupal\ap_genesys_cloud_chat\GenesysChatDeploymentInterface $entity
 */
class GenesysChatDeploymentBrandingForm extends EntityForm {

  /**
   * The default brand colour for a new profile.
   */
  const DEFAULT_BRAND_COLOR = GenesysChatDeploymentInterface::DEFAULT_BRAND_COLOR;

  /**
   * The default icon/text colour shown over the brand colour.
   */
  const DEFAULT_TEXT_COLOR = GenesysChatDeploymentInterface::DEFAULT_TEXT_COLOR;

  /**
   * Regex a stored colour value must match before it is trusted.
   *
   */
  const COLOR_PATTERN = '/^#[0-9a-f]{6}$/i';

  /**
   * Regex a stored custom CSS class must match before it is trusted.
   *
   */
  const CSS_CLASS_PATTERN = '/^-?[_a-zA-Z][_a-zA-Z0-9-]*$/';

  /**
   * File extensions accepted for an uploaded chat icon.
   */
  const ICON_EXTENSIONS = 'png jpg svg';

  /**
   * MIME types accepted for an uploaded chat icon, matching ICON_EXTENSIONS.
   */
  const ICON_MIME_TYPES = ['image/png', 'image/jpeg', 'image/svg+xml'];

  /**
   * Max size for an uploaded PNG/JPG chat icon.
   *
   * A PNG/JPG icon displays at 100×100px, so even a 2x-retina source
   * (~200×200px) is small in bytes for any legitimately-sized asset -
   * generous enough to cover a detailed image or one with baked-in text,
   * well below what would actually be needed for a photo-sized upload.
   */
  const MAX_RASTER_ICON_SIZE = 150 * 1024;

  /**
   * Max size for an uploaded SVG chat icon.
   *
   * A clean vector icon at this display size is almost always well under
   * 10 KB; anything approaching this cap usually means embedded base64
   * raster data (which defeats the point of using SVG at all) or an
   * unoptimized design-tool export.
   */
  const MAX_SVG_ICON_SIZE = 50 * 1024;

  /**
   * The file usage service.
   *
   * @var \Drupal\file\FileUsage\FileUsageInterface
   */
  protected $fileUsage;

  /**
   * Constructs a GenesysChatDeploymentBrandingForm.
   *
   * File entities are loaded through $this->entityTypeManager, which
   * EntityForm already provides, at the point of use rather than by
   * injecting the file storage (Drupal practice: storage handlers can be
   * reset, so holding on to one can go stale).
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\file\FileUsage\FileUsageInterface $file_usage
   *   The file usage service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, FileUsageInterface $file_usage) {
    // Used for the sitewide accessibility policy (contrast enforcement).
    $this->configFactory = $config_factory;
    $this->fileUsage = $file_usage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('file.usage')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $deployment = $this->entity;

    $form['genesys_manages_launcher'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Let Genesys Cloud manage the chat launcher'),
      '#description' => $this->t('Check this if Genesys Cloud\'s own default launcher is used instead. This can be adjusted under Genesys Cloud configuration for the service (Appearance and Branding → Launcher Visibility). If using fields, it is recommended to set the Genesys Cloud configuration for the launcher to "hide" and uncheck this setting.', [
        ':url' => Url::fromRoute('ap_genesys_cloud.accessibility_settings')->toString(),
      ]),
      '#default_value' => $deployment->genesysManagesLauncher(),
    ];

    // Everything below is moot while Genesys Cloud's own launcher is
    // doing the job instead - hidden as one unit rather than repeating
    // this condition on every field.
    $form['customization'] = [
      '#type' => 'container',
      '#states' => [
        'invisible' => [
          ':input[name="genesys_manages_launcher"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['customization']['use_theme_colors'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use theme default colors instead of the settings below'),
      '#description' => $this->t('When checked, this module injects no colour override at all and your theme is responsible for styling the chat icon and popup (via the <code>--apgc-brand-color</code>/<code>--apgc-text-color</code> CSS custom properties, if your theme sets them, or by targeting the classes below directly). This also means the colour-contrast checker on the <a href=":url">Accessibility</a> tab cannot check anything in this mode . Please verify your theme\'s own contrast separately if this option is checked.', [
        ':url' => Url::fromRoute('ap_genesys_cloud.accessibility_settings')->toString(),
      ]),
      '#default_value' => $deployment->useThemeColors(),
    ];

    // Purely declarative — no custom JS needed to hide these when the
    // theme-default toggle above is checked.
    $color_states = [
      '#states' => [
        'invisible' => [
          ':input[name="use_theme_colors"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $brand_color_default = $deployment->getBrandColor() ?: self::DEFAULT_BRAND_COLOR;
    $form['customization']['brand_color_row'] = $color_states + [
      '#type' => 'container',
      '#attributes' => ['class' => ['apgc-color-row']],
    ];
    $form['customization']['brand_color_row']['brand_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Button & Header Color'),
      '#description' => $this->t('The background color for the default chat icon, the popup header, and the submit button.'),
      '#default_value' => $brand_color_default,
    ];
    // A plain-text mirror of the colour swatch above: the native colour
    // picker shows no hex value on its face, only in some browsers'
    // tooltips, which admins found hard to read/copy back out. This field
    // is kept in sync with the swatch client-side (js/ap-genesys-cloud.
    // settings.js) and is not itself saved — 'brand_color' (the swatch)
    // remains the actual submitted/validated value.
    $form['customization']['brand_color_row']['brand_color_hex'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Button & Header Color hex value'),
      '#title_display' => 'invisible',
      '#size' => 8,
      '#maxlength' => 7,
      '#default_value' => $brand_color_default,
      '#placeholder' => self::DEFAULT_BRAND_COLOR,
      '#attributes' => ['class' => ['apgc-color-hex']],
    ];

    $text_color_default = $deployment->getTextColor() ?: self::DEFAULT_TEXT_COLOR;
    $form['customization']['text_color_row'] = $color_states + [
      '#type' => 'container',
      '#attributes' => ['class' => ['apgc-color-row']],
    ];
    $form['customization']['text_color_row']['text_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Icon & Text Color'),
      '#description' => $this->t('The color used for the default chat icon and for text shown over the Button & Header Color. Checked for contrast against that color per the policy on the <a href=":url">Accessibility</a> tab, if enabled.', [
        ':url' => Url::fromRoute('ap_genesys_cloud.accessibility_settings')->toString(),
      ]),
      '#default_value' => $text_color_default,
    ];
    // Same hex-mirror pattern as brand_color_row above
    $form['customization']['text_color_row']['text_color_hex'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Icon & Text Color hex value'),
      '#title_display' => 'invisible',
      '#size' => 8,
      '#maxlength' => 7,
      '#default_value' => $text_color_default,
      '#placeholder' => self::DEFAULT_TEXT_COLOR,
      '#attributes' => ['class' => ['apgc-color-hex']],
    ];

    $form['customization']['chat_icon'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Chat Icon'),
      '#description' => $this->t('(Optional) Upload a custom chat icon in PNG, JPG, or SVG format. If left empty, a standard chat bubble icon is used instead. The icon displays at 100×100px for PNG/JPG images, and 75x75 for SVG. Max size: 150 KB for PNG/JPG, 50 KB for SVG.'),
      '#upload_location' => 'public://ap-genesys/chatbot/',
      '#default_value' => $deployment->getChatIcon(),
      // Only the extension allowlist runs at upload time: it has to, or a
      // disallowed file would already be sitting in public storage by the
      // time the form is saved. The type and per-format size checks run in
      // validateForm().
      '#upload_validators' => $this->getIconUploadValidators(),
      '#multiple' => FALSE,
    ];

    $form['customization']['icon_alttext'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom Chat Icon Alt Text'),
      '#description' => $this->t('(Optional) Enter custom alternative text for the chat icon, for accessibility purposes. If left empty, "Enter a text chat with a live agent!" is used instead.'),
      '#default_value' => $deployment->getIconAltText(),
      '#placeholder' => $this->t('Enter a text chat with a live agent!'),
    ];

    $form['customization']['custom_css_class'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Additional CSS Class'),
      '#description' => $this->t('(Optional) Adds this class to both the chat icon and the popup, so your theme can style them directly instead of (or alongside) the color settings above. One CSS class name, e.g. <code>my-theme-chat</code>. If your CSS changes manipulate color, it will not be picked up by <a href=":url">Accessibility</a> and will need manual review.', [
        ':url' => Url::fromRoute('ap_genesys_cloud.accessibility_settings')->toString(),
      ]),
      '#default_value' => $deployment->getCustomCssClass(),
      '#placeholder' => 'my-theme-chat',
    ];

    $form['#attached']['library'][] = 'ap_genesys_cloud_chat/settings_form';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Nothing below matters while Genesys Cloud's own launcher is doing
    // the job instead. Skip the rest of validation entirely.
    if ($form_state->getValue('genesys_manages_launcher')) {
      return;
    }

    // Only sanitize icons that passed the type and size checks.
    if ($this->validateNewIcons($form_state)) {
      $this->sanitizeNewSvgIcon($form_state);
    }

    $custom_css_class = (string) $form_state->getValue('custom_css_class');
    if ($custom_css_class !== '' && !preg_match(self::CSS_CLASS_PATTERN, $custom_css_class)) {
      $form_state->setErrorByName('custom_css_class', $this->t('Additional CSS Class must be a single valid CSS class name (letters, digits, hyphens, or underscores; cannot start with a digit).'));
    }

    // When the theme is providing its own colours, this module's colour
    // fields aren't used for anything - validating their format, and
    // checking their contrast against each other, would only produce
    // confusing errors about values that never actually render.
    if ($form_state->getValue('use_theme_colors')) {
      return;
    }

    // #type 'color' only enforces #rrggbb client-side (and not at all for a
    // scripted/direct POST); this value is later written into a raw inline
    // <style> block by ChatBlock, so it must be a strict hex colour by
    // the time it's saved, not just "whatever the browser sent."
    $brand_color = (string) $form_state->getValue('brand_color');
    $brand_color_valid = (bool) preg_match(self::COLOR_PATTERN, $brand_color);
    if (!$brand_color_valid) {
      $form_state->setErrorByName('brand_color', $this->t('Button & Header Color must be a valid hex colour (e.g. #4A5568).'));
    }

    // Icon & Text Colour is a free colour (not restricted to white/black),
    // so it needs the same strict-hex format check.
    $text_color = (string) $form_state->getValue('text_color');
    $text_color_valid = (bool) preg_match(self::COLOR_PATTERN, $text_color);
    if (!$text_color_valid) {
      $form_state->setErrorByName('text_color', $this->t('Icon & Text Color must be a valid hex colour (e.g. #ffffff).'));
    }

    // Each colour can be individually well-formed and still combine into a
    // pairing that's unreadable (e.g. a light Button & Header Colour with
    // white text). Only checked once both pass their own format checks
    // above, since ColorContrast::ratio() assumes strict 6-digit hex
    // input. How strictly this is enforced is itself configurable since
    // different sites target different WCAG levels and some admins want
    // a warning rather than a hard block.
    if ($brand_color_valid && $text_color_valid) {
      $accessibility = $this->configFactory->get('ap_genesys_cloud.accessibility');
      $enforcement = $accessibility->get('contrast_enforcement') ?: 'warning';
      $wcag_level = $accessibility->get('wcag_level') ?: 'AA';
      $min_ratio = ColorContrast::minRatioForLevel($wcag_level);

      // Enforcement 'none', or a level with no numeric requirement at all
      // (WCAG Level A has no text-contrast success criterion, though it
      // isn't offered as a choice on the Accessibility tab), means
      // there's nothing to check.
      if ($enforcement !== 'none' && $min_ratio !== NULL) {
        $ratio = ColorContrast::ratio($brand_color, $text_color);
        if ($ratio < $min_ratio) {
          $message = $this->t('Button & Header Color and Icon & Text Color only have @ratio:1 contrast — WCAG 2.1/2.2 @level requires at least @min:1. To be in compliance with WCAG, revisit the chosen colors and address accordingly.', [
            '@ratio' => number_format($ratio, 2),
            '@level' => $wcag_level,
            '@min' => $min_ratio,
          ]);
          if ($enforcement === 'block') {
            $form_state->setErrorByName('brand_color', $message);
          }
          else {
            $this->messenger()->addWarning($message);
          }
        }
      }
    }
  }

  /**
   * Builds the chat icon's upload-time validators for the running core.
   *
   * Drupal 10.2 replaced the file_validate_*() functions with validation
   * constraint plugins (the file.validator service), and Drupal 11 removed
   * the old names, so the allowlist is declared whichever way this core
   * understands. Without any extension validator, Drupal falls back to its
   * default list, which excludes SVG.
   *
   * @return array
   *   The #upload_validators array.
   */
  protected function getIconUploadValidators() {
    if (interface_exists('Drupal\file\Validation\FileValidatorInterface')) {
      return ['FileExtension' => ['extensions' => self::ICON_EXTENSIONS]];
    }
    return ['file_validate_extensions' => [self::ICON_EXTENSIONS]];
  }

  /**
   * Checks each newly uploaded chat icon's type and size.
   *
   * Only touches fids not already saved on the profile, same as
   * sanitizeNewSvgIcon().
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return bool
   *   TRUE if every new icon passed.
   */
  protected function validateNewIcons(FormStateInterface $form_state) {
    $valid = TRUE;
    $new_fids = $form_state->getValue('chat_icon') ?: [];
    foreach (array_diff($new_fids, $this->getSavedChatIcon()) as $fid) {
      $file = $this->entityTypeManager->getStorage('file')->load($fid);
      if (!$file) {
        continue;
      }
      foreach ($this->getIconFileErrors($file) as $error) {
        $form_state->setErrorByName('chat_icon', $error);
        $valid = FALSE;
      }
    }
    return $valid;
  }

  /**
   * Gets the reasons an uploaded file can't be used as the chat icon.
   *
   * SVGs get a smaller size cap than PNG/JPG: the icon only displays at
   * 75×75px (SVG) or 100×100px (PNG/JPG), so a flat multi-megabyte cap
   * isn't a meaningful guide for either format, and a clean SVG at this
   * size needs far less headroom than a raster that might have baked-in
   * text or artwork.
   *
   * @param \Drupal\file\FileInterface $file
   *   The uploaded file.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   Error messages, or an empty array if the file is acceptable.
   */
  public function getIconFileErrors(FileInterface $file) {
    $mime = $file->getMimeType();
    if (!in_array($mime, self::ICON_MIME_TYPES, TRUE)) {
      return [
        $this->t('The chat icon must be a PNG, JPG, or SVG file.'),
      ];
    }

    $is_svg = $mime === 'image/svg+xml';
    $limit = $is_svg ? self::MAX_SVG_ICON_SIZE : self::MAX_RASTER_ICON_SIZE;
    if ($file->getSize() > $limit) {
      return [
        $this->t('The icon is @size, which exceeds the @limit limit for @type files.', [
          '@size' => $this->formatSize($file->getSize()),
          '@limit' => $this->formatSize($limit),
          '@type' => $is_svg ? 'SVG' : 'PNG/JPG',
        ]),
      ];
    }

    return [];
  }

  /**
   * Formats a byte count for display, on any supported core.
   *
   * The format_size() function was deprecated in Drupal 10.2 and removed
   * in 11, in favour of ByteSizeMarkup.
   *
   * @param int $bytes
   *   The size in bytes.
   *
   * @return \Drupal\Component\Render\MarkupInterface|string
   *   The formatted size.
   */
  protected function formatSize($bytes) {
    if (class_exists(ByteSizeMarkup::class)) {
      return ByteSizeMarkup::create($bytes);
    }
    // Only reached on cores before 10.2, where format_size() still exists
    return format_size($bytes);
  }

  /**
   * Sanitizes a newly-uploaded SVG chat icon in place, or rejects it.
   *
   * Runs in validateForm() rather than submitForm() specifically so an SVG
   * that can't be safely sanitized (embedded script, an unparseable/DOCTYPE
   * payload the library refuses to touch) is rejected with a form error
   * before the file is ever marked permanent - mirrors how brand_color/
   * text_color/custom_css_class are validated here, not just checked after
   * the fact. Only touches fids not already saved on the profile (same
   * array_diff pattern as updateChatIconUsage()), so re-saving the form
   * without changing the icon never re-sanitizes an already-clean file.
   *
   * Rendering an uploaded icon only ever happens via <img src>, which
   * doesn't execute embedded script - but the raw file is still reachable
   * at a direct public URL, where a browser navigating to it directly
   * would execute an embedded <script>. Sanitizing on upload closes that
   * gap regardless of how the file is later served.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  protected function sanitizeNewSvgIcon(FormStateInterface $form_state) {
    $old_fids = $this->getSavedChatIcon();
    $new_fids = $form_state->getValue('chat_icon') ?: [];

    foreach (array_diff($new_fids, $old_fids) as $fid) {
      $file = $this->entityTypeManager->getStorage('file')->load($fid);
      if (!$file || $file->getMimeType() !== 'image/svg+xml') {
        continue;
      }

      $uri = $file->getFileUri();
      $clean = SvgIconSanitizer::sanitize(file_get_contents($uri));
      if ($clean === FALSE) {
        $form_state->setErrorByName('chat_icon', $this->t('This SVG could not be safely processed and was rejected. Try re-exporting it from your design tool, or upload a PNG/JPG instead.'));
        continue;
      }

      file_put_contents($uri, $clean);
      $file->setSize(strlen($clean));
      $file->save();
    }
  }

  /**
   * Gets the chat icon file IDs as currently saved, before this submit.
   *
   * $this->entity already carries the submitted values by the time
   * validation and save run, so the stored ones are reloaded instead.
   *
   * @return int[]
   *   The saved file IDs, or an empty list.
   */
  protected function getSavedChatIcon() {
    if ($this->entity->isNew()) {
      return [];
    }
    $unchanged = $this->entityTypeManager->getStorage($this->entity->getEntityTypeId())->loadUnchanged($this->entity->id());
    return $unchanged ? $unchanged->getChatIcon() : [];
  }

  /**
   * {@inheritdoc}
   *
   * Copies only this tab's own properties. The parent would copy every
   * form value (including the hex mirror fields) onto the entity as
   * undeclared properties.
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    foreach (['icon_alttext', 'brand_color', 'text_color', 'custom_css_class'] as $key) {
      if ($form_state->hasValue($key)) {
        $entity->set($key, (string) $form_state->getValue($key));
      }
    }
    foreach (['use_theme_colors', 'genesys_manages_launcher'] as $key) {
      if ($form_state->hasValue($key)) {
        $entity->set($key, (bool) $form_state->getValue($key));
      }
    }
    if ($form_state->hasValue('chat_icon')) {
      $entity->set('chat_icon', $this->normalizeFids($form_state->getValue('chat_icon')));
    }
  }

  /**
   * Normalizes a managed_file value to a list of file IDs.
   *
   * EntityForm also rebuilds the entity from #after_build, while input is
   * still being processed — before managed_file has reduced its value to
   * a plain list of fids. At that point the value is still the widget's
   * raw shape: a 'fids' entry (a space-separated string, as its hidden
   * field submits it) alongside its upload/remove buttons.
   *
   * @param mixed $value
   *   The submitted chat_icon value.
   *
   * @return int[]
   *   The file IDs.
   */
  protected function normalizeFids($value) {
    if (is_array($value) && array_key_exists('fids', $value)) {
      $value = $value['fids'];
    }
    if (is_string($value)) {
      $value = preg_split('/\s+/', trim($value));
    }
    return array_values(array_filter(array_map('intval', (array) $value)));
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $this->updateChatIconUsage($this->getSavedChatIcon(), $this->entity->getChatIcon());
    $status = parent::save($form, $form_state);
    $this->messenger()->addStatus($this->t('Saved the branding for the %label chat deployment.', [
      '%label' => $this->entity->label(),
    ]));
    return $status;
  }

  /**
   * Marks newly-referenced icon files permanent and releases old ones.
   *
   * `#type => 'managed_file'` uploads default to Drupal's "temporary" file
   * status. Without this, an uploaded icon would be silently deleted by
   * file_cron() the next time it runs (temporary files unreferenced by any
   * file_usage entry are deleted after `system.file.temporary_maximum_age`,
   * 6 hours by default) — even though the profile still points at it. See
   * file.module's file_cron(). Usage is recorded per profile (see
   * GenesysChatDeploymentInterface::getFileUsageType()).
   *
   * @param array $old_fids
   *   The file IDs previously saved on the profile.
   * @param array $new_fids
   *   The file IDs being saved now.
   */
  protected function updateChatIconUsage(array $old_fids, array $new_fids) {
    foreach (array_diff($old_fids, $new_fids) as $fid) {
      if ($file = $this->entityTypeManager->getStorage('file')->load($fid)) {
        $this->fileUsage->delete($file, 'ap_genesys_cloud_chat', $this->entity->getFileUsageType(), GenesysChatDeploymentInterface::FILE_USAGE_ID);
        // Permanent files are never touched by file_cron()'s garbage
        // collection (it only considers temporary ones), so an orphaned
        // permanent file with no remaining usage would sit forever unless
        // deleted here.
        if (!$this->fileUsage->listUsage($file)) {
          $file->delete();
        }
      }
    }

    foreach (array_diff($new_fids, $old_fids) as $fid) {
      if ($file = $this->entityTypeManager->getStorage('file')->load($fid)) {
        $file->setPermanent();
        $file->save();
        $this->fileUsage->add($file, 'ap_genesys_cloud_chat', $this->entity->getFileUsageType(), GenesysChatDeploymentInterface::FILE_USAGE_ID);
      }
    }
  }

}
