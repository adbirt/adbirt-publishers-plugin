<?php

/**
 * @package adbirt-ads-display
 * @version 1.2.0
 */

// require the widget class before anything else
$widget_file_path = trailingslashit(plugin_dir_path(__FILE__)) . 'includes/class-AAD_widget.php';
require $widget_file_path;

/**
 * The main class for handling the
 * creation and insertion of adbirt ads.
 */
class Adbirt_Publisher
{
    public function __construct()
    {
        add_action('rest_api_init', array($this, 'register_custom_REST_routes'));
        add_action('widgets_init', array($this, 'load_widget'));
        add_action('admin_menu', array($this, 'admin_sidebar_item'));

        add_filter('plugin_action_links', array($this, 'settings_hook'), 10, 1);
        add_filter('wp_enqueue_scripts', array($this, 'register_css_and_js'));

        add_shortcode('adbirt_ads_display', array($this, 'adbirt_ads_display_shortcode'));

        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }


    /**
     * Generates a unique random alpha-numeric string.
     * The string is always unique because it also includes the current unix timestamp 
     * ```php
     * time()
     * ```
     */
    public function generate_random_string($length = 5)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);

        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }

        return $randomString . '_' . time();
    }

    /**
     * Loads the widget
     */
    public function load_widget()
    {
        return register_widget('AAD_widget');
    }

    public function activate()
    {
        return true;
    }

    public function deactivate()
    {

        $menu_slug = 'adbirt-publisher';

        /**
         * Removes Adbirt Admin page from wp-admin menu
         */
        remove_menu_page($menu_slug);

        return true;
    }

    /**
     * This function retrives campaign categories from adbirt.com backend
     */
    public function get_categories()
    {
        $categories = array();

        if (get_transient('adbirt_campaign_categories') == false) {
            // not in cache
            $response = wp_safe_remote_get('https://adbirt.com/campaigns/get-campaign-categories-as-json');
            $body = json_decode($response['body'], true);

            if (intval($body['status']) == 200) {
                $categories = $body['categories'];

                set_transient('adbirt_campaign_categories', $categories, 5 * MINUTE_IN_SECONDS);
            }
        } else {
            // already in cache
            $categories = get_transient('adbirt_campaign_categories');
        }

        return $categories;
    }

    public function register_css_and_js()
    {
        $this->register_css();
        $this->register_js();

        return true;
    }

    public function register_css()
    {
        // wp_enqueue_style('ubm-css', 'https://adbirt.com/public/assets/css/ubm.css?ver=2.60', false, '2.5.0', 'all');

        return true;
    }

    public function register_js()
    {
        $url = 'https://adbirt.com/public/assets/js/ubm-jsonp.js?ver=2.70';

        wp_enqueue_script('adbirt-publisher', $url, array('jquery'), '2.6.0', true);

        return true;
    }

    /**
     * Register custom HTTP REST routes for this plugin
     */
    public function register_custom_REST_routes()
    {
        // register_rest_route('wp/v2', 'adbirt-ads-display', array(
        //     'methods' => 'POST',
        //     'callback' => array($this, 'api_endpoint'),
        //     'permission_callback' => '__return_true',
        // ));
    }

    public function settings_hook($links)
    {

        // $this_plugin = false;

        // if (!$this_plugin) {
        //     $this_plugin = plugin_basename(__FILE__);
        // }

        // if (isset($file) && ($file == $this_plugin)) {

        //     $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=adbirt-ads-display">Settings</a>';
        //     array_unshift($links, $settings_link);
        // }

        return $links;
    }

    /**
     * @param {array} attributes = { name: string, id: string, code: string, interval: string }
     * @param {?string} content
     */
    public function adbirt_ads_display_shortcode($attributes = array(), $content = '')
    {
        global $default_config, $default_ad;

        $default_interval = 5;

        $attrs = shortcode_atts(
            array(
                'name' => '',
                'interval' => "$default_interval",
                'category' => '',
            ),
            $attributes
        );

        $config = json_decode(get_option('adbirt_ads_display', json_encode($default_config)), true);
        $ads = $config['ads'] ?? array();
        $name = $attrs['name'];
        $category = isset($attrs['category']) ? $attrs['category'] : '';
        $interval = isset($attrs['interval']) ? intval($attrs['interval']) : $default_interval;

        $markup = '';
        $local_id = 'aad_' . $this->generate_random_string();

        if ($name === '' && is_numeric($interval)) {

            ob_start();
?>

            <span id="<?php echo esc_attr($local_id); ?>">
                <style id="<?php echo esc_attr($local_id); ?>_style">
                    span#<?php echo esc_html($local_id); ?>>a {
                        display: none;
                        visibility: hidden;
                    }
                </style>
                <?php foreach ($ads as $ad) {
                    if ($ad['status'] === 'Published') {
                        if ($category !== '') {
                            if (strtolower($ad['category']) === strtolower($category)) {
                                $_local_markup = $ad['code'];
                                echo $_local_markup;
                                // consider ☝️
                            } else {
                                break;
                            }
                        } else {
                            $_local_markup = $ad['code'];
                            echo $_local_markup;
                            // consider ☝️
                        }
                    }
                } ?>
                <script id="<?php echo esc_attr($local_id); ?>_script">
                    (() => {

                        const widths = [];
                        const heights = [];

                        const showAds = async () => {
                            const id = `<?php echo $local_id; ?>`.trim();
                            const ads = <?php echo json_encode($ads) ?>;
                            const interval = <?php echo intval(esc_attr($interval)); ?>;
                            const category = `<?php echo $category; ?>`;

                            let index = 0;
                            while (true) {

                                const selector = `span#<?php echo esc_attr($local_id); ?> > a.ubm_banner`;
                                const ad_elems = Array.from(document.querySelectorAll(selector));

                                if (ad_elems.length === 0) {
                                    await new Promise(resolve => setTimeout(resolve, 0.5 * 1000));
                                    continue;
                                }

                                if ((widths.length < ads.length) && (heights.length < ads.length)) {
                                    ad_elems.forEach(elem => {
                                        widths.push(elem.style.width);
                                        heights.push(elem.style.height);
                                    });
                                }

                                // console.log('showing ad at index: ', index);
                                const ad = ads[index];
                                const ad_elem = ad_elems[index];

                                ad_elems.forEach(elem => {
                                    elem.style.visibility = 'hidden';
                                    elem.style.display = 'none';
                                    elem.style.width = '0px';
                                    elem.style.height = '0px';
                                });

                                ad_elem.style.visibility = 'visible';
                                ad_elem.style.display = 'block';
                                ad_elem.style.width = widths[index];
                                ad_elem.style.height = heights[index];

                                // console.log('current ad element is ', ad_elem);

                                await new Promise(resolve => setTimeout(resolve, interval * 1000));

                                if ((index + 1) == ads.length) {
                                    index = 0;
                                } else {
                                    ++index;
                                }
                            }
                        };

                        setTimeout(() => showAds(), 1000);
                    })();
                </script>
            </span>

            <?php
            $markup = ob_get_clean();
        } else if ($name !== '') {
            $single_ad = array();

            if (sizeof($ads) > 0) {
                foreach ($ads as $ad) {
                    if ($ad['name'] === $name) {
                        $single_ad = $ad;
                        break;
                    }
                }
            } else {
                $single_ad = $default_ad;
            }

            ob_start();
            if (isset($single_ad['status']) && ($single_ad['status'] === 'Published')) {
                echo $single_ad['code'];
            } else {
            ?>
                <!-- Draft: <?php
                            // echo $single_ad['code'];
                            ?> -->
<?php
            }
            $markup = ob_get_clean();
        }

        return $markup;
    }

    public function admin_sidebar_item()
    {
        $page_title = 'Adbirt Publisher Dashboard';
        $menu_slug = 'adbirt-publisher';
        $capability = 'edit_pages';
        $render_function = array($this, 'adbirt_publisher_page_content');
        $icon = 'https://adbirt.com/public/assets-revamp/img/favicon.png';

        add_menu_page($page_title, $page_title, $capability, $menu_slug, $render_function, $icon, 3);
        add_submenu_page($menu_slug, $page_title, $page_title, $capability, $menu_slug, $render_function);
        add_options_page($page_title, $page_title, $capability, $menu_slug, $render_function);

        return true;
    }

    public function getCategoryNameFromId(array $categories, ?string $search)
    {
        require(plugin_dir_path(__FILE__) . 'modules/get-categories-by-id.php');
    }

    public function adbirt_publisher_page_content()
    {
        require(plugin_dir_path(__FILE__) . 'modules/adbirt-publisher-page-content.php');
    }
}
