<?php
defined('ABSPATH') || exit;

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/public.php';

if (is_admin()) {
	require_once __DIR__ . '/admin-ui.php';
}

if (!function_exists('vms_add_dispatch_module_boot')) {
	function vms_add_dispatch_module_boot(): void
	{
		if (function_exists('vms_register_module')) {
			vms_register_module(array(
				'slug' => 'availability_date_dispatch',
				'name' => 'ADD - Availability & Date Dispatch',
				'version' => defined('VMS_VERSION') ? (string) VMS_VERSION : '0.2.24.454',
				'premium' => false,
				'description' => 'Single-event availability dispatch for missing vendor assignments, secure vendor responses, and Event Plan assignment follow-up.',
				'source' => 'core',
			));
		}

		vms_add_dispatch_maybe_upgrade_schema();
	}
}
add_action('plugins_loaded', 'vms_add_dispatch_module_boot', 8);
