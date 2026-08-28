<?php
defined('ABSPATH') || exit;

/**
 * STAFF-01 Phase A admin surfaces:
 * - Staff Roles (taxonomy UI + structured role metadata)
 * - Staffing Templates
 * - Staffing Rollup Rebuild (Preview/Run)
 */

add_action('admin_menu', function () {
	$parent_slug = 'vms-dashboard';
	$capability = 'manage_options';

	add_submenu_page(
		$parent_slug,
		__('Staff Roles', 'backstage-venue-manager'),
		__('Staff Roles', 'backstage-venue-manager'),
		$capability,
		'edit-tags.php?taxonomy=vms_staff_role&post_type=vms_staff'
	);

	add_submenu_page(
		$parent_slug,
		__('Staffing Templates', 'backstage-venue-manager'),
		__('Staffing Templates', 'backstage-venue-manager'),
		$capability,
		'vms-staffing-templates',
		'bvmgr_staffing_admin_render_templates_page'
	);

	add_submenu_page(
		$parent_slug,
		__('Staffing Rollups', 'backstage-venue-manager'),
		__('Staffing Rollups', 'backstage-venue-manager'),
		$capability,
		'vms-staffing-rollups',
		'bvmgr_staffing_admin_render_rollups_page'
	);
}, 35);

if (!function_exists('bvmgr_staffing_role_meta_field_value')) {
	function bvmgr_staffing_role_meta_field_value(array $meta, string $key, $default = '')
	{
		return array_key_exists($key, $meta) ? $meta[$key] : $default;
	}
}


if (!function_exists('bvmgr_staffing_admin_qualification_mode_label')) {
	function bvmgr_staffing_admin_qualification_mode_label(string $mode): string
	{
		$mode = function_exists('bvmgr_staffing_normalize_qualification_mode')
			? bvmgr_staffing_normalize_qualification_mode($mode, 'warn')
			: 'warn';
		$labels = array(
			'warn' => __('Warn only', 'backstage-venue-manager'),
			'soft_block' => __('Soft block', 'backstage-venue-manager'),
			'hard_block' => __('Hard block', 'backstage-venue-manager'),
		);
		return isset($labels[$mode]) ? (string) $labels[$mode] : (string) $labels['warn'];
	}
}

if (!function_exists('bvmgr_staffing_admin_render_required_qualification_rows')) {
	function bvmgr_staffing_admin_render_required_qualification_rows(array $rows, string $field_base): void
	{
		if (empty($rows)) {
			$rows = array(array('name' => '', 'mode' => 'warn'));
		}
		?>
		<div class="vms-qualification-rule-builder" data-vms-qualification-builder="1" data-field-base="<?php echo esc_attr($field_base); ?>">
			<div class="vms-qualification-rule-rows" data-vms-qualification-rows="1">
				<?php foreach ($rows as $idx => $row) : ?>
					<?php
						$name = isset($row['name']) ? sanitize_text_field((string) $row['name']) : '';
						$mode = function_exists('bvmgr_staffing_normalize_qualification_mode')
							? bvmgr_staffing_normalize_qualification_mode((string) ($row['mode'] ?? 'warn'), 'warn')
							: 'warn';
					?>
					<div class="vms-qualification-rule-row" data-vms-qualification-row="1">
						<div class="vms-qualification-rule-row__fields">
							<label>
								<span><?php esc_html_e('Qualification', 'backstage-venue-manager'); ?></span>
								<input type="text" class="regular-text" name="<?php echo esc_attr($field_base . '[' . (int) $idx . '][name]'); ?>" value="<?php echo esc_attr($name); ?>" placeholder="TABC Certified">
							</label>
							<label>
								<span><?php esc_html_e('Enforcement', 'backstage-venue-manager'); ?></span>
								<select name="<?php echo esc_attr($field_base . '[' . (int) $idx . '][mode]'); ?>">
									<option value="warn" <?php selected($mode, 'warn'); ?>><?php esc_html_e('Warn only', 'backstage-venue-manager'); ?></option>
									<option value="soft_block" <?php selected($mode, 'soft_block'); ?>><?php esc_html_e('Soft block', 'backstage-venue-manager'); ?></option>
									<option value="hard_block" <?php selected($mode, 'hard_block'); ?>><?php esc_html_e('Hard block', 'backstage-venue-manager'); ?></option>
								</select>
							</label>
						</div>
						<div class="vms-qualification-rule-row__actions">
							<button type="button" class="button-link-delete vms-qualification-rule-remove" data-vms-qualification-remove="1"><?php esc_html_e('Remove', 'backstage-venue-manager'); ?></button>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<template data-vms-qualification-row-template="1">
				<div class="vms-qualification-rule-row" data-vms-qualification-row="1">
					<div class="vms-qualification-rule-row__fields">
						<label>
							<span><?php esc_html_e('Qualification', 'backstage-venue-manager'); ?></span>
							<input type="text" class="regular-text" name="<?php echo esc_attr($field_base . '[__INDEX__][name]'); ?>" value="" placeholder="TABC Certified">
						</label>
						<label>
							<span><?php esc_html_e('Enforcement', 'backstage-venue-manager'); ?></span>
							<select name="<?php echo esc_attr($field_base . '[__INDEX__][mode]'); ?>">
								<option value="warn"><?php esc_html_e('Warn only', 'backstage-venue-manager'); ?></option>
								<option value="soft_block"><?php esc_html_e('Soft block', 'backstage-venue-manager'); ?></option>
								<option value="hard_block"><?php esc_html_e('Hard block', 'backstage-venue-manager'); ?></option>
							</select>
						</label>
					</div>
					<div class="vms-qualification-rule-row__actions">
						<button type="button" class="button-link-delete vms-qualification-rule-remove" data-vms-qualification-remove="1"><?php esc_html_e('Remove', 'backstage-venue-manager'); ?></button>
					</div>
				</div>
			</template>
			<p><button type="button" class="button" data-vms-qualification-add="1"><?php esc_html_e('Add requirement', 'backstage-venue-manager'); ?></button></p>
			<p class="description"><?php esc_html_e('Each qualification can have its own enforcement level. Hard block prevents invalid assignments; soft block warns and flags them; warn only keeps it informational.', 'backstage-venue-manager'); ?></p>
		</div>
		<?php
	}
}

add_action('vms_staff_role_add_form_fields', function () {
	$defaults = function_exists('bvmgr_staffing_role_meta_defaults') ? bvmgr_staffing_role_meta_defaults() : array();
	$is_critical = !empty($defaults['is_critical']);
	$is_active = !empty($defaults['is_active']);
	$default_headcount = isset($defaults['default_headcount']) ? (int) $defaults['default_headcount'] : 1;
	$default_pay_type = isset($defaults['default_pay_type']) ? (string) $defaults['default_pay_type'] : 'none';
	$default_rate = isset($defaults['default_rate']) ? (string) $defaults['default_rate'] : '';
	$default_notes = isset($defaults['default_notes']) ? (string) $defaults['default_notes'] : '';
	?>
	<div class="form-field">
		<label>
			<input type="checkbox" name="vms_staffing_role_meta[is_critical]" value="1" <?php checked($is_critical); ?>>
			<?php esc_html_e('Critical role', 'backstage-venue-manager'); ?>
		</label>
		<p class="description"><?php esc_html_e('Critical unfilled roles produce red-flag readiness.', 'backstage-venue-manager'); ?></p>
	</div>
	<div class="form-field">
		<label>
			<input type="checkbox" name="vms_staffing_role_meta[is_active]" value="1" <?php checked($is_active); ?>>
			<?php esc_html_e('Active role', 'backstage-venue-manager'); ?>
		</label>
		<p class="description"><?php esc_html_e('Inactive roles stay in history but are hidden from new staffing selection.', 'backstage-venue-manager'); ?></p>
	</div>
	<div class="form-field">
		<label for="vms_staffing_role_default_headcount"><?php esc_html_e('Default headcount', 'backstage-venue-manager'); ?></label>
		<input type="number" min="1" step="1" id="vms_staffing_role_default_headcount" name="vms_staffing_role_meta[default_headcount]" value="<?php echo esc_attr((string) $default_headcount); ?>">
	</div>
	<div class="form-field">
		<label for="vms_staffing_role_default_pay_type"><?php esc_html_e('Default pay type', 'backstage-venue-manager'); ?></label>
		<select id="vms_staffing_role_default_pay_type" name="vms_staffing_role_meta[default_pay_type]">
			<option value="none" <?php selected($default_pay_type, 'none'); ?>><?php esc_html_e('None', 'backstage-venue-manager'); ?></option>
			<option value="hourly" <?php selected($default_pay_type, 'hourly'); ?>><?php esc_html_e('Hourly', 'backstage-venue-manager'); ?></option>
			<option value="flat" <?php selected($default_pay_type, 'flat'); ?>><?php esc_html_e('Flat', 'backstage-venue-manager'); ?></option>
		</select>
	</div>
	<div class="form-field">
		<label for="vms_staffing_role_default_rate"><?php esc_html_e('Default rate', 'backstage-venue-manager'); ?></label>
		<input type="number" min="0" step="0.01" id="vms_staffing_role_default_rate" name="vms_staffing_role_meta[default_rate]" value="<?php echo esc_attr($default_rate); ?>">
	</div>
	<div class="form-field">
		<label for="vms_staffing_role_default_notes"><?php esc_html_e('Default notes', 'backstage-venue-manager'); ?></label>
		<textarea id="vms_staffing_role_default_notes" name="vms_staffing_role_meta[default_notes]" rows="3"><?php echo esc_textarea($default_notes); ?></textarea>
	</div>
	<div class="form-field">
		<label><?php esc_html_e('Required qualifications', 'backstage-venue-manager'); ?></label>
		<?php
			$required_qualification_rules = isset($defaults['required_qualification_rules']) && is_array($defaults['required_qualification_rules']) ? $defaults['required_qualification_rules'] : array();
			bvmgr_staffing_admin_render_required_qualification_rows($required_qualification_rules, 'vms_staffing_role_meta[required_qualifications]');
		?>
	</div>
	<?php
});

add_action('vms_staff_role_edit_form_fields', function ($term) {
	$role_id = isset($term->term_id) ? absint($term->term_id) : 0;
	$meta = function_exists('bvmgr_staffing_role_meta_get') ? bvmgr_staffing_role_meta_get($role_id) : array();
	$is_critical = !empty(bvmgr_staffing_role_meta_field_value($meta, 'is_critical', 0));
	$is_active = !empty(bvmgr_staffing_role_meta_field_value($meta, 'is_active', 1));
	$default_headcount = (int) bvmgr_staffing_role_meta_field_value($meta, 'default_headcount', 1);
	$default_pay_type = (string) bvmgr_staffing_role_meta_field_value($meta, 'default_pay_type', 'none');
	$default_rate = (string) bvmgr_staffing_role_meta_field_value($meta, 'default_rate', '');
	$default_notes = (string) bvmgr_staffing_role_meta_field_value($meta, 'default_notes', '');
	?>
	<tr class="form-field">
		<th scope="row"><?php esc_html_e('Critical role', 'backstage-venue-manager'); ?></th>
		<td>
			<label>
				<input type="checkbox" name="vms_staffing_role_meta[is_critical]" value="1" <?php checked($is_critical); ?>>
				<?php esc_html_e('Mark as critical', 'backstage-venue-manager'); ?>
			</label>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row"><?php esc_html_e('Active role', 'backstage-venue-manager'); ?></th>
		<td>
			<label>
				<input type="checkbox" name="vms_staffing_role_meta[is_active]" value="1" <?php checked($is_active); ?>>
				<?php esc_html_e('Role is active', 'backstage-venue-manager'); ?>
			</label>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row"><label for="vms_staffing_role_default_headcount"><?php esc_html_e('Default headcount', 'backstage-venue-manager'); ?></label></th>
		<td><input type="number" min="1" step="1" id="vms_staffing_role_default_headcount" name="vms_staffing_role_meta[default_headcount]" value="<?php echo esc_attr((string) $default_headcount); ?>"></td>
	</tr>
	<tr class="form-field">
		<th scope="row"><label for="vms_staffing_role_default_pay_type"><?php esc_html_e('Default pay type', 'backstage-venue-manager'); ?></label></th>
		<td>
			<select id="vms_staffing_role_default_pay_type" name="vms_staffing_role_meta[default_pay_type]">
				<option value="none" <?php selected($default_pay_type, 'none'); ?>><?php esc_html_e('None', 'backstage-venue-manager'); ?></option>
				<option value="hourly" <?php selected($default_pay_type, 'hourly'); ?>><?php esc_html_e('Hourly', 'backstage-venue-manager'); ?></option>
				<option value="flat" <?php selected($default_pay_type, 'flat'); ?>><?php esc_html_e('Flat', 'backstage-venue-manager'); ?></option>
			</select>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row"><label for="vms_staffing_role_default_rate"><?php esc_html_e('Default rate', 'backstage-venue-manager'); ?></label></th>
		<td><input type="number" min="0" step="0.01" id="vms_staffing_role_default_rate" name="vms_staffing_role_meta[default_rate]" value="<?php echo esc_attr($default_rate); ?>"></td>
	</tr>
	<tr class="form-field">
		<th scope="row"><label for="vms_staffing_role_default_notes"><?php esc_html_e('Default notes', 'backstage-venue-manager'); ?></label></th>
		<td><textarea id="vms_staffing_role_default_notes" name="vms_staffing_role_meta[default_notes]" rows="3"><?php echo esc_textarea($default_notes); ?></textarea></td>
	</tr>
	<tr class="form-field">
		<th scope="row"><?php esc_html_e('Required qualifications', 'backstage-venue-manager'); ?></th>
		<td>
			<?php
				$required_qualification_rules = bvmgr_staffing_role_meta_field_value($meta, 'required_qualification_rules', array());
				if (!is_array($required_qualification_rules)) {
					$required_qualification_rules = array();
				}
				bvmgr_staffing_admin_render_required_qualification_rows($required_qualification_rules, 'vms_staffing_role_meta[required_qualifications]');
			?>
		</td>
	</tr>
	<?php
});

add_action('created_vms_staff_role', 'bvmgr_staffing_admin_save_role_term_meta');
add_action('edited_vms_staff_role', 'bvmgr_staffing_admin_save_role_term_meta');

if (!function_exists('bvmgr_staffing_admin_save_role_term_meta')) {
	function bvmgr_staffing_admin_save_role_term_meta($term_id): void
	{
		$term_id = absint($term_id);
		if ($term_id <= 0) return;
		if (!current_user_can('manage_options')) return;
		if (isset($_POST['_wpnonce_add-tag']) && !is_array($_POST['_wpnonce_add-tag'])) {
			$add_nonce = sanitize_text_field(wp_unslash((string) $_POST['_wpnonce_add-tag']));
			if (!wp_verify_nonce($add_nonce, 'add-tag')) return;
		} elseif (isset($_POST['_wpnonce']) && !is_array($_POST['_wpnonce'])) {
			$edit_nonce = sanitize_text_field(wp_unslash((string) $_POST['_wpnonce']));
			if (!wp_verify_nonce($edit_nonce, 'update-tag_' . $term_id)) return;
		} else {
			return;
		}

		$raw = isset($_POST['vms_staffing_role_meta']) && is_array($_POST['vms_staffing_role_meta'])
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Structured role-term meta is unslashed here and normalized by vms_staffing_role_meta_save().
			? (array) wp_unslash($_POST['vms_staffing_role_meta'])
			: null;
		if (!is_array($raw)) return;

		if (!function_exists('bvmgr_staffing_role_meta_save')) return;
		bvmgr_staffing_role_meta_save($term_id, $raw);
	}
}

if (!function_exists('bvmgr_staffing_admin_role_options_html')) {
	function bvmgr_staffing_admin_role_options_html(int $selected = 0): string
	{
		$roles = function_exists('bvmgr_staffing_get_role_catalog') ? bvmgr_staffing_get_role_catalog(true) : array();
		$html = '';
		foreach ($roles as $r) {
			$rid = isset($r['role_id']) ? absint($r['role_id']) : 0;
			if ($rid <= 0) continue;
			$name = isset($r['name']) ? (string) $r['name'] : ('#' . $rid);
			$active = !empty($r['is_active']);
			$label = $name . ($active ? '' : ' (' . __('inactive', 'backstage-venue-manager') . ')');
			$html .= sprintf(
				'<option value="%d" %s>%s</option>',
				$rid,
				selected($selected, $rid, false),
				esc_html($label)
			);
		}
		return $html;
	}
}

if (!function_exists('bvmgr_staffing_admin_get_venues')) {
	function bvmgr_staffing_admin_get_venues(): array
	{
		$venues = get_posts(array(
			'post_type'      => 'vms_venue',
			'post_status'    => array('publish', 'draft', 'private', 'pending'),
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		));
		return is_array($venues) ? $venues : array();
	}
}

if (!function_exists('bvmgr_staffing_admin_request_method')) {
	function bvmgr_staffing_admin_request_method(): string
	{
		$method = bvmgr_request_server_value('REQUEST_METHOD');
		if ($method === '') {
			return '';
		}

		return strtoupper(sanitize_text_field($method));
	}
}

if (!function_exists('bvmgr_staffing_admin_screen_is_role_target')) {
	function bvmgr_staffing_admin_screen_is_role_target($screen): bool
	{
		if (!is_object($screen)) {
			return false;
		}

		if (!in_array((string) ($screen->base ?? ''), array('edit-tags', 'term'), true)) {
			return false;
		}

		if ((string) ($screen->taxonomy ?? '') !== 'vms_staff_role') {
			return false;
		}

		return (string) ($screen->post_type ?? '') === 'vms_staff';
	}
}

if (!function_exists('bvmgr_staffing_admin_is_templates_page')) {
	function bvmgr_staffing_admin_is_templates_page(): bool
	{
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only Staffing templates routing only controls whether admin assets load.
			$page = bvmgr_request_read_key($_GET, 'page');

		return $page === 'vms-staffing-templates';
	}
}

if (!function_exists('bvmgr_staffing_admin_enqueue_assets')) {
	function bvmgr_staffing_admin_enqueue_assets(): void
	{
		if (!current_user_can('manage_options')) {
			return;
		}

		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		$is_role_screen = bvmgr_staffing_admin_screen_is_role_target($screen);
		$is_templates_page = bvmgr_staffing_admin_is_templates_page();

		if (!$is_role_screen && !$is_templates_page) {
			return;
		}

		$version = function_exists('bvmgr_asset_version')
			? bvmgr_asset_version()
			: (defined('BVMGR_VERSION') ? (string) BVMGR_VERSION : '');

		if ($is_templates_page) {
			wp_enqueue_style(
				'bvmgr-staffing-admin',
				BVMGR_PLUGIN_URL . 'assets/css/vms-staffing-admin.css',
				array(),
				$version
			);
		}

		wp_enqueue_script(
			'bvmgr-staffing-admin',
			BVMGR_PLUGIN_URL . 'assets/js/vms-staffing-admin.js',
			array(),
			$version,
			true
		);
	}
}
add_action('admin_enqueue_scripts', 'bvmgr_staffing_admin_enqueue_assets', 50);

if (!function_exists('bvmgr_staffing_admin_build_template_payload_from_post')) {
	function bvmgr_staffing_admin_build_template_payload_from_post(array $post_data): array
	{
		$payload = array(
			'template_id'                 => isset($post_data['vms_tpl_template_id']) ? absint($post_data['vms_tpl_template_id']) : 0,
			'name'                        => isset($post_data['vms_tpl_name']) ? sanitize_text_field((string) $post_data['vms_tpl_name']) : '',
			'scope_venue_id'              => isset($post_data['vms_tpl_scope_venue_id']) ? absint($post_data['vms_tpl_scope_venue_id']) : 0,
			'scope_day_of_week'           => isset($post_data['vms_tpl_scope_day_of_week']) ? sanitize_text_field((string) $post_data['vms_tpl_scope_day_of_week']) : '',
			'scope_event_type'            => isset($post_data['vms_tpl_scope_event_type']) ? sanitize_key((string) $post_data['vms_tpl_scope_event_type']) : '',
			'priority'                    => isset($post_data['vms_tpl_priority']) ? (int) $post_data['vms_tpl_priority'] : 100,
			'min_headcount'               => isset($post_data['vms_tpl_min_headcount']) ? sanitize_text_field((string) $post_data['vms_tpl_min_headcount']) : '',
			'max_headcount'               => isset($post_data['vms_tpl_max_headcount']) ? sanitize_text_field((string) $post_data['vms_tpl_max_headcount']) : '',
			'is_active'                   => !empty($post_data['vms_tpl_is_active']) ? 1 : 0,
			'auto_apply_on_event_create'  => !empty($post_data['vms_tpl_auto_apply']) ? 1 : 0,
			'slots'                       => array(),
		);

		$slot_rows = isset($post_data['vms_tpl_slots']) && is_array($post_data['vms_tpl_slots'])
			? (array) $post_data['vms_tpl_slots']
			: array();
		foreach ($slot_rows as $row) {
			if (!is_array($row)) continue;
			if (function_exists('bvmgr_staffing_template_normalize_slot_row')) {
				$normalized_row = bvmgr_staffing_template_normalize_slot_row($row);
				if (is_array($normalized_row)) {
					$payload['slots'][] = $normalized_row;
				}
				continue;
			}
			$payload['slots'][] = $row;
		}

		return $payload;
	}
}

if (!function_exists('bvmgr_staffing_admin_template_row_markup')) {
	function bvmgr_staffing_admin_template_row_markup($idx, array $slot = array()): string
	{
		$role_id = isset($slot['role_id']) ? absint($slot['role_id']) : 0;
		$base_headcount = isset($slot['base_headcount']) ? absint($slot['base_headcount']) : 1;
		if ($base_headcount <= 0) {
			$base_headcount = 1;
		}
		$activation_threshold = isset($slot['activation_threshold']) && $slot['activation_threshold'] !== null && $slot['activation_threshold'] !== '' ? max(0, (int) $slot['activation_threshold']) : 1;
		$mode = isset($slot['shift_time_mode']) ? sanitize_key((string) $slot['shift_time_mode']) : 'absolute';
		if (!in_array($mode, array('absolute', 'relative'), true)) {
			$mode = 'absolute';
		}
		$start = isset($slot['shift_start_local']) ? (string) $slot['shift_start_local'] : '';
		$end = isset($slot['shift_end_local']) ? (string) $slot['shift_end_local'] : '';
		$start_anchor_key = isset($slot['start_anchor_key']) ? sanitize_key((string) $slot['start_anchor_key']) : 'event_start';
		$end_anchor_key = isset($slot['end_anchor_key']) ? sanitize_key((string) $slot['end_anchor_key']) : 'event_end';
		$start_offset_minutes = isset($slot['start_offset_minutes']) ? (int) $slot['start_offset_minutes'] : 0;
		$end_offset_minutes = isset($slot['end_offset_minutes']) ? (int) $slot['end_offset_minutes'] : 0;
		$duration_minutes = isset($slot['duration_minutes']) && $slot['duration_minutes'] !== null ? (int) $slot['duration_minutes'] : '';
		$pay_type = isset($slot['pay_type']) ? sanitize_key((string) $slot['pay_type']) : 'inherit_role';
		if (!in_array($pay_type, array('inherit_role', 'hourly', 'flat', 'none'), true)) {
			$pay_type = 'inherit_role';
		}
		$pay_rate = isset($slot['pay_rate']) && $slot['pay_rate'] !== null ? (string) $slot['pay_rate'] : '';
		$is_optional = !empty($slot['is_optional']);
		$notes = isset($slot['notes']) ? (string) $slot['notes'] : '';
		$anchor_options = array(
			'event_start' => __('Event start', 'backstage-venue-manager'),
			'event_end'   => __('Event end', 'backstage-venue-manager'),
			'a1'          => __('Anchor 1', 'backstage-venue-manager'),
			'a2'          => __('Anchor 2', 'backstage-venue-manager'),
			'a3'          => __('Anchor 3', 'backstage-venue-manager'),
			'a4'          => __('Anchor 4', 'backstage-venue-manager'),
		);
		$field_names = array(
			'role_id'              => 'vms_tpl_slots[' . $idx . '][role_id]',
			'base_headcount'       => 'vms_tpl_slots[' . $idx . '][base_headcount]',
			'activation_threshold' => 'vms_tpl_slots[' . $idx . '][activation_threshold]',
			'shift_time_mode'      => 'vms_tpl_slots[' . $idx . '][shift_time_mode]',
			'shift_start_local'    => 'vms_tpl_slots[' . $idx . '][shift_start_local]',
			'shift_end_local'      => 'vms_tpl_slots[' . $idx . '][shift_end_local]',
			'start_anchor_key'     => 'vms_tpl_slots[' . $idx . '][start_anchor_key]',
			'start_offset_minutes' => 'vms_tpl_slots[' . $idx . '][start_offset_minutes]',
			'end_anchor_key'       => 'vms_tpl_slots[' . $idx . '][end_anchor_key]',
			'end_offset_minutes'   => 'vms_tpl_slots[' . $idx . '][end_offset_minutes]',
			'duration_minutes'     => 'vms_tpl_slots[' . $idx . '][duration_minutes]',
			'pay_type'             => 'vms_tpl_slots[' . $idx . '][pay_type]',
			'pay_rate'             => 'vms_tpl_slots[' . $idx . '][pay_rate]',
			'is_optional'          => 'vms_tpl_slots[' . $idx . '][is_optional]',
			'notes'                => 'vms_tpl_slots[' . $idx . '][notes]',
		);
		ob_start();
		?>
		<div class="vms-tpl-slot-row" data-vms-tpl-slot-row="1">
			<div class="vms-tpl-slot-card">
				<div class="vms-tpl-slot-card__row vms-tpl-slot-card__row--identity">
					<label>
						<span><?php esc_html_e('Role', 'backstage-venue-manager'); ?></span>
						<select name="<?php echo esc_attr($field_names['role_id']); ?>" data-vms-tpl-role-input="1">
							<option value="0"><?php esc_html_e('Select role', 'backstage-venue-manager'); ?></option>
							<?php
							echo wp_kses(
								bvmgr_staffing_admin_role_options_html($role_id),
								array(
									'option' => array(
										'value'    => true,
										'selected' => true,
									),
								)
							);
							?>
						</select>
					</label>
					<label>
						<span><?php esc_html_e('Staff needed', 'backstage-venue-manager'); ?></span>
						<input type="number" min="1" step="1" name="<?php echo esc_attr($field_names['base_headcount']); ?>" value="<?php echo esc_attr((string) $base_headcount); ?>" data-vms-tpl-headcount-input="1">
					</label>
					<label>
						<span><?php esc_html_e('Activate at attendance', 'backstage-venue-manager'); ?></span>
						<input type="number" min="0" step="1" name="<?php echo esc_attr($field_names['activation_threshold']); ?>" value="<?php echo esc_attr((string) $activation_threshold); ?>">
					</label>
					<label>
						<span><?php esc_html_e('Time mode', 'backstage-venue-manager'); ?></span>
						<select name="<?php echo esc_attr($field_names['shift_time_mode']); ?>" data-vms-tpl-time-mode-input="1">
							<option value="absolute" <?php selected($mode, 'absolute'); ?>><?php esc_html_e('Absolute', 'backstage-venue-manager'); ?></option>
							<option value="relative" <?php selected($mode, 'relative'); ?>><?php esc_html_e('Relative', 'backstage-venue-manager'); ?></option>
						</select>
					</label>
				</div>

				<div class="vms-tpl-slot-card__row vms-tpl-slot-card__row--timing">
					<label data-vms-tpl-absolute-field="1">
						<span><?php esc_html_e('Shift start', 'backstage-venue-manager'); ?></span>
						<input type="time" name="<?php echo esc_attr($field_names['shift_start_local']); ?>" value="<?php echo esc_attr($start); ?>" data-vms-tpl-shift-start-input="1">
					</label>
					<label data-vms-tpl-absolute-field="1" data-vms-tpl-end-field="1">
						<span><?php esc_html_e('Shift end', 'backstage-venue-manager'); ?></span>
						<input type="time" name="<?php echo esc_attr($field_names['shift_end_local']); ?>" value="<?php echo esc_attr($end); ?>" data-vms-tpl-shift-end-input="1">
					</label>
					<label data-vms-tpl-relative-field="1">
						<span><?php esc_html_e('Start anchor', 'backstage-venue-manager'); ?></span>
						<select name="<?php echo esc_attr($field_names['start_anchor_key']); ?>" data-vms-tpl-start-anchor-input="1">
							<?php foreach ($anchor_options as $anchor_key => $anchor_label) : ?>
								<option value="<?php echo esc_attr($anchor_key); ?>" <?php selected($start_anchor_key, $anchor_key); ?>><?php echo esc_html($anchor_label); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label data-vms-tpl-relative-field="1">
						<span><?php esc_html_e('Start offset (min)', 'backstage-venue-manager'); ?></span>
						<input type="number" step="1" name="<?php echo esc_attr($field_names['start_offset_minutes']); ?>" value="<?php echo esc_attr((string) $start_offset_minutes); ?>" data-vms-tpl-start-offset-input="1">
					</label>
					<label data-vms-tpl-relative-field="1" data-vms-tpl-end-field="1">
						<span><?php esc_html_e('End anchor', 'backstage-venue-manager'); ?></span>
						<select name="<?php echo esc_attr($field_names['end_anchor_key']); ?>" data-vms-tpl-end-anchor-input="1">
							<?php foreach ($anchor_options as $anchor_key => $anchor_label) : ?>
								<option value="<?php echo esc_attr($anchor_key); ?>" <?php selected($end_anchor_key, $anchor_key); ?>><?php echo esc_html($anchor_label); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label data-vms-tpl-relative-field="1" data-vms-tpl-end-field="1">
						<span><?php esc_html_e('End offset (min)', 'backstage-venue-manager'); ?></span>
						<input type="number" step="1" name="<?php echo esc_attr($field_names['end_offset_minutes']); ?>" value="<?php echo esc_attr((string) $end_offset_minutes); ?>" data-vms-tpl-end-offset-input="1">
					</label>
					<label data-vms-tpl-duration-field="1">
						<span><?php esc_html_e('Duration (min)', 'backstage-venue-manager'); ?></span>
						<input type="number" min="0" step="1" name="<?php echo esc_attr($field_names['duration_minutes']); ?>" value="<?php echo esc_attr((string) $duration_minutes); ?>" data-vms-tpl-duration-input="1">
					</label>
				</div>

				<p class="description vms-tpl-slot-card__help"><?php esc_html_e('Set staff needed, the attendance trigger for this role, and the shift timing. Absolute mode uses Shift start plus Shift end or Duration. Relative mode uses start anchor/offset plus End anchor/offset or Duration.', 'backstage-venue-manager'); ?></p>
				<div class="vms-ep-inline-warning vms-hidden" data-vms-tpl-absolute-warning>
					<?php esc_html_e('Absolute time mode requires Shift start plus Shift end or Duration when this slot is in use.', 'backstage-venue-manager'); ?>
				</div>

				<div class="vms-tpl-slot-card__row vms-tpl-slot-card__row--pay">
					<label>
						<span><?php esc_html_e('Pay type', 'backstage-venue-manager'); ?></span>
						<select name="<?php echo esc_attr($field_names['pay_type']); ?>">
							<option value="inherit_role" <?php selected($pay_type, 'inherit_role'); ?>><?php esc_html_e('Inherit role', 'backstage-venue-manager'); ?></option>
							<option value="hourly" <?php selected($pay_type, 'hourly'); ?>><?php esc_html_e('Hourly', 'backstage-venue-manager'); ?></option>
							<option value="flat" <?php selected($pay_type, 'flat'); ?>><?php esc_html_e('Flat', 'backstage-venue-manager'); ?></option>
							<option value="none" <?php selected($pay_type, 'none'); ?>><?php esc_html_e('None', 'backstage-venue-manager'); ?></option>
						</select>
					</label>
					<label>
						<span><?php esc_html_e('Rate', 'backstage-venue-manager'); ?></span>
						<input type="number" min="0" step="0.01" name="<?php echo esc_attr($field_names['pay_rate']); ?>" value="<?php echo esc_attr($pay_rate); ?>">
					</label>
					<label class="vms-tpl-slot-card__optional">
						<span><?php esc_html_e('Optional', 'backstage-venue-manager'); ?></span>
						<span class="vms-tpl-slot-card__optional-check"><input type="checkbox" name="<?php echo esc_attr($field_names['is_optional']); ?>" value="1" <?php checked($is_optional); ?>> <?php esc_html_e('Optional slot', 'backstage-venue-manager'); ?></span>
					</label>
					<label class="vms-tpl-slot-card__notes">
						<span><?php esc_html_e('Notes', 'backstage-venue-manager'); ?></span>
						<input type="text" class="regular-text" name="<?php echo esc_attr($field_names['notes']); ?>" value="<?php echo esc_attr($notes); ?>">
					</label>
					<div class="vms-tpl-slot-card__actions">
						<button type="button" class="button vms-tpl-remove-row"><?php esc_html_e('Remove', 'backstage-venue-manager'); ?></button>
					</div>
				</div>
			</div>
		</div>
		<?php
		return trim((string) ob_get_clean());
	}
}

if (!function_exists('bvmgr_staffing_admin_render_template_row')) {
	function bvmgr_staffing_admin_render_template_row(int $idx, array $slot = array()): void
	{
		echo wp_kses(
			bvmgr_staffing_admin_template_row_markup($idx, $slot),
			array(
				'div'    => array(
					'class'                         => true,
					'data-vms-tpl-slot-row'        => true,
					'data-vms-tpl-absolute-warning' => true,
				),
				'label'  => array(
					'class'                    => true,
					'data-vms-tpl-absolute-field' => true,
					'data-vms-tpl-relative-field' => true,
					'data-vms-tpl-end-field'      => true,
				),
				'span'   => array('class' => true),
				'p'      => array('class' => true),
				'select' => array(
					'name'                        => true,
					'data-vms-tpl-role-input'     => true,
					'data-vms-tpl-time-mode-input' => true,
					'data-vms-tpl-start-anchor-input' => true,
					'data-vms-tpl-end-anchor-input'   => true,
				),
				'option' => array(
					'value'    => true,
					'selected' => true,
				),
				'input'  => array(
					'type'                          => true,
					'class'                         => true,
					'name'                          => true,
					'value'                         => true,
					'placeholder'                   => true,
					'min'                           => true,
					'step'                          => true,
					'checked'                       => true,
					'data-vms-tpl-headcount-input'  => true,
					'data-vms-tpl-shift-start-input' => true,
					'data-vms-tpl-shift-end-input'   => true,
					'data-vms-tpl-start-offset-input' => true,
					'data-vms-tpl-end-offset-input'   => true,
					'data-vms-tpl-duration-input'     => true,
				),
				'button' => array(
					'type'  => true,
					'class' => true,
				),
			)
		);
	}
}

if (!function_exists('bvmgr_staffing_admin_render_templates_page')) {
	function bvmgr_staffing_admin_render_templates_page(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Insufficient permissions.', 'backstage-venue-manager'));
		}

		$request_method = bvmgr_staffing_admin_request_method();
		$post_data = 'POST' === $request_method ? wp_unslash($_POST) : array();
		$post_action = isset($post_data['vms_tpl_action']) ? sanitize_key((string) $post_data['vms_tpl_action']) : '';

		if ('POST' === $request_method && 'save' === $post_action) {
			check_admin_referer('vms_staffing_template_save');
			$payload = bvmgr_staffing_admin_build_template_payload_from_post($post_data);
			$res = function_exists('bvmgr_staffing_save_template') ? bvmgr_staffing_save_template($payload, get_current_user_id()) : array('ok' => false, 'error' => 'core_missing');
			$next = admin_url('admin.php?page=vms-staffing-templates');
			if (!empty($res['ok'])) {
				$next = add_query_arg(array(
					'template_id' => (int) $res['template_id'],
					'saved'       => 1,
				), $next);
			} else {
				$next = add_query_arg(array(
					'error' => isset($res['error']) ? (string) $res['error'] : 'save_failed',
				), $next);
			}
			wp_safe_redirect($next);
			exit;
		}

		if ('POST' === $request_method && 'delete' === $post_action) {
			check_admin_referer('vms_staffing_template_delete');
			$template_id = isset($post_data['vms_tpl_template_id']) ? absint($post_data['vms_tpl_template_id']) : 0;
			$ok = $template_id > 0 && function_exists('bvmgr_staffing_delete_template') ? bvmgr_staffing_delete_template($template_id, get_current_user_id()) : false;
			$next = admin_url('admin.php?page=vms-staffing-templates');
			$next = add_query_arg(array('deleted' => $ok ? 1 : 0), $next);
			wp_safe_redirect($next);
			exit;
		}

		$templates = function_exists('bvmgr_staffing_get_templates') ? bvmgr_staffing_get_templates() : array();
		$template_id = isset($_GET['template_id']) ? absint($_GET['template_id']) : 0;
		$current = $template_id > 0 && function_exists('bvmgr_staffing_get_template') ? bvmgr_staffing_get_template($template_id) : null;
		$current_slots = is_array($current) && isset($current['slots']) && is_array($current['slots']) ? $current['slots'] : array();
		if (empty($current_slots)) {
			$current_slots = array(array());
		}
		$venues = bvmgr_staffing_admin_get_venues();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__('Staffing Templates', 'backstage-venue-manager') . '</h1>';
		echo '<p class="description">' . esc_html__('Templates define reusable role slots seeded into Event Plans, with optional template-wide guest bands and per-role attendance triggers.', 'backstage-venue-manager') . '</p>';

		if (!empty($_GET['saved'])) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Template saved.', 'backstage-venue-manager') . '</p></div>';
		}
		if (isset($_GET['deleted'])) {
			if ((int) $_GET['deleted'] === 1) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Template deleted.', 'backstage-venue-manager') . '</p></div>';
			} else {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Template delete failed.', 'backstage-venue-manager') . '</p></div>';
			}
		}
		if (!empty($_GET['error'])) {
			/* translators: %s: sanitized template save error code. */
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(sprintf(__('Template save failed: %s', 'backstage-venue-manager'), sanitize_text_field((string) wp_unslash($_GET['error'])))) . '</p></div>';
		}

		echo '<h2>' . esc_html__('Existing Templates', 'backstage-venue-manager') . '</h2>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__('Name', 'backstage-venue-manager') . '</th>';
		echo '<th>' . esc_html__('Scope', 'backstage-venue-manager') . '</th>';
		echo '<th>' . esc_html__('Priority', 'backstage-venue-manager') . '</th>';
		echo '<th>' . esc_html__('Status', 'backstage-venue-manager') . '</th>';
		echo '<th>' . esc_html__('Actions', 'backstage-venue-manager') . '</th>';
		echo '</tr></thead><tbody>';
		if (empty($templates)) {
			echo '<tr><td colspan="5">' . esc_html__('No templates yet.', 'backstage-venue-manager') . '</td></tr>';
		} else {
			foreach ($templates as $tpl) {
				$tid = isset($tpl['template_id']) ? absint($tpl['template_id']) : 0;
				if ($tid <= 0) continue;
				$name = isset($tpl['name']) ? (string) $tpl['name'] : ('#' . $tid);
				$scope_parts = array();
				$sv = isset($tpl['scope_venue_id']) ? absint($tpl['scope_venue_id']) : 0;
				if ($sv > 0) {
					/* translators: %d: venue post ID assigned to the staffing template scope. */
					$scope_parts[] = sprintf(__('Venue #%d', 'backstage-venue-manager'), $sv);
				}
				$sd = isset($tpl['scope_day_of_week']) && $tpl['scope_day_of_week'] !== null ? (int) $tpl['scope_day_of_week'] : null;
				if ($sd !== null && $sd >= 0 && $sd <= 6) {
					$dow = array(__('Sun', 'backstage-venue-manager'), __('Mon', 'backstage-venue-manager'), __('Tue', 'backstage-venue-manager'), __('Wed', 'backstage-venue-manager'), __('Thu', 'backstage-venue-manager'), __('Fri', 'backstage-venue-manager'), __('Sat', 'backstage-venue-manager'));
					$scope_parts[] = $dow[$sd];
				}
				$st = isset($tpl['scope_event_type']) ? sanitize_key((string) $tpl['scope_event_type']) : '';
				if ($st !== '') {
					/* translators: %s: staffing template event type key. */
					$scope_parts[] = sprintf(__('Type: %s', 'backstage-venue-manager'), $st);
				}
				$min_headcount = (isset($tpl['min_headcount']) && $tpl['min_headcount'] !== null && $tpl['min_headcount'] !== '') ? max(0, (int) $tpl['min_headcount']) : null;
				$max_headcount = (isset($tpl['max_headcount']) && $tpl['max_headcount'] !== null && $tpl['max_headcount'] !== '') ? max(0, (int) $tpl['max_headcount']) : null;
				if ($min_headcount !== null || $max_headcount !== null) {
					/* translators: %s: attendance range for the staffing template. */
					$scope_parts[] = sprintf(__('Attendance: %s', 'backstage-venue-manager'), ($min_headcount !== null ? (string) $min_headcount : '0') . '–' . ($max_headcount !== null ? (string) $max_headcount : '∞'));
				}
				if (empty($scope_parts)) $scope_parts[] = __('Any', 'backstage-venue-manager');
				$edit_url = add_query_arg(array('page' => 'vms-staffing-templates', 'template_id' => $tid), admin_url('admin.php'));
				echo '<tr>';
				echo '<td>' . esc_html($name) . '</td>';
				echo '<td>' . esc_html(implode(' · ', $scope_parts)) . '</td>';
				echo '<td>' . esc_html((string) (isset($tpl['priority']) ? (int) $tpl['priority'] : 0)) . '</td>';
				echo '<td>' . (!empty($tpl['is_active']) ? esc_html__('Active', 'backstage-venue-manager') : esc_html__('Inactive', 'backstage-venue-manager')) . '</td>';
				echo '<td><a class="button button-small" href="' . esc_url($edit_url) . '">' . esc_html__('Edit', 'backstage-venue-manager') . '</a></td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';

		$title = is_array($current) ? __('Edit Template', 'backstage-venue-manager') : __('New Template', 'backstage-venue-manager');
		echo '<hr>';
		echo '<h2>' . esc_html($title) . '</h2>';
		echo '<form method="post">';
		wp_nonce_field('vms_staffing_template_save');
		echo '<input type="hidden" name="vms_tpl_action" value="save">';
		echo '<input type="hidden" name="vms_tpl_template_id" value="' . esc_attr((string) (is_array($current) ? absint($current['template_id']) : 0)) . '">';
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th><label for="vms_tpl_name">' . esc_html__('Template name', 'backstage-venue-manager') . '</label></th><td><input class="regular-text" id="vms_tpl_name" name="vms_tpl_name" value="' . esc_attr(is_array($current) ? (string) $current['name'] : '') . '" required></td></tr>';
		echo '<tr><th><label for="vms_tpl_priority">' . esc_html__('Priority', 'backstage-venue-manager') . '</label></th><td><input type="number" id="vms_tpl_priority" name="vms_tpl_priority" value="' . esc_attr((string) (is_array($current) ? (int) $current['priority'] : 100)) . '"><p class="description">' . esc_html__('Higher priority wins when multiple templates match.', 'backstage-venue-manager') . '</p></td></tr>';
		echo '<tr><th>' . esc_html__('Attendance band', 'backstage-venue-manager') . '</th><td><label>' . esc_html__('Min', 'backstage-venue-manager') . ' <input type="number" min="0" step="1" name="vms_tpl_min_headcount" value="' . esc_attr(is_array($current) && isset($current['min_headcount']) && $current['min_headcount'] !== null ? (string) (int) $current['min_headcount'] : '') . '"></label> &nbsp; <label>' . esc_html__('Max', 'backstage-venue-manager') . ' <input type="number" min="0" step="1" name="vms_tpl_max_headcount" value="' . esc_attr(is_array($current) && isset($current['max_headcount']) && $current['max_headcount'] !== null ? (string) (int) $current['max_headcount'] : '') . '"></label><p class="description">' . esc_html__('Optional. Leave blank to match any attendance. Blank max means no ceiling.', 'backstage-venue-manager') . '</p></td></tr>';
		echo '<tr><th>' . esc_html__('Scope venue', 'backstage-venue-manager') . '</th><td><select name="vms_tpl_scope_venue_id"><option value="0">' . esc_html__('Any venue', 'backstage-venue-manager') . '</option>';
		$sel_venue = is_array($current) ? absint($current['scope_venue_id']) : 0;
		foreach ($venues as $venue) {
			$vid = isset($venue->ID) ? absint($venue->ID) : 0;
			if ($vid <= 0) continue;
			echo '<option value="' . esc_attr((string) $vid) . '" ' . selected($sel_venue, $vid, false) . '>' . esc_html((string) get_the_title($vid)) . '</option>';
		}
		echo '</select></td></tr>';
		$sel_dow = is_array($current) && $current['scope_day_of_week'] !== null ? (string) (int) $current['scope_day_of_week'] : '';
		$dow = array(__('Sun', 'backstage-venue-manager'), __('Mon', 'backstage-venue-manager'), __('Tue', 'backstage-venue-manager'), __('Wed', 'backstage-venue-manager'), __('Thu', 'backstage-venue-manager'), __('Fri', 'backstage-venue-manager'), __('Sat', 'backstage-venue-manager'));
		echo '<tr><th>' . esc_html__('Scope day-of-week', 'backstage-venue-manager') . '</th><td><select name="vms_tpl_scope_day_of_week"><option value="">' . esc_html__('Any day', 'backstage-venue-manager') . '</option>';
		foreach ($dow as $i => $label) {
			echo '<option value="' . esc_attr((string) $i) . '" ' . selected($sel_dow, (string) $i, false) . '>' . esc_html($label) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th><label for="vms_tpl_scope_event_type">' . esc_html__('Scope event type', 'backstage-venue-manager') . '</label></th><td><input class="regular-text" id="vms_tpl_scope_event_type" name="vms_tpl_scope_event_type" value="' . esc_attr(is_array($current) ? (string) $current['scope_event_type'] : '') . '"><p class="description">' . esc_html__('Optional key (leave blank for any).', 'backstage-venue-manager') . '</p></td></tr>';
		echo '<tr><th>' . esc_html__('Status', 'backstage-venue-manager') . '</th><td><label><input type="checkbox" name="vms_tpl_is_active" value="1" ' . checked(is_array($current) ? !empty($current['is_active']) : true, true, false) . '> ' . esc_html__('Active', 'backstage-venue-manager') . '</label> &nbsp; <label><input type="checkbox" name="vms_tpl_auto_apply" value="1" ' . checked(is_array($current) ? !empty($current['auto_apply_on_event_create']) : true, true, false) . '> ' . esc_html__('Auto-apply on Event Plan create', 'backstage-venue-manager') . '</label></td></tr>';
		echo '</table>';

		echo '<h3>' . esc_html__('Template Role Slots', 'backstage-venue-manager') . '</h3>';
		echo '<p class="description">' . esc_html__('Template slot cards mirror the Event Plan timing controls so the visible fields match the selected Time mode.', 'backstage-venue-manager') . '</p>';
		echo '<div id="vms-tpl-slots">';
		$idx = 0;
		foreach ($current_slots as $slot) {
			bvmgr_staffing_admin_render_template_row($idx, is_array($slot) ? $slot : array());
			$idx++;
		}
		echo '</div>';
		echo '<p><button type="button" class="button" id="vms-tpl-add-row">' . esc_html__('Add Slot', 'backstage-venue-manager') . '</button></p>';
		echo '<p><button type="submit" class="button button-primary">' . esc_html__('Save Template', 'backstage-venue-manager') . '</button></p>';
		echo '</form>';

		if (is_array($current) && !empty($current['template_id'])) {
			echo '<form method="post" onsubmit="return confirm(\'' . esc_js(__('Delete this template?', 'backstage-venue-manager')) . '\');">';
			wp_nonce_field('vms_staffing_template_delete');
			echo '<input type="hidden" name="vms_tpl_action" value="delete">';
			echo '<input type="hidden" name="vms_tpl_template_id" value="' . esc_attr((string) absint($current['template_id'])) . '">';
			echo '<p><button type="submit" class="button button-secondary">' . esc_html__('Delete Template', 'backstage-venue-manager') . '</button></p>';
			echo '</form>';
		}

		$template_row_template = bvmgr_staffing_admin_template_row_markup('__INDEX__', array());
		echo '<template id="vms-tpl-slot-row-template">' . $template_row_template . '</template>';

		echo '</div>';
	}
}

if (!function_exists('bvmgr_staffing_admin_render_rollups_page')) {
	function bvmgr_staffing_admin_render_rollups_page(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Insufficient permissions.', 'backstage-venue-manager'));
		}

		$result = null;
		$preview = true;
		$filters = array(
			'start_date'        => '',
			'end_date'          => '',
			'venue_id'          => 0,
			'include_drafts'    => 0,
			'include_cancelled' => 0,
		);

		$request_method = bvmgr_staffing_admin_request_method();
		if ('POST' === $request_method) {
			check_admin_referer('vms_staffing_rollups_run');
			$post_data = wp_unslash($_POST);
			$filters = array(
				'start_date'        => isset($post_data['vms_staffing_start_date']) ? sanitize_text_field((string) $post_data['vms_staffing_start_date']) : '',
				'end_date'          => isset($post_data['vms_staffing_end_date']) ? sanitize_text_field((string) $post_data['vms_staffing_end_date']) : '',
				'venue_id'          => isset($post_data['vms_staffing_venue_id']) ? absint($post_data['vms_staffing_venue_id']) : 0,
				'include_drafts'    => !empty($post_data['vms_staffing_include_drafts']) ? 1 : 0,
				'include_cancelled' => !empty($post_data['vms_staffing_include_cancelled']) ? 1 : 0,
			);
			$action = isset($post_data['vms_staffing_rollup_action']) ? sanitize_key((string) $post_data['vms_staffing_rollup_action']) : 'preview';
			$preview = ($action !== 'run');
			$result = function_exists('bvmgr_staffing_rebuild_rollups')
				? bvmgr_staffing_rebuild_rollups($filters, $preview)
				: array('preview' => 1, 'matched_count' => 0, 'error_count' => 1, 'errors' => array(array('error' => 'core_missing')));
		}

		global $wpdb;
		$t_roll = function_exists('bvmgr_staffing_table_name') ? bvmgr_staffing_table_name('rollups') : '';
		$dirty_count = 0;
		if ($t_roll !== '') {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Rollup dirty-count reads the plugin-owned rollups repository with a %i/%d-prepared identifier and filter, and the admin page must show request-fresh state after rebuild and dirty-flag mutations.
			$dirty_count = (int) $wpdb->get_var(
				$wpdb->prepare('SELECT COUNT(*) FROM %i WHERE dirty = %d', $t_roll, 1)
			);
		}

		$venues = bvmgr_staffing_admin_get_venues();
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__('Staffing Rollups', 'backstage-venue-manager') . '</h1>';
		echo '<p class="description">' . esc_html__('Rebuild staffing readiness cache by date/venue/status using Preview → Run.', 'backstage-venue-manager') . '</p>';
		echo '<p><strong>' . esc_html__('Dirty rollups:', 'backstage-venue-manager') . '</strong> ' . esc_html((string) $dirty_count) . '</p>';

		echo '<form method="post">';
		wp_nonce_field('vms_staffing_rollups_run');
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th><label for="vms_staffing_start_date">' . esc_html__('Start date', 'backstage-venue-manager') . '</label></th><td><input type="date" id="vms_staffing_start_date" name="vms_staffing_start_date" value="' . esc_attr((string) $filters['start_date']) . '"></td></tr>';
		echo '<tr><th><label for="vms_staffing_end_date">' . esc_html__('End date', 'backstage-venue-manager') . '</label></th><td><input type="date" id="vms_staffing_end_date" name="vms_staffing_end_date" value="' . esc_attr((string) $filters['end_date']) . '"></td></tr>';
		echo '<tr><th><label for="vms_staffing_venue_id">' . esc_html__('Venue', 'backstage-venue-manager') . '</label></th><td><select id="vms_staffing_venue_id" name="vms_staffing_venue_id"><option value="0">' . esc_html__('All venues', 'backstage-venue-manager') . '</option>';
		foreach ($venues as $venue) {
			$vid = isset($venue->ID) ? absint($venue->ID) : 0;
			if ($vid <= 0) continue;
			echo '<option value="' . esc_attr((string) $vid) . '" ' . selected((int) $filters['venue_id'], $vid, false) . '>' . esc_html((string) get_the_title($vid)) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th>' . esc_html__('Inclusion', 'backstage-venue-manager') . '</th><td>';
		echo '<label><input type="checkbox" name="vms_staffing_include_drafts" value="1" ' . checked(!empty($filters['include_drafts']), true, false) . '> ' . esc_html__('Include Draft/Ready/Tentative/Confirmed', 'backstage-venue-manager') . '</label><br>';
		echo '<label><input type="checkbox" name="vms_staffing_include_cancelled" value="1" ' . checked(!empty($filters['include_cancelled']), true, false) . '> ' . esc_html__('Include Cancelled', 'backstage-venue-manager') . '</label>';
		echo '</td></tr>';
		echo '</table>';
		echo '<p>';
		echo '<button type="submit" name="vms_staffing_rollup_action" value="preview" class="button">' . esc_html__('Preview', 'backstage-venue-manager') . '</button> ';
		echo '<button type="submit" name="vms_staffing_rollup_action" value="run" class="button button-primary">' . esc_html__('Run Rebuild', 'backstage-venue-manager') . '</button>';
		echo '</p>';
		echo '</form>';

		if (is_array($result)) {
			echo '<hr><h2>' . esc_html(!empty($result['preview']) ? __('Preview Result', 'backstage-venue-manager') : __('Run Result', 'backstage-venue-manager')) . '</h2>';
			echo '<p><strong>' . esc_html__('Run ID:', 'backstage-venue-manager') . '</strong> ' . esc_html((string) ($result['run_id'] ?? '')) . '</p>';
			echo '<ul>';
			/* translators: %d: number of event plans matched by the rollup filters. */
			echo '<li>' . esc_html(sprintf(__('Matched events: %d', 'backstage-venue-manager'), (int) ($result['matched_count'] ?? 0))) . '</li>';
			/* translators: %d: number of staffing rollups rebuilt in this run. */
			echo '<li>' . esc_html(sprintf(__('Rebuilt: %d', 'backstage-venue-manager'), (int) ($result['rebuilt_count'] ?? 0))) . '</li>';
			/* translators: %d: number of staffing rollup rebuild errors. */
			echo '<li>' . esc_html(sprintf(__('Errors: %d', 'backstage-venue-manager'), (int) ($result['error_count'] ?? 0))) . '</li>';
			echo '</ul>';
			$errors = isset($result['errors']) && is_array($result['errors']) ? $result['errors'] : array();
			if (!empty($errors)) {
				echo '<h3>' . esc_html__('Errors', 'backstage-venue-manager') . '</h3>';
				echo '<pre>' . esc_html(wp_json_encode($errors, JSON_PRETTY_PRINT)) . '</pre>';
			}
		}

		echo '</div>';
	}
}
