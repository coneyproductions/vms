<?php
defined('ABSPATH') || exit;

require_once __DIR__ . '/defaults.php';
require_once __DIR__ . '/cpt.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/admin-ui.php';
require_once __DIR__ . '/front.php';

if (!function_exists('bvmgr_status_notices_module_boot')) {
	function bvmgr_status_notices_module_boot(): void
	{
		if (function_exists('bvmgr_register_module')) {
			bvmgr_register_module(array(
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
add_action('plugins_loaded', 'bvmgr_status_notices_module_boot', 8);
