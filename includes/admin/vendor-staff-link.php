<?php
if (!defined('ABSPATH')) exit;

/**
 * VMS – Vendor ↔ Staff (Dual-hat) Linking (Admin)
 *
 * Why:
 * - Some people are operational staff (need Staff Roles + Ops Console access)
 * - The same person may also be paid/managed as a Vendor (1099 contractor profile)
 *
 * We keep Vendor and Staff concepts separate, and link them 1:1 when needed.
 */

function bvmgr_vendor_linked_staff_meta_key(): string
{
	if (function_exists('bvmgr_meta_key')) {
		$k = (string) bvmgr_meta_key('vendor', 'linked_staff_id');
		if ($k !== '') return $k;
	}
	return '_vms_linked_staff_id';
}

function bvmgr_staff_linked_vendor_meta_key(): string
{
	if (function_exists('bvmgr_meta_key')) {
		$k = (string) bvmgr_meta_key('staff', 'linked_vendor_id');
		if ($k !== '') return $k;
	}
	return '_vms_linked_vendor_id';
}

add_action('add_meta_boxes', function (): void {
	add_meta_box(
		'vms_vendor_staff_link',
		__('Also Works as Staff', 'backstage-venue-manager'),
		'bvmgr_vendor_staff_link_metabox_render',
		'vms_vendor',
		'side',
		'default'
	);
});

function bvmgr_vendor_staff_link_metabox_render($post): void
{
	if (!($post instanceof WP_Post)) return;

	$vendor_id = (int) $post->ID;
	$current_staff_id = (int) get_post_meta($vendor_id, bvmgr_vendor_linked_staff_meta_key(), true);

	wp_nonce_field('vms_vendor_staff_link_save', 'vms_vendor_staff_link_nonce');

	echo '<p class="description">' . esc_html__('Link this vendor to a Staff profile so they can be assigned Staff Roles and use staff-only console areas.', 'backstage-venue-manager') . '</p>';

	$staff_posts = get_posts(array(
		'post_type'      => 'vms_staff',
		'post_status'    => 'any',
		'numberposts'    => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'no_found_rows'  => true,
		'fields'         => 'ids',
	));

	echo '<select name="vms_linked_staff_id" style="width:100%;">';
	echo '<option value="0">— ' . esc_html__('Not linked', 'backstage-venue-manager') . ' —</option>';

	foreach ($staff_posts as $sid) {
		$sid = (int) $sid;
		if ($sid <= 0) continue;
		$title = (string) get_the_title($sid);
		if ($title === '') $title = 'Staff #' . (string) $sid;
		printf(
			'<option value="%d" %s>%s</option>',
			$sid,
			selected($current_staff_id, $sid, false),
			esc_html($title)
		);
	}

	echo '</select>';

	if ($current_staff_id > 0) {
		$edit_staff = get_edit_post_link($current_staff_id, '');
		if ($edit_staff) {
			echo '<p style="margin-top:10px;">';
			echo '<a class="button button-secondary" href="' . esc_url($edit_staff) . '">' . esc_html__('Edit linked staff', 'backstage-venue-manager') . '</a>';
			echo '</p>';
		}
	} else {
		$action_url = admin_url('admin-post.php?action=vms_create_staff_from_vendor&vendor_id=' . (string) $vendor_id);
		$action_url = wp_nonce_url($action_url, 'vms_create_staff_from_vendor_' . (string) $vendor_id);

		echo '<p style="margin-top:10px;">';
		echo '<a class="button button-primary" href="' . esc_url($action_url) . '">' . esc_html__('Create staff profile from this vendor', 'backstage-venue-manager') . '</a>';
		echo '</p>';
	}

	echo '<p class="description" style="margin-top:10px;">' .
		esc_html__('Tip: Staff Roles live on the Staff profile (Bar, Ticket Checker, Cleanup, etc). Vendor category (music vendor, food vendor, contractor) stays on the Vendor.', 'backstage-venue-manager') .
		'</p>';
}

add_action('save_post_vms_vendor', function (int $post_id, WP_Post $post, bool $update): void {
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (wp_is_post_revision($post_id)) return;
	if (!current_user_can('edit_post', $post_id)) return;

	$nonce = (isset($_POST['vms_vendor_staff_link_nonce']) && !is_array($_POST['vms_vendor_staff_link_nonce']))
		? sanitize_text_field(wp_unslash((string) $_POST['vms_vendor_staff_link_nonce']))
		: '';
	if ($nonce === '' || !wp_verify_nonce($nonce, 'vms_vendor_staff_link_save')) {
		return;
	}

	$vendor_id = (int) $post_id;
	$k_vendor_staff = bvmgr_vendor_linked_staff_meta_key();
	$k_staff_vendor = bvmgr_staff_linked_vendor_meta_key();

	$old_staff_id = (int) get_post_meta($vendor_id, $k_vendor_staff, true);
	$new_staff_id = isset($_POST['vms_linked_staff_id']) ? (int) $_POST['vms_linked_staff_id'] : 0;
	if ($new_staff_id < 0) $new_staff_id = 0;

	// Normalize: only allow real staff posts.
	if ($new_staff_id > 0) {
		$sp = get_post($new_staff_id);
		if (!$sp || $sp->post_type !== 'vms_staff') {
			$new_staff_id = 0;
		}
	}

	if ($old_staff_id === $new_staff_id) {
		return;
	}

	// Unlink previous reverse pointer.
	if ($old_staff_id > 0) {
		$linked_vendor_now = (int) get_post_meta($old_staff_id, $k_staff_vendor, true);
		if ($linked_vendor_now === $vendor_id) {
			delete_post_meta($old_staff_id, $k_staff_vendor);
		}
	}

	if ($new_staff_id <= 0) {
		delete_post_meta($vendor_id, $k_vendor_staff);
		return;
	}

	// Enforce uniqueness: if another vendor already links to this staff, unlink it.
	global $wpdb;
	$other_vendor_ids = array();
	if (is_object($wpdb) && method_exists($wpdb, 'get_col') && method_exists($wpdb, 'prepare')) {
		$t_posts = (isset($wpdb->posts) && is_string($wpdb->posts) && $wpdb->posts !== '') ? $wpdb->posts : '';
		$t_postmeta = (isset($wpdb->postmeta) && is_string($wpdb->postmeta) && $wpdb->postmeta !== '') ? $wpdb->postmeta : '';
		if ($t_posts !== '' && $t_postmeta !== '') {
			/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Vendor/staff reverse-link cleanup reads request-fresh postmeta pointers with prepared identifiers and filters so dual-hat links stay one-to-one after admin edits. */
			$other_vendor_ids = $wpdb->get_col($wpdb->prepare('SELECT pm.post_id FROM %i AS pm INNER JOIN %i AS p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND pm.meta_value = %s AND p.post_type = %s ORDER BY pm.meta_id ASC', $t_postmeta, $t_posts, $k_vendor_staff, (string) $new_staff_id, 'vms_vendor'));
		}
	}
	if (!is_array($other_vendor_ids)) {
		$other_vendor_ids = array();
	}

	foreach ($other_vendor_ids as $ovid) {
		$ovid = (int) $ovid;
		if ($ovid <= 0 || $ovid === $vendor_id) continue;
		delete_post_meta($ovid, $k_vendor_staff);
	}

	// Enforce uniqueness: if staff was linked to another vendor, unlink that vendor.
	$prior_vendor_id = (int) get_post_meta($new_staff_id, $k_staff_vendor, true);
	if ($prior_vendor_id > 0 && $prior_vendor_id !== $vendor_id) {
		delete_post_meta($prior_vendor_id, $k_vendor_staff);
	}

	update_post_meta($vendor_id, $k_vendor_staff, $new_staff_id);
	update_post_meta($new_staff_id, $k_staff_vendor, $vendor_id);

}, 20, 3);

add_action('admin_post_vms_create_staff_from_vendor', function (): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This admin-post action verifies a vendor-specific nonce immediately below before creating the staff record.
	$vendor_id = bvmgr_request_read_absint($_GET, 'vendor_id');
	if ($vendor_id <= 0) {
		wp_safe_redirect(admin_url('edit.php?post_type=vms_vendor'));
		exit;
	}

	if (!current_user_can('edit_post', $vendor_id)) {
		wp_die(esc_html__('You do not have permission to do that.', 'backstage-venue-manager'));
	}

	check_admin_referer('vms_create_staff_from_vendor_' . (string) $vendor_id);

	$vendor = get_post($vendor_id);
	if (!$vendor || $vendor->post_type !== 'vms_vendor') {
		wp_safe_redirect(admin_url('edit.php?post_type=vms_vendor'));
		exit;
	}

	$k_vendor_staff = bvmgr_vendor_linked_staff_meta_key();
	$k_staff_vendor = bvmgr_staff_linked_vendor_meta_key();

	$existing_staff_id = (int) get_post_meta($vendor_id, $k_vendor_staff, true);
	if ($existing_staff_id > 0) {
		$edit_staff = get_edit_post_link($existing_staff_id, '');
		wp_safe_redirect($edit_staff ? $edit_staff : admin_url('post.php?post=' . (string) $vendor_id . '&action=edit'));
		exit;
	}

	$title = trim(wp_strip_all_tags((string) $vendor->post_title));
	if ($title === '') $title = 'Staff';

	$staff_id = wp_insert_post(array(
		'post_type'   => 'vms_staff',
		'post_status' => 'publish',
		'post_title'  => $title,
	));

	if (is_wp_error($staff_id) || !$staff_id) {
		if (function_exists('bvmgr_add_admin_notice')) {
			bvmgr_add_admin_notice(__('Failed to create staff profile from vendor.', 'backstage-venue-manager'), 'error');
		}
		wp_safe_redirect(admin_url('post.php?post=' . (string) $vendor_id . '&action=edit'));
		exit;
	}

	$staff_id = (int) $staff_id;

	// Copy basic contact fields (best-effort, non-destructive).
	$k_email = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'primary_email') : '_vms_vendor_primary_email';
	$k_phone = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'primary_phone') : '_vms_vendor_primary_phone';
	$k_contact = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'contact_name') : '_vms_contact_name';

	$contact_name = (string) get_post_meta($vendor_id, (string) $k_contact, true);
	$email = (string) get_post_meta($vendor_id, (string) $k_email, true);
	$phone = (string) get_post_meta($vendor_id, (string) $k_phone, true);

	if ($contact_name !== '') update_post_meta($staff_id, '_vms_contact_name', $contact_name);
	if ($email !== '') update_post_meta($staff_id, '_vms_contact_email', $email);
	if ($phone !== '') update_post_meta($staff_id, '_vms_contact_phone', $phone);

	// Default worker type: contractor
	$k_worker = function_exists('bvmgr_staff_worker_type_meta_key') ? bvmgr_staff_worker_type_meta_key() : (function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('staff', 'worker_type') : '_vms_staff_worker_type');
	if ($k_worker === '') $k_worker = '_vms_staff_worker_type';
	update_post_meta($staff_id, $k_worker, 'contractor');

	// Link both ways.
	update_post_meta($vendor_id, $k_vendor_staff, $staff_id);
	update_post_meta($staff_id, $k_staff_vendor, $vendor_id);

	if (function_exists('bvmgr_add_admin_notice')) {
		bvmgr_add_admin_notice(__('Staff profile created and linked to this vendor.', 'backstage-venue-manager'), 'success');
	}

	$edit_staff = get_edit_post_link($staff_id, '');
	wp_safe_redirect($edit_staff ? $edit_staff : admin_url('post.php?post=' . (string) $vendor_id . '&action=edit'));
	exit;
});
