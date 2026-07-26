<?php
declare(strict_types=1);

$repoRoot = dirname(__DIR__);

$runtimeFiles = array(
	'private_files' => $repoRoot . '/includes/core/private-files.php',
	'safety_private_files' => $repoRoot . '/includes/safety/private-files.php',
	'ticketing_verifications' => $repoRoot . '/includes/integrations/ticketing-verifications.php',
	'image_normalization' => $repoRoot . '/includes/helpers/image-normalization.php',
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

$readPatternCount = static function (string $pattern, string $source): int {
	$result = preg_match_all($pattern, $source, $matches);
	if ($result === false) {
		throw new RuntimeException('Regex failed for pattern ' . $pattern);
	}

	return $result;
};

$privateFilesSource = $sources['private_files'];
$safetySource = $sources['safety_private_files'];
$ticketingSource = $sources['ticketing_verifications'];
$imageSource = $sources['image_normalization'];

$streamFunction = $extractFunction($privateFilesSource, 'vms_private_files_stream_path');
$storeFunction = $extractFunction($privateFilesSource, 'vms_private_files_store_validated_upload');
$deleteFunction = $extractFunction($privateFilesSource, 'vms_private_files_delete');
$pathSafeFunction = $extractFunction($privateFilesSource, 'vms_private_files_path_is_safe');
$w9DownloadFunction = $extractFunction($privateFilesSource, 'vms_private_w9_download_handler');
$staffDownloadFunction = $extractFunction($privateFilesSource, 'vms_private_staff_cert_download_handler');
$safetyDownloadFunction = $extractFunction($safetySource, 'vms_safety_private_file_download_handler');
$ticketingPathFunction = $extractFunction($ticketingSource, 'vms_ticketing_verification_path_within_root');
$ticketingDeleteFunction = $extractFunction($ticketingSource, 'vms_ticketing_verification_delete_proof_file');
$ticketingStoreFunction = $extractFunction($ticketingSource, 'vms_ticketing_verification_store_proof_file');
$ticketingStreamFunction = $extractFunction($ticketingSource, 'vms_ticketing_verification_stream_proof');
$imageNormalizeFunction = $extractFunction($imageSource, 'vms_normalize_uploaded_image_to_jpeg');

$allSource = implode("\n", $sources);
$assert(substr_count($allSource, 'phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile') === 2, 'F3 should contain exactly two readfile suppressions.');
$assert(substr_count($allSource, 'phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod') === 2, 'F3 should contain exactly two chmod suppressions.');
$assert(strpos($allSource, 'phpcs:disable') === false, 'F3 should not introduce phpcs:disable.');
$assert(strpos($allSource, 'phpcs:enable') === false, 'F3 should not introduce phpcs:enable.');

$assert(substr_count($allSource, 'wp_delete_file(') === 6, 'F3 should use wp_delete_file() exactly six times.');
$assert(substr_count($allSource, 'wp_is_writable(') === 2, 'F3 should use wp_is_writable() exactly twice.');
$assert($readPatternCount('/(^|[^[:alnum:]_])@unlink\(/m', $allSource) === 0, 'F3 should not retain native @unlink() in runtime files.');
$assert($readPatternCount('/(?<!wp_)is_writable\(/', $allSource) === 0, 'F3 should not retain bare is_writable() in runtime files.');

$assert(strpos($streamFunction, 'nocache_headers();') !== false, 'The shared stream helper should preserve nocache_headers().');
$assert(strpos($streamFunction, "header('Content-Type: ' . \$mime);") !== false, 'The shared stream helper should set Content-Type.');
$assert(strpos($streamFunction, "header('Content-Disposition: attachment; filename=\"' . \$filename . '\"');") !== false, 'The shared stream helper should set Content-Disposition.');
$assert(strpos($streamFunction, "header('X-Content-Type-Options: nosniff');") !== false, 'The shared stream helper should set nosniff.');
$assert(strpos($streamFunction, "header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');") !== false, 'The shared stream helper should preserve private cache-control.');
$assert(strpos($streamFunction, "readfile(\$path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile") !== false, 'The shared stream helper should retain a line-specific readfile suppression.');
$assert(strpos($streamFunction, 'exit;') !== false, 'The shared stream helper should terminate immediately after streaming.');
$assert(strpos($streamFunction, 'ob_end_clean') === false, 'The shared stream helper should not introduce output-buffer cleanup churn.');
$assert(strpos($streamFunction, 'headers_sent(') === false, 'The shared stream helper should not introduce headers_sent() branching.');

$assert(strpos($pathSafeFunction, '$real_base = realpath($base_dir);') !== false, 'The private-file path guard should canonicalize the base directory.');
$assert(strpos($pathSafeFunction, '$real_path = realpath($path);') !== false, 'The private-file path guard should canonicalize candidate paths.');
$assert(strpos($pathSafeFunction, 'return strpos($normalized_path, $normalized_base) === 0;') !== false, 'The private-file path guard should enforce the normalized base prefix.');

$assert(strpos($storeFunction, 'wp_handle_upload(') !== false, 'The broker should still use wp_handle_upload().');
$assert(strpos($storeFunction, 'wp_delete_file($handled_file);') !== false, 'The broker should use wp_delete_file() for handled-file rollback.');
$assert(strpos($storeFunction, '@chmod($destination, 0640); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod') !== false, 'The broker should retain only a line-specific chmod suppression.');
$assert(strpos($storeFunction, 'wp_delete_file($destination);') !== false, 'The broker should use wp_delete_file() for register rollback.');

$assert(strpos($deleteFunction, 'vms_private_files_path_is_safe($path)') !== false, 'The shared private-file delete path should preserve its safe-path guard.');
$assert(strpos($deleteFunction, 'wp_delete_file($path);') !== false, 'The shared private-file delete path should use wp_delete_file().');

$assert(strpos($w9DownloadFunction, "check_admin_referer('vms_private_w9_download_' . \$post_id);") !== false, 'The W-9 download handler should retain nonce verification.');
$assert(strpos($w9DownloadFunction, '!vms_private_w9_user_can_download($post_id)') !== false, 'The W-9 download handler should retain authorization.');
$assert(strpos($w9DownloadFunction, 'vms_private_w9_file_payload($post_id)') !== false, 'The W-9 download handler should still resolve through the payload helper.');
$assert(strpos($w9DownloadFunction, 'vms_private_files_stream_path(') !== false, 'The W-9 download handler should still use the shared stream helper.');

$assert(strpos($staffDownloadFunction, "check_admin_referer('vms_private_staff_cert_download_' . \$staff_id . '_' . \$qualification_id);") !== false, 'The staff certification download handler should retain nonce verification.');
$assert(strpos($staffDownloadFunction, '!vms_private_staff_cert_user_can_download($staff_id)') !== false, 'The staff certification download handler should retain authorization.');
$assert(strpos($staffDownloadFunction, 'vms_private_staff_cert_file_payload($staff_id, $match)') !== false, 'The staff certification download handler should still resolve through the payload helper.');
$assert(strpos($staffDownloadFunction, 'vms_private_files_stream_path(') !== false, 'The staff certification download handler should still use the shared stream helper.');

$assert(strpos($safetyDownloadFunction, 'current_user_can(vms_safety_view_capability())') !== false, 'The safety download handler should retain its capability gate.');
$assert(strpos($safetyDownloadFunction, "check_admin_referer('vms_private_file_download_' . \$file_id);") !== false, 'The safety download handler should retain nonce verification.');
$assert(strpos($safetyDownloadFunction, "vms_safety_audit_log('doc_downloaded'") !== false, 'The safety download handler should retain audit logging.');
$assert(strpos($safetyDownloadFunction, 'vms_private_files_stream_path($path, $name, $mime);') !== false, 'The safety download handler should prefer the shared stream helper.');
$assert(strpos($safetyDownloadFunction, "readfile(\$path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile") !== false, 'The safety fallback stream should use a line-specific readfile suppression.');
$assert(strpos($safetyDownloadFunction, 'exit;') !== false, 'The safety download handler should terminate after streaming.');

$assert(strpos($ticketingPathFunction, '$current_root = vms_ticketing_verification_upload_root();') !== false, 'The verification path guard should include the current private root.');
$assert(strpos($ticketingPathFunction, "trailingslashit(\$base) . 'vms-verification-proofs'") !== false, 'The verification path guard should preserve the legacy proof root.');
$assert(strpos($ticketingPathFunction, '$real_path = realpath($path);') !== false, 'The verification path guard should canonicalize candidate paths.');
$assert(strpos($ticketingPathFunction, 'strpos($real_path, trailingslashit($real_root)) === 0 || $real_path === $real_root') !== false, 'The verification path guard should enforce root prefix matching.');

$assert(strpos($ticketingDeleteFunction, '!vms_ticketing_verification_path_within_root($path)') !== false, 'Verification proof cleanup should retain root enforcement.');
$assert(strpos($ticketingDeleteFunction, 'wp_delete_file($path);') !== false, 'Verification proof cleanup should use wp_delete_file().');

$assert(strpos($ticketingStoreFunction, '!wp_is_writable($root)') !== false, 'Verification proof storage should use wp_is_writable() for the root check.');
$assert(strpos($ticketingStoreFunction, 'vms_ticketing_verification_optimize_image_upload(') !== false, 'Verification proof storage should preserve image normalization.');
$assert(strpos($ticketingStoreFunction, 'vms_private_files_register_path(') !== false, 'Verification proof storage should preserve private-file registration.');
$assert(strpos($ticketingStoreFunction, 'wp_delete_file($stored_path);') !== false, 'Verification proof storage should use wp_delete_file() for failed registration cleanup.');

$assert(strpos($ticketingStreamFunction, '!vms_ticketing_verification_current_user_can_manage()') !== false, 'Verification proof streaming should retain its capability gate.');
$assert(strpos($ticketingStreamFunction, "!wp_verify_nonce(\$nonce, 'vms_verification_proof_' . \$request_id)") !== false, 'Verification proof streaming should retain nonce verification.');
$assert(strpos($ticketingStreamFunction, 'vms_ticketing_verification_proof_payload($request_id)') !== false, 'Verification proof streaming should still resolve through the payload helper.');
$assert(strpos($ticketingStreamFunction, 'vms_private_files_stream_path(') !== false, 'Verification proof streaming should still use the shared stream helper.');

$assert(strpos($imageNormalizeFunction, '!wp_is_writable($target_dir)') !== false, 'Image normalization should use wp_is_writable() for the target directory.');
$assert(strpos($imageNormalizeFunction, '@chmod($saved_path, 0640); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod') !== false, 'Image normalization should retain only a line-specific chmod suppression.');
$assert(strpos($imageNormalizeFunction, 'wp_delete_file($saved_path);') !== false, 'Image normalization should use wp_delete_file() for oversize cleanup.');
$assert(strpos($imageNormalizeFunction, "return new WP_Error('file_too_large'") !== false, 'Image normalization should still fail closed on oversized normalized output.');

fwrite(STDOUT, "private-file-operations-boundary-remediation: OK\n");
