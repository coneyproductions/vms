<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap-wordpress.php';
vms_tests_require_wordpress(__DIR__);

if (!class_exists('VMS_Admin_Event_Plans')) {
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

$registerPost = static function (int $postId) use (&$createdPosts): int {
	$createdPosts[] = $postId;
	return $postId;
};

$registerTerm = static function (array $termRow) use (&$createdTerms): array {
	$createdTerms[] = $termRow;
	return $termRow;
};

$cleanup = static function () use (&$createdPosts, &$createdTerms): void {
	foreach (array_reverse($createdPosts) as $postId) {
		wp_delete_post((int) $postId, true);
	}
	foreach (array_reverse($createdTerms) as $termRow) {
		if (!empty($termRow['created']) && !empty($termRow['term_id'])) {
			wp_delete_term((int) $termRow['term_id'], 'vms_vendor_type');
		}
	}
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
		wp_set_object_terms($vendorId, array_values($typeSlugs), 'vms_vendor_type', false);
		return $vendorId;
	};

	$createPlan = static function (string $title, int $primaryVendorId) use ($registerPost): int {
		$planId = wp_insert_post(array(
			'post_type' => 'vms_event_plan',
			'post_status' => 'publish',
			'post_title' => $title,
		), true);
		if (is_wp_error($planId) || (int) $planId <= 0) {
			throw new RuntimeException('Failed to create Event Plan: ' . $title);
		}

		$planId = $registerPost((int) $planId);
		update_post_meta($planId, '_vms_event_plan_status', 'draft');
		update_post_meta($planId, '_vms_event_date', '2026-08-20');
		update_post_meta($planId, '_vms_start_time', '18:00');
		update_post_meta($planId, '_vms_end_time', '22:00');
		update_post_meta($planId, '_vms_venue_id', 0);
		update_post_meta($planId, '_vms_band_vendor_id', $primaryVendorId);
		return $planId;
	};

	foreach (array(
		'band' => 'Music Vendor',
		'food_truck' => 'Food Vendor',
		'dessert_truck' => 'Dessert Vendor',
		'market_vendor' => 'Market Vendor',
	) as $slug => $label) {
		$ensureVendorType($slug, $label);
	}

	$primaryVendorId = $createVendor('Capacity Primary Vendor', array('band'));
	$foodVendorA = $createVendor('Capacity Food Vendor A', array('food_truck'));
	$foodVendorB = $createVendor('Capacity Food Vendor B', array('food_truck'));
	$dessertVendorA = $createVendor('Capacity Dessert Vendor A', array('dessert_truck'));
	$dessertVendorB = $createVendor('Capacity Dessert Vendor B', array('dessert_truck'));
	$marketVendorA = $createVendor('Capacity Market Vendor A', array('market_vendor'));
	$marketVendorB = $createVendor('Capacity Market Vendor B', array('market_vendor'));

	$assignmentMetaKey = function_exists('vms_event_plan_secondary_vendor_assignment_meta_key')
		? (string) vms_event_plan_secondary_vendor_assignment_meta_key()
		: '_vms_secondary_vendor_assignments_v1';

	$planId = $createPlan('Secondary Vendor Capacity Regression', $primaryVendorId);
	$seedResult = vms_event_plan_save_secondary_vendors_module($planId, array(
		'vms_secondary_vendor_assignments' => array(
			array(
				'type_slug' => 'food_truck',
				'mode' => 'standard',
				'slot_limit' => '1',
				'vendor_ids' => array($foodVendorA),
			),
		),
	));
	$assert(!is_wp_error($seedResult), 'Initial in-capacity Food Vendor seed should save.');

	$beforeAssignments = get_post_meta($planId, $assignmentMetaKey, true);
	$beforeFlatIds = get_post_meta($planId, '_vms_secondary_vendor_ids', true);
	$beforeIndexIds = get_post_meta($planId, '_vms_secondary_vendor_id', false);

	$blockedResult = vms_event_plan_save_secondary_vendors_module($planId, array(
		'vms_secondary_vendor_assignments' => array(
			array(
				'type_slug' => 'food_truck',
				'mode' => 'standard',
				'slot_limit' => '1',
				'vendor_ids' => array($foodVendorA, $foodVendorB),
			),
		),
	));
	$assert(is_wp_error($blockedResult), 'Standard Additional Vendor groups should reject over-capacity saves without an override.');
	$assert($blockedResult instanceof WP_Error && $blockedResult->get_error_code() === 'vms_secondary_vendor_over_capacity', 'Blocked standard over-capacity save should use the capacity error code.');
	$assert(wp_json_encode($beforeAssignments) === wp_json_encode(get_post_meta($planId, $assignmentMetaKey, true)), 'Blocked over-capacity saves must not change canonical assignment meta.');
	$assert(wp_json_encode($beforeFlatIds) === wp_json_encode(get_post_meta($planId, '_vms_secondary_vendor_ids', true)), 'Blocked over-capacity saves must not change flat compatibility meta.');
	$assert(wp_json_encode($beforeIndexIds) === wp_json_encode(get_post_meta($planId, '_vms_secondary_vendor_id', false)), 'Blocked over-capacity saves must not change repeated compatibility meta.');

	$approvedResult = vms_event_plan_save_secondary_vendors_module($planId, array(
		'vms_secondary_vendor_assignments' => array(
			array(
				'type_slug' => 'food_truck',
				'mode' => 'standard',
				'slot_limit' => '1',
				'allow_over_capacity' => '1',
				'vendor_ids' => array($foodVendorA, $foodVendorB),
			),
			array(
				'type_slug' => 'dessert_truck',
				'mode' => 'standard',
				'slot_limit' => '',
				'vendor_ids' => array($dessertVendorA),
			),
			array(
				'type_slug' => 'market_vendor',
				'mode' => 'market',
				'slot_limit' => '3',
				'needed_slots' => '3',
				'open_for_dispatch' => '1',
				'vendor_ids' => array($marketVendorA),
			),
		),
	));
	$assert(!is_wp_error($approvedResult), 'Explicit over-capacity override should allow the standard Food Vendor group to save.');

	$storedAssignments = (array) get_post_meta($planId, $assignmentMetaKey, true);
	$assert(!empty($storedAssignments['food_truck']['allow_over_capacity']), 'Approved over-capacity groups should persist the override flag in canonical assignment meta.');
	$assert((int) ($storedAssignments['dessert_truck']['slot_limit'] ?? 0) === 1, 'Blank standard group capacity should normalize to the vendor type default.');
	$assert((int) ($storedAssignments['market_vendor']['slot_limit'] ?? 0) === 3, 'Market Vendor capacity should remain the explicit submitted value.');
	$assert((int) ($storedAssignments['market_vendor']['needed_slots'] ?? 0) === 3, 'Market Vendor open needs should persist an explicit needed target.');
	$assert(!empty($storedAssignments['market_vendor']['open_for_dispatch']), 'Market Vendor open needs should persist the ADD visibility flag.');

	$context = vms_add_dispatch_get_event_plan_context($planId);
	$assert(is_array($context), 'ADD context should load for the capacity test Event Plan.');
	$rowsByType = array();
	foreach ((array) ($context['secondary_vendor_groups'] ?? array()) as $row) {
		$rowsByType[(string) ($row['type_slug'] ?? '')] = $row;
	}
	$assert((string) ($rowsByType['food_truck']['status'] ?? '') === 'over_capacity', 'ADD should report over-capacity Food Vendor groups.');
	$assert((string) ($rowsByType['dessert_truck']['status'] ?? '') === 'full', 'ADD should report filled standard groups as full.');
	$assert((string) ($rowsByType['market_vendor']['status'] ?? '') === 'open', 'ADD should report market groups below capacity as open.');
	$assert((int) ($rowsByType['market_vendor']['open_spots'] ?? -1) === 2, 'ADD should expose market open spots from grouped capacity.');
	$assert(in_array('market_vendor', (array) ($context['missing_secondary_types'] ?? array()), true), 'ADD missing types should include only open-capacity groups.');
	$assert(!in_array('food_truck', (array) ($context['missing_secondary_types'] ?? array()), true), 'ADD missing types should not include over-capacity groups as open needs.');

	$blockedSet = vms_event_plan_set_secondary_vendors($planId, 'dessert_truck', array($dessertVendorA, $dessertVendorB));
	$assert(is_wp_error($blockedSet), 'ADD assignment setter should reject over-capacity secondary vendor updates without an override.');
	$assert($blockedSet instanceof WP_Error && $blockedSet->get_error_code() === 'vms_secondary_vendor_over_capacity', 'ADD assignment setter should surface the capacity error code.');

	$enableDessertOverride = vms_event_plan_save_secondary_vendors_module($planId, array(
		'vms_secondary_vendor_assignments' => array(
			array(
				'type_slug' => 'food_truck',
				'mode' => 'standard',
				'slot_limit' => '1',
				'allow_over_capacity' => '1',
				'vendor_ids' => array($foodVendorA, $foodVendorB),
			),
			array(
				'type_slug' => 'dessert_truck',
				'mode' => 'standard',
				'slot_limit' => '1',
				'allow_over_capacity' => '1',
				'vendor_ids' => array($dessertVendorA),
			),
			array(
				'type_slug' => 'market_vendor',
				'mode' => 'market',
				'slot_limit' => '3',
				'needed_slots' => '3',
				'open_for_dispatch' => '1',
				'vendor_ids' => array($marketVendorA),
			),
		),
	));
	$assert(!is_wp_error($enableDessertOverride), 'Saving a pending override for a full group should succeed.');
	$approvedSet = vms_event_plan_set_secondary_vendors($planId, 'dessert_truck', array($dessertVendorA, $dessertVendorB));
	$assert(!is_wp_error($approvedSet), 'ADD assignment setter should preserve an existing group override and allow the assignment.');
	$afterSetterAssignments = (array) get_post_meta($planId, $assignmentMetaKey, true);
	$assert((int) ($afterSetterAssignments['market_vendor']['needed_slots'] ?? 0) === 3, 'ADD assignment setter should preserve unrelated Market Vendor needed_slots.');
	$assert(!empty($afterSetterAssignments['market_vendor']['open_for_dispatch']), 'ADD assignment setter should preserve unrelated Market Vendor open_for_dispatch.');

	$marketPlanId = $createPlan('Market Vendor Capacity Regression', $primaryVendorId);
	$marketBlocked = vms_event_plan_save_secondary_vendors_module($marketPlanId, array(
		'vms_secondary_vendor_assignments' => array(
			array(
				'type_slug' => 'market_vendor',
				'mode' => 'market',
				'slot_limit' => '1',
				'vendor_ids' => array($marketVendorA, $marketVendorB),
			),
		),
	));
	$assert(is_wp_error($marketBlocked), 'Market Vendor groups with an explicit capacity should also require an override when over capacity.');

	$marketUncapped = vms_event_plan_save_secondary_vendors_module($marketPlanId, array(
		'vms_secondary_vendor_assignments' => array(
			array(
				'type_slug' => 'market_vendor',
				'mode' => 'market',
				'slot_limit' => '',
				'vendor_ids' => array($marketVendorA, $marketVendorB),
			),
		),
	));
	$assert(!is_wp_error($marketUncapped), 'Blank Market Vendor capacity should remain uncapped and save without an override.');
	$marketAssignments = (array) get_post_meta($marketPlanId, $assignmentMetaKey, true);
	$assert(array_key_exists('market_vendor', $marketAssignments) && array_key_exists('slot_limit', (array) $marketAssignments['market_vendor']) && $marketAssignments['market_vendor']['slot_limit'] === null, 'Blank Market Vendor capacity should persist as uncapped.');

	$assert(vms_add_dispatch_type_label('food-truck') === 'Food Vendor', 'ADD type labels should normalize hyphenated Food Vendor slugs.');
	$assert(vms_add_dispatch_type_label('dessert_truck') === 'Dessert Vendor', 'ADD type labels should display Dessert Vendor.');
	$assert(vms_add_dispatch_type_label('market_vendor') === 'Market Vendor', 'ADD type labels should display Market Vendor.');
	$assert(vms_add_dispatch_type_label('band') === 'Music Vendor', 'ADD type labels should display Music Vendor for primary music vendor type.');

	fwrite(STDOUT, "event plan secondary vendor capacity + ADD regression: PASS\n");
} catch (Throwable $e) {
	fwrite(STDERR, 'event plan secondary vendor capacity + ADD regression: FAIL - ' . $e->getMessage() . "\n");
	exit(1);
} finally {
	$cleanup();
}
