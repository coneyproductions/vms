<?php
declare(strict_types=1);

/** Installed-path regression for the active BVM root and dependency authorities. */

$sourceRoot = dirname(__DIR__);
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
	$checks++;
	if (!$condition) {
		throw new RuntimeException($message);
	}
};
$normalize = static function (string $path): string {
	return str_replace('\\', '/', $path);
};
$removeTree = static function (string $root): void {
	if (!is_dir($root)) {
		return;
	}
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ($iterator as $entry) {
		$path = $entry->getPathname();
		if ($entry->isLink() || $entry->isFile()) {
			unlink($path);
		} else {
			rmdir($path);
		}
	}
	rmdir($root);
};

$staleCheckPath = $sourceRoot . '/includes/core/cli/stale-check.php';
$performancePath = $sourceRoot . '/includes/core/event-plan-performance.php';
$bootstrapPath = $sourceRoot . '/backstage-venue-manager.php';
$staleCheckSource = (string) file_get_contents($staleCheckPath);
$performanceSource = (string) file_get_contents($performancePath);
$bootstrapSource = (string) file_get_contents($bootstrapPath);

$assert(strpos($bootstrapSource, "define('BVMGR_PLUGIN_PATH', plugin_dir_path(__FILE__));") !== false, 'BVMGR_PLUGIN_PATH must derive from the active plugin entry file.');
$assert(substr_count($staleCheckSource, "BVMGR_PLUGIN_PATH . '") === 12, 'Every stale-check source/asset read must use the active plugin root.');
$assert(strpos($staleCheckSource, "WP_CONTENT_DIR . '/plugins/vms/") === false, 'Stale-check must not read the legacy installed vms sibling.');

$dependencyStart = strpos($performanceSource, "if (!function_exists('bvmgr_event_plan_perf_dependency_snapshot'))");
$dependencyEnd = strpos($performanceSource, "if (!function_exists('bvmgr_event_plan_perf_memory_checkpoint'))", $dependencyStart === false ? 0 : $dependencyStart);
$assert($dependencyStart !== false && $dependencyEnd !== false, 'Dependency snapshot source block must remain discoverable.');
$dependencySource = substr($performanceSource, (int) $dependencyStart, (int) $dependencyEnd - (int) $dependencyStart);
$assert(strpos($dependencySource, "wp_normalize_path(BVMGR_PLUGIN_PATH)") !== false, 'BVM dependency attribution must use the active plugin root.');
$assert(strpos($dependencySource, "defined('WC_ABSPATH')") !== false, 'WooCommerce dependency attribution must use WC_ABSPATH when available.');
foreach (array('TRIBE_EVENTS_FILE', 'EVENT_TICKETS_MAIN_PLUGIN_FILE', 'EVENT_TICKETS_PLUS_FILE') as $constant) {
	$assert(strpos($dependencySource, "defined('" . $constant . "')") !== false, $constant . ' must remain an explicit dependency authority.');
}
foreach (array('/plugins/vms/', '/plugins/woocommerce/', '/plugins/the-events-calendar/', '/plugins/event-tickets/', '/plugins/event-tickets-plus/') as $legacyFragment) {
	$assert(strpos($dependencySource, $legacyFragment) === false, 'Dependency attribution must not infer ownership from ' . $legacyFragment . '.');
}

$tempRoot = sys_get_temp_dir() . '/bvm-installed-paths-' . bin2hex(random_bytes(8));
$requiredPaths = array(
	'includes/admin/schedule.php',
	'includes/cpt/event-plans.php',
	'includes/schedule/helpers.php',
	'assets/vms-ticketing-front.js',
	'assets/css/vms-ticketing-front.css',
	'includes/integrations/ticketing-rules-v2.php',
);
$layoutRoots = array(
	'isolated reconstruction worktree' => $sourceRoot,
	'normal source repository' => $tempRoot . '/packages/vms-github-reconcile',
	'installed backstage-venue-manager plugin' => $tempRoot . '/wordpress/wp-content/plugins/backstage-venue-manager',
	'public release package' => $tempRoot . '/release/backstage-venue-manager',
);

try {
	foreach ($layoutRoots as $label => $root) {
		if ($root !== $sourceRoot) {
			foreach ($requiredPaths as $relativePath) {
				$path = $root . '/' . $relativePath;
				if (!is_dir(dirname($path))) {
					mkdir(dirname($path), 0700, true);
				}
				file_put_contents($path, str_ends_with($path, '.php') ? "<?php\n// synthetic path fixture\n" : "synthetic path fixture\n");
			}
		}

		$normalizedRoot = rtrim($normalize($root), '/') . '/';
		foreach ($requiredPaths as $relativePath) {
			$resolved = $normalizedRoot . $relativePath;
			$assert(strpos($resolved, $normalizedRoot) === 0 && is_file($resolved), $label . ' must resolve ' . $relativePath . ' inside its own plugin root.');
		}
	}

	$installedRoot = (string) realpath($layoutRoots['installed backstage-venue-manager plugin']) . '/';
	$wooRoot = $tempRoot . '/wordpress/wp-content/plugins/shop-custom/';
	$tecRoot = $tempRoot . '/wordpress/wp-content/plugins/calendar-custom/';
	$ticketsRoot = $tempRoot . '/wordpress/wp-content/plugins/tickets-custom/';
	$ticketsPlusRoot = $tempRoot . '/wordpress/wp-content/plugins/tickets-plus-custom/';
	$deceptiveRoot = $tempRoot . '/unrelated/plugins/vms/';
	foreach (array($wooRoot . 'woocommerce.php', $tecRoot . 'calendar.php', $ticketsRoot . 'tickets.php', $ticketsPlusRoot . 'plus.php', $deceptiveRoot . 'false-positive.php') as $fixture) {
		if (!is_dir(dirname($fixture))) {
			mkdir(dirname($fixture), 0700, true);
		}
		file_put_contents($fixture, "<?php\n// synthetic included-file fixture\n");
	}
	$wooRoot = (string) realpath($wooRoot) . '/';
	$tecRoot = (string) realpath($tecRoot) . '/';
	$ticketsRoot = (string) realpath($ticketsRoot) . '/';
	$ticketsPlusRoot = (string) realpath($ticketsPlusRoot) . '/';

	define('ABSPATH', $tempRoot . '/wordpress/');
	define('BVMGR_PLUGIN_PATH', $installedRoot);
	define('WC_ABSPATH', $wooRoot);
	define('TRIBE_EVENTS_FILE', $tecRoot . 'calendar.php');
	define('EVENT_TICKETS_MAIN_PLUGIN_FILE', $ticketsRoot . 'tickets.php');
	define('EVENT_TICKETS_PLUS_FILE', $ticketsPlusRoot . 'plus.php');
	function wp_normalize_path(string $path): string { return str_replace('\\', '/', $path); }
	function plugin_dir_path(string $path): string { return rtrim(dirname($path), '/\\') . '/'; }
	function add_action(...$args): bool { return true; }
	function add_filter(...$args): bool { return true; }

	require $installedRoot . 'includes/admin/schedule.php';
	require $installedRoot . 'includes/cpt/event-plans.php';
	require $wooRoot . 'woocommerce.php';
	require TRIBE_EVENTS_FILE;
	require EVENT_TICKETS_MAIN_PLUGIN_FILE;
	require EVENT_TICKETS_PLUS_FILE;
	require $deceptiveRoot . 'false-positive.php';
	require $performancePath;

	$snapshot = bvmgr_event_plan_perf_dependency_snapshot();
	$assert(($snapshot['included_vms_file_count'] ?? -1) === 2, 'BVM included-file count must follow BVMGR_PLUGIN_PATH and reject a deceptive /plugins/vms/ path.');
	$assert(($snapshot['included_woo_file_count'] ?? -1) === 1, 'WooCommerce included-file count must follow WC_ABSPATH.');
	$assert(($snapshot['included_tec_file_count'] ?? -1) === 3, 'TEC included-file count must follow the three declared main-plugin file roots.');
} finally {
	$removeTree($tempRoot);
}

fwrite(STDOUT, 'PASS: ' . $checks . " installed-plugin path assertions across four layouts.\n");
