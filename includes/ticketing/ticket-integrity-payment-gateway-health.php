<?php
defined('ABSPATH') || exit;

function vms_ticket_integrity_payment_gateway_health_option_key(): string
{
	return 'vms_ticket_integrity_payment_gateway_health';
}

function vms_ticket_integrity_payment_gateway_notice_option_key(): string
{
	return 'vms_ticket_integrity_payment_gateway_notice';
}

function vms_ticket_integrity_payment_gateway_health_hook(): string
{
	return 'vms_ticket_integrity_payment_gateway_health';
}

function vms_ticket_integrity_payment_gateway_health_defaults(): array
{
	return array(
		'version' => 1,
		'status' => 'unknown',
		'status_label' => __('Unknown', 'backstage-venue-manager'),
		'summary' => '',
		'diagnostic_message' => '',
		'last_checked_gmt' => 0,
		'last_checked_local' => __('Never', 'backstage-venue-manager'),
		'status_changed_gmt' => 0,
		'status_changed_local' => __('Never', 'backstage-venue-manager'),
		'trigger' => '',
		'site_environment' => array(),
		'checkout' => array(),
		'square' => array(),
		'apple_pay' => array(),
		'checks' => array(),
		'failed_checks' => array(),
		'warnings' => array(),
		'incident' => array(
			'active' => false,
		),
		'last_incident' => array(),
	);
}

function vms_ticket_integrity_payment_gateway_status_label(string $status): string
{
	$status = sanitize_key($status);
	switch ($status) {
		case 'ok':
			return __('OK', 'backstage-venue-manager');
		case 'warning':
			return __('Warning', 'backstage-venue-manager');
		case 'critical':
			return __('Critical', 'backstage-venue-manager');
		default:
			return __('Unknown', 'backstage-venue-manager');
	}
}

function vms_ticket_integrity_payment_gateway_status_css(string $status): string
{
	$status = sanitize_key($status);
	switch ($status) {
		case 'ok':
			return 'green';
		case 'warning':
			return 'yellow';
		case 'critical':
			return 'red';
		default:
			return 'neutral';
	}
}

function vms_ticket_integrity_payment_gateway_status_rank(string $status): int
{
	$status = sanitize_key($status);
	switch ($status) {
		case 'critical':
			return 30;
		case 'warning':
			return 20;
		case 'ok':
			return 10;
		default:
			return 0;
	}
}

function vms_ticket_integrity_payment_gateway_check(string $key, string $label, string $status, string $message): array
{
	$status = sanitize_key($status);
	if (!in_array($status, array('ok', 'warning', 'critical'), true)) {
		$status = 'unknown';
	}

	$key = sanitize_key($key);
	$label = sanitize_text_field($label);
	$message = sanitize_text_field($message);

	return array(
		'key' => $key,
		'label' => $label,
		'status' => $status,
		'status_label' => vms_ticket_integrity_payment_gateway_status_label($status),
		'status_css' => vms_ticket_integrity_payment_gateway_status_css($status),
		'message' => $message,
	);
}

function vms_ticket_integrity_payment_gateway_descriptor($gateway): array
{
	$id = '';
	if (is_object($gateway) && isset($gateway->id)) {
		$id = sanitize_key((string) $gateway->id);
	}

	$title = '';
	if (is_object($gateway) && method_exists($gateway, 'get_title')) {
		try {
			$title = trim(wp_strip_all_tags((string) $gateway->get_title()));
		} catch (Throwable $error) {
			unset($error);
			$title = '';
		}
	}
	if ($title === '' && is_object($gateway) && isset($gateway->title)) {
		$title = trim(wp_strip_all_tags((string) $gateway->title));
	}
	if ($title === '' && is_object($gateway) && isset($gateway->method_title)) {
		$title = trim(wp_strip_all_tags((string) $gateway->method_title));
	}
	if ($title === '') {
		$title = $id !== '' ? $id : __('Payment gateway', 'backstage-venue-manager');
	}

	$enabled = false;
	if (is_object($gateway) && method_exists($gateway, 'get_option')) {
		try {
			$enabled = ('yes' === (string) $gateway->get_option('enabled', (string) ($gateway->enabled ?? 'no')));
		} catch (Throwable $error) {
			unset($error);
			$enabled = ('yes' === (string) ($gateway->enabled ?? 'no'));
		}
	} elseif (is_object($gateway)) {
		$enabled = ('yes' === (string) ($gateway->enabled ?? 'no'));
	}

	return array(
		'id' => $id,
		'title' => $title,
		'enabled' => $enabled,
		'class' => is_object($gateway) ? get_class($gateway) : '',
	);
}

function vms_ticket_integrity_payment_gateway_ids(array $descriptors): array
{
	$ids = array();
	foreach ($descriptors as $descriptor) {
		if (!is_array($descriptor)) {
			continue;
		}
		$id = sanitize_key((string) ($descriptor['id'] ?? ''));
		if ($id === '') {
			continue;
		}
		$ids[] = $id;
	}

	return array_values(array_unique($ids));
}

function vms_ticket_integrity_payment_gateway_titles(array $descriptors): array
{
	$titles = array();
	foreach ($descriptors as $descriptor) {
		if (!is_array($descriptor)) {
			continue;
		}
		$title = trim((string) ($descriptor['title'] ?? ''));
		if ($title === '') {
			continue;
		}
		$titles[] = $title;
	}

	return array_values(array_unique($titles));
}

function vms_ticket_integrity_payment_gateway_objects(): array
{
	if (!function_exists('WC') || !class_exists('WooCommerce')) {
		return array();
	}

	try {
		$woocommerce = WC();
		if (!is_object($woocommerce) || !method_exists($woocommerce, 'payment_gateways')) {
			return array();
		}

		$manager = $woocommerce->payment_gateways();
		if (!is_object($manager)) {
			return array();
		}

		$gateways = array();
		if (method_exists($manager, 'payment_gateways')) {
			$gateways = $manager->payment_gateways();
		} elseif (isset($manager->payment_gateways) && is_array($manager->payment_gateways)) {
			$gateways = $manager->payment_gateways;
		}

		if (!is_array($gateways)) {
			return array();
		}

		return array_filter($gateways, 'is_object');
	} catch (Throwable $error) {
		vms_ticket_integrity_log_event(
			'payment_gateway_health_error',
			__('Payment gateway health could not load WooCommerce gateways.', 'backstage-venue-manager'),
			array(
				'error' => $error->getMessage(),
			)
		);
		return array();
	}
}

function vms_ticket_integrity_checkout_gateway_snapshot(array $gateways): array
{
	$enabled_gateways = array();
	foreach ($gateways as $gateway) {
		$descriptor = vms_ticket_integrity_payment_gateway_descriptor($gateway);
		if (!empty($descriptor['enabled'])) {
			$enabled_gateways[$descriptor['id']] = $descriptor;
		}
	}

	$available_gateways = array();
	$errors = array();
	$warning = '';
	$source = 'none';
	$checkout_list_obtained = false;

	if (function_exists('WC') && class_exists('WooCommerce')) {
		try {
			$woocommerce = WC();
			if (is_object($woocommerce) && method_exists($woocommerce, 'payment_gateways')) {
				$manager = $woocommerce->payment_gateways();
				if (is_object($manager) && method_exists($manager, 'get_available_payment_gateways')) {
					$raw_available = $manager->get_available_payment_gateways();
					$checkout_list_obtained = true;
					$source = 'woocommerce_available_payment_gateways';
					foreach ((array) $raw_available as $gateway) {
						if (!is_object($gateway)) {
							continue;
						}
						$descriptor = vms_ticket_integrity_payment_gateway_descriptor($gateway);
						$available_gateways[$descriptor['id']] = $descriptor;
					}
				}
			}
		} catch (Throwable $error) {
			$errors[] = sanitize_text_field($error->getMessage());
		}
	}

	if (!$checkout_list_obtained && !empty($enabled_gateways)) {
		$available_gateways = $enabled_gateways;
		$source = 'enabled_gateway_fallback';
		$warning = __('WooCommerce did not return a live checkout gateway list in this context, so checkout availability was inferred from enabled gateway configuration.', 'backstage-venue-manager');
	}

	return array(
		'source' => $source,
		'warning' => $warning,
		'errors' => $errors,
		'direct_list_obtained' => $checkout_list_obtained,
		'enabled_gateways' => array_values($enabled_gateways),
		'enabled_gateway_ids' => vms_ticket_integrity_payment_gateway_ids(array_values($enabled_gateways)),
		'enabled_gateway_titles' => vms_ticket_integrity_payment_gateway_titles(array_values($enabled_gateways)),
		'available_gateways' => array_values($available_gateways),
		'available_gateway_ids' => vms_ticket_integrity_payment_gateway_ids(array_values($available_gateways)),
		'available_gateway_titles' => vms_ticket_integrity_payment_gateway_titles(array_values($available_gateways)),
		'available_count' => count($available_gateways),
	);
}

function vms_ticket_integrity_plugin_active(string $plugin_file): bool
{
	$plugin_file = trim($plugin_file);
	if ($plugin_file === '') {
		return false;
	}

	$active_plugins = array_map('strval', (array) get_option('active_plugins', array()));
	if (in_array($plugin_file, $active_plugins, true)) {
		return true;
	}

	$sitewide = get_site_option('active_sitewide_plugins', array());
	return is_array($sitewide) && isset($sitewide[$plugin_file]);
}

function vms_ticket_integrity_square_gateway_ids(): array
{
	return array('square_credit_card', 'square');
}

function vms_ticket_integrity_square_related_gateway_ids(): array
{
	return array('square_credit_card', 'square', 'square_cash_app_pay', 'gift_cards_pay');
}

function vms_ticket_integrity_square_gateway_object(array $gateways)
{
	foreach ($gateways as $gateway) {
		$descriptor = vms_ticket_integrity_payment_gateway_descriptor($gateway);
		if (in_array($descriptor['id'], vms_ticket_integrity_square_gateway_ids(), true)) {
			return $gateway;
		}
	}

	return null;
}

function vms_ticket_integrity_square_gateway_settings(): array
{
	$legacy = get_option('woocommerce_square_settings', array());
	$current = get_option('woocommerce_square_credit_card_settings', array());

	if (!is_array($legacy)) {
		$legacy = array();
	}
	if (!is_array($current)) {
		$current = array();
	}

	return array_merge($legacy, $current);
}

function vms_ticket_integrity_square_core_settings(): array
{
	$settings = get_option('wc_square_settings', array());
	return is_array($settings) ? $settings : array();
}

function vms_ticket_integrity_payment_gateway_site_environment(): array
{
	$environment = function_exists('wp_get_environment_type') ? sanitize_key((string) wp_get_environment_type()) : 'production';
	if ($environment === '') {
		$environment = 'production';
	}

	$home_url = home_url('/');
	$host = strtolower((string) wp_parse_url($home_url, PHP_URL_HOST));
	$is_live = ($environment === 'production');

	$non_live_tokens = array(
		'localhost',
		'.local',
		'.test',
		'.invalid',
		'.example',
		'.ddev.site',
		'.dev',
		'.staging',
		'staging.',
		'dev.',
		'test.',
	);

	foreach ($non_live_tokens as $token) {
		if ($host !== '' && strpos($host, $token) !== false) {
			$is_live = false;
			break;
		}
	}

	if (in_array($host, array('127.0.0.1', '::1'), true)) {
		$is_live = false;
	}

	return array(
		'wp_environment_type' => $environment,
		'is_live' => $is_live,
		'home_url' => esc_url_raw($home_url),
		'host' => $host,
		'label' => $is_live ? __('Live / Production', 'backstage-venue-manager') : __('Non-production', 'backstage-venue-manager'),
	);
}

function vms_ticket_integrity_square_gateway_snapshot(array $gateways, array $checkout, array $site_environment): array
{
	$plugin_active = function_exists('wc_square') || defined('WC_SQUARE_PLUGIN_VERSION') || vms_ticket_integrity_plugin_active('woocommerce-square/woocommerce-square.php');
	$plugin_version = defined('WC_SQUARE_PLUGIN_VERSION') ? (string) WC_SQUARE_PLUGIN_VERSION : '';
	$gateway = vms_ticket_integrity_square_gateway_object($gateways);
	$gateway_descriptor = $gateway ? vms_ticket_integrity_payment_gateway_descriptor($gateway) : array();
	$gateway_settings = vms_ticket_integrity_square_gateway_settings();
	$core_settings = vms_ticket_integrity_square_core_settings();

	$gateway_enabled = !empty($gateway_descriptor)
		? !empty($gateway_descriptor['enabled'])
		: ('yes' === (string) ($gateway_settings['enabled'] ?? 'no'));

	$environment = 'unknown';
	$has_access_token = false;
	$has_refresh_token = false;
	$location_id_present = false;
	$settings_handler = null;

	if ($plugin_active && function_exists('wc_square')) {
		try {
			$square_plugin = wc_square();
			if (is_object($square_plugin) && method_exists($square_plugin, 'get_settings_handler')) {
				$settings_handler = $square_plugin->get_settings_handler();
			}
		} catch (Throwable $error) {
			unset($error);
			$settings_handler = null;
		}
	}

	if (is_object($settings_handler)) {
		try {
			if (method_exists($settings_handler, 'is_sandbox')) {
				$environment = $settings_handler->is_sandbox() ? 'sandbox' : 'production';
			}
			if (method_exists($settings_handler, 'is_connected')) {
				$has_access_token = (bool) $settings_handler->is_connected();
			}
			if (method_exists($settings_handler, 'get_location_id')) {
				$location_id_present = (trim((string) $settings_handler->get_location_id()) !== '');
			}
			if (method_exists($settings_handler, 'get_refresh_token')) {
				$has_refresh_token = (trim((string) $settings_handler->get_refresh_token()) !== '');
			} else {
				$refresh_tokens = get_option('wc_square_refresh_tokens', array());
				if (is_array($refresh_tokens) && $environment !== 'unknown') {
					$has_refresh_token = !empty($refresh_tokens[$environment]);
				}
			}
		} catch (Throwable $error) {
			vms_ticket_integrity_log_event(
				'payment_gateway_health_error',
				__('Payment gateway health could not inspect the Square settings handler.', 'backstage-venue-manager'),
				array(
					'error' => $error->getMessage(),
				)
			);
		}
	}

	if ($environment === 'unknown') {
		$environment = !empty($core_settings['enable_sandbox']) || (defined('WC_SQUARE_SANDBOX') && WC_SQUARE_SANDBOX) ? 'sandbox' : 'production';
	}

	$environment_key = ($environment === 'sandbox') ? 'sandbox' : 'production';
	$access_tokens = get_option('wc_square_access_tokens', array());
	if (!$has_access_token) {
		if (is_array($access_tokens) && !empty($access_tokens[$environment_key])) {
			$has_access_token = true;
		} elseif (trim((string) get_option('woocommerce_square_merchant_access_token', '')) !== '') {
			$has_access_token = true;
		}
	}

	if (!$has_refresh_token) {
		$refresh_tokens = get_option('wc_square_refresh_tokens', array());
		$has_refresh_token = is_array($refresh_tokens) && !empty($refresh_tokens[$environment_key]);
	}

	if (!$location_id_present) {
		$location_id_present = (trim((string) ($core_settings[$environment_key . '_location_id'] ?? '')) !== '');
	}

	$gateway_ids = array_merge(
		vms_ticket_integrity_square_gateway_ids(),
		array_values(array_intersect(vms_ticket_integrity_square_related_gateway_ids(), (array) ($checkout['enabled_gateway_ids'] ?? array())))
	);
	$available_gateway_ids = array_map('sanitize_key', (array) ($checkout['available_gateway_ids'] ?? array()));
	$enabled_gateway_ids = array_map('sanitize_key', (array) ($checkout['enabled_gateway_ids'] ?? array()));
	$non_square_enabled = array_diff($enabled_gateway_ids, vms_ticket_integrity_square_related_gateway_ids());
	$non_square_available = array_diff($available_gateway_ids, vms_ticket_integrity_square_related_gateway_ids());

	$expected = (
		$gateway_enabled
		|| $has_access_token
		|| $location_id_present
		|| in_array('square_credit_card', $enabled_gateway_ids, true)
		|| in_array('square_credit_card', $available_gateway_ids, true)
		|| in_array('square', $enabled_gateway_ids, true)
		|| in_array('square', $available_gateway_ids, true)
	);

	$available_at_checkout = false;
	foreach ($available_gateway_ids as $gateway_id) {
		if (in_array($gateway_id, $gateway_ids, true)) {
			$available_at_checkout = true;
			break;
		}
	}

	$environment_label = ($environment === 'sandbox')
		? __('Sandbox', 'backstage-venue-manager')
		: (($environment === 'production') ? __('Production', 'backstage-venue-manager') : __('Unknown', 'backstage-venue-manager'));

	return array(
		'plugin_active' => $plugin_active,
		'plugin_version' => $plugin_version,
		'gateway_present' => !empty($gateway_descriptor) || !empty($gateway_settings),
		'gateway_id' => (string) ($gateway_descriptor['id'] ?? ''),
		'gateway_title' => (string) ($gateway_descriptor['title'] ?? __('Square', 'backstage-venue-manager')),
		'gateway_enabled' => $gateway_enabled,
		'connection_present' => ($has_access_token && $location_id_present),
		'authenticated' => $has_access_token,
		'has_access_token' => $has_access_token,
		'has_refresh_token' => $has_refresh_token,
		'has_location_id' => $location_id_present,
		'environment' => $environment,
		'environment_label' => $environment_label,
		'is_live_sandbox' => !empty($site_environment['is_live']) && $environment === 'sandbox',
		'expected' => $expected,
		'available_at_checkout' => $available_at_checkout,
		'non_square_enabled_count' => count($non_square_enabled),
		'non_square_available_count' => count($non_square_available),
		'digital_wallets_enabled' => ('yes' === (string) ($gateway_settings['enable_digital_wallets'] ?? 'yes')),
		'apple_pay_domain_registered' => (string) ($gateway_settings['apple_pay_domain_registered'] ?? ''),
		'apple_pay_domain_registration_attempted' => (string) ($gateway_settings['apple_pay_domain_registration_attempted'] ?? 'no'),
		'apple_pay_merchant_id_present' => (trim((string) get_option('sv_wc_apple_pay_merchant_id', '')) !== ''),
	);
}

function vms_ticket_integrity_square_apple_pay_snapshot(array $square): array
{
	$attempted = ('yes' === (string) ($square['apple_pay_domain_registration_attempted'] ?? 'no'));
	$registered = ('yes' === (string) ($square['apple_pay_domain_registered'] ?? 'no'));
	$enabled = !empty($square['digital_wallets_enabled']);
	$merchant_id_present = !empty($square['apple_pay_merchant_id_present']);

	return array(
		'enabled' => $enabled,
		'registration_attempted' => $attempted,
		'domain_registered' => $registered,
		'merchant_id_present' => $merchant_id_present,
		'failed' => ($enabled && $attempted && !$registered),
	);
}

function vms_ticket_integrity_payment_gateway_non_ok_checks(array $checks): array
{
	$out = array();
	foreach ($checks as $check) {
		if (!is_array($check)) {
			continue;
		}
		$status = sanitize_key((string) ($check['status'] ?? 'unknown'));
		if (!in_array($status, array('warning', 'critical'), true)) {
			continue;
		}
		$out[] = $check;
	}

	return $out;
}

function vms_ticket_integrity_payment_gateway_summarize_failures(array $checks): string
{
	$messages = array();
	foreach (vms_ticket_integrity_payment_gateway_non_ok_checks($checks) as $check) {
		$message = trim((string) ($check['message'] ?? ''));
		if ($message === '') {
			continue;
		}
		$messages[] = $message;
		if (count($messages) >= 2) {
			break;
		}
	}

	if (empty($messages)) {
		return '';
	}

	return implode(' ', $messages);
}

function vms_ticket_integrity_payment_gateway_notice_defaults(): array
{
	return array(
		'active' => false,
		'status' => 'ok',
		'message' => '',
		'diagnostic_message' => '',
		'first_detected_failure_gmt' => 0,
		'updated_at_gmt' => 0,
		'failed_checks' => array(),
	);
}

function vms_ticket_integrity_get_payment_gateway_notice(): array
{
	$notice = get_option(vms_ticket_integrity_payment_gateway_notice_option_key(), array());
	if (!is_array($notice)) {
		$notice = array();
	}

	return array_merge(vms_ticket_integrity_payment_gateway_notice_defaults(), $notice);
}

function vms_ticket_integrity_update_payment_gateway_notice(array $notice): void
{
	$payload = array_merge(vms_ticket_integrity_payment_gateway_notice_defaults(), $notice);
	update_option(vms_ticket_integrity_payment_gateway_notice_option_key(), $payload, false);
}

function vms_ticket_integrity_store_payment_gateway_health(array $health): array
{
	$defaults = vms_ticket_integrity_payment_gateway_health_defaults();
	$previous = get_option(vms_ticket_integrity_payment_gateway_health_option_key(), array());
	if (!is_array($previous)) {
		$previous = array();
	}

	$health = array_merge($defaults, $health);
	$now = absint($health['last_checked_gmt'] ?? time());
	if ($now <= 0) {
		$now = time();
	}

	$current_status = sanitize_key((string) ($health['status'] ?? 'unknown'));
	$previous_status = sanitize_key((string) ($previous['status'] ?? 'unknown'));
	$non_ok_checks = vms_ticket_integrity_payment_gateway_non_ok_checks((array) ($health['checks'] ?? array()));
	$is_problem = in_array($current_status, array('warning', 'critical'), true);

	$incident = array('active' => false);
	$last_incident = is_array($previous['last_incident'] ?? null) ? $previous['last_incident'] : array();
	$previous_incident = is_array($previous['incident'] ?? null) ? $previous['incident'] : array();

	if ($is_problem) {
		$first_detected = absint($previous_incident['first_detected_failure_gmt'] ?? 0);
		if (empty($previous_incident['active']) || $first_detected <= 0) {
			$first_detected = $now;
		}

		$incident = array(
			'active' => true,
			'status' => $current_status,
			'first_detected_failure_gmt' => $first_detected,
			'first_detected_failure_local' => vms_ticket_integrity_format_datetime($first_detected),
			'last_seen_gmt' => $now,
			'last_seen_local' => vms_ticket_integrity_format_datetime($now),
			'failed_checks' => $non_ok_checks,
			'diagnostic_message' => sanitize_text_field((string) ($health['diagnostic_message'] ?? '')),
			'summary' => sanitize_text_field((string) ($health['summary'] ?? '')),
		);
	} elseif (!empty($previous_incident['active'])) {
		$resolved = $previous_incident;
		$resolved['active'] = false;
		$resolved['resolved_at_gmt'] = $now;
		$resolved['resolved_at_local'] = vms_ticket_integrity_format_datetime($now);
		$last_incident = $resolved;
	}

	$health['incident'] = $incident;
	$health['last_incident'] = $last_incident;
	$health['failed_checks'] = $non_ok_checks;
	$health['warnings'] = array_values(array_filter($non_ok_checks, static function (array $check): bool {
		return (($check['status'] ?? '') === 'warning');
	}));
	$health['status_label'] = vms_ticket_integrity_payment_gateway_status_label($current_status);
	$health['last_checked_local'] = vms_ticket_integrity_format_datetime($now);

	$status_changed = ($current_status !== $previous_status);
	$status_changed_gmt = $status_changed ? $now : absint($previous['status_changed_gmt'] ?? $now);
	$health['status_changed_gmt'] = $status_changed_gmt;
	$health['status_changed_local'] = vms_ticket_integrity_format_datetime($status_changed_gmt);

	update_option(vms_ticket_integrity_payment_gateway_health_option_key(), $health, false);

	$store = function_exists('vms_ticket_integrity_get_results_store') ? vms_ticket_integrity_get_results_store() : array();
	if (!is_array($store)) {
		$store = array();
	}
	$store['payment_gateway_health'] = $health;
	update_option(vms_ticket_integrity_results_option_key(), $store, false);

	if ($current_status === 'critical') {
		vms_ticket_integrity_update_payment_gateway_notice(
			array(
				'active' => true,
				'status' => 'critical',
				'message' => sanitize_text_field((string) ($health['summary'] ?? '')),
				'diagnostic_message' => sanitize_text_field((string) ($health['diagnostic_message'] ?? '')),
				'first_detected_failure_gmt' => absint($incident['first_detected_failure_gmt'] ?? $now),
				'updated_at_gmt' => $now,
				'failed_checks' => $non_ok_checks,
			)
		);
	} else {
		vms_ticket_integrity_update_payment_gateway_notice(
			array(
				'active' => false,
				'status' => $current_status,
				'message' => '',
				'diagnostic_message' => '',
				'first_detected_failure_gmt' => 0,
				'updated_at_gmt' => $now,
				'failed_checks' => array(),
			)
		);
	}

	if ($current_status === 'critical' && $previous_status !== 'critical') {
		if (function_exists('vms_ticket_integrity_log_event')) {
			vms_ticket_integrity_log_event(
				'payment_gateway_health_critical',
				__('Payment gateway health entered a critical state.', 'backstage-venue-manager'),
				array(
					'status' => $current_status,
					'message' => (string) ($health['summary'] ?? ''),
				)
			);
		}
		vms_ticket_integrity_maybe_send_payment_gateway_alert_email($health);
	} elseif ($current_status === 'ok' && $previous_status === 'critical' && function_exists('vms_ticket_integrity_log_event')) {
		vms_ticket_integrity_log_event(
			'payment_gateway_health_recovered',
			__('Payment gateway health recovered to OK.', 'backstage-venue-manager'),
			array(
				'message' => (string) ($health['summary'] ?? ''),
			)
		);
	}

	return $health;
}

function vms_ticket_integrity_get_payment_gateway_health(): array
{
	$health = get_option(vms_ticket_integrity_payment_gateway_health_option_key(), array());
	if (!is_array($health)) {
		$health = array();
	}

	return array_merge(vms_ticket_integrity_payment_gateway_health_defaults(), $health);
}

function vms_ticket_integrity_payment_gateway_alert_recipient(): string
{
	$settings = function_exists('vms_ticket_integrity_get_settings') ? vms_ticket_integrity_get_settings() : array();
	if (empty($settings['email_alerts_enabled'])) {
		return '';
	}

	$recipient = sanitize_email((string) ($settings['alert_recipient'] ?? ''));
	if ($recipient === '') {
		$recipient = sanitize_email((string) get_option('admin_email', ''));
	}

	return $recipient;
}

function vms_ticket_integrity_maybe_send_payment_gateway_alert_email(array $health): void
{
	$recipient = vms_ticket_integrity_payment_gateway_alert_recipient();
	if ($recipient === '') {
		return;
	}

	$site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
	$subject = sprintf('[%s] %s', $site_name, __('Payment Gateway Health CRITICAL', 'backstage-venue-manager'));

	$lines = array();
	$lines[] = __('Payment Gateway Health', 'backstage-venue-manager');
	$lines[] = str_repeat('=', 22);
	$lines[] = sprintf(__('Detected: %s', 'backstage-venue-manager'), vms_ticket_integrity_format_datetime(absint($health['last_checked_gmt'] ?? time())));
	$lines[] = sprintf(__('Status: %s', 'backstage-venue-manager'), vms_ticket_integrity_payment_gateway_status_label((string) ($health['status'] ?? 'critical')));
	$lines[] = '';
	$lines[] = sanitize_text_field((string) ($health['summary'] ?? __('Payment gateway health is critical.', 'backstage-venue-manager')));
	$diagnostic = trim((string) ($health['diagnostic_message'] ?? ''));
	if ($diagnostic !== '') {
		$lines[] = $diagnostic;
	}
	$lines[] = '';
	$lines[] = __('Failed checks:', 'backstage-venue-manager');
	foreach ((array) ($health['failed_checks'] ?? array()) as $check) {
		if (!is_array($check)) {
			continue;
		}
		$lines[] = sprintf(
			'- [%1$s] %2$s',
			vms_ticket_integrity_payment_gateway_status_label((string) ($check['status'] ?? 'critical')),
			(string) ($check['message'] ?? '')
		);
	}
	$lines[] = '';
	$lines[] = sprintf(__('Review the full monitor: %s', 'backstage-venue-manager'), vms_ticket_integrity_admin_url());

	$body_text = implode("\n", $lines);
	$result = function_exists('vms_notify_provider_core_email_send')
		? (array) vms_notify_provider_core_email_send(
			array(
				'to' => $recipient,
				'subject' => $subject,
				'body_text' => $body_text,
			)
		)
		: array(
			'success' => wp_mail($recipient, $subject, $body_text),
			'provider_message_id' => null,
			'error_message' => '',
		);

	if (function_exists('vms_notify_insert_log')) {
		vms_notify_insert_log(
			array(
				'source' => 'ticket_integrity',
				'event_key' => 'payment_gateway_health_critical',
				'recipient_user_id' => 0,
				'recipient_address' => $recipient,
				'channel' => 'email',
				'locale' => get_locale(),
				'template_key' => 'ticket_integrity.payment_gateway_health_critical',
				'payload' => array(
					'status' => (string) ($health['status'] ?? 'critical'),
					'last_checked_gmt' => absint($health['last_checked_gmt'] ?? time()),
				),
				'provider' => 'core_email',
				'provider_message_id' => (string) ($result['provider_message_id'] ?? ''),
				'status' => !empty($result['success']) ? 'sent' : 'failed',
				'error_message' => (string) ($result['error_message'] ?? ''),
			)
		);
	}

	if (function_exists('vms_ticket_integrity_log_event')) {
		vms_ticket_integrity_log_event(
			!empty($result['success']) ? 'payment_gateway_health_email_sent' : 'payment_gateway_health_email_failed',
			!empty($result['success']) ? __('Payment gateway health critical email sent.', 'backstage-venue-manager') : __('Payment gateway health critical email failed to send.', 'backstage-venue-manager'),
			array(
				'recipient' => $recipient,
				'status' => (string) ($health['status'] ?? 'critical'),
			)
		);
	}
}

function vms_ticket_integrity_run_payment_gateway_health_check(array $args = array()): array
{
	$trigger = sanitize_key((string) ($args['trigger'] ?? 'manual'));
	$persist = !isset($args['persist']) || (bool) $args['persist'];
	$now = time();
	$site_environment = vms_ticket_integrity_payment_gateway_site_environment();
	$gateways = vms_ticket_integrity_payment_gateway_objects();
	$checkout = vms_ticket_integrity_checkout_gateway_snapshot($gateways);
	$square = vms_ticket_integrity_square_gateway_snapshot($gateways, $checkout, $site_environment);
	$apple_pay = vms_ticket_integrity_square_apple_pay_snapshot($square);

	$checks = array();

	if (empty($checkout['available_count'])) {
		$checks[] = vms_ticket_integrity_payment_gateway_check(
			'woocommerce_payment_methods',
			__('WooCommerce payment methods available', 'backstage-venue-manager'),
			'critical',
			__('No payment methods are currently available at checkout.', 'backstage-venue-manager')
		);
	} else {
		$checks[] = vms_ticket_integrity_payment_gateway_check(
			'woocommerce_payment_methods',
			__('WooCommerce payment methods available', 'backstage-venue-manager'),
			'ok',
			sprintf(
				/* translators: 1: count, 2: gateway titles */
				__('Checkout currently has %1$d payment method(s): %2$s.', 'backstage-venue-manager'),
				absint($checkout['available_count']),
				implode(', ', array_slice((array) ($checkout['available_gateway_titles'] ?? array()), 0, 5))
			)
		);
	}

	$square_gateway_status = 'ok';
	$square_gateway_message = __('WooCommerce Square is active and the card gateway is enabled.', 'backstage-venue-manager');
	if (!empty($square['expected']) && empty($square['plugin_active'])) {
		$square_gateway_status = 'critical';
		$square_gateway_message = __('WooCommerce Square appears to be expected on this site, but the plugin is not active.', 'backstage-venue-manager');
	} elseif (!empty($square['plugin_active']) && !$square['gateway_enabled']) {
		if (absint($checkout['available_count'] ?? 0) === 0) {
			$square_gateway_status = 'critical';
			$square_gateway_message = __('Square is disabled and checkout does not currently have any fallback payment methods.', 'backstage-venue-manager');
		} elseif (!empty($square['expected'])) {
			$square_gateway_status = 'warning';
			$square_gateway_message = __('Square is disabled, but other payment methods are still configured.', 'backstage-venue-manager');
		} else {
			$square_gateway_message = __('Square is installed but its card gateway is disabled.', 'backstage-venue-manager');
		}
	} elseif (empty($square['plugin_active'])) {
		$square_gateway_message = __('WooCommerce Square is not active on this site.', 'backstage-venue-manager');
	}
	$checks[] = vms_ticket_integrity_payment_gateway_check(
		'square_gateway_status',
		__('Square gateway status', 'backstage-venue-manager'),
		$square_gateway_status,
		$square_gateway_message
	);

	$square_connection_status = 'ok';
	$square_connection_message = __('Square is connected, authenticated, and using Production.', 'backstage-venue-manager');
	if (!empty($square['expected']) && (!$square['authenticated'] || !$square['has_location_id'] || !$square['connection_present'])) {
		$square_connection_status = 'critical';
		$square_connection_message = __('Square appears disconnected or missing required authentication/location data.', 'backstage-venue-manager');
	} elseif (!empty($square['is_live_sandbox'])) {
		$square_connection_status = 'critical';
		$square_connection_message = __('This live site is using Square Sandbox instead of Production.', 'backstage-venue-manager');
	} elseif (!empty($square['gateway_enabled']) && empty($square['available_at_checkout']) && !empty($checkout['available_count'])) {
		$square_connection_status = 'warning';
		$square_connection_message = __('Square is enabled but is not currently one of the checkout payment methods being offered.', 'backstage-venue-manager');
	} elseif (($square['environment'] ?? '') === 'sandbox') {
		$square_connection_message = __('Square is connected and using Sandbox on a non-production environment.', 'backstage-venue-manager');
	}
	$checks[] = vms_ticket_integrity_payment_gateway_check(
		'square_connection_health',
		__('Square connection health', 'backstage-venue-manager'),
		$square_connection_status,
		$square_connection_message
	);

	$apple_pay_status = 'ok';
	$apple_pay_message = __('Apple Pay domain registration is healthy or not enabled.', 'backstage-venue-manager');
	if (!empty($apple_pay['failed']) && !empty($checkout['available_count'])) {
		$apple_pay_status = 'warning';
		$apple_pay_message = __('Apple Pay domain registration failed, but regular checkout payment methods are still available.', 'backstage-venue-manager');
	} elseif (!empty($apple_pay['failed'])) {
		$apple_pay_status = 'warning';
		$apple_pay_message = __('Apple Pay domain registration failed.', 'backstage-venue-manager');
	} elseif (!empty($apple_pay['enabled']) && empty($apple_pay['domain_registered']) && empty($apple_pay['registration_attempted'])) {
		$apple_pay_message = __('Apple Pay is enabled, but the domain registration has not been attempted yet.', 'backstage-venue-manager');
	}
	$checks[] = vms_ticket_integrity_payment_gateway_check(
		'apple_pay_registration',
		__('Apple Pay registration', 'backstage-venue-manager'),
		$apple_pay_status,
		$apple_pay_message
	);

	$overall_status = 'ok';
	foreach ($checks as $check) {
		$status = sanitize_key((string) ($check['status'] ?? 'unknown'));
		if (vms_ticket_integrity_payment_gateway_status_rank($status) > vms_ticket_integrity_payment_gateway_status_rank($overall_status)) {
			$overall_status = $status;
		}
	}

	$summary = '';
	$diagnostic_message = '';
	if ($overall_status === 'critical') {
		$summary = vms_ticket_integrity_payment_gateway_summarize_failures($checks);
		if ($summary === '') {
			$summary = __('Payment gateway health is in a critical state.', 'backstage-venue-manager');
		}
		$diagnostic_message = __('Investigate the Square connection, enabled payment gateways, and checkout payment method availability immediately.', 'backstage-venue-manager');
	} elseif ($overall_status === 'warning') {
		$summary = vms_ticket_integrity_payment_gateway_summarize_failures($checks);
		if ($summary === '') {
			$summary = __('Payment gateway health has warnings.', 'backstage-venue-manager');
		}
		$diagnostic_message = __('Checkout can still take payment, but one or more payment gateway checks need attention.', 'backstage-venue-manager');
	} else {
		$summary = __('Checkout has at least one available payment method and Square is connected.', 'backstage-venue-manager');
		$diagnostic_message = __('Square is connected, enabled, and the checkout payment gateway list is healthy.', 'backstage-venue-manager');
	}

	if (!empty($checkout['warning'])) {
		$diagnostic_message .= ' ' . sanitize_text_field((string) $checkout['warning']);
	}

	$health = array(
		'version' => 1,
		'status' => $overall_status,
		'status_label' => vms_ticket_integrity_payment_gateway_status_label($overall_status),
		'summary' => $summary,
		'diagnostic_message' => $diagnostic_message,
		'last_checked_gmt' => $now,
		'last_checked_local' => vms_ticket_integrity_format_datetime($now),
		'trigger' => $trigger,
		'site_environment' => $site_environment,
		'checkout' => $checkout,
		'square' => $square,
		'apple_pay' => $apple_pay,
		'checks' => $checks,
	);

	return $persist ? vms_ticket_integrity_store_payment_gateway_health($health) : $health;
}

function vms_ticket_integrity_prepare_payment_gateway_health(string $trigger = 'report', int $stale_after = 0): array
{
	$health = vms_ticket_integrity_get_payment_gateway_health();
	$last_checked = absint($health['last_checked_gmt'] ?? 0);
	$stale_after = ($stale_after > 0) ? $stale_after : (30 * MINUTE_IN_SECONDS);
	$is_stale = ($last_checked <= 0 || ($last_checked < (time() - $stale_after)));

	if ($is_stale) {
		$health = vms_ticket_integrity_run_payment_gateway_health_check(
			array(
				'trigger' => sanitize_key($trigger),
				'persist' => true,
			)
		);
	}

	return $health;
}

function vms_ticket_integrity_payment_gateway_menu_alert_needed(): bool
{
	$health = vms_ticket_integrity_get_payment_gateway_health();
	return (($health['status'] ?? '') === 'critical');
}

function vms_ticket_integrity_render_payment_gateway_admin_notice(): void
{
	if (!current_user_can('manage_options')) {
		return;
	}

	$notice = vms_ticket_integrity_get_payment_gateway_notice();
	if (empty($notice['active']) || ($notice['status'] ?? '') !== 'critical') {
		return;
	}

	$message = trim((string) ($notice['message'] ?? ''));
	if ($message === '') {
		$message = __('Payment gateway health is critical.', 'backstage-venue-manager');
	}

	$first_detected = absint($notice['first_detected_failure_gmt'] ?? 0);

	echo '<div class="notice notice-error">';
	echo '<p><strong>' . esc_html__('Payment Gateway Health:', 'backstage-venue-manager') . '</strong> ' . esc_html($message) . '</p>';
	if ($first_detected > 0) {
		echo '<p>' . esc_html(sprintf(__('First detected: %s', 'backstage-venue-manager'), vms_ticket_integrity_format_datetime($first_detected))) . '</p>';
	}
	echo '<p><a class="button button-secondary" href="' . esc_url(vms_ticket_integrity_admin_url()) . '">' . esc_html__('Open Ticket Integrity', 'backstage-venue-manager') . '</a></p>';
	echo '</div>';
}
add_action('admin_notices', 'vms_ticket_integrity_render_payment_gateway_admin_notice', 18);
