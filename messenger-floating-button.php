<?php
/**
 * Plugin Name: JOY Messenger Floating Button
 * Plugin URI: https://ssilin.ru/blog/knopka-messendzhera-max-i-telegram-dlya-sajta-plagin-dlya-wordpress/
 * Description: Floating button for Telegram, WhatsApp, MAX messengers and phone calls with live admin preview.
 * Version: 2.5.0
 * Author: Stas Silin
 * License: GPL v2 or later
 * Text Domain: joy-messenger-floating-button
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MFB_VERSION', '2.5.0');
define('MFB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MFB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MFB_PLUGIN_BASENAME', plugin_basename(__FILE__));

class MFB_Plugin {

    private static $instance = null;
    private $settings = array();
    private $rendered = false;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_settings();
        $this->init_hooks();
    }

    private function defaults() {
        return array(
            'primary_color' => '#003F6E',
            'icon_color' => '#FFFFFF',
            'whatsapp_color' => '#25D366',
            'call_color' => '#1DDA50',
            'button_size_desktop' => 58,
            'button_size_mobile' => 58,
            'messenger_size_desktop' => 58,
            'messenger_size_mobile' => 52,
            'messenger_icon_scale' => 82,
            'button_radius' => 999,
            'messenger_button_radius' => 999,
            'offset_bottom_desktop' => 28,
            'offset_right_desktop' => 28,
            'offset_bottom_mobile' => 82,
            'offset_right_mobile' => 8,
            'telegram_link' => 'https://t.me/joytop',
            'whatsapp_link' => 'https://wa.me/79839992828',
            'max_link' => 'https://max.ru/u/f9LHodD0cOJ8t-shVF3j2VkazaC22RBHsnmBzmT9plLvhyBOZ_rVAYkoyy8',
            'call_phone' => '',
            'main_icon' => '',
            'telegram_icon' => '',
            'whatsapp_icon' => '',
            'max_icon' => '',
            'call_icon' => '',
            'tooltip_text' => array(
                'ru' => 'Написать в мессенджер',
                'en' => 'Write to messenger',
                'es' => 'Escribir al messenger'
            ),
            'buttons_order' => array('call', 'max', 'telegram', 'whatsapp'),
            'show_on_mobile' => 1,
            'display_type' => 'auto'
        );
    }

    private function allowed_buttons() {
        return array('call', 'max', 'telegram', 'whatsapp');
    }

    private function button_labels() {
        return array(
            'call' => __('Call', 'joy-messenger-floating-button'),
            'max' => 'MAX',
            'telegram' => 'Telegram',
            'whatsapp' => 'WhatsApp'
        );
    }

    private function load_settings() {
        $defaults = $this->defaults();
        $saved = get_option('mfb_settings', array());
        $this->settings = wp_parse_args($saved, $defaults);

        $this->settings['tooltip_text'] = wp_parse_args(
            isset($this->settings['tooltip_text']) && is_array($this->settings['tooltip_text']) ? $this->settings['tooltip_text'] : array(),
            $defaults['tooltip_text']
        );

        if (!isset($this->settings['buttons_order']) || !is_array($this->settings['buttons_order']) || empty($this->settings['buttons_order'])) {
            $this->settings['buttons_order'] = $defaults['buttons_order'];
        }

        $allowed_buttons = $this->allowed_buttons();
        $this->settings['buttons_order'] = array_values(array_intersect($this->settings['buttons_order'], $allowed_buttons));

        foreach ($defaults['buttons_order'] as $button) {
            if (!in_array($button, $this->settings['buttons_order'], true)) {
                $this->settings['buttons_order'][] = $button;
            }
        }
    }

    private function init_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_shortcode('messenger_button', array($this, 'shortcode_handler'));

        if ($this->settings['display_type'] !== 'shortcode') {
            add_action('wp_footer', array($this, 'display_button'), 999);
        }

        add_filter('plugin_action_links_' . MFB_PLUGIN_BASENAME, array($this, 'add_action_links'));
    }

    public function enqueue_frontend_assets() {
        if (is_admin()) {
            return;
        }

        if (wp_is_mobile() && empty($this->settings['show_on_mobile'])) {
            return;
        }

        wp_enqueue_style('mfb-style', MFB_PLUGIN_URL . 'assets/css/style.css', array(), MFB_VERSION);
        wp_enqueue_script('mfb-script', MFB_PLUGIN_URL . 'assets/js/script.js', array(), MFB_VERSION, true);

        wp_localize_script('mfb-script', 'mfb_settings', array(
            'telegram_link' => $this->settings['telegram_link'],
            'whatsapp_link' => $this->settings['whatsapp_link'],
            'max_link' => $this->settings['max_link'],
            'call_link' => $this->get_button_url('call'),
            'tooltip_text' => $this->get_tooltip_text(),
            'is_mobile' => wp_is_mobile(),
            'buttons_order' => $this->settings['buttons_order']
        ));

        wp_add_inline_style('mfb-style', $this->get_custom_css_vars(':root'));
    }

    public function enqueue_admin_assets($hook) {
        if ('toplevel_page_joy-messenger-settings' !== $hook) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('mfb-admin-style', MFB_PLUGIN_URL . 'assets/css/admin.css', array(), MFB_VERSION);
        wp_enqueue_script('mfb-admin-script', MFB_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), MFB_VERSION, true);
        wp_localize_script('mfb-admin-script', 'mfb_admin_i18n', array(
            'mediaTitle' => __('Select or upload an icon', 'joy-messenger-floating-button'),
            'mediaButton' => __('Use this icon', 'joy-messenger-floating-button'),
            'upload' => __('Upload / choose', 'joy-messenger-floating-button'),
            'clear' => __('Clear', 'joy-messenger-floating-button'),
            'defaultLabel' => __('Default', 'joy-messenger-floating-button'),
            'previewTitle' => __('Live Preview', 'joy-messenger-floating-button'),
            'defaultIcons' => array(
                'telegram' => MFB_PLUGIN_URL . 'assets/images/telegram-logo.svg',
                'whatsapp' => MFB_PLUGIN_URL . 'assets/images/whatsapp_logo.svg',
                'max' => MFB_PLUGIN_URL . 'assets/images/max-messenger-logo.svg',
                'call' => MFB_PLUGIN_URL . 'assets/images/joy-call.svg'
            ),
            'labels' => array(
                'call' => __('Call', 'joy-messenger-floating-button'),
                'max' => 'MAX',
                'telegram' => 'Telegram',
                'whatsapp' => 'WhatsApp'
            )
        ));
    }

    private function get_custom_css_vars($selector) {
        return sprintf(
            '%1$s{
                --mfb-primary-color:%2$s;
                --mfb-icon-color:%3$s;
                --mfb-whatsapp-color:%4$s;
                --mfb-call-color:%5$s;
                --mfb-button-size-desktop:%6$dpx;
                --mfb-button-size-mobile:%7$dpx;
                --mfb-messenger-size-desktop:%8$dpx;
                --mfb-messenger-size-mobile:%9$dpx;
                --mfb-messenger-icon-scale:%10$d%%;
                --mfb-button-radius:%11$dpx;
                --mfb-messenger-button-radius:%12$dpx;
                --mfb-offset-bottom-desktop:%13$dpx;
                --mfb-offset-right-desktop:%14$dpx;
                --mfb-offset-bottom-mobile:%15$dpx;
                --mfb-offset-right-mobile:%16$dpx;
            }',
            $selector,
            esc_attr($this->settings['primary_color']),
            esc_attr($this->settings['icon_color']),
            esc_attr($this->settings['whatsapp_color']),
            esc_attr($this->settings['call_color']),
            intval($this->settings['button_size_desktop']),
            intval($this->settings['button_size_mobile']),
            intval($this->settings['messenger_size_desktop']),
            intval($this->settings['messenger_size_mobile']),
            intval($this->settings['messenger_icon_scale']),
            intval($this->settings['button_radius']),
            intval($this->settings['messenger_button_radius']),
            intval($this->settings['offset_bottom_desktop']),
            intval($this->settings['offset_right_desktop']),
            intval($this->settings['offset_bottom_mobile']),
            intval($this->settings['offset_right_mobile'])
        );
    }

    private function get_tooltip_text() {
        $locale = get_locale();
        $lang = substr($locale, 0, 2);

        if (isset($this->settings['tooltip_text'][$lang])) {
            return $this->settings['tooltip_text'][$lang];
        }

        return $this->settings['tooltip_text']['en'];
    }

    private function get_icon_url($type) {
        $icon_key = $type . '_icon';
        $icon = isset($this->settings[$icon_key]) ? $this->settings[$icon_key] : '';
        if (!empty($icon)) {
            return $icon;
        }

        $default_icons = array(
            'telegram' => MFB_PLUGIN_URL . 'assets/images/telegram-logo.svg',
            'whatsapp' => MFB_PLUGIN_URL . 'assets/images/whatsapp_logo.svg',
            'max' => MFB_PLUGIN_URL . 'assets/images/max-messenger-logo.svg',
            'call' => MFB_PLUGIN_URL . 'assets/images/joy-call.svg'
        );

        return isset($default_icons[$type]) ? $default_icons[$type] : '';
    }

    private function normalize_phone($phone) {
        $phone = trim((string) $phone);
        if ('' === $phone) {
            return '';
        }

        if (0 === strpos($phone, 'tel:')) {
            $phone = substr($phone, 4);
        }

        $phone = preg_replace('/[^0-9+*#,;]/', '', $phone);
        return $phone ? 'tel:' . $phone : '';
    }

    private function get_button_url($type) {
        if ('call' === $type) {
            return $this->normalize_phone(isset($this->settings['call_phone']) ? $this->settings['call_phone'] : '');
        }

        $key = $type . '_link';
        return isset($this->settings[$key]) ? $this->settings[$key] : '';
    }

    public function display_button() {
        if ($this->rendered) {
            return;
        }

        $allowed_html = array(
            'div' => array('class' => true, 'data-mfb' => true),
            'button' => array('type' => true, 'class' => true, 'aria-label' => true, 'aria-expanded' => true, 'data-link' => true, 'data-type' => true),
            'img' => array('src' => true, 'alt' => true, 'loading' => true, 'decoding' => true, 'class' => true),
            'svg' => array('class' => true, 'viewBox' => true, 'viewbox' => true, 'xmlns' => true, 'aria-hidden' => true, 'focusable' => true),
            'path' => array('d' => true)
        );

        echo wp_kses($this->render_button(), $allowed_html);
        $this->rendered = true;
    }

    public function shortcode_handler($atts) {
        $this->rendered = true;
        return $this->render_button();
    }

    public function render_button() {
        $labels = $this->button_labels();
        ob_start();
        ?>
        <div class="mfb-container" data-mfb="true">
            <div class="mfb-buttons-container">
                <?php foreach ($this->settings['buttons_order'] as $type): ?>
                    <?php
                    $link = $this->get_button_url($type);
                    if (empty($link)) { continue; }
                    ?>
                    <button type="button" class="mfb-btn mfb-<?php echo esc_attr($type); ?>"
                            aria-label="<?php echo esc_attr(isset($labels[$type]) ? $labels[$type] : ucfirst($type)); ?>"
                            data-type="<?php echo esc_attr($type); ?>"
                            data-link="<?php echo esc_url($link); ?>">
                        <img src="<?php echo esc_url($this->get_icon_url($type)); ?>"
                             alt="<?php echo esc_attr(isset($labels[$type]) ? $labels[$type] : $type); ?>"
                             loading="lazy"
                             decoding="async">
                    </button>
                <?php endforeach; ?>
            </div>

            <button type="button" class="mfb-main-btn" aria-label="<?php echo esc_attr__('Open messengers', 'joy-messenger-floating-button'); ?>" aria-expanded="false">
                <div class="mfb-pulse"></div>
                <?php if (!empty($this->settings['main_icon'])): ?>
                    <img class="mfb-main-custom-icon" src="<?php echo esc_url($this->settings['main_icon']); ?>" alt="" loading="lazy" decoding="async">
                <?php else: ?>
                    <svg class="mfb-icon mfb-icon-comment" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M20 2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h14l4 4V4c0-1.1-.9-2-2-2z"/>
                    </svg>
                <?php endif; ?>
                <svg class="mfb-icon mfb-icon-close" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                </svg>
                <div class="mfb-tooltip"><?php echo esc_html($this->get_tooltip_text()); ?></div>
            </button>
        </div>
        <?php
        return ob_get_clean();
    }

    public function add_admin_menu() {
        add_menu_page(
            __('Joy Messenger Button', 'joy-messenger-floating-button'),
            __('Joy Messenger Button', 'joy-messenger-floating-button'),
            'manage_options',
            'joy-messenger-settings',
            array($this, 'render_settings_page'),
            'dashicons-format-chat',
            100
        );
    }

    public function render_settings_page() {
        ?>
        <div class="wrap mfb-admin-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <div class="mfb-settings-container">
                <div class="mfb-settings-panel">
                    <form method="post" action="options.php" id="mfb-settings-form">
                        <?php
                        settings_fields('mfb_settings_group');
                        do_settings_sections('joy-messenger-settings');
                        submit_button(__('Save Changes', 'joy-messenger-floating-button'));
                        ?>
                    </form>
                </div>
                <?php $this->render_admin_preview(); ?>
            </div>
        </div>
        <?php
    }

    private function render_admin_preview() {
        $labels = $this->button_labels();
        $preview_css = $this->get_custom_css_vars('.mfb-admin-preview-card');
        ?>
        <div class="mfb-admin-preview-card">
            <style><?php echo esc_html($preview_css); ?></style>
            <div class="mfb-admin-preview-head">
                <h2><?php esc_html_e('Live Preview', 'joy-messenger-floating-button'); ?></h2>
                <p><?php esc_html_e('Changes are shown here instantly before saving.', 'joy-messenger-floating-button'); ?></p>
            </div>
            <div class="mfb-admin-preview-stage">
                <div class="mfb-container mfb-preview-container mfb-open" data-mfb-preview="true">
                    <div class="mfb-buttons-container active" data-preview-buttons>
                        <?php foreach ($this->settings['buttons_order'] as $type): ?>
                            <?php if (!in_array($type, $this->allowed_buttons(), true)) { continue; } ?>
                            <?php $is_hidden = empty($this->get_button_url($type)); ?>
                            <button type="button" class="mfb-btn mfb-<?php echo esc_attr($type); ?> <?php echo $is_hidden ? 'is-preview-hidden' : ''; ?>" data-preview-type="<?php echo esc_attr($type); ?>" aria-label="<?php echo esc_attr(isset($labels[$type]) ? $labels[$type] : $type); ?>">
                                <img src="<?php echo esc_url($this->get_icon_url($type)); ?>" alt="<?php echo esc_attr(isset($labels[$type]) ? $labels[$type] : $type); ?>">
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="mfb-main-btn active" aria-label="<?php echo esc_attr__('Open messengers', 'joy-messenger-floating-button'); ?>" aria-expanded="true">
                        <div class="mfb-pulse"></div>
                        <svg class="mfb-icon mfb-icon-comment" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M20 2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h14l4 4V4c0-1.1-.9-2-2-2z"/>
                        </svg>
                        <img class="mfb-main-custom-icon <?php echo empty($this->settings['main_icon']) ? 'is-preview-hidden' : ''; ?>" src="<?php echo esc_url($this->settings['main_icon']); ?>" alt="">
                        <svg class="mfb-icon mfb-icon-close" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                        </svg>
                        <div class="mfb-tooltip"><?php echo esc_html($this->get_tooltip_text()); ?></div>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    public function register_settings() {
        register_setting('mfb_settings_group', 'mfb_settings', array($this, 'sanitize_settings'));

        add_settings_section('mfb_main_section', __('General Settings', 'joy-messenger-floating-button'), null, 'joy-messenger-settings');
        add_settings_section('mfb_appearance_section', __('Appearance', 'joy-messenger-floating-button'), null, 'joy-messenger-settings');
        add_settings_section('mfb_icons_section', __('Icons', 'joy-messenger-floating-button'), null, 'joy-messenger-settings');
        add_settings_section('mfb_links_section', __('Messenger Links', 'joy-messenger-floating-button'), null, 'joy-messenger-settings');
        add_settings_section('mfb_order_section', __('Buttons Order', 'joy-messenger-floating-button'), null, 'joy-messenger-settings');
        add_settings_section('mfb_text_section', __('Tooltip Text', 'joy-messenger-floating-button'), null, 'joy-messenger-settings');
        add_settings_section('mfb_advanced_section', __('Advanced', 'joy-messenger-floating-button'), null, 'joy-messenger-settings');

        $this->add_settings_fields();
    }

    private function add_settings_fields() {
        add_settings_field('primary_color', __('Button Color', 'joy-messenger-floating-button'), array($this, 'render_color_field'), 'joy-messenger-settings', 'mfb_main_section', array('name' => 'primary_color'));
        add_settings_field('icon_color', __('Default Chat Icon Color', 'joy-messenger-floating-button'), array($this, 'render_color_field'), 'joy-messenger-settings', 'mfb_main_section', array('name' => 'icon_color'));
        add_settings_field('whatsapp_color', __('WhatsApp Color', 'joy-messenger-floating-button'), array($this, 'render_color_field'), 'joy-messenger-settings', 'mfb_main_section', array('name' => 'whatsapp_color'));
        add_settings_field('call_color', __('Call Button Color', 'joy-messenger-floating-button'), array($this, 'render_color_field'), 'joy-messenger-settings', 'mfb_main_section', array('name' => 'call_color'));

        add_settings_field('button_size_desktop', __('Main Button Size (Desktop)', 'joy-messenger-floating-button'), array($this, 'render_number_field'), 'joy-messenger-settings', 'mfb_appearance_section', array('name' => 'button_size_desktop', 'min' => 30, 'max' => 120, 'unit' => 'px'));
        add_settings_field('button_size_mobile', __('Main Button Size (Mobile)', 'joy-messenger-floating-button'), array($this, 'render_number_field'), 'joy-messenger-settings', 'mfb_appearance_section', array('name' => 'button_size_mobile', 'min' => 30, 'max' => 120, 'unit' => 'px'));
        add_settings_field('button_radius', __('Main Button Border Radius', 'joy-messenger-floating-button'), array($this, 'render_number_field'), 'joy-messenger-settings', 'mfb_appearance_section', array('name' => 'button_radius', 'min' => 0, 'max' => 999, 'unit' => 'px'));
        add_settings_field('messenger_size_desktop', __('Messenger Button Size (Desktop)', 'joy-messenger-floating-button'), array($this, 'render_number_field'), 'joy-messenger-settings', 'mfb_appearance_section', array('name' => 'messenger_size_desktop', 'min' => 30, 'max' => 120, 'unit' => 'px'));
        add_settings_field('messenger_size_mobile', __('Messenger Button Size (Mobile)', 'joy-messenger-floating-button'), array($this, 'render_number_field'), 'joy-messenger-settings', 'mfb_appearance_section', array('name' => 'messenger_size_mobile', 'min' => 30, 'max' => 120, 'unit' => 'px'));
        add_settings_field('messenger_button_radius', __('Messenger Button Border Radius', 'joy-messenger-floating-button'), array($this, 'render_number_field'), 'joy-messenger-settings', 'mfb_appearance_section', array('name' => 'messenger_button_radius', 'min' => 0, 'max' => 999, 'unit' => 'px'));
        add_settings_field('messenger_icon_scale', __('Messenger Icon Scale', 'joy-messenger-floating-button'), array($this, 'render_number_field'), 'joy-messenger-settings', 'mfb_appearance_section', array('name' => 'messenger_icon_scale', 'min' => 40, 'max' => 110, 'unit' => '%'));
        add_settings_field('offset_bottom_desktop', __('Bottom Offset (Desktop)', 'joy-messenger-floating-button'), array($this, 'render_number_field'), 'joy-messenger-settings', 'mfb_appearance_section', array('name' => 'offset_bottom_desktop', 'min' => 0, 'max' => 300, 'unit' => 'px'));
        add_settings_field('offset_right_desktop', __('Right Offset (Desktop)', 'joy-messenger-floating-button'), array($this, 'render_number_field'), 'joy-messenger-settings', 'mfb_appearance_section', array('name' => 'offset_right_desktop', 'min' => 0, 'max' => 300, 'unit' => 'px'));
        add_settings_field('offset_bottom_mobile', __('Bottom Offset (Mobile)', 'joy-messenger-floating-button'), array($this, 'render_number_field'), 'joy-messenger-settings', 'mfb_appearance_section', array('name' => 'offset_bottom_mobile', 'min' => 0, 'max' => 300, 'unit' => 'px'));
        add_settings_field('offset_right_mobile', __('Right Offset (Mobile)', 'joy-messenger-floating-button'), array($this, 'render_number_field'), 'joy-messenger-settings', 'mfb_appearance_section', array('name' => 'offset_right_mobile', 'min' => 0, 'max' => 300, 'unit' => 'px'));

        add_settings_field('main_icon', __('Main Chat Icon', 'joy-messenger-floating-button'), array($this, 'render_image_field'), 'joy-messenger-settings', 'mfb_icons_section', array('name' => 'main_icon'));
        add_settings_field('call_icon', __('Call Icon', 'joy-messenger-floating-button'), array($this, 'render_image_field'), 'joy-messenger-settings', 'mfb_icons_section', array('name' => 'call_icon'));
        add_settings_field('telegram_icon', __('Telegram Icon', 'joy-messenger-floating-button'), array($this, 'render_image_field'), 'joy-messenger-settings', 'mfb_icons_section', array('name' => 'telegram_icon'));
        add_settings_field('whatsapp_icon', __('WhatsApp Icon', 'joy-messenger-floating-button'), array($this, 'render_image_field'), 'joy-messenger-settings', 'mfb_icons_section', array('name' => 'whatsapp_icon'));
        add_settings_field('max_icon', __('MAX Icon', 'joy-messenger-floating-button'), array($this, 'render_image_field'), 'joy-messenger-settings', 'mfb_icons_section', array('name' => 'max_icon'));

        add_settings_field('call_phone', __('Phone Number', 'joy-messenger-floating-button'), array($this, 'render_phone_field'), 'joy-messenger-settings', 'mfb_links_section', array('name' => 'call_phone'));
        add_settings_field('telegram_link', __('Telegram Link', 'joy-messenger-floating-button'), array($this, 'render_url_field'), 'joy-messenger-settings', 'mfb_links_section', array('name' => 'telegram_link'));
        add_settings_field('whatsapp_link', __('WhatsApp Link', 'joy-messenger-floating-button'), array($this, 'render_url_field'), 'joy-messenger-settings', 'mfb_links_section', array('name' => 'whatsapp_link'));
        add_settings_field('max_link', __('MAX Link', 'joy-messenger-floating-button'), array($this, 'render_url_field'), 'joy-messenger-settings', 'mfb_links_section', array('name' => 'max_link'));

        add_settings_field('buttons_order', __('Buttons Order (top to bottom)', 'joy-messenger-floating-button'), array($this, 'render_order_field'), 'joy-messenger-settings', 'mfb_order_section', array('name' => 'buttons_order'));

        add_settings_field('tooltip_text_ru', __('Tooltip Text (Russian)', 'joy-messenger-floating-button'), array($this, 'render_text_field'), 'joy-messenger-settings', 'mfb_text_section', array('name' => 'tooltip_text_ru'));
        add_settings_field('tooltip_text_en', __('Tooltip Text (English)', 'joy-messenger-floating-button'), array($this, 'render_text_field'), 'joy-messenger-settings', 'mfb_text_section', array('name' => 'tooltip_text_en'));
        add_settings_field('tooltip_text_es', __('Tooltip Text (Spanish)', 'joy-messenger-floating-button'), array($this, 'render_text_field'), 'joy-messenger-settings', 'mfb_text_section', array('name' => 'tooltip_text_es'));

        add_settings_field('show_on_mobile', __('Show on Mobile Devices', 'joy-messenger-floating-button'), array($this, 'render_checkbox_field'), 'joy-messenger-settings', 'mfb_advanced_section', array('name' => 'show_on_mobile'));
        add_settings_field('display_type', __('Display Type', 'joy-messenger-floating-button'), array($this, 'render_select_field'), 'joy-messenger-settings', 'mfb_advanced_section', array(
            'name' => 'display_type',
            'options' => array(
                'auto' => __('Auto in footer', 'joy-messenger-floating-button'),
                'shortcode' => __('Shortcode only [messenger_button]', 'joy-messenger-floating-button'),
                'both' => __('Both', 'joy-messenger-floating-button')
            )
        ));
    }

    public function render_color_field($args) {
        $value = isset($this->settings[$args['name']]) ? $this->settings[$args['name']] : '';
        echo '<input type="color" name="mfb_settings[' . esc_attr($args['name']) . ']" value="' . esc_attr($value) . '" class="mfb-color-field" data-mfb-setting="' . esc_attr($args['name']) . '">';
    }

    public function render_number_field($args) {
        $value = isset($this->settings[$args['name']]) ? $this->settings[$args['name']] : 40;
        $unit = isset($args['unit']) ? $args['unit'] : 'px';
        echo '<input type="number" name="mfb_settings[' . esc_attr($args['name']) . ']" value="' . esc_attr($value) . '" min="' . esc_attr($args['min']) . '" max="' . esc_attr($args['max']) . '" class="small-text" data-mfb-setting="' . esc_attr($args['name']) . '"> ' . esc_html($unit);
    }

    public function render_url_field($args) {
        $value = isset($this->settings[$args['name']]) ? $this->settings[$args['name']] : '';
        echo '<input type="url" name="mfb_settings[' . esc_attr($args['name']) . ']" value="' . esc_url($value) . '" class="regular-text" data-mfb-setting="' . esc_attr($args['name']) . '" placeholder="https://...">';
        echo '<p class="description">' . esc_html__('For WhatsApp use format: https://wa.me/79XXXXXXXXX. Leave link empty to hide this messenger.', 'joy-messenger-floating-button') . '</p>';
    }

    public function render_phone_field($args) {
        $value = isset($this->settings[$args['name']]) ? $this->settings[$args['name']] : '';
        echo '<input type="text" name="mfb_settings[' . esc_attr($args['name']) . ']" value="' . esc_attr($value) . '" class="regular-text" data-mfb-setting="' . esc_attr($args['name']) . '" placeholder="+7 999 999-99-99">';
        echo '<p class="description">' . esc_html__('Enter a phone number. The button will open a tel: link. Leave empty to hide the call button.', 'joy-messenger-floating-button') . '</p>';
    }

    public function render_text_field($args) {
        $lang = str_replace('tooltip_text_', '', $args['name']);
        $value = isset($this->settings['tooltip_text'][$lang]) ? $this->settings['tooltip_text'][$lang] : '';
        echo '<input type="text" name="mfb_settings[tooltip_text][' . esc_attr($lang) . ']" value="' . esc_attr($value) . '" class="regular-text" data-mfb-setting="tooltip_text_' . esc_attr($lang) . '">';
    }

    public function render_checkbox_field($args) {
        $value = isset($this->settings[$args['name']]) ? $this->settings[$args['name']] : 1;
        echo '<label><input type="checkbox" name="mfb_settings[' . esc_attr($args['name']) . ']" value="1" ' . checked(1, $value, false) . '> ' . esc_html__('Enable display on phones and tablets', 'joy-messenger-floating-button') . '</label>';
    }

    public function render_select_field($args) {
        $value = isset($this->settings[$args['name']]) ? $this->settings[$args['name']] : 'auto';
        echo '<select name="mfb_settings[' . esc_attr($args['name']) . ']">';
        foreach ($args['options'] as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($value, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }

    public function render_image_field($args) {
        $value = isset($this->settings[$args['name']]) ? $this->settings[$args['name']] : '';
        $preview = $value;
        if (empty($preview) && 'main_icon' !== $args['name']) {
            $preview = $this->get_icon_url(str_replace('_icon', '', $args['name']));
        }
        ?>
        <div class="mfb-image-upload" data-icon-field="<?php echo esc_attr($args['name']); ?>">
            <input type="url"
                   name="mfb_settings[<?php echo esc_attr($args['name']); ?>]"
                   value="<?php echo esc_url($value); ?>"
                   class="regular-text mfb-image-url"
                   data-mfb-setting="<?php echo esc_attr($args['name']); ?>"
                   placeholder="https:// or /wp-content/uploads/...">
            <button type="button" class="button mfb-upload-button"><?php esc_html_e('Upload / choose', 'joy-messenger-floating-button'); ?></button>
            <button type="button" class="button mfb-clear-button"><?php esc_html_e('Clear', 'joy-messenger-floating-button'); ?></button>
            <div class="mfb-icon-preview <?php echo empty($preview) ? 'is-empty' : ''; ?>">
                <?php if (!empty($preview)): ?>
                    <img src="<?php echo esc_url($preview); ?>" alt="preview">
                <?php else: ?>
                    <span><?php esc_html_e('Default', 'joy-messenger-floating-button'); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <p class="description"><?php esc_html_e('Upload an SVG, PNG or WebP icon from the WordPress media library. Leave empty to use the default icon.', 'joy-messenger-floating-button'); ?></p>
        <?php
    }

    public function render_order_field($args) {
        $order = $this->settings['buttons_order'];
        $buttons = $this->button_labels();
        ?>
        <div class="order-list">
            <?php foreach ($order as $key): ?>
                <?php if (!isset($buttons[$key])) { continue; } ?>
                <label>
                    <input type="checkbox" name="mfb_settings[buttons_order][]" value="<?php echo esc_attr($key); ?>" checked="checked" data-mfb-order="<?php echo esc_attr($key); ?>">
                    <?php echo esc_html($buttons[$key]); ?>
                </label>
            <?php endforeach; ?>
            <?php foreach ($buttons as $key => $label): ?>
                <?php if (!in_array($key, $order, true)): ?>
                    <label>
                        <input type="checkbox" name="mfb_settings[buttons_order][]" value="<?php echo esc_attr($key); ?>" data-mfb-order="<?php echo esc_attr($key); ?>">
                        <?php echo esc_html($label); ?>
                    </label>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <p class="description"><?php esc_html_e('Select buttons. The visual order matches the order from top to bottom.', 'joy-messenger-floating-button'); ?></p>
        <?php
    }

    public function sanitize_settings($input) {
        $input = is_array($input) ? $input : array();
        $defaults = $this->defaults();
        $sanitized = array();

        foreach (array('primary_color', 'icon_color', 'whatsapp_color', 'call_color') as $field) {
            $sanitized[$field] = !empty($input[$field]) ? sanitize_hex_color($input[$field]) : $defaults[$field];
            if (empty($sanitized[$field])) {
                $sanitized[$field] = $defaults[$field];
            }
        }

        $number_fields = array(
            'button_size_desktop' => array(30, 120),
            'button_size_mobile' => array(30, 120),
            'messenger_size_desktop' => array(30, 120),
            'messenger_size_mobile' => array(30, 120),
            'messenger_icon_scale' => array(40, 110),
            'button_radius' => array(0, 999),
            'messenger_button_radius' => array(0, 999),
            'offset_bottom_desktop' => array(0, 300),
            'offset_right_desktop' => array(0, 300),
            'offset_bottom_mobile' => array(0, 300),
            'offset_right_mobile' => array(0, 300)
        );

        foreach ($number_fields as $field => $limits) {
            $value = isset($input[$field]) ? absint($input[$field]) : $defaults[$field];
            $sanitized[$field] = min(max($value, $limits[0]), $limits[1]);
        }

        foreach (array('telegram_link', 'whatsapp_link', 'max_link') as $field) {
            $sanitized[$field] = isset($input[$field]) ? esc_url_raw($input[$field]) : $defaults[$field];
        }

        $sanitized['call_phone'] = isset($input['call_phone']) ? sanitize_text_field($input['call_phone']) : '';

        foreach (array('main_icon', 'telegram_icon', 'whatsapp_icon', 'max_icon', 'call_icon') as $field) {
            $sanitized[$field] = isset($input[$field]) ? esc_url_raw($input[$field]) : '';
        }

        $sanitized['show_on_mobile'] = isset($input['show_on_mobile']) ? 1 : 0;
        $sanitized['display_type'] = isset($input['display_type']) && in_array($input['display_type'], array('auto', 'shortcode', 'both'), true) ? $input['display_type'] : 'auto';

        $allowed_buttons = $this->allowed_buttons();
        $sanitized['buttons_order'] = array();
        if (isset($input['buttons_order']) && is_array($input['buttons_order'])) {
            foreach ($input['buttons_order'] as $button) {
                $button = sanitize_key($button);
                if (in_array($button, $allowed_buttons, true) && !in_array($button, $sanitized['buttons_order'], true)) {
                    $sanitized['buttons_order'][] = $button;
                }
            }
        }
        if (empty($sanitized['buttons_order'])) {
            $sanitized['buttons_order'] = $defaults['buttons_order'];
        }

        $sanitized['tooltip_text'] = array();
        if (isset($input['tooltip_text']) && is_array($input['tooltip_text'])) {
            foreach (array('ru', 'en', 'es') as $lang) {
                $sanitized['tooltip_text'][$lang] = isset($input['tooltip_text'][$lang]) ? sanitize_text_field($input['tooltip_text'][$lang]) : $defaults['tooltip_text'][$lang];
                if ('' === $sanitized['tooltip_text'][$lang]) {
                    $sanitized['tooltip_text'][$lang] = $defaults['tooltip_text'][$lang];
                }
            }
        } else {
            $sanitized['tooltip_text'] = $defaults['tooltip_text'];
        }

        return $sanitized;
    }

    public function add_action_links($links) {
        $settings_link = '<a href="admin.php?page=joy-messenger-settings">' . esc_html__('Settings', 'joy-messenger-floating-button') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

MFB_Plugin::get_instance();