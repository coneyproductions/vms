<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap-wordpress.php';
vms_tests_require_wordpress(__DIR__);
require_once dirname(__DIR__, 2) . '/scripts/lib/release-compatibility.php';

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
$fixture = get_option($fixtureOption, array());
if (!is_array($fixture)) {
	$fixture = array();
}

$sanitizeRecursive = static function ($value) use (&$sanitizeRecursive) {
	if (is_array($value)) {
		$isList = array_keys($value) === range(0, count($value) - 1);
		$next = array();
		foreach ($value as $key => $child) {
			$next[$key] = $sanitizeRecursive($child);
		}
		if ($isList) {
			return array_values($next);
		}
		ksort($next);
		return $next;
	}
	if ($value instanceof stdClass) {
		return $sanitizeRecursive((array) $value);
	}
	if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
		return $value;
	}
	return (string) $value;
};

$normalizeMetaValue = static function ($value) use (&$normalizeMetaValue) {
	if (is_array($value)) {
		$isList = array_keys($value) === range(0, count($value) - 1);
		$next = array();
		foreach ($value as $key => $child) {
			$next[$key] = $normalizeMetaValue($child);
		}
		if ($isList) {
			usort($next, static function ($left, $right): int {
				return strcmp(wp_json_encode($left), wp_json_encode($right));
			});
			return array_values($next);
		}
		ksort($next);
		return $next;
	}
	if (is_object($value)) {
		return $normalizeMetaValue((array) $value);
	}
	if (is_numeric($value) && (string) (int) $value === (string) $value) {
		return (int) $value;
	}
	return $value;
};

$vmsOwnedCronHook = static function (string $hook): bool {
	if (function_exists('bvmgr_is_owned_cron_hook')) {
		return bvmgr_is_owned_cron_hook($hook);
	}

	return strpos($hook, 'vms_') === 0;
};

$tableExists = static function (string $tableName) use ($wpdb): bool {
	$like = $wpdb->esc_like($tableName);
	$found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $like));
	return is_string($found) && $found === $tableName;
};

$tableRowCount = static function (string $tableName) use ($tableExists, $wpdb): int {
	if (!$tableExists($tableName)) {
		return 0;
	}

	$count = $wpdb->get_var("SELECT COUNT(*) FROM {$tableName}");
	return is_numeric($count) ? (int) $count : 0;
};

$activePlugins = array_values(array_map('strval', (array) get_option('active_plugins', array())));
sort($activePlugins, SORT_STRING);
$vmsPluginBase = VMS_Release_Compatibility_Tooling::resolveInstalledPluginBasename($activePlugins, WP_PLUGIN_DIR);
$vmsActive = $vmsPluginBase !== '' ? is_plugin_active($vmsPluginBase) : false;

$vmsOptionRows = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name ASC",
		'vms\_%'
	),
	ARRAY_A
);
$vmsOptionNames = array();
foreach ((array) $vmsOptionRows as $row) {
	if (!is_array($row) || empty($row['option_name'])) {
		continue;
	}
	$vmsOptionNames[] = (string) $row['option_name'];
}

$vmsTables = array();
$tableRows = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->prefix . 'vms_') . '%'));
foreach ((array) $tableRows as $tableName) {
	$vmsTables[] = (string) $tableName;
}
sort($vmsTables, SORT_STRING);

$ownedCronHooks = array();
$duplicateCronHooks = array();
$overdueCronHooks = array();
$cronTotal = 0;
$now = time();
if (function_exists('_get_cron_array')) {
	$cron = _get_cron_array();
	foreach ((array) $cron as $timestamp => $hooks) {
		foreach ((array) $hooks as $hook => $instances) {
			$hook = (string) $hook;
			$count = is_array($instances) ? count($instances) : 0;
			if (!$vmsOwnedCronHook($hook)) {
				continue;
			}
			$ownedCronHooks[$hook] = ($ownedCronHooks[$hook] ?? 0) + $count;
			$cronTotal += $count;
			if ((int) $timestamp < ($now - 300)) {
				$overdueCronHooks[$hook] = ($overdueCronHooks[$hook] ?? 0) + $count;
			}
		}
	}
	foreach ($ownedCronHooks as $hook => $count) {
		if ($count > 1) {
			$duplicateCronHooks[$hook] = $count;
		}
	}
	ksort($ownedCronHooks);
	ksort($duplicateCronHooks);
	ksort($overdueCronHooks);
}

$actionScheduler = array(
	'available' => false,
	'owned_actions_total' => 0,
	'owned_hooks' => array(),
	'duplicate_hooks' => array(),
	'overdue_hooks' => array(),
	'status_counts' => array(),
);
$actionSchedulerTable = $wpdb->prefix . 'actionscheduler_actions';
if ($tableExists($actionSchedulerTable)) {
	$actionScheduler['available'] = true;
	$rows = $wpdb->get_results(
		"SELECT hook, status, scheduled_date_gmt FROM {$actionSchedulerTable} WHERE hook LIKE 'vms\_%'",
		ARRAY_A
	);
	foreach ((array) $rows as $row) {
		if (!is_array($row)) {
			continue;
		}
		$hook = isset($row['hook']) ? (string) $row['hook'] : '';
		if ($hook === '') {
			continue;
		}
		$status = isset($row['status']) ? (string) $row['status'] : '';
		$scheduledAt = isset($row['scheduled_date_gmt']) ? (string) $row['scheduled_date_gmt'] : '';
		$actionScheduler['owned_actions_total']++;
		$actionScheduler['owned_hooks'][$hook] = ($actionScheduler['owned_hooks'][$hook] ?? 0) + 1;
		$actionScheduler['status_counts'][$status] = ($actionScheduler['status_counts'][$status] ?? 0) + 1;
		if ($scheduledAt !== '') {
			$scheduledTs = strtotime($scheduledAt . ' UTC');
			if (is_int($scheduledTs) && $scheduledTs < ($now - 300) && in_array($status, array('pending', 'running'), true)) {
				$actionScheduler['overdue_hooks'][$hook] = ($actionScheduler['overdue_hooks'][$hook] ?? 0) + 1;
			}
		}
	}
	foreach ((array) $actionScheduler['owned_hooks'] as $hook => $count) {
		if ((int) $count > 1) {
			$actionScheduler['duplicate_hooks'][$hook] = (int) $count;
		}
	}
	ksort($actionScheduler['owned_hooks']);
	ksort($actionScheduler['duplicate_hooks']);
	ksort($actionScheduler['overdue_hooks']);
	ksort($actionScheduler['status_counts']);
}

$rolesSummary = array();
$capsSummary = array();
global $wp_roles;
if ($wp_roles instanceof WP_Roles) {
	foreach ((array) $wp_roles->roles as $roleKey => $roleData) {
		$matchedCaps = array();
		foreach ((array) ($roleData['capabilities'] ?? array()) as $cap => $enabled) {
			if (!$enabled) {
				continue;
			}
			$cap = (string) $cap;
			if (strpos($cap, 'vms_') === 0) {
				$matchedCaps[] = $cap;
				$capsSummary[$cap] = true;
			}
		}
		if ($matchedCaps === array()) {
			continue;
		}
		sort($matchedCaps, SORT_STRING);
		$rolesSummary[(string) $roleKey] = $matchedCaps;
	}
}
ksort($rolesSummary);
$capsSummary = array_values(array_keys($capsSummary));
sort($capsSummary, SORT_STRING);

$postTypeCounts = array();
foreach (array('vms_event_plan', 'vms_vendor', 'vms_staff', 'tribe_events', 'product', 'vms_vendor_application') as $postType) {
	$counts = wp_count_posts($postType);
	$postTypeCounts[$postType] = ($counts instanceof stdClass && isset($counts->publish)) ? (int) $counts->publish : 0;
}
ksort($postTypeCounts);

$pageKeys = array(
	'vendor_application',
	'vendor_portal',
	'staff_portal',
	'public_calendar',
);
$publicPages = array();
foreach ($pageKeys as $pageKey) {
	$pageId = (int) get_option('vms_page_' . $pageKey, 0);
	$publicPages[$pageKey] = array(
		'id' => $pageId,
		'exists' => $pageId > 0 && get_post($pageId) instanceof WP_Post,
		'status' => $pageId > 0 ? (string) get_post_status($pageId) : '',
		'slug' => $pageId > 0 ? (string) get_post_field('post_name', $pageId) : '',
	);
}
ksort($publicPages);

$fixtureState = array(
	'present' => !empty($fixture),
	'option_name' => $fixtureOption,
	'option_present' => !empty($fixture),
	'manifest' => $sanitizeRecursive($fixture),
	'checks' => array(),
	'preserved' => true,
);
if (!empty($fixture)) {
	$expectedPlanMeta = (array) ($fixture['expected']['plan_meta'] ?? array());
	$expectedProductMeta = (array) ($fixture['expected']['product_meta'] ?? array());
	$expectedUserPrograms = array_values(array_map('sanitize_key', (array) ($fixture['expected']['user_programs'] ?? array())));
	sort($expectedUserPrograms, SORT_STRING);
	$expectedScheduledHooks = array_values(array_map('strval', (array) ($fixture['expected']['scheduled_hooks'] ?? array())));
	sort($expectedScheduledHooks, SORT_STRING);
	$planId = isset($fixture['plan_id']) ? (int) $fixture['plan_id'] : 0;
	$eventId = isset($fixture['tec_event_id']) ? (int) $fixture['tec_event_id'] : 0;
	$vendorId = isset($fixture['vendor_id']) ? (int) $fixture['vendor_id'] : 0;
	$userId = isset($fixture['user_id']) ? (int) $fixture['user_id'] : 0;
	$normalProductId = isset($fixture['normal_product_id']) ? (int) $fixture['normal_product_id'] : 0;
	$qualifiedProductId = isset($fixture['qualified_product_id']) ? (int) $fixture['qualified_product_id'] : 0;
	$admissionEntryId = isset($fixture['admission_entry_id']) ? (int) $fixture['admission_entry_id'] : 0;

	$currentPlanMeta = array();
	foreach ($expectedPlanMeta as $metaKey => $_expectedValue) {
		$currentPlanMeta[(string) $metaKey] = $normalizeMetaValue(get_post_meta($planId, (string) $metaKey, true));
	}

	$currentProductMeta = array();
	foreach ($expectedProductMeta as $productId => $metaMap) {
		$productId = (int) $productId;
		$currentProductMeta[$productId] = array();
		foreach ((array) $metaMap as $metaKey => $_expectedValue) {
			$currentProductMeta[$productId][(string) $metaKey] = $normalizeMetaValue(get_post_meta($productId, (string) $metaKey, true));
		}
		ksort($currentProductMeta[$productId]);
	}

	$currentUserPrograms = array();
	if ($userId > 0) {
		if (function_exists('bvmgr_ticketing_get_user_verified_programs')) {
			$currentUserPrograms = array_values(array_map('sanitize_key', bvmgr_ticketing_get_user_verified_programs($userId)));
		} else {
			$currentUserPrograms = array_values(array_map('sanitize_key', (array) get_user_meta($userId, 'vms_verified_programs', true)));
		}
		sort($currentUserPrograms, SORT_STRING);
	}

	$admissionsTable = $wpdb->prefix . 'vms_admission_entries';
	$admissionRow = null;
	if ($admissionEntryId > 0 && $tableExists($admissionsTable)) {
		$admissionRow = $wpdb->get_row(
			$wpdb->prepare("SELECT id, event_plan_id, status, guest_name, party_size, checked_in_qty FROM {$admissionsTable} WHERE id = %d", $admissionEntryId),
			ARRAY_A
		);
	}

	$vendorLinkRows = array();
	$vendorLinksTable = $wpdb->prefix . 'vms_vendor_user_links';
	if ($vendorId > 0 && $tableExists($vendorLinksTable)) {
		$vendorLinkRows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT vendor_id, user_id, user_role, link_status, is_primary FROM {$vendorLinksTable} WHERE vendor_id = %d ORDER BY user_id ASC",
				$vendorId
			),
			ARRAY_A
		);
	}

	$currentScheduledHooks = array();
	foreach (array_keys($ownedCronHooks) as $hook) {
		if (in_array($hook, $expectedScheduledHooks, true)) {
			$currentScheduledHooks[$hook] = (int) ($ownedCronHooks[$hook] ?? 0);
		}
	}
	$eventPermalink = $eventId > 0 ? (string) get_permalink($eventId) : '';

	$fixtureChecks = array(
		'plan_exists' => $planId > 0 && get_post_type($planId) === 'vms_event_plan',
		'tec_event_exists' => $eventId > 0 && get_post_type($eventId) === 'tribe_events',
		'vendor_exists' => $vendorId > 0 && get_post_type($vendorId) === 'vms_vendor',
		'normal_product_exists' => $normalProductId > 0 && get_post_type($normalProductId) === 'product',
		'qualified_product_exists' => $qualifiedProductId > 0 && get_post_type($qualifiedProductId) === 'product',
		'user_exists' => $userId > 0 && get_user_by('id', $userId) instanceof WP_User,
		'user_programs_match' => $currentUserPrograms === $expectedUserPrograms,
		'plan_meta_match' => $sanitizeRecursive($currentPlanMeta) === $sanitizeRecursive($normalizeMetaValue($expectedPlanMeta)),
		'product_meta_match' => $sanitizeRecursive($currentProductMeta) === $sanitizeRecursive($normalizeMetaValue($expectedProductMeta)),
		'admission_entry_present' => is_array($admissionRow) && (int) ($admissionRow['event_plan_id'] ?? 0) === $planId,
		'vendor_user_link_present' => !empty($vendorLinkRows),
	);
	if ($expectedScheduledHooks !== array()) {
		$fixtureChecks['scheduled_hooks_present'] = array_reduce($expectedScheduledHooks, static function (bool $carry, string $hook) use ($ownedCronHooks): bool {
			return $carry && !empty($ownedCronHooks[$hook]);
		}, true);
	}

	$fixtureState['checks'] = array(
		'expected_plan_meta' => $sanitizeRecursive($normalizeMetaValue($expectedPlanMeta)),
		'current_plan_meta' => $sanitizeRecursive($currentPlanMeta),
		'expected_product_meta' => $sanitizeRecursive($normalizeMetaValue($expectedProductMeta)),
		'current_product_meta' => $sanitizeRecursive($currentProductMeta),
		'expected_user_programs' => $expectedUserPrograms,
		'current_user_programs' => $currentUserPrograms,
		'admission_row' => $sanitizeRecursive($admissionRow),
		'vendor_user_links' => $sanitizeRecursive($vendorLinkRows),
		'event_permalink' => $sanitizeRecursive($eventPermalink),
		'status' => $fixtureChecks,
	);
	if ($expectedScheduledHooks !== array()) {
		$fixtureState['checks']['expected_scheduled_hooks'] = $expectedScheduledHooks;
		$fixtureState['checks']['current_scheduled_hooks'] = $currentScheduledHooks;
	}
	$fixturePreservationChecks = $fixtureChecks;
	unset($fixturePreservationChecks['scheduled_hooks_present']);
	$fixtureState['event_permalink'] = $sanitizeRecursive($eventPermalink);
	$fixtureState['preserved'] = !in_array(false, array_values($fixturePreservationChecks), true);
}

$pluginVersion = defined('BVMGR_VERSION') ? (string) BVMGR_VERSION : '';
$buildVersion = '';
$pluginBuildFile = VMS_Release_Compatibility_Tooling::buildVersionPathForPluginBasename(WP_PLUGIN_DIR, $vmsPluginBase);
if ($pluginBuildFile !== '' && is_file($pluginBuildFile)) {
	$buildVersion = trim((string) file_get_contents($pluginBuildFile));
}

$report = array(
	'captured_at_utc' => gmdate('c'),
	'site' => array(
		'home' => (string) home_url('/'),
		'url' => (string) site_url('/'),
		'wp_version' => (string) get_bloginfo('version'),
		'php_version' => PHP_VERSION,
		'db_server_version' => method_exists($wpdb, 'db_version') ? (string) $wpdb->db_version() : '',
		'db_prefix' => (string) $wpdb->prefix,
	),
	'plugin' => array(
		'active' => $vmsActive,
		'active_plugins' => $activePlugins,
		'basename' => $vmsPluginBase,
		'recognized_basenames' => VMS_Release_Compatibility_Tooling::recognizedPluginBasenames(),
		'version' => $pluginVersion,
		'build_version' => $buildVersion,
		'vms_loaded' => did_action('vms_loaded') > 0,
		'public_pages' => $publicPages,
	),
	'post_type_counts' => $postTypeCounts,
	'vms_options' => array(
		'count' => count($vmsOptionNames),
		'names' => $vmsOptionNames,
		'version_markers' => array(
			'vms_db_schema_version' => (string) get_option('vms_db_schema_version', ''),
			'vms_admission_db_version' => (string) get_option('vms_admission_db_version', ''),
			'vms_ticketing_claims_db_schema_version' => (string) get_option('vms_ticketing_claims_db_schema_version', ''),
			'vms_ticket_mutation_audit_db_schema_version' => (string) get_option('vms_ticket_mutation_audit_db_schema_version', ''),
			'vms_ticket_inventory_audit_db_schema_version' => (string) get_option('vms_ticket_inventory_audit_db_schema_version', ''),
			'vms_square_ticket_mirror_db_schema_version' => (string) get_option('vms_square_ticket_mirror_db_schema_version', ''),
		),
	),
	'vms_tables' => array(
		'names' => $vmsTables,
		'row_counts' => array(
			$wpdb->prefix . 'vms_vendor_user_links' => $tableRowCount($wpdb->prefix . 'vms_vendor_user_links'),
			$wpdb->prefix . 'vms_admission_entries' => $tableRowCount($wpdb->prefix . 'vms_admission_entries'),
			$wpdb->prefix . 'vms_admission_audit' => $tableRowCount($wpdb->prefix . 'vms_admission_audit'),
			$wpdb->prefix . 'vms_pass_claims' => $tableRowCount($wpdb->prefix . 'vms_pass_claims'),
			$wpdb->prefix . 'vms_pass_tokens' => $tableRowCount($wpdb->prefix . 'vms_pass_tokens'),
		),
	),
	'roles' => array(
		'roles_with_vms_caps' => $rolesSummary,
		'unique_vms_caps' => $capsSummary,
	),
	'cron' => array(
		'owned_total' => $cronTotal,
		'owned_hooks' => $ownedCronHooks,
		'duplicate_hooks' => $duplicateCronHooks,
		'overdue_hooks' => $overdueCronHooks,
	),
	'action_scheduler' => $actionScheduler,
	'fixture' => $fixtureState,
);

fwrite(STDOUT, wp_json_encode($sanitizeRecursive($report), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
