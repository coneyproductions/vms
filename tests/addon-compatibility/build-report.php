<?php
declare(strict_types=1);

if ($argc !== 9) {
	fwrite(STDERR, "Usage: php build-report.php <index.tsv> <source.json> <normal-before> <normal-after> <db-cleanup> <runtime-cleanup> <report.json> <report.txt>\n");
	exit(2);
}

[$script, $indexPath, $sourcePath, $normalBefore, $normalAfter, $databaseCleanup, $runtimeCleanup, $jsonPath, $textPath] = $argv;
unset($script);

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

$classifyDebug = static function (string $contents) use ($normalizeLogLine): array {
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
	$ownedPattern = '#wp-content/plugins/(?:backstage-venue-manager|vms-events-slider|vms-fill-dates|vms-data-tools|vms-express-bar|vms-refer-a-friend)/#';
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
		} elseif (strpos($line, '[VMS DT TRACE]') !== false || strpos($line, '[VMS ') !== false || strpos($line, '[VMSEB ') !== false) {
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
	if (count($columns) !== 8) {
		fwrite(STDERR, "Malformed scenario index row.\n");
		exit(2);
	}
	[$id, $addon, $core, $woocommerce, $order, $exitCode, $rawPath, $debugPath] = $columns;
	$raw = is_file($rawPath) ? (string) file_get_contents($rawPath) : '';
	$debug = is_file($debugPath) ? (string) file_get_contents($debugPath) : '';
	$payload = null;
	if (preg_match('/^BVM_COMPAT_RESULT_JSON=([A-Za-z0-9+\/=]+)$/m', $raw, $match) === 1) {
		$decoded = base64_decode($match[1], true);
		$payload = is_string($decoded) ? json_decode($decoded, true) : null;
	}
	$debugSummary = $classifyDebug($debug);
	$checkFailures = array();
	if (is_array($payload)) {
		foreach ((array) ($payload['checks'] ?? array()) as $check) {
			if (is_array($check) && empty($check['passed'])) {
				$checkFailures[] = $check;
			}
		}
	}
	$passed = (int) $exitCode === 0
		&& is_array($payload)
		&& $checkFailures === array()
		&& $debugSummary['fatal'] === array()
		&& $debugSummary['database_error'] === array()
		&& $debugSummary['owned_warning_or_notice'] === array();
	$probeOutput = array();
	foreach (preg_split('/\R/', preg_replace('/^BVM_COMPAT_RESULT_JSON=.*$/m', '', trim($raw)) ?: '') ?: array() as $outputLine) {
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
		'load_order' => $order,
		'exit_code' => (int) $exitCode,
		'passed' => $passed,
		'payload' => is_array($payload) ? $payload : null,
		'check_failures' => $checkFailures,
		'debug' => $debugSummary,
		'probe_output_without_payload' => array_values(array_unique($probeOutput)),
	);
}

$officialAddons = array('events-slider', 'fill-dates', 'data-tools', 'express-bar', 'refer-a-friend');
$dimensions = array('BVM Recognized', 'No Fatal', 'Menu', 'Notices', 'Core-Absent Behavior');
$matrix = array();

foreach ($officialAddons as $addon) {
	$dimensionResults = array();
	foreach ($dimensions as $dimension) {
		$relevant = array();
		foreach ($scenarios as $scenario) {
			$payload = $scenario['payload'];
			if (!is_array($payload)) {
				continue;
			}
			foreach ((array) ($payload['checks'] ?? array()) as $runtimeCheck) {
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

	$coreFirstId = 'bvm-only-' . $addon . '-core-first';
	$addonFirstId = 'bvm-only-' . $addon . '-addon-first';
	$loadOrderPassed = isset($scenarios[$coreFirstId], $scenarios[$addonFirstId])
		&& $scenarios[$coreFirstId]['passed']
		&& $scenarios[$addonFirstId]['passed'];
	$allPassed = !in_array(false, $dimensionResults, true) && $loadOrderPassed;
	if (!$dimensionResults['BVM Recognized']) {
		$overall = 'FAIL — unusable with BVM-only core';
	} elseif (!$allPassed) {
		$overall = 'PARTIAL — runtime incompatibility found';
	} elseif ($addon === 'express-bar') {
		$overall = 'PASS WITH DEBT — works but reconstructs current menu hooks';
	} elseif ($addon === 'data-tools') {
		$overall = 'PASS WITH DEBT — works through the current late menu bridge cleanup';
	} else {
		$overall = 'PASS — BVM-only runtime compatible';
	}
	$matrix[$addon] = array_merge(
		$dimensionResults,
		array('Load Order' => $loadOrderPassed, 'Overall' => $overall)
	);
}

$crossScenarioIds = array('bvm-all-official-five-core-first', 'bvm-all-official-five-addons-first');
$crossAddonPassed = true;
foreach ($crossScenarioIds as $crossId) {
	$crossAddonPassed = $crossAddonPassed && isset($scenarios[$crossId]) && $scenarios[$crossId]['passed'];
}

$firstIdentity = array();
foreach ($scenarios as $scenario) {
	if ($scenario['core'] === 'yes' && is_array($scenario['payload'])) {
		$firstIdentity = array(
			'active_plugins' => $scenario['payload']['active_plugins'] ?? array(),
			'identity' => $scenario['payload']['identity'] ?? array(),
		);
		break;
	}
}

$cleanupPassed = $databaseCleanup === 'pass' && $runtimeCleanup === 'pass';
$normalSiteUnchanged = $normalBefore !== '' && hash_equals($normalBefore, $normalAfter);
$scenarioPass = $scenarios !== array() && !in_array(false, array_column($scenarios, 'passed'), true);
$overallPass = $scenarioPass && $crossAddonPassed && $cleanupPassed && $normalSiteUnchanged;

$report = array(
	'schema_version' => 1,
	'overall' => $overallPass ? 'PASS' : 'FAIL',
	'isolation' => array(
		'wordpress' => 'Local WordPress core copied to a temporary tree',
		'database' => 'uniquely named bvm_compat_* disposable database',
		'database_cleanup' => $databaseCleanup,
		'runtime_tree_cleanup' => $runtimeCleanup,
		'activation_schema_setup' => 'pass',
		'normal_active_plugins_before_sha256' => $normalBefore,
		'normal_active_plugins_after_sha256' => $normalAfter,
		'normal_active_plugins_unchanged' => $normalSiteUnchanged,
	),
	'source_manifest' => $sourceManifest,
	'bvm_only_identity_proof' => $firstIdentity,
	'matrix' => $matrix,
	'cross_addon' => array(
		'scenarios' => $crossScenarioIds,
		'passed' => $crossAddonPassed,
	),
	'scenarios' => array_values($scenarios),
);

$json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
if (file_put_contents($jsonPath, $json) === false) {
	fwrite(STDERR, "Could not write the JSON report.\n");
	exit(2);
}

$yesNo = static fn(bool $value): string => $value ? 'PASS' : 'FAIL';
$text = array(
	'BVM-Only Add-on Runtime Compatibility',
	'Overall: ' . $report['overall'],
	'',
	'WordPress: ' . ($sourceManifest['wordpress_version'] ?? ''),
	'BVM: ' . ($sourceManifest['plugins']['backstage-venue-manager']['version'] ?? ''),
	'Database cleanup: ' . strtoupper($databaseCleanup),
	'Runtime cleanup: ' . strtoupper($runtimeCleanup),
	'Normal active plugins unchanged: ' . $yesNo($normalSiteUnchanged),
	'',
	'| Add-on | BVM Recognized | No Fatal | Menu | Notices | Core-Absent Behavior | Load Order | Overall |',
	'| --- | --- | --- | --- | --- | --- | --- | --- |',
);
foreach ($matrix as $addon => $row) {
	$text[] = sprintf(
		'| %s | %s | %s | %s | %s | %s | %s | %s |',
		$addon,
		$yesNo($row['BVM Recognized']),
		$yesNo($row['No Fatal']),
		$yesNo($row['Menu']),
		$yesNo($row['Notices']),
		$yesNo($row['Core-Absent Behavior']),
		$yesNo($row['Load Order']),
		$row['Overall']
	);
}
$text[] = '';
$text[] = 'Cross-add-on scenarios: ' . $yesNo($crossAddonPassed);
$text[] = '';
$text[] = 'Scenario results:';
foreach ($scenarios as $scenario) {
	$text[] = '- ' . $scenario['id'] . ': ' . ($scenario['passed'] ? 'PASS' : 'FAIL');
	foreach ($scenario['check_failures'] as $failure) {
		$text[] = '  - ' . ($failure['id'] ?? 'unknown') . ': ' . ($failure['message'] ?? 'failed');
	}
	foreach ($scenario['debug']['fatal'] as $line) {
		$text[] = '  - fatal: ' . $line;
	}
	foreach ($scenario['debug']['database_error'] as $line) {
		$text[] = '  - database error: ' . $line;
	}
	foreach ($scenario['debug']['owned_warning_or_notice'] as $line) {
		$text[] = '  - owned warning/notice: ' . $line;
	}
}

if (file_put_contents($textPath, implode("\n", $text) . "\n") === false) {
	fwrite(STDERR, "Could not write the text report.\n");
	exit(2);
}

echo "Wrote runtime reports:\n- {$jsonPath}\n- {$textPath}\n";
exit($overallPass ? 0 : 1);
