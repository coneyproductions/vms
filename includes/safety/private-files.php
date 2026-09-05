<?php

defined('ABSPATH') || exit;

if (!defined('VMS_SAFETY_PRIVATE_FILES_TABLE_SUFFIX')) {
	define('VMS_SAFETY_PRIVATE_FILES_TABLE_SUFFIX', 'vms_private_files');
}
if (!defined('VMS_SAFETY_PRIVATE_FILES_SCHEMA_VERSION')) {
	define('VMS_SAFETY_PRIVATE_FILES_SCHEMA_VERSION', '1');
}
if (!defined('VMS_SAFETY_PRIVATE_FILES_SCHEMA_OPTION')) {
	define('VMS_SAFETY_PRIVATE_FILES_SCHEMA_OPTION', 'vms_safety_private_files_schema_version');
}

if (!function_exists('vms_safety_private_files_table')) {
	function vms_safety_private_files_table(): string
	{
		global $wpdb;
		return $wpdb->prefix . VMS_SAFETY_PRIVATE_FILES_TABLE_SUFFIX;
	}
}

if (!function_exists('vms_safety_private_files_upload_dir')) {
	/**
	 * @return array{dir:string,url:string,base_dir:string,base_url:string}
	 */
	function vms_safety_private_files_upload_dir(): array
	{
		$uploads = wp_upload_dir();
		$base_dir = trailingslashit((string) ($uploads['basedir'] ?? ''));
		$base_url = trailingslashit((string) ($uploads['baseurl'] ?? ''));
		$dir = $base_dir . 'vms-private';
		$url = $base_url . 'vms-private';
		return array(
			'dir' => $dir,
			'url' => $url,
			'base_dir' => $base_dir,
			'base_url' => $base_url,
		);
	}
}

if (!function_exists('vms_safety_private_files_ensure_dir')) {
	function vms_safety_private_files_ensure_dir(): bool
	{
		$paths = vms_safety_private_files_upload_dir();
		$dir = $paths['dir'];
		if (!wp_mkdir_p($dir)) {
			return false;
		}

		$htaccess = $dir . '/.htaccess';
		if (!file_exists($htaccess)) {
			file_put_contents($htaccess, "Deny from all\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n");
		}
		$index = $dir . '/index.php';
		if (!file_exists($index)) {
			file_put_contents($index, "<?php\nhttp_response_code(403);\nexit;\n");
		}
		return true;
	}
}

if (!function_exists('vms_safety_private_files_install_schema')) {
	function vms_safety_private_files_install_schema(): void
	{
		$installed = (string) get_option(VMS_SAFETY_PRIVATE_FILES_SCHEMA_OPTION, '');
		if ($installed === VMS_SAFETY_PRIVATE_FILES_SCHEMA_VERSION) {
			vms_safety_private_files_ensure_dir();
			return;
		}

		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = vms_safety_private_files_table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			original_filename VARCHAR(255) NOT NULL,
			stored_filename VARCHAR(255) NOT NULL,
			mime_type VARCHAR(191) NOT NULL,
			file_size BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			sha256 CHAR(64) NOT NULL,
			created_at DATETIME NOT NULL,
			created_by BIGINT(20) UNSIGNED NULL,
			related_post_type VARCHAR(64) NULL,
			related_post_id BIGINT(20) UNSIGNED NULL,
			PRIMARY KEY  (id),
			KEY related_post (related_post_type, related_post_id),
			KEY created_at (created_at)
		) {$charset};";

		dbDelta($sql);
		vms_safety_private_files_ensure_dir();
		update_option(VMS_SAFETY_PRIVATE_FILES_SCHEMA_OPTION, VMS_SAFETY_PRIVATE_FILES_SCHEMA_VERSION, false);
	}
}
add_action('init', 'vms_safety_private_files_install_schema', 35);

if (!function_exists('vms_safety_private_file_get')) {
	/**
	 * @return array<string,mixed>|null
	 */
	function vms_safety_private_file_get(int $file_id): ?array
	{
		if ($file_id <= 0) {
			return null;
		}

		global $wpdb;
		$table = vms_safety_private_files_table();
		$row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $file_id), ARRAY_A);
		if (!is_array($row)) {
			return null;
		}
		return $row;
	}
}

if (!function_exists('vms_safety_private_file_path')) {
	function vms_safety_private_file_path(string $stored_filename): string
	{
		$paths = vms_safety_private_files_upload_dir();
		return trailingslashit($paths['dir']) . ltrim($stored_filename, '/');
	}
}

if (!function_exists('vms_safety_private_file_download_url')) {
	function vms_safety_private_file_download_url(int $file_id): string
	{
		return wp_nonce_url(
			add_query_arg(array(
				'action' => 'vms_private_file_download',
				'file_id' => $file_id,
			), admin_url('admin-post.php')),
			'vms_private_file_download_' . $file_id
		);
	}
}

if (!function_exists('vms_safety_private_allowed_mimes')) {
	/**
	 * @return array<string,string|array<int,string>>
	 */
	function vms_safety_private_allowed_mimes(): array
	{
		return array(
			'pdf' => 'application/pdf',
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png' => 'image/png',
			'webp' => 'image/webp',
			'txt' => 'text/plain',
			'csv' => array('text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'),
			'doc' => 'application/msword',
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'xls' => 'application/vnd.ms-excel',
			'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		);
	}
}

if (!function_exists('vms_safety_private_max_bytes')) {
	function vms_safety_private_max_bytes(): int
	{
		$configured = 20 * 1024 * 1024;
		$wp_limit = (int) wp_max_upload_size();
		if ($wp_limit > 0) {
			return max(1, min($configured, $wp_limit));
		}

		return $configured;
	}
}

if (!function_exists('vms_safety_store_private_upload')) {
	/**
	 * @param array<string,mixed> $upload
	 * @param array<string,mixed> $context
	 * @return int|WP_Error
	 */
	function vms_safety_store_private_upload(array $upload, array $context = array())
	{
		if (!function_exists('vms_validate_uploaded_file') || !function_exists('vms_private_files_store_validated_upload')) {
			return new WP_Error('vms_safety_upload_unavailable', __('The private upload handler is unavailable.', 'backstage-venue-manager'));
		}

		$validated = vms_validate_uploaded_file(
			$upload,
			array(
				'allowed_mimes' => vms_safety_private_allowed_mimes(),
				'max_bytes' => vms_safety_private_max_bytes(),
				'type_message' => __('Please upload a PDF, image, spreadsheet, text file, or Office document.', 'backstage-venue-manager'),
				'empty_message' => __('The uploaded file is empty.', 'backstage-venue-manager'),
				'too_large_message' => __('The uploaded file is too large.', 'backstage-venue-manager'),
				'tmp_invalid_message' => __('The uploaded file could not be verified.', 'backstage-venue-manager'),
			)
		);
		if (is_wp_error($validated)) {
			return $validated;
		}

		if (!vms_safety_private_files_ensure_dir()) {
			return new WP_Error('vms_safety_upload_dir', __('Could not create private upload directory.', 'backstage-venue-manager'));
		}

		$file_id = vms_private_files_store_validated_upload(
			$validated,
			array(
				'bucket' => 'safety',
				'related_post_type' => isset($context['related_post_type']) ? sanitize_key((string) $context['related_post_type']) : null,
				'related_post_id' => isset($context['related_post_id']) ? absint($context['related_post_id']) : null,
			)
		);
		if (is_wp_error($file_id)) {
			return $file_id;
		}

		vms_safety_audit_log('doc_uploaded', array('file_id' => (int) $file_id, 'filename' => (string) ($validated['sanitized_name'] ?? 'file')));
		return (int) $file_id;
	}
}

if (!function_exists('vms_safety_private_file_download_handler')) {
	function vms_safety_private_file_download_handler(): void
	{
		if (!current_user_can(vms_safety_view_capability())) {
			wp_die(esc_html__('You do not have permission to download this file.', 'backstage-venue-manager'));
		}

		$file_id = isset($_GET['file_id']) ? absint($_GET['file_id']) : 0;
		if ($file_id <= 0) {
			wp_die(esc_html__('Invalid file id.', 'backstage-venue-manager'));
		}
		check_admin_referer('vms_private_file_download_' . $file_id);

		$row = vms_safety_private_file_get($file_id);
		if (!$row) {
			wp_die(esc_html__('File not found.', 'backstage-venue-manager'));
		}

		$path = vms_safety_private_file_path((string) $row['stored_filename']);
		if (!file_exists($path)) {
			wp_die(esc_html__('Stored file is missing.', 'backstage-venue-manager'));
		}

		$size = (int) filesize($path);
		$name = (string) $row['original_filename'];
		$mime = (string) $row['mime_type'];

		vms_safety_audit_log('doc_downloaded', array('file_id' => $file_id, 'filename' => $name));

		if (function_exists('vms_private_files_stream_path')) {
			vms_private_files_stream_path($path, $name, $mime);
		}

		nocache_headers();
		header('Content-Type: ' . $mime);
		header('Content-Disposition: attachment; filename="' . rawurlencode($name) . '"');
		header('Content-Length: ' . (string) $size);
		header('X-Content-Type-Options: nosniff');
		readfile($path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Fallback stream for the nonce-gated safety private download when the shared private-file stream helper is unavailable; the path comes from brokered private storage and WordPress has no equivalent streamed-response API.
		exit;
	}
}
add_action('admin_post_vms_private_file_download', 'vms_safety_private_file_download_handler');
