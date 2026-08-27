<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap-wordpress.php';
vms_tests_require_wordpress(__DIR__);

if (!function_exists('is_plugin_active')) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

global $wpdb;

$rawArgs = isset($argv[1]) ? (string) $argv[1] : '{}';
$args = json_decode($rawArgs, true);
if (!is_array($args)) {
	$args = array();
}

$fixtureOption = isset($args['fixture_option']) ? (string) $args['fixture_option'] : 'vms_compat_upgrade_fixture_manifest';
$fixturePrefix = isset($args['prefix']) ? sanitize_key((string) $args['prefix']) : 'compat';
$suffix = strtolower(wp_generate_password(6, false, false));
$labelBase = strtoupper($fixturePrefix) . '-' . $suffix;

wp_set_current_user(1);

$eventPlanMetaKey = static function (string $field, string $fallback): string {
	if (function_exists('bvmgr_meta_key')) {
		$key = (string) bvmgr_meta_key('event_plan', $field);
		if ($key !== '') {
			return $key;
		}
	}

	return $fallback;
};

$productMetaKey = static function (string $field, string $fallback): string {
	if (function_exists('bvmgr_ticketing_v2_product_meta_key')) {
		$key = (string) bvmgr_ticketing_v2_product_meta_key($field);
		if ($key !== '') {
			return $key;
		}
	}
	if (function_exists('bvmgr_meta_key')) {
		$key = (string) bvmgr_meta_key('product', $field);
		if ($key !== '') {
			return $key;
		}
	}

	return $fallback;
};

$createPost = static function (array $postarr, string $label): int {
	$postId = wp_insert_post($postarr, true);
	if (is_wp_error($postId) || (int) $postId <= 0) {
		throw new RuntimeException('Failed to create ' . $label . ': ' . (is_wp_error($postId) ? $postId->get_error_message() : 'unknown error'));
	}

	return (int) $postId;
};

$ensureAdmissionRow = static function (int $eventPlanId, int $venueId, int $actorUserId, string $guestName, string $guestEmail) use ($wpdb): int {
	$table = function_exists('vms_admission_table_entries') ? vms_admission_table_entries() : ($wpdb->prefix . 'vms_admission_entries');
	$createdAt = current_time('mysql', true);
	$guestNameNorm = sanitize_title($guestName);
	$guestEmailNorm = sanitize_email($guestEmail);
	$result = $wpdb->insert(
		$table,
		array(
			'event_plan_id' => $eventPlanId,
			'venue_id' => $venueId,
			'admission_kind' => 'guest_list',
			'source' => 'compat_fixture',
			'owner_vendor_id' => null,
			'guest_name' => $guestName,
			'guest_name_norm' => $guestNameNorm,
			'guest_email' => $guestEmail,
			'guest_email_norm' => $guestEmailNorm,
			'party_size' => 2,
			'checked_in_qty' => 0,
			'status' => 'active',
			'admission_token' => '',
			'created_by' => $actorUserId,
			'created_at' => $createdAt,
		),
		array(
			'%d',
			'%d',
			'%s',
			'%s',
			'%d',
			'%s',
			'%s',
			'%s',
			'%s',
			'%d',
			'%d',
			'%s',
			'%s',
			'%d',
			'%s',
		)
	);
	if ($result === false) {
		throw new RuntimeException('Failed to create admissions fixture row.');
	}

	$entryId = (int) $wpdb->insert_id;
	if ($entryId > 0 && function_exists('vms_admission_ensure_entry_token')) {
		vms_admission_ensure_entry_token($entryId);
	}

	return $entryId;
};

$createProduct = static function (string $title, float $price) use ($createPost): int {
	$productId = $createPost(
		array(
			'post_type' => 'product',
			'post_status' => 'publish',
			'post_title' => $title,
		),
		'WooCommerce product'
	);

	update_post_meta($productId, '_sku', sanitize_title($title) . '-' . wp_generate_password(5, false, false));
	update_post_meta($productId, '_price', wc_format_decimal($price, 2));
	update_post_meta($productId, '_regular_price', wc_format_decimal($price, 2));
	update_post_meta($productId, '_stock_status', 'instock');
	update_post_meta($productId, '_virtual', 'yes');
	update_post_meta($productId, '_manage_stock', 'no');
	wp_set_object_terms($productId, 'simple', 'product_type', false);

	return $productId;
};

$eventPlanId = $createPost(
	array(
		'post_type' => 'vms_event_plan',
		'post_status' => 'publish',
		'post_title' => 'Compatibility Event Plan ' . $labelBase,
	),
	'Event Plan'
);

$vendorId = $createPost(
	array(
		'post_type' => 'vms_vendor',
		'post_status' => 'publish',
		'post_title' => 'Compatibility Vendor ' . $labelBase,
	),
	'vendor'
);

$eventDate = gmdate('Y-m-d', strtotime('+14 days'));
$eventStartTime = '19:00';
$eventEndTime = '22:00';
$eventStartUtc = gmdate('Y-m-d H:i:s', strtotime('+14 days 19:00:00 UTC'));
$eventEndUtc = gmdate('Y-m-d H:i:s', strtotime('+14 days 22:00:00 UTC'));
$eventTimezone = function_exists('wp_timezone_string') ? (string) wp_timezone_string() : 'UTC';
if ($eventTimezone === '') {
	$eventTimezone = 'UTC';
}

update_post_meta($eventPlanId, '_vms_event_date', $eventDate);
update_post_meta($eventPlanId, '_vms_start_time', $eventStartTime);
update_post_meta($eventPlanId, '_vms_end_time', $eventEndTime);
update_post_meta($eventPlanId, '_vms_event_plan_status', 'published');
update_post_meta($eventPlanId, '_vms_band_vendor_id', $vendorId);

$tecEventId = 0;
if (function_exists('tribe_create_event')) {
	$createdEventId = tribe_create_event(
		array(
			'post_title' => 'Compatibility TEC Event ' . $labelBase,
			'post_status' => 'publish',
			'EventStartDate' => $eventDate,
			'EventEndDate' => $eventDate,
			'EventStartTime' => $eventStartTime,
			'EventEndTime' => $eventEndTime,
			'EventAllDay' => false,
		)
	);
	if (!is_wp_error($createdEventId) && (int) $createdEventId > 0) {
		$tecEventId = (int) $createdEventId;
	}
}
if ($tecEventId <= 0) {
	$tecEventId = $createPost(
		array(
			'post_type' => 'tribe_events',
			'post_status' => 'publish',
			'post_title' => 'Compatibility TEC Event ' . $labelBase,
		),
		'TEC event'
	);
	update_post_meta($tecEventId, '_EventStartDate', gmdate('Y-m-d H:i:s', strtotime('+14 days 19:00:00')));
	update_post_meta($tecEventId, '_EventEndDate', gmdate('Y-m-d H:i:s', strtotime('+14 days 22:00:00')));
	update_post_meta($tecEventId, '_EventStartDateUTC', $eventStartUtc);
	update_post_meta($tecEventId, '_EventEndDateUTC', $eventEndUtc);
	update_post_meta($tecEventId, '_EventTimezone', $eventTimezone);
}

$userId = (int) wp_create_user(
	'compat-veteran-' . $suffix,
	wp_generate_password(24, true, true),
	'compat-veteran-' . $suffix . '@example.test'
);
if ($userId <= 0) {
	throw new RuntimeException('Failed to create compatible verified user fixture.');
}

$normalProductId = $createProduct('Compatibility General Admission ' . $labelBase, 25.00);
$qualifiedProductId = $createProduct('Compatibility Veteran Ticket ' . $labelBase, 0.00);

$planTecMetaKey = $eventPlanMetaKey('tec_event_id', '_vms_tec_event_id');
$ticketProductsMetaKey = $eventPlanMetaKey('ticket_product_ids', '_vms_ticket_product_ids_v1');
$ticketingEnabledMetaKey = $eventPlanMetaKey('ticketing_enabled_override', '_vms_ticketing_enabled_override');
$ticketUiLayoutMetaKey = '_vms_ticket_ui_layout_override';
$ticketingConfigMetaKey = $eventPlanMetaKey('ticketing_config_v2', '_vms_ticketing_config_v2');
$ticketingSyncMetaKey = $eventPlanMetaKey('ticketing_sync_v2', '_vms_ticketing_sync_v2');

$productPlanMetaKey = $productMetaKey('event_plan_id', '_vms_event_plan_id');
$productTecMetaKey = $productMetaKey('tec_event_id', '_vms_tec_event_id');
$productRoleMetaKey = $productMetaKey('product_role', '_vms_product_role');
$visibilityMetaKey = $productMetaKey('ticketing_visibility_mode', '_vms_ticketing_visibility_mode');
$verifiedProgramMetaKey = $productMetaKey('ticketing_verified_program', '_vms_ticketing_verified_program');
$allowedProgramsMetaKey = $productMetaKey('ticketing_allowed_programs', '_vms_ticketing_allowed_programs');
$claimTypeMetaKey = $productMetaKey('ticketing_claim_grant_type', '_vms_ticketing_claim_grant_type');

update_post_meta($eventPlanId, $planTecMetaKey, $tecEventId);
update_post_meta($eventPlanId, $ticketProductsMetaKey, array($normalProductId, $qualifiedProductId));
update_post_meta($eventPlanId, $ticketingEnabledMetaKey, 'on');
update_post_meta($eventPlanId, $ticketUiLayoutMetaKey, 'progressive');

$ticketingConfig = array(
	'version' => 2,
	'tickets' => array(
		array(
			'ticket_key' => 'general_admission',
			'label' => 'General Admission',
			'visibility_mode' => 'public',
			'price' => '25.00',
			'product_id' => $normalProductId,
		),
		array(
			'ticket_key' => 'veteran_admission',
			'label' => 'Veteran Ticket',
			'visibility_mode' => 'verified',
			'verified_program' => 'veteran',
			'allowed_programs' => array('veteran'),
			'claim_grant_type' => 'event_ticket_eligibility',
			'price' => '0.00',
			'product_id' => $qualifiedProductId,
		),
	),
);
$ticketingSync = array(
	'version' => 2,
	'product_ids' => array($normalProductId, $qualifiedProductId),
	'linked_tec_event_id' => $tecEventId,
	'last_commit' => array(
		'status' => 'seeded',
		'seeded_at_gmt' => gmdate('c'),
	),
);
update_post_meta($eventPlanId, $ticketingConfigMetaKey, $ticketingConfig);
update_post_meta($eventPlanId, $ticketingSyncMetaKey, $ticketingSync);

foreach (array($normalProductId, $qualifiedProductId) as $productId) {
	update_post_meta($productId, $productPlanMetaKey, $eventPlanId);
	update_post_meta($productId, $productTecMetaKey, $tecEventId);
}
update_post_meta($normalProductId, $productRoleMetaKey, 'ga_ticket');
update_post_meta($normalProductId, '_vms_ticket_key', 'general_admission');
update_post_meta($qualifiedProductId, $productRoleMetaKey, 'ga_ticket');
update_post_meta($qualifiedProductId, '_vms_ticket_key', 'veteran_admission');
update_post_meta($qualifiedProductId, $visibilityMetaKey, 'verified');
update_post_meta($qualifiedProductId, $verifiedProgramMetaKey, 'veteran');
update_post_meta($qualifiedProductId, $allowedProgramsMetaKey, array('veteran'));
update_post_meta($qualifiedProductId, $claimTypeMetaKey, 'event_ticket_eligibility');

if (function_exists('bvmgr_vendor_user_link_upsert')) {
	bvmgr_vendor_user_link_upsert(
		$vendorId,
		$userId,
		array(
			'role' => 'manager',
			'status' => 'active',
			'set_primary_for_user' => true,
			'source' => 'compat_fixture',
		),
		1
	);
} else {
	update_user_meta($userId, '_vms_vendor_id', $vendorId);
	update_post_meta($vendorId, '_vms_vendor_user_id', $userId);
}

if (function_exists('bvmgr_ticketing_verification_assign_program')) {
	bvmgr_ticketing_verification_assign_program($userId, 'veteran', 'Compatibility fixture', 1);
} else {
	update_user_meta($userId, 'vms_verified_programs', array('veteran'));
	$user = get_user_by('id', $userId);
	if ($user instanceof WP_User && !$user->has_cap('vms_verified_veteran')) {
		$user->add_role('vms_verified_veteran');
	}
}

$admissionEntryId = $ensureAdmissionRow(
	$eventPlanId,
	0,
	1,
	'Compatibility Guest ' . $labelBase,
	'compat-guest-' . $suffix . '@example.test'
);

$scheduledHooks = array();
$spotHook = function_exists('bvmgr_ticket_integrity_spot_hook')
	? (string) bvmgr_ticket_integrity_spot_hook()
	: 'vms_ticket_integrity_spot_scan';
if ($spotHook !== '') {
	wp_schedule_single_event(time() + 600, $spotHook, array($eventPlanId));
	$scheduledHooks[] = $spotHook;
}

$fixture = array(
	'version' => 1,
	'created_at_utc' => gmdate('c'),
	'plan_id' => $eventPlanId,
	'tec_event_id' => $tecEventId,
	'vendor_id' => $vendorId,
	'user_id' => $userId,
	'normal_product_id' => $normalProductId,
	'qualified_product_id' => $qualifiedProductId,
	'admission_entry_id' => $admissionEntryId,
	'seeded_scheduled_hooks' => $scheduledHooks,
	'expected' => array(
		'plan_meta' => array(
			$planTecMetaKey => $tecEventId,
			$ticketProductsMetaKey => array($normalProductId, $qualifiedProductId),
			$ticketingEnabledMetaKey => 'on',
			$ticketUiLayoutMetaKey => 'progressive',
			$ticketingConfigMetaKey => $ticketingConfig,
			$ticketingSyncMetaKey => $ticketingSync,
		),
		'product_meta' => array(
			$normalProductId => array(
				$productPlanMetaKey => $eventPlanId,
				$productTecMetaKey => $tecEventId,
				$productRoleMetaKey => 'ga_ticket',
				'_vms_ticket_key' => 'general_admission',
			),
			$qualifiedProductId => array(
				$productPlanMetaKey => $eventPlanId,
				$productTecMetaKey => $tecEventId,
				$productRoleMetaKey => 'ga_ticket',
				'_vms_ticket_key' => 'veteran_admission',
				$visibilityMetaKey => 'verified',
				$verifiedProgramMetaKey => 'veteran',
				$allowedProgramsMetaKey => array('veteran'),
				$claimTypeMetaKey => 'event_ticket_eligibility',
			),
		),
		'user_programs' => array('veteran'),
	),
);

update_option($fixtureOption, $fixture, false);

fwrite(
	STDOUT,
	wp_json_encode(
		array(
			'fixture_option' => $fixtureOption,
			'fixture' => $fixture,
		),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
	) . "\n"
);
