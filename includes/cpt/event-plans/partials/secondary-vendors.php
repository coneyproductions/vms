<?php
defined('ABSPATH') || exit;

$secondary_vendor_boot_summary = isset($secondary_vendor_boot_summary) && is_array($secondary_vendor_boot_summary)
	? $secondary_vendor_boot_summary
	: array();
$secondary_vendor_assignments = isset($secondary_vendor_assignments) && is_array($secondary_vendor_assignments)
	? $secondary_vendor_assignments
	: array();
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
$selected_vendor_titles = isset($secondary_vendor_boot_summary['selected_vendor_titles']) && is_array($secondary_vendor_boot_summary['selected_vendor_titles'])
	? $secondary_vendor_boot_summary['selected_vendor_titles']
	: array();
$selected_missing_map = isset($secondary_vendor_boot_summary['selected_missing_map']) && is_array($secondary_vendor_boot_summary['selected_missing_map'])
	? $secondary_vendor_boot_summary['selected_missing_map']
	: array();
$secondary_has_saved_state = !empty($assignment_groups);
$vms_module_owner = isset($vms_module_owner) ? sanitize_key((string) $vms_module_owner) : 'vendors';

$secondary_group_type_options = !empty($secondary_type_options) && is_array($secondary_type_options)
	? $secondary_type_options
	: (function_exists('bvmgr_event_plan_additional_vendor_type_options') ? (array) bvmgr_event_plan_additional_vendor_type_options() : array());

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

$secondary_config = array(
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

$render_secondary_vendor_status_badges = static function (array $group, int $selected_id): string {
	$selected_id = absint($selected_id);
	$pool_rows = isset($group['pool_option_rows']) && is_array($group['pool_option_rows'])
		? $group['pool_option_rows']
		: array();
	$pool_row = array();
	foreach ($pool_rows as $candidate_row) {
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
		echo '<span class="vms-secondary-vendor-badge vms-secondary-vendor-badge--' . esc_attr($variant) . '">' . esc_html($label) . '</span>';
	}

	return (string) ob_get_clean();
};

$render_secondary_vendor_group_summary = static function (array $group): array {
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
		'warning' => $warning !== '',
		'over_capacity' => $over_capacity,
		'is_market_group' => $is_market_group,
	);
};

$render_secondary_vendor_select = static function (array $group, int $selected_id, int $group_index, int $row_index, array $secondary_group_type_options, array $type_pool_map) use ($render_secondary_vendor_status_badges): void {
	$type_slug = sanitize_key((string) ($group['type_slug'] ?? ''));
	$pool_option_rows = isset($type_pool_map[$type_slug]) && is_array($type_pool_map[$type_slug])
		? $type_pool_map[$type_slug]
		: array();
	$disabled = $type_slug === '' ? ' disabled="disabled"' : '';
	$field_name = sprintf('vms_secondary_vendor_assignments[%d][vendor_ids][]', $group_index);

	echo '<div class="vms-secondary-vendor-row" data-vms-row-index="' . esc_attr((string) $row_index) . '">';
	echo '<div class="vms-secondary-vendor-row__vendor">';
	echo '<select name="' . esc_attr($field_name) . '" class="vms-secondary-vendor-select" data-selected-id="' . esc_attr((string) $selected_id) . '"' . $disabled . '>';
	if ($type_slug === '') {
		echo '<option value="">' . esc_html__('-- Select a Vendor Type first --', 'backstage-venue-manager') . '</option>';
	} else {
		echo '<option value="">' . esc_html__('-- Select a Vendor --', 'backstage-venue-manager') . '</option>';
		foreach ($pool_option_rows as $pool_row) {
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
			echo '<option value="' . esc_attr((string) $vendor_id) . '" ' . selected($selected_id, $vendor_id, false) . '>' . esc_html($label) . '</option>';
		}
	}
	echo '</select>';
	echo '</div>';
	echo '<div class="vms-secondary-vendor-row__indicators" data-vms-secondary-row-indicators>';
	echo $render_secondary_vendor_status_badges($group, $selected_id);
	echo '</div>';
	echo '<div class="vms-secondary-vendor-row__action">';
	echo '<button type="button" class="button button-secondary vms-secondary-vendor-remove">' . esc_html__('Remove', 'backstage-venue-manager') . '</button>';
	echo '</div>';
	echo '</div>';
};

$render_secondary_vendor_group = static function (array $group, int $group_index) use ($post, $secondary_group_type_options, $secondary_mode_options, $type_pool_map, $render_secondary_vendor_select, $render_secondary_vendor_group_summary): void {
	$type_slug = sanitize_key((string) ($group['type_slug'] ?? ''));
	$type_name = trim((string) ($group['type_name'] ?? ''));
	if ($type_name === '' && $type_slug !== '' && function_exists('bvmgr_vendor_type_label')) {
		$type_name = (string) bvmgr_vendor_type_label($type_slug);
	}
	$mode = sanitize_key((string) ($group['mode'] ?? 'standard'));
	$slot_limit_display = isset($group['slot_limit_display']) ? (string) $group['slot_limit_display'] : '';
	$vendor_ids = isset($group['vendor_ids']) && is_array($group['vendor_ids']) ? array_values(array_map('absint', $group['vendor_ids'])) : array();
	if (empty($vendor_ids)) {
		$vendor_ids = array(0);
	}
	$group_missing = isset($group['secondary_missing']) && is_array($group['secondary_missing']) ? $group['secondary_missing'] : array();
	$group_mismatch = isset($group['secondary_mismatch']) && is_array($group['secondary_mismatch']) ? $group['secondary_mismatch'] : array();
	$group_unqualified = isset($group['secondary_unqualified']) && is_array($group['secondary_unqualified']) ? $group['secondary_unqualified'] : array();
	$group_titles = isset($group['selected_vendor_titles']) && is_array($group['selected_vendor_titles']) ? $group['selected_vendor_titles'] : array();
	$group_missing_map = isset($group['selected_missing_map']) && is_array($group['selected_missing_map']) ? $group['selected_missing_map'] : array();
	$group_summary = $render_secondary_vendor_group_summary($group);
	$is_market_group = !empty($group_summary['is_market_group']);
	$group_has_type = ($type_slug !== '');
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
	$market_control_attrs = $is_market_group ? '' : ' hidden="hidden"';
	$market_input_attrs = $is_market_group ? '' : ' disabled="disabled"';

	$add_secondary_vendor_args = array(
		'post_type' => 'vms_vendor',
		'vms_return_to_event_plan' => (int) $post->ID,
		'vms_prefill_vendor_role' => 'secondary',
	);
	if ($type_slug !== '') {
		$add_secondary_vendor_args['vms_prefill_vendor_type'] = $type_slug;
	}
	$add_secondary_vendor_url = add_query_arg($add_secondary_vendor_args, admin_url('post-new.php'));

	echo '<div class="vms-secondary-vendor-group' . ($is_market_group ? ' vms-secondary-vendor-group--market' : '') . (!$group_has_type ? ' vms-secondary-vendor-group--type-pending' : '') . '" data-vms-group-index="' . esc_attr((string) $group_index) . '"';
	echo ' data-vms-missing-ids="' . esc_attr(wp_json_encode(array_values(array_map('absint', $group_missing)))) . '"';
	echo ' data-vms-mismatch-ids="' . esc_attr(wp_json_encode(array_values(array_map('absint', $group_mismatch)))) . '"';
	echo ' data-vms-unqualified-ids="' . esc_attr(wp_json_encode(array_values(array_map('absint', $group_unqualified)))) . '"';
	echo '>';
	echo '<div class="vms-secondary-vendor-group__header">';

	echo '<label class="vms-secondary-vendor-group__field vms-secondary-vendor-group__field--type">';
	echo '<span class="vms-secondary-vendor-group__field-label">' . esc_html__('Vendor type', 'backstage-venue-manager') . '</span>';
	echo '<select name="' . esc_attr(sprintf('vms_secondary_vendor_assignments[%d][type_slug]', $group_index)) . '" class="vms-secondary-vendor-group-type">';
	echo '<option value="">' . esc_html__('-- Select a Vendor Type --', 'backstage-venue-manager') . '</option>';
	foreach ($secondary_group_type_options as $option_slug => $option_label) {
		$option_slug = sanitize_key((string) $option_slug);
		$option_label = trim((string) $option_label);
		if ($option_slug === '' || $option_label === '') {
			continue;
		}
		echo '<option value="' . esc_attr($option_slug) . '" ' . selected($type_slug, $option_slug, false) . '>' . esc_html($option_label) . '</option>';
	}
	echo '</select>';
	echo '</label>';

	echo '<label class="vms-secondary-vendor-group__field vms-secondary-vendor-group__field--mode">';
	echo '<span class="vms-secondary-vendor-group__field-label">' . esc_html__('Mode', 'backstage-venue-manager') . '</span>';
	echo '<select name="' . esc_attr(sprintf('vms_secondary_vendor_assignments[%d][mode]', $group_index)) . '" class="vms-secondary-vendor-group-mode">';
	foreach ($secondary_mode_options as $mode_slug => $mode_label) {
		$mode_slug = sanitize_key((string) $mode_slug);
		if ($mode_slug === '') {
			continue;
		}
		echo '<option value="' . esc_attr($mode_slug) . '" ' . selected($mode, $mode_slug, false) . '>' . esc_html((string) $mode_label) . '</option>';
	}
	echo '</select>';
	echo '</label>';

	echo '<label class="vms-secondary-vendor-group__field vms-secondary-vendor-group__field--capacity">';
	echo '<span class="vms-secondary-vendor-group__field-label">' . esc_html__('Slot limit / capacity', 'backstage-venue-manager') . '</span>';
	echo '<input type="number" min="0" step="1" class="small-text vms-secondary-vendor-group-slot-limit" name="' . esc_attr(sprintf('vms_secondary_vendor_assignments[%d][slot_limit]', $group_index)) . '" value="' . esc_attr($slot_limit_display) . '" placeholder="' . esc_attr__('Use default', 'backstage-venue-manager') . '" />';
	echo '</label>';

	echo '<label class="vms-secondary-vendor-group__field vms-secondary-vendor-group__field--market-target"' . $market_control_attrs . '>';
	echo '<span class="vms-secondary-vendor-group__field-label">' . esc_html__('Market target / needed vendors', 'backstage-venue-manager') . '</span>';
	echo '<input type="number" min="0" step="1" class="small-text vms-secondary-vendor-group-needed-slots" name="' . esc_attr(sprintf('vms_secondary_vendor_assignments[%d][needed_slots]', $group_index)) . '" value="' . esc_attr($needed_slots_display) . '" placeholder="' . esc_attr__('Blank', 'backstage-venue-manager') . '"' . $market_input_attrs . ' />';
	echo '</label>';

	echo '<label class="vms-secondary-vendor-group__field vms-secondary-vendor-group__field--market-dispatch"' . $market_control_attrs . '>';
	echo '<span class="vms-secondary-vendor-group__field-label">' . esc_html__('ADD visibility', 'backstage-venue-manager') . '</span>';
	echo '<span class="vms-secondary-vendor-group__checkbox-line">';
	echo '<input type="hidden" class="vms-secondary-vendor-group-open-for-dispatch-hidden" name="' . esc_attr(sprintf('vms_secondary_vendor_assignments[%d][open_for_dispatch]', $group_index)) . '" value="0"' . $market_input_attrs . ' />';
	echo '<input type="checkbox" class="vms-secondary-vendor-group-open-for-dispatch" name="' . esc_attr(sprintf('vms_secondary_vendor_assignments[%d][open_for_dispatch]', $group_index)) . '" value="1" ' . checked($open_for_dispatch, true, false) . $market_input_attrs . ' />';
	echo '<span>' . esc_html__('Show this market need in ADD', 'backstage-venue-manager') . '</span>';
	echo '</span>';
	echo '</label>';

	echo '<div class="vms-secondary-vendor-group__field vms-secondary-vendor-group__field--summary">';
	echo '<span class="vms-secondary-vendor-group__field-label">' . esc_html__('Filled', 'backstage-venue-manager') . '</span>';
	echo '<p class="vms-secondary-vendor-group__summary' . (!empty($group_summary['warning']) ? ' is-warning' : '') . '">' . esc_html((string) ($group_summary['text'] ?? '')) . '</p>';
	echo '</div>';

	echo '<div class="vms-secondary-vendor-group__field vms-secondary-vendor-group__field--actions">';
	echo '<span class="vms-secondary-vendor-group__field-label screen-reader-text">' . esc_html__('Actions', 'backstage-venue-manager') . '</span>';
	echo '<button type="button" class="button button-secondary vms-secondary-vendor-remove-group">' . esc_html__('Remove group', 'backstage-venue-manager') . '</button>';
	echo '</div>';

	echo '</div>';

	echo '<label class="vms-secondary-vendor-group__override"' . (!$is_over_capacity ? ' hidden="hidden"' : '') . '>';
	echo '<input type="checkbox" class="vms-secondary-vendor-group-over-capacity-override" name="' . esc_attr(sprintf('vms_secondary_vendor_assignments[%d][allow_over_capacity]', $group_index)) . '" value="1" ' . checked($allow_over_capacity, true, false) . ' />';
	echo '<span>' . esc_html__('Allow over-capacity assignment for this group.', 'backstage-venue-manager') . '</span>';
	echo '</label>';

	if (!empty($group_missing)) {
		echo '<div class="notice notice-warning inline vms-notice vms-notice--warning"><p>';
		echo esc_html__('🚩 One or more selected vendors no longer exist (or are in the Trash). Remove or replace them below.', 'backstage-venue-manager');
		echo '</p></div>';
	}

	if (!empty($group_mismatch)) {
		$mismatch_labels = array();
		foreach ($group_mismatch as $vendor_id) {
			$vendor_id = (int) $vendor_id;
			/* translators: %d: vendor ID. */
			$mismatch_labels[] = trim((string) ($group_titles[$vendor_id] ?? sprintf(__('Vendor #%d', 'backstage-venue-manager'), $vendor_id)));
		}
		echo '<div class="notice notice-warning inline vms-notice vms-notice--warning"><p>';
		echo esc_html__('🚩 One or more selected vendors no longer match this vendor type. Review and re-select vendors below.', 'backstage-venue-manager');
		if (!empty($mismatch_labels)) {
			/* translators: %s: affected vendor(s). */
			echo ' ' . esc_html(sprintf(__('Affected vendor(s): %s', 'backstage-venue-manager'), implode(', ', $mismatch_labels)));
		}
		echo '</p></div>';
	}

	if (!empty($group_unqualified)) {
		echo '<div class="notice notice-warning inline vms-notice vms-notice--warning"><p>';
		echo esc_html__('🚩 One or more selected vendors are missing required profile items. They are still attached, but they need attention.', 'backstage-venue-manager');
		echo '</p>';
		if (function_exists('bvmgr_help_is_enabled') && bvmgr_help_is_enabled()) {
			echo '<ul class="vms-help-missing-list">';
			foreach ($group_unqualified as $vendor_id) {
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
				echo '<li><strong>' . esc_html($vendor_label) . '</strong>: ';
				echo esc_html__('Missing:', 'backstage-venue-manager') . ' ' . esc_html(!empty($missing_items) ? implode(', ', $missing_items) : __('Unknown', 'backstage-venue-manager')) . ' ';
				echo '<a href="' . esc_url(admin_url('post.php?post=' . $vendor_id . '&action=edit')) . '" target="_blank" rel="noopener">' . esc_html__('Edit vendor', 'backstage-venue-manager') . '</a>';
				echo '</li>';
			}
			echo '</ul>';
		}
		echo '</div>';
	}

	echo '<div class="vms-secondary-vendor-group__rows-toolbar">';
	echo '<div class="vms-secondary-vendor-group__rows-copy">';
	echo '<p class="vms-secondary-vendor-group__label">' . esc_html__('Selected vendors', 'backstage-venue-manager') . '</p>';
	echo '<p class="description vms-secondary-vendor-group__guidance"' . ($group_has_type ? ' hidden="hidden"' : '') . '>' . esc_html__('Select a vendor type to choose eligible vendors.', 'backstage-venue-manager') . '</p>';
	echo '</div>';
	echo '<p class="vms-secondary-vendor-actions vms-secondary-vendor-actions--inline">';
	echo '<button type="button" class="button button-secondary vms-secondary-vendor-add-row">' . esc_html__('Add vendor row', 'backstage-venue-manager') . '</button> ';
	echo '<a class="button button-secondary" href="' . esc_url($add_secondary_vendor_url) . '" target="_blank" rel="noopener">' . esc_html__('Add new vendor', 'backstage-venue-manager') . '</a>';
	echo '</p>';
	echo '</div>';

	echo '<div class="vms-secondary-vendor-rows-wrap">';
	echo '<div class="vms-secondary-vendor-rows__head" aria-hidden="true">';
	echo '<span>' . esc_html__('Vendor', 'backstage-venue-manager') . '</span>';
	echo '<span>' . esc_html__('Status', 'backstage-venue-manager') . '</span>';
	echo '<span>' . esc_html__('Action', 'backstage-venue-manager') . '</span>';
	echo '</div>';
	echo '<div class="vms-secondary-vendor-rows">';
	foreach ($vendor_ids as $row_index => $vendor_id) {
		$render_secondary_vendor_select($group, (int) $vendor_id, $group_index, (int) $row_index, $secondary_group_type_options, $type_pool_map);
	}
	echo '</div>';
	echo '</div>';
	echo '</div>';
};
?>

<p class="description">
	<?php esc_html_e('Attach one or more additional vendors to this event. Use separate groups for Food Vendor, Dessert Vendor, Photographer, Market Vendor, and other non-performer vendor types. These vendors will see this date as Tentative when the Event Plan is Draft/Ready and Booked once Published.', 'backstage-venue-manager'); ?>
</p>

<div id="vms-secondary-vendors-section"
	data-vms-module-owner="<?php echo esc_attr($vms_module_owner); ?>"
	data-vms-save-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
	data-vms-save-nonce="<?php echo esc_attr(wp_create_nonce('bvmgr_event_plan_secondary_vendors_save')); ?>"
	data-vms-save-post-id="<?php echo (int) $post->ID; ?>">
	<input type="hidden" name="vms_secondary_vendors_module_detached" value="1" />
	<input type="hidden" name="vms_clear_secondary_vendors" value="0" id="vms-clear-secondary-vendors-intent" />
	<script type="application/json" data-vms-secondary-config><?php echo wp_json_encode($secondary_config); ?></script>

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
			<?php $render_secondary_vendor_group((array) $group, (int) $group_index); ?>
		<?php endforeach; ?>
	</div>

	<p class="vms-secondary-vendor-actions">
		<button type="button" class="button button-secondary" id="vms-secondary-vendor-add-group"><?php esc_html_e('Add vendor group', 'backstage-venue-manager'); ?></button>
		<button type="button" class="button button-secondary" id="vms-secondary-vendor-clear" <?php disabled(!$secondary_has_saved_state); ?>><?php esc_html_e('Clear additional vendors', 'backstage-venue-manager'); ?></button>
		<button type="button" class="button button-primary" id="vms-secondary-vendor-save"><?php esc_html_e('Save Additional Vendors', 'backstage-venue-manager'); ?></button>
	</p>
	<p class="description vms-mt-8 vms-mb-0" data-vms-secondary-save-status aria-live="polite"></p>

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
						'vms_return_to_event_plan' => (int) $post->ID,
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
	$vendor_category_snapshot = function_exists('bvmgr_event_plan_collect_vendor_category_snapshot')
		? (array) bvmgr_event_plan_collect_vendor_category_snapshot((int) $post->ID)
		: array();
	$vendor_category_rows = isset($vendor_category_snapshot['vendors']) && is_array($vendor_category_snapshot['vendors'])
		? $vendor_category_snapshot['vendors']
		: array();
	$vendor_category_names = isset($vendor_category_snapshot['term_names']) && is_array($vendor_category_snapshot['term_names'])
		? $vendor_category_snapshot['term_names']
		: array();
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
							<?php /* translators: %s: secondary vendor category label. */ ?>
							- <?php printf(esc_html__('No %s selected yet.', 'backstage-venue-manager'), esc_html(strtolower($category_label))); ?>
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

</div>
