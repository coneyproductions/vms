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

$registerPost = static function (int $postId) use (&$createdPosts): int {
	$createdPosts[] = $postId;
	return $postId;
};

$registerTerm = static function (array $termRow) use (&$createdTerms): array {
	$createdTerms[] = $termRow;
	return $termRow;
};

$cleanup = static function () use (&$createdPosts, &$createdTerms, &$originalPost): void {
	foreach (array_reverse($createdPosts) as $postId) {
		wp_delete_post((int) $postId, true);
	}
	foreach (array_reverse($createdTerms) as $termRow) {
		if (!empty($termRow['created']) && !empty($termRow['term_id'])) {
			wp_delete_term((int) $termRow['term_id'], 'vms_vendor_type');
		}
	}
	$_POST = $originalPost;
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

	$createVendor = static function (string $title, array $typeSlugs) use ($registerPost): int {
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

	$normalizeAssignments = static function (array $assignments): array {
		$normalized = array();
		foreach ($assignments as $typeSlug => $assignment) {
			if (!is_array($assignment)) {
				continue;
			}

			$typeSlug = sanitize_key((string) $typeSlug);
			if ($typeSlug === '') {
				continue;
			}

			$vendorIds = array_values(array_unique(array_filter(array_map('absint', (array) ($assignment['vendor_ids'] ?? array())))));
			sort($vendorIds);

			$normalized[$typeSlug] = array(
				'mode' => sanitize_key((string) ($assignment['mode'] ?? '')),
				'slot_limit' => ($assignment['slot_limit'] ?? null),
				'vendor_ids' => $vendorIds,
			);
		}

		ksort($normalized);
		return $normalized;
	};

	$runBroadSave = static function (int $planId, array $overrides = array()): void {
		$_POST = array_merge(array(
			'vms_event_plan_details_nonce' => wp_create_nonce('vms_save_event_plan_details'),
			'post_ID' => $planId,
			'original_post_status' => 'publish',
			'vms_event_plan_action' => 'save_draft',
			'vms_staffing_lazy_unloaded' => '1',
			'vms_secondary_vendors_lazy_unloaded' => '1',
			'vms_secondary_vendors_module_detached' => '1',
		), $overrides);

		$reflection = new ReflectionClass('BVMGR_Admin_Event_Plans');
		/** @var BVMGR_Admin_Event_Plans $admin */
		$admin = $reflection->newInstanceWithoutConstructor();
		$admin->save_event_plan_meta($planId, get_post($planId));
		clean_post_cache($planId);
	};

	foreach (array(
		'band' => 'Music Vendor',
		'food_truck' => 'Food Vendor',
		'dessert_truck' => 'Dessert Vendor',
		'market_vendor' => 'Market Vendor',
	) as $slug => $label) {
		$ensureVendorType($slug, $label);
	}

	$primaryVendorId = $createVendor('Primary Music Vendor', array('band'));
	$foodVendorA = $createVendor('Food Vendor A', array('food_truck'));
	$foodVendorB = $createVendor('Food Vendor B', array('food_truck'));
	$dessertVendorA = $createVendor('Dessert Vendor A', array('dessert_truck'));
	$dessertVendorB = $createVendor('Dessert Vendor B', array('dessert_truck'));
	$marketVendorC = $createVendor('Market Vendor C', array('market_vendor'));
	$marketVendorD = $createVendor('Market Vendor D', array('market_vendor'));
	$marketVendorE = $createVendor('Market Vendor E', array('market_vendor'));
	$planId = $createPlan('Secondary Vendor Assignments Regression');

	update_post_meta($planId, '_vms_event_plan_status', 'draft');
	update_post_meta($planId, '_vms_event_date', '2026-07-10');
	update_post_meta($planId, '_vms_start_time', '18:00');
	update_post_meta($planId, '_vms_end_time', '22:00');
	update_post_meta($planId, '_vms_venue_id', 0);
	update_post_meta($planId, '_vms_band_vendor_id', $primaryVendorId);

	$assignmentMetaKey = function_exists('bvmgr_event_plan_secondary_vendor_assignment_meta_key')
		? bvmgr_event_plan_secondary_vendor_assignment_meta_key()
		: '_vms_secondary_vendor_assignments_v1';

	delete_post_meta($planId, $assignmentMetaKey);
	update_post_meta($planId, '_vms_secondary_vendor_type', 'food_truck');
	update_post_meta($planId, '_vms_secondary_vendor_ids', array($foodVendorA));
	delete_post_meta($planId, '_vms_secondary_vendor_id');
	add_post_meta($planId, '_vms_secondary_vendor_id', $foodVendorA, false);

	$legacyHydrated = function_exists('bvmgr_event_plan_get_secondary_vendor_assignments')
		? bvmgr_event_plan_get_secondary_vendor_assignments($planId, array('primary_vendor_id' => $primaryVendorId))
		: array();
	$assert($normalizeAssignments($legacyHydrated) === array(
		'food_truck' => array(
			'mode' => 'standard',
			'slot_limit' => 1,
			'vendor_ids' => array($foodVendorA),
		),
	), 'Legacy secondary vendor meta should hydrate into the canonical assignment map.');

	$saveResult = function_exists('bvmgr_event_plan_save_secondary_vendors_module')
		? bvmgr_event_plan_save_secondary_vendors_module($planId, array(
			'vms_secondary_vendor_assignments' => array(
				array(
					'type_slug' => 'food_truck',
					'mode' => 'standard',
					'slot_limit' => '1',
					'vendor_ids' => array($foodVendorA),
				),
				array(
					'type_slug' => 'dessert_truck',
					'mode' => 'standard',
					'slot_limit' => '1',
					'vendor_ids' => array($dessertVendorA),
				),
				array(
					'type_slug' => 'market_vendor',
					'mode' => 'market',
					'slot_limit' => '10',
					'vendor_ids' => array($marketVendorC, $marketVendorD, $marketVendorE),
				),
			),
		))
		: new WP_Error('missing_helper', 'Secondary vendor module save helper is unavailable.');
	$assert(!is_wp_error($saveResult), 'Secondary vendor module save should succeed.');

	$expectedAssignments = array(
		'dessert_truck' => array(
			'mode' => 'standard',
			'slot_limit' => 1,
			'vendor_ids' => array($dessertVendorA),
		),
		'food_truck' => array(
			'mode' => 'standard',
			'slot_limit' => 1,
			'vendor_ids' => array($foodVendorA),
		),
		'market_vendor' => array(
			'mode' => 'market',
			'slot_limit' => 10,
			'vendor_ids' => array($marketVendorC, $marketVendorD, $marketVendorE),
		),
	);

	$storedAssignments = get_post_meta($planId, $assignmentMetaKey, true);
	$assert($normalizeAssignments((array) $storedAssignments) === $expectedAssignments, 'Canonical secondary vendor assignment meta should store Food, Dessert, and Market groups.');

	$flatSecondaryIds = (array) get_post_meta($planId, '_vms_secondary_vendor_ids', true);
	sort($flatSecondaryIds);
	$expectedFlatIds = array($dessertVendorA, $foodVendorA, $marketVendorC, $marketVendorD, $marketVendorE);
	sort($expectedFlatIds);
	$assert($flatSecondaryIds === $expectedFlatIds, 'Flat secondary vendor ids should contain every assigned additional vendor.');

	$indexSecondaryIds = array_map('absint', (array) get_post_meta($planId, '_vms_secondary_vendor_id', false));
	sort($indexSecondaryIds);
	$assert($indexSecondaryIds === $expectedFlatIds, 'Repeated secondary vendor index rows should contain every assigned additional vendor.');

	if (function_exists('bvmgr_event_plan_import_update_secondary_meta')) {
		bvmgr_event_plan_import_update_secondary_meta($planId, 'food_truck', array($foodVendorB), $primaryVendorId);
		$afterImportAssignments = function_exists('bvmgr_event_plan_get_secondary_vendor_assignments')
			? bvmgr_event_plan_get_secondary_vendor_assignments($planId, array('primary_vendor_id' => $primaryVendorId))
			: array();
		$assert($normalizeAssignments($afterImportAssignments) === array(
			'dessert_truck' => array(
				'mode' => 'standard',
				'slot_limit' => 1,
				'vendor_ids' => array($dessertVendorA),
			),
			'food_truck' => array(
				'mode' => 'standard',
				'slot_limit' => 1,
				'vendor_ids' => array($foodVendorB),
			),
			'market_vendor' => array(
				'mode' => 'market',
				'slot_limit' => 10,
				'vendor_ids' => array($marketVendorC, $marketVendorD, $marketVendorE),
			),
		), 'Importer secondary vendor updates should preserve unrelated assignment groups.');
	}

	$beforeBroadSave = $normalizeAssignments((array) get_post_meta($planId, $assignmentMetaKey, true));
	$runBroadSave($planId, array(
		'vms_start_time' => '18:30',
		'vms_end_time' => '22:30',
	));
	$afterBroadSave = $normalizeAssignments((array) get_post_meta($planId, $assignmentMetaKey, true));
	$assert($afterBroadSave === $beforeBroadSave, 'Broad Event Plan saves must not wipe canonical secondary vendor assignments when the detached module is not posted.');

	$setResult = function_exists('bvmgr_event_plan_set_secondary_vendors')
		? bvmgr_event_plan_set_secondary_vendors($planId, 'food_truck', array($foodVendorA))
		: new WP_Error('missing_helper', 'Secondary vendor set helper is unavailable.');
	$assert(!is_wp_error($setResult), 'Setting one secondary vendor group should succeed.');

	$afterSetAssignments = function_exists('bvmgr_event_plan_get_secondary_vendor_assignments')
		? bvmgr_event_plan_get_secondary_vendor_assignments($planId, array('primary_vendor_id' => $primaryVendorId))
		: array();
	$assert($normalizeAssignments($afterSetAssignments) === $expectedAssignments, 'Saving Food Vendor should not wipe Dessert Vendor or Market Vendor groups.');

	$calendarAssignments = array(
		'food_truck' => array(
			'mode' => 'standard',
			'slot_limit' => 1,
			'vendor_ids' => array($foodVendorA),
		),
		'dessert_truck' => array(
			'mode' => 'standard',
			'slot_limit' => 1,
			'vendor_ids' => array(),
		),
		'market_vendor' => array(
			'mode' => 'market',
			'slot_limit' => 10,
			'vendor_ids' => array($marketVendorC, $marketVendorD, $marketVendorE),
		),
	);
	$writeCalendarAssignments = function_exists('bvmgr_event_plan_write_secondary_vendor_assignments')
		? bvmgr_event_plan_write_secondary_vendor_assignments($planId, $calendarAssignments)
		: new WP_Error('missing_helper', 'Secondary vendor write helper is unavailable.');
	$assert(!is_wp_error($writeCalendarAssignments), 'Writing calendar secondary vendor assignments should succeed.');

	$calendarGroups = function_exists('bvmgr_calendar_prepare_vendor_groups')
		? bvmgr_calendar_prepare_vendor_groups($planId, 0, 'admin', 0)
		: array();
	$assert(isset($calendarGroups['food_truck'], $calendarGroups['dessert_truck'], $calendarGroups['market_vendor']), 'Calendar feed should build vendor groups for each additional vendor type.');
	$assert((int) ($calendarGroups['food_truck']['filled_slots'] ?? -1) === 1, 'Food Vendor slot counts should only reflect Food Vendor assignments.');
	$assert((int) ($calendarGroups['food_truck']['max_slots'] ?? -1) === 1, 'Food Vendor max slots should remain separate from other vendor types.');
	$assert(empty($calendarGroups['food_truck']['has_open_slots']), 'A filled Food Vendor slot should not remain open.');
	$assert((int) ($calendarGroups['dessert_truck']['filled_slots'] ?? -1) === 0, 'Dessert Vendor slot counts should remain separate from Food Vendor assignments.');
	$assert((int) ($calendarGroups['dessert_truck']['max_slots'] ?? -1) === 1, 'Dessert Vendor max slots should default independently.');
	$assert(!empty($calendarGroups['dessert_truck']['has_open_slots']), 'An empty Dessert Vendor slot should remain open even when Food Vendor is filled.');
	$assert((int) ($calendarGroups['market_vendor']['filled_slots'] ?? -1) === 3, 'Market Vendor filled slots should reflect only Market Vendor assignments.');
	$assert((int) ($calendarGroups['market_vendor']['max_slots'] ?? -1) === 10, 'Market Vendor capacity should remain separate from standard vendor slots.');
	$assert(!empty($calendarGroups['market_vendor']['has_open_slots']), 'Market Vendor groups should stay open while capacity remains.');

	$dispatchContext = function_exists('bvmgr_add_dispatch_get_event_plan_context')
		? bvmgr_add_dispatch_get_event_plan_context($planId)
		: null;
	$assert(is_array($dispatchContext), 'ADD context should be available for Event Plans.');
	$dispatchMissingTypes = array_values(array_unique(array_map('sanitize_key', (array) ($dispatchContext['missing_secondary_types'] ?? array()))));
	sort($dispatchMissingTypes);
	$assert($dispatchMissingTypes === array('dessert_truck'), 'ADD context should expose only secondary vendor types with open needed slots, not spare Market capacity without a target.');

	$dessertInterest = function_exists('bvmgr_add_dispatch_resolve_vendor_interest_target')
		? bvmgr_add_dispatch_resolve_vendor_interest_target((array) $dispatchContext, $dessertVendorB)
		: array('ok' => false);
	$assert(!empty($dessertInterest['ok']) && (string) ($dessertInterest['vendor_type'] ?? '') === 'dessert_truck', 'ADD should allow Dessert Vendors to target the open Dessert Vendor slot.');

	$foodInterest = function_exists('bvmgr_add_dispatch_resolve_vendor_interest_target')
		? bvmgr_add_dispatch_resolve_vendor_interest_target((array) $dispatchContext, $foodVendorB)
		: array('ok' => true);
	$assert(empty($foodInterest['ok']), 'ADD should not treat a filled Food Vendor slot as open just because another vendor type still has capacity.');

	$assert(function_exists('bvmgr_vendor_type_label') && bvmgr_vendor_type_label('band') === 'Music Vendor', 'The band vendor type should display as Music Vendor.');
	$assert(function_exists('bvmgr_vendor_type_label') && bvmgr_vendor_type_label('food_truck') === 'Food Vendor', 'The food_truck vendor type should display as Food Vendor.');

	fwrite(STDOUT, "event plan secondary vendor assignments regression: PASS\n");
} catch (Throwable $e) {
	fwrite(STDERR, 'event plan secondary vendor assignments regression: FAIL - ' . $e->getMessage() . "\n");
	$cleanup();
	exit(1);
}

$cleanup();
