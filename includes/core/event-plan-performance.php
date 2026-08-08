<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_event_plan_perf_trace_enabled')) {
	function vms_event_plan_perf_trace_enabled(): bool
	{
		return defined('VMS_EP_PERF_TRACE') && VMS_EP_PERF_TRACE;
	}
}

if (!function_exists('vms_event_plan_perf_log_path')) {
	function vms_event_plan_perf_log_path(): string
	{
		$path = defined('WP_CONTENT_DIR')
			? WP_CONTENT_DIR . '/vms-event-plan-perf-trace.log'
			: dirname(__DIR__, 3) . '/vms-event-plan-perf-trace.log';

		return (string) apply_filters('vms_event_plan_perf_log_path', $path);
	}
}

if (!function_exists('vms_event_plan_perf_request_state')) {
	function vms_event_plan_perf_request_state(): array
	{
		$state = $GLOBALS['vms_event_plan_perf_request_state'] ?? array();
		return is_array($state) ? $state : array();
	}
}

if (!function_exists('vms_event_plan_perf_save_request_state')) {
	function vms_event_plan_perf_save_request_state(array $state): void
	{
		$GLOBALS['vms_event_plan_perf_request_state'] = $state;
	}
}

if (!function_exists('vms_event_plan_perf_request_data')) {
	function vms_event_plan_perf_request_data(): array
	{
		static $request = null;
		if (is_array($request)) {
			return $request;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Performance sampling reads request context only for diagnostics and never authorizes or mutates state.
		$request = (isset($_REQUEST) && is_array($_REQUEST)) ? wp_unslash($_REQUEST) : array();
		return is_array($request) ? $request : array();
	}
}

if (!function_exists('vms_event_plan_perf_get_data')) {
	function vms_event_plan_perf_get_data(): array
	{
		static $request = null;
		if (is_array($request)) {
			return $request;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Performance sampling reads request context only for diagnostics and never authorizes or mutates state.
		$request = (isset($_GET) && is_array($_GET)) ? wp_unslash($_GET) : array();
		return is_array($request) ? $request : array();
	}
}

if (!function_exists('vms_event_plan_perf_post_data')) {
	function vms_event_plan_perf_post_data(): array
	{
		static $request = null;
		if (is_array($request)) {
			return $request;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Performance sampling reads request context only for diagnostics and never authorizes or mutates state.
		$request = (isset($_POST) && is_array($_POST)) ? wp_unslash($_POST) : array();
		return is_array($request) ? $request : array();
	}
}

if (!function_exists('vms_event_plan_perf_get_plan_counter')) {
	function vms_event_plan_perf_get_plan_counter(string $key, int $plan_id): int
	{
		$plan_id = absint($plan_id);
		if ($plan_id <= 0) {
			return 0;
		}

		$state = vms_event_plan_perf_request_state();
		$bucket = is_array($state[$key] ?? null) ? $state[$key] : array();
		return absint($bucket[$plan_id] ?? 0);
	}
}

if (!function_exists('vms_event_plan_perf_increment_plan_counter')) {
	function vms_event_plan_perf_increment_plan_counter(string $key, int $plan_id): int
	{
		$plan_id = absint($plan_id);
		if ($plan_id <= 0) {
			return 0;
		}

		$state = vms_event_plan_perf_request_state();
		if (!isset($state[$key]) || !is_array($state[$key])) {
			$state[$key] = array();
		}

		$state[$key][$plan_id] = absint($state[$key][$plan_id] ?? 0) + 1;
		vms_event_plan_perf_save_request_state($state);
		return absint($state[$key][$plan_id]);
	}
}

if (!function_exists('vms_event_plan_perf_current_plan_id')) {
	function vms_event_plan_perf_current_plan_id(): int
	{
		$request = vms_event_plan_perf_request_data();
		$get_request = vms_event_plan_perf_get_data();
		$post_request = vms_event_plan_perf_post_data();
		$candidates = array(
			$request['post'] ?? 0,
			$request['post_ID'] ?? 0,
			$get_request['post'] ?? 0,
			$post_request['post_ID'] ?? 0,
		);

		foreach ($candidates as $candidate) {
			$plan_id = absint($candidate);
			if ($plan_id > 0 && get_post_type($plan_id) === 'vms_event_plan') {
				return $plan_id;
			}
		}

		return 0;
	}
}

if (!function_exists('vms_event_plan_perf_request_scenario')) {
	function vms_event_plan_perf_request_scenario(): string
	{
		$request = vms_event_plan_perf_request_data();
		$scenario = $request['_vms_ep_perf_trace_scenario'] ?? '';
		return sanitize_key((string) $scenario);
	}
}

if (!function_exists('vms_event_plan_perf_baseline_screen')) {
	function vms_event_plan_perf_baseline_screen(): string
	{
		$request = vms_event_plan_perf_request_data();
		$screen = $request['_vms_ep_perf_baseline_screen'] ?? '';
		return sanitize_key((string) $screen);
	}
}

if (!function_exists('vms_event_plan_perf_actor_user_meta_key')) {
	function vms_event_plan_perf_actor_user_meta_key(): string
	{
		return '_vms_event_plan_actor_user_id';
	}
}

if (!function_exists('vms_event_plan_perf_actor_source_meta_key')) {
	function vms_event_plan_perf_actor_source_meta_key(): string
	{
		return '_vms_event_plan_actor_user_source';
	}
}

if (!function_exists('vms_event_plan_perf_request_id')) {
	function vms_event_plan_perf_request_id(): string
	{
		static $request_id = '';
		if ($request_id !== '') {
			return $request_id;
		}

		$seed = array(
			(string) microtime(true),
			(string) wp_rand(1000, 999999),
			vms_request_server_value('REQUEST_TIME_FLOAT'),
			vms_request_current_uri(),
		);
		$request_id = substr(hash('sha256', implode('|', $seed)), 0, 12);
		return $request_id;
	}
}

if (!function_exists('vms_event_plan_perf_user_is_valid')) {
	function vms_event_plan_perf_user_is_valid(int $user_id): bool
	{
		$user_id = absint($user_id);
		if ($user_id <= 0) {
			return false;
		}

		return get_userdata($user_id) instanceof WP_User;
	}
}

if (!function_exists('vms_event_plan_perf_first_admin_user_id')) {
	function vms_event_plan_perf_first_admin_user_id(): int
	{
		static $cached = null;
		if ($cached !== null) {
			return absint($cached);
		}

		$admins = get_users(array(
			'role' => 'administrator',
			'number' => 1,
			'orderby' => 'ID',
			'order' => 'ASC',
			'fields' => 'ids',
		));
		$cached = !empty($admins) ? absint($admins[0]) : 0;
		return absint($cached);
	}
}

if (!function_exists('vms_event_plan_capture_actor_user_id')) {
	function vms_event_plan_capture_actor_user_id(int $plan_id, int $actor_user_id = 0, string $source = ''): int
	{
		$plan_id = absint($plan_id);
		if ($plan_id <= 0 || get_post_type($plan_id) !== 'vms_event_plan') {
			return 0;
		}

		$actor_user_id = $actor_user_id > 0 ? absint($actor_user_id) : absint(get_current_user_id());
		if (!vms_event_plan_perf_user_is_valid($actor_user_id)) {
			$existing = absint(get_post_meta($plan_id, vms_event_plan_perf_actor_user_meta_key(), true));
			return vms_event_plan_perf_user_is_valid($existing) ? $existing : 0;
		}

		update_post_meta($plan_id, vms_event_plan_perf_actor_user_meta_key(), $actor_user_id);
		if ($source !== '') {
			update_post_meta($plan_id, vms_event_plan_perf_actor_source_meta_key(), sanitize_key($source));
		}

		return $actor_user_id;
	}
}

if (!function_exists('vms_event_plan_get_captured_actor_user_id')) {
	function vms_event_plan_get_captured_actor_user_id(int $plan_id): int
	{
		$plan_id = absint($plan_id);
		if ($plan_id <= 0) {
			return 0;
		}

		$actor_user_id = absint(get_post_meta($plan_id, vms_event_plan_perf_actor_user_meta_key(), true));
		return vms_event_plan_perf_user_is_valid($actor_user_id) ? $actor_user_id : 0;
	}
}

if (!function_exists('vms_event_plan_resolve_tec_post_author')) {
	function vms_event_plan_resolve_tec_post_author(int $plan_id, int $actor_user_id = 0): int
	{
		$plan_id = absint($plan_id);
		if ($plan_id <= 0) {
			return 0;
		}

		$plan_author_id = absint(get_post_field('post_author', $plan_id));
		if (vms_event_plan_perf_user_is_valid($plan_author_id)) {
			return $plan_author_id;
		}

		$actor_user_id = $actor_user_id > 0 ? absint($actor_user_id) : vms_event_plan_get_captured_actor_user_id($plan_id);
		if (vms_event_plan_perf_user_is_valid($actor_user_id)) {
			return $actor_user_id;
		}

		$current_user_id = absint(get_current_user_id());
		if (vms_event_plan_perf_user_is_valid($current_user_id)) {
			return $current_user_id;
		}

		return vms_event_plan_perf_first_admin_user_id();
	}
}

if (!function_exists('vms_event_plan_ticketing_snapshot')) {
	function vms_event_plan_ticketing_snapshot(int $plan_id): array
	{
		static $cache = array();

		$plan_id = absint($plan_id);
		$snapshot = array(
			'mode' => 'read_only',
			'saved_config_present' => false,
			'enabled_ticket_count' => 0,
			'enabled_entitlement_count' => 0,
			'linked_ticket_product_count' => 0,
			'mapped_entitlement_product_count' => 0,
			'effective_ticket_count' => 0,
			'has_effective_tickets' => false,
		);

		if ($plan_id <= 0 || get_post_type($plan_id) !== 'vms_event_plan') {
			return $snapshot;
		}

		if (isset($cache[$plan_id]) && is_array($cache[$plan_id])) {
			return $cache[$plan_id];
		}

		$cfg = function_exists('vms_ticketing_v2_get_saved_config')
			? (array) vms_ticketing_v2_get_saved_config($plan_id)
			: array();
		if (!empty($cfg)) {
			$snapshot['saved_config_present'] = true;
			$cfg_mode = sanitize_key((string) ($cfg['mode'] ?? ''));
			if ($cfg_mode !== '') {
				$snapshot['mode'] = $cfg_mode;
			}

			foreach ((array) ($cfg['tickets'] ?? array()) as $ticket_row) {
				if (!is_array($ticket_row)) {
					continue;
				}
				if (empty($ticket_row['enabled'])) {
					continue;
				}
				if (trim((string) ($ticket_row['title'] ?? '')) === '') {
					continue;
				}
				$snapshot['enabled_ticket_count']++;
			}

			foreach ((array) ($cfg['entitlements'] ?? array()) as $entitlement_row) {
				if (!is_array($entitlement_row)) {
					continue;
				}
				if (empty($entitlement_row['enabled'])) {
					continue;
				}
				if (trim((string) ($entitlement_row['label'] ?? '')) === '') {
					continue;
				}
				$snapshot['enabled_entitlement_count']++;
			}
		}

		if (!$snapshot['saved_config_present'] && function_exists('vms_ticketing_b_get_mode')) {
			$snapshot['mode'] = sanitize_key((string) vms_ticketing_b_get_mode($plan_id));
			if ($snapshot['mode'] === '') {
				$snapshot['mode'] = 'read_only';
			}
		}

		$tec_event_id = function_exists('vms_ticketing_b_get_linked_tec_event_id')
			? absint(vms_ticketing_b_get_linked_tec_event_id($plan_id))
			: absint(get_post_meta($plan_id, '_vms_tec_event_id', true));

		if ($tec_event_id > 0 && function_exists('vms_ticketing_b_get_event_ticket_products')) {
			$product_ids = array_values(array_unique(array_filter(array_map('absint', (array) vms_ticketing_b_get_event_ticket_products($tec_event_id)))));
			$snapshot['linked_ticket_product_count'] = count($product_ids);
		}

		if (function_exists('vms_ticketing_v2_get_sync')) {
			$sync = (array) vms_ticketing_v2_get_sync($plan_id);
			$entitlements = is_array($sync['map']['entitlements'] ?? null) ? (array) $sync['map']['entitlements'] : array();
			foreach ($entitlements as $row) {
				if (!is_array($row)) {
					continue;
				}
				if (absint($row['woo_product_id'] ?? 0) > 0) {
					$snapshot['mapped_entitlement_product_count']++;
				}
			}
		}

		if ($snapshot['mode'] === 'vms_managed') {
			$snapshot['effective_ticket_count'] = absint($snapshot['enabled_ticket_count']) + absint($snapshot['enabled_entitlement_count']);
		} else {
			$snapshot['effective_ticket_count'] = absint($snapshot['linked_ticket_product_count']) + absint($snapshot['mapped_entitlement_product_count']);
		}

		$snapshot['has_effective_tickets'] = $snapshot['effective_ticket_count'] > 0;
		$cache[$plan_id] = $snapshot;
		return $snapshot;
	}
}

if (!function_exists('vms_event_plan_has_effective_tickets')) {
	function vms_event_plan_has_effective_tickets(int $plan_id): bool
	{
		$snapshot = vms_event_plan_ticketing_snapshot($plan_id);
		return !empty($snapshot['has_effective_tickets']);
	}
}

if (!function_exists('vms_event_plan_resolve_public_calendar_url')) {
	function vms_event_plan_resolve_public_calendar_url(int $plan_id): string
	{
		$plan_id = absint($plan_id);
		if ($plan_id <= 0 || get_post_type($plan_id) !== 'vms_event_plan') {
			return '';
		}

		$tec_event_id = function_exists('vms_ticketing_b_get_linked_tec_event_id')
			? absint(vms_ticketing_b_get_linked_tec_event_id($plan_id))
			: absint(get_post_meta($plan_id, '_vms_tec_event_id', true));
		if ($tec_event_id <= 0 || get_post_type($tec_event_id) !== 'tribe_events') {
			return '';
		}

		$tec_status = sanitize_key((string) get_post_status($tec_event_id));
		if (!in_array($tec_status, array('publish', 'future'), true)) {
			return '';
		}

		$url = trim((string) get_permalink($tec_event_id));
		return $url !== '' ? $url : '';
	}
}

if (!function_exists('vms_event_plan_public_calendar_is_ready')) {
	function vms_event_plan_public_calendar_is_ready(int $plan_id): bool
	{
		return vms_event_plan_resolve_public_calendar_url($plan_id) !== '';
	}
}

if (!function_exists('vms_event_plan_perf_pid')) {
	function vms_event_plan_perf_pid(): int
	{
		return function_exists('getmypid') ? absint(getmypid()) : 0;
	}
}

if (!function_exists('vms_event_plan_perf_request_type')) {
	function vms_event_plan_perf_request_type(): string
	{
		if (defined('WP_CLI') && WP_CLI) {
			return 'wp_cli';
		}
		if (defined('DOING_CRON') && DOING_CRON) {
			return 'cron';
		}
		if (defined('DOING_AJAX') && DOING_AJAX) {
			return 'ajax';
		}
		if (defined('REST_REQUEST') && REST_REQUEST) {
			return 'rest';
		}
		if (is_admin()) {
			return 'admin';
		}

		return 'front';
	}
}

if (!function_exists('vms_event_plan_perf_current_screen_context')) {
	function vms_event_plan_perf_current_screen_context(): array
	{
		$screen_id = '';
		$screen_base = '';
		$screen_post_type = '';
		$pagenow = isset($GLOBALS['pagenow']) ? sanitize_key((string) $GLOBALS['pagenow']) : '';

		if (function_exists('get_current_screen')) {
			$screen = get_current_screen();
			if ($screen instanceof WP_Screen) {
				$screen_id = sanitize_key((string) $screen->id);
				$screen_base = sanitize_key((string) $screen->base);
				$screen_post_type = sanitize_key((string) $screen->post_type);
			}
		}

		return array(
			'screen_id' => $screen_id,
			'screen_base' => $screen_base,
			'screen_post_type' => $screen_post_type,
			'pagenow' => $pagenow,
		);
	}
}

if (!function_exists('vms_event_plan_perf_extract_plan_id_from_args')) {
	function vms_event_plan_perf_extract_plan_id_from_args($args): int
	{
		if (is_scalar($args)) {
			$candidate = absint($args);
			return ($candidate > 0 && get_post_type($candidate) === 'vms_event_plan') ? $candidate : 0;
		}

		if (!is_array($args)) {
			return 0;
		}

		foreach (array('plan_id', 'event_plan_id', 'post_id') as $key) {
			if (!empty($args[$key])) {
				$candidate = absint($args[$key]);
				if ($candidate > 0 && get_post_type($candidate) === 'vms_event_plan') {
					return $candidate;
				}
			}
		}

		foreach ($args as $value) {
			$plan_id = vms_event_plan_perf_extract_plan_id_from_args($value);
			if ($plan_id > 0) {
				return $plan_id;
			}
		}

		return 0;
	}
}

if (!function_exists('vms_event_plan_perf_request_context')) {
	function vms_event_plan_perf_request_context(int $plan_id = 0): array
	{
		global $wpdb;

		$plan_id = absint($plan_id);
		if ($plan_id <= 0) {
			$plan_id = vms_event_plan_perf_current_plan_id();
		}

		$screen = vms_event_plan_perf_current_screen_context();
		$request = vms_event_plan_perf_request_data();
		$plan_action = isset($request['vms_event_plan_action']) ? sanitize_key((string) $request['vms_event_plan_action']) : '';
		$wp_action = isset($request['action']) ? sanitize_key((string) $request['action']) : '';
		$post_status = $plan_id > 0 ? sanitize_key((string) get_post_status($plan_id)) : '';
		$autosave = (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) ? 1 : 0;
		if (!$autosave && $plan_id > 0 && function_exists('wp_is_post_autosave')) {
			$autosave = wp_is_post_autosave($plan_id) ? 1 : 0;
		}

		return array(
			'request_type' => vms_event_plan_perf_request_type(),
			'request_method' => vms_request_method(''),
			'request_uri' => vms_request_current_uri(),
			'trace_scenario' => vms_event_plan_perf_request_scenario(),
			'wp_action' => $wp_action,
			'plan_action' => $plan_action,
			'is_autosave' => $autosave,
			'is_revision' => ($plan_id > 0 && wp_is_post_revision($plan_id)) ? 1 : 0,
			'is_preview' => (!empty($request['preview']) || !empty($request['wp-preview'])) ? 1 : 0,
			'is_publish_request' => ((strpos($plan_action, 'publish') !== false) || sanitize_key((string) ($request['post_status'] ?? '')) === 'publish') ? 1 : 0,
			'current_user_id' => absint(get_current_user_id()),
			'post_status' => $post_status,
			'query_count' => isset($wpdb->num_queries) ? absint($wpdb->num_queries) : 0,
			'peak_memory_bytes' => function_exists('memory_get_peak_usage') ? (int) memory_get_peak_usage(true) : 0,
			'peak_memory_mb' => function_exists('memory_get_peak_usage') ? round((float) memory_get_peak_usage(true) / 1048576, 2) : 0,
			'save_pass_count' => vms_event_plan_perf_get_plan_counter('save_pass_count', $plan_id),
			'internal_wp_update_post_count' => vms_event_plan_perf_get_plan_counter('internal_wp_update_post_count', $plan_id),
		) + $screen;
	}
}

if (!function_exists('vms_event_plan_perf_log')) {
	function vms_event_plan_perf_log(string $hook_name, int $plan_id = 0, array $context = array()): void
	{
		if (!vms_event_plan_perf_trace_enabled()) {
			return;
		}

		$plan_id = absint($plan_id);
		$ticket_snapshot = vms_event_plan_ticketing_snapshot($plan_id);
		$entry = array(
			'logged_at_gmt' => gmdate('Y-m-d H:i:s'),
			'request_id' => vms_event_plan_perf_request_id(),
			'hook_name' => substr(sanitize_key($hook_name), 0, 80),
			'event_plan_id' => $plan_id,
			'ticket_count' => absint($ticket_snapshot['effective_ticket_count'] ?? 0),
			'ticket_mode' => substr(sanitize_key((string) ($ticket_snapshot['mode'] ?? '')), 0, 40),
		);

		$integer_keys = array('count');
		$timing_keys = array('elapsed_ms', 'runtime_ms', 'duration_ms', 'total_elapsed_ms');
		foreach ($context as $key => $value) {
			$key = sanitize_key((string) $key);
			if (!in_array($key, $integer_keys, true) && !in_array($key, $timing_keys, true)) {
				continue;
			}
			if (!is_numeric($value)) {
				continue;
			}

			$number = (float) $value;
			if (!is_finite($number) || $number < 0) {
				continue;
			}
			$number = min($number, 1000000000);
			$entry[$key] = in_array($key, $integer_keys, true) ? (int) $number : round($number, 3);
		}

		do_action('vms_event_plan_perf_trace', $entry);
	}
}

if (!function_exists('vms_event_plan_perf_query_count')) {
	function vms_event_plan_perf_query_count(): int
	{
		global $wpdb;

		return isset($wpdb->num_queries) ? absint($wpdb->num_queries) : 0;
	}
}

if (!function_exists('vms_event_plan_perf_should_capture_queries')) {
	function vms_event_plan_perf_should_capture_queries(): bool
	{
		if (!vms_event_plan_perf_trace_enabled()) {
			return false;
		}
		if (function_exists('is_admin') && !is_admin()) {
			return false;
		}
		if (vms_event_plan_perf_baseline_screen() !== '') {
			return true;
		}
		if (vms_event_plan_perf_current_plan_id() > 0) {
			return true;
		}

		$request = vms_event_plan_perf_request_data();
		$post_type = isset($request['post_type']) ? sanitize_key((string) $request['post_type']) : '';
		return $post_type === 'vms_event_plan';
	}
}

if (!function_exists('vms_event_plan_perf_maybe_enable_query_capture')) {
	function vms_event_plan_perf_maybe_enable_query_capture(): void
	{
		global $wpdb;

		if (!vms_event_plan_perf_should_capture_queries() || !is_object($wpdb)) {
			return;
		}

		if (!defined('SAVEQUERIES')) {
			define('SAVEQUERIES', true);
		}

		if (property_exists($wpdb, 'save_queries')) {
			$wpdb->save_queries = true;
		}
		if (!isset($wpdb->queries) || !is_array($wpdb->queries)) {
			$wpdb->queries = array();
		}
	}
}
vms_event_plan_perf_maybe_enable_query_capture();

if (!function_exists('vms_event_plan_perf_query_entries')) {
	function vms_event_plan_perf_query_entries(): array
	{
		global $wpdb;

		$savequeries_enabled = (defined('SAVEQUERIES') && SAVEQUERIES) || (!empty($wpdb->save_queries));
		if (!is_object($wpdb) || !$savequeries_enabled || !isset($wpdb->queries) || !is_array($wpdb->queries)) {
			return array();
		}

		return $wpdb->queries;
	}
}

if (!function_exists('vms_event_plan_perf_sanitize_query_caller')) {
	function vms_event_plan_perf_sanitize_query_caller(string $caller): string
	{
		$caller = preg_replace('/\s+/', ' ', trim($caller));
		$caller = is_string($caller) ? $caller : '';
		if ($caller === '') {
			return 'unknown';
		}

		if (strlen($caller) > 200) {
			$caller = substr($caller, 0, 200);
		}

		return sanitize_text_field($caller);
	}
}

if (!function_exists('vms_event_plan_perf_classify_query_source')) {
	function vms_event_plan_perf_classify_query_source(string $caller, string $sql): string
	{
		$caller = strtolower($caller);
		$sql = strtolower($sql);

		if (
			strpos($caller, 'vms_') !== false
			|| strpos($caller, 'vmsadmin') !== false
			|| strpos($caller, 'vms_admin') !== false
			|| strpos($caller, 'vms\\') !== false
		) {
			return 'vms';
		}
		if (
			strpos($caller, 'woocommerce') !== false
			|| strpos($caller, 'wc_') !== false
			|| strpos($caller, 'wc->') !== false
			|| strpos($caller, 'automattic\\woocommerce') !== false
		) {
			return 'woo';
		}
		if (
			strpos($caller, 'tribe__') !== false
			|| strpos($caller, 'tribe_') !== false
			|| strpos($caller, 'event_tickets') !== false
			|| strpos($caller, 'tec') !== false
		) {
			return 'tec';
		}
		if (
			strpos($caller, 'wp_') !== false
			|| strpos($caller, 'wp->') !== false
			|| strpos($caller, 'wpdb') !== false
			|| strpos($caller, 'get_post') !== false
			|| strpos($caller, 'get_metadata') !== false
			|| strpos($caller, 'update_metadata') !== false
			|| strpos($caller, 'wp_cache') !== false
		) {
			return 'core';
		}
		if (strpos($sql, 'woocommerce') !== false) {
			return 'woo';
		}
		if (strpos($sql, 'tribe_') !== false || strpos($sql, 'tec_') !== false) {
			return 'tec';
		}

		return 'unknown';
	}
}

if (!function_exists('vms_event_plan_perf_normalize_query_pattern')) {
	function vms_event_plan_perf_normalize_query_pattern(string $sql): string
	{
		$sql = preg_replace('/\/\*.*?\*\//s', ' ', $sql);
		$sql = preg_replace('/--[^\r\n]*/', ' ', $sql);
		$sql = preg_replace("/'(?:\\\\.|[^'\\\\])*'/", "'?'", $sql);
		$sql = preg_replace('/"(?:\\\\.|[^"\\\\])*"/', '"?"', $sql);
		$sql = preg_replace('/\b0x[0-9a-f]+\b/i', '?', $sql);
		$sql = preg_replace('/\b\d+\b/', '?', $sql);
		$sql = preg_replace('/\bIN\s*\((?:\s*\?,?)+\s*\)/i', 'IN (?)', $sql);
		$sql = preg_replace('/\bVALUES\s*\((?:[^()]|\([^)]*\))*\)/i', 'VALUES (...)', $sql);
		$sql = preg_replace('/\s+/', ' ', trim((string) $sql));

		if (strlen($sql) > 220) {
			$sql = substr($sql, 0, 220) . '...';
		}

		return sanitize_text_field($sql);
	}
}

if (!function_exists('vms_event_plan_perf_query_descriptor')) {
	function vms_event_plan_perf_query_descriptor(string $sql): array
	{
		$table = '';
		if (preg_match('/\b(?:FROM|UPDATE|INTO|JOIN)\s+`?([a-z0-9_]+)`?/i', $sql, $matches)) {
			$table = sanitize_key((string) ($matches[1] ?? ''));
		}

		$repeat_scope = '';
		$repeat_key = '';
		$object_id = '';

		if (strpos($table, 'postmeta') !== false) {
			$repeat_scope = 'meta_key';
			if (preg_match("/meta_key`?\s*=\s*'([^']+)'/i", $sql, $matches)) {
				$repeat_key = sanitize_text_field((string) ($matches[1] ?? ''));
			}
			if (preg_match('/post_id`?\s*=\s*(\d+)/i', $sql, $matches)) {
				$object_id = 'post:' . absint($matches[1]);
			}
		} elseif (strpos($table, 'options') !== false) {
			$repeat_scope = 'option_name';
			if (preg_match("/option_name`?\s*=\s*'([^']+)'/i", $sql, $matches)) {
				$repeat_key = sanitize_text_field((string) ($matches[1] ?? ''));
			}
		} elseif (strpos($table, 'posts') !== false) {
			$repeat_scope = 'post_lookup';
			if (preg_match('/\bID`?\s*=\s*(\d+)/i', $sql, $matches)) {
				$object_id = 'post:' . absint($matches[1]);
			}
			if (preg_match("/post_type`?\s*=\s*'([^']+)'/i", $sql, $matches)) {
				$repeat_key = sanitize_key((string) ($matches[1] ?? ''));
			}
		}

		return array(
			'table' => $table,
			'repeat_scope' => $repeat_scope,
			'repeat_key' => $repeat_key,
			'object_id' => $object_id,
		);
	}
}

if (!function_exists('vms_event_plan_perf_emit_query_fingerprints')) {
	function vms_event_plan_perf_emit_query_fingerprints(int $plan_id, string $phase, array $query_entries, array $context = array(), int $limit = 8): void
	{
		if (!vms_event_plan_perf_trace_enabled() || empty($query_entries)) {
			return;
		}

		$fingerprints = array();
		foreach ($query_entries as $query_entry) {
			if (!is_array($query_entry)) {
				continue;
			}

			$sql = (string) ($query_entry[0] ?? '');
			$elapsed_seconds = (float) ($query_entry[1] ?? 0.0);
			$caller = vms_event_plan_perf_sanitize_query_caller((string) ($query_entry[2] ?? ''));
			if ($sql === '') {
				continue;
			}

			$pattern = vms_event_plan_perf_normalize_query_pattern($sql);
			$source = vms_event_plan_perf_classify_query_source($caller, $sql);
			$descriptor = vms_event_plan_perf_query_descriptor($sql);
			$bucket_key = md5($source . '|' . $pattern);

			if (!isset($fingerprints[$bucket_key])) {
				$fingerprints[$bucket_key] = array(
					'pattern' => $pattern,
					'source' => $source,
					'count' => 0,
					'total_elapsed_ms' => 0.0,
					'sample_caller' => $caller,
					'table' => (string) ($descriptor['table'] ?? ''),
					'repeat_scope' => (string) ($descriptor['repeat_scope'] ?? ''),
					'repeat_key' => (string) ($descriptor['repeat_key'] ?? ''),
					'object_ids' => array(),
				);
			}

			$fingerprints[$bucket_key]['count']++;
			$fingerprints[$bucket_key]['total_elapsed_ms'] += max(0.0, $elapsed_seconds * 1000);
			if ($caller !== 'unknown' && $fingerprints[$bucket_key]['sample_caller'] === 'unknown') {
				$fingerprints[$bucket_key]['sample_caller'] = $caller;
			}

			$object_id = (string) ($descriptor['object_id'] ?? '');
			if ($object_id !== '') {
				$fingerprints[$bucket_key]['object_ids'][$object_id] = true;
			}
		}

		if (empty($fingerprints)) {
			return;
		}

		uasort($fingerprints, static function (array $left, array $right): int {
			if ($left['count'] === $right['count']) {
				return $right['total_elapsed_ms'] <=> $left['total_elapsed_ms'];
			}
			return $right['count'] <=> $left['count'];
		});

		$rank = 0;
		foreach ($fingerprints as $fingerprint) {
			$rank++;
			if ($rank > $limit) {
				break;
			}

			$unique_object_count = count((array) ($fingerprint['object_ids'] ?? array()));
			vms_event_plan_perf_log('event_plan_query_fingerprint', $plan_id, $context + array(
				'phase' => $phase,
				'fingerprint_rank' => $rank,
				'source' => sanitize_key((string) ($fingerprint['source'] ?? 'unknown')),
				'pattern' => (string) ($fingerprint['pattern'] ?? ''),
				'fingerprint_count' => absint($fingerprint['count'] ?? 0),
				'total_elapsed_ms' => round((float) ($fingerprint['total_elapsed_ms'] ?? 0.0), 3),
				'sample_caller' => (string) ($fingerprint['sample_caller'] ?? 'unknown'),
				'table' => sanitize_key((string) ($fingerprint['table'] ?? '')),
				'repeat_scope' => sanitize_key((string) ($fingerprint['repeat_scope'] ?? '')),
				'repeat_key' => (string) ($fingerprint['repeat_key'] ?? ''),
				'unique_object_count' => $unique_object_count,
				'same_object_repeated' => ($unique_object_count === 1 && absint($fingerprint['count'] ?? 0) > 1) ? 1 : 0,
			));
		}
	}
}

if (!function_exists('vms_event_plan_perf_query_checkpoint')) {
	function vms_event_plan_perf_query_checkpoint(int $plan_id, string $phase, array $context = array(), string $group = 'admin_boot', bool $emit_fingerprints = true): void
	{
		if (!vms_event_plan_perf_trace_enabled()) {
			return;
		}

		vms_event_plan_perf_maybe_enable_query_capture();

		$plan_id = absint($plan_id);
		$phase = sanitize_key($phase);
		$group = sanitize_key($group);
		if ($phase === '') {
			$phase = 'checkpoint';
		}
		if ($group === '') {
			$group = 'default';
		}

		$query_entries = vms_event_plan_perf_query_entries();
		$query_index = count($query_entries);
		$query_count = vms_event_plan_perf_query_count();

		$state = vms_event_plan_perf_request_state();
		if (!isset($state['query_checkpoints']) || !is_array($state['query_checkpoints'])) {
			$state['query_checkpoints'] = array();
		}

		$checkpoint_key = $group . ':' . $plan_id;
		$previous = is_array($state['query_checkpoints'][$checkpoint_key] ?? null)
			? $state['query_checkpoints'][$checkpoint_key]
			: array();

		$previous_phase = sanitize_key((string) ($previous['phase'] ?? ''));
		$previous_query_index = absint($previous['query_index'] ?? 0);
		$previous_query_count = absint($previous['query_count'] ?? 0);

		$delta_entries = ($previous_query_index > 0 || !empty($query_entries))
			? array_slice($query_entries, $previous_query_index)
			: array();
		$query_count_delta = max(0, $query_count - $previous_query_count);
		$query_index_delta = max(0, $query_index - $previous_query_index);
		$query_elapsed_ms = 0.0;
		foreach ($delta_entries as $delta_entry) {
			if (!is_array($delta_entry)) {
				continue;
			}
			$query_elapsed_ms += max(0.0, (float) ($delta_entry[1] ?? 0.0) * 1000);
		}

		$context['phase'] = $phase;
		$context['checkpoint_group'] = $group;
		$context['query_count_current'] = $query_count;
		$context['query_count_delta'] = $query_count_delta;
		$context['query_index_current'] = $query_index;
		$context['query_index_delta'] = $query_index_delta;
		$context['query_elapsed_ms_total'] = round($query_elapsed_ms, 3);
		$context['previous_phase'] = $previous_phase !== '' ? $previous_phase : 'none';
		$context['query_trace_enabled'] = !empty($query_entries) ? 1 : 0;

		vms_event_plan_perf_log('event_plan_admin_boot_hook', $plan_id, $context);

		if ($emit_fingerprints && !empty($delta_entries)) {
			vms_event_plan_perf_emit_query_fingerprints($plan_id, $phase, $delta_entries, $context);
		}

		$state['query_checkpoints'][$checkpoint_key] = array(
			'phase' => $phase,
			'query_index' => $query_index,
			'query_count' => $query_count,
		);
		vms_event_plan_perf_save_request_state($state);
	}
}

if (!function_exists('vms_event_plan_perf_dependency_snapshot')) {
	function vms_event_plan_perf_dependency_snapshot(): array
	{
		$included_files = function_exists('get_included_files') ? (array) get_included_files() : array();
		$included_file_count = count($included_files);
		$vms_file_count = 0;
		$woo_file_count = 0;
		$tec_file_count = 0;
		$ticketing_file_count = 0;
		$staffing_file_count = 0;
		$vendor_file_count = 0;
		$integrity_file_count = 0;

		foreach ($included_files as $included_file) {
			$included_file = wp_normalize_path((string) $included_file);
			if ($included_file === '') {
				continue;
			}

			if (strpos($included_file, '/plugins/vms/') !== false) {
				$vms_file_count++;
			}
			if (strpos($included_file, '/plugins/woocommerce/') !== false) {
				$woo_file_count++;
			}
			if (
				strpos($included_file, '/plugins/the-events-calendar/') !== false
				|| strpos($included_file, '/plugins/event-tickets/') !== false
				|| strpos($included_file, '/plugins/event-tickets-plus/') !== false
			) {
				$tec_file_count++;
			}
			if (strpos($included_file, '/includes/ticketing/') !== false || strpos($included_file, '/partials/ticketing-') !== false) {
				$ticketing_file_count++;
			}
			if (strpos($included_file, '/includes/core/staff') !== false || strpos($included_file, '/partials/staff') !== false) {
				$staffing_file_count++;
			}
			if (strpos($included_file, '/vendor-availability') !== false || strpos($included_file, '/vendor-type') !== false || strpos($included_file, '/vendor-category') !== false) {
				$vendor_file_count++;
			}
			if (strpos($included_file, '/integrity') !== false) {
				$integrity_file_count++;
			}
		}

		return array(
			'included_file_count' => $included_file_count,
			'included_vms_file_count' => $vms_file_count,
			'included_woo_file_count' => $woo_file_count,
			'included_tec_file_count' => $tec_file_count,
			'included_ticketing_file_count' => $ticketing_file_count,
			'included_staffing_file_count' => $staffing_file_count,
			'included_vendor_file_count' => $vendor_file_count,
			'included_integrity_file_count' => $integrity_file_count,
			'declared_class_count' => count(get_declared_classes()),
			'declared_interface_count' => count(get_declared_interfaces()),
			'declared_trait_count' => function_exists('get_declared_traits') ? count(get_declared_traits()) : 0,
			'woo_loaded' => class_exists('WooCommerce', false) ? 1 : 0,
			'woo_order_loaded' => class_exists('WC_Order', false) ? 1 : 0,
			'tec_loaded' => (class_exists('Tribe__Events__Main', false) || function_exists('tribe_get_events')) ? 1 : 0,
			'ticketing_helper_loaded' => function_exists('vms_ticketing_v2_get_saved_config') ? 1 : 0,
			'vendor_availability_helper_loaded' => function_exists('vms_get_vendor_availability_for_date') ? 1 : 0,
			'integrity_helper_loaded' => function_exists('vms_event_plan_clear_integrity_flags') ? 1 : 0,
			'comp_default_helper_loaded' => function_exists('vms_get_event_plan_effective_comp_default') ? 1 : 0,
		);
	}
}

if (!function_exists('vms_event_plan_perf_memory_checkpoint')) {
	function vms_event_plan_perf_memory_checkpoint(int $plan_id, string $phase, array $context = array(), string $group = 'default'): void
	{
		if (!vms_event_plan_perf_trace_enabled()) {
			return;
		}

		$plan_id = absint($plan_id);
		$group = sanitize_key($group);
		$phase = sanitize_key($phase);
		if ($group === '') {
			$group = 'default';
		}
		if ($phase === '') {
			$phase = 'checkpoint';
		}

		$memory_usage_bytes = function_exists('memory_get_usage') ? (int) memory_get_usage(true) : 0;
		$peak_memory_bytes = function_exists('memory_get_peak_usage') ? (int) memory_get_peak_usage(true) : 0;
		$query_count = vms_event_plan_perf_query_count();

		$state = vms_event_plan_perf_request_state();
		if (!isset($state['memory_checkpoints']) || !is_array($state['memory_checkpoints'])) {
			$state['memory_checkpoints'] = array();
		}

		$checkpoint_key = $group . ':' . $plan_id;
		$previous = is_array($state['memory_checkpoints'][$checkpoint_key] ?? null)
			? $state['memory_checkpoints'][$checkpoint_key]
			: array();

		$previous_memory_bytes = (int) ($previous['memory_usage_bytes'] ?? $memory_usage_bytes);
		$previous_peak_bytes = (int) ($previous['peak_memory_bytes'] ?? $peak_memory_bytes);
		$previous_query_count = (int) ($previous['query_count'] ?? $query_count);
		$previous_phase = sanitize_key((string) ($previous['phase'] ?? ''));

		$context['phase'] = $phase;
		$context['checkpoint_group'] = $group;
		$context['memory_usage_bytes'] = $memory_usage_bytes;
		$context['memory_usage_mb'] = round((float) $memory_usage_bytes / 1048576, 2);
		$context['peak_memory_bytes'] = $peak_memory_bytes;
		$context['peak_memory_mb'] = round((float) $peak_memory_bytes / 1048576, 2);
		$context['query_count_current'] = $query_count;
		$context['memory_delta_from_prev_bytes'] = $memory_usage_bytes - $previous_memory_bytes;
		$context['memory_delta_from_prev_mb'] = round((float) ($memory_usage_bytes - $previous_memory_bytes) / 1048576, 2);
		$context['peak_memory_delta_from_prev_bytes'] = $peak_memory_bytes - $previous_peak_bytes;
		$context['peak_memory_delta_from_prev_mb'] = round((float) ($peak_memory_bytes - $previous_peak_bytes) / 1048576, 2);
		$context['query_count_delta_from_prev'] = $query_count - $previous_query_count;
		$context['previous_phase'] = $previous_phase !== '' ? $previous_phase : 'none';

		$capture_dependency_snapshot = !empty($context['capture_dependency_snapshot']);
		unset($context['capture_dependency_snapshot']);
		if ($capture_dependency_snapshot) {
			$context += vms_event_plan_perf_dependency_snapshot();
		}

		vms_event_plan_perf_log('event_plan_memory_checkpoint', $plan_id, $context);

		$state['memory_checkpoints'][$checkpoint_key] = array(
			'phase' => $phase,
			'memory_usage_bytes' => $memory_usage_bytes,
			'peak_memory_bytes' => $peak_memory_bytes,
			'query_count' => $query_count,
		);
		vms_event_plan_perf_save_request_state($state);
	}
}

if (!function_exists('vms_event_plan_perf_span_start')) {
	function vms_event_plan_perf_span_start(string $hook_name, int $plan_id = 0, array $context = array()): string
	{
		if (!vms_event_plan_perf_trace_enabled()) {
			return '';
		}

		$token = sanitize_key(str_replace(array('.', ':'), '_', $hook_name)) . '_' . wp_generate_password(12, false, false);
		if (!isset($GLOBALS['vms_event_plan_perf_spans']) || !is_array($GLOBALS['vms_event_plan_perf_spans'])) {
			$GLOBALS['vms_event_plan_perf_spans'] = array();
		}

		$GLOBALS['vms_event_plan_perf_spans'][$token] = array(
			'started_at' => microtime(true),
			'query_count_start' => vms_event_plan_perf_query_count(),
			'memory_usage_start_bytes' => function_exists('memory_get_usage') ? (int) memory_get_usage(true) : 0,
			'peak_memory_start_bytes' => function_exists('memory_get_peak_usage') ? (int) memory_get_peak_usage(true) : 0,
		);
		$context['phase'] = 'start';
		vms_event_plan_perf_log($hook_name, $plan_id, $context);
		return $token;
	}
}

if (!function_exists('vms_event_plan_perf_span_finish')) {
	function vms_event_plan_perf_span_finish(string $hook_name, int $plan_id, string $token, array $context = array()): void
	{
		if (!vms_event_plan_perf_trace_enabled()) {
			return;
		}

		$started_at = microtime(true);
		$query_count_start = vms_event_plan_perf_query_count();
		$memory_usage_start_bytes = function_exists('memory_get_usage') ? (int) memory_get_usage(true) : 0;
		$peak_memory_start_bytes = function_exists('memory_get_peak_usage') ? (int) memory_get_peak_usage(true) : 0;
		if (isset($GLOBALS['vms_event_plan_perf_spans'][$token])) {
			$span_state = $GLOBALS['vms_event_plan_perf_spans'][$token];
			if (is_array($span_state)) {
				$started_at = (float) ($span_state['started_at'] ?? $started_at);
				$query_count_start = absint($span_state['query_count_start'] ?? $query_count_start);
				$memory_usage_start_bytes = (int) ($span_state['memory_usage_start_bytes'] ?? $memory_usage_start_bytes);
				$peak_memory_start_bytes = (int) ($span_state['peak_memory_start_bytes'] ?? $peak_memory_start_bytes);
			} else {
				$started_at = (float) $span_state;
			}
			unset($GLOBALS['vms_event_plan_perf_spans'][$token]);
		}

		$query_count_end = vms_event_plan_perf_query_count();
		$memory_usage_end_bytes = function_exists('memory_get_usage') ? (int) memory_get_usage(true) : 0;
		$peak_memory_end_bytes = function_exists('memory_get_peak_usage') ? (int) memory_get_peak_usage(true) : 0;
		$context['phase'] = 'end';
		$context['elapsed_ms'] = (int) round(max(0.0, microtime(true) - $started_at) * 1000);
		$context['query_count_start'] = $query_count_start;
		$context['query_count_end'] = $query_count_end;
		$context['query_count_delta'] = max(0, $query_count_end - $query_count_start);
		$context['memory_usage_delta_bytes'] = max(0, $memory_usage_end_bytes - $memory_usage_start_bytes);
		$context['memory_usage_delta_mb'] = round(max(0, $memory_usage_end_bytes - $memory_usage_start_bytes) / 1048576, 2);
		$context['peak_memory_delta_bytes'] = max(0, $peak_memory_end_bytes - $peak_memory_start_bytes);
		$context['peak_memory_delta_mb'] = round(max(0, $peak_memory_end_bytes - $peak_memory_start_bytes) / 1048576, 2);
		vms_event_plan_perf_log($hook_name, $plan_id, $context);
	}
}

if (!function_exists('vms_event_plan_perf_post_update_changed_fields')) {
	function vms_event_plan_perf_post_update_changed_fields(int $post_id, array $postarr): array
	{
		$post_id = absint($post_id);
		if ($post_id <= 0) {
			return array();
		}

		$post = get_post($post_id);
		if (!$post instanceof WP_Post) {
			return array();
		}

		$changed_fields = array();
		foreach ($postarr as $field => $value) {
			$field = sanitize_key((string) $field);
			if ($field === '' || $field === 'id') {
				continue;
			}

			$current_value = get_post_field($field, $post_id, 'raw');
			$current_value = is_scalar($current_value) || $current_value === null
				? (string) $current_value
				: maybe_serialize($current_value);
			$next_value = is_scalar($value) || $value === null
				? (string) $value
				: maybe_serialize($value);

			if ($field === 'post_status') {
				$current_value = sanitize_key($current_value);
				$next_value = sanitize_key($next_value);
			}

			if ($current_value !== $next_value) {
				$changed_fields[] = $field;
			}
		}

		return array_values(array_unique(array_filter($changed_fields)));
	}
}

if (!function_exists('vms_event_plan_perf_wp_update_post')) {
	function vms_event_plan_perf_wp_update_post(array $postarr, string $reason = '', int $plan_id = 0)
	{
		$target_post_id = absint($postarr['ID'] ?? 0);
		$plan_id = absint($plan_id);
		if ($plan_id <= 0 && $target_post_id > 0 && get_post_type($target_post_id) === 'vms_event_plan') {
			$plan_id = $target_post_id;
		}

		if (!vms_event_plan_perf_trace_enabled() || $plan_id <= 0) {
			return wp_update_post($postarr);
		}

		$target_post_type = $target_post_id > 0 ? sanitize_key((string) get_post_type($target_post_id)) : '';
		$fields = array_values(array_filter(array_map('sanitize_key', array_keys($postarr)), static function ($field) {
			return $field !== 'id' && $field !== '';
		}));
		$trace = vms_event_plan_perf_span_start(
			'vms_internal_wp_update_post',
			$plan_id,
			array(
				'reason' => $reason,
				'target_post_id' => $target_post_id,
				'target_post_type' => $target_post_type,
				'fields' => $fields,
				'internal_wp_update_post_count' => vms_event_plan_perf_increment_plan_counter('internal_wp_update_post_count', $plan_id),
			)
		);

		try {
			return wp_update_post($postarr);
		} finally {
			vms_event_plan_perf_span_finish(
				'vms_internal_wp_update_post',
				$plan_id,
				$trace,
				array(
					'reason' => $reason,
					'target_post_id' => $target_post_id,
					'target_post_type' => $target_post_type,
					'fields' => $fields,
				)
			);
		}
	}
}

if (!function_exists('vms_event_plan_perf_track_save_pass')) {
	function vms_event_plan_perf_track_save_pass(int $post_id, WP_Post $post, bool $update): void
	{
		if (!vms_event_plan_perf_trace_enabled()) {
			return;
		}
		if ($post->post_type !== 'vms_event_plan' || wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
			return;
		}

		$pass = vms_event_plan_perf_increment_plan_counter('save_pass_count', $post_id);
		vms_event_plan_perf_log(
			'save_post_vms_event_plan_pass',
			$post_id,
			array(
				'phase' => 'pass',
				'save_pass' => $pass,
				'update' => $update ? 1 : 0,
				'post_status_before' => isset(vms_event_plan_perf_post_data()['original_post_status']) ? sanitize_key((string) vms_event_plan_perf_post_data()['original_post_status']) : '',
				'post_status_after' => sanitize_key((string) $post->post_status),
			)
		);
	}
}
add_action('save_post_vms_event_plan', 'vms_event_plan_perf_track_save_pass', 0, 3);

if (!function_exists('vms_event_plan_perf_detect_admin_request')) {
	function vms_event_plan_perf_detect_admin_request(): array
	{
		$plan_id = vms_event_plan_perf_current_plan_id();
		$request = vms_event_plan_perf_request_data();
		$post_type = isset($request['post_type']) ? sanitize_key((string) $request['post_type']) : '';
		if ($post_type === '' && $plan_id > 0) {
			$post_type = sanitize_key((string) get_post_type($plan_id));
		}

		return array(
			'plan_id' => $plan_id,
			'post_type' => $post_type,
			'screen_action' => isset($request['action']) ? sanitize_key((string) $request['action']) : '',
		);
	}
}

if (!function_exists('vms_event_plan_perf_start_admin_screen_baseline')) {
	function vms_event_plan_perf_start_admin_screen_baseline(): void
	{
		if (!vms_event_plan_perf_trace_enabled() || !is_admin()) {
			return;
		}

		$baseline_screen = vms_event_plan_perf_baseline_screen();
		if ($baseline_screen === '') {
			return;
		}

		$state = vms_event_plan_perf_request_state();
		if (!empty($state['admin_screen_baseline'])) {
			return;
		}

		$plan_id = vms_event_plan_perf_current_plan_id();
		$token = vms_event_plan_perf_span_start(
			'event_plan_memory_baseline',
			$plan_id,
			array(
				'screen' => $baseline_screen,
				'section' => 'admin_screen_baseline',
			)
		);

		vms_event_plan_perf_memory_checkpoint(
			$plan_id,
			'baseline_start',
			array(
				'screen' => $baseline_screen,
				'section' => 'admin_screen_baseline',
				'capture_dependency_snapshot' => 1,
			),
			'baseline_' . $baseline_screen
		);

		$state['admin_screen_baseline'] = array(
			'token' => $token,
			'plan_id' => $plan_id,
			'screen' => $baseline_screen,
		);
		vms_event_plan_perf_save_request_state($state);
	}
}
add_action('current_screen', 'vms_event_plan_perf_start_admin_screen_baseline', 1);

if (!function_exists('vms_event_plan_perf_finish_admin_screen_baseline')) {
	function vms_event_plan_perf_finish_admin_screen_baseline(): void
	{
		if (!vms_event_plan_perf_trace_enabled() || !is_admin()) {
			return;
		}

		$state = vms_event_plan_perf_request_state();
		$baseline = is_array($state['admin_screen_baseline'] ?? null) ? $state['admin_screen_baseline'] : array();
		$token = sanitize_text_field((string) ($baseline['token'] ?? ''));
		$screen = sanitize_key((string) ($baseline['screen'] ?? ''));
		if ($token === '' || $screen === '') {
			return;
		}

		$plan_id = absint($baseline['plan_id'] ?? 0);
		vms_event_plan_perf_span_finish(
			'event_plan_memory_baseline',
			$plan_id,
			$token,
			array(
				'screen' => $screen,
				'section' => 'admin_screen_baseline',
			)
		);

		vms_event_plan_perf_memory_checkpoint(
			$plan_id,
			'baseline_end',
			array(
				'screen' => $screen,
				'section' => 'admin_screen_baseline',
				'capture_dependency_snapshot' => 1,
			),
			'baseline_' . $screen
		);

		$memory_usage_bytes = function_exists('memory_get_usage') ? (int) memory_get_usage(true) : 0;
		$peak_memory_bytes = function_exists('memory_get_peak_usage') ? (int) memory_get_peak_usage(true) : 0;
		vms_event_plan_perf_log(
			'event_plan_memory_baseline',
			$plan_id,
			array(
				'phase' => 'complete',
				'screen' => $screen,
				'section' => 'admin_screen_baseline',
				'memory_usage_bytes' => $memory_usage_bytes,
				'memory_usage_mb' => round((float) $memory_usage_bytes / 1048576, 2),
				'peak_memory_bytes' => $peak_memory_bytes,
				'peak_memory_mb' => round((float) $peak_memory_bytes / 1048576, 2),
				'query_count_total' => vms_event_plan_perf_query_count(),
			) + vms_event_plan_perf_dependency_snapshot()
		);

		unset($state['admin_screen_baseline']);
		vms_event_plan_perf_save_request_state($state);
	}
}
add_action('shutdown', 'vms_event_plan_perf_finish_admin_screen_baseline', 999);

if (!function_exists('vms_event_plan_perf_start_admin_screen_boot')) {
	function vms_event_plan_perf_start_admin_screen_boot(): void
	{
		if (!vms_event_plan_perf_trace_enabled()) {
			return;
		}

		$context = vms_event_plan_perf_detect_admin_request();
		if (($context['post_type'] ?? '') !== 'vms_event_plan') {
			return;
		}

		$token = vms_event_plan_perf_span_start(
			'event_plan_admin_screen_boot',
			(int) ($context['plan_id'] ?? 0),
			array(
				'section' => 'admin_screen_boot',
				'screen_action' => $context['screen_action'] ?? '',
			)
		);

		vms_event_plan_perf_memory_checkpoint(
			(int) ($context['plan_id'] ?? 0),
			'admin_boot_start',
			array(
				'section' => 'admin_screen_boot',
				'capture_dependency_snapshot' => 1,
			),
			'admin_boot'
		);

		$state = vms_event_plan_perf_request_state();
		$state['admin_screen_boot'] = array(
			'token' => $token,
			'plan_id' => absint($context['plan_id'] ?? 0),
			'screen_action' => sanitize_key((string) ($context['screen_action'] ?? '')),
		);
		vms_event_plan_perf_save_request_state($state);

		vms_event_plan_perf_query_checkpoint(
			(int) ($context['plan_id'] ?? 0),
			'load_post',
			array(
				'section' => 'admin_screen_boot',
				'screen_action' => $context['screen_action'] ?? '',
				'before_details_render' => 1,
			),
			'admin_boot'
		);
	}
}
add_action('load-post.php', 'vms_event_plan_perf_start_admin_screen_boot', 1);
add_action('load-post-new.php', 'vms_event_plan_perf_start_admin_screen_boot', 1);

if (!function_exists('vms_event_plan_perf_track_admin_boot_phase')) {
	function vms_event_plan_perf_track_admin_boot_phase(string $phase, array $context = array()): void
	{
		if (!vms_event_plan_perf_trace_enabled()) {
			return;
		}

		$state = vms_event_plan_perf_request_state();
		$boot = is_array($state['admin_screen_boot'] ?? null) ? $state['admin_screen_boot'] : array();
		$plan_id = absint($boot['plan_id'] ?? 0);
		if ($plan_id <= 0 && (vms_event_plan_perf_detect_admin_request()['post_type'] ?? '') !== 'vms_event_plan') {
			return;
		}

		vms_event_plan_perf_query_checkpoint(
			$plan_id,
			$phase,
			$context + array(
				'before_details_render' => 1,
			),
			'admin_boot'
		);
	}
}

if (!function_exists('vms_event_plan_perf_track_current_screen_phase')) {
	function vms_event_plan_perf_track_current_screen_phase($screen): void
	{
		$screen_id = '';
		$screen_base = '';
		$screen_post_type = '';
		if ($screen instanceof WP_Screen) {
			$screen_id = sanitize_key((string) $screen->id);
			$screen_base = sanitize_key((string) $screen->base);
			$screen_post_type = sanitize_key((string) $screen->post_type);
		}

		if ($screen_post_type !== 'vms_event_plan') {
			return;
		}

		vms_event_plan_perf_track_admin_boot_phase('current_screen', array(
			'section' => 'admin_screen_boot',
			'screen_id' => $screen_id,
			'screen_base' => $screen_base,
			'screen_post_type' => $screen_post_type,
		));
	}
}
add_action('current_screen', 'vms_event_plan_perf_track_current_screen_phase', 999);

if (!function_exists('vms_event_plan_perf_track_admin_enqueue_phase')) {
	function vms_event_plan_perf_track_admin_enqueue_phase($hook_suffix = ''): void
	{
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if (!$screen instanceof WP_Screen || sanitize_key((string) $screen->post_type) !== 'vms_event_plan') {
			return;
		}

		vms_event_plan_perf_track_admin_boot_phase('admin_enqueue', array(
			'section' => 'admin_screen_boot',
			'hook_suffix' => sanitize_key((string) $hook_suffix),
			'screen_id' => sanitize_key((string) $screen->id),
		));
	}
}
add_action('admin_enqueue_scripts', 'vms_event_plan_perf_track_admin_enqueue_phase', 999);

if (!function_exists('vms_event_plan_perf_track_metabox_phase')) {
	function vms_event_plan_perf_track_metabox_phase($post): void
	{
		$post = $post instanceof WP_Post ? $post : get_post($post);
		if (!$post instanceof WP_Post || $post->post_type !== 'vms_event_plan') {
			return;
		}

		vms_event_plan_perf_track_admin_boot_phase('metabox_registration', array(
			'section' => 'admin_screen_boot',
			'post_id' => (int) $post->ID,
		));
	}
}
add_action('add_meta_boxes_vms_event_plan', 'vms_event_plan_perf_track_metabox_phase', 999);

if (!function_exists('vms_event_plan_perf_track_admin_head_phase')) {
	function vms_event_plan_perf_track_admin_head_phase(): void
	{
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if (!$screen instanceof WP_Screen || sanitize_key((string) $screen->post_type) !== 'vms_event_plan') {
			return;
		}

		vms_event_plan_perf_track_admin_boot_phase('admin_head', array(
			'section' => 'admin_screen_boot',
			'screen_id' => sanitize_key((string) $screen->id),
		));
	}
}
add_action('admin_head-post.php', 'vms_event_plan_perf_track_admin_head_phase', 999);
add_action('admin_head-post-new.php', 'vms_event_plan_perf_track_admin_head_phase', 999);

if (!function_exists('vms_event_plan_perf_finish_admin_screen_boot')) {
	function vms_event_plan_perf_finish_admin_screen_boot(): void
	{
		if (!vms_event_plan_perf_trace_enabled()) {
			return;
		}

		$state = vms_event_plan_perf_request_state();
		$boot = is_array($state['admin_screen_boot'] ?? null) ? $state['admin_screen_boot'] : array();
		$token = sanitize_text_field((string) ($boot['token'] ?? ''));
		if ($token === '') {
			return;
		}

		vms_event_plan_perf_span_finish(
			'event_plan_admin_screen_boot',
			absint($boot['plan_id'] ?? 0),
			$token,
			array(
				'section' => 'admin_screen_boot',
				'screen_action' => sanitize_key((string) ($boot['screen_action'] ?? '')),
			)
		);

		vms_event_plan_perf_memory_checkpoint(
			absint($boot['plan_id'] ?? 0),
			'admin_boot_end',
			array(
				'section' => 'admin_screen_boot',
				'capture_dependency_snapshot' => 1,
			),
			'admin_boot'
		);

		unset($state['admin_screen_boot']);
		vms_event_plan_perf_save_request_state($state);
	}
}
add_action('admin_footer-post.php', 'vms_event_plan_perf_finish_admin_screen_boot', 999);
add_action('admin_footer-post-new.php', 'vms_event_plan_perf_finish_admin_screen_boot', 999);

if (!function_exists('vms_event_plan_perf_track_scheduled_event')) {
	function vms_event_plan_perf_track_scheduled_event($event)
	{
		if (!vms_event_plan_perf_trace_enabled() || !is_object($event)) {
			return $event;
		}

		$plan_id = vms_event_plan_perf_extract_plan_id_from_args($event->args ?? array());
		if ($plan_id <= 0) {
			$plan_id = vms_event_plan_perf_current_plan_id();
		}
		if ($plan_id <= 0) {
			return $event;
		}

		vms_event_plan_perf_log(
			'wp_cron_schedule',
			$plan_id,
			array(
				'job_name' => sanitize_key((string) ($event->hook ?? '')),
				'cron_hook' => sanitize_key((string) ($event->hook ?? '')),
				'cron_schedule' => sanitize_key((string) ($event->schedule ?? 'single')),
				'cron_timestamp' => absint($event->timestamp ?? 0),
				'cron_args' => $event->args ?? array(),
			)
		);

		return $event;
	}
}
add_filter('schedule_event', 'vms_event_plan_perf_track_scheduled_event', PHP_INT_MAX, 1);

if (!function_exists('vms_event_plan_perf_track_action_scheduler_enqueue')) {
	function vms_event_plan_perf_track_action_scheduler_enqueue($pre, string $hook, array $args, string $group, int $priority, bool $unique)
	{
		if (!vms_event_plan_perf_trace_enabled()) {
			return $pre;
		}

		$plan_id = vms_event_plan_perf_extract_plan_id_from_args($args);
		if ($plan_id <= 0) {
			$plan_id = vms_event_plan_perf_current_plan_id();
		}
		if ($plan_id <= 0) {
			return $pre;
		}

		vms_event_plan_perf_log(
			'action_scheduler_enqueue_async',
			$plan_id,
			array(
				'job_name' => sanitize_key($hook),
				'action_scheduler_hook' => sanitize_key($hook),
				'action_scheduler_group' => sanitize_key($group),
				'action_scheduler_priority' => $priority,
				'action_scheduler_unique' => $unique ? 1 : 0,
				'action_scheduler_args' => $args,
			)
		);

		return $pre;
	}
}
add_filter('pre_as_enqueue_async_action', 'vms_event_plan_perf_track_action_scheduler_enqueue', PHP_INT_MAX, 6);

if (!function_exists('vms_event_plan_perf_track_action_scheduler_single')) {
	function vms_event_plan_perf_track_action_scheduler_single($pre, int $timestamp, string $hook, array $args, string $group, int $priority)
	{
		if (!vms_event_plan_perf_trace_enabled()) {
			return $pre;
		}

		$plan_id = vms_event_plan_perf_extract_plan_id_from_args($args);
		if ($plan_id <= 0) {
			$plan_id = vms_event_plan_perf_current_plan_id();
		}
		if ($plan_id <= 0) {
			return $pre;
		}

		vms_event_plan_perf_log(
			'action_scheduler_schedule_single',
			$plan_id,
			array(
				'job_name' => sanitize_key($hook),
				'action_scheduler_hook' => sanitize_key($hook),
				'action_scheduler_group' => sanitize_key($group),
				'action_scheduler_priority' => $priority,
				'action_scheduler_timestamp' => $timestamp,
				'action_scheduler_args' => $args,
			)
		);

		return $pre;
	}
}
add_filter('pre_as_schedule_single_action', 'vms_event_plan_perf_track_action_scheduler_single', PHP_INT_MAX, 6);

if (!function_exists('vms_event_plan_perf_is_queue_meta_key')) {
	function vms_event_plan_perf_is_queue_meta_key(string $meta_key): bool
	{
		foreach (array(
			'_vms_calendar_maintenance_',
			'_vms_calendar_publish_',
			'_vms_staffing_seed_',
			'_vms_ticket_integrity_',
		) as $needle) {
			if (strpos($meta_key, $needle) === 0) {
				return true;
			}
		}

		return false;
	}
}

if (!function_exists('vms_event_plan_perf_trace_queue_meta_write')) {
	function vms_event_plan_perf_trace_queue_meta_write($meta_id, int $object_id, string $meta_key, $meta_value): void
	{
		unset($meta_id);

		if (!vms_event_plan_perf_trace_enabled()) {
			return;
		}
		if ($object_id <= 0 || get_post_type($object_id) !== 'vms_event_plan') {
			return;
		}
		if (!vms_event_plan_perf_is_queue_meta_key($meta_key)) {
			return;
		}

		$value_summary = '';
		if (is_scalar($meta_value) || $meta_value === null) {
			$value_summary = sanitize_text_field((string) $meta_value);
		} else {
			$value_summary = sha1(maybe_serialize($meta_value));
		}

		vms_event_plan_perf_log(
			'queue_meta_write',
			$object_id,
			array(
				'job_name' => 'queue_meta_write',
				'queue_meta_key' => sanitize_key($meta_key),
				'queue_meta_write_action' => sanitize_key((string) current_filter()),
				'queue_meta_value' => $value_summary,
			)
		);
	}
}
add_action('added_post_meta', 'vms_event_plan_perf_trace_queue_meta_write', PHP_INT_MAX, 4);
add_action('updated_post_meta', 'vms_event_plan_perf_trace_queue_meta_write', PHP_INT_MAX, 4);
add_action('deleted_post_meta', 'vms_event_plan_perf_trace_queue_meta_write', PHP_INT_MAX, 4);

if (!function_exists('vms_event_plan_perf_job_lock_key')) {
	function vms_event_plan_perf_job_lock_key(string $job_name, int $plan_id): string
	{
		$job_name = sanitize_key($job_name);
		$plan_id = absint($plan_id);
		return 'vms_ep_job_' . substr(md5($job_name . '|' . $plan_id), 0, 20);
	}
}

if (!function_exists('vms_event_plan_perf_job_has_lock')) {
	function vms_event_plan_perf_job_has_lock(string $job_name, int $plan_id): bool
	{
		$plan_id = absint($plan_id);
		if ($plan_id <= 0) {
			return false;
		}

		return !empty(get_transient(vms_event_plan_perf_job_lock_key($job_name, $plan_id)));
	}
}

if (!function_exists('vms_event_plan_perf_job_get_lock')) {
	function vms_event_plan_perf_job_get_lock(string $job_name, int $plan_id): array
	{
		$plan_id = absint($plan_id);
		if ($plan_id <= 0) {
			return array();
		}

		$lock = get_transient(vms_event_plan_perf_job_lock_key($job_name, $plan_id));
		return is_array($lock) ? $lock : array();
	}
}

if (!function_exists('vms_event_plan_perf_job_set_lock')) {
	function vms_event_plan_perf_job_set_lock(string $job_name, int $plan_id, string $state = 'pending', int $ttl = 900): void
	{
		$plan_id = absint($plan_id);
		if ($plan_id <= 0) {
			return;
		}

		set_transient(
			vms_event_plan_perf_job_lock_key($job_name, $plan_id),
			array(
				'state' => sanitize_key($state),
				'request_id' => vms_event_plan_perf_request_id(),
				'updated_at_gmt' => gmdate('Y-m-d H:i:s'),
			),
			max(60, absint($ttl))
		);
	}
}

if (!function_exists('vms_event_plan_perf_job_clear_lock')) {
	function vms_event_plan_perf_job_clear_lock(string $job_name, int $plan_id): void
	{
		$plan_id = absint($plan_id);
		if ($plan_id <= 0) {
			return;
		}

		delete_transient(vms_event_plan_perf_job_lock_key($job_name, $plan_id));
	}
}

if (!function_exists('vms_event_plan_apply_tec_author_args')) {
	function vms_event_plan_apply_tec_author_args(int $plan_id, array $args, int $existing_tec_event_id = 0, string $hook_name = ''): array
	{
		$plan_id = absint($plan_id);
		$existing_tec_event_id = absint($existing_tec_event_id);
		if ($plan_id <= 0) {
			return $args;
		}

		$before_author = $existing_tec_event_id > 0 ? absint(get_post_field('post_author', $existing_tec_event_id)) : 0;
		$needs_author = ($existing_tec_event_id <= 0) || !vms_event_plan_perf_user_is_valid($before_author);
		if (!$needs_author) {
			return $args;
		}

		$actor_user_id = vms_event_plan_get_captured_actor_user_id($plan_id);
		$resolved_author = vms_event_plan_resolve_tec_post_author($plan_id, $actor_user_id);
		if ($resolved_author > 0) {
			$args['post_author'] = $resolved_author;
		}

		vms_event_plan_perf_log(
			$hook_name !== '' ? $hook_name : 'tec_author_prepare',
			$plan_id,
			array(
				'job_name' => 'tec_author_prepare',
				'linked_tec_event_id' => $existing_tec_event_id,
				'tec_post_author_before' => $before_author,
				'tec_post_author_after' => $resolved_author,
				'actor_user_id' => $actor_user_id,
				'tec_permalink_present' => ($existing_tec_event_id > 0 && (string) get_permalink($existing_tec_event_id) !== '') ? 1 : 0,
			)
		);

		return $args;
	}
}

if (!function_exists('vms_event_plan_backfill_tec_event_author')) {
	function vms_event_plan_backfill_tec_event_author(int $plan_id, int $tec_event_id, string $hook_name = ''): int
	{
		$plan_id = absint($plan_id);
		$tec_event_id = absint($tec_event_id);
		if ($plan_id <= 0 || $tec_event_id <= 0 || get_post_type($tec_event_id) !== 'tribe_events') {
			return 0;
		}

		$before_author = absint(get_post_field('post_author', $tec_event_id));
		$actor_user_id = vms_event_plan_get_captured_actor_user_id($plan_id);
		$after_author = $before_author;
		if (!vms_event_plan_perf_user_is_valid($before_author)) {
			$resolved_author = vms_event_plan_resolve_tec_post_author($plan_id, $actor_user_id);
			if ($resolved_author > 0) {
				vms_event_plan_perf_wp_update_post(array(
					'ID' => $tec_event_id,
					'post_author' => $resolved_author,
				), $hook_name !== '' ? $hook_name . '_tec_author_backfill' : 'tec_author_backfill', $plan_id);
				clean_post_cache($tec_event_id);
				$after_author = absint(get_post_field('post_author', $tec_event_id));
			}
		}

		vms_event_plan_perf_log(
			$hook_name !== '' ? $hook_name : 'tec_author_backfill',
			$plan_id,
			array(
				'job_name' => 'tec_author_backfill',
				'linked_tec_event_id' => $tec_event_id,
				'tec_post_author_before' => $before_author,
				'tec_post_author_after' => $after_author,
				'actor_user_id' => $actor_user_id,
				'tec_permalink_present' => ((string) get_permalink($tec_event_id) !== '') ? 1 : 0,
			)
		);

		return $after_author;
	}
}
