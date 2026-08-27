<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_add_dispatch_settings')) {
	function vms_add_dispatch_settings(): array
	{
		return (array) get_option('vms_settings', array());
	}
}

if (!function_exists('vms_add_dispatch_enabled_by_settings')) {
	function vms_add_dispatch_enabled_by_settings(): bool
	{
		$settings = vms_add_dispatch_settings();
		if (!array_key_exists('availability_date_dispatch_enabled', $settings)) {
			return true;
		}

		return !empty($settings['availability_date_dispatch_enabled']);
	}
}

if (!function_exists('vms_add_dispatch_page_slug')) {
	function vms_add_dispatch_page_slug(): string
	{
		return 'vms-add-dispatch';
	}
}

if (!function_exists('vms_add_dispatch_admin_url')) {
	function vms_add_dispatch_admin_url(array $args = array()): string
	{
		$args = array_merge(
			array(
				'page' => vms_add_dispatch_page_slug(),
			),
			$args
		);

		return add_query_arg($args, admin_url('admin.php'));
	}
}

if (!function_exists('vms_add_dispatch_now_mysql')) {
	function vms_add_dispatch_now_mysql(): string
	{
		return current_time('mysql');
	}
}

if (!function_exists('vms_add_dispatch_event_meta_key')) {
	function vms_add_dispatch_event_meta_key(string $key, string $fallback): string
	{
		if (function_exists('bvmgr_meta_key')) {
			$resolved = (string) bvmgr_meta_key('event_plan', $key);
			if ($resolved !== '') {
				return $resolved;
			}
		}

		return $fallback;
	}
}

if (!function_exists('vms_add_dispatch_vendor_email_key')) {
	function vms_add_dispatch_vendor_email_key(): string
	{
		if (function_exists('bvmgr_meta_key')) {
			$resolved = (string) bvmgr_meta_key('vendor', 'email');
			if ($resolved !== '') {
				return $resolved;
			}
		}

	return '_vms_vendor_email';
	}
}

if (!function_exists('vms_add_dispatch_primary_vendor_key')) {
	function vms_add_dispatch_primary_vendor_key(): string
	{
		return vms_add_dispatch_event_meta_key('band_vendor_id', '_vms_band_vendor_id');
	}
}


if (!function_exists('vms_add_dispatch_event_status_key')) {
	function vms_add_dispatch_event_status_key(): string
	{
		if (function_exists('bvmgr_meta_key')) {
			$resolved = (string) bvmgr_meta_key('event_plan', 'status');
			if ($resolved !== '') {
				return $resolved;
			}
		}

		return '_vms_event_plan_status';
	}
}

if (!function_exists('vms_add_dispatch_normalize_event_status')) {
	function vms_add_dispatch_normalize_event_status(string $status): string
	{
		$status = sanitize_key($status);
		if ($status === 'canceled') {
			$status = 'cancelled';
		}
		return $status;
	}
}

if (!function_exists('vms_add_dispatch_is_past_event_date')) {
	function vms_add_dispatch_is_past_event_date(string $date): bool
	{
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
			return false;
		}

		$today = function_exists('wp_date') ? wp_date('Y-m-d', time(), function_exists('wp_timezone') ? wp_timezone() : null) : current_time('Y-m-d');
		return $date < (string) $today;
	}
}

if (!function_exists('vms_add_dispatch_format_date')) {
	function vms_add_dispatch_format_date(string $date): string
	{
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
			return $date;
		}

		try {
			$dt = new DateTimeImmutable($date . ' 12:00:00', function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC'));
			return wp_date('F j, Y', $dt->getTimestamp(), function_exists('wp_timezone') ? wp_timezone() : null);
		} catch (Exception $e) {
			return $date;
		}
	}
}

if (!function_exists('vms_add_dispatch_type_options')) {
	function vms_add_dispatch_type_options(): array
	{
		$terms = get_terms(array(
			'taxonomy' => 'vms_vendor_type',
			'hide_empty' => false,
		));
		if (!is_array($terms) || is_wp_error($terms)) {
			return array();
		}

		$options = array();
		foreach ($terms as $term) {
			if (!$term instanceof WP_Term) {
				continue;
			}

			$slug = function_exists('vms_vendor_type_canonical_slug_for_term')
				? vms_vendor_type_canonical_slug_for_term($term)
				: sanitize_title((string) $term->slug);
			$label = function_exists('vms_vendor_type_label')
				? (string) vms_vendor_type_label($slug !== '' ? $slug : (string) $term->slug)
				: (string) $term->name;
			if ($slug === '' || trim($label) === '') {
				continue;
			}

			$options[$slug] = $label;
		}

		return $options;
	}
}

if (!function_exists('vms_add_dispatch_type_label')) {
	function vms_add_dispatch_type_label(string $slug): string
	{
		$slug = function_exists('vms_vendor_type_normalize_slug')
			? (string) vms_vendor_type_normalize_slug($slug)
			: (function_exists('vms_event_plan_normalize_secondary_vendor_type_slug')
				? (string) vms_event_plan_normalize_secondary_vendor_type_slug($slug)
				: sanitize_title($slug));
		$options = vms_add_dispatch_type_options();
		if (isset($options[$slug])) {
			return (string) $options[$slug];
		}

		$fallbacks = function_exists('vms_event_plan_secondary_vendor_type_options')
			? (array) vms_event_plan_secondary_vendor_type_options()
			: array(
				'band' => __('Music Vendor', 'backstage-venue-manager'),
				'food_truck' => __('Food Vendor', 'backstage-venue-manager'),
				'dessert_truck' => __('Dessert Vendor', 'backstage-venue-manager'),
				'drink_truck' => __('Drink Vendor', 'backstage-venue-manager'),
				'photographer' => __('Photographer', 'backstage-venue-manager'),
				'market_vendor' => __('Market Vendor', 'backstage-venue-manager'),
			);
		if (isset($fallbacks[$slug])) {
			return (string) $fallbacks[$slug];
		}

		return $slug !== '' ? ucwords(str_replace(array('_', '-'), ' ', $slug)) : '';
	}
}

if (!function_exists('vms_add_dispatch_assignment_int_value')) {
	function vms_add_dispatch_assignment_int_value(array $assignment, array $keys): ?int
	{
		foreach ($keys as $key) {
			$key = (string) $key;
			if (!array_key_exists($key, $assignment)) {
				continue;
			}
			$value = $assignment[$key];
			if ($value === '' || $value === null) {
				return null;
			}
			return max(0, (int) $value);
		}

		return null;
	}
}

if (!function_exists('vms_add_dispatch_assignment_bool_value')) {
	function vms_add_dispatch_assignment_bool_value(array $assignment, string $key, bool $default = true): bool
	{
		if (!array_key_exists($key, $assignment)) {
			return $default;
		}
		if (function_exists('vms_event_plan_parse_secondary_vendor_over_capacity_override')) {
			return vms_event_plan_parse_secondary_vendor_over_capacity_override($assignment[$key]);
		}

		$value = $assignment[$key];
		if (is_bool($value)) {
			return $value;
		}
		return in_array(strtolower(trim((string) $value)), array('1', 'true', 'yes', 'on'), true);
	}
}

if (!function_exists('vms_add_dispatch_vendor_type_slugs')) {
	function vms_add_dispatch_vendor_type_slugs(int $vendor_id): array
	{
		$vendor_id = absint($vendor_id);
		if ($vendor_id <= 0) {
			return array();
		}

		if (function_exists('vms_event_plan_secondary_vendor_terms_for_vendor')) {
			$slugs = array_values(array_unique(array_filter(array_map('sanitize_key', (array) vms_event_plan_secondary_vendor_terms_for_vendor($vendor_id)))));
			if (!empty($slugs)) {
				return $slugs;
			}
		}

		$primary_type = function_exists('bvmgr_calendar_vendor_primary_type')
			? sanitize_title((string) (bvmgr_calendar_vendor_primary_type($vendor_id)['slug'] ?? ''))
			: '';

		return $primary_type !== '' ? array($primary_type) : array();
	}
}

if (!function_exists('vms_add_dispatch_secondary_group_rows')) {
	function vms_add_dispatch_secondary_group_rows(array $assignments): array
	{
		$rows = array();
		foreach ($assignments as $type_slug => $assignment) {
			$type_slug = function_exists('vms_vendor_type_normalize_slug')
				? vms_vendor_type_normalize_slug((string) $type_slug)
				: sanitize_title((string) $type_slug);
			if ($type_slug === '') {
				continue;
			}

			$assignment = is_array($assignment) ? $assignment : array();
			$mode = sanitize_key((string) ($assignment['mode'] ?? ''));
			if (!in_array($mode, array('standard', 'market'), true)) {
				$mode = function_exists('vms_event_plan_secondary_vendor_default_mode')
					? vms_event_plan_secondary_vendor_default_mode($type_slug)
					: 'standard';
			}

			$vendor_ids = array_values(array_unique(array_filter(array_map('absint', (array) ($assignment['vendor_ids'] ?? array())), static function (int $vendor_id): bool {
				return $vendor_id > 0;
			})));
			$slot_limit = function_exists('vms_event_plan_secondary_vendor_effective_slot_limit')
				? vms_event_plan_secondary_vendor_effective_slot_limit($type_slug, $mode, $assignment['slot_limit'] ?? null)
				: ($assignment['slot_limit'] ?? null);
			if ($slot_limit !== null && $slot_limit !== '') {
				$slot_limit = max(0, (int) $slot_limit);
			} else {
				$slot_limit = null;
			}

			$filled_slots = count($vendor_ids);
			$available_slots = null;
			$is_open = false;
			$over_capacity = false;
			$status = 'full';
			$status_label = __('Full', 'backstage-venue-manager');
			$is_market_group = ($mode === 'market' || $type_slug === 'market_vendor');
			$open_for_dispatch = vms_add_dispatch_assignment_bool_value($assignment, 'open_for_dispatch', true);
			$needed_slots = vms_add_dispatch_assignment_int_value($assignment, array('needed_slots', 'target_slots', 'needed', 'target'));
			if ($needed_slots === null && !$is_market_group && $open_for_dispatch && $slot_limit !== null && $slot_limit !== '') {
				$needed_slots = (int) $slot_limit;
			}
			$open_needed = 0;
			$open_capacity = null;

			if ($slot_limit !== '' && $slot_limit !== null) {
				if ($filled_slots > (int) $slot_limit) {
					$over_capacity = true;
					$available_slots = 0;
					$open_capacity = 0;
					$status = 'over_capacity';
					$status_label = __('Over capacity', 'backstage-venue-manager');
				} else {
					$available_slots = max(0, (int) $slot_limit - $filled_slots);
					$open_capacity = $available_slots;
				}
			}

			if (!$over_capacity && $open_for_dispatch && $needed_slots !== null) {
				$open_needed = max(0, $needed_slots - $filled_slots);
				$is_open = $open_needed > 0;
				$status = $is_open ? 'open' : 'full';
				$status_label = $is_open ? __('Open', 'backstage-venue-manager') : __('Full', 'backstage-venue-manager');
			} elseif (!$over_capacity && !$open_for_dispatch) {
				$status = 'excluded';
				$status_label = __('Excluded', 'backstage-venue-manager');
			}

			$rows[] = array(
				'type_slug' => $type_slug,
				'label' => (string) vms_add_dispatch_type_label($type_slug),
				'mode' => $mode,
				'slot_limit' => $slot_limit,
				'capacity' => $slot_limit,
				'needed_slots' => $needed_slots,
				'target_slots' => $needed_slots,
				'filled_slots' => $filled_slots,
				'available_slots' => $available_slots,
				'open_spots' => $open_needed,
				'open_needed' => $open_needed,
				'open_capacity' => $open_capacity,
				'is_open' => $is_open,
				'is_full' => !$is_open && !$over_capacity,
				'over_capacity' => $over_capacity,
				'over_capacity_by' => $over_capacity ? max(0, $filled_slots - (int) $slot_limit) : 0,
				'status' => $status,
				'status_label' => $status_label,
				'open_for_dispatch' => $open_for_dispatch,
				'allow_over_capacity' => function_exists('vms_event_plan_parse_secondary_vendor_over_capacity_override')
					? vms_event_plan_parse_secondary_vendor_over_capacity_override($assignment['allow_over_capacity'] ?? false)
					: !empty($assignment['allow_over_capacity']),
				'vendor_ids' => $vendor_ids,
			);
		}

		return $rows;
	}
}

if (!function_exists('vms_add_dispatch_context_vendor_need_rows')) {
	function vms_add_dispatch_context_vendor_need_rows(array $context, bool $include_non_open = true): array
	{
		$rows = array();
		$primary_vendor_id = absint($context['primary_vendor_id'] ?? 0);
		if ($primary_vendor_id <= 0) {
			$rows[] = array(
				'target_mode' => 'primary',
				'type_slug' => 'band',
				'label' => __('Primary Vendor', 'backstage-venue-manager'),
				'mode' => 'primary',
				'filled_slots' => 0,
				'needed_slots' => 1,
				'target_slots' => 1,
				'capacity' => 1,
				'open_needed' => 1,
				'open_spots' => 1,
				'open_capacity' => 1,
				'status' => 'open',
				'status_label' => __('Open', 'backstage-venue-manager'),
				'is_open' => true,
			);
		}

		foreach ((array) ($context['secondary_vendor_groups'] ?? array()) as $group) {
			if (!is_array($group)) {
				continue;
			}
			if (!$include_non_open && empty($group['is_open'])) {
				continue;
			}
			$group['target_mode'] = 'secondary';
			$rows[] = $group;
		}

		return $rows;
	}
}

if (!function_exists('vms_add_dispatch_context_exclusion_reason')) {
	function vms_add_dispatch_context_exclusion_reason(?array $context, array $options = array()): string
	{
		if (!$context) {
			return __('Invalid Event Plan', 'backstage-venue-manager');
		}
		$include_past = !empty($options['include_past_events']);
		$include_cancelled = !empty($options['include_cancelled_events']);
		if (trim((string) ($context['event_date'] ?? '')) === '') {
			return __('Missing date', 'backstage-venue-manager');
		}
		if (!$include_past && !empty($context['is_past_event'])) {
			return __('Past event', 'backstage-venue-manager');
		}
		if (!$include_cancelled && in_array((string) ($context['event_status'] ?? ''), array('cancelled', 'archived'), true)) {
			return __('Cancelled or archived', 'backstage-venue-manager');
		}
		if (empty(vms_add_dispatch_context_vendor_need_rows($context, false))) {
			return __('No open vendor needs detected', 'backstage-venue-manager');
		}

		return '';
	}
}

if (!function_exists('vms_add_dispatch_get_event_plan_vendor_need_candidates')) {
	function vms_add_dispatch_get_event_plan_vendor_need_candidates(int $limit = 12): array
	{
		$scan = function_exists('vms_add_dispatch_get_event_plan_need_scan')
			? vms_add_dispatch_get_event_plan_need_scan($limit, 0)
			: array('contexts' => array());

		return (array) ($scan['contexts'] ?? array());
	}
}

if (!function_exists('vms_add_dispatch_get_event_plan_need_scan')) {
	function vms_add_dispatch_get_event_plan_need_scan(int $limit = 12, int $excluded_limit = 8, array $options = array()): array
	{
		$date_key = vms_add_dispatch_event_meta_key('date', '_vms_event_date');
		$today = function_exists('wp_date') ? wp_date('Y-m-d', time(), function_exists('wp_timezone') ? wp_timezone() : null) : current_time('Y-m-d');
		$include_past = !empty($options['include_past_events']);
		$candidate_args = array(
			'post_type' => 'vms_event_plan',
			'post_status' => array('publish', 'draft', 'pending', 'future', 'private'),
			'posts_per_page' => max(50, min(300, max(1, $limit) * 20)),
			'orderby' => 'meta_value',
			'order' => 'ASC',
			'meta_key' => $date_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- ADD intentionally orders its bounded 50-to-300 Event Plan candidate sample by canonical event-date metadata before selecting open vendor needs.
			'fields' => 'ids',
		);
		if (!$include_past) {
			$candidate_args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- ADD applies the canonical event-date lower bound only to the same bounded 50-to-300 candidate sample when past events are excluded.
				array(
					'key' => $date_key,
					'value' => $today,
					'compare' => '>=',
					'type' => 'DATE',
				),
			);
		}
		$diagnostic_args = array(
			'post_type' => 'vms_event_plan',
			'post_status' => array('publish', 'draft', 'pending', 'future', 'private'),
			'posts_per_page' => max(20, min(120, max(1, $excluded_limit) * 8)),
			'orderby' => 'modified',
			'order' => 'DESC',
			'fields' => 'ids',
		);
		$candidate_ids = get_posts($candidate_args);
		$diagnostic_ids = $excluded_limit > 0 ? get_posts($diagnostic_args) : array();
		if (!is_array($candidate_ids) && !is_array($diagnostic_ids)) {
			return array(
				'contexts' => array(),
				'excluded' => array(),
			);
		}
		$ids = array_values(array_unique(array_filter(array_map('absint', array_merge(
			is_array($candidate_ids) ? $candidate_ids : array(),
			is_array($diagnostic_ids) ? $diagnostic_ids : array()
		)))));

		$contexts = array();
		$excluded = array();
		foreach ($ids as $event_plan_id) {
			$context = vms_add_dispatch_get_event_plan_context((int) $event_plan_id);
			if (!$context) {
				continue;
			}
			$reason = vms_add_dispatch_context_exclusion_reason($context, $options);
			if ($reason !== '') {
				if ($excluded_limit > 0 && count($excluded) < $excluded_limit) {
					$excluded[] = array(
						'event_plan_id' => (int) ($context['event_plan_id'] ?? 0),
						'event_title' => (string) ($context['event_title'] ?? ''),
						'event_date' => (string) ($context['event_date'] ?? ''),
						'post_status' => (string) ($context['post_status'] ?? ''),
						'event_status' => (string) ($context['event_status'] ?? ''),
						'reason' => $reason,
					);
				}
				continue;
			}

			$context['vendor_need_rows'] = vms_add_dispatch_context_vendor_need_rows($context, true);
			if (count($contexts) < $limit) {
				$contexts[] = $context;
			}
		}

		usort($contexts, static function (array $left, array $right): int {
			return strcmp((string) ($left['event_date'] ?? ''), (string) ($right['event_date'] ?? ''));
		});

		return array(
			'contexts' => $contexts,
			'excluded' => $excluded,
		);
	}
}

if (!function_exists('vms_add_dispatch_primary_type_suggestions')) {
	function vms_add_dispatch_primary_type_suggestions(): array
	{
		return array(
			'artist',
			'band',
			'bands',
			'headliner',
			'musician',
			'performer',
			'performers',
			'talent',
		);
	}
}

if (!function_exists('vms_add_dispatch_default_target_mode')) {
	function vms_add_dispatch_default_target_mode(array $context): string
	{
		$missing = (array) ($context['missing_slots'] ?? array());
		if (in_array('primary', $missing, true)) {
			return 'primary';
		}
		if (!empty($context['missing_secondary_types']) || !empty($context['secondary_vendor_type'])) {
			return 'secondary';
		}

		return 'secondary';
	}
}

if (!function_exists('vms_add_dispatch_default_vendor_type')) {
	function vms_add_dispatch_default_vendor_type(array $context, string $target_mode): string
	{
		$options = vms_add_dispatch_type_options();
		if (empty($options)) {
			return '';
		}

		if ($target_mode === 'secondary') {
			foreach ((array) ($context['missing_secondary_types'] ?? array()) as $candidate_type) {
				$secondary_type = function_exists('vms_vendor_type_normalize_slug')
					? vms_vendor_type_normalize_slug((string) $candidate_type)
					: sanitize_title((string) $candidate_type);
				if (isset($options[$secondary_type])) {
					return $secondary_type;
				}
			}

			if (!empty($context['secondary_vendor_type'])) {
				$secondary_type = function_exists('vms_vendor_type_normalize_slug')
					? vms_vendor_type_normalize_slug((string) $context['secondary_vendor_type'])
					: sanitize_title((string) $context['secondary_vendor_type']);
				if (isset($options[$secondary_type])) {
					return $secondary_type;
				}
			}
		}

		if ($target_mode === 'primary') {
			foreach (vms_add_dispatch_primary_type_suggestions() as $candidate) {
				if (isset($options[$candidate])) {
					return $candidate;
				}
			}
		}

		$keys = array_keys($options);
		return isset($keys[0]) ? (string) $keys[0] : '';
	}
}

if (!function_exists('vms_add_dispatch_default_message')) {
	function vms_add_dispatch_default_message(array $context, array $builder_args): string
	{
		$target_mode = sanitize_key((string) ($builder_args['target_mode'] ?? 'secondary'));
		$vendor_type = function_exists('vms_vendor_type_normalize_slug')
			? vms_vendor_type_normalize_slug((string) ($builder_args['vendor_type'] ?? ''))
			: sanitize_key(str_replace('-', '_', (string) ($builder_args['vendor_type'] ?? '')));
		$role_label = $target_mode === 'primary'
			? __('primary vendor', 'backstage-venue-manager')
			: strtolower((string) vms_add_dispatch_type_label($vendor_type));

		$title = trim((string) ($context['event_title'] ?? ''));
		$date_label = vms_add_dispatch_format_date((string) ($context['event_date'] ?? ''));
		$venue = trim((string) ($context['venue_name'] ?? ''));

		$message = sprintf(
			/* translators: 1: role/type label, 2: event title, 3: event date, 4: venue */
			__('We have an open %1$s slot for %2$s on %3$s at %4$s. Please let us know whether you are available.', 'backstage-venue-manager'),
			$role_label !== '' ? $role_label : __('vendor', 'backstage-venue-manager'),
			$title !== '' ? $title : __('this event', 'backstage-venue-manager'),
			$date_label !== '' ? $date_label : __('the scheduled date', 'backstage-venue-manager'),
			$venue !== '' ? $venue : __('the venue on file', 'backstage-venue-manager')
		);

		return $message;
	}
}

if (!function_exists('vms_add_dispatch_get_event_plan_context')) {
	function vms_add_dispatch_get_event_plan_context(int $event_plan_id): ?array
	{
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0 || get_post_type($event_plan_id) !== 'vms_event_plan') {
			return null;
		}

		$post = get_post($event_plan_id);
		if (!$post instanceof WP_Post) {
			return null;
		}

		$date_key = vms_add_dispatch_event_meta_key('date', '_vms_event_date');
		$venue_key = vms_add_dispatch_event_meta_key('venue_id', '_vms_venue_id');
		$primary_vendor_key = vms_add_dispatch_primary_vendor_key();
		$secondary_ids_key = vms_add_dispatch_event_meta_key('secondary_vendor_ids', '_vms_secondary_vendor_ids');
		$secondary_index_key = vms_add_dispatch_event_meta_key('secondary_vendor_id', '_vms_secondary_vendor_id');

		$event_date = sanitize_text_field((string) get_post_meta($event_plan_id, $date_key, true));
		$venue_id = absint(get_post_meta($event_plan_id, $venue_key, true));
		$primary_vendor_id = (int) get_post_meta($event_plan_id, $primary_vendor_key, true);
		$secondary_vendor_assignments = function_exists('vms_event_plan_get_secondary_vendor_assignments')
			? (array) vms_event_plan_get_secondary_vendor_assignments($event_plan_id, array(
				'primary_vendor_id' => $primary_vendor_id,
			))
			: array();

		if (!empty($secondary_vendor_assignments) && function_exists('vms_event_plan_get_secondary_vendor_flat_ids_from_assignments')) {
			$secondary_vendor_ids = vms_event_plan_get_secondary_vendor_flat_ids_from_assignments($secondary_vendor_assignments, $primary_vendor_id);
		} else {
			$secondary_vendor_ids = get_post_meta($event_plan_id, $secondary_ids_key, true);
			if (!is_array($secondary_vendor_ids)) {
				$secondary_vendor_ids = get_post_meta($event_plan_id, $secondary_index_key, false);
				if (!is_array($secondary_vendor_ids)) {
					$secondary_vendor_ids = array();
				}
			}
			$secondary_vendor_ids = array_values(array_unique(array_filter(array_map('absint', $secondary_vendor_ids), static function (int $vendor_id): bool {
				return $vendor_id > 0;
			})));
		}

		$secondary_group_rows = function_exists('vms_add_dispatch_secondary_group_rows')
			? vms_add_dispatch_secondary_group_rows($secondary_vendor_assignments)
			: array();
		$missing_secondary_groups = array_values(array_filter($secondary_group_rows, static function (array $row): bool {
			return !empty($row['is_open']);
		}));
		$missing_secondary_types = array_values(array_unique(array_filter(array_map(static function (array $row): string {
			return function_exists('vms_vendor_type_normalize_slug')
				? vms_vendor_type_normalize_slug((string) ($row['type_slug'] ?? ''))
				: sanitize_key(str_replace('-', '_', (string) ($row['type_slug'] ?? '')));
		}, $missing_secondary_groups))));

		$secondary_vendor_type = !empty($missing_secondary_types)
			? (string) $missing_secondary_types[0]
			: (function_exists('vms_event_plan_legacy_secondary_vendor_type_from_assignments')
				? (string) vms_event_plan_legacy_secondary_vendor_type_from_assignments($secondary_vendor_assignments)
				: '');
		$secondary_vendor_type_label = $secondary_vendor_type !== ''
			? (string) vms_add_dispatch_type_label($secondary_vendor_type)
			: '';

		$missing_slots = array();
		if ($primary_vendor_id <= 0) {
			$missing_slots[] = 'primary';
		}
		if (!empty($missing_secondary_groups)) {
			$missing_slots[] = 'secondary';
		}
		$missing_slot_labels = array();
		foreach ($missing_slots as $slot) {
			if ($slot === 'primary') {
				$missing_slot_labels[] = __('Primary vendor missing', 'backstage-venue-manager');
			}
		}
		foreach ($missing_secondary_groups as $group) {
			$open_spots = $group['open_spots'] ?? null;
			if ($open_spots !== null && $open_spots !== '') {
				$missing_slot_labels[] = sprintf(
					/* translators: 1: vendor type label, 2: open slot count */
					_n('%1$s: %2$d slot open', '%1$s: %2$d slots open', (int) $open_spots, 'backstage-venue-manager'),
					(string) ($group['label'] ?? __('Vendor', 'backstage-venue-manager')),
					(int) $open_spots
				);
			} else {
				/* translators: %s: human-readable value used in this message. */
				$missing_slot_labels[] = sprintf(__('Additional vendor (%s) slot open', 'backstage-venue-manager'), (string) ($group['label'] ?? __('Vendor', 'backstage-venue-manager')));
			}
		}

		$event_status = vms_add_dispatch_normalize_event_status((string) get_post_meta($event_plan_id, vms_add_dispatch_event_status_key(), true));

		$context = array(
			'event_plan_id' => $event_plan_id,
			'event_title' => (string) get_the_title($event_plan_id),
			'event_date' => $event_date,
			'venue_id' => $venue_id,
			'venue_name' => $venue_id > 0 ? (string) get_the_title($venue_id) : '',
			'primary_vendor_id' => $primary_vendor_id,
			'primary_vendor_name' => $primary_vendor_id > 0 ? (string) get_the_title($primary_vendor_id) : '',
			'secondary_vendor_assignments' => $secondary_vendor_assignments,
			'secondary_vendor_groups' => $secondary_group_rows,
			'secondary_vendor_type' => $secondary_vendor_type,
			'secondary_vendor_type_label' => $secondary_vendor_type_label,
			'secondary_vendor_ids' => $secondary_vendor_ids,
			'missing_secondary_types' => $missing_secondary_types,
			'missing_secondary_groups' => $missing_secondary_groups,
			'missing_slots' => $missing_slots,
			'missing_slot_labels' => $missing_slot_labels,
			'post_status' => (string) get_post_status($event_plan_id),
			'event_status' => $event_status,
			'is_past_event' => vms_add_dispatch_is_past_event_date($event_date),
		);
		$context['vendor_need_rows'] = function_exists('vms_add_dispatch_context_vendor_need_rows')
			? vms_add_dispatch_context_vendor_need_rows($context, true)
			: array();

		return $context;
	}
}

if (!function_exists('vms_add_dispatch_parse_builder_args')) {
	function vms_add_dispatch_parse_builder_args(array $source, ?array $context = null): array
	{
		$target_mode = sanitize_key((string) ($source['target_mode'] ?? ''));
		if (!in_array($target_mode, array('primary', 'secondary'), true)) {
			$target_mode = $context ? vms_add_dispatch_default_target_mode($context) : 'secondary';
		}

		$vendor_type = function_exists('vms_vendor_type_normalize_slug')
			? vms_vendor_type_normalize_slug((string) ($source['vendor_type'] ?? ''))
			: sanitize_key(str_replace('-', '_', (string) ($source['vendor_type'] ?? '')));
		$options = vms_add_dispatch_type_options();
		if ($vendor_type === '' || !isset($options[$vendor_type])) {
			$vendor_type = $context ? vms_add_dispatch_default_vendor_type($context, $target_mode) : '';
		}

		$message = sanitize_textarea_field((string) ($source['message'] ?? ''));
		if ($message === '' && $context) {
			$message = vms_add_dispatch_default_message($context, array(
				'target_mode' => $target_mode,
				'vendor_type' => $vendor_type,
			));
		}

		$include_no_response = !empty($source['include_no_response']) || !empty($source['include_unknown']);

		return array(
			'target_mode' => $target_mode,
			'vendor_type' => $vendor_type,
			'message' => $message,
			'include_unknown' => $include_no_response ? 1 : 0,
			'include_no_response' => $include_no_response ? 1 : 0,
			'include_tentative' => !empty($source['include_tentative']) ? 1 : 0,
			'include_previously_contacted' => !empty($source['include_previously_contacted']) ? 1 : 0,
		);
	}
}

if (!function_exists('vms_add_dispatch_no_response_explanation')) {
	function vms_add_dispatch_no_response_explanation(): string
	{
		return __('We’re reaching out because your availability for this date is not currently marked unavailable in the vendor portal.', 'backstage-venue-manager');
	}
}

if (!function_exists('vms_add_dispatch_selected_vendor_ids')) {
	function vms_add_dispatch_selected_vendor_ids(array $source): array
	{
		$raw = isset($source['vendor_ids']) && is_array($source['vendor_ids']) ? $source['vendor_ids'] : array();
		return array_values(array_unique(array_filter(array_map('absint', $raw), static function (int $vendor_id): bool {
			return $vendor_id > 0;
		})));
	}
}

if (!function_exists('vms_add_dispatch_vendor_email')) {
	function vms_add_dispatch_vendor_email(int $vendor_id): string
	{
		$vendor_id = absint($vendor_id);
		if ($vendor_id <= 0) {
			return '';
		}

		$candidates = array(
			(string) get_post_meta($vendor_id, vms_add_dispatch_vendor_email_key(), true),
			(function_exists('bvmgr_meta_key') ? (string) get_post_meta($vendor_id, (string) (bvmgr_meta_key('vendor', 'primary_email') ?: '_vms_vendor_primary_email'), true) : (string) get_post_meta($vendor_id, '_vms_vendor_primary_email', true)),
			(function_exists('bvmgr_meta_key') ? (string) get_post_meta($vendor_id, (string) (bvmgr_meta_key('vendor', 'contact_email') ?: '_vms_contact_email'), true) : (string) get_post_meta($vendor_id, '_vms_contact_email', true)),
		);

		foreach ($candidates as $candidate) {
			$email = sanitize_email((string) $candidate);
			if ($email !== '' && is_email($email)) {
				return $email;
			}
		}

		$user_ids = array();
		if (function_exists('vms_vendor_user_links_get_by_vendor')) {
			foreach ((array) vms_vendor_user_links_get_by_vendor($vendor_id, false) as $row) {
				$user_id = absint($row['user_id'] ?? 0);
				if ($user_id > 0) {
					$user_ids[] = $user_id;
				}
			}
		}

		$legacy_user_id = absint(get_post_meta($vendor_id, defined('BVMGR_VENDOR_PRIMARY_USER_META_KEY') ? BVMGR_VENDOR_PRIMARY_USER_META_KEY : '_vms_vendor_user_id', true));
		if ($legacy_user_id > 0) {
			$user_ids[] = $legacy_user_id;
		}

		foreach (array_values(array_unique(array_filter($user_ids))) as $user_id) {
			$user = get_userdata((int) $user_id);
			if (!$user) {
				continue;
			}
			$email = sanitize_email((string) $user->user_email);
			if ($email !== '' && is_email($email)) {
				return $email;
			}
		}

		return '';
	}
}

if (!function_exists('vms_add_dispatch_vendor_previously_contacted')) {
	function vms_add_dispatch_vendor_previously_contacted(int $event_plan_id, int $vendor_id): bool
	{
		global $wpdb;
		$table = vms_add_dispatch_table_name('responses');
		if ($table === '' || $event_plan_id <= 0 || $vendor_id <= 0) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- ADD recipient-history reads query the plugin-owned responses table with %i/%d-prepared values so request composition reflects fresh outreach state.
		$count = (int) $wpdb->get_var($wpdb->prepare(
			'SELECT COUNT(1) FROM %i WHERE event_plan_id = %d AND vendor_id = %d',
			$table,
			$event_plan_id,
			$vendor_id
		));

		return $count > 0;
	}
}


if (!function_exists('vms_add_dispatch_collect_recipient_candidates')) {
	function vms_add_dispatch_collect_recipient_candidates(array $context, array $builder_args): array
	{
		if (!function_exists('vms_vendor_availability_collect_vendors') || !function_exists('vms_vendor_effective_availability_for_date')) {
			return array();
		}

		$event_plan_id = (int) ($context['event_plan_id'] ?? 0);
		$event_date = (string) ($context['event_date'] ?? '');
		$vendor_type = function_exists('vms_vendor_type_normalize_slug')
			? vms_vendor_type_normalize_slug((string) ($builder_args['vendor_type'] ?? ''))
			: sanitize_key(str_replace('-', '_', (string) ($builder_args['vendor_type'] ?? '')));
		if ($event_plan_id <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date) || $vendor_type === '') {
			return array();
		}

		$assigned_ids = array_values(array_unique(array_filter(array_merge(
			array((int) ($context['primary_vendor_id'] ?? 0)),
			array_map('absint', (array) ($context['secondary_vendor_ids'] ?? array()))
		), static function (int $vendor_id): bool {
			return $vendor_id > 0;
		})));

		$vendors = (array) vms_vendor_availability_collect_vendors();
		$rows = array();
		foreach ($vendors as $vendor) {
			$vendor_id = absint($vendor['vendor_id'] ?? 0);
			if ($vendor_id <= 0) {
				continue;
			}

			if ((string) ($vendor['post_status'] ?? '') !== 'publish') {
				continue;
			}

			if (!in_array($vendor_type, (array) ($vendor['type_slugs'] ?? array()), true)) {
				continue;
			}

			$email = vms_add_dispatch_vendor_email($vendor_id);
			$contactable = is_email($email);
			$busy_source = function_exists('vms_vendor_availability_busy_source_for_date')
				? (string) vms_vendor_availability_busy_source_for_date($vendor_id, $event_date, $event_plan_id)
				: '';
			$resolved = (array) vms_vendor_effective_availability_for_date($vendor_id, $event_date, array(
				'busy_source' => sanitize_key($busy_source),
			));

			$state = sanitize_key((string) ($resolved['state'] ?? 'no-response'));
			if ($state === 'no_response') {
				$state = 'no-response';
			}
			$previously_contacted = vms_add_dispatch_vendor_previously_contacted($event_plan_id, $vendor_id);
			$included = true;
			$detail = (string) ($resolved['detail'] ?? '');
			$selection_reason = '';

			if (in_array($vendor_id, $assigned_ids, true)) {
				$included = false;
				$selection_reason = __('Already assigned to this Event Plan.', 'backstage-venue-manager');
			} elseif (!$contactable) {
				$included = false;
				$selection_reason = __('No vendor email on file.', 'backstage-venue-manager');
			} elseif (in_array($state, array('current', 'booked', 'unavailable'), true)) {
				$included = false;
				$selection_reason = $detail !== '' ? $detail : __('Vendor is already unavailable or booked for this date.', 'backstage-venue-manager');
			} elseif ($state === 'tentative' && empty($builder_args['include_tentative'])) {
				$included = false;
				$selection_reason = __('Tentative vendors are currently excluded by your filters.', 'backstage-venue-manager');
			} elseif ($state === 'no-response' && empty($builder_args['include_unknown']) && empty($builder_args['include_no_response'])) {
				$included = false;
				$selection_reason = __('No-response vendors are excluded by the current filters.', 'backstage-venue-manager');
			} elseif (!in_array($state, array('available', 'no-response', 'tentative'), true)) {
				$included = false;
				$selection_reason = $detail !== '' ? $detail : __('This vendor does not currently qualify for ADD outreach.', 'backstage-venue-manager');
			} elseif ($previously_contacted && empty($builder_args['include_previously_contacted'])) {
				$included = false;
				$selection_reason = __('Previously contacted vendors are currently excluded by your filters.', 'backstage-venue-manager');
			} else {
				if ($state === 'available') {
					$selection_reason = __('Ready to contact. Vendor currently shows as available.', 'backstage-venue-manager');
				} elseif ($state === 'tentative') {
					$selection_reason = __('Ready to contact because tentative vendors are included.', 'backstage-venue-manager');
				} else {
					$selection_reason = __('Ready to contact because no-response vendors and vendors without availability setup are included.', 'backstage-venue-manager') . ' ' . vms_add_dispatch_no_response_explanation();
				}
				if ($previously_contacted) {
					$selection_reason .= ' ' . __('This vendor was also contacted previously for this Event Plan.', 'backstage-venue-manager');
				}
			}

			$rows[] = array(
				'vendor_id' => $vendor_id,
				'title' => (string) ($vendor['title'] ?? ''),
				'email' => $email,
				'state' => $state,
				'label' => (string) ($resolved['label'] ?? __('No reply', 'backstage-venue-manager')),
				'detail' => $detail,
				'source' => (string) ($resolved['source'] ?? ''),
				'reason' => (string) ($resolved['reason'] ?? ''),
				'types' => (array) ($vendor['types'] ?? array()),
				'home_venue_label' => (string) ($vendor['home_venue_label'] ?? ''),
				'previously_contacted' => $previously_contacted,
				'contactable' => $contactable,
				'included' => $included,
				'selection_reason' => $selection_reason,
			);
		}

		usort($rows, static function (array $a, array $b): int {
			$ain = !empty($a['included']) ? 0 : 1;
			$bin = !empty($b['included']) ? 0 : 1;
			if ($ain !== $bin) {
				return $ain <=> $bin;
			}
			$priority = array(
				'available' => 1,
				'no-response' => 2,
				'tentative' => 3,
				'booked' => 4,
				'unavailable' => 5,
				'current' => 6,
			);
			$ap = $priority[sanitize_key((string) ($a['state'] ?? ''))] ?? 99;
			$bp = $priority[sanitize_key((string) ($b['state'] ?? ''))] ?? 99;
			if ($ap === $bp) {
				return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
			}
			return $ap <=> $bp;
		});

		return $rows;
	}
}

if (!function_exists('vms_add_dispatch_collect_eligible_recipients')) {
	function vms_add_dispatch_collect_eligible_recipients(array $context, array $builder_args): array
	{
		$rows = vms_add_dispatch_collect_recipient_candidates($context, $builder_args);
		return array_values(array_filter($rows, static function (array $row): bool {
			return !empty($row['included']);
		}));
	}
}

if (!function_exists('vms_add_dispatch_generate_public_key')) {
	function vms_add_dispatch_generate_public_key(): string
	{
		try {
			return strtolower(bin2hex(random_bytes(12)));
		} catch (Exception $e) {
			return strtolower(wp_generate_password(24, false, false));
		}
	}
}

if (!function_exists('vms_add_dispatch_token_signature')) {
	function vms_add_dispatch_token_signature(string $public_key, int $request_id, int $vendor_id, string $created_at): string
	{
		$payload = strtolower($public_key) . '|' . $request_id . '|' . $vendor_id . '|' . $created_at;
		return hash_hmac('sha256', $payload, wp_salt('auth'));
	}
}

if (!function_exists('vms_add_dispatch_build_raw_token')) {
	function vms_add_dispatch_build_raw_token(array $response_row): string
	{
		$public_key = strtolower((string) ($response_row['token_public_key'] ?? ''));
		$request_id = (int) ($response_row['request_id'] ?? 0);
		$vendor_id = (int) ($response_row['vendor_id'] ?? 0);
		$created_at = (string) ($response_row['created_at'] ?? '');
		if ($public_key === '' || $request_id <= 0 || $vendor_id <= 0 || $created_at === '') {
			return '';
		}

		$signature = vms_add_dispatch_token_signature($public_key, $request_id, $vendor_id, $created_at);
		return $public_key . '.' . $signature;
	}
}

if (!function_exists('vms_add_dispatch_build_response_url')) {
	function vms_add_dispatch_build_response_url(array $response_row, string $choice = ''): string
	{
		$raw = vms_add_dispatch_build_raw_token($response_row);
		if ($raw === '') {
			return '';
		}

		$url = home_url('/availability-dispatch/respond/' . rawurlencode($raw));
		$choice = sanitize_key($choice);
		if ($choice !== '') {
			$url = add_query_arg('choice', $choice, $url);
		}

		return $url;
	}
}

if (!function_exists('vms_add_dispatch_get_request_token')) {
	function vms_add_dispatch_get_request_token(): string
	{
		$token = get_query_var('vms_add_dispatch_token');
		if (is_string($token) && $token !== '') {
			return rawurldecode($token);
		}

		$token = bvmgr_request_read_scalar($_GET, 'vms_add_dispatch_token'); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public response-token fallback preserves the established scalar token contract before verification.
		if ($token !== '') {
			return rawurldecode($token);
		}

		$uri = bvmgr_request_current_uri('');
		if ($uri !== '' && preg_match('~^/availability-dispatch/respond/([^/?#]+)~', $uri, $matches)) {
			return rawurldecode((string) $matches[1]);
		}

		return '';
	}
}

if (!function_exists('vms_add_dispatch_get_request_choice')) {
	function vms_add_dispatch_get_request_choice(): string
	{
		$choice = bvmgr_request_read_key($_GET, 'choice'); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Passive response-choice state remains nonce-free while rejecting malformed choice values.
		if ($choice === 'yes') {
			return 'available';
		}
		if ($choice === 'no') {
			return 'unavailable';
		}

		return in_array($choice, array('available', 'unavailable'), true) ? $choice : '';
	}
}

if (!function_exists('vms_add_dispatch_get_response')) {
	function vms_add_dispatch_get_response(int $response_id): ?array
	{
		global $wpdb;
		$table = vms_add_dispatch_table_name('responses');
		if ($table === '' || $response_id <= 0) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- ADD response reads query the plugin-owned responses table with %i/%d-prepared values so admin/public flows can reload fresh custom-table state after mutations.
		$row = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id = %d', $table, $response_id), ARRAY_A);
		return is_array($row) ? $row : null;
	}
}

if (!function_exists('vms_add_dispatch_find_response_by_raw_token')) {
	function vms_add_dispatch_find_response_by_raw_token(string $raw_token): ?array
	{
		$raw_token = trim($raw_token);
		if ($raw_token === '' || strpos($raw_token, '.') === false) {
			return null;
		}

		$parts = explode('.', $raw_token, 2);
		if (count($parts) !== 2) {
			return null;
		}

		$public_key = sanitize_key((string) $parts[0]);
		$signature = strtolower(sanitize_text_field((string) $parts[1]));
		if ($public_key === '' || $signature === '') {
			return null;
		}

		global $wpdb;
		$table = vms_add_dispatch_table_name('responses');
		if ($table === '') {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- ADD token lookups query the plugin-owned responses table with %i/%s-prepared values so public response validation sees fresh token state.
		$row = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE token_public_key = %s', $table, $public_key), ARRAY_A);
		if (!is_array($row)) {
			return null;
		}

		$expected = vms_add_dispatch_token_signature(
			(string) ($row['token_public_key'] ?? ''),
			(int) ($row['request_id'] ?? 0),
			(int) ($row['vendor_id'] ?? 0),
			(string) ($row['created_at'] ?? '')
		);
		if (!hash_equals($expected, $signature)) {
			return null;
		}

		$stored_hash = (string) ($row['token_hash'] ?? '');
		$hash = hash('sha256', $raw_token);
		if ($stored_hash === '' || !hash_equals($stored_hash, $hash)) {
			return null;
		}

		return $row;
	}
}

if (!function_exists('vms_add_dispatch_response_expired')) {
	function vms_add_dispatch_response_expired(array $response_row): bool
	{
		$expires_at = trim((string) ($response_row['token_expires_at'] ?? ''));
		if ($expires_at === '' || strpos($expires_at, '0000-00-00') === 0) {
			return false;
		}

		try {
			$tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
			$expires = new DateTimeImmutable($expires_at, $tz);
			return time() >= $expires->getTimestamp();
		} catch (Exception $e) {
			return false;
		}
	}
}

if (!function_exists('vms_add_dispatch_log')) {
	function vms_add_dispatch_log(string $action, array $args = array()): bool
	{
		global $wpdb;
		$table = vms_add_dispatch_table_name('logs');
		if ($table === '') {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- ADD audit logging persists normalized custom-table rows through wpdb::insert(); no core API preserves this repository contract.
		$ok = $wpdb->insert(
			$table,
			array(
				'request_id' => !empty($args['request_id']) ? absint($args['request_id']) : null,
				'response_id' => !empty($args['response_id']) ? absint($args['response_id']) : null,
				'vendor_id' => !empty($args['vendor_id']) ? absint($args['vendor_id']) : null,
				'event_plan_id' => !empty($args['event_plan_id']) ? absint($args['event_plan_id']) : null,
				'event_date' => sanitize_text_field((string) ($args['event_date'] ?? '')),
				'action' => sanitize_key($action),
				'previous_value' => sanitize_text_field((string) ($args['previous_value'] ?? '')),
				'new_value' => sanitize_text_field((string) ($args['new_value'] ?? '')),
				'source' => sanitize_key((string) ($args['source'] ?? 'add')),
				'actor_user_id' => isset($args['actor_user_id']) ? absint($args['actor_user_id']) : null,
				'created_at' => vms_add_dispatch_now_mysql(),
				'details_json' => wp_json_encode((array) ($args['details'] ?? array())),
			),
			array('%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
		);

		return $ok === 1;
	}
}

if (!function_exists('vms_add_dispatch_get_request')) {
	function vms_add_dispatch_get_request(int $request_id): ?array
	{
		global $wpdb;
		$table = vms_add_dispatch_table_name('requests');
		if ($table === '' || $request_id <= 0) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- ADD request reads query the plugin-owned requests table with %i/%d-prepared values so admin workflows can reload fresh custom-table state after writes.
		$row = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id = %d', $table, $request_id), ARRAY_A);
		return is_array($row) ? $row : null;
	}
}

if (!function_exists('vms_add_dispatch_get_requests_for_event_plan')) {
	function vms_add_dispatch_get_requests_for_event_plan(int $event_plan_id, int $limit = 20): array
	{
		global $wpdb;
		$requests = vms_add_dispatch_table_name('requests');
		$responses = vms_add_dispatch_table_name('responses');
		if ($requests === '' || $responses === '' || $event_plan_id <= 0) {
			return array();
		}

		$limit = max(1, min(100, $limit));
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- ADD request rollups read the plugin-owned request/response tables with %i/%d-prepared values so dashboard history reflects immediate recipient mutations.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.*,
					COUNT(resp.id) AS recipient_total,
					SUM(CASE WHEN resp.response_status = 'available' THEN 1 ELSE 0 END) AS available_count,
					SUM(CASE WHEN resp.response_status = 'unavailable' THEN 1 ELSE 0 END) AS unavailable_count,
					SUM(CASE WHEN resp.response_status = 'requested' THEN 1 ELSE 0 END) AS requested_count
				FROM %i AS r
				LEFT JOIN %i AS resp ON resp.request_id = r.id
				WHERE r.event_plan_id = %d
				GROUP BY r.id
				ORDER BY r.created_at DESC
				LIMIT %d",
				$requests,
				$responses,
				$event_plan_id,
				$limit
			),
			ARRAY_A
		);
		return is_array($rows) ? $rows : array();
	}
}

if (!function_exists('vms_add_dispatch_get_recent_requests')) {
	function vms_add_dispatch_get_recent_requests(int $limit = 20): array
	{
		global $wpdb;
		$requests = vms_add_dispatch_table_name('requests');
		$responses = vms_add_dispatch_table_name('responses');
		if ($requests === '' || $responses === '') {
			return array();
		}

		$limit = max(1, min(100, $limit));
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- ADD recent-request rollups read the plugin-owned request/response tables with %i/%d-prepared values so dashboard history reflects immediate recipient mutations.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.*,
					COUNT(resp.id) AS recipient_total,
					SUM(CASE WHEN resp.response_status = 'available' THEN 1 ELSE 0 END) AS available_count,
					SUM(CASE WHEN resp.response_status = 'unavailable' THEN 1 ELSE 0 END) AS unavailable_count,
					SUM(CASE WHEN resp.response_status = 'requested' THEN 1 ELSE 0 END) AS requested_count
				FROM %i AS r
				LEFT JOIN %i AS resp ON resp.request_id = r.id
				GROUP BY r.id
				ORDER BY r.created_at DESC
				LIMIT %d",
				$requests,
				$responses,
				$limit
			),
			ARRAY_A
		);
		return is_array($rows) ? $rows : array();
	}
}

if (!function_exists('vms_add_dispatch_get_responses_for_request')) {
	function vms_add_dispatch_get_responses_for_request(int $request_id): array
	{
		global $wpdb;
		$table = vms_add_dispatch_table_name('responses');
		if ($table === '' || $request_id <= 0) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- ADD response lists read the plugin-owned responses table with %i/%d-prepared values so admin review shows fresh response state after writes.
		$rows = $wpdb->get_results(
			$wpdb->prepare('SELECT * FROM %i WHERE request_id = %d ORDER BY responded_at DESC, created_at ASC', $table, $request_id),
			ARRAY_A
		);
		if (!is_array($rows)) {
			return array();
		}

		foreach ($rows as &$row) {
			$row['vendor_title'] = (string) get_the_title((int) ($row['vendor_id'] ?? 0));
		}
		unset($row);

		return $rows;
	}
}

if (!function_exists('vms_add_dispatch_get_recent_responses_for_event_plan')) {
	function vms_add_dispatch_get_recent_responses_for_event_plan(int $event_plan_id, int $limit = 12): array
	{
		global $wpdb;
		$table = vms_add_dispatch_table_name('responses');
		if ($table === '' || $event_plan_id <= 0) {
			return array();
		}

		$limit = max(1, min(100, $limit));
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- ADD recent-response reads query the plugin-owned responses table with %i/%d-prepared values so dashboard history stays request-fresh after writes.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE event_plan_id = %d ORDER BY created_at DESC LIMIT %d',
				$table,
				$event_plan_id,
				$limit
			),
			ARRAY_A
		);
		if (!is_array($rows)) {
			return array();
		}

		foreach ($rows as &$row) {
			$row['vendor_title'] = (string) get_the_title((int) ($row['vendor_id'] ?? 0));
		}
		unset($row);

		return $rows;
	}
}

if (!function_exists('vms_add_dispatch_create_request')) {
	function vms_add_dispatch_create_request(int $event_plan_id, array $builder_args, array $recipients)
	{
		global $wpdb;
		$event_plan_id = absint($event_plan_id);
		$context = vms_add_dispatch_get_event_plan_context($event_plan_id);
		if (!$context) {
			return new WP_Error('add_dispatch_event_missing', __('The selected Event Plan could not be found.', 'backstage-venue-manager'));
		}
		if (empty($recipients)) {
			return new WP_Error('add_dispatch_no_recipients', __('Choose at least one recipient before sending ADD.', 'backstage-venue-manager'));
		}

		$requests_table = vms_add_dispatch_table_name('requests');
		$responses_table = vms_add_dispatch_table_name('responses');
		if ($requests_table === '' || $responses_table === '') {
			return new WP_Error('add_dispatch_schema_missing', __('ADD tables are not available yet.', 'backstage-venue-manager'));
		}

		$now = vms_add_dispatch_now_mysql();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- ADD request creation persists normalized custom-table rows through wpdb::insert(); no core API preserves this repository lifecycle.
		$inserted = $wpdb->insert(
			$requests_table,
			array(
				'event_plan_id' => $event_plan_id,
				'venue_id' => (int) ($context['venue_id'] ?? 0),
				'event_date' => (string) ($context['event_date'] ?? ''),
				'request_mode' => 'single_event',
				'target_mode' => sanitize_key((string) ($builder_args['target_mode'] ?? 'secondary')),
				'vendor_type' => sanitize_title((string) ($builder_args['vendor_type'] ?? '')),
				'message' => sanitize_textarea_field((string) ($builder_args['message'] ?? '')),
				'status' => 'active',
				'include_unknown' => !empty($builder_args['include_unknown']) ? 1 : 0,
				'include_tentative' => !empty($builder_args['include_tentative']) ? 1 : 0,
				'include_previously_contacted' => !empty($builder_args['include_previously_contacted']) ? 1 : 0,
				'recipient_count' => count($recipients),
				'context_json' => wp_json_encode(array(
					'event_title' => (string) ($context['event_title'] ?? ''),
					'venue_name' => (string) ($context['venue_name'] ?? ''),
					'secondary_vendor_type' => (string) ($context['secondary_vendor_type'] ?? ''),
				)),
				'created_by' => get_current_user_id(),
				'created_at' => $now,
			),
			array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%d', '%s')
		);
		if ($inserted !== 1) {
			return new WP_Error('add_dispatch_request_insert_failed', __('The ADD request could not be created.', 'backstage-venue-manager'));
		}

		$request_id = (int) $wpdb->insert_id;
		$responses = array();
		$expires_at = wp_date('Y-m-d H:i:s', time() + (14 * DAY_IN_SECONDS), function_exists('wp_timezone') ? wp_timezone() : null);

		foreach ($recipients as $recipient) {
			$vendor_id = absint($recipient['vendor_id'] ?? 0);
			$email = sanitize_email((string) ($recipient['email'] ?? ''));
			if ($vendor_id <= 0 || !is_email($email)) {
				continue;
			}

			$public_key = vms_add_dispatch_generate_public_key();
			$raw_token = $public_key . '.' . vms_add_dispatch_token_signature($public_key, $request_id, $vendor_id, $now);
			$token_hash = hash('sha256', $raw_token);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- ADD recipient creation persists normalized custom-table rows through wpdb::insert(); no core API preserves this repository lifecycle.
			$response_inserted = $wpdb->insert(
				$responses_table,
				array(
					'request_id' => $request_id,
					'vendor_id' => $vendor_id,
					'event_plan_id' => $event_plan_id,
					'venue_id' => (int) ($context['venue_id'] ?? 0),
					'event_date' => (string) ($context['event_date'] ?? ''),
					'vendor_email' => $email,
					'response_status' => 'requested',
					'response_source' => '',
					'token_public_key' => $public_key,
					'token_hash' => $token_hash,
					'token_expires_at' => $expires_at,
					'last_sent_at' => $now,
					'send_count' => 1,
					'created_at' => $now,
					'updated_at' => $now,
				),
				array('%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
			);
			if ($response_inserted !== 1) {
				continue;
			}

			$response_row = vms_add_dispatch_get_response((int) $wpdb->insert_id);
			if (is_array($response_row)) {
				$response_row['vendor_title'] = (string) ($recipient['title'] ?? get_the_title($vendor_id));
				$responses[] = $response_row;
			}
		}

		if (empty($responses)) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- ADD request rollback deletes the plugin-owned request row directly so partial recipient batches are fully reverted in-request.
			$wpdb->delete($requests_table, array('id' => $request_id), array('%d'));
			return new WP_Error('add_dispatch_response_insert_failed', __('The ADD request could not create any valid recipient rows.', 'backstage-venue-manager'));
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- ADD request finalization writes the plugin-owned request row directly so recipient totals remain consistent with the freshly inserted response rows.
		$wpdb->update(
			$requests_table,
			array(
				'recipient_count' => count($responses),
				'updated_at' => $now,
			),
			array('id' => $request_id),
			array('%d', '%s'),
			array('%d')
		);

		vms_add_dispatch_log('request_created', array(
			'request_id' => $request_id,
			'event_plan_id' => $event_plan_id,
			'event_date' => (string) ($context['event_date'] ?? ''),
			'source' => 'add',
			'actor_user_id' => get_current_user_id(),
			'details' => array(
				'recipient_count' => count($responses),
				'target_mode' => (string) ($builder_args['target_mode'] ?? ''),
				'vendor_type' => (string) ($builder_args['vendor_type'] ?? ''),
			),
		));

		$request = vms_add_dispatch_get_request($request_id);
		if (!$request) {
			return new WP_Error('add_dispatch_request_reload_failed', __('The ADD request was created but could not be reloaded.', 'backstage-venue-manager'));
		}

		return array(
			'request' => $request,
			'responses' => $responses,
			'context' => $context,
		);
	}
}

if (!function_exists('vms_add_dispatch_prepare_resend')) {
	function vms_add_dispatch_prepare_resend(int $response_id)
	{
		global $wpdb;
		$responses_table = vms_add_dispatch_table_name('responses');
		if ($responses_table === '') {
			return new WP_Error('add_dispatch_schema_missing', __('ADD tables are not available yet.', 'backstage-venue-manager'));
		}

		$response = vms_add_dispatch_get_response($response_id);
		if (!$response) {
			return new WP_Error('add_dispatch_response_missing', __('The requested ADD response could not be found.', 'backstage-venue-manager'));
		}

		$request = vms_add_dispatch_get_request((int) ($response['request_id'] ?? 0));
		if (!$request) {
			return new WP_Error('add_dispatch_request_missing', __('The parent ADD request could not be found.', 'backstage-venue-manager'));
		}
		if (sanitize_key((string) ($request['status'] ?? '')) !== 'active') {
			return new WP_Error('add_dispatch_request_closed', __('This ADD request is already closed.', 'backstage-venue-manager'));
		}

		$status = sanitize_key((string) ($response['response_status'] ?? 'requested'));
		if (in_array($status, array('available', 'unavailable'), true)) {
			return new WP_Error('add_dispatch_already_answered', __('This vendor has already responded.', 'backstage-venue-manager'));
		}

		$now = vms_add_dispatch_now_mysql();
		$expires_at = wp_date('Y-m-d H:i:s', time() + (14 * DAY_IN_SECONDS), function_exists('wp_timezone') ? wp_timezone() : null);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- ADD resend writes the plugin-owned response row directly so fresh token expiry and send counts persist immediately.
		$updated = $wpdb->update(
			$responses_table,
			array(
				'token_expires_at' => $expires_at,
				'last_sent_at' => $now,
				'send_count' => max(1, (int) ($response['send_count'] ?? 0)) + 1,
				'updated_at' => $now,
			),
			array('id' => $response_id),
			array('%s', '%s', '%d', '%s'),
			array('%d')
		);
		if ($updated === false) {
			return new WP_Error('add_dispatch_resend_update_failed', __('The response could not be refreshed for resend.', 'backstage-venue-manager'));
		}

		$fresh = vms_add_dispatch_get_response($response_id);
		if (!$fresh) {
			return new WP_Error('add_dispatch_resend_reload_failed', __('The refreshed response could not be loaded.', 'backstage-venue-manager'));
		}

		return array(
			'request' => $request,
			'response' => $fresh,
			'context' => vms_add_dispatch_get_event_plan_context((int) ($response['event_plan_id'] ?? 0)),
		);
	}
}

if (!function_exists('vms_add_dispatch_close_request')) {
	function vms_add_dispatch_close_request(int $request_id)
	{
		global $wpdb;
		$table = vms_add_dispatch_table_name('requests');
		if ($table === '') {
			return new WP_Error('add_dispatch_schema_missing', __('ADD tables are not available yet.', 'backstage-venue-manager'));
		}

		$request = vms_add_dispatch_get_request($request_id);
		if (!$request) {
			return new WP_Error('add_dispatch_request_missing', __('The ADD request could not be found.', 'backstage-venue-manager'));
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- ADD request closure writes the plugin-owned request row directly so admin close actions persist immediately.
		$updated = $wpdb->update(
			$table,
			array(
				'status' => 'closed',
				'updated_at' => vms_add_dispatch_now_mysql(),
				'closed_at' => vms_add_dispatch_now_mysql(),
			),
			array('id' => $request_id),
			array('%s', '%s', '%s'),
			array('%d')
		);
		if ($updated === false) {
			return new WP_Error('add_dispatch_close_failed', __('The ADD request could not be closed.', 'backstage-venue-manager'));
		}

		vms_add_dispatch_log('request_closed', array(
			'request_id' => $request_id,
			'event_plan_id' => (int) ($request['event_plan_id'] ?? 0),
			'event_date' => (string) ($request['event_date'] ?? ''),
			'source' => 'admin',
			'actor_user_id' => get_current_user_id(),
		));

		return true;
	}
}

if (!function_exists('vms_add_dispatch_write_vendor_availability')) {
	function vms_add_dispatch_write_vendor_availability(int $vendor_id, string $event_date, string $new_value)
	{
		$vendor_id = absint($vendor_id);
		$new_value = sanitize_key($new_value);
		if ($vendor_id <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date) || !in_array($new_value, array('available', 'unavailable'), true)) {
			return new WP_Error('add_dispatch_invalid_write', __('The ADD availability write is invalid.', 'backstage-venue-manager'));
		}

		$manual = function_exists('vms_vendor_normalize_manual_availability')
			? (array) vms_vendor_normalize_manual_availability($vendor_id)
			: (array) get_post_meta($vendor_id, '_vms_availability_manual', true);

		$normalized = array();
		foreach ($manual as $date => $state) {
			$date = sanitize_text_field((string) $date);
			$state = sanitize_key((string) $state);
			if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
				continue;
			}
			if (!in_array($state, array('available', 'unavailable'), true)) {
				continue;
			}
			$normalized[$date] = $state;
		}

		$previous = isset($normalized[$event_date]) ? sanitize_key((string) $normalized[$event_date]) : '';
		$normalized[$event_date] = $new_value;

		$updated = update_post_meta($vendor_id, '_vms_availability_manual', $normalized);
		if ($updated === false) {
			$fresh = function_exists('vms_vendor_normalize_manual_availability')
				? (array) vms_vendor_normalize_manual_availability($vendor_id)
				: (array) get_post_meta($vendor_id, '_vms_availability_manual', true);
			$fresh_value = isset($fresh[$event_date]) ? sanitize_key((string) $fresh[$event_date]) : '';
			if ($fresh_value !== $new_value) {
				return new WP_Error('add_dispatch_write_failed', __('The vendor availability could not be updated.', 'backstage-venue-manager'));
			}
		}

		update_post_meta($vendor_id, '_vms_availability_preferred_method', 'manual');
		if (function_exists('vms_vendor_flag_vendor_update')) {
			vms_vendor_flag_vendor_update($vendor_id, 'availability_add_dispatch');
		}

		return array(
			'previous' => $previous,
			'new' => $new_value,
		);
	}
}

if (!function_exists('vms_add_dispatch_record_public_response')) {
	function vms_add_dispatch_record_public_response(array $response_row, string $choice, string $source = 'email')
	{
		global $wpdb;
		$responses_table = vms_add_dispatch_table_name('responses');
		if ($responses_table === '') {
			return new WP_Error('add_dispatch_schema_missing', __('ADD tables are not available yet.', 'backstage-venue-manager'));
		}

		$choice = sanitize_key($choice);
		if (!in_array($choice, array('available', 'unavailable'), true)) {
			return new WP_Error('add_dispatch_choice_invalid', __('The selected availability choice is invalid.', 'backstage-venue-manager'));
		}

		$response_id = (int) ($response_row['id'] ?? 0);
		if ($response_id <= 0) {
			return new WP_Error('add_dispatch_response_missing', __('The ADD response could not be found.', 'backstage-venue-manager'));
		}

		$current_status = sanitize_key((string) ($response_row['response_status'] ?? 'requested'));
		if (in_array($current_status, array('available', 'unavailable'), true)) {
			return array(
				'status' => $current_status,
				'already_recorded' => true,
				'responded_at' => (string) ($response_row['responded_at'] ?? ''),
			);
		}

		if (vms_add_dispatch_response_expired($response_row)) {
			return new WP_Error('add_dispatch_expired', __('This ADD response link has expired.', 'backstage-venue-manager'));
		}

		$write = vms_add_dispatch_write_vendor_availability(
			(int) ($response_row['vendor_id'] ?? 0),
			(string) ($response_row['event_date'] ?? ''),
			$choice
		);
		if (is_wp_error($write)) {
			return $write;
		}

		$now = vms_add_dispatch_now_mysql();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- ADD public-response writes the plugin-owned response row directly so availability choices and audit fields persist immediately.
		$updated = $wpdb->update(
			$responses_table,
			array(
				'response_status' => $choice,
				'responded_at' => $now,
				'response_source' => sanitize_key($source),
				'availability_written' => 1,
				'availability_written_at' => $now,
				'availability_before' => sanitize_text_field((string) ($write['previous'] ?? '')),
				'availability_after' => sanitize_text_field((string) ($write['new'] ?? '')),
				'updated_at' => $now,
			),
			array('id' => $response_id),
			array('%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s'),
			array('%d')
		);
		if ($updated === false) {
			return new WP_Error('add_dispatch_response_update_failed', __('The ADD response record could not be updated.', 'backstage-venue-manager'));
		}

		vms_add_dispatch_log('availability_write', array(
			'request_id' => (int) ($response_row['request_id'] ?? 0),
			'response_id' => $response_id,
			'vendor_id' => (int) ($response_row['vendor_id'] ?? 0),
			'event_plan_id' => (int) ($response_row['event_plan_id'] ?? 0),
			'event_date' => (string) ($response_row['event_date'] ?? ''),
			'previous_value' => (string) ($write['previous'] ?? ''),
			'new_value' => (string) ($write['new'] ?? ''),
			'source' => 'add',
			'details' => array(
				'response_source' => sanitize_key($source),
				'vendor_email' => (string) ($response_row['vendor_email'] ?? ''),
			),
		));

		return array(
			'status' => $choice,
			'already_recorded' => false,
			'responded_at' => $now,
			'previous' => (string) ($write['previous'] ?? ''),
			'new' => (string) ($write['new'] ?? ''),
		);
	}
}

if (!function_exists('vms_add_dispatch_is_primary_vendor_type_slug')) {
	function vms_add_dispatch_is_primary_vendor_type_slug(string $slug): bool
	{
		$slug = sanitize_title($slug);
		if ($slug === '') {
			return false;
		}

		$candidates = function_exists('vms_add_dispatch_primary_type_suggestions')
			? (array) vms_add_dispatch_primary_type_suggestions()
			: array('artist', 'band', 'bands', 'headliner', 'musician', 'performer', 'performers', 'talent');

		$candidates = array_values(array_unique(array_filter(array_map('sanitize_title', $candidates))));
		return in_array($slug, $candidates, true);
	}
}

if (!function_exists('vms_add_dispatch_resolve_vendor_interest_target')) {
	function vms_add_dispatch_resolve_vendor_interest_target(array $context, int $vendor_id): array
	{
		$vendor_id = absint($vendor_id);
		if ($vendor_id <= 0) {
			return array(
				'ok' => false,
				'reason' => __('That vendor record could not be found.', 'backstage-venue-manager'),
				'target_mode' => '',
				'vendor_type' => '',
			);
		}

		$viewer_types = vms_add_dispatch_vendor_type_slugs($vendor_id);
		$viewer_type = !empty($viewer_types) ? (string) $viewer_types[0] : '';
		if ($viewer_type === '') {
			return array(
				'ok' => false,
				'reason' => __('This vendor does not have a primary vendor type yet.', 'backstage-venue-manager'),
				'target_mode' => '',
				'vendor_type' => '',
			);
		}

		$assigned_ids = array_values(array_unique(array_filter(array_merge(
			array((int) ($context['primary_vendor_id'] ?? 0)),
			array_map('absint', (array) ($context['secondary_vendor_ids'] ?? array()))
		), static function (int $assigned_vendor_id): bool {
			return $assigned_vendor_id > 0;
		})));

		if (in_array($vendor_id, $assigned_ids, true)) {
			return array(
				'ok' => false,
				'reason' => __('You are already assigned to this Event Plan.', 'backstage-venue-manager'),
				'target_mode' => '',
				'vendor_type' => $viewer_type,
			);
		}

		$missing = array_map('sanitize_key', (array) ($context['missing_slots'] ?? array()));
		$missing_secondary_types = array_values(array_unique(array_filter(array_map(static function ($type_slug): string {
			return function_exists('vms_vendor_type_normalize_slug')
				? vms_vendor_type_normalize_slug((string) $type_slug)
				: sanitize_title((string) $type_slug);
		}, (array) ($context['missing_secondary_types'] ?? array())))));

		$viewer_primary_type = '';
		foreach ($viewer_types as $candidate_type) {
			if (vms_add_dispatch_is_primary_vendor_type_slug((string) $candidate_type)) {
				$viewer_primary_type = (string) $candidate_type;
				break;
			}
		}

		if (in_array('primary', $missing, true) && $viewer_primary_type !== '') {
			return array(
				'ok' => true,
				'reason' => '',
				'target_mode' => 'primary',
				'vendor_type' => $viewer_primary_type,
			);
		}

		foreach ($viewer_types as $candidate_type) {
			if (!in_array((string) $candidate_type, $missing_secondary_types, true)) {
				continue;
			}

			return array(
				'ok' => true,
				'reason' => '',
				'target_mode' => 'secondary',
				'vendor_type' => (string) $candidate_type,
			);
		}

		return array(
			'ok' => false,
			'reason' => __('That opportunity is no longer open for this vendor type.', 'backstage-venue-manager'),
			'target_mode' => '',
			'vendor_type' => $viewer_type,
		);
	}
}

if (!function_exists('vms_add_dispatch_get_portal_interest_response')) {
	function vms_add_dispatch_get_portal_interest_response(int $event_plan_id, int $vendor_id, bool $require_active = false): ?array
	{
		global $wpdb;
		$requests_table = vms_add_dispatch_table_name('requests');
		$responses_table = vms_add_dispatch_table_name('responses');
		if ($requests_table === '' || $responses_table === '' || $event_plan_id <= 0 || $vendor_id <= 0) {
			return null;
		}

		if ($require_active) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Portal-interest reads join the plugin-owned request/response tables with %i-prepared identifiers so portal/admin flows see fresh assignment state after writes.
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT resp.*, req.status AS request_status, req.vendor_type, req.target_mode
					FROM %i AS resp
					INNER JOIN %i AS req ON req.id = resp.request_id
					WHERE resp.event_plan_id = %d
					  AND resp.vendor_id = %d
					  AND resp.response_source = %s
					  AND req.status = %s
					ORDER BY resp.id DESC
					LIMIT 1",
					$responses_table,
					$requests_table,
					$event_plan_id,
					$vendor_id,
					'portal_interest',
					'active'
				),
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Portal-interest reads join the plugin-owned request/response tables with %i-prepared identifiers so portal/admin flows see fresh assignment state after writes.
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT resp.*, req.status AS request_status, req.vendor_type, req.target_mode
					FROM %i AS resp
					INNER JOIN %i AS req ON req.id = resp.request_id
					WHERE resp.event_plan_id = %d
					  AND resp.vendor_id = %d
					  AND resp.response_source = %s
					ORDER BY resp.id DESC
					LIMIT 1",
					$responses_table,
					$requests_table,
					$event_plan_id,
					$vendor_id,
					'portal_interest'
				),
				ARRAY_A
			);
		}
		return is_array($row) ? $row : null;
	}
}

if (!function_exists('vms_add_dispatch_pending_portal_interest_count')) {
	function vms_add_dispatch_pending_portal_interest_count(): int
	{
		static $request_cache = null;
		if ($request_cache !== null) {
			return (int) $request_cache;
		}

		global $wpdb;
		$requests_table = vms_add_dispatch_table_name('requests');
		$responses_table = vms_add_dispatch_table_name('responses');
		if ($requests_table === '' || $responses_table === '') {
			$request_cache = 0;
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Portal-interest counters join the plugin-owned request/response tables with %i-prepared identifiers, and this request-local dashboard cache must start from fresh custom-table state.
		$request_cache = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(1)
			 FROM %i AS resp
			 INNER JOIN %i AS req ON req.id = resp.request_id
			 WHERE req.status = %s
			   AND resp.response_source = %s
			   AND resp.response_status = %s
			   AND resp.assigned_at IS NULL",
			$responses_table,
			$requests_table,
			'active',
			'portal_interest',
			'available'
		));

		return (int) $request_cache;
	}
}

if (!function_exists('vms_add_dispatch_get_vendor_portal_interest_rows')) {
	function vms_add_dispatch_get_vendor_portal_interest_rows(int $vendor_id, int $limit = 50): array
	{
		global $wpdb;
		$requests_table = vms_add_dispatch_table_name('requests');
		$responses_table = vms_add_dispatch_table_name('responses');
		if ($requests_table === '' || $responses_table === '' || $vendor_id <= 0) {
			return array();
		}

		$limit = max(1, min(200, $limit));
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Portal-interest history reads join the plugin-owned request/response tables with %i-prepared identifiers so vendor/admin review sees fresh custom-table state after writes.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT resp.*, req.status AS request_status, req.vendor_type, req.target_mode
				 FROM %i AS resp
				 INNER JOIN %i AS req ON req.id = resp.request_id
				 WHERE resp.vendor_id = %d
				   AND resp.response_source = %s
				 ORDER BY COALESCE(resp.responded_at, resp.created_at) DESC, resp.created_at DESC
				 LIMIT %d",
				$responses_table,
				$requests_table,
				$vendor_id,
				'portal_interest',
				$limit
			),
			ARRAY_A
		);
		return is_array($rows) ? $rows : array();
	}
}

if (!function_exists('vms_add_dispatch_create_portal_interest')) {
	function vms_add_dispatch_create_portal_interest(int $event_plan_id, int $vendor_id)
	{
		if (!vms_add_dispatch_enabled_by_settings()) {
			return new WP_Error('add_dispatch_disabled', __('ADD is currently disabled in settings.', 'backstage-venue-manager'));
		}

		$event_plan_id = absint($event_plan_id);
		$vendor_id = absint($vendor_id);
		$context = vms_add_dispatch_get_event_plan_context($event_plan_id);
		if (!$context) {
			return new WP_Error('add_dispatch_event_missing', __('That Event Plan could not be found.', 'backstage-venue-manager'));
		}
		if (!empty($context['is_past_event'])) {
			return new WP_Error('add_dispatch_event_past', __('That event date has already passed.', 'backstage-venue-manager'));
		}

		$existing = vms_add_dispatch_get_portal_interest_response($event_plan_id, $vendor_id, true);
		if (is_array($existing)) {
			$existing_status = sanitize_key((string) ($existing['response_status'] ?? 'requested'));
			if ($existing_status === 'unavailable') {
				$reactivated = vms_add_dispatch_reactivate_portal_interest($existing);
				if (is_wp_error($reactivated)) {
					return $reactivated;
				}
				$request = vms_add_dispatch_get_request((int) ($existing['request_id'] ?? 0));
				$response = vms_add_dispatch_get_response((int) ($existing['id'] ?? 0));
				if (function_exists('vms_add_dispatch_send_operator_interest_notification') && is_array($request) && is_array($response)) {
					vms_add_dispatch_send_operator_interest_notification($request, $response, $context);
				}
				return array(
					'already_exists' => false,
					'request' => is_array($request) ? $request : array(),
					'response' => is_array($response) ? $response : array(),
					'context' => $context,
				);
			}
			$request = vms_add_dispatch_get_request((int) ($existing['request_id'] ?? 0));
			return array(
				'already_exists' => true,
				'request' => is_array($request) ? $request : array(),
				'response' => $existing,
				'context' => $context,
			);
		}

		$target = vms_add_dispatch_resolve_vendor_interest_target($context, $vendor_id);
		if (empty($target['ok'])) {
			return new WP_Error('add_dispatch_interest_closed', (string) ($target['reason'] ?? __('That opportunity is no longer open.', 'backstage-venue-manager')));
		}

		$recipient_email = vms_add_dispatch_vendor_email($vendor_id);
		if (!is_email($recipient_email)) {
			$current_user = wp_get_current_user();
			if ($current_user instanceof WP_User) {
				$current_user_email = sanitize_email((string) $current_user->user_email);
				if ($current_user_email !== '' && is_email($current_user_email)) {
					$recipient_email = $current_user_email;
				}
			}
		}

		$recipient = array(
			'vendor_id' => $vendor_id,
			'title' => (string) get_the_title($vendor_id),
			'email' => $recipient_email,
		);

		$builder_args = array(
			'target_mode' => (string) ($target['target_mode'] ?? 'secondary'),
			'vendor_type' => (string) ($target['vendor_type'] ?? ''),
			'message' => __('Vendor expressed interest through the vendor portal.', 'backstage-venue-manager'),
			'include_unknown' => 1,
			'include_tentative' => 1,
			'include_previously_contacted' => 1,
		);

		$created = vms_add_dispatch_create_request($event_plan_id, $builder_args, array($recipient));
		if (is_wp_error($created)) {
			return $created;
		}

		$response = isset($created['responses'][0]) && is_array($created['responses'][0]) ? $created['responses'][0] : array();
		$response_id = (int) ($response['id'] ?? 0);
		if ($response_id <= 0) {
			return new WP_Error('add_dispatch_response_missing', __('The interest response could not be created.', 'backstage-venue-manager'));
		}

		$recorded = vms_add_dispatch_record_public_response($response, 'available', 'portal_interest');
		if (is_wp_error($recorded)) {
			return $recorded;
		}

		$request = isset($created['request']) && is_array($created['request'])
			? $created['request']
			: vms_add_dispatch_get_request((int) ($response['request_id'] ?? 0));
		$response = vms_add_dispatch_get_response($response_id);

		if (function_exists('vms_add_dispatch_send_operator_interest_notification') && is_array($request) && is_array($response)) {
			vms_add_dispatch_send_operator_interest_notification($request, $response, $context);
		}

		vms_add_dispatch_log('portal_interest_submitted', array(
			'request_id' => (int) ($request['id'] ?? 0),
			'response_id' => $response_id,
			'vendor_id' => $vendor_id,
			'event_plan_id' => $event_plan_id,
			'event_date' => (string) ($context['event_date'] ?? ''),
			'source' => 'portal_interest',
			'actor_user_id' => get_current_user_id(),
			'details' => array(
				'target_mode' => (string) ($target['target_mode'] ?? ''),
				'vendor_type' => (string) ($target['vendor_type'] ?? ''),
			),
		));

		return array(
			'already_exists' => false,
			'request' => is_array($request) ? $request : array(),
			'response' => is_array($response) ? $response : array(),
			'context' => $context,
		);
	}
}

if (!function_exists('vms_add_dispatch_restore_vendor_availability')) {
	function vms_add_dispatch_restore_vendor_availability(int $vendor_id, string $event_date, string $previous_value)
	{
		$vendor_id = absint($vendor_id);
		$previous_value = sanitize_key($previous_value);
		if ($vendor_id <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date)) {
			return new WP_Error('add_dispatch_invalid_restore', __('The availability restore request is invalid.', 'backstage-venue-manager'));
		}

		$manual = function_exists('vms_vendor_normalize_manual_availability')
			? (array) vms_vendor_normalize_manual_availability($vendor_id)
			: (array) get_post_meta($vendor_id, '_vms_availability_manual', true);
		$normalized = array();
		foreach ($manual as $date => $state) {
			$date = sanitize_text_field((string) $date);
			$state = sanitize_key((string) $state);
			if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
				continue;
			}
			if (!in_array($state, array('available', 'unavailable'), true)) {
				continue;
			}
			$normalized[$date] = $state;
		}

		if (in_array($previous_value, array('available', 'unavailable'), true)) {
			$normalized[$event_date] = $previous_value;
		} else {
			unset($normalized[$event_date]);
		}

		$updated = update_post_meta($vendor_id, '_vms_availability_manual', $normalized);
		if ($updated === false) {
			$fresh = function_exists('vms_vendor_normalize_manual_availability')
				? (array) vms_vendor_normalize_manual_availability($vendor_id)
				: (array) get_post_meta($vendor_id, '_vms_availability_manual', true);
			$fresh_value = isset($fresh[$event_date]) ? sanitize_key((string) $fresh[$event_date]) : '';
			if ($fresh_value !== $previous_value) {
				return new WP_Error('add_dispatch_restore_failed', __('The vendor availability could not be restored.', 'backstage-venue-manager'));
			}
		}

		update_post_meta($vendor_id, '_vms_availability_preferred_method', 'manual');
		if (function_exists('vms_vendor_flag_vendor_update')) {
			vms_vendor_flag_vendor_update($vendor_id, 'availability_add_dispatch_restore');
		}

		return true;
	}
}

if (!function_exists('vms_add_dispatch_reactivate_portal_interest')) {
	function vms_add_dispatch_reactivate_portal_interest(array $response_row)
	{
		global $wpdb;
		$responses_table = vms_add_dispatch_table_name('responses');
		if ($responses_table === '') {
			return new WP_Error('add_dispatch_schema_missing', __('ADD tables are not available yet.', 'backstage-venue-manager'));
		}

		$response_id = (int) ($response_row['id'] ?? 0);
		if ($response_id <= 0) {
			return new WP_Error('add_dispatch_response_missing', __('The ADD response could not be found.', 'backstage-venue-manager'));
		}

		$write = vms_add_dispatch_write_vendor_availability(
			(int) ($response_row['vendor_id'] ?? 0),
			(string) ($response_row['event_date'] ?? ''),
			'available'
		);
		if (is_wp_error($write)) {
			return $write;
		}

		$now = vms_add_dispatch_now_mysql();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Portal-interest reactivation writes the plugin-owned response row directly so restored availability state persists immediately.
		$updated = $wpdb->update(
			$responses_table,
			array(
				'response_status' => 'available',
				'responded_at' => $now,
				'response_source' => 'portal_interest',
				'availability_written' => 1,
				'availability_written_at' => $now,
				'availability_before' => sanitize_text_field((string) ($write['previous'] ?? '')),
				'availability_after' => 'available',
				'updated_at' => $now,
			),
			array('id' => $response_id),
			array('%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s'),
			array('%d')
		);
		if ($updated === false) {
			return new WP_Error('add_dispatch_response_update_failed', __('The interest response could not be reactivated.', 'backstage-venue-manager'));
		}

		vms_add_dispatch_log('portal_interest_reactivated', array(
			'request_id' => (int) ($response_row['request_id'] ?? 0),
			'response_id' => $response_id,
			'vendor_id' => (int) ($response_row['vendor_id'] ?? 0),
			'event_plan_id' => (int) ($response_row['event_plan_id'] ?? 0),
			'event_date' => (string) ($response_row['event_date'] ?? ''),
			'source' => 'portal_interest',
			'actor_user_id' => get_current_user_id(),
		));

		return true;
	}
}

if (!function_exists('vms_add_dispatch_withdraw_portal_interest')) {
	function vms_add_dispatch_withdraw_portal_interest(int $event_plan_id, int $vendor_id)
	{
		global $wpdb;
		$requests_table = vms_add_dispatch_table_name('requests');
		$responses_table = vms_add_dispatch_table_name('responses');
		if ($requests_table === '' || $responses_table === '') {
			return new WP_Error('add_dispatch_schema_missing', __('ADD tables are not available yet.', 'backstage-venue-manager'));
		}

		$interest = vms_add_dispatch_get_portal_interest_response($event_plan_id, $vendor_id, false);
		if (!is_array($interest)) {
			return new WP_Error('add_dispatch_interest_missing', __('No saved request was found for this date.', 'backstage-venue-manager'));
		}
		if (trim((string) ($interest['assigned_at'] ?? '')) !== '') {
			return new WP_Error('add_dispatch_interest_assigned', __('This request has already been assigned and cannot be withdrawn here.', 'backstage-venue-manager'));
		}
		$current_status = sanitize_key((string) ($interest['response_status'] ?? 'requested'));
		if ($current_status === 'unavailable') {
			return true;
		}

		$restore = vms_add_dispatch_restore_vendor_availability(
			(int) ($interest['vendor_id'] ?? 0),
			(string) ($interest['event_date'] ?? ''),
			(string) ($interest['availability_before'] ?? '')
		);
		if (is_wp_error($restore)) {
			return $restore;
		}

		$now = vms_add_dispatch_now_mysql();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Portal-interest withdrawal writes the plugin-owned response row directly so withdrawn availability state persists immediately.
		$updated = $wpdb->update(
			$responses_table,
			array(
				'response_status' => 'unavailable',
				'responded_at' => $now,
				'response_source' => 'portal_interest',
				'availability_after' => sanitize_text_field((string) ($interest['availability_before'] ?? '')),
				'updated_at' => $now,
			),
			array('id' => (int) ($interest['id'] ?? 0)),
			array('%s', '%s', '%s', '%s', '%s'),
			array('%d')
		);
		if ($updated === false) {
			return new WP_Error('add_dispatch_withdraw_failed', __('The request could not be withdrawn.', 'backstage-venue-manager'));
		}

		vms_add_dispatch_log('portal_interest_withdrawn', array(
			'request_id' => (int) ($interest['request_id'] ?? 0),
			'response_id' => (int) ($interest['id'] ?? 0),
			'vendor_id' => (int) ($interest['vendor_id'] ?? 0),
			'event_plan_id' => (int) ($interest['event_plan_id'] ?? 0),
			'event_date' => (string) ($interest['event_date'] ?? ''),
			'source' => 'portal_interest',
			'actor_user_id' => get_current_user_id(),
		));

		$request = vms_add_dispatch_get_request((int) ($interest['request_id'] ?? 0));
		$response = vms_add_dispatch_get_response((int) ($interest['id'] ?? 0));
		$context = function_exists('vms_add_dispatch_get_event_plan_context')
			? (array) vms_add_dispatch_get_event_plan_context((int) ($interest['event_plan_id'] ?? 0))
			: array();
		if (function_exists('vms_add_dispatch_send_operator_interest_withdraw_notification') && is_array($request) && is_array($response)) {
			vms_add_dispatch_send_operator_interest_withdraw_notification($request, $response, $context);
		}

		return true;
	}
}

if (!function_exists('vms_add_dispatch_assign_vendor_to_plan')) {
	function vms_add_dispatch_assign_vendor_type_label_list(array $type_slugs): string
	{
		$labels = array();
		foreach ($type_slugs as $type_slug) {
			$type_slug = function_exists('vms_vendor_type_normalize_slug')
				? (string) vms_vendor_type_normalize_slug((string) $type_slug)
				: sanitize_key((string) $type_slug);
			if ($type_slug === '') {
				continue;
			}
			$label = function_exists('vms_add_dispatch_type_label')
				? (string) vms_add_dispatch_type_label($type_slug)
				: ucwords(str_replace(array('_', '-'), ' ', $type_slug));
			if (trim($label) !== '') {
				$labels[] = $label;
			}
		}

		$labels = array_values(array_unique($labels));
		return !empty($labels) ? implode(', ', $labels) : __('No current vendor type', 'backstage-venue-manager');
	}
}

if (!function_exists('vms_add_dispatch_assignment_review')) {
	function vms_add_dispatch_assignment_review(int $response_id, string $selected_type = '') {
		$response = vms_add_dispatch_get_response($response_id);
		if (!$response) {
			return new WP_Error('add_dispatch_response_missing', __('The selected ADD response could not be found.', 'backstage-venue-manager'));
		}

		$request = vms_add_dispatch_get_request((int) ($response['request_id'] ?? 0));
		if (!$request) {
			return new WP_Error('add_dispatch_request_missing', __('The parent ADD request could not be found.', 'backstage-venue-manager'));
		}

		$event_plan_id = (int) ($request['event_plan_id'] ?? $response['event_plan_id'] ?? 0);
		$vendor_id = (int) ($response['vendor_id'] ?? 0);
		if ($event_plan_id <= 0 || get_post_type($event_plan_id) !== 'vms_event_plan') {
			return new WP_Error('add_dispatch_event_missing', __('The target Event Plan could not be found.', 'backstage-venue-manager'));
		}
		if ($vendor_id <= 0 || get_post_type($vendor_id) !== 'vms_vendor') {
			return new WP_Error('add_dispatch_vendor_missing', __('The selected vendor could not be found.', 'backstage-venue-manager'));
		}

		$target_mode = sanitize_key((string) ($request['target_mode'] ?? 'secondary'));
		$original_type = function_exists('vms_vendor_type_normalize_slug')
			? vms_vendor_type_normalize_slug((string) ($request['vendor_type'] ?? ''))
			: sanitize_key(str_replace('-', '_', (string) ($request['vendor_type'] ?? '')));
		$current_types = vms_add_dispatch_vendor_type_slugs($vendor_id);
		$current_types = array_values(array_unique(array_filter(array_map(static function ($type_slug): string {
			return function_exists('vms_vendor_type_normalize_slug')
				? (string) vms_vendor_type_normalize_slug((string) $type_slug)
				: sanitize_key((string) $type_slug);
		}, $current_types))));
		$additional_options = function_exists('vms_event_plan_additional_vendor_type_options')
			? (array) vms_event_plan_additional_vendor_type_options()
			: (function_exists('vms_add_dispatch_type_options') ? (array) vms_add_dispatch_type_options() : array());

		$primary_vendor_id = (int) get_post_meta($event_plan_id, vms_add_dispatch_primary_vendor_key(), true);
		$assignments = function_exists('vms_event_plan_get_secondary_vendor_assignments')
			? (array) vms_event_plan_get_secondary_vendor_assignments($event_plan_id, array('primary_vendor_id' => $primary_vendor_id))
			: array();
		$group_rows = function_exists('vms_add_dispatch_secondary_group_rows')
			? vms_add_dispatch_secondary_group_rows($assignments)
			: array();

		$eligible_types = array();
		foreach ($current_types as $type_slug) {
			if ($type_slug !== '' && isset($additional_options[$type_slug])) {
				$eligible_types[] = $type_slug;
			}
		}
		$eligible_types = array_values(array_unique($eligible_types));

		$default_type = '';
		foreach ($eligible_types as $type_slug) {
			if ($type_slug !== $original_type) {
				$default_type = $type_slug;
				break;
			}
		}
		if ($default_type === '' && in_array($original_type, $eligible_types, true)) {
			$default_type = $original_type;
		}
		if ($default_type === '' && !empty($eligible_types)) {
			$default_type = (string) $eligible_types[0];
		}
		$selected_type = function_exists('vms_vendor_type_normalize_slug')
			? (string) vms_vendor_type_normalize_slug($selected_type)
			: sanitize_key($selected_type);
		if ($selected_type === '' || !in_array($selected_type, $eligible_types, true)) {
			$selected_type = $default_type;
		}

		$targets = array();
		foreach ($eligible_types as $type_slug) {
			$assignment = isset($assignments[$type_slug]) && is_array($assignments[$type_slug])
				? (array) $assignments[$type_slug]
				: array();
			$exists = !empty($assignment);
			$mode = sanitize_key((string) ($assignment['mode'] ?? ''));
			if (!in_array($mode, array('standard', 'market'), true)) {
				$mode = function_exists('vms_event_plan_secondary_vendor_default_mode')
					? (string) vms_event_plan_secondary_vendor_default_mode($type_slug)
					: 'standard';
			}
			$slot_limit = function_exists('vms_event_plan_secondary_vendor_effective_slot_limit')
				? vms_event_plan_secondary_vendor_effective_slot_limit($type_slug, $mode, $assignment['slot_limit'] ?? null)
				: ($assignment['slot_limit'] ?? null);
			$vendor_ids = array_values(array_unique(array_filter(array_map('absint', (array) ($assignment['vendor_ids'] ?? array())))));
			$filled = count($vendor_ids);
			$allow_over_capacity = function_exists('vms_event_plan_parse_secondary_vendor_over_capacity_override')
				? vms_event_plan_parse_secondary_vendor_over_capacity_override($assignment['allow_over_capacity'] ?? false)
				: !empty($assignment['allow_over_capacity']);
			$warnings = array();
			if ($primary_vendor_id > 0 && $primary_vendor_id === $vendor_id) {
				$warnings[] = __('This vendor is already the Primary Vendor and cannot also be assigned as an Additional Vendor.', 'backstage-venue-manager');
			}
			if (in_array($vendor_id, $vendor_ids, true)) {
				$warnings[] = __('This vendor is already in this Additional Vendor group.', 'backstage-venue-manager');
			}
			if ($slot_limit !== null && $filled >= (int) $slot_limit && !in_array($vendor_id, $vendor_ids, true)) {
				$warnings[] = $allow_over_capacity
					? __('This group is full, but over-capacity is already allowed for the group.', 'backstage-venue-manager')
					: __('This group is full. Confirming this assignment requires the over-capacity override.', 'backstage-venue-manager');
			}
			if ($slot_limit !== null && $filled > (int) $slot_limit) {
				/* translators: %d: number used in this message. */
				$warnings[] = sprintf(__('This group is already over capacity by %d.', 'backstage-venue-manager'), $filled - (int) $slot_limit);
			}

			$targets[$type_slug] = array(
				'type_slug' => $type_slug,
				'label' => function_exists('vms_add_dispatch_type_label') ? (string) vms_add_dispatch_type_label($type_slug) : (string) ($additional_options[$type_slug] ?? $type_slug),
				'exists' => $exists,
				'mode' => $mode,
				'slot_limit' => $slot_limit,
				'filled' => $filled,
				/* translators: 1: number 1 used in this message, 2: number 2 used in this message. */
				'capacity_label' => $slot_limit === null ? __('No slot limit', 'backstage-venue-manager') : sprintf(__('%1$d of %2$d filled', 'backstage-venue-manager'), $filled, (int) $slot_limit),
				'allow_over_capacity' => $allow_over_capacity,
				'is_full' => $slot_limit !== null && $filled >= (int) $slot_limit,
				'is_duplicate' => in_array($vendor_id, $vendor_ids, true),
				'is_primary_conflict' => $primary_vendor_id > 0 && $primary_vendor_id === $vendor_id,
				'warnings' => $warnings,
			);
		}

		$warnings = array();
		if ($target_mode !== 'primary' && $original_type !== '' && !in_array($original_type, $current_types, true) && !empty($current_types)) {
			$warnings[] = sprintf(
				/* translators: 1: value 1 used in this message, 2: value 2 used in this message. */
				__('This vendor originally responded as %1$s, but their current vendor type is %2$s.', 'backstage-venue-manager'),
				vms_add_dispatch_type_label($original_type),
				vms_add_dispatch_assign_vendor_type_label_list($current_types)
			);
		}
		if (trim((string) ($response['assigned_at'] ?? '')) !== '') {
			$warnings[] = __('This ADD response is already marked assigned.', 'backstage-venue-manager');
		}

		return array(
			'response' => $response,
			'request' => $request,
			'event_plan_id' => $event_plan_id,
			'event_title' => (string) get_the_title($event_plan_id),
			'event_date' => (string) ($request['event_date'] ?? get_post_meta($event_plan_id, '_vms_event_date', true)),
			'vendor_id' => $vendor_id,
			'vendor_title' => (string) get_the_title($vendor_id),
			'target_mode' => $target_mode,
			'original_type' => $original_type,
			'original_type_label' => $target_mode === 'primary' ? __('Primary Vendor', 'backstage-venue-manager') : vms_add_dispatch_type_label($original_type),
			'current_types' => $current_types,
			'current_type_labels' => vms_add_dispatch_assign_vendor_type_label_list($current_types),
			'assignments' => $assignments,
			'group_rows' => $group_rows,
			'eligible_types' => $eligible_types,
			'targets' => $targets,
			'default_type' => $default_type,
			'selected_type' => $selected_type,
			'warnings' => $warnings,
		);
	}
}

if (!function_exists('vms_add_dispatch_apply_assignment_review')) {
	function vms_add_dispatch_apply_assignment_review(int $response_id, string $target_type = '', bool $allow_over_capacity = false)
	{
		$raw_target_type = trim((string) $target_type);
		$review = vms_add_dispatch_assignment_review($response_id, $target_type);
		if (is_wp_error($review)) {
			return $review;
		}
		$response = (array) ($review['response'] ?? array());
		$request = (array) ($review['request'] ?? array());

		if (sanitize_key((string) ($response['response_status'] ?? '')) !== 'available') {
			return new WP_Error('add_dispatch_vendor_not_available', __('Only vendors who responded Available can be assigned from ADD.', 'backstage-venue-manager'));
		}
		if (trim((string) ($response['assigned_at'] ?? '')) !== '') {
			return new WP_Error('add_dispatch_already_assigned', __('This ADD response is already marked assigned.', 'backstage-venue-manager'));
		}

		$event_plan_id = (int) ($review['event_plan_id'] ?? 0);
		$vendor_id = (int) ($review['vendor_id'] ?? 0);
		$target_mode = sanitize_key((string) ($review['target_mode'] ?? 'secondary'));
		$vendor_type = function_exists('vms_vendor_type_normalize_slug')
			? vms_vendor_type_normalize_slug($target_type)
			: sanitize_key($target_type);

		if ($event_plan_id <= 0 || get_post_type($event_plan_id) !== 'vms_event_plan') {
			return new WP_Error('add_dispatch_event_missing', __('The target Event Plan could not be found.', 'backstage-venue-manager'));
		}
		if ($vendor_id <= 0 || get_post_type($vendor_id) !== 'vms_vendor') {
			return new WP_Error('add_dispatch_vendor_missing', __('The selected vendor could not be found.', 'backstage-venue-manager'));
		}

		if ($target_mode === 'primary') {
			$primary_vendor_key = vms_add_dispatch_primary_vendor_key();
			$current_primary = (int) get_post_meta($event_plan_id, $primary_vendor_key, true);
			if ($current_primary > 0 && $current_primary !== $vendor_id) {
				return new WP_Error('add_dispatch_primary_already_set', __('This Event Plan already has a primary vendor. Clear or replace it in the Event Plan editor before using ADD assignment.', 'backstage-venue-manager'));
			}

			update_post_meta($event_plan_id, $primary_vendor_key, $vendor_id);
			if (function_exists('vms_event_plan_clear_integrity_flags')) {
				vms_event_plan_clear_integrity_flags($event_plan_id);
			}
		} else {
			if ($raw_target_type === '') {
				return new WP_Error('add_dispatch_assignment_target_required', __('Review the ADD response and choose an Assign as target before confirming this assignment.', 'backstage-venue-manager'));
			}
			$targets = (array) ($review['targets'] ?? array());
			if ($vendor_type === '' || !isset($targets[$vendor_type])) {
				return new WP_Error('add_dispatch_invalid_target_type', __('Choose a current vendor type before assigning this ADD response.', 'backstage-venue-manager'));
			}
			$target = (array) $targets[$vendor_type];
			if (!empty($target['is_primary_conflict'])) {
				return new WP_Error('add_dispatch_primary_secondary_conflict', __('This vendor is already the Primary Vendor and cannot also be assigned as an Additional Vendor.', 'backstage-venue-manager'));
			}
			if (!empty($target['is_duplicate'])) {
				return new WP_Error('add_dispatch_duplicate_secondary_vendor', __('This vendor is already assigned to that Additional Vendor group.', 'backstage-venue-manager'));
			}
			if (!empty($target['is_full']) && empty($target['allow_over_capacity']) && !$allow_over_capacity) {
				return new WP_Error('vms_secondary_vendor_over_capacity', __('This Additional Vendor group is full. Check the over-capacity override to confirm this assignment.', 'backstage-venue-manager'));
			}

			$current_assignments = (array) ($review['assignments'] ?? array());
			$current_assignment = isset($current_assignments[$vendor_type]) && is_array($current_assignments[$vendor_type])
				? (array) $current_assignments[$vendor_type]
				: array();
			$mode = sanitize_key((string) ($current_assignment['mode'] ?? ''));
			if (!in_array($mode, array('standard', 'market'), true)) {
				$mode = function_exists('vms_event_plan_secondary_vendor_default_mode')
					? (string) vms_event_plan_secondary_vendor_default_mode($vendor_type)
					: 'standard';
			}
			$current_assignment['mode'] = $mode;
			if (!array_key_exists('slot_limit', $current_assignment)) {
				$current_assignment['slot_limit'] = function_exists('vms_event_plan_secondary_vendor_default_slot_limit')
					? vms_event_plan_secondary_vendor_default_slot_limit($vendor_type, $mode)
					: 1;
			}
			$current_ids = array_values(array_unique(array_filter(array_map('absint', (array) ($current_assignment['vendor_ids'] ?? array())))));
			$current_ids[] = $vendor_id;
			$current_assignment['vendor_ids'] = array_values(array_unique(array_filter(array_map('absint', $current_ids))));
			if ($allow_over_capacity || !empty($current_assignment['allow_over_capacity'])) {
				$current_assignment['allow_over_capacity'] = true;
			}
			$current_assignments[$vendor_type] = $current_assignment;

			$assignment = function_exists('vms_event_plan_write_secondary_vendor_assignments')
				? vms_event_plan_write_secondary_vendor_assignments($event_plan_id, $current_assignments)
				: new WP_Error('add_dispatch_secondary_writer_missing', __('The Additional Vendors assignment writer is not available.', 'backstage-venue-manager'));
			if (is_wp_error($assignment)) {
				return $assignment;
			}
		}

		global $wpdb;
		$responses_table = vms_add_dispatch_table_name('responses');
		$now = vms_add_dispatch_now_mysql();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- ADD assignment finalization writes the plugin-owned response row directly so assigned markers persist immediately after plan writes.
		$updated = $wpdb->update(
			$responses_table,
			array(
				'assigned_at' => $now,
				'assigned_by' => get_current_user_id(),
				'updated_at' => $now,
			),
			array('id' => $response_id),
			array('%s', '%d', '%s'),
			array('%d')
		);
		if ($updated === false) {
			return new WP_Error('add_dispatch_assign_update_failed', __('The ADD assignment marker could not be saved.', 'backstage-venue-manager'));
		}

		vms_add_dispatch_log('assignment_applied', array(
			'request_id' => (int) ($request['id'] ?? 0),
			'response_id' => $response_id,
			'vendor_id' => $vendor_id,
			'event_plan_id' => $event_plan_id,
			'event_date' => (string) ($request['event_date'] ?? ''),
			'source' => 'admin',
			'actor_user_id' => get_current_user_id(),
			'details' => array(
				'target_mode' => $target_mode,
				'vendor_type' => $vendor_type,
				'original_vendor_type' => (string) ($review['original_type'] ?? ''),
				'current_vendor_types' => (array) ($review['current_types'] ?? array()),
			),
		));

		return true;
	}
}

if (!function_exists('vms_add_dispatch_assign_vendor_to_plan')) {
	function vms_add_dispatch_assign_vendor_to_plan(int $response_id, string $target_type = '', bool $allow_over_capacity = false)
	{
		return vms_add_dispatch_apply_assignment_review($response_id, $target_type, $allow_over_capacity);
	}
}


if (!function_exists('vms_add_dispatch_get_recent_responses')) {
	function vms_add_dispatch_get_recent_responses(int $limit = 12): array
	{
		global $wpdb;
		$responses = vms_add_dispatch_table_name('responses');
		$requests = vms_add_dispatch_table_name('requests');
		if ($responses === '' || $requests === '') {
			return array();
		}

		$limit = max(1, min(100, $limit));
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- ADD recent-response reads join the plugin-owned request/response tables with %i-prepared identifiers so dashboard history stays request-fresh after writes.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT resp.*, req.vendor_type, req.target_mode, req.status AS request_status
				 FROM %i AS resp
				 LEFT JOIN %i AS req ON req.id = resp.request_id
				 ORDER BY COALESCE(resp.responded_at, resp.created_at) DESC, resp.created_at DESC
				 LIMIT %d",
				$responses,
				$requests,
				$limit
			),
			ARRAY_A
		);
		if (!is_array($rows)) {
			return array();
		}

		foreach ($rows as &$row) {
			$row['vendor_title'] = (string) get_the_title((int) ($row['vendor_id'] ?? 0));
			$row['event_title'] = (string) get_the_title((int) ($row['event_plan_id'] ?? 0));
		}
		unset($row);

		return $rows;
	}
}


if (!function_exists('vms_add_dispatch_get_pending_portal_interest_rows')) {
	function vms_add_dispatch_get_pending_portal_interest_rows(int $limit = 20): array
	{
		global $wpdb;
		$requests = vms_add_dispatch_table_name('requests');
		$responses = vms_add_dispatch_table_name('responses');
		if ($requests === '' || $responses === '') {
			return array();
		}

		$limit = max(1, min(100, $limit));
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Pending portal-interest reads join the plugin-owned request/response tables with %i-prepared identifiers so assignment review starts from fresh custom-table state.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT resp.*, req.status AS request_status, req.vendor_type, req.target_mode
				 FROM %i AS resp
				 INNER JOIN %i AS req ON req.id = resp.request_id
				 WHERE resp.response_source = %s
				   AND resp.response_status = %s
				   AND resp.assigned_at IS NULL
				 ORDER BY COALESCE(resp.responded_at, resp.created_at) DESC, resp.created_at DESC
				 LIMIT %d",
				$responses,
				$requests,
				'portal_interest',
				'available',
				$limit
			),
			ARRAY_A
		);
		if (!is_array($rows)) {
			return array();
		}

		foreach ($rows as &$row) {
			$row['vendor_title'] = (string) get_the_title((int) ($row['vendor_id'] ?? 0));
			$row['event_title'] = (string) get_the_title((int) ($row['event_plan_id'] ?? 0));
		}
		unset($row);

		return $rows;
	}
}

if (!function_exists('vms_add_dispatch_get_dashboard_counts')) {
	function vms_add_dispatch_get_dashboard_counts(): array
	{
		$recent_requests = vms_add_dispatch_get_recent_requests(50);
		$recent_responses = vms_add_dispatch_get_recent_responses(50);
		$counts = array(
			'active_requests' => 0,
			'pending_recipients' => 0,
			'available_responses' => 0,
			'closed_requests' => 0,
		);

		foreach ($recent_requests as $request) {
			$status = sanitize_key((string) ($request['status'] ?? 'active'));
			if ($status === 'active') {
				$counts['active_requests']++;
				$counts['pending_recipients'] += (int) ($request['requested_count'] ?? 0);
			} elseif ($status === 'closed') {
				$counts['closed_requests']++;
			}
		}

		foreach ($recent_responses as $response) {
			if (sanitize_key((string) ($response['response_status'] ?? '')) === 'available') {
				$counts['available_responses']++;
			}
		}

		return $counts;
	}
}

if (!function_exists('vms_add_dispatch_get_open_event_plan_candidates')) {
	function vms_add_dispatch_get_open_event_plan_candidates(int $limit = 12): array
	{
		$scan = function_exists('vms_add_dispatch_get_event_plan_need_scan')
			? vms_add_dispatch_get_event_plan_need_scan($limit, 0)
			: array('contexts' => array());

		return (array) ($scan['contexts'] ?? array());
	}
}
