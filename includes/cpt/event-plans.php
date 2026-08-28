<?php

/**
 * VMS Event Plans — Admin + Publishing
 *
 * Notes:
 * - This file intentionally contains ONLY Event Plan related logic.
 * - It assumes some helper functions exist elsewhere in the plugin (ex: vms_get_current_venue_id()).
 */

if (!defined('ABSPATH')) {
    exit;
}
 
/**
 * Secondary Vendor Qualification (V1)
 *
 * Default rule (can be overridden via filters):
 * - Vendor must have at least one contact method (email or phone).
 *
 * Customization hooks:
 * - vms_secondary_vendor_missing_items (array $missing, int $vendor_id, array $ctx)
 */
if (!function_exists('bvmgr_secondary_vendor_missing_items')) {
    function bvmgr_secondary_vendor_missing_items(int $vendor_id, array $ctx = array()): array
    {
        $missing = array();

        $vp = get_post($vendor_id);
        if (!$vp || $vp->post_type !== 'vms_vendor' || $vp->post_status === 'trash') {
            $missing[] = 'Vendor missing';
            return $missing;
        }

        $k_primary_email = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'primary_email') : '_vms_vendor_primary_email';
        $k_primary_phone = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'primary_phone') : '_vms_vendor_primary_phone';
        $k_email_legacy  = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'email') : '_vms_vendor_email';
        $k_phone_legacy  = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'phone') : '_vms_vendor_phone';

        $primary_email = trim((string) get_post_meta($vendor_id, $k_primary_email, true));
        $primary_phone = trim((string) get_post_meta($vendor_id, $k_primary_phone, true));
        $legacy_email  = trim((string) get_post_meta($vendor_id, $k_email_legacy, true));
        $legacy_phone  = trim((string) get_post_meta($vendor_id, $k_phone_legacy, true));

        if ($primary_email === '' && $primary_phone === '' && $legacy_email === '' && $legacy_phone === '') {
            $missing[] = 'Contact info';
        }

        if (function_exists('apply_filters')) {
            $missing = (array) apply_filters('vms_secondary_vendor_missing_items', $missing, $vendor_id, $ctx);
        }

        $missing = array_values(array_unique(array_filter(array_map(function ($s) {
            $s = trim((string) $s);
            return $s !== '' ? $s : null;
        }, $missing))));

	        return $missing;
	    }
	}

if (!function_exists('bvmgr_secondary_vendor_is_qualified')) {
    function bvmgr_secondary_vendor_is_qualified(int $vendor_id, array $ctx = array()): bool
    {
        $missing = function_exists('bvmgr_secondary_vendor_missing_items')
            ? (array) bvmgr_secondary_vendor_missing_items($vendor_id, $ctx)
            : array();

        $ok = empty($missing);

        if (function_exists('apply_filters')) {
            $ok = (bool) apply_filters('vms_secondary_vendor_is_qualified', $ok, $vendor_id, $ctx, $missing);
		}

        return (bool) $ok;
    }
}



/**
 * Register CPT: vms_event_plan
 */
add_action('init', 'bvmgr_register_event_plan_cpt');
function bvmgr_register_event_plan_cpt(): void
{
    register_post_type('vms_event_plan', array(
        'labels' => array(
            'name'          => __('Event Plans', 'backstage-venue-manager'),
            'singular_name' => __('Event Plan', 'backstage-venue-manager'),
            'menu_name'     => __('Event Plans', 'backstage-venue-manager'),
        ),
        'public'          => false,
        'show_ui'         => true,
        'show_in_menu'    => false,
        'menu_icon'       => 'dashicons-calendar-alt',
        'supports'        => array('title', 'editor', 'thumbnail'),
        'capability_type' => 'post',
        'has_archive'     => false,
        'rewrite'         => false,
    ));
}

if (!function_exists('bvmgr_event_plan_admin_edit_url')) {
    function bvmgr_event_plan_admin_edit_url(int $post_id, array $query_args = array(), string $fragment = '', string $context = 'raw'): string
    {
        $post_id = absint($post_id);
        if ($post_id <= 0) {
            $url = admin_url('edit.php?post_type=vms_event_plan');
        } else {
            $url = add_query_arg(
                array(
                    'post'   => $post_id,
                    'action' => 'edit',
                ),
                admin_url('post.php')
            );
        }

        if (!empty($query_args)) {
            $url = add_query_arg($query_args, $url);
        }

        $fragment = ltrim(trim($fragment), '#');
        if ($fragment !== '') {
            $url .= '#' . $fragment;
        }

        if ($context === 'display') {
            $url = str_replace('&', '&amp;', $url);
        }

        return $url;
    }
}

if (!function_exists('bvmgr_event_plan_is_canonical_edit_link')) {
    function bvmgr_event_plan_is_canonical_edit_link($link, int $post_id): bool
    {
        if (!is_string($link) || trim($link) === '') {
            return false;
        }

        $parts = wp_parse_url($link);
        if (!is_array($parts)) {
            return false;
        }

        $path = isset($parts['path']) ? wp_basename((string) $parts['path']) : '';
        if ($path !== 'post.php') {
            return false;
        }

        $query_args = array();
        if (!empty($parts['query'])) {
            parse_str((string) $parts['query'], $query_args);
        }

        return absint($query_args['post'] ?? 0) === $post_id
            && sanitize_key((string) ($query_args['action'] ?? '')) === 'edit';
    }
}

if (!function_exists('bvmgr_event_plan_force_edit_post_link')) {
    /**
     * Guarantee a real edit link for Event Plans before core builds the save redirect.
     *
     * Some admin/plugin flows can filter the edit link to empty or to a non-editor URL.
     * Force the canonical Event Plan editor link so both core and later filters have a
     * stable base URL to work with.
     */
    function bvmgr_event_plan_force_edit_post_link($link, int $post_id, string $context = 'display')
    {
        if ($post_id <= 0 || get_post_type($post_id) !== 'vms_event_plan') {
            return $link;
        }

        if (bvmgr_event_plan_is_canonical_edit_link($link, $post_id)) {
            return $link;
        }

        return bvmgr_event_plan_admin_edit_url($post_id, array(), '', $context);
    }
}
add_filter('get_edit_post_link', 'bvmgr_event_plan_force_edit_post_link', 999, 3);

if (!function_exists('bvmgr_event_plan_force_redirect_post_location')) {
    /**
     * Guard the Event Plan save redirect against null/incorrect edit URLs.
     *
     * WordPress builds the post-save redirect from get_edit_post_link(). When that
     * unexpectedly resolves empty, core falls through to a bad redirect and the save
     * appears to dump operators into the generic Posts list. Force Event Plans back
     * to their editor while preserving the original query flags like `message=4`.
     */
    function bvmgr_event_plan_force_redirect_post_location($location, int $post_id)
    {
        if ($post_id <= 0 || get_post_type($post_id) !== 'vms_event_plan') {
            return $location;
        }

        $query_args = array();
        $fragment = '';

        if (is_string($location) && $location !== '') {
            $parts = wp_parse_url($location);
            if (is_array($parts)) {
                if (!empty($parts['query'])) {
                    parse_str((string) $parts['query'], $query_args);
                }
                if (!empty($parts['fragment'])) {
                    $fragment = (string) $parts['fragment'];
                }
            }
        }

        unset($query_args['post'], $query_args['action'], $query_args['post_type']);

        $target = function_exists('bvmgr_event_plan_pull_runtime_redirect_target')
            ? (array) bvmgr_event_plan_pull_runtime_redirect_target($post_id)
            : array();
        $target_post_id = absint($target['target_post_id'] ?? 0);
        if ($target_post_id > 0 && get_post_type($target_post_id) === 'vms_event_plan') {
            $extra_query_args = isset($target['query_args']) && is_array($target['query_args']) ? $target['query_args'] : array();
            $query_args = array_merge($query_args, $extra_query_args);
            $fragment = isset($target['fragment']) ? (string) $target['fragment'] : $fragment;
            return bvmgr_event_plan_admin_edit_url($target_post_id, $query_args, $fragment);
        }

        return bvmgr_event_plan_admin_edit_url($post_id, $query_args, $fragment);
    }
}
add_filter('redirect_post_location', 'bvmgr_event_plan_force_redirect_post_location', 999, 2);

if (!function_exists('bvmgr_event_plan_runtime_redirect_targets')) {
    function &bvmgr_event_plan_runtime_redirect_targets(): array
    {
        if (!isset($GLOBALS['bvmgr_event_plan_runtime_redirect_targets']) || !is_array($GLOBALS['bvmgr_event_plan_runtime_redirect_targets'])) {
            $GLOBALS['bvmgr_event_plan_runtime_redirect_targets'] = array();
        }

        return $GLOBALS['bvmgr_event_plan_runtime_redirect_targets'];
    }
}

if (!function_exists('bvmgr_event_plan_set_runtime_redirect_target')) {
    function bvmgr_event_plan_set_runtime_redirect_target(int $source_post_id, int $target_post_id, array $query_args = array(), string $fragment = ''): void
    {
        $source_post_id = absint($source_post_id);
        $target_post_id = absint($target_post_id);
        if ($source_post_id <= 0 || $target_post_id <= 0) {
            return;
        }

        $targets =& bvmgr_event_plan_runtime_redirect_targets();
        $targets[$source_post_id] = array(
            'target_post_id' => $target_post_id,
            'query_args'     => is_array($query_args) ? $query_args : array(),
            'fragment'       => (string) $fragment,
        );
    }
}

if (!function_exists('bvmgr_event_plan_pull_runtime_redirect_target')) {
    function bvmgr_event_plan_pull_runtime_redirect_target(int $source_post_id): array
    {
        $source_post_id = absint($source_post_id);
        if ($source_post_id <= 0) {
            return array();
        }

        $targets =& bvmgr_event_plan_runtime_redirect_targets();
        if (empty($targets[$source_post_id]) || !is_array($targets[$source_post_id])) {
            return array();
        }

        $target = $targets[$source_post_id];
        unset($targets[$source_post_id]);

        return $target;
    }
}

if (!function_exists('bvmgr_event_plan_reopenable_sections')) {
    function bvmgr_event_plan_reopenable_sections(): array
    {
        return array(
            'secondary_vendors' => 'vms-additional-vendors',
            'staff' => 'vms-staffing',
            'compensation' => 'vms-compensation',
            'cancellation' => 'vms-cancellation',
            'readiness_details' => 'vms-readiness-details',
            'ticketing_v2' => 'vms_event_plan_ticketing_v2',
        );
    }
}

if (!function_exists('bvmgr_event_plan_normalize_reopen_section')) {
    function bvmgr_event_plan_normalize_reopen_section(string $section): string
    {
        $section = sanitize_key($section);
        if ($section === '') {
            return '';
        }

        $allowed = bvmgr_event_plan_reopenable_sections();
        return isset($allowed[$section]) ? $section : '';
    }
}

if (!function_exists('bvmgr_event_plan_reopen_section_fragment')) {
    function bvmgr_event_plan_reopen_section_fragment(string $section): string
    {
        $section = bvmgr_event_plan_normalize_reopen_section($section);
        if ($section === '') {
            return '';
        }

        $sections = bvmgr_event_plan_reopenable_sections();
        return isset($sections[$section]) ? (string) $sections[$section] : '';
    }
}

if (!function_exists('bvmgr_event_plan_set_runtime_reopen_section_target')) {
    function bvmgr_event_plan_set_runtime_reopen_section_target(int $post_id, string $section): void
    {
        $post_id = absint($post_id);
        $section = bvmgr_event_plan_normalize_reopen_section($section);
        if ($post_id <= 0 || $section === '') {
            return;
        }

        $fragment = bvmgr_event_plan_reopen_section_fragment($section);
        bvmgr_event_plan_set_runtime_redirect_target(
            $post_id,
            $post_id,
            array(
                'vms_ep_load_section' => $section,
            ),
            $fragment
        );
    }
}

if (!function_exists('bvmgr_event_plan_normalize_related_plan_ids')) {
    function bvmgr_event_plan_normalize_related_plan_ids($value): array
    {
        if (!is_array($value)) {
            $value = array();
        }

        $ids = array_map('absint', $value);
        $ids = array_values(array_unique(array_filter($ids, static function ($id) {
            return $id > 0 && get_post_type($id) === 'vms_event_plan';
        })));

        return $ids;
    }
}

if (!function_exists('bvmgr_event_plan_current_get_request')) {
    function bvmgr_event_plan_current_get_request(): array
    {
        static $request = null;
        if (is_array($request)) {
            return $request;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only admin editor/query routing is centralized here; callers sanitize by expected type.
        $request = (isset($_GET) && is_array($_GET)) ? wp_unslash($_GET) : array();
        return is_array($request) ? $request : array();
    }
}

if (!function_exists('bvmgr_event_plan_current_post_request')) {
    function bvmgr_event_plan_current_post_request(): array
    {
        static $request = null;
        static $cache_generation = null;
        $current_generation = max(0, (int) ($GLOBALS['bvmgr_event_plan_request_cache_generation'] ?? 0));
        if (is_array($request) && $cache_generation === $current_generation) {
            return $request;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Event Plan save handlers validate nonce/capability before acting on these normalized values.
        $request = (isset($_POST) && is_array($_POST)) ? wp_unslash($_POST) : array();
        $cache_generation = $current_generation;
        return is_array($request) ? $request : array();
    }
}

if (!function_exists('bvmgr_event_plan_current_request_data')) {
    function bvmgr_event_plan_current_request_data(): array
    {
        static $request = null;
        if (is_array($request)) {
            return $request;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Action-specific handlers validate nonce/capability after this request snapshot is normalized.
        $request = (isset($_REQUEST) && is_array($_REQUEST)) ? wp_unslash($_REQUEST) : array();
        return is_array($request) ? $request : array();
    }
}

if (!function_exists('bvmgr_event_plan_editor_verified_post_data')) {
    function bvmgr_event_plan_editor_verified_post_data(): array
    {
        static $request = null;
        static $cache_generation = null;
        $current_generation = max(0, (int) ($GLOBALS['bvmgr_event_plan_request_cache_generation'] ?? 0));
        if (is_array($request) && $cache_generation === $current_generation) {
            return $request;
        }

        $request = bvmgr_event_plan_current_post_request();
        $nonce = isset($request['bvmgr_event_plan_details_nonce']) && !is_array($request['bvmgr_event_plan_details_nonce'])
            ? sanitize_text_field((string) $request['bvmgr_event_plan_details_nonce'])
            : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, bvmgr_nonce_action_for_value($nonce, 'bvmgr_save_event_plan_details'))) {
            $request = array();
        }
        $cache_generation = $current_generation;

        return $request;
    }
}

if (!function_exists('bvmgr_event_plan_create_rescheduled_draft')) {
    function bvmgr_event_plan_create_rescheduled_draft(int $source_post_id, array $args = array()): array
    {
        $source_post_id = absint($source_post_id);
        if ($source_post_id <= 0) {
            return array(
                'ok' => false,
                'error' => 'invalid_source',
                'error_message' => __('The cancelled Event Plan could not be found.', 'backstage-venue-manager'),
            );
        }

        $source = get_post($source_post_id);
        if (!$source || $source->post_type !== 'vms_event_plan') {
            return array(
                'ok' => false,
                'error' => 'invalid_source',
                'error_message' => __('The cancelled Event Plan could not be found.', 'backstage-venue-manager'),
            );
        }

        if (!current_user_can('edit_post', $source_post_id)) {
            return array(
                'ok' => false,
                'error' => 'forbidden',
                'error_message' => __('You do not have permission to reschedule this Event Plan.', 'backstage-venue-manager'),
            );
        }

        $k_status = function_exists('bvmgr_meta_key')
            ? (bvmgr_meta_key('event_plan', 'status') ?: '_vms_event_plan_status')
            : '_vms_event_plan_status';
        $current_status = sanitize_key((string) get_post_meta($source_post_id, $k_status, true));
        if ($current_status === '') {
            $current_status = 'draft';
        }
        if ($current_status !== 'cancelled') {
            return array(
                'ok' => false,
                'error' => 'source_not_cancelled',
                'error_message' => __('Only cancelled Event Plans can create a rescheduled draft.', 'backstage-venue-manager'),
            );
        }

        $replacement_date = isset($args['replacement_date']) ? sanitize_text_field(wp_unslash((string) $args['replacement_date'])) : '';
        if ($replacement_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $replacement_date)) {
            return array(
                'ok' => false,
                'error' => 'missing_replacement_date',
                'error_message' => __('Enter a valid replacement date before creating the rescheduled draft.', 'backstage-venue-manager'),
            );
        }

        $title = trim((string) ($args['post_title'] ?? $source->post_title));
        if ($title === '') {
            /* translators: %d: event plan ID. */
            $title = sprintf(__('Event Plan #%d', 'backstage-venue-manager'), $source_post_id);
        }

        $new_post_id = 0;
        if (function_exists('bvmgr_duplicate_post_with_meta_and_terms')) {
            $new_post_id = (int) bvmgr_duplicate_post_with_meta_and_terms($source_post_id, array(
                'post_status' => 'draft',
                'post_title'  => $title,
                'post_author' => get_current_user_id(),
            ));
        }

        if ($new_post_id <= 0) {
            return array(
                'ok' => false,
                'error' => 'duplicate_failed',
                'error_message' => __('Backstage Venue Manager could not create the replacement Event Plan draft.', 'backstage-venue-manager'),
            );
        }

        $meta_keys_to_clear = array(
            '_edit_lock',
            '_edit_last',
            '_wp_old_slug',
            '_vms_import_event_key',
            '_vms_tec_event_id',
            '_vms_tec_event_url',
            '_vms_wc_product_map',
            '_vms_ticket_product_ids_v1',
            '_vms_ticket_stats_v1',
            '_vms_tickets_sold_count',
            '_vms_ticket_tier_map_v1',
            '_vms_ticketing_sync_v2',
            '_vms_ticketing_stats_v2',
            '_vms_ticketing_migration_snapshot_v1',
            '_vms_ticket_manual_product_ids_v1',
            '_vms_calendar_unpublished_suppress',
            '_vms_event_plan_start_datetime',
            '_vms_event_plan_end_datetime',
            '_vms_synced_vendor_category_term_ids',
            '_vms_cancel_policy',
            '_vms_cancel_reason_code',
            '_vms_cancel_reason_note',
            '_vms_cancel_vendor_message',
            '_vms_cancelled_at_gmt',
            '_vms_cancelled_by_user_id',
            '_vms_cancel_job_id',
            '_vms_cancel_job_state',
            '_vms_cancel_job_summary',
            '_vms_cancel_requires_operator_review',
            '_vms_social_unpublished_after_post',
            '_vms_comp_snapshot',
            '_vms_comp_needs_snapshot',
            '_vms_pay_override_ack',
            '_vms_pay_override_ack_ts',
            '_vms_pay_override_ack_user_id',
            '_vms_pay_override_ack_default_snapshot',
            '_vms_pay_override_ack_actual_snapshot',
            '_vms_pay_default_source',
            '_vms_pay_default_structure',
            '_vms_pay_default_flat_fee_amount',
            '_vms_pay_default_door_split_percent',
            '_vms_pay_default_attendance_bonus_mode',
            '_vms_pay_default_attendance_bonus_start_count',
            '_vms_pay_default_attendance_bonus_step_size',
            '_vms_pay_default_attendance_bonus_step_bonus',
            '_vms_pay_default_attendance_bonus_per_ticket_rate',
            '_vms_pay_default_attendance_bonus_max_bonus',
            '_vms_pay_default_holiday_name',
            '_vms_low_guarantee_ack',
            '_vms_low_guarantee_ack_ts',
            '_vms_low_guarantee_ack_user_id',
            '_vms_low_guarantee_ack_snapshot',
            '_vms_integrity_issue',
            '_vms_integrity_vendor_id',
            '_vms_integrity_vendor_title',
            '_vms_integrity_venue_id',
            '_vms_integrity_venue_title',
            '_vms_integrity_ts',
            '_vms_true_headcount',
            '_vms_comp_headcount_true',
            '_vms_concessions_actual_cents',
            '_vms_concessions_actual_source',
            '_vms_event_direct_costs_cents',
            '_vms_event_processing_fees_cents',
            '_vms_event_actuals_totals',
            '_vms_event_actuals_pulled_at_utc',
            '_vms_event_actuals_provider',
            '_vms_admin_scroll_to',
            '_vms_rescheduled_from_plan_id',
            '_vms_rescheduled_to_plan_ids',
        );
        foreach ($meta_keys_to_clear as $meta_key) {
            delete_post_meta($new_post_id, $meta_key);
        }

        $k_date = function_exists('bvmgr_meta_key')
            ? (bvmgr_meta_key('event_plan', 'date') ?: '_vms_event_date')
            : '_vms_event_date';
        $k_rescheduled_from = function_exists('bvmgr_meta_key')
            ? (bvmgr_meta_key('event_plan', 'rescheduled_from_plan_id') ?: '_vms_rescheduled_from_plan_id')
            : '_vms_rescheduled_from_plan_id';
        $k_rescheduled_to = function_exists('bvmgr_meta_key')
            ? (bvmgr_meta_key('event_plan', 'rescheduled_to_plan_ids') ?: '_vms_rescheduled_to_plan_ids')
            : '_vms_rescheduled_to_plan_ids';

        $k_ticketing_override = function_exists('bvmgr_meta_key')
            ? (bvmgr_meta_key('event_plan', 'ticketing_enabled_override') ?: '_vms_ticketing_enabled_override')
            : '_vms_ticketing_enabled_override';

        update_post_meta($new_post_id, $k_status, 'draft');
        update_post_meta($new_post_id, $k_date, $replacement_date);
        update_post_meta($new_post_id, $k_rescheduled_from, $source_post_id);

        // A cancelled source plan forces ticketing off during closeout.
        // The replacement draft should start from a normal reviewable state,
        // so explicitly re-enable event-level ticketing instead of inheriting
        // the source plan's cancellation override.
        delete_post_meta($new_post_id, $k_ticketing_override);
        update_post_meta($new_post_id, $k_ticketing_override, 'on');

        $existing_children = bvmgr_event_plan_normalize_related_plan_ids(get_post_meta($source_post_id, $k_rescheduled_to, true));
        $existing_children[] = $new_post_id;
        update_post_meta($source_post_id, $k_rescheduled_to, array_values(array_unique(array_map('absint', $existing_children))));

        clean_post_cache($new_post_id);
        clean_post_cache($source_post_id);

        return array(
            'ok' => true,
            'new_post_id' => $new_post_id,
        );
    }
}

/**
 * Admin functionality for VMS Event Plans.
 */
class BVMGR_Admin_Event_Plans
{
    private $event_plan_admin_boot_cache = array();

    public function __construct()
    {
        add_action('add_meta_boxes', array($this, 'register_meta_boxes'));
        add_action('save_post_vms_event_plan', array($this, 'save_event_plan_meta'), 10, 2);
        add_filter('get_user_option_meta-box-order_vms_event_plan', array($this, 'default_metabox_order'), 10, 3);

        // AJAX: Venue default comp (by date)
        add_action('wp_ajax_vms_get_venue_comp_defaults', array($this, 'ajax_get_venue_comp_defaults'));
        add_action('wp_ajax_vms_get_event_plan_comp_options', array($this, 'ajax_get_event_plan_comp_options'));
        add_action('wp_ajax_vms_load_event_plan_admin_section', array($this, 'ajax_load_event_plan_admin_section'));
        add_action('wp_ajax_vms_save_event_plan_secondary_vendors', array($this, 'ajax_save_event_plan_secondary_vendors'));
        add_action('wp_ajax_vms_save_event_plan_ticket_ui_overrides', array($this, 'ajax_save_event_plan_ticket_ui_overrides'));
        add_action('wp_ajax_vms_save_event_plan_calendar_unpublished_suppress', array($this, 'ajax_save_event_plan_calendar_unpublished_suppress'));
        add_action('wp_ajax_vms_load_event_plan_supporting_vendor_options', array($this, 'ajax_load_event_plan_supporting_vendor_options'));
    }

    public function register_meta_boxes(): void
    {
        $plan_id = function_exists('bvmgr_event_plan_perf_current_plan_id')
            ? bvmgr_event_plan_perf_current_plan_id()
            : 0;
        $trace = function_exists('bvmgr_event_plan_perf_span_start')
            ? bvmgr_event_plan_perf_span_start('event_plan_meta_box_registration', $plan_id, array('section' => 'meta_box_registration'))
            : '';

        try {
            add_meta_box(
                'vms_event_plan_details',
                __('Event Plan Details', 'backstage-venue-manager'),
                array($this, 'render_event_plan_details_meta_box'),
                'vms_event_plan',
                'normal',
                'default'
            );

            add_meta_box(
                'vms_event_plan_ticketing_v2',
                __('Ticketing', 'backstage-venue-manager'),
                array($this, 'render_event_plan_ticketing_v2_host_meta_box'),
                'vms_event_plan',
                'normal',
                'default'
            );

            add_meta_box(
                'vms_event_plan_advanced_controls',
                __('Advanced Controls', 'backstage-venue-manager'),
                array($this, 'render_event_plan_advanced_controls_host_meta_box'),
                'vms_event_plan',
                'normal',
                'default'
            );
        } finally {
            if (function_exists('bvmgr_event_plan_perf_span_finish')) {
                bvmgr_event_plan_perf_span_finish('event_plan_meta_box_registration', $plan_id, $trace, array('section' => 'meta_box_registration'));
            }
        }
    }

    public function render_event_plan_ticketing_v2_host_meta_box(WP_Post $post): void
    {
        $plan_id = (int) $post->ID;
        $meta_bundle = $this->get_event_plan_meta_bundle($plan_id);

        echo $this->capture_event_plan_partial('ticketing-v2', array(
            'post' => $post,
            'ticketing_boot_summary' => $this->get_event_plan_ticketing_boot_summary($plan_id),
            'add_on_boot_summary' => $this->get_event_plan_add_on_boot_summary($plan_id),
            'ticket_stats' => (array) ($meta_bundle['ticket_stats'] ?? array()),
            'vms_ticketing_v2_render_mode' => $this->is_event_plan_admin_section_requested($plan_id, 'ticketing_v2') ? 'full' : 'summary',
            'vms_ticketing_v2_load_url' => $this->get_event_plan_admin_section_url($plan_id, 'ticketing_v2', 'vms_event_plan_ticketing_v2'),
            'vms_ticketing_v2_summary_url' => $this->get_event_plan_admin_section_url($plan_id, '', 'vms_event_plan_ticketing_v2'),
        )); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function render_event_plan_advanced_controls_host_meta_box(WP_Post $post): void
    {
        $plan_id = (int) $post->ID;
        $meta_bundle = $this->get_event_plan_meta_bundle($plan_id);

        echo $this->capture_event_plan_partial('advanced-controls', array(
            'post' => $post,
            'ticket_stats' => (array) ($meta_bundle['ticket_stats'] ?? array()),
            'vms_ticketing_v2_render_mode' => 'omit',
        )); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    private function get_event_plan_module_ownership_map(): array
    {
        return array(
            'event_plan_details' => 'core_event_details',
            'ticketing_v2' => 'ticketing',
            'staff' => 'staffing_ops',
            'secondary_vendors' => 'vendors',
            'readiness_details' => 'diagnostics_advanced',
            'advanced_controls' => 'diagnostics_advanced',
            'agreements' => 'agreements',
            'sponsorships' => 'sponsorships',
            'social_promotion' => 'marketing_assets',
            'meta_ads' => 'marketing_assets',
        );
    }

    private function get_event_plan_section_module_owner(string $section): string
    {
        $section = sanitize_key($section);
        $map = $this->get_event_plan_module_ownership_map();
        if ($section !== '' && isset($map[$section])) {
            return (string) $map[$section];
        }

        return 'core_event_details';
    }

    private function get_event_plan_secondary_vendors_module_payload(int $post_id): array
    {
        $post_id = absint($post_id);
        $post = get_post($post_id);
        if (!$post instanceof WP_Post || $post->post_type !== 'vms_event_plan') {
            return array(
                'html' => '',
                'has_data' => false,
                'summary_meta' => '',
                'module_owner' => $this->get_event_plan_section_module_owner('secondary_vendors'),
            );
        }

        $bundle = $this->get_event_plan_meta_bundle($post_id);
        $bands = get_posts(array(
            'post_type'      => 'vms_vendor',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'no_found_rows'  => true,
            'update_post_term_cache' => false,
        ));
        $secondary_vendor_assignments = is_array($bundle['secondary_vendor_assignments'] ?? null)
            ? (array) $bundle['secondary_vendor_assignments']
            : array();
        $secondary_vendor_type = sanitize_key((string) ($bundle['secondary_vendor_type'] ?? ''));
        $secondary_vendor_ids = is_array($bundle['secondary_vendor_ids'] ?? null)
            ? array_values(array_filter(array_map('absint', (array) $bundle['secondary_vendor_ids'])))
            : array();
        $secondary_vendor_boot_summary = $this->get_event_plan_secondary_vendor_boot_summary(
            $post_id,
            is_array($bands) ? $bands : array(),
            (string) ($bundle['event_date'] ?? ''),
            $secondary_vendor_assignments,
            true
        );
        $module_owner = $this->get_event_plan_section_module_owner('secondary_vendors');
        $html = $this->capture_event_plan_partial('secondary-vendors', array(
            'post' => $post,
            'secondary_vendor_assignments' => $secondary_vendor_assignments,
            'secondary_vendor_type' => $secondary_vendor_type,
            'secondary_vendor_ids' => $secondary_vendor_ids,
            'secondary_vendor_boot_summary' => $secondary_vendor_boot_summary,
            'vms_module_owner' => $module_owner,
        ));
        $group_count = count((array) ($secondary_vendor_boot_summary['assignment_groups'] ?? array()));
        $has_data = ($group_count > 0 || !empty($secondary_vendor_ids));
        $warning_count = count((array) ($secondary_vendor_boot_summary['secondary_missing'] ?? array()))
            + count((array) ($secondary_vendor_boot_summary['secondary_mismatch'] ?? array()))
            + count((array) ($secondary_vendor_boot_summary['secondary_unqualified'] ?? array()));
        $summary_bits = array();
        if ($group_count === 1) {
            $first_group = (array) reset($secondary_vendor_boot_summary['assignment_groups']);
            $type_name = trim((string) ($first_group['type_name'] ?? ''));
            $type_slug = sanitize_key((string) ($first_group['type_slug'] ?? ''));
            if ($type_name !== '' || $type_slug !== '') {
                $summary_bits[] = $type_name !== '' ? $type_name : $type_slug;
            }
        } elseif ($group_count > 1) {
            /* translators: %d: number of groups. */
            $summary_bits[] = sprintf(_n('%d group', '%d groups', $group_count, 'backstage-venue-manager'), $group_count);
        }
        /* translators: %d: number of selected items. */
        $summary_bits[] = sprintf(_n('%d selected', '%d selected', count((array) $secondary_vendor_ids), 'backstage-venue-manager'), count((array) $secondary_vendor_ids));
        /* translators: %d: number of warnings. */
        $summary_bits[] = sprintf(_n('%d warning', '%d warnings', $warning_count, 'backstage-venue-manager'), $warning_count);

        return array(
            'html' => $html,
            'has_data' => $has_data,
            'summary_meta' => implode(' • ', array_filter($summary_bits)),
            'secondary_vendor_assignments' => $secondary_vendor_assignments,
            'secondary_vendor_type' => $secondary_vendor_type,
            'secondary_vendor_ids' => $secondary_vendor_ids,
            'module_owner' => $module_owner,
        );
    }

    private function build_event_plan_secondary_vendors_save_response_payload(int $post_id): array
    {
        $post_id = absint($post_id);
        $bundle = $this->get_event_plan_meta_bundle($post_id);
        $bands = get_posts(array(
            'post_type'      => 'vms_vendor',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'no_found_rows'  => true,
            'update_post_term_cache' => false,
        ));
        $secondary_vendor_assignments = is_array($bundle['secondary_vendor_assignments'] ?? null)
            ? (array) $bundle['secondary_vendor_assignments']
            : array();
        $secondary_vendor_ids = is_array($bundle['secondary_vendor_ids'] ?? null)
            ? array_values(array_filter(array_map('absint', (array) $bundle['secondary_vendor_ids'])))
            : array();
        $secondary_vendor_boot_summary = $this->get_event_plan_secondary_vendor_boot_summary(
            $post_id,
            is_array($bands) ? $bands : array(),
            (string) ($bundle['event_date'] ?? ''),
            $secondary_vendor_assignments,
            true
        );
        $module_owner = $this->get_event_plan_section_module_owner('secondary_vendors');
        $vendor_category_snapshot = function_exists('bvmgr_event_plan_collect_vendor_category_snapshot')
            ? (array) bvmgr_event_plan_collect_vendor_category_snapshot($post_id)
            : array();
        $context = $this->build_event_plan_secondary_vendors_save_response_context(
            $post_id,
            $secondary_vendor_boot_summary,
            $module_owner,
            $vendor_category_snapshot
        );

        $group_count = count((array) ($secondary_vendor_boot_summary['assignment_groups'] ?? array()));
        $has_data = ($group_count > 0 || !empty($secondary_vendor_ids));
        $warning_count = count((array) ($secondary_vendor_boot_summary['secondary_missing'] ?? array()))
            + count((array) ($secondary_vendor_boot_summary['secondary_mismatch'] ?? array()))
            + count((array) ($secondary_vendor_boot_summary['secondary_unqualified'] ?? array()));
        $summary_bits = array();
        if ($group_count === 1) {
            $first_group = (array) reset($secondary_vendor_boot_summary['assignment_groups']);
            $type_name = trim((string) ($first_group['type_name'] ?? ''));
            $type_slug = sanitize_key((string) ($first_group['type_slug'] ?? ''));
            if ($type_name !== '' || $type_slug !== '') {
                $summary_bits[] = $type_name !== '' ? $type_name : $type_slug;
            }
        } elseif ($group_count > 1) {
            /* translators: %d: number of groups. */
            $summary_bits[] = sprintf(_n('%d group', '%d groups', $group_count, 'backstage-venue-manager'), $group_count);
        }
        /* translators: %d: number of selected items. */
        $summary_bits[] = sprintf(_n('%d selected', '%d selected', count((array) $secondary_vendor_ids), 'backstage-venue-manager'), count((array) $secondary_vendor_ids));
        /* translators: %d: number of warnings. */
        $summary_bits[] = sprintf(_n('%d warning', '%d warnings', $warning_count, 'backstage-venue-manager'), $warning_count);

        return array(
            'context' => $context,
            'html' => $this->render_event_plan_secondary_vendors_save_response_html($context),
            'has_data' => $has_data,
            'summary_meta' => implode(' • ', array_filter($summary_bits)),
            'module_owner' => $module_owner,
        );
    }

    private function build_event_plan_secondary_vendors_save_response_context(
        int $post_id,
        array $secondary_vendor_boot_summary,
        string $module_owner,
        array $vendor_category_snapshot
    ): array {
        $assignment_groups = isset($secondary_vendor_boot_summary['assignment_groups']) && is_array($secondary_vendor_boot_summary['assignment_groups'])
            ? array_values($secondary_vendor_boot_summary['assignment_groups'])
            : array();
        $secondary_type_options = isset($secondary_vendor_boot_summary['secondary_type_options']) && is_array($secondary_vendor_boot_summary['secondary_type_options'])
            ? $secondary_vendor_boot_summary['secondary_type_options']
            : array();
        $secondary_mode_options = isset($secondary_vendor_boot_summary['secondary_mode_options']) && is_array($secondary_vendor_boot_summary['secondary_mode_options'])
            ? $secondary_vendor_boot_summary['secondary_mode_options']
            : array();
        $type_pool_map = isset($secondary_vendor_boot_summary['type_pool_map']) && is_array($secondary_vendor_boot_summary['type_pool_map'])
            ? $secondary_vendor_boot_summary['type_pool_map']
            : array();
        $secondary_missing = isset($secondary_vendor_boot_summary['secondary_missing']) && is_array($secondary_vendor_boot_summary['secondary_missing'])
            ? $secondary_vendor_boot_summary['secondary_missing']
            : array();
        $secondary_mismatch = isset($secondary_vendor_boot_summary['secondary_mismatch']) && is_array($secondary_vendor_boot_summary['secondary_mismatch'])
            ? $secondary_vendor_boot_summary['secondary_mismatch']
            : array();
        $secondary_unqualified = isset($secondary_vendor_boot_summary['secondary_unqualified']) && is_array($secondary_vendor_boot_summary['secondary_unqualified'])
            ? $secondary_vendor_boot_summary['secondary_unqualified']
            : array();
        $secondary_group_type_options = !empty($secondary_type_options) && is_array($secondary_type_options)
            ? $secondary_type_options
            : (function_exists('bvmgr_event_plan_additional_vendor_type_options') ? (array) bvmgr_event_plan_additional_vendor_type_options() : array());
        $vendor_category_rows = isset($vendor_category_snapshot['vendors']) && is_array($vendor_category_snapshot['vendors'])
            ? $vendor_category_snapshot['vendors']
            : array();
        $vendor_category_names = isset($vendor_category_snapshot['term_names']) && is_array($vendor_category_snapshot['term_names'])
            ? $vendor_category_snapshot['term_names']
            : array();

        return array(
            'post_id' => $post_id,
            'module_owner' => sanitize_key($module_owner),
            'assignment_groups' => $assignment_groups,
            'secondary_group_type_options' => $secondary_group_type_options,
            'secondary_mode_options' => $secondary_mode_options,
            'type_pool_map' => $type_pool_map,
            'secondary_missing' => $secondary_missing,
            'secondary_mismatch' => $secondary_mismatch,
            'secondary_unqualified' => $secondary_unqualified,
            'secondary_has_saved_state' => !empty($assignment_groups),
            'secondary_config' => $this->build_event_plan_secondary_vendors_save_response_config(
                $secondary_group_type_options,
                $secondary_mode_options,
                $type_pool_map
            ),
            'help_enabled' => function_exists('bvmgr_help_is_enabled') && bvmgr_help_is_enabled(),
            'vendor_category_rows' => $vendor_category_rows,
            'vendor_category_names' => $vendor_category_names,
        );
    }

    private function build_event_plan_secondary_vendors_save_response_config(
        array $secondary_group_type_options,
        array $secondary_mode_options,
        array $type_pool_map
    ): array {
        $secondary_config_type_options = array();
        foreach ($secondary_group_type_options as $type_slug => $type_label) {
            $type_slug = sanitize_key((string) $type_slug);
            $type_label = trim((string) $type_label);
            if ($type_slug === '' || $type_label === '') {
                continue;
            }

            $default_mode = function_exists('bvmgr_event_plan_secondary_vendor_default_mode')
                ? (string) bvmgr_event_plan_secondary_vendor_default_mode($type_slug)
                : 'standard';
            $default_slot_limit = function_exists('bvmgr_event_plan_secondary_vendor_default_slot_limit')
                ? bvmgr_event_plan_secondary_vendor_default_slot_limit($type_slug, $default_mode)
                : 1;
            $secondary_config_type_options[] = array(
                'slug' => $type_slug,
                'label' => $type_label,
                'default_mode' => $default_mode,
                'default_slot_limit' => $default_slot_limit,
            );
        }

        $secondary_config_mode_options = array();
        foreach ($secondary_mode_options as $mode_slug => $mode_label) {
            $mode_slug = sanitize_key((string) $mode_slug);
            $mode_label = trim((string) $mode_label);
            if ($mode_slug === '' || $mode_label === '') {
                continue;
            }
            $secondary_config_mode_options[] = array(
                'slug' => $mode_slug,
                'label' => $mode_label,
            );
        }

        return array(
            'typeOptions' => $secondary_config_type_options,
            'modeOptions' => $secondary_config_mode_options,
            'pools' => is_array($type_pool_map) ? $type_pool_map : array(),
            'labels' => array(
                'selectType' => __('-- Select a Vendor Type --', 'backstage-venue-manager'),
                'selectVendor' => __('-- Select a Vendor --', 'backstage-venue-manager'),
                'selectTypeFirst' => __('-- Select a Vendor Type first --', 'backstage-venue-manager'),
                'chooseType' => __('Choose type first', 'backstage-venue-manager'),
                'occupancyUnknown' => __('No slot limit set', 'backstage-venue-manager'),
                'available' => __('Available', 'backstage-venue-manager'),
                'unavailable' => __('Not available', 'backstage-venue-manager'),
                'unknownAvailability' => __('Availability unknown', 'backstage-venue-manager'),
                'qualified' => __('Qualified', 'backstage-venue-manager'),
                'needsAttention' => __('Needs attention', 'backstage-venue-manager'),
                'typeMismatch' => __('Type mismatch', 'backstage-venue-manager'),
                'missingVendor' => __('Missing vendor', 'backstage-venue-manager'),
                'market' => __('Market', 'backstage-venue-manager'),
                'standard' => __('Standard', 'backstage-venue-manager'),
                'pendingVendor' => __('Select vendor', 'backstage-venue-manager'),
                /* translators: %d: number used in this message. */
                'overCapacity' => __('Over capacity by %d', 'backstage-venue-manager'),
                /* translators: %d: number used in this message. */
                'target' => __('Target %d', 'backstage-venue-manager'),
                /* translators: %d: number of items described in this message. */
                'needed' => __('%d needed', 'backstage-venue-manager'),
                'hiddenFromDispatch' => __('Hidden from ADD', 'backstage-venue-manager'),
                'saveUnavailable' => __('Additional Vendors save is not available right now.', 'backstage-venue-manager'),
                'saving' => __('Saving Additional Vendors…', 'backstage-venue-manager'),
                'saveFailed' => __('Additional Vendors could not be saved. Reload the page and try again.', 'backstage-venue-manager'),
            ),
        );
    }

    private function render_event_plan_secondary_vendors_save_response_html(array $context): string
    {
        $assignment_groups = isset($context['assignment_groups']) && is_array($context['assignment_groups'])
            ? $context['assignment_groups']
            : array();
        $secondary_has_saved_state = !empty($context['secondary_has_saved_state']);
        $secondary_missing = isset($context['secondary_missing']) && is_array($context['secondary_missing'])
            ? $context['secondary_missing']
            : array();
        $secondary_mismatch = isset($context['secondary_mismatch']) && is_array($context['secondary_mismatch'])
            ? $context['secondary_mismatch']
            : array();
        $secondary_unqualified = isset($context['secondary_unqualified']) && is_array($context['secondary_unqualified'])
            ? $context['secondary_unqualified']
            : array();

        ob_start();
        ?>
        <p class="description">
            <?php esc_html_e('Attach one or more additional vendors to this event. Use separate groups for Food Vendor, Dessert Vendor, Photographer, Market Vendor, and other non-performer vendor types. These vendors will see this date as Tentative when the Event Plan is Draft/Ready and Booked once Published.', 'backstage-venue-manager'); ?>
        </p>

        <div id="vms-secondary-vendors-section"
            data-vms-module-owner="<?php echo esc_attr((string) ($context['module_owner'] ?? 'vendors')); ?>"
            data-vms-save-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
            data-vms-save-nonce="<?php echo esc_attr(wp_create_nonce('bvmgr_event_plan_secondary_vendors_save')); ?>"
            data-vms-save-post-id="<?php echo (int) ($context['post_id'] ?? 0); ?>">
            <input type="hidden" name="vms_secondary_vendors_module_detached" value="1" />
            <input type="hidden" name="vms_clear_secondary_vendors" value="0" id="vms-clear-secondary-vendors-intent" />
            <SCRIPT data-vms-secondary-config type="application/json"><?php echo wp_json_encode((array) ($context['secondary_config'] ?? array())); ?></SCRIPT>

            <p class="description vms-mt-8 vms-mb-8"><?php esc_html_e('Use Save Additional Vendors to save changes in this section.', 'backstage-venue-manager'); ?></p>

            <?php if (!$secondary_has_saved_state) : ?>
                <div class="notice notice-info inline vms-notice vms-notice--info">
                    <p><?php esc_html_e('Add a vendor group, then save this section to store your additional vendor assignments.', 'backstage-venue-manager'); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($secondary_missing) || !empty($secondary_mismatch) || !empty($secondary_unqualified)) : ?>
                <p class="description vms-secondary-vendor-legend">
                    <?php
                    echo esc_html__('Availability guide: [✓] Available, [✖] Not Available, [?] Unknown. Qualification guide: [Q✓] Qualified, [Q⚠] Needs attention.', 'backstage-venue-manager');
                    if (function_exists('bvmgr_help_icon')) {
                        bvmgr_help_icon(__('“[Q⚠] Needs attention” means this vendor is missing required profile items (usually phone or email).', 'backstage-venue-manager'));
                    }
                    ?>
                </p>
            <?php endif; ?>

            <div id="vms-secondary-vendor-groups">
                <?php foreach ($assignment_groups as $group_index => $group) : ?>
                    <?php echo $this->render_event_plan_secondary_vendors_save_response_group_html((array) $group, (int) $group_index, $context); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php endforeach; ?>
            </div>

            <p class="vms-secondary-vendor-actions">
                <button type="button" class="button button-secondary" id="vms-secondary-vendor-add-group"><?php esc_html_e('Add vendor group', 'backstage-venue-manager'); ?></button>
                <button type="button" class="button button-secondary" id="vms-secondary-vendor-clear"<?php echo $this->render_event_plan_secondary_vendors_save_response_disabled_attr(!$secondary_has_saved_state); ?>><?php esc_html_e('Clear additional vendors', 'backstage-venue-manager'); ?></button>
                <button type="button" class="button button-primary" id="vms-secondary-vendor-save"><?php esc_html_e('Save Additional Vendors', 'backstage-venue-manager'); ?></button>
            </p>
            <p class="description vms-mt-8 vms-mb-0" data-vms-secondary-save-status aria-live="polite"></p>

            <?php echo $this->render_event_plan_secondary_vendors_save_response_group_template_html($context); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php echo $this->render_event_plan_secondary_vendors_save_response_row_template_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php echo $this->render_event_plan_secondary_vendors_save_response_vendor_category_notice_html($context); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private function build_event_plan_secondary_vendors_save_response_group_summary_context(array $group): array
    {
        $mode = sanitize_key((string) ($group['mode'] ?? 'standard'));
        $type_slug = sanitize_key((string) ($group['type_slug'] ?? ''));
        $is_market_group = ($mode === 'market' || $type_slug === 'market_vendor');
        $has_type = ($type_slug !== '');
        $vendor_ids = isset($group['vendor_ids']) && is_array($group['vendor_ids']) ? $group['vendor_ids'] : array();
        $filled = count(array_filter(array_map('absint', $vendor_ids), static function ($vendor_id): bool {
            return $vendor_id > 0;
        }));
        $slot_limit = array_key_exists('slot_limit', $group) && $group['slot_limit'] !== null && $group['slot_limit'] !== ''
            ? max(0, (int) $group['slot_limit'])
            : null;
        $needed_slots = array_key_exists('needed_slots', $group) && $group['needed_slots'] !== null && $group['needed_slots'] !== ''
            ? max(0, (int) $group['needed_slots'])
            : null;
        $open_for_dispatch = function_exists('bvmgr_event_plan_parse_secondary_vendor_over_capacity_override')
            ? bvmgr_event_plan_parse_secondary_vendor_over_capacity_override($group['open_for_dispatch'] ?? true)
            : !array_key_exists('open_for_dispatch', $group) || !empty($group['open_for_dispatch']);
        $warning = '';
        $over_capacity = false;
        $parts = array();

        if (!$has_type) {
            $parts[] = ($slot_limit === null)
                /* translators: %d: number of selected items. */
                ? sprintf(_n('%d selected', '%d selected', $filled, 'backstage-venue-manager'), $filled)
                /* translators: 1: number 1 used in this message, 2: number 2 used in this message. */
                : sprintf(__('%1$d of %2$d filled', 'backstage-venue-manager'), $filled, $slot_limit);
            $parts[] = __('Choose type first', 'backstage-venue-manager');
        } else {
            $parts[] = ($slot_limit === null)
                /* translators: %d: number of selected items. */
                ? sprintf(_n('%d selected', '%d selected', $filled, 'backstage-venue-manager'), $filled)
                /* translators: 1: number 1 used in this message, 2: number 2 used in this message. */
                : sprintf(__('%1$d of %2$d filled', 'backstage-venue-manager'), $filled, $slot_limit);
            $parts[] = $is_market_group ? __('Market', 'backstage-venue-manager') : __('Standard', 'backstage-venue-manager');
            if ($is_market_group && $needed_slots !== null) {
                /* translators: %d: number used in this message. */
                $parts[] = sprintf(__('Target %d', 'backstage-venue-manager'), $needed_slots);
                $parts[] = $open_for_dispatch
                    /* translators: %d: number of items described in this message. */
                    ? sprintf(_n('%d needed', '%d needed', max(0, $needed_slots - $filled), 'backstage-venue-manager'), max(0, $needed_slots - $filled))
                    : __('Hidden from ADD', 'backstage-venue-manager');
            }
            if ($slot_limit === null) {
                $parts[] = __('No slot limit set', 'backstage-venue-manager');
            } elseif ($filled > $slot_limit) {
                $over_capacity = true;
                /* translators: %d: number used in this message. */
                $warning = sprintf(__('Over capacity by %d', 'backstage-venue-manager'), $filled - $slot_limit);
                $parts[] = $warning;
            }
        }

        return array(
            'text' => implode(' • ', array_filter($parts)),
            'warning' => ($warning !== ''),
            'over_capacity' => $over_capacity,
            'is_market_group' => $is_market_group,
            'has_type' => $has_type,
        );
    }

    private function render_event_plan_secondary_vendors_save_response_group_html(array $group, int $group_index, array $context): string
    {
        $type_slug = sanitize_key((string) ($group['type_slug'] ?? ''));
        $type_name = trim((string) ($group['type_name'] ?? ''));
        $mode = sanitize_key((string) ($group['mode'] ?? 'standard'));
        $slot_limit_display = isset($group['slot_limit_display']) ? (string) $group['slot_limit_display'] : '';
        $vendor_ids = isset($group['vendor_ids']) && is_array($group['vendor_ids'])
            ? array_values(array_map('absint', $group['vendor_ids']))
            : array();
        if (empty($vendor_ids)) {
            $vendor_ids = array(0);
        }

        $group_missing = isset($group['secondary_missing']) && is_array($group['secondary_missing']) ? $group['secondary_missing'] : array();
        $group_mismatch = isset($group['secondary_mismatch']) && is_array($group['secondary_mismatch']) ? $group['secondary_mismatch'] : array();
        $group_unqualified = isset($group['secondary_unqualified']) && is_array($group['secondary_unqualified']) ? $group['secondary_unqualified'] : array();
        $group_titles = isset($group['selected_vendor_titles']) && is_array($group['selected_vendor_titles']) ? $group['selected_vendor_titles'] : array();
        $group_missing_map = isset($group['selected_missing_map']) && is_array($group['selected_missing_map']) ? $group['selected_missing_map'] : array();
        $group_summary = $this->build_event_plan_secondary_vendors_save_response_group_summary_context($group);
        $is_market_group = !empty($group_summary['is_market_group']);
        $group_has_type = !empty($group_summary['has_type']);
        $allow_over_capacity = function_exists('bvmgr_event_plan_parse_secondary_vendor_over_capacity_override')
            ? bvmgr_event_plan_parse_secondary_vendor_over_capacity_override($group['allow_over_capacity'] ?? false)
            : !empty($group['allow_over_capacity']);
        $is_over_capacity = !empty($group_summary['over_capacity']);
        $needed_slots_display = array_key_exists('needed_slots', $group) && $group['needed_slots'] !== null && $group['needed_slots'] !== ''
            ? (string) max(0, (int) $group['needed_slots'])
            : '';
        $open_for_dispatch = function_exists('bvmgr_event_plan_parse_secondary_vendor_over_capacity_override')
            ? bvmgr_event_plan_parse_secondary_vendor_over_capacity_override($group['open_for_dispatch'] ?? true)
            : !array_key_exists('open_for_dispatch', $group) || !empty($group['open_for_dispatch']);
        $add_secondary_vendor_args = array(
            'post_type' => 'vms_vendor',
            'vms_return_to_event_plan' => (int) ($context['post_id'] ?? 0),
            'vms_prefill_vendor_role' => 'secondary',
        );
        if ($type_slug !== '') {
            $add_secondary_vendor_args['vms_prefill_vendor_type'] = $type_slug;
        }
        $add_secondary_vendor_url = add_query_arg($add_secondary_vendor_args, admin_url('post-new.php'));
        $secondary_group_type_options = isset($context['secondary_group_type_options']) && is_array($context['secondary_group_type_options'])
            ? $context['secondary_group_type_options']
            : array();
        $secondary_mode_options = isset($context['secondary_mode_options']) && is_array($context['secondary_mode_options'])
            ? $context['secondary_mode_options']
            : array();

        ob_start();
        ?>
        <div class="vms-secondary-vendor-group<?php echo $is_market_group ? ' vms-secondary-vendor-group--market' : ''; ?><?php echo !$group_has_type ? ' vms-secondary-vendor-group--type-pending' : ''; ?>"
            data-vms-group-index="<?php echo esc_attr((string) $group_index); ?>"
            data-vms-missing-ids="<?php echo esc_attr(wp_json_encode(array_values(array_map('absint', $group_missing)))); ?>"
            data-vms-mismatch-ids="<?php echo esc_attr(wp_json_encode(array_values(array_map('absint', $group_mismatch)))); ?>"
            data-vms-unqualified-ids="<?php echo esc_attr(wp_json_encode(array_values(array_map('absint', $group_unqualified)))); ?>">
            <div class="vms-secondary-vendor-group__header">
                <label class="vms-secondary-vendor-group__field vms-secondary-vendor-group__field--type">
                    <span class="vms-secondary-vendor-group__field-label"><?php esc_html_e('Vendor type', 'backstage-venue-manager'); ?></span>
                    <select name="<?php echo esc_attr(sprintf('vms_secondary_vendor_assignments[%d][type_slug]', $group_index)); ?>" class="vms-secondary-vendor-group-type">
                        <option value=""><?php esc_html_e('-- Select a Vendor Type --', 'backstage-venue-manager'); ?></option>
                        <?php foreach ($secondary_group_type_options as $option_slug => $option_label) : ?>
                            <?php
                            $option_slug = sanitize_key((string) $option_slug);
                            $option_label = trim((string) $option_label);
                            if ($option_slug === '' || $option_label === '') {
                                continue;
                            }
                            ?>
                            <option value="<?php echo esc_attr($option_slug); ?>"<?php echo $this->render_event_plan_secondary_vendors_save_response_selected_attr($type_slug === $option_slug); ?>><?php echo esc_html($option_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="vms-secondary-vendor-group__field vms-secondary-vendor-group__field--mode">
                    <span class="vms-secondary-vendor-group__field-label"><?php esc_html_e('Mode', 'backstage-venue-manager'); ?></span>
                    <select name="<?php echo esc_attr(sprintf('vms_secondary_vendor_assignments[%d][mode]', $group_index)); ?>" class="vms-secondary-vendor-group-mode">
                        <?php foreach ($secondary_mode_options as $mode_slug => $mode_label) : ?>
                            <?php
                            $mode_slug = sanitize_key((string) $mode_slug);
                            if ($mode_slug === '') {
                                continue;
                            }
                            ?>
                            <option value="<?php echo esc_attr($mode_slug); ?>"<?php echo $this->render_event_plan_secondary_vendors_save_response_selected_attr($mode === $mode_slug); ?>><?php echo esc_html((string) $mode_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="vms-secondary-vendor-group__field vms-secondary-vendor-group__field--capacity">
                    <span class="vms-secondary-vendor-group__field-label"><?php esc_html_e('Slot limit / capacity', 'backstage-venue-manager'); ?></span>
                    <input type="number" min="0" step="1" class="small-text vms-secondary-vendor-group-slot-limit" name="<?php echo esc_attr(sprintf('vms_secondary_vendor_assignments[%d][slot_limit]', $group_index)); ?>" value="<?php echo esc_attr($slot_limit_display); ?>" placeholder="<?php esc_attr_e('Use default', 'backstage-venue-manager'); ?>" />
                </label>

                <label class="vms-secondary-vendor-group__field vms-secondary-vendor-group__field--market-target"<?php echo $this->render_event_plan_secondary_vendors_save_response_hidden_attr(!$is_market_group); ?>>
                    <span class="vms-secondary-vendor-group__field-label"><?php esc_html_e('Market target / needed vendors', 'backstage-venue-manager'); ?></span>
                    <input type="number" min="0" step="1" class="small-text vms-secondary-vendor-group-needed-slots" name="<?php echo esc_attr(sprintf('vms_secondary_vendor_assignments[%d][needed_slots]', $group_index)); ?>" value="<?php echo esc_attr($needed_slots_display); ?>" placeholder="<?php esc_attr_e('Blank', 'backstage-venue-manager'); ?>"<?php echo $this->render_event_plan_secondary_vendors_save_response_disabled_attr(!$is_market_group); ?> />
                </label>

                <label class="vms-secondary-vendor-group__field vms-secondary-vendor-group__field--market-dispatch"<?php echo $this->render_event_plan_secondary_vendors_save_response_hidden_attr(!$is_market_group); ?>>
                    <span class="vms-secondary-vendor-group__field-label"><?php esc_html_e('ADD visibility', 'backstage-venue-manager'); ?></span>
                    <span class="vms-secondary-vendor-group__checkbox-line">
                        <input type="hidden" class="vms-secondary-vendor-group-open-for-dispatch-hidden" name="<?php echo esc_attr(sprintf('vms_secondary_vendor_assignments[%d][open_for_dispatch]', $group_index)); ?>" value="0"<?php echo $this->render_event_plan_secondary_vendors_save_response_disabled_attr(!$is_market_group); ?> />
                        <input type="checkbox" class="vms-secondary-vendor-group-open-for-dispatch" name="<?php echo esc_attr(sprintf('vms_secondary_vendor_assignments[%d][open_for_dispatch]', $group_index)); ?>" value="1"<?php echo $this->render_event_plan_secondary_vendors_save_response_checked_attr($open_for_dispatch); ?><?php echo $this->render_event_plan_secondary_vendors_save_response_disabled_attr(!$is_market_group); ?> />
                        <span><?php esc_html_e('Show this market need in ADD', 'backstage-venue-manager'); ?></span>
                    </span>
                </label>

                <div class="vms-secondary-vendor-group__field vms-secondary-vendor-group__field--summary">
                    <span class="vms-secondary-vendor-group__field-label"><?php esc_html_e('Filled', 'backstage-venue-manager'); ?></span>
                    <p class="vms-secondary-vendor-group__summary<?php echo !empty($group_summary['warning']) ? ' is-warning' : ''; ?>"><?php echo esc_html((string) ($group_summary['text'] ?? '')); ?></p>
                </div>

                <div class="vms-secondary-vendor-group__field vms-secondary-vendor-group__field--actions">
                    <span class="vms-secondary-vendor-group__field-label screen-reader-text"><?php esc_html_e('Actions', 'backstage-venue-manager'); ?></span>
                    <button type="button" class="button button-secondary vms-secondary-vendor-remove-group"><?php esc_html_e('Remove group', 'backstage-venue-manager'); ?></button>
                </div>
            </div>

            <label class="vms-secondary-vendor-group__override"<?php echo $this->render_event_plan_secondary_vendors_save_response_hidden_attr(!$is_over_capacity); ?>>
                <input type="checkbox" class="vms-secondary-vendor-group-over-capacity-override" name="<?php echo esc_attr(sprintf('vms_secondary_vendor_assignments[%d][allow_over_capacity]', $group_index)); ?>" value="1"<?php echo $this->render_event_plan_secondary_vendors_save_response_checked_attr($allow_over_capacity); ?> />
                <span><?php esc_html_e('Allow over-capacity assignment for this group.', 'backstage-venue-manager'); ?></span>
            </label>

            <?php if (!empty($group_missing)) : ?>
                <div class="notice notice-warning inline vms-notice vms-notice--warning"><p><?php esc_html_e('🚩 One or more selected vendors no longer exist (or are in the Trash). Remove or replace them below.', 'backstage-venue-manager'); ?></p></div>
            <?php endif; ?>

            <?php if (!empty($group_mismatch)) : ?>
                <?php
                $mismatch_labels = array();
                foreach ($group_mismatch as $vendor_id) {
                    $vendor_id = (int) $vendor_id;
                    /* translators: %d: vendor ID. */
                    $mismatch_labels[] = trim((string) ($group_titles[$vendor_id] ?? sprintf(__('Vendor #%d', 'backstage-venue-manager'), $vendor_id)));
                }
                ?>
                <div class="notice notice-warning inline vms-notice vms-notice--warning">
                    <p>
                        <?php esc_html_e('🚩 One or more selected vendors no longer match this vendor type. Review and re-select vendors below.', 'backstage-venue-manager'); ?>
                        <?php if (!empty($mismatch_labels)) : ?>
                            <?php
                            printf(
                                ' %s',
                                esc_html(
                                    sprintf(
                                        /* translators: %s: affected vendor(s). */
                                        __('Affected vendor(s): %s', 'backstage-venue-manager'),
                                        implode(', ', $mismatch_labels)
                                    )
                                )
                            );
                            ?>
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if (!empty($group_unqualified)) : ?>
                <div class="notice notice-warning inline vms-notice vms-notice--warning">
                    <p><?php esc_html_e('🚩 One or more selected vendors are missing required profile items. They are still attached, but they need attention.', 'backstage-venue-manager'); ?></p>
                    <?php if (!empty($context['help_enabled'])) : ?>
                        <ul class="vms-help-missing-list">
                            <?php foreach ($group_unqualified as $vendor_id) : ?>
                                <?php
                                $vendor_id = (int) $vendor_id;
                                /* translators: %d: vendor ID. */
                                $vendor_label = trim((string) ($group_titles[$vendor_id] ?? sprintf(__('Vendor #%d', 'backstage-venue-manager'), $vendor_id)));
                                $missing_items = isset($group_missing_map[$vendor_id]) && is_array($group_missing_map[$vendor_id]) ? $group_missing_map[$vendor_id] : array();
                                $missing_items = array_map(static function ($missing_item): string {
                                    $missing_item = trim((string) $missing_item);
                                    if ($missing_item === 'Contact info') {
                                        return 'Contact info (phone or email)';
                                    }
                                    return $missing_item;
                                }, $missing_items);
                                ?>
                                <li>
                                    <strong><?php echo esc_html($vendor_label); ?></strong>:
                                    <?php echo esc_html__('Missing:', 'backstage-venue-manager'); ?>
                                    <?php echo esc_html(!empty($missing_items) ? implode(', ', $missing_items) : __('Unknown', 'backstage-venue-manager')); ?>
                                    <a href="<?php echo esc_url(admin_url('post.php?post=' . $vendor_id . '&action=edit')); ?>" target="_blank" rel="noopener"><?php esc_html_e('Edit vendor', 'backstage-venue-manager'); ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="vms-secondary-vendor-group__rows-toolbar">
                <div class="vms-secondary-vendor-group__rows-copy">
                    <p class="vms-secondary-vendor-group__label"><?php esc_html_e('Selected vendors', 'backstage-venue-manager'); ?></p>
                    <p class="description vms-secondary-vendor-group__guidance"<?php echo $this->render_event_plan_secondary_vendors_save_response_hidden_attr($group_has_type); ?>><?php esc_html_e('Select a vendor type to choose eligible vendors.', 'backstage-venue-manager'); ?></p>
                </div>
                <p class="vms-secondary-vendor-actions vms-secondary-vendor-actions--inline">
                    <button type="button" class="button button-secondary vms-secondary-vendor-add-row"><?php esc_html_e('Add vendor row', 'backstage-venue-manager'); ?></button>
                    <a class="button button-secondary" href="<?php echo esc_url($add_secondary_vendor_url); ?>" target="_blank" rel="noopener"><?php esc_html_e('Add new vendor', 'backstage-venue-manager'); ?></a>
                </p>
            </div>

            <div class="vms-secondary-vendor-rows-wrap">
                <div class="vms-secondary-vendor-rows__head" aria-hidden="true">
                    <span><?php esc_html_e('Vendor', 'backstage-venue-manager'); ?></span>
                    <span><?php esc_html_e('Status', 'backstage-venue-manager'); ?></span>
                    <span><?php esc_html_e('Action', 'backstage-venue-manager'); ?></span>
                </div>
                <div class="vms-secondary-vendor-rows">
                    <?php foreach ($vendor_ids as $row_index => $vendor_id) : ?>
                        <?php echo $this->render_event_plan_secondary_vendors_save_response_row_html($group, (int) $vendor_id, $group_index, (int) $row_index, $context); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private function render_event_plan_secondary_vendors_save_response_row_html(
        array $group,
        int $selected_id,
        int $group_index,
        int $row_index,
        array $context
    ): string {
        $type_slug = sanitize_key((string) ($group['type_slug'] ?? ''));
        $pool_option_rows = isset($context['type_pool_map'][$type_slug]) && is_array($context['type_pool_map'][$type_slug])
            ? $context['type_pool_map'][$type_slug]
            : array();
        $field_name = sprintf('vms_secondary_vendor_assignments[%d][vendor_ids][]', $group_index);

        ob_start();
        ?>
        <div class="vms-secondary-vendor-row" data-vms-row-index="<?php echo esc_attr((string) $row_index); ?>">
            <div class="vms-secondary-vendor-row__vendor">
                <select name="<?php echo esc_attr($field_name); ?>" class="vms-secondary-vendor-select" data-selected-id="<?php echo esc_attr((string) $selected_id); ?>"<?php echo $this->render_event_plan_secondary_vendors_save_response_disabled_attr($type_slug === ''); ?>>
                    <?php if ($type_slug === '') : ?>
                        <option value=""><?php esc_html_e('-- Select a Vendor Type first --', 'backstage-venue-manager'); ?></option>
                    <?php else : ?>
                        <option value=""><?php esc_html_e('-- Select a Vendor --', 'backstage-venue-manager'); ?></option>
                        <?php foreach ($pool_option_rows as $pool_row) : ?>
                            <?php
                            if (!is_array($pool_row)) {
                                continue;
                            }
                            $vendor_id = absint($pool_row['vendor_id'] ?? 0);
                            if ($vendor_id <= 0) {
                                continue;
                            }
                            $label = trim((string) ($pool_row['label'] ?? ''));
                            if ($label === '') {
                                $label = trim((string) ($pool_row['vendor_title'] ?? ''));
                            }
                            ?>
                            <option value="<?php echo esc_attr((string) $vendor_id); ?>"<?php echo $this->render_event_plan_secondary_vendors_save_response_selected_attr($selected_id === $vendor_id); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="vms-secondary-vendor-row__indicators" data-vms-secondary-row-indicators>
                <?php echo $this->render_event_plan_secondary_vendors_save_response_status_badges_html($group, $selected_id, $pool_option_rows); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
            <div class="vms-secondary-vendor-row__action">
                <button type="button" class="button button-secondary vms-secondary-vendor-remove"><?php esc_html_e('Remove', 'backstage-venue-manager'); ?></button>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private function render_event_plan_secondary_vendors_save_response_status_badges_html(array $group, int $selected_id, array $pool_option_rows): string
    {
        $selected_id = absint($selected_id);
        $pool_row = array();
        foreach ($pool_option_rows as $candidate_row) {
            if (!is_array($candidate_row)) {
                continue;
            }
            if (absint($candidate_row['vendor_id'] ?? 0) === $selected_id) {
                $pool_row = $candidate_row;
                break;
            }
        }

        $group_missing = isset($group['secondary_missing']) && is_array($group['secondary_missing']) ? array_map('absint', $group['secondary_missing']) : array();
        $group_mismatch = isset($group['secondary_mismatch']) && is_array($group['secondary_mismatch']) ? array_map('absint', $group['secondary_mismatch']) : array();
        $group_unqualified = isset($group['secondary_unqualified']) && is_array($group['secondary_unqualified']) ? array_map('absint', $group['secondary_unqualified']) : array();

        $badges = array();
        if ($selected_id <= 0) {
            $badges[] = array('label' => __('Select vendor', 'backstage-venue-manager'), 'variant' => 'pending');
        } else {
            if (in_array($selected_id, $group_missing, true)) {
                $badges[] = array('label' => __('Missing vendor', 'backstage-venue-manager'), 'variant' => 'missing');
            } else {
                $availability_state = sanitize_key((string) ($pool_row['availability_state'] ?? ''));
                if ($availability_state === 'available') {
                    $badges[] = array('label' => __('Available', 'backstage-venue-manager'), 'variant' => 'available');
                } elseif ($availability_state === 'unavailable') {
                    $badges[] = array('label' => __('Not available', 'backstage-venue-manager'), 'variant' => 'unavailable');
                } else {
                    $badges[] = array('label' => __('Availability unknown', 'backstage-venue-manager'), 'variant' => 'unknown');
                }
            }

            if (in_array($selected_id, $group_mismatch, true)) {
                $badges[] = array('label' => __('Type mismatch', 'backstage-venue-manager'), 'variant' => 'mismatch');
            }

            if (in_array($selected_id, $group_unqualified, true)) {
                $badges[] = array('label' => __('Needs attention', 'backstage-venue-manager'), 'variant' => 'attention');
            } else {
                $badges[] = array('label' => __('Qualified', 'backstage-venue-manager'), 'variant' => 'qualified');
            }
        }

        ob_start();
        foreach ($badges as $badge) {
            $label = trim((string) ($badge['label'] ?? ''));
            $variant = sanitize_html_class((string) ($badge['variant'] ?? 'unknown'));
            if ($label === '') {
                continue;
            }
            ?>
            <span class="vms-secondary-vendor-badge vms-secondary-vendor-badge--<?php echo esc_attr($variant); ?>"><?php echo esc_html($label); ?></span>
            <?php
        }

        return (string) ob_get_clean();
    }

    private function render_event_plan_secondary_vendors_save_response_group_template_html(array $context): string
    {
        $post_id = (int) ($context['post_id'] ?? 0);

        ob_start();
        ?>
        <template id="vms-secondary-vendor-group-template">
            <div class="vms-secondary-vendor-group vms-secondary-vendor-group--type-pending" data-vms-group-index="" data-vms-missing-ids="[]" data-vms-mismatch-ids="[]" data-vms-unqualified-ids="[]">
                <div class="vms-secondary-vendor-group__header">
                    <label class="vms-secondary-vendor-group__field vms-secondary-vendor-group__field--type">
                        <span class="vms-secondary-vendor-group__field-label"><?php esc_html_e('Vendor type', 'backstage-venue-manager'); ?></span>
                        <select class="vms-secondary-vendor-group-type"></select>
                    </label>
                    <label class="vms-secondary-vendor-group__field vms-secondary-vendor-group__field--mode">
                        <span class="vms-secondary-vendor-group__field-label"><?php esc_html_e('Mode', 'backstage-venue-manager'); ?></span>
                        <select class="vms-secondary-vendor-group-mode"></select>
                    </label>
                    <label class="vms-secondary-vendor-group__field vms-secondary-vendor-group__field--capacity">
                        <span class="vms-secondary-vendor-group__field-label"><?php esc_html_e('Slot limit / capacity', 'backstage-venue-manager'); ?></span>
                        <input type="number" min="0" step="1" class="small-text vms-secondary-vendor-group-slot-limit" value="" />
                    </label>
                    <label class="vms-secondary-vendor-group__field vms-secondary-vendor-group__field--market-target" hidden="hidden">
                        <span class="vms-secondary-vendor-group__field-label"><?php esc_html_e('Market target / needed vendors', 'backstage-venue-manager'); ?></span>
                        <input type="number" min="0" step="1" class="small-text vms-secondary-vendor-group-needed-slots" value="" placeholder="<?php esc_attr_e('Blank', 'backstage-venue-manager'); ?>" disabled="disabled" />
                    </label>
                    <label class="vms-secondary-vendor-group__field vms-secondary-vendor-group__field--market-dispatch" hidden="hidden">
                        <span class="vms-secondary-vendor-group__field-label"><?php esc_html_e('ADD visibility', 'backstage-venue-manager'); ?></span>
                        <span class="vms-secondary-vendor-group__checkbox-line">
                            <input type="hidden" class="vms-secondary-vendor-group-open-for-dispatch-hidden" value="0" disabled="disabled" />
                            <input type="checkbox" class="vms-secondary-vendor-group-open-for-dispatch" value="1" checked="checked" disabled="disabled" />
                            <span><?php esc_html_e('Show this market need in ADD', 'backstage-venue-manager'); ?></span>
                        </span>
                    </label>
                    <div class="vms-secondary-vendor-group__field vms-secondary-vendor-group__field--summary">
                        <span class="vms-secondary-vendor-group__field-label"><?php esc_html_e('Filled', 'backstage-venue-manager'); ?></span>
                        <p class="vms-secondary-vendor-group__summary"></p>
                    </div>
                    <div class="vms-secondary-vendor-group__field vms-secondary-vendor-group__field--actions">
                        <span class="vms-secondary-vendor-group__field-label screen-reader-text"><?php esc_html_e('Actions', 'backstage-venue-manager'); ?></span>
                        <button type="button" class="button button-secondary vms-secondary-vendor-remove-group"><?php esc_html_e('Remove group', 'backstage-venue-manager'); ?></button>
                    </div>
                </div>
                <label class="vms-secondary-vendor-group__override" hidden="hidden">
                    <input type="checkbox" class="vms-secondary-vendor-group-over-capacity-override" value="1" />
                    <span><?php esc_html_e('Allow over-capacity assignment for this group.', 'backstage-venue-manager'); ?></span>
                </label>
                <div class="vms-secondary-vendor-group__rows-toolbar">
                    <div class="vms-secondary-vendor-group__rows-copy">
                        <p class="vms-secondary-vendor-group__label"><?php esc_html_e('Selected vendors', 'backstage-venue-manager'); ?></p>
                        <p class="description vms-secondary-vendor-group__guidance"><?php esc_html_e('Select a vendor type to choose eligible vendors.', 'backstage-venue-manager'); ?></p>
                    </div>
                    <p class="vms-secondary-vendor-actions vms-secondary-vendor-actions--inline">
                        <button type="button" class="button button-secondary vms-secondary-vendor-add-row"><?php esc_html_e('Add vendor row', 'backstage-venue-manager'); ?></button>
                        <a class="button button-secondary vms-secondary-vendor-add-new-link" href="<?php echo esc_url(add_query_arg(array(
                            'post_type' => 'vms_vendor',
                            'vms_return_to_event_plan' => $post_id,
                            'vms_prefill_vendor_role' => 'secondary',
                        ), admin_url('post-new.php'))); ?>" target="_blank" rel="noopener"><?php esc_html_e('Add new vendor', 'backstage-venue-manager'); ?></a>
                    </p>
                </div>
                <div class="vms-secondary-vendor-rows-wrap">
                    <div class="vms-secondary-vendor-rows__head" aria-hidden="true">
                        <span><?php esc_html_e('Vendor', 'backstage-venue-manager'); ?></span>
                        <span><?php esc_html_e('Status', 'backstage-venue-manager'); ?></span>
                        <span><?php esc_html_e('Action', 'backstage-venue-manager'); ?></span>
                    </div>
                    <div class="vms-secondary-vendor-rows"></div>
                </div>
            </div>
        </template>
        <?php

        return (string) ob_get_clean();
    }

    private function render_event_plan_secondary_vendors_save_response_row_template_html(): string
    {
        ob_start();
        ?>
        <template id="vms-secondary-vendor-row-template">
            <div class="vms-secondary-vendor-row" data-vms-row-index="">
                <div class="vms-secondary-vendor-row__vendor">
                    <select class="vms-secondary-vendor-select"></select>
                </div>
                <div class="vms-secondary-vendor-row__indicators" data-vms-secondary-row-indicators>
                    <span class="vms-secondary-vendor-badge vms-secondary-vendor-badge--pending"><?php esc_html_e('Select vendor', 'backstage-venue-manager'); ?></span>
                </div>
                <div class="vms-secondary-vendor-row__action">
                    <button type="button" class="button button-secondary vms-secondary-vendor-remove"><?php esc_html_e('Remove', 'backstage-venue-manager'); ?></button>
                </div>
            </div>
        </template>
        <?php

        return (string) ob_get_clean();
    }

    private function render_event_plan_secondary_vendors_save_response_vendor_category_notice_html(array $context): string
    {
        $vendor_category_rows = isset($context['vendor_category_rows']) && is_array($context['vendor_category_rows'])
            ? $context['vendor_category_rows']
            : array();
        $vendor_category_names = isset($context['vendor_category_names']) && is_array($context['vendor_category_names'])
            ? $context['vendor_category_names']
            : array();

        ob_start();
        ?>
        <div class="notice notice-info inline vms-notice vms-notice--info">
            <p><strong><?php esc_html_e('Vendor category sync', 'backstage-venue-manager'); ?></strong></p>
            <?php if (!empty($vendor_category_rows)) : ?>
                <ul>
                    <?php foreach ($vendor_category_rows as $category_row) : ?>
                        <?php
                        $vendor_title = isset($category_row['vendor_title']) ? (string) $category_row['vendor_title'] : '';
                        $source_label = isset($category_row['source_label']) ? (string) $category_row['source_label'] : '';
                        $category_label = isset($category_row['category_label']) ? (string) $category_row['category_label'] : __('Category', 'backstage-venue-manager');
                        $category_list = isset($category_row['term_names']) && is_array($category_row['term_names']) ? $category_row['term_names'] : array();
                        ?>
                        <li>
                            <strong><?php echo esc_html($source_label); ?><?php if ($vendor_title !== '') : ?>:</strong> <?php echo esc_html($vendor_title); ?><?php else : ?>:</strong> <?php esc_html_e('(not selected)', 'backstage-venue-manager'); ?><?php endif; ?>
                            <?php if (!empty($category_list)) : ?>
                                - <?php echo esc_html($category_label); ?>: <?php echo esc_html(implode(', ', $category_list)); ?>
                            <?php else : ?>
                                - <?php printf(/* translators: %s: lowercased vendor category display label. */ esc_html__('No %s selected yet.', 'backstage-venue-manager'), esc_html(strtolower($category_label))); ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p><?php esc_html_e('No vendor categories are attached yet. Add categories on each vendor profile, then save this Event Plan to snapshot them here.', 'backstage-venue-manager'); ?></p>
            <?php endif; ?>
            <p class="description">
                <?php
                if (!empty($vendor_category_names)) {
                    printf(
                        /* translators: %s: tec event categories that will be synced from this plan. */
                        esc_html__('TEC Event Categories that will be synced from this plan: %s', 'backstage-venue-manager'),
                        esc_html(implode(', ', $vendor_category_names))
                    );
                } else {
                    esc_html_e('When vendor categories exist, they will flow into this Event Plan and then into the linked TEC event categories.', 'backstage-venue-manager');
                }
                ?>
            </p>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private function render_event_plan_secondary_vendors_save_response_selected_attr(bool $selected): string
    {
        return $selected ? ' selected="selected"' : '';
    }

    private function render_event_plan_secondary_vendors_save_response_checked_attr(bool $checked): string
    {
        return $checked ? ' checked="checked"' : '';
    }

    private function render_event_plan_secondary_vendors_save_response_disabled_attr(bool $disabled): string
    {
        return $disabled ? ' disabled="disabled"' : '';
    }

    private function render_event_plan_secondary_vendors_save_response_hidden_attr(bool $hidden): string
    {
        return $hidden ? ' hidden="hidden"' : '';
    }

    private function build_event_plan_secondary_vendors_lazy_load_response_payload(int $post_id): array
    {
        $post_id = absint($post_id);
        $bundle = $this->get_event_plan_meta_bundle($post_id);
        $bands = get_posts(array(
            'post_type'      => 'vms_vendor',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'no_found_rows'  => true,
            'update_post_term_cache' => false,
        ));
        $secondary_vendor_assignments = is_array($bundle['secondary_vendor_assignments'] ?? null)
            ? (array) $bundle['secondary_vendor_assignments']
            : array();
        $secondary_vendor_ids = is_array($bundle['secondary_vendor_ids'] ?? null)
            ? array_values(array_filter(array_map('absint', (array) $bundle['secondary_vendor_ids'])))
            : array();
        $secondary_vendor_boot_summary = $this->get_event_plan_secondary_vendor_boot_summary(
            $post_id,
            is_array($bands) ? $bands : array(),
            (string) ($bundle['event_date'] ?? ''),
            $secondary_vendor_assignments,
            true
        );
        $module_owner = $this->get_event_plan_section_module_owner('secondary_vendors');
        $vendor_category_snapshot = function_exists('bvmgr_event_plan_collect_vendor_category_snapshot')
            ? (array) bvmgr_event_plan_collect_vendor_category_snapshot($post_id)
            : array();
        $context = $this->build_event_plan_secondary_vendors_lazy_load_context(
            $post_id,
            $secondary_vendor_boot_summary,
            $module_owner,
            $vendor_category_snapshot
        );

        $group_count = count((array) ($secondary_vendor_boot_summary['assignment_groups'] ?? array()));
        $has_data = ($group_count > 0 || !empty($secondary_vendor_ids));
        $warning_count = count((array) ($secondary_vendor_boot_summary['secondary_missing'] ?? array()))
            + count((array) ($secondary_vendor_boot_summary['secondary_mismatch'] ?? array()))
            + count((array) ($secondary_vendor_boot_summary['secondary_unqualified'] ?? array()));
        $summary_bits = array();
        if ($group_count === 1) {
            $first_group = (array) reset($secondary_vendor_boot_summary['assignment_groups']);
            $type_name = trim((string) ($first_group['type_name'] ?? ''));
            $type_slug = sanitize_key((string) ($first_group['type_slug'] ?? ''));
            if ($type_name !== '' || $type_slug !== '') {
                $summary_bits[] = $type_name !== '' ? $type_name : $type_slug;
            }
        } elseif ($group_count > 1) {
            /* translators: %d: number of groups. */
            $summary_bits[] = sprintf(_n('%d group', '%d groups', $group_count, 'backstage-venue-manager'), $group_count);
        }
        /* translators: %d: number of selected items. */
        $summary_bits[] = sprintf(_n('%d selected', '%d selected', count((array) $secondary_vendor_ids), 'backstage-venue-manager'), count((array) $secondary_vendor_ids));
        /* translators: %d: number of warnings. */
        $summary_bits[] = sprintf(_n('%d warning', '%d warnings', $warning_count, 'backstage-venue-manager'), $warning_count);

        return array(
            'context' => $context,
            'html' => $this->render_event_plan_secondary_vendors_lazy_load_html($context),
            'has_data' => $has_data,
            'summary_meta' => implode(' • ', array_filter($summary_bits)),
            'module_owner' => $module_owner,
        );
    }

    private function build_event_plan_secondary_vendors_lazy_load_context(
        int $post_id,
        array $secondary_vendor_boot_summary,
        string $module_owner,
        array $vendor_category_snapshot
    ): array {
        $assignment_groups = isset($secondary_vendor_boot_summary['assignment_groups']) && is_array($secondary_vendor_boot_summary['assignment_groups'])
            ? array_values($secondary_vendor_boot_summary['assignment_groups'])
            : array();
        $secondary_type_options = isset($secondary_vendor_boot_summary['secondary_type_options']) && is_array($secondary_vendor_boot_summary['secondary_type_options'])
            ? $secondary_vendor_boot_summary['secondary_type_options']
            : array();
        $secondary_mode_options = isset($secondary_vendor_boot_summary['secondary_mode_options']) && is_array($secondary_vendor_boot_summary['secondary_mode_options'])
            ? $secondary_vendor_boot_summary['secondary_mode_options']
            : array();
        $type_pool_map = isset($secondary_vendor_boot_summary['type_pool_map']) && is_array($secondary_vendor_boot_summary['type_pool_map'])
            ? $secondary_vendor_boot_summary['type_pool_map']
            : array();
        $secondary_missing = isset($secondary_vendor_boot_summary['secondary_missing']) && is_array($secondary_vendor_boot_summary['secondary_missing'])
            ? $secondary_vendor_boot_summary['secondary_missing']
            : array();
        $secondary_mismatch = isset($secondary_vendor_boot_summary['secondary_mismatch']) && is_array($secondary_vendor_boot_summary['secondary_mismatch'])
            ? $secondary_vendor_boot_summary['secondary_mismatch']
            : array();
        $secondary_unqualified = isset($secondary_vendor_boot_summary['secondary_unqualified']) && is_array($secondary_vendor_boot_summary['secondary_unqualified'])
            ? $secondary_vendor_boot_summary['secondary_unqualified']
            : array();
        $secondary_group_type_options = !empty($secondary_type_options) && is_array($secondary_type_options)
            ? $secondary_type_options
            : (function_exists('bvmgr_event_plan_additional_vendor_type_options') ? (array) bvmgr_event_plan_additional_vendor_type_options() : array());
        $vendor_category_rows = isset($vendor_category_snapshot['vendors']) && is_array($vendor_category_snapshot['vendors'])
            ? $vendor_category_snapshot['vendors']
            : array();
        $vendor_category_names = isset($vendor_category_snapshot['term_names']) && is_array($vendor_category_snapshot['term_names'])
            ? $vendor_category_snapshot['term_names']
            : array();

        return array(
            'post_id' => $post_id,
            'module_owner' => sanitize_key($module_owner),
            'assignment_groups' => $assignment_groups,
            'secondary_group_type_options' => $secondary_group_type_options,
            'secondary_mode_options' => $secondary_mode_options,
            'type_pool_map' => $type_pool_map,
            'secondary_missing' => $secondary_missing,
            'secondary_mismatch' => $secondary_mismatch,
            'secondary_unqualified' => $secondary_unqualified,
            'secondary_has_saved_state' => !empty($assignment_groups),
            'secondary_config' => $this->build_event_plan_secondary_vendors_lazy_load_config(
                $secondary_group_type_options,
                $secondary_mode_options,
                $type_pool_map
            ),
            'help_enabled' => function_exists('bvmgr_help_is_enabled') && bvmgr_help_is_enabled(),
            'vendor_category_rows' => $vendor_category_rows,
            'vendor_category_names' => $vendor_category_names,
        );
    }

    private function build_event_plan_secondary_vendors_lazy_load_config(
        array $secondary_group_type_options,
        array $secondary_mode_options,
        array $type_pool_map
    ): array {
        $secondary_config_type_options = array();
        foreach ($secondary_group_type_options as $type_slug => $type_label) {
            $type_slug = sanitize_key((string) $type_slug);
            $type_label = trim((string) $type_label);
            if ($type_slug === '' || $type_label === '') {
                continue;
            }

            $default_mode = function_exists('bvmgr_event_plan_secondary_vendor_default_mode')
                ? (string) bvmgr_event_plan_secondary_vendor_default_mode($type_slug)
                : 'standard';
            $default_slot_limit = function_exists('bvmgr_event_plan_secondary_vendor_default_slot_limit')
                ? bvmgr_event_plan_secondary_vendor_default_slot_limit($type_slug, $default_mode)
                : 1;
            $secondary_config_type_options[] = array(
                'slug' => $type_slug,
                'label' => $type_label,
                'default_mode' => $default_mode,
                'default_slot_limit' => $default_slot_limit,
            );
        }

        $secondary_config_mode_options = array();
        foreach ($secondary_mode_options as $mode_slug => $mode_label) {
            $mode_slug = sanitize_key((string) $mode_slug);
            $mode_label = trim((string) $mode_label);
            if ($mode_slug === '' || $mode_label === '') {
                continue;
            }
            $secondary_config_mode_options[] = array(
                'slug' => $mode_slug,
                'label' => $mode_label,
            );
        }

        return array(
            'typeOptions' => $secondary_config_type_options,
            'modeOptions' => $secondary_config_mode_options,
            'pools' => is_array($type_pool_map) ? $type_pool_map : array(),
            'labels' => array(
                'selectType' => __('-- Select a Vendor Type --', 'backstage-venue-manager'),
                'selectVendor' => __('-- Select a Vendor --', 'backstage-venue-manager'),
                'selectTypeFirst' => __('-- Select a Vendor Type first --', 'backstage-venue-manager'),
                'chooseType' => __('Choose type first', 'backstage-venue-manager'),
                'occupancyUnknown' => __('No slot limit set', 'backstage-venue-manager'),
                'available' => __('Available', 'backstage-venue-manager'),
                'unavailable' => __('Not available', 'backstage-venue-manager'),
                'unknownAvailability' => __('Availability unknown', 'backstage-venue-manager'),
                'qualified' => __('Qualified', 'backstage-venue-manager'),
                'needsAttention' => __('Needs attention', 'backstage-venue-manager'),
                'typeMismatch' => __('Type mismatch', 'backstage-venue-manager'),
                'missingVendor' => __('Missing vendor', 'backstage-venue-manager'),
                'market' => __('Market', 'backstage-venue-manager'),
                'standard' => __('Standard', 'backstage-venue-manager'),
                'pendingVendor' => __('Select vendor', 'backstage-venue-manager'),
                /* translators: %d: number used in this message. */
                'overCapacity' => __('Over capacity by %d', 'backstage-venue-manager'),
                /* translators: %d: number used in this message. */
                'target' => __('Target %d', 'backstage-venue-manager'),
                /* translators: %d: number of items described in this message. */
                'needed' => __('%d needed', 'backstage-venue-manager'),
                'hiddenFromDispatch' => __('Hidden from ADD', 'backstage-venue-manager'),
                'saveUnavailable' => __('Additional Vendors save is not available right now.', 'backstage-venue-manager'),
                'saving' => __('Saving Additional Vendors…', 'backstage-venue-manager'),
                'saveFailed' => __('Additional Vendors could not be saved. Reload the page and try again.', 'backstage-venue-manager'),
            ),
        );
    }

    private function render_event_plan_secondary_vendors_lazy_load_html(array $context): string
    {
        $assignment_groups = isset($context['assignment_groups']) && is_array($context['assignment_groups'])
            ? $context['assignment_groups']
            : array();
        $secondary_has_saved_state = !empty($context['secondary_has_saved_state']);
        $secondary_missing = isset($context['secondary_missing']) && is_array($context['secondary_missing'])
            ? $context['secondary_missing']
            : array();
        $secondary_mismatch = isset($context['secondary_mismatch']) && is_array($context['secondary_mismatch'])
            ? $context['secondary_mismatch']
            : array();
        $secondary_unqualified = isset($context['secondary_unqualified']) && is_array($context['secondary_unqualified'])
            ? $context['secondary_unqualified']
            : array();

        ob_start();
        ?>
        <p class="description">
            <?php esc_html_e('Attach one or more additional vendors to this event. Use separate groups for Food Vendor, Dessert Vendor, Photographer, Market Vendor, and other non-performer vendor types. These vendors will see this date as Tentative when the Event Plan is Draft/Ready and Booked once Published.', 'backstage-venue-manager'); ?>
        </p>

        <div id="vms-secondary-vendors-section"
            data-vms-module-owner="<?php echo esc_attr((string) ($context['module_owner'] ?? 'vendors')); ?>"
            data-vms-save-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
            data-vms-save-nonce="<?php echo esc_attr(wp_create_nonce('bvmgr_event_plan_secondary_vendors_save')); ?>"
            data-vms-save-post-id="<?php echo (int) ($context['post_id'] ?? 0); ?>">
            <input type="hidden" name="vms_secondary_vendors_module_detached" value="1" />
            <input type="hidden" name="vms_clear_secondary_vendors" value="0" id="vms-clear-secondary-vendors-intent" />
            <script type="application/json" data-vms-secondary-config><?php echo wp_json_encode((array) ($context['secondary_config'] ?? array())); ?></script>

            <p class="description vms-mt-8 vms-mb-8"><?php esc_html_e('Use Save Additional Vendors to save changes in this section.', 'backstage-venue-manager'); ?></p>

            <?php if (!$secondary_has_saved_state) : ?>
                <div class="notice notice-info inline vms-notice vms-notice--info">
                    <p><?php esc_html_e('Add a vendor group, then save this section to store your additional vendor assignments.', 'backstage-venue-manager'); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($secondary_missing) || !empty($secondary_mismatch) || !empty($secondary_unqualified)) : ?>
                <p class="description vms-secondary-vendor-legend">
                    <?php
                    echo esc_html__('Availability guide: [✓] Available, [✖] Not Available, [?] Unknown. Qualification guide: [Q✓] Qualified, [Q⚠] Needs attention.', 'backstage-venue-manager');
                    if (function_exists('bvmgr_help_icon')) {
                        bvmgr_help_icon(__('“[Q⚠] Needs attention” means this vendor is missing required profile items (usually phone or email).', 'backstage-venue-manager'));
                    }
                    ?>
                </p>
            <?php endif; ?>

            <div id="vms-secondary-vendor-groups">
                <?php foreach ($assignment_groups as $group_index => $group) : ?>
                    <?php echo $this->render_event_plan_secondary_vendors_lazy_load_group_html((array) $group, (int) $group_index, $context); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php endforeach; ?>
            </div>

            <p class="vms-secondary-vendor-actions">
                <button type="button" class="button button-secondary" id="vms-secondary-vendor-add-group"><?php esc_html_e('Add vendor group', 'backstage-venue-manager'); ?></button>
                <button type="button" class="button button-secondary" id="vms-secondary-vendor-clear"<?php echo $this->render_event_plan_secondary_vendors_lazy_load_disabled_attr(!$secondary_has_saved_state); ?>><?php esc_html_e('Clear additional vendors', 'backstage-venue-manager'); ?></button>
                <button type="button" class="button button-primary" id="vms-secondary-vendor-save"><?php esc_html_e('Save Additional Vendors', 'backstage-venue-manager'); ?></button>
            </p>
            <p class="description vms-mt-8 vms-mb-0" data-vms-secondary-save-status aria-live="polite"></p>

            <?php echo $this->render_event_plan_secondary_vendors_lazy_load_group_template_html($context); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php echo $this->render_event_plan_secondary_vendors_lazy_load_row_template_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php echo $this->render_event_plan_secondary_vendors_lazy_load_vendor_category_notice_html($context); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private function build_event_plan_secondary_vendors_lazy_load_group_summary_context(array $group): array
    {
        $mode = sanitize_key((string) ($group['mode'] ?? 'standard'));
        $type_slug = sanitize_key((string) ($group['type_slug'] ?? ''));
        $is_market_group = ($mode === 'market' || $type_slug === 'market_vendor');
        $has_type = ($type_slug !== '');
        $vendor_ids = isset($group['vendor_ids']) && is_array($group['vendor_ids']) ? $group['vendor_ids'] : array();
        $filled = count(array_filter(array_map('absint', $vendor_ids), static function ($vendor_id): bool {
            return $vendor_id > 0;
        }));
        $slot_limit = array_key_exists('slot_limit', $group) && $group['slot_limit'] !== null && $group['slot_limit'] !== ''
            ? max(0, (int) $group['slot_limit'])
            : null;
        $needed_slots = array_key_exists('needed_slots', $group) && $group['needed_slots'] !== null && $group['needed_slots'] !== ''
            ? max(0, (int) $group['needed_slots'])
            : null;
        $open_for_dispatch = function_exists('bvmgr_event_plan_parse_secondary_vendor_over_capacity_override')
            ? bvmgr_event_plan_parse_secondary_vendor_over_capacity_override($group['open_for_dispatch'] ?? true)
            : !array_key_exists('open_for_dispatch', $group) || !empty($group['open_for_dispatch']);
        $warning = '';
        $over_capacity = false;
        $parts = array();

        if (!$has_type) {
            $parts[] = ($slot_limit === null)
                /* translators: %d: number of selected items. */
                ? sprintf(_n('%d selected', '%d selected', $filled, 'backstage-venue-manager'), $filled)
                /* translators: 1: number 1 used in this message, 2: number 2 used in this message. */
                : sprintf(__('%1$d of %2$d filled', 'backstage-venue-manager'), $filled, $slot_limit);
            $parts[] = __('Choose type first', 'backstage-venue-manager');
        } else {
            $parts[] = ($slot_limit === null)
                /* translators: %d: number of selected items. */
                ? sprintf(_n('%d selected', '%d selected', $filled, 'backstage-venue-manager'), $filled)
                /* translators: 1: number 1 used in this message, 2: number 2 used in this message. */
                : sprintf(__('%1$d of %2$d filled', 'backstage-venue-manager'), $filled, $slot_limit);
            $parts[] = $is_market_group ? __('Market', 'backstage-venue-manager') : __('Standard', 'backstage-venue-manager');
            if ($is_market_group && $needed_slots !== null) {
                /* translators: %d: number used in this message. */
                $parts[] = sprintf(__('Target %d', 'backstage-venue-manager'), $needed_slots);
                $parts[] = $open_for_dispatch
                    /* translators: %d: number of items described in this message. */
                    ? sprintf(_n('%d needed', '%d needed', max(0, $needed_slots - $filled), 'backstage-venue-manager'), max(0, $needed_slots - $filled))
                    : __('Hidden from ADD', 'backstage-venue-manager');
            }
            if ($slot_limit === null) {
                $parts[] = __('No slot limit set', 'backstage-venue-manager');
            } elseif ($filled > $slot_limit) {
                $over_capacity = true;
                /* translators: %d: number used in this message. */
                $warning = sprintf(__('Over capacity by %d', 'backstage-venue-manager'), $filled - $slot_limit);
                $parts[] = $warning;
            }
        }

        return array(
            'text' => implode(' • ', array_filter($parts)),
            'warning' => ($warning !== ''),
            'over_capacity' => $over_capacity,
            'is_market_group' => $is_market_group,
            'has_type' => $has_type,
        );
    }

    private function render_event_plan_secondary_vendors_lazy_load_group_html(array $group, int $group_index, array $context): string
    {
        $type_slug = sanitize_key((string) ($group['type_slug'] ?? ''));
        $type_name = trim((string) ($group['type_name'] ?? ''));
        $mode = sanitize_key((string) ($group['mode'] ?? 'standard'));
        $slot_limit_display = isset($group['slot_limit_display']) ? (string) $group['slot_limit_display'] : '';
        $vendor_ids = isset($group['vendor_ids']) && is_array($group['vendor_ids'])
            ? array_values(array_map('absint', $group['vendor_ids']))
            : array();
        if (empty($vendor_ids)) {
            $vendor_ids = array(0);
        }

        $group_missing = isset($group['secondary_missing']) && is_array($group['secondary_missing']) ? $group['secondary_missing'] : array();
        $group_mismatch = isset($group['secondary_mismatch']) && is_array($group['secondary_mismatch']) ? $group['secondary_mismatch'] : array();
        $group_unqualified = isset($group['secondary_unqualified']) && is_array($group['secondary_unqualified']) ? $group['secondary_unqualified'] : array();
        $group_titles = isset($group['selected_vendor_titles']) && is_array($group['selected_vendor_titles']) ? $group['selected_vendor_titles'] : array();
        $group_missing_map = isset($group['selected_missing_map']) && is_array($group['selected_missing_map']) ? $group['selected_missing_map'] : array();
        $group_summary = $this->build_event_plan_secondary_vendors_lazy_load_group_summary_context($group);
        $is_market_group = !empty($group_summary['is_market_group']);
        $group_has_type = !empty($group_summary['has_type']);
        $allow_over_capacity = function_exists('bvmgr_event_plan_parse_secondary_vendor_over_capacity_override')
            ? bvmgr_event_plan_parse_secondary_vendor_over_capacity_override($group['allow_over_capacity'] ?? false)
            : !empty($group['allow_over_capacity']);
        $is_over_capacity = !empty($group_summary['over_capacity']);
        $needed_slots_display = array_key_exists('needed_slots', $group) && $group['needed_slots'] !== null && $group['needed_slots'] !== ''
            ? (string) max(0, (int) $group['needed_slots'])
            : '';
        $open_for_dispatch = function_exists('bvmgr_event_plan_parse_secondary_vendor_over_capacity_override')
            ? bvmgr_event_plan_parse_secondary_vendor_over_capacity_override($group['open_for_dispatch'] ?? true)
            : !array_key_exists('open_for_dispatch', $group) || !empty($group['open_for_dispatch']);
        $add_secondary_vendor_args = array(
            'post_type' => 'vms_vendor',
            'vms_return_to_event_plan' => (int) ($context['post_id'] ?? 0),
            'vms_prefill_vendor_role' => 'secondary',
        );
        if ($type_slug !== '') {
            $add_secondary_vendor_args['vms_prefill_vendor_type'] = $type_slug;
        }
        $add_secondary_vendor_url = add_query_arg($add_secondary_vendor_args, admin_url('post-new.php'));
        $secondary_group_type_options = isset($context['secondary_group_type_options']) && is_array($context['secondary_group_type_options'])
            ? $context['secondary_group_type_options']
            : array();
        $secondary_mode_options = isset($context['secondary_mode_options']) && is_array($context['secondary_mode_options'])
            ? $context['secondary_mode_options']
            : array();

        ob_start();
        ?>
        <div class="vms-secondary-vendor-group<?php echo $is_market_group ? ' vms-secondary-vendor-group--market' : ''; ?><?php echo !$group_has_type ? ' vms-secondary-vendor-group--type-pending' : ''; ?>"
            data-vms-group-index="<?php echo esc_attr((string) $group_index); ?>"
            data-vms-missing-ids="<?php echo esc_attr(wp_json_encode(array_values(array_map('absint', $group_missing)))); ?>"
            data-vms-mismatch-ids="<?php echo esc_attr(wp_json_encode(array_values(array_map('absint', $group_mismatch)))); ?>"
            data-vms-unqualified-ids="<?php echo esc_attr(wp_json_encode(array_values(array_map('absint', $group_unqualified)))); ?>">
            <div class="vms-secondary-vendor-group__header">
                <label class="vms-secondary-vendor-group__field vms-secondary-vendor-group__field--type">
                    <span class="vms-secondary-vendor-group__field-label"><?php esc_html_e('Vendor type', 'backstage-venue-manager'); ?></span>
                    <select name="<?php echo esc_attr(sprintf('vms_secondary_vendor_assignments[%d][type_slug]', $group_index)); ?>" class="vms-secondary-vendor-group-type">
                        <option value=""><?php esc_html_e('-- Select a Vendor Type --', 'backstage-venue-manager'); ?></option>
                        <?php foreach ($secondary_group_type_options as $option_slug => $option_label) : ?>
                            <?php
                            $option_slug = sanitize_key((string) $option_slug);
                            $option_label = trim((string) $option_label);
                            if ($option_slug === '' || $option_label === '') {
                                continue;
                            }
                            ?>
                            <option value="<?php echo esc_attr($option_slug); ?>"<?php echo $this->render_event_plan_secondary_vendors_lazy_load_selected_attr($type_slug === $option_slug); ?>><?php echo esc_html($option_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="vms-secondary-vendor-group__field vms-secondary-vendor-group__field--mode">
                    <span class="vms-secondary-vendor-group__field-label"><?php esc_html_e('Mode', 'backstage-venue-manager'); ?></span>
                    <select name="<?php echo esc_attr(sprintf('vms_secondary_vendor_assignments[%d][mode]', $group_index)); ?>" class="vms-secondary-vendor-group-mode">
                        <?php foreach ($secondary_mode_options as $mode_slug => $mode_label) : ?>
                            <?php
                            $mode_slug = sanitize_key((string) $mode_slug);
                            if ($mode_slug === '') {
                                continue;
                            }
                            ?>
                            <option value="<?php echo esc_attr($mode_slug); ?>"<?php echo $this->render_event_plan_secondary_vendors_lazy_load_selected_attr($mode === $mode_slug); ?>><?php echo esc_html((string) $mode_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="vms-secondary-vendor-group__field vms-secondary-vendor-group__field--capacity">
                    <span class="vms-secondary-vendor-group__field-label"><?php esc_html_e('Slot limit / capacity', 'backstage-venue-manager'); ?></span>
                    <input type="number" min="0" step="1" class="small-text vms-secondary-vendor-group-slot-limit" name="<?php echo esc_attr(sprintf('vms_secondary_vendor_assignments[%d][slot_limit]', $group_index)); ?>" value="<?php echo esc_attr($slot_limit_display); ?>" placeholder="<?php esc_attr_e('Use default', 'backstage-venue-manager'); ?>" />
                </label>

                <label class="vms-secondary-vendor-group__field vms-secondary-vendor-group__field--market-target"<?php echo $this->render_event_plan_secondary_vendors_lazy_load_hidden_attr(!$is_market_group); ?>>
                    <span class="vms-secondary-vendor-group__field-label"><?php esc_html_e('Market target / needed vendors', 'backstage-venue-manager'); ?></span>
                    <input type="number" min="0" step="1" class="small-text vms-secondary-vendor-group-needed-slots" name="<?php echo esc_attr(sprintf('vms_secondary_vendor_assignments[%d][needed_slots]', $group_index)); ?>" value="<?php echo esc_attr($needed_slots_display); ?>" placeholder="<?php esc_attr_e('Blank', 'backstage-venue-manager'); ?>"<?php echo $this->render_event_plan_secondary_vendors_lazy_load_disabled_attr(!$is_market_group); ?> />
                </label>

                <label class="vms-secondary-vendor-group__field vms-secondary-vendor-group__field--market-dispatch"<?php echo $this->render_event_plan_secondary_vendors_lazy_load_hidden_attr(!$is_market_group); ?>>
                    <span class="vms-secondary-vendor-group__field-label"><?php esc_html_e('ADD visibility', 'backstage-venue-manager'); ?></span>
                    <span class="vms-secondary-vendor-group__checkbox-line">
                        <input type="hidden" class="vms-secondary-vendor-group-open-for-dispatch-hidden" name="<?php echo esc_attr(sprintf('vms_secondary_vendor_assignments[%d][open_for_dispatch]', $group_index)); ?>" value="0"<?php echo $this->render_event_plan_secondary_vendors_lazy_load_disabled_attr(!$is_market_group); ?> />
                        <input type="checkbox" class="vms-secondary-vendor-group-open-for-dispatch" name="<?php echo esc_attr(sprintf('vms_secondary_vendor_assignments[%d][open_for_dispatch]', $group_index)); ?>" value="1"<?php echo $this->render_event_plan_secondary_vendors_lazy_load_checked_attr($open_for_dispatch); ?><?php echo $this->render_event_plan_secondary_vendors_lazy_load_disabled_attr(!$is_market_group); ?> />
                        <span><?php esc_html_e('Show this market need in ADD', 'backstage-venue-manager'); ?></span>
                    </span>
                </label>

                <div class="vms-secondary-vendor-group__field vms-secondary-vendor-group__field--summary">
                    <span class="vms-secondary-vendor-group__field-label"><?php esc_html_e('Filled', 'backstage-venue-manager'); ?></span>
                    <p class="vms-secondary-vendor-group__summary<?php echo !empty($group_summary['warning']) ? ' is-warning' : ''; ?>"><?php echo esc_html((string) ($group_summary['text'] ?? '')); ?></p>
                </div>

                <div class="vms-secondary-vendor-group__field vms-secondary-vendor-group__field--actions">
                    <span class="vms-secondary-vendor-group__field-label screen-reader-text"><?php esc_html_e('Actions', 'backstage-venue-manager'); ?></span>
                    <button type="button" class="button button-secondary vms-secondary-vendor-remove-group"><?php esc_html_e('Remove group', 'backstage-venue-manager'); ?></button>
                </div>
            </div>

            <label class="vms-secondary-vendor-group__override"<?php echo $this->render_event_plan_secondary_vendors_lazy_load_hidden_attr(!$is_over_capacity); ?>>
                <input type="checkbox" class="vms-secondary-vendor-group-over-capacity-override" name="<?php echo esc_attr(sprintf('vms_secondary_vendor_assignments[%d][allow_over_capacity]', $group_index)); ?>" value="1"<?php echo $this->render_event_plan_secondary_vendors_lazy_load_checked_attr($allow_over_capacity); ?> />
                <span><?php esc_html_e('Allow over-capacity assignment for this group.', 'backstage-venue-manager'); ?></span>
            </label>

            <?php if (!empty($group_missing)) : ?>
                <div class="notice notice-warning inline vms-notice vms-notice--warning"><p><?php esc_html_e('🚩 One or more selected vendors no longer exist (or are in the Trash). Remove or replace them below.', 'backstage-venue-manager'); ?></p></div>
            <?php endif; ?>

            <?php if (!empty($group_mismatch)) : ?>
                <?php
                $mismatch_labels = array();
                foreach ($group_mismatch as $vendor_id) {
                    $vendor_id = (int) $vendor_id;
                    /* translators: %d: vendor ID. */
                    $mismatch_labels[] = trim((string) ($group_titles[$vendor_id] ?? sprintf(__('Vendor #%d', 'backstage-venue-manager'), $vendor_id)));
                }
                ?>
                <div class="notice notice-warning inline vms-notice vms-notice--warning">
                    <p>
                        <?php esc_html_e('🚩 One or more selected vendors no longer match this vendor type. Review and re-select vendors below.', 'backstage-venue-manager'); ?>
                        <?php if (!empty($mismatch_labels)) : ?>
                            <?php
                            printf(
                                ' %s',
                                esc_html(
                                    sprintf(
                                        /* translators: %s: affected vendor(s). */
                                        __('Affected vendor(s): %s', 'backstage-venue-manager'),
                                        implode(', ', $mismatch_labels)
                                    )
                                )
                            );
                            ?>
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if (!empty($group_unqualified)) : ?>
                <div class="notice notice-warning inline vms-notice vms-notice--warning">
                    <p><?php esc_html_e('🚩 One or more selected vendors are missing required profile items. They are still attached, but they need attention.', 'backstage-venue-manager'); ?></p>
                    <?php if (!empty($context['help_enabled'])) : ?>
                        <ul class="vms-help-missing-list">
                            <?php foreach ($group_unqualified as $vendor_id) : ?>
                                <?php
                                $vendor_id = (int) $vendor_id;
                                /* translators: %d: vendor ID. */
                                $vendor_label = trim((string) ($group_titles[$vendor_id] ?? sprintf(__('Vendor #%d', 'backstage-venue-manager'), $vendor_id)));
                                $missing_items = isset($group_missing_map[$vendor_id]) && is_array($group_missing_map[$vendor_id]) ? $group_missing_map[$vendor_id] : array();
                                $missing_items = array_map(static function ($missing_item): string {
                                    $missing_item = trim((string) $missing_item);
                                    if ($missing_item === 'Contact info') {
                                        return 'Contact info (phone or email)';
                                    }
                                    return $missing_item;
                                }, $missing_items);
                                ?>
                                <li>
                                    <strong><?php echo esc_html($vendor_label); ?></strong>:
                                    <?php echo esc_html__('Missing:', 'backstage-venue-manager'); ?>
                                    <?php echo esc_html(!empty($missing_items) ? implode(', ', $missing_items) : __('Unknown', 'backstage-venue-manager')); ?>
                                    <a href="<?php echo esc_url(admin_url('post.php?post=' . $vendor_id . '&action=edit')); ?>" target="_blank" rel="noopener"><?php esc_html_e('Edit vendor', 'backstage-venue-manager'); ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="vms-secondary-vendor-group__rows-toolbar">
                <div class="vms-secondary-vendor-group__rows-copy">
                    <p class="vms-secondary-vendor-group__label"><?php esc_html_e('Selected vendors', 'backstage-venue-manager'); ?></p>
                    <p class="description vms-secondary-vendor-group__guidance"<?php echo $this->render_event_plan_secondary_vendors_lazy_load_hidden_attr($group_has_type); ?>><?php esc_html_e('Select a vendor type to choose eligible vendors.', 'backstage-venue-manager'); ?></p>
                </div>
                <p class="vms-secondary-vendor-actions vms-secondary-vendor-actions--inline">
                    <button type="button" class="button button-secondary vms-secondary-vendor-add-row"><?php esc_html_e('Add vendor row', 'backstage-venue-manager'); ?></button>
                    <a class="button button-secondary" href="<?php echo esc_url($add_secondary_vendor_url); ?>" target="_blank" rel="noopener"><?php esc_html_e('Add new vendor', 'backstage-venue-manager'); ?></a>
                </p>
            </div>

            <div class="vms-secondary-vendor-rows-wrap">
                <div class="vms-secondary-vendor-rows__head" aria-hidden="true">
                    <span><?php esc_html_e('Vendor', 'backstage-venue-manager'); ?></span>
                    <span><?php esc_html_e('Status', 'backstage-venue-manager'); ?></span>
                    <span><?php esc_html_e('Action', 'backstage-venue-manager'); ?></span>
                </div>
                <div class="vms-secondary-vendor-rows">
                    <?php foreach ($vendor_ids as $row_index => $vendor_id) : ?>
                        <?php echo $this->render_event_plan_secondary_vendors_lazy_load_row_html($group, (int) $vendor_id, $group_index, (int) $row_index, $context); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private function render_event_plan_secondary_vendors_lazy_load_row_html(
        array $group,
        int $selected_id,
        int $group_index,
        int $row_index,
        array $context
    ): string {
        $type_slug = sanitize_key((string) ($group['type_slug'] ?? ''));
        $pool_option_rows = isset($context['type_pool_map'][$type_slug]) && is_array($context['type_pool_map'][$type_slug])
            ? $context['type_pool_map'][$type_slug]
            : array();
        $field_name = sprintf('vms_secondary_vendor_assignments[%d][vendor_ids][]', $group_index);

        ob_start();
        ?>
        <div class="vms-secondary-vendor-row" data-vms-row-index="<?php echo esc_attr((string) $row_index); ?>">
            <div class="vms-secondary-vendor-row__vendor">
                <select name="<?php echo esc_attr($field_name); ?>" class="vms-secondary-vendor-select" data-selected-id="<?php echo esc_attr((string) $selected_id); ?>"<?php echo $this->render_event_plan_secondary_vendors_lazy_load_disabled_attr($type_slug === ''); ?>>
                    <?php if ($type_slug === '') : ?>
                        <option value=""><?php esc_html_e('-- Select a Vendor Type first --', 'backstage-venue-manager'); ?></option>
                    <?php else : ?>
                        <option value=""><?php esc_html_e('-- Select a Vendor --', 'backstage-venue-manager'); ?></option>
                        <?php foreach ($pool_option_rows as $pool_row) : ?>
                            <?php
                            if (!is_array($pool_row)) {
                                continue;
                            }
                            $vendor_id = absint($pool_row['vendor_id'] ?? 0);
                            if ($vendor_id <= 0) {
                                continue;
                            }
                            $label = trim((string) ($pool_row['label'] ?? ''));
                            if ($label === '') {
                                $label = trim((string) ($pool_row['vendor_title'] ?? ''));
                            }
                            ?>
                            <option value="<?php echo esc_attr((string) $vendor_id); ?>"<?php echo $this->render_event_plan_secondary_vendors_lazy_load_selected_attr($selected_id === $vendor_id); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="vms-secondary-vendor-row__indicators" data-vms-secondary-row-indicators>
                <?php echo $this->render_event_plan_secondary_vendors_lazy_load_status_badges_html($group, $selected_id, $pool_option_rows); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
            <div class="vms-secondary-vendor-row__action">
                <button type="button" class="button button-secondary vms-secondary-vendor-remove"><?php esc_html_e('Remove', 'backstage-venue-manager'); ?></button>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private function render_event_plan_secondary_vendors_lazy_load_status_badges_html(array $group, int $selected_id, array $pool_option_rows): string
    {
        $selected_id = absint($selected_id);
        $pool_row = array();
        foreach ($pool_option_rows as $candidate_row) {
            if (!is_array($candidate_row)) {
                continue;
            }
            if (absint($candidate_row['vendor_id'] ?? 0) === $selected_id) {
                $pool_row = $candidate_row;
                break;
            }
        }

        $group_missing = isset($group['secondary_missing']) && is_array($group['secondary_missing']) ? array_map('absint', $group['secondary_missing']) : array();
        $group_mismatch = isset($group['secondary_mismatch']) && is_array($group['secondary_mismatch']) ? array_map('absint', $group['secondary_mismatch']) : array();
        $group_unqualified = isset($group['secondary_unqualified']) && is_array($group['secondary_unqualified']) ? array_map('absint', $group['secondary_unqualified']) : array();

        $badges = array();
        if ($selected_id <= 0) {
            $badges[] = array('label' => __('Select vendor', 'backstage-venue-manager'), 'variant' => 'pending');
        } else {
            if (in_array($selected_id, $group_missing, true)) {
                $badges[] = array('label' => __('Missing vendor', 'backstage-venue-manager'), 'variant' => 'missing');
            } else {
                $availability_state = sanitize_key((string) ($pool_row['availability_state'] ?? ''));
                if ($availability_state === 'available') {
                    $badges[] = array('label' => __('Available', 'backstage-venue-manager'), 'variant' => 'available');
                } elseif ($availability_state === 'unavailable') {
                    $badges[] = array('label' => __('Not available', 'backstage-venue-manager'), 'variant' => 'unavailable');
                } else {
                    $badges[] = array('label' => __('Availability unknown', 'backstage-venue-manager'), 'variant' => 'unknown');
                }
            }

            if (in_array($selected_id, $group_mismatch, true)) {
                $badges[] = array('label' => __('Type mismatch', 'backstage-venue-manager'), 'variant' => 'mismatch');
            }

            if (in_array($selected_id, $group_unqualified, true)) {
                $badges[] = array('label' => __('Needs attention', 'backstage-venue-manager'), 'variant' => 'attention');
            } else {
                $badges[] = array('label' => __('Qualified', 'backstage-venue-manager'), 'variant' => 'qualified');
            }
        }

        ob_start();
        foreach ($badges as $badge) {
            $label = trim((string) ($badge['label'] ?? ''));
            $variant = sanitize_html_class((string) ($badge['variant'] ?? 'unknown'));
            if ($label === '') {
                continue;
            }
            ?>
            <span class="vms-secondary-vendor-badge vms-secondary-vendor-badge--<?php echo esc_attr($variant); ?>"><?php echo esc_html($label); ?></span>
            <?php
        }

        return (string) ob_get_clean();
    }

    private function render_event_plan_secondary_vendors_lazy_load_group_template_html(array $context): string
    {
        $post_id = (int) ($context['post_id'] ?? 0);

        ob_start();
        ?>
        <template id="vms-secondary-vendor-group-template">
            <div class="vms-secondary-vendor-group vms-secondary-vendor-group--type-pending" data-vms-group-index="" data-vms-missing-ids="[]" data-vms-mismatch-ids="[]" data-vms-unqualified-ids="[]">
                <div class="vms-secondary-vendor-group__header">
                    <label class="vms-secondary-vendor-group__field vms-secondary-vendor-group__field--type">
                        <span class="vms-secondary-vendor-group__field-label"><?php esc_html_e('Vendor type', 'backstage-venue-manager'); ?></span>
                        <select class="vms-secondary-vendor-group-type"></select>
                    </label>
                    <label class="vms-secondary-vendor-group__field vms-secondary-vendor-group__field--mode">
                        <span class="vms-secondary-vendor-group__field-label"><?php esc_html_e('Mode', 'backstage-venue-manager'); ?></span>
                        <select class="vms-secondary-vendor-group-mode"></select>
                    </label>
                    <label class="vms-secondary-vendor-group__field vms-secondary-vendor-group__field--capacity">
                        <span class="vms-secondary-vendor-group__field-label"><?php esc_html_e('Slot limit / capacity', 'backstage-venue-manager'); ?></span>
                        <input type="number" min="0" step="1" class="small-text vms-secondary-vendor-group-slot-limit" value="" />
                    </label>
                    <label class="vms-secondary-vendor-group__field vms-secondary-vendor-group__field--market-target" hidden="hidden">
                        <span class="vms-secondary-vendor-group__field-label"><?php esc_html_e('Market target / needed vendors', 'backstage-venue-manager'); ?></span>
                        <input type="number" min="0" step="1" class="small-text vms-secondary-vendor-group-needed-slots" value="" placeholder="<?php esc_attr_e('Blank', 'backstage-venue-manager'); ?>" disabled="disabled" />
                    </label>
                    <label class="vms-secondary-vendor-group__field vms-secondary-vendor-group__field--market-dispatch" hidden="hidden">
                        <span class="vms-secondary-vendor-group__field-label"><?php esc_html_e('ADD visibility', 'backstage-venue-manager'); ?></span>
                        <span class="vms-secondary-vendor-group__checkbox-line">
                            <input type="hidden" class="vms-secondary-vendor-group-open-for-dispatch-hidden" value="0" disabled="disabled" />
                            <input type="checkbox" class="vms-secondary-vendor-group-open-for-dispatch" value="1" checked="checked" disabled="disabled" />
                            <span><?php esc_html_e('Show this market need in ADD', 'backstage-venue-manager'); ?></span>
                        </span>
                    </label>
                    <div class="vms-secondary-vendor-group__field vms-secondary-vendor-group__field--summary">
                        <span class="vms-secondary-vendor-group__field-label"><?php esc_html_e('Filled', 'backstage-venue-manager'); ?></span>
                        <p class="vms-secondary-vendor-group__summary"></p>
                    </div>
                    <div class="vms-secondary-vendor-group__field vms-secondary-vendor-group__field--actions">
                        <span class="vms-secondary-vendor-group__field-label screen-reader-text"><?php esc_html_e('Actions', 'backstage-venue-manager'); ?></span>
                        <button type="button" class="button button-secondary vms-secondary-vendor-remove-group"><?php esc_html_e('Remove group', 'backstage-venue-manager'); ?></button>
                    </div>
                </div>
                <label class="vms-secondary-vendor-group__override" hidden="hidden">
                    <input type="checkbox" class="vms-secondary-vendor-group-over-capacity-override" value="1" />
                    <span><?php esc_html_e('Allow over-capacity assignment for this group.', 'backstage-venue-manager'); ?></span>
                </label>
                <div class="vms-secondary-vendor-group__rows-toolbar">
                    <div class="vms-secondary-vendor-group__rows-copy">
                        <p class="vms-secondary-vendor-group__label"><?php esc_html_e('Selected vendors', 'backstage-venue-manager'); ?></p>
                        <p class="description vms-secondary-vendor-group__guidance"><?php esc_html_e('Select a vendor type to choose eligible vendors.', 'backstage-venue-manager'); ?></p>
                    </div>
                    <p class="vms-secondary-vendor-actions vms-secondary-vendor-actions--inline">
                        <button type="button" class="button button-secondary vms-secondary-vendor-add-row"><?php esc_html_e('Add vendor row', 'backstage-venue-manager'); ?></button>
                        <a class="button button-secondary vms-secondary-vendor-add-new-link" href="<?php echo esc_url(add_query_arg(array(
                            'post_type' => 'vms_vendor',
                            'vms_return_to_event_plan' => $post_id,
                            'vms_prefill_vendor_role' => 'secondary',
                        ), admin_url('post-new.php'))); ?>" target="_blank" rel="noopener"><?php esc_html_e('Add new vendor', 'backstage-venue-manager'); ?></a>
                    </p>
                </div>
                <div class="vms-secondary-vendor-rows-wrap">
                    <div class="vms-secondary-vendor-rows__head" aria-hidden="true">
                        <span><?php esc_html_e('Vendor', 'backstage-venue-manager'); ?></span>
                        <span><?php esc_html_e('Status', 'backstage-venue-manager'); ?></span>
                        <span><?php esc_html_e('Action', 'backstage-venue-manager'); ?></span>
                    </div>
                    <div class="vms-secondary-vendor-rows"></div>
                </div>
            </div>
        </template>
        <?php

        return (string) ob_get_clean();
    }

    private function render_event_plan_secondary_vendors_lazy_load_row_template_html(): string
    {
        ob_start();
        ?>
        <template id="vms-secondary-vendor-row-template">
            <div class="vms-secondary-vendor-row" data-vms-row-index="">
                <div class="vms-secondary-vendor-row__vendor">
                    <select class="vms-secondary-vendor-select"></select>
                </div>
                <div class="vms-secondary-vendor-row__indicators" data-vms-secondary-row-indicators>
                    <span class="vms-secondary-vendor-badge vms-secondary-vendor-badge--pending"><?php esc_html_e('Select vendor', 'backstage-venue-manager'); ?></span>
                </div>
                <div class="vms-secondary-vendor-row__action">
                    <button type="button" class="button button-secondary vms-secondary-vendor-remove"><?php esc_html_e('Remove', 'backstage-venue-manager'); ?></button>
                </div>
            </div>
        </template>
        <?php

        return (string) ob_get_clean();
    }

    private function render_event_plan_secondary_vendors_lazy_load_vendor_category_notice_html(array $context): string
    {
        $vendor_category_rows = isset($context['vendor_category_rows']) && is_array($context['vendor_category_rows'])
            ? $context['vendor_category_rows']
            : array();
        $vendor_category_names = isset($context['vendor_category_names']) && is_array($context['vendor_category_names'])
            ? $context['vendor_category_names']
            : array();

        ob_start();
        ?>
        <div class="notice notice-info inline vms-notice vms-notice--info">
            <p><strong><?php esc_html_e('Vendor category sync', 'backstage-venue-manager'); ?></strong></p>
            <?php if (!empty($vendor_category_rows)) : ?>
                <ul>
                    <?php foreach ($vendor_category_rows as $category_row) : ?>
                        <?php
                        $vendor_title = isset($category_row['vendor_title']) ? (string) $category_row['vendor_title'] : '';
                        $source_label = isset($category_row['source_label']) ? (string) $category_row['source_label'] : '';
                        $category_label = isset($category_row['category_label']) ? (string) $category_row['category_label'] : __('Category', 'backstage-venue-manager');
                        $category_list = isset($category_row['term_names']) && is_array($category_row['term_names']) ? $category_row['term_names'] : array();
                        ?>
                        <li>
                            <strong><?php echo esc_html($source_label); ?><?php if ($vendor_title !== '') : ?>:</strong> <?php echo esc_html($vendor_title); ?><?php else : ?>:</strong> <?php esc_html_e('(not selected)', 'backstage-venue-manager'); ?><?php endif; ?>
                            <?php if (!empty($category_list)) : ?>
                                - <?php echo esc_html($category_label); ?>: <?php echo esc_html(implode(', ', $category_list)); ?>
                            <?php else : ?>
                                - <?php printf(/* translators: %s: lowercased vendor category display label. */ esc_html__('No %s selected yet.', 'backstage-venue-manager'), esc_html(strtolower($category_label))); ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p><?php esc_html_e('No vendor categories are attached yet. Add categories on each vendor profile, then save this Event Plan to snapshot them here.', 'backstage-venue-manager'); ?></p>
            <?php endif; ?>
            <p class="description">
                <?php
                if (!empty($vendor_category_names)) {
                    printf(
                        /* translators: %s: tec event categories that will be synced from this plan. */
                        esc_html__('TEC Event Categories that will be synced from this plan: %s', 'backstage-venue-manager'),
                        esc_html(implode(', ', $vendor_category_names))
                    );
                } else {
                    esc_html_e('When vendor categories exist, they will flow into this Event Plan and then into the linked TEC event categories.', 'backstage-venue-manager');
                }
                ?>
            </p>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private function render_event_plan_secondary_vendors_lazy_load_selected_attr(bool $selected): string
    {
        return $selected ? ' selected="selected"' : '';
    }

    private function render_event_plan_secondary_vendors_lazy_load_checked_attr(bool $checked): string
    {
        return $checked ? ' checked="checked"' : '';
    }

    private function render_event_plan_secondary_vendors_lazy_load_disabled_attr(bool $disabled): string
    {
        return $disabled ? ' disabled="disabled"' : '';
    }

    private function render_event_plan_secondary_vendors_lazy_load_hidden_attr(bool $hidden): string
    {
        return $hidden ? ' hidden="hidden"' : '';
    }

    /**
     * Seed the Event Plan editor with a sensible first-load metabox order while
     * still allowing each user to save and keep a custom drag/drop layout.
     *
     * @param mixed       $result Existing user option value.
     * @param string      $option User option name.
     * @param int|WP_User $user   User object or user ID.
     * @return array<string,string>|mixed
     */
    public function default_metabox_order($result, string $option, $user)
    {
        unset($option, $user);

        if (is_array($result)) {
            return $result;
        }

        return array(
            'normal' => implode(',', array(
                'vms_event_plan_details',
                'vms_event_plan_ticketing_v2',
                'vms_guest_list_comp_admission',
                'vms-discounts-event-rules',
                'vms-event-plan-tasks',
                'vms_square_door_split',
                'vms_event_plan_goals_finance',
                'vms_express_bar',
                'vms_social_promotion',
                'vms_ma_event_plan_meta_ads',
                'vms_event_plan_advanced_controls',
            )),
            'side' => '',
            'advanced' => '',
        );
    }

    private function get_event_plan_admin_boot_cached_value(int $plan_id, string $cache_key, callable $loader, string $hook_name, array $context = array())
    {
        $plan_id = absint($plan_id);
        if ($plan_id <= 0) {
            return $loader();
        }

        if (!isset($this->event_plan_admin_boot_cache[$plan_id]) || !is_array($this->event_plan_admin_boot_cache[$plan_id])) {
            $this->event_plan_admin_boot_cache[$plan_id] = array();
        }

        $cache_hit = array_key_exists($cache_key, $this->event_plan_admin_boot_cache[$plan_id]);
        $trace_context = array_merge($context, array(
            'cache' => $cache_hit ? 'hit' : 'miss',
        ));
        $trace = function_exists('bvmgr_event_plan_perf_span_start')
            ? bvmgr_event_plan_perf_span_start($hook_name, $plan_id, $trace_context)
            : '';

        try {
            if (function_exists('bvmgr_event_plan_perf_log')) {
                bvmgr_event_plan_perf_log('event_plan_query_optimization', $plan_id, array(
                    'phase' => sanitize_key($cache_key),
                    'cache' => $cache_hit ? 'hit' : 'miss',
                    'source_hook' => sanitize_key($hook_name),
                ));
            }
            if (!$cache_hit) {
                $this->event_plan_admin_boot_cache[$plan_id][$cache_key] = $loader();
            }

            return $this->event_plan_admin_boot_cache[$plan_id][$cache_key];
        } finally {
            if (function_exists('bvmgr_event_plan_perf_span_finish')) {
                bvmgr_event_plan_perf_span_finish($hook_name, $plan_id, $trace, $trace_context);
            }
        }
    }

    private function get_event_plan_admin_boot_local_cache_value(int $plan_id, string $cache_key, callable $loader, ?bool &$cache_hit = null)
    {
        $plan_id = absint($plan_id);
        if ($plan_id <= 0) {
            $cache_hit = false;
            return $loader();
        }

        if (!isset($this->event_plan_admin_boot_cache[$plan_id]) || !is_array($this->event_plan_admin_boot_cache[$plan_id])) {
            $this->event_plan_admin_boot_cache[$plan_id] = array();
        }

        $cache_hit = array_key_exists($cache_key, $this->event_plan_admin_boot_cache[$plan_id]);
        if (!$cache_hit) {
            $this->event_plan_admin_boot_cache[$plan_id][$cache_key] = $loader();
        }

        return $this->event_plan_admin_boot_cache[$plan_id][$cache_key];
    }

    private function get_event_plan_cached_vendor_availability_state(int $plan_id, int $vendor_id, string $event_date, ?bool &$cache_hit = null): string
    {
        $vendor_id = absint($vendor_id);
        $event_date = trim((string) $event_date);
        if ($vendor_id <= 0 || $event_date === '') {
            $cache_hit = false;
            return '';
        }

        $cache_key = 'vendor_availability_state:' . $vendor_id . '|' . $event_date;
        $value = $this->get_event_plan_admin_boot_local_cache_value(
            $plan_id,
            $cache_key,
            static function () use ($vendor_id, $event_date): string {
                if (!function_exists('bvmgr_get_vendor_availability_for_date')) {
                    return '';
                }

                return sanitize_key((string) bvmgr_get_vendor_availability_for_date($vendor_id, $event_date));
            },
            $cache_hit
        );

        return sanitize_key((string) $value);
    }

    private function get_event_plan_cached_supporting_vendor_default(int $plan_id, int $vendor_id, int $venue_id_effective, string $event_date, ?bool &$cache_hit = null): array
    {
        $vendor_id = absint($vendor_id);
        $venue_id_effective = absint($venue_id_effective);
        $event_date = trim((string) $event_date);
        if ($vendor_id <= 0) {
            $cache_hit = false;
            return array(
                'guaranteed_fee' => '',
                'structure' => '',
            );
        }

        $cache_key = 'supporting_vendor_default:' . $vendor_id . '|' . $venue_id_effective . '|' . $event_date;
        $value = $this->get_event_plan_admin_boot_local_cache_value(
            $plan_id,
            $cache_key,
            static function () use ($vendor_id, $venue_id_effective, $event_date): array {
                if (!function_exists('bvmgr_get_lineup_supporting_compensation_default')) {
                    return array(
                        'guaranteed_fee' => '',
                        'structure' => '',
                    );
                }

                $support_defaults = (array) bvmgr_get_lineup_supporting_compensation_default($vendor_id, $venue_id_effective, $event_date);
                return array(
                    'guaranteed_fee' => $support_defaults['guaranteed_fee'] ?? '',
                    'structure' => sanitize_key((string) ($support_defaults['structure'] ?? '')),
                );
            },
            $cache_hit
        );

        return is_array($value) ? $value : array(
            'guaranteed_fee' => '',
            'structure' => '',
        );
    }

    private function get_event_plan_cached_vendor_tax_summary(int $plan_id, int $vendor_id, ?bool &$cache_hit = null): array
    {
        $vendor_id = absint($vendor_id);
        if ($vendor_id <= 0) {
            $cache_hit = false;
            return array(
                'missing' => array(),
                'tax_ok' => true,
                'tax_bypass_active' => false,
                'tax_bypass_until' => '',
                'tax_bypass_reason' => '',
            );
        }

        $cache_key = 'vendor_tax_summary:' . $vendor_id;
        $value = $this->get_event_plan_admin_boot_local_cache_value(
            $plan_id,
            $cache_key,
            static function () use ($vendor_id): array {
                $missing = function_exists('bvmgr_vendor_tax_profile_missing_items')
                    ? (array) bvmgr_vendor_tax_profile_missing_items($vendor_id)
                    : array();
                $bypass = function_exists('bvmgr_get_tax_bypass_status')
                    ? (array) bvmgr_get_tax_bypass_status($vendor_id)
                    : array();

                return array(
                    'missing' => array_values(array_filter(array_map('strval', $missing))),
                    'tax_ok' => empty($missing),
                    'tax_bypass_active' => !empty($bypass['is_active']),
                    'tax_bypass_until' => isset($bypass['until']) ? (string) $bypass['until'] : '',
                    'tax_bypass_reason' => isset($bypass['reason']) ? (string) $bypass['reason'] : '',
                );
            },
            $cache_hit
        );

        return is_array($value) ? $value : array(
            'missing' => array(),
            'tax_ok' => true,
            'tax_bypass_active' => false,
            'tax_bypass_until' => '',
            'tax_bypass_reason' => '',
        );
    }

    private function get_event_plan_cached_secondary_vendor_missing_items(int $plan_id, int $vendor_id, string $type_slug, ?bool &$cache_hit = null): array
    {
        $vendor_id = absint($vendor_id);
        $type_slug = sanitize_key($type_slug);
        if ($vendor_id <= 0) {
            $cache_hit = false;
            return array();
        }

        $cache_key = 'secondary_vendor_missing_items:' . $vendor_id . '|' . $type_slug;
        $value = $this->get_event_plan_admin_boot_local_cache_value(
            $plan_id,
            $cache_key,
            static function () use ($plan_id, $vendor_id, $type_slug): array {
                if (!function_exists('bvmgr_secondary_vendor_missing_items')) {
                    return array();
                }

                $missing = (array) bvmgr_secondary_vendor_missing_items($vendor_id, array(
                    'context'   => 'event_plan_secondary_vendor',
                    'plan_id'   => $plan_id,
                    'type_slug' => $type_slug,
                ));

                return array_values(array_filter(array_map('strval', $missing)));
            },
            $cache_hit
        );

        return is_array($value) ? $value : array();
    }

    private function get_event_plan_cached_vendor_type_match(int $plan_id, int $vendor_id, string $type_slug, ?bool &$cache_hit = null): bool
    {
        $vendor_id = absint($vendor_id);
        $type_slug = sanitize_key($type_slug);
        if ($vendor_id <= 0 || $type_slug === '') {
            $cache_hit = false;
            return true;
        }

        $cache_key = 'vendor_type_match:' . $vendor_id . '|' . $type_slug;
        $value = $this->get_event_plan_admin_boot_local_cache_value(
            $plan_id,
            $cache_key,
            static function () use ($vendor_id, $type_slug): bool {
                if (function_exists('bvmgr_vendor_has_type')) {
                    return (bool) bvmgr_vendor_has_type($vendor_id, $type_slug);
                }

                if (function_exists('has_term')) {
                    return (bool) has_term($type_slug, 'vms_vendor_type', $vendor_id);
                }

                return true;
            },
            $cache_hit
        );

        return (bool) $value;
    }

    private function get_event_plan_cached_vendor_type_slug_map(int $plan_id, array $vendor_ids, ?bool &$cache_hit = null): array
    {
        $vendor_ids = array_values(array_unique(array_filter(array_map('absint', $vendor_ids), static function ($vendor_id): bool {
            return $vendor_id > 0;
        })));
        if (empty($vendor_ids) || !taxonomy_exists('vms_vendor_type')) {
            $cache_hit = false;
            return array();
        }

        $cache_key = 'vendor_type_slug_map:' . md5(implode(',', $vendor_ids));
        $value = $this->get_event_plan_admin_boot_local_cache_value(
            $plan_id,
            $cache_key,
            static function () use ($vendor_ids): array {
                $map = array();
                $terms = wp_get_object_terms($vendor_ids, 'vms_vendor_type', array(
                    'fields' => 'all_with_object_id',
                ));

                if (is_wp_error($terms) || !is_array($terms)) {
                    return $map;
                }

                foreach ($terms as $term) {
                    if (!$term instanceof WP_Term || !isset($term->object_id)) {
                        continue;
                    }

                    $vendor_id = absint($term->object_id);
                    if ($vendor_id <= 0) {
                        continue;
                    }

                    $slug = function_exists('bvmgr_vendor_type_canonical_slug_for_term')
                        ? bvmgr_vendor_type_canonical_slug_for_term($term)
                        : sanitize_key((string) $term->slug);
                    if ($slug === '') {
                        continue;
                    }

                    if (!isset($map[$vendor_id]) || !is_array($map[$vendor_id])) {
                        $map[$vendor_id] = array();
                    }
                    $map[$vendor_id][$slug] = true;
                }

                foreach ($map as $vendor_id => $slug_map) {
                    $map[$vendor_id] = array_values(array_keys(is_array($slug_map) ? $slug_map : array()));
                }

                return $map;
            },
            $cache_hit
        );

        return is_array($value) ? $value : array();
    }

    private function get_event_plan_meta_bundle(int $plan_id): array
    {
        $plan_id = absint($plan_id);

        return (array) $this->get_event_plan_admin_boot_cached_value(
            $plan_id,
            'meta_bundle',
            function () use ($plan_id): array {
                $all_meta = get_post_meta($plan_id);
                if (!is_array($all_meta)) {
                    $all_meta = array();
                }

                $read_meta = static function (string $key, $default = '') use ($all_meta) {
                    if ($key === '' || !isset($all_meta[$key][0])) {
                        return $default;
                    }

                    return maybe_unserialize($all_meta[$key][0]);
                };

                $secondary_ids_key = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'secondary_vendor_ids') : '_vms_secondary_vendor_ids';
                if ($secondary_ids_key === '') {
                    $secondary_ids_key = '_vms_secondary_vendor_ids';
                }
                $secondary_type_key = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'secondary_vendor_type') : '_vms_secondary_vendor_type';
                if ($secondary_type_key === '') {
                    $secondary_type_key = '_vms_secondary_vendor_type';
                }
                $status_key = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'status') : '_vms_event_plan_status';
                if ($status_key === '') {
                    $status_key = '_vms_event_plan_status';
                }
                $integrity_key = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'integrity_issue') : '_vms_integrity_issue';
                if ($integrity_key === '') {
                    $integrity_key = '_vms_integrity_issue';
                }
                $tec_id_key = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'tec_event_id') : '_vms_tec_event_id';
                if ($tec_id_key === '') {
                    $tec_id_key = '_vms_tec_event_id';
                }
                $tec_url_key = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'tec_event_url') : '_vms_tec_event_url';
                if ($tec_url_key === '') {
                    $tec_url_key = '_vms_tec_event_url';
                }
                $ticket_product_ids_key = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'ticket_product_ids') : '_vms_ticket_product_ids_v1';
                if ($ticket_product_ids_key === '') {
                    $ticket_product_ids_key = '_vms_ticket_product_ids_v1';
                }
                $ticket_stats_key = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'ticket_stats') : '_vms_ticket_stats_v1';
                if ($ticket_stats_key === '') {
                    $ticket_stats_key = '_vms_ticket_stats_v1';
                }
                $manual_ticket_ids_key = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'ticket_manual_product_ids') : '_vms_ticket_manual_product_ids_v1';
                if ($manual_ticket_ids_key === '') {
                    $manual_ticket_ids_key = '_vms_ticket_manual_product_ids_v1';
                }
                $ticketing_v2_config_key = function_exists('bvmgr_event_plan_ticketing_v2_config_meta_key')
                    ? (string) bvmgr_event_plan_ticketing_v2_config_meta_key()
                    : '_vms_ticketing_config_v2';
                $ticketing_v2_sync_key = function_exists('bvmgr_ticketing_v2_k')
                    ? (string) bvmgr_ticketing_v2_k('sync')
                    : '_vms_ticketing_sync_v2';

                $manual_ticket_product_ids = $read_meta($manual_ticket_ids_key, array());
                if (!is_array($manual_ticket_product_ids)) {
                    $manual_ticket_product_ids = array();
                }
                $manual_ticket_product_ids = array_values(array_unique(array_filter(array_map('absint', $manual_ticket_product_ids), static function ($value): bool {
                    return $value > 0;
                })));

                $ticket_product_ids = $read_meta($ticket_product_ids_key, array());
                if (!is_array($ticket_product_ids)) {
                    $ticket_product_ids = array();
                }

                $ticket_stats = $read_meta($ticket_stats_key, array());
                if (!is_array($ticket_stats)) {
                    $ticket_stats = array();
                }

                $staff_assignments = $read_meta('_vms_staff_assignments', array());
                if (!is_array($staff_assignments)) {
                    $staff_assignments = array();
                }

                $ticketing_v2_config = $read_meta($ticketing_v2_config_key, array());
                if (!is_array($ticketing_v2_config)) {
                    $ticketing_v2_config = array();
                }
                $ticketing_v2_sync = $read_meta($ticketing_v2_sync_key, array());
                if (!is_array($ticketing_v2_sync)) {
                    $ticketing_v2_sync = array();
                }

                $band_vendor_id = absint($read_meta('_vms_band_vendor_id', 0));
                $secondary_vendor_assignments = function_exists('bvmgr_event_plan_get_secondary_vendor_assignments')
                    ? (array) bvmgr_event_plan_get_secondary_vendor_assignments($plan_id, array(
                        'primary_vendor_id' => $band_vendor_id,
                    ))
                    : array();
                $secondary_vendor_ids = !empty($secondary_vendor_assignments) && function_exists('bvmgr_event_plan_get_secondary_vendor_flat_ids_from_assignments')
                    ? bvmgr_event_plan_get_secondary_vendor_flat_ids_from_assignments($secondary_vendor_assignments, $band_vendor_id)
                    : $read_meta($secondary_ids_key, array());
                if (!is_array($secondary_vendor_ids)) {
                    $secondary_vendor_ids = array();
                }
                $secondary_vendor_ids = array_values(array_unique(array_filter(array_map('absint', $secondary_vendor_ids), static function ($value): bool {
                    return $value > 0;
                })));

                $secondary_vendor_type = !empty($secondary_vendor_assignments) && function_exists('bvmgr_event_plan_legacy_secondary_vendor_type_from_assignments')
                    ? (string) bvmgr_event_plan_legacy_secondary_vendor_type_from_assignments($secondary_vendor_assignments)
                    : (string) $read_meta($secondary_type_key, '');
                if ($secondary_vendor_type !== '') {
                    $secondary_vendor_type = function_exists('bvmgr_vendor_type_normalize_slug')
                        ? (string) bvmgr_vendor_type_normalize_slug($secondary_vendor_type)
                        : sanitize_title($secondary_vendor_type);
                }

                return array(
                    'event_date' => (string) $read_meta('_vms_event_date', ''),
                    'venue_id' => absint($read_meta('_vms_venue_id', 0)),
                    'band_vendor_id' => $band_vendor_id,
                    'plan_status' => sanitize_key((string) $read_meta($status_key, '')),
                    'integrity_issue' => sanitize_key((string) $read_meta($integrity_key, '')),
                    'linked_tec_id' => absint($read_meta($tec_id_key, 0)),
                    'linked_tec_url' => (string) $read_meta($tec_url_key, ''),
                    'ticket_product_ids' => $ticket_product_ids,
                    'ticket_stats' => $ticket_stats,
                    'manual_ticket_product_ids' => $manual_ticket_product_ids,
                    'ticketing_v2_config' => $ticketing_v2_config,
                    'ticketing_v2_sync' => $ticketing_v2_sync,
                    'secondary_vendor_assignments' => $secondary_vendor_assignments,
                    'secondary_vendor_type' => $secondary_vendor_type,
                    'secondary_vendor_ids' => $secondary_vendor_ids,
                    'staff_assignments' => $staff_assignments,
                );
            },
            'event_plan_admin_boot_meta_bundle',
            array(
                'section' => 'admin_boot_meta_bundle',
            )
        );
    }

    private function get_event_plan_linked_tec_summary(int $plan_id): array
    {
        $plan_id = absint($plan_id);

        return (array) $this->get_event_plan_admin_boot_cached_value(
            $plan_id,
            'linked_tec_summary',
            function () use ($plan_id): array {
                $bundle = $this->get_event_plan_meta_bundle($plan_id);
                $linked_tec_id = absint($bundle['linked_tec_id'] ?? 0);
                $summary = array(
                    'linked_tec_id' => $linked_tec_id,
                    'linked_tec_url' => (string) ($bundle['linked_tec_url'] ?? ''),
                    'linked_tec_title' => '',
                    'linked_tec_status' => '',
                    'linked_tec_admin_url' => '',
                );

                if ($linked_tec_id > 0) {
                    $summary['linked_tec_title'] = (string) get_the_title($linked_tec_id);
                    $summary['linked_tec_status'] = sanitize_key((string) get_post_status($linked_tec_id));
                    $summary['linked_tec_admin_url'] = admin_url('post.php?post=' . $linked_tec_id . '&action=edit');
                }

                return $summary;
            },
            'event_plan_admin_boot_linked_tec',
            array(
                'section' => 'linked_tec_summary',
            )
        );
    }

    private function get_event_plan_ticketing_boot_summary(int $plan_id): array
    {
        $plan_id = absint($plan_id);

        return (array) $this->get_event_plan_admin_boot_cached_value(
            $plan_id,
            'ticketing_boot_summary',
            function () use ($plan_id): array {
                $bundle = $this->get_event_plan_meta_bundle($plan_id);
                $linked_tec = $this->get_event_plan_linked_tec_summary($plan_id);
                $ticketing_effective = function_exists('bvmgr_event_plan_is_ticketing_enabled')
                    ? (bool) bvmgr_event_plan_is_ticketing_enabled($plan_id)
                    : true;
                $cfg = isset($bundle['ticketing_v2_config']) && is_array($bundle['ticketing_v2_config']) ? $bundle['ticketing_v2_config'] : array();
                $sync = isset($bundle['ticketing_v2_sync']) && is_array($bundle['ticketing_v2_sync']) ? $bundle['ticketing_v2_sync'] : array();
                $enabled_ticket_count = 0;
                foreach ((array) ($cfg['tickets'] ?? array()) as $ticket_row) {
                    if (!is_array($ticket_row) || empty($ticket_row['enabled'])) {
                        continue;
                    }
                    if (trim((string) ($ticket_row['title'] ?? '')) === '') {
                        continue;
                    }
                    $enabled_ticket_count++;
                }
                $enabled_entitlement_count = 0;
                foreach ((array) ($cfg['entitlements'] ?? array()) as $entitlement_row) {
                    if (!is_array($entitlement_row) || empty($entitlement_row['enabled'])) {
                        continue;
                    }
                    if (trim((string) ($entitlement_row['label'] ?? '')) === '') {
                        continue;
                    }
                    $enabled_entitlement_count++;
                }
                $sync_entitlements = is_array($sync['map']['entitlements'] ?? null) ? (array) $sync['map']['entitlements'] : array();
                $mapped_entitlement_product_count = 0;
                foreach ($sync_entitlements as $sync_entitlement_row) {
                    if (!is_array($sync_entitlement_row)) {
                        continue;
                    }
                    if (absint($sync_entitlement_row['woo_product_id'] ?? 0) > 0) {
                        $mapped_entitlement_product_count++;
                    }
                }
                $ticket_mode = sanitize_key((string) ($cfg['mode'] ?? ''));
                if ($ticket_mode === '') {
                    $ticket_mode = 'read_only';
                }
                $linked_ticket_product_count = count((array) ($bundle['ticket_product_ids'] ?? array()));
                $effective_ticket_count = ($ticket_mode === 'vms_managed')
                    ? ($enabled_ticket_count + $enabled_entitlement_count)
                    : ($linked_ticket_product_count + $mapped_entitlement_product_count);

                return array(
                    'linked_tec_id' => absint($linked_tec['linked_tec_id'] ?? 0),
                    'linked_tec_title' => (string) ($linked_tec['linked_tec_title'] ?? ''),
                    'linked_tec_status' => (string) ($linked_tec['linked_tec_status'] ?? ''),
                    'ticketing_effective' => $ticketing_effective ? 1 : 0,
                    'ticket_mode' => $ticket_mode,
                    'saved_config_present' => !empty($cfg) ? 1 : 0,
                    'enabled_ticket_count' => $enabled_ticket_count,
                    'enabled_entitlement_count' => $enabled_entitlement_count,
                    'effective_ticket_count' => $effective_ticket_count,
                    'linked_ticket_product_count' => $linked_ticket_product_count,
                    'mapped_entitlement_product_count' => $mapped_entitlement_product_count,
                    'manual_ticket_product_count' => count((array) ($bundle['manual_ticket_product_ids'] ?? array())),
                    'ticket_stats_present' => !empty($bundle['ticket_stats']) ? 1 : 0,
                );
            },
            'event_plan_admin_boot_ticket_summary',
            array(
                'section' => 'ticketing_summary',
            )
        );
    }

    private function get_event_plan_add_on_boot_summary(int $plan_id): array
    {
        $plan_id = absint($plan_id);

        return (array) $this->get_event_plan_admin_boot_cached_value(
            $plan_id,
            'addon_boot_summary',
            function () use ($plan_id): array {
                $ticketing_summary = $this->get_event_plan_ticketing_boot_summary($plan_id);

                return array(
                    'enabled_add_on_count' => absint($ticketing_summary['enabled_entitlement_count'] ?? 0),
                    'mapped_add_on_product_count' => absint($ticketing_summary['mapped_entitlement_product_count'] ?? 0),
                );
            },
            'event_plan_admin_boot_add_on_summary',
            array(
                'section' => 'add_on_summary',
            )
        );
    }

    private function get_event_plan_integrity_boot_summary(int $plan_id): array
    {
        $plan_id = absint($plan_id);

        return (array) $this->get_event_plan_admin_boot_cached_value(
            $plan_id,
            'integrity_boot_summary',
            function () use ($plan_id): array {
                $bundle = $this->get_event_plan_meta_bundle($plan_id);
                $issue = sanitize_key((string) ($bundle['integrity_issue'] ?? ''));

                return array(
                    'integrity_issue' => $issue !== '' ? $issue : 'none',
                    'has_missing_vendor_issue' => in_array($issue, array('missing_vendor', 'missing_secondary_vendor', 'trashed_vendor', 'trashed_secondary_vendor'), true) ? 1 : 0,
                );
            },
            'event_plan_missing_vendor_integrity_boot',
            array(
                'section' => 'integrity_warning_state',
            )
        );
    }

    private function get_event_plan_vendor_boot_summary(int $plan_id, array $bands, string $event_date, int $venue_id_effective, array $args = array()): array
    {
        $plan_id = absint($plan_id);
        $event_date = trim((string) $event_date);
        $venue_id_effective = absint($venue_id_effective);
        $args = wp_parse_args($args, array(
            'include_primary_rows' => true,
            'include_supporting_rows' => true,
            'include_vendor_state_map' => false,
            'primary_scope' => 'all',
            'primary_vendor_id' => 0,
            'supporting_scope' => 'all',
            'supporting_vendor_ids' => array(),
        ));
        $include_primary_rows = !empty($args['include_primary_rows']);
        $include_supporting_rows = !empty($args['include_supporting_rows']);
        $include_vendor_state_map = !empty($args['include_vendor_state_map']);
        $primary_scope = sanitize_key((string) ($args['primary_scope'] ?? 'all'));
        if (!$include_primary_rows) {
            $primary_scope = 'none';
        } elseif (!in_array($primary_scope, array('all', 'selected_only'), true)) {
            $primary_scope = 'all';
        }
        $primary_vendor_id = absint($args['primary_vendor_id'] ?? 0);
        $supporting_scope = sanitize_key((string) ($args['supporting_scope'] ?? 'all'));
        if (!$include_supporting_rows) {
            $supporting_scope = 'none';
        } elseif (!in_array($supporting_scope, array('all', 'selected_only'), true)) {
            $supporting_scope = 'all';
        }
        $supporting_vendor_ids = array_values(array_unique(array_filter(array_map('absint', (array) ($args['supporting_vendor_ids'] ?? array())), static function ($vendor_id): bool {
            return $vendor_id > 0;
        })));
        $supporting_vendor_id_map = array_fill_keys($supporting_vendor_ids, true);
        $vendor_ids = array();
        foreach ($bands as $band) {
            if (is_object($band) && !empty($band->ID)) {
                $vendor_ids[] = absint($band->ID);
                continue;
            }
            if (is_numeric($band)) {
                $vendor_ids[] = absint($band);
            }
        }

        $cache_key = 'vendor_boot_summary:' . md5(wp_json_encode(array(
            'event_date' => $event_date,
            'venue_id_effective' => $venue_id_effective,
            'vendor_ids' => $vendor_ids,
            'include_primary_rows' => $include_primary_rows ? 1 : 0,
            'include_supporting_rows' => $include_supporting_rows ? 1 : 0,
            'include_vendor_state_map' => $include_vendor_state_map ? 1 : 0,
            'primary_scope' => $primary_scope,
            'primary_vendor_id' => $primary_vendor_id,
            'supporting_scope' => $supporting_scope,
            'supporting_vendor_ids' => $supporting_vendor_ids,
        )));

        return (array) $this->get_event_plan_admin_boot_cached_value(
            $plan_id,
            $cache_key,
            function () use ($plan_id, $bands, $event_date, $venue_id_effective, $include_primary_rows, $include_supporting_rows, $include_vendor_state_map, $primary_scope, $primary_vendor_id, $supporting_scope, $supporting_vendor_id_map): array {
                if (function_exists('bvmgr_event_plan_perf_memory_checkpoint')) {
                    bvmgr_event_plan_perf_memory_checkpoint($plan_id, 'vendor_summary_before', array(
                        'section' => 'vendor_summary_boot',
                        'vendor_count' => is_array($bands) ? count($bands) : 0,
                        'primary_scope' => $primary_scope,
                        'supporting_scope' => $supporting_scope,
                    ), 'vendor_summary');
                }

                $availability_trace = function_exists('bvmgr_event_plan_perf_span_start')
                    ? bvmgr_event_plan_perf_span_start('event_plan_vendor_availability_boot', $plan_id, array(
                        'section' => 'vendor_availability_boot',
                        'phase' => 'summary_only',
                        'vendor_count' => is_array($bands) ? count($bands) : 0,
                        'primary_scope' => $primary_scope,
                        'supporting_scope' => $supporting_scope,
                    ))
                    : '';

                $primary_rows = array();
                $supporting_rows = array();
                $vendor_state_map = array();
                $availability_hit_count = 0;
                $availability_miss_count = 0;
                $support_default_hit_count = 0;
                $support_default_miss_count = 0;
                $tax_summary_hit_count = 0;
                $tax_summary_miss_count = 0;

                if (function_exists('bvmgr_event_plan_perf_memory_checkpoint')) {
                    bvmgr_event_plan_perf_memory_checkpoint($plan_id, 'vendor_availability_before', array(
                        'section' => 'vendor_availability_boot',
                        'vendor_count' => is_array($bands) ? count($bands) : 0,
                    ), 'vendor_availability');
                }

                try {
                    foreach ($bands as $band) {
                        $vendor_id = 0;
                        $vendor_title = '';

                        if (is_object($band) && !empty($band->ID)) {
                            $vendor_id = absint($band->ID);
                            $vendor_title = (string) $band->post_title;
                        } elseif (is_numeric($band)) {
                            $vendor_id = absint($band);
                            $vendor_title = (string) get_the_title($vendor_id);
                        }

                        if ($vendor_id <= 0) {
                            continue;
                        }

                        $should_include_primary_row = $include_primary_rows && (
                            $primary_scope === 'all'
                            || ($primary_scope === 'selected_only' && $primary_vendor_id > 0 && $vendor_id === $primary_vendor_id)
                        );
                        $should_include_supporting_row = $include_supporting_rows && (
                            $supporting_scope === 'all'
                            || ($supporting_scope === 'selected_only' && isset($supporting_vendor_id_map[$vendor_id]))
                        );
                        $availability_cache_hit = false;
                        $availability_state = '';
                        if ($event_date !== '' && ($should_include_primary_row || $should_include_supporting_row || $include_vendor_state_map)) {
                            $availability_state = $this->get_event_plan_cached_vendor_availability_state($plan_id, $vendor_id, $event_date, $availability_cache_hit);
                            if ($availability_cache_hit) {
                                $availability_hit_count++;
                            } else {
                                $availability_miss_count++;
                            }
                        }

                        $availability_suffix = '';
                        if ($event_date !== '') {
                            if ($availability_state === 'available') {
                                $availability_suffix = ' [✓]';
                            } elseif ($availability_state === 'unavailable') {
                                $availability_suffix = ' [✖]';
                            } else {
                                $availability_suffix = ' [?]';
                            }
                        }

                        $support_default_fee = '';
                        if ($should_include_supporting_row) {
                            $support_default_cache_hit = false;
                            $support_defaults = $this->get_event_plan_cached_supporting_vendor_default($plan_id, $vendor_id, $venue_id_effective, $event_date, $support_default_cache_hit);
                            if ($support_default_cache_hit) {
                                $support_default_hit_count++;
                            } else {
                                $support_default_miss_count++;
                            }

                            if (array_key_exists('guaranteed_fee', $support_defaults) && $support_defaults['guaranteed_fee'] !== '' && $support_defaults['guaranteed_fee'] !== null && is_numeric($support_defaults['guaranteed_fee'])) {
                                $support_default_fee = number_format((float) $support_defaults['guaranteed_fee'], 2, '.', '');
                            }
                        }

                        $tax_missing = array();
                        $tax_ok = true;
                        $tax_bypass_active = false;
                        $tax_bypass_until = '';
                        $tax_bypass_reason = '';
                        if ($should_include_primary_row || $include_vendor_state_map) {
                            $tax_summary_cache_hit = false;
                            $tax_summary = $this->get_event_plan_cached_vendor_tax_summary($plan_id, $vendor_id, $tax_summary_cache_hit);
                            if ($tax_summary_cache_hit) {
                                $tax_summary_hit_count++;
                            } else {
                                $tax_summary_miss_count++;
                            }

                            $tax_missing = isset($tax_summary['missing']) && is_array($tax_summary['missing']) ? $tax_summary['missing'] : array();
                            $tax_ok = !empty($tax_summary['tax_ok']);
                            $tax_bypass_active = !empty($tax_summary['tax_bypass_active']);
                            $tax_bypass_until = (string) ($tax_summary['tax_bypass_until'] ?? '');
                            $tax_bypass_reason = (string) ($tax_summary['tax_bypass_reason'] ?? '');
                        }

                        if ($should_include_primary_row) {
                            $primary_label = $vendor_title . $availability_suffix . ($tax_ok ? ' [T✓]' : ' [T⚠]');
                            if ($tax_bypass_active) {
                                $primary_label .= ' [TB]';
                            }

                            $primary_rows[] = array(
                                'vendor_id' => $vendor_id,
                                'vendor_title' => $vendor_title,
                                'label' => $primary_label,
                                'tax_ok' => $tax_ok ? '1' : '0',
                                'tax_bypass_active' => $tax_bypass_active ? '1' : '0',
                                'tax_bypass_until' => $tax_bypass_until,
                                'tax_bypass_reason' => $tax_bypass_reason,
                                'tax_missing' => $tax_ok ? '' : implode(' | ', $tax_missing),
                            );
                        }

                        if ($should_include_supporting_row) {
                            $supporting_rows[] = array(
                                'vendor_id' => $vendor_id,
                                'vendor_title' => $vendor_title,
                                'label' => $vendor_title . $availability_suffix,
                                'default_fee' => $support_default_fee,
                            );
                        }

                        if ($include_vendor_state_map) {
                            $vendor_state_map[$vendor_id] = array(
                                'vendor_id' => $vendor_id,
                                'vendor_title' => $vendor_title,
                                'availability_state' => $availability_state,
                                'availability_suffix' => $availability_suffix,
                                'tax_ok' => $tax_ok,
                                'tax_missing' => $tax_missing,
                                'tax_bypass_active' => $tax_bypass_active,
                                'tax_bypass_until' => $tax_bypass_until,
                                'tax_bypass_reason' => $tax_bypass_reason,
                                'default_fee' => $support_default_fee,
                            );
                        }
                    }
                } finally {
                    if (function_exists('bvmgr_event_plan_perf_span_finish')) {
                        bvmgr_event_plan_perf_span_finish('event_plan_vendor_availability_boot', $plan_id, $availability_trace, array(
                            'section' => 'vendor_availability_boot',
                            'phase' => 'summary_only',
                            'primary_scope' => $primary_scope,
                            'supporting_scope' => $supporting_scope,
                            'vendor_count' => count($supporting_rows),
                            'availability_cache_hit_count' => $availability_hit_count,
                            'availability_cache_miss_count' => $availability_miss_count,
                            'support_default_cache_hit_count' => $support_default_hit_count,
                            'support_default_cache_miss_count' => $support_default_miss_count,
                            'tax_summary_cache_hit_count' => $tax_summary_hit_count,
                            'tax_summary_cache_miss_count' => $tax_summary_miss_count,
                        ));
                    }
                    if (function_exists('bvmgr_event_plan_perf_memory_checkpoint')) {
                        bvmgr_event_plan_perf_memory_checkpoint($plan_id, 'vendor_availability_after', array(
                            'section' => 'vendor_availability_boot',
                            'vendor_count' => count($supporting_rows),
                            'vendor_state_count' => count($vendor_state_map),
                        ), 'vendor_availability');
                    }
                }

                if (function_exists('bvmgr_event_plan_perf_log')) {
                    bvmgr_event_plan_perf_log('event_plan_vendor_conflict_boot', $plan_id, array(
                        'section' => 'vendor_conflict_boot',
                        'phase' => 'summary_only',
                        'reason' => 'initial_boot_summary',
                        'primary_scope' => $primary_scope,
                        'supporting_scope' => $supporting_scope,
                    ));
                }

                $summary = array(
                    'primary_rows' => $primary_rows,
                    'supporting_rows' => $supporting_rows,
                    'vendor_state_map' => $vendor_state_map,
                );

                if (function_exists('bvmgr_event_plan_perf_memory_checkpoint')) {
                    bvmgr_event_plan_perf_memory_checkpoint($plan_id, 'vendor_summary_after', array(
                        'section' => 'vendor_summary_boot',
                        'primary_row_count' => count($primary_rows),
                        'supporting_row_count' => count($supporting_rows),
                        'vendor_state_count' => count($vendor_state_map),
                        'payload_size_bytes' => strlen(maybe_serialize($summary)),
                        'primary_scope' => $primary_scope,
                        'supporting_scope' => $supporting_scope,
                    ), 'vendor_summary');
                }

                return $summary;
            },
            'event_plan_vendor_summary_boot',
            array(
                'section' => 'vendor_summary_boot',
                'phase' => 'run',
                'vendor_count' => count($vendor_ids),
                'primary_scope' => $primary_scope,
                'supporting_scope' => $supporting_scope,
            )
        );
    }

    private function get_event_plan_secondary_vendor_boot_summary(int $plan_id, array $bands, string $event_date, array $secondary_vendor_assignments, bool $full_details = true): array
    {
        $plan_id = absint($plan_id);
        $event_date = trim((string) $event_date);
        $primary_vendor_id = (int) get_post_meta($plan_id, '_vms_band_vendor_id', true);
        $secondary_vendor_assignments = function_exists('bvmgr_event_plan_normalize_secondary_vendor_assignment_map')
            ? bvmgr_event_plan_normalize_secondary_vendor_assignment_map($plan_id, $secondary_vendor_assignments, $primary_vendor_id, array(
                'preserve_empty' => true,
            ))
            : (array) $secondary_vendor_assignments;
        $secondary_vendor_ids = function_exists('bvmgr_event_plan_get_secondary_vendor_flat_ids_from_assignments')
            ? bvmgr_event_plan_get_secondary_vendor_flat_ids_from_assignments($secondary_vendor_assignments, $primary_vendor_id)
            : array();
        $assignment_signature = array();
        foreach ($secondary_vendor_assignments as $type_slug => $assignment) {
            $assignment_signature[] = $type_slug . ':' . wp_json_encode($assignment);
        }

        $cache_key = 'secondary_vendor_boot_summary:' . md5(($full_details ? 'full' : 'summary') . '|' . $event_date . '|' . implode('|', $assignment_signature));

        return (array) $this->get_event_plan_admin_boot_cached_value(
            $plan_id,
            $cache_key,
            function () use ($plan_id, $bands, $event_date, $secondary_vendor_assignments, $secondary_vendor_ids, $full_details): array {
                if (function_exists('bvmgr_event_plan_perf_memory_checkpoint')) {
                    bvmgr_event_plan_perf_memory_checkpoint($plan_id, 'secondary_vendor_summary_before', array(
                        'section' => 'secondary_vendor_summary_boot',
                        'detail_mode' => $full_details ? 'full' : 'summary_only',
                        'secondary_vendor_group_count' => count($secondary_vendor_assignments),
                        'secondary_vendor_selected_count' => count($secondary_vendor_ids),
                    ), 'secondary_vendor_summary');
                }

                $secondary_type_options = function_exists('bvmgr_event_plan_additional_vendor_type_options')
                    ? (array) bvmgr_event_plan_additional_vendor_type_options()
                    : array();
                foreach (array_keys($secondary_vendor_assignments) as $current_type_slug) {
                    $current_type_slug = sanitize_key((string) $current_type_slug);
                    if ($current_type_slug === '' || isset($secondary_type_options[$current_type_slug])) {
                        continue;
                    }

                    $secondary_type_options[$current_type_slug] = function_exists('bvmgr_vendor_type_label')
                        ? (string) bvmgr_vendor_type_label($current_type_slug)
                        : ucwords(str_replace(array('_', '-'), ' ', $current_type_slug));
                }

                $secondary_types = array();
                foreach ($secondary_type_options as $type_slug => $type_label) {
                    $secondary_types[] = (object) array(
                        'slug' => (string) $type_slug,
                        'name' => (string) $type_label,
                    );
                }

                $vendor_index = array();
                foreach ($bands as $band) {
                    if (is_object($band) && !empty($band->ID)) {
                        $vendor_index[absint($band->ID)] = $band;
                    }
                }

                $candidate_ids = $full_details ? array_keys($vendor_index) : $secondary_vendor_ids;
                $candidate_ids = array_values(array_unique(array_filter(array_map('absint', $candidate_ids), static function ($vendor_id): bool {
                    return $vendor_id > 0;
                })));
                $vendor_type_map_cache_hit = false;
                $vendor_type_map = $this->get_event_plan_cached_vendor_type_slug_map($plan_id, $candidate_ids, $vendor_type_map_cache_hit);
                $availability_hit_count = 0;
                $availability_miss_count = 0;
                $qualification_hit_count = 0;
                $qualification_miss_count = 0;
                $type_pool_map = array();
                $assignment_groups = array();
                $secondary_missing = array();
                $secondary_mismatch = array();
                $secondary_unqualified = array();

                if (function_exists('bvmgr_event_plan_perf_memory_checkpoint')) {
                    bvmgr_event_plan_perf_memory_checkpoint($plan_id, 'secondary_vendor_availability_before', array(
                        'section' => 'secondary_vendor_summary_boot',
                        'detail_mode' => $full_details ? 'full' : 'summary_only',
                        'secondary_vendor_group_count' => count($secondary_vendor_assignments),
                        'secondary_vendor_selected_count' => count($secondary_vendor_ids),
                    ), 'secondary_vendor_availability');
                }

                if ($full_details) {
                    foreach ($secondary_type_options as $type_slug => $type_label) {
                        $pool_option_rows = array();
                        foreach ($bands as $band) {
                            if (!is_object($band) || empty($band->ID)) {
                                continue;
                            }

                            $vendor_id = absint($band->ID);
                            if ($vendor_id <= 0) {
                                continue;
                            }

                            $vendor_type_slugs = isset($vendor_type_map[$vendor_id]) && is_array($vendor_type_map[$vendor_id])
                                ? $vendor_type_map[$vendor_id]
                                : array();
                            if (!in_array($type_slug, $vendor_type_slugs, true)) {
                                continue;
                            }

                            $availability_cache_hit = false;
                            $availability_state = $this->get_event_plan_cached_vendor_availability_state($plan_id, $vendor_id, $event_date, $availability_cache_hit);
                            if ($availability_cache_hit) {
                                $availability_hit_count++;
                            } else {
                                $availability_miss_count++;
                            }

                            $availability_suffix = '';
                            if ($event_date !== '') {
                                if ($availability_state === 'available') {
                                    $availability_suffix = ' [✓]';
                                } elseif ($availability_state === 'unavailable') {
                                    $availability_suffix = ' [✖]';
                                } else {
                                    $availability_suffix = ' [?]';
                                }
                            }

                            $qualification_cache_hit = false;
                            $missing = $this->get_event_plan_cached_secondary_vendor_missing_items($plan_id, $vendor_id, $type_slug, $qualification_cache_hit);
                            if ($qualification_cache_hit) {
                                $qualification_hit_count++;
                            } else {
                                $qualification_miss_count++;
                            }

                            $pool_option_rows[] = array(
                                'vendor_id' => $vendor_id,
                                'vendor_title' => (string) $band->post_title,
                                'label' => (string) $band->post_title . $availability_suffix . (empty($missing) ? ' [Q✓]' : ' [Q⚠]'),
                                'availability_state' => $availability_state,
                                'qualified' => empty($missing),
                                'missing' => $missing,
                            );
                        }

                        $type_pool_map[$type_slug] = $pool_option_rows;
                    }
                }

                foreach ($secondary_vendor_assignments as $type_slug => $assignment) {
                    $type_slug = sanitize_key((string) $type_slug);
                    if ($type_slug === '') {
                        continue;
                    }

                    $type_name = trim((string) ($secondary_type_options[$type_slug] ?? ''));
                    if ($type_name === '') {
                        $type_name = function_exists('bvmgr_vendor_type_label')
                            ? (string) bvmgr_vendor_type_label($type_slug)
                            : ucwords(str_replace(array('_', '-'), ' ', $type_slug));
                    }

                    $group_vendor_ids = array_values(array_unique(array_filter(array_map('absint', (array) ($assignment['vendor_ids'] ?? array())))));
                    $group_missing = array();
                    $group_mismatch = array();
                    $group_unqualified = array();
                    $selected_vendor_titles = array();
                    $selected_missing_map = array();

                    foreach ($group_vendor_ids as $vendor_id) {
                        $vendor_post = $vendor_index[$vendor_id] ?? get_post($vendor_id);
                        if (!$vendor_post || $vendor_post->post_type !== 'vms_vendor' || $vendor_post->post_status === 'trash') {
                            $group_missing[] = $vendor_id;
                            $secondary_missing[] = $vendor_id;
                            continue;
                        }

                        $selected_vendor_titles[$vendor_id] = trim((string) $vendor_post->post_title);

                        $matches_type = false;
                        if (isset($vendor_type_map[$vendor_id]) && is_array($vendor_type_map[$vendor_id])) {
                            $matches_type = in_array($type_slug, $vendor_type_map[$vendor_id], true);
                        } else {
                            $type_match_cache_hit = false;
                            $matches_type = $this->get_event_plan_cached_vendor_type_match($plan_id, $vendor_id, $type_slug, $type_match_cache_hit);
                        }
                        if (!$matches_type) {
                            $group_mismatch[] = $vendor_id;
                            $secondary_mismatch[] = $vendor_id;
                        }

                        $qualification_cache_hit = false;
                        $missing = $this->get_event_plan_cached_secondary_vendor_missing_items($plan_id, $vendor_id, $type_slug, $qualification_cache_hit);
                        if ($qualification_cache_hit) {
                            $qualification_hit_count++;
                        } else {
                            $qualification_miss_count++;
                        }

                        if (!empty($missing)) {
                            $group_unqualified[] = $vendor_id;
                            $secondary_unqualified[] = $vendor_id;
                            $selected_missing_map[$vendor_id] = $missing;
                        }
                    }

                    $slot_limit = array_key_exists('slot_limit', $assignment) ? $assignment['slot_limit'] : null;
                    $selected_count = count($group_vendor_ids);
                    $slot_limit_value = ($slot_limit === '' || $slot_limit === null) ? null : max(0, (int) $slot_limit);
                    $needed_slots = array_key_exists('needed_slots', $assignment) && $assignment['needed_slots'] !== '' && $assignment['needed_slots'] !== null
                        ? max(0, (int) $assignment['needed_slots'])
                        : null;
                    $open_for_dispatch = function_exists('bvmgr_event_plan_parse_secondary_vendor_over_capacity_override')
                        ? bvmgr_event_plan_parse_secondary_vendor_over_capacity_override($assignment['open_for_dispatch'] ?? true)
                        : !array_key_exists('open_for_dispatch', $assignment) || !empty($assignment['open_for_dispatch']);
                    $open_slots = $slot_limit_value === null ? null : max(0, $slot_limit_value - $selected_count);
                    $occupancy_label = $slot_limit_value === null
                        /* translators: %d: number of selected items. */
                        ? sprintf(_n('%d selected', '%d selected', $selected_count, 'backstage-venue-manager'), $selected_count)
                        /* translators: 1: number 1 used in this message, 2: number 2 used in this message. */
                        : sprintf(__('%1$d of %2$d filled', 'backstage-venue-manager'), $selected_count, $slot_limit_value);

                    $assignment_groups[] = array(
                        'type_slug' => $type_slug,
                        'type_name' => $type_name,
                        'mode' => sanitize_key((string) ($assignment['mode'] ?? 'standard')),
                        'mode_label' => (string) (bvmgr_event_plan_secondary_vendor_mode_options()[sanitize_key((string) ($assignment['mode'] ?? 'standard'))] ?? __('Standard', 'backstage-venue-manager')),
                        'slot_limit' => $slot_limit_value,
                        'slot_limit_display' => $slot_limit_value === null ? '' : (string) $slot_limit_value,
                        'needed_slots' => $needed_slots,
                        'needed_slots_display' => $needed_slots === null ? '' : (string) $needed_slots,
                        'open_for_dispatch' => $open_for_dispatch,
                        'allow_over_capacity' => !empty($assignment['allow_over_capacity']),
                        'vendor_ids' => $group_vendor_ids,
                        'selected_count' => $selected_count,
                        'filled_slots' => $selected_count,
                        'open_slots' => $open_slots,
                        'occupancy_label' => $occupancy_label,
                        'pool_option_rows' => (array) ($type_pool_map[$type_slug] ?? array()),
                        'secondary_missing' => $group_missing,
                        'secondary_mismatch' => $group_mismatch,
                        'secondary_unqualified' => $group_unqualified,
                        'selected_vendor_titles' => $selected_vendor_titles,
                        'selected_missing_map' => $selected_missing_map,
                    );
                }

                $secondary_missing = array_values(array_unique(array_filter(array_map('absint', $secondary_missing))));
                $secondary_mismatch = array_values(array_unique(array_filter(array_map('absint', $secondary_mismatch))));
                $secondary_unqualified = array_values(array_unique(array_filter(array_map('absint', $secondary_unqualified))));
                $all_selected_vendor_titles = array();
                $all_selected_missing_map = array();
                foreach ($assignment_groups as $group) {
                    foreach ((array) ($group['selected_vendor_titles'] ?? array()) as $vendor_id => $vendor_title) {
                        $all_selected_vendor_titles[(int) $vendor_id] = (string) $vendor_title;
                    }
                    foreach ((array) ($group['selected_missing_map'] ?? array()) as $vendor_id => $missing_items) {
                        $all_selected_missing_map[(int) $vendor_id] = is_array($missing_items) ? $missing_items : array();
                    }
                }

                $pool_trace_data = array(
                    'section' => 'secondary_vendor_summary_boot',
                    'phase' => $full_details ? 'run' : 'summary_only',
                    'secondary_vendor_type' => count($assignment_groups) === 1 ? (string) ($assignment_groups[0]['type_slug'] ?? 'none') : 'multi',
                    'secondary_vendor_group_count' => count($assignment_groups),
                    'secondary_vendor_pool_count' => array_sum(array_map('count', (array) $type_pool_map)),
                    'secondary_vendor_selected_count' => count($secondary_vendor_ids),
                    'secondary_vendor_missing_count' => count($secondary_missing),
                    'secondary_vendor_mismatch_count' => count($secondary_mismatch),
                    'secondary_vendor_unqualified_count' => count($secondary_unqualified),
                    'type_map_cache' => $vendor_type_map_cache_hit ? 'hit' : 'miss',
                    'availability_cache_hit_count' => $availability_hit_count,
                    'availability_cache_miss_count' => $availability_miss_count,
                    'qualification_cache_hit_count' => $qualification_hit_count,
                    'qualification_cache_miss_count' => $qualification_miss_count,
                );

                if (function_exists('bvmgr_event_plan_perf_log')) {
                    bvmgr_event_plan_perf_log('event_plan_vendor_availability_boot', $plan_id, $pool_trace_data);
                    bvmgr_event_plan_perf_log('event_plan_vendor_conflict_boot', $plan_id, array(
                        'section' => 'secondary_vendor_summary_boot',
                        'phase' => $full_details ? 'run' : 'summary_only',
                        'reason' => $full_details ? 'secondary_vendor_editor_load' : 'initial_boot_summary',
                    ));
                }

                if (function_exists('bvmgr_event_plan_perf_memory_checkpoint')) {
                    bvmgr_event_plan_perf_memory_checkpoint($plan_id, 'secondary_vendor_availability_after', array(
                        'section' => 'secondary_vendor_summary_boot',
                        'detail_mode' => $full_details ? 'full' : 'summary_only',
                        'secondary_vendor_pool_count' => array_sum(array_map('count', (array) $type_pool_map)),
                        'secondary_vendor_warning_count' => count($secondary_missing) + count($secondary_mismatch) + count($secondary_unqualified),
                    ), 'secondary_vendor_availability');
                }

                $summary = array(
                    'secondary_types' => $secondary_types,
                    'secondary_type_options' => $secondary_type_options,
                    'secondary_mode_options' => function_exists('bvmgr_event_plan_secondary_vendor_mode_options')
                        ? (array) bvmgr_event_plan_secondary_vendor_mode_options()
                        : array(),
                    'detail_mode' => $full_details ? 'full' : 'summary_only',
                    'assignment_groups' => $assignment_groups,
                    'type_pool_map' => $type_pool_map,
                    'pool_option_rows' => count($assignment_groups) === 1 ? (array) ($assignment_groups[0]['pool_option_rows'] ?? array()) : array(),
                    'secondary_type_name' => count($assignment_groups) === 1 ? (string) ($assignment_groups[0]['type_name'] ?? '') : '',
                    'secondary_missing' => $secondary_missing,
                    'secondary_mismatch' => $secondary_mismatch,
                    'secondary_unqualified' => $secondary_unqualified,
                    'selected_vendor_titles' => $all_selected_vendor_titles,
                    'selected_missing_map' => $all_selected_missing_map,
                );

                if (function_exists('bvmgr_event_plan_perf_memory_checkpoint')) {
                    bvmgr_event_plan_perf_memory_checkpoint($plan_id, 'secondary_vendor_summary_after', array(
                        'section' => 'secondary_vendor_summary_boot',
                        'detail_mode' => $full_details ? 'full' : 'summary_only',
                        'secondary_vendor_pool_count' => array_sum(array_map('count', (array) $type_pool_map)),
                        'secondary_vendor_warning_count' => count($secondary_missing) + count($secondary_mismatch) + count($secondary_unqualified),
                        'payload_size_bytes' => strlen(maybe_serialize($summary)),
                    ), 'secondary_vendor_summary');
                }

                return $summary;
            },
            'event_plan_secondary_vendor_summary_boot',
            array(
                'section' => 'secondary_vendor_summary_boot',
                'phase' => 'run',
                'detail_mode' => $full_details ? 'full' : 'summary_only',
                'secondary_vendor_type' => count($secondary_vendor_assignments) === 1 ? (string) array_key_first($secondary_vendor_assignments) : 'multi',
                'secondary_vendor_selected_count' => count($secondary_vendor_ids),
            )
        );
    }

    private function get_event_plan_readiness_boot_summary(int $plan_id, array $vendor_boot_summary = array(), array $secondary_vendor_boot_summary = array()): array
    {
        $plan_id = absint($plan_id);

        return (array) $this->get_event_plan_admin_boot_cached_value(
            $plan_id,
            'readiness_boot_summary',
            function () use ($plan_id, $vendor_boot_summary, $secondary_vendor_boot_summary): array {
                if (function_exists('bvmgr_event_plan_perf_memory_checkpoint')) {
                    bvmgr_event_plan_perf_memory_checkpoint($plan_id, 'readiness_before', array(
                        'section' => 'readiness_status_boot',
                    ), 'readiness');
                }

                $bundle = $this->get_event_plan_meta_bundle($plan_id);
                $linked_tec_summary = $this->get_event_plan_linked_tec_summary($plan_id);
                $ticketing_summary = $this->get_event_plan_ticketing_boot_summary($plan_id);
                $add_on_summary = $this->get_event_plan_add_on_boot_summary($plan_id);
                $integrity_summary = $this->get_event_plan_integrity_boot_summary($plan_id);
                $primary_rows = isset($vendor_boot_summary['primary_rows']) && is_array($vendor_boot_summary['primary_rows']) ? $vendor_boot_summary['primary_rows'] : array();
                $secondary_missing = isset($secondary_vendor_boot_summary['secondary_missing']) && is_array($secondary_vendor_boot_summary['secondary_missing']) ? $secondary_vendor_boot_summary['secondary_missing'] : array();
                $secondary_mismatch = isset($secondary_vendor_boot_summary['secondary_mismatch']) && is_array($secondary_vendor_boot_summary['secondary_mismatch']) ? $secondary_vendor_boot_summary['secondary_mismatch'] : array();
                $secondary_unqualified = isset($secondary_vendor_boot_summary['secondary_unqualified']) && is_array($secondary_vendor_boot_summary['secondary_unqualified']) ? $secondary_vendor_boot_summary['secondary_unqualified'] : array();

                $summary = array(
                    'linked_tec_present' => !empty($linked_tec_summary['linked_tec_id']) ? 1 : 0,
                    'linked_tec_status' => sanitize_key((string) ($linked_tec_summary['linked_tec_status'] ?? '')),
                    'ticketing_effective' => !empty($ticketing_summary['ticketing_effective']) ? 1 : 0,
                    'effective_ticket_count' => absint($ticketing_summary['effective_ticket_count'] ?? 0),
                    'enabled_add_on_count' => absint($add_on_summary['enabled_add_on_count'] ?? 0),
                    'primary_vendor_option_count' => count($primary_rows),
                    'primary_vendor_assigned' => absint($bundle['band_vendor_id'] ?? 0) > 0 ? 1 : 0,
                    'secondary_vendor_count' => count((array) ($bundle['secondary_vendor_ids'] ?? array())),
                    'secondary_vendor_warning_count' => count($secondary_missing) + count($secondary_mismatch) + count($secondary_unqualified),
                    'publish_blocking_warning' => !empty($integrity_summary['has_missing_vendor_issue']) ? 1 : 0,
                    'blocking_issue_count' => !empty($integrity_summary['has_missing_vendor_issue']) ? 1 : 0,
                    'integrity_issue' => sanitize_key((string) ($integrity_summary['integrity_issue'] ?? 'none')),
                );

                if (function_exists('bvmgr_event_plan_perf_memory_checkpoint')) {
                    bvmgr_event_plan_perf_memory_checkpoint($plan_id, 'readiness_after', array(
                        'section' => 'readiness_status_boot',
                        'publish_blocking_warning' => absint($summary['publish_blocking_warning'] ?? 0),
                        'secondary_vendor_warning_count' => absint($summary['secondary_vendor_warning_count'] ?? 0),
                        'payload_size_bytes' => strlen(maybe_serialize($summary)),
                    ), 'readiness');
                }

                return $summary;
            },
            'event_plan_readiness_boot',
            array(
                'section' => 'readiness_status_boot',
                'detail_mode' => 'summary_only',
                'phase' => 'run',
            )
        );
    }

    private function get_event_plan_integrity_issue_label(string $issue): string
    {
        $issue = sanitize_key($issue);
        $labels = array(
            'missing_vendor' => __('Primary vendor missing', 'backstage-venue-manager'),
            'missing_secondary_vendor' => __('Secondary vendor missing', 'backstage-venue-manager'),
            'trashed_vendor' => __('Primary vendor trashed', 'backstage-venue-manager'),
            'trashed_secondary_vendor' => __('Secondary vendor trashed', 'backstage-venue-manager'),
            'calendar_event_unpublished' => __('Linked calendar event unpublished', 'backstage-venue-manager'),
            'missing_calendar_event' => __('Linked calendar event missing', 'backstage-venue-manager'),
            'orphaned_calendar_event' => __('Linked calendar event orphaned', 'backstage-venue-manager'),
            'missing_venue' => __('Venue missing', 'backstage-venue-manager'),
            'trashed_venue' => __('Venue trashed', 'backstage-venue-manager'),
        );

        if (isset($labels[$issue]) && $labels[$issue] !== '') {
            return (string) $labels[$issue];
        }

        if ($issue === '' || $issue === 'none') {
            return __('No integrity issue', 'backstage-venue-manager');
        }

        return ucwords(str_replace(array('-', '_'), ' ', $issue));
    }

    private function build_event_plan_readiness_summary_context(int $plan_id, array $readiness_boot_summary, array $secondary_vendor_boot_summary = array(), array $linked_tec_summary = array(), array $ticketing_summary = array(), array $add_on_summary = array(), array $integrity_summary = array()): array
    {
        $plan_id = absint($plan_id);
        if ($plan_id <= 0) {
            return array();
        }

        if (empty($linked_tec_summary) || !is_array($linked_tec_summary)) {
            $linked_tec_summary = $this->get_event_plan_linked_tec_summary($plan_id);
        }
        if (empty($ticketing_summary) || !is_array($ticketing_summary)) {
            $ticketing_summary = $this->get_event_plan_ticketing_boot_summary($plan_id);
        }
        if (empty($add_on_summary) || !is_array($add_on_summary)) {
            $add_on_summary = $this->get_event_plan_add_on_boot_summary($plan_id);
        }
        if (empty($integrity_summary) || !is_array($integrity_summary)) {
            $integrity_summary = $this->get_event_plan_integrity_boot_summary($plan_id);
        }

        $secondary_missing = isset($secondary_vendor_boot_summary['secondary_missing']) && is_array($secondary_vendor_boot_summary['secondary_missing'])
            ? $secondary_vendor_boot_summary['secondary_missing']
            : array();
        $secondary_mismatch = isset($secondary_vendor_boot_summary['secondary_mismatch']) && is_array($secondary_vendor_boot_summary['secondary_mismatch'])
            ? $secondary_vendor_boot_summary['secondary_mismatch']
            : array();
        $secondary_unqualified = isset($secondary_vendor_boot_summary['secondary_unqualified']) && is_array($secondary_vendor_boot_summary['secondary_unqualified'])
            ? $secondary_vendor_boot_summary['secondary_unqualified']
            : array();

        $warning_items = array();
        $integrity_issue = sanitize_key((string) ($integrity_summary['integrity_issue'] ?? 'none'));
        if (!empty($integrity_summary['has_missing_vendor_issue'])) {
            $warning_items[] = $this->get_event_plan_integrity_issue_label($integrity_issue);
        }
        if (!empty($secondary_missing)) {
            /* translators: %d: number of items described in this message. */
            $warning_items[] = sprintf(_n('%d selected secondary vendor is missing or trashed.', '%d selected secondary vendors are missing or trashed.', count($secondary_missing), 'backstage-venue-manager'), count($secondary_missing));
        }
        if (!empty($secondary_mismatch)) {
            /* translators: %d: number of items described in this message. */
            $warning_items[] = sprintf(_n('%d selected secondary vendor no longer matches the chosen vendor type.', '%d selected secondary vendors no longer match the chosen vendor type.', count($secondary_mismatch), 'backstage-venue-manager'), count($secondary_mismatch));
        }
        if (!empty($secondary_unqualified)) {
            /* translators: %d: number of items described in this message. */
            $warning_items[] = sprintf(_n('%d selected secondary vendor needs profile qualification fixes.', '%d selected secondary vendors need profile qualification fixes.', count($secondary_unqualified), 'backstage-venue-manager'), count($secondary_unqualified));
        }

        $linked_tec_label = !empty($linked_tec_summary['linked_tec_id'])
            ? sprintf(
                /* translators: %1$s: human-readable linked TEC status label. */
                __('Linked (%1$s)', 'backstage-venue-manager'),
                $this->get_event_plan_integrity_issue_label((string) ($linked_tec_summary['linked_tec_status'] ?? 'draft'))
            )
            : __('Not linked', 'backstage-venue-manager');

        $summary_rows = array(
            array(
                'label' => __('Publish-blocking warnings', 'backstage-venue-manager'),
                'value' => absint($readiness_boot_summary['blocking_issue_count'] ?? 0),
                'state' => !empty($readiness_boot_summary['publish_blocking_warning']) ? 'warning' : 'ok',
            ),
            array(
                'label' => __('Vendor warnings', 'backstage-venue-manager'),
                'value' => absint($readiness_boot_summary['secondary_vendor_warning_count'] ?? 0),
                'state' => !empty($readiness_boot_summary['secondary_vendor_warning_count']) ? 'warning' : 'ok',
            ),
            array(
                'label' => __('Linked TEC status', 'backstage-venue-manager'),
                'value' => !empty($linked_tec_summary['linked_tec_id'])
                    /* translators: %s: uppercase linked TEC status value. */
                    ? sprintf(__('Linked (%s)', 'backstage-venue-manager'), strtoupper((string) ($linked_tec_summary['linked_tec_status'] ?? 'draft')))
                    : __('Not linked', 'backstage-venue-manager'),
                'state' => !empty($linked_tec_summary['linked_tec_id']) ? 'ok' : 'info',
            ),
            array(
                'label' => __('Configured tickets', 'backstage-venue-manager'),
                'value' => absint($ticketing_summary['effective_ticket_count'] ?? 0),
                'state' => !empty($ticketing_summary['effective_ticket_count']) ? 'ok' : 'info',
            ),
        );

        return array(
            'summary_rows' => $summary_rows,
            'warning_items' => $warning_items,
            'status_label' => !empty($readiness_boot_summary['publish_blocking_warning']) ? __('Blocking warnings present', 'backstage-venue-manager') : __('No blocking publish warnings', 'backstage-venue-manager'),
            'secondary_vendor_type_name' => (string) ($secondary_vendor_boot_summary['secondary_type_name'] ?? ''),
            'integrity_issue_label' => $this->get_event_plan_integrity_issue_label($integrity_issue),
            'linked_tec_status_label' => $linked_tec_label,
            'payload_size_bytes' => strlen(maybe_serialize(array(
                'summary_rows' => $summary_rows,
                'warning_items' => $warning_items,
            ))),
        );
    }

    private function get_event_plan_readiness_detail_context(int $plan_id, array $readiness_boot_summary = array(), array $secondary_vendor_boot_summary = array()): array
    {
        $plan_id = absint($plan_id);
        if ($plan_id <= 0) {
            return array();
        }

        $bundle = $this->get_event_plan_meta_bundle($plan_id);
        if (empty($secondary_vendor_boot_summary) || !is_array($secondary_vendor_boot_summary)) {
            $secondary_vendor_boot_summary = $this->get_event_plan_secondary_vendor_boot_summary(
                $plan_id,
                array(),
                (string) ($bundle['event_date'] ?? ''),
                is_array($bundle['secondary_vendor_assignments'] ?? null) ? (array) $bundle['secondary_vendor_assignments'] : array(),
                false
            );
        }

        if (empty($readiness_boot_summary) || !is_array($readiness_boot_summary)) {
            $readiness_boot_summary = $this->get_event_plan_readiness_boot_summary(
                $plan_id,
                array(),
                $secondary_vendor_boot_summary
            );
        }

        $linked_tec_summary = $this->get_event_plan_linked_tec_summary($plan_id);
        $ticketing_summary = $this->get_event_plan_ticketing_boot_summary($plan_id);
        $add_on_summary = $this->get_event_plan_add_on_boot_summary($plan_id);
        $integrity_summary = $this->get_event_plan_integrity_boot_summary($plan_id);
        $summary_context = $this->build_event_plan_readiness_summary_context(
            $plan_id,
            $readiness_boot_summary,
            $secondary_vendor_boot_summary,
            $linked_tec_summary,
            $ticketing_summary,
            $add_on_summary,
            $integrity_summary
        );

        return array(
            'readiness_boot_summary' => $readiness_boot_summary,
            'secondary_vendor_boot_summary' => $secondary_vendor_boot_summary,
            'linked_tec_summary' => $linked_tec_summary,
            'ticketing_summary' => $ticketing_summary,
            'add_on_summary' => $add_on_summary,
            'integrity_summary' => $integrity_summary,
            'summary_rows' => isset($summary_context['summary_rows']) && is_array($summary_context['summary_rows']) ? $summary_context['summary_rows'] : array(),
            'warning_items' => isset($summary_context['warning_items']) && is_array($summary_context['warning_items']) ? $summary_context['warning_items'] : array(),
            'status_label' => (string) ($summary_context['status_label'] ?? __('No blocking publish warnings', 'backstage-venue-manager')),
            'secondary_vendor_type_name' => (string) ($summary_context['secondary_vendor_type_name'] ?? ''),
            'integrity_issue_label' => (string) ($summary_context['integrity_issue_label'] ?? ''),
            'linked_tec_status_label' => (string) ($summary_context['linked_tec_status_label'] ?? ''),
            'payload_size_bytes' => absint($summary_context['payload_size_bytes'] ?? 0),
        );
    }

    private function build_event_plan_readiness_details_response_payload(int $post_id): array
    {
        $detail_context = $this->get_event_plan_readiness_detail_context($post_id);

        return array(
            'detail_context' => $detail_context,
            'html' => $this->render_event_plan_readiness_details_response_html($detail_context),
        );
    }

    private function render_event_plan_readiness_details_response_html(array $detail_context): string
    {
        $summary_rows = isset($detail_context['summary_rows']) && is_array($detail_context['summary_rows'])
            ? $detail_context['summary_rows']
            : array();
        $warning_items = isset($detail_context['warning_items']) && is_array($detail_context['warning_items'])
            ? $detail_context['warning_items']
            : array();
        $linked_tec_summary = isset($detail_context['linked_tec_summary']) && is_array($detail_context['linked_tec_summary'])
            ? $detail_context['linked_tec_summary']
            : array();
        $ticketing_summary = isset($detail_context['ticketing_summary']) && is_array($detail_context['ticketing_summary'])
            ? $detail_context['ticketing_summary']
            : array();
        $secondary_vendor_boot_summary = isset($detail_context['secondary_vendor_boot_summary']) && is_array($detail_context['secondary_vendor_boot_summary'])
            ? $detail_context['secondary_vendor_boot_summary']
            : array();
        $readiness_boot_summary = isset($detail_context['readiness_boot_summary']) && is_array($detail_context['readiness_boot_summary'])
            ? $detail_context['readiness_boot_summary']
            : array();
        $add_on_summary = isset($detail_context['add_on_summary']) && is_array($detail_context['add_on_summary'])
            ? $detail_context['add_on_summary']
            : array();
        $html = '<div class="vms-ep-card vms-ep-card--white vms-ep-card--readiness-details">';
        $html .= '<p class="description">' . esc_html((string) ($detail_context['status_label'] ?? __('No blocking publish warnings', 'backstage-venue-manager'))) . '</p>';
        $html .= $this->render_event_plan_readiness_details_summary_rows_html($summary_rows);
        $html .= $this->render_event_plan_readiness_details_warning_notice_html($warning_items);
        $html .= '<p class="description">' . $this->render_event_plan_readiness_details_linked_tec_text($linked_tec_summary) . '</p>';
        $html .= '<p class="description">' . $this->render_event_plan_readiness_details_ticketing_text($ticketing_summary, $add_on_summary) . '</p>';
        $html .= '<p class="description">' . $this->render_event_plan_readiness_details_secondary_vendor_text($secondary_vendor_boot_summary, $readiness_boot_summary) . '</p>';
        $html .= '</div>';

        return $html;
    }

    private function render_event_plan_readiness_details_summary_rows_html(array $summary_rows): string
    {
        if (empty($summary_rows)) {
            return '';
        }

        $html = '<ul class="vms-ep-inline-list">';
        foreach ($summary_rows as $summary_row) {
            if (!is_array($summary_row)) {
                continue;
            }

            $html .= '<li><strong>'
                . esc_html((string) ($summary_row['label'] ?? ''))
                . ':</strong> '
                . esc_html((string) ($summary_row['value'] ?? ''))
                . '</li>';
        }
        $html .= '</ul>';

        return $html;
    }

    private function render_event_plan_readiness_details_warning_notice_html(array $warning_items): string
    {
        $warning_texts = array();
        foreach ($warning_items as $warning_item) {
            if (!is_scalar($warning_item) && null !== $warning_item) {
                continue;
            }

            $warning_text = trim((string) $warning_item);
            if ($warning_text === '') {
                continue;
            }

            $warning_texts[] = $warning_text;
        }

        if (empty($warning_texts)) {
            return '<div class="notice notice-success inline vms-notice"><p>'
                . esc_html__('No blocking or vendor-warning details are currently flagged in this summary view.', 'backstage-venue-manager')
                . '</p></div>';
        }

        $html = '<div class="notice notice-warning inline vms-notice vms-notice--warning"><p><strong>'
            . esc_html__('Current warning details', 'backstage-venue-manager')
            . '</strong></p><ul>';
        foreach ($warning_texts as $warning_text) {
            $html .= '<li>' . esc_html($warning_text) . '</li>';
        }
        $html .= '</ul></div>';

        return $html;
    }

    private function render_event_plan_readiness_details_linked_tec_text(array $linked_tec_summary): string
    {
        $linked_tec_id = absint($linked_tec_summary['linked_tec_id'] ?? 0);
        if ($linked_tec_id > 0) {
            return sprintf(
                /* translators: 1: value 1 used in this message, 2: value 2 used in this message. */
                esc_html__('Linked TEC event: %1$s (%2$s).', 'backstage-venue-manager'),
                /* translators: %d: event ID. */
                esc_html((string) ($linked_tec_summary['linked_tec_title'] ?? sprintf(__('Event #%d', 'backstage-venue-manager'), $linked_tec_id))),
                esc_html(strtoupper((string) ($linked_tec_summary['linked_tec_status'] ?? 'draft')))
            );
        }

        return esc_html__('Linked TEC event: not linked.', 'backstage-venue-manager');
    }

    private function render_event_plan_readiness_details_ticketing_text(array $ticketing_summary, array $add_on_summary): string
    {
        return sprintf(
            /* translators: 1: number 1 used in this message, 2: number 2 used in this message. */
            esc_html__('Configured tickets: %1$d. Configured add-ons: %2$d.', 'backstage-venue-manager'),
            absint($ticketing_summary['effective_ticket_count'] ?? 0),
            absint($add_on_summary['enabled_add_on_count'] ?? 0)
        );
    }

    private function render_event_plan_readiness_details_secondary_vendor_text(array $secondary_vendor_boot_summary, array $readiness_boot_summary): string
    {
        return sprintf(
            /* translators: 1: number 1 used in this message, 2: number 2 used in this message. */
            esc_html__('Secondary vendor warnings: %1$d. Selected secondary vendors: %2$d.', 'backstage-venue-manager'),
            count((array) ($secondary_vendor_boot_summary['secondary_missing'] ?? array()))
                + count((array) ($secondary_vendor_boot_summary['secondary_mismatch'] ?? array()))
                + count((array) ($secondary_vendor_boot_summary['secondary_unqualified'] ?? array())),
            absint($readiness_boot_summary['secondary_vendor_count'] ?? 0)
        );
    }

    private function should_defer_event_plan_admin_section(int $plan_id, string $section): bool
    {
        $plan_id = absint($plan_id);
        $section = sanitize_key($section);
        if ($plan_id <= 0 || $section === '') {
            return false;
        }

        return !wp_doing_ajax()
            && $this->event_plan_admin_section_supports_lazy_load($section)
            && !$this->is_event_plan_admin_section_requested($plan_id, $section);
    }

    private function is_event_plan_admin_section_requested(int $plan_id, string $section): bool
    {
        $plan_id = absint($plan_id);
        $section = sanitize_key($section);
        if ($plan_id <= 0 || $section === '') {
            return false;
        }

        $request = bvmgr_event_plan_current_get_request();
        $requested_section = isset($request['vms_ep_load_section'])
            ? sanitize_key((string) $request['vms_ep_load_section'])
            : '';
        if ($requested_section !== $section) {
            return false;
        }

        $requested_plan_id = isset($request['post']) ? absint($request['post']) : 0;
        if ($requested_plan_id > 0 && $requested_plan_id !== $plan_id) {
            return false;
        }

        return true;
    }

    private function get_event_plan_admin_section_url(int $plan_id, string $section = '', string $anchor = ''): string
    {
        $plan_id = absint($plan_id);
        $anchor = trim($anchor);

        $url = add_query_arg(
            array(
                'post' => $plan_id,
                'action' => 'edit',
            ),
            admin_url('post.php')
        );

        if ($section !== '') {
            $url = add_query_arg('vms_ep_load_section', sanitize_key($section), $url);
        }

        if ($anchor !== '') {
            $url .= '#' . rawurlencode(ltrim($anchor, '#'));
        }

        return $url;
    }

    public function ajax_get_venue_comp_defaults(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Not allowed'), 403);
        }

        if (false === check_ajax_referer(bvmgr_nonce_action_for_request('bvmgr_get_venue_comp_defaults', 'nonce'), 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed. Please refresh the page and try again.', 'backstage-venue-manager')), 403);
        }

        $venue_id   = isset($_POST['venue_id']) ? absint($_POST['venue_id']) : 0;
        $event_date = isset($_POST['event_date']) ? sanitize_text_field(wp_unslash($_POST['event_date'])) : '';

        if ($venue_id <= 0 || $event_date === '') {
            wp_send_json_success(array('row' => array()));
        }

        if (!function_exists('bvmgr_get_event_plan_effective_comp_default')) {
            wp_send_json_error(array('message' => 'Effective default helper not loaded'), 500);
        }

        $resolved = (array) bvmgr_get_event_plan_effective_comp_default($venue_id, $event_date);
        $row = array();
        $has_default = !empty($resolved['has_default']);
        $structure = isset($resolved['structure']) ? sanitize_key((string) $resolved['structure']) : '';
        if ($has_default && $structure !== '') {
            $row['source'] = isset($resolved['source']) ? sanitize_key((string) $resolved['source']) : '';
            $row['label'] = isset($resolved['label']) ? (string) $resolved['label'] : '';
            $row['structure'] = $structure;

            if (array_key_exists('flat_fee_amount', $resolved) && $resolved['flat_fee_amount'] !== null && $resolved['flat_fee_amount'] !== '') {
                $row['flat_fee_amount'] = (string) $resolved['flat_fee_amount'];
            }
            if (array_key_exists('door_split_percent', $resolved) && $resolved['door_split_percent'] !== null && $resolved['door_split_percent'] !== '') {
                $row['door_split_percent'] = (string) $resolved['door_split_percent'];
            }
            if (array_key_exists('attendance_bonus_mode', $resolved) && $resolved['attendance_bonus_mode'] !== null && $resolved['attendance_bonus_mode'] !== '') {
                $row['attendance_bonus_mode'] = (string) $resolved['attendance_bonus_mode'];
            }
            if (array_key_exists('attendance_bonus_start_count', $resolved) && $resolved['attendance_bonus_start_count'] !== null && $resolved['attendance_bonus_start_count'] !== '') {
                $row['attendance_bonus_start_count'] = (string) $resolved['attendance_bonus_start_count'];
            }
            if (array_key_exists('attendance_bonus_step_size', $resolved) && $resolved['attendance_bonus_step_size'] !== null && $resolved['attendance_bonus_step_size'] !== '') {
                $row['attendance_bonus_step_size'] = (string) $resolved['attendance_bonus_step_size'];
            }
            if (array_key_exists('attendance_bonus_step_bonus', $resolved) && $resolved['attendance_bonus_step_bonus'] !== null && $resolved['attendance_bonus_step_bonus'] !== '') {
                $row['attendance_bonus_step_bonus'] = (string) $resolved['attendance_bonus_step_bonus'];
            }
            if (array_key_exists('attendance_bonus_per_ticket_rate', $resolved) && $resolved['attendance_bonus_per_ticket_rate'] !== null && $resolved['attendance_bonus_per_ticket_rate'] !== '') {
                $row['attendance_bonus_per_ticket_rate'] = (string) $resolved['attendance_bonus_per_ticket_rate'];
            }
            if (array_key_exists('attendance_bonus_max_bonus', $resolved) && $resolved['attendance_bonus_max_bonus'] !== null && $resolved['attendance_bonus_max_bonus'] !== '') {
                $row['attendance_bonus_max_bonus'] = (string) $resolved['attendance_bonus_max_bonus'];
            }
            if (($row['source'] ?? '') === 'holiday' && !empty($resolved['holiday_name'])) {
                $row['holiday_name'] = (string) $resolved['holiday_name'];
            }
        }

        wp_send_json_success(array('row' => $row));
    }

    public function ajax_get_event_plan_comp_options(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Not allowed'), 403);
        }

        check_ajax_referer(bvmgr_nonce_action_for_request('bvmgr_comp_options', 'nonce'), 'nonce', true);

        $venue_id   = isset($_POST['venue_id']) ? absint($_POST['venue_id']) : 0;
        $vendor_id  = isset($_POST['vendor_id']) ? absint($_POST['vendor_id']) : 0;
        $event_date = isset($_POST['event_date']) ? sanitize_text_field(wp_unslash($_POST['event_date'])) : '';

        if (!function_exists('bvmgr_get_event_plan_comp_options')) {
            wp_send_json_error(array('message' => 'Comp options helper not loaded'), 500);
        }

        $opts = bvmgr_get_event_plan_comp_options($venue_id, $event_date, $vendor_id);
        $opts['_venue_selected'] = ($venue_id > 0);
        $opts['_date_selected'] = (!empty($event_date));
        $selected_opt = isset($_POST['selected_opt']) ? sanitize_text_field(wp_unslash($_POST['selected_opt'])) : '';
        $html = $this->render_event_plan_compensation_options_response_html($opts, 0, $selected_opt);

        wp_send_json_success(array(
            'html' => $html,
            'max_guarantee' => isset($opts['max_guarantee']) ? (float) $opts['max_guarantee'] : 0.0,
        ));
    }

    public function ajax_load_event_plan_admin_section(): void
    {
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $section = isset($_POST['section']) ? sanitize_key((string) wp_unslash($_POST['section'])) : '';

        if ($post_id <= 0 || get_post_type($post_id) !== 'vms_event_plan') {
            wp_send_json_error(array('message' => 'Invalid Event Plan.'), 400);
        }

        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => 'Not allowed'), 403);
        }

        check_ajax_referer(bvmgr_nonce_action_for_request('bvmgr_event_plan_admin_section', 'nonce'), 'nonce', true);

        if (!$this->event_plan_admin_section_supports_lazy_load($section)) {
            wp_send_json_error(array('message' => 'Section not supported.'), 400);
        }

        $post = get_post($post_id);
        if (!$post instanceof WP_Post || $post->post_type !== 'vms_event_plan') {
            wp_send_json_error(array('message' => 'Event Plan not found.'), 404);
        }

        $trace = function_exists('bvmgr_event_plan_perf_span_start')
            ? bvmgr_event_plan_perf_span_start(
                'event_plan_section_lazy_load',
                $post_id,
                array(
                    'section' => $section,
                    'lazy_load' => 1,
                )
            )
            : '';

        try {
            if ($section === 'staff') {
                $staff_assignments = get_post_meta($post_id, '_vms_staff_assignments', true);
                if (!is_array($staff_assignments)) {
                    $staff_assignments = array();
                }

                $staff_payload = $this->build_event_plan_staff_response_payload($post_id, $staff_assignments);
                $html = is_string($staff_payload['html'] ?? null)
                    ? (string) $staff_payload['html']
                    : '';

                if (function_exists('bvmgr_event_plan_perf_log')) {
                    bvmgr_event_plan_perf_log('event_plan_staff_section_render', $post_id, array(
                        'phase' => 'full',
                        'lazy_load' => 1,
                        'section' => 'staff',
                    ));
                }

                wp_send_json_success(array(
                    'html' => $html,
                    'section' => $section,
                ));
            }

            if ($section === 'secondary_vendors') {
                $payload = $this->build_event_plan_secondary_vendors_lazy_load_response_payload($post_id);
                $html = (string) ($payload['html'] ?? '');

                if (function_exists('bvmgr_event_plan_perf_log')) {
                    bvmgr_event_plan_perf_log('event_plan_vendor_conflict_details', $post_id, array(
                        'phase' => 'full',
                        'lazy_load' => 1,
                        'section' => 'secondary_vendors',
                    ));
                }

                wp_send_json_success(array(
                    'html' => $html,
                    'section' => $section,
                    'has_data' => !empty($payload['has_data']) ? 1 : 0,
                    'summary_meta' => (string) ($payload['summary_meta'] ?? ''),
                    'module_owner' => (string) ($payload['module_owner'] ?? $this->get_event_plan_section_module_owner('secondary_vendors')),
                ));
            }

            if ($section === 'readiness_details') {
                $readiness_payload = $this->build_event_plan_readiness_details_response_payload($post_id);
                $detail_context = isset($readiness_payload['detail_context']) && is_array($readiness_payload['detail_context'])
                    ? $readiness_payload['detail_context']
                    : array();
                $html = is_string($readiness_payload['html'] ?? null)
                    ? (string) $readiness_payload['html']
                    : '';

                if (function_exists('bvmgr_event_plan_perf_log')) {
                    bvmgr_event_plan_perf_log('event_plan_readiness_details', $post_id, array(
                        'phase' => 'full',
                        'lazy_load' => 1,
                        'section' => 'readiness_details',
                        'summary_row_count' => count((array) ($detail_context['summary_rows'] ?? array())),
                        'warning_item_count' => count((array) ($detail_context['warning_items'] ?? array())),
                        'payload_size_bytes' => strlen($html),
                    ));
                }

                wp_send_json_success(array(
                    'html' => $html,
                    'section' => $section,
                ));
            }

            wp_send_json_error(array('message' => 'Section not implemented.'), 400);
        } finally {
            if (function_exists('bvmgr_event_plan_perf_span_finish')) {
                bvmgr_event_plan_perf_span_finish(
                    'event_plan_section_lazy_load',
                    $post_id,
                    $trace,
                    array(
                        'section' => $section,
                        'lazy_load' => 1,
                    )
                );
            }
        }
    }

    public function ajax_save_event_plan_secondary_vendors(): void
    {
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        if ($post_id <= 0 || get_post_type($post_id) !== 'vms_event_plan') {
            wp_send_json_error(array('message' => 'Invalid Event Plan.'), 400);
        }

        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => 'Not allowed'), 403);
        }

        check_ajax_referer(bvmgr_nonce_action_for_request('bvmgr_event_plan_secondary_vendors_save', 'nonce'), 'nonce', true);

        $result = function_exists('bvmgr_event_plan_save_secondary_vendors_module')
            ? bvmgr_event_plan_save_secondary_vendors_module($post_id, (array) $_POST)
            : new WP_Error('vms_secondary_vendor_save_unavailable', __('Additional Vendors save helper is unavailable.', 'backstage-venue-manager'));
        if (is_wp_error($result)) {
            $error_code = (string) $result->get_error_code();
            wp_send_json_error(array(
                'code' => sanitize_key($error_code),
                'message' => sanitize_text_field((string) $result->get_error_message()),
            ), $error_code === 'vms_secondary_vendor_over_capacity' ? 400 : 500);
        }

        // Legacy sink reference: get_event_plan_secondary_vendors_module_payload($post_id)
        $payload = $this->build_event_plan_secondary_vendors_save_response_payload($post_id);
        wp_send_json_success(array(
            'html' => (string) ($payload['html'] ?? ''),
            'has_data' => !empty($payload['has_data']) ? 1 : 0,
            'summary_meta' => (string) ($payload['summary_meta'] ?? ''),
            'module_owner' => (string) ($payload['module_owner'] ?? $this->get_event_plan_section_module_owner('secondary_vendors')),
            'changed' => !empty($result['changed']) ? 1 : 0,
            'dirty_fields' => array_values(array_unique(array_map('sanitize_key', (array) ($result['dirty_fields'] ?? array())))),
            'repair_reasons' => array_values(array_unique(array_map('sanitize_key', (array) ($result['repair_reasons'] ?? array())))),
            'queued_calendar_maintenance' => !empty($result['queued_calendar_maintenance']) ? 1 : 0,
            'message' => !empty($result['changed'])
                ? __('Additional Vendors saved.', 'backstage-venue-manager')
                : __('No Additional Vendor changes to save.', 'backstage-venue-manager'),
        ));
    }

    private function event_plan_ticket_ui_override_meta_keys(): array
    {
        return array(
            'vms_ticket_ui_layout_override' => '_vms_ticket_ui_layout_override',
            'vms_ticket_ui_availability_display_override' => '_vms_ticket_ui_availability_display_override',
            'vms_ticket_ui_sale_availability_display_override' => '_vms_ticket_ui_sale_availability_display_override',
            'vms_ticket_ui_addons_heading_override' => '_vms_ticket_ui_addons_heading_override',
            'vms_ticket_ui_addons_subtext_override' => '_vms_ticket_ui_addons_subtext_override',
            'vms_ticket_ui_help_tickets_override' => '_vms_ticket_ui_help_tickets_override',
            'vms_ticket_ui_help_addons_override' => '_vms_ticket_ui_help_addons_override',
        );
    }

    private function save_event_plan_ticket_ui_overrides(int $post_id, array $request): array
    {
        $post_id = absint($post_id);
        $meta_keys = $this->event_plan_ticket_ui_override_meta_keys();
        $result = array(
            'changed' => false,
            'changed_fields' => array(),
            'values' => array(),
        );

        if ($post_id <= 0 || get_post_type($post_id) !== 'vms_event_plan') {
            foreach ($meta_keys as $field_name => $meta_key) {
                unset($meta_key);
                $result['values'][$field_name] = '';
            }

            return $result;
        }

        $targets = array();
        if (array_key_exists('vms_ticket_ui_layout_override', $request)) {
            $layout = sanitize_key((string) wp_unslash($request['vms_ticket_ui_layout_override']));
            $targets['vms_ticket_ui_layout_override'] = in_array($layout, array('classic', 'v2', 'progressive'), true)
                ? $layout
                : '';
        }
        if (array_key_exists('vms_ticket_ui_availability_display_override', $request)) {
            $display = sanitize_key((string) wp_unslash($request['vms_ticket_ui_availability_display_override']));
            $targets['vms_ticket_ui_availability_display_override'] = in_array($display, array('always', 'low', 'hide'), true)
                ? $display
                : '';
        }
        if (array_key_exists('vms_ticket_ui_sale_availability_display_override', $request)) {
            $display = sanitize_key((string) wp_unslash($request['vms_ticket_ui_sale_availability_display_override']));
            $targets['vms_ticket_ui_sale_availability_display_override'] = in_array($display, array('when_capped', 'low', 'hide'), true)
                ? $display
                : '';
        }
        if (array_key_exists('vms_ticket_ui_addons_heading_override', $request)) {
            $targets['vms_ticket_ui_addons_heading_override'] = sanitize_text_field((string) wp_unslash($request['vms_ticket_ui_addons_heading_override']));
        }
        if (array_key_exists('vms_ticket_ui_addons_subtext_override', $request)) {
            $targets['vms_ticket_ui_addons_subtext_override'] = sanitize_text_field((string) wp_unslash($request['vms_ticket_ui_addons_subtext_override']));
        }
        if (array_key_exists('vms_ticket_ui_help_tickets_override', $request)) {
            $targets['vms_ticket_ui_help_tickets_override'] = wp_kses_post((string) wp_unslash($request['vms_ticket_ui_help_tickets_override']));
        }
        if (array_key_exists('vms_ticket_ui_help_addons_override', $request)) {
            $targets['vms_ticket_ui_help_addons_override'] = wp_kses_post((string) wp_unslash($request['vms_ticket_ui_help_addons_override']));
        }

        foreach ($targets as $field_name => $target_value) {
            $meta_key = $meta_keys[$field_name] ?? '';
            if ($meta_key === '') {
                continue;
            }

            $current_value = (string) get_post_meta($post_id, $meta_key, true);
            if ($target_value !== '') {
                update_post_meta($post_id, $meta_key, $target_value);
            } else {
                delete_post_meta($post_id, $meta_key);
            }

            if ($current_value !== $target_value) {
                $result['changed'] = true;
                $result['changed_fields'][] = $field_name;
            }
        }

        foreach ($meta_keys as $field_name => $meta_key) {
            if (array_key_exists($field_name, $targets)) {
                $result['values'][$field_name] = (string) $targets[$field_name];
                continue;
            }

            $result['values'][$field_name] = (string) get_post_meta($post_id, $meta_key, true);
        }

        return $result;
    }

    public function ajax_save_event_plan_ticket_ui_overrides(): void
    {
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        if ($post_id <= 0 || get_post_type($post_id) !== 'vms_event_plan') {
            wp_send_json_error(array('message' => 'Invalid Event Plan.'), 400);
        }

        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => 'Not allowed'), 403);
        }

        check_ajax_referer(bvmgr_nonce_action_for_request('bvmgr_event_plan_ticket_ui_overrides_save', 'nonce'), 'nonce', true);

        $result = $this->save_event_plan_ticket_ui_overrides($post_id, (array) $_POST);

        wp_send_json_success(array(
            'changed' => !empty($result['changed']) ? 1 : 0,
            'changed_fields' => array_values(array_unique(array_map('sanitize_key', (array) ($result['changed_fields'] ?? array())))),
            'values' => is_array($result['values']) ? $result['values'] : array(),
            'message' => !empty($result['changed'])
                ? __('Public UI overrides saved.', 'backstage-venue-manager')
                : __('No public UI override changes to save.', 'backstage-venue-manager'),
        ));
    }

    private function save_event_plan_calendar_unpublished_suppress(int $post_id, bool $suppress): array
    {
        $post_id = absint($post_id);
        if ($post_id <= 0 || get_post_type($post_id) !== 'vms_event_plan') {
            return array(
                'changed' => false,
                'suppressed' => false,
            );
        }

        $meta_key = function_exists('bvmgr_meta_key')
            ? (bvmgr_meta_key('event_plan', 'calendar_unpublished_suppress') ?: '_vms_calendar_unpublished_suppress')
            : '_vms_calendar_unpublished_suppress';
        $stored = (string) get_post_meta($post_id, $meta_key, true);
        $current = in_array($stored, array('1', 'yes', 'true'), true);

        if ($suppress) {
            update_post_meta($post_id, $meta_key, '1');
        } else {
            delete_post_meta($post_id, $meta_key);
        }

        return array(
            'changed' => ($current !== $suppress),
            'suppressed' => $suppress,
        );
    }

    public function ajax_save_event_plan_calendar_unpublished_suppress(): void
    {
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        if ($post_id <= 0 || get_post_type($post_id) !== 'vms_event_plan') {
            wp_send_json_error(array('message' => 'Invalid Event Plan.'), 400);
        }

        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => 'Not allowed'), 403);
        }

        check_ajax_referer(bvmgr_nonce_action_for_request('bvmgr_event_plan_calendar_unpublished_suppress_save', 'nonce'), 'nonce', true);

        $suppress = !empty($_POST['suppress']);
        $result = $this->save_event_plan_calendar_unpublished_suppress($post_id, $suppress);

        wp_send_json_success(array(
            'changed' => !empty($result['changed']) ? 1 : 0,
            'suppressed' => !empty($result['suppressed']) ? 1 : 0,
            'message' => $suppress
                ? __('Unpublished calendar warning suppressor saved.', 'backstage-venue-manager')
                : __('Unpublished calendar warning suppressor cleared.', 'backstage-venue-manager'),
        ));
    }

    public function ajax_load_event_plan_supporting_vendor_options(): void
    {
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        if ($post_id <= 0 || get_post_type($post_id) !== 'vms_event_plan') {
            wp_send_json_error(array('message' => 'Invalid Event Plan.'), 400);
        }

        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => 'Not allowed'), 403);
        }

        check_ajax_referer(bvmgr_nonce_action_for_request('bvmgr_event_plan_admin_section', 'nonce'), 'nonce', true);

        $trace = function_exists('bvmgr_event_plan_perf_span_start')
            ? bvmgr_event_plan_perf_span_start('event_plan_supporting_vendor_options_lazy_load', $post_id, array(
                'section' => 'supporting_vendor_options',
                'lazy_load' => 1,
            ))
            : '';

        try {
            $bundle = $this->get_event_plan_meta_bundle($post_id);
            $bands = get_posts(array(
                'post_type'      => 'vms_vendor',
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
                'no_found_rows'  => true,
                'update_post_term_cache' => false,
            ));
            $response_payload = $this->build_event_plan_supporting_vendor_options_response_payload(
                $post_id,
                is_array($bands) ? $bands : array(),
                (string) ($bundle['event_date'] ?? ''),
                absint($bundle['venue_id'] ?? 0),
                absint($bundle['band_vendor_id'] ?? 0)
            );
            $primary_html = is_string($response_payload['primary_html'] ?? null)
                ? (string) $response_payload['primary_html']
                : '';
            $supporting_html = is_string($response_payload['supporting_html'] ?? null)
                ? (string) $response_payload['supporting_html']
                : '';

            if (function_exists('bvmgr_event_plan_perf_log')) {
                bvmgr_event_plan_perf_log('event_plan_vendor_options', $post_id, array(
                    'phase' => 'lazy_full_vendor_options',
                    'option_mode' => 'supporting_vendor_options_response',
                    'lazy_load' => 1,
                    'primary_option_count' => count((array) ($response_payload['primary_rows'] ?? array())),
                    'supporting_option_count' => count((array) ($response_payload['supporting_rows'] ?? array())),
                    'primary_option_payload_bytes' => strlen($primary_html),
                    'shared_option_payload_bytes' => strlen($supporting_html),
                ));
            }

            wp_send_json_success(array(
                'primary_html' => $primary_html,
                'supporting_html' => $supporting_html,
            ));
        } finally {
            if (function_exists('bvmgr_event_plan_perf_span_finish')) {
                bvmgr_event_plan_perf_span_finish('event_plan_supporting_vendor_options_lazy_load', $post_id, $trace, array(
                    'section' => 'supporting_vendor_options',
                    'lazy_load' => 1,
                ));
            }
        }
    }

    private function build_event_plan_supporting_vendor_options_response_payload(int $post_id, array $bands, string $event_date, int $venue_id_effective, int $selected_primary_vendor_id = 0): array
    {
        $vendor_boot_summary = $this->get_event_plan_vendor_boot_summary($post_id, $bands, $event_date, $venue_id_effective, array(
            'include_primary_rows' => true,
            'include_supporting_rows' => true,
            'include_vendor_state_map' => false,
            'primary_scope' => 'all',
            'primary_vendor_id' => $selected_primary_vendor_id,
            'supporting_scope' => 'all',
            'supporting_vendor_ids' => array(),
        ));
        $primary_rows = isset($vendor_boot_summary['primary_rows']) && is_array($vendor_boot_summary['primary_rows'])
            ? $vendor_boot_summary['primary_rows']
            : array();
        $supporting_rows = isset($vendor_boot_summary['supporting_rows']) && is_array($vendor_boot_summary['supporting_rows'])
            ? $vendor_boot_summary['supporting_rows']
            : array();

        return array(
            'primary_rows' => $primary_rows,
            'supporting_rows' => $supporting_rows,
            'primary_html' => $this->render_event_plan_supporting_vendor_options_primary_html($primary_rows, $selected_primary_vendor_id),
            'supporting_html' => $this->render_event_plan_supporting_vendor_options_supporting_html($supporting_rows),
        );
    }

    private function render_event_plan_supporting_vendor_options_primary_html(array $rows, int $selected_id): string
    {
        return $this->render_event_plan_primary_vendor_option_html($rows, $selected_id);
    }

    private function render_event_plan_supporting_vendor_options_supporting_html(array $rows): string
    {
        return $this->render_event_plan_supporting_vendor_option_html($rows, 0);
    }

    private function event_plan_admin_section_supports_lazy_load(string $section): bool
    {
        return in_array($section, array('staff', 'secondary_vendors', 'readiness_details'), true);
    }

    private function build_event_plan_vendor_option_context(int $post_id, array $bands, string $event_date, int $venue_id_effective, int $selected_primary_vendor_id = 0, array $selected_supporting_vendor_ids = array(), bool $include_full_option_payload = false): array
    {
        $trace = function_exists('bvmgr_event_plan_perf_span_start')
            ? bvmgr_event_plan_perf_span_start('event_plan_vendor_option_context', $post_id, array(
                'section' => 'vendor_option_context',
                'band_count' => count($bands),
                'venue_id' => $venue_id_effective,
                'primary_mode' => $include_full_option_payload ? 'all' : 'selected_only',
                'supporting_mode' => $include_full_option_payload ? 'all' : 'selected_only',
            ))
            : '';

        try {
            if (function_exists('bvmgr_event_plan_perf_memory_checkpoint')) {
                bvmgr_event_plan_perf_memory_checkpoint($post_id, 'vendor_option_context_before', array(
                    'section' => 'vendor_option_context',
                    'band_count' => count($bands),
                    'venue_id' => $venue_id_effective,
                    'primary_mode' => $include_full_option_payload ? 'all' : 'selected_only',
                    'supporting_mode' => $include_full_option_payload ? 'all' : 'selected_only',
                ), 'vendor_option_context');
            }

            $vendor_boot_summary = $this->get_event_plan_vendor_boot_summary($post_id, $bands, $event_date, $venue_id_effective, array(
                'include_primary_rows' => true,
                'include_supporting_rows' => true,
                'include_vendor_state_map' => false,
                'primary_scope' => $include_full_option_payload ? 'all' : 'selected_only',
                'primary_vendor_id' => $selected_primary_vendor_id,
                'supporting_scope' => $include_full_option_payload ? 'all' : 'selected_only',
                'supporting_vendor_ids' => $selected_supporting_vendor_ids,
            ));
            $primary_rows = isset($vendor_boot_summary['primary_rows']) && is_array($vendor_boot_summary['primary_rows'])
                ? $vendor_boot_summary['primary_rows']
                : array();
            $supporting_rows = isset($vendor_boot_summary['supporting_rows']) && is_array($vendor_boot_summary['supporting_rows'])
                ? $vendor_boot_summary['supporting_rows']
                : array();
            $primary_option_html = $include_full_option_payload
                ? $this->render_event_plan_primary_vendor_option_html($primary_rows, $selected_primary_vendor_id)
                : $this->render_event_plan_primary_vendor_selected_option_html($primary_rows, $selected_primary_vendor_id);
            $supporting_option_html = $include_full_option_payload
                ? $this->render_event_plan_supporting_vendor_option_html($supporting_rows, 0)
                : '';

            if (function_exists('bvmgr_event_plan_perf_memory_checkpoint')) {
                bvmgr_event_plan_perf_memory_checkpoint($post_id, 'vendor_option_context_after', array(
                    'section' => 'vendor_option_context',
                    'primary_option_count' => count($primary_rows),
                    'primary_option_payload_bytes' => strlen($primary_option_html),
                    'supporting_option_count' => count($supporting_rows),
                    'supporting_option_payload_bytes' => strlen($supporting_option_html),
                    'option_mode' => $include_full_option_payload ? 'shared_full_payload' : 'selected_rows_only',
                ), 'vendor_option_context');
            }

            return array(
                'primary_rows' => $primary_rows,
                'primary_option_html' => $primary_option_html,
                'supporting_rows' => $supporting_rows,
                'supporting_option_html' => $supporting_option_html,
                'vendor_state_map' => isset($vendor_boot_summary['vendor_state_map']) && is_array($vendor_boot_summary['vendor_state_map'])
                    ? $vendor_boot_summary['vendor_state_map']
                    : array(),
            );
        } finally {
            if (function_exists('bvmgr_event_plan_perf_span_finish')) {
                bvmgr_event_plan_perf_span_finish('event_plan_vendor_option_context', $post_id, $trace, array(
                    'section' => 'vendor_option_context',
                    'primary_mode' => $include_full_option_payload ? 'all' : 'selected_only',
                    'supporting_mode' => $include_full_option_payload ? 'all' : 'selected_only',
                ));
            }
        }
    }

    private function render_event_plan_primary_vendor_option_html(array $rows, int $selected_id): string
    {
        $html = '<option value="">' . esc_html__('-- Select Primary Vendor --', 'backstage-venue-manager') . '</option>';

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $vendor_id = absint($row['vendor_id'] ?? 0);
            if ($vendor_id <= 0) {
                continue;
            }

            $html .= '<option value="' . esc_attr((string) $vendor_id) . '"'
                . selected($selected_id, $vendor_id, false)
                . ' data-vendor-title="' . esc_attr((string) ($row['vendor_title'] ?? '')) . '"'
                . ' data-tax-ok="' . esc_attr((string) ($row['tax_ok'] ?? '0')) . '"'
                . ' data-tax-bypass-active="' . esc_attr((string) ($row['tax_bypass_active'] ?? '0')) . '"'
                . ' data-tax-bypass-until="' . esc_attr((string) ($row['tax_bypass_until'] ?? '')) . '"'
                . ' data-tax-bypass-reason="' . esc_attr((string) ($row['tax_bypass_reason'] ?? '')) . '"'
                . ' data-tax-missing="' . esc_attr((string) ($row['tax_missing'] ?? '')) . '">'
                . esc_html((string) ($row['label'] ?? ''))
                . '</option>';
        }

        return $html;
    }

    private function render_event_plan_primary_vendor_selected_option_html(array $rows, int $selected_id): string
    {
        $html = '<option value="">' . esc_html__('-- Select Primary Vendor --', 'backstage-venue-manager') . '</option>';
        if ($selected_id <= 0) {
            return $html;
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $vendor_id = absint($row['vendor_id'] ?? 0);
            if ($vendor_id !== $selected_id) {
                continue;
            }

            $html .= '<option value="' . esc_attr((string) $vendor_id) . '"'
                . selected($selected_id, $vendor_id, false)
                . ' data-vendor-title="' . esc_attr((string) ($row['vendor_title'] ?? '')) . '"'
                . ' data-tax-ok="' . esc_attr((string) ($row['tax_ok'] ?? '0')) . '"'
                . ' data-tax-bypass-active="' . esc_attr((string) ($row['tax_bypass_active'] ?? '0')) . '"'
                . ' data-tax-bypass-until="' . esc_attr((string) ($row['tax_bypass_until'] ?? '')) . '"'
                . ' data-tax-bypass-reason="' . esc_attr((string) ($row['tax_bypass_reason'] ?? '')) . '"'
                . ' data-tax-missing="' . esc_attr((string) ($row['tax_missing'] ?? '')) . '">'
                . esc_html((string) ($row['label'] ?? ''))
                . '</option>';

            return $html;
        }

        $fallback_title = trim((string) get_the_title($selected_id));
        if ($fallback_title === '') {
            $fallback_title = __('Assigned primary vendor', 'backstage-venue-manager');
        }

        $html .= '<option value="' . esc_attr((string) $selected_id) . '" selected="selected" data-vendor-title="' . esc_attr($fallback_title) . '" data-tax-ok="0" data-tax-bypass-active="0" data-tax-bypass-until="" data-tax-bypass-reason="" data-tax-missing="">'
            . esc_html($fallback_title)
            . '</option>';

        return $html;
    }

    private function render_event_plan_supporting_vendor_option_html(array $rows, int $selected_id): string
    {
        $html = '<option value="">' . esc_html__('-- Select a Vendor --', 'backstage-venue-manager') . '</option>';

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $vendor_id = absint($row['vendor_id'] ?? 0);
            if ($vendor_id <= 0) {
                continue;
            }

            $default_fee_attr = '';
            $default_fee = (string) ($row['default_fee'] ?? '');
            if ($default_fee !== '') {
                $default_fee_attr = ' data-lineup-support-default-fee="' . esc_attr($default_fee) . '"';
            }

            $html .= '<option value="' . esc_attr((string) $vendor_id) . '"'
                . ' data-vendor-title="' . esc_attr((string) ($row['vendor_title'] ?? '')) . '"'
                . $default_fee_attr
                . ' ' . selected($selected_id, $vendor_id, false)
                . '>'
                . esc_html((string) ($row['label'] ?? ''))
                . '</option>';
        }

        return $html;
    }

    private function render_event_plan_supporting_vendor_selected_option_html(array $rows, int $selected_id): string
    {
        $html = '<option value="">' . esc_html__('-- Select a Vendor --', 'backstage-venue-manager') . '</option>';
        if ($selected_id <= 0) {
            return $html;
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $vendor_id = absint($row['vendor_id'] ?? 0);
            if ($vendor_id !== $selected_id) {
                continue;
            }

            $default_fee_attr = '';
            $default_fee = (string) ($row['default_fee'] ?? '');
            if ($default_fee !== '') {
                $default_fee_attr = ' data-lineup-support-default-fee="' . esc_attr($default_fee) . '"';
            }

            $html .= '<option value="' . esc_attr((string) $vendor_id) . '"'
                . ' data-vendor-title="' . esc_attr((string) ($row['vendor_title'] ?? '')) . '"'
                . $default_fee_attr
                . selected($selected_id, $vendor_id, false)
                . '>'
                . esc_html((string) ($row['label'] ?? ''))
                . '</option>';

            return $html;
        }

        $fallback_title = trim((string) get_the_title($selected_id));
        if ($fallback_title === '') {
            $fallback_title = __('Assigned vendor', 'backstage-venue-manager');
        }

        $html .= '<option value="' . esc_attr((string) $selected_id) . '" selected="selected" data-vendor-title="' . esc_attr($fallback_title) . '">'
            . esc_html($fallback_title)
            . '</option>';

        return $html;
    }

    /**
     * Render the complete HTML fragment returned in the compensation-options AJAX html field.
     */
    private function render_event_plan_compensation_options_response_html(array $opts, int $current_pkg_id = 0, string $selected_opt = ""): string
    {
        $fmt_money = function($n): string {
            $n = (float) $n;
            if ($n < 0) $n = 0;
            return '$' . number_format_i18n($n, 2);
        };

        $defaults = isset($opts['defaults']) && is_array($opts['defaults']) ? $opts['defaults'] : array();
        $packages = isset($opts['packages']) && is_array($opts['packages']) ? $opts['packages'] : array();
        $venue_selected = !empty($opts['_venue_selected']);

        // Recompute max guarantee from the provided tiles (keeps badges correct even if upstream values drift).
        $max = 0.0;
        foreach ($defaults as $d) {
            if (is_array($d) && !empty($d['enabled'])) {
                $max = max($max, (float) ($d['guarantee'] ?? 0.0));
            }
        }
        foreach ($packages as $p) {
            if (is_array($p) && !empty($p['enabled'])) {
                $max = max($max, (float) ($p['guarantee'] ?? 0.0));
            }
        }
        if ($max < 0) $max = 0.0;

        // Deterministic visual scale for guarantee comparisons.
        // Lowest guarantee maps to scale-1 and highest maps to scale-5.
        $scale_values = array();
        foreach ($defaults as $d) {
            if (!is_array($d) || empty($d['enabled'])) continue;
            $g = isset($d['guarantee']) ? (float) $d['guarantee'] : 0.0;
            if ($g < 0) $g = 0.0;
            $scale_values[(string) sprintf('%.4f', $g)] = $g;
        }
        foreach ($packages as $p) {
            if (!is_array($p) || empty($p['enabled'])) continue;
            $g = isset($p['guarantee']) ? (float) $p['guarantee'] : 0.0;
            if ($g < 0) $g = 0.0;
            $scale_values[(string) sprintf('%.4f', $g)] = $g;
        }
        $scale_points = array_values($scale_values);
        sort($scale_points, SORT_NUMERIC);
        $scale_count = count($scale_points);
        $scale_class_for = static function(bool $enabled, float $guarantee) use ($scale_points, $scale_count): string {
            if (!$enabled || $scale_count <= 1) return '';
            if ($guarantee < 0) $guarantee = 0.0;

            $rank = 0;
            $needle = (float) $guarantee;
            foreach ($scale_points as $idx => $point) {
                if ((float) $point >= $needle) {
                    $rank = (int) $idx;
                    break;
                }
                $rank = (int) $idx;
            }

            $bucket = (int) floor(((float) $rank / max(1, $scale_count - 1)) * 4.0);
            $bucket = max(0, min(4, $bucket));
            return ' vms-comp-opt-tile--scale-' . (string) ($bucket + 1);
        };

        $sel = trim((string) $selected_opt);
        if ($sel === '' && (int) $current_pkg_id > 0) {
            $sel = 'package:' . (int) $current_pkg_id;
        }

        $html = '';

        $html .= '<div class="vms-comp-opt-row">';
        $html .= '<div class="vms-comp-opt-row__title"><strong>' . esc_html__('Defaults', 'backstage-venue-manager') . '</strong></div>';
        if ($scale_count > 1) {
            $html .= '<div class="description vms-comp-opt-scale-legend">' . esc_html__('Color scale: lower guaranteed pay -> higher guaranteed pay.', 'backstage-venue-manager') . '</div>';
        }
        $html .= '<div class="vms-comp-opt-tiles vms-comp-opt-tiles--defaults">';

        foreach (array('venue', 'vendor', 'holiday') as $k) {
            $d = isset($defaults[$k]) && is_array($defaults[$k]) ? $defaults[$k] : array();
            $html .= $this->render_event_plan_compensation_default_option_tile_html($k, $d, $sel, $max, $fmt_money, $scale_class_for);
        }

        $html .= '</div>';
        $html .= '</div>';

        $html .= '<div class="vms-comp-opt-row vms-comp-opt-row--packages">';
        $html .= '<div class="vms-comp-opt-row__title"><strong>' . esc_html__('Packages (Optional presets)', 'backstage-venue-manager') . '</strong></div>';
 
        if (empty($packages)) {
            $html .= $this->render_event_plan_compensation_package_empty_state_html($venue_selected);
        } else {
            $html .= '<div class="vms-comp-opt-tiles vms-comp-opt-tiles--packages">';

            foreach ($packages as $p) {
                if (!is_array($p)) continue;
                $html .= $this->render_event_plan_compensation_package_option_tile_html($p, $sel, $max, $fmt_money, $scale_class_for);
            }
 
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    private function render_event_plan_compensation_package_empty_state_html(bool $venue_selected): string
    {
        if (!$venue_selected) {
            return '<div class="notice notice-info inline vms-notice vms-notice--info vms-notice-tight"><p><em>' . esc_html__('Select a Venue above to load packages.', 'backstage-venue-manager') . '</em></p></div>';
        }

        return '<div class="notice notice-info inline vms-notice vms-notice--info vms-notice-tight"><p><strong>' . esc_html__('No Comp Packages are available for the selected venue yet.', 'backstage-venue-manager') . '</strong></p></div>';
    }

    private function render_event_plan_compensation_default_option_tile_html(string $key, array $option, string $selected_opt, float $max, callable $fmt_money, callable $scale_class_for): string
    {
        $enabled = !empty($option['enabled']);
        $title = isset($option['title']) ? (string) $option['title'] : '';
        $sub = isset($option['subtitle']) ? (string) $option['subtitle'] : '';
        $terms = isset($option['terms']) && is_array($option['terms']) ? $option['terms'] : array();
        $guarantee = isset($option['guarantee']) ? (float) $option['guarantee'] : 0.0;
        if ($guarantee < 0) {
            $guarantee = 0.0;
        }

        $opt = 'default:' . $key;
        $is_sel = ($selected_opt !== '' && $selected_opt === $opt);
        $is_max = ($enabled && $max > 0 && abs($guarantee - $max) < 0.0001);
        $scale_class = (string) $scale_class_for($enabled, $guarantee);

        $html = '<button type="button" class="vms-comp-opt-tile' . $scale_class . ($enabled ? '' : ' is-disabled') . ($is_sel ? ' is-selected' : '') . '"'
            . ' data-opt-kind="default" data-opt-key="' . esc_attr($key) . '"'
            . ' data-opt="' . esc_attr($opt) . '"'
            . $this->render_event_plan_compensation_option_tile_term_data_attributes($terms)
            . ' data-package-id="0"'
            . ($enabled ? '' : ' disabled="disabled"')
            . '>';

        $html .= '<div class="vms-comp-opt-tile__title">' . esc_html($title) . '</div>';
        $html .= '<div class="vms-comp-opt-tile__value">' . ($enabled ? esc_html($fmt_money($guarantee)) : '—') . '</div>';
        $html .= '<div class="vms-comp-opt-tile__sub">' . esc_html($sub) . '</div>';
        $html .= '<div class="vms-comp-opt-tile__badge' . ($is_max ? '' : ' vms-hidden') . '">' . esc_html__('Highest guaranteed', 'backstage-venue-manager') . '</div>';
        $html .= '</button>';

        return $html;
    }

    private function render_event_plan_compensation_package_option_tile_html(array $package, string $selected_opt, float $max, callable $fmt_money, callable $scale_class_for): string
    {
        $enabled = !empty($package['enabled']);
        $package_id = isset($package['id']) ? (int) $package['id'] : 0;
        $title = isset($package['title']) ? (string) $package['title'] : '';
        $sub = isset($package['subtitle']) ? (string) $package['subtitle'] : '';
        $terms = isset($package['terms']) && is_array($package['terms']) ? $package['terms'] : array();
        $guarantee = isset($package['guarantee']) ? (float) $package['guarantee'] : 0.0;
        if ($guarantee < 0) {
            $guarantee = 0.0;
        }

        $opt = 'package:' . $package_id;
        $is_sel = ($selected_opt !== '' && $selected_opt === $opt);
        $is_max = ($enabled && $max > 0 && abs($guarantee - $max) < 0.0001);
        $scale_class = (string) $scale_class_for($enabled, $guarantee);

        $html = '<button type="button" class="vms-comp-opt-tile' . $scale_class . ($enabled ? '' : ' is-disabled') . ($is_sel ? ' is-selected' : '') . '"'
            . ' data-opt-kind="package" data-opt-id="' . esc_attr($package_id) . '"'
            . ' data-opt="' . esc_attr($opt) . '"'
            . $this->render_event_plan_compensation_option_tile_term_data_attributes($terms)
            . ' data-package-id="' . esc_attr($package_id) . '"'
            . ($enabled ? '' : ' disabled="disabled"')
            . '>';

        $html .= '<div class="vms-comp-opt-tile__title">' . esc_html($title) . '</div>';
        $html .= '<div class="vms-comp-opt-tile__value">' . ($enabled ? esc_html($fmt_money($guarantee)) : '—') . '</div>';
        $html .= '<div class="vms-comp-opt-tile__sub">' . esc_html($sub) . '</div>';
        $html .= '<div class="vms-comp-opt-tile__badge' . ($is_max ? '' : ' vms-hidden') . '">' . esc_html__('Highest guaranteed', 'backstage-venue-manager') . '</div>';
        $html .= '</button>';

        return $html;
    }

    private function render_event_plan_compensation_option_tile_term_data_attributes(array $terms): string
    {
        $values = array(
            'structure' => isset($terms['structure']) ? (string) $terms['structure'] : '',
            'flat' => array_key_exists('flat_fee_amount', $terms) ? (string) $terms['flat_fee_amount'] : '',
            'split' => array_key_exists('door_split_percent', $terms) ? (string) $terms['door_split_percent'] : '',
            'bonus_mode' => array_key_exists('attendance_bonus_mode', $terms) ? (string) $terms['attendance_bonus_mode'] : '',
            'bonus_start_count' => array_key_exists('attendance_bonus_start_count', $terms) ? (string) $terms['attendance_bonus_start_count'] : '',
            'bonus_step_size' => array_key_exists('attendance_bonus_step_size', $terms) ? (string) $terms['attendance_bonus_step_size'] : '',
            'bonus_step_bonus' => array_key_exists('attendance_bonus_step_bonus', $terms) ? (string) $terms['attendance_bonus_step_bonus'] : '',
            'bonus_per_ticket_rate' => array_key_exists('attendance_bonus_per_ticket_rate', $terms) ? (string) $terms['attendance_bonus_per_ticket_rate'] : '',
            'bonus_max_bonus' => array_key_exists('attendance_bonus_max_bonus', $terms) ? (string) $terms['attendance_bonus_max_bonus'] : '',
            'commission_percent' => array_key_exists('commission_percent', $terms) ? (string) $terms['commission_percent'] : '',
            'commission_mode' => array_key_exists('commission_mode', $terms) ? (string) $terms['commission_mode'] : '',
        );

        return ' data-structure="' . esc_attr($values['structure']) . '"'
            . ' data-flat="' . esc_attr($values['flat']) . '"'
            . ' data-split="' . esc_attr($values['split']) . '"'
            . ' data-bonus-mode="' . esc_attr($values['bonus_mode']) . '"'
            . ' data-bonus-start-count="' . esc_attr($values['bonus_start_count']) . '"'
            . ' data-bonus-step-size="' . esc_attr($values['bonus_step_size']) . '"'
            . ' data-bonus-step-bonus="' . esc_attr($values['bonus_step_bonus']) . '"'
            . ' data-bonus-per-ticket-rate="' . esc_attr($values['bonus_per_ticket_rate']) . '"'
            . ' data-bonus-max-bonus="' . esc_attr($values['bonus_max_bonus']) . '"'
            . ' data-commission-percent="' . esc_attr($values['commission_percent']) . '"'
            . ' data-commission-mode="' . esc_attr($values['commission_mode']) . '"';
    }

    private function get_lock_pay_required_basics_labels(): array
    {
        return array(
            __('Date', 'backstage-venue-manager'),
            __('Venue', 'backstage-venue-manager'),
            __('Start Time', 'backstage-venue-manager'),
            __('End Time', 'backstage-venue-manager'),
            __('Primary Vendor', 'backstage-venue-manager'),
        );
    }

    private function get_lock_pay_basics_notice_copy(): string
    {
        return __('Save the basic Event Plan details first before locking pay. Required: Date, Venue, Start Time, End Time, and Primary Vendor.', 'backstage-venue-manager');
    }

    private function get_lock_pay_basics_state(int $post_id, array $request = array()): array
    {
        $event_date = array_key_exists('vms_event_date', $request)
            ? sanitize_text_field(wp_unslash((string) $request['vms_event_date']))
            : (string) get_post_meta($post_id, '_vms_event_date', true);

        $start_time = array_key_exists('vms_start_time', $request)
            ? sanitize_text_field(wp_unslash((string) $request['vms_start_time']))
            : (string) get_post_meta($post_id, '_vms_start_time', true);

        $end_time = array_key_exists('vms_end_time', $request)
            ? sanitize_text_field(wp_unslash((string) $request['vms_end_time']))
            : (string) get_post_meta($post_id, '_vms_end_time', true);

        $venue_id = array_key_exists('vms_venue_id', $request)
            ? absint($request['vms_venue_id'])
            : (int) get_post_meta($post_id, '_vms_venue_id', true);

        $band_id = array_key_exists('vms_band_vendor_id', $request)
            ? absint($request['vms_band_vendor_id'])
            : (int) get_post_meta($post_id, '_vms_band_vendor_id', true);

        $missing = array();
        $labels = $this->get_lock_pay_required_basics_labels();

        if ($event_date === '') {
            $missing[] = $labels[0];
        }
        if ($venue_id <= 0) {
            $missing[] = $labels[1];
        }
        if ($start_time === '') {
            $missing[] = $labels[2];
        }
        if ($end_time === '') {
            $missing[] = $labels[3];
        }
        if ($band_id <= 0) {
            $missing[] = $labels[4];
        }

        if ($venue_id > 0) {
            $venue_post = get_post($venue_id);
            if (!$venue_post || $venue_post->post_type !== 'vms_venue' || $venue_post->post_status === 'trash') {
                $missing[] = $labels[1];
            }
        }

        if ($band_id > 0 && function_exists('bvmgr_event_plan_vendor_exists') && !bvmgr_event_plan_vendor_exists($band_id)) {
            $missing[] = $labels[4];
        }

        if ($event_date !== '' && strtotime($event_date) === false) {
            $missing[] = $labels[0];
        }

        $base_date = ($event_date !== '' && strtotime($event_date) !== false) ? $event_date : '2000-01-01';
        $start_ts = false;
        $end_ts = false;

        if ($start_time !== '') {
            $start_ts = strtotime($base_date . ' ' . $start_time);
            if ($start_ts === false) {
                $missing[] = $labels[2];
            }
        }

        if ($end_time !== '') {
            $end_ts = strtotime($base_date . ' ' . $end_time);
            if ($end_ts === false) {
                $missing[] = $labels[3];
            }
        }

        if ($start_ts !== false && $end_ts !== false && $end_ts <= $start_ts) {
            $bump = defined('DAY_IN_SECONDS') ? (int) DAY_IN_SECONDS : 86400;
            $end_ts += $bump;
            if ($end_ts <= $start_ts) {
                $missing[] = $labels[3];
            }
        }

        $missing = array_values(array_unique(array_filter(array_map('strval', $missing))));

        return array(
            'ok' => empty($missing),
            'missing' => $missing,
            'values' => array(
                'event_date' => $event_date,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'venue_id' => $venue_id,
                'band_id' => $band_id,
            ),
        );
    }

    private function format_cancellation_gmt(string $gmt): string
    {
        $gmt = trim($gmt);
        if ($gmt === '') {
            return '';
        }
        $ts = strtotime($gmt . ' GMT');
        if (!$ts) {
            return $gmt;
        }
        return wp_date('M j, Y g:i a', $ts, wp_timezone());
    }

    private function get_cancellation_notification_kind_labels(): array
    {
        return array(
            'vendor_primary' => __('Primary Vendor', 'backstage-venue-manager'),
            'vendor_secondary' => __('Secondary Vendor', 'backstage-venue-manager'),
            'vendor_lineup' => __('Vendor', 'backstage-venue-manager'),
            'assigned_vendor' => __('Vendor', 'backstage-venue-manager'),
            'staff' => __('Staff', 'backstage-venue-manager'),
        );
    }

    private function get_cancellation_notification_group_labels(): array
    {
        return array(
            'vendor' => __('Vendors', 'backstage-venue-manager'),
            'secondary_vendor' => __('Secondary vendors', 'backstage-venue-manager'),
            'staff' => __('Staff', 'backstage-venue-manager'),
            'other' => __('Other recipients', 'backstage-venue-manager'),
        );
    }

    private function build_cancellation_notification_group_totals(array $data): array
    {
        $stored = isset($data['group_totals']) && is_array($data['group_totals']) ? $data['group_totals'] : array();
        if (!empty($stored)) {
            $out = array();
            foreach ($stored as $group_key => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $group_key = sanitize_key((string) $group_key);
                if ($group_key === '') {
                    continue;
                }
                $out[$group_key] = array(
                    'label' => sanitize_text_field((string) ($row['label'] ?? ucfirst(str_replace('_', ' ', $group_key)))),
                    'recipient_count' => absint($row['recipient_count'] ?? 0),
                    'sent' => absint($row['sent'] ?? 0),
                    'failed' => absint($row['failed'] ?? 0),
                    'skipped' => absint($row['skipped'] ?? 0),
                );
            }
            if (!empty($out)) {
                return $out;
            }
        }

        $group_labels = $this->get_cancellation_notification_group_labels();
        $totals = array();
        foreach ($group_labels as $group_key => $label) {
            $totals[$group_key] = array(
                'label' => $label,
                'recipient_count' => 0,
                'sent' => 0,
                'failed' => 0,
                'skipped' => 0,
            );
        }

        $tally_rows = function (array $rows, string $bucket) use (&$totals) {
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $row_groups = isset($row['groups']) && is_array($row['groups'])
                    ? array_values(array_unique(array_filter(array_map('sanitize_key', $row['groups']))))
                    : array();
                if (empty($row_groups)) {
                    $fallback_group = sanitize_key((string) ($row['group'] ?? ''));
                    if ($fallback_group === '') {
                        $kind = sanitize_key((string) ($row['kind'] ?? ''));
                        if ($kind !== '' && function_exists('bvmgr_cancellation_notification_kind_group')) {
                            $fallback_group = sanitize_key((string) bvmgr_cancellation_notification_kind_group($kind));
                        }
                    }
                    if ($fallback_group !== '') {
                        $row_groups[] = $fallback_group;
                    }
                }
                foreach ($row_groups as $group_key) {
                    if ($group_key === '' || !isset($totals[$group_key])) {
                        continue;
                    }
                    $totals[$group_key][$bucket]++;
                }
            }
        };

        $tally_rows(isset($data['recipients']) && is_array($data['recipients']) ? $data['recipients'] : array(), 'recipient_count');
        $tally_rows(isset($data['sent']) && is_array($data['sent']) ? $data['sent'] : array(), 'sent');
        $tally_rows(isset($data['failed']) && is_array($data['failed']) ? $data['failed'] : array(), 'failed');
        $tally_rows(isset($data['skipped']) && is_array($data['skipped']) ? $data['skipped'] : array(), 'skipped');

        foreach ($totals as $group_key => $row) {
            if (
                absint($row['recipient_count']) === 0
                && absint($row['sent']) === 0
                && absint($row['failed']) === 0
                && absint($row['skipped']) === 0
            ) {
                unset($totals[$group_key]);
            }
        }

        return $totals;
    }

    private function format_cancellation_notification_result_row(string $status, array $row): string
    {
        $status = sanitize_key($status);
        $status_label = ucfirst($status !== '' ? $status : 'sent');
        $email = sanitize_email((string) ($row['email'] ?? ''));
        $kind = sanitize_key((string) ($row['kind'] ?? ''));
        $kind_labels = $this->get_cancellation_notification_kind_labels();
        $kind_label = sanitize_text_field((string) ($row['kind_label'] ?? ''));
        if ($kind_label === '' && $kind !== '') {
            if (function_exists('bvmgr_cancellation_notification_kind_label')) {
                $kind_label = sanitize_text_field((string) bvmgr_cancellation_notification_kind_label($kind));
            } elseif (isset($kind_labels[$kind])) {
                $kind_label = (string) $kind_labels[$kind];
            }
        }

        $display_name = trim(sanitize_text_field((string) ($row['display_name'] ?? ($row['label'] ?? ''))));
        $reason = sanitize_text_field((string) ($row['reason'] ?? ''));
        $error = sanitize_text_field((string) ($row['error'] ?? ''));

        $alias_labels = array();
        $aliases = isset($row['aliases']) && is_array($row['aliases']) ? $row['aliases'] : array();
        foreach ($aliases as $alias) {
            if (!is_array($alias)) {
                continue;
            }
            $alias_kind = sanitize_key((string) ($alias['kind'] ?? ''));
            if ($alias_kind === '' || $alias_kind === $kind) {
                continue;
            }
            $alias_label = sanitize_text_field((string) ($alias['kind_label'] ?? ''));
            if ($alias_label === '') {
                if (function_exists('bvmgr_cancellation_notification_kind_label')) {
                    $alias_label = sanitize_text_field((string) bvmgr_cancellation_notification_kind_label($alias_kind));
                } elseif (isset($kind_labels[$alias_kind])) {
                    $alias_label = (string) $kind_labels[$alias_kind];
                }
            }
            if ($alias_label !== '') {
                $alias_labels[] = $alias_label;
            }
        }
        $alias_labels = array_values(array_unique(array_filter($alias_labels, 'strlen')));
        $alias_suffix = !empty($alias_labels)
            /* translators: %s: human-readable value used in this message. */
            ? sprintf(__(' (also %s)', 'backstage-venue-manager'), implode(', ', $alias_labels))
            : '';

        if ($kind_label !== '' || $display_name !== '') {
            $subject_label = $display_name !== '' ? $display_name : ($email !== '' ? $email : __('Unknown recipient', 'backstage-venue-manager'));
            $line = sprintf(
                /* translators: 1: value 1 used in this message, 2: value 2 used in this message, 3: value 3 used in this message. */
                __('%1$s: %2$s - %3$s', 'backstage-venue-manager'),
                $status_label,
                $kind_label !== '' ? $kind_label : __('Recipient', 'backstage-venue-manager'),
                $subject_label
            );
            if ($email !== '' && strtolower($subject_label) !== strtolower($email)) {
                $line .= ' <' . $email . '>';
            }
            $line .= $alias_suffix;
            if ($status === 'failed' && $error !== '') {
                $line .= ' (' . $error . ')';
            }
            if ($status === 'skipped' && $reason !== '') {
                $line .= ' - ' . $reason;
            }
            return $line;
        }

        if ($status === 'failed') {
            /* translators: 1: value 1 used in this message, 2: value 2 used in this message. */
            return sprintf(__('Failed: %1$s (%2$s)', 'backstage-venue-manager'), $email, $error !== '' ? $error : 'send_failed');
        }
        if ($status === 'skipped') {
            if ($reason !== '' && $email !== '') {
                /* translators: 1: value 1 used in this message, 2: value 2 used in this message. */
                return sprintf(__('Skipped: %1$s (%2$s)', 'backstage-venue-manager'), $email, $reason);
            }
            if ($reason !== '') {
                /* translators: %s: skipped. */
                return sprintf(__('Skipped: %s', 'backstage-venue-manager'), $reason);
            }
            /* translators: %s: skipped. */
            return sprintf(__('Skipped: %s', 'backstage-venue-manager'), $email);
        }

        /* translators: %s: sent. */
        return sprintf(__('Sent: %s', 'backstage-venue-manager'), $email);
    }

    private function render_cancellation_step_data(string $step_key, array $data): void
    {
        if (empty($data)) {
            return;
        }

        if ($step_key === 'provider_sales_stop') {
            $updated = isset($data['updated_products']) && is_array($data['updated_products']) ? count($data['updated_products']) : 0;
            $failed = isset($data['failed_products']) && is_array($data['failed_products']) ? count($data['failed_products']) : 0;
            ?>
	            <div class="vms-cancel-job-step__meta">
	                <?php /* translators: %d: number of updated products. */ ?>
	                <span><?php echo esc_html(sprintf(__('Updated products: %d', 'backstage-venue-manager'), (int) $updated)); ?></span>
	                <?php /* translators: %d: number of failed products. */ ?>
	                <span><?php echo esc_html(sprintf(__('Failed products: %d', 'backstage-venue-manager'), (int) $failed)); ?></span>
            </div>
            <?php if (!empty($data['failed_products']) && is_array($data['failed_products'])) : ?>
                <ul class="vms-cancel-job-list">
                    <?php foreach (array_slice($data['failed_products'], 0, 8) as $row) :
                        if (!is_array($row)) continue;
                        $product_id = absint($row['product_id'] ?? 0);
                        $error = sanitize_text_field((string) ($row['error'] ?? 'unknown_error'));
                        ?>
	                        <?php /* translators: 1: product ID, 2: failure reason. */ ?>
	                        <li><?php echo esc_html(sprintf(__('Product #%1$d failed: %2$s', 'backstage-venue-manager'), $product_id, $error)); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php
            return;
        }

        if ($step_key === 'refund_discovery') {
            $candidates = isset($data['candidates']) && is_array($data['candidates']) ? $data['candidates'] : array();
            $candidate_count = isset($data['candidate_order_count']) ? absint($data['candidate_order_count']) : count($candidates);
            $eligible_count = absint($data['auto_refund_eligible_count'] ?? 0);
            $manual_review_count = absint($data['manual_review_count'] ?? 0);
            ?>
	            <div class="vms-cancel-job-step__meta">
	                <?php /* translators: %d: number of candidate orders. */ ?>
	                <span><?php echo esc_html(sprintf(__('Candidate orders: %d', 'backstage-venue-manager'), (int) $candidate_count)); ?></span>
	                <?php /* translators: %d: number of scanned orders. */ ?>
	                <span><?php echo esc_html(sprintf(__('Orders scanned: %d', 'backstage-venue-manager'), absint($data['orders_scanned'] ?? 0))); ?></span>
	                <?php /* translators: %d: number of automatically eligible orders. */ ?>
	                <span><?php echo esc_html(sprintf(__('Auto-eligible: %d', 'backstage-venue-manager'), $eligible_count)); ?></span>
	                <?php /* translators: %d: number of orders requiring manual review. */ ?>
	                <span><?php echo esc_html(sprintf(__('Manual review: %d', 'backstage-venue-manager'), $manual_review_count)); ?></span>
            </div>
            <?php if (!empty($candidates)) : ?>
                <ul class="vms-cancel-job-list">
                    <?php foreach (array_slice($candidates, 0, 12) as $cand) :
                        if (!is_array($cand)) continue;
                        $order_id = absint($cand['order_id'] ?? 0);
                        $order_no = (string) ($cand['order_number'] ?? (string) $order_id);
                        $line_items = isset($cand['line_items']) && is_array($cand['line_items']) ? $cand['line_items'] : array();
                        $line_count = count($line_items);
                        $detail_bits = array();
                        $estimated_refund_total = (float) ($cand['estimated_refund_total'] ?? 0.0);
                        if ($estimated_refund_total > 0) {
                            /* translators: %s: human-readable value used in this message. */
                            $detail_bits[] = sprintf(__('Est. refund: $%s', 'backstage-venue-manager'), number_format_i18n($estimated_refund_total, 2));
                        }
                        if (!empty($cand['auto_refund_eligible'])) {
                            $detail_bits[] = __('auto-refund eligible', 'backstage-venue-manager');
                        } else {
                            $reason = sanitize_text_field((string) ($cand['manual_review_reason'] ?? 'manual_review_required'));
                            /* translators: %s: manual review. */
                            $detail_bits[] = sprintf(__('manual review: %s', 'backstage-venue-manager'), ucwords(str_replace(array('_', '-'), ' ', $reason)));
                        }
                        ?>
                        <li>
	                            <?php /* translators: 1: order number, 2: number of order line items. */ ?>
	                            <?php echo esc_html(sprintf(__('Order #%1$s (%2$d items)', 'backstage-venue-manager'), $order_no, $line_count)); ?><?php echo !empty($detail_bits) ? esc_html(' — ' . implode(' • ', $detail_bits)) : ''; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif;
            return;
        }

        if ($step_key === 'refund_execution') {
            $queued = isset($data['queued_orders']) && is_array($data['queued_orders']) ? $data['queued_orders'] : array();
            $created = isset($data['refunds_created']) && is_array($data['refunds_created']) ? $data['refunds_created'] : array();
            $failed = isset($data['failed_orders']) && is_array($data['failed_orders']) ? $data['failed_orders'] : array();
            $skipped = isset($data['skipped_orders']) && is_array($data['skipped_orders']) ? $data['skipped_orders'] : array();
            ?>
	            <div class="vms-cancel-job-step__meta">
	                <?php /* translators: %d: number of queued refund actions. */ ?>
	                <span><?php echo esc_html(sprintf(__('Queued: %d', 'backstage-venue-manager'), count($queued))); ?></span>
	                <?php /* translators: %d: number of sent refunds. */ ?>
	                <span><?php echo esc_html(sprintf(__('Refunds sent: %d', 'backstage-venue-manager'), count($created))); ?></span>
	                <?php /* translators: %d: number of failed refund actions. */ ?>
	                <span><?php echo esc_html(sprintf(__('Failed: %d', 'backstage-venue-manager'), count($failed))); ?></span>
	                <?php /* translators: %d: number of skipped refund actions. */ ?>
	                <span><?php echo esc_html(sprintf(__('Skipped: %d', 'backstage-venue-manager'), count($skipped))); ?></span>
            </div>
            <?php if (!empty($created)) : ?>
                <ul class="vms-cancel-job-list">
                    <?php foreach (array_slice($created, 0, 12) as $row) :
                        if (!is_array($row)) continue;
                        $order_id = absint($row['order_id'] ?? 0);
                        $refund_id = absint($row['refund_id'] ?? 0);
                        $amount = number_format_i18n((float) ($row['amount'] ?? 0), 2);
                        ?>
	                        <?php /* translators: 1: order ID, 2: formatted refund amount, 3: refund ID. */ ?>
	                        <li><?php echo esc_html(sprintf(__('Order #%1$d refunded (%2$s, refund #%3$d)', 'backstage-venue-manager'), $order_id, '$' . $amount, $refund_id)); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif;
            if (!empty($queued)) : ?>
                <ul class="vms-cancel-job-list">
                    <?php foreach (array_slice($queued, 0, 12) as $row) :
                        if (!is_array($row)) continue;
                        $order_id = absint($row['order_id'] ?? 0);
                        $order_no = (string) ($row['order_number'] ?? (string) $order_id);
                        $reason = sanitize_text_field((string) ($row['reason'] ?? 'manual_review_required'));
                        ?>
	                        <?php /* translators: 1: order number, 2: manual review reason label. */ ?>
	                        <li><?php echo esc_html(sprintf(__('Queued for manual review: order #%1$s (%2$s)', 'backstage-venue-manager'), $order_no, ucwords(str_replace(array('_', '-'), ' ', $reason)))); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif;
            if (!empty($failed)) : ?>
                <ul class="vms-cancel-job-list">
                    <?php foreach (array_slice($failed, 0, 12) as $row) :
                        if (!is_array($row)) continue;
                        $order_id = absint($row['order_id'] ?? 0);
                        $error = sanitize_text_field((string) ($row['error'] ?? 'unknown_error'));
                        ?>
	                        <?php /* translators: 1: order ID, 2: failure reason. */ ?>
	                        <li><?php echo esc_html(sprintf(__('Order #%1$d failed: %2$s', 'backstage-venue-manager'), $order_id, $error)); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif;
            if (!empty($skipped)) : ?>
                <ul class="vms-cancel-job-list">
                    <?php foreach (array_slice($skipped, 0, 12) as $row) :
                        if (!is_array($row)) continue;
                        $order_id = absint($row['order_id'] ?? 0);
                        $reason = sanitize_text_field((string) ($row['reason'] ?? 'skipped'));
                        ?>
	                        <?php /* translators: 1: order ID, 2: skip reason label. */ ?>
	                        <li><?php echo esc_html(sprintf(__('Order #%1$d skipped: %2$s', 'backstage-venue-manager'), $order_id, ucwords(str_replace(array('_', '-'), ' ', $reason)))); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif;
            return;
        }

        if ($step_key === 'notifications') {
            $recipient_count = absint($data['recipient_count'] ?? 0);
            $deliverable_count = absint($data['deliverable_recipient_count'] ?? $recipient_count);
            $sent_count = absint($data['sent_count'] ?? 0);
            $failed_count = absint($data['failed_count'] ?? 0);
            $skipped_count = absint($data['skipped_count'] ?? 0);
            $group_totals = $this->build_cancellation_notification_group_totals($data);
            $skip_reasons = isset($data['skip_reasons']) && is_array($data['skip_reasons']) ? $data['skip_reasons'] : array();
            if (empty($skip_reasons) && !empty($data['skipped']) && is_array($data['skipped'])) {
                foreach ($data['skipped'] as $row) {
                    if (!is_array($row)) continue;
                    $reason = sanitize_key((string) ($row['reason'] ?? ''));
                    if ($reason === '') continue;
                    if (!isset($skip_reasons[$reason])) {
                        $skip_reasons[$reason] = 0;
                    }
                    $skip_reasons[$reason]++;
                }
            }
            $missing_email_count = absint($skip_reasons['missing_email'] ?? 0);
            ?>
	            <div class="vms-cancel-job-step__meta">
	                <?php /* translators: %d: number of discovered recipients. */ ?>
	                <span><?php echo esc_html(sprintf(__('Recipients discovered: %d', 'backstage-venue-manager'), $recipient_count)); ?></span>
	                <?php if ($deliverable_count !== $recipient_count) : ?>
	                    <?php /* translators: %d: number of deliverable recipients. */ ?>
	                    <span><?php echo esc_html(sprintf(__('Deliverable: %d', 'backstage-venue-manager'), $deliverable_count)); ?></span>
	                <?php endif; ?>
	                <?php /* translators: %d: number of sent notifications. */ ?>
	                <span><?php echo esc_html(sprintf(__('Sent: %d', 'backstage-venue-manager'), $sent_count)); ?></span>
	                <?php /* translators: %d: number of failed notifications. */ ?>
	                <span><?php echo esc_html(sprintf(__('Failed: %d', 'backstage-venue-manager'), $failed_count)); ?></span>
	                <?php /* translators: %d: number of skipped notifications. */ ?>
	                <span><?php echo esc_html(sprintf(__('Skipped: %d', 'backstage-venue-manager'), $skipped_count)); ?></span>
	                <?php if ($missing_email_count > 0) : ?>
	                    <?php /* translators: %d: number of notifications skipped for missing email addresses. */ ?>
	                    <span><?php echo esc_html(sprintf(__('Missing email skips: %d', 'backstage-venue-manager'), $missing_email_count)); ?></span>
	                <?php endif; ?>
            </div>
            <?php
            $sent = isset($data['sent']) && is_array($data['sent']) ? $data['sent'] : array();
            $failed = isset($data['failed']) && is_array($data['failed']) ? $data['failed'] : array();
            $skipped = isset($data['skipped']) && is_array($data['skipped']) ? $data['skipped'] : array();
            if (!empty($group_totals)) : ?>
                <div class="vms-cancel-job-step__meta">
                    <?php foreach ($group_totals as $group_row) :
                        if (!is_array($group_row)) continue;
                        $label = sanitize_text_field((string) ($group_row['label'] ?? ''));
                        if ($label === '') continue;
                        ?>
                        <span>
                            <?php
                            echo esc_html(sprintf(
                                /* translators: 1: value 1 used in this message, 2: number 2 used in this message, 3: number 3 used in this message, 4: number 4 used in this message. */
                                __('%1$s: sent %2$d, failed %3$d, skipped %4$d', 'backstage-venue-manager'),
                                $label,
                                absint($group_row['sent'] ?? 0),
                                absint($group_row['failed'] ?? 0),
                                absint($group_row['skipped'] ?? 0)
                            ));
                            ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif;
            if (!empty($sent)) : ?>
                <ul class="vms-cancel-job-list">
                    <?php foreach (array_slice($sent, 0, 12) as $row) :
                        if (!is_array($row)) continue;
                        ?>
                        <li><?php echo esc_html($this->format_cancellation_notification_result_row('sent', $row)); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif;
            if (!empty($failed)) : ?>
                <ul class="vms-cancel-job-list">
                    <?php foreach (array_slice($failed, 0, 12) as $row) :
                        if (!is_array($row)) continue;
                        ?>
                        <li><?php echo esc_html($this->format_cancellation_notification_result_row('failed', $row)); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif;
            if (!empty($skipped)) : ?>
                <ul class="vms-cancel-job-list">
                    <?php foreach (array_slice($skipped, 0, 12) as $row) :
                        if (!is_array($row)) continue;
                        ?>
                        <li><?php echo esc_html($this->format_cancellation_notification_result_row('skipped', $row)); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif;
            return;
        }
    }

    private function render_cancellation_job_panel(int $post_id, string $plan_status): void
    {
        $k_job_id = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'cancel_job_id') ?: '_vms_cancel_job_id') : '_vms_cancel_job_id';
        $k_job_state = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'cancel_job_state') ?: '_vms_cancel_job_state') : '_vms_cancel_job_state';
        $k_job_summary = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'cancel_job_summary') ?: '_vms_cancel_job_summary') : '_vms_cancel_job_summary';
        $k_cancel_review = function_exists('bvmgr_meta_key')
            ? (bvmgr_meta_key('event_plan', 'cancel_requires_operator_review') ?: '_vms_cancel_requires_operator_review')
            : '_vms_cancel_requires_operator_review';

        $job_id = sanitize_text_field((string) get_post_meta($post_id, $k_job_id, true));
        $job_state = sanitize_key((string) get_post_meta($post_id, $k_job_state, true));
        $summary = get_post_meta($post_id, $k_job_summary, true);
        if (!is_array($summary)) {
            $summary = array();
        }
        $steps = isset($summary['steps']) && is_array($summary['steps']) ? $summary['steps'] : array();
        $backfill_notice = '';

        // Reliability hardening: legacy cancelled plans may predate job envelopes.
        if (
            $plan_status === 'cancelled'
            && ($job_id === '' || empty($steps))
            && function_exists('bvmgr_cancellation_backfill_legacy_job')
        ) {
            $repair = (array) bvmgr_cancellation_backfill_legacy_job($post_id, array(
                'source' => 'event_plan_panel_backfill',
                'backfill_by_user_id' => get_current_user_id(),
            ));
            if (!empty($repair['ok'])) {
                $job_id = sanitize_text_field((string) ($repair['job_id'] ?? (string) get_post_meta($post_id, $k_job_id, true)));
                $job_state = sanitize_key((string) ($repair['state'] ?? (string) get_post_meta($post_id, $k_job_state, true)));
                $summary = isset($repair['summary']) && is_array($repair['summary'])
                    ? $repair['summary']
                    : get_post_meta($post_id, $k_job_summary, true);
                if (!is_array($summary)) {
                    $summary = array();
                }
                $steps = isset($summary['steps']) && is_array($summary['steps']) ? $summary['steps'] : array();
                if (!empty($repair['created'])) {
                    $backfill_notice = __('Legacy cancelled plan auto-backfilled into a safe no-op cancellation job envelope.', 'backstage-venue-manager');
                }
            }
        }

        if ($job_id === '' && empty($steps) && $plan_status !== 'cancelled') {
            return;
        }

        $job_state_labels = function_exists('bvmgr_cancellation_job_statuses')
            ? (array) bvmgr_cancellation_job_statuses()
            : array();
        $step_status_labels = function_exists('bvmgr_cancellation_step_statuses')
            ? (array) bvmgr_cancellation_step_statuses()
            : array();
        $step_labels = function_exists('bvmgr_cancellation_step_labels')
            ? (array) bvmgr_cancellation_step_labels()
            : array();

        $job_state_label = isset($job_state_labels[$job_state]) ? (string) $job_state_labels[$job_state] : strtoupper($job_state ?: 'queued');
        $policy = sanitize_key((string) ($summary['policy'] ?? ''));
        $reason = sanitize_key((string) ($summary['reason_code'] ?? ''));
        $policy_labels = function_exists('bvmgr_cancellation_policy_options') ? (array) bvmgr_cancellation_policy_options() : array();
        $reason_labels = function_exists('bvmgr_cancellation_reason_options') ? (array) bvmgr_cancellation_reason_options() : array();
        $policy_label = isset($policy_labels[$policy]) ? (string) $policy_labels[$policy] : $policy;
        $reason_label = isset($reason_labels[$reason]) ? (string) $reason_labels[$reason] : $reason;
        $created = $this->format_cancellation_gmt((string) ($summary['created_at_gmt'] ?? ''));
        $requires_review = ((string) get_post_meta($post_id, $k_cancel_review, true) === '1');
        $retry_allowed = ($plan_status === 'cancelled' && function_exists('bvmgr_cancellation_retry_step'));
        $queue_only_policies = array('stop_sales_queue_refunds');
        $auto_refund_policies = function_exists('bvmgr_cancellation_auto_refund_policies')
            ? array_values(array_unique(array_filter(array_map('sanitize_key', (array) bvmgr_cancellation_auto_refund_policies()))))
            : array('stop_sales_auto_refund', 'stop_sales_auto_refund_remove_attendees');
        $refund_capable_policies = array_values(array_unique(array_merge($queue_only_policies, $auto_refund_policies)));
        $allow_manual_live_refunds = (
            $plan_status === 'cancelled'
            && $job_id !== ''
            && $job_state !== 'running'
            && function_exists('bvmgr_cancellation_request_live_refund_run')
            && in_array($policy, $refund_capable_policies, true)
        );
        $has_retryable_steps = false;
        $has_refund_execution_retryable = false;
        foreach ($steps as $step) {
            if (!is_array($step)) {
                continue;
            }
            $step_key_scan = sanitize_key((string) ($step['key'] ?? ''));
            $step_status_scan = sanitize_key((string) ($step['status'] ?? 'pending'));
            if ($step_key_scan === '' || $step_key_scan === 'policy_capture') {
                continue;
            }
            if (in_array($step_status_scan, array('failed', 'blocked'), true)) {
                $has_retryable_steps = true;
                if ($step_key_scan === 'refund_execution') {
                    $has_refund_execution_retryable = true;
                }
            }
        }
        ?>
        <div class="vms-cancel-job-panel">
            <h4><?php esc_html_e('Cancellation Job', 'backstage-venue-manager'); ?></h4>
            <?php if ($backfill_notice !== '') : ?>
                <p class="description"><?php echo esc_html($backfill_notice); ?></p>
            <?php endif; ?>

            <?php if ($job_id === '') : ?>
                <p class="description"><?php esc_html_e('No cancellation job has been created for this Event Plan yet.', 'backstage-venue-manager'); ?></p>
            <?php else : ?>
                <div class="vms-cancel-job-summary">
                    <span class="vms-status-pill vms-cancel-job-state vms-cancel-job-state--<?php echo esc_attr($job_state ?: 'queued'); ?>">
                        <?php echo esc_html($job_state_label); ?>
                    </span>
                    <span><strong><?php esc_html_e('Job ID:', 'backstage-venue-manager'); ?></strong> <?php echo esc_html($job_id); ?></span>
                    <?php if ($policy !== '') : ?>
                        <span><strong><?php esc_html_e('Policy:', 'backstage-venue-manager'); ?></strong> <?php echo esc_html($policy_label); ?></span>
                    <?php endif; ?>
                    <?php if ($reason !== '') : ?>
                        <span><strong><?php esc_html_e('Reason:', 'backstage-venue-manager'); ?></strong> <?php echo esc_html($reason_label); ?></span>
                    <?php endif; ?>
                    <?php if ($created !== '') : ?>
                        <span><strong><?php esc_html_e('Created:', 'backstage-venue-manager'); ?></strong> <?php echo esc_html($created); ?></span>
                    <?php endif; ?>
                    <span class="<?php echo $requires_review ? 'vms-cancel-job-review is-on' : 'vms-cancel-job-review'; ?>">
                        <?php echo $requires_review ? esc_html__('Operator review required', 'backstage-venue-manager') : esc_html__('No operator review required', 'backstage-venue-manager'); ?>
                    </span>
                </div>
                <?php if ($retry_allowed && $has_retryable_steps && function_exists('bvmgr_cancellation_retry_all_failed_steps')) : ?>
                    <p class="vms-cancel-job-step__actions">
                        <button
                            type="submit"
                            name="vms_event_plan_action"
                            value="retry_cancellation_all"
                            class="button button-secondary"
                            data-vms-requires-refund-confirm="<?php echo $has_refund_execution_retryable ? '1' : '0'; ?>"
                        >
                            <?php esc_html_e('Retry All Failed/Blocked Steps', 'backstage-venue-manager'); ?>
                        </button>
                    </p>
                    <input type="hidden" name="vms_cancel_bulk_retry_confirm" id="vms_cancel_bulk_retry_confirm" value="0" />
                <?php endif; ?>

                <?php if ($allow_manual_live_refunds) : ?>
                    <?php
                    $live_refund_source_post_id = (int) $post_id;
                    if ($live_refund_source_post_id <= 0) {
                        $live_refund_source_post_id = isset($admin_get['post']) ? absint($admin_get['post']) : 0;
                    }
                    if ($live_refund_source_post_id <= 0) {
                        global $post;
                        if ($post instanceof WP_Post) {
                            $live_refund_source_post_id = (int) $post->ID;
                        }
                    }

                    $live_refund_plan_id = $live_refund_source_post_id;
                    $live_refund_source_post_type = sanitize_key((string) get_post_type($live_refund_source_post_id));
                    if ($live_refund_source_post_type !== 'vms_event_plan' && function_exists('bvmgr_get_event_plan_for_tec_event')) {
                        $resolved_live_refund_plan_id = (int) bvmgr_get_event_plan_for_tec_event($live_refund_source_post_id);
                        if ($resolved_live_refund_plan_id > 0) {
                            $live_refund_plan_id = $resolved_live_refund_plan_id;
                        }
                    }
                    if ($live_refund_plan_id <= 0) {
                        $live_refund_plan_id = (int) $post_id;
                    }
                    $live_refund_return_id = $live_refund_plan_id > 0 ? $live_refund_plan_id : (int) $post_id;
                    $live_refund_nonce_post_id = $live_refund_plan_id > 0
                        ? $live_refund_plan_id
                        : ($live_refund_source_post_id > 0 ? $live_refund_source_post_id : (int) $post_id);
                    $live_refund_url = add_query_arg(
                        array(
                            'post'                => $live_refund_return_id,
                            'action'              => 'edit',
                            'vms_live_refund_now' => '1',
                            'event_plan_id'       => $live_refund_plan_id,
                            'source_post_id'      => $live_refund_source_post_id,
                            'post_id'             => (int) $post_id,
                            '_wpnonce'            => wp_create_nonce('bvmgr_run_live_refunds_now_' . $live_refund_nonce_post_id),
                        ),
                        admin_url('post.php')
                    );
                    $live_refund_url .= '#vms-cancel-job-panel';
                    ?>
                    <p class="vms-cancel-job-step__actions">
                        <a
                            class="button button-primary"
                            id="vms_run_live_refunds_now_button"
                            href="<?php echo esc_url($live_refund_url); ?>"
                        >
                            <?php esc_html_e('Run Live Refunds Now', 'backstage-venue-manager'); ?>
                        </a>
                    </p>
                    <p class="description"><?php esc_html_e('Runs as a standalone refund action without saving this Event Plan. Re-scans the cancelled event, attempts LIVE WooCommerce gateway refunds for remaining eligible ticket orders, and skips anything unsafe into manual review. Already refunded lines are not refunded twice.', 'backstage-venue-manager'); ?></p>
                <?php endif; ?>

                <?php if (empty($steps)) : ?>
                    <p class="description"><?php esc_html_e('Job summary is present but no step data is available yet.', 'backstage-venue-manager'); ?></p>
                <?php else : ?>
                    <div class="vms-cancel-job-steps">
                        <?php foreach ($steps as $step) :
                            if (!is_array($step)) continue;
                            $step_key = sanitize_key((string) ($step['key'] ?? ''));
                            if ($step_key === '') continue;
                            $step_status = sanitize_key((string) ($step['status'] ?? 'pending'));
                            $step_status_label = isset($step_status_labels[$step_status]) ? (string) $step_status_labels[$step_status] : ucfirst($step_status ?: 'pending');
                            $step_label = isset($step_labels[$step_key]) ? (string) $step_labels[$step_key] : str_replace('_', ' ', $step_key);
                            $step_message = sanitize_text_field((string) ($step['message'] ?? ''));
                            $step_data = isset($step['data']) && is_array($step['data']) ? $step['data'] : array();
                            $updated = $this->format_cancellation_gmt((string) ($step['updated_at_gmt'] ?? ''));
                            $is_retryable = in_array($step_status, array('failed', 'blocked'), true);
                            ?>
                            <div class="vms-cancel-job-step vms-cancel-job-step--<?php echo esc_attr($step_status ?: 'pending'); ?>">
                                <div class="vms-cancel-job-step__head">
                                    <strong><?php echo esc_html($step_label); ?></strong>
                                    <span class="vms-status-pill"><?php echo esc_html($step_status_label); ?></span>
                                </div>
                                <?php if ($step_message !== '') : ?>
                                    <p class="vms-cancel-job-step__message">
                                        <code><?php echo esc_html($step_message); ?></code>
                                    </p>
                                <?php endif; ?>
                                <?php if ($updated !== '') : ?>
                                    <p class="vms-cancel-job-step__updated">
	                                        <?php /* translators: %s: formatted update timestamp. */ ?>
	                                        <?php echo esc_html(sprintf(__('Updated: %s', 'backstage-venue-manager'), $updated)); ?>
                                    </p>
                                <?php endif; ?>

                                <?php $this->render_cancellation_step_data($step_key, $step_data); ?>

                                <?php if ($retry_allowed && $is_retryable) : ?>
                                    <p class="vms-cancel-job-step__actions">
                                        <button
                                            type="submit"
                                            name="vms_event_plan_action"
                                            value="<?php echo esc_attr('retry_cancellation_step:' . $step_key); ?>"
                                            class="button button-secondary button-small"
                                        >
                                            <?php esc_html_e('Retry Step', 'backstage-venue-manager'); ?>
                                        </button>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }


    private function get_event_plan_partial_path(string $partial): string
    {
        $partial = trim(str_replace(array('..', '\\'), '', $partial));
        if ($partial === '') {
            return '';
        }

        return __DIR__ . '/event-plans/partials/' . $partial . '.php';
    }

    private function prepare_event_plan_partial_vars(array $vars): array
    {
        unset($vars['this']);
        return $vars;
    }

    private function get_event_plan_staff_render_context(int $post_id, array $staff_assignments): array
    {
        $trace = function_exists('bvmgr_event_plan_perf_span_start')
            ? bvmgr_event_plan_perf_span_start('event_plan_staff_render_context', $post_id, array('section' => 'staffing'))
            : '';

        try {
        $staff_roles = get_terms(array(
            'taxonomy'   => 'vms_staff_role',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ));

        $staff_slots = function_exists('bvmgr_staffing_get_event_slots')
            ? (array) bvmgr_staffing_get_event_slots($post_id, true)
            : array();
        $staff_slot_by_role = array();
        if (is_array($staff_slots)) {
            foreach ($staff_slots as $slot_row) {
                if (!is_array($slot_row)) continue;
                $rid = isset($slot_row['role_id']) ? absint($slot_row['role_id']) : 0;
                if ($rid <= 0) continue;
                $status = isset($slot_row['status']) ? sanitize_key((string) $slot_row['status']) : 'active';
                if (!isset($staff_slot_by_role[$rid])) {
                    $staff_slot_by_role[$rid] = $slot_row;
                } elseif ($status === 'active') {
                    $staff_slot_by_role[$rid] = $slot_row;
                }
            }
        }

        $staff_role_meta_map = function_exists('bvmgr_staffing_role_map_by_id') ? (array) bvmgr_staffing_role_map_by_id(true) : array();

        $staff_posts = get_posts(array(
            'post_type'      => 'vms_staff',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'all',
        ));

        $staff_posts_by_id = array();
        if (is_array($staff_posts)) {
            foreach ($staff_posts as $sp) {
                $sid = is_object($sp) && isset($sp->ID) ? (int) $sp->ID : 0;
                if ($sid > 0) {
                    $staff_posts_by_id[$sid] = $sp;
                }
            }
        }

        $staff_assigned_by_role = function_exists('bvmgr_staffing_get_event_assigned_staff_map')
            ? (array) bvmgr_staffing_get_event_assigned_staff_map($post_id)
            : array();
        $staff_by_role = array();
        $staff_eligible_counts_by_role = array();
        if (is_array($staff_roles) && !is_wp_error($staff_roles) && is_array($staff_posts)) {
            foreach ($staff_roles as $role_term) {
                $role_id = is_object($role_term) && isset($role_term->term_id) ? (int) $role_term->term_id : 0;
                if ($role_id <= 0) {
                    continue;
                }

                $eligible_staff = array();
                foreach ($staff_posts as $sp) {
                    $sid = is_object($sp) && isset($sp->ID) ? (int) $sp->ID : 0;
                    if ($sid <= 0) {
                        continue;
                    }

                    $candidate_status = function_exists('bvmgr_staffing_staff_candidate_status_for_role')
                        ? (array) bvmgr_staffing_staff_candidate_status_for_role($sid, $role_id)
                        : array('eligible' => false);
                    if (empty($candidate_status['eligible'])) {
                        continue;
                    }
                    $eligible_staff[$sid] = $sp;
                }

                $staff_eligible_counts_by_role[$role_id] = count($eligible_staff);

                $display_staff = $eligible_staff;
                $assigned_staff_ids = isset($staff_assigned_by_role[$role_id]) && is_array($staff_assigned_by_role[$role_id])
                    ? array_values(array_unique(array_filter(array_map('absint', $staff_assigned_by_role[$role_id]))))
                    : array();
                foreach ($assigned_staff_ids as $assigned_staff_id) {
                    if ($assigned_staff_id <= 0 || isset($display_staff[$assigned_staff_id]) || !isset($staff_posts_by_id[$assigned_staff_id])) {
                        continue;
                    }
                    $display_staff[$assigned_staff_id] = $staff_posts_by_id[$assigned_staff_id];
                }

                $staff_by_role[$role_id] = array_values($display_staff);
            }
        }

        $render_tax_badge = function (int $sid): string {
            $missing = function_exists('bvmgr_vendor_tax_profile_missing_items') ? (array) bvmgr_vendor_tax_profile_missing_items($sid) : array();
            $tax_ok = empty($missing);

            $bypass_active = false;
            $bypass_until = '';
            if (function_exists('bvmgr_get_tax_bypass_status')) {
                $b = (array) bvmgr_get_tax_bypass_status($sid);
                $bypass_active = !empty($b['is_active']);
                $bypass_until = isset($b['until']) ? (string) $b['until'] : '';
            }

            if ($tax_ok) {
                return '<span class="vms-ep-tax-badge vms-ep-tax-badge--ok" aria-label="Tax profile ok">T✓</span>';
            }

            if ($bypass_active) {
                $until_txt = $bypass_until !== '' ? esc_html($bypass_until) : esc_html__('(date unknown)', 'backstage-venue-manager');
                /* translators: %s: date or time value. */
                return '<span class="vms-ep-tax-badge vms-ep-tax-badge--bypass" aria-label="Tax profile bypass active">TB</span><span class="vms-ep-tax-badge-note">' . sprintf(esc_html__('until %s', 'backstage-venue-manager'), $until_txt) . '</span>';
            }

            return '<span class="vms-ep-tax-badge vms-ep-tax-badge--missing" aria-label="Tax profile missing items">T⚠</span>';
        };

        $vms_staff_has_data = false;
        foreach ($staff_slot_by_role as $slot_row) {
            if (!is_array($slot_row)) continue;
            $need = isset($slot_row['headcount_needed']) ? max(0, (int) $slot_row['headcount_needed']) : 0;
            $assignments_list = isset($slot_row['assignments']) && is_array($slot_row['assignments']) ? $slot_row['assignments'] : array();
            if ($need > 0 || !empty($assignments_list)) {
                $vms_staff_has_data = true;
                break;
            }
        }
        if (!$vms_staff_has_data && is_array($staff_assignments)) {
            foreach ($staff_assignments as $arr) {
                if (is_array($arr) && !empty(array_filter(array_map('absint', $arr), function ($v) {
                    return $v > 0;
                }))) {
                    $vms_staff_has_data = true;
                    break;
                }
            }
        }

        $staff_activation_thresholds = function_exists('bvmgr_staffing_get_event_role_activation_thresholds')
            ? (array) bvmgr_staffing_get_event_role_activation_thresholds($post_id)
            : array();
        $staff_headcount_context = function_exists('bvmgr_staffing_get_event_plan_headcount_context')
            ? (array) bvmgr_staffing_get_event_plan_headcount_context($post_id)
            : array('wired' => false, 'headcount' => 0, 'label' => __('Attendance not wired yet', 'backstage-venue-manager'));
        $staff_headcount_wired = !empty($staff_headcount_context['wired']);
        $staff_current_headcount = max(0, (int) ($staff_headcount_context['headcount'] ?? 0));
        $staff_headcount_label = isset($staff_headcount_context['label']) ? (string) $staff_headcount_context['label'] : __('Attendance not wired yet', 'backstage-venue-manager');
        $staffing_templates = function_exists('bvmgr_staffing_get_templates')
            ? (array) bvmgr_staffing_get_templates(array('is_active' => 1))
            : array();
        $staff_applied_template_id = function_exists('bvmgr_staffing_get_event_applied_template_id')
            ? (int) bvmgr_staffing_get_event_applied_template_id($post_id)
            : 0;
        $staff_applied_template = ($staff_applied_template_id > 0 && function_exists('bvmgr_staffing_get_template'))
            ? bvmgr_staffing_get_template($staff_applied_template_id)
            : null;
        $staff_recommended_template = function_exists('bvmgr_staffing_get_recommended_template_for_event_plan')
            ? bvmgr_staffing_get_recommended_template_for_event_plan($post_id)
            : null;

        return array(
            'staff_roles' => $staff_roles,
            'staff_slots' => $staff_slots,
            'staff_slot_by_role' => $staff_slot_by_role,
            'staff_role_meta_map' => $staff_role_meta_map,
            'staff_posts' => $staff_posts,
            'staff_by_role' => $staff_by_role,
            'staff_eligible_counts_by_role' => $staff_eligible_counts_by_role,
            'render_tax_badge' => $render_tax_badge,
            'vms_staff_has_data' => $vms_staff_has_data,
            'staff_activation_thresholds' => $staff_activation_thresholds,
            'staff_headcount_context' => $staff_headcount_context,
            'staff_headcount_wired' => $staff_headcount_wired,
            'staff_current_headcount' => $staff_current_headcount,
            'staff_headcount_label' => $staff_headcount_label,
            'staffing_templates' => $staffing_templates,
            'staff_applied_template_id' => $staff_applied_template_id,
            'staff_applied_template' => $staff_applied_template,
            'staff_recommended_template' => $staff_recommended_template,
        );
        } finally {
            if (function_exists('bvmgr_event_plan_perf_span_finish')) {
                bvmgr_event_plan_perf_span_finish('event_plan_staff_render_context', $post_id, $trace, array('section' => 'staffing'));
            }
        }
    }

    private function build_event_plan_staff_response_payload(int $post_id, array $staff_assignments): array
    {
        $render_context = $this->get_event_plan_staff_render_context($post_id, $staff_assignments);
        $staff_context = $this->build_event_plan_staff_response_context($render_context, $staff_assignments);

        return array(
            'staff_context' => $staff_context,
            'html' => $this->render_event_plan_staff_response_html($staff_context),
        );
    }

    private function build_event_plan_staff_response_context(array $render_context, array $staff_assignments): array
    {
        $staff_roles = isset($render_context['staff_roles']) && is_array($render_context['staff_roles']) && !is_wp_error($render_context['staff_roles'])
            ? $render_context['staff_roles']
            : array();
        $staff_slot_by_role = isset($render_context['staff_slot_by_role']) && is_array($render_context['staff_slot_by_role'])
            ? $render_context['staff_slot_by_role']
            : array();
        $staff_role_meta_map = isset($render_context['staff_role_meta_map']) && is_array($render_context['staff_role_meta_map'])
            ? $render_context['staff_role_meta_map']
            : array();
        $staff_by_role = isset($render_context['staff_by_role']) && is_array($render_context['staff_by_role'])
            ? $render_context['staff_by_role']
            : array();
        $staff_eligible_counts_by_role = isset($render_context['staff_eligible_counts_by_role']) && is_array($render_context['staff_eligible_counts_by_role'])
            ? $render_context['staff_eligible_counts_by_role']
            : array();
        $staff_activation_thresholds = isset($render_context['staff_activation_thresholds']) && is_array($render_context['staff_activation_thresholds'])
            ? $render_context['staff_activation_thresholds']
            : array();
        $staff_headcount_wired = !empty($render_context['staff_headcount_wired']);
        $staff_current_headcount = max(0, (int) ($render_context['staff_current_headcount'] ?? 0));
        $staff_headcount_label = isset($render_context['staff_headcount_label']) ? (string) $render_context['staff_headcount_label'] : __('Attendance not wired yet', 'backstage-venue-manager');
        $staffing_templates = isset($render_context['staffing_templates']) && is_array($render_context['staffing_templates'])
            ? $render_context['staffing_templates']
            : array();
        $staff_applied_template_id = max(0, (int) ($render_context['staff_applied_template_id'] ?? 0));
        $staff_applied_template = isset($render_context['staff_applied_template']) && is_array($render_context['staff_applied_template'])
            ? $render_context['staff_applied_template']
            : null;
        $staff_recommended_template = isset($render_context['staff_recommended_template']) && is_array($render_context['staff_recommended_template'])
            ? $render_context['staff_recommended_template']
            : null;
        $vms_staff_has_data = !empty($render_context['vms_staff_has_data']);

        $applied_name = (is_array($staff_applied_template) && !empty($staff_applied_template['name']))
            ? (string) $staff_applied_template['name']
            : __('None recorded', 'backstage-venue-manager');
        $recommended_name = (is_array($staff_recommended_template) && !empty($staff_recommended_template['name']))
            ? (string) $staff_recommended_template['name']
            : __('No match', 'backstage-venue-manager');
        $headcount_summary_text = $staff_headcount_wired
            ? sprintf(
                /* translators: %1$d: anticipated guests. */
                __('Anticipated guests: %1$d. Staffing highlights below update against this number.', 'backstage-venue-manager'),
                $staff_current_headcount,
                $staff_headcount_label
            )
            : __('Anticipated guest count is not available yet. Staffing highlights will appear once ticket sales or guest entries are available.', 'backstage-venue-manager');

        $role_rows = $this->build_event_plan_staff_response_role_rows(
            $staff_roles,
            $staff_slot_by_role,
            $staff_role_meta_map,
            $staff_by_role,
            $staff_eligible_counts_by_role,
            $staff_activation_thresholds,
            $staff_headcount_wired,
            $staff_current_headcount,
            $vms_staff_has_data,
            $staff_applied_template_id,
            $staff_assignments
        );

        return array(
            'has_data' => $vms_staff_has_data,
            'headcount_wired' => $staff_headcount_wired,
            'headcount_summary_text' => $headcount_summary_text,
            'current_headcount' => $staff_current_headcount,
            'headcount_label' => $staff_headcount_label,
            'template_alerts' => $this->build_event_plan_staff_response_template_alerts(
                $staff_roles,
                $staff_activation_thresholds,
                $staff_headcount_wired,
                $staff_current_headcount,
                $staff_applied_template,
                $staff_recommended_template,
                $staff_applied_template_id
            ),
            'applied_template_summary' => sprintf(
                /* translators: 1: value 1 used in this message, 2: value 2 used in this message. */
                __('Applied: %1$s · Recommended now: %2$s', 'backstage-venue-manager'),
                $applied_name,
                $recommended_name
            ),
            'applied_template_id' => $staff_applied_template_id,
            'template_options' => $this->build_event_plan_staff_response_template_option_rows($staffing_templates),
            'has_roles' => !empty($role_rows),
            'role_rows' => $role_rows,
        );
    }

    private function build_event_plan_staff_response_template_alerts(
        array $staff_roles,
        array $staff_activation_thresholds,
        bool $staff_headcount_wired,
        int $staff_current_headcount,
        ?array $staff_applied_template,
        ?array $staff_recommended_template,
        int $staff_applied_template_id
    ): array {
        $alerts = array();
        $applied_template_band_min = (is_array($staff_applied_template) && isset($staff_applied_template['min_headcount']) && $staff_applied_template['min_headcount'] !== null && $staff_applied_template['min_headcount'] !== '')
            ? max(0, (int) $staff_applied_template['min_headcount'])
            : null;
        $applied_template_band_max = (is_array($staff_applied_template) && isset($staff_applied_template['max_headcount']) && $staff_applied_template['max_headcount'] !== null && $staff_applied_template['max_headcount'] !== '')
            ? max(0, (int) $staff_applied_template['max_headcount'])
            : null;

        if ($staff_headcount_wired && is_array($staff_applied_template) && $applied_template_band_max !== null && $staff_current_headcount > $applied_template_band_max) {
            $alerts[] = sprintf(
                /* translators: 1: number 1 used in this message, 2: number 2 used in this message. */
                __('Anticipated guests (%1$d) are above the applied template ceiling of %2$d. Review staffing now.', 'backstage-venue-manager'),
                $staff_current_headcount,
                $applied_template_band_max
            );
        }

        if ($staff_headcount_wired && is_array($staff_applied_template) && $applied_template_band_min !== null && $staff_current_headcount < $applied_template_band_min) {
            $alerts[] = sprintf(
                /* translators: 1: number 1 used in this message, 2: number 2 used in this message. */
                __('Anticipated guests (%1$d) are below the applied template floor of %2$d.', 'backstage-venue-manager'),
                $staff_current_headcount,
                $applied_template_band_min
            );
        }

        if (
            $staff_headcount_wired
            && is_array($staff_recommended_template)
            && !empty($staff_recommended_template['template_id'])
            && (int) $staff_recommended_template['template_id'] > 0
            && (int) $staff_recommended_template['template_id'] !== $staff_applied_template_id
        ) {
            $alerts[] = sprintf(
                /* translators: %s: current guest count fits a different staffing template. */
                __('Current guest count fits a different staffing template: %s.', 'backstage-venue-manager'),
                isset($staff_recommended_template['name']) ? (string) $staff_recommended_template['name'] : __('Recommended template', 'backstage-venue-manager')
            );
        }

        $next_threshold_gap = null;
        $next_threshold_role = '';
        foreach ($staff_roles as $role) {
            if (!is_object($role) || empty($role->term_id)) {
                continue;
            }

            $role_id = (int) $role->term_id;
            $threshold = array_key_exists($role_id, $staff_activation_thresholds)
                ? max(0, (int) $staff_activation_thresholds[$role_id])
                : 0;
            if ($threshold <= 0 || $threshold <= $staff_current_headcount) {
                continue;
            }

            $gap = $threshold - $staff_current_headcount;
            if ($next_threshold_gap === null || $gap < $next_threshold_gap) {
                $next_threshold_gap = $gap;
                $next_threshold_role = isset($role->name) ? (string) $role->name : '';
            }
        }

        if ($staff_headcount_wired && $next_threshold_gap !== null && $next_threshold_gap <= 10) {
            $alerts[] = sprintf(
                /* translators: %1$d: number of guests remaining before the next staffing trigger, %2$s: optional staffing role suffix for that trigger. */ __('This event is %1$d away from the next staffing trigger%2$s.', 'backstage-venue-manager'),
                $next_threshold_gap,
                $next_threshold_role !== '' ? sprintf(/* translators: %s: staffing role name for the next trigger. */ __(' for %s', 'backstage-venue-manager'), $next_threshold_role) : ''
            );
        }

        return $alerts;
    }

    private function build_event_plan_staff_response_template_option_rows(array $staffing_templates): array
    {
        $rows = array();
        foreach ($staffing_templates as $template_row) {
            if (!is_array($template_row)) {
                continue;
            }

            $template_id = isset($template_row['template_id']) ? absint($template_row['template_id']) : 0;
            if ($template_id <= 0) {
                continue;
            }

            $label_parts = array();
            $label_parts[] = isset($template_row['name']) ? (string) $template_row['name'] : ('#' . $template_id);
            if (
                (isset($template_row['min_headcount']) && $template_row['min_headcount'] !== null && $template_row['min_headcount'] !== '')
                || (isset($template_row['max_headcount']) && $template_row['max_headcount'] !== null && $template_row['max_headcount'] !== '')
            ) {
                $label_parts[] = sprintf(
                    /* translators: %1$s: lower guest-count boundary, %2$s: upper guest-count boundary. */ __('guests %1$s-%2$s', 'backstage-venue-manager'),
                    (isset($template_row['min_headcount']) && $template_row['min_headcount'] !== null && $template_row['min_headcount'] !== '')
                        ? (int) $template_row['min_headcount']
                        : 0,
                    (isset($template_row['max_headcount']) && $template_row['max_headcount'] !== null && $template_row['max_headcount'] !== '')
                        ? (int) $template_row['max_headcount']
                        : '∞'
                );
            }

            $rows[] = array(
                'template_id' => $template_id,
                'label' => implode(' · ', $label_parts),
            );
        }

        return $rows;
    }

    private function build_event_plan_staff_response_role_rows(
        array $staff_roles,
        array $staff_slot_by_role,
        array $staff_role_meta_map,
        array $staff_by_role,
        array $staff_eligible_counts_by_role,
        array $staff_activation_thresholds,
        bool $staff_headcount_wired,
        int $staff_current_headcount,
        bool $vms_staff_has_data,
        int $staff_applied_template_id,
        array $staff_assignments
    ): array {
        $rows = array();
        $previous_duration_minutes = '';

        foreach ($staff_roles as $role) {
            if (!is_object($role) || empty($role->term_id)) {
                continue;
            }

            $role_id = (int) $role->term_id;
            if ($role_id <= 0) {
                continue;
            }

            $role_name = isset($role->name) ? (string) $role->name : '';
            $role_meta = isset($staff_role_meta_map[$role_id]) && is_array($staff_role_meta_map[$role_id])
                ? $staff_role_meta_map[$role_id]
                : array();
            $slot_row = isset($staff_slot_by_role[$role_id]) && is_array($staff_slot_by_role[$role_id])
                ? $staff_slot_by_role[$role_id]
                : array();

            $assigned_ids = array();
            if (!empty($slot_row['assignments']) && is_array($slot_row['assignments'])) {
                foreach ($slot_row['assignments'] as $assignment_row) {
                    if (!is_array($assignment_row)) {
                        continue;
                    }

                    $assignment_status = isset($assignment_row['status']) ? sanitize_key((string) $assignment_row['status']) : '';
                    if (!in_array($assignment_status, array('proposed', 'confirmed'), true)) {
                        continue;
                    }

                    $staff_id = isset($assignment_row['staff_id']) ? absint($assignment_row['staff_id']) : 0;
                    if ($staff_id > 0) {
                        $assigned_ids[] = $staff_id;
                    }
                }
            } elseif (isset($staff_assignments[$role_id]) && is_array($staff_assignments[$role_id])) {
                $assigned_ids = array_map('intval', $staff_assignments[$role_id]);
            }

            $assigned_ids = array_values(array_unique(array_filter($assigned_ids, static function ($value): bool {
                return $value > 0;
            })));

            $default_headcount = isset($role_meta['default_headcount']) ? max(1, (int) $role_meta['default_headcount']) : 1;
            $use_role_default_headcount = empty($slot_row) && !$vms_staff_has_data && $staff_applied_template_id <= 0;
            $headcount = isset($slot_row['headcount_needed'])
                ? max(0, (int) $slot_row['headcount_needed'])
                : ($use_role_default_headcount ? $default_headcount : 0);
            $time_mode = isset($slot_row['shift_time_mode']) ? sanitize_key((string) $slot_row['shift_time_mode']) : 'absolute';
            if (!in_array($time_mode, array('absolute', 'relative'), true)) {
                $time_mode = 'absolute';
            }

            $shift_start = isset($slot_row['shift_start_local']) ? (string) $slot_row['shift_start_local'] : '';
            $shift_end = isset($slot_row['shift_end_local']) ? (string) $slot_row['shift_end_local'] : '';
            $duration_minutes = isset($slot_row['duration_minutes']) && $slot_row['duration_minutes'] !== null
                ? (int) $slot_row['duration_minutes']
                : '';
            $filled = count($assigned_ids);
            $open = max(0, $headcount - $filled);
            $is_critical = !empty($role_meta['is_critical']);
            $role_in_use = ($headcount > 0 || $filled > 0);
            $activation_threshold = array_key_exists($role_id, $staff_activation_thresholds)
                ? max(0, (int) $staff_activation_thresholds[$role_id])
                : ($role_in_use ? 1 : 0);
            $threshold_met = $staff_headcount_wired && ($staff_current_headcount >= $activation_threshold);
            $required_now = ($headcount > 0) && $threshold_met;
            // Preserve the legacy partial's warning calculation order for this lazy-load response.
            $absolute_time_missing = $role_in_use && $time_mode === 'absolute' && ($shift_start === '' || ($shift_end === '' && (int) $previous_duration_minutes <= 0));
            $missing_staff_now = $required_now && ($filled < $headcount);

            $role_card_classes = array('vms-ep-staff-role');
            if ($required_now) {
                $role_card_classes[] = 'is-required-now';
            }
            if ($absolute_time_missing || $missing_staff_now) {
                $role_card_classes[] = 'has-inline-warning';
            }
            if ($missing_staff_now) {
                $role_card_classes[] = 'has-required-gap';
            }
            if ($role_in_use && !$required_now && $staff_headcount_wired && $activation_threshold > 0) {
                $role_card_classes[] = 'is-waiting-threshold';
            }

            if (!$role_in_use) {
                $state_pill = __('Not set', 'backstage-venue-manager');
                $state_class = 'is-inactive';
            } elseif (!$staff_headcount_wired) {
                $state_pill = __('Guests pending', 'backstage-venue-manager');
                $state_class = 'is-unwired';
            } elseif ($required_now) {
                $state_pill = __('Needed now', 'backstage-venue-manager');
                $state_class = 'is-required';
            } elseif ($activation_threshold <= 0) {
                $state_pill = __('Always needed', 'backstage-venue-manager');
                $state_class = 'is-active';
            } else {
                $state_pill = sprintf(
                    /* translators: %d: number used in this message. */
                    __('Needed at %d+ guests', 'backstage-venue-manager'),
                    $activation_threshold
                );
                $state_class = 'is-waiting';
            }

            if (!$role_in_use) {
                $threshold_copy = __('Set staff needed and the guest trigger for when this role should become needed.', 'backstage-venue-manager');
            } elseif (!$staff_headcount_wired) {
                $threshold_copy = sprintf(
                    /* translators: %d: number used in this message. */
                    __('Guest count is not available yet. This role will become needed at %d guests once sales or guest entries are available.', 'backstage-venue-manager'),
                    $activation_threshold
                );
            } elseif ($required_now) {
                $threshold_copy = sprintf(
                    /* translators: 1: number 1 used in this message, 2: number 2 used in this message. */
                    __('This role is needed now based on %1$d anticipated guests. It turns on at %2$d guests.', 'backstage-venue-manager'),
                    $staff_current_headcount,
                    $activation_threshold
                );
            } elseif ($activation_threshold <= 0) {
                $threshold_copy = sprintf(
                    __('This role is needed as soon as guest counts are available.', 'backstage-venue-manager'),
                    $staff_current_headcount
                );
            } else {
                $threshold_copy = sprintf(
                    /* translators: 1: number 1 used in this message, 2: number 2 used in this message. */
                    __('This role becomes needed at %2$d anticipated guests. Current guest count: %1$d.', 'backstage-venue-manager'),
                    $staff_current_headcount,
                    $activation_threshold
                );
            }

            $qualification_summary_parts = array();
            $required_qualification_rules = !empty($role_meta['required_qualification_rules']) && is_array($role_meta['required_qualification_rules'])
                ? $role_meta['required_qualification_rules']
                : array();
            foreach ($required_qualification_rules as $qualification_rule) {
                if (!is_array($qualification_rule) || empty($qualification_rule['name'])) {
                    continue;
                }

                $qualification_summary_parts[] = sprintf(
                    /* translators: 1: value 1 used in this message, 2: value 2 used in this message. */
                    __('%1$s (%2$s)', 'backstage-venue-manager'),
                    (string) $qualification_rule['name'],
                    function_exists('bvmgr_staffing_admin_qualification_mode_label')
                        ? bvmgr_staffing_admin_qualification_mode_label((string) ($qualification_rule['mode'] ?? 'warn'))
                        : (string) ($qualification_rule['mode'] ?? 'warn')
                );
            }

            $role_staff = isset($staff_by_role[$role_id]) && is_array($staff_by_role[$role_id]) ? $staff_by_role[$role_id] : array();
            $role_eligible_count = isset($staff_eligible_counts_by_role[$role_id]) ? max(0, (int) $staff_eligible_counts_by_role[$role_id]) : 0;
            $rows[] = array(
                'role_id' => $role_id,
                'role_name' => $role_name,
                'is_critical' => $is_critical,
                'card_class_name' => implode(' ', $role_card_classes),
                'state_pill' => $state_pill,
                'state_class' => $state_class,
                'headcount' => $headcount,
                'filled' => $filled,
                'open' => $open,
                'base_summary' => sprintf(
                    /* translators: 1: number 1 used in this message, 2: number 2 used in this message, 3: number 3 used in this message, 4: value 4 used in this message. */
                    __('Need %1$d · Filled %2$d · Open %3$d%4$s', 'backstage-venue-manager'),
                    $headcount,
                    $filled,
                    $open,
                    $is_critical ? ' · ' . __('Critical', 'backstage-venue-manager') : ''
                ),
                'activation_threshold' => $activation_threshold,
                'time_mode' => $time_mode,
                'shift_start' => $shift_start,
                'shift_end' => $shift_end,
                'start_anchor_key' => isset($slot_row['start_anchor_key']) ? (string) $slot_row['start_anchor_key'] : 'event_start',
                'end_anchor_key' => isset($slot_row['end_anchor_key']) ? (string) $slot_row['end_anchor_key'] : 'event_end',
                'start_offset_minutes' => isset($slot_row['start_offset_minutes']) ? (int) $slot_row['start_offset_minutes'] : 0,
                'end_offset_minutes' => isset($slot_row['end_offset_minutes']) ? (int) $slot_row['end_offset_minutes'] : 0,
                'duration_minutes' => $duration_minutes,
                'threshold_copy' => $threshold_copy,
                'qualification_summary' => !empty($qualification_summary_parts)
                    ? sprintf(
                        /* translators: %s: comma-separated required qualification names. */
                        __('Required qualifications: %s.', 'backstage-venue-manager'),
                        implode(', ', $qualification_summary_parts)
                    )
                    : '',
                'absolute_time_missing' => $absolute_time_missing,
                'missing_staff_now' => $missing_staff_now,
                'role_eligible_count' => $role_eligible_count,
                'no_eligible_staff_text' => sprintf(
                    /* translators: %s: human-readable value used in this message. */
                    __('No %s-eligible staff found.', 'backstage-venue-manager'),
                    strtolower($role_name)
                ),
                'show_assigned_ineligible_copy' => ($role_eligible_count <= 0 && !empty($role_staff)),
                'candidate_rows' => $this->build_event_plan_staff_response_candidate_rows(
                    $role_id,
                    $assigned_ids,
                    $role_staff,
                    $required_qualification_rules
                ),
            );

            $previous_duration_minutes = $duration_minutes;
        }

        return $rows;
    }

    private function build_event_plan_staff_response_candidate_rows(
        int $role_id,
        array $assigned_ids,
        array $role_staff,
        array $required_qualification_rules
    ): array {
        $rows = array();
        $has_required_qualification_rules = !empty($required_qualification_rules);

        foreach ($role_staff as $staff_post) {
            $staff_id = is_object($staff_post) && isset($staff_post->ID) ? (int) $staff_post->ID : 0;
            if ($staff_id <= 0) {
                continue;
            }

            $checked = in_array($staff_id, $assigned_ids, true);
            $candidate_status = function_exists('bvmgr_staffing_staff_candidate_status_for_role')
                ? (array) bvmgr_staffing_staff_candidate_status_for_role($staff_id, $role_id)
                : array(
                    'eligible' => true,
                    'qualification' => array('ok' => true, 'mode' => 'warn', 'missing' => array(), 'expired' => array()),
                    'ineligibility_reason' => '',
                );
            $role_eligible = !empty($candidate_status['eligible']);
            $eligibility_reason = isset($candidate_status['ineligibility_reason']) ? (string) $candidate_status['ineligibility_reason'] : '';
            $qualification_check = isset($candidate_status['qualification']) && is_array($candidate_status['qualification'])
                ? $candidate_status['qualification']
                : array('ok' => true, 'mode' => 'warn', 'missing' => array(), 'expired' => array());
            $qualification_ok = !empty($qualification_check['ok']);
            $qualification_mode = isset($qualification_check['mode']) ? (string) $qualification_check['mode'] : 'warn';
            $qualification_disabled = (!$qualification_ok && $qualification_mode === 'hard_block' && !$checked);
            $qualification_parts = array();

            if (!empty($qualification_check['missing'])) {
                $qualification_parts[] = sprintf(
                    /* translators: %s: human-readable value used in this message. */
                    __('missing %s', 'backstage-venue-manager'),
                    implode(', ', array_map('strval', (array) $qualification_check['missing']))
                );
            }

            if (!empty($qualification_check['expired'])) {
                $qualification_parts[] = sprintf(
                    /* translators: %s: human-readable value used in this message. */
                    __('expired %s', 'backstage-venue-manager'),
                    implode(', ', array_map('strval', (array) $qualification_check['expired']))
                );
            }

            $badge_rows = $this->build_event_plan_staff_response_tax_badge_rows($staff_id);
            if (!$role_eligible && $checked) {
                $badge_rows[] = array(
                    'kind' => 'badge',
                    'class_name' => 'vms-ep-tax-badge vms-ep-tax-badge--missing',
                    'text' => __('Role⚠', 'backstage-venue-manager'),
                    'aria_label' => '',
                );
                if ($eligibility_reason !== '') {
                    $badge_rows[] = array(
                        'kind' => 'note',
                        'class_name' => 'vms-ep-tax-badge-note',
                        'text' => $eligibility_reason,
                        'aria_label' => '',
                    );
                }
            }

            if (!$qualification_ok) {
                $badge_rows[] = array(
                    'kind' => 'badge',
                    'class_name' => 'vms-ep-tax-badge ' . ($qualification_disabled ? 'vms-ep-tax-badge--missing' : 'vms-ep-tax-badge--bypass'),
                    'text' => $qualification_disabled ? 'Q✕' : 'Q⚠',
                    'aria_label' => '',
                );
                $badge_rows[] = array(
                    'kind' => 'note',
                    'class_name' => 'vms-ep-tax-badge-note',
                    'text' => implode('; ', $qualification_parts),
                    'aria_label' => '',
                );
            } elseif ($has_required_qualification_rules) {
                $badge_rows[] = array(
                    'kind' => 'badge',
                    'class_name' => 'vms-ep-tax-badge vms-ep-tax-badge--ok',
                    'text' => 'Q✓',
                    'aria_label' => '',
                );
            }

            $rows[] = array(
                'staff_id' => $staff_id,
                'title' => function_exists('get_the_title') ? (string) get_the_title($staff_id) : (is_object($staff_post) && isset($staff_post->post_title) ? (string) $staff_post->post_title : ''),
                'checked' => $checked,
                'disabled' => $qualification_disabled,
                'badge_rows' => $badge_rows,
            );
        }

        return $rows;
    }

    private function build_event_plan_staff_response_tax_badge_rows(int $staff_id): array
    {
        $missing_items = function_exists('bvmgr_vendor_tax_profile_missing_items')
            ? (array) bvmgr_vendor_tax_profile_missing_items($staff_id)
            : array();
        if (empty($missing_items)) {
            return array(
                array(
                    'kind' => 'badge',
                    'class_name' => 'vms-ep-tax-badge vms-ep-tax-badge--ok',
                    'text' => 'T✓',
                    'aria_label' => 'Tax profile ok',
                ),
            );
        }

        $bypass_active = false;
        $bypass_until = '';
        if (function_exists('bvmgr_get_tax_bypass_status')) {
            $bypass_status = (array) bvmgr_get_tax_bypass_status($staff_id);
            $bypass_active = !empty($bypass_status['is_active']);
            $bypass_until = isset($bypass_status['until']) ? (string) $bypass_status['until'] : '';
        }

        if ($bypass_active) {
            return array(
                array(
                    'kind' => 'badge',
                    'class_name' => 'vms-ep-tax-badge vms-ep-tax-badge--bypass',
                    'text' => 'TB',
                    'aria_label' => 'Tax profile bypass active',
                ),
                array(
                    'kind' => 'note',
                    'class_name' => 'vms-ep-tax-badge-note',
                    'text' => sprintf(
                        /* translators: %s: date or time value. */
                        __('until %s', 'backstage-venue-manager'),
                        $bypass_until !== '' ? $bypass_until : __('(date unknown)', 'backstage-venue-manager')
                    ),
                    'aria_label' => '',
                ),
            );
        }

        return array(
            array(
                'kind' => 'badge',
                'class_name' => 'vms-ep-tax-badge vms-ep-tax-badge--missing',
                'text' => 'T⚠',
                'aria_label' => 'Tax profile missing items',
            ),
        );
    }

    private function render_event_plan_staff_response_html(array $staff_context): string
    {
        $has_data = !empty($staff_context['has_data']);
        $headcount_wired = !empty($staff_context['headcount_wired']);
        $headcount_summary_text = isset($staff_context['headcount_summary_text']) ? (string) $staff_context['headcount_summary_text'] : '';
        $template_alerts = isset($staff_context['template_alerts']) && is_array($staff_context['template_alerts'])
            ? $staff_context['template_alerts']
            : array();
        $applied_template_summary = isset($staff_context['applied_template_summary']) ? (string) $staff_context['applied_template_summary'] : '';
        $applied_template_id = max(0, (int) ($staff_context['applied_template_id'] ?? 0));
        $template_options = isset($staff_context['template_options']) && is_array($staff_context['template_options'])
            ? $staff_context['template_options']
            : array();
        $role_rows = isset($staff_context['role_rows']) && is_array($staff_context['role_rows'])
            ? $staff_context['role_rows']
            : array();

        ob_start();
        ?>
        <div class="vms-ep-card vms-ep-card--white vms-ep-card--staff" data-vms-section-has-data="<?php echo $has_data ? '1' : '0'; ?>">
            <p class="description"><?php esc_html_e('Structured staffing by role: set staff needed and shift windows, then assign staff. Missing staff is based only on roles with Staff needed above 0.', 'backstage-venue-manager'); ?></p>
            <p class="description vms-ep-staff-headcount-summary <?php echo $headcount_wired ? '' : 'is-muted'; ?>" id="vms-ep-staff-headcount-summary"><?php echo esc_html($headcount_summary_text); ?></p>
            <?php echo $this->render_event_plan_staff_response_template_alert_notice_html($template_alerts); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <div class="vms-ep-inline-card vms-mb-12">
                <strong><?php esc_html_e('Staffing template', 'backstage-venue-manager'); ?></strong>
                <p class="description vms-m0"><?php echo esc_html($applied_template_summary); ?></p>
                <p class="vms-m0 vms-mt-8">
                    <label>
                        <?php esc_html_e('Template', 'backstage-venue-manager'); ?>
                        <select name="vms_staffing_template_id">
                            <option value="0"><?php esc_html_e('Select staffing template', 'backstage-venue-manager'); ?></option>
                            <?php echo $this->render_event_plan_staff_response_template_option_rows_html($template_options, $applied_template_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </select>
                    </label>
                    <label class="vms-ml-8">
                        <?php esc_html_e('Mode', 'backstage-venue-manager'); ?>
                        <select name="vms_staffing_template_mode">
                            <option value="merge_missing"><?php esc_html_e('Merge missing roles only', 'backstage-venue-manager'); ?></option>
                            <option value="replace_all"><?php esc_html_e('Replace staffing from template', 'backstage-venue-manager'); ?></option>
                        </select>
                    </label>
                    <button type="submit" class="button" name="vms_staffing_template_apply" value="1"><?php esc_html_e('Apply selected template', 'backstage-venue-manager'); ?></button>
                </p>
                <p class="description vms-m0"><?php esc_html_e('Use this when the event was not created from Schedule or when current guest count points to a different staffing package.', 'backstage-venue-manager'); ?></p>
            </div>

            <input type="hidden" name="vms_staff_assignments_present" value="1" />
            <input type="hidden" name="vms_staffing_roles_present" value="1" />

            <div
                class="vms-ep-staff-wrap"
                data-vms-staff-wrap="1"
                data-vms-current-headcount="<?php echo esc_attr((string) absint($staff_context['current_headcount'] ?? 0)); ?>"
                data-vms-headcount-wired="<?php echo $headcount_wired ? '1' : '0'; ?>"
                data-vms-headcount-label="<?php echo esc_attr((string) ($staff_context['headcount_label'] ?? '')); ?>"
            >
                <?php if (empty($role_rows)) : ?>
                    <p class="description"><?php esc_html_e('No staff roles are configured yet. Create roles in Staff Roles first.', 'backstage-venue-manager'); ?></p>
                <?php else : ?>
                    <?php echo $this->render_event_plan_staff_response_role_cards_html($role_rows); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php endif; ?>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private function render_event_plan_staff_response_template_alert_notice_html(array $template_alerts): string
    {
        $alert_texts = array_values(array_filter(array_map('strval', $template_alerts), static function (string $alert_text): bool {
            return trim($alert_text) !== '';
        }));
        if (empty($alert_texts)) {
            return '';
        }

        ob_start();
        ?>
        <div class="notice notice-warning inline">
            <p><strong><?php esc_html_e('Staffing alert:', 'backstage-venue-manager'); ?></strong></p>
            <ul style="margin:0 0 0 18px;">
                <?php foreach ($alert_texts as $alert_text) : ?>
                    <li><?php echo esc_html($alert_text); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private function render_event_plan_staff_response_template_option_rows_html(array $template_options, int $selected_template_id): string
    {
        ob_start();
        foreach ($template_options as $template_option) {
            if (!is_array($template_option)) {
                continue;
            }

            $template_id = isset($template_option['template_id']) ? absint($template_option['template_id']) : 0;
            if ($template_id <= 0) {
                continue;
            }
            ?>
            <option value="<?php echo esc_attr((string) $template_id); ?>"<?php echo $this->render_event_plan_staff_response_selected_attr($selected_template_id === $template_id); ?>><?php echo esc_html((string) ($template_option['label'] ?? '')); ?></option>
            <?php
        }

        return (string) ob_get_clean();
    }

    private function render_event_plan_staff_response_role_cards_html(array $role_rows): string
    {
        ob_start();
        foreach ($role_rows as $role_row) {
            if (!is_array($role_row)) {
                continue;
            }

            echo $this->render_event_plan_staff_response_role_card_html($role_row); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        return (string) ob_get_clean();
    }

    private function render_event_plan_staff_response_role_card_html(array $role_row): string
    {
        $role_id = isset($role_row['role_id']) ? absint($role_row['role_id']) : 0;
        if ($role_id <= 0) {
            return '';
        }

        $role_name = isset($role_row['role_name']) ? (string) $role_row['role_name'] : '';
        $candidate_rows = isset($role_row['candidate_rows']) && is_array($role_row['candidate_rows'])
            ? $role_row['candidate_rows']
            : array();

        ob_start();
        ?>
        <div
            class="<?php echo esc_attr((string) ($role_row['card_class_name'] ?? 'vms-ep-staff-role')); ?>"
            data-vms-staff-role="1"
            data-role-id="<?php echo esc_attr((string) $role_id); ?>"
            data-role-name="<?php echo esc_attr($role_name); ?>"
            data-role-critical="<?php echo !empty($role_row['is_critical']) ? '1' : '0'; ?>"
        >
            <div class="vms-ep-staff-role__head">
                <div class="vms-ep-staff-role__head-copy">
                    <strong><?php echo esc_html($role_name); ?></strong>
                    <span class="description" data-vms-role-base-summary><?php echo esc_html((string) ($role_row['base_summary'] ?? '')); ?></span>
                </div>
                <span class="vms-ep-staff-role__state <?php echo esc_attr('vms-ep-staff-role__state--' . (string) ($role_row['state_class'] ?? 'is-inactive')); ?>" data-vms-role-state-pill><?php echo esc_html((string) ($role_row['state_pill'] ?? '')); ?></span>
            </div>

            <p class="vms-m0 vms-mb-8 vms-ep-staff-role__controls">
                <label>
                    <?php esc_html_e('Staff needed', 'backstage-venue-manager'); ?>
                    <input type="number" min="0" step="1" name="vms_staff_role_headcount[<?php echo esc_attr((string) $role_id); ?>]" value="<?php echo esc_attr((string) absint($role_row['headcount'] ?? 0)); ?>" data-vms-role-headcount-input="1">
                </label>
                <label>
                    <?php esc_html_e('Activate at attendance', 'backstage-venue-manager'); ?>
                    <input type="number" min="0" step="1" name="vms_staff_role_activation_threshold[<?php echo esc_attr((string) $role_id); ?>]" value="<?php echo esc_attr((string) absint($role_row['activation_threshold'] ?? 0)); ?>" data-vms-role-threshold-input="1">
                </label>
                <label>
                    <?php esc_html_e('Time mode', 'backstage-venue-manager'); ?>
                    <select name="vms_staff_role_time_mode[<?php echo esc_attr((string) $role_id); ?>]" data-vms-role-time-mode-input="1">
                        <option value="absolute"<?php echo $this->render_event_plan_staff_response_selected_attr((string) ($role_row['time_mode'] ?? 'absolute') === 'absolute'); ?>><?php esc_html_e('Absolute', 'backstage-venue-manager'); ?></option>
                        <option value="relative"<?php echo $this->render_event_plan_staff_response_selected_attr((string) ($role_row['time_mode'] ?? 'absolute') === 'relative'); ?>><?php esc_html_e('Relative', 'backstage-venue-manager'); ?></option>
                    </select>
                </label>
                <label data-vms-role-absolute-field="1">
                    <?php esc_html_e('Shift start', 'backstage-venue-manager'); ?>
                    <input type="time" name="vms_staff_role_shift_start[<?php echo esc_attr((string) $role_id); ?>]" value="<?php echo esc_attr((string) ($role_row['shift_start'] ?? '')); ?>" data-vms-role-shift-start-input="1">
                </label>
                <label data-vms-role-absolute-field="1" data-vms-role-end-field="1">
                    <?php esc_html_e('Shift end', 'backstage-venue-manager'); ?>
                    <input type="time" name="vms_staff_role_shift_end[<?php echo esc_attr((string) $role_id); ?>]" value="<?php echo esc_attr((string) ($role_row['shift_end'] ?? '')); ?>" data-vms-role-shift-end-input="1">
                </label>
                <label data-vms-role-relative-field="1">
                    <?php esc_html_e('Start anchor', 'backstage-venue-manager'); ?>
                    <select name="vms_staff_role_start_anchor[<?php echo esc_attr((string) $role_id); ?>]" data-vms-role-start-anchor-input="1">
                        <?php echo $this->render_event_plan_staff_response_anchor_option_rows_html((string) ($role_row['start_anchor_key'] ?? 'event_start')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </select>
                </label>
                <label data-vms-role-relative-field="1">
                    <?php esc_html_e('Start offset (min)', 'backstage-venue-manager'); ?>
                    <input type="number" step="1" name="vms_staff_role_start_offset[<?php echo esc_attr((string) $role_id); ?>]" value="<?php echo esc_attr((string) ((int) ($role_row['start_offset_minutes'] ?? 0))); ?>" data-vms-role-start-offset-input="1">
                </label>
                <label data-vms-role-relative-field="1" data-vms-role-end-field="1">
                    <?php esc_html_e('End anchor', 'backstage-venue-manager'); ?>
                    <select name="vms_staff_role_end_anchor[<?php echo esc_attr((string) $role_id); ?>]" data-vms-role-end-anchor-input="1">
                        <?php echo $this->render_event_plan_staff_response_anchor_option_rows_html((string) ($role_row['end_anchor_key'] ?? 'event_end')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </select>
                </label>
                <label data-vms-role-relative-field="1" data-vms-role-end-field="1">
                    <?php esc_html_e('End offset (min)', 'backstage-venue-manager'); ?>
                    <input type="number" step="1" name="vms_staff_role_end_offset[<?php echo esc_attr((string) $role_id); ?>]" value="<?php echo esc_attr((string) ((int) ($role_row['end_offset_minutes'] ?? 0))); ?>" data-vms-role-end-offset-input="1">
                </label>
                <label data-vms-role-duration-field="1">
                    <?php esc_html_e('Duration (min)', 'backstage-venue-manager'); ?>
                    <input type="number" min="0" step="1" name="vms_staff_role_duration_minutes[<?php echo esc_attr((string) $role_id); ?>]" value="<?php echo esc_attr((string) ($role_row['duration_minutes'] ?? '')); ?>" data-vms-role-duration-input="1">
                </label>
            </p>
            <p class="description vms-m0" data-vms-role-threshold-copy><?php echo esc_html((string) ($role_row['threshold_copy'] ?? '')); ?></p>
            <?php if ((string) ($role_row['qualification_summary'] ?? '') !== '') : ?>
                <p class="description vms-m0"><?php echo esc_html((string) $role_row['qualification_summary']); ?></p>
            <?php endif; ?>
            <p class="description vms-m0"><?php esc_html_e('Absolute mode uses Shift start plus Shift end or Duration. Relative mode uses start anchor/offset plus End anchor/offset or Duration.', 'backstage-venue-manager'); ?></p>
            <div class="vms-ep-inline-warning <?php echo !empty($role_row['absolute_time_missing']) ? '' : 'vms-hidden'; ?>" data-vms-role-absolute-warning>
                <?php esc_html_e('Absolute time mode requires Shift start plus Shift end or Duration when this role is in use.', 'backstage-venue-manager'); ?>
            </div>
            <div class="vms-ep-inline-warning vms-ep-inline-warning--required <?php echo !empty($role_row['missing_staff_now']) ? '' : 'vms-hidden'; ?>" data-vms-role-required-warning>
                <?php esc_html_e('Current guest count has reached this role\'s trigger. Assign staff until Filled reaches Staff needed.', 'backstage-venue-manager'); ?>
            </div>

            <?php if ((int) ($role_row['role_eligible_count'] ?? 0) <= 0) : ?>
                <p class="description vms-m0"><?php echo esc_html((string) ($role_row['no_eligible_staff_text'] ?? '')); ?></p>
                <?php if (!empty($role_row['show_assigned_ineligible_copy'])) : ?>
                    <p class="description vms-m0"><?php esc_html_e('Currently assigned but now-ineligible staff are shown below so this plan does not silently lose them.', 'backstage-venue-manager'); ?></p>
                <?php endif; ?>
            <?php endif; ?>
            <?php if (empty($candidate_rows)) : ?>
                <p class="description vms-m0"><?php esc_html_e('No staff candidates are available for this role yet.', 'backstage-venue-manager'); ?></p>
            <?php else : ?>
                <div class="vms-ep-check-grid" role="group" aria-label="<?php echo esc_attr($role_name); ?>">
                    <?php echo $this->render_event_plan_staff_response_candidate_rows_html($role_id, $candidate_rows); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
                <p class="description vms-m0"><?php esc_html_e('Tax status: T✓ ok, T⚠ missing, TB bypass active. Assigned staff default to Proposed status in staffing rollups.', 'backstage-venue-manager'); ?></p>
            <?php endif; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private function render_event_plan_staff_response_anchor_option_rows_html(string $selected_key): string
    {
        $anchor_options = array(
            'event_start' => __('Event start', 'backstage-venue-manager'),
            'event_end' => __('Event end', 'backstage-venue-manager'),
            'a1' => __('Anchor 1', 'backstage-venue-manager'),
            'a2' => __('Anchor 2', 'backstage-venue-manager'),
            'a3' => __('Anchor 3', 'backstage-venue-manager'),
            'a4' => __('Anchor 4', 'backstage-venue-manager'),
        );

        ob_start();
        foreach ($anchor_options as $anchor_key => $anchor_label) {
            ?>
            <option value="<?php echo esc_attr($anchor_key); ?>"<?php echo $this->render_event_plan_staff_response_selected_attr($selected_key === $anchor_key); ?>><?php echo esc_html($anchor_label); ?></option>
            <?php
        }

        return (string) ob_get_clean();
    }

    private function render_event_plan_staff_response_candidate_rows_html(int $role_id, array $candidate_rows): string
    {
        ob_start();
        foreach ($candidate_rows as $candidate_row) {
            if (!is_array($candidate_row)) {
                continue;
            }

            $staff_id = isset($candidate_row['staff_id']) ? absint($candidate_row['staff_id']) : 0;
            if ($staff_id <= 0) {
                continue;
            }
            ?>
            <label class="vms-ep-check">
                <input type="checkbox" name="vms_staff_assignments[<?php echo esc_attr((string) $role_id); ?>][]" value="<?php echo esc_attr((string) $staff_id); ?>"<?php echo $this->render_event_plan_staff_response_checked_attr(!empty($candidate_row['checked'])); ?><?php echo $this->render_event_plan_staff_response_disabled_attr(!empty($candidate_row['disabled'])); ?> data-vms-role-assignment-input="1" />
                <span class="vms-ep-check__label"><?php echo esc_html((string) ($candidate_row['title'] ?? '')); ?></span>
                <?php echo $this->render_event_plan_staff_response_badges_html(isset($candidate_row['badge_rows']) && is_array($candidate_row['badge_rows']) ? $candidate_row['badge_rows'] : array()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </label>
            <?php
        }

        return (string) ob_get_clean();
    }

    private function render_event_plan_staff_response_badges_html(array $badge_rows): string
    {
        ob_start();
        foreach ($badge_rows as $badge_row) {
            if (!is_array($badge_row)) {
                continue;
            }

            $text = isset($badge_row['text']) ? (string) $badge_row['text'] : '';
            if ($text === '') {
                continue;
            }

            $kind = isset($badge_row['kind']) ? (string) $badge_row['kind'] : 'badge';
            $class_name = isset($badge_row['class_name']) ? (string) $badge_row['class_name'] : '';
            if ($kind === 'note') {
                ?>
                <span class="<?php echo esc_attr($class_name !== '' ? $class_name : 'vms-ep-tax-badge-note'); ?>"><?php echo esc_html($text); ?></span>
                <?php
                continue;
            }
            ?>
            <span class="<?php echo esc_attr($class_name !== '' ? $class_name : 'vms-ep-tax-badge'); ?>"<?php if ((string) ($badge_row['aria_label'] ?? '') !== '') : ?> aria-label="<?php echo esc_attr((string) $badge_row['aria_label']); ?>"<?php endif; ?>><?php echo esc_html($text); ?></span>
            <?php
        }

        return (string) ob_get_clean();
    }

    private function render_event_plan_staff_response_selected_attr(bool $selected): string
    {
        return $selected ? ' selected="selected"' : '';
    }

    private function render_event_plan_staff_response_checked_attr(bool $checked): string
    {
        return $checked ? ' checked="checked"' : '';
    }

    private function render_event_plan_staff_response_disabled_attr(bool $disabled): string
    {
        return $disabled ? ' disabled="disabled"' : '';
    }

    private function get_event_plan_compensation_render_context(
        int $post_id,
        array $default,
        array $actual,
        string $comp_structure,
        $flat_fee_amount,
        $door_split_percent,
        string $attendance_bonus_mode,
        $attendance_bonus_start_count,
        $attendance_bonus_step_size,
        $attendance_bonus_step_bonus,
        $attendance_bonus_per_ticket_rate,
        $attendance_bonus_max_bonus,
        $commission_percent,
        string $commission_mode,
        array $comp_opts,
        int $selected_band_id,
        int $venue_id_effective,
        string $event_date
    ): array {
        $trace = function_exists('bvmgr_event_plan_perf_span_start')
            ? bvmgr_event_plan_perf_span_start('event_plan_compensation_render_context', $post_id, array('section' => 'compensation'))
            : '';

        try {
        $vms_norm_num = static function ($value): string {
            if ($value === '' || $value === null) {
                return '';
            }

            if (is_string($value)) {
                $value = trim($value);
                if ($value === '') {
                    return '';
                }
            }

            return is_numeric($value) ? number_format((float) $value, 4, '.', '') : (string) $value;
        };

        $ack = (string) get_post_meta($post_id, '_vms_pay_override_ack', true);
        $ack_ts = (int) get_post_meta($post_id, '_vms_pay_override_ack_ts', true);
        $ack_user = (int) get_post_meta($post_id, '_vms_pay_override_ack_user_id', true);
        $ack_default_snapshot = get_post_meta($post_id, '_vms_pay_override_ack_default_snapshot', true);
        $ack_actual_snapshot = get_post_meta($post_id, '_vms_pay_override_ack_actual_snapshot', true);

        $ack_checked = false;
        $ack_still_valid = false;
        if ($ack === '1' && is_array($ack_default_snapshot) && is_array($ack_actual_snapshot)) {
            $ack_still_valid = (
                (string) ($ack_default_snapshot['structure'] ?? '') === (string) ($default['structure'] ?? '')
                && $vms_norm_num($ack_default_snapshot['flat_fee_amount'] ?? '') === $vms_norm_num($default['flat_fee_amount'] ?? '')
                && $vms_norm_num($ack_default_snapshot['door_split_percent'] ?? '') === $vms_norm_num($default['door_split_percent'] ?? '')
                && (string) ($ack_default_snapshot['attendance_bonus_mode'] ?? '') === (string) ($default['attendance_bonus_mode'] ?? '')
                && $vms_norm_num($ack_default_snapshot['attendance_bonus_start_count'] ?? '') === $vms_norm_num($default['attendance_bonus_start_count'] ?? '')
                && $vms_norm_num($ack_default_snapshot['attendance_bonus_step_size'] ?? '') === $vms_norm_num($default['attendance_bonus_step_size'] ?? '')
                && $vms_norm_num($ack_default_snapshot['attendance_bonus_step_bonus'] ?? '') === $vms_norm_num($default['attendance_bonus_step_bonus'] ?? '')
                && $vms_norm_num($ack_default_snapshot['attendance_bonus_per_ticket_rate'] ?? '') === $vms_norm_num($default['attendance_bonus_per_ticket_rate'] ?? '')
                && $vms_norm_num($ack_default_snapshot['attendance_bonus_max_bonus'] ?? '') === $vms_norm_num($default['attendance_bonus_max_bonus'] ?? '')
                && (string) ($ack_actual_snapshot['structure'] ?? '') === (string) ($actual['structure'] ?? '')
                && $vms_norm_num($ack_actual_snapshot['flat_fee_amount'] ?? '') === $vms_norm_num($actual['flat_fee_amount'] ?? '')
                && $vms_norm_num($ack_actual_snapshot['door_split_percent'] ?? '') === $vms_norm_num($actual['door_split_percent'] ?? '')
                && (string) ($ack_actual_snapshot['attendance_bonus_mode'] ?? '') === (string) ($actual['attendance_bonus_mode'] ?? '')
                && $vms_norm_num($ack_actual_snapshot['attendance_bonus_start_count'] ?? '') === $vms_norm_num($actual['attendance_bonus_start_count'] ?? '')
                && $vms_norm_num($ack_actual_snapshot['attendance_bonus_step_size'] ?? '') === $vms_norm_num($actual['attendance_bonus_step_size'] ?? '')
                && $vms_norm_num($ack_actual_snapshot['attendance_bonus_step_bonus'] ?? '') === $vms_norm_num($actual['attendance_bonus_step_bonus'] ?? '')
                && $vms_norm_num($ack_actual_snapshot['attendance_bonus_per_ticket_rate'] ?? '') === $vms_norm_num($actual['attendance_bonus_per_ticket_rate'] ?? '')
                && $vms_norm_num($ack_actual_snapshot['attendance_bonus_max_bonus'] ?? '') === $vms_norm_num($actual['attendance_bonus_max_bonus'] ?? '')
            );
            $ack_checked = $ack_still_valid;
        }

        $vms_fmt_money = static function ($n) {
            return '$' . number_format_i18n((float) $n, 2);
        };

        $vms_flat_num = is_numeric($flat_fee_amount) ? (float) $flat_fee_amount : 0.0;
        if ($vms_flat_num < 0) {
            $vms_flat_num = 0.0;
        }

        $vms_struct_guarantee_map = array(
            'flat_fee' => $vms_flat_num,
            'door_split' => 0.0,
            'flat_fee_door_split' => $vms_flat_num,
            'attendance_bonus' => $vms_flat_num,
        );

        $vms_struct_guarantee_max = max($vms_struct_guarantee_map);
        $vms_selected_guarantee = ($comp_structure === 'door_split') ? 0.0 : (float) $vms_flat_num;

        $vms_guarantee_max = isset($comp_opts['max_guarantee']) ? (float) $comp_opts['max_guarantee'] : 0.0;
        if ($vms_guarantee_max < 0) {
            $vms_guarantee_max = 0.0;
        }

        $vms_requires_low_guarantee_ack = ($vms_guarantee_max > 0 && $vms_selected_guarantee < $vms_guarantee_max);

        $k_low_ack = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'low_guarantee_ack') : '_vms_low_guarantee_ack';
        $k_low_ack_ts = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'low_guarantee_ack_ts') : '_vms_low_guarantee_ack_ts';
        $k_low_ack_user = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'low_guarantee_ack_user_id') : '_vms_low_guarantee_ack_user_id';
        $k_low_ack_snapshot = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'low_guarantee_ack_snapshot') : '_vms_low_guarantee_ack_snapshot';

        $vms_low_ack = (string) get_post_meta($post_id, $k_low_ack, true);
        $vms_low_ack_ts = (int) get_post_meta($post_id, $k_low_ack_ts, true);
        $vms_low_ack_user = (int) get_post_meta($post_id, $k_low_ack_user, true);
        $vms_low_ack_snapshot = get_post_meta($post_id, $k_low_ack_snapshot, true);

        $vms_low_ack_checked = false;
        $vms_low_ack_still_valid = false;
        if ($vms_low_ack === '1' && is_array($vms_low_ack_snapshot)) {
            $vms_low_ack_still_valid = (
                (string) ($vms_low_ack_snapshot['structure'] ?? '') === (string) ($comp_structure ?? '')
                && $vms_norm_num($vms_low_ack_snapshot['flat_fee_amount'] ?? '') === $vms_norm_num($flat_fee_amount)
                && $vms_norm_num($vms_low_ack_snapshot['door_split_percent'] ?? '') === $vms_norm_num($door_split_percent)
                && (string) ($vms_low_ack_snapshot['attendance_bonus_mode'] ?? '') === (string) ($attendance_bonus_mode ?? '')
                && $vms_norm_num($vms_low_ack_snapshot['attendance_bonus_start_count'] ?? '') === $vms_norm_num($attendance_bonus_start_count)
                && $vms_norm_num($vms_low_ack_snapshot['attendance_bonus_step_size'] ?? '') === $vms_norm_num($attendance_bonus_step_size)
                && $vms_norm_num($vms_low_ack_snapshot['attendance_bonus_step_bonus'] ?? '') === $vms_norm_num($attendance_bonus_step_bonus)
                && $vms_norm_num($vms_low_ack_snapshot['attendance_bonus_per_ticket_rate'] ?? '') === $vms_norm_num($attendance_bonus_per_ticket_rate)
                && $vms_norm_num($vms_low_ack_snapshot['attendance_bonus_max_bonus'] ?? '') === $vms_norm_num($attendance_bonus_max_bonus)
                && $vms_norm_num($vms_low_ack_snapshot['selected_guarantee'] ?? '') === $vms_norm_num($vms_selected_guarantee)
                && $vms_norm_num($vms_low_ack_snapshot['max_guarantee'] ?? '') === $vms_norm_num($vms_guarantee_max)
            );
            $vms_low_ack_checked = ($vms_requires_low_guarantee_ack && $vms_low_ack_still_valid);
        }

        $vms_combined_ack_checked = ($ack_checked || $vms_low_ack_checked);

        $vms_comp_terms_for_compare = static function (array $terms) use ($vms_norm_num): array {
            return array(
                'structure' => isset($terms['structure']) ? (string) $terms['structure'] : '',
                'flat_fee_amount' => $vms_norm_num($terms['flat_fee_amount'] ?? ''),
                'door_split_percent' => $vms_norm_num($terms['door_split_percent'] ?? ''),
                'attendance_bonus_mode' => isset($terms['attendance_bonus_mode']) ? (string) $terms['attendance_bonus_mode'] : '',
                'attendance_bonus_start_count' => $vms_norm_num($terms['attendance_bonus_start_count'] ?? ''),
                'attendance_bonus_step_size' => $vms_norm_num($terms['attendance_bonus_step_size'] ?? ''),
                'attendance_bonus_step_bonus' => $vms_norm_num($terms['attendance_bonus_step_bonus'] ?? ''),
                'attendance_bonus_per_ticket_rate' => $vms_norm_num($terms['attendance_bonus_per_ticket_rate'] ?? ''),
                'attendance_bonus_max_bonus' => $vms_norm_num($terms['attendance_bonus_max_bonus'] ?? ''),
                'commission_percent' => $vms_norm_num($terms['commission_percent'] ?? ''),
                'commission_mode' => isset($terms['commission_mode']) ? (string) $terms['commission_mode'] : '',
            );
        };

        $vms_actual_terms = array_merge($actual, array(
            'commission_percent' => $commission_percent,
            'commission_mode' => $commission_mode,
        ));

        $vms_vendor_default_tile = (isset($comp_opts['defaults']['vendor']) && is_array($comp_opts['defaults']['vendor']))
            ? $comp_opts['defaults']['vendor']
            : array();
        $vms_vendor_default_terms = (!empty($vms_vendor_default_tile['enabled']) && !empty($vms_vendor_default_tile['terms']) && is_array($vms_vendor_default_tile['terms']))
            ? $vms_vendor_default_tile['terms']
            : array();
        $vms_vendor_default_has_terms = !empty($vms_vendor_default_terms);
        $vms_vendor_default_matches_draft = false;
        if ($vms_vendor_default_has_terms) {
            $vms_vendor_default_matches_draft = ($vms_comp_terms_for_compare($vms_vendor_default_terms) === $vms_comp_terms_for_compare($vms_actual_terms));
        }
        $vms_show_vendor_default_drift_notice = ($vms_vendor_default_has_terms && !$vms_vendor_default_matches_draft);
        $vms_vendor_default_subtitle = isset($vms_vendor_default_tile['subtitle']) ? trim((string) $vms_vendor_default_tile['subtitle']) : '';
        $vms_vendor_default_summary = function_exists('bvmgr_snapshot_summary_line')
            ? (string) bvmgr_snapshot_summary_line($vms_vendor_default_terms)
            : '';
        $vms_actual_draft_summary = function_exists('bvmgr_snapshot_summary_line')
            ? (string) bvmgr_snapshot_summary_line($vms_actual_terms)
            : '';

        $vms_format_comp_field_value = static function (string $field, array $terms) use ($vms_norm_num): string {
            $raw = $terms[$field] ?? '';
            if ($raw === '' || $raw === null) {
                return '—';
            }

            switch ($field) {
                case 'structure':
                    return function_exists('bvmgr_pretty_structure_label') ? (string) bvmgr_pretty_structure_label((string) $raw) : (string) $raw;
                case 'door_split_percent':
                case 'commission_percent':
                    return rtrim(rtrim((string) $vms_norm_num($raw), '0'), '.') . '%';
                case 'attendance_bonus_mode':
                    return match ((string) $raw) {
                        'step' => __('Step', 'backstage-venue-manager'),
                        'continuous' => __('Continuous', 'backstage-venue-manager'),
                        default => (string) $raw,
                    };
                case 'commission_mode':
                    return ((string) $raw === 'gross')
                        ? __('Based on gross / settlement', 'backstage-venue-manager')
                        : __('Added on top of vendor pay', 'backstage-venue-manager');
                case 'flat_fee_amount':
                case 'attendance_bonus_step_bonus':
                case 'attendance_bonus_per_ticket_rate':
                case 'attendance_bonus_max_bonus':
                    return '$' . number_format_i18n((float) $raw, 2);
                default:
                    return (string) $raw;
            }
        };

        $vms_vendor_default_source_rows = array();
        if ($selected_band_id > 0) {
            $vms_vendor_global_terms = array();
            if (function_exists('bvmgr_get_vendor_default_comp_package_id') && function_exists('bvmgr_get_comp_package_terms')) {
                $vms_vendor_package_id = (int) bvmgr_get_vendor_default_comp_package_id($selected_band_id);
                if ($vms_vendor_package_id > 0) {
                    $vms_vendor_global_terms = array_merge($vms_vendor_global_terms, (array) bvmgr_get_comp_package_terms($vms_vendor_package_id));
                    $vms_package_pct = function_exists('bvmgr_normalize_agent_fee_percent') ? bvmgr_normalize_agent_fee_percent(get_post_meta($vms_vendor_package_id, '_vms_commission_percent', true)) : null;
                    $vms_package_mode = function_exists('bvmgr_normalize_agent_fee_mode') ? bvmgr_normalize_agent_fee_mode(get_post_meta($vms_vendor_package_id, '_vms_commission_mode', true)) : '';
                    if ($vms_package_pct !== null && $vms_package_pct > 0) {
                        $vms_vendor_global_terms['commission_percent'] = $vms_package_pct;
                        $vms_vendor_global_terms['commission_mode'] = ($vms_package_mode !== '') ? $vms_package_mode : 'artist_fee';
                    }
                }
            }

            $vms_vendor_structure_key = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('vendor', 'default_comp_structure') ?: '_vms_default_comp_structure') : '_vms_default_comp_structure';
            $vms_vendor_flat_key = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('vendor', 'default_flat_fee_amount') ?: '_vms_default_flat_fee_amount') : '_vms_default_flat_fee_amount';
            $vms_vendor_split_key = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('vendor', 'default_door_split_percent') ?: '_vms_default_door_split_percent') : '_vms_default_door_split_percent';
            $vms_vendor_bonus_mode_key = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('vendor', 'default_attendance_bonus_mode') ?: '_vms_default_attendance_bonus_mode') : '_vms_default_attendance_bonus_mode';
            $vms_vendor_bonus_start_key = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('vendor', 'default_attendance_bonus_start_count') ?: '_vms_default_attendance_bonus_start_count') : '_vms_default_attendance_bonus_start_count';
            $vms_vendor_bonus_step_size_key = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('vendor', 'default_attendance_bonus_step_size') ?: '_vms_default_attendance_bonus_step_size') : '_vms_default_attendance_bonus_step_size';
            $vms_vendor_bonus_step_bonus_key = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('vendor', 'default_attendance_bonus_step_bonus') ?: '_vms_default_attendance_bonus_step_bonus') : '_vms_default_attendance_bonus_step_bonus';
            $vms_vendor_bonus_per_ticket_key = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('vendor', 'default_attendance_bonus_per_ticket_rate') ?: '_vms_default_attendance_bonus_per_ticket_rate') : '_vms_default_attendance_bonus_per_ticket_rate';
            $vms_vendor_bonus_max_key = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('vendor', 'default_attendance_bonus_max_bonus') ?: '_vms_default_attendance_bonus_max_bonus') : '_vms_default_attendance_bonus_max_bonus';

            $vms_vendor_global_meta_terms = array(
                'structure' => (string) get_post_meta($selected_band_id, $vms_vendor_structure_key, true),
                'flat_fee_amount' => get_post_meta($selected_band_id, $vms_vendor_flat_key, true),
                'door_split_percent' => get_post_meta($selected_band_id, $vms_vendor_split_key, true),
                'attendance_bonus_mode' => (string) get_post_meta($selected_band_id, $vms_vendor_bonus_mode_key, true),
                'attendance_bonus_start_count' => get_post_meta($selected_band_id, $vms_vendor_bonus_start_key, true),
                'attendance_bonus_step_size' => get_post_meta($selected_band_id, $vms_vendor_bonus_step_size_key, true),
                'attendance_bonus_step_bonus' => get_post_meta($selected_band_id, $vms_vendor_bonus_step_bonus_key, true),
                'attendance_bonus_per_ticket_rate' => get_post_meta($selected_band_id, $vms_vendor_bonus_per_ticket_key, true),
                'attendance_bonus_max_bonus' => get_post_meta($selected_band_id, $vms_vendor_bonus_max_key, true),
            );
            if (function_exists('bvmgr_normalize_comp_terms')) {
                $vms_vendor_global_meta_terms = (array) bvmgr_normalize_comp_terms($vms_vendor_global_meta_terms);
            }
            if (!empty($vms_vendor_global_meta_terms)) {
                $vms_vendor_global_terms = array_merge($vms_vendor_global_terms, $vms_vendor_global_meta_terms);
            }
            if (function_exists('bvmgr_get_vendor_default_agent_fee_terms')) {
                $vms_vendor_global_terms = array_merge($vms_vendor_global_terms, (array) bvmgr_get_vendor_default_agent_fee_terms($selected_band_id));
            }
            if (function_exists('bvmgr_normalize_comp_terms')) {
                $vms_vendor_global_terms = (array) bvmgr_normalize_comp_terms($vms_vendor_global_terms);
            }

            $vms_vendor_venue_terms = array();
            if ($venue_id_effective > 0 && function_exists('bvmgr_get_vendor_default_comp_by_venue_map')) {
                $vms_vendor_by_venue_map = (array) bvmgr_get_vendor_default_comp_by_venue_map($selected_band_id);
                if (isset($vms_vendor_by_venue_map[$venue_id_effective]) && is_array($vms_vendor_by_venue_map[$venue_id_effective])) {
                    $vms_vendor_venue_terms = $vms_vendor_by_venue_map[$venue_id_effective];
                    if (function_exists('bvmgr_normalize_comp_terms')) {
                        $vms_vendor_venue_terms = (array) bvmgr_normalize_comp_terms($vms_vendor_venue_terms);
                    }
                }
            }

            $vms_vendor_venue_dow_terms = array();
            if ($venue_id_effective > 0 && $event_date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date) && function_exists('bvmgr_get_vendor_default_comp_by_venue_dow_map')) {
                $vms_vendor_by_venue_dow_map = (array) bvmgr_get_vendor_default_comp_by_venue_dow_map($selected_band_id);
                if (isset($vms_vendor_by_venue_dow_map[$venue_id_effective]) && is_array($vms_vendor_by_venue_dow_map[$venue_id_effective])) {
                    $vms_tz = function_exists('bvmgr_get_timezone') ? bvmgr_get_timezone() : wp_timezone();
                    if (!$vms_tz instanceof DateTimeZone) {
                        $vms_tz = wp_timezone();
                    }
                    try {
                        $vms_dt = new DateTimeImmutable($event_date, $vms_tz);
                    } catch (Exception $e) {
                        $vms_dt = null;
                    }
                    if ($vms_dt instanceof DateTimeImmutable) {
                        $vms_dow = (int) $vms_dt->format('w');
                        if (isset($vms_vendor_by_venue_dow_map[$venue_id_effective][$vms_dow]) && is_array($vms_vendor_by_venue_dow_map[$venue_id_effective][$vms_dow])) {
                            $vms_vendor_venue_dow_terms = $vms_vendor_by_venue_dow_map[$venue_id_effective][$vms_dow];
                            if (function_exists('bvmgr_normalize_comp_terms')) {
                                $vms_vendor_venue_dow_terms = (array) bvmgr_normalize_comp_terms($vms_vendor_venue_dow_terms);
                            }
                        }
                    }
                }
            }

            $vms_vendor_default_source_rows = array(
                array(
                    'label' => __('Global vendor defaults', 'backstage-venue-manager'),
                    'summary' => !empty($vms_vendor_global_terms) && function_exists('bvmgr_snapshot_summary_line')
                        ? (string) bvmgr_snapshot_summary_line($vms_vendor_global_terms)
                        : __('Not configured', 'backstage-venue-manager'),
                    'is_active' => ($vms_vendor_default_subtitle === __('Global vendor defaults', 'backstage-venue-manager')),
                ),
                array(
                    'label' => __('Venue-specific defaults', 'backstage-venue-manager'),
                    'summary' => !empty($vms_vendor_venue_terms) && function_exists('bvmgr_snapshot_summary_line')
                        ? (string) bvmgr_snapshot_summary_line($vms_vendor_venue_terms)
                        : __('Not configured for this venue', 'backstage-venue-manager'),
                    'is_active' => ($vms_vendor_default_subtitle === __('Venue-specific defaults', 'backstage-venue-manager')),
                ),
                array(
                    'label' => __('Venue + day defaults', 'backstage-venue-manager'),
                    'summary' => !empty($vms_vendor_venue_dow_terms) && function_exists('bvmgr_snapshot_summary_line')
                        ? (string) bvmgr_snapshot_summary_line($vms_vendor_venue_dow_terms)
                        : __('Not configured for this venue/day', 'backstage-venue-manager'),
                    'is_active' => ($vms_vendor_default_subtitle === __('Venue + day defaults', 'backstage-venue-manager')),
                ),
            );
        }

        $vms_vendor_default_diff_rows = array();
        $vms_diff_field_labels = array(
            'structure' => __('Comp structure', 'backstage-venue-manager'),
            'flat_fee_amount' => __('Base / flat amount', 'backstage-venue-manager'),
            'door_split_percent' => __('Door split %', 'backstage-venue-manager'),
            'attendance_bonus_mode' => __('Bonus style', 'backstage-venue-manager'),
            'attendance_bonus_start_count' => __('Starts after attendance', 'backstage-venue-manager'),
            'attendance_bonus_step_size' => __('Step size', 'backstage-venue-manager'),
            'attendance_bonus_step_bonus' => __('Bonus per step', 'backstage-venue-manager'),
            'attendance_bonus_per_ticket_rate' => __('Per-ticket bonus rate', 'backstage-venue-manager'),
            'attendance_bonus_max_bonus' => __('Bonus cap', 'backstage-venue-manager'),
            'commission_percent' => __('Agent fee %', 'backstage-venue-manager'),
            'commission_mode' => __('Agent fee basis', 'backstage-venue-manager'),
        );
        if ($vms_vendor_default_has_terms) {
            $vms_vendor_terms_for_compare = $vms_comp_terms_for_compare($vms_vendor_default_terms);
            $vms_actual_terms_for_compare = $vms_comp_terms_for_compare($vms_actual_terms);
            foreach ($vms_diff_field_labels as $vms_diff_key => $vms_diff_label) {
                if (($vms_vendor_terms_for_compare[$vms_diff_key] ?? '') === ($vms_actual_terms_for_compare[$vms_diff_key] ?? '')) {
                    continue;
                }
                $vms_vendor_default_diff_rows[] = array(
                    'label' => $vms_diff_label,
                    'live' => $vms_format_comp_field_value($vms_diff_key, $vms_vendor_default_terms),
                    'draft' => $vms_format_comp_field_value($vms_diff_key, $vms_actual_terms),
                );
            }
        }

        return array(
            'ack' => $ack,
            'ack_ts' => $ack_ts,
            'ack_user' => $ack_user,
            'ack_default_snapshot' => $ack_default_snapshot,
            'ack_actual_snapshot' => $ack_actual_snapshot,
            'ack_checked' => $ack_checked,
            'ack_still_valid' => $ack_still_valid,
            'vms_fmt_money' => $vms_fmt_money,
            'vms_flat_num' => $vms_flat_num,
            'vms_struct_guarantee_map' => $vms_struct_guarantee_map,
            'vms_struct_guarantee_max' => $vms_struct_guarantee_max,
            'vms_selected_guarantee' => $vms_selected_guarantee,
            'vms_guarantee_max' => $vms_guarantee_max,
            'vms_requires_low_guarantee_ack' => $vms_requires_low_guarantee_ack,
            'vms_low_ack' => $vms_low_ack,
            'vms_low_ack_ts' => $vms_low_ack_ts,
            'vms_low_ack_user' => $vms_low_ack_user,
            'vms_low_ack_snapshot' => $vms_low_ack_snapshot,
            'vms_low_ack_checked' => $vms_low_ack_checked,
            'vms_low_ack_still_valid' => $vms_low_ack_still_valid,
            'vms_combined_ack_checked' => $vms_combined_ack_checked,
            'vms_vendor_default_has_terms' => $vms_vendor_default_has_terms,
            'vms_vendor_default_matches_draft' => $vms_vendor_default_matches_draft,
            'vms_show_vendor_default_drift_notice' => $vms_show_vendor_default_drift_notice,
            'vms_vendor_default_subtitle' => $vms_vendor_default_subtitle,
            'vms_vendor_default_summary' => $vms_vendor_default_summary,
            'vms_actual_draft_summary' => $vms_actual_draft_summary,
            'vms_vendor_default_source_rows' => $vms_vendor_default_source_rows,
            'vms_vendor_default_diff_rows' => $vms_vendor_default_diff_rows,
        );
        } finally {
            if (function_exists('bvmgr_event_plan_perf_span_finish')) {
                bvmgr_event_plan_perf_span_finish('event_plan_compensation_render_context', $post_id, $trace, array('section' => 'compensation'));
            }
        }
    }


    private function render_event_plan_partial(string $partial, array $vars = array()): void
    {
        $path = $this->get_event_plan_partial_path($partial);
        if ($path === '' || !is_readable($path)) {
            return;
        }

        extract($this->prepare_event_plan_partial_vars($vars), EXTR_SKIP);
        include $path;
    }

    private function capture_event_plan_partial(string $partial, array $vars = array()): string
    {
        $post_obj = $vars['post'] ?? null;
        $plan_id = ($post_obj instanceof WP_Post) ? (int) $post_obj->ID : absint($vars['post_id'] ?? 0);
        $memory_phase_map = array(
            'time-lineup' => 'time_lineup_render',
            'secondary-vendors' => 'secondary_vendors_render',
            'advanced-controls' => 'advanced_controls_render',
            'ticketing-v2' => 'ticketing_summary_render',
            'staff' => 'staff_render',
        );
        $memory_group = isset($memory_phase_map[$partial]) ? (string) $memory_phase_map[$partial] : '';
        $trace = function_exists('bvmgr_event_plan_perf_span_start')
            ? bvmgr_event_plan_perf_span_start('event_plan_partial_render_' . sanitize_key($partial), $plan_id, array('section' => 'partial_render', 'partial' => $partial))
            : '';
        $html = '';

        if ($memory_group !== '' && function_exists('bvmgr_event_plan_perf_memory_checkpoint')) {
            bvmgr_event_plan_perf_memory_checkpoint($plan_id, $memory_group . '_before', array(
                'section' => 'partial_render',
                'partial' => $partial,
            ), $memory_group);
        }

        try {
            ob_start();
            $this->render_event_plan_partial($partial, $vars);
            $html = (string) ob_get_clean();
            return $html;
        } finally {
            if (function_exists('bvmgr_event_plan_perf_span_finish')) {
                bvmgr_event_plan_perf_span_finish('event_plan_partial_render_' . sanitize_key($partial), $plan_id, $trace, array(
                    'section' => 'partial_render',
                    'partial' => $partial,
                    'rendered_html_bytes' => strlen($html),
                ));
            }
            if ($memory_group !== '' && function_exists('bvmgr_event_plan_perf_memory_checkpoint')) {
                bvmgr_event_plan_perf_memory_checkpoint($plan_id, $memory_group . '_after', array(
                    'section' => 'partial_render',
                    'partial' => $partial,
                    'rendered_html_bytes' => strlen($html),
                ), $memory_group);
            }
        }
    }

    /**
     * Render meta box
     */
    public function render_event_plan_details_meta_box(WP_Post $post): void
    {
        $admin_request = bvmgr_event_plan_current_request_data();
        $admin_get = bvmgr_event_plan_current_get_request();
        if (function_exists('bvmgr_event_plan_perf_query_checkpoint')) {
            bvmgr_event_plan_perf_query_checkpoint((int) $post->ID, 'before_details_render', array(
                'section' => 'meta_box_render',
                'screen_action' => isset($admin_request['action']) ? sanitize_key((string) $admin_request['action']) : '',
                'before_details_render' => 1,
            ), 'admin_boot');
        }

        $render_trace = function_exists('bvmgr_event_plan_perf_span_start')
            ? bvmgr_event_plan_perf_span_start('event_plan_details_meta_box_render', (int) $post->ID, array('section' => 'meta_box_render'))
            : '';

        try {
        if (function_exists('bvmgr_event_plan_perf_memory_checkpoint')) {
            bvmgr_event_plan_perf_memory_checkpoint((int) $post->ID, 'details_meta_box_before', array(
                'section' => 'meta_box_render',
                'capture_dependency_snapshot' => 1,
            ), 'details_meta_box');
        }
        wp_nonce_field('bvmgr_save_event_plan_details', 'bvmgr_event_plan_details_nonce');
        echo '<input type="hidden" name="vms_reopen_section_after_save" id="vms-reopen-section-after-save" value="" />';
        $scroll_to = (string) get_post_meta($post->ID, '_vms_admin_scroll_to', true);

        // ----------------------------
        // Load core meta
        // ----------------------------
        $event_date     = (string) get_post_meta($post->ID, '_vms_event_date', true);
        $start_time     = (string) get_post_meta($post->ID, '_vms_start_time', true);
        $end_time       = (string) get_post_meta($post->ID, '_vms_end_time', true);
        $occurrence_locked = function_exists('bvmgr_event_occurrence_is_published')
            && bvmgr_event_occurrence_is_published((int) $post->ID);

        // Venue: saved vs UI default (important for "packages show on first load")
        $venue_id_saved = (int) get_post_meta($post->ID, '_vms_venue_id', true);

        $venue_id_ui = 0;
        if ($venue_id_saved <= 0 && function_exists('bvmgr_get_current_venue_id')) {
            $venue_id_ui = (int) bvmgr_get_current_venue_id();
        }

        // ----------------------------
        // Schedule "Create/Add" defaults (first-load only)
        // ----------------------------
        if ($post->post_status === 'auto-draft') {

            $qs_venue = isset($admin_get['vms_venue_id']) ? absint($admin_get['vms_venue_id']) : 0;
            $qs_date  = isset($admin_get['vms_date']) ? sanitize_text_field((string) $admin_get['vms_date']) : '';

            // Prefill venue/date in the form (but do NOT force a title here)
            if ($venue_id_saved <= 0 && $qs_venue > 0) {
                $venue_id_ui = $qs_venue;
            }

            if (empty($event_date) && !empty($qs_date)) {
                $event_date = $qs_date;
            }
        }

        // Effective venue for rendering: show packages immediately on new plan
        $venue_id_effective = $venue_id_saved > 0 ? $venue_id_saved : $venue_id_ui;

        // Default times (use effective venue)
        if ($venue_id_effective > 0 && (empty($start_time) || empty($end_time)) && function_exists('bvmgr_get_venue_default_times')) {
            $defaults = (array) bvmgr_get_venue_default_times($venue_id_effective);

            if (empty($start_time) && !empty($defaults['start'])) {
                $start_time = (string) $defaults['start'];
            }
            if (empty($end_time)) {
                if (!empty($defaults['end'])) {
                    $end_time = (string) $defaults['end'];
                } elseif (!empty($start_time) && !empty($defaults['dur']) && function_exists('bvmgr_time_add_minutes')) {
                    $end_time = (string) bvmgr_time_add_minutes($start_time, (int) $defaults['dur']);
                }
            }
        }
        if (empty($start_time)) $start_time = '19:00';
        if (empty($end_time))   $end_time   = '21:00';

        $auto_title = (string) get_post_meta($post->ID, '_vms_auto_title', true);

        // Default OFF for brand-new plans (auto-draft), but preserve legacy behavior for existing plans.
        if ($auto_title === '') {
            $auto_title = ($post->post_status === 'auto-draft') ? '0' : '1';
        }

        $auto_comp = (string) get_post_meta($post->ID, '_vms_auto_comp', true);
        if ($auto_comp === '') $auto_comp = '1';

        $auto_comp_venue = (string) get_post_meta($post->ID, '_vms_auto_comp_venue', true);
        if ($auto_comp_venue === '') $auto_comp_venue = '1';

        // Draft pay fields
        $comp_structure      = (string) get_post_meta($post->ID, '_vms_comp_structure', true);
        if ($comp_structure === '') $comp_structure = 'flat_fee';

        $flat_fee_amount     = get_post_meta($post->ID, '_vms_flat_fee_amount', true);
        $door_split_percent  = get_post_meta($post->ID, '_vms_door_split_percent', true);
        $attendance_bonus_mode = (string) get_post_meta($post->ID, '_vms_attendance_bonus_mode', true);
        $attendance_bonus_start_count = get_post_meta($post->ID, '_vms_attendance_bonus_start_count', true);
        $attendance_bonus_step_size = get_post_meta($post->ID, '_vms_attendance_bonus_step_size', true);
        $attendance_bonus_step_bonus = get_post_meta($post->ID, '_vms_attendance_bonus_step_bonus', true);
        $attendance_bonus_per_ticket_rate = get_post_meta($post->ID, '_vms_attendance_bonus_per_ticket_rate', true);
        $attendance_bonus_max_bonus = get_post_meta($post->ID, '_vms_attendance_bonus_max_bonus', true);
        $commission_percent = get_post_meta($post->ID, '_vms_commission_percent', true);
        $commission_mode = (string) get_post_meta($post->ID, '_vms_commission_mode', true);
        if (!in_array($commission_mode, array('artist_fee', 'gross'), true)) $commission_mode = 'artist_fee';
        $k_commission_override_none = function_exists('bvmgr_meta_key')
            ? (bvmgr_meta_key('event_plan', 'commission_override_none') ?: '_vms_commission_override_none')
            : '_vms_commission_override_none';
        $commission_override_none = (string) get_post_meta($post->ID, $k_commission_override_none, true) === '1';
        if ($commission_override_none) {
            $commission_percent = '';
            $commission_mode = 'artist_fee';
        }

        $deposit_terms = function_exists('bvmgr_get_event_plan_deposit_terms')
            ? (array) bvmgr_get_event_plan_deposit_terms((int) $post->ID)
            : array();
        $deposit_amount = $deposit_terms['deposit_amount'] ?? '';
        $deposit_status = function_exists('bvmgr_normalize_comp_deposit_status')
            ? bvmgr_normalize_comp_deposit_status($deposit_terms['deposit_status'] ?? '')
            : (string) ($deposit_terms['deposit_status'] ?? 'not_required');
        $deposit_treatment = function_exists('bvmgr_normalize_comp_deposit_treatment')
            ? bvmgr_normalize_comp_deposit_treatment($deposit_terms['deposit_treatment'] ?? '')
            : (string) ($deposit_terms['deposit_treatment'] ?? 'creditable');
        $deposit_due_date = function_exists('bvmgr_normalize_comp_deposit_date')
            ? bvmgr_normalize_comp_deposit_date($deposit_terms['deposit_due_date'] ?? '')
            : (string) ($deposit_terms['deposit_due_date'] ?? '');
        $deposit_paid_date = function_exists('bvmgr_normalize_comp_deposit_date')
            ? bvmgr_normalize_comp_deposit_date($deposit_terms['deposit_paid_date'] ?? '')
            : (string) ($deposit_terms['deposit_paid_date'] ?? '');
        $deposit_notes = isset($deposit_terms['deposit_notes']) ? (string) $deposit_terms['deposit_notes'] : '';
        $deposit_status_options = function_exists('bvmgr_comp_deposit_status_options') ? bvmgr_comp_deposit_status_options() : array(
            'not_required' => __('Not required', 'backstage-venue-manager'),
            'unpaid' => __('Unpaid', 'backstage-venue-manager'),
            'paid' => __('Paid', 'backstage-venue-manager'),
            'waived' => __('Waived', 'backstage-venue-manager'),
            'refunded' => __('Refunded', 'backstage-venue-manager'),
        );
        $deposit_treatment_options = function_exists('bvmgr_comp_deposit_treatment_options') ? bvmgr_comp_deposit_treatment_options() : array(
            'creditable' => __('Applies toward total payment', 'backstage-venue-manager'),
            'refundable' => __('Refundable', 'backstage-venue-manager'),
            'nonrefundable' => __('Non-refundable', 'backstage-venue-manager'),
        );

        $final_payment_terms = function_exists('bvmgr_get_event_plan_final_payment_terms')
            ? (array) bvmgr_get_event_plan_final_payment_terms((int) $post->ID)
            : array();
        $final_payment_timing = function_exists('bvmgr_normalize_comp_final_payment_timing')
            ? bvmgr_normalize_comp_final_payment_timing($final_payment_terms['final_payment_timing'] ?? '')
            : (string) ($final_payment_terms['final_payment_timing'] ?? 'not_set');
        $final_payment_days_after = function_exists('bvmgr_normalize_comp_final_payment_days_after')
            ? bvmgr_normalize_comp_final_payment_days_after($final_payment_terms['final_payment_days_after'] ?? '')
            : (string) ($final_payment_terms['final_payment_days_after'] ?? '');
        $final_payment_date = function_exists('bvmgr_normalize_comp_final_payment_date')
            ? bvmgr_normalize_comp_final_payment_date($final_payment_terms['final_payment_date'] ?? '')
            : (string) ($final_payment_terms['final_payment_date'] ?? '');
        $final_payment_custom_text = isset($final_payment_terms['final_payment_custom_text']) ? (string) $final_payment_terms['final_payment_custom_text'] : '';
        $final_payment_method = function_exists('bvmgr_normalize_comp_final_payment_method')
            ? bvmgr_normalize_comp_final_payment_method($final_payment_terms['final_payment_method'] ?? '')
            : (string) ($final_payment_terms['final_payment_method'] ?? 'not_set');
        $final_payment_method_other = isset($final_payment_terms['final_payment_method_other']) ? (string) $final_payment_terms['final_payment_method_other'] : '';
        $final_payment_timing_options = function_exists('bvmgr_comp_final_payment_timing_options') ? bvmgr_comp_final_payment_timing_options() : array(
            'not_set' => __('Not set', 'backstage-venue-manager'),
            'in_advance' => __('In advance', 'backstage-venue-manager'),
            'day_of_event' => __('Day of event', 'backstage-venue-manager'),
            'days_after' => __('N days after event', 'backstage-venue-manager'),
            'fixed_date' => __('Specific date', 'backstage-venue-manager'),
            'custom' => __('Custom timing', 'backstage-venue-manager'),
        );
        $final_payment_method_options = function_exists('bvmgr_comp_final_payment_method_options') ? bvmgr_comp_final_payment_method_options() : array(
            'not_set' => __('Not set', 'backstage-venue-manager'),
            'check' => __('Check', 'backstage-venue-manager'),
            'cash' => __('Cash', 'backstage-venue-manager'),
            'ach_direct_deposit' => __('ACH / Direct Deposit', 'backstage-venue-manager'),
            'zelle' => __('Zelle', 'backstage-venue-manager'),
            'venmo' => __('Venmo', 'backstage-venue-manager'),
            'paypal' => __('PayPal', 'backstage-venue-manager'),
            'other' => __('Other', 'backstage-venue-manager'),
        );

        // Current package selection
        $current_pkg_id = (int) get_post_meta($post->ID, '_vms_comp_package_id', true);

        $k_comp_selected_option = function_exists('bvmgr_meta_key')
            ? (bvmgr_meta_key('event_plan', 'comp_selected_option') ?: '_vms_comp_selected_option')
            : '_vms_comp_selected_option';
        $selected_opt = (string) get_post_meta($post->ID, $k_comp_selected_option, true);
        if ($selected_opt === '' && (int) $current_pkg_id > 0) {
            $selected_opt = 'package:' . (int) $current_pkg_id;
        }

        // Snapshot (locked pay)
        $snapshot = get_post_meta($post->ID, '_vms_comp_snapshot', true);
        if (!is_array($snapshot)) $snapshot = array();

        $needs_snapshot = (get_post_meta($post->ID, '_vms_comp_needs_snapshot', true) === '1');

        $current_hash  = function_exists('bvmgr_comp_hash_for_plan') ? (string) bvmgr_comp_hash_for_plan((int)$post->ID) : '';
        $snapshot_hash = isset($snapshot['comp_hash']) ? (string) $snapshot['comp_hash'] : '';

        $out_of_sync = false;
        if (!empty($snapshot)) {
            if ($snapshot_hash !== '' && $current_hash !== '' && $snapshot_hash !== $current_hash) $out_of_sync = true;
            if ($needs_snapshot) $out_of_sync = true;
        }

        // Load packages for effective venue (+ global)
        $packages = array();
        if ($venue_id_effective > 0 && function_exists('bvmgr_get_comp_packages_for_venue')) {
            $packages = (array) bvmgr_get_comp_packages_for_venue($venue_id_effective, true);
        }

        // Plan status LEGACY
        // $plan_status = (string) get_post_meta($post->ID, '_vms_event_plan_status', true);
        // if ($plan_status === '') $plan_status = 'draft';

        // Plan status (validated against canonical registry)
        // updated this during constants.php > meta-keys.php refactor
        $plan_status = (string) get_post_meta($post->ID, bvmgr_meta_key('event_plan', 'status'), true);

        $allowed = function_exists('bvmgr_event_plan_statuses')
            ? array_keys(bvmgr_event_plan_statuses())
            : array('draft', 'ready', 'published', 'tentative', 'confirmed', 'cancelled', 'archived');

        if ($plan_status === '' || !in_array($plan_status, $allowed, true)) {
            $plan_status = 'draft';
        }
        // END Plan status (validated against canonical registry)

        $k_cancel_policy = function_exists('bvmgr_meta_key')
            ? (bvmgr_meta_key('event_plan', 'cancel_policy') ?: '_vms_cancel_policy')
            : '_vms_cancel_policy';
        $k_cancel_reason_code = function_exists('bvmgr_meta_key')
            ? (bvmgr_meta_key('event_plan', 'cancel_reason_code') ?: '_vms_cancel_reason_code')
            : '_vms_cancel_reason_code';
        $k_cancel_reason_note = function_exists('bvmgr_meta_key')
            ? (bvmgr_meta_key('event_plan', 'cancel_reason_note') ?: '_vms_cancel_reason_note')
            : '_vms_cancel_reason_note';
        $k_cancel_vendor_message = function_exists('bvmgr_meta_key')
            ? (bvmgr_meta_key('event_plan', 'cancel_vendor_message') ?: '_vms_cancel_vendor_message')
            : '_vms_cancel_vendor_message';

        $cancel_policy = sanitize_key((string) get_post_meta($post->ID, $k_cancel_policy, true));
        $cancel_reason_code = sanitize_key((string) get_post_meta($post->ID, $k_cancel_reason_code, true));
        $cancel_reason_note = trim((string) get_post_meta($post->ID, $k_cancel_reason_note, true));
        $cancel_vendor_message = trim((string) get_post_meta($post->ID, $k_cancel_vendor_message, true));

        $cancel_policy_options = function_exists('bvmgr_cancellation_policy_options')
            ? (array) bvmgr_cancellation_policy_options()
            : array('status_only' => __('Status only', 'backstage-venue-manager'));
        $cancel_reason_options = function_exists('bvmgr_cancellation_reason_options')
            ? (array) bvmgr_cancellation_reason_options()
            : array('other' => __('Other', 'backstage-venue-manager'));

        if ($cancel_policy === '' || !array_key_exists($cancel_policy, $cancel_policy_options)) {
            $cancel_policy = 'status_only';
        }
        if ($cancel_reason_code !== '' && !array_key_exists($cancel_reason_code, $cancel_reason_options)) {
            $cancel_reason_code = 'other';
        }

        // ----------------------------
        // Data for dropdowns
        // ----------------------------
        $dropdown_trace = function_exists('bvmgr_event_plan_perf_span_start')
            ? bvmgr_event_plan_perf_span_start('event_plan_primary_vendor_lookup', (int) $post->ID, array('section' => 'primary_vendor_lookup'))
            : '';
        try {
            $venues = get_posts(array(
                'post_type'      => 'vms_venue',
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
                'no_found_rows'  => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            ));

            $bands = get_posts(array(
                'post_type'      => 'vms_vendor',
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
                'no_found_rows'  => true,
                'update_post_term_cache' => false,
            ));
        } finally {
            if (function_exists('bvmgr_event_plan_perf_span_finish')) {
                bvmgr_event_plan_perf_span_finish('event_plan_primary_vendor_lookup', (int) $post->ID, $dropdown_trace, array('section' => 'primary_vendor_lookup'));
            }
        }

        if (function_exists('bvmgr_event_plan_perf_memory_checkpoint')) {
            bvmgr_event_plan_perf_memory_checkpoint((int) $post->ID, 'details_meta_box_after_dropdowns', array(
                'section' => 'meta_box_render',
                'venue_count' => is_array($venues) ? count($venues) : 0,
                'vendor_count' => is_array($bands) ? count($bands) : 0,
            ), 'details_meta_box');
        }

        $selected_band_id = (int) get_post_meta($post->ID, '_vms_band_vendor_id', true);

        if ($post->post_status === 'auto-draft' && $selected_band_id <= 0) {
            $qs_band_vendor = isset($admin_get['vms_band_vendor_id']) ? absint($admin_get['vms_band_vendor_id']) : 0;
            if ($qs_band_vendor <= 0 && isset($admin_get['vms_prefill_vendor_mode'], $admin_get['vms_prefill_vendor_id'])) {
                $qs_prefill_mode = sanitize_key((string) $admin_get['vms_prefill_vendor_mode']);
                if ($qs_prefill_mode === 'primary') {
                    $qs_band_vendor = absint($admin_get['vms_prefill_vendor_id']);
                }
            }
            if ($qs_band_vendor > 0) {
                $selected_band_id = $qs_band_vendor;
            }
        }

        if (!$commission_override_none && ($commission_percent === '' || $commission_percent === null) && $selected_band_id > 0 && function_exists('bvmgr_get_vendor_default_agent_fee_terms')) {
            $vendor_agent_fee = (array) bvmgr_get_vendor_default_agent_fee_terms($selected_band_id);
            if (!empty($vendor_agent_fee)) {
                $commission_percent = $vendor_agent_fee['commission_percent'] ?? '';
                $commission_mode = isset($vendor_agent_fee['commission_mode']) ? (string) $vendor_agent_fee['commission_mode'] : 'artist_fee';
                if (!in_array($commission_mode, array('artist_fee', 'gross'), true)) {
                    $commission_mode = 'artist_fee';
                }
            }
        }

        $lock_pay_basics_state = $this->get_lock_pay_basics_state((int) $post->ID);
        $lock_pay_enabled = !empty($lock_pay_basics_state['ok']);

        $comp_options_nonce = wp_create_nonce('bvmgr_comp_options');
        $comp_opts_trace = function_exists('bvmgr_event_plan_perf_span_start')
            ? bvmgr_event_plan_perf_span_start('event_plan_comp_options_lookup', (int) $post->ID, array('section' => 'compensation'))
            : '';
        try {
            $comp_opts = function_exists('bvmgr_get_event_plan_comp_options')
                ? (array) bvmgr_get_event_plan_comp_options($venue_id_effective, $event_date, $selected_band_id)
                : array('defaults' => array(), 'packages' => array(), 'max_guarantee' => 0.0);
        } finally {
            if (function_exists('bvmgr_event_plan_perf_span_finish')) {
                bvmgr_event_plan_perf_span_finish('event_plan_comp_options_lookup', (int) $post->ID, $comp_opts_trace, array('section' => 'compensation'));
            }
        }
        $comp_opts['_venue_selected'] = ($venue_id_effective > 0);
        $comp_opts['_date_selected'] = (!empty($event_date));

        // Staff assignments (role_term_id => [staff_post_ids...])
        $staff_assignments = get_post_meta($post->ID, '_vms_staff_assignments', true);
        if (!is_array($staff_assignments)) $staff_assignments = array();
// Secondary vendors (non-performer vendors, e.g., food trucks)
        $k_secondary_ids     = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'secondary_vendor_ids') : '_vms_secondary_vendor_ids';
        $k_secondary_type    = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'secondary_vendor_type') : '_vms_secondary_vendor_type';
        $k_secondary_unq     = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'secondary_vendor_unqualified') : '_vms_secondary_vendor_unqualified';
        $k_secondary_unq_ids = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'secondary_vendor_unqualified_ids') : '_vms_secondary_vendor_unqualified_ids';

        $secondary_trace = function_exists('bvmgr_event_plan_perf_span_start')
            ? bvmgr_event_plan_perf_span_start('event_plan_secondary_vendor_lookup', (int) $post->ID, array('section' => 'secondary_vendor_lookup'))
            : '';
        try {
            $secondary_vendor_type = function_exists('bvmgr_vendor_type_normalize_slug')
                ? bvmgr_vendor_type_normalize_slug((string) get_post_meta($post->ID, $k_secondary_type, true))
                : sanitize_title((string) get_post_meta($post->ID, $k_secondary_type, true));

            $secondary_vendor_ids = get_post_meta($post->ID, $k_secondary_ids, true);
            if (!is_array($secondary_vendor_ids)) $secondary_vendor_ids = array();
            $secondary_vendor_ids = array_values(array_unique(array_filter(array_map('absint', $secondary_vendor_ids), function ($v) {
                return $v > 0;
            })));

        if ($post->post_status === 'auto-draft') {
            if ($secondary_vendor_type === '' && isset($admin_get['vms_secondary_vendor_type'])) {
                $secondary_vendor_type = function_exists('bvmgr_vendor_type_normalize_slug')
                    ? bvmgr_vendor_type_normalize_slug((string) $admin_get['vms_secondary_vendor_type'])
                    : sanitize_title((string) $admin_get['vms_secondary_vendor_type']);
            }
            if ($secondary_vendor_type === '' && isset($admin_get['vms_prefill_vendor_mode'], $admin_get['vms_prefill_vendor_type'])) {
                $qs_prefill_mode = sanitize_key((string) $admin_get['vms_prefill_vendor_mode']);
                if ($qs_prefill_mode === 'secondary') {
                    $secondary_vendor_type = function_exists('bvmgr_vendor_type_normalize_slug')
                        ? bvmgr_vendor_type_normalize_slug((string) $admin_get['vms_prefill_vendor_type'])
                        : sanitize_title((string) $admin_get['vms_prefill_vendor_type']);
                }
            }

            if (empty($secondary_vendor_ids)) {
                $qs_secondary_ids = array();
                if (isset($admin_get['vms_secondary_vendor_id'])) {
                    $qs_secondary_ids[] = absint($admin_get['vms_secondary_vendor_id']);
                }
                if (isset($admin_get['vms_secondary_vendor_ids']) && is_array($admin_get['vms_secondary_vendor_ids'])) {
                    $qs_secondary_ids = array_merge($qs_secondary_ids, array_map('absint', (array) $admin_get['vms_secondary_vendor_ids']));
                }
                if (empty($qs_secondary_ids) && isset($admin_get['vms_prefill_vendor_mode'], $admin_get['vms_prefill_vendor_id'])) {
                    $qs_prefill_mode = sanitize_key((string) $admin_get['vms_prefill_vendor_mode']);
                    if ($qs_prefill_mode === 'secondary') {
                        $qs_secondary_ids[] = absint($admin_get['vms_prefill_vendor_id']);
                    }
                }
                $qs_secondary_ids = array_values(array_unique(array_filter($qs_secondary_ids, static function ($v) {
                    return $v > 0;
                })));
                if (!empty($qs_secondary_ids)) {
                    $secondary_vendor_ids = $qs_secondary_ids;
                }
            }
        }
 
        // Stored qualification flags (rebuilt on every save; used for list view + visibility)
            $secondary_unqualified = ((string) get_post_meta($post->ID, $k_secondary_unq, true) === '1');
            $secondary_unqualified_ids = get_post_meta($post->ID, $k_secondary_unq_ids, true);
            if (!is_array($secondary_unqualified_ids)) $secondary_unqualified_ids = array();
            $secondary_unqualified_ids = array_values(array_unique(array_filter(array_map('absint', $secondary_unqualified_ids), function ($v) {
                return $v > 0;
            })));
        } finally {
            if (function_exists('bvmgr_event_plan_perf_span_finish')) {
                bvmgr_event_plan_perf_span_finish('event_plan_secondary_vendor_lookup', (int) $post->ID, $secondary_trace, array('section' => 'secondary_vendor_lookup'));
            }
        }

        $secondary_vendor_assignments = function_exists('bvmgr_event_plan_get_secondary_vendor_assignments')
            ? (array) bvmgr_event_plan_get_secondary_vendor_assignments((int) $post->ID, array(
                'primary_vendor_id' => $selected_band_id,
            ))
            : array();
        if ($post->post_status === 'auto-draft' && empty($secondary_vendor_assignments) && ($secondary_vendor_type !== '' || !empty($secondary_vendor_ids)) && function_exists('bvmgr_event_plan_normalize_secondary_vendor_assignment_map')) {
            $secondary_vendor_assignments = bvmgr_event_plan_normalize_secondary_vendor_assignment_map((int) $post->ID, array(
                $secondary_vendor_type !== '' ? $secondary_vendor_type : 'legacy' => array(
                    'type_slug' => $secondary_vendor_type,
                    'mode' => 'standard',
                    'slot_limit' => null,
                    'vendor_ids' => $secondary_vendor_ids,
                ),
            ), $selected_band_id, array(
                'preserve_empty' => true,
            ));
        }
        if (!empty($secondary_vendor_assignments)) {
            $secondary_vendor_type = function_exists('bvmgr_event_plan_legacy_secondary_vendor_type_from_assignments')
                ? (string) bvmgr_event_plan_legacy_secondary_vendor_type_from_assignments($secondary_vendor_assignments)
                : $secondary_vendor_type;
            $secondary_vendor_ids = function_exists('bvmgr_event_plan_get_secondary_vendor_flat_ids_from_assignments')
                ? bvmgr_event_plan_get_secondary_vendor_flat_ids_from_assignments($secondary_vendor_assignments, $selected_band_id)
                : $secondary_vendor_ids;
        }


        $vms_time_current = static function (string $value): string {
            $value = trim($value);
            if ($value === '') {
                return '';
            }
            if (preg_match('/^\d{2}:\d{2}$/', $value)) {
                return $value;
            }
            $ts = strtotime($value);
            if ($ts === false) {
                return '';
            }
            return gmdate('H:i', $ts);
        };

        $vms_time_options = array();
        for ($i = 0; $i < 48; $i++) {
            $h = (int) floor($i / 2);
            $m = ($i % 2) ? 30 : 0;
            $value = sprintf('%02d:%02d', $h, $m);
            $label = date_i18n('g:ia', strtotime('2000-01-01 ' . $value . ':00'));
            $vms_time_options[$value] = $label;
        }
        $start_time_current = $vms_time_current((string) $start_time);
        $end_time_current = $vms_time_current((string) $end_time);
        if ($start_time_current !== '' && !isset($vms_time_options[$start_time_current])) {
            $vms_time_options[$start_time_current] = date_i18n('g:ia', strtotime('2000-01-01 ' . $start_time_current . ':00'));
        }
        if ($end_time_current !== '' && !isset($vms_time_options[$end_time_current])) {
            $vms_time_options[$end_time_current] = date_i18n('g:ia', strtotime('2000-01-01 ' . $end_time_current . ':00'));
        }
        ksort($vms_time_options);

        $lineup_context = array(
            'legacy_primary_vendor_id' => $selected_band_id,
            'event_start' => $start_time_current,
            'event_end' => $end_time_current,
        );
        $lineup_trace = function_exists('bvmgr_event_plan_perf_span_start')
            ? bvmgr_event_plan_perf_span_start('event_plan_supporting_act_lookup', (int) $post->ID, array('section' => 'supporting_act_lookup'))
            : '';
        try {
            $lineup_entries = function_exists('bvmgr_get_event_plan_lineup_entries')
                ? (array) bvmgr_get_event_plan_lineup_entries((int) $post->ID, $lineup_context)
                : array();
            $lineup_summary = function_exists('bvmgr_get_event_plan_lineup_summary')
                ? (array) bvmgr_get_event_plan_lineup_summary((int) $post->ID, $lineup_context)
                : array();
            $lineup_warnings = function_exists('bvmgr_get_event_plan_lineup_warnings')
                ? (array) bvmgr_get_event_plan_lineup_warnings((int) $post->ID, $lineup_context)
                : array();
        } finally {
            if (function_exists('bvmgr_event_plan_perf_span_finish')) {
                bvmgr_event_plan_perf_span_finish('event_plan_supporting_act_lookup', (int) $post->ID, $lineup_trace, array('section' => 'supporting_act_lookup'));
            }
        }
        $lineup_primary_entry = array();
        $lineup_supporting_entries = array();
        foreach ($lineup_entries as $lineup_entry) {
            if (sanitize_key((string) ($lineup_entry['role'] ?? '')) === 'primary' && empty($lineup_primary_entry)) {
                $lineup_primary_entry = $lineup_entry;
                continue;
            }
            $lineup_supporting_entries[] = $lineup_entry;
        }
        $lineup_supporting_selected_vendor_ids = array_values(array_unique(array_filter(array_map(static function ($entry): int {
            return is_array($entry) ? absint($entry['vendor_id'] ?? 0) : 0;
        }, $lineup_supporting_entries), static function ($vendor_id): bool {
            return $vendor_id > 0;
        })));
        if (empty($lineup_primary_entry)) {
            $lineup_primary_entry = array(
                'row_id' => function_exists('bvmgr_lineup_schedule_make_row_id') ? bvmgr_lineup_schedule_make_row_id() : 'lineup_primary',
                'vendor_id' => $selected_band_id,
                'role' => 'primary',
                'set_start' => $start_time_current,
                'set_end' => $end_time_current,
                'set_start_label' => function_exists('bvmgr_lineup_schedule_format_time_label') ? bvmgr_lineup_schedule_format_time_label($start_time_current) : $start_time_current,
                'set_end_label' => function_exists('bvmgr_lineup_schedule_format_time_label') ? bvmgr_lineup_schedule_format_time_label($end_time_current) : $end_time_current,
                'duration_label' => '',
                'duration_minutes' => null,
                'warning_count' => 0,
                'public_name_override' => '',
                'show_public' => '1',
                'show_portal' => '1',
                'schedule_notes' => '',
                'pay_notes' => '',
                'internal_notes' => '',
                'display_name' => $selected_band_id > 0 ? (string) get_the_title($selected_band_id) : '',
            );
        }

        $lineup_primary_vendor_id = absint($lineup_primary_entry['vendor_id'] ?? $selected_band_id);
        $lineup_primary_vendor_label = $lineup_primary_vendor_id > 0 ? (string) get_the_title($lineup_primary_vendor_id) : __('Unassigned primary vendor', 'backstage-venue-manager');
        $lineup_primary_time_label = trim(implode(' – ', array_filter(array(
            (string) ($lineup_primary_entry['set_start_label'] ?? ''),
            (string) ($lineup_primary_entry['set_end_label'] ?? ''),
        ))));
        $lineup_primary_duration_label = trim((string) ($lineup_primary_entry['duration_label'] ?? ''));
        $lineup_primary_warning_count = (int) ($lineup_primary_entry['warning_count'] ?? 0);
        $lineup_primary_pay_summary = '';
        if ($comp_structure === 'door_split') {
            /* translators: %s: door split. */
            $lineup_primary_pay_summary = ($door_split_percent !== '') ? sprintf(__('Door split: %s%%', 'backstage-venue-manager'), rtrim(rtrim((string) $door_split_percent, '0'), '.')) : __('Door split', 'backstage-venue-manager');
        } elseif ($comp_structure === 'flat_fee_door_split') {
            $fee_label = ($flat_fee_amount !== '') ? function_exists('vms_format_currency') ? vms_format_currency((float) $flat_fee_amount) : ('$' . number_format((float) $flat_fee_amount, 2)) : __('No guarantee set', 'backstage-venue-manager');
            $split_label = ($door_split_percent !== '') ? rtrim(rtrim((string) $door_split_percent, '0'), '.') . '%' : __('door split', 'backstage-venue-manager');
            /* translators: 1: value 1 used in this message, 2: value 2 used in this message. */
            $lineup_primary_pay_summary = sprintf(__('%1$s + %2$s door', 'backstage-venue-manager'), $fee_label, $split_label);
        } elseif ($comp_structure === 'attendance_bonus') {
            $fee_label = ($flat_fee_amount !== '') ? function_exists('vms_format_currency') ? vms_format_currency((float) $flat_fee_amount) : ('$' . number_format((float) $flat_fee_amount, 2)) : __('No base fee set', 'backstage-venue-manager');
            /* translators: %s: human-readable value used in this message. */
            $lineup_primary_pay_summary = sprintf(__('Attendance bonus | Base %s', 'backstage-venue-manager'), $fee_label);
        } else {
            if ($flat_fee_amount !== '') {
                $lineup_primary_pay_summary = function_exists('vms_format_currency') ? vms_format_currency((float) $flat_fee_amount) : ('$' . number_format((float) $flat_fee_amount, 2));
            }
        }

        $lineup_warning_messages = array();
        foreach ($lineup_warnings as $lineup_warning_row) {
            $lineup_warning_messages[] = sanitize_text_field((string) ($lineup_warning_row['message'] ?? ''));
        }

        $availability_boot_trace = function_exists('bvmgr_event_plan_perf_span_start')
            ? bvmgr_event_plan_perf_span_start('event_plan_admin_boot_availability_conflict', (int) $post->ID, array('section' => 'availability_conflict_summary'))
            : '';
        try {
            if (function_exists('bvmgr_event_plan_perf_memory_checkpoint')) {
                bvmgr_event_plan_perf_memory_checkpoint((int) $post->ID, 'vendor_conflict_before', array(
                    'section' => 'availability_conflict_summary',
                    'band_count' => count(is_array($bands) ? $bands : array()),
                    'venue_id' => (int) $venue_id_effective,
                    'selected_supporting_vendor_count' => count($lineup_supporting_selected_vendor_ids),
                ), 'vendor_conflict');
            }
            $vendor_option_context = $this->build_event_plan_vendor_option_context(
                (int) $post->ID,
                is_array($bands) ? $bands : array(),
                (string) $event_date,
                (int) $venue_id_effective,
                $selected_band_id,
                $lineup_supporting_selected_vendor_ids,
                false
            );
            if (function_exists('bvmgr_event_plan_perf_memory_checkpoint')) {
                bvmgr_event_plan_perf_memory_checkpoint((int) $post->ID, 'vendor_conflict_after', array(
                    'section' => 'availability_conflict_summary',
                    'primary_option_count' => count((array) ($vendor_option_context['primary_rows'] ?? array())),
                    'supporting_option_count' => count((array) ($vendor_option_context['supporting_rows'] ?? array())),
                ), 'vendor_conflict');
            }
        } finally {
            if (function_exists('bvmgr_event_plan_perf_span_finish')) {
                bvmgr_event_plan_perf_span_finish('event_plan_admin_boot_availability_conflict', (int) $post->ID, $availability_boot_trace, array(
                    'section' => 'availability_conflict_summary',
                ));
            }
        }
        $lineup_primary_vendor_option_rows = is_array($vendor_option_context['primary_rows'] ?? null)
            ? $vendor_option_context['primary_rows']
            : array();
        $lineup_primary_vendor_option_html = is_string($vendor_option_context['primary_option_html'] ?? null)
            ? (string) $vendor_option_context['primary_option_html']
            : '';
        $lineup_supporting_vendor_option_rows = is_array($vendor_option_context['supporting_rows'] ?? null)
            ? $vendor_option_context['supporting_rows']
            : array();
        $lineup_supporting_vendor_option_html = is_string($vendor_option_context['supporting_option_html'] ?? null)
            ? (string) $vendor_option_context['supporting_option_html']
            : '';
        $lineup_supporting_default_fee_map = array();
        foreach ($lineup_supporting_vendor_option_rows as $lineup_supporting_vendor_row) {
            if (!is_array($lineup_supporting_vendor_row)) {
                continue;
            }
            $lineup_supporting_vendor_id = absint($lineup_supporting_vendor_row['vendor_id'] ?? 0);
            if ($lineup_supporting_vendor_id <= 0) {
                continue;
            }
            $lineup_supporting_default_fee_map[$lineup_supporting_vendor_id] = (string) ($lineup_supporting_vendor_row['default_fee'] ?? '');
        }
        $render_primary_vendor_select_options = function (int $selected_id) use ($lineup_primary_vendor_option_rows, $lineup_primary_vendor_option_html) {
            if ($lineup_primary_vendor_option_html !== '') {
                echo $lineup_primary_vendor_option_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                return;
            }
            echo $this->render_event_plan_primary_vendor_selected_option_html($lineup_primary_vendor_option_rows, $selected_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        };
        $render_lineup_vendor_select_options = function (int $selected_id) use ($lineup_supporting_vendor_option_rows) {
            echo $this->render_event_plan_supporting_vendor_selected_option_html($lineup_supporting_vendor_option_rows, $selected_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        };
        if (function_exists('bvmgr_event_plan_perf_log')) {
            bvmgr_event_plan_perf_log('event_plan_vendor_options', (int) $post->ID, array(
                'phase' => 'selected_only',
                'option_mode' => 'selected_rows_only',
                'primary_option_count' => count($lineup_primary_vendor_option_rows),
                'primary_option_payload_bytes' => strlen($lineup_primary_vendor_option_html),
                'supporting_option_count' => count($lineup_supporting_vendor_option_rows),
                'supporting_select_count' => count($lineup_supporting_entries) + 1,
                'shared_option_payload_bytes' => strlen($lineup_supporting_vendor_option_html),
            ));
        }

        $vendor_summary_trace = function_exists('bvmgr_event_plan_perf_span_start')
            ? bvmgr_event_plan_perf_span_start('event_plan_admin_boot_vendor_summary', (int) $post->ID, array('section' => 'vendor_summary'))
            : '';
        try {
            $vendor_summary_context = array(
                'primary_vendor_assigned' => $selected_band_id > 0 ? 1 : 0,
                'supporting_vendor_count' => count($lineup_supporting_entries),
                'primary_vendor_option_count' => count($lineup_primary_vendor_option_rows),
            );
        } finally {
            if (function_exists('bvmgr_event_plan_perf_span_finish')) {
                bvmgr_event_plan_perf_span_finish('event_plan_admin_boot_vendor_summary', (int) $post->ID, $vendor_summary_trace, array(
                    'section' => 'vendor_summary',
                    'primary_vendor_assigned' => $selected_band_id > 0 ? 1 : 0,
                    'supporting_vendor_count' => count($lineup_supporting_entries),
                    'primary_vendor_option_count' => count($lineup_primary_vendor_option_rows),
                ));
            }
        }

        $secondary_vendor_lazy_enabled = $this->should_defer_event_plan_admin_section((int) $post->ID, 'secondary_vendors');
        $secondary_state_trace = function_exists('bvmgr_event_plan_perf_span_start')
            ? bvmgr_event_plan_perf_span_start('event_plan_admin_boot_secondary_vendor_state', (int) $post->ID, array('section' => 'secondary_vendor_state'))
            : '';
        $secondary_vendor_boot_summary = array();
        try {
            $secondary_vendor_boot_summary = $this->get_event_plan_secondary_vendor_boot_summary(
                (int) $post->ID,
                is_array($bands) ? $bands : array(),
                (string) $event_date,
                is_array($secondary_vendor_assignments) ? $secondary_vendor_assignments : array(),
                !$secondary_vendor_lazy_enabled
            );
            $secondary_state_context = array(
                'secondary_vendor_type' => count((array) $secondary_vendor_assignments) === 1 ? (string) array_key_first((array) $secondary_vendor_assignments) : 'multi',
                'secondary_vendor_count' => count($secondary_vendor_ids),
                'secondary_vendor_pool_count' => array_sum(array_map('count', (array) ($secondary_vendor_boot_summary['type_pool_map'] ?? array()))),
                'secondary_vendor_warning_count' => count((array) ($secondary_vendor_boot_summary['secondary_missing'] ?? array()))
                    + count((array) ($secondary_vendor_boot_summary['secondary_mismatch'] ?? array()))
                    + count((array) ($secondary_vendor_boot_summary['secondary_unqualified'] ?? array())),
            );
        } finally {
            if (function_exists('bvmgr_event_plan_perf_span_finish')) {
                bvmgr_event_plan_perf_span_finish('event_plan_admin_boot_secondary_vendor_state', (int) $post->ID, $secondary_state_trace, array(
                    'section' => 'secondary_vendor_state',
                    'secondary_vendor_type' => count((array) $secondary_vendor_assignments) === 1 ? (string) array_key_first((array) $secondary_vendor_assignments) : 'multi',
                    'secondary_vendor_count' => count($secondary_vendor_ids),
                    'secondary_vendor_pool_count' => array_sum(array_map('count', (array) ($secondary_vendor_boot_summary['type_pool_map'] ?? array()))),
                    'secondary_vendor_warning_count' => count((array) ($secondary_vendor_boot_summary['secondary_missing'] ?? array()))
                        + count((array) ($secondary_vendor_boot_summary['secondary_mismatch'] ?? array()))
                        + count((array) ($secondary_vendor_boot_summary['secondary_unqualified'] ?? array())),
                ));
            }
        }

        $staffing_summary_trace = function_exists('bvmgr_event_plan_perf_span_start')
            ? bvmgr_event_plan_perf_span_start('event_plan_admin_boot_staffing_summary', (int) $post->ID, array('section' => 'staffing_summary'))
            : '';
        try {
            $staff_assignment_total = 0;
            foreach ($staff_assignments as $staff_role_assignments) {
                if (!is_array($staff_role_assignments)) {
                    continue;
                }
                $staff_assignment_total += count(array_filter(array_map('absint', $staff_role_assignments)));
            }
        } finally {
            if (function_exists('bvmgr_event_plan_perf_span_finish')) {
                bvmgr_event_plan_perf_span_finish('event_plan_admin_boot_staffing_summary', (int) $post->ID, $staffing_summary_trace, array(
                    'section' => 'staffing_summary',
                    'staff_role_count' => count($staff_assignments),
                    'staff_assignment_count' => $staff_assignment_total,
                    'staffing_render_mode' => $this->should_defer_event_plan_admin_section((int) $post->ID, 'staff') ? 'deferred' : 'full',
                ));
            }
        }

        $integrity_trace = function_exists('bvmgr_event_plan_perf_span_start')
            ? bvmgr_event_plan_perf_span_start('event_plan_admin_boot_integrity_flags', (int) $post->ID, array('section' => 'integrity_flags'))
            : '';
        $integrity_boot_summary = array();
        try {
            $integrity_boot_summary = $this->get_event_plan_integrity_boot_summary((int) $post->ID);
            $issue = sanitize_key((string) ($integrity_boot_summary['integrity_issue'] ?? ''));
            if ($issue === 'none') {
                $issue = '';
            }
        } finally {
            if (function_exists('bvmgr_event_plan_perf_span_finish')) {
                bvmgr_event_plan_perf_span_finish('event_plan_admin_boot_integrity_flags', (int) $post->ID, $integrity_trace, array(
                    'section' => 'integrity_flags',
                    'integrity_issue' => $issue !== '' ? sanitize_key($issue) : 'none',
                ));
            }
        }

        // ----------------------------
        // Render UI
        // ----------------------------
?>

        <?php
            if ($issue === 'missing_vendor') {
                $k_vt = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_vendor_title') : '_vms_integrity_vendor_title';
                $vendor_title = (string) get_post_meta($post->ID, $k_vt, true);
                if ($vendor_title === '') $vendor_title = __('(unknown vendor)', 'backstage-venue-manager');

                echo '<div class="notice notice-error inline vms-notice vms-notice--critical"><p>' .
                    esc_html__('🚩 This event plan lost its vendor (the vendor was deleted) and needs attention.', 'backstage-venue-manager') .
                    /* translators: %s: previous vendor. */
                    ' ' . sprintf(esc_html__('Previous vendor: %s', 'backstage-venue-manager'), esc_html($vendor_title)) .
                    ' ' . esc_html__('Select a new Primary Vendor, then mark Ready again.', 'backstage-venue-manager') .
                    '</p></div>';
            } elseif ($issue === 'missing_secondary_vendor') {
                $k_vt = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_vendor_title') : '_vms_integrity_vendor_title';
                $vendor_title = (string) get_post_meta($post->ID, $k_vt, true);
                if ($vendor_title === '') $vendor_title = __('(unknown vendor)', 'backstage-venue-manager');

                echo '<div class="notice notice-warning inline vms-notice vms-notice--warning"><p>' .
                    esc_html__('🚩 This event plan lost a secondary vendor (the vendor was deleted) and needs attention.', 'backstage-venue-manager') .
                    /* translators: %s: removed vendor. */
                    ' ' . sprintf(esc_html__('Removed vendor: %s', 'backstage-venue-manager'), esc_html($vendor_title)) .
                    ' ' . esc_html__('Review the Additional Vendors section below, then mark Ready again if needed.', 'backstage-venue-manager') .
                    '</p></div>';
            }
        ?>

        <?php
            if ($post->post_status === 'auto-draft' && isset($admin_get['vms_prefill_vendor_id'], $admin_get['vms_prefill_vendor_mode'])) {
                $prefill_vendor_id = absint($admin_get['vms_prefill_vendor_id']);
                $prefill_mode = sanitize_key((string) $admin_get['vms_prefill_vendor_mode']);
                $prefill_vendor_label = isset($admin_get['vms_prefill_vendor_label']) ? sanitize_text_field((string) $admin_get['vms_prefill_vendor_label']) : '';
                if ($prefill_vendor_id > 0) {
                    $resolved_vendor_label = $prefill_vendor_label !== '' ? $prefill_vendor_label : (string) get_the_title($prefill_vendor_id);
                    if ($resolved_vendor_label === '') {
                        $resolved_vendor_label = __('Selected vendor', 'backstage-venue-manager');
                    }

                    if ($prefill_mode === 'secondary') {
                        $prefill_type_label = '';
                        if (isset($admin_get['vms_prefill_vendor_type'])) {
                            $prefill_type_slug = function_exists('bvmgr_vendor_type_normalize_slug')
                                ? bvmgr_vendor_type_normalize_slug((string) $admin_get['vms_prefill_vendor_type'])
                                : sanitize_title((string) $admin_get['vms_prefill_vendor_type']);
                            if ($prefill_type_slug !== '') {
                                $prefill_type_term = function_exists('bvmgr_vendor_type_get_term')
                                    ? bvmgr_vendor_type_get_term($prefill_type_slug)
                                    : get_term_by('slug', $prefill_type_slug, 'vms_vendor_type');
                                if ($prefill_type_term instanceof WP_Term) {
                                    $prefill_type_label = (string) $prefill_type_term->name;
                                }
                            }
                        }

                        $secondary_message = ($prefill_type_label !== '')
                            /* translators: 1: value 1 used in this message, 2: value 2 used in this message. */
                            ? sprintf(__('Booking prefill: %1$s was added as a secondary vendor (%2$s). Review the Additional Vendors section below, then save the Event Plan.', 'backstage-venue-manager'), $resolved_vendor_label, $prefill_type_label)
                            /* translators: %s: booking prefill. */
                            : sprintf(__('Booking prefill: %s was added as a secondary vendor. Review the Additional Vendors section below, then save the Event Plan.', 'backstage-venue-manager'), $resolved_vendor_label);
                        echo '<div class="notice notice-info inline vms-notice"><p>' . esc_html($secondary_message) . '</p></div>';
                    } elseif ($prefill_mode === 'primary') {
                        /* translators: %s: booking prefill. */
                        $primary_message = sprintf(__('Booking prefill: %s was added as the primary vendor. Review below, then save the Event Plan.', 'backstage-venue-manager'), $resolved_vendor_label);
                        echo '<div class="notice notice-info inline vms-notice"><p>' . esc_html($primary_message) . '</p></div>';
                    }
                }
            }
        ?>

        <div
            class="vms-ep-basic-grid"
            <?php echo $scroll_to !== '' ? ' data-vms-scroll-target="' . esc_attr($scroll_to) . '"' : ''; ?>
            data-vms-lazy-loading-label="<?php echo esc_attr__('Loading section editor…', 'backstage-venue-manager'); ?>"
            data-vms-lazy-error-label="<?php echo esc_attr__('Unable to load this editor section right now. Refresh and try again.', 'backstage-venue-manager'); ?>"
        >
        <p class="vms-ep-basic-item">
            <label for="vms_event_date"><strong><?php esc_html_e('Event Date', 'backstage-venue-manager'); ?></strong></label><br />
            <input type="date" id="vms_event_date" name="vms_event_date" value="<?php echo esc_attr($event_date); ?>"<?php echo $occurrence_locked ? ' disabled aria-disabled="true"' : ''; ?> />
            <?php if ($occurrence_locked) : ?>
                <br /><span class="description"><?php esc_html_e('Published event dates are protected. Use the controlled change below.', 'backstage-venue-manager'); ?></span>
            <?php endif; ?>
        </p>

        <?php if ($occurrence_locked && function_exists('bvmgr_event_occurrence_render_admin_panel')) : ?>
            <div class="vms-ep-basic-item vms-ep-basic-span">
                <?php bvmgr_event_occurrence_render_admin_panel((int) $post->ID); ?>
            </div>
        <?php endif; ?>

        <p class="vms-ep-basic-item">
            <label for="vms_venue_id"><strong><?php esc_html_e('Venue', 'backstage-venue-manager'); ?></strong></label><br />
            <select id="vms_venue_id" name="vms_venue_id" class="vms-ep-select-md" required>
                <option value=""><?php esc_html_e('-- Select a Venue --', 'backstage-venue-manager'); ?></option>
                <?php foreach ($venues as $venue): ?>
                    <option value="<?php echo esc_attr($venue->ID); ?>" <?php selected($venue_id_effective, $venue->ID); ?>>
                        <?php echo esc_html($venue->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <br /><span class="description"><?php esc_html_e('Required. This scopes the event plan to a specific venue.', 'backstage-venue-manager'); ?></span>
        </p>

        <?php
        // Holiday panel
        $holiday = null;
        if ($venue_id_effective > 0 && $event_date && function_exists('bvmgr_get_venue_holiday_for_date')) {
            $holiday = bvmgr_get_venue_holiday_for_date($venue_id_effective, $event_date);
        }

        echo '<div class="vms-ep-basic-item vms-ep-basic-span">';
        echo '<h4>' . esc_html__('Holiday', 'backstage-venue-manager') . '</h4>';
        echo '<div class="vms-ep-holiday-card">';

        if ($venue_id_effective <= 0 || !$event_date) {
            echo '<p class="description vms-m0">' . esc_html__('Select a Venue and Event Date to see holiday status.', 'backstage-venue-manager') . '</p>';
        } elseif (!$holiday) {
            echo '<p class="description vms-m0">' . esc_html__('No holiday is configured for this venue on the selected date.', 'backstage-venue-manager') . '</p>';
            echo '<p class="description vms-mt-8 vms-mb-0">' . esc_html__('Holiday pay is role-dependent and will apply automatically once holidays are configured.', 'backstage-venue-manager') . '</p>';
        } else {
            $badge_class = (($holiday['status'] ?? '') === 'closed') ? 'vms-ep-badge vms-ep-badge--closed' : 'vms-ep-badge vms-ep-badge--open';

            echo '<p class="vms-m0 vms-mb-8">';
            echo '<span class="' . esc_attr($badge_class) . '">';
            echo (($holiday['status'] ?? '') === 'closed') ? esc_html__('CLOSED', 'backstage-venue-manager') : esc_html__('OPEN', 'backstage-venue-manager');
            echo '</span>';

            $name = trim((string)($holiday['name'] ?? ''));
            if ($name !== '') {
                echo ' <strong class="vms-ml-8">' . esc_html($name) . '</strong>';
            }
            echo '</p>';

            if (($holiday['status'] ?? '') === 'closed') {
                echo '<p class="description vms-m0">' . esc_html__('This venue is marked CLOSED on this holiday. This Event Plan cannot be marked READY or Published.', 'backstage-venue-manager') . '</p>';
            } else {
                echo '<p class="description vms-m0">' . esc_html__('Holiday pay/hours are role-dependent and will be applied automatically (once holiday rules are configured).', 'backstage-venue-manager') . '</p>';
            }
        }

        // ---------------------------------
        // Compute "default pay" for this venue/date
        // Priority: holiday vendor defaults > venue defaults (AJAX endpoint mirrors this, but we need a server-side baseline too)
        // ---------------------------------
        $vms_norm_num = function($v) {
            if ($v === null) return '';
            if (is_string($v)) $v = trim($v);
            if ($v === '') return '';
            if (!is_numeric($v)) return (string) $v;
            $s = rtrim(rtrim(sprintf('%.4f', (float) $v), '0'), '.');
            return $s;
        };
        $resolved_default = function_exists('bvmgr_get_event_plan_effective_comp_default')
            ? (array) bvmgr_get_event_plan_effective_comp_default((int) $venue_id_effective, (string) $event_date)
            : array();

        $default_source = isset($resolved_default['source']) ? sanitize_key((string) $resolved_default['source']) : '';
        $default_label = isset($resolved_default['label']) ? (string) $resolved_default['label'] : '';
        $default = array(
            'structure' => isset($resolved_default['structure']) ? (string) $resolved_default['structure'] : '',
            'flat_fee_amount' => $vms_norm_num($resolved_default['flat_fee_amount'] ?? ''),
            'door_split_percent' => $vms_norm_num($resolved_default['door_split_percent'] ?? ''),
            'attendance_bonus_mode' => isset($resolved_default['attendance_bonus_mode']) ? (string) $resolved_default['attendance_bonus_mode'] : '',
            'attendance_bonus_start_count' => $vms_norm_num($resolved_default['attendance_bonus_start_count'] ?? ''),
            'attendance_bonus_step_size' => $vms_norm_num($resolved_default['attendance_bonus_step_size'] ?? ''),
            'attendance_bonus_step_bonus' => $vms_norm_num($resolved_default['attendance_bonus_step_bonus'] ?? ''),
            'attendance_bonus_per_ticket_rate' => $vms_norm_num($resolved_default['attendance_bonus_per_ticket_rate'] ?? ''),
            'attendance_bonus_max_bonus' => $vms_norm_num($resolved_default['attendance_bonus_max_bonus'] ?? ''),
        );

        // Current "actual" is the Draft Pay fields
        $actual = array(
            'structure' => (string)$comp_structure,
            'flat_fee_amount' => $vms_norm_num($flat_fee_amount),
            'door_split_percent' => $vms_norm_num($door_split_percent),
            'attendance_bonus_mode' => (string) $attendance_bonus_mode,
            'attendance_bonus_start_count' => $vms_norm_num($attendance_bonus_start_count),
            'attendance_bonus_step_size' => $vms_norm_num($attendance_bonus_step_size),
            'attendance_bonus_step_bonus' => $vms_norm_num($attendance_bonus_step_bonus),
            'attendance_bonus_per_ticket_rate' => $vms_norm_num($attendance_bonus_per_ticket_rate),
            'attendance_bonus_max_bonus' => $vms_norm_num($attendance_bonus_max_bonus),
            'deposit_amount' => $vms_norm_num($deposit_amount),
            'deposit_status' => (string) $deposit_status,
            'deposit_treatment' => (string) $deposit_treatment,
            'deposit_due_date' => (string) $deposit_due_date,
            'deposit_paid_date' => (string) $deposit_paid_date,
            'deposit_notes' => (string) $deposit_notes,
        );

// Existing audit trail values (display only)
        extract($this->get_event_plan_compensation_render_context(
            (int) $post->ID,
            $default,
            $actual,
            (string) $comp_structure,
            $flat_fee_amount,
            $door_split_percent,
            $attendance_bonus_mode,
            $attendance_bonus_start_count,
            $attendance_bonus_step_size,
            $attendance_bonus_step_bonus,
            $attendance_bonus_per_ticket_rate,
            $attendance_bonus_max_bonus,
            $commission_percent,
            $commission_mode,
            $comp_opts,
            $selected_band_id,
            (int) $venue_id_effective,
            (string) $event_date
        ), EXTR_SKIP);


        ?>

        <?php
        echo '</div></div>';
        ?>


    <?php $vms_time_lineup_html = $this->capture_event_plan_partial('time-lineup', get_defined_vars()); ?>
    <?php echo $vms_time_lineup_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

    <?php $vms_title_html = $this->capture_event_plan_partial('title', get_defined_vars()); ?>
    <?php echo $vms_title_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

    <?php $vms_comp_defaults_nonce = wp_create_nonce('bvmgr_get_venue_comp_defaults'); ?>
    <?php $vms_compensation_html = $this->capture_event_plan_partial('compensation', get_defined_vars()); ?>
    <?php $vms_compensation_html = str_replace('id="vms-comp-options" data-nonce="', 'id="vms-comp-options" data-defaults-nonce="' . esc_attr($vms_comp_defaults_nonce) . '" data-nonce="', $vms_compensation_html); ?>
    <?php echo $vms_compensation_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

    <?php
        $vms_secondary_group_count = count((array) ($secondary_vendor_boot_summary['assignment_groups'] ?? array()));
        $vms_secondary_has_data = ($vms_secondary_group_count > 0 || !empty($secondary_vendor_ids));
        $vms_secondary_warning_count = count((array) ($secondary_vendor_boot_summary['secondary_missing'] ?? array()))
            + count((array) ($secondary_vendor_boot_summary['secondary_mismatch'] ?? array()))
            + count((array) ($secondary_vendor_boot_summary['secondary_unqualified'] ?? array()));
        $vms_secondary_summary_bits = array();
        if ($vms_secondary_group_count === 1) {
            $vms_first_secondary_group = (array) reset($secondary_vendor_boot_summary['assignment_groups']);
            $vms_secondary_type_name = trim((string) ($vms_first_secondary_group['type_name'] ?? ''));
            $vms_secondary_type_slug = sanitize_key((string) ($vms_first_secondary_group['type_slug'] ?? ''));
            if ($vms_secondary_type_name !== '' || $vms_secondary_type_slug !== '') {
                $vms_secondary_summary_bits[] = $vms_secondary_type_name !== '' ? $vms_secondary_type_name : $vms_secondary_type_slug;
            }
        } elseif ($vms_secondary_group_count > 1) {
            /* translators: %d: number of groups. */
            $vms_secondary_summary_bits[] = sprintf(_n('%d group', '%d groups', $vms_secondary_group_count, 'backstage-venue-manager'), $vms_secondary_group_count);
        }
        /* translators: %d: number of selected items. */
        $vms_secondary_summary_bits[] = sprintf(_n('%d selected', '%d selected', count((array) $secondary_vendor_ids), 'backstage-venue-manager'), count((array) $secondary_vendor_ids));
        /* translators: %d: number of warnings. */
        $vms_secondary_summary_bits[] = sprintf(_n('%d warning', '%d warnings', $vms_secondary_warning_count, 'backstage-venue-manager'), $vms_secondary_warning_count);
    ?>
    <?php if ($secondary_vendor_lazy_enabled) : ?>
        <?php
            if (function_exists('bvmgr_event_plan_perf_log')) {
                bvmgr_event_plan_perf_log('event_plan_vendor_conflict_details', (int) $post->ID, array(
                    'phase' => 'lazy_available',
                    'lazy_load' => 1,
                    'section' => 'secondary_vendors',
                ));
                bvmgr_event_plan_perf_log('event_plan_secondary_vendor_render', (int) $post->ID, array(
                    'phase' => 'summary_only',
                    'lazy_load' => 1,
                    'section' => 'secondary_vendors',
                    'skip_reason' => 'collapsed_initial_load',
                    'secondary_vendor_warning_count' => $vms_secondary_warning_count,
                ));
            }
            if (function_exists('bvmgr_event_plan_perf_memory_checkpoint')) {
                bvmgr_event_plan_perf_memory_checkpoint((int) $post->ID, 'secondary_vendors_shell_before', array(
                    'section' => 'secondary_vendors_shell',
                    'secondary_vendor_warning_count' => $vms_secondary_warning_count,
                ), 'secondary_vendors_shell');
            }
        ?>
        <section
            id="vms-additional-vendors"
            class="vms-collapsible-section"
            data-section-key="secondary_vendors"
            data-has-data="<?php echo $vms_secondary_has_data ? '1' : '0'; ?>"
            data-vms-lazy-section="secondary_vendors"
            data-vms-lazy-loaded="0"
            data-vms-lazy-post-id="<?php echo (int) $post->ID; ?>"
            data-vms-lazy-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
            data-vms-lazy-nonce="<?php echo esc_attr(wp_create_nonce('bvmgr_event_plan_admin_section')); ?>"
        >
            <button type="button" class="vms-collapsible-toggle" aria-expanded="false">
                <span class="vms-collapsible-chevron" aria-hidden="true"></span>
                <span class="vms-collapsible-label"><?php esc_html_e('Additional Vendors', 'backstage-venue-manager'); ?></span>
                <span class="vms-collapsible-meta"><?php echo esc_html(implode(' • ', array_filter($vms_secondary_summary_bits))); ?></span>
                <span class="vms-collapsible-flag" aria-hidden="true" hidden><?php esc_html_e('Changed', 'backstage-venue-manager'); ?></span>
            </button>
            <div class="vms-collapsible-body" hidden>
                <div class="vms-ep-card vms-ep-card--white vms-ep-card--secondary-vendors" data-vms-section-has-data="<?php echo $vms_secondary_has_data ? '1' : '0'; ?>">
                    <p class="description"><?php esc_html_e('Expand this section to load vendor availability, qualification warnings, and editable additional-vendor rows.', 'backstage-venue-manager'); ?></p>
                    <input type="hidden" name="vms_secondary_vendors_lazy_unloaded" value="1" />
                    <p class="description vms-m0"><?php echo esc_html(implode(' • ', array_filter($vms_secondary_summary_bits))); ?></p>
                </div>
            </div>
        </section>
        <?php
            if (function_exists('bvmgr_event_plan_perf_memory_checkpoint')) {
                bvmgr_event_plan_perf_memory_checkpoint((int) $post->ID, 'secondary_vendors_shell_after', array(
                    'section' => 'secondary_vendors_shell',
                    'secondary_vendor_warning_count' => $vms_secondary_warning_count,
                ), 'secondary_vendors_shell');
            }
        ?>
        <?php else : ?>
        <?php $vms_secondary_vendors_html = $this->capture_event_plan_partial('secondary-vendors', get_defined_vars()); ?>
        <?php if (!empty($vms_secondary_vendors_html)): ?>
        <h4 id="vms-additional-vendors" class="vms-collapsible-title" data-section-key="secondary_vendors" data-section-has-data="<?php echo $vms_secondary_has_data ? '1' : '0'; ?>"><?php esc_html_e('Additional Vendors', 'backstage-venue-manager'); ?></h4>
        <div class="vms-ep-card vms-ep-card--white vms-ep-card--secondary-vendors" data-vms-section-has-data="<?php echo $vms_secondary_has_data ? '1' : '0'; ?>">
            <?php echo $vms_secondary_vendors_html; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php
        $vms_staff_lazy_enabled = $this->should_defer_event_plan_admin_section((int) $post->ID, 'staff');
        $vms_staff_nonce = wp_create_nonce('bvmgr_event_plan_admin_section');
        $vms_staff_has_data_hint = !empty($staff_assignments);
    ?>
    <?php if ($vms_staff_lazy_enabled) : ?>
        <?php
            if (function_exists('bvmgr_event_plan_perf_log')) {
                bvmgr_event_plan_perf_log('event_plan_staff_section_render', (int) $post->ID, array(
                    'phase' => 'summary_only',
                    'lazy_load' => 1,
                    'section' => 'staff',
                    'skip_reason' => 'collapsed_initial_load',
                ));
            }
            if (function_exists('bvmgr_event_plan_perf_memory_checkpoint')) {
                bvmgr_event_plan_perf_memory_checkpoint((int) $post->ID, 'staff_shell_before', array(
                    'section' => 'staff_shell',
                    'lazy_load' => 1,
                ), 'staff_shell');
            }
        ?>
        <section
            id="vms-staffing"
            class="vms-collapsible-section"
            data-section-key="staff"
            data-has-data="<?php echo $vms_staff_has_data_hint ? '1' : '0'; ?>"
            data-vms-lazy-section="staff"
            data-vms-lazy-loaded="0"
            data-vms-lazy-post-id="<?php echo (int) $post->ID; ?>"
            data-vms-lazy-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
            data-vms-lazy-nonce="<?php echo esc_attr($vms_staff_nonce); ?>"
        >
            <button type="button" class="vms-collapsible-toggle" aria-expanded="false">
                <span class="vms-collapsible-chevron" aria-hidden="true"></span>
                <span class="vms-collapsible-label"><?php esc_html_e('Staff', 'backstage-venue-manager'); ?></span>
                <span class="vms-collapsible-flag" aria-hidden="true" hidden><?php esc_html_e('Changed', 'backstage-venue-manager'); ?></span>
            </button>
            <div class="vms-collapsible-body" hidden>
                <div class="vms-ep-card vms-ep-card--white vms-ep-card--staff" data-vms-section-has-data="<?php echo $vms_staff_has_data_hint ? '1' : '0'; ?>">
                    <p class="description"><?php esc_html_e('Expand this section to load the staffing editor, assignments, and shift windows.', 'backstage-venue-manager'); ?></p>
                    <input type="hidden" name="vms_staffing_lazy_unloaded" value="1" />
                    <p class="description vms-m0"><?php esc_html_e('The staffing editor is deferred on initial load to reduce Event Plan open time.', 'backstage-venue-manager'); ?></p>
                </div>
            </div>
        </section>
        <?php
            if (function_exists('bvmgr_event_plan_perf_memory_checkpoint')) {
                bvmgr_event_plan_perf_memory_checkpoint((int) $post->ID, 'staff_shell_after', array(
                    'section' => 'staff_shell',
                    'lazy_load' => 1,
                ), 'staff_shell');
            }
        ?>
    <?php else : ?>
        <?php
            extract($this->get_event_plan_staff_render_context((int) $post->ID, $staff_assignments), EXTR_SKIP);
        ?>
        <?php $vms_staff_html = $this->capture_event_plan_partial('staff', get_defined_vars()); ?>
        <?php echo $vms_staff_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <?php endif; ?>

    <?php
        $readiness_trace = function_exists('bvmgr_event_plan_perf_span_start')
            ? bvmgr_event_plan_perf_span_start('event_plan_admin_boot_readiness', (int) $post->ID, array('section' => 'readiness_status_checks'))
            : '';
        $readiness_boot_summary = array();
        try {
            $readiness_boot_summary = $this->get_event_plan_readiness_boot_summary(
                (int) $post->ID,
                array(
                    'primary_rows' => $lineup_primary_vendor_option_rows,
                ),
                isset($secondary_vendor_boot_summary) && is_array($secondary_vendor_boot_summary) ? $secondary_vendor_boot_summary : array()
            );
            $vms_workflow_status_html = $this->capture_event_plan_partial('workflow-status', get_defined_vars());
        } finally {
            if (function_exists('bvmgr_event_plan_perf_span_finish')) {
                bvmgr_event_plan_perf_span_finish('event_plan_admin_boot_readiness', (int) $post->ID, $readiness_trace, array(
                    'section' => 'readiness_status_checks',
                    'publish_blocking_warning' => absint($readiness_boot_summary['publish_blocking_warning'] ?? 0),
                    'secondary_vendor_warning_count' => absint($readiness_boot_summary['secondary_vendor_warning_count'] ?? 0),
                    'linked_tec_present' => absint($readiness_boot_summary['linked_tec_present'] ?? 0),
                ));
            }
        }
        $vms_readiness_summary_context = $this->build_event_plan_readiness_summary_context(
            (int) $post->ID,
            $readiness_boot_summary,
            $secondary_vendor_boot_summary
        );
        $vms_readiness_lazy_enabled = $this->should_defer_event_plan_admin_section((int) $post->ID, 'readiness_details');
        $vms_readiness_summary_rows = isset($vms_readiness_summary_context['summary_rows']) && is_array($vms_readiness_summary_context['summary_rows'])
            ? $vms_readiness_summary_context['summary_rows']
            : array();
        $vms_readiness_warning_items = isset($vms_readiness_summary_context['warning_items']) && is_array($vms_readiness_summary_context['warning_items'])
            ? $vms_readiness_summary_context['warning_items']
            : array();
    ?>
    <?php if (function_exists('bvmgr_event_plan_perf_memory_checkpoint')) {
        bvmgr_event_plan_perf_memory_checkpoint((int) $post->ID, 'readiness_summary_before', array(
            'section' => 'readiness_summary',
            'warning_item_count' => count($vms_readiness_warning_items),
        ), 'readiness_summary');
    } ?>
    <div class="vms-ep-card vms-ep-card--white vms-ep-card--readiness-summary" data-vms-collapsible-break="1">
        <h4><?php esc_html_e('Readiness Summary', 'backstage-venue-manager'); ?></h4>
        <p class="description"><?php echo esc_html((string) ($vms_readiness_summary_context['status_label'] ?? __('No blocking publish warnings', 'backstage-venue-manager'))); ?></p>
        <?php if (!empty($vms_readiness_summary_rows)) : ?>
            <ul class="vms-ep-inline-list">
                <?php foreach ($vms_readiness_summary_rows as $vms_readiness_row) : ?>
                    <?php if (!is_array($vms_readiness_row)) { continue; } ?>
                    <li>
                        <strong><?php echo esc_html((string) ($vms_readiness_row['label'] ?? '')); ?>:</strong>
                        <?php echo esc_html((string) ($vms_readiness_row['value'] ?? '')); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if (!empty($vms_readiness_warning_items)) : ?>
	            <?php /* translators: %d: number of readiness warnings. */ ?>
	            <p class="description"><?php echo esc_html(sprintf(_n('%d warning needs attention. Expand Readiness details below for the full list.', '%d warnings need attention. Expand Readiness details below for the full list.', count($vms_readiness_warning_items), 'backstage-venue-manager'), count($vms_readiness_warning_items))); ?></p>
        <?php endif; ?>
    </div>
    <?php if (function_exists('bvmgr_event_plan_perf_memory_checkpoint')) {
        bvmgr_event_plan_perf_memory_checkpoint((int) $post->ID, 'readiness_summary_after', array(
            'section' => 'readiness_summary',
            'warning_item_count' => count($vms_readiness_warning_items),
            'payload_size_bytes' => absint($vms_readiness_summary_context['payload_size_bytes'] ?? 0),
        ), 'readiness_summary');
    } ?>
    <?php if ($vms_readiness_lazy_enabled) : ?>
        <?php
            if (function_exists('bvmgr_event_plan_perf_log')) {
                bvmgr_event_plan_perf_log('event_plan_readiness_details', (int) $post->ID, array(
                    'phase' => 'lazy_available',
                    'lazy_load' => 1,
                    'section' => 'readiness_details',
                    'blocking_issue_count' => absint($readiness_boot_summary['blocking_issue_count'] ?? 0),
                    'warning_item_count' => count($vms_readiness_warning_items),
                ));
            }
        ?>
        <section
            id="vms-readiness-details"
            class="vms-collapsible-section"
            data-section-key="readiness_details"
            data-has-data="<?php echo (!empty($vms_readiness_warning_items) || !empty($readiness_boot_summary['publish_blocking_warning'])) ? '1' : '0'; ?>"
            data-vms-lazy-section="readiness_details"
            data-vms-lazy-loaded="0"
            data-vms-lazy-post-id="<?php echo (int) $post->ID; ?>"
            data-vms-lazy-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
            data-vms-lazy-nonce="<?php echo esc_attr(wp_create_nonce('bvmgr_event_plan_admin_section')); ?>"
        >
            <button type="button" class="vms-collapsible-toggle" aria-expanded="false">
                <span class="vms-collapsible-chevron" aria-hidden="true"></span>
                <span class="vms-collapsible-label"><?php esc_html_e('Readiness details', 'backstage-venue-manager'); ?></span>
	                <?php
	                /* translators: %d: number of blocking readiness issues. */
	                $vms_readiness_blocking_meta = sprintf(_n('%d blocking issue', '%d blocking issues', absint($readiness_boot_summary['blocking_issue_count'] ?? 0), 'backstage-venue-manager'), absint($readiness_boot_summary['blocking_issue_count'] ?? 0));
	                /* translators: %d: number of readiness warnings. */
	                $vms_readiness_warning_meta = sprintf(_n('%d warning', '%d warnings', count($vms_readiness_warning_items), 'backstage-venue-manager'), count($vms_readiness_warning_items));
	                ?>
	                <span class="vms-collapsible-meta"><?php echo esc_html($vms_readiness_blocking_meta . ' • ' . $vms_readiness_warning_meta); ?></span>
                <span class="vms-collapsible-flag" aria-hidden="true" hidden><?php esc_html_e('Changed', 'backstage-venue-manager'); ?></span>
            </button>
            <div class="vms-collapsible-body" hidden>
                <div class="vms-ep-card vms-ep-card--white">
                    <p class="description"><?php esc_html_e('Expand this section to load detailed readiness reasons, vendor warning categories, and linked ticket/calendar status.', 'backstage-venue-manager'); ?></p>
                </div>
            </div>
        </section>
    <?php endif; ?>
    <?php echo $vms_workflow_status_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	    <?php
        // Scroll helper (optional)
        if ($scroll_to) {
            delete_post_meta($post->ID, '_vms_admin_scroll_to');
        }
    } finally {
            if (function_exists('bvmgr_event_plan_perf_memory_checkpoint')) {
                bvmgr_event_plan_perf_memory_checkpoint((int) $post->ID, 'details_meta_box_after', array(
                    'section' => 'meta_box_render',
                    'capture_dependency_snapshot' => 1,
                ), 'details_meta_box');
            }
            if (function_exists('bvmgr_event_plan_perf_span_finish')) {
                bvmgr_event_plan_perf_span_finish('event_plan_details_meta_box_render', (int) $post->ID, $render_trace, array('section' => 'meta_box_render'));
            }
        }
    }

    /**
     * Save Event Plan meta fields + handle actions
     */
    public function save_event_plan_meta(int $post_id, WP_Post $post): void
    {
        $request = bvmgr_event_plan_editor_verified_post_data();
        if (empty($request)) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        if (isset($request['post_ID']) && absint($request['post_ID']) > 0 && absint($request['post_ID']) !== $post_id) {
            return;
        }

        if (function_exists('bvmgr_event_occurrence_lock_editor_request')) {
            $occurrence_lock = bvmgr_event_occurrence_lock_editor_request($post_id, $request);
            $request = (array) ($occurrence_lock['request'] ?? $request);
            if (!empty($occurrence_lock['blocked']) && function_exists('bvmgr_add_admin_notice')) {
                bvmgr_add_admin_notice(
                    __('The published event date/time was not changed. Use “Change event date…” so linked tickets and reservations can be migrated safely.', 'backstage-venue-manager'),
                    'error'
                );
            }
        }

        $original_status = isset($request['original_post_status']) ? sanitize_key((string) $request['original_post_status']) : sanitize_key((string) $post->post_status);
        $actor_user_id = function_exists('bvmgr_event_plan_capture_actor_user_id')
            ? bvmgr_event_plan_capture_actor_user_id($post_id, (int) get_current_user_id(), 'event_plan_editor_save')
            : (int) get_current_user_id();
        $save_trace = function_exists('bvmgr_event_plan_perf_span_start')
            ? bvmgr_event_plan_perf_span_start(
                'save_post_vms_event_plan_core',
                $post_id,
                array(
                    'create' => ($original_status === 'auto-draft') ? 1 : 0,
                    'update' => ($original_status === 'auto-draft') ? 0 : 1,
                    'old_status' => $original_status,
                    'new_status' => sanitize_key((string) $post->post_status),
                    'actor_user_id' => $actor_user_id,
                )
            )
            : '';

        $reopen_section_after_save = isset($request['vms_reopen_section_after_save'])
            ? bvmgr_event_plan_normalize_reopen_section((string) $request['vms_reopen_section_after_save'])
            : '';
        if ($reopen_section_after_save !== '') {
            bvmgr_event_plan_set_runtime_reopen_section_target($post_id, $reopen_section_after_save);
        }
        $detached_editor_action = isset($request['vms_event_plan_action'])
            ? sanitize_key((string) $request['vms_event_plan_action'])
            : '';
        if ($detached_editor_action === 'resync_to_calendar') {
            if (function_exists('bvmgr_add_admin_notice')) {
                bvmgr_add_admin_notice(__('Re-sync to Calendar now uses a dedicated saved-state action. Reload the Event Plan and use the Advanced Controls Re-sync button.', 'backstage-venue-manager'), 'warning');
            }
            return;
        }
        $ticket_ui_override_save_intent = isset($request['vms_ticket_ui_overrides_save_intent'])
            ? sanitize_text_field((string) $request['vms_ticket_ui_overrides_save_intent'])
            : '';
        if ($ticket_ui_override_save_intent === '1') {
            $ticket_ui_override_result = $this->save_event_plan_ticket_ui_overrides($post_id, $request);
            if (function_exists('bvmgr_add_admin_notice')) {
                bvmgr_add_admin_notice(
                    !empty($ticket_ui_override_result['changed'])
                        ? __('Public UI overrides saved.', 'backstage-venue-manager')
                        : __('No public UI override changes to save.', 'backstage-venue-manager'),
                    'success'
                );
            }
            return;
        }
        $lock_pay_saved_basics_before_save = $this->get_lock_pay_basics_state($post_id);
        $lock_pay_request_basics = $this->get_lock_pay_basics_state($post_id, $request);

	        // The main WP editor content is now the single canonical event description.
	        delete_post_meta($post_id, '_vms_agenda_text');

	        $primary_vendor_submission = function_exists('bvmgr_event_plan_resolve_primary_vendor_submission')
	            ? bvmgr_event_plan_resolve_primary_vendor_submission($post_id, $request)
	            : array(
	                'field_present' => array_key_exists('vms_band_vendor_id', $request),
	                'posted_vendor_id' => array_key_exists('vms_band_vendor_id', $request) ? absint($request['vms_band_vendor_id']) : 0,
	                'clear_requested' => !empty($request['vms_clear_primary_vendor']) || !empty($request['vms_clear_lineup_primary_vendor']),
	                'current_vendor_id' => (int) get_post_meta($post_id, '_vms_band_vendor_id', true),
	                'current_lineup_primary_vendor_id' => (int) get_post_meta($post_id, '_vms_band_vendor_id', true),
	                'effective_vendor_id' => (int) get_post_meta($post_id, '_vms_band_vendor_id', true),
	                'lineup_primary_vendor_id' => (int) get_post_meta($post_id, '_vms_band_vendor_id', true),
	                'should_write' => false,
	            );
	        $effective_band_id = absint($primary_vendor_submission['effective_vendor_id'] ?? 0);
	        $effective_lineup_primary_vendor_id = absint($primary_vendor_submission['lineup_primary_vendor_id'] ?? $effective_band_id);

	        // Band
	        if (!empty($primary_vendor_submission['field_present']) && !empty($primary_vendor_submission['should_write'])) {
	            if ($effective_band_id !== (int) ($primary_vendor_submission['current_vendor_id'] ?? 0)) {
	                update_post_meta($post_id, '_vms_band_vendor_id', $effective_band_id);
	            }

	            // If the plan was previously flagged due to a missing vendor, clear the flag once a valid vendor is re-attached.
	            if ($effective_band_id > 0 && function_exists('bvmgr_event_plan_vendor_exists') && bvmgr_event_plan_vendor_exists((int) $effective_band_id)) {
	                if (function_exists('bvmgr_event_plan_clear_integrity_flags')) {
	                    bvmgr_event_plan_clear_integrity_flags($post_id);
	                }
	            }
	        }

        // Staffing payload capture (structured role-slot model; legacy compatibility preserved in helper).
        $staffing_present = isset($request['vms_staff_assignments_present']) || isset($request['vms_staffing_roles_present']);
        $staffing_lazy_unloaded = !empty($request['vms_staffing_lazy_unloaded']);
        $staffing_raw_assignments = (isset($request['vms_staff_assignments']) && is_array($request['vms_staff_assignments'])) ? (array) $request['vms_staff_assignments'] : array();
        $staffing_headcounts = (isset($request['vms_staff_role_headcount']) && is_array($request['vms_staff_role_headcount'])) ? (array) $request['vms_staff_role_headcount'] : array();
        $staffing_activation_thresholds_raw = (isset($request['vms_staff_role_activation_threshold']) && is_array($request['vms_staff_role_activation_threshold'])) ? (array) $request['vms_staff_role_activation_threshold'] : array();
        $staffing_time_modes = (isset($request['vms_staff_role_time_mode']) && is_array($request['vms_staff_role_time_mode'])) ? (array) $request['vms_staff_role_time_mode'] : array();
        $staffing_shift_starts = (isset($request['vms_staff_role_shift_start']) && is_array($request['vms_staff_role_shift_start'])) ? (array) $request['vms_staff_role_shift_start'] : array();
        $staffing_shift_ends = (isset($request['vms_staff_role_shift_end']) && is_array($request['vms_staff_role_shift_end'])) ? (array) $request['vms_staff_role_shift_end'] : array();
        $staffing_start_anchor_keys = (isset($request['vms_staff_role_start_anchor']) && is_array($request['vms_staff_role_start_anchor'])) ? (array) $request['vms_staff_role_start_anchor'] : array();
        $staffing_start_offsets = (isset($request['vms_staff_role_start_offset']) && is_array($request['vms_staff_role_start_offset'])) ? (array) $request['vms_staff_role_start_offset'] : array();
        $staffing_end_anchor_keys = (isset($request['vms_staff_role_end_anchor']) && is_array($request['vms_staff_role_end_anchor'])) ? (array) $request['vms_staff_role_end_anchor'] : array();
        $staffing_end_offsets = (isset($request['vms_staff_role_end_offset']) && is_array($request['vms_staff_role_end_offset'])) ? (array) $request['vms_staff_role_end_offset'] : array();
        $staffing_duration_minutes = (isset($request['vms_staff_role_duration_minutes']) && is_array($request['vms_staff_role_duration_minutes'])) ? (array) $request['vms_staff_role_duration_minutes'] : array();
        $staffing_activation_thresholds_clean = array();
        $staffing_absolute_time_warning_roles = array();
        $staffing_required_now_gap_roles = array();
        $staffing_role_assignment_warnings = array();
        $staffing_role_assignment_blocked = array();
        if (!$staffing_present && $staffing_lazy_unloaded) {
            if (function_exists('bvmgr_event_plan_perf_log')) {
                bvmgr_event_plan_perf_log('event_plan_staffing_save', $post_id, array(
                    'phase' => 'skip',
                    'dirty_branch' => 'skip',
                    'skip_reason' => 'no_staffing_change',
                    'lazy_unloaded' => 1,
                    'section' => 'staffing_save',
                ));
            }
            if (function_exists('bvmgr_event_plan_save_profiler_note_heavy_action')) {
                bvmgr_event_plan_save_profiler_note_heavy_action(
                    'staffing_save',
                    'skipped',
                    'no_staffing_change'
                );
            }
        }
        if (!empty($staffing_present)) {
            $staff_roles_index = array();
            $all_staff_roles = get_terms(array(
                'taxonomy'   => 'vms_staff_role',
                'hide_empty' => false,
            ));
            if (!is_wp_error($all_staff_roles) && is_array($all_staff_roles)) {
                foreach ($all_staff_roles as $staff_role_term) {
                    if (is_object($staff_role_term) && isset($staff_role_term->term_id)) {
                        $staff_roles_index[(int) $staff_role_term->term_id] = (string) $staff_role_term->name;
                    }
                }
            }

            $staff_role_ids = array_unique(array_merge(
                array_map('intval', array_keys((array) $staffing_headcounts)),
                array_map('intval', array_keys((array) $staffing_raw_assignments)),
                array_map('intval', array_keys((array) $staffing_activation_thresholds_raw)),
                array_map('intval', array_keys((array) $staffing_time_modes)),
                array_map('intval', array_keys((array) $staffing_shift_starts)),
                array_map('intval', array_keys((array) $staffing_shift_ends)),
                array_map('intval', array_keys((array) $staffing_start_anchor_keys)),
                array_map('intval', array_keys((array) $staffing_start_offsets)),
                array_map('intval', array_keys((array) $staffing_end_anchor_keys)),
                array_map('intval', array_keys((array) $staffing_end_offsets)),
                array_map('intval', array_keys((array) $staffing_duration_minutes))
            ));

            $staff_headcount_context = function_exists('bvmgr_staffing_get_event_plan_headcount_context')
                ? (array) bvmgr_staffing_get_event_plan_headcount_context((int) $post_id)
                : array('wired' => false, 'headcount' => 0);
            $staff_current_headcount = max(0, (int) ($staff_headcount_context['headcount'] ?? 0));
            $staff_headcount_wired = !empty($staff_headcount_context['wired']);
            $existing_assigned_staff_by_role = function_exists('bvmgr_staffing_get_event_assigned_staff_map')
                ? (array) bvmgr_staffing_get_event_assigned_staff_map((int) $post_id)
                : array();

            foreach ($staff_role_ids as $staff_role_id) {
                $staff_role_id = absint($staff_role_id);
                if ($staff_role_id <= 0) {
                    continue;
                }

                /* translators: %d: role ID. */
                $role_name = $staff_roles_index[$staff_role_id] ?? sprintf(__('Role #%d', 'backstage-venue-manager'), $staff_role_id);
                $headcount_value = isset($staffing_headcounts[$staff_role_id]) ? max(0, absint($staffing_headcounts[$staff_role_id])) : 0;
                $assigned_staff = isset($staffing_raw_assignments[$staff_role_id]) && is_array($staffing_raw_assignments[$staff_role_id])
                    ? array_values(array_unique(array_filter(array_map('absint', $staffing_raw_assignments[$staff_role_id]), function ($v) {
                        return $v > 0;
                    })))
                    : array();
                $role_in_use = ($headcount_value > 0 || !empty($assigned_staff));

                $threshold_value = null;
                if (array_key_exists($staff_role_id, $staffing_activation_thresholds_raw)) {
                    $threshold_raw = trim((string) $staffing_activation_thresholds_raw[$staff_role_id]);
                    if ($threshold_raw !== '' && is_numeric($threshold_raw)) {
                        $threshold_value = max(0, (int) $threshold_raw);
                    } else {
                        $threshold_value = 0;
                    }
                }
                if ($threshold_value === null) {
                    $threshold_value = $role_in_use ? 1 : 0;
                }
                $staffing_activation_thresholds_clean[$staff_role_id] = $threshold_value;

                $time_mode_value = isset($staffing_time_modes[$staff_role_id]) ? sanitize_key((string) $staffing_time_modes[$staff_role_id]) : 'absolute';
                if (!in_array($time_mode_value, array('absolute', 'relative'), true)) {
                    $time_mode_value = 'absolute';
                }
                $shift_start_value = isset($staffing_shift_starts[$staff_role_id]) ? trim((string) $staffing_shift_starts[$staff_role_id]) : '';
                $shift_end_value = isset($staffing_shift_ends[$staff_role_id]) ? trim((string) $staffing_shift_ends[$staff_role_id]) : '';
                if (!preg_match('/^\d{2}:\d{2}$/', $shift_start_value)) {
                    $shift_start_value = '';
                }
                if (!preg_match('/^\d{2}:\d{2}$/', $shift_end_value)) {
                    $shift_end_value = '';
                }

                if ($role_in_use && $time_mode_value === 'absolute' && ($shift_start_value === '' || ($shift_end_value === '' && (!isset($staffing_duration_minutes[$staff_role_id]) || (int) $staffing_duration_minutes[$staff_role_id] <= 0)))) {
                    $staffing_absolute_time_warning_roles[] = $role_name;
                }

                if ($staff_headcount_wired && $headcount_value > 0 && $staff_current_headcount >= $threshold_value && count($assigned_staff) < $headcount_value) {
                    $staffing_required_now_gap_roles[] = $role_name;
                }

                $existing_assigned_staff = isset($existing_assigned_staff_by_role[$staff_role_id]) && is_array($existing_assigned_staff_by_role[$staff_role_id])
                    ? array_values(array_unique(array_filter(array_map('absint', $existing_assigned_staff_by_role[$staff_role_id]), function ($v) {
                        return $v > 0;
                    })))
                    : array();
                foreach ($assigned_staff as $assigned_staff_id) {
                    $staff_name = get_the_title((int) $assigned_staff_id);
                    $candidate_status = function_exists('bvmgr_staffing_staff_candidate_status_for_role')
                        ? (array) bvmgr_staffing_staff_candidate_status_for_role((int) $assigned_staff_id, (int) $staff_role_id)
                        : array(
                            'eligible' => true,
                            'ineligibility_reason' => '',
                            'qualification' => array('ok' => true, 'mode' => 'warn', 'missing' => array(), 'expired' => array()),
                        );
                    $already_assigned = in_array((int) $assigned_staff_id, $existing_assigned_staff, true);
                    if (empty($candidate_status['eligible'])) {
                        $reason = trim((string) ($candidate_status['ineligibility_reason'] ?? ''));
                        $warning_label = trim((string) $staff_name) . ' → ' . $role_name . ($reason !== '' ? ' (' . $reason . ')' : '');
                        if ($already_assigned) {
                            $staffing_role_assignment_warnings[] = $warning_label;
                        } else {
                            $staffing_role_assignment_blocked[] = $warning_label;
                            if (isset($staffing_raw_assignments[$staff_role_id]) && is_array($staffing_raw_assignments[$staff_role_id])) {
                                $staffing_raw_assignments[$staff_role_id] = array_values(array_filter(array_map('absint', $staffing_raw_assignments[$staff_role_id]), function ($v) use ($assigned_staff_id) {
                                    return $v > 0 && $v !== (int) $assigned_staff_id;
                                }));
                            }
                        }
                        continue;
                    }

                    $qual_check = isset($candidate_status['qualification']) && is_array($candidate_status['qualification'])
                        ? $candidate_status['qualification']
                        : array('ok' => true, 'mode' => 'warn', 'missing' => array(), 'expired' => array());
                    if (!empty($qual_check['ok'])) {
                        continue;
                    }
                    $issues = array();
                    if (!empty($qual_check['missing'])) {
                        /* translators: %s: human-readable value used in this message. */
                        $issues[] = sprintf(__('missing %s', 'backstage-venue-manager'), implode(', ', array_map('sanitize_text_field', (array) $qual_check['missing'])));
                    }
                    if (!empty($qual_check['expired'])) {
                        /* translators: %s: human-readable value used in this message. */
                        $issues[] = sprintf(__('expired %s', 'backstage-venue-manager'), implode(', ', array_map('sanitize_text_field', (array) $qual_check['expired'])));
                    }
                    if (!empty($issues)) {
                        $staffing_role_assignment_warnings[] = trim((string) $staff_name) . ' → ' . $role_name . ' (' . implode('; ', $issues) . ')';
                    }
                }
            }
        }
        $staffing_template_apply_now = !empty($request['vms_staffing_template_apply']);
        $staffing_template_selected_id = isset($request['vms_staffing_template_id']) ? absint($request['vms_staffing_template_id']) : 0;
        $staffing_template_apply_mode = isset($request['vms_staffing_template_mode']) ? sanitize_key((string) $request['vms_staffing_template_mode']) : 'merge_missing';
        if (!in_array($staffing_template_apply_mode, array('merge_missing', 'replace_all'), true)) {
            $staffing_template_apply_mode = 'merge_missing';
        }

        // Core basics: only write these when the field was explicitly posted.
        $event_date_posted = array_key_exists('vms_event_date', $request);
        $start_time_posted = array_key_exists('vms_start_time', $request);
        $end_time_posted = array_key_exists('vms_end_time', $request);
        $venue_id_posted = array_key_exists('vms_venue_id', $request);

        $event_date = $event_date_posted
            ? sanitize_text_field((string) $request['vms_event_date'])
            : (string) get_post_meta($post_id, '_vms_event_date', true);

        $start_time = $start_time_posted
            ? sanitize_text_field((string) $request['vms_start_time'])
            : (string) get_post_meta($post_id, '_vms_start_time', true);

        $end_time = $end_time_posted
            ? sanitize_text_field((string) $request['vms_end_time'])
            : (string) get_post_meta($post_id, '_vms_end_time', true);

        $venue_id = $venue_id_posted
            ? absint($request['vms_venue_id'])
            : (int) get_post_meta($post_id, '_vms_venue_id', true);

		// Public ticket sales destination. This is intentionally separate from the
		// existing Phase B none/read_only/vms_managed synchronization mode.
		if (array_key_exists('vms_ticketing_sales_mode', $request)) {
			$sales_mode = function_exists('bvmgr_event_plan_normalize_ticketing_sales_mode')
				? bvmgr_event_plan_normalize_ticketing_sales_mode($request['vms_ticketing_sales_mode'])
				: (sanitize_key((string) $request['vms_ticketing_sales_mode']) === 'external' ? 'external' : 'serenade_range');
			$key = function_exists('bvmgr_event_plan_ticketing_meta_key')
				? bvmgr_event_plan_ticketing_meta_key('ticketing_sales_mode', '_vms_ticketing_sales_mode')
				: '_vms_ticketing_sales_mode';
			update_post_meta($post_id, $key, $sales_mode);
		}

		if (array_key_exists('vms_external_ticket_url', $request)) {
			$raw_external_url = is_array($request['vms_external_ticket_url']) ? '' : trim((string) $request['vms_external_ticket_url']);
			$previous_external_url = function_exists('bvmgr_event_plan_get_external_ticket_url')
				? bvmgr_event_plan_get_external_ticket_url($post_id)
				: trim((string) get_post_meta($post_id, '_vms_external_ticket_url', true));
			if ($previous_external_url !== '' && function_exists('bvmgr_tec_capture_legacy_event_url_ownership')) {
				bvmgr_tec_capture_legacy_event_url_ownership($post_id, $previous_external_url);
			}
			$external_url = function_exists('bvmgr_event_plan_sanitize_external_ticket_url')
				? bvmgr_event_plan_sanitize_external_ticket_url($raw_external_url)
				: esc_url_raw($raw_external_url, array('http', 'https'));
			$key = function_exists('bvmgr_event_plan_ticketing_meta_key')
				? bvmgr_event_plan_ticketing_meta_key('external_ticket_url', '_vms_external_ticket_url')
				: '_vms_external_ticket_url';
			if ($external_url !== '') {
				update_post_meta($post_id, $key, $external_url);
			} else {
				delete_post_meta($post_id, $key);
				if ($raw_external_url !== '' && function_exists('bvmgr_add_admin_notice')) {
					bvmgr_add_admin_notice(__('The external ticket URL was not saved. Enter a complete http:// or https:// URL.', 'backstage-venue-manager'), 'error');
				}
			}
		}

		$text_fields = array(
			'vms_external_ticket_provider' => array('external_ticket_provider', '_vms_external_ticket_provider'),
			'vms_external_event_producer' => array('external_event_producer', '_vms_external_event_producer'),
		);
		foreach ($text_fields as $request_key => $meta_definition) {
			if (!array_key_exists($request_key, $request)) {
				continue;
			}
			$value = is_array($request[$request_key]) ? '' : sanitize_text_field((string) $request[$request_key]);
			$key = function_exists('bvmgr_event_plan_ticketing_meta_key')
				? bvmgr_event_plan_ticketing_meta_key((string) $meta_definition[0], (string) $meta_definition[1])
				: (string) $meta_definition[1];
			if ($value !== '') {
				update_post_meta($post_id, $key, $value);
			} else {
				delete_post_meta($post_id, $key);
			}
		}

		if (array_key_exists('vms_external_event_producer_website', $request)) {
			$raw_producer_website = is_array($request['vms_external_event_producer_website']) ? '' : trim((string) $request['vms_external_event_producer_website']);
			$producer_website = function_exists('bvmgr_event_plan_sanitize_external_event_producer_website')
				? bvmgr_event_plan_sanitize_external_event_producer_website($raw_producer_website)
				: esc_url_raw($raw_producer_website, array('http', 'https'));
			$key = function_exists('bvmgr_event_plan_ticketing_meta_key')
				? bvmgr_event_plan_ticketing_meta_key('external_event_producer_website', '_vms_external_event_producer_website')
				: '_vms_external_event_producer_website';
			if ($producer_website !== '') {
				update_post_meta($post_id, $key, $producer_website);
			} else {
				delete_post_meta($post_id, $key);
				if ($raw_producer_website !== '' && function_exists('bvmgr_add_admin_notice')) {
					bvmgr_add_admin_notice(__('The presenter/producer website was not saved. Enter a complete http:// or https:// URL.', 'backstage-venue-manager'), 'error');
				}
			}
		}

		if (array_key_exists('vms_event_relationship', $request)) {
			$relationship = function_exists('bvmgr_event_plan_normalize_relationship')
				? bvmgr_event_plan_normalize_relationship($request['vms_event_relationship'])
				: (sanitize_key((string) $request['vms_event_relationship']) === 'hosted_third_party' ? 'hosted_third_party' : 'serenade_range_produced');
			$key = function_exists('bvmgr_event_plan_ticketing_meta_key')
				? bvmgr_event_plan_ticketing_meta_key('event_relationship', '_vms_event_relationship')
				: '_vms_event_relationship';
			update_post_meta($post_id, $key, $relationship);
		}

	        // Ticketing enabled override (on|off|inherit)
	        if (array_key_exists('vms_ticketing_enabled_override', $request)) {
	            $ov = sanitize_text_field((string) $request['vms_ticketing_enabled_override']);
	            $ticketing_override_audit_pushed = false;
	            if (function_exists('bvmgr_ticket_mutation_audit_push_context')) {
	                bvmgr_ticket_mutation_audit_push_context(array(
	                    'trigger_source' => 'save_hook',
	                    'change_type' => 'event_save_sync',
	                    'source_function' => 'save_event_plan_meta',
	                    'source_hook' => sanitize_key((string) current_filter()),
	                    'summary_text' => __('Saved ticketing enabled override from the Event Plan editor.', 'backstage-venue-manager'),
	                ));
	                $ticketing_override_audit_pushed = true;
	            }
	            if ($ov === 'on' || $ov === 'off') {
	                update_post_meta($post_id, '_vms_ticketing_enabled_override', $ov);
	            } else {
	                delete_post_meta($post_id, '_vms_ticketing_enabled_override');
	            }
	            if ($ticketing_override_audit_pushed && function_exists('bvmgr_ticket_mutation_audit_pop_context')) {
	                bvmgr_ticket_mutation_audit_pop_context();
	            }
	        }

        // Ticketing v2: GA ticket image policy (event_plan|custom|none) + optional custom image attachment ID.
        if (array_key_exists('vms_ticketing_ga_image_mode', $request)) {
            $mode = sanitize_key((string) $request['vms_ticketing_ga_image_mode']);
            if (!in_array($mode, array('event_plan', 'custom', 'none'), true)) {
                $mode = 'event_plan';
            }
            update_post_meta($post_id, '_vms_ticketing_ga_image_mode', $mode);
        }
        if (array_key_exists('vms_ticketing_ga_image_id', $request)) {
            $img_id = absint($request['vms_ticketing_ga_image_id']);
            if ($img_id > 0) {
                update_post_meta($post_id, '_vms_ticketing_ga_image_id', $img_id);
            } else {
                delete_post_meta($post_id, '_vms_ticketing_ga_image_id');
            }
        }

	        // Draft pay
	        $comp_structure = isset($request['vms_comp_structure']) ? sanitize_key((string) $request['vms_comp_structure']) : 'flat_fee';
	        if (!in_array($comp_structure, array('flat_fee', 'door_split', 'flat_fee_door_split', 'attendance_bonus'), true)) {
	            $comp_structure = 'flat_fee';
	        }

                // Preserve requested action for enforcement messaging (may be cleared later if blocked).
                $requested_action_raw = isset($request['vms_event_plan_action']) ? sanitize_text_field((string) $request['vms_event_plan_action']) : '';
                if ($requested_action_raw === 'lock_draft_pay') {
                    $saved_basics_ready = !empty($lock_pay_saved_basics_before_save['ok']);
                    $request_basics_ready = !empty($lock_pay_request_basics['ok']);

                    if (!$saved_basics_ready || !$request_basics_ready) {
                        if (function_exists('bvmgr_add_admin_notice')) {
                            $msg = $this->get_lock_pay_basics_notice_copy();
                            if (!$saved_basics_ready && $request_basics_ready) {
                                $msg .= ' ' . __('Your details were saved. Review them, then click Lock Draft Pay again.', 'backstage-venue-manager');
                            }
                            bvmgr_add_admin_notice($msg, 'error');
                        }

                        update_post_meta($post_id, '_vms_admin_scroll_to', 'vms_event_date');
                        $_POST['vms_event_plan_action'] = '';
                        $request['vms_event_plan_action'] = '';
                        $requested_action_raw = '';
                    }
                }

                // Number inputs are text fields (no spinner arrows). Normalize user input like "$1,200.00".
                $flat_fee_amount_raw = isset($request['vms_flat_fee_amount']) ? (string) $request['vms_flat_fee_amount'] : '';
                $flat_fee_amount_raw = trim($flat_fee_amount_raw);
                $flat_fee_amount_raw = preg_replace('/[^0-9.\-]/', '', $flat_fee_amount_raw);
                $flat_fee_amount = ($flat_fee_amount_raw === '' ? '' : (float) $flat_fee_amount_raw);
                if ($flat_fee_amount !== '' && $flat_fee_amount < 0) $flat_fee_amount = 0;

                $door_split_percent_raw = isset($request['vms_door_split_percent']) ? (string) $request['vms_door_split_percent'] : '';
                $door_split_percent_raw = trim($door_split_percent_raw);
                $door_split_percent_raw = preg_replace('/[^0-9.\-]/', '', $door_split_percent_raw);
	                $door_split_percent = ($door_split_percent_raw === '' ? '' : (float) $door_split_percent_raw);
	                if ($door_split_percent !== '' ) {
	                    if ($door_split_percent < 0) $door_split_percent = 0;
	                    if ($door_split_percent > 100) $door_split_percent = 100;
	                }

	                $attendance_bonus_mode = isset($request['vms_attendance_bonus_mode']) ? sanitize_key((string) $request['vms_attendance_bonus_mode']) : '';
	                if (!in_array($attendance_bonus_mode, array('step', 'continuous'), true)) {
	                    $attendance_bonus_mode = '';
	                }

	                $attendance_bonus_start_count_raw = isset($request['vms_attendance_bonus_start_count']) ? (string) $request['vms_attendance_bonus_start_count'] : '';
	                $attendance_bonus_start_count_raw = trim($attendance_bonus_start_count_raw);
	                $attendance_bonus_start_count_raw = preg_replace('/[^0-9.\-]/', '', $attendance_bonus_start_count_raw);
	                $attendance_bonus_start_count = '';
	                if ($attendance_bonus_start_count_raw !== '' && is_numeric($attendance_bonus_start_count_raw)) {
	                    $attendance_bonus_start_count = max(0, (int) floor((float) $attendance_bonus_start_count_raw));
	                }

	                $attendance_bonus_step_size_raw = isset($request['vms_attendance_bonus_step_size']) ? (string) $request['vms_attendance_bonus_step_size'] : '';
	                $attendance_bonus_step_size_raw = trim($attendance_bonus_step_size_raw);
	                $attendance_bonus_step_size_raw = preg_replace('/[^0-9.\-]/', '', $attendance_bonus_step_size_raw);
	                $attendance_bonus_step_size = '';
	                if ($attendance_bonus_step_size_raw !== '' && is_numeric($attendance_bonus_step_size_raw)) {
	                    $parsed_step_size = (int) floor((float) $attendance_bonus_step_size_raw);
	                    if ($parsed_step_size >= 1) {
	                        $attendance_bonus_step_size = $parsed_step_size;
	                    }
	                }

	                $attendance_bonus_step_bonus_raw = isset($request['vms_attendance_bonus_step_bonus']) ? (string) $request['vms_attendance_bonus_step_bonus'] : '';
	                $attendance_bonus_step_bonus_raw = trim($attendance_bonus_step_bonus_raw);
	                $attendance_bonus_step_bonus_raw = preg_replace('/[^0-9.\-]/', '', $attendance_bonus_step_bonus_raw);
	                $attendance_bonus_step_bonus = ($attendance_bonus_step_bonus_raw === '' ? '' : (float) $attendance_bonus_step_bonus_raw);
	                if ($attendance_bonus_step_bonus !== '' && $attendance_bonus_step_bonus < 0) $attendance_bonus_step_bonus = 0;

	                $attendance_bonus_per_ticket_rate_raw = isset($request['vms_attendance_bonus_per_ticket_rate']) ? (string) $request['vms_attendance_bonus_per_ticket_rate'] : '';
	                $attendance_bonus_per_ticket_rate_raw = trim($attendance_bonus_per_ticket_rate_raw);
	                $attendance_bonus_per_ticket_rate_raw = preg_replace('/[^0-9.\-]/', '', $attendance_bonus_per_ticket_rate_raw);
	                $attendance_bonus_per_ticket_rate = ($attendance_bonus_per_ticket_rate_raw === '' ? '' : (float) $attendance_bonus_per_ticket_rate_raw);
	                if ($attendance_bonus_per_ticket_rate !== '' && $attendance_bonus_per_ticket_rate < 0) $attendance_bonus_per_ticket_rate = 0;

	                $attendance_bonus_max_bonus_raw = isset($request['vms_attendance_bonus_max_bonus']) ? (string) $request['vms_attendance_bonus_max_bonus'] : '';
	                $attendance_bonus_max_bonus_raw = trim($attendance_bonus_max_bonus_raw);
	                $attendance_bonus_max_bonus_raw = preg_replace('/[^0-9.\-]/', '', $attendance_bonus_max_bonus_raw);
	                $attendance_bonus_max_bonus = ($attendance_bonus_max_bonus_raw === '' ? '' : (float) $attendance_bonus_max_bonus_raw);
	                if ($attendance_bonus_max_bonus !== '' && $attendance_bonus_max_bonus < 0) $attendance_bonus_max_bonus = 0;

	                $commission_percent_raw = isset($request['vms_commission_percent']) ? (string) $request['vms_commission_percent'] : '';
	                $commission_percent_raw = trim($commission_percent_raw);
	                $commission_percent_raw = preg_replace('/[^0-9.\-]/', '', $commission_percent_raw);
	                $commission_percent = ($commission_percent_raw === '' ? '' : (float) $commission_percent_raw);
	                if ($commission_percent !== '' && $commission_percent < 0) $commission_percent = 0;
                $commission_mode = isset($request['vms_commission_mode']) ? sanitize_key((string) $request['vms_commission_mode']) : 'artist_fee';
                if (!in_array($commission_mode, array('artist_fee', 'gross'), true)) $commission_mode = 'artist_fee';

                $deposit_amount_raw = isset($request['vms_deposit_amount']) ? (string) $request['vms_deposit_amount'] : '';
                $deposit_amount_raw = trim($deposit_amount_raw);
                $deposit_amount_raw = preg_replace('/[^0-9.\-]/', '', $deposit_amount_raw);
                $deposit_amount = ($deposit_amount_raw === '' ? '' : (float) $deposit_amount_raw);
                if ($deposit_amount !== '' && $deposit_amount < 0) $deposit_amount = 0;

                $deposit_status = isset($request['vms_deposit_status']) ? sanitize_key((string) $request['vms_deposit_status']) : 'not_required';
                $deposit_status = function_exists('bvmgr_normalize_comp_deposit_status') ? bvmgr_normalize_comp_deposit_status($deposit_status) : $deposit_status;
                $deposit_treatment = isset($request['vms_deposit_treatment']) ? sanitize_key((string) $request['vms_deposit_treatment']) : 'creditable';
                $deposit_treatment = function_exists('bvmgr_normalize_comp_deposit_treatment') ? bvmgr_normalize_comp_deposit_treatment($deposit_treatment) : $deposit_treatment;
                $deposit_due_date = isset($request['vms_deposit_due_date']) ? sanitize_text_field((string) $request['vms_deposit_due_date']) : '';
                $deposit_due_date = function_exists('bvmgr_normalize_comp_deposit_date') ? bvmgr_normalize_comp_deposit_date($deposit_due_date) : $deposit_due_date;
                $deposit_paid_date = isset($request['vms_deposit_paid_date']) ? sanitize_text_field((string) $request['vms_deposit_paid_date']) : '';
                $deposit_paid_date = function_exists('bvmgr_normalize_comp_deposit_date') ? bvmgr_normalize_comp_deposit_date($deposit_paid_date) : $deposit_paid_date;
                $deposit_notes = isset($request['vms_deposit_notes']) ? sanitize_textarea_field((string) $request['vms_deposit_notes']) : '';
                if ($deposit_amount !== '' && (float) $deposit_amount > 0 && $deposit_status === 'not_required') {
                    $deposit_status = 'unpaid';
                }
                $deposit_terms_for_save = array(
                    'deposit_amount' => $deposit_amount,
                    'deposit_status' => $deposit_status,
                    'deposit_treatment' => $deposit_treatment,
                    'deposit_due_date' => $deposit_due_date,
                    'deposit_paid_date' => $deposit_paid_date,
                    'deposit_notes' => $deposit_notes,
                );

                $final_payment_timing = isset($request['vms_final_payment_timing']) ? sanitize_key((string) $request['vms_final_payment_timing']) : 'not_set';
                $final_payment_timing = function_exists('bvmgr_normalize_comp_final_payment_timing') ? bvmgr_normalize_comp_final_payment_timing($final_payment_timing) : $final_payment_timing;
                $final_payment_days_after = isset($request['vms_final_payment_days_after']) ? sanitize_text_field((string) $request['vms_final_payment_days_after']) : '';
                $final_payment_days_after = function_exists('bvmgr_normalize_comp_final_payment_days_after') ? bvmgr_normalize_comp_final_payment_days_after($final_payment_days_after) : $final_payment_days_after;
                $final_payment_date = isset($request['vms_final_payment_date']) ? sanitize_text_field((string) $request['vms_final_payment_date']) : '';
                $final_payment_date = function_exists('bvmgr_normalize_comp_final_payment_date') ? bvmgr_normalize_comp_final_payment_date($final_payment_date) : $final_payment_date;
                $final_payment_custom_text = isset($request['vms_final_payment_custom_text']) ? sanitize_text_field((string) $request['vms_final_payment_custom_text']) : '';
                $final_payment_method = isset($request['vms_final_payment_method']) ? sanitize_key((string) $request['vms_final_payment_method']) : 'not_set';
                $final_payment_method = function_exists('bvmgr_normalize_comp_final_payment_method') ? bvmgr_normalize_comp_final_payment_method($final_payment_method) : $final_payment_method;
                $final_payment_method_other = isset($request['vms_final_payment_method_other']) ? sanitize_text_field((string) $request['vms_final_payment_method_other']) : '';
                $final_payment_terms_for_save = array(
                    'final_payment_timing' => $final_payment_timing,
                    'final_payment_days_after' => $final_payment_days_after,
                    'final_payment_date' => $final_payment_date,
                    'final_payment_custom_text' => $final_payment_custom_text,
                    'final_payment_method' => $final_payment_method,
                    'final_payment_method_other' => $final_payment_method_other,
                );

                $k_commission_override_none = function_exists('bvmgr_meta_key')
                    ? (bvmgr_meta_key('event_plan', 'commission_override_none') ?: '_vms_commission_override_none')
                    : '_vms_commission_override_none';
                $selected_comp_option_posted = isset($request['vms_comp_selected_option'])
                    ? sanitize_text_field((string) $request['vms_comp_selected_option'])
                    : '';

                $attendance_invalid_message = '';
	                if ($comp_structure === 'attendance_bonus' && $attendance_bonus_mode === 'step' && trim($attendance_bonus_step_size_raw) !== '' && $attendance_bonus_step_size === '') {
	                    $attendance_invalid_message = __('Step Size must be at least 1 for Base + Attendance Bonus step mode.', 'backstage-venue-manager');
	                }
	                if ($attendance_invalid_message !== '' && function_exists('bvmgr_add_admin_notice')) {
	                    bvmgr_add_admin_notice($attendance_invalid_message, 'error');
	                    if (function_exists('bvmgr_admin_scroll_to_compensation')) {
	                        bvmgr_admin_scroll_to_compensation($post_id);
	                    }
	                }

                // ---------------------------------
                // Pay override enforcement (bombproof)
                // Rule: If Draft Pay differs from computed default pay for venue/date (including holiday),
                // user must acknowledge before ANY save is allowed.
                // ---------------------------------

                $ack = (isset($request['vms_pay_override_ack']) && (string) $request['vms_pay_override_ack'] === '1');

                // Single-ack UX: one checkbox can satisfy both pay-diff and low-guarantee gates.
                $low_guarantee_ack = $ack || (isset($request['vms_low_guarantee_ack']) && (string) $request['vms_low_guarantee_ack'] === '1');

	                $actual = array(
	                    'structure' => (string) $comp_structure,
	                    'flat_fee_amount' => ($flat_fee_amount === '' || $flat_fee_amount === null) ? null : (float) $flat_fee_amount,
	                    'door_split_percent' => ($door_split_percent === '' || $door_split_percent === null) ? null : (float) $door_split_percent,
	                    'attendance_bonus_mode' => $attendance_bonus_mode,
	                    'attendance_bonus_start_count' => ($attendance_bonus_start_count === '' || $attendance_bonus_start_count === null) ? null : (int) $attendance_bonus_start_count,
	                    'attendance_bonus_step_size' => ($attendance_bonus_step_size === '' || $attendance_bonus_step_size === null) ? null : (int) $attendance_bonus_step_size,
	                    'attendance_bonus_step_bonus' => ($attendance_bonus_step_bonus === '' || $attendance_bonus_step_bonus === null) ? null : (float) $attendance_bonus_step_bonus,
	                    'attendance_bonus_per_ticket_rate' => ($attendance_bonus_per_ticket_rate === '' || $attendance_bonus_per_ticket_rate === null) ? null : (float) $attendance_bonus_per_ticket_rate,
	                    'attendance_bonus_max_bonus' => ($attendance_bonus_max_bonus === '' || $attendance_bonus_max_bonus === null) ? null : (float) $attendance_bonus_max_bonus,
	                );

                // Low-guarantee acknowledgment (based on the highest guaranteed option available).
                $selected_guarantee = ($actual['structure'] === 'door_split')
                    ? 0.0
                    : max(0.0, (float) ($actual['flat_fee_amount'] ?? 0.0));

                $guarantee_max = 0.0;
	                if (function_exists('bvmgr_get_event_plan_comp_options')) {
	                    $venue_for_opts = isset($request['vms_venue_id']) ? absint($request['vms_venue_id']) : 0;
	                    $date_for_opts  = isset($request['vms_event_date']) ? sanitize_text_field((string) $request['vms_event_date']) : '';
	                    $band_for_opts  = $effective_band_id;
	                    $opts = (array) bvmgr_get_event_plan_comp_options($venue_for_opts, $date_for_opts, $band_for_opts);
	                    $guarantee_max = isset($opts['max_guarantee']) ? (float) $opts['max_guarantee'] : 0.0;
	                }
                if ($guarantee_max < 0) $guarantee_max = 0.0;

                $requires_low_guarantee_ack = ($guarantee_max > 0 && $selected_guarantee < $guarantee_max);

	                $default = array(
	                    'source' => '',
	                    'label'  => '',
	                    'holiday_name' => '',
	                    'structure' => '',
	                    'flat_fee_amount' => null,
	                    'door_split_percent' => null,
	                    'attendance_bonus_mode' => '',
	                    'attendance_bonus_start_count' => null,
	                    'attendance_bonus_step_size' => null,
	                    'attendance_bonus_step_bonus' => null,
	                    'attendance_bonus_per_ticket_rate' => null,
	                    'attendance_bonus_max_bonus' => null,
	                );

                $has_default = false;
                if (function_exists('bvmgr_get_event_plan_effective_comp_default')) {
                    $resolved_default = (array) bvmgr_get_event_plan_effective_comp_default((int) $venue_id, (string) $event_date);
                    $default['source'] = isset($resolved_default['source']) ? sanitize_key((string) $resolved_default['source']) : '';
                    $default['label'] = isset($resolved_default['label']) ? (string) $resolved_default['label'] : '';
                    $default['holiday_name'] = isset($resolved_default['holiday_name']) ? (string) $resolved_default['holiday_name'] : '';
                    $default['structure'] = isset($resolved_default['structure']) ? (string) $resolved_default['structure'] : '';

                    if (array_key_exists('flat_fee_amount', $resolved_default) && $resolved_default['flat_fee_amount'] !== '' && $resolved_default['flat_fee_amount'] !== null) {
                        $default['flat_fee_amount'] = (float) $resolved_default['flat_fee_amount'];
                    }
	                    if (array_key_exists('door_split_percent', $resolved_default) && $resolved_default['door_split_percent'] !== '' && $resolved_default['door_split_percent'] !== null) {
	                        $default['door_split_percent'] = (float) $resolved_default['door_split_percent'];
	                    }
	                    if (array_key_exists('attendance_bonus_mode', $resolved_default) && $resolved_default['attendance_bonus_mode'] !== '' && $resolved_default['attendance_bonus_mode'] !== null) {
	                        $default['attendance_bonus_mode'] = (string) $resolved_default['attendance_bonus_mode'];
	                    }
	                    if (array_key_exists('attendance_bonus_start_count', $resolved_default) && $resolved_default['attendance_bonus_start_count'] !== '' && $resolved_default['attendance_bonus_start_count'] !== null) {
	                        $default['attendance_bonus_start_count'] = (int) $resolved_default['attendance_bonus_start_count'];
	                    }
	                    if (array_key_exists('attendance_bonus_step_size', $resolved_default) && $resolved_default['attendance_bonus_step_size'] !== '' && $resolved_default['attendance_bonus_step_size'] !== null) {
	                        $default['attendance_bonus_step_size'] = (int) $resolved_default['attendance_bonus_step_size'];
	                    }
	                    if (array_key_exists('attendance_bonus_step_bonus', $resolved_default) && $resolved_default['attendance_bonus_step_bonus'] !== '' && $resolved_default['attendance_bonus_step_bonus'] !== null) {
	                        $default['attendance_bonus_step_bonus'] = (float) $resolved_default['attendance_bonus_step_bonus'];
	                    }
	                    if (array_key_exists('attendance_bonus_per_ticket_rate', $resolved_default) && $resolved_default['attendance_bonus_per_ticket_rate'] !== '' && $resolved_default['attendance_bonus_per_ticket_rate'] !== null) {
	                        $default['attendance_bonus_per_ticket_rate'] = (float) $resolved_default['attendance_bonus_per_ticket_rate'];
	                    }
	                    if (array_key_exists('attendance_bonus_max_bonus', $resolved_default) && $resolved_default['attendance_bonus_max_bonus'] !== '' && $resolved_default['attendance_bonus_max_bonus'] !== null) {
	                        $default['attendance_bonus_max_bonus'] = (float) $resolved_default['attendance_bonus_max_bonus'];
	                    }

	                    $has_default = (!empty($resolved_default['has_default']) && $default['structure'] !== '');
	                }

                $differs = false;

	                if ($has_default) {
	                    if ($default['structure'] !== '' && $actual['structure'] !== '' && $default['structure'] !== $actual['structure']) {
	                        $differs = true;
	                    }
	                    if ($default['flat_fee_amount'] !== null && $default['flat_fee_amount'] !== $actual['flat_fee_amount']) {
	                        $differs = true;
	                    }
	                    if ($default['door_split_percent'] !== null && $default['door_split_percent'] !== $actual['door_split_percent']) {
	                        $differs = true;
	                    }
	                    if (($default['structure'] === 'attendance_bonus' || $actual['structure'] === 'attendance_bonus')) {
	                        if ($default['attendance_bonus_mode'] !== '' && $default['attendance_bonus_mode'] !== $actual['attendance_bonus_mode']) {
	                            $differs = true;
	                        }
	                        if ($default['attendance_bonus_start_count'] !== null && $default['attendance_bonus_start_count'] !== $actual['attendance_bonus_start_count']) {
	                            $differs = true;
	                        }
	                        if ($default['attendance_bonus_step_size'] !== null && $default['attendance_bonus_step_size'] !== $actual['attendance_bonus_step_size']) {
	                            $differs = true;
	                        }
	                        if ($default['attendance_bonus_step_bonus'] !== null && $default['attendance_bonus_step_bonus'] !== $actual['attendance_bonus_step_bonus']) {
	                            $differs = true;
	                        }
	                        if ($default['attendance_bonus_per_ticket_rate'] !== null && $default['attendance_bonus_per_ticket_rate'] !== $actual['attendance_bonus_per_ticket_rate']) {
	                            $differs = true;
	                        }
	                        if ($default['attendance_bonus_max_bonus'] !== null && $default['attendance_bonus_max_bonus'] !== $actual['attendance_bonus_max_bonus']) {
	                            $differs = true;
	                        }
	                    }
	                }

                // Enforce acknowledgment if mismatch
                if ($has_default && $differs && !$ack) {

                    $requested_action = isset($request['vms_event_plan_action']) ? sanitize_text_field((string) $request['vms_event_plan_action']) : '';
                    $needs_ack_to_proceed = in_array($requested_action, array('mark_ready', 'publish_now', 'lock_draft_pay'), true);

if (function_exists('bvmgr_add_admin_notice')) {
                        $label = ($default['label'] !== '' ? $default['label'] : __('defaults', 'backstage-venue-manager'));
                        if ($needs_ack_to_proceed) {
                            $verb = __('Mark Ready', 'backstage-venue-manager');
                            if ($requested_action === 'publish_now') {
                                $verb = __('Publish', 'backstage-venue-manager');
                            } elseif ($requested_action === 'lock_draft_pay') {
                                $verb = __('Lock Draft Pay', 'backstage-venue-manager');
                            }

                            $message = sprintf(
                                /* translators: 1: default pay configuration label, 2: requested status action label. */
                                __('Draft Pay differs from %1$s. Acknowledge the difference before %2$s. Your edits were saved, but the status action was blocked.', 'backstage-venue-manager'),
                                $label,
                                $verb
                            );
                            bvmgr_add_admin_notice($message, 'error');
                        } else {
                            $message = sprintf(
                                /* translators: %s: default pay configuration label. */
                                __('Draft Pay differs from %s. You can keep saving Draft changes, but you must acknowledge before Mark Ready, Publish, or Lock Draft Pay.', 'backstage-venue-manager'),
                                $label
                            );
                            bvmgr_add_admin_notice($message, 'warning');
                        }
                    }

                    if (function_exists('bvmgr_admin_scroll_to_compensation')) {
                        bvmgr_admin_scroll_to_compensation($post_id);
                    }

                    // Clear any prior acknowledgment so it cannot linger incorrectly
                    delete_post_meta($post_id, '_vms_pay_override_ack');
                    delete_post_meta($post_id, '_vms_pay_override_ack_ts');
                    delete_post_meta($post_id, '_vms_pay_override_ack_user_id');
                    delete_post_meta($post_id, '_vms_pay_override_ack_default_snapshot');
                    delete_post_meta($post_id, '_vms_pay_override_ack_actual_snapshot');

                    // Block status actions until the override has been acknowledged (do not block saving the edits).
                    if ($needs_ack_to_proceed) {
                        $_POST['vms_event_plan_action'] = '';
                        $request['vms_event_plan_action'] = '';
                    }

                }


                // ---------------------------------
                // Low-guarantee structure acknowledgment (bombproof)
                // Rule: If Draft Pay has a lower guaranteed payout than the highest guaranteed option available
                // from the option tiles, user must acknowledge before Mark Ready, Publish, or Lock Draft Pay.
                // ---------------------------------

                $k_low_ack = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'low_guarantee_ack') : '_vms_low_guarantee_ack';
                $k_low_ack_ts = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'low_guarantee_ack_ts') : '_vms_low_guarantee_ack_ts';
                $k_low_ack_user = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'low_guarantee_ack_user_id') : '_vms_low_guarantee_ack_user_id';
                $k_low_ack_snapshot = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'low_guarantee_ack_snapshot') : '_vms_low_guarantee_ack_snapshot';

                $needs_low_ack_to_proceed = in_array($requested_action_raw, array('mark_ready', 'publish_now', 'lock_draft_pay'), true);

                if ($requires_low_guarantee_ack && !$low_guarantee_ack) {

                    if (function_exists('bvmgr_add_admin_notice')) {
                        $sel = '$' . number_format_i18n((float) $selected_guarantee, 2);
                        $max = '$' . number_format_i18n((float) $guarantee_max, 2);

                        if ($needs_low_ack_to_proceed) {
                            $verb = __('Mark Ready', 'backstage-venue-manager');
                            if ($requested_action_raw === 'publish_now') {
                                $verb = __('Publish', 'backstage-venue-manager');
                            } elseif ($requested_action_raw === 'lock_draft_pay') {
                                $verb = __('Lock Draft Pay', 'backstage-venue-manager');
                            }

                            $message = sprintf(
                                /* translators: 1: selected guarantee amount, 2: highest available guarantee amount, 3: requested status action label. */
                                __('Draft Pay has a lower guaranteed payout (%1$s) than the highest guaranteed option (%2$s). Acknowledge this choice before %3$s. Your edits were saved, but the status action was blocked.', 'backstage-venue-manager'),
                                $sel,
                                $max,
                                $verb
                            );
                            bvmgr_add_admin_notice($message, 'error');
                        } else {
                            $message = sprintf(
                                /* translators: 1: selected guarantee amount, 2: highest available guarantee amount. */
                                __('Draft Pay has a lower guaranteed payout (%1$s) than the highest guaranteed option (%2$s). You can keep saving Draft changes, but you must acknowledge before Mark Ready, Publish, or Lock Draft Pay.', 'backstage-venue-manager'),
                                $sel,
                                $max
                            );
                            bvmgr_add_admin_notice($message, 'warning');
                        }
                    }

                    if (function_exists('bvmgr_admin_scroll_to_compensation')) {
                        bvmgr_admin_scroll_to_compensation($post_id);
                    }

                    // Clear any prior acknowledgment so it cannot linger incorrectly
                    delete_post_meta($post_id, $k_low_ack);
                    delete_post_meta($post_id, $k_low_ack_ts);
                    delete_post_meta($post_id, $k_low_ack_user);
                    delete_post_meta($post_id, $k_low_ack_snapshot);

                    // Block status actions until acknowledged (do not block saving the edits).
                    if ($needs_low_ack_to_proceed) {
                        $_POST['vms_event_plan_action'] = '';
                        $request['vms_event_plan_action'] = '';
                    }
                }

                $lineup_request_keys = array(
                    'vms_lineup_entries',
                    'vms_band_vendor_id',
                    'vms_start_time',
                    'vms_end_time',
                    'vms_event_date',
                    'vms_venue_id',
                );
                $lineup_request_present = false;
                foreach ($lineup_request_keys as $lineup_request_key) {
                    if (array_key_exists($lineup_request_key, $request)) {
                        $lineup_request_present = true;
                        break;
                    }
                }

                $posted_lineup_rows = (isset($request['vms_lineup_entries']) && is_array($request['vms_lineup_entries']))
                    ? (array) $request['vms_lineup_entries']
                    : array();

		                $lineup_context = array(
		                    'legacy_primary_vendor_id' => $effective_lineup_primary_vendor_id,
		                    'event_start' => $start_time,
		                    'event_end' => $end_time,
		                    'venue_id' => $venue_id,
		                    'event_date' => $event_date,
		                );
	                $lineup_save_needed = $lineup_request_present;
	                $lineup_skip_reason = $lineup_request_present ? '' : 'lineup_not_posted';
		                if ($lineup_request_present) {
		                    if (!isset($posted_lineup_rows['primary']) || !is_array($posted_lineup_rows['primary'])) {
		                        $posted_lineup_rows['primary'] = array();
		                    }
		                    $posted_lineup_rows['primary']['vendor_id'] = $effective_lineup_primary_vendor_id;

	                    if (function_exists('bvmgr_normalize_event_plan_lineup_entries')) {
	                        $lineup_key = function_exists('bvmgr_lineup_schedule_meta_key')
	                            ? bvmgr_lineup_schedule_meta_key('lineup_entries_v1', '_vms_lineup_entries_v1')
	                            : '_vms_lineup_entries_v1';
	                        $lineup_band_key = function_exists('bvmgr_lineup_schedule_meta_key')
	                            ? bvmgr_lineup_schedule_meta_key('band_vendor_id', '_vms_band_vendor_id')
	                            : '_vms_band_vendor_id';
	                        $lineup_index_key = function_exists('bvmgr_lineup_schedule_meta_key')
	                            ? bvmgr_lineup_schedule_meta_key('lineup_entry_vendor_id', '_vms_lineup_entry_vendor_id')
	                            : '_vms_lineup_entry_vendor_id';

	                        $current_lineup = get_post_meta($post_id, $lineup_key, true);
	                        $current_lineup = is_array($current_lineup) ? array_values($current_lineup) : array();
	                        $next_lineup = array_values(bvmgr_normalize_event_plan_lineup_entries($posted_lineup_rows, $lineup_context));

	                        $current_primary_vendor_id = absint(get_post_meta($post_id, $lineup_band_key, true));
	                        $next_primary_vendor_id = 0;
	                        $next_vendor_ids = array();
	                        foreach ($next_lineup as $entry) {
	                            if (!is_array($entry)) {
	                                continue;
	                            }
	                            $vendor_id = absint($entry['vendor_id'] ?? 0);
	                            if ($vendor_id > 0 && !in_array($vendor_id, $next_vendor_ids, true)) {
	                                $next_vendor_ids[] = $vendor_id;
	                            }
	                            if ($next_primary_vendor_id <= 0 && sanitize_key((string) ($entry['role'] ?? '')) === 'primary') {
	                                $next_primary_vendor_id = $vendor_id;
	                            }
	                        }
	                        sort($next_vendor_ids, SORT_NUMERIC);

	                        $current_vendor_ids = array_values(array_unique(array_filter(array_map('absint', get_post_meta($post_id, $lineup_index_key, false)))));
	                        sort($current_vendor_ids, SORT_NUMERIC);

	                        if (
	                            maybe_serialize($current_lineup) === maybe_serialize($next_lineup)
	                            && $current_primary_vendor_id === $next_primary_vendor_id
	                            && $current_vendor_ids === $next_vendor_ids
	                        ) {
	                            $lineup_save_needed = false;
	                            $lineup_skip_reason = 'no_lineup_change';
	                        }
	                    }
	                }

	                $lineup_save_trace = function_exists('bvmgr_event_plan_perf_span_start')
	                    ? bvmgr_event_plan_perf_span_start('event_plan_lineup_save', $post_id, array('section' => 'lineup_save'))
	                    : '';
	                if (!$lineup_save_needed) {
	                    if (function_exists('bvmgr_event_plan_save_profiler_note_heavy_action')) {
	                        bvmgr_event_plan_save_profiler_note_heavy_action('lineup_save', 'skipped', $lineup_skip_reason);
	                    }
	                } elseif (function_exists('bvmgr_save_event_plan_lineup_entries')) {
	                    if (function_exists('bvmgr_event_plan_save_profiler_note_heavy_action')) {
	                        bvmgr_event_plan_save_profiler_note_heavy_action('lineup_save', 'triggered', 'lineup_changed');
	                    }
	                    bvmgr_save_event_plan_lineup_entries($post_id, $posted_lineup_rows, $lineup_context);
	                }
	                if (function_exists('bvmgr_event_plan_perf_span_finish')) {
	                    bvmgr_event_plan_perf_span_finish('event_plan_lineup_save', $post_id, $lineup_save_trace, array(
	                        'section' => 'lineup_save',
	                        'supporting_act_rows' => is_array($posted_lineup_rows) ? count($posted_lineup_rows) : 0,
	                        'phase' => $lineup_save_needed ? 'run' : 'skip',
	                        'skip_reason' => $lineup_skip_reason,
	                    ));
	                }

                // ------------------------------------------------------
                // Secondary vendors (non-performer vendors)
                // Store canonical list + rebuild index meta for fast queries.
                // ------------------------------------------------------
                $k_secondary_ids     = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'secondary_vendor_ids') : '_vms_secondary_vendor_ids';
                $k_secondary_idx     = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'secondary_vendor_id') : '_vms_secondary_vendor_id';
                $k_secondary_type    = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'secondary_vendor_type') : '_vms_secondary_vendor_type';
                $k_secondary_unq     = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'secondary_vendor_unqualified') : '_vms_secondary_vendor_unqualified';
                $k_secondary_unq_ids = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'secondary_vendor_unqualified_ids') : '_vms_secondary_vendor_unqualified_ids';
                $k_band_vendor_id    = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'band_vendor_id') ?: '_vms_band_vendor_id') : '_vms_band_vendor_id';
                $k_tec_event_id      = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'tec_event_id') ?: '_vms_tec_event_id') : '_vms_tec_event_id';
	                $secondary_vendor_module_detached = !empty($request['vms_secondary_vendors_module_detached']);
	                $secondary_vendor_request = $secondary_vendor_module_detached ? array() : $request;
	                $secondary_vendor_submission = function_exists('bvmgr_event_plan_resolve_secondary_vendor_submission')
	                    ? bvmgr_event_plan_resolve_secondary_vendor_submission($post_id, $secondary_vendor_request)
	                    : array(
	                        'current_state' => array(
	                            'primary_vendor_id' => (int) get_post_meta($post_id, $k_band_vendor_id, true),
	                            'secondary_vendor_type' => sanitize_key((string) get_post_meta($post_id, $k_secondary_type, true)),
	                            'secondary_vendor_ids' => array_values(array_unique(array_filter(array_map('absint', (array) get_post_meta($post_id, $k_secondary_ids, true))))),
	                            'linked_tec_event_id' => (int) get_post_meta($post_id, $k_tec_event_id, true),
	                        ),
	                        'submission_present' => array_key_exists('vms_secondary_vendor_type', $secondary_vendor_request) || array_key_exists('vms_secondary_vendor_ids', $secondary_vendor_request),
	                        'clear_requested' => !empty($secondary_vendor_request['vms_clear_secondary_vendors']),
	                        'type_slug' => array_key_exists('vms_secondary_vendor_type', $secondary_vendor_request) ? sanitize_key((string) $secondary_vendor_request['vms_secondary_vendor_type']) : sanitize_key((string) get_post_meta($post_id, $k_secondary_type, true)),
	                        'secondary_ids' => array_key_exists('vms_secondary_vendor_ids', $secondary_vendor_request) && is_array($secondary_vendor_request['vms_secondary_vendor_ids']) ? array_values(array_unique(array_filter(array_map('absint', $secondary_vendor_request['vms_secondary_vendor_ids'])))) : array_values(array_unique(array_filter(array_map('absint', (array) get_post_meta($post_id, $k_secondary_ids, true))))),
	                    );
	                $secondary_vendor_present = !$secondary_vendor_module_detached && !empty($secondary_vendor_submission['submission_present']);
	                $secondary_vendor_lazy_unloaded = !empty($request['vms_secondary_vendors_lazy_unloaded']);

                if (!$secondary_vendor_present && $secondary_vendor_lazy_unloaded) {
                    if (function_exists('bvmgr_event_plan_perf_log')) {
                        bvmgr_event_plan_perf_log('event_plan_secondary_vendor_rebuild', $post_id, array(
                            'phase' => 'skip',
                            'skip_reason' => 'no_vendor_change',
                            'dirty_branch' => 'skip',
                            'dirty_fields' => array(),
                            'repair_reasons' => array(),
                            'lazy_unloaded' => 1,
                            'section' => 'secondary_vendor_rebuild',
                        ));
                        bvmgr_event_plan_perf_log('event_plan_calendar_vendor_maintenance', $post_id, array(
                            'phase' => 'skip',
                            'dirty_branch' => 'skip',
                            'skip_reason' => 'no_vendor_change',
                            'lazy_unloaded' => 1,
                        ));
                    }
                    if (function_exists('bvmgr_event_plan_save_profiler_note_heavy_action')) {
                        bvmgr_event_plan_save_profiler_note_heavy_action('secondary_vendor_rebuild', 'skipped', 'no_vendor_change');
                    }
                } else {

	                $current_band_id = (int) get_post_meta($post_id, $k_band_vendor_id, true);
	                $proposed_assignments = is_array($secondary_vendor_submission['secondary_vendor_assignments'] ?? null)
	                    ? (array) $secondary_vendor_submission['secondary_vendor_assignments']
	                    : array();
	                $proposed_secondary_type = function_exists('bvmgr_event_plan_legacy_secondary_vendor_type_from_assignments')
	                    ? (string) bvmgr_event_plan_legacy_secondary_vendor_type_from_assignments($proposed_assignments)
	                    : sanitize_key((string) ($secondary_vendor_submission['type_slug'] ?? ''));
	                $proposed_secondary_ids = function_exists('bvmgr_event_plan_get_secondary_vendor_flat_ids_from_assignments')
	                    ? array_values(array_unique(array_filter(array_map('absint', (array) bvmgr_event_plan_get_secondary_vendor_flat_ids_from_assignments($proposed_assignments, $current_band_id)))))
	                    : array_values(array_unique(array_filter(array_map('absint', (array) ($secondary_vendor_submission['secondary_ids'] ?? array())), static function ($vendor_id) {
	                        return $vendor_id > 0;
	                    })));

                $linked_tec_event_id = (int) get_post_meta($post_id, $k_tec_event_id, true);
	                $current_vendor_state = isset($secondary_vendor_submission['current_state']) && is_array($secondary_vendor_submission['current_state'])
	                    ? $secondary_vendor_submission['current_state']
	                    : (function_exists('bvmgr_event_plan_get_secondary_vendor_state')
	                        ? bvmgr_event_plan_get_secondary_vendor_state($post_id)
	                        : array(
	                            'primary_vendor_id' => $current_band_id,
	                            'secondary_vendor_assignments' => array(),
	                            'secondary_vendor_type' => sanitize_key((string) get_post_meta($post_id, $k_secondary_type, true)),
	                            'secondary_vendor_ids' => array_values(array_unique(array_filter(array_map('absint', (array) get_post_meta($post_id, $k_secondary_ids, true))))),
	                            'linked_tec_event_id' => $linked_tec_event_id,
	                        ));
                $proposed_vendor_state = array(
                    'primary_vendor_id' => $current_band_id,
                    'secondary_vendor_assignments' => $proposed_assignments,
                    'secondary_vendor_type' => $proposed_secondary_type,
                    'secondary_vendor_ids' => $proposed_secondary_ids,
                    'linked_tec_event_id' => $linked_tec_event_id,
                );

                $vendor_dirty_fields = function_exists('bvmgr_event_plan_secondary_vendor_state_diff_fields')
                    ? bvmgr_event_plan_secondary_vendor_state_diff_fields(
                        $current_vendor_state,
                        $proposed_vendor_state,
                        array('secondary_vendor_assignments', 'secondary_vendor_type', 'secondary_vendor_ids')
                    )
                    : array();

                $primary_vendor_touched = function_exists('bvmgr_event_plan_save_profiler_meta_key_touched')
                    ? bvmgr_event_plan_save_profiler_meta_key_touched($k_band_vendor_id)
                    : false;
                if ($primary_vendor_touched && !in_array('primary_vendor_id', $vendor_dirty_fields, true)) {
                    $vendor_dirty_fields[] = 'primary_vendor_id';
                }

                $repair_reasons = function_exists('bvmgr_event_plan_secondary_vendor_rebuild_repair_reasons')
                    ? bvmgr_event_plan_secondary_vendor_rebuild_repair_reasons($post_id, $current_vendor_state)
                    : array();

                $maintenance_dirty_fields = $vendor_dirty_fields;
                $linked_tec_target_touched = function_exists('bvmgr_event_plan_save_profiler_meta_key_touched')
                    ? bvmgr_event_plan_save_profiler_meta_key_touched($k_tec_event_id)
                    : false;
                if ($linked_tec_target_touched && !in_array('linked_tec_event_id', $maintenance_dirty_fields, true)) {
                    $maintenance_dirty_fields[] = 'linked_tec_event_id';
                }

                $secondary_dirty_reason = '';
                if (!empty($vendor_dirty_fields)) {
                    $secondary_dirty_reason = implode(',', $vendor_dirty_fields);
                } elseif (!empty($repair_reasons)) {
                    $secondary_dirty_reason = implode(',', $repair_reasons);
                }

                if (empty($vendor_dirty_fields) && empty($repair_reasons)) {
                    if (function_exists('bvmgr_event_plan_perf_log')) {
                        bvmgr_event_plan_perf_log('event_plan_secondary_vendor_rebuild', $post_id, array(
                            'phase' => 'skip',
                            'skip_reason' => 'no_vendor_change',
                            'dirty_branch' => 'skip',
                            'dirty_fields' => array(),
                            'repair_reasons' => array(),
                            'primary_vendor_touched' => $primary_vendor_touched ? 1 : 0,
                            'secondary_vendor_type' => (string) ($current_vendor_state['secondary_vendor_type'] ?? ''),
                            'secondary_vendor_count' => count((array) ($current_vendor_state['secondary_vendor_ids'] ?? array())),
                        ));
                    }
                    if (function_exists('bvmgr_event_plan_save_profiler_note_heavy_action')) {
                        bvmgr_event_plan_save_profiler_note_heavy_action('secondary_vendor_rebuild', 'skipped', 'no_vendor_change');
                    }

                    if ($linked_tec_event_id > 0) {
                        if (function_exists('bvmgr_event_plan_perf_log')) {
                            bvmgr_event_plan_perf_log('event_plan_calendar_vendor_maintenance', $post_id, array(
                                'phase' => !empty($maintenance_dirty_fields) ? 'run' : 'skip',
                                'dirty_branch' => !empty($maintenance_dirty_fields) ? 'queue' : 'skip',
                                'skip_reason' => empty($maintenance_dirty_fields) ? 'no_vendor_change' : '',
                                'dirty_reason' => !empty($maintenance_dirty_fields) ? implode(',', $maintenance_dirty_fields) : '',
                                'linked_tec_event_id' => $linked_tec_event_id,
                            ));
                        }

                        if (!empty($maintenance_dirty_fields) && function_exists('bvmgr_event_plan_schedule_calendar_maintenance')) {
                            bvmgr_event_plan_schedule_calendar_maintenance($post_id, $linked_tec_event_id, 'vendor_category_sync');
                        }
                    }
                } else {
                    $secondary_save_trace = function_exists('bvmgr_event_plan_perf_span_start')
                        ? bvmgr_event_plan_perf_span_start('event_plan_secondary_vendor_rebuild', $post_id, array(
                            'section' => 'secondary_vendor_rebuild',
                            'dirty_branch' => 'run',
                            'dirty_fields' => $vendor_dirty_fields,
                            'repair_reasons' => $repair_reasons,
                        ))
                        : '';

                    if (function_exists('bvmgr_event_plan_save_profiler_note_heavy_action')) {
                        bvmgr_event_plan_save_profiler_note_heavy_action('secondary_vendor_rebuild', 'triggered', $secondary_dirty_reason !== '' ? $secondary_dirty_reason : 'vendor_state_changed');
                    }

                    $write_result = function_exists('bvmgr_event_plan_write_secondary_vendor_assignments')
                        ? bvmgr_event_plan_write_secondary_vendor_assignments($post_id, $proposed_assignments)
                        : new WP_Error('vms_secondary_vendor_write_unavailable', __('Additional Vendor save helper is unavailable.', 'backstage-venue-manager'));

                    $written_secondary_type = $proposed_secondary_type;
                    $written_secondary_ids = $proposed_secondary_ids;
                    $unq_ids = array();

                    if (is_wp_error($write_result)) {
                        if (function_exists('bvmgr_add_admin_notice')) {
                            bvmgr_add_admin_notice((string) $write_result->get_error_message(), 'error');
                        }
                    } else {
                        $written_secondary_type = (string) ($write_result['secondary_vendor_type'] ?? $proposed_secondary_type);
                        $written_secondary_ids = array_values(array_unique(array_filter(array_map('absint', (array) ($write_result['secondary_vendor_ids'] ?? $proposed_secondary_ids)))));
                        $unq_ids = array_values(array_unique(array_filter(array_map('absint', (array) ($write_result['unqualified_ids'] ?? array())), static function ($vendor_id) {
                            return $vendor_id > 0;
                        })));
                    }

                    if (!is_wp_error($write_result)) {
                        if (function_exists('bvmgr_event_plan_update_vendor_category_snapshot')) {
                            bvmgr_event_plan_update_vendor_category_snapshot($post_id);
                        }

                        if ($linked_tec_event_id > 0) {
                            if (function_exists('bvmgr_event_plan_perf_log')) {
                                bvmgr_event_plan_perf_log('event_plan_calendar_vendor_maintenance', $post_id, array(
                                    'phase' => 'run',
                                    'dirty_branch' => 'queue',
                                    'dirty_reason' => $secondary_dirty_reason !== '' ? $secondary_dirty_reason : 'vendor_state_changed',
                                    'linked_tec_event_id' => $linked_tec_event_id,
                                ));
                            }
                            if (function_exists('bvmgr_event_plan_schedule_calendar_maintenance')) {
                                bvmgr_event_plan_schedule_calendar_maintenance($post_id, $linked_tec_event_id, 'vendor_category_sync');
                            }
                        }

                        // Clear integrity flag if it was only about a missing secondary vendor.
                        if (function_exists('bvmgr_event_plan_clear_integrity_flags')) {
                            $k_issue = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_issue') : '_vms_integrity_issue';
                            $issue_now = (string) get_post_meta($post_id, $k_issue, true);
                            if ($issue_now === 'missing_secondary_vendor') {
                                bvmgr_event_plan_clear_integrity_flags($post_id);
                            }
                        }
                    }

                    if (function_exists('bvmgr_event_plan_perf_span_finish')) {
                        bvmgr_event_plan_perf_span_finish('event_plan_secondary_vendor_rebuild', $post_id, $secondary_save_trace, array(
                            'section' => 'secondary_vendor_rebuild',
                            'dirty_branch' => 'run',
                            'dirty_fields' => $vendor_dirty_fields,
                            'repair_reasons' => $repair_reasons,
                            'secondary_vendor_type' => $written_secondary_type,
                            'secondary_vendor_count' => count($written_secondary_ids),
                            'secondary_vendor_unqualified_count' => count($unq_ids),
                        ));
                    }
                }
                }

                // Audit trail + default snapshot storage
                if ($has_default) {
                    update_post_meta($post_id, '_vms_pay_default_source', $default['source']);
                    update_post_meta($post_id, '_vms_pay_default_structure', $default['structure']);
                    if ($default['flat_fee_amount'] === null) delete_post_meta($post_id, '_vms_pay_default_flat_fee_amount');
                    else update_post_meta($post_id, '_vms_pay_default_flat_fee_amount', (float) $default['flat_fee_amount']);

                    if ($default['door_split_percent'] === null) delete_post_meta($post_id, '_vms_pay_default_door_split_percent');
                    else update_post_meta($post_id, '_vms_pay_default_door_split_percent', (float) $default['door_split_percent']);

                    if ($default['attendance_bonus_mode'] === '') delete_post_meta($post_id, '_vms_pay_default_attendance_bonus_mode');
                    else update_post_meta($post_id, '_vms_pay_default_attendance_bonus_mode', (string) $default['attendance_bonus_mode']);

                    if ($default['attendance_bonus_start_count'] === null) delete_post_meta($post_id, '_vms_pay_default_attendance_bonus_start_count');
                    else update_post_meta($post_id, '_vms_pay_default_attendance_bonus_start_count', (int) $default['attendance_bonus_start_count']);

                    if ($default['attendance_bonus_step_size'] === null) delete_post_meta($post_id, '_vms_pay_default_attendance_bonus_step_size');
                    else update_post_meta($post_id, '_vms_pay_default_attendance_bonus_step_size', (int) $default['attendance_bonus_step_size']);

                    if ($default['attendance_bonus_step_bonus'] === null) delete_post_meta($post_id, '_vms_pay_default_attendance_bonus_step_bonus');
                    else update_post_meta($post_id, '_vms_pay_default_attendance_bonus_step_bonus', (float) $default['attendance_bonus_step_bonus']);

                    if ($default['attendance_bonus_per_ticket_rate'] === null) delete_post_meta($post_id, '_vms_pay_default_attendance_bonus_per_ticket_rate');
                    else update_post_meta($post_id, '_vms_pay_default_attendance_bonus_per_ticket_rate', (float) $default['attendance_bonus_per_ticket_rate']);

                    if ($default['attendance_bonus_max_bonus'] === null) delete_post_meta($post_id, '_vms_pay_default_attendance_bonus_max_bonus');
                    else update_post_meta($post_id, '_vms_pay_default_attendance_bonus_max_bonus', (float) $default['attendance_bonus_max_bonus']);

                    if ($default['holiday_name'] !== '') update_post_meta($post_id, '_vms_pay_default_holiday_name', $default['holiday_name']);
                    else delete_post_meta($post_id, '_vms_pay_default_holiday_name');
                } else {
                    delete_post_meta($post_id, '_vms_pay_default_source');
                    delete_post_meta($post_id, '_vms_pay_default_structure');
                    delete_post_meta($post_id, '_vms_pay_default_flat_fee_amount');
                    delete_post_meta($post_id, '_vms_pay_default_door_split_percent');
                    delete_post_meta($post_id, '_vms_pay_default_attendance_bonus_mode');
                    delete_post_meta($post_id, '_vms_pay_default_attendance_bonus_start_count');
                    delete_post_meta($post_id, '_vms_pay_default_attendance_bonus_step_size');
                    delete_post_meta($post_id, '_vms_pay_default_attendance_bonus_step_bonus');
                    delete_post_meta($post_id, '_vms_pay_default_attendance_bonus_per_ticket_rate');
                    delete_post_meta($post_id, '_vms_pay_default_attendance_bonus_max_bonus');
                    delete_post_meta($post_id, '_vms_pay_default_holiday_name');
                }

                if ($has_default && $differs && $ack) {
                    $pay_ack_default_snapshot = array(
                        'source' => $default['source'],
                        'label'  => $default['label'],
                        'holiday_name' => $default['holiday_name'],
                        'structure' => $default['structure'],
                        'flat_fee_amount' => $default['flat_fee_amount'],
                        'door_split_percent' => $default['door_split_percent'],
                        'attendance_bonus_mode' => $default['attendance_bonus_mode'],
                        'attendance_bonus_start_count' => $default['attendance_bonus_start_count'],
                        'attendance_bonus_step_size' => $default['attendance_bonus_step_size'],
                        'attendance_bonus_step_bonus' => $default['attendance_bonus_step_bonus'],
                        'attendance_bonus_per_ticket_rate' => $default['attendance_bonus_per_ticket_rate'],
                        'attendance_bonus_max_bonus' => $default['attendance_bonus_max_bonus'],
                    );
                    $pay_ack_actual_snapshot = array(
                        'structure' => $actual['structure'],
                        'flat_fee_amount' => $actual['flat_fee_amount'],
                        'door_split_percent' => $actual['door_split_percent'],
                        'attendance_bonus_mode' => $actual['attendance_bonus_mode'],
                        'attendance_bonus_start_count' => $actual['attendance_bonus_start_count'],
                        'attendance_bonus_step_size' => $actual['attendance_bonus_step_size'],
                        'attendance_bonus_step_bonus' => $actual['attendance_bonus_step_bonus'],
                        'attendance_bonus_per_ticket_rate' => $actual['attendance_bonus_per_ticket_rate'],
                        'attendance_bonus_max_bonus' => $actual['attendance_bonus_max_bonus'],
                    );

                    $pay_ack_unchanged = (
                        (string) get_post_meta($post_id, '_vms_pay_override_ack', true) === '1'
                        && maybe_serialize(get_post_meta($post_id, '_vms_pay_override_ack_default_snapshot', true)) === maybe_serialize($pay_ack_default_snapshot)
                        && maybe_serialize(get_post_meta($post_id, '_vms_pay_override_ack_actual_snapshot', true)) === maybe_serialize($pay_ack_actual_snapshot)
                    );

                    update_post_meta($post_id, '_vms_pay_override_ack', '1');
                    if (!$pay_ack_unchanged || (int) get_post_meta($post_id, '_vms_pay_override_ack_ts', true) <= 0) {
                        update_post_meta($post_id, '_vms_pay_override_ack_ts', time());
                    }
                    if (!$pay_ack_unchanged || (int) get_post_meta($post_id, '_vms_pay_override_ack_user_id', true) <= 0) {
                        update_post_meta($post_id, '_vms_pay_override_ack_user_id', get_current_user_id());
                    }
                    update_post_meta($post_id, '_vms_pay_override_ack_default_snapshot', $pay_ack_default_snapshot);
                    update_post_meta($post_id, '_vms_pay_override_ack_actual_snapshot', $pay_ack_actual_snapshot);
                } else {
                    // If no mismatch, do not keep an old acknowledgment around
                    delete_post_meta($post_id, '_vms_pay_override_ack');
                    delete_post_meta($post_id, '_vms_pay_override_ack_ts');
                    delete_post_meta($post_id, '_vms_pay_override_ack_user_id');
                    delete_post_meta($post_id, '_vms_pay_override_ack_default_snapshot');
                    delete_post_meta($post_id, '_vms_pay_override_ack_actual_snapshot');
                }

                // Low-guarantee structure acknowledgment meta
                $k_low_ack = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'low_guarantee_ack') : '_vms_low_guarantee_ack';
                $k_low_ack_ts = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'low_guarantee_ack_ts') : '_vms_low_guarantee_ack_ts';
                $k_low_ack_user = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'low_guarantee_ack_user_id') : '_vms_low_guarantee_ack_user_id';
                $k_low_ack_snapshot = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'low_guarantee_ack_snapshot') : '_vms_low_guarantee_ack_snapshot';

                if ($requires_low_guarantee_ack && $low_guarantee_ack) {
                    $low_ack_snapshot = array(
                        'structure' => $actual['structure'],
                        'flat_fee_amount' => $actual['flat_fee_amount'],
                        'door_split_percent' => $actual['door_split_percent'],
                        'attendance_bonus_mode' => $actual['attendance_bonus_mode'],
                        'attendance_bonus_start_count' => $actual['attendance_bonus_start_count'],
                        'attendance_bonus_step_size' => $actual['attendance_bonus_step_size'],
                        'attendance_bonus_step_bonus' => $actual['attendance_bonus_step_bonus'],
                        'attendance_bonus_per_ticket_rate' => $actual['attendance_bonus_per_ticket_rate'],
                        'attendance_bonus_max_bonus' => $actual['attendance_bonus_max_bonus'],
                        'selected_guarantee' => $selected_guarantee,
                        'max_guarantee' => $guarantee_max,
                    );
                    $low_ack_unchanged = (
                        (string) get_post_meta($post_id, $k_low_ack, true) === '1'
                        && maybe_serialize(get_post_meta($post_id, $k_low_ack_snapshot, true)) === maybe_serialize($low_ack_snapshot)
                    );

                    update_post_meta($post_id, $k_low_ack, '1');
                    if (!$low_ack_unchanged || (int) get_post_meta($post_id, $k_low_ack_ts, true) <= 0) {
                        update_post_meta($post_id, $k_low_ack_ts, time());
                    }
                    if (!$low_ack_unchanged || (int) get_post_meta($post_id, $k_low_ack_user, true) <= 0) {
                        update_post_meta($post_id, $k_low_ack_user, get_current_user_id());
                    }
                    update_post_meta($post_id, $k_low_ack_snapshot, $low_ack_snapshot);
                } else {
                    delete_post_meta($post_id, $k_low_ack);
                    delete_post_meta($post_id, $k_low_ack_ts);
                    delete_post_meta($post_id, $k_low_ack_user);
                    delete_post_meta($post_id, $k_low_ack_snapshot);
                }



                // Auto toggles
                $auto_title      = isset($request['vms_auto_title']) ? '1' : '0';
                $auto_comp       = isset($request['vms_auto_comp']) ? '1' : '0';
                $auto_comp_venue = isset($request['vms_auto_comp_venue']) ? '1' : '0';

                // Package selection (persist so dropdown sticks even without Apply)
                $comp_package_id = isset($request['vms_comp_package_id']) ? absint($request['vms_comp_package_id']) : 0;

                if ($event_date_posted) {
                    if ($event_date === '') {
                        delete_post_meta($post_id, '_vms_event_date');
                    } else {
                        update_post_meta($post_id, '_vms_event_date', $event_date);
                    }
                }

                if ($start_time_posted) {
                    if ($start_time === '') {
                        delete_post_meta($post_id, '_vms_start_time');
                    } else {
                        update_post_meta($post_id, '_vms_start_time', $start_time);
                    }
                }

                if ($end_time_posted) {
                    if ($end_time === '') {
                        delete_post_meta($post_id, '_vms_end_time');
                    } else {
                        update_post_meta($post_id, '_vms_end_time', $end_time);
                    }
                }

                if ($venue_id_posted) {
                    if ($venue_id <= 0) {
                        delete_post_meta($post_id, '_vms_venue_id');
                    } else {
                        update_post_meta($post_id, '_vms_venue_id', $venue_id);
                    }
                }

                if (function_exists('bvmgr_event_plan_store_checkin_close_meta')) {
                    bvmgr_event_plan_store_checkin_close_meta($post_id);
                }

                // Save remaining core meta
                $fields = array(
                    '_vms_auto_title'      => $auto_title,
                    '_vms_auto_comp'       => $auto_comp,
                    '_vms_auto_comp_venue' => $auto_comp_venue,
                );

                foreach ($fields as $k => $v) {
                    if ($v === '' || $v === null) delete_post_meta($post_id, $k);
                    else update_post_meta($post_id, $k, $v);
                }

                update_post_meta($post_id, '_vms_comp_structure', $comp_structure);

                if ($flat_fee_amount === '') {
                    delete_post_meta($post_id, '_vms_flat_fee_amount');
                } else {
                    update_post_meta($post_id, '_vms_flat_fee_amount', (float) $flat_fee_amount);
                }

                if (in_array($comp_structure, array('door_split', 'flat_fee_door_split'), true) && $door_split_percent !== '') {
                    update_post_meta($post_id, '_vms_door_split_percent', (float) $door_split_percent);
                } else {
                    delete_post_meta($post_id, '_vms_door_split_percent');
                }

                $commission_supported = in_array($comp_structure, array('flat_fee', 'flat_fee_door_split', 'attendance_bonus'), true);
                $commission_explicit_none = (
                    $commission_supported
                    && array_key_exists('vms_commission_percent', $request)
                    && ($commission_percent_raw === '' || (is_numeric($commission_percent) && (float) $commission_percent <= 0))
                );

                if ($commission_supported && $commission_percent !== '' && (float) $commission_percent > 0) {
                    update_post_meta($post_id, '_vms_commission_percent', (float) $commission_percent);
                    update_post_meta($post_id, '_vms_commission_mode', $commission_mode);
                    delete_post_meta($post_id, $k_commission_override_none);
                } else {
                    delete_post_meta($post_id, '_vms_commission_percent');
                    delete_post_meta($post_id, '_vms_commission_mode');
                    if ($commission_explicit_none) {
                        update_post_meta($post_id, $k_commission_override_none, '1');
                    } else {
                        delete_post_meta($post_id, $k_commission_override_none);
                    }
                }

                if (function_exists('bvmgr_event_plan_save_deposit_terms')) {
                    bvmgr_event_plan_save_deposit_terms((int) $post_id, $deposit_terms_for_save);
                } else {
                    if ($deposit_amount === '') delete_post_meta($post_id, '_vms_deposit_amount');
                    else update_post_meta($post_id, '_vms_deposit_amount', (float) $deposit_amount);
                    update_post_meta($post_id, '_vms_deposit_status', $deposit_status);
                    update_post_meta($post_id, '_vms_deposit_treatment', $deposit_treatment);
                    if ($deposit_due_date === '') delete_post_meta($post_id, '_vms_deposit_due_date');
                    else update_post_meta($post_id, '_vms_deposit_due_date', $deposit_due_date);
                    if ($deposit_paid_date === '') delete_post_meta($post_id, '_vms_deposit_paid_date');
                    else update_post_meta($post_id, '_vms_deposit_paid_date', $deposit_paid_date);
                    if ($deposit_notes === '') delete_post_meta($post_id, '_vms_deposit_notes');
                    else update_post_meta($post_id, '_vms_deposit_notes', $deposit_notes);
                }

                if (function_exists('bvmgr_event_plan_save_final_payment_terms')) {
                    bvmgr_event_plan_save_final_payment_terms((int) $post_id, $final_payment_terms_for_save);
                } else {
                    update_post_meta($post_id, '_vms_final_payment_timing', $final_payment_timing);
                    update_post_meta($post_id, '_vms_final_payment_method', $final_payment_method);
                    if ($final_payment_days_after === '') delete_post_meta($post_id, '_vms_final_payment_days_after');
                    else update_post_meta($post_id, '_vms_final_payment_days_after', $final_payment_days_after);
                    if ($final_payment_date === '') delete_post_meta($post_id, '_vms_final_payment_date');
                    else update_post_meta($post_id, '_vms_final_payment_date', $final_payment_date);
                    if ($final_payment_custom_text === '') delete_post_meta($post_id, '_vms_final_payment_custom_text');
                    else update_post_meta($post_id, '_vms_final_payment_custom_text', $final_payment_custom_text);
                    if ($final_payment_method_other === '') delete_post_meta($post_id, '_vms_final_payment_method_other');
                    else update_post_meta($post_id, '_vms_final_payment_method_other', $final_payment_method_other);
                }

                if ($comp_structure === 'attendance_bonus') {
                    if ($attendance_bonus_mode === '') {
                        delete_post_meta($post_id, '_vms_attendance_bonus_mode');
                    } else {
                        update_post_meta($post_id, '_vms_attendance_bonus_mode', $attendance_bonus_mode);
                    }

                    if ($attendance_bonus_start_count === '') {
                        delete_post_meta($post_id, '_vms_attendance_bonus_start_count');
                    } else {
                        update_post_meta($post_id, '_vms_attendance_bonus_start_count', (int) $attendance_bonus_start_count);
                    }

                    if ($attendance_bonus_max_bonus === '') {
                        delete_post_meta($post_id, '_vms_attendance_bonus_max_bonus');
                    } else {
                        update_post_meta($post_id, '_vms_attendance_bonus_max_bonus', (float) $attendance_bonus_max_bonus);
                    }

                    if ($attendance_bonus_mode === 'step') {
                        if ($attendance_bonus_step_size === '') {
                            delete_post_meta($post_id, '_vms_attendance_bonus_step_size');
                        } else {
                            update_post_meta($post_id, '_vms_attendance_bonus_step_size', (int) $attendance_bonus_step_size);
                        }

                        if ($attendance_bonus_step_bonus === '') {
                            delete_post_meta($post_id, '_vms_attendance_bonus_step_bonus');
                        } else {
                            update_post_meta($post_id, '_vms_attendance_bonus_step_bonus', (float) $attendance_bonus_step_bonus);
                        }

                        delete_post_meta($post_id, '_vms_attendance_bonus_per_ticket_rate');
                    } elseif ($attendance_bonus_mode === 'continuous') {
                        if ($attendance_bonus_per_ticket_rate === '') {
                            delete_post_meta($post_id, '_vms_attendance_bonus_per_ticket_rate');
                        } else {
                            update_post_meta($post_id, '_vms_attendance_bonus_per_ticket_rate', (float) $attendance_bonus_per_ticket_rate);
                        }

                        delete_post_meta($post_id, '_vms_attendance_bonus_step_size');
                        delete_post_meta($post_id, '_vms_attendance_bonus_step_bonus');
                    } else {
                        delete_post_meta($post_id, '_vms_attendance_bonus_step_size');
                        delete_post_meta($post_id, '_vms_attendance_bonus_step_bonus');
                        delete_post_meta($post_id, '_vms_attendance_bonus_per_ticket_rate');
                    }
                } else {
                    delete_post_meta($post_id, '_vms_attendance_bonus_mode');
                    delete_post_meta($post_id, '_vms_attendance_bonus_start_count');
                    delete_post_meta($post_id, '_vms_attendance_bonus_step_size');
                    delete_post_meta($post_id, '_vms_attendance_bonus_step_bonus');
                    delete_post_meta($post_id, '_vms_attendance_bonus_per_ticket_rate');
                    delete_post_meta($post_id, '_vms_attendance_bonus_max_bonus');
                }

                if (function_exists('bvmgr_event_plan_maybe_cleanup_legacy_ticket_meta_for_plan')) {
                    bvmgr_event_plan_maybe_cleanup_legacy_ticket_meta_for_plan($post_id);
                }

                if ($comp_package_id > 0) update_post_meta($post_id, '_vms_comp_package_id', $comp_package_id);
                else delete_post_meta($post_id, '_vms_comp_package_id');

                // Persist the last clicked compensation tile selection
                if (isset($request['vms_comp_selected_option'])) {
                    $sel_opt = sanitize_text_field((string) $request['vms_comp_selected_option']);
                    $sel_opt = preg_replace('/[^a-z0-9:_-]/i', '', (string) $sel_opt);
                    $k_comp_selected_option = function_exists('bvmgr_meta_key')
                        ? (bvmgr_meta_key('event_plan', 'comp_selected_option') ?: '_vms_comp_selected_option')
                        : '_vms_comp_selected_option';
                    if ($sel_opt !== '') update_post_meta($post_id, $k_comp_selected_option, $sel_opt);
                    else delete_post_meta($post_id, $k_comp_selected_option);
                }

                // Structured staffing save (STAFF-01 Phase A) with fallback to legacy map behavior.
                if (!empty($staffing_present)) {
                    $staffing_save_trace = function_exists('bvmgr_event_plan_perf_span_start')
                        ? bvmgr_event_plan_perf_span_start('event_plan_staffing_save', $post_id, array('section' => 'staffing_save'))
                        : '';
                    $staffing_request_state = function_exists('bvmgr_staffing_assess_event_plan_save_request')
                        ? bvmgr_staffing_assess_event_plan_save_request(
                            (int) $post_id,
                            is_array($staffing_headcounts) ? $staffing_headcounts : array(),
                            is_array($staffing_raw_assignments) ? $staffing_raw_assignments : array(),
                            is_array($staffing_time_modes) ? $staffing_time_modes : array(),
                            is_array($staffing_shift_starts) ? $staffing_shift_starts : array(),
                            is_array($staffing_shift_ends) ? $staffing_shift_ends : array(),
                            is_array($staffing_start_anchor_keys) ? $staffing_start_anchor_keys : array(),
                            is_array($staffing_start_offsets) ? $staffing_start_offsets : array(),
                            is_array($staffing_end_anchor_keys) ? $staffing_end_anchor_keys : array(),
                            is_array($staffing_end_offsets) ? $staffing_end_offsets : array(),
                            is_array($staffing_duration_minutes) ? $staffing_duration_minutes : array(),
                            is_array($staffing_activation_thresholds_clean) ? $staffing_activation_thresholds_clean : array(),
                            !empty($staffing_template_apply_now),
                            (int) $staffing_template_selected_id,
                            (string) $staffing_template_apply_mode
                        )
                        : array();
                    $staffing_has_request_state = !empty($staffing_request_state);
                    $staffing_matrix_dirty = !$staffing_has_request_state || !empty($staffing_request_state['matrix_dirty']);
                    $staffing_thresholds_dirty = !$staffing_has_request_state || !empty($staffing_request_state['thresholds_dirty']);
                    $staffing_template_apply_requested = $staffing_has_request_state
                        ? !empty($staffing_request_state['template_apply_requested'])
                        : (!empty($staffing_template_apply_now) && (int) $staffing_template_selected_id > 0);
                    $staffing_dirty_reason = $staffing_has_request_state && function_exists('bvmgr_staffing_plan_save_request_state_dirty_reason')
                        ? bvmgr_staffing_plan_save_request_state_dirty_reason($staffing_request_state)
                        : '';
                    $staffing_should_skip_expensive_work = (
                        $staffing_has_request_state
                        && !$staffing_matrix_dirty
                        && !$staffing_thresholds_dirty
                        && !$staffing_template_apply_requested
                    );

                    if (function_exists('bvmgr_event_plan_perf_log')) {
                        bvmgr_event_plan_perf_log('event_plan_staffing_save', $post_id, array(
                            'phase' => $staffing_should_skip_expensive_work ? 'skip' : 'run',
                            'dirty_branch' => $staffing_should_skip_expensive_work ? 'skip' : 'run',
                            'skip_reason' => $staffing_should_skip_expensive_work ? 'no_staffing_change' : '',
                            'dirty_reason' => !$staffing_should_skip_expensive_work ? $staffing_dirty_reason : '',
                            'matrix_dirty' => $staffing_matrix_dirty ? 1 : 0,
                            'thresholds_dirty' => $staffing_thresholds_dirty ? 1 : 0,
                            'template_apply_requested' => $staffing_template_apply_requested ? 1 : 0,
                            'role_count' => (int) ($staffing_request_state['role_count'] ?? 0),
                        ));
                    }
                    if (function_exists('bvmgr_event_plan_save_profiler_note_heavy_action')) {
                        bvmgr_event_plan_save_profiler_note_heavy_action(
                            'staffing_save',
                            $staffing_should_skip_expensive_work ? 'skipped' : 'triggered',
                            $staffing_should_skip_expensive_work ? 'no_staffing_change' : ($staffing_dirty_reason !== '' ? $staffing_dirty_reason : 'staffing_changed')
                        );
                    }

                    if ($staffing_matrix_dirty) {
                        if (function_exists('bvmgr_staffing_save_event_roles_matrix')) {
                            bvmgr_staffing_save_event_roles_matrix(
                                (int) $post_id,
                                is_array($staffing_headcounts) ? $staffing_headcounts : array(),
                                is_array($staffing_raw_assignments) ? $staffing_raw_assignments : array(),
                                is_array($staffing_time_modes) ? $staffing_time_modes : array(),
                                is_array($staffing_shift_starts) ? $staffing_shift_starts : array(),
                                is_array($staffing_shift_ends) ? $staffing_shift_ends : array(),
                                is_array($staffing_start_anchor_keys) ? $staffing_start_anchor_keys : array(),
                                is_array($staffing_start_offsets) ? $staffing_start_offsets : array(),
                                is_array($staffing_end_anchor_keys) ? $staffing_end_anchor_keys : array(),
                                is_array($staffing_end_offsets) ? $staffing_end_offsets : array(),
                                is_array($staffing_duration_minutes) ? $staffing_duration_minutes : array(),
                                (int) get_current_user_id(),
                                $staffing_request_state
                            );
                        } else {
                            $raw = is_array($staffing_raw_assignments) ? $staffing_raw_assignments : array();
                            $clean = array();
                            foreach ($raw as $term_id => $ids) {
                                $term_id = absint($term_id);
                                if ($term_id <= 0) continue;
                                if (!is_array($ids)) $ids = array();
                                $ids = array_map('absint', $ids);
                                $ids = array_values(array_filter($ids, function ($v) {
                                    return $v > 0;
                                }));
                                if (!empty($ids)) $clean[$term_id] = $ids;
                            }
                            if (!empty($clean)) update_post_meta($post_id, '_vms_staff_assignments', $clean);
                            else delete_post_meta($post_id, '_vms_staff_assignments');
                        }
                    }

                    if ($staffing_thresholds_dirty) {
                        if (function_exists('bvmgr_staffing_set_event_role_activation_thresholds')) {
                            bvmgr_staffing_set_event_role_activation_thresholds((int) $post_id, is_array($staffing_activation_thresholds_clean) ? $staffing_activation_thresholds_clean : array());
                        } elseif (!empty($staffing_activation_thresholds_clean)) {
                            update_post_meta($post_id, '_vms_staff_role_activation_thresholds', $staffing_activation_thresholds_clean);
                        } else {
                            delete_post_meta($post_id, '_vms_staff_role_activation_thresholds');
                        }
                    }

                    $suppress_staffing_validation_notices = ($staffing_template_apply_now && $staffing_template_selected_id > 0 && $staffing_template_apply_mode === 'replace_all');
                    if ($suppress_staffing_validation_notices) {
                        $staffing_absolute_time_warning_roles = array();
                        $staffing_required_now_gap_roles = array();
                        $staffing_role_assignment_warnings = array();
                        $staffing_role_assignment_blocked = array();
                    }
                    if (!empty($staffing_absolute_time_warning_roles) && function_exists('bvmgr_add_admin_notice')) {
                        bvmgr_add_admin_notice(
                            sprintf(
                                /* translators: %s: absolute staff shift times are missing for. */
                                __('Absolute staff shift times are missing for: %s. When Time mode is Absolute and the role is in use, enter Shift start plus Shift end or Duration.', 'backstage-venue-manager'),
                                implode(', ', array_map('sanitize_text_field', array_unique($staffing_absolute_time_warning_roles)))
                            ),
                            'warning'
                        );
                        update_post_meta($post_id, '_vms_admin_scroll_to', 'vms_staff_assignments_present');
                    }
                    if (!empty($staffing_required_now_gap_roles) && function_exists('bvmgr_add_admin_notice')) {
                        bvmgr_add_admin_notice(
                            sprintf(
                                /* translators: %s: current attendance has activated these staff requirements, but they are still not fully assigned. */
                                __('Current attendance has activated these staff requirements, but they are still not fully assigned: %s.', 'backstage-venue-manager'),
                                implode(', ', array_map('sanitize_text_field', array_unique($staffing_required_now_gap_roles)))
                            ),
                            'warning'
                        );
                        update_post_meta($post_id, '_vms_admin_scroll_to', 'vms_staff_assignments_present');
                    }
                    if (!empty($staffing_role_assignment_warnings) && function_exists('bvmgr_add_admin_notice')) {
                        bvmgr_add_admin_notice(
                            sprintf(
                                /* translators: %s: staff assignment review needed. */
                                __('Staff assignment review needed: %s.', 'backstage-venue-manager'),
                                implode(' | ', array_map('sanitize_text_field', array_unique($staffing_role_assignment_warnings)))
                            ),
                            'warning'
                        );
                    }
                    if (!empty($staffing_role_assignment_blocked) && function_exists('bvmgr_add_admin_notice')) {
                        bvmgr_add_admin_notice(
                            sprintf(
                                /* translators: %s: invalid staff assignments were blocked because those staff are not currently eligible for the selected roles. */
                                __('Invalid staff assignments were blocked because those staff are not currently eligible for the selected roles: %s.', 'backstage-venue-manager'),
                                implode(' | ', array_map('sanitize_text_field', array_unique($staffing_role_assignment_blocked)))
                            ),
                            'error'
                        );
                    }
                    if ($staffing_template_apply_requested && function_exists('bvmgr_staffing_apply_template_to_event')) {
                        $template_apply_result = bvmgr_staffing_apply_template_to_event((int) $post_id, (int) $staffing_template_selected_id, (string) $staffing_template_apply_mode, (int) get_current_user_id());
                        if (!empty($template_apply_result['ok']) && function_exists('bvmgr_add_admin_notice')) {
                            bvmgr_add_admin_notice(
                                sprintf(
                                    /* translators: 1: number 1 used in this message, 2: number 2 used in this message. */
                                    __('Staffing template applied. Seeded %1$d role slots and skipped %2$d existing role slots.', 'backstage-venue-manager'),
                                    (int) ($template_apply_result['seeded'] ?? 0),
                                    (int) ($template_apply_result['skipped'] ?? 0)
                                ),
                                'success'
                            );
                        } elseif (function_exists('bvmgr_add_admin_notice')) {
                            bvmgr_add_admin_notice(
                                sprintf(
                                    /* translators: %s: staffing template apply failed. */
                                    __('Staffing template apply failed: %s.', 'backstage-venue-manager'),
                                    sanitize_text_field((string) ($template_apply_result['error'] ?? 'unknown_error'))
                                ),
                                'error'
                            );
                        }
                    }
                    if (function_exists('bvmgr_event_plan_perf_span_finish')) {
                        bvmgr_event_plan_perf_span_finish('event_plan_staffing_save', $post_id, $staffing_save_trace, array(
                            'section' => 'staffing_save',
                            'dirty_branch' => $staffing_should_skip_expensive_work ? 'skip' : 'run',
                            'dirty_reason' => !$staffing_should_skip_expensive_work ? $staffing_dirty_reason : '',
                            'skip_reason' => $staffing_should_skip_expensive_work ? 'no_staffing_change' : '',
                            'matrix_dirty' => $staffing_matrix_dirty ? 1 : 0,
                            'thresholds_dirty' => $staffing_thresholds_dirty ? 1 : 0,
                            'template_apply_requested' => $staffing_template_apply_requested ? 1 : 0,
                            'role_count' => (int) ($staffing_request_state['role_count'] ?? 0),
                        ));
                    }
                }

                // Auto behaviors
                if ($auto_title === '1') {

                    $band_id = (int) get_post_meta($post_id, '_vms_band_vendor_id', true);
                    $band_name = $band_id ? (string) get_the_title($band_id) : '';
                    $new_auto_title = function_exists('bvmgr_event_plan_build_auto_title')
                        ? bvmgr_event_plan_build_auto_title((string) $band_name)
                        : trim(wp_strip_all_tags((string) $band_name));

                    if ($new_auto_title !== '') {
                        $current_title = trim((string) get_the_title($post_id));
                        $last_auto     = (string) get_post_meta($post_id, '_vms_last_auto_title', true);

                        $is_emptyish = ($current_title === '' || strcasecmp($current_title, 'Auto Draft') === 0);
                        $matches_last_auto = ($last_auto !== '' && $current_title === $last_auto);

                        // Only overwrite when title is empty-ish OR still matches the previous auto title.
                        if ($new_auto_title && ($is_emptyish || $matches_last_auto)) {
                            $auto_title_postarr = array(
                                'ID'         => $post_id,
                                'post_title' => $new_auto_title,
                            );
                            $auto_title_changed_fields = function_exists('bvmgr_event_plan_perf_post_update_changed_fields')
                                ? bvmgr_event_plan_perf_post_update_changed_fields($post_id, $auto_title_postarr)
                                : array('post_title');

                            if (empty($auto_title_changed_fields)) {
                                if (function_exists('bvmgr_event_plan_perf_log')) {
                                    bvmgr_event_plan_perf_log('event_plan_auto_title_sync', $post_id, array(
                                        'phase' => 'skip',
                                        'skip_reason' => 'no_op',
                                        'current_post_title' => $current_title,
                                        'computed_post_title' => $new_auto_title,
                                        'changed_fields' => array(),
                                    ));
                                }
                            } else {
                                // Prevent recursion loops
                                remove_action('save_post_vms_event_plan', array($this, 'save_event_plan_meta'), 10);

                                bvmgr_event_plan_perf_wp_update_post($auto_title_postarr, 'event_plan_auto_title_sync', $post_id);

                                add_action('save_post_vms_event_plan', array($this, 'save_event_plan_meta'), 10, 2);
                            }
                        }

                        // Track the last auto title so we can detect custom edits later
                        update_post_meta($post_id, '_vms_last_auto_title', $new_auto_title);
                    }
                }

                // Keep existing auto-comp behavior
                if ($auto_comp === '1' && function_exists('bvmgr_maybe_apply_band_comp_defaults_to_plan')) {
                    bvmgr_maybe_apply_band_comp_defaults_to_plan($post_id);
                }

                // Handle actions (optional; normal "Update" saves have no action)
                $action = isset($request['vms_event_plan_action']) ? sanitize_text_field((string) $request['vms_event_plan_action']) : '';
                $retry_step_key = '';
                if (strpos($action, 'retry_cancellation_step:') === 0) {
                    $retry_step_key = sanitize_key(substr($action, strlen('retry_cancellation_step:')));
                    $action = 'retry_cancellation_step';
                }
                $k_cancel_policy = function_exists('bvmgr_meta_key')
                    ? (bvmgr_meta_key('event_plan', 'cancel_policy') ?: '_vms_cancel_policy')
                    : '_vms_cancel_policy';
                $k_cancel_reason_code = function_exists('bvmgr_meta_key')
                    ? (bvmgr_meta_key('event_plan', 'cancel_reason_code') ?: '_vms_cancel_reason_code')
                    : '_vms_cancel_reason_code';
                $k_cancel_reason_note = function_exists('bvmgr_meta_key')
                    ? (bvmgr_meta_key('event_plan', 'cancel_reason_note') ?: '_vms_cancel_reason_note')
                    : '_vms_cancel_reason_note';
                $k_cancel_vendor_message = function_exists('bvmgr_meta_key')
                    ? (bvmgr_meta_key('event_plan', 'cancel_vendor_message') ?: '_vms_cancel_vendor_message')
                    : '_vms_cancel_vendor_message';
                $k_plan_status = function_exists('bvmgr_meta_key')
                    ? (bvmgr_meta_key('event_plan', 'status') ?: '_vms_event_plan_status')
                    : '_vms_event_plan_status';

                $cancel_policy_post = isset($request['vms_cancel_policy']) ? sanitize_key((string) $request['vms_cancel_policy']) : '';
                $cancel_reason_code_post = isset($request['vms_cancel_reason_code']) ? sanitize_key((string) $request['vms_cancel_reason_code']) : '';
                $cancel_reason_note_post = isset($request['vms_cancel_reason_note']) ? sanitize_textarea_field((string) $request['vms_cancel_reason_note']) : '';
                $cancel_vendor_message_post = isset($request['vms_cancel_vendor_message']) ? sanitize_textarea_field((string) $request['vms_cancel_vendor_message']) : '';
                $cancel_auto_refund_confirmed_post = isset($request['vms_cancel_auto_refund_confirmed']) ? sanitize_key((string) $request['vms_cancel_auto_refund_confirmed']) : '0';
                $cancel_manual_live_refund_confirm_post = isset($request['vms_cancel_manual_live_refund_confirm']) ? sanitize_key((string) $request['vms_cancel_manual_live_refund_confirm']) : '0';

                $cancel_policy_options = function_exists('bvmgr_cancellation_policy_options')
                    ? array_keys((array) bvmgr_cancellation_policy_options())
                    : array('status_only');
                if (!in_array($cancel_policy_post, $cancel_policy_options, true)) {
                    $cancel_policy_post = 'status_only';
                }

                $cancel_reason_options = function_exists('bvmgr_cancellation_reason_options')
                    ? array_keys((array) bvmgr_cancellation_reason_options())
                    : array('other');
                if ($cancel_reason_code_post !== '' && !in_array($cancel_reason_code_post, $cancel_reason_options, true)) {
                    $cancel_reason_code_post = 'other';
                }

                update_post_meta($post_id, $k_cancel_policy, $cancel_policy_post);
                if ($cancel_reason_code_post === '') {
                    delete_post_meta($post_id, $k_cancel_reason_code);
                } else {
                    update_post_meta($post_id, $k_cancel_reason_code, $cancel_reason_code_post);
                }
                if ($cancel_reason_note_post === '') {
                    delete_post_meta($post_id, $k_cancel_reason_note);
                } else {
                    update_post_meta($post_id, $k_cancel_reason_note, $cancel_reason_note_post);
                }
                if ($cancel_vendor_message_post === '') {
                    delete_post_meta($post_id, $k_cancel_vendor_message);
                } else {
                    update_post_meta($post_id, $k_cancel_vendor_message, $cancel_vendor_message_post);
                }

                $current_status = (string) get_post_meta($post_id, $k_plan_status, true);
                if ($current_status === '') {
                    $current_status = 'draft';
                }
                $new_status = $current_status;
                $replacement_date_requested = isset($request['vms_reschedule_event_date'])
                    ? sanitize_text_field((string) $request['vms_reschedule_event_date'])
                    : '';
                $queue_rescheduled_draft_after_cancel = false;

                // BUG-01 hardening: recover legacy Draft+TEC mismatches when WP status is already non-draft.
                if ($action === '' && $new_status === 'draft') {
                    $wp_status_now = sanitize_key((string) get_post_status($post_id));
                    $k_tec = function_exists('bvmgr_meta_key')
                        ? (bvmgr_meta_key('event_plan', 'tec_event_id') ?: '_vms_tec_event_id')
                        : '_vms_tec_event_id';
                    $k_issue = function_exists('bvmgr_meta_key')
                        ? (bvmgr_meta_key('event_plan', 'integrity_issue') ?: '_vms_integrity_issue')
                        : '_vms_integrity_issue';
                    $integrity_forced_draft = array(
                        'calendar_event_unlinked',
                        'missing_calendar_event',
                        'trashed_calendar_event',
                        'calendar_event_unpublished',
                        'missing_vendor',
                        'trashed_vendor',
                        'missing_secondary_vendor',
                        'trashed_secondary_vendor',
                        'missing_venue',
                        'trashed_venue',
                        'venue_unpublished',
                    );
                    $issue_now = sanitize_key((string) get_post_meta($post_id, $k_issue, true));

                    if (
                        in_array($wp_status_now, array('publish', 'private', 'pending', 'future'), true)
                        && !in_array($issue_now, $integrity_forced_draft, true)
                    ) {
                        $tec_id_now = (int) get_post_meta($post_id, $k_tec, true);
                        $tec_post_now = ($tec_id_now > 0) ? get_post($tec_id_now) : null;
                        $tec_status_now = ($tec_post_now && is_object($tec_post_now)) ? sanitize_key((string) $tec_post_now->post_status) : '';

                        if ($tec_post_now && is_object($tec_post_now) && $tec_post_now->post_type === 'tribe_events' && $tec_status_now !== 'trash') {
                            $new_status = in_array($tec_status_now, array('publish', 'future'), true) ? 'published' : 'ready';
                            if (function_exists('bvmgr_add_admin_notice')) {
                                bvmgr_add_admin_notice(__('Detected a legacy Draft status mismatch and auto-repaired Event Plan status.', 'backstage-venue-manager'), 'warning');
                            }
                        }
                    }
                }

                if ($action !== '') {

                    switch ($action) {
                    case 'save_draft':
                        $new_status = 'draft';
                        bvmgr_add_admin_notice(__('Event plan saved as Draft.', 'backstage-venue-manager'), 'success');
                        break;

                    case 'mark_ready':
                        $errors = bvmgr_validate_event_plan($post_id);
                        if (empty($errors)) {
                            bvmgr_maybe_autoset_event_plan_title($post_id);
                            $new_status = 'ready';
                            bvmgr_add_admin_notice(__('Event plan marked Ready.', 'backstage-venue-manager'), 'success');
                        } else {
                            $new_status = 'draft';
                            bvmgr_add_admin_notice(__('Cannot mark Ready:', 'backstage-venue-manager') . ' ' . implode(' ', $errors), 'error');
                        }
                        break;

                    case 'publish_now':
                        if (!in_array($current_status, array('ready', 'published'), true)) {
                            bvmgr_add_admin_notice(__('Event must be Ready before publishing.', 'backstage-venue-manager'), 'error');
                            break;
                        }

                        $errors = bvmgr_validate_event_plan($post_id);
                        if (!empty($errors)) {
                            bvmgr_add_admin_notice(__('Cannot publish:', 'backstage-venue-manager') . ' ' . implode(' ', $errors), 'error');
                            break;
                        }

                        $published = false;
                        $deferred_calendar_publish = function_exists('bvmgr_event_plan_deferred_calendar_publish_enabled')
                            ? bvmgr_event_plan_deferred_calendar_publish_enabled($post_id, 'publish_now')
                            : false;

                        if ($deferred_calendar_publish && function_exists('bvmgr_event_plan_schedule_deferred_calendar_publish')) {
                                $published = bvmgr_event_plan_schedule_deferred_calendar_publish($post_id, 'publish_now');
                                if ($published) {
                                    $new_status = 'published';
                                    bvmgr_add_admin_notice(__('Event Plan marked Published. Calendar sync has been queued so the editor request stays light.', 'backstage-venue-manager'), 'success');
                                    bvmgr_add_admin_notice(__('The public calendar event will stay hidden until the linked TEC event is fully published and clickable. Use Re-sync to Calendar for an immediate manual sync if needed.', 'backstage-venue-manager'), 'info');
                                }
                            } else {
                            $published = bvmgr_publish_event_to_calendar($post_id, $post);
                            if ($published) {
                                $new_status = 'published';
                                bvmgr_add_admin_notice(__('Event published successfully.', 'backstage-venue-manager'), 'success');
                            }
                        }

                        if ($published) {
                            $tec_id_for_ticketing = function_exists('bvmgr_ticketing_b_get_linked_tec_event_id')
                                ? (int) bvmgr_ticketing_b_get_linked_tec_event_id($post_id)
                                : 0;

                            $cfg_v2 = get_post_meta(
                                $post_id,
                                function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'ticketing_config_v2') : '_vms_ticketing_config_v2',
                                true
                            );
                            $mode_v2 = is_array($cfg_v2) ? (string) ($cfg_v2['mode'] ?? '') : '';
                            $auto_money_allowed = function_exists('bvmgr_event_plan_ticketing_auto_money_allowed')
                                ? bvmgr_event_plan_ticketing_auto_money_allowed($post_id, $action, $current_status)
                                : ($action === 'publish_now');

                            if (function_exists('bvmgr_event_plan_is_externally_ticketed') && bvmgr_event_plan_is_externally_ticketed($post_id)) {
                                bvmgr_add_admin_notice(__('External Ticketing is active. The public event was synced without creating or updating native ticket products.', 'backstage-venue-manager'), 'info');
                            } elseif (!$auto_money_allowed) {
                                bvmgr_add_admin_notice(__('Woo product auto-publish skipped. Money-impacting product actions are disabled on Draft/Ready save paths.', 'backstage-venue-manager'), 'warning');
                            } elseif (!($tec_id_for_ticketing > 0 && $mode_v2 !== '' && $mode_v2 !== 'none')) {
                                bvmgr_add_admin_notice(__('Legacy Woo product auto-publish is retired. Use Ticketing Preview → Commit for all ticket/product creation and updates.', 'backstage-venue-manager'), 'warning');
                            }
                        } else {
                            bvmgr_add_admin_notice(__('Failed to queue/publish event to calendar. Please check settings.', 'backstage-venue-manager'), 'error');
                        }
                        break;

                    case 'mark_cancelled':
                        if ($replacement_date_requested !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $replacement_date_requested)) {
                            bvmgr_add_admin_notice(__('Enter a valid replacement date before cancelling with automatic reschedule.', 'backstage-venue-manager'), 'error');
                            break;
                        }

                        $new_status = 'cancelled';
                        if (function_exists('bvmgr_cancellation_create_job')) {
                            $job = (array) bvmgr_cancellation_create_job($post_id, array(
                                'policy' => (string) $cancel_policy_post,
                                'reason_code' => (string) $cancel_reason_code_post,
                                'reason_note' => (string) $cancel_reason_note_post,
                                'vendor_message' => (string) $cancel_vendor_message_post,
                                'auto_refund_confirmed' => ($cancel_auto_refund_confirmed_post === '1'),
                            ));
                            if (!empty($job['ok'])) {
                                $k_cancel_review = function_exists('bvmgr_meta_key')
                                    ? (bvmgr_meta_key('event_plan', 'cancel_requires_operator_review') ?: '_vms_cancel_requires_operator_review')
                                    : '_vms_cancel_requires_operator_review';
                                $k_job_state = function_exists('bvmgr_meta_key')
                                    ? (bvmgr_meta_key('event_plan', 'cancel_job_state') ?: '_vms_cancel_job_state')
                                    : '_vms_cancel_job_state';
                                $job_state = sanitize_key((string) get_post_meta($post_id, $k_job_state, true));
                                $requires_review = ($job_state === 'completed') ? '0' : '1';
                                update_post_meta($post_id, $k_cancel_review, $requires_review);
                            }
                        }
                        if ($replacement_date_requested !== '') {
                            $queue_rescheduled_draft_after_cancel = true;
                        }
                        bvmgr_add_admin_notice(__('Event plan marked Cancelled.', 'backstage-venue-manager'), 'success');
                        if ($queue_rescheduled_draft_after_cancel) {
                            bvmgr_add_admin_notice(__('Replacement date captured. Backstage Venue Manager will create a linked Draft Event Plan after this cancellation save completes.', 'backstage-venue-manager'), 'info');
                        }
                        if ($cancel_policy_post !== 'status_only') {
                            bvmgr_add_admin_notice(__('Cancellation job captured. Review the Cancellation Job panel for step outcomes and refund activity.', 'backstage-venue-manager'), 'warning');
                        }
                        break;

                    case 'run_live_refunds_now':
                        $new_status = 'cancelled';
                        if ($cancel_manual_live_refund_confirm_post !== '1') {
                            bvmgr_add_admin_notice(__('Live refund run was not confirmed. No refunds were attempted.', 'backstage-venue-manager'), 'warning');
                            break;
                        }
                        if (!function_exists('bvmgr_cancellation_request_live_refund_run')) {
                            bvmgr_add_admin_notice(__('Live refund helper is unavailable.', 'backstage-venue-manager'), 'error');
                            break;
                        }

                        $live_refund = (array) bvmgr_cancellation_request_live_refund_run($post_id, array(
                            'requested_by_user_id' => get_current_user_id(),
                            'policy_override' => (string) $cancel_policy_post,
                        ));
                        if (empty($live_refund['ok'])) {
                            $err = sanitize_text_field((string) ($live_refund['error'] ?? 'manual_live_refund_failed'));
                            bvmgr_add_admin_notice(__('Unable to run live refunds:', 'backstage-venue-manager') . ' ' . $err, 'error');
                            break;
                        }

                        $live_state = sanitize_key((string) ($live_refund['state'] ?? 'queued'));
                        $live_summary = isset($live_refund['summary']) && is_array($live_refund['summary']) ? $live_refund['summary'] : array();
                        $target_policy = sanitize_key((string) ($live_refund['target_policy'] ?? 'stop_sales_auto_refund'));
                        $policy_labels = function_exists('bvmgr_cancellation_policy_options') ? (array) bvmgr_cancellation_policy_options() : array();
                        $target_policy_label = isset($policy_labels[$target_policy]) ? (string) $policy_labels[$target_policy] : $target_policy;
                        $steps_live = isset($live_summary['steps']) && is_array($live_summary['steps']) ? $live_summary['steps'] : array();
                        $refund_execution_data = array();
                        foreach ($steps_live as $step_live) {
                            if (!is_array($step_live)) {
                                continue;
                            }
                            if (sanitize_key((string) ($step_live['key'] ?? '')) !== 'refund_execution') {
                                continue;
                            }
                            $refund_execution_data = isset($step_live['data']) && is_array($step_live['data']) ? $step_live['data'] : array();
                            break;
                        }
                        $created_count = isset($refund_execution_data['refunds_created']) && is_array($refund_execution_data['refunds_created']) ? count((array) $refund_execution_data['refunds_created']) : 0;
                        $queued_count = isset($refund_execution_data['queued_orders']) && is_array($refund_execution_data['queued_orders']) ? count((array) $refund_execution_data['queued_orders']) : 0;
                        $failed_count = isset($refund_execution_data['failed_orders']) && is_array($refund_execution_data['failed_orders']) ? count((array) $refund_execution_data['failed_orders']) : 0;

                        bvmgr_add_admin_notice(
                            sprintf(
                                /* translators: 1: value 1 used in this message, 2: number 2 used in this message, 3: number 3 used in this message, 4: number 4 used in this message. */
                                __('Live refund batch submitted. Policy: %1$s. Refunded: %2$d. Queued for manual review: %3$d. Failed: %4$d.', 'backstage-venue-manager'),
                                sanitize_text_field($target_policy_label),
                                (int) $created_count,
                                (int) $queued_count,
                                (int) $failed_count
                            ),
                            ($failed_count > 0 ? 'warning' : 'success')
                        );
                        if (!empty($live_refund['policy_changed'])) {
                            bvmgr_add_admin_notice(__('Cancellation policy was upgraded to a live auto-refund policy for this already-cancelled plan before running refunds.', 'backstage-venue-manager'), 'info');
                        }
                        if ($queued_count > 0 || $failed_count > 0 || $live_state !== 'completed') {
                            bvmgr_add_admin_notice(__('Review the Cancellation Job panel for any orders that still require manual follow-up.', 'backstage-venue-manager'), 'warning');
                        }
                        break;

                    case 'retry_cancellation_step':
                        $new_status = 'cancelled';
                        if ($retry_step_key === '') {
                            bvmgr_add_admin_notice(__('Retry request is missing a step key.', 'backstage-venue-manager'), 'error');
                            break;
                        }
                        if (!function_exists('bvmgr_cancellation_retry_step')) {
                            bvmgr_add_admin_notice(__('Cancellation retry helper is unavailable.', 'backstage-venue-manager'), 'error');
                            break;
                        }

                        $retry = (array) bvmgr_cancellation_retry_step($post_id, $retry_step_key, array(
                            'retry_by_user_id' => get_current_user_id(),
                        ));
                        if (empty($retry['ok'])) {
                            $err = sanitize_text_field((string) ($retry['error'] ?? 'retry_failed'));
                            bvmgr_add_admin_notice(__('Unable to retry cancellation step:', 'backstage-venue-manager') . ' ' . $err, 'error');
                            break;
                        }

                        if (function_exists('bvmgr_cancellation_run_job')) {
                            $run = (array) bvmgr_cancellation_run_job($post_id);
                            $run_state = sanitize_key((string) ($run['state'] ?? 'queued'));
                            $state_labels = function_exists('bvmgr_cancellation_job_statuses') ? (array) bvmgr_cancellation_job_statuses() : array();
                            $run_state_label = isset($state_labels[$run_state]) ? (string) $state_labels[$run_state] : strtoupper($run_state ?: 'queued');
                            bvmgr_add_admin_notice(
                                sprintf(
                                    /* translators: 1: value 1 used in this message, 2: value 2 used in this message. */
                                    __('Cancellation step retried: %1$s. Job state: %2$s.', 'backstage-venue-manager'),
                                    sanitize_text_field($retry_step_key),
                                    $run_state_label
                                ),
                                'success'
                            );
                        } else {
                            bvmgr_add_admin_notice(__('Cancellation step retry queued. Run the cancellation job to process pending steps.', 'backstage-venue-manager'), 'warning');
                        }
                        break;

                    case 'create_rescheduled_draft':
                        $new_status = 'cancelled';
                        if ($current_status !== 'cancelled') {
                            bvmgr_add_admin_notice(__('Only cancelled Event Plans can create a rescheduled draft.', 'backstage-venue-manager'), 'error');
                            break;
                        }

                        $replacement_date = isset($request['vms_reschedule_event_date'])
                            ? sanitize_text_field((string) $request['vms_reschedule_event_date'])
                            : '';

                        if (!function_exists('bvmgr_event_plan_create_rescheduled_draft')) {
                            bvmgr_add_admin_notice(__('Reschedule helper is unavailable in this build.', 'backstage-venue-manager'), 'error');
                            break;
                        }

                        $created = (array) bvmgr_event_plan_create_rescheduled_draft($post_id, array(
                            'replacement_date' => $replacement_date,
                            'post_title'       => (string) get_the_title($post_id),
                        ));

                        if (empty($created['ok'])) {
                            $err = (string) ($created['error_message'] ?? __('Backstage Venue Manager could not create the rescheduled draft.', 'backstage-venue-manager'));
                            bvmgr_add_admin_notice(sanitize_text_field($err), 'error');
                            break;
                        }

                        $new_plan_id = absint($created['new_post_id'] ?? 0);
                        if ($new_plan_id > 0 && function_exists('bvmgr_event_plan_set_runtime_redirect_target')) {
                            bvmgr_event_plan_set_runtime_redirect_target($post_id, $new_plan_id, array(
                                'vms_rescheduled_from' => $post_id,
                            ));
                        }

                        bvmgr_add_admin_notice(__('Rescheduled draft created. Review the replacement plan and publish it when ready.', 'backstage-venue-manager'), 'success');
                        bvmgr_add_admin_notice(__('The cancelled Event Plan was preserved and linked to the new draft for audit history.', 'backstage-venue-manager'), 'info');
                        break;

                    case 'retry_cancellation_all':
                        $new_status = 'cancelled';
                        $requires_bulk_confirm = false;
                        $k_job_summary = function_exists('bvmgr_meta_key')
                            ? (bvmgr_meta_key('event_plan', 'cancel_job_summary') ?: '_vms_cancel_job_summary')
                            : '_vms_cancel_job_summary';
                        $job_summary = get_post_meta($post_id, $k_job_summary, true);
                        if (is_array($job_summary) && !empty($job_summary['steps']) && is_array($job_summary['steps'])) {
                            foreach ($job_summary['steps'] as $step) {
                                if (!is_array($step)) {
                                    continue;
                                }
                                $step_key = sanitize_key((string) ($step['key'] ?? ''));
                                $step_status = sanitize_key((string) ($step['status'] ?? 'pending'));
                                if ($step_key === 'refund_execution' && in_array($step_status, array('failed', 'blocked'), true)) {
                                    $requires_bulk_confirm = true;
                                    break;
                                }
                            }
                        }
                        $bulk_confirm = isset($request['vms_cancel_bulk_retry_confirm']) ? sanitize_text_field((string) $request['vms_cancel_bulk_retry_confirm']) : '0';
                        if ($requires_bulk_confirm && $bulk_confirm !== '1') {
                            bvmgr_add_admin_notice(__('Bulk retry requires confirmation because Refund execution is failed/blocked. Confirm and retry again.', 'backstage-venue-manager'), 'error');
                            break;
                        }

                        if (!function_exists('bvmgr_cancellation_retry_all_failed_steps')) {
                            bvmgr_add_admin_notice(__('Cancellation bulk retry helper is unavailable.', 'backstage-venue-manager'), 'error');
                            break;
                        }

                        $retry_all = (array) bvmgr_cancellation_retry_all_failed_steps($post_id, array(
                            'retry_by_user_id' => get_current_user_id(),
                        ));
                        if (empty($retry_all['ok'])) {
                            $err = sanitize_text_field((string) ($retry_all['error'] ?? 'retry_failed'));
                            bvmgr_add_admin_notice(__('Unable to retry cancellation steps:', 'backstage-venue-manager') . ' ' . $err, 'error');
                            break;
                        }

                        if (function_exists('bvmgr_cancellation_run_job')) {
                            $run = (array) bvmgr_cancellation_run_job($post_id);
                            $run_state = sanitize_key((string) ($run['state'] ?? 'queued'));
                            $state_labels = function_exists('bvmgr_cancellation_job_statuses') ? (array) bvmgr_cancellation_job_statuses() : array();
                            $run_state_label = isset($state_labels[$run_state]) ? (string) $state_labels[$run_state] : strtoupper($run_state ?: 'queued');
                            $retried = isset($retry_all['retried_steps']) && is_array($retry_all['retried_steps']) ? count($retry_all['retried_steps']) : 0;
                            bvmgr_add_admin_notice(
                                sprintf(
                                    /* translators: 1: number 1 used in this message, 2: value 2 used in this message. */
                                    __('Cancellation bulk retry queued for %1$d step(s). Job state: %2$s.', 'backstage-venue-manager'),
                                    $retried,
                                    $run_state_label
                                ),
                                'success'
                            );
                        } else {
                            bvmgr_add_admin_notice(__('Cancellation bulk retry queued. Run the cancellation job to process pending steps.', 'backstage-venue-manager'), 'warning');
                        }
                        break;

            
        case 'apply_vendor_defaults':
                    case 'apply_band_defaults':
                        $vendor_id_for_apply = (int) get_post_meta($post_id, '_vms_band_vendor_id', true);
                        $venue_id_for_apply = isset($request['vms_venue_id']) ? absint($request['vms_venue_id']) : 0;
                        if ($venue_id_for_apply <= 0) {
                            $k_venue_id = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'venue_id') ?: '_vms_venue_id') : '_vms_venue_id';
                            $venue_id_for_apply = (int) get_post_meta($post_id, $k_venue_id, true);
                        }
                        $event_date_for_apply = isset($request['vms_event_date']) ? sanitize_text_field((string) $request['vms_event_date']) : '';
                        if ($event_date_for_apply === '') {
                            $k_event_date = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'date') ?: '_vms_event_date') : '_vms_event_date';
                            $event_date_for_apply = (string) get_post_meta($post_id, $k_event_date, true);
                        }

                        if ($vendor_id_for_apply <= 0) {
                            bvmgr_add_admin_notice(__('Select a Primary Vendor first.', 'backstage-venue-manager'), 'error');
                            bvmgr_admin_scroll_to_compensation($post_id);
                            break;
                        }

                        if (!function_exists('bvmgr_get_vendor_default_comp_terms') || !function_exists('bvmgr_event_plan_apply_comp_terms')) {
                            bvmgr_add_admin_notice(__('Vendor default helper is missing.', 'backstage-venue-manager'), 'error');
                            bvmgr_admin_scroll_to_compensation($post_id);
                            break;
                        }

                        $vendor_terms = (array) bvmgr_get_vendor_default_comp_terms($vendor_id_for_apply, (int) $venue_id_for_apply, (string) $event_date_for_apply);
                        if (function_exists('bvmgr_get_vendor_default_agent_fee_terms')) {
                            $vendor_agent_fee = (array) bvmgr_get_vendor_default_agent_fee_terms($vendor_id_for_apply);
                            if (!empty($vendor_agent_fee)) {
                                $vendor_terms = array_merge($vendor_terms, $vendor_agent_fee);
                            }
                        }

                        if (empty($vendor_terms) || empty($vendor_terms['structure'])) {
                            bvmgr_add_admin_notice(__('No current Primary Vendor default is configured for this Event Plan context.', 'backstage-venue-manager'), 'error');
                            bvmgr_admin_scroll_to_compensation($post_id);
                            break;
                        }

                        $applied_vendor_defaults = bvmgr_event_plan_apply_comp_terms($post_id, $vendor_terms);
                        if (!$applied_vendor_defaults) {
                            bvmgr_add_admin_notice(__('Could not apply the current Primary Vendor default to Draft Pay.', 'backstage-venue-manager'), 'error');
                            bvmgr_admin_scroll_to_compensation($post_id);
                            break;
                        }

                        update_post_meta($post_id, '_vms_comp_needs_snapshot', '1');
                        delete_post_meta($post_id, '_vms_comp_package_id');
                        $k_comp_selected_option = function_exists('bvmgr_meta_key')
                            ? (bvmgr_meta_key('event_plan', 'comp_selected_option') ?: '_vms_comp_selected_option')
                            : '_vms_comp_selected_option';
                        update_post_meta($post_id, $k_comp_selected_option, 'default:vendor');
                        bvmgr_add_admin_notice(__('Current Primary Vendor default applied to Draft Pay.', 'backstage-venue-manager'), 'success');
                        bvmgr_admin_scroll_to_compensation($post_id);
                        break;

                    case 'apply_venue_defaults':
                        $venue_id   = isset($request['vms_venue_id']) ? absint($request['vms_venue_id']) : 0;
                        $event_date = isset($request['vms_event_date']) ? sanitize_text_field((string) $request['vms_event_date']) : '';

                        if ($venue_id <= 0 || !$event_date) {
                            bvmgr_add_admin_notice(__('Select a Venue and Event Date first.', 'backstage-venue-manager'), 'error');
                            break;
                        }
                        if (!function_exists('bvmgr_get_event_plan_effective_comp_default')) {
                            bvmgr_add_admin_notice(__('Effective defaults helper is missing.', 'backstage-venue-manager'), 'error');
                            break;
                        }

                        $resolved = (array) bvmgr_get_event_plan_effective_comp_default($venue_id, $event_date);
                        if (empty($resolved['has_default']) || empty($resolved['structure'])) {
                            bvmgr_add_admin_notice(__('No date defaults found for that date/day.', 'backstage-venue-manager'), 'error');
                            break;
                        }

                        if (function_exists('bvmgr_event_plan_apply_comp_terms')) {
                            $applied = bvmgr_event_plan_apply_comp_terms($post_id, $resolved);
                            if (!$applied) {
                                bvmgr_add_admin_notice(__('Could not apply date defaults because the resolved compensation terms were invalid.', 'backstage-venue-manager'), 'error');
                                break;
                            }
                        } else {
                            update_post_meta($post_id, '_vms_comp_structure', sanitize_text_field((string) $resolved['structure']));

                            if (array_key_exists('flat_fee_amount', $resolved)) {
                                $val = $resolved['flat_fee_amount'];
                                if ($val === '' || $val === null) delete_post_meta($post_id, '_vms_flat_fee_amount');
                                else update_post_meta($post_id, '_vms_flat_fee_amount', (float) $val);
                            }

                            if (array_key_exists('door_split_percent', $resolved) && in_array((string) $resolved['structure'], array('door_split', 'flat_fee_door_split'), true)) {
                                $val = $resolved['door_split_percent'];
                                if ($val === '' || $val === null) delete_post_meta($post_id, '_vms_door_split_percent');
                                else update_post_meta($post_id, '_vms_door_split_percent', (float) $val);
                            } else {
                                delete_post_meta($post_id, '_vms_door_split_percent');
                            }

                            if ((string) $resolved['structure'] === 'attendance_bonus') {
                                if (!empty($resolved['attendance_bonus_mode'])) {
                                    update_post_meta($post_id, '_vms_attendance_bonus_mode', (string) $resolved['attendance_bonus_mode']);
                                }
                                if (array_key_exists('attendance_bonus_start_count', $resolved) && $resolved['attendance_bonus_start_count'] !== null) {
                                    update_post_meta($post_id, '_vms_attendance_bonus_start_count', (int) $resolved['attendance_bonus_start_count']);
                                }
                                if (array_key_exists('attendance_bonus_max_bonus', $resolved) && $resolved['attendance_bonus_max_bonus'] !== null && $resolved['attendance_bonus_max_bonus'] !== '') {
                                    update_post_meta($post_id, '_vms_attendance_bonus_max_bonus', (float) $resolved['attendance_bonus_max_bonus']);
                                } else {
                                    delete_post_meta($post_id, '_vms_attendance_bonus_max_bonus');
                                }
                                if ((string) ($resolved['attendance_bonus_mode'] ?? '') === 'step') {
                                    update_post_meta($post_id, '_vms_attendance_bonus_step_size', (int) ($resolved['attendance_bonus_step_size'] ?? 0));
                                    update_post_meta($post_id, '_vms_attendance_bonus_step_bonus', (float) ($resolved['attendance_bonus_step_bonus'] ?? 0));
                                    delete_post_meta($post_id, '_vms_attendance_bonus_per_ticket_rate');
                                } else {
                                    update_post_meta($post_id, '_vms_attendance_bonus_per_ticket_rate', (float) ($resolved['attendance_bonus_per_ticket_rate'] ?? 0));
                                    delete_post_meta($post_id, '_vms_attendance_bonus_step_size');
                                    delete_post_meta($post_id, '_vms_attendance_bonus_step_bonus');
                                }
                            } else {
                                delete_post_meta($post_id, '_vms_attendance_bonus_mode');
                                delete_post_meta($post_id, '_vms_attendance_bonus_start_count');
                                delete_post_meta($post_id, '_vms_attendance_bonus_step_size');
                                delete_post_meta($post_id, '_vms_attendance_bonus_step_bonus');
                                delete_post_meta($post_id, '_vms_attendance_bonus_per_ticket_rate');
                                delete_post_meta($post_id, '_vms_attendance_bonus_max_bonus');
                            }
                        }

                        update_post_meta($post_id, '_vms_comp_needs_snapshot', '1');
                        $applied_label = isset($resolved['label']) ? trim((string) $resolved['label']) : '';
                        if ($applied_label === '') {
                            $applied_label = __('Date defaults', 'backstage-venue-manager');
                        }
                        $applied_label = sanitize_text_field($applied_label);
                        /* translators: %s: applied date-default label. */
                        bvmgr_add_admin_notice(sprintf(__('%s applied for this date.', 'backstage-venue-manager'), $applied_label), 'success');
                        bvmgr_admin_scroll_to_compensation($post_id);
                        break;

                    case 'apply_comp_package':
                        if ($venue_id <= 0) {
                            bvmgr_add_admin_notice(__('Please select a Venue first, then apply the package.', 'backstage-venue-manager'), 'error');
                            break;
                        }
                        if ($comp_package_id <= 0) {
                            bvmgr_add_admin_notice(__('Please select a comp package first.', 'backstage-venue-manager'), 'error');
                            break;
                        }
                        if (!function_exists('bvmgr_apply_comp_package_to_plan')) {
                            bvmgr_add_admin_notice(__('Package apply helper is missing (bvmgr_apply_comp_package_to_plan).', 'backstage-venue-manager'), 'error');
                            break;
                        }

                        $ok = bvmgr_apply_comp_package_to_plan($post_id, $comp_package_id);
                        bvmgr_admin_scroll_to_compensation($post_id);

                        if ($ok) bvmgr_add_admin_notice(__('Comp package applied and snapshotted for this event plan.', 'backstage-venue-manager'), 'success');
                        else bvmgr_add_admin_notice(__('Failed to Apply Package. (Check package type/meta.)', 'backstage-venue-manager'), 'error');
                        break;

                    case 'lock_draft_pay':
                        $structure = (string) get_post_meta($post_id, '_vms_comp_structure', true);
                        if ($structure === '') $structure = 'flat_fee';

                        $flat  = get_post_meta($post_id, '_vms_flat_fee_amount', true);
                        $split = get_post_meta($post_id, '_vms_door_split_percent', true);
                        $bonus_mode = (string) get_post_meta($post_id, '_vms_attendance_bonus_mode', true);
                        $bonus_start_count = get_post_meta($post_id, '_vms_attendance_bonus_start_count', true);
                        $bonus_step_size = get_post_meta($post_id, '_vms_attendance_bonus_step_size', true);
                        $bonus_step_bonus = get_post_meta($post_id, '_vms_attendance_bonus_step_bonus', true);
                        $bonus_per_ticket_rate = get_post_meta($post_id, '_vms_attendance_bonus_per_ticket_rate', true);
                        $bonus_max_bonus = get_post_meta($post_id, '_vms_attendance_bonus_max_bonus', true);
                        $commission_percent = get_post_meta($post_id, '_vms_commission_percent', true);
                        $commission_mode = (string) get_post_meta($post_id, '_vms_commission_mode', true);
                        if (!in_array($commission_mode, array('artist_fee', 'gross'), true)) $commission_mode = 'artist_fee';
                        $deposit_terms = function_exists('bvmgr_get_event_plan_deposit_terms') ? (array) bvmgr_get_event_plan_deposit_terms((int) $post_id) : array();
                        $final_payment_terms = function_exists('bvmgr_get_event_plan_final_payment_terms') ? (array) bvmgr_get_event_plan_final_payment_terms((int) $post_id) : array();

                        $flat  = ($flat === '' || $flat === null) ? null : (float) $flat;
                        $split = ($split === '' || $split === null) ? null : (float) $split;
                        $bonus_start_count = ($bonus_start_count === '' || $bonus_start_count === null) ? null : (int) $bonus_start_count;
                        $bonus_step_size = ($bonus_step_size === '' || $bonus_step_size === null) ? null : (int) $bonus_step_size;
                        $bonus_step_bonus = ($bonus_step_bonus === '' || $bonus_step_bonus === null) ? null : (float) $bonus_step_bonus;
                        $bonus_per_ticket_rate = ($bonus_per_ticket_rate === '' || $bonus_per_ticket_rate === null) ? null : (float) $bonus_per_ticket_rate;
                        $bonus_max_bonus = ($bonus_max_bonus === '' || $bonus_max_bonus === null) ? null : (float) $bonus_max_bonus;
                        $commission_percent = ($commission_percent === '' || $commission_percent === null) ? null : max(0, (float) $commission_percent);

                        if (in_array($structure, array('flat_fee', 'flat_fee_door_split'), true) && ($flat === null || $flat <= 0)) {
                            bvmgr_add_admin_notice(__('Cannot lock Draft Pay: Flat Fee Amount is required for this structure.', 'backstage-venue-manager'), 'error');
                            break;
                        }
                        if (in_array($structure, array('door_split', 'flat_fee_door_split'), true) && ($split === null || $split <= 0 || $split > 100)) {
                            bvmgr_add_admin_notice(__('Cannot lock Draft Pay: Door Split % must be between 1 and 100 for this structure.', 'backstage-venue-manager'), 'error');
                            break;
                        }
                        if ($structure === 'attendance_bonus') {
                            if ($flat === null || $flat < 0) {
                                bvmgr_add_admin_notice(__('Cannot lock Draft Pay: Base Pay is required for Base + Attendance Bonus.', 'backstage-venue-manager'), 'error');
                                break;
                            }
                            if (!in_array($bonus_mode, array('step', 'continuous'), true)) {
                                bvmgr_add_admin_notice(__('Cannot lock Draft Pay: Bonus Style is required for Base + Attendance Bonus.', 'backstage-venue-manager'), 'error');
                                break;
                            }
                            if ($bonus_start_count === null || $bonus_start_count < 0) {
                                bvmgr_add_admin_notice(__('Cannot lock Draft Pay: Bonus Starts After is required for Base + Attendance Bonus.', 'backstage-venue-manager'), 'error');
                                break;
                            }
                            if ($bonus_mode === 'step') {
                                if ($bonus_step_size === null || $bonus_step_size < 1) {
                                    bvmgr_add_admin_notice(__('Cannot lock Draft Pay: Step Size must be at least 1 for step attendance bonus mode.', 'backstage-venue-manager'), 'error');
                                    break;
                                }
                                if ($bonus_step_bonus === null || $bonus_step_bonus < 0) {
                                    bvmgr_add_admin_notice(__('Cannot lock Draft Pay: Bonus Per Step is required for step attendance bonus mode.', 'backstage-venue-manager'), 'error');
                                    break;
                                }
                            } else {
                                if ($bonus_per_ticket_rate === null || $bonus_per_ticket_rate < 0) {
                                    bvmgr_add_admin_notice(__('Cannot lock Draft Pay: Bonus Per Ticket is required for continuous attendance bonus mode.', 'backstage-venue-manager'), 'error');
                                    break;
                                }
                            }
                        }

                        $pkg_id    = (int) get_post_meta($post_id, '_vms_comp_package_id', true);
                        $pkg_title = $pkg_id ? (string) get_the_title($pkg_id) : '';

                        $snapshot = array(
                            'locked_via'         => 'manual_lock',
                            'package_id'         => $pkg_id ?: null,
                            'package_title'      => $pkg_title ?: null,
                            'applied_at'         => current_time('mysql'),
                            'structure'          => $structure,
                            'flat_fee_amount'    => $flat,
                            'door_split_percent' => $split,
                            'attendance_bonus_mode' => ($structure === 'attendance_bonus') ? $bonus_mode : null,
                            'attendance_bonus_start_count' => ($structure === 'attendance_bonus') ? $bonus_start_count : null,
                            'attendance_bonus_step_size' => ($structure === 'attendance_bonus' && $bonus_mode === 'step') ? $bonus_step_size : null,
                            'attendance_bonus_step_bonus' => ($structure === 'attendance_bonus' && $bonus_mode === 'step') ? $bonus_step_bonus : null,
                            'attendance_bonus_per_ticket_rate' => ($structure === 'attendance_bonus' && $bonus_mode === 'continuous') ? $bonus_per_ticket_rate : null,
                            'attendance_bonus_max_bonus' => ($structure === 'attendance_bonus') ? $bonus_max_bonus : null,
                            'commission_percent' => in_array($structure, array('flat_fee', 'flat_fee_door_split', 'attendance_bonus'), true) ? $commission_percent : null,
                            'commission_mode' => (in_array($structure, array('flat_fee', 'flat_fee_door_split', 'attendance_bonus'), true) && $commission_percent !== null && $commission_percent > 0) ? $commission_mode : null,
                        );

                        if (!empty($deposit_terms)) {
                            $snapshot = array_merge($snapshot, $deposit_terms);
                        }
                        if (!empty($final_payment_terms)) {
                            $snapshot = array_merge($snapshot, $final_payment_terms);
                        }

                        $hash = function_exists('bvmgr_comp_hash_for_plan')
                            ? (string) bvmgr_comp_hash_for_plan($post_id)
                            : md5(wp_json_encode(array('structure' => $structure, 'flat' => $flat, 'split' => $split)));

                        $snapshot['comp_hash'] = $hash;

                        update_post_meta($post_id, '_vms_comp_snapshot', $snapshot);
                        delete_post_meta($post_id, '_vms_comp_needs_snapshot');

                        bvmgr_add_admin_notice(__('Draft Pay locked for this event (snapshot created).', 'backstage-venue-manager'), 'success');
                        bvmgr_admin_scroll_to_compensation($post_id);
                        break;
                }

                }

                // HARD RULE: plans must never save with an empty title.
                $current_title = trim((string) get_the_title($post_id));
                if ($current_title === '' || strcasecmp($current_title, 'Auto Draft') === 0) {

                    $band_id   = (int) get_post_meta($post_id, '_vms_band_vendor_id', true);
                    $band_name = $band_id ? (string) get_the_title($band_id) : '';

                    $venue_id   = (int) get_post_meta($post_id, '_vms_venue_id', true);
                    $venue_name = $venue_id ? (string) get_the_title($venue_id) : '';

                    $fallback = function_exists('bvmgr_event_plan_build_fallback_title')
                        ? bvmgr_event_plan_build_fallback_title($post_id, (string) $band_name, (string) $venue_name)
                        : ((trim((string) $band_name) !== '')
                            ? trim((string) $band_name)
                            : ((trim((string) $venue_name) !== '')
                                ? trim((string) $venue_name)
                                : ('Event Plan #' . (int) $post_id)));

                    // Update title once, safely
                    remove_action('save_post_vms_event_plan', array($this, 'save_event_plan_meta'), 10);
                    bvmgr_event_plan_perf_wp_update_post(array('ID' => $post_id, 'post_title' => $fallback), 'event_plan_fallback_title_sync', $post_id);
                    add_action('save_post_vms_event_plan', array($this, 'save_event_plan_meta'), 10, 2);
                }


                update_post_meta($post_id, $k_plan_status, $new_status);

                // Sync WP post_status so the editor UI reflects the plan workflow status.
                // - draft     → post_status = draft
                // - ready     → post_status = publish
                // - published → post_status = publish
                // - cancelled → post_status = publish
                $desired_wp_status = ($new_status === 'draft') ? 'draft' : 'publish';
                $post_now = get_post($post_id);

                if ($post_now && $post_now->post_type === 'vms_event_plan' && $post_now->post_status !== $desired_wp_status) {
                    // Prevent recursion loops.
                    remove_action('save_post_vms_event_plan', array($this, 'save_event_plan_meta'), 10);
                    bvmgr_event_plan_perf_wp_update_post(array(
                        'ID' => $post_id,
                        'post_status' => $desired_wp_status,
                    ), 'event_plan_wp_status_sync', $post_id);
                    add_action('save_post_vms_event_plan', array($this, 'save_event_plan_meta'), 10, 2);
                }

                if (
                    $action === 'mark_cancelled'
                    && $new_status === 'cancelled'
                    && $queue_rescheduled_draft_after_cancel
                    && $replacement_date_requested !== ''
                ) {
                    if (!function_exists('bvmgr_event_plan_create_rescheduled_draft')) {
                        bvmgr_add_admin_notice(__('Reschedule helper is unavailable in this build.', 'backstage-venue-manager'), 'error');
                    } else {
                        $created = (array) bvmgr_event_plan_create_rescheduled_draft($post_id, array(
                            'replacement_date' => $replacement_date_requested,
                            'post_title'       => (string) get_the_title($post_id),
                        ));

                        if (empty($created['ok'])) {
                            $err = (string) ($created['error_message'] ?? __('Backstage Venue Manager could not create the rescheduled draft.', 'backstage-venue-manager'));
                            bvmgr_add_admin_notice(sanitize_text_field($err), 'error');
                        } else {
                            $new_plan_id = absint($created['new_post_id'] ?? 0);
                            if ($new_plan_id > 0 && function_exists('bvmgr_event_plan_set_runtime_redirect_target')) {
                                bvmgr_event_plan_set_runtime_redirect_target($post_id, $new_plan_id, array(
                                    'vms_rescheduled_from' => $post_id,
                                ));
                            }
                            bvmgr_add_admin_notice(__('Linked rescheduled draft created. Review the replacement plan and publish it when ready.', 'backstage-venue-manager'), 'success');
                            bvmgr_add_admin_notice(__('The cancelled Event Plan was preserved and linked to the new draft for audit history.', 'backstage-venue-manager'), 'info');
                        }
                    }
                }

	                $save_scope = function_exists('bvmgr_event_plan_save_profiler_current_save_scope')
	                    ? bvmgr_event_plan_save_profiler_current_save_scope($post_id)
	                    : '';
	                $featured_image_only_save = ($save_scope === 'featured_image_only');
	                if ($save_scope !== '' && function_exists('bvmgr_event_plan_save_profiler_note')) {
	                    bvmgr_event_plan_save_profiler_note('save_scope', $save_scope);
	                }

	                $tec_status_trace = function_exists('bvmgr_event_plan_perf_span_start')
	                    ? bvmgr_event_plan_perf_span_start('event_plan_tec_status_sync', $post_id, array('section' => 'tec_status_sync'))
	                    : '';
	                if ($featured_image_only_save) {
	                    if (function_exists('bvmgr_event_plan_save_profiler_note_heavy_action')) {
	                        bvmgr_event_plan_save_profiler_note_heavy_action('tec_status_sync', 'skipped', 'featured_image_only');
	                    }
	                } elseif (function_exists('vms_sync_tec_status_from_plan')) {
	                    vms_sync_tec_status_from_plan($post_id);
	                }
	                if (function_exists('bvmgr_event_plan_perf_span_finish')) {
	                    bvmgr_event_plan_perf_span_finish('event_plan_tec_status_sync', $post_id, $tec_status_trace, array(
	                        'section' => 'tec_status_sync',
	                        'phase' => $featured_image_only_save ? 'skip' : 'run',
	                        'skip_reason' => $featured_image_only_save ? 'featured_image_only' : '',
	                    ));
	                }

	                $featured_image_trace = function_exists('bvmgr_event_plan_perf_span_start')
	                    ? bvmgr_event_plan_perf_span_start('event_plan_featured_image_sync_on_save', $post_id, array('section' => 'featured_image_sync'))
	                    : '';
	                if (function_exists('bvmgr_event_plan_save_profiler_note_heavy_action')) {
	                    bvmgr_event_plan_save_profiler_note_heavy_action('featured_image_sync_on_save', 'skipped', 'delegated_to_save_post');
	                }
	                if (function_exists('bvmgr_event_plan_perf_span_finish')) {
	                    bvmgr_event_plan_perf_span_finish('event_plan_featured_image_sync_on_save', $post_id, $featured_image_trace, array(
	                        'section' => 'featured_image_sync',
	                        'phase' => 'skip',
	                        'skip_reason' => 'delegated_to_save_post',
	                    ));
	                }

	                $saved_side_effects_trace = function_exists('bvmgr_event_plan_perf_span_start')
	                    ? bvmgr_event_plan_perf_span_start('event_plan_saved_side_effects', $post_id, array('section' => 'saved_side_effects'))
	                    : '';
	                if ($featured_image_only_save) {
	                    if (function_exists('bvmgr_event_plan_save_profiler_note_heavy_action')) {
	                        bvmgr_event_plan_save_profiler_note_heavy_action('event_plan_saved_side_effects', 'skipped', 'featured_image_only');
	                    }
	                } else {
	                    do_action('vms_event_plan_saved', (int) $post_id, array(
	                        'plan_status' => (string) $new_status,
	                        'actor_user_id' => (int) get_current_user_id(),
	                    ));
	                }
	                if (function_exists('bvmgr_event_plan_perf_span_finish')) {
	                    bvmgr_event_plan_perf_span_finish('event_plan_saved_side_effects', $post_id, $saved_side_effects_trace, array(
	                        'section' => 'saved_side_effects',
	                        'phase' => $featured_image_only_save ? 'skip' : 'run',
	                        'skip_reason' => $featured_image_only_save ? 'featured_image_only' : '',
	                    ));
	                }

                if (function_exists('bvmgr_event_plan_perf_span_finish')) {
                    bvmgr_event_plan_perf_span_finish(
                        'save_post_vms_event_plan_core',
                        $post_id,
                        $save_trace,
                        array(
                            'create' => ($original_status === 'auto-draft') ? 1 : 0,
                            'update' => ($original_status === 'auto-draft') ? 0 : 1,
                            'old_status' => $original_status,
                            'new_status' => sanitize_key((string) get_post_status($post_id)),
                            'actor_user_id' => $actor_user_id,
                        )
                    );
                }
            }
        }

        /**
         * Bootstrap admin hooks for Event Plans.
         */
        if (is_admin()) {
            new BVMGR_Admin_Event_Plans();
        }


        // Published-only by default in admin list views; optional what-if toggle includes Draft/Ready.
        // Applies to: edit.php?post_type=vms_event_plan
        if (is_admin()) {

            function bvmgr_admin_event_plan_list_query_value(string $key): string
            {
                // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended -- Read-only list-filter routing; persistence is nonce-gated separately.
                if (!isset($_GET[$key]) || is_array($_GET[$key])) {
                    return '';
                }

                // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Helper centralizes the raw list-filter read; callers sanitize by expected type.
                return (string) wp_unslash($_GET[$key]);
            }

            function bvmgr_admin_event_plan_list_has_valid_filter_nonce(): bool
            {
                $nonce = bvmgr_admin_event_plan_list_query_value('_bvmgr_ep_list_nonce');
                if ($nonce === '') {
                    return false;
                }

	                $nonce = sanitize_text_field($nonce);
	                return (bool) wp_verify_nonce($nonce, bvmgr_nonce_action_for_value($nonce, 'bvmgr_event_plan_list_filters'));
            }
    
            function bvmgr_admin_event_plan_list_include_drafts_requested(): bool
            {
                $user_id = (int) get_current_user_id();

                // Persisted default (per-user). If the user has never set a preference,
                // default to showing Draft/Ready so the Event Plans list matches Schedule visibility.
                $has_pref = (function_exists('bvmgr_user_pref_has_include_drafts'))
                    ? (bool) bvmgr_user_pref_has_include_drafts($user_id)
                    : (bool) metadata_exists('user', $user_id, '_vms_include_drafts');

                $pref = $has_pref
                    ? ((function_exists('bvmgr_user_pref_get_include_drafts')) ? (bool) bvmgr_user_pref_get_include_drafts($user_id) : false)
                    : true;

                // If explicitly set in the querystring, treat it as an intentional update and persist it.
                $requested_include_drafts = bvmgr_admin_event_plan_list_query_value('include_drafts');
                if ($requested_include_drafts !== '') {
                    $raw = strtolower(trim($requested_include_drafts));
                    $val = in_array($raw, array('1', 'true', 'yes', 'on'), true);

                    if (bvmgr_admin_event_plan_list_has_valid_filter_nonce()) {
                        if (function_exists('bvmgr_user_pref_set_include_drafts')) {
                            bvmgr_user_pref_set_include_drafts((bool) $val, $user_id);
                        } else {
                            update_user_meta($user_id, '_vms_include_drafts', $val ? '1' : '0');
                        }
                    }

                    return (bool) $val;
                }

                return (bool) $pref;
            }

            function bvmgr_admin_event_plan_list_add_include_drafts_toggle(): void
            {
                global $typenow;
                if ($typenow !== 'vms_event_plan') {
                    return;
                }

                $checked = bvmgr_admin_event_plan_list_include_drafts_requested();

                echo '<input type="hidden" name="include_drafts" value="0">';
                wp_nonce_field('bvmgr_event_plan_list_filters', '_bvmgr_ep_list_nonce', false);

                echo '<label class="vms-ep-list-toggle" data-vms-tour="event-plans.include-drafts">';
                echo '<input type="checkbox" name="include_drafts" value="1"' . checked(true, $checked, false) . '>';
                echo ' ' . esc_html__('Include Draft/Ready (what-if)', 'backstage-venue-manager');
                echo '</label>';

                $tour_button = '<button type="button" class="button button-secondary vms-tour-help-trigger" data-vms-tour-start="vms.event_plan.list.basics" data-vms-tour="event-plans.help">' . esc_html__('Start Guided Tour', 'backstage-venue-manager') . '</button>';
                if (function_exists('bvmgr_render_help_button')) {
                    $tour_button = bvmgr_render_help_button(array(
                        'tour_id' => 'vms.event_plan.list.basics',
                        'anchor' => 'event-plans.help',
                        'label' => __('Start Guided Tour', 'backstage-venue-manager'),
                        'class' => 'button-secondary',
                    ));
                }

                echo ' <span class="vms-ep-list-help" data-vms-tour="event-plans.help-action">' . wp_kses_post($tour_button) . '</span>';
            }
            add_action('restrict_manage_posts', 'bvmgr_admin_event_plan_list_add_include_drafts_toggle');

            function bvmgr_admin_event_plan_list_default_published_only_query($query): void
            {
                if (!is_admin() || !($query instanceof WP_Query) || !$query->is_main_query()) {
                    return;
                }

                global $pagenow;
                if ($pagenow !== 'edit.php') {
                    return;
                }

                if ($query->get('post_type') !== 'vms_event_plan') {
                    return;
                }

                // Respect explicit WP status views (Draft, Trash, etc).
                $ps_req = sanitize_key(bvmgr_admin_event_plan_list_query_value('post_status'));
                if ($ps_req !== '' && $ps_req !== 'all') {
                    return;
                }

                $include_drafts = bvmgr_admin_event_plan_list_include_drafts_requested();

                // Narrow the query for performance.
                $query->set('post_status', $include_drafts
                    ? array('publish', 'private', 'draft', 'pending', 'future')
                    : array('publish', 'private')
                );

                // Marker for the_posts filter.
                $query->set('vms_ep_filter_internal_status', 1);
                $query->set('vms_ep_include_drafts', $include_drafts ? 1 : 0);
            }
            add_action('pre_get_posts', 'bvmgr_admin_event_plan_list_default_published_only_query');

            function bvmgr_admin_event_plan_list_filter_by_internal_status($posts, $query)
            {
                if (!is_admin() || !($query instanceof WP_Query) || !$query->is_main_query()) {
                    return $posts;
                }

                if ((int) $query->get('vms_ep_filter_internal_status') !== 1) {
                    return $posts;
                }

                if ($query->get('post_type') !== 'vms_event_plan') {
                    return $posts;
                }

                $include_drafts = ((int) $query->get('vms_ep_include_drafts') === 1);
                $out = array();

                foreach ((array) $posts as $p) {
                    if (!is_object($p) || empty($p->ID)) {
                        continue;
                    }

                    $pid = absint($p->ID);
                    if ($pid <= 0) {
                        continue;
                    }

                    if (function_exists('bvmgr_event_plan_should_include')) {
                        if (!bvmgr_event_plan_should_include($pid, 'event_list', array(
                            'include_drafts'    => (bool) $include_drafts,
                            'include_cancelled' => true,
                        ))) {
                            continue;
                        }
                    } else {
                        // Fallback: Published-only by WP status.
                        if (!$include_drafts) {
                            $ps = (string) get_post_status($pid);
                            if (!in_array($ps, array('publish', 'private'), true)) {
                                continue;
                            }
                        }
                    }

                    $out[] = $p;
                }

                return $out;
            }
            add_filter('the_posts', 'bvmgr_admin_event_plan_list_filter_by_internal_status', 10, 2);
        }

        /**
         * Validation — used for READY and PUBLISH
         */
        function bvmgr_event_plan_is_venue_closed_for_event_date(int $venue_id, string $event_date, array &$context = array()): bool
        {
            $context = array(
                'reason' => '',
                'holiday_name' => '',
            );

            $venue_id = absint($venue_id);
            $event_date = trim((string) $event_date);
            if ($venue_id <= 0 || $event_date === '') {
                return false;
            }

            // Holiday precedence: explicit OPEN wins, explicit CLOSED blocks.
            if (function_exists('bvmgr_get_venue_holiday_for_date')) {
                $holiday = bvmgr_get_venue_holiday_for_date($venue_id, $event_date);
                if (is_array($holiday) && !empty($holiday)) {
                    $holiday_status = sanitize_key((string) ($holiday['status'] ?? ''));
                    $holiday_name = trim((string) ($holiday['name'] ?? ''));

                    if ($holiday_name !== '') {
                        $context['holiday_name'] = $holiday_name;
                    }

                    if ($holiday_status === 'open') {
                        $context['reason'] = 'holiday_open_override';
                        return false;
                    }

                    if ($holiday_status === 'closed') {
                        $context['reason'] = 'holiday_closed';
                        return true;
                    }
                }
            }

            // When Season Dates open-window rules exist, enforce them directly from rules
            // (not generated payload) to avoid stale-payload false negatives.
            if (function_exists('bvmgr_sch_season_get_rules') && function_exists('bvmgr_sch_season_is_open_by_rules')) {
                $rules = bvmgr_sch_season_get_rules($venue_id);
                $has_enabled_open_window = false;

                if (is_array($rules)) {
                    foreach ($rules as $rule) {
                        if (!is_array($rule)) {
                            continue;
                        }
                        if (empty($rule['enabled'])) {
                            continue;
                        }
                        if ((string) ($rule['type'] ?? '') !== 'open_window') {
                            continue;
                        }
                        $has_enabled_open_window = true;
                        break;
                    }
                }

                if ($has_enabled_open_window) {
                    $is_open = (bool) bvmgr_sch_season_is_open_by_rules((array) $rules, $event_date);
                    $context['reason'] = $is_open ? 'season_rules_open' : 'season_rules_closed';
                    return !$is_open;
                }
            }

            return false;
        }

        function bvmgr_validate_event_plan(int $post_id): array
        {
            $errors = array();

            $event_date = (string) get_post_meta($post_id, '_vms_event_date', true);
            $start_time = (string) get_post_meta($post_id, '_vms_start_time', true);
            $end_time   = (string) get_post_meta($post_id, '_vms_end_time', true);

            if ($event_date === '') $errors[] = __('Event date is required.', 'backstage-venue-manager');

            if ($start_time === '' || $end_time === '') {
                $errors[] = __('Start time and end time are required.', 'backstage-venue-manager');
        	} else {
        	    $start_ts = strtotime($event_date . ' ' . $start_time);
        	    $end_ts   = strtotime($event_date . ' ' . $end_time);

        	    if (!$start_ts || !$end_ts) {
			$errors[] = __('Start time or end time is not a valid time.', 'backstage-venue-manager');
        	    } elseif ($end_ts <= $start_ts) {
        	        // If the end time is earlier than the start time, assume the event crosses midnight.
        	        $bump = defined('DAY_IN_SECONDS') ? (int) DAY_IN_SECONDS : 86400;
        	        $end_ts = $end_ts + $bump;
        	        if ($end_ts <= $start_ts) {
			    $errors[] = __('End time must be after start time.', 'backstage-venue-manager');
                    }

        	    }
        	}

			if (function_exists('bvmgr_event_plan_is_externally_ticketed') && bvmgr_event_plan_is_externally_ticketed($post_id)) {
				$external_url = function_exists('bvmgr_event_plan_get_external_ticket_url')
					? bvmgr_event_plan_get_external_ticket_url($post_id)
					: '';
				if ($external_url === '') {
					$errors[] = __('External Ticketing requires a complete http:// or https:// ticket purchase URL before this Event Plan can be marked Ready or published.', 'backstage-venue-manager');
				}
			}

            $comp_structure     = (string) get_post_meta($post_id, '_vms_comp_structure', true);
            if ($comp_structure === '') $comp_structure = 'flat_fee';

            $flat_fee_amount    = get_post_meta($post_id, '_vms_flat_fee_amount', true);
            $door_split_percent = get_post_meta($post_id, '_vms_door_split_percent', true);
            $attendance_bonus_mode = (string) get_post_meta($post_id, '_vms_attendance_bonus_mode', true);
            $attendance_bonus_start_count = get_post_meta($post_id, '_vms_attendance_bonus_start_count', true);
            $attendance_bonus_step_size = get_post_meta($post_id, '_vms_attendance_bonus_step_size', true);
            $attendance_bonus_step_bonus = get_post_meta($post_id, '_vms_attendance_bonus_step_bonus', true);
            $attendance_bonus_per_ticket_rate = get_post_meta($post_id, '_vms_attendance_bonus_per_ticket_rate', true);
            $attendance_bonus_max_bonus = get_post_meta($post_id, '_vms_attendance_bonus_max_bonus', true);

            if (in_array($comp_structure, array('flat_fee', 'flat_fee_door_split'), true)) {
                if ($flat_fee_amount === '' || $flat_fee_amount === null) $errors[] = __('Flat fee amount is required for this compensation structure.', 'backstage-venue-manager');
                elseif (!is_numeric($flat_fee_amount) || (float)$flat_fee_amount <= 0) $errors[] = __('Flat fee amount must be a positive number.', 'backstage-venue-manager');
            }

            if ($comp_structure === 'attendance_bonus') {
                if ($flat_fee_amount === '' || $flat_fee_amount === null) {
                    $errors[] = __('Base Pay is required for Base + Attendance Bonus.', 'backstage-venue-manager');
                } elseif (!is_numeric($flat_fee_amount) || (float) $flat_fee_amount < 0) {
                    $errors[] = __('Base Pay must be a non-negative number.', 'backstage-venue-manager');
                }

                if (!in_array($attendance_bonus_mode, array('step', 'continuous'), true)) {
                    $errors[] = __('Bonus Style is required for Base + Attendance Bonus.', 'backstage-venue-manager');
                }

                if ($attendance_bonus_start_count === '' || $attendance_bonus_start_count === null) {
                    $errors[] = __('Bonus Starts After is required for Base + Attendance Bonus.', 'backstage-venue-manager');
                } elseif (!is_numeric($attendance_bonus_start_count) || (float) $attendance_bonus_start_count < 0 || floor((float) $attendance_bonus_start_count) !== (float) $attendance_bonus_start_count) {
                    $errors[] = __('Bonus Starts After must be a whole number that is 0 or greater.', 'backstage-venue-manager');
                }

                if ($attendance_bonus_mode === 'step') {
                    if ($attendance_bonus_step_size === '' || $attendance_bonus_step_size === null) {
                        $errors[] = __('Step Size is required for step attendance bonus mode.', 'backstage-venue-manager');
                    } elseif (!is_numeric($attendance_bonus_step_size) || (float) $attendance_bonus_step_size < 1 || floor((float) $attendance_bonus_step_size) !== (float) $attendance_bonus_step_size) {
                        $errors[] = __('Step Size must be a whole number that is at least 1.', 'backstage-venue-manager');
                    }

                    if ($attendance_bonus_step_bonus === '' || $attendance_bonus_step_bonus === null) {
                        $errors[] = __('Bonus Per Step is required for step attendance bonus mode.', 'backstage-venue-manager');
                    } elseif (!is_numeric($attendance_bonus_step_bonus) || (float) $attendance_bonus_step_bonus < 0) {
                        $errors[] = __('Bonus Per Step must be a non-negative number.', 'backstage-venue-manager');
                    }
                } elseif ($attendance_bonus_mode === 'continuous') {
                    if ($attendance_bonus_per_ticket_rate === '' || $attendance_bonus_per_ticket_rate === null) {
                        $errors[] = __('Bonus Per Ticket is required for continuous attendance bonus mode.', 'backstage-venue-manager');
                    } elseif (!is_numeric($attendance_bonus_per_ticket_rate) || (float) $attendance_bonus_per_ticket_rate < 0) {
                        $errors[] = __('Bonus Per Ticket must be a non-negative number.', 'backstage-venue-manager');
                    }
                }

                if ($attendance_bonus_max_bonus !== '' && $attendance_bonus_max_bonus !== null) {
                    if (!is_numeric($attendance_bonus_max_bonus) || (float) $attendance_bonus_max_bonus < 0) {
                        $errors[] = __('Max Bonus must be a non-negative number when provided.', 'backstage-venue-manager');
                    }
                }
            }

            if (in_array($comp_structure, array('door_split', 'flat_fee_door_split'), true)) {
                if ($door_split_percent === '' || $door_split_percent === null) $errors[] = __('Door split percentage is required for this compensation structure.', 'backstage-venue-manager');
                elseif (!is_numeric($door_split_percent)) $errors[] = __('Door split percentage must be a number.', 'backstage-venue-manager');
                else {
                    $pct = (float) $door_split_percent;
                    if ($pct <= 0 || $pct > 100) $errors[] = __('Door split percentage must be between 1 and 100.', 'backstage-venue-manager');
                }
            }

            // Band required
            $band_id = (int) get_post_meta($post_id, '_vms_band_vendor_id', true);
            if (!$band_id) {
                $errors[] = __('A Primary Vendor must be selected before marking this event Ready.', 'backstage-venue-manager');
                return $errors;
            }

            // Band exists (guard against deleted vendor IDs lingering on published plans)
            if (function_exists('bvmgr_event_plan_vendor_exists') && !bvmgr_event_plan_vendor_exists((int) $band_id)) {
                $errors[] = __('Selected Primary Vendor no longer exists (it was deleted). Select a new Primary Vendor before marking Ready.', 'backstage-venue-manager');
                return $errors;
            }

            if ($event_date && function_exists('bvmgr_get_vendor_availability_for_date')) {
                $availability = bvmgr_get_vendor_availability_for_date($band_id, $event_date);
                if ($availability === 'unavailable') {
                    $band_name = get_the_title($band_id) ?: __('Selected Primary Vendor', 'backstage-venue-manager');
                    $nice_date = date_i18n('M j, Y', strtotime($event_date));
                    /* translators: 1: primary vendor name, 2: event date. */
                    $errors[] = sprintf(__('%1$s is marked Not Available on %2$s.', 'backstage-venue-manager'), $band_name, $nice_date);
                }
            }

            // Vendor tax profile required unless bypass is active
            if ($band_id > 0) {
                $missing = function_exists('bvmgr_vendor_tax_profile_missing_items') ? (array) bvmgr_vendor_tax_profile_missing_items($band_id) : array();
                if (!empty($missing)) {
                    if (function_exists('bvmgr_tax_bypass_is_active') && bvmgr_tax_bypass_is_active($band_id)) {
                        if (function_exists('bvmgr_add_admin_notice')) {
                            bvmgr_add_admin_notice(
                                sprintf(
                                    /* translators: 1: primary vendor name, 2: bypass expiration date. */
                                    __('Tax profile bypass active for "%1$s" until %2$s. Ready/Publish allowed, but W-9 is still required.', 'backstage-venue-manager'),
                                    get_the_title($band_id),
                                    (string) get_post_meta($band_id, '_vms_tax_bypass_until', true)
                                ),
                                'warning'
                            );
                        }
                    } else {
                        if (function_exists('bvmgr_add_admin_notice')) {
                            $vendor_name = get_the_title($band_id);
                            bvmgr_add_admin_notice(
                                sprintf(
                                    /* translators: 1: primary vendor name, 2: comma-separated missing tax profile items. */
                                    __('Primary Vendor "%1$s" is missing required Tax Profile items: %2$s. Ready/Publish allowed with warning, but payouts and tax reporting may be blocked.', 'backstage-venue-manager'),
                                    $vendor_name ? $vendor_name : '#' . $band_id,
                                    implode(', ', $missing)
                                ),
                                'warning'
                            );
                        }
                    }
                }
            }

            // Staff tax completeness
            $assignments = get_post_meta($post_id, '_vms_staff_assignments', true);
            if (is_array($assignments)) {
                foreach ($assignments as $role_id => $staff_ids) {
                    if (!is_array($staff_ids)) continue;
                    foreach ($staff_ids as $sid) {
                        $sid = (int) $sid;
                        if ($sid <= 0) continue;

                        $missing = function_exists('bvmgr_vendor_tax_profile_missing_items') ? (array) bvmgr_vendor_tax_profile_missing_items($sid) : array();
                        if (!empty($missing)) {
                            if (function_exists('bvmgr_tax_bypass_is_active') && bvmgr_tax_bypass_is_active($sid)) {
                                if (function_exists('bvmgr_add_admin_notice')) {
                                    bvmgr_add_admin_notice(
                                        sprintf(
                                            /* translators: 1: vendor or staff name, 2: bypass expiration date. */
                                            /* translators: 1: staff member name, 2: bypass expiration date. */
                                            __('Tax profile bypass active for staff "%1$s" until %2$s. Ready/Publish allowed, but W-9 is still required.', 'backstage-venue-manager'),
                                            get_the_title($sid),
                                            (string) get_post_meta($sid, '_vms_tax_bypass_until', true)
                                        ),
                                        'warning'
                                    );
                                }
                            } else {
                                if (function_exists('bvmgr_add_admin_notice')) {
                                    $staff_name = get_the_title($sid);
                                    bvmgr_add_admin_notice(
                                        sprintf(
                                            /* translators: 1: vendor or staff name, 2: comma-separated missing tax profile items. */
                                            /* translators: 1: staff member name, 2: comma-separated missing tax profile items. */
                                            __('Staff "%1$s" is missing required Tax Profile items: %2$s. Ready/Publish allowed with warning, but payouts and tax reporting may be blocked.', 'backstage-venue-manager'),
                                            $staff_name ? $staff_name : '#' . $sid,
                                            implode(', ', $missing)
                                        ),
                                        'warning'
                                    );
                                }
                            }
                        }
}
                }
            }

            // Venue closure guard for Ready/Publish:
            // - explicit Holiday CLOSED blocks
            // - Season Dates open-window rules are enforced when configured
            $venue_id = (int) get_post_meta($post_id, '_vms_venue_id', true);
            $venue_closed_context = array();
            if ($venue_id > 0 && $event_date && function_exists('bvmgr_event_plan_is_venue_closed_for_event_date') && bvmgr_event_plan_is_venue_closed_for_event_date($venue_id, $event_date, $venue_closed_context)) {
                $reason = sanitize_key((string) ($venue_closed_context['reason'] ?? ''));
                if ($reason === 'holiday_closed') {
                    $holiday_name = trim((string) ($venue_closed_context['holiday_name'] ?? ''));
                    if ($holiday_name === '') {
                        $holiday_name = __('Holiday', 'backstage-venue-manager');
                    }
                    /* translators: 1: holiday name, 2: event date. */
                    /* translators: 1: holiday name, 2: event date. */
                    $errors[] = sprintf(__('Venue is CLOSED for "%1$s" on %2$s.', 'backstage-venue-manager'), $holiday_name, $event_date);
                } else {
                    /* translators: %s: date or time value. */
                    $errors[] = sprintf(__('Venue is CLOSED by Season Dates rules on %s.', 'backstage-venue-manager'), $event_date);
                }
            }

            return $errors;
        }

        /**
         * Auto-generate Event Plan title if empty/Auto Draft
         */
        function bvmgr_maybe_autoset_event_plan_title(int $post_id): void
        {
            $post = get_post($post_id);
            if (!$post) return;

            $current_title = trim((string)$post->post_title);
            if ($current_title && strcasecmp($current_title, 'Auto Draft') !== 0) return;

            $band_id = (int) get_post_meta($post_id, '_vms_band_vendor_id', true);
            $band_name = $band_id ? (string) get_the_title($band_id) : '';
            $new_title = function_exists('bvmgr_event_plan_build_auto_title')
                ? bvmgr_event_plan_build_auto_title((string) $band_name)
                : trim(wp_strip_all_tags((string) $band_name));
            if ($new_title === '') return;

            $autoset_postarr = array(
                'ID'         => $post_id,
                'post_title' => $new_title,
                'post_name'  => sanitize_title($new_title),
            );
            $autoset_changed_fields = function_exists('bvmgr_event_plan_perf_post_update_changed_fields')
                ? bvmgr_event_plan_perf_post_update_changed_fields($post_id, $autoset_postarr)
                : array('post_title', 'post_name');
            if (empty($autoset_changed_fields)) {
                if (function_exists('bvmgr_event_plan_perf_log')) {
                    bvmgr_event_plan_perf_log('event_plan_autoset_title_action', $post_id, array(
                        'phase' => 'skip',
                        'skip_reason' => 'no_op',
                        'current_post_title' => $current_title,
                        'computed_post_title' => $new_title,
                        'computed_post_name' => sanitize_title($new_title),
                        'changed_fields' => array(),
                    ));
                }
                return;
            }

            bvmgr_event_plan_perf_wp_update_post($autoset_postarr, 'event_plan_autoset_title_action', $post_id);

            /* translators: %s: human-readable value used in this message. */
            bvmgr_add_admin_notice(sprintf(__('Event title set to "%s".', 'backstage-venue-manager'), $new_title), 'success');
        }

        /**
         * Admin notices — transient-based
         */
        function bvmgr_add_admin_notice(string $message, string $type = 'success'): void
        {
            $user_id = get_current_user_id();
            if (!$user_id) return;

            $key = 'vms_event_plan_notices_' . $user_id;
            $notices = get_transient($key);
            if (!is_array($notices)) $notices = array();

            $notices[] = array('type' => $type, 'message' => $message);
            set_transient($key, $notices, 60);
        }

        if (!function_exists('bvmgr_event_plan_editor_register_detached_form')) {
            /**
             * @param array<string,mixed> $hidden_fields
             */
            function bvmgr_event_plan_editor_register_detached_form(string $form_id, string $method, string $action, array $hidden_fields = array()): void
            {
                global $bvmgr_event_plan_editor_detached_forms;
                if (!is_array($bvmgr_event_plan_editor_detached_forms)) {
                    $bvmgr_event_plan_editor_detached_forms = array();
                }

                $bvmgr_event_plan_editor_detached_forms[$form_id] = array(
                    'method' => (strtolower($method) === 'get') ? 'get' : 'post',
                    'action' => esc_url_raw($action),
                    'hidden_fields' => $hidden_fields,
                );
            }
        }

        if (!function_exists('bvmgr_event_plan_editor_render_detached_forms')) {
            function bvmgr_event_plan_editor_render_detached_forms(): void
            {
                global $bvmgr_event_plan_editor_detached_forms;
                if (!is_array($bvmgr_event_plan_editor_detached_forms) || empty($bvmgr_event_plan_editor_detached_forms)) {
                    return;
                }

                foreach ($bvmgr_event_plan_editor_detached_forms as $form_id => $form) {
                    $form_id = sanitize_html_class((string) $form_id);
                    $method = (($form['method'] ?? 'post') === 'get') ? 'get' : 'post';
                    $action = esc_url((string) ($form['action'] ?? ''));
                    $hidden_fields = is_array($form['hidden_fields'] ?? null) ? (array) $form['hidden_fields'] : array();

                    echo '<form id="' . esc_attr($form_id) . '" method="' . esc_attr($method) . '" action="' . $action . '" class="vms-event-plan-detached-form" style="display:none;">';
                    foreach ($hidden_fields as $name => $value) {
                        if ($value === null) {
                            continue;
                        }
                        echo '<input type="hidden" name="' . esc_attr((string) $name) . '" value="' . esc_attr((string) $value) . '" />';
                    }
                    echo '</form>';
                }
            }
        }
        add_action('admin_footer', 'bvmgr_event_plan_editor_render_detached_forms', 44);

        if (!function_exists('bvmgr_event_plan_edit_screen_url')) {
            function bvmgr_event_plan_edit_screen_url(int $post_id = 0): string
            {
                $post_id = absint($post_id);
                if ($post_id > 0) {
                    return admin_url('post.php?post=' . $post_id . '&action=edit');
                }

                return admin_url('edit.php?post_type=vms_event_plan');
            }
        }

        if (!function_exists('bvmgr_event_plan_handle_resync_calendar_request')) {
            /**
             * @param array<string,mixed> $request
             * @return array{ok:bool,post_id:int,tec_event_id:int,redirect_url:string,notice_type:string,notice_message:string,source:string}
             */
            function bvmgr_event_plan_handle_resync_calendar_request(array $request, bool $redirect = true): array
            {
                $post_id = isset($request['post_id']) ? absint($request['post_id']) : 0;
                $default_redirect = bvmgr_event_plan_edit_screen_url($post_id);
                $redirect_raw = isset($request['redirect_to']) ? (string) $request['redirect_to'] : '';
                $redirect_url = $redirect_raw !== ''
                    ? wp_validate_redirect($redirect_raw, $default_redirect)
                    : $default_redirect;
                $source = isset($request['source']) ? sanitize_key((string) $request['source']) : '';
                $result = array(
                    'ok' => false,
                    'post_id' => $post_id,
                    'tec_event_id' => 0,
                    'redirect_url' => $redirect_url,
                    'notice_type' => 'error',
                    'notice_message' => '',
                    'source' => $source,
                );

                $post = ($post_id > 0) ? get_post($post_id) : null;
                if (!($post instanceof WP_Post) || $post->post_type !== 'vms_event_plan') {
                    $result['notice_message'] = __('Invalid Event Plan for calendar re-sync.', 'backstage-venue-manager');
                } elseif (!current_user_can('edit_post', $post_id)) {
                    $result['notice_message'] = __('You do not have permission to re-sync this Event Plan.', 'backstage-venue-manager');
                } else {
                    $result['redirect_url'] = $redirect_url !== ''
                        ? $redirect_url
                        : bvmgr_event_plan_edit_screen_url($post_id);
	                    $nonce = (isset($request['_bvmgr_resync_calendar_nonce']) && !is_array($request['_bvmgr_resync_calendar_nonce']))
	                        ? sanitize_text_field((string) $request['_bvmgr_resync_calendar_nonce'])
	                        : '';
                    if ($nonce === '' || !wp_verify_nonce($nonce, bvmgr_nonce_action_for_value($nonce, 'bvmgr_resync_calendar'))) {
                        $result['notice_message'] = __('Calendar re-sync request could not be verified. Please reload the Event Plan and try again.', 'backstage-venue-manager');
                    } else {
                        $tec_key_id = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'tec_event_id') ?: '_vms_tec_event_id') : '_vms_tec_event_id';
                        $existing_tec_id = (int) get_post_meta($post_id, $tec_key_id, true);
                        $result['tec_event_id'] = $existing_tec_id;

                        if ($existing_tec_id <= 0) {
                            $result['notice_message'] = __('No linked calendar event found. Use “Publish Now” first.', 'backstage-venue-manager');
                        } else {
                            $errors = bvmgr_validate_event_plan($post_id);
                            if (!empty($errors)) {
                                $result['notice_message'] = __('Cannot re-sync:', 'backstage-venue-manager') . ' ' . implode(' ', $errors);
                            } else {
                                $ok = bvmgr_resync_event_to_calendar($post_id, $post, $existing_tec_id);
                                if ($ok) {
                                    $result['ok'] = true;
                                    $result['notice_type'] = 'success';
                                    $result['notice_message'] = __('Calendar event re-synced successfully.', 'backstage-venue-manager');
                                } else {
                                    $result['notice_message'] = __('Failed to re-sync calendar event. Please check settings.', 'backstage-venue-manager');
                                }
                            }
                        }
                    }
                }

                if ($result['notice_message'] !== '') {
                    bvmgr_add_admin_notice($result['notice_message'], $result['notice_type']);
                }

                if ($redirect) {
                    wp_safe_redirect($result['redirect_url']);
                    exit;
                }

                return $result;
            }
        }

        if (!function_exists('bvmgr_handle_admin_post_resync_event_to_calendar')) {
            function bvmgr_handle_admin_post_resync_event_to_calendar(): void
            {
                bvmgr_event_plan_handle_resync_calendar_request(bvmgr_event_plan_current_request_data(), true);
            }
        }
        add_action('admin_post_vms_resync_event_to_calendar', 'bvmgr_handle_admin_post_resync_event_to_calendar');

        if (!function_exists('bvmgr_resolve_live_refund_event_plan_id')) {
            function bvmgr_resolve_live_refund_event_plan_id(int $candidate_id): int
            {
                $candidate_id = absint($candidate_id);
                if ($candidate_id <= 0) {
                    return 0;
                }

                $post = get_post($candidate_id);
                if (!($post instanceof WP_Post)) {
                    return 0;
                }

                $post_type = sanitize_key((string) $post->post_type);
                if ($post_type === 'vms_event_plan') {
                    return (int) $post->ID;
                }

                if ($post_type === 'revision' && !empty($post->post_parent)) {
                    return bvmgr_resolve_live_refund_event_plan_id((int) $post->post_parent);
                }

                if ($post_type === 'tribe_events' && function_exists('bvmgr_get_event_plan_for_tec_event')) {
                    $linked_plan_id = (int) bvmgr_get_event_plan_for_tec_event((int) $post->ID);
                    if ($linked_plan_id > 0) {
                        return $linked_plan_id;
                    }
                }

                foreach (array('_vms_event_plan_id', 'vms_event_plan_id') as $meta_key) {
                    $linked_plan_id = absint(get_post_meta((int) $post->ID, $meta_key, true));
                    if ($linked_plan_id > 0 && get_post_type($linked_plan_id) === 'vms_event_plan') {
                        return $linked_plan_id;
                    }
                }

                return 0;
            }
        }

        if (!function_exists('bvmgr_run_live_refunds_now_request')) {
            function bvmgr_run_live_refunds_now_request(array $request): void
            {
                $requested_event_plan_id = isset($request['event_plan_id']) ? absint($request['event_plan_id']) : 0;
                $requested_post_id = isset($request['post_id']) ? absint($request['post_id']) : 0;
                $requested_source_post_id = isset($request['source_post_id']) ? absint($request['source_post_id']) : 0;
                $request_candidate_id = $requested_event_plan_id > 0
                    ? $requested_event_plan_id
                    : ($requested_source_post_id > 0 ? $requested_source_post_id : $requested_post_id);
                $return_url_raw = isset($request['return_url']) ? (string) $request['return_url'] : '';

                if ($request_candidate_id <= 0) {
                    $candidate_urls = array();
                    if ($return_url_raw !== '') {
                        $candidate_urls[] = $return_url_raw;
                    }
                    $referer = wp_get_referer();
                    if (is_string($referer) && $referer !== '') {
                        $candidate_urls[] = $referer;
                    }
                    foreach ($candidate_urls as $candidate_url) {
                        $parts = wp_parse_url($candidate_url);
                        if (!is_array($parts) || empty($parts['query'])) {
                            continue;
                        }
                        $parsed = array();
                        parse_str((string) $parts['query'], $parsed);
                        $parsed_post_id = isset($parsed['post']) ? absint($parsed['post']) : 0;
                        if ($parsed_post_id > 0) {
                            $request_candidate_id = $parsed_post_id;
                            break;
                        }
                    }
                }

                $post_id = function_exists('bvmgr_resolve_live_refund_event_plan_id')
                    ? bvmgr_resolve_live_refund_event_plan_id($request_candidate_id)
                    : $request_candidate_id;
                $redirect_post_id = $post_id > 0 ? $post_id : $request_candidate_id;
                $redirect_url = function_exists('bvmgr_event_plan_admin_edit_url')
                    ? bvmgr_event_plan_admin_edit_url($redirect_post_id, array(), 'vms-cancel-job-panel', 'raw')
                    : add_query_arg(array('post' => $redirect_post_id, 'action' => 'edit'), admin_url('post.php'));
                if ($return_url_raw !== '') {
                    $fallback_url = function_exists('bvmgr_event_plan_admin_edit_url')
                        ? bvmgr_event_plan_admin_edit_url($redirect_post_id, array(), 'vms-cancel-job-panel', 'raw')
                        : admin_url('edit.php?post_type=vms_event_plan');
                    $redirect_url = wp_validate_redirect($return_url_raw, $fallback_url);
                }

                if ($post_id <= 0 || get_post_type($post_id) !== 'vms_event_plan') {
                    if (function_exists('bvmgr_add_admin_notice')) {
                        $received_type = $request_candidate_id > 0 ? sanitize_key((string) get_post_type($request_candidate_id)) : '';
                        $debug_context = sprintf(
                            /* translators: 1: number 1 used in this message, 2: value 2 used in this message. */
                            __('Received ID %1$d (%2$s).', 'backstage-venue-manager'),
                            (int) $request_candidate_id,
                            $received_type !== '' ? $received_type : __('unknown type', 'backstage-venue-manager')
                        );
                        bvmgr_add_admin_notice(__('Invalid Event Plan for live refund action.', 'backstage-venue-manager') . ' ' . $debug_context, 'error');
                    }
                    wp_safe_redirect($redirect_url);
                    exit;
                }

                if (!current_user_can('edit_post', $post_id)) {
                    if (function_exists('bvmgr_add_admin_notice')) {
                        bvmgr_add_admin_notice(__('You do not have permission to run live refunds for this Event Plan.', 'backstage-venue-manager'), 'error');
                    }
                    wp_safe_redirect($redirect_url);
                    exit;
                }

	                $nonce = (isset($request['_wpnonce']) && !is_array($request['_wpnonce']))
	                    ? sanitize_text_field((string) $request['_wpnonce'])
	                    : '';
                $nonce_ok = false;
                foreach (array_unique(array_filter(array(absint($post_id), absint($request_candidate_id), absint($requested_source_post_id), absint($requested_post_id)))) as $nonce_post_id) {
                    if (wp_verify_nonce($nonce, bvmgr_nonce_action_for_value($nonce, 'bvmgr_run_live_refunds_now_' . $nonce_post_id))) {
                        $nonce_ok = true;
                        break;
                    }
                }
                if (!$nonce_ok) {
                    if (function_exists('bvmgr_add_admin_notice')) {
                        bvmgr_add_admin_notice(__('Live refund request could not be verified. Please reload the Event Plan and try again.', 'backstage-venue-manager'), 'error');
                    }
                    wp_safe_redirect($redirect_url);
                    exit;
                }

                if (!function_exists('bvmgr_cancellation_request_live_refund_run')) {
                    if (function_exists('bvmgr_add_admin_notice')) {
                        bvmgr_add_admin_notice(__('Live refund helper is unavailable.', 'backstage-venue-manager'), 'error');
                    }
                    wp_safe_redirect($redirect_url);
                    exit;
                }

                $live_refund = (array) bvmgr_cancellation_request_live_refund_run($post_id, array(
                    'requested_by_user_id' => get_current_user_id(),
                ));

                if (empty($live_refund['ok'])) {
                    $err = sanitize_text_field((string) ($live_refund['error'] ?? 'manual_live_refund_failed'));
                    if (function_exists('bvmgr_add_admin_notice')) {
                        bvmgr_add_admin_notice(__('Unable to run live refunds:', 'backstage-venue-manager') . ' ' . $err, 'error');
                    }
                    wp_safe_redirect($redirect_url);
                    exit;
                }

                $live_state = sanitize_key((string) ($live_refund['state'] ?? 'queued'));
                $live_summary = isset($live_refund['summary']) && is_array($live_refund['summary']) ? $live_refund['summary'] : array();
                $target_policy = sanitize_key((string) ($live_refund['target_policy'] ?? 'stop_sales_auto_refund'));
                $policy_labels = function_exists('bvmgr_cancellation_policy_options') ? (array) bvmgr_cancellation_policy_options() : array();
                $target_policy_label = isset($policy_labels[$target_policy]) ? (string) $policy_labels[$target_policy] : $target_policy;
                $steps_live = isset($live_summary['steps']) && is_array($live_summary['steps']) ? $live_summary['steps'] : array();
                $refund_execution_data = array();
                foreach ($steps_live as $step_live) {
                    if (!is_array($step_live)) {
                        continue;
                    }
                    if (sanitize_key((string) ($step_live['key'] ?? '')) !== 'refund_execution') {
                        continue;
                    }
                    $refund_execution_data = isset($step_live['data']) && is_array($step_live['data']) ? $step_live['data'] : array();
                    break;
                }
                $created_count = isset($refund_execution_data['refunds_created']) && is_array($refund_execution_data['refunds_created']) ? count((array) $refund_execution_data['refunds_created']) : 0;
                $queued_count = isset($refund_execution_data['queued_orders']) && is_array($refund_execution_data['queued_orders']) ? count((array) $refund_execution_data['queued_orders']) : 0;
                $failed_count = isset($refund_execution_data['failed_orders']) && is_array($refund_execution_data['failed_orders']) ? count((array) $refund_execution_data['failed_orders']) : 0;

                if (function_exists('bvmgr_add_admin_notice')) {
                    bvmgr_add_admin_notice(
                        sprintf(
                            /* translators: 1: value 1 used in this message, 2: number 2 used in this message, 3: number 3 used in this message, 4: number 4 used in this message. */
                            __('Live refund batch submitted. Policy: %1$s. Refunded: %2$d. Queued for manual review: %3$d. Failed: %4$d.', 'backstage-venue-manager'),
                            sanitize_text_field($target_policy_label),
                            (int) $created_count,
                            (int) $queued_count,
                            (int) $failed_count
                        ),
                        ($failed_count > 0 ? 'warning' : 'success')
                    );
                    if (!empty($live_refund['policy_changed'])) {
                        bvmgr_add_admin_notice(__('Cancellation policy was upgraded to a live auto-refund policy for this already-cancelled plan before running refunds.', 'backstage-venue-manager'), 'info');
                    }
                    if ($queued_count > 0 || $failed_count > 0 || $live_state !== 'completed') {
                        bvmgr_add_admin_notice(__('Review the Cancellation Job panel for any orders that still require manual follow-up.', 'backstage-venue-manager'), 'warning');
                    }
                }

                wp_safe_redirect($redirect_url);
                exit;
            }
        }

        add_action('admin_init', 'bvmgr_handle_live_refunds_now_screen_request');
        function bvmgr_handle_live_refunds_now_screen_request(): void
        {
            if (!is_admin()) {
                return;
            }
            $request = bvmgr_event_plan_current_get_request();
            $flag = isset($request['vms_live_refund_now']) ? sanitize_key((string) $request['vms_live_refund_now']) : '';
            if ($flag !== '1') {
                return;
            }
            bvmgr_run_live_refunds_now_request($request);
        }

        add_action('admin_post_vms_run_live_refunds_now', 'bvmgr_handle_admin_post_run_live_refunds_now');
        function bvmgr_handle_admin_post_run_live_refunds_now(): void
        {
            bvmgr_run_live_refunds_now_request(bvmgr_event_plan_current_request_data());
        }

        add_action('admin_notices', 'bvmgr_render_event_planadmin_notices');
        function bvmgr_render_event_planadmin_notices(): void
        {
            $user_id = get_current_user_id();
            if (!$user_id) return;

            $key = 'vms_event_plan_notices_' . $user_id;
            $notices = get_transient($key);
            if (!is_array($notices) || empty($notices)) return;

            delete_transient($key);

            foreach ($notices as $notice) {
                $type = (string)($notice['type'] ?? 'success');
                $class = 'notice notice-success vms-notice vms-notice--success';
                if ($type === 'error' || $type === 'critical') {
                    $class = 'notice notice-error vms-notice vms-notice--critical';
                } elseif ($type === 'warning') {
                    $class = 'notice notice-warning vms-notice vms-notice--warning';
                } elseif ($type === 'info') {
                    $class = 'notice notice-info vms-notice vms-notice--info';
                }
                printf('<div class="%s"><p>%s</p></div>', esc_attr($class), esc_html((string)($notice['message'] ?? '')));
            }
        }


        /**
         * Integrity helpers (Event Plans)
         *
         * Used to flag situations where a published/ready plan loses its vendor (e.g., vendor deleted).
         */
        function bvmgr_event_plan_vendor_exists(int $vendor_id): bool
        {
            if ($vendor_id <= 0) return false;

            $p = get_post($vendor_id);
            if (!$p) return false;
            if ($p->post_type !== 'vms_vendor') return false;
            if ($p->post_status === 'trash') return false;

            return true;
        }

		if (!function_exists('bvmgr_event_plan_normalize_secondary_vendor_type_slug')) {
			function bvmgr_event_plan_normalize_secondary_vendor_type_slug(string $type_slug): string
			{
				return function_exists('bvmgr_vendor_type_normalize_slug')
					? bvmgr_vendor_type_normalize_slug($type_slug)
					: sanitize_title($type_slug);
			}
		}

		if (!function_exists('bvmgr_event_plan_get_current_primary_lineup_vendor_id')) {
			function bvmgr_event_plan_get_current_primary_lineup_vendor_id(int $post_id, int $fallback_vendor_id = 0): int
			{
				$post_id = absint($post_id);
				$fallback_vendor_id = absint($fallback_vendor_id);
				if ($post_id <= 0) {
					return $fallback_vendor_id;
				}

				$entries = function_exists('bvmgr_get_event_plan_lineup_entries')
					? (array) bvmgr_get_event_plan_lineup_entries($post_id)
					: array();
				if (empty($entries)) {
					$lineup_key = function_exists('bvmgr_lineup_schedule_meta_key')
						? bvmgr_lineup_schedule_meta_key('lineup_entries_v1', '_vms_lineup_entries_v1')
						: '_vms_lineup_entries_v1';
					$entries = get_post_meta($post_id, $lineup_key, true);
					$entries = is_array($entries) ? array_values($entries) : array();
				}

				foreach ($entries as $entry) {
					if (!is_array($entry)) {
						continue;
					}
					if (sanitize_key((string) ($entry['role'] ?? '')) !== 'primary') {
						continue;
					}

					$vendor_id = absint($entry['vendor_id'] ?? 0);
					return $vendor_id > 0 ? $vendor_id : $fallback_vendor_id;
				}

				return $fallback_vendor_id;
			}
		}

		if (!function_exists('bvmgr_event_plan_resolve_primary_vendor_submission')) {
			function bvmgr_event_plan_resolve_primary_vendor_submission(int $post_id, array $request): array
			{
				$post_id = absint($post_id);
				$current_vendor_id = (int) get_post_meta($post_id, '_vms_band_vendor_id', true);
				$current_lineup_primary_vendor_id = bvmgr_event_plan_get_current_primary_lineup_vendor_id($post_id, $current_vendor_id);
				$field_present = array_key_exists('vms_band_vendor_id', $request);
				$posted_vendor_id = $field_present ? absint($request['vms_band_vendor_id']) : 0;
				$clear_requested = !empty($request['vms_clear_primary_vendor']) || !empty($request['vms_clear_lineup_primary_vendor']);

				$effective_vendor_id = $current_vendor_id;
				$lineup_primary_vendor_id = $current_lineup_primary_vendor_id > 0 ? $current_lineup_primary_vendor_id : $current_vendor_id;
				$should_write = false;

				if ($field_present) {
					if ($posted_vendor_id > 0) {
						$effective_vendor_id = $posted_vendor_id;
						$lineup_primary_vendor_id = $posted_vendor_id;
						$should_write = true;
					} elseif ($clear_requested) {
						$effective_vendor_id = 0;
						$lineup_primary_vendor_id = 0;
						$should_write = true;
					}
				}

				return array(
					'field_present' => $field_present,
					'posted_vendor_id' => $posted_vendor_id,
					'clear_requested' => $clear_requested,
					'current_vendor_id' => $current_vendor_id,
					'current_lineup_primary_vendor_id' => $current_lineup_primary_vendor_id,
					'effective_vendor_id' => $effective_vendor_id,
					'lineup_primary_vendor_id' => $lineup_primary_vendor_id,
					'should_write' => $should_write,
				);
			}
		}

		if (!function_exists('bvmgr_event_plan_normalize_secondary_vendor_ids')) {
			function bvmgr_event_plan_normalize_secondary_vendor_ids(int $post_id, string $type_slug, array $secondary_ids, int $primary_vendor_id = 0): array
			{
                $post_id = absint($post_id);
                $type_slug = bvmgr_event_plan_normalize_secondary_vendor_type_slug($type_slug);
                $primary_vendor_id = absint($primary_vendor_id);

                $secondary_ids = array_values(array_unique(array_filter(array_map('absint', $secondary_ids), static function ($vendor_id) {
                    return $vendor_id > 0;
                })));

                if ($primary_vendor_id > 0) {
                    $secondary_ids = array_values(array_filter($secondary_ids, static function ($vendor_id) use ($primary_vendor_id) {
                        return (int) $vendor_id !== (int) $primary_vendor_id;
                    }));
                }

                $valid_secondary = array();
                foreach ($secondary_ids as $vendor_id) {
                    $vendor_id = (int) $vendor_id;
                    if ($vendor_id <= 0) {
                        continue;
                    }

                    if (function_exists('bvmgr_event_plan_vendor_exists')) {
                        if (!bvmgr_event_plan_vendor_exists($vendor_id)) {
                            continue;
                        }
                    } else {
                        $vendor_post = get_post($vendor_id);
                        if (!$vendor_post || $vendor_post->post_type !== 'vms_vendor' || $vendor_post->post_status === 'trash') {
                            continue;
                        }
                    }

                    if ($type_slug !== '') {
                        $matches_type = function_exists('bvmgr_vendor_has_type')
                            ? bvmgr_vendor_has_type($vendor_id, $type_slug)
                            : (function_exists('has_term') ? has_term($type_slug, 'vms_vendor_type', $vendor_id) : true);
                        if (!$matches_type) {
                            continue;
                        }
                    }

                    $valid_secondary[] = $vendor_id;
                }

				return array_values(array_unique(array_filter(array_map('absint', $valid_secondary), static function ($vendor_id) {
					return $vendor_id > 0;
				})));
			}
		}

		if (!function_exists('bvmgr_event_plan_resolve_secondary_vendor_submission')) {
			function bvmgr_event_plan_resolve_secondary_vendor_submission(int $post_id, array $request): array
			{
				$post_id = absint($post_id);
				$current_state = function_exists('bvmgr_event_plan_get_secondary_vendor_state')
					? bvmgr_event_plan_get_secondary_vendor_state($post_id)
					: array(
						'primary_vendor_id' => (int) get_post_meta($post_id, '_vms_band_vendor_id', true),
						'secondary_vendor_type' => sanitize_key((string) get_post_meta($post_id, '_vms_secondary_vendor_type', true)),
						'secondary_vendor_ids' => array_values(array_unique(array_filter(array_map('absint', (array) get_post_meta($post_id, '_vms_secondary_vendor_ids', true))))),
						'linked_tec_event_id' => 0,
					);
				$current_type = bvmgr_event_plan_normalize_secondary_vendor_type_slug((string) ($current_state['secondary_vendor_type'] ?? ''));
				$current_ids = array_values(array_unique(array_filter(array_map('absint', (array) ($current_state['secondary_vendor_ids'] ?? array())), static function ($vendor_id) {
					return $vendor_id > 0;
				})));

				$type_field_present = array_key_exists('vms_secondary_vendor_type', $request);
				$ids_field_present = array_key_exists('vms_secondary_vendor_ids', $request);
				$clear_requested = !empty($request['vms_clear_secondary_vendors']);
				$submission_present = $type_field_present || $ids_field_present;

				$type_slug = $current_type;
				if ($clear_requested) {
					$type_slug = '';
				} elseif ($type_field_present) {
					$posted_type = bvmgr_event_plan_normalize_secondary_vendor_type_slug((string) wp_unslash((string) $request['vms_secondary_vendor_type']));
					if ($posted_type !== '') {
						$type_slug = $posted_type;
					}
				}

				$secondary_ids = $current_ids;
				if ($clear_requested) {
					$secondary_ids = array();
				} elseif ($ids_field_present) {
					$raw_secondary = is_array($request['vms_secondary_vendor_ids']) ? $request['vms_secondary_vendor_ids'] : array();
					$posted_secondary_ids = array_values(array_unique(array_filter(array_map('absint', $raw_secondary), static function ($vendor_id) {
						return $vendor_id > 0;
					})));
					if (!empty($posted_secondary_ids)) {
						$secondary_ids = $posted_secondary_ids;
					} elseif ($type_slug !== '' && $type_slug !== $current_type) {
						$secondary_ids = array();
					}
				} elseif ($type_field_present && $type_slug !== '' && $type_slug !== $current_type) {
					$secondary_ids = array();
				}

				return array(
					'current_state' => $current_state,
					'current_type' => $current_type,
					'current_ids' => $current_ids,
					'type_field_present' => $type_field_present,
					'ids_field_present' => $ids_field_present,
					'submission_present' => $submission_present,
					'clear_requested' => $clear_requested,
					'type_slug' => $type_slug,
					'secondary_ids' => $secondary_ids,
				);
			}
		}

		if (!function_exists('bvmgr_event_plan_get_secondary_vendor_state')) {
			function bvmgr_event_plan_get_secondary_vendor_state(int $post_id): array
			{
                $post_id = absint($post_id);
                $k_band = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'band_vendor_id') ?: '_vms_band_vendor_id') : '_vms_band_vendor_id';
                $k_secondary_ids = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'secondary_vendor_ids') ?: '_vms_secondary_vendor_ids') : '_vms_secondary_vendor_ids';
                $k_secondary_type = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'secondary_vendor_type') ?: '_vms_secondary_vendor_type') : '_vms_secondary_vendor_type';
                $k_tec_event_id = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'tec_event_id') ?: '_vms_tec_event_id') : '_vms_tec_event_id';

                $primary_vendor_id = (int) get_post_meta($post_id, $k_band, true);
                $secondary_type = bvmgr_event_plan_normalize_secondary_vendor_type_slug((string) get_post_meta($post_id, $k_secondary_type, true));
                $secondary_ids = get_post_meta($post_id, $k_secondary_ids, true);
                if (!is_array($secondary_ids)) {
                    $secondary_ids = array();
                }

                return array(
                    'primary_vendor_id' => $primary_vendor_id,
                    'secondary_vendor_type' => $secondary_type,
                    'secondary_vendor_ids' => bvmgr_event_plan_normalize_secondary_vendor_ids($post_id, $secondary_type, $secondary_ids, $primary_vendor_id),
                    'linked_tec_event_id' => (int) get_post_meta($post_id, $k_tec_event_id, true),
                );
            }
        }

        if (!function_exists('bvmgr_event_plan_secondary_vendor_state_diff_fields')) {
            function bvmgr_event_plan_secondary_vendor_state_diff_fields(array $before, array $after, array $fields = array()): array
            {
                if (empty($fields)) {
                    $fields = array('primary_vendor_id', 'secondary_vendor_type', 'secondary_vendor_ids', 'linked_tec_event_id');
                }

                $dirty_fields = array();
                foreach ($fields as $field) {
                    $field = sanitize_key((string) $field);
                    if ($field === '') {
                        continue;
                    }

                    $before_value = $before[$field] ?? null;
                    $after_value = $after[$field] ?? null;

                    if (is_array($before_value) || is_array($after_value)) {
                        $before_encoded = wp_json_encode(array_values((array) $before_value));
                        $after_encoded = wp_json_encode(array_values((array) $after_value));
                        if ($before_encoded !== $after_encoded) {
                            $dirty_fields[] = $field;
                        }
                        continue;
                    }

                    if ((string) $before_value !== (string) $after_value) {
                        $dirty_fields[] = $field;
                    }
                }

                return array_values(array_unique(array_filter($dirty_fields)));
            }
        }

        if (!function_exists('bvmgr_event_plan_secondary_vendor_rebuild_repair_reasons')) {
            function bvmgr_event_plan_secondary_vendor_rebuild_repair_reasons(int $post_id, array $current_state = array()): array
            {
                $post_id = absint($post_id);
                if ($post_id <= 0) {
                    return array();
                }

                if (empty($current_state)) {
                    $current_state = bvmgr_event_plan_get_secondary_vendor_state($post_id);
                }

                $k_secondary_ids = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'secondary_vendor_ids') ?: '_vms_secondary_vendor_ids') : '_vms_secondary_vendor_ids';
                $k_secondary_idx = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'secondary_vendor_id') ?: '_vms_secondary_vendor_id') : '_vms_secondary_vendor_id';
                $k_snapshot = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'vendor_category_snapshot') ?: '_vms_vendor_category_snapshot') : '_vms_vendor_category_snapshot';
                $k_issue = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'integrity_issue') ?: '_vms_integrity_issue') : '_vms_integrity_issue';

                $expected_secondary_ids = array_values(array_unique(array_filter(array_map('absint', (array) ($current_state['secondary_vendor_ids'] ?? array())), static function ($vendor_id) {
                    return $vendor_id > 0;
                })));

                $stored_secondary_ids = get_post_meta($post_id, $k_secondary_ids, true);
                if (!is_array($stored_secondary_ids)) {
                    $stored_secondary_ids = array();
                }
                $stored_secondary_ids = array_values(array_unique(array_filter(array_map('absint', $stored_secondary_ids), static function ($vendor_id) {
                    return $vendor_id > 0;
                })));

                $index_secondary_ids = array_values(array_unique(array_filter(array_map('absint', (array) get_post_meta($post_id, $k_secondary_idx, false)), static function ($vendor_id) {
                    return $vendor_id > 0;
                })));

                $repair_reasons = array();
                if (wp_json_encode($stored_secondary_ids) !== wp_json_encode($expected_secondary_ids)) {
                    $repair_reasons[] = 'secondary_vendor_canonical_mismatch';
                }
                if (wp_json_encode($index_secondary_ids) !== wp_json_encode($expected_secondary_ids)) {
                    $repair_reasons[] = 'secondary_vendor_index_mismatch';
                }

                $has_any_vendor = ((int) ($current_state['primary_vendor_id'] ?? 0) > 0) || !empty($expected_secondary_ids);
                if ($has_any_vendor && !metadata_exists('post', $post_id, $k_snapshot)) {
                    $repair_reasons[] = 'vendor_category_snapshot_missing';
                }

                $issue_now = sanitize_key((string) get_post_meta($post_id, $k_issue, true));
                if ($issue_now === 'missing_secondary_vendor') {
                    $repair_reasons[] = 'integrity_issue_missing_secondary_vendor';
                }

                return array_values(array_unique(array_filter($repair_reasons)));
            }
        }

        if (!function_exists('bvmgr_event_plan_set_secondary_vendors')) {
            /**
             * Canonical secondary-vendor assignment writer.
             *
             * Mirrors the Event Plan editor save behavior so external workflows can
             * assign vendors without inventing a second storage path.
             *
             * @return array<string,mixed>|WP_Error
             */
            function bvmgr_event_plan_set_secondary_vendors(int $post_id, string $type_slug, array $secondary_ids)
            {
                $post_id = absint($post_id);
                if ($post_id <= 0 || get_post_type($post_id) !== 'vms_event_plan') {
                    return new WP_Error('vms_event_plan_invalid', __('Event Plan could not be found for secondary-vendor assignment.', 'backstage-venue-manager'));
                }

                $k_secondary_ids     = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'secondary_vendor_ids') : '_vms_secondary_vendor_ids';
                $k_secondary_idx     = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'secondary_vendor_id') : '_vms_secondary_vendor_id';
                $k_secondary_type    = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'secondary_vendor_type') : '_vms_secondary_vendor_type';
                $k_secondary_unq     = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'secondary_vendor_unqualified') : '_vms_secondary_vendor_unqualified';
                $k_secondary_unq_ids = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'secondary_vendor_unqualified_ids') : '_vms_secondary_vendor_unqualified_ids';

                $type_slug = function_exists('bvmgr_vendor_type_normalize_slug')
                    ? bvmgr_vendor_type_normalize_slug($type_slug)
                    : sanitize_title($type_slug);
                if ($type_slug !== '') {
                    update_post_meta($post_id, $k_secondary_type, $type_slug);
                } else {
                    delete_post_meta($post_id, $k_secondary_type);
                }

                $clean_secondary = array_values(array_unique(array_filter(array_map('absint', $secondary_ids), static function ($vendor_id) {
                    return $vendor_id > 0;
                })));

                $current_band_id = (int) get_post_meta($post_id, '_vms_band_vendor_id', true);
                if ($current_band_id > 0) {
                    $clean_secondary = array_values(array_filter($clean_secondary, static function ($vendor_id) use ($current_band_id) {
                        return (int) $vendor_id !== (int) $current_band_id;
                    }));
                }

                $valid_secondary = array();
                foreach ($clean_secondary as $vendor_id) {
                    $vendor_id = (int) $vendor_id;
                    if ($vendor_id <= 0) {
                        continue;
                    }

                    if (function_exists('bvmgr_event_plan_vendor_exists')) {
                        if (!bvmgr_event_plan_vendor_exists($vendor_id)) {
                            continue;
                        }
                    } else {
                        $vendor_post = get_post($vendor_id);
                        if (!$vendor_post || $vendor_post->post_type !== 'vms_vendor' || $vendor_post->post_status === 'trash') {
                            continue;
                        }
                    }

                    if ($type_slug !== '') {
                        $matches_type = function_exists('bvmgr_vendor_has_type')
                            ? bvmgr_vendor_has_type($vendor_id, $type_slug)
                            : (function_exists('has_term') ? has_term($type_slug, 'vms_vendor_type', $vendor_id) : true);
                        if (!$matches_type) {
                            continue;
                        }
                    }

                    $valid_secondary[] = $vendor_id;
                }

                if (!empty($valid_secondary)) {
                    update_post_meta($post_id, $k_secondary_ids, $valid_secondary);
                } else {
                    delete_post_meta($post_id, $k_secondary_ids);
                }

                delete_post_meta($post_id, $k_secondary_idx);
                foreach ($valid_secondary as $vendor_id) {
                    add_post_meta($post_id, $k_secondary_idx, (int) $vendor_id, false);
                }

                $unq_ids = array();
                foreach ($valid_secondary as $vendor_id) {
                    if (function_exists('bvmgr_secondary_vendor_is_qualified')) {
                        $ok = bvmgr_secondary_vendor_is_qualified((int) $vendor_id, array(
                            'context' => 'event_plan_secondary_vendor',
                            'plan_id' => (int) $post_id,
                            'type_slug' => $type_slug,
                        ));
                        if (!$ok) {
                            $unq_ids[] = (int) $vendor_id;
                        }
                    }
                }

                $unq_ids = array_values(array_unique(array_filter(array_map('absint', $unq_ids), static function ($vendor_id) {
                    return $vendor_id > 0;
                })));

                if (!empty($unq_ids)) {
                    update_post_meta($post_id, $k_secondary_unq, '1');
                    update_post_meta($post_id, $k_secondary_unq_ids, $unq_ids);
                } else {
                    delete_post_meta($post_id, $k_secondary_unq);
                    delete_post_meta($post_id, $k_secondary_unq_ids);
                }

                if (function_exists('bvmgr_event_plan_clear_integrity_flags')) {
                    $k_issue = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_issue') : '_vms_integrity_issue';
                    $issue_now = (string) get_post_meta($post_id, $k_issue, true);
                    if ($issue_now === 'missing_secondary_vendor') {
                        bvmgr_event_plan_clear_integrity_flags($post_id);
                    }
                }

                return array(
                    'type_slug' => $type_slug,
                    'secondary_ids' => $valid_secondary,
                    'unqualified_ids' => $unq_ids,
                );
            }
        }

        if (!function_exists('bvmgr_event_plan_save_secondary_vendors_module')) {
            function bvmgr_event_plan_save_secondary_vendors_module(int $post_id, array $request)
            {
                $post_id = absint($post_id);
                if ($post_id <= 0 || get_post_type($post_id) !== 'vms_event_plan') {
                    return new WP_Error('vms_event_plan_invalid', __('Event Plan could not be found for Additional Vendors save.', 'backstage-venue-manager'));
                }

                $current_vendor_state = function_exists('bvmgr_event_plan_get_secondary_vendor_state')
                    ? bvmgr_event_plan_get_secondary_vendor_state($post_id)
                    : array(
                        'primary_vendor_id' => (int) get_post_meta($post_id, '_vms_band_vendor_id', true),
                        'secondary_vendor_type' => sanitize_key((string) get_post_meta($post_id, '_vms_secondary_vendor_type', true)),
                        'secondary_vendor_ids' => array_values(array_unique(array_filter(array_map('absint', (array) get_post_meta($post_id, '_vms_secondary_vendor_ids', true))))),
                        'linked_tec_event_id' => (int) get_post_meta($post_id, '_vms_tec_event_id', true),
                    );
                $secondary_vendor_submission = function_exists('bvmgr_event_plan_resolve_secondary_vendor_submission')
                    ? bvmgr_event_plan_resolve_secondary_vendor_submission($post_id, $request)
                    : array(
                        'current_state' => $current_vendor_state,
                        'type_slug' => (string) ($current_vendor_state['secondary_vendor_type'] ?? ''),
                        'secondary_ids' => (array) ($current_vendor_state['secondary_vendor_ids'] ?? array()),
                        'clear_requested' => !empty($request['vms_clear_secondary_vendors']),
                    );

                $type_slug = function_exists('bvmgr_event_plan_normalize_secondary_vendor_type_slug')
                    ? bvmgr_event_plan_normalize_secondary_vendor_type_slug((string) ($secondary_vendor_submission['type_slug'] ?? ''))
                    : sanitize_key((string) ($secondary_vendor_submission['type_slug'] ?? ''));
                $valid_secondary = function_exists('bvmgr_event_plan_normalize_secondary_vendor_ids')
                    ? bvmgr_event_plan_normalize_secondary_vendor_ids(
                        $post_id,
                        $type_slug,
                        (array) ($secondary_vendor_submission['secondary_ids'] ?? array()),
                        (int) ($current_vendor_state['primary_vendor_id'] ?? 0)
                    )
                    : array_values(array_unique(array_filter(array_map('absint', (array) ($secondary_vendor_submission['secondary_ids'] ?? array())))));
                $linked_tec_event_id = (int) ($current_vendor_state['linked_tec_event_id'] ?? 0);
                $proposed_vendor_state = array(
                    'primary_vendor_id' => (int) ($current_vendor_state['primary_vendor_id'] ?? 0),
                    'secondary_vendor_type' => $type_slug,
                    'secondary_vendor_ids' => $valid_secondary,
                    'linked_tec_event_id' => $linked_tec_event_id,
                );
                $vendor_dirty_fields = function_exists('bvmgr_event_plan_secondary_vendor_state_diff_fields')
                    ? bvmgr_event_plan_secondary_vendor_state_diff_fields(
                        $current_vendor_state,
                        $proposed_vendor_state,
                        array('secondary_vendor_type', 'secondary_vendor_ids')
                    )
                    : array();
                $repair_reasons = function_exists('bvmgr_event_plan_secondary_vendor_rebuild_repair_reasons')
                    ? bvmgr_event_plan_secondary_vendor_rebuild_repair_reasons($post_id, $current_vendor_state)
                    : array();
                $changed = !empty($vendor_dirty_fields) || !empty($repair_reasons);
                $queued_calendar_maintenance = false;

                if ($changed) {
                    $write_result = function_exists('bvmgr_event_plan_set_secondary_vendors')
                        ? bvmgr_event_plan_set_secondary_vendors($post_id, $type_slug, $valid_secondary)
                        : array(
                            'type_slug' => $type_slug,
                            'secondary_ids' => $valid_secondary,
                            'unqualified_ids' => array(),
                        );
                    if (is_wp_error($write_result)) {
                        return $write_result;
                    }

                    if (function_exists('bvmgr_event_plan_update_vendor_category_snapshot')) {
                        bvmgr_event_plan_update_vendor_category_snapshot($post_id);
                    }

                    if ($linked_tec_event_id > 0 && function_exists('bvmgr_event_plan_schedule_calendar_maintenance')) {
                        bvmgr_event_plan_schedule_calendar_maintenance($post_id, $linked_tec_event_id, 'vendor_category_sync');
                        $queued_calendar_maintenance = true;
                    }
                }

                clean_post_cache($post_id);

                return array(
                    'changed' => $changed,
                    'dirty_fields' => array_values(array_unique(array_map('sanitize_key', (array) $vendor_dirty_fields))),
                    'repair_reasons' => array_values(array_unique(array_map('sanitize_key', (array) $repair_reasons))),
                    'queued_calendar_maintenance' => $queued_calendar_maintenance,
                    'secondary_vendor_type' => $type_slug,
                    'secondary_vendor_ids' => $valid_secondary,
                );
            }
        }


        function bvmgr_event_plan_get_venue_state(int $venue_id): string
        {
            if ($venue_id <= 0) return 'none';

            $p = get_post($venue_id);
            if (!$p || $p->post_type !== 'vms_venue') return 'missing';
            if ($p->post_status === 'trash') return 'trashed';
            if ($p->post_status !== 'publish') return 'unpublished';
            return 'ok';
        }

        function bvmgr_event_plan_flag_missing_vendor(int $plan_id, int $vendor_id, string $vendor_title = ''): void
        {
            if ($plan_id <= 0) return;

            $k_issue = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_issue') : '_vms_integrity_issue';
            $k_vid   = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_vendor_id') : '_vms_integrity_vendor_id';
            $k_vt    = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_vendor_title') : '_vms_integrity_vendor_title';
            $k_ts    = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_ts') : '_vms_integrity_ts';
            $k_venue_id = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'integrity_venue_id') ?: '_vms_integrity_venue_id') : '_vms_integrity_venue_id';
            $k_venue_t  = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'integrity_venue_title') ?: '_vms_integrity_venue_title') : '_vms_integrity_venue_title';

                    $k_venue_id = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'integrity_venue_id') ?: '_vms_integrity_venue_id') : '_vms_integrity_venue_id';
            $k_venue_t  = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'integrity_venue_title') ?: '_vms_integrity_venue_title') : '_vms_integrity_venue_title';
        $k_venue_id = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'integrity_venue_id') ?: '_vms_integrity_venue_id') : '_vms_integrity_venue_id';
            $k_venue_t  = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'integrity_venue_title') ?: '_vms_integrity_venue_title') : '_vms_integrity_venue_title';

        update_post_meta($plan_id, $k_issue, 'missing_vendor');
            update_post_meta($plan_id, $k_vid, (int) $vendor_id);
            if ($vendor_title !== '') update_post_meta($plan_id, $k_vt, (string) $vendor_title);
            update_post_meta($plan_id, $k_ts, (string) wp_date('Y-m-d H:i:s'));
        }

        function bvmgr_event_plan_flag_missing_secondary_vendor(int $plan_id, int $vendor_id, string $vendor_title = ''): void
        {
            if ($plan_id <= 0) return;

            $k_issue = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_issue') : '_vms_integrity_issue';
            $k_vid   = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_vendor_id') : '_vms_integrity_vendor_id';
            $k_vt    = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_vendor_title') : '_vms_integrity_vendor_title';
            $k_ts    = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_ts') : '_vms_integrity_ts';
            $k_venue_id = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'integrity_venue_id') ?: '_vms_integrity_venue_id') : '_vms_integrity_venue_id';
            $k_venue_t  = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'integrity_venue_title') ?: '_vms_integrity_venue_title') : '_vms_integrity_venue_title';

            update_post_meta($plan_id, $k_issue, 'missing_secondary_vendor');
            update_post_meta($plan_id, $k_vid, (int) $vendor_id);
            if ($vendor_title !== '') update_post_meta($plan_id, $k_vt, (string) $vendor_title);
            update_post_meta($plan_id, $k_ts, (string) wp_date('Y-m-d H:i:s'));
        }



        function bvmgr_event_plan_flag_trashed_vendor(int $plan_id, int $vendor_id, string $vendor_title = ''): void
        {
            if ($plan_id <= 0 || $vendor_id <= 0) return;

            $k_issue = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_issue') : '_vms_integrity_issue';
            $k_vid   = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_vendor_id') : '_vms_integrity_vendor_id';
            $k_vt    = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_vendor_title') : '_vms_integrity_vendor_title';
            $k_ts    = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_ts') : '_vms_integrity_ts';

            update_post_meta($plan_id, $k_issue, 'trashed_vendor');
            update_post_meta($plan_id, $k_vid, $vendor_id);
            if ($vendor_title !== '') update_post_meta($plan_id, $k_vt, $vendor_title);
            update_post_meta($plan_id, $k_ts, time());
        }

        function bvmgr_event_plan_flag_trashed_secondary_vendor(int $plan_id, int $vendor_id, string $vendor_title = ''): void
        {
            if ($plan_id <= 0 || $vendor_id <= 0) return;

            $k_issue = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_issue') : '_vms_integrity_issue';
            $k_vid   = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_vendor_id') : '_vms_integrity_vendor_id';
            $k_vt    = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_vendor_title') : '_vms_integrity_vendor_title';
            $k_ts    = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_ts') : '_vms_integrity_ts';

            update_post_meta($plan_id, $k_issue, 'trashed_secondary_vendor');
            update_post_meta($plan_id, $k_vid, $vendor_id);
            if ($vendor_title !== '') update_post_meta($plan_id, $k_vt, $vendor_title);
            update_post_meta($plan_id, $k_ts, time());
        }

        function bvmgr_event_plan_flag_missing_venue(int $plan_id, int $venue_id, string $venue_title = ''): void
        {
            if ($plan_id <= 0 || $venue_id <= 0) return;

            $k_issue = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_issue') : '_vms_integrity_issue';
            $k_venue = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'integrity_venue_id') ?: '_vms_integrity_venue_id') : '_vms_integrity_venue_id';
            $k_vt    = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'integrity_venue_title') ?: '_vms_integrity_venue_title') : '_vms_integrity_venue_title';
            $k_ts    = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_ts') : '_vms_integrity_ts';

            update_post_meta($plan_id, $k_issue, 'missing_venue');
            update_post_meta($plan_id, $k_venue, $venue_id);
            if ($venue_title !== '') update_post_meta($plan_id, $k_vt, $venue_title);
            update_post_meta($plan_id, $k_ts, time());
        }

        function bvmgr_event_plan_flag_trashed_venue(int $plan_id, int $venue_id, string $venue_title = ''): void
        {
            if ($plan_id <= 0 || $venue_id <= 0) return;

            $k_issue = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_issue') : '_vms_integrity_issue';
            $k_venue = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'integrity_venue_id') ?: '_vms_integrity_venue_id') : '_vms_integrity_venue_id';
            $k_vt    = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'integrity_venue_title') ?: '_vms_integrity_venue_title') : '_vms_integrity_venue_title';
            $k_ts    = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_ts') : '_vms_integrity_ts';

            update_post_meta($plan_id, $k_issue, 'trashed_venue');
            update_post_meta($plan_id, $k_venue, $venue_id);
            if ($venue_title !== '') update_post_meta($plan_id, $k_vt, $venue_title);
            update_post_meta($plan_id, $k_ts, time());
        }

        function bvmgr_event_plan_flag_venue_unpublished(int $plan_id, int $venue_id, string $venue_title = ''): void
        {
            if ($plan_id <= 0 || $venue_id <= 0) return;

            $k_issue = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_issue') : '_vms_integrity_issue';
            $k_venue = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'integrity_venue_id') ?: '_vms_integrity_venue_id') : '_vms_integrity_venue_id';
            $k_vt    = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'integrity_venue_title') ?: '_vms_integrity_venue_title') : '_vms_integrity_venue_title';
            $k_ts    = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_ts') : '_vms_integrity_ts';

            update_post_meta($plan_id, $k_issue, 'venue_unpublished');
            update_post_meta($plan_id, $k_venue, $venue_id);
            if ($venue_title !== '') update_post_meta($plan_id, $k_vt, $venue_title);
            update_post_meta($plan_id, $k_ts, time());
        }
        function bvmgr_event_plan_clear_integrity_flags(int $plan_id): void
        {
            if ($plan_id <= 0) return;

            $k_issue = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_issue') : '_vms_integrity_issue';
            $k_vid   = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_vendor_id') : '_vms_integrity_vendor_id';
            $k_vt    = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_vendor_title') : '_vms_integrity_vendor_title';
            $k_ts    = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_ts') : '_vms_integrity_ts';
            $k_venue_id = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'integrity_venue_id') ?: '_vms_integrity_venue_id') : '_vms_integrity_venue_id';
            $k_venue_t  = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'integrity_venue_title') ?: '_vms_integrity_venue_title') : '_vms_integrity_venue_title';

            delete_post_meta($plan_id, $k_issue);
            delete_post_meta($plan_id, $k_vid);
            delete_post_meta($plan_id, $k_vt);
            delete_post_meta($plan_id, $k_ts);
            delete_post_meta($plan_id, $k_venue_id);
            delete_post_meta($plan_id, $k_venue_t);
        }


        /**
         * Background safety net: daily cron scan to flag published/ready Event Plans
         * whose vendor record was deleted. This prevents issues from slipping
         * through if the plan is never opened in the editor.
         */
        if (!function_exists('bvmgr_integrity_schedule_daily_scan')) {
            function bvmgr_integrity_schedule_daily_scan(): void
            {
                if (function_exists('bvmgr_should_run_runtime_maintenance') && !bvmgr_should_run_runtime_maintenance()) {
                    return;
                }
                if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_event')) {
                    return;
                }

                if (!wp_next_scheduled('vms_integrity_daily_scan')) {
                    wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'vms_integrity_daily_scan');
                }
            }
        }
        add_action('init', 'bvmgr_integrity_schedule_daily_scan');



        function bvmgr_integrity_scan_event_plans_for_orphaned_venues(int $limit = 500): array
        {
        	$results = array(
        		'checked' => 0,
        		'flagged_missing_venue' => 0,
        		'flagged_trashed_venue' => 0,
        		'flagged_venue_unpublished' => 0,
        		'cleared_venue_refs' => 0,
        		'forced_draft' => 0,
        	);

        	$args = array(
        		'post_type' => 'vms_event_plan',
        		'post_status' => array('publish', 'draft'),
        		'posts_per_page' => ($limit > 0 ? $limit : 500),
        		'fields' => 'ids',
        		'no_found_rows' => true,
        		'orderby' => 'ID',
        		'order' => 'DESC',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- This finite ID-only integrity batch must locate positive Venue references before repairing broken links.
        		'meta_query' => array(
        			array(
        				'key' => '_vms_venue_id',
        				'value' => 0,
        				'compare' => '>',
        				'type' => 'NUMERIC',
        			),
        		),
        	);

        	$event_plan_ids = get_posts($args);
        	if (empty($event_plan_ids)) return $results;

	$k_status = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'status') ?: '_vms_event_plan_status') : '_vms_event_plan_status';

        	foreach ($event_plan_ids as $event_plan_id) {
        		$results['checked']++;

        		$venue_id = (int) get_post_meta($event_plan_id, '_vms_venue_id', true);
        		if ($venue_id <= 0) continue;

		$state = bvmgr_event_plan_get_venue_state($venue_id);
        		$needs_force_draft = false;

        		if ($state === 'missing') {
			bvmgr_event_plan_flag_missing_venue($event_plan_id, $venue_id, '');
        			update_post_meta($event_plan_id, '_vms_venue_id', 0);
        			$results['cleared_venue_refs']++;
        			$results['flagged_missing_venue']++;
        			$needs_force_draft = true;
        		} elseif ($state === 'trashed') {
        			$vp = get_post($venue_id);
        			$title = ($vp && !empty($vp->post_title)) ? $vp->post_title : '';
			bvmgr_event_plan_flag_trashed_venue($event_plan_id, $venue_id, $title);
        			$results['flagged_trashed_venue']++;
        			$needs_force_draft = true;
        		} elseif ($state === 'unpublished') {
        			$vp = get_post($venue_id);
        			$title = ($vp && !empty($vp->post_title)) ? $vp->post_title : '';
			bvmgr_event_plan_flag_venue_unpublished($event_plan_id, $venue_id, $title);
        			$results['flagged_venue_unpublished']++;
        			$needs_force_draft = true;
        		}

        		if ($needs_force_draft) {
        			$internal_status = get_post_meta($event_plan_id, $k_status, true);
        			if ($internal_status === 'published' || $internal_status === 'ready') {
        				update_post_meta($event_plan_id, $k_status, 'draft');

        				$p = get_post($event_plan_id);
        				if ($p && $p->post_status !== 'draft') {
					bvmgr_event_plan_perf_wp_update_post(array('ID' => $event_plan_id, 'post_status' => 'draft'), 'event_plan_force_draft_scan_vendor_or_venue', $event_plan_id);
        				}

        				$results['forced_draft']++;
        			}
        		}
        	}

        	return $results;
        }


        /**
         * Returns a detailed list of Event Plans that reference Venues with link issues.
         *
         * Output shape:
         * [
         *   'trashed' => [ [plan_id, plan_title, venue_id, venue_title], ... ],
         *   'missing' => [ ... ],
         *   'unpublished' => [ ... ],
         * ]
         */
        function bvmgr_integrity_list_event_plans_with_venue_issues(int $limit = 500): array
        {
        	$out = array(
        		'trashed' => array(),
        		'missing' => array(),
        		'unpublished' => array(),
        	);

        	$args = array(
        		'post_type' => 'vms_event_plan',
        		'post_status' => array('publish', 'draft'),
        		'posts_per_page' => ($limit > 0 ? $limit : 500),
        		'fields' => 'ids',
        		'no_found_rows' => true,
        		'orderby' => 'ID',
        		'order' => 'DESC',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- This finite ID-only reconciliation list must locate positive Venue references for operator review.
        		'meta_query' => array(
        			array(
        				'key' => '_vms_venue_id',
        				'value' => 0,
        				'compare' => '>',
        				'type' => 'NUMERIC',
        			),
        		),
        	);

        	$event_plan_ids = get_posts($args);
        	if (empty($event_plan_ids)) return $out;

        	foreach ($event_plan_ids as $event_plan_id) {
        		$pid = (int) $event_plan_id;
        		$venue_id = (int) get_post_meta($pid, '_vms_venue_id', true);
        		if ($venue_id <= 0) continue;

		$state = bvmgr_event_plan_get_venue_state($venue_id);
        		if ($state !== 'missing' && $state !== 'trashed' && $state !== 'unpublished') {
        			continue;
        		}

        		$plan = get_post($pid);
        		$plan_title = ($plan && !empty($plan->post_title)) ? (string) $plan->post_title : ('Event Plan #' . $pid);

        		$venue_title = '';
        		$vp = get_post($venue_id);
        		if ($vp && !empty($vp->post_title)) {
        			$venue_title = (string) $vp->post_title;
        		}

        		$out[$state][] = array(
        			'plan_id' => $pid,
        			'plan_title' => $plan_title,
        			'venue_id' => $venue_id,
        			'venue_title' => $venue_title,
        		);
        	}

        	return $out;
        }



        function bvmgr_event_plan_calendar_unpublished_suppressed(int $plan_id): bool
        {
	$k = function_exists('bvmgr_meta_key')
		? (bvmgr_meta_key('event_plan', 'calendar_unpublished_suppress') ?: '_vms_calendar_unpublished_suppress')
        		: '_vms_calendar_unpublished_suppress';

        	$v = (string) get_post_meta($plan_id, $k, true);
        	return in_array($v, array('1', 'yes', 'true'), true);
        }

        function bvmgr_integrity_calendar_unpublished_applies_for_status(string $internal_status): bool
        {
        	$statuses = apply_filters('vms_integrity_calendar_unpublished_statuses', array('published'));

        	if (!is_array($statuses)) {
        		$statuses = array('published');
        	}

        	$clean = array();
        	foreach ($statuses as $s) {
        		$s = sanitize_key((string) $s);
        		if ($s !== '') $clean[] = $s;
        	}

        	if (empty($clean)) $clean = array('published');

        	return in_array(sanitize_key($internal_status), $clean, true);
        }


        function bvmgr_event_plan_get_calendar_event_state(int $tec_event_id): string
        {
        	if ($tec_event_id <= 0) return 'none';

        	$p = get_post($tec_event_id);
        	if (!$p || $p->post_type !== 'tribe_events') return 'missing';
        	if ($p->post_status === 'trash') return 'trashed';

        	// TEC events can be Scheduled (future). Treat publish and future as OK.
        	if (!in_array($p->post_status, array('publish', 'future'), true)) return 'unpublished';

        	return 'ok';
        }

        function bvmgr_integrity_list_event_plans_with_calendar_issues(int $limit = 500): array
        {
        	$out = array(
        		'trashed' => array(),
        		'missing' => array(),
        		'unpublished' => array(),
        		'unlinked' => array(),
        	);

        	$args = array(
        		'post_type' => 'vms_event_plan',
        		'post_status' => array('publish', 'draft'),
        		'posts_per_page' => ($limit > 0 ? $limit : 500),
        		'fields' => 'ids',
        		'no_found_rows' => true,
        		'orderby' => 'ID',
        		'order' => 'DESC',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- This finite ID-only reconciliation list must combine linked calendar IDs with publish-ready plans to report integrity issues.
        		'meta_query' => array(
        			'relation' => 'OR',
        			array(
        				'key' => '_vms_tec_event_id',
        				'value' => 0,
        				'compare' => '>',
        				'type' => 'NUMERIC',
        			),
        			array(
        				'key' => '_vms_event_plan_status',
        				'value' => array('published', 'ready'),
        				'compare' => 'IN',
        			),
        		),
        	);

        	$event_plan_ids = get_posts($args);
        	if (empty($event_plan_ids)) return $out;

	$k_status = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'status') ?: '_vms_event_plan_status') : '_vms_event_plan_status';

        	foreach ($event_plan_ids as $event_plan_id) {
        		$pid = (int) $event_plan_id;

        		$plan = get_post($pid);
        		$plan_title = ($plan && !empty($plan->post_title)) ? (string) $plan->post_title : ('Event Plan #' . $pid);

        		$internal_status = (string) get_post_meta($pid, $k_status, true);

        		$tec_event_id  = (int) get_post_meta($pid, '_vms_tec_event_id', true);
        		$tec_event_url = (string) get_post_meta($pid, '_vms_tec_event_url', true);

        		if ($tec_event_id <= 0) {
        			// Only list as unlinked if the plan is intended to be Published or Ready.
        			if ($internal_status === 'published' || $internal_status === 'ready') {
        				$out['unlinked'][] = array(
        					'plan_id' => $pid,
        					'plan_title' => $plan_title,
        					'internal_status' => $internal_status,
        					'tec_event_id' => 0,
        					'tec_event_url' => $tec_event_url,
        				);
        			}
        			continue;
        		}

		$state = bvmgr_event_plan_get_calendar_event_state($tec_event_id);

        		if ($state === 'unpublished') {
			if (!bvmgr_integrity_calendar_unpublished_applies_for_status($internal_status)) {
        				continue;
        			}
			if (bvmgr_event_plan_calendar_unpublished_suppressed($pid)) {
        				continue;
        			}
        		}

        		if ($state !== 'missing' && $state !== 'trashed' && $state !== 'unpublished') {
        			continue;
        		}

        		$tec_title = '';
        		$tec_status = '';
        		$tp = get_post($tec_event_id);
        		if ($tp) {
        			$tec_title = !empty($tp->post_title) ? (string) $tp->post_title : '';
        			$tec_status = (string) $tp->post_status;
        		}

        		$out[$state][] = array(
        			'plan_id' => $pid,
        			'plan_title' => $plan_title,
        			'internal_status' => $internal_status,
        			'tec_event_id' => $tec_event_id,
        			'tec_event_title' => $tec_title,
        			'tec_event_status' => $tec_status,
        			'tec_event_url' => $tec_event_url,
        		);
        	}

        	return $out;
        }



        function bvmgr_integrity_scan_event_plans_for_orphaned_calendar_events(int $limit = 500): array
        {
        	$results = array(
        		'checked' => 0,
        		'flagged_calendar_event_unlinked' => 0,
        		'flagged_missing_calendar_event' => 0,
        		'flagged_trashed_calendar_event' => 0,
        		'flagged_calendar_event_unpublished' => 0,
        		'cleared_calendar_event_refs' => 0,
        		'forced_draft' => 0,
        	);

        	$args = array(
        		'post_type' => 'vms_event_plan',
        		'post_status' => array('publish', 'draft'),
        		'posts_per_page' => ($limit > 0 ? $limit : 500),
        		'fields' => 'ids',
        		'no_found_rows' => true,
        		'orderby' => 'ID',
        		'order' => 'DESC',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- This finite ID-only integrity batch must combine linked calendar IDs with publish-ready plans before repairs.
        		'meta_query' => array(
        			'relation' => 'OR',
        			array(
        				'key' => '_vms_tec_event_id',
        				'value' => 0,
        				'compare' => '>',
        				'type' => 'NUMERIC',
        			),
        			array(
        				'key' => '_vms_event_plan_status',
        				'value' => array('published', 'ready'),
        				'compare' => 'IN',
        			),
        		),
        	);

        	$event_plan_ids = get_posts($args);
        	if (empty($event_plan_ids)) return $results;

	$k_status = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'status') ?: '_vms_event_plan_status') : '_vms_event_plan_status';
	$k_issue  = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_issue') : '_vms_integrity_issue';
	$k_ts     = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_ts') : '_vms_integrity_ts';

        	foreach ($event_plan_ids as $event_plan_id) {
        		$results['checked']++;

        		$internal_status = (string) get_post_meta($event_plan_id, $k_status, true);
        		$force_if_needed = ($internal_status === 'published' || $internal_status === 'ready');

        		$tec_event_id  = (int) get_post_meta($event_plan_id, '_vms_tec_event_id', true);
        		$tec_event_url = (string) get_post_meta($event_plan_id, '_vms_tec_event_url', true);

        		$existing_issue = (string) get_post_meta($event_plan_id, $k_issue, true);
		$unpub_applies = bvmgr_integrity_calendar_unpublished_applies_for_status($internal_status);
		$unpub_suppressed = bvmgr_event_plan_calendar_unpublished_suppressed((int) $event_plan_id);

        		$needs_force_draft = false;
        		$issue_to_set = '';

        		if ($tec_event_id <= 0) {
        			// Only treat as a problem when the plan is supposed to be Published/Ready.
        			if ($force_if_needed) {
        				$results['flagged_calendar_event_unlinked']++;
        				$needs_force_draft = true;
        				$issue_to_set = 'calendar_event_unlinked';

        				// If the URL exists but the ID does not, clear the URL so it cannot mislead.
        				if ($tec_event_url !== '') update_post_meta($event_plan_id, '_vms_tec_event_url', '');
        			}
        		} else {
        			$tec_post = get_post($tec_event_id);

        			if (!$tec_post || $tec_post->post_type !== 'tribe_events') {
        				// Permanently deleted or invalid reference: clear it.
        				$results['flagged_missing_calendar_event']++;
        				$results['cleared_calendar_event_refs']++;
        				$needs_force_draft = true;
        				$issue_to_set = 'missing_calendar_event';

        				update_post_meta($event_plan_id, '_vms_tec_event_id', 0);
        				if ($tec_event_url !== '') update_post_meta($event_plan_id, '_vms_tec_event_url', '');
        			} elseif ($tec_post->post_status === 'trash') {
        				// Trashed TEC event: keep the reference so it can recover if restored.
        				$results['flagged_trashed_calendar_event']++;
        				$needs_force_draft = true;
        				$issue_to_set = 'trashed_calendar_event';
        			} elseif (!in_array($tec_post->post_status, array('publish', 'future'), true)) {
        				if ($unpub_applies && !$unpub_suppressed) {
        					$results['flagged_calendar_event_unpublished']++;
        					$issue_to_set = 'calendar_event_unpublished';
        					// Unpublished TEC event is a visibility mismatch, not a broken reference. Do not force Draft.
        					$needs_force_draft = false;
        				} else {
        					// Not applicable (Draft/other status) or operator suppressed. Clear stale flag.
        					if ($existing_issue === 'calendar_event_unpublished') {
        						update_post_meta($event_plan_id, $k_issue, '');
        						update_post_meta($event_plan_id, $k_ts, 0);
        						$existing_issue = '';
        					}
        				}
        			}
        		}

        		// Clear stale “calendar_event_unpublished” flag when it no longer applies.
        		if ($issue_to_set === '' && $existing_issue === 'calendar_event_unpublished') {
        			update_post_meta($event_plan_id, $k_issue, '');
        			update_post_meta($event_plan_id, $k_ts, 0);
        			$existing_issue = '';
        		}

        		// Flag as Needs Attention (but do not overwrite an existing integrity issue).
        		if ($issue_to_set !== '') {
        			if ($existing_issue === '') {
        				update_post_meta($event_plan_id, $k_issue, $issue_to_set);
        				update_post_meta($event_plan_id, $k_ts, time());
        				$existing_issue = $issue_to_set;
        			}
        		}

        		// If anything broke while Published/Ready, force the plan back to Draft (internal + WP post_status)
        		if ($needs_force_draft && $force_if_needed) {
        			update_post_meta($event_plan_id, $k_status, 'draft');

        			$p = get_post($event_plan_id);
        			if ($p && $p->post_status !== 'draft') {
				bvmgr_event_plan_perf_wp_update_post(array('ID' => $event_plan_id, 'post_status' => 'draft'), 'event_plan_force_draft_integrity_scan', $event_plan_id);
        			}

        			$results['forced_draft']++;
        		}
        	}

        	return $results;
        }

        function bvmgr_integrity_scan_event_plans_all(int $limit = 500): array
        {
        	return array(
		'vendors' => bvmgr_integrity_scan_event_plans_for_missing_vendors($limit),
		'venues'  => bvmgr_integrity_scan_event_plans_for_orphaned_venues($limit),
		'events' => bvmgr_integrity_scan_event_plans_for_orphaned_calendar_events($limit),
        	);
        }

        add_action('vms_integrity_daily_scan', 'bvmgr_integrity_scan_event_plans_for_missing_vendors');
        add_action('vms_integrity_daily_scan', 'bvmgr_integrity_scan_event_plans_for_orphaned_venues');

        function bvmgr_integrity_scan_event_plans_for_missing_vendors(int $limit = 500): array
        {
        	$results = array(
        		'checked' => 0,
        		'flagged_missing_vendor' => 0,
        		'flagged_trashed_vendor' => 0,
        		'flagged_missing_secondary_vendor' => 0,
        		'flagged_trashed_secondary_vendor' => 0,
        		'removed_missing_secondary_vendor_ids' => 0,
        		'forced_draft' => 0,
        	);

        	// 1) Event Plans with a band vendor assigned
        	$args_band = array(
        		'post_type' => 'vms_event_plan',
        		'post_status' => array('publish', 'draft'),
        		'posts_per_page' => ($limit > 0 ? $limit : 500),
        		'fields' => 'ids',
        		'no_found_rows' => true,
        		'orderby' => 'ID',
        		'order' => 'DESC',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- This finite ID-only integrity batch must locate positive primary Vendor references before repairing broken links.
        		'meta_query' => array(
        			array(
        				'key' => '_vms_band_vendor_id',
        				'value' => 0,
        				'compare' => '>',
        				'type' => 'NUMERIC',
        			),
        		),
        	);

        	$event_plan_ids = get_posts($args_band);

        	// 2) Event Plans with secondary vendor ids assigned (array stored as serialized meta)
        	$args_secondary = array(
        		'post_type' => 'vms_event_plan',
        		'post_status' => array('publish', 'draft'),
        		'posts_per_page' => ($limit > 0 ? $limit : 500),
        		'fields' => 'ids',
        		'no_found_rows' => true,
        		'orderby' => 'ID',
        		'order' => 'DESC',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- This finite ID-only integrity batch must locate serialized secondary Vendor assignments before validating each ID.
        		'meta_query' => array(
        			array(
        				'key' => '_vms_secondary_vendor_ids',
        				'compare' => 'EXISTS',
        			),
        		),
        	);

        	$secondary_plan_ids = get_posts($args_secondary);
        	if (!empty($secondary_plan_ids)) {
        		$event_plan_ids = array_unique(array_merge($event_plan_ids, $secondary_plan_ids));
        	}

        	if (empty($event_plan_ids)) {
        		return $results;
        	}

	$k_status = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'status') ?: '_vms_event_plan_status') : '_vms_event_plan_status';

        	foreach ($event_plan_ids as $event_plan_id) {
        		$results['checked']++;

        		$needs_force_draft = false;

        		// Band vendor check
        		$band_vendor_id = (int) get_post_meta($event_plan_id, '_vms_band_vendor_id', true);
        		if ($band_vendor_id > 0) {
        			$vendor_post = get_post($band_vendor_id);

        			if (!$vendor_post || $vendor_post->post_type !== 'vms_vendor') {
        				// Permanently deleted or invalid reference: clear it
				bvmgr_event_plan_flag_missing_vendor($event_plan_id, $band_vendor_id, '');
        				update_post_meta($event_plan_id, '_vms_band_vendor_id', 0);
        				$needs_force_draft = true;
        				$results['flagged_missing_vendor']++;
        			} elseif ($vendor_post->post_status === 'trash') {
        				// Trashed vendor: keep the reference so it can recover if restored
				bvmgr_event_plan_flag_trashed_vendor($event_plan_id, $band_vendor_id, $vendor_post->post_title);
        				$needs_force_draft = true;
        				$results['flagged_trashed_vendor']++;
        			}
        		}

        		// Secondary vendors check
        		$secondary_vendor_ids = get_post_meta($event_plan_id, '_vms_secondary_vendor_ids', true);
        		if (!is_array($secondary_vendor_ids)) {
        			$secondary_vendor_ids = array();
        		}

        		$removed_ids = array();
        		$removed_titles = array();
        		$new_secondary_vendor_ids = array();
        		$first_trashed_secondary = null;

        		foreach ($secondary_vendor_ids as $vid) {
        			$vid = (int) $vid;
        			if ($vid <= 0) continue;

        			$vp = get_post($vid);
        			if (!$vp || $vp->post_type !== 'vms_vendor') {
        				$removed_ids[] = $vid;
        				$removed_titles[] = 'Deleted vendor (ID: ' . $vid . ')';
        				$needs_force_draft = true;
        				continue;
        			}

        			if ($vp->post_status === 'trash') {
        				$new_secondary_vendor_ids[] = $vid; // keep it (recoverable)
        				$needs_force_draft = true;
        				if ($first_trashed_secondary === null) {
        					$first_trashed_secondary = array('id' => $vid, 'title' => $vp->post_title);
        				}
        				continue;
        			}

        			$new_secondary_vendor_ids[] = $vid;
        		}

        		if (!empty($removed_ids)) {
        			// Remove only truly missing vendors (deleted). Keep trashed vendor IDs so they can recover.
        			$new_secondary_vendor_ids = array_values(array_unique($new_secondary_vendor_ids));
        			update_post_meta($event_plan_id, '_vms_secondary_vendor_ids', $new_secondary_vendor_ids);

        			// Rebuild the index meta: _vms_secondary_vendor_id
        			delete_post_meta($event_plan_id, '_vms_secondary_vendor_id');
        			foreach ($new_secondary_vendor_ids as $sid) {
        				add_post_meta($event_plan_id, '_vms_secondary_vendor_id', (int) $sid, false);
        			}

			bvmgr_event_plan_flag_missing_secondary_vendor($event_plan_id, $removed_ids, $removed_titles);
        			$needs_force_draft = true;
        			$results['flagged_missing_secondary_vendor']++;
        			$results['removed_missing_secondary_vendor_ids'] += count($removed_ids);
        		}

        		if ($first_trashed_secondary !== null) {
			bvmgr_event_plan_flag_trashed_secondary_vendor($event_plan_id, (int) $first_trashed_secondary['id'], (string) $first_trashed_secondary['title']);
        			$needs_force_draft = true;
        			$results['flagged_trashed_secondary_vendor']++;
        		}

        		// If anything broke while Published/Ready, force the plan back to Draft (internal + WP post_status)
        		if ($needs_force_draft) {
        			$internal_status = get_post_meta($event_plan_id, $k_status, true);
        			if ($internal_status === 'published' || $internal_status === 'ready') {
        				update_post_meta($event_plan_id, $k_status, 'draft');

        				$p = get_post($event_plan_id);
        				if ($p && $p->post_status !== 'draft') {
					bvmgr_event_plan_perf_wp_update_post(array('ID' => $event_plan_id, 'post_status' => 'draft'), 'event_plan_force_draft_secondary_vendor_scan', $event_plan_id);
        				}

        				$results['forced_draft']++;
        			}
        		}
        	}

        	return $results;
        }

        /**
         * Admin safety net: if a plan is opened and its band vendor ID points to a deleted vendor,
         * automatically revert the plan to Draft and flag it for review.
         */
        add_action('admin_init', function () {
            if (!is_admin()) return;

            $request = bvmgr_event_plan_current_get_request();
            $post_id = isset($request['post']) ? absint($request['post']) : 0;
            $action  = isset($request['action']) ? sanitize_key((string) $request['action']) : '';
            if ($post_id <= 0 || $action !== 'edit') return;

            $post = get_post($post_id);
            if (!$post || $post->post_type !== 'vms_event_plan') return;
            if (!current_user_can('edit_post', $post_id)) return;

            $k_band = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'band_vendor_id') : '_vms_band_vendor_id';
            $band_id = (int) get_post_meta($post_id, $k_band, true);

            if ($band_id > 0 && function_exists('bvmgr_event_plan_vendor_exists') && !bvmgr_event_plan_vendor_exists($band_id)) {
                // Flag + revert (non-destructive; does not touch TEC events automatically).
                if (function_exists('bvmgr_event_plan_flag_missing_vendor')) {
                    bvmgr_event_plan_flag_missing_vendor($post_id, $band_id, '');
                }

                // Clear the broken pointer so the UI doesn't keep referencing a dead vendor ID.
                update_post_meta($post_id, $k_band, 0);

                // Revert plan workflow status.
                $k_status = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'status') : '_vms_event_plan_status';
                update_post_meta($post_id, $k_status, 'draft');

                // Revert WP status if it was published.
                if ($post->post_status === 'publish') {
                    bvmgr_event_plan_perf_wp_update_post(array('ID' => $post_id, 'post_status' => 'draft'), 'event_plan_force_draft_missing_primary_vendor_admin_guard', $post_id);
                }

                if (function_exists('bvmgr_add_admin_notice')) {
                    bvmgr_add_admin_notice(__('🚩 The selected Primary Vendor was deleted. This event plan was reverted to Draft. Review the event and choose a new Primary Vendor.', 'backstage-venue-manager'), 'error');
                }
            }

            // Secondary vendors safety net
            $k_secondary_ids = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'secondary_vendor_ids') : '_vms_secondary_vendor_ids';
            $k_secondary_idx = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'secondary_vendor_id') : '_vms_secondary_vendor_id';
            $sec_ids = get_post_meta($post_id, $k_secondary_ids, true);
            if (!is_array($sec_ids)) $sec_ids = array();
            $sec_ids = array_values(array_unique(array_filter(array_map('absint', $sec_ids), fn($v) => $v > 0)));

            if (!empty($sec_ids)) {
                $valid = array();
                $missing = array();
                foreach ($sec_ids as $vid) {
                    if (function_exists('bvmgr_event_plan_vendor_exists') && bvmgr_event_plan_vendor_exists((int) $vid)) {
                        $valid[] = (int) $vid;
                    } else {
                        $missing[] = (int) $vid;
                    }
                }

                if (!empty($missing)) {
                    if (!empty($valid)) update_post_meta($post_id, $k_secondary_ids, $valid);
                    else delete_post_meta($post_id, $k_secondary_ids);

                    // Rebuild index
                    delete_post_meta($post_id, $k_secondary_idx);
                    foreach ($valid as $vid) {
                        add_post_meta($post_id, $k_secondary_idx, (int) $vid, false);
                    }

                    // Flag (don’t overwrite a more severe missing headliner flag)
                    $k_issue = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_issue') : '_vms_integrity_issue';
                    $issue_now = (string) get_post_meta($post_id, $k_issue, true);
                    if ($issue_now !== 'missing_vendor' && function_exists('bvmgr_event_plan_flag_missing_secondary_vendor')) {
                        bvmgr_event_plan_flag_missing_secondary_vendor($post_id, (int) $missing[0], '');
                    }

                    // Revert plan workflow status.
                    $k_status = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'status') : '_vms_event_plan_status';
                    update_post_meta($post_id, $k_status, 'draft');

                    // Revert WP status if it was published.
                    if ($post->post_status === 'publish') {
                        bvmgr_event_plan_perf_wp_update_post(array('ID' => $post_id, 'post_status' => 'draft'), 'event_plan_force_draft_missing_secondary_vendor_admin_guard', $post_id);
                    }

                    if (function_exists('bvmgr_add_admin_notice')) {
                        bvmgr_add_admin_notice(__('🚩 A secondary vendor was deleted. This event plan was reverted to Draft. Review Additional Vendors and reassign as needed.', 'backstage-venue-manager'), 'error');
                    }
                }
            }
        });

        /**
         * List table: status pill column
         */
        add_filter('manage_vms_event_plan_posts_columns', 'bvmgr_add_event_plan_status_column');
        function bvmgr_add_event_plan_status_column(array $columns): array
        {
            $new = array();
            foreach ($columns as $key => $label) {
                if ($key === 'date') $new['vms_plan_status'] = __('Plan Status', 'backstage-venue-manager');
                $new[$key] = $label;
            }
            if (!isset($new['vms_plan_status'])) $new['vms_plan_status'] = __('Plan Status', 'backstage-venue-manager');
            return $new;
        }

        add_action('manage_vms_event_plan_posts_custom_column', 'bvmgr_render_event_plan_status_column', 10, 2);
        function bvmgr_render_event_plan_status_column(string $column, int $post_id): void
        {
            if ($column !== 'vms_plan_status') return;

            $status = function_exists('bvmgr_event_plan_get_status')
                ? (string) bvmgr_event_plan_get_status($post_id, 'event_list')
                : sanitize_key((string) get_post_meta($post_id, bvmgr_meta_key('event_plan', 'status'), true));

            $status = sanitize_key((string) $status);
            if ($status === 'canceled') $status = 'cancelled';
            if ($status === '') $status = 'draft';

            $label = function_exists('bvmgr_event_plan_status_label')
                ? (string) bvmgr_event_plan_status_label($status)
                : ucwords(str_replace(array('_', '-'), ' ', $status));

            $class = function_exists('bvmgr_event_plan_status_pill_class')
                ? (string) bvmgr_event_plan_status_pill_class($status)
                : 'vms-pill-draft';

            echo '<span class="vms-status-pill ' . esc_attr($class) . '">' . esc_html($label) . '</span>';
        }

        add_filter('manage_vms_event_plan_posts_columns', 'bvmgr_add_event_plan_event_date_column', 30);
        function bvmgr_add_event_plan_event_date_column(array $columns): array
        {
            $new = array();
            foreach ($columns as $key => $label) {
                if ($key === 'date') {
                    $new['vms_event_date'] = __('Event Date', 'backstage-venue-manager');
                }
                $new[$key] = $label;
            }

            if (!isset($new['vms_event_date'])) {
                $new['vms_event_date'] = __('Event Date', 'backstage-venue-manager');
            }

            return $new;
        }

        function bvmgr_admin_event_plan_list_event_date_meta_key(): string
        {
            $key = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'date') : '';
            return $key !== '' ? $key : '_vms_event_date';
        }

        function bvmgr_admin_event_plan_list_format_event_date_label(string $event_date): string
        {
            $event_date = trim($event_date);
            if ($event_date === '') {
                return '';
            }

            $timezone = function_exists('wp_timezone') ? wp_timezone() : null;
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $event_date, $timezone ?: null);
            if ($date instanceof DateTimeImmutable) {
                return $date->format('M j, Y');
            }

            return $event_date;
        }

        function bvmgr_admin_event_plan_list_format_start_time_label(string $start_time): string
        {
            $start_time = trim($start_time);
            if ($start_time === '') {
                return '';
            }

            $timezone = function_exists('wp_timezone') ? wp_timezone() : null;
            $time = DateTimeImmutable::createFromFormat('!H:i', $start_time, $timezone ?: null);
            if (!($time instanceof DateTimeImmutable)) {
                $time = DateTimeImmutable::createFromFormat('!H:i:s', $start_time, $timezone ?: null);
            }
            if ($time instanceof DateTimeImmutable) {
                return strtolower($time->format('g:i A'));
            }

            return $start_time;
        }

        add_action('manage_vms_event_plan_posts_custom_column', 'bvmgr_render_event_plan_event_date_column', 10, 2);
        function bvmgr_render_event_plan_event_date_column(string $column, int $post_id): void
        {
            if ($column !== 'vms_event_date') return;

            $event_date = trim((string) get_post_meta($post_id, bvmgr_admin_event_plan_list_event_date_meta_key(), true));
            if ($event_date === '') {
                echo '—';
                return;
            }

            $start_time = trim((string) get_post_meta($post_id, '_vms_start_time', true));
            $date_label = bvmgr_admin_event_plan_list_format_event_date_label($event_date);
            $time_label = bvmgr_admin_event_plan_list_format_start_time_label($start_time);

            echo '<strong>' . esc_html($date_label !== '' ? $date_label : $event_date) . '</strong>';
            if ($time_label !== '') {
                echo '<div class="description">' . esc_html($time_label) . '</div>';
            }
        }

        add_filter('manage_edit-vms_event_plan_sortable_columns', 'bvmgr_add_event_plan_event_date_sortable_column');
        function bvmgr_add_event_plan_event_date_sortable_column(array $columns): array
        {
            $columns['vms_event_date'] = 'vms_event_date';
            return $columns;
        }

        add_action('pre_get_posts', 'bvmgr_admin_event_plan_list_maybe_sort_by_event_date', 60);
        function bvmgr_admin_event_plan_list_maybe_sort_by_event_date($query): void
        {
            if (!is_admin() || !($query instanceof WP_Query) || !$query->is_main_query()) {
                return;
            }

            global $pagenow;
            if ($pagenow !== 'edit.php') {
                return;
            }

            if ($query->get('post_type') !== 'vms_event_plan') {
                return;
            }

            if ((string) $query->get('orderby') !== 'vms_event_date') {
                return;
            }

            $query->set('vms_ep_sort_event_date', 1);
        }

        add_filter('posts_clauses', 'bvmgr_admin_event_plan_list_event_date_sort_clauses', 10, 2);
        function bvmgr_admin_event_plan_list_event_date_sort_clauses(array $clauses, WP_Query $query): array
        {
            if (!is_admin() || !$query->is_main_query()) {
                return $clauses;
            }

            if ((int) $query->get('vms_ep_sort_event_date') !== 1) {
                return $clauses;
            }

            if ($query->get('post_type') !== 'vms_event_plan') {
                return $clauses;
            }

            global $wpdb;

            $join = isset($clauses['join']) ? (string) $clauses['join'] : '';
            $groupby = isset($clauses['groupby']) ? (string) $clauses['groupby'] : '';
            $order = strtoupper((string) $query->get('order')) === 'ASC' ? 'ASC' : 'DESC';

            if (strpos($join, 'vms_ep_event_date_meta') === false) {
                $join .= $wpdb->prepare(
                    " LEFT JOIN {$wpdb->postmeta} AS vms_ep_event_date_meta ON ({$wpdb->posts}.ID = vms_ep_event_date_meta.post_id AND vms_ep_event_date_meta.meta_key = %s)",
                    bvmgr_admin_event_plan_list_event_date_meta_key()
                );
            }

            if (strpos($join, 'vms_ep_start_time_meta') === false) {
                $join .= $wpdb->prepare(
                    " LEFT JOIN {$wpdb->postmeta} AS vms_ep_start_time_meta ON ({$wpdb->posts}.ID = vms_ep_start_time_meta.post_id AND vms_ep_start_time_meta.meta_key = %s)",
                    '_vms_start_time'
                );
            }

            if ($groupby === '') {
                $groupby = "{$wpdb->posts}.ID";
            } elseif (strpos($groupby, "{$wpdb->posts}.ID") === false) {
                $groupby .= ", {$wpdb->posts}.ID";
            }

            $date_missing_expr = "(vms_ep_event_date_meta.meta_value IS NULL OR TRIM(vms_ep_event_date_meta.meta_value) = '')";
            $time_missing_expr = "(vms_ep_start_time_meta.meta_value IS NULL OR TRIM(vms_ep_start_time_meta.meta_value) = '')";
            $start_time_expr = "LEFT(TRIM(vms_ep_start_time_meta.meta_value), 5)";
            $datetime_expr = "CASE WHEN {$date_missing_expr} THEN NULL WHEN {$time_missing_expr} THEN STR_TO_DATE(vms_ep_event_date_meta.meta_value, '%Y-%m-%d') ELSE STR_TO_DATE(CONCAT(vms_ep_event_date_meta.meta_value, ' ', {$start_time_expr}), '%Y-%m-%d %H:%i') END";

            $clauses['join'] = $join;
            $clauses['groupby'] = $groupby;
            $clauses['orderby'] = "CASE WHEN {$date_missing_expr} THEN 1 ELSE 0 END ASC, {$datetime_expr} {$order}, {$wpdb->posts}.post_title {$order}, {$wpdb->posts}.ID {$order}";

            return $clauses;
        }



        /**
         * List table: payment/tax warning column (published plans only)
         */
        add_filter('manage_vms_event_plan_posts_columns', 'bvmgr_add_event_plan_tax_column', 20);
        function bvmgr_add_event_plan_tax_column(array $columns): array
        {
            $new = array();
            foreach ($columns as $key => $label) {
                $new[$key] = $label;
                if ($key === 'vms_plan_status') {
                    $new['vms_plan_tax'] = __('Payment', 'backstage-venue-manager');
                }
            }
            if (!isset($new['vms_plan_tax'])) $new['vms_plan_tax'] = __('Payment', 'backstage-venue-manager');
            return $new;
        }

        add_action('manage_vms_event_plan_posts_custom_column', 'bvmgr_render_event_plan_tax_column', 10, 2);
        function bvmgr_render_event_plan_tax_column(string $column, int $post_id): void
        {
            if ($column !== 'vms_plan_tax') return;

            $wp_status = (string) get_post_status($post_id);

            $plan_status = function_exists('bvmgr_event_plan_get_status')
                ? (string) bvmgr_event_plan_get_status($post_id, 'event_list')
                : sanitize_key((string) get_post_meta($post_id, bvmgr_meta_key('event_plan', 'status'), true));

            $plan_status = sanitize_key((string) $plan_status);

            $is_published = ($wp_status === 'publish' || $plan_status === 'published');
            if (!$is_published) return;

            $k_band_vendor_id = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'band_vendor_id') : '';
            if ($k_band_vendor_id === '') $k_band_vendor_id = '_vms_band_vendor_id';

            $vendor_id = absint(get_post_meta($post_id, $k_band_vendor_id, true));
            if ($vendor_id <= 0) return;

            $tax_missing = false;
            if (function_exists('bvmgr_is_vendor_tax_profile_complete')) {
                $tax_missing = !bvmgr_is_vendor_tax_profile_complete($vendor_id);
            }

            $st_bypass = function_exists('bvmgr_get_tax_bypass_status') ? (array) bvmgr_get_tax_bypass_status($vendor_id) : array();
            $bypass_active = !empty($st_bypass['is_active']);
            $bypass_until  = isset($st_bypass['until']) ? (string) $st_bypass['until'] : '';

            // Only show something if tax is missing or bypass is active (so the operator can't miss it).
            if (!$tax_missing && !$bypass_active) return;

            $label = '';
            $class = '';

            if ($tax_missing && !$bypass_active) {
                $label = __('Payment blocked', 'backstage-venue-manager');
                $class = 'vms-pill-pay-blocked';
            } elseif ($bypass_active) {
                /* translators: %s: date or time value. */
                $label = sprintf(__('Bypass until %s', 'backstage-venue-manager'), ($bypass_until !== '' ? $bypass_until : '—'));
                $class = 'vms-pill-bypass';
            }

            if ($label === '') return;

            $pill = '<span class="vms-status-pill ' . esc_attr($class) . '">' . esc_html($label) . '</span>';
            $link = get_edit_post_link($vendor_id, 'raw');

            if (!empty($link)) {
                echo '<a href="' . esc_url($link) . '">' . $pill . '</a>';
            } else {
                echo $pill;
            }
        }
        if (!function_exists('bvmgr_event_plan_legacy_ticket_meta_keys')) {
            function bvmgr_event_plan_legacy_ticket_meta_keys(): array
            {
                return array(
                    '_vms_price_ga',
                    '_vms_enable_tables',
                    '_vms_enable_firepits',
                    '_vms_enable_pools',
                    '_vms_table_count',
                    '_vms_firepit_count',
                    '_vms_price_table',
                    '_vms_price_firepit',
                    '_vms_price_pool',
                    '_vms_min_tickets_per_table',
                    '_vms_min_tickets_per_firepit',
                );
            }
        }

        if (!function_exists('bvmgr_event_plan_ticketing_v2_config_meta_key')) {
            function bvmgr_event_plan_ticketing_v2_config_meta_key(): string
            {
                if (function_exists('bvmgr_ticketing_v2_k')) {
                    return (string) bvmgr_ticketing_v2_k('config');
                }
                if (function_exists('bvmgr_meta_key')) {
                    $key = (string) bvmgr_meta_key('event_plan', 'ticketing_config_v2');
                    if ($key !== '') {
                        return $key;
                    }
                }
                return '_vms_ticketing_config_v2';
            }
        }

        if (!function_exists('bvmgr_event_plan_has_ticketing_v2_config')) {
            function bvmgr_event_plan_has_ticketing_v2_config(int $plan_id): bool
            {
                $plan_id = absint($plan_id);
                if ($plan_id <= 0) {
                    return false;
                }

                $cfg_key = bvmgr_event_plan_ticketing_v2_config_meta_key();
                if (!metadata_exists('post', $plan_id, $cfg_key)) {
                    return false;
                }

                $raw = get_post_meta($plan_id, $cfg_key, true);
                return is_array($raw) && !empty($raw);
            }
        }

        if (!function_exists('bvmgr_event_plan_maybe_cleanup_legacy_ticket_meta_for_plan')) {
            /**
             * @return array{cleaned:bool,deleted_keys:int,template_applied:bool,reason:string}
             */
            function bvmgr_event_plan_maybe_cleanup_legacy_ticket_meta_for_plan(int $plan_id, bool $allow_apply_default_template = true): array
            {
                $plan_id = absint($plan_id);
                $result = array(
                    'cleaned' => false,
                    'deleted_keys' => 0,
                    'template_applied' => false,
                    'reason' => '',
                );
                if ($plan_id <= 0) {
                    $result['reason'] = 'invalid_plan_id';
                    return $result;
                }

                $present_legacy_keys = array();
                foreach (bvmgr_event_plan_legacy_ticket_meta_keys() as $legacy_key) {
                    $legacy_key = (string) $legacy_key;
                    if ($legacy_key === '') {
                        continue;
                    }
                    if (metadata_exists('post', $plan_id, $legacy_key)) {
                        $present_legacy_keys[] = $legacy_key;
                    }
                }
                if (empty($present_legacy_keys)) {
                    $result['reason'] = 'no_legacy_meta';
                    return $result;
                }

                $has_v2_config = bvmgr_event_plan_has_ticketing_v2_config($plan_id);
                if (!$has_v2_config && $allow_apply_default_template) {
                    if (
                        function_exists('bvmgr_ticketing_v2_get_default_template_id')
                        && function_exists('bvmgr_ticketing_v2_templates_apply_to_plan')
                    ) {
                        $template_id = (string) bvmgr_ticketing_v2_get_default_template_id();
                        if ($template_id !== '') {
                            $apply_result = bvmgr_ticketing_v2_templates_apply_to_plan($plan_id, $template_id);
                            if (!empty($apply_result['ok'])) {
                                $has_v2_config = bvmgr_event_plan_has_ticketing_v2_config($plan_id);
                                if ($has_v2_config) {
                                    $result['template_applied'] = true;
                                }
                            }
                        }
                    }
                }

                if (!$has_v2_config) {
                    $result['reason'] = 'no_v2_config';
                    return $result;
                }

                foreach ($present_legacy_keys as $legacy_key) {
                    delete_post_meta($plan_id, (string) $legacy_key);
                    $result['deleted_keys'] += 1;
                }
                $result['cleaned'] = true;
                $result['reason'] = 'cleaned';
                return $result;
            }
        }

		if (!function_exists('bvmgr_event_plan_legacy_ticket_meta_candidate_ids')) {
			function bvmgr_event_plan_legacy_ticket_meta_candidate_ids(int $after_id, int $limit): array
			{
				global $wpdb;

				$after_id = max(0, (int) $after_id);
				$limit = max(1, min(200, (int) $limit));

				$legacy_keys = array_values(array_filter(array_map('strval', bvmgr_event_plan_legacy_ticket_meta_keys())));
				if (empty($legacy_keys)) {
					return array();
				}

				$statuses = array('publish', 'private', 'draft', 'pending', 'future');
				$status_placeholders = implode(',', array_fill(0, count($statuses), '%s'));
				$key_placeholders = implode(',', array_fill(0, count($legacy_keys), '%s'));

				$sql = "
					SELECT DISTINCT p.ID
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
					WHERE p.post_type = 'vms_event_plan'
					  AND p.post_status IN ({$status_placeholders})
					  AND pm.meta_key IN ({$key_placeholders})
					  AND p.ID > %d
					ORDER BY p.ID ASC
					LIMIT %d
				";

				$params = array_merge($statuses, $legacy_keys, array($after_id, $limit));
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Legacy-ticket cleanup executes this immediately prepared ID batch and must read current metadata before deleting it.
				$rows = $wpdb->get_col($wpdb->prepare($sql, ...$params));
				if (!is_array($rows) || empty($rows)) {
					return array();
				}

				return array_values(array_filter(array_map('absint', $rows)));
			}
		}

		if (!function_exists('bvmgr_event_plan_cleanup_legacy_ticket_meta_once')) {
			function bvmgr_event_plan_cleanup_legacy_ticket_meta_once(): void
			{
				$is_cron = defined('DOING_CRON') && DOING_CRON;
				if (!$is_cron) {
					if (!is_admin() || !current_user_can('manage_options')) {
						return;
					}
                    if (defined('DOING_AJAX') && DOING_AJAX) {
                        return;
                    }
					if (!empty(bvmgr_event_plan_current_post_request())) {
						return;
					}
				}

				$target_version = defined('BVMGR_VERSION') ? (string) BVMGR_VERSION : 'dev';
				$marker_option = 'vms_event_plan_legacy_ticket_cleanup_version';
				$last_version = (string) get_option($marker_option, '');
				if ($last_version === $target_version) {
					return;
				}

				$lock_key = 'vms_event_plan_legacy_ticket_cleanup_lock_until';
				$lock_until = (int) get_option($lock_key, 0);
				$now_ts = time();
				if ($lock_until > $now_ts) {
					return;
				}
				update_option($lock_key, $now_ts + 120, false);

				$cursor_option = 'vms_event_plan_legacy_ticket_cleanup_cursor';
				$progress_option = 'vms_event_plan_legacy_ticket_cleanup_progress';
				$cursor = max(0, (int) get_option($cursor_option, 0));

				$summary = get_option($progress_option, array());
				if (!is_array($summary) || (($summary['version'] ?? '') !== $target_version)) {
					$summary = array(
						'version' => $target_version,
						'run_at_gmt' => gmdate('c'),
						'scanned' => 0,
						'cleaned_plans' => 0,
						'deleted_keys' => 0,
						'template_applied' => 0,
						'skipped_no_v2_config' => 0,
						'is_incremental' => true,
					);
				}

				$batch_size = max(5, min(80, (int) apply_filters('vms_event_plan_legacy_ticket_cleanup_batch_size', 20)));
				$max_seconds = max(1, min(6, (int) apply_filters('vms_event_plan_legacy_ticket_cleanup_max_seconds', 2)));
				$start = microtime(true);
				$completed = false;

				while ((microtime(true) - $start) < $max_seconds) {
					$ids = bvmgr_event_plan_legacy_ticket_meta_candidate_ids($cursor, $batch_size);
					if (empty($ids)) {
						update_option($marker_option, $target_version, false);
						$completed = true;
						$summary['completed_at_gmt'] = gmdate('c');
						update_option('vms_event_plan_legacy_ticket_cleanup_last_run', $summary, false);
						delete_option($cursor_option);
						delete_option($progress_option);
						break;
					}

					foreach ($ids as $plan_id) {
						$plan_id = absint($plan_id);
						if ($plan_id <= 0) {
							continue;
						}
						$cursor = max($cursor, $plan_id);
						$summary['scanned'] += 1;

						$result = bvmgr_event_plan_maybe_cleanup_legacy_ticket_meta_for_plan($plan_id, true);
						if (!empty($result['template_applied'])) {
							$summary['template_applied'] += 1;
						}
						if (!empty($result['cleaned'])) {
							$summary['cleaned_plans'] += 1;
							$summary['deleted_keys'] += (int) ($result['deleted_keys'] ?? 0);
						} elseif (($result['reason'] ?? '') === 'no_v2_config') {
							$summary['skipped_no_v2_config'] += 1;
						}
					}

					update_option($cursor_option, $cursor, false);
					update_option($progress_option, $summary, false);

					if (count($ids) < $batch_size) {
						continue;
					}
				}

				delete_option($lock_key);

				if (!$completed && function_exists('wp_schedule_single_event') && !wp_next_scheduled('vms_event_plan_legacy_ticket_cleanup')) {
					wp_schedule_single_event(time() + 300, 'vms_event_plan_legacy_ticket_cleanup');
				}
			}
		}

		if (!function_exists('bvmgr_event_plan_maybe_schedule_legacy_ticket_cleanup')) {
			function bvmgr_event_plan_maybe_schedule_legacy_ticket_cleanup(): void
			{
				if (defined('DOING_AJAX') && DOING_AJAX) {
					return;
				}

				// Never spend admin-editor CPU on migration cleanup during a save/publish request.
				$get_request = bvmgr_event_plan_current_get_request();
				if (!empty(bvmgr_event_plan_current_post_request()) || (isset($get_request['action'], $get_request['post']) && (string) $get_request['action'] === 'edit')) {
					return;
				}

				$target_version = defined('BVMGR_VERSION') ? (string) BVMGR_VERSION : 'dev';
				if ((string) get_option('vms_event_plan_legacy_ticket_cleanup_version', '') === $target_version) {
					return;
				}

				$guard = function_exists('bvmgr_admin_guard_begin')
					? bvmgr_admin_guard_begin('admin_init.event_plan_legacy_ticket_cleanup_schedule', array(
						'task' => 'event_plan_legacy_ticket_cleanup_schedule',
						'allow_action' => 'event_plan_legacy_ticket_cleanup_schedule',
						'lock_name' => 'event_plan_legacy_ticket_cleanup_schedule',
						'lock_ttl' => 90,
					))
					: true;
				if ($guard === false) {
					return;
				}

				$scheduled = false;
				try {
					if (!wp_next_scheduled('vms_event_plan_legacy_ticket_cleanup')) {
						$scheduled = (bool) wp_schedule_single_event(time() + 300, 'vms_event_plan_legacy_ticket_cleanup');
					}
				} finally {
					if (is_array($guard) && function_exists('bvmgr_admin_guard_finish')) {
						bvmgr_admin_guard_finish($guard, array('scheduled_cleanup' => $scheduled ? 1 : 0));
					}
				}
			}
		}
        add_action('admin_init', 'bvmgr_event_plan_maybe_schedule_legacy_ticket_cleanup', 40);
        add_action('vms_event_plan_legacy_ticket_cleanup', 'bvmgr_event_plan_cleanup_legacy_ticket_meta_once', 10, 0);

        /**
         * TEC Publish
         */
        if (!function_exists('bvmgr_event_plan_calendar_normalize_signature_data')) {
            function bvmgr_event_plan_calendar_normalize_signature_data($value) {
                if (is_array($value)) {
                    $normalized = array();
                    foreach ($value as $key => $item) {
                        $normalized[(string) $key] = bvmgr_event_plan_calendar_normalize_signature_data($item);
                    }
                    ksort($normalized);
                    return $normalized;
                }

                if (is_bool($value)) {
                    return $value ? 1 : 0;
                }

                if (is_scalar($value) || $value === null) {
                    return $value;
                }

                return (string) $value;
            }
        }

        if (!function_exists('bvmgr_event_plan_calendar_sync_signature')) {
            function bvmgr_event_plan_calendar_sync_signature(array $args): string
            {
                $payload = bvmgr_event_plan_calendar_normalize_signature_data($args);
                return hash('sha256', (string) wp_json_encode($payload));
            }
        }

        if (!function_exists('bvmgr_event_plan_schedule_calendar_maintenance')) {
            function bvmgr_event_plan_schedule_calendar_maintenance(int $plan_id, int $tec_event_id, string $reason = 'publish'): void
            {
                $plan_id = absint($plan_id);
                $tec_event_id = absint($tec_event_id);
                if ($plan_id <= 0 || $tec_event_id <= 0) {
                    return;
                }

                if (function_exists('bvmgr_event_plan_capture_actor_user_id')) {
                    bvmgr_event_plan_capture_actor_user_id($plan_id, (int) get_current_user_id(), 'calendar_maintenance_schedule');
                }

                if (function_exists('bvmgr_event_plan_has_effective_tickets') && !bvmgr_event_plan_has_effective_tickets($plan_id)) {
                    if (function_exists('bvmgr_event_plan_perf_log')) {
                        bvmgr_event_plan_perf_log(
                            'vms_event_plan_schedule_calendar_maintenance',
                            $plan_id,
                            array(
                                'job_name' => 'calendar_maintenance',
                                'linked_tec_event_id' => $tec_event_id,
                                'reason' => $reason,
                                'skipped' => 1,
                                'skip_reason' => 'no_effective_tickets',
                            )
                        );
                    }
                    return;
                }

                $hook = 'vms_event_plan_calendar_maintenance';
                $args = array($plan_id, $tec_event_id);
                $already_locked = function_exists('bvmgr_event_plan_perf_job_has_lock')
                    ? bvmgr_event_plan_perf_job_has_lock('calendar_maintenance', $plan_id)
                    : false;

                $already_scheduled = (bool) wp_next_scheduled($hook, $args);
                if (!$already_locked && !$already_scheduled) {
                    wp_schedule_single_event(time() + 90, $hook, $args);
                    if (function_exists('bvmgr_event_plan_perf_job_set_lock')) {
                        bvmgr_event_plan_perf_job_set_lock('calendar_maintenance', $plan_id, 'pending', 15 * MINUTE_IN_SECONDS);
                    }
                }

                $queue_reason = sanitize_key($reason);
                $queue_meta_skipped = false;
                $existing_queue_reason = sanitize_key((string) get_post_meta($plan_id, '_vms_calendar_maintenance_reason', true));
                $existing_queued_at = (int) get_post_meta($plan_id, '_vms_calendar_maintenance_queued_at', true);
                if (($already_locked || $already_scheduled) && $existing_queue_reason === $queue_reason && $existing_queued_at > 0) {
                    $queue_meta_skipped = true;
                } else {
                    update_post_meta($plan_id, '_vms_calendar_maintenance_queued_at', time());
                    update_post_meta($plan_id, '_vms_calendar_maintenance_reason', $queue_reason);
                }

                if (function_exists('bvmgr_event_plan_perf_log')) {
                    bvmgr_event_plan_perf_log(
                        'vms_event_plan_schedule_calendar_maintenance',
                        $plan_id,
                        array(
                            'job_name' => 'calendar_maintenance',
                            'linked_tec_event_id' => $tec_event_id,
                            'reason' => $reason,
                            'already_scheduled' => $already_scheduled ? 1 : 0,
                            'already_locked' => $already_locked ? 1 : 0,
                            'queue_meta_skipped' => $queue_meta_skipped ? 1 : 0,
                        )
                    );
                }
            }
        }

        if (!function_exists('bvmgr_event_plan_run_calendar_maintenance')) {
            function bvmgr_event_plan_run_calendar_maintenance(int $plan_id, int $tec_event_id): void
            {
                $plan_id = absint($plan_id);
                $tec_event_id = absint($tec_event_id);
                if ($plan_id <= 0 || $tec_event_id <= 0) {
                    return;
                }

                $trace = function_exists('bvmgr_event_plan_perf_span_start')
                    ? bvmgr_event_plan_perf_span_start(
                        'vms_event_plan_calendar_maintenance',
                        $plan_id,
                        array(
                            'job_name' => 'calendar_maintenance',
                            'linked_tec_event_id' => $tec_event_id,
                        )
                    )
                    : '';
                $lock = function_exists('bvmgr_event_plan_perf_job_get_lock')
                    ? bvmgr_event_plan_perf_job_get_lock('calendar_maintenance', $plan_id)
                    : array();
                if (($lock['state'] ?? '') === 'running') {
                    if (function_exists('bvmgr_event_plan_perf_log')) {
                        bvmgr_event_plan_perf_log(
                            'vms_event_plan_calendar_maintenance',
                            $plan_id,
                            array(
                                'job_name' => 'calendar_maintenance',
                                'linked_tec_event_id' => $tec_event_id,
                                'skipped' => 1,
                                'skip_reason' => 'job_already_running',
                            )
                        );
                    }
                    if (function_exists('bvmgr_event_plan_perf_span_finish')) {
                        bvmgr_event_plan_perf_span_finish('vms_event_plan_calendar_maintenance', $plan_id, $trace, array('job_name' => 'calendar_maintenance', 'linked_tec_event_id' => $tec_event_id, 'skipped' => 1));
                    }
                    return;
                }

                if (function_exists('bvmgr_event_plan_perf_job_set_lock')) {
                    bvmgr_event_plan_perf_job_set_lock('calendar_maintenance', $plan_id, 'running', 15 * MINUTE_IN_SECONDS);
                }

                try {
                    $tec_post = get_post($tec_event_id);
                    if (!$tec_post || $tec_post->post_type !== 'tribe_events') {
                        update_post_meta($plan_id, '_vms_calendar_maintenance_last_error', 'missing_tec_event');
                        return;
                    }

                    if (function_exists('bvmgr_event_plan_backfill_tec_event_author')) {
                        bvmgr_event_plan_backfill_tec_event_author($plan_id, $tec_event_id, 'vms_event_plan_calendar_maintenance');
                    }

                    if (function_exists('bvmgr_tec_sync_vendor_categories_from_plan')) {
                        bvmgr_tec_sync_vendor_categories_from_plan($plan_id, $tec_event_id);
                    }

                    update_post_meta($plan_id, '_vms_calendar_maintenance_last_run', time());
                    delete_post_meta($plan_id, '_vms_calendar_maintenance_last_error');
                } finally {
                    if (function_exists('bvmgr_event_plan_perf_job_clear_lock')) {
                        bvmgr_event_plan_perf_job_clear_lock('calendar_maintenance', $plan_id);
                    }
                    if (function_exists('bvmgr_event_plan_perf_span_finish')) {
                        bvmgr_event_plan_perf_span_finish(
                            'vms_event_plan_calendar_maintenance',
                            $plan_id,
                            $trace,
                            array(
                                'job_name' => 'calendar_maintenance',
                                'linked_tec_event_id' => $tec_event_id,
                            )
                        );
                    }
                }
            }
        }
        add_action('vms_event_plan_calendar_maintenance', 'bvmgr_event_plan_run_calendar_maintenance', 10, 2);

        if (!function_exists('bvmgr_event_plan_deferred_calendar_publish_enabled')) {
            function bvmgr_event_plan_deferred_calendar_publish_enabled(int $post_id = 0, string $reason = 'publish_now'): bool
            {
                $enabled = true;

                if (defined('VMS_EVENT_PLAN_DEFER_CALENDAR_PUBLISH') && VMS_EVENT_PLAN_DEFER_CALENDAR_PUBLISH === false) {
                    $enabled = false;
                }

                /**
                 * Allows a site owner/developer to force synchronous Event Plan calendar publishing.
                 * Default stays deferred because TEC writes can max shared-host CPU during editor saves.
                 */
                return (bool) apply_filters('vms_event_plan_defer_calendar_publish', $enabled, absint($post_id), sanitize_key($reason));
            }
        }

        if (!function_exists('bvmgr_event_plan_schedule_deferred_calendar_publish')) {
            function bvmgr_event_plan_schedule_deferred_calendar_publish(int $post_id, string $reason = 'publish_now'): bool
            {
                $post_id = absint($post_id);
                if ($post_id <= 0) {
                    return false;
                }

                $actor_user_id = function_exists('bvmgr_event_plan_capture_actor_user_id')
                    ? bvmgr_event_plan_capture_actor_user_id($post_id, (int) get_current_user_id(), 'deferred_calendar_publish_schedule')
                    : (int) get_current_user_id();

                $hook = 'vms_event_plan_deferred_calendar_publish';
                $args = array($post_id);
                $already_locked = function_exists('bvmgr_event_plan_perf_job_has_lock')
                    ? bvmgr_event_plan_perf_job_has_lock('calendar_publish', $post_id)
                    : false;

                $already_scheduled = (bool) wp_next_scheduled($hook, $args);
                if (!$already_locked && !$already_scheduled) {
                    wp_schedule_single_event(time() + 180, $hook, $args);
                    if (function_exists('bvmgr_event_plan_perf_job_set_lock')) {
                        bvmgr_event_plan_perf_job_set_lock('calendar_publish', $post_id, 'pending', 20 * MINUTE_IN_SECONDS);
                    }
                }

                $queue_reason = sanitize_key($reason);
                $queue_meta_skipped = false;
                $existing_queue_state = sanitize_key((string) get_post_meta($post_id, '_vms_calendar_publish_queue_state', true));
                $existing_queue_reason = sanitize_key((string) get_post_meta($post_id, '_vms_calendar_publish_queue_reason', true));
                $existing_queued_at = (int) get_post_meta($post_id, '_vms_calendar_publish_queued_at', true);
                if (($already_locked || $already_scheduled) && $existing_queue_state === 'queued' && $existing_queue_reason === $queue_reason && $existing_queued_at > 0) {
                    $queue_meta_skipped = true;
                } else {
                    update_post_meta($post_id, '_vms_calendar_publish_queue_state', 'queued');
                    update_post_meta($post_id, '_vms_calendar_publish_queued_at', time());
                    update_post_meta($post_id, '_vms_calendar_publish_queue_reason', $queue_reason);
                    delete_post_meta($post_id, '_vms_calendar_publish_last_error');
                }

                if (function_exists('bvmgr_event_plan_perf_log')) {
                    bvmgr_event_plan_perf_log(
                        'vms_event_plan_schedule_deferred_calendar_publish',
                        $post_id,
                        array(
                            'job_name' => 'calendar_publish',
                            'reason' => $reason,
                            'actor_user_id' => $actor_user_id,
                            'already_locked' => $already_locked ? 1 : 0,
                            'already_scheduled' => $already_scheduled ? 1 : 0,
                            'queue_meta_skipped' => $queue_meta_skipped ? 1 : 0,
                        )
                    );
                }

                return true;
            }
        }

        if (!function_exists('bvmgr_event_plan_run_deferred_calendar_publish')) {
            function bvmgr_event_plan_run_deferred_calendar_publish(int $post_id): void
            {
                $post_id = absint($post_id);
                if ($post_id <= 0) {
                    return;
                }

                $trace = function_exists('bvmgr_event_plan_perf_span_start')
                    ? bvmgr_event_plan_perf_span_start('vms_event_plan_deferred_calendar_publish', $post_id, array('job_name' => 'calendar_publish'))
                    : '';
                $lock = function_exists('bvmgr_event_plan_perf_job_get_lock')
                    ? bvmgr_event_plan_perf_job_get_lock('calendar_publish', $post_id)
                    : array();
                if (($lock['state'] ?? '') === 'running') {
                    if (function_exists('bvmgr_event_plan_perf_log')) {
                        bvmgr_event_plan_perf_log(
                            'vms_event_plan_deferred_calendar_publish',
                            $post_id,
                            array(
                                'job_name' => 'calendar_publish',
                                'skipped' => 1,
                                'skip_reason' => 'job_already_running',
                            )
                        );
                    }
                    if (function_exists('bvmgr_event_plan_perf_span_finish')) {
                        bvmgr_event_plan_perf_span_finish('vms_event_plan_deferred_calendar_publish', $post_id, $trace, array('job_name' => 'calendar_publish', 'skipped' => 1));
                    }
                    return;
                }

                if (function_exists('bvmgr_event_plan_perf_job_set_lock')) {
                    bvmgr_event_plan_perf_job_set_lock('calendar_publish', $post_id, 'running', 20 * MINUTE_IN_SECONDS);
                }

                try {
                    $post = get_post($post_id);
                    if (!$post || $post->post_type !== 'vms_event_plan') {
                        return;
                    }

                    if (function_exists('bvmgr_validate_event_plan')) {
                        $errors = bvmgr_validate_event_plan($post_id);
                        if (!empty($errors)) {
                            update_post_meta($post_id, '_vms_calendar_publish_queue_state', 'failed');
                            update_post_meta($post_id, '_vms_calendar_publish_last_error', implode(' ', array_map('sanitize_text_field', (array) $errors)));
                            return;
                        }
                    }

                    update_post_meta($post_id, '_vms_calendar_publish_queue_state', 'running');
                    update_post_meta($post_id, '_vms_calendar_publish_started_at', time());

                    $ok = bvmgr_publish_event_to_calendar($post_id, $post);
                    if ($ok) {
                        update_post_meta($post_id, '_vms_calendar_publish_queue_state', 'complete');
                        update_post_meta($post_id, '_vms_calendar_publish_completed_at', time());
                        delete_post_meta($post_id, '_vms_calendar_publish_last_error');
                    } else {
                        update_post_meta($post_id, '_vms_calendar_publish_queue_state', 'failed');
                        update_post_meta($post_id, '_vms_calendar_publish_last_error', 'deferred_calendar_publish_failed');
                    }
                } finally {
                    if (function_exists('bvmgr_event_plan_perf_job_clear_lock')) {
                        bvmgr_event_plan_perf_job_clear_lock('calendar_publish', $post_id);
                    }
                    if (function_exists('bvmgr_event_plan_perf_span_finish')) {
                        bvmgr_event_plan_perf_span_finish('vms_event_plan_deferred_calendar_publish', $post_id, $trace, array('job_name' => 'calendar_publish'));
                    }
                }
            }
        }
        add_action('vms_event_plan_deferred_calendar_publish', 'bvmgr_event_plan_run_deferred_calendar_publish', 10, 1);

        // Later featured-image edits do not rebuild the TEC event payload, so mirror plan thumbnails directly.
	        function bvmgr_event_plan_sync_linked_tec_featured_image(int $plan_id, int $tec_event_id = 0, string $source = ''): array
	        {
	            static $request_guard = array();

	            $plan_id = absint($plan_id);
	            $tec_event_id = absint($tec_event_id);
	            $source = sanitize_key($source);
	            $trace = function_exists('bvmgr_event_plan_perf_span_start')
	                ? bvmgr_event_plan_perf_span_start('event_plan_featured_image_sync', $plan_id, array(
	                    'section' => 'featured_image_sync',
	                    'linked_tec_event_id' => $tec_event_id,
	                    'source' => $source,
	                ))
	                : '';

            $result = array(
                'ok' => false,
                'updated' => false,
                'plan_id' => $plan_id,
                'tec_event_id' => 0,
                'plan_thumbnail_id' => 0,
                'tec_thumbnail_id' => 0,
                'reason' => '',
            );

            try {
                if ($plan_id <= 0 || get_post_type($plan_id) !== 'vms_event_plan') {
                    $result['reason'] = 'invalid_plan';
                    return $result;
                }

                if ($tec_event_id <= 0) {
                    if (function_exists('bvmgr_get_plan_tec_event_id')) {
                        $tec_event_id = absint(bvmgr_get_plan_tec_event_id($plan_id));
                    }
                    if ($tec_event_id <= 0) {
                        $tec_key_id = function_exists('bvmgr_meta_key')
                            ? (bvmgr_meta_key('event_plan', 'tec_event_id') ?: '_vms_tec_event_id')
                            : '_vms_tec_event_id';
                        $tec_event_id = absint(get_post_meta($plan_id, $tec_key_id, true));
                    }
                }

                $plan_thumb_id = absint(get_post_thumbnail_id($plan_id));
                $result['plan_thumbnail_id'] = $plan_thumb_id;

                $guard_key = implode(':', array(
                    $plan_id,
                    $tec_event_id,
                    $plan_thumb_id,
                ));
                if (isset($request_guard[$guard_key])) {
                    $result['tec_event_id'] = $tec_event_id;
                    $result['reason'] = 'request_guard_duplicate';
                    return $result;
                }

                $result['tec_event_id'] = $tec_event_id;

                if ($tec_event_id <= 0 || get_post_type($tec_event_id) !== 'tribe_events') {
                    $request_guard[$guard_key] = true;
                    $result['reason'] = 'missing_linked_tec_event';
                    return $result;
                }

                if ($plan_thumb_id <= 0) {
                    $request_guard[$guard_key] = true;
                    $result['reason'] = 'plan_thumbnail_missing';
                    return $result;
                }

                $current_tec_thumb_id = absint(get_post_thumbnail_id($tec_event_id));
                $result['tec_thumbnail_id'] = $current_tec_thumb_id;

                if ($current_tec_thumb_id === $plan_thumb_id) {
                    $request_guard[$guard_key] = true;
                    $result['ok'] = true;
                    $result['reason'] = 'already_synced';
                    return $result;
                }

                set_post_thumbnail($tec_event_id, $plan_thumb_id);

                $updated_tec_thumb_id = absint(get_post_thumbnail_id($tec_event_id));
                $result['tec_thumbnail_id'] = $updated_tec_thumb_id;
                $result['updated'] = ($updated_tec_thumb_id === $plan_thumb_id);
                $result['ok'] = $result['updated'];
                $result['reason'] = $result['updated'] ? 'updated' : 'set_post_thumbnail_failed';
                if ($result['updated']) {
                    $request_guard[$guard_key] = true;
                }

                return $result;
	            } finally {
	                if (function_exists('bvmgr_event_plan_save_profiler_track_featured_image_sync')) {
	                    bvmgr_event_plan_save_profiler_track_featured_image_sync($source, $result);
	                }
	                if (function_exists('bvmgr_event_plan_perf_span_finish')) {
	                    bvmgr_event_plan_perf_span_finish('event_plan_featured_image_sync', $plan_id, $trace, array(
	                        'section' => 'featured_image_sync',
	                        'source' => $source,
	                        'linked_tec_event_id' => (int) ($result['tec_event_id'] ?? 0),
	                        'plan_thumbnail_id' => (int) ($result['plan_thumbnail_id'] ?? 0),
	                        'tec_thumbnail_id' => (int) ($result['tec_thumbnail_id'] ?? 0),
                        'updated' => !empty($result['updated']) ? 1 : 0,
                        'ok' => !empty($result['ok']) ? 1 : 0,
                        'reason' => sanitize_key((string) ($result['reason'] ?? '')),
                    ));
                }
            }
        }

        function bvmgr_event_plan_maybe_sync_tec_featured_image_from_thumbnail_meta($meta_id, $object_id, $meta_key, $meta_value): void
        {
            unset($meta_id, $meta_value);

            if ($meta_key !== '_thumbnail_id') {
                return;
            }

            $plan_id = absint($object_id);
            if ($plan_id <= 0 || get_post_type($plan_id) !== 'vms_event_plan') {
                return;
            }

	            if (function_exists('bvmgr_event_plan_save_profiler_force_effective_meta_key_for_post')) {
	                bvmgr_event_plan_save_profiler_force_effective_meta_key_for_post($plan_id, '_thumbnail_id');
	            }

	            bvmgr_event_plan_sync_linked_tec_featured_image($plan_id, 0, 'thumbnail_meta');
	        }
        add_action('added_post_meta', 'bvmgr_event_plan_maybe_sync_tec_featured_image_from_thumbnail_meta', 10, 4);
        add_action('updated_post_meta', 'bvmgr_event_plan_maybe_sync_tec_featured_image_from_thumbnail_meta', 10, 4);

        function bvmgr_event_plan_sync_linked_tec_featured_image_on_save(int $post_id, WP_Post $post, bool $update): void
        {
            unset($update);

            if ($post->post_type !== 'vms_event_plan') {
                return;
            }

            if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id)) {
                return;
            }

            if (sanitize_key((string) $post->post_status) === 'auto-draft') {
                return;
            }

	            if (
	                function_exists('bvmgr_event_plan_save_profiler_featured_image_sync_completed')
	                && bvmgr_event_plan_save_profiler_featured_image_sync_completed($post_id)
	            ) {
	                if (function_exists('bvmgr_event_plan_save_profiler_note_heavy_action')) {
	                    bvmgr_event_plan_save_profiler_note_heavy_action('featured_image_sync_save_post', 'skipped', 'already_completed');
	                }
	                return;
	            }

	            bvmgr_event_plan_sync_linked_tec_featured_image($post_id, 0, 'save_post');
	        }
        add_action('save_post_vms_event_plan', 'bvmgr_event_plan_sync_linked_tec_featured_image_on_save', 50, 3);

        /**
         * Keep native ticket sale windows aligned after a pre-closure occurrence change.
         *
         * Completed occurrences are deliberately not reopened here. A separate,
         * explicit Reschedule workflow must own that exceptional transition.
         */
        function bvmgr_event_plan_sync_ticket_windows_after_calendar_change(
            int $post_id,
            int $tec_event_id,
            bool $occurrence_changed,
            bool $event_was_closed
        ): bool {
            if (!$occurrence_changed || !function_exists('bvmgr_ticketing_v2_sync_mapped_ticket_sales_windows_for_calendar_change')) {
                return true;
            }

            $result = bvmgr_ticketing_v2_sync_mapped_ticket_sales_windows_for_calendar_change(
                $post_id,
                $tec_event_id,
                $event_was_closed
            );

            if (!empty($result['skipped']) && (string) ($result['reason'] ?? '') === 'completed_event_not_reopened') {
                update_post_meta($post_id, '_vms_ticketing_reschedule_required_v1', array(
                    'tec_event_id' => absint($tec_event_id),
                    'recorded_at' => time(),
                    'reason' => 'completed_occurrence_changed',
                ));
                bvmgr_add_admin_notice(
                    __('The calendar occurrence was updated, but VMS did not reopen ticket sale windows because the previous occurrence had already completed. Use the future explicit Reschedule workflow for an event that did not actually occur.', 'backstage-venue-manager'),
                    'warning'
                );
                return true;
            }

            if (empty($result['ok'])) {
                bvmgr_add_admin_notice(
                    __('The calendar occurrence updated, but one or more mapped ticket sale windows could not be synchronized. Review ticket mappings before relying on the public sale state.', 'backstage-venue-manager'),
                    'error'
                );
                return false;
            }

            if (empty($result['skipped'])) {
                delete_post_meta($post_id, '_vms_ticketing_reschedule_required_v1');
            }

            return true;
        }

        function bvmgr_publish_event_to_calendar(int $post_id, WP_Post $post): bool
        {
            $trace = function_exists('bvmgr_event_plan_perf_span_start')
                ? bvmgr_event_plan_perf_span_start('vms_publish_event_to_calendar', $post_id, array('job_name' => 'calendar_publish'))
                : '';

            try {
                if (function_exists('bvmgr_event_plan_capture_actor_user_id')) {
                    bvmgr_event_plan_capture_actor_user_id($post_id, (int) get_current_user_id(), 'publish_event_to_calendar');
                }

                if (!function_exists('tribe_create_event') || !function_exists('tribe_update_event')) {
                    bvmgr_add_admin_notice(__('The Events Calendar functions are not available. Is the plugin active?', 'backstage-venue-manager'), 'error');
                    return false;
                }

                $tec_key_id  = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'tec_event_id') ?: '_vms_tec_event_id') : '_vms_tec_event_id';
                $tec_key_url = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'tec_event_url') ?: '_vms_tec_event_url') : '_vms_tec_event_url';

                $existing_tec_id = (int) get_post_meta($post_id, $tec_key_id, true);
                $tec_event_id = 0;
                $calendar_occurrence_changed = false;
                $existing_event_was_closed = false;

                if ($existing_tec_id > 0 && function_exists('bvmgr_ticketing_v2_plan_calendar_alignment')) {
                    $alignment_before = bvmgr_ticketing_v2_plan_calendar_alignment($post_id, $existing_tec_id);
                    $calendar_occurrence_changed = empty($alignment_before['checkable']) || empty($alignment_before['aligned']);
                }
                if ($existing_tec_id > 0 && function_exists('bvmgr_ticketing_v2_calendar_event_was_closed')) {
                    $existing_event_was_closed = bvmgr_ticketing_v2_calendar_event_was_closed($existing_tec_id);
                }

                $args = bvmgr_build_tec_event_args($post_id, $existing_tec_id);
                if (empty($args)) {
                    bvmgr_add_admin_notice(__('Unable to build event data for The Events Calendar.', 'backstage-venue-manager'), 'error');
                    return false;
                }

                if (function_exists('bvmgr_event_plan_apply_tec_author_args')) {
                    $args = bvmgr_event_plan_apply_tec_author_args($post_id, $args, $existing_tec_id, 'vms_publish_event_to_calendar');
                }

                $sync_signature = function_exists('bvmgr_event_plan_calendar_sync_signature')
                    ? bvmgr_event_plan_calendar_sync_signature($args)
                    : '';
                $signature_key = '_vms_tec_last_sync_signature';

                if ($existing_tec_id > 0) {
                    $plan_thumb = absint(get_post_thumbnail_id($post_id));
                    $tec_thumb  = absint(get_post_thumbnail_id($existing_tec_id));
                    if ($plan_thumb <= 0 && $tec_thumb > 0 && isset($args['FeaturedImage'])) {
                        unset($args['FeaturedImage']);
                    }

                    $existing_tec_post = get_post($existing_tec_id);
                    $last_signature = (string) get_post_meta($post_id, $signature_key, true);
                    if (
                        $existing_tec_post
                        && $existing_tec_post->post_type === 'tribe_events'
                        && $existing_tec_post->post_status !== 'trash'
                        && $sync_signature !== ''
                        && hash_equals($sync_signature, $last_signature)
                        && !$calendar_occurrence_changed
                    ) {
                        $tec_event_id = $existing_tec_id;
                    } else {
                        $updated_id = tribe_update_event($existing_tec_id, $args);
                        if ($updated_id && !is_wp_error($updated_id)) {
                            $tec_event_id = (int) $updated_id;
                        } else {
                            bvmgr_add_admin_notice(__('Failed to update existing Events Calendar event. Will attempt to create a new one.', 'backstage-venue-manager'), 'error');
                        }
                    }
                }

                if (!$tec_event_id) {
                    $created_id = tribe_create_event($args);
                    if ($created_id && !is_wp_error($created_id)) {
                        $tec_event_id = (int) $created_id;
                        update_post_meta($post_id, $tec_key_id, $tec_event_id);
                    } else {
                        bvmgr_add_admin_notice(__('Failed to create event in The Events Calendar.', 'backstage-venue-manager'), 'error');
                        return false;
                    }
                }

                if (function_exists('bvmgr_event_plan_backfill_tec_event_author')) {
                    bvmgr_event_plan_backfill_tec_event_author($post_id, $tec_event_id, 'vms_publish_event_to_calendar');
                }

				if (function_exists('bvmgr_tec_finalize_event_url_sync')) {
					bvmgr_tec_finalize_event_url_sync($tec_event_id, $args);
				}

                if ($sync_signature !== '') {
                    update_post_meta($post_id, $signature_key, $sync_signature);
                    update_post_meta($post_id, '_vms_tec_last_sync_at', time());
                }

                delete_post_meta($tec_event_id, '_EventOrganizerID');

	                if (function_exists('bvmgr_event_plan_sync_linked_tec_featured_image')) {
	                    bvmgr_event_plan_sync_linked_tec_featured_image($post_id, $tec_event_id, 'publish_to_calendar');
	                }

                $tec_permalink = get_permalink($tec_event_id);
                if ($tec_permalink) {
                    update_post_meta($post_id, $tec_key_url, esc_url_raw($tec_permalink));
                }

                if (function_exists('bvmgr_event_plan_sync_checkin_close_meta_to_tec')) {
                    bvmgr_event_plan_sync_checkin_close_meta_to_tec($post_id, $tec_event_id);
                }

                if (!bvmgr_event_plan_sync_ticket_windows_after_calendar_change(
                    $post_id,
                    $tec_event_id,
                    $calendar_occurrence_changed,
                    $existing_event_was_closed
                )) {
                    return false;
                }

                if (function_exists('bvmgr_event_plan_schedule_calendar_maintenance')) {
                    bvmgr_event_plan_schedule_calendar_maintenance($post_id, $tec_event_id, 'publish_now');
                }

                return true;
            } finally {
                if (function_exists('bvmgr_event_plan_perf_span_finish')) {
                    bvmgr_event_plan_perf_span_finish('vms_publish_event_to_calendar', $post_id, $trace, array('job_name' => 'calendar_publish'));
                }
            }
        }

        /**
         * Re-sync an existing TEC event from this Event Plan without creating a new event.
         * This is used by the “Re-sync to Calendar” button once a plan is already linked to TEC.
         */
        function bvmgr_resync_event_to_calendar(int $post_id, WP_Post $post, int $existing_tec_id): bool
        {
            $trace = function_exists('bvmgr_event_plan_perf_span_start')
                ? bvmgr_event_plan_perf_span_start('vms_resync_event_to_calendar', $post_id, array('job_name' => 'calendar_resync', 'linked_tec_event_id' => $existing_tec_id))
                : '';

            try {
                if ($existing_tec_id <= 0) {
                    return false;
                }

                if (function_exists('bvmgr_event_plan_capture_actor_user_id')) {
                    bvmgr_event_plan_capture_actor_user_id($post_id, (int) get_current_user_id(), 'resync_event_to_calendar');
                }

                if (!function_exists('tribe_update_event')) {
                    if (function_exists('bvmgr_record_operational_issue')) {
                        bvmgr_record_operational_issue('event_plan_tec_provider_unavailable', array(
                            'service' => 'the_events_calendar',
                            'operation' => 'resync_event',
                            'status' => 'unavailable',
                            'plan_id' => $post_id,
                            'event_id' => $existing_tec_id,
                        ));
                    }
                    return false;
                }

                $args = bvmgr_build_tec_event_args($post_id, $existing_tec_id);
                if (empty($args)) {
                    return false;
                }

                if (function_exists('bvmgr_event_plan_apply_tec_author_args')) {
                    $args = bvmgr_event_plan_apply_tec_author_args($post_id, $args, $existing_tec_id, 'vms_resync_event_to_calendar');
                }

                $calendar_occurrence_changed = true;
                $existing_event_was_closed = false;
                if (function_exists('bvmgr_ticketing_v2_plan_calendar_alignment')) {
                    $alignment_before = bvmgr_ticketing_v2_plan_calendar_alignment($post_id, $existing_tec_id);
                    $calendar_occurrence_changed = empty($alignment_before['checkable']) || empty($alignment_before['aligned']);
                }
                if (function_exists('bvmgr_ticketing_v2_calendar_event_was_closed')) {
                    $existing_event_was_closed = bvmgr_ticketing_v2_calendar_event_was_closed($existing_tec_id);
                }

                $plan_thumb = absint(get_post_thumbnail_id($post_id));
                $tec_thumb  = absint(get_post_thumbnail_id($existing_tec_id));
                if ($plan_thumb <= 0 && $tec_thumb > 0 && isset($args['FeaturedImage'])) {
                    unset($args['FeaturedImage']);
                }

                $updated_id = tribe_update_event($existing_tec_id, $args);
                if (!$updated_id || is_wp_error($updated_id)) {
                    if (function_exists('bvmgr_record_operational_issue')) {
                        bvmgr_record_operational_issue('event_plan_tec_resync_failed', array(
                            'service' => 'the_events_calendar',
                            'operation' => 'resync_event',
                            'status' => 'failed',
                            'plan_id' => $post_id,
                            'event_id' => $existing_tec_id,
                        ), is_wp_error($updated_id) ? $updated_id : 'tribe_update_event_failed');
                    }
                    return false;
                }

                $tec_event_id = (int) $updated_id;

                if (function_exists('bvmgr_event_plan_backfill_tec_event_author')) {
                    bvmgr_event_plan_backfill_tec_event_author($post_id, $tec_event_id, 'vms_resync_event_to_calendar');
                }

				if (function_exists('bvmgr_tec_finalize_event_url_sync')) {
					bvmgr_tec_finalize_event_url_sync($tec_event_id, $args);
				}

                delete_post_meta($tec_event_id, '_EventOrganizerID');

	                if (function_exists('bvmgr_event_plan_sync_linked_tec_featured_image')) {
	                    bvmgr_event_plan_sync_linked_tec_featured_image($post_id, $tec_event_id, 'resync_to_calendar');
	                }

                $tec_key_url = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'tec_event_url') : '_vms_tec_event_url';
                $tec_permalink = get_permalink($tec_event_id);
                if ($tec_permalink) {
                    update_post_meta($post_id, $tec_key_url, esc_url_raw($tec_permalink));
                }

                if (function_exists('bvmgr_event_plan_calendar_sync_signature')) {
                    update_post_meta($post_id, '_vms_tec_last_sync_signature', bvmgr_event_plan_calendar_sync_signature($args));
                    update_post_meta($post_id, '_vms_tec_last_sync_at', time());
                }

                if (function_exists('bvmgr_event_plan_sync_checkin_close_meta_to_tec')) {
                    bvmgr_event_plan_sync_checkin_close_meta_to_tec($post_id, $tec_event_id);
                }

                if (!bvmgr_event_plan_sync_ticket_windows_after_calendar_change(
                    $post_id,
                    $tec_event_id,
                    $calendar_occurrence_changed,
                    $existing_event_was_closed
                )) {
                    return false;
                }

                if (function_exists('bvmgr_event_plan_schedule_calendar_maintenance')) {
                    bvmgr_event_plan_schedule_calendar_maintenance($post_id, $tec_event_id, 'resync_to_calendar');
                }

                return true;
            } finally {
                if (function_exists('bvmgr_event_plan_perf_span_finish')) {
                    bvmgr_event_plan_perf_span_finish('vms_resync_event_to_calendar', $post_id, $trace, array('job_name' => 'calendar_resync', 'linked_tec_event_id' => $existing_tec_event_id));
                }
            }
        }
        /**
         * Resolve which featured image ID (if any) should be sent in TEC payload args.
         *
         * Return semantics:
         * - >0 : send this attachment ID in `FeaturedImage`.
         * -  0 : do not send `FeaturedImage` (preserve existing image or keep empty).
         */
        function bvmgr_tec_resolve_featured_image_arg(int $plan_thumb_id, int $existing_tec_thumb_id, int $vendor_thumb_id): int
        {
            $plan_thumb_id = absint($plan_thumb_id);
            $existing_tec_thumb_id = absint($existing_tec_thumb_id);
            $vendor_thumb_id = absint($vendor_thumb_id);

            if ($plan_thumb_id > 0) {
                return $plan_thumb_id;
            }
            if ($existing_tec_thumb_id > 0) {
                return 0;
            }
            if ($vendor_thumb_id > 0) {
                return $vendor_thumb_id;
            }
            return 0;
        }

        function bvmgr_build_tec_event_args(int $post_id, int $existing_tec_id = 0): array {

            $post = get_post($post_id);
            if (!$post || $post->post_type !== 'vms_event_plan') {
                return array();
            }

            $event_date = (string) get_post_meta($post_id, '_vms_event_date', true);
            if ($event_date === '') {
                return array();
            }

            // Prefer stored times when available; use sane defaults so TEC always gets a time.
            $start_time = (string) get_post_meta($post_id, '_vms_start_time', true);
            $end_time   = (string) get_post_meta($post_id, '_vms_end_time', true);

            if ($start_time === '') $start_time = '19:00';
            if ($end_time === '')   $end_time   = '22:00';

            $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');

            $start_dt = DateTime::createFromFormat('Y-m-d H:i', $event_date . ' ' . $start_time, $tz);
            $end_dt   = DateTime::createFromFormat('Y-m-d H:i', $event_date . ' ' . $end_time, $tz);

            // If the end time is earlier than the start, assume the event crosses midnight.
            if ($start_dt && $end_dt && $end_dt->getTimestamp() <= $start_dt->getTimestamp()) {
                $end_dt->modify('+1 day');
            }

            $start_date = $event_date;
            $end_date   = $end_dt ? $end_dt->format('Y-m-d') : $event_date;

            $start_time_out = $start_dt ? $start_dt->format('H:i') : $start_time;
            $end_time_out   = $end_dt ? $end_dt->format('H:i') : $end_time;

            $band_id   = (int) get_post_meta($post_id, '_vms_band_vendor_id', true);
            $band_name = $band_id ? (string) get_the_title($band_id) : '';

            // TEC title: prefer the Event Plan title (operator-controlled). Fall back to performer name.
            $title = trim((string) $post->post_title);
            if ($title === '') {
                $title = $band_name !== '' ? $band_name : ('Event Plan #' . (int) $post_id);
            }

            // TEC description: use the canonical Event Plan editor content. (No auto-fallback to titles.)
            $content = trim((string) $post->post_content);

            $args = array(
                'post_title'       => $title,
                'post_status'      => 'publish',

                // TEC time handling: provide both date + time keys for best compatibility.
                'EventStartDate'   => $start_date,
                'EventEndDate'     => $end_date,
                'EventStartTime'   => $start_time_out,
                'EventEndTime'     => $end_time_out,
                'EventAllDay'      => false,
            );

            // Safety: never overwrite an existing TEC event description with blank content.
            // (Leaving the VMS plan editor empty should not erase the public event page.)
            if ($content !== '') {
                $args['post_content'] = $content;
            }

            // Featured image policy:
            // - Prefer Event Plan featured image.
            // - If plan image is missing and linked TEC event already has an image, preserve TEC image.
            // - Only then fallback to vendor image.
            $plan_thumb_id = absint(get_post_thumbnail_id($post_id));
            $existing_tec_thumb_id = ($existing_tec_id > 0) ? absint(get_post_thumbnail_id($existing_tec_id)) : 0;
            $vendor_thumb_id = ($band_id > 0) ? absint(get_post_thumbnail_id($band_id)) : 0;
            $featured_image_id = function_exists('bvmgr_tec_resolve_featured_image_arg')
                ? (int) bvmgr_tec_resolve_featured_image_arg($plan_thumb_id, $existing_tec_thumb_id, $vendor_thumb_id)
                : 0;
            if ($featured_image_id > 0) {
                $args['FeaturedImage'] = $featured_image_id;
            }

            // TEC Venue mapping (VMS venue → TEC venue).
            $tec_venue_id = bvmgr_tec_get_tec_venue_id_for_plan($post_id);
            if ($tec_venue_id) {
                $args['Venue'] = array('VenueID' => $tec_venue_id);
            }

            // Organizer: do NOT auto-map the performer as the organizer.
            // (We can add a proper "Default Organizer" setting later.)
            // $organizer_id = vms_tec_get_tec_organizer_id_for_plan($post_id);

            // TEC Event Website is distinct from the ticket-purchase destination.
            // Preserve independent website data and only set/clear values VMS owns.
            foreach (bvmgr_tec_build_event_url_args($post_id, $existing_tec_id) as $event_url_key => $event_url_value) {
                $args[$event_url_key] = $event_url_value;
            }

            // Cost (optional).
            $cost = bvmgr_tec_build_event_cost_string($post_id);
            if ($cost !== '') {
                $args['EventCost'] = $cost;
			} elseif (function_exists('bvmgr_event_plan_is_externally_ticketed') && bvmgr_event_plan_is_externally_ticketed($post_id)) {
				// An explicit empty collection makes the ownership intent clear to TEC;
				// the late event-cost filter also prevents Event Tickets from reinjecting preserved native prices.
				$args['EventCost'] = array();
            }

            return $args;
        }



        function bvmgr_event_plan_collect_vendor_category_snapshot(int $plan_id): array {

            $plan_id = absint($plan_id);
            if ($plan_id <= 0) {
                return array(
                    'term_ids' => array(),
                    'term_names' => array(),
                    'term_slugs' => array(),
                    'vendors' => array(),
                );
            }

            $k_band = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'band_vendor_id') ?: '_vms_band_vendor_id') : '_vms_band_vendor_id';
            $band_id = (int) get_post_meta($plan_id, $k_band, true);
            $secondary_assignments = function_exists('bvmgr_event_plan_get_secondary_vendor_assignments')
                ? (array) bvmgr_event_plan_get_secondary_vendor_assignments($plan_id, array(
                    'primary_vendor_id' => $band_id,
                ))
                : array();

            $snapshot = array(
                'term_ids' => array(),
                'term_names' => array(),
                'term_slugs' => array(),
                'vendors' => array(),
            );

            $vendors = array();
            if ($band_id > 0) {
                $vendors[] = array(
                    'vendor_id' => $band_id,
                    'source' => 'primary',
                    'source_label' => __('Primary Vendor', 'backstage-venue-manager'),
                    'type_slug' => function_exists('bvmgr_vendor_primary_type_slug') ? bvmgr_vendor_primary_type_slug($band_id) : '',
                );
            }

            foreach ($secondary_assignments as $type_slug => $assignment) {
                $type_slug = sanitize_title((string) $type_slug);
                $source_label = function_exists('bvmgr_vendor_type_label')
                    ? (string) bvmgr_vendor_type_label($type_slug)
                    : ucwords(str_replace(array('_', '-'), ' ', $type_slug));
                foreach ((array) ($assignment['vendor_ids'] ?? array()) as $secondary_id) {
                    $secondary_id = absint($secondary_id);
                    if ($secondary_id <= 0) {
                        continue;
                    }

                    $vendors[] = array(
                        'vendor_id' => $secondary_id,
                        'source' => 'secondary',
                        'source_label' => $source_label,
                        'type_slug' => $type_slug,
                    );
                }
            }

            foreach ($vendors as $vendor_row) {
                $vendor_id = absint($vendor_row['vendor_id'] ?? 0);
                if ($vendor_id <= 0 || get_post_type($vendor_id) !== 'vms_vendor') {
                    continue;
                }

                $type_slug = sanitize_title((string) ($vendor_row['type_slug'] ?? ''));
                if ($type_slug === '' && function_exists('bvmgr_vendor_primary_type_slug')) {
                    $type_slug = bvmgr_vendor_primary_type_slug($vendor_id);
                }

                $category_label = function_exists('bvmgr_vendor_category_label_for_type')
                    ? bvmgr_vendor_category_label_for_type($type_slug)
                    : __('Category', 'backstage-venue-manager');

                $terms = function_exists('bvmgr_vendor_get_category_terms')
                    ? bvmgr_vendor_get_category_terms($vendor_id)
                    : wp_get_post_terms($vendor_id, 'vms_vendor_category');
                if (is_wp_error($terms) || !is_array($terms)) {
                    $terms = array();
                }

                $row_term_ids = array();
                $row_term_names = array();
                $row_term_slugs = array();
                foreach ($terms as $term) {
                    if (!$term instanceof WP_Term) {
                        continue;
                    }
                    $term_id = absint($term->term_id);
                    $term_name = trim((string) $term->name);
                    $term_slug = sanitize_title((string) $term->slug);
                    if ($term_id <= 0 || $term_name === '') {
                        continue;
                    }
                    $row_term_ids[] = $term_id;
                    $row_term_names[] = $term_name;
                    if ($term_slug !== '') {
                        $row_term_slugs[] = $term_slug;
                    }
                    $snapshot['term_ids'][] = $term_id;
                    $snapshot['term_names'][] = $term_name;
                    if ($term_slug !== '') {
                        $snapshot['term_slugs'][] = $term_slug;
                    }
                }

                $snapshot['vendors'][] = array(
                    'vendor_id' => $vendor_id,
                    'vendor_title' => (string) get_the_title($vendor_id),
                    'source' => (string) ($vendor_row['source'] ?? ''),
                    'source_label' => (string) ($vendor_row['source_label'] ?? __('Vendor', 'backstage-venue-manager')),
                    'type_slug' => $type_slug,
                    'category_label' => $category_label,
                    'term_ids' => array_values(array_unique(array_filter(array_map('absint', $row_term_ids)))),
                    'term_names' => array_values(array_unique(array_filter(array_map('strval', $row_term_names)))),
                    'term_slugs' => array_values(array_unique(array_filter(array_map('strval', $row_term_slugs)))),
                );
            }

            $snapshot['term_ids'] = array_values(array_unique(array_filter(array_map('absint', (array) $snapshot['term_ids']))));
            $snapshot['term_names'] = array_values(array_unique(array_filter(array_map('strval', (array) $snapshot['term_names']))));
            $snapshot['term_slugs'] = array_values(array_unique(array_filter(array_map('strval', (array) $snapshot['term_slugs']))));

            return $snapshot;
        }

        function bvmgr_event_plan_update_vendor_category_snapshot(int $plan_id): array {

            $plan_id = absint($plan_id);
            if ($plan_id <= 0) {
                return array();
            }

            $snapshot = bvmgr_event_plan_collect_vendor_category_snapshot($plan_id);
            $k_term_ids = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'vendor_category_term_ids') ?: '_vms_vendor_category_term_ids') : '_vms_vendor_category_term_ids';
            $k_names = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'vendor_category_names') ?: '_vms_vendor_category_names') : '_vms_vendor_category_names';
            $k_snapshot = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'vendor_category_snapshot') ?: '_vms_vendor_category_snapshot') : '_vms_vendor_category_snapshot';

            if (!empty($snapshot['term_ids'])) {
                update_post_meta($plan_id, $k_term_ids, array_values($snapshot['term_ids']));
            } else {
                delete_post_meta($plan_id, $k_term_ids);
            }

            if (!empty($snapshot['term_names'])) {
                update_post_meta($plan_id, $k_names, array_values($snapshot['term_names']));
            } else {
                delete_post_meta($plan_id, $k_names);
            }

            if (!empty($snapshot['vendors']) || !empty($snapshot['term_ids'])) {
                update_post_meta($plan_id, $k_snapshot, $snapshot);
            } else {
                delete_post_meta($plan_id, $k_snapshot);
            }

            return $snapshot;
        }

        function bvmgr_tec_sync_vendor_categories_from_plan(int $plan_id, int $tec_event_id): void {

            $plan_id = absint($plan_id);
            $tec_event_id = absint($tec_event_id);
            if ($plan_id <= 0 || $tec_event_id <= 0) {
                return;
            }
            if (!taxonomy_exists('tribe_events_cat')) {
                return;
            }
            $tec_post = get_post($tec_event_id);
            if (!$tec_post || $tec_post->post_type !== 'tribe_events') {
                return;
            }

            $snapshot = bvmgr_event_plan_update_vendor_category_snapshot($plan_id);
            $vendor_terms = isset($snapshot['term_names']) && is_array($snapshot['term_names']) ? $snapshot['term_names'] : array();
            $managed_meta_key = '_vms_synced_vendor_category_term_ids';
            $previous_managed_ids = get_post_meta($tec_event_id, $managed_meta_key, true);
            if (!is_array($previous_managed_ids)) {
                $previous_managed_ids = array();
            }
            $previous_managed_ids = array_values(array_unique(array_filter(array_map('absint', $previous_managed_ids), function ($value) {
                return $value > 0;
            })));

            $resolved_tec_term_ids = array();
            foreach ((array) $vendor_terms as $vendor_term_name) {
                $vendor_term_name = trim((string) $vendor_term_name);
                if ($vendor_term_name === '') {
                    continue;
                }

                $slug = sanitize_title($vendor_term_name);
                $tec_term = null;
                if ($slug !== '') {
                    $tec_term = get_term_by('slug', $slug, 'tribe_events_cat');
                }
                if ((!$tec_term || is_wp_error($tec_term)) && $vendor_term_name !== '') {
                    $tec_term = get_term_by('name', $vendor_term_name, 'tribe_events_cat');
                }
                if (!$tec_term || is_wp_error($tec_term)) {
                    $created = wp_insert_term($vendor_term_name, 'tribe_events_cat', $slug !== '' ? array('slug' => $slug) : array());
                    if (is_wp_error($created)) {
                        continue;
                    }
                    $term_id = absint($created['term_id'] ?? 0);
                } else {
                    $term_id = absint($tec_term->term_id);
                }

                if ($term_id > 0) {
                    $resolved_tec_term_ids[] = $term_id;
                }
            }

            $resolved_tec_term_ids = array_values(array_unique(array_filter(array_map('absint', $resolved_tec_term_ids), function ($value) {
                return $value > 0;
            })));

            $existing_term_ids = wp_get_post_terms($tec_event_id, 'tribe_events_cat', array('fields' => 'ids'));
            if (is_wp_error($existing_term_ids) || !is_array($existing_term_ids)) {
                $existing_term_ids = array();
            }
            $existing_term_ids = array_values(array_unique(array_filter(array_map('absint', $existing_term_ids), function ($value) {
                return $value > 0;
            })));

            $preserved_manual_ids = array_values(array_diff($existing_term_ids, $previous_managed_ids));
            $final_term_ids = array_values(array_unique(array_merge($preserved_manual_ids, $resolved_tec_term_ids)));

            wp_set_post_terms($tec_event_id, $final_term_ids, 'tribe_events_cat', false);

            if (!empty($resolved_tec_term_ids)) {
                update_post_meta($tec_event_id, $managed_meta_key, $resolved_tec_term_ids);
            } else {
                delete_post_meta($tec_event_id, $managed_meta_key);
            }
        }


 

        /**
         * TEC integration helpers
         * VMS is the source of truth; TEC entities are derived.
         */

        function bvmgr_tec_sync_event_extras_from_plan(int $plan_id, int $tec_event_id): void {

            if (!$plan_id || !$tec_event_id) {
                return;
            }

            if (!function_exists('tribe_update_event')) {
                return;
            }

            $args = bvmgr_build_tec_event_args($plan_id, $tec_event_id);
            if (empty($args)) {
                return;
            }

            if (function_exists('bvmgr_event_plan_apply_tec_author_args')) {
                $args = bvmgr_event_plan_apply_tec_author_args($plan_id, $args, $tec_event_id, 'vms_tec_sync_event_extras_from_plan');
            }

            $updated = tribe_update_event($tec_event_id, $args);
            delete_post_meta($tec_event_id, '_EventOrganizerID');

            if (function_exists('bvmgr_event_plan_backfill_tec_event_author')) {
                bvmgr_event_plan_backfill_tec_event_author($plan_id, $tec_event_id, 'vms_tec_sync_event_extras_from_plan');
            }

            if (!$updated || is_wp_error($updated)) {
                if (function_exists('bvmgr_record_operational_issue')) {
                    bvmgr_record_operational_issue('event_plan_tec_extras_sync_failed', array(
                        'service' => 'the_events_calendar',
                        'operation' => 'sync_event_extras',
                        'status' => 'failed',
                        'plan_id' => $plan_id,
                        'event_id' => $tec_event_id,
                    ), is_wp_error($updated) ? $updated : 'tribe_update_event_failed');
                }
				return;
			}

			if (function_exists('bvmgr_tec_finalize_event_url_sync')) {
				bvmgr_tec_finalize_event_url_sync($tec_event_id, $args);
            }
        }



        function bvmgr_tec_get_tec_venue_id_for_plan(int $plan_id): int
        {
            $vms_venue_id = (int) get_post_meta($plan_id, '_vms_venue_id', true);
            return bvmgr_tec_ensure_tec_venue_for_vms_venue($vms_venue_id);
        }

        function bvmgr_tec_ensure_tec_venue_for_vms_venue(int $vms_venue_id): int
        {
            if ($vms_venue_id <= 0) return 0;
            if (!function_exists('tribe_create_venue')) return 0;

            $key = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('venue', 'tec_venue_id') : '_vms_tec_venue_id';

            $existing = (int) get_post_meta($vms_venue_id, $key, true);
            if ($existing > 0 && get_post_status($existing)) {
                if (function_exists('bvmgr_sync_tec_venue_from_vms_venue')) {
                    bvmgr_sync_tec_venue_from_vms_venue($vms_venue_id, $existing);
                }
                return $existing;
            }

            $name = (string) get_the_title($vms_venue_id);
            if ($name === '') return 0;

            $location = function_exists('bvmgr_get_venue_location_data')
                ? bvmgr_get_venue_location_data($vms_venue_id)
                : array();

            $create_args = array(
                'Venue' => $name,
            );

            if (!empty($location['address'])) {
                $create_args['Address'] = (string) $location['address'];
            }
            if (!empty($location['city'])) {
                $create_args['City'] = (string) $location['city'];
            }
            if (!empty($location['state'])) {
                $create_args['State'] = (string) $location['state'];
            }
            if (!empty($location['zip'])) {
                $create_args['Zip'] = (string) $location['zip'];
            }
            if (!empty($location['country'])) {
                $create_args['Country'] = (string) $location['country'];
            }

            $created = tribe_create_venue($create_args);

            if ($created && !is_wp_error($created)) {
                $created = (int) $created;
                update_post_meta($vms_venue_id, $key, $created);
                if (function_exists('bvmgr_sync_tec_venue_from_vms_venue')) {
                    bvmgr_sync_tec_venue_from_vms_venue($vms_venue_id, $created);
                }
                return $created;
            }

            return 0;
        }

        function bvmgr_tec_get_tec_organizer_id_for_plan(int $plan_id): int
        {
            $vendor_id = (int) get_post_meta($plan_id, '_vms_band_vendor_id', true);
            return bvmgr_tec_ensure_tec_organizer_for_vendor($vendor_id);
        }

        function bvmgr_tec_ensure_tec_organizer_for_vendor(int $vendor_id): int
        {
            if ($vendor_id <= 0) return 0;
            if (!function_exists('tribe_create_organizer')) return 0;

            $key = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'tec_organizer_id') : '_vms_tec_organizer_id';

            $existing = (int) get_post_meta($vendor_id, $key, true);
            if ($existing > 0 && get_post_status($existing)) return $existing;

            $name = (string) get_the_title($vendor_id);
            if ($name === '') return 0;

            $email = '';
            $phone = '';
            $website = '';

            if (function_exists('bvmgr_meta_key')) {
                $email = (string) get_post_meta($vendor_id, bvmgr_meta_key('vendor', 'primary_email'), true);
                $phone = (string) get_post_meta($vendor_id, bvmgr_meta_key('vendor', 'primary_phone'), true);
                $website = (string) get_post_meta($vendor_id, bvmgr_meta_key('vendor', 'website'), true);
            }

            $args = array(
                'Organizer' => $name,
            );

            if ($email !== '') $args['Email'] = $email;
            if ($phone !== '') $args['Phone'] = $phone;
            if ($website !== '') $args['Website'] = $website;

            $created = tribe_create_organizer($args);

            if ($created && !is_wp_error($created)) {
                $created = (int) $created;
                update_post_meta($vendor_id, $key, $created);
                return $created;
            }

            return 0;
        }

        function bvmgr_tec_get_native_ticket_url_for_plan(int $plan_id): string
        {
            $map = get_post_meta($plan_id, '_vms_wc_product_map', true);
            if (!is_array($map)) return '';

            $ga_id = isset($map['ga']) ? (int) $map['ga'] : 0;
            if ($ga_id <= 0) return '';

            $url = get_permalink($ga_id);
            return $url ? esc_url_raw($url) : '';
        }

        function bvmgr_tec_get_ticket_url_for_plan(int $plan_id): string
        {
			if (function_exists('bvmgr_event_plan_is_externally_ticketed') && bvmgr_event_plan_is_externally_ticketed($plan_id)) {
				return function_exists('bvmgr_event_plan_get_external_ticket_url')
					? bvmgr_event_plan_get_external_ticket_url($plan_id)
					: '';
			}

			return bvmgr_tec_get_native_ticket_url_for_plan($plan_id);
		}

		function bvmgr_tec_managed_event_url_meta_key(): string
		{
			return '_vms_tec_managed_event_url';
		}

		/**
		 * Mark a checkout URL written by the earlier External Ticketing implementation
		 * before the Event Plan URL changes, so the next resync can clear it safely.
		 */
		function bvmgr_tec_capture_legacy_event_url_ownership(int $plan_id, string $previous_external_url): void
		{
			$plan_id = absint($plan_id);
			$previous_external_url = trim($previous_external_url);
			if ($plan_id <= 0 || $previous_external_url === '') {
				return;
			}

			$tec_key = function_exists('bvmgr_meta_key') ? (string) (bvmgr_meta_key('event_plan', 'tec_event_id') ?: '_vms_tec_event_id') : '_vms_tec_event_id';
			$event_id = absint(get_post_meta($plan_id, $tec_key, true));
			if ($event_id <= 0) {
				return;
			}

			$current_event_url = trim((string) get_post_meta($event_id, '_EventURL', true));
			if ($current_event_url !== '' && hash_equals($current_event_url, $previous_external_url)) {
				update_post_meta($event_id, bvmgr_tec_managed_event_url_meta_key(), $current_event_url);
			}
		}

		/**
		 * Build only the TEC Event Website mutation VMS is allowed to make.
		 *
		 * @return array<string,string>
		 */
		function bvmgr_tec_build_event_url_args(int $plan_id, int $existing_tec_id = 0): array
		{
			$plan_id = absint($plan_id);
			$existing_tec_id = absint($existing_tec_id);
			$is_external = function_exists('bvmgr_event_plan_is_externally_ticketed') && bvmgr_event_plan_is_externally_ticketed($plan_id);
			$external_url = function_exists('bvmgr_event_plan_get_external_ticket_url') ? bvmgr_event_plan_get_external_ticket_url($plan_id) : '';
			$native_url = bvmgr_tec_get_native_ticket_url_for_plan($plan_id);
			$current_url = $existing_tec_id > 0 ? trim((string) get_post_meta($existing_tec_id, '_EventURL', true)) : '';
			$managed_url = $existing_tec_id > 0 ? trim((string) get_post_meta($existing_tec_id, bvmgr_tec_managed_event_url_meta_key(), true)) : '';

			$owned_values = array_values(array_unique(array_filter(array($managed_url, $external_url, $native_url), 'strlen')));
			$current_is_owned = $current_url !== '' && in_array($current_url, $owned_values, true);

			if ($is_external) {
				return $current_is_owned ? array('EventURL' => '') : array();
			}

			// A TEC website not recognized as VMS-managed belongs to the event editor.
			if ($current_url !== '' && !$current_is_owned) {
				return array();
			}
			if ($native_url !== '') {
				return array('EventURL' => $native_url);
			}

			return $current_is_owned ? array('EventURL' => '') : array();
		}

		function bvmgr_tec_finalize_event_url_sync(int $event_id, array $args): void
		{
			$event_id = absint($event_id);
			if ($event_id <= 0 || !array_key_exists('EventURL', $args)) {
				return;
			}

			$event_url = trim((string) $args['EventURL']);
			if ($event_url === '') {
				delete_post_meta($event_id, '_EventURL');
				delete_post_meta($event_id, bvmgr_tec_managed_event_url_meta_key());
				return;
			}

			update_post_meta($event_id, bvmgr_tec_managed_event_url_meta_key(), $event_url);
		}

        function bvmgr_tec_build_event_cost_string(int $plan_id): string
        {
			if (function_exists('bvmgr_event_plan_is_externally_ticketed') && bvmgr_event_plan_is_externally_ticketed($plan_id)) {
				return '';
			}

            $from_price = function_exists('bvmgr_ticketing_v2_get_from_price_for_display')
                ? bvmgr_ticketing_v2_get_from_price_for_display($plan_id)
                : null;
            if ($from_price === null || $from_price <= 0) {
                return '';
            }

            return 'From ' . bvmgr_tec_format_money((float) $from_price);
        }

        function bvmgr_tec_format_money(float $amount): string
        {
            $rounded = round($amount, 2);
            $is_intish = abs($rounded - round($rounded)) < 0.01;
            return '$' . ($is_intish ? number_format((float) round($rounded), 0) : number_format($rounded, 2));
        }


        add_filter('post_row_actions', 'bvmgr_event_plan_row_actions', 10, 2);
        function bvmgr_event_plan_row_actions(array $actions, WP_Post $post): array
        {
            if ($post->post_type !== 'vms_event_plan') return $actions;

            $tec_url = (string) get_post_meta($post->ID, '_vms_tec_event_url', true);
            if ($tec_url) {
                $actions['vms_view_tec'] =
                    '<a href="' . esc_url($tec_url) . '" target="_blank" rel="noopener">' .
                    esc_html__('View in Calendar', 'backstage-venue-manager') .
                    '</a>';
            }
            return $actions;
        }



        /**
         * Event Plans list (admin): show a flag state when a plan was auto-drafted due to a missing vendor.
         */
        add_filter('display_post_states', function (array $states, WP_Post $post): array {
            if (!is_admin()) return $states;
            if ($post->post_type !== 'vms_event_plan') return $states;

            $k_issue = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_issue') : '_vms_integrity_issue';
            $issue = (string) get_post_meta($post->ID, $k_issue, true);

            $needs_attention = false;
            if (in_array($issue, array('missing_vendor','missing_secondary_vendor','trashed_vendor','trashed_secondary_vendor','missing_venue','trashed_venue','venue_unpublished','missing_calendar_event','trashed_calendar_event','calendar_event_unpublished','calendar_event_unlinked'), true)) {
        			$needs_attention = true;
        		}

            $k_unq = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'secondary_vendor_unqualified') : '_vms_secondary_vendor_unqualified';
            if ((string) get_post_meta($post->ID, $k_unq, true) === '1') {
                $needs_attention = true;
            }

            if ($needs_attention) {
                $states[] = '<span class="vms-needs-attention">🚩 Needs attention</span>';
            }

            return $states;
        }, 10, 2);

        /**
         * Event Plans list (admin): DO NOT auto-filter by Schedule's "current venue".
         * This list should be all venues unless the user explicitly filters on this screen.
         */
        add_action('pre_get_posts', function ($query) {
            if (!is_admin() || !$query->is_main_query()) return;

            $screen = function_exists('get_current_screen') ? get_current_screen() : null;
            if (!$screen || $screen->id !== 'edit-vms_event_plan') return;

            // Optional explicit filter (future UI can set this query param).
            $request = bvmgr_event_plan_current_get_request();
            $venue_id = isset($request['vms_venue_id']) ? absint($request['vms_venue_id']) : 0;
            if ($venue_id <= 0) return;

            $meta_query = (array) $query->get('meta_query');
            $meta_query[] = array(
                'key'     => '_vms_venue_id',
                'value'   => $venue_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            );
            $query->set('meta_query', $meta_query);
        });

        /**
         * Event Plans titles (admin): decode WordPress typographic entities like "&#8211;".
         * These sometimes get stored/returned when titles are composed from filtered sources.
         */
        add_filter('the_title', function ($title, $post_id) {
            if (!is_admin()) return $title;

            $post = get_post($post_id);
            if (!$post || $post->post_type !== 'vms_event_plan') return $title;

            // Fast exit if there are no entities to decode.
            if (strpos($title, '&') === false) return $title;

            $charset = function_exists('get_bloginfo') ? (string) get_bloginfo('charset') : 'UTF-8';
            $decoded = html_entity_decode($title, ENT_QUOTES, $charset);
            // Handle double-encoded cases (e.g., "&amp;#8211;").
            $decoded = html_entity_decode($decoded, ENT_QUOTES, $charset);

            return $decoded;
        }, 20, 2);

        /**
         * Woo publish pipeline
         */
        function bvmgr_publish_products_from_plan(int $plan_id): array
        {
            return array(
                'ok' => false,
                'error' => 'legacy_pipeline_retired',
                'message' => 'Legacy Woo publish pipeline is retired. Use Ticketing Preview → Commit.',
                'plan_id' => absint($plan_id),
            );
        }

        function bvmgr_build_product_blueprint_for_plan(int $plan_id, string $nice_date, string $band_name): array
        {
            $plan_id = absint($plan_id);
            if ($plan_id <= 0 || !function_exists('bvmgr_ticketing_v2_get_config')) {
                return array();
            }

            $cfg = bvmgr_ticketing_v2_get_config($plan_id);
            $tickets = isset($cfg['tickets']) && is_array($cfg['tickets']) ? $cfg['tickets'] : array();
            if (empty($tickets)) {
                return array();
            }

            usort($tickets, static function ($a, $b): int {
                $a_sort = isset($a['sort_order']) ? (int) $a['sort_order'] : 9999;
                $b_sort = isset($b['sort_order']) ? (int) $b['sort_order'] : 9999;
                if ($a_sort === $b_sort) {
                    $a_title = strtolower(trim((string) ($a['title'] ?? '')));
                    $b_title = strtolower(trim((string) ($b['title'] ?? '')));
                    return strcmp($a_title, $b_title);
                }
                return $a_sort <=> $b_sort;
            });

            $items = array();
            $seen_keys = array();
            foreach ($tickets as $ticket) {
                if (!is_array($ticket) || empty($ticket['enabled'])) {
                    continue;
                }

                $title = trim(sanitize_text_field((string) ($ticket['title'] ?? '')));
                if ($title === '') {
                    continue;
                }

                $price = (float) ($ticket['price'] ?? 0);
                if ($price < 0) {
                    $price = 0.0;
                }

                $ticket_key = sanitize_key((string) ($ticket['ticket_key'] ?? ''));
                if ($ticket_key === '') {
                    $ticket_key = sanitize_key($title);
                }
                if ($ticket_key === '') {
                    $ticket_key = 'ticket';
                }

                $base_key = $ticket_key;
                $suffix = 2;
                while (isset($seen_keys[$ticket_key])) {
                    $ticket_key = $base_key . '_' . $suffix;
                    $suffix++;
                }
                $seen_keys[$ticket_key] = true;

                $sku_suffix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $ticket_key));
                if ($sku_suffix === '') {
                    $sku_suffix = 'TKT';
                }
                if (strlen($sku_suffix) > 12) {
                    $sku_suffix = substr($sku_suffix, 0, 12);
                }

                $visibility_mode = sanitize_key((string) ($ticket['visibility_mode'] ?? 'public'));
                if (!in_array($visibility_mode, array('public', 'login', 'verified'), true)) {
                    $visibility_mode = 'public';
                }
                $verified_program = sanitize_key((string) ($ticket['verified_program'] ?? ''));
                if ($visibility_mode !== 'verified') {
                    $verified_program = '';
                }

                $meta = array(
                    '_sr_addon_qualifier' => 'yes',
                );
                if (function_exists('bvmgr_ticketing_v2_product_meta_key')) {
                    $meta[bvmgr_ticketing_v2_product_meta_key('ticketing_visibility_mode')] = $visibility_mode;
                    if ($verified_program !== '') {
                        $meta[bvmgr_ticketing_v2_product_meta_key('ticketing_verified_program')] = $verified_program;
                    }
                    $ratio_rule_enabled = !empty($ticket['ratio_rule_enabled']);
                    $ratio_rule_max_per_qualifying = max(0, absint($ticket['ratio_rule_max_per_qualifying'] ?? 0));
                    if ($ratio_rule_enabled && $ratio_rule_max_per_qualifying > 0) {
                        $meta[bvmgr_ticketing_v2_product_meta_key('ticketing_ratio_rule_enabled')] = '1';
                        $meta[bvmgr_ticketing_v2_product_meta_key('ticketing_ratio_rule_max_per_qualifying')] = (string) $ratio_rule_max_per_qualifying;
                        $meta[bvmgr_ticketing_v2_product_meta_key('ticketing_ratio_rule_qualifier_mode')] = 'counts_toward_unlock';
                        $ratio_rule_group = sanitize_title((string) ($ticket['ratio_rule_group'] ?? ''));
                        if ($ratio_rule_group !== '') {
                            $meta[bvmgr_ticketing_v2_product_meta_key('ticketing_ratio_rule_group')] = $ratio_rule_group;
                        }
                    }
                }

                $items[$ticket_key] = array(
                    'name'       => "{$nice_date} — {$band_name} — {$title}",
                    'price'      => $price,
                    'is_ticket'  => true,
                    'sku_suffix' => $sku_suffix,
                    'meta'       => $meta,
                    'tags'       => array('ticket'),
                );
            }

            return $items;
        }

                /**
         * Calculate units sold for a product across paid/active orders.
         *
         * We deliberately include "processing" and "on-hold" because many ticket sites
         * keep orders in processing for virtual goods.
         */
        function bvmgr_wc_units_sold_for_product_details(int $product_id): array
        {
            $product_id = absint($product_id);
            if ($product_id <= 0) {
                return array(
                    'ok' => false,
                    'sold_qty' => 0,
                    'order_scan_sold_qty' => 0,
                    'meta_total_sales' => 0,
                    'ignored_total_sales' => 0,
                    'message' => 'invalid_product_id',
                );
            }

            if (function_exists('bvmgr_ticketing_v2_calc_sold_qty_for_product')) {
                $result = bvmgr_ticketing_v2_calc_sold_qty_for_product($product_id);
                if (is_array($result) && array_key_exists('sold_qty', $result)) {
                    return $result;
                }
            }

            if (!function_exists('wc_get_orders')) {
                return array(
                    'ok' => false,
                    'sold_qty' => 0,
                    'order_scan_sold_qty' => 0,
                    'meta_total_sales' => max(0, (int) get_post_meta($product_id, 'total_sales', true)),
                    'ignored_total_sales' => 0,
                    'message' => 'woocommerce_unavailable',
                );
            }

            $statuses = array('processing', 'completed', 'on-hold');
            $base_args = array(
                'limit'    => -1,
                'status'   => $statuses,
                'type'     => 'shop_order',
                'return'   => 'ids',
                'paginate' => false,
            );

            // Woo supports filtering by product in many versions; if unsupported, we fall back.
            $order_ids = array();
            try {
                $args = $base_args;
                $args['product'] = $product_id;
                $order_ids = wc_get_orders($args);
            } catch (Throwable $e) {
                $order_ids = array();
            }

            if (!is_array($order_ids)) $order_ids = array();

            if (empty($order_ids)) {
                try {
                    $order_ids = wc_get_orders($base_args);
                } catch (Throwable $e) {
                    $order_ids = array();
                }
                if (!is_array($order_ids)) $order_ids = array();
            }

            $sold = 0;
            foreach ($order_ids as $oid) {
                $order = wc_get_order($oid);
                if (!$order) continue;

                foreach ($order->get_items('line_item') as $item) {
                    if (!$item) continue;
                    $pid = (int) $item->get_product_id();
                    $vid = (int) $item->get_variation_id();
                    if ($pid === $product_id || $vid === $product_id) {
                        $sold += max(0, (int) $item->get_quantity());
                    }
                }
            }

            $meta_total = max(0, (int) get_post_meta($product_id, 'total_sales', true));

            return array(
                'ok' => true,
                'sold_qty' => max(0, (int) $sold),
                'order_scan_sold_qty' => max(0, (int) $sold),
                'meta_total_sales' => $meta_total,
                'ignored_total_sales' => ($meta_total > $sold) ? 1 : 0,
                'message' => ($meta_total > $sold) ? 'ignored_stale_total_sales' : 'ok',
            );
        }

        function bvmgr_wc_units_sold_for_product(int $product_id): int
        {
            $result = bvmgr_wc_units_sold_for_product_details($product_id);
            return max(0, (int) ($result['sold_qty'] ?? 0));
        }

function bvmgr_upsert_plan_product(int $plan_id, int $existing_product_id, array $spec, int $tec_event_id = 0): int
        {
            if (!class_exists('WC_Product_Simple')) return 0;

            $name  = (string)($spec['name'] ?? '');
            $price = (float)($spec['price'] ?? 0.0);
            if ($name === '') return 0;

            $product = null;
            if ($existing_product_id > 0) {
                $product = wc_get_product($existing_product_id);
            }
            if (!$product) {
                $product = new WC_Product_Simple();
            }

            $product->set_name($name);
            $product->set_regular_price($price);
            $product->set_status('publish');
            $product->set_catalog_visibility('visible');

            $inventory_write_context = array();
            if (isset($spec['stock_qty'])) {
                // Entitlements / add-ons: never blindly reset stock.
                // Reconcile desired capacity against what has already been sold.
                $desired = max(0, (int) $spec['stock_qty']);

                $sold = 0;
                $sold_result = array(
                    'ok' => true,
                    'meta_total_sales' => 0,
                    'ignored_total_sales' => 0,
                );
                if ($existing_product_id > 0) {
                    $sold_result = bvmgr_wc_units_sold_for_product_details($existing_product_id);
                    $sold = max(0, (int) ($sold_result['sold_qty'] ?? 0));
                }

                $new_stock = max(0, (int) ($desired - $sold));
                $reason_text = sprintf(
                    /* translators: 1: desired capacity, 2: sold quantity, 3: remaining quantity */
                    __('Legacy plan-product upsert recalculated Woo stock from desired capacity %1$d minus sold quantity %2$d, leaving %3$d remaining.', 'backstage-venue-manager'),
                    $desired,
                    $sold,
                    $new_stock
                );
                if (!empty($sold_result['ignored_total_sales'])) {
                    $reason_text .= ' ' . sprintf(
                        /* translators: %d: Woo total_sales value */
                        __('Woo total_sales reported %d for this product, but the legacy upsert ignored that stale lifetime counter and trusted the paid-order scan instead.', 'backstage-venue-manager'),
                        max(0, absint($sold_result['meta_total_sales'] ?? 0))
                    );
                }

                $product->set_manage_stock(true);
                $product->set_stock_quantity($new_stock);
                $product->set_stock_status(($new_stock > 0) ? 'instock' : 'outofstock');
                $product->set_sold_individually(true);
                $inventory_write_context = array(
                    'trigger_source' => 'reconciliation',
                    'source_function' => 'vms_upsert_plan_product',
                    'derivation_source' => 'legacy_plan_product_sold_count_reconciliation',
                    'confidence_level' => 'authoritative',
                    'expected_effect' => ($new_stock > 0) ? 'reopen' : 'close',
                    'reason_text' => $reason_text,
                    'writer_branch' => 'legacy_plan_product_stock_reconciliation',
                    'result_health' => ($new_stock > 0) ? 'expected_sellable_state' : 'expected_closed_state',
                );
            } else {
                $product->set_manage_stock(false);
                $inventory_write_context = array(
                    'trigger_source' => 'reconciliation',
                    'source_function' => 'vms_upsert_plan_product',
                    'derivation_source' => 'authoritative_config',
                    'confidence_level' => 'authoritative',
                    'expected_effect' => 'preserve',
                    'reason_text' => __('Legacy plan-product upsert disabled Woo manage-stock for this product because no stock-managed capacity was configured.', 'backstage-venue-manager'),
                    'writer_branch' => 'legacy_plan_product_non_stock_branch',
                    'result_health' => 'manual_review',
                );
            }

            if (function_exists('bvmgr_ticketing_v2_push_inventory_write_context')) {
                bvmgr_ticketing_v2_push_inventory_write_context($inventory_write_context);
            } elseif (function_exists('bvmgr_ticket_mutation_audit_push_context')) {
                bvmgr_ticket_mutation_audit_push_context($inventory_write_context);
            }

            try {
                $product_id = (int) $product->save();
            } finally {
                if (function_exists('bvmgr_ticketing_v2_pop_inventory_write_context')) {
                    bvmgr_ticketing_v2_pop_inventory_write_context();
                } elseif (function_exists('bvmgr_ticket_mutation_audit_pop_context')) {
                    bvmgr_ticket_mutation_audit_pop_context();
                }
            }
            if (!$product_id) return 0;

            update_post_meta($product_id, '_vms_event_plan_id', $plan_id);
            if ($tec_event_id > 0) update_post_meta($product_id, '_vms_tec_event_id', $tec_event_id);

            $tags = (isset($spec['tags']) && is_array($spec['tags'])) ? $spec['tags'] : array();
            wp_set_object_terms($product_id, $tags, 'product_tag', false);

            $meta = (isset($spec['meta']) && is_array($spec['meta'])) ? $spec['meta'] : array();
            foreach ($meta as $k => $v) {
                update_post_meta($product_id, (string)$k, $v);
            }

            update_post_meta($product_id, '_vms_product_role', !empty($spec['is_ticket']) ? 'ticket' : 'addon');
            if (function_exists('bvmgr_ticketing_v2_apply_reporting_category')) {
                bvmgr_ticketing_v2_apply_reporting_category($product_id, !empty($spec['is_ticket']) ? 'ticket' : 'addon');
            }

            return $product_id;
        }

        function bvmgr_disable_plan_product(int $product_id): void
        {
            $p = wc_get_product($product_id);
            if (!$p) return;
            $p->set_status('draft');
            $p->save();
        }
