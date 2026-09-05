<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/scripts/lib/wporg-prefix-migration-state.php';

$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
	if (!$condition) {
		$failures[] = $message;
	}
};

$store = array();
$reads = static function (int $siteId, string $key, $default = null) use (&$store) {
	return $store[$siteId][$key] ?? $default;
};
$writes = static function (int $siteId, string $key, $value) use (&$store): bool {
	$store[$siteId][$key] = $value;
	return true;
};
$exists = static function (int $siteId, string $key) use (&$store): bool {
	return array_key_exists($key, $store[$siteId] ?? array());
};

$calls = array('copy' => 0, 'resume' => 0);
$steps = array(
	'copy' => static function () use (&$calls): array {
		$calls['copy']++;
		return array('complete' => true);
	},
	'resume' => static function () use (&$calls): array {
		$calls['resume']++;
		return $calls['resume'] === 1
			? array('complete' => false, 'cursor' => 25)
			: array('complete' => true);
	},
);

$first = BVMGR_Prefix_Migration_State::runSite(1, 1, $steps, $reads, $writes);
$assert($first['status'] === 'interrupted', 'First run must checkpoint an incomplete step.');
$assert(!$exists(1, BVMGR_Prefix_Migration_State::VERSION_OPTION), 'Final marker must not be written before all steps complete.');
$assert(($store[1][BVMGR_Prefix_Migration_State::JOURNAL_OPTION]['cursor'] ?? null) === 25, 'Retry cursor must persist.');
$assert($calls === array('copy' => 1, 'resume' => 1), 'First run must execute each reached step once.');

$second = BVMGR_Prefix_Migration_State::runSite(1, 1, $steps, $reads, $writes);
$assert($second['status'] === 'complete' && !$second['already_complete'], 'Retry must complete the interrupted site.');
$assert(($store[1][BVMGR_Prefix_Migration_State::VERSION_OPTION] ?? 0) === 1, 'Independent migration version marker must finalize after steps.');
$assert($calls === array('copy' => 1, 'resume' => 2), 'Retry must skip completed work and resume only the incomplete step.');

$third = BVMGR_Prefix_Migration_State::runSite(1, 1, $steps, $reads, $writes);
$assert($third['already_complete'] === true, 'Completed migration must be idempotent.');
$assert($calls === array('copy' => 1, 'resume' => 2), 'Idempotent rerun must execute no steps.');

$versionTwoCalls = 0;
$versionTwo = BVMGR_Prefix_Migration_State::runSite(
	1,
	2,
	array('copy' => static function () use (&$versionTwoCalls): array {
		$versionTwoCalls++;
		return array('complete' => true);
	}),
	$reads,
	$writes
);
$assert($versionTwo['status'] === 'complete' && $versionTwoCalls === 1, 'A later migration version must not inherit completed step IDs from an older journal.');
$assert(($store[1][BVMGR_Prefix_Migration_State::VERSION_OPTION] ?? 0) === 2, 'Later migration version must advance its independent marker.');
$downgradeCalls = 0;
$downgrade = BVMGR_Prefix_Migration_State::runSite(
	1,
	1,
	array('must-not-run' => static function () use (&$downgradeCalls): array {
		$downgradeCalls++;
		return array('complete' => true);
	}),
	$reads,
	$writes
);
$assert($downgrade['already_complete'] === true && $downgradeCalls === 0, 'An older target must never downgrade or rerun a newer completed migration.');
$assert(($store[1][BVMGR_Prefix_Migration_State::VERSION_OPTION] ?? 0) === 2, 'Downgrade guard must preserve the newer marker.');

$siteTwoCalls = 0;
$siteTwo = BVMGR_Prefix_Migration_State::runSite(
	2,
	1,
	array('site-two' => static function () use (&$siteTwoCalls): array {
		$siteTwoCalls++;
		return array('complete' => true);
	}),
	$reads,
	$writes
);
$assert($siteTwo['status'] === 'complete' && $siteTwoCalls === 1, 'A second site must have isolated migration state.');
$assert(($store[1][BVMGR_Prefix_Migration_State::VERSION_OPTION] ?? null) === 2, 'Site-two migration must not alter site one.');

$network = BVMGR_Prefix_Migration_State::runNetwork(
	array(3, 4, 4),
	1,
	array('network-step' => static fn(): array => array('complete' => true)),
	$reads,
	$writes
);
$assert(array_keys($network) === array(3, 4), 'Network runner must de-duplicate and isolate site IDs.');
$assert(($store[3][BVMGR_Prefix_Migration_State::VERSION_OPTION] ?? null) === 1, 'Network runner must mark site three independently.');
$assert(($store[4][BVMGR_Prefix_Migration_State::VERSION_OPTION] ?? null) === 1, 'Network runner must mark site four independently.');

$throwCalls = 0;
$throwStep = array('fragile' => static function () use (&$throwCalls): array {
	$throwCalls++;
	if ($throwCalls === 1) {
		throw new RuntimeException('simulated interruption');
	}
	return array('complete' => true);
});
$interrupted = BVMGR_Prefix_Migration_State::runSite(5, 1, $throwStep, $reads, $writes);
$assert($interrupted['status'] === 'interrupted', 'Thrown interruption must persist retryable state.');
$recovered = BVMGR_Prefix_Migration_State::runSite(5, 1, $throwStep, $reads, $writes);
$assert($recovered['status'] === 'complete' && $throwCalls === 2, 'Interrupted step must be retryable.');

$store[6]['vms_setting'] = array('enabled' => true);
$copy = BVMGR_Prefix_Migration_State::copyBeforeCutover(6, 'vms_setting', 'bvmgr_setting', $exists, $reads, $writes);
$assert($copy === array('status' => 'copied-and-verified', 'copied' => true), 'Copy-before-cutover must copy and verify an absent canonical value.');
$assert(isset($store[6]['vms_setting']), 'Copy-before-cutover must retain the legacy value.');
$assert($store[6]['bvmgr_setting'] === $store[6]['vms_setting'], 'Canonical copy must equal legacy input.');
$copyAgain = BVMGR_Prefix_Migration_State::copyBeforeCutover(6, 'vms_setting', 'bvmgr_setting', $exists, $reads, $writes);
$assert($copyAgain['status'] === 'canonical-present', 'Copy-before-cutover must be idempotent.');

$store[7]['vms_only'] = 'legacy';
$assert(BVMGR_Prefix_Migration_State::dualRead(7, 'bvmgr_only', 'vms_only', $exists, $reads, 'default') === 'legacy', 'Dual-read must fall back to legacy.');
$store[7]['bvmgr_only'] = 'canonical';
$assert(BVMGR_Prefix_Migration_State::dualRead(7, 'bvmgr_only', 'vms_only', $exists, $reads, 'default') === 'canonical', 'Dual-read must prefer canonical.');
$assert(BVMGR_Prefix_Migration_State::canonicalWrite(7, 'bvmgr_write', 'vms_write', 'new', $writes), 'Canonical write must succeed.');
$assert(isset($store[7]['bvmgr_write']) && !isset($store[7]['vms_write']), 'Canonical write must not mirror by default.');
$assert(BVMGR_Prefix_Migration_State::canonicalWrite(7, 'bvmgr_mirror', 'vms_mirror', 'safe', $writes, true), 'Explicit rollback-safe mirrored write must succeed.');
$assert(($store[7]['bvmgr_mirror'] ?? null) === 'safe' && ($store[7]['vms_mirror'] ?? null) === 'safe', 'Rollback-safe mirror must update both exact keys.');

if ($failures !== array()) {
	fwrite(STDERR, "Prefix migration-state failures:\n- " . implode("\n- ", $failures) . "\n");
	exit(1);
}

echo "Prefix migration-state tests passed.\n";
