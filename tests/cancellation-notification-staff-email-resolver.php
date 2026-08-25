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

if (!function_exists('vms_cancellation_run_step')) {
	require_once dirname(__DIR__) . '/backstage-venue-manager.php';
}

$assert = static function (bool $condition, string $message): void {
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
};

$createdPosts = array();
$createdUsers = array();
$createdTerms = array();
$createdPlanIds = array();

$registerPost = static function (int $postId) use (&$createdPosts): int {
	$createdPosts[] = $postId;
	return $postId;
};

$registerUser = static function (int $userId) use (&$createdUsers): int {
	$createdUsers[] = $userId;
	return $userId;
};

$registerTerm = static function (int $termId) use (&$createdTerms): int {
	$createdTerms[] = $termId;
	return $termId;
};

$registerPlan = static function (int $planId) use (&$createdPlanIds): int {
	$createdPlanIds[] = $planId;
	return $planId;
};

$cleanup = static function () use (&$createdPosts, &$createdUsers, &$createdTerms, &$createdPlanIds): void {
	global $wpdb;

	foreach (array_reverse($createdPlanIds) as $planId) {
		$slotTable = function_exists('vms_staffing_table_name') ? vms_staffing_table_name('event_slots') : '';
		$assignmentTable = function_exists('vms_staffing_table_name') ? vms_staffing_table_name('assignments') : '';
		$rollupTable = function_exists('vms_staffing_table_name') ? vms_staffing_table_name('rollups') : '';
		$auditTable = function_exists('vms_staffing_table_name') ? vms_staffing_table_name('audit') : '';
		$slotIds = array();
		if ($slotTable !== '') {
			$slotIds = $wpdb->get_col($wpdb->prepare("SELECT slot_id FROM {$slotTable} WHERE event_plan_id = %d", (int) $planId));
		}
		if ($assignmentTable !== '' && !empty($slotIds)) {
			$in = implode(',', array_map('absint', $slotIds));
			$wpdb->query("DELETE FROM {$assignmentTable} WHERE slot_id IN ({$in})");
		}
		if ($slotTable !== '') {
			$wpdb->delete($slotTable, array('event_plan_id' => (int) $planId), array('%d'));
		}
		if ($rollupTable !== '') {
			$wpdb->delete($rollupTable, array('event_plan_id' => (int) $planId), array('%d'));
		}
		if ($auditTable !== '') {
			$wpdb->delete($auditTable, array('event_plan_id' => (int) $planId), array('%d'));
		}
	}

	foreach (array_reverse($createdUsers) as $userId) {
		if ($userId > 0 && function_exists('wp_delete_user')) {
			wp_delete_user($userId);
		}
	}

	foreach (array_reverse($createdPosts) as $postId) {
		wp_delete_post((int) $postId, true);
	}

	foreach (array_reverse($createdTerms) as $termId) {
		wp_delete_term((int) $termId, 'vms_staff_role');
	}
};

try {
	wp_set_current_user(1);
	add_filter('vms_cancellation_notifications_dry_run', '__return_true');

	$suffix = strtolower(wp_generate_password(8, false, false));

	$createVendor = static function (string $title, string $email) use ($registerPost): int {
		$vendorId = wp_insert_post(array(
			'post_type' => 'vms_vendor',
			'post_status' => 'draft',
			'post_title' => $title,
		), true);
		if (is_wp_error($vendorId) || (int) $vendorId <= 0) {
			throw new RuntimeException('Failed to create test vendor: ' . $title);
		}

		$vendorId = $registerPost((int) $vendorId);
		$key = function_exists('vms_meta_key') ? (vms_meta_key('vendor', 'primary_email') ?: '_vms_vendor_primary_email') : '_vms_vendor_primary_email';
		update_post_meta($vendorId, $key, $email);

		return $vendorId;
	};

	$createStaff = static function (string $title) use ($registerPost): int {
		$staffId = wp_insert_post(array(
			'post_type' => 'vms_staff',
			'post_status' => 'draft',
			'post_title' => $title,
		), true);
		if (is_wp_error($staffId) || (int) $staffId <= 0) {
			throw new RuntimeException('Failed to create test staff profile: ' . $title);
		}

		return $registerPost((int) $staffId);
	};

	$createUser = static function (string $login, string $email) use ($registerUser): int {
		$userId = wp_create_user($login, wp_generate_password(20, true, true), $email);
		if (is_wp_error($userId) || (int) $userId <= 0) {
			throw new RuntimeException('Failed to create test user: ' . $login);
		}

		return $registerUser((int) $userId);
	};

	$role = wp_insert_term('Cancellation Test Role ' . $suffix, 'vms_staff_role', array(
		'slug' => 'cancel-test-role-' . $suffix,
	));
	if (is_wp_error($role) || empty($role['term_id'])) {
		throw new RuntimeException('Failed to create test staff role.');
	}
	$roleId = $registerTerm((int) $role['term_id']);

	$primaryVendorId = $createVendor('Cancellation Test Primary Vendor ' . $suffix, 'primary-' . $suffix . '@example.test');
	$secondaryVendorId = $createVendor('Cancellation Test Secondary Vendor ' . $suffix, 'secondary-' . $suffix . '@example.test');

	$eventPlanId = wp_insert_post(array(
		'post_type' => 'vms_event_plan',
		'post_status' => 'draft',
		'post_title' => 'Cancellation Notification Resolver Test ' . $suffix,
	), true);
	if (is_wp_error($eventPlanId) || (int) $eventPlanId <= 0) {
		throw new RuntimeException('Failed to create test Event Plan.');
	}
	$eventPlanId = (int) $eventPlanId;
	$registerPost($eventPlanId);
	$registerPlan($eventPlanId);

	$bandKey = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'band_vendor_id') ?: '_vms_band_vendor_id') : '_vms_band_vendor_id';
	$secondaryKey = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'secondary_vendor_ids') ?: '_vms_secondary_vendor_ids') : '_vms_secondary_vendor_ids';
	update_post_meta($eventPlanId, $bandKey, $primaryVendorId);
	update_post_meta($eventPlanId, $secondaryKey, array($secondaryVendorId));
	update_post_meta($eventPlanId, '_vms_event_date', '2026-07-04');

	$staffLinkedId = $createStaff('Cancellation Test Staff Linked ' . $suffix);
	$staffMetaOnlyId = $createStaff('Cancellation Test Staff User Meta ' . $suffix);
	$staffMissingId = $createStaff('Cancellation Test Staff Missing Email ' . $suffix);
	wp_set_post_terms($staffLinkedId, array($roleId), 'vms_staff_role', false);
	wp_set_post_terms($staffMetaOnlyId, array($roleId), 'vms_staff_role', false);
	wp_set_post_terms($staffMissingId, array($roleId), 'vms_staff_role', false);

	$linkedUserId = $createUser('cancel_linked_' . $suffix, 'linked-' . $suffix . '@example.test');
	$conflictUserId = $createUser('cancel_conflict_' . $suffix, 'conflict-' . $suffix . '@example.test');
	$metaOnlyUserId = $createUser('cancel_meta_' . $suffix, 'meta-' . $suffix . '@example.test');

	update_post_meta($staffLinkedId, '_vms_linked_user_id', $linkedUserId);
	update_user_meta($conflictUserId, '_vms_staff_id', $staffLinkedId);
	update_user_meta($metaOnlyUserId, '_vms_staff_id', $staffMetaOnlyId);

	$seedResult = function_exists('vms_staffing_save_event_roles_matrix')
		? vms_staffing_save_event_roles_matrix(
			$eventPlanId,
			array($roleId => 3),
			array($roleId => array($staffLinkedId, $staffMetaOnlyId, $staffMissingId))
		)
		: array('ok' => false, 'error' => 'staffing_save_unavailable');
	$assert(!empty($seedResult['ok']), 'Failed to seed staffing slots for the cancellation notification harness.');

	delete_post_meta($eventPlanId, '_vms_staff_assignments');

	$summary = array(
		'policy' => 'status_only',
		'steps' => array(),
		'vendor_message' => '',
	);
	$result = vms_cancellation_run_step($eventPlanId, 'status_only', 'notifications', $summary);
	$assert(($result['status'] ?? '') === 'done', 'Expected dry-run notifications step to return done.');
	$assert(($result['message'] ?? '') === 'notifications_dry_run_only', 'Expected dry-run notifications step message.');

	$data = isset($result['data']) && is_array($result['data']) ? $result['data'] : array();
	$assert(($data['staff_assignment_source'] ?? '') === 'modern', 'Expected modern staffing assignment source.');

	$recipients = isset($data['recipients']) && is_array($data['recipients']) ? $data['recipients'] : array();
	$skipped = isset($data['skipped']) && is_array($data['skipped']) ? $data['skipped'] : array();

	$findRecipientByStaffId = static function (array $rows, int $staffId): ?array {
		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}
			if (absint($row['staff_id'] ?? 0) === $staffId) {
				return $row;
			}
		}
		return null;
	};

	$linkedRecipient = $findRecipientByStaffId($recipients, $staffLinkedId);
	$assert(is_array($linkedRecipient), 'Expected linked-user staff recipient to be present.');
	$assert(($linkedRecipient['email'] ?? '') === 'linked-' . $suffix . '@example.test', 'Expected linked-user email to win over fallback user-meta email.');
	$assert(($linkedRecipient['email_source'] ?? '') === 'linked_user_meta', 'Expected linked-user email source.');

	$metaOnlyRecipient = $findRecipientByStaffId($recipients, $staffMetaOnlyId);
	$assert(is_array($metaOnlyRecipient), 'Expected user-meta staff recipient to be present.');
	$assert(($metaOnlyRecipient['email'] ?? '') === 'meta-' . $suffix . '@example.test', 'Expected user-meta fallback email.');
	$assert(($metaOnlyRecipient['email_source'] ?? '') === 'user_staff_meta', 'Expected user-meta email source.');

	$missingRecipient = $findRecipientByStaffId($skipped, $staffMissingId);
	$assert(is_array($missingRecipient), 'Expected missing-email staff recipient to be skipped.');
	$assert(($missingRecipient['reason'] ?? '') === 'missing_email', 'Expected missing-email skip reason.');

	$assert(absint($data['recipient_count'] ?? 0) === 5, 'Expected five recipients discovered including missing-email staff.');
	$assert(absint($data['deliverable_recipient_count'] ?? 0) === 4, 'Expected four deliverable recipients.');

	$output = array(
		'ok' => true,
		'status' => $result['status'] ?? '',
		'message' => $result['message'] ?? '',
		'staff_assignment_source' => $data['staff_assignment_source'] ?? '',
		'recipient_count' => absint($data['recipient_count'] ?? 0),
		'deliverable_recipient_count' => absint($data['deliverable_recipient_count'] ?? 0),
		'linked_user_email' => $linkedRecipient['email'] ?? '',
		'linked_user_email_source' => $linkedRecipient['email_source'] ?? '',
		'user_meta_email' => $metaOnlyRecipient['email'] ?? '',
		'user_meta_email_source' => $metaOnlyRecipient['email_source'] ?? '',
		'missing_email_reason' => $missingRecipient['reason'] ?? '',
	);

	echo wp_json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} catch (Throwable $e) {
	fwrite(STDERR, $e->getMessage() . "\n");
	$cleanup();
	exit(1);
}

$cleanup();
exit(0);
