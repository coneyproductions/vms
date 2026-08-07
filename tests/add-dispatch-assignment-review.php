<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap-wordpress.php';
vms_tests_require_wordpress(__DIR__);

if (!class_exists('VMS_Admin_Event_Plans')) {
	require_once dirname(__DIR__) . '/vendor-management-system.php';
}
if (!function_exists('vms_add_dispatch_render_assignment_review')) {
	require_once dirname(__DIR__) . '/includes/modules/availability-date-dispatch/admin-ui.php';
}
if (!function_exists('vms_add_dispatch_email_body_text')) {
	require_once dirname(__DIR__) . '/includes/modules/availability-date-dispatch/email.php';
}

$assert = static function (bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
};

$createdPosts = array();
$createdTerms = array();
$createdAddRequestIds = array();

$registerPost = static function (int $postId) use (&$createdPosts): int {
	$createdPosts[] = $postId;
	return $postId;
};

$cleanup = static function () use (&$createdPosts, &$createdTerms, &$createdAddRequestIds): void {
	global $wpdb;
	$requestsTable = function_exists('vms_add_dispatch_table_name') ? vms_add_dispatch_table_name('requests') : '';
	$responsesTable = function_exists('vms_add_dispatch_table_name') ? vms_add_dispatch_table_name('responses') : '';
	foreach (array_reverse($createdAddRequestIds) as $requestId) {
		$requestId = (int) $requestId;
		if ($requestId <= 0) {
			continue;
		}
		if ($responsesTable !== '') {
			$wpdb->delete($responsesTable, array('request_id' => $requestId), array('%d'));
		}
		if ($requestsTable !== '') {
			$wpdb->delete($requestsTable, array('id' => $requestId), array('%d'));
		}
	}
	foreach (array_reverse($createdPosts) as $postId) {
		wp_delete_post((int) $postId, true);
	}
	foreach (array_reverse($createdTerms) as $termId) {
		wp_delete_term((int) $termId, 'vms_vendor_type');
	}
};

try {
	wp_set_current_user(1);

	$ensureVendorType = static function (string $slug, string $label) use (&$createdTerms): string {
		$existing = get_term_by('slug', $slug, 'vms_vendor_type');
		if ($existing instanceof WP_Term) {
			return (string) $existing->slug;
		}
		$created = wp_insert_term($label, 'vms_vendor_type', array('slug' => $slug));
		if (is_wp_error($created) || empty($created['term_id'])) {
			throw new RuntimeException('Failed to create vendor type: ' . $slug);
		}
		$createdTerms[] = (int) $created['term_id'];
		return $slug;
	};

	$createVendor = static function (string $title, array $typeSlugs, string $email = '') use ($registerPost): int {
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
		if ($email !== '') {
			update_post_meta($vendorId, vms_add_dispatch_vendor_email_key(), $email);
		}
		return $vendorId;
	};

	$createPlan = static function (string $title, int $primaryVendorId = 0) use ($registerPost): int {
		$planId = wp_insert_post(array(
			'post_type' => 'vms_event_plan',
			'post_status' => 'publish',
			'post_title' => $title,
		), true);
		if (is_wp_error($planId) || (int) $planId <= 0) {
			throw new RuntimeException('Failed to create Event Plan: ' . $title);
		}
		$planId = $registerPost((int) $planId);
		update_post_meta($planId, '_vms_event_date', wp_date('Y-m-d', strtotime('+60 days')));
		update_post_meta($planId, '_vms_start_time', '18:00');
		update_post_meta($planId, '_vms_end_time', '22:00');
		update_post_meta($planId, '_vms_event_plan_status', 'ready');
		update_post_meta($planId, '_vms_venue_id', 0);
		if ($primaryVendorId > 0) {
			update_post_meta($planId, '_vms_band_vendor_id', $primaryVendorId);
		}
		return $planId;
	};

	foreach (array(
		'band' => 'Music Vendor',
		'food_truck' => 'Food Vendor',
		'food_vendor' => 'Food Vendor',
		'dessert_truck' => 'Dessert Vendor',
	) as $slug => $label) {
		$ensureVendorType($slug, $label);
	}

	$primaryVendorId = $createVendor('ADD Assign Review Music Vendor', array('band'), 'assign-music@example.test');
	$vendorId = $createVendor('ADD Assign Review Dessert Vendor', array('food_truck', 'food_vendor'), 'assign-dessert@example.test');
	$existingDessertVendorId = $createVendor('ADD Assign Review Existing Dessert Vendor', array('dessert_truck'), 'assign-existing-dessert@example.test');
	$planId = $createPlan('ADD Assign Review Food To Dessert', $primaryVendorId);
	$seed = vms_event_plan_write_secondary_vendor_assignments($planId, array(
		'food_truck' => array(
			'mode' => 'standard',
			'slot_limit' => 1,
			'vendor_ids' => array(),
		),
		'dessert_truck' => array(
			'mode' => 'standard',
			'slot_limit' => 1,
			'vendor_ids' => array(),
		),
	));
	$assert(!is_wp_error($seed), 'Seeded Food/Dessert groups should save.');

	$created = vms_add_dispatch_create_request($planId, array(
		'target_mode' => 'secondary',
		'vendor_type' => 'food_truck',
		'include_no_response' => 1,
		'include_unknown' => 1,
	), array(array(
		'vendor_id' => $vendorId,
		'title' => get_the_title($vendorId),
		'email' => 'assign-dessert@example.test',
	)));
	$assert(!is_wp_error($created), 'Food Vendor ADD request should be created.');
	$request = (array) ($created['request'] ?? array());
	$response = (array) (($created['responses'][0] ?? array()));
	$createdAddRequestIds[] = (int) ($request['id'] ?? 0);
	$responseId = (int) ($response['id'] ?? 0);
	$assert($responseId > 0, 'ADD response should be stored.');
	$recorded = vms_add_dispatch_record_public_response($response, 'available', 'email');
	$assert(!is_wp_error($recorded), 'Vendor should respond Available.');

	wp_set_object_terms($vendorId, array('dessert_truck'), 'vms_vendor_type', false);

	$beforeAssignments = (array) get_post_meta($planId, vms_event_plan_secondary_vendor_assignment_meta_key(), true);
	$review = vms_add_dispatch_assignment_review($responseId);
	$assert(!is_wp_error($review), 'Assignment review should load.');
	$review = (array) $review;
	$assert((string) ($review['original_type'] ?? '') === 'food_truck', 'Original response type should remain Food Vendor.');
	$assert(in_array('dessert_truck', (array) ($review['current_types'] ?? array()), true), 'Current vendor type should be Dessert Vendor.');
	$assert((string) ($review['selected_type'] ?? '') === 'dessert_truck', 'Review should default Assign as target to current Dessert Vendor type.');
	$assert(!empty($review['warnings']) && strpos((string) $review['warnings'][0], 'originally responded as Food Vendor') !== false, 'Review should warn about Food Vendor to Dessert Vendor mismatch.');
	ob_start();
	vms_add_dispatch_render_assignment_review($responseId);
	$reviewHtml = (string) ob_get_clean();
	$assert(strpos($reviewHtml, 'Assign as') !== false && strpos($reviewHtml, 'Dessert Vendor') !== false && strpos($reviewHtml, 'Food Vendor') !== false, 'Review UI should show target selector and readable labels.');

	$direct = vms_add_dispatch_assign_vendor_to_plan($responseId);
	$assert(is_wp_error($direct) && $direct->get_error_code() === 'add_dispatch_assignment_target_required', 'Legacy direct assignment should not write without confirmation target.');
	$assert(wp_json_encode($beforeAssignments) === wp_json_encode(get_post_meta($planId, vms_event_plan_secondary_vendor_assignment_meta_key(), true)), 'Review/direct attempt should not change assignments before confirmation.');

	$assigned = vms_add_dispatch_apply_assignment_review($responseId, 'dessert_truck', false);
	$assert(!is_wp_error($assigned), 'Confirmed Dessert Vendor assignment should succeed.');
	$afterAssignments = (array) get_post_meta($planId, vms_event_plan_secondary_vendor_assignment_meta_key(), true);
	$assert(in_array($vendorId, (array) ($afterAssignments['dessert_truck']['vendor_ids'] ?? array()), true), 'Confirmed assignment should write vendor into Dessert Vendor group.');
	$assert(!in_array($vendorId, (array) ($afterAssignments['food_truck']['vendor_ids'] ?? array()), true), 'Food Vendor slot should remain open.');
	$context = vms_add_dispatch_get_event_plan_context($planId);
	$foodRow = array();
	foreach ((array) ($context['vendor_need_rows'] ?? array()) as $row) {
		if ((string) ($row['type_slug'] ?? '') === 'food_truck') {
			$foodRow = (array) $row;
			break;
		}
	}
	$assert(!empty($foodRow['is_open']) && (int) ($foodRow['open_needed'] ?? 0) === 1, 'Food Vendor open need should remain after assigning vendor as Dessert Vendor.');
	$compatIds = get_post_meta($planId, function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'secondary_vendor_ids') : '_vms_secondary_vendor_ids', true);
	$assert(in_array($vendorId, (array) $compatIds, true), 'Compatibility secondary vendor IDs should include assigned Dessert Vendor.');
	$freshResponse = vms_add_dispatch_get_response($responseId);
	$assert(is_array($freshResponse) && trim((string) ($freshResponse['assigned_at'] ?? '')) !== '', 'ADD response should be marked assigned after confirmation.');

	$fullPlanId = $createPlan('ADD Assign Review Full Dessert', $primaryVendorId);
	$fullSeed = vms_event_plan_write_secondary_vendor_assignments($fullPlanId, array(
		'dessert_truck' => array(
			'mode' => 'standard',
			'slot_limit' => 1,
			'vendor_ids' => array($existingDessertVendorId),
		),
	));
	$assert(!is_wp_error($fullSeed), 'Full Dessert fixture should save.');
	$fullCreated = vms_add_dispatch_create_request($fullPlanId, array(
		'target_mode' => 'secondary',
		'vendor_type' => 'food_truck',
		'include_no_response' => 1,
		'include_unknown' => 1,
	), array(array(
		'vendor_id' => $vendorId,
		'title' => get_the_title($vendorId),
		'email' => 'assign-dessert@example.test',
	)));
	$assert(!is_wp_error($fullCreated), 'Full-group ADD request should be created.');
	$fullRequest = (array) ($fullCreated['request'] ?? array());
	$fullResponse = (array) (($fullCreated['responses'][0] ?? array()));
	$createdAddRequestIds[] = (int) ($fullRequest['id'] ?? 0);
	$fullResponseId = (int) ($fullResponse['id'] ?? 0);
	$assert(!is_wp_error(vms_add_dispatch_record_public_response($fullResponse, 'available', 'email')), 'Full-group response should be Available.');
	$blocked = vms_add_dispatch_apply_assignment_review($fullResponseId, 'dessert_truck', false);
	$assert(is_wp_error($blocked) && $blocked->get_error_code() === 'vms_secondary_vendor_over_capacity', 'Full target group should require over-capacity override.');
	$override = vms_add_dispatch_apply_assignment_review($fullResponseId, 'dessert_truck', true);
	$assert(!is_wp_error($override), 'Full target group should assign when override is confirmed.');
	$fullAssignments = (array) get_post_meta($fullPlanId, vms_event_plan_secondary_vendor_assignment_meta_key(), true);
	$assert(!empty($fullAssignments['dessert_truck']['allow_over_capacity']), 'Override confirmation should persist allow_over_capacity.');
	$assert(in_array($vendorId, (array) ($fullAssignments['dessert_truck']['vendor_ids'] ?? array()), true), 'Override assignment should add vendor to full Dessert Vendor group.');

	fwrite(STDOUT, "ADD assignment review regression: PASS\n");
} catch (Throwable $e) {
	fwrite(STDERR, 'ADD assignment review regression: FAIL - ' . $e->getMessage() . "\n");
	exit(1);
} finally {
	$cleanup();
}
