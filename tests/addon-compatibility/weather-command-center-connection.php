<?php
declare(strict_types=1);

$repoRoot = dirname(__DIR__, 2);
$mirror = $repoRoot . '/includes/admin/event-command-center.php';
$live = dirname($repoRoot, 2) . '/backstage-venue-manager/includes/admin/event-command-center.php';
$weatherMenu = $repoRoot . '/companion-plugins/vmsx-weather-risk/includes/admin/menu.php';

$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
	if (!$condition) {
		$failures[] = $message;
	}
};

foreach (array($mirror, $live, $weatherMenu) as $path) {
	$assert(is_file($path), "Required source is missing: {$path}");
}

$mirrorSource = is_file($mirror) ? (string) file_get_contents($mirror) : '';
$liveSource = is_file($live) ? (string) file_get_contents($live) : '';
$weatherSource = is_file($weatherMenu) ? (string) file_get_contents($weatherMenu) : '';

$assert($mirrorSource === $liveSource, 'Mirror and active-local Command Center sources are not synchronized.');
$assert(strpos($weatherSource, "public const SETTINGS_SLUG = 'vms-weather-risk-settings';") !== false, 'Weather no longer declares the accepted canonical settings slug.');
$assert(strpos($weatherSource, "public const LEGACY_SETTINGS_SLUG = 'vmsx-weather-risk-settings';") !== false, 'Weather no longer declares its bounded legacy settings slug.');

$activeStart = strpos($mirrorSource, "if (!function_exists('bvmgr_event_command_center_is_weather_addon_active'))");
$urlStart = strpos($mirrorSource, "if (!function_exists('bvmgr_event_command_center_get_weather_url'))");
$headerStart = strpos($mirrorSource, "if (!function_exists('bvmgr_event_command_center_get_plan_header'))");
$assert($activeStart !== false && $urlStart !== false && $headerStart !== false, 'Could not isolate the Command Center Weather helpers.');

if ($activeStart !== false && $urlStart !== false && $headerStart !== false) {
	$activeHelper = substr($mirrorSource, $activeStart, $urlStart - $activeStart);
	$urlHelper = substr($mirrorSource, $urlStart, $headerStart - $urlStart);
	foreach (array('vms-weather-risk-settings', 'vmsx-weather-risk-settings') as $slug) {
		$assert(strpos($activeHelper, $slug) !== false, "Weather active detection omitted {$slug}.");
		$assert(strpos($urlHelper, $slug) !== false, "Weather URL resolution omitted {$slug}.");
	}
	$assert(strpos($activeHelper, 'vms-weather-risk-settings') < strpos($activeHelper, 'vmsx-weather-risk-settings'), 'Weather active detection is not canonical-first.');
	$assert(strpos($urlHelper, 'vms-weather-risk-settings') < strpos($urlHelper, 'vmsx-weather-risk-settings'), 'Weather URL resolution is not canonical-first.');
}

if ($failures !== array()) {
	fwrite(STDERR, "Weather Command Center connection failures:\n- " . implode("\n- ", $failures) . "\n");
	exit(1);
}

echo "Weather Command Center canonical-first/legacy-fallback connection passed in mirror and active-local BVM.\n";
