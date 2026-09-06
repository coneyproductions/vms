<?php

$operation = (string) getenv('BVM_WAVE2C_SPONSOR_OPERATION');

$writeJson = static function (string $path, array $payload): void {
	$encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
	if (file_put_contents($path, $encoded) !== strlen($encoded)) {
		throw new RuntimeException('Could not write the migration receipt.');
	}
	chmod($path, 0600);
};

if ($operation === 'compare') {
	$before = json_decode((string) file_get_contents((string) getenv('BVM_WAVE2C_BEFORE')), true, 512, JSON_THROW_ON_ERROR);
	$one = json_decode((string) file_get_contents((string) getenv('BVM_WAVE2C_AFTER_ONE')), true, 512, JSON_THROW_ON_ERROR);
	$two = json_decode((string) file_get_contents((string) getenv('BVM_WAVE2C_AFTER_TWO')), true, 512, JSON_THROW_ON_ERROR);
	$failures = array();

	if ($before['active_plugins_sha256'] !== $one['active_plugins_sha256'] || $one['active_plugins_sha256'] !== $two['active_plugins_sha256']) {
		$failures[] = 'active_plugins';
	}
	if ($before['tables'] !== $one['tables']) {
		$failures[] = 'first-load-tables';
	}
	if ($one['tables'] !== $two['tables']) {
		$failures[] = 'second-load-tables';
	}
	if (array_keys($before['options']) !== array_keys($one['options'])) {
		$failures[] = 'option-name-set';
	}
	foreach ($before['options'] as $name => $state) {
		if ($name !== 'vms_sponsorships_version' && ($one['options'][$name] ?? null) !== $state) {
			$failures[] = 'first-load-option:' . $name;
		}
	}

	$versionBefore = $before['options']['vms_sponsorships_version'] ?? null;
	$versionOne = $one['options']['vms_sponsorships_version'] ?? null;
	if (
		($versionBefore['version_value'] ?? null) !== '0.1.27'
		|| ($versionOne['version_value'] ?? null) !== '0.1.28'
		|| ($versionBefore['option_id'] ?? null) !== ($versionOne['option_id'] ?? null)
		|| ($versionBefore['autoload'] ?? null) !== ($versionOne['autoload'] ?? null)
	) {
		$failures[] = 'version-transition';
	}
	if ($one !== $two) {
		$failures[] = 'second-load-idempotence';
	}

	$writeJson(
		(string) getenv('BVM_WAVE2C_COMPARISON'),
		array(
			'passed' => $failures === array(),
			'failures' => $failures,
			'expected' => array(
				'vms_sponsorships_version' => array('from' => '0.1.27', 'to' => '0.1.28'),
				'tables' => 'unchanged',
				'other_options' => 'unchanged',
				'active_plugins' => 'unchanged',
				'second_load' => 'idempotent',
			),
			'actual' => array(
				'vms_sponsorships_version' => array(
					'from' => $versionBefore['version_value'] ?? null,
					'to' => $versionOne['version_value'] ?? null,
					'option_id_preserved' => ($versionBefore['option_id'] ?? null) === ($versionOne['option_id'] ?? null),
					'autoload_preserved' => ($versionBefore['autoload'] ?? null) === ($versionOne['autoload'] ?? null),
				),
				'table_state_preserved' => $before['tables'] === $one['tables'],
				'other_options_preserved' => count(array_filter($failures, static fn(string $failure): bool => str_starts_with($failure, 'first-load-option:'))) === 0,
				'active_plugins_preserved' => $before['active_plugins_sha256'] === $one['active_plugins_sha256'],
				'second_load_idempotent' => $one === $two,
			),
		)
	);

	if ($failures !== array()) {
		fwrite(STDERR, 'Migration mismatch: ' . implode(', ', $failures) . "\n");
		exit(1);
	}
	echo "DISPOSABLE_CURRENT_SHAPE_MIGRATION_EXACT\n";
	exit(0);
}

if (!defined('ABSPATH')) {
	fwrite(STDERR, "This operation must run through WordPress.\n");
	exit(2);
}

global $wpdb;

if ($operation === 'import-options') {
	$backup = json_decode((string) file_get_contents((string) getenv('BVM_WAVE2C_SPONSOR_OPTIONS_BACKUP')), true, 512, JSON_THROW_ON_ERROR);
	$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'vms\\_sponsorships\\_%'");
	foreach ($backup['rows'] as $row) {
		$value = hex2bin((string) $row['option_value_hex']);
		if ($value === false) {
			throw new RuntimeException('Invalid option backup encoding.');
		}
		$inserted = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->options} (option_id,option_name,option_value,autoload) VALUES (%d,%s,%s,%s)",
				(int) $row['option_id'],
				(string) $row['option_name'],
				$value,
				(string) $row['autoload']
			)
		);
		if ($inserted !== 1) {
			throw new RuntimeException('Could not import a Sponsorships option row.');
		}
	}
	echo 'DISPOSABLE_CURRENT_SPONSOR_STATE_IMPORTED ' . count($backup['rows']) . "\n";
	exit(0);
}

if ($operation === 'capture') {
	$tableSuffixes = array(
		'vms_sponsorship_packages',
		'vms_sponsorship_applications',
		'vms_sponsorship_assignments',
		'vms_sponsor_assets',
		'vms_sponsor_metrics',
		'vms_sponsor_fulfillment_items',
	);
	$tables = array();
	foreach ($tableSuffixes as $suffix) {
		$table = $wpdb->prefix . $suffix;
		$create = $wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_N);
		$checksum = $wpdb->get_row("CHECKSUM TABLE `{$table}`", ARRAY_N);
		$status = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',
				$table
			),
			ARRAY_A
		);
		$tables[$table] = array(
			'row_count' => (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`"),
			'checksum' => (string) ($checksum[1] ?? ''),
			'auto_increment' => isset($status['AUTO_INCREMENT']) ? (int) $status['AUTO_INCREMENT'] : null,
			'create_sha256' => hash('sha256', (string) ($create[1] ?? '')),
		);
	}

	$rows = $wpdb->get_results(
		"SELECT option_id,option_name,option_value,autoload FROM {$wpdb->options} WHERE option_name LIKE 'vms\\_sponsorships\\_%' ORDER BY option_name",
		ARRAY_A
	);
	$options = array();
	foreach ($rows as $row) {
		$name = (string) $row['option_name'];
		$value = (string) $row['option_value'];
		$options[$name] = array(
			'option_id' => (int) $row['option_id'],
			'length' => strlen($value),
			'sha256' => hash('sha256', $value),
			'autoload' => (string) $row['autoload'],
			'version_value' => $name === 'vms_sponsorships_version' ? $value : null,
		);
	}
	$active = (string) $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", 'active_plugins'));
	$writeJson(
		(string) getenv('BVM_WAVE2C_CAPTURE_TARGET'),
		array(
			'tables' => $tables,
			'options' => $options,
			'active_plugins_sha256' => hash('sha256', $active),
		)
	);
	exit(0);
}

if ($operation === 'migrate') {
	require WP_PLUGIN_DIR . '/vms-sponsorships/vms-sponsorships.php';
	VMS_Sponsorships::instance();
	if (getenv('BVM_WAVE2C_ASSERT_HTTP') === 'yes') {
		$result = wp_remote_get('https://bvm-containment.invalid/wave2c-sponsor-migration', array('timeout' => 1));
		if (!is_wp_error($result) || $result->get_error_code() !== 'bvm_test_containment_http_blocked') {
			throw new RuntimeException('HTTP containment failed.');
		}
	}
	echo "DISPOSABLE_SPONSOR_MIGRATION_LOAD_PASS\n";
	exit(0);
}

fwrite(STDERR, "Unknown Wave 2C Sponsorships migration operation.\n");
exit(2);
