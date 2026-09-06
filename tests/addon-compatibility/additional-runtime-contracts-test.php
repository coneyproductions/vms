<?php
declare(strict_types=1);

$contracts = require __DIR__ . '/additional-runtime-contracts.php';
$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
	if (!$condition) {
		$failures[] = $message;
	}
};

$expectedVersions = array(
	'drm-calendar-intake' => '0.2.4',
	'vms-commerce-discounts' => '0.2.13',
	'vms-investor-portal' => '0.2.3',
	'vms-meta-ads' => '0.1.107',
	'vms-ops-console-premium' => '0.1.68',
	'vms-season-passes' => '0.1.0',
	'vms-sponsorships' => '0.1.28',
	'vmsx-checkout-policies' => '0.1.8',
	'vmsx-weather-risk' => '0.1.12',
	'drm-events-bridge' => '0.2.2',
);

$assert(array_keys((array) ($contracts['plugins'] ?? array())) === array_keys($expectedVersions), 'The supported direct-runtime set must be explicit and ordered.');
$assert(($contracts['blocked'] ?? null) === array(), 'No accepted supported profile may remain blocked.');
$assert(isset($contracts['retired']['vms-safety-pro']), 'Safety Toolkit must remain explicit retired history.');
$assert(!isset($contracts['plugins']['vms-safety-pro']), 'Safety Toolkit must not be a supported runtime target.');
$assert(($contracts['indirect']['drm-event-router']['version'] ?? '') === '0.1.3', 'Router 0.1.3 must be the current authoritative Bridge fixture.');

$requiredFields = array(
	'name', 'version', 'entry', 'dependency', 'companions', 'marker', 'capabilities',
	'hooks', 'hook_callbacks', 'menus', 'fallback_menus', 'notice', 'post_types',
	'taxonomies', 'meta', 'options', 'tables', 'rest_namespaces', 'ajax_actions', 'cron_hooks',
);
$capabilityFields = array('id', 'stage', 'canonical_provider', 'legacy_fallback', 'external_dependency_owner', 'guard', 'expected_outcome');
$semanticCount = 0;
$persistentCount = 0;

foreach ((array) ($contracts['plugins'] ?? array()) as $slug => $plugin) {
	$assert(($plugin['version'] ?? '') === ($expectedVersions[$slug] ?? null), $slug . ' must use its approved version.');
	$assert(($plugin['entry'] ?? '') === $slug . '/' . basename((string) ($plugin['entry'] ?? '')), $slug . ' entry must use its staged public directory.');
	foreach ($requiredFields as $field) {
		$assert(array_key_exists($field, $plugin), $slug . ' is missing contract field ' . $field . '.');
	}
	$assert(!array_key_exists('functions', $plugin) && !array_key_exists('constants', $plugin), $slug . ' must not flatten historical symbols into required BVM declarations.');
	$assert(is_array($plugin['companions']['required'] ?? null) && is_array($plugin['companions']['optional'] ?? null), $slug . ' dependencies must distinguish required and optional owners.');
	$assert(is_array($plugin['notice']['owner_patterns'] ?? null) && ($plugin['notice']['owner_patterns'] ?? array()) !== array(), $slug . ' must declare package-specific notice ownership.');

	foreach ((array) ($plugin['capabilities'] ?? array()) as $capability) {
		++$semanticCount;
		foreach ($capabilityFields as $field) {
			$assert(array_key_exists($field, $capability), $slug . ' semantic capability is missing ' . $field . '.');
		}
		$assert(in_array((string) ($capability['stage'] ?? ''), array('bootstrap', 'registration', 'feature path'), true), $slug . ' capability has an invalid stage.');
		$canonical = (string) ($capability['canonical_provider'] ?? '');
		$assert($canonical === '' || strpos($canonical, 'vms_') !== 0, $slug . ' canonical provider must not be a historical vms_* name.');
		$assert((string) ($capability['guard'] ?? '') !== '' && (string) ($capability['expected_outcome'] ?? '') !== '', $slug . ' capability must state guard and outcome semantics.');
	}

	foreach ((array) ($plugin['menus'] ?? array()) as $menu) {
		$assert(array_key_exists('registry', $menu), $slug . ' menu must state whether BVM registry ownership is expected.');
	}

	foreach (array('post_types', 'taxonomies', 'meta', 'options', 'tables', 'rest_namespaces', 'ajax_actions', 'cron_hooks') as $field) {
		$list = $plugin[$field] ?? null;
		$assert(is_array($list), $slug . ' persistent/runtime identifier field ' . $field . ' must be an array.');
		if (is_array($list)) {
			$assert(count($list) === count(array_unique($list)), $slug . ' ' . $field . ' contains duplicates.');
			$persistentCount += count($list);
		}
	}
}

$assert(($contracts['plugins']['vms-commerce-discounts']['companions']['optional'] ?? array()) === array('woocommerce-square', 'the-events-calendar', 'event-tickets', 'event-tickets-plus'), 'Commerce must classify Square as optional.');
$assert(($contracts['plugins']['vms-season-passes']['companions']['optional'] ?? array()) === array('woocommerce', 'vms-ops-console-premium'), 'Season must keep WooCommerce and Ops optional.');
$assert(($contracts['plugins']['vmsx-weather-risk']['companions']['optional'] ?? array()) === array('vms-data-tools'), 'Weather must keep Data Tools optional.');

$bvmRoot = dirname(__DIR__, 2);
$bvmSource = '';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($bvmRoot . '/includes', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $fileInfo) {
	if ($fileInfo->isFile() && $fileInfo->getExtension() === 'php') {
		$bvmSource .= "\n" . (string) file_get_contents($fileInfo->getPathname());
	}
}
$isEmitted = static function (string $hook) use ($bvmSource): bool {
	return preg_match('/(?:do_action|apply_filters)\s*\(\s*([\'\"])' . preg_quote($hook, '/') . '\1/', $bvmSource) === 1;
};
foreach ((array) ($contracts['hook_emission']['bvm_emitted'] ?? array()) as $hook) {
	$assert($isEmitted((string) $hook), $hook . ' is classified BVM-emitted but no emitter exists.');
}
foreach ((array) ($contracts['hook_emission']['historical_dead'] ?? array()) as $hook) {
	$assert(!$isEmitted((string) $hook), $hook . ' is classified historical/dead but a BVM emitter exists.');
}

if ($failures !== array()) {
	fwrite(STDERR, "Additional semantic contract failures:\n- " . implode("\n- ", $failures) . "\n");
	exit(1);
}

echo sprintf(
	"Additional semantic runtime contracts passed: %d supported / %d retired / %d capabilities / %d persistent identifiers.\n",
	count($contracts['plugins']), count($contracts['retired']), $semanticCount, $persistentCount
);
