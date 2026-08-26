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
$createdPlanIds = array();
$originalPost = $_POST ?? array();

$registerPost = static function (int $postId) use (&$createdPosts): int {
	$createdPosts[] = $postId;
	return $postId;
};

$registerTerm = static function (int $termId) use (&$createdTerms): int {
	$createdTerms[] = $termId;
	return $termId;
};

$registerPlan = static function (int $planId) use (&$createdPlanIds): int {
	$createdPlanIds[] = $planId;
	return $planId;
};

$cleanup = static function () use (&$createdPosts, &$createdTerms, &$createdPlanIds, &$originalPost): void {
	global $wpdb;

	foreach (array_reverse($createdPlanIds) as $planId) {
		$slotTable = function_exists('vms_staffing_table_name') ? vms_staffing_table_name('event_slots') : '';
		$assignmentTable = function_exists('vms_staffing_table_name') ? vms_staffing_table_name('assignments') : '';
		$rollupTable = function_exists('vms_staffing_table_name') ? vms_staffing_table_name('rollups') : '';
		$auditTable = function_exists('vms_staffing_table_name') ? vms_staffing_table_name('audit') : '';
		$slotIds = array();
		if ($slotTable !== '') {
			$slotIds = $wpdb->get_col($wpdb->prepare("SELECT slot_id FROM {$slotTable} WHERE event_plan_id = %d", (int) $planId));
			$wpdb->delete($slotTable, array('event_plan_id' => (int) $planId), array('%d'));
		}
		if ($assignmentTable !== '' && !empty($slotIds)) {
			$in = implode(',', array_map('absint', $slotIds));
			$wpdb->query("DELETE FROM {$assignmentTable} WHERE slot_id IN ({$in})");
		}
		if ($rollupTable !== '') {
			$wpdb->delete($rollupTable, array('event_plan_id' => (int) $planId), array('%d'));
		}
		if ($auditTable !== '') {
			$wpdb->delete($auditTable, array('event_plan_id' => (int) $planId), array('%d'));
		}
	}

	foreach (array_reverse($createdPosts) as $postId) {
		wp_delete_post((int) $postId, true);
	}
	foreach (array_reverse($createdTerms) as $termId) {
		wp_delete_term((int) $termId, 'vms_staff_role');
	}
	$_POST = $originalPost;
};

try {
	wp_set_current_user(1);

	$createRole = static function (string $name, string $slug, array $meta) use ($registerTerm): int {
		$created = wp_insert_term($name, 'vms_staff_role', array('slug' => $slug));
		if (is_wp_error($created) || empty($created['term_id'])) {
			throw new RuntimeException('Failed to create test staff role: ' . $name);
		}

		$roleId = $registerTerm((int) $created['term_id']);
		if (function_exists('vms_staffing_role_meta_save')) {
			vms_staffing_role_meta_save($roleId, $meta);
		}

		return $roleId;
	};

	$createStaff = static function (string $title, array $roleIds, array $qualifications = array()) use ($registerPost): int {
		$staffId = wp_insert_post(array(
			'post_type' => 'vms_staff',
			'post_status' => 'publish',
			'post_title' => $title,
		), true);
		if (is_wp_error($staffId) || (int) $staffId <= 0) {
			throw new RuntimeException('Failed to create test staff profile: ' . $title);
		}

		$staffId = $registerPost((int) $staffId);
		if (!empty($roleIds)) {
			wp_set_post_terms($staffId, array_values(array_map('absint', $roleIds)), 'vms_staff_role', false);
		}
		if (function_exists('vms_staffing_save_staff_qualifications')) {
			vms_staffing_save_staff_qualifications($staffId, $qualifications);
		}

		return $staffId;
	};

	$createPlan = static function (string $title) use ($registerPost, $registerPlan): int {
		$planId = wp_insert_post(array(
			'post_type' => 'vms_event_plan',
			'post_status' => 'draft',
			'post_title' => $title,
		), true);
		if (is_wp_error($planId) || (int) $planId <= 0) {
			throw new RuntimeException('Failed to create test Event Plan: ' . $title);
		}

		$planId = (int) $planId;
		$registerPost($planId);
		$registerPlan($planId);
		update_post_meta($planId, '_vms_event_date', '2026-06-15');
		update_post_meta($planId, '_vms_start_time', '18:00');
		update_post_meta($planId, '_vms_end_time', '22:00');
		update_post_meta($planId, '_vms_venue_id', 0);
		update_post_meta($planId, '_vms_event_plan_status', 'draft');

		return $planId;
	};

	$invokeStaffContext = static function (int $planId): array {
		$reflection = new ReflectionClass('BVMGR_Admin_Event_Plans');
		$admin = $reflection->newInstanceWithoutConstructor();
		$method = $reflection->getMethod('get_event_plan_staff_render_context');
		$method->setAccessible(true);
		$staffAssignments = get_post_meta($planId, '_vms_staff_assignments', true);
		return (array) $method->invoke($admin, $planId, is_array($staffAssignments) ? $staffAssignments : array());
	};

	$runSave = static function (int $planId, array $overrides = array()): void {
		$defaults = array(
			'vms_event_plan_details_nonce' => wp_create_nonce('vms_save_event_plan_details'),
			'post_ID' => $planId,
			'original_post_status' => 'draft',
			'vms_event_plan_action' => 'save_draft',
			'vms_event_date' => '2026-06-15',
			'vms_start_time' => '18:00',
			'vms_end_time' => '22:00',
			'vms_venue_id' => '0',
			'vms_staff_assignments_present' => '1',
			'vms_staffing_roles_present' => '1',
		);
		$_POST = array_merge($defaults, $overrides);

		$reflection = new ReflectionClass('BVMGR_Admin_Event_Plans');
		$admin = $reflection->newInstanceWithoutConstructor();
		$admin->save_event_plan_meta($planId, get_post($planId));
		clean_post_cache($planId);
	};

	$suffix = strtolower(wp_generate_password(6, false, false));
	$barRoleId = $createRole(
		'QA Bar Role ' . $suffix,
		'qa-bar-role-' . $suffix,
		array(
			'is_active' => 1,
			'default_headcount' => 1,
			'qualification_check_mode' => 'hard_block',
			'required_qualifications' => array(
				array('name' => 'TABC Certified', 'mode' => 'hard_block'),
			),
		)
	);
	$cleanupRoleId = $createRole(
		'QA Cleanup Role ' . $suffix,
		'qa-cleanup-role-' . $suffix,
		array(
			'is_active' => 1,
			'default_headcount' => 1,
			'qualification_check_mode' => 'soft_block',
			'required_qualifications' => array(
				array('name' => 'Cleanup Orientation', 'mode' => 'soft_block'),
			),
		)
	);

	$barQualifiedId = $createStaff(
		'QA Staff Bar Qualified ' . $suffix,
		array($barRoleId),
		array(
			array(
				'name' => 'TABC Certified',
				'authority' => 'Texas ABC',
				'credential_number' => 'QABAR-' . $suffix,
				'issue_date' => '2026-01-01',
				'expiration_date' => '2027-01-01',
				'status' => 'active',
			),
		)
	);
	$barExpiredId = $createStaff(
		'QA Staff Bar Expired ' . $suffix,
		array($barRoleId),
		array(
			array(
				'name' => 'TABC Certified',
				'authority' => 'Texas ABC',
				'credential_number' => 'QAEXP-' . $suffix,
				'issue_date' => '2024-01-01',
				'expiration_date' => '2025-01-01',
				'status' => 'active',
			),
		)
	);
	$cleanupOnlyId = $createStaff(
		'QA Staff Cleanup Only ' . $suffix,
		array($cleanupRoleId),
		array()
	);

	$planId = $createPlan('QA Staffing Eligibility Harness ' . $suffix);

	$seedResult = vms_staffing_save_event_roles_matrix(
		$planId,
		array(
			$barRoleId => 1,
			$cleanupRoleId => 1,
		),
		array(
			$barRoleId => array($barExpiredId),
			$cleanupRoleId => array($cleanupOnlyId),
		),
		array(
			$barRoleId => 'absolute',
			$cleanupRoleId => 'absolute',
		),
		array(
			$barRoleId => '18:00',
			$cleanupRoleId => '18:00',
		),
		array(
			$barRoleId => '22:00',
			$cleanupRoleId => '22:00',
		),
		array(),
		array(),
		array(),
		array(),
		array(),
		1
	);
	$assert(!empty($seedResult['ok']), 'Failed to seed staffing slots for the eligibility harness.');

	$barQualifiedStatus = vms_staffing_staff_candidate_status_for_role($barQualifiedId, $barRoleId);
	$barExpiredStatus = vms_staffing_staff_candidate_status_for_role($barExpiredId, $barRoleId);
	$cleanupStatus = vms_staffing_staff_candidate_status_for_role($cleanupOnlyId, $cleanupRoleId);
	$assert(!empty($barQualifiedStatus['eligible']), 'Qualified bar staff should be eligible for the bar role.');
	$assert(empty($barExpiredStatus['eligible']) && !empty($barExpiredStatus['qualification_hard_blocked']), 'Expired bar staff should be ineligible because of the hard-block certification rule.');
	$assert(!empty($cleanupStatus['eligible']) && empty($cleanupStatus['qualification_hard_blocked']), 'Soft-block cleanup qualification gaps should not remove cleanup candidates.');

	$context = $invokeStaffContext($planId);
	$barRoleStaff = isset($context['staff_by_role'][$barRoleId]) && is_array($context['staff_by_role'][$barRoleId]) ? $context['staff_by_role'][$barRoleId] : array();
	$barRoleIds = array_values(array_map(static function ($post): int {
		return (int) ($post->ID ?? 0);
	}, $barRoleStaff));
	$cleanupRoleStaff = isset($context['staff_by_role'][$cleanupRoleId]) && is_array($context['staff_by_role'][$cleanupRoleId]) ? $context['staff_by_role'][$cleanupRoleId] : array();
	$cleanupRoleIds = array_values(array_map(static function ($post): int {
		return (int) ($post->ID ?? 0);
	}, $cleanupRoleStaff));

	$assert((int) ($context['staff_eligible_counts_by_role'][$barRoleId] ?? 0) === 1, 'Bar role should count only one currently eligible staff member.');
	$assert(in_array($barQualifiedId, $barRoleIds, true), 'Qualified bar staff should appear in the bar candidate list.');
	$assert(in_array($barExpiredId, $barRoleIds, true), 'Assigned but expired bar staff should remain visible in the bar candidate list.');
	$assert(!in_array($cleanupOnlyId, $barRoleIds, true), 'Cleanup-only staff should not appear in the bar candidate list.');
	$assert(in_array($cleanupOnlyId, $cleanupRoleIds, true), 'Cleanup-only staff should appear in the cleanup candidate list.');

	$runSave($planId, array(
		'vms_staff_assignments' => array(
			$barRoleId => array((string) $barExpiredId, (string) $cleanupOnlyId),
			$cleanupRoleId => array((string) $cleanupOnlyId),
		),
		'vms_staff_role_headcount' => array(
			$barRoleId => '1',
			$cleanupRoleId => '1',
		),
		'vms_staff_role_activation_threshold' => array(
			$barRoleId => '0',
			$cleanupRoleId => '0',
		),
		'vms_staff_role_time_mode' => array(
			$barRoleId => 'absolute',
			$cleanupRoleId => 'absolute',
		),
		'vms_staff_role_shift_start' => array(
			$barRoleId => '18:00',
			$cleanupRoleId => '18:00',
		),
		'vms_staff_role_shift_end' => array(
			$barRoleId => '22:00',
			$cleanupRoleId => '22:00',
		),
	));

	$assignedAfterSave = vms_staffing_get_event_assigned_staff_map($planId);
	$barAssignedAfterSave = isset($assignedAfterSave[$barRoleId]) && is_array($assignedAfterSave[$barRoleId]) ? array_values(array_map('absint', $assignedAfterSave[$barRoleId])) : array();
	$assert(in_array($barExpiredId, $barAssignedAfterSave, true), 'Existing assigned but now-ineligible bar staff should remain assigned after save.');
	$assert(!in_array($cleanupOnlyId, $barAssignedAfterSave, true), 'Newly posted ineligible bar staff should be blocked from assignment.');

	fwrite(STDOUT, "event plan staff eligibility regression tests: PASS\n");
} catch (Throwable $e) {
	$cleanup();
	fwrite(STDERR, $e->getMessage() . PHP_EOL);
	exit(1);
}

$cleanup();
