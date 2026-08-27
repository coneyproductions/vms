<?php
declare(strict_types=1);

function vms_test_fail(string $message): void
{
	throw new RuntimeException($message);
}

function vms_test_assert_true(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	vms_test_fail($message);
}

/**
 * @param mixed $expected
 * @param mixed $actual
 */
function vms_test_assert_same($expected, $actual, string $message): void
{
	if ($expected === $actual) {
		return;
	}

	vms_test_fail(
		$message
		. "\nExpected: " . var_export($expected, true)
		. "\nActual: " . var_export($actual, true)
	);
}

function vms_test_assert_contains(string $needle, string $haystack, string $message): void
{
	vms_test_assert_true(strpos($haystack, $needle) !== false, $message . "\nMissing: " . $needle);
}

function vms_test_assert_not_contains(string $needle, string $haystack, string $message): void
{
	vms_test_assert_true(strpos($haystack, $needle) === false, $message . "\nUnexpected: " . $needle);
}

function vms_test_read_file(string $path): string
{
	$contents = file_get_contents($path);
	if (!is_string($contents) || $contents === '') {
		vms_test_fail('Failed to read source file: ' . $path);
	}

	return $contents;
}

function vms_test_extract_function(string $source, string $name): string
{
	$pattern = '~function\s+' . preg_quote($name, '~') . '\s*\(~';
	if (!preg_match($pattern, $source, $matches, PREG_OFFSET_CAPTURE)) {
		vms_test_fail('Unable to locate function ' . $name . '.');
	}
	$start = (int) $matches[0][1];

	$brace = strpos($source, '{', $start);
	if ($brace === false) {
		vms_test_fail('Unable to locate opening brace for ' . $name . '.');
	}

	$depth = 1;
	$length = strlen($source);
	for ($i = $brace + 1; $i < $length; $i++) {
		$char = $source[$i];
		if ($char === '{') {
			$depth++;
			continue;
		}
		if ($char === '}') {
			$depth--;
			if ($depth === 0) {
				return substr($source, $start, ($i - $start) + 1);
			}
		}
	}

	vms_test_fail('Unable to locate closing brace for ' . $name . '.');
}

function vms_test_extract_admin_post_closure(string $source, string $hook): string
{
	$needle = "add_action('" . $hook . "', function (): void {";
	$start = strpos($source, $needle);
	if ($start === false) {
		vms_test_fail('Unable to locate admin-post closure for ' . $hook . '.');
	}

	$brace = strpos($source, '{', $start);
	if ($brace === false) {
		vms_test_fail('Unable to locate opening brace for admin-post closure ' . $hook . '.');
	}

	$depth = 1;
	$length = strlen($source);
	for ($i = $brace + 1; $i < $length; $i++) {
		$char = $source[$i];
		if ($char === '{') {
			$depth++;
			continue;
		}
		if ($char === '}') {
			$depth--;
			if ($depth === 0) {
				$end = strpos($source, ');', $i);
				if ($end === false) {
					vms_test_fail('Unable to locate closing add_action() for ' . $hook . '.');
				}

				return substr($source, $start, ($end - $start) + 2);
			}
		}
	}

	vms_test_fail('Unable to locate closing brace for admin-post closure ' . $hook . '.');
}

function vms_test_count_pattern(string $pattern, string $contents): int
{
	$count = preg_match_all($pattern, $contents);
	if ($count === false) {
		vms_test_fail('Failed counting pattern: ' . $pattern);
	}

	return $count;
}

function vms_test_assert_stream_boundary(string $label, string $body, array $requiredNeedles): void
{
	foreach ($requiredNeedles as $needle) {
		vms_test_assert_contains($needle, $body, $label . ' should retain the expected boundary evidence.');
	}

	vms_test_assert_contains("fopen('php://output'", $body, $label . ' should stream directly to php://output.');
	vms_test_assert_not_contains('$wp_filesystem', $body, $label . ' should not switch to the WordPress filesystem abstraction for the HTTP response stream.');
	vms_test_assert_not_contains('request_filesystem_credentials', $body, $label . ' should not require filesystem credentials for the HTTP response stream.');
	vms_test_assert_not_contains('file_system_operations_fopen', $body, $label . ' should not add an unnecessary fopen() suppression.');
}

try {
	$pluginRoot = dirname(__DIR__);
	$settingsPath = $pluginRoot . '/includes/admin/settings-page.php';
	$squarePath = $pluginRoot . '/includes/admin/square-sync-protection.php';
	$admissionsPath = $pluginRoot . '/includes/modules/admissions/admin-ui.php';
	$passClaimsPath = $pluginRoot . '/includes/modules/admissions/pass-claims.php';

	$settingsSource = vms_test_read_file($settingsPath);
	$squareSource = vms_test_read_file($squarePath);
	$admissionsSource = vms_test_read_file($admissionsPath);
	$passClaimsSource = vms_test_read_file($passClaimsPath);

	$combinedSource = implode("\n", array($settingsSource, $squareSource, $admissionsSource, $passClaimsSource));
	vms_test_assert_same(
		6,
		vms_test_count_pattern('/phpcs:ignore (?:WordPress\.WP\.AlternativeFunctions\.file_system_operations_fclose|Squiz\.PHP\.DiscouragedFunctions\.Discouraged)/', $combinedSource),
		'The F2 runtime files should keep exactly the six owned stream-boundary suppressions while allowing later, separately tested remediation annotations in the same files.'
	);
	vms_test_assert_same(
		5,
		vms_test_count_pattern('/phpcs:ignore WordPress\.WP\.AlternativeFunctions\.file_system_operations_fclose/', $combinedSource),
		'The F2 runtime files should keep exactly five php://output fclose() suppressions.'
	);
	vms_test_assert_same(
		1,
		vms_test_count_pattern('/phpcs:ignore Squiz\.PHP\.DiscouragedFunctions\.Discouraged/', $combinedSource),
		'The F2 runtime files should keep exactly one set_time_limit() suppression.'
	);
	vms_test_assert_same(0, vms_test_count_pattern('/phpcs:disable/', $combinedSource), 'The F2 runtime files should not introduce any broad phpcs:disable directives.');
	vms_test_assert_same(0, vms_test_count_pattern('/phpcs:enable/', $combinedSource), 'The F2 runtime files should not introduce any phpcs:enable directives.');

	$settingsFunction = vms_test_extract_function($settingsSource, 'vms_handle_ticketing_stock_csv');
	vms_test_assert_stream_boundary(
		'Ticketing stock CSV export',
		$settingsFunction,
		array(
			"current_user_can('manage_options')",
			"wp_verify_nonce(\$nonce, 'vms_ticketing_stock_csv')",
			"get_transient('vms_ticketing_stock_reconcile_last')",
			"get_transient(vms_ticketing_stock_preview_transient_key(get_current_user_id()))",
			"@set_time_limit(0); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Administrator-only ticketing stock CSV export streams a bounded transient report and WordPress does not provide a native execution-limit alternative.",
			"header('Content-Type: text/csv; charset=utf-8');",
			"header('Content-Disposition: attachment; filename=vms-ticketing-stock-' . \$mode . '-report-' . gmdate('Ymd-His') . '.csv');",
			"fclose(\$out); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the bounded administrator CSV response stream opened on php://output; no local filesystem path or WP_Filesystem replacement applies to this HTTP output handle.",
			"fputcsv(\$out, array('product_id', 'product_name', 'sku', 'plan_id', 'entitlement_id', 'capacity', 'sold_qty', 'old_stock', 'new_stock', 'status', 'message'));",
			'exit;',
		)
	);

	$squareClosure = vms_test_extract_admin_post_closure($squareSource, 'admin_post_vms_square_sync_protection_csv');
	vms_test_assert_stream_boundary(
		'Square Sync Protection CSV export',
		$squareClosure,
		array(
			"current_user_can('manage_options')",
			"check_admin_referer('vms_square_sync_protection_csv');",
			'$report = bvmgr_square_sync_protection_get_report();',
			'nocache_headers();',
			"header('Content-Type: text/csv; charset=utf-8');",
			"header('Content-Disposition: attachment; filename=' . \$filename);",
			"fclose(\$out); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the bounded administrator CSV response stream opened on php://output; no local filesystem path or WP_Filesystem replacement applies to this HTTP output handle.",
			"fputcsv(\$out, array('Product ID', 'Name', 'SKU', 'Protection Reason', 'Sync with Square', 'Had Square Link', 'Square Meta Cleared'));",
			'exit;',
		)
	);

	$admissionsFunction = vms_test_extract_function($admissionsSource, 'vms_admission_export_csv');
	vms_test_assert_stream_boundary(
		'Admissions door-list CSV export',
		$admissionsFunction,
		array(
			'current_user_can(vms_admission_manage_capability())',
			"wp_verify_nonce(\$nonce, 'vms_admissions_export_csv_' . \$event_plan_id)",
			'$rows = $wpdb->get_results($wpdb->prepare(',
			'FROM %i WHERE event_plan_id = %d ORDER BY guest_name ASC, id ASC',
			'$table,',
			'$event_plan_id',
			"header('Content-Type: text/csv; charset=utf-8');",
			"header('Content-Disposition: attachment; filename=' . \$filename);",
			"fclose(\$fh); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the bounded administrator CSV response stream opened on php://output; no local filesystem path or WP_Filesystem replacement applies to this HTTP output handle.",
			"fputcsv(\$fh, array('Guest Name', 'Guest Email', 'Party Size', 'Phone', 'Notes', 'Status', 'Source', 'Owner Vendor'));",
			"vms_admission_audit_log(\$event_plan_id, null, 'export_csv', get_current_user_id(), 'admin', array(",
			'exit;',
		)
	);

	$passExportFunction = vms_test_extract_function($passClaimsSource, 'vms_pass_claims_handle_export_csv');
	vms_test_assert_stream_boundary(
		'Pass token CSV export',
		$passExportFunction,
		array(
			'current_user_can(vms_pass_claims_capability())',
			"wp_verify_nonce(\$nonce, 'vms_pass_export_' . \$batch_id)",
			'vms_pass_claims_get_tokens($batch_id, 10000)',
			'if (!headers_sent()) {',
			'nocache_headers();',
			"header('Content-Type: text/csv; charset=utf-8');",
			"header('Content-Disposition: attachment; filename=\"' . sanitize_file_name(\$filename) . '\"');",
			"fclose(\$out); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the bounded administrator CSV response stream opened on php://output; no local filesystem path or WP_Filesystem replacement applies to this HTTP output handle.",
			"fputcsv(\$out, array(",
			"'token_id',",
			"'created_at',",
			"vms_admission_audit_log(0, null, 'pass_tokens_export_csv', get_current_user_id(), 'admin', array(",
			'exit;',
		)
	);

	$passReportFunction = vms_test_extract_function($passClaimsSource, 'vms_pass_claims_handle_report_export_csv');
	vms_test_assert_stream_boundary(
		'Pass report CSV export',
		$passReportFunction,
		array(
			'current_user_can(vms_pass_claims_capability())',
			"wp_verify_nonce(\$nonce, 'vms_pass_report_export_' . \$scope)",
			"if (!in_array(\$scope, array('source', 'batch', 'source_event', 'event'), true)) {",
			'$rows = vms_pass_claims_reports_by_batch();',
			'$rows = vms_pass_claims_reports_source_events();',
			'$rows = vms_pass_claims_reports_by_event();',
			'$rows = vms_pass_claims_reports_by_source();',
			'if (!headers_sent()) {',
			'nocache_headers();',
			"header('Content-Type: text/csv; charset=utf-8');",
			"header('Content-Disposition: attachment; filename=\"' . sanitize_file_name(\$filename) . '\"');",
			"fclose(\$out); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the bounded administrator CSV response stream opened on php://output; no local filesystem path or WP_Filesystem replacement applies to this HTTP output handle.",
			"'batch_id',",
			"'source_id',",
			"'event_plan_id',",
			"vms_admission_audit_log(0, null, 'pass_reports_export_csv', get_current_user_id(), 'admin', array(",
			'exit;',
		)
	);

	echo "Admin export stream boundary remediation OK.\n";
} catch (Throwable $throwable) {
	fwrite(STDERR, $throwable->getMessage() . "\n");
	exit(1);
}
