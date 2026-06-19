<?php
defined('ABSPATH') || exit;

require_once __DIR__ . '/defaults.php';
require_once __DIR__ . '/cpt.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/admin-ui.php';
require_once __DIR__ . '/front.php';

if (!function_exists('vms_status_notices_module_boot')) {
	function vms_status_notices_module_boot(): void
	{
		if (function_exists('vms_register_module')) {
			vms_register_module(array(
				'slug' => 'status_notices',
				'name' => 'Status Notices',
				'version' => '1.0.0',
				'premium' => false,
				'description' => 'Targeted front/admin status notices with browser and device-aware delivery.',
				'source' => 'core',
			));
		}
	}
}
add_action('plugins_loaded', 'vms_status_notices_module_boot', 8);
