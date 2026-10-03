<?php
defined('ABSPATH') || exit;

add_action('admin_notices', 'vmseb_admin_notices');
add_action('add_meta_boxes_vms_event_plan', 'vmseb_add_metabox');
add_action('save_post_vms_event_plan', 'vmseb_save_metabox', 20, 2);
add_action('admin_menu', 'vmseb_admin_menu', 40);
add_filter('vms_admin_ui_nav_cluster_items', 'vmseb_register_planning_nav_items', 40, 3);
add_action('admin_post_vmseb_save_bar_menu', 'vmseb_handle_save_bar_menu');
add_action('admin_post_vmseb_update_order', 'vmseb_handle_order_update');
add_action('admin_post_vmseb_create_public_page', 'vmseb_handle_create_public_page');
add_action('admin_post_vmseb_refresh_catalog_registry', 'vmseb_handle_refresh_catalog_registry');
add_action('admin_enqueue_scripts', 'vmseb_admin_enqueue_assets');
add_action('wp_ajax_vmseb_save_bar_menu_row', 'vmseb_handle_save_bar_menu_row');
add_action('woocommerce_product_options_general_product_data', 'vmseb_render_woo_product_membership_control');
add_action('woocommerce_process_product_meta', 'vmseb_save_woo_product_membership_control', 30, 2);
add_action('woocommerce_variation_options_inventory', 'vmseb_render_woo_variation_membership_control', 30, 3);
add_action('woocommerce_save_product_variation', 'vmseb_save_woo_variation_membership_control', 30, 2);

if (!function_exists('vmseb_admin_page_hooks')) {
    /**
     * Store the opaque hook suffixes returned by WordPress for Express Bar pages.
     *
     * @param array<string,string>|null $replace
     * @return array<string,string>
     */
    function vmseb_admin_page_hooks(?array $replace = null): array
    {
        static $hooks = array();

        if (is_array($replace)) {
            $hooks = array();
            foreach ($replace as $page => $hook) {
                $page = sanitize_key((string) $page);
                $hook = is_string($hook) ? trim($hook) : '';
                if ($page !== '' && $hook !== '') {
                    $hooks[$page] = $hook;
                }
            }
        }

        return $hooks;
    }
}

if (!function_exists('vmseb_admin_enqueue_assets')) {
    function vmseb_admin_enqueue_assets(string $hook_suffix): void
    {
        if (!in_array($hook_suffix, array_values(vmseb_admin_page_hooks()), true)) {
            return;
        }
        wp_enqueue_style('vmseb-admin', VMSEB_URL . 'assets/css/admin.css', array(), VMSEB_VERSION);
        wp_enqueue_script('vmseb-admin', VMSEB_URL . 'assets/js/admin.js', array(), VMSEB_VERSION, true);
        wp_localize_script('vmseb-admin', 'vmsebAdmin', array(
            'ajaxUrl'      => admin_url('admin-ajax.php'),
            'rowSaveNonce' => wp_create_nonce('vmseb_save_bar_menu_row'),
            'messages'     => array(
                'saveRow'       => __('Save Row', 'vms-express-bar'),
                'savingRow'     => __('Saving...', 'vms-express-bar'),
                'rowSaved'      => __('Saved.', 'vms-express-bar'),
                'rowUnchanged'  => __('No unsaved changes.', 'vms-express-bar'),
                'rowSaveFailed' => __('Could not save this row.', 'vms-express-bar'),
            ),
        ));
    }
}

if (!function_exists('vmseb_admin_notices')) {
    function vmseb_admin_notices(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        if (!vmseb_is_vms_active()) {
            echo '<div class="notice notice-warning"><p>VMS Express Bar requires the VMS core plugin to be active.</p></div>';
            return;
        }
        if (!vmseb_is_woocommerce_active()) {
            echo '<div class="notice notice-warning"><p>VMS Express Bar requires WooCommerce to be active.</p></div>';
        }
    }
}

if (!function_exists('vmseb_add_metabox')) {
    function vmseb_add_metabox(): void
    {
        if (!vmseb_is_vms_active()) {
            return;
        }
        add_meta_box('vmseb_express_bar', __('Express Bar', 'vms-express-bar'), 'vmseb_render_metabox', 'vms_event_plan', 'side', 'default');
    }
}

if (!function_exists('vmseb_render_metabox')) {
    function vmseb_render_metabox(WP_Post $post): void
    {
        $cfg = vmseb_get_event_meta($post->ID);
        $shortcode = '[vms_express_bar_menu event_plan_id="' . (int) $post->ID . '"]';
        wp_nonce_field('vmseb_save_' . $post->ID, 'vmseb_nonce');
        ?>
        <div class="vmseb-metabox">
            <p><strong><?php echo esc_html__('Express Bar Menu:', 'vms-express-bar'); ?></strong> <a href="<?php echo esc_url(admin_url('admin.php?page=vms-bar-menu')); ?>"><?php echo esc_html__('Manage Bar Menu', 'vms-express-bar'); ?></a></p>
            <p><label><input type="checkbox" name="vms_express_bar_enabled" value="1" <?php checked(!empty($cfg['enabled'])); ?> /> <?php echo esc_html__('Enable Express Bar for this event.', 'vms-express-bar'); ?></label></p>
            <p>
                <label for="vmseb_open_at"><strong><?php echo esc_html__('Ordering opens', 'vms-express-bar'); ?></strong></label><br />
                <input type="datetime-local" class="widefat" id="vmseb_open_at" name="vmseb_open_at" value="<?php echo esc_attr((string) $cfg['opens_at']); ?>" />
                <small><?php echo esc_html__('Leave blank to use the site default when automatic defaults are enabled; otherwise the menu stays browse-only until an opening time is set.', 'vms-express-bar'); ?></small>
            </p>
            <p>
                <label for="vmseb_close_at"><strong><?php echo esc_html__('Ordering closes', 'vms-express-bar'); ?></strong></label><br />
                <input type="datetime-local" class="widefat" id="vmseb_close_at" name="vmseb_close_at" value="<?php echo esc_attr((string) $cfg['closes_at']); ?>" />
                <small><?php echo esc_html__('Leave blank to use the site default when automatic defaults are enabled; otherwise ordering closes at the end of the event day.', 'vms-express-bar'); ?></small>
            </p>
            <p>
                <label for="vms_express_bar_headline"><strong><?php echo esc_html__('Customer headline override', 'vms-express-bar'); ?></strong></label><br />
                <input type="text" class="widefat" id="vms_express_bar_headline" name="vms_express_bar_headline" value="<?php echo esc_attr((string) get_post_meta($post->ID, '_vms_express_bar_headline', true)); ?>" />
            </p>
            <p>
                <label for="vms_express_bar_pickup_instructions"><strong><?php echo esc_html__('Pickup / ID instructions override', 'vms-express-bar'); ?></strong></label><br />
                <textarea class="widefat" rows="4" id="vms_express_bar_pickup_instructions" name="vms_express_bar_pickup_instructions"><?php echo esc_textarea((string) get_post_meta($post->ID, '_vms_express_bar_pickup_instructions', true)); ?></textarea>
            </p>
            <p><strong><?php echo esc_html__('Shortcode', 'vms-express-bar'); ?></strong><br /><code><?php echo esc_html($shortcode); ?></code></p>
            <p><small><?php echo esc_html__('Express Bar uses the shared Bar Menu. Customers can access it through the dedicated Express Bar page and the ticket-purchase flow when ordering is available.', 'vms-express-bar'); ?></small></p>
        </div>
        <?php
    }
}

if (!function_exists('vmseb_save_metabox')) {
    function vmseb_save_metabox(int $post_id, WP_Post $post): void
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
        $nonce = isset($_POST['vmseb_nonce']) ? sanitize_text_field(wp_unslash($_POST['vmseb_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'vmseb_save_' . $post_id)) {
            return;
        }

        update_post_meta($post_id, '_vms_express_bar_enabled', !empty($_POST['vms_express_bar_enabled']) ? '1' : '0');
        if (isset($_POST['vmseb_auto_embed'])) {
            update_post_meta($post_id, '_vmseb_auto_embed', !empty($_POST['vmseb_auto_embed']) ? '1' : '0');
        }
        update_post_meta($post_id, '_vmseb_open_at', isset($_POST['vmseb_open_at']) ? sanitize_text_field(wp_unslash($_POST['vmseb_open_at'])) : '');
        update_post_meta($post_id, '_vmseb_close_at', isset($_POST['vmseb_close_at']) ? sanitize_text_field(wp_unslash($_POST['vmseb_close_at'])) : '');
        update_post_meta($post_id, '_vms_express_bar_headline', isset($_POST['vms_express_bar_headline']) ? sanitize_text_field(wp_unslash($_POST['vms_express_bar_headline'])) : '');
        update_post_meta($post_id, '_vms_express_bar_pickup_instructions', isset($_POST['vms_express_bar_pickup_instructions']) ? sanitize_textarea_field(wp_unslash($_POST['vms_express_bar_pickup_instructions'])) : '');
        vmseb_invalidate_event_plan_public_cache($post_id);
    }
}

if (!function_exists('vmseb_admin_menu')) {
    function vmseb_admin_menu(): void
    {
        $parent = vmseb_parent_menu_slug();
        $express_hook = add_submenu_page($parent, __('Express Bar', 'vms-express-bar'), __('Express Bar', 'vms-express-bar'), 'manage_options', 'vms-express-bar', 'vmseb_render_queue_page');
        $bar_menu_hook = add_submenu_page($parent, __('Bar Menu', 'vms-express-bar'), __('Bar Menu', 'vms-express-bar'), 'manage_options', 'vms-bar-menu', 'vmseb_render_bar_menu_page');

        vmseb_admin_page_hooks(array(
            'vms-express-bar' => is_string($express_hook) ? $express_hook : '',
            'vms-bar-menu' => is_string($bar_menu_hook) ? $bar_menu_hook : '',
        ));
    }
}

if (!function_exists('vmseb_register_planning_nav_items')) {
    function vmseb_register_planning_nav_items(array $items, string $cluster_key, array $cluster): array
    {
        unset($cluster);
        if ($cluster_key !== 'planning' || !vmseb_is_vms_active()) {
            return $items;
        }
        $adds = array(
            array('label' => 'Express Bar', 'url' => admin_url('admin.php?page=vms-express-bar')),
            array('label' => 'Bar Menu', 'url' => admin_url('admin.php?page=vms-bar-menu')),
        );
        $existing = array();
        foreach ($items as $item) {
            if (is_array($item) && !empty($item['url'])) {
                $existing[(string) $item['url']] = true;
            }
        }
        foreach ($adds as $item) {
            if (empty($existing[$item['url']])) {
                $items[] = $item;
            }
        }
        return $items;
    }
}

if (!function_exists('vmseb_log_bar_menu_save')) {
    function vmseb_log_bar_menu_save(string $event, array $context = array()): void
    {
        $payload = array_merge(array(
            'event' => $event,
            'time'  => gmdate('c'),
        ), $context);
        error_log('[VMSEB Bar Menu] ' . wp_json_encode($payload));
    }
}

if (!function_exists('vmseb_get_persisted_registry')) {
    function vmseb_get_persisted_registry(): array
    {
        $stored = get_option('vmseb_catalog_registry', array());
        return is_array($stored) ? $stored : array();
    }
}

if (!function_exists('vmseb_build_admin_search_value')) {
    function vmseb_build_admin_search_value(array $item): string
    {
        $product_id = absint($item['product_id'] ?? 0);
        $variation_id = absint($item['variation_id'] ?? 0);
        $kind_label = ($item['kind'] ?? '') === 'variation' ? 'variation' : 'simple product';
        $parts = array(
            (string) ($item['title'] ?? ''),
            (string) ($item['default_category'] ?? $item['category'] ?? ''),
            (string) ($item['sku'] ?? ''),
            (string) ($item['source_label'] ?? ''),
            $kind_label,
            (string) ($item['token'] ?? ''),
            $product_id > 0 ? 'product ' . $product_id : '',
            $variation_id > 0 ? 'variation ' . $variation_id : '',
        );

        return trim(implode(' ', array_values(array_unique(array_filter($parts, static function(string $value): bool {
            return trim($value) !== '';
        })))));
    }
}

if (!function_exists('vmseb_default_registry_row_for_candidate')) {
    function vmseb_default_registry_row_for_candidate(array $candidate): array
    {
        return array(
            'enabled'            => 0,
            'category'           => trim((string) ($candidate['default_category'] ?? '')),
            'sort'               => 0,
            'bucket_eligible'    => 0,
            'age_gate'           => vmseb_default_age_gate_for_candidate($candidate),
            'online_cap'         => 0,
            'bar_only_threshold' => 0,
        );
    }
}

if (!function_exists('vmseb_build_registry_identifier')) {
    function vmseb_build_registry_identifier(string $token, array $submitted_row = array(), ?array $candidate = null): array
    {
        $parsed = vmseb_parse_catalog_token($token);
        $product_id = isset($submitted_row['product_id']) ? absint($submitted_row['product_id']) : 0;
        $variation_id = isset($submitted_row['variation_id']) ? absint($submitted_row['variation_id']) : 0;

        if ($candidate) {
            $product_id = (int) ($candidate['product_id'] ?? $product_id);
            $variation_id = (int) ($candidate['variation_id'] ?? $variation_id);
        } elseif ($parsed) {
            if ($parsed['kind'] === 'product' && $product_id <= 0) {
                $product_id = (int) $parsed['id'];
            }
            if ($parsed['kind'] === 'variation' && $variation_id <= 0) {
                $variation_id = (int) $parsed['id'];
            }
        }

        return array(
            'token'        => $token,
            'product_id'   => $product_id,
            'variation_id' => $variation_id,
        );
    }
}

if (!function_exists('vmseb_merge_registry_row_payload')) {
    function vmseb_merge_registry_row_payload(array $base_row, array $submitted_row, array $candidate): array
    {
        $row = $base_row;
        $valid_fields = array();

        if (array_key_exists('enabled', $submitted_row)) {
            $row['enabled'] = !empty($submitted_row['enabled']) ? 1 : 0;
            $valid_fields[] = 'enabled';
        }
        if (array_key_exists('bucket_eligible', $submitted_row)) {
            $row['bucket_eligible'] = !empty($submitted_row['bucket_eligible']) ? 1 : 0;
            $valid_fields[] = 'bucket_eligible';
        }
        if (array_key_exists('age_gate', $submitted_row)) {
            $row['age_gate'] = !empty($submitted_row['age_gate']) ? 1 : 0;
            $valid_fields[] = 'age_gate';
        }
        if (array_key_exists('sort', $submitted_row)) {
            $row['sort'] = (int) $submitted_row['sort'];
            $valid_fields[] = 'sort';
        }
        if (array_key_exists('online_cap', $submitted_row)) {
            $row['online_cap'] = max(0, absint($submitted_row['online_cap']));
            $valid_fields[] = 'online_cap';
        }
        if (array_key_exists('bar_only_threshold', $submitted_row)) {
            $row['bar_only_threshold'] = max(0, absint($submitted_row['bar_only_threshold']));
            $valid_fields[] = 'bar_only_threshold';
        }
        if (trim((string) ($row['category'] ?? '')) === '') {
            $row['category'] = trim((string) ($candidate['default_category'] ?? ''));
        }

        return array(
            'row'          => $row,
            'valid_fields' => $valid_fields,
        );
    }
}

if (!function_exists('vmseb_decode_registry_changes_payload')) {
    function vmseb_decode_registry_changes_payload(string $raw_payload): array
    {
        $raw_payload = trim($raw_payload);
        if ($raw_payload === '') {
            return array(
                'rows'  => array(),
                'error' => '',
            );
        }

        $decoded = json_decode($raw_payload, true);
        if (!is_array($decoded)) {
            return array(
                'rows'  => array(),
                'error' => 'invalid_json',
            );
        }

        $rows = array();
        foreach ($decoded as $key => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $token = isset($entry['token']) ? trim((string) $entry['token']) : '';
            if ($token === '' && is_string($key)) {
                $token = trim($key);
            }
            if ($token === '') {
                continue;
            }
            $entry['token'] = $token;
            $rows[$token] = $entry;
        }

        return array(
            'rows'  => $rows,
            'error' => '',
        );
    }
}

if (!function_exists('vmseb_save_bar_menu_settings_from_request')) {
    function vmseb_save_bar_menu_settings_from_request(array $request): void
    {
        $settings = vmseb_get_settings();
        $settings['headline'] = isset($request['headline']) ? sanitize_text_field(wp_unslash($request['headline'])) : $settings['headline'];
        $settings['pickup_instructions'] = isset($request['pickup_instructions']) ? sanitize_textarea_field(wp_unslash($request['pickup_instructions'])) : $settings['pickup_instructions'];
        $settings['bucket_discount_label'] = isset($request['bucket_discount_label']) ? sanitize_text_field(wp_unslash($request['bucket_discount_label'])) : $settings['bucket_discount_label'];
        $settings['bucket_emoji'] = isset($request['bucket_emoji']) ? sanitize_text_field(wp_unslash($request['bucket_emoji'])) : $settings['bucket_emoji'];
        $settings['age_gate_enabled'] = !empty($request['age_gate_enabled']) ? 1 : 0;
        $settings['age_gate_title'] = isset($request['age_gate_title']) ? sanitize_text_field(wp_unslash($request['age_gate_title'])) : $settings['age_gate_title'];
        $settings['age_gate_message'] = isset($request['age_gate_message']) ? sanitize_textarea_field(wp_unslash($request['age_gate_message'])) : $settings['age_gate_message'];
        $settings['age_gate_min_age'] = isset($request['age_gate_min_age']) ? max(1, min(99, absint($request['age_gate_min_age']) ?: 21)) : $settings['age_gate_min_age'];
        $settings['default_window_auto_apply'] = !empty($request['default_window_auto_apply']) ? 1 : 0;
        $settings['default_window_open_minutes_before'] = isset($request['default_window_open_minutes_before']) ? max(0, min(10080, absint($request['default_window_open_minutes_before']))) : $settings['default_window_open_minutes_before'];
        $settings['default_window_close_minutes_before'] = isset($request['default_window_close_minutes_before']) ? max(0, min(1440, absint($request['default_window_close_minutes_before']))) : $settings['default_window_close_minutes_before'];
        $settings['tips_enabled'] = !empty($request['tips_enabled']) ? 1 : 0;
        if (isset($request['tips_max_custom_amount'])) {
            $tips_max = (float) sanitize_text_field(wp_unslash($request['tips_max_custom_amount']));
            $settings['tips_max_custom_amount'] = $tips_max > 0 ? min(500.0, $tips_max) : 100.0;
        }

        if (isset($request['public_page_id'])) {
            vmseb_set_public_page_id(absint($request['public_page_id']));
        }

        update_option('vmseb_settings', $settings, false);
        vmseb_invalidate_dedicated_public_cache();
    }
}

if (!function_exists('vmseb_save_registry_rows')) {
    function vmseb_save_registry_rows(array $submitted_rows, string $context = 'bulk'): array
    {
        $registry = vmseb_get_registry();
        $new_registry = $registry;
        $submitted = array();
        $saved = array();
        $skipped = array();
        $failures = array();
        $prepared_rows = array();
        $prepared_identifiers = array();

        foreach ($submitted_rows as $token => $submitted_row) {
            $token = trim((string) $token);
            $submitted_row = is_array($submitted_row) ? $submitted_row : array();
            $identifier = vmseb_build_registry_identifier($token, $submitted_row);
            $submitted[] = $identifier;

            $parsed = vmseb_parse_catalog_token($token);
            if (!$parsed) {
                $skipped[] = $identifier;
                $failures[] = array('token' => $token, 'reason' => 'invalid_token');
                continue;
            }

            if ($parsed['kind'] === 'product') {
                $submitted_product_id = absint($submitted_row['product_id'] ?? 0);
                if ($submitted_product_id > 0 && $submitted_product_id !== (int) $parsed['id']) {
                    $skipped[] = $identifier;
                    $failures[] = array('token' => $token, 'reason' => 'submitted_product_id_mismatch');
                    continue;
                }
            } else {
                $submitted_variation_id = absint($submitted_row['variation_id'] ?? 0);
                if ($submitted_variation_id > 0 && $submitted_variation_id !== (int) $parsed['id']) {
                    $skipped[] = $identifier;
                    $failures[] = array('token' => $token, 'reason' => 'submitted_variation_id_mismatch');
                    continue;
                }
            }

            $candidate = vmseb_get_candidate_by_token($token);
            if (!$candidate) {
                $skipped[] = $identifier;
                $failures[] = array('token' => $token, 'reason' => 'candidate_not_found');
                continue;
            }

            $identifier = vmseb_build_registry_identifier($token, $submitted_row, $candidate);
            if (!empty($submitted_row['product_id']) && (int) $submitted_row['product_id'] !== (int) ($candidate['product_id'] ?? 0)) {
                $skipped[] = $identifier;
                $failures[] = array('token' => $token, 'reason' => 'candidate_product_id_mismatch');
                continue;
            }
            if (!empty($submitted_row['variation_id']) && (int) ($candidate['variation_id'] ?? 0) > 0 && (int) $submitted_row['variation_id'] !== (int) ($candidate['variation_id'] ?? 0)) {
                $skipped[] = $identifier;
                $failures[] = array('token' => $token, 'reason' => 'candidate_variation_id_mismatch');
                continue;
            }

            $base_row = isset($new_registry[$token]) && is_array($new_registry[$token])
                ? $new_registry[$token]
                : vmseb_default_registry_row_for_candidate($candidate);
            $merged = vmseb_merge_registry_row_payload($base_row, $submitted_row, $candidate);
            if (empty($merged['valid_fields'])) {
                $skipped[] = $identifier;
                $failures[] = array('token' => $token, 'reason' => 'no_valid_fields_submitted');
                continue;
            }

            $new_registry[$token] = $merged['row'];
            $prepared_rows[$token] = $merged['row'];
            $prepared_identifiers[$token] = $identifier;
        }

        $persisted = true;
        $updated = false;
        if ($new_registry !== $registry) {
            $persisted = update_option('vmseb_catalog_registry', $new_registry, false);
            $updated = $persisted;
        }

        if ($persisted) {
            $saved = array_values($prepared_identifiers);
            if ($updated) {
                vmseb_get_registry(true);
                vmseb_invalidate_dedicated_public_cache();
            }
        } elseif (!empty($prepared_identifiers)) {
            foreach ($prepared_identifiers as $token => $identifier) {
                $failures[] = array('token' => $token, 'reason' => 'update_option_failed');
                $prepared_rows[$token] = array();
            }
        }

        $result = array(
            'submitted' => array_values($submitted),
            'saved'     => array_values($saved),
            'skipped'   => array_values($skipped),
            'failures'  => array_values($failures),
            'rows'      => $prepared_rows,
            'updated'   => $updated,
            'persisted' => $persisted,
        );

        vmseb_log_bar_menu_save('registry_save_' . $context, array(
            'submitted_product_ids' => $result['submitted'],
            'saved_product_ids'     => $result['saved'],
            'skipped_product_ids'   => $result['skipped'],
            'save_failures'         => $result['failures'],
        ));

        return $result;
    }
}

if (!function_exists('vmseb_render_bar_menu_page')) {
    function vmseb_render_bar_menu_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $settings = vmseb_get_settings();
        $items = vmseb_get_catalog_items(false);
        $persisted_registry = vmseb_get_persisted_registry();
        $enabled_count = count(array_filter($items, static function(array $item): bool {
            return !empty($item['enabled']);
        }));
        $configured_count = count(array_filter(array_keys($items), static function(string $token) use ($persisted_registry): bool {
            return isset($persisted_registry[$token]) && is_array($persisted_registry[$token]);
        }));
        $unconfigured_count = max(0, count($items) - $configured_count);
        $public_page_id = vmseb_find_public_page_id(true);
        $public_page_url = $public_page_id > 0 ? get_permalink($public_page_id) : '';
        $public_page_has_shortcode = $public_page_id > 0 ? vmseb_page_contains_shortcode($public_page_id) : false;
        $page_choices = get_posts(array(
            'post_type'        => 'page',
            'post_status'      => array('publish', 'draft', 'pending', 'private', 'future'),
            'posts_per_page'   => 200,
            'orderby'          => 'title',
            'order'            => 'ASC',
            'suppress_filters' => false,
            'no_found_rows'    => true,
        ));
        $prune_report = vmseb_get_registry_prune_report();

        echo '<div class="wrap vmseb-admin-page">';
        echo '<h1>Bar Menu</h1>';
        echo '<p>Build the public Express Bar menu from your Woo catalog. Express Bar does not duplicate product names, prices, images, or categories. Product truth stays in Woo/Square; Express Bar only controls event-specific availability and operational flags.</p>';
        if (!empty($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Bar Menu settings saved.</p></div>';
        }
        if (!empty($_GET['vmseb_page_setup'])) {
            if (sanitize_key((string) $_GET['vmseb_page_setup']) === 'ready') {
                echo '<div class="notice notice-success is-dismissible"><p>Express Bar page is ready.</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>Express Bar page could not be created. Please create a page manually and add <code>[vms_express_bar_menu]</code>.</p></div>';
            }
        }
        if (!empty($_GET['vmseb_refreshed'])) {
            $removed = absint($_GET['vmseb_refreshed']);
            echo '<div class="notice notice-success is-dismissible"><p>Catalog cleanup complete. Removed ' . (int) $removed . ' deleted/unavailable configured item' . ($removed === 1 ? '' : 's') . '.</p></div>';
        }

        echo '<div class="vmseb-admin-card vmseb-public-page-card">';
        echo '<h2>Public Express Bar Page</h2>';
        if ($public_page_id > 0 && $public_page_url) {
            echo '<p><strong>Current page:</strong> <a href="' . esc_url($public_page_url) . '" target="_blank" rel="noopener">' . esc_html(get_the_title($public_page_id)) . '</a></p>';
            if ($public_page_has_shortcode) {
                echo '<p class="vmseb-good">Shortcode detected. This page is ready for the full Express Bar menu.</p>';
            } else {
                echo '<p class="vmseb-warn">Page found, but the shortcode <code>[vms_express_bar_menu]</code> was not detected. Add the shortcode to that page or choose another page below.</p>';
            }
        } else {
            echo '<p><strong>No Express Bar page is assigned yet.</strong> Create one automatically or choose an existing page below.</p>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="vmseb_create_public_page" />';
            wp_nonce_field('vmseb_create_public_page', 'vmseb_create_public_page_nonce');
            submit_button('Create Express Bar Page', 'secondary', 'submit', false);
            echo '</form>';
        }
        echo '</div>';

        echo '<div class="vmseb-admin-card">';
        echo '<h2>Catalog Cleanup</h2>';
        echo '<p>Use this after deleting or trashing Woo products. It only removes dead configured rows; new eligible Woo products already appear automatically below as disabled and not configured.</p>';
        echo '<p><strong>Registry rows:</strong> ' . (int) $prune_report['total'] . ' &nbsp; <strong>Deleted/unavailable:</strong> ' . (int) $prune_report['stale'] . '</p>';
        echo '<form class="vmseb-action-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="vmseb_refresh_catalog_registry" />';
        wp_nonce_field('vmseb_refresh_catalog_registry');
        submit_button('Remove deleted products', 'secondary', 'submit', false);
        echo '</form>';
        echo '</div>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" data-vmseb-bar-menu-form>';
        echo '<input type="hidden" name="action" value="vmseb_save_bar_menu" />';
        wp_nonce_field('vmseb_save_bar_menu', 'vmseb_bar_menu_nonce');
        echo '<input type="hidden" name="vmseb_registry_changes" value="" data-vmseb-registry-changes />';

        echo '<h2 class="title">Dedicated Page</h2>';
        echo '<table class="form-table" role="presentation">';
        echo '<tr><th scope="row"><label for="vmseb_public_page_id">Express Bar page</label></th><td><select id="vmseb_public_page_id" name="public_page_id">';
        echo '<option value="0">Auto-detect / not assigned</option>';
        foreach ($page_choices as $page_choice) {
            if (!$page_choice instanceof WP_Post) {
                continue;
            }
            echo '<option value="' . (int) $page_choice->ID . '" ' . selected($public_page_id, (int) $page_choice->ID, false) . '>' . esc_html($page_choice->post_title . ' (' . $page_choice->post_status . ')') . '</option>';
        }
        echo '</select><p class="description">The selected page should contain <code>[vms_express_bar_menu]</code>. Event pages now point customers here instead of embedding the full menu inline.</p></td></tr>';
        echo '</table>';

        echo '<h2 class="title">Public Copy &amp; Messaging</h2>';
        echo '<table class="form-table" role="presentation">';
        echo '<tr><th scope="row"><label for="vmseb_headline">Default headline</label></th><td><input type="text" class="regular-text" id="vmseb_headline" name="headline" value="' . esc_attr($settings['headline']) . '" /></td></tr>';
        echo '<tr><th scope="row"><label for="vmseb_pickup_instructions">Pickup / ID instructions</label></th><td><textarea class="large-text" rows="3" id="vmseb_pickup_instructions" name="pickup_instructions">' . esc_textarea($settings['pickup_instructions']) . '</textarea></td></tr>';
        echo '<tr><th scope="row"><label for="vmseb_discount_message">Checkout discount message</label></th><td><input type="text" class="regular-text" id="vmseb_discount_message" name="bucket_discount_label" value="' . esc_attr($settings['bucket_discount_label']) . '" /><p class="description">Shown on the public builder. Express Bar does not calculate discounts; Woo/VMS Discounts handles final pricing.</p></td></tr>';
        echo '<tr><th scope="row"><label for="vmseb_bucket_emoji">Bucket eligible emoji</label></th><td><input type="text" class="small-text" id="vmseb_bucket_emoji" name="bucket_emoji" value="' . esc_attr($settings['bucket_emoji']) . '" maxlength="8" /><p class="description">Shown next to bucket-eligible items and in the checkout note.</p></td></tr>';
        echo '<tr><th scope="row">Birthday gate</th><td><label><input type="checkbox" name="age_gate_enabled" value="1" ' . checked(!empty($settings['age_gate_enabled']), true, false) . ' /> Ask for birthday before customers can continue with age-gated items.</label><p class="description">This is a reminder and deterrent only. Staff still checks ID at pickup.</p></td></tr>';
        echo '<tr><th scope="row"><label for="vmseb_age_gate_title">Birthday gate title</label></th><td><input type="text" class="regular-text" id="vmseb_age_gate_title" name="age_gate_title" value="' . esc_attr($settings['age_gate_title']) . '" /></td></tr>';
        echo '<tr><th scope="row"><label for="vmseb_age_gate_message">Birthday gate message</label></th><td><textarea class="large-text" rows="2" id="vmseb_age_gate_message" name="age_gate_message">' . esc_textarea($settings['age_gate_message']) . '</textarea></td></tr>';
        echo '<tr><th scope="row"><label for="vmseb_age_gate_min_age">Minimum age</label></th><td><input type="number" min="1" max="99" step="1" id="vmseb_age_gate_min_age" name="age_gate_min_age" value="' . (int) $settings['age_gate_min_age'] . '" /></td></tr>';
        echo '</table>';

        echo '<h2 class="title">Default Ordering Window</h2>';
        echo '<table class="form-table" role="presentation">';
        echo '<tr><th scope="row">Automatic defaults</th><td><label><input type="checkbox" name="default_window_auto_apply" value="1" ' . checked(!empty($settings['default_window_auto_apply']), true, false) . ' /> Use the site default window when an event has no explicit Express Bar open/close time.</label><p class="description">Explicit per-event times always win.</p></td></tr>';
        echo '<tr><th scope="row"><label for="vmseb_default_open_before">Open before event</label></th><td><input type="number" min="0" max="10080" step="1" id="vmseb_default_open_before" name="default_window_open_minutes_before" value="' . (int) $settings['default_window_open_minutes_before'] . '" /> minutes<p class="description">2880 minutes = 48 hours before showtime.</p></td></tr>';
        echo '<tr><th scope="row"><label for="vmseb_default_close_before">Close before event</label></th><td><input type="number" min="0" max="1440" step="1" id="vmseb_default_close_before" name="default_window_close_minutes_before" value="' . (int) $settings['default_window_close_minutes_before'] . '" /> minutes<p class="description">0 closes at showtime, which preserves the current presales-only safe default.</p></td></tr>';
        echo '</table>';

        echo '<h2 class="title">Checkout Tips</h2>';
        echo '<table class="form-table" role="presentation">';
        echo '<tr><th scope="row">Bar staff tip</th><td><label><input type="checkbox" name="tips_enabled" value="1" ' . checked(!empty($settings['tips_enabled']), true, false) . ' /> Offer an optional tip on checkout when the cart contains Express Bar items.</label><p class="description">Uses 15%, 20%, 25%, Custom, and No tip. Percentages apply only to Express Bar line totals.</p></td></tr>';
        echo '<tr><th scope="row"><label for="vmseb_tip_max">Maximum custom tip</label></th><td><input type="number" min="1" max="500" step="0.01" id="vmseb_tip_max" name="tips_max_custom_amount" value="' . esc_attr((string) $settings['tips_max_custom_amount']) . '" /><p class="description">Safety cap for accidental custom entries.</p></td></tr>';
        echo '</table>';

        echo '<h2 class="title">Item Registry</h2>';
        echo '<p>Only items checked <strong>Show in Express Bar</strong> will appear on the public Express Bar page. The displayed Express Bar section comes from the current WooCommerce product category. Use Express Bar here only for operational flags like show/hide, bucket eligibility, age gate, sort, online caps, and low-stock bar-only thresholds. <a href="' . esc_url(admin_url('edit-tags.php?taxonomy=product_cat&post_type=product')) . '">Manage Woo categories</a>.</p>';
        echo '<p class="vmseb-registry-summary"><strong>Enabled in Express Bar:</strong> ' . (int) $enabled_count . ' &nbsp; <strong>Total eligible Woo items:</strong> ' . (int) count($items) . ' &nbsp; <strong>Not configured:</strong> ' . (int) $unconfigured_count . '</p>';
        echo '<p class="description">Use <strong>Save Row</strong> to persist one product at a time. <strong>Save Bar Menu</strong> remains available as a fallback and only submits rows with unsaved changes.</p>';
        echo '<noscript><p class="vmseb-warn">JavaScript is required for row-level item saves and changed-row bulk submission.</p></noscript>';
        echo '<p><label for="vmseb-registry-search"><strong>Filter items</strong></label><br /><input type="search" id="vmseb-registry-search" class="regular-text" placeholder="Search catalog..." /></p>';
        echo '<div class="vmseb-registry-wrap">';
        echo '<table class="widefat striped vmseb-registry-table"><thead><tr><th>Show in Express Bar</th><th>Item</th><th>Woo Category / Express Bar Section</th><th>Bucket Eligible</th><th>Birthday Gate</th><th>Sort</th><th>Online Cap</th><th>Low Stock → Bar Only</th><th>Stock</th><th>Save</th></tr></thead><tbody>';

        foreach ($items as $token => $item) {
            $availability = vmseb_get_item_online_availability($item);
            $is_configured = isset($persisted_registry[$token]) && is_array($persisted_registry[$token]);
            echo '<tr class="vmseb-registry-row" data-vmseb-row data-token="' . esc_attr($token) . '" data-product-id="' . (int) ($item['product_id'] ?? 0) . '" data-variation-id="' . (int) ($item['variation_id'] ?? 0) . '" data-search="' . esc_attr(vmseb_build_admin_search_value($item)) . '">';
            echo '<td><label><input type="checkbox" value="1" data-vmseb-field="enabled" ' . checked(!empty($item['enabled']), true, false) . ' /> <span class="screen-reader-text">Show ' . esc_html($item['title']) . ' in Express Bar</span></label></td>';
            echo '<td>';
            if (!empty($item['image_url'])) {
                echo '<img class="vmseb-thumb" src="' . esc_url($item['image_url']) . '" alt="" /> ';
            }
            echo '<div class="vmseb-item-title"><strong>' . esc_html($item['title']) . '</strong></div>';
            if (!$is_configured) {
                echo '<div class="vmseb-item-meta"><span class="vmseb-warn"><strong>New — not configured</strong></span></div>';
            }
            echo '<div class="vmseb-item-meta">' . esc_html($item['kind'] === 'variation' ? $item['source_label'] : 'Simple product') . ' • ' . wp_kses_post($item['price_html']) . '</div>';
            echo '</td>';
            echo '<td><span class="vmseb-category-pill">' . esc_html($item['default_category'] !== '' ? $item['default_category'] : '—') . '</span></td>';
            echo '<td><label><input type="checkbox" value="1" data-vmseb-field="bucket_eligible" ' . checked(!empty($item['bucket_eligible']), true, false) . ' /> <span>Yes</span></label></td>';
            echo '<td><label><input type="checkbox" value="1" data-vmseb-field="age_gate" ' . checked(!empty($item['age_gate']), true, false) . ' /> <span>Yes</span></label></td>';
            echo '<td><input type="number" class="small-text" value="' . (int) $item['sort'] . '" data-vmseb-field="sort" /></td>';
            echo '<td><input type="number" class="small-text" min="0" value="' . (int) $item['online_cap'] . '" data-vmseb-field="online_cap" /><div class="description">0 = no cap</div></td>';
            echo '<td><input type="number" class="small-text" min="0" value="' . (int) $item['bar_only_threshold'] . '" data-vmseb-field="bar_only_threshold" /><div class="description">0 = ignore</div></td>';
            echo '<td>';
            if ($item['manage_stock']) {
                echo '<span class="vmseb-stock-pill">' . ($availability['available'] === null ? '—' : (int) $availability['available']) . ' online</span>';
            } else {
                echo '<span class="vmseb-stock-pill">Not stock-managed</span>';
            }
            echo '</td>';
            echo '<td class="vmseb-row-actions"><button type="button" class="button button-secondary" data-vmseb-row-save disabled>Save Row</button><div class="vmseb-row-feedback" data-vmseb-row-feedback aria-live="polite"></div></td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
        echo '<div class="vmseb-form-actions">';
        submit_button('Save Bar Menu', 'primary', 'submit', false);
        echo '<p class="description">This saves the page settings above and only the registry rows that still have unsaved changes.</p>';
        echo '</div>';
        echo '</form></div>';
    }
}

if (!function_exists('vmseb_handle_save_bar_menu')) {
    function vmseb_handle_save_bar_menu(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        $nonce = isset($_POST['vmseb_bar_menu_nonce']) ? sanitize_text_field(wp_unslash($_POST['vmseb_bar_menu_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'vmseb_save_bar_menu')) {
            wp_die('Invalid request');
        }

        vmseb_save_bar_menu_settings_from_request($_POST);

        $submitted_rows = array();
        $raw_changes = isset($_POST['vmseb_registry_changes']) ? (string) wp_unslash($_POST['vmseb_registry_changes']) : '';
        $decoded_changes = vmseb_decode_registry_changes_payload($raw_changes);
        if ($decoded_changes['error'] !== '') {
            vmseb_log_bar_menu_save('registry_save_bulk_decode_error', array(
                'submitted_product_ids' => array(),
                'saved_product_ids'     => array(),
                'skipped_product_ids'   => array(),
                'save_failures'         => array(
                    array('reason' => $decoded_changes['error']),
                ),
            ));
        }
        if (!empty($decoded_changes['rows'])) {
            $submitted_rows = $decoded_changes['rows'];
        } elseif (isset($_POST['registry']) && is_array($_POST['registry'])) {
            foreach ((array) wp_unslash($_POST['registry']) as $token => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $submitted_rows[(string) $token] = array(
                    'token'              => (string) $token,
                    'enabled'            => !empty($row['enabled']) ? 1 : 0,
                    'bucket_eligible'    => !empty($row['bucket_eligible']) ? 1 : 0,
                    'age_gate'           => !empty($row['age_gate']) ? 1 : 0,
                );
                if (isset($row['sort'])) {
                    $submitted_rows[(string) $token]['sort'] = (int) $row['sort'];
                }
                if (isset($row['online_cap'])) {
                    $submitted_rows[(string) $token]['online_cap'] = max(0, absint($row['online_cap']));
                }
                if (isset($row['bar_only_threshold'])) {
                    $submitted_rows[(string) $token]['bar_only_threshold'] = max(0, absint($row['bar_only_threshold']));
                }
            }
        }

        if (!empty($submitted_rows)) {
            vmseb_save_registry_rows($submitted_rows, 'bulk');
        } else {
            vmseb_log_bar_menu_save('registry_save_bulk', array(
                'submitted_product_ids' => array(),
                'saved_product_ids'     => array(),
                'skipped_product_ids'   => array(),
                'save_failures'         => array(),
            ));
        }

        wp_safe_redirect(admin_url('admin.php?page=vms-bar-menu&updated=1'));
        exit;
    }
}

if (!function_exists('vmseb_handle_save_bar_menu_row')) {
    function vmseb_handle_save_bar_menu_row(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized.'), 403);
        }
        check_ajax_referer('vmseb_save_bar_menu_row', 'nonce');

        $token = isset($_POST['token']) ? trim(sanitize_text_field(wp_unslash($_POST['token']))) : '';
        $submitted_row = array(
            'token'              => $token,
            'product_id'         => isset($_POST['product_id']) ? absint(wp_unslash($_POST['product_id'])) : 0,
            'variation_id'       => isset($_POST['variation_id']) ? absint(wp_unslash($_POST['variation_id'])) : 0,
            'enabled'            => !empty($_POST['enabled']) ? 1 : 0,
            'bucket_eligible'    => !empty($_POST['bucket_eligible']) ? 1 : 0,
            'age_gate'           => !empty($_POST['age_gate']) ? 1 : 0,
            'sort'               => isset($_POST['sort']) ? (int) wp_unslash($_POST['sort']) : 0,
            'online_cap'         => isset($_POST['online_cap']) ? max(0, absint(wp_unslash($_POST['online_cap']))) : 0,
            'bar_only_threshold' => isset($_POST['bar_only_threshold']) ? max(0, absint(wp_unslash($_POST['bar_only_threshold']))) : 0,
        );
        $result = vmseb_save_registry_rows(array($token => $submitted_row), 'row');

        if (empty($result['persisted'])) {
            wp_send_json_error(array(
                'message'  => 'Could not save this row. Check the log for details.',
                'failures' => $result['failures'],
            ), 500);
        }
        if (count($result['saved']) < 1) {
            $message = !empty($result['failures']) ? 'This row could not be saved.' : 'No changes were submitted for this row.';
            wp_send_json_error(array(
                'message'  => $message,
                'failures' => $result['failures'],
                'skipped'  => $result['skipped'],
            ), 400);
        }

        $message = !empty($result['updated']) ? 'Row saved.' : 'Row already matched the saved values.';
        wp_send_json_success(array(
            'message' => $message,
            'saved'   => $result['saved'],
        ));
    }
}

if (!function_exists('vmseb_render_woo_product_membership_control')) {
    function vmseb_render_woo_product_membership_control(): void
    {
        global $post;
        if (!($post instanceof WP_Post) || !function_exists('wc_get_product')) {
            return;
        }
        $product = wc_get_product((int) $post->ID);
        if (!$product instanceof WC_Product) {
            return;
        }

        if ($product->is_type('simple') && function_exists('woocommerce_wp_checkbox')) {
            $token = 'p_' . (int) $product->get_id();
            $registry = vmseb_get_registry();
            woocommerce_wp_checkbox(array(
                'id'              => '_vmseb_show_in_express_bar',
                'label'           => __('Show in Express Bar', 'vms-express-bar'),
                'description'     => __('Uses the same global Express Bar Menu registry. Woo catalog visibility is separate.', 'vms-express-bar'),
                'value'           => !empty($registry[$token]['enabled']) ? 'yes' : 'no',
                'checked_value'   => 'yes',
                'unchecked_value' => 'no',
            ));
            return;
        }

        if ($product->is_type('variable')) {
            $bar_menu_url = admin_url('admin.php?page=vms-bar-menu');
            echo '<div class="notice notice-info inline show_if_variable vmseb-variable-guidance">';
            echo '<p><strong>' . esc_html__('Express Bar', 'vms-express-bar') . '</strong></p>';
            echo '<p>' . esc_html__('This is a variable product. Express Bar visibility is managed separately for each variation.', 'vms-express-bar') . '</p>';
            echo '<p>' . esc_html__('Open the Variations tab to configure each variation, or manage the full menu from Bar Menu.', 'vms-express-bar') . '</p>';
            echo '<p><a class="button button-secondary" href="' . esc_url($bar_menu_url) . '">' . esc_html__('Manage Bar Menu', 'vms-express-bar') . '</a></p>';
            echo '</div>';
        }
    }
}

if (!function_exists('vmseb_can_save_woo_membership')) {
    function vmseb_can_save_woo_membership(int $post_id): bool
    {
        if (!current_user_can('edit_post', $post_id)) {
            return false;
        }
        $nonce = isset($_POST['woocommerce_meta_nonce']) ? sanitize_text_field(wp_unslash($_POST['woocommerce_meta_nonce'])) : '';
        return $nonce !== '' && wp_verify_nonce($nonce, 'woocommerce_save_data');
    }
}

if (!function_exists('vmseb_save_membership_enabled_state')) {
    function vmseb_save_membership_enabled_state(string $token, int $product_id, int $variation_id, bool $enabled, string $context): void
    {
        $registry = vmseb_get_registry();
        if (!$enabled && !array_key_exists($token, $registry)) {
            return;
        }
        vmseb_save_registry_rows(array(
            $token => array(
                'token'        => $token,
                'product_id'   => $product_id,
                'variation_id' => $variation_id,
                'enabled'      => $enabled ? 1 : 0,
            ),
        ), $context);
    }
}

if (!function_exists('vmseb_save_woo_product_membership_control')) {
    function vmseb_save_woo_product_membership_control(int $post_id, ?WP_Post $post = null): void
    {
        unset($post);
        // The standard Woo product nonce is verified by vmseb_can_save_woo_membership().
        if (!isset($_POST['_vmseb_show_in_express_bar']) || !vmseb_can_save_woo_membership($post_id)) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return;
        }
        $product = wc_get_product($post_id);
        if (!$product instanceof WC_Product || !$product->is_type('simple')) {
            return;
        }
        $enabled = sanitize_key((string) wp_unslash($_POST['_vmseb_show_in_express_bar'])) === 'yes'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        vmseb_save_membership_enabled_state('p_' . $post_id, $post_id, 0, $enabled, 'woo_product');
    }
}

if (!function_exists('vmseb_render_woo_variation_membership_control')) {
    function vmseb_render_woo_variation_membership_control(int $loop, array $variation_data, WP_Post $variation): void
    {
        unset($variation_data);
        $variation_id = (int) $variation->ID;
        $token = 'v_' . $variation_id;
        $registry = vmseb_get_registry();
        $field_name = 'variable_vmseb_show_in_express_bar[' . $loop . ']';
        echo '<p class="form-row form-row-full">';
        echo '<label><input type="hidden" name="' . esc_attr($field_name) . '" value="no" /><input type="checkbox" class="checkbox" name="' . esc_attr($field_name) . '" value="yes" ' . checked(!empty($registry[$token]['enabled']), true, false) . ' /> <strong>' . esc_html__('Show in Express Bar', 'vms-express-bar') . '</strong></label>';
        echo '<span class="description">' . esc_html__('Uses this variation’s existing Express Bar registry row. Woo catalog visibility is separate.', 'vms-express-bar') . '</span>';
        echo '</p>';
    }
}

if (!function_exists('vmseb_save_woo_variation_membership_control')) {
    function vmseb_save_woo_variation_membership_control(int $variation_id, int $loop): void
    {
        if (!vmseb_can_save_woo_membership($variation_id)) {
            return;
        }
        // The standard Woo variation nonce is verified above.
        if (!isset($_POST['variable_vmseb_show_in_express_bar'][$loop])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return;
        }
        $variation = wc_get_product($variation_id);
        if (!$variation instanceof WC_Product_Variation) {
            return;
        }
        $enabled = sanitize_key((string) wp_unslash($_POST['variable_vmseb_show_in_express_bar'][$loop])) === 'yes'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        vmseb_save_membership_enabled_state('v_' . $variation_id, (int) $variation->get_parent_id(), $variation_id, $enabled, 'woo_variation');
    }
}


if (!function_exists('vmseb_handle_refresh_catalog_registry')) {
    function vmseb_handle_refresh_catalog_registry(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('vmseb_refresh_catalog_registry');
        $result = vmseb_prune_catalog_registry();
        if (!empty($result['removed'])) {
            vmseb_invalidate_dedicated_public_cache();
        }
        wp_safe_redirect(admin_url('admin.php?page=vms-bar-menu&vmseb_refreshed=' . absint($result['removed'] ?? 0)));
        exit;
    }
}

if (!function_exists('vmseb_handle_create_public_page')) {
    function vmseb_handle_create_public_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        $nonce = isset($_POST['vmseb_create_public_page_nonce']) ? sanitize_text_field(wp_unslash($_POST['vmseb_create_public_page_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'vmseb_create_public_page')) {
            wp_die('Invalid request');
        }
        $page_id = vmseb_create_public_page();
        $args = array('page' => 'vms-bar-menu');
        $args['vmseb_page_setup'] = $page_id > 0 ? 'ready' : 'failed';
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }
}

if (!function_exists('vmseb_queue_views')) {
    function vmseb_queue_views(): array
    {
        return array(
            'current'   => __('Current Show', 'vms-express-bar'),
            'completed' => __('Completed', 'vms-express-bar'),
            'past'      => __('Past / Unclaimed', 'vms-express-bar'),
            'future'    => __('Future Orders', 'vms-express-bar'),
        );
    }
}

if (!function_exists('vmseb_get_queue_view')) {
    function vmseb_get_queue_view($value): string
    {
        $view = sanitize_key((string) $value);
        return array_key_exists($view, vmseb_queue_views()) ? $view : 'current';
    }
}

if (!function_exists('vmseb_order_is_paid_for_fulfillment')) {
    function vmseb_order_is_paid_for_fulfillment(WC_Order $order): bool
    {
        return method_exists($order, 'is_paid') && $order->is_paid();
    }
}

if (!function_exists('vmseb_parse_event_plan_ids')) {
    function vmseb_parse_event_plan_ids($value): array
    {
        if (!is_array($value)) {
            $value = preg_split('/[\s,]+/', (string) $value) ?: array();
        }
        return array_values(array_unique(array_filter(array_map('absint', $value))));
    }
}

if (!function_exists('vmseb_get_order_event_plan_ids')) {
    function vmseb_get_order_event_plan_ids(WC_Order $order): array
    {
        $event_ids = array();
        foreach ($order->get_items('line_item') as $item) {
            if ((string) $item->get_meta('_vms_express_bar', true) !== '1') {
                continue;
            }
            $event_id = absint($item->get_meta('_vms_express_bar_event_plan_id', true));
            if ($event_id > 0) {
                $event_ids[] = $event_id;
            }
        }
        if (empty($event_ids)) {
            $event_ids = vmseb_parse_event_plan_ids($order->get_meta('_vms_express_bar_event_plan_ids', true));
        }
        return array_values(array_unique(array_filter($event_ids)));
    }
}

if (!function_exists('vmseb_get_queue_event_context')) {
    function vmseb_get_queue_event_context(int $event_plan_id): array
    {
        $start = $event_plan_id > 0 ? vmseb_get_event_plan_start_datetime($event_plan_id) : null;
        $title = $event_plan_id > 0 ? trim((string) get_the_title($event_plan_id)) : '';
        $date_label = '';
        $time_label = '';
        if ($start instanceof DateTimeImmutable) {
            $date_label = wp_date('D, M j, Y', $start->getTimestamp(), vmseb_wp_timezone());
            $time_label = wp_date('g:i A', $start->getTimestamp(), vmseb_wp_timezone());
        }
        return array(
            'id'         => $event_plan_id,
            'title'      => $title !== '' ? $title : sprintf(__('Event Plan #%d', 'vms-express-bar'), $event_plan_id),
            'start'      => $start,
            'timestamp'  => $start instanceof DateTimeImmutable ? $start->getTimestamp() : 0,
            'date_label' => $date_label,
            'time_label' => $time_label,
        );
    }
}

if (!function_exists('vmseb_get_queue_event_options')) {
    function vmseb_get_queue_event_options(array $orders): array
    {
        $event_ids = array();
        foreach ($orders as $order) {
            if ($order instanceof WC_Order) {
                $event_ids = array_merge($event_ids, vmseb_get_order_event_plan_ids($order));
            }
        }

        $today = new DateTimeImmutable('today', vmseb_wp_timezone());
        foreach (get_posts(array(
            'post_type'        => 'vms_event_plan',
            'post_status'      => array('publish', 'draft', 'pending', 'future', 'private'),
            'posts_per_page'   => 500,
            'fields'           => 'ids',
            'orderby'          => 'ID',
            'order'            => 'ASC',
            'no_found_rows'    => true,
            'suppress_filters' => true,
        )) as $event_id) {
            $event_id = absint($event_id);
            if ($event_id <= 0 || (string) get_post_meta($event_id, '_vms_express_bar_enabled', true) !== '1') {
                continue;
            }
            $start = vmseb_get_event_plan_start_datetime($event_id);
            if ($start instanceof DateTimeImmutable && $start >= $today) {
                $event_ids[] = $event_id;
            }
        }

        $options = array();
        foreach (array_values(array_unique(array_filter($event_ids))) as $event_id) {
            $options[$event_id] = vmseb_get_queue_event_context($event_id);
        }
        uasort($options, static function(array $left, array $right) use ($today): int {
            $left_ts = (int) ($left['timestamp'] ?? 0);
            $right_ts = (int) ($right['timestamp'] ?? 0);
            $today_ts = $today->getTimestamp();
            $left_group = $left_ts <= 0 ? 2 : ($left_ts >= $today_ts ? 0 : 1);
            $right_group = $right_ts <= 0 ? 2 : ($right_ts >= $today_ts ? 0 : 1);
            if ($left_group !== $right_group) {
                return $left_group <=> $right_group;
            }
            if ($left_group === 1) {
                return $right_ts <=> $left_ts;
            }
            if ($left_ts !== $right_ts) {
                return $left_ts <=> $right_ts;
            }
            return (int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0);
        });
        return $options;
    }
}

if (!function_exists('vmseb_get_default_queue_event_id')) {
    function vmseb_get_default_queue_event_id(array $orders, array $event_options): int
    {
        $today = new DateTimeImmutable('today', vmseb_wp_timezone());
        $candidates = array();
        foreach ($orders as $order) {
            if (!$order instanceof WC_Order || !vmseb_order_is_paid_for_fulfillment($order)) {
                continue;
            }
            $queue_status = vmseb_normalize_queue_status((string) $order->get_meta('_vms_express_bar_queue_status', true));
            if (!in_array($queue_status, array('pending', 'ready'), true)) {
                continue;
            }
            foreach (vmseb_get_order_event_plan_ids($order) as $event_id) {
                $context = $event_options[$event_id] ?? vmseb_get_queue_event_context($event_id);
                $start = $context['start'] ?? null;
                if ($start instanceof DateTimeImmutable && $start >= $today) {
                    $candidates[$event_id] = (int) $context['timestamp'];
                }
            }
        }
        if (!empty($candidates)) {
            asort($candidates, SORT_NUMERIC);
            return (int) array_key_first($candidates);
        }

        $public_event_id = function_exists('vmseb_resolve_public_event_plan_id') ? absint(vmseb_resolve_public_event_plan_id()) : 0;
        if ($public_event_id > 0) {
            return $public_event_id;
        }
        foreach ($event_options as $event_id => $context) {
            $start = $context['start'] ?? null;
            if ($start instanceof DateTimeImmutable && $start >= $today) {
                return (int) $event_id;
            }
        }
        return !empty($event_options) ? (int) array_key_first($event_options) : 0;
    }
}

if (!function_exists('vmseb_queue_event_relation')) {
    function vmseb_queue_event_relation(int $event_plan_id, ?DateTimeImmutable $pivot): string
    {
        $event_start = vmseb_get_event_plan_start_datetime($event_plan_id);
        if (!$event_start instanceof DateTimeImmutable || !$pivot instanceof DateTimeImmutable) {
            return 'unknown';
        }
        if ($event_start < $pivot) {
            return 'past';
        }
        if ($event_start > $pivot) {
            return 'future';
        }
        return 'current';
    }
}

if (!function_exists('vmseb_order_matches_queue_view')) {
    function vmseb_order_matches_queue_view(WC_Order $order, string $view, int $selected_event_id, ?DateTimeImmutable $pivot): bool
    {
        $queue_status = vmseb_normalize_queue_status((string) $order->get_meta('_vms_express_bar_queue_status', true));
        $event_ids = vmseb_get_order_event_plan_ids($order);
        if ($view === 'completed') {
            return $queue_status === 'completed' && ($selected_event_id === 0 || in_array($selected_event_id, $event_ids, true));
        }
        if (!in_array($queue_status, array('pending', 'ready'), true) || !vmseb_order_is_paid_for_fulfillment($order)) {
            return false;
        }
        if ($view === 'current') {
            return $selected_event_id > 0 && in_array($selected_event_id, $event_ids, true);
        }
        foreach ($event_ids as $event_id) {
            if (vmseb_queue_event_relation($event_id, $pivot) === $view) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('vmseb_queue_item_matches_view')) {
    function vmseb_queue_item_matches_view(int $event_plan_id, string $view, int $selected_event_id, ?DateTimeImmutable $pivot): bool
    {
        if ($view === 'current' || ($view === 'completed' && $selected_event_id > 0)) {
            return $event_plan_id === $selected_event_id;
        }
        if ($view === 'completed') {
            return true;
        }
        return vmseb_queue_event_relation($event_plan_id, $pivot) === $view;
    }
}

if (!function_exists('vmseb_get_queue_order_display_data')) {
    function vmseb_get_queue_order_display_data(WC_Order $order, string $view, int $selected_event_id, ?DateTimeImmutable $pivot): array
    {
        $item_lines = array();
        $events = array();
        $movement = array();
        $pickup_name = '';
        foreach ($order->get_items('line_item') as $item) {
            if ((string) $item->get_meta('_vms_express_bar', true) !== '1') {
                continue;
            }
            $event_id = absint($item->get_meta('_vms_express_bar_event_plan_id', true));
            if (!vmseb_queue_item_matches_view($event_id, $view, $selected_event_id, $pivot)) {
                continue;
            }
            if ($pickup_name === '') {
                $pickup_name = trim((string) $item->get_meta('_vms_express_bar_pickup_name', true));
            }
            $item_lines[] = $item->get_name() . ' × ' . max(1, absint($item->get_quantity()));
            if ($event_id > 0 && !isset($events[$event_id])) {
                $events[$event_id] = vmseb_get_queue_event_context($event_id);
            }

            $token = sanitize_text_field((string) $item->get_meta('_vmseb_item_token', true));
            $label = sanitize_text_field((string) $item->get_meta('_vmseb_item_label', true));
            if ($label === '') {
                $label = sanitize_text_field((string) $item->get_name());
            }
            if ($token === '') {
                $token = 'item_' . md5($label);
            }
            if (!isset($movement[$token])) {
                $movement[$token] = array('label' => $label, 'qty' => 0);
            }
            $movement[$token]['qty'] += max(1, absint($item->get_quantity()));
        }
        return array(
            'item_lines'  => $item_lines,
            'events'      => $events,
            'movement'    => $movement,
            'pickup_name' => $pickup_name,
        );
    }
}

if (!function_exists('vmseb_render_queue_page')) {
    function vmseb_render_queue_page(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        $view = vmseb_get_queue_view(isset($_GET['view']) ? wp_unslash($_GET['view']) : 'current');
        $event_was_requested = isset($_GET['event_plan_id']);
        $requested_event_id = $event_was_requested ? absint(wp_unslash($_GET['event_plan_id'])) : 0;
        if ($requested_event_id > 0 && get_post_type($requested_event_id) !== 'vms_event_plan') {
            $requested_event_id = 0;
            $event_was_requested = false;
        }
        $orders = wc_get_orders(array(
            'limit'      => -1,
            'orderby'    => 'date',
            'order'      => 'DESC',
            'type'       => 'shop_order',
            'status'     => array_keys(wc_get_order_statuses()),
            'meta_key'   => '_vms_express_bar_order',
            'meta_value' => '1',
        ));
        $orders = is_array($orders) ? $orders : array();
        $event_options = vmseb_get_queue_event_options($orders);
        if ($requested_event_id > 0 && !isset($event_options[$requested_event_id]) && get_post_type($requested_event_id) === 'vms_event_plan') {
            $event_options[$requested_event_id] = vmseb_get_queue_event_context($requested_event_id);
        }
        $default_event_id = vmseb_get_default_queue_event_id($orders, $event_options);
        $selected_event_id = ($view === 'completed' && $event_was_requested && $requested_event_id === 0)
            ? 0
            : ($requested_event_id > 0 ? $requested_event_id : $default_event_id);
        $operational_event_id = $selected_event_id > 0 ? $selected_event_id : $default_event_id;
        $selected_event = $operational_event_id > 0
            ? ($event_options[$operational_event_id] ?? vmseb_get_queue_event_context($operational_event_id))
            : array();
        $pivot = ($selected_event['start'] ?? null) instanceof DateTimeImmutable
            ? $selected_event['start']
            : new DateTimeImmutable('today', vmseb_wp_timezone());

        $counts = array_fill_keys(array_keys(vmseb_queue_views()), 0);
        $excluded_unfinished = 0;
        foreach ($orders as $order) {
            if (!$order instanceof WC_Order) {
                continue;
            }
            $queue_status = vmseb_normalize_queue_status((string) $order->get_meta('_vms_express_bar_queue_status', true));
            if (in_array($queue_status, array('pending', 'ready'), true) && !vmseb_order_is_paid_for_fulfillment($order)) {
                $excluded_unfinished++;
            }
            foreach (array_keys($counts) as $count_view) {
                $count_event_id = $count_view === 'completed' ? $selected_event_id : $operational_event_id;
                if (vmseb_order_matches_queue_view($order, $count_view, $count_event_id, $pivot)) {
                    $counts[$count_view]++;
                }
            }
        }

        echo '<div class="wrap vmseb-admin-page"><h1>' . esc_html__('Express Bar Fulfillment', 'vms-express-bar') . '</h1>';
        echo '<nav class="nav-tab-wrapper vmseb-queue-tabs" aria-label="' . esc_attr__('Express Bar queue views', 'vms-express-bar') . '">';
        foreach (vmseb_queue_views() as $view_key => $view_label) {
            $tab_event_id = $view_key === 'completed' ? $selected_event_id : $operational_event_id;
            $tab_url = add_query_arg(array('page' => 'vms-express-bar', 'view' => $view_key, 'event_plan_id' => $tab_event_id), admin_url('admin.php'));
            echo '<a class="nav-tab ' . ($view === $view_key ? 'nav-tab-active' : '') . '" href="' . esc_url($tab_url) . '">' . esc_html($view_label) . ' <span class="count">(' . (int) $counts[$view_key] . ')</span></a>';
        }
        echo '</nav>';

        if ($operational_event_id > 0) {
            echo '<section class="vmseb-show-context" aria-label="' . esc_attr__('Selected show', 'vms-express-bar') . '">';
            echo '<span class="vmseb-show-context__eyebrow">' . esc_html__('Operational show', 'vms-express-bar') . '</span>';
            echo '<h2>' . esc_html((string) ($selected_event['title'] ?? '')) . '</h2>';
            echo '<p><strong>' . esc_html((string) ($selected_event['date_label'] ?? '')) . '</strong>';
            if (!empty($selected_event['time_label'])) {
                echo ' <span aria-hidden="true">—</span> <strong>' . esc_html((string) $selected_event['time_label']) . '</strong>';
            }
            echo ' <span class="vmseb-event-id">' . esc_html(sprintf(__('Event Plan #%d', 'vms-express-bar'), $operational_event_id)) . '</span></p>';
            echo '</section>';
        }

        echo '<form method="get" class="vmseb-filter-form">';
        echo '<input type="hidden" name="page" value="vms-express-bar" />';
        echo '<input type="hidden" name="view" value="' . esc_attr($view) . '" />';
        echo '<label for="vmseb_event_plan_id"><strong>' . esc_html__('Operational show', 'vms-express-bar') . '</strong></label> ';
        echo '<select id="vmseb_event_plan_id" name="event_plan_id">';
        if ($view === 'completed') {
            echo '<option value="0" ' . selected($selected_event_id, 0, false) . '>' . esc_html__('All events (history)', 'vms-express-bar') . '</option>';
        }
        foreach ($event_options as $event_id => $context) {
            $option_label = (string) ($context['title'] ?? '');
            if (!empty($context['date_label'])) {
                $option_label .= ' — ' . (string) $context['date_label'];
            }
            if (!empty($context['time_label'])) {
                $option_label .= ' — ' . (string) $context['time_label'];
            }
            $option_label .= ' (#' . (int) $event_id . ')';
            echo '<option value="' . (int) $event_id . '" ' . selected($selected_event_id, $event_id, false) . '>' . esc_html($option_label) . '</option>';
        }
        echo '</select> ';
        submit_button(__('Apply', 'vms-express-bar'), 'secondary', '', false);
        echo '</form>';

        $view_copy = array(
            'current'   => __('Paid Pending and Ready orders for this show only.', 'vms-express-bar'),
            'completed' => __('Picked-up orders remain available here for inspection or recovery.', 'vms-express-bar'),
            'past'      => __('Paid unfinished orders for shows before the operational show.', 'vms-express-bar'),
            'future'    => __('Paid unfinished orders for shows after the operational show.', 'vms-express-bar'),
        );
        echo '<p class="description vmseb-view-description">' . esc_html($view_copy[$view]) . '</p>';
        if ($excluded_unfinished > 0) {
            echo '<p class="notice notice-info inline vmseb-excluded-note"><strong>' . (int) $excluded_unfinished . '</strong> ' . esc_html(_n('unfinished order is hidden from fulfillment because WooCommerce does not consider it paid.', 'unfinished orders are hidden from fulfillment because WooCommerce does not consider them paid.', $excluded_unfinished, 'vms-express-bar')) . '</p>';
        }

        $movement = array();
        $rendered = 0;
        echo '<div class="vmseb-table-wrap"><table class="widefat striped vmseb-table"><thead><tr><th>' . esc_html__('Order', 'vms-express-bar') . '</th><th>' . esc_html__('Customer', 'vms-express-bar') . '</th><th>' . esc_html__('Event', 'vms-express-bar') . '</th><th>' . esc_html__('Items', 'vms-express-bar') . '</th><th>' . esc_html__('Total', 'vms-express-bar') . '</th><th>' . esc_html__('Fulfillment', 'vms-express-bar') . '</th><th>' . esc_html__('Woo status', 'vms-express-bar') . '</th><th>' . esc_html__('ID Check', 'vms-express-bar') . '</th><th>' . esc_html__('Actions', 'vms-express-bar') . '</th></tr></thead><tbody>';
        foreach ($orders as $order) {
            if (!$order instanceof WC_Order || !vmseb_order_matches_queue_view($order, $view, $selected_event_id, $pivot)) {
                continue;
            }
            $display = vmseb_get_queue_order_display_data($order, $view, $selected_event_id, $pivot);
            $queue_status = vmseb_normalize_queue_status((string) $order->get_meta('_vms_express_bar_queue_status', true));
            $id_verified = (string) $order->get_meta('_vms_express_bar_id_verified', true) === '1';
            foreach ($display['movement'] as $token => $entry) {
                if (!isset($movement[$token])) {
                    $movement[$token] = array('label' => (string) ($entry['label'] ?? ''), 'qty' => 0);
                }
                $movement[$token]['qty'] += absint($entry['qty'] ?? 0);
            }
            $rendered++;
            $customer = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
            if ($customer === '') {
                $customer = $order->get_billing_email();
            }
            $event_labels = array();
            foreach ($display['events'] as $event_context) {
                $label = (string) ($event_context['title'] ?? '');
                if (!empty($event_context['date_label'])) {
                    $label .= ' — ' . (string) $event_context['date_label'];
                }
                if (!empty($event_context['time_label'])) {
                    $label .= ' — ' . (string) $event_context['time_label'];
                }
                $event_labels[] = $label;
            }
            $woo_status = (string) $order->get_status();
            $woo_status_label = function_exists('wc_get_order_status_name') ? wc_get_order_status_name($woo_status) : ucfirst($woo_status);
            $order_edit_url = method_exists($order, 'get_edit_order_url') ? (string) $order->get_edit_order_url() : admin_url('post.php?post=' . $order->get_id() . '&action=edit');
            echo '<tr>';
            echo '<td data-label="' . esc_attr__('Order', 'vms-express-bar') . '"><a href="' . esc_url($order_edit_url) . '">#' . (int) $order->get_id() . '</a><br /><small>' . esc_html($order->get_date_created() ? $order->get_date_created()->date_i18n('Y-m-d g:i a') : '') . '</small></td>';
            echo '<td data-label="' . esc_attr__('Customer', 'vms-express-bar') . '">' . esc_html($customer) . ($display['pickup_name'] !== '' ? '<br /><small>' . esc_html__('Pickup:', 'vms-express-bar') . ' ' . esc_html($display['pickup_name']) . '</small>' : '') . '</td>';
            echo '<td data-label="' . esc_attr__('Event', 'vms-express-bar') . '">' . esc_html(implode(' | ', $event_labels)) . '</td>';
            echo '<td data-label="' . esc_attr__('Items', 'vms-express-bar') . '">' . esc_html(implode(' | ', $display['item_lines'])) . '</td>';
            echo '<td data-label="' . esc_attr__('Total', 'vms-express-bar') . '">' . wp_kses_post($order->get_formatted_order_total()) . '</td>';
            echo '<td data-label="' . esc_attr__('Fulfillment', 'vms-express-bar') . '"><span class="vmseb-status vmseb-status--' . esc_attr($queue_status) . '">' . esc_html(ucfirst($queue_status)) . '</span></td>';
            echo '<td data-label="' . esc_attr__('Woo status', 'vms-express-bar') . '"><span class="vmseb-pill vmseb-pill--woo">' . esc_html($woo_status_label) . '</span></td>';
            echo '<td data-label="' . esc_attr__('ID Check', 'vms-express-bar') . '">' . ($id_verified ? '<span class="vmseb-pill vmseb-pill--ok">' . esc_html__('Verified', 'vms-express-bar') . '</span>' : '<span class="vmseb-pill">' . esc_html__('Pending', 'vms-express-bar') . '</span>') . '</td>';
            echo '<td data-label="' . esc_attr__('Actions', 'vms-express-bar') . '">';
            $is_paid = vmseb_order_is_paid_for_fulfillment($order);
            if ($is_paid && $queue_status !== 'ready') {
                echo vmseb_action_form($order->get_id(), 'ready', __('Mark Ready', 'vms-express-bar'), $view, $selected_event_id) . ' ';
            }
            if ($is_paid && $queue_status !== 'completed') {
                echo vmseb_action_form($order->get_id(), 'completed', __('Picked Up', 'vms-express-bar'), $view, $selected_event_id) . ' ';
            }
            echo vmseb_action_form($order->get_id(), 'verify_id', $id_verified ? __('Undo ID', 'vms-express-bar') : __('ID Verified', 'vms-express-bar'), $view, $selected_event_id);
            echo '</td></tr>';
        }
        if ($rendered === 0) {
            echo '<tr><td colspan="9">' . esc_html__('No Express Bar orders match this view.', 'vms-express-bar') . '</td></tr>';
        }
        echo '</tbody></table></div>';

        if (!empty($movement)) {
            uasort($movement, static function(array $left, array $right): int {
                return ($right['qty'] <=> $left['qty']);
            });
            echo '<section class="vmseb-movement"><h2>' . esc_html__('Item movement summary', 'vms-express-bar') . '</h2>';
            echo '<p class="description">' . esc_html__('Totals reflect only the valid orders shown in this working view.', 'vms-express-bar') . '</p>';
            echo '<table class="widefat striped vmseb-table"><thead><tr><th>' . esc_html__('Item', 'vms-express-bar') . '</th><th>' . esc_html__('Qty', 'vms-express-bar') . '</th></tr></thead><tbody>';
            foreach ($movement as $entry) {
                echo '<tr><td>' . esc_html($entry['label']) . '</td><td>' . (int) $entry['qty'] . '</td></tr>';
            }
            echo '</tbody></table></section>';
        }
        echo '</div>';
    }
}

if (!function_exists('vmseb_action_form')) {
    function vmseb_action_form(int $order_id, string $action_name, string $label, string $return_view = 'current', int $return_event_plan_id = 0): string
    {
        ob_start();
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="vmseb-action-form">
            <input type="hidden" name="action" value="vmseb_update_order" />
            <input type="hidden" name="order_id" value="<?php echo (int) $order_id; ?>" />
            <input type="hidden" name="queue_action" value="<?php echo esc_attr($action_name); ?>" />
            <input type="hidden" name="return_view" value="<?php echo esc_attr(vmseb_get_queue_view($return_view)); ?>" />
            <input type="hidden" name="return_event_plan_id" value="<?php echo (int) $return_event_plan_id; ?>" />
            <?php wp_nonce_field('vmseb_update_order_' . $order_id . '_' . $action_name, 'vmseb_order_nonce'); ?>
            <button type="submit" class="button button-small"><?php echo esc_html($label); ?></button>
        </form>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('vmseb_handle_order_update')) {
    function vmseb_handle_order_update(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Unauthorized');
        }
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $queue_action = isset($_POST['queue_action']) ? sanitize_key(wp_unslash($_POST['queue_action'])) : '';
        $return_view = vmseb_get_queue_view(isset($_POST['return_view']) ? wp_unslash($_POST['return_view']) : 'current');
        $return_event_plan_id = isset($_POST['return_event_plan_id']) ? absint(wp_unslash($_POST['return_event_plan_id'])) : 0;
        $nonce = isset($_POST['vmseb_order_nonce']) ? sanitize_text_field(wp_unslash($_POST['vmseb_order_nonce'])) : '';
        if ($order_id <= 0 || !in_array($queue_action, array('ready', 'completed', 'verify_id'), true) || !wp_verify_nonce($nonce, 'vmseb_update_order_' . $order_id . '_' . $queue_action)) {
            wp_die('Invalid request');
        }
        $order = wc_get_order($order_id);
        if (!$order instanceof WC_Order) {
            wp_die('Order not found');
        }
        if (in_array($queue_action, array('ready', 'completed'), true) && !vmseb_order_is_paid_for_fulfillment($order)) {
            wp_die('Order is not paid and cannot be fulfilled');
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
        wp_safe_redirect(add_query_arg(array(
            'page'          => 'vms-express-bar',
            'view'          => $return_view,
            'event_plan_id' => $return_event_plan_id,
        ), admin_url('admin.php')));
        exit;
    }
}
