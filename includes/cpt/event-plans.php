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
if (!function_exists('vms_secondary_vendor_missing_items')) {
    function vms_secondary_vendor_missing_items(int $vendor_id, array $ctx = array()): array
    {
        $missing = array();

        $vp = get_post($vendor_id);
        if (!$vp || $vp->post_type !== 'vms_vendor' || $vp->post_status === 'trash') {
            $missing[] = 'Vendor missing';
            return $missing;
        }

        $k_primary_email = function_exists('vms_meta_key') ? vms_meta_key('vendor', 'primary_email') : '_vms_vendor_primary_email';
        $k_primary_phone = function_exists('vms_meta_key') ? vms_meta_key('vendor', 'primary_phone') : '_vms_vendor_primary_phone';
        $k_email_legacy  = function_exists('vms_meta_key') ? vms_meta_key('vendor', 'email') : '_vms_vendor_email';
        $k_phone_legacy  = function_exists('vms_meta_key') ? vms_meta_key('vendor', 'phone') : '_vms_vendor_phone';

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

if (!function_exists('vms_secondary_vendor_is_qualified')) {
    function vms_secondary_vendor_is_qualified(int $vendor_id, array $ctx = array()): bool
    {
        $missing = function_exists('vms_secondary_vendor_missing_items')
            ? (array) vms_secondary_vendor_missing_items($vendor_id, $ctx)
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
add_action('init', 'vms_register_event_plan_cpt');
function vms_register_event_plan_cpt(): void
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

if (!function_exists('vms_event_plan_admin_edit_url')) {
    function vms_event_plan_admin_edit_url(int $post_id, array $query_args = array(), string $fragment = '', string $context = 'raw'): string
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

if (!function_exists('vms_event_plan_is_canonical_edit_link')) {
    function vms_event_plan_is_canonical_edit_link($link, int $post_id): bool
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

if (!function_exists('vms_event_plan_force_edit_post_link')) {
    /**
     * Guarantee a real edit link for Event Plans before core builds the save redirect.
     *
     * Some admin/plugin flows can filter the edit link to empty or to a non-editor URL.
     * Force the canonical Event Plan editor link so both core and later filters have a
     * stable base URL to work with.
     */
    function vms_event_plan_force_edit_post_link($link, int $post_id, string $context = 'display')
    {
        if ($post_id <= 0 || get_post_type($post_id) !== 'vms_event_plan') {
            return $link;
        }

        if (vms_event_plan_is_canonical_edit_link($link, $post_id)) {
            return $link;
        }

        return vms_event_plan_admin_edit_url($post_id, array(), '', $context);
    }
}
add_filter('get_edit_post_link', 'vms_event_plan_force_edit_post_link', 999, 3);

if (!function_exists('vms_event_plan_force_redirect_post_location')) {
    /**
     * Guard the Event Plan save redirect against null/incorrect edit URLs.
     *
     * WordPress builds the post-save redirect from get_edit_post_link(). When that
     * unexpectedly resolves empty, core falls through to a bad redirect and the save
     * appears to dump operators into the generic Posts list. Force Event Plans back
     * to their editor while preserving the original query flags like `message=4`.
     */
    function vms_event_plan_force_redirect_post_location($location, int $post_id)
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

        $target = function_exists('vms_event_plan_pull_runtime_redirect_target')
            ? (array) vms_event_plan_pull_runtime_redirect_target($post_id)
            : array();
        $target_post_id = absint($target['target_post_id'] ?? 0);
        if ($target_post_id > 0 && get_post_type($target_post_id) === 'vms_event_plan') {
            $extra_query_args = isset($target['query_args']) && is_array($target['query_args']) ? $target['query_args'] : array();
            $query_args = array_merge($query_args, $extra_query_args);
            $fragment = isset($target['fragment']) ? (string) $target['fragment'] : $fragment;
            return vms_event_plan_admin_edit_url($target_post_id, $query_args, $fragment);
        }

        return vms_event_plan_admin_edit_url($post_id, $query_args, $fragment);
    }
}
add_filter('redirect_post_location', 'vms_event_plan_force_redirect_post_location', 999, 2);

if (!function_exists('vms_event_plan_runtime_redirect_targets')) {
    function &vms_event_plan_runtime_redirect_targets(): array
    {
        if (!isset($GLOBALS['vms_event_plan_runtime_redirect_targets']) || !is_array($GLOBALS['vms_event_plan_runtime_redirect_targets'])) {
            $GLOBALS['vms_event_plan_runtime_redirect_targets'] = array();
        }

        return $GLOBALS['vms_event_plan_runtime_redirect_targets'];
    }
}

if (!function_exists('vms_event_plan_set_runtime_redirect_target')) {
    function vms_event_plan_set_runtime_redirect_target(int $source_post_id, int $target_post_id, array $query_args = array(), string $fragment = ''): void
    {
        $source_post_id = absint($source_post_id);
        $target_post_id = absint($target_post_id);
        if ($source_post_id <= 0 || $target_post_id <= 0) {
            return;
        }

        $targets =& vms_event_plan_runtime_redirect_targets();
        $targets[$source_post_id] = array(
            'target_post_id' => $target_post_id,
            'query_args'     => is_array($query_args) ? $query_args : array(),
            'fragment'       => (string) $fragment,
        );
    }
}

if (!function_exists('vms_event_plan_pull_runtime_redirect_target')) {
    function vms_event_plan_pull_runtime_redirect_target(int $source_post_id): array
    {
        $source_post_id = absint($source_post_id);
        if ($source_post_id <= 0) {
            return array();
        }

        $targets =& vms_event_plan_runtime_redirect_targets();
        if (empty($targets[$source_post_id]) || !is_array($targets[$source_post_id])) {
            return array();
        }

        $target = $targets[$source_post_id];
        unset($targets[$source_post_id]);

        return $target;
    }
}

if (!function_exists('vms_event_plan_reopenable_sections')) {
    function vms_event_plan_reopenable_sections(): array
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

if (!function_exists('vms_event_plan_normalize_reopen_section')) {
    function vms_event_plan_normalize_reopen_section(string $section): string
    {
        $section = sanitize_key($section);
        if ($section === '') {
            return '';
        }

        $allowed = vms_event_plan_reopenable_sections();
        return isset($allowed[$section]) ? $section : '';
    }
}

if (!function_exists('vms_event_plan_reopen_section_fragment')) {
    function vms_event_plan_reopen_section_fragment(string $section): string
    {
        $section = vms_event_plan_normalize_reopen_section($section);
        if ($section === '') {
            return '';
        }

        $sections = vms_event_plan_reopenable_sections();
        return isset($sections[$section]) ? (string) $sections[$section] : '';
    }
}

if (!function_exists('vms_event_plan_set_runtime_reopen_section_target')) {
    function vms_event_plan_set_runtime_reopen_section_target(int $post_id, string $section): void
    {
        $post_id = absint($post_id);
        $section = vms_event_plan_normalize_reopen_section($section);
        if ($post_id <= 0 || $section === '') {
            return;
        }

        $fragment = vms_event_plan_reopen_section_fragment($section);
        vms_event_plan_set_runtime_redirect_target(
            $post_id,
            $post_id,
            array(
                'vms_ep_load_section' => $section,
            ),
            $fragment
        );
    }
}

if (!function_exists('vms_event_plan_normalize_related_plan_ids')) {
    function vms_event_plan_normalize_related_plan_ids($value): array
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

if (!function_exists('vms_event_plan_create_rescheduled_draft')) {
    function vms_event_plan_create_rescheduled_draft(int $source_post_id, array $args = array()): array
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

        $k_status = function_exists('vms_meta_key')
            ? (vms_meta_key('event_plan', 'status') ?: '_vms_event_plan_status')
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
        if (function_exists('vms_duplicate_post_with_meta_and_terms')) {
            $new_post_id = (int) vms_duplicate_post_with_meta_and_terms($source_post_id, array(
                'post_status' => 'draft',
                'post_title'  => $title,
                'post_author' => get_current_user_id(),
            ));
        }

        if ($new_post_id <= 0) {
            return array(
                'ok' => false,
                'error' => 'duplicate_failed',
                'error_message' => __('VMS could not create the replacement Event Plan draft.', 'backstage-venue-manager'),
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

        $k_date = function_exists('vms_meta_key')
            ? (vms_meta_key('event_plan', 'date') ?: '_vms_event_date')
            : '_vms_event_date';
        $k_rescheduled_from = function_exists('vms_meta_key')
            ? (vms_meta_key('event_plan', 'rescheduled_from_plan_id') ?: '_vms_rescheduled_from_plan_id')
            : '_vms_rescheduled_from_plan_id';
        $k_rescheduled_to = function_exists('vms_meta_key')
            ? (vms_meta_key('event_plan', 'rescheduled_to_plan_ids') ?: '_vms_rescheduled_to_plan_ids')
            : '_vms_rescheduled_to_plan_ids';

        $k_ticketing_override = function_exists('vms_meta_key')
            ? (vms_meta_key('event_plan', 'ticketing_enabled_override') ?: '_vms_ticketing_enabled_override')
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

        $existing_children = vms_event_plan_normalize_related_plan_ids(get_post_meta($source_post_id, $k_rescheduled_to, true));
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
class VMS_Admin_Event_Plans
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
        $plan_id = function_exists('vms_event_plan_perf_current_plan_id')
            ? vms_event_plan_perf_current_plan_id()
            : 0;
        $trace = function_exists('vms_event_plan_perf_span_start')
            ? vms_event_plan_perf_span_start('event_plan_meta_box_registration', $plan_id, array('section' => 'meta_box_registration'))
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
            if (function_exists('vms_event_plan_perf_span_finish')) {
                vms_event_plan_perf_span_finish('event_plan_meta_box_registration', $plan_id, $trace, array('section' => 'meta_box_registration'));
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
        $trace = function_exists('vms_event_plan_perf_span_start')
            ? vms_event_plan_perf_span_start($hook_name, $plan_id, $trace_context)
            : '';

        try {
            if (function_exists('vms_event_plan_perf_log')) {
                vms_event_plan_perf_log('event_plan_query_optimization', $plan_id, array(
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
            if (function_exists('vms_event_plan_perf_span_finish')) {
                vms_event_plan_perf_span_finish($hook_name, $plan_id, $trace, $trace_context);
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
                if (!function_exists('vms_get_vendor_availability_for_date')) {
                    return '';
                }

                return sanitize_key((string) vms_get_vendor_availability_for_date($vendor_id, $event_date));
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
                if (!function_exists('vms_get_lineup_supporting_compensation_default')) {
                    return array(
                        'guaranteed_fee' => '',
                        'structure' => '',
                    );
                }

                $support_defaults = (array) vms_get_lineup_supporting_compensation_default($vendor_id, $venue_id_effective, $event_date);
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
                $missing = function_exists('vms_vendor_tax_profile_missing_items')
                    ? (array) vms_vendor_tax_profile_missing_items($vendor_id)
                    : array();
                $bypass = function_exists('vms_get_tax_bypass_status')
                    ? (array) vms_get_tax_bypass_status($vendor_id)
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
                if (!function_exists('vms_secondary_vendor_missing_items')) {
                    return array();
                }

                $missing = (array) vms_secondary_vendor_missing_items($vendor_id, array(
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
                if (function_exists('vms_vendor_has_type')) {
                    return (bool) vms_vendor_has_type($vendor_id, $type_slug);
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

                    $slug = function_exists('vms_vendor_type_canonical_slug_for_term')
                        ? vms_vendor_type_canonical_slug_for_term($term)
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

                $secondary_ids_key = function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'secondary_vendor_ids') : '_vms_secondary_vendor_ids';
                if ($secondary_ids_key === '') {
                    $secondary_ids_key = '_vms_secondary_vendor_ids';
                }
                $secondary_type_key = function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'secondary_vendor_type') : '_vms_secondary_vendor_type';
                if ($secondary_type_key === '') {
                    $secondary_type_key = '_vms_secondary_vendor_type';
                }
                $status_key = function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'status') : '_vms_event_plan_status';
                if ($status_key === '') {
                    $status_key = '_vms_event_plan_status';
                }
                $integrity_key = function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'integrity_issue') : '_vms_integrity_issue';
                if ($integrity_key === '') {
                    $integrity_key = '_vms_integrity_issue';
                }
                $tec_id_key = function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'tec_event_id') : '_vms_tec_event_id';
                if ($tec_id_key === '') {
                    $tec_id_key = '_vms_tec_event_id';
                }
                $tec_url_key = function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'tec_event_url') : '_vms_tec_event_url';
                if ($tec_url_key === '') {
                    $tec_url_key = '_vms_tec_event_url';
                }
                $ticket_product_ids_key = function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'ticket_product_ids') : '_vms_ticket_product_ids_v1';
                if ($ticket_product_ids_key === '') {
                    $ticket_product_ids_key = '_vms_ticket_product_ids_v1';
                }
                $ticket_stats_key = function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'ticket_stats') : '_vms_ticket_stats_v1';
                if ($ticket_stats_key === '') {
                    $ticket_stats_key = '_vms_ticket_stats_v1';
                }
                $manual_ticket_ids_key = function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'ticket_manual_product_ids') : '_vms_ticket_manual_product_ids_v1';
                if ($manual_ticket_ids_key === '') {
                    $manual_ticket_ids_key = '_vms_ticket_manual_product_ids_v1';
                }
                $ticketing_v2_config_key = function_exists('vms_event_plan_ticketing_v2_config_meta_key')
                    ? (string) vms_event_plan_ticketing_v2_config_meta_key()
                    : '_vms_ticketing_config_v2';
                $ticketing_v2_sync_key = function_exists('vms_ticketing_v2_k')
                    ? (string) vms_ticketing_v2_k('sync')
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
                $secondary_vendor_assignments = function_exists('vms_event_plan_get_secondary_vendor_assignments')
                    ? (array) vms_event_plan_get_secondary_vendor_assignments($plan_id, array(
                        'primary_vendor_id' => $band_vendor_id,
                    ))
                    : array();
                $secondary_vendor_ids = !empty($secondary_vendor_assignments) && function_exists('vms_event_plan_get_secondary_vendor_flat_ids_from_assignments')
                    ? vms_event_plan_get_secondary_vendor_flat_ids_from_assignments($secondary_vendor_assignments, $band_vendor_id)
                    : $read_meta($secondary_ids_key, array());
                if (!is_array($secondary_vendor_ids)) {
                    $secondary_vendor_ids = array();
                }
                $secondary_vendor_ids = array_values(array_unique(array_filter(array_map('absint', $secondary_vendor_ids), static function ($value): bool {
                    return $value > 0;
                })));

                $secondary_vendor_type = !empty($secondary_vendor_assignments) && function_exists('vms_event_plan_legacy_secondary_vendor_type_from_assignments')
                    ? (string) vms_event_plan_legacy_secondary_vendor_type_from_assignments($secondary_vendor_assignments)
                    : (string) $read_meta($secondary_type_key, '');
                if ($secondary_vendor_type !== '') {
                    $secondary_vendor_type = function_exists('vms_vendor_type_normalize_slug')
                        ? (string) vms_vendor_type_normalize_slug($secondary_vendor_type)
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
                $ticketing_effective = function_exists('vms_event_plan_is_ticketing_enabled')
                    ? (bool) vms_event_plan_is_ticketing_enabled($plan_id)
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
                if (function_exists('vms_event_plan_perf_memory_checkpoint')) {
                    vms_event_plan_perf_memory_checkpoint($plan_id, 'vendor_summary_before', array(
                        'section' => 'vendor_summary_boot',
                        'vendor_count' => is_array($bands) ? count($bands) : 0,
                        'primary_scope' => $primary_scope,
                        'supporting_scope' => $supporting_scope,
                    ), 'vendor_summary');
                }

                $availability_trace = function_exists('vms_event_plan_perf_span_start')
                    ? vms_event_plan_perf_span_start('event_plan_vendor_availability_boot', $plan_id, array(
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

                if (function_exists('vms_event_plan_perf_memory_checkpoint')) {
                    vms_event_plan_perf_memory_checkpoint($plan_id, 'vendor_availability_before', array(
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
                    if (function_exists('vms_event_plan_perf_span_finish')) {
                        vms_event_plan_perf_span_finish('event_plan_vendor_availability_boot', $plan_id, $availability_trace, array(
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
                    if (function_exists('vms_event_plan_perf_memory_checkpoint')) {
                        vms_event_plan_perf_memory_checkpoint($plan_id, 'vendor_availability_after', array(
                            'section' => 'vendor_availability_boot',
                            'vendor_count' => count($supporting_rows),
                            'vendor_state_count' => count($vendor_state_map),
                        ), 'vendor_availability');
                    }
                }

                if (function_exists('vms_event_plan_perf_log')) {
                    vms_event_plan_perf_log('event_plan_vendor_conflict_boot', $plan_id, array(
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

                if (function_exists('vms_event_plan_perf_memory_checkpoint')) {
                    vms_event_plan_perf_memory_checkpoint($plan_id, 'vendor_summary_after', array(
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
        $secondary_vendor_assignments = function_exists('vms_event_plan_normalize_secondary_vendor_assignment_map')
            ? vms_event_plan_normalize_secondary_vendor_assignment_map($plan_id, $secondary_vendor_assignments, $primary_vendor_id, array(
                'preserve_empty' => true,
            ))
            : (array) $secondary_vendor_assignments;
        $secondary_vendor_ids = function_exists('vms_event_plan_get_secondary_vendor_flat_ids_from_assignments')
            ? vms_event_plan_get_secondary_vendor_flat_ids_from_assignments($secondary_vendor_assignments, $primary_vendor_id)
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
                if (function_exists('vms_event_plan_perf_memory_checkpoint')) {
                    vms_event_plan_perf_memory_checkpoint($plan_id, 'secondary_vendor_summary_before', array(
                        'section' => 'secondary_vendor_summary_boot',
                        'detail_mode' => $full_details ? 'full' : 'summary_only',
                        'secondary_vendor_group_count' => count($secondary_vendor_assignments),
                        'secondary_vendor_selected_count' => count($secondary_vendor_ids),
                    ), 'secondary_vendor_summary');
                }

                $secondary_type_options = function_exists('vms_event_plan_additional_vendor_type_options')
                    ? (array) vms_event_plan_additional_vendor_type_options()
                    : array();
                foreach (array_keys($secondary_vendor_assignments) as $current_type_slug) {
                    $current_type_slug = sanitize_key((string) $current_type_slug);
                    if ($current_type_slug === '' || isset($secondary_type_options[$current_type_slug])) {
                        continue;
                    }

                    $secondary_type_options[$current_type_slug] = function_exists('vms_vendor_type_label')
                        ? (string) vms_vendor_type_label($current_type_slug)
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

                if (function_exists('vms_event_plan_perf_memory_checkpoint')) {
                    vms_event_plan_perf_memory_checkpoint($plan_id, 'secondary_vendor_availability_before', array(
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
                        $type_name = function_exists('vms_vendor_type_label')
                            ? (string) vms_vendor_type_label($type_slug)
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
                    $open_for_dispatch = function_exists('vms_event_plan_parse_secondary_vendor_over_capacity_override')
                        ? vms_event_plan_parse_secondary_vendor_over_capacity_override($assignment['open_for_dispatch'] ?? true)
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
                        'mode_label' => (string) (vms_event_plan_secondary_vendor_mode_options()[sanitize_key((string) ($assignment['mode'] ?? 'standard'))] ?? __('Standard', 'backstage-venue-manager')),
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

                if (function_exists('vms_event_plan_perf_log')) {
                    vms_event_plan_perf_log('event_plan_vendor_availability_boot', $plan_id, $pool_trace_data);
                    vms_event_plan_perf_log('event_plan_vendor_conflict_boot', $plan_id, array(
                        'section' => 'secondary_vendor_summary_boot',
                        'phase' => $full_details ? 'run' : 'summary_only',
                        'reason' => $full_details ? 'secondary_vendor_editor_load' : 'initial_boot_summary',
                    ));
                }

                if (function_exists('vms_event_plan_perf_memory_checkpoint')) {
                    vms_event_plan_perf_memory_checkpoint($plan_id, 'secondary_vendor_availability_after', array(
                        'section' => 'secondary_vendor_summary_boot',
                        'detail_mode' => $full_details ? 'full' : 'summary_only',
                        'secondary_vendor_pool_count' => array_sum(array_map('count', (array) $type_pool_map)),
                        'secondary_vendor_warning_count' => count($secondary_missing) + count($secondary_mismatch) + count($secondary_unqualified),
                    ), 'secondary_vendor_availability');
                }

                $summary = array(
                    'secondary_types' => $secondary_types,
                    'secondary_type_options' => $secondary_type_options,
                    'secondary_mode_options' => function_exists('vms_event_plan_secondary_vendor_mode_options')
                        ? (array) vms_event_plan_secondary_vendor_mode_options()
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

                if (function_exists('vms_event_plan_perf_memory_checkpoint')) {
                    vms_event_plan_perf_memory_checkpoint($plan_id, 'secondary_vendor_summary_after', array(
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
                if (function_exists('vms_event_plan_perf_memory_checkpoint')) {
                    vms_event_plan_perf_memory_checkpoint($plan_id, 'readiness_before', array(
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

                if (function_exists('vms_event_plan_perf_memory_checkpoint')) {
                    vms_event_plan_perf_memory_checkpoint($plan_id, 'readiness_after', array(
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

        $requested_section = isset($_GET['vms_ep_load_section'])
            ? sanitize_key((string) wp_unslash($_GET['vms_ep_load_section']))
            : '';
        if ($requested_section !== $section) {
            return false;
        }

        $requested_plan_id = isset($_GET['post']) ? absint($_GET['post']) : 0;
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

        $venue_id   = isset($_POST['venue_id']) ? absint($_POST['venue_id']) : 0;
        $event_date = isset($_POST['event_date']) ? sanitize_text_field(wp_unslash($_POST['event_date'])) : '';

        if ($venue_id <= 0 || $event_date === '') {
            wp_send_json_success(array('row' => array()));
        }

        if (!function_exists('vms_get_event_plan_effective_comp_default')) {
            wp_send_json_error(array('message' => 'Effective default helper not loaded'), 500);
        }

        $resolved = (array) vms_get_event_plan_effective_comp_default($venue_id, $event_date);
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

        check_ajax_referer('vms_comp_options', 'nonce');

        $venue_id   = isset($_POST['venue_id']) ? absint($_POST['venue_id']) : 0;
        $vendor_id  = isset($_POST['vendor_id']) ? absint($_POST['vendor_id']) : 0;
        $event_date = isset($_POST['event_date']) ? sanitize_text_field(wp_unslash($_POST['event_date'])) : '';

        if (!function_exists('vms_get_event_plan_comp_options')) {
            wp_send_json_error(array('message' => 'Comp options helper not loaded'), 500);
        }

        $opts = vms_get_event_plan_comp_options($venue_id, $event_date, $vendor_id);
        $opts['_venue_selected'] = ($venue_id > 0);
        $opts['_date_selected'] = (!empty($event_date));
        $selected_opt = isset($_POST['selected_opt']) ? sanitize_text_field(wp_unslash($_POST['selected_opt'])) : '';
        $html = $this->render_comp_option_tiles_html($opts, 0, $selected_opt);

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

        check_ajax_referer('vms_event_plan_admin_section', 'nonce');

        if (!$this->event_plan_admin_section_supports_lazy_load($section)) {
            wp_send_json_error(array('message' => 'Section not supported.'), 400);
        }

        $post = get_post($post_id);
        if (!$post instanceof WP_Post || $post->post_type !== 'vms_event_plan') {
            wp_send_json_error(array('message' => 'Event Plan not found.'), 404);
        }

        $trace = function_exists('vms_event_plan_perf_span_start')
            ? vms_event_plan_perf_span_start(
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

                $vars = array(
                    'post' => $post,
                    'staff_assignments' => $staff_assignments,
                    'vms_staff_include_heading' => false,
                );
                $vars = array_merge($vars, $this->get_event_plan_staff_render_context($post_id, $staff_assignments));
                $html = $this->capture_event_plan_partial('staff', $vars);

                if (function_exists('vms_event_plan_perf_log')) {
                    vms_event_plan_perf_log('event_plan_staff_section_render', $post_id, array(
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
                $payload = $this->get_event_plan_secondary_vendors_module_payload($post_id);
                $html = (string) ($payload['html'] ?? '');

                if (function_exists('vms_event_plan_perf_log')) {
                    vms_event_plan_perf_log('event_plan_vendor_conflict_details', $post_id, array(
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
                $detail_context = $this->get_event_plan_readiness_detail_context($post_id);
                $html = $this->capture_event_plan_partial('readiness-details', array(
                    'post' => $post,
                    'vms_readiness_detail_context' => $detail_context,
                ));

                if (function_exists('vms_event_plan_perf_log')) {
                    vms_event_plan_perf_log('event_plan_readiness_details', $post_id, array(
                        'phase' => 'full',
                        'lazy_load' => 1,
                        'section' => 'readiness_details',
                    ));
                }

                wp_send_json_success(array(
                    'html' => $html,
                    'section' => $section,
                ));
            }

            wp_send_json_error(array('message' => 'Section not implemented.'), 400);
        } finally {
            if (function_exists('vms_event_plan_perf_span_finish')) {
                vms_event_plan_perf_span_finish(
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

        check_ajax_referer('vms_event_plan_secondary_vendors_save', 'nonce');

        $result = function_exists('vms_event_plan_save_secondary_vendors_module')
            ? vms_event_plan_save_secondary_vendors_module($post_id, (array) $_POST)
            : new WP_Error('vms_secondary_vendor_save_unavailable', __('Additional Vendors save helper is unavailable.', 'backstage-venue-manager'));
        if (is_wp_error($result)) {
            $error_code = (string) $result->get_error_code();
            wp_send_json_error(array(
                'code' => sanitize_key($error_code),
                'message' => sanitize_text_field((string) $result->get_error_message()),
            ), $error_code === 'vms_secondary_vendor_over_capacity' ? 400 : 500);
        }

        $payload = $this->get_event_plan_secondary_vendors_module_payload($post_id);
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

        check_ajax_referer('vms_event_plan_ticket_ui_overrides_save', 'nonce');

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

        $meta_key = function_exists('vms_meta_key')
            ? (vms_meta_key('event_plan', 'calendar_unpublished_suppress') ?: '_vms_calendar_unpublished_suppress')
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

        check_ajax_referer('vms_event_plan_calendar_unpublished_suppress_save', 'nonce');

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

        check_ajax_referer('vms_event_plan_admin_section', 'nonce');

        $trace = function_exists('vms_event_plan_perf_span_start')
            ? vms_event_plan_perf_span_start('event_plan_supporting_vendor_options_lazy_load', $post_id, array(
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
            $vendor_option_context = $this->build_event_plan_vendor_option_context(
                $post_id,
                is_array($bands) ? $bands : array(),
                (string) ($bundle['event_date'] ?? ''),
                absint($bundle['venue_id'] ?? 0),
                absint($bundle['band_vendor_id'] ?? 0),
                array(),
                true
            );
            $primary_html = is_string($vendor_option_context['primary_option_html'] ?? null)
                ? (string) $vendor_option_context['primary_option_html']
                : '';
            $supporting_html = is_string($vendor_option_context['supporting_option_html'] ?? null)
                ? (string) $vendor_option_context['supporting_option_html']
                : '';

            if (function_exists('vms_event_plan_perf_log')) {
                vms_event_plan_perf_log('event_plan_vendor_options', $post_id, array(
                    'phase' => 'lazy_full_vendor_options',
                    'option_mode' => 'shared_full_payload',
                    'lazy_load' => 1,
                    'primary_option_count' => count((array) ($vendor_option_context['primary_rows'] ?? array())),
                    'supporting_option_count' => count((array) ($vendor_option_context['supporting_rows'] ?? array())),
                    'primary_option_payload_bytes' => strlen($primary_html),
                    'shared_option_payload_bytes' => strlen($supporting_html),
                ));
            }

            wp_send_json_success(array(
                'primary_html' => $primary_html,
                'supporting_html' => $supporting_html,
            ));
        } finally {
            if (function_exists('vms_event_plan_perf_span_finish')) {
                vms_event_plan_perf_span_finish('event_plan_supporting_vendor_options_lazy_load', $post_id, $trace, array(
                    'section' => 'supporting_vendor_options',
                    'lazy_load' => 1,
                ));
            }
        }
    }

    private function event_plan_admin_section_supports_lazy_load(string $section): bool
    {
        return in_array($section, array('staff', 'secondary_vendors', 'readiness_details'), true);
    }

    private function build_event_plan_vendor_option_context(int $post_id, array $bands, string $event_date, int $venue_id_effective, int $selected_primary_vendor_id = 0, array $selected_supporting_vendor_ids = array(), bool $include_full_option_payload = false): array
    {
        $trace = function_exists('vms_event_plan_perf_span_start')
            ? vms_event_plan_perf_span_start('event_plan_vendor_option_context', $post_id, array(
                'section' => 'vendor_option_context',
                'band_count' => count($bands),
                'venue_id' => $venue_id_effective,
                'primary_mode' => $include_full_option_payload ? 'all' : 'selected_only',
                'supporting_mode' => $include_full_option_payload ? 'all' : 'selected_only',
            ))
            : '';

        try {
            if (function_exists('vms_event_plan_perf_memory_checkpoint')) {
                vms_event_plan_perf_memory_checkpoint($post_id, 'vendor_option_context_before', array(
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

            if (function_exists('vms_event_plan_perf_memory_checkpoint')) {
                vms_event_plan_perf_memory_checkpoint($post_id, 'vendor_option_context_after', array(
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
            if (function_exists('vms_event_plan_perf_span_finish')) {
                vms_event_plan_perf_span_finish('event_plan_vendor_option_context', $post_id, $trace, array(
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

    private function render_comp_option_tiles_html(array $opts, int $current_pkg_id = 0, string $selected_opt = ""): string
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
            $enabled = !empty($d['enabled']);
            $title = isset($d['title']) ? (string) $d['title'] : '';
            $sub = isset($d['subtitle']) ? (string) $d['subtitle'] : '';
            $terms = isset($d['terms']) && is_array($d['terms']) ? $d['terms'] : array();
            $g = isset($d['guarantee']) ? (float) $d['guarantee'] : 0.0;
            if ($g < 0) $g = 0.0;

            $structure = isset($terms['structure']) ? (string) $terms['structure'] : '';
            $flat = array_key_exists('flat_fee_amount', $terms) ? (string) $terms['flat_fee_amount'] : '';
            $split = array_key_exists('door_split_percent', $terms) ? (string) $terms['door_split_percent'] : '';
            $bonus_mode = array_key_exists('attendance_bonus_mode', $terms) ? (string) $terms['attendance_bonus_mode'] : '';
            $bonus_start = array_key_exists('attendance_bonus_start_count', $terms) ? (string) $terms['attendance_bonus_start_count'] : '';
            $bonus_step_size = array_key_exists('attendance_bonus_step_size', $terms) ? (string) $terms['attendance_bonus_step_size'] : '';
            $bonus_step_bonus = array_key_exists('attendance_bonus_step_bonus', $terms) ? (string) $terms['attendance_bonus_step_bonus'] : '';
            $bonus_per_ticket = array_key_exists('attendance_bonus_per_ticket_rate', $terms) ? (string) $terms['attendance_bonus_per_ticket_rate'] : '';
            $bonus_max = array_key_exists('attendance_bonus_max_bonus', $terms) ? (string) $terms['attendance_bonus_max_bonus'] : '';
            $commission_pct = array_key_exists('commission_percent', $terms) ? (string) $terms['commission_percent'] : '';
            $commission_mode = array_key_exists('commission_mode', $terms) ? (string) $terms['commission_mode'] : '';

            $opt = 'default:' . $k;
            $is_sel = ($sel !== '' && $sel === $opt);

            $is_max = ($enabled && $max > 0 && abs($g - $max) < 0.0001);
            $scale_class = $scale_class_for($enabled, $g);

            $html .= '<button type="button" class="vms-comp-opt-tile' . $scale_class . ($enabled ? '' : ' is-disabled') . ($is_sel ? ' is-selected' : '') . '"'
                . ' data-opt-kind="default" data-opt-key="' . esc_attr($k) . '"'
                . ' data-opt="' . esc_attr($opt) . '"'
                . ' data-structure="' . esc_attr($structure) . '"'
                . ' data-flat="' . esc_attr($flat) . '"'
                . ' data-split="' . esc_attr($split) . '"'
                . ' data-bonus-mode="' . esc_attr($bonus_mode) . '"'
                . ' data-bonus-start-count="' . esc_attr($bonus_start) . '"'
                . ' data-bonus-step-size="' . esc_attr($bonus_step_size) . '"'
                . ' data-bonus-step-bonus="' . esc_attr($bonus_step_bonus) . '"'
                . ' data-bonus-per-ticket-rate="' . esc_attr($bonus_per_ticket) . '"'
                . ' data-bonus-max-bonus="' . esc_attr($bonus_max) . '"'
                . ' data-commission-percent="' . esc_attr($commission_pct) . '"'
                . ' data-commission-mode="' . esc_attr($commission_mode) . '"'
                . ' data-package-id="0"'
                . ($enabled ? '' : ' disabled="disabled"')
                . '>';

            $html .= '<div class="vms-comp-opt-tile__title">' . esc_html($title) . '</div>';
            $html .= '<div class="vms-comp-opt-tile__value">' . ($enabled ? esc_html($fmt_money($g)) : '—') . '</div>';
            $html .= '<div class="vms-comp-opt-tile__sub">' . esc_html($sub) . '</div>';
            $html .= '<div class="vms-comp-opt-tile__badge' . ($is_max ? '' : ' vms-hidden') . '">' . esc_html__('Highest guaranteed', 'backstage-venue-manager') . '</div>';
            $html .= '</button>';
        }

        $html .= '</div>';
        $html .= '</div>';

        $html .= '<div class="vms-comp-opt-row vms-comp-opt-row--packages">';
        $html .= '<div class="vms-comp-opt-row__title"><strong>' . esc_html__('Packages (Optional presets)', 'backstage-venue-manager') . '</strong></div>';
 
        if (empty($packages)) {
            if (!$venue_selected) {
                $html .= '<div class="notice notice-info inline vms-notice vms-notice--info vms-notice-tight"><p><em>' . esc_html__('Select a Venue above to load packages.', 'backstage-venue-manager') . '</em></p></div>';
            } else {
                $html .= '<div class="notice notice-info inline vms-notice vms-notice--info vms-notice-tight"><p><strong>' . esc_html__('No Comp Packages are available for the selected venue yet.', 'backstage-venue-manager') . '</strong></p></div>';
            }
        } else {
            $html .= '<div class="vms-comp-opt-tiles vms-comp-opt-tiles--packages">';

            foreach ($packages as $p) {
                if (!is_array($p)) continue;
                $enabled = !empty($p['enabled']);
                $pid = isset($p['id']) ? (int) $p['id'] : 0;
                $title = isset($p['title']) ? (string) $p['title'] : '';
                $sub = isset($p['subtitle']) ? (string) $p['subtitle'] : '';
                $terms = isset($p['terms']) && is_array($p['terms']) ? $p['terms'] : array();
                $g = isset($p['guarantee']) ? (float) $p['guarantee'] : 0.0;
                if ($g < 0) $g = 0.0;

                $structure = isset($terms['structure']) ? (string) $terms['structure'] : '';
                $flat = array_key_exists('flat_fee_amount', $terms) ? (string) $terms['flat_fee_amount'] : '';
                $split = array_key_exists('door_split_percent', $terms) ? (string) $terms['door_split_percent'] : '';
                $bonus_mode = array_key_exists('attendance_bonus_mode', $terms) ? (string) $terms['attendance_bonus_mode'] : '';
                $bonus_start = array_key_exists('attendance_bonus_start_count', $terms) ? (string) $terms['attendance_bonus_start_count'] : '';
                $bonus_step_size = array_key_exists('attendance_bonus_step_size', $terms) ? (string) $terms['attendance_bonus_step_size'] : '';
                $bonus_step_bonus = array_key_exists('attendance_bonus_step_bonus', $terms) ? (string) $terms['attendance_bonus_step_bonus'] : '';
                $bonus_per_ticket = array_key_exists('attendance_bonus_per_ticket_rate', $terms) ? (string) $terms['attendance_bonus_per_ticket_rate'] : '';
                $bonus_max = array_key_exists('attendance_bonus_max_bonus', $terms) ? (string) $terms['attendance_bonus_max_bonus'] : '';
                $commission_pct = array_key_exists('commission_percent', $terms) ? (string) $terms['commission_percent'] : '';
                $commission_mode = array_key_exists('commission_mode', $terms) ? (string) $terms['commission_mode'] : '';

                $is_max = ($enabled && $max > 0 && abs($g - $max) < 0.0001);
                $scale_class = $scale_class_for($enabled, $g);

                $opt = 'package:' . (int) $pid;
                $is_sel = ($sel !== '' ? ($sel === $opt) : ($pid > 0 && $pid === (int) $current_pkg_id));

                $html .= '<button type="button" class="vms-comp-opt-tile' . $scale_class . ($enabled ? '' : ' is-disabled') . ($is_sel ? ' is-selected' : '') . '"'
                    . ' data-opt-kind="package" data-opt-id="' . esc_attr($pid) . '"'
                    . ' data-opt="' . esc_attr($opt) . '"'
                    . ' data-structure="' . esc_attr($structure) . '"'
                    . ' data-flat="' . esc_attr($flat) . '"'
                    . ' data-split="' . esc_attr($split) . '"'
                    . ' data-bonus-mode="' . esc_attr($bonus_mode) . '"'
                    . ' data-bonus-start-count="' . esc_attr($bonus_start) . '"'
                    . ' data-bonus-step-size="' . esc_attr($bonus_step_size) . '"'
                    . ' data-bonus-step-bonus="' . esc_attr($bonus_step_bonus) . '"'
                    . ' data-bonus-per-ticket-rate="' . esc_attr($bonus_per_ticket) . '"'
                    . ' data-bonus-max-bonus="' . esc_attr($bonus_max) . '"'
                    . ' data-commission-percent="' . esc_attr($commission_pct) . '"'
                    . ' data-commission-mode="' . esc_attr($commission_mode) . '"'
                    . ' data-package-id="' . esc_attr($pid) . '"'
                    . ($enabled ? '' : ' disabled="disabled"')
                    . '>';

                $html .= '<div class="vms-comp-opt-tile__title">' . esc_html($title) . '</div>';
                $html .= '<div class="vms-comp-opt-tile__value">' . ($enabled ? esc_html($fmt_money($g)) : '—') . '</div>';
                $html .= '<div class="vms-comp-opt-tile__sub">' . esc_html($sub) . '</div>';
                $html .= '<div class="vms-comp-opt-tile__badge' . ($is_max ? '' : ' vms-hidden') . '">' . esc_html__('Highest guaranteed', 'backstage-venue-manager') . '</div>';
                $html .= '</button>';
            }
 
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
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

        if ($band_id > 0 && function_exists('vms_event_plan_vendor_exists') && !vms_event_plan_vendor_exists($band_id)) {
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
                        if ($kind !== '' && function_exists('vms_cancellation_notification_kind_group')) {
                            $fallback_group = sanitize_key((string) vms_cancellation_notification_kind_group($kind));
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
            if (function_exists('vms_cancellation_notification_kind_label')) {
                $kind_label = sanitize_text_field((string) vms_cancellation_notification_kind_label($kind));
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
                if (function_exists('vms_cancellation_notification_kind_label')) {
                    $alias_label = sanitize_text_field((string) vms_cancellation_notification_kind_label($alias_kind));
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
        $k_job_id = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'cancel_job_id') ?: '_vms_cancel_job_id') : '_vms_cancel_job_id';
        $k_job_state = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'cancel_job_state') ?: '_vms_cancel_job_state') : '_vms_cancel_job_state';
        $k_job_summary = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'cancel_job_summary') ?: '_vms_cancel_job_summary') : '_vms_cancel_job_summary';
        $k_cancel_review = function_exists('vms_meta_key')
            ? (vms_meta_key('event_plan', 'cancel_requires_operator_review') ?: '_vms_cancel_requires_operator_review')
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
            && function_exists('vms_cancellation_backfill_legacy_job')
        ) {
            $repair = (array) vms_cancellation_backfill_legacy_job($post_id, array(
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

        $job_state_labels = function_exists('vms_cancellation_job_statuses')
            ? (array) vms_cancellation_job_statuses()
            : array();
        $step_status_labels = function_exists('vms_cancellation_step_statuses')
            ? (array) vms_cancellation_step_statuses()
            : array();
        $step_labels = function_exists('vms_cancellation_step_labels')
            ? (array) vms_cancellation_step_labels()
            : array();

        $job_state_label = isset($job_state_labels[$job_state]) ? (string) $job_state_labels[$job_state] : strtoupper($job_state ?: 'queued');
        $policy = sanitize_key((string) ($summary['policy'] ?? ''));
        $reason = sanitize_key((string) ($summary['reason_code'] ?? ''));
        $policy_labels = function_exists('vms_cancellation_policy_options') ? (array) vms_cancellation_policy_options() : array();
        $reason_labels = function_exists('vms_cancellation_reason_options') ? (array) vms_cancellation_reason_options() : array();
        $policy_label = isset($policy_labels[$policy]) ? (string) $policy_labels[$policy] : $policy;
        $reason_label = isset($reason_labels[$reason]) ? (string) $reason_labels[$reason] : $reason;
        $created = $this->format_cancellation_gmt((string) ($summary['created_at_gmt'] ?? ''));
        $requires_review = ((string) get_post_meta($post_id, $k_cancel_review, true) === '1');
        $retry_allowed = ($plan_status === 'cancelled' && function_exists('vms_cancellation_retry_step'));
        $queue_only_policies = array('stop_sales_queue_refunds');
        $auto_refund_policies = function_exists('vms_cancellation_auto_refund_policies')
            ? array_values(array_unique(array_filter(array_map('sanitize_key', (array) vms_cancellation_auto_refund_policies()))))
            : array('stop_sales_auto_refund', 'stop_sales_auto_refund_remove_attendees');
        $refund_capable_policies = array_values(array_unique(array_merge($queue_only_policies, $auto_refund_policies)));
        $allow_manual_live_refunds = (
            $plan_status === 'cancelled'
            && $job_id !== ''
            && $job_state !== 'running'
            && function_exists('vms_cancellation_request_live_refund_run')
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
                <?php if ($retry_allowed && $has_retryable_steps && function_exists('vms_cancellation_retry_all_failed_steps')) : ?>
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
                        $live_refund_source_post_id = isset($_GET['post']) ? absint($_GET['post']) : 0;
                    }
                    if ($live_refund_source_post_id <= 0) {
                        global $post;
                        if ($post instanceof WP_Post) {
                            $live_refund_source_post_id = (int) $post->ID;
                        }
                    }

                    $live_refund_plan_id = $live_refund_source_post_id;
                    $live_refund_source_post_type = sanitize_key((string) get_post_type($live_refund_source_post_id));
                    if ($live_refund_source_post_type !== 'vms_event_plan' && function_exists('vms_get_event_plan_for_tec_event')) {
                        $resolved_live_refund_plan_id = (int) vms_get_event_plan_for_tec_event($live_refund_source_post_id);
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
                            '_wpnonce'            => wp_create_nonce('vms_run_live_refunds_now_' . $live_refund_nonce_post_id),
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
        $trace = function_exists('vms_event_plan_perf_span_start')
            ? vms_event_plan_perf_span_start('event_plan_staff_render_context', $post_id, array('section' => 'staffing'))
            : '';

        try {
        $staff_roles = get_terms(array(
            'taxonomy'   => 'vms_staff_role',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ));

        $staff_slots = function_exists('vms_staffing_get_event_slots')
            ? (array) vms_staffing_get_event_slots($post_id, true)
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

        $staff_role_meta_map = function_exists('vms_staffing_role_map_by_id') ? (array) vms_staffing_role_map_by_id(true) : array();

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

        $staff_assigned_by_role = function_exists('vms_staffing_get_event_assigned_staff_map')
            ? (array) vms_staffing_get_event_assigned_staff_map($post_id)
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

                    $candidate_status = function_exists('vms_staffing_staff_candidate_status_for_role')
                        ? (array) vms_staffing_staff_candidate_status_for_role($sid, $role_id)
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
            $missing = function_exists('vms_vendor_tax_profile_missing_items') ? (array) vms_vendor_tax_profile_missing_items($sid) : array();
            $tax_ok = empty($missing);

            $bypass_active = false;
            $bypass_until = '';
            if (function_exists('vms_get_tax_bypass_status')) {
                $b = (array) vms_get_tax_bypass_status($sid);
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

        $staff_activation_thresholds = function_exists('vms_staffing_get_event_role_activation_thresholds')
            ? (array) vms_staffing_get_event_role_activation_thresholds($post_id)
            : array();
        $staff_headcount_context = function_exists('vms_staffing_get_event_plan_headcount_context')
            ? (array) vms_staffing_get_event_plan_headcount_context($post_id)
            : array('wired' => false, 'headcount' => 0, 'label' => __('Attendance not wired yet', 'backstage-venue-manager'));
        $staff_headcount_wired = !empty($staff_headcount_context['wired']);
        $staff_current_headcount = max(0, (int) ($staff_headcount_context['headcount'] ?? 0));
        $staff_headcount_label = isset($staff_headcount_context['label']) ? (string) $staff_headcount_context['label'] : __('Attendance not wired yet', 'backstage-venue-manager');
        $staffing_templates = function_exists('vms_staffing_get_templates')
            ? (array) vms_staffing_get_templates(array('is_active' => 1))
            : array();
        $staff_applied_template_id = function_exists('vms_staffing_get_event_applied_template_id')
            ? (int) vms_staffing_get_event_applied_template_id($post_id)
            : 0;
        $staff_applied_template = ($staff_applied_template_id > 0 && function_exists('vms_staffing_get_template'))
            ? vms_staffing_get_template($staff_applied_template_id)
            : null;
        $staff_recommended_template = function_exists('vms_staffing_get_recommended_template_for_event_plan')
            ? vms_staffing_get_recommended_template_for_event_plan($post_id)
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
            if (function_exists('vms_event_plan_perf_span_finish')) {
                vms_event_plan_perf_span_finish('event_plan_staff_render_context', $post_id, $trace, array('section' => 'staffing'));
            }
        }
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
        $trace = function_exists('vms_event_plan_perf_span_start')
            ? vms_event_plan_perf_span_start('event_plan_compensation_render_context', $post_id, array('section' => 'compensation'))
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

        $k_low_ack = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'low_guarantee_ack') : '_vms_low_guarantee_ack';
        $k_low_ack_ts = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'low_guarantee_ack_ts') : '_vms_low_guarantee_ack_ts';
        $k_low_ack_user = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'low_guarantee_ack_user_id') : '_vms_low_guarantee_ack_user_id';
        $k_low_ack_snapshot = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'low_guarantee_ack_snapshot') : '_vms_low_guarantee_ack_snapshot';

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
        $vms_vendor_default_summary = function_exists('vms_snapshot_summary_line')
            ? (string) vms_snapshot_summary_line($vms_vendor_default_terms)
            : '';
        $vms_actual_draft_summary = function_exists('vms_snapshot_summary_line')
            ? (string) vms_snapshot_summary_line($vms_actual_terms)
            : '';

        $vms_format_comp_field_value = static function (string $field, array $terms) use ($vms_norm_num): string {
            $raw = $terms[$field] ?? '';
            if ($raw === '' || $raw === null) {
                return '—';
            }

            switch ($field) {
                case 'structure':
                    return function_exists('vms_pretty_structure_label') ? (string) vms_pretty_structure_label((string) $raw) : (string) $raw;
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
            if (function_exists('vms_get_vendor_default_comp_package_id') && function_exists('vms_get_comp_package_terms')) {
                $vms_vendor_package_id = (int) vms_get_vendor_default_comp_package_id($selected_band_id);
                if ($vms_vendor_package_id > 0) {
                    $vms_vendor_global_terms = array_merge($vms_vendor_global_terms, (array) vms_get_comp_package_terms($vms_vendor_package_id));
                    $vms_package_pct = function_exists('vms_normalize_agent_fee_percent') ? vms_normalize_agent_fee_percent(get_post_meta($vms_vendor_package_id, '_vms_commission_percent', true)) : null;
                    $vms_package_mode = function_exists('vms_normalize_agent_fee_mode') ? vms_normalize_agent_fee_mode(get_post_meta($vms_vendor_package_id, '_vms_commission_mode', true)) : '';
                    if ($vms_package_pct !== null && $vms_package_pct > 0) {
                        $vms_vendor_global_terms['commission_percent'] = $vms_package_pct;
                        $vms_vendor_global_terms['commission_mode'] = ($vms_package_mode !== '') ? $vms_package_mode : 'artist_fee';
                    }
                }
            }

            $vms_vendor_structure_key = function_exists('vms_meta_key') ? (vms_meta_key('vendor', 'default_comp_structure') ?: '_vms_default_comp_structure') : '_vms_default_comp_structure';
            $vms_vendor_flat_key = function_exists('vms_meta_key') ? (vms_meta_key('vendor', 'default_flat_fee_amount') ?: '_vms_default_flat_fee_amount') : '_vms_default_flat_fee_amount';
            $vms_vendor_split_key = function_exists('vms_meta_key') ? (vms_meta_key('vendor', 'default_door_split_percent') ?: '_vms_default_door_split_percent') : '_vms_default_door_split_percent';
            $vms_vendor_bonus_mode_key = function_exists('vms_meta_key') ? (vms_meta_key('vendor', 'default_attendance_bonus_mode') ?: '_vms_default_attendance_bonus_mode') : '_vms_default_attendance_bonus_mode';
            $vms_vendor_bonus_start_key = function_exists('vms_meta_key') ? (vms_meta_key('vendor', 'default_attendance_bonus_start_count') ?: '_vms_default_attendance_bonus_start_count') : '_vms_default_attendance_bonus_start_count';
            $vms_vendor_bonus_step_size_key = function_exists('vms_meta_key') ? (vms_meta_key('vendor', 'default_attendance_bonus_step_size') ?: '_vms_default_attendance_bonus_step_size') : '_vms_default_attendance_bonus_step_size';
            $vms_vendor_bonus_step_bonus_key = function_exists('vms_meta_key') ? (vms_meta_key('vendor', 'default_attendance_bonus_step_bonus') ?: '_vms_default_attendance_bonus_step_bonus') : '_vms_default_attendance_bonus_step_bonus';
            $vms_vendor_bonus_per_ticket_key = function_exists('vms_meta_key') ? (vms_meta_key('vendor', 'default_attendance_bonus_per_ticket_rate') ?: '_vms_default_attendance_bonus_per_ticket_rate') : '_vms_default_attendance_bonus_per_ticket_rate';
            $vms_vendor_bonus_max_key = function_exists('vms_meta_key') ? (vms_meta_key('vendor', 'default_attendance_bonus_max_bonus') ?: '_vms_default_attendance_bonus_max_bonus') : '_vms_default_attendance_bonus_max_bonus';

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
            if (function_exists('vms_normalize_comp_terms')) {
                $vms_vendor_global_meta_terms = (array) vms_normalize_comp_terms($vms_vendor_global_meta_terms);
            }
            if (!empty($vms_vendor_global_meta_terms)) {
                $vms_vendor_global_terms = array_merge($vms_vendor_global_terms, $vms_vendor_global_meta_terms);
            }
            if (function_exists('vms_get_vendor_default_agent_fee_terms')) {
                $vms_vendor_global_terms = array_merge($vms_vendor_global_terms, (array) vms_get_vendor_default_agent_fee_terms($selected_band_id));
            }
            if (function_exists('vms_normalize_comp_terms')) {
                $vms_vendor_global_terms = (array) vms_normalize_comp_terms($vms_vendor_global_terms);
            }

            $vms_vendor_venue_terms = array();
            if ($venue_id_effective > 0 && function_exists('vms_get_vendor_default_comp_by_venue_map')) {
                $vms_vendor_by_venue_map = (array) vms_get_vendor_default_comp_by_venue_map($selected_band_id);
                if (isset($vms_vendor_by_venue_map[$venue_id_effective]) && is_array($vms_vendor_by_venue_map[$venue_id_effective])) {
                    $vms_vendor_venue_terms = $vms_vendor_by_venue_map[$venue_id_effective];
                    if (function_exists('vms_normalize_comp_terms')) {
                        $vms_vendor_venue_terms = (array) vms_normalize_comp_terms($vms_vendor_venue_terms);
                    }
                }
            }

            $vms_vendor_venue_dow_terms = array();
            if ($venue_id_effective > 0 && $event_date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date) && function_exists('vms_get_vendor_default_comp_by_venue_dow_map')) {
                $vms_vendor_by_venue_dow_map = (array) vms_get_vendor_default_comp_by_venue_dow_map($selected_band_id);
                if (isset($vms_vendor_by_venue_dow_map[$venue_id_effective]) && is_array($vms_vendor_by_venue_dow_map[$venue_id_effective])) {
                    $vms_tz = function_exists('vms_get_timezone') ? vms_get_timezone() : wp_timezone();
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
                            if (function_exists('vms_normalize_comp_terms')) {
                                $vms_vendor_venue_dow_terms = (array) vms_normalize_comp_terms($vms_vendor_venue_dow_terms);
                            }
                        }
                    }
                }
            }

            $vms_vendor_default_source_rows = array(
                array(
                    'label' => __('Global vendor defaults', 'backstage-venue-manager'),
                    'summary' => !empty($vms_vendor_global_terms) && function_exists('vms_snapshot_summary_line')
                        ? (string) vms_snapshot_summary_line($vms_vendor_global_terms)
                        : __('Not configured', 'backstage-venue-manager'),
                    'is_active' => ($vms_vendor_default_subtitle === __('Global vendor defaults', 'backstage-venue-manager')),
                ),
                array(
                    'label' => __('Venue-specific defaults', 'backstage-venue-manager'),
                    'summary' => !empty($vms_vendor_venue_terms) && function_exists('vms_snapshot_summary_line')
                        ? (string) vms_snapshot_summary_line($vms_vendor_venue_terms)
                        : __('Not configured for this venue', 'backstage-venue-manager'),
                    'is_active' => ($vms_vendor_default_subtitle === __('Venue-specific defaults', 'backstage-venue-manager')),
                ),
                array(
                    'label' => __('Venue + day defaults', 'backstage-venue-manager'),
                    'summary' => !empty($vms_vendor_venue_dow_terms) && function_exists('vms_snapshot_summary_line')
                        ? (string) vms_snapshot_summary_line($vms_vendor_venue_dow_terms)
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
            if (function_exists('vms_event_plan_perf_span_finish')) {
                vms_event_plan_perf_span_finish('event_plan_compensation_render_context', $post_id, $trace, array('section' => 'compensation'));
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
        $trace = function_exists('vms_event_plan_perf_span_start')
            ? vms_event_plan_perf_span_start('event_plan_partial_render_' . sanitize_key($partial), $plan_id, array('section' => 'partial_render', 'partial' => $partial))
            : '';
        $html = '';

        if ($memory_group !== '' && function_exists('vms_event_plan_perf_memory_checkpoint')) {
            vms_event_plan_perf_memory_checkpoint($plan_id, $memory_group . '_before', array(
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
            if (function_exists('vms_event_plan_perf_span_finish')) {
                vms_event_plan_perf_span_finish('event_plan_partial_render_' . sanitize_key($partial), $plan_id, $trace, array(
                    'section' => 'partial_render',
                    'partial' => $partial,
                    'rendered_html_bytes' => strlen($html),
                ));
            }
            if ($memory_group !== '' && function_exists('vms_event_plan_perf_memory_checkpoint')) {
                vms_event_plan_perf_memory_checkpoint($plan_id, $memory_group . '_after', array(
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
        if (function_exists('vms_event_plan_perf_query_checkpoint')) {
            vms_event_plan_perf_query_checkpoint((int) $post->ID, 'before_details_render', array(
                'section' => 'meta_box_render',
                'screen_action' => isset($_REQUEST['action']) ? sanitize_key((string) wp_unslash($_REQUEST['action'])) : '',
                'before_details_render' => 1,
            ), 'admin_boot');
        }

        $render_trace = function_exists('vms_event_plan_perf_span_start')
            ? vms_event_plan_perf_span_start('event_plan_details_meta_box_render', (int) $post->ID, array('section' => 'meta_box_render'))
            : '';

        try {
        if (function_exists('vms_event_plan_perf_memory_checkpoint')) {
            vms_event_plan_perf_memory_checkpoint((int) $post->ID, 'details_meta_box_before', array(
                'section' => 'meta_box_render',
                'capture_dependency_snapshot' => 1,
            ), 'details_meta_box');
        }
        wp_nonce_field('vms_save_event_plan_details', 'vms_event_plan_details_nonce');
        echo '<input type="hidden" name="vms_reopen_section_after_save" id="vms-reopen-section-after-save" value="" />';
        $scroll_to = (string) get_post_meta($post->ID, '_vms_admin_scroll_to', true);

        // ----------------------------
        // Load core meta
        // ----------------------------
        $event_date     = (string) get_post_meta($post->ID, '_vms_event_date', true);
        $start_time     = (string) get_post_meta($post->ID, '_vms_start_time', true);
        $end_time       = (string) get_post_meta($post->ID, '_vms_end_time', true);

        // Venue: saved vs UI default (important for "packages show on first load")
        $venue_id_saved = (int) get_post_meta($post->ID, '_vms_venue_id', true);

        $venue_id_ui = 0;
        if ($venue_id_saved <= 0 && function_exists('vms_get_current_venue_id')) {
            $venue_id_ui = (int) vms_get_current_venue_id();
        }

        // ----------------------------
        // Schedule "Create/Add" defaults (first-load only)
        // ----------------------------
        if ($post->post_status === 'auto-draft') {

            $qs_venue = isset($_GET['vms_venue_id']) ? (int) $_GET['vms_venue_id'] : 0;
            $qs_date  = isset($_GET['vms_date']) ? sanitize_text_field(wp_unslash($_GET['vms_date'])) : '';

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
        if ($venue_id_effective > 0 && (empty($start_time) || empty($end_time)) && function_exists('vms_get_venue_default_times')) {
            $defaults = (array) vms_get_venue_default_times($venue_id_effective);

            if (empty($start_time) && !empty($defaults['start'])) {
                $start_time = (string) $defaults['start'];
            }
            if (empty($end_time)) {
                if (!empty($defaults['end'])) {
                    $end_time = (string) $defaults['end'];
                } elseif (!empty($start_time) && !empty($defaults['dur']) && function_exists('vms_time_add_minutes')) {
                    $end_time = (string) vms_time_add_minutes($start_time, (int) $defaults['dur']);
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
        $k_commission_override_none = function_exists('vms_meta_key')
            ? (vms_meta_key('event_plan', 'commission_override_none') ?: '_vms_commission_override_none')
            : '_vms_commission_override_none';
        $commission_override_none = (string) get_post_meta($post->ID, $k_commission_override_none, true) === '1';
        if ($commission_override_none) {
            $commission_percent = '';
            $commission_mode = 'artist_fee';
        }

        $deposit_terms = function_exists('vms_get_event_plan_deposit_terms')
            ? (array) vms_get_event_plan_deposit_terms((int) $post->ID)
            : array();
        $deposit_amount = $deposit_terms['deposit_amount'] ?? '';
        $deposit_status = function_exists('vms_normalize_comp_deposit_status')
            ? vms_normalize_comp_deposit_status($deposit_terms['deposit_status'] ?? '')
            : (string) ($deposit_terms['deposit_status'] ?? 'not_required');
        $deposit_treatment = function_exists('vms_normalize_comp_deposit_treatment')
            ? vms_normalize_comp_deposit_treatment($deposit_terms['deposit_treatment'] ?? '')
            : (string) ($deposit_terms['deposit_treatment'] ?? 'creditable');
        $deposit_due_date = function_exists('vms_normalize_comp_deposit_date')
            ? vms_normalize_comp_deposit_date($deposit_terms['deposit_due_date'] ?? '')
            : (string) ($deposit_terms['deposit_due_date'] ?? '');
        $deposit_paid_date = function_exists('vms_normalize_comp_deposit_date')
            ? vms_normalize_comp_deposit_date($deposit_terms['deposit_paid_date'] ?? '')
            : (string) ($deposit_terms['deposit_paid_date'] ?? '');
        $deposit_notes = isset($deposit_terms['deposit_notes']) ? (string) $deposit_terms['deposit_notes'] : '';
        $deposit_status_options = function_exists('vms_comp_deposit_status_options') ? vms_comp_deposit_status_options() : array(
            'not_required' => __('Not required', 'backstage-venue-manager'),
            'unpaid' => __('Unpaid', 'backstage-venue-manager'),
            'paid' => __('Paid', 'backstage-venue-manager'),
            'waived' => __('Waived', 'backstage-venue-manager'),
            'refunded' => __('Refunded', 'backstage-venue-manager'),
        );
        $deposit_treatment_options = function_exists('vms_comp_deposit_treatment_options') ? vms_comp_deposit_treatment_options() : array(
            'creditable' => __('Applies toward total payment', 'backstage-venue-manager'),
            'refundable' => __('Refundable', 'backstage-venue-manager'),
            'nonrefundable' => __('Non-refundable', 'backstage-venue-manager'),
        );

        $final_payment_terms = function_exists('vms_get_event_plan_final_payment_terms')
            ? (array) vms_get_event_plan_final_payment_terms((int) $post->ID)
            : array();
        $final_payment_timing = function_exists('vms_normalize_comp_final_payment_timing')
            ? vms_normalize_comp_final_payment_timing($final_payment_terms['final_payment_timing'] ?? '')
            : (string) ($final_payment_terms['final_payment_timing'] ?? 'not_set');
        $final_payment_days_after = function_exists('vms_normalize_comp_final_payment_days_after')
            ? vms_normalize_comp_final_payment_days_after($final_payment_terms['final_payment_days_after'] ?? '')
            : (string) ($final_payment_terms['final_payment_days_after'] ?? '');
        $final_payment_date = function_exists('vms_normalize_comp_final_payment_date')
            ? vms_normalize_comp_final_payment_date($final_payment_terms['final_payment_date'] ?? '')
            : (string) ($final_payment_terms['final_payment_date'] ?? '');
        $final_payment_custom_text = isset($final_payment_terms['final_payment_custom_text']) ? (string) $final_payment_terms['final_payment_custom_text'] : '';
        $final_payment_method = function_exists('vms_normalize_comp_final_payment_method')
            ? vms_normalize_comp_final_payment_method($final_payment_terms['final_payment_method'] ?? '')
            : (string) ($final_payment_terms['final_payment_method'] ?? 'not_set');
        $final_payment_method_other = isset($final_payment_terms['final_payment_method_other']) ? (string) $final_payment_terms['final_payment_method_other'] : '';
        $final_payment_timing_options = function_exists('vms_comp_final_payment_timing_options') ? vms_comp_final_payment_timing_options() : array(
            'not_set' => __('Not set', 'backstage-venue-manager'),
            'in_advance' => __('In advance', 'backstage-venue-manager'),
            'day_of_event' => __('Day of event', 'backstage-venue-manager'),
            'days_after' => __('N days after event', 'backstage-venue-manager'),
            'fixed_date' => __('Specific date', 'backstage-venue-manager'),
            'custom' => __('Custom timing', 'backstage-venue-manager'),
        );
        $final_payment_method_options = function_exists('vms_comp_final_payment_method_options') ? vms_comp_final_payment_method_options() : array(
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

        $k_comp_selected_option = function_exists('vms_meta_key')
            ? (vms_meta_key('event_plan', 'comp_selected_option') ?: '_vms_comp_selected_option')
            : '_vms_comp_selected_option';
        $selected_opt = (string) get_post_meta($post->ID, $k_comp_selected_option, true);
        if ($selected_opt === '' && (int) $current_pkg_id > 0) {
            $selected_opt = 'package:' . (int) $current_pkg_id;
        }

        // Snapshot (locked pay)
        $snapshot = get_post_meta($post->ID, '_vms_comp_snapshot', true);
        if (!is_array($snapshot)) $snapshot = array();

        $needs_snapshot = (get_post_meta($post->ID, '_vms_comp_needs_snapshot', true) === '1');

        $current_hash  = function_exists('vms_comp_hash_for_plan') ? (string) vms_comp_hash_for_plan((int)$post->ID) : '';
        $snapshot_hash = isset($snapshot['comp_hash']) ? (string) $snapshot['comp_hash'] : '';

        $out_of_sync = false;
        if (!empty($snapshot)) {
            if ($snapshot_hash !== '' && $current_hash !== '' && $snapshot_hash !== $current_hash) $out_of_sync = true;
            if ($needs_snapshot) $out_of_sync = true;
        }

        // Load packages for effective venue (+ global)
        $packages = array();
        if ($venue_id_effective > 0 && function_exists('vms_get_comp_packages_for_venue')) {
            $packages = (array) vms_get_comp_packages_for_venue($venue_id_effective, true);
        }

        // Plan status LEGACY
        // $plan_status = (string) get_post_meta($post->ID, '_vms_event_plan_status', true);
        // if ($plan_status === '') $plan_status = 'draft';

        // Plan status (validated against canonical registry)
        // updated this during constants.php > meta-keys.php refactor
        $plan_status = (string) get_post_meta($post->ID, vms_meta_key('event_plan', 'status'), true);

        $allowed = function_exists('vms_event_plan_statuses')
            ? array_keys(vms_event_plan_statuses())
            : array('draft', 'ready', 'published', 'tentative', 'confirmed', 'cancelled', 'archived');

        if ($plan_status === '' || !in_array($plan_status, $allowed, true)) {
            $plan_status = 'draft';
        }
        // END Plan status (validated against canonical registry)

        $k_cancel_policy = function_exists('vms_meta_key')
            ? (vms_meta_key('event_plan', 'cancel_policy') ?: '_vms_cancel_policy')
            : '_vms_cancel_policy';
        $k_cancel_reason_code = function_exists('vms_meta_key')
            ? (vms_meta_key('event_plan', 'cancel_reason_code') ?: '_vms_cancel_reason_code')
            : '_vms_cancel_reason_code';
        $k_cancel_reason_note = function_exists('vms_meta_key')
            ? (vms_meta_key('event_plan', 'cancel_reason_note') ?: '_vms_cancel_reason_note')
            : '_vms_cancel_reason_note';
        $k_cancel_vendor_message = function_exists('vms_meta_key')
            ? (vms_meta_key('event_plan', 'cancel_vendor_message') ?: '_vms_cancel_vendor_message')
            : '_vms_cancel_vendor_message';

        $cancel_policy = sanitize_key((string) get_post_meta($post->ID, $k_cancel_policy, true));
        $cancel_reason_code = sanitize_key((string) get_post_meta($post->ID, $k_cancel_reason_code, true));
        $cancel_reason_note = trim((string) get_post_meta($post->ID, $k_cancel_reason_note, true));
        $cancel_vendor_message = trim((string) get_post_meta($post->ID, $k_cancel_vendor_message, true));

        $cancel_policy_options = function_exists('vms_cancellation_policy_options')
            ? (array) vms_cancellation_policy_options()
            : array('status_only' => __('Status only', 'backstage-venue-manager'));
        $cancel_reason_options = function_exists('vms_cancellation_reason_options')
            ? (array) vms_cancellation_reason_options()
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
        $dropdown_trace = function_exists('vms_event_plan_perf_span_start')
            ? vms_event_plan_perf_span_start('event_plan_primary_vendor_lookup', (int) $post->ID, array('section' => 'primary_vendor_lookup'))
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
            if (function_exists('vms_event_plan_perf_span_finish')) {
                vms_event_plan_perf_span_finish('event_plan_primary_vendor_lookup', (int) $post->ID, $dropdown_trace, array('section' => 'primary_vendor_lookup'));
            }
        }

        if (function_exists('vms_event_plan_perf_memory_checkpoint')) {
            vms_event_plan_perf_memory_checkpoint((int) $post->ID, 'details_meta_box_after_dropdowns', array(
                'section' => 'meta_box_render',
                'venue_count' => is_array($venues) ? count($venues) : 0,
                'vendor_count' => is_array($bands) ? count($bands) : 0,
            ), 'details_meta_box');
        }

        $selected_band_id = (int) get_post_meta($post->ID, '_vms_band_vendor_id', true);

        if ($post->post_status === 'auto-draft' && $selected_band_id <= 0) {
            $qs_band_vendor = isset($_GET['vms_band_vendor_id']) ? absint($_GET['vms_band_vendor_id']) : 0;
            if ($qs_band_vendor <= 0 && isset($_GET['vms_prefill_vendor_mode'], $_GET['vms_prefill_vendor_id'])) {
                $qs_prefill_mode = sanitize_key(wp_unslash((string) $_GET['vms_prefill_vendor_mode']));
                if ($qs_prefill_mode === 'primary') {
                    $qs_band_vendor = absint($_GET['vms_prefill_vendor_id']);
                }
            }
            if ($qs_band_vendor > 0) {
                $selected_band_id = $qs_band_vendor;
            }
        }

        if (!$commission_override_none && ($commission_percent === '' || $commission_percent === null) && $selected_band_id > 0 && function_exists('vms_get_vendor_default_agent_fee_terms')) {
            $vendor_agent_fee = (array) vms_get_vendor_default_agent_fee_terms($selected_band_id);
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

        $comp_options_nonce = wp_create_nonce('vms_comp_options');
        $comp_opts_trace = function_exists('vms_event_plan_perf_span_start')
            ? vms_event_plan_perf_span_start('event_plan_comp_options_lookup', (int) $post->ID, array('section' => 'compensation'))
            : '';
        try {
            $comp_opts = function_exists('vms_get_event_plan_comp_options')
                ? (array) vms_get_event_plan_comp_options($venue_id_effective, $event_date, $selected_band_id)
                : array('defaults' => array(), 'packages' => array(), 'max_guarantee' => 0.0);
        } finally {
            if (function_exists('vms_event_plan_perf_span_finish')) {
                vms_event_plan_perf_span_finish('event_plan_comp_options_lookup', (int) $post->ID, $comp_opts_trace, array('section' => 'compensation'));
            }
        }
        $comp_opts['_venue_selected'] = ($venue_id_effective > 0);
        $comp_opts['_date_selected'] = (!empty($event_date));

        // Staff assignments (role_term_id => [staff_post_ids...])
        $staff_assignments = get_post_meta($post->ID, '_vms_staff_assignments', true);
        if (!is_array($staff_assignments)) $staff_assignments = array();
// Secondary vendors (non-performer vendors, e.g., food trucks)
        $k_secondary_ids     = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'secondary_vendor_ids') : '_vms_secondary_vendor_ids';
        $k_secondary_type    = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'secondary_vendor_type') : '_vms_secondary_vendor_type';
        $k_secondary_unq     = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'secondary_vendor_unqualified') : '_vms_secondary_vendor_unqualified';
        $k_secondary_unq_ids = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'secondary_vendor_unqualified_ids') : '_vms_secondary_vendor_unqualified_ids';

        $secondary_trace = function_exists('vms_event_plan_perf_span_start')
            ? vms_event_plan_perf_span_start('event_plan_secondary_vendor_lookup', (int) $post->ID, array('section' => 'secondary_vendor_lookup'))
            : '';
        try {
            $secondary_vendor_type = function_exists('vms_vendor_type_normalize_slug')
                ? vms_vendor_type_normalize_slug((string) get_post_meta($post->ID, $k_secondary_type, true))
                : sanitize_title((string) get_post_meta($post->ID, $k_secondary_type, true));

            $secondary_vendor_ids = get_post_meta($post->ID, $k_secondary_ids, true);
            if (!is_array($secondary_vendor_ids)) $secondary_vendor_ids = array();
            $secondary_vendor_ids = array_values(array_unique(array_filter(array_map('absint', $secondary_vendor_ids), function ($v) {
                return $v > 0;
            })));

        if ($post->post_status === 'auto-draft') {
            if ($secondary_vendor_type === '' && isset($_GET['vms_secondary_vendor_type'])) {
                $secondary_vendor_type = function_exists('vms_vendor_type_normalize_slug')
                    ? vms_vendor_type_normalize_slug((string) wp_unslash((string) $_GET['vms_secondary_vendor_type']))
                    : sanitize_title(wp_unslash((string) $_GET['vms_secondary_vendor_type']));
            }
            if ($secondary_vendor_type === '' && isset($_GET['vms_prefill_vendor_mode'], $_GET['vms_prefill_vendor_type'])) {
                $qs_prefill_mode = sanitize_key(wp_unslash((string) $_GET['vms_prefill_vendor_mode']));
                if ($qs_prefill_mode === 'secondary') {
                    $secondary_vendor_type = function_exists('vms_vendor_type_normalize_slug')
                        ? vms_vendor_type_normalize_slug((string) wp_unslash((string) $_GET['vms_prefill_vendor_type']))
                        : sanitize_title(wp_unslash((string) $_GET['vms_prefill_vendor_type']));
                }
            }

            if (empty($secondary_vendor_ids)) {
                $qs_secondary_ids = array();
                if (isset($_GET['vms_secondary_vendor_id'])) {
                    $qs_secondary_ids[] = absint($_GET['vms_secondary_vendor_id']);
                }
                if (isset($_GET['vms_secondary_vendor_ids']) && is_array($_GET['vms_secondary_vendor_ids'])) {
                    $qs_secondary_ids = array_merge($qs_secondary_ids, array_map('absint', (array) wp_unslash($_GET['vms_secondary_vendor_ids'])));
                }
                if (empty($qs_secondary_ids) && isset($_GET['vms_prefill_vendor_mode'], $_GET['vms_prefill_vendor_id'])) {
                    $qs_prefill_mode = sanitize_key(wp_unslash((string) $_GET['vms_prefill_vendor_mode']));
                    if ($qs_prefill_mode === 'secondary') {
                        $qs_secondary_ids[] = absint($_GET['vms_prefill_vendor_id']);
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
            if (function_exists('vms_event_plan_perf_span_finish')) {
                vms_event_plan_perf_span_finish('event_plan_secondary_vendor_lookup', (int) $post->ID, $secondary_trace, array('section' => 'secondary_vendor_lookup'));
            }
        }

        $secondary_vendor_assignments = function_exists('vms_event_plan_get_secondary_vendor_assignments')
            ? (array) vms_event_plan_get_secondary_vendor_assignments((int) $post->ID, array(
                'primary_vendor_id' => $selected_band_id,
            ))
            : array();
        if ($post->post_status === 'auto-draft' && empty($secondary_vendor_assignments) && ($secondary_vendor_type !== '' || !empty($secondary_vendor_ids)) && function_exists('vms_event_plan_normalize_secondary_vendor_assignment_map')) {
            $secondary_vendor_assignments = vms_event_plan_normalize_secondary_vendor_assignment_map((int) $post->ID, array(
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
            $secondary_vendor_type = function_exists('vms_event_plan_legacy_secondary_vendor_type_from_assignments')
                ? (string) vms_event_plan_legacy_secondary_vendor_type_from_assignments($secondary_vendor_assignments)
                : $secondary_vendor_type;
            $secondary_vendor_ids = function_exists('vms_event_plan_get_secondary_vendor_flat_ids_from_assignments')
                ? vms_event_plan_get_secondary_vendor_flat_ids_from_assignments($secondary_vendor_assignments, $selected_band_id)
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
        $lineup_trace = function_exists('vms_event_plan_perf_span_start')
            ? vms_event_plan_perf_span_start('event_plan_supporting_act_lookup', (int) $post->ID, array('section' => 'supporting_act_lookup'))
            : '';
        try {
            $lineup_entries = function_exists('vms_get_event_plan_lineup_entries')
                ? (array) vms_get_event_plan_lineup_entries((int) $post->ID, $lineup_context)
                : array();
            $lineup_summary = function_exists('vms_get_event_plan_lineup_summary')
                ? (array) vms_get_event_plan_lineup_summary((int) $post->ID, $lineup_context)
                : array();
            $lineup_warnings = function_exists('vms_get_event_plan_lineup_warnings')
                ? (array) vms_get_event_plan_lineup_warnings((int) $post->ID, $lineup_context)
                : array();
        } finally {
            if (function_exists('vms_event_plan_perf_span_finish')) {
                vms_event_plan_perf_span_finish('event_plan_supporting_act_lookup', (int) $post->ID, $lineup_trace, array('section' => 'supporting_act_lookup'));
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
                'row_id' => function_exists('vms_lineup_schedule_make_row_id') ? vms_lineup_schedule_make_row_id() : 'lineup_primary',
                'vendor_id' => $selected_band_id,
                'role' => 'primary',
                'set_start' => $start_time_current,
                'set_end' => $end_time_current,
                'set_start_label' => function_exists('vms_lineup_schedule_format_time_label') ? vms_lineup_schedule_format_time_label($start_time_current) : $start_time_current,
                'set_end_label' => function_exists('vms_lineup_schedule_format_time_label') ? vms_lineup_schedule_format_time_label($end_time_current) : $end_time_current,
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

        $availability_boot_trace = function_exists('vms_event_plan_perf_span_start')
            ? vms_event_plan_perf_span_start('event_plan_admin_boot_availability_conflict', (int) $post->ID, array('section' => 'availability_conflict_summary'))
            : '';
        try {
            if (function_exists('vms_event_plan_perf_memory_checkpoint')) {
                vms_event_plan_perf_memory_checkpoint((int) $post->ID, 'vendor_conflict_before', array(
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
            if (function_exists('vms_event_plan_perf_memory_checkpoint')) {
                vms_event_plan_perf_memory_checkpoint((int) $post->ID, 'vendor_conflict_after', array(
                    'section' => 'availability_conflict_summary',
                    'primary_option_count' => count((array) ($vendor_option_context['primary_rows'] ?? array())),
                    'supporting_option_count' => count((array) ($vendor_option_context['supporting_rows'] ?? array())),
                ), 'vendor_conflict');
            }
        } finally {
            if (function_exists('vms_event_plan_perf_span_finish')) {
                vms_event_plan_perf_span_finish('event_plan_admin_boot_availability_conflict', (int) $post->ID, $availability_boot_trace, array(
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
        if (function_exists('vms_event_plan_perf_log')) {
            vms_event_plan_perf_log('event_plan_vendor_options', (int) $post->ID, array(
                'phase' => 'selected_only',
                'option_mode' => 'selected_rows_only',
                'primary_option_count' => count($lineup_primary_vendor_option_rows),
                'primary_option_payload_bytes' => strlen($lineup_primary_vendor_option_html),
                'supporting_option_count' => count($lineup_supporting_vendor_option_rows),
                'supporting_select_count' => count($lineup_supporting_entries) + 1,
                'shared_option_payload_bytes' => strlen($lineup_supporting_vendor_option_html),
            ));
        }

        $vendor_summary_trace = function_exists('vms_event_plan_perf_span_start')
            ? vms_event_plan_perf_span_start('event_plan_admin_boot_vendor_summary', (int) $post->ID, array('section' => 'vendor_summary'))
            : '';
        try {
            $vendor_summary_context = array(
                'primary_vendor_assigned' => $selected_band_id > 0 ? 1 : 0,
                'supporting_vendor_count' => count($lineup_supporting_entries),
                'primary_vendor_option_count' => count($lineup_primary_vendor_option_rows),
            );
        } finally {
            if (function_exists('vms_event_plan_perf_span_finish')) {
                vms_event_plan_perf_span_finish('event_plan_admin_boot_vendor_summary', (int) $post->ID, $vendor_summary_trace, array(
                    'section' => 'vendor_summary',
                    'primary_vendor_assigned' => $selected_band_id > 0 ? 1 : 0,
                    'supporting_vendor_count' => count($lineup_supporting_entries),
                    'primary_vendor_option_count' => count($lineup_primary_vendor_option_rows),
                ));
            }
        }

        $secondary_vendor_lazy_enabled = $this->should_defer_event_plan_admin_section((int) $post->ID, 'secondary_vendors');
        $secondary_state_trace = function_exists('vms_event_plan_perf_span_start')
            ? vms_event_plan_perf_span_start('event_plan_admin_boot_secondary_vendor_state', (int) $post->ID, array('section' => 'secondary_vendor_state'))
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
            if (function_exists('vms_event_plan_perf_span_finish')) {
                vms_event_plan_perf_span_finish('event_plan_admin_boot_secondary_vendor_state', (int) $post->ID, $secondary_state_trace, array(
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

        $staffing_summary_trace = function_exists('vms_event_plan_perf_span_start')
            ? vms_event_plan_perf_span_start('event_plan_admin_boot_staffing_summary', (int) $post->ID, array('section' => 'staffing_summary'))
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
            if (function_exists('vms_event_plan_perf_span_finish')) {
                vms_event_plan_perf_span_finish('event_plan_admin_boot_staffing_summary', (int) $post->ID, $staffing_summary_trace, array(
                    'section' => 'staffing_summary',
                    'staff_role_count' => count($staff_assignments),
                    'staff_assignment_count' => $staff_assignment_total,
                    'staffing_render_mode' => $this->should_defer_event_plan_admin_section((int) $post->ID, 'staff') ? 'deferred' : 'full',
                ));
            }
        }

        $integrity_trace = function_exists('vms_event_plan_perf_span_start')
            ? vms_event_plan_perf_span_start('event_plan_admin_boot_integrity_flags', (int) $post->ID, array('section' => 'integrity_flags'))
            : '';
        $integrity_boot_summary = array();
        try {
            $integrity_boot_summary = $this->get_event_plan_integrity_boot_summary((int) $post->ID);
            $issue = sanitize_key((string) ($integrity_boot_summary['integrity_issue'] ?? ''));
            if ($issue === 'none') {
                $issue = '';
            }
        } finally {
            if (function_exists('vms_event_plan_perf_span_finish')) {
                vms_event_plan_perf_span_finish('event_plan_admin_boot_integrity_flags', (int) $post->ID, $integrity_trace, array(
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
                $k_vt = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'integrity_vendor_title') : '_vms_integrity_vendor_title';
                $vendor_title = (string) get_post_meta($post->ID, $k_vt, true);
                if ($vendor_title === '') $vendor_title = __('(unknown vendor)', 'backstage-venue-manager');

                echo '<div class="notice notice-error inline vms-notice vms-notice--critical"><p>' .
                    esc_html__('🚩 This event plan lost its vendor (the vendor was deleted) and needs attention.', 'backstage-venue-manager') .
                    /* translators: %s: previous vendor. */
                    ' ' . sprintf(esc_html__('Previous vendor: %s', 'backstage-venue-manager'), esc_html($vendor_title)) .
                    ' ' . esc_html__('Select a new Primary Vendor, then mark Ready again.', 'backstage-venue-manager') .
                    '</p></div>';
            } elseif ($issue === 'missing_secondary_vendor') {
                $k_vt = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'integrity_vendor_title') : '_vms_integrity_vendor_title';
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
            if ($post->post_status === 'auto-draft' && isset($_GET['vms_prefill_vendor_id'], $_GET['vms_prefill_vendor_mode'])) {
                $prefill_vendor_id = absint($_GET['vms_prefill_vendor_id']);
                $prefill_mode = sanitize_key(wp_unslash((string) $_GET['vms_prefill_vendor_mode']));
                $prefill_vendor_label = isset($_GET['vms_prefill_vendor_label']) ? sanitize_text_field(wp_unslash((string) $_GET['vms_prefill_vendor_label'])) : '';
                if ($prefill_vendor_id > 0) {
                    $resolved_vendor_label = $prefill_vendor_label !== '' ? $prefill_vendor_label : (string) get_the_title($prefill_vendor_id);
                    if ($resolved_vendor_label === '') {
                        $resolved_vendor_label = __('Selected vendor', 'backstage-venue-manager');
                    }

                    if ($prefill_mode === 'secondary') {
                        $prefill_type_label = '';
                        if (isset($_GET['vms_prefill_vendor_type'])) {
                            $prefill_type_slug = function_exists('vms_vendor_type_normalize_slug')
                                ? vms_vendor_type_normalize_slug((string) wp_unslash((string) $_GET['vms_prefill_vendor_type']))
                                : sanitize_title(wp_unslash((string) $_GET['vms_prefill_vendor_type']));
                            if ($prefill_type_slug !== '') {
                                $prefill_type_term = function_exists('vms_vendor_type_get_term')
                                    ? vms_vendor_type_get_term($prefill_type_slug)
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
            <input type="date" id="vms_event_date" name="vms_event_date" value="<?php echo esc_attr($event_date); ?>" />
        </p>

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
        if ($venue_id_effective > 0 && $event_date && function_exists('vms_get_venue_holiday_for_date')) {
            $holiday = vms_get_venue_holiday_for_date($venue_id_effective, $event_date);
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
        $resolved_default = function_exists('vms_get_event_plan_effective_comp_default')
            ? (array) vms_get_event_plan_effective_comp_default((int) $venue_id_effective, (string) $event_date)
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

    <?php $vms_compensation_html = $this->capture_event_plan_partial('compensation', get_defined_vars()); ?>
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
            if (function_exists('vms_event_plan_perf_log')) {
                vms_event_plan_perf_log('event_plan_vendor_conflict_details', (int) $post->ID, array(
                    'phase' => 'lazy_available',
                    'lazy_load' => 1,
                    'section' => 'secondary_vendors',
                ));
                vms_event_plan_perf_log('event_plan_secondary_vendor_render', (int) $post->ID, array(
                    'phase' => 'summary_only',
                    'lazy_load' => 1,
                    'section' => 'secondary_vendors',
                    'skip_reason' => 'collapsed_initial_load',
                    'secondary_vendor_warning_count' => $vms_secondary_warning_count,
                ));
            }
            if (function_exists('vms_event_plan_perf_memory_checkpoint')) {
                vms_event_plan_perf_memory_checkpoint((int) $post->ID, 'secondary_vendors_shell_before', array(
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
            data-vms-lazy-nonce="<?php echo esc_attr(wp_create_nonce('vms_event_plan_admin_section')); ?>"
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
            if (function_exists('vms_event_plan_perf_memory_checkpoint')) {
                vms_event_plan_perf_memory_checkpoint((int) $post->ID, 'secondary_vendors_shell_after', array(
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
        $vms_staff_nonce = wp_create_nonce('vms_event_plan_admin_section');
        $vms_staff_has_data_hint = !empty($staff_assignments);
    ?>
    <?php if ($vms_staff_lazy_enabled) : ?>
        <?php
            if (function_exists('vms_event_plan_perf_log')) {
                vms_event_plan_perf_log('event_plan_staff_section_render', (int) $post->ID, array(
                    'phase' => 'summary_only',
                    'lazy_load' => 1,
                    'section' => 'staff',
                    'skip_reason' => 'collapsed_initial_load',
                ));
            }
            if (function_exists('vms_event_plan_perf_memory_checkpoint')) {
                vms_event_plan_perf_memory_checkpoint((int) $post->ID, 'staff_shell_before', array(
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
            if (function_exists('vms_event_plan_perf_memory_checkpoint')) {
                vms_event_plan_perf_memory_checkpoint((int) $post->ID, 'staff_shell_after', array(
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
        $readiness_trace = function_exists('vms_event_plan_perf_span_start')
            ? vms_event_plan_perf_span_start('event_plan_admin_boot_readiness', (int) $post->ID, array('section' => 'readiness_status_checks'))
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
            if (function_exists('vms_event_plan_perf_span_finish')) {
                vms_event_plan_perf_span_finish('event_plan_admin_boot_readiness', (int) $post->ID, $readiness_trace, array(
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
    <?php if (function_exists('vms_event_plan_perf_memory_checkpoint')) {
        vms_event_plan_perf_memory_checkpoint((int) $post->ID, 'readiness_summary_before', array(
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
    <?php if (function_exists('vms_event_plan_perf_memory_checkpoint')) {
        vms_event_plan_perf_memory_checkpoint((int) $post->ID, 'readiness_summary_after', array(
            'section' => 'readiness_summary',
            'warning_item_count' => count($vms_readiness_warning_items),
            'payload_size_bytes' => absint($vms_readiness_summary_context['payload_size_bytes'] ?? 0),
        ), 'readiness_summary');
    } ?>
    <?php if ($vms_readiness_lazy_enabled) : ?>
        <?php
            if (function_exists('vms_event_plan_perf_log')) {
                vms_event_plan_perf_log('event_plan_readiness_details', (int) $post->ID, array(
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
            data-vms-lazy-nonce="<?php echo esc_attr(wp_create_nonce('vms_event_plan_admin_section')); ?>"
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

	    <script>
	        (function() {
	            document.documentElement.classList.add('vms-js');

	            const form = document.getElementById('post');
	            if (!form) return;

	            const venueSel = document.getElementById('vms_venue_id');
	            const dateInp = document.getElementById('vms_event_date');
	            const bandSel = document.getElementById('vms_band_vendor_id');

	            const fStruct = document.getElementById('vms_comp_structure');
	            const fFlat = document.getElementById('vms_flat_fee_amount');
	            const fSplit = document.getElementById('vms_door_split_percent');
	            const fBonusMode = document.getElementById('vms_attendance_bonus_mode');
	            const fBonusStart = document.getElementById('vms_attendance_bonus_start_count');
	            const fBonusStepSize = document.getElementById('vms_attendance_bonus_step_size');
	            const fBonusStepBonus = document.getElementById('vms_attendance_bonus_step_bonus');
	            const fBonusPerTicket = document.getElementById('vms_attendance_bonus_per_ticket_rate');
	            const fBonusMax = document.getElementById('vms_attendance_bonus_max_bonus');
	            const fCommissionPercent = document.getElementById('vms_commission_percent');
	            const fCommissionMode = document.getElementById('vms_commission_mode');

	            const flatLabelText = document.getElementById('vms_flat_fee_amount_label_text');
	            const flatHelp = document.getElementById('vms_flat_fee_amount_help');
	            const previewWrap = document.getElementById('vms-attendance-bonus-preview');
	            const previewFormula = document.getElementById('vms-attendance-bonus-formula');
	            const previewTable = document.getElementById('vms-attendance-bonus-preview-table');
	            const agentFeeSummary = document.getElementById('vms-agent-fee-summary');

	            const tilesWrap = document.getElementById('vms-comp-tiles');
	            const tiles = tilesWrap ? Array.from(tilesWrap.querySelectorAll('[data-structure]')) : [];

	            const ackCard = document.getElementById('vms-comp-ack-wrap');
	            let overrideDiff = false;
	            let lowDiff = false;
	            const lowSummary = document.getElementById('vms-low-guarantee-summary');

	            const defStruct = document.getElementById('vms_default_structure');
	            const defFlat = document.getElementById('vms_default_flat_fee_amount');
	            const defSplit = document.getElementById('vms_default_door_split_percent');
	            const defBonusMode = document.getElementById('vms_default_attendance_bonus_mode');
	            const defBonusStart = document.getElementById('vms_default_attendance_bonus_start_count');
	            const defBonusStepSize = document.getElementById('vms_default_attendance_bonus_step_size');
	            const defBonusStepBonus = document.getElementById('vms_default_attendance_bonus_step_bonus');
	            const defBonusPerTicket = document.getElementById('vms_default_attendance_bonus_per_ticket_rate');
	            const defBonusMax = document.getElementById('vms_default_attendance_bonus_max_bonus');
	            const defCommissionPercent = document.getElementById('vms_default_commission_percent');
	            const defCommissionMode = document.getElementById('vms_default_commission_mode');
	            const defLabel = document.getElementById('vms_default_label');
	            const ack = document.getElementById('vms_pay_override_ack');
	            const lowAck = ack;
	            const lowBox = document.getElementById('vms-low-guarantee-box');
	            const summary = document.getElementById('vms-pay-override-summary');

	            if (!fStruct || !fFlat || !fSplit) return;

	            function num(v) {
	                let s = String(v ?? '').trim();
	                if (!s) return null;
	                s = s.replace(/[^0-9.\-]/g, '');
	                if (!s || s === '-' || s === '.' || s === '-.') return null;
	                const x = parseFloat(s);
	                return Number.isFinite(x) ? x : null;
	            }

	            function nonNegativeMoney(v) {
	                const parsed = num(v);
	                if (parsed === null) return null;
	                return Math.max(0, parsed);
	            }

	            function nonNegativeInt(v) {
	                const parsed = num(v);
	                if (parsed === null) return null;
	                return Math.max(0, Math.floor(parsed));
	            }

	            function str(v) {
	                return String(v ?? '').trim();
	            }

	            function formatMoney(v) {
	                if (v === null || v === undefined || !Number.isFinite(Number(v))) return '—';
	                return '$' + Number(v).toFixed(2);
	            }

	            function formatPct(v) {
	                if (v === null || v === undefined || !Number.isFinite(Number(v))) return '—';
	                return Number(v).toFixed(2) + '%';
	            }

	            function structureLabel(structure) {
	                if (structure === 'door_split') return 'Door Split';
	                if (structure === 'flat_fee_door_split') return 'Flat Fee + Door Split';
	                if (structure === 'attendance_bonus') return 'Base + Attendance Bonus';
	                return 'Flat Fee';
	            }

	            function bonusModeLabel(mode) {
	                if (mode === 'continuous') return 'Continuous';
	                if (mode === 'step') return 'Step';
	                return '—';
	            }

	            function selectedStructure() {
	                return str(fStruct.value || 'flat_fee');
	            }

	            function selectedBonusMode() {
	                return str(fBonusMode ? fBonusMode.value : '');
	            }

	            function guaranteeMap(flatFee) {
	                const ff = Math.max(0, Number(flatFee || 0));
	                return {
	                    flat_fee: ff,
	                    door_split: 0,
	                    flat_fee_door_split: ff,
	                    attendance_bonus: ff,
	                };
	            }

	            const actionButtons = Array.from(form.querySelectorAll('button[type="submit"][name="vms_event_plan_action"]'));
	            actionButtons.forEach((btn) => {
	                btn.dataset.vmsBaseDisabled = btn.disabled ? '1' : '0';
	            });

	            function setButtonsDisabled(disabled) {
	                actionButtons.forEach(btn => {
	                    const v = btn.value || '';
	                    if (v === 'mark_ready' || v === 'publish_now' || v === 'lock_draft_pay') {
	                        const baseDisabled = btn.dataset.vmsBaseDisabled === '1';
	                        const nextDisabled = baseDisabled || !!disabled;
	                        btn.disabled = nextDisabled;
	                        btn.classList.toggle('disabled', nextDisabled);
	                    }
	                });
	            }

	            function updateTileSelection() {
	                if (!tiles.length) return;
	                const cur = selectedStructure();
	                tiles.forEach(t => {
	                    const isSel = (t.getAttribute('data-structure') === cur);
	                    t.classList.toggle('is-selected', isSel);
	                    t.setAttribute('aria-checked', isSel ? 'true' : 'false');
	                });
	            }

	            function applyStructureScale(map, maxAvailable) {
	                if (!tiles.length || !map) return;
	                const scaleClasses = [
	                    'vms-comp-tile--scale-1',
	                    'vms-comp-tile--scale-2',
	                    'vms-comp-tile--scale-3',
	                    'vms-comp-tile--scale-4',
	                    'vms-comp-tile--scale-5',
	                ];

	                const values = {};
	                Object.keys(map).forEach((k) => {
	                    const raw = Number(map[k] || 0);
	                    values[k] = Number.isFinite(raw) ? Math.max(0, raw) : 0;
	                });

	                const structValues = Object.values(values);
	                const maxStruct = structValues.length ? Math.max.apply(null, structValues) : 0;
	                const parsedMaxAvailable = Number(maxAvailable || 0);
	                const maxAvailableSafe = Number.isFinite(parsedMaxAvailable) ? Math.max(0, parsedMaxAvailable) : 0;
	                const referenceMax = Math.max(maxStruct, maxAvailableSafe);

	                tiles.forEach((t) => {
	                    scaleClasses.forEach((cls) => t.classList.remove(cls));
	                    if (!(referenceMax > 0)) return;

	                    const key = String(t.getAttribute('data-structure') || '').trim();
	                    if (!key) return;
	                    const needle = Number(values[key] || 0);
	                    const ratio = Math.max(0, Math.min(1, needle / referenceMax));
	                    const bucket = Math.max(0, Math.min(4, Math.floor(ratio * 4)));
	                    t.classList.add('vms-comp-tile--scale-' + String(bucket + 1));
	                });
	            }

	            function attendanceState() {
	                return {
	                    mode: selectedBonusMode(),
	                    start: nonNegativeInt(fBonusStart ? fBonusStart.value : ''),
	                    stepSize: nonNegativeInt(fBonusStepSize ? fBonusStepSize.value : ''),
	                    stepBonus: nonNegativeMoney(fBonusStepBonus ? fBonusStepBonus.value : ''),
	                    perTicketRate: nonNegativeMoney(fBonusPerTicket ? fBonusPerTicket.value : ''),
	                    maxBonus: nonNegativeMoney(fBonusMax ? fBonusMax.value : ''),
	                };
	            }

	            function setFieldVisibility() {
	                const cur = selectedStructure();
	                const mode = selectedBonusMode();
	                document.querySelectorAll('[data-show-when]').forEach(el => {
	                    const allowedStructures = String(el.getAttribute('data-show-when') || '').split(',').map(s => s.trim()).filter(Boolean);
	                    const allowedModes = String(el.getAttribute('data-show-when-mode') || '').split(',').map(s => s.trim()).filter(Boolean);
	                    const structureMatch = allowedStructures.includes(cur);
	                    const modeMatch = !allowedModes.length || allowedModes.includes(mode);
	                    el.classList.toggle('vms-hidden', !(structureMatch && modeMatch));
	                });

	                if (flatLabelText) {
	                    flatLabelText.textContent = (cur === 'attendance_bonus') ? 'Base Pay' : 'Flat Fee Amount';
	                }
	                if (flatHelp) {
	                    flatHelp.classList.toggle('vms-hidden', cur !== 'attendance_bonus');
	                }
	            }

	            function attendanceCapInfo(state) {
	                if (state.maxBonus === null || state.start === null) return null;

	                if (state.mode === 'step' && state.stepSize !== null && state.stepSize >= 1 && state.stepBonus !== null && state.stepBonus > 0) {
	                    const stepsToCap = Math.max(0, Math.ceil(state.maxBonus / state.stepBonus));
	                    return {
	                        count: state.start + (stepsToCap * state.stepSize),
	                        steps: stepsToCap,
	                    };
	                }

	                if (state.mode === 'continuous' && state.perTicketRate !== null && state.perTicketRate > 0) {
	                    const ticketsToCap = Math.max(0, Math.ceil(state.maxBonus / state.perTicketRate));
	                    return {
	                        count: state.start + ticketsToCap,
	                        tickets: ticketsToCap,
	                    };
	                }

	                return null;
	            }

	            function buildAttendancePreviewCounts(state) {
	                const counts = [];
	                const pushCount = (value) => {
	                    const safe = Math.max(0, Math.floor(Number(value || 0)));
	                    if (!counts.includes(safe)) counts.push(safe);
	                };
	                const start = state.start ?? 0;
	                const capInfo = attendanceCapInfo(state);

	                if (state.mode === 'step') {
	                    const stepSize = state.stepSize ?? 0;
	                    pushCount(start);

	                    if (capInfo && Number.isFinite(Number(capInfo.steps))) {
	                        const exactSteps = Math.max(0, Number(capInfo.steps || 0));
	                        if (exactSteps <= 40) {
	                            for (let stepIndex = 1; stepIndex <= exactSteps; stepIndex += 1) {
	                                pushCount(start + (stepIndex * stepSize));
	                            }
	                        } else {
	                            for (let stepIndex = 1; stepIndex <= 10; stepIndex += 1) {
	                                pushCount(start + (stepIndex * stepSize));
	                            }
	                            pushCount(start + (Math.floor(exactSteps / 2) * stepSize));
	                            pushCount(start + (Math.max(1, exactSteps - 2) * stepSize));
	                            pushCount(start + (Math.max(1, exactSteps - 1) * stepSize));
	                            pushCount(capInfo.count);
	                        }
	                    } else {
	                        for (let stepIndex = 1; stepIndex <= 5; stepIndex += 1) {
	                            pushCount(start + (stepIndex * stepSize));
	                        }
	                    }
	                } else {
	                    pushCount(start);
	                    if (capInfo && Number.isFinite(Number(capInfo.tickets))) {
	                        const exactTickets = Math.max(0, Number(capInfo.tickets || 0));
	                        if (exactTickets <= 12) {
	                            for (let ticketIndex = 1; ticketIndex <= exactTickets; ticketIndex += 1) {
	                                pushCount(start + ticketIndex);
	                            }
	                        } else {
	                            pushCount(start + 1);
	                            pushCount(start + Math.ceil(exactTickets * 0.1));
	                            pushCount(start + Math.ceil(exactTickets * 0.25));
	                            pushCount(start + Math.ceil(exactTickets * 0.5));
	                            pushCount(start + Math.ceil(exactTickets * 0.75));
	                            pushCount(capInfo.count);
	                        }
	                    } else {
	                        pushCount(start + 1);
	                        pushCount(start + 5);
	                        pushCount(start + 10);
	                        pushCount(start + 25);
	                        pushCount(start + 50);
	                    }
	                }

	                counts.sort((a, b) => a - b);
	                return counts;
	            }

	            function calculateAttendancePreviewPayout(base, state, attendanceCount) {
	                const safeAttendance = Math.max(0, Math.floor(Number(attendanceCount || 0)));
	                const safeBase = Math.max(0, Number(base || 0));
	                let bonus = 0;
	                if (state.mode === 'step' && state.start !== null && state.stepSize !== null && state.stepSize >= 1 && state.stepBonus !== null) {
	                    const stepsReached = Math.floor(Math.max(0, safeAttendance - state.start) / state.stepSize);
	                    bonus = stepsReached * state.stepBonus;
	                } else if (state.mode === 'continuous' && state.start !== null && state.perTicketRate !== null) {
	                    bonus = Math.max(0, safeAttendance - state.start) * state.perTicketRate;
	                }

	                bonus = Math.max(0, Number(bonus || 0));
	                if (state.maxBonus !== null) {
	                    bonus = Math.min(state.maxBonus, bonus);
	                }

	                return {
	                    base: safeBase,
	                    bonus: bonus,
	                    payout: safeBase + bonus,
	                };
	            }

	            function renderAttendancePreview() {
	                if (!previewWrap || !previewFormula || !previewTable) return false;

	                const cur = selectedStructure();
	                const base = nonNegativeMoney(fFlat.value);
	                const state = attendanceState();
	                const isAttendance = (cur === 'attendance_bonus');

	                previewWrap.classList.toggle('vms-hidden', !isAttendance);
	                if (!isAttendance) {
	                    return false;
	                }

	                const mode = state.mode;
	                const isStepValid = (base !== null && mode === 'step' && state.start !== null && state.stepSize !== null && state.stepSize >= 1 && state.stepBonus !== null);
	                const isContinuousValid = (base !== null && mode === 'continuous' && state.start !== null && state.perTicketRate !== null);

	                if (!isStepValid && !isContinuousValid) {
	                    let msg = 'Complete Base Pay, Bonus Style, and the attendance bonus fields to preview payouts.';
	                    if (mode === 'step' && state.stepSize !== null && state.stepSize < 1) {
	                        msg = 'Step Size must be at least 1 for step-mode attendance bonuses.';
	                    }
	                    previewFormula.textContent = msg;
	                    previewTable.innerHTML = '';
	                    return true;
	                }

	                const capInfo = attendanceCapInfo(state);
	                const counts = buildAttendancePreviewCounts(state);
	                if (mode === 'step') {
	                    const parts = [
	                        `Base pay ${formatMoney(base)}.`,
	                        `No bonus is earned through ${state.start} attendance.`,
	                        `Add ${formatMoney(state.stepBonus)} every ${state.stepSize} tickets after that.`,
	                    ];
	                    if (state.maxBonus !== null) {
	                        let capSentence = `Total bonus caps at ${formatMoney(state.maxBonus)}.`;
	                        if (capInfo && capInfo.count !== null) {
	                            capSentence = `Total bonus caps at ${formatMoney(state.maxBonus)} once attendance reaches ${capInfo.count}.`;
	                        }
	                        parts.push(capSentence);
	                    }
	                    previewFormula.textContent = parts.join(' ');
	                } else {
	                    const parts = [
	                        `Base pay ${formatMoney(base)}.`,
	                        `No bonus is earned through ${state.start} attendance.`,
	                        `Add ${formatMoney(state.perTicketRate)} per ticket after that.`,
	                    ];
	                    if (state.maxBonus !== null) {
	                        let capSentence = `Total bonus caps at ${formatMoney(state.maxBonus)}.`;
	                        if (capInfo && capInfo.count !== null) {
	                            capSentence = `Total bonus caps at ${formatMoney(state.maxBonus)} once attendance reaches ${capInfo.count}.`;
	                        }
	                        parts.push(capSentence);
	                    }
	                    previewFormula.textContent = parts.join(' ');
	                }

	                const rows = counts.map((count) => {
	                    const payout = calculateAttendancePreviewPayout(base, state, count);
	                    return `<tr><td>${count}</td><td>${formatMoney(payout.payout)}</td></tr>`;
	                }).join('');

	                previewTable.innerHTML = `<table class="widefat striped"><thead><tr><th>Attendance</th><th>Payout</th></tr></thead><tbody>${rows}</tbody></table>`;
	                return false;
	            }

	            function renderLowGuarantee() {
	                if (!lowBox || !lowAck || !lowSummary) return false;

	                const cur = selectedStructure();
	                const flat = nonNegativeMoney(fFlat.value);
	                const map = guaranteeMap(flat);
	                const maxAvailInp = document.getElementById('vms_max_guarantee_available');
	                const maxAvail = nonNegativeMoney(maxAvailInp ? maxAvailInp.value : 0);
	                applyStructureScale(map, maxAvail);

	                const selG = (cur === 'door_split') ? 0 : Math.max(0, Number(flat || 0));
	                const requires = (Number(maxAvail || 0) > 0 && selG < Number(maxAvail || 0));
	                lowDiff = requires;

	                document.querySelectorAll('[data-guarantee-for]').forEach(el => {
	                    const k = el.getAttribute('data-guarantee-for');
	                    const g = map[k] ?? 0;
	                    el.textContent = '$' + Number(g).toFixed(2);
	                });

	                lowBox.classList.toggle('vms-hidden', !requires);
	                if (!requires) {
	                    return false;
	                }

	                lowSummary.textContent = 'Selected guaranteed: $' + Number(selG).toFixed(2) + '. Highest available guaranteed: $' + Number(maxAvail || 0).toFixed(2) + '.';
	                return !lowAck.checked;
	            }

	            function renderAgentFeeSummary() {
	                if (!agentFeeSummary || !fCommissionPercent || !fCommissionMode) return;

	                const pct = nonNegativeMoney(fCommissionPercent.value);
	                const mode = str(fCommissionMode.value || 'artist_fee');
	                const flat = nonNegativeMoney(fFlat.value);
	                const cur = selectedStructure();
	                const baseLabel = (cur === 'attendance_bonus') ? 'Base pay' : 'Flat fee';

	                if (pct === null || pct <= 0) {
	                    agentFeeSummary.textContent = 'No agent fee is currently set for this event.';
	                    return;
	                }

	                if (mode === 'gross') {
	                    agentFeeSummary.textContent = `Agent fee is set to ${formatPct(pct)} and will be based on gross / settlement, so it is not included in the guaranteed expense total yet.`;
	                    return;
	                }

	                if (flat === null) {
	                    agentFeeSummary.textContent = `Agent fee is set to ${formatPct(pct)} and will be added on top once ${baseLabel.toLowerCase()} is entered.`;
	                    return;
	                }

	                const feeAmount = Math.max(0, flat * (pct / 100));
	                const total = flat + feeAmount;
	                agentFeeSummary.textContent = `Agent fee: ${formatPct(pct)} of ${baseLabel.toLowerCase()} = ${formatMoney(feeAmount)}. Guaranteed expense total: ${formatMoney(total)}.`;
	            }

	            function actualState() {
	                const attendance = attendanceState();
	                return {
	                    structure: selectedStructure(),
	                    flat: nonNegativeMoney(fFlat.value),
	                    split: nonNegativeMoney(fSplit.value),
	                    attendance_bonus_mode: attendance.mode,
	                    attendance_bonus_start_count: attendance.start,
	                    attendance_bonus_step_size: attendance.stepSize,
	                    attendance_bonus_step_bonus: attendance.stepBonus,
	                    attendance_bonus_per_ticket_rate: attendance.perTicketRate,
	                    attendance_bonus_max_bonus: attendance.maxBonus,
	                    commission_percent: nonNegativeMoney(fCommissionPercent ? fCommissionPercent.value : ''),
	                    commission_mode: str(fCommissionMode ? fCommissionMode.value : ''),
	                };
	            }

	            function defaultState() {
	                return {
	                    structure: str(defStruct ? defStruct.value : ''),
	                    flat: nonNegativeMoney(defFlat ? defFlat.value : ''),
	                    split: nonNegativeMoney(defSplit ? defSplit.value : ''),
	                    attendance_bonus_mode: str(defBonusMode ? defBonusMode.value : ''),
	                    attendance_bonus_start_count: nonNegativeInt(defBonusStart ? defBonusStart.value : ''),
	                    attendance_bonus_step_size: nonNegativeInt(defBonusStepSize ? defBonusStepSize.value : ''),
	                    attendance_bonus_step_bonus: nonNegativeMoney(defBonusStepBonus ? defBonusStepBonus.value : ''),
	                    attendance_bonus_per_ticket_rate: nonNegativeMoney(defBonusPerTicket ? defBonusPerTicket.value : ''),
	                    attendance_bonus_max_bonus: nonNegativeMoney(defBonusMax ? defBonusMax.value : ''),
	                    commission_percent: nonNegativeMoney(defCommissionPercent ? defCommissionPercent.value : ''),
	                    commission_mode: str(defCommissionMode ? defCommissionMode.value : ''),
	                    label: str(defLabel ? defLabel.value : 'Defaults'),
	                };
	            }

	            function differs(a, d) {
	                let diff = false;
	                if (d.structure && a.structure && d.structure !== a.structure) diff = true;
	                if (d.flat !== null && d.flat !== a.flat) diff = true;
	                if (d.split !== null && d.split !== a.split) diff = true;

	                const compareAttendance = (d.structure === 'attendance_bonus' || a.structure === 'attendance_bonus');
	                if (!compareAttendance) {
	                    return diff;
	                }

	                if (d.attendance_bonus_mode && d.attendance_bonus_mode !== a.attendance_bonus_mode) diff = true;
	                if (d.attendance_bonus_start_count !== null && d.attendance_bonus_start_count !== a.attendance_bonus_start_count) diff = true;
	                if (d.attendance_bonus_step_size !== null && d.attendance_bonus_step_size !== a.attendance_bonus_step_size) diff = true;
	                if (d.attendance_bonus_step_bonus !== null && d.attendance_bonus_step_bonus !== a.attendance_bonus_step_bonus) diff = true;
	                if (d.attendance_bonus_per_ticket_rate !== null && d.attendance_bonus_per_ticket_rate !== a.attendance_bonus_per_ticket_rate) diff = true;
	                if (d.attendance_bonus_max_bonus !== null && d.attendance_bonus_max_bonus !== a.attendance_bonus_max_bonus) diff = true;
	                if (d.commission_percent !== null && d.commission_percent !== a.commission_percent) diff = true;
	                if (d.commission_mode && d.commission_mode !== a.commission_mode) diff = true;
	                return diff;
	            }

	            function renderPayOverride() {
	                if (!ack || !summary) return false;

	                const section = document.getElementById('vms-pay-override-box');
	                const a = actualState();
	                const d = defaultState();
	                const hasAnyDefault = !!(
	                    d.structure ||
	                    d.flat !== null ||
	                    d.split !== null ||
	                    d.attendance_bonus_mode ||
	                    d.attendance_bonus_start_count !== null ||
	                    d.attendance_bonus_step_size !== null ||
	                    d.attendance_bonus_step_bonus !== null ||
	                    d.attendance_bonus_per_ticket_rate !== null ||
	                    d.attendance_bonus_max_bonus !== null ||
	                    d.commission_percent !== null ||
	                    d.commission_mode
	                );

	                if (!hasAnyDefault) {
	                    if (section) section.classList.add('vms-hidden');
	                    overrideDiff = false;
	                    return false;
	                }

	                const isDiff = differs(a, d);
	                overrideDiff = isDiff;
	                if (section) section.classList.toggle('vms-hidden', !isDiff);
	                if (!isDiff) {
	                    return false;
	                }

	                const lines = [`Draft Pay differs from ${d.label}.`];
	                if (d.structure && a.structure && d.structure !== a.structure) {
	                    lines.push(`Structure: default ${structureLabel(d.structure)} vs draft ${structureLabel(a.structure)}.`);
	                }
	                if (d.flat !== null && d.flat !== a.flat) {
	                    const flatLabel = (a.structure === 'attendance_bonus' || d.structure === 'attendance_bonus') ? 'Base pay' : 'Flat fee';
	                    lines.push(`${flatLabel}: default ${formatMoney(d.flat)} vs draft ${formatMoney(a.flat)}.`);
	                }
	                if (d.split !== null && d.split !== a.split) {
	                    lines.push(`Door split: default ${formatPct(d.split)} vs draft ${formatPct(a.split)}.`);
	                }
	                if ((d.structure === 'attendance_bonus' || a.structure === 'attendance_bonus')) {
	                    if (d.attendance_bonus_mode && d.attendance_bonus_mode !== a.attendance_bonus_mode) {
	                        lines.push(`Bonus style: default ${bonusModeLabel(d.attendance_bonus_mode)} vs draft ${bonusModeLabel(a.attendance_bonus_mode)}.`);
	                    }
	                    if (d.attendance_bonus_start_count !== null && d.attendance_bonus_start_count !== a.attendance_bonus_start_count) {
	                        lines.push(`Bonus starts after: default ${d.attendance_bonus_start_count} vs draft ${a.attendance_bonus_start_count}.`);
	                    }
	                    if (d.attendance_bonus_step_size !== null && d.attendance_bonus_step_size !== a.attendance_bonus_step_size) {
	                        lines.push(`Step size: default ${d.attendance_bonus_step_size} vs draft ${a.attendance_bonus_step_size}.`);
	                    }
	                    if (d.attendance_bonus_step_bonus !== null && d.attendance_bonus_step_bonus !== a.attendance_bonus_step_bonus) {
	                        lines.push(`Bonus per step: default ${formatMoney(d.attendance_bonus_step_bonus)} vs draft ${formatMoney(a.attendance_bonus_step_bonus)}.`);
	                    }
	                    if (d.attendance_bonus_per_ticket_rate !== null && d.attendance_bonus_per_ticket_rate !== a.attendance_bonus_per_ticket_rate) {
	                        lines.push(`Bonus per ticket: default ${formatMoney(d.attendance_bonus_per_ticket_rate)} vs draft ${formatMoney(a.attendance_bonus_per_ticket_rate)}.`);
	                    }
	                    if (d.attendance_bonus_max_bonus !== null && d.attendance_bonus_max_bonus !== a.attendance_bonus_max_bonus) {
	                        lines.push(`Max bonus: default ${formatMoney(d.attendance_bonus_max_bonus)} vs draft ${formatMoney(a.attendance_bonus_max_bonus)}.`);
	                    }
	                }
	                if (d.commission_percent !== null && d.commission_percent !== a.commission_percent) {
	                    lines.push(`Agent fee: default ${formatPct(d.commission_percent)} vs draft ${formatPct(a.commission_percent)}.`);
	                }
	                if (d.commission_mode && d.commission_mode !== a.commission_mode) {
	                    const modeLabel = (value) => value === 'gross' ? 'gross / settlement' : 'added on top';
	                    lines.push(`Agent fee basis: default ${modeLabel(d.commission_mode)} vs draft ${modeLabel(a.commission_mode)}.`);
	                }
	                summary.textContent = lines.join(' ');
	                return !ack.checked;
	            }

	            function render() {
	                updateTileSelection();
	                setFieldVisibility();

	                const attendanceInvalid = renderAttendancePreview();
	                renderAgentFeeSummary();
	                const needsOverrideAck = renderPayOverride();
	                const needsLowAck = renderLowGuarantee();

	                if (ackCard) {
	                    ackCard.classList.toggle('vms-hidden', !(overrideDiff || lowDiff));
	                }

	                setButtonsDisabled(needsOverrideAck || needsLowAck || attendanceInvalid);
	            }

	            function payStateSignature() {
	                const attendance = attendanceState();
	                return JSON.stringify([
	                    selectedStructure(),
	                    nonNegativeMoney(fFlat.value),
	                    nonNegativeMoney(fSplit.value),
	                    attendance.mode,
	                    attendance.start,
	                    attendance.stepSize,
	                    attendance.stepBonus,
	                    attendance.perTicketRate,
	                    attendance.maxBonus,
	                ]);
	            }

	            let lastPaySig = payStateSignature();

	            function resetAllAcksAndRender() {
	                const nextSig = payStateSignature();
	                if (nextSig === lastPaySig) {
	                    render();
	                    return;
	                }
	                lastPaySig = nextSig;
	                if (ack) ack.checked = false;
	                if (lowAck) lowAck.checked = false;
	                render();
	            }

	            if (tiles.length) {
	                tiles.forEach(tile => {
	                    tile.addEventListener('click', () => {
	                        const k = tile.getAttribute('data-structure');
	                        if (!k) return;
	                        fStruct.value = k;
	                        fStruct.dispatchEvent(new Event('change', { bubbles: true }));
	                    });
	                });
	            }

	            [fStruct, fFlat, fSplit, fBonusMode, fBonusStart, fBonusStepSize, fBonusStepBonus, fBonusPerTicket, fBonusMax].forEach(el => {
	                if (!el) return;
	                el.addEventListener('change', resetAllAcksAndRender);
	                el.addEventListener('input', resetAllAcksAndRender);
	            });

	            function resetOverrideAckOnly() {
	                if (ack) ack.checked = false;
	                if (lowAck) lowAck.checked = false;
	                lastPaySig = payStateSignature();
	                render();
	            }
	            if (venueSel) venueSel.addEventListener('change', resetOverrideAckOnly);
	            if (dateInp) dateInp.addEventListener('change', resetOverrideAckOnly);

	            if (bandSel) bandSel.addEventListener('change', () => {
	                if (lowAck) lowAck.checked = false;
	                render();
	            });

	            if (ack) ack.addEventListener('change', render);
	            if (lowAck) lowAck.addEventListener('change', render);

	            document.addEventListener('vms_comp_options_updated', () => {
	                lastPaySig = payStateSignature();
	                render();
	            });

	            render();
	        })();
	    </script>

    <script>
        (function() {
            const form = document.getElementById('post');
            if (!form) return;

            const hiddenConfirm = document.getElementById('vms_cancel_bulk_retry_confirm');
            const btn = form.querySelector('button[type="submit"][name="vms_event_plan_action"][value="retry_cancellation_all"]');
            if (!btn || !hiddenConfirm) return;

            btn.addEventListener('click', function(e) {
                hiddenConfirm.value = '0';
                const requires = (btn.getAttribute('data-vms-requires-refund-confirm') === '1');
                if (!requires) {
                    return;
                }
                const ok = window.confirm('Refund execution is currently failed or blocked. Retrying all steps may attempt refund execution again. Continue?');
                if (!ok) {
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }
                hiddenConfirm.value = '1';
            });
        })();
    </script>

    <script>
        (function() {
            const btn = document.getElementById('vms_run_live_refunds_now_button');
            if (!btn) return;

            btn.addEventListener('click', function(e) {
                const href = btn.getAttribute('href') || '';
                if (!href) {
                    e.preventDefault();
                    window.alert('Unable to start the live refund action because the request link is missing.');
                    return;
                }

                const message = 'Run LIVE refunds now for this already-cancelled event? This does not save the Event Plan. VMS will attempt WooCommerce gateway refunds for remaining eligible ticket lines and queue anything unsafe for manual review.';
                if (!window.confirm(message)) {
                    e.preventDefault();
                    return;
                }

                btn.classList.add('disabled');
                btn.setAttribute('aria-disabled', 'true');
                btn.style.pointerEvents = 'none';
                window.location.href = href;
                e.preventDefault();
            });
        })();
    </script>

    <script>
        (function() {
            const form = document.getElementById('post');
            if (!form) return;

            const btn = form.querySelector('button[type="submit"][name="vms_event_plan_action"][value="mark_cancelled"]');
            const dateField = document.getElementById('vms_reschedule_event_date');
            const policyField = document.getElementById('vms_cancel_policy');
            const autoRefundConfirmField = document.getElementById('vms_cancel_auto_refund_confirmed');
            if (!btn || btn.disabled) return;

            btn.addEventListener('click', function(e) {
                if (autoRefundConfirmField) autoRefundConfirmField.value = '0';
                const replacementDate = dateField ? String(dateField.value || '').trim() : '';
                const policy = policyField ? String(policyField.value || '').trim() : '';
                const usesAutoRefund = (policy === 'stop_sales_auto_refund' || policy === 'stop_sales_auto_refund_remove_attendees');

                let message = 'Are you sure you want to mark this event as Cancelled?';
                if (replacementDate !== '') {
                    message += ' VMS will also create a linked Draft Event Plan for ' + replacementDate + '.';
                }
                if (usesAutoRefund) {
                    message += ' This will attempt LIVE payment refunds for matching ticket orders through WooCommerce. Mixed orders will refund only the cancelled event ticket lines when possible, and anything unsafe will be queued for manual review.';
                }

                const ok = window.confirm(message);
                if (ok) {
                    if (usesAutoRefund && autoRefundConfirmField) {
                        autoRefundConfirmField.value = '1';
                    }
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
            });
        })();
    </script>

	    <script>
	        (function() {
	            function initSecondaryVendors(root) {
	                const scope = (root && typeof root.querySelector === 'function') ? root : document;
	                const section = (scope.id === 'vms-secondary-vendors-section')
	                    ? scope
	                    : scope.querySelector('#vms-secondary-vendors-section');
	                if (!section || section.dataset.vmsSecondaryInitBound === '1') {
	                    return;
	                }

	                const groupsWrap = section.querySelector('#vms-secondary-vendor-groups');
	                const btnAddGroup = section.querySelector('#vms-secondary-vendor-add-group');
	                const btnClear = section.querySelector('#vms-secondary-vendor-clear');
	                const btnSave = section.querySelector('#vms-secondary-vendor-save');
	                const statusEl = section.querySelector('[data-vms-secondary-save-status]');
	                const groupTemplate = section.querySelector('#vms-secondary-vendor-group-template');
	                const rowTemplate = section.querySelector('#vms-secondary-vendor-row-template');
	                const clearInput = section.querySelector('#vms-clear-secondary-vendors-intent');
	                const ajaxUrl = String(section.dataset.vmsSaveUrl || '').trim();
	                const saveNonce = String(section.dataset.vmsSaveNonce || '').trim();
	                const postId = parseInt(section.dataset.vmsSavePostId || '0', 10) || 0;
	                const configNode = section.querySelector('[data-vms-secondary-config]');
	                let config = {};

	                if (!groupsWrap || !btnAddGroup || !btnSave || !groupTemplate || !rowTemplate) {
	                    return;
	                }

	                try {
	                    config = configNode ? JSON.parse(String(configNode.textContent || '{}')) : {};
	                } catch (error) {
	                    config = {};
	                }

	                const typeOptions = Array.isArray(config.typeOptions) ? config.typeOptions : [];
	                const modeOptions = Array.isArray(config.modeOptions) ? config.modeOptions : [];
	                const pools = config && typeof config.pools === 'object' && config.pools ? config.pools : {};
	                const labels = config && typeof config.labels === 'object' && config.labels ? config.labels : {};
	                const marketTypeSlug = 'market_vendor';

	                function escapeHtml(value) {
	                    return String(value || '').replace(/[&<>"']/g, function(char) {
	                        return ({
	                            '&': '&amp;',
	                            '<': '&lt;',
	                            '>': '&gt;',
	                            '"': '&quot;',
	                            "'": '&#39;'
	                        })[char] || char;
	                    });
	                }

	                function setStatus(target, message, type) {
	                    if (!target) {
	                        return;
	                    }
	                    target.textContent = String(message || '');
	                    target.setAttribute('data-vms-state', String(type || 'info'));
	                }

	                function setClearIntent(shouldClear) {
	                    if (clearInput) {
	                        clearInput.value = shouldClear ? '1' : '0';
	                    }
	                }

	                function vendorTypeOptionsForGroup(group) {
	                    const currentType = String(group.querySelector('.vms-secondary-vendor-group-type')?.value || '').trim();
	                    const used = new Set();
	                    groupsWrap.querySelectorAll('.vms-secondary-vendor-group').forEach((node) => {
	                        if (node === group) {
	                            return;
	                        }
	                        const slug = String(node.querySelector('.vms-secondary-vendor-group-type')?.value || '').trim();
	                        if (slug) {
	                            used.add(slug);
	                        }
	                    });

	                    return typeOptions.map((option) => {
	                        const slug = String(option.slug || '').trim();
	                        return Object.assign({}, option, {
	                            disabled: slug !== '' && slug !== currentType && used.has(slug)
	                        });
	                    });
	                }

	                function defaultModeForType(typeSlug) {
	                    const match = typeOptions.find((option) => String(option.slug || '') === String(typeSlug || ''));
	                    return String(match && match.default_mode ? match.default_mode : 'standard');
	                }

	                function defaultSlotLimitForType(typeSlug) {
	                    const match = typeOptions.find((option) => String(option.slug || '') === String(typeSlug || ''));
	                    if (!match || match.default_slot_limit === undefined || match.default_slot_limit === null || match.default_slot_limit === '') {
	                        return '';
	                    }
	                    return String(match.default_slot_limit);
	                }

	                function hasSelectedType(group) {
	                    return !!String(group.querySelector('.vms-secondary-vendor-group-type')?.value || '').trim();
	                }

	                function poolForType(typeSlug) {
	                    const rows = pools && pools[typeSlug];
	                    return Array.isArray(rows) ? rows : [];
	                }

	                function isMarketGroup(group) {
	                    const typeSlug = String(group.querySelector('.vms-secondary-vendor-group-type')?.value || '').trim();
	                    const mode = String(group.querySelector('.vms-secondary-vendor-group-mode')?.value || '').trim();
	                    return mode === 'market' || typeSlug === marketTypeSlug;
	                }

	                function poolRowForVendor(typeSlug, vendorId) {
	                    const normalizedType = String(typeSlug || '').trim();
	                    const targetVendorId = parseInt(vendorId || '0', 10) || 0;
	                    if (!normalizedType || !(targetVendorId > 0)) {
	                        return null;
	                    }

	                    return poolForType(normalizedType).find((row) => {
	                        const rowVendorId = parseInt(row && row.vendor_id ? row.vendor_id : '0', 10) || 0;
	                        return rowVendorId === targetVendorId;
	                    }) || null;
	                }

	                function parseGroupVendorIds(group, datasetKey) {
	                    if (!group || !datasetKey) {
	                        return [];
	                    }

	                    try {
	                        const parsed = JSON.parse(String(group.dataset[datasetKey] || '[]'));
	                        return Array.isArray(parsed)
	                            ? parsed.map((value) => parseInt(value || '0', 10) || 0).filter((value) => value > 0)
	                            : [];
	                    } catch (error) {
	                        return [];
	                    }
	                }

	                function buildRowIndicators(group, vendorId) {
	                    const normalizedVendorId = parseInt(vendorId || '0', 10) || 0;
	                    const typeSlug = String(group.querySelector('.vms-secondary-vendor-group-type')?.value || '').trim();
	                    const badges = [];
	                    const market = isMarketGroup(group);
	                    const pendingLabel = String(labels.pendingVendor || 'Select vendor');
	                    const marketLabel = String(labels.market || 'Market');

	                    if (!(normalizedVendorId > 0)) {
	                        badges.push({ label: pendingLabel, variant: 'pending' });
	                    } else {
	                        const row = poolRowForVendor(typeSlug, normalizedVendorId);
	                        const missingIds = parseGroupVendorIds(group, 'vmsMissingIds');
	                        const mismatchIds = parseGroupVendorIds(group, 'vmsMismatchIds');
	                        const unqualifiedIds = parseGroupVendorIds(group, 'vmsUnqualifiedIds');

	                        if (missingIds.includes(normalizedVendorId)) {
	                            badges.push({ label: String(labels.missingVendor || 'Missing vendor'), variant: 'missing' });
	                        } else {
	                            const availability = String((row && row.availability_state) || '').trim();
	                            if (availability === 'available') {
	                                badges.push({ label: String(labels.available || 'Available'), variant: 'available' });
	                            } else if (availability === 'unavailable') {
	                                badges.push({ label: String(labels.unavailable || 'Not available'), variant: 'unavailable' });
	                            } else {
	                                badges.push({ label: String(labels.unknownAvailability || 'Availability unknown'), variant: 'unknown' });
	                            }
	                        }

	                        if (mismatchIds.includes(normalizedVendorId)) {
	                            badges.push({ label: String(labels.typeMismatch || 'Type mismatch'), variant: 'mismatch' });
	                        }
	                        const rowNeedsAttention = !!(row && Object.prototype.hasOwnProperty.call(row, 'qualified') && !row.qualified);
	                        if (unqualifiedIds.includes(normalizedVendorId) || rowNeedsAttention) {
	                            badges.push({ label: String(labels.needsAttention || 'Needs attention'), variant: 'attention' });
	                        } else {
	                            badges.push({ label: String(labels.qualified || 'Qualified'), variant: 'qualified' });
	                        }
	                    }

	                    if (market) {
	                        badges.unshift({ label: marketLabel, variant: 'market' });
	                    }

	                    return badges.map((badge) => {
	                        return `<span class="vms-secondary-vendor-badge vms-secondary-vendor-badge--${escapeHtml(String(badge.variant || 'unknown'))}">${escapeHtml(String(badge.label || ''))}</span>`;
	                    }).join('');
	                }

	                function updateRowIndicators(group, row) {
	                    if (!group || !row) {
	                        return;
	                    }
	                    const indicators = row.querySelector('[data-vms-secondary-row-indicators]');
	                    if (!indicators) {
	                        return;
	                    }
	                    const select = row.querySelector('.vms-secondary-vendor-select');
	                    indicators.innerHTML = buildRowIndicators(group, select ? select.value : '');
	                }

	                function updateGroupMarketTarget(group, market) {
	                    if (!group) {
	                        return;
	                    }
	                    const isMarket = market !== undefined ? !!market : isMarketGroup(group);
	                    const targetField = group.querySelector('.vms-secondary-vendor-group__field--market-target');
	                    const dispatchField = group.querySelector('.vms-secondary-vendor-group__field--market-dispatch');
	                    const neededInput = group.querySelector('.vms-secondary-vendor-group-needed-slots');
	                    const dispatchHidden = group.querySelector('.vms-secondary-vendor-group-open-for-dispatch-hidden');
	                    const dispatchInput = group.querySelector('.vms-secondary-vendor-group-open-for-dispatch');

	                    if (targetField) {
	                        targetField.hidden = !isMarket;
	                    }
	                    if (dispatchField) {
	                        dispatchField.hidden = !isMarket;
	                    }
	                    [neededInput, dispatchHidden, dispatchInput].forEach((input) => {
	                        if (input) {
	                            input.disabled = !isMarket;
	                        }
	                    });
	                }

	                function updateGroupLayout(group) {
	                    if (!group) {
	                        return;
	                    }
	                    const hasType = hasSelectedType(group);
	                    const market = isMarketGroup(group);
	                    const guidance = group.querySelector('.vms-secondary-vendor-group__guidance');
	                    group.classList.toggle('vms-secondary-vendor-group--market', market);
	                    group.classList.toggle('vms-secondary-vendor-group--type-pending', !hasType);
	                    if (guidance) {
	                        guidance.hidden = hasType;
	                    }
	                    updateGroupMarketTarget(group, market);
	                    Array.from(group.querySelectorAll('.vms-secondary-vendor-row')).forEach((row) => {
	                        updateRowIndicators(group, row);
	                    });
	                }

	                function updateGroupCapacityOverride(group, isOverCapacity) {
	                    if (!group) {
	                        return;
	                    }
	                    const overrideWrap = group.querySelector('.vms-secondary-vendor-group__override');
	                    const overrideInput = group.querySelector('.vms-secondary-vendor-group-over-capacity-override');
	                    if (!overrideWrap || !overrideInput) {
	                        return;
	                    }

	                    overrideWrap.hidden = !isOverCapacity;
	                    if (!isOverCapacity) {
	                        overrideInput.checked = false;
	                    }
	                }

	                function ensureGroupTypeOptions(group) {
	                    const typeSelect = group.querySelector('.vms-secondary-vendor-group-type');
	                    if (!typeSelect) {
	                        return;
	                    }

	                    const currentValue = String(typeSelect.value || '').trim();
	                    typeSelect.innerHTML = '';
	                    const placeholder = document.createElement('option');
	                    placeholder.value = '';
	                    placeholder.textContent = String(labels.selectType || '-- Select a Vendor Type --');
	                    typeSelect.appendChild(placeholder);

	                    vendorTypeOptionsForGroup(group).forEach((option) => {
	                        const node = document.createElement('option');
	                        node.value = String(option.slug || '');
	                        node.textContent = String(option.label || option.slug || '');
	                        if (option.disabled) {
	                            node.disabled = true;
	                        }
	                        if (node.value === currentValue) {
	                            node.selected = true;
	                        }
	                        typeSelect.appendChild(node);
	                    });
	                }

	                function ensureModeOptions(group) {
	                    const modeSelect = group.querySelector('.vms-secondary-vendor-group-mode');
	                    if (!modeSelect) {
	                        return;
	                    }
	                    const currentValue = String(modeSelect.value || '').trim();
	                    modeSelect.innerHTML = '';
	                    modeOptions.forEach((option) => {
	                        const node = document.createElement('option');
	                        node.value = String(option.slug || '');
	                        node.textContent = String(option.label || option.slug || '');
	                        if (node.value === currentValue) {
	                            node.selected = true;
	                        }
	                        modeSelect.appendChild(node);
	                    });
	                    if (!modeSelect.value && modeOptions.length) {
	                        modeSelect.value = String(modeOptions[0].slug || 'standard');
	                    }
	                }

	                function syncVendorSelect(select, typeSlug, selectedValue) {
	                    if (!select) {
	                        return;
	                    }

	                    const normalizedType = String(typeSlug || '').trim();
	                    const currentSelected = selectedValue !== undefined && selectedValue !== null
	                        ? String(selectedValue)
	                        : String(select.value || select.dataset.selectedId || '');
	                    select.innerHTML = '';
	                    if (!normalizedType) {
	                        const opt = document.createElement('option');
	                        opt.value = '';
	                        opt.textContent = String(labels.selectTypeFirst || '-- Select a Vendor Type first --');
	                        select.appendChild(opt);
	                        select.disabled = true;
	                        return;
	                    }

	                    const placeholder = document.createElement('option');
	                    placeholder.value = '';
	                    placeholder.textContent = String(labels.selectVendor || '-- Select a Vendor --');
	                    select.appendChild(placeholder);

	                    poolForType(normalizedType).forEach((row) => {
	                        const vendorId = parseInt(row && row.vendor_id ? row.vendor_id : '0', 10) || 0;
	                        if (!(vendorId > 0)) {
	                            return;
	                        }
	                        const opt = document.createElement('option');
	                        opt.value = String(vendorId);
	                        opt.textContent = String(row.label || row.vendor_title || vendorId);
	                        if (String(vendorId) === currentSelected) {
	                            opt.selected = true;
	                        }
	                        select.appendChild(opt);
	                    });

	                    select.disabled = false;
	                    select.dataset.selectedId = String(select.value || '');
	                }

	                function updateAddNewLink(group) {
	                    const link = group.querySelector('.vms-secondary-vendor-add-new-link');
	                    const typeSlug = String(group.querySelector('.vms-secondary-vendor-group-type')?.value || '').trim();
	                    if (!link) {
	                        return;
	                    }

	                    const url = new URL(link.href, window.location.origin);
	                    if (typeSlug) {
	                        url.searchParams.set('vms_prefill_vendor_type', typeSlug);
	                    } else {
	                        url.searchParams.delete('vms_prefill_vendor_type');
	                    }
	                    link.href = url.toString();
	                }

	                function appendGroupMarketTargetSummary(parts, group, filled, market) {
	                    if (!market) {
	                        return;
	                    }
	                    const neededValue = String(group.querySelector('.vms-secondary-vendor-group-needed-slots')?.value || '').trim();
	                    if (!neededValue) {
	                        return;
	                    }
	                    const target = Math.max(0, parseInt(neededValue, 10) || 0);
	                    const openNeeded = Math.max(0, target - filled);
	                    const dispatchInput = group.querySelector('.vms-secondary-vendor-group-open-for-dispatch');
	                    const openForDispatch = dispatchInput ? !!dispatchInput.checked : true;
	                    const targetTemplate = String(labels.target || 'Target %d');
	                    parts.push(targetTemplate.replace('%d', String(target)));
	                    if (openForDispatch) {
	                        const neededTemplate = String(labels.needed || '%d needed');
	                        parts.push(neededTemplate.replace('%d', String(openNeeded)));
	                    } else {
	                        parts.push(String(labels.hiddenFromDispatch || 'Hidden from ADD'));
	                    }
	                }

	                function updateGroupSummary(group) {
	                    const summary = group.querySelector('.vms-secondary-vendor-group__summary');
	                    if (!summary) {
	                        return;
	                    }

	                    const hasType = hasSelectedType(group);
	                    const market = isMarketGroup(group);
	                    const slotLimitValue = String(group.querySelector('.vms-secondary-vendor-group-slot-limit')?.value || '').trim();
	                    const filled = Array.from(group.querySelectorAll('.vms-secondary-vendor-select'))
	                        .map((select) => String(select.value || '').trim())
	                        .filter(Boolean).length;
	                    const parts = [];
	                    let isOverCapacity = false;

	                    summary.classList.remove('is-warning');

	                    if (!slotLimitValue) {
	                        parts.push(`${filled} selected`);
	                    } else {
	                        const limit = Math.max(0, parseInt(slotLimitValue, 10) || 0);
	                        parts.push(`${filled} of ${limit} filled`);
	                        if (hasType && filled > limit) {
	                            const overBy = filled - limit;
	                            const template = String(labels.overCapacity || 'Over capacity by %d');
	                            parts.push(market ? String(labels.market || 'Market') : String(labels.standard || 'Standard'));
	                            appendGroupMarketTargetSummary(parts, group, filled, market);
	                            parts.push(template.replace('%d', String(overBy)));
	                            isOverCapacity = true;
	                            summary.classList.add('is-warning');
	                            summary.textContent = parts.join(' • ');
	                            updateGroupCapacityOverride(group, isOverCapacity);
	                            updateGroupLayout(group);
	                            return;
	                        }
	                    }

	                    if (!hasType) {
	                        parts.push(String(labels.chooseType || 'Choose type first'));
	                        summary.textContent = parts.join(' • ');
	                        updateGroupCapacityOverride(group, false);
	                        updateGroupLayout(group);
	                        return;
	                    }

	                    if (!slotLimitValue) {
	                        parts.push(market ? String(labels.market || 'Market') : String(labels.standard || 'Standard'));
	                        appendGroupMarketTargetSummary(parts, group, filled, market);
	                        parts.push(String(labels.occupancyUnknown || 'No slot limit set'));
	                        summary.textContent = parts.join(' • ');
	                        updateGroupCapacityOverride(group, false);
	                        updateGroupLayout(group);
	                        return;
	                    }

	                    const limit = Math.max(0, parseInt(slotLimitValue, 10) || 0);
	                    parts.push(market ? String(labels.market || 'Market') : String(labels.standard || 'Standard'));
	                    appendGroupMarketTargetSummary(parts, group, filled, market);
	                    if (filled > limit) {
	                        const overBy = filled - limit;
	                        const template = String(labels.overCapacity || 'Over capacity by %d');
	                        parts.push(template.replace('%d', String(overBy)));
	                        isOverCapacity = true;
	                        summary.classList.add('is-warning');
	                    }
	                    summary.textContent = parts.join(' • ');
	                    updateGroupCapacityOverride(group, isOverCapacity);
	                    updateGroupLayout(group);
	                }

	                function updateGroupNames(group, groupIndex) {
	                    group.dataset.vmsGroupIndex = String(groupIndex);
	                    const typeSelect = group.querySelector('.vms-secondary-vendor-group-type');
	                    const modeSelect = group.querySelector('.vms-secondary-vendor-group-mode');
	                    const slotInput = group.querySelector('.vms-secondary-vendor-group-slot-limit');
	                    const overrideInput = group.querySelector('.vms-secondary-vendor-group-over-capacity-override');
	                    const neededInput = group.querySelector('.vms-secondary-vendor-group-needed-slots');
	                    const dispatchHidden = group.querySelector('.vms-secondary-vendor-group-open-for-dispatch-hidden');
	                    const dispatchInput = group.querySelector('.vms-secondary-vendor-group-open-for-dispatch');
	                    if (typeSelect) {
	                        typeSelect.name = `vms_secondary_vendor_assignments[${groupIndex}][type_slug]`;
	                    }
	                    if (modeSelect) {
	                        modeSelect.name = `vms_secondary_vendor_assignments[${groupIndex}][mode]`;
	                    }
	                    if (slotInput) {
	                        slotInput.name = `vms_secondary_vendor_assignments[${groupIndex}][slot_limit]`;
	                    }
	                    if (overrideInput) {
	                        overrideInput.name = `vms_secondary_vendor_assignments[${groupIndex}][allow_over_capacity]`;
	                    }
	                    if (neededInput) {
	                        neededInput.name = `vms_secondary_vendor_assignments[${groupIndex}][needed_slots]`;
	                    }
	                    if (dispatchHidden) {
	                        dispatchHidden.name = `vms_secondary_vendor_assignments[${groupIndex}][open_for_dispatch]`;
	                    }
	                    if (dispatchInput) {
	                        dispatchInput.name = `vms_secondary_vendor_assignments[${groupIndex}][open_for_dispatch]`;
	                    }
	                    Array.from(group.querySelectorAll('.vms-secondary-vendor-row')).forEach((row, rowIndex) => {
	                        row.dataset.vmsRowIndex = String(rowIndex);
	                        const select = row.querySelector('.vms-secondary-vendor-select');
	                        if (select) {
	                            select.name = `vms_secondary_vendor_assignments[${groupIndex}][vendor_ids][]`;
	                        }
	                    });
	                }

	                function wireRow(group, row) {
	                    if (!row || row.dataset.vmsSecondaryRowBound === '1') {
	                        return;
	                    }
	                    row.dataset.vmsSecondaryRowBound = '1';
	                    const btn = row.querySelector('.vms-secondary-vendor-remove');
	                    const select = row.querySelector('.vms-secondary-vendor-select');
	                    if (btn) {
	                        btn.addEventListener('click', function() {
	                            row.remove();
	                            if (!group.querySelector('.vms-secondary-vendor-row')) {
	                                addRow(group, '');
	                            }
	                            renumberGroups();
	                            updateGroupSummary(group);
	                            setClearIntent(false);
	                            setStatus(statusEl, '', 'info');
	                        });
	                    }
	                    if (select) {
	                        select.addEventListener('change', function() {
	                            select.dataset.selectedId = String(select.value || '');
	                            updateRowIndicators(group, row);
	                            updateGroupSummary(group);
	                            setClearIntent(false);
	                            setStatus(statusEl, '', 'info');
	                        });
	                    }
	                }

	                function addRow(group, selectedValue) {
	                    const rows = group.querySelector('.vms-secondary-vendor-rows');
	                    if (!rows) {
	                        return null;
	                    }
	                    const node = rowTemplate.content.cloneNode(true);
	                    const row = node.querySelector('.vms-secondary-vendor-row');
	                    rows.appendChild(node);
	                    const appendedRow = rows.lastElementChild;
	                    const select = appendedRow ? appendedRow.querySelector('.vms-secondary-vendor-select') : null;
	                    if (select) {
	                        select.dataset.selectedId = String(selectedValue || '');
	                    }
	                    wireRow(group, appendedRow);
	                    syncVendorSelect(select, String(group.querySelector('.vms-secondary-vendor-group-type')?.value || ''), selectedValue || '');
	                    updateRowIndicators(group, appendedRow);
	                    updateGroupSummary(group);
	                    return appendedRow;
	                }

	                function refreshGroup(group, options = {}) {
	                    const preserveSelections = options.preserveSelections !== false;
	                    const resetDefaults = !!options.resetDefaults;
	                    const typeSelect = group.querySelector('.vms-secondary-vendor-group-type');
	                    const modeSelect = group.querySelector('.vms-secondary-vendor-group-mode');
	                    const slotInput = group.querySelector('.vms-secondary-vendor-group-slot-limit');
	                    const selectedType = String(typeSelect?.value || '').trim();

	                    ensureGroupTypeOptions(group);
	                    ensureModeOptions(group);

	                    if (resetDefaults && modeSelect) {
	                        modeSelect.value = defaultModeForType(selectedType);
	                    }
	                    if (resetDefaults && slotInput) {
	                        slotInput.value = defaultSlotLimitForType(selectedType);
	                    }

	                    const rows = Array.from(group.querySelectorAll('.vms-secondary-vendor-row'));
	                    if (!rows.length) {
	                        addRow(group, '');
	                    }
	                    Array.from(group.querySelectorAll('.vms-secondary-vendor-row')).forEach((row) => {
	                        const select = row.querySelector('.vms-secondary-vendor-select');
	                        const selectedValue = preserveSelections ? String(select?.value || select?.dataset.selectedId || '') : '';
	                        syncVendorSelect(select, selectedType, selectedValue);
	                        updateRowIndicators(group, row);
	                    });
	                    updateAddNewLink(group);
	                    updateGroupSummary(group);
	                }

	                function wireGroup(group) {
	                    if (!group || group.dataset.vmsSecondaryGroupBound === '1') {
	                        return;
	                    }
	                    group.dataset.vmsSecondaryGroupBound = '1';
	                    const addRowBtn = group.querySelector('.vms-secondary-vendor-add-row');
	                    const removeGroupBtn = group.querySelector('.vms-secondary-vendor-remove-group');
	                    const typeSelect = group.querySelector('.vms-secondary-vendor-group-type');
	                    const modeSelect = group.querySelector('.vms-secondary-vendor-group-mode');
	                    const slotInput = group.querySelector('.vms-secondary-vendor-group-slot-limit');
	                    const overrideInput = group.querySelector('.vms-secondary-vendor-group-over-capacity-override');
	                    const neededInput = group.querySelector('.vms-secondary-vendor-group-needed-slots');
	                    const dispatchInput = group.querySelector('.vms-secondary-vendor-group-open-for-dispatch');

	                    if (addRowBtn) {
	                        addRowBtn.addEventListener('click', function() {
	                            addRow(group, '');
	                            renumberGroups();
	                            setClearIntent(false);
	                            setStatus(statusEl, '', 'info');
	                        });
	                    }
	                    if (removeGroupBtn) {
	                        removeGroupBtn.addEventListener('click', function() {
	                            group.remove();
	                            refreshAllGroups();
	                            setClearIntent(false);
	                            setStatus(statusEl, '', 'info');
	                        });
	                    }
	                    if (typeSelect) {
	                        typeSelect.addEventListener('change', function() {
	                            refreshGroup(group, { preserveSelections: false, resetDefaults: true });
	                            refreshAllGroups();
	                            setClearIntent(false);
	                            setStatus(statusEl, '', 'info');
	                        });
	                    }
	                    if (modeSelect) {
	                        modeSelect.addEventListener('change', function() {
	                            updateGroupSummary(group);
	                            setClearIntent(false);
	                            setStatus(statusEl, '', 'info');
	                        });
	                    }
	                    if (slotInput) {
	                        slotInput.addEventListener('input', function() {
	                            updateGroupSummary(group);
	                            setClearIntent(false);
	                            setStatus(statusEl, '', 'info');
	                        });
	                    }
	                    if (overrideInput) {
	                        overrideInput.addEventListener('change', function() {
	                            setClearIntent(false);
	                            setStatus(statusEl, '', 'info');
	                        });
	                    }
	                    if (neededInput) {
	                        neededInput.addEventListener('input', function() {
	                            updateGroupSummary(group);
	                            setClearIntent(false);
	                            setStatus(statusEl, '', 'info');
	                        });
	                    }
	                    if (dispatchInput) {
	                        dispatchInput.addEventListener('change', function() {
	                            updateGroupSummary(group);
	                            setClearIntent(false);
	                            setStatus(statusEl, '', 'info');
	                        });
	                    }

	                    Array.from(group.querySelectorAll('.vms-secondary-vendor-row')).forEach((row) => wireRow(group, row));
	                }

	                function renumberGroups() {
	                    Array.from(groupsWrap.querySelectorAll('.vms-secondary-vendor-group')).forEach((group, index) => {
	                        updateGroupNames(group, index);
	                    });
	                }

	                function nextAvailableType() {
	                    const used = new Set(Array.from(groupsWrap.querySelectorAll('.vms-secondary-vendor-group-type')).map((select) => String(select.value || '').trim()).filter(Boolean));
	                    const next = typeOptions.find((option) => {
	                        const slug = String(option.slug || '').trim();
	                        return slug && !used.has(slug);
	                    });
	                    return next ? String(next.slug || '') : '';
	                }

	                function createGroup(typeSlug = '') {
	                    const node = groupTemplate.content.cloneNode(true);
	                    groupsWrap.appendChild(node);
	                    const group = groupsWrap.lastElementChild;
	                    wireGroup(group);
	                    const initialType = String(typeSlug || '').trim();
	                    const typeSelect = group.querySelector('.vms-secondary-vendor-group-type');
	                    if (typeSelect && initialType) {
	                        typeSelect.value = initialType;
	                    }
	                    refreshGroup(group, { preserveSelections: false, resetDefaults: true });
	                    renumberGroups();
	                    return group;
	                }

	                function refreshAllGroups() {
	                    Array.from(groupsWrap.querySelectorAll('.vms-secondary-vendor-group')).forEach((group) => {
	                        wireGroup(group);
	                        refreshGroup(group, { preserveSelections: true, resetDefaults: false });
	                    });
	                    renumberGroups();
	                    btnAddGroup.disabled = !nextAvailableType();
	                }

	                btnAddGroup.addEventListener('click', function() {
	                    if (btnAddGroup.disabled) {
	                        return;
	                    }
	                    createGroup('');
	                    refreshAllGroups();
	                    setClearIntent(false);
	                    setStatus(statusEl, '', 'info');
	                });

	                if (btnClear) {
	                    btnClear.addEventListener('click', function() {
	                        groupsWrap.innerHTML = '';
	                        setClearIntent(true);
	                        refreshAllGroups();
	                        setStatus(statusEl, '', 'info');
	                    });
	                }

	                function serializeGroups() {
	                    return Array.from(groupsWrap.querySelectorAll('.vms-secondary-vendor-group')).map((group) => {
	                        const typeSlug = String(group.querySelector('.vms-secondary-vendor-group-type')?.value || '').trim();
	                        const mode = String(group.querySelector('.vms-secondary-vendor-group-mode')?.value || '').trim();
	                        const slotLimit = String(group.querySelector('.vms-secondary-vendor-group-slot-limit')?.value || '').trim();
	                        const allowOverCapacity = !!group.querySelector('.vms-secondary-vendor-group-over-capacity-override')?.checked;
	                        const market = isMarketGroup(group);
	                        const neededSlots = String(group.querySelector('.vms-secondary-vendor-group-needed-slots')?.value || '').trim();
	                        const openForDispatch = !!group.querySelector('.vms-secondary-vendor-group-open-for-dispatch')?.checked;
	                        const vendorIds = Array.from(group.querySelectorAll('.vms-secondary-vendor-select'))
	                            .map((select) => String(select.value || '').trim())
	                            .filter(Boolean);
	                        return {
	                            typeSlug,
	                            mode,
	                            slotLimit,
	                            allowOverCapacity,
	                            market,
	                            neededSlots,
	                            openForDispatch,
	                            vendorIds
	                        };
	                    }).filter((group) => group.typeSlug !== '' || group.vendorIds.length > 0 || group.slotLimit !== '' || (group.market && group.neededSlots !== ''));
	                }

	                async function saveModule() {
	                    if (!btnSave || btnSave.disabled) {
	                        return;
	                    }
	                    if (!ajaxUrl || !saveNonce || !(postId > 0)) {
	                        setStatus(statusEl, String(labels.saveUnavailable || 'Additional Vendors save is not available right now.'), 'error');
	                        return;
	                    }

	                    btnSave.disabled = true;
	                    setStatus(statusEl, String(labels.saving || 'Saving Additional Vendors…'), 'info');

	                    const params = new URLSearchParams();
	                    params.set('action', 'vms_save_event_plan_secondary_vendors');
	                    params.set('post_id', String(postId));
	                    params.set('nonce', saveNonce);
	                    params.set('vms_clear_secondary_vendors', clearInput ? String(clearInput.value || '0') : '0');

	                    serializeGroups().forEach((group, groupIndex) => {
	                        params.set(`vms_secondary_vendor_assignments[${groupIndex}][type_slug]`, group.typeSlug);
	                        params.set(`vms_secondary_vendor_assignments[${groupIndex}][mode]`, group.mode);
	                        params.set(`vms_secondary_vendor_assignments[${groupIndex}][slot_limit]`, group.slotLimit);
	                        if (group.allowOverCapacity) {
	                            params.set(`vms_secondary_vendor_assignments[${groupIndex}][allow_over_capacity]`, '1');
	                        }
	                        if (group.market) {
	                            if (group.neededSlots !== '') {
	                                params.set(`vms_secondary_vendor_assignments[${groupIndex}][needed_slots]`, group.neededSlots);
	                            }
	                            params.set(`vms_secondary_vendor_assignments[${groupIndex}][open_for_dispatch]`, group.openForDispatch ? '1' : '0');
	                        }
	                        group.vendorIds.forEach((vendorId) => {
	                            params.append(`vms_secondary_vendor_assignments[${groupIndex}][vendor_ids][]`, vendorId);
	                        });
	                    });

	                    const scenarioField = document.querySelector('#post input[name="_vms_ep_perf_trace_scenario"]');
	                    if (scenarioField && scenarioField.value) {
	                        params.set('_vms_ep_perf_trace_scenario', String(scenarioField.value || ''));
	                    }

	                    try {
	                        const response = await window.fetch(ajaxUrl, {
	                            method: 'POST',
	                            credentials: 'same-origin',
	                            headers: {
	                                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
	                            },
	                            body: params.toString()
	                        });
	                        const payload = await response.json().catch(() => null);
	                        if (!response.ok || !payload || !payload.success || !payload.data || typeof payload.data.html !== 'string') {
	                            const message = payload && payload.data && typeof payload.data.message === 'string'
	                                ? payload.data.message
	                                : '';
	                            throw new Error(message || 'secondary_vendor_save_failed');
	                        }

	                        const collapsibleSection = section.closest('.vms-collapsible-section[data-section-key]');
	                        const body = collapsibleSection ? collapsibleSection.querySelector('.vms-collapsible-body') : null;
	                        if (!collapsibleSection || !body) {
	                            throw new Error('secondary_vendor_render_target_missing');
	                        }

	                        body.innerHTML = payload.data.html;
	                        collapsibleSection.dataset.vmsLazyLoaded = '1';
	                        collapsibleSection.dataset.hasData = payload.data.has_data ? '1' : '0';
	                        const meta = collapsibleSection.querySelector('.vms-collapsible-meta');
	                        if (meta && typeof payload.data.summary_meta === 'string') {
	                            meta.textContent = payload.data.summary_meta;
	                        }
	                        if (typeof window.vmsEventPlanPersistRequestedSection === 'function') {
	                            window.vmsEventPlanPersistRequestedSection('secondary_vendors');
	                        }
	                        if (typeof window.vmsEventPlanInitCollapsibleSection === 'function') {
	                            window.vmsEventPlanInitCollapsibleSection(collapsibleSection);
	                        }
	                        if (typeof window.vmsEventPlanInitSecondaryVendors === 'function') {
	                            window.vmsEventPlanInitSecondaryVendors(body);
	                        }
	                        const nextSection = body.querySelector('#vms-secondary-vendors-section');
	                        const nextStatus = nextSection ? nextSection.querySelector('[data-vms-secondary-save-status]') : null;
	                        setStatus(
	                            nextStatus,
	                            typeof payload.data.message === 'string' && payload.data.message !== ''
	                                ? payload.data.message
	                                : 'Additional Vendors saved.',
	                            payload.data.changed ? 'success' : 'info'
	                        );
	                        return;
	                    } catch (error) {
	                        const message = error && error.message && error.message !== 'secondary_vendor_save_failed'
	                            ? error.message
	                            : String(labels.saveFailed || 'Additional Vendors could not be saved. Reload the page and try again.');
	                        setStatus(statusEl, message, 'error');
	                    } finally {
	                        if (btnSave && document.body.contains(btnSave)) {
	                            btnSave.disabled = false;
	                        }
	                    }
	                }

	                if (btnSave) {
	                    btnSave.addEventListener('click', function() {
	                        saveModule();
	                    });
	                }

	                refreshAllGroups();
	                section.dataset.vmsSecondaryInitBound = '1';
	            }

	            window.vmsEventPlanInitSecondaryVendors = initSecondaryVendors;

	            if (document.readyState === 'loading') {
	                document.addEventListener('DOMContentLoaded', function() {
	                    initSecondaryVendors(document);
	                }, { once: true });
	            } else {
	                initSecondaryVendors(document);
	            }
	        })();
	    </script>

	    <script>
	        (function() {
	            const bandSel = document.getElementById('vms_band_vendor_id');
	            const wrap = document.getElementById('vms-tax-status');
            const bypassWrap = document.getElementById('vms-tax-bypass-inline');
            const bypassUntil = document.getElementById('vms-tax-bypass-until');
            const bypassReason = document.getElementById('vms-tax-bypass-reason');
            const bypassSetBtn = document.getElementById('vms-tax-bypass-set');
            const bypassClearBtn = document.getElementById('vms-tax-bypass-clear');
            const bypassMsg = document.getElementById('vms-tax-bypass-msg');
            const bypassActiveFlag = document.getElementById('vms-tax-bypass-active-flag');
            if (!bandSel || !wrap) return;

            function escapeHtml(str) {
                return String(str).replace(/[&<>"']/g, s => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                } [s]));
            }

            function selectedVendorId() {
                const raw = bandSel.value || '';
                const id = parseInt(raw, 10);
                return Number.isFinite(id) && id > 0 ? id : 0;
            }

            function setBypassMsg(text, type) {
                if (!bypassMsg) return;
                bypassMsg.textContent = text || '';
                bypassMsg.className = 'description vms-mt-6';
                if (type === 'error') {
                    bypassMsg.className += ' vms-text-danger';
                }
            }

            function setBypassUiEnabled(enabled) {
                const on = !!enabled;
                if (bypassUntil) bypassUntil.disabled = !on;
                if (bypassReason) bypassReason.disabled = !on;
                if (bypassSetBtn) bypassSetBtn.disabled = !on;
                if (bypassClearBtn) bypassClearBtn.disabled = !on;
            }

            function updateBypassDefaultsFromSelection() {
                if (!bypassWrap) return;
                const opt = bandSel.options[bandSel.selectedIndex];
                const hasVendor = !!(opt && selectedVendorId() > 0);

                setBypassUiEnabled(hasVendor);
                if (!hasVendor) {
                    bypassWrap.classList.add('vms-hidden');
                    wrap.classList.remove('vms-tax-has-bypass-inline', 'vms-tax-has-bypass-inline-active');
                    setBypassMsg('Select a vendor to manage bypass.', '');
                    return;
                }

                const taxOk = opt.getAttribute('data-tax-ok') === '1';
                const active = opt.getAttribute('data-tax-bypass-active') === '1';
                const until = (opt.getAttribute('data-tax-bypass-until') || '').trim();
                const reason = (opt.getAttribute('data-tax-bypass-reason') || '').trim();
                const fallbackUntil = (bypassWrap.getAttribute('data-default-until') || '').trim();
                const needed = (!taxOk || active);

                bypassWrap.classList.toggle('vms-hidden', !needed);
                wrap.classList.toggle('vms-tax-has-bypass-inline', needed);
                wrap.classList.toggle('vms-tax-has-bypass-inline-active', (needed && active));
                if (!needed) {
                    if (bypassReason) {
                        bypassReason.classList.remove('has-active-bypass');
                        bypassReason.value = '';
                    }
                    if (bypassActiveFlag) {
                        bypassActiveFlag.classList.add('vms-hidden');
                    }
                    bypassWrap.classList.remove('has-active-bypass');
                    wrap.classList.remove('vms-tax-has-bypass-inline', 'vms-tax-has-bypass-inline-active');
                    return;
                }

                if (bypassUntil) {
                    bypassUntil.value = until || fallbackUntil;
                }
                if (bypassReason) {
                    if (active) {
                        bypassReason.value = reason;
                    } else if (!bypassReason.value) {
                        bypassReason.value = '';
                    }
                    bypassReason.classList.toggle('has-active-bypass', active);
                }

                bypassWrap.classList.toggle('has-active-bypass', active);
                if (bypassActiveFlag) {
                    bypassActiveFlag.classList.toggle('vms-hidden', !active);
                }
                setBypassMsg(active ? ('Bypass active until ' + (until || '—') + '.') : 'No bypass is active for this vendor.', '');
            }

            function updateSelectedOptionBypass(active, until, reason) {
                const opt = bandSel.options[bandSel.selectedIndex];
                if (!opt) return;
                opt.setAttribute('data-tax-bypass-active', active ? '1' : '0');
                opt.setAttribute('data-tax-bypass-until', active ? (until || '') : '');
                opt.setAttribute('data-tax-bypass-reason', active ? (reason || '') : '');
            }

            function render() {
                const opt = bandSel.options[bandSel.selectedIndex];
                if (!opt || !opt.value) {
                    wrap.innerHTML =
                        '<div class="vms-tax-box vms-notice vms-notice--info">' +
                        '<div class="title">Tax Profile</div>' +
                        '<div class="muted">Select a Primary Vendor to see tax requirements.</div>' +
                        '</div>';
                    updateBypassDefaultsFromSelection();
                    return;
                }

                const ok = opt.getAttribute('data-tax-ok') === '1';
                const bypassActive = opt.getAttribute('data-tax-bypass-active') === '1';
                const bypassUntil = (opt.getAttribute('data-tax-bypass-until') || '').trim();
                const missing = (opt.getAttribute('data-tax-missing') || '').trim();

                if (ok) {
                    wrap.innerHTML =
                        '<div class="vms-tax-box ok vms-notice vms-notice--success">' +
                        '<div class="title">✅ Tax Profile Complete</div>' +
                        '<div class="muted">This vendor is eligible for Ready/Publish (tax-wise).</div>' +
                        '</div>';
                } else if (bypassActive) {
                    wrap.innerHTML =
                        '<div class="vms-tax-box warn vms-notice vms-notice--warning">' +
                        '<div class="title">🟡 Tax Profile Bypass Active</div>' +
                        '<div class="muted"><strong>Missing:</strong> ' + escapeHtml(missing || '—') + '</div>' +
                        '<div class="muted vms-mt-6">Ready/Publish is allowed while the bypass is active' + (bypassUntil ? (' (until ' + escapeHtml(bypassUntil) + ')') : '') + '.</div>' +
                        '</div>';
                } else {
                    wrap.innerHTML =
                        '<div class="vms-tax-box bad vms-notice vms-notice--warning">' +
                        '<div class="title">⚠️ Tax Profile Incomplete</div>' +
                        '<div class="muted"><strong>Missing:</strong> ' + escapeHtml(missing || '—') + '</div>' +
                        '<div class="muted vms-mt-6">Needs attention — payments/exports blocked until complete or bypass set. Ready/Publish allowed.</div>' +
                        '</div>';
                }

                updateBypassDefaultsFromSelection();
            }

            async function postBypass(action, payload) {
                const nonce = bypassWrap ? (bypassWrap.getAttribute('data-nonce') || '') : '';
                const form = new FormData();
                form.append('action', action);
                form.append('nonce', nonce);
                Object.keys(payload || {}).forEach((k) => form.append(k, payload[k]));

                const res = await fetch(ajaxurl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: form
                });
                return await res.json();
            }

            if (bypassSetBtn) {
                bypassSetBtn.addEventListener('click', async function() {
                    const vendorId = selectedVendorId();
                    if (!(vendorId > 0)) {
                        setBypassMsg('Select a vendor first.', 'error');
                        return;
                    }

                    const until = bypassUntil ? String(bypassUntil.value || '').trim() : '';
                    const reason = bypassReason ? String(bypassReason.value || '').trim() : '';
                    if (!/^\d{4}-\d{2}-\d{2}$/.test(until)) {
                        setBypassMsg('Enter a valid "Until" date (YYYY-MM-DD).', 'error');
                        return;
                    }
                    if (!reason) {
                        setBypassMsg('Reason is required.', 'error');
                        return;
                    }

                    setBypassMsg('Applying bypass…', '');
                    bypassSetBtn.disabled = true;
                    try {
                        const json = await postBypass('vms_tax_bypass_set', {
                            post_id: String(vendorId),
                            until: until,
                            reason: reason
                        });

                        if (!json || !json.success) {
                            const msg = (json && json.data && json.data.message) ? String(json.data.message) : 'Bypass update failed.';
                            setBypassMsg(msg, 'error');
                            return;
                        }

                        updateSelectedOptionBypass(true, until, reason);
                        setBypassMsg('Bypass applied.', '');
                        render();
                    } catch (e) {
                        setBypassMsg('Bypass update failed.', 'error');
                    } finally {
                        bypassSetBtn.disabled = false;
                    }
                });
            }

            if (bypassClearBtn) {
                bypassClearBtn.addEventListener('click', async function() {
                    const vendorId = selectedVendorId();
                    if (!(vendorId > 0)) {
                        setBypassMsg('Select a vendor first.', 'error');
                        return;
                    }

                    setBypassMsg('Clearing bypass…', '');
                    bypassClearBtn.disabled = true;
                    try {
                        const json = await postBypass('vms_tax_bypass_clear', {
                            post_id: String(vendorId)
                        });

                        if (!json || !json.success) {
                            const msg = (json && json.data && json.data.message) ? String(json.data.message) : 'Clear failed.';
                            setBypassMsg(msg, 'error');
                            return;
                        }

                        updateSelectedOptionBypass(false, '', '');
                        if (bypassReason) bypassReason.value = '';
                        setBypassMsg('Bypass cleared.', '');
                        render();
                    } catch (e) {
                        setBypassMsg('Clear failed.', 'error');
                    } finally {
                        bypassClearBtn.disabled = false;
                    }
                });
            }

            bandSel.addEventListener('change', render);
            render();
        })();
    </script>

    <script>
        (function() {
            const venueSel = document.getElementById('vms_venue_id');
            const dateInp = document.getElementById('vms_event_date');
            const autoChk = document.getElementById('vms_auto_comp_venue');
            const hint = document.getElementById('vms-venue-defaults-hint');

	            const fStruct = document.getElementById('vms_comp_structure');
	            const fFlat = document.getElementById('vms_flat_fee_amount');
	            const fSplit = document.getElementById('vms_door_split_percent');
	            const fBonusMode = document.getElementById('vms_attendance_bonus_mode');
	            const fBonusStart = document.getElementById('vms_attendance_bonus_start_count');
	            const fBonusStepSize = document.getElementById('vms_attendance_bonus_step_size');
	            const fBonusStepBonus = document.getElementById('vms_attendance_bonus_step_bonus');
	            const fBonusPerTicket = document.getElementById('vms_attendance_bonus_per_ticket_rate');
	            const fBonusMax = document.getElementById('vms_attendance_bonus_max_bonus');
	            const selInp = document.getElementById('vms_comp_selected_option');
	            const pkgInp = document.getElementById('vms_comp_package_id');
	            const optionsWrap = document.getElementById('vms-comp-options');

	            if (!venueSel || !dateInp || !autoChk || !fStruct) return;

	            let dirty = false;
	            let lastAutoAppliedSig = '';
	            [fStruct, fFlat, fSplit, fBonusMode, fBonusStart, fBonusStepSize, fBonusStepBonus, fBonusPerTicket, fBonusMax].forEach(el => {
	                if (!el) return;
	                el.addEventListener('change', () => dirty = true);
	                el.addEventListener('input', () => dirty = true);
	            });

            function isBlank(val) {
                return (val === null || val === undefined || String(val).trim() === '');
            }

	            function draftHasValues() {
	                const flat = fFlat ? fFlat.value : '';
	                const split = fSplit ? fSplit.value : '';
	                const bonusMode = fBonusMode ? fBonusMode.value : '';
	                const bonusStart = fBonusStart ? fBonusStart.value : '';
	                const stepSize = fBonusStepSize ? fBonusStepSize.value : '';
	                const stepBonus = fBonusStepBonus ? fBonusStepBonus.value : '';
	                const perTicket = fBonusPerTicket ? fBonusPerTicket.value : '';
	                const maxBonus = fBonusMax ? fBonusMax.value : '';
	                return (!isBlank(flat) || !isBlank(split) || !isBlank(bonusMode) || !isBlank(bonusStart) || !isBlank(stepSize) || !isBlank(stepBonus) || !isBlank(perTicket) || !isBlank(maxBonus));
	            }

            function normalizeSigPart(val) {
                if (isBlank(val)) return '';
                const n = Number.parseFloat(String(val).replace(/[^0-9.\-]/g, ''));
                if (!Number.isFinite(n)) return String(val).trim();
                return String(n);
            }

	            function currentDraftSig() {
	                return JSON.stringify({
	                    structure: String(fStruct.value || '').trim(),
	                    flat: normalizeSigPart(fFlat ? fFlat.value : ''),
	                    split: normalizeSigPart(fSplit ? fSplit.value : ''),
	                    attendance_bonus_mode: String(fBonusMode ? (fBonusMode.value || '') : '').trim(),
	                    attendance_bonus_start_count: normalizeSigPart(fBonusStart ? fBonusStart.value : ''),
	                    attendance_bonus_step_size: normalizeSigPart(fBonusStepSize ? fBonusStepSize.value : ''),
	                    attendance_bonus_step_bonus: normalizeSigPart(fBonusStepBonus ? fBonusStepBonus.value : ''),
	                    attendance_bonus_per_ticket_rate: normalizeSigPart(fBonusPerTicket ? fBonusPerTicket.value : ''),
	                    attendance_bonus_max_bonus: normalizeSigPart(fBonusMax ? fBonusMax.value : ''),
	                });
	            }

            function setHint(msg, type) {
                if (!hint) return;
                hint.textContent = msg || '';
                hint.style.color = (type === 'warn') ? '#92400e' : (type === 'ok' ? '#065f46' : '');
            }

            function applyRow(row) {
                if (!row || !row.structure) {
                    setHint('No date defaults found for that day.', 'warn');
                    return;
                }

                const source = String(row.source || 'venue').trim().toLowerCase();
                const selectedOpt = (source === 'holiday') ? 'default:holiday' : 'default:venue';
                const sourceLabel = String(row.label || (source === 'holiday' ? 'Holiday defaults' : 'Venue defaults')).trim();

                if (!autoChk.checked) {
                    setHint(sourceLabel + ' found. Turn on auto-fill to apply automatically.', 'info');
                    return;
                }

                const canOverwriteAuto = (lastAutoAppliedSig !== '' && currentDraftSig() === lastAutoAppliedSig);
                if (dirty || (draftHasValues() && !canOverwriteAuto)) {
                    setHint(sourceLabel + ' found. Auto-fill skipped because Draft Pay already has values.', 'warn');
                    return;
                }

	                fStruct.value = row.structure || 'flat_fee';
	                if (fFlat && typeof row.flat_fee_amount !== 'undefined') fFlat.value = row.flat_fee_amount ?? '';
	                if (fSplit && typeof row.door_split_percent !== 'undefined') fSplit.value = row.door_split_percent ?? '';
	                if (fBonusMode && typeof row.attendance_bonus_mode !== 'undefined') fBonusMode.value = row.attendance_bonus_mode ?? '';
	                if (fBonusStart && typeof row.attendance_bonus_start_count !== 'undefined') fBonusStart.value = row.attendance_bonus_start_count ?? '';
	                if (fBonusStepSize && typeof row.attendance_bonus_step_size !== 'undefined') fBonusStepSize.value = row.attendance_bonus_step_size ?? '';
	                if (fBonusStepBonus && typeof row.attendance_bonus_step_bonus !== 'undefined') fBonusStepBonus.value = row.attendance_bonus_step_bonus ?? '';
	                if (fBonusPerTicket && typeof row.attendance_bonus_per_ticket_rate !== 'undefined') fBonusPerTicket.value = row.attendance_bonus_per_ticket_rate ?? '';
	                if (fBonusMax && typeof row.attendance_bonus_max_bonus !== 'undefined') fBonusMax.value = row.attendance_bonus_max_bonus ?? '';
	                if (pkgInp) pkgInp.value = '';
	                if (selInp) selInp.value = selectedOpt;

                if (optionsWrap) {
                    optionsWrap.querySelectorAll('.vms-comp-opt-tile').forEach((tile) => {
                        const isSel = String(tile.getAttribute('data-opt') || '') === selectedOpt;
                        tile.classList.toggle('is-selected', isSel);
                    });
                }

	                [fStruct, fFlat, fSplit, fBonusMode, fBonusStart, fBonusStepSize, fBonusStepBonus, fBonusPerTicket, fBonusMax].forEach((el) => {
	                    if (!el) return;
	                    el.dispatchEvent(new Event('input', { bubbles: true }));
	                    el.dispatchEvent(new Event('change', { bubbles: true }));
                });

                lastAutoAppliedSig = currentDraftSig();
                dirty = false;
                document.dispatchEvent(new Event('vms_comp_options_updated'));
                setHint(sourceLabel + ' applied for this date. (Override anytime.)', 'ok');
            }

            async function fetchDefaults() {
                const venue_id = venueSel.value || '';
                const event_date = dateInp.value || '';
                if (!venue_id || !event_date) return null;

                const form = new FormData();
                form.append('action', 'vms_get_venue_comp_defaults');
                form.append('venue_id', venue_id);
                form.append('event_date', event_date);

                const resp = await fetch(ajaxurl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: form
                });
                const json = await resp.json();
                if (!json || !json.success) return null;
                return (json.data && json.data.row) ? json.data.row : null;
            }

            async function onVenueOrDateChange() {
                const venue_id = venueSel.value || '';
                const event_date = dateInp.value || '';

                if (!venue_id || !event_date) {
                    setHint('Select a Venue and Event Date to apply date defaults.', '');
                    return;
                }

                const row = await fetchDefaults();
                if (!row || !row.structure) {
                    setHint('No date defaults found for that day.', 'warn');
                    return;
                }

                applyRow(row);
            }

            venueSel.addEventListener('change', onVenueOrDateChange);
            dateInp.addEventListener('change', onVenueOrDateChange);
            autoChk.addEventListener('change', function() {
                if (autoChk.checked) dirty = false;
                onVenueOrDateChange();
            });

            if (selInp && String(selInp.value || '').startsWith('default:')) {
                lastAutoAppliedSig = currentDraftSig();
            }
            setHint('Select a Venue and Event Date to apply date defaults.', '');
        })();
    </script>

    <?php
        // Scroll helper (optional)
        if ($scroll_to) {
            delete_post_meta($post->ID, '_vms_admin_scroll_to');
        }
    } finally {
            if (function_exists('vms_event_plan_perf_memory_checkpoint')) {
                vms_event_plan_perf_memory_checkpoint((int) $post->ID, 'details_meta_box_after', array(
                    'section' => 'meta_box_render',
                    'capture_dependency_snapshot' => 1,
                ), 'details_meta_box');
            }
            if (function_exists('vms_event_plan_perf_span_finish')) {
                vms_event_plan_perf_span_finish('event_plan_details_meta_box_render', (int) $post->ID, $render_trace, array('section' => 'meta_box_render'));
            }
        }
    }

    /**
     * Save Event Plan meta fields + handle actions
     */
    public function save_event_plan_meta(int $post_id, WP_Post $post): void
    {
	        $nonce = (isset($_POST['vms_event_plan_details_nonce']) && !is_array($_POST['vms_event_plan_details_nonce']))
	            ? sanitize_text_field(wp_unslash((string) $_POST['vms_event_plan_details_nonce']))
	            : '';
	        if ($nonce === '' || !wp_verify_nonce($nonce, 'vms_save_event_plan_details')) {
	            return;
	        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        if (isset($_POST['post_ID']) && absint($_POST['post_ID']) > 0 && absint($_POST['post_ID']) !== $post_id) {
            return;
        }

        $original_status = isset($_POST['original_post_status']) ? sanitize_key((string) wp_unslash($_POST['original_post_status'])) : sanitize_key((string) $post->post_status);
        $actor_user_id = function_exists('vms_event_plan_capture_actor_user_id')
            ? vms_event_plan_capture_actor_user_id($post_id, (int) get_current_user_id(), 'event_plan_editor_save')
            : (int) get_current_user_id();
        $save_trace = function_exists('vms_event_plan_perf_span_start')
            ? vms_event_plan_perf_span_start(
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

        $request = (array) $_POST;
        $reopen_section_after_save = isset($request['vms_reopen_section_after_save'])
            ? vms_event_plan_normalize_reopen_section((string) wp_unslash($request['vms_reopen_section_after_save']))
            : '';
        if ($reopen_section_after_save !== '') {
            vms_event_plan_set_runtime_reopen_section_target($post_id, $reopen_section_after_save);
        }
        $detached_editor_action = isset($request['vms_event_plan_action'])
            ? sanitize_key((string) wp_unslash($request['vms_event_plan_action']))
            : '';
        if ($detached_editor_action === 'resync_to_calendar') {
            if (function_exists('vms_add_admin_notice')) {
                vms_add_admin_notice(__('Re-sync to Calendar now uses a dedicated saved-state action. Reload the Event Plan and use the Advanced Controls Re-sync button.', 'backstage-venue-manager'), 'warning');
            }
            return;
        }
        $ticket_ui_override_save_intent = isset($request['vms_ticket_ui_overrides_save_intent'])
            ? sanitize_text_field((string) wp_unslash($request['vms_ticket_ui_overrides_save_intent']))
            : '';
        if ($ticket_ui_override_save_intent === '1') {
            $ticket_ui_override_result = $this->save_event_plan_ticket_ui_overrides($post_id, $request);
            if (function_exists('vms_add_admin_notice')) {
                vms_add_admin_notice(
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

	        $primary_vendor_submission = function_exists('vms_event_plan_resolve_primary_vendor_submission')
	            ? vms_event_plan_resolve_primary_vendor_submission($post_id, $request)
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
	            if ($effective_band_id > 0 && function_exists('vms_event_plan_vendor_exists') && vms_event_plan_vendor_exists((int) $effective_band_id)) {
	                if (function_exists('vms_event_plan_clear_integrity_flags')) {
	                    vms_event_plan_clear_integrity_flags($post_id);
	                }
	            }
	        }

        // Staffing payload capture (structured role-slot model; legacy compatibility preserved in helper).
        $staffing_present = isset($_POST['vms_staff_assignments_present']) || isset($_POST['vms_staffing_roles_present']);
        $staffing_lazy_unloaded = !empty($_POST['vms_staffing_lazy_unloaded']);
        $staffing_raw_assignments = (isset($_POST['vms_staff_assignments']) && is_array($_POST['vms_staff_assignments'])) ? (array) $_POST['vms_staff_assignments'] : array();
        $staffing_headcounts = (isset($_POST['vms_staff_role_headcount']) && is_array($_POST['vms_staff_role_headcount'])) ? (array) $_POST['vms_staff_role_headcount'] : array();
        $staffing_activation_thresholds_raw = (isset($_POST['vms_staff_role_activation_threshold']) && is_array($_POST['vms_staff_role_activation_threshold'])) ? (array) $_POST['vms_staff_role_activation_threshold'] : array();
        $staffing_time_modes = (isset($_POST['vms_staff_role_time_mode']) && is_array($_POST['vms_staff_role_time_mode'])) ? (array) $_POST['vms_staff_role_time_mode'] : array();
        $staffing_shift_starts = (isset($_POST['vms_staff_role_shift_start']) && is_array($_POST['vms_staff_role_shift_start'])) ? (array) $_POST['vms_staff_role_shift_start'] : array();
        $staffing_shift_ends = (isset($_POST['vms_staff_role_shift_end']) && is_array($_POST['vms_staff_role_shift_end'])) ? (array) $_POST['vms_staff_role_shift_end'] : array();
        $staffing_start_anchor_keys = (isset($_POST['vms_staff_role_start_anchor']) && is_array($_POST['vms_staff_role_start_anchor'])) ? (array) $_POST['vms_staff_role_start_anchor'] : array();
        $staffing_start_offsets = (isset($_POST['vms_staff_role_start_offset']) && is_array($_POST['vms_staff_role_start_offset'])) ? (array) $_POST['vms_staff_role_start_offset'] : array();
        $staffing_end_anchor_keys = (isset($_POST['vms_staff_role_end_anchor']) && is_array($_POST['vms_staff_role_end_anchor'])) ? (array) $_POST['vms_staff_role_end_anchor'] : array();
        $staffing_end_offsets = (isset($_POST['vms_staff_role_end_offset']) && is_array($_POST['vms_staff_role_end_offset'])) ? (array) $_POST['vms_staff_role_end_offset'] : array();
        $staffing_duration_minutes = (isset($_POST['vms_staff_role_duration_minutes']) && is_array($_POST['vms_staff_role_duration_minutes'])) ? (array) $_POST['vms_staff_role_duration_minutes'] : array();
        $staffing_activation_thresholds_clean = array();
        $staffing_absolute_time_warning_roles = array();
        $staffing_required_now_gap_roles = array();
        $staffing_role_assignment_warnings = array();
        $staffing_role_assignment_blocked = array();
        if (!$staffing_present && $staffing_lazy_unloaded) {
            if (function_exists('vms_event_plan_perf_log')) {
                vms_event_plan_perf_log('event_plan_staffing_save', $post_id, array(
                    'phase' => 'skip',
                    'dirty_branch' => 'skip',
                    'skip_reason' => 'no_staffing_change',
                    'lazy_unloaded' => 1,
                    'section' => 'staffing_save',
                ));
            }
            if (function_exists('vms_event_plan_save_profiler_note_heavy_action')) {
                vms_event_plan_save_profiler_note_heavy_action(
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

            $staff_headcount_context = function_exists('vms_staffing_get_event_plan_headcount_context')
                ? (array) vms_staffing_get_event_plan_headcount_context((int) $post_id)
                : array('wired' => false, 'headcount' => 0);
            $staff_current_headcount = max(0, (int) ($staff_headcount_context['headcount'] ?? 0));
            $staff_headcount_wired = !empty($staff_headcount_context['wired']);
            $existing_assigned_staff_by_role = function_exists('vms_staffing_get_event_assigned_staff_map')
                ? (array) vms_staffing_get_event_assigned_staff_map((int) $post_id)
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
                    $candidate_status = function_exists('vms_staffing_staff_candidate_status_for_role')
                        ? (array) vms_staffing_staff_candidate_status_for_role((int) $assigned_staff_id, (int) $staff_role_id)
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
        $staffing_template_apply_now = !empty($_POST['vms_staffing_template_apply']);
        $staffing_template_selected_id = isset($_POST['vms_staffing_template_id']) ? absint($_POST['vms_staffing_template_id']) : 0;
        $staffing_template_apply_mode = isset($_POST['vms_staffing_template_mode']) ? sanitize_key((string) wp_unslash($_POST['vms_staffing_template_mode'])) : 'merge_missing';
        if (!in_array($staffing_template_apply_mode, array('merge_missing', 'replace_all'), true)) {
            $staffing_template_apply_mode = 'merge_missing';
        }

        // Core basics: only write these when the field was explicitly posted.
        $event_date_posted = array_key_exists('vms_event_date', $request);
        $start_time_posted = array_key_exists('vms_start_time', $request);
        $end_time_posted = array_key_exists('vms_end_time', $request);
        $venue_id_posted = array_key_exists('vms_venue_id', $request);

        $event_date = $event_date_posted
            ? sanitize_text_field(wp_unslash((string) $request['vms_event_date']))
            : (string) get_post_meta($post_id, '_vms_event_date', true);

        $start_time = $start_time_posted
            ? sanitize_text_field(wp_unslash((string) $request['vms_start_time']))
            : (string) get_post_meta($post_id, '_vms_start_time', true);

        $end_time = $end_time_posted
            ? sanitize_text_field(wp_unslash((string) $request['vms_end_time']))
            : (string) get_post_meta($post_id, '_vms_end_time', true);

        $venue_id = $venue_id_posted
            ? absint($request['vms_venue_id'])
            : (int) get_post_meta($post_id, '_vms_venue_id', true);

        
	        // Ticketing enabled override (on|off|inherit)
	        if (array_key_exists('vms_ticketing_enabled_override', (array) $_POST)) {
	            $ov = sanitize_text_field((string) $_POST['vms_ticketing_enabled_override']);
	            $ticketing_override_audit_pushed = false;
	            if (function_exists('vms_ticket_mutation_audit_push_context')) {
	                vms_ticket_mutation_audit_push_context(array(
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
	            if ($ticketing_override_audit_pushed && function_exists('vms_ticket_mutation_audit_pop_context')) {
	                vms_ticket_mutation_audit_pop_context();
	            }
	        }

        // Ticketing v2: GA ticket image policy (event_plan|custom|none) + optional custom image attachment ID.
        if (array_key_exists('vms_ticketing_ga_image_mode', (array) $_POST)) {
            $mode = sanitize_key((string) $_POST['vms_ticketing_ga_image_mode']);
            if (!in_array($mode, array('event_plan', 'custom', 'none'), true)) {
                $mode = 'event_plan';
            }
            update_post_meta($post_id, '_vms_ticketing_ga_image_mode', $mode);
        }
        if (array_key_exists('vms_ticketing_ga_image_id', (array) $_POST)) {
            $img_id = absint($_POST['vms_ticketing_ga_image_id']);
            if ($img_id > 0) {
                update_post_meta($post_id, '_vms_ticketing_ga_image_id', $img_id);
            } else {
                delete_post_meta($post_id, '_vms_ticketing_ga_image_id');
            }
        }

	        // Draft pay
	        $comp_structure = isset($_POST['vms_comp_structure']) ? sanitize_key((string) $_POST['vms_comp_structure']) : 'flat_fee';
	        if (!in_array($comp_structure, array('flat_fee', 'door_split', 'flat_fee_door_split', 'attendance_bonus'), true)) {
	            $comp_structure = 'flat_fee';
	        }

                // Preserve requested action for enforcement messaging (may be cleared later if blocked).
                $requested_action_raw = isset($_POST['vms_event_plan_action']) ? sanitize_text_field((string) $_POST['vms_event_plan_action']) : '';
                if ($requested_action_raw === 'lock_draft_pay') {
                    $saved_basics_ready = !empty($lock_pay_saved_basics_before_save['ok']);
                    $request_basics_ready = !empty($lock_pay_request_basics['ok']);

                    if (!$saved_basics_ready || !$request_basics_ready) {
                        if (function_exists('vms_add_admin_notice')) {
                            $msg = $this->get_lock_pay_basics_notice_copy();
                            if (!$saved_basics_ready && $request_basics_ready) {
                                $msg .= ' ' . __('Your details were saved. Review them, then click Lock Draft Pay again.', 'backstage-venue-manager');
                            }
                            vms_add_admin_notice($msg, 'error');
                        }

                        update_post_meta($post_id, '_vms_admin_scroll_to', 'vms_event_date');
                        $_POST['vms_event_plan_action'] = '';
                        $requested_action_raw = '';
                    }
                }

                // Number inputs are text fields (no spinner arrows). Normalize user input like "$1,200.00".
                $flat_fee_amount_raw = isset($_POST['vms_flat_fee_amount']) ? (string) $_POST['vms_flat_fee_amount'] : '';
                $flat_fee_amount_raw = trim($flat_fee_amount_raw);
                $flat_fee_amount_raw = preg_replace('/[^0-9.\-]/', '', $flat_fee_amount_raw);
                $flat_fee_amount = ($flat_fee_amount_raw === '' ? '' : (float) $flat_fee_amount_raw);
                if ($flat_fee_amount !== '' && $flat_fee_amount < 0) $flat_fee_amount = 0;

                $door_split_percent_raw = isset($_POST['vms_door_split_percent']) ? (string) $_POST['vms_door_split_percent'] : '';
                $door_split_percent_raw = trim($door_split_percent_raw);
                $door_split_percent_raw = preg_replace('/[^0-9.\-]/', '', $door_split_percent_raw);
	                $door_split_percent = ($door_split_percent_raw === '' ? '' : (float) $door_split_percent_raw);
	                if ($door_split_percent !== '' ) {
	                    if ($door_split_percent < 0) $door_split_percent = 0;
	                    if ($door_split_percent > 100) $door_split_percent = 100;
	                }

	                $attendance_bonus_mode = isset($_POST['vms_attendance_bonus_mode']) ? sanitize_key((string) $_POST['vms_attendance_bonus_mode']) : '';
	                if (!in_array($attendance_bonus_mode, array('step', 'continuous'), true)) {
	                    $attendance_bonus_mode = '';
	                }

	                $attendance_bonus_start_count_raw = isset($_POST['vms_attendance_bonus_start_count']) ? (string) $_POST['vms_attendance_bonus_start_count'] : '';
	                $attendance_bonus_start_count_raw = trim($attendance_bonus_start_count_raw);
	                $attendance_bonus_start_count_raw = preg_replace('/[^0-9.\-]/', '', $attendance_bonus_start_count_raw);
	                $attendance_bonus_start_count = '';
	                if ($attendance_bonus_start_count_raw !== '' && is_numeric($attendance_bonus_start_count_raw)) {
	                    $attendance_bonus_start_count = max(0, (int) floor((float) $attendance_bonus_start_count_raw));
	                }

	                $attendance_bonus_step_size_raw = isset($_POST['vms_attendance_bonus_step_size']) ? (string) $_POST['vms_attendance_bonus_step_size'] : '';
	                $attendance_bonus_step_size_raw = trim($attendance_bonus_step_size_raw);
	                $attendance_bonus_step_size_raw = preg_replace('/[^0-9.\-]/', '', $attendance_bonus_step_size_raw);
	                $attendance_bonus_step_size = '';
	                if ($attendance_bonus_step_size_raw !== '' && is_numeric($attendance_bonus_step_size_raw)) {
	                    $parsed_step_size = (int) floor((float) $attendance_bonus_step_size_raw);
	                    if ($parsed_step_size >= 1) {
	                        $attendance_bonus_step_size = $parsed_step_size;
	                    }
	                }

	                $attendance_bonus_step_bonus_raw = isset($_POST['vms_attendance_bonus_step_bonus']) ? (string) $_POST['vms_attendance_bonus_step_bonus'] : '';
	                $attendance_bonus_step_bonus_raw = trim($attendance_bonus_step_bonus_raw);
	                $attendance_bonus_step_bonus_raw = preg_replace('/[^0-9.\-]/', '', $attendance_bonus_step_bonus_raw);
	                $attendance_bonus_step_bonus = ($attendance_bonus_step_bonus_raw === '' ? '' : (float) $attendance_bonus_step_bonus_raw);
	                if ($attendance_bonus_step_bonus !== '' && $attendance_bonus_step_bonus < 0) $attendance_bonus_step_bonus = 0;

	                $attendance_bonus_per_ticket_rate_raw = isset($_POST['vms_attendance_bonus_per_ticket_rate']) ? (string) $_POST['vms_attendance_bonus_per_ticket_rate'] : '';
	                $attendance_bonus_per_ticket_rate_raw = trim($attendance_bonus_per_ticket_rate_raw);
	                $attendance_bonus_per_ticket_rate_raw = preg_replace('/[^0-9.\-]/', '', $attendance_bonus_per_ticket_rate_raw);
	                $attendance_bonus_per_ticket_rate = ($attendance_bonus_per_ticket_rate_raw === '' ? '' : (float) $attendance_bonus_per_ticket_rate_raw);
	                if ($attendance_bonus_per_ticket_rate !== '' && $attendance_bonus_per_ticket_rate < 0) $attendance_bonus_per_ticket_rate = 0;

	                $attendance_bonus_max_bonus_raw = isset($_POST['vms_attendance_bonus_max_bonus']) ? (string) $_POST['vms_attendance_bonus_max_bonus'] : '';
	                $attendance_bonus_max_bonus_raw = trim($attendance_bonus_max_bonus_raw);
	                $attendance_bonus_max_bonus_raw = preg_replace('/[^0-9.\-]/', '', $attendance_bonus_max_bonus_raw);
	                $attendance_bonus_max_bonus = ($attendance_bonus_max_bonus_raw === '' ? '' : (float) $attendance_bonus_max_bonus_raw);
	                if ($attendance_bonus_max_bonus !== '' && $attendance_bonus_max_bonus < 0) $attendance_bonus_max_bonus = 0;

	                $commission_percent_raw = isset($_POST['vms_commission_percent']) ? (string) $_POST['vms_commission_percent'] : '';
	                $commission_percent_raw = trim($commission_percent_raw);
	                $commission_percent_raw = preg_replace('/[^0-9.\-]/', '', $commission_percent_raw);
	                $commission_percent = ($commission_percent_raw === '' ? '' : (float) $commission_percent_raw);
	                if ($commission_percent !== '' && $commission_percent < 0) $commission_percent = 0;
                $commission_mode = isset($_POST['vms_commission_mode']) ? sanitize_key((string) $_POST['vms_commission_mode']) : 'artist_fee';
                if (!in_array($commission_mode, array('artist_fee', 'gross'), true)) $commission_mode = 'artist_fee';

                $deposit_amount_raw = isset($_POST['vms_deposit_amount']) ? (string) wp_unslash($_POST['vms_deposit_amount']) : '';
                $deposit_amount_raw = trim($deposit_amount_raw);
                $deposit_amount_raw = preg_replace('/[^0-9.\-]/', '', $deposit_amount_raw);
                $deposit_amount = ($deposit_amount_raw === '' ? '' : (float) $deposit_amount_raw);
                if ($deposit_amount !== '' && $deposit_amount < 0) $deposit_amount = 0;

                $deposit_status = isset($_POST['vms_deposit_status']) ? sanitize_key((string) wp_unslash($_POST['vms_deposit_status'])) : 'not_required';
                $deposit_status = function_exists('vms_normalize_comp_deposit_status') ? vms_normalize_comp_deposit_status($deposit_status) : $deposit_status;
                $deposit_treatment = isset($_POST['vms_deposit_treatment']) ? sanitize_key((string) wp_unslash($_POST['vms_deposit_treatment'])) : 'creditable';
                $deposit_treatment = function_exists('vms_normalize_comp_deposit_treatment') ? vms_normalize_comp_deposit_treatment($deposit_treatment) : $deposit_treatment;
                $deposit_due_date = isset($_POST['vms_deposit_due_date']) ? sanitize_text_field(wp_unslash((string) $_POST['vms_deposit_due_date'])) : '';
                $deposit_due_date = function_exists('vms_normalize_comp_deposit_date') ? vms_normalize_comp_deposit_date($deposit_due_date) : $deposit_due_date;
                $deposit_paid_date = isset($_POST['vms_deposit_paid_date']) ? sanitize_text_field(wp_unslash((string) $_POST['vms_deposit_paid_date'])) : '';
                $deposit_paid_date = function_exists('vms_normalize_comp_deposit_date') ? vms_normalize_comp_deposit_date($deposit_paid_date) : $deposit_paid_date;
                $deposit_notes = isset($_POST['vms_deposit_notes']) ? sanitize_textarea_field(wp_unslash((string) $_POST['vms_deposit_notes'])) : '';
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

                $final_payment_timing = isset($_POST['vms_final_payment_timing']) ? sanitize_key((string) wp_unslash($_POST['vms_final_payment_timing'])) : 'not_set';
                $final_payment_timing = function_exists('vms_normalize_comp_final_payment_timing') ? vms_normalize_comp_final_payment_timing($final_payment_timing) : $final_payment_timing;
                $final_payment_days_after = isset($_POST['vms_final_payment_days_after']) ? sanitize_text_field(wp_unslash((string) $_POST['vms_final_payment_days_after'])) : '';
                $final_payment_days_after = function_exists('vms_normalize_comp_final_payment_days_after') ? vms_normalize_comp_final_payment_days_after($final_payment_days_after) : $final_payment_days_after;
                $final_payment_date = isset($_POST['vms_final_payment_date']) ? sanitize_text_field(wp_unslash((string) $_POST['vms_final_payment_date'])) : '';
                $final_payment_date = function_exists('vms_normalize_comp_final_payment_date') ? vms_normalize_comp_final_payment_date($final_payment_date) : $final_payment_date;
                $final_payment_custom_text = isset($_POST['vms_final_payment_custom_text']) ? sanitize_text_field(wp_unslash((string) $_POST['vms_final_payment_custom_text'])) : '';
                $final_payment_method = isset($_POST['vms_final_payment_method']) ? sanitize_key((string) wp_unslash($_POST['vms_final_payment_method'])) : 'not_set';
                $final_payment_method = function_exists('vms_normalize_comp_final_payment_method') ? vms_normalize_comp_final_payment_method($final_payment_method) : $final_payment_method;
                $final_payment_method_other = isset($_POST['vms_final_payment_method_other']) ? sanitize_text_field(wp_unslash((string) $_POST['vms_final_payment_method_other'])) : '';
                $final_payment_terms_for_save = array(
                    'final_payment_timing' => $final_payment_timing,
                    'final_payment_days_after' => $final_payment_days_after,
                    'final_payment_date' => $final_payment_date,
                    'final_payment_custom_text' => $final_payment_custom_text,
                    'final_payment_method' => $final_payment_method,
                    'final_payment_method_other' => $final_payment_method_other,
                );

                $k_commission_override_none = function_exists('vms_meta_key')
                    ? (vms_meta_key('event_plan', 'commission_override_none') ?: '_vms_commission_override_none')
                    : '_vms_commission_override_none';
                $selected_comp_option_posted = isset($_POST['vms_comp_selected_option'])
                    ? sanitize_text_field(wp_unslash((string) $_POST['vms_comp_selected_option']))
                    : '';

                $attendance_invalid_message = '';
	                if ($comp_structure === 'attendance_bonus' && $attendance_bonus_mode === 'step' && trim($attendance_bonus_step_size_raw) !== '' && $attendance_bonus_step_size === '') {
	                    $attendance_invalid_message = __('Step Size must be at least 1 for Base + Attendance Bonus step mode.', 'backstage-venue-manager');
	                }
	                if ($attendance_invalid_message !== '' && function_exists('vms_add_admin_notice')) {
	                    vms_add_admin_notice($attendance_invalid_message, 'error');
	                    if (function_exists('vms_admin_scroll_to_compensation')) {
	                        vms_admin_scroll_to_compensation($post_id);
	                    }
	                }

                // ---------------------------------
                // Pay override enforcement (bombproof)
                // Rule: If Draft Pay differs from computed default pay for venue/date (including holiday),
                // user must acknowledge before ANY save is allowed.
                // ---------------------------------

                $ack = (isset($_POST['vms_pay_override_ack']) && (string)$_POST['vms_pay_override_ack'] === '1');

                // Single-ack UX: one checkbox can satisfy both pay-diff and low-guarantee gates.
                $low_guarantee_ack = $ack || (isset($_POST['vms_low_guarantee_ack']) && (string)$_POST['vms_low_guarantee_ack'] === '1');

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
	                if (function_exists('vms_get_event_plan_comp_options')) {
	                    $venue_for_opts = isset($_POST['vms_venue_id']) ? absint($_POST['vms_venue_id']) : 0;
	                    $date_for_opts  = isset($_POST['vms_event_date']) ? sanitize_text_field(wp_unslash($_POST['vms_event_date'])) : '';
	                    $band_for_opts  = $effective_band_id;
	                    $opts = (array) vms_get_event_plan_comp_options($venue_for_opts, $date_for_opts, $band_for_opts);
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
                if (function_exists('vms_get_event_plan_effective_comp_default')) {
                    $resolved_default = (array) vms_get_event_plan_effective_comp_default((int) $venue_id, (string) $event_date);
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

                    $requested_action = isset($_POST['vms_event_plan_action']) ? sanitize_text_field((string) $_POST['vms_event_plan_action']) : '';
                    $needs_ack_to_proceed = in_array($requested_action, array('mark_ready', 'publish_now', 'lock_draft_pay'), true);

if (function_exists('vms_add_admin_notice')) {
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
                            vms_add_admin_notice($message, 'error');
                        } else {
                            $message = sprintf(
                                /* translators: %s: default pay configuration label. */
                                __('Draft Pay differs from %s. You can keep saving Draft changes, but you must acknowledge before Mark Ready, Publish, or Lock Draft Pay.', 'backstage-venue-manager'),
                                $label
                            );
                            vms_add_admin_notice($message, 'warning');
                        }
                    }

                    if (function_exists('vms_admin_scroll_to_compensation')) {
                        vms_admin_scroll_to_compensation($post_id);
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
                    }

                }


                // ---------------------------------
                // Low-guarantee structure acknowledgment (bombproof)
                // Rule: If Draft Pay has a lower guaranteed payout than the highest guaranteed option available
                // from the option tiles, user must acknowledge before Mark Ready, Publish, or Lock Draft Pay.
                // ---------------------------------

                $k_low_ack = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'low_guarantee_ack') : '_vms_low_guarantee_ack';
                $k_low_ack_ts = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'low_guarantee_ack_ts') : '_vms_low_guarantee_ack_ts';
                $k_low_ack_user = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'low_guarantee_ack_user_id') : '_vms_low_guarantee_ack_user_id';
                $k_low_ack_snapshot = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'low_guarantee_ack_snapshot') : '_vms_low_guarantee_ack_snapshot';

                $needs_low_ack_to_proceed = in_array($requested_action_raw, array('mark_ready', 'publish_now', 'lock_draft_pay'), true);

                if ($requires_low_guarantee_ack && !$low_guarantee_ack) {

                    if (function_exists('vms_add_admin_notice')) {
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
                            vms_add_admin_notice($message, 'error');
                        } else {
                            $message = sprintf(
                                /* translators: 1: selected guarantee amount, 2: highest available guarantee amount. */
                                __('Draft Pay has a lower guaranteed payout (%1$s) than the highest guaranteed option (%2$s). You can keep saving Draft changes, but you must acknowledge before Mark Ready, Publish, or Lock Draft Pay.', 'backstage-venue-manager'),
                                $sel,
                                $max
                            );
                            vms_add_admin_notice($message, 'warning');
                        }
                    }

                    if (function_exists('vms_admin_scroll_to_compensation')) {
                        vms_admin_scroll_to_compensation($post_id);
                    }

                    // Clear any prior acknowledgment so it cannot linger incorrectly
                    delete_post_meta($post_id, $k_low_ack);
                    delete_post_meta($post_id, $k_low_ack_ts);
                    delete_post_meta($post_id, $k_low_ack_user);
                    delete_post_meta($post_id, $k_low_ack_snapshot);

                    // Block status actions until acknowledged (do not block saving the edits).
                    if ($needs_low_ack_to_proceed) {
                        $_POST['vms_event_plan_action'] = '';
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
                    if (array_key_exists($lineup_request_key, $_POST)) {
                        $lineup_request_present = true;
                        break;
                    }
                }

                $posted_lineup_rows = (isset($_POST['vms_lineup_entries']) && is_array($_POST['vms_lineup_entries']))
                    ? (array) $_POST['vms_lineup_entries']
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

	                    if (function_exists('vms_normalize_event_plan_lineup_entries')) {
	                        $lineup_key = function_exists('vms_lineup_schedule_meta_key')
	                            ? vms_lineup_schedule_meta_key('lineup_entries_v1', '_vms_lineup_entries_v1')
	                            : '_vms_lineup_entries_v1';
	                        $lineup_band_key = function_exists('vms_lineup_schedule_meta_key')
	                            ? vms_lineup_schedule_meta_key('band_vendor_id', '_vms_band_vendor_id')
	                            : '_vms_band_vendor_id';
	                        $lineup_index_key = function_exists('vms_lineup_schedule_meta_key')
	                            ? vms_lineup_schedule_meta_key('lineup_entry_vendor_id', '_vms_lineup_entry_vendor_id')
	                            : '_vms_lineup_entry_vendor_id';

	                        $current_lineup = get_post_meta($post_id, $lineup_key, true);
	                        $current_lineup = is_array($current_lineup) ? array_values($current_lineup) : array();
	                        $next_lineup = array_values(vms_normalize_event_plan_lineup_entries($posted_lineup_rows, $lineup_context));

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

	                $lineup_save_trace = function_exists('vms_event_plan_perf_span_start')
	                    ? vms_event_plan_perf_span_start('event_plan_lineup_save', $post_id, array('section' => 'lineup_save'))
	                    : '';
	                if (!$lineup_save_needed) {
	                    if (function_exists('vms_event_plan_save_profiler_note_heavy_action')) {
	                        vms_event_plan_save_profiler_note_heavy_action('lineup_save', 'skipped', $lineup_skip_reason);
	                    }
	                } elseif (function_exists('vms_save_event_plan_lineup_entries')) {
	                    if (function_exists('vms_event_plan_save_profiler_note_heavy_action')) {
	                        vms_event_plan_save_profiler_note_heavy_action('lineup_save', 'triggered', 'lineup_changed');
	                    }
	                    vms_save_event_plan_lineup_entries($post_id, $posted_lineup_rows, $lineup_context);
	                }
	                if (function_exists('vms_event_plan_perf_span_finish')) {
	                    vms_event_plan_perf_span_finish('event_plan_lineup_save', $post_id, $lineup_save_trace, array(
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
                $k_secondary_ids     = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'secondary_vendor_ids') : '_vms_secondary_vendor_ids';
                $k_secondary_idx     = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'secondary_vendor_id') : '_vms_secondary_vendor_id';
                $k_secondary_type    = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'secondary_vendor_type') : '_vms_secondary_vendor_type';
                $k_secondary_unq     = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'secondary_vendor_unqualified') : '_vms_secondary_vendor_unqualified';
                $k_secondary_unq_ids = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'secondary_vendor_unqualified_ids') : '_vms_secondary_vendor_unqualified_ids';
                $k_band_vendor_id    = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'band_vendor_id') ?: '_vms_band_vendor_id') : '_vms_band_vendor_id';
                $k_tec_event_id      = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'tec_event_id') ?: '_vms_tec_event_id') : '_vms_tec_event_id';
	                $secondary_vendor_module_detached = !empty($_POST['vms_secondary_vendors_module_detached']);
	                $secondary_vendor_request = $secondary_vendor_module_detached ? array() : $request;
	                $secondary_vendor_submission = function_exists('vms_event_plan_resolve_secondary_vendor_submission')
	                    ? vms_event_plan_resolve_secondary_vendor_submission($post_id, $secondary_vendor_request)
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
	                $secondary_vendor_lazy_unloaded = !empty($_POST['vms_secondary_vendors_lazy_unloaded']);

                if (!$secondary_vendor_present && $secondary_vendor_lazy_unloaded) {
                    if (function_exists('vms_event_plan_perf_log')) {
                        vms_event_plan_perf_log('event_plan_secondary_vendor_rebuild', $post_id, array(
                            'phase' => 'skip',
                            'skip_reason' => 'no_vendor_change',
                            'dirty_branch' => 'skip',
                            'dirty_fields' => array(),
                            'repair_reasons' => array(),
                            'lazy_unloaded' => 1,
                            'section' => 'secondary_vendor_rebuild',
                        ));
                        vms_event_plan_perf_log('event_plan_calendar_vendor_maintenance', $post_id, array(
                            'phase' => 'skip',
                            'dirty_branch' => 'skip',
                            'skip_reason' => 'no_vendor_change',
                            'lazy_unloaded' => 1,
                        ));
                    }
                    if (function_exists('vms_event_plan_save_profiler_note_heavy_action')) {
                        vms_event_plan_save_profiler_note_heavy_action('secondary_vendor_rebuild', 'skipped', 'no_vendor_change');
                    }
                } else {

	                $current_band_id = (int) get_post_meta($post_id, $k_band_vendor_id, true);
	                $proposed_assignments = is_array($secondary_vendor_submission['secondary_vendor_assignments'] ?? null)
	                    ? (array) $secondary_vendor_submission['secondary_vendor_assignments']
	                    : array();
	                $proposed_secondary_type = function_exists('vms_event_plan_legacy_secondary_vendor_type_from_assignments')
	                    ? (string) vms_event_plan_legacy_secondary_vendor_type_from_assignments($proposed_assignments)
	                    : sanitize_key((string) ($secondary_vendor_submission['type_slug'] ?? ''));
	                $proposed_secondary_ids = function_exists('vms_event_plan_get_secondary_vendor_flat_ids_from_assignments')
	                    ? array_values(array_unique(array_filter(array_map('absint', (array) vms_event_plan_get_secondary_vendor_flat_ids_from_assignments($proposed_assignments, $current_band_id)))))
	                    : array_values(array_unique(array_filter(array_map('absint', (array) ($secondary_vendor_submission['secondary_ids'] ?? array())), static function ($vendor_id) {
	                        return $vendor_id > 0;
	                    })));

                $linked_tec_event_id = (int) get_post_meta($post_id, $k_tec_event_id, true);
	                $current_vendor_state = isset($secondary_vendor_submission['current_state']) && is_array($secondary_vendor_submission['current_state'])
	                    ? $secondary_vendor_submission['current_state']
	                    : (function_exists('vms_event_plan_get_secondary_vendor_state')
	                        ? vms_event_plan_get_secondary_vendor_state($post_id)
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

                $vendor_dirty_fields = function_exists('vms_event_plan_secondary_vendor_state_diff_fields')
                    ? vms_event_plan_secondary_vendor_state_diff_fields(
                        $current_vendor_state,
                        $proposed_vendor_state,
                        array('secondary_vendor_assignments', 'secondary_vendor_type', 'secondary_vendor_ids')
                    )
                    : array();

                $primary_vendor_touched = function_exists('vms_event_plan_save_profiler_meta_key_touched')
                    ? vms_event_plan_save_profiler_meta_key_touched($k_band_vendor_id)
                    : false;
                if ($primary_vendor_touched && !in_array('primary_vendor_id', $vendor_dirty_fields, true)) {
                    $vendor_dirty_fields[] = 'primary_vendor_id';
                }

                $repair_reasons = function_exists('vms_event_plan_secondary_vendor_rebuild_repair_reasons')
                    ? vms_event_plan_secondary_vendor_rebuild_repair_reasons($post_id, $current_vendor_state)
                    : array();

                $maintenance_dirty_fields = $vendor_dirty_fields;
                $linked_tec_target_touched = function_exists('vms_event_plan_save_profiler_meta_key_touched')
                    ? vms_event_plan_save_profiler_meta_key_touched($k_tec_event_id)
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
                    if (function_exists('vms_event_plan_perf_log')) {
                        vms_event_plan_perf_log('event_plan_secondary_vendor_rebuild', $post_id, array(
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
                    if (function_exists('vms_event_plan_save_profiler_note_heavy_action')) {
                        vms_event_plan_save_profiler_note_heavy_action('secondary_vendor_rebuild', 'skipped', 'no_vendor_change');
                    }

                    if ($linked_tec_event_id > 0) {
                        if (function_exists('vms_event_plan_perf_log')) {
                            vms_event_plan_perf_log('event_plan_calendar_vendor_maintenance', $post_id, array(
                                'phase' => !empty($maintenance_dirty_fields) ? 'run' : 'skip',
                                'dirty_branch' => !empty($maintenance_dirty_fields) ? 'queue' : 'skip',
                                'skip_reason' => empty($maintenance_dirty_fields) ? 'no_vendor_change' : '',
                                'dirty_reason' => !empty($maintenance_dirty_fields) ? implode(',', $maintenance_dirty_fields) : '',
                                'linked_tec_event_id' => $linked_tec_event_id,
                            ));
                        }

                        if (!empty($maintenance_dirty_fields) && function_exists('vms_event_plan_schedule_calendar_maintenance')) {
                            vms_event_plan_schedule_calendar_maintenance($post_id, $linked_tec_event_id, 'vendor_category_sync');
                        }
                    }
                } else {
                    $secondary_save_trace = function_exists('vms_event_plan_perf_span_start')
                        ? vms_event_plan_perf_span_start('event_plan_secondary_vendor_rebuild', $post_id, array(
                            'section' => 'secondary_vendor_rebuild',
                            'dirty_branch' => 'run',
                            'dirty_fields' => $vendor_dirty_fields,
                            'repair_reasons' => $repair_reasons,
                        ))
                        : '';

                    if (function_exists('vms_event_plan_save_profiler_note_heavy_action')) {
                        vms_event_plan_save_profiler_note_heavy_action('secondary_vendor_rebuild', 'triggered', $secondary_dirty_reason !== '' ? $secondary_dirty_reason : 'vendor_state_changed');
                    }

                    $write_result = function_exists('vms_event_plan_write_secondary_vendor_assignments')
                        ? vms_event_plan_write_secondary_vendor_assignments($post_id, $proposed_assignments)
                        : new WP_Error('vms_secondary_vendor_write_unavailable', __('Additional Vendor save helper is unavailable.', 'backstage-venue-manager'));

                    $written_secondary_type = $proposed_secondary_type;
                    $written_secondary_ids = $proposed_secondary_ids;
                    $unq_ids = array();

                    if (is_wp_error($write_result)) {
                        if (function_exists('vms_add_admin_notice')) {
                            vms_add_admin_notice((string) $write_result->get_error_message(), 'error');
                        }
                    } else {
                        $written_secondary_type = (string) ($write_result['secondary_vendor_type'] ?? $proposed_secondary_type);
                        $written_secondary_ids = array_values(array_unique(array_filter(array_map('absint', (array) ($write_result['secondary_vendor_ids'] ?? $proposed_secondary_ids)))));
                        $unq_ids = array_values(array_unique(array_filter(array_map('absint', (array) ($write_result['unqualified_ids'] ?? array())), static function ($vendor_id) {
                            return $vendor_id > 0;
                        })));
                    }

                    if (!is_wp_error($write_result)) {
                        if (function_exists('vms_event_plan_update_vendor_category_snapshot')) {
                            vms_event_plan_update_vendor_category_snapshot($post_id);
                        }

                        if ($linked_tec_event_id > 0) {
                            if (function_exists('vms_event_plan_perf_log')) {
                                vms_event_plan_perf_log('event_plan_calendar_vendor_maintenance', $post_id, array(
                                    'phase' => 'run',
                                    'dirty_branch' => 'queue',
                                    'dirty_reason' => $secondary_dirty_reason !== '' ? $secondary_dirty_reason : 'vendor_state_changed',
                                    'linked_tec_event_id' => $linked_tec_event_id,
                                ));
                            }
                            if (function_exists('vms_event_plan_schedule_calendar_maintenance')) {
                                vms_event_plan_schedule_calendar_maintenance($post_id, $linked_tec_event_id, 'vendor_category_sync');
                            }
                        }

                        // Clear integrity flag if it was only about a missing secondary vendor.
                        if (function_exists('vms_event_plan_clear_integrity_flags')) {
                            $k_issue = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'integrity_issue') : '_vms_integrity_issue';
                            $issue_now = (string) get_post_meta($post_id, $k_issue, true);
                            if ($issue_now === 'missing_secondary_vendor') {
                                vms_event_plan_clear_integrity_flags($post_id);
                            }
                        }
                    }

                    if (function_exists('vms_event_plan_perf_span_finish')) {
                        vms_event_plan_perf_span_finish('event_plan_secondary_vendor_rebuild', $post_id, $secondary_save_trace, array(
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
                $k_low_ack = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'low_guarantee_ack') : '_vms_low_guarantee_ack';
                $k_low_ack_ts = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'low_guarantee_ack_ts') : '_vms_low_guarantee_ack_ts';
                $k_low_ack_user = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'low_guarantee_ack_user_id') : '_vms_low_guarantee_ack_user_id';
                $k_low_ack_snapshot = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'low_guarantee_ack_snapshot') : '_vms_low_guarantee_ack_snapshot';

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
                $auto_title      = isset($_POST['vms_auto_title']) ? '1' : '0';
                $auto_comp       = isset($_POST['vms_auto_comp']) ? '1' : '0';
                $auto_comp_venue = isset($_POST['vms_auto_comp_venue']) ? '1' : '0';

                // Package selection (persist so dropdown sticks even without Apply)
                $comp_package_id = isset($_POST['vms_comp_package_id']) ? absint($_POST['vms_comp_package_id']) : 0;

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

                if (function_exists('vms_event_plan_store_checkin_close_meta')) {
                    vms_event_plan_store_checkin_close_meta($post_id);
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
                    && array_key_exists('vms_commission_percent', $_POST)
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

                if (function_exists('vms_event_plan_save_deposit_terms')) {
                    vms_event_plan_save_deposit_terms((int) $post_id, $deposit_terms_for_save);
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

                if (function_exists('vms_event_plan_save_final_payment_terms')) {
                    vms_event_plan_save_final_payment_terms((int) $post_id, $final_payment_terms_for_save);
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

                if (function_exists('vms_event_plan_maybe_cleanup_legacy_ticket_meta_for_plan')) {
                    vms_event_plan_maybe_cleanup_legacy_ticket_meta_for_plan($post_id);
                }

                if ($comp_package_id > 0) update_post_meta($post_id, '_vms_comp_package_id', $comp_package_id);
                else delete_post_meta($post_id, '_vms_comp_package_id');

                // Persist the last clicked compensation tile selection
                if (isset($_POST['vms_comp_selected_option'])) {
                    $sel_opt = sanitize_text_field(wp_unslash((string) $_POST['vms_comp_selected_option']));
                    $sel_opt = preg_replace('/[^a-z0-9:_-]/i', '', (string) $sel_opt);
                    $k_comp_selected_option = function_exists('vms_meta_key')
                        ? (vms_meta_key('event_plan', 'comp_selected_option') ?: '_vms_comp_selected_option')
                        : '_vms_comp_selected_option';
                    if ($sel_opt !== '') update_post_meta($post_id, $k_comp_selected_option, $sel_opt);
                    else delete_post_meta($post_id, $k_comp_selected_option);
                }

                // Structured staffing save (STAFF-01 Phase A) with fallback to legacy map behavior.
                if (!empty($staffing_present)) {
                    $staffing_save_trace = function_exists('vms_event_plan_perf_span_start')
                        ? vms_event_plan_perf_span_start('event_plan_staffing_save', $post_id, array('section' => 'staffing_save'))
                        : '';
                    $staffing_request_state = function_exists('vms_staffing_assess_event_plan_save_request')
                        ? vms_staffing_assess_event_plan_save_request(
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
                    $staffing_dirty_reason = $staffing_has_request_state && function_exists('vms_staffing_plan_save_request_state_dirty_reason')
                        ? vms_staffing_plan_save_request_state_dirty_reason($staffing_request_state)
                        : '';
                    $staffing_should_skip_expensive_work = (
                        $staffing_has_request_state
                        && !$staffing_matrix_dirty
                        && !$staffing_thresholds_dirty
                        && !$staffing_template_apply_requested
                    );

                    if (function_exists('vms_event_plan_perf_log')) {
                        vms_event_plan_perf_log('event_plan_staffing_save', $post_id, array(
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
                    if (function_exists('vms_event_plan_save_profiler_note_heavy_action')) {
                        vms_event_plan_save_profiler_note_heavy_action(
                            'staffing_save',
                            $staffing_should_skip_expensive_work ? 'skipped' : 'triggered',
                            $staffing_should_skip_expensive_work ? 'no_staffing_change' : ($staffing_dirty_reason !== '' ? $staffing_dirty_reason : 'staffing_changed')
                        );
                    }

                    if ($staffing_matrix_dirty) {
                        if (function_exists('vms_staffing_save_event_roles_matrix')) {
                            vms_staffing_save_event_roles_matrix(
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
                        if (function_exists('vms_staffing_set_event_role_activation_thresholds')) {
                            vms_staffing_set_event_role_activation_thresholds((int) $post_id, is_array($staffing_activation_thresholds_clean) ? $staffing_activation_thresholds_clean : array());
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
                    if (!empty($staffing_absolute_time_warning_roles) && function_exists('vms_add_admin_notice')) {
                        vms_add_admin_notice(
                            sprintf(
                                /* translators: %s: absolute staff shift times are missing for. */
                                __('Absolute staff shift times are missing for: %s. When Time mode is Absolute and the role is in use, enter Shift start plus Shift end or Duration.', 'backstage-venue-manager'),
                                implode(', ', array_map('sanitize_text_field', array_unique($staffing_absolute_time_warning_roles)))
                            ),
                            'warning'
                        );
                        update_post_meta($post_id, '_vms_admin_scroll_to', 'vms_staff_assignments_present');
                    }
                    if (!empty($staffing_required_now_gap_roles) && function_exists('vms_add_admin_notice')) {
                        vms_add_admin_notice(
                            sprintf(
                                /* translators: %s: current attendance has activated these staff requirements, but they are still not fully assigned. */
                                __('Current attendance has activated these staff requirements, but they are still not fully assigned: %s.', 'backstage-venue-manager'),
                                implode(', ', array_map('sanitize_text_field', array_unique($staffing_required_now_gap_roles)))
                            ),
                            'warning'
                        );
                        update_post_meta($post_id, '_vms_admin_scroll_to', 'vms_staff_assignments_present');
                    }
                    if (!empty($staffing_role_assignment_warnings) && function_exists('vms_add_admin_notice')) {
                        vms_add_admin_notice(
                            sprintf(
                                /* translators: %s: staff assignment review needed. */
                                __('Staff assignment review needed: %s.', 'backstage-venue-manager'),
                                implode(' | ', array_map('sanitize_text_field', array_unique($staffing_role_assignment_warnings)))
                            ),
                            'warning'
                        );
                    }
                    if (!empty($staffing_role_assignment_blocked) && function_exists('vms_add_admin_notice')) {
                        vms_add_admin_notice(
                            sprintf(
                                /* translators: %s: invalid staff assignments were blocked because those staff are not currently eligible for the selected roles. */
                                __('Invalid staff assignments were blocked because those staff are not currently eligible for the selected roles: %s.', 'backstage-venue-manager'),
                                implode(' | ', array_map('sanitize_text_field', array_unique($staffing_role_assignment_blocked)))
                            ),
                            'error'
                        );
                    }
                    if ($staffing_template_apply_requested && function_exists('vms_staffing_apply_template_to_event')) {
                        $template_apply_result = vms_staffing_apply_template_to_event((int) $post_id, (int) $staffing_template_selected_id, (string) $staffing_template_apply_mode, (int) get_current_user_id());
                        if (!empty($template_apply_result['ok']) && function_exists('vms_add_admin_notice')) {
                            vms_add_admin_notice(
                                sprintf(
                                    /* translators: 1: number 1 used in this message, 2: number 2 used in this message. */
                                    __('Staffing template applied. Seeded %1$d role slots and skipped %2$d existing role slots.', 'backstage-venue-manager'),
                                    (int) ($template_apply_result['seeded'] ?? 0),
                                    (int) ($template_apply_result['skipped'] ?? 0)
                                ),
                                'success'
                            );
                        } elseif (function_exists('vms_add_admin_notice')) {
                            vms_add_admin_notice(
                                sprintf(
                                    /* translators: %s: staffing template apply failed. */
                                    __('Staffing template apply failed: %s.', 'backstage-venue-manager'),
                                    sanitize_text_field((string) ($template_apply_result['error'] ?? 'unknown_error'))
                                ),
                                'error'
                            );
                        }
                    }
                    if (function_exists('vms_event_plan_perf_span_finish')) {
                        vms_event_plan_perf_span_finish('event_plan_staffing_save', $post_id, $staffing_save_trace, array(
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
                    $new_auto_title = function_exists('vms_event_plan_build_auto_title')
                        ? vms_event_plan_build_auto_title((string) $band_name)
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
                            $auto_title_changed_fields = function_exists('vms_event_plan_perf_post_update_changed_fields')
                                ? vms_event_plan_perf_post_update_changed_fields($post_id, $auto_title_postarr)
                                : array('post_title');

                            if (empty($auto_title_changed_fields)) {
                                if (function_exists('vms_event_plan_perf_log')) {
                                    vms_event_plan_perf_log('event_plan_auto_title_sync', $post_id, array(
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

                                vms_event_plan_perf_wp_update_post($auto_title_postarr, 'event_plan_auto_title_sync', $post_id);

                                add_action('save_post_vms_event_plan', array($this, 'save_event_plan_meta'), 10, 2);
                            }
                        }

                        // Track the last auto title so we can detect custom edits later
                        update_post_meta($post_id, '_vms_last_auto_title', $new_auto_title);
                    }
                }

                // Keep existing auto-comp behavior
                if ($auto_comp === '1' && function_exists('vms_maybe_apply_band_comp_defaults_to_plan')) {
                    vms_maybe_apply_band_comp_defaults_to_plan($post_id);
                }

                // Handle actions (optional; normal "Update" saves have no action)
                $action = isset($_POST['vms_event_plan_action']) ? sanitize_text_field($_POST['vms_event_plan_action']) : '';
                $retry_step_key = '';
                if (strpos($action, 'retry_cancellation_step:') === 0) {
                    $retry_step_key = sanitize_key(substr($action, strlen('retry_cancellation_step:')));
                    $action = 'retry_cancellation_step';
                }
                $k_cancel_policy = function_exists('vms_meta_key')
                    ? (vms_meta_key('event_plan', 'cancel_policy') ?: '_vms_cancel_policy')
                    : '_vms_cancel_policy';
                $k_cancel_reason_code = function_exists('vms_meta_key')
                    ? (vms_meta_key('event_plan', 'cancel_reason_code') ?: '_vms_cancel_reason_code')
                    : '_vms_cancel_reason_code';
                $k_cancel_reason_note = function_exists('vms_meta_key')
                    ? (vms_meta_key('event_plan', 'cancel_reason_note') ?: '_vms_cancel_reason_note')
                    : '_vms_cancel_reason_note';
                $k_cancel_vendor_message = function_exists('vms_meta_key')
                    ? (vms_meta_key('event_plan', 'cancel_vendor_message') ?: '_vms_cancel_vendor_message')
                    : '_vms_cancel_vendor_message';
                $k_plan_status = function_exists('vms_meta_key')
                    ? (vms_meta_key('event_plan', 'status') ?: '_vms_event_plan_status')
                    : '_vms_event_plan_status';

                $cancel_policy_post = isset($_POST['vms_cancel_policy']) ? sanitize_key((string) $_POST['vms_cancel_policy']) : '';
                $cancel_reason_code_post = isset($_POST['vms_cancel_reason_code']) ? sanitize_key((string) $_POST['vms_cancel_reason_code']) : '';
                $cancel_reason_note_post = isset($_POST['vms_cancel_reason_note']) ? sanitize_textarea_field((string) wp_unslash($_POST['vms_cancel_reason_note'])) : '';
                $cancel_vendor_message_post = isset($_POST['vms_cancel_vendor_message']) ? sanitize_textarea_field((string) wp_unslash($_POST['vms_cancel_vendor_message'])) : '';
                $cancel_auto_refund_confirmed_post = isset($_POST['vms_cancel_auto_refund_confirmed']) ? sanitize_key((string) $_POST['vms_cancel_auto_refund_confirmed']) : '0';
                $cancel_manual_live_refund_confirm_post = isset($_POST['vms_cancel_manual_live_refund_confirm']) ? sanitize_key((string) $_POST['vms_cancel_manual_live_refund_confirm']) : '0';

                $cancel_policy_options = function_exists('vms_cancellation_policy_options')
                    ? array_keys((array) vms_cancellation_policy_options())
                    : array('status_only');
                if (!in_array($cancel_policy_post, $cancel_policy_options, true)) {
                    $cancel_policy_post = 'status_only';
                }

                $cancel_reason_options = function_exists('vms_cancellation_reason_options')
                    ? array_keys((array) vms_cancellation_reason_options())
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
                $replacement_date_requested = isset($_POST['vms_reschedule_event_date'])
                    ? sanitize_text_field((string) wp_unslash($_POST['vms_reschedule_event_date']))
                    : '';
                $queue_rescheduled_draft_after_cancel = false;

                // BUG-01 hardening: recover legacy Draft+TEC mismatches when WP status is already non-draft.
                if ($action === '' && $new_status === 'draft') {
                    $wp_status_now = sanitize_key((string) get_post_status($post_id));
                    $k_tec = function_exists('vms_meta_key')
                        ? (vms_meta_key('event_plan', 'tec_event_id') ?: '_vms_tec_event_id')
                        : '_vms_tec_event_id';
                    $k_issue = function_exists('vms_meta_key')
                        ? (vms_meta_key('event_plan', 'integrity_issue') ?: '_vms_integrity_issue')
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
                            if (function_exists('vms_add_admin_notice')) {
                                vms_add_admin_notice(__('Detected a legacy Draft status mismatch and auto-repaired Event Plan status.', 'backstage-venue-manager'), 'warning');
                            }
                        }
                    }
                }

                if ($action !== '') {

                    switch ($action) {
                    case 'save_draft':
                        $new_status = 'draft';
                        vms_add_admin_notice(__('Event plan saved as Draft.', 'backstage-venue-manager'), 'success');
                        break;

                    case 'mark_ready':
                        $errors = vms_validate_event_plan($post_id);
                        if (empty($errors)) {
                            vms_maybe_autoset_event_plan_title($post_id);
                            $new_status = 'ready';
                            vms_add_admin_notice(__('Event plan marked Ready.', 'backstage-venue-manager'), 'success');
                        } else {
                            $new_status = 'draft';
                            vms_add_admin_notice(__('Cannot mark Ready:', 'backstage-venue-manager') . ' ' . implode(' ', $errors), 'error');
                        }
                        break;

                    case 'publish_now':
                        if (!in_array($current_status, array('ready', 'published'), true)) {
                            vms_add_admin_notice(__('Event must be Ready before publishing.', 'backstage-venue-manager'), 'error');
                            break;
                        }

                        $errors = vms_validate_event_plan($post_id);
                        if (!empty($errors)) {
                            vms_add_admin_notice(__('Cannot publish:', 'backstage-venue-manager') . ' ' . implode(' ', $errors), 'error');
                            break;
                        }

                        $published = false;
                        $deferred_calendar_publish = function_exists('vms_event_plan_deferred_calendar_publish_enabled')
                            ? vms_event_plan_deferred_calendar_publish_enabled($post_id, 'publish_now')
                            : false;

                        if ($deferred_calendar_publish && function_exists('vms_event_plan_schedule_deferred_calendar_publish')) {
                                $published = vms_event_plan_schedule_deferred_calendar_publish($post_id, 'publish_now');
                                if ($published) {
                                    $new_status = 'published';
                                    vms_add_admin_notice(__('Event Plan marked Published. Calendar sync has been queued so the editor request stays light.', 'backstage-venue-manager'), 'success');
                                    vms_add_admin_notice(__('The public calendar event will stay hidden until the linked TEC event is fully published and clickable. Use Re-sync to Calendar for an immediate manual sync if needed.', 'backstage-venue-manager'), 'info');
                                }
                            } else {
                            $published = vms_publish_event_to_calendar($post_id, $post);
                            if ($published) {
                                $new_status = 'published';
                                vms_add_admin_notice(__('Event published successfully.', 'backstage-venue-manager'), 'success');
                            }
                        }

                        if ($published) {
                            $tec_id_for_ticketing = function_exists('vms_ticketing_b_get_linked_tec_event_id')
                                ? (int) vms_ticketing_b_get_linked_tec_event_id($post_id)
                                : 0;

                            $cfg_v2 = get_post_meta(
                                $post_id,
                                function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'ticketing_config_v2') : '_vms_ticketing_config_v2',
                                true
                            );
                            $mode_v2 = is_array($cfg_v2) ? (string) ($cfg_v2['mode'] ?? '') : '';
                            $auto_money_allowed = function_exists('vms_event_plan_ticketing_auto_money_allowed')
                                ? vms_event_plan_ticketing_auto_money_allowed($post_id, $action, $current_status)
                                : ($action === 'publish_now');

                            if (!$auto_money_allowed) {
                                vms_add_admin_notice(__('Woo product auto-publish skipped. Money-impacting product actions are disabled on Draft/Ready save paths.', 'backstage-venue-manager'), 'warning');
                            } elseif (!($tec_id_for_ticketing > 0 && $mode_v2 !== '' && $mode_v2 !== 'none')) {
                                vms_add_admin_notice(__('Legacy Woo product auto-publish is retired. Use Ticketing Preview → Commit for all ticket/product creation and updates.', 'backstage-venue-manager'), 'warning');
                            }
                        } else {
                            vms_add_admin_notice(__('Failed to queue/publish event to calendar. Please check settings.', 'backstage-venue-manager'), 'error');
                        }
                        break;

                    case 'mark_cancelled':
                        if ($replacement_date_requested !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $replacement_date_requested)) {
                            vms_add_admin_notice(__('Enter a valid replacement date before cancelling with automatic reschedule.', 'backstage-venue-manager'), 'error');
                            break;
                        }

                        $new_status = 'cancelled';
                        if (function_exists('vms_cancellation_create_job')) {
                            $job = (array) vms_cancellation_create_job($post_id, array(
                                'policy' => (string) $cancel_policy_post,
                                'reason_code' => (string) $cancel_reason_code_post,
                                'reason_note' => (string) $cancel_reason_note_post,
                                'vendor_message' => (string) $cancel_vendor_message_post,
                                'auto_refund_confirmed' => ($cancel_auto_refund_confirmed_post === '1'),
                            ));
                            if (!empty($job['ok'])) {
                                $k_cancel_review = function_exists('vms_meta_key')
                                    ? (vms_meta_key('event_plan', 'cancel_requires_operator_review') ?: '_vms_cancel_requires_operator_review')
                                    : '_vms_cancel_requires_operator_review';
                                $k_job_state = function_exists('vms_meta_key')
                                    ? (vms_meta_key('event_plan', 'cancel_job_state') ?: '_vms_cancel_job_state')
                                    : '_vms_cancel_job_state';
                                $job_state = sanitize_key((string) get_post_meta($post_id, $k_job_state, true));
                                $requires_review = ($job_state === 'completed') ? '0' : '1';
                                update_post_meta($post_id, $k_cancel_review, $requires_review);
                            }
                        }
                        if ($replacement_date_requested !== '') {
                            $queue_rescheduled_draft_after_cancel = true;
                        }
                        vms_add_admin_notice(__('Event plan marked Cancelled.', 'backstage-venue-manager'), 'success');
                        if ($queue_rescheduled_draft_after_cancel) {
                            vms_add_admin_notice(__('Replacement date captured. VMS will create a linked Draft Event Plan after this cancellation save completes.', 'backstage-venue-manager'), 'info');
                        }
                        if ($cancel_policy_post !== 'status_only') {
                            vms_add_admin_notice(__('Cancellation job captured. Review the Cancellation Job panel for step outcomes and refund activity.', 'backstage-venue-manager'), 'warning');
                        }
                        break;

                    case 'run_live_refunds_now':
                        $new_status = 'cancelled';
                        if ($cancel_manual_live_refund_confirm_post !== '1') {
                            vms_add_admin_notice(__('Live refund run was not confirmed. No refunds were attempted.', 'backstage-venue-manager'), 'warning');
                            break;
                        }
                        if (!function_exists('vms_cancellation_request_live_refund_run')) {
                            vms_add_admin_notice(__('Live refund helper is unavailable.', 'backstage-venue-manager'), 'error');
                            break;
                        }

                        $live_refund = (array) vms_cancellation_request_live_refund_run($post_id, array(
                            'requested_by_user_id' => get_current_user_id(),
                            'policy_override' => (string) $cancel_policy_post,
                        ));
                        if (empty($live_refund['ok'])) {
                            $err = sanitize_text_field((string) ($live_refund['error'] ?? 'manual_live_refund_failed'));
                            vms_add_admin_notice(__('Unable to run live refunds:', 'backstage-venue-manager') . ' ' . $err, 'error');
                            break;
                        }

                        $live_state = sanitize_key((string) ($live_refund['state'] ?? 'queued'));
                        $live_summary = isset($live_refund['summary']) && is_array($live_refund['summary']) ? $live_refund['summary'] : array();
                        $target_policy = sanitize_key((string) ($live_refund['target_policy'] ?? 'stop_sales_auto_refund'));
                        $policy_labels = function_exists('vms_cancellation_policy_options') ? (array) vms_cancellation_policy_options() : array();
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

                        vms_add_admin_notice(
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
                            vms_add_admin_notice(__('Cancellation policy was upgraded to a live auto-refund policy for this already-cancelled plan before running refunds.', 'backstage-venue-manager'), 'info');
                        }
                        if ($queued_count > 0 || $failed_count > 0 || $live_state !== 'completed') {
                            vms_add_admin_notice(__('Review the Cancellation Job panel for any orders that still require manual follow-up.', 'backstage-venue-manager'), 'warning');
                        }
                        break;

                    case 'retry_cancellation_step':
                        $new_status = 'cancelled';
                        if ($retry_step_key === '') {
                            vms_add_admin_notice(__('Retry request is missing a step key.', 'backstage-venue-manager'), 'error');
                            break;
                        }
                        if (!function_exists('vms_cancellation_retry_step')) {
                            vms_add_admin_notice(__('Cancellation retry helper is unavailable.', 'backstage-venue-manager'), 'error');
                            break;
                        }

                        $retry = (array) vms_cancellation_retry_step($post_id, $retry_step_key, array(
                            'retry_by_user_id' => get_current_user_id(),
                        ));
                        if (empty($retry['ok'])) {
                            $err = sanitize_text_field((string) ($retry['error'] ?? 'retry_failed'));
                            vms_add_admin_notice(__('Unable to retry cancellation step:', 'backstage-venue-manager') . ' ' . $err, 'error');
                            break;
                        }

                        if (function_exists('vms_cancellation_run_job')) {
                            $run = (array) vms_cancellation_run_job($post_id);
                            $run_state = sanitize_key((string) ($run['state'] ?? 'queued'));
                            $state_labels = function_exists('vms_cancellation_job_statuses') ? (array) vms_cancellation_job_statuses() : array();
                            $run_state_label = isset($state_labels[$run_state]) ? (string) $state_labels[$run_state] : strtoupper($run_state ?: 'queued');
                            vms_add_admin_notice(
                                sprintf(
                                    /* translators: 1: value 1 used in this message, 2: value 2 used in this message. */
                                    __('Cancellation step retried: %1$s. Job state: %2$s.', 'backstage-venue-manager'),
                                    sanitize_text_field($retry_step_key),
                                    $run_state_label
                                ),
                                'success'
                            );
                        } else {
                            vms_add_admin_notice(__('Cancellation step retry queued. Run the cancellation job to process pending steps.', 'backstage-venue-manager'), 'warning');
                        }
                        break;

                    case 'create_rescheduled_draft':
                        $new_status = 'cancelled';
                        if ($current_status !== 'cancelled') {
                            vms_add_admin_notice(__('Only cancelled Event Plans can create a rescheduled draft.', 'backstage-venue-manager'), 'error');
                            break;
                        }

                        $replacement_date = isset($_POST['vms_reschedule_event_date'])
                            ? sanitize_text_field((string) wp_unslash($_POST['vms_reschedule_event_date']))
                            : '';

                        if (!function_exists('vms_event_plan_create_rescheduled_draft')) {
                            vms_add_admin_notice(__('Reschedule helper is unavailable in this build.', 'backstage-venue-manager'), 'error');
                            break;
                        }

                        $created = (array) vms_event_plan_create_rescheduled_draft($post_id, array(
                            'replacement_date' => $replacement_date,
                            'post_title'       => (string) get_the_title($post_id),
                        ));

                        if (empty($created['ok'])) {
                            $err = (string) ($created['error_message'] ?? __('VMS could not create the rescheduled draft.', 'backstage-venue-manager'));
                            vms_add_admin_notice(sanitize_text_field($err), 'error');
                            break;
                        }

                        $new_plan_id = absint($created['new_post_id'] ?? 0);
                        if ($new_plan_id > 0 && function_exists('vms_event_plan_set_runtime_redirect_target')) {
                            vms_event_plan_set_runtime_redirect_target($post_id, $new_plan_id, array(
                                'vms_rescheduled_from' => $post_id,
                            ));
                        }

                        vms_add_admin_notice(__('Rescheduled draft created. Review the replacement plan and publish it when ready.', 'backstage-venue-manager'), 'success');
                        vms_add_admin_notice(__('The cancelled Event Plan was preserved and linked to the new draft for audit history.', 'backstage-venue-manager'), 'info');
                        break;

                    case 'retry_cancellation_all':
                        $new_status = 'cancelled';
                        $requires_bulk_confirm = false;
                        $k_job_summary = function_exists('vms_meta_key')
                            ? (vms_meta_key('event_plan', 'cancel_job_summary') ?: '_vms_cancel_job_summary')
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
                        $bulk_confirm = isset($_POST['vms_cancel_bulk_retry_confirm']) ? sanitize_text_field((string) $_POST['vms_cancel_bulk_retry_confirm']) : '0';
                        if ($requires_bulk_confirm && $bulk_confirm !== '1') {
                            vms_add_admin_notice(__('Bulk retry requires confirmation because Refund execution is failed/blocked. Confirm and retry again.', 'backstage-venue-manager'), 'error');
                            break;
                        }

                        if (!function_exists('vms_cancellation_retry_all_failed_steps')) {
                            vms_add_admin_notice(__('Cancellation bulk retry helper is unavailable.', 'backstage-venue-manager'), 'error');
                            break;
                        }

                        $retry_all = (array) vms_cancellation_retry_all_failed_steps($post_id, array(
                            'retry_by_user_id' => get_current_user_id(),
                        ));
                        if (empty($retry_all['ok'])) {
                            $err = sanitize_text_field((string) ($retry_all['error'] ?? 'retry_failed'));
                            vms_add_admin_notice(__('Unable to retry cancellation steps:', 'backstage-venue-manager') . ' ' . $err, 'error');
                            break;
                        }

                        if (function_exists('vms_cancellation_run_job')) {
                            $run = (array) vms_cancellation_run_job($post_id);
                            $run_state = sanitize_key((string) ($run['state'] ?? 'queued'));
                            $state_labels = function_exists('vms_cancellation_job_statuses') ? (array) vms_cancellation_job_statuses() : array();
                            $run_state_label = isset($state_labels[$run_state]) ? (string) $state_labels[$run_state] : strtoupper($run_state ?: 'queued');
                            $retried = isset($retry_all['retried_steps']) && is_array($retry_all['retried_steps']) ? count($retry_all['retried_steps']) : 0;
                            vms_add_admin_notice(
                                sprintf(
                                    /* translators: 1: number 1 used in this message, 2: value 2 used in this message. */
                                    __('Cancellation bulk retry queued for %1$d step(s). Job state: %2$s.', 'backstage-venue-manager'),
                                    $retried,
                                    $run_state_label
                                ),
                                'success'
                            );
                        } else {
                            vms_add_admin_notice(__('Cancellation bulk retry queued. Run the cancellation job to process pending steps.', 'backstage-venue-manager'), 'warning');
                        }
                        break;

            
        case 'apply_vendor_defaults':
                    case 'apply_band_defaults':
                        $vendor_id_for_apply = (int) get_post_meta($post_id, '_vms_band_vendor_id', true);
                        $venue_id_for_apply = isset($_POST['vms_venue_id']) ? absint($_POST['vms_venue_id']) : 0;
                        if ($venue_id_for_apply <= 0) {
                            $k_venue_id = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'venue_id') ?: '_vms_venue_id') : '_vms_venue_id';
                            $venue_id_for_apply = (int) get_post_meta($post_id, $k_venue_id, true);
                        }
                        $event_date_for_apply = isset($_POST['vms_event_date']) ? sanitize_text_field((string) wp_unslash($_POST['vms_event_date'])) : '';
                        if ($event_date_for_apply === '') {
                            $k_event_date = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'date') ?: '_vms_event_date') : '_vms_event_date';
                            $event_date_for_apply = (string) get_post_meta($post_id, $k_event_date, true);
                        }

                        if ($vendor_id_for_apply <= 0) {
                            vms_add_admin_notice(__('Select a Primary Vendor first.', 'backstage-venue-manager'), 'error');
                            vms_admin_scroll_to_compensation($post_id);
                            break;
                        }

                        if (!function_exists('vms_get_vendor_default_comp_terms') || !function_exists('vms_event_plan_apply_comp_terms')) {
                            vms_add_admin_notice(__('Vendor default helper is missing.', 'backstage-venue-manager'), 'error');
                            vms_admin_scroll_to_compensation($post_id);
                            break;
                        }

                        $vendor_terms = (array) vms_get_vendor_default_comp_terms($vendor_id_for_apply, (int) $venue_id_for_apply, (string) $event_date_for_apply);
                        if (function_exists('vms_get_vendor_default_agent_fee_terms')) {
                            $vendor_agent_fee = (array) vms_get_vendor_default_agent_fee_terms($vendor_id_for_apply);
                            if (!empty($vendor_agent_fee)) {
                                $vendor_terms = array_merge($vendor_terms, $vendor_agent_fee);
                            }
                        }

                        if (empty($vendor_terms) || empty($vendor_terms['structure'])) {
                            vms_add_admin_notice(__('No current Primary Vendor default is configured for this Event Plan context.', 'backstage-venue-manager'), 'error');
                            vms_admin_scroll_to_compensation($post_id);
                            break;
                        }

                        $applied_vendor_defaults = vms_event_plan_apply_comp_terms($post_id, $vendor_terms);
                        if (!$applied_vendor_defaults) {
                            vms_add_admin_notice(__('Could not apply the current Primary Vendor default to Draft Pay.', 'backstage-venue-manager'), 'error');
                            vms_admin_scroll_to_compensation($post_id);
                            break;
                        }

                        update_post_meta($post_id, '_vms_comp_needs_snapshot', '1');
                        delete_post_meta($post_id, '_vms_comp_package_id');
                        $k_comp_selected_option = function_exists('vms_meta_key')
                            ? (vms_meta_key('event_plan', 'comp_selected_option') ?: '_vms_comp_selected_option')
                            : '_vms_comp_selected_option';
                        update_post_meta($post_id, $k_comp_selected_option, 'default:vendor');
                        vms_add_admin_notice(__('Current Primary Vendor default applied to Draft Pay.', 'backstage-venue-manager'), 'success');
                        vms_admin_scroll_to_compensation($post_id);
                        break;

                    case 'apply_venue_defaults':
                        $venue_id   = isset($_POST['vms_venue_id']) ? absint($_POST['vms_venue_id']) : 0;
                        $event_date = isset($_POST['vms_event_date']) ? sanitize_text_field($_POST['vms_event_date']) : '';

                        if ($venue_id <= 0 || !$event_date) {
                            vms_add_admin_notice(__('Select a Venue and Event Date first.', 'backstage-venue-manager'), 'error');
                            break;
                        }
                        if (!function_exists('vms_get_event_plan_effective_comp_default')) {
                            vms_add_admin_notice(__('Effective defaults helper is missing.', 'backstage-venue-manager'), 'error');
                            break;
                        }

                        $resolved = (array) vms_get_event_plan_effective_comp_default($venue_id, $event_date);
                        if (empty($resolved['has_default']) || empty($resolved['structure'])) {
                            vms_add_admin_notice(__('No date defaults found for that date/day.', 'backstage-venue-manager'), 'error');
                            break;
                        }

                        if (function_exists('vms_event_plan_apply_comp_terms')) {
                            $applied = vms_event_plan_apply_comp_terms($post_id, $resolved);
                            if (!$applied) {
                                vms_add_admin_notice(__('Could not apply date defaults because the resolved compensation terms were invalid.', 'backstage-venue-manager'), 'error');
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
                        vms_add_admin_notice(sprintf(__('%s applied for this date.', 'backstage-venue-manager'), $applied_label), 'success');
                        vms_admin_scroll_to_compensation($post_id);
                        break;

                    case 'apply_comp_package':
                        if ($venue_id <= 0) {
                            vms_add_admin_notice(__('Please select a Venue first, then apply the package.', 'backstage-venue-manager'), 'error');
                            break;
                        }
                        if ($comp_package_id <= 0) {
                            vms_add_admin_notice(__('Please select a comp package first.', 'backstage-venue-manager'), 'error');
                            break;
                        }
                        if (!function_exists('vms_apply_comp_package_to_plan')) {
                            vms_add_admin_notice(__('Package apply helper is missing (vms_apply_comp_package_to_plan).', 'backstage-venue-manager'), 'error');
                            break;
                        }

                        $ok = vms_apply_comp_package_to_plan($post_id, $comp_package_id);
                        vms_admin_scroll_to_compensation($post_id);

                        if ($ok) vms_add_admin_notice(__('Comp package applied and snapshotted for this event plan.', 'backstage-venue-manager'), 'success');
                        else vms_add_admin_notice(__('Failed to Apply Package. (Check package type/meta.)', 'backstage-venue-manager'), 'error');
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
                        $deposit_terms = function_exists('vms_get_event_plan_deposit_terms') ? (array) vms_get_event_plan_deposit_terms((int) $post_id) : array();
                        $final_payment_terms = function_exists('vms_get_event_plan_final_payment_terms') ? (array) vms_get_event_plan_final_payment_terms((int) $post_id) : array();

                        $flat  = ($flat === '' || $flat === null) ? null : (float) $flat;
                        $split = ($split === '' || $split === null) ? null : (float) $split;
                        $bonus_start_count = ($bonus_start_count === '' || $bonus_start_count === null) ? null : (int) $bonus_start_count;
                        $bonus_step_size = ($bonus_step_size === '' || $bonus_step_size === null) ? null : (int) $bonus_step_size;
                        $bonus_step_bonus = ($bonus_step_bonus === '' || $bonus_step_bonus === null) ? null : (float) $bonus_step_bonus;
                        $bonus_per_ticket_rate = ($bonus_per_ticket_rate === '' || $bonus_per_ticket_rate === null) ? null : (float) $bonus_per_ticket_rate;
                        $bonus_max_bonus = ($bonus_max_bonus === '' || $bonus_max_bonus === null) ? null : (float) $bonus_max_bonus;
                        $commission_percent = ($commission_percent === '' || $commission_percent === null) ? null : max(0, (float) $commission_percent);

                        if (in_array($structure, array('flat_fee', 'flat_fee_door_split'), true) && ($flat === null || $flat <= 0)) {
                            vms_add_admin_notice(__('Cannot lock Draft Pay: Flat Fee Amount is required for this structure.', 'backstage-venue-manager'), 'error');
                            break;
                        }
                        if (in_array($structure, array('door_split', 'flat_fee_door_split'), true) && ($split === null || $split <= 0 || $split > 100)) {
                            vms_add_admin_notice(__('Cannot lock Draft Pay: Door Split % must be between 1 and 100 for this structure.', 'backstage-venue-manager'), 'error');
                            break;
                        }
                        if ($structure === 'attendance_bonus') {
                            if ($flat === null || $flat < 0) {
                                vms_add_admin_notice(__('Cannot lock Draft Pay: Base Pay is required for Base + Attendance Bonus.', 'backstage-venue-manager'), 'error');
                                break;
                            }
                            if (!in_array($bonus_mode, array('step', 'continuous'), true)) {
                                vms_add_admin_notice(__('Cannot lock Draft Pay: Bonus Style is required for Base + Attendance Bonus.', 'backstage-venue-manager'), 'error');
                                break;
                            }
                            if ($bonus_start_count === null || $bonus_start_count < 0) {
                                vms_add_admin_notice(__('Cannot lock Draft Pay: Bonus Starts After is required for Base + Attendance Bonus.', 'backstage-venue-manager'), 'error');
                                break;
                            }
                            if ($bonus_mode === 'step') {
                                if ($bonus_step_size === null || $bonus_step_size < 1) {
                                    vms_add_admin_notice(__('Cannot lock Draft Pay: Step Size must be at least 1 for step attendance bonus mode.', 'backstage-venue-manager'), 'error');
                                    break;
                                }
                                if ($bonus_step_bonus === null || $bonus_step_bonus < 0) {
                                    vms_add_admin_notice(__('Cannot lock Draft Pay: Bonus Per Step is required for step attendance bonus mode.', 'backstage-venue-manager'), 'error');
                                    break;
                                }
                            } else {
                                if ($bonus_per_ticket_rate === null || $bonus_per_ticket_rate < 0) {
                                    vms_add_admin_notice(__('Cannot lock Draft Pay: Bonus Per Ticket is required for continuous attendance bonus mode.', 'backstage-venue-manager'), 'error');
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

                        $hash = function_exists('vms_comp_hash_for_plan')
                            ? (string) vms_comp_hash_for_plan($post_id)
                            : md5(wp_json_encode(array('structure' => $structure, 'flat' => $flat, 'split' => $split)));

                        $snapshot['comp_hash'] = $hash;

                        update_post_meta($post_id, '_vms_comp_snapshot', $snapshot);
                        delete_post_meta($post_id, '_vms_comp_needs_snapshot');

                        vms_add_admin_notice(__('Draft Pay locked for this event (snapshot created).', 'backstage-venue-manager'), 'success');
                        vms_admin_scroll_to_compensation($post_id);
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

                    $fallback = function_exists('vms_event_plan_build_fallback_title')
                        ? vms_event_plan_build_fallback_title($post_id, (string) $band_name, (string) $venue_name)
                        : ((trim((string) $band_name) !== '')
                            ? trim((string) $band_name)
                            : ((trim((string) $venue_name) !== '')
                                ? trim((string) $venue_name)
                                : ('Event Plan #' . (int) $post_id)));

                    // Update title once, safely
                    remove_action('save_post_vms_event_plan', array($this, 'save_event_plan_meta'), 10);
                    vms_event_plan_perf_wp_update_post(array('ID' => $post_id, 'post_title' => $fallback), 'event_plan_fallback_title_sync', $post_id);
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
                    vms_event_plan_perf_wp_update_post(array(
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
                    if (!function_exists('vms_event_plan_create_rescheduled_draft')) {
                        vms_add_admin_notice(__('Reschedule helper is unavailable in this build.', 'backstage-venue-manager'), 'error');
                    } else {
                        $created = (array) vms_event_plan_create_rescheduled_draft($post_id, array(
                            'replacement_date' => $replacement_date_requested,
                            'post_title'       => (string) get_the_title($post_id),
                        ));

                        if (empty($created['ok'])) {
                            $err = (string) ($created['error_message'] ?? __('VMS could not create the rescheduled draft.', 'backstage-venue-manager'));
                            vms_add_admin_notice(sanitize_text_field($err), 'error');
                        } else {
                            $new_plan_id = absint($created['new_post_id'] ?? 0);
                            if ($new_plan_id > 0 && function_exists('vms_event_plan_set_runtime_redirect_target')) {
                                vms_event_plan_set_runtime_redirect_target($post_id, $new_plan_id, array(
                                    'vms_rescheduled_from' => $post_id,
                                ));
                            }
                            vms_add_admin_notice(__('Linked rescheduled draft created. Review the replacement plan and publish it when ready.', 'backstage-venue-manager'), 'success');
                            vms_add_admin_notice(__('The cancelled Event Plan was preserved and linked to the new draft for audit history.', 'backstage-venue-manager'), 'info');
                        }
                    }
                }

	                $save_scope = function_exists('vms_event_plan_save_profiler_current_save_scope')
	                    ? vms_event_plan_save_profiler_current_save_scope($post_id)
	                    : '';
	                $featured_image_only_save = ($save_scope === 'featured_image_only');
	                if ($save_scope !== '' && function_exists('vms_event_plan_save_profiler_note')) {
	                    vms_event_plan_save_profiler_note('save_scope', $save_scope);
	                }

	                $tec_status_trace = function_exists('vms_event_plan_perf_span_start')
	                    ? vms_event_plan_perf_span_start('event_plan_tec_status_sync', $post_id, array('section' => 'tec_status_sync'))
	                    : '';
	                if ($featured_image_only_save) {
	                    if (function_exists('vms_event_plan_save_profiler_note_heavy_action')) {
	                        vms_event_plan_save_profiler_note_heavy_action('tec_status_sync', 'skipped', 'featured_image_only');
	                    }
	                } elseif (function_exists('vms_sync_tec_status_from_plan')) {
	                    vms_sync_tec_status_from_plan($post_id);
	                }
	                if (function_exists('vms_event_plan_perf_span_finish')) {
	                    vms_event_plan_perf_span_finish('event_plan_tec_status_sync', $post_id, $tec_status_trace, array(
	                        'section' => 'tec_status_sync',
	                        'phase' => $featured_image_only_save ? 'skip' : 'run',
	                        'skip_reason' => $featured_image_only_save ? 'featured_image_only' : '',
	                    ));
	                }

	                $featured_image_trace = function_exists('vms_event_plan_perf_span_start')
	                    ? vms_event_plan_perf_span_start('event_plan_featured_image_sync_on_save', $post_id, array('section' => 'featured_image_sync'))
	                    : '';
	                if (function_exists('vms_event_plan_save_profiler_note_heavy_action')) {
	                    vms_event_plan_save_profiler_note_heavy_action('featured_image_sync_on_save', 'skipped', 'delegated_to_save_post');
	                }
	                if (function_exists('vms_event_plan_perf_span_finish')) {
	                    vms_event_plan_perf_span_finish('event_plan_featured_image_sync_on_save', $post_id, $featured_image_trace, array(
	                        'section' => 'featured_image_sync',
	                        'phase' => 'skip',
	                        'skip_reason' => 'delegated_to_save_post',
	                    ));
	                }

	                $saved_side_effects_trace = function_exists('vms_event_plan_perf_span_start')
	                    ? vms_event_plan_perf_span_start('event_plan_saved_side_effects', $post_id, array('section' => 'saved_side_effects'))
	                    : '';
	                if ($featured_image_only_save) {
	                    if (function_exists('vms_event_plan_save_profiler_note_heavy_action')) {
	                        vms_event_plan_save_profiler_note_heavy_action('event_plan_saved_side_effects', 'skipped', 'featured_image_only');
	                    }
	                } else {
	                    do_action('vms_event_plan_saved', (int) $post_id, array(
	                        'plan_status' => (string) $new_status,
	                        'actor_user_id' => (int) get_current_user_id(),
	                    ));
	                }
	                if (function_exists('vms_event_plan_perf_span_finish')) {
	                    vms_event_plan_perf_span_finish('event_plan_saved_side_effects', $post_id, $saved_side_effects_trace, array(
	                        'section' => 'saved_side_effects',
	                        'phase' => $featured_image_only_save ? 'skip' : 'run',
	                        'skip_reason' => $featured_image_only_save ? 'featured_image_only' : '',
	                    ));
	                }

                if (function_exists('vms_event_plan_perf_span_finish')) {
                    vms_event_plan_perf_span_finish(
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
            new VMS_Admin_Event_Plans();
        }


        // Published-only by default in admin list views; optional what-if toggle includes Draft/Ready.
        // Applies to: edit.php?post_type=vms_event_plan
        if (is_admin()) {

            function vms_admin_event_plan_list_query_value(string $key): string
            {
                // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended -- Read-only list-filter routing; persistence is nonce-gated separately.
                if (!isset($_GET[$key]) || is_array($_GET[$key])) {
                    return '';
                }

                // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Helper centralizes the raw list-filter read; callers sanitize by expected type.
                return (string) wp_unslash($_GET[$key]);
            }

            function vms_admin_event_plan_list_has_valid_filter_nonce(): bool
            {
                $nonce = vms_admin_event_plan_list_query_value('_vms_ep_list_nonce');
                if ($nonce === '') {
                    return false;
                }

	                $nonce = sanitize_text_field($nonce);
	                return (bool) wp_verify_nonce($nonce, 'vms_event_plan_list_filters');
            }
    
            function vms_admin_event_plan_list_include_drafts_requested(): bool
            {
                $user_id = (int) get_current_user_id();

                // Persisted default (per-user). If the user has never set a preference,
                // default to showing Draft/Ready so the Event Plans list matches Schedule visibility.
                $has_pref = (function_exists('vms_user_pref_has_include_drafts'))
                    ? (bool) vms_user_pref_has_include_drafts($user_id)
                    : (bool) metadata_exists('user', $user_id, '_vms_include_drafts');

                $pref = $has_pref
                    ? ((function_exists('vms_user_pref_get_include_drafts')) ? (bool) vms_user_pref_get_include_drafts($user_id) : false)
                    : true;

                // If explicitly set in the querystring, treat it as an intentional update and persist it.
                $requested_include_drafts = vms_admin_event_plan_list_query_value('include_drafts');
                if ($requested_include_drafts !== '') {
                    $raw = strtolower(trim($requested_include_drafts));
                    $val = in_array($raw, array('1', 'true', 'yes', 'on'), true);

                    if (vms_admin_event_plan_list_has_valid_filter_nonce()) {
                        if (function_exists('vms_user_pref_set_include_drafts')) {
                            vms_user_pref_set_include_drafts((bool) $val, $user_id);
                        } else {
                            update_user_meta($user_id, '_vms_include_drafts', $val ? '1' : '0');
                        }
                    }

                    return (bool) $val;
                }

                return (bool) $pref;
            }

            function vms_admin_event_plan_list_add_include_drafts_toggle(): void
            {
                global $typenow;
                if ($typenow !== 'vms_event_plan') {
                    return;
                }

                $checked = vms_admin_event_plan_list_include_drafts_requested();

                echo '<input type="hidden" name="include_drafts" value="0">';
                wp_nonce_field('vms_event_plan_list_filters', '_vms_ep_list_nonce', false);

                echo '<label class="vms-ep-list-toggle" data-vms-tour="event-plans.include-drafts">';
                echo '<input type="checkbox" name="include_drafts" value="1"' . checked(true, $checked, false) . '>';
                echo ' ' . esc_html__('Include Draft/Ready (what-if)', 'backstage-venue-manager');
                echo '</label>';

                $tour_button = '<button type="button" class="button button-secondary vms-tour-help-trigger" data-vms-tour-start="vms.event_plan.list.basics" data-vms-tour="event-plans.help">' . esc_html__('Start Guided Tour', 'backstage-venue-manager') . '</button>';
                if (function_exists('vms_render_help_button')) {
                    $tour_button = vms_render_help_button(array(
                        'tour_id' => 'vms.event_plan.list.basics',
                        'anchor' => 'event-plans.help',
                        'label' => __('Start Guided Tour', 'backstage-venue-manager'),
                        'class' => 'button-secondary',
                    ));
                }

                echo ' <span class="vms-ep-list-help" data-vms-tour="event-plans.help-action">' . wp_kses_post($tour_button) . '</span>';
            }
            add_action('restrict_manage_posts', 'vms_admin_event_plan_list_add_include_drafts_toggle');

            function vms_admin_event_plan_list_default_published_only_query($query): void
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
                $ps_req = sanitize_key(vms_admin_event_plan_list_query_value('post_status'));
                if ($ps_req !== '' && $ps_req !== 'all') {
                    return;
                }

                $include_drafts = vms_admin_event_plan_list_include_drafts_requested();

                // Narrow the query for performance.
                $query->set('post_status', $include_drafts
                    ? array('publish', 'private', 'draft', 'pending', 'future')
                    : array('publish', 'private')
                );

                // Marker for the_posts filter.
                $query->set('vms_ep_filter_internal_status', 1);
                $query->set('vms_ep_include_drafts', $include_drafts ? 1 : 0);
            }
            add_action('pre_get_posts', 'vms_admin_event_plan_list_default_published_only_query');

            function vms_admin_event_plan_list_filter_by_internal_status($posts, $query)
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

                    if (function_exists('vms_event_plan_should_include')) {
                        if (!vms_event_plan_should_include($pid, 'event_list', array(
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
            add_filter('the_posts', 'vms_admin_event_plan_list_filter_by_internal_status', 10, 2);
        }

        /**
         * Validation — used for READY and PUBLISH
         */
        function vms_event_plan_is_venue_closed_for_event_date(int $venue_id, string $event_date, array &$context = array()): bool
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
            if (function_exists('vms_get_venue_holiday_for_date')) {
                $holiday = vms_get_venue_holiday_for_date($venue_id, $event_date);
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
            if (function_exists('vms_sch_season_get_rules') && function_exists('vms_sch_season_is_open_by_rules')) {
                $rules = vms_sch_season_get_rules($venue_id);
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
                    $is_open = (bool) vms_sch_season_is_open_by_rules((array) $rules, $event_date);
                    $context['reason'] = $is_open ? 'season_rules_open' : 'season_rules_closed';
                    return !$is_open;
                }
            }

            return false;
        }

        function vms_validate_event_plan(int $post_id): array
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
            if (function_exists('vms_event_plan_vendor_exists') && !vms_event_plan_vendor_exists((int) $band_id)) {
                $errors[] = __('Selected Primary Vendor no longer exists (it was deleted). Select a new Primary Vendor before marking Ready.', 'backstage-venue-manager');
                return $errors;
            }

            if ($event_date && function_exists('vms_get_vendor_availability_for_date')) {
                $availability = vms_get_vendor_availability_for_date($band_id, $event_date);
                if ($availability === 'unavailable') {
                    $band_name = get_the_title($band_id) ?: __('Selected Primary Vendor', 'backstage-venue-manager');
                    $nice_date = date_i18n('M j, Y', strtotime($event_date));
                    /* translators: 1: primary vendor name, 2: event date. */
                    $errors[] = sprintf(__('%1$s is marked Not Available on %2$s.', 'backstage-venue-manager'), $band_name, $nice_date);
                }
            }

            // Vendor tax profile required unless bypass is active
            if ($band_id > 0) {
                $missing = function_exists('vms_vendor_tax_profile_missing_items') ? (array) vms_vendor_tax_profile_missing_items($band_id) : array();
                if (!empty($missing)) {
                    if (function_exists('vms_tax_bypass_is_active') && vms_tax_bypass_is_active($band_id)) {
                        if (function_exists('vms_add_admin_notice')) {
                            vms_add_admin_notice(
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
                        if (function_exists('vms_add_admin_notice')) {
                            $vendor_name = get_the_title($band_id);
                            vms_add_admin_notice(
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

                        $missing = function_exists('vms_vendor_tax_profile_missing_items') ? (array) vms_vendor_tax_profile_missing_items($sid) : array();
                        if (!empty($missing)) {
                            if (function_exists('vms_tax_bypass_is_active') && vms_tax_bypass_is_active($sid)) {
                                if (function_exists('vms_add_admin_notice')) {
                                    vms_add_admin_notice(
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
                                if (function_exists('vms_add_admin_notice')) {
                                    $staff_name = get_the_title($sid);
                                    vms_add_admin_notice(
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
            if ($venue_id > 0 && $event_date && function_exists('vms_event_plan_is_venue_closed_for_event_date') && vms_event_plan_is_venue_closed_for_event_date($venue_id, $event_date, $venue_closed_context)) {
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
        function vms_maybe_autoset_event_plan_title(int $post_id): void
        {
            $post = get_post($post_id);
            if (!$post) return;

            $current_title = trim((string)$post->post_title);
            if ($current_title && strcasecmp($current_title, 'Auto Draft') !== 0) return;

            $band_id = (int) get_post_meta($post_id, '_vms_band_vendor_id', true);
            $band_name = $band_id ? (string) get_the_title($band_id) : '';
            $new_title = function_exists('vms_event_plan_build_auto_title')
                ? vms_event_plan_build_auto_title((string) $band_name)
                : trim(wp_strip_all_tags((string) $band_name));
            if ($new_title === '') return;

            $autoset_postarr = array(
                'ID'         => $post_id,
                'post_title' => $new_title,
                'post_name'  => sanitize_title($new_title),
            );
            $autoset_changed_fields = function_exists('vms_event_plan_perf_post_update_changed_fields')
                ? vms_event_plan_perf_post_update_changed_fields($post_id, $autoset_postarr)
                : array('post_title', 'post_name');
            if (empty($autoset_changed_fields)) {
                if (function_exists('vms_event_plan_perf_log')) {
                    vms_event_plan_perf_log('event_plan_autoset_title_action', $post_id, array(
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

            vms_event_plan_perf_wp_update_post($autoset_postarr, 'event_plan_autoset_title_action', $post_id);

            /* translators: %s: human-readable value used in this message. */
            vms_add_admin_notice(sprintf(__('Event title set to "%s".', 'backstage-venue-manager'), $new_title), 'success');
        }

        /**
         * Admin notices — transient-based
         */
        function vms_add_admin_notice(string $message, string $type = 'success'): void
        {
            $user_id = get_current_user_id();
            if (!$user_id) return;

            $key = 'vms_event_plan_notices_' . $user_id;
            $notices = get_transient($key);
            if (!is_array($notices)) $notices = array();

            $notices[] = array('type' => $type, 'message' => $message);
            set_transient($key, $notices, 60);
        }

        if (!function_exists('vms_event_plan_editor_register_detached_form')) {
            /**
             * @param array<string,mixed> $hidden_fields
             */
            function vms_event_plan_editor_register_detached_form(string $form_id, string $method, string $action, array $hidden_fields = array()): void
            {
                global $vms_event_plan_editor_detached_forms;
                if (!is_array($vms_event_plan_editor_detached_forms)) {
                    $vms_event_plan_editor_detached_forms = array();
                }

                $vms_event_plan_editor_detached_forms[$form_id] = array(
                    'method' => (strtolower($method) === 'get') ? 'get' : 'post',
                    'action' => esc_url_raw($action),
                    'hidden_fields' => $hidden_fields,
                );
            }
        }

        if (!function_exists('vms_event_plan_editor_render_detached_forms')) {
            function vms_event_plan_editor_render_detached_forms(): void
            {
                global $vms_event_plan_editor_detached_forms;
                if (!is_array($vms_event_plan_editor_detached_forms) || empty($vms_event_plan_editor_detached_forms)) {
                    return;
                }

                foreach ($vms_event_plan_editor_detached_forms as $form_id => $form) {
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
        add_action('admin_footer', 'vms_event_plan_editor_render_detached_forms', 44);

        if (!function_exists('vms_event_plan_edit_screen_url')) {
            function vms_event_plan_edit_screen_url(int $post_id = 0): string
            {
                $post_id = absint($post_id);
                if ($post_id > 0) {
                    return admin_url('post.php?post=' . $post_id . '&action=edit');
                }

                return admin_url('edit.php?post_type=vms_event_plan');
            }
        }

        if (!function_exists('vms_event_plan_handle_resync_calendar_request')) {
            /**
             * @param array<string,mixed> $request
             * @return array{ok:bool,post_id:int,tec_event_id:int,redirect_url:string,notice_type:string,notice_message:string,source:string}
             */
            function vms_event_plan_handle_resync_calendar_request(array $request, bool $redirect = true): array
            {
                $post_id = isset($request['post_id']) ? absint($request['post_id']) : 0;
                $default_redirect = vms_event_plan_edit_screen_url($post_id);
                $redirect_raw = isset($request['redirect_to']) ? (string) wp_unslash($request['redirect_to']) : '';
                $redirect_url = $redirect_raw !== ''
                    ? wp_validate_redirect($redirect_raw, $default_redirect)
                    : $default_redirect;
                $source = isset($request['source']) ? sanitize_key((string) wp_unslash($request['source'])) : '';
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
                        : vms_event_plan_edit_screen_url($post_id);
	                    $nonce = (isset($request['_vms_resync_calendar_nonce']) && !is_array($request['_vms_resync_calendar_nonce']))
	                        ? sanitize_text_field(wp_unslash((string) $request['_vms_resync_calendar_nonce']))
	                        : '';
                    if ($nonce === '' || !wp_verify_nonce($nonce, 'vms_resync_calendar')) {
                        $result['notice_message'] = __('Calendar re-sync request could not be verified. Please reload the Event Plan and try again.', 'backstage-venue-manager');
                    } else {
                        $tec_key_id = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'tec_event_id') ?: '_vms_tec_event_id') : '_vms_tec_event_id';
                        $existing_tec_id = (int) get_post_meta($post_id, $tec_key_id, true);
                        $result['tec_event_id'] = $existing_tec_id;

                        if ($existing_tec_id <= 0) {
                            $result['notice_message'] = __('No linked calendar event found. Use “Publish Now” first.', 'backstage-venue-manager');
                        } else {
                            $errors = vms_validate_event_plan($post_id);
                            if (!empty($errors)) {
                                $result['notice_message'] = __('Cannot re-sync:', 'backstage-venue-manager') . ' ' . implode(' ', $errors);
                            } else {
                                $ok = vms_resync_event_to_calendar($post_id, $post, $existing_tec_id);
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
                    vms_add_admin_notice($result['notice_message'], $result['notice_type']);
                }

                if ($redirect) {
                    wp_safe_redirect($result['redirect_url']);
                    exit;
                }

                return $result;
            }
        }

        if (!function_exists('vms_handle_admin_post_resync_event_to_calendar')) {
            function vms_handle_admin_post_resync_event_to_calendar(): void
            {
                vms_event_plan_handle_resync_calendar_request($_REQUEST, true);
            }
        }
        add_action('admin_post_vms_resync_event_to_calendar', 'vms_handle_admin_post_resync_event_to_calendar');

        if (!function_exists('vms_resolve_live_refund_event_plan_id')) {
            function vms_resolve_live_refund_event_plan_id(int $candidate_id): int
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
                    return vms_resolve_live_refund_event_plan_id((int) $post->post_parent);
                }

                if ($post_type === 'tribe_events' && function_exists('vms_get_event_plan_for_tec_event')) {
                    $linked_plan_id = (int) vms_get_event_plan_for_tec_event((int) $post->ID);
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

        if (!function_exists('vms_run_live_refunds_now_request')) {
            function vms_run_live_refunds_now_request(array $request): void
            {
                $requested_event_plan_id = isset($request['event_plan_id']) ? absint($request['event_plan_id']) : 0;
                $requested_post_id = isset($request['post_id']) ? absint($request['post_id']) : 0;
                $requested_source_post_id = isset($request['source_post_id']) ? absint($request['source_post_id']) : 0;
                $request_candidate_id = $requested_event_plan_id > 0
                    ? $requested_event_plan_id
                    : ($requested_source_post_id > 0 ? $requested_source_post_id : $requested_post_id);
                $return_url_raw = isset($request['return_url']) ? (string) wp_unslash($request['return_url']) : '';

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

                $post_id = function_exists('vms_resolve_live_refund_event_plan_id')
                    ? vms_resolve_live_refund_event_plan_id($request_candidate_id)
                    : $request_candidate_id;
                $redirect_post_id = $post_id > 0 ? $post_id : $request_candidate_id;
                $redirect_url = function_exists('vms_event_plan_admin_edit_url')
                    ? vms_event_plan_admin_edit_url($redirect_post_id, array(), 'vms-cancel-job-panel', 'raw')
                    : add_query_arg(array('post' => $redirect_post_id, 'action' => 'edit'), admin_url('post.php'));
                if ($return_url_raw !== '') {
                    $fallback_url = function_exists('vms_event_plan_admin_edit_url')
                        ? vms_event_plan_admin_edit_url($redirect_post_id, array(), 'vms-cancel-job-panel', 'raw')
                        : admin_url('edit.php?post_type=vms_event_plan');
                    $redirect_url = wp_validate_redirect($return_url_raw, $fallback_url);
                }

                if ($post_id <= 0 || get_post_type($post_id) !== 'vms_event_plan') {
                    if (function_exists('vms_add_admin_notice')) {
                        $received_type = $request_candidate_id > 0 ? sanitize_key((string) get_post_type($request_candidate_id)) : '';
                        $debug_context = sprintf(
                            /* translators: 1: number 1 used in this message, 2: value 2 used in this message. */
                            __('Received ID %1$d (%2$s).', 'backstage-venue-manager'),
                            (int) $request_candidate_id,
                            $received_type !== '' ? $received_type : __('unknown type', 'backstage-venue-manager')
                        );
                        vms_add_admin_notice(__('Invalid Event Plan for live refund action.', 'backstage-venue-manager') . ' ' . $debug_context, 'error');
                    }
                    wp_safe_redirect($redirect_url);
                    exit;
                }

                if (!current_user_can('edit_post', $post_id)) {
                    if (function_exists('vms_add_admin_notice')) {
                        vms_add_admin_notice(__('You do not have permission to run live refunds for this Event Plan.', 'backstage-venue-manager'), 'error');
                    }
                    wp_safe_redirect($redirect_url);
                    exit;
                }

	                $nonce = (isset($request['_wpnonce']) && !is_array($request['_wpnonce']))
	                    ? sanitize_text_field(wp_unslash((string) $request['_wpnonce']))
	                    : '';
                $nonce_ok = false;
                foreach (array_unique(array_filter(array(absint($post_id), absint($request_candidate_id), absint($requested_source_post_id), absint($requested_post_id)))) as $nonce_post_id) {
                    if (wp_verify_nonce($nonce, 'vms_run_live_refunds_now_' . $nonce_post_id)) {
                        $nonce_ok = true;
                        break;
                    }
                }
                if (!$nonce_ok) {
                    if (function_exists('vms_add_admin_notice')) {
                        vms_add_admin_notice(__('Live refund request could not be verified. Please reload the Event Plan and try again.', 'backstage-venue-manager'), 'error');
                    }
                    wp_safe_redirect($redirect_url);
                    exit;
                }

                if (!function_exists('vms_cancellation_request_live_refund_run')) {
                    if (function_exists('vms_add_admin_notice')) {
                        vms_add_admin_notice(__('Live refund helper is unavailable.', 'backstage-venue-manager'), 'error');
                    }
                    wp_safe_redirect($redirect_url);
                    exit;
                }

                $live_refund = (array) vms_cancellation_request_live_refund_run($post_id, array(
                    'requested_by_user_id' => get_current_user_id(),
                ));

                if (empty($live_refund['ok'])) {
                    $err = sanitize_text_field((string) ($live_refund['error'] ?? 'manual_live_refund_failed'));
                    if (function_exists('vms_add_admin_notice')) {
                        vms_add_admin_notice(__('Unable to run live refunds:', 'backstage-venue-manager') . ' ' . $err, 'error');
                    }
                    wp_safe_redirect($redirect_url);
                    exit;
                }

                $live_state = sanitize_key((string) ($live_refund['state'] ?? 'queued'));
                $live_summary = isset($live_refund['summary']) && is_array($live_refund['summary']) ? $live_refund['summary'] : array();
                $target_policy = sanitize_key((string) ($live_refund['target_policy'] ?? 'stop_sales_auto_refund'));
                $policy_labels = function_exists('vms_cancellation_policy_options') ? (array) vms_cancellation_policy_options() : array();
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

                if (function_exists('vms_add_admin_notice')) {
                    vms_add_admin_notice(
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
                        vms_add_admin_notice(__('Cancellation policy was upgraded to a live auto-refund policy for this already-cancelled plan before running refunds.', 'backstage-venue-manager'), 'info');
                    }
                    if ($queued_count > 0 || $failed_count > 0 || $live_state !== 'completed') {
                        vms_add_admin_notice(__('Review the Cancellation Job panel for any orders that still require manual follow-up.', 'backstage-venue-manager'), 'warning');
                    }
                }

                wp_safe_redirect($redirect_url);
                exit;
            }
        }

        add_action('admin_init', 'vms_handle_live_refunds_now_screen_request');
        function vms_handle_live_refunds_now_screen_request(): void
        {
            if (!is_admin()) {
                return;
            }
            $flag = isset($_GET['vms_live_refund_now']) ? sanitize_key((string) wp_unslash($_GET['vms_live_refund_now'])) : '';
            if ($flag !== '1') {
                return;
            }
            vms_run_live_refunds_now_request($_GET);
        }

        add_action('admin_post_vms_run_live_refunds_now', 'vms_handle_admin_post_run_live_refunds_now');
        function vms_handle_admin_post_run_live_refunds_now(): void
        {
            vms_run_live_refunds_now_request($_REQUEST);
        }

        add_action('admin_notices', 'vms_render_event_planadmin_notices');
        function vms_render_event_planadmin_notices(): void
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
        function vms_event_plan_vendor_exists(int $vendor_id): bool
        {
            if ($vendor_id <= 0) return false;

            $p = get_post($vendor_id);
            if (!$p) return false;
            if ($p->post_type !== 'vms_vendor') return false;
            if ($p->post_status === 'trash') return false;

            return true;
        }

		if (!function_exists('vms_event_plan_normalize_secondary_vendor_type_slug')) {
			function vms_event_plan_normalize_secondary_vendor_type_slug(string $type_slug): string
			{
				return function_exists('vms_vendor_type_normalize_slug')
					? vms_vendor_type_normalize_slug($type_slug)
					: sanitize_title($type_slug);
			}
		}

		if (!function_exists('vms_event_plan_get_current_primary_lineup_vendor_id')) {
			function vms_event_plan_get_current_primary_lineup_vendor_id(int $post_id, int $fallback_vendor_id = 0): int
			{
				$post_id = absint($post_id);
				$fallback_vendor_id = absint($fallback_vendor_id);
				if ($post_id <= 0) {
					return $fallback_vendor_id;
				}

				$entries = function_exists('vms_get_event_plan_lineup_entries')
					? (array) vms_get_event_plan_lineup_entries($post_id)
					: array();
				if (empty($entries)) {
					$lineup_key = function_exists('vms_lineup_schedule_meta_key')
						? vms_lineup_schedule_meta_key('lineup_entries_v1', '_vms_lineup_entries_v1')
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

		if (!function_exists('vms_event_plan_resolve_primary_vendor_submission')) {
			function vms_event_plan_resolve_primary_vendor_submission(int $post_id, array $request): array
			{
				$post_id = absint($post_id);
				$current_vendor_id = (int) get_post_meta($post_id, '_vms_band_vendor_id', true);
				$current_lineup_primary_vendor_id = vms_event_plan_get_current_primary_lineup_vendor_id($post_id, $current_vendor_id);
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

		if (!function_exists('vms_event_plan_normalize_secondary_vendor_ids')) {
			function vms_event_plan_normalize_secondary_vendor_ids(int $post_id, string $type_slug, array $secondary_ids, int $primary_vendor_id = 0): array
			{
                $post_id = absint($post_id);
                $type_slug = vms_event_plan_normalize_secondary_vendor_type_slug($type_slug);
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

                    if (function_exists('vms_event_plan_vendor_exists')) {
                        if (!vms_event_plan_vendor_exists($vendor_id)) {
                            continue;
                        }
                    } else {
                        $vendor_post = get_post($vendor_id);
                        if (!$vendor_post || $vendor_post->post_type !== 'vms_vendor' || $vendor_post->post_status === 'trash') {
                            continue;
                        }
                    }

                    if ($type_slug !== '') {
                        $matches_type = function_exists('vms_vendor_has_type')
                            ? vms_vendor_has_type($vendor_id, $type_slug)
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

		if (!function_exists('vms_event_plan_resolve_secondary_vendor_submission')) {
			function vms_event_plan_resolve_secondary_vendor_submission(int $post_id, array $request): array
			{
				$post_id = absint($post_id);
				$current_state = function_exists('vms_event_plan_get_secondary_vendor_state')
					? vms_event_plan_get_secondary_vendor_state($post_id)
					: array(
						'primary_vendor_id' => (int) get_post_meta($post_id, '_vms_band_vendor_id', true),
						'secondary_vendor_type' => sanitize_key((string) get_post_meta($post_id, '_vms_secondary_vendor_type', true)),
						'secondary_vendor_ids' => array_values(array_unique(array_filter(array_map('absint', (array) get_post_meta($post_id, '_vms_secondary_vendor_ids', true))))),
						'linked_tec_event_id' => 0,
					);
				$current_type = vms_event_plan_normalize_secondary_vendor_type_slug((string) ($current_state['secondary_vendor_type'] ?? ''));
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
					$posted_type = vms_event_plan_normalize_secondary_vendor_type_slug((string) wp_unslash((string) $request['vms_secondary_vendor_type']));
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

		if (!function_exists('vms_event_plan_get_secondary_vendor_state')) {
			function vms_event_plan_get_secondary_vendor_state(int $post_id): array
			{
                $post_id = absint($post_id);
                $k_band = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'band_vendor_id') ?: '_vms_band_vendor_id') : '_vms_band_vendor_id';
                $k_secondary_ids = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'secondary_vendor_ids') ?: '_vms_secondary_vendor_ids') : '_vms_secondary_vendor_ids';
                $k_secondary_type = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'secondary_vendor_type') ?: '_vms_secondary_vendor_type') : '_vms_secondary_vendor_type';
                $k_tec_event_id = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'tec_event_id') ?: '_vms_tec_event_id') : '_vms_tec_event_id';

                $primary_vendor_id = (int) get_post_meta($post_id, $k_band, true);
                $secondary_type = vms_event_plan_normalize_secondary_vendor_type_slug((string) get_post_meta($post_id, $k_secondary_type, true));
                $secondary_ids = get_post_meta($post_id, $k_secondary_ids, true);
                if (!is_array($secondary_ids)) {
                    $secondary_ids = array();
                }

                return array(
                    'primary_vendor_id' => $primary_vendor_id,
                    'secondary_vendor_type' => $secondary_type,
                    'secondary_vendor_ids' => vms_event_plan_normalize_secondary_vendor_ids($post_id, $secondary_type, $secondary_ids, $primary_vendor_id),
                    'linked_tec_event_id' => (int) get_post_meta($post_id, $k_tec_event_id, true),
                );
            }
        }

        if (!function_exists('vms_event_plan_secondary_vendor_state_diff_fields')) {
            function vms_event_plan_secondary_vendor_state_diff_fields(array $before, array $after, array $fields = array()): array
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

        if (!function_exists('vms_event_plan_secondary_vendor_rebuild_repair_reasons')) {
            function vms_event_plan_secondary_vendor_rebuild_repair_reasons(int $post_id, array $current_state = array()): array
            {
                $post_id = absint($post_id);
                if ($post_id <= 0) {
                    return array();
                }

                if (empty($current_state)) {
                    $current_state = vms_event_plan_get_secondary_vendor_state($post_id);
                }

                $k_secondary_ids = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'secondary_vendor_ids') ?: '_vms_secondary_vendor_ids') : '_vms_secondary_vendor_ids';
                $k_secondary_idx = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'secondary_vendor_id') ?: '_vms_secondary_vendor_id') : '_vms_secondary_vendor_id';
                $k_snapshot = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'vendor_category_snapshot') ?: '_vms_vendor_category_snapshot') : '_vms_vendor_category_snapshot';
                $k_issue = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'integrity_issue') ?: '_vms_integrity_issue') : '_vms_integrity_issue';

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

        if (!function_exists('vms_event_plan_set_secondary_vendors')) {
            /**
             * Canonical secondary-vendor assignment writer.
             *
             * Mirrors the Event Plan editor save behavior so external workflows can
             * assign vendors without inventing a second storage path.
             *
             * @return array<string,mixed>|WP_Error
             */
            function vms_event_plan_set_secondary_vendors(int $post_id, string $type_slug, array $secondary_ids)
            {
                $post_id = absint($post_id);
                if ($post_id <= 0 || get_post_type($post_id) !== 'vms_event_plan') {
                    return new WP_Error('vms_event_plan_invalid', __('Event Plan could not be found for secondary-vendor assignment.', 'backstage-venue-manager'));
                }

                $k_secondary_ids     = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'secondary_vendor_ids') : '_vms_secondary_vendor_ids';
                $k_secondary_idx     = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'secondary_vendor_id') : '_vms_secondary_vendor_id';
                $k_secondary_type    = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'secondary_vendor_type') : '_vms_secondary_vendor_type';
                $k_secondary_unq     = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'secondary_vendor_unqualified') : '_vms_secondary_vendor_unqualified';
                $k_secondary_unq_ids = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'secondary_vendor_unqualified_ids') : '_vms_secondary_vendor_unqualified_ids';

                $type_slug = function_exists('vms_vendor_type_normalize_slug')
                    ? vms_vendor_type_normalize_slug($type_slug)
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

                    if (function_exists('vms_event_plan_vendor_exists')) {
                        if (!vms_event_plan_vendor_exists($vendor_id)) {
                            continue;
                        }
                    } else {
                        $vendor_post = get_post($vendor_id);
                        if (!$vendor_post || $vendor_post->post_type !== 'vms_vendor' || $vendor_post->post_status === 'trash') {
                            continue;
                        }
                    }

                    if ($type_slug !== '') {
                        $matches_type = function_exists('vms_vendor_has_type')
                            ? vms_vendor_has_type($vendor_id, $type_slug)
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
                    if (function_exists('vms_secondary_vendor_is_qualified')) {
                        $ok = vms_secondary_vendor_is_qualified((int) $vendor_id, array(
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

                if (function_exists('vms_event_plan_clear_integrity_flags')) {
                    $k_issue = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'integrity_issue') : '_vms_integrity_issue';
                    $issue_now = (string) get_post_meta($post_id, $k_issue, true);
                    if ($issue_now === 'missing_secondary_vendor') {
                        vms_event_plan_clear_integrity_flags($post_id);
                    }
                }

                return array(
                    'type_slug' => $type_slug,
                    'secondary_ids' => $valid_secondary,
                    'unqualified_ids' => $unq_ids,
                );
            }
        }

        if (!function_exists('vms_event_plan_save_secondary_vendors_module')) {
            function vms_event_plan_save_secondary_vendors_module(int $post_id, array $request)
            {
                $post_id = absint($post_id);
                if ($post_id <= 0 || get_post_type($post_id) !== 'vms_event_plan') {
                    return new WP_Error('vms_event_plan_invalid', __('Event Plan could not be found for Additional Vendors save.', 'backstage-venue-manager'));
                }

                $current_vendor_state = function_exists('vms_event_plan_get_secondary_vendor_state')
                    ? vms_event_plan_get_secondary_vendor_state($post_id)
                    : array(
                        'primary_vendor_id' => (int) get_post_meta($post_id, '_vms_band_vendor_id', true),
                        'secondary_vendor_type' => sanitize_key((string) get_post_meta($post_id, '_vms_secondary_vendor_type', true)),
                        'secondary_vendor_ids' => array_values(array_unique(array_filter(array_map('absint', (array) get_post_meta($post_id, '_vms_secondary_vendor_ids', true))))),
                        'linked_tec_event_id' => (int) get_post_meta($post_id, '_vms_tec_event_id', true),
                    );
                $secondary_vendor_submission = function_exists('vms_event_plan_resolve_secondary_vendor_submission')
                    ? vms_event_plan_resolve_secondary_vendor_submission($post_id, $request)
                    : array(
                        'current_state' => $current_vendor_state,
                        'type_slug' => (string) ($current_vendor_state['secondary_vendor_type'] ?? ''),
                        'secondary_ids' => (array) ($current_vendor_state['secondary_vendor_ids'] ?? array()),
                        'clear_requested' => !empty($request['vms_clear_secondary_vendors']),
                    );

                $type_slug = function_exists('vms_event_plan_normalize_secondary_vendor_type_slug')
                    ? vms_event_plan_normalize_secondary_vendor_type_slug((string) ($secondary_vendor_submission['type_slug'] ?? ''))
                    : sanitize_key((string) ($secondary_vendor_submission['type_slug'] ?? ''));
                $valid_secondary = function_exists('vms_event_plan_normalize_secondary_vendor_ids')
                    ? vms_event_plan_normalize_secondary_vendor_ids(
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
                $vendor_dirty_fields = function_exists('vms_event_plan_secondary_vendor_state_diff_fields')
                    ? vms_event_plan_secondary_vendor_state_diff_fields(
                        $current_vendor_state,
                        $proposed_vendor_state,
                        array('secondary_vendor_type', 'secondary_vendor_ids')
                    )
                    : array();
                $repair_reasons = function_exists('vms_event_plan_secondary_vendor_rebuild_repair_reasons')
                    ? vms_event_plan_secondary_vendor_rebuild_repair_reasons($post_id, $current_vendor_state)
                    : array();
                $changed = !empty($vendor_dirty_fields) || !empty($repair_reasons);
                $queued_calendar_maintenance = false;

                if ($changed) {
                    $write_result = function_exists('vms_event_plan_set_secondary_vendors')
                        ? vms_event_plan_set_secondary_vendors($post_id, $type_slug, $valid_secondary)
                        : array(
                            'type_slug' => $type_slug,
                            'secondary_ids' => $valid_secondary,
                            'unqualified_ids' => array(),
                        );
                    if (is_wp_error($write_result)) {
                        return $write_result;
                    }

                    if (function_exists('vms_event_plan_update_vendor_category_snapshot')) {
                        vms_event_plan_update_vendor_category_snapshot($post_id);
                    }

                    if ($linked_tec_event_id > 0 && function_exists('vms_event_plan_schedule_calendar_maintenance')) {
                        vms_event_plan_schedule_calendar_maintenance($post_id, $linked_tec_event_id, 'vendor_category_sync');
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


        function vms_event_plan_get_venue_state(int $venue_id): string
        {
            if ($venue_id <= 0) return 'none';

            $p = get_post($venue_id);
            if (!$p || $p->post_type !== 'vms_venue') return 'missing';
            if ($p->post_status === 'trash') return 'trashed';
            if ($p->post_status !== 'publish') return 'unpublished';
            return 'ok';
        }

        function vms_event_plan_flag_missing_vendor(int $plan_id, int $vendor_id, string $vendor_title = ''): void
        {
            if ($plan_id <= 0) return;

            $k_issue = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'integrity_issue') : '_vms_integrity_issue';
            $k_vid   = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'integrity_vendor_id') : '_vms_integrity_vendor_id';
            $k_vt    = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'integrity_vendor_title') : '_vms_integrity_vendor_title';
            $k_ts    = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'integrity_ts') : '_vms_integrity_ts';
            $k_venue_id = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'integrity_venue_id') ?: '_vms_integrity_venue_id') : '_vms_integrity_venue_id';
            $k_venue_t  = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'integrity_venue_title') ?: '_vms_integrity_venue_title') : '_vms_integrity_venue_title';

                    $k_venue_id = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'integrity_venue_id') ?: '_vms_integrity_venue_id') : '_vms_integrity_venue_id';
            $k_venue_t  = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'integrity_venue_title') ?: '_vms_integrity_venue_title') : '_vms_integrity_venue_title';
        $k_venue_id = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'integrity_venue_id') ?: '_vms_integrity_venue_id') : '_vms_integrity_venue_id';
            $k_venue_t  = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'integrity_venue_title') ?: '_vms_integrity_venue_title') : '_vms_integrity_venue_title';

        update_post_meta($plan_id, $k_issue, 'missing_vendor');
            update_post_meta($plan_id, $k_vid, (int) $vendor_id);
            if ($vendor_title !== '') update_post_meta($plan_id, $k_vt, (string) $vendor_title);
            update_post_meta($plan_id, $k_ts, (string) wp_date('Y-m-d H:i:s'));
        }

        function vms_event_plan_flag_missing_secondary_vendor(int $plan_id, int $vendor_id, string $vendor_title = ''): void
        {
            if ($plan_id <= 0) return;

            $k_issue = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'integrity_issue') : '_vms_integrity_issue';
            $k_vid   = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'integrity_vendor_id') : '_vms_integrity_vendor_id';
            $k_vt    = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'integrity_vendor_title') : '_vms_integrity_vendor_title';
            $k_ts    = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'integrity_ts') : '_vms_integrity_ts';
            $k_venue_id = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'integrity_venue_id') ?: '_vms_integrity_venue_id') : '_vms_integrity_venue_id';
            $k_venue_t  = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'integrity_venue_title') ?: '_vms_integrity_venue_title') : '_vms_integrity_venue_title';

            update_post_meta($plan_id, $k_issue, 'missing_secondary_vendor');
            update_post_meta($plan_id, $k_vid, (int) $vendor_id);
            if ($vendor_title !== '') update_post_meta($plan_id, $k_vt, (string) $vendor_title);
            update_post_meta($plan_id, $k_ts, (string) wp_date('Y-m-d H:i:s'));
        }



        function vms_event_plan_flag_trashed_vendor(int $plan_id, int $vendor_id, string $vendor_title = ''): void
        {
            if ($plan_id <= 0 || $vendor_id <= 0) return;

            $k_issue = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'integrity_issue') : '_vms_integrity_issue';
            $k_vid   = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'integrity_vendor_id') : '_vms_integrity_vendor_id';
            $k_vt    = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'integrity_vendor_title') : '_vms_integrity_vendor_title';
            $k_ts    = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'integrity_ts') : '_vms_integrity_ts';

            update_post_meta($plan_id, $k_issue, 'trashed_vendor');
            update_post_meta($plan_id, $k_vid, $vendor_id);
            if ($vendor_title !== '') update_post_meta($plan_id, $k_vt, $vendor_title);
            update_post_meta($plan_id, $k_ts, time());
        }

        function vms_event_plan_flag_trashed_secondary_vendor(int $plan_id, int $vendor_id, string $vendor_title = ''): void
        {
            if ($plan_id <= 0 || $vendor_id <= 0) return;

            $k_issue = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'integrity_issue') : '_vms_integrity_issue';
            $k_vid   = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'integrity_vendor_id') : '_vms_integrity_vendor_id';
            $k_vt    = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'integrity_vendor_title') : '_vms_integrity_vendor_title';
            $k_ts    = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'integrity_ts') : '_vms_integrity_ts';

            update_post_meta($plan_id, $k_issue, 'trashed_secondary_vendor');
            update_post_meta($plan_id, $k_vid, $vendor_id);
            if ($vendor_title !== '') update_post_meta($plan_id, $k_vt, $vendor_title);
            update_post_meta($plan_id, $k_ts, time());
        }

        function vms_event_plan_flag_missing_venue(int $plan_id, int $venue_id, string $venue_title = ''): void
        {
            if ($plan_id <= 0 || $venue_id <= 0) return;

            $k_issue = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'integrity_issue') : '_vms_integrity_issue';
            $k_venue = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'integrity_venue_id') ?: '_vms_integrity_venue_id') : '_vms_integrity_venue_id';
            $k_vt    = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'integrity_venue_title') ?: '_vms_integrity_venue_title') : '_vms_integrity_venue_title';
            $k_ts    = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'integrity_ts') : '_vms_integrity_ts';

            update_post_meta($plan_id, $k_issue, 'missing_venue');
            update_post_meta($plan_id, $k_venue, $venue_id);
            if ($venue_title !== '') update_post_meta($plan_id, $k_vt, $venue_title);
            update_post_meta($plan_id, $k_ts, time());
        }

        function vms_event_plan_flag_trashed_venue(int $plan_id, int $venue_id, string $venue_title = ''): void
        {
            if ($plan_id <= 0 || $venue_id <= 0) return;

            $k_issue = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'integrity_issue') : '_vms_integrity_issue';
            $k_venue = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'integrity_venue_id') ?: '_vms_integrity_venue_id') : '_vms_integrity_venue_id';
            $k_vt    = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'integrity_venue_title') ?: '_vms_integrity_venue_title') : '_vms_integrity_venue_title';
            $k_ts    = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'integrity_ts') : '_vms_integrity_ts';

            update_post_meta($plan_id, $k_issue, 'trashed_venue');
            update_post_meta($plan_id, $k_venue, $venue_id);
            if ($venue_title !== '') update_post_meta($plan_id, $k_vt, $venue_title);
            update_post_meta($plan_id, $k_ts, time());
        }

        function vms_event_plan_flag_venue_unpublished(int $plan_id, int $venue_id, string $venue_title = ''): void
        {
            if ($plan_id <= 0 || $venue_id <= 0) return;

            $k_issue = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'integrity_issue') : '_vms_integrity_issue';
            $k_venue = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'integrity_venue_id') ?: '_vms_integrity_venue_id') : '_vms_integrity_venue_id';
            $k_vt    = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'integrity_venue_title') ?: '_vms_integrity_venue_title') : '_vms_integrity_venue_title';
            $k_ts    = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'integrity_ts') : '_vms_integrity_ts';

            update_post_meta($plan_id, $k_issue, 'venue_unpublished');
            update_post_meta($plan_id, $k_venue, $venue_id);
            if ($venue_title !== '') update_post_meta($plan_id, $k_vt, $venue_title);
            update_post_meta($plan_id, $k_ts, time());
        }
        function vms_event_plan_clear_integrity_flags(int $plan_id): void
        {
            if ($plan_id <= 0) return;

            $k_issue = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'integrity_issue') : '_vms_integrity_issue';
            $k_vid   = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'integrity_vendor_id') : '_vms_integrity_vendor_id';
            $k_vt    = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'integrity_vendor_title') : '_vms_integrity_vendor_title';
            $k_ts    = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'integrity_ts') : '_vms_integrity_ts';
            $k_venue_id = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'integrity_venue_id') ?: '_vms_integrity_venue_id') : '_vms_integrity_venue_id';
            $k_venue_t  = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'integrity_venue_title') ?: '_vms_integrity_venue_title') : '_vms_integrity_venue_title';

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
        if (!function_exists('vms_integrity_schedule_daily_scan')) {
            function vms_integrity_schedule_daily_scan(): void
            {
                if (function_exists('vms_should_run_runtime_maintenance') && !vms_should_run_runtime_maintenance()) {
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
        add_action('init', 'vms_integrity_schedule_daily_scan');



        function vms_integrity_scan_event_plans_for_orphaned_venues(int $limit = 500): array
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

        	$k_status = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'status') ?: '_vms_event_plan_status') : '_vms_event_plan_status';

        	foreach ($event_plan_ids as $event_plan_id) {
        		$results['checked']++;

        		$venue_id = (int) get_post_meta($event_plan_id, '_vms_venue_id', true);
        		if ($venue_id <= 0) continue;

        		$state = vms_event_plan_get_venue_state($venue_id);
        		$needs_force_draft = false;

        		if ($state === 'missing') {
        			vms_event_plan_flag_missing_venue($event_plan_id, $venue_id, '');
        			update_post_meta($event_plan_id, '_vms_venue_id', 0);
        			$results['cleared_venue_refs']++;
        			$results['flagged_missing_venue']++;
        			$needs_force_draft = true;
        		} elseif ($state === 'trashed') {
        			$vp = get_post($venue_id);
        			$title = ($vp && !empty($vp->post_title)) ? $vp->post_title : '';
        			vms_event_plan_flag_trashed_venue($event_plan_id, $venue_id, $title);
        			$results['flagged_trashed_venue']++;
        			$needs_force_draft = true;
        		} elseif ($state === 'unpublished') {
        			$vp = get_post($venue_id);
        			$title = ($vp && !empty($vp->post_title)) ? $vp->post_title : '';
        			vms_event_plan_flag_venue_unpublished($event_plan_id, $venue_id, $title);
        			$results['flagged_venue_unpublished']++;
        			$needs_force_draft = true;
        		}

        		if ($needs_force_draft) {
        			$internal_status = get_post_meta($event_plan_id, $k_status, true);
        			if ($internal_status === 'published' || $internal_status === 'ready') {
        				update_post_meta($event_plan_id, $k_status, 'draft');

        				$p = get_post($event_plan_id);
        				if ($p && $p->post_status !== 'draft') {
        					vms_event_plan_perf_wp_update_post(array('ID' => $event_plan_id, 'post_status' => 'draft'), 'event_plan_force_draft_scan_vendor_or_venue', $event_plan_id);
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
        function vms_integrity_list_event_plans_with_venue_issues(int $limit = 500): array
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

        		$state = vms_event_plan_get_venue_state($venue_id);
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



        function vms_event_plan_calendar_unpublished_suppressed(int $plan_id): bool
        {
        	$k = function_exists('vms_meta_key')
        		? (vms_meta_key('event_plan', 'calendar_unpublished_suppress') ?: '_vms_calendar_unpublished_suppress')
        		: '_vms_calendar_unpublished_suppress';

        	$v = (string) get_post_meta($plan_id, $k, true);
        	return in_array($v, array('1', 'yes', 'true'), true);
        }

        function vms_integrity_calendar_unpublished_applies_for_status(string $internal_status): bool
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


        function vms_event_plan_get_calendar_event_state(int $tec_event_id): string
        {
        	if ($tec_event_id <= 0) return 'none';

        	$p = get_post($tec_event_id);
        	if (!$p || $p->post_type !== 'tribe_events') return 'missing';
        	if ($p->post_status === 'trash') return 'trashed';

        	// TEC events can be Scheduled (future). Treat publish and future as OK.
        	if (!in_array($p->post_status, array('publish', 'future'), true)) return 'unpublished';

        	return 'ok';
        }

        function vms_integrity_list_event_plans_with_calendar_issues(int $limit = 500): array
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

        	$k_status = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'status') ?: '_vms_event_plan_status') : '_vms_event_plan_status';

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

        		$state = vms_event_plan_get_calendar_event_state($tec_event_id);

        		if ($state === 'unpublished') {
        			if (!vms_integrity_calendar_unpublished_applies_for_status($internal_status)) {
        				continue;
        			}
        			if (vms_event_plan_calendar_unpublished_suppressed($pid)) {
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



        function vms_integrity_scan_event_plans_for_orphaned_calendar_events(int $limit = 500): array
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

        	$k_status = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'status') ?: '_vms_event_plan_status') : '_vms_event_plan_status';
        	$k_issue  = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'integrity_issue') : '_vms_integrity_issue';
        	$k_ts     = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'integrity_ts') : '_vms_integrity_ts';

        	foreach ($event_plan_ids as $event_plan_id) {
        		$results['checked']++;

        		$internal_status = (string) get_post_meta($event_plan_id, $k_status, true);
        		$force_if_needed = ($internal_status === 'published' || $internal_status === 'ready');

        		$tec_event_id  = (int) get_post_meta($event_plan_id, '_vms_tec_event_id', true);
        		$tec_event_url = (string) get_post_meta($event_plan_id, '_vms_tec_event_url', true);

        		$existing_issue = (string) get_post_meta($event_plan_id, $k_issue, true);
        		$unpub_applies = vms_integrity_calendar_unpublished_applies_for_status($internal_status);
        		$unpub_suppressed = vms_event_plan_calendar_unpublished_suppressed((int) $event_plan_id);

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
        				vms_event_plan_perf_wp_update_post(array('ID' => $event_plan_id, 'post_status' => 'draft'), 'event_plan_force_draft_integrity_scan', $event_plan_id);
        			}

        			$results['forced_draft']++;
        		}
        	}

        	return $results;
        }

        function vms_integrity_scan_event_plans_all(int $limit = 500): array
        {
        	return array(
        		'vendors' => vms_integrity_scan_event_plans_for_missing_vendors($limit),
        		'venues'  => vms_integrity_scan_event_plans_for_orphaned_venues($limit),
        		'events' => vms_integrity_scan_event_plans_for_orphaned_calendar_events($limit),
        	);
        }

        add_action('vms_integrity_daily_scan', 'vms_integrity_scan_event_plans_for_missing_vendors');
        add_action('vms_integrity_daily_scan', 'vms_integrity_scan_event_plans_for_orphaned_venues');

        function vms_integrity_scan_event_plans_for_missing_vendors(int $limit = 500): array
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

        	$k_status = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'status') ?: '_vms_event_plan_status') : '_vms_event_plan_status';

        	foreach ($event_plan_ids as $event_plan_id) {
        		$results['checked']++;

        		$needs_force_draft = false;

        		// Band vendor check
        		$band_vendor_id = (int) get_post_meta($event_plan_id, '_vms_band_vendor_id', true);
        		if ($band_vendor_id > 0) {
        			$vendor_post = get_post($band_vendor_id);

        			if (!$vendor_post || $vendor_post->post_type !== 'vms_vendor') {
        				// Permanently deleted or invalid reference: clear it
        				vms_event_plan_flag_missing_vendor($event_plan_id, $band_vendor_id, '');
        				update_post_meta($event_plan_id, '_vms_band_vendor_id', 0);
        				$needs_force_draft = true;
        				$results['flagged_missing_vendor']++;
        			} elseif ($vendor_post->post_status === 'trash') {
        				// Trashed vendor: keep the reference so it can recover if restored
        				vms_event_plan_flag_trashed_vendor($event_plan_id, $band_vendor_id, $vendor_post->post_title);
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

        			vms_event_plan_flag_missing_secondary_vendor($event_plan_id, $removed_ids, $removed_titles);
        			$needs_force_draft = true;
        			$results['flagged_missing_secondary_vendor']++;
        			$results['removed_missing_secondary_vendor_ids'] += count($removed_ids);
        		}

        		if ($first_trashed_secondary !== null) {
        			vms_event_plan_flag_trashed_secondary_vendor($event_plan_id, (int) $first_trashed_secondary['id'], (string) $first_trashed_secondary['title']);
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
        					vms_event_plan_perf_wp_update_post(array('ID' => $event_plan_id, 'post_status' => 'draft'), 'event_plan_force_draft_secondary_vendor_scan', $event_plan_id);
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

            $post_id = isset($_GET['post']) ? absint($_GET['post']) : 0;
            $action  = isset($_GET['action']) ? sanitize_key((string) $_GET['action']) : '';
            if ($post_id <= 0 || $action !== 'edit') return;

            $post = get_post($post_id);
            if (!$post || $post->post_type !== 'vms_event_plan') return;
            if (!current_user_can('edit_post', $post_id)) return;

            $k_band = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'band_vendor_id') : '_vms_band_vendor_id';
            $band_id = (int) get_post_meta($post_id, $k_band, true);

            if ($band_id > 0 && function_exists('vms_event_plan_vendor_exists') && !vms_event_plan_vendor_exists($band_id)) {
                // Flag + revert (non-destructive; does not touch TEC events automatically).
                if (function_exists('vms_event_plan_flag_missing_vendor')) {
                    vms_event_plan_flag_missing_vendor($post_id, $band_id, '');
                }

                // Clear the broken pointer so the UI doesn't keep referencing a dead vendor ID.
                update_post_meta($post_id, $k_band, 0);

                // Revert plan workflow status.
                $k_status = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'status') : '_vms_event_plan_status';
                update_post_meta($post_id, $k_status, 'draft');

                // Revert WP status if it was published.
                if ($post->post_status === 'publish') {
                    vms_event_plan_perf_wp_update_post(array('ID' => $post_id, 'post_status' => 'draft'), 'event_plan_force_draft_missing_primary_vendor_admin_guard', $post_id);
                }

                if (function_exists('vms_add_admin_notice')) {
                    vms_add_admin_notice(__('🚩 The selected Primary Vendor was deleted. This event plan was reverted to Draft. Review the event and choose a new Primary Vendor.', 'backstage-venue-manager'), 'error');
                }
            }

            // Secondary vendors safety net
            $k_secondary_ids = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'secondary_vendor_ids') : '_vms_secondary_vendor_ids';
            $k_secondary_idx = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'secondary_vendor_id') : '_vms_secondary_vendor_id';
            $sec_ids = get_post_meta($post_id, $k_secondary_ids, true);
            if (!is_array($sec_ids)) $sec_ids = array();
            $sec_ids = array_values(array_unique(array_filter(array_map('absint', $sec_ids), fn($v) => $v > 0)));

            if (!empty($sec_ids)) {
                $valid = array();
                $missing = array();
                foreach ($sec_ids as $vid) {
                    if (function_exists('vms_event_plan_vendor_exists') && vms_event_plan_vendor_exists((int) $vid)) {
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
                    $k_issue = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'integrity_issue') : '_vms_integrity_issue';
                    $issue_now = (string) get_post_meta($post_id, $k_issue, true);
                    if ($issue_now !== 'missing_vendor' && function_exists('vms_event_plan_flag_missing_secondary_vendor')) {
                        vms_event_plan_flag_missing_secondary_vendor($post_id, (int) $missing[0], '');
                    }

                    // Revert plan workflow status.
                    $k_status = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'status') : '_vms_event_plan_status';
                    update_post_meta($post_id, $k_status, 'draft');

                    // Revert WP status if it was published.
                    if ($post->post_status === 'publish') {
                        vms_event_plan_perf_wp_update_post(array('ID' => $post_id, 'post_status' => 'draft'), 'event_plan_force_draft_missing_secondary_vendor_admin_guard', $post_id);
                    }

                    if (function_exists('vms_add_admin_notice')) {
                        vms_add_admin_notice(__('🚩 A secondary vendor was deleted. This event plan was reverted to Draft. Review Additional Vendors and reassign as needed.', 'backstage-venue-manager'), 'error');
                    }
                }
            }
        });

        /**
         * List table: status pill column
         */
        add_filter('manage_vms_event_plan_posts_columns', 'vms_add_event_plan_status_column');
        function vms_add_event_plan_status_column(array $columns): array
        {
            $new = array();
            foreach ($columns as $key => $label) {
                if ($key === 'date') $new['vms_plan_status'] = __('Plan Status', 'backstage-venue-manager');
                $new[$key] = $label;
            }
            if (!isset($new['vms_plan_status'])) $new['vms_plan_status'] = __('Plan Status', 'backstage-venue-manager');
            return $new;
        }

        add_action('manage_vms_event_plan_posts_custom_column', 'vms_render_event_plan_status_column', 10, 2);
        function vms_render_event_plan_status_column(string $column, int $post_id): void
        {
            if ($column !== 'vms_plan_status') return;

            $status = function_exists('vms_event_plan_get_status')
                ? (string) vms_event_plan_get_status($post_id, 'event_list')
                : sanitize_key((string) get_post_meta($post_id, vms_meta_key('event_plan', 'status'), true));

            $status = sanitize_key((string) $status);
            if ($status === 'canceled') $status = 'cancelled';
            if ($status === '') $status = 'draft';

            $label = function_exists('vms_event_plan_status_label')
                ? (string) vms_event_plan_status_label($status)
                : ucwords(str_replace(array('_', '-'), ' ', $status));

            $class = function_exists('vms_event_plan_status_pill_class')
                ? (string) vms_event_plan_status_pill_class($status)
                : 'vms-pill-draft';

            echo '<span class="vms-status-pill ' . esc_attr($class) . '">' . esc_html($label) . '</span>';
        }

        add_filter('manage_vms_event_plan_posts_columns', 'vms_add_event_plan_event_date_column', 30);
        function vms_add_event_plan_event_date_column(array $columns): array
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

        function vms_admin_event_plan_list_event_date_meta_key(): string
        {
            $key = function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'date') : '';
            return $key !== '' ? $key : '_vms_event_date';
        }

        function vms_admin_event_plan_list_format_event_date_label(string $event_date): string
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

        function vms_admin_event_plan_list_format_start_time_label(string $start_time): string
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

        add_action('manage_vms_event_plan_posts_custom_column', 'vms_render_event_plan_event_date_column', 10, 2);
        function vms_render_event_plan_event_date_column(string $column, int $post_id): void
        {
            if ($column !== 'vms_event_date') return;

            $event_date = trim((string) get_post_meta($post_id, vms_admin_event_plan_list_event_date_meta_key(), true));
            if ($event_date === '') {
                echo '—';
                return;
            }

            $start_time = trim((string) get_post_meta($post_id, '_vms_start_time', true));
            $date_label = vms_admin_event_plan_list_format_event_date_label($event_date);
            $time_label = vms_admin_event_plan_list_format_start_time_label($start_time);

            echo '<strong>' . esc_html($date_label !== '' ? $date_label : $event_date) . '</strong>';
            if ($time_label !== '') {
                echo '<div class="description">' . esc_html($time_label) . '</div>';
            }
        }

        add_filter('manage_edit-vms_event_plan_sortable_columns', 'vms_add_event_plan_event_date_sortable_column');
        function vms_add_event_plan_event_date_sortable_column(array $columns): array
        {
            $columns['vms_event_date'] = 'vms_event_date';
            return $columns;
        }

        add_action('pre_get_posts', 'vms_admin_event_plan_list_maybe_sort_by_event_date', 60);
        function vms_admin_event_plan_list_maybe_sort_by_event_date($query): void
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

        add_filter('posts_clauses', 'vms_admin_event_plan_list_event_date_sort_clauses', 10, 2);
        function vms_admin_event_plan_list_event_date_sort_clauses(array $clauses, WP_Query $query): array
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
                    vms_admin_event_plan_list_event_date_meta_key()
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
        add_filter('manage_vms_event_plan_posts_columns', 'vms_add_event_plan_tax_column', 20);
        function vms_add_event_plan_tax_column(array $columns): array
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

        add_action('manage_vms_event_plan_posts_custom_column', 'vms_render_event_plan_tax_column', 10, 2);
        function vms_render_event_plan_tax_column(string $column, int $post_id): void
        {
            if ($column !== 'vms_plan_tax') return;

            $wp_status = (string) get_post_status($post_id);

            $plan_status = function_exists('vms_event_plan_get_status')
                ? (string) vms_event_plan_get_status($post_id, 'event_list')
                : sanitize_key((string) get_post_meta($post_id, vms_meta_key('event_plan', 'status'), true));

            $plan_status = sanitize_key((string) $plan_status);

            $is_published = ($wp_status === 'publish' || $plan_status === 'published');
            if (!$is_published) return;

            $k_band_vendor_id = function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'band_vendor_id') : '';
            if ($k_band_vendor_id === '') $k_band_vendor_id = '_vms_band_vendor_id';

            $vendor_id = absint(get_post_meta($post_id, $k_band_vendor_id, true));
            if ($vendor_id <= 0) return;

            $tax_missing = false;
            if (function_exists('vms_is_vendor_tax_profile_complete')) {
                $tax_missing = !vms_is_vendor_tax_profile_complete($vendor_id);
            }

            $st_bypass = function_exists('vms_get_tax_bypass_status') ? (array) vms_get_tax_bypass_status($vendor_id) : array();
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
        if (!function_exists('vms_event_plan_legacy_ticket_meta_keys')) {
            function vms_event_plan_legacy_ticket_meta_keys(): array
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

        if (!function_exists('vms_event_plan_ticketing_v2_config_meta_key')) {
            function vms_event_plan_ticketing_v2_config_meta_key(): string
            {
                if (function_exists('vms_ticketing_v2_k')) {
                    return (string) vms_ticketing_v2_k('config');
                }
                if (function_exists('vms_meta_key')) {
                    $key = (string) vms_meta_key('event_plan', 'ticketing_config_v2');
                    if ($key !== '') {
                        return $key;
                    }
                }
                return '_vms_ticketing_config_v2';
            }
        }

        if (!function_exists('vms_event_plan_has_ticketing_v2_config')) {
            function vms_event_plan_has_ticketing_v2_config(int $plan_id): bool
            {
                $plan_id = absint($plan_id);
                if ($plan_id <= 0) {
                    return false;
                }

                $cfg_key = vms_event_plan_ticketing_v2_config_meta_key();
                if (!metadata_exists('post', $plan_id, $cfg_key)) {
                    return false;
                }

                $raw = get_post_meta($plan_id, $cfg_key, true);
                return is_array($raw) && !empty($raw);
            }
        }

        if (!function_exists('vms_event_plan_maybe_cleanup_legacy_ticket_meta_for_plan')) {
            /**
             * @return array{cleaned:bool,deleted_keys:int,template_applied:bool,reason:string}
             */
            function vms_event_plan_maybe_cleanup_legacy_ticket_meta_for_plan(int $plan_id, bool $allow_apply_default_template = true): array
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
                foreach (vms_event_plan_legacy_ticket_meta_keys() as $legacy_key) {
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

                $has_v2_config = vms_event_plan_has_ticketing_v2_config($plan_id);
                if (!$has_v2_config && $allow_apply_default_template) {
                    if (
                        function_exists('vms_ticketing_v2_get_default_template_id')
                        && function_exists('vms_ticketing_v2_templates_apply_to_plan')
                    ) {
                        $template_id = (string) vms_ticketing_v2_get_default_template_id();
                        if ($template_id !== '') {
                            $apply_result = vms_ticketing_v2_templates_apply_to_plan($plan_id, $template_id);
                            if (!empty($apply_result['ok'])) {
                                $has_v2_config = vms_event_plan_has_ticketing_v2_config($plan_id);
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

		if (!function_exists('vms_event_plan_legacy_ticket_meta_candidate_ids')) {
			function vms_event_plan_legacy_ticket_meta_candidate_ids(int $after_id, int $limit): array
			{
				global $wpdb;

				$after_id = max(0, (int) $after_id);
				$limit = max(1, min(200, (int) $limit));

				$legacy_keys = array_values(array_filter(array_map('strval', vms_event_plan_legacy_ticket_meta_keys())));
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
				$rows = $wpdb->get_col($wpdb->prepare($sql, ...$params));
				if (!is_array($rows) || empty($rows)) {
					return array();
				}

				return array_values(array_filter(array_map('absint', $rows)));
			}
		}

		if (!function_exists('vms_event_plan_cleanup_legacy_ticket_meta_once')) {
			function vms_event_plan_cleanup_legacy_ticket_meta_once(): void
			{
				$is_cron = defined('DOING_CRON') && DOING_CRON;
				if (!$is_cron) {
					if (!is_admin() || !current_user_can('manage_options')) {
						return;
					}
                    if (defined('DOING_AJAX') && DOING_AJAX) {
                        return;
                    }
					if (!empty($_POST)) {
						return;
					}
				}

				$target_version = defined('VMS_VERSION') ? (string) VMS_VERSION : 'dev';
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
					$ids = vms_event_plan_legacy_ticket_meta_candidate_ids($cursor, $batch_size);
					if (empty($ids)) {
						update_option($marker_option, $target_version, false);
						$completed = true;
						$summary['completed_at_gmt'] = gmdate('c');
						update_option('vms_event_plan_legacy_ticket_cleanup_last_run', $summary, false);
						delete_option($cursor_option);
						delete_option($progress_option);
						error_log(sprintf(
							'[VMS] Legacy ticket meta cleanup complete: version=%s scanned=%d cleaned=%d deleted_keys=%d template_applied=%d skipped_no_v2=%d',
							$target_version,
							(int) $summary['scanned'],
							(int) $summary['cleaned_plans'],
							(int) $summary['deleted_keys'],
							(int) $summary['template_applied'],
							(int) $summary['skipped_no_v2_config']
						));
						break;
					}

					foreach ($ids as $plan_id) {
						$plan_id = absint($plan_id);
						if ($plan_id <= 0) {
							continue;
						}
						$cursor = max($cursor, $plan_id);
						$summary['scanned'] += 1;

						$result = vms_event_plan_maybe_cleanup_legacy_ticket_meta_for_plan($plan_id, true);
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

		if (!function_exists('vms_event_plan_maybe_schedule_legacy_ticket_cleanup')) {
			function vms_event_plan_maybe_schedule_legacy_ticket_cleanup(): void
			{
				if (defined('DOING_AJAX') && DOING_AJAX) {
					return;
				}

				// Never spend admin-editor CPU on migration cleanup during a save/publish request.
				if (!empty($_POST) || (isset($_GET['action'], $_GET['post']) && (string) $_GET['action'] === 'edit')) {
					return;
				}

				$target_version = defined('VMS_VERSION') ? (string) VMS_VERSION : 'dev';
				if ((string) get_option('vms_event_plan_legacy_ticket_cleanup_version', '') === $target_version) {
					return;
				}

				$guard = function_exists('vms_admin_guard_begin')
					? vms_admin_guard_begin('admin_init.event_plan_legacy_ticket_cleanup_schedule', array(
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
					if (is_array($guard) && function_exists('vms_admin_guard_finish')) {
						vms_admin_guard_finish($guard, array('scheduled_cleanup' => $scheduled ? 1 : 0));
					}
				}
			}
		}
        add_action('admin_init', 'vms_event_plan_maybe_schedule_legacy_ticket_cleanup', 40);
        add_action('vms_event_plan_legacy_ticket_cleanup', 'vms_event_plan_cleanup_legacy_ticket_meta_once', 10, 0);

        /**
         * TEC Publish
         */
        if (!function_exists('vms_event_plan_calendar_normalize_signature_data')) {
            function vms_event_plan_calendar_normalize_signature_data($value) {
                if (is_array($value)) {
                    $normalized = array();
                    foreach ($value as $key => $item) {
                        $normalized[(string) $key] = vms_event_plan_calendar_normalize_signature_data($item);
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

        if (!function_exists('vms_event_plan_calendar_sync_signature')) {
            function vms_event_plan_calendar_sync_signature(array $args): string
            {
                $payload = vms_event_plan_calendar_normalize_signature_data($args);
                return hash('sha256', (string) wp_json_encode($payload));
            }
        }

        if (!function_exists('vms_event_plan_schedule_calendar_maintenance')) {
            function vms_event_plan_schedule_calendar_maintenance(int $plan_id, int $tec_event_id, string $reason = 'publish'): void
            {
                $plan_id = absint($plan_id);
                $tec_event_id = absint($tec_event_id);
                if ($plan_id <= 0 || $tec_event_id <= 0) {
                    return;
                }

                if (function_exists('vms_event_plan_capture_actor_user_id')) {
                    vms_event_plan_capture_actor_user_id($plan_id, (int) get_current_user_id(), 'calendar_maintenance_schedule');
                }

                if (function_exists('vms_event_plan_has_effective_tickets') && !vms_event_plan_has_effective_tickets($plan_id)) {
                    if (function_exists('vms_event_plan_perf_log')) {
                        vms_event_plan_perf_log(
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
                $already_locked = function_exists('vms_event_plan_perf_job_has_lock')
                    ? vms_event_plan_perf_job_has_lock('calendar_maintenance', $plan_id)
                    : false;

                $already_scheduled = (bool) wp_next_scheduled($hook, $args);
                if (!$already_locked && !$already_scheduled) {
                    wp_schedule_single_event(time() + 90, $hook, $args);
                    if (function_exists('vms_event_plan_perf_job_set_lock')) {
                        vms_event_plan_perf_job_set_lock('calendar_maintenance', $plan_id, 'pending', 15 * MINUTE_IN_SECONDS);
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

                if (function_exists('vms_event_plan_perf_log')) {
                    vms_event_plan_perf_log(
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

        if (!function_exists('vms_event_plan_run_calendar_maintenance')) {
            function vms_event_plan_run_calendar_maintenance(int $plan_id, int $tec_event_id): void
            {
                $plan_id = absint($plan_id);
                $tec_event_id = absint($tec_event_id);
                if ($plan_id <= 0 || $tec_event_id <= 0) {
                    return;
                }

                $trace = function_exists('vms_event_plan_perf_span_start')
                    ? vms_event_plan_perf_span_start(
                        'vms_event_plan_calendar_maintenance',
                        $plan_id,
                        array(
                            'job_name' => 'calendar_maintenance',
                            'linked_tec_event_id' => $tec_event_id,
                        )
                    )
                    : '';
                $lock = function_exists('vms_event_plan_perf_job_get_lock')
                    ? vms_event_plan_perf_job_get_lock('calendar_maintenance', $plan_id)
                    : array();
                if (($lock['state'] ?? '') === 'running') {
                    if (function_exists('vms_event_plan_perf_log')) {
                        vms_event_plan_perf_log(
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
                    if (function_exists('vms_event_plan_perf_span_finish')) {
                        vms_event_plan_perf_span_finish('vms_event_plan_calendar_maintenance', $plan_id, $trace, array('job_name' => 'calendar_maintenance', 'linked_tec_event_id' => $tec_event_id, 'skipped' => 1));
                    }
                    return;
                }

                if (function_exists('vms_event_plan_perf_job_set_lock')) {
                    vms_event_plan_perf_job_set_lock('calendar_maintenance', $plan_id, 'running', 15 * MINUTE_IN_SECONDS);
                }

                try {
                    $tec_post = get_post($tec_event_id);
                    if (!$tec_post || $tec_post->post_type !== 'tribe_events') {
                        update_post_meta($plan_id, '_vms_calendar_maintenance_last_error', 'missing_tec_event');
                        return;
                    }

                    if (function_exists('vms_event_plan_backfill_tec_event_author')) {
                        vms_event_plan_backfill_tec_event_author($plan_id, $tec_event_id, 'vms_event_plan_calendar_maintenance');
                    }

                    if (function_exists('vms_tec_sync_vendor_categories_from_plan')) {
                        vms_tec_sync_vendor_categories_from_plan($plan_id, $tec_event_id);
                    }

                    update_post_meta($plan_id, '_vms_calendar_maintenance_last_run', time());
                    delete_post_meta($plan_id, '_vms_calendar_maintenance_last_error');
                } finally {
                    if (function_exists('vms_event_plan_perf_job_clear_lock')) {
                        vms_event_plan_perf_job_clear_lock('calendar_maintenance', $plan_id);
                    }
                    if (function_exists('vms_event_plan_perf_span_finish')) {
                        vms_event_plan_perf_span_finish(
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
        add_action('vms_event_plan_calendar_maintenance', 'vms_event_plan_run_calendar_maintenance', 10, 2);

        if (!function_exists('vms_event_plan_deferred_calendar_publish_enabled')) {
            function vms_event_plan_deferred_calendar_publish_enabled(int $post_id = 0, string $reason = 'publish_now'): bool
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

        if (!function_exists('vms_event_plan_schedule_deferred_calendar_publish')) {
            function vms_event_plan_schedule_deferred_calendar_publish(int $post_id, string $reason = 'publish_now'): bool
            {
                $post_id = absint($post_id);
                if ($post_id <= 0) {
                    return false;
                }

                $actor_user_id = function_exists('vms_event_plan_capture_actor_user_id')
                    ? vms_event_plan_capture_actor_user_id($post_id, (int) get_current_user_id(), 'deferred_calendar_publish_schedule')
                    : (int) get_current_user_id();

                $hook = 'vms_event_plan_deferred_calendar_publish';
                $args = array($post_id);
                $already_locked = function_exists('vms_event_plan_perf_job_has_lock')
                    ? vms_event_plan_perf_job_has_lock('calendar_publish', $post_id)
                    : false;

                $already_scheduled = (bool) wp_next_scheduled($hook, $args);
                if (!$already_locked && !$already_scheduled) {
                    wp_schedule_single_event(time() + 180, $hook, $args);
                    if (function_exists('vms_event_plan_perf_job_set_lock')) {
                        vms_event_plan_perf_job_set_lock('calendar_publish', $post_id, 'pending', 20 * MINUTE_IN_SECONDS);
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

                if (function_exists('vms_event_plan_perf_log')) {
                    vms_event_plan_perf_log(
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

        if (!function_exists('vms_event_plan_run_deferred_calendar_publish')) {
            function vms_event_plan_run_deferred_calendar_publish(int $post_id): void
            {
                $post_id = absint($post_id);
                if ($post_id <= 0) {
                    return;
                }

                $trace = function_exists('vms_event_plan_perf_span_start')
                    ? vms_event_plan_perf_span_start('vms_event_plan_deferred_calendar_publish', $post_id, array('job_name' => 'calendar_publish'))
                    : '';
                $lock = function_exists('vms_event_plan_perf_job_get_lock')
                    ? vms_event_plan_perf_job_get_lock('calendar_publish', $post_id)
                    : array();
                if (($lock['state'] ?? '') === 'running') {
                    if (function_exists('vms_event_plan_perf_log')) {
                        vms_event_plan_perf_log(
                            'vms_event_plan_deferred_calendar_publish',
                            $post_id,
                            array(
                                'job_name' => 'calendar_publish',
                                'skipped' => 1,
                                'skip_reason' => 'job_already_running',
                            )
                        );
                    }
                    if (function_exists('vms_event_plan_perf_span_finish')) {
                        vms_event_plan_perf_span_finish('vms_event_plan_deferred_calendar_publish', $post_id, $trace, array('job_name' => 'calendar_publish', 'skipped' => 1));
                    }
                    return;
                }

                if (function_exists('vms_event_plan_perf_job_set_lock')) {
                    vms_event_plan_perf_job_set_lock('calendar_publish', $post_id, 'running', 20 * MINUTE_IN_SECONDS);
                }

                try {
                    $post = get_post($post_id);
                    if (!$post || $post->post_type !== 'vms_event_plan') {
                        return;
                    }

                    if (function_exists('vms_validate_event_plan')) {
                        $errors = vms_validate_event_plan($post_id);
                        if (!empty($errors)) {
                            update_post_meta($post_id, '_vms_calendar_publish_queue_state', 'failed');
                            update_post_meta($post_id, '_vms_calendar_publish_last_error', implode(' ', array_map('sanitize_text_field', (array) $errors)));
                            return;
                        }
                    }

                    update_post_meta($post_id, '_vms_calendar_publish_queue_state', 'running');
                    update_post_meta($post_id, '_vms_calendar_publish_started_at', time());

                    $ok = vms_publish_event_to_calendar($post_id, $post);
                    if ($ok) {
                        update_post_meta($post_id, '_vms_calendar_publish_queue_state', 'complete');
                        update_post_meta($post_id, '_vms_calendar_publish_completed_at', time());
                        delete_post_meta($post_id, '_vms_calendar_publish_last_error');
                    } else {
                        update_post_meta($post_id, '_vms_calendar_publish_queue_state', 'failed');
                        update_post_meta($post_id, '_vms_calendar_publish_last_error', 'deferred_calendar_publish_failed');
                    }
                } finally {
                    if (function_exists('vms_event_plan_perf_job_clear_lock')) {
                        vms_event_plan_perf_job_clear_lock('calendar_publish', $post_id);
                    }
                    if (function_exists('vms_event_plan_perf_span_finish')) {
                        vms_event_plan_perf_span_finish('vms_event_plan_deferred_calendar_publish', $post_id, $trace, array('job_name' => 'calendar_publish'));
                    }
                }
            }
        }
        add_action('vms_event_plan_deferred_calendar_publish', 'vms_event_plan_run_deferred_calendar_publish', 10, 1);

        // Later featured-image edits do not rebuild the TEC event payload, so mirror plan thumbnails directly.
	        function vms_event_plan_sync_linked_tec_featured_image(int $plan_id, int $tec_event_id = 0, string $source = ''): array
	        {
	            static $request_guard = array();

	            $plan_id = absint($plan_id);
	            $tec_event_id = absint($tec_event_id);
	            $source = sanitize_key($source);
	            $trace = function_exists('vms_event_plan_perf_span_start')
	                ? vms_event_plan_perf_span_start('event_plan_featured_image_sync', $plan_id, array(
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
                    if (function_exists('vms_get_plan_tec_event_id')) {
                        $tec_event_id = absint(vms_get_plan_tec_event_id($plan_id));
                    }
                    if ($tec_event_id <= 0) {
                        $tec_key_id = function_exists('vms_meta_key')
                            ? (vms_meta_key('event_plan', 'tec_event_id') ?: '_vms_tec_event_id')
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
	                if (function_exists('vms_event_plan_save_profiler_track_featured_image_sync')) {
	                    vms_event_plan_save_profiler_track_featured_image_sync($source, $result);
	                }
	                if (function_exists('vms_event_plan_perf_span_finish')) {
	                    vms_event_plan_perf_span_finish('event_plan_featured_image_sync', $plan_id, $trace, array(
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

        function vms_event_plan_maybe_sync_tec_featured_image_from_thumbnail_meta($meta_id, $object_id, $meta_key, $meta_value): void
        {
            unset($meta_id, $meta_value);

            if ($meta_key !== '_thumbnail_id') {
                return;
            }

            $plan_id = absint($object_id);
            if ($plan_id <= 0 || get_post_type($plan_id) !== 'vms_event_plan') {
                return;
            }

	            if (function_exists('vms_event_plan_save_profiler_force_effective_meta_key_for_post')) {
	                vms_event_plan_save_profiler_force_effective_meta_key_for_post($plan_id, '_thumbnail_id');
	            }

	            vms_event_plan_sync_linked_tec_featured_image($plan_id, 0, 'thumbnail_meta');
	        }
        add_action('added_post_meta', 'vms_event_plan_maybe_sync_tec_featured_image_from_thumbnail_meta', 10, 4);
        add_action('updated_post_meta', 'vms_event_plan_maybe_sync_tec_featured_image_from_thumbnail_meta', 10, 4);

        function vms_event_plan_sync_linked_tec_featured_image_on_save(int $post_id, WP_Post $post, bool $update): void
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
	                function_exists('vms_event_plan_save_profiler_featured_image_sync_completed')
	                && vms_event_plan_save_profiler_featured_image_sync_completed($post_id)
	            ) {
	                if (function_exists('vms_event_plan_save_profiler_note_heavy_action')) {
	                    vms_event_plan_save_profiler_note_heavy_action('featured_image_sync_save_post', 'skipped', 'already_completed');
	                }
	                return;
	            }

	            vms_event_plan_sync_linked_tec_featured_image($post_id, 0, 'save_post');
	        }
        add_action('save_post_vms_event_plan', 'vms_event_plan_sync_linked_tec_featured_image_on_save', 50, 3);

        function vms_publish_event_to_calendar(int $post_id, WP_Post $post): bool
        {
            $trace = function_exists('vms_event_plan_perf_span_start')
                ? vms_event_plan_perf_span_start('vms_publish_event_to_calendar', $post_id, array('job_name' => 'calendar_publish'))
                : '';

            try {
                if (function_exists('vms_event_plan_capture_actor_user_id')) {
                    vms_event_plan_capture_actor_user_id($post_id, (int) get_current_user_id(), 'publish_event_to_calendar');
                }

                if (!function_exists('tribe_create_event') || !function_exists('tribe_update_event')) {
                    vms_add_admin_notice(__('The Events Calendar functions are not available. Is the plugin active?', 'backstage-venue-manager'), 'error');
                    return false;
                }

                $tec_key_id  = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'tec_event_id') ?: '_vms_tec_event_id') : '_vms_tec_event_id';
                $tec_key_url = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'tec_event_url') ?: '_vms_tec_event_url') : '_vms_tec_event_url';

                $existing_tec_id = (int) get_post_meta($post_id, $tec_key_id, true);
                $tec_event_id = 0;

                $args = vms_build_tec_event_args($post_id, $existing_tec_id);
                if (empty($args)) {
                    vms_add_admin_notice(__('Unable to build event data for The Events Calendar.', 'backstage-venue-manager'), 'error');
                    return false;
                }

                if (function_exists('vms_event_plan_apply_tec_author_args')) {
                    $args = vms_event_plan_apply_tec_author_args($post_id, $args, $existing_tec_id, 'vms_publish_event_to_calendar');
                }

                $sync_signature = function_exists('vms_event_plan_calendar_sync_signature')
                    ? vms_event_plan_calendar_sync_signature($args)
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
                    ) {
                        $tec_event_id = $existing_tec_id;
                    } else {
                        $updated_id = tribe_update_event($existing_tec_id, $args);
                        if ($updated_id && !is_wp_error($updated_id)) {
                            $tec_event_id = (int) $updated_id;
                        } else {
                            vms_add_admin_notice(__('Failed to update existing Events Calendar event. Will attempt to create a new one.', 'backstage-venue-manager'), 'error');
                        }
                    }
                }

                if (!$tec_event_id) {
                    $created_id = tribe_create_event($args);
                    if ($created_id && !is_wp_error($created_id)) {
                        $tec_event_id = (int) $created_id;
                        update_post_meta($post_id, $tec_key_id, $tec_event_id);
                    } else {
                        vms_add_admin_notice(__('Failed to create event in The Events Calendar.', 'backstage-venue-manager'), 'error');
                        return false;
                    }
                }

                if (function_exists('vms_event_plan_backfill_tec_event_author')) {
                    vms_event_plan_backfill_tec_event_author($post_id, $tec_event_id, 'vms_publish_event_to_calendar');
                }

                if ($sync_signature !== '') {
                    update_post_meta($post_id, $signature_key, $sync_signature);
                    update_post_meta($post_id, '_vms_tec_last_sync_at', time());
                }

                delete_post_meta($tec_event_id, '_EventOrganizerID');

	                if (function_exists('vms_event_plan_sync_linked_tec_featured_image')) {
	                    vms_event_plan_sync_linked_tec_featured_image($post_id, $tec_event_id, 'publish_to_calendar');
	                }

                $tec_permalink = get_permalink($tec_event_id);
                if ($tec_permalink) {
                    update_post_meta($post_id, $tec_key_url, esc_url_raw($tec_permalink));
                }

                if (function_exists('vms_event_plan_sync_checkin_close_meta_to_tec')) {
                    vms_event_plan_sync_checkin_close_meta_to_tec($post_id, $tec_event_id);
                }

                if (function_exists('vms_event_plan_schedule_calendar_maintenance')) {
                    vms_event_plan_schedule_calendar_maintenance($post_id, $tec_event_id, 'publish_now');
                }

                return true;
            } finally {
                if (function_exists('vms_event_plan_perf_span_finish')) {
                    vms_event_plan_perf_span_finish('vms_publish_event_to_calendar', $post_id, $trace, array('job_name' => 'calendar_publish'));
                }
            }
        }

        /**
         * Re-sync an existing TEC event from this Event Plan without creating a new event.
         * This is used by the “Re-sync to Calendar” button once a plan is already linked to TEC.
         */
        function vms_resync_event_to_calendar(int $post_id, WP_Post $post, int $existing_tec_id): bool
        {
            $trace = function_exists('vms_event_plan_perf_span_start')
                ? vms_event_plan_perf_span_start('vms_resync_event_to_calendar', $post_id, array('job_name' => 'calendar_resync', 'linked_tec_event_id' => $existing_tec_id))
                : '';

            try {
                if ($existing_tec_id <= 0) {
                    return false;
                }

                if (function_exists('vms_event_plan_capture_actor_user_id')) {
                    vms_event_plan_capture_actor_user_id($post_id, (int) get_current_user_id(), 'resync_event_to_calendar');
                }

                if (!function_exists('tribe_update_event')) {
                    error_log('VMS TEC: tribe_update_event() not available. Is The Events Calendar active?');
                    return false;
                }

                $args = vms_build_tec_event_args($post_id, $existing_tec_id);
                if (empty($args)) {
                    return false;
                }

                if (function_exists('vms_event_plan_apply_tec_author_args')) {
                    $args = vms_event_plan_apply_tec_author_args($post_id, $args, $existing_tec_id, 'vms_resync_event_to_calendar');
                }

                $plan_thumb = absint(get_post_thumbnail_id($post_id));
                $tec_thumb  = absint(get_post_thumbnail_id($existing_tec_id));
                if ($plan_thumb <= 0 && $tec_thumb > 0 && isset($args['FeaturedImage'])) {
                    unset($args['FeaturedImage']);
                }

                $updated_id = tribe_update_event($existing_tec_id, $args);
                if (!$updated_id || is_wp_error($updated_id)) {
                    $msg = is_wp_error($updated_id) ? $updated_id->get_error_message() : 'Unknown error';
                    error_log('VMS TEC: Failed to re-sync plan ' . $post_id . ' to TEC event ' . $existing_tec_id . ': ' . $msg);
                    return false;
                }

                $tec_event_id = (int) $updated_id;

                if (function_exists('vms_event_plan_backfill_tec_event_author')) {
                    vms_event_plan_backfill_tec_event_author($post_id, $tec_event_id, 'vms_resync_event_to_calendar');
                }

                delete_post_meta($tec_event_id, '_EventOrganizerID');

	                if (function_exists('vms_event_plan_sync_linked_tec_featured_image')) {
	                    vms_event_plan_sync_linked_tec_featured_image($post_id, $tec_event_id, 'resync_to_calendar');
	                }

                $tec_key_url = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'tec_event_url') : '_vms_tec_event_url';
                $tec_permalink = get_permalink($tec_event_id);
                if ($tec_permalink) {
                    update_post_meta($post_id, $tec_key_url, esc_url_raw($tec_permalink));
                }

                if (function_exists('vms_event_plan_calendar_sync_signature')) {
                    update_post_meta($post_id, '_vms_tec_last_sync_signature', vms_event_plan_calendar_sync_signature($args));
                    update_post_meta($post_id, '_vms_tec_last_sync_at', time());
                }

                if (function_exists('vms_event_plan_sync_checkin_close_meta_to_tec')) {
                    vms_event_plan_sync_checkin_close_meta_to_tec($post_id, $tec_event_id);
                }

                if (function_exists('vms_event_plan_schedule_calendar_maintenance')) {
                    vms_event_plan_schedule_calendar_maintenance($post_id, $tec_event_id, 'resync_to_calendar');
                }

                return true;
            } finally {
                if (function_exists('vms_event_plan_perf_span_finish')) {
                    vms_event_plan_perf_span_finish('vms_resync_event_to_calendar', $post_id, $trace, array('job_name' => 'calendar_resync', 'linked_tec_event_id' => $existing_tec_id));
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
        function vms_tec_resolve_featured_image_arg(int $plan_thumb_id, int $existing_tec_thumb_id, int $vendor_thumb_id): int
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

        function vms_build_tec_event_args(int $post_id, int $existing_tec_id = 0): array {

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
            $featured_image_id = function_exists('vms_tec_resolve_featured_image_arg')
                ? (int) vms_tec_resolve_featured_image_arg($plan_thumb_id, $existing_tec_thumb_id, $vendor_thumb_id)
                : 0;
            if ($featured_image_id > 0) {
                $args['FeaturedImage'] = $featured_image_id;
            }

            // TEC Venue mapping (VMS venue → TEC venue).
            $tec_venue_id = vms_tec_get_tec_venue_id_for_plan($post_id);
            if ($tec_venue_id) {
                $args['Venue'] = array('VenueID' => $tec_venue_id);
            }

            // Organizer: do NOT auto-map the performer as the organizer.
            // (We can add a proper "Default Organizer" setting later.)
            // $organizer_id = vms_tec_get_tec_organizer_id_for_plan($post_id);

            // Ticket URL (optional).
            $ticket_url = vms_tec_get_ticket_url_for_plan($post_id);
            if ($ticket_url) {
                $args['EventURL'] = esc_url_raw($ticket_url);
            }

            // Cost (optional).
            $cost = vms_tec_build_event_cost_string($post_id);
            if ($cost !== '') {
                $args['EventCost'] = $cost;
            }

            return $args;
        }



        function vms_event_plan_collect_vendor_category_snapshot(int $plan_id): array {

            $plan_id = absint($plan_id);
            if ($plan_id <= 0) {
                return array(
                    'term_ids' => array(),
                    'term_names' => array(),
                    'term_slugs' => array(),
                    'vendors' => array(),
                );
            }

            $k_band = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'band_vendor_id') ?: '_vms_band_vendor_id') : '_vms_band_vendor_id';
            $band_id = (int) get_post_meta($plan_id, $k_band, true);
            $secondary_assignments = function_exists('vms_event_plan_get_secondary_vendor_assignments')
                ? (array) vms_event_plan_get_secondary_vendor_assignments($plan_id, array(
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
                    'type_slug' => function_exists('vms_vendor_primary_type_slug') ? vms_vendor_primary_type_slug($band_id) : '',
                );
            }

            foreach ($secondary_assignments as $type_slug => $assignment) {
                $type_slug = sanitize_title((string) $type_slug);
                $source_label = function_exists('vms_vendor_type_label')
                    ? (string) vms_vendor_type_label($type_slug)
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
                if ($type_slug === '' && function_exists('vms_vendor_primary_type_slug')) {
                    $type_slug = vms_vendor_primary_type_slug($vendor_id);
                }

                $category_label = function_exists('vms_vendor_category_label_for_type')
                    ? vms_vendor_category_label_for_type($type_slug)
                    : __('Category', 'backstage-venue-manager');

                $terms = function_exists('vms_vendor_get_category_terms')
                    ? vms_vendor_get_category_terms($vendor_id)
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

        function vms_event_plan_update_vendor_category_snapshot(int $plan_id): array {

            $plan_id = absint($plan_id);
            if ($plan_id <= 0) {
                return array();
            }

            $snapshot = vms_event_plan_collect_vendor_category_snapshot($plan_id);
            $k_term_ids = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'vendor_category_term_ids') ?: '_vms_vendor_category_term_ids') : '_vms_vendor_category_term_ids';
            $k_names = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'vendor_category_names') ?: '_vms_vendor_category_names') : '_vms_vendor_category_names';
            $k_snapshot = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'vendor_category_snapshot') ?: '_vms_vendor_category_snapshot') : '_vms_vendor_category_snapshot';

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

        function vms_tec_sync_vendor_categories_from_plan(int $plan_id, int $tec_event_id): void {

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

            $snapshot = vms_event_plan_update_vendor_category_snapshot($plan_id);
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

        function vms_tec_sync_event_extras_from_plan(int $plan_id, int $tec_event_id): void {

            if (!$plan_id || !$tec_event_id) {
                return;
            }

            if (!function_exists('tribe_update_event')) {
                return;
            }

            $args = vms_build_tec_event_args($plan_id, $tec_event_id);
            if (empty($args)) {
                return;
            }

            if (function_exists('vms_event_plan_apply_tec_author_args')) {
                $args = vms_event_plan_apply_tec_author_args($plan_id, $args, $tec_event_id, 'vms_tec_sync_event_extras_from_plan');
            }

            $updated = tribe_update_event($tec_event_id, $args);
            delete_post_meta($tec_event_id, '_EventOrganizerID');

            if (function_exists('vms_event_plan_backfill_tec_event_author')) {
                vms_event_plan_backfill_tec_event_author($plan_id, $tec_event_id, 'vms_tec_sync_event_extras_from_plan');
            }

            if (!$updated || is_wp_error($updated)) {
                $msg = is_wp_error($updated) ? $updated->get_error_message() : 'Unknown error';
                error_log('VMS TEC: Failed to sync TEC event extras for plan ' . $plan_id . ' (TEC event ' . $tec_event_id . '): ' . $msg);
            }
        }



        function vms_tec_get_tec_venue_id_for_plan(int $plan_id): int
        {
            $vms_venue_id = (int) get_post_meta($plan_id, '_vms_venue_id', true);
            return vms_tec_ensure_tec_venue_for_vms_venue($vms_venue_id);
        }

        function vms_tec_ensure_tec_venue_for_vms_venue(int $vms_venue_id): int
        {
            if ($vms_venue_id <= 0) return 0;
            if (!function_exists('tribe_create_venue')) return 0;

            $key = function_exists('vms_meta_key') ? vms_meta_key('venue', 'tec_venue_id') : '_vms_tec_venue_id';

            $existing = (int) get_post_meta($vms_venue_id, $key, true);
            if ($existing > 0 && get_post_status($existing)) {
                if (function_exists('vms_sync_tec_venue_from_vms_venue')) {
                    vms_sync_tec_venue_from_vms_venue($vms_venue_id, $existing);
                }
                return $existing;
            }

            $name = (string) get_the_title($vms_venue_id);
            if ($name === '') return 0;

            $location = function_exists('vms_get_venue_location_data')
                ? vms_get_venue_location_data($vms_venue_id)
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
                if (function_exists('vms_sync_tec_venue_from_vms_venue')) {
                    vms_sync_tec_venue_from_vms_venue($vms_venue_id, $created);
                }
                return $created;
            }

            return 0;
        }

        function vms_tec_get_tec_organizer_id_for_plan(int $plan_id): int
        {
            $vendor_id = (int) get_post_meta($plan_id, '_vms_band_vendor_id', true);
            return vms_tec_ensure_tec_organizer_for_vendor($vendor_id);
        }

        function vms_tec_ensure_tec_organizer_for_vendor(int $vendor_id): int
        {
            if ($vendor_id <= 0) return 0;
            if (!function_exists('tribe_create_organizer')) return 0;

            $key = function_exists('vms_meta_key') ? vms_meta_key('vendor', 'tec_organizer_id') : '_vms_tec_organizer_id';

            $existing = (int) get_post_meta($vendor_id, $key, true);
            if ($existing > 0 && get_post_status($existing)) return $existing;

            $name = (string) get_the_title($vendor_id);
            if ($name === '') return 0;

            $email = '';
            $phone = '';
            $website = '';

            if (function_exists('vms_meta_key')) {
                $email = (string) get_post_meta($vendor_id, vms_meta_key('vendor', 'primary_email'), true);
                $phone = (string) get_post_meta($vendor_id, vms_meta_key('vendor', 'primary_phone'), true);
                $website = (string) get_post_meta($vendor_id, vms_meta_key('vendor', 'website'), true);
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

        function vms_tec_get_ticket_url_for_plan(int $plan_id): string
        {
            $map = get_post_meta($plan_id, '_vms_wc_product_map', true);
            if (!is_array($map)) return '';

            $ga_id = isset($map['ga']) ? (int) $map['ga'] : 0;
            if ($ga_id <= 0) return '';

            $url = get_permalink($ga_id);
            return $url ? esc_url_raw($url) : '';
        }

        function vms_tec_build_event_cost_string(int $plan_id): string
        {
            $from_price = function_exists('vms_ticketing_v2_get_from_price_for_display')
                ? vms_ticketing_v2_get_from_price_for_display($plan_id)
                : null;
            if ($from_price === null || $from_price <= 0) {
                return '';
            }

            return 'From ' . vms_tec_format_money((float) $from_price);
        }

        function vms_tec_format_money(float $amount): string
        {
            $rounded = round($amount, 2);
            $is_intish = abs($rounded - round($rounded)) < 0.01;
            return '$' . ($is_intish ? number_format((float) round($rounded), 0) : number_format($rounded, 2));
        }


        add_filter('post_row_actions', 'vms_event_plan_row_actions', 10, 2);
        function vms_event_plan_row_actions(array $actions, WP_Post $post): array
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

            $k_issue = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'integrity_issue') : '_vms_integrity_issue';
            $issue = (string) get_post_meta($post->ID, $k_issue, true);

            $needs_attention = false;
            if (in_array($issue, array('missing_vendor','missing_secondary_vendor','trashed_vendor','trashed_secondary_vendor','missing_venue','trashed_venue','venue_unpublished','missing_calendar_event','trashed_calendar_event','calendar_event_unpublished','calendar_event_unlinked'), true)) {
        			$needs_attention = true;
        		}

            $k_unq = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'secondary_vendor_unqualified') : '_vms_secondary_vendor_unqualified';
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
            $venue_id = isset($_GET['vms_venue_id']) ? absint($_GET['vms_venue_id']) : 0;
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
        function vms_publish_products_from_plan(int $plan_id): array
        {
            return array(
                'ok' => false,
                'error' => 'legacy_pipeline_retired',
                'message' => 'Legacy Woo publish pipeline is retired. Use Ticketing Preview → Commit.',
                'plan_id' => absint($plan_id),
            );
        }

        function vms_build_product_blueprint_for_plan(int $plan_id, string $nice_date, string $band_name): array
        {
            $plan_id = absint($plan_id);
            if ($plan_id <= 0 || !function_exists('vms_ticketing_v2_get_config')) {
                return array();
            }

            $cfg = vms_ticketing_v2_get_config($plan_id);
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
                if (function_exists('vms_ticketing_v2_product_meta_key')) {
                    $meta[vms_ticketing_v2_product_meta_key('ticketing_visibility_mode')] = $visibility_mode;
                    if ($verified_program !== '') {
                        $meta[vms_ticketing_v2_product_meta_key('ticketing_verified_program')] = $verified_program;
                    }
                    $ratio_rule_enabled = !empty($ticket['ratio_rule_enabled']);
                    $ratio_rule_max_per_qualifying = max(0, absint($ticket['ratio_rule_max_per_qualifying'] ?? 0));
                    if ($ratio_rule_enabled && $ratio_rule_max_per_qualifying > 0) {
                        $meta[vms_ticketing_v2_product_meta_key('ticketing_ratio_rule_enabled')] = '1';
                        $meta[vms_ticketing_v2_product_meta_key('ticketing_ratio_rule_max_per_qualifying')] = (string) $ratio_rule_max_per_qualifying;
                        $meta[vms_ticketing_v2_product_meta_key('ticketing_ratio_rule_qualifier_mode')] = 'counts_toward_unlock';
                        $ratio_rule_group = sanitize_title((string) ($ticket['ratio_rule_group'] ?? ''));
                        if ($ratio_rule_group !== '') {
                            $meta[vms_ticketing_v2_product_meta_key('ticketing_ratio_rule_group')] = $ratio_rule_group;
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
        function vms_wc_units_sold_for_product_details(int $product_id): array
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

            if (function_exists('vms_ticketing_v2_calc_sold_qty_for_product')) {
                $result = vms_ticketing_v2_calc_sold_qty_for_product($product_id);
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

        function vms_wc_units_sold_for_product(int $product_id): int
        {
            $result = vms_wc_units_sold_for_product_details($product_id);
            return max(0, (int) ($result['sold_qty'] ?? 0));
        }

function vms_upsert_plan_product(int $plan_id, int $existing_product_id, array $spec, int $tec_event_id = 0): int
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
                    $sold_result = vms_wc_units_sold_for_product_details($existing_product_id);
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

            if (function_exists('vms_ticketing_v2_push_inventory_write_context')) {
                vms_ticketing_v2_push_inventory_write_context($inventory_write_context);
            } elseif (function_exists('vms_ticket_mutation_audit_push_context')) {
                vms_ticket_mutation_audit_push_context($inventory_write_context);
            }

            try {
                $product_id = (int) $product->save();
            } finally {
                if (function_exists('vms_ticketing_v2_pop_inventory_write_context')) {
                    vms_ticketing_v2_pop_inventory_write_context();
                } elseif (function_exists('vms_ticket_mutation_audit_pop_context')) {
                    vms_ticket_mutation_audit_pop_context();
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
            if (function_exists('vms_ticketing_v2_apply_reporting_category')) {
                vms_ticketing_v2_apply_reporting_category($product_id, !empty($spec['is_ticket']) ? 'ticket' : 'addon');
            }

            return $product_id;
        }

        function vms_disable_plan_product(int $product_id): void
        {
            $p = wc_get_product($product_id);
            if (!$p) return;
            $p->set_status('draft');
            $p->save();
        }
