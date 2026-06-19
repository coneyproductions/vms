<?php
declare(strict_types=1);

$wpLoad = dirname(__DIR__, 4) . '/wp-load.php';
if (!defined('ABSPATH')) {
	if (!file_exists($wpLoad)) {
		fwrite(STDERR, "Could not locate wp-load.php.\n");
		exit(1);
	}
	require_once $wpLoad;
}

if (!class_exists('VMS_Admin_Event_Plans')) {
	require_once dirname(__DIR__) . '/vendor-management-system.php';
}
if (!function_exists('vms_add_dispatch_render_request_history')) {
	require_once dirname(__DIR__) . '/includes/modules/availability-date-dispatch/admin-ui.php';
}
if (!function_exists('vms_vendor_availability_collect_vendors')) {
	require_once dirname(__DIR__) . '/includes/admin/vendor-availability.php';
}
if (!function_exists('vms_add_dispatch_email_body_text')) {
	require_once dirname(__DIR__) . '/includes/modules/availability-date-dispatch/email.php';
}

$assert = static function (bool $condition, string $message): void {
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
};

$createdPosts = array();
$createdTerms = array();
$createdAddRequestIds = array();

$registerPost = static function (int $postId) use (&$createdPosts): int {
	$createdPosts[] = $postId;
	return $postId;
};

$registerTerm = static function (array $termRow) use (&$createdTerms): array {
	$createdTerms[] = $termRow;
	return $termRow;
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

	$createPlan = static function (string $title, string $eventDate, int $primaryVendorId = 0, string $eventStatus = 'draft') use ($registerPost): int {
		$planId = wp_insert_post(array(
			'post_type' => 'vms_event_plan',
			'post_status' => 'publish',
			'post_title' => $title,
		), true);
		if (is_wp_error($planId) || (int) $planId <= 0) {
			throw new RuntimeException('Failed to create Event Plan: ' . $title);
		}

		$planId = $registerPost((int) $planId);
		update_post_meta($planId, '_vms_event_date', $eventDate);
		update_post_meta($planId, '_vms_start_time', '18:00');
		update_post_meta($planId, '_vms_end_time', '22:00');
		update_post_meta($planId, '_vms_event_plan_status', $eventStatus);
		update_post_meta($planId, '_vms_venue_id', 0);
		if ($primaryVendorId > 0) {
			update_post_meta($planId, '_vms_band_vendor_id', $primaryVendorId);
		} else {
			delete_post_meta($planId, '_vms_band_vendor_id');
		}
		return $planId;
	};

	$rowByType = static function (array $context, string $typeSlug): array {
		foreach ((array) ($context['vendor_need_rows'] ?? array()) as $row) {
			if ((string) ($row['type_slug'] ?? '') === $typeSlug) {
				return (array) $row;
			}
		}
		foreach ((array) ($context['secondary_vendor_groups'] ?? array()) as $row) {
			if ((string) ($row['type_slug'] ?? '') === $typeSlug) {
				return (array) $row;
			}
		}
		return array();
	};

	foreach (array(
		'band' => 'Music Vendor',
		'food_vendor' => 'Food Vendor',
		'food_truck' => 'Food Vendor',
		'dessert_truck' => 'Dessert Vendor',
		'drink_truck' => 'Drink Vendor',
		'market_vendor' => 'Market Vendor',
	) as $slug => $label) {
		$ensureVendorType($slug, $label);
	}

	$futureDate = wp_date('Y-m-d', strtotime('+45 days'));
	$pastDate = wp_date('Y-m-d', strtotime('-45 days'));
	$primaryVendorId = $createVendor('ADD Needs Music Vendor', array('band'));
	$dessertVendorId = $createVendor('ADD Needs Dessert Vendor', array('dessert_truck'));
	$foodVendorA = $createVendor('ADD Needs Food Vendor A', array('food_truck', 'food_vendor'));
	$foodVendorB = $createVendor('ADD Needs Food Vendor B', array('food_truck', 'food_vendor'));
	$foodNoResponseEmailVendor = $createVendor('ADD No Response Food Email', array('food_truck', 'food_vendor'));
	update_post_meta($foodNoResponseEmailVendor, vms_add_dispatch_vendor_email_key(), 'add-no-response@example.test');
	$foodNoResponseNoEmailVendor = $createVendor('ADD No Response Food No Email', array('food_truck', 'food_vendor'));
	$marketVendorIds = array();
	for ($i = 1; $i <= 30; $i++) {
		$marketVendorIds[] = $createVendor('ADD Needs Market Vendor ' . $i, array('market_vendor'));
	}
	$marketVendorTwelve = array_slice($marketVendorIds, 0, 12);
	$marketVendorTwentyFive = array_slice($marketVendorIds, 0, 25);

	$missingPrimaryPlan = $createPlan('ADD Future Missing Primary Need', $futureDate, 0, 'ready');
	$pastMissingPrimaryPlan = $createPlan('ADD Past Missing Primary Excluded', $pastDate, 0, 'ready');
	$cancelledPlan = $createPlan('ADD Cancelled Missing Primary Excluded', $futureDate, 0, 'cancelled');

	$foodOpenPlan = $createPlan('ADD Food Vendor Open Need', $futureDate, $primaryVendorId, 'ready');
	$foodResult = vms_event_plan_write_secondary_vendor_assignments($foodOpenPlan, array(
		'food_truck' => array(
			'mode' => 'standard',
			'slot_limit' => 1,
			'vendor_ids' => array(),
		),
	));
	$assert(!is_wp_error($foodResult), 'Food Vendor open fixture should save.');

	$dessertFullPlan = $createPlan('ADD Dessert Vendor Full No Need', $futureDate, $primaryVendorId, 'ready');
	$dessertResult = vms_event_plan_write_secondary_vendor_assignments($dessertFullPlan, array(
		'dessert_truck' => array(
			'mode' => 'standard',
			'slot_limit' => 1,
			'vendor_ids' => array($dessertVendorId),
		),
	));
	$assert(!is_wp_error($dessertResult), 'Dessert Vendor full fixture should save.');

	$marketCapacityOnlyPlan = $createPlan('ADD Market Capacity Only No Target', $futureDate, $primaryVendorId, 'ready');
	$marketCapacityResult = vms_event_plan_write_secondary_vendor_assignments($marketCapacityOnlyPlan, array(
		'market_vendor' => array(
			'mode' => 'market',
			'slot_limit' => 50,
			'vendor_ids' => $marketVendorTwelve,
		),
	));
	$assert(!is_wp_error($marketCapacityResult), 'Market capacity-only fixture should save.');

	$marketTargetPlan = $createPlan('ADD Market Target Open Need', $futureDate, $primaryVendorId, 'ready');
	$marketTargetResult = vms_event_plan_write_secondary_vendor_assignments($marketTargetPlan, array(
		'market_vendor' => array(
			'mode' => 'market',
			'slot_limit' => 50,
			'needed_slots' => 25,
			'open_for_dispatch' => true,
			'vendor_ids' => $marketVendorTwelve,
		),
	));
	$assert(!is_wp_error($marketTargetResult), 'Market target fixture should save.');

	$marketTargetFullPlan = $createPlan('ADD Market Target Full', $futureDate, $primaryVendorId, 'ready');
	$marketTargetFullResult = vms_event_plan_write_secondary_vendor_assignments($marketTargetFullPlan, array(
		'market_vendor' => array(
			'mode' => 'market',
			'slot_limit' => 50,
			'needed_slots' => 25,
			'open_for_dispatch' => true,
			'vendor_ids' => $marketVendorTwentyFive,
		),
	));
	$assert(!is_wp_error($marketTargetFullResult), 'Market target-full fixture should save.');

	$marketOverTargetPlan = $createPlan('ADD Market Over Target No Open Need', $futureDate, $primaryVendorId, 'ready');
	$marketOverTargetResult = vms_event_plan_write_secondary_vendor_assignments($marketOverTargetPlan, array(
		'market_vendor' => array(
			'mode' => 'market',
			'slot_limit' => 50,
			'needed_slots' => 25,
			'open_for_dispatch' => true,
			'vendor_ids' => $marketVendorIds,
		),
	));
	$assert(!is_wp_error($marketOverTargetResult), 'Market over-target fixture should save.');

	$overCapacityPlan = $createPlan('ADD Over Capacity Status', $futureDate, $primaryVendorId, 'ready');
	$overCapacityResult = vms_event_plan_write_secondary_vendor_assignments($overCapacityPlan, array(
		'food_truck' => array(
			'mode' => 'standard',
			'slot_limit' => 1,
			'allow_over_capacity' => true,
			'vendor_ids' => array($foodVendorA, $foodVendorB),
		),
	));
	$assert(!is_wp_error($overCapacityResult), 'Over-capacity fixture should save with explicit override.');

	$scan = vms_add_dispatch_get_event_plan_need_scan(20, 20);
	$contextsById = array();
	foreach ((array) ($scan['contexts'] ?? array()) as $context) {
		$contextsById[(int) ($context['event_plan_id'] ?? 0)] = $context;
	}
	$excludedById = array();
	foreach ((array) ($scan['excluded'] ?? array()) as $row) {
		$excludedById[(int) ($row['event_plan_id'] ?? 0)] = $row;
	}

	$assert(isset($contextsById[$missingPrimaryPlan]), 'Future Event Plan with missing Primary Vendor should appear in ADD open needs.');
	$primaryRows = vms_add_dispatch_context_vendor_need_rows((array) $contextsById[$missingPrimaryPlan], false);
	$assert(!empty($primaryRows) && (string) ($primaryRows[0]['target_mode'] ?? '') === 'primary' && (int) ($primaryRows[0]['open_needed'] ?? 0) === 1, 'Missing Primary Vendor should render as one open primary need.');

	$pastReason = vms_add_dispatch_context_exclusion_reason(vms_add_dispatch_get_event_plan_context($pastMissingPrimaryPlan));
	$cancelledReason = vms_add_dispatch_context_exclusion_reason(vms_add_dispatch_get_event_plan_context($cancelledPlan));
	$assert($pastReason === 'Past event', 'Past Event Plan with missing Primary Vendor should be excluded with a documented reason.');
	$assert($cancelledReason === 'Cancelled or archived', 'Cancelled Event Plan should be excluded with a documented reason.');

	$foodContext = vms_add_dispatch_get_event_plan_context($foodOpenPlan);
	$foodRow = $rowByType((array) $foodContext, 'food_truck');
	$assert((string) ($foodRow['status'] ?? '') === 'open' && (int) ($foodRow['open_needed'] ?? 0) === 1, 'Food Vendor capacity 1 / filled 0 should appear as one open Food Vendor need.');
	$assert(vms_add_dispatch_type_label('food-truck') === 'Food Vendor', 'ADD labels should normalize food-truck to Food Vendor.');
	$candidateById = static function (array $rows, int $vendorId): array {
		foreach ($rows as $row) {
			if ((int) ($row['vendor_id'] ?? 0) === $vendorId) {
				return (array) $row;
			}
		}
		return array();
	};
	$builderOff = vms_add_dispatch_parse_builder_args(array(
		'target_mode' => 'secondary',
		'vendor_type' => 'food_truck',
	), (array) $foodContext);
	$collectedNoResponseEmailVendor = $candidateById(vms_vendor_availability_collect_vendors(), $foodNoResponseEmailVendor);
	$candidatesOff = vms_add_dispatch_collect_recipient_candidates((array) $foodContext, $builderOff);
	$noResponseEmailOff = $candidateById($candidatesOff, $foodNoResponseEmailVendor);
	$noResponseNoEmailOff = $candidateById($candidatesOff, $foodNoResponseNoEmailVendor);
	$assert(!empty($noResponseEmailOff) && empty($noResponseEmailOff['included']) && strpos((string) ($noResponseEmailOff['selection_reason'] ?? ''), 'No-response vendors') !== false, 'No-response vendors should be excluded when the no-response toggle is off. Builder: ' . wp_json_encode($builderOff) . ' Collected: ' . wp_json_encode($collectedNoResponseEmailVendor) . ' Row: ' . wp_json_encode($noResponseEmailOff));
	$assert(!empty($noResponseNoEmailOff) && empty($noResponseNoEmailOff['included']) && (string) ($noResponseNoEmailOff['selection_reason'] ?? '') === 'No vendor email on file.', 'No-email vendors should remain uncontactable with a clear reason.');
	$builderOn = vms_add_dispatch_parse_builder_args(array(
		'target_mode' => 'secondary',
		'vendor_type' => 'food_truck',
		'include_no_response' => 1,
	), (array) $foodContext);
	$candidatesOn = vms_add_dispatch_collect_recipient_candidates((array) $foodContext, $builderOn);
	$noResponseEmailOn = $candidateById($candidatesOn, $foodNoResponseEmailVendor);
	$noResponseNoEmailOn = $candidateById($candidatesOn, $foodNoResponseNoEmailVendor);
	$safeNoResponseCopy = vms_add_dispatch_no_response_explanation();
	$assert(!empty($noResponseEmailOn) && !empty($noResponseEmailOn['included']) && strpos((string) ($noResponseEmailOn['selection_reason'] ?? ''), $safeNoResponseCopy) !== false, 'No-response vendors with email should be included when the no-response toggle is on.');
	$assert(!empty($noResponseNoEmailOn) && empty($noResponseNoEmailOn['included']) && (string) ($noResponseNoEmailOn['selection_reason'] ?? '') === 'No vendor email on file.', 'No-email vendors should remain excluded even when no-response vendors are included.');
	$eligibleOn = vms_add_dispatch_collect_eligible_recipients((array) $foodContext, $builderOn);
	$assert(!empty($candidateById($eligibleOn, $foodNoResponseEmailVendor)), 'No-response vendors with email should be eligible for selected-recipient resolution.');
	$createdSelected = vms_add_dispatch_create_request($foodOpenPlan, $builderOn, array($noResponseEmailOn));
	$assert(!is_wp_error($createdSelected), 'Selected no-response vendor should create a valid ADD request recipient.');
	$createdRequestId = (int) ($createdSelected['request']['id'] ?? 0);
	$assert($createdRequestId > 0, 'Created ADD request should expose its request id.');
	$createdAddRequestIds[] = $createdRequestId;
	$createdResponses = (array) ($createdSelected['responses'] ?? array());
	$createdVendorIds = array_values(array_map(static function (array $row): int {
		return (int) ($row['vendor_id'] ?? 0);
	}, $createdResponses));
	$assert(in_array($foodNoResponseEmailVendor, $createdVendorIds, true), 'Selected no-response vendor should receive a stored ADD response row.');
	$assert(!in_array($foodVendorA, $createdVendorIds, true) && !in_array($foodVendorB, $createdVendorIds, true), 'Unselected eligible vendors should not receive ADD response rows.');
	$emailBody = vms_add_dispatch_email_body_text(array(
		'include_no_response' => 1,
		'message' => 'Test outreach message.',
	), array(
		'token' => 'test-token',
	), (array) $foodContext);
	$assert(strpos($emailBody, $safeNoResponseCopy) !== false, 'No-response outreach email should include the safe explanation copy.');

	$dessertContext = vms_add_dispatch_get_event_plan_context($dessertFullPlan);
	$dessertRow = $rowByType((array) $dessertContext, 'dessert_truck');
	$assert((string) ($dessertRow['status'] ?? '') === 'full' && (int) ($dessertRow['open_needed'] ?? -1) === 0, 'Dessert Vendor capacity 1 / filled 1 should not appear as open.');
	$assert(empty($dessertContext['missing_secondary_types']), 'Full Dessert Vendor group should not be exposed as a missing secondary type.');
	$assert(empty(vms_add_dispatch_dashboard_need_rows((array) $dessertContext, array())), 'Full vendor groups should be hidden from ADD dashboard by default.');
	$assert(!empty(vms_add_dispatch_dashboard_need_rows((array) $dessertContext, array('show_full_groups' => true))), 'Full vendor groups should be available only when the diagnostic filter is enabled.');

	$marketCapacityContext = vms_add_dispatch_get_event_plan_context($marketCapacityOnlyPlan);
	$marketCapacityRow = $rowByType((array) $marketCapacityContext, 'market_vendor');
	$assert((string) ($marketCapacityRow['status'] ?? '') === 'full', 'Market Vendor capacity without target should not be marked Open.');
	$assert(($marketCapacityRow['needed_slots'] ?? null) === null, 'Market Vendor capacity without target should not imply a needed count.');
	$assert((int) ($marketCapacityRow['open_needed'] ?? -1) === 0, 'Market Vendor capacity 50 / filled 12 / no target should not claim 38 required vendors.');
	$assert((int) ($marketCapacityRow['open_capacity'] ?? -1) === 38, 'Market Vendor capacity 50 / filled 12 should still expose 38 open capacity spots.');

	$marketTargetContext = vms_add_dispatch_get_event_plan_context($marketTargetPlan);
	$marketTargetRow = $rowByType((array) $marketTargetContext, 'market_vendor');
	$assert((string) ($marketTargetRow['status'] ?? '') === 'open' && (int) ($marketTargetRow['open_needed'] ?? 0) === 13, 'Market Vendor target 25 / filled 12 should appear as 13 open Market Vendor needs.');

	$marketTargetFullContext = vms_add_dispatch_get_event_plan_context($marketTargetFullPlan);
	$marketTargetFullRow = $rowByType((array) $marketTargetFullContext, 'market_vendor');
	$assert((string) ($marketTargetFullRow['status'] ?? '') === 'full' && (int) ($marketTargetFullRow['open_needed'] ?? -1) === 0, 'Market Vendor target 25 / filled 25 should show full or no open need.');

	$marketOverTargetContext = vms_add_dispatch_get_event_plan_context($marketOverTargetPlan);
	$marketOverTargetRow = $rowByType((array) $marketOverTargetContext, 'market_vendor');
	$assert((string) ($marketOverTargetRow['status'] ?? '') === 'full' && (int) ($marketOverTargetRow['open_needed'] ?? -1) === 0, 'Market Vendor target 25 / filled 30 should show full, not open needed spots.');

	$overContext = vms_add_dispatch_get_event_plan_context($overCapacityPlan);
	$overRow = $rowByType((array) $overContext, 'food_truck');
	$assert((string) ($overRow['status'] ?? '') === 'over_capacity' && empty($overRow['is_open']), 'Over-capacity groups should show Over capacity, not Open.');

	$recentResponses = vms_add_dispatch_get_recent_responses(1);
	$recentRequests = vms_add_dispatch_get_recent_requests(1);
	$assert(is_array($recentResponses), 'Recent responses query should still return an array.');
	$assert(is_array($recentRequests), 'Request History query should still return an array.');
	ob_start();
	vms_add_dispatch_render_dashboard_home();
	$dashboardHtml = (string) ob_get_clean();
	$assert(strpos($dashboardHtml, 'Open Vendor Needs') !== false, 'ADD dashboard should render the Open Vendor Needs table heading.');
	$assert(strpos($dashboardHtml, 'vms-add-quickstart-event') !== false && strpos($dashboardHtml, 'vms-add-quickstart-needs') !== false, 'ADD Quick Start should render grouped by Event Plan with need summaries.');
	$assert(strpos($dashboardHtml, 'No open vendor needs found. Past, cancelled, full, and non-dispatchable Event Plans are hidden by default.') !== false || strpos($dashboardHtml, 'ADD Food Vendor Open Need') !== false, 'ADD dashboard should expose the clarified open-needs empty/open state.');
	ob_start();
	vms_add_dispatch_render_request_builder((array) $foodContext, $builderOn, $eligibleOn);
	$builderHtml = (string) ob_get_clean();
	$assert(strpos($builderHtml, 'Select all eligible') !== false && strpos($builderHtml, 'Clear selected') !== false, 'ADD recipient preview should expose compact select-all controls.');
	$assert(strpos($builderHtml, 'class="vms-add-recipient-checkbox"') !== false, 'Eligible ADD recipients should render as selectable checkboxes.');
	$assert(strpos($builderHtml, 'class="vms-add-recipient-checkbox" name="vendor_ids[]" value="' . (string) $foodNoResponseEmailVendor . '" checked') === false, 'Eligible no-response recipients should not be checked by default.');
	$assert(strpos($builderHtml, 'data-vms-add-state="no-response"') !== false && strpos($builderHtml, 'data-vms-add-base-selectable="1"') !== false, 'ADD recipient rows should expose live eligibility data for no-response recalculation.');
	$assert(strpos($builderHtml, 'data-vms-add-send-button disabled') !== false, 'ADD send button should remain disabled until at least one recipient is selected.');
	$assert(strpos($builderHtml, 'data-vms-add-eligible-count') !== false && strpos($builderHtml, 'data-vms-add-selected-count') !== false, 'ADD live review should expose eligible and selected counts.');
	ob_start();
	vms_add_dispatch_render_request_history(array(), 0);
	$requestHistoryHtml = (string) ob_get_clean();
	$assert(strpos($requestHistoryHtml, 'Request History') !== false, 'Request History renderer should still render.');

	fwrite(STDOUT, "ADD open vendor needs visibility regression: PASS\n");
} catch (Throwable $e) {
	fwrite(STDERR, 'ADD open vendor needs visibility regression: FAIL - ' . $e->getMessage() . "\n");
	exit(1);
} finally {
	$cleanup();
}
