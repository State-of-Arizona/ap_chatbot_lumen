<?php

namespace Drupal\ap_chatbot_lumen\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Site\Settings;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a Chat Icon block.
 *
 * @Block(
 *   id = "ap_chatlumen_block",
 *   admin_label = @Translation("Chat Icon Block"),
 * )
 */
class LumenChatBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = \Drupal::config('ap_chatbot_lumen.settings');
    $chat_icon = $config->get('chat_icon');
    $icon_alttext = $config->get('icon_alttext') ?: $this->t('Enter a text chat with a live agent!');
    $icon_url = $this->getIconUrl($config);
    $icon_css = 'chatbubble default';
    $deployment_id = $config->get('deployment_id');
    $environment_name = $config->get('environment_name');
    $fields = $config->get('custom_fields') ?? [];
    $site_name = \Drupal::config('system.site')->get('name');

    // If a custom icon is uploaded, retrieve its URL.
    if (!empty($chat_icon)) {
      $file = \Drupal\file\Entity\File::load($chat_icon[0]);
      if ($file) {
        $icon_url = $file->createFileUrl() . '?v=' . time(); // Add a timestamp to bust cache. It's kinda needed here.
        $icon_css = 'chatbubble custom-icon';
      }
    }

    // Create attributes
    $chatAttributes = new Attribute();
    $chatAttributes->addClass($icon_css);


    $buildChat = [];
    $buildChat['ap_chatlumen_block'] = [
      '#theme' => 'ap_chatlumen_icon',
      '#icon_url' => $icon_url,
      '#icon_alttext' => $icon_alttext,
      '#attributes' => $chatAttributes,
      '#attached' => [
        'library' => [
          'ap_chatbot_lumen/chatbot_init',
          'ap_chatbot_lumen/chat_icon',
        ],
        'drupalSettings' => [
          'apChatbotLumen' => [
            'deploymentId' => $deployment_id,
            'envName' => $environment_name,
            'customFields' => $fields,
          ],
        ],
      ],
      '#cache' => [
        'tags' => ['config:ap_chatbot_lumen.settings'], // Invalidate the cache when configuration is changed.
      ],
    ];

    $buildChat['ap_chat_modal'] = [
      '#theme' => 'ap_chat_modal',
      '#customFields' => $fields,
      '#deploymentId' => $deployment_id,
      '#envName' => $environment_name,
      '#site_name' => $site_name,
    ];

    // Render the floating chat icon.
    return $buildChat;
  }

  private function getIconUrl($config) {
    $chat_icon = $config->get('chat_icon');
    if (!empty($chat_icon)) {
      $file = \Drupal\file\Entity\File::load($chat_icon[0]);
      if ($file) {
        return $file->createFileUrl();
      }
    }
    return \Drupal::service('extension.list.module')->getPath('ap_chatbot_lumen') . '/images/default-icon-chat.svg';
  }
}
