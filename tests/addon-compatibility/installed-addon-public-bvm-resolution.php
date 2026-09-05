<?php
declare(strict_types=1);

if ($argc !== 3) {
	fwrite(STDERR, "usage: php installed-addon-public-bvm-resolution.php <public-bvm-root> <release-root>\n");
	exit(2);
}

$bvmRoot = rtrim($argv[1], DIRECTORY_SEPARATOR);
$releaseRoot = rtrim($argv[2], DIRECTORY_SEPARATOR);
$expectedMabVersion = getenv('BVM_SWEEP_EXPECTED_MAB_VERSION') ?: '0.1.105.1';
$expectedOpsVersion = getenv('BVM_SWEEP_EXPECTED_OPS_VERSION') ?: '0.1.65.1';

$contracts = array(
	'vms-data-tools' => array(
		'version' => '0.5.54',
		'entry' => 'vms-data-tools.php',
		'helper' => 'includes/core-compat.php',
		'functions' => array(
			'vms_admin_guard_current_screen_id', 'vms_admin_guard_request_uri',
			'vms_calculate_attendance_bonus_payout', 'vms_calendar_get_event_slot_limits',
			'vms_core', 'vms_event_plan_get_status', 'vms_event_plan_set_secondary_vendors',
			'vms_event_plan_status_label', 'vms_event_plan_status_normalize',
			'vms_get_event_plan_comp_terms', 'vms_get_timezone', 'vms_meta_key',
			'vms_normalize_email_cell', 'vms_payables_build_bills_for_export', 'vms_portal_notice',
			'vms_pretty_structure_label', 'vms_resource_fingerprint_add_marker',
			'vms_resource_fingerprint_flag', 'vms_resource_fingerprint_span_finish',
			'vms_resource_fingerprint_span_start', 'vms_staffing_get_event_slots',
			'vms_staffing_resolve_slot_window', 'vms_ticket_revenue_available_statuses',
			'vms_ticket_revenue_build_report', 'vms_ticket_revenue_cents_to_decimal',
			'vms_ticket_revenue_event_key', 'vms_ticket_revenue_is_valid_ymd',
			'vms_ticket_revenue_normalize_args', 'vms_ticket_revenue_normalize_ymd',
			'vms_ticket_revenue_wp_now_ymd', 'vms_vendor_portal_get_count_breakdown',
			'vms_vendor_portal_get_progress_headcount_context', 'vms_vendor_schema',
		),
		'classes' => array('VMS_Vendor_Schema_Registry'),
		'constants' => array('VMS_USER_PRIMARY_VENDOR_META_KEY', 'VMS_VENDOR_PRIMARY_USER_META_KEY', 'VMS_VENUE_CPT'),
	),
	'vms-meta-ads' => array(
		'version' => $expectedMabVersion,
		'entry' => 'vms-meta-ads.php',
		'helper' => 'includes/core-compat.php',
		'functions' => array('vms_enqueue_tour_assets', 'vms_event_plan_get_status', 'vms_meta_key', 'vms_module_is_enabled', 'vms_module_is_registered', 'vms_register_module', 'vms_venue_meta_key'),
		'classes' => array(),
		'constants' => array('VMS_PLUGIN_PATH', 'VMS_PLUGIN_URL', 'VMS_VERSION', 'VMS_CAP_SOCIAL_MANAGE'),
	),
	'vms-events-slider' => array(
		'version' => '1.0.10',
		'entry' => 'vms-events-slider.php',
		'helper' => 'vms-events-slider.php',
		'functions' => array('vms_calendar_feed_cache_bust', 'vms_event_plan_get_public_reschedule_destination', 'vms_event_plan_get_status', 'vms_get_event_plan_for_tec_event', 'vms_meta_key', 'vms_resolve_event_plan_for_tec_event', 'vms_tec_is_cancelled_event', 'vms_ticketing_b_meta_key', 'vms_ticketing_v2_find_plan_id_by_tec_event_id'),
		'classes' => array(),
		'constants' => array('VMS_CALENDAR_FEED_CACHE_BUST_OPTION'),
	),
	'vms-refer-a-friend' => array(
		'version' => '0.2.6',
		'entry' => 'vms-refer-a-friend.php',
		'helper' => 'includes/core-compat.php',
		'functions' => array('vms_admin_ui_render_shell', 'vms_get_public_event_calendar_url', 'vms_register_admin_page'),
		'classes' => array(),
		'constants' => array(),
	),
	'vms-investor-portal' => array(
		'version' => '0.2.3',
		'entry' => 'vms-investor-portal.php',
		'helper' => 'includes/core-compat.php',
		'functions' => array('vms_admin_menu_parent_slug', 'vms_admission_table_entries', 'vms_event_command_center_get_ticket_reporting_truth', 'vms_event_command_center_get_ticket_snapshot', 'vms_event_command_center_get_ticket_snapshot_light', 'vms_event_plan_get_status', 'vms_event_plan_status_normalize', 'vms_get_event_plan_for_tec_event', 'vms_goals_get_default_direct_costs_cents', 'vms_goals_get_event_pnl', 'vms_goals_get_overhead_allocated_cents', 'vms_goals_get_settings', 'vms_meta_key', 'vms_register_admin_page', 'vms_staffing_get_event_plan_headcount_context', 'vms_ticket_revenue_build_report', 'vms_ticket_revenue_plan_tec_meta_key', 'vms_ticketing_v2_find_plan_id_by_tec_event', 'vms_ticketing_v2_find_plan_id_by_tec_event_id'),
		'classes' => array(),
		'constants' => array('VMS_PLUGIN_PATH'),
	),
	'vms-ops-console-premium' => array(
		'version' => $expectedOpsVersion,
		'entry' => 'vms-ops-console-premium.php',
		'helper' => 'includes/core-compat.php',
		'functions' => array('vms_admin_ui_data_tools_capability', 'vms_admin_ui_ops_capability', 'vms_admin_ui_page_url', 'vms_admin_ui_registered_page_url', 'vms_admission_audit_log', 'vms_event_plan_checkin_close_meta_key', 'vms_get_current_venue_id', 'vms_get_event_plan_for_tec_event', 'vms_meta_key', 'vms_pass_claims_get_batch_by_id'),
		'classes' => array(),
		'constants' => array('VMS_VENUE_TEMPLATE_META_KEY'),
	),
);

$phpFiles = static function (string $root): array {
	$files = array();
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
	foreach ($iterator as $file) {
		if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
			$files[] = $file->getPathname();
		}
	}
	sort($files);
	return $files;
};

$declarations = array('functions' => array(), 'classes' => array(), 'constants' => array());
foreach ($phpFiles($bvmRoot) as $path) {
	$source = file_get_contents($path);
	if (!is_string($source)) {
		continue;
	}
	$tokens = token_get_all($source);
	$count = count($tokens);
	for ($i = 0; $i < $count; $i++) {
		$token = $tokens[$i];
		if (!is_array($token)) {
			continue;
		}
		if ($token[0] === T_FUNCTION || $token[0] === T_CLASS) {
			for ($j = $i + 1; $j < $count; $j++) {
				if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
					$key = $token[0] === T_FUNCTION ? 'functions' : 'classes';
					$declarations[$key][$tokens[$j][1]] = true;
					break;
				}
				if ($tokens[$j] === '(' || $tokens[$j] === '{') {
					break;
				}
			}
		}
	}
	if (preg_match_all('/define\(\s*[\'\"]([A-Z][A-Z0-9_]+)[\'\"]/', $source, $matches)) {
		foreach ($matches[1] as $constant) {
			$declarations['constants'][$constant] = true;
		}
	}
}

$failures = array();
$results = array();

foreach ($contracts as $slug => $contract) {
	$root = $releaseRoot . DIRECTORY_SEPARATOR . $slug;
	$entry = $root . DIRECTORY_SEPARATOR . $contract['entry'];
	$helper = $root . DIRECTORY_SEPARATOR . $contract['helper'];
	$pluginFailures = array();

	if (!is_file($entry) || !is_file($helper)) {
		$pluginFailures[] = 'missing entry or compatibility helper';
	} else {
		$entrySource = (string) file_get_contents($entry);
		if (!preg_match('/^[ \t*#@]*Version:\s*' . preg_quote($contract['version'], '/') . '\s*$/mi', $entrySource)) {
			$pluginFailures[] = 'version marker does not match ' . $contract['version'];
		}
		$helperSource = (string) file_get_contents($helper);
		if (strpos($helperSource, "'bvmgr_'") === false || !preg_match('/function_exists\(\s*\$legacy_name\s*\)/', $helperSource)) {
			$pluginFailures[] = 'helper does not prefer public bvmgr_* with legacy fallback';
		}
	}

	$legacyFunctions = array_fill_keys($contract['functions'], true);
	$legacyClasses = array_fill_keys($contract['classes'], true);
	$legacyConstants = array_fill_keys($contract['constants'], true);
	foreach ($phpFiles($root) as $path) {
		$relative = str_replace('\\', '/', substr($path, strlen($root) + 1));
		if (strpos('/' . $relative, '/tests/') !== false) {
			continue;
		}
		$tokens = token_get_all((string) file_get_contents($path));
		$count = count($tokens);
		for ($i = 0; $i < $count; $i++) {
			$token = $tokens[$i];
			if (!is_array($token) || $token[0] !== T_STRING) {
				continue;
			}
			$name = $token[1];
			if ($name === 'function_exists') {
				$j = $i + 1;
				while ($j < $count && (!is_array($tokens[$j]) || $tokens[$j][0] !== T_CONSTANT_ENCAPSED_STRING)) {
					if ($tokens[$j] === ')') {
						break;
					}
					$j++;
				}
				if ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_CONSTANT_ENCAPSED_STRING) {
					$checkedName = trim($tokens[$j][1], "'\"");
					if (isset($legacyFunctions[$checkedName])) {
						$pluginFailures[] = $relative . ':' . $token[2] . ' directly checks legacy function ' . $checkedName;
					}
				}
			}
			if (isset($legacyConstants[$name]) || isset($legacyClasses[$name])) {
				$pluginFailures[] = $relative . ':' . $token[2] . ' directly consumes legacy symbol ' . $name;
			}
			if (!isset($legacyFunctions[$name])) {
				continue;
			}
			$j = $i + 1;
			while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
				$j++;
			}
			if ($j < $count && $tokens[$j] === '(') {
				$pluginFailures[] = $relative . ':' . $token[2] . ' directly calls legacy function ' . $name;
			}
		}
	}

	foreach ($contract['functions'] as $legacyName) {
		$publicName = 'bvmgr_' . substr($legacyName, 4);
		if (!isset($declarations['functions'][$publicName])) {
			$pluginFailures[] = 'public BVM function is absent: ' . $publicName;
		}
	}
	foreach ($contract['classes'] as $legacyName) {
		$publicName = 'BVMGR_' . substr($legacyName, 4);
		if (!isset($declarations['classes'][$publicName])) {
			$pluginFailures[] = 'public BVM class is absent: ' . $publicName;
		}
	}
	foreach ($contract['constants'] as $legacyName) {
		$publicName = 'BVMGR_' . substr($legacyName, 4);
		if (!isset($declarations['constants'][$publicName])) {
			$pluginFailures[] = 'public BVM constant is absent: ' . $publicName;
		}
	}

	$pluginFailures = array_values(array_unique($pluginFailures));
	$results[$slug] = array(
		'version' => $contract['version'],
		'functions_checked' => count($contract['functions']),
		'classes_checked' => count($contract['classes']),
		'constants_checked' => count($contract['constants']),
		'status' => $pluginFailures === array() ? 'PASS' : 'FAIL',
		'failures' => $pluginFailures,
	);
	if ($pluginFailures !== array()) {
		$failures[$slug] = $pluginFailures;
	}
}

echo json_encode(array(
	'public_bvm_root' => $bvmRoot,
	'release_root' => $releaseRoot,
	'plugins' => $results,
	'status' => $failures === array() ? 'PASS' : 'FAIL',
), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

exit($failures === array() ? 0 : 1);
