<?php

/**
 * Adbirt Publisher: places Adbirt ad slots on the site and loads the Adbirt
 * publisher script (https://dsp.adbirt.com/publisher.js), which fills them.
 *
 * Sites and slots are created in the Adbirt dashboard (Publisher section);
 * each slot has a public id such as slot_0123456789abcdef01234567. Ads are
 * served only on the verified domain, so nothing here is secret.
 *
 * @package adbirt-ads-display
 */

if (!defined('ABSPATH')) {
    exit;
}

class Adbirt_Publisher
{
    const OPTION = 'adbirt_publisher_settings';
    const DEFAULT_SCRIPT_URL = 'https://dsp.adbirt.com/publisher.js';
    const SCRIPT_HANDLE = 'adbirt-publisher';
    const SLOT_PATTERN = '/^slot_[a-f0-9]{24}$/';

    /** @var Adbirt_Publisher|null */
    private static $instance = null;

    /** @var bool */
    private $script_needed = false;

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_shortcode('adbirt_slot', array($this, 'slot_shortcode'));
        // The 1.x shortcode called adbirt.com endpoints that no longer exist.
        // Keep it registered so old posts do not show raw shortcode text.
        add_shortcode('adbirt_ads_display', array($this, 'legacy_shortcode'));
        add_action('init', array($this, 'register_block'));
        add_action('widgets_init', array($this, 'register_widget'));
        add_action('wp_enqueue_scripts', array($this, 'register_script'));
        add_action('wp_footer', array($this, 'enqueue_script_if_needed'), 1);
        add_filter('script_loader_tag', array($this, 'script_tag_attributes'), 10, 2);
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_filter('plugin_action_links_' . plugin_basename(ADBIRT_PUBLISHER_FILE), array($this, 'settings_link'));
    }

    /** @return array{site_id:string,script_url:string,api_base:string} */
    public static function settings()
    {
        $saved = get_option(self::OPTION, array());
        $saved = is_array($saved) ? $saved : array();
        return array(
            'site_id' => isset($saved['site_id']) ? (string) $saved['site_id'] : '',
            'script_url' => !empty($saved['script_url']) ? (string) $saved['script_url'] : self::DEFAULT_SCRIPT_URL,
            'api_base' => isset($saved['api_base']) ? (string) $saved['api_base'] : '',
        );
    }

    public static function is_slot_id($value)
    {
        return is_string($value) && preg_match(self::SLOT_PATTERN, $value) === 1;
    }

    /**
     * Markup for one slot. The publisher script finds it, asks Adbirt for an
     * ad when it nears the viewport, and collapses it when there is none.
     */
    public function slot_markup($slot_id, $extra_class = '')
    {
        $slot_id = trim((string) $slot_id);
        if (!self::is_slot_id($slot_id)) {
            if (current_user_can('edit_posts')) {
                return '<!-- Adbirt: "' . esc_html($slot_id) . '" is not a slot id. Copy it from the Publisher section of your Adbirt dashboard. -->';
            }
            return '';
        }
        $this->script_needed = true;
        $classes = trim('adbirt-slot ' . $extra_class);
        return sprintf(
            '<ins class="%s" data-slot="%s" style="display:block"></ins>',
            esc_attr($classes),
            esc_attr($slot_id)
        );
    }

    /** [adbirt_slot id="slot_..."] */
    public function slot_shortcode($attributes = array())
    {
        $attributes = shortcode_atts(array('id' => '', 'class' => ''), $attributes, 'adbirt_slot');
        return $this->slot_markup($attributes['id'], sanitize_html_class($attributes['class']));
    }

    public function legacy_shortcode()
    {
        if (current_user_can('edit_posts')) {
            return '<!-- Adbirt: [adbirt_ads_display] is retired. Replace it with [adbirt_slot id="..."] using a slot id from your Adbirt dashboard. -->';
        }
        return '';
    }

    public function register_block()
    {
        if (!function_exists('register_block_type')) {
            return;
        }
        wp_register_script(
            'adbirt-slot-block-editor',
            plugins_url('blocks/slot/editor.js', ADBIRT_PUBLISHER_FILE),
            array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'),
            ADBIRT_PUBLISHER_VERSION,
            true
        );
        register_block_type(ADBIRT_PUBLISHER_DIR . 'blocks/slot', array(
            'editor_script' => 'adbirt-slot-block-editor',
            'render_callback' => array($this, 'render_block'),
        ));
    }

    public function render_block($attributes)
    {
        $slot_id = isset($attributes['slotId']) ? $attributes['slotId'] : '';
        return $this->slot_markup($slot_id);
    }

    public function register_widget()
    {
        register_widget('AAD_widget');
    }

    public function register_script()
    {
        $settings = self::settings();
        wp_register_script(self::SCRIPT_HANDLE, esc_url_raw($settings['script_url']), array(), null, true);
    }

    /** The script loads once per page, and only on pages that have a slot. */
    public function enqueue_script_if_needed()
    {
        if ($this->script_needed) {
            wp_enqueue_script(self::SCRIPT_HANDLE);
        }
    }

    public function script_tag_attributes($tag, $handle)
    {
        if ($handle !== self::SCRIPT_HANDLE) {
            return $tag;
        }
        $settings = self::settings();
        $extra = ' async';
        if ($settings['api_base'] !== '') {
            $extra .= ' data-api="' . esc_attr($settings['api_base']) . '"';
        }
        return preg_replace('/<script\b/', '<script' . $extra, $tag, 1);
    }

    public function admin_menu()
    {
        add_options_page(
            __('Adbirt Publisher', 'adbirt-ads-display'),
            __('Adbirt Publisher', 'adbirt-ads-display'),
            'manage_options',
            'adbirt-publisher',
            array($this, 'settings_page')
        );
    }

    public function register_settings()
    {
        register_setting('adbirt_publisher', self::OPTION, array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_settings'),
            'default' => array(),
        ));
    }

    public function sanitize_settings($input)
    {
        $input = is_array($input) ? $input : array();
        $clean = array();
        $site_id = isset($input['site_id']) ? sanitize_text_field($input['site_id']) : '';
        $clean['site_id'] = preg_match('/^[a-f0-9]{24}$/', $site_id) ? $site_id : '';
        if ($site_id !== '' && $clean['site_id'] === '') {
            add_settings_error(self::OPTION, 'site_id', __('The site id is the 24-character id shown for your site in the Adbirt dashboard.', 'adbirt-ads-display'));
        }
        $script_url = isset($input['script_url']) ? esc_url_raw(trim($input['script_url']), array('https', 'http')) : '';
        $clean['script_url'] = $script_url !== '' ? $script_url : self::DEFAULT_SCRIPT_URL;
        $api_base = isset($input['api_base']) ? esc_url_raw(trim($input['api_base']), array('https', 'http')) : '';
        $clean['api_base'] = untrailingslashit($api_base);
        return $clean;
    }

    public function settings_link($links)
    {
        $url = admin_url('options-general.php?page=adbirt-publisher');
        array_unshift($links, '<a href="' . esc_url($url) . '">' . esc_html__('Settings', 'adbirt-ads-display') . '</a>');
        return $links;
    }

    public function settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $settings = self::settings();
        $name = esc_attr(self::OPTION);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Adbirt Publisher', 'adbirt-ads-display'); ?></h1>
            <p><?php esc_html_e('Show Adbirt ads on this site. In your Adbirt dashboard, open Publisher, add and verify this site, then create ad slots.', 'adbirt-ads-display'); ?></p>
            <ol>
                <li><?php esc_html_e('Add a slot anywhere with the shortcode', 'adbirt-ads-display'); ?> <code>[adbirt_slot id="slot_..."]</code></li>
                <li><?php esc_html_e('or the "Adbirt Ad Slot" block in the editor,', 'adbirt-ads-display'); ?></li>
                <li><?php esc_html_e('or the "Adbirt Ad Slot" widget in Appearance > Widgets.', 'adbirt-ads-display'); ?></li>
            </ol>
            <form method="post" action="options.php">
                <?php settings_fields('adbirt_publisher'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="adbirt-site-id"><?php esc_html_e('Publisher site id', 'adbirt-ads-display'); ?></label></th>
                        <td>
                            <input name="<?php echo $name; ?>[site_id]" id="adbirt-site-id" type="text" class="regular-text code" value="<?php echo esc_attr($settings['site_id']); ?>" pattern="[a-f0-9]{24}" aria-describedby="adbirt-site-id-help">
                            <p class="description" id="adbirt-site-id-help"><?php esc_html_e('Optional. The id of this site in your Adbirt dashboard, for your reference. Ads are served by slot id.', 'adbirt-ads-display'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="adbirt-script-url"><?php esc_html_e('Script URL', 'adbirt-ads-display'); ?></label></th>
                        <td>
                            <input name="<?php echo $name; ?>[script_url]" id="adbirt-script-url" type="url" class="regular-text code" value="<?php echo esc_attr($settings['script_url']); ?>" aria-describedby="adbirt-script-url-help">
                            <p class="description" id="adbirt-script-url-help"><?php echo esc_html(sprintf(__('Leave as %s unless Adbirt support asks you to change it.', 'adbirt-ads-display'), self::DEFAULT_SCRIPT_URL)); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="adbirt-api-base"><?php esc_html_e('Ad server (advanced)', 'adbirt-ads-display'); ?></label></th>
                        <td>
                            <input name="<?php echo $name; ?>[api_base]" id="adbirt-api-base" type="url" class="regular-text code" value="<?php echo esc_attr($settings['api_base']); ?>" aria-describedby="adbirt-api-base-help">
                            <p class="description" id="adbirt-api-base-help"><?php esc_html_e('Empty: the server the script is loaded from. Only for testing.', 'adbirt-ads-display'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
