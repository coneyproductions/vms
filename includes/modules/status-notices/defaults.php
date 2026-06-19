<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_status_notices_capability')) {
	function vms_status_notices_capability(): string
	{
		return 'manage_options';
	}
}

if (!function_exists('vms_status_notice_default_notice')) {
	function vms_status_notice_default_notice(): array
	{
		return array(
			'id' => 0,
			'title' => '',
			'enabled' => 0,
			'scope' => 'front',
			'severity' => 'warning',
			'priority' => 0,
			'updated_at' => 0,
			'headline' => '',
			'body_html' => '',
			'primary_btn_label' => '',
			'primary_btn_url' => '',
			'secondary_btn_label' => '',
			'secondary_btn_url' => '',
			'intensity' => 2,
			'placement' => 'top',
			'dismissible' => 1,
			'dismiss_ttl' => '1d',
			'trigger' => 'on_load',
			'trigger_delay_ms' => 0,
			'trigger_selector' => '',
			'pages_mode' => 'all',
			'include_page_types' => array(),
			'include_object_ids' => array(),
			'exclude_object_ids' => array(),
			'url_contains' => array(),
			'url_excludes' => array(),
			'user_mode' => 'everyone',
			'roles_include' => array(),
			'roles_exclude' => array(),
			'user_ids_include' => array(),
			'device_mode' => 'any',
			'browser_include' => array(),
			'os_include' => array(),
			'schedule_mode' => 'always',
			'start_at' => '',
			'end_at' => '',
			'start_ts' => 0,
			'end_ts' => 0,
			'frequency' => 'every_load',
			'metrics_enabled' => 0,
			'impressions' => 0,
			'dismissals' => 0,
			'primary_clicks' => 0,
			'secondary_clicks' => 0,
			'audience_summary' => '',
		);
	}
}

if (!function_exists('vms_status_notice_allowed_scopes')) {
	function vms_status_notice_allowed_scopes(): array
	{
		return array('front', 'admin', 'both');
	}
}

if (!function_exists('vms_status_notice_allowed_severities')) {
	function vms_status_notice_allowed_severities(): array
	{
		return array('info', 'warning', 'critical');
	}
}

if (!function_exists('vms_status_notice_allowed_placements')) {
	function vms_status_notice_allowed_placements(): array
	{
		return array('top', 'bottom');
	}
}

if (!function_exists('vms_status_notice_allowed_dismiss_ttls')) {
	function vms_status_notice_allowed_dismiss_ttls(): array
	{
		return array('1h', '1d', '7d', 'forever');
	}
}

if (!function_exists('vms_status_notice_allowed_triggers')) {
	function vms_status_notice_allowed_triggers(): array
	{
		return array('on_load', 'after_delay', 'on_element_visible', 'when_element_exists');
	}
}

if (!function_exists('vms_status_notice_allowed_pages_mode')) {
	function vms_status_notice_allowed_pages_mode(): array
	{
		return array('all', 'include');
	}
}

if (!function_exists('vms_status_notice_allowed_page_types')) {
	function vms_status_notice_allowed_page_types(): array
	{
		return array('event', 'product', 'cart', 'checkout', 'account', 'ticketing', 'generic');
	}
}

if (!function_exists('vms_status_notice_allowed_user_mode')) {
	function vms_status_notice_allowed_user_mode(): array
	{
		return array('everyone', 'logged_in', 'logged_out', 'roles_include', 'roles_exclude');
	}
}

if (!function_exists('vms_status_notice_allowed_device_mode')) {
	function vms_status_notice_allowed_device_mode(): array
	{
		return array('any', 'mobile', 'tablet', 'desktop');
	}
}

if (!function_exists('vms_status_notice_allowed_browsers')) {
	function vms_status_notice_allowed_browsers(): array
	{
		return array('safari_ios', 'safari_mac', 'chrome', 'firefox', 'edge', 'other');
	}
}

if (!function_exists('vms_status_notice_allowed_os')) {
	function vms_status_notice_allowed_os(): array
	{
		return array('ios', 'android', 'windows', 'macos', 'linux', 'other');
	}
}

if (!function_exists('vms_status_notice_allowed_schedule_mode')) {
	function vms_status_notice_allowed_schedule_mode(): array
	{
		return array('always', 'scheduled');
	}
}

if (!function_exists('vms_status_notice_allowed_frequency')) {
	function vms_status_notice_allowed_frequency(): array
	{
		return array('every_load', 'once_session', 'once_per_ttl', 'until_dismissed');
	}
}

if (!function_exists('vms_status_notice_template_keys')) {
	function vms_status_notice_template_keys(): array
	{
		return array('maintenance_banner', 'major_outage', 'browser_warning', 'admin_alert', 'ios_safari_ticketing_warning');
	}
}

if (!function_exists('vms_status_notice_template_defaults')) {
	function vms_status_notice_template_defaults(string $template): array
	{
		$base = vms_status_notice_default_notice();
		$template = sanitize_key($template);
		if (!in_array($template, vms_status_notice_template_keys(), true)) {
			return $base;
		}

		switch ($template) {
			case 'maintenance_banner':
				return array_merge($base, array(
					'enabled' => 1,
					'title' => 'Maintenance Banner',
					'scope' => 'front',
					'severity' => 'info',
					'intensity' => 2,
					'placement' => 'top',
					'headline' => 'Planned maintenance in progress',
					'body_html' => 'Some sections may load slowly while we complete maintenance.',
				));
			case 'major_outage':
				return array_merge($base, array(
					'enabled' => 1,
					'title' => 'Major Outage Alert',
					'scope' => 'both',
					'severity' => 'critical',
					'priority' => 200,
					'intensity' => 5,
					'dismissible' => 0,
					'frequency' => 'every_load',
					'headline' => 'Service disruption detected',
					'body_html' => 'We are actively working to restore service. Please check back shortly.',
				));
			case 'browser_warning':
				return array_merge($base, array(
					'enabled' => 1,
					'title' => 'Browser Compatibility Warning',
					'scope' => 'front',
					'severity' => 'warning',
					'priority' => 100,
					'intensity' => 3,
					'placement' => 'bottom',
					'dismiss_ttl' => '1d',
					'headline' => 'Having trouble in this browser?',
					'body_html' => 'If checkout stalls, try Chrome, Firefox, or a desktop browser.',
				));
			case 'admin_alert':
				return array_merge($base, array(
					'enabled' => 1,
					'title' => 'Admin Alert',
					'scope' => 'admin',
					'severity' => 'warning',
					'intensity' => 2,
					'headline' => 'Admin attention needed',
					'body_html' => 'A system integration requires operator review.',
				));
			case 'ios_safari_ticketing_warning':
				return array_merge($base, array(
					'enabled' => 1,
					'title' => 'iOS Safari Ticketing Warning',
					'scope' => 'front',
					'severity' => 'warning',
					'priority' => 100,
					'intensity' => 3,
					'placement' => 'bottom',
					'dismissible' => 1,
					'dismiss_ttl' => '1d',
					'trigger' => 'when_element_exists',
					'trigger_selector' => '.tribe-tickets__tickets, #tribe-tickets, .tribe-events-tickets',
					'pages_mode' => 'include',
					'include_page_types' => array('event', 'checkout', 'cart', 'ticketing'),
					'user_mode' => 'everyone',
					'device_mode' => 'any',
					'browser_include' => array('safari_ios'),
					'os_include' => array('ios'),
					'headline' => 'Having trouble checking out in Safari?',
					'body_html' => 'We\'re fixing an iPhone/iPad Safari issue that can interfere with ticket checkout. For now, please try Chrome/Firefox, or purchase from a desktop browser.',
					'primary_btn_label' => 'Workaround help',
					'primary_btn_url' => home_url('/help/ticket-checkout-workaround/'),
					'secondary_btn_label' => 'Continue anyway',
				));
		}

		return $base;
	}
}

if (!function_exists('vms_status_notice_scope_labels')) {
	function vms_status_notice_scope_labels(): array
	{
		return array(
			'front' => __('Front-end', 'vms'),
			'admin' => __('WP Admin', 'vms'),
			'both' => __('Front + Admin', 'vms'),
		);
	}
}

if (!function_exists('vms_status_notice_severity_labels')) {
	function vms_status_notice_severity_labels(): array
	{
		return array(
			'info' => __('Info', 'vms'),
			'warning' => __('Warning', 'vms'),
			'critical' => __('Critical', 'vms'),
		);
	}
}

if (!function_exists('vms_status_notice_page_type_labels')) {
	function vms_status_notice_page_type_labels(): array
	{
		return array(
			'event' => __('Event pages', 'vms'),
			'product' => __('Product pages', 'vms'),
			'cart' => __('Cart page', 'vms'),
			'checkout' => __('Checkout page', 'vms'),
			'account' => __('Account page', 'vms'),
			'ticketing' => __('Ticketing surfaces', 'vms'),
			'generic' => __('Generic pages', 'vms'),
		);
	}
}

if (!function_exists('vms_status_notice_device_labels')) {
	function vms_status_notice_device_labels(): array
	{
		return array(
			'any' => __('Any device', 'vms'),
			'mobile' => __('Mobile', 'vms'),
			'tablet' => __('Tablet', 'vms'),
			'desktop' => __('Desktop', 'vms'),
		);
	}
}

if (!function_exists('vms_status_notice_browser_labels')) {
	function vms_status_notice_browser_labels(): array
	{
		return array(
			'safari_ios' => __('Safari (iOS)', 'vms'),
			'safari_mac' => __('Safari (macOS)', 'vms'),
			'chrome' => __('Chrome', 'vms'),
			'firefox' => __('Firefox', 'vms'),
			'edge' => __('Edge', 'vms'),
			'other' => __('Other', 'vms'),
		);
	}
}

if (!function_exists('vms_status_notice_os_labels')) {
	function vms_status_notice_os_labels(): array
	{
		return array(
			'ios' => __('iOS', 'vms'),
			'android' => __('Android', 'vms'),
			'windows' => __('Windows', 'vms'),
			'macos' => __('macOS', 'vms'),
			'linux' => __('Linux', 'vms'),
			'other' => __('Other', 'vms'),
		);
	}
}
