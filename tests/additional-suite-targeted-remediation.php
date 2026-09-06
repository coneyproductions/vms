<?php
declare(strict_types=1);

$repoRoot = dirname(__DIR__);
$pluginRoot = dirname($repoRoot, 2);
$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
	if (!$condition) {
		$failures[] = $message;
	}
};
$source = static fn(string $path): string => (string) file_get_contents($path);
$treeHash = static function (string $root): string {
	$files = array();
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
	foreach ($iterator as $fileInfo) {
		if (!$fileInfo->isFile() || $fileInfo->isLink()) {
			continue;
		}
		$relative = str_replace('\\', '/', substr($fileInfo->getPathname(), strlen($root) + 1));
		$files[$relative] = hash_file('sha256', $fileInfo->getPathname());
	}
	ksort($files, SORT_STRING);
	$context = hash_init('sha256');
	foreach ($files as $relative => $hash) {
		hash_update($context, $relative . "\0" . $hash . "\n");
	}
	return hash_final($context);
};

$contracts = require $repoRoot . '/tests/addon-compatibility/additional-runtime-contracts.php';
$assert(array_keys($contracts['plugins']) === array(
	'drm-calendar-intake', 'vms-commerce-discounts', 'vms-investor-portal', 'vms-meta-ads',
	'vms-ops-console-premium', 'vms-season-passes', 'vms-sponsorships',
	'vmsx-checkout-policies', 'vmsx-weather-risk', 'drm-events-bridge',
), 'Supported profile set must exclude retired Safety and include accepted Bridge.');
$assert(($contracts['retired']['vms-safety-pro']['status'] ?? '') !== '', 'Safety must remain retired metadata.');
$assert(in_array('vms_addons_manifest_entries', $contracts['hook_emission']['historical_dead'] ?? array(), true), 'The add-ons manifest hook must remain classified dead.');
$assert(in_array('vms_event_plan_after_modules', $contracts['hook_emission']['historical_dead'] ?? array(), true), 'The event-plan after-modules hook must remain classified dead.');

$seasonCompat = $source($pluginRoot . '/vms-season-passes/includes/core-compat.php');
$seasonBootstrap = $source($pluginRoot . '/vms-season-passes/vms-season-passes.php');
$seasonLoad = $source($pluginRoot . '/vms-season-passes/includes/load.php');
$seasonAdmin = $source($pluginRoot . '/vms-season-passes/includes/admin.php');
$seasonScanner = $source($pluginRoot . '/vms-season-passes/includes/scanner.php');
$assert(strpos($seasonCompat, "preg_replace('/^vms_/', 'bvmgr_'") !== false, 'Season resolver must prefer canonical bvmgr_* providers.');
$assert(strpos($seasonCompat, "defined('BVMGR_VERSION')") < strpos($seasonCompat, "defined('VMS_VERSION')"), 'Season must resolve BVMGR_VERSION before VMS_VERSION.');
$assert(strpos($seasonBootstrap, "require_once VMS_SEASON_PASSES_PLUGIN_DIR . 'includes/core-compat.php'") !== false, 'Season must load compatibility before discovery.');
$assert(strpos($seasonBootstrap, 'if (!vms_season_passes_should_boot())') !== false, 'Season activation must stay inert before schema/capability work when core is absent.');
$assert(strpos($seasonLoad, "vms_season_passes_core_function('vms_register_module')") !== false, 'Season module registration must use the resolver.');
$assert(strpos($seasonAdmin, "vms_season_passes_core_function('vms_register_admin_page')") !== false, 'Season admin registry must use the resolver.');
$assert(strpos($seasonAdmin, "vms_season_passes_core_function('vms_admin_ui_render_shell')") !== false, 'Season shell rendering must use the resolver.');
$assert(strpos($seasonScanner, "vms_season_passes_core_function('vms_get_event_plan_for_tec_event')") !== false, 'Season TEC scanning must use the resolver.');

$weatherArchive = $pluginRoot . '/VMS WEATHER RISK ZIP ARCHIVES/vmsx-weather-risk-0.1.12-title-cleanup.zip';
$weatherRoot = $repoRoot . '/companion-plugins/vmsx-weather-risk';
$assert(hash_file('sha256', $weatherArchive) === '5d57006c4ef190b5ac7786abce15f2585b4ffb302831451399224b0ef03ade28', 'Weather frozen archive SHA-256 must match the approved 0.1.12 baseline.');
$assert($treeHash($weatherRoot) === 'e019619ce1bd5bef851dbdb4573fd527332983cda34004bd5fc531ae50cc5c0b', 'Accepted Weather 0.1.12 candidate tree changed.');
$assert($treeHash($pluginRoot . '/vmsx-weather-risk') === 'e019619ce1bd5bef851dbdb4573fd527332983cda34004bd5fc531ae50cc5c0b', 'Installed Weather tree must match the promoted 0.1.12 candidate.');
$weatherCompat = $source($weatherRoot . '/includes/compatibility.php');
$weatherBootstrap = $source($weatherRoot . '/includes/bootstrap.php');
$weatherScheduler = $source($weatherRoot . '/includes/cache/scheduler.php');
$assert(strpos($weatherCompat, "core_constant('BVMGR_PLUGIN_PATH', 'VMS_PLUGIN_PATH'") !== false, 'Weather must resolve canonical BVM plugin path first.');
$assert(strpos($weatherCompat, "core_constant('BVMGR_VERSION', 'VMS_VERSION'") !== false, 'Weather must resolve canonical BVM version first.');
$assert(strpos($weatherBootstrap, 'if (!VMSX_Weather_Risk_Compatibility::is_ready())') !== false, 'Weather activation must avoid orphaned state without core readiness.');
$assert(strpos($weatherBootstrap, "core_function('vms_register_module')") !== false, 'Weather module registration must use the resolver.');
$assert(strpos($weatherScheduler, "add_filter('cron_schedules'") !== false, 'Weather activation must register its custom schedule before scheduling the event.');

$sponsorshipCompat = $source($pluginRoot . '/vms-sponsorships/includes/core-compat.php');
$sponsorshipAdmin = $source($pluginRoot . '/vms-sponsorships/includes/class-vms-sponsorships-admin.php');
$assert(strpos($sponsorshipCompat, "preg_replace('/^vms_/', 'bvmgr_'") !== false, 'Sponsorship must resolve canonical providers first.');
$assert(strpos($sponsorshipAdmin, "vms_sponsorships_core_function('vms_register_admin_page')") !== false, 'Sponsorship must populate canonical registry metadata.');
$assert(strpos($sponsorshipAdmin, 'if ($this->canonical_registry_registered)') !== false, 'Sponsorship must suppress fallback rows after canonical registration.');

$checkout = $source($pluginRoot . '/vmsx-checkout-policies/vmsx-checkout-policies.php');
$assert(strpos($checkout, "defined('BVMGR_VERSION')") !== false, 'Checkout must detect canonical BVM identity.');
$assert(strpos($checkout, "core_function('vms_render_settings_page')") !== false, 'Checkout must select the canonical settings renderer first.');
$assert(strpos($checkout, "core_function('vms_ticketing_v2_meta_get')") !== false, 'Checkout must select canonical ticket meta helpers first.');

$syntheticNotices = array(
	'VMS Season Passes is installed but inactive. Activate Venue Management System first.',
	'Show Risk Advisor is installed but not active. Show Risk Advisor requires the VMS core plugin.',
);
$owned = array();
foreach ($contracts['plugins'] as $slug => $contract) {
	$owned[$slug] = array_values(array_filter($syntheticNotices, static function (string $notice) use ($contract): bool {
		foreach ($contract['notice']['owner_patterns'] as $pattern) {
			if ($pattern !== '' && stripos($notice, (string) $pattern) !== false) {
				return true;
			}
		}
		return false;
	}));
}
$assert(count($owned['vms-season-passes']) === 1 && count($owned['vmsx-weather-risk']) === 1, 'Season and Weather notices must be attributed to their owners.');
$assert($owned['drm-calendar-intake'] === array() && $owned['vms-commerce-discounts'] === array() && $owned['vms-meta-ads'] === array(), 'Unrelated packages must not inherit Season or Weather notices.');

if ($failures !== array()) {
	fwrite(STDERR, "Additional-suite targeted remediation failures:\n- " . implode("\n- ", $failures) . "\n");
	exit(1);
}

echo "Additional-suite targeted remediation source and ownership tests passed.\n";
