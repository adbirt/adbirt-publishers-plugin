<?php

/**
 * @package adbirt-ads-display
 */

/**
 * Creates the widget
 */
class AAD_widget extends WP_Widget
{

    // The construct part
    public function __construct()
    {
        parent::__construct(
            // Base ID of your widget
            'AAD_widget',
            // Widget name will appear in UI
            __('Adbirt Ads display widget', 'adbirt-ads-display'),
            // Widget description
            array('description' => __('Insert Abirt ads in your widget area', 'adbirt-ads-display'))
        );
    }

    // Creating widget front-end
    public function widget($args, $instance)
    {

        $name = '';
        if (isset($instance['name'])) {
            $name = $instance['name'];
        } else {
            $name = $instance['name'] = '';
        }

        // before and after widget arguments are defined by themes
        echo $args['before_widget'];

        $name = isset($instance['name']) ? $instance['name'] : '';
        $markup = do_shortcode("[adbirt_ads_display name='$name']");
        echo $markup;

        echo $args['after_widget'];
    }

    // Widget Backend
    public function form($instance)
    {
        $name = '';
        if (isset($instance['name'])) {
            $name = $instance['name'];
        } else {
            $name = $instance['name'] = '';
        }
        // Widget admin form
?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('name')); ?>">
                Campaign Name
                <small>
                    (optioal, if this is left out, then all campaigns will show, one at a time)
                </small>
            </label>
            <input name="<?php echo esc_attr($this->get_field_name('name')); ?>" id="<?php echo esc_attr($this->get_field_id('name')); ?>" class="widefat" type="text" value="<?php echo esc_attr($name); ?>">
        </p>
<?php
    }

    // Updating widget replacing old instances with new
    public function update($new_instance, $old_instance)
    {
        $instance = array();
        $instance['name'] = (!empty($new_instance['name'])) ? strip_tags($new_instance['name']) : strip_tags($old_instance['name'] ?? '');

        return $instance;
    }

    // Class AAD_widget ends here
}
