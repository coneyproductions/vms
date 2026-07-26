<?php
declare(strict_types=1);

$repoRoot = dirname(__DIR__);
$actionsFile = $repoRoot . '/includes/admin/data-tools/actions-event-plan-import.php';
$pageFile = $repoRoot . '/includes/admin/data-tools/page-event-plan-import.php';
$engineFile = $repoRoot . '/includes/services/event-plan-import/event-plan-import-engine.php';

$actionsSource = file_get_contents($actionsFile);
$pageSource = file_get_contents($pageFile);
$engineSource = file_get_contents($engineFile);

if (!is_string($actionsSource) || !is_string($pageSource) || !is_string($engineSource)) {
	throw new RuntimeException('Could not read one or more required source files.');
}

$assert = static function (bool $condition, string $message): void {
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
};

$assert(strpos($actionsSource, 'move_uploaded_file(') === false, 'Event Plan import actions should no longer call move_uploaded_file() directly.');
$assert(strpos($actionsSource, 'media_handle_upload(') === false, 'Event Plan import actions should not use media_handle_upload().');
$assert(strpos($actionsSource, 'wp_handle_sideload(') === false, 'Event Plan import actions should not use wp_handle_sideload().');
$assert(strpos($actionsSource, 'wp_upload_bits(') === false, 'Event Plan import actions should not use wp_upload_bits().');
$assert(strpos($actionsSource, 'copy(') === false, 'Event Plan import actions should not introduce direct copy() handling.');
$assert(strpos($actionsSource, 'rename(') === false, 'Event Plan import actions should not introduce direct rename() handling.');
$assert(strpos($actionsSource, 'wp_handle_upload(') !== false, 'Event Plan import actions should call wp_handle_upload().');
$assert(substr_count($actionsSource, "'test_form' => false") === 1, 'Event Plan import should disable test_form exactly once for this admin-post flow.');
$assert(strpos($actionsSource, "current_user_can('manage_options')") !== false, 'Event Plan import capability should remain manage_options.');
$assert(strpos($actionsSource, "check_admin_referer('vms_event_plan_import_preview')") !== false, 'Event Plan import nonce action should remain unchanged.');
$assert(strpos($actionsSource, "vms_upload_read_file(\$_FILES, 'event_plan_csv_file')") !== false, 'Event Plan import should keep the event_plan_csv_file upload field.');
$assert(strpos($actionsSource, "vms_validate_uploaded_file(\n") !== false || strpos($actionsSource, 'vms_validate_uploaded_file(') !== false, 'Event Plan import should preserve shared upload validation.');
$assert(strpos($actionsSource, "'Failed to store uploaded CSV file.'") !== false, 'Event Plan import should preserve the existing storage-failure notice.');
$assert(strpos($actionsSource, "vms_event_plan_import_build_preview_from_csv(\$target_path, \$source_name, \$options, \$token, \$target_key)") !== false, 'Event Plan import should keep the existing preview-builder call signature.');
$assert(strpos($actionsSource, "vms_event_plan_import_delete_stored_file(\$target_key);") !== false, 'Event Plan import should preserve preview-build rollback.');
$assert(strpos($actionsSource, "vms_event_plan_import_prepare_generated_path('csv', \$token, 'source')") !== false, 'Event Plan import should preserve the generated <token>-source.csv staging contract.');
$assert(strpos($actionsSource, "vms_event_plan_import_with_scoped_upload_dir(") !== false, 'Event Plan import should scope the upload_dir filter to the upload call.');
$assert(strpos($actionsSource, "remove_filter('upload_dir', 'vms_event_plan_import_filter_upload_dir');") !== false, 'Event Plan import should remove the scoped upload_dir filter.');
$assert(strpos($actionsSource, "basename(\$target_path)") !== false, 'Event Plan import should preserve the deterministic token-based source basename.');
$assert(strpos($actionsSource, "vms_event_plan_import_path_is_safe(\$handled_file)") !== false, 'Event Plan import should only delete unexpected handled files when safe.');
$assert(strpos($actionsSource, "wp_delete_file(\$handled_file);") !== false, 'Event Plan import should use wp_delete_file() for safe handled-file rollback.');
$assert(strpos($actionsSource, '@unlink($handled_file);') === false, 'Event Plan import should not retain @unlink() for handled-file rollback.');
$assert(strpos($actionsSource, "@chmod(\$target_path, 0640); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod") !== false, 'Event Plan import should retain only a line-specific chmod suppression for the staged CSV.');
$assert(strpos($actionsSource, "\$handled['url']") === false && strpos($actionsSource, '$handled["url"]') === false, 'Event Plan import should not use the returned public URL from wp_handle_upload().');
$assert(strpos($actionsSource, 'vms_private_files_store_validated_upload(') === false, 'Event Plan import should not route preview uploads through the shared private-file broker.');

$assert(strpos($pageSource, "wp_nonce_field('vms_event_plan_import_preview');") !== false, 'Event Plan import form should keep the existing preview nonce field.');
$assert(strpos($pageSource, 'name="event_plan_csv_file"') !== false, 'Event Plan import form should keep the existing upload field name.');
$assert(strpos($pageSource, 'name="action" value="vms_event_plan_import_preview"') !== false, 'Event Plan import form should keep the existing admin-post action.');

$assert(strpos($engineSource, "return 'event-plan-imports';") !== false, 'Event Plan import should preserve the event-plan-imports bucket.');
$assert(strpos($engineSource, "vms_event_plan_import_storage_bucket() . '/' . \$token . '-' . \$suffix . '.' . \$extension") !== false, 'Event Plan import should preserve token-based generated storage keys.');
$assert(strpos($engineSource, "'source_csv_storage_key'") !== false, 'Event Plan import preview payload should preserve source_csv_storage_key.');
$assert(strpos($engineSource, "'rows_json_storage_key'") !== false, 'Event Plan import preview payload should preserve rows_json_storage_key.');
$assert(strpos($engineSource, "'report_csv_storage_key'") !== false, 'Event Plan import preview payload should preserve report_csv_storage_key.');
$assert(strpos($actionsSource, "vms_event_plan_import_delete_preview_payload(\$token);") !== false, 'Event Plan import commit handler should preserve preview cleanup.');
$assert(strpos($engineSource, "foreach (array('source_csv_storage_key', 'rows_json_storage_key', 'report_csv_storage_key') as \$storage_key_field)") !== false, 'Event Plan import preview cleanup should still delete source, rows, and report storage keys.');
$assert(strpos($engineSource, "'source_csv_storage_key' => (string) (\$preview_payload['source_csv_storage_key'] ?? ''),") !== false, 'Event Plan import audit metadata should retain source_csv_storage_key.');
$assert(strpos($engineSource, "'report_csv_storage_key' => (string) (\$preview_payload['report_csv_storage_key'] ?? ''),") !== false, 'Event Plan import audit metadata should retain report_csv_storage_key.');

define('ABSPATH', __DIR__ . '/');

final class VmsEventPlanImportRedirectException extends RuntimeException
{
}

if (!class_exists('WP_Error')) {
	class WP_Error
	{
		private string $code;
		private string $message;

		public function __construct(string $code = '', string $message = '')
		{
			$this->code = $code;
			$this->message = $message;
		}

		public function get_error_code(): string
		{
			return $this->code;
		}

		public function get_error_message(): string
		{
			return $this->message;
		}
	}
}

function is_wp_error($thing): bool
{
	return $thing instanceof WP_Error;
}

function __(string $text, string $domain = ''): string
{
	unset($domain);
	return $text;
}

function esc_html__(string $text, string $domain = ''): string
{
	unset($domain);
	return $text;
}

function wp_normalize_path(string $path): string
{
	return str_replace('\\', '/', $path);
}

function trailingslashit(string $value): string
{
	return rtrim($value, "/\\") . '/';
}

function sanitize_key(string $value): string
{
	return strtolower((string) preg_replace('/[^a-z0-9_\-]/', '', $value));
}

function sanitize_text_field(string $value): string
{
	return trim($value);
}

function wp_unslash($value)
{
	if (is_array($value)) {
		return array_map('wp_unslash', $value);
	}
	if (is_string($value)) {
		return stripslashes($value);
	}

	return $value;
}

function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
{
	unset($hook, $callback, $priority, $accepted_args);
	return true;
}

function add_filter(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
{
	unset($priority, $accepted_args);
	$GLOBALS['vms_test_filters'][$hook][] = $callback;
	$GLOBALS['vms_test_filter_events'][] = array('add', $hook, $callback);
	return true;
}

function remove_filter(string $hook, $callback, int $priority = 10): bool
{
	unset($priority);
	$callbacks = $GLOBALS['vms_test_filters'][$hook] ?? array();
	foreach ($callbacks as $index => $registered) {
		if ($registered === $callback) {
			unset($callbacks[$index]);
		}
	}
	$GLOBALS['vms_test_filters'][$hook] = array_values($callbacks);
	$GLOBALS['vms_test_filter_events'][] = array('remove', $hook, $callback);
	return true;
}

function apply_filters(string $hook, $value)
{
	$callbacks = $GLOBALS['vms_test_filters'][$hook] ?? array();
	foreach ($callbacks as $callback) {
		$value = $callback($value);
	}

	return $value;
}

function current_user_can(string $capability): bool
{
	$GLOBALS['vms_test_calls']['current_user_can'][] = $capability;
	return !empty($GLOBALS['vms_test_case']['allow_current_user']);
}

function check_admin_referer(string $action): bool
{
	$GLOBALS['vms_test_calls']['check_admin_referer'][] = $action;
	return true;
}

function wp_safe_redirect(string $url): bool
{
	$GLOBALS['vms_test_calls']['wp_safe_redirect'][] = $url;
	throw new VmsEventPlanImportRedirectException($url);
}

function wp_handle_sideload(...$args): array
{
	unset($args);
	$GLOBALS['vms_test_forbidden_calls'][] = 'wp_handle_sideload';
	return array('error' => 'forbidden');
}

function media_handle_upload(...$args)
{
	unset($args);
	$GLOBALS['vms_test_forbidden_calls'][] = 'media_handle_upload';
	return 0;
}

function wp_upload_bits(...$args): array
{
	unset($args);
	$GLOBALS['vms_test_forbidden_calls'][] = 'wp_upload_bits';
	return array('error' => 'forbidden');
}

function wp_delete_file(string $path): bool
{
	$GLOBALS['vms_test_calls']['wp_delete_file'][] = $path;
	if ($path === '' || !file_exists($path)) {
		return false;
	}

	@unlink($path);
	return !file_exists($path);
}

function vms_event_plan_import_admin_page_url(array $args = array()): string
{
	if (empty($args)) {
		return '/wp-admin/admin.php?page=vms-import-event-plans';
	}

	return '/wp-admin/admin.php?page=vms-import-event-plans&' . http_build_query($args);
}

function vms_event_plan_import_allowed_mimes(): array
{
	return array(
		'csv' => array(
			'text/csv',
			'text/plain',
			'application/csv',
			'application/vnd.ms-excel',
		),
	);
}

function vms_event_plan_import_max_bytes(): int
{
	return 5 * 1024 * 1024;
}

function vms_event_plan_import_make_token(): string
{
	return (string) ($GLOBALS['vms_test_case']['token'] ?? 'epcsv_test_token');
}

function vms_upload_read_file(array $files, string $field)
{
	$GLOBALS['vms_test_calls']['vms_upload_read_file'][] = array(
		'files' => $files,
		'field' => $field,
	);
	return $GLOBALS['vms_test_case']['upload_return'];
}

function vms_validate_uploaded_file(array $upload, array $args = array())
{
	$GLOBALS['vms_test_calls']['vms_validate_uploaded_file'][] = array(
		'upload' => $upload,
		'args' => $args,
	);
	return $GLOBALS['vms_test_case']['validated_return'];
}

function vms_event_plan_import_prepare_generated_path(string $extension, string $token, string $suffix)
{
	$GLOBALS['vms_test_calls']['vms_event_plan_import_prepare_generated_path'][] = array(
		'extension' => $extension,
		'token' => $token,
		'suffix' => $suffix,
	);

	if (!empty($GLOBALS['vms_test_case']['prepare_generated_path_error'])) {
		return $GLOBALS['vms_test_case']['prepare_generated_path_error'];
	}

	$prepared = $GLOBALS['vms_test_case']['prepared_path'];
	$dir = dirname((string) $prepared['path']);
	if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
		throw new RuntimeException('Could not create test upload directory: ' . $dir);
	}

	$GLOBALS['vms_test_storage_map'][(string) $prepared['storage_key']] = (string) $prepared['path'];

	return $prepared;
}

function vms_event_plan_import_build_preview_from_csv(string $csv_path, string $source_name, array $options, string $token, string $source_storage_key = '')
{
	$GLOBALS['vms_test_calls']['vms_event_plan_import_build_preview_from_csv'][] = array(
		'csv_path' => $csv_path,
		'source_name' => $source_name,
		'options' => $options,
		'token' => $token,
		'source_storage_key' => $source_storage_key,
	);

	if (!empty($GLOBALS['vms_test_case']['build_preview_result'])) {
		return $GLOBALS['vms_test_case']['build_preview_result'];
	}

	return array(
		'token' => $token,
		'summary' => array(
			'create' => 1,
			'update' => 2,
			'skip' => 3,
			'errors' => 4,
		),
		'source_csv_storage_key' => $source_storage_key,
		'rows_json_storage_key' => 'event-plan-imports/' . $token . '-rows.json',
		'report_csv_storage_key' => 'event-plan-imports/' . $token . '-preview-report.csv',
	);
}

function vms_event_plan_import_delete_stored_file(string $reference): void
{
	$GLOBALS['vms_test_calls']['vms_event_plan_import_delete_stored_file'][] = $reference;
	$path = $GLOBALS['vms_test_storage_map'][$reference] ?? '';
	if (is_string($path) && $path !== '' && file_exists($path)) {
		unlink($path);
	}
}

function vms_event_plan_import_set_preview_payload(string $token, array $payload, int $user_id = 0): void
{
	$GLOBALS['vms_test_calls']['vms_event_plan_import_set_preview_payload'][] = array(
		'token' => $token,
		'payload' => $payload,
		'user_id' => $user_id,
	);
}

function vms_event_plan_import_set_notice(string $type, string $message): void
{
	$GLOBALS['vms_test_calls']['vms_event_plan_import_set_notice'][] = array(
		'type' => $type,
		'message' => $message,
	);
}

function vms_event_plan_import_path_is_safe(string $path): bool
{
	$safeRoot = (string) ($GLOBALS['vms_test_case']['safe_root'] ?? '');
	if ($safeRoot === '') {
		return false;
	}

	return strpos(wp_normalize_path($path), trailingslashit(wp_normalize_path($safeRoot))) === 0;
}

function wp_handle_upload(&$file, $overrides = false, $time = null): array
{
	unset($time);

	$uploads = array(
		'path' => (string) ($GLOBALS['vms_test_case']['default_upload_path'] ?? sys_get_temp_dir()),
		'basedir' => (string) ($GLOBALS['vms_test_case']['default_upload_path'] ?? sys_get_temp_dir()),
		'subdir' => '',
		'url' => 'https://example.test/uploads',
		'baseurl' => 'https://example.test/uploads',
		'error' => false,
	);
	$uploads = apply_filters('upload_dir', $uploads);

	$extension = pathinfo((string) $file['name'], PATHINFO_EXTENSION);
	$extension = $extension !== '' ? '.' . $extension : '';
	$name = pathinfo((string) $file['name'], PATHINFO_FILENAME);
	$uniqueCallback = is_array($overrides) && isset($overrides['unique_filename_callback']) ? $overrides['unique_filename_callback'] : null;
	$filename = is_callable($uniqueCallback)
		? (string) $uniqueCallback((string) $uploads['path'], $name, $extension)
		: (string) $file['name'];

	$GLOBALS['vms_test_calls']['wp_handle_upload'][] = array(
		'file' => $file,
		'overrides' => $overrides,
		'uploads' => $uploads,
		'filename' => $filename,
		'upload_dir_filter_count' => count($GLOBALS['vms_test_filters']['upload_dir'] ?? array()),
	);

	$mode = (string) ($GLOBALS['vms_test_case']['wp_handle_upload_mode'] ?? 'success');
	if ($mode === 'throw') {
		throw new RuntimeException('Simulated wp_handle_upload exception.');
	}
	if ($mode === 'error') {
		return array('error' => 'Simulated upload failure.');
	}

	$path = '';
	if ($mode === 'unexpected_outside' || $mode === 'unexpected_inside') {
		$path = (string) ($GLOBALS['vms_test_case']['unexpected_path'] ?? '');
	} else {
		$path = rtrim((string) $uploads['path'], '/\\') . '/' . $filename;
	}
	if ($path === '') {
		throw new RuntimeException('wp_handle_upload() test stub did not resolve a target path.');
	}

	$dir = dirname($path);
	if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
		throw new RuntimeException('Could not create wp_handle_upload() test directory: ' . $dir);
	}
	file_put_contents($path, "event_key,event_date,venue_name,primary_vendor_name\n");

	return array(
		'file' => $path,
		'url' => ((string) $uploads['url'] === '' ? '/' : rtrim((string) $uploads['url'], '/') . '/') . basename($path),
		'type' => 'text/csv',
	);
}

require $actionsFile;

function vms_test_recursive_delete(string $path): void
{
	if (!file_exists($path)) {
		return;
	}

	if (is_file($path) || is_link($path)) {
		@unlink($path);
		return;
	}

	$entries = scandir($path);
	if (!is_array($entries)) {
		@rmdir($path);
		return;
	}

	foreach ($entries as $entry) {
		if ($entry === '.' || $entry === '..') {
			continue;
		}
		vms_test_recursive_delete($path . DIRECTORY_SEPARATOR . $entry);
	}

	@rmdir($path);
}

function vms_test_reset_case(string $mode = 'success'): void
{
	$tempRoot = sys_get_temp_dir() . '/vms-event-plan-import-upload-api-' . bin2hex(random_bytes(6));
	$safeRoot = $tempRoot . '/vms-private/event-plan-imports';
	if (!mkdir($safeRoot, 0777, true) && !is_dir($safeRoot)) {
		throw new RuntimeException('Could not create test safe root: ' . $safeRoot);
	}

	$token = 'epcsv_test_token';
	$targetPath = $safeRoot . '/' . $token . '-source.csv';
	$targetKey = 'event-plan-imports/' . $token . '-source.csv';
	$outsideDir = $tempRoot . '/outside';
	if (!mkdir($outsideDir, 0777, true) && !is_dir($outsideDir)) {
		throw new RuntimeException('Could not create test outside dir: ' . $outsideDir);
	}

	$GLOBALS['vms_test_case'] = array(
		'allow_current_user' => true,
		'wp_handle_upload_mode' => $mode,
		'token' => $token,
		'safe_root' => $safeRoot,
		'default_upload_path' => $tempRoot . '/default-uploads',
		'prepared_path' => array(
			'path' => $targetPath,
			'storage_key' => $targetKey,
		),
		'unexpected_path' => $mode === 'unexpected_outside'
			? $outsideDir . '/outside-source.csv'
			: $safeRoot . '/unexpected-source.csv',
		'upload_return' => array(
			'name' => 'Quarterly Notes.csv',
			'type' => 'text/plain',
			'tmp_name' => $tempRoot . '/php-upload.csv',
			'error' => UPLOAD_ERR_OK,
			'size' => 128,
		),
		'validated_return' => array(
			'name' => 'Quarterly Notes.csv',
			'sanitized_name' => 'Quarterly-Notes.csv',
			'type' => 'text/plain',
			'tmp_name' => $tempRoot . '/php-upload.csv',
			'error' => UPLOAD_ERR_OK,
			'size' => 512,
			'mime' => 'text/plain',
			'ext' => 'csv',
		),
		'build_preview_result' => null,
		'prepare_generated_path_error' => null,
		'temp_root' => $tempRoot,
	);

	file_put_contents((string) $GLOBALS['vms_test_case']['upload_return']['tmp_name'], "event_key,event_date,venue_name,primary_vendor_name\n");

	$GLOBALS['vms_test_calls'] = array(
		'current_user_can' => array(),
		'check_admin_referer' => array(),
		'vms_upload_read_file' => array(),
		'vms_validate_uploaded_file' => array(),
		'vms_event_plan_import_prepare_generated_path' => array(),
		'wp_handle_upload' => array(),
		'vms_event_plan_import_build_preview_from_csv' => array(),
		'vms_event_plan_import_delete_stored_file' => array(),
		'vms_event_plan_import_set_preview_payload' => array(),
		'vms_event_plan_import_set_notice' => array(),
		'wp_safe_redirect' => array(),
	);
	$GLOBALS['vms_test_filters'] = array();
	$GLOBALS['vms_test_filter_events'] = array();
	$GLOBALS['vms_test_forbidden_calls'] = array();
	$GLOBALS['vms_test_storage_map'] = array(
		$targetKey => $targetPath,
	);

	$_FILES = array(
		'event_plan_csv_file' => array(
			'name' => 'Quarterly Notes.csv',
			'type' => 'text/plain',
			'tmp_name' => (string) $GLOBALS['vms_test_case']['upload_return']['tmp_name'],
			'error' => UPLOAD_ERR_OK,
			'size' => 128,
		),
	);
	$_POST = array(
		'auto_create_missing_vendors' => '1',
		'allow_update_locked_plans' => '1',
	);
}

function vms_test_cleanup_case(): void
{
	$tempRoot = (string) ($GLOBALS['vms_test_case']['temp_root'] ?? '');
	if ($tempRoot !== '') {
		vms_test_recursive_delete($tempRoot);
	}
}

function vms_test_run_preview_action(): string
{
	try {
		vms_event_plan_import_handle_preview_action();
		throw new RuntimeException('Expected Event Plan preview action to terminate via redirect.');
	} catch (VmsEventPlanImportRedirectException $redirect) {
		return $redirect->getMessage();
	}
}

vms_test_reset_case('success');
$successRedirect = vms_test_run_preview_action();
$assert($successRedirect === '/wp-admin/admin.php?page=vms-import-event-plans&preview_token=epcsv_test_token', 'Successful Event Plan preview should preserve the existing preview_token redirect.');
$assert($GLOBALS['vms_test_calls']['current_user_can'] === array('manage_options'), 'Successful Event Plan preview should preserve the manage_options capability check.');
$assert($GLOBALS['vms_test_calls']['check_admin_referer'] === array('vms_event_plan_import_preview'), 'Successful Event Plan preview should preserve the preview nonce action.');
$assert(count($GLOBALS['vms_test_calls']['vms_upload_read_file']) === 1 && $GLOBALS['vms_test_calls']['vms_upload_read_file'][0]['field'] === 'event_plan_csv_file', 'Successful Event Plan preview should keep the event_plan_csv_file field.');
$validateCall = $GLOBALS['vms_test_calls']['vms_validate_uploaded_file'][0] ?? array();
$assert(($validateCall['args']['allowed_mimes'] ?? null) === vms_event_plan_import_allowed_mimes(), 'Successful Event Plan preview should preserve the CSV MIME allowlist.');
$assert(($validateCall['args']['max_bytes'] ?? null) === vms_event_plan_import_max_bytes(), 'Successful Event Plan preview should preserve the 5 MB upload cap through shared validation.');
$handleCall = $GLOBALS['vms_test_calls']['wp_handle_upload'][0] ?? array();
$assert(($handleCall['overrides']['test_form'] ?? null) === false, 'Successful Event Plan preview should disable test_form for the admin-post upload flow.');
$assert(($handleCall['overrides']['mimes'] ?? null) === vms_event_plan_import_allowed_mimes(), 'Successful Event Plan preview should pass the current CSV allowlist into wp_handle_upload().');
$assert(($handleCall['file']['size'] ?? null) === 512, 'Successful Event Plan preview should pass the measured validated file size into wp_handle_upload().');
$assert(($handleCall['upload_dir_filter_count'] ?? 0) === 1, 'Successful Event Plan preview should scope exactly one upload_dir filter around the wp_handle_upload() call.');
$assert(($handleCall['filename'] ?? '') === 'epcsv_test_token-source.csv', 'Successful Event Plan preview should preserve the deterministic token-based -source.csv basename.');
$assert(($handleCall['uploads']['path'] ?? '') === $GLOBALS['vms_test_case']['safe_root'], 'Successful Event Plan preview should direct wp_handle_upload() into the Event Plan import staging bucket.');
$previewCall = $GLOBALS['vms_test_calls']['vms_event_plan_import_build_preview_from_csv'][0] ?? array();
$assert(($previewCall['csv_path'] ?? '') === $GLOBALS['vms_test_case']['prepared_path']['path'], 'Successful Event Plan preview should pass the handled file path into the preview builder.');
$assert(($previewCall['source_storage_key'] ?? '') === $GLOBALS['vms_test_case']['prepared_path']['storage_key'], 'Successful Event Plan preview should preserve source_csv_storage_key.');
$assert(($previewCall['source_name'] ?? '') === 'Quarterly Notes.csv', 'Successful Event Plan preview should preserve the original source filename for preview metadata.');
$storedPayload = $GLOBALS['vms_test_calls']['vms_event_plan_import_set_preview_payload'][0]['payload'] ?? array();
$assert(is_array($storedPayload) && ($storedPayload['source_csv_storage_key'] ?? '') === 'event-plan-imports/epcsv_test_token-source.csv', 'Successful Event Plan preview should preserve source_csv_storage_key in the stored preview payload.');
$assert(is_array($storedPayload) && ($storedPayload['rows_json_storage_key'] ?? '') === 'event-plan-imports/epcsv_test_token-rows.json', 'Successful Event Plan preview should preserve rows_json_storage_key in the stored preview payload.');
$assert(is_array($storedPayload) && ($storedPayload['report_csv_storage_key'] ?? '') === 'event-plan-imports/epcsv_test_token-preview-report.csv', 'Successful Event Plan preview should preserve report_csv_storage_key in the stored preview payload.');
$lastNotice = end($GLOBALS['vms_test_calls']['vms_event_plan_import_set_notice']);
$assert(is_array($lastNotice) && $lastNotice['type'] === 'success' && $lastNotice['message'] === 'Preview ready. Create: 1, Update: 2, Skip: 3, Errors: 4.', 'Successful Event Plan preview should preserve the summary success notice.');
$assert(($GLOBALS['vms_test_filters']['upload_dir'] ?? array()) === array(), 'Successful Event Plan preview should remove the scoped upload_dir filter after wp_handle_upload().');
$assert($GLOBALS['vms_test_forbidden_calls'] === array(), 'Successful Event Plan preview should not invoke media_handle_upload(), wp_handle_sideload(), or wp_upload_bits().');
vms_test_cleanup_case();

vms_test_reset_case('error');
$errorRedirect = vms_test_run_preview_action();
$assert($errorRedirect === '/wp-admin/admin.php?page=vms-import-event-plans', 'WordPress upload API errors should preserve the existing admin-page redirect.');
$errorNotice = end($GLOBALS['vms_test_calls']['vms_event_plan_import_set_notice']);
$assert(is_array($errorNotice) && $errorNotice['type'] === 'error' && $errorNotice['message'] === 'Failed to store uploaded CSV file.', 'WordPress upload API errors should preserve the existing storage-failure notice.');
$assert($GLOBALS['vms_test_calls']['vms_event_plan_import_build_preview_from_csv'] === array(), 'WordPress upload API errors should not continue into preview building.');
$assert(($GLOBALS['vms_test_filters']['upload_dir'] ?? array()) === array(), 'WordPress upload API errors should still remove the scoped upload_dir filter.');
vms_test_cleanup_case();

vms_test_reset_case('unexpected_outside');
$outsideRedirect = vms_test_run_preview_action();
$outsideNotice = end($GLOBALS['vms_test_calls']['vms_event_plan_import_set_notice']);
$assert($outsideRedirect === '/wp-admin/admin.php?page=vms-import-event-plans', 'Unexpected handled paths outside the bucket should preserve the existing admin-page redirect.');
$assert(is_array($outsideNotice) && $outsideNotice['message'] === 'Failed to store uploaded CSV file.', 'Unexpected handled paths outside the bucket should preserve the existing storage-failure notice.');
$assert($GLOBALS['vms_test_calls']['vms_event_plan_import_build_preview_from_csv'] === array(), 'Unexpected handled paths outside the bucket should be rejected before preview building.');
$assert(($GLOBALS['vms_test_calls']['wp_delete_file'] ?? array()) === array(), 'Unexpected handled paths outside the safe bucket should not call wp_delete_file().');
$assert(file_exists((string) $GLOBALS['vms_test_case']['unexpected_path']), 'Unexpected handled paths outside the safe bucket should not be deleted unsafely.');
$assert(($GLOBALS['vms_test_filters']['upload_dir'] ?? array()) === array(), 'Unexpected handled paths outside the bucket should still remove the scoped upload_dir filter.');
vms_test_cleanup_case();

vms_test_reset_case('unexpected_inside');
$insideRedirect = vms_test_run_preview_action();
$insideNotice = end($GLOBALS['vms_test_calls']['vms_event_plan_import_set_notice']);
$assert($insideRedirect === '/wp-admin/admin.php?page=vms-import-event-plans', 'Unexpected handled paths inside the bucket should preserve the existing admin-page redirect.');
$assert(is_array($insideNotice) && $insideNotice['message'] === 'Failed to store uploaded CSV file.', 'Unexpected handled paths inside the bucket should preserve the existing storage-failure notice.');
$assert(($GLOBALS['vms_test_calls']['wp_delete_file'] ?? array()) === array((string) $GLOBALS['vms_test_case']['unexpected_path']), 'Unexpected handled paths inside the bucket should delete the safe handled file through wp_delete_file().');
$assert(!file_exists((string) $GLOBALS['vms_test_case']['unexpected_path']), 'Unexpected handled paths inside the safe bucket should be deleted when safe.');
$assert(($GLOBALS['vms_test_filters']['upload_dir'] ?? array()) === array(), 'Unexpected handled paths inside the bucket should still remove the scoped upload_dir filter.');
vms_test_cleanup_case();

vms_test_reset_case('success');
$GLOBALS['vms_test_case']['build_preview_result'] = new WP_Error('preview_failed', 'Preview build failed.');
$previewFailureRedirect = vms_test_run_preview_action();
$previewFailureNotice = end($GLOBALS['vms_test_calls']['vms_event_plan_import_set_notice']);
$assert($previewFailureRedirect === '/wp-admin/admin.php?page=vms-import-event-plans', 'Preview-build failures should preserve the existing admin-page redirect.');
$assert(is_array($previewFailureNotice) && $previewFailureNotice['type'] === 'error' && $previewFailureNotice['message'] === 'Preview build failed.', 'Preview-build failures should continue surfacing the preview builder error message.');
$assert($GLOBALS['vms_test_calls']['vms_event_plan_import_delete_stored_file'] === array('event-plan-imports/epcsv_test_token-source.csv'), 'Preview-build failures should still delete the stored source key.');
$assert($GLOBALS['vms_test_calls']['vms_event_plan_import_set_preview_payload'] === array(), 'Preview-build failures should not store preview payload state.');
$assert(($GLOBALS['vms_test_filters']['upload_dir'] ?? array()) === array(), 'Preview-build failures should still remove the scoped upload_dir filter.');
vms_test_cleanup_case();

vms_test_reset_case('throw');
$threw = false;
try {
	vms_event_plan_import_handle_preview_action();
} catch (RuntimeException $exception) {
	$threw = true;
	$assert($exception->getMessage() === 'Simulated wp_handle_upload exception.', 'Simulated wp_handle_upload exceptions should bubble with the original message.');
}
$assert($threw, 'Simulated wp_handle_upload exceptions should reach the test harness.');
$assert(($GLOBALS['vms_test_filters']['upload_dir'] ?? array()) === array(), 'Thrown wp_handle_upload exceptions should still remove the scoped upload_dir filter.');
vms_test_cleanup_case();

fwrite(STDOUT, "event-plan-import-upload-api-remediation: OK\n");
