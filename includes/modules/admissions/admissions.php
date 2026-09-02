<?php
defined('ABSPATH') || exit;

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/caps.php';
require_once __DIR__ . '/normalize.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/admission-tokens.php';
require_once __DIR__ . '/vendor-guest-portal.php';
require_once __DIR__ . '/rest.php';
require_once __DIR__ . '/admin-ui.php';
require_once __DIR__ . '/shortcodes.php';
require_once __DIR__ . '/pass-claims.php';

if (!function_exists('bvmgr_admission_module_boot')) {
	function bvmgr_admission_module_boot(): void
	{
		if (function_exists('bvmgr_register_module')) {
			bvmgr_register_module(array(
				'slug' => 'admissions',
				'name' => 'Guest List / Comp Admission',
				'version' => '1.0.0',
				'premium' => false,
				'description' => 'Event-plan guest list and door check-in module.',
				'source' => 'core',
			));
		}

		bvmgr_admission_ensure_capability_mapping();
		bvmgr_admission_maybe_upgrade_schema();
	}
}
add_action('plugins_loaded', 'bvmgr_admission_module_boot', 8);
