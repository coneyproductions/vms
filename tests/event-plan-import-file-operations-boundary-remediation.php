<?php
declare(strict_types=1);

$repoRoot = dirname(__DIR__);

$runtimeFiles = array(
	'actions' => $repoRoot . '/includes/admin/data-tools/actions-event-plan-import.php',
	'engine' => $repoRoot . '/includes/services/event-plan-import/event-plan-import-engine.php',
);

$sources = array();
foreach ($runtimeFiles as $key => $path) {
	$source = file_get_contents($path);
	if (!is_string($source)) {
		throw new RuntimeException('Could not read required runtime file: ' . $path);
	}

	$sources[$key] = $source;
}

$assert = static function (bool $condition, string $message): void {
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
};

$extractFunction = static function (string $source, string $functionName): string {
	$needle = 'function ' . $functionName;
	$start = strpos($source, $needle);
	if ($start === false) {
		throw new RuntimeException('Could not find function ' . $functionName . '().');
	}

	$braceStart = strpos($source, '{', $start);
	if ($braceStart === false) {
		throw new RuntimeException('Could not find opening brace for ' . $functionName . '().');
	}

	$length = strlen($source);
	$depth = 0;
	for ($offset = $braceStart; $offset < $length; $offset++) {
		$character = $source[$offset];
		if ($character === '{') {
			$depth++;
			continue;
		}
		if ($character === '}') {
			$depth--;
			if ($depth === 0) {
				return substr($source, $start, $offset - $start + 1);
			}
		}
	}

	throw new RuntimeException('Could not extract function body for ' . $functionName . '().');
};

$countPattern = static function (string $pattern, string $source): int {
	$result = preg_match_all($pattern, $source, $matches);
	if ($result === false) {
		throw new RuntimeException('Regex failed for pattern ' . $pattern);
	}

	return $result;
};

$actionsSource = $sources['actions'];
$engineSource = $sources['engine'];
$allSource = $actionsSource . "\n" . $engineSource;

$previewAction = $extractFunction($actionsSource, 'vms_event_plan_import_handle_preview_action');
$downloadReportAction = $extractFunction($actionsSource, 'vms_event_plan_import_handle_download_report_action');
$sampleDownloadAction = $extractFunction($actionsSource, 'vms_event_plan_import_handle_download_sample_csv');
$deletePreviewPayload = $extractFunction($engineSource, 'vms_event_plan_import_delete_preview_payload');
$deleteStoredFile = $extractFunction($engineSource, 'vms_event_plan_import_delete_stored_file');
$buildPreviewFromCsv = $extractFunction($engineSource, 'vms_event_plan_import_build_preview_from_csv');
$readRowsJson = $extractFunction($engineSource, 'vms_event_plan_import_read_rows_json');
$revertLastRun = $extractFunction($engineSource, 'vms_event_plan_import_revert_last_run');

$assert(substr_count($allSource, 'phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod') === 1, 'F4 should contain exactly one chmod suppression.');
$assert(substr_count($allSource, 'phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile') === 1, 'F4 should contain exactly one readfile suppression.');
$assert(substr_count($allSource, 'phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen') === 2, 'F4 should contain exactly two fopen suppressions.');
$assert(substr_count($allSource, 'phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose') === 3, 'F4 should contain exactly three fclose suppressions.');
$assert(strpos($allSource, 'phpcs:disable') === false, 'F4 should not introduce phpcs:disable.');
$assert(strpos($allSource, 'phpcs:enable') === false, 'F4 should not introduce phpcs:enable.');

$assert(substr_count($allSource, 'wp_delete_file(') === 2, 'F4 should use wp_delete_file() exactly twice.');
$assert($countPattern('/(^|[^[:alnum:]_])@unlink\(/m', $allSource) === 0, 'F4 should not retain native @unlink() in runtime files.');
$assert(strpos($allSource, 'WP_Filesystem(') === false, 'F4 should not introduce WP_Filesystem initialization.');
$assert(strpos($allSource, '::move(') === false, 'F4 should not introduce WP_Filesystem move handling.');
$assert(strpos($allSource, 'rename(') === false, 'F4 should not introduce direct rename() handling.');
$assert(strpos($allSource, 'copy(') === false, 'F4 should not introduce direct copy() handling.');

$assert(strpos($previewAction, "current_user_can('manage_options')") !== false, 'Preview action should retain the manage_options capability gate.');
$assert(strpos($previewAction, "check_admin_referer('vms_event_plan_import_preview')") !== false, 'Preview action should retain the preview nonce gate.');
$assert(strpos($previewAction, "bvmgr_upload_read_file(\$_FILES, 'event_plan_csv_file')") !== false, 'Preview action should retain the event_plan_csv_file upload field.');
$assert(strpos($previewAction, 'bvmgr_validate_uploaded_file(') !== false, 'Preview action should retain shared upload validation.');
$assert(strpos($previewAction, "vms_event_plan_import_prepare_generated_path('csv', \$token, 'source')") !== false, 'Preview action should retain deterministic <token>-source.csv staging.');
$assert(strpos($previewAction, 'wp_handle_upload(') !== false, 'Preview action should retain wp_handle_upload() staging.');
$assert(strpos($previewAction, 'vms_event_plan_import_path_is_safe($handled_file)') !== false, 'Preview action should retain its safe-path guard before handled-file rollback.');
$assert(strpos($previewAction, 'wp_delete_file($handled_file);') !== false, 'Preview action should use wp_delete_file() for safe handled-file rollback.');
$assert(strpos($previewAction, '@chmod($target_path, 0640); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod') !== false, 'Preview action should retain only a line-specific chmod suppression.');
$assert(strpos($previewAction, "vms_event_plan_import_build_preview_from_csv(\$target_path, \$source_name, \$options, \$token, \$target_key)") !== false, 'Preview action should retain the existing preview-builder call signature.');
$assert(strpos($previewAction, 'vms_event_plan_import_delete_stored_file($target_key);') !== false, 'Preview action should retain staged source rollback when preview building fails.');

$assert(strpos($downloadReportAction, "current_user_can('manage_options')") !== false, 'Preview report download should retain the manage_options capability gate.');
$assert(strpos($downloadReportAction, "wp_verify_nonce(\$nonce, 'vms_event_plan_import_download_report_' . \$token)") !== false, 'Preview report download should retain the token-scoped nonce gate.');
$assert(strpos($downloadReportAction, "vms_event_plan_import_storage_path((string) (\$preview['report_csv_storage_key'] ?? (\$preview['report_csv_path'] ?? '')))") !== false, 'Preview report download should retain the storage-key to legacy-path fallback.');
$assert(strpos($downloadReportAction, '!file_exists($path)') !== false && strpos($downloadReportAction, '!vms_event_plan_import_path_is_safe($path)') !== false, 'Preview report download should retain file-existence and safe-path checks.');
$assert(strpos($downloadReportAction, "bvmgr_private_files_stream_path(\$path, \$filename, 'text/csv');") !== false, 'Preview report download should still prefer the shared private stream helper.');
$assert(strpos($downloadReportAction, 'readfile($path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile') !== false, 'Preview report download should retain only a line-specific readfile suppression.');
$assert(strpos($downloadReportAction, 'file_get_contents(') === false, 'Preview report download should not switch to a full-memory read.');

$assert(strpos($sampleDownloadAction, "check_admin_referer('vms_event_plan_import_download_sample_csv')") !== false, 'Sample CSV download should retain its nonce gate.');
$assert(strpos($sampleDownloadAction, "fopen('php://output', 'wb')") !== false, 'Sample CSV download should retain the php://output stream.');
$assert(strpos($sampleDownloadAction, "fputcsv(\$out, array(") !== false, 'Sample CSV download should still emit CSV rows directly.');
$assert(strpos($sampleDownloadAction, 'fclose($out); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose') !== false, 'Sample CSV download should retain only a line-specific fclose suppression.');

$assert(strpos($deletePreviewPayload, "foreach (array('source_csv_storage_key', 'rows_json_storage_key', 'report_csv_storage_key') as \$storage_key_field)") !== false, 'Preview cleanup should still delete source, rows, and report storage keys.');
$assert(strpos($deletePreviewPayload, "foreach (array('source_csv_path', 'rows_json_path', 'report_csv_path') as \$legacy_path_field)") !== false, 'Preview cleanup should still honor legacy path fields during deletion.');
$assert(strpos($deletePreviewPayload, 'vms_event_plan_import_delete_stored_file((string) $payload[$storage_key_field]);') !== false, 'Preview cleanup should still route storage-key deletion through the shared staged-file helper.');

$assert(strpos($deleteStoredFile, 'vms_event_plan_import_storage_path($reference)') !== false, 'Stored-file deletion should still resolve through the storage-path helper.');
$assert(strpos($deleteStoredFile, "if (\$path !== '' && file_exists(\$path) && is_file(\$path)) {") !== false, 'Stored-file deletion should still require an existing regular file.');
$assert(strpos($deleteStoredFile, 'wp_delete_file($path);') !== false, 'Stored-file deletion should use wp_delete_file().');

$assert(strpos($buildPreviewFromCsv, 'fopen($csv_path, \'rb\'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen') !== false, 'Preview builder should retain only a line-specific fopen suppression for the staged CSV reader.');
$assert(substr_count($buildPreviewFromCsv, 'finally {') === 2, 'Preview builder should close both staged-file streams via finally blocks.');
$assert(strpos($buildPreviewFromCsv, 'fclose($fh); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose') !== false, 'Preview builder should close the staged CSV stream with a line-specific suppression.');
$assert(strpos($buildPreviewFromCsv, 'while (($cells = fgetcsv($fh)) !== false) {') !== false, 'Preview builder should preserve the incremental CSV parse loop.');
$assert(strpos($buildPreviewFromCsv, "'row_number' => \$row_number") !== false, 'Preview builder should preserve per-row numbering in report and commit payloads.');
$assert(strpos($buildPreviewFromCsv, "vms_event_plan_import_prepare_generated_path('json', \$token, 'rows')") !== false, 'Preview builder should retain deterministic rows-cache storage.');
$assert(strpos($buildPreviewFromCsv, "vms_event_plan_import_prepare_generated_path('csv', \$token, 'preview-report')") !== false, 'Preview builder should retain deterministic preview-report storage.');
$assert(strpos($buildPreviewFromCsv, 'file_put_contents($rows_json_path, wp_json_encode($json_payload, JSON_PRETTY_PRINT))') !== false, 'Preview builder should retain JSON row-cache writing.');
$assert(strpos($buildPreviewFromCsv, 'vms_event_plan_import_delete_stored_file($rows_json_storage_key);') !== false, 'Preview builder should still delete the rows cache on write failure.');
$assert(strpos($buildPreviewFromCsv, 'vms_event_plan_import_delete_stored_file($report_csv_storage_key);') !== false, 'Preview builder should still delete the report CSV on write failure.');
$assert(strpos($buildPreviewFromCsv, 'fopen($report_csv_path, \'wb\'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen') !== false, 'Preview builder should retain only a line-specific fopen suppression for the preview-report writer.');
$assert(strpos($buildPreviewFromCsv, "fputcsv(\$report_fh, array('row_number', 'event_key', 'plan_id', 'action', 'messages'));") !== false, 'Preview builder should retain the preview-report CSV header.');
$assert(strpos($buildPreviewFromCsv, 'fclose($report_fh); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose') !== false, 'Preview builder should close the preview-report writer with a line-specific suppression.');
$assert(strpos($buildPreviewFromCsv, '$source_hash = sha1_file($csv_path);') !== false, 'Preview builder should retain source file hashing for commit continuity.');
$assert(strpos($buildPreviewFromCsv, '$source_hash = sha1($token . \'|\' . filesize($csv_path));') !== false, 'Preview builder should retain the source-hash fallback when sha1_file() fails.');
$assert(strpos($buildPreviewFromCsv, "'source_csv_storage_key' => \$source_storage_key !== '' ? \$source_storage_key : ''") !== false, 'Preview builder should preserve source_csv_storage_key in the preview payload.');
$assert(strpos($buildPreviewFromCsv, "'rows_json_storage_key' => \$rows_json_storage_key") !== false, 'Preview builder should preserve rows_json_storage_key in the preview payload.');
$assert(strpos($buildPreviewFromCsv, "'report_csv_storage_key' => \$report_csv_storage_key") !== false, 'Preview builder should preserve report_csv_storage_key in the preview payload.');

$assert(strpos($readRowsJson, 'vms_event_plan_import_storage_path($rows_json_reference)') !== false, 'Rows-cache reader should still resolve through the storage-path helper.');
$assert(strpos($readRowsJson, '!vms_event_plan_import_path_is_safe($rows_json_path)') !== false, 'Rows-cache reader should still enforce the safe-path guard.');
$assert(strpos($readRowsJson, '5 * 1024 * 1024') !== false, 'Rows-cache reader should still enforce the 5 MB JSON read cap.');
$assert(strpos($readRowsJson, '$raw = file_get_contents($rows_json_path);') !== false, 'Rows-cache reader should still read only the validated staged JSON path.');
$assert(strpos($readRowsJson, 'bvmgr_json_decode_associative($raw, 64)') !== false, 'Rows-cache reader should still decode through the bounded JSON helper.');

$assert(strpos($revertLastRun, "vms_event_plan_import_storage_path((string) (\$run['snapshot_storage_key'] ?? (\$run['snapshot_path'] ?? '')))") !== false, 'Revert should still resolve snapshot files through the storage-path helper.');
$assert(strpos($revertLastRun, '!vms_event_plan_import_path_is_safe($snapshot_path)') !== false, 'Revert should still enforce the safe-path guard.');
$assert(strpos($revertLastRun, '5 * 1024 * 1024') !== false, 'Revert should still enforce the 5 MB snapshot read cap.');
$assert(strpos($revertLastRun, '$raw = file_get_contents($snapshot_path);') !== false, 'Revert should still read only the validated snapshot path.');
$assert(strpos($revertLastRun, 'bvmgr_json_decode_associative($raw, 64)') !== false, 'Revert should still decode through the bounded JSON helper.');

fwrite(STDOUT, "event-plan-import-file-operations-boundary-remediation: OK\n");
