<?php
declare(strict_types=1);

[$script, $wordpress_root] = $argv + array(null, null);
if (!$wordpress_root || getenv('BVM_DISPOSABLE_DB_GUARDED') !== '1') {
	throw new RuntimeException('guarded disposable database required');
}
require rtrim($wordpress_root, '/') . '/wp-load.php';
global $wpdb;

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
	$assertions++;
	if (!$condition) throw new RuntimeException($message);
};
$table_names = bvmgr_admission_offers_table_names();
$normalize_create = static function (string $table) use ($wpdb): string {
	$row = $wpdb->get_row('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`', ARRAY_N);
	return preg_replace('/AUTO_INCREMENT=\d+\s*/', '', (string) ($row[1] ?? '')) ?? '';
};
$indexes = static function (string $table) use ($wpdb): array {
	$rows = $wpdb->get_results('SHOW INDEX FROM `' . str_replace('`', '``', $table) . '`', ARRAY_A);
	$result = array();
	foreach ($rows as $row) $result[(string) $row['Key_name']][] = (string) $row['Column_name'];
	return $result;
};

$version = (string) $wpdb->get_var('SELECT VERSION()');
$assert(str_starts_with($version, '8.0.35'), 'Certification requires MySQL 8.0.35.');
bvmgr_admission_offers_maybe_upgrade_schema();
$first = array();
$schema = array();
foreach ($table_names as $kind => $table) {
	$status = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS LIKE %s', $table), ARRAY_A);
	$assert(is_array($status), 'Missing table ' . $table);
	$assert(($status['Engine'] ?? '') === 'InnoDB', $table . ' must use InnoDB.');
	$assert(str_starts_with((string) ($status['Collation'] ?? ''), 'utf8mb4_'), $table . ' must use WordPress utf8mb4 collation.');
	$first[$table] = $normalize_create($table);
	$schema[$kind] = array('table' => $table, 'engine' => $status['Engine'], 'collation' => $status['Collation'], 'create' => $first[$table]);
}
$assert(count($first) === 9, 'Exactly nine tables must exist.');
$assert(isset($indexes($table_names['claims'])['distribution_campaign']), 'Campaign index missing.');
$assert(isset($indexes($table_names['claims'])['distribution_subject']), 'Subject index missing.');
$assert(isset($indexes($table_names['identities'])['scope_versions']), 'Identity version index missing.');
$assert($indexes($table_names['identities'])['scoped_identity'] === array('offer_id', 'identity_scope_key', 'identity_type', 'hash_key_version', 'identity_hash'), 'Identity unique index shape mismatch.');
$assert($indexes($table_names['allocations'])['order_item_claim'] === array('order_provider', 'order_ref', 'order_item_ref', 'claim_id'), 'Allocation unique index must use full references.');

bvmgr_admission_offers_maybe_upgrade_schema();
$second = array_map($normalize_create, $table_names);
$assert(array_values($first) === array_values($second), 'Second schema readiness run must be idempotent.');

$wpdb->query("ALTER TABLE {$table_names['claims']} DROP INDEX distribution_campaign, DROP INDEX distribution_subject");
$wpdb->query("ALTER TABLE {$table_names['identities']} DROP INDEX scoped_identity, DROP INDEX scope_versions, DROP COLUMN hash_key_version, ADD UNIQUE KEY scoped_identity (offer_id, identity_scope_key, identity_type, identity_hash)");
$wpdb->query("ALTER TABLE {$table_names['allocations']} DROP INDEX order_item_claim, ADD UNIQUE KEY order_item_claim (order_provider, order_ref(80), order_item_ref(80), claim_id)");
update_option(bvmgr_admission_offers_db_option_key(), '1.0.0', false);
bvmgr_admission_offers_maybe_upgrade_schema();
$identity_columns = $wpdb->get_col("SHOW COLUMNS FROM {$table_names['identities']}");
$assert(in_array('hash_key_version', $identity_columns, true), 'Additive upgrade must restore hash_key_version.');
$assert(isset($indexes($table_names['claims'])['distribution_campaign'], $indexes($table_names['claims'])['distribution_subject']), 'Additive upgrade must restore claim indexes.');
$assert($indexes($table_names['allocations'])['order_item_claim'] === array('order_provider', 'order_ref', 'order_item_ref', 'claim_id'), 'Additive upgrade must restore full allocation uniqueness.');
$assert(get_option(bvmgr_admission_offers_db_option_key()) === '1.1.0', 'Schema version must advance after verified upgrade.');

$keyring = BVMGR_Admission_Offer_Identity_Keyring::load_or_create($wpdb);
$assert($keyring->active_version() === 1 && strlen($keyring->keys()[1]) === 32, 'Real WordPress option must preserve a 256-bit installation key.');

$clear = static function () use ($wpdb, $table_names): void {
	foreach (array_reverse($table_names) as $table) $wpdb->query("DELETE FROM {$table}");
};
$seed = static function (int $capacity, array $quantities) use ($wpdb): array {
	$offer = BVMGR_Admission_Offer_Value::from_array(array('name' => 'Real contention', 'offer_type' => 'complimentary', 'status' => 'active', 'max_qty_per_claim' => max($quantities), 'capacity_total' => $capacity, 'identity_policy' => array()));
	$offer_id = (new BVMGR_Admission_Offer_Repository($wpdb))->create($offer, 1, '2026-09-28 12:00:00');
	$claims = array();
	foreach ($quantities as $offset => $quantity) {
		$claims[] = (new BVMGR_Admission_Offer_Claim_Repository($wpdb))->create(array('offer_id' => $offer_id, 'event_plan_id' => 9001, 'quantity' => $quantity), str_repeat(chr(65 + $offset), 32), '2026-09-28 12:00:00');
	}
	return array($offer_id, $claims);
};
$worker = __DIR__ . '/worker.php';
$php = PHP_BINARY;
$start_worker = static function (array $payload) use ($worker, $php, $wordpress_root): array {
	$command = array($php, $worker, $wordpress_root, base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)));
	$pipes = array();
	$process = proc_open($command, array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
	if (!is_resource($process)) throw new RuntimeException('worker_start_failed');
	fclose($pipes[0]);
	return array($process, $pipes);
};
$finish_worker = static function (array $handle): array {
	[$process, $pipes] = $handle;
	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]); fclose($pipes[2]);
	$code = proc_close($process);
	if ($code !== 0 || $stdout === '') throw new RuntimeException('worker_failed:' . $code . ':' . $stderr);
	return json_decode($stdout, true, 32, JSON_THROW_ON_ERROR);
};
$wait_marker = static function (string $marker): void {
	$deadline = microtime(true) + 10;
	while (!is_file($marker)) {
		if (microtime(true) > $deadline) throw new RuntimeException('marker_timeout');
		usleep(20000);
	}
};
$run_pair = static function (int $capacity, array $quantities, array $expected, string $label) use ($clear, $seed, $start_worker, $finish_worker, $wait_marker, $assert, $table_names, $wpdb): array {
	$clear();
	[$offer_id, $claims] = $seed($capacity, $quantities);
	$marker = sys_get_temp_dir() . '/bvm-ao-' . bin2hex(random_bytes(6)) . '.lock';
	$release = $marker . '.release';
	$base = array('offer_id' => $offer_id, 'event_plan_id' => 9001, 'now' => '2026-09-28 12:00:00');
	$a = $start_worker($base + array('claim_id' => $claims[0], 'quantity' => $quantities[0], 'idempotency_key' => $label . '-first-123456', 'marker' => $marker, 'release' => $release));
	$wait_marker($marker);
	$b = $start_worker($base + array('claim_id' => $claims[1], 'quantity' => $quantities[1], 'idempotency_key' => $label . '-second-123456'));
	usleep(400000);
	$assert(proc_get_status($b[0])['running'], $label . ': second connection must genuinely wait.');
	file_put_contents($release, 'release');
	$result_a = $finish_worker($a); $result_b = $finish_worker($b);
	@unlink($marker); @unlink($release);
	$assert($result_a['connection_id'] !== $result_b['connection_id'], $label . ': contenders must use distinct sessions.');
	$assert($result_a['ok'] === $expected[0] && $result_b['ok'] === $expected[1], $label . ': outcomes mismatch.');
	$total = (int) $wpdb->get_var("SELECT COALESCE(SUM(quantity),0) FROM {$table_names['reservations']} WHERE state IN ('held','order_attached','consumed')");
	return array('a' => $result_a, 'b' => $result_b, 'total' => $total);
};

$contention_forward = $run_pair(2, array(2, 1), array(true, false), 'forward');
$assert($contention_forward['b']['error'] === 'offer_capacity_exhausted' && $contention_forward['total'] === 2, 'Forward contention must finish at capacity 2.');
$contention_reverse = $run_pair(2, array(1, 2), array(true, false), 'reverse');
$assert($contention_reverse['b']['error'] === 'offer_capacity_exhausted' && $contention_reverse['total'] === 1, 'Reverse timing must preserve committed quantity 1.');
$fit = $run_pair(3, array(2, 1), array(true, true), 'combined-fit');
$assert($fit['total'] === 3, 'Combined fitting requests must both succeed.');

$clear(); [$offer_id, $claims] = $seed(2, array(2));
$service = new BVMGR_Admission_Offer_Capacity_Service(new BVMGR_Admission_Offer_WPDB_Capacity_Store(), static function (): void {});
$one = $service->acquire($offer_id, $claims[0], 9001, 2, 'real-idempotency-123456', '2026-09-28 12:00:00');
$two = $service->acquire($offer_id, $claims[0], 9001, 2, 'real-idempotency-123456', '2026-09-28 12:01:00');
$assert((int) $one['id'] === (int) $two['id'] && (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_names['reservations']}") === 1, 'Real idempotent retry must not consume twice.');

$clear(); [$offer_id, $claims] = $seed(2, array(2));
$wpdb->insert($table_names['reservations'], array('public_id' => bvmgr_admission_offer_generate_public_id('ar'), 'offer_id' => $offer_id, 'claim_id' => $claims[0], 'event_plan_id' => 9001, 'quantity' => 2, 'state' => 'held', 'idempotency_key_hash' => bvmgr_admission_offer_idempotency_hash('stale-real-123456'), 'expires_at' => '2026-09-28 11:59:00', 'state_version' => 1, 'created_at' => '2026-09-28 11:00:00'));
$fresh = $service->acquire($offer_id, $claims[0], 9001, 2, 'fresh-real-123456', '2026-09-28 12:00:00');
$assert((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_names['reservations']} WHERE state='expired' AND offer_id=%d", $offer_id)) === 1 && (int) $fresh['id'] > 0, 'Stale hold must expire under lock and release capacity.');

$clear(); [$offer_id, $claims] = $seed(2, array(1));
foreach (array('order_attached', 'consumed') as $state) {
	$wpdb->insert($table_names['reservations'], array('public_id' => bvmgr_admission_offer_generate_public_id('ar'), 'offer_id' => $offer_id, 'claim_id' => $claims[0], 'event_plan_id' => 9001, 'quantity' => 1, 'state' => $state, 'idempotency_key_hash' => bvmgr_admission_offer_idempotency_hash($state . '-real-123456'), 'expires_at' => '2026-09-28 11:00:00', 'state_version' => 1, 'created_at' => '2026-09-28 11:00:00'));
}
try { $service->acquire($offer_id, $claims[0], 9001, 1, 'counted-real-123456', '2026-09-28 12:00:00'); $assert(false, 'Committed states must exhaust capacity.'); } catch (BVMGR_Admission_Offer_Domain_Exception $e) { $assert($e->getMessage() === 'offer_capacity_exhausted', 'Committed states must remain counted.'); }

$clear(); [$offer_id, $claims] = $seed(1, array(1));
$failure = $finish_worker($start_worker(array('offer_id' => $offer_id, 'claim_id' => $claims[0], 'event_plan_id' => 9001, 'quantity' => 1, 'idempotency_key' => 'rollback-real-123456', 'now' => '2026-09-28 12:00:00', 'fail_after_insert' => true)));
$assert(!$failure['ok'] && $failure['error'] === 'injected_after_insert' && (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_names['reservations']}") === 0, 'Injected failure must roll back the partial reservation.');
$after_rollback = $service->acquire($offer_id, $claims[0], 9001, 1, 'after-rollback-123456', '2026-09-28 12:00:00');
$assert((int) $after_rollback['id'] > 0, 'Rollback must release locks for the next request.');

$clear(); [$offer_id, $claims] = $seed(1, array(1, 1));
$marker = sys_get_temp_dir() . '/bvm-ao-timeout-' . bin2hex(random_bytes(6)); $release = $marker . '.release';
$locker = $start_worker(array('offer_id' => $offer_id, 'claim_id' => $claims[0], 'event_plan_id' => 9001, 'quantity' => 1, 'idempotency_key' => 'locker-real-123456', 'now' => '2026-09-28 12:00:00', 'marker' => $marker, 'release' => $release));
$wait_marker($marker);
$timeout = $finish_worker($start_worker(array('offer_id' => $offer_id, 'claim_id' => $claims[1], 'event_plan_id' => 9001, 'quantity' => 1, 'idempotency_key' => 'timeout-real-123456', 'now' => '2026-09-28 12:00:00', 'lock_wait_timeout' => 1, 'max_attempts' => 2)));
$assert(!$timeout['ok'] && $timeout['error_class'] === 'BVMGR_Admission_Offer_Transient_Transaction_Exception', 'Real lock timeout must exhaust the bounded transient policy.');
file_put_contents($release, 'release'); $locker_result = $finish_worker($locker); @unlink($marker); @unlink($release);
$assert($locker_result['ok'] && $locker_result['connection_id'] !== $timeout['connection_id'], 'Timeout fixture must use independent sessions and leave the locker healthy.');

$final_tables = array();
foreach ($table_names as $table) $final_tables[$table] = hash('sha256', $normalize_create($table));
$result = array(
	'assertions' => $assertions,
	'mysql_version' => $version,
	'wordpress_version' => get_bloginfo('version'),
	'schema_version' => get_option(bvmgr_admission_offers_db_option_key()),
	'schema' => $schema,
	'contention' => array('forward' => $contention_forward, 'reverse' => $contention_reverse, 'combined_fit' => $fit, 'timeout' => $timeout),
	'rollback_expected' => array('tables' => $final_tables, 'option' => get_option(bvmgr_admission_offers_db_option_key())),
);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
