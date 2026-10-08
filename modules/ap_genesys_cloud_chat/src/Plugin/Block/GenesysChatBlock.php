<?php

namespace Drupal\ap_genesys_cloud_chat\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Template\Attribute as HtmlAttribute;
use Drupal\Core\Url;
use Drupal\ap_genesys_cloud\ColorContrast;
use Drupal\ap_genesys_cloud_chat\AutocompleteToken;
use Drupal\ap_genesys_cloud_chat\BootstrapUrl;
use Drupal\ap_genesys_cloud_chat\GenesysChatDeploymentInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the floating Chat Icon block.
 *
 * Declared both as a PHP attribute (read by Drupal 10.2+, where the
 * annotation is deprecated) and as an annotation (read by Drupal 9, which
 * ignores the attribute). Drop the annotation when Drupal 9 support ends.
 *
 * @Block(
 *   id = "ap_genesys_cloud_block",
 *   admin_label = @Translation("Chat Icon Block"),
 * )
 */
#[Block(
  id: 'ap_genesys_cloud_block',
  admin_label: new TranslatableMarkup('Chat Icon Block'),
)]
class GenesysChatBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The default chat icon path, relative to the module.
   */
  const DEFAULT_ICON_PATH = '/images/default-icon-chat.svg';

  /**
   * The fallback brand colour for an invalid stored value.
   */
  const DEFAULT_BRAND_COLOR = GenesysChatDeploymentInterface::DEFAULT_BRAND_COLOR;

  /**
   * The fallback icon/text colour for an invalid stored value.
   */
  const DEFAULT_TEXT_COLOR = GenesysChatDeploymentInterface::DEFAULT_TEXT_COLOR;

  /**
   * Regex a colour value must match before it's trusted in inline CSS.
   */
  const COLOR_PATTERN = '/^#[0-9a-f]{6}$/i';

  /**
   * Regex a stored custom CSS class must match before it's trusted.
   */
  const CSS_CLASS_PATTERN = '/^-?[_a-zA-Z][_a-zA-Z0-9-]*$/';

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager.
   *
   * Storages are fetched from it where they're used, not injected or kept,
   * since storage handlers can be reset.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * The bootstrap URL checker.
   *
   * @var \Drupal\ap_genesys_cloud_chat\BootstrapUrl
   */
  protected $bootstrapUrl;

  /**
   * The module's logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a GenesysChatBlock.
   *
   * @param array $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator
   *   The file URL generator.
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_extension_list
   *   The module extension list.
   * @param \Psr\Log\LoggerInterface $logger
   *   The module's logger channel.
   * @param \Drupal\ap_genesys_cloud_chat\BootstrapUrl $bootstrap_url
   *   The bootstrap URL checker.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    FileUrlGeneratorInterface $file_url_generator,
    ModuleExtensionList $module_extension_list,
    LoggerInterface $logger,
    BootstrapUrl $bootstrap_url,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->fileUrlGenerator = $file_url_generator;
    $this->moduleExtensionList = $module_extension_list;
    $this->logger = $logger;
    $this->bootstrapUrl = $bootstrap_url;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('file_url_generator'),
      $container->get('extension.list.module'),
      $container->get('logger.factory')->get('ap_genesys_cloud_chat'),
      $container->get('ap_genesys_cloud_chat.bootstrap_url')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    // Empty until a profile is chosen; the block form requires one.
    return ['deployment' => ''] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $options = [];
    foreach ($this->entityTypeManager->getStorage('ap_genesys_deployment')->loadMultiple() as $deployment) {
      $options[$deployment->id()] = $deployment->label();
    }
    asort($options, SORT_NATURAL | SORT_FLAG_CASE);

    $description = $this->t("Which Genesys deployment, and its branding, this placement loads. Use the block's Visibility settings to choose the pages it appears on. Only one chat deployment can run on a page, so placements with different deployments must not be visible on the same page.");
    if (!$options) {
      $description = $this->t('No chat deployments exist yet. <a href=":url">Add a chat deployment</a> first, then come back to place this block.', [
        ':url' => Url::fromRoute('entity.ap_genesys_deployment.add_form')->toString(),
      ]);
    }

    $form['deployment'] = [
      '#type' => 'select',
      '#title' => $this->t('Chat deployment'),
      '#description' => $description,
      '#options' => $options,
      '#default_value' => $this->configuration['deployment'],
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['deployment'] = (string) $form_state->getValue('deployment');
  }

  /**
   * {@inheritdoc}
   *
   * Declares the selected deployment profile as a config dependency, so
   * config import creates the profile before this block placement.
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();
    if ($this->configuration['deployment'] !== '' && $deployment = $this->entityTypeManager->getStorage('ap_genesys_deployment')->load($this->configuration['deployment'])) {
      $dependencies[$deployment->getConfigDependencyKey()][] = $deployment->getConfigDependencyName();
    }
    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $deployment = $this->resolveDeployment();
    if (!$deployment) {
      // Renders nothing, but reappears once the profile is (re)created or
      // another one is selected.
      return [
        '#cache' => [
          'tags' => $this->entityTypeManager->getStorage('ap_genesys_deployment')->getEntityType()->getListCacheTags(),
        ],
      ];
    }

    // The bootstrap URL becomes a <script src> on this page. The form only
    // accepts Genesys Cloud addresses, but config can be changed outside
    // it (config import, drush, direct API use), so it's checked again
    // here. Renders nothing until the profile is fixed.
    if (!$this->bootstrapUrl->isAllowed($deployment->getBootstrapUrl())) {
      $this->logger->warning('Chat deployment %deployment has a bootstrap script URL that is not an allowed Genesys Cloud HTTPS address, so its Chat Icon Block renders nothing.', [
        '%deployment' => $deployment->id(),
      ]);
      return [
        '#cache' => [
          'tags' => $deployment->getCacheTags(),
        ],
      ];
    }

    // The profile, plus the sitewide policy buildColorStyle() reads and
    // the site name shown in the popup.
    $cache_tags = Cache::mergeTags(
      $deployment->getCacheTags(),
      $this->configFactory->get('ap_genesys_cloud.accessibility')->getCacheTags(),
      $this->configFactory->get('system.site')->getCacheTags()
    );

    // Genesys Cloud's own default launcher is standing in for this
    // module's icon/popup usually because that launcher hasn't been hidden
    // in Genesys Cloud Admin yet, so showing this module's own icon too
    // would just duplicate it. Still attach the Genesys bootstrap script,
    // but render none of this module's own markup.
    if ($deployment->genesysManagesLauncher()) {
      return [
        'ap_genesys_cloud_block' => [
          '#attached' => [
            'library' => ['ap_genesys_cloud_chat/chatbot_init'],
            'drupalSettings' => [
              'apGenesysCloud' => $this->buildJsSettings($deployment, FALSE),
            ],
          ],
          '#cache' => [
            'tags' => $cache_tags,
          ],
        ],
      ];
    }

    $fields = $deployment->getCustomFields();
    $site_name = $this->configFactory->get('system.site')->get('name');

    ['url' => $icon_url, 'svg' => $icon_svg, 'css_class' => $icon_css] = $this->getIcon($deployment);
    $custom_css_class = $this->sanitizeCssClass($deployment->getCustomCssClass());

    $chat_attributes = new HtmlAttribute();
    $chat_attributes->addClass($icon_css);
    if ($custom_css_class !== '') {
      $chat_attributes->addClass($custom_css_class);
    }

    $color_style = $this->buildColorStyle($deployment);

    $build = [];
    $build['ap_genesys_cloud_block'] = [
      '#theme' => 'ap_genesys_cloud_icon',
      '#icon_url' => $icon_url,
      '#icon_svg' => $icon_svg,
      '#icon_alttext' => $deployment->getIconAltText() ?: $this->t('Enter a text chat with a live agent!'),
      '#attributes' => $chat_attributes,
      '#attached' => [
        'library' => [
          'ap_genesys_cloud_chat/chatbot_init',
          'ap_genesys_cloud_chat/chat_icon',
        ],
        'drupalSettings' => [
          'apGenesysCloud' => $this->buildJsSettings($deployment, TRUE),
        ],
      ],
      '#cache' => [
        'tags' => $cache_tags,
      ],
    ];

    // Omitted entirely (not just left empty) when the site has opted to use
    // its theme's own colours instead
    if ($color_style !== NULL) {
      $build['ap_genesys_cloud_block']['#attached']['html_head'][] = [$color_style, 'ap_genesys_cloud_colors'];
    }

    // Sites with no lead-capture fields configured (e.g. an agency whose
    // provided script is just the bootstrap snippet, with no form) skip the
    // popup entirely, clicking the icon opens Messenger directly instead.
    if (!empty($fields)) {
      $build['ap_genesys_cloud_modal'] = [
        '#theme' => 'ap_genesys_cloud_modal',
        '#custom_fields' => $this->prepareFieldsForDisplay($fields),
        '#site_name' => $site_name,
        '#custom_css_class' => $custom_css_class,
        '#cache' => [
          'tags' => $cache_tags,
        ],
      ];
    }

    return $build;
  }

  /**
   * Loads the deployment profile this placement uses.
   *
   * @return \Drupal\ap_genesys_cloud_chat\GenesysChatDeploymentInterface|null
   *   The selected profile, or NULL if none is selected or it no longer
   *   exists (e.g. config altered outside the block form). Either case is
   *   logged, since the block then renders nothing.
   */
  protected function resolveDeployment() {
    $deployment_name = (string) ($this->configuration['deployment'] ?? '');
    $deployment = $deployment_name !== '' ? $this->entityTypeManager->getStorage('ap_genesys_deployment')->load($deployment_name) : NULL;
    if ($deployment) {
      return $deployment;
    }

    if ($deployment_name === '') {
      $this->logger->warning('A Chat Icon Block has no chat deployment selected, so it renders nothing. Select one in its block settings.');
    }
    else {
      $this->logger->warning('A Chat Icon Block references missing chat deployment %deployment, so it renders nothing. Select another one in its block settings.', [
        '%deployment' => $deployment_name,
      ]);
    }
    return NULL;
  }

  /**
   * Builds the drupalSettings.apGenesysCloud values for a deployment.
   *
   * @param \Drupal\ap_genesys_cloud_chat\GenesysChatDeploymentInterface $deployment
   *   The deployment profile.
   * @param bool $include_fields
   *   Whether to include the lead-capture fields (not needed when Genesys
   *   manages its own launcher, since this module's popup isn't shown).
   *
   * @return array
   *   The settings array.
   */
  protected function buildJsSettings(GenesysChatDeploymentInterface $deployment, $include_fields) {
    $settings = [
      'deploymentId' => $deployment->getDeploymentId(),
      'envName' => $deployment->getEnvironmentName(),
      'bootstrapUrl' => $deployment->getBootstrapUrl(),
      'instances' => [$deployment->getDeploymentId() => TRUE],
    ];
    if ($include_fields) {
      $settings['customFields'] = $deployment->getCustomFields();
    }
    return $settings;
  }

  /**
   * Adds each lead-capture field's autocomplete and input type for output.
   *
   * Text fields with a valid autocomplete value expose their purpose (WCAG
   * 2.1 SC 1.3.5) and get a matching input type; every other field, and
   * any stored value that isn't valid (config can be changed outside the
   * form), is rendered with autocomplete="off".
   *
   * @param array $fields
   *   The deployment's lead-capture fields.
   *
   * @return array
   *   The fields, each with 'autocomplete_attribute' and 'input_type'.
   */
  protected function prepareFieldsForDisplay(array $fields) {
    foreach ($fields as $key => $field) {
      $token = ($field['type'] ?? 'text') === 'select' ? '' : AutocompleteToken::normalize($field['autocomplete'] ?? '');
      $fields[$key]['autocomplete_attribute'] = $token ?: 'off';
      $fields[$key]['input_type'] = $token ? AutocompleteToken::inputType($token) : 'text';
    }
    return $fields;
  }

  /**
   * Resolves the chat icon to render, and its wrapper CSS class.
   *
   * @param \Drupal\ap_genesys_cloud_chat\GenesysChatDeploymentInterface $deployment
   *   The deployment profile.
   *
   * @return array
   *   An array with keys 'url' (string|null), 'svg' (Markup|null, trusted
   *   inline SVG for the default icon only) and 'css_class' (string) —
   *   'apgc-chatbubble default', 'apgc-chatbubble custom-icon' (uploaded
   *   PNG/JPG), or 'apgc-chatbubble custom-svg' (uploaded SVG).
   */
  protected function getIcon(GenesysChatDeploymentInterface $deployment) {
    $chat_icon = $deployment->getChatIcon();
    if (!empty($chat_icon[0])) {
      $file = $this->entityTypeManager->getStorage('file')->load($chat_icon[0]);
      if ($file) {
        // An uploaded SVG reuses the default icon's bubble styling (see
        // css/chat.css's .apgc-chatbubble.custom-svg rules) instead of the
        // raster custom-icon treatment
        $css_class = $file->getMimeType() === 'image/svg+xml' ? 'custom-svg' : 'custom-icon';
        return [
          'url' => $this->fileUrlGenerator->generateString($file->getFileUri()),
          'svg' => NULL,
          'css_class' => 'apgc-chatbubble ' . $css_class,
        ];
      }
    }

    $module_path = $this->moduleExtensionList->getPath('ap_genesys_cloud_chat');
    $icon_path = DRUPAL_ROOT . '/' . $module_path . self::DEFAULT_ICON_PATH;
    $svg_contents = is_readable($icon_path) ? file_get_contents($icon_path) : FALSE;

    return [
      'url' => NULL,
      'svg' => $svg_contents !== FALSE ? Markup::create($svg_contents) : NULL,
      'css_class' => 'apgc-chatbubble default',
    ];
  }

  /**
   * Builds the inline <style> element carrying the configured colours.
   *
   * @param \Drupal\ap_genesys_cloud_chat\GenesysChatDeploymentInterface $deployment
   *   The deployment profile.
   *
   * @return array|null
   *   A render array for use in #attached['html_head'], or NULL if the
   *   site has opted to use its theme's own colours instead (see
   *   GenesysChatDeploymentBrandingForm's "Use theme default colours" option) --
   *   in which case nothing should be injected at all, so the theme's
   *   own CSS (or this module's own hardcoded fallback in chat.css) can
   *   take effect.
   */
  protected function buildColorStyle(GenesysChatDeploymentInterface $deployment) {
    if ($deployment->useThemeColors()) {
      return NULL;
    }

    $brand_color = $this->sanitizeColor($deployment->getBrandColor(), self::DEFAULT_BRAND_COLOR);
    $text_color = $this->sanitizeColor($deployment->getTextColor(), self::DEFAULT_TEXT_COLOR);

    $accessibility = $this->configFactory->get('ap_genesys_cloud.accessibility');
    if ($accessibility->get('contrast_enforcement') === 'block') {
      $min_ratio = ColorContrast::minRatioForLevel($accessibility->get('wcag_level') ?: 'AA');
      if ($min_ratio !== NULL && ColorContrast::ratio($brand_color, $text_color) < $min_ratio) {
        $brand_color = self::DEFAULT_BRAND_COLOR;
        $text_color = self::DEFAULT_TEXT_COLOR;
      }
    }

    return [
      '#tag' => 'style',
      '#value' => Markup::create(
        ':root{--apgc-brand-color:' . $brand_color . ';--apgc-text-color:' . $text_color . ';}'
      ),
    ];
  }

  /**
   * Validates a stored colour value, falling back to a safe default.
   *
   * @param mixed $color
   *   The value read from config.
   * @param string $default
   *   The fallback to use if $color isn't a strict 6-digit hex colour.
   *
   * @return string
   *   A trusted, strict hex colour string.
   */
  protected function sanitizeColor($color, string $default) {
    if (is_string($color) && preg_match(self::COLOR_PATTERN, $color)) {
      return $color;
    }
    return $default;
  }

  /**
   * Validates a stored custom CSS class, omitting it if malformed.
   *
   * Unlike sanitizeColor(), there's no sensible default class to fall
   * back to, an invalid stored value (e.g. from a config bypass) is
   * simply dropped rather than substituted.
   *
   * @param mixed $class
   *   The value read from config.
   *
   * @return string
   *   A trusted CSS class name, or an empty string if $class wasn't one.
   */
  protected function sanitizeCssClass($class) {
    if (is_string($class) && $class !== '' && preg_match(self::CSS_CLASS_PATTERN, $class)) {
      return $class;
    }
    return '';
  }

}
