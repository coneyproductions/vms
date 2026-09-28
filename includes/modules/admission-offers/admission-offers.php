<?php
defined('ABSPATH') || exit;

require_once __DIR__ . '/contracts.php';
require_once __DIR__ . '/domain.php';
require_once __DIR__ . '/keys.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/repositories.php';
require_once __DIR__ . '/capacity.php';

if (!function_exists('bvmgr_admission_offers_foundation_boot')) {
	function bvmgr_admission_offers_foundation_boot(): void
	{
		bvmgr_admission_offers_maybe_upgrade_schema();
	}
}

// Phase A is intentionally dormant: schema readiness only. No public route,
// admin menu, cart hook, payment observer, ticket hook, or provider is registered.
add_action('plugins_loaded', 'bvmgr_admission_offers_foundation_boot', 8);
