<?php
declare(strict_types=1);

[$script, $wordpress_root, $encoded] = $argv + array(null, null, null);
$expected = json_decode((string) base64_decode((string) $encoded, true), true, 32, JSON_THROW_ON_ERROR);
require rtrim((string) $wordpress_root, '/') . '/wp-load.php';
global $wpdb;

$actual = array();
foreach ($expected['tables'] as $table => $signature) {
	$row = $wpdb->get_row('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`', ARRAY_N);
	$actual[$table] = hash('sha256', preg_replace('/AUTO_INCREMENT=\d+\s*/', '', (string) ($row[1] ?? '')) ?? '');
}
$option = get_option('vms_admission_offers_db_version', null);
if ($actual !== $expected['tables'] || $option !== $expected['option']) {
	throw new RuntimeException('pre_phase_rollback_changed_dormant_foundation_state');
}
echo json_encode(array('rollback_harmless' => true, 'tables' => count($actual), 'option' => $option), JSON_THROW_ON_ERROR);
