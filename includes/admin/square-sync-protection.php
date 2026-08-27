<?php
defined('ABSPATH') || exit;

/**
 * Admin UI for VMS Square Sync Protection.
 */

if (!function_exists('vms_square_sync_protection_report_key')) {
    function vms_square_sync_protection_report_key(): string
    {
        return 'vms_square_sync_protection_report_' . max(1, get_current_user_id());
    }
}

if (!function_exists('vms_square_sync_protection_store_report')) {
    /**
     * @param array<string,mixed> $report
     */
    function vms_square_sync_protection_store_report(array $report): void
    {
        set_transient(vms_square_sync_protection_report_key(), $report, 30 * MINUTE_IN_SECONDS);
    }
}

if (!function_exists('vms_square_sync_protection_get_report')) {
    /**
     * @return array<string,mixed>
     */
    function vms_square_sync_protection_get_report(): array
    {
        $report = get_transient(vms_square_sync_protection_report_key());
        return is_array($report) ? $report : array();
    }
}

if (!function_exists('vms_square_sync_protection_redirect')) {
    function vms_square_sync_protection_redirect(string $notice): void
    {
        wp_safe_redirect(add_query_arg(array(
            'page' => 'vms-square-sync-protection',
            'vms_square_notice' => sanitize_key($notice),
        ), admin_url('admin.php')));
        exit;
    }
}

add_action('admin_menu', function (): void {
    add_submenu_page(
        'vms-dashboard',
        __('Square Sync Protection', 'backstage-venue-manager'),
        __('Square Sync Protection', 'backstage-venue-manager'),
        'manage_options',
        'vms-square-sync-protection',
        'vms_render_square_sync_protection_page'
    );
}, 60);

add_action('admin_post_vms_square_sync_protection_scan', function (): void {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Insufficient permissions.', 'backstage-venue-manager'));
    }
    check_admin_referer('vms_square_sync_protection_scan');

    if (!function_exists('vms_square_firewall_scan_products')) {
        wp_die(esc_html__('Square Sync Firewall is not loaded.', 'backstage-venue-manager'));
    }

    $report = vms_square_firewall_scan_products(false, 10000);
    vms_square_sync_protection_store_report($report);
    vms_square_sync_protection_redirect('scan_done');
});

add_action('admin_post_vms_square_sync_protection_repair', function (): void {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Insufficient permissions.', 'backstage-venue-manager'));
    }
    check_admin_referer('vms_square_sync_protection_repair');

    if (!function_exists('vms_square_firewall_scan_products')) {
        wp_die(esc_html__('Square Sync Firewall is not loaded.', 'backstage-venue-manager'));
    }

    $report = vms_square_firewall_scan_products(true, 10000);
    vms_square_sync_protection_store_report($report);
    vms_square_sync_protection_redirect('repair_done');
});

add_action('admin_post_vms_square_sync_protection_csv', function (): void {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Insufficient permissions.', 'backstage-venue-manager'));
    }
    check_admin_referer('vms_square_sync_protection_csv');

    $report = vms_square_sync_protection_get_report();
    if (empty($report)) {
        wp_die(esc_html__('No Square Sync Protection report is available. Run a scan first.', 'backstage-venue-manager'));
    }

    $filename = 'vms-square-sync-protection-' . gmdate('Ymd-His') . '.csv';
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $out = fopen('php://output', 'w');
    if ($out) {
        fputcsv($out, array('Product ID', 'Name', 'SKU', 'Protection Reason', 'Sync with Square', 'Had Square Link', 'Square Meta Cleared'));
        foreach ((array) ($report['rows'] ?? array()) as $row) {
            if (!is_array($row)) {
                continue;
            }
            fputcsv($out, array(
                absint($row['product_id'] ?? 0),
                (string) ($row['name'] ?? ''),
                (string) ($row['sku'] ?? ''),
                (string) ($row['reason'] ?? ''),
                (string) ($row['sync_value'] ?? ''),
                !empty($row['had_square_link']) ? 'yes' : 'no',
                (int) ($row['meta_cleared'] ?? 0),
            ));
        }
        fclose($out); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the bounded administrator CSV response stream opened on php://output; no local filesystem path or WP_Filesystem replacement applies to this HTTP output handle.
    }
    exit;
});

if (!function_exists('vms_render_square_sync_protection_summary_table')) {
    /**
     * @param array<string,mixed> $report
     */
    function vms_render_square_sync_protection_summary_table(array $report): void
    {
        $labels = array(
            'mode' => __('Mode', 'backstage-venue-manager'),
            'checked' => __('Products checked', 'backstage-venue-manager'),
            'protected_candidates' => __('VMS/TEC products found', 'backstage-venue-manager'),
            'had_square_links' => __('Had Square links/metadata', 'backstage-venue-manager'),
            'sync_yes' => __('Marked Sync with Square = yes', 'backstage-venue-manager'),
            'already_safe' => __('Already safe', 'backstage-venue-manager'),
            'repaired' => __('Repaired', 'backstage-venue-manager'),
            'meta_cleared' => __('Square meta fields cleared', 'backstage-venue-manager'),
            'skipped' => __('Normal products skipped', 'backstage-venue-manager'),
        );

        echo '<table class="widefat striped">';
        echo '<tbody>';
        foreach ($labels as $key => $label) {
            $value = $report[$key] ?? '';
            if ($key === 'ts') {
                continue;
            }
            echo '<tr>';
            echo '<th scope="row">' . esc_html($label) . '</th>';
            echo '<td>' . esc_html(is_scalar($value) ? (string) $value : '') . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    }
}

if (!function_exists('vms_render_square_sync_protection_rows')) {
    /**
     * @param array<string,mixed> $report
     */
    function vms_render_square_sync_protection_rows(array $report): void
    {
        $rows = (array) ($report['rows'] ?? array());
        if (empty($rows)) {
            echo '<p>' . esc_html__('No protected VMS/TEC products were found in the displayed report rows.', 'backstage-venue-manager') . '</p>';
            return;
        }

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Product', 'backstage-venue-manager') . '</th>';
        echo '<th>' . esc_html__('SKU', 'backstage-venue-manager') . '</th>';
        echo '<th>' . esc_html__('Reason', 'backstage-venue-manager') . '</th>';
        echo '<th>' . esc_html__('Sync with Square', 'backstage-venue-manager') . '</th>';
        echo '<th>' . esc_html__('Square link', 'backstage-venue-manager') . '</th>';
        echo '<th>' . esc_html__('Meta cleared', 'backstage-venue-manager') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $product_id = absint($row['product_id'] ?? 0);
            $title = (string) ($row['name'] ?? '');
            if ($title === '') {
                $title = '#' . $product_id;
            }
            $edit_link = $product_id > 0 ? get_edit_post_link($product_id, 'raw') : '';

            echo '<tr>';
            echo '<td>';
            if ($edit_link) {
                echo '<a href="' . esc_url($edit_link) . '">' . esc_html($title) . '</a>';
            } else {
                echo esc_html($title);
            }
            echo '</td>';
            echo '<td><code>' . esc_html((string) ($row['sku'] ?? '')) . '</code></td>';
            echo '<td><code>' . esc_html((string) ($row['reason'] ?? '')) . '</code></td>';
            echo '<td>' . esc_html((string) ($row['sync_value'] ?? '')) . '</td>';
            echo '<td>' . (!empty($row['had_square_link']) ? esc_html__('Yes', 'backstage-venue-manager') : esc_html__('No', 'backstage-venue-manager')) . '</td>';
            echo '<td>' . esc_html((string) (int) ($row['meta_cleared'] ?? 0)) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }
}

if (!function_exists('vms_square_sync_protection_render_admin_notice')) {
    function vms_square_sync_protection_render_admin_notice(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only Square Sync notice state only affects admin feedback.
        $notice = vms_request_read_key($_GET, 'vms_square_notice');
        if ($notice === 'scan_done') {
            echo '<div class="notice notice-info"><p>' . esc_html__('Square Sync Protection scan complete.', 'backstage-venue-manager') . '</p></div>';
        } elseif ($notice === 'repair_done') {
            echo '<div class="notice notice-success"><p>' . esc_html__('Square Sync Protection repair complete.', 'backstage-venue-manager') . '</p></div>';
        }
    }
}

if (!function_exists('vms_render_square_sync_protection_page_content')) {
    function vms_render_square_sync_protection_page_content(): void
    {
        $report = vms_square_sync_protection_get_report();

        echo '<p>' . esc_html__('Protect VMS/TEC tickets, admissions, passes, and event add-ons from Square catalog or inventory sync while leaving normal Square-owned items available for menus, merch, and inventory workflows.', 'backstage-venue-manager') . '</p>';

        echo '<div class="card">';
        echo '<h2>' . esc_html__('Run protection tools', 'backstage-venue-manager') . '</h2>';
        echo '<p>' . esc_html__('Scan shows what would be protected. Repair forces protected products to Sync with Square = no and removes stale Square item IDs from those products.', 'backstage-venue-manager') . '</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('vms_square_sync_protection_scan');
        echo '<input type="hidden" name="action" value="vms_square_sync_protection_scan" />';
        submit_button(__('Scan protected products', 'backstage-venue-manager'), 'secondary', 'submit', false);
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('vms_square_sync_protection_repair');
        echo '<input type="hidden" name="action" value="vms_square_sync_protection_repair" />';
        submit_button(__('Repair protected products', 'backstage-venue-manager'), 'primary', 'submit', false);
        echo '</form>';
        echo '<p class="description">' . esc_html__('Normal Square catalog products such as bar/menu items, shirts, eggs, and merch are skipped unless they are explicitly marked as VMS/TEC ticketing products.', 'backstage-venue-manager') . '</p>';
        echo '</div>';

        if (!empty($report)) {
            $csv_url = wp_nonce_url(admin_url('admin-post.php?action=vms_square_sync_protection_csv'), 'vms_square_sync_protection_csv');
            echo '<h2>' . esc_html__('Last report', 'backstage-venue-manager') . '</h2>';
            if (!empty($report['ts'])) {
                $ts = (int) $report['ts'];
                $readable = wp_date('Y-m-d H:i', $ts, wp_timezone());
                echo '<p class="description">' . esc_html($readable) . '</p>';
            }
            vms_render_square_sync_protection_summary_table($report);
            echo '<p><a class="button button-secondary" href="' . esc_url($csv_url) . '">' . esc_html__('Download report CSV', 'backstage-venue-manager') . '</a></p>';
            echo '<h3>' . esc_html__('Protected product details', 'backstage-venue-manager') . '</h3>';
            echo '<p class="description">' . esc_html__('Showing up to the first 200 protected products from the report.', 'backstage-venue-manager') . '</p>';
            vms_render_square_sync_protection_rows($report);
        }
    }
}

if (!function_exists('vms_render_square_sync_protection_page')) {
    function vms_render_square_sync_protection_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'backstage-venue-manager'));
        }

        if (function_exists('bvmgr_admin_ui_render_shell')) {
            bvmgr_admin_ui_render_shell(
                array(
                    'title' => __('Square Sync Protection', 'backstage-venue-manager'),
                    'subtitle' => __('Protect VMS-owned products from accidental Square catalog and inventory sync.', 'backstage-venue-manager'),
                    'shell_id' => 'vms-square-sync-protection-wrap',
                    'notices_callback' => 'vms_square_sync_protection_render_admin_notice',
                ),
                'vms_render_square_sync_protection_page_content'
            );
            return;
        }

        echo '<div class="wrap" id="vms-square-sync-protection-wrap">';
        echo '<h1>' . esc_html__('Square Sync Protection', 'backstage-venue-manager') . '</h1>';
        vms_render_square_sync_protection_page_content();
        echo '</div>';
    }
}
