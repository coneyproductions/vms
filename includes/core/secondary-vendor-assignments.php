<?php
defined('ABSPATH') || exit;

if (!function_exists('bvmgr_event_plan_secondary_vendor_assignment_meta_key')) {
	function bvmgr_event_plan_secondary_vendor_assignment_meta_key(): string
	{
		if (function_exists('bvmgr_meta_key')) {
			$key = (string) bvmgr_meta_key('event_plan', 'secondary_vendor_assignments_v1');
			if ($key !== '') {
				return $key;
			}
		}

		return '_vms_secondary_vendor_assignments_v1';
	}
}

if (!function_exists('bvmgr_event_plan_normalize_secondary_vendor_type_slug')) {
	function bvmgr_event_plan_normalize_secondary_vendor_type_slug(string $type_slug): string
	{
		return function_exists('vms_vendor_type_normalize_slug')
			? (string) vms_vendor_type_normalize_slug($type_slug)
			: sanitize_title($type_slug);
	}
}

if (!function_exists('bvmgr_event_plan_secondary_vendor_type_options')) {
	function bvmgr_event_plan_secondary_vendor_type_options(): array
	{
		$options = array();

		if (function_exists('vms_vendor_type_select_options')) {
			foreach ((array) vms_vendor_type_select_options() as $slug => $label) {
				$slug = bvmgr_event_plan_normalize_secondary_vendor_type_slug((string) $slug);
				$label = trim((string) $label);
				if ($slug === '' || $label === '') {
					continue;
				}
				$options[$slug] = $label;
			}
		}

		if (empty($options)) {
			$options = array(
				'band' => __('Music Vendor', 'backstage-venue-manager'),
				'food_truck' => __('Food Vendor', 'backstage-venue-manager'),
				'dessert_truck' => __('Dessert Vendor', 'backstage-venue-manager'),
				'drink_truck' => __('Drink Vendor', 'backstage-venue-manager'),
				'photographer' => __('Photographer', 'backstage-venue-manager'),
				'market_vendor' => __('Market Vendor', 'backstage-venue-manager'),
			);
		}

		return (array) apply_filters('vms_event_plan_secondary_vendor_type_options', $options);
	}
}

if (!function_exists('bvmgr_event_plan_additional_vendor_type_options')) {
	function bvmgr_event_plan_additional_vendor_type_options(): array
	{
		$options = bvmgr_event_plan_secondary_vendor_type_options();
		unset($options['band']);
		return $options;
	}
}

if (!function_exists('bvmgr_event_plan_secondary_vendor_mode_options')) {
	function bvmgr_event_plan_secondary_vendor_mode_options(): array
	{
		return array(
			'standard' => __('Standard', 'backstage-venue-manager'),
			'market' => __('Market', 'backstage-venue-manager'),
		);
	}
}

if (!function_exists('bvmgr_event_plan_secondary_vendor_default_mode')) {
	function bvmgr_event_plan_secondary_vendor_default_mode(string $type_slug): string
	{
		$type_slug = bvmgr_event_plan_normalize_secondary_vendor_type_slug($type_slug);
		return $type_slug === 'market_vendor' ? 'market' : 'standard';
	}
}

if (!function_exists('bvmgr_event_plan_secondary_vendor_default_slot_limit')) {
	function bvmgr_event_plan_secondary_vendor_default_slot_limit(string $type_slug, string $mode = 'standard')
	{
		$type_slug = bvmgr_event_plan_normalize_secondary_vendor_type_slug($type_slug);
		$mode = sanitize_key($mode);

		if ($mode === 'market' && $type_slug === 'market_vendor') {
			return null;
		}

		$defaults = array(
			'band' => 1,
			'food_truck' => 1,
			'dessert_truck' => 1,
			'drink_truck' => 1,
			'photographer' => 1,
			'market_vendor' => null,
		);

		return array_key_exists($type_slug, $defaults) ? $defaults[$type_slug] : 1;
	}
}

if (!function_exists('bvmgr_event_plan_parse_secondary_vendor_over_capacity_override')) {
	function bvmgr_event_plan_parse_secondary_vendor_over_capacity_override($value): bool
	{
		if (is_bool($value)) {
			return $value;
		}
		if (is_numeric($value)) {
			return (int) $value === 1;
		}

		$value = strtolower(trim((string) $value));
		return in_array($value, array('1', 'true', 'yes', 'on'), true);
	}
}

if (!function_exists('bvmgr_event_plan_secondary_vendor_effective_slot_limit')) {
	function bvmgr_event_plan_secondary_vendor_effective_slot_limit(string $type_slug, string $mode, $slot_limit): ?int
	{
		$type_slug = bvmgr_event_plan_normalize_secondary_vendor_type_slug($type_slug);
		$mode = sanitize_key($mode);
		if (!in_array($mode, array('standard', 'market'), true)) {
			$mode = bvmgr_event_plan_secondary_vendor_default_mode($type_slug);
		}

		if ($slot_limit !== null && $slot_limit !== '') {
			return max(0, (int) $slot_limit);
		}

		$default = bvmgr_event_plan_secondary_vendor_default_slot_limit($type_slug, $mode);
		if ($default === null || $default === '') {
			return null;
		}

		return max(0, (int) $default);
	}
}

if (!function_exists('bvmgr_event_plan_secondary_vendor_compare_value')) {
	function bvmgr_event_plan_secondary_vendor_compare_value($value)
	{
		if (!is_array($value)) {
			return $value;
		}

		$is_list = array_keys($value) === range(0, count($value) - 1);
		$normalized = array();
		foreach ($value as $key => $child) {
			$normalized[$key] = bvmgr_event_plan_secondary_vendor_compare_value($child);
		}

		if ($is_list) {
			usort($normalized, static function ($left, $right): int {
				return strcmp((string) wp_json_encode($left), (string) wp_json_encode($right));
			});
		} else {
			ksort($normalized);
		}

		return $normalized;
	}
}

if (!function_exists('bvmgr_event_plan_secondary_vendor_terms_for_vendor')) {
	function bvmgr_event_plan_secondary_vendor_terms_for_vendor(int $vendor_id): array
	{
		$vendor_id = absint($vendor_id);
		if ($vendor_id <= 0 || !taxonomy_exists('vms_vendor_type')) {
			return array();
		}

		$terms = get_the_terms($vendor_id, 'vms_vendor_type');
		if (!is_array($terms) || is_wp_error($terms)) {
			return array();
		}

		$slugs = array();
		foreach ($terms as $term) {
			if (!$term instanceof WP_Term) {
				continue;
			}

			$slugs[] = bvmgr_event_plan_normalize_secondary_vendor_type_slug((string) $term->slug);
		}

		return array_values(array_unique(array_filter($slugs)));
	}
}

if (!function_exists('bvmgr_event_plan_normalize_secondary_vendor_ids')) {
	function bvmgr_event_plan_normalize_secondary_vendor_ids(int $post_id, string $type_slug, array $secondary_ids, int $primary_vendor_id = 0): array
	{
		$post_id = absint($post_id);
		$type_slug = bvmgr_event_plan_normalize_secondary_vendor_type_slug($type_slug);
		$primary_vendor_id = absint($primary_vendor_id);

		$secondary_ids = array_values(array_unique(array_filter(array_map('absint', $secondary_ids), static function ($vendor_id): bool {
			return $vendor_id > 0;
		})));

		if ($primary_vendor_id > 0) {
			$secondary_ids = array_values(array_filter($secondary_ids, static function ($vendor_id) use ($primary_vendor_id): bool {
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
				$matches_type = function_exists('vms_vendor_has_type')
					? vms_vendor_has_type($vendor_id, $type_slug)
					: (function_exists('has_term') ? has_term($type_slug, 'vms_vendor_type', $vendor_id) : true);
				if (!$matches_type) {
					continue;
				}
			}

			$valid_secondary[] = $vendor_id;
		}

		return array_values(array_unique(array_filter(array_map('absint', $valid_secondary), static function ($vendor_id): bool {
			return $vendor_id > 0;
		})));
	}
}

if (!function_exists('bvmgr_event_plan_normalize_secondary_vendor_assignment_map')) {
	function bvmgr_event_plan_normalize_secondary_vendor_assignment_map(int $post_id, array $assignments, int $primary_vendor_id = 0, array $args = array()): array
	{
		$post_id = absint($post_id);
		$primary_vendor_id = absint($primary_vendor_id);
		$preserve_empty = array_key_exists('preserve_empty', $args) ? !empty($args['preserve_empty']) : true;
		$normalized = array();

		foreach ($assignments as $raw_key => $raw_row) {
			$row = is_array($raw_row) ? $raw_row : array();
			$candidate_type = '';
			foreach (array('type_slug', 'vendor_type', 'type', 'slug') as $field) {
				if (!array_key_exists($field, $row)) {
					continue;
				}

				$candidate_type = (string) $row[$field];
				if (trim($candidate_type) !== '') {
					break;
				}
			}

			if ($candidate_type === '' && is_string($raw_key)) {
				$candidate_type = $raw_key;
			}

			$type_slug = bvmgr_event_plan_normalize_secondary_vendor_type_slug($candidate_type);
			if ($type_slug === '') {
				continue;
			}

			$mode = sanitize_key((string) ($row['mode'] ?? ''));
			if (!in_array($mode, array('standard', 'market'), true)) {
				$mode = bvmgr_event_plan_secondary_vendor_default_mode($type_slug);
			}

			$slot_limit = null;
			$slot_limit_present = false;
			if (array_key_exists('slot_limit', $row)) {
				$slot_limit_present = true;
				if ($row['slot_limit'] === '' || $row['slot_limit'] === null) {
					$slot_limit = bvmgr_event_plan_secondary_vendor_default_slot_limit($type_slug, $mode);
				} else {
					$slot_limit = max(0, (int) $row['slot_limit']);
				}
			} elseif (array_key_exists('capacity', $row)) {
				$slot_limit_present = true;
				if ($row['capacity'] === '' || $row['capacity'] === null) {
					$slot_limit = bvmgr_event_plan_secondary_vendor_default_slot_limit($type_slug, $mode);
				} else {
					$slot_limit = max(0, (int) $row['capacity']);
				}
			} else {
				$slot_limit = bvmgr_event_plan_secondary_vendor_default_slot_limit($type_slug, $mode);
			}

			$allow_over_capacity = false;
			foreach (array('allow_over_capacity', 'over_capacity_override', 'capacity_override') as $override_key) {
				if (array_key_exists($override_key, $row) && bvmgr_event_plan_parse_secondary_vendor_over_capacity_override($row[$override_key])) {
					$allow_over_capacity = true;
					break;
				}
			}

			$target_slots = null;
			foreach (array('needed_slots', 'target_slots', 'needed', 'target') as $target_key) {
				if (!array_key_exists($target_key, $row)) {
					continue;
				}
				if ($row[$target_key] !== '' && $row[$target_key] !== null) {
					$target_slots = max(0, (int) $row[$target_key]);
				}
				break;
			}

			$open_for_dispatch_present = false;
			$open_for_dispatch = true;
			if (array_key_exists('open_for_dispatch', $row)) {
				$open_for_dispatch_present = true;
				$open_for_dispatch = bvmgr_event_plan_parse_secondary_vendor_over_capacity_override($row['open_for_dispatch']);
			}

			$raw_vendor_ids = $row['vendor_ids'] ?? ($row['secondary_ids'] ?? array());
			if (!is_array($raw_vendor_ids)) {
				$raw_vendor_ids = $raw_vendor_ids !== '' && $raw_vendor_ids !== null ? array($raw_vendor_ids) : array();
			}

			$vendor_ids = bvmgr_event_plan_normalize_secondary_vendor_ids($post_id, $type_slug, $raw_vendor_ids, $primary_vendor_id);
			$should_keep = $preserve_empty || !empty($vendor_ids) || $slot_limit !== null || $mode === 'market';

			if (isset($normalized[$type_slug])) {
				$existing = $normalized[$type_slug];
				$existing['vendor_ids'] = array_values(array_unique(array_merge(
					array_map('absint', (array) ($existing['vendor_ids'] ?? array())),
					array_map('absint', $vendor_ids)
				)));
				$existing['mode'] = $mode !== '' ? $mode : (string) ($existing['mode'] ?? bvmgr_event_plan_secondary_vendor_default_mode($type_slug));
				if ($slot_limit_present || !array_key_exists('slot_limit', $existing)) {
					$existing['slot_limit'] = $slot_limit;
				} elseif (($existing['slot_limit'] ?? null) === null && $slot_limit !== null) {
					$existing['slot_limit'] = $slot_limit;
				}
				if ($allow_over_capacity || !empty($existing['allow_over_capacity'])) {
					$existing['allow_over_capacity'] = true;
				} else {
					unset($existing['allow_over_capacity']);
				}
				if ($target_slots !== null || !array_key_exists('needed_slots', $existing)) {
					if ($target_slots !== null) {
						$existing['needed_slots'] = $target_slots;
					} else {
						unset($existing['needed_slots']);
					}
				}
				if ($open_for_dispatch_present) {
					$existing['open_for_dispatch'] = $open_for_dispatch;
				}
				$normalized[$type_slug] = $existing;
				continue;
			}

			if (!$should_keep) {
				continue;
			}

			$normalized[$type_slug] = array(
				'mode' => $mode,
				'slot_limit' => $slot_limit,
				'vendor_ids' => $vendor_ids,
			);
			if ($allow_over_capacity) {
				$normalized[$type_slug]['allow_over_capacity'] = true;
			}
			if ($target_slots !== null) {
				$normalized[$type_slug]['needed_slots'] = $target_slots;
			}
			if ($open_for_dispatch_present) {
				$normalized[$type_slug]['open_for_dispatch'] = $open_for_dispatch;
			}
		}

		return $normalized;
	}
}

if (!function_exists('bvmgr_event_plan_secondary_vendor_over_capacity_rows')) {
	function bvmgr_event_plan_secondary_vendor_over_capacity_rows(array $assignments): array
	{
		$rows = array();
		$type_options = function_exists('bvmgr_event_plan_secondary_vendor_type_options')
			? (array) bvmgr_event_plan_secondary_vendor_type_options()
			: array();

		foreach ($assignments as $type_slug => $assignment) {
			$type_slug = bvmgr_event_plan_normalize_secondary_vendor_type_slug((string) $type_slug);
			if ($type_slug === '' || !is_array($assignment)) {
				continue;
			}

			$mode = sanitize_key((string) ($assignment['mode'] ?? ''));
			if (!in_array($mode, array('standard', 'market'), true)) {
				$mode = bvmgr_event_plan_secondary_vendor_default_mode($type_slug);
			}

			$slot_limit = bvmgr_event_plan_secondary_vendor_effective_slot_limit($type_slug, $mode, $assignment['slot_limit'] ?? null);
			if ($slot_limit === null) {
				continue;
			}

			$vendor_ids = array_values(array_unique(array_filter(array_map('absint', (array) ($assignment['vendor_ids'] ?? array())), static function ($vendor_id): bool {
				return $vendor_id > 0;
			})));
			$filled = count($vendor_ids);
			$allow_over_capacity = bvmgr_event_plan_parse_secondary_vendor_over_capacity_override($assignment['allow_over_capacity'] ?? false);
			if ($filled <= $slot_limit || $allow_over_capacity) {
				continue;
			}

			$label = trim((string) ($type_options[$type_slug] ?? ''));
			if ($label === '' && function_exists('vms_vendor_type_label')) {
				$label = trim((string) vms_vendor_type_label($type_slug));
			}
			if ($label === '') {
				$label = ucwords(str_replace(array('_', '-'), ' ', $type_slug));
			}

			$rows[] = array(
				'type_slug' => $type_slug,
				'label' => $label,
				'mode' => $mode,
				'filled' => $filled,
				'slot_limit' => $slot_limit,
				'over_by' => $filled - $slot_limit,
				'vendor_ids' => $vendor_ids,
			);
		}

		return $rows;
	}
}

if (!function_exists('bvmgr_event_plan_validate_secondary_vendor_assignment_capacity')) {
	function bvmgr_event_plan_validate_secondary_vendor_assignment_capacity(array $assignments)
	{
		$over_capacity_rows = bvmgr_event_plan_secondary_vendor_over_capacity_rows($assignments);
		if (empty($over_capacity_rows)) {
			return true;
		}

		$labels = array();
		foreach ($over_capacity_rows as $row) {
			$labels[] = sprintf(
				/* translators: 1: vendor type label, 2: filled count, 3: capacity */
				__('%1$s (%2$d of %3$d filled)', 'backstage-venue-manager'),
				(string) ($row['label'] ?? __('Additional Vendor', 'backstage-venue-manager')),
				(int) ($row['filled'] ?? 0),
				(int) ($row['slot_limit'] ?? 0)
			);
		}

		return new WP_Error(
			'vms_secondary_vendor_over_capacity',
			sprintf(
				/* translators: %s: comma-separated over-capacity groups */
				__('Additional Vendors cannot be saved over capacity unless you check the over-capacity override for each affected group: %s.', 'backstage-venue-manager'),
				implode(', ', $labels)
			),
			array('over_capacity_groups' => $over_capacity_rows)
		);
	}
}

if (!function_exists('bvmgr_event_plan_get_secondary_vendor_flat_ids_from_assignments')) {
	function bvmgr_event_plan_get_secondary_vendor_flat_ids_from_assignments(array $assignments, int $primary_vendor_id = 0): array
	{
		$primary_vendor_id = absint($primary_vendor_id);
		$secondary_ids = array();

		foreach ($assignments as $assignment) {
			if (!is_array($assignment)) {
				continue;
			}

			$secondary_ids = array_merge($secondary_ids, array_map('absint', (array) ($assignment['vendor_ids'] ?? array())));
		}

		$secondary_ids = array_values(array_unique(array_filter($secondary_ids, static function ($vendor_id) use ($primary_vendor_id): bool {
			$vendor_id = absint($vendor_id);
			if ($vendor_id <= 0) {
				return false;
			}

			return $primary_vendor_id <= 0 || $vendor_id !== $primary_vendor_id;
		})));

		return $secondary_ids;
	}
}

if (!function_exists('bvmgr_event_plan_get_secondary_vendor_ids_for_type')) {
	function bvmgr_event_plan_get_secondary_vendor_ids_for_type($source, string $type_slug): array
	{
		$type_slug = bvmgr_event_plan_normalize_secondary_vendor_type_slug($type_slug);
		if ($type_slug === '') {
			return array();
		}

		$assignments = is_numeric($source)
			? bvmgr_event_plan_get_secondary_vendor_assignments((int) $source)
			: (is_array($source) ? $source : array());

		$assignment = $assignments[$type_slug] ?? array();
		return array_values(array_unique(array_filter(array_map('absint', (array) ($assignment['vendor_ids'] ?? array())))));
	}
}

if (!function_exists('bvmgr_event_plan_legacy_secondary_vendor_type_from_assignments')) {
	function bvmgr_event_plan_legacy_secondary_vendor_type_from_assignments(array $assignments): string
	{
		$first = '';
		foreach ($assignments as $type_slug => $assignment) {
			unset($assignment);
			$type_slug = bvmgr_event_plan_normalize_secondary_vendor_type_slug((string) $type_slug);
			if ($type_slug === '') {
				continue;
			}

			if ($first === '') {
				$first = $type_slug;
			}

			if ($type_slug !== 'band') {
				return $type_slug;
			}
		}

		return $first;
	}
}

if (!function_exists('bvmgr_event_plan_guess_secondary_vendor_assignments_from_flat_ids')) {
	function bvmgr_event_plan_guess_secondary_vendor_assignments_from_flat_ids(int $post_id, array $secondary_ids, int $primary_vendor_id = 0): array
	{
		$post_id = absint($post_id);
		$primary_vendor_id = absint($primary_vendor_id);
		$guessed = array();

		foreach (array_values(array_unique(array_filter(array_map('absint', $secondary_ids)))) as $vendor_id) {
			if ($vendor_id <= 0 || $vendor_id === $primary_vendor_id) {
				continue;
			}

			$type_slugs = bvmgr_event_plan_secondary_vendor_terms_for_vendor($vendor_id);
			$type_slug = '';
			foreach ($type_slugs as $candidate) {
				if ($candidate !== 'band') {
					$type_slug = $candidate;
					break;
				}
				if ($type_slug === '') {
					$type_slug = $candidate;
				}
			}

			if ($type_slug === '') {
				continue;
			}

			if (!isset($guessed[$type_slug])) {
				$guessed[$type_slug] = array(
					'mode' => bvmgr_event_plan_secondary_vendor_default_mode($type_slug),
					'slot_limit' => null,
					'vendor_ids' => array(),
				);
			}

			$guessed[$type_slug]['vendor_ids'][] = $vendor_id;
		}

		return bvmgr_event_plan_normalize_secondary_vendor_assignment_map($post_id, $guessed, $primary_vendor_id, array(
			'preserve_empty' => true,
		));
	}
}

if (!function_exists('bvmgr_event_plan_read_legacy_secondary_vendor_assignments')) {
	function bvmgr_event_plan_read_legacy_secondary_vendor_assignments(int $post_id, int $primary_vendor_id = 0): array
	{
		$post_id = absint($post_id);
		$primary_vendor_id = absint($primary_vendor_id);
		$k_secondary_ids = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'secondary_vendor_ids') ?: '_vms_secondary_vendor_ids') : '_vms_secondary_vendor_ids';
		$k_secondary_idx = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'secondary_vendor_id') ?: '_vms_secondary_vendor_id') : '_vms_secondary_vendor_id';
		$k_secondary_type = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'secondary_vendor_type') ?: '_vms_secondary_vendor_type') : '_vms_secondary_vendor_type';

		$secondary_type = bvmgr_event_plan_normalize_secondary_vendor_type_slug((string) get_post_meta($post_id, $k_secondary_type, true));
		$secondary_ids = get_post_meta($post_id, $k_secondary_ids, true);
		if (!is_array($secondary_ids)) {
			$secondary_ids = get_post_meta($post_id, $k_secondary_idx, false);
			if (!is_array($secondary_ids)) {
				$secondary_ids = array();
			}
		}

		$secondary_ids = array_values(array_unique(array_filter(array_map('absint', $secondary_ids))));

		if ($secondary_type !== '') {
			return bvmgr_event_plan_normalize_secondary_vendor_assignment_map($post_id, array(
				$secondary_type => array(
					'type_slug' => $secondary_type,
					'mode' => 'standard',
					'slot_limit' => null,
					'vendor_ids' => $secondary_ids,
				),
			), $primary_vendor_id, array(
				'preserve_empty' => true,
			));
		}

		return bvmgr_event_plan_guess_secondary_vendor_assignments_from_flat_ids($post_id, $secondary_ids, $primary_vendor_id);
	}
}

if (!function_exists('bvmgr_event_plan_get_secondary_vendor_assignments')) {
	function bvmgr_event_plan_get_secondary_vendor_assignments(int $post_id, array $args = array()): array
	{
		$post_id = absint($post_id);
		$primary_vendor_id = array_key_exists('primary_vendor_id', $args)
			? absint($args['primary_vendor_id'])
			: (int) get_post_meta($post_id, '_vms_band_vendor_id', true);

		$raw = get_post_meta($post_id, bvmgr_event_plan_secondary_vendor_assignment_meta_key(), true);
		$has_canonical_meta = function_exists('metadata_exists')
			? metadata_exists('post', $post_id, bvmgr_event_plan_secondary_vendor_assignment_meta_key())
			: ($raw !== '' && $raw !== null);

		if (is_array($raw)) {
			$normalized = bvmgr_event_plan_normalize_secondary_vendor_assignment_map($post_id, $raw, $primary_vendor_id, array(
				'preserve_empty' => true,
			));
			if (!empty($normalized) || $has_canonical_meta) {
				return $normalized;
			}
		}

		return bvmgr_event_plan_read_legacy_secondary_vendor_assignments($post_id, $primary_vendor_id);
	}
}

if (!function_exists('bvmgr_event_plan_write_secondary_vendor_assignments')) {
	function bvmgr_event_plan_write_secondary_vendor_assignments(int $post_id, array $assignments)
	{
		$post_id = absint($post_id);
		if ($post_id <= 0 || get_post_type($post_id) !== 'vms_event_plan') {
			return new WP_Error('vms_event_plan_invalid', __('Event Plan could not be found for secondary-vendor assignment.', 'backstage-venue-manager'));
		}

		$k_secondary_ids = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'secondary_vendor_ids') ?: '_vms_secondary_vendor_ids') : '_vms_secondary_vendor_ids';
		$k_secondary_idx = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'secondary_vendor_id') ?: '_vms_secondary_vendor_id') : '_vms_secondary_vendor_id';
		$k_secondary_type = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'secondary_vendor_type') ?: '_vms_secondary_vendor_type') : '_vms_secondary_vendor_type';
		$k_secondary_unq = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'secondary_vendor_unqualified') ?: '_vms_secondary_vendor_unqualified') : '_vms_secondary_vendor_unqualified';
		$k_secondary_unq_ids = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'secondary_vendor_unqualified_ids') ?: '_vms_secondary_vendor_unqualified_ids') : '_vms_secondary_vendor_unqualified_ids';
		$primary_vendor_id = (int) get_post_meta($post_id, '_vms_band_vendor_id', true);

		$normalized = bvmgr_event_plan_normalize_secondary_vendor_assignment_map($post_id, $assignments, $primary_vendor_id, array(
			'preserve_empty' => true,
		));
		$capacity_validation = bvmgr_event_plan_validate_secondary_vendor_assignment_capacity($normalized);
		if (is_wp_error($capacity_validation)) {
			return $capacity_validation;
		}

		$flat_ids = bvmgr_event_plan_get_secondary_vendor_flat_ids_from_assignments($normalized, $primary_vendor_id);
		$legacy_type = bvmgr_event_plan_legacy_secondary_vendor_type_from_assignments($normalized);

		if (!empty($normalized)) {
			update_post_meta($post_id, bvmgr_event_plan_secondary_vendor_assignment_meta_key(), $normalized);
		} else {
			delete_post_meta($post_id, bvmgr_event_plan_secondary_vendor_assignment_meta_key());
		}

		if ($legacy_type !== '') {
			update_post_meta($post_id, $k_secondary_type, $legacy_type);
		} else {
			delete_post_meta($post_id, $k_secondary_type);
		}

		if (!empty($flat_ids)) {
			update_post_meta($post_id, $k_secondary_ids, $flat_ids);
		} else {
			delete_post_meta($post_id, $k_secondary_ids);
		}

		delete_post_meta($post_id, $k_secondary_idx);
		foreach ($flat_ids as $vendor_id) {
			add_post_meta($post_id, $k_secondary_idx, (int) $vendor_id, false);
		}

		$unqualified_ids = array();
		if (function_exists('bvmgr_secondary_vendor_is_qualified')) {
			foreach ($normalized as $type_slug => $assignment) {
				foreach ((array) ($assignment['vendor_ids'] ?? array()) as $vendor_id) {
					$vendor_id = absint($vendor_id);
					if ($vendor_id <= 0) {
						continue;
					}

					$ok = bvmgr_secondary_vendor_is_qualified($vendor_id, array(
						'context' => 'event_plan_secondary_vendor',
						'plan_id' => $post_id,
						'type_slug' => (string) $type_slug,
					));
					if (!$ok) {
						$unqualified_ids[] = $vendor_id;
					}
				}
			}
		}

		$unqualified_ids = array_values(array_unique(array_filter(array_map('absint', $unqualified_ids))));
		if (!empty($unqualified_ids)) {
			update_post_meta($post_id, $k_secondary_unq, '1');
			update_post_meta($post_id, $k_secondary_unq_ids, $unqualified_ids);
		} else {
			delete_post_meta($post_id, $k_secondary_unq);
			delete_post_meta($post_id, $k_secondary_unq_ids);
		}

		return array(
			'secondary_vendor_assignments' => $normalized,
			'secondary_vendor_type' => $legacy_type,
			'secondary_vendor_ids' => $flat_ids,
			'unqualified_ids' => $unqualified_ids,
		);
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
				'secondary_vendor_assignments' => array(),
				'secondary_vendor_type' => '',
				'secondary_vendor_ids' => array(),
				'linked_tec_event_id' => 0,
			);

		$primary_vendor_id = (int) ($current_state['primary_vendor_id'] ?? 0);
		$assignments_field_present = array_key_exists('vms_secondary_vendor_assignments', $request);
		$type_field_present = array_key_exists('vms_secondary_vendor_type', $request);
		$ids_field_present = array_key_exists('vms_secondary_vendor_ids', $request);
		$clear_requested = !empty($request['vms_clear_secondary_vendors']);
		$submission_present = $assignments_field_present || $type_field_present || $ids_field_present || $clear_requested;

		$secondary_vendor_assignments = is_array($current_state['secondary_vendor_assignments'] ?? null)
			? (array) $current_state['secondary_vendor_assignments']
			: array();

		if ($clear_requested) {
			$secondary_vendor_assignments = array();
		} elseif ($assignments_field_present) {
			$raw_assignments = $request['vms_secondary_vendor_assignments'];
			$raw_assignments = is_array($raw_assignments) ? wp_unslash($raw_assignments) : array();
			$secondary_vendor_assignments = bvmgr_event_plan_normalize_secondary_vendor_assignment_map($post_id, (array) $raw_assignments, $primary_vendor_id, array(
				'preserve_empty' => true,
			));
		} elseif ($type_field_present || $ids_field_present) {
			$current_type = bvmgr_event_plan_normalize_secondary_vendor_type_slug((string) ($current_state['secondary_vendor_type'] ?? ''));
			$current_ids = array_values(array_unique(array_filter(array_map('absint', (array) ($current_state['secondary_vendor_ids'] ?? array())))));

			$type_slug = $current_type;
			if ($type_field_present) {
				$posted_type = bvmgr_event_plan_normalize_secondary_vendor_type_slug((string) wp_unslash((string) $request['vms_secondary_vendor_type']));
				if ($posted_type !== '') {
					$type_slug = $posted_type;
				}
			}

			$secondary_ids = $current_ids;
			if ($ids_field_present) {
				$raw_secondary = $request['vms_secondary_vendor_ids'];
				$raw_secondary = is_array($raw_secondary) ? wp_unslash($raw_secondary) : array();
				$posted_secondary_ids = array_values(array_unique(array_filter(array_map('absint', (array) $raw_secondary), static function ($vendor_id): bool {
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

			$secondary_vendor_assignments = $type_slug !== '' || !empty($secondary_ids)
				? bvmgr_event_plan_normalize_secondary_vendor_assignment_map($post_id, array(
					$type_slug !== '' ? $type_slug : 'legacy' => array(
						'type_slug' => $type_slug,
						'mode' => 'standard',
						'slot_limit' => null,
						'vendor_ids' => $secondary_ids,
					),
				), $primary_vendor_id, array(
					'preserve_empty' => true,
				))
				: array();
		}

		$type_slug = bvmgr_event_plan_legacy_secondary_vendor_type_from_assignments($secondary_vendor_assignments);
		$secondary_ids = bvmgr_event_plan_get_secondary_vendor_flat_ids_from_assignments($secondary_vendor_assignments, $primary_vendor_id);

		return array(
			'current_state' => $current_state,
			'assignments_field_present' => $assignments_field_present,
			'type_field_present' => $type_field_present,
			'ids_field_present' => $ids_field_present,
			'submission_present' => $submission_present,
			'clear_requested' => $clear_requested,
			'secondary_vendor_assignments' => $secondary_vendor_assignments,
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
		$k_tec_event_id = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'tec_event_id') ?: '_vms_tec_event_id') : '_vms_tec_event_id';
		$primary_vendor_id = (int) get_post_meta($post_id, $k_band, true);
		$secondary_vendor_assignments = bvmgr_event_plan_get_secondary_vendor_assignments($post_id, array(
			'primary_vendor_id' => $primary_vendor_id,
		));

		return array(
			'primary_vendor_id' => $primary_vendor_id,
			'secondary_vendor_assignments' => $secondary_vendor_assignments,
			'secondary_vendor_type' => bvmgr_event_plan_legacy_secondary_vendor_type_from_assignments($secondary_vendor_assignments),
			'secondary_vendor_ids' => bvmgr_event_plan_get_secondary_vendor_flat_ids_from_assignments($secondary_vendor_assignments, $primary_vendor_id),
			'linked_tec_event_id' => (int) get_post_meta($post_id, $k_tec_event_id, true),
		);
	}
}

if (!function_exists('bvmgr_event_plan_secondary_vendor_state_diff_fields')) {
	function bvmgr_event_plan_secondary_vendor_state_diff_fields(array $before, array $after, array $fields = array()): array
	{
		if (empty($fields)) {
			$fields = array('primary_vendor_id', 'secondary_vendor_assignments', 'secondary_vendor_type', 'secondary_vendor_ids', 'linked_tec_event_id');
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
				$before_encoded = wp_json_encode(bvmgr_event_plan_secondary_vendor_compare_value((array) $before_value));
				$after_encoded = wp_json_encode(bvmgr_event_plan_secondary_vendor_compare_value((array) $after_value));
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
		$k_secondary_type = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'secondary_vendor_type') ?: '_vms_secondary_vendor_type') : '_vms_secondary_vendor_type';
		$k_snapshot = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'vendor_category_snapshot') ?: '_vms_vendor_category_snapshot') : '_vms_vendor_category_snapshot';
		$k_issue = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'integrity_issue') ?: '_vms_integrity_issue') : '_vms_integrity_issue';

		$expected_assignments = is_array($current_state['secondary_vendor_assignments'] ?? null)
			? (array) $current_state['secondary_vendor_assignments']
			: array();
		$expected_secondary_ids = array_values(array_unique(array_filter(array_map('absint', (array) ($current_state['secondary_vendor_ids'] ?? array())), static function ($vendor_id): bool {
			return $vendor_id > 0;
		})));
		$expected_type = bvmgr_event_plan_legacy_secondary_vendor_type_from_assignments($expected_assignments);

		$stored_canonical = get_post_meta($post_id, bvmgr_event_plan_secondary_vendor_assignment_meta_key(), true);
		$stored_canonical = is_array($stored_canonical)
			? bvmgr_event_plan_normalize_secondary_vendor_assignment_map($post_id, $stored_canonical, (int) ($current_state['primary_vendor_id'] ?? 0), array(
				'preserve_empty' => true,
			))
			: array();
		$has_canonical = function_exists('metadata_exists')
			? metadata_exists('post', $post_id, bvmgr_event_plan_secondary_vendor_assignment_meta_key())
			: (!empty($stored_canonical));

		$stored_secondary_ids = get_post_meta($post_id, $k_secondary_ids, true);
		if (!is_array($stored_secondary_ids)) {
			$stored_secondary_ids = array();
		}
		$stored_secondary_ids = array_values(array_unique(array_filter(array_map('absint', $stored_secondary_ids), static function ($vendor_id): bool {
			return $vendor_id > 0;
		})));

		$index_secondary_ids = array_values(array_unique(array_filter(array_map('absint', (array) get_post_meta($post_id, $k_secondary_idx, false)), static function ($vendor_id): bool {
			return $vendor_id > 0;
		})));
		$stored_type = bvmgr_event_plan_normalize_secondary_vendor_type_slug((string) get_post_meta($post_id, $k_secondary_type, true));

		$repair_reasons = array();
		if ((empty($stored_canonical) && !empty($expected_assignments)) || ($has_canonical && wp_json_encode(bvmgr_event_plan_secondary_vendor_compare_value($stored_canonical)) !== wp_json_encode(bvmgr_event_plan_secondary_vendor_compare_value($expected_assignments)))) {
			$repair_reasons[] = 'secondary_vendor_assignment_map_mismatch';
		}
		if (wp_json_encode($stored_secondary_ids) !== wp_json_encode($expected_secondary_ids)) {
			$repair_reasons[] = 'secondary_vendor_canonical_mismatch';
		}
		if (wp_json_encode($index_secondary_ids) !== wp_json_encode($expected_secondary_ids)) {
			$repair_reasons[] = 'secondary_vendor_index_mismatch';
		}
		if ($stored_type !== $expected_type) {
			$repair_reasons[] = 'secondary_vendor_legacy_type_mismatch';
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
	function bvmgr_event_plan_set_secondary_vendors(int $post_id, string $type_slug, array $secondary_ids)
	{
		$post_id = absint($post_id);
		if ($post_id <= 0 || get_post_type($post_id) !== 'vms_event_plan') {
			return new WP_Error('vms_event_plan_invalid', __('Event Plan could not be found for secondary-vendor assignment.', 'backstage-venue-manager'));
		}

		$type_slug = bvmgr_event_plan_normalize_secondary_vendor_type_slug($type_slug);
		$current_assignments = bvmgr_event_plan_get_secondary_vendor_assignments($post_id);

		if ($type_slug === '') {
			$current_assignments = array();
		} else {
			$current_assignment = isset($current_assignments[$type_slug]) && is_array($current_assignments[$type_slug])
				? (array) $current_assignments[$type_slug]
				: array();
			$current_assignments[$type_slug] = array(
				'mode' => (string) ($current_assignment['mode'] ?? bvmgr_event_plan_secondary_vendor_default_mode($type_slug)),
				'slot_limit' => array_key_exists($type_slug, $current_assignments)
					? ($current_assignment['slot_limit'] ?? bvmgr_event_plan_secondary_vendor_default_slot_limit($type_slug, (string) ($current_assignment['mode'] ?? 'standard')))
					: bvmgr_event_plan_secondary_vendor_default_slot_limit($type_slug, bvmgr_event_plan_secondary_vendor_default_mode($type_slug)),
				'vendor_ids' => array_values(array_unique(array_filter(array_map('absint', $secondary_ids)))),
			);
			if (bvmgr_event_plan_parse_secondary_vendor_over_capacity_override($current_assignment['allow_over_capacity'] ?? false)) {
				$current_assignments[$type_slug]['allow_over_capacity'] = true;
			}
			if (array_key_exists('needed_slots', $current_assignment) && $current_assignment['needed_slots'] !== '' && $current_assignment['needed_slots'] !== null) {
				$current_assignments[$type_slug]['needed_slots'] = max(0, (int) $current_assignment['needed_slots']);
			}
			if (array_key_exists('open_for_dispatch', $current_assignment)) {
				$current_assignments[$type_slug]['open_for_dispatch'] = bvmgr_event_plan_parse_secondary_vendor_over_capacity_override($current_assignment['open_for_dispatch']);
			}
		}

		$write_result = bvmgr_event_plan_write_secondary_vendor_assignments($post_id, $current_assignments);
		if (is_wp_error($write_result)) {
			return $write_result;
		}

		if (function_exists('bvmgr_event_plan_clear_integrity_flags')) {
			$k_issue = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_issue') : '_vms_integrity_issue';
			$issue_now = (string) get_post_meta($post_id, $k_issue, true);
			if ($issue_now === 'missing_secondary_vendor') {
				bvmgr_event_plan_clear_integrity_flags($post_id);
			}
		}

		$assignments = (array) ($write_result['secondary_vendor_assignments'] ?? array());
		$assignment = ($type_slug !== '' && isset($assignments[$type_slug]) && is_array($assignments[$type_slug]))
			? (array) $assignments[$type_slug]
			: array();

		return array(
			'type_slug' => $type_slug,
			'secondary_ids' => array_values(array_unique(array_filter(array_map('absint', (array) ($assignment['vendor_ids'] ?? array()))))),
			'secondary_vendor_assignments' => $assignments,
			'secondary_vendor_type' => (string) ($write_result['secondary_vendor_type'] ?? ''),
			'secondary_vendor_ids' => array_values(array_unique(array_filter(array_map('absint', (array) ($write_result['secondary_vendor_ids'] ?? array()))))),
			'unqualified_ids' => array_values(array_unique(array_filter(array_map('absint', (array) ($write_result['unqualified_ids'] ?? array()))))),
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

		$current_vendor_state = bvmgr_event_plan_get_secondary_vendor_state($post_id);
		$secondary_vendor_submission = bvmgr_event_plan_resolve_secondary_vendor_submission($post_id, $request);
		$proposed_assignments = is_array($secondary_vendor_submission['secondary_vendor_assignments'] ?? null)
			? (array) $secondary_vendor_submission['secondary_vendor_assignments']
			: array();
		$linked_tec_event_id = (int) ($current_vendor_state['linked_tec_event_id'] ?? 0);
		$capacity_validation = bvmgr_event_plan_validate_secondary_vendor_assignment_capacity($proposed_assignments);
		if (is_wp_error($capacity_validation)) {
			return $capacity_validation;
		}

		$proposed_vendor_state = array(
			'primary_vendor_id' => (int) ($current_vendor_state['primary_vendor_id'] ?? 0),
			'secondary_vendor_assignments' => $proposed_assignments,
			'secondary_vendor_type' => bvmgr_event_plan_legacy_secondary_vendor_type_from_assignments($proposed_assignments),
			'secondary_vendor_ids' => bvmgr_event_plan_get_secondary_vendor_flat_ids_from_assignments($proposed_assignments, (int) ($current_vendor_state['primary_vendor_id'] ?? 0)),
			'linked_tec_event_id' => $linked_tec_event_id,
		);

		$vendor_dirty_fields = bvmgr_event_plan_secondary_vendor_state_diff_fields(
			$current_vendor_state,
			$proposed_vendor_state,
			array('secondary_vendor_assignments', 'secondary_vendor_type', 'secondary_vendor_ids')
		);
		$repair_reasons = bvmgr_event_plan_secondary_vendor_rebuild_repair_reasons($post_id, $current_vendor_state);
		$changed = !empty($vendor_dirty_fields) || !empty($repair_reasons);
		$queued_calendar_maintenance = false;

		if ($changed) {
			$write_result = bvmgr_event_plan_write_secondary_vendor_assignments($post_id, $proposed_assignments);
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

			if (function_exists('bvmgr_event_plan_clear_integrity_flags')) {
				$k_issue = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'integrity_issue') : '_vms_integrity_issue';
				$issue_now = (string) get_post_meta($post_id, $k_issue, true);
				if ($issue_now === 'missing_secondary_vendor') {
					bvmgr_event_plan_clear_integrity_flags($post_id);
				}
			}
		}

		clean_post_cache($post_id);

		return array(
			'changed' => $changed,
			'dirty_fields' => array_values(array_unique(array_map('sanitize_key', (array) $vendor_dirty_fields))),
			'repair_reasons' => array_values(array_unique(array_map('sanitize_key', (array) $repair_reasons))),
			'queued_calendar_maintenance' => $queued_calendar_maintenance,
			'secondary_vendor_assignments' => $proposed_assignments,
			'secondary_vendor_type' => (string) ($proposed_vendor_state['secondary_vendor_type'] ?? ''),
			'secondary_vendor_ids' => array_values(array_unique(array_map('absint', (array) ($proposed_vendor_state['secondary_vendor_ids'] ?? array())))),
		);
	}
}

if (!function_exists('bvmgr_event_plan_secondary_vendor_group_available_slots')) {
	function bvmgr_event_plan_secondary_vendor_group_available_slots(array $assignment): ?int
	{
		$slot_limit = $assignment['slot_limit'] ?? null;
		if ($slot_limit === null || $slot_limit === '') {
			return null;
		}

		$limit = max(0, (int) $slot_limit);
		$filled = count(array_values(array_unique(array_filter(array_map('absint', (array) ($assignment['vendor_ids'] ?? array()))))));
		return max(0, $limit - $filled);
	}
}
