<?php
defined('ABSPATH') || exit;

if (!function_exists('bvmgr_tasks_admin_parent_slug')) {
	function bvmgr_tasks_admin_parent_slug(): string
	{
		return 'vms-dashboard';
	}
}

if (!function_exists('bvmgr_tasks_admin_page_url')) {
	/**
	 * @param array<string,mixed> $args
	 */
	function bvmgr_tasks_admin_page_url(string $page_slug, array $args = array()): string
	{
		$base = admin_url('admin.php?page=' . urlencode($page_slug));
		if (empty($args)) {
			return $base;
		}
		return add_query_arg($args, $base);
	}
}

if (!function_exists('bvmgr_tasks_admin_query_arg')) {
	function bvmgr_tasks_admin_query_arg(string $key): string
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin display/filter state only; no mutation depends on these query args.
		if (!isset($_GET[$key])) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only admin display/filter state is unslashed here and sanitized or cast by the caller.
		return (string) wp_unslash($_GET[$key]);
	}
}

if (!function_exists('bvmgr_tasks_admin_request_arg')) {
	function bvmgr_tasks_admin_request_arg(string $key): string
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Redirect-only return context is allowlisted to known admin destinations and does not mutate state on its own.
		if (!isset($_REQUEST[$key])) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Redirect-only return context is unslashed here and sanitized or cast by the caller.
		return (string) wp_unslash($_REQUEST[$key]);
	}
}

if (!function_exists('bvmgr_tasks_is_event_plan_edit_screen')) {
	function bvmgr_tasks_is_event_plan_edit_screen(): bool
	{
		if (!is_admin() || !function_exists('get_current_screen')) {
			return false;
		}
		$screen = get_current_screen();
		if (!is_object($screen)) {
			return false;
		}
		return ($screen->base ?? '') === 'post' && ($screen->post_type ?? '') === 'vms_event_plan';
	}
}

if (!function_exists('bvmgr_tasks_event_plan_metabox_form_id')) {
	function bvmgr_tasks_event_plan_metabox_form_id(int $event_plan_id, string $suffix, int $instance_id = 0): string
	{
		$event_plan_id = absint($event_plan_id);
		$instance_id = absint($instance_id);
		$suffix = sanitize_key($suffix);
		if ($suffix === '') {
			$suffix = 'action';
		}

		$form_id = 'vms-tasks-ep-form-' . $event_plan_id . '-' . $suffix;
		if ($instance_id > 0) {
			$form_id .= '-' . $instance_id;
		}
		return $form_id;
	}
}

if (!function_exists('bvmgr_tasks_event_plan_metabox_register_form')) {
	/**
	 * Register a detached footer form for the Event Plan editor.
	 *
	 * @param array<string,mixed> $hidden_fields
	 */
	function bvmgr_tasks_event_plan_metabox_register_form(string $form_id, string $method, string $action, array $hidden_fields = array()): void
	{
		global $bvmgr_tasks_event_plan_metabox_forms;
		if (!is_array($bvmgr_tasks_event_plan_metabox_forms)) {
			$bvmgr_tasks_event_plan_metabox_forms = array();
		}
		$bvmgr_tasks_event_plan_metabox_forms[$form_id] = array(
			'method' => (strtolower($method) === 'get') ? 'get' : 'post',
			'action' => esc_url_raw($action),
			'hidden_fields' => $hidden_fields,
		);
	}
}

if (!function_exists('bvmgr_tasks_render_event_plan_metabox_footer_forms')) {
	function bvmgr_tasks_render_event_plan_metabox_footer_forms(): void
	{
		if (!bvmgr_tasks_is_event_plan_edit_screen()) {
			return;
		}

		global $bvmgr_tasks_event_plan_metabox_forms;
		if (!is_array($bvmgr_tasks_event_plan_metabox_forms) || empty($bvmgr_tasks_event_plan_metabox_forms)) {
			return;
		}

		foreach ($bvmgr_tasks_event_plan_metabox_forms as $form_id => $form) {
			$form_id = sanitize_html_class((string) $form_id);
			$method = (($form['method'] ?? 'post') === 'get') ? 'get' : 'post';
			$action = (string) ($form['action'] ?? '');
			$hidden_fields = is_array($form['hidden_fields'] ?? null) ? (array) $form['hidden_fields'] : array();
			echo '<form id="' . esc_attr($form_id) . '" method="' . esc_attr($method) . '" action="' . esc_url($action) . '" class="vms-tasks-detached-form" style="display:none;">';
			foreach ($hidden_fields as $name => $value) {
				if ($value === null) {
					continue;
				}
				echo '<input type="hidden" name="' . esc_attr((string) $name) . '" value="' . esc_attr((string) $value) . '" />';
			}
			echo '</form>';
		}
	}
}
add_action('admin_footer', 'bvmgr_tasks_render_event_plan_metabox_footer_forms', 45);

if (!function_exists('bvmgr_tasks_admin_render_notices')) {
	function bvmgr_tasks_admin_render_notices(): void
	{
		$notice = sanitize_key(bvmgr_tasks_admin_query_arg('vms_tasks_notice'));
		$message = sanitize_text_field(bvmgr_tasks_admin_query_arg('vms_tasks_message'));
		if ($notice === '' && $message === '') {
			return;
		}

		$type = 'info';
		if ($notice === 'error') {
			$type = 'error';
		} elseif ($notice === 'success') {
			$type = 'success';
		}

		echo '<div class="notice notice-' . esc_attr($type) . '"><p>';
		echo esc_html($message !== '' ? $message : __('Staff Tasks action completed.', 'backstage-venue-manager'));
		echo '</p></div>';
	}
}

if (!function_exists('bvmgr_tasks_admin_get_venues')) {
	/** @return array<int,string> */
	function bvmgr_tasks_admin_get_venues(): array
	{
		$ids = get_posts(array(
			'post_type' => 'vms_venue',
			'post_status' => array('publish', 'private', 'draft', 'pending', 'future'),
			'posts_per_page' => -1,
			'fields' => 'ids',
			'orderby' => 'title',
			'order' => 'ASC',
			'no_found_rows' => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		));
		if (!is_array($ids)) {
			return array();
		}

		$out = array();
		foreach ($ids as $id) {
			$venue_id = absint($id);
			if ($venue_id <= 0) {
				continue;
			}
			$label = trim((string) get_the_title($venue_id));
			if ($label === '') {
				/* translators: %d: venue post ID. */
				$label = sprintf(__('Venue #%d', 'backstage-venue-manager'), $venue_id);
			}
			$out[$venue_id] = $label;
		}
		asort($out);
		return $out;
	}
}

if (!function_exists('bvmgr_tasks_admin_get_user_options')) {
	/** @return array<int,string> */
	function bvmgr_tasks_admin_get_user_options(): array
	{
		$users = get_users(array(
			'orderby' => 'display_name',
			'order' => 'ASC',
			'fields' => array('ID', 'display_name', 'user_email'),
		));
		$out = array();
		foreach ((array) $users as $user) {
			$uid = isset($user->ID) ? absint($user->ID) : 0;
			if ($uid <= 0) {
				continue;
			}
			$label = (string) ($user->display_name ?? '');
			$email = (string) ($user->user_email ?? '');
			if ($email !== '') {
				$label .= ' (' . $email . ')';
			}
			$out[$uid] = $label;
		}
		return $out;
	}
}

if (!function_exists('bvmgr_tasks_admin_get_role_options')) {
	/** @return array<string,string> */
	function bvmgr_tasks_admin_get_role_options(bool $include_inactive = true): array
	{
		$out = array();

		if (function_exists('bvmgr_staffing_get_role_catalog')) {
			$rows = bvmgr_staffing_get_role_catalog($include_inactive);
			foreach ((array) $rows as $row) {
				$slug = sanitize_key((string) ($row['slug'] ?? ''));
				if ($slug === '') {
					continue;
				}
				$name = trim((string) ($row['name'] ?? $slug));
				if ($name === '') {
					$name = $slug;
				}
				$inactive = !empty($include_inactive) && empty($row['is_active']);
				$out[$slug] = $inactive
					? sprintf(
						/* translators: %s is a role name. */
						__('%s (inactive)', 'backstage-venue-manager'),
						$name
					)
					: $name;
			}
		} elseif (taxonomy_exists('vms_staff_role')) {
			$terms = get_terms(array(
				'taxonomy' => 'vms_staff_role',
				'hide_empty' => false,
				'orderby' => 'name',
				'order' => 'ASC',
			));
			if (!is_wp_error($terms)) {
				foreach ((array) $terms as $term) {
					$slug = sanitize_key((string) ($term->slug ?? ''));
					if ($slug === '') {
						continue;
					}
					$out[$slug] = trim((string) ($term->name ?? $slug));
				}
			}
		}

		asort($out);
		return $out;
	}
}

if (!function_exists('bvmgr_tasks_admin_get_event_type_options')) {
	/** @return array<string,string> */
	function bvmgr_tasks_admin_get_event_type_options(): array
	{
		global $wpdb;
		$postmeta = $wpdb->postmeta;
		$types = array();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Staff Tasks admin selector reads distinct event-type values from wp_postmeta with prepared identifier/filter values, and the selector must stay request-fresh after Event Plan meta edits.
			$rows = $wpdb->get_col($wpdb->prepare(
				'SELECT DISTINCT meta_value FROM %i WHERE meta_key = %s AND meta_value <> \'\' ORDER BY meta_value ASC',
				$postmeta,
				'_vms_event_type'
			));
		if (is_array($rows)) {
			foreach ($rows as $row) {
				$key = sanitize_key((string) $row);
				if ($key !== '') {
					$types[$key] = $key;
				}
			}
		}

		return $types;
	}
}

if (!function_exists('bvmgr_tasks_admin_get_checklist_options')) {
	/** @return array<int,string> */
	function bvmgr_tasks_admin_get_checklist_options(bool $active_only = true, string $scope = ''): array
	{
		$filters = $active_only ? array('is_active' => 1) : array();
		$scope = sanitize_key($scope);
		if ($scope !== '') {
			$scope = bvmgr_tasks_sanitize_scope($scope);
			$filters['scope'] = $scope;
		}
		$rows = bvmgr_tasks_get_checklist_templates($filters);
		$options = array();
		foreach ($rows as $row) {
			$checklist_id = absint($row['id'] ?? 0);
			if ($checklist_id <= 0) {
				continue;
			}
			$name = trim((string) ($row['name'] ?? ''));
			if ($name === '') {
				$name = sprintf(
					/* translators: %d is a checklist template id. */
					__('Checklist #%d', 'backstage-venue-manager'),
					$checklist_id
				);
			}
			$apply_mode = bvmgr_tasks_sanitize_apply_mode((string) ($row['apply_mode'] ?? 'default_all_events'));
			$label = $name;
			if ($apply_mode === 'by_venue') {
				$label .= ' (' . __('By venue', 'backstage-venue-manager') . ')';
			} elseif ($apply_mode === 'by_event_type') {
				$label .= ' (' . __('By event type', 'backstage-venue-manager') . ')';
			} else {
				$label .= ' (' . __('Default', 'backstage-venue-manager') . ')';
			}
			$label .= ' - ' . bvmgr_tasks_admin_scope_label((string) ($row['scope'] ?? 'event'));
			$options[$checklist_id] = $label;
		}
		return $options;
	}
}

if (!function_exists('bvmgr_tasks_admin_assignment_mode_label')) {
	function bvmgr_tasks_admin_assignment_mode_label(string $mode): string
	{
		$mode = bvmgr_tasks_sanitize_assignment_mode($mode);
		if ($mode === 'person') {
			return __('Person', 'backstage-venue-manager');
		}
		if ($mode === 'scheduled_role') {
			return __('Scheduled Role', 'backstage-venue-manager');
		}
		return __('Role', 'backstage-venue-manager');
	}
}

if (!function_exists('bvmgr_tasks_admin_assignment_summary')) {
	/**
	 * @param array<string,mixed> $row
	 * @param array<int,string> $users
	 * @param array<string,string> $role_options
	 */
	function bvmgr_tasks_admin_assignment_summary(array $row, array $users, array $role_options): string
	{
		$mode = bvmgr_tasks_sanitize_assignment_mode((string) ($row['assignment_mode'] ?? 'person'));
		$role_key = sanitize_key((string) ($row['role_key'] ?? ''));
		$assignee_id = absint($row['assignee_user_id'] ?? 0);
		$assignee_label = ($assignee_id > 0 && isset($users[$assignee_id]))
			? $users[$assignee_id]
			: __('Unassigned', 'backstage-venue-manager');

		if ($mode === 'person') {
			return bvmgr_tasks_admin_assignment_mode_label($mode) . ': ' . $assignee_label;
		}

		$role_label = ($role_key !== '' && isset($role_options[$role_key]))
			? $role_options[$role_key]
			: ($role_key !== '' ? $role_key : __('Unspecified role', 'backstage-venue-manager'));

		$summary = bvmgr_tasks_admin_assignment_mode_label($mode) . ': ' . $role_label;
		if ($assignee_id > 0) {
			$summary .= '; ' . __('Assigned', 'backstage-venue-manager') . ': ' . $assignee_label;
		} else {
			$summary .= '; ' . __('Unassigned', 'backstage-venue-manager');
		}

		return $summary;
	}
}

if (!function_exists('bvmgr_tasks_admin_scope_label')) {
	function bvmgr_tasks_admin_scope_label(string $scope): string
	{
		$scope = bvmgr_tasks_sanitize_scope($scope);
		if ($scope === 'general') {
			return __('Not linked to an event', 'backstage-venue-manager');
		}
		return __('Event-linked', 'backstage-venue-manager');
	}
}

if (!function_exists('bvmgr_tasks_admin_sanitize_anchor_token')) {
	function bvmgr_tasks_admin_sanitize_anchor_token(string $anchor): string
	{
		if (function_exists('bvmgr_tours_sanitize_anchor_token')) {
			return bvmgr_tours_sanitize_anchor_token($anchor);
		}
		$anchor = strtolower(trim($anchor));
		if ($anchor === '') {
			return '';
		}
		$sanitized = preg_replace('/[^a-z0-9._-]/', '', $anchor);
		return is_string($sanitized) ? $sanitized : '';
	}
}

if (!function_exists('bvmgr_tasks_admin_hover_tip_map')) {
	/**
	 * @return array<string,string>
	 */
	function bvmgr_tasks_admin_hover_tip_map(): array
	{
		$map = array();
		if (!function_exists('bvmgr_get_tour_registry')) {
			return $map;
		}

		foreach ((array) bvmgr_get_tour_registry() as $tour) {
			if (!is_array($tour)) {
				continue;
			}
			$tour_id = sanitize_key((string) ($tour['id'] ?? ''));
			if (strpos($tour_id, 'vms_staff_tasks_') !== 0) {
				continue;
			}
			foreach ((array) ($tour['steps'] ?? array()) as $step) {
				if (!is_array($step)) {
					continue;
				}
				$anchor = bvmgr_tasks_admin_sanitize_anchor_token((string) ($step['anchor'] ?? ''));
				if ($anchor === '' || isset($map[$anchor])) {
					continue;
				}
				$content = wp_strip_all_tags((string) ($step['content'] ?? ''));
				$content = trim(preg_replace('/\s+/', ' ', $content) ?? '');
				if ($content === '') {
					$content = sanitize_text_field((string) ($step['title'] ?? ''));
				}
				if ($content !== '') {
					$map[$anchor] = $content;
				}
			}
		}

		return $map;
	}
}

if (!function_exists('bvmgr_tasks_admin_render_hover_tip_assets')) {
	function bvmgr_tasks_admin_render_hover_tip_assets(): void
	{
		// Inline icon assets were replaced by the standardized tours runtime
		// so all modules share one help icon style + behavior path.
		return;
	}
}

if (!function_exists('bvmgr_tasks_admin_help_button')) {
	function bvmgr_tasks_admin_help_button(string $tour_id, string $anchor = 'tasks.help'): string
	{
		$tour_id = sanitize_key($tour_id);
		$anchor = bvmgr_tasks_admin_sanitize_anchor_token($anchor);
		if ($tour_id === '') {
			return '';
		}
		if (function_exists('bvmgr_render_help_button')) {
			return bvmgr_render_help_button(array(
				'tour_id' => $tour_id,
				'anchor' => $anchor,
				'class' => 'vms-staff-tasks-help-menu',
			));
		}
		$anchor_attr = $anchor !== '' ? ' data-vms-tour="' . esc_attr($anchor) . '"' : '';
		return '<button type="button" class="button button-secondary" data-vms-tour-start="' . esc_attr($tour_id) . '"' . $anchor_attr . '>' . esc_html__('Help', 'backstage-venue-manager') . '</button>';
	}
}

if (!function_exists('bvmgr_tasks_admin_get_event_options')) {
	/** @return array<int,string> */
	function bvmgr_tasks_admin_get_event_options(int $horizon_days = 120): array
	{
		$horizon_days = max(1, min(365, $horizon_days));
		$event_ids = function_exists('bvmgr_tasks_collect_upcoming_event_ids')
			? bvmgr_tasks_collect_upcoming_event_ids($horizon_days)
			: array();

		if (empty($event_ids)) {
			$k_date = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'date') : '_vms_event_date';
			if ($k_date === '') {
				$k_date = '_vms_event_date';
			}

			$fallback_ids = get_posts(array(
				'post_type' => 'vms_event_plan',
				'post_status' => array('publish', 'private', 'draft', 'pending', 'future'),
				'posts_per_page' => 200,
				'fields' => 'ids',
				'orderby' => 'ID',
				'order' => 'ASC',
				'no_found_rows' => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			));
			$event_ids = is_array($fallback_ids) ? array_values(array_filter(array_map('absint', $fallback_ids))) : array();
			$event_dates = array();
			foreach ($event_ids as $event_id) {
				$event_date = trim((string) get_post_meta($event_id, $k_date, true));
				if ($event_date !== '') {
					$event_dates[$event_id] = $event_date;
				}
			}
			$event_ids = array_keys($event_dates);
			usort($event_ids, static function (int $left, int $right) use ($event_dates): int {
				$cmp = strcmp($event_dates[$left], $event_dates[$right]);
				return 0 !== $cmp ? $cmp : ($left <=> $right);
			});
		}

		$venues = bvmgr_tasks_admin_get_venues();
		$options = array();
		foreach ($event_ids as $raw_event_id) {
			$event_id = absint($raw_event_id);
			if ($event_id <= 0) {
				continue;
			}

			$context = bvmgr_tasks_get_event_context($event_id);
			if (!is_array($context)) {
				continue;
			}

			$title = trim((string) ($context['event_title'] ?? get_the_title($event_id)));
			if ($title === '') {
				/* translators: %d: event post ID. */
				$title = sprintf(__('Event #%d', 'backstage-venue-manager'), $event_id);
			}
			$date_ymd = trim((string) ($context['date_ymd'] ?? ''));
			if ($date_ymd === '' && !empty($context['event_start_local'])) {
				$date_ymd = substr((string) $context['event_start_local'], 0, 10);
			}

			$label = $title;
			if ($date_ymd !== '') {
				$label .= ' - ' . $date_ymd;
			}

			$venue_id = absint($context['venue_id'] ?? 0);
			if ($venue_id > 0 && isset($venues[$venue_id])) {
				$label .= ' @ ' . $venues[$venue_id];
			}

			$options[$event_id] = $label;
		}

		return $options;
	}
}

if (!function_exists('bvmgr_tasks_admin_register_menu')) {
	if (!function_exists('bvmgr_tasks_admin_menu_fallback_capability')) {
		function bvmgr_tasks_admin_menu_fallback_capability(): string
		{
			if (function_exists('bvmgr_admin_ui_data_tools_capability')) {
				$cap = sanitize_text_field((string) bvmgr_admin_ui_data_tools_capability());
				if ($cap !== '') {
					return $cap;
				}
			}
			if (function_exists('bvmgr_admin_ui_ops_capability')) {
				$cap = sanitize_text_field((string) bvmgr_admin_ui_ops_capability());
				if ($cap !== '') {
					return $cap;
				}
			}
			return 'manage_options';
		}
	}

	if (!function_exists('bvmgr_tasks_admin_menu_capability')) {
		function bvmgr_tasks_admin_menu_capability(string $preferred_cap): string
		{
			$preferred_cap = sanitize_text_field($preferred_cap);
			if ($preferred_cap !== '' && current_user_can($preferred_cap)) {
				return $preferred_cap;
			}
			$fallback_cap = bvmgr_tasks_admin_menu_fallback_capability();
			if ($fallback_cap !== '' && current_user_can($fallback_cap)) {
				return $fallback_cap;
			}
			return 'manage_options';
		}
	}

	function bvmgr_tasks_admin_register_menu(): void
	{
		$parent = bvmgr_tasks_admin_parent_slug();
		$menu_cap = 'read';

		if (bvmgr_tasks_current_user_can_manage_all()) {
			add_submenu_page(
				$parent,
				__('Tasks', 'backstage-venue-manager'),
				__('Tasks', 'backstage-venue-manager'),
				$menu_cap,
				'vms-tasks',
				'bvmgr_tasks_render_tasks_page'
			);

			add_submenu_page(
				$parent,
				__('Task Templates', 'backstage-venue-manager'),
				__('Task Templates', 'backstage-venue-manager'),
				$menu_cap,
				'vms-task-templates',
				'bvmgr_tasks_render_task_templates_page'
			);

			add_submenu_page(
				$parent,
				__('Checklist Templates', 'backstage-venue-manager'),
				__('Checklist Templates', 'backstage-venue-manager'),
				$menu_cap,
				'vms-checklist-templates',
				'bvmgr_tasks_render_checklist_templates_page'
			);

			add_submenu_page(
				$parent,
				__('Task Settings', 'backstage-venue-manager'),
				__('Task Settings', 'backstage-venue-manager'),
				$menu_cap,
				'vms-task-settings',
				'bvmgr_tasks_render_settings_page'
			);
		}

		if (bvmgr_tasks_current_user_can_view_self()) {
			add_submenu_page(
				$parent,
				__('My Tasks', 'backstage-venue-manager'),
				__('My Tasks', 'backstage-venue-manager'),
				$menu_cap,
				'vms-my-tasks',
				'bvmgr_tasks_render_my_tasks_page'
			);
		}
	}
}
add_action('admin_menu', 'bvmgr_tasks_admin_register_menu', 40);

if (!function_exists('bvmgr_tasks_admin_post_redirect')) {
	/**
	 * @param array<string,mixed> $query
	 */
	function bvmgr_tasks_admin_post_redirect(string $page_slug, array $query = array()): void
	{
		wp_safe_redirect(bvmgr_tasks_admin_page_url($page_slug, $query));
		exit;
	}
}

if (!function_exists('bvmgr_tasks_admin_notice_url')) {
	function bvmgr_tasks_admin_notice_url(string $url, string $notice, string $message): string
	{
		return add_query_arg(array(
			'vms_tasks_notice' => sanitize_key($notice),
			'vms_tasks_message' => sanitize_text_field($message),
		), $url);
	}
}

if (!function_exists('bvmgr_tasks_admin_redirect_url_with_notice')) {
	function bvmgr_tasks_admin_redirect_url_with_notice(string $url, string $notice, string $message): void
	{
		wp_safe_redirect(bvmgr_tasks_admin_notice_url($url, $notice, $message));
		exit;
	}
}

if (!function_exists('bvmgr_tasks_admin_resolve_return_url')) {
	function bvmgr_tasks_admin_resolve_return_url(string $default_page = 'vms-tasks'): string
	{
		$return_page = sanitize_key(bvmgr_tasks_admin_request_arg('return_page'));
		if (in_array($return_page, array('vms-tasks', 'vms-my-tasks'), true)) {
			return bvmgr_tasks_admin_page_url($return_page);
		}
		if ($return_page === 'event-plan') {
			$event_id = absint(bvmgr_tasks_admin_request_arg('event_id'));
			if ($event_id > 0) {
				$edit_url = get_edit_post_link($event_id, 'url');
				if (is_string($edit_url) && $edit_url !== '') {
					return $edit_url;
				}
			}
		}

		return bvmgr_tasks_admin_page_url($default_page);
	}
}

if (!function_exists('bvmgr_tasks_admin_parse_due_input')) {
	function bvmgr_tasks_admin_parse_due_input(string $raw): ?string
	{
		$raw = trim($raw);
		if ($raw === '') {
			return null;
		}

		$normalized = $raw;
		if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $raw)) {
			$normalized = str_replace('T', ' ', $raw) . ':00';
		} elseif (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}$/', $raw)) {
			$normalized = $raw . ':00';
		}

		try {
			$dt = new DateTimeImmutable($normalized, wp_timezone());
			return $dt->format('Y-m-d H:i:s');
		} catch (Exception $e) {
			return null;
		}
	}
}

if (!function_exists('bvmgr_tasks_admin_handle_transition')) {
	function bvmgr_tasks_admin_handle_transition(): void
	{
		$return_url = bvmgr_tasks_admin_resolve_return_url('vms-tasks');
		$nonce = (isset($_POST['_wpnonce']) && !is_array($_POST['_wpnonce']))
			? sanitize_text_field(wp_unslash((string) $_POST['_wpnonce']))
			: '';
		if ($nonce === '' || !wp_verify_nonce($nonce, 'vms_tasks_transition')) {
			bvmgr_tasks_admin_redirect_url_with_notice($return_url, 'error', __('Security check failed.', 'backstage-venue-manager'));
		}

		$instance_id = absint($_POST['instance_id'] ?? 0);
		$target_status = sanitize_key((string) ($_POST['target_status'] ?? 'open'));
		$reason = isset($_POST['reason']) ? sanitize_text_field((string) wp_unslash($_POST['reason'])) : '';

		$row = bvmgr_tasks_get_instance($instance_id);
		if (!is_array($row)) {
			bvmgr_tasks_admin_redirect_url_with_notice($return_url, 'error', __('Task was not found.', 'backstage-venue-manager'));
		}

		$current_user_id = absint(get_current_user_id());
		$can_manage_all = bvmgr_tasks_current_user_can_manage_all();
		$can_self = bvmgr_tasks_current_user_can_complete_self() && absint($row['assignee_user_id'] ?? 0) === $current_user_id;

		if (!$can_manage_all && !$can_self) {
			bvmgr_tasks_admin_redirect_url_with_notice($return_url, 'error', __('You do not have permission to update this task.', 'backstage-venue-manager'));
		}

		if (!$can_manage_all && in_array($target_status, array('canceled', 'open'), true)) {
			bvmgr_tasks_admin_redirect_url_with_notice($return_url, 'error', __('Only admins can cancel or reopen tasks.', 'backstage-venue-manager'));
		}

		$updated = bvmgr_tasks_transition_instance_status($instance_id, $target_status, $reason, $current_user_id);
		if (is_wp_error($updated)) {
			bvmgr_tasks_admin_redirect_url_with_notice($return_url, 'error', $updated->get_error_message());
		}

		bvmgr_tasks_admin_redirect_url_with_notice($return_url, 'success', __('Task updated.', 'backstage-venue-manager'));
	}
}
add_action('admin_post_vms_tasks_transition', 'bvmgr_tasks_admin_handle_transition');

if (!function_exists('bvmgr_tasks_admin_handle_generate_event')) {
	function bvmgr_tasks_admin_handle_generate_event(): void
	{
		$return_url = bvmgr_tasks_admin_resolve_return_url('vms-tasks');
		if (!bvmgr_tasks_current_user_can_manage_all()) {
			bvmgr_tasks_admin_redirect_url_with_notice($return_url, 'error', __('Insufficient permissions.', 'backstage-venue-manager'));
		}
		$event_id = absint($_GET['event_id'] ?? 0);
		$nonce = (isset($_GET['_wpnonce']) && !is_array($_GET['_wpnonce']))
			? sanitize_text_field(wp_unslash((string) $_GET['_wpnonce']))
			: '';
		if (!wp_verify_nonce($nonce, 'vms_tasks_generate_event_' . $event_id)) {
			bvmgr_tasks_admin_redirect_url_with_notice($return_url, 'error', __('Security check failed.', 'backstage-venue-manager'));
		}

		$result = bvmgr_tasks_generate_for_event($event_id, array(
			'allow_supersede' => true,
			'actor_user_id' => get_current_user_id(),
		));
		if (is_wp_error($result)) {
			bvmgr_tasks_admin_redirect_url_with_notice($return_url, 'error', $result->get_error_message());
		}

		$message = sprintf(
			/* translators: 1: created tasks count, 2: superseded tasks count */
			__('Task generation complete. Created %1$d task(s), superseded %2$d.', 'backstage-venue-manager'),
			absint($result['instances_created'] ?? 0),
			absint($result['instances_superseded'] ?? 0)
		);
		bvmgr_tasks_admin_redirect_url_with_notice($return_url, 'success', $message);
	}
}
add_action('admin_post_vms_tasks_generate_event', 'bvmgr_tasks_admin_handle_generate_event');

if (!function_exists('bvmgr_tasks_admin_handle_update_assignment')) {
	function bvmgr_tasks_admin_handle_update_assignment(): void
	{
		$return_url = bvmgr_tasks_admin_resolve_return_url('vms-tasks');
		if (!bvmgr_tasks_current_user_can_manage_all()) {
			bvmgr_tasks_admin_redirect_url_with_notice($return_url, 'error', __('Insufficient permissions.', 'backstage-venue-manager'));
		}
		$nonce = (isset($_POST['_wpnonce']) && !is_array($_POST['_wpnonce']))
			? sanitize_text_field(wp_unslash((string) $_POST['_wpnonce']))
			: '';
		if ($nonce === '' || !wp_verify_nonce($nonce, 'vms_tasks_update_assignment')) {
			bvmgr_tasks_admin_redirect_url_with_notice($return_url, 'error', __('Security check failed.', 'backstage-venue-manager'));
			}

			$instance_id = absint($_POST['instance_id'] ?? 0);
			$event_id = absint($_POST['event_id'] ?? 0);
			$assignment_mode = bvmgr_tasks_sanitize_assignment_mode(sanitize_key((string) wp_unslash($_POST['assignment_mode'] ?? 'person')));
			$role_key = sanitize_key((string) wp_unslash($_POST['role_key'] ?? ''));
			$assignee_user_id = absint($_POST['assignee_user_id'] ?? 0);
			$resolution_message = '';

		if ($assignee_user_id > 0 && $assignment_mode !== 'scheduled_role') {
			$assignment_mode = 'person';
			$role_key = '';
		}

		if (in_array($assignment_mode, array('role', 'scheduled_role'), true) && $role_key === '') {
			bvmgr_tasks_admin_redirect_url_with_notice($return_url, 'error', __('Role key is required for role-based assignment modes.', 'backstage-venue-manager'));
		}

		if ($assignment_mode === 'scheduled_role' && $assignee_user_id <= 0 && $event_id > 0 && function_exists('bvmgr_tasks_resolve_scheduled_role_user_id')) {
			$resolved = bvmgr_tasks_resolve_scheduled_role_user_id($event_id, $role_key);
			$status = (string) ($resolved['status'] ?? 'none');
			if ($status === 'single') {
				$assignee_user_id = absint($resolved['assignee_user_id'] ?? 0);
				if ($assignee_user_id > 0) {
					$resolution_message = __('Scheduled role resolved to the assigned staff member.', 'backstage-venue-manager');
				}
			} elseif ($status === 'multiple') {
				$resolution_message = __('Scheduled role has multiple staff assigned; task remains unassigned.', 'backstage-venue-manager');
			} else {
				$resolution_message = __('No staff member is currently scheduled for that role.', 'backstage-venue-manager');
			}
		}

		$assignment_locked = !empty($_POST['assignment_locked']) && $assignee_user_id > 0;

		$updated = bvmgr_tasks_set_instance_assignment(
			$instance_id,
			$assignee_user_id,
			$assignment_locked,
			get_current_user_id(),
			$assignment_mode,
			$role_key
		);
		if (is_wp_error($updated)) {
			bvmgr_tasks_admin_redirect_url_with_notice($return_url, 'error', $updated->get_error_message());
		}

		$message = __('Assignment updated.', 'backstage-venue-manager');
		if ($resolution_message !== '') {
			$message .= ' ' . $resolution_message;
		}
		bvmgr_tasks_admin_redirect_url_with_notice($return_url, 'success', $message);
	}
}
add_action('admin_post_vms_tasks_update_assignment', 'bvmgr_tasks_admin_handle_update_assignment');

if (!function_exists('bvmgr_tasks_admin_handle_create_one_off')) {
	function bvmgr_tasks_admin_handle_create_one_off(): void
	{
		$return_url = bvmgr_tasks_admin_resolve_return_url('vms-tasks');
		if (!bvmgr_tasks_current_user_can_manage_all()) {
			bvmgr_tasks_admin_redirect_url_with_notice($return_url, 'error', __('Insufficient permissions.', 'backstage-venue-manager'));
		}
		$nonce = (isset($_POST['_wpnonce']) && !is_array($_POST['_wpnonce']))
			? sanitize_text_field(wp_unslash((string) $_POST['_wpnonce']))
			: '';
		if ($nonce === '' || !wp_verify_nonce($nonce, 'vms_tasks_create_one_off')) {
			bvmgr_tasks_admin_redirect_url_with_notice($return_url, 'error', __('Security check failed.', 'backstage-venue-manager'));
		}
		if (!bvmgr_tasks_db_ready()) {
			bvmgr_tasks_admin_redirect_url_with_notice($return_url, 'error', __('Staff Tasks tables are unavailable.', 'backstage-venue-manager'));
		}

		$event_id = absint($_POST['event_id'] ?? 0);
		$venue_id_input = absint($_POST['venue_id'] ?? 0);
		$event = null;
		if ($event_id > 0) {
			$event = bvmgr_tasks_get_event_context($event_id);
			if (!is_array($event)) {
				bvmgr_tasks_admin_redirect_url_with_notice($return_url, 'error', __('Event context is unavailable for task creation.', 'backstage-venue-manager'));
			}
		}

			$title = sanitize_text_field((string) wp_unslash($_POST['title'] ?? ''));
			if ($title === '') {
				bvmgr_tasks_admin_redirect_url_with_notice($return_url, 'error', __('Task title is required.', 'backstage-venue-manager'));
			}
			$instructions = wp_kses_post((string) wp_unslash($_POST['instructions'] ?? ''));
			$priority = bvmgr_tasks_sanitize_priority(sanitize_key((string) wp_unslash($_POST['priority'] ?? 'normal')));
			$is_required = !empty($_POST['is_required']) ? 1 : 0;
			$due_raw = sanitize_text_field((string) wp_unslash($_POST['due_at_local'] ?? ''));
			$due_at_local = bvmgr_tasks_admin_parse_due_input($due_raw);
			$assignment_mode = bvmgr_tasks_sanitize_assignment_mode(sanitize_key((string) wp_unslash($_POST['assignment_mode'] ?? 'person')));
			$role_key = sanitize_key((string) wp_unslash($_POST['role_key'] ?? ''));
			$assignee_user_id = absint($_POST['assignee_user_id'] ?? 0);
			$recurrence_pattern = bvmgr_tasks_sanitize_recurrence_pattern(sanitize_key((string) wp_unslash($_POST['recurrence_pattern'] ?? 'none')));
			$recurrence_every_n_days = absint($_POST['recurrence_every_n_days'] ?? 0);
			$assignment_locked = !empty($_POST['assignment_locked']) && $assignee_user_id > 0;
			$make_repeatable_now = !empty($_POST['make_repeatable_now']);
		$repeatable_checklist_id = absint($_POST['repeatable_checklist_id'] ?? 0);
		if ($due_raw !== '' && $due_at_local === null) {
			bvmgr_tasks_admin_redirect_url_with_notice($return_url, 'error', __('Due date is invalid. Use date and time format from the picker.', 'backstage-venue-manager'));
		}

		if ($assignee_user_id > 0) {
			$assignment_mode = 'person';
			$role_key = '';
		}
		if (in_array($assignment_mode, array('role', 'scheduled_role'), true) && $role_key === '') {
			bvmgr_tasks_admin_redirect_url_with_notice($return_url, 'error', __('Role key is required for role-based assignment modes.', 'backstage-venue-manager'));
		}
		if ($assignment_mode === 'person') {
			$role_key = '';
		}
		if ($event_id <= 0 && $assignment_mode === 'scheduled_role') {
			bvmgr_tasks_admin_redirect_url_with_notice($return_url, 'error', __('Scheduled role assignment requires an event-linked task.', 'backstage-venue-manager'));
		}
		if ($event_id > 0) {
			$recurrence_pattern = 'none';
			$recurrence_every_n_days = 0;
		}
		if ($recurrence_pattern !== 'none' && $due_at_local === null) {
			bvmgr_tasks_admin_redirect_url_with_notice($return_url, 'error', __('Recurring tasks require a due date/time.', 'backstage-venue-manager'));
		}
		if ($recurrence_pattern === 'every_n_days' && ($recurrence_every_n_days < 2 || $recurrence_every_n_days > 365)) {
			bvmgr_tasks_admin_redirect_url_with_notice($return_url, 'error', __('Every N days recurrence must be between 2 and 365.', 'backstage-venue-manager'));
		}

		$resolved_venue_id = 0;
		$resolved_event_type = '';
		if (is_array($event)) {
			$resolved_venue_id = absint($event['venue_id'] ?? 0);
			$resolved_event_type = (string) ($event['event_type'] ?? '');
		} else {
			$resolved_venue_id = $venue_id_input > 0 ? $venue_id_input : 0;
		}

		$created = bvmgr_tasks_insert_instance(array(
			'event_id' => $event_id > 0 ? $event_id : null,
			'venue_id' => $resolved_venue_id > 0 ? $resolved_venue_id : null,
			'event_type' => $resolved_event_type,
			'title' => $title,
			'instructions' => $instructions,
			'priority' => $priority,
			'is_required' => $is_required,
			'due_at_local' => $due_at_local,
			'status' => 'open',
			'assignment_mode' => $assignment_mode,
			'role_key' => $role_key,
			'assignee_user_id' => $assignee_user_id,
			'assignment_locked' => $assignment_locked ? 1 : 0,
			'recurrence_pattern' => $recurrence_pattern,
			'recurrence_every_n_days' => $recurrence_every_n_days,
		));
		if (is_wp_error($created)) {
			bvmgr_tasks_admin_redirect_url_with_notice($return_url, 'error', $created->get_error_message());
		}

		$instance_id = absint($created);
		bvmgr_tasks_log_task_action($instance_id, 'created_ad_hoc', get_current_user_id(), wp_json_encode(array(
			'event_id' => $event_id,
			'title' => $title,
			'is_required' => $is_required ? 1 : 0,
			'assignment_mode' => $assignment_mode,
			'role_key' => $role_key,
			'assignee_user_id' => $assignee_user_id,
			'recurrence_pattern' => $recurrence_pattern,
			'recurrence_every_n_days' => $recurrence_every_n_days,
		)));
		if ($assignee_user_id > 0 && function_exists('bvmgr_tasks_emit_assignment_notification')) {
			$latest = bvmgr_tasks_get_instance($instance_id);
			if (is_array($latest)) {
				bvmgr_tasks_emit_assignment_notification($latest);
			}
		}

		if ($make_repeatable_now) {
			$due_mode = 'none';
			$due_time_local = '';
			if (is_string($due_at_local) && preg_match('/^\d{4}-\d{2}-\d{2}\s(\d{2}:\d{2})/', $due_at_local, $matches)) {
				$due_mode = 'fixed_datetime';
				$due_time_local = (string) $matches[1];
			}

			$template_payload = array(
				'title' => $title,
				'instructions' => $instructions,
				'is_active' => 1,
				'priority' => $priority,
				'required_default' => $is_required ? 1 : 0,
				'scope' => ($event_id > 0 ? 'event' : 'general'),
				'due_mode' => $due_mode,
				'due_offset_minutes' => '',
				'due_time_local' => $due_time_local,
				'assignment_mode' => $assignment_mode,
				'role_key' => $role_key,
				'assignee_user_id' => $assignee_user_id,
			);
			$template_id = bvmgr_tasks_upsert_task_template($template_payload, 0);
			if (is_wp_error($template_id)) {
				$message = sprintf(
					/* translators: %s is an error string from template save. */
					__('Task was created, but repeatable template save failed: %s', 'backstage-venue-manager'),
					$template_id->get_error_message()
				);
				bvmgr_tasks_admin_redirect_url_with_notice($return_url, 'error', $message);
			}

			$template_id = absint($template_id);
			if ($repeatable_checklist_id > 0) {
				$target_checklist = bvmgr_tasks_get_checklist_template($repeatable_checklist_id);
				if (!is_array($target_checklist)) {
					$message = __('Task and repeatable template were created, but selected checklist was not found.', 'backstage-venue-manager');
					bvmgr_tasks_admin_redirect_url_with_notice($return_url, 'error', $message);
				}

				$items = bvmgr_tasks_get_checklist_items($repeatable_checklist_id);
				$sort = count($items) + 1;
				$items[] = array(
					'task_template_id' => $template_id,
					'sort_order' => $sort,
					'overrides' => array(),
				);
				$replace = bvmgr_tasks_replace_checklist_items($repeatable_checklist_id, $items);
				if (is_wp_error($replace)) {
					$message = sprintf(
						/* translators: %s is an error string from checklist update. */
						__('Task and repeatable template were created, but checklist update failed: %s', 'backstage-venue-manager'),
						$replace->get_error_message()
					);
					bvmgr_tasks_admin_redirect_url_with_notice($return_url, 'error', $message);
				}

				$success = __('Task created and saved as a repeatable template. It was added to the selected checklist.', 'backstage-venue-manager');
				bvmgr_tasks_admin_redirect_url_with_notice($return_url, 'success', $success);
			}

			$success = __('Task created and saved as a repeatable template. Add it to a checklist template to activate automatic generation.', 'backstage-venue-manager');
			bvmgr_tasks_admin_redirect_url_with_notice($return_url, 'success', $success);
		}

		if (!empty($_POST['open_repeatable_template'])) {
			$template_url = bvmgr_tasks_admin_page_url('vms-task-templates', array('clone_instance_id' => $instance_id));
			bvmgr_tasks_admin_redirect_url_with_notice($template_url, 'success', __('Task created. Template draft loaded from this task. Save it and add it to a checklist to make it repeatable.', 'backstage-venue-manager'));
		}

		$success_message = __('Task created.', 'backstage-venue-manager');
		if ($recurrence_pattern !== 'none') {
			$success_message .= ' ' . sprintf(
				/* translators: %s is the recurrence label. */
				__('Recurring schedule: %s.', 'backstage-venue-manager'),
				bvmgr_tasks_recurrence_label($recurrence_pattern, $recurrence_every_n_days)
			);
		}
		bvmgr_tasks_admin_redirect_url_with_notice($return_url, 'success', $success_message);
	}
}
add_action('admin_post_vms_tasks_create_one_off', 'bvmgr_tasks_admin_handle_create_one_off');

	if (!function_exists('bvmgr_tasks_admin_page_asset_pages')) {
		/**
		 * @return array<int,string>
		 */
		function bvmgr_tasks_admin_page_asset_pages(): array
		{
			return array(
				'vms-tasks',
				'vms-checklist-templates',
			);
		}
	}

	if (!function_exists('bvmgr_tasks_admin_enqueue_page_assets')) {
		/**
		 * Enqueue Staff Tasks admin-page helpers only on the supported pages.
		 */
		function bvmgr_tasks_admin_enqueue_page_assets(): void
		{
			$page = sanitize_key(bvmgr_tasks_admin_query_arg('page'));
			if (!in_array($page, bvmgr_tasks_admin_page_asset_pages(), true)) {
				return;
			}
			if (!bvmgr_tasks_current_user_can_manage_all()) {
				return;
			}

			wp_enqueue_script(
				'vms-tasks-admin-pages',
				BVMGR_PLUGIN_URL . 'assets/js/vms-tasks-admin-pages.js',
				array(),
				defined('BVMGR_VERSION') ? BVMGR_VERSION : null,
				true
			);
		}
	}
	add_action('admin_enqueue_scripts', 'bvmgr_tasks_admin_enqueue_page_assets', 60);

	if (!function_exists('bvmgr_tasks_admin_enqueue_event_plan_metabox_assets')) {
		/**
		 * Enqueue Staff Tasks metabox JS on the Event Plan edit screen.
	 */
	function bvmgr_tasks_admin_enqueue_event_plan_metabox_assets(): void
	{
		if (!bvmgr_tasks_is_event_plan_edit_screen()) {
			return;
		}
		if (!bvmgr_tasks_current_user_can_manage_all()) {
			return;
		}

		wp_enqueue_script(
			'vms-tasks-event-plan-metabox',
			BVMGR_PLUGIN_URL . 'assets/js/vms-tasks-event-plan-metabox.js',
			array(),
			defined('BVMGR_VERSION') ? BVMGR_VERSION : null,
			true
		);
	}
}
add_action('admin_enqueue_scripts', 'bvmgr_tasks_admin_enqueue_event_plan_metabox_assets', 60);

if (!function_exists('bvmgr_tasks_admin_handle_create_one_off_ajax')) {
	/**
	 * AJAX endpoint for the Event Plan Tasks metabox.
	 *
	 * Uses the same rules as admin_post_vms_tasks_create_one_off but returns JSON.
	 */
	function bvmgr_tasks_admin_handle_create_one_off_ajax(): void
	{
		if (!bvmgr_tasks_current_user_can_manage_all()) {
			wp_send_json_error(array('message' => __('Insufficient permissions.', 'backstage-venue-manager')), 403);
		}
		$nonce = (isset($_POST['nonce']) && !is_array($_POST['nonce']))
			? sanitize_text_field(wp_unslash((string) $_POST['nonce']))
			: '';
		if ($nonce === '' || !wp_verify_nonce($nonce, 'vms_tasks_create_one_off')) {
			wp_send_json_error(array('message' => __('Security check failed.', 'backstage-venue-manager')), 403);
		}
		if (!bvmgr_tasks_db_ready()) {
			wp_send_json_error(array('message' => __('Staff Tasks tables are unavailable.', 'backstage-venue-manager')), 500);
		}

		$event_id = absint($_POST['event_id'] ?? 0);
		if ($event_id <= 0) {
			wp_send_json_error(array('message' => __('Event ID is required.', 'backstage-venue-manager')), 400);
		}
		$event = bvmgr_tasks_get_event_context($event_id);
		if (!is_array($event)) {
			wp_send_json_error(array('message' => __('Event context is unavailable for task creation.', 'backstage-venue-manager')), 400);
		}

			$title = sanitize_text_field((string) wp_unslash($_POST['title'] ?? ''));
			if ($title === '') {
				wp_send_json_error(array('message' => __('Task title is required.', 'backstage-venue-manager')), 400);
			}
			$instructions = wp_kses_post((string) wp_unslash($_POST['instructions'] ?? ''));
			$priority = bvmgr_tasks_sanitize_priority(sanitize_key((string) wp_unslash($_POST['priority'] ?? 'normal')));
			$is_required = !empty($_POST['is_required']) ? 1 : 0;
			$due_raw = sanitize_text_field((string) wp_unslash($_POST['due_at_local'] ?? ''));
			$due_at_local = bvmgr_tasks_admin_parse_due_input($due_raw);
			if ($due_raw !== '' && $due_at_local === null) {
				wp_send_json_error(array('message' => __('Due date is invalid. Use date and time format from the picker.', 'backstage-venue-manager')), 400);
			}
			$assignment_mode = bvmgr_tasks_sanitize_assignment_mode(sanitize_key((string) wp_unslash($_POST['assignment_mode'] ?? 'person')));
			$role_key = sanitize_key((string) wp_unslash($_POST['role_key'] ?? ''));
			$assignee_user_id = absint($_POST['assignee_user_id'] ?? 0);
			$assignment_locked = !empty($_POST['assignment_locked']) && $assignee_user_id > 0;
		$make_repeatable_now = !empty($_POST['make_repeatable_now']);
		$repeatable_checklist_id = absint($_POST['repeatable_checklist_id'] ?? 0);

		if ($assignee_user_id > 0) {
			$assignment_mode = 'person';
			$role_key = '';
		}
		if (in_array($assignment_mode, array('role', 'scheduled_role'), true) && $role_key === '') {
			wp_send_json_error(array('message' => __('Role key is required for role-based assignment modes.', 'backstage-venue-manager')), 400);
		}
		if ($assignment_mode === 'person') {
			$role_key = '';
		}

		$created = bvmgr_tasks_insert_instance(array(
			'event_id' => $event_id,
			'venue_id' => absint($event['venue_id'] ?? 0) ?: null,
			'event_type' => (string) ($event['event_type'] ?? ''),
			'title' => $title,
			'instructions' => $instructions,
			'priority' => $priority,
			'is_required' => $is_required,
			'due_at_local' => $due_at_local,
			'status' => 'open',
			'assignment_mode' => $assignment_mode,
			'role_key' => $role_key,
			'assignee_user_id' => $assignee_user_id,
			'assignment_locked' => $assignment_locked ? 1 : 0,
			'recurrence_pattern' => 'none',
			'recurrence_every_n_days' => 0,
		));
		if (is_wp_error($created)) {
			wp_send_json_error(array('message' => $created->get_error_message()), 500);
		}

		$instance_id = absint($created);
		bvmgr_tasks_log_task_action($instance_id, 'created_ad_hoc', get_current_user_id(), wp_json_encode(array(
			'event_id' => $event_id,
			'title' => $title,
			'is_required' => $is_required ? 1 : 0,
			'assignment_mode' => $assignment_mode,
			'role_key' => $role_key,
			'assignee_user_id' => $assignee_user_id,
		)));
		if ($assignee_user_id > 0 && function_exists('bvmgr_tasks_emit_assignment_notification')) {
			$latest = bvmgr_tasks_get_instance($instance_id);
			if (is_array($latest)) {
				bvmgr_tasks_emit_assignment_notification($latest);
			}
		}

		// Repeatable template save is optional from the metabox.
		if ($make_repeatable_now) {
			$due_mode = 'none';
			$due_time_local = '';
			if (is_string($due_at_local) && preg_match('/^\d{4}-\d{2}-\d{2}\s(\d{2}:\d{2})/', $due_at_local, $matches)) {
				$due_mode = 'fixed_datetime';
				$due_time_local = (string) $matches[1];
			}

			$template_payload = array(
				'title' => $title,
				'instructions' => $instructions,
				'is_active' => 1,
				'priority' => $priority,
				'required_default' => $is_required ? 1 : 0,
				'scope' => 'event',
				'due_mode' => $due_mode,
				'due_offset_minutes' => '',
				'due_time_local' => $due_time_local,
				'assignment_mode' => $assignment_mode,
				'role_key' => $role_key,
				'assignee_user_id' => $assignee_user_id,
			);
			$template_id = bvmgr_tasks_upsert_task_template($template_payload, 0);
			if (is_wp_error($template_id)) {
				wp_send_json_error(array('message' => $template_id->get_error_message()), 500);
			}

			$template_id = absint($template_id);
			if ($repeatable_checklist_id > 0) {
				$target_checklist = bvmgr_tasks_get_checklist_template($repeatable_checklist_id);
				if (!is_array($target_checklist)) {
					wp_send_json_error(array('message' => __('Selected checklist was not found.', 'backstage-venue-manager')), 400);
				}

				$items = bvmgr_tasks_get_checklist_items($repeatable_checklist_id);
				$sort = count($items) + 1;
				$items[] = array(
					'task_template_id' => $template_id,
					'sort_order' => $sort,
					'overrides' => array(),
				);
				$replace = bvmgr_tasks_replace_checklist_items($repeatable_checklist_id, $items);
				if (is_wp_error($replace)) {
					wp_send_json_error(array('message' => $replace->get_error_message()), 500);
				}
			}
		}

		$tasks_url = bvmgr_tasks_admin_page_url('vms-tasks', array('event_id' => $event_id));
		wp_send_json_success(array(
			'instance_id' => $instance_id,
			'tasks_url' => $tasks_url,
			'message' => __('Task created.', 'backstage-venue-manager'),
		));
	}
}
add_action('wp_ajax_vms_tasks_create_one_off_ajax', 'bvmgr_tasks_admin_handle_create_one_off_ajax');

if (!function_exists('bvmgr_tasks_render_tasks_page')) {
	function bvmgr_tasks_render_tasks_page(): void
	{
		if (!bvmgr_tasks_current_user_can_manage_all()) {
			wp_die(esc_html__('Insufficient permissions.', 'backstage-venue-manager'));
		}
		bvmgr_tasks_admin_render_hover_tip_assets();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__('Tasks', 'backstage-venue-manager') . ' ' . bvmgr_tasks_admin_help_button('vms_staff_tasks_overview', 'tasks.help') . '</h1>';
		bvmgr_tasks_admin_render_notices();

		if (!bvmgr_tasks_db_ready()) {
			echo '<div class="notice notice-error"><p>' . esc_html__('Staff Tasks tables are unavailable. Tasks are disabled until schema setup succeeds.', 'backstage-venue-manager') . '</p></div>';
			echo '</div>';
			return;
		}

		$filters = array(
			'task_instance_id' => absint(bvmgr_tasks_admin_query_arg('task_instance_id')),
			'status' => sanitize_key(bvmgr_tasks_admin_query_arg('status')),
			'event_id' => absint(bvmgr_tasks_admin_query_arg('event_id')),
			'event_linkage' => sanitize_key(bvmgr_tasks_admin_query_arg('event_linkage')),
			'assignee_user_id' => absint(bvmgr_tasks_admin_query_arg('assignee_user_id')),
			'role_key' => sanitize_key(bvmgr_tasks_admin_query_arg('role_key')),
			'venue_id' => absint(bvmgr_tasks_admin_query_arg('venue_id')),
			'required_only' => !empty(bvmgr_tasks_admin_query_arg('required_only')) ? 1 : 0,
			'due_bucket' => sanitize_key(bvmgr_tasks_admin_query_arg('due_bucket')),
			'limit' => 500,
		);
		$now_local = bvmgr_tasks_now_local_mysql();
		if ($filters['due_bucket'] === 'overdue') {
			$filters['due_before'] = $now_local;
			$filters['exclude_status'] = 'superseded';
		}

		$rows = bvmgr_tasks_get_instances($filters);
		$users = bvmgr_tasks_admin_get_user_options();
		$role_options = bvmgr_tasks_admin_get_role_options(true);
		$checklist_options = bvmgr_tasks_admin_get_checklist_options(true);
		$checklist_rows = bvmgr_tasks_get_checklist_templates(array('is_active' => 1));
		$checklist_scope_by_id = array();
		foreach ($checklist_rows as $checklist_row) {
			$checklist_id = absint($checklist_row['id'] ?? 0);
			if ($checklist_id <= 0) {
				continue;
			}
			$checklist_scope_by_id[$checklist_id] = bvmgr_tasks_sanitize_scope((string) ($checklist_row['scope'] ?? 'event'));
		}
		$venues = bvmgr_tasks_admin_get_venues();
		$settings = bvmgr_tasks_get_settings();
		$event_options = bvmgr_tasks_admin_get_event_options((int) ($settings['horizon_days'] ?? 120));
		$default_event_id = absint($filters['event_id']);
		if ($default_event_id > 0 && !isset($event_options[$default_event_id])) {
			$context = bvmgr_tasks_get_event_context($default_event_id);
			if (is_array($context)) {
				$event_label = trim((string) ($context['event_title'] ?? get_the_title($default_event_id)));
				$date_ymd = trim((string) ($context['date_ymd'] ?? ''));
				if ($event_label === '') {
					/* translators: %d: event post ID. */
					$event_label = sprintf(__('Event #%d', 'backstage-venue-manager'), $default_event_id);
				}
				if ($date_ymd !== '') {
					$event_label .= ' - ' . $date_ymd;
				}
				$venue_id = absint($context['venue_id'] ?? 0);
				if ($venue_id > 0 && isset($venues[$venue_id])) {
					$event_label .= ' @ ' . $venues[$venue_id];
				}
				$event_options[$default_event_id] = $event_label;
			}
		}

		echo '<form method="get" style="margin:12px 0;" data-vms-tour="tasks.event-filter">';
		echo '<input type="hidden" name="page" value="vms-tasks">';
		if (!empty($filters['task_instance_id'])) {
			echo '<input type="hidden" name="task_instance_id" value="' . esc_attr((string) absint($filters['task_instance_id'])) . '">';
		}
		echo '<label>' . esc_html__('Status', 'backstage-venue-manager') . ' <select name="status">';
		echo '<option value="">' . esc_html__('All', 'backstage-venue-manager') . '</option>';
		foreach (array('open', 'done', 'skipped', 'canceled', 'superseded') as $status) {
			echo '<option value="' . esc_attr($status) . '" ' . selected($filters['status'], $status, false) . '>' . esc_html(ucfirst($status)) . '</option>';
		}
		echo '</select></label> ';
		echo '<label>' . esc_html__('Due', 'backstage-venue-manager') . ' <select name="due_bucket">';
		echo '<option value="">' . esc_html__('Any', 'backstage-venue-manager') . '</option>';
		echo '<option value="overdue" ' . selected($filters['due_bucket'], 'overdue', false) . '>' . esc_html__('Overdue', 'backstage-venue-manager') . '</option>';
		echo '</select></label> ';
		echo '<label>' . esc_html__('Event Linkage', 'backstage-venue-manager') . ' <select name="event_linkage">';
		echo '<option value="">' . esc_html__('All', 'backstage-venue-manager') . '</option>';
		echo '<option value="event" ' . selected($filters['event_linkage'], 'event', false) . '>' . esc_html__('Event-linked', 'backstage-venue-manager') . '</option>';
		echo '<option value="non_event" ' . selected($filters['event_linkage'], 'non_event', false) . '>' . esc_html__('Not linked to an event', 'backstage-venue-manager') . '</option>';
		echo '</select></label> ';
		echo '<label>' . esc_html__('Venue', 'backstage-venue-manager') . ' <select name="venue_id">';
		echo '<option value="0">' . esc_html__('All', 'backstage-venue-manager') . '</option>';
		foreach ($venues as $venue_id => $venue_name) {
			echo '<option value="' . esc_attr((string) $venue_id) . '" ' . selected($filters['venue_id'], $venue_id, false) . '>' . esc_html($venue_name) . '</option>';
		}
		echo '</select></label> ';
		echo '<label>' . esc_html__('Assignee', 'backstage-venue-manager') . ' <select name="assignee_user_id">';
		echo '<option value="0">' . esc_html__('All', 'backstage-venue-manager') . '</option>';
		foreach ($users as $uid => $label) {
			echo '<option value="' . esc_attr((string) $uid) . '" ' . selected($filters['assignee_user_id'], $uid, false) . '>' . esc_html($label) . '</option>';
		}
		echo '</select></label> ';
		echo '<label>' . esc_html__('Role', 'backstage-venue-manager') . ' <select name="role_key">';
		echo '<option value="">' . esc_html__('All', 'backstage-venue-manager') . '</option>';
		foreach ($role_options as $role_key => $role_label) {
			echo '<option value="' . esc_attr($role_key) . '" ' . selected($filters['role_key'], $role_key, false) . '>' . esc_html($role_label) . '</option>';
		}
		echo '</select></label> ';
		echo '<label><input type="checkbox" name="required_only" value="1" ' . checked($filters['required_only'], 1, false) . '> ' . esc_html__('Required only', 'backstage-venue-manager') . '</label> ';
		echo '<button class="button" type="submit">' . esc_html__('Filter', 'backstage-venue-manager') . '</button>';
		echo '</form>';
		if (!empty($filters['task_instance_id'])) {
			$clear_focus_url = bvmgr_tasks_admin_page_url('vms-tasks');
			echo '<p class="description">';
			echo esc_html(sprintf(
				/* translators: %d is a task instance id. */
				__('Focused on task #%d from a notification link.', 'backstage-venue-manager'),
				absint($filters['task_instance_id'])
			));
			echo ' <a href="' . esc_url($clear_focus_url) . '">' . esc_html__('Clear focus', 'backstage-venue-manager') . '</a>';
			echo '</p>';
		}

		echo '<p class="description">' . esc_html__('Regenerate tasks from each Event Plan using the Tasks metabox action "Regenerate Tasks Now" (nonce-protected).', 'backstage-venue-manager') . '</p>';
		echo '<p style="margin:10px 0;padding:10px;border-left:4px solid #2271b1;background:#f0f6fc;" data-vms-tour="tasks.repeatable">';
		echo '<strong>' . esc_html__('Repeatable Tasks Setup:', 'backstage-venue-manager') . '</strong> ';
		echo esc_html__('Create role-based task templates, then include them in checklist templates (default, venue, or event type) so events generate tasks automatically.', 'backstage-venue-manager') . ' ';
		echo '<a class="button button-small" href="' . esc_url(bvmgr_tasks_admin_page_url('vms-task-templates')) . '">' . esc_html__('Task Templates', 'backstage-venue-manager') . '</a> ';
		echo '<a class="button button-small" href="' . esc_url(bvmgr_tasks_admin_page_url('vms-checklist-templates')) . '">' . esc_html__('Checklist Templates', 'backstage-venue-manager') . '</a>';
		echo '</p>';
		echo '<h2 style="margin-top:16px;">' . esc_html__('Add Task', 'backstage-venue-manager') . '</h2>';
			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:8px 0 16px 0;padding:12px;border:1px solid #ccd0d4;background:#fff;" data-vms-tour="tasks.add">';
			wp_nonce_field('vms_tasks_create_one_off');
			echo '<input type="hidden" name="action" value="vms_tasks_create_one_off">';
			echo '<input type="hidden" name="return_page" value="vms-tasks">';
			echo '<p>';
			echo '<label for="vms_tasks_one_off_event"><strong>' . esc_html__('Event', 'backstage-venue-manager') . '</strong></label><br>';
			echo '<select id="vms_tasks_one_off_event" name="event_id" style="min-width:320px;max-width:100%;">';
			echo '<option value="0">' . esc_html__('Not linked to an event', 'backstage-venue-manager') . '</option>';
			foreach ($event_options as $event_id => $event_label) {
				echo '<option value="' . esc_attr((string) $event_id) . '" ' . selected($default_event_id, $event_id, false) . '>' . esc_html($event_label) . '</option>';
			}
			echo '</select>';
			echo '</p>';
			echo '<p id="vms_tasks_create_venue_row">';
			echo '<label for="vms_tasks_one_off_venue"><strong>' . esc_html__('Venue (optional when not linked)', 'backstage-venue-manager') . '</strong></label><br>';
			echo '<select id="vms_tasks_one_off_venue" name="venue_id">';
			echo '<option value="0">' . esc_html__('None', 'backstage-venue-manager') . '</option>';
			foreach ($venues as $venue_id => $venue_name) {
				echo '<option value="' . esc_attr((string) $venue_id) . '">' . esc_html($venue_name) . '</option>';
			}
			echo '</select>';
			echo '</p>';
			echo '<p>';
			echo '<label for="vms_tasks_one_off_title"><strong>' . esc_html__('Task title', 'backstage-venue-manager') . '</strong></label><br>';
			echo '<input id="vms_tasks_one_off_title" type="text" name="title" required class="regular-text" style="max-width:100%;">';
			echo '</p>';
			echo '<p>';
			echo '<label for="vms_tasks_one_off_instructions"><strong>' . esc_html__('Instructions', 'backstage-venue-manager') . '</strong></label><br>';
			echo '<textarea id="vms_tasks_one_off_instructions" name="instructions" rows="2" class="large-text" placeholder="' . esc_attr__('Optional', 'backstage-venue-manager') . '"></textarea>';
			echo '</p>';
			echo '<p>';
			echo '<label for="vms_tasks_one_off_due"><strong>' . esc_html__('Due date/time', 'backstage-venue-manager') . '</strong></label><br>';
			echo '<input id="vms_tasks_one_off_due" type="datetime-local" name="due_at_local"> ';
			echo '<label for="vms_tasks_one_off_priority"><strong>' . esc_html__('Priority', 'backstage-venue-manager') . '</strong></label> ';
			echo '<select id="vms_tasks_one_off_priority" name="priority">';
			foreach (array('low', 'normal', 'high') as $priority) {
				echo '<option value="' . esc_attr($priority) . '"' . selected($priority, 'normal', false) . '>' . esc_html(ucfirst($priority)) . '</option>';
			}
			echo '</select>';
			echo ' <label for="vms_tasks_one_off_recurrence_pattern"><strong>' . esc_html__('Repeats', 'backstage-venue-manager') . '</strong></label> ';
			echo '<select id="vms_tasks_one_off_recurrence_pattern" name="recurrence_pattern">';
			echo '<option value="none">' . esc_html__('Does not repeat', 'backstage-venue-manager') . '</option>';
			echo '<option value="daily">' . esc_html__('Daily', 'backstage-venue-manager') . '</option>';
			echo '<option value="every_n_days">' . esc_html__('Every N days', 'backstage-venue-manager') . '</option>';
			echo '<option value="weekly">' . esc_html__('Weekly', 'backstage-venue-manager') . '</option>';
			echo '<option value="monthly">' . esc_html__('Monthly', 'backstage-venue-manager') . '</option>';
			echo '<option value="quarterly">' . esc_html__('Quarterly', 'backstage-venue-manager') . '</option>';
			echo '<option value="semi_annual">' . esc_html__('Semi-annually', 'backstage-venue-manager') . '</option>';
			echo '<option value="annual">' . esc_html__('Annually', 'backstage-venue-manager') . '</option>';
			echo '</select> ';
			echo '<input id="vms_tasks_one_off_recurrence_n_days" type="number" name="recurrence_every_n_days" min="2" max="365" value="7" style="width:90px;display:none;" placeholder="' . esc_attr__('N days', 'backstage-venue-manager') . '"> ';
			echo '<span class="description" id="vms_tasks_one_off_recurrence_note">' . esc_html__('Recurrence applies to tasks not linked to an event.', 'backstage-venue-manager') . '</span>';
			echo '</p>';
			echo '<p>';
			echo '<label for="vms_tasks_one_off_assignment_mode"><strong>' . esc_html__('Assignment mode', 'backstage-venue-manager') . '</strong></label><br>';
			echo '<select id="vms_tasks_one_off_assignment_mode" name="assignment_mode">';
			echo '<option value="person">' . esc_html__('Person', 'backstage-venue-manager') . '</option>';
			echo '<option value="role">' . esc_html__('Role', 'backstage-venue-manager') . '</option>';
			echo '<option value="scheduled_role" id="vms_tasks_one_off_assignment_scheduled">' . esc_html__('Scheduled Role', 'backstage-venue-manager') . '</option>';
			echo '</select> ';
			echo '<label for="vms_tasks_one_off_role_key"><strong>' . esc_html__('Role', 'backstage-venue-manager') . '</strong></label> ';
			echo '<select id="vms_tasks_one_off_role_key" name="role_key">';
			echo '<option value="">' . esc_html__('Select role', 'backstage-venue-manager') . '</option>';
			foreach ($role_options as $role_key => $role_label) {
				echo '<option value="' . esc_attr($role_key) . '">' . esc_html($role_label) . '</option>';
			}
			echo '</select>';
			echo '</p>';
			echo '<p>';
			echo '<label for="vms_tasks_one_off_assignee"><strong>' . esc_html__('Assignee', 'backstage-venue-manager') . '</strong></label><br>';
			echo '<select id="vms_tasks_one_off_assignee" name="assignee_user_id">';
			echo '<option value="0">' . esc_html__('Unassigned', 'backstage-venue-manager') . '</option>';
			foreach ($users as $uid => $label) {
				echo '<option value="' . esc_attr((string) $uid) . '">' . esc_html($label) . '</option>';
			}
			echo '</select> ';
			echo '<label><input type="checkbox" name="assignment_locked" value="1"> ' . esc_html__('Lock assignment', 'backstage-venue-manager') . '</label> ';
			echo '<label><input type="checkbox" name="is_required" value="1"> ' . esc_html__('Required', 'backstage-venue-manager') . '</label> ';
			echo '<label><input type="checkbox" name="make_repeatable_now" value="1"> ' . esc_html__('Also save as repeatable template now', 'backstage-venue-manager') . '</label> ';
			echo '<label>' . esc_html__('Add to checklist', 'backstage-venue-manager') . ' <select id="vms_tasks_one_off_repeatable_checklist" name="repeatable_checklist_id">';
			echo '<option value="0">' . esc_html__('None (template only)', 'backstage-venue-manager') . '</option>';
			foreach ($checklist_options as $checklist_id => $checklist_label) {
				$checklist_scope = (string) ($checklist_scope_by_id[absint($checklist_id)] ?? 'event');
				echo '<option value="' . esc_attr((string) $checklist_id) . '" data-scope="' . esc_attr($checklist_scope) . '">' . esc_html($checklist_label) . '</option>';
			}
			echo '</select></label>';
			echo '</p>';
			echo '<p><button class="button button-primary" type="submit">' . esc_html__('Create Task', 'backstage-venue-manager') . '</button></p>';
			echo '</form>';

			echo '<table class="widefat striped" data-vms-tour="tasks.list">';
			echo '<thead><tr>';
			echo '<th>' . esc_html__('Task', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Event', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Due', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Required', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Assignment', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Status', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Actions', 'backstage-venue-manager') . '</th>';
			echo '</tr></thead><tbody>';

		if (empty($rows)) {
			echo '<tr><td colspan="7">' . esc_html__('No tasks found for current filters.', 'backstage-venue-manager') . '</td></tr>';
		} else {
			foreach ($rows as $row) {
				$instance_id = absint($row['id'] ?? 0);
				$event_id = absint($row['event_id'] ?? 0);
				$due_at = (string) ($row['due_at_local'] ?? '');
				$assignment_mode = bvmgr_tasks_sanitize_assignment_mode((string) ($row['assignment_mode'] ?? 'person'));
				$current_role_key = sanitize_key((string) ($row['role_key'] ?? ''));
				$assignee_id = absint($row['assignee_user_id'] ?? 0);
				$assignment_summary = bvmgr_tasks_admin_assignment_summary($row, $users, $role_options);
				$status = bvmgr_tasks_sanitize_status((string) ($row['status'] ?? 'open'));
				$clone_url = bvmgr_tasks_admin_page_url('vms-task-templates', array('clone_instance_id' => $instance_id));
				$recurrence_pattern = bvmgr_tasks_sanitize_recurrence_pattern((string) ($row['recurrence_pattern'] ?? 'none'));
				$recurrence_every_n_days = absint($row['recurrence_every_n_days'] ?? 0);
				$recurrence_label = bvmgr_tasks_recurrence_label($recurrence_pattern, $recurrence_every_n_days);

				echo '<tr>';
				echo '<td><strong>' . esc_html((string) ($row['title'] ?? '')) . '</strong><br><span class="description">' . esc_html((string) ($row['priority'] ?? 'normal')) . '</span>';
				if ($recurrence_pattern !== 'none') {
					echo '<br><span class="description">' . esc_html($recurrence_label) . '</span>';
				}
				echo '</td>';
				echo '<td>';
				if ($event_id > 0) {
					echo '<a href="' . esc_url(get_edit_post_link($event_id)) . '">' . esc_html(get_the_title($event_id)) . '</a> <small>#' . esc_html((string) $event_id) . '</small>';
				} else {
					echo '<span class="description">' . esc_html__('Not linked to an event', 'backstage-venue-manager') . '</span>';
				}
				echo '</td>';
				echo '<td>' . esc_html($due_at !== '' ? $due_at : __('No due date', 'backstage-venue-manager')) . '</td>';
				echo '<td>' . (!empty($row['is_required']) ? esc_html__('Yes', 'backstage-venue-manager') : esc_html__('No', 'backstage-venue-manager')) . '</td>';
				echo '<td>' . esc_html($assignment_summary) . '</td>';
				echo '<td>' . esc_html(strtoupper($status)) . '</td>';
				echo '<td>';

				if ($status === 'open') {
					echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:0 0 6px 0;">';
					wp_nonce_field('vms_tasks_transition');
					echo '<input type="hidden" name="action" value="vms_tasks_transition">';
					echo '<input type="hidden" name="return_page" value="vms-tasks">';
					echo '<input type="hidden" name="instance_id" value="' . esc_attr((string) $instance_id) . '">';
					echo '<button class="button button-small" name="target_status" value="done" type="submit">' . esc_html__('Done', 'backstage-venue-manager') . '</button> ';
					echo '<button class="button button-small" name="target_status" value="skipped" type="submit">' . esc_html__('Skip', 'backstage-venue-manager') . '</button> ';
					echo '<input type="text" name="reason" placeholder="' . esc_attr__('Reason for skip/cancel', 'backstage-venue-manager') . '" style="width:180px;"> ';
					echo '<button class="button button-small" name="target_status" value="canceled" type="submit">' . esc_html__('Cancel', 'backstage-venue-manager') . '</button>';
					echo '</form>';
				} else {
					echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:0;">';
					wp_nonce_field('vms_tasks_transition');
					echo '<input type="hidden" name="action" value="vms_tasks_transition">';
					echo '<input type="hidden" name="return_page" value="vms-tasks">';
					echo '<input type="hidden" name="instance_id" value="' . esc_attr((string) $instance_id) . '">';
					echo '<input type="text" name="reason" placeholder="' . esc_attr__('Reason for reopen', 'backstage-venue-manager') . '" style="width:180px;"> ';
					echo '<button class="button button-small" name="target_status" value="open" type="submit">' . esc_html__('Reopen', 'backstage-venue-manager') . '</button>';
					echo '</form>';
				}

				echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:6px 0 0;" data-vms-tour="tasks.assignment">';
				wp_nonce_field('vms_tasks_update_assignment');
				echo '<input type="hidden" name="action" value="vms_tasks_update_assignment">';
				echo '<input type="hidden" name="return_page" value="vms-tasks">';
				echo '<input type="hidden" name="instance_id" value="' . esc_attr((string) $instance_id) . '">';
				echo '<input type="hidden" name="event_id" value="' . esc_attr((string) $event_id) . '">';
				echo '<select name="assignment_mode" style="max-width:150px;">';
				echo '<option value="person" ' . selected($assignment_mode, 'person', false) . '>' . esc_html__('Person', 'backstage-venue-manager') . '</option>';
				echo '<option value="role" ' . selected($assignment_mode, 'role', false) . '>' . esc_html__('Role', 'backstage-venue-manager') . '</option>';
				echo '<option value="scheduled_role" ' . selected($assignment_mode, 'scheduled_role', false) . '>' . esc_html__('Scheduled Role', 'backstage-venue-manager') . '</option>';
				echo '</select> ';
				echo '<select name="role_key" style="max-width:180px;">';
				echo '<option value="">' . esc_html__('Select role', 'backstage-venue-manager') . '</option>';
				foreach ($role_options as $role_key => $role_label) {
					echo '<option value="' . esc_attr($role_key) . '" ' . selected($current_role_key, $role_key, false) . '>' . esc_html($role_label) . '</option>';
				}
				if ($current_role_key !== '' && !isset($role_options[$current_role_key])) {
					echo '<option value="' . esc_attr($current_role_key) . '" selected>' . esc_html($current_role_key) . '</option>';
				}
				echo '</select> ';
				echo '<select name="assignee_user_id" style="max-width:220px;">';
				echo '<option value="0">' . esc_html__('Unassigned', 'backstage-venue-manager') . '</option>';
				foreach ($users as $uid => $label) {
					echo '<option value="' . esc_attr((string) $uid) . '" ' . selected($assignee_id, $uid, false) . '>' . esc_html($label) . '</option>';
				}
				echo '</select> ';
				echo '<label><input type="checkbox" name="assignment_locked" value="1" ' . checked(!empty($row['assignment_locked']) && $assignee_id > 0, true, false) . '> ' . esc_html__('Lock', 'backstage-venue-manager') . '</label> ';
				echo '<button class="button button-small" type="submit">' . esc_html__('Save Assignment', 'backstage-venue-manager') . '</button>';
				echo '</form>';
				echo '<p style="margin:6px 0 0;"><a class="button button-small" href="' . esc_url($clone_url) . '">' . esc_html__('Make Repeatable', 'backstage-venue-manager') . '</a></p>';

				echo '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
		echo '</div>';
	}
}

if (!function_exists('bvmgr_tasks_admin_is_exact_post_request')) {
	function bvmgr_tasks_admin_is_exact_post_request(): bool
	{
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Exact POST gate intentionally accepts only the literal REQUEST_METHOD value "POST".
		$request_method = $_SERVER['REQUEST_METHOD'] ?? null;
		if (!is_scalar($request_method)) {
			return false;
		}

		return 'POST' === wp_unslash($request_method);
	}
}

if (!function_exists('bvmgr_tasks_render_task_templates_page')) {
	function bvmgr_tasks_render_task_templates_page(): void
	{
		if (!bvmgr_tasks_current_user_can_manage_templates()) {
			wp_die(esc_html__('Insufficient permissions.', 'backstage-venue-manager'));
		}
		bvmgr_tasks_admin_render_hover_tip_assets();

		$messages = array();
		$errors = array();
		$edit_id = isset($_GET['template_id']) ? absint($_GET['template_id']) : 0;
		$clone_instance_id = isset($_GET['clone_instance_id']) ? absint($_GET['clone_instance_id']) : 0;

		if (bvmgr_tasks_admin_is_exact_post_request() && isset($_POST['vms_tasks_template_action'])) {
			check_admin_referer('vms_tasks_save_template');
			$action = sanitize_key((string) wp_unslash($_POST['vms_tasks_template_action']));
			$template_id = absint($_POST['template_id'] ?? 0);
			if ($action === 'save') {
				$payload = array(
					'title' => sanitize_text_field((string) wp_unslash($_POST['title'] ?? '')),
					'instructions' => wp_kses_post((string) wp_unslash($_POST['instructions'] ?? '')),
					'is_active' => !empty($_POST['is_active']) ? 1 : 0,
					'priority' => sanitize_key((string) wp_unslash($_POST['priority'] ?? 'normal')),
					'required_default' => !empty($_POST['required_default']) ? 1 : 0,
					'scope' => sanitize_key((string) wp_unslash($_POST['scope'] ?? 'event')),
					'due_mode' => sanitize_key((string) wp_unslash($_POST['due_mode'] ?? 'none')),
					'due_offset_minutes' => sanitize_text_field((string) wp_unslash($_POST['due_offset_minutes'] ?? '')),
					'due_time_local' => sanitize_text_field((string) wp_unslash($_POST['due_time_local'] ?? '')),
					'assignment_mode' => sanitize_key((string) wp_unslash($_POST['assignment_mode'] ?? 'role')),
					'role_key' => sanitize_key((string) wp_unslash($_POST['role_key'] ?? '')),
					'assignee_user_id' => absint($_POST['assignee_user_id'] ?? 0),
				);
				$saved = bvmgr_tasks_upsert_task_template($payload, $template_id);
				if (is_wp_error($saved)) {
					$errors[] = $saved->get_error_message();
				} else {
					$messages[] = __('Task template saved.', 'backstage-venue-manager');
					$edit_id = absint($saved);
				}
			}
		}

		$current = $edit_id > 0 ? bvmgr_tasks_get_task_template($edit_id) : null;
		if (!is_array($current) && $clone_instance_id > 0) {
			$source = bvmgr_tasks_get_instance($clone_instance_id);
			if (is_array($source)) {
				$due_at_local = trim((string) ($source['due_at_local'] ?? ''));
				$due_time_local = '';
				if ($due_at_local !== '' && preg_match('/^\d{4}-\d{2}-\d{2}\s(\d{2}:\d{2})/', $due_at_local, $matches)) {
					$due_time_local = (string) $matches[1];
				}
				$current = array(
					'title' => sanitize_text_field((string) ($source['title'] ?? '')),
					'instructions' => (string) ($source['instructions'] ?? ''),
					'is_active' => 1,
					'priority' => bvmgr_tasks_sanitize_priority((string) ($source['priority'] ?? 'normal')),
					'required_default' => !empty($source['is_required']) ? 1 : 0,
					'scope' => (absint($source['event_id'] ?? 0) > 0 ? 'event' : 'general'),
					'due_mode' => ($due_time_local !== '' ? 'fixed_datetime' : 'none'),
					'due_offset_minutes' => '',
					'due_time_local' => $due_time_local,
					'assignment_mode' => bvmgr_tasks_sanitize_assignment_mode((string) ($source['assignment_mode'] ?? 'person')),
					'role_key' => sanitize_key((string) ($source['role_key'] ?? '')),
					'assignee_user_id' => absint($source['assignee_user_id'] ?? 0),
				);
				$messages[] = __('Template draft loaded from selected task. Save it, then add it to a checklist template to make it repeatable.', 'backstage-venue-manager');
			} else {
				$errors[] = __('Could not load the source task for template prefill.', 'backstage-venue-manager');
			}
		}
		$templates = bvmgr_tasks_get_task_templates();
		$users = bvmgr_tasks_admin_get_user_options();
		$role_options = bvmgr_tasks_admin_get_role_options(true);

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__('Task Templates', 'backstage-venue-manager') . ' ' . bvmgr_tasks_admin_help_button('vms_staff_tasks_templates', 'templates.help') . '</h1>';
		echo '<p class="description" data-vms-tour="templates.repeatable">' . esc_html__('Repeatable flow: save a task template here, then include it in a checklist template so it auto-generates for matching events.', 'backstage-venue-manager') . ' ';
		echo '<a class="button button-small" href="' . esc_url(bvmgr_tasks_admin_page_url('vms-checklist-templates')) . '">' . esc_html__('Open Checklist Templates', 'backstage-venue-manager') . '</a></p>';
		foreach ($errors as $error) {
			echo '<div class="notice notice-error"><p>' . esc_html((string) $error) . '</p></div>';
		}
		foreach ($messages as $message) {
			echo '<div class="notice notice-success"><p>' . esc_html((string) $message) . '</p></div>';
		}

		echo '<form method="post">';
		wp_nonce_field('vms_tasks_save_template');
		echo '<input type="hidden" name="vms_tasks_template_action" value="save">';
		echo '<input type="hidden" name="template_id" value="' . esc_attr((string) ($current['id'] ?? 0)) . '">';
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th><label for="vms_tasks_title">' . esc_html__('Title', 'backstage-venue-manager') . '</label></th><td><input id="vms_tasks_title" class="regular-text" name="title" value="' . esc_attr((string) ($current['title'] ?? '')) . '" required></td></tr>';
		echo '<tr><th><label for="vms_tasks_instructions">' . esc_html__('Instructions', 'backstage-venue-manager') . '</label></th><td><textarea id="vms_tasks_instructions" class="large-text" rows="3" name="instructions">' . esc_textarea((string) ($current['instructions'] ?? '')) . '</textarea></td></tr>';
		echo '<tr><th>' . esc_html__('Enabled', 'backstage-venue-manager') . '</th><td><label><input type="checkbox" name="is_active" value="1" ' . checked(!empty($current['is_active']) || !$current, true, false) . '> ' . esc_html__('Active', 'backstage-venue-manager') . '</label></td></tr>';
		echo '<tr><th><label for="vms_tasks_priority">' . esc_html__('Priority', 'backstage-venue-manager') . '</label></th><td><select id="vms_tasks_priority" name="priority">';
		foreach (array('low', 'normal', 'high') as $p) {
			echo '<option value="' . esc_attr($p) . '" ' . selected((string) ($current['priority'] ?? 'normal'), $p, false) . '>' . esc_html(ucfirst($p)) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th>' . esc_html__('Required by default', 'backstage-venue-manager') . '</th><td><label><input type="checkbox" name="required_default" value="1" ' . checked(!empty($current['required_default']), true, false) . '> ' . esc_html__('Required', 'backstage-venue-manager') . '</label></td></tr>';
		echo '<tr data-vms-tour="templates.scope"><th><label for="vms_tasks_scope">' . esc_html__('Context', 'backstage-venue-manager') . '</label></th><td><select id="vms_tasks_scope" name="scope">';
		echo '<option value="event" ' . selected((string) ($current['scope'] ?? 'event'), 'event', false) . '>' . esc_html__('Event-linked', 'backstage-venue-manager') . '</option>';
		echo '<option value="general" ' . selected((string) ($current['scope'] ?? 'event'), 'general', false) . '>' . esc_html__('Not linked to an event', 'backstage-venue-manager') . '</option>';
		echo '</select></td></tr>';
		echo '<tr data-vms-tour="templates.due"><th><label for="vms_tasks_due_mode">' . esc_html__('Due mode', 'backstage-venue-manager') . '</label></th><td><select id="vms_tasks_due_mode" name="due_mode">';
		echo '<option value="none" ' . selected((string) ($current['due_mode'] ?? 'none'), 'none', false) . '>' . esc_html__('None', 'backstage-venue-manager') . '</option>';
		echo '<option value="event_offset" ' . selected((string) ($current['due_mode'] ?? 'none'), 'event_offset', false) . '>' . esc_html__('Event offset (minutes)', 'backstage-venue-manager') . '</option>';
		echo '<option value="fixed_datetime" ' . selected((string) ($current['due_mode'] ?? 'none'), 'fixed_datetime', false) . '>' . esc_html__('Fixed time on event date', 'backstage-venue-manager') . '</option>';
		echo '</select> ';
		echo '<input type="number" name="due_offset_minutes" value="' . esc_attr((string) ($current['due_offset_minutes'] ?? '')) . '" placeholder="' . esc_attr__('Offset minutes', 'backstage-venue-manager') . '" style="width:140px;"> ';
		echo '<input type="text" name="due_time_local" value="' . esc_attr((string) ($current['due_time_local'] ?? '')) . '" placeholder="HH:MM" style="width:90px;">';
		echo '</td></tr>';
		echo '<tr data-vms-tour="templates.assignment"><th><label for="vms_tasks_assignment_mode">' . esc_html__('Assignment mode', 'backstage-venue-manager') . '</label></th><td><select id="vms_tasks_assignment_mode" name="assignment_mode">';
		echo '<option value="role" ' . selected((string) ($current['assignment_mode'] ?? 'role'), 'role', false) . '>' . esc_html__('Role', 'backstage-venue-manager') . '</option>';
		echo '<option value="person" ' . selected((string) ($current['assignment_mode'] ?? 'role'), 'person', false) . '>' . esc_html__('Person', 'backstage-venue-manager') . '</option>';
		echo '<option value="scheduled_role" ' . selected((string) ($current['assignment_mode'] ?? 'role'), 'scheduled_role', false) . '>' . esc_html__('Scheduled Role', 'backstage-venue-manager') . '</option>';
		echo '</select> ';
		$current_role_key = sanitize_key((string) ($current['role_key'] ?? ''));
		echo '<select name="role_key">';
		echo '<option value="">' . esc_html__('Select role', 'backstage-venue-manager') . '</option>';
		foreach ($role_options as $role_key => $role_label) {
			echo '<option value="' . esc_attr($role_key) . '"' . selected($current_role_key, $role_key, false) . '>' . esc_html($role_label) . '</option>';
		}
		if ($current_role_key !== '' && !isset($role_options[$current_role_key])) {
			echo '<option value="' . esc_attr($current_role_key) . '" selected>' . esc_html($current_role_key) . '</option>';
		}
		echo '</select> ';
		echo '<select name="assignee_user_id"><option value="0">' . esc_html__('No specific person', 'backstage-venue-manager') . '</option>';
		foreach ($users as $uid => $label) {
			echo '<option value="' . esc_attr((string) $uid) . '" ' . selected(absint($current['assignee_user_id'] ?? 0), $uid, false) . '>' . esc_html($label) . '</option>';
		}
		echo '</select></td></tr>';
		echo '</tbody></table>';
		submit_button(__('Save Task Template', 'backstage-venue-manager'));
		echo '</form>';

		echo '<h2>' . esc_html__('Existing Templates', 'backstage-venue-manager') . '</h2>';
		echo '<table class="widefat striped" data-vms-tour="templates.table"><thead><tr><th>' . esc_html__('Title', 'backstage-venue-manager') . '</th><th>' . esc_html__('Context', 'backstage-venue-manager') . '</th><th>' . esc_html__('Mode', 'backstage-venue-manager') . '</th><th>' . esc_html__('Required', 'backstage-venue-manager') . '</th><th>' . esc_html__('Active', 'backstage-venue-manager') . '</th></tr></thead><tbody>';
		if (empty($templates)) {
			echo '<tr><td colspan="5">' . esc_html__('No task templates yet.', 'backstage-venue-manager') . '</td></tr>';
		} else {
			foreach ($templates as $template) {
				$tid = absint($template['id'] ?? 0);
				echo '<tr>';
				echo '<td><a href="' . esc_url(bvmgr_tasks_admin_page_url('vms-task-templates', array('template_id' => $tid))) . '">' . esc_html((string) ($template['title'] ?? '')) . '</a></td>';
				echo '<td>' . esc_html(bvmgr_tasks_admin_scope_label((string) ($template['scope'] ?? 'event'))) . '</td>';
				echo '<td>' . esc_html((string) ($template['assignment_mode'] ?? 'role')) . '</td>';
				echo '<td>' . (!empty($template['required_default']) ? esc_html__('Yes', 'backstage-venue-manager') : esc_html__('No', 'backstage-venue-manager')) . '</td>';
				echo '<td>' . (!empty($template['is_active']) ? esc_html__('Yes', 'backstage-venue-manager') : esc_html__('No', 'backstage-venue-manager')) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
		echo '</div>';
	}
}

if (!function_exists('bvmgr_tasks_render_checklist_templates_page')) {
	function bvmgr_tasks_render_checklist_templates_page(): void
	{
		if (!bvmgr_tasks_current_user_can_manage_checklists()) {
			wp_die(esc_html__('Insufficient permissions.', 'backstage-venue-manager'));
		}
		bvmgr_tasks_admin_render_hover_tip_assets();

		$messages = array();
		$errors = array();
		$edit_id = isset($_GET['checklist_id']) ? absint($_GET['checklist_id']) : 0;

		if (bvmgr_tasks_admin_is_exact_post_request() && isset($_POST['vms_tasks_checklist_action'])) {
			check_admin_referer('vms_tasks_save_checklist');
			$action = sanitize_key((string) wp_unslash($_POST['vms_tasks_checklist_action']));
			$checklist_id = absint($_POST['checklist_id'] ?? 0);
				if ($action === 'save') {
					$payload = array(
						'name' => sanitize_text_field((string) wp_unslash($_POST['name'] ?? '')),
						'is_active' => !empty($_POST['is_active']) ? 1 : 0,
						'priority_order' => (int) sanitize_text_field((string) wp_unslash($_POST['priority_order'] ?? '100')),
						'scope' => sanitize_key((string) wp_unslash($_POST['scope'] ?? 'event')),
						'apply_mode' => sanitize_key((string) wp_unslash($_POST['apply_mode'] ?? 'default_all_events')),
						'venue_id' => absint($_POST['venue_id'] ?? 0),
					'event_type' => sanitize_key((string) wp_unslash($_POST['event_type'] ?? '')),
				);
				$saved = bvmgr_tasks_upsert_checklist_template($payload, $checklist_id);
				if (is_wp_error($saved)) {
					$errors[] = $saved->get_error_message();
				} else {
					$template_ids = isset($_POST['task_template_ids']) && is_array($_POST['task_template_ids'])
						? array_map('absint', (array) wp_unslash($_POST['task_template_ids']))
						: array();
					$items = array();
					$sort = 0;
					foreach ($template_ids as $template_id) {
						if ($template_id <= 0) {
							continue;
						}
						$sort++;
						$items[] = array(
							'task_template_id' => $template_id,
							'sort_order' => $sort,
							'overrides' => array(),
						);
					}
					$replace = bvmgr_tasks_replace_checklist_items((int) $saved, $items);
					if (is_wp_error($replace)) {
						$errors[] = $replace->get_error_message();
					} else {
						$messages[] = __('Checklist template saved.', 'backstage-venue-manager');
						$edit_id = (int) $saved;
					}
				}
			}
		}

		$current = $edit_id > 0 ? bvmgr_tasks_get_checklist_template($edit_id) : null;
		$current_items = $edit_id > 0 ? bvmgr_tasks_get_checklist_items($edit_id) : array();
		$selected_template_ids = array_values(array_unique(array_filter(array_map(static function ($item): int {
			return absint($item['task_template_id'] ?? 0);
		}, $current_items))));
		$current_scope = bvmgr_tasks_sanitize_scope((string) ($current['scope'] ?? 'event'));

		$checklists = bvmgr_tasks_get_checklist_templates();
		$templates = bvmgr_tasks_get_task_templates(array('is_active' => 1, 'scope' => $current_scope));
		$venues = bvmgr_tasks_admin_get_venues();
		$event_type_options = bvmgr_tasks_admin_get_event_type_options();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__('Checklist Templates', 'backstage-venue-manager') . ' ' . bvmgr_tasks_admin_help_button('vms_staff_tasks_checklists', 'checklists.help') . '</h1>';
		foreach ($errors as $error) {
			echo '<div class="notice notice-error"><p>' . esc_html((string) $error) . '</p></div>';
		}
		foreach ($messages as $message) {
			echo '<div class="notice notice-success"><p>' . esc_html((string) $message) . '</p></div>';
		}

		echo '<form method="post">';
		wp_nonce_field('vms_tasks_save_checklist');
		echo '<input type="hidden" name="vms_tasks_checklist_action" value="save">';
		echo '<input type="hidden" name="checklist_id" value="' . esc_attr((string) ($current['id'] ?? 0)) . '">';
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th><label for="vms_tasks_checklist_name">' . esc_html__('Name', 'backstage-venue-manager') . '</label></th><td><input id="vms_tasks_checklist_name" class="regular-text" name="name" value="' . esc_attr((string) ($current['name'] ?? '')) . '" required></td></tr>';
		echo '<tr><th>' . esc_html__('Active', 'backstage-venue-manager') . '</th><td><label><input type="checkbox" name="is_active" value="1" ' . checked(!empty($current['is_active']) || !$current, true, false) . '> ' . esc_html__('Enabled', 'backstage-venue-manager') . '</label></td></tr>';
		echo '<tr><th><label for="vms_tasks_checklist_priority">' . esc_html__('Priority order', 'backstage-venue-manager') . '</label></th><td><input type="number" id="vms_tasks_checklist_priority" name="priority_order" value="' . esc_attr((string) ($current['priority_order'] ?? 100)) . '"></td></tr>';
		echo '<tr data-vms-tour="checklists.scope"><th><label for="vms_tasks_checklist_scope">' . esc_html__('Context', 'backstage-venue-manager') . '</label></th><td><select id="vms_tasks_checklist_scope" name="scope">';
		echo '<option value="event" ' . selected($current_scope, 'event', false) . '>' . esc_html__('Event-linked', 'backstage-venue-manager') . '</option>';
		echo '<option value="general" ' . selected($current_scope, 'general', false) . '>' . esc_html__('Not linked to an event', 'backstage-venue-manager') . '</option>';
		echo '</select></td></tr>';
		echo '<tr data-vms-tour="checklists.apply-mode" id="vms_tasks_checklist_apply_mode_row"><th><label for="vms_tasks_apply_mode">' . esc_html__('Apply mode', 'backstage-venue-manager') . '</label></th><td><select id="vms_tasks_apply_mode" name="apply_mode">';
		echo '<option value="default_all_events" ' . selected((string) ($current['apply_mode'] ?? 'default_all_events'), 'default_all_events', false) . '>' . esc_html__('Default for all events', 'backstage-venue-manager') . '</option>';
		echo '<option value="by_venue" ' . selected((string) ($current['apply_mode'] ?? ''), 'by_venue', false) . '>' . esc_html__('By venue', 'backstage-venue-manager') . '</option>';
		echo '<option value="by_event_type" ' . selected((string) ($current['apply_mode'] ?? ''), 'by_event_type', false) . '>' . esc_html__('By event type', 'backstage-venue-manager') . '</option>';
		echo '</select></td></tr>';
		echo '<tr id="vms_tasks_checklist_venue_row"><th><label for="vms_tasks_checklist_venue">' . esc_html__('Venue', 'backstage-venue-manager') . '</label></th><td><select id="vms_tasks_checklist_venue" name="venue_id"><option value="0">' . esc_html__('None', 'backstage-venue-manager') . '</option>';
		foreach ($venues as $venue_id => $venue_name) {
			echo '<option value="' . esc_attr((string) $venue_id) . '" ' . selected(absint($current['venue_id'] ?? 0), $venue_id, false) . '>' . esc_html($venue_name) . '</option>';
		}
		echo '</select></td></tr>';
		$current_event_type = sanitize_key((string) ($current['event_type'] ?? ''));
		echo '<tr id="vms_tasks_checklist_event_type_row"><th><label for="vms_tasks_checklist_event_type">' . esc_html__('Event type key', 'backstage-venue-manager') . '</label></th><td><select id="vms_tasks_checklist_event_type" name="event_type">';
		echo '<option value="">' . esc_html__('Select event type', 'backstage-venue-manager') . '</option>';
		foreach ($event_type_options as $type_key => $type_label) {
			echo '<option value="' . esc_attr($type_key) . '"' . selected($current_event_type, $type_key, false) . '>' . esc_html($type_label) . '</option>';
		}
		if ($current_event_type !== '' && !isset($event_type_options[$current_event_type])) {
			echo '<option value="' . esc_attr($current_event_type) . '" selected>' . esc_html($current_event_type) . '</option>';
		}
		echo '</select><p class="description">' . esc_html__('Used only when Apply mode is "By event type".', 'backstage-venue-manager') . '</p></td></tr>';
		echo '<tr data-vms-tour="checklists.tasks"><th>' . esc_html__('Tasks in checklist', 'backstage-venue-manager') . '</th><td>';
		if (empty($templates)) {
			echo '<p>' . esc_html__('No active task templates found.', 'backstage-venue-manager') . '</p>';
		} else {
			foreach ($templates as $template) {
				$tid = absint($template['id'] ?? 0);
				if ($tid <= 0) {
					continue;
				}
				$checked = in_array($tid, $selected_template_ids, true);
				echo '<label style="display:block;margin:4px 0;"><input type="checkbox" name="task_template_ids[]" value="' . esc_attr((string) $tid) . '" ' . checked($checked, true, false) . '> ' . esc_html((string) ($template['title'] ?? '')) . '</label>';
			}
		}
		echo '<p class="description" data-vms-tour="checklists.generated">' . esc_html__('Event-linked checklist templates generate task instances when event task generation runs.', 'backstage-venue-manager') . '</p>';
		echo '</td></tr>';
		echo '</tbody></table>';
		submit_button(__('Save Checklist Template', 'backstage-venue-manager'));
		echo '</form>';

		echo '<h2>' . esc_html__('Existing Checklists', 'backstage-venue-manager') . '</h2>';
		echo '<table class="widefat striped" data-vms-tour="checklists.table"><thead><tr><th>' . esc_html__('Name', 'backstage-venue-manager') . '</th><th>' . esc_html__('Context', 'backstage-venue-manager') . '</th><th>' . esc_html__('Apply mode', 'backstage-venue-manager') . '</th><th>' . esc_html__('Priority', 'backstage-venue-manager') . '</th><th>' . esc_html__('Active', 'backstage-venue-manager') . '</th></tr></thead><tbody>';
		if (empty($checklists)) {
			echo '<tr><td colspan="5">' . esc_html__('No checklist templates yet.', 'backstage-venue-manager') . '</td></tr>';
		} else {
			foreach ($checklists as $checklist) {
				$cid = absint($checklist['id'] ?? 0);
				echo '<tr>';
				echo '<td><a href="' . esc_url(bvmgr_tasks_admin_page_url('vms-checklist-templates', array('checklist_id' => $cid))) . '">' . esc_html((string) ($checklist['name'] ?? '')) . '</a></td>';
				echo '<td>' . esc_html(bvmgr_tasks_admin_scope_label((string) ($checklist['scope'] ?? 'event'))) . '</td>';
				echo '<td>' . esc_html((string) ($checklist['apply_mode'] ?? 'default_all_events')) . '</td>';
				echo '<td>' . esc_html((string) ($checklist['priority_order'] ?? '100')) . '</td>';
				echo '<td>' . (!empty($checklist['is_active']) ? esc_html__('Yes', 'backstage-venue-manager') : esc_html__('No', 'backstage-venue-manager')) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
		echo '</div>';
	}
}

if (!function_exists('bvmgr_tasks_render_settings_page')) {
	function bvmgr_tasks_render_settings_page(): void
	{
		if (!bvmgr_tasks_current_user_can_manage_all()) {
			wp_die(esc_html__('Insufficient permissions.', 'backstage-venue-manager'));
		}
		bvmgr_tasks_admin_render_hover_tip_assets();

		$saved = false;
		if (bvmgr_tasks_admin_is_exact_post_request() && isset($_POST['vms_tasks_settings_action'])) {
			check_admin_referer('vms_tasks_save_settings');
			$input = array(
				'horizon_days' => absint($_POST['horizon_days'] ?? 60),
				'regenerate_on_event_date_change' => !empty($_POST['regenerate_on_event_date_change']) ? 1 : 0,
				'regenerate_on_venue_change' => !empty($_POST['regenerate_on_venue_change']) ? 1 : 0,
				'regenerate_on_event_type_change' => !empty($_POST['regenerate_on_event_type_change']) ? 1 : 0,
				'show_dashboard_cards' => !empty($_POST['show_dashboard_cards']) ? 1 : 0,
				'dashboard_events_lookahead_days' => absint($_POST['dashboard_events_lookahead_days'] ?? 14),
				'dashboard_max_events' => absint($_POST['dashboard_max_events'] ?? 10),
				'notify_assignment_alerts' => !empty($_POST['notify_assignment_alerts']) ? 1 : 0,
					'notify_due_soon_alerts' => !empty($_POST['notify_due_soon_alerts']) ? 1 : 0,
					'notify_overdue_alerts' => !empty($_POST['notify_overdue_alerts']) ? 1 : 0,
					'notify_daily_digest' => !empty($_POST['notify_daily_digest']) ? 1 : 0,
					'notify_digest_time' => sanitize_text_field((string) wp_unslash($_POST['notify_digest_time'] ?? '08:00')),
					'notify_digest_window' => sanitize_key((string) ($_POST['notify_digest_window'] ?? 'next3')),
				);
			bvmgr_tasks_update_settings($input);
			$saved = true;
		}

		$settings = bvmgr_tasks_get_settings();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__('Task Settings', 'backstage-venue-manager') . ' ' . bvmgr_tasks_admin_help_button('vms_staff_tasks_settings', 'task-settings.help') . '</h1>';
		if ($saved) {
			echo '<div class="notice notice-success"><p>' . esc_html__('Task settings saved.', 'backstage-venue-manager') . '</p></div>';
		}

		echo '<form method="post">';
		wp_nonce_field('vms_tasks_save_settings');
		echo '<input type="hidden" name="vms_tasks_settings_action" value="save">';
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr data-vms-tour="task-settings.generation"><th><label for="vms_tasks_horizon_days">' . esc_html__('Horizon days', 'backstage-venue-manager') . '</label></th><td><input type="number" id="vms_tasks_horizon_days" name="horizon_days" min="1" max="365" value="' . esc_attr((string) ($settings['horizon_days'] ?? 60)) . '"></td></tr>';
		echo '<tr data-vms-tour="task-settings.generation"><th>' . esc_html__('Regeneration policy', 'backstage-venue-manager') . '</th><td>';
		echo '<label><input type="checkbox" name="regenerate_on_event_date_change" value="1" ' . checked(!empty($settings['regenerate_on_event_date_change']), true, false) . '> ' . esc_html__('Regenerate on event date change', 'backstage-venue-manager') . '</label><br>';
		echo '<label><input type="checkbox" name="regenerate_on_venue_change" value="1" ' . checked(!empty($settings['regenerate_on_venue_change']), true, false) . '> ' . esc_html__('Regenerate on venue change', 'backstage-venue-manager') . '</label><br>';
		echo '<label><input type="checkbox" name="regenerate_on_event_type_change" value="1" ' . checked(!empty($settings['regenerate_on_event_type_change']), true, false) . '> ' . esc_html__('Regenerate on event type change', 'backstage-venue-manager') . '</label>';
		echo '</td></tr>';
		echo '<tr data-vms-tour="task-settings.notifications"><th>' . esc_html__('Notifications', 'backstage-venue-manager') . '</th><td>';
		echo '<label><input type="checkbox" name="notify_assignment_alerts" value="1" ' . checked(!empty($settings['notify_assignment_alerts']), true, false) . '> ' . esc_html__('Assignment alerts', 'backstage-venue-manager') . '</label><br>';
		echo '<label><input type="checkbox" name="notify_due_soon_alerts" value="1" ' . checked(!empty($settings['notify_due_soon_alerts']), true, false) . '> ' . esc_html__('Due soon alerts', 'backstage-venue-manager') . '</label><br>';
		echo '<label><input type="checkbox" name="notify_overdue_alerts" value="1" ' . checked(!empty($settings['notify_overdue_alerts']), true, false) . '> ' . esc_html__('Overdue alerts', 'backstage-venue-manager') . '</label><br>';
		echo '<label><input type="checkbox" name="notify_daily_digest" value="1" ' . checked(!empty($settings['notify_daily_digest']), true, false) . '> ' . esc_html__('Daily digest', 'backstage-venue-manager') . '</label>';
		echo '</td></tr>';
		echo '<tr data-vms-tour="task-settings.digest"><th><label for="vms_tasks_notify_digest_time">' . esc_html__('Digest time', 'backstage-venue-manager') . '</label></th><td>';
		echo '<input type="time" id="vms_tasks_notify_digest_time" name="notify_digest_time" value="' . esc_attr((string) ($settings['notify_digest_time'] ?? '08:00')) . '"> ';
		echo '<label for="vms_tasks_notify_digest_window">' . esc_html__('Window', 'backstage-venue-manager') . '</label> ';
		echo '<select id="vms_tasks_notify_digest_window" name="notify_digest_window">';
		echo '<option value="today" ' . selected((string) ($settings['notify_digest_window'] ?? 'next3'), 'today', false) . '>' . esc_html__('Today', 'backstage-venue-manager') . '</option>';
		echo '<option value="next3" ' . selected((string) ($settings['notify_digest_window'] ?? 'next3'), 'next3', false) . '>' . esc_html__('Next 3 days', 'backstage-venue-manager') . '</option>';
		echo '<option value="next7" ' . selected((string) ($settings['notify_digest_window'] ?? 'next3'), 'next7', false) . '>' . esc_html__('Next 7 days', 'backstage-venue-manager') . '</option>';
		echo '</select>';
		echo '</td></tr>';
		echo '<tr data-vms-tour="task-settings.dashboard"><th>' . esc_html__('Dashboard cards', 'backstage-venue-manager') . '</th><td><label><input type="checkbox" name="show_dashboard_cards" value="1" ' . checked(!empty($settings['show_dashboard_cards']), true, false) . '> ' . esc_html__('Show dashboard cards', 'backstage-venue-manager') . '</label></td></tr>';
		echo '<tr data-vms-tour="task-settings.dashboard"><th><label for="vms_tasks_dashboard_lookahead">' . esc_html__('Dashboard lookahead days', 'backstage-venue-manager') . '</label></th><td><input type="number" id="vms_tasks_dashboard_lookahead" name="dashboard_events_lookahead_days" min="1" max="90" value="' . esc_attr((string) ($settings['dashboard_events_lookahead_days'] ?? 14)) . '"></td></tr>';
		echo '<tr data-vms-tour="task-settings.dashboard"><th><label for="vms_tasks_dashboard_max">' . esc_html__('Dashboard max events', 'backstage-venue-manager') . '</label></th><td><input type="number" id="vms_tasks_dashboard_max" name="dashboard_max_events" min="1" max="50" value="' . esc_attr((string) ($settings['dashboard_max_events'] ?? 10)) . '"></td></tr>';
		echo '</tbody></table>';
		echo '<div data-vms-tour="task-settings.save">';
		submit_button(__('Save Task Settings', 'backstage-venue-manager'));
		echo '</div>';
		echo '</form>';
		echo '</div>';
	}
}

if (!function_exists('bvmgr_tasks_render_my_tasks_page')) {
	function bvmgr_tasks_render_my_tasks_page(): void
	{
		if (!bvmgr_tasks_current_user_can_view_self()) {
			wp_die(esc_html__('Insufficient permissions.', 'backstage-venue-manager'));
		}
		$user_id = absint(get_current_user_id());
		$tab = sanitize_key(bvmgr_tasks_admin_query_arg('tab'));
		if ($tab === '') {
			$tab = 'today';
		}
		if (!in_array($tab, array('overdue', 'today', 'upcoming'), true)) {
			$tab = 'today';
		}

		$filters = array(
			'assignee_user_id' => $user_id,
			'status' => 'open',
			'limit' => 300,
		);
		$tz = wp_timezone();
		$today = wp_date('Y-m-d', time(), $tz);
		$today_start = $today . ' 00:00:00';
		$today_end = $today . ' 23:59:59';
		if ($tab === 'overdue') {
			$filters['due_before'] = bvmgr_tasks_now_local_mysql();
		} elseif ($tab === 'today') {
			$filters['due_after'] = $today_start;
			$filters['due_before'] = $today_end;
		} elseif ($tab === 'upcoming') {
			$filters['due_after'] = $today_end;
		}

		$rows = bvmgr_tasks_get_instances($filters);

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__('My Tasks', 'backstage-venue-manager') . '</h1>';
		bvmgr_tasks_admin_render_notices();
		echo '<nav class="nav-tab-wrapper">';
		foreach (array(
			'overdue' => __('Overdue', 'backstage-venue-manager'),
			'today' => __('Today', 'backstage-venue-manager'),
			'upcoming' => __('Upcoming', 'backstage-venue-manager'),
		) as $slug => $label) {
			echo '<a class="nav-tab ' . ($tab === $slug ? 'nav-tab-active' : '') . '" href="' . esc_url(bvmgr_tasks_admin_page_url('vms-my-tasks', array('tab' => $slug))) . '">' . esc_html($label) . '</a>';
		}
		echo '</nav>';

		if (empty($rows)) {
			echo '<p>' . esc_html__('No tasks in this tab.', 'backstage-venue-manager') . '</p>';
			echo '</div>';
			return;
		}

		echo '<div style="margin-top:12px;display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;">';
		foreach ($rows as $row) {
			$instance_id = absint($row['id'] ?? 0);
			$event_id = absint($row['event_id'] ?? 0);
			$recurrence_pattern = bvmgr_tasks_sanitize_recurrence_pattern((string) ($row['recurrence_pattern'] ?? 'none'));
			$recurrence_every_n_days = absint($row['recurrence_every_n_days'] ?? 0);
			echo '<div class="postbox" style="padding:12px;">';
			echo '<h2 style="margin:0 0 8px;">' . esc_html((string) ($row['title'] ?? '')) . '</h2>';
			if ($event_id > 0) {
				echo '<p><strong>' . esc_html__('Event:', 'backstage-venue-manager') . '</strong> <a href="' . esc_url(get_edit_post_link($event_id)) . '">' . esc_html(get_the_title($event_id)) . '</a></p>';
			}
			echo '<p><strong>' . esc_html__('Due:', 'backstage-venue-manager') . '</strong> ' . esc_html((string) ($row['due_at_local'] ?? __('No due date', 'backstage-venue-manager'))) . '</p>';
			if ($recurrence_pattern !== 'none') {
				echo '<p><strong>' . esc_html__('Repeats:', 'backstage-venue-manager') . '</strong> ' . esc_html(bvmgr_tasks_recurrence_label($recurrence_pattern, $recurrence_every_n_days)) . '</p>';
			}
			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
			wp_nonce_field('vms_tasks_transition');
			echo '<input type="hidden" name="action" value="vms_tasks_transition">';
			echo '<input type="hidden" name="return_page" value="vms-my-tasks">';
			echo '<input type="hidden" name="instance_id" value="' . esc_attr((string) $instance_id) . '">';
			echo '<button class="button button-primary button-small" type="submit" name="target_status" value="done">' . esc_html__('Done', 'backstage-venue-manager') . '</button> ';
			echo '<input type="text" name="reason" placeholder="' . esc_attr__('Skip reason', 'backstage-venue-manager') . '" style="width:130px;"> ';
			echo '<button class="button button-small" type="submit" name="target_status" value="skipped">' . esc_html__('Skip', 'backstage-venue-manager') . '</button>';
			echo '</form>';
			echo '</div>';
		}
		echo '</div>';
		echo '</div>';
	}
}

if (!function_exists('bvmgr_tasks_collect_dashboard_red_flags')) {
	/**
	 * @return array<int,array<string,mixed>>
	 */
	function bvmgr_tasks_collect_dashboard_red_flags(int $lookahead_days, int $max_events): array
	{
		$lookahead_days = max(1, min(90, $lookahead_days));
		$max_events = max(1, min(50, $max_events));
		$event_ids = array_slice(bvmgr_tasks_collect_upcoming_event_ids($lookahead_days), 0, $max_events);
		if (empty($event_ids)) {
			return array();
		}

		$flags = array();
		$now_local = bvmgr_tasks_now_local_mysql();
		$tz = wp_timezone();
		$soon_cutoff = (new DateTimeImmutable('now', $tz))->modify('+24 hours')->format('Y-m-d H:i:s');

		foreach ($event_ids as $event_id) {
			$event_id = absint($event_id);
			if ($event_id <= 0) {
				continue;
			}

			$required_open = bvmgr_tasks_count_instances(array(
				'event_id' => $event_id,
				'status' => 'open',
				'required_only' => 1,
			));
			if ($required_open <= 0) {
				continue;
			}

			$required_overdue = bvmgr_tasks_count_instances(array(
				'event_id' => $event_id,
				'status' => 'open',
				'required_only' => 1,
				'due_before' => $now_local,
			));
			if ($required_overdue > 0) {
				$flags[] = array(
					'event_id' => $event_id,
					'label' => sprintf(
						/* translators: %d is the count of overdue required tasks. */
						_n('%d overdue required task', '%d overdue required tasks', $required_overdue, 'backstage-venue-manager'),
						$required_overdue
					),
				);
				continue;
			}

			$event = bvmgr_tasks_get_event_context($event_id);
			$event_start_local = is_array($event) ? (string) ($event['event_start_local'] ?? '') : '';
			if ($event_start_local !== '' && $event_start_local <= $soon_cutoff) {
				$flags[] = array(
					'event_id' => $event_id,
					'label' => sprintf(
						/* translators: %d is the count of open required tasks. */
						_n('%d open required task within 24h of event start', '%d open required tasks within 24h of event start', $required_open, 'backstage-venue-manager'),
						$required_open
					),
				);
			}
		}

		return $flags;
	}
}

if (!function_exists('bvmgr_tasks_render_dashboard_cards')) {
	function bvmgr_tasks_render_dashboard_cards(): void
	{
		if (!bvmgr_tasks_current_user_can_manage_all()) {
			return;
		}

		$settings = bvmgr_tasks_get_settings();
		if (empty($settings['show_dashboard_cards'])) {
			return;
		}

		echo '<section id="vms-dashboard-staff-tasks" style="margin:16px 0;">';
		echo '<h2>' . esc_html__('Staff Tasks', 'backstage-venue-manager') . '</h2>';

		if (!bvmgr_tasks_db_ready()) {
			echo '<p class="description">' . esc_html__('Staff Tasks tables are unavailable. Run schema setup before using task dashboards.', 'backstage-venue-manager') . '</p>';
			echo '</section>';
			return;
		}

		$tz = wp_timezone();
		$today = wp_date('Y-m-d', time(), $tz);
		$today_start = $today . ' 00:00:00';
		$today_end = $today . ' 23:59:59';
		$now_local = bvmgr_tasks_now_local_mysql();

		$due_today = bvmgr_tasks_count_instances(array(
			'status' => 'open',
			'due_after' => $today_start,
			'due_before' => $today_end,
		));
		$overdue = bvmgr_tasks_count_instances(array(
			'status' => 'open',
			'due_before' => $now_local,
		));

		$lookahead_days = max(1, min(90, absint($settings['dashboard_events_lookahead_days'] ?? 14)));
		$max_events = max(1, min(50, absint($settings['dashboard_max_events'] ?? 10)));
		$red_flags = bvmgr_tasks_collect_dashboard_red_flags($lookahead_days, $max_events);
		$red_flag_count = count($red_flags);

		echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px;max-width:760px;">';

		echo '<a class="postbox" style="display:block;padding:10px;text-decoration:none;" href="' . esc_url(bvmgr_tasks_admin_page_url('vms-tasks', array('status' => 'open'))) . '">';
		echo '<strong style="display:block;">' . esc_html__('Tasks Due Today', 'backstage-venue-manager') . '</strong>';
		echo '<span style="font-size:24px;line-height:1.1;">' . esc_html((string) $due_today) . '</span>';
		echo '</a>';

		echo '<a class="postbox" style="display:block;padding:10px;text-decoration:none;" href="' . esc_url(bvmgr_tasks_admin_page_url('vms-tasks', array('status' => 'open', 'due_bucket' => 'overdue'))) . '">';
		echo '<strong style="display:block;">' . esc_html__('Overdue Tasks', 'backstage-venue-manager') . '</strong>';
		echo '<span style="font-size:24px;line-height:1.1;">' . esc_html((string) $overdue) . '</span>';
		echo '</a>';

		echo '<a class="postbox" style="display:block;padding:10px;text-decoration:none;" href="' . esc_url(bvmgr_tasks_admin_page_url('vms-tasks', array('status' => 'open', 'required_only' => 1))) . '">';
		echo '<strong style="display:block;">' . esc_html__('Upcoming Event Red Flags', 'backstage-venue-manager') . '</strong>';
		echo '<span style="font-size:24px;line-height:1.1;">' . esc_html((string) $red_flag_count) . '</span>';
		echo '</a>';

		echo '</div>';

		if (!empty($red_flags)) {
			echo '<ul style="margin:10px 0 0 20px;">';
			foreach (array_slice($red_flags, 0, 5) as $flag) {
				$event_id = absint($flag['event_id'] ?? 0);
				if ($event_id <= 0) {
					continue;
				}
				echo '<li>';
				echo '<a href="' . esc_url(get_edit_post_link($event_id)) . '">' . esc_html(get_the_title($event_id)) . '</a>';
				echo ' <small>#' . esc_html((string) $event_id) . ' · ' . esc_html((string) ($flag['label'] ?? '')) . '</small>';
				echo '</li>';
			}
			echo '</ul>';
		}

		echo '</section>';
	}
}

if (!function_exists('bvmgr_tasks_register_event_plan_metabox')) {
	function bvmgr_tasks_register_event_plan_metabox(): void
	{
		add_meta_box(
			'vms-event-plan-tasks',
			__('Tasks', 'backstage-venue-manager'),
			'bvmgr_tasks_render_event_plan_metabox',
			'vms_event_plan',
			'normal',
			'default'
		);
	}
}
add_action('add_meta_boxes_vms_event_plan', 'bvmgr_tasks_register_event_plan_metabox');

if (!function_exists('bvmgr_tasks_admin_event_plan_checklist_reason')) {
	/**
	 * @param array<string,mixed> $checklist
	 * @param array<string,mixed> $event_context
	 * @param array<int,string> $venues
	 */
	function bvmgr_tasks_admin_event_plan_checklist_reason(array $checklist, array $event_context, array $venues): string
	{
		$apply_mode = bvmgr_tasks_sanitize_apply_mode((string) ($checklist['apply_mode'] ?? 'default_all_events'));
		if ($apply_mode === 'by_venue') {
			$venue_id = absint($checklist['venue_id'] ?? 0);
			if ($venue_id > 0 && isset($venues[$venue_id])) {
				return sprintf(
					/* translators: %s is the venue name. */
					__('Venue = %s', 'backstage-venue-manager'),
					$venues[$venue_id]
				);
			}
			return sprintf(
				/* translators: %d is a venue id. */
				__('Venue ID = %d', 'backstage-venue-manager'),
				$venue_id
			);
		}
		if ($apply_mode === 'by_event_type') {
			$type_key = sanitize_key((string) ($checklist['event_type'] ?? ''));
			if ($type_key === '') {
				$type_key = sanitize_key((string) ($event_context['event_type'] ?? ''));
			}
			if ($type_key !== '') {
				return sprintf(
					/* translators: %s is the event type key. */
					__('Event type = %s', 'backstage-venue-manager'),
					$type_key
				);
			}
			return __('Event type match', 'backstage-venue-manager');
		}

		return __('All events', 'backstage-venue-manager');
	}
}

if (!function_exists('bvmgr_tasks_admin_group_event_plan_rows')) {
	/**
	 * @param array<int,array<string,mixed>> $rows
	 * @param array<string,mixed> $event_context
	 * @return array<string,array<int,array<string,mixed>>>
	 */
	function bvmgr_tasks_admin_group_event_plan_rows(array $rows, array $event_context): array
	{
		$groups = array(
			'pre_event' => array(),
			'day_of' => array(),
			'post_event' => array(),
			'no_due' => array(),
		);

		$event_start_local = trim((string) ($event_context['event_start_local'] ?? ''));
		$event_date_ymd = trim((string) ($event_context['date_ymd'] ?? ''));
		if ($event_date_ymd === '' && $event_start_local !== '') {
			$event_date_ymd = substr($event_start_local, 0, 10);
		}

		foreach ($rows as $row) {
			$due_raw = trim((string) ($row['due_at_local'] ?? ''));
			if ($due_raw === '') {
				$groups['no_due'][] = $row;
				continue;
			}
			if ($event_start_local !== '' && $due_raw < $event_start_local) {
				$groups['pre_event'][] = $row;
				continue;
			}
			if ($event_date_ymd !== '' && strpos($due_raw, $event_date_ymd) === 0) {
				$groups['day_of'][] = $row;
				continue;
			}
			if ($event_start_local !== '' && $due_raw > $event_start_local) {
				$groups['post_event'][] = $row;
				continue;
			}
			$groups['day_of'][] = $row;
		}

		return $groups;
	}
}

if (!function_exists('bvmgr_tasks_render_event_plan_tasks_table')) {
	/**
	 * @param array<int,array<string,mixed>> $rows
	 * @param array<int,string> $users
	 * @param array<string,string> $role_options
	 */
	function bvmgr_tasks_render_event_plan_tasks_table(array $rows, array $users, array $role_options, bool $can_manage_all, int $event_id): void
	{
		$form_action = admin_url('admin-post.php');
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__('Task', 'backstage-venue-manager') . '</th>';
		echo '<th>' . esc_html__('Due', 'backstage-venue-manager') . '</th>';
		echo '<th>' . esc_html__('Assignment', 'backstage-venue-manager') . '</th>';
		echo '<th>' . esc_html__('Status', 'backstage-venue-manager') . '</th>';
		if ($can_manage_all) {
			echo '<th>' . esc_html__('Actions', 'backstage-venue-manager') . '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ($rows as $row) {
			$instance_id = absint($row['id'] ?? 0);
			$status = bvmgr_tasks_sanitize_status((string) ($row['status'] ?? 'open'));
			$is_one_off = absint($row['task_template_id'] ?? 0) <= 0;
			$assignment_mode = bvmgr_tasks_sanitize_assignment_mode((string) ($row['assignment_mode'] ?? 'person'));
			$current_role_key = sanitize_key((string) ($row['role_key'] ?? ''));
			$assignee_id = absint($row['assignee_user_id'] ?? 0);
			$assignment_summary = bvmgr_tasks_admin_assignment_summary($row, $users, $role_options);
			$due_raw = (string) ($row['due_at_local'] ?? '');
			$clone_url = bvmgr_tasks_admin_page_url('vms-task-templates', array('clone_instance_id' => $instance_id));
			$done_form_id = '';
			$remove_form_id = '';
			$assignment_form_id = '';

			echo '<tr>';
			echo '<td>' . esc_html((string) ($row['title'] ?? ''));
			if ($is_one_off) {
				echo ' <small>(' . esc_html__('Manual', 'backstage-venue-manager') . ')</small>';
			}
			echo '</td>';
			echo '<td>' . esc_html($due_raw !== '' ? $due_raw : __('No due date', 'backstage-venue-manager')) . '</td>';
			echo '<td>' . esc_html($assignment_summary) . '</td>';
			echo '<td>' . esc_html(strtoupper($status)) . '</td>';
			if ($can_manage_all) {
				if ($status === 'open') {
					$done_form_id = bvmgr_tasks_event_plan_metabox_form_id($event_id, 'transition-done', $instance_id);
					bvmgr_tasks_event_plan_metabox_register_form($done_form_id, 'post', $form_action, array(
						'_wpnonce' => wp_create_nonce('vms_tasks_transition'),
						'action' => 'vms_tasks_transition',
						'return_page' => 'event-plan',
						'event_id' => $event_id,
						'instance_id' => $instance_id,
						'target_status' => 'done',
					));
				}

				if ($status === 'open' && $is_one_off) {
					$remove_form_id = bvmgr_tasks_event_plan_metabox_form_id($event_id, 'transition-canceled', $instance_id);
					bvmgr_tasks_event_plan_metabox_register_form($remove_form_id, 'post', $form_action, array(
						'_wpnonce' => wp_create_nonce('vms_tasks_transition'),
						'action' => 'vms_tasks_transition',
						'return_page' => 'event-plan',
						'event_id' => $event_id,
						'instance_id' => $instance_id,
						'target_status' => 'canceled',
						'reason' => __('Removed manual task from Event Plan panel.', 'backstage-venue-manager'),
					));
				}

				$assignment_form_id = bvmgr_tasks_event_plan_metabox_form_id($event_id, 'assignment', $instance_id);
				bvmgr_tasks_event_plan_metabox_register_form($assignment_form_id, 'post', $form_action, array(
					'_wpnonce' => wp_create_nonce('vms_tasks_update_assignment'),
					'action' => 'vms_tasks_update_assignment',
					'return_page' => 'event-plan',
					'event_id' => $event_id,
					'instance_id' => $instance_id,
				));

				echo '<td>';

				if ($done_form_id !== '') {
					echo '<button class="button button-small" type="submit" form="' . esc_attr($done_form_id) . '" style="margin:0 6px 6px 0;">' . esc_html__('Done', 'backstage-venue-manager') . '</button>';
				}

				if ($remove_form_id !== '') {
					echo '<button class="button button-small" type="submit" form="' . esc_attr($remove_form_id) . '" style="margin:0 6px 6px 0;">' . esc_html__('Remove', 'backstage-venue-manager') . '</button>';
				}

				echo '<select name="assignment_mode" form="' . esc_attr($assignment_form_id) . '">';
				echo '<option value="person" ' . selected($assignment_mode, 'person', false) . '>' . esc_html__('Person', 'backstage-venue-manager') . '</option>';
				echo '<option value="role" ' . selected($assignment_mode, 'role', false) . '>' . esc_html__('Role', 'backstage-venue-manager') . '</option>';
				echo '<option value="scheduled_role" ' . selected($assignment_mode, 'scheduled_role', false) . '>' . esc_html__('Scheduled Role', 'backstage-venue-manager') . '</option>';
				echo '</select> ';
				echo '<select name="role_key" form="' . esc_attr($assignment_form_id) . '">';
				echo '<option value="">' . esc_html__('Select role', 'backstage-venue-manager') . '</option>';
				foreach ($role_options as $role_key => $role_label) {
					echo '<option value="' . esc_attr($role_key) . '" ' . selected($current_role_key, $role_key, false) . '>' . esc_html($role_label) . '</option>';
				}
				if ($current_role_key !== '' && !isset($role_options[$current_role_key])) {
					echo '<option value="' . esc_attr($current_role_key) . '" selected>' . esc_html($current_role_key) . '</option>';
				}
				echo '</select> ';
				echo '<select name="assignee_user_id" form="' . esc_attr($assignment_form_id) . '">';
				echo '<option value="0">' . esc_html__('Unassigned', 'backstage-venue-manager') . '</option>';
				foreach ($users as $uid => $label) {
					echo '<option value="' . esc_attr((string) $uid) . '" ' . selected($assignee_id, $uid, false) . '>' . esc_html($label) . '</option>';
				}
				echo '</select> ';
				echo '<label><input type="checkbox" name="assignment_locked" value="1" form="' . esc_attr($assignment_form_id) . '" ' . checked(!empty($row['assignment_locked']) && $assignee_id > 0, true, false) . '> ' . esc_html__('Lock', 'backstage-venue-manager') . '</label> ';
				echo '<button class="button button-small" type="submit" form="' . esc_attr($assignment_form_id) . '">' . esc_html__('Save Assignment', 'backstage-venue-manager') . '</button>';
				echo '<p style="margin:6px 0 0;"><a class="button button-small" href="' . esc_url($clone_url) . '">' . esc_html__('Make Repeatable', 'backstage-venue-manager') . '</a></p>';
				echo '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table>';
	}
}

if (!function_exists('bvmgr_tasks_render_event_plan_metabox')) {
	function bvmgr_tasks_render_event_plan_metabox(WP_Post $post): void
	{
		$event_id = absint($post->ID);
		if ($event_id <= 0) {
			echo '<p>' . esc_html__('Event ID is missing.', 'backstage-venue-manager') . '</p>';
			return;
		}

		if (!bvmgr_tasks_db_ready()) {
			echo '<p>' . esc_html__('Staff Tasks tables are unavailable. Run schema setup first.', 'backstage-venue-manager') . '</p>';
			return;
		}

		$rows = bvmgr_tasks_get_instances_for_event($event_id, true);
		$event_context = bvmgr_tasks_get_event_context($event_id);
		$users = bvmgr_tasks_admin_get_user_options();
		$role_options = bvmgr_tasks_admin_get_role_options(true);
		$checklist_options = bvmgr_tasks_admin_get_checklist_options(true, 'event');
		$venues = bvmgr_tasks_admin_get_venues();
		$can_manage_all = bvmgr_tasks_current_user_can_manage_all();
		$generate_url = wp_nonce_url(
			admin_url('admin-post.php?action=vms_tasks_generate_event&event_id=' . $event_id . '&return_page=event-plan'),
			'vms_tasks_generate_event_' . $event_id
		);
		bvmgr_tasks_admin_render_notices();

		echo '<p><a class="button" href="' . esc_url(bvmgr_tasks_admin_page_url('vms-tasks', array('event_id' => $event_id))) . '">' . esc_html__('Open Tasks Page For This Event', 'backstage-venue-manager') . '</a> ';
		if ($can_manage_all) {
			echo '<a class="button button-secondary" href="' . esc_url($generate_url) . '">' . esc_html__('Regenerate Tasks Now', 'backstage-venue-manager') . '</a>';
		}
		echo '</p>';

		echo '<h4>' . esc_html__('Applied Checklists', 'backstage-venue-manager') . '</h4>';
		if (!is_array($event_context)) {
			echo '<p class="description">' . esc_html__('Checklist context is unavailable for this event.', 'backstage-venue-manager') . '</p>';
		} else {
			$applied_checklists = bvmgr_tasks_get_applicable_checklists(
				absint($event_context['venue_id'] ?? 0),
				(string) ($event_context['event_type'] ?? '')
			);
			if (empty($applied_checklists)) {
				echo '<p class="description">' . esc_html__('No active checklist templates apply to this event right now.', 'backstage-venue-manager') . '</p>';
			} else {
				echo '<ul style="margin:6px 0 14px 20px;">';
				foreach ($applied_checklists as $checklist) {
					$checklist_id = absint($checklist['id'] ?? 0);
					if ($checklist_id <= 0) {
						continue;
					}
					$checklist_name = trim((string) ($checklist['name'] ?? ''));
					if ($checklist_name === '') {
						$checklist_name = sprintf(
							/* translators: %d is a checklist id. */
							__('Checklist #%d', 'backstage-venue-manager'),
							$checklist_id
						);
					}
					$reason = bvmgr_tasks_admin_event_plan_checklist_reason($checklist, $event_context, $venues);
					echo '<li><strong>' . esc_html($checklist_name) . '</strong> ';
					echo '<span class="description">' . esc_html(sprintf(
						/* translators: %s is the checklist applicability reason. */
						__('applied because: %s', 'backstage-venue-manager'),
						$reason
					)) . '</span></li>';
				}
				echo '</ul>';
			}
		}

		if ($can_manage_all) {
			echo '<h4>' . esc_html__('Add Task', 'backstage-venue-manager') . '</h4>';
			echo '<p class="description">' . esc_html__('Event-linked tasks repeat per event through checklist templates. Time-based recurrence is available from the main Tasks screen for tasks not linked to an event.', 'backstage-venue-manager') . '</p>';

			// This metabox renders inside the WordPress post edit form.
			// Nested <form> tags can corrupt the DOM and block unrelated actions
			// (including cancellation) via browser required-field validation.
			// Use an AJAX submit button instead.
			$nonce = wp_create_nonce('vms_tasks_create_one_off');
			echo '<div class="vms-tasks-event-plan-addtask" data-vms-event-id="' . esc_attr((string) $event_id) . '" data-vms-nonce="' . esc_attr($nonce) . '">';
			echo '<p><input type="text" class="widefat" data-vms-tasks-field="title" placeholder="' . esc_attr__('Task title', 'backstage-venue-manager') . '"></p>';
			echo '<p><textarea class="widefat" rows="2" data-vms-tasks-field="instructions" placeholder="' . esc_attr__('Instructions (optional)', 'backstage-venue-manager') . '"></textarea></p>';
			echo '<p>';
			echo '<label>' . esc_html__('Priority', 'backstage-venue-manager') . ' <select data-vms-tasks-field="priority">';
			foreach (array('low', 'normal', 'high') as $priority) {
				echo '<option value="' . esc_attr($priority) . '"' . selected($priority, 'normal', false) . '>' . esc_html(ucfirst($priority)) . '</option>';
			}
			echo '</select></label> ';
			echo '<label><input type="checkbox" data-vms-tasks-field="is_required" value="1" checked> ' . esc_html__('Required', 'backstage-venue-manager') . '</label> ';
			echo '<label>' . esc_html__('Due', 'backstage-venue-manager') . ' <input type="datetime-local" data-vms-tasks-field="due_at_local"></label>';
			echo '</p>';
			echo '<p>';
			echo '<label>' . esc_html__('Assignment mode', 'backstage-venue-manager') . ' <select data-vms-tasks-field="assignment_mode">';
			echo '<option value="person">' . esc_html__('Person', 'backstage-venue-manager') . '</option>';
			echo '<option value="role">' . esc_html__('Role', 'backstage-venue-manager') . '</option>';
			echo '<option value="scheduled_role">' . esc_html__('Scheduled Role', 'backstage-venue-manager') . '</option>';
			echo '</select></label> ';
			echo '<label>' . esc_html__('Role', 'backstage-venue-manager') . ' <select data-vms-tasks-field="role_key">';
			echo '<option value="">' . esc_html__('Select role', 'backstage-venue-manager') . '</option>';
			foreach ($role_options as $role_key => $role_label) {
				echo '<option value="' . esc_attr($role_key) . '">' . esc_html($role_label) . '</option>';
			}
			echo '</select></label> ';
			echo '<label>' . esc_html__('Assignee', 'backstage-venue-manager') . ' <select data-vms-tasks-field="assignee_user_id">';
			echo '<option value="0">' . esc_html__('Unassigned', 'backstage-venue-manager') . '</option>';
			foreach ($users as $uid => $label) {
				echo '<option value="' . esc_attr((string) $uid) . '">' . esc_html($label) . '</option>';
			}
			echo '</select></label> ';
			echo '<label><input type="checkbox" data-vms-tasks-field="assignment_locked" value="1" checked> ' . esc_html__('Lock assignment', 'backstage-venue-manager') . '</label> ';
			echo '<label><input type="checkbox" data-vms-tasks-field="make_repeatable_now" value="1"> ' . esc_html__('Also save as repeatable template now', 'backstage-venue-manager') . '</label> ';
			echo '<label>' . esc_html__('Add to checklist', 'backstage-venue-manager') . ' <select data-vms-tasks-field="repeatable_checklist_id">';
			echo '<option value="0">' . esc_html__('None (template only)', 'backstage-venue-manager') . '</option>';
			foreach ($checklist_options as $checklist_id => $checklist_label) {
				echo '<option value="' . esc_attr((string) $checklist_id) . '">' . esc_html($checklist_label) . '</option>';
			}
			echo '</select></label>';
			echo '</p>';
			echo '<div class="notice inline vms-tasks-event-plan-addtask__notice" role="status" aria-live="polite"></div>';
			echo '<p class="vms-tasks-event-plan-addtask__actions">';
			echo '<button class="button button-primary" type="button" data-vms-tasks-action="create-one-off">' . esc_html__('Create Task', 'backstage-venue-manager') . '</button> ';
			echo '<span class="spinner"></span>';
			echo '</p>';
			echo '</div>';
		}

		if (empty($rows)) {
			echo '<p>' . esc_html__('No task instances exist for this event yet.', 'backstage-venue-manager') . '</p>';
			return;
		}

		$grouped = is_array($event_context)
			? bvmgr_tasks_admin_group_event_plan_rows($rows, $event_context)
			: array(
				'pre_event' => array(),
				'day_of' => $rows,
				'post_event' => array(),
				'no_due' => array(),
			);

		$sections = array(
			'pre_event' => __('Pre-event', 'backstage-venue-manager'),
			'day_of' => __('Day-of', 'backstage-venue-manager'),
			'post_event' => __('Post-event', 'backstage-venue-manager'),
			'no_due' => __('No Due Date', 'backstage-venue-manager'),
		);
		foreach ($sections as $key => $heading) {
			$section_rows = isset($grouped[$key]) && is_array($grouped[$key]) ? $grouped[$key] : array();
			if (empty($section_rows)) {
				continue;
			}
			echo '<h4 style="margin-top:14px;">' . esc_html($heading) . '</h4>';
			bvmgr_tasks_render_event_plan_tasks_table($section_rows, $users, $role_options, $can_manage_all, $event_id);
		}
	}
}
