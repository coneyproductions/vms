<?php
defined('ABSPATH') || exit;

require_once __DIR__ . '/caps.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/authority.php';
require_once __DIR__ . '/event-authority.php';
require_once __DIR__ . '/generator.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/delivery.php';

if (is_admin()) {
	require_once __DIR__ . '/tours.php';
	require_once __DIR__ . '/admin-ui.php';
    require_once __DIR__ . '/authority-ui.php';
}

if (!function_exists('bvmgr_staff_tasks_module_boot')) {
	function bvmgr_staff_tasks_module_boot(): void
	{
		if (function_exists('bvmgr_register_module')) {
			bvmgr_register_module(array(
				'slug' => 'staff_tasks',
				'name' => 'Staff Tasks',
				'version' => '1.2.0',
				'premium' => false,
				'description' => 'Task templates, checklist generation, assignment resolution, and task completion tracking for event operations.',
				'source' => 'core',
			));
		}

		// Schema, role capabilities and cron are initialized by explicit administrator POST only.
	}
}
add_action('plugins_loaded', 'bvmgr_staff_tasks_module_boot', 8);
