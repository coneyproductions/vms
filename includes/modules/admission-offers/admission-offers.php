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
require_once __DIR__ . '/woo-checkout-service.php';
require_once __DIR__ . '/woo-cart-adapter.php';
require_once __DIR__ . '/lifecycle.php';

if (!function_exists('bvmgr_admission_offers_foundation_boot')) {
	function bvmgr_admission_offers_foundation_boot(): void
	{
		bvmgr_admission_offers_maybe_upgrade_schema();
	}
}

// Phase C2 adds only Woo session/cart validation and an explicit fail-closed
// checkout barrier. It registers no public activation route, pricing mutation,
// order/payment hook, credential path, campaign surface, or scheduler.
add_action('plugins_loaded', 'bvmgr_admission_offers_foundation_boot', 8);
