<?php
defined('ABSPATH') || exit;

add_action('admin_menu', 'bvm_sqr_admin_menu', 90);
add_action('admin_post_bvm_sqr_ensure_objects', 'bvm_sqr_admin_ensure_objects');
add_action('admin_notices', 'bvm_sqr_missing_objects_notice');

if (!function_exists('bvm_sqr_admin_menu')) {
    function bvm_sqr_admin_menu(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        add_submenu_page(
            'woocommerce',
            __('BVM Square Reporting', 'bvm-square-reporting'),
            __('BVM Square Reporting', 'bvm-square-reporting'),
            'manage_woocommerce',
            'bvm-square-reporting',
            'bvm_sqr_render_admin_page'
        );
    }
}

if (!function_exists('bvm_sqr_admin_ensure_objects')) {
    function bvm_sqr_admin_ensure_objects(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to manage Square reporting.', 'bvm-square-reporting'));
        }
        check_admin_referer('bvm_sqr_ensure_objects');
        $result = bvm_sqr_ensure_objects();
        set_transient('bvm_sqr_admin_result_' . get_current_user_id(), $result, 120);
        wp_safe_redirect(admin_url('admin.php?page=bvm-square-reporting'));
        exit;
    }
}

if (!function_exists('bvm_sqr_missing_objects_notice')) {
    function bvm_sqr_missing_objects_notice(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (is_object($screen) && strpos((string) $screen->id, 'bvm-square-reporting') !== false) {
            return;
        }
        $objects = bvm_sqr_get_objects();
        $missing = array();
        foreach (array_keys(bvm_sqr_classes()) as $key) {
            if (empty($objects[$key]['variation_id'])) {
                $missing[] = $key;
            }
        }
        if (empty($missing)) {
            return;
        }
        echo '<div class="notice notice-warning"><p>';
        echo wp_kses_post(sprintf(
            __('BVM Square Reporting is installed but not fully provisioned for <strong>%s</strong>. <a href="%s">Open BVM Square Reporting</a>.', 'bvm-square-reporting'),
            esc_html(strtoupper(bvm_sqr_environment())),
            esc_url(admin_url('admin.php?page=bvm-square-reporting'))
        ));
        echo '</p></div>';
    }
}

if (!function_exists('bvm_sqr_render_admin_page')) {
    function bvm_sqr_render_admin_page(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        $environment = bvm_sqr_environment();
        $objects = bvm_sqr_get_objects($environment);
        $context = bvm_sqr_square_context();
        $result = get_transient('bvm_sqr_admin_result_' . get_current_user_id());
        if ($result !== false) {
            delete_transient('bvm_sqr_admin_result_' . get_current_user_id());
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('BVM Square Reporting', 'bvm-square-reporting'); ?></h1>
            <p><?php esc_html_e('Maps BVM online-only checkout lines to four stable Square reporting categories without syncing event-specific products into the Square catalog.', 'bvm-square-reporting'); ?></p>

            <table class="widefat striped" style="max-width:1100px;margin:18px 0;">
                <tbody>
                    <tr><th style="width:220px;"><?php esc_html_e('Environment', 'bvm-square-reporting'); ?></th><td><strong><?php echo esc_html(strtoupper($environment)); ?></strong></td></tr>
                    <tr><th><?php esc_html_e('Square location', 'bvm-square-reporting'); ?></th><td><?php echo esc_html((string) ($context['location_id'] ?? 'Not available')); ?></td></tr>
                    <tr><th><?php esc_html_e('Square API', 'bvm-square-reporting'); ?></th><td><?php echo !empty($context['ok']) ? esc_html__('Connected', 'bvm-square-reporting') : esc_html((string) ($context['error'] ?? 'Unavailable')); ?></td></tr>
                </tbody>
            </table>

            <?php if (is_array($result)) : ?>
                <div class="notice <?php echo !empty($result['ok']) ? 'notice-success' : 'notice-error'; ?> inline"><p>
                    <?php echo !empty($result['ok'])
                        ? esc_html__('Square reporting objects are ready.', 'bvm-square-reporting')
                        : esc_html((string) ($result['error'] ?? 'Provisioning did not complete.')); ?>
                </p></div>
            <?php endif; ?>

            <h2><?php esc_html_e('Reporting classes', 'bvm-square-reporting'); ?></h2>
            <table class="widefat striped" style="max-width:1100px;">
                <thead><tr>
                    <th><?php esc_html_e('Class', 'bvm-square-reporting'); ?></th>
                    <th><?php esc_html_e('Square category', 'bvm-square-reporting'); ?></th>
                    <th><?php esc_html_e('Reporting SKU', 'bvm-square-reporting'); ?></th>
                    <th><?php esc_html_e('Variation ID', 'bvm-square-reporting'); ?></th>
                    <th><?php esc_html_e('Status', 'bvm-square-reporting'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach (bvm_sqr_classes() as $key => $definition) :
                    $row = isset($objects[$key]) && is_array($objects[$key]) ? $objects[$key] : array();
                    $ready = !empty($row['variation_id']);
                    ?>
                    <tr>
                        <td><code><?php echo esc_html($key); ?></code></td>
                        <td><?php echo esc_html((string) ($row['category_name'] ?? $definition['label'])); ?></td>
                        <td><code><?php echo esc_html((string) $definition['sku']); ?></code></td>
                        <td><code><?php echo esc_html((string) ($row['variation_id'] ?? '—')); ?></code></td>
                        <td><?php echo $ready ? '<strong style="color:#168038">Ready</strong>' : '<strong style="color:#b32d2e">Not provisioned</strong>'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:18px;">
                <input type="hidden" name="action" value="bvm_sqr_ensure_objects" />
                <?php wp_nonce_field('bvm_sqr_ensure_objects'); ?>
                <?php submit_button(__('Ensure / Repair Square Reporting Objects', 'bvm-square-reporting'), 'primary', 'submit', false); ?>
            </form>

            <p style="max-width:900px;margin-top:18px;"><strong><?php esc_html_e('What this does:', 'bvm-square-reporting'); ?></strong> <?php esc_html_e('Creates or reuses four permanent Square catalog categories/items for reporting. It does not enable normal Square sync for BVM ticket, add-on, rental, or tip products. Individual events remain managed in WooCommerce/BVM.', 'bvm-square-reporting'); ?></p>
        </div>
        <?php
    }
}
