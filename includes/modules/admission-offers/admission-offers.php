<?php
defined('ABSPATH') || exit;

require_once __DIR__ . '/contracts.php';
require_once __DIR__ . '/domain.php';
require_once __DIR__ . '/keys.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/repositories.php';
require_once __DIR__ . '/capacity.php';
require_once __DIR__ . '/eligibility.php';
require_once __DIR__ . '/native-complimentary.php';
require_once __DIR__ . '/claim-service.php';
require_once __DIR__ . '/paid-claim-service.php';
require_once __DIR__ . '/lifecycle.php';

if (!function_exists('bvmgr_admission_offers_foundation_boot')) {
	function bvmgr_admission_offers_foundation_boot(): void
	{
		bvmgr_admission_offers_maybe_upgrade_schema();
	}
}

// Phase B/C1 expose internal provider-facing services and observe the native
// BVM admission lifecycle. They intentionally register no public route, admin
// menu, cart/payment observer, campaign surface, or distribution adapter.
add_action('plugins_loaded', 'bvmgr_admission_offers_foundation_boot', 8);
