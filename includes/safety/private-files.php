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

if (!function_exists('vms_safety_store_private_upload')) {
	/**
	 * @param array<string,mixed> $upload
	 * @param array<string,mixed> $context
	 * @return int|WP_Error
	 */
	function vms_safety_store_private_upload(array $upload, array $context = array())
	{
		if (empty($upload['tmp_name']) || empty($upload['name'])) {
			return new WP_Error('vms_safety_upload_missing', __('Missing upload payload.', 'vms'));
		}
		if (!empty($upload['error']) && (int) $upload['error'] !== UPLOAD_ERR_OK) {
			return new WP_Error('vms_safety_upload_error', __('Upload failed.', 'vms'));
		}
		if (!is_uploaded_file((string) $upload['tmp_name'])) {
			return new WP_Error('vms_safety_upload_invalid', __('Invalid upload source.', 'vms'));
		}
		if (!vms_safety_private_files_ensure_dir()) {
			return new WP_Error('vms_safety_upload_dir', __('Could not create private upload directory.', 'vms'));
		}

		$filename = sanitize_file_name((string) $upload['name']);
		if ($filename === '') {
			$filename = 'file';
		}
		$ext = pathinfo($filename, PATHINFO_EXTENSION);
		$stored = wp_generate_uuid4();
		if (is_string($ext) && $ext !== '') {
			$stored .= '.' . strtolower($ext);
		}
		$path = vms_safety_private_file_path($stored);

		if (!@move_uploaded_file((string) $upload['tmp_name'], $path)) {
			return new WP_Error('vms_safety_upload_move', __('Could not store uploaded file.', 'vms'));
		}

		$mime = wp_check_filetype($filename);
		$mime_type = !empty($mime['type']) ? (string) $mime['type'] : 'application/octet-stream';
		$file_size = file_exists($path) ? (int) filesize($path) : 0;
		$hash = file_exists($path) ? hash_file('sha256', $path) : '';

		global $wpdb;
		$table = vms_safety_private_files_table();
		$inserted = $wpdb->insert(
			$table,
			array(
				'original_filename' => $filename,
				'stored_filename' => $stored,
				'mime_type' => $mime_type,
				'file_size' => max(0, $file_size),
				'sha256' => is_string($hash) ? $hash : '',
				'created_at' => current_time('mysql'),
				'created_by' => get_current_user_id(),
				'related_post_type' => isset($context['related_post_type']) ? sanitize_key((string) $context['related_post_type']) : null,
				'related_post_id' => isset($context['related_post_id']) ? absint($context['related_post_id']) : null,
			),
			array('%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%d')
		);
		if (!$inserted) {
			@unlink($path);
			return new WP_Error('vms_safety_upload_db', __('Could not register uploaded file.', 'vms'));
		}

		$file_id = (int) $wpdb->insert_id;
		vms_safety_audit_log('doc_uploaded', array('file_id' => $file_id, 'filename' => $filename));
		return $file_id;
	}
}

if (!function_exists('vms_safety_private_file_download_handler')) {
	function vms_safety_private_file_download_handler(): void
	{
		if (!current_user_can(vms_safety_view_capability())) {
			wp_die(esc_html__('You do not have permission to download this file.', 'vms'));
		}

		$file_id = isset($_GET['file_id']) ? absint($_GET['file_id']) : 0;
		if ($file_id <= 0) {
			wp_die(esc_html__('Invalid file id.', 'vms'));
		}
		check_admin_referer('vms_private_file_download_' . $file_id);

		$row = vms_safety_private_file_get($file_id);
		if (!$row) {
			wp_die(esc_html__('File not found.', 'vms'));
		}

		$path = vms_safety_private_file_path((string) $row['stored_filename']);
		if (!file_exists($path)) {
			wp_die(esc_html__('Stored file is missing.', 'vms'));
		}

		$size = (int) filesize($path);
		$name = (string) $row['original_filename'];
		$mime = (string) $row['mime_type'];

		vms_safety_audit_log('doc_downloaded', array('file_id' => $file_id, 'filename' => $name));

		nocache_headers();
		header('Content-Description: File Transfer');
		header('Content-Type: ' . $mime);
		header('Content-Disposition: attachment; filename="' . rawurlencode($name) . '"');
		header('Content-Length: ' . (string) $size);
		header('X-Content-Type-Options: nosniff');
		readfile($path);
		exit;
	}
}
add_action('admin_post_vms_private_file_download', 'vms_safety_private_file_download_handler');
