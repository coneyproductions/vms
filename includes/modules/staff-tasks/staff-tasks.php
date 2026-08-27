<?php
defined('ABSPATH') || exit;

require_once __DIR__ . '/caps.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/generator.php';
require_once __DIR__ . '/notifications.php';

if (is_admin()) {
	require_once __DIR__ . '/tours.php';
	require_once __DIR__ . '/admin-ui.php';
}

if (!function_exists('vms_staff_tasks_module_boot')) {
	function vms_staff_tasks_module_boot(): void
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

		vms_tasks_ensure_capability_mapping();
		vms_tasks_maybe_upgrade_schema();
	}
}
add_action('plugins_loaded', 'vms_staff_tasks_module_boot', 8);
