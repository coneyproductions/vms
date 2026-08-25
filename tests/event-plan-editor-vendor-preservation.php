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

	$ensureVendorType = static function (string $slug, string $name) use ($registerTerm): string {
		$existing = get_term_by('slug', $slug, 'vms_vendor_type');
		if ($existing instanceof WP_Term) {
			return (string) $existing->slug;
		}

		$created = wp_insert_term($name, 'vms_vendor_type', array('slug' => $slug));
		if (is_wp_error($created) || empty($created['term_id'])) {
			throw new RuntimeException('Failed to create test vendor type term: ' . $slug);
		}
		$registerTerm(array(
			'term_id' => (int) $created['term_id'],
			'created' => true,
		));

		return $slug;
	};

	$createVendor = static function (string $title, string $typeSlug = '') use ($registerPost): int {
		$vendorId = wp_insert_post(array(
			'post_type' => 'vms_vendor',
			'post_status' => 'publish',
			'post_title' => $title,
		), true);
		if (is_wp_error($vendorId) || (int) $vendorId <= 0) {
			throw new RuntimeException('Failed to create test vendor: ' . $title);
		}
		$vendorId = $registerPost((int) $vendorId);
		if ($typeSlug !== '') {
			wp_set_object_terms($vendorId, array($typeSlug), 'vms_vendor_type', false);
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
			throw new RuntimeException('Failed to create test Event Plan: ' . $title);
		}
		return $registerPost((int) $planId);
	};

	$getPrimaryLineupEntry = static function (int $planId): array {
		$entries = function_exists('vms_get_event_plan_lineup_entries')
			? (array) vms_get_event_plan_lineup_entries($planId)
			: (array) get_post_meta($planId, '_vms_lineup_entries_v1', true);
		foreach ($entries as $entry) {
			if (!is_array($entry)) {
				continue;
			}
			if (sanitize_key((string) ($entry['role'] ?? '')) === 'primary') {
				return $entry;
			}
		}
		return array();
	};

	$seedPlanState = static function (int $planId, int $primaryVendorId, string $secondaryType, array $secondaryVendorIds) use ($getPrimaryLineupEntry): void {
		update_post_meta($planId, '_vms_event_plan_status', 'published');
		update_post_meta($planId, '_vms_event_date', '2026-06-12');
		update_post_meta($planId, '_vms_start_time', '19:00');
		update_post_meta($planId, '_vms_end_time', '21:00');
		update_post_meta($planId, '_vms_venue_id', 0);
		update_post_meta($planId, '_vms_band_vendor_id', $primaryVendorId);
		update_post_meta($planId, '_vms_secondary_vendor_type', $secondaryType);
		update_post_meta($planId, '_vms_secondary_vendor_ids', array_values($secondaryVendorIds));
		delete_post_meta($planId, '_vms_secondary_vendor_id');
		foreach ($secondaryVendorIds as $secondaryVendorId) {
			add_post_meta($planId, '_vms_secondary_vendor_id', (int) $secondaryVendorId, false);
		}
		update_post_meta($planId, '_vms_staff_assignments', array(
			97 => array(2443),
			85 => array(1248),
		));
		update_post_meta($planId, '_vms_ticketing_enabled_override', 'on');
		update_post_meta($planId, '_vms_ticket_ui_layout_override', 'progressive');
		update_post_meta($planId, '_vms_ticket_ui_addons_heading_override', 'Fire Pits & Tables');
		if (function_exists('vms_save_event_plan_lineup_entries')) {
			vms_save_event_plan_lineup_entries(
				$planId,
				array(
					'primary' => array(
						'row_id' => 'lineup_test_primary',
						'role' => 'primary',
						'sort_order' => 0,
						'vendor_id' => $primaryVendorId,
						'set_start' => '19:00',
						'set_end' => '21:00',
						'show_public' => '1',
						'show_portal' => '1',
					),
				),
				array(
					'legacy_primary_vendor_id' => $primaryVendorId,
					'event_start' => '19:00',
					'event_end' => '21:00',
					'event_date' => '2026-06-12',
					'venue_id' => 0,
				)
			);
		}

		$primaryEntry = $getPrimaryLineupEntry($planId);
		if (empty($primaryEntry)) {
			throw new RuntimeException('Failed to seed the primary lineup row.');
		}
	};

	$runSave = static function (int $planId, array $overrides = array()): void {
		$defaults = array(
			'vms_event_plan_details_nonce' => wp_create_nonce('vms_save_event_plan_details'),
			'post_ID' => $planId,
			'original_post_status' => 'publish',
			'vms_event_plan_action' => 'save_draft',
			'vms_staffing_lazy_unloaded' => '1',
			'vms_secondary_vendors_lazy_unloaded' => '1',
		);
		$_POST = array_merge($defaults, $overrides);

		$reflection = new ReflectionClass('VMS_Admin_Event_Plans');
		/** @var VMS_Admin_Event_Plans $admin */
		$admin = $reflection->newInstanceWithoutConstructor();
		$admin->save_event_plan_meta($planId, get_post($planId));
		clean_post_cache($planId);
	};

	$runSecondaryVendorModuleSave = static function (int $planId, array $request = array()) use ($assert): array {
		$assert(function_exists('vms_event_plan_save_secondary_vendors_module'), 'Expected isolated Secondary Vendors module save helper to exist.');
		$result = vms_event_plan_save_secondary_vendors_module($planId, $request);
		if ($result instanceof WP_Error) {
			throw new RuntimeException('Secondary Vendors module save failed: ' . $result->get_error_message());
		}
		clean_post_cache($planId);
		return is_array($result) ? $result : array();
	};

	$normalizeValue = static function ($value) use (&$normalizeValue) {
		if (is_array($value)) {
			$isList = array_keys($value) === range(0, count($value) - 1);
			$next = array();
			foreach ($value as $key => $child) {
				$next[$key] = $normalizeValue($child);
			}
			if ($isList) {
				usort($next, static function ($left, $right): int {
					return strcmp(wp_json_encode($left), wp_json_encode($right));
				});
			} else {
				ksort($next);
			}
			return $next;
		}
		return $value;
	};

	$getRelevantState = static function (int $planId) use ($normalizeValue): array {
		$secondaryIds = get_post_meta($planId, '_vms_secondary_vendor_ids', true);
		if (!is_array($secondaryIds)) {
			$secondaryIds = array();
		}
		$staffAssignments = get_post_meta($planId, '_vms_staff_assignments', true);
		if (!is_array($staffAssignments)) {
			$staffAssignments = array();
		}

		return array(
			'core_details' => $normalizeValue(array(
				'event_date' => (string) get_post_meta($planId, '_vms_event_date', true),
				'start_time' => (string) get_post_meta($planId, '_vms_start_time', true),
				'end_time' => (string) get_post_meta($planId, '_vms_end_time', true),
				'venue_id' => (int) get_post_meta($planId, '_vms_venue_id', true),
			)),
			'secondary_vendors' => $normalizeValue(array(
				'type' => (string) get_post_meta($planId, '_vms_secondary_vendor_type', true),
				'ids' => array_values(array_unique(array_map('absint', $secondaryIds))),
				'index_ids' => array_values(array_unique(array_map('absint', (array) get_post_meta($planId, '_vms_secondary_vendor_id', false)))),
			)),
			'staffing' => $normalizeValue(array(
				'assignments' => $staffAssignments,
			)),
			'ticketing' => $normalizeValue(array(
				'enabled_override' => (string) get_post_meta($planId, '_vms_ticketing_enabled_override', true),
				'ui_layout_override' => (string) get_post_meta($planId, '_vms_ticket_ui_layout_override', true),
				'addons_heading_override' => (string) get_post_meta($planId, '_vms_ticket_ui_addons_heading_override', true),
			)),
		);
	};

	$diffState = static function (array $before, array $after): array {
		$diff = array();
		foreach ($after as $module => $afterValue) {
			$beforeValue = $before[$module] ?? null;
			if (wp_json_encode($beforeValue) === wp_json_encode($afterValue)) {
				continue;
			}
			if (is_array($afterValue) && is_array($beforeValue)) {
				$moduleDiff = array();
				foreach ($afterValue as $field => $fieldValue) {
					$fieldBefore = $beforeValue[$field] ?? null;
					if (wp_json_encode($fieldBefore) === wp_json_encode($fieldValue)) {
						continue;
					}
					$moduleDiff[$field] = array(
						'before' => $fieldBefore,
						'after' => $fieldValue,
					);
				}
				if (!empty($moduleDiff)) {
					$diff[$module] = $moduleDiff;
				}
				continue;
			}
			$diff[$module] = array(
				'before' => $beforeValue,
				'after' => $afterValue,
			);
		}
		return $diff;
	};

	$printDiff = static function (string $label, array $diff): void {
		fwrite(STDOUT, $label . " meta diff:\n");
		if (empty($diff)) {
			fwrite(STDOUT, "{}\n");
			return;
		}
		fwrite(STDOUT, wp_json_encode($diff, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
	};

	$foodTruckSlug = $ensureVendorType('food_truck', 'Food Truck');
	$photoSlug = $ensureVendorType('photographer', 'Photographer');

	$primaryVendorId = $createVendor('VMS Test Primary Vendor');
	$newPrimaryVendorId = $createVendor('VMS Test New Primary Vendor');
	$foodTruckVendorId = $createVendor('VMS Test Food Truck Vendor', $foodTruckSlug);
	$photoVendorId = $createVendor('VMS Test Photographer Vendor', $photoSlug);

	$planId = $createPlan('VMS Event Plan Preservation Harness');

	$seedPlanState($planId, $primaryVendorId, $foodTruckSlug, array($foodTruckVendorId));

	$runSave($planId, array(
		'vms_band_vendor_id' => '',
		'vms_start_time' => '19:00',
		'vms_end_time' => '21:00',
		'vms_event_date' => '2026-06-12',
		'vms_venue_id' => '0',
		'vms_lineup_entries' => array(
			'primary' => array(
				'row_id' => 'lineup_test_primary',
				'role' => 'primary',
				'sort_order' => 0,
				'vendor_id' => '',
				'set_start' => '19:00',
				'set_end' => '21:00',
				'show_public' => '1',
				'show_portal' => '1',
			),
		),
	));
	$assert((int) get_post_meta($planId, '_vms_band_vendor_id', true) === $primaryVendorId, 'Blank posted primary vendor cleared the saved primary vendor without clear intent.');
	$primaryEntry = $getPrimaryLineupEntry($planId);
	$assert((int) ($primaryEntry['vendor_id'] ?? 0) === $primaryVendorId, 'Blank posted primary vendor cleared the primary lineup vendor without clear intent.');
	$assert((string) ($primaryEntry['set_start'] ?? '') === '19:00' && (string) ($primaryEntry['set_end'] ?? '') === '21:00', 'Primary lineup times were not preserved when blank primary vendor was posted.');

	$seedPlanState($planId, $primaryVendorId, $foodTruckSlug, array($foodTruckVendorId));
	$runSave($planId, array(
		'vms_band_vendor_id' => '',
		'vms_clear_primary_vendor' => '1',
		'vms_clear_lineup_primary_vendor' => '1',
		'vms_start_time' => '19:00',
		'vms_end_time' => '21:00',
		'vms_event_date' => '2026-06-12',
		'vms_venue_id' => '0',
		'vms_lineup_entries' => array(
			'primary' => array(
				'row_id' => 'lineup_test_primary',
				'role' => 'primary',
				'sort_order' => 0,
				'vendor_id' => '',
				'set_start' => '19:00',
				'set_end' => '21:00',
				'show_public' => '1',
				'show_portal' => '1',
			),
		),
	));
	$assert((int) get_post_meta($planId, '_vms_band_vendor_id', true) === 0, 'Explicit primary vendor clear did not clear the saved primary vendor.');
	$primaryEntry = $getPrimaryLineupEntry($planId);
	$assert(array_key_exists('vendor_id', $primaryEntry) && (int) ($primaryEntry['vendor_id'] ?? 0) === 0, 'Explicit primary vendor clear did not clear the primary lineup vendor.');

	$seedPlanState($planId, $primaryVendorId, $foodTruckSlug, array($foodTruckVendorId));
	$runSave($planId, array(
		'vms_band_vendor_id' => (string) $newPrimaryVendorId,
		'vms_start_time' => '19:00',
		'vms_end_time' => '21:00',
		'vms_event_date' => '2026-06-12',
		'vms_venue_id' => '0',
		'vms_lineup_entries' => array(
			'primary' => array(
				'row_id' => 'lineup_test_primary',
				'role' => 'primary',
				'sort_order' => 0,
				'vendor_id' => (string) $newPrimaryVendorId,
				'set_start' => '19:00',
				'set_end' => '21:00',
				'show_public' => '1',
				'show_portal' => '1',
			),
		),
	));
	$assert((int) get_post_meta($planId, '_vms_band_vendor_id', true) === $newPrimaryVendorId, 'Valid primary vendor reassignment did not persist.');
	$primaryEntry = $getPrimaryLineupEntry($planId);
	$assert((int) ($primaryEntry['vendor_id'] ?? 0) === $newPrimaryVendorId, 'Valid primary vendor reassignment did not update the primary lineup vendor.');

	$seedPlanState($planId, $primaryVendorId, $foodTruckSlug, array($foodTruckVendorId));
	$runSave($planId, array(
		'vms_secondary_vendor_type' => '',
		'vms_secondary_vendor_ids' => array(''),
	));
	$assert((string) get_post_meta($planId, '_vms_secondary_vendor_type', true) === $foodTruckSlug, 'Blank secondary vendor type cleared the saved type without clear intent.');
	$assert((array) get_post_meta($planId, '_vms_secondary_vendor_ids', true) === array($foodTruckVendorId), 'Blank secondary vendor IDs cleared the saved vendor selections without clear intent.');

	$seedPlanState($planId, $primaryVendorId, $foodTruckSlug, array($foodTruckVendorId));
	$runSave($planId, array(
		'vms_clear_secondary_vendors' => '1',
		'vms_secondary_vendor_type' => '',
		'vms_secondary_vendor_ids' => array(''),
	));
	$assert((string) get_post_meta($planId, '_vms_secondary_vendor_type', true) === '', 'Explicit secondary vendor clear did not clear the saved type.');
	$assert(get_post_meta($planId, '_vms_secondary_vendor_ids', true) === '', 'Explicit secondary vendor clear did not clear the saved vendor selections.');
	$assert(get_post_meta($planId, '_vms_secondary_vendor_id', false) === array(), 'Explicit secondary vendor clear did not clear the secondary vendor index.');

	$seedPlanState($planId, $primaryVendorId, $foodTruckSlug, array($foodTruckVendorId));
	$runSave($planId, array(
		'vms_secondary_vendor_type' => $photoSlug,
		'vms_secondary_vendor_ids' => array((string) $photoVendorId),
	));
	$assert((string) get_post_meta($planId, '_vms_secondary_vendor_type', true) === $photoSlug, 'Valid secondary vendor type reassignment did not persist.');
	$assert((array) get_post_meta($planId, '_vms_secondary_vendor_ids', true) === array($photoVendorId), 'Valid secondary vendor reassignment did not persist the selected vendor IDs.');

	$seedPlanState($planId, $primaryVendorId, $foodTruckSlug, array($foodTruckVendorId));
	$runSave($planId, array(
		'vms_event_plan_action' => 'save_draft',
		'vms_secondary_vendors_lazy_unloaded' => '1',
		'vms_staffing_lazy_unloaded' => '1',
	));
	$assert((int) get_post_meta($planId, '_vms_band_vendor_id', true) === $primaryVendorId, 'Deferred/unloaded save altered the saved primary vendor.');
	$assert((string) get_post_meta($planId, '_vms_secondary_vendor_type', true) === $foodTruckSlug, 'Deferred/unloaded save altered the saved secondary vendor type.');
	$assert((array) get_post_meta($planId, '_vms_secondary_vendor_ids', true) === array($foodTruckVendorId), 'Deferred/unloaded save altered the saved secondary vendor IDs.');
	$assert((array) get_post_meta($planId, '_vms_staff_assignments', true) === array(
		97 => array(2443),
		85 => array(1248),
	), 'Deferred/unloaded save altered the saved staff assignments.');
	$assert((string) get_post_meta($planId, '_vms_ticketing_enabled_override', true) === 'on', 'Deferred/unloaded save altered ticketing meta.');

	$seedPlanState($planId, $primaryVendorId, $foodTruckSlug, array($foodTruckVendorId));
	$coreSaveBefore = $getRelevantState($planId);
	$runSave($planId, array(
		'vms_event_date' => '2026-06-19',
		'vms_start_time' => '19:00',
		'vms_end_time' => '21:00',
		'vms_venue_id' => '0',
		'vms_secondary_vendors_module_detached' => '1',
		'vms_secondary_vendor_type' => '',
		'vms_secondary_vendor_ids' => array(''),
	));
	$coreSaveAfter = $getRelevantState($planId);
	$coreSaveDiff = $diffState($coreSaveBefore, $coreSaveAfter);
	$printDiff('Core save with detached Secondary Vendors', $coreSaveDiff);
	$assert(($coreSaveAfter['core_details']['event_date'] ?? '') === '2026-06-19', 'Core details save did not persist the updated event date.');
	$assert(($coreSaveAfter['secondary_vendors'] ?? array()) === ($coreSaveBefore['secondary_vendors'] ?? array()), 'Core details save altered Secondary Vendors while the detached module was loaded.');
	$assert(($coreSaveAfter['staffing'] ?? array()) === ($coreSaveBefore['staffing'] ?? array()), 'Core details save altered staffing while the detached Secondary Vendors module was loaded.');
	$assert(($coreSaveAfter['ticketing'] ?? array()) === ($coreSaveBefore['ticketing'] ?? array()), 'Core details save altered ticketing overrides while the detached Secondary Vendors module was loaded.');
	$assert(isset($coreSaveDiff['core_details']) && count($coreSaveDiff) === 1, 'Core details save should only change the core module diff.');

	$seedPlanState($planId, $primaryVendorId, $foodTruckSlug, array($foodTruckVendorId));
	$moduleSaveBefore = $getRelevantState($planId);
	$moduleSaveResult = $runSecondaryVendorModuleSave($planId, array(
		'vms_secondary_vendor_type' => $photoSlug,
		'vms_secondary_vendor_ids' => array((string) $photoVendorId),
		'vms_clear_secondary_vendors' => '0',
	));
	$moduleSaveAfter = $getRelevantState($planId);
	$moduleSaveDiff = $diffState($moduleSaveBefore, $moduleSaveAfter);
	$printDiff('Secondary Vendors module save', $moduleSaveDiff);
	$assert(!empty($moduleSaveResult['changed']), 'Expected the isolated Secondary Vendors module save to report a changed state.');
	$assert(($moduleSaveAfter['secondary_vendors']['type'] ?? '') === $photoSlug, 'Isolated Secondary Vendors save did not persist the new vendor type.');
	$assert(($moduleSaveAfter['secondary_vendors']['ids'] ?? array()) === array($photoVendorId), 'Isolated Secondary Vendors save did not persist the new vendor IDs.');
	$assert(($moduleSaveAfter['core_details'] ?? array()) === ($moduleSaveBefore['core_details'] ?? array()), 'Isolated Secondary Vendors save altered core details.');
	$assert(($moduleSaveAfter['staffing'] ?? array()) === ($moduleSaveBefore['staffing'] ?? array()), 'Isolated Secondary Vendors save altered staffing data.');
	$assert(($moduleSaveAfter['ticketing'] ?? array()) === ($moduleSaveBefore['ticketing'] ?? array()), 'Isolated Secondary Vendors save altered ticketing overrides.');
	$assert(isset($moduleSaveDiff['secondary_vendors']) && count($moduleSaveDiff) === 1, 'Isolated Secondary Vendors save should only change the Secondary Vendors module diff.');

	fwrite(STDOUT, "Event Plan editor vendor preservation test passed.\n");
	$cleanup();
	exit(0);
} catch (Throwable $error) {
	$cleanup();
	fwrite(STDERR, $error->getMessage() . "\n");
	exit(1);
}
