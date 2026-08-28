<?php
defined('ABSPATH') || exit;

/**
 * ==========================================================
 * VMS — Vendor ↔ WP User Linking (Admin)
 * ==========================================================
 *
 * This UI manages the many-to-many vendor↔user relationship layer.
 *
 * Authoritative storage:
 * - DB table: {$wpdb->prefix}vms_vendor_user_links
 *
 * Back-compat pointers (still maintained):
 * - Vendor post_meta: _vms_vendor_user_id (primary contact user for vendor)
 * - User meta:       _vms_vendor_id      (primary/default vendor for portal convenience)
 */


if (!function_exists('bvmgr_vendor_portal_admin_preview_url')) {
	function bvmgr_vendor_portal_admin_preview_url(int $vendor_id): string
	{
		$vendor_id = absint($vendor_id);
		if ($vendor_id <= 0) {
			return '';
		}

		$page_id = absint(get_option('vms_page_vendor_portal', 0));
		if ($page_id <= 0) {
			$page = get_page_by_path('vendor-portal');
			if ($page instanceof WP_Post) {
				$page_id = (int) $page->ID;
			}
		}

		$base_url = $page_id > 0
			? get_permalink($page_id)
			: home_url('/vendor-portal/');

		if (!is_string($base_url) || $base_url == '') {
			$base_url = home_url('/vendor-portal/');
		}

		return add_query_arg(array(
			'tab' => 'dashboard',
			'vendor_id' => $vendor_id,
			'vms_preview_vendor' => $vendor_id,
			'bvmgr_preview_nonce' => wp_create_nonce('bvmgr_preview_vendor_portal_' . $vendor_id),
		), $base_url);
	}
}


add_action('add_meta_boxes', function (): void {
	add_meta_box(
		'vms_vendor_user_link',
		'Vendor Portal Users',
		'bvmgr_vendor_user_links_metabox_render',
		'vms_vendor',
		'side',
		'default'
	);
});

function bvmgr_vendor_user_links_metabox_render($post): void
{
	if (!($post instanceof WP_Post)) return;

	wp_nonce_field('bvmgr_vendor_user_links_save', 'bvmgr_vendor_user_links_nonce');

	$vendor_id = (int) $post->ID;

	$links = array();
	if (function_exists('bvmgr_vendor_user_links_get_by_vendor')) {
		$links = bvmgr_vendor_user_links_get_by_vendor($vendor_id, true);
	}

	// Vendor's primary contact user (back-compat convenience)
	$primary_user_id = (int) get_post_meta($vendor_id, (defined('BVMGR_VENDOR_PRIMARY_USER_META_KEY') ? BVMGR_VENDOR_PRIMARY_USER_META_KEY : '_vms_vendor_user_id'), true);

	echo '<p class="description">';
	echo 'Link one or more WordPress users who can manage this vendor in the Vendor Portal.';
	echo '</p>';

	if (current_user_can('manage_options') && function_exists('bvmgr_vendor_portal_admin_preview_url')) {
		$preview_url = (string) bvmgr_vendor_portal_admin_preview_url($vendor_id);
		if ($preview_url !== '') {
			echo '<p><a class="button button-secondary button-small" href="' . esc_url($preview_url) . '" target="_blank" rel="noopener">' . esc_html__('Preview Vendor Portal', 'backstage-venue-manager') . '</a></p>';
		}
	}

	echo '<div class="vms-vul-metabox">';

	// Existing links
	if (!empty($links)) {
		echo '<div class="vms-vul-section-head">';
		echo '<strong>Linked users</strong>';
		echo '</div>';

		foreach ($links as $row) {
			$user_id = isset($row['user_id']) ? absint($row['user_id']) : 0;
			if ($user_id <= 0) continue;

			$user = get_user_by('id', $user_id);

			$user_label = $user ? $user->display_name : ('User #' . (string) $user_id);
			$user_email = ($user && !empty($user->user_email)) ? $user->user_email : '';

			$role = isset($row['user_role']) ? (string) $row['user_role'] : 'manager';
			$status = isset($row['link_status']) ? (string) $row['link_status'] : 'active';

			echo '<div class="vms-vul-user-card">';
			echo '<div class="vms-vul-user-name"><strong>' . esc_html($user_label) . '</strong></div>';
			if ($user_email !== '') {
				echo '<div class="vms-vul-user-email">' . esc_html($user_email) . '</div>';
			}

			echo '<label class="vms-vul-field-label"><strong>Role</strong></label>';
			echo '<select name="vms_vul[links][' . esc_attr((string) $user_id) . '][role]" class="vms-vul-select">';
			echo bvmgr_vendor_user_links_role_options($role);
			echo '</select>';

			echo '<label class="vms-vul-field-label vms-vul-field-label-gap"><strong>Status</strong></label>';
			echo '<select name="vms_vul[links][' . esc_attr((string) $user_id) . '][status]" class="vms-vul-select">';
			echo bvmgr_vendor_user_links_status_options($status);
			echo '</select>';

			echo '<label class="vms-vul-check-label vms-vul-check-label-gap-lg">';
			echo '<input type="checkbox" name="vms_vul[links][' . esc_attr((string) $user_id) . '][remove]" value="1"> ';
			echo 'Remove link';
			echo '</label>';

			echo '<label class="vms-vul-check-label vms-vul-check-label-gap-sm">';
			echo '<input type="checkbox" name="vms_vul[links][' . esc_attr((string) $user_id) . '][set_primary_for_user]" value="1"> ';
			echo 'Make this vendor the primary portal vendor for this user';
			echo '</label>';

			echo '</div>';
		}
	} else {
		echo '<p><em>No users are linked yet.</em></p>';
	}

	// Primary contact user selector (vendor-side pointer)
	$linked_user_ids = array();
	foreach ($links as $row) {
		$linked_user_ids[] = isset($row['user_id']) ? absint($row['user_id']) : 0;
	}
	$linked_user_ids = array_values(array_filter(array_unique($linked_user_ids)));

	echo '<div class="vms-vul-section-head vms-vul-section-head-compact">';
	echo '<strong>Primary contact user (optional)</strong>';
	echo '</div>';

	if (!empty($linked_user_ids)) {
		echo '<select name="vms_vul[primary_user_id]" class="vms-vul-select">';
		echo '<option value="0">— Not set —</option>';

		foreach ($linked_user_ids as $uid) {
			$user = get_user_by('id', $uid);
			$label = $user ? $user->display_name : ('User #' . (string) $uid);
			printf(
				'<option value="%d" %s>%s</option>',
				(int) $uid,
				selected($primary_user_id, (int) $uid, false),
				esc_html($label)
			);
		}
		echo '</select>';
		echo '<p class="description vms-vul-help">Used as a vendor-facing “primary contact” pointer. It does not limit portal access for other linked users.</p>';
	} else {
		echo '<p class="description">Link at least one user above to choose a primary contact.</p>';
	}

	// Add new link
	echo '<div class="vms-vul-section-head vms-vul-section-head-add">';
	echo '<strong>Add another user</strong>';
	echo '</div>';

	$users = get_users(array(
		'orderby' => 'display_name',
		'order'   => 'ASC',
		'fields'  => array('ID', 'display_name', 'user_email'),
	));

	echo '<label class="vms-vul-field-label"><strong>User</strong></label>';
	echo '<select name="vms_vul[new_user_id]" class="vms-vul-select">';
	echo '<option value="0">— Select a user —</option>';
	foreach ($users as $u) {
		$label = $u->display_name;
		if (!empty($u->user_email)) $label .= ' — ' . $u->user_email;
		printf('<option value="%d">%s</option>', (int) $u->ID, esc_html($label));
	}
	echo '</select>';

	echo '<label class="vms-vul-field-label vms-vul-field-label-gap"><strong>Role</strong></label>';
	echo '<select name="vms_vul[new_role]" class="vms-vul-select">';
	echo bvmgr_vendor_user_links_role_options('manager');
	echo '</select>';

	echo '<label class="vms-vul-field-label vms-vul-field-label-gap"><strong>Status</strong></label>';
	echo '<select name="vms_vul[new_status]" class="vms-vul-select">';
	echo bvmgr_vendor_user_links_status_options('active');
	echo '</select>';

	echo '<label class="vms-vul-check-label vms-vul-check-label-gap-md">';
	echo '<input type="checkbox" name="vms_vul[new_set_primary_for_user]" value="1"> ';
	echo 'Make this vendor the primary portal vendor for this user';
	echo '</label>';

	echo '<p class="description vms-vul-help">Changes are saved when you click “Update” on this Vendor.</p>';
	echo '</div>';
}

function bvmgr_vendor_user_links_role_options(string $selected_role): string
{
	$selected_role = sanitize_key($selected_role);

	$roles = array(
		'owner'          => 'Owner',
		'primary_contact'=> 'Primary contact',
		'manager'        => 'Manager',
		'agent'          => 'Agent',
		'accountant'     => 'Accountant',
		'viewer'         => 'Viewer (read-only)',
	);

	$out = '';
	foreach ($roles as $key => $label) {
		$out .= sprintf(
			'<option value="%s" %s>%s</option>',
			esc_attr($key),
			selected($selected_role, $key, false),
			esc_html($label)
		);
	}
	return $out;
}

function bvmgr_vendor_user_links_status_options(string $selected_status): string
{
	$selected_status = sanitize_key($selected_status);

	$statuses = array(
		'active'   => 'Active',
		'pending'  => 'Pending',
		'disabled' => 'Disabled',
	);

	$out = '';
	foreach ($statuses as $key => $label) {
		$out .= sprintf(
			'<option value="%s" %s>%s</option>',
			esc_attr($key),
			selected($selected_status, $key, false),
			esc_html($label)
		);
	}
	return $out;
}

add_action('save_post_vms_vendor', function (int $post_id, WP_Post $post, bool $update): void {
	// Avoid autosaves / revisions
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (wp_is_post_revision($post_id)) return;

	if (!current_user_can('edit_post', $post_id)) return;

	$nonce = (isset($_POST['bvmgr_vendor_user_links_nonce']) && !is_array($_POST['bvmgr_vendor_user_links_nonce']))
		? sanitize_text_field(wp_unslash((string) $_POST['bvmgr_vendor_user_links_nonce']))
		: '';
	if ($nonce === '' || !wp_verify_nonce($nonce, bvmgr_nonce_action_for_value($nonce, 'bvmgr_vendor_user_links_save'))) {
		return;
	}

	$actor_user_id = get_current_user_id();

		$data = (isset($_POST['vms_vul']) && is_array($_POST['vms_vul']))
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Structured vendor-user link payloads are unslashed here and normalized field-by-field below.
			? (array) wp_unslash($_POST['vms_vul'])
			: array();

	// Update existing links
	$posted_links = isset($data['links']) && is_array($data['links']) ? $data['links'] : array();

	foreach ($posted_links as $uid_raw => $row_raw) {
		$user_id = absint((string) $uid_raw);
		if ($user_id <= 0) continue;

		$row = is_array($row_raw) ? $row_raw : array();

		$remove = !empty($row['remove']);
		if ($remove) {
			if (function_exists('bvmgr_vendor_user_link_delete')) {
				bvmgr_vendor_user_link_delete($post_id, $user_id, $actor_user_id);
			}
			continue;
		}

		$role = isset($row['role']) ? sanitize_key((string) $row['role']) : 'manager';
		$status = isset($row['status']) ? sanitize_key((string) $row['status']) : 'active';

		if (function_exists('bvmgr_vendor_user_link_update')) {
			bvmgr_vendor_user_link_update($post_id, $user_id, array(
				'role'   => $role,
				'status' => $status,
			), $actor_user_id);
		} elseif (function_exists('bvmgr_vendor_user_link_upsert')) {
			bvmgr_vendor_user_link_upsert($post_id, $user_id, array(
				'role'   => $role,
				'status' => $status,
				'source' => 'vendor_user_metabox',
			), $actor_user_id);
		}

		if (!empty($row['set_primary_for_user']) && function_exists('bvmgr_vendor_user_links_set_primary_for_user')) {
			bvmgr_vendor_user_links_set_primary_for_user($user_id, $post_id, $actor_user_id);
		}
	}

	// Add new link
	$new_user_id = isset($data['new_user_id']) ? absint((string) $data['new_user_id']) : 0;
	if ($new_user_id > 0 && function_exists('bvmgr_vendor_user_link_upsert')) {
		$new_role = isset($data['new_role']) ? sanitize_key((string) $data['new_role']) : 'manager';
		$new_status = isset($data['new_status']) ? sanitize_key((string) $data['new_status']) : 'active';
		$set_primary = !empty($data['new_set_primary_for_user']);

		bvmgr_vendor_user_link_upsert($post_id, $new_user_id, array(
			'role' => $new_role,
			'status' => $new_status,
			'set_primary_for_user' => $set_primary,
			'source' => 'vendor_user_metabox',
		), $actor_user_id);

		if ($set_primary && function_exists('bvmgr_vendor_user_links_set_primary_for_user')) {
			bvmgr_vendor_user_links_set_primary_for_user($new_user_id, $post_id, $actor_user_id);
		}
	}

	// Update vendor's primary contact user pointer (optional convenience)
	$primary_user_id = isset($data['primary_user_id']) ? absint((string) $data['primary_user_id']) : 0;
	$key = (defined('BVMGR_VENDOR_PRIMARY_USER_META_KEY') ? BVMGR_VENDOR_PRIMARY_USER_META_KEY : '_vms_vendor_user_id');

	if ($primary_user_id > 0) {
		// Only allow setting primary contact to a currently linked user.
		$linked_ids = array();
		if (function_exists('bvmgr_vendor_user_links_get_by_vendor')) {
			$rows = bvmgr_vendor_user_links_get_by_vendor($post_id, true);
			foreach ($rows as $r) {
				$linked_ids[] = isset($r['user_id']) ? absint($r['user_id']) : 0;
			}
		}
		$linked_ids = array_values(array_filter(array_unique($linked_ids)));

		if (in_array($primary_user_id, $linked_ids, true)) {
			update_post_meta($post_id, $key, $primary_user_id);
		}
	} else {
		delete_post_meta($post_id, $key);
	}
}, 20, 3);

add_filter('post_row_actions', function (array $actions, WP_Post $post): array {
	if ($post->post_type !== 'vms_vendor') {
		return $actions;
	}
	if (!current_user_can('manage_options')) {
		return $actions;
	}
	if (!function_exists('bvmgr_vendor_portal_admin_preview_url')) {
		return $actions;
	}

	$preview_url = (string) bvmgr_vendor_portal_admin_preview_url((int) $post->ID);
	if ($preview_url === '') {
		return $actions;
	}

	$actions['vms_vendor_portal_preview'] =
		'<a href="' . esc_url($preview_url) . '" target="_blank" rel="noopener">' .
		esc_html__('Preview Portal', 'backstage-venue-manager') .
		'</a>';

	return $actions;
}, 10, 2);
