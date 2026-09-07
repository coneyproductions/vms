<?php
/** Read-only presentation adapters for optional operational workspaces. */
defined('ABSPATH') || exit;

function bvmgr_event_command_center_event_day_url(int $plan_id): string
{
    $capability = function_exists('bvmgr_admission_manage_capability') ? bvmgr_admission_manage_capability() : 'manage_options';
    return $plan_id > 0 && get_post_type($plan_id) === 'vms_event_plan'
        && function_exists('bvmgr_event_day_report_url') && current_user_can($capability)
        ? (string) bvmgr_event_day_report_url($plan_id) : '';
}

function bvmgr_event_command_center_get_operational_context(int $plan_id): array
{
    $out = array(
        'communications' => array('available' => false, 'pending' => 0, 'failed' => 0, 'review_required' => 0, 'summary' => __('Customer notice status unavailable.', 'backstage-venue-manager'), 'url' => ''),
        'documents' => array('available' => false, 'issues' => array(), 'summary' => __('Agreement status unavailable.', 'backstage-venue-manager'), 'url' => ''),
        'tools' => array(),
        'marketing_tools' => array(),
        'event_day_url' => '',
    );
    if ($plan_id <= 0 || get_post_type($plan_id) !== 'vms_event_plan') {
        return $out;
    }
    $report_url = bvmgr_event_command_center_event_day_url($plan_id);
    $out['event_day_url'] = $report_url;
    if ($report_url !== '') {
        $out['tools'][] = array('label' => __('Event-Day Guest List / Report', 'backstage-venue-manager'), 'url' => $report_url);
    }
    $can_edit = current_user_can('edit_post', $plan_id);
    if ($can_edit && function_exists('bvmgr_admission_render_event_plan_metabox') && function_exists('bvmgr_event_command_center_edit_fragment_url')
        && current_user_can(function_exists('bvmgr_admission_manage_capability') ? bvmgr_admission_manage_capability() : 'manage_options')) {
        $out['tools'][] = array('label' => __('Admissions / check-in', 'backstage-venue-manager'), 'url' => bvmgr_event_command_center_edit_fragment_url($plan_id, 'vms_guest_list_comp_admission'));
    }
    if (current_user_can('manage_options') && function_exists('bvmgr_admin_ui_registered_page_url')) {
        $profit_url = bvmgr_admin_ui_registered_page_url('vms-event-profitability');
        if ($profit_url !== '') {
            // This report supports title search, not an exact event ID filter.
            $out['tools'][] = array('label' => __('Profitability report (all events)', 'backstage-venue-manager'), 'url' => $profit_url);
        }
        foreach (array('vms-social-sharing' => __('Social sharing workspace', 'backstage-venue-manager'), 'vms-marketing-social' => __('Marketing workspace', 'backstage-venue-manager')) as $slug => $label) {
            $url = bvmgr_admin_ui_registered_page_url($slug);
            if ($url !== '') { $out['marketing_tools'][] = array('label' => $label, 'url' => $url); }
        }
    }
    if ($can_edit && function_exists('bvmgr_event_occurrence_history') && function_exists('bvmgr_event_communication_get_ledger') && function_exists('bvmgr_event_communication_summary')) {
        try {
            $communications = $out['communications'];
            $communications['url'] = function_exists('bvmgr_event_communication_admin_url') ? bvmgr_event_communication_admin_url($plan_id) : '';
            $seen = array();
            foreach (bvmgr_event_occurrence_history($plan_id) as $entry) {
                if (!is_array($entry)) { continue; }
                $operation_id = (string) ($entry['operation_id'] ?? '');
                if ($operation_id === '' || isset($seen[$operation_id])) { continue; }
                $seen[$operation_id] = true;
                $ledger = bvmgr_event_communication_get_ledger($plan_id, $operation_id);
                if (!$ledger) {
                    $impact = (array) ($entry['impact_counts'] ?? array());
                    if ((int) ($impact['customers'] ?? 0) > 0 || (int) ($impact['custom_admission_rows'] ?? 0) > 0) {
                        $communications['review_required']++;
                    }
                    continue;
                }
                $summary = bvmgr_event_communication_summary($ledger);
                $communications['pending'] += max(0, (int) ($summary['pending'] ?? 0));
                $communications['failed'] += max(0, (int) ($summary['failed'] ?? 0));
                foreach ((array) ($ledger['recipient_states'] ?? array()) as $state) {
                    if (function_exists('bvmgr_event_communication_has_unfinished_attempt') && bvmgr_event_communication_has_unfinished_attempt((array) $state)) {
                        $communications['review_required']++;
                    }
                }
            }
            $communications['available'] = true;
            $communications['summary'] = $seen
                ? sprintf(
                    /* translators: 1: pending occurrence-change notices, 2: failed notices, 3: records requiring review. */
                    __('Occurrence-change notices: %1$d pending, %2$d failed, %3$d requiring review.', 'backstage-venue-manager'),
                    $communications['pending'], $communications['failed'], $communications['review_required'])
                : __('No occurrence-change notices are recorded. Marketing and outreach have separate workflows.', 'backstage-venue-manager');
            $out['communications'] = $communications;
        } catch (Throwable $error) {
            // A failed optional read must not imply that messages were delivered.
        }
    }
    if ($can_edit && function_exists('vmsa_can_manage') && function_exists('vmsa_get_event_plan_packet_ids') && function_exists('vmsa_build_operator_queue_row') && function_exists('vmsa_admin_page_url')) {
        try {
            if (vmsa_can_manage()) {
                $documents = $out['documents'];
                $documents['url'] = function_exists('vmsa_agreement_setup_url') ? vmsa_agreement_setup_url($plan_id) : '';
                $active = 0;
                foreach (vmsa_get_event_plan_packet_ids($plan_id) as $packet_id) {
                    $row = vmsa_build_operator_queue_row((int) $packet_id);
                    if (!$row || (int) ($row['event_id'] ?? 0) !== $plan_id || ($row['bucket'] ?? '') === 'history') { continue; }
                    $active++;
                    if (($row['status'] ?? '') === 'acknowledged' && ($row['terms_state'] ?? '') === 'current') { continue; }
                    $documents['issues'][] = array(
                        'title' => (string) ($row['title'] ?? __('Agreement needs review', 'backstage-venue-manager')),
                        'detail' => trim((string) ($row['status_label'] ?? '') . ' · ' . (string) ($row['terms_label'] ?? '')),
                        'action_label' => __('Review agreement', 'backstage-venue-manager'),
                        'action_url' => vmsa_admin_page_url(array('packet_id' => (int) $packet_id)),
                    );
                }
                $documents['available'] = true;
                $documents['summary'] = $active > 0
                    ? sprintf(
                        /* translators: 1: active agreement packets, 2: packets requiring review. */
                        __('%1$d active agreement packets; %2$d require review.', 'backstage-venue-manager'), $active, count($documents['issues']))
                    : __('No active agreement packets. Document requirements are managed in Agreements.', 'backstage-venue-manager');
                $out['documents'] = $documents;
            }
        } catch (Throwable $error) {
            // Optional add-on failure remains unavailable rather than an all-clear.
        }
    }
    if ($can_edit && function_exists('bvmgr_staff_portal_get_event_tech_docs') && function_exists('bvmgr_vendor_portal_user_can_download_tech_doc')) {
        try {
            foreach (bvmgr_staff_portal_get_event_tech_docs($plan_id) as $doc) {
                if (!is_array($doc) || empty($doc['url']) || !bvmgr_vendor_portal_user_can_download_tech_doc((int) ($doc['vendor_id'] ?? 0), (string) ($doc['doc_key'] ?? ''), $plan_id)) { continue; }
                $out['tools'][] = array('label' => trim((string) ($doc['vendor_name'] ?? '') . ' — ' . (string) ($doc['label'] ?? '')), 'url' => (string) $doc['url']);
            }
        } catch (Throwable $error) {
            // A private document cannot become a public media URL fallback.
        }
    }
    if ($can_edit && current_user_can('manage_options') && class_exists('WooCommerce') && (string) get_post_meta($plan_id, '_vms_express_bar_enabled', true) === '1' && function_exists('bvmgr_admin_ui_registered_page_url')) {
        $bar_url = bvmgr_admin_ui_registered_page_url('vms-express-bar');
        if ($bar_url !== '') {
            $out['tools'][] = array('label' => __('Express Bar orders', 'backstage-venue-manager'), 'url' => add_query_arg('event_plan_id', $plan_id, $bar_url));
        }
    }
    return $out;
}

function bvmgr_event_command_center_get_operational_weather(int $plan_id, array $fallback): array
{
    $out = array_merge($fallback, array('state' => 'unavailable', 'concern' => false, 'window_label' => '', 'freshness_label' => '', 'label' => __('Unavailable', 'backstage-venue-manager'), 'summary' => __('No current weather assessment is available.', 'backstage-venue-manager')));
    if ($plan_id <= 0 || get_post_type($plan_id) !== 'vms_event_plan'
        || !is_callable(array('VMSX_Weather_Risk_Advisory_Engine', 'get_snapshot'))
        || !is_callable(array('VMSX_Weather_Risk_Capabilities', 'can_view_event'))
        || !is_callable(array('VMSX_Weather_Risk_Settings', 'get'))) {
        return $out;
    }
    try {
        if (!VMSX_Weather_Risk_Capabilities::can_view_event($plan_id)) {
            $out['url'] = '';
            return $out;
        }
        if (is_callable(array('VMSX_Weather_Risk_Admin_Menu', 'details_url'))) {
            $out['url'] = VMSX_Weather_Risk_Admin_Menu::details_url($plan_id);
        }
        $settings = VMSX_Weather_Risk_Settings::get();
        if (empty($settings['enabled'])) {
            $out['active'] = false;
            $out['summary'] = __('Weather Risk is disabled for this site.', 'backstage-venue-manager');
            return $out;
        }
        $out['active'] = true;
        $snapshot = VMSX_Weather_Risk_Advisory_Engine::get_snapshot($plan_id);
        if (!is_array($snapshot) || (int) ($snapshot['event_id'] ?? 0) !== $plan_id) { return $out; }
        $weather = (array) ($snapshot['weather_risk'] ?? array());
        $window = (array) ($snapshot['window'] ?? array());
        $band = (string) ($weather['band'] ?? '');
        $computed = (int) ($snapshot['computed_at_utc'] ?? 0);
        if ($computed <= 0 || $computed > time() || empty($window['ok']) || !in_array($band, array('Low', 'Watch', 'High', 'Critical'), true) || (int) ($snapshot['provider_health']['responded'] ?? 0) <= 0) { return $out; }
        $out['window_label'] = (string) ($window['label'] ?? '');
        $out['freshness_label'] = sprintf(
            /* translators: %s: elapsed time since weather assessment. */
            __('Assessed %s ago', 'backstage-venue-manager'), human_time_diff($computed, time()));
        // Match Weather Risk 0.1.12's documented scheduler cadence, using today's distance.
        $days = (int) floor(((int) ($window['event_start_utc'] ?? 0) - time()) / DAY_IN_SECONDS);
        $interval = $days <= 2 ? HOUR_IN_SECONDS : ($days <= 5 ? 4 * HOUR_IN_SECONDS : DAY_IN_SECONDS);
        $stale = time() - $computed >= $interval || (int) ($window['window_end_utc'] ?? 0) < time();
        $out['state'] = $stale ? 'stale' : 'current';
        $out['concern'] = in_array($band, array('Watch', 'High', 'Critical'), true);
        $out['label'] = $stale ? __('Assessment out of date', 'backstage-venue-manager') : $band;
        $reasons = array_values(array_filter((array) ($weather['reasons'] ?? array()), 'is_string'));
        $out['summary'] = $reasons ? (string) $reasons[0] : ($out['concern']
            ? __('Elevated weather risk in the cached event window. Review the weather details.', 'backstage-venue-manager')
            : __('No elevated weather concern in the cached event window.', 'backstage-venue-manager'));
        if ($stale) {
            $out['summary'] = __('Review Weather Risk for an updated assessment. ', 'backstage-venue-manager') . $out['summary'];
        }
    } catch (Throwable $error) {
        $out['state'] = 'unavailable';
        $out['concern'] = false;
        $out['label'] = __('Unavailable', 'backstage-venue-manager');
        $out['summary'] = __('The weather assessment could not be read.', 'backstage-venue-manager');
    }
    return $out;
}
