<?php
/**
 * Plugin Name: GuideOS Language Modal
 * Plugin URI: https://guideos.de
 * Description: Displays a modal for non-German visitors with customizable content to inform them about GuideOS
 * Version: 1.0.0
 * Author: Samuel Rüegger
 * Author URI: https://rueegger.me
 * License: GPL v2 or later
 * Text Domain: guideos-language-modal
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('GOLM_VERSION', '1.0.0');
define('GOLM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GOLM_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main Plugin Class
 */
class GuideOS_Language_Modal {

    /**
     * Constructor
     */
    public function __construct() {
        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));

        // Frontend hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('wp_footer', array($this, 'render_modal'));
    }

    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        add_options_page(
            __('Language Modal Settings', 'guideos-language-modal'),
            __('Language Modal', 'guideos-language-modal'),
            'manage_options',
            'guideos-language-modal',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('golm_settings_group', 'golm_enabled');
        register_setting('golm_settings_group', 'golm_modal_title');
        register_setting('golm_settings_group', 'golm_modal_content');
        register_setting('golm_settings_group', 'golm_cta_enabled');
        register_setting('golm_settings_group', 'golm_cta_text');
        register_setting('golm_settings_group', 'golm_cta_url');
        register_setting('golm_settings_group', 'golm_close_button_text');

        add_settings_section(
            'golm_main_section',
            __('Modal Configuration', 'guideos-language-modal'),
            array($this, 'section_callback'),
            'guideos-language-modal'
        );

        // Enable/Disable Field
        add_settings_field(
            'golm_enabled',
            __('Enable Modal', 'guideos-language-modal'),
            array($this, 'render_enabled_field'),
            'guideos-language-modal',
            'golm_main_section'
        );

        // Modal Title Field
        add_settings_field(
            'golm_modal_title',
            __('Modal Title', 'guideos-language-modal'),
            array($this, 'render_title_field'),
            'guideos-language-modal',
            'golm_main_section'
        );

        // Modal Content Field
        add_settings_field(
            'golm_modal_content',
            __('Modal Content', 'guideos-language-modal'),
            array($this, 'render_content_field'),
            'guideos-language-modal',
            'golm_main_section'
        );

        // Close Button Text
        add_settings_field(
            'golm_close_button_text',
            __('Close Button Text', 'guideos-language-modal'),
            array($this, 'render_close_button_field'),
            'guideos-language-modal',
            'golm_main_section'
        );

        // CTA Enable
        add_settings_field(
            'golm_cta_enabled',
            __('Enable Call-to-Action Button', 'guideos-language-modal'),
            array($this, 'render_cta_enabled_field'),
            'guideos-language-modal',
            'golm_main_section'
        );

        // CTA Text Field
        add_settings_field(
            'golm_cta_text',
            __('CTA Button Text', 'guideos-language-modal'),
            array($this, 'render_cta_text_field'),
            'guideos-language-modal',
            'golm_main_section'
        );

        // CTA URL Field
        add_settings_field(
            'golm_cta_url',
            __('CTA Button URL', 'guideos-language-modal'),
            array($this, 'render_cta_url_field'),
            'guideos-language-modal',
            'golm_main_section'
        );
    }

    /**
     * Section callback
     */
    public function section_callback() {
        echo '<p>' . __('Configure the language detection modal that appears for non-German visitors.', 'guideos-language-modal') . '</p>';
    }

    /**
     * Render enabled field
     */
    public function render_enabled_field() {
        $enabled = get_option('golm_enabled', '0');
        ?>
        <label>
            <input type="checkbox" name="golm_enabled" value="1" <?php checked($enabled, '1'); ?> />
            <?php _e('Show modal to non-German visitors', 'guideos-language-modal'); ?>
        </label>
        <?php
    }

    /**
     * Render title field
     */
    public function render_title_field() {
        $title = get_option('golm_modal_title', __('Welcome to GuideOS', 'guideos-language-modal'));
        ?>
        <input type="text" name="golm_modal_title" value="<?php echo esc_attr($title); ?>" class="regular-text" />
        <?php
    }

    /**
     * Render content field with TinyMCE
     */
    public function render_content_field() {
        $content = get_option('golm_modal_content', __('This website is currently available in German only. We are working on an English version.', 'guideos-language-modal'));

        wp_editor($content, 'golm_modal_content', array(
            'textarea_name' => 'golm_modal_content',
            'media_buttons' => false,
            'textarea_rows' => 10,
            'teeny' => false,
            'tinymce' => array(
                'toolbar1' => 'formatselect,bold,italic,bullist,numlist,link,unlink',
            ),
        ));
    }

    /**
     * Render close button text field
     */
    public function render_close_button_field() {
        $text = get_option('golm_close_button_text', __('Close', 'guideos-language-modal'));
        ?>
        <input type="text" name="golm_close_button_text" value="<?php echo esc_attr($text); ?>" class="regular-text" />
        <?php
    }

    /**
     * Render CTA enabled field
     */
    public function render_cta_enabled_field() {
        $enabled = get_option('golm_cta_enabled', '0');
        ?>
        <label>
            <input type="checkbox" name="golm_cta_enabled" value="1" <?php checked($enabled, '1'); ?> />
            <?php _e('Show a call-to-action button in the modal', 'guideos-language-modal'); ?>
        </label>
        <?php
    }

    /**
     * Render CTA text field
     */
    public function render_cta_text_field() {
        $text = get_option('golm_cta_text', __('Learn More', 'guideos-language-modal'));
        ?>
        <input type="text" name="golm_cta_text" value="<?php echo esc_attr($text); ?>" class="regular-text" />
        <?php
    }

    /**
     * Render CTA URL field
     */
    public function render_cta_url_field() {
        $url = get_option('golm_cta_url', '');
        ?>
        <input type="url" name="golm_cta_url" value="<?php echo esc_attr($url); ?>" class="regular-text" placeholder="https://" />
        <?php
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('golm_settings_group');
                do_settings_sections('guideos-language-modal');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts() {
        // Only load if modal is enabled
        if (get_option('golm_enabled', '0') !== '1') {
            return;
        }

        wp_enqueue_script(
            'golm-frontend',
            GOLM_PLUGIN_URL . 'assets/js/frontend.js',
            array(),
            GOLM_VERSION,
            true
        );

        wp_enqueue_style(
            'golm-frontend',
            GOLM_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            GOLM_VERSION
        );

        // Localize script with modal data
        wp_localize_script('golm-frontend', 'golmData', array(
            'enabled' => get_option('golm_enabled', '0'),
            'title' => get_option('golm_modal_title', ''),
            'content' => get_option('golm_modal_content', ''),
            'closeText' => get_option('golm_close_button_text', __('Close', 'guideos-language-modal')),
            'ctaEnabled' => get_option('golm_cta_enabled', '0'),
            'ctaText' => get_option('golm_cta_text', ''),
            'ctaUrl' => get_option('golm_cta_url', ''),
        ));
    }

    /**
     * Render modal HTML in footer
     */
    public function render_modal() {
        // Only render if modal is enabled
        if (get_option('golm_enabled', '0') !== '1') {
            return;
        }

        $title = get_option('golm_modal_title', '');
        $content = get_option('golm_modal_content', '');
        $close_text = get_option('golm_close_button_text', __('Close', 'guideos-language-modal'));
        $cta_enabled = get_option('golm_cta_enabled', '0');
        $cta_text = get_option('golm_cta_text', '');
        $cta_url = get_option('golm_cta_url', '');
        ?>
        <div id="golm-modal-overlay" class="golm-modal-overlay" style="display: none;">
            <div class="golm-modal-content">
                <div class="golm-modal-header">
                    <h2><?php echo esc_html($title); ?></h2>
                    <button class="golm-close-button" id="golm-close-button" aria-label="<?php echo esc_attr($close_text); ?>">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="golm-modal-body">
                    <?php echo wp_kses_post(wpautop($content)); ?>
                </div>
                <div class="golm-modal-footer">
                    <?php if ($cta_enabled === '1' && !empty($cta_url)): ?>
                        <a href="<?php echo esc_url($cta_url); ?>" class="button button-primary golm-cta-button" target="_blank">
                            <?php echo esc_html($cta_text); ?>
                        </a>
                    <?php endif; ?>
                    <button class="button golm-close-footer-button" id="golm-close-footer-button">
                        <?php echo esc_html($close_text); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
}

// Initialize the plugin
new GuideOS_Language_Modal();
