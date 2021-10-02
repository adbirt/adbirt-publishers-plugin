<?php

/**
 * @package adbirt-ads-display
 */


// require the widget class before anything else
$widget_file_path = trailingslashit(plugin_dir_path(__FILE__)) . 'includes/class-AAD_widget.php';
require $widget_file_path;

$default_ad = array(
    'name' => 'Sample Campaign',
    'code' => '<a data-id=\'sample==\' class=\'ubm-banner\'></a>',
    'id' => 'sample==',
    'category' => '-- Select a Category --',
    'status' => 'Draft',
);

$default_config = array(
    'ads' => array(
        $default_ad,
    ),
    // categories retrived from adbirt.com backend
    'categories' => array("beauty and jewelry", "recruitment agencies", "finance", "health science", "marketing, sales and service", "education and training", "hospitality and tourism", "information technology", "admin, human resources", "arts, media, communications", "building construction", "hotel, restaurant", "computer, information", "digital products", "ebooks", "software", "engineering", "home appliances"),
    'version' => '1.4.0',
);

/**
 * The main class for handling the
 * creation and insertion of adbirt ads.
 */
class Adbirt_Ads_Display
{

    public ?string $plugin_version = '';

    public function __construct()
    {

        add_action('rest_api_init', array($this, 'register_custom_REST_routes'));
        add_action('widgets_init', array($this, 'load_widget'));
        add_action('admin_menu', array($this, 'admin_sidebar_item'));

        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        add_filter('plugin_action_links', array($this, 'settings_hook'), 10, 1);
        add_filter('wp_enqueue_scripts', array($this, 'register_css_and_js'));

        add_shortcode('adbirt_ads_display', array($this, 'adbirt_ads_display_shortcode'));

        return $this;
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

        global $default_config;

        $plugin_data = get_plugin_data(__FILE__, false, true);
        $this->plugin_version = $plugin_data['Version'];

        $config = get_option(
            'adbirt_ads_display',
            false
        );

        if ($config == false) {
            $config = $default_config;
        }

        $config['version'] = '';
        $config['version'] = $default_config['version'];

        update_option('adbirt_ads_display', json_encode($config));

        return true;
    }

    public function deactivate()
    {

        $menu_slug = 'adbirt-ads-display';

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

        $categories_url = 'https://adbirt.com/campaigns/get-campaign-categories-as-json';

        $response = wp_safe_remote_get($categories_url);
        $categories = $response['body'];

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
        wp_enqueue_style('ubm-css', 'https://adbirt.com/public/assets/css/ubm.css?ver=2.50', false, '2.5.0', 'all');

        return true;
    }

    public function register_js()
    {
        wp_enqueue_script('ubm-jsonp', 'https://adbirt.com/public/assets/js/ubm-jsonp.js?ver=2.50', array('jquery'), '2.5.0', false);

        return true;
    }

    /**
     * Register cusston HTTP REST routes for this plugin
     */
    public function register_custom_REST_routes()
    {

        register_rest_route('wp/v2', 'adbirt-ads-display', array(
            'methods' => 'POST',
            'callback' => array($this, 'api_endpoint'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('wp/v2', 'adbirt-ads-display/delete-campaign', array(
            'methods' => 'POST',
            'callback' => array($this, 'delete_campaign_endpoint'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('wp/v2', 'adbirt-ads-display/add-category', array(
            'methods' => 'POST',
            'callback' => array($this, 'add_category_endpoint'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Endpoint for adding new campaign categories
     */
    public function add_category_endpoint(WP_REST_Request $request)
    {

        global $default_config;

        $params = $request->get_body_params();
        $category = $params['category'];

        $config = json_decode(get_option('adbirt_ads_display', json_encode($default_config)), true);
        $categories = $config['categories'];

        array_push($categories, $category);
        $config['categories'] = $categories;
        update_option('adbirt_ads_display', json_encode($config));

        return rest_ensure_response($config);
    }

    /**
     * Main api endpoint for editing and creating campaigns
     */
    public function api_endpoint(WP_REST_Request $req)
    {
        global $default_config;

        if (!isset($config['categories']) || !is_array($config['categories'])) {
            $config['categories'] = $default_config['categories'];
        }
        $config['version'] = $default_config['version'];

        $params = $req->get_body_params();

        $ad_name = $params['ad_name'] ?? false;
        $ad_id = $params['ad_id'] ?? false;
        $ad_status = $params['ad_status'] ?? 'Draft'; // "Published" or "Draft"
        $ad_code = $params['ad_code'] ? $params['ad_code'] : false;
        $ad_category = $params['ad_category'] ?? 'Draft';

        $new_campaign = $new_campaign = array(
            'name' => $ad_name,
            'code' => $ad_code,
            'id' => $ad_id,
            'category' => $ad_category,
            'status' => $ad_status,
        );

        $config = json_decode(
            get_option(
                'adbirt_ads_display',
                json_encode($default_config)
            ),
            true
        );
        $ads = $config['ads'];

        $already_added = false;

        foreach ($ads as $index => $ad) {
            if ($ad['name'] == $new_campaign['name']) {
                $ads[$index]['name'] = $new_campaign['name'];
                $ads[$index]['code'] = $new_campaign['code'];
                $ads[$index]['id'] = $new_campaign['id'];
                $ads[$index]['category'] = $new_campaign['category'];
                $ads[$index]['status'] = $new_campaign['status'];

                $already_added = true;

                $config['ads'] = $ads;
                break;
            }
        }

        if (!$already_added) {
            array_push($ads, $new_campaign);

            $already_added = true;
            $config['ads'] = $ads;
        }

        update_option('adbirt_ads_display', json_encode($config));

        return rest_ensure_response($new_campaign['code']);
    }

    /**
     * Endpoint for deleting campaigns
     */
    public function delete_campaign_endpoint($req)
    {
        global $default_config;

        $params = $req->get_body_params();
        $from = $params['from'];
        $index = intval($params['index']) - 1;

        $config = json_decode(get_option('adbirt_ads_display', json_encode($default_config)), true);
        $ads = $config['ads'];

        array_splice($ads, $index, 1);
        $config['ads'] = $ads;
        update_option('adbirt_ads_display', json_encode($config));

        if (wp_redirect($from)) {
            exit(0);
        } else {
            return header("Location: $from");
        }
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

        $attrs = shortcode_atts(
            array(
                'name' => '',
                'interval' => '10',
                'category' => '',
            ),
            $attributes
        );

        $config = json_decode(get_option('adbirt_ads_display', json_encode($default_config)), true);
        $ads = $config['ads'] ?? array();
        $name = $attrs['name'];
        $category = isset($attrs['category']) ? $attrs['category'] : '';
        $interval = isset($attrs['interval']) ? intval($attrs['interval']) : 5;

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

        $page_title = 'Adbirt Ads display';
        $menu_slug = 'adbirt-ads-display';
        $capability = 'edit_pages';
        $render_function = array($this, 'options_page_content');
        $icon = 'dashicons-filter';

        add_menu_page($page_title, $page_title, $capability, $menu_slug, $render_function, $icon, 2);
        add_submenu_page($menu_slug, $page_title, $page_title, $capability, $menu_slug, $render_function);
        add_options_page($page_title, $page_title, $capability, $menu_slug, $render_function);

        return true;
    }

    public function options_page_content()
    {
        global $default_config;

        $config = json_decode(
            get_option(
                'adbirt_ads_display',
                json_encode($default_config)
            ),
            true
        );

        $ads = $config['ads'];
        $categories = /* $this->get_categories() */ $config['categories'];

        ob_start();
        ?>
        <div class="wrap">

            <script>
                (() => {
                    const aad_formSelector = '#adbirt-ads-display-endpoint-form';
                    const aad_tableSelector = '#adbirt-ads-display-list-holder';

                    const endpoint = `<?php echo get_bloginfo('url') . '/wp-json' . '/wp/v2/adbirt-ads-display'; ?>`;
                    const addCategoryEndpoint = `${endpoint}/add-category`;
                    const deleteCampaignEndpoint = `${endpoint}/delete-campaign`;

                    window.aadCopyToClipboard = async (event) => {
                        event.preventDefault();
                        try {
                            const text = event.target.innerHTML.trim();
                            await navigator.clipboard.writeText(text);
                            alert('Successfully copied to clipboard');
                        } catch (error) {
                            console.error(error);
                            alert('Couldn\'t copy!');
                        }
                        return false;
                    }

                    window.editCampaign = async (event, campaignName, index) => {
                        intIndex = parseInt(index) + 1;

                        window.aadIsEditing = true;
                        window.aadIsEditingIndex = intIndex;

                        const table = document.querySelector(aad_tableSelector);
                        const rows = table.rows;
                        const form = document.querySelector(aad_formSelector);

                        const row = rows[intIndex];
                        const cells = Array.from(row.querySelectorAll('th'));

                        ad_name = cells.find(cell => cell.className == 'aad_campaign-name').textContent.trim();
                        ad_code = cells.find(cell => cell.className == 'aad_campaign-code').textContent.trim();
                        ad_category = cells.find(cell => cell.className == 'aad_campaign-category').textContent.trim();

                        form.querySelector('[name=ad_name]').value = ad_name;
                        form.querySelector('[name=ad_code]').value = ad_code;
                        form.querySelector('[name=ad_category]').value = ad_category;

                        form.scrollIntoView(true);
                    }

                    window.deleteCampaign = async (event, campaignName, index) => {

                        const shoultDelete = confirm(`Are you sure you want to delete ${campaignName}?`);

                        if (shoultDelete) {

                            index = parseInt(index.toString()) + 1;
                            window.aadIsDeleting = true;

                            const form = document.createElement('form');
                            document.body.appendChild(form);
                            form.method = "POST";
                            form.action = `${deleteCampaignEndpoint}`;

                            const input = document.querySelector('input');
                            input.name = 'index';
                            input.value = index;
                            form.appendChild(input);

                            const input2 = document.createElement('input');
                            input2.name = 'from';
                            input2.value = window.location.href;
                            form.appendChild(input2)

                            form.submit();

                        }

                    }

                    window.aad_addCategory = async (event) => {
                        event.preventDefault();
                        event.target.onclick = null;
                        event.bubbles = false;
                        event.target.style.display = 'none';

                        const div = document.createElement('div');
                        div.innerHTML = '<br />'
                        event.target.after(div);
                        div.setAttribute('id', 'aad_add-category-modal');
                        div.style.float = 'left';
                        div.style.backgroundColor = '#ecedef';
                        div.style.padding = '8px';
                        div.style.borderRadius = '4px';
                        div.style.zIndex = '9999999999';
                        div.style.fontSize = '16px';
                        // div.style.position = 'fixed';
                        // div.style.top = 500;
                        // div.style.left = 500;

                        div.innerHTML += '<b>Category name</b><br />';

                        const input = document.createElement('input');
                        div.appendChild(input);
                        input.style.float = 'left';

                        const submitButton = document.createElement('button');
                        submitButton.type = 'button';
                        submitButton.textContent = 'Add';
                        div.appendChild(submitButton)
                        submitButton.style.float = 'left';
                        submitButton.style.color = '#fff';
                        submitButton.style.background = '#0f0';
                        submitButton.onclick = async () => {
                            try {

                                const category = input.value;
                                if (!category) {
                                    input.placeholder = input.value = 'This field is mandatory';
                                    input.style.borderColor = '#f00';
                                } else {
                                    const res = await fetch(`${addCategoryEndpoint}?noCacheToken=${Math.random()}`, {
                                        method: 'POST',
                                        body: new URLSearchParams({
                                            category
                                        })
                                    });

                                    div.remove();

                                    form.querySelector('select').innerHTML += `<option value="${category}" selected>${category}</option>`;
                                    const feedBack = await res.text();

                                    event.target.onclick = window.aad_addCategory;
                                    event.target.style.display = 'block';
                                }

                            } catch (error) {
                                console.error(error);
                            }
                        }

                        const cancelButton = document.createElement('button');
                        cancelButton.type = 'button';
                        cancelButton.onclick = () => {
                            event.target.onclick = window.aad_addCategory;
                            div.remove();
                            event.target.style.display = 'block';
                        }
                        cancelButton.innerHTML = '&times';
                        div.appendChild(cancelButton);
                        cancelButton.style.float = 'left';
                        cancelButton.style.color = '#fff';
                        cancelButton.style.background = '#f00';

                        // div.innerHTML += '<br />';

                        return false;
                    }
                })();
            </script>

            <?php
            if (isset($_GET['shortcode']) && isset($_GET['fromUrl'])) {
            ?>

                <script>
                    (() => {
                        const shortcode = url.searchParams.get('shortcode');
                        const fromUrl = url.searchParams.get('fromUrl');
                    })();
                </script>

                <br>
                <br>
                <br>

                <div id="aad_success">
                    <br>
                    <br>
                    <strong id="success-message">
                        Submitted successfully
                    </strong>
                    <p>Copy the shortcode below and paste it where you want the campaign to show</p>
                    <p id="aad_shortcode" onclick="aadCopyToClipboard(event)">
                        <b>
                            <?php $shortcode = urldecode(esc_url($_GET['shortcode']));
                            $decoded_shortcode = str_replace('\\', '', $shortcode);
                            echo $decoded_shortcode; ?>
                        </b>
                    </p>
                    <br>
                    <a href="<?php echo urldecode(esc_url($_GET['fromUrl'])); ?>">Go back</a>
                    <br>
                    <br>
                </div>

                <style>
                    html,
                    body {
                        background-color: #ecedef;
                    }

                    .wrap {
                        width: 100% !important;
                        height: 100%;
                        margin: 0;
                        padding: 0;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        flex-direction: column;
                        background-color: #ecedef;
                    }

                    #aad_shortcode {
                        cursor: pointer;
                    }

                    #aad_success {
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        justify-content: center;
                        border-radius: 30px;
                        box-shadow: 0px 0px 3px 3px;
                        width: calc(100% - 20px);
                        font-size: 18px;
                        color: #fff;
                        max-width: 650px;
                        height: 50%;
                        background-color: rgb(77, 73, 73);
                    }

                    #aad_success strong,
                    #aad_success a {
                        font-weight: 900;
                    }

                    #aad_success a {
                        text-decoration: underline;
                        text-decoration-color: black;
                        color: rgb(45, 171, 209);
                        font-size: 18px;
                    }

                    #aad_success strong {
                        color: #fff;
                        font-size: 20px;
                    }
                </style>

            <?php
            } else {
            ?>

                <br>
                <br>
                <center>
                    <h1 id="aad-header">
                        <u>
                            <?php
                            _e('Adbirt Ads Display', 'adbirt-ads-display');
                            echo ' - ' . $config['version'];
                            ?>
                        </u>
                    </h1>
                </center>
                <br />

                <center>
                    <h2 class="screen-reader-text">Plugins list</h2>
                    <table id="adbirt-ads-display-list-holder" class="wp-list-table widefat fixed striped table-view-list">
                        <thead>
                            <tr>
                                <th scope="col">Campaign Name</th>
                                <th scope="col">Campaign Code</th>
                                <th scope="col">Campaign Category</th>
                                <th scope="col">Publicity Status</th>
                                <th scope="col">Shortcode</th>
                                <th scope="col">Edit</th>
                                <th scope="col">Delete</th>
                            </tr>
                        </thead>

                        <tbody id="adbirt-ads-display-list" class="the-list">

                            <?php if (sizeof($ads) > 0) { ?>
                                <!-- loop through all the ads -->
                                <?php

                                $index = 0;

                                foreach ($ads as $ad) {
                                    // foreach ($merged_array as $ad) {
                                    $_category = ($ad['category'] == '-- Select a Category --' ? 'no category' : (ucfirst($ad['category']) ?? null)) ?? 'no category';
                                ?>

                                    <tr class="<?php echo 'level-' . $index; ?>  <?php echo $ad['status'] === 'Published' ? 'active' : '' ?>">
                                        <th class="aad_campaign-name"> <?php echo $ad['name']; ?> </th>
                                        <th class="aad_campaign-code">
                                            <code>
                                                <?php echo isset($ad['code']) ? esc_html($ad['code']) : 'no code'; ?>
                                            </code>
                                        </th>
                                        <th class="aad_campaign-category"> <?php echo $_category; ?> </th>
                                        <th class="aad_campaign-status"> <?php echo isset($ad['status']) ? $ad['status'] : 'Draft'; ?> </th>
                                        <th class="aad_campaign-shortcode" title="Click to copy shortcode for '<?php echo $ad['name']; ?>'" style="cursor: pointer;" onclick="window.aadCopyToClipboard(event)">
                                            <code>
                                                <?php echo isset($ad['id']) ? "[adbirt_ads_display name='" . $ad['name'] . "']" : 'no shortcode'; ?>
                                            </code>
                                        </th>
                                        <th class="aad_campaign-edit"> <button id="aad-edit-campaign-button" onclick="window.editCampaign(event, `<?php echo $ad['name']; ?>`, `<?php echo $index; ?>`)">Edit</button> </th>
                                        <th class="aad_campaign-delete"> <button id="aad-delete-campaign-button" onclick="window.deleteCampaign(event, `<?php echo $ad['name']; ?>`, `<?php echo $index; ?>`)">Delete</button> </th>
                                    </tr>

                                <?php

                                    $index++;
                                } ?>
                                <!-- end loop -->

                            <?php } else { ?>

                                <tr>
                                    <td>Nothing yet</td>
                                    <td>Nothing yet</td>
                                    <td>Nothing yet</td>
                                    <td>Nothing yet</td>
                                    <td>Nothing yet</td>
                                    <td>Nothing yet</td>
                                    <td>Nothing yet</td>
                                </tr>

                            <?php } ?>

                        </tbody>

                        <tfoot>
                            <tr>
                                <th scope="col">Campaign Name</th>
                                <th scope="col">Campaign Code</th>
                                <th scope="col">Campaign Category</th>
                                <th scope="col">Publicity Status</th>
                                <th scope="col">Shortcode</th>
                                <th scope="col">Edit</th>
                                <th scope="col">Delete</th>
                            </tr>
                        </tfoot>
                    </table>
                </center>
                <br />
                <br />

                <center>
                    <h2 class="aad-header" id="aad-add-new-campaign">
                        <u>
                            <?php _e('Add new campaign', 'adbirt-ads-display') ?>
                        </u>
                    </h2>
                    <br>
                    <br>
                    <br>
                    <h3 class="aad-header" id="aad-add-new-campaign">
                        <u>
                            <?php _e('Note: Saving a campaign with the same name as an existing campaign will edit th exising one', 'adbirt-ads-display') ?>
                        </u>
                    </h3>
                    <br>
                    <br>
                    <br>

                    <form method="post" id="adbirt-ads-display-endpoint-form" onsubmit="return false">

                        <div style="display: none; padding: 8px; background: green; color: white;" id="adbirt-ads-display-success">
                            <?php _e('Submitted successfully', 'adbirt-ads-display'); ?>
                            <span id="aad_ad_placeholder"></span>
                        </div>
                        <div style="display: none; padding: 8px; background: green; color: white;" id="adbirt-ads-display-loading">
                            <?php _e('Loading...', 'adbirt-ads-display'); ?>
                        </div>


                        <label for="adbirt-ads-display-ad-name"> <?php _e('Campaign name', 'adbirt-ads-display') ?> </label>
                        <br />
                        <input type="text" name="ad_name" value="" id="adbirt-ads-display-ad-name" required />
                        <br />
                        <br />

                        <label for="adbirt-ads-display-ad-category"> <?php _e('Campaign category', 'adbirt-ads-display') ?> </label>
                        <br />
                        <select name="ad_category" value="" id="adbirt-ads-display-ad-category" required>
                            <option selected disabled value="-- Select a Category --">-- Select a Category --</option>
                            <?php foreach ($categories as $index => $category) { ?>
                                <option value="<?php echo $category; ?>">
                                    <?php echo ucfirst($category); ?>
                                </option>
                            <?php } ?>
                        </select>
                        <small style="display: block !important; text-align: left !important;">
                            <a href="#" onclick="window.aad_addCategory(event)">Add new category</a>
                        </small>
                        <br />
                        <br />

                        <label for="adbirt-ads-display-ad-status">
                            <?php _e('Publish immeiately', 'adbirt-ads-display') ?>
                            <input style="width: 20px !important; height: 20px !important; display: inline; float: left !important;" name="ad_status" id="adbirt-ads-display-ad-code" type="checkbox" checked />
                        </label>
                        <br />
                        <br />

                        <label for="adbirt-ads-display-ad-code"> <?php _e('Campaign code', 'adbirt-ads-display') ?> </label>
                        <br />
                        <textarea style="height: 160px;" rows="10" name="ad_code" value="" id="adbirt-ads-display-ad-code" required></textarea>
                        <br />
                        <br />



                        <center>
                            <input type="submit" id="adbirt-ads-display-button" name="submit" value="Save" />
                        </center>
                    </form>
                </center>

                <style>
                    :root {
                        --aad-primary-color: #bc6a44;
                    }

                    * {
                        scroll-behavior: smooth !important;
                    }

                    #adbirt-ads-display-list-holder {
                        max-width: 100%;
                        font-size: 16px !important;
                        overflow-y: auto;
                        border-radius: 5px;
                        box-shadow: 0px 0px 3px 3px;
                        border: 2px solid var(--aad-primary-color);
                    }

                    #adbirt-ads-display-list-holder th {
                        line-clamp: 4 !important;
                        -webkit-line-clamp: 4 !important;
                        text-overflow: ellipsis !important;
                        overflow: hidden !important;
                    }

                    #adbirt-ads-display-list-holder,
                    #adbirt-ads-display-list-holder * {
                        transition: all .5s all;
                    }

                    body {
                        /* background-color: var(--aad-primary-color) !important; */
                        background: linear-gradient(to bottom, #966650aa, #db622abb, var(--aad-primary-color)), url('<?php echo trailingslashit(plugin_dir_url(__FILE__)); ?>assets/img/background.jpg');
                        background-size: cover;
                        background-attachment: fixed;
                        /* background-position: center; */
                    }

                    #aad-edit-campaign-button,
                    #aad-delete-campaign-button {
                        border: .2px solid var(--aad-primary-color) !important;
                        padding: 8px !important;
                        border-radius: 4px !important;
                        color: #fff !important;
                        cursor: pointer !important;
                    }

                    #aad-edit-campaign-button {
                        background-color: #00708b !important;
                    }

                    #aad-delete-campaign-button {
                        background-color: #7e0030 !important;
                    }

                    #aad-header,
                    .aad-header {
                        font-weight: 900 !important;
                        color: blue !important;
                        background-color: #fff;
                        padding: 9px;
                        display: inline;
                        width: fit-content !important;
                        margin: 8px;
                        border-radius: 5px;
                    }

                    table {
                        background-color: #ecedef;
                    }

                    #adbirt-ads-display-endpoint-form {
                        width: calc(100% - 20px);
                        /* height: 300px; */
                        padding: 12px;
                        max-width: 650px;
                        font-weight: 900;
                        border-radius: 15px;
                        background-color: #ecedef;
                        box-shadow: 0px 0px 3px 3px;
                    }

                    #adbirt-ads-display-endpoint-form label,
                    #adbirt-ads-display-endpoint-form input,
                    #adbirt-ads-display-endpoint-form select,
                    #adbirt-ads-display-endpoint-form textarea {
                        width: 100% !important;
                        max-width: none !important;
                        border-radius: 5px !important;
                    }

                    #adbirt-ads-display-endpoint-form label {
                        text-align: left !important;
                        float: left !important;
                        display: block !important;
                    }

                    #adbirt-ads-display-endpoint-form input:not([type=submit]),
                    #adbirt-ads-display-endpoint-form input:not([type=checkbox]),
                    #adbirt-ads-display-endpoint-form textarea,
                    #adbirt-ads-display-endpoint-form select {
                        border-bottom: 2px solid var(--aad-primary-color);
                    }

                    #adbirt-ads-display-endpoint-form input,
                    #adbirt-ads-display-endpoint-form input:not([type=checkbox]),
                    #adbirt-ads-display-endpoint-form textarea {
                        height: 30px;
                    }

                    #adbirt-ads-display-endpoint-form input[type=submit] {
                        background-color: var(--aad-primary-color);
                        color: #fff;
                        font-weight: 900;
                    }

                    #adbirt-ads-display-list-holder,
                    #adbirt-ads-display-list-holder th,
                    #adbirt-ads-display-list-holder td {
                        border: 2px solid var(--aad-primary-color);
                    }
                </style>

                <script>
                    const namespace = 'adbirt-ads-display';

                    const form = document.querySelector(`#adbirt-ads-display-endpoint-form`);
                    const submit_button = document.querySelector(`#${namespace}-button`);

                    const endpoint = `<?php echo get_bloginfo('url') . '/wp-json' . '/wp/v2/adbirt-ads-display'; ?>`;

                    const loading = document.querySelector(`#${namespace}-loading`);
                    const success = document.querySelector(`#${namespace}-success`);

                    submit_button.addEventListener('click', async () => {


                        const ad_id = '';
                        const ad_code = document.querySelector(`[name=ad_code]`).value || '';
                        const ad_name = document.querySelector(`#${namespace}-ad-name`).value || '';
                        const ad_category = document.querySelector('[name=ad_category]').value.toString().trim() || '';
                        ad_status = document.querySelector('[name=ad_status]').checked ? 'Published' : 'Draft';

                        if (!ad_code && !ad_id /*  && !ad_category */ ) {

                            alert(`Campaign name, category, and code must be provided.`);

                        } else if (ad_name && ad_code /*  && ad_category */ ) {

                            let isError = false;

                            try {

                                loading.style.display = 'block';
                                success.style.display = 'none';

                                const res = await fetch(`${endpoint}?noCacheToken=${Math.random()}`, {
                                    method: 'POST',
                                    body: new URLSearchParams({
                                        ad_name,
                                        ad_id,
                                        ad_code,
                                        ad_category,
                                        ad_status,
                                        edit: true,
                                        index: window.aadIsEditingIndex || false,
                                    })
                                });

                                loading.style.display = 'none';
                                success.style.display = 'block';

                                const placeholder = document.querySelector('#aad_ad_placeholder');
                                placeholder.scrollIntoView(true);

                                const shortcode = `[adbirt_ads_display name='${ad_name}']`;
                                const _url = new URL(window.location.href);
                                const fromUrl = window.location.href;

                                _url.searchParams.append('fromUrl', encodeURIComponent(fromUrl));
                                _url.searchParams.append('shortcode', shortcode);
                                const toUrl = _url.toString();

                                isError = false;

                                setTimeout(() => window.location.replace(toUrl), 500);

                            } catch (error) {

                                loading.style.display = 'none';
                                success.style.display = 'none';

                                isError = true;
                                console.error(error);

                                alert('Network error!!!');

                            }

                        } else {

                            alert('something went wrong!\n\ncheck all the input fields and provide accurate details');

                        }

                    })
                </script>

        </div>

    <?php
            }
    ?>

<?php
        $markup = ob_get_clean();

        // echo the markup of the shortcode on the page
        echo $markup;

        // optional return
        // return $markup;
    }
}
