<?php
if (!defined('ABSPATH')) exit;

/**
 * VMS – Staff Tax Profile Sidebar (Admin)
 * Shows tax profile completion badge + missing items list in the sidebar.
 *
 * Staff may be:
 * - Contractor (1099): Tax Profile required (upload or off-site workflow)
 * - Employee (W-2): Employee packet required (W-4 + I-9 verified)
 */

if (!function_exists('vms_staff_tax_provider')) {
    function vms_staff_tax_provider(): string
    {
        if (function_exists('vms_tax_settings_get_provider')) {
            return (string) vms_tax_settings_get_provider();
        }

        $settings = get_option('vms_settings', array());
        $settings = is_array($settings) ? $settings : array();
        $provider = isset($settings['tax_w9_provider']) ? (string) $settings['tax_w9_provider'] : 'upload';
        if (!in_array($provider, array('upload', 'quickbooks_email', 'tax1099_email'), true)) {
            $provider = 'upload';
        }
        return $provider;
    }
}

if (!function_exists('vms_staff_tax_provider_label')) {
    function vms_staff_tax_provider_label(string $provider): string
    {
        if (function_exists('vms_tax_provider_label')) {
            return (string) vms_tax_provider_label($provider);
        }
        if ($provider === 'quickbooks_email') return 'QuickBooks Online';
        if ($provider === 'tax1099_email') return 'Tax1099';
        return 'Upload';
    }
}

if (!function_exists('vms_staff_tax_meta_key')) {
    function vms_staff_tax_meta_key(string $field, string $fallback): string
    {
        if (function_exists('vms_meta_key')) {
            $mapped = (string) vms_meta_key('vendor', $field);
            if ($mapped !== '') {
                return $mapped;
            }
        }
        return $fallback;
    }
}

if (!function_exists('vms_staff_tax_status_context')) {
    function vms_staff_tax_status_context(int $staff_id): array
    {
        $staff_id = absint($staff_id);
        $provider = vms_staff_tax_provider();

        $k_done = vms_staff_tax_meta_key('tax_profile_completed_at', '_vms_tax_profile_completed_at');
        $k_attest = vms_staff_tax_meta_key('w9_attested_at', '_vms_w9_external_vendor_attested_at');
        $k_prov = vms_staff_tax_meta_key('w9_provider', '_vms_w9_offsite_provider');
        $k_upload = vms_staff_tax_meta_key('w9_upload_id', '_vms_w9_upload_id');
        $k_confirmed_at = vms_staff_tax_meta_key('tax_admin_confirmed_at', '_vms_tax_admin_confirmed_at');
        $k_confirmed_by = vms_staff_tax_meta_key('tax_admin_confirmed_by', '_vms_tax_admin_confirmed_by');

        $done_at = (int) get_post_meta($staff_id, $k_done, true);
        $attested_at = (int) get_post_meta($staff_id, $k_attest, true);
        $stored_provider = (string) get_post_meta($staff_id, $k_prov, true);
        if (!in_array($stored_provider, array('upload', 'quickbooks_email', 'tax1099_email'), true)) {
            $stored_provider = '';
        }
        $effective_provider = ($done_at > 0 && $stored_provider !== '') ? $stored_provider : $provider;

        $upload_id = (int) get_post_meta($staff_id, $k_upload, true);
        $upload_url = $upload_id && function_exists('vms_private_w9_download_url') ? vms_private_w9_download_url($staff_id) : '';
        $upload_label = $upload_id && function_exists('vms_private_w9_file_label') ? vms_private_w9_file_label($staff_id) : '';

        $admin_confirmed_at = (int) get_post_meta($staff_id, $k_confirmed_at, true);
        if ($admin_confirmed_at <= 0 && $done_at > 0) {
            $admin_confirmed_at = $done_at;
        }
        $admin_confirmed_by = (int) get_post_meta($staff_id, $k_confirmed_by, true);
        $admin_user = $admin_confirmed_by > 0 ? get_user_by('id', $admin_confirmed_by) : null;
        $admin_name = $admin_user ? (string) ($admin_user->display_name ?: $admin_user->user_login) : '';

        $missing = function_exists('vms_vendor_tax_profile_missing_items')
            ? (array) vms_vendor_tax_profile_missing_items($staff_id)
            : array();

        $stage = 'incomplete';
        $badge_label = __('Incomplete', 'backstage-venue-manager');
        if ($done_at > 0) {
            $stage = 'complete';
            $badge_label = __('Complete', 'backstage-venue-manager');
        } elseif ($effective_provider !== 'upload' && $attested_at > 0) {
            $stage = 'submitted';
            $badge_label = __('Submitted', 'backstage-venue-manager');
        }

        return array(
            'provider' => $effective_provider,
            'provider_label' => vms_staff_tax_provider_label($effective_provider),
            'global_provider' => $provider,
            'done_at' => $done_at,
            'attested_at' => $attested_at,
            'upload_id' => $upload_id,
            'upload_url' => $upload_url,
            'upload_label' => $upload_label,
            'missing' => $missing,
            'stage' => $stage,
            'badge_label' => $badge_label,
            'admin_confirmed_at' => $admin_confirmed_at,
            'admin_confirmed_by' => $admin_confirmed_by,
            'admin_confirmed_by_name' => $admin_name,
            'keys' => array(
                'done' => $k_done,
                'provider' => $k_prov,
                'confirmed_at' => $k_confirmed_at,
                'confirmed_by' => $k_confirmed_by,
            ),
        );
    }
}

if (!function_exists('vms_staff_tax_mark_complete_url')) {
    function vms_staff_tax_mark_complete_url(int $staff_id): string
    {
        return wp_nonce_url(
            add_query_arg(array('action' => 'vms_staff_tax_mark_complete', 'staff_id' => $staff_id), admin_url('admin-post.php')),
            'vms_staff_tax_mark_complete_' . $staff_id
        );
    }
}

if (!function_exists('vms_staff_tax_clear_complete_url')) {
    function vms_staff_tax_clear_complete_url(int $staff_id): string
    {
        return wp_nonce_url(
            add_query_arg(array('action' => 'vms_staff_tax_clear_complete', 'staff_id' => $staff_id), admin_url('admin-post.php')),
            'vms_staff_tax_clear_complete_' . $staff_id
        );
    }
}

add_action('admin_post_vms_staff_tax_mark_complete', function (): void {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This admin-post action verifies a staff-specific nonce immediately below before mutating staff tax state.
    $staff_id = vms_request_read_absint($_GET, 'staff_id');
    if ($staff_id <= 0) wp_die('Invalid staff member.');
    if (!current_user_can('edit_post', $staff_id)) wp_die('Permission denied.');
    check_admin_referer('vms_staff_tax_mark_complete_' . $staff_id);

    $provider = vms_staff_tax_provider();
    $ctx = vms_staff_tax_status_context($staff_id);
    $keys = isset($ctx['keys']) && is_array($ctx['keys']) ? $ctx['keys'] : array();

    update_post_meta($staff_id, (string) ($keys['done'] ?? '_vms_tax_profile_completed_at'), time());
    update_post_meta($staff_id, (string) ($keys['provider'] ?? '_vms_w9_offsite_provider'), $provider);
    update_post_meta($staff_id, (string) ($keys['confirmed_at'] ?? '_vms_tax_admin_confirmed_at'), time());
    update_post_meta($staff_id, (string) ($keys['confirmed_by'] ?? '_vms_tax_admin_confirmed_by'), get_current_user_id());

    wp_safe_redirect(add_query_arg(array('post' => $staff_id, 'action' => 'edit', 'vms_staff_tax_notice' => 'complete'), admin_url('post.php')));
    exit;
});

add_action('admin_post_vms_staff_tax_clear_complete', function (): void {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This admin-post action verifies a staff-specific nonce immediately below before mutating staff tax state.
    $staff_id = vms_request_read_absint($_GET, 'staff_id');
    if ($staff_id <= 0) wp_die('Invalid staff member.');
    if (!current_user_can('edit_post', $staff_id)) wp_die('Permission denied.');
    check_admin_referer('vms_staff_tax_clear_complete_' . $staff_id);

    $ctx = vms_staff_tax_status_context($staff_id);
    $keys = isset($ctx['keys']) && is_array($ctx['keys']) ? $ctx['keys'] : array();

    delete_post_meta($staff_id, (string) ($keys['done'] ?? '_vms_tax_profile_completed_at'));
    delete_post_meta($staff_id, (string) ($keys['confirmed_at'] ?? '_vms_tax_admin_confirmed_at'));
    delete_post_meta($staff_id, (string) ($keys['confirmed_by'] ?? '_vms_tax_admin_confirmed_by'));

    wp_safe_redirect(add_query_arg(array('post' => $staff_id, 'action' => 'edit', 'vms_staff_tax_notice' => 'cleared'), admin_url('post.php')));
    exit;
});

add_action('admin_notices', function (): void {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only staff tax notice state only affects admin feedback.
    $notice = vms_request_read_key($_GET, 'vms_staff_tax_notice');
    if ($notice === '') {
        return;
    }

    if ($notice === 'complete') {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Staff tax profile marked complete.', 'backstage-venue-manager') . '</p></div>';
    } elseif ($notice === 'cleared') {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Staff admin completion was cleared.', 'backstage-venue-manager') . '</p></div>';
    }
});

add_action('add_meta_boxes', function () {
    add_meta_box(
        'vms_staff_tax_status',
        __('Tax Profile Status', 'backstage-venue-manager'),
        'vms_render_staff_tax_status_metabox',
        'vms_staff',
        'side',
        'low'
    );
});

function vms_render_staff_tax_status_metabox($post)
{
    $staff_id = (int) $post->ID;

    $worker_type = function_exists('vms_staff_get_worker_type')
        ? (string) vms_staff_get_worker_type($staff_id)
        : '';

    if ($worker_type === '') {
        $k = function_exists('vms_meta_key') ? (string) vms_meta_key('staff', 'worker_type') : '_vms_staff_worker_type';
        $raw = (string) get_post_meta($staff_id, $k !== '' ? $k : '_vms_staff_worker_type', true);
        $raw = sanitize_key($raw);
        $worker_type = in_array($raw, array('employee', 'contractor'), true) ? $raw : 'contractor';
    }

    if ($worker_type === 'employee') {
        wp_nonce_field('vms_staff_employee_packet_save', 'vms_staff_employee_packet_nonce');

        $missing = vms_staff_employee_packet_missing_items($staff_id);
        $is_complete = empty($missing);

        echo '<p class="vms-staff-tax-badge-row">';
        echo $is_complete
            ? '<span class="vms-badge vms-badge-ok">' . esc_html__('Employee packet complete', 'backstage-venue-manager') . '</span>'
            : '<span class="vms-badge vms-badge-miss">' . esc_html__('Employee packet incomplete', 'backstage-venue-manager') . '</span>';
        echo '</p>';

        echo '<p class="vms-mini">' . esc_html__('W-2 employees still need onboarding paperwork. Track it here (no SSN entered into WordPress).', 'backstage-venue-manager') . '</p>';

        if (!$is_complete) {
            echo '<p class="vms-mini">' . esc_html__('Missing required items:', 'backstage-venue-manager') . '</p>';
            echo '<ul class="vms-missing">';
            foreach ($missing as $m) {
                echo '<li>' . esc_html($m) . '</li>';
            }
            echo '</ul>';
        }

        $w4 = (int) get_post_meta($staff_id, vms_staff_employee_w4_received_key(), true) ? 1 : 0;
        $i9 = (int) get_post_meta($staff_id, vms_staff_employee_i9_verified_key(), true) ? 1 : 0;
        $dd = (int) get_post_meta($staff_id, vms_staff_employee_direct_deposit_received_key(), true) ? 1 : 0;

        echo '<div class="vms-mini">';
        echo '<label class="vms-admin-tax-check"><input type="checkbox" name="vms_emp_w4" value="1" ' . checked($w4, 1, false) . '> ' . esc_html__('W-4 received', 'backstage-venue-manager') . '</label>';
        echo '<label class="vms-admin-tax-check"><input type="checkbox" name="vms_emp_i9" value="1" ' . checked($i9, 1, false) . '> ' . esc_html__('I-9 verified', 'backstage-venue-manager') . '</label>';
        echo '<label class="vms-admin-tax-check"><input type="checkbox" name="vms_emp_dd" value="1" ' . checked($dd, 1, false) . '> ' . esc_html__('Direct deposit info received (optional)', 'backstage-venue-manager') . '</label>';
        echo '</div>';

        echo '<p class="vms-mini">' . esc_html__('Saved when you click Update.', 'backstage-venue-manager') . '</p>';
        return;
    }

    $ctx = vms_staff_tax_status_context($staff_id);
    $stage = (string) ($ctx['stage'] ?? 'incomplete');
    $provider = (string) ($ctx['provider'] ?? 'upload');
    $provider_label = (string) ($ctx['provider_label'] ?? __('Upload', 'backstage-venue-manager'));
    $done_at = (int) ($ctx['done_at'] ?? 0);
    $attested_at = (int) ($ctx['attested_at'] ?? 0);
    $admin_confirmed_at = (int) ($ctx['admin_confirmed_at'] ?? 0);
    $admin_confirmed_by_name = (string) ($ctx['admin_confirmed_by_name'] ?? '');
    $missing = isset($ctx['missing']) ? (array) $ctx['missing'] : array();
    $upload_url = (string) ($ctx['upload_url'] ?? '');
    $upload_label = (string) ($ctx['upload_label'] ?? '');

    echo '<p class="vms-staff-tax-badge-row">';
    if ($stage === 'complete') {
        echo '<span class="vms-badge vms-badge-ok">' . esc_html__('Complete', 'backstage-venue-manager') . '</span>';
    } elseif ($stage === 'submitted') {
        echo '<span class="vms-badge vms-badge-warn">' . esc_html__('Submitted', 'backstage-venue-manager') . '</span>';
    } else {
        echo '<span class="vms-badge vms-badge-miss">' . esc_html__('Incomplete', 'backstage-venue-manager') . '</span>';
    }
    echo '</p>';

    echo '<p class="vms-mini"><strong>' . esc_html__('W-9 source of truth:', 'backstage-venue-manager') . '</strong> ' . esc_html($provider_label) . '</p>';

    if ($provider !== 'upload') {
        echo '<p class="vms-mini">' . esc_html__('This staff member should complete their W-9/tax step through the secure off-site workflow. Use the buttons below to confirm or clear admin completion in VMS.', 'backstage-venue-manager') . '</p>';
    }

    if ($attested_at > 0) {
        echo '<p class="vms-mini"><strong>' . esc_html__('Staff confirmed:', 'backstage-venue-manager') . '</strong> ' . esc_html(wp_date('M j, Y g:ia', $attested_at, wp_timezone())) . '</p>';
    }

    if ($admin_confirmed_at > 0) {
        echo '<p class="vms-mini"><strong>' . esc_html__('Admin confirmed:', 'backstage-venue-manager') . '</strong> ' . esc_html(wp_date('M j, Y g:ia', $admin_confirmed_at, wp_timezone()));
        if ($admin_confirmed_by_name !== '') {
            echo '<br><strong>' . esc_html__('By:', 'backstage-venue-manager') . '</strong> ' . esc_html($admin_confirmed_by_name);
        }
        echo '</p>';
    } elseif ($done_at > 0) {
        echo '<p class="vms-mini"><strong>' . esc_html__('Completed:', 'backstage-venue-manager') . '</strong> ' . esc_html(wp_date('M j, Y g:ia', $done_at, wp_timezone())) . '</p>';
    }

    if ($upload_url) {
        echo '<p class="vms-mini"><strong>' . esc_html__('W-9 file:', 'backstage-venue-manager') . '</strong> <a href="' . esc_url($upload_url) . '" target="_blank" rel="noopener">' . esc_html($upload_label !== '' ? $upload_label : __('Download', 'backstage-venue-manager')) . '</a></p>';
    }

    if (!empty($missing)) {
        echo '<p class="vms-mini">' . esc_html__('Still needed:', 'backstage-venue-manager') . '</p>';
        echo '<ul class="vms-missing">';
        foreach ($missing as $m) {
            echo '<li>' . esc_html((string) $m) . '</li>';
        }
        echo '</ul>';
    }

    $complete_label = ($provider === 'quickbooks_email')
        ? __('Mark QuickBooks complete', 'backstage-venue-manager')
        : (($provider === 'tax1099_email') ? __('Mark Tax1099 complete', 'backstage-venue-manager') : __('Mark complete', 'backstage-venue-manager'));

    echo '<p><a class="button button-primary" href="' . esc_url(vms_staff_tax_mark_complete_url($staff_id)) . '">' . esc_html($complete_label) . '</a></p>';
    if ($stage === 'complete') {
        echo '<p><a class="button" href="' . esc_url(vms_staff_tax_clear_complete_url($staff_id)) . '">' . esc_html__('Clear admin completion', 'backstage-venue-manager') . '</a></p>';
    }

    echo '<p class="vms-mini vms-staff-tax-tip">' . esc_html__('Tip: staff can complete their side from the Staff Portal. This box reflects the active source of truth and lets admin confirm it without using the temporary bypass.', 'backstage-venue-manager') . '</p>';
}

/**
 * Employee packet helpers
 */
function vms_staff_employee_w4_received_key(): string
{
    if (function_exists('vms_meta_key')) {
        $k = (string) vms_meta_key('staff', 'employee_w4_received');
        if ($k !== '') return $k;
    }
    return '_vms_employee_w4_received';
}

function vms_staff_employee_i9_verified_key(): string
{
    if (function_exists('vms_meta_key')) {
        $k = (string) vms_meta_key('staff', 'employee_i9_verified');
        if ($k !== '') return $k;
    }
    return '_vms_employee_i9_verified';
}

function vms_staff_employee_direct_deposit_received_key(): string
{
    if (function_exists('vms_meta_key')) {
        $k = (string) vms_meta_key('staff', 'employee_direct_deposit_received');
        if ($k !== '') return $k;
    }
    return '_vms_employee_direct_deposit_received';
}

function vms_staff_employee_packet_missing_items(int $staff_id): array
{
    $missing = array();
    $staff_id = (int) $staff_id;

    $w4 = (int) get_post_meta($staff_id, vms_staff_employee_w4_received_key(), true) ? 1 : 0;
    $i9 = (int) get_post_meta($staff_id, vms_staff_employee_i9_verified_key(), true) ? 1 : 0;

    if (!$w4) $missing[] = __('W-4 received', 'backstage-venue-manager');
    if (!$i9) $missing[] = __('I-9 verified', 'backstage-venue-manager');

    return $missing;
}

add_action('save_post_vms_staff', function (int $post_id, WP_Post $post, bool $update): void {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $nonce = (isset($_POST['vms_staff_employee_packet_nonce']) && !is_array($_POST['vms_staff_employee_packet_nonce']))
        ? sanitize_text_field(wp_unslash((string) $_POST['vms_staff_employee_packet_nonce']))
        : '';
    if ($nonce === '' || !wp_verify_nonce($nonce, 'vms_staff_employee_packet_save')) {
        return;
    }

    $staff_id = (int) $post_id;
    $worker_type = function_exists('vms_staff_get_worker_type') ? (string) vms_staff_get_worker_type($staff_id) : '';
    if ($worker_type !== 'employee') {
        return;
    }

    $flag = static function (string $key): int {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- This save path verifies vms_staff_employee_packet_save before reading employee-packet flags.
        return vms_request_read_bool_flag($_POST, $key) ? 1 : 0;
    };

    $w4 = $flag('vms_emp_w4');
    $i9 = $flag('vms_emp_i9');
    $dd = $flag('vms_emp_dd');

    $today = function_exists('wp_date') ? wp_date('Y-m-d', time(), wp_timezone()) : date('Y-m-d');

    vms_staff_employee_packet_set_flag($staff_id, vms_staff_employee_w4_received_key(), '_vms_employee_w4_received_date', $w4, $today);
    vms_staff_employee_packet_set_flag($staff_id, vms_staff_employee_i9_verified_key(), '_vms_employee_i9_verified_date', $i9, $today);
    vms_staff_employee_packet_set_flag($staff_id, vms_staff_employee_direct_deposit_received_key(), '_vms_employee_direct_deposit_received_date', $dd, $today);

}, 20, 3);

function vms_staff_employee_packet_set_flag(int $staff_id, string $flag_key, string $date_key_fallback, int $value, string $today): void
{
    $staff_id = (int) $staff_id;
    $flag_key = (string) $flag_key;
    if ($flag_key === '') return;

    $date_key = $date_key_fallback;

    if ($value) {
        update_post_meta($staff_id, $flag_key, 1);
        if ((string) get_post_meta($staff_id, $date_key, true) === '') {
            update_post_meta($staff_id, $date_key, $today);
        }
    } else {
        delete_post_meta($staff_id, $flag_key);
        delete_post_meta($staff_id, $date_key);
    }
}
