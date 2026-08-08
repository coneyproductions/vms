<?php

defined('ABSPATH') || exit;

if (!function_exists('vms_approvals_queue_notice_transient_key')) {
	function vms_approvals_queue_notice_transient_key(int $user_id): string
	{
		return 'vms_approvals_notice_' . max(0, $user_id);
	}
}

if (!function_exists('vms_approvals_queue_log')) {
	/**
	 * @param array<string,mixed> $context
	 * @param mixed               $error
	 */
	function vms_approvals_queue_log(string $event_code, array $context = array(), $error = null): void
	{
		if (!function_exists('vms_record_operational_issue')) {
			return;
		}

		$safe_context = array();
		foreach (array('provider', 'operation', 'status') as $key) {
			$value = $context[$key] ?? '';
			if (!is_scalar($value)) {
				continue;
			}
			$safe_context[$key] = substr(sanitize_key((string) $value), 0, 80);
		}

		vms_record_operational_issue(
			$event_code,
			$safe_context,
			$error
		);
	}
}

if (!function_exists('vms_approvals_queue_add_admin_notice')) {
	function vms_approvals_queue_add_admin_notice(string $message, string $type = 'warning'): void
	{
		$user_id = get_current_user_id();
		if ($user_id <= 0) {
			return;
		}

		$key = vms_approvals_queue_notice_transient_key((int) $user_id);
		$rows = get_transient($key);
		if (!is_array($rows)) {
			$rows = array();
		}

		$rows[] = array(
			'type' => sanitize_key($type),
			'message' => sanitize_text_field($message),
			'ts' => current_time('timestamp'),
		);
		$rows = array_values(array_slice($rows, -5, 5, true));
		set_transient($key, $rows, MINUTE_IN_SECONDS * 10);
	}
}

if (!function_exists('vms_approvals_queue_render_admin_notices')) {
	function vms_approvals_queue_render_admin_notices(): void
	{
		$user_id = get_current_user_id();
		if ($user_id <= 0) {
			return;
		}

		$key = vms_approvals_queue_notice_transient_key((int) $user_id);
		$rows = get_transient($key);
		if (!is_array($rows) || empty($rows)) {
			return;
		}
		delete_transient($key);

		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}
			$type = sanitize_key((string) ($row['type'] ?? 'warning'));
			$message = sanitize_text_field((string) ($row['message'] ?? ''));
			if ($message === '') {
				continue;
			}

			$class = 'notice-warning';
			if ($type === 'error') {
				$class = 'notice-error';
			} elseif ($type === 'success') {
				$class = 'notice-success';
			} elseif ($type === 'info') {
				$class = 'notice-info';
			}

			echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
		}
	}
}
add_action('admin_notices', 'vms_approvals_queue_render_admin_notices', 9);

if (!function_exists('vms_approvals_queue_register_provider')) {
	/**
	 * @param array<string,mixed> $args
	 */
	function vms_approvals_queue_register_provider(string $provider_id, array $args): void
	{
		$provider_id = sanitize_key($provider_id);
		if ($provider_id === '') {
			return;
		}

		$defaults = array(
			'label' => ucwords(str_replace('_', ' ', $provider_id)),
			'menu_label' => ucwords(str_replace('_', ' ', $provider_id)),
			'section_label' => ucwords(str_replace('_', ' ', $provider_id)),
			'description' => '',
			'capability' => 'manage_options',
			'pending_count_callback' => null,
			'summary_callback' => null,
			'screen_url' => '',
			'screen_url_callback' => null,
			'menu_slugs' => array(),
			'sort' => 100,
		);
		$provider = wp_parse_args($args, $defaults);

		$provider['id'] = $provider_id;
		$provider['label'] = sanitize_text_field((string) $provider['label']);
		$provider['menu_label'] = sanitize_text_field((string) $provider['menu_label']);
		$provider['section_label'] = sanitize_text_field((string) $provider['section_label']);
		$provider['description'] = sanitize_text_field((string) $provider['description']);
		$provider['capability'] = sanitize_key((string) $provider['capability']);
		$provider['screen_url'] = esc_url_raw((string) $provider['screen_url']);
		$provider['sort'] = (int) $provider['sort'];

		$menu_slugs = array();
		foreach ((array) ($provider['menu_slugs'] ?? array()) as $slug) {
			$slug = trim((string) $slug);
			if ($slug !== '') {
				$menu_slugs[] = $slug;
			}
		}
		$provider['menu_slugs'] = array_values(array_unique($menu_slugs));

		if (!isset($GLOBALS['vms_approvals_queue_providers']) || !is_array($GLOBALS['vms_approvals_queue_providers'])) {
			$GLOBALS['vms_approvals_queue_providers'] = array();
		}
		$GLOBALS['vms_approvals_queue_providers'][$provider_id] = $provider;
	}
}

if (!function_exists('vms_approvals_queue_normalize_filtered_provider')) {
	/**
	 * @param mixed $provider
	 * @return array<string,mixed>|null
	 */
	function vms_approvals_queue_normalize_filtered_provider(string $provider_id, $provider): ?array
	{
		$provider_id = sanitize_key($provider_id);
		if ($provider_id === '' || !is_array($provider)) {
			return null;
		}
		$provider['id'] = $provider_id;
		$provider['label'] = sanitize_text_field((string) ($provider['label'] ?? $provider_id));
		$provider['menu_label'] = sanitize_text_field((string) ($provider['menu_label'] ?? $provider['label']));
		$provider['section_label'] = sanitize_text_field((string) ($provider['section_label'] ?? $provider['label']));
		$provider['description'] = sanitize_text_field((string) ($provider['description'] ?? ''));
		$provider['capability'] = sanitize_key((string) ($provider['capability'] ?? 'manage_options'));
		$provider['screen_url'] = esc_url_raw((string) ($provider['screen_url'] ?? ''));
		$provider['sort'] = (int) ($provider['sort'] ?? 100);
		$provider['menu_slugs'] = array_values(array_filter(array_map('strval', (array) ($provider['menu_slugs'] ?? array()))));
		return $provider;
	}
}

if (!function_exists('vms_approvals_queue_get_providers')) {
	/**
	 * @return array<string,array<string,mixed>>
	 */
	function vms_approvals_queue_get_providers(): array
	{
		$providers = isset($GLOBALS['vms_approvals_queue_providers']) && is_array($GLOBALS['vms_approvals_queue_providers'])
			? $GLOBALS['vms_approvals_queue_providers']
			: array();

		$filtered = apply_filters('vms_approvals_queue_providers', $providers);
		if (!is_array($filtered)) {
			$filtered = $providers;
		}

		$normalized = array();
		foreach ($filtered as $provider_id => $provider) {
			$id = is_string($provider_id) ? $provider_id : '';
			if ($id === '' && is_array($provider) && isset($provider['id'])) {
				$id = (string) $provider['id'];
			}
			$row = vms_approvals_queue_normalize_filtered_provider($id, $provider);
			if ($row === null) {
				continue;
			}
			$normalized[$row['id']] = $row;
		}

		uasort($normalized, static function (array $a, array $b): int {
			$cmp = ((int) ($a['sort'] ?? 100)) <=> ((int) ($b['sort'] ?? 100));
			if ($cmp !== 0) {
				return $cmp;
			}
			return strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
		});

		return $normalized;
	}
}

if (!function_exists('vms_approvals_queue_user_can_provider')) {
	/**
	 * @param array<string,mixed> $provider
	 */
	function vms_approvals_queue_user_can_provider(array $provider): bool
	{
		$capability = sanitize_key((string) ($provider['capability'] ?? ''));
		if ($capability === '') {
			return true;
		}
		return current_user_can($capability) || current_user_can('manage_options');
	}
}

if (!function_exists('vms_approvals_queue_provider_url')) {
	/**
	 * @param array<string,mixed> $provider
	 */
	function vms_approvals_queue_provider_url(array $provider): string
	{
		if (!empty($provider['screen_url_callback']) && is_callable($provider['screen_url_callback'])) {
			try {
				$resolved = call_user_func($provider['screen_url_callback'], $provider);
				if (is_string($resolved) && $resolved !== '') {
					return esc_url_raw($resolved);
				}
			} catch (Throwable $e) {
				vms_approvals_queue_log(
					'approvals_provider_url_callback_failed',
					array(
						'provider' => (string) ($provider['id'] ?? ''),
						'operation' => 'resolve_url',
						'status' => 'failed',
					),
					$e
				);
			}
		}

		return esc_url_raw((string) ($provider['screen_url'] ?? ''));
	}
}

if (!function_exists('vms_approvals_queue_provider_pending_count')) {
	/**
	 * @param array<string,mixed> $provider
	 */
	function vms_approvals_queue_provider_pending_count(array $provider): int
	{
		$callback = $provider['pending_count_callback'] ?? null;
		if (!is_callable($callback)) {
			vms_approvals_queue_log(
				'approvals_provider_pending_callback_missing',
				array(
					'provider' => (string) ($provider['id'] ?? ''),
					'operation' => 'count_pending',
					'status' => 'missing',
				)
			);
			return 0;
		}

		try {
			$value = call_user_func($callback, $provider);
			if (is_wp_error($value)) {
				vms_approvals_queue_log(
					'approvals_provider_pending_callback_failed',
					array(
						'provider' => (string) ($provider['id'] ?? ''),
						'operation' => 'count_pending',
						'status' => 'failed',
					),
					$value
				);
				vms_approvals_queue_add_admin_notice(
					__('Approvals count refresh failed for one queue. Check logs for details.', 'backstage-venue-manager'),
					'warning'
				);
				return 0;
			}

			return max(0, absint($value));
		} catch (Throwable $e) {
			vms_approvals_queue_log(
				'approvals_provider_pending_callback_threw',
				array(
					'provider' => (string) ($provider['id'] ?? ''),
					'operation' => 'count_pending',
					'status' => 'failed',
				),
				$e
			);
			vms_approvals_queue_add_admin_notice(
				__('Approvals count refresh failed for one queue. Check logs for details.', 'backstage-venue-manager'),
				'warning'
			);
		}

		return 0;
	}
}

if (!function_exists('vms_approvals_queue_provider_summary_items')) {
	/**
	 * @param array<string,mixed> $provider
	 * @return array<int,array<string,string>>
	 */
	function vms_approvals_queue_provider_summary_items(array $provider): array
	{
		$callback = $provider['summary_callback'] ?? null;
		if (!is_callable($callback)) {
			return array();
		}

		try {
			$value = call_user_func($callback, $provider);
			if (!is_array($value)) {
				return array();
			}
			$items = array();
			foreach ($value as $row) {
				if (!is_array($row)) {
					continue;
				}
				$title = sanitize_text_field((string) ($row['title'] ?? ''));
				if ($title === '') {
					continue;
				}
				$meta = sanitize_text_field((string) ($row['meta'] ?? ''));
				$items[] = array(
					'title' => $title,
					'meta' => $meta,
				);
			}
			return array_slice($items, 0, 5);
		} catch (Throwable $e) {
			vms_approvals_queue_log(
				'approvals_provider_summary_callback_threw',
				array(
					'provider' => (string) ($provider['id'] ?? ''),
					'operation' => 'summarize',
					'status' => 'failed',
				),
				$e
			);
			return array();
		}
	}
}

if (!function_exists('vms_approvals_queue_collect_snapshot')) {
	/**
	 * @return array{generated_at:string,total_pending:int,providers:array<int,array<string,mixed>>}
	 */
	function vms_approvals_queue_collect_snapshot(bool $capability_aware = true): array
	{
		static $cache = array();

		$cache_key = ($capability_aware ? 'cap' : 'all') . ':' . (int) get_current_user_id();
		if (isset($cache[$cache_key])) {
			return $cache[$cache_key];
		}

		$snapshot = array(
			'generated_at' => (string) current_time('mysql'),
			'total_pending' => 0,
			'providers' => array(),
		);

		foreach (vms_approvals_queue_get_providers() as $provider) {
			if ($capability_aware && !vms_approvals_queue_user_can_provider($provider)) {
				continue;
			}

			$pending_count = vms_approvals_queue_provider_pending_count($provider);
			$screen_url = vms_approvals_queue_provider_url($provider);
			if ($screen_url === '') {
				vms_approvals_queue_log(
					'approvals_provider_url_missing',
					array(
						'provider' => (string) ($provider['id'] ?? ''),
						'operation' => 'resolve_url',
						'status' => 'missing',
					)
				);
			}

			$summary_items = array();
			if ($pending_count > 0) {
				$summary_items = vms_approvals_queue_provider_summary_items($provider);
			}

			$row = array(
				'id' => (string) ($provider['id'] ?? ''),
				'label' => (string) ($provider['label'] ?? ''),
				'menu_label' => (string) ($provider['menu_label'] ?? ''),
				'section_label' => (string) ($provider['section_label'] ?? ''),
				'description' => (string) ($provider['description'] ?? ''),
				'capability' => (string) ($provider['capability'] ?? ''),
				'menu_slugs' => (array) ($provider['menu_slugs'] ?? array()),
				'pending_count' => max(0, $pending_count),
				'screen_url' => $screen_url,
				'summary_items' => $summary_items,
			);

			$snapshot['providers'][] = $row;
			$snapshot['total_pending'] += max(0, $pending_count);
		}

		$cache[$cache_key] = $snapshot;
		return $snapshot;
	}
}

if (!function_exists('vms_approvals_queue_current_user_can_any_provider')) {
	function vms_approvals_queue_current_user_can_any_provider(): bool
	{
		foreach (vms_approvals_queue_get_providers() as $provider) {
			if (vms_approvals_queue_user_can_provider($provider)) {
				return true;
			}
		}
		return false;
	}
}

if (!function_exists('vms_approvals_queue_badge_html')) {
	function vms_approvals_queue_badge_html(int $count): string
	{
		$count = max(0, absint($count));
		if ($count <= 0) {
			return '';
		}

		return ' <span class="awaiting-mod count-' . $count . '"><span class="pending-count">' . $count . '</span></span>';
	}
}

if (!function_exists('vms_approvals_queue_strip_badge_markup')) {
	function vms_approvals_queue_strip_badge_markup(string $label): string
	{
		$label = preg_replace('/\s*<span class="awaiting-mod[^>]*>.*?<\/span>\s*/', '', $label);
		if (!is_string($label)) {
			return '';
		}
		return trim($label);
	}
}

if (!function_exists('vms_approvals_queue_update_menu_entry')) {
	function vms_approvals_queue_update_menu_entry(array $entry, string $label, int $pending_count = 0): array
	{
		$text = vms_approvals_queue_strip_badge_markup((string) $label);
		$entry[0] = $text . vms_approvals_queue_badge_html($pending_count);
		if (isset($entry[3])) {
			$entry[3] = $text;
		}
		return $entry;
	}
}

if (!function_exists('vms_approvals_queue_apply_menu_badges')) {
	function vms_approvals_queue_apply_menu_badges(): void
	{
		if (!is_admin()) {
			return;
		}
		if (!vms_approvals_queue_current_user_can_any_provider()) {
			return;
		}

		global $menu, $submenu;

		$snapshot = vms_approvals_queue_collect_snapshot(true);
		$total_pending = max(0, (int) ($snapshot['total_pending'] ?? 0));

		// Top-level VMS badge.
		if (is_array($menu)) {
			foreach ($menu as $index => $item) {
				if (!is_array($item) || (string) ($item[2] ?? '') !== 'vms-dashboard') {
					continue;
				}
				$menu[$index] = vms_approvals_queue_update_menu_entry(
					$item,
					__('VMS', 'backstage-venue-manager'),
					$total_pending
				);
				break;
			}
		}

		if (!isset($submenu['vms-dashboard']) || !is_array($submenu['vms-dashboard'])) {
			return;
		}

		$provider_counts = array();
		$provider_labels = array();
		$provider_slugs = array();
		foreach ((array) ($snapshot['providers'] ?? array()) as $provider) {
			$provider_id = sanitize_key((string) ($provider['id'] ?? ''));
			if ($provider_id === '') {
				continue;
			}
			$provider_counts[$provider_id] = max(0, absint($provider['pending_count'] ?? 0));
			$provider_labels[$provider_id] = sanitize_text_field((string) ($provider['menu_label'] ?? $provider['label'] ?? ''));
			$provider_slugs[$provider_id] = (array) ($provider['menu_slugs'] ?? array());
		}

		foreach ($submenu['vms-dashboard'] as $index => $item) {
			if (!is_array($item)) {
				continue;
			}
			$slug = (string) ($item[2] ?? '');
			if ($slug === '') {
				continue;
			}

			if ($slug === 'vms-approvals') {
				$submenu['vms-dashboard'][$index] = vms_approvals_queue_update_menu_entry(
					$item,
					__('Approvals', 'backstage-venue-manager'),
					$total_pending
				);
				continue;
			}

			foreach ($provider_slugs as $provider_id => $candidate_slugs) {
				if (!in_array($slug, (array) $candidate_slugs, true)) {
					continue;
				}

				$submenu['vms-dashboard'][$index] = vms_approvals_queue_update_menu_entry(
					$item,
					(string) ($provider_labels[$provider_id] ?? ''),
					(int) ($provider_counts[$provider_id] ?? 0)
				);
				break;
			}
		}
	}
}
add_action('admin_menu', 'vms_approvals_queue_apply_menu_badges', 1200);
add_action('admin_head', 'vms_approvals_queue_apply_menu_badges', 20);

if (!function_exists('vms_approvals_queue_register_menu')) {
	function vms_approvals_queue_register_menu(): void
	{
		if (!vms_approvals_queue_current_user_can_any_provider()) {
			return;
		}

		add_submenu_page(
			'vms-dashboard',
			__('Approvals', 'backstage-venue-manager'),
			__('Approvals', 'backstage-venue-manager'),
			'read',
			'vms-approvals',
			'vms_approvals_queue_render_page'
		);
	}
}
add_action('admin_menu', 'vms_approvals_queue_register_menu', 32);

if (!function_exists('vms_approvals_queue_default_vendor_post_type')) {
	function vms_approvals_queue_default_vendor_post_type(): string
	{
		$post_type = '';
		if (function_exists('vms_admin_ui_vendor_application_post_type')) {
			$post_type = sanitize_key((string) vms_admin_ui_vendor_application_post_type());
		}
		if ($post_type === '' && defined('VMS_VENDOR_APP_CPT')) {
			$post_type = sanitize_key((string) VMS_VENDOR_APP_CPT);
		}
		if ($post_type === '') {
			$post_type = 'vms_vendor_app';
		}
		return $post_type;
	}
}

if (!function_exists('vms_approvals_queue_verification_pending_count')) {
	function vms_approvals_queue_verification_pending_count(): int
	{
		if (!function_exists('vms_ticketing_verification_request_post_types')) {
			return 0;
		}

		$query = new WP_Query(array(
			'post_type' => vms_ticketing_verification_request_post_types(),
			'post_status' => array('pending'),
			'posts_per_page' => 1,
			'fields' => 'ids',
			'no_found_rows' => false,
		));
		return max(0, absint($query->found_posts));
	}
}

if (!function_exists('vms_approvals_queue_verification_summary')) {
	/**
	 * @return array<int,array<string,string>>
	 */
	function vms_approvals_queue_verification_summary(): array
	{
		if (!function_exists('vms_ticketing_verification_request_post_types')) {
			return array();
		}

		$rows = get_posts(array(
			'post_type' => vms_ticketing_verification_request_post_types(),
			'post_status' => array('pending'),
			'posts_per_page' => 5,
			'orderby' => 'date',
			'order' => 'DESC',
			'no_found_rows' => true,
		));
		$items = array();
		foreach ((array) $rows as $post) {
			if (!($post instanceof WP_Post)) {
				continue;
			}
			$request_id = (int) $post->ID;
			$user_id = absint(get_post_meta($request_id, 'user_id', true));
			$program = sanitize_key((string) get_post_meta($request_id, 'program', true));
			$submitted_at = (string) get_post_meta($request_id, 'submitted_at', true);

			$user = ($user_id > 0) ? get_userdata($user_id) : null;
			if ($user instanceof WP_User) {
				$name = trim((string) $user->display_name);
			} else {
				/* translators: %d: verification request user ID. */
				$name = sprintf(__('User #%d', 'backstage-venue-manager'), $user_id);
			}
			if ($name === '') {
				/* translators: %d: verification request post ID. */
				$name = sprintf(__('Request #%d', 'backstage-venue-manager'), $request_id);
			}

			$program_label = function_exists('vms_ticketing_verification_program_label')
				? (string) vms_ticketing_verification_program_label($program)
				: ucwords(str_replace('_', ' ', $program));
			$meta_bits = array_filter(array($program_label, $submitted_at));
			$items[] = array(
				'title' => $name,
				'meta' => implode(' - ', $meta_bits),
			);
		}
		return $items;
	}
}

if (!function_exists('vms_approvals_queue_vendor_pending_count')) {
	function vms_approvals_queue_vendor_pending_count(): int
	{
		if (function_exists('vms_vendor_app_count_pending')) {
			return max(0, absint(vms_vendor_app_count_pending()));
		}
		return 0;
	}
}

if (!function_exists('vms_approvals_queue_vendor_summary')) {
	/**
	 * @return array<int,array<string,string>>
	 */
	function vms_approvals_queue_vendor_summary(): array
	{
		$post_type = vms_approvals_queue_default_vendor_post_type();
		if ($post_type === '' || !post_type_exists($post_type)) {
			return array();
		}

		$rows = get_posts(array(
			'post_type' => array($post_type),
			'post_status' => array('publish', 'draft', 'pending', 'private'),
			'posts_per_page' => 5,
			'orderby' => 'date',
			'order' => 'DESC',
			'no_found_rows' => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The review-queue summary is capped at five applications and filters exact pending plus confirmed-or-legacy states.
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key' => '_vms_app_status',
					'value' => 'pending',
					'compare' => '=',
				),
				array(
					'relation' => 'OR',
					array(
						'key' => function_exists('vms_vendor_app_meta_key') ? (vms_vendor_app_meta_key('confirmation_state') ?: '_vms_app_confirmation_state') : '_vms_app_confirmation_state',
						'value' => 'confirmed',
						'compare' => '=',
					),
					array(
						'key' => function_exists('vms_vendor_app_meta_key') ? (vms_vendor_app_meta_key('confirmation_state') ?: '_vms_app_confirmation_state') : '_vms_app_confirmation_state',
						'compare' => 'NOT EXISTS',
					),
				),
			),
		));

		$items = array();
		foreach ((array) $rows as $post) {
			if (!($post instanceof WP_Post)) {
				continue;
			}
			$app_id = (int) $post->ID;
			$title = get_the_title($app_id);
			if (!is_string($title) || trim($title) === '') {
				/* translators: %d: vendor application post ID. */
				$title = sprintf(__('Application #%d', 'backstage-venue-manager'), $app_id);
			}
			$email = sanitize_email((string) get_post_meta($app_id, '_vms_app_email', true));
			$created = mysql2date(get_option('date_format') . ' ' . get_option('time_format'), (string) $post->post_date, true);
			$meta_bits = array_filter(array($email, $created));
			$items[] = array(
				'title' => $title,
				'meta' => implode(' - ', $meta_bits),
			);
		}
		return $items;
	}
}

if (!function_exists('vms_approvals_queue_register_default_providers')) {
	function vms_approvals_queue_register_default_providers(): void
	{
		$verification_cap = function_exists('vms_ticketing_verification_manage_capability')
			? (string) vms_ticketing_verification_manage_capability()
			: 'manage_options';

		vms_approvals_queue_register_provider(
			'credential_access',
			array(
				'label' => __('Credential / Special Ticket Access Applications', 'backstage-venue-manager'),
				'menu_label' => __('Eligibility Approvals', 'backstage-venue-manager'),
				'section_label' => __('Credential / Special Ticket Access Applications', 'backstage-venue-manager'),
				'description' => __('Review submissions for veteran, teacher, and other verified-eligibility ticket programs.', 'backstage-venue-manager'),
				'capability' => $verification_cap,
				'pending_count_callback' => 'vms_approvals_queue_verification_pending_count',
				'summary_callback' => 'vms_approvals_queue_verification_summary',
				'screen_url_callback' => static function (): string {
					return (string) add_query_arg(
						array(
							'page' => 'vms-verifications',
							'status' => 'pending',
						),
						admin_url('admin.php')
					);
				},
				'menu_slugs' => array('vms-verifications'),
				'sort' => 10,
			)
		);

		$vendor_post_type = vms_approvals_queue_default_vendor_post_type();
		$vendor_screen_slug = 'edit.php?post_type=' . $vendor_post_type;

		vms_approvals_queue_register_provider(
			'vendor_applications',
			array(
				'label' => __('Vendor Applications', 'backstage-venue-manager'),
				'menu_label' => __('Vendor Applications', 'backstage-venue-manager'),
				'section_label' => __('Vendor Applications', 'backstage-venue-manager'),
				'description' => __('Approve or reject incoming vendor submissions before they move into active vendor records.', 'backstage-venue-manager'),
				'capability' => 'edit_posts',
				'pending_count_callback' => 'vms_approvals_queue_vendor_pending_count',
				'summary_callback' => 'vms_approvals_queue_vendor_summary',
				'screen_url_callback' => static function () use ($vendor_screen_slug): string {
					return admin_url($vendor_screen_slug);
				},
				'menu_slugs' => array(
					$vendor_screen_slug,
					'edit.php?post_type=vms_vendor_app',
					'edit.php?post_type=vms_vendor_application',
				),
				'sort' => 20,
			)
		);
	}
}
add_action('init', 'vms_approvals_queue_register_default_providers', 40);

if (!function_exists('vms_approvals_queue_audit_option_key')) {
	function vms_approvals_queue_audit_option_key(): string
	{
		return 'vms_approvals_audit_log';
	}
}

if (!function_exists('vms_approvals_queue_record_transition')) {
	/**
	 * @param array<string,mixed> $context
	 */
	function vms_approvals_queue_record_transition(string $queue_id, int $item_id, string $from_status, string $to_status, array $context = array()): void
	{
		$queue_id = sanitize_key($queue_id);
		$item_id = absint($item_id);
		$from_status = sanitize_key($from_status);
		$to_status = sanitize_key($to_status);
		if ($queue_id === '' || $item_id <= 0 || $to_status === '') {
			return;
		}

		$entry = array(
			'ts' => current_time('mysql'),
			'queue_id' => $queue_id,
			'item_id' => $item_id,
			'from_status' => $from_status,
			'to_status' => $to_status,
			'actor_id' => (int) get_current_user_id(),
			'note' => sanitize_text_field((string) ($context['note'] ?? '')),
		);

		$existing = get_option(vms_approvals_queue_audit_option_key(), array());
		if (!is_array($existing)) {
			$existing = array();
		}
		$existing[] = $entry;
		$existing = array_values(array_slice($existing, -300, 300, true));
		update_option(vms_approvals_queue_audit_option_key(), $existing, false);
	}
}

if (!function_exists('vms_approvals_queue_recent_audit_entries')) {
	/**
	 * @return array<int,array<string,mixed>>
	 */
	function vms_approvals_queue_recent_audit_entries(int $limit = 12): array
	{
		$limit = max(1, min(50, absint($limit)));
		$existing = get_option(vms_approvals_queue_audit_option_key(), array());
		if (!is_array($existing) || empty($existing)) {
			return array();
		}
		return array_reverse(array_slice($existing, -$limit, $limit, true));
	}
}

if (!function_exists('vms_approvals_queue_render_help_button')) {
	function vms_approvals_queue_render_help_button(string $tour_id, string $anchor, string $label): string
	{
		$button = '<button type="button" class="button button-secondary vms-tour-help-trigger" data-vms-tour-start="' . esc_attr($tour_id) . '" data-vms-tour="' . esc_attr($anchor) . '">' . esc_html($label) . '</button>';
		if (function_exists('vms_render_help_button')) {
			$button = vms_render_help_button(
				array(
					'tour_id' => $tour_id,
					'anchor' => $anchor,
					'label' => $label,
					'class' => 'button-secondary',
				)
			);
		}
		return $button;
	}
}

if (!function_exists('vms_approvals_queue_allowed_help_html')) {
	/**
	 * @return array<string,array<string,bool>>
	 */
	function vms_approvals_queue_allowed_help_html(): array
	{
		return array(
			'button' => array(
				'type' => true,
				'class' => true,
				'style' => true,
				'data-vms-tour' => true,
				'data-vms-tour-start' => true,
				'data-vms-help-action' => true,
				'data-vms-help-open' => true,
			),
			'details' => array(
				'class' => true,
				'style' => true,
				'data-vms-tour' => true,
			),
			'div' => array(
				'class' => true,
				'style' => true,
			),
			'summary' => array(
				'class' => true,
				'style' => true,
			),
		);
	}
}

if (!function_exists('vms_approvals_queue_render_page')) {
	function vms_approvals_queue_render_page(): void
	{
		if (!vms_approvals_queue_current_user_can_any_provider()) {
			wp_die(esc_html__('You do not have permission to access approvals.', 'backstage-venue-manager'));
		}

		$snapshot = vms_approvals_queue_collect_snapshot(true);
		$total_pending = max(0, (int) ($snapshot['total_pending'] ?? 0));
		$providers = (array) ($snapshot['providers'] ?? array());
		$audit_rows = vms_approvals_queue_recent_audit_entries(10);
		$help_button = vms_approvals_queue_render_help_button(
			'vms.approvals.queue',
			'approvals.queue.help',
			__('Start Guided Tour', 'backstage-venue-manager')
		);

		echo '<div class="wrap vms-approvals-page" data-vms-tour="approvals.queue.root">';
		echo '<h1>' . esc_html__('Approvals', 'backstage-venue-manager') . '</h1>';
		echo '<p class="description">' . esc_html__('Use this queue first whenever pending badges appear. It consolidates approval work across credential and vendor workflows so submissions are not missed.', 'backstage-venue-manager') . '</p>';
		echo '<p data-vms-tour="approvals.queue.help">' . wp_kses($help_button, vms_approvals_queue_allowed_help_html()) . '</p>';

		echo '<section class="vms-approvals-overview" data-vms-tour="approvals.queue.total">';
		echo '<h2>' . esc_html__('Pending Review Items', 'backstage-venue-manager') . '</h2>';
		echo '<p class="vms-approvals-total">';
		echo '<strong>' . esc_html((string) $total_pending) . '</strong> ';
		echo esc_html(_n('item pending', 'items pending', $total_pending, 'backstage-venue-manager'));
		echo '</p>';
		echo '</section>';

		echo '<div class="vms-approvals-grid">';
		foreach ($providers as $provider) {
			$provider_id = sanitize_key((string) ($provider['id'] ?? ''));
			if ($provider_id === '') {
				continue;
			}
			$label = (string) ($provider['section_label'] ?? $provider['label'] ?? $provider_id);
			$pending_count = max(0, absint($provider['pending_count'] ?? 0));
			$screen_url = (string) ($provider['screen_url'] ?? '');
			$description = (string) ($provider['description'] ?? '');
			$summary_items = (array) ($provider['summary_items'] ?? array());

			echo '<section class="vms-approvals-card" data-vms-tour="approvals.queue.section-' . esc_attr($provider_id) . '">';
			echo '<header class="vms-approvals-card__header">';
			echo '<h3>' . esc_html($label) . '</h3>';
			echo '<span class="vms-approvals-pill">' . esc_html((string) $pending_count) . '</span>';
			echo '</header>';
			if ($description !== '') {
				echo '<p class="description">' . esc_html($description) . '</p>';
			}
			if ($pending_count > 0) {
				echo '<p><strong>' . esc_html__('Action needed now:', 'backstage-venue-manager') . '</strong> ' . esc_html__('These submissions are waiting for an operator decision.', 'backstage-venue-manager') . '</p>';
			} else {
				echo '<p>' . esc_html__('Queue is clear. New submissions will appear here immediately when created.', 'backstage-venue-manager') . '</p>';
			}
			if ($screen_url !== '') {
				echo '<p><a class="button button-primary" href="' . esc_url($screen_url) . '">' . esc_html__('Open Review Screen', 'backstage-venue-manager') . '</a></p>';
			} else {
				echo '<p class="description">' . esc_html__('Destination screen is unavailable right now. Check logs for provider URL errors.', 'backstage-venue-manager') . '</p>';
			}

			if (!empty($summary_items)) {
				echo '<ul class="vms-approvals-preview">';
				foreach ($summary_items as $summary) {
					if (!is_array($summary)) {
						continue;
					}
					$title = sanitize_text_field((string) ($summary['title'] ?? ''));
					if ($title === '') {
						continue;
					}
					$meta = sanitize_text_field((string) ($summary['meta'] ?? ''));
					echo '<li><strong>' . esc_html($title) . '</strong>';
					if ($meta !== '') {
						echo '<span>' . esc_html($meta) . '</span>';
					}
					echo '</li>';
				}
				echo '</ul>';
			}
			echo '</section>';
		}
		echo '</div>';

		echo '<section class="vms-approvals-audit" data-vms-tour="approvals.queue.audit">';
		echo '<h2>' . esc_html__('Recent Approval Audit Trail', 'backstage-venue-manager') . '</h2>';
		echo '<p class="description">' . esc_html__('Review recent status transitions when operators need to confirm who approved or rejected a submission.', 'backstage-venue-manager') . '</p>';
		if (empty($audit_rows)) {
			echo '<p>' . esc_html__('No approval transitions have been recorded yet.', 'backstage-venue-manager') . '</p>';
		} else {
			echo '<table class="widefat striped">';
			echo '<thead><tr>';
			echo '<th>' . esc_html__('When', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Queue', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Item', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Transition', 'backstage-venue-manager') . '</th>';
			echo '<th>' . esc_html__('Operator', 'backstage-venue-manager') . '</th>';
			echo '</tr></thead><tbody>';
			foreach ($audit_rows as $row) {
				if (!is_array($row)) {
					continue;
				}
				$ts = sanitize_text_field((string) ($row['ts'] ?? ''));
				$queue_id = sanitize_key((string) ($row['queue_id'] ?? ''));
				$item_id = absint($row['item_id'] ?? 0);
				$from_status = sanitize_key((string) ($row['from_status'] ?? ''));
				$to_status = sanitize_key((string) ($row['to_status'] ?? ''));
				$actor_id = absint($row['actor_id'] ?? 0);
				$actor = ($actor_id > 0) ? get_userdata($actor_id) : null;
				$actor_label = ($actor instanceof WP_User) ? (string) $actor->display_name : __('System', 'backstage-venue-manager');
				echo '<tr>';
				echo '<td>' . esc_html($ts) . '</td>';
				echo '<td>' . esc_html($queue_id) . '</td>';
				echo '<td>#' . esc_html((string) $item_id) . '</td>';
				echo '<td>' . esc_html(($from_status !== '' ? $from_status : __('unknown', 'backstage-venue-manager')) . ' -> ' . $to_status) . '</td>';
				echo '<td>' . esc_html($actor_label) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}
		echo '</section>';
		echo '</div>';
	}
}

if (!function_exists('vms_approvals_queue_render_dashboard_card')) {
	function vms_approvals_queue_render_dashboard_card(): void
	{
		if (!vms_approvals_queue_current_user_can_any_provider()) {
			return;
		}

		$snapshot = vms_approvals_queue_collect_snapshot(true);
		$providers = (array) ($snapshot['providers'] ?? array());
		if (empty($providers)) {
			return;
		}

		$total_pending = max(0, absint($snapshot['total_pending'] ?? 0));
		$queue_url = admin_url('admin.php?page=vms-approvals');
		echo '<section id="vms-dashboard-approvals" class="vms-dashboard-approvals-card" data-vms-tour="dashboard.approvals.card">';
		echo '<h2>' . esc_html__('Pending Approvals', 'backstage-venue-manager') . '</h2>';
		echo '<p>' . esc_html__('Start here after login so no submitted application is missed.', 'backstage-venue-manager') . '</p>';
		echo '<p class="vms-dashboard-approvals-card__total"><strong>' . esc_html((string) $total_pending) . '</strong> ' . esc_html(_n('item pending', 'items pending', $total_pending, 'backstage-venue-manager')) . '</p>';
		echo '<ul class="vms-dashboard-approvals-card__list">';
		foreach ($providers as $provider) {
			$label = sanitize_text_field((string) ($provider['menu_label'] ?? $provider['label'] ?? ''));
			$count = max(0, absint($provider['pending_count'] ?? 0));
			$screen_url = (string) ($provider['screen_url'] ?? '');
			echo '<li data-vms-tour="dashboard.approvals.provider">';
			if ($screen_url !== '') {
				echo '<a href="' . esc_url($screen_url) . '">' . esc_html($label) . '</a>';
			} else {
				echo '<span>' . esc_html($label) . '</span>';
			}
			echo '<strong>' . esc_html((string) $count) . '</strong>';
			echo '</li>';
		}
		echo '</ul>';
		echo '<p data-vms-tour="dashboard.approvals.open"><a class="button button-primary" href="' . esc_url($queue_url) . '">' . esc_html__('Open Approvals Queue', 'backstage-venue-manager') . '</a></p>';
		echo '</section>';
	}
}

if (!function_exists('vms_approvals_queue_print_styles')) {
	function vms_approvals_queue_print_styles(): void
	{
		if (!is_admin()) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only approvals queue routing only affects admin display state.
		$page = vms_request_read_key($_GET, 'page');
		if ($page !== 'vms-approvals' && $page !== 'vms-dashboard' && $page !== 'vms-verifications') {
			return;
		}

		echo '<style id="vms-approvals-queue-styles">';
		echo '.vms-approvals-overview{margin:14px 0 18px;padding:12px 14px;border:1px solid #ccd0d4;background:#fff;border-radius:8px;}';
		echo '.vms-approvals-total{margin:0;font-size:16px;}';
		echo '.vms-approvals-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:12px;margin:14px 0 20px;}';
		echo '.vms-approvals-card{background:#fff;border:1px solid #ccd0d4;border-radius:8px;padding:14px;}';
		echo '.vms-approvals-card__header{display:flex;align-items:center;justify-content:space-between;gap:12px;}';
		echo '.vms-approvals-card__header h3{margin:0;font-size:15px;}';
		echo '.vms-approvals-pill{display:inline-flex;align-items:center;justify-content:center;min-width:28px;height:28px;border-radius:999px;background:#d63638;color:#fff;font-weight:700;padding:0 8px;}';
		echo '.vms-approvals-preview{margin:8px 0 0 18px;}';
		echo '.vms-approvals-preview li{margin:0 0 4px 0;}';
		echo '.vms-approvals-preview li span{display:block;color:#646970;font-size:12px;}';
		echo '.vms-dashboard-approvals-card{margin:14px 0;padding:14px;border:1px solid #ccd0d4;background:#fff;border-left:4px solid #d63638;}';
		echo '.vms-dashboard-approvals-card h2{margin:0 0 6px 0;}';
		echo '.vms-dashboard-approvals-card__total{margin:0 0 10px;font-size:15px;}';
		echo '.vms-dashboard-approvals-card__list{margin:0 0 10px 0;padding:0;list-style:none;}';
		echo '.vms-dashboard-approvals-card__list li{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:4px 0;border-top:1px solid #f0f0f1;}';
		echo '.vms-dashboard-approvals-card__list li:first-child{border-top:0;}';
		echo '.vms-verifications-toolbar{display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;margin:8px 0 10px;}';
		echo '.vms-verifications-toolbar .button{margin:0;}';
		echo '</style>';
	}
}
add_action('admin_head', 'vms_approvals_queue_print_styles', 30);

if (!function_exists('vms_approvals_queue_render_vendor_list_tour_launcher')) {
	function vms_approvals_queue_render_vendor_list_tour_launcher(): void
	{
		if (!is_admin()) {
			return;
		}

		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		$screen_id = (is_object($screen) && isset($screen->id)) ? sanitize_key((string) $screen->id) : '';
		if (!in_array($screen_id, array('edit-vms_vendor_app', 'edit-vms_vendor_application'), true)) {
			return;
		}

		$button = vms_approvals_queue_render_help_button(
			'vms.approvals.vendor_applications',
			'approvals.vendor.help',
			__('Start Guided Tour', 'backstage-venue-manager')
		);
		echo '<div class="notice notice-info" data-vms-tour="approvals.vendor.help"><p><strong>' . esc_html__('Vendor approvals live on this table.', 'backstage-venue-manager') . '</strong> ';
		echo esc_html__('Review pending applications promptly so approved vendors are not blocked from onboarding workflows.', 'backstage-venue-manager');
		echo '</p><p>' . wp_kses($button, vms_approvals_queue_allowed_help_html()) . '</p></div>';
	}
}
add_action('all_admin_notices', 'vms_approvals_queue_render_vendor_list_tour_launcher', 50);

if (!function_exists('vms_approvals_queue_register_tours')) {
	/**
	 * @param array<int,array<string,mixed>> $tours
	 * @return array<int,array<string,mixed>>
	 */
	function vms_approvals_queue_register_tours(array $tours): array
	{
		$audience = array(
			'capabilities_any' => array('manage_options'),
			'capabilities_all' => array(),
			'roles_any' => array(),
			'roles_all' => array(),
		);

		$tours[] = array(
			'id' => 'vms.approvals.queue',
			'title' => __('Approvals Queue', 'backstage-venue-manager'),
			'screen' => 'admin:vms-approvals',
			'version' => '1.0.0',
			'level' => 'beginner',
			'description' => __('Understand what is pending, why badges appear, and where to start reviewing.', 'backstage-venue-manager'),
			'audience' => $audience,
			'auto_run' => true,
			'priority' => 8,
			'steps' => array(
				array(
					'id' => 'queue_total',
					'selector' => '[data-vms-tour="approvals.queue.total"]',
					'title' => __('Pending Total', 'backstage-venue-manager'),
					'body' => wp_kses_post(__('This total is the combined pending workload; it drives the red admin badges so operators notice approvals immediately.', 'backstage-venue-manager')),
					'placement' => 'bottom',
					'guard' => array('type' => 'element_exists'),
				),
				array(
					'id' => 'queue_credentials',
					'selector' => '[data-vms-tour="approvals.queue.section-credential_access"]',
					'title' => __('Credential Applications', 'backstage-venue-manager'),
					'body' => wp_kses_post(__('Review these first when verification-dependent tickets are on sale. Approve to unlock eligibility, or reject when proof does not meet policy.', 'backstage-venue-manager')),
					'placement' => 'top',
					'guard' => array('type' => 'element_exists'),
				),
				array(
					'id' => 'queue_vendor_apps',
					'selector' => '[data-vms-tour="approvals.queue.section-vendor_applications"]',
					'title' => __('Vendor Applications', 'backstage-venue-manager'),
					'body' => wp_kses_post(__('These requests stay visible here until reviewed, which prevents vendor onboarding submissions from sitting unnoticed.', 'backstage-venue-manager')),
					'placement' => 'top',
					'guard' => array('type' => 'element_exists'),
				),
				array(
					'id' => 'queue_audit',
					'selector' => '[data-vms-tour="approvals.queue.audit"]',
					'title' => __('Audit Trail', 'backstage-venue-manager'),
					'body' => wp_kses_post(__('Use this history to confirm who changed a status and when, especially during dispute resolution or missed-submission investigations.', 'backstage-venue-manager')),
					'placement' => 'top',
					'guard' => array('type' => 'element_exists'),
				),
			),
		);

		$tours[] = array(
			'id' => 'vms.approvals.credentials',
			'title' => __('Eligibility Approvals', 'backstage-venue-manager'),
			'screen' => 'admin:vms-verifications',
			'version' => '1.0.0',
			'level' => 'beginner',
			'description' => __('Process credential access requests without missing pending submissions.', 'backstage-venue-manager'),
			'audience' => $audience,
			'auto_run' => true,
			'priority' => 9,
			'steps' => array(
				array(
					'id' => 'credentials_help',
					'selector' => '[data-vms-tour="approvals.credentials.help"]',
					'title' => __('Why This Screen Exists', 'backstage-venue-manager'),
					'body' => wp_kses_post(__('This queue controls access to eligibility-based tickets. Pending requests stay here until you decide, so approvals are never hidden in raw records.', 'backstage-venue-manager')),
					'placement' => 'bottom',
					'guard' => array('type' => 'element_exists'),
				),
				array(
					'id' => 'credentials_filters',
					'selector' => '[data-vms-tour="approvals.credentials.filters"]',
					'title' => __('Filter and Search', 'backstage-venue-manager'),
					'body' => wp_kses_post(__('Use status, program, and search filters to narrow the queue quickly before taking decisions.', 'backstage-venue-manager')),
					'placement' => 'bottom',
					'guard' => array('type' => 'element_exists'),
				),
				array(
					'id' => 'credentials_table',
					'selector' => '[data-vms-tour="approvals.credentials.table"]',
					'title' => __('Decision Table', 'backstage-venue-manager'),
					'body' => wp_kses_post(__('Each row shows submitter context, status, and proof controls so you can decide without opening hidden meta screens.', 'backstage-venue-manager')),
					'placement' => 'top',
					'guard' => array('type' => 'element_exists'),
				),
				array(
					'id' => 'credentials_actions',
					'selector' => '[data-vms-tour="approvals.credentials.actions"]',
					'title' => __('Approve vs Deny', 'backstage-venue-manager'),
					'body' => wp_kses_post(__('Approving grants the program and removes stored proof; denying keeps an explicit decision record so unresolved requests do not linger as pending.', 'backstage-venue-manager')),
					'placement' => 'left',
					'guard' => array('type' => 'element_exists'),
				),
			),
		);

		$vendor_tour_steps = array(
			array(
				'id' => 'vendor_intro',
				'selector' => '[data-vms-tour="approvals.vendor.help"]',
				'title' => __('Vendor Approval Queue', 'backstage-venue-manager'),
				'body' => wp_kses_post(__('This list is the operator gate for vendor applications. Reviewing it regularly prevents onboarding delays.', 'backstage-venue-manager')),
				'placement' => 'bottom',
				'guard' => array('type' => 'element_exists'),
			),
			array(
				'id' => 'vendor_search',
				'selector' => '#post-search-input',
				'title' => __('Search Applications', 'backstage-venue-manager'),
				'body' => wp_kses_post(__('Search by applicant details when a submitter asks for status, so you can respond without scanning the full table.', 'backstage-venue-manager')),
				'placement' => 'bottom',
				'guard' => array('type' => 'element_exists'),
			),
			array(
				'id' => 'vendor_table',
				'selector' => '.wp-list-table.posts',
				'title' => __('Review Rows', 'backstage-venue-manager'),
				'body' => wp_kses_post(__('Status, contact info, and row actions are visible together so approvals and rejections are fast and traceable.', 'backstage-venue-manager')),
				'placement' => 'top',
				'guard' => array('type' => 'element_exists'),
			),
		);

		$tours[] = array(
			'id' => 'vms.approvals.vendor_applications',
			'title' => __('Vendor Applications Queue', 'backstage-venue-manager'),
			'screen' => 'admin:edit-vms_vendor_app',
			'version' => '1.0.0',
			'level' => 'beginner',
			'description' => __('Review vendor applications in one predictable queue.', 'backstage-venue-manager'),
			'audience' => $audience,
			'auto_run' => true,
			'priority' => 9,
			'steps' => $vendor_tour_steps,
		);

		$tours[] = array(
			'id' => 'vms.approvals.vendor_applications_legacy',
			'title' => __('Vendor Applications Queue (Legacy Slug)', 'backstage-venue-manager'),
			'screen' => 'admin:edit-vms_vendor_application',
			'version' => '1.0.0',
			'level' => 'beginner',
			'description' => __('Review vendor applications in one predictable queue.', 'backstage-venue-manager'),
			'audience' => $audience,
			'auto_run' => true,
			'priority' => 9,
			'steps' => $vendor_tour_steps,
		);

		$tours[] = array(
			'id' => 'vms.dashboard.approvals',
			'title' => __('Dashboard Pending Approvals Card', 'backstage-venue-manager'),
			'screen' => 'admin:vms-dashboard',
			'version' => '1.0.0',
			'level' => 'beginner',
			'description' => __('Spot pending approvals immediately from the dashboard.', 'backstage-venue-manager'),
			'audience' => $audience,
			'auto_run' => true,
			'priority' => 15,
			'steps' => array(
				array(
					'id' => 'dashboard_card',
					'selector' => '[data-vms-tour="dashboard.approvals.card"]',
					'title' => __('Pending Approvals Card', 'backstage-venue-manager'),
					'body' => wp_kses_post(__('This card answers "Do I have anything pending?" immediately after login so approvals are never buried.', 'backstage-venue-manager')),
					'placement' => 'bottom',
					'guard' => array('type' => 'element_exists'),
				),
				array(
					'id' => 'dashboard_card_open',
					'selector' => '[data-vms-tour="dashboard.approvals.open"]',
					'title' => __('Open Queue First', 'backstage-venue-manager'),
					'body' => wp_kses_post(__('Use this button to jump directly into the unified queue before handling other admin tasks.', 'backstage-venue-manager')),
					'placement' => 'left',
					'guard' => array('type' => 'element_exists'),
				),
			),
		);

		return $tours;
	}
}
add_filter('vms_tours_register', 'vms_approvals_queue_register_tours');
