<?php
defined('ABSPATH') || exit;

function bvmgr_staffing_notify_admin_url(int $plan): string
{
    return add_query_arg(array('page' => 'bvm-staffing-notifications', 'plan_id' => $plan), admin_url('admin.php'));
}

function bvmgr_staffing_notify_status(array $latest): string
{
    if (!$latest) return __('Committed; awaiting notification queue', 'backstage-venue-manager');
    $work = json_decode((string) $latest['payload_json'], true) ?: array();
    if (($work['phase'] ?? '') === 'attempt') return __('Delivery outcome unknown; investigate before any resend', 'backstage-venue-manager');
    if ($latest['status'] === 'sent') return __('Accepted by email transport; inbox delivery unverified', 'backstage-venue-manager');
    if ($latest['status'] === 'failed' && (int) ($work['attempts'] ?? 0) >= 3) return __('Automatic retries exhausted; operator review required', 'backstage-venue-manager');
    return $latest['status'] . ($latest['error_message'] !== '' ? ' — ' . $latest['error_message'] : '');
}

function bvmgr_staffing_notify_render(int $plan, bool $history = false): void
{
    if (!current_user_can('edit_post', $plan)) return;
    if (bvmgr_staffing_notify_floor() < 0) {
        echo '<p>' . esc_html__('Staffing notification delivery is not enabled. Existing assignments remain authoritative.', 'backstage-venue-manager') . '</p>';
    } else {
        echo '<p>' . esc_html__('Proposals, confirmations, declines, cancellations and audited shift-time changes notify the assigned person. Retries retain the original change; superseded changes are not sent.', 'backstage-venue-manager') . '</p>';
        $audits = bvmgr_staffing_notify_audits($plan);
        if (!$audits) echo '<p>' . esc_html__('No notification-triggering staffing changes since delivery was enabled.', 'backstage-venue-manager') . '</p>';
        foreach ($audits as $audit) {
            $logs = bvmgr_staffing_notify_logs(bvmgr_staffing_notify_key((int) $audit['log_id']));
            $latest = $logs[0] ?? array();
            $payload = $latest ? (json_decode((string) $latest['payload_json'], true) ?: array()) : bvmgr_staffing_notify_work($audit);
            echo '<div class="bvm-staffing-notification"><p><strong>' . esc_html($audit['created_at'] . ' UTC · #' . $audit['log_id'] . ' · ' . ($payload['status'] ?? __('Shift time updated', 'backstage-venue-manager'))) . '</strong><br>';
            echo esc_html(get_the_title((int) ($payload['staff_id'] ?? 0)) . ' — ' . ($latest['recipient_address'] ?? '')) . '<br>' . esc_html(bvmgr_staffing_notify_status($latest)) . '</p>';
            if ($history) {
                if (!$latest || in_array($latest['status'], array('queued', 'failed'), true) && ($payload['phase'] ?? '') !== 'attempt') {
                    echo '<form method="post">';
                    wp_nonce_field('bvmgr_staffing_notify_retry_' . $audit['log_id'], 'bvmgr_staffing_notify_nonce');
                    echo '<button class="button" name="retry_audit" value="' . esc_attr((string) $audit['log_id']) . '">' . esc_html__('Retry this staffing notification', 'backstage-venue-manager') . '</button></form>';
                }
                foreach ($logs as $log) {
                    $attempt = json_decode((string) $log['payload_json'], true) ?: array();
                    echo '<details><summary>' . esc_html($log['created_at'] . ' UTC — ' . $log['status'] . ' — ' . ($log['recipient_address'] ?: __('Unresolved recipient', 'backstage-venue-manager'))) . '</summary><p>';
                    echo esc_html(($attempt['role'] ?? '') . ' · ' . ($attempt['start'] ?? '') . ' · ' . __('Attempt ', 'backstage-venue-manager') . ($attempt['attempts'] ?? 0) . ' · ' . $log['error_message']) . '</p></details>';
                }
            }
            echo '</div>';
        }
    }
    echo '<p><a href="' . esc_url(bvmgr_staffing_notify_admin_url($plan)) . '">' . esc_html__('Staffing notification history / retry', 'backstage-venue-manager') . '</a></p>';
}

function bvmgr_staffing_notify_admin_page(): void
{
    $plan = isset($_GET['plan_id']) && is_scalar($_GET['plan_id']) ? absint($_GET['plan_id']) : 0;
    if (get_post_type($plan) !== 'vms_event_plan' || !current_user_can('edit_post', $plan)) wp_die(esc_html__('You cannot edit this Event Plan.', 'backstage-venue-manager'), '', array('response' => 403));
    echo '<div class="wrap"><h1>' . esc_html__('Staffing notifications', 'backstage-venue-manager') . '</h1>';
    if (bvmgr_request_method() === 'post') {
        if (isset($_POST['enable_staffing_notifications'])) {
            check_admin_referer('bvmgr_staffing_notify_enable', 'bvmgr_staffing_notify_nonce');
            if (!bvmgr_staffing_notify_enable()) wp_die(esc_html__('Delivery could not be enabled.', 'backstage-venue-manager'));
        } elseif (isset($_POST['retry_audit']) && is_scalar($_POST['retry_audit'])) {
            $id = absint($_POST['retry_audit']);
            check_admin_referer('bvmgr_staffing_notify_retry_' . $id, 'bvmgr_staffing_notify_nonce');
            $audits = bvmgr_staffing_notify_audits($plan);
            if (!in_array($id, array_map('intval', array_column($audits, 'log_id')), true)) wp_die(esc_html__('Notification is unavailable for this event.', 'backstage-venue-manager'));
            bvmgr_staffing_notify_tick($plan, $id);
            echo '<div class="notice notice-info"><p>' . esc_html__('Retry checked against current assignment authority. Successful, uncertain and superseded deliveries are not resent; failed attempts wait 60 seconds.', 'backstage-venue-manager') . '</p></div>';
        }
    }
    if (bvmgr_staffing_notify_floor() < 0 && current_user_can('manage_options')) {
        echo '<form method="post">'; wp_nonce_field('bvmgr_staffing_notify_enable', 'bvmgr_staffing_notify_nonce');
        echo '<p>' . esc_html__('Enable for future committed staffing changes only. Historical changes will not be emailed.', 'backstage-venue-manager') . '</p><button class="button button-primary" name="enable_staffing_notifications" value="1">' . esc_html__('Enable staffing notification delivery', 'backstage-venue-manager') . '</button></form>';
    }
    bvmgr_staffing_notify_render($plan, true);
    echo '</div>';
}
add_action('admin_menu', static function (): void { add_submenu_page(null, __('Staffing notifications', 'backstage-venue-manager'), __('Staffing notifications', 'backstage-venue-manager'), 'edit_posts', 'bvm-staffing-notifications', 'bvmgr_staffing_notify_admin_page'); });
add_action('add_meta_boxes', static function (): void {
    add_meta_box('bvm-staffing-notifications', __('Staffing notifications', 'backstage-venue-manager'), static function ($post): void { bvmgr_staffing_notify_render((int) $post->ID); }, 'vms_event_plan', 'normal', 'default');
});

function bvmgr_staffing_notify_landing(): void
{
    auth_redirect();
    $id = isset($_GET['assignment_id']) && is_scalar($_GET['assignment_id']) ? absint($_GET['assignment_id']) : 0;
    $row = bvmgr_staffing_lifecycle_row($id);
    $operator = $row && current_user_can('edit_post', (int) $row['event_plan_id']);
    if (!$row || (!$operator && (int) get_user_meta(get_current_user_id(), '_vms_staff_id', true) !== (int) $row['staff_id'])) {
        wp_die(esc_html__('This assignment is not available to your account.', 'backstage-venue-manager'), '', array('response' => 403));
    }
    nocache_headers();
    header('Content-Type: text/html; charset=' . get_option('blog_charset'));
    bvmgr_staffing_lifecycle_enqueue_assets();
    echo '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html__('Staffing assignment', 'backstage-venue-manager') . '</title>';
    wp_print_styles(array('bvmgr-staffing-lifecycle'));
    echo '</head><body><main><h1>' . esc_html__('Your current staffing assignments', 'backstage-venue-manager') . '</h1>';
    bvmgr_staffing_render_lifecycle_controls((int) $row['event_plan_id'], $operator ? 'operator' : 'staff');
    echo '</main>'; wp_print_scripts(array('bvmgr-staffing-lifecycle')); echo '</body></html>'; exit;
}
add_action('admin_post_bvmgr_staffing_assignment', 'bvmgr_staffing_notify_landing');
add_action('admin_post_nopriv_bvmgr_staffing_assignment', static function (): void { auth_redirect(); });
