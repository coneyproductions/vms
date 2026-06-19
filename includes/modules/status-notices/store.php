<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_status_notice_parse_list')) {
	function vms_status_notice_parse_list(string $raw): array
	{
		$raw = str_replace(array("\r\n", "\r"), "\n", $raw);
		$parts = array_map('trim', explode("\n", $raw));
		$out = array();
		foreach ($parts as $part) {
			if ($part === '') {
				continue;
			}
			$out[] = sanitize_text_field($part);
		}
		return array_values(array_unique($out));
	}
}

if (!function_exists('vms_status_notice_parse_int_list')) {
	function vms_status_notice_parse_int_list(string $raw): array
	{
		$raw = str_replace(array("\r\n", "\r", ';', '|'), "\n", $raw);
		$raw = str_replace(',', "\n", $raw);
		$parts = array_map('trim', explode("\n", $raw));
		$out = array();
		foreach ($parts as $part) {
			if ($part === '' || !is_numeric($part)) {
				continue;
			}
			$id = absint($part);
			if ($id > 0) {
				$out[] = $id;
			}
		}
		return array_values(array_unique($out));
	}
}

if (!function_exists('vms_status_notice_parse_local_datetime')) {
	function vms_status_notice_parse_local_datetime(string $raw): int
	{
		$raw = trim($raw);
		if ($raw === '') {
			return 0;
		}
		$raw = str_replace('T', ' ', $raw);
		$tz = wp_timezone();
		try {
			$dt = new DateTimeImmutable($raw, $tz);
			return (int) $dt->getTimestamp();
		} catch (Exception $e) {
			return 0;
		}
	}
}

if (!function_exists('vms_status_notice_format_local_datetime')) {
	function vms_status_notice_format_local_datetime(string $raw, int $ts): string
	{
		$raw = trim($raw);
		if ($raw !== '') {
			return str_replace(' ', 'T', substr($raw, 0, 16));
		}
		if ($ts <= 0) {
			return '';
		}
		return (string) wp_date('Y-m-d\TH:i', $ts, wp_timezone());
	}
}

if (!function_exists('vms_status_notice_sanitize_in_set')) {
	function vms_status_notice_sanitize_in_set(string $value, array $allowed, string $fallback): string
	{
		$value = sanitize_key($value);
		if (!in_array($value, $allowed, true)) {
			return $fallback;
		}
		return $value;
	}
}

if (!function_exists('vms_status_notice_sanitize_payload')) {
	function vms_status_notice_sanitize_payload(array $raw): array
	{
		$defaults = vms_status_notice_default_notice();

		$title = sanitize_text_field((string) ($raw['title'] ?? ''));
		if ($title === '') {
			$title = __('Untitled Status Notice', 'vms');
		}

		$enabled = !empty($raw['enabled']) ? 1 : 0;
		$scope = vms_status_notice_sanitize_in_set((string) ($raw['scope'] ?? ''), vms_status_notice_allowed_scopes(), 'front');
		$severity = vms_status_notice_sanitize_in_set((string) ($raw['severity'] ?? ''), vms_status_notice_allowed_severities(), 'warning');
		$priority = (int) ($raw['priority'] ?? 0);
		if ($priority > 10000) {
			$priority = 10000;
		}
		if ($priority < -10000) {
			$priority = -10000;
		}

		$headline = sanitize_text_field((string) ($raw['headline'] ?? ''));
		$body_html = wp_kses_post((string) ($raw['body_html'] ?? ''));
		$primary_btn_label = sanitize_text_field((string) ($raw['primary_btn_label'] ?? ''));
		$primary_btn_url = esc_url_raw((string) ($raw['primary_btn_url'] ?? ''));
		$secondary_btn_label = sanitize_text_field((string) ($raw['secondary_btn_label'] ?? ''));
		$secondary_btn_url = esc_url_raw((string) ($raw['secondary_btn_url'] ?? ''));

		$intensity = (int) ($raw['intensity'] ?? 2);
		if ($intensity < 1 || $intensity > 5) {
			$intensity = 2;
		}

		$placement = vms_status_notice_sanitize_in_set((string) ($raw['placement'] ?? ''), vms_status_notice_allowed_placements(), 'top');
		$dismissible = !empty($raw['dismissible']) ? 1 : 0;
		$dismiss_ttl = vms_status_notice_sanitize_in_set((string) ($raw['dismiss_ttl'] ?? ''), vms_status_notice_allowed_dismiss_ttls(), '1d');
		$trigger = vms_status_notice_sanitize_in_set((string) ($raw['trigger'] ?? ''), vms_status_notice_allowed_triggers(), 'on_load');
		$trigger_delay_ms = absint((string) ($raw['trigger_delay_ms'] ?? '0'));
		if ($trigger_delay_ms > 60000) {
			$trigger_delay_ms = 60000;
		}
		$trigger_selector = trim(sanitize_text_field((string) ($raw['trigger_selector'] ?? '')));

		$pages_mode = vms_status_notice_sanitize_in_set((string) ($raw['pages_mode'] ?? ''), vms_status_notice_allowed_pages_mode(), 'all');
		$include_page_types = array_values(array_intersect(
			array_map('sanitize_key', (array) ($raw['include_page_types'] ?? array())),
			vms_status_notice_allowed_page_types()
		));
		$include_object_ids = vms_status_notice_parse_int_list((string) ($raw['include_object_ids_raw'] ?? ''));
		$exclude_object_ids = vms_status_notice_parse_int_list((string) ($raw['exclude_object_ids_raw'] ?? ''));
		$url_contains = vms_status_notice_parse_list((string) ($raw['url_contains_raw'] ?? ''));
		$url_excludes = vms_status_notice_parse_list((string) ($raw['url_excludes_raw'] ?? ''));

		$user_mode = vms_status_notice_sanitize_in_set((string) ($raw['user_mode'] ?? ''), vms_status_notice_allowed_user_mode(), 'everyone');
		$roles_include = array_values(array_map('sanitize_key', (array) ($raw['roles_include'] ?? array())));
		$roles_exclude = array_values(array_map('sanitize_key', (array) ($raw['roles_exclude'] ?? array())));
		$user_ids_include = vms_status_notice_parse_int_list((string) ($raw['user_ids_include_raw'] ?? ''));

		$device_mode = vms_status_notice_sanitize_in_set((string) ($raw['device_mode'] ?? ''), vms_status_notice_allowed_device_mode(), 'any');
		$browser_include = array_values(array_intersect(
			array_map('sanitize_key', (array) ($raw['browser_include'] ?? array())),
			vms_status_notice_allowed_browsers()
		));
		$os_include = array_values(array_intersect(
			array_map('sanitize_key', (array) ($raw['os_include'] ?? array())),
			vms_status_notice_allowed_os()
		));

		$schedule_mode = vms_status_notice_sanitize_in_set((string) ($raw['schedule_mode'] ?? ''), vms_status_notice_allowed_schedule_mode(), 'always');
		$start_at_input = trim((string) ($raw['start_at'] ?? ''));
		$end_at_input = trim((string) ($raw['end_at'] ?? ''));
		$start_ts = vms_status_notice_parse_local_datetime($start_at_input);
		$end_ts = vms_status_notice_parse_local_datetime($end_at_input);
		$start_at = $start_ts > 0 ? (string) wp_date('Y-m-d H:i:s', $start_ts, wp_timezone()) : '';
		$end_at = $end_ts > 0 ? (string) wp_date('Y-m-d H:i:s', $end_ts, wp_timezone()) : '';
		$frequency = vms_status_notice_sanitize_in_set((string) ($raw['frequency'] ?? ''), vms_status_notice_allowed_frequency(), 'every_load');

		$metrics_enabled = !empty($raw['metrics_enabled']) ? 1 : 0;

		$payload = array_merge($defaults, array(
			'title' => $title,
			'enabled' => $enabled,
			'scope' => $scope,
			'severity' => $severity,
			'priority' => $priority,
			'updated_at' => time(),
			'headline' => $headline,
			'body_html' => $body_html,
			'primary_btn_label' => $primary_btn_label,
			'primary_btn_url' => $primary_btn_url,
			'secondary_btn_label' => $secondary_btn_label,
			'secondary_btn_url' => $secondary_btn_url,
			'intensity' => $intensity,
			'placement' => $placement,
			'dismissible' => $dismissible,
			'dismiss_ttl' => $dismiss_ttl,
			'trigger' => $trigger,
			'trigger_delay_ms' => $trigger_delay_ms,
			'trigger_selector' => $trigger_selector,
			'pages_mode' => $pages_mode,
			'include_page_types' => $include_page_types,
			'include_object_ids' => $include_object_ids,
			'exclude_object_ids' => $exclude_object_ids,
			'url_contains' => $url_contains,
			'url_excludes' => $url_excludes,
			'user_mode' => $user_mode,
			'roles_include' => $roles_include,
			'roles_exclude' => $roles_exclude,
			'user_ids_include' => $user_ids_include,
			'device_mode' => $device_mode,
			'browser_include' => $browser_include,
			'os_include' => $os_include,
			'schedule_mode' => $schedule_mode,
			'start_at' => $start_at,
			'end_at' => $end_at,
			'start_ts' => $start_ts,
			'end_ts' => $end_ts,
			'frequency' => $frequency,
			'metrics_enabled' => $metrics_enabled,
		));

		$payload['audience_summary'] = vms_status_notice_build_audience_summary($payload);
		return $payload;
	}
}

if (!function_exists('vms_status_notice_meta_map')) {
	function vms_status_notice_meta_map(): array
	{
		return array(
			'enabled' => '_vms_notice_enabled',
			'scope' => '_vms_notice_scope',
			'severity' => '_vms_notice_severity',
			'priority' => '_vms_notice_priority',
			'updated_at' => '_vms_notice_updated_at',
			'headline' => '_vms_notice_headline',
			'body_html' => '_vms_notice_body_html',
			'primary_btn_label' => '_vms_notice_primary_btn_label',
			'primary_btn_url' => '_vms_notice_primary_btn_url',
			'secondary_btn_label' => '_vms_notice_secondary_btn_label',
			'secondary_btn_url' => '_vms_notice_secondary_btn_url',
			'intensity' => '_vms_notice_intensity',
			'placement' => '_vms_notice_placement',
			'dismissible' => '_vms_notice_dismissible',
			'dismiss_ttl' => '_vms_notice_dismiss_ttl',
			'trigger' => '_vms_notice_trigger',
			'trigger_delay_ms' => '_vms_notice_trigger_delay_ms',
			'trigger_selector' => '_vms_notice_trigger_selector',
			'pages_mode' => '_vms_notice_pages_mode',
			'include_page_types' => '_vms_notice_include_page_types',
			'include_object_ids' => '_vms_notice_include_object_ids',
			'exclude_object_ids' => '_vms_notice_exclude_object_ids',
			'url_contains' => '_vms_notice_url_contains',
			'url_excludes' => '_vms_notice_url_excludes',
			'user_mode' => '_vms_notice_user_mode',
			'roles_include' => '_vms_notice_roles_include',
			'roles_exclude' => '_vms_notice_roles_exclude',
			'user_ids_include' => '_vms_notice_user_ids_include',
			'device_mode' => '_vms_notice_device_mode',
			'browser_include' => '_vms_notice_browser_include',
			'os_include' => '_vms_notice_os_include',
			'schedule_mode' => '_vms_notice_schedule_mode',
			'start_at' => '_vms_notice_start_at',
			'end_at' => '_vms_notice_end_at',
			'start_ts' => '_vms_notice_start_ts',
			'end_ts' => '_vms_notice_end_ts',
			'frequency' => '_vms_notice_frequency',
			'metrics_enabled' => '_vms_notice_metrics_enabled',
			'impressions' => '_vms_notice_impressions',
			'dismissals' => '_vms_notice_dismissals',
			'primary_clicks' => '_vms_notice_primary_clicks',
			'secondary_clicks' => '_vms_notice_secondary_clicks',
			'audience_summary' => '_vms_notice_audience_summary',
		);
	}
}

if (!function_exists('vms_status_notice_build_audience_summary')) {
	function vms_status_notice_build_audience_summary(array $notice): string
	{
		$scope_labels = vms_status_notice_scope_labels();
		$page_labels = vms_status_notice_page_type_labels();
		$device_labels = vms_status_notice_device_labels();
		$browser_labels = vms_status_notice_browser_labels();
		$os_labels = vms_status_notice_os_labels();

		$parts = array();
		$scope = (string) ($notice['scope'] ?? 'front');
		$parts[] = isset($scope_labels[$scope]) ? (string) $scope_labels[$scope] : $scope;

		$page_types = (array) ($notice['include_page_types'] ?? array());
		if (!empty($page_types)) {
			$labels = array();
			foreach ($page_types as $key) {
				$key = sanitize_key((string) $key);
				$labels[] = isset($page_labels[$key]) ? (string) $page_labels[$key] : $key;
			}
			$parts[] = implode(', ', $labels);
		} else {
			$parts[] = __('All pages', 'vms');
		}

		$device = (string) ($notice['device_mode'] ?? 'any');
		$parts[] = isset($device_labels[$device]) ? (string) $device_labels[$device] : $device;

		$os_include = (array) ($notice['os_include'] ?? array());
		if (!empty($os_include)) {
			$labels = array();
			foreach ($os_include as $key) {
				$key = sanitize_key((string) $key);
				$labels[] = isset($os_labels[$key]) ? (string) $os_labels[$key] : $key;
			}
			$parts[] = implode(', ', $labels);
		}

		$browser_include = (array) ($notice['browser_include'] ?? array());
		if (!empty($browser_include)) {
			$labels = array();
			foreach ($browser_include as $key) {
				$key = sanitize_key((string) $key);
				$labels[] = isset($browser_labels[$key]) ? (string) $browser_labels[$key] : $key;
			}
			$parts[] = implode(', ', $labels);
		}

		$user_mode = (string) ($notice['user_mode'] ?? 'everyone');
		switch ($user_mode) {
			case 'logged_in':
				$parts[] = __('Logged-in users', 'vms');
				break;
			case 'logged_out':
				$parts[] = __('Logged-out users', 'vms');
				break;
			case 'roles_include':
				$parts[] = __('Specific roles', 'vms');
				break;
			case 'roles_exclude':
				$parts[] = __('Exclude roles', 'vms');
				break;
			default:
				$parts[] = __('Everyone', 'vms');
		}

		return implode(' • ', array_values(array_filter($parts)));
	}
}

if (!function_exists('vms_status_notice_get')) {
	function vms_status_notice_get(int $notice_id): ?array
	{
		$post = get_post($notice_id);
		if (!($post instanceof WP_Post) || $post->post_type !== vms_status_notice_post_type()) {
			return null;
		}

		$map = vms_status_notice_meta_map();
		$notice = vms_status_notice_default_notice();
		$notice['id'] = (int) $notice_id;
		$notice['title'] = (string) $post->post_title;
		foreach ($map as $field => $meta_key) {
			$notice[$field] = get_post_meta($notice_id, $meta_key, true);
		}

		foreach (array('enabled', 'dismissible', 'metrics_enabled', 'priority', 'updated_at', 'intensity', 'trigger_delay_ms', 'start_ts', 'end_ts', 'impressions', 'dismissals', 'primary_clicks', 'secondary_clicks') as $int_key) {
			$notice[$int_key] = isset($notice[$int_key]) ? (int) $notice[$int_key] : 0;
		}

		foreach (array('include_page_types', 'include_object_ids', 'exclude_object_ids', 'url_contains', 'url_excludes', 'roles_include', 'roles_exclude', 'user_ids_include', 'browser_include', 'os_include') as $array_key) {
			$value = $notice[$array_key] ?? array();
			if (!is_array($value)) {
				$value = array();
			}
			$notice[$array_key] = array_values($value);
		}

		$notice['start_at'] = vms_status_notice_format_local_datetime((string) ($notice['start_at'] ?? ''), (int) ($notice['start_ts'] ?? 0));
		$notice['end_at'] = vms_status_notice_format_local_datetime((string) ($notice['end_at'] ?? ''), (int) ($notice['end_ts'] ?? 0));
		$notice['audience_summary'] = vms_status_notice_build_audience_summary($notice);
		return $notice;
	}
}

if (!function_exists('vms_status_notice_save')) {
	function vms_status_notice_save(int $notice_id, array $raw_payload): int
	{
		$payload = vms_status_notice_sanitize_payload($raw_payload);
		$postarr = array(
			'post_type' => vms_status_notice_post_type(),
			'post_title' => $payload['title'],
			'post_status' => 'publish',
		);

		if ($notice_id > 0) {
			$postarr['ID'] = $notice_id;
			$result = wp_update_post($postarr, true);
		} else {
			$result = wp_insert_post($postarr, true);
		}

		if (is_wp_error($result) || $result <= 0) {
			return 0;
		}

		$notice_id = (int) $result;
		$payload['id'] = $notice_id;

		$map = vms_status_notice_meta_map();
		foreach ($map as $field => $meta_key) {
			if (!array_key_exists($field, $payload)) {
				continue;
			}
			update_post_meta($notice_id, $meta_key, $payload[$field]);
		}

		return $notice_id;
	}
}

if (!function_exists('vms_status_notice_query_all')) {
	function vms_status_notice_query_all(): array
	{
		$posts = get_posts(array(
			'post_type' => vms_status_notice_post_type(),
			'post_status' => array('publish', 'draft'),
			'posts_per_page' => -1,
			'orderby' => 'date',
			'order' => 'DESC',
		));

		$out = array();
		foreach ($posts as $post) {
			if (!($post instanceof WP_Post)) {
				continue;
			}
			$notice = vms_status_notice_get((int) $post->ID);
			if (is_array($notice)) {
				$out[] = $notice;
			}
		}
		return $out;
	}
}

if (!function_exists('vms_status_notice_enabled_for_scope')) {
	function vms_status_notice_enabled_for_scope(string $scope): array
	{
		$scope = sanitize_key($scope);
		$notices = vms_status_notice_query_all();
		$out = array();
		foreach ($notices as $notice) {
			if (empty($notice['enabled'])) {
				continue;
			}
			$notice_scope = (string) ($notice['scope'] ?? 'front');
			if ($scope === 'front' && !in_array($notice_scope, array('front', 'both'), true)) {
				continue;
			}
			if ($scope === 'admin' && !in_array($notice_scope, array('admin', 'both'), true)) {
				continue;
			}
			$out[] = $notice;
		}

		usort($out, static function (array $a, array $b): int {
			$priority_cmp = ((int) ($b['priority'] ?? 0)) <=> ((int) ($a['priority'] ?? 0));
			if ($priority_cmp !== 0) {
				return $priority_cmp;
			}
			return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
		});

		return $out;
	}
}
