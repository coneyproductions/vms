<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap-wordpress.php';
vms_tests_require_wordpress(__DIR__);

if (!class_exists('BVMGR_Admin_Event_Plans')) {
	require_once dirname(__DIR__) . '/backstage-venue-manager.php';
}
if (!function_exists('vms_render_vendor_availability_list_view')) {
	require_once dirname(__DIR__) . '/includes/admin/vendor-availability.php';
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

	$createVendor = static function (string $title, array $typeSlugs, int $venueId = 0) use ($registerPost): int {
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
		if ($venueId > 0) {
			update_post_meta($vendorId, '_vms_home_venue_id', $venueId);
		}
		return $vendorId;
	};

	$ensureVendorType('food_truck', 'Food Vendor');
	$ensureVendorType('market_vendor', 'Market Vendor');

	$venueId = wp_insert_post(array(
		'post_type' => 'vms_venue',
		'post_status' => 'publish',
		'post_title' => 'Vendor Availability Friday Only Venue',
	), true);
	if (is_wp_error($venueId) || (int) $venueId <= 0) {
		throw new RuntimeException('Failed to create venue fixture.');
	}
	$venueId = $registerPost((int) $venueId);
	update_post_meta($venueId, '_vms_venue_open_days', array(5));
	update_post_meta($venueId, '_vms_venue_open_year_round', '1');

	$tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
	$nextFriday = new DateTimeImmutable('next friday', $tz);
	$openDate = $nextFriday->format('Y-m-d');
	$closedDate = $nextFriday->modify('+1 day')->format('Y-m-d');
	$month = $nextFriday->format('Y-m');

	$foodVendorId = $createVendor('VA UX Food Vendor', array('food_truck'), $venueId);
	$marketVendorId = $createVendor('VA UX Market Vendor', array('market_vendor'), $venueId);
	$uncategorizedVendorId = $createVendor('VA UX Uncategorized Vendor', array(), $venueId);

	$allVendors = vms_vendor_availability_collect_vendors();
	$fixtureVendors = array_values(array_filter($allVendors, static function (array $row) use ($foodVendorId, $marketVendorId, $uncategorizedVendorId): bool {
		return in_array((int) ($row['vendor_id'] ?? 0), array($foodVendorId, $marketVendorId, $uncategorizedVendorId), true);
	}));
	$assert(count($fixtureVendors) === 3, 'Vendor Availability fixtures should be collectible.');

	$baseFilters = array(
		'status' => 'all',
		'type' => '',
		'venue_id' => $venueId,
		'day_filter' => 'all',
		'setup' => 'all',
		'roster' => 'all',
	);
	$busyMap = array();
	$openRows = vms_vendor_availability_day_rows($fixtureVendors, $openDate, $busyMap, $baseFilters);
	$closedRows = vms_vendor_availability_day_rows($fixtureVendors, $closedDate, $busyMap, $baseFilters);
	$assert(count($openRows) === 3 && count($closedRows) === 3, 'Fixture vendors should render on open and closed dates before day filtering.');

	$foodOpenRow = array_values(array_filter($openRows, static function (array $row) use ($foodVendorId): bool {
		return (int) ($row['vendor_id'] ?? 0) === $foodVendorId;
	}))[0] ?? array();
	$foodClosedRow = array_values(array_filter($closedRows, static function (array $row) use ($foodVendorId): bool {
		return (int) ($row['vendor_id'] ?? 0) === $foodVendorId;
	}))[0] ?? array();

	$openLinks = vms_vendor_availability_booking_links((array) $foodOpenRow, $openDate, $baseFilters);
	$closedLinks = vms_vendor_availability_booking_links((array) $foodClosedRow, $closedDate, $baseFilters);
	$assert((string) ($openLinks['url'] ?? '') !== '' && empty($openLinks['override_url']), 'Open venue dates should expose the normal booking link.');
	$assert((string) ($closedLinks['url'] ?? '') === '' && (string) ($closedLinks['override_url'] ?? '') !== '' && ($closedLinks['venue_open'] ?? null) === false, 'Closed venue dates should suppress the normal booking link and expose an override link.');
	$assert(strpos((string) $closedLinks['override_url'], 'vms_override_venue_schedule=1') !== false, 'Closed venue override link should carry an explicit override flag.');

	$assert(vms_vendor_availability_day_matches_filter($openDate, array_merge($baseFilters, array('day_filter' => 'venue_open'))) === true, 'Venue-open filter should include configured open days.');
	$assert(vms_vendor_availability_day_matches_filter($closedDate, array_merge($baseFilters, array('day_filter' => 'venue_open'))) === false, 'Venue-open filter should exclude configured closed days.');
	$assert(vms_vendor_availability_day_matches_filter($closedDate, array_merge($baseFilters, array('day_filter' => 'weekends'))) === true, 'Weekend filter should include Saturday.');
	$assert(vms_vendor_availability_day_matches_filter($closedDate, array_merge($baseFilters, array('day_filter' => 'weekdays'))) === false, 'Weekday filter should exclude Saturday.');

	$typeGroups = vms_vendor_availability_group_rows_by_type($openRows);
	$groupLabels = array_map(static function (array $group): string {
		return (string) ($group['label'] ?? '');
	}, $typeGroups);
	$assert(in_array('Food Vendor', $groupLabels, true), 'Detail grouping should include Food Vendor.');
	$assert(in_array('Market Vendor', $groupLabels, true), 'Detail grouping should include Market Vendor.');
	$assert(in_array('Uncategorized', $groupLabels, true), 'Detail grouping should include Uncategorized.');

	ob_start();
	vms_render_vendor_availability_list_view($closedRows, $closedDate, 'list', $baseFilters);
	$listHtml = (string) ob_get_clean();
	$assert(strpos($listHtml, 'vms-va-type-group-row') !== false, 'List view should render vendor-type group rows.');
	$assert(strpos($listHtml, 'Food Vendor') !== false && strpos($listHtml, 'Market Vendor') !== false, 'List view should expose type labels/badges.');
	$assert(strpos($listHtml, 'Venue closed') !== false && strpos($listHtml, 'Override venue schedule and book anyway') !== false, 'List view should explain closed venue booking suppression and expose override.');
	$assert(strpos($listHtml, 'Start booking') === false, 'Closed venue detail rows should suppress normal Start booking actions.');
	$assert(strpos($listHtml, 'vms-va-actions') !== false, 'Detail rows should render a compact inline action cluster.');
	$assert(strpos($listHtml, 'No response / no setup') !== false, 'No-response vendors without setup should render as one compact status.');
	$assert(strpos($listHtml, 'No availability setup yet') === false, 'Detail rows should not duplicate no-response/no-setup wording in a separate setup column.');
	$assert(strpos($listHtml, '<th>Setup</th>') === false && strpos($listHtml, '<th>Type</th>') === false, 'Detail table should avoid repetitive setup/type columns.');

	$uncategorizedOnly = vms_vendor_availability_filter_vendors($fixtureVendors, array_merge($baseFilters, array('type' => 'uncategorized')));
	$assert(count($uncategorizedOnly) === 1 && (int) ($uncategorizedOnly[0]['vendor_id'] ?? 0) === $uncategorizedVendorId, 'Uncategorized type filter should return only vendors without a type.');

	ob_start();
	vms_render_vendor_availability_month_view($fixtureVendors, array(
		'month' => $month,
		'date' => $closedDate,
		'status' => 'all',
		'type' => '',
		'venue_id' => $venueId,
		'day_filter' => 'venue_open',
		'setup' => 'all',
		'roster' => 'all',
		'q' => '',
	), vms_vendor_availability_month_matrix_rows($fixtureVendors, $month, $busyMap, array(
		'status' => 'all',
		'venue_id' => $venueId,
		'day_filter' => 'venue_open',
	)));
	$monthHtml = (string) ob_get_clean();
	$assert(strpos($monthHtml, 'Hidden by day filter') !== false, 'Month view should visibly suppress days outside the selected day filter.');
	$assert(strpos($monthHtml, 'Expand full day') === false, 'Month view should not render bulky inline full-day rosters.');
	$assert(strpos($monthHtml, 'View full detail') !== false, 'Month view should route full rosters to the detail view.');

	$availableOnlyMonth = vms_vendor_availability_month_matrix_rows($fixtureVendors, $month, $busyMap, array_merge($baseFilters, array('status' => 'available')));
	$allStatusMonth = vms_vendor_availability_month_matrix_rows($fixtureVendors, $month, $busyMap, array_merge($baseFilters, array('status' => 'all')));
	$assert((int) ($availableOnlyMonth[$openDate]['summary']['total'] ?? -1) === 0, 'Availability status filter should change month cell result sets.');
	$assert((int) ($allStatusMonth[$openDate]['summary']['total'] ?? 0) === 3, 'Reset/default status should restore all matching vendors in month cells.');

	fwrite(STDOUT, "Vendor Availability UX regression: PASS\n");
} catch (Throwable $e) {
	fwrite(STDERR, 'Vendor Availability UX regression: FAIL - ' . $e->getMessage() . "\n");
	exit(1);
} finally {
	$cleanup();
}
