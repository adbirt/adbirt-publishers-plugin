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
        $filtered = array_filter($categories, function ($cat) use ($search) {
            return intval($cat['id']) == intval($search) ? true : false;
        });

        $match = null;

        foreach ($filtered as $key => $value) {
            $match = $value;
            break;
        }

        return ucwords($match['category_name']);
    }

    public function adbirt_publisher_page_content()
    {
        $config_defaults = array(
            'is_logged_in' => false,
        );
        $config = get_option('adbirt_publisher_config', $config_defaults);

        $config['categories'] = $this->get_categories();
        $config['campaigns'] = array();

        $alertMessages = array();

        if (isset($_POST['logout']) && $_POST['logout'] == 'true') {
            update_option('adbirt_publisher_config', $config_defaults);
            $config = $config_defaults;

            array_push($alertMessages, array(
                'message' => 'You\'ve logged out successfully.',
                'severity' => 'success'
            ));
        }

        if ($config['is_logged_in'] === false) {

            if (isset($_POST['email'])) {
                if (isset($_POST['password'])) {
                    $email = $_POST['email'];
                    $password = $_POST['password'];

                    $remote_response = wp_remote_post('https://adbirt.com/login', array(
                        'timeout'     => 4000,
                        'redirection' => 15,
                        'headers' => array('Content-Type' => 'application/x-www-form-urlencoded'),
                        'blocking' => true,
                        'body' => array(
                            'email' => $email,
                            'password' => $password,
                            'is_remote_request' => 'true'
                        )
                    ));

                    $body = json_decode($remote_response['body'], true, 512 * 2, JSON_THROW_ON_ERROR);

                    function loginError(string $errorMessage, string $severity, &$config, &$alertMessages)
                    {
                        $config['is_logged_in'] = false;
                        $config['user'] = null;

                        array_push($alertMessages, array(
                            'message' => $errorMessage,
                            'severity' => $severity
                        ));
                    };

                    if (intval($body['status']) == 300) {
                        loginError($body['message'], 'info', $config, $alertMessages);
                    } elseif (intval($body['status']) == 400) {
                        loginError($body['message'], 'danger', $config, $alertMessages);
                    } elseif (intval($body['status']) == 500) {
                        loginError('An error occurred on the server. Please try again later.', 'danger', $config, $alertMessages);
                    } elseif (intval($body['status']) == 200) {
                        $user = $config['user'] = $body['payload'];

                        // user properties >>
                        // active: 1
                        // address: "NO 4, Jesus Avenue Canaan Estate, Along Lekki garden phase 1"
                        // birthday: "2017-07-05"
                        // city: "Lagos"
                        // country: "Nigeria"
                        // created_at: "2019-09-12 07:13:49"
                        // email: "adbirtofficial@gmail.com"
                        // id: 49
                        // login: "email"
                        // name: "Adbirt"
                        // phone: null
                        // updated_at: "2022-01-07 08:47:10"

                        if (intval($user['active']) == 1) {
                            $config['is_logged_in'] = true;
                            $config['user'] = $body['payload'];

                            $role = ''; // 'admin', 'advertiser', 'publisher'

                            switch (intval($body['role_id'])) {
                                case 1:
                                    $user['role'] = 'admin';
                                    break;

                                case 2:
                                    $user['role'] = 'advertiser';
                                    break;

                                case 3:
                                    $user['role'] = 'publisher';
                                    break;
                            }

                            if ($user['role'] != 'publisher') {
                                loginError('Only Publisher accounts are allowed! Visit https://adbirt.com/register to sign up as a publisher', 'danger', $config, $alertMessages);
                            }

                            $config['campaigns'] = $body['campaigns'];

                            $user['propic'] = $body['propic'];
                            $config['user'] = $user;
                            // done
                        } else {
                            loginError('Your account has been deactivated, contact info@adbirt.com to rectify this.', 'danger', $config, $alertMessages);
                        }
                    } else {
                        loginError('Something went wrong. Please try again later.', 'danger', $config, $alertMessages);
                    }
                } else {
                    array_push($alertMessages, array(
                        'message' => 'Please enter your password.',
                        'severity' => 'danger'
                    ));
                }
            }
            // else skip
        }

        // 
        // apply state to UI and render it
        // 

        ob_start();
        ?>
        <!-- begin wrap -->
        <div class="wrap">
            <!-- begin style section -->
            <link rel="stylesheet" href="<?php echo trailingslashit(plugin_dir_url(__FILE__)) . 'assets/css/dashboard-styles.css'; ?>">
            <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
            <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto:regular,bold,italic,thin,light,bolditalic,black,medium&amp;lang=en">
            <link rel="stylesheet" href="https://adbirt.com/public/dist/css/style.min.css">
            <link rel="stylesheet" href="https://adbirt.com/public/assets-revamp/bootstrap/css/bootstrap.css">
            <link rel="stylesheet" href="https://adbirt.com/public/assets-revamp/fonts/font-awesome.css">
            <!-- end style section -->

            <?php
            if ($config['is_logged_in'] === true) {
            ?>
                <div class="container-sm">

                    <br />

                    <div class="row">
                        <div class="col-12 col-md-4">
                            <img src="<?php echo $config['user']['propic'] ?? 'https://adbirt.com/public/assets-revamp/img/avatar.png'; ?>" alt="<?php echo $user['name']; ?>" title="<?php echo $user['name']; ?>" class="w-100 img-circle bg-warning border border-warning" />
                        </div>

                        <div class="col-12 col-md-8 d-flex flex-column align-items-start justify-content-center">
                            <ul class="card w-100">
                                <li>
                                    <strong>Username:</strong> <?php echo $config['user']['name']; ?>
                                </li>
                                <li>
                                    <strong>Email:</strong> <?php echo $config['user']['email']; ?>
                                </li>
                                <li>
                                    <strong>Role:</strong> <?php echo $config['user']['role']; ?>
                                </li>
                                <li>
                                    <form method="post">
                                        <input type="hidden" name="logout" value="true">
                                        <button type="submit" class="btn btn-danger">Logout</button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <br />

                    <!-- start copy -->
                    <section class="content">
                        <div class="container-fluid yield-content">

                            <!-- Begin yield content -->
                            <!-- Content -->
                            <div class="layout-content" data-scrollable="" id="mainDiv">
                                <div class="w-100">

                                    <div class="viewtable">
                                        <h3 class="active">My Running Ads</h3>
                                        <p>Copy the shortcode for any campaign and place it where you want the ad to show</p>
                                        <div class="card w-100 mw-100">
                                            <div class="table-responsive">
                                                <table id="datatable-example" class="table table-striped table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Name</th>
                                                            <th>Category</th>
                                                            <th>Type</th>
                                                            <th>Price</th>
                                                            <th>Short code</th>
                                                            <!-- <th>Action</th> -->
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php
                                                        foreach ($config['campaigns'] as $campaignIndex => $campaign) {
                                                        ?>
                                                            <tr class="data-11">
                                                                <td class="campaigns_name"><?php echo ucwords($campaign['campaign']['campaign_name']); ?></td>
                                                                <td class="campaigns_name"><?php echo $this->getCategoryNameFromId($config['categories'], $campaign['campaign']['campaign_category']); ?></td>
                                                                <td class="campaigns_name"><?php echo ucwords($campaign['campaign']['campaign_type']); ?></td>
                                                                <td class="campaigns_name"><?php echo $campaign['campaign']['campaign_cost_per_action']; ?></td>
                                                                <td>
                                                                    <div class="row input-group mb-3 w-75">
                                                                        <input type="text" value='<a class="ubm-banner" data-id="<?php echo base64_encode($campaign["advert_code"]); ?>"></a>' class="form-control" id="source-code-<?php echo $campaignIndex; ?>" readonly="">
                                                                        <div class="input-group-append">
                                                                            <span class="input-group-text copy-btn btn btn-info" title="Copy to clipboard" data-clipboard-target="#source-code-<?php echo $campaignIndex; ?>" data-clipboard-action="copy">
                                                                                <i class="fa fa-copy"></i>
                                                                                <!-- &nbsp;
                                                                                Copy -->
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                                <!-- <td>
                                                                    <a href="https://adbirt.com/campaigns/view-my-campaign/MTE=" class="btn btn-info">
                                                                        <i class="fa fa-eye"></i>
                                                                    </a>
                                                                </td> -->
                                                            </tr>
                                                        <?php
                                                        }
                                                        ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div><!-- /.box-body -->
                                    </div><!-- /.box -->
                                </div>
                            </div><!-- /.col -->
                        </div><!-- /.row -->
                    </section>
                    <!-- end copy -->

                </div>
            <?php
            } elseif ($config['is_logged_in'] === false) {
            ?>
                <!-- begin login section -->
                <div class="login">
                    <div class="row w-100">
                        <div class="col-sm-8 col-sm-push-1 col-md-4 col-md-push-4 col-lg-4 col-lg-push-4">

                            <div class="row justify-content-center">
                                <div class="center m-a-2">
                                    <div class="icon-block img-circle">
                                        <a target="_blank" href="https://adbirt.com"><img src="http://adbirt.com/public/assets-revamp/img/favicon.png" /></a>
                                    </div>
                                </div>
                                <div class="center m-a-2">
                                    <div class="icon-block img-circle">
                                        <a href="#adbirt-login-form"><i class="material-icons md-36 text-muted">lock</i></a>
                                    </div>
                                </div>
                            </div>

                            <div class="card bg-transparent">
                                <div class="card-header bg-white center">
                                    <h4 class="card-title">Login</h4>
                                    <p class="card-subtitle">Access your Adbirt Publisher Account</p>

                                    <?php
                                    foreach ($alertMessages as $key => $alert) {
                                        $message = $alert['message'];
                                        $severity = $alert['severity'];
                                    ?>
                                        <div class="alert alert-<?php echo $severity; ?>" role="alert">
                                            <?php echo $message; ?>
                                        </div>
                                    <?php
                                    }
                                    ?>

                                </div>

                                <div class="p-2">
                                    <form method="post" id="adbirt-login-form">
                                        <div class="form-group">
                                            <input type="text" name="email" id="email" class="form-control" autofocus placeholder="Email Address or Phone Number" required />
                                        </div>

                                        <div class="form-group">
                                            <input type="password" name="password" id="password" class="form-control" placeholder="Password" required />
                                        </div>

                                        <div class="form-group ">
                                            <button type="submit" class="btn btn-primary btn-block btn-rounded">Login</button>
                                            <br />
                                        </div>
                                    </form>

                                </div>

                                <div class="card-footer center bg-white">
                                    <p>Not yet a User? <a target="_blank" href="https://adbirt.com/register" class="text-center">Sign up</a></p>
                                    <p>Or, go to <a target="_blank" href="https://adbirt.com/">Adbirt Home Page</a></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end login section -->
            <?php
            }
            ?>

            <!-- begin script section -->
            <script src="https://adbirt.com/public/dist/vendor/jquery.min.js"></script>
            <script src="https://adbirt.com/public/dist/vendor/tether.min.js"></script>
            <script src="https://adbirt.com/public/dist/vendor/bootstrap.min.js"></script>
            <script src="https://adbirt.com/public/dist/vendor/adminplus.js"></script>
            <script src="https://adbirt.com/public/dist/js/main.min.js"></script>
            <script src="https://adbirt.com/public/dist/vendor/sweetalert.min.js"></script>
            <script src="https://adbirt.com/public/plugins/iCheck/icheck.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/clipboard@2.0.8/dist/clipboard.min.js"></script>
            <script>
                new ClipboardJS('.copy-btn');
            </script>
            <script>
                $(function() {
                    $('input').iCheck({
                        checkboxClass: 'icheckbox_square-blue',
                        radioClass: 'iradio_square-blue',
                        increaseArea: '20%'
                    });
                });
            </script>
            <!-- end script section -->
        </div>
        <!-- end wrap -->
<?php
        echo ob_get_clean();

        update_option('adbirt_publisher_config', $config);
    }
}
