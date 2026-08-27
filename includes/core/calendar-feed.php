<?php
defined('ABSPATH') || exit;

/**
 * Calendar feed cache-bust option.
 */
if (!defined('BVMGR_CALENDAR_FEED_CACHE_BUST_OPTION')) {
	define('BVMGR_CALENDAR_FEED_CACHE_BUST_OPTION', 'vms_calendar_feed_cache_bust');
}

if (!function_exists('vms_calendar_boolish')) {
	function vms_calendar_boolish($value, bool $default = false): bool
	{
		if (is_bool($value)) {
			return $value;
		}
		if ($value === null || $value === '') {
			return $default;
		}
		if (is_numeric($value)) {
			return ((int) $value) === 1;
		}
		$v = strtolower(trim((string) $value));
		if (in_array($v, array('1', 'true', 'yes', 'on'), true)) {
			return true;
		}
		if (in_array($v, array('0', 'false', 'no', 'off'), true)) {
			return false;
		}
		return $default;
	}
}

if (!function_exists('vms_calendar_is_valid_ymd')) {
	function vms_calendar_is_valid_ymd(string $ymd): bool
	{
		return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd);
	}
}

if (!function_exists('vms_calendar_parse_assoc_map')) {
	/**
	 * Accepts array, JSON object string, or newline "key:value" pairs.
	 *
	 * @return array<string,mixed>
	 */
	function vms_calendar_parse_assoc_map($raw): array
	{
		if (is_array($raw)) {
			return $raw;
		}

		if (!is_string($raw)) {
			return array();
		}

		$raw = trim($raw);
		if ($raw === '') {
			return array();
		}

		$decoded = bvmgr_json_decode_associative($raw, 16);
		if (
			!empty($decoded['ok'])
			&& is_array($decoded['value'])
			&& bvmgr_json_decoded_is_object($decoded['value'], (string) ($decoded['top_level_token'] ?? ''))
		) {
			return $decoded['value'];
		}

		$out = array();
		$lines = preg_split('/\r\n|\r|\n/', $raw) ?: array();
		foreach ($lines as $line) {
			$line = trim((string) $line);
			if ($line === '' || strpos($line, '#') === 0) {
				continue;
			}
			$parts = preg_split('/\s*[:=]\s*/', $line, 2);
			if (!is_array($parts) || count($parts) < 2) {
				continue;
			}
			$key = sanitize_key((string) $parts[0]);
			$val = trim((string) $parts[1]);
			if ($key === '') {
				continue;
			}
			$out[$key] = $val;
		}

		return $out;
	}
}

if (!function_exists('vms_calendar_sanitize_icon_map')) {
	/**
	 * @return array<string,string>
	 */
	function vms_calendar_sanitize_icon_map($raw): array
	{
		$parsed = vms_calendar_parse_assoc_map($raw);
		$out = array();

		foreach ($parsed as $slug => $icon) {
			$key = sanitize_key((string) $slug);
			$val = sanitize_text_field((string) $icon);
			if ($key === '' || $val === '') {
				continue;
			}
			$out[$key] = $val;
		}

		return $out;
	}
}

if (!function_exists('vms_calendar_sanitize_int_map')) {
	/**
	 * @return array<string,int>
	 */
	function vms_calendar_sanitize_int_map($raw): array
	{
		$parsed = vms_calendar_parse_assoc_map($raw);
		$out = array();
		foreach ($parsed as $slug => $value) {
			$key = sanitize_key((string) $slug);
			if ($key === '') {
				continue;
			}
			$n = max(0, absint($value));
			$out[$key] = (int) $n;
		}
		return $out;
	}
}

if (!function_exists('vms_calendar_settings')) {
	/**
	 * @return array<string,mixed>
	 */
	function vms_calendar_settings(): array
	{
		return (array) get_option('vms_settings', array());
	}
}

if (!function_exists('vms_calendar_vendor_type_icons')) {
	/**
	 * Settings key: vms_settings[calendar_vendor_type_icons]
	 *
	 * @return array<string,string>
	 */
	function vms_calendar_vendor_type_icons(): array
	{
		$settings = vms_calendar_settings();
		$icons = vms_calendar_sanitize_icon_map($settings['calendar_vendor_type_icons'] ?? array());
		return (array) apply_filters('vms_calendar_vendor_type_icons', $icons);
	}
}

if (!function_exists('vms_calendar_global_slot_limits')) {
	/**
	 * Settings key: vms_settings[calendar_default_slot_limits]
	 *
	 * @return array<string,int>
	 */
	function vms_calendar_global_slot_limits(): array
	{
		$settings = vms_calendar_settings();
		return vms_calendar_sanitize_int_map($settings['calendar_default_slot_limits'] ?? array());
	}
}

if (!function_exists('vms_calendar_vendor_visibility_map')) {
	/**
	 * Settings key: vms_settings[calendar_vendor_show_other_vendors_by_type]
	 *
	 * @return array<string,bool>
	 */
	function vms_calendar_vendor_visibility_map(): array
	{
		$raw = vms_calendar_parse_assoc_map(vms_calendar_settings()['calendar_vendor_show_other_vendors_by_type'] ?? array());
		$out = array();
		foreach ($raw as $slug => $value) {
			$key = sanitize_key((string) $slug);
			if ($key === '') {
				continue;
			}
			$out[$key] = vms_calendar_boolish($value, false);
		}
		return $out;
	}
}

if (!function_exists('vms_calendar_public_vendor_visibility_map')) {
	/**
	 * Settings key: vms_settings[calendar_public_show_vendors_by_type]
	 *
	 * @return array<string,bool>
	 */
	function vms_calendar_public_vendor_visibility_map(): array
	{
		$raw = vms_calendar_parse_assoc_map(vms_calendar_settings()['calendar_public_show_vendors_by_type'] ?? array());
		$out = array();
		foreach ($raw as $slug => $value) {
			$key = sanitize_key((string) $slug);
			if ($key === '') {
				continue;
			}
			$out[$key] = vms_calendar_boolish($value, false);
		}
		return $out;
	}
}

if (!function_exists('vms_calendar_open_slot_display_map')) {
	/**
	 * Settings key: vms_settings[calendar_open_slot_display_by_vendor_type]
	 *
	 * @return array<string,bool>
	 */
	function vms_calendar_open_slot_display_map(): array
	{
		$raw = vms_calendar_parse_assoc_map(vms_calendar_settings()['calendar_open_slot_display_by_vendor_type'] ?? array());
		$out = array();
		foreach ($raw as $slug => $value) {
			$key = sanitize_key((string) $slug);
			if ($key === '') {
				continue;
			}
			$out[$key] = vms_calendar_boolish($value, false);
		}
		return $out;
	}
}

if (!function_exists('vms_calendar_vendor_show_tickets_sold')) {
	function vms_calendar_vendor_show_tickets_sold(): bool
	{
		$settings = vms_calendar_settings();
		return vms_calendar_boolish($settings['calendar_show_tickets_sold_to_vendors'] ?? 0, false);
	}
}

if (!function_exists('vms_calendar_public_show_vendors')) {
	function vms_calendar_public_show_vendors(): bool
	{
		$settings = vms_calendar_settings();
		return vms_calendar_boolish($settings['calendar_public_show_vendors'] ?? 1, true);
	}
}

if (!function_exists('vms_calendar_show_open_slots_for_context')) {
	function vms_calendar_show_open_slots_for_context(string $context): bool
	{
		$context = sanitize_key($context);
		$settings = vms_calendar_settings();

		if ($context === 'public') {
			return vms_calendar_boolish($settings['calendar_show_open_slots_public'] ?? 0, false);
		}
		if ($context === 'vendor') {
			return vms_calendar_boolish($settings['calendar_show_open_slots_vendor'] ?? 1, true);
		}
		return true; // admin
	}
}

if (!function_exists('vms_calendar_vendor_type_open_slot_enabled')) {
	function vms_calendar_vendor_type_open_slot_enabled(string $vendor_type_slug): bool
	{
		$slug = sanitize_key($vendor_type_slug);
		$map = vms_calendar_open_slot_display_map();
		if (array_key_exists($slug, $map)) {
			return (bool) $map[$slug];
		}
		return in_array($slug, array('food_truck', 'dessert_truck', 'drink_truck', 'photographer', 'market_vendor'), true);
	}
}

if (!function_exists('vms_calendar_vendor_context_default_statuses')) {
	/**
	 * Vendor default is published-only unless setting enables tentative visibility.
	 *
	 * @return string[]
	 */
	function vms_calendar_vendor_context_default_statuses(): array
	{
		$settings = vms_calendar_settings();
		$show_tentative = vms_calendar_boolish($settings['calendar_vendor_show_tentative'] ?? 0, false);
		if ($show_tentative) {
			return array('published', 'ready', 'draft', 'tentative', 'confirmed');
		}
		return array('published');
	}
}

if (!function_exists('vms_calendar_default_statuses_for_context')) {
	/**
	 * @return string[]
	 */
	function vms_calendar_default_statuses_for_context(string $context): array
	{
		$context = sanitize_key($context);
		if ($context === 'admin') {
			return array_keys((array) (function_exists('bvmgr_event_plan_statuses')
				? bvmgr_event_plan_statuses()
				: array('draft' => 'Draft', 'ready' => 'Ready', 'published' => 'Published', 'tentative' => 'Tentative', 'confirmed' => 'Confirmed', 'cancelled' => 'Cancelled', 'archived' => 'Archived')));
		}
		if ($context === 'vendor') {
			return vms_calendar_vendor_context_default_statuses();
		}
		return array('published');
	}
}

if (!function_exists('vms_calendar_get_venue_slot_limits')) {
	/**
	 * Venue-level defaults (optional future setting).
	 *
	 * @return array<string,int>
	 */
	function vms_calendar_get_venue_slot_limits(int $venue_id): array
	{
		$venue_id = absint($venue_id);
		if ($venue_id <= 0) {
			return array();
		}

		$keys = array(
			'_vms_calendar_default_slot_limits',
			'vms_calendar_default_slot_limits',
		);

		foreach ($keys as $k) {
			$raw = get_post_meta($venue_id, $k, true);
			$map = vms_calendar_sanitize_int_map($raw);
			if (!empty($map)) {
				return $map;
			}
		}

		return array();
	}
}

if (!function_exists('bvmgr_calendar_get_event_slot_limits')) {
	/**
	 * Resolve slot limits with precedence:
	 * 1) Event plan override
	 * 2) Venue defaults
	 * 3) Global defaults
	 *
	 * @return array<string,int>
	 */
	function bvmgr_calendar_get_event_slot_limits(int $event_plan_id, int $venue_id): array
	{
		$event_plan_id = absint($event_plan_id);
		$venue_id = absint($venue_id);
		$slot_limits = array();

		$k_slot_limits = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'slot_limits') : '';
		$candidates = array();
		if ($k_slot_limits !== '') {
			$candidates[] = $k_slot_limits;
		}
		$candidates[] = 'vms_slot_limits';
		$candidates[] = '_vms_slot_limits';

		foreach ($candidates as $mk) {
			$raw = get_post_meta($event_plan_id, (string) $mk, true);
			$map = vms_calendar_sanitize_int_map($raw);
			if (!empty($map)) {
				$slot_limits = $map;
				break;
			}
		}

		if (empty($slot_limits)) {
			$venue = vms_calendar_get_venue_slot_limits($venue_id);
			if (!empty($venue)) {
				$slot_limits = $venue;
			} else {
				$slot_limits = vms_calendar_global_slot_limits();
			}
		}

		if (function_exists('bvmgr_event_plan_get_secondary_vendor_assignments')) {
			foreach ((array) bvmgr_event_plan_get_secondary_vendor_assignments($event_plan_id) as $type_slug => $assignment) {
				$type_slug = sanitize_key((string) $type_slug);
				if ($type_slug === '') {
					continue;
				}

				$slot_limit = $assignment['slot_limit'] ?? null;
				if ($slot_limit === '' || $slot_limit === null) {
					$default_slot_limit = function_exists('bvmgr_event_plan_secondary_vendor_default_slot_limit')
						? bvmgr_event_plan_secondary_vendor_default_slot_limit($type_slug, sanitize_key((string) ($assignment['mode'] ?? 'standard')))
						: null;
					if ($default_slot_limit === '' || $default_slot_limit === null) {
						continue;
					}

					$slot_limits[$type_slug] = max(0, (int) $default_slot_limit);
					continue;
				}

				$slot_limits[$type_slug] = max(0, (int) $slot_limit);
			}
		}

		return $slot_limits;
	}
}

if (!function_exists('bvmgr_calendar_vendor_primary_type')) {
	/**
	 * @return array{slug:string,name:string}
	 */
	function bvmgr_calendar_vendor_primary_type(int $vendor_id): array
	{
		static $cache = array();
		$vendor_id = absint($vendor_id);
		if ($vendor_id <= 0) {
			return array('slug' => 'untyped', 'name' => __('Other', 'backstage-venue-manager'));
		}

		if (isset($cache[$vendor_id])) {
			return $cache[$vendor_id];
		}

		$terms = wp_get_post_terms($vendor_id, 'vms_vendor_type');
		if (is_wp_error($terms) || empty($terms)) {
			$cache[$vendor_id] = array('slug' => 'untyped', 'name' => __('Other', 'backstage-venue-manager'));
			return $cache[$vendor_id];
		}

		$term = $terms[0];
		$slug = sanitize_key((string) ($term->slug ?? ''));
		$name = sanitize_text_field((string) ($term->name ?? ''));
		if ($slug === '') {
			$slug = 'untyped';
		}
		if ($name === '') {
			$name = __('Other', 'backstage-venue-manager');
		}

		$cache[$vendor_id] = array('slug' => $slug, 'name' => $name);
		return $cache[$vendor_id];
	}
}

if (!function_exists('vms_calendar_vendor_type_label')) {
	function vms_calendar_vendor_type_label(string $type_slug): string
	{
		$type_slug = sanitize_key($type_slug);
		if ($type_slug === '') {
			return __('Other', 'backstage-venue-manager');
		}

		if (function_exists('vms_vendor_type_label')) {
			$label = trim((string) vms_vendor_type_label($type_slug));
			if ($label !== '') {
				return $label;
			}
		}

		return ucwords(str_replace(array('_', '-'), ' ', $type_slug));
	}
}

if (!function_exists('bvmgr_calendar_plan_vendor_ids')) {
	/**
	 * @return array{band_id:int,secondary_ids:int[],secondary_assignments:array<string,array<string,mixed>>,lineup_ids:int[]}
	 */
	function bvmgr_calendar_plan_vendor_ids(int $event_plan_id): array
	{
		$event_plan_id = absint($event_plan_id);
		$k_band = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'band_vendor_id') : '_vms_band_vendor_id';
		$k_secondary_ids = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'secondary_vendor_ids') : '_vms_secondary_vendor_ids';
		$k_secondary_idx = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'secondary_vendor_id') : '_vms_secondary_vendor_id';

		$band_id = absint(get_post_meta($event_plan_id, $k_band ?: '_vms_band_vendor_id', true));
		$secondary_assignments = function_exists('bvmgr_event_plan_get_secondary_vendor_assignments')
			? (array) bvmgr_event_plan_get_secondary_vendor_assignments($event_plan_id, array(
				'primary_vendor_id' => $band_id,
			))
			: array();

		if (!empty($secondary_assignments) && function_exists('bvmgr_event_plan_get_secondary_vendor_flat_ids_from_assignments')) {
			$secondary = bvmgr_event_plan_get_secondary_vendor_flat_ids_from_assignments($secondary_assignments, $band_id);
		} else {
			$secondary = get_post_meta($event_plan_id, $k_secondary_ids ?: '_vms_secondary_vendor_ids', true);
			if (!is_array($secondary)) {
				$secondary = get_post_meta($event_plan_id, $k_secondary_idx ?: '_vms_secondary_vendor_id', false);
			}
			$secondary = array_values(array_unique(array_filter(array_map('absint', (array) $secondary), function ($v) use ($band_id) {
				return ($v > 0 && $v !== $band_id);
			})));
		}

		$lineup_ids = function_exists('vms_get_event_plan_lineup_vendor_ids')
			? (array) vms_get_event_plan_lineup_vendor_ids($event_plan_id)
			: array();
		$lineup_ids = array_values(array_unique(array_filter(array_map('absint', (array) $lineup_ids))));

		if ($band_id <= 0 && function_exists('vms_get_event_plan_lineup_primary_entry')) {
			$primary_entry = (array) vms_get_event_plan_lineup_primary_entry($event_plan_id);
			$band_id = absint($primary_entry['vendor_id'] ?? 0);
		}

		return array(
			'band_id' => $band_id,
			'secondary_ids' => $secondary,
			'secondary_assignments' => $secondary_assignments,
			'lineup_ids' => $lineup_ids,
		);
	}
}

if (!function_exists('vms_calendar_vendor_display_url')) {
	function vms_calendar_vendor_display_url(int $vendor_id): ?string
	{
		$vendor_id = absint($vendor_id);
		if ($vendor_id <= 0) {
			return null;
		}
		if (function_exists('bvmgr_vendor_profile_is_enabled') && !bvmgr_vendor_profile_is_enabled($vendor_id)) {
			return null;
		}
		if (function_exists('bvmgr_vendor_profile_url')) {
			$url = (string) bvmgr_vendor_profile_url($vendor_id);
			return $url !== '' ? $url : null;
		}
		return null;
	}
}

if (!function_exists('bvmgr_calendar_assignment_status_for_plan')) {
	function bvmgr_calendar_assignment_status_for_plan(string $plan_status): ?string
	{
		$s = function_exists('bvmgr_event_plan_status_normalize')
			? bvmgr_event_plan_status_normalize($plan_status)
			: sanitize_key($plan_status);

		if ($s === 'published') {
			return 'booked';
		}
		if (in_array($s, array('draft', 'ready', 'tentative', 'confirmed'), true)) {
			return 'tentative';
		}
		return null;
	}
}

if (!function_exists('vms_calendar_open_slot_link')) {
	function vms_calendar_open_slot_link(string $vendor_type_slug): ?string
	{
		$settings = vms_calendar_settings();
		$slug = sanitize_key($vendor_type_slug);

		$target_by_type = vms_calendar_parse_assoc_map($settings['calendar_open_slot_link_target_by_type'] ?? array());
		$custom_by_type = vms_calendar_parse_assoc_map($settings['calendar_open_slot_link_custom_url_by_type'] ?? array());

		$target = isset($target_by_type[$slug]) ? sanitize_key((string) $target_by_type[$slug]) : sanitize_key((string) ($settings['calendar_open_slot_link_target'] ?? 'vendor_dashboard'));

		$custom = '';
		if (isset($custom_by_type[$slug])) {
			$custom = esc_url_raw((string) $custom_by_type[$slug]);
		}
		if ($custom === '') {
			$custom = esc_url_raw((string) ($settings['calendar_open_slot_link_custom_url'] ?? ''));
		}

		$url = '';
		if ($target === 'custom' && $custom !== '') {
			$url = $custom;
		} elseif ($target === 'vendor_registration') {
			$page = get_page_by_path('vendor-application');
			if ($page instanceof WP_Post) {
				$url = (string) get_permalink($page->ID);
			}
		} else {
			$vendor_page_id = absint(get_option('vms_page_vendor_portal', 0));
			if ($vendor_page_id > 0) {
				$url = (string) get_permalink($vendor_page_id);
			}
		}

		$url = apply_filters('vms_calendar_open_slot_link', $url, $slug);
		return $url !== '' ? $url : null;
	}
}

if (!function_exists('vms_calendar_get_ticket_sold_count')) {
	function vms_calendar_get_ticket_sold_count(int $event_plan_id): int
	{
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0) {
			return 0;
		}

		$k_count = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'tickets_sold_count') : '';
		$candidates = array();
		if ($k_count !== '') {
			$candidates[] = $k_count;
		}
		$candidates[] = 'vms_tickets_sold_count';
		$candidates[] = '_vms_tickets_sold_count';

		foreach ($candidates as $mk) {
			$raw = get_post_meta($event_plan_id, (string) $mk, true);
			if (is_numeric($raw)) {
				return max(0, (int) $raw);
			}
		}

		$k_stats = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'ticket_stats') : '_vms_ticket_stats_v1';
		if ($k_stats === '') {
			$k_stats = '_vms_ticket_stats_v1';
		}
		$stats = get_post_meta($event_plan_id, $k_stats, true);
		if (is_array($stats)) {
			if (isset($stats['qty_sold']) && is_numeric($stats['qty_sold'])) {
				return max(0, (int) $stats['qty_sold']);
			}
			if (isset($stats['qty']) && is_numeric($stats['qty'])) {
				return max(0, (int) $stats['qty']);
			}
		}

		return 0;
	}
}

if (!function_exists('vms_calendar_parse_time_hhmm')) {
	function vms_calendar_parse_time_hhmm(string $raw): string
	{
		$raw = trim($raw);
		if (!preg_match('/^(\d{1,2}):(\d{2})$/', $raw, $m)) {
			return '';
		}
		$h = (int) $m[1];
		$i = (int) $m[2];
		if ($h < 0 || $h > 23 || $i < 0 || $i > 59) {
			return '';
		}
		return str_pad((string) $h, 2, '0', STR_PAD_LEFT) . ':' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
	}
}

if (!function_exists('vms_calendar_iso_local')) {
	function vms_calendar_iso_local(string $ymd, string $hhmm = ''): string
	{
		$tz = function_exists('bvmgr_get_timezone') ? bvmgr_get_timezone() : wp_timezone();
		$time = ($hhmm !== '') ? $hhmm : '00:00';
		try {
			$dt = new DateTimeImmutable($ymd . ' ' . $time . ':00', $tz);
			return $dt->format(DateTime::ATOM);
		} catch (Exception $e) {
			try {
				$fallback = new DateTimeImmutable($ymd . ' 00:00:00', $tz);
				return $fallback->format(DateTime::ATOM);
			} catch (Exception $e2) {
				return '';
			}
		}
	}
}

if (!function_exists('vms_calendar_prepare_vendor_groups')) {
	/**
	 * @param string[] $include_vendor_types
	 * @return array<string,array<string,mixed>>
	 */
	function vms_calendar_prepare_vendor_groups(
		int $event_plan_id,
		int $venue_id,
		string $context,
		int $viewer_vendor_id,
		array $include_vendor_types = array()
	): array {
		$context = sanitize_key($context);
		$include_vendor_types = array_values(array_filter(array_map('sanitize_key', $include_vendor_types)));

		$icon_map = vms_calendar_vendor_type_icons();
		$limits = bvmgr_calendar_get_event_slot_limits($event_plan_id, $venue_id);
		$vendor_ids = bvmgr_calendar_plan_vendor_ids($event_plan_id);
		$secondary_assignments = is_array($vendor_ids['secondary_assignments'] ?? null)
			? (array) $vendor_ids['secondary_assignments']
			: array();
		$groups = array();

		$ensure_group = static function (string $type_slug, string $type_name = '', array $assignment = array()) use (&$groups, $icon_map, $limits, $include_vendor_types): bool {
			if ($type_slug === '') {
				$type_slug = 'untyped';
			}

			if (!empty($include_vendor_types) && !in_array($type_slug, $include_vendor_types, true)) {
				return false;
			}

			if (!isset($groups[$type_slug])) {
				$mode = sanitize_key((string) ($assignment['mode'] ?? ''));
				if (!in_array($mode, array('standard', 'market'), true)) {
					$mode = function_exists('bvmgr_event_plan_secondary_vendor_default_mode')
						? bvmgr_event_plan_secondary_vendor_default_mode($type_slug)
						: 'standard';
				}

				$groups[$type_slug] = array(
					'type_slug' => $type_slug,
					'type_name' => $type_name !== '' ? $type_name : vms_calendar_vendor_type_label($type_slug),
					'mode' => $mode,
					'icon' => (string) ($icon_map[$type_slug] ?? ''),
					'vendors' => array(),
					'max_slots' => isset($limits[$type_slug]) ? max(0, (int) $limits[$type_slug]) : 0,
					'filled_slots' => 0,
					'has_open_slots' => false,
					'open_slot_link' => null,
				);
			}

			return true;
		};

		$append_vendor = static function (string $type_slug, int $vendor_id) use (&$groups, $context, $event_plan_id): void {
			$type_slug = sanitize_key($type_slug);
			$vendor_id = absint($vendor_id);
			if ($type_slug === '' || $vendor_id <= 0 || !isset($groups[$type_slug])) {
				return;
			}

			$display_name = (string) get_the_title($vendor_id);
			if ($display_name === '') {
				$display_name = __('(Vendor)', 'backstage-venue-manager');
			}

			$vendor_url = vms_calendar_vendor_display_url($vendor_id);
			$vendor_url = apply_filters('vms_calendar_link_target', $vendor_url, array(
				'link_scope' => 'vendor',
				'context' => $context,
				'event_plan_id' => $event_plan_id,
				'vendor_id' => $vendor_id,
				'vendor_type_slug' => $type_slug,
			));
			if (!is_string($vendor_url) || trim($vendor_url) === '') {
				$vendor_url = null;
			}
			$groups[$type_slug]['vendors'][] = array(
				'vendor_id' => (int) $vendor_id,
				'display_name' => $display_name,
				'public_url' => $vendor_url,
			);
			$groups[$type_slug]['filled_slots'] = (int) $groups[$type_slug]['filled_slots'] + 1;
		};

		$primary_vendor_id = absint($vendor_ids['band_id'] ?? 0);
		if ($primary_vendor_id > 0) {
			$primary_type = bvmgr_calendar_vendor_primary_type($primary_vendor_id);
			$primary_type_slug = sanitize_key((string) ($primary_type['slug'] ?? ''));
			$primary_type_name = trim((string) ($primary_type['name'] ?? ''));
			if ($ensure_group($primary_type_slug, $primary_type_name)) {
				$append_vendor($primary_type_slug !== '' ? $primary_type_slug : 'untyped', $primary_vendor_id);
			}
		}

		if (!empty($secondary_assignments)) {
			foreach ($secondary_assignments as $type_slug => $assignment) {
				$type_slug = sanitize_key((string) $type_slug);
				$assignment = is_array($assignment) ? $assignment : array();
				if (!$ensure_group($type_slug, vms_calendar_vendor_type_label($type_slug), $assignment)) {
					continue;
				}

				foreach ((array) ($assignment['vendor_ids'] ?? array()) as $vendor_id) {
					$append_vendor($type_slug, (int) $vendor_id);
				}
			}
		} else {
			foreach ((array) ($vendor_ids['secondary_ids'] ?? array()) as $vendor_id) {
				$vendor_id = absint($vendor_id);
				if ($vendor_id <= 0) {
					continue;
				}

				$type = bvmgr_calendar_vendor_primary_type($vendor_id);
				$type_slug = sanitize_key((string) ($type['slug'] ?? ''));
				$type_name = trim((string) ($type['name'] ?? ''));
				if (!$ensure_group($type_slug, $type_name)) {
					continue;
				}

				$append_vendor($type_slug !== '' ? $type_slug : 'untyped', $vendor_id);
			}
		}

		$viewer_type = ($viewer_vendor_id > 0) ? (string) (bvmgr_calendar_vendor_primary_type($viewer_vendor_id)['slug'] ?? '') : '';
		$viewer_type_slugs = array();
		if ($viewer_vendor_id > 0 && function_exists('bvmgr_event_plan_secondary_vendor_terms_for_vendor')) {
			$viewer_type_slugs = array_values(array_unique(array_filter(array_map('sanitize_key', (array) bvmgr_event_plan_secondary_vendor_terms_for_vendor($viewer_vendor_id)))));
		}
		if (empty($viewer_type_slugs) && $viewer_type !== '') {
			$viewer_type_slugs[] = $viewer_type;
		}
		$vendor_visibility_map = vms_calendar_vendor_visibility_map();
		$public_visibility_map = vms_calendar_public_vendor_visibility_map();
		$show_open_slots = vms_calendar_show_open_slots_for_context($context);

		foreach ($groups as $slug => &$group) {
			$filled = max(0, (int) ($group['filled_slots'] ?? 0));
			$max = max(0, (int) ($group['max_slots'] ?? 0));
			$open = ($max > 0 && $filled < $max);
			$type_open_enabled = vms_calendar_vendor_type_open_slot_enabled((string) $slug);
			if (!$show_open_slots || !$type_open_enabled) {
				$open = false;
			}
			if ($context === 'vendor') {
				if (empty($viewer_type_slugs) || !in_array((string) $slug, $viewer_type_slugs, true)) {
					$open = false;
				}
			}
			$group['has_open_slots'] = $open;
			$group['open_slot_link'] = $open ? vms_calendar_open_slot_link((string) $slug) : null;
			if ($open && $context === 'vendor' && $group['open_slot_link']) {
				$group['open_slot_link'] = add_query_arg(array(
					'tab' => 'opportunities',
				), (string) $group['open_slot_link']);
			}

			// Context-based visibility gates.
			$allowed = true;
			if ($context === 'vendor') {
				$allowed = false;
				$group_vendor_ids = array_map(static function ($row) {
					return absint($row['vendor_id'] ?? 0);
				}, (array) ($group['vendors'] ?? array()));
				if ($viewer_vendor_id > 0 && in_array($viewer_vendor_id, $group_vendor_ids, true)) {
					$allowed = true;
				} elseif ($slug === 'talent') {
					$allowed = true; // Always keep entertainment presence visible in vendor context.
				} elseif (array_key_exists($slug, $vendor_visibility_map)) {
					$allowed = (bool) $vendor_visibility_map[$slug];
				} elseif (!empty($viewer_type_slugs) && in_array((string) $slug, $viewer_type_slugs, true)) {
					$allowed = true;
				}
			} elseif ($context === 'public') {
				$allowed = vms_calendar_public_show_vendors();
				if ($allowed) {
					if (array_key_exists($slug, $public_visibility_map)) {
						$allowed = (bool) $public_visibility_map[$slug];
					} else {
						$allowed = ($slug === 'talent');
					}
				}
			}

			if (!$allowed) {
				$group['vendors'] = array();
			}
		}
		unset($group);

		ksort($groups);
		return (array) apply_filters('vms_calendar_vendor_groups', $groups, $event_plan_id, $context);
	}
}

if (!function_exists('bvmgr_calendar_feed_cache_bust')) {
	function bvmgr_calendar_feed_cache_bust(): void
	{
		update_option(BVMGR_CALENDAR_FEED_CACHE_BUST_OPTION, (string) time(), false);
	}
}

if (!function_exists('vms_calendar_feed_hooks')) {
	function vms_calendar_feed_hooks(): void
	{
		add_action('save_post_vms_event_plan', 'bvmgr_calendar_feed_cache_bust', 20, 0);
		add_action('save_post_vms_vendor', 'bvmgr_calendar_feed_cache_bust', 20, 0);
		add_action('save_post_vms_venue', 'bvmgr_calendar_feed_cache_bust', 20, 0);
		add_action('updated_option', function ($option) {
			if ($option === 'vms_settings') {
				bvmgr_calendar_feed_cache_bust();
			}
		}, 10, 1);
	}
}
vms_calendar_feed_hooks();

if (!function_exists('bvmgr_get_calendar_events')) {
	/**
	 * Canonical Event Feed (Core).
	 *
	 * @param array<string,mixed> $args
	 * @return array<int,array<string,mixed>>
	 */
	function bvmgr_get_calendar_events(array $args): array
	{
		$context = isset($args['context']) ? sanitize_key((string) $args['context']) : 'public';
		if (!in_array($context, array('admin', 'vendor', 'public'), true)) {
			$context = 'public';
		}

		$start_date = isset($args['start_date']) ? (string) $args['start_date'] : '';
		$end_date = isset($args['end_date']) ? (string) $args['end_date'] : '';
		if (!vms_calendar_is_valid_ymd($start_date) || !vms_calendar_is_valid_ymd($end_date)) {
			return array();
		}
		if ($end_date < $start_date) {
			$tmp = $start_date;
			$start_date = $end_date;
			$end_date = $tmp;
		}

		$viewer_vendor_id = isset($args['viewer_vendor_id']) ? absint($args['viewer_vendor_id']) : 0;
		$include_past = isset($args['include_past'])
			? (bool) $args['include_past']
			: ($context === 'admin');
		$include_open_close_shading = isset($args['include_open_close_shading'])
			? (bool) $args['include_open_close_shading']
			: ($context === 'public');

		$include_vendor_types = array();
		if (isset($args['include_vendor_types'])) {
			$include_vendor_types = array_values(array_filter(array_map('sanitize_key', (array) $args['include_vendor_types'])));
		}

		$include_statuses = array();
		if (isset($args['include_statuses']) && is_array($args['include_statuses']) && !empty($args['include_statuses'])) {
			foreach ((array) $args['include_statuses'] as $st) {
				$key = function_exists('bvmgr_event_plan_status_normalize')
					? bvmgr_event_plan_status_normalize((string) $st)
					: sanitize_key((string) $st);
				if ($key !== '') {
					$include_statuses[] = $key;
				}
			}
		}
		if (empty($include_statuses)) {
			$include_statuses = vms_calendar_default_statuses_for_context($context);
		}
		$include_statuses = array_values(array_unique(array_filter(array_map('sanitize_key', $include_statuses))));

		$venue_ids = 'all';
		if (isset($args['venue_ids']) && $args['venue_ids'] !== 'all') {
			$venue_ids = array_values(array_unique(array_filter(array_map('absint', (array) $args['venue_ids']))));
			if (empty($venue_ids)) {
				$venue_ids = 'all';
			}
		}

		$cache_version = (string) get_option(BVMGR_CALENDAR_FEED_CACHE_BUST_OPTION, '1');
		$cache_ttl = (int) apply_filters('vms_calendar_feed_cache_ttl', 10 * MINUTE_IN_SECONDS, $context);
		$cache_key = 'vms_cal_feed_' . md5(wp_json_encode(array(
			'v' => $cache_version,
			'start' => $start_date,
			'end' => $end_date,
			'venues' => $venue_ids,
			'ctx' => $context,
			'viewer' => $viewer_vendor_id,
			'st' => $include_statuses,
			'types' => $include_vendor_types,
			'past' => $include_past ? 1 : 0,
			'shading' => $include_open_close_shading ? 1 : 0,
		)));

		$cached = get_transient($cache_key);
		if (is_array($cached)) {
			return $cached;
		}

		$k_date = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'date') : '_vms_event_date';
		$k_venue = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'venue_id') : '_vms_venue_id';
		$k_tec_event_id = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'tec_event_id') : '_vms_tec_event_id';
		$k_tec_event_url = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'tec_event_url') : '_vms_tec_event_url';

		if ($k_date === '') {
			$k_date = '_vms_event_date';
		}
		if ($k_venue === '') {
			$k_venue = '_vms_venue_id';
		}
		if ($k_tec_event_id === '') {
			$k_tec_event_id = '_vms_tec_event_id';
		}
		if ($k_tec_event_url === '') {
			$k_tec_event_url = '_vms_tec_event_url';
		}

		$meta_query = array(
			'relation' => 'AND',
			array(
				'key' => $k_date,
				'value' => $start_date,
				'compare' => '>=',
				'type' => 'DATE',
			),
			array(
				'key' => $k_date,
				'value' => $end_date,
				'compare' => '<=',
				'type' => 'DATE',
			),
		);

		if (is_array($venue_ids) && !empty($venue_ids)) {
			$meta_query[] = array(
				'key' => $k_venue,
				'value' => $venue_ids,
				'compare' => 'IN',
				'type' => 'NUMERIC',
			);
		}

		$query_args = array(
			'post_type' => 'vms_event_plan',
			'post_status' => array('publish', 'private', 'draft', 'pending', 'future'),
			'posts_per_page' => -1,
			'fields' => 'ids',
			'meta_key' => $k_date, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Calendar feed ordering intentionally uses canonical event-date metadata for the complete requested date/venue result set before response caching.
			'orderby' => 'meta_value',
			'order' => 'ASC',
			'meta_query' => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Calendar feed intentionally retrieves the complete Event Plan ID set within the requested date and optional Venue bounds before response caching.
			'no_found_rows' => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
		);

		$query_args = (array) apply_filters('vms_calendar_events_query_args', $query_args, $args);
		$plan_ids = get_posts($query_args);
		if (!is_array($plan_ids) || empty($plan_ids)) {
			set_transient($cache_key, array(), $cache_ttl);
			return array();
		}

		$plan_ids = array_values(array_unique(array_filter(array_map('absint', $plan_ids))));
		if (empty($plan_ids)) {
			set_transient($cache_key, array(), $cache_ttl);
			return array();
		}

		$events = array();
		$today = wp_date('Y-m-d', time(), function_exists('bvmgr_get_timezone') ? bvmgr_get_timezone() : wp_timezone());

		$venue_name_cache = array();
		foreach ($plan_ids as $plan_id) {
			$date_key = (string) get_post_meta($plan_id, $k_date, true);
			if (!vms_calendar_is_valid_ymd($date_key)) {
				continue;
			}

			if (!$include_past && $date_key < $today && $context !== 'admin') {
				continue;
			}

			$plan_status = function_exists('bvmgr_event_plan_get_status')
				? (string) bvmgr_event_plan_get_status($plan_id, $context)
				: sanitize_key((string) get_post_meta($plan_id, (function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'status') ?: '_vms_event_plan_status') : '_vms_event_plan_status'), true));

			$plan_status = function_exists('bvmgr_event_plan_status_normalize')
				? bvmgr_event_plan_status_normalize($plan_status)
				: sanitize_key($plan_status);

			if (!in_array($plan_status, $include_statuses, true)) {
				continue;
			}

			$venue_id = absint(get_post_meta($plan_id, $k_venue, true));
			if ($venue_id > 0 && !isset($venue_name_cache[$venue_id])) {
				$venue_name_cache[$venue_id] = (string) get_the_title($venue_id);
			}
			$venue_name = (string) ($venue_name_cache[$venue_id] ?? '');

			$start_hhmm = vms_calendar_parse_time_hhmm((string) get_post_meta($plan_id, '_vms_start_time', true));
			$end_hhmm = vms_calendar_parse_time_hhmm((string) get_post_meta($plan_id, '_vms_end_time', true));
			$start_local = vms_calendar_iso_local($date_key, $start_hhmm);
			$end_local = $end_hhmm !== '' ? vms_calendar_iso_local($date_key, $end_hhmm) : null;

			$tec_event_id = absint(get_post_meta($plan_id, $k_tec_event_id, true));
			$public_url = null;
			if ($context === 'public' && function_exists('bvmgr_event_plan_resolve_public_calendar_url')) {
				$resolved_public_url = bvmgr_event_plan_resolve_public_calendar_url($plan_id);
				if ($plan_status === 'published' && $resolved_public_url === '') {
					continue;
				}
				$public_url = ($resolved_public_url !== '') ? $resolved_public_url : null;
			} else {
				$tec_event_url = (string) get_post_meta($plan_id, $k_tec_event_url, true);
				if ($tec_event_url === '' && $tec_event_id > 0) {
					$tec_event_url = (string) get_permalink($tec_event_id);
				}
				$public_url = $tec_event_url !== '' ? $tec_event_url : null;
			}
			$public_url = apply_filters('vms_calendar_link_target', $public_url, array(
				'link_scope' => 'event',
				'context' => $context,
				'event_plan_id' => $plan_id,
				'vendor_id' => 0,
				'vendor_type_slug' => '',
			));
			if (!is_string($public_url) || trim($public_url) === '') {
				$public_url = null;
			}

			$title = (string) get_the_title($plan_id);
			if ($title === '') {
				$title = __('Event', 'backstage-venue-manager');
			}

			$excerpt = '';
			$post = get_post($plan_id);
			if ($post instanceof WP_Post) {
				$excerpt = trim((string) $post->post_excerpt);
				if ($excerpt === '') {
					$excerpt = wp_strip_all_tags((string) $post->post_content);
					$excerpt = wp_trim_words($excerpt, 24, '...');
				}
			}
			$excerpt = ($excerpt !== '') ? $excerpt : null;

			$img_id = get_post_thumbnail_id($plan_id);
			if (!$img_id) {
				$v = bvmgr_calendar_plan_vendor_ids($plan_id);
				if (!empty($v['band_id'])) {
					$img_id = get_post_thumbnail_id((int) $v['band_id']);
				}
			}
			$image_url = $img_id ? wp_get_attachment_image_url($img_id, 'large') : null;

			$vendor_groups = vms_calendar_prepare_vendor_groups(
				$plan_id,
				$venue_id,
				$context,
				$viewer_vendor_id,
				$include_vendor_types
			);

			$viewer_assigned = false;
			if ($context === 'vendor' && $viewer_vendor_id > 0) {
				foreach ($vendor_groups as $g) {
					foreach ((array) ($g['vendors'] ?? array()) as $row) {
						if ((int) ($row['vendor_id'] ?? 0) === $viewer_vendor_id) {
							$viewer_assigned = true;
							break 2;
						}
					}
				}
			}
			$viewer_status = null;
			if ($context === 'vendor') {
				$viewer_status = array(
					'assigned' => $viewer_assigned,
					'assignment_status' => $viewer_assigned ? bvmgr_calendar_assignment_status_for_plan($plan_status) : null,
				);
			}

			$tickets_sold = null;
			if ($context === 'admin' || ($context === 'vendor' && vms_calendar_vendor_show_tickets_sold())) {
				$tickets_sold = vms_calendar_get_ticket_sold_count($plan_id);
			}

			$is_open = null;
			if ($include_open_close_shading && $venue_id > 0 && function_exists('bvmgr_venue_is_open_on_date')) {
				$is_open = (bool) bvmgr_venue_is_open_on_date($venue_id, $date_key);
			}

			$event = array(
				'event_plan_id' => (int) $plan_id,
				'title' => $title,
				'start_local' => $start_local,
				'end_local' => $end_local,
				'date_key' => $date_key,
				'venue_id' => (int) $venue_id,
				'venue_name' => $venue_name,
				'public_url' => $public_url,
				'ticket_url' => null,
				'image_url' => $image_url,
				'excerpt' => $excerpt,
				'vendor_groups' => $vendor_groups,
				'viewer_status' => $viewer_status,
				'tickets_sold' => $tickets_sold,
				'venue_open' => $is_open,
				'plan_status' => $plan_status,
			);

			$event = (array) apply_filters('vms_calendar_event_object', $event, $plan_id, $args);
			$events[] = $event;
		}

		usort($events, static function (array $a, array $b): int {
			$da = (string) ($a['date_key'] ?? '');
			$db = (string) ($b['date_key'] ?? '');
			if ($da !== $db) {
				return strcmp($da, $db);
			}
			$sa = (string) ($a['start_local'] ?? '');
			$sb = (string) ($b['start_local'] ?? '');
			return strcmp($sa, $sb);
		});

		set_transient($cache_key, $events, $cache_ttl);
		return $events;
	}
}
