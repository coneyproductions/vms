<?php
if (!defined('ABSPATH')) exit;

/**
 * VMS – Staff Employment Type (Admin)
 *
 * Goal: support both W-2 employees and 1099/W-9 contractors without per-user settings.
 *
 * Storage:
 * - Staff post_meta: vms_meta_key('staff','worker_type') fallback '_vms_staff_worker_type'
 *   Values: 'contractor' (default) | 'employee'
 */

function vms_staff_worker_type_meta_key(): string
{
	if (function_exists('vms_meta_key')) {
		$k = (string) vms_meta_key('staff', 'worker_type');
		if ($k !== '') return $k;
	}
	return '_vms_staff_worker_type';
}

function vms_staff_get_worker_type(int $staff_id): string
{
	$staff_id = (int) $staff_id;
	$raw = (string) get_post_meta($staff_id, vms_staff_worker_type_meta_key(), true);
	$raw = sanitize_key($raw);
	return in_array($raw, array('employee', 'contractor'), true) ? $raw : 'contractor';
}

add_action('add_meta_boxes', function () {
	add_meta_box(
		'vms_staff_worker_type',
		__('Employment Type', 'vms'),
		'vms_render_staff_worker_type_metabox',
		'vms_staff',
		'side',
		'default'
	);
});

function vms_render_staff_worker_type_metabox($post): void
{
	if (!($post instanceof WP_Post)) return;

	$staff_id = (int) $post->ID;
	$current = vms_staff_get_worker_type($staff_id);

	wp_nonce_field('vms_staff_worker_type_save', 'vms_staff_worker_type_nonce');

	echo '<p class="description">' . esc_html__('Choose how this person is paid for compliance flows.', 'vms') . '</p>';

	echo '<select name="vms_staff_worker_type" style="width:100%;">';
	echo '<option value="contractor" ' . selected($current, 'contractor', false) . '>' . esc_html__('Contractor (1099 / W-9)', 'vms') . '</option>';
	echo '<option value="employee" ' . selected($current, 'employee', false) . '>' . esc_html__('Employee (W-2)', 'vms') . '</option>';
	echo '</select>';

	if ($current === 'employee') {
		echo '<p class="description" style="margin-top:10px;">' .
			esc_html__('W-9 is not required for W-2 employees. Do not upload W-4/I-9 into WordPress.', 'vms') .
			'</p>';
	} else {
		echo '<p class="description" style="margin-top:10px;">' .
			esc_html__('1099 contractors must complete a Tax Profile (W-9 upload or off-site workflow).', 'vms') .
			'</p>';
	}
}

add_action('save_post_vms_staff', function (int $post_id, WP_Post $post, bool $update): void {
	// Avoid autosaves / revisions
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (wp_is_post_revision($post_id)) return;
	if (!current_user_can('edit_post', $post_id)) return;

	if (!isset($_POST['vms_staff_worker_type_nonce']) || !wp_verify_nonce((string) $_POST['vms_staff_worker_type_nonce'], 'vms_staff_worker_type_save')) {
		return;
	}

	$raw = isset($_POST['vms_staff_worker_type']) ? sanitize_key((string) $_POST['vms_staff_worker_type']) : 'contractor';
	$type = in_array($raw, array('employee', 'contractor'), true) ? $raw : 'contractor';

	update_post_meta($post_id, vms_staff_worker_type_meta_key(), $type);
}, 20, 3);
