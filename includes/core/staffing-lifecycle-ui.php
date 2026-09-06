<?php
defined('ABSPATH') || exit;

function bvmgr_staffing_status_label(string $status): string
{
    $labels = array('proposed' => __('Proposed · tentative', 'backstage-venue-manager'), 'confirmed' => __('Confirmed · committed', 'backstage-venue-manager'),
        'declined' => __('Declined', 'backstage-venue-manager'), 'canceled' => __('Canceled', 'backstage-venue-manager'));
    return $labels[$status] ?? __('Unknown status', 'backstage-venue-manager');
}
function bvmgr_staffing_lifecycle_nonce(int $id, string $target, int $revision, string $context): string
{
    return 'bvmgr_staffing:' . $context . ':' . $id . ':' . $target . ':' . $revision;
}
function bvmgr_staffing_lifecycle_message(string $error): string
{
    $messages = array(
        'confirmed_overlap' => __('This person already has an overlapping confirmed shift. No change was saved.', 'backstage-venue-manager'),
        'explicit_cancellation_required' => __('Cancel active assignments using their lifecycle controls before removing staff or replacing the role.', 'backstage-venue-manager'),
        'stale_assignment' => __('This assignment changed. Refresh the staffing list before trying again.', 'backstage-venue-manager'),
        'staffing_busy' => __('Another staffing change is being saved. Please try again.', 'backstage-venue-manager'),
        'reason_required' => __('Enter a reason for this change.', 'backstage-venue-manager'),
        'assignment_expired' => __('This assignment can no longer receive a response.', 'backstage-venue-manager'),
        'staff_ineligible' => __('This staff member is no longer eligible for the role.', 'backstage-venue-manager'),
        'lifecycle_migration_required' => __('Staffing changes are unavailable until the administrator completes the staffing update.', 'backstage-venue-manager'),
        'commit_outcome_unknown' => __('The save result could not be verified. Retry this same action to check its recorded result.', 'backstage-venue-manager'),
    );
    return $messages[$error] ?? __('The assignment could not be changed. Refresh the page and try again.', 'backstage-venue-manager');
}

/** Testable authenticated request boundary; only the service writes statuses. */
function bvmgr_staffing_handle_lifecycle_request(array $request, string $context, string $method): array
{
    if ($method !== 'POST' || !is_user_logged_in()) return array('ok' => false, 'error' => 'forbidden');
    foreach (array('assignment_id','event_plan_id','revision','target','nonce','operation_id') as $field) {
        if (!isset($request[$field]) || !is_scalar($request[$field])) return array('ok' => false, 'error' => 'invalid_request');
    }
    foreach (array('assignment_id','event_plan_id','revision') as $field) {
        if (!preg_match('/^\d+$/D', (string) $request[$field]) || strlen((string) $request[$field]) > 18) return array('ok' => false, 'error' => 'invalid_request');
    }
    if ((int) $request['assignment_id'] <= 0 || (int) $request['event_plan_id'] <= 0) return array('ok' => false, 'error' => 'invalid_request');
    $id = (int) $request['assignment_id']; $revision = (int) $request['revision'];
    $target = sanitize_key((string) $request['target']);
    if (!wp_verify_nonce((string) $request['nonce'], bvmgr_staffing_lifecycle_nonce($id, $target, $revision, $context))) return array('ok' => false, 'error' => 'invalid_nonce');
    if (isset($request['reason']) && !is_scalar($request['reason'])) return array('ok' => false, 'error' => 'invalid_request');
    return bvmgr_staffing_transition_assignment($id, $target, array('context' => $context, 'event_plan_id' => (int) $request['event_plan_id'],
        'revision' => $revision, 'operation_id' => (string) $request['operation_id'], 'reason' => (string) ($request['reason'] ?? '')));
}
/** Do not expose pay overrides, private notes, or other people's assignment IDs. */
function bvmgr_staffing_lifecycle_public_result(array $result): array
{
    $public = array_intersect_key($result, array_flip(array('ok', 'noop', 'error', 'message')));
    if (!empty($result['ok']) && isset($result['assignment'])) {
        $row = $result['assignment'];
        $public['assignment'] = array('assignment_id' => (int) $row['assignment_id'], 'revision' => (int) $row['revision'], 'status' => $row['status'], 'label' => bvmgr_staffing_status_label($row['status']));
    }
    return $public;
}
function bvmgr_staffing_lifecycle_ajax(string $context): void
{
    $result = bvmgr_staffing_handle_lifecycle_request(wp_unslash($_POST), $context, strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')));
    $result['message'] = !empty($result['ok']) ? __('Assignment saved. Reload this page to refresh all staffing summaries.', 'backstage-venue-manager') : bvmgr_staffing_lifecycle_message($result['error']);
    if (!empty($result['warnings'])) $result['message'] .= ' ' . __('Overlapping tentative assignments remain. Review the proposed shifts.', 'backstage-venue-manager');
    $result = bvmgr_staffing_lifecycle_public_result($result);
    if (!empty($result['ok'])) wp_send_json_success($result);
    $status = in_array($result['error'], array('forbidden','invalid_nonce'), true) ? 403 : 409;
    wp_send_json_error($result, $status);
}
add_action('wp_ajax_bvmgr_staffing_operator_transition', static function (): void { bvmgr_staffing_lifecycle_ajax('operator'); });
add_action('wp_ajax_bvmgr_staffing_staff_transition', static function (): void { bvmgr_staffing_lifecycle_ajax('staff'); });

function bvmgr_staffing_render_lifecycle_controls(int $plan_id, string $context = 'operator'): void
{
    global $wpdb;
    $staff_id = $context === 'staff' ? (int) get_user_meta(get_current_user_id(), '_vms_staff_id', true) : 0;
    if (!is_user_logged_in() || ($context === 'operator' && ($plan_id <= 0 || !current_user_can('edit_post', $plan_id))) || ($context === 'staff' && get_post_type($staff_id) !== 'vms_staff')) return;
    bvmgr_staffing_lifecycle_enqueue_assets();
    $rows = $wpdb->get_results($wpdb->prepare('SELECT a.*, s.event_plan_id, s.role_id, s.status AS slot_status FROM %i a INNER JOIN %i s ON s.slot_id=a.slot_id WHERE (%d=0 OR s.event_plan_id=%d) AND (%d=0 OR a.staff_id=%d) ORDER BY s.event_plan_id, s.role_id, a.assignment_id',
        bvmgr_staffing_table_name('assignments'), bvmgr_staffing_table_name('event_slots'), $plan_id, $plan_id, $staff_id, $staff_id), ARRAY_A);
    echo '<section class="bvm-staffing-lifecycle" data-staffing-saving="' . esc_attr__('Saving assignment…', 'backstage-venue-manager') . '" data-staffing-network-error="' . esc_attr__('The save result could not be verified. Retry this same action to check its recorded result.', 'backstage-venue-manager') . '" data-staffing-url="' . esc_url(admin_url('admin-ajax.php')) . '"><h4>' . esc_html__('Assignment responses', 'backstage-venue-manager') . '</h4>';
    if ($context === 'operator') echo '<p>' . esc_html__('Use the matrix to propose new staff. Confirm, cancel, or repropose existing assignments here. Existing states are preserved by matrix saves.', 'backstage-venue-manager') . '</p>';
    if ($context === 'operator') {
        $snapshot = bvmgr_staffing_resolve_event_snapshot($plan_id);
        echo '<p class="bvm-staffing-counts">' . esc_html(sprintf(
            /* translators: 1: planned positions, 2: active assignments, 3: proposed assignments, 4: confirmed assignments, 5: open planned positions, 6: open required positions. */
            __('Planned %1$d · Assigned %2$d · Proposed %3$d · Confirmed %4$d · Open positions %5$d · Required open now %6$d', 'backstage-venue-manager'),
            $snapshot['planned_headcount'], $snapshot['assigned_headcount'], $snapshot['proposed_headcount'], $snapshot['confirmed_headcount'], $snapshot['open_positions'], $snapshot['open_headcount_total']
        )) . '</p>';
    }
    echo '<p role="status" aria-live="polite" data-staffing-feedback></p>';
    $shown = 0;
    foreach ($rows as $row) {
        $plan = (int) $row['event_plan_id'];
        $date = (string) get_post_meta($plan, '_vms_event_date', true);
        if ($context === 'staff' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) || $date < wp_date('Y-m-d') || !in_array(bvmgr_event_plan_get_status($plan, 'dashboard'), array('ready','published','tentative','confirmed'), true))) continue;
        $shown++;
        $window = bvmgr_staffing_lifecycle_window($row);
        $active = $row['slot_status'] === 'active' && (int) ($window['end_ts'] ?? 0) > time() && (int) ($window['start_ts'] ?? 0) > 0 && (int) $window['end_ts'] > (int) $window['start_ts'] && $date >= wp_date('Y-m-d');
        $role = get_term((int) $row['role_id'], 'vms_staff_role');
        echo '<div class="bvm-staffing-assignment" data-staffing-assignment="' . esc_attr((string) $row['assignment_id']) . '"><strong>' . esc_html($context === 'staff' ? get_the_title($plan) : get_the_title((int) $row['staff_id'])) . '</strong> · ' . esc_html($role instanceof WP_Term ? $role->name : __('Role', 'backstage-venue-manager'));
        echo '<p>' . esc_html($date . ' ' . (!empty($window['start_ts']) ? wp_date('g:ia', $window['start_ts']) : '') . '–' . (!empty($window['end_ts']) ? wp_date('g:ia', $window['end_ts']) : '')) . '</p>';
        echo '<span class="bvm-staffing-state is-' . esc_attr(sanitize_key($row['status'])) . '" data-staffing-state>' . esc_html(bvmgr_staffing_status_label($row['status'])) . '</span> ';
        if ($row['slot_status'] === 'active' && in_array($row['status'], array('proposed','confirmed'), true)) {
            $overlap = bvmgr_staffing_assignment_overlaps($row, $window);
            if ($overlap['soft'] || $overlap['hard']) echo '<p class="bvm-staffing-warning">' . esc_html($row['status'] === 'confirmed' && $overlap['hard'] ? __('Confirmed shift conflict. Review this assignment.', 'backstage-venue-manager') : __('Overlapping assignments: proposed coverage is tentative.', 'backstage-venue-manager')) . '</p>';
        }
        $actions = array();
        if ($active && $row['status'] === 'proposed') $actions = $context === 'staff' ? array('confirmed' => __('Accept','backstage-venue-manager'),'declined' => __('Decline','backstage-venue-manager')) : array('confirmed' => __('Confirm','backstage-venue-manager'));
        if ($context === 'operator' && in_array($row['status'], array('proposed','confirmed'), true)) $actions['canceled'] = __('Cancel','backstage-venue-manager');
        if ($context === 'operator' && $active && in_array($row['status'], array('declined','canceled'), true)) $actions['proposed'] = __('Repropose','backstage-venue-manager');
        if (isset($actions['proposed']) || (isset($actions['canceled']) && ($row['status'] === 'confirmed' || !$active))) {
            $reason_id = 'bvm-staffing-reason-' . $context . '-' . (int) $row['assignment_id'];
            echo '<p><label for="' . esc_attr($reason_id) . '">' . esc_html__('Reason for cancellation or reproposal', 'backstage-venue-manager') . '</label><input id="' . esc_attr($reason_id) . '" type="text" maxlength="500" data-staffing-reason-input></p>';
        }
        foreach ($actions as $target => $label) {
            if (!isset($row['revision'])) continue;
            $revision = (int) $row['revision'];
            $reason = $target === 'proposed' || ($target === 'canceled' && ($row['status'] === 'confirmed' || !$active));
            echo '<button type="button" class="button" data-staffing-action="' . esc_attr($target) . '" data-staffing-context="' . esc_attr($context) . '" data-staffing-plan="' . esc_attr((string) $plan) . '" data-staffing-revision="' . esc_attr((string) $revision) . '" data-staffing-operation="' . esc_attr(wp_generate_uuid4()) . '" data-staffing-nonce="' . esc_attr(wp_create_nonce(bvmgr_staffing_lifecycle_nonce((int) $row['assignment_id'], $target, $revision, $context))) . '" data-staffing-reason="' . ($reason ? '1' : '0') . '" data-staffing-reason-label="' . esc_attr__('Reason for this change:', 'backstage-venue-manager') . '">' . esc_html($label) . '</button> ';
        }
        echo '</div>';
    }
    if (!$shown) echo '<p>' . esc_html__('No assignments are available for a response.', 'backstage-venue-manager') . '</p>';
    echo '</section>';
}

function bvmgr_staffing_lifecycle_enqueue_assets(): void
{
    wp_enqueue_script('bvmgr-staffing-lifecycle', BVMGR_PLUGIN_URL . 'assets/js/vms-staffing-lifecycle.js', array(), '1.0.0', true);
    wp_enqueue_style('bvmgr-staffing-lifecycle', BVMGR_PLUGIN_URL . 'assets/css/vms-staffing-lifecycle.css', array(), '1.0.0');
}
add_action('admin_enqueue_scripts', static function (): void {
    $screen = get_current_screen();
    if ($screen && $screen->post_type === 'vms_event_plan' && in_array($screen->base, array('post', 'post-new'), true)) bvmgr_staffing_lifecycle_enqueue_assets();
});
