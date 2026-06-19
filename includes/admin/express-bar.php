<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_express_bar_admin_boot')) {
    function vms_express_bar_admin_boot(): void
    {
        add_action('add_meta_boxes_vms_event_plan', 'vms_express_bar_add_metabox');
        add_action('save_post_vms_event_plan', 'vms_express_bar_save_metabox', 20, 2);
        add_action('admin_menu', 'vms_express_bar_admin_menu', 40);
        add_action('admin_post_vms_express_bar_update_order', 'vms_express_bar_handle_order_update');
    }
}
add_action('init', 'vms_express_bar_admin_boot');

if (!function_exists('vms_express_bar_add_metabox')) {
    function vms_express_bar_add_metabox(): void
    {
        add_meta_box(
            'vms_express_bar',
            __('Express Bar', 'vms'),
            'vms_express_bar_render_metabox',
            'vms_event_plan',
            'normal',
            'default'
        );
    }
}

if (!function_exists('vms_express_bar_render_metabox')) {
    function vms_express_bar_render_metabox(WP_Post $post): void
    {
        $enabled = (string) get_post_meta($post->ID, '_vms_express_bar_enabled', true) === '1';
        $product_ids = (string) get_post_meta($post->ID, '_vms_express_bar_product_ids', true);
        $headline = (string) get_post_meta($post->ID, '_vms_express_bar_headline', true);
        $pickup = (string) get_post_meta($post->ID, '_vms_express_bar_pickup_instructions', true);
        $shortcode = '[vms_express_bar_menu event_plan_id="' . (int) $post->ID . '"]';
        wp_nonce_field('vms_express_bar_save_' . $post->ID, 'vms_express_bar_nonce');
        ?>
        <p>
            <label>
                <input type="checkbox" name="vms_express_bar_enabled" value="1" <?php checked($enabled); ?> />
                <?php echo esc_html__('Enable Express Bar for this event.', 'vms'); ?>
            </label>
        </p>
        <p>
            <label for="vms_express_bar_product_ids"><strong><?php echo esc_html__('Woo product IDs', 'vms'); ?></strong></label><br />
            <input type="text" class="widefat" id="vms_express_bar_product_ids" name="vms_express_bar_product_ids" value="<?php echo esc_attr($product_ids); ?>" placeholder="123, 456, 789" />
            <small><?php echo esc_html__('Comma-separated WooCommerce product IDs allowed on this event’s Express Bar menu.', 'vms'); ?></small>
        </p>
        <p>
            <label for="vms_express_bar_headline"><strong><?php echo esc_html__('Customer headline', 'vms'); ?></strong></label><br />
            <input type="text" class="widefat" id="vms_express_bar_headline" name="vms_express_bar_headline" value="<?php echo esc_attr($headline); ?>" />
        </p>
        <p>
            <label for="vms_express_bar_pickup_instructions"><strong><?php echo esc_html__('Pickup / ID instructions', 'vms'); ?></strong></label><br />
            <textarea class="widefat" rows="4" id="vms_express_bar_pickup_instructions" name="vms_express_bar_pickup_instructions"><?php echo esc_textarea($pickup); ?></textarea>
        </p>
        <p>
            <strong><?php echo esc_html__('Shortcode', 'vms'); ?></strong><br />
            <code><?php echo esc_html($shortcode); ?></code>
        </p>
        <p>
            <?php echo esc_html__('Compliance reminder: this module is structured as pre-order + in-person handoff. Staff must verify ID in person before handing over alcohol.', 'vms'); ?>
        </p>
        <?php
    }
}

if (!function_exists('vms_express_bar_save_metabox')) {
    function vms_express_bar_save_metabox(int $post_id, WP_Post $post): void
    {
        if ($post->post_type !== 'vms_event_plan') {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        $nonce = isset($_POST['vms_express_bar_nonce']) ? sanitize_text_field(wp_unslash($_POST['vms_express_bar_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'vms_express_bar_save_' . $post_id)) {
            return;
        }

        update_post_meta($post_id, '_vms_express_bar_enabled', !empty($_POST['vms_express_bar_enabled']) ? '1' : '0');

        $raw_ids = isset($_POST['vms_express_bar_product_ids']) ? sanitize_text_field(wp_unslash($_POST['vms_express_bar_product_ids'])) : '';
        $ids = array_values(array_unique(array_filter(array_map('absint', preg_split('/[^0-9]+/', $raw_ids) ?: array()))));
        update_post_meta($post_id, '_vms_express_bar_product_ids', implode(',', $ids));

        $headline = isset($_POST['vms_express_bar_headline']) ? sanitize_text_field(wp_unslash($_POST['vms_express_bar_headline'])) : '';
        update_post_meta($post_id, '_vms_express_bar_headline', $headline);

        $pickup = isset($_POST['vms_express_bar_pickup_instructions']) ? sanitize_textarea_field(wp_unslash($_POST['vms_express_bar_pickup_instructions'])) : '';
        update_post_meta($post_id, '_vms_express_bar_pickup_instructions', $pickup);
    }
}

if (!function_exists('vms_express_bar_admin_menu')) {
    function vms_express_bar_admin_menu(): void
    {
        add_submenu_page(
            'vms-dashboard',
            __('Express Bar', 'vms'),
            __('Express Bar', 'vms'),
            'manage_options',
            'vms-express-bar',
            'vms_express_bar_render_admin_page'
        );
    }
}

if (!function_exists('vms_express_bar_render_admin_page')) {
    function vms_express_bar_render_admin_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'vms'));
        }
        if (!class_exists('WooCommerce')) {
            echo '<div class="wrap"><h1>Express Bar</h1><p>WooCommerce is required.</p></div>';
            return;
        }

        $selected_plan_id = isset($_GET['event_plan_id']) ? absint($_GET['event_plan_id']) : 0;
        $plans = get_posts(array(
            'post_type' => 'vms_event_plan',
            'post_status' => array('publish', 'draft', 'future', 'pending', 'private'),
            'numberposts' => 100,
            'orderby' => 'date',
            'order' => 'DESC',
        ));

        $orders = wc_get_orders(array(
            'limit' => 100,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_key' => '_vms_express_bar_order',
            'meta_value' => '1',
            'status' => array_keys(wc_get_order_statuses()),
        ));

        echo '<div class="wrap">';
        echo '<h1>Express Bar</h1>';
        echo '<p>Prepaid queue for event-day drink pickup. Staff should verify ID in person before handing over alcohol.</p>';

        echo '<form method="get" style="margin:1em 0;">';
        echo '<input type="hidden" name="page" value="vms-express-bar" />';
        echo '<label for="vms-express-bar-event-plan"><strong>Filter by Event Plan:</strong></label> ';
        echo '<select id="vms-express-bar-event-plan" name="event_plan_id">';
        echo '<option value="0">All Event Plans</option>';
        foreach ($plans as $plan) {
            echo '<option value="' . (int) $plan->ID . '" ' . selected($selected_plan_id, (int) $plan->ID, false) . '>' . esc_html(get_the_title($plan->ID)) . ' (#' . (int) $plan->ID . ')</option>';
        }
        echo '</select> ';
        submit_button(__('Filter', 'vms'), 'secondary', '', false);
        echo '</form>';

        if (empty($orders)) {
            echo '<p>No Express Bar orders found yet.</p>';
            echo '</div>';
            return;
        }

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>Order</th><th>Date</th><th>Customer</th><th>Event Plan</th><th>Items</th><th>Queue</th><th>ID Verified</th><th>Actions</th>';
        echo '</tr></thead><tbody>';

        $found = false;
        foreach ($orders as $order) {
            if (!($order instanceof WC_Order)) {
                continue;
            }

            $event_ids_csv = (string) $order->get_meta('_vms_express_bar_event_plan_ids', true);
            $event_ids = array_values(array_unique(array_filter(array_map('absint', preg_split('/[^0-9]+/', $event_ids_csv) ?: array()))));
            if ($selected_plan_id > 0 && !in_array($selected_plan_id, $event_ids, true)) {
                continue;
            }

            $found = true;
            $queue_status = sanitize_key((string) $order->get_meta('_vms_express_bar_queue_status', true));
            if ($queue_status === '') {
                $queue_status = 'new';
            }
            $id_verified = (string) $order->get_meta('_vms_express_bar_id_verified', true) === '1';
            $customer = trim($order->get_formatted_billing_full_name());
            if ($customer === '') {
                $customer = (string) $order->get_billing_email();
            }
            $pickup_name = '';
            $event_names = array();
            $item_lines = array();

            foreach ($order->get_items() as $item_id => $item) {
                unset($item_id);
                if (!$item instanceof WC_Order_Item_Product) {
                    continue;
                }
                if ((string) $item->get_meta('_vms_express_bar', true) !== '1') {
                    continue;
                }
                $item_plan_id = absint($item->get_meta('_vms_express_bar_event_plan_id', true));
                if ($selected_plan_id > 0 && $item_plan_id !== $selected_plan_id) {
                    continue;
                }
                $event_name = (string) $item->get_meta('_vms_express_bar_event_plan_title', true);
                if ($event_name === '' && $item_plan_id > 0) {
                    $event_name = get_the_title($item_plan_id);
                }
                if ($event_name !== '') {
                    $event_names[$event_name] = $event_name;
                }
                if ($pickup_name === '') {
                    $pickup_name = (string) $item->get_meta('_vms_express_bar_pickup_name', true);
                }
                $item_lines[] = sprintf('%s × %d', $item->get_name(), (int) $item->get_quantity());
            }

            if (empty($item_lines)) {
                continue;
            }

            echo '<tr>';
            echo '<td><a href="' . esc_url($order->get_edit_order_url()) . '">#' . (int) $order->get_id() . '</a></td>';
            echo '<td>' . esc_html($order->get_date_created() ? $order->get_date_created()->date_i18n('Y-m-d g:ia') : '') . '</td>';
            echo '<td>' . esc_html($customer) . ($pickup_name !== '' ? '<br /><small>Pickup: ' . esc_html($pickup_name) . '</small>' : '') . '</td>';
            echo '<td>' . esc_html(implode(', ', $event_names)) . '</td>';
            echo '<td>' . esc_html(implode(' | ', $item_lines)) . '</td>';
            echo '<td>' . esc_html(ucfirst($queue_status)) . '</td>';
            echo '<td>' . ($id_verified ? 'Yes' : 'No') . '</td>';
            echo '<td>';
            echo vms_express_bar_action_form($order->get_id(), 'ready', __('Mark Ready', 'vms')) . ' ';
            echo vms_express_bar_action_form($order->get_id(), 'completed', __('Mark Completed', 'vms')) . ' ';
            echo vms_express_bar_action_form($order->get_id(), 'verify_id', $id_verified ? __('Undo ID Check', 'vms') : __('ID Verified', 'vms'));
            echo '</td>';
            echo '</tr>';
        }

        if (!$found) {
            echo '<tr><td colspan="8">No Express Bar orders match this filter.</td></tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }
}

if (!function_exists('vms_express_bar_action_form')) {
    function vms_express_bar_action_form(int $order_id, string $action_name, string $label): string
    {
        $url = admin_url('admin-post.php');
        ob_start();
        ?>
        <form method="post" action="<?php echo esc_url($url); ?>" style="display:inline-block; margin:0 4px 4px 0;">
            <input type="hidden" name="action" value="vms_express_bar_update_order" />
            <input type="hidden" name="order_id" value="<?php echo (int) $order_id; ?>" />
            <input type="hidden" name="queue_action" value="<?php echo esc_attr($action_name); ?>" />
            <?php wp_nonce_field('vms_express_bar_update_order_' . $order_id . '_' . $action_name, 'vms_express_bar_order_nonce'); ?>
            <button type="submit" class="button button-secondary"><?php echo esc_html($label); ?></button>
        </form>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('vms_express_bar_handle_order_update')) {
    function vms_express_bar_handle_order_update(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Not allowed.', 'vms'));
        }
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $queue_action = isset($_POST['queue_action']) ? sanitize_key(wp_unslash($_POST['queue_action'])) : '';
        $nonce = isset($_POST['vms_express_bar_order_nonce']) ? sanitize_text_field(wp_unslash($_POST['vms_express_bar_order_nonce'])) : '';
        if ($order_id <= 0 || !wp_verify_nonce($nonce, 'vms_express_bar_update_order_' . $order_id . '_' . $queue_action)) {
            wp_die(esc_html__('Invalid request.', 'vms'));
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_die(esc_html__('Order not found.', 'vms'));
        }

        switch ($queue_action) {
            case 'ready':
                $order->update_meta_data('_vms_express_bar_queue_status', 'ready');
                break;
            case 'completed':
                $order->update_meta_data('_vms_express_bar_queue_status', 'completed');
                break;
            case 'verify_id':
                $current = (string) $order->get_meta('_vms_express_bar_id_verified', true) === '1';
                $order->update_meta_data('_vms_express_bar_id_verified', $current ? '0' : '1');
                break;
        }

        $order->save();
        wp_safe_redirect(admin_url('admin.php?page=vms-express-bar'));
        exit;
    }
}
