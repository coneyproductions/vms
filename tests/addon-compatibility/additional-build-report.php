<?php
declare(strict_types=1);

if ($argc !== 9) {
	fwrite(STDERR, "Usage: php additional-build-report.php <index.tsv> <source.json> <normal-before> <normal-after> <db-cleanup> <runtime-cleanup> <report.json> <report.txt>\n");
	exit(2);
}

[$script, $indexPath, $sourcePath, $normalBefore, $normalAfter, $databaseCleanup, $runtimeCleanup, $jsonPath, $textPath] = $argv;
unset($script);

$contracts = require __DIR__ . '/additional-runtime-contracts.php';
$phase6a = getenv('BVM_COMPAT_PHASE') === 'phase6a';
$sourceManifest = json_decode((string) file_get_contents($sourcePath), true);
if (!is_array($sourceManifest)) {
	fwrite(STDERR, "Could not decode the isolated source manifest.\n");
	exit(2);
}

$normalizeLogLine = static function (string $line): string {
	$line = preg_replace('/^\[[^\]]+\]\s*/', '', trim($line)) ?: trim($line);
	$line = preg_replace('#(?:/[A-Za-z0-9_. -]+)+/wp-content/plugins/#', 'wp-content/plugins/', $line) ?: $line;
	$line = preg_replace('#(?:/[A-Za-z0-9_. -]+)+/wp-(admin|includes)/#', 'wordpress/wp-$1/', $line) ?: $line;
	$line = preg_replace('/\belapsed_ms=[^ ]+/', 'elapsed_ms=<dynamic>', $line) ?: $line;
	$line = preg_replace('/\bmemory=[^ ]+/', 'memory=<dynamic>', $line) ?: $line;
	return $line;
};

$ownedSlugs = array_merge(array('backstage-venue-manager'), array_keys($contracts['plugins']), array('vms-events-slider', 'vms-fill-dates', 'vms-data-tools', 'vms-express-bar', 'vms-refer-a-friend'));
$ownedPattern = '#wp-content/plugins/(?:' . implode('|', array_map(static fn(string $slug): string => preg_quote($slug, '#'), $ownedSlugs)) . ')/#';
$classifyDebug = static function (string $contents) use ($normalizeLogLine, $ownedPattern): array {
	$summary = array(
		'fatal' => array(),
		'database_error' => array(),
		'owned_warning_or_notice' => array(),
		'upstream_deprecation' => array(),
		'translation_timing_notice' => array(),
		'network_isolation_warning' => array(),
		'diagnostic' => array(),
		'other' => array(),
	);
	foreach (preg_split('/\R/', $contents) ?: array() as $line) {
		$line = $normalizeLogLine($line);
		if ($line === '') {
			continue;
		}
		if (preg_match('/Fatal error|Parse error|Uncaught (?:Error|Exception)|Allowed memory size/i', $line) === 1) {
			$summary['fatal'][] = $line;
		} elseif (strpos($line, 'WordPress database error') !== false) {
			$summary['database_error'][] = $line;
		} elseif (stripos($line, 'Deprecated') !== false) {
			$summary['upstream_deprecation'][] = $line;
		} elseif (strpos($line, '_load_textdomain_just_in_time') !== false) {
			$summary['translation_timing_notice'][] = $line;
		} elseif (strpos($line, 'could not establish a secure connection to WordPress.org') !== false) {
			$summary['network_isolation_warning'][] = $line;
		} elseif (preg_match('/Warning|Notice/i', $line) === 1 && preg_match($ownedPattern, $line) === 1) {
			$summary['owned_warning_or_notice'][] = $line;
		} elseif (preg_match('/\[(?:VMS|VMSEB|vms-)/', $line) === 1) {
			$summary['diagnostic'][] = $line;
		} else {
			$summary['other'][] = $line;
		}
	}
	foreach ($summary as $key => $lines) {
		$summary[$key] = array_values(array_unique($lines));
	}
	return $summary;
};

$scenarios = array();
$indexLines = file($indexPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (!is_array($indexLines)) {
	fwrite(STDERR, "Could not read the scenario index.\n");
	exit(2);
}

foreach ($indexLines as $line) {
	$columns = explode("\t", $line);
	if (count($columns) !== 9) {
		fwrite(STDERR, "Malformed additional scenario index row.\n");
		exit(2);
	}
	[$id, $addon, $core, $woocommerce, $companionState, $order, $exitCode, $rawPath, $debugPath] = $columns;
	$raw = is_file($rawPath) ? (string) file_get_contents($rawPath) : '';
	$debug = is_file($debugPath) ? (string) file_get_contents($debugPath) : '';
	$payload = null;
	if (preg_match('/^BVM_COMPAT_RESULT_JSON=([A-Za-z0-9+\/=]+)$/m', $raw, $match) === 1) {
		$decoded = base64_decode($match[1], true);
		$payload = is_string($decoded) ? json_decode($decoded, true) : null;
	}
	$activationState = null;
	if (preg_match('/^BVM_COMPAT_ACTIVATION_STATE_JSON=([A-Za-z0-9+\/=]+)$/m', $raw, $match) === 1) {
		$decoded = base64_decode($match[1], true);
		$activationState = is_string($decoded) ? json_decode($decoded, true) : null;
	}
	$debugSummary = $classifyDebug($debug);
	$checkFailures = array();
	if (is_array($payload)) {
		foreach ((array) ($payload['checks'] ?? array()) as $runtimeCheck) {
			if (is_array($runtimeCheck) && empty($runtimeCheck['passed'])) {
				$checkFailures[] = $runtimeCheck;
			}
		}
	}
	$isActivationScenario = $order === 'activation';
	$passed = (int) $exitCode === 0
		&& (($isActivationScenario && is_array($activationState)) || (!$isActivationScenario && is_array($payload)))
		&& $checkFailures === array()
		&& $debugSummary['fatal'] === array()
		&& $debugSummary['database_error'] === array()
		&& $debugSummary['owned_warning_or_notice'] === array();
	$probeOutput = array();
	foreach (preg_split('/\R/', preg_replace('/^BVM_COMPAT_(?:RESULT|ACTIVATION_STATE)_JSON=.*$/m', '', trim($raw)) ?: '') ?: array() as $outputLine) {
		$outputLine = $normalizeLogLine($outputLine);
		if ($outputLine !== '') {
			$probeOutput[] = $outputLine;
		}
	}
	$scenarios[$id] = array(
		'id' => $id,
		'addon' => $addon,
		'core' => $core,
		'woocommerce' => $woocommerce,
		'companion_state' => $companionState,
		'load_order' => $order,
		'exit_code' => (int) $exitCode,
		'passed' => $passed,
		'payload' => is_array($payload) ? $payload : null,
		'activation_state' => is_array($activationState) ? $activationState : null,
		'check_failures' => $checkFailures,
		'debug' => $debugSummary,
		'probe_output_without_payload' => array_values(array_unique($probeOutput)),
	);
}

$dimensions = array('BVM Detection', 'APIs', 'Menu/UI', 'Notices', 'BVM-Absent');
$matrix = array();
$matrixContracts = $phase6a
	? array('drm-events-bridge' => $contracts['plugins']['drm-events-bridge'])
	: $contracts['plugins'];
foreach ($matrixContracts as $addon => $contract) {
	$dimensionResults = array();
	foreach ($dimensions as $dimension) {
		$relevant = array();
		foreach ($scenarios as $scenario) {
			if (!is_array($scenario['payload'])) {
				continue;
			}
			foreach ((array) ($scenario['payload']['checks'] ?? array()) as $runtimeCheck) {
				if (!is_array($runtimeCheck) || ($runtimeCheck['dimension'] ?? '') !== $dimension) {
					continue;
				}
				$checkAddon = (string) ($runtimeCheck['addon'] ?? '');
				if ($checkAddon === $addon || $checkAddon === 'all') {
					$relevant[] = !empty($runtimeCheck['passed']);
				}
			}
		}
		$dimensionResults[$dimension] = $relevant !== array() && !in_array(false, $relevant, true);
	}

	$coreFirstId = 'additional-' . $addon . '-core-first';
	$addonFirstId = 'additional-' . $addon . '-addon-first';
	$loadOrderPassed = isset($scenarios[$coreFirstId], $scenarios[$addonFirstId]) && $scenarios[$coreFirstId]['passed'] && $scenarios[$addonFirstId]['passed'];
	$allPassed = !in_array(false, $dimensionResults, true) && $loadOrderPassed;
	$companionActivationDefect = $addon === 'vms-commerce-discounts'
		&& isset($scenarios['third-party-activation-absent-square-vms-commerce-discounts'])
		&& !$scenarios['third-party-activation-absent-square-vms-commerce-discounts']['passed'];
	if ($allPassed && $companionActivationDefect) {
		$overall = 'PARTIAL — BVM compatible; missing WooCommerce Square fatals during activation';
	} elseif ($allPassed) {
		$overall = 'PASS — BVM-only runtime compatible';
	} elseif (!$dimensionResults['BVM Detection'] || !$dimensionResults['APIs']) {
		$overall = 'FAIL — effectively incompatible with BVM-only core';
	} else {
		$overall = 'PARTIAL — specific runtime incompatibility found';
	}
	$matrix[$addon] = array_merge(
		array('Plugin' => $contract['name'], 'Version' => $contract['version'], 'Companions' => $contract['companions']),
		$dimensionResults,
		array('Load Order' => $loadOrderPassed, 'Overall' => $overall)
	);
}

foreach ($contracts['blocked'] as $slug => $blocked) {
	$matrix[$slug] = array(
		'Plugin' => $blocked['name'],
		'Version' => $blocked['version'],
		'Companions' => array('required' => array(), 'optional' => array()),
		'BVM Detection' => null,
		'APIs' => null,
		'Menu/UI' => null,
		'Notices' => null,
		'BVM-Absent' => null,
		'Load Order' => null,
		'Overall' => $blocked['status'],
	);
}

$crossScenarioIds = array('additional-coexistence-core-first', 'additional-coexistence-addons-first');
$crossAddonPassed = true;
foreach ($crossScenarioIds as $crossId) {
	$crossAddonPassed = $crossAddonPassed && isset($scenarios[$crossId]) && $scenarios[$crossId]['passed'];
}

$firstIdentity = array();
foreach ($scenarios as $scenario) {
	if ($scenario['core'] === 'yes' && is_array($scenario['payload'])) {
		$firstIdentity = array('active_plugins' => $scenario['payload']['active_plugins'] ?? array(), 'identity' => $scenario['payload']['identity'] ?? array());
		break;
	}
}

$cleanupPassed = $databaseCleanup === 'pass' && $runtimeCleanup === 'pass';
$normalSiteUnchanged = $normalBefore !== '' && hash_equals($normalBefore, $normalAfter);
$scenarioPass = $scenarios !== array() && !in_array(false, array_column($scenarios, 'passed'), true);
$overallPass = $scenarioPass && $crossAddonPassed && $cleanupPassed && $normalSiteUnchanged;

$report = array(
	'schema_version' => 1,
	'suite' => $phase6a ? 'drm_events_bridge_phase6a' : 'additional_first_party',
	'overall' => $overallPass ? 'PASS' : 'FAIL',
	'isolation' => array(
		'wordpress' => 'Local WordPress core copied to a temporary tree',
		'database' => 'uniquely named bvm_compat_* disposable database',
		'database_cleanup' => $databaseCleanup,
		'runtime_tree_cleanup' => $runtimeCleanup,
		'activation_schema_setup' => 'pass',
		'external_http' => 'blocked',
		'normal_active_plugins_before_sha256' => $normalBefore,
		'normal_active_plugins_after_sha256' => $normalAfter,
		'normal_active_plugins_unchanged' => $normalSiteUnchanged,
	),
	'source_manifest' => $sourceManifest,
	'contract_manifest' => $contracts,
	'forensic_source_integrity' => $phase6a ? array(
		'bridge_dirty_status_before_sha256' => getenv('BVM_COMPAT_BRIDGE_STATUS_BEFORE_SHA256') ?: '',
		'bridge_dirty_status_after_sha256' => getenv('BVM_COMPAT_BRIDGE_STATUS_AFTER_SHA256') ?: '',
		'bridge_dirty_status_unchanged' => ($before = getenv('BVM_COMPAT_BRIDGE_STATUS_BEFORE_SHA256')) !== false
			&& ($after = getenv('BVM_COMPAT_BRIDGE_STATUS_AFTER_SHA256')) !== false
			&& $before !== ''
			&& hash_equals($before, $after),
	) : array(),
	'bvm_only_identity_proof' => $firstIdentity,
	'matrix' => $matrix,
	'cross_ecosystem' => array('scenarios' => $crossScenarioIds, 'passed' => $crossAddonPassed),
	'scenarios' => array_values($scenarios),
);

$json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
if (file_put_contents($jsonPath, $json) === false) {
	fwrite(STDERR, "Could not write the additional integration JSON report.\n");
	exit(2);
}

$status = static fn($value): string => $value === null ? 'BLOCKED' : ($value ? 'PASS' : 'FAIL');
$text = array(
	$phase6a ? 'BVM / DRM Events Bridge Phase 6A Runtime Compatibility' : 'BVM Additional First-Party Integration Runtime Compatibility',
	'Overall: ' . $report['overall'],
	'',
	'WordPress: ' . ($sourceManifest['wordpress_version'] ?? ''),
	'BVM: ' . ($sourceManifest['plugins']['backstage-venue-manager']['version'] ?? ''),
	'Database cleanup: ' . strtoupper($databaseCleanup),
	'Runtime cleanup: ' . strtoupper($runtimeCleanup),
	'External HTTP: BLOCKED',
	'Normal active plugins unchanged: ' . $status($normalSiteUnchanged),
	...($phase6a ? array('Bridge forensic worktree unchanged: ' . $status($report['forensic_source_integrity']['bridge_dirty_status_unchanged'])) : array()),
	'',
	'| Plugin | Version | BVM Detection | APIs | Menu/UI | Notices | BVM-Absent | Load Order | Overall |',
	'| --- | ---: | --- | --- | --- | --- | --- | --- | --- |',
);
foreach ($matrix as $row) {
	$text[] = sprintf(
		'| %s | %s | %s | %s | %s | %s | %s | %s | %s |',
		$row['Plugin'],
		$row['Version'],
		$status($row['BVM Detection']),
		$status($row['APIs']),
		$status($row['Menu/UI']),
		$status($row['Notices']),
		$status($row['BVM-Absent']),
		$status($row['Load Order']),
		$row['Overall']
	);
}
$text[] = '';
$text[] = 'Cross-ecosystem scenarios: ' . $status($crossAddonPassed);
$text[] = '';
$text[] = 'Scenario results:';
foreach ($scenarios as $scenario) {
	$text[] = '- ' . $scenario['id'] . ': ' . ($scenario['passed'] ? 'PASS' : 'FAIL');
	foreach ($scenario['check_failures'] as $failure) {
		$text[] = '  - ' . ($failure['id'] ?? 'unknown') . ': ' . ($failure['message'] ?? 'failed');
		if (!empty($failure['details'])) {
			$text[] = '    ' . json_encode($failure['details'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		}
	}
	if ($scenario['payload'] === null) {
		foreach ($scenario['probe_output_without_payload'] as $outputLine) {
			if (preg_match('/Fatal error|Parse error|Uncaught (?:Error|Exception)/i', $outputLine) === 1) {
				$text[] = '  - process failure: ' . $outputLine;
			}
		}
	}
	foreach (array('fatal', 'database_error', 'owned_warning_or_notice') as $debugType) {
		foreach ($scenario['debug'][$debugType] as $line) {
			$text[] = '  - ' . str_replace('_', ' ', $debugType) . ': ' . $line;
		}
	}
}

if (file_put_contents($textPath, implode("\n", $text) . "\n") === false) {
	fwrite(STDERR, "Could not write the additional integration text report.\n");
	exit(2);
}

echo "Wrote additional runtime reports:\n- {$jsonPath}\n- {$textPath}\n";
exit($overallPass ? 0 : 1);
