<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_status_notice_admin_page_url')) {
	function vms_status_notice_admin_page_url(array $args = array()): string
	{
		return add_query_arg($args, admin_url('admin.php?page=vms-status-notices'));
	}
}

if (!function_exists('vms_status_notice_admin_query_arg')) {
	function vms_status_notice_admin_query_arg(string $key): string
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only Status Notices admin routing and filters only change admin display state.
		if (!isset($_GET[$key])) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only Status Notices admin routing and filters are unslashed here and sanitized or cast by the caller.
		return (string) wp_unslash($_GET[$key]);
	}
}

if (!function_exists('vms_status_notice_admin_templates')) {
	function vms_status_notice_admin_templates(): array
	{
		return array(
			'maintenance_banner' => __('Maintenance (Banner)', 'backstage-venue-manager'),
			'major_outage' => __('Major Outage (Fullscreen)', 'backstage-venue-manager'),
			'browser_warning' => __('Browser Compatibility Warning (Sticky)', 'backstage-venue-manager'),
			'admin_alert' => __('Admin-only Alert', 'backstage-venue-manager'),
			'ios_safari_ticketing_warning' => __('iOS Safari Ticketing Warning', 'backstage-venue-manager'),
		);
	}
}

if (!function_exists('vms_status_notice_admin_enqueue_assets')) {
	function vms_status_notice_admin_enqueue_assets(): void
	{
		$page = sanitize_key(vms_status_notice_admin_query_arg('page'));
		if ($page !== 'vms-status-notices') {
			return;
		}

		$ver = defined('BVMGR_VERSION') ? BVMGR_VERSION : null;
		wp_enqueue_style(
			'vms-notices-front',
			BVMGR_PLUGIN_URL . 'assets/css/vms-notices-front.css',
			array('vms-ui'),
			$ver
		);
		wp_enqueue_style(
			'vms-status-notices-admin',
			BVMGR_PLUGIN_URL . 'assets/css/vms-status-notices-admin.css',
			array('vms-admin', 'vms-admin-ui', 'vms-notices-front'),
			$ver
		);
		wp_enqueue_script(
			'vms-status-notices-admin',
			BVMGR_PLUGIN_URL . 'assets/js/vms-status-notices-admin.js',
			array(),
			$ver,
			true
		);

		wp_localize_script('vms-status-notices-admin', 'vmsStatusNoticesAdmin', array(
			'pageTypeLabels' => vms_status_notice_page_type_labels(),
			'deviceLabels' => vms_status_notice_device_labels(),
			'browserLabels' => vms_status_notice_browser_labels(),
			'osLabels' => vms_status_notice_os_labels(),
			'ajaxUrl' => admin_url('admin-ajax.php'),
			'searchNonce' => wp_create_nonce('vms_status_notice_object_search'),
			'searchMinChars' => 2,
			'strings' => array(
				'pass' => __('PASS', 'backstage-venue-manager'),
				'fail' => __('FAIL', 'backstage-venue-manager'),
				'simulatorNeedsSelection' => __('Simulator evaluated using current form settings.', 'backstage-venue-manager'),
				'simPassMessage' => __('This simulated context matches current targeting.', 'backstage-venue-manager'),
				'addRow' => __('Add row', 'backstage-venue-manager'),
				'addId' => __('Add ID', 'backstage-venue-manager'),
				'search' => __('Search', 'backstage-venue-manager'),
				'remove' => __('Remove', 'backstage-venue-manager'),
				'searchPlaceholder' => __('Search pages, posts, products, events...', 'backstage-venue-manager'),
				'manualIdPlaceholder' => __('Enter an ID', 'backstage-venue-manager'),
				'searchHint' => __('Type at least 2 characters to search.', 'backstage-venue-manager'),
				'searchNoMatches' => __('No matches found.', 'backstage-venue-manager'),
				'searchFailed' => __('Search failed. Try again.', 'backstage-venue-manager'),
				'searchUnavailable' => __('Search is unavailable; add IDs manually.', 'backstage-venue-manager'),
			),
		));
	}
}
add_action('admin_enqueue_scripts', 'vms_status_notice_admin_enqueue_assets', 45);

if (!function_exists('vms_status_notice_roles_catalog')) {
	function vms_status_notice_roles_catalog(): array
	{
		$catalog = array();
		if (!function_exists('wp_roles')) {
			return $catalog;
		}
		$roles = wp_roles();
		if (!is_object($roles) || !isset($roles->roles) || !is_array($roles->roles)) {
			return $catalog;
		}
		foreach ($roles->roles as $slug => $role) {
			$catalog[sanitize_key((string) $slug)] = isset($role['name']) ? (string) $role['name'] : (string) $slug;
		}
		return $catalog;
	}
}

if (!function_exists('vms_status_notice_render_checkbox_group')) {
	function vms_status_notice_render_checkbox_group(string $name, array $options, array $selected, string $class = 'vms-status-grid-checks'): void
	{
		echo '<div class="' . esc_attr($class) . '">';
		foreach ($options as $value => $label) {
			$value = sanitize_key((string) $value);
			$is_checked = in_array($value, array_map('sanitize_key', $selected), true);
			echo '<label><input type="checkbox" name="' . esc_attr($name) . '[]" value="' . esc_attr($value) . '"' . checked(true, $is_checked, false) . '> ' . esc_html((string) $label) . '</label>';
		}
		echo '</div>';
	}
}

if (!function_exists('vms_status_notice_render_admin_page')) {
	function vms_status_notice_render_admin_page(): void
	{
		if (!current_user_can(vms_status_notices_capability())) {
			wp_die(esc_html__('You do not have permission to manage Status Notices.', 'backstage-venue-manager'));
		}

		$view = sanitize_key(vms_status_notice_admin_query_arg('view'));
		if ($view === '') {
			$view = 'list';
		}
		if ($view === 'edit') {
			vms_status_notice_render_edit_screen();
			return;
		}

		vms_status_notice_render_list_screen();
	}
}

if (!function_exists('vms_status_notice_notice_bar')) {
	function vms_status_notice_notice_bar(): void
	{
		$status = sanitize_key(vms_status_notice_admin_query_arg('vms_status_notice_result'));
		if ($status === '') {
			return;
		}

		$message = '';
		switch ($status) {
			case 'saved':
				$message = __('Status Notice saved.', 'backstage-venue-manager');
				break;
			case 'duplicated':
				$message = __('Status Notice duplicated.', 'backstage-venue-manager');
				break;
			case 'toggled':
				$message = __('Status Notice updated.', 'backstage-venue-manager');
				break;
			case 'trashed':
				$message = __('Status Notice moved to trash.', 'backstage-venue-manager');
				break;
			case 'bulk_updated':
				$bulk_count = absint(vms_status_notice_admin_query_arg('bulk_count'));
				if ($bulk_count > 0) {
					/* translators: %d: number of updated notices */
					$message = sprintf(_n('%d notice updated.', '%d notices updated.', $bulk_count, 'backstage-venue-manager'), $bulk_count);
				} else {
					$message = __('Bulk action completed.', 'backstage-venue-manager');
				}
				break;
		}

		if ($message !== '') {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
		}
	}
}

if (!function_exists('vms_status_notice_render_list_screen')) {
	function vms_status_notice_render_list_screen(): void
	{
		$items = vms_status_notice_query_all();
		$scope_filter = sanitize_key(vms_status_notice_admin_query_arg('scope'));
		$severity_filter = sanitize_key(vms_status_notice_admin_query_arg('severity'));
		$enabled_filter = sanitize_key(vms_status_notice_admin_query_arg('enabled'));
		$q = sanitize_text_field(vms_status_notice_admin_query_arg('q'));

		$items = array_values(array_filter($items, static function (array $item) use ($scope_filter, $severity_filter, $enabled_filter, $q): bool {
			if ($scope_filter !== '' && $scope_filter !== (string) ($item['scope'] ?? '')) {
				return false;
			}
			if ($severity_filter !== '' && $severity_filter !== (string) ($item['severity'] ?? '')) {
				return false;
			}
			if ($enabled_filter === 'enabled' && empty($item['enabled'])) {
				return false;
			}
			if ($enabled_filter === 'disabled' && !empty($item['enabled'])) {
				return false;
			}
			if ($q !== '') {
				$needle = function_exists('mb_strtolower') ? mb_strtolower($q, 'UTF-8') : strtolower($q);
				$haystack = function_exists('mb_strtolower')
					? mb_strtolower((string) (($item['title'] ?? '') . ' ' . ($item['headline'] ?? '') . ' ' . ($item['audience_summary'] ?? '')), 'UTF-8')
					: strtolower((string) (($item['title'] ?? '') . ' ' . ($item['headline'] ?? '') . ' ' . ($item['audience_summary'] ?? '')));
				if (strpos($haystack, $needle) === false) {
					return false;
				}
			}
			return true;
		}));

		$scope_labels = vms_status_notice_scope_labels();
		$severity_labels = vms_status_notice_severity_labels();

		if (function_exists('bvmgr_admin_ui_render_shell')) {
			bvmgr_admin_ui_render_shell(
				array(
					'title' => __('Status Notices', 'backstage-venue-manager'),
					'subtitle' => __('Targeted website and admin notices with device/browser-aware delivery.', 'backstage-venue-manager'),
					'shell_id' => 'vms-status-notices-wrap',
					'actions_html' => '<a class="button button-primary" href="' . esc_url(vms_status_notice_admin_page_url(array('view' => 'edit'))) . '">' . esc_html__('Create Notice', 'backstage-venue-manager') . '</a>',
					'notices_callback' => 'vms_status_notice_notice_bar',
				),
				static function () use ($items, $scope_filter, $severity_filter, $enabled_filter, $q, $scope_labels, $severity_labels): void {
					echo '<form class="vms-status-filters" method="get">';
					echo '<input type="hidden" name="page" value="vms-status-notices">';
					echo '<label>' . esc_html__('Scope', 'backstage-venue-manager') . '<select name="scope"><option value="">' . esc_html__('All', 'backstage-venue-manager') . '</option>';
					foreach ($scope_labels as $key => $label) {
						echo '<option value="' . esc_attr((string) $key) . '"' . selected((string) $scope_filter, (string) $key, false) . '>' . esc_html((string) $label) . '</option>';
					}
					echo '</select></label>';

					echo '<label>' . esc_html__('Severity', 'backstage-venue-manager') . '<select name="severity"><option value="">' . esc_html__('All', 'backstage-venue-manager') . '</option>';
					foreach ($severity_labels as $key => $label) {
						echo '<option value="' . esc_attr((string) $key) . '"' . selected((string) $severity_filter, (string) $key, false) . '>' . esc_html((string) $label) . '</option>';
					}
					echo '</select></label>';

					echo '<label>' . esc_html__('Enabled', 'backstage-venue-manager') . '<select name="enabled">';
					echo '<option value="">' . esc_html__('All', 'backstage-venue-manager') . '</option>';
					echo '<option value="enabled"' . selected($enabled_filter, 'enabled', false) . '>' . esc_html__('Enabled', 'backstage-venue-manager') . '</option>';
					echo '<option value="disabled"' . selected($enabled_filter, 'disabled', false) . '>' . esc_html__('Disabled', 'backstage-venue-manager') . '</option>';
					echo '</select></label>';
					echo '<label>' . esc_html__('Search', 'backstage-venue-manager') . '<input type="search" name="q" value="' . esc_attr($q) . '" placeholder="' . esc_attr__('Title or audience', 'backstage-venue-manager') . '"></label>';
					echo '<button class="button" type="submit">' . esc_html__('Filter', 'backstage-venue-manager') . '</button>';
					echo '</form>';

					echo '<p class="description vms-status-template-links">' . esc_html__('Quick templates:', 'backstage-venue-manager') . ' ';
					foreach (vms_status_notice_admin_templates() as $template_key => $template_label) {
						$url = vms_status_notice_admin_page_url(array('view' => 'edit', 'template' => $template_key));
						echo '<a href="' . esc_url($url) . '">' . esc_html($template_label) . '</a> ';
					}
					echo '</p>';

					echo '<form class="vms-status-bulk-actions" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
					echo '<input type="hidden" name="action" value="vms_status_notice_bulk">';
					wp_nonce_field('vms_status_notice_bulk');
					echo '<div class="vms-status-bulk-actions__row">';
					echo '<label>' . esc_html__('Bulk Action', 'backstage-venue-manager') . ' ';
					echo '<select name="bulk_action">';
					echo '<option value="">' . esc_html__('Select action', 'backstage-venue-manager') . '</option>';
					echo '<option value="enable">' . esc_html__('Enable', 'backstage-venue-manager') . '</option>';
					echo '<option value="disable">' . esc_html__('Disable', 'backstage-venue-manager') . '</option>';
					echo '<option value="trash">' . esc_html__('Trash', 'backstage-venue-manager') . '</option>';
					echo '</select>';
					echo '</label> ';
					echo '<button class="button" type="submit">' . esc_html__('Apply', 'backstage-venue-manager') . '</button>';
					echo '</div>';

					echo '<table class="widefat striped vms-status-table">';
					echo '<thead><tr>';
					echo '<th class="check-column"><input type="checkbox" id="vms-status-select-all" aria-label="' . esc_attr__('Select all notices', 'backstage-venue-manager') . '"></th>';
					echo '<th>' . esc_html__('Enabled', 'backstage-venue-manager') . '</th>';
					echo '<th>' . esc_html__('Title', 'backstage-venue-manager') . '</th>';
					echo '<th>' . esc_html__('Scope', 'backstage-venue-manager') . '</th>';
					echo '<th>' . esc_html__('Severity', 'backstage-venue-manager') . '</th>';
					echo '<th>' . esc_html__('Intensity', 'backstage-venue-manager') . '</th>';
					echo '<th>' . esc_html__('Audience Summary', 'backstage-venue-manager') . '</th>';
					echo '<th>' . esc_html__('Active Window', 'backstage-venue-manager') . '</th>';
					echo '<th>' . esc_html__('Priority', 'backstage-venue-manager') . '</th>';
					echo '<th>' . esc_html__('Actions', 'backstage-venue-manager') . '</th>';
					echo '</tr></thead><tbody>';

					if (empty($items)) {
						echo '<tr><td colspan="10">' . esc_html__('No notices found for this filter.', 'backstage-venue-manager') . '</td></tr>';
					} else {
						foreach ($items as $item) {
							$id = (int) ($item['id'] ?? 0);
							$edit_url = vms_status_notice_admin_page_url(array('view' => 'edit', 'id' => $id));
							$toggle_url = wp_nonce_url(admin_url('admin-post.php?action=vms_status_notice_toggle&id=' . $id . '&enabled=' . (empty($item['enabled']) ? '1' : '0')), 'vms_status_notice_toggle_' . $id);
							$duplicate_url = wp_nonce_url(admin_url('admin-post.php?action=vms_status_notice_duplicate&id=' . $id), 'vms_status_notice_duplicate_' . $id);
							$trash_url = wp_nonce_url(admin_url('admin-post.php?action=vms_status_notice_trash&id=' . $id), 'vms_status_notice_trash_' . $id);

							$window = __('Always', 'backstage-venue-manager');
							if ((string) ($item['schedule_mode'] ?? 'always') === 'scheduled') {
								$start = (int) ($item['start_ts'] ?? 0);
								$end = (int) ($item['end_ts'] ?? 0);
								if ($start > 0 || $end > 0) {
									$window = trim(($start > 0 ? wp_date('Y-m-d H:i', $start, wp_timezone()) : 'n/a') . ' - ' . ($end > 0 ? wp_date('Y-m-d H:i', $end, wp_timezone()) : 'n/a'));
								}
							}

							echo '<tr>';
							/* translators: %s: notice title. */
							echo '<th class="check-column"><input type="checkbox" class="vms-status-row-check" name="notice_ids[]" value="' . esc_attr((string) $id) . '" aria-label="' . esc_attr(sprintf(__('Select %s', 'backstage-venue-manager'), (string) ($item['title'] ?? 'notice'))) . '"></th>';
							echo '<td>' . (!empty($item['enabled']) ? esc_html__('Yes', 'backstage-venue-manager') : esc_html__('No', 'backstage-venue-manager')) . '</td>';
							echo '<td><strong><a href="' . esc_url($edit_url) . '">' . esc_html((string) ($item['title'] ?? '')) . '</a></strong><br><span class="description">' . esc_html((string) ($item['headline'] ?? '')) . '</span></td>';
							echo '<td>' . esc_html((string) ($scope_labels[(string) ($item['scope'] ?? 'front')] ?? (string) ($item['scope'] ?? 'front'))) . '</td>';
							echo '<td>' . esc_html((string) ($severity_labels[(string) ($item['severity'] ?? 'warning')] ?? (string) ($item['severity'] ?? 'warning'))) . '</td>';
							echo '<td>' . esc_html((string) (int) ($item['intensity'] ?? 0)) . '</td>';
							echo '<td>' . esc_html((string) ($item['audience_summary'] ?? '')) . '</td>';
							echo '<td>' . esc_html($window) . '</td>';
							echo '<td>' . esc_html((string) (int) ($item['priority'] ?? 0)) . '</td>';
							echo '<td class="vms-status-table-actions">';
							echo '<a href="' . esc_url($edit_url) . '">' . esc_html__('Edit', 'backstage-venue-manager') . '</a>';
							echo '<a href="' . esc_url($duplicate_url) . '">' . esc_html__('Duplicate', 'backstage-venue-manager') . '</a>';
							echo '<a href="' . esc_url($toggle_url) . '">' . (empty($item['enabled']) ? esc_html__('Enable', 'backstage-venue-manager') : esc_html__('Disable', 'backstage-venue-manager')) . '</a>';
							echo '<a href="' . esc_url($trash_url) . '" class="is-danger" onclick="return confirm(' . esc_attr(wp_json_encode(__('Move this notice to trash?', 'backstage-venue-manager'))) . ');">' . esc_html__('Trash', 'backstage-venue-manager') . '</a>';
							echo '</td>';
							echo '</tr>';
						}
					}

					echo '</tbody></table>';
					echo '</form>';
				}
			);
			return;
		}

		echo '<div class="wrap"><h1>' . esc_html__('Status Notices', 'backstage-venue-manager') . '</h1>';
		vms_status_notice_notice_bar();
		echo '</div>';
	}
}

if (!function_exists('vms_status_notice_render_edit_screen')) {
	function vms_status_notice_render_edit_screen(): void
	{
		$notice_id = absint(vms_status_notice_admin_query_arg('id'));
		$template = sanitize_key(vms_status_notice_admin_query_arg('template'));

		$notice = $notice_id > 0 ? vms_status_notice_get($notice_id) : null;
		if (!is_array($notice)) {
			$notice = vms_status_notice_default_notice();
			if ($template !== '') {
				$notice = vms_status_notice_template_defaults($template);
			}
		}

		$notice['include_object_ids_raw'] = implode("\n", array_map('intval', (array) ($notice['include_object_ids'] ?? array())));
		$notice['exclude_object_ids_raw'] = implode("\n", array_map('intval', (array) ($notice['exclude_object_ids'] ?? array())));
		$notice['user_ids_include_raw'] = implode("\n", array_map('intval', (array) ($notice['user_ids_include'] ?? array())));
		$notice['url_contains_raw'] = implode("\n", array_map('strval', (array) ($notice['url_contains'] ?? array())));
		$notice['url_excludes_raw'] = implode("\n", array_map('strval', (array) ($notice['url_excludes'] ?? array())));

		$scope_labels = vms_status_notice_scope_labels();
		$severity_labels = vms_status_notice_severity_labels();
		$page_type_labels = vms_status_notice_page_type_labels();
		$device_labels = vms_status_notice_device_labels();
		$browser_labels = vms_status_notice_browser_labels();
		$os_labels = vms_status_notice_os_labels();
		$role_labels = vms_status_notice_roles_catalog();

		$title = $notice_id > 0 ? __('Edit Status Notice', 'backstage-venue-manager') : __('Create Status Notice', 'backstage-venue-manager');
		$subtitle = __('Use targeting rules to display browser/device-specific guidance without code changes.', 'backstage-venue-manager');

		$content = static function () use ($notice, $notice_id, $scope_labels, $severity_labels, $page_type_labels, $device_labels, $browser_labels, $os_labels, $role_labels): void {
			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-status-notice-form" id="vms-status-notice-form">';
			echo '<input type="hidden" name="action" value="vms_status_notice_save">';
			wp_nonce_field('vms_status_notice_save');
			echo '<input type="hidden" name="notice_id" value="' . esc_attr((string) $notice_id) . '">';

			echo '<section class="vms-status-card">';
			echo '<h2>' . esc_html__('Message', 'backstage-venue-manager') . '</h2>';
			echo '<div class="vms-status-grid">';
			echo '<label>' . esc_html__('Internal Title', 'backstage-venue-manager') . '<input type="text" name="title" value="' . esc_attr((string) ($notice['title'] ?? '')) . '" required></label>';
			echo '<label>' . esc_html__('Headline', 'backstage-venue-manager') . '<input type="text" name="headline" value="' . esc_attr((string) ($notice['headline'] ?? '')) . '"></label>';
			echo '<label class="vms-status-span-2">' . esc_html__('Body', 'backstage-venue-manager') . '<textarea name="body_html" rows="4" placeholder="' . esc_attr__('Message body (HTML allowed)', 'backstage-venue-manager') . '">' . esc_textarea((string) ($notice['body_html'] ?? '')) . '</textarea></label>';
			echo '<label>' . esc_html__('Primary Button Label', 'backstage-venue-manager') . '<input type="text" name="primary_btn_label" value="' . esc_attr((string) ($notice['primary_btn_label'] ?? '')) . '"></label>';
			echo '<label>' . esc_html__('Primary Button URL', 'backstage-venue-manager') . '<input type="url" name="primary_btn_url" value="' . esc_attr((string) ($notice['primary_btn_url'] ?? '')) . '"></label>';
			echo '<label>' . esc_html__('Secondary Button Label', 'backstage-venue-manager') . '<input type="text" name="secondary_btn_label" value="' . esc_attr((string) ($notice['secondary_btn_label'] ?? '')) . '"></label>';
			echo '<label>' . esc_html__('Secondary Button URL', 'backstage-venue-manager') . '<input type="url" name="secondary_btn_url" value="' . esc_attr((string) ($notice['secondary_btn_url'] ?? '')) . '"></label>';
			echo '</div>';
			echo '</section>';

			echo '<section class="vms-status-card">';
			echo '<h2>' . esc_html__('Display', 'backstage-venue-manager') . '</h2>';
			echo '<div class="vms-status-grid">';
			echo '<label><input type="checkbox" name="enabled" value="1"' . checked(1, (int) ($notice['enabled'] ?? 0), false) . '> ' . esc_html__('Enabled', 'backstage-venue-manager') . '</label>';

			echo '<label>' . esc_html__('Scope', 'backstage-venue-manager') . '<select name="scope">';
			foreach ($scope_labels as $key => $label) {
				echo '<option value="' . esc_attr((string) $key) . '"' . selected((string) ($notice['scope'] ?? ''), (string) $key, false) . '>' . esc_html((string) $label) . '</option>';
			}
			echo '</select></label>';

			echo '<label>' . esc_html__('Severity', 'backstage-venue-manager') . '<select name="severity">';
			foreach ($severity_labels as $key => $label) {
				echo '<option value="' . esc_attr((string) $key) . '"' . selected((string) ($notice['severity'] ?? ''), (string) $key, false) . '>' . esc_html((string) $label) . '</option>';
			}
			echo '</select></label>';

			echo '<label>' . esc_html__('Priority', 'backstage-venue-manager') . '<input type="number" name="priority" value="' . esc_attr((string) (int) ($notice['priority'] ?? 0)) . '"></label>';
			echo '<label>' . esc_html__('Intensity (1-5)', 'backstage-venue-manager') . '<input type="number" min="1" max="5" name="intensity" value="' . esc_attr((string) (int) ($notice['intensity'] ?? 2)) . '"></label>';
			echo '<label>' . esc_html__('Placement', 'backstage-venue-manager') . '<select name="placement"><option value="top"' . selected((string) ($notice['placement'] ?? ''), 'top', false) . '>' . esc_html__('Top', 'backstage-venue-manager') . '</option><option value="bottom"' . selected((string) ($notice['placement'] ?? ''), 'bottom', false) . '>' . esc_html__('Bottom', 'backstage-venue-manager') . '</option></select></label>';
			echo '<label><input type="checkbox" name="dismissible" value="1"' . checked(1, (int) ($notice['dismissible'] ?? 0), false) . '> ' . esc_html__('Dismissible', 'backstage-venue-manager') . '</label>';
			echo '<label>' . esc_html__('Dismiss TTL', 'backstage-venue-manager') . '<select name="dismiss_ttl">';
			foreach (vms_status_notice_allowed_dismiss_ttls() as $ttl) {
				echo '<option value="' . esc_attr($ttl) . '"' . selected((string) ($notice['dismiss_ttl'] ?? ''), $ttl, false) . '>' . esc_html($ttl) . '</option>';
			}
			echo '</select></label>';
			echo '<label>' . esc_html__('Trigger', 'backstage-venue-manager') . '<select name="trigger">';
			foreach (vms_status_notice_allowed_triggers() as $trigger) {
				echo '<option value="' . esc_attr($trigger) . '"' . selected((string) ($notice['trigger'] ?? ''), $trigger, false) . '>' . esc_html($trigger) . '</option>';
			}
			echo '</select></label>';
			echo '<label>' . esc_html__('Trigger Delay (ms)', 'backstage-venue-manager') . '<input type="number" min="0" max="60000" name="trigger_delay_ms" value="' . esc_attr((string) (int) ($notice['trigger_delay_ms'] ?? 0)) . '"></label>';
			echo '<label class="vms-status-span-2">' . esc_html__('Trigger Selector', 'backstage-venue-manager') . '<input type="text" name="trigger_selector" value="' . esc_attr((string) ($notice['trigger_selector'] ?? '')) . '" placeholder=".tribe-tickets__tickets"></label>';
			echo '</div>';
			echo '</section>';

			echo '<section class="vms-status-card">';
			echo '<h2>' . esc_html__('Targeting', 'backstage-venue-manager') . '</h2>';
			echo '<div class="vms-status-grid">';
			echo '<label>' . esc_html__('Pages Mode', 'backstage-venue-manager') . '<select name="pages_mode"><option value="all"' . selected((string) ($notice['pages_mode'] ?? ''), 'all', false) . '>' . esc_html__('All pages (with exclusions)', 'backstage-venue-manager') . '</option><option value="include"' . selected((string) ($notice['pages_mode'] ?? ''), 'include', false) . '>' . esc_html__('Include matching pages only', 'backstage-venue-manager') . '</option></select></label>';
			echo '<div class="vms-status-span-2"><span class="vms-status-label">' . esc_html__('Include Page Types', 'backstage-venue-manager') . '</span>';
			vms_status_notice_render_checkbox_group('include_page_types', $page_type_labels, (array) ($notice['include_page_types'] ?? array()));
			echo '</div>';
			echo '<label>' . esc_html__('Include Objects', 'backstage-venue-manager');
			echo '<textarea class="vms-status-list-source" data-list-ui="object-picker" data-value-type="int" data-row-placeholder="123" name="include_object_ids_raw" rows="3" placeholder="123&#10;456">' . esc_textarea((string) ($notice['include_object_ids_raw'] ?? '')) . '</textarea>';
			echo '<span class="description">' . esc_html__('Search and add pages/posts/products/events, or add IDs manually.', 'backstage-venue-manager') . '</span>';
			echo '</label>';
			echo '<label>' . esc_html__('Exclude Objects', 'backstage-venue-manager');
			echo '<textarea class="vms-status-list-source" data-list-ui="object-picker" data-value-type="int" data-row-placeholder="789" name="exclude_object_ids_raw" rows="3" placeholder="789">' . esc_textarea((string) ($notice['exclude_object_ids_raw'] ?? '')) . '</textarea>';
			echo '<span class="description">' . esc_html__('Use excludes to suppress specific IDs even if include rules match.', 'backstage-venue-manager') . '</span>';
			echo '</label>';
			echo '<label>' . esc_html__('URL Contains', 'backstage-venue-manager');
			echo '<textarea class="vms-status-list-source" data-list-ui="rows" data-value-type="text" data-row-placeholder="/tickets" name="url_contains_raw" rows="3" placeholder="/tickets">' . esc_textarea((string) ($notice['url_contains_raw'] ?? '')) . '</textarea>';
			echo '<span class="description">' . esc_html__('Add one URL fragment per row.', 'backstage-venue-manager') . '</span>';
			echo '</label>';
			echo '<label>' . esc_html__('URL Excludes', 'backstage-venue-manager');
			echo '<textarea class="vms-status-list-source" data-list-ui="rows" data-value-type="text" data-row-placeholder="/thank-you" name="url_excludes_raw" rows="3" placeholder="/thank-you">' . esc_textarea((string) ($notice['url_excludes_raw'] ?? '')) . '</textarea>';
			echo '<span class="description">' . esc_html__('Exclude URL fragments that should never show this notice.', 'backstage-venue-manager') . '</span>';
			echo '</label>';

			echo '<label>' . esc_html__('User Mode', 'backstage-venue-manager') . '<select name="user_mode">';
			foreach (vms_status_notice_allowed_user_mode() as $mode) {
				echo '<option value="' . esc_attr($mode) . '"' . selected((string) ($notice['user_mode'] ?? ''), $mode, false) . '>' . esc_html($mode) . '</option>';
			}
			echo '</select></label>';
			echo '<div><span class="vms-status-label">' . esc_html__('Roles Include', 'backstage-venue-manager') . '</span>';
			vms_status_notice_render_checkbox_group('roles_include', $role_labels, (array) ($notice['roles_include'] ?? array()));
			echo '</div>';
			echo '<div><span class="vms-status-label">' . esc_html__('Roles Exclude', 'backstage-venue-manager') . '</span>';
			vms_status_notice_render_checkbox_group('roles_exclude', $role_labels, (array) ($notice['roles_exclude'] ?? array()));
			echo '</div>';
			echo '<label>' . esc_html__('User IDs Include', 'backstage-venue-manager');
			echo '<textarea class="vms-status-list-source" data-list-ui="rows" data-value-type="int" data-row-placeholder="12" name="user_ids_include_raw" rows="3" placeholder="12&#10;47">' . esc_textarea((string) ($notice['user_ids_include_raw'] ?? '')) . '</textarea>';
			echo '<span class="description">' . esc_html__('Optional advanced filter for specific logged-in users.', 'backstage-venue-manager') . '</span>';
			echo '</label>';

			echo '<label>' . esc_html__('Device Mode', 'backstage-venue-manager') . '<select name="device_mode">';
			foreach ($device_labels as $key => $label) {
				echo '<option value="' . esc_attr((string) $key) . '"' . selected((string) ($notice['device_mode'] ?? ''), (string) $key, false) . '>' . esc_html((string) $label) . '</option>';
			}
			echo '</select></label>';
			echo '<div><span class="vms-status-label">' . esc_html__('Browser Include', 'backstage-venue-manager') . '</span>';
			vms_status_notice_render_checkbox_group('browser_include', $browser_labels, (array) ($notice['browser_include'] ?? array()));
			echo '</div>';
			echo '<div><span class="vms-status-label">' . esc_html__('OS Include', 'backstage-venue-manager') . '</span>';
			vms_status_notice_render_checkbox_group('os_include', $os_labels, (array) ($notice['os_include'] ?? array()));
			echo '</div>';
			echo '</div>';
			echo '</section>';

			echo '<section class="vms-status-card">';
			echo '<h2>' . esc_html__('Timing', 'backstage-venue-manager') . '</h2>';
			echo '<div class="vms-status-grid">';
			echo '<label>' . esc_html__('Schedule Mode', 'backstage-venue-manager') . '<select name="schedule_mode"><option value="always"' . selected((string) ($notice['schedule_mode'] ?? ''), 'always', false) . '>' . esc_html__('Always', 'backstage-venue-manager') . '</option><option value="scheduled"' . selected((string) ($notice['schedule_mode'] ?? ''), 'scheduled', false) . '>' . esc_html__('Scheduled', 'backstage-venue-manager') . '</option></select></label>';
			echo '<label>' . esc_html__('Start At', 'backstage-venue-manager') . '<input type="datetime-local" name="start_at" value="' . esc_attr((string) ($notice['start_at'] ?? '')) . '"></label>';
			echo '<label>' . esc_html__('End At', 'backstage-venue-manager') . '<input type="datetime-local" name="end_at" value="' . esc_attr((string) ($notice['end_at'] ?? '')) . '"></label>';
			echo '<label>' . esc_html__('Frequency', 'backstage-venue-manager') . '<select name="frequency">';
			foreach (vms_status_notice_allowed_frequency() as $frequency) {
				echo '<option value="' . esc_attr($frequency) . '"' . selected((string) ($notice['frequency'] ?? ''), $frequency, false) . '>' . esc_html($frequency) . '</option>';
			}
			echo '</select></label>';
			echo '<label><input type="checkbox" name="metrics_enabled" value="1"' . checked(1, (int) ($notice['metrics_enabled'] ?? 0), false) . '> ' . esc_html__('Enable metrics counters', 'backstage-venue-manager') . '</label>';
			echo '</div>';
			echo '</section>';

			echo '<section class="vms-status-card">';
			echo '<h2>' . esc_html__('Preview & Debug', 'backstage-venue-manager') . '</h2>';
			echo '<p id="vms-status-audience-summary" class="description">' . esc_html((string) ($notice['audience_summary'] ?? '')) . '</p>';
			echo '<div id="vms-status-preview" class="vms-status-preview" data-intensity="' . esc_attr((string) (int) ($notice['intensity'] ?? 2)) . '">';
			echo '<div class="vms-notice vms-notice--preview vms-notice--intensity-' . esc_attr((string) (int) ($notice['intensity'] ?? 2)) . '">';
			echo '<strong class="vms-notice__headline">' . esc_html((string) (($notice['headline'] ?? '') !== '' ? $notice['headline'] : __('Preview headline', 'backstage-venue-manager'))) . '</strong>';
			echo '<div class="vms-notice__body">' . wp_kses_post((string) (($notice['body_html'] ?? '') !== '' ? $notice['body_html'] : __('Preview body text.', 'backstage-venue-manager'))) . '</div>';
			echo '</div>';
			echo '</div>';

			echo '<div class="vms-status-simulator">';
			echo '<label>' . esc_html__('Simulated Device', 'backstage-venue-manager') . '<select id="vms-status-sim-device"><option value="mobile">mobile</option><option value="tablet">tablet</option><option value="desktop">desktop</option></select></label>';
			echo '<label>' . esc_html__('Simulated Browser', 'backstage-venue-manager') . '<select id="vms-status-sim-browser"><option value="safari_ios">safari_ios</option><option value="safari_mac">safari_mac</option><option value="chrome">chrome</option><option value="firefox">firefox</option><option value="edge">edge</option><option value="other">other</option></select></label>';
			echo '<label>' . esc_html__('Simulated OS', 'backstage-venue-manager') . '<select id="vms-status-sim-os"><option value="ios">ios</option><option value="android">android</option><option value="windows">windows</option><option value="macos">macos</option><option value="linux">linux</option><option value="other">other</option></select></label>';
			echo '<label>' . esc_html__('Logged-in', 'backstage-venue-manager') . '<select id="vms-status-sim-logged"><option value="1">yes</option><option value="0">no</option></select></label>';
			if (!empty($role_labels)) {
				echo '<div class="vms-status-span-2"><span class="vms-status-label">' . esc_html__('Simulated Roles', 'backstage-venue-manager') . '</span>';
				echo '<div class="vms-status-grid-checks">';
				foreach ($role_labels as $role_slug => $role_label) {
					echo '<label><input type="checkbox" class="vms-status-sim-role" value="' . esc_attr((string) $role_slug) . '"> ' . esc_html((string) $role_label) . '</label>';
				}
				echo '</div>';
				echo '</div>';
			}
			echo '<label>' . esc_html__('Simulated Page Type', 'backstage-venue-manager') . '<select id="vms-status-sim-page"><option value="event">event</option><option value="product">product</option><option value="cart">cart</option><option value="checkout">checkout</option><option value="account">account</option><option value="ticketing">ticketing</option><option value="generic">generic</option></select></label>';
			echo '<button type="button" class="button" id="vms-status-run-sim">' . esc_html__('Run Targeting Simulator', 'backstage-venue-manager') . '</button>';
			echo '<p id="vms-status-sim-result" class="vms-status-sim-result" aria-live="polite"></p>';
			echo '</div>';
			echo '</section>';

			echo '<p class="submit">';
			echo '<button type="submit" class="button button-primary">' . esc_html__('Save Status Notice', 'backstage-venue-manager') . '</button> ';
			echo '<a class="button" href="' . esc_url(vms_status_notice_admin_page_url()) . '">' . esc_html__('Back to Notices', 'backstage-venue-manager') . '</a>';
			echo '</p>';
			echo '</form>';
		};

		if (function_exists('bvmgr_admin_ui_render_shell')) {
			bvmgr_admin_ui_render_shell(
				array(
					'title' => $title,
					'subtitle' => $subtitle,
					'shell_id' => 'vms-status-notice-edit-wrap',
					'notices_callback' => 'vms_status_notice_notice_bar',
				),
				$content
			);
			return;
		}

		echo '<div class="wrap">';
		vms_status_notice_notice_bar();
		$content();
		echo '</div>';
	}
}

if (!function_exists('vms_status_notice_admin_redirect')) {
	function vms_status_notice_admin_redirect(array $args = array()): void
	{
		wp_safe_redirect(vms_status_notice_admin_page_url($args));
		exit;
	}
}

if (!function_exists('vms_status_notice_handle_save')) {
	function vms_status_notice_handle_save(): void
	{
		if (!current_user_can(vms_status_notices_capability())) {
			wp_die(esc_html__('Access denied.', 'backstage-venue-manager'));
		}
		check_admin_referer('vms_status_notice_save');

		$notice_id = isset($_POST['notice_id']) ? absint(wp_unslash((string) $_POST['notice_id'])) : 0;
		$raw = isset($_POST) ? (array) wp_unslash($_POST) : array();
		$saved_id = vms_status_notice_save($notice_id, $raw);
		if ($saved_id <= 0) {
			vms_status_notice_admin_redirect(array('view' => 'edit', 'id' => $notice_id));
		}

		vms_status_notice_admin_redirect(array('view' => 'edit', 'id' => $saved_id, 'vms_status_notice_result' => 'saved'));
	}
}
add_action('admin_post_vms_status_notice_save', 'vms_status_notice_handle_save');

if (!function_exists('vms_status_notice_handle_duplicate')) {
	function vms_status_notice_handle_duplicate(): void
	{
		if (!current_user_can(vms_status_notices_capability())) {
			wp_die(esc_html__('Access denied.', 'backstage-venue-manager'));
		}
		$notice_id = isset($_GET['id']) ? absint(wp_unslash((string) $_GET['id'])) : 0;
		check_admin_referer('vms_status_notice_duplicate_' . $notice_id);

		$notice = vms_status_notice_get($notice_id);
		if (!is_array($notice)) {
			vms_status_notice_admin_redirect();
		}

		$raw = $notice;
		$raw['title'] = ((string) ($notice['title'] ?? '')) . ' (Copy)';
		$raw['include_object_ids_raw'] = implode("\n", array_map('intval', (array) ($notice['include_object_ids'] ?? array())));
		$raw['exclude_object_ids_raw'] = implode("\n", array_map('intval', (array) ($notice['exclude_object_ids'] ?? array())));
		$raw['user_ids_include_raw'] = implode("\n", array_map('intval', (array) ($notice['user_ids_include'] ?? array())));
		$raw['url_contains_raw'] = implode("\n", array_map('strval', (array) ($notice['url_contains'] ?? array())));
		$raw['url_excludes_raw'] = implode("\n", array_map('strval', (array) ($notice['url_excludes'] ?? array())));
			$new_id = vms_status_notice_save(0, $raw);

			if ($new_id > 0) {
				vms_status_notice_admin_redirect(array('view' => 'edit', 'id' => $new_id, 'vms_status_notice_result' => 'duplicated'));
			}

		vms_status_notice_admin_redirect();
	}
}
add_action('admin_post_vms_status_notice_duplicate', 'vms_status_notice_handle_duplicate');

if (!function_exists('vms_status_notice_handle_toggle')) {
	function vms_status_notice_handle_toggle(): void
	{
		if (!current_user_can(vms_status_notices_capability())) {
			wp_die(esc_html__('Access denied.', 'backstage-venue-manager'));
		}
		$notice_id = isset($_GET['id']) ? absint(wp_unslash((string) $_GET['id'])) : 0;
		check_admin_referer('vms_status_notice_toggle_' . $notice_id);
			if ($notice_id > 0) {
				$enabled = isset($_GET['enabled']) ? absint(wp_unslash((string) $_GET['enabled'])) : 0;
				update_post_meta($notice_id, '_vms_notice_enabled', $enabled ? 1 : 0);
				update_post_meta($notice_id, '_vms_notice_updated_at', time());
			}
			vms_status_notice_admin_redirect(array('vms_status_notice_result' => 'toggled'));
		}
}
add_action('admin_post_vms_status_notice_toggle', 'vms_status_notice_handle_toggle');

if (!function_exists('vms_status_notice_handle_trash')) {
	function vms_status_notice_handle_trash(): void
	{
		if (!current_user_can(vms_status_notices_capability())) {
			wp_die(esc_html__('Access denied.', 'backstage-venue-manager'));
		}
		$notice_id = isset($_GET['id']) ? absint(wp_unslash((string) $_GET['id'])) : 0;
		check_admin_referer('vms_status_notice_trash_' . $notice_id);
			if ($notice_id > 0) {
				wp_trash_post($notice_id);
			}
			vms_status_notice_admin_redirect(array('vms_status_notice_result' => 'trashed'));
		}
}
add_action('admin_post_vms_status_notice_trash', 'vms_status_notice_handle_trash');

if (!function_exists('vms_status_notice_handle_bulk')) {
	function vms_status_notice_handle_bulk(): void
	{
		if (!current_user_can(vms_status_notices_capability())) {
			wp_die(esc_html__('Access denied.', 'backstage-venue-manager'));
		}
		check_admin_referer('vms_status_notice_bulk');

		$bulk_action = isset($_POST['bulk_action']) ? sanitize_key((string) wp_unslash($_POST['bulk_action'])) : '';
		$notice_ids = isset($_POST['notice_ids']) ? array_map('absint', (array) wp_unslash($_POST['notice_ids'])) : array();
		$notice_ids = array_values(array_unique(array_filter($notice_ids, static function (int $id): bool {
			return $id > 0;
		})));

		if (empty($notice_ids) || !in_array($bulk_action, array('enable', 'disable', 'trash'), true)) {
			vms_status_notice_admin_redirect();
		}

		$updated = 0;
		foreach ($notice_ids as $notice_id) {
			if (get_post_type($notice_id) !== 'vms_notice') {
				continue;
			}

			if ($bulk_action === 'trash') {
				if (wp_trash_post($notice_id)) {
					$updated += 1;
				}
				continue;
			}

			$enabled = $bulk_action === 'enable' ? 1 : 0;
			update_post_meta($notice_id, '_vms_notice_enabled', $enabled);
			update_post_meta($notice_id, '_vms_notice_updated_at', time());
			$updated += 1;
		}

		vms_status_notice_admin_redirect(array(
			'vms_status_notice_result' => 'bulk_updated',
			'bulk_count' => $updated,
		));
	}
}
add_action('admin_post_vms_status_notice_bulk', 'vms_status_notice_handle_bulk');

if (!function_exists('vms_status_notice_object_search_post_types')) {
	function vms_status_notice_object_search_post_types(): array
	{
		$post_types = array();
		foreach (array('page', 'post', 'product', 'tribe_events') as $candidate) {
			if (post_type_exists($candidate)) {
				$post_types[] = $candidate;
			}
		}

		if (!empty($post_types)) {
			return array_values(array_unique($post_types));
		}

		$public_post_types = get_post_types(array('public' => true), 'names');
		foreach ((array) $public_post_types as $post_type) {
			$post_type = sanitize_key((string) $post_type);
			if ($post_type === '' || $post_type === 'attachment') {
				continue;
			}
			$post_types[] = $post_type;
		}

		return array_values(array_unique($post_types));
	}
}

if (!function_exists('vms_status_notice_handle_object_search')) {
	function vms_status_notice_handle_object_search(): void
	{
		if (!current_user_can(vms_status_notices_capability())) {
			wp_send_json_error(array('message' => __('Access denied.', 'backstage-venue-manager')), 403);
		}
		check_ajax_referer('vms_status_notice_object_search', 'nonce');

		$query = isset($_GET['q']) ? sanitize_text_field((string) wp_unslash($_GET['q'])) : '';
		$query_length = function_exists('mb_strlen') ? mb_strlen($query, 'UTF-8') : strlen($query);
		if ($query_length < 2) {
			wp_send_json_success(array('items' => array()));
		}

		$post_types = vms_status_notice_object_search_post_types();
		if (empty($post_types)) {
			wp_send_json_success(array('items' => array()));
		}

		$posts = get_posts(array(
			'post_type' => $post_types,
			'post_status' => array('publish', 'private', 'draft', 'pending', 'future'),
			's' => $query,
			'posts_per_page' => 20,
			'orderby' => 'relevance',
			'order' => 'DESC',
			'no_found_rows' => true,
			'suppress_filters' => false,
		));

		$items = array();
		foreach ($posts as $post) {
			if (!$post instanceof WP_Post) {
				continue;
			}

			$post_type = sanitize_key((string) $post->post_type);
			$post_type_object = get_post_type_object($post_type);
			$post_type_label = $post_type;
			if (is_object($post_type_object) && isset($post_type_object->labels->singular_name)) {
				$post_type_label = (string) $post_type_object->labels->singular_name;
			}

			$title = trim((string) $post->post_title);
			if ($title === '') {
				/* translators: %d: post ID. */
				$title = sprintf(__('Untitled (#%d)', 'backstage-venue-manager'), (int) $post->ID);
			}

			$items[] = array(
				'id' => (int) $post->ID,
				'title' => $title,
				'post_type' => $post_type,
				'post_type_label' => $post_type_label,
				'status' => sanitize_key((string) $post->post_status),
			);
		}

		wp_send_json_success(array('items' => $items));
	}
}
add_action('wp_ajax_vms_status_notice_search_objects', 'vms_status_notice_handle_object_search');
