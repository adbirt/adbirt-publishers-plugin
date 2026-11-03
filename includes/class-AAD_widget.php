<?php

/**
 * Widget: one Adbirt ad slot in a sidebar or footer.
 *
 * Keeps the 1.x widget base id (AAD_widget) so existing widget areas keep
 * the widget; it now needs a slot id instead of a campaign name.
 *
 * @package adbirt-ads-display
 */

if (!defined('ABSPATH')) {
    exit;
}

class AAD_widget extends WP_Widget
{
    public function __construct()
    {
        parent::__construct(
            'AAD_widget',
            __('Adbirt Ad Slot', 'adbirt-ads-display'),
            array('description' => __('Shows ads from one Adbirt ad slot.', 'adbirt-ads-display'))
        );
    }

    public function widget($args, $instance)
    {
        $slot_id = isset($instance['slot_id']) ? $instance['slot_id'] : '';
        $markup = Adbirt_Publisher::instance()->slot_markup($slot_id);
        if ($markup === '') {
            return;
        }
        echo $args['before_widget'];
        if (!empty($instance['title'])) {
            echo $args['before_title'] . esc_html(apply_filters('widget_title', $instance['title'])) . $args['after_title'];
        }
        echo $markup; // Built from a validated slot id with esc_attr.
        echo $args['after_widget'];
    }

    public function form($instance)
    {
        $title = isset($instance['title']) ? $instance['title'] : '';
        $slot_id = isset($instance['slot_id']) ? $instance['slot_id'] : '';
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php esc_html_e('Title (optional)', 'adbirt-ads-display'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('slot_id')); ?>"><?php esc_html_e('Slot id', 'adbirt-ads-display'); ?></label>
            <input class="widefat code" id="<?php echo esc_attr($this->get_field_id('slot_id')); ?>" name="<?php echo esc_attr($this->get_field_name('slot_id')); ?>" type="text" value="<?php echo esc_attr($slot_id); ?>" placeholder="slot_...">
            <small><?php esc_html_e('From the Publisher section of your Adbirt dashboard.', 'adbirt-ads-display'); ?></small>
        </p>
        <?php
    }

    public function update($new_instance, $old_instance)
    {
        $slot_id = isset($new_instance['slot_id']) ? sanitize_text_field($new_instance['slot_id']) : '';
        return array(
            'title' => isset($new_instance['title']) ? sanitize_text_field($new_instance['title']) : '',
            'slot_id' => Adbirt_Publisher::is_slot_id($slot_id) ? $slot_id : '',
        );
    }
}
