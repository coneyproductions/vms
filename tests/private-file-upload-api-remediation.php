<?php
declare(strict_types=1);

$repoRoot = dirname(__DIR__);
$privateFilesFile = $repoRoot . '/includes/core/private-files.php';
$vendorPortalFile = $repoRoot . '/includes/portal/vendor-portal.php';
$ticketingFile = $repoRoot . '/includes/integrations/ticketing-verifications.php';
$adminW9File = $repoRoot . '/includes/admin/tax-profile-admin-metabox.php';
$vendorTaxFile = $repoRoot . '/includes/portal/vendor-tax-profile.php';
$staffPortalFile = $repoRoot . '/includes/portal/staff-portal.php';
$eventPlanFile = $repoRoot . '/includes/admin/data-tools/actions-event-plan-import.php';

$privateFilesSource = file_get_contents($privateFilesFile);
$vendorPortalSource = file_get_contents($vendorPortalFile);
$ticketingSource = file_get_contents($ticketingFile);
$adminW9Source = file_get_contents($adminW9File);
$vendorTaxSource = file_get_contents($vendorTaxFile);
$staffPortalSource = file_get_contents($staffPortalFile);
$eventPlanSource = file_get_contents($eventPlanFile);

if (
	!is_string($privateFilesSource)
	|| !is_string($vendorPortalSource)
	|| !is_string($ticketingSource)
	|| !is_string($adminW9Source)
	|| !is_string($vendorTaxSource)
	|| !is_string($staffPortalSource)
	|| !is_string($eventPlanSource)
) {
	throw new RuntimeException('Could not read one or more required source files.');
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

$runCommand = static function (string $command) use ($repoRoot): string {
	if (!function_exists('shell_exec')) {
		throw new RuntimeException('shell_exec() is required for this focused diff guard.');
	}

	$output = shell_exec('cd ' . escapeshellarg($repoRoot) . ' && ' . $command . ' 2>/dev/null');
	return is_string($output) ? trim($output) : '';
};

$brokerFunction = $extractFunction($privateFilesSource, 'vms_private_files_store_validated_upload');
$staffCertFunction = $extractFunction($staffPortalSource, 'vms_staff_portal_handle_certification_submission');
$verificationStoreFunction = $extractFunction($ticketingSource, 'vms_ticketing_verification_store_proof_file');

$assert(strpos($brokerFunction, 'move_uploaded_file(') === false, 'The shared private-file broker should no longer call move_uploaded_file() directly.');
$assert(strpos($brokerFunction, 'copy(') === false, 'The shared private-file broker should not introduce copy().');
$assert(strpos($brokerFunction, 'rename(') === false, 'The shared private-file broker should not introduce rename().');
$assert(strpos($brokerFunction, 'media_handle_upload(') === false, 'The shared private-file broker should not introduce media_handle_upload().');
$assert(strpos($brokerFunction, 'wp_handle_sideload(') === false, 'The shared private-file broker should not introduce wp_handle_sideload().');
$assert(strpos($brokerFunction, 'wp_upload_bits(') === false, 'The shared private-file broker should not introduce wp_upload_bits().');
$assert(strpos($brokerFunction, 'wp_handle_upload(') !== false, 'The shared private-file broker should call wp_handle_upload().');
$assert(strpos($privateFilesSource, "require_once ABSPATH . 'wp-admin/includes/file.php';") !== false, 'The broker should lazy-load wp-admin/includes/file.php when wp_handle_upload() is unavailable.');
$assert(substr_count($brokerFunction, "'test_form' => false") === 1, 'The broker should pass test_form => false exactly once.');
$assert(strpos($brokerFunction, "'mimes' => \$allowed_mimes") !== false, 'The broker should pass the caller MIME map to wp_handle_upload().');
$assert(strpos($brokerFunction, "'unique_filename_callback' => static function") !== false, 'The broker should preserve the generated basename through unique_filename_callback.');
$assert(strpos($privateFilesSource, "add_filter('upload_dir', 'vms_private_files_filter_upload_dir');") !== false, 'The broker should scope upload_dir through a dedicated filter.');
$assert(strpos($privateFilesSource, "remove_filter('upload_dir', 'vms_private_files_filter_upload_dir');") !== false, 'The broker should remove the upload_dir filter after each upload call.');
$assert(strpos($privateFilesSource, "unset(\$GLOBALS['bvmgr_private_files_upload_dir_context']);") !== false, 'The broker should clear the upload_dir context after each upload call.');
$assert(strpos($brokerFunction, 'wp_normalize_path($handled_real) === wp_normalize_path($destination_real)') !== false, 'The broker should verify the handled path exactly matches the intended destination.');
$assert(strpos($brokerFunction, 'vms_private_files_path_is_safe($handled_file)') !== false, 'The broker should only unlink unexpected handled files when safe.');
$assert(strpos($brokerFunction, "\$handled['url']") === false && strpos($brokerFunction, '$handled["url"]') === false, 'The broker should not use the returned public URL.');
$assert(strpos($brokerFunction, 'wp_insert_attachment(') === false, 'The broker should not create attachments.');
$assert(strpos($brokerFunction, '@chmod($destination, 0640);') !== false, 'The broker should preserve the existing 0640 permission behavior.');
$assert(strpos($privateFilesSource, "'allowed_mimes' => vms_private_w9_allowed_mimes()") !== false, 'The W-9 wrapper should pass its exact MIME map into the broker.');
$assert(strpos($privateFilesSource, "'allowed_mimes' => vms_private_staff_cert_allowed_mimes()") !== false, 'The staff-cert wrapper should pass its exact MIME map into the broker.');
$assert(strpos($vendorPortalSource, '$allowed_mimes = vms_vendor_portal_tech_doc_allowed_mimes();') !== false, 'Vendor tech docs should reuse the same MIME map for validation and storage.');
$assert(substr_count($vendorPortalSource, "'allowed_mimes' => \$allowed_mimes") >= 2, 'Vendor tech docs should pass the exact same MIME map to validation and storage.');
$assert(strpos($ticketingSource, '$allowed_mimes = vms_ticketing_verification_allowed_mimes();') !== false, 'Ticket verification proofs should reuse the same MIME map for storage.');
$assert(strpos($verificationStoreFunction, "'allowed_mimes' => \$allowed_mimes") !== false, 'The non-image verification proof branch should pass the exact validation MIME map into the broker.');
$assert(strpos($privateFilesSource, 'get_allowed_mime_types(') === false, 'The broker should not fall back to the global WordPress MIME allowlist.');
$assert(strpos($privateFilesSource, 'wp_get_mime_types(') === false, 'The broker should not widen the MIME boundary through a global MIME helper.');
$assert(strpos($privateFilesSource, "explode('|', (string) \$extensions)") !== false, 'The MIME normalizer should preserve grouped extension keys.');

$assert(strpos($adminW9Source, 'vms_private_files_delete($previous_upload_id);') !== false, 'The admin W-9 replacement cleanup should remain unchanged.');
$assert(strpos($vendorTaxSource, 'vms_private_files_delete($previous_upload_id);') !== false, 'The vendor tax-profile W-9 replacement cleanup should remain unchanged.');
$assert(strpos($staffPortalSource, 'vms_private_files_delete($previous_upload_id);') !== false, 'The staff W-9 replacement cleanup should remain unchanged.');
$assert(strpos($staffCertFunction, 'vms_private_files_delete(') === false, 'The staff certification submission flow should preserve its existing downstream no-rollback behavior.');
$assert(strpos($vendorPortalSource, 'vms_private_files_delete($previous_id);') !== false, 'Vendor tech-document replacement cleanup should remain unchanged.');
$assert(strpos($ticketingSource, 'vms_private_files_delete((int) $stored[\'file_id\']);') !== false, 'Ticket verification request-creation rollback should remain unchanged.');
$assert(strpos($verificationStoreFunction, 'vms_ticketing_verification_optimize_image_upload(') !== false, 'The verification image branch should remain intact.');
$assert(strpos($verificationStoreFunction, 'vms_private_files_register_path(') !== false, 'The verification image branch should still register files directly.');
$assert(strpos($verificationStoreFunction, 'vms_private_files_store_validated_upload(') !== false, 'The verification non-image branch should still use the shared broker.');
$assert(strpos($eventPlanSource, 'vms_event_plan_import_with_scoped_upload_dir(') !== false, 'The Event Plan upload API implementation should remain unchanged.');
$assert(strpos($eventPlanSource, 'wp_handle_upload(') !== false, 'The Event Plan upload API implementation should remain present.');

define('ABSPATH', __DIR__ . '/');

final class VmsPrivateFilesUploadApiException extends RuntimeException
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

function wp_is_writable(string $path): bool
{
	return is_writable($path);
}

function wp_delete_file(string $path): bool
{
	return @unlink($path);
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

function sanitize_file_name(string $value): string
{
	$value = preg_replace('/[^A-Za-z0-9._-]+/', '-', $value);
	return trim((string) $value, '-');
}

function absint($value): int
{
	return abs((int) $value);
}

function wp_normalize_path(string $path): string
{
	return str_replace('\\', '/', $path);
}

function wp_upload_dir($time = null, bool $create_dir = true): array
{
	unset($time, $create_dir);
	$baseDir = (string) ($GLOBALS['vms_test_case']['uploads_base_dir'] ?? sys_get_temp_dir());
	return array(
		'path' => $baseDir,
		'basedir' => $baseDir,
		'subdir' => '',
		'url' => 'https://example.test/uploads',
		'baseurl' => 'https://example.test/uploads',
		'error' => false,
	);
}

function wp_mkdir_p(string $target): bool
{
	return is_dir($target) || mkdir($target, 0777, true);
}

function wp_generate_uuid4(): string
{
	return (string) ($GLOBALS['vms_test_case']['uuid'] ?? 'uuid-test');
}

function current_time(string $type)
{
	return $type === 'mysql' ? '2026-07-18 12:00:00' : 1784376000;
}

function get_current_user_id(): int
{
	return 77;
}

function get_option(string $option, $default = false)
{
	unset($option);
	return $default;
}

function update_option(string $option, $value, bool $autoload = true): bool
{
	unset($option, $value, $autoload);
	return true;
}

function dbDelta(string $sql): void
{
	unset($sql);
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
		throw new VmsPrivateFilesUploadApiException('Simulated wp_handle_upload exception.');
	}
	if ($mode === 'error') {
		return array('error' => 'Simulated upload failure.');
	}

	if ($mode === 'unexpected_inside' || $mode === 'unexpected_outside') {
		$path = (string) ($GLOBALS['vms_test_case']['unexpected_path'] ?? '');
	} else {
		$path = rtrim((string) $uploads['path'], '/\\') . '/' . $filename;
	}
	if ($path === '') {
		throw new RuntimeException('The wp_handle_upload() test stub did not resolve a target path.');
	}

	$dir = dirname($path);
	if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
		throw new RuntimeException('Could not create the wp_handle_upload() test directory: ' . $dir);
	}
	file_put_contents($path, (string) ($GLOBALS['vms_test_case']['stored_file_contents'] ?? 'private upload'));

	return array(
		'file' => $path,
		'url' => ((string) $uploads['url'] === '' ? '/' : rtrim((string) $uploads['url'], '/') . '/') . basename($path),
		'type' => (string) ($file['type'] ?? 'application/octet-stream'),
	);
}

require $privateFilesFile;

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

function vms_test_make_wpdb(string $prefix)
{
	return new class($prefix) {
		public string $prefix;
		public int $insert_id = 0;

		public function __construct(string $prefix)
		{
			$this->prefix = $prefix;
		}

		public function get_charset_collate(): string
		{
			return '';
		}

		public function insert(string $table, array $data, array $format): bool
		{
			$GLOBALS['vms_test_calls']['wpdb_insert'][] = array(
				'table' => $table,
				'data' => $data,
				'format' => $format,
			);

			if (!empty($GLOBALS['vms_test_case']['wpdb_insert_fail'])) {
				return false;
			}

			$this->insert_id = (int) ($GLOBALS['vms_test_case']['insert_id'] ?? 321);
			return true;
		}
	};
}

function vms_test_reset_case(string $mode = 'success'): void
{
	$tempRoot = sys_get_temp_dir() . '/vms-private-upload-api-' . bin2hex(random_bytes(6));
	$uploadsBaseDir = $tempRoot . '/uploads';
	$privateRoot = $uploadsBaseDir . '/vms-private';
	$bucketDir = $privateRoot . '/verifications';
	$outsideDir = $tempRoot . '/outside';
	if (!mkdir($outsideDir, 0777, true) && !is_dir($outsideDir)) {
		throw new RuntimeException('Could not create the outside mismatch directory.');
	}

	$tmpUpload = $tempRoot . '/php-upload.pdf';
	if (!is_dir(dirname($tmpUpload)) && !mkdir(dirname($tmpUpload), 0777, true) && !is_dir(dirname($tmpUpload))) {
		throw new RuntimeException('Could not create the temporary upload directory.');
	}
	file_put_contents($tmpUpload, 'validated upload');

	$GLOBALS['vms_test_case'] = array(
		'wp_handle_upload_mode' => $mode,
		'uploads_base_dir' => $uploadsBaseDir,
		'default_upload_path' => $tempRoot . '/default-uploads',
		'uuid' => 'uuid-test',
		'insert_id' => 321,
		'wpdb_insert_fail' => false,
		'stored_file_contents' => 'private upload',
		'tmp_upload' => $tmpUpload,
		'expected_destination' => $bucketDir . '/uuid-test.pdf',
		'unexpected_path' => $mode === 'unexpected_outside'
			? $outsideDir . '/mismatch.pdf'
			: $bucketDir . '/unexpected.pdf',
		'temp_root' => $tempRoot,
	);

	$GLOBALS['vms_test_filters'] = array();
	$GLOBALS['vms_test_filter_events'] = array();
	$GLOBALS['vms_test_calls'] = array(
		'wp_handle_upload' => array(),
		'wpdb_insert' => array(),
	);
	$GLOBALS['wpdb'] = vms_test_make_wpdb('wp_');
}

function vms_test_validated_upload(): array
{
	return array(
		'name' => 'Verification Proof.pdf',
		'sanitized_name' => 'Verification-Proof.pdf',
		'tmp_name' => (string) $GLOBALS['vms_test_case']['tmp_upload'],
		'reported_mime' => 'application/pdf',
		'reported_size' => 128,
		'size' => 128,
		'ext' => 'pdf',
		'mime' => 'application/pdf',
	);
}

function vms_test_allowed_mimes(): array
{
	return array(
		'pdf' => 'application/pdf',
		'jpg|jpeg|jpe' => array('image/jpeg', 'image/pjpeg'),
	);
}

function vms_test_assert_filter_cycle(string $messagePrefix): void
{
	$events = $GLOBALS['vms_test_filter_events'];
	$assert = $GLOBALS['vms_test_assert'];
	$assert(count($events) >= 2, $messagePrefix . ' should add and remove the upload_dir filter.');
	$assert($events[0][0] === 'add' && $events[0][1] === 'upload_dir', $messagePrefix . ' should add the upload_dir filter first.');
	$assert($events[count($events) - 1][0] === 'remove' && $events[count($events) - 1][1] === 'upload_dir', $messagePrefix . ' should remove the upload_dir filter last.');
	$assert(($GLOBALS['vms_test_filters']['upload_dir'] ?? array()) === array(), $messagePrefix . ' should leave no upload_dir filters registered.');
}

$GLOBALS['vms_test_assert'] = $assert;

vms_test_reset_case('success');
$validated = vms_test_validated_upload();
$allowedMimes = vms_test_allowed_mimes();
$result = vms_private_files_store_validated_upload(
	$validated,
	array(
		'allowed_mimes' => $allowedMimes,
		'bucket' => 'verifications',
		'related_post_type' => 'ticket_verification',
		'related_post_id' => 45,
	)
);
$assert(!is_wp_error($result), 'The broker should succeed for a valid upload.');
$assert($result === 321, 'The broker should return the registered private-file ID.');
$expectedDestination = (string) $GLOBALS['vms_test_case']['expected_destination'];
$call = $GLOBALS['vms_test_calls']['wp_handle_upload'][0] ?? null;
$assert(is_array($call), 'The broker should call wp_handle_upload() exactly once on success.');
$assert(($call['overrides']['test_form'] ?? null) === false, 'The broker should pass test_form => false.');
$assert(($call['overrides']['mimes'] ?? null) === $allowedMimes, 'The broker should pass the exact caller MIME map.');
$assert(($call['uploads']['path'] ?? '') === dirname($expectedDestination), 'The scoped upload_dir path should target the selected private bucket.');
$assert(($call['uploads']['basedir'] ?? '') === dirname($expectedDestination), 'The scoped upload_dir basedir should target the selected private bucket.');
$assert(($call['uploads']['subdir'] ?? null) === '', 'The scoped upload_dir subdir should be empty.');
$assert(($call['uploads']['url'] ?? null) === '', 'The scoped upload_dir url should be blank.');
$assert(($call['uploads']['baseurl'] ?? null) === '', 'The scoped upload_dir baseurl should be blank.');
$assert(($call['uploads']['error'] ?? null) === false, 'The scoped upload_dir error should be false.');
$assert(($call['upload_dir_filter_count'] ?? 0) === 1, 'The broker should scope exactly one upload_dir filter during the upload call.');
$assert(($call['filename'] ?? '') === 'uuid-test.pdf', 'The deterministic destination basename should remain UUID-based.');
$assert(($call['file']['name'] ?? '') === 'Verification-Proof.pdf', 'The broker should preserve the sanitized display filename for wp_handle_upload().');
$assert(file_exists($expectedDestination), 'The broker should store the file at the expected private destination.');
$assert(sprintf('%o', fileperms($expectedDestination) & 0777) === '640', 'The broker should preserve the 0640 permission behavior.');
$insert = $GLOBALS['vms_test_calls']['wpdb_insert'][0] ?? null;
$assert(is_array($insert), 'The broker should register the stored file.');
$assert(($insert['data']['stored_filename'] ?? '') === 'verifications/uuid-test.pdf', 'The broker should preserve the generated storage key.');
$assert(($insert['data']['original_filename'] ?? '') === 'Verification-Proof.pdf', 'The broker should preserve the original display filename metadata.');
$assert(($insert['data']['mime_type'] ?? '') === 'application/pdf', 'The broker should preserve the detected MIME metadata.');
$assert((int) ($insert['data']['related_post_id'] ?? 0) === 45, 'The broker should preserve related-post metadata.');
vms_test_assert_filter_cycle('Successful uploads');
vms_test_recursive_delete((string) $GLOBALS['vms_test_case']['temp_root']);

vms_test_reset_case('error');
$errorResult = vms_private_files_store_validated_upload(
	vms_test_validated_upload(),
	array(
		'allowed_mimes' => vms_test_allowed_mimes(),
		'bucket' => 'verifications',
	)
);
$assert(is_wp_error($errorResult), 'API errors should return a WP_Error.');
$assert($errorResult->get_error_code() === 'private_upload_move_failed', 'API errors should map to private_upload_move_failed.');
$assert($errorResult->get_error_message() === 'Could not store the uploaded file.', 'API errors should preserve the public error message.');
vms_test_assert_filter_cycle('API errors');
vms_test_recursive_delete((string) $GLOBALS['vms_test_case']['temp_root']);

vms_test_reset_case('unexpected_inside');
$insideMismatchPath = (string) $GLOBALS['vms_test_case']['unexpected_path'];
$insideResult = vms_private_files_store_validated_upload(
	vms_test_validated_upload(),
	array(
		'allowed_mimes' => vms_test_allowed_mimes(),
		'bucket' => 'verifications',
	)
);
$assert(is_wp_error($insideResult) && $insideResult->get_error_code() === 'private_upload_move_failed', 'Inside-root path mismatches should map to private_upload_move_failed.');
$assert(!file_exists($insideMismatchPath), 'Inside-root unexpected files should be deleted.');
vms_test_assert_filter_cycle('Inside-root path mismatches');
vms_test_recursive_delete((string) $GLOBALS['vms_test_case']['temp_root']);

vms_test_reset_case('unexpected_outside');
$outsideMismatchPath = (string) $GLOBALS['vms_test_case']['unexpected_path'];
$outsideResult = vms_private_files_store_validated_upload(
	vms_test_validated_upload(),
	array(
		'allowed_mimes' => vms_test_allowed_mimes(),
		'bucket' => 'verifications',
	)
);
$assert(is_wp_error($outsideResult) && $outsideResult->get_error_code() === 'private_upload_move_failed', 'Outside-root path mismatches should map to private_upload_move_failed.');
$assert(file_exists($outsideMismatchPath), 'Outside-root unexpected files should not be deleted.');
vms_test_assert_filter_cycle('Outside-root path mismatches');
vms_test_recursive_delete((string) $GLOBALS['vms_test_case']['temp_root']);

vms_test_reset_case('throw');
$thrown = false;
try {
	vms_private_files_store_validated_upload(
		vms_test_validated_upload(),
		array(
			'allowed_mimes' => vms_test_allowed_mimes(),
			'bucket' => 'verifications',
		)
	);
} catch (VmsPrivateFilesUploadApiException $exception) {
	$thrown = $exception->getMessage() === 'Simulated wp_handle_upload exception.';
}
$assert($thrown, 'Thrown wp_handle_upload() exceptions should bubble to the harness.');
vms_test_assert_filter_cycle('Thrown exceptions');
vms_test_recursive_delete((string) $GLOBALS['vms_test_case']['temp_root']);

vms_test_reset_case('success');
$GLOBALS['vms_test_case']['wpdb_insert_fail'] = true;
$registerFailureDestination = (string) $GLOBALS['vms_test_case']['expected_destination'];
$registerFailure = vms_private_files_store_validated_upload(
	vms_test_validated_upload(),
	array(
		'allowed_mimes' => vms_test_allowed_mimes(),
		'bucket' => 'verifications',
	)
);
$assert(is_wp_error($registerFailure), 'Registration failures should return a WP_Error.');
$assert($registerFailure->get_error_code() === 'private_upload_register_failed', 'Registration failures should preserve the private_upload_register_failed code.');
$assert(!file_exists($registerFailureDestination), 'Registration failures should unlink the intended destination.');
vms_test_assert_filter_cycle('Registration failures');
vms_test_recursive_delete((string) $GLOBALS['vms_test_case']['temp_root']);

vms_test_reset_case('success');
$missingAllowed = vms_private_files_store_validated_upload(
	vms_test_validated_upload(),
	array(
		'bucket' => 'verifications',
	)
);
$assert(is_wp_error($missingAllowed), 'Missing MIME maps should return a WP_Error.');
$assert($missingAllowed->get_error_code() === 'private_storage_invalid', 'Missing MIME maps should fail through private_storage_invalid.');
$assert(empty($GLOBALS['vms_test_calls']['wp_handle_upload']), 'Missing MIME maps should fail before wp_handle_upload().');
$assert(empty($GLOBALS['vms_test_filter_events']), 'Missing MIME maps should fail before registering upload_dir filters.');
vms_test_recursive_delete((string) $GLOBALS['vms_test_case']['temp_root']);

fwrite(STDOUT, "private-file-upload-api-remediation: OK\n");
