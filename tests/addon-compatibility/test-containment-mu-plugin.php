<?php
/**
 * Plugin Name: BVM Test Containment Guard
 * Description: Test-only, time-limited HTTP and cron containment for disposable compatibility runs.
 *
 * This file is copied into a runtime only for the bounded duration of a test.
 * It is excluded from public packages with the rest of tests/.
 */

declare(strict_types=1);

$bvmTestContainmentStatePath = __DIR__ . '/bvm-test-containment-state.json';
$bvmTestContainmentStateRaw  = is_readable($bvmTestContainmentStatePath)
	? file_get_contents($bvmTestContainmentStatePath)
	: false;
$bvmTestContainmentState     = is_string($bvmTestContainmentStateRaw)
	? json_decode($bvmTestContainmentStateRaw, true)
	: null;

if (!is_array($bvmTestContainmentState)) {
	return;
}

$bvmTestContainmentToken    = (string) ($bvmTestContainmentState['token'] ?? '');
$bvmTestContainmentExpires  = (int) ($bvmTestContainmentState['expires_utc'] ?? 0);
$bvmTestContainmentEvidence = (string) ($bvmTestContainmentState['evidence_log'] ?? '');
$bvmTestContainmentNormalRoot = rtrim(str_replace('\\', '/', (string) ($bvmTestContainmentState['normal_root'] ?? '')), '/');
$bvmTestContainmentPreservedTransients = is_array($bvmTestContainmentState['normal_preserved_transients'] ?? null)
	? $bvmTestContainmentState['normal_preserved_transients']
	: array();
$bvmTestContainmentPreservedOptions = is_array($bvmTestContainmentState['normal_preserved_options'] ?? null)
	? $bvmTestContainmentState['normal_preserved_options']
	: array();

if (
	preg_match('/\A[a-f0-9]{32}\z/', $bvmTestContainmentToken) !== 1
	|| $bvmTestContainmentExpires < time()
	|| $bvmTestContainmentExpires > time() + 7200
	|| $bvmTestContainmentEvidence === ''
	|| !is_dir(dirname($bvmTestContainmentEvidence))
	|| $bvmTestContainmentNormalRoot === ''
	|| !is_dir($bvmTestContainmentNormalRoot)
) {
	return;
}

$bvmTestContainmentRecord = static function (string $event, array $details = array()) use (
	$bvmTestContainmentEvidence,
	$bvmTestContainmentToken
): void {
	$record = array_merge(
		array(
			'event'        => $event,
			'token_sha256' => hash('sha256', $bvmTestContainmentToken),
			'runtime_sha256' => hash('sha256', defined('ABSPATH') ? (string) ABSPATH : __DIR__),
			'doing_cron'   => defined('DOING_CRON') && DOING_CRON,
		),
		$details
	);
	$encoded = json_encode($record, JSON_UNESCAPED_SLASHES);
	if (is_string($encoded)) {
		file_put_contents($bvmTestContainmentEvidence, $encoded . "\n", FILE_APPEND | LOCK_EX);
	}
};

$bvmTestContainmentRecord('guard_loaded');

$bvmTestContainmentCurrentRoot = rtrim(str_replace('\\', '/', defined('ABSPATH') ? (string) ABSPATH : ''), '/');
$bvmTestContainmentIsNormalRoot = $bvmTestContainmentCurrentRoot === $bvmTestContainmentNormalRoot;
$bvmTestContainmentIsAuthorizedCli = defined('WP_CLI')
	&& WP_CLI
	&& hash_equals($bvmTestContainmentToken, (string) getenv('BVM_TEST_CONTAINMENT_TOKEN'));

// Normal-site acceptance probes are intentionally allowed only from the
// tokenized CLI process. Keep BVM's bounded performance telemetry read-only in
// that process so a verification boot cannot append operational option state.
if ($bvmTestContainmentIsNormalRoot && $bvmTestContainmentIsAuthorizedCli) {
	add_filter('vms_resource_fingerprint_threshold_seconds', static fn(): float => PHP_FLOAT_MAX, -PHP_INT_MAX);
	add_filter('vms_resource_fingerprint_memory_threshold_bytes', static fn(): int => PHP_INT_MAX, -PHP_INT_MAX);
	add_filter(
		'pre_update_option_vms_resource_fingerprint_log',
		static fn($value, $oldValue) => $oldValue,
		-PHP_INT_MAX,
		2
	);
	foreach (array('transient' => '_transient_', 'site_transient' => '_site_transient_') as $kind => $optionPrefix) {
		$transientNames = is_array($bvmTestContainmentPreservedTransients[$kind] ?? null)
			? $bvmTestContainmentPreservedTransients[$kind]
			: array();
		foreach ($transientNames as $transientName) {
			if (!is_string($transientName) || preg_match('/\A[a-zA-Z0-9_.-]+\z/', $transientName) !== 1) {
				continue;
			}
			add_filter(
				"pre_{$kind}_{$transientName}",
				static fn() => get_option($optionPrefix . $transientName, false),
				-PHP_INT_MAX
			);
		}
	}
	foreach ($bvmTestContainmentPreservedOptions as $optionName) {
		if (!is_string($optionName) || preg_match('/\A[a-zA-Z0-9_.-]+\z/', $optionName) !== 1) {
			continue;
		}
		add_filter(
			"pre_update_option_{$optionName}",
			static fn($value, $oldValue) => $oldValue,
			-PHP_INT_MAX,
			2
		);
	}
	$bvmTestContainmentRecord('normal_cli_telemetry_contained');
}

// Freeze every independently launched normal-site process for the bounded run.
// The initiating harness carries the random token; Local FPM, direct wp-cron,
// and unrelated CLI processes do not. The disposable runtime remains usable.
if ($bvmTestContainmentIsNormalRoot && !$bvmTestContainmentIsAuthorizedCli) {
	$event = defined('DOING_CRON') && DOING_CRON
		? 'normal_cron_process_blocked'
		: ((defined('WP_CLI') && WP_CLI) ? 'normal_cli_process_blocked' : 'normal_web_process_blocked');
	$bvmTestContainmentRecord($event);
	if (!(defined('WP_CLI') && WP_CLI) && !headers_sent()) {
		http_response_code(503);
		header('Content-Type: text/plain; charset=UTF-8');
		header('Retry-After: 60');
	}
	echo "Local compatibility test containment is active.\n";
	exit;
}

add_filter(
	'pre_http_request',
	static function ($preempt, array $parsedArgs, string $url) use ($bvmTestContainmentRecord) {
		unset($preempt, $parsedArgs);
		$host = parse_url($url, PHP_URL_HOST);
		$bvmTestContainmentRecord(
			'http_blocked',
			array('host' => is_string($host) ? strtolower($host) : '')
		);

		return new WP_Error(
			'bvm_test_containment_http_blocked',
			'External HTTP is disabled by the bounded BVM compatibility-test guard.'
		);
	},
	-PHP_INT_MAX,
	3
);

add_filter(
	'pre_get_ready_cron_jobs',
	static function ($preempt) use ($bvmTestContainmentRecord): array {
		unset($preempt);
		$bvmTestContainmentRecord('cron_blocked');
		return array();
	},
	-PHP_INT_MAX
);
