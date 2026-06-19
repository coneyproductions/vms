<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_core_module_toggle_enabled')) {
	function vms_core_module_toggle_enabled(string $slug): bool
	{
		$slug = sanitize_key($slug);
		if ($slug === '') {
			return false;
		}

		$settings = (array) get_option('vms_settings', array());
		$key_map = array(
			'availability_date_dispatch' => 'availability_date_dispatch_enabled',
		);

		if (!isset($key_map[$slug])) {
			return true;
		}

		$key = (string) $key_map[$slug];
		if (!array_key_exists($key, $settings)) {
			return true;
		}

		return !empty($settings[$key]);
	}
}

if (!function_exists('vms_module_registry')) {
	function &vms_module_registry(): array
	{
		static $registry = array();
		return $registry;
	}
}

if (!function_exists('vms_register_module')) {
	function vms_register_module(array $module): bool
	{
		$slug = sanitize_key((string) ($module['slug'] ?? ''));
		if ($slug === '') {
			return false;
		}

		$normalized = array(
			'slug' => $slug,
			'name' => sanitize_text_field((string) ($module['name'] ?? $slug)),
			'version' => sanitize_text_field((string) ($module['version'] ?? '')),
			'premium' => !empty($module['premium']),
			'description' => sanitize_text_field((string) ($module['description'] ?? '')),
			'source' => sanitize_text_field((string) ($module['source'] ?? '')),
		);

		$registry = &vms_module_registry();
		$registry[$slug] = $normalized;
		return true;
	}
}

if (!function_exists('vms_get_registered_modules')) {
	function vms_get_registered_modules(): array
	{
		$registry = vms_module_registry();
		ksort($registry);
		return $registry;
	}
}

if (!function_exists('vms_get_registered_module')) {
	function vms_get_registered_module(string $slug): ?array
	{
		$slug = sanitize_key($slug);
		$registry = vms_module_registry();
		return isset($registry[$slug]) ? $registry[$slug] : null;
	}
}

if (!function_exists('vms_module_is_registered')) {
	function vms_module_is_registered(string $slug): bool
	{
		return vms_get_registered_module($slug) !== null;
	}
}

if (!function_exists('vms_module_is_licensed')) {
	function vms_module_is_licensed(string $slug): bool
	{
		$module = vms_get_registered_module($slug);
		if (!$module || empty($module['premium'])) {
			return true;
		}

		$licensed_slugs = array_map('sanitize_key', (array) get_option('vms_premium_modules_enabled', array()));
		$licensed = in_array($slug, $licensed_slugs, true);

		/**
		 * Filter premium module licensing status.
		 *
		 * @param bool  $licensed Current computed status.
		 * @param string $slug    Module slug.
		 * @param array $module   Registered module metadata.
		 */
		return (bool) apply_filters('vms_premium_module_licensed', $licensed, $slug, $module);
	}
}

if (!function_exists('vms_module_is_enabled')) {
	function vms_module_is_enabled(string $slug): bool
	{
		$slug = sanitize_key($slug);
		$module = vms_get_registered_module($slug);
		if (!$module) {
			// Fail closed until the module has registered itself with VMS.
			return false;
		}
		$enabled = vms_module_is_licensed($slug);

		/**
		 * Filter module enablement (premium and non-premium).
		 *
		 * @param bool  $enabled Computed enable state.
		 * @param string $slug   Module slug.
		 * @param array|null $module Module metadata, if registered.
		 */
		return (bool) apply_filters('vms_module_enabled', $enabled, $slug, $module);
	}
}

function vms_load_modules(): void {
	require_once __DIR__ . '/admissions/admissions.php';
	require_once __DIR__ . '/status-notices/status-notices.php';
	require_once __DIR__ . '/staff-tasks/staff-tasks.php';
	require_once __DIR__ . '/email-followups/email-followups.php';
	$add_dispatch_bootstrap = __DIR__ . '/availability-date-dispatch/availability-date-dispatch.php';
	if (file_exists($add_dispatch_bootstrap) && vms_core_module_toggle_enabled('availability_date_dispatch')) {
		require_once $add_dispatch_bootstrap;
	}
	do_action('vms_modules_loaded');
}
