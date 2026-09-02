<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap-wordpress.php';
vms_tests_require_wordpress(__DIR__);

if (!class_exists('BVMGR_Admin_Event_Plans')) {
	require_once dirname(__DIR__) . '/backstage-venue-manager.php';
}

$assert = static function (bool $condition, string $message): void {
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
};

$createdPosts = array();
$createdTerms = array();
$originalPost = $_POST ?? array();
$originalGet = $_GET ?? array();
$originalRequest = $_REQUEST ?? array();

$registerPost = static function (int $postId) use (&$createdPosts): int {
	$createdPosts[] = $postId;
	return $postId;
};

$registerTerm = static function (array $termRow) use (&$createdTerms): array {
	$createdTerms[] = $termRow;
	return $termRow;
};

$cleanup = static function () use (&$createdPosts, &$createdTerms, &$originalPost, &$originalGet, &$originalRequest): void {
	foreach (array_reverse($createdPosts) as $postId) {
		wp_delete_post((int) $postId, true);
	}
	foreach (array_reverse($createdTerms) as $termRow) {
		if (!empty($termRow['created']) && !empty($termRow['term_id'])) {
			wp_delete_term((int) $termRow['term_id'], 'vms_vendor_type');
		}
	}

	$_POST = $originalPost;
	$_GET = $originalGet;
	$_REQUEST = $originalRequest;
};

try {
	wp_set_current_user(1);

	$ensureVendorType = static function (string $slug, string $label) use ($registerTerm): string {
		$existing = get_term_by('slug', $slug, 'vms_vendor_type');
		if ($existing instanceof WP_Term) {
			return (string) $existing->slug;
		}

		$created = wp_insert_term($label, 'vms_vendor_type', array('slug' => $slug));
		if (is_wp_error($created) || empty($created['term_id'])) {
			throw new RuntimeException('Failed to create vendor type term: ' . $slug);
		}

		$registerTerm(array(
			'term_id' => (int) $created['term_id'],
			'created' => true,
		));

		return $slug;
	};

	$createVendor = static function (string $title, array $typeSlugs = array()) use ($registerPost): int {
		$vendorId = wp_insert_post(array(
			'post_type' => 'vms_vendor',
			'post_status' => 'publish',
			'post_title' => $title,
		), true);
		if (is_wp_error($vendorId) || (int) $vendorId <= 0) {
			throw new RuntimeException('Failed to create vendor: ' . $title);
		}

		$vendorId = $registerPost((int) $vendorId);
		if (!empty($typeSlugs)) {
			wp_set_object_terms($vendorId, array_values($typeSlugs), 'vms_vendor_type', false);
		}

		return $vendorId;
	};

	$createPlan = static function (string $title) use ($registerPost): int {
		$planId = wp_insert_post(array(
			'post_type' => 'vms_event_plan',
			'post_status' => 'publish',
			'post_title' => $title,
		), true);
		if (is_wp_error($planId) || (int) $planId <= 0) {
			throw new RuntimeException('Failed to create Event Plan: ' . $title);
		}

		return $registerPost((int) $planId);
	};

	$runSave = static function (int $planId, array $overrides = array()): void {
		$GLOBALS['bvmgr_event_plan_request_cache_generation'] = max(0, (int) ($GLOBALS['bvmgr_event_plan_request_cache_generation'] ?? 0)) + 1;
		$_POST = array_merge(array(
			'bvmgr_event_plan_details_nonce' => wp_create_nonce('bvmgr_save_event_plan_details'),
			'post_ID' => $planId,
			'original_post_status' => 'publish',
			'vms_event_plan_action' => 'save_draft',
			'vms_staffing_lazy_unloaded' => '1',
			'vms_secondary_vendors_lazy_unloaded' => '1',
			'vms_secondary_vendors_module_detached' => '1',
		), $overrides);
		$_GET = array();
		$_REQUEST = $_POST;

		$reflection = new ReflectionClass('BVMGR_Admin_Event_Plans');
		/** @var BVMGR_Admin_Event_Plans $admin */
		$admin = $reflection->newInstanceWithoutConstructor();
		$admin->save_event_plan_meta($planId, get_post($planId));
		clean_post_cache($planId);
	};

	$getSecondaryPayload = static function (int $planId): array {
		$reflection = new ReflectionClass('BVMGR_Admin_Event_Plans');
		/** @var BVMGR_Admin_Event_Plans $admin */
		$admin = $reflection->newInstanceWithoutConstructor();
		$method = $reflection->getMethod('get_event_plan_secondary_vendors_module_payload');
		$method->setAccessible(true);
		return (array) $method->invoke($admin, $planId);
	};

	foreach (array(
		'band' => 'Music Vendor',
		'food_truck' => 'Food Vendor',
		'dessert_truck' => 'Dessert Vendor',
		'market_vendor' => 'Market Vendor',
	) as $slug => $label) {
		$ensureVendorType($slug, $label);
	}

	$primaryVendorId = $createVendor('Module Reopen Primary Vendor', array('band'));
	$foodVendorId = $createVendor('Module Reopen Food Vendor', array('food_truck'));
	$dessertVendorId = $createVendor('Module Reopen Dessert Vendor', array('dessert_truck'));
	$marketVendorIds = array();
	for ($i = 1; $i <= 12; $i++) {
		$marketVendorIds[] = $createVendor('Module Reopen Market Vendor ' . $i, array('market_vendor'));
	}

	$planId = $createPlan('Event Plan Module Reopen Regression');
	update_post_meta($planId, '_vms_event_plan_status', 'draft');
	update_post_meta($planId, '_vms_event_date', '2026-07-11');
	update_post_meta($planId, '_vms_start_time', '18:00');
	update_post_meta($planId, '_vms_end_time', '22:00');
	update_post_meta($planId, '_vms_venue_id', 0);
	update_post_meta($planId, '_vms_band_vendor_id', $primaryVendorId);

	$assignmentMetaKey = function_exists('bvmgr_event_plan_secondary_vendor_assignment_meta_key')
		? (string) bvmgr_event_plan_secondary_vendor_assignment_meta_key()
		: '_vms_secondary_vendor_assignments_v1';

	$saveResult = function_exists('bvmgr_event_plan_save_secondary_vendors_module')
		? bvmgr_event_plan_save_secondary_vendors_module($planId, array(
			'vms_secondary_vendor_assignments' => array(
				array(
					'type_slug' => 'food_truck',
					'mode' => 'standard',
					'slot_limit' => '1',
					'vendor_ids' => array($foodVendorId),
				),
				array(
					'type_slug' => 'dessert_truck',
					'mode' => 'standard',
					'slot_limit' => '1',
					'vendor_ids' => array($dessertVendorId),
				),
				array(
					'type_slug' => 'market_vendor',
					'mode' => 'market',
					'slot_limit' => '10',
					'needed_slots' => '12',
					'open_for_dispatch' => '1',
					'allow_over_capacity' => '1',
					'vendor_ids' => $marketVendorIds,
				),
			),
		))
		: new WP_Error('missing_secondary_save_helper', 'Secondary vendor save helper is unavailable.');
	$assert(!is_wp_error($saveResult), 'Failed to seed Additional Vendors market group.');

	$expectedFlatIds = array_merge(array($foodVendorId, $dessertVendorId), $marketVendorIds);
	sort($expectedFlatIds);
	$flatIdsAfterModuleSave = array_map('absint', (array) get_post_meta($planId, '_vms_secondary_vendor_ids', true));
	sort($flatIdsAfterModuleSave);
	$indexIdsAfterModuleSave = array_map('absint', (array) get_post_meta($planId, '_vms_secondary_vendor_id', false));
	sort($indexIdsAfterModuleSave);
	$assert($flatIdsAfterModuleSave === $expectedFlatIds, 'Additional Vendors module save should keep flat compatibility secondary vendor ids in sync.');
	$assert($indexIdsAfterModuleSave === $expectedFlatIds, 'Additional Vendors module save should keep repeated secondary vendor index rows in sync.');

	$secondaryBefore = get_post_meta($planId, $assignmentMetaKey, true);
	$assert((int) ($secondaryBefore['market_vendor']['needed_slots'] ?? 0) === 12, 'Seeded Market Vendor group should persist needed_slots in canonical assignment meta.');
	$assert(!empty($secondaryBefore['market_vendor']['open_for_dispatch']), 'Seeded Market Vendor group should persist open_for_dispatch in canonical assignment meta.');
	$assert(!empty($secondaryBefore['market_vendor']['allow_over_capacity']), 'Seeded Market Vendor group should persist allow_over_capacity in canonical assignment meta.');
	$payload = $getSecondaryPayload($planId);
	$html = (string) ($payload['html'] ?? '');
	$assert($html !== '', 'Expected Additional Vendors module HTML to render.');
	$assert(strpos($html, 'vms-secondary-vendor-group__field--summary') !== false, 'Additional Vendors groups should render a compact summary field in the header row.');
	$assert(strpos($html, 'vms-secondary-vendor-group__field--market-target') !== false, 'Market Vendor groups should render the target field in the compact header row.');
	$assert(strpos($html, 'Market target / needed vendors') !== false, 'Market Vendor groups should label the target/needed-slots control.');
	$assert(strpos($html, 'Show this market need in ADD') !== false, 'Market Vendor groups should expose the ADD visibility checkbox.');
	$assert(strpos($html, 'vms-secondary-vendor-group__rows-toolbar') !== false, 'Additional Vendors groups should render a compact selected-vendors toolbar.');
	$assert(strpos($html, 'vms-secondary-vendor-group__guidance') !== false, 'Additional Vendors templates should include the compact empty-state guidance copy.');
	$assert(strpos($html, 'Select a vendor type to choose eligible vendors.') !== false, 'Additional Vendors templates should guide the empty group state.');
	$assert(strpos($html, 'vms-secondary-vendor-group--type-pending') !== false, 'New Additional Vendors groups should start in the compact empty-state shell.');
	$assert(strpos($html, 'vms-secondary-vendor-group--market') !== false, 'Market vendor groups should render with the compact market class.');
	$assert(strpos($html, 'vms-secondary-vendor-rows__head') !== false, 'Market vendor groups should render the compact table/list head.');
	$assert(strpos($html, 'vms-secondary-vendor-row__indicators') !== false, 'Market vendor rows should render inline status indicators.');
	$assert(substr_count($html, '1 of 1 filled • Standard') >= 2, 'Standard vendor groups should render compact filled summaries for multiple group types.');
	$assert(strpos($html, '12 of 10 filled') !== false, 'Market vendor group summary should show the filled/capacity count.');
	$assert(strpos($html, 'Target 12') !== false, 'Market vendor group summary should show the target count when configured.');
	$assert(strpos($html, '0 needed') !== false, 'Market vendor group summary should show no open target need when filled meets target.');
	$assert(strpos($html, 'Over capacity by 2') !== false, 'Market vendor group summary should warn when the market group exceeds capacity.');
	$assert(strpos($html, 'vms-secondary-vendor-group-over-capacity-override') !== false, 'Over-capacity Additional Vendors groups should render an explicit override checkbox.');
	$assert(strpos($html, 'Allow over-capacity assignment for this group.') !== false, 'Over-capacity override copy should be visible after save.');

	$runSave($planId, array(
		'vms_reopen_section_after_save' => 'staff',
	));
	$staffRedirect = function_exists('bvmgr_event_plan_pull_runtime_redirect_target')
		? (array) bvmgr_event_plan_pull_runtime_redirect_target($planId)
		: array();
	$assert((string) ($staffRedirect['query_args']['vms_ep_load_section'] ?? '') === 'staff', 'Staff save should preserve the staff section reopen target.');
	$assert((string) ($staffRedirect['fragment'] ?? '') === 'vms-staffing', 'Staff save should redirect back to the Staff anchor.');
	$flatIdsAfterStaffSave = array_map('absint', (array) get_post_meta($planId, '_vms_secondary_vendor_ids', true));
	sort($flatIdsAfterStaffSave);
	$indexIdsAfterStaffSave = array_map('absint', (array) get_post_meta($planId, '_vms_secondary_vendor_id', false));
	sort($indexIdsAfterStaffSave);
	$secondaryAfterStaffSave = get_post_meta($planId, $assignmentMetaKey, true);
	$assert($flatIdsAfterStaffSave === $expectedFlatIds, 'Unrelated full saves should preserve flat compatibility secondary vendor ids for grouped Additional Vendors.');
	$assert($indexIdsAfterStaffSave === $expectedFlatIds, 'Unrelated full saves should preserve repeated secondary vendor index rows for grouped Additional Vendors.');
	$assert(wp_json_encode($secondaryBefore) === wp_json_encode($secondaryAfterStaffSave), 'Saving Staff should preserve Additional Vendors canonical assignment meta including market target fields.');

	$runSave($planId, array(
		'vms_reopen_section_after_save' => 'ticketing_v2',
		'vms_ticket_ui_overrides_save_intent' => '1',
		'vms_ticket_ui_layout_override' => 'progressive',
	));
	$ticketRedirect = function_exists('bvmgr_event_plan_pull_runtime_redirect_target')
		? (array) bvmgr_event_plan_pull_runtime_redirect_target($planId)
		: array();
	$secondaryAfter = get_post_meta($planId, $assignmentMetaKey, true);
	$assert((string) ($ticketRedirect['query_args']['vms_ep_load_section'] ?? '') === 'ticketing_v2', 'Ticket save should preserve the ticketing section reopen target.');
	$assert((string) ($ticketRedirect['fragment'] ?? '') === 'vms_event_plan_ticketing_v2', 'Ticket save should redirect back to the Ticketing metabox anchor.');
	$assert(wp_json_encode($secondaryBefore) === wp_json_encode($secondaryAfter), 'Saving an unrelated module should not alter saved Additional Vendors assignments.');

	$eventPlansPhp = (string) file_get_contents(dirname(__DIR__) . '/includes/cpt/event-plans.php');
	$secondaryVendorsJs = (string) file_get_contents(dirname(__DIR__) . '/assets/js/vms-event-plan-secondary-vendors.js');
	$shellAssetJs = (string) file_get_contents(dirname(__DIR__) . '/assets/js/vms-event-plan-shell.js');
	$adminTicketingJs = (string) file_get_contents(dirname(__DIR__) . '/assets/admin-ticketing.js');
	$assert(strpos($eventPlansPhp, "createGroup('');") === false, 'Event Plan PHP should no longer own the Additional Vendors create-group runtime.');
	$assert(strpos($secondaryVendorsJs, "createGroup('');") !== false, 'Adding a vendor group should start from the compact empty state instead of auto-selecting a type.');
	$assert(strpos($shellAssetJs, 'window.BVMGR_EVENT_PLAN_PERSIST_REQUESTED_SECTION = persistRequestedSection;') !== false, 'Event Plan shell asset should expose the saved-section URL helper.');
	$assert(strpos($shellAssetJs, 'window.BVMGR_EVENT_PLAN_REVEAL_REQUESTED_SECTION = revealRequestedSection;') !== false, 'Event Plan shell asset should expose the requested-section reopen helper.');
	$assert(strpos($secondaryVendorsJs, "window.BVMGR_EVENT_PLAN_PERSIST_REQUESTED_SECTION('secondary_vendors');") !== false, 'Additional Vendors save should persist the saved module target.');
	$assert(strpos($adminTicketingJs, "persistRequestedSectionTarget('ticketing_v2');") !== false, 'Ticketing saves should persist the saved module target.');

	fwrite(STDOUT, "event plan module reopen + market layout regression: PASS\n");
} catch (Throwable $e) {
	fwrite(STDERR, 'event plan module reopen + market layout regression: FAIL - ' . $e->getMessage() . "\n");
	exit(1);
} finally {
	$cleanup();
}
