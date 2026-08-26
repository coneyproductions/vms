<?php
defined('ABSPATH') || exit;

if (!defined('BVMGR_PRIVATE_FILES_TABLE_SUFFIX')) {
	define('BVMGR_PRIVATE_FILES_TABLE_SUFFIX', 'vms_private_files');
}
if (!defined('BVMGR_PRIVATE_FILES_SCHEMA_VERSION')) {
	define('BVMGR_PRIVATE_FILES_SCHEMA_VERSION', '1');
}
if (!defined('BVMGR_PRIVATE_FILES_SCHEMA_OPTION')) {
	define('BVMGR_PRIVATE_FILES_SCHEMA_OPTION', 'vms_private_files_schema_version');
}

if (!function_exists('vms_private_files_table')) {
	function vms_private_files_table(): string
	{
		global $wpdb;
		return $wpdb->prefix . BVMGR_PRIVATE_FILES_TABLE_SUFFIX;
	}
}

if (!function_exists('vms_private_files_upload_dir')) {
	/**
	 * @return array{dir:string,base_dir:string}
	 */
	function vms_private_files_upload_dir(): array
	{
		$uploads = wp_upload_dir(null, false);
		$base_dir = isset($uploads['basedir']) ? trim((string) $uploads['basedir']) : '';
		$dir = $base_dir !== '' ? trailingslashit($base_dir) . 'vms-private' : '';

		return array(
			'dir' => $dir,
			'base_dir' => $base_dir,
		);
	}
}

if (!function_exists('vms_private_files_write_hardening_files')) {
	function vms_private_files_write_hardening_files(string $dir): void
	{
		$dir = trim($dir);
		if ($dir === '' || !is_dir($dir)) {
			return;
		}

		$index = trailingslashit($dir) . 'index.php';
		if (!file_exists($index)) {
			@file_put_contents($index, "<?php\nhttp_response_code(403);\nexit;\n");
		}

		$htaccess = trailingslashit($dir) . '.htaccess';
		if (!file_exists($htaccess)) {
			@file_put_contents($htaccess, "Deny from all\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n");
		}

		$webconfig = trailingslashit($dir) . 'web.config';
		if (!file_exists($webconfig)) {
			@file_put_contents($webconfig, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n");
		}
	}
}

if (!function_exists('vms_private_files_ensure_dir')) {
	function vms_private_files_ensure_dir(string $bucket = ''): bool
	{
		$paths = vms_private_files_upload_dir();
		$base_dir = $paths['dir'];
		if ($base_dir === '') {
			return false;
		}

		if (!wp_mkdir_p($base_dir)) {
			return false;
		}
		vms_private_files_write_hardening_files($base_dir);

		$bucket = sanitize_key($bucket);
		if ($bucket === '') {
			return true;
		}

		$bucket_dir = trailingslashit($base_dir) . $bucket;
		if (!wp_mkdir_p($bucket_dir)) {
			return false;
		}
		vms_private_files_write_hardening_files($bucket_dir);

		return true;
	}
}

if (!function_exists('vms_private_files_bucket_dir')) {
	function vms_private_files_bucket_dir(string $bucket): string
	{
		$paths = vms_private_files_upload_dir();
		$base_dir = $paths['dir'];
		$bucket = sanitize_key($bucket);
		if ($base_dir === '' || $bucket === '') {
			return '';
		}

		return trailingslashit($base_dir) . $bucket;
	}
}

if (!function_exists('vms_private_files_validate_storage_key')) {
	function vms_private_files_validate_storage_key(string $storage_key): string
	{
		$storage_key = trim(str_replace('\\', '/', $storage_key));
		if ($storage_key === '' || strpos($storage_key, "\0") !== false) {
			return '';
		}
		if ($storage_key[0] === '/' || strpos($storage_key, ':') !== false || strpos($storage_key, '../') !== false || strpos($storage_key, '/..') !== false) {
			return '';
		}

		$parts = array_filter(explode('/', $storage_key), static function ($part): bool {
			return $part !== '';
		});
		if (empty($parts)) {
			return '';
		}

		$clean = array();
		foreach ($parts as $part) {
			$part = trim((string) $part);
			if ($part === '' || $part === '.' || $part === '..') {
				return '';
			}
			if ($part !== sanitize_file_name($part)) {
				return '';
			}
			$clean[] = $part;
		}

		return implode('/', $clean);
	}
}

if (!function_exists('vms_private_files_generate_storage_key')) {
	function vms_private_files_generate_storage_key(string $bucket, string $extension = ''): string
	{
		$bucket = sanitize_key($bucket);
		if ($bucket === '') {
			$bucket = 'general';
		}

		$filename = wp_generate_uuid4();
		$extension = sanitize_key(ltrim($extension, '.'));
		if ($extension !== '') {
			$filename .= '.' . $extension;
		}

		return $bucket . '/' . $filename;
	}
}

if (!function_exists('vms_private_files_absolute_path')) {
	function vms_private_files_absolute_path(string $storage_key): string
	{
		$storage_key = vms_private_files_validate_storage_key($storage_key);
		if ($storage_key === '') {
			return '';
		}

		$paths = vms_private_files_upload_dir();
		$base_dir = $paths['dir'];
		if ($base_dir === '') {
			return '';
		}

		return trailingslashit($base_dir) . $storage_key;
	}
}

if (!function_exists('vms_private_files_path_is_safe')) {
	function vms_private_files_path_is_safe(string $path): bool
	{
		$path = trim($path);
		if ($path === '') {
			return false;
		}

		$paths = vms_private_files_upload_dir();
		$base_dir = $paths['dir'];
		if ($base_dir === '') {
			return false;
		}

		$real_base = realpath($base_dir);
		$real_path = realpath($path);
		if ($real_base === false || $real_path === false) {
			return false;
		}

		$normalized_base = trailingslashit(wp_normalize_path($real_base));
		$normalized_path = wp_normalize_path($real_path);
		if ($normalized_base === '' || $normalized_path === '') {
			return false;
		}

		return strpos($normalized_path, $normalized_base) === 0;
	}
}

if (!function_exists('vms_private_files_normalize_allowed_mimes')) {
	/**
	 * @param array<string,mixed> $allowed_mimes
	 * @return array<string,string|array<int,string>>
	 */
	function vms_private_files_normalize_allowed_mimes(array $allowed_mimes): array
	{
		$normalized = array();

		foreach ($allowed_mimes as $extensions => $raw_mimes) {
			$parts = array_map(
				static function ($part): string {
					return sanitize_key(trim((string) $part));
				},
				explode('|', (string) $extensions)
			);
			$parts = array_values(array_unique(array_filter($parts, static function ($part): bool {
				return $part !== '';
			})));
			$extension = implode('|', $parts);
			if ($extension === '') {
				continue;
			}

			if (is_array($raw_mimes)) {
				$clean_mimes = array();
				foreach ($raw_mimes as $raw_mime) {
					$mime = sanitize_text_field((string) $raw_mime);
					if ($mime !== '') {
						$clean_mimes[] = $mime;
					}
				}

				$clean_mimes = array_values(array_unique($clean_mimes));
				if (!empty($clean_mimes)) {
					$normalized[$extension] = $clean_mimes;
				}

				continue;
			}

			$mime = sanitize_text_field((string) $raw_mimes);
			if ($mime !== '') {
				$normalized[$extension] = $mime;
			}
		}

		return $normalized;
	}
}

if (!function_exists('vms_private_files_filter_upload_dir')) {
	/**
	 * @param array<string,mixed> $uploads
	 * @return array<string,mixed>
	 */
	function vms_private_files_filter_upload_dir(array $uploads): array
	{
		$context = isset($GLOBALS['bvmgr_private_files_upload_dir_context']) && is_array($GLOBALS['bvmgr_private_files_upload_dir_context'])
			? $GLOBALS['bvmgr_private_files_upload_dir_context']
			: array();
		$path = isset($context['path']) ? trim((string) $context['path']) : '';
		if ($path === '') {
			return $uploads;
		}

		$uploads['path'] = $path;
		$uploads['basedir'] = $path;
		$uploads['subdir'] = '';
		$uploads['url'] = '';
		$uploads['baseurl'] = '';
		$uploads['error'] = false;

		return $uploads;
	}
}

if (!function_exists('vms_private_files_with_scoped_upload_dir')) {
	/**
	 * @param array<string,mixed> $context
	 * @return mixed
	 */
	function vms_private_files_with_scoped_upload_dir(array $context, callable $callback)
	{
		$GLOBALS['bvmgr_private_files_upload_dir_context'] = $context;
		add_filter('upload_dir', 'vms_private_files_filter_upload_dir');

		try {
			return $callback();
		} finally {
			remove_filter('upload_dir', 'vms_private_files_filter_upload_dir');
			unset($GLOBALS['bvmgr_private_files_upload_dir_context']);
		}
	}
}

if (!function_exists('vms_private_files_install_schema')) {
	function vms_private_files_install_schema(): void
	{
		$installed = (string) get_option(BVMGR_PRIVATE_FILES_SCHEMA_OPTION, '');
		if ($installed === BVMGR_PRIVATE_FILES_SCHEMA_VERSION) {
			vms_private_files_ensure_dir();
			return;
		}

		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = vms_private_files_table();
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
		vms_private_files_ensure_dir();
		update_option(BVMGR_PRIVATE_FILES_SCHEMA_OPTION, BVMGR_PRIVATE_FILES_SCHEMA_VERSION, false);
	}
}
add_action('init', 'vms_private_files_install_schema', 34);

if (!function_exists('vms_private_file_get')) {
	/**
	 * @return array<string,mixed>|null
	 */
	function vms_private_file_get(int $file_id): ?array
	{
		if ($file_id <= 0) {
			return null;
		}

		global $wpdb;
		$table = vms_private_files_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Authorization-sensitive private-file reads must observe the current plugin-owned index row; stale cached metadata could expose a deleted or reassigned file.
		$row = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id = %d', $table, $file_id), ARRAY_A);
		return is_array($row) ? $row : null;
	}
}

if (!function_exists('vms_private_file_path')) {
	function vms_private_file_path(string $stored_filename): string
	{
		return vms_private_files_absolute_path($stored_filename);
	}
}

if (!function_exists('vms_private_files_safe_download_name')) {
	function vms_private_files_safe_download_name(string $filename, string $fallback_base = 'download'): string
	{
		$filename = sanitize_file_name($filename);
		if ($filename !== '') {
			return $filename;
		}

		$fallback_base = sanitize_file_name($fallback_base);
		return $fallback_base !== '' ? $fallback_base : 'download';
	}
}

if (!function_exists('vms_private_files_stream_path')) {
	function vms_private_files_stream_path(string $path, string $filename, string $mime): void
	{
		$path = trim($path);
		$filename = vms_private_files_safe_download_name($filename);
		$mime = trim($mime);
		if ($mime === '') {
			$mime = 'application/octet-stream';
		}
		if ($path === '' || !file_exists($path) || !is_file($path) || !is_readable($path)) {
			wp_die(esc_html__('Requested file is not available.', 'backstage-venue-manager'));
		}

		nocache_headers();
		header('Content-Type: ' . $mime);
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('X-Content-Type-Options: nosniff');
		header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
		header('Pragma: no-cache');

		$size = @filesize($path);
		if (is_numeric($size) && (int) $size >= 0) {
			header('Content-Length: ' . (string) (int) $size);
		}

			readfile($path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Stream the validated local private-file response without buffering; callers supply authorized, path-checked files and WordPress has no equivalent streamed-response API.
		exit;
	}
}

if (!function_exists('vms_private_files_attachment_payload')) {
	/**
	 * @return array<string,string>|WP_Error
	 */
	function vms_private_files_attachment_payload(int $attachment_id)
	{
		$attachment_id = absint($attachment_id);
		if ($attachment_id <= 0) {
			return new WP_Error('attachment_missing', __('Requested file is not available.', 'backstage-venue-manager'));
		}

		$path = (string) get_attached_file($attachment_id, true);
		if ($path === '' || !file_exists($path) || !is_file($path) || !is_readable($path)) {
			return new WP_Error('attachment_missing', __('Requested file is not available.', 'backstage-venue-manager'));
		}

		$mime = (string) get_post_mime_type($attachment_id);
		if ($mime === '') {
			$checked = wp_check_filetype(basename($path));
			$mime = !empty($checked['type']) ? (string) $checked['type'] : 'application/octet-stream';
		}

		$title = trim((string) get_the_title($attachment_id));
		$extension = pathinfo($path, PATHINFO_EXTENSION);
		$filename = sanitize_file_name($title);
		if ($filename === '') {
			$filename = sanitize_file_name(basename($path));
		} elseif (is_string($extension) && $extension !== '' && strtolower((string) pathinfo($filename, PATHINFO_EXTENSION)) !== strtolower($extension)) {
			$filename .= '.' . strtolower($extension);
		}

		return array(
			'path' => $path,
			'mime' => $mime,
			'filename' => vms_private_files_safe_download_name($filename !== '' ? $filename : basename($path)),
		);
	}
}

if (!function_exists('vms_private_files_register_path')) {
	/**
	 * @param array<string,mixed> $args
	 * @return int|WP_Error
	 */
	function vms_private_files_register_path(string $storage_key, string $path, string $original_filename, string $mime, array $args = array())
	{
		$storage_key = vms_private_files_validate_storage_key($storage_key);
		$path = trim($path);
		$mime = sanitize_text_field($mime);
		if ($storage_key === '' || $path === '' || $mime === '') {
			return new WP_Error('private_upload_register_failed', __('Could not register the uploaded file.', 'backstage-venue-manager'));
		}
		if (!file_exists($path) || !is_file($path) || !is_readable($path) || !vms_private_files_path_is_safe($path)) {
			return new WP_Error('private_upload_register_failed', __('Could not register the uploaded file.', 'backstage-venue-manager'));
		}

		$expected_path = vms_private_files_absolute_path($storage_key);
		$real_path = realpath($path);
		$real_expected = $expected_path !== '' ? realpath($expected_path) : false;
		if ($expected_path === '' || $real_path === false || $real_expected === false || wp_normalize_path($real_path) !== wp_normalize_path($real_expected)) {
			return new WP_Error('private_upload_register_failed', __('Could not register the uploaded file.', 'backstage-venue-manager'));
		}

		$actual_size = max(0, (int) @filesize($path));
		$hash = hash_file('sha256', $path);

		global $wpdb;
		$table = vms_private_files_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Private-file registration persists one validated metadata row in the plugin-owned index through wpdb::insert(); no core API owns this repository.
		$inserted = $wpdb->insert(
			$table,
			array(
				'original_filename' => vms_private_files_safe_download_name($original_filename !== '' ? $original_filename : basename($storage_key)),
				'stored_filename' => $storage_key,
				'mime_type' => $mime,
				'file_size' => $actual_size,
				'sha256' => is_string($hash) ? $hash : '',
				'created_at' => current_time('mysql'),
				'created_by' => isset($args['created_by']) ? absint($args['created_by']) : get_current_user_id(),
				'related_post_type' => isset($args['related_post_type']) ? sanitize_key((string) $args['related_post_type']) : null,
				'related_post_id' => isset($args['related_post_id']) ? absint($args['related_post_id']) : null,
			),
			array('%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%d')
		);
		if (!$inserted) {
			return new WP_Error('private_upload_register_failed', __('Could not register the uploaded file.', 'backstage-venue-manager'));
		}

		return (int) $wpdb->insert_id;
	}
}

if (!function_exists('vms_private_files_store_validated_upload')) {
	/**
	 * @param array<string,mixed> $validated_upload
	 * @param array<string,mixed> $args
	 * @return int|WP_Error
	 */
	function vms_private_files_store_validated_upload(array $validated_upload, array $args = array())
	{
		$bucket = isset($args['bucket']) ? sanitize_key((string) $args['bucket']) : 'general';
		if ($bucket === '') {
			$bucket = 'general';
		}
		$allowed_mimes = isset($args['allowed_mimes']) && is_array($args['allowed_mimes'])
			? vms_private_files_normalize_allowed_mimes($args['allowed_mimes'])
			: array();
		if (empty($allowed_mimes)) {
			return new WP_Error('private_storage_invalid', __('Could not prepare private file storage.', 'backstage-venue-manager'));
		}
		if (!vms_private_files_ensure_dir($bucket)) {
			return new WP_Error('private_dir_unavailable', __('Could not create the private upload directory.', 'backstage-venue-manager'));
		}

		$extension = isset($validated_upload['ext']) ? sanitize_key((string) $validated_upload['ext']) : '';
		$storage_key = vms_private_files_generate_storage_key($bucket, $extension);
		$destination = vms_private_files_absolute_path($storage_key);
		if ($destination === '') {
			return new WP_Error('private_storage_invalid', __('Could not prepare private file storage.', 'backstage-venue-manager'));
		}

		$destination_dir = dirname($destination);
		if (!is_dir($destination_dir) && !wp_mkdir_p($destination_dir)) {
			return new WP_Error('private_storage_invalid', __('Could not prepare private file storage.', 'backstage-venue-manager'));
		}
		vms_private_files_write_hardening_files($destination_dir);

		$tmp_name = isset($validated_upload['tmp_name']) ? trim((string) $validated_upload['tmp_name']) : '';
		if ($tmp_name === '') {
			return new WP_Error('private_upload_move_failed', __('Could not store the uploaded file.', 'backstage-venue-manager'));
		}

		if (!function_exists('wp_handle_upload')) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$upload_name = isset($validated_upload['sanitized_name']) ? trim((string) $validated_upload['sanitized_name']) : '';
		if ($upload_name === '') {
			$upload_name = isset($validated_upload['name']) ? trim((string) $validated_upload['name']) : '';
		}
		if ($upload_name === '') {
			$upload_name = 'upload';
		}

		$upload_for_handle = array(
			'name' => $upload_name,
			'type' => isset($validated_upload['reported_mime']) ? sanitize_text_field((string) $validated_upload['reported_mime']) : sanitize_text_field((string) ($validated_upload['mime'] ?? '')),
			'tmp_name' => $tmp_name,
			'error' => UPLOAD_ERR_OK,
			'size' => max(0, (int) ($validated_upload['size'] ?? ($validated_upload['reported_size'] ?? 0))),
		);
		$destination_basename = basename($destination);
		$handled = vms_private_files_with_scoped_upload_dir(
			array(
				'path' => $destination_dir,
			),
			static function () use (&$upload_for_handle, $allowed_mimes, $destination_basename): array {
				return wp_handle_upload(
					$upload_for_handle,
					array(
						'test_form' => false,
						'mimes' => $allowed_mimes,
						'unique_filename_callback' => static function (string $dir, string $name, string $ext) use ($destination_basename): string {
							unset($dir, $name, $ext);
							return $destination_basename;
						},
					)
				);
			}
		);

		$handled_file = is_array($handled) && isset($handled['file']) ? trim((string) $handled['file']) : '';
		$destination_real = realpath($destination);
		$handled_real = $handled_file !== '' ? realpath($handled_file) : false;
		$path_matches_destination = (
			is_string($destination_real)
			&& $destination_real !== ''
			&& is_string($handled_real)
			&& $handled_real !== ''
			&& wp_normalize_path($handled_real) === wp_normalize_path($destination_real)
		);
		if (!is_array($handled) || !empty($handled['error']) || !$path_matches_destination) {
			if (
				$handled_file !== ''
				&& file_exists($handled_file)
				&& is_file($handled_file)
				&& vms_private_files_path_is_safe($handled_file)
			) {
					wp_delete_file($handled_file);
				}

			return new WP_Error('private_upload_move_failed', __('Could not store the uploaded file.', 'backstage-venue-manager'));
		}
			@chmod($destination, 0640); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Preserve 0640 permissions on the validated private upload path so brokered files remain locally readable while denied to public web access; WP_Filesystem would add incompatible credential-driven semantics.

		$mime = isset($validated_upload['mime']) ? sanitize_text_field((string) $validated_upload['mime']) : 'application/octet-stream';
		if ($mime === '') {
			$mime = 'application/octet-stream';
		}

		$registered = vms_private_files_register_path(
			$storage_key,
			$destination,
			isset($validated_upload['sanitized_name']) ? (string) $validated_upload['sanitized_name'] : 'upload',
			$mime,
			$args
		);
		if (is_wp_error($registered)) {
				wp_delete_file($destination);
				return $registered;
		}

		return (int) $registered;
	}
}

if (!function_exists('vms_private_files_delete')) {
	function vms_private_files_delete(int $file_id): bool
	{
		$file_id = absint($file_id);
		if ($file_id <= 0) {
			return false;
		}

		$row = vms_private_file_get($file_id);
		if (!is_array($row)) {
			return false;
		}

		$path = vms_private_file_path((string) ($row['stored_filename'] ?? ''));
		if ($path !== '' && vms_private_files_path_is_safe($path) && file_exists($path) && is_file($path)) {
				wp_delete_file($path);
			}

		global $wpdb;
		$table = vms_private_files_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Private-file deletion removes the current plugin-owned index row immediately after bounded local-file cleanup; no core deletion or cache API owns this repository.
		$deleted = $wpdb->delete($table, array('id' => $file_id), array('%d'));

		return $deleted !== false;
	}
}

if (!function_exists('vms_private_w9_storage_kind_meta_key')) {
	function vms_private_w9_storage_kind_meta_key(): string
	{
		if (function_exists('vms_meta_key')) {
			$mapped = (string) vms_meta_key('vendor', 'w9_upload_storage_kind');
			if ($mapped !== '') {
				return $mapped;
			}
		}

		return '_vms_w9_upload_storage_kind';
	}
}

if (!function_exists('vms_private_w9_allowed_mimes')) {
	function vms_private_w9_allowed_mimes(): array
	{
		return array(
			'pdf' => 'application/pdf',
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png' => 'image/png',
			'webp' => 'image/webp',
		);
	}
}

if (!function_exists('vms_private_w9_max_bytes')) {
	function vms_private_w9_max_bytes(): int
	{
		$configured = 10 * 1024 * 1024;
		$wp_limit = (int) wp_max_upload_size();
		if ($wp_limit > 0) {
			return max(1, min($configured, $wp_limit));
		}

		return $configured;
	}
}

if (!function_exists('vms_private_w9_download_url')) {
	function vms_private_w9_download_url(int $post_id): string
	{
		$post_id = absint($post_id);
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'vms_private_w9_download',
					'post_id' => $post_id,
				),
				admin_url('admin-post.php')
			),
			'vms_private_w9_download_' . $post_id
		);
	}
}

if (!function_exists('vms_private_w9_store_upload')) {
	/**
	 * @return int|WP_Error
	 */
	function vms_private_w9_store_upload(int $post_id, array $files)
	{
		$post_id = absint($post_id);
		$upload = vms_upload_read_file($files, 'vms_w9_upload');
		if (is_wp_error($upload)) {
			return $upload;
		}

		$validated = vms_validate_uploaded_file(
			$upload,
			array(
				'allowed_mimes' => vms_private_w9_allowed_mimes(),
				'max_bytes' => vms_private_w9_max_bytes(),
				'type_message' => __('Upload must be a PDF or image (JPG/PNG/WEBP).', 'backstage-venue-manager'),
				'empty_message' => __('The uploaded W-9 file is empty.', 'backstage-venue-manager'),
				'too_large_message' => __('The uploaded W-9 file is too large.', 'backstage-venue-manager'),
				'tmp_invalid_message' => __('The uploaded W-9 file could not be verified.', 'backstage-venue-manager'),
			)
		);
		if (is_wp_error($validated)) {
			return $validated;
		}

		$post_type = get_post_type($post_id);
		if (!is_string($post_type) || $post_type === '') {
			$post_type = 'record';
		}

		return vms_private_files_store_validated_upload(
			$validated,
			array(
				'allowed_mimes' => vms_private_w9_allowed_mimes(),
				'bucket' => 'tax-docs',
				'related_post_type' => sanitize_key($post_type),
				'related_post_id' => $post_id,
			)
		);
	}
}

if (!function_exists('vms_private_w9_file_payload')) {
	/**
	 * @return array<string,string|int>|WP_Error
	 */
	function vms_private_w9_file_payload(int $post_id)
	{
		$post_id = absint($post_id);
		if ($post_id <= 0) {
			return new WP_Error('w9_missing', __('Requested file is not available.', 'backstage-venue-manager'));
		}

		$upload_key = function_exists('vms_meta_key') ? (string) vms_meta_key('vendor', 'w9_upload_id') : '_vms_w9_upload_id';
		if ($upload_key === '') {
			$upload_key = '_vms_w9_upload_id';
		}

		$file_id = absint(get_post_meta($post_id, $upload_key, true));
		if ($file_id <= 0) {
			return new WP_Error('w9_missing', __('Requested file is not available.', 'backstage-venue-manager'));
		}

		$storage_kind = sanitize_key((string) get_post_meta($post_id, vms_private_w9_storage_kind_meta_key(), true));
		if ($storage_kind === 'private_file') {
			$row = vms_private_file_get($file_id);
			if (!is_array($row)) {
				return new WP_Error('w9_missing', __('Requested file is not available.', 'backstage-venue-manager'));
			}

			$path = vms_private_file_path((string) ($row['stored_filename'] ?? ''));
			if ($path === '' || !vms_private_files_path_is_safe($path)) {
				return new WP_Error('w9_missing', __('Requested file is not available.', 'backstage-venue-manager'));
			}

			return array(
				'path' => $path,
				'mime' => (string) ($row['mime_type'] ?? 'application/octet-stream'),
				'filename' => (string) ($row['original_filename'] ?? 'w9-upload'),
				'storage_kind' => 'private_file',
				'file_id' => $file_id,
			);
		}

		$attachment = vms_private_files_attachment_payload($file_id);
		if (is_wp_error($attachment)) {
			return $attachment;
		}

		$attachment['storage_kind'] = 'attachment';
		$attachment['file_id'] = $file_id;
		return $attachment;
	}
}

if (!function_exists('vms_private_w9_file_label')) {
	function vms_private_w9_file_label(int $post_id): string
	{
		$payload = vms_private_w9_file_payload($post_id);
		if (is_wp_error($payload)) {
			return '';
		}

		return trim((string) ($payload['filename'] ?? ''));
	}
}

if (!function_exists('vms_private_w9_user_can_download')) {
	function vms_private_w9_user_can_download(int $post_id): bool
	{
		$post_id = absint($post_id);
		if ($post_id <= 0) {
			return false;
		}

		if (current_user_can('edit_post', $post_id)) {
			return true;
		}
		if (!is_user_logged_in()) {
			return false;
		}

		$user_id = get_current_user_id();
		$post_type = get_post_type($post_id);
		if ($post_type === 'vms_vendor') {
			if (function_exists('vms_user_can_access_vendor') && vms_user_can_access_vendor($user_id, $post_id)) {
				return true;
			}
			if (function_exists('vms_get_active_vendor_ids_for_user')) {
				$vendor_ids = array_map('absint', (array) vms_get_active_vendor_ids_for_user($user_id));
				return in_array($post_id, $vendor_ids, true);
			}
		}

		if ($post_type === 'vms_staff') {
			return (int) get_user_meta($user_id, '_vms_staff_id', true) === $post_id;
		}

		return false;
	}
}

if (!function_exists('vms_private_w9_download_handler')) {
	function vms_private_w9_download_handler(): void
	{
		$post_id = isset($_GET['post_id']) && !is_array($_GET['post_id']) ? absint($_GET['post_id']) : 0;
		if ($post_id <= 0) {
			wp_die(esc_html__('Requested file is not available.', 'backstage-venue-manager'));
		}

		check_admin_referer('vms_private_w9_download_' . $post_id);
		if (!vms_private_w9_user_can_download($post_id)) {
			wp_die(esc_html__('You do not have permission to download this file.', 'backstage-venue-manager'));
		}

		$payload = vms_private_w9_file_payload($post_id);
		if (is_wp_error($payload)) {
			wp_die(esc_html($payload->get_error_message()));
		}

		$mime = trim((string) ($payload['mime'] ?? ''));
		$allowed_mimes = array_values(vms_private_w9_allowed_mimes());
		if (!in_array($mime, $allowed_mimes, true)) {
			$mime = 'application/octet-stream';
		}

		vms_private_files_stream_path(
			(string) ($payload['path'] ?? ''),
			(string) ($payload['filename'] ?? 'w9-upload'),
			$mime
		);
	}
}
add_action('admin_post_vms_private_w9_download', 'vms_private_w9_download_handler');

if (!function_exists('vms_private_staff_cert_allowed_mimes')) {
	function vms_private_staff_cert_allowed_mimes(): array
	{
		return array(
			'pdf' => 'application/pdf',
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png' => 'image/png',
			'webp' => 'image/webp',
			'heic' => 'image/heic',
			'heif' => 'image/heif',
		);
	}
}

if (!function_exists('vms_private_staff_cert_max_bytes')) {
	function vms_private_staff_cert_max_bytes(): int
	{
		$configured = 10 * 1024 * 1024;
		$wp_limit = (int) wp_max_upload_size();
		if ($wp_limit > 0) {
			return max(1, min($configured, $wp_limit));
		}

		return $configured;
	}
}

if (!function_exists('vms_private_staff_cert_store_upload')) {
	/**
	 * @return int|WP_Error
	 */
	function vms_private_staff_cert_store_upload(int $staff_id, array $files)
	{
		$staff_id = absint($staff_id);
		$upload = vms_upload_read_file($files, 'vms_staff_certification_file');
		if (is_wp_error($upload)) {
			return $upload;
		}

		$validated = vms_validate_uploaded_file(
			$upload,
			array(
				'allowed_mimes' => vms_private_staff_cert_allowed_mimes(),
				'max_bytes' => vms_private_staff_cert_max_bytes(),
				'type_message' => __('Upload must be a PDF or image (JPG/PNG/WEBP/HEIC/HEIF).', 'backstage-venue-manager'),
				'empty_message' => __('The uploaded certificate file is empty.', 'backstage-venue-manager'),
				'too_large_message' => __('The uploaded certificate file is too large.', 'backstage-venue-manager'),
				'tmp_invalid_message' => __('The uploaded certificate file could not be verified.', 'backstage-venue-manager'),
			)
		);
		if (is_wp_error($validated)) {
			return $validated;
		}

		return vms_private_files_store_validated_upload(
			$validated,
			array(
				'allowed_mimes' => vms_private_staff_cert_allowed_mimes(),
				'bucket' => 'staff-certifications',
				'related_post_type' => 'vms_staff',
				'related_post_id' => $staff_id,
			)
		);
	}
}

if (!function_exists('vms_private_staff_cert_download_url')) {
	function vms_private_staff_cert_download_url(int $staff_id, string $qualification_id): string
	{
		$staff_id = absint($staff_id);
		$qualification_id = sanitize_key($qualification_id);
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'vms_private_staff_cert_download',
					'staff_id' => $staff_id,
					'qualification_id' => $qualification_id,
				),
				admin_url('admin-post.php')
			),
			'vms_private_staff_cert_download_' . $staff_id . '_' . $qualification_id
		);
	}
}

if (!function_exists('vms_private_staff_cert_user_can_download')) {
	function vms_private_staff_cert_user_can_download(int $staff_id): bool
	{
		$staff_id = absint($staff_id);
		if ($staff_id <= 0) {
			return false;
		}

		if (current_user_can('edit_post', $staff_id)) {
			return true;
		}
		if (!is_user_logged_in()) {
			return false;
		}

		return (int) get_user_meta(get_current_user_id(), '_vms_staff_id', true) === $staff_id;
	}
}

if (!function_exists('vms_private_staff_cert_file_payload')) {
	/**
	 * @param array<string,mixed> $row
	 * @return array<string,string|int>|WP_Error
	 */
	function vms_private_staff_cert_file_payload(int $staff_id, array $row)
	{
		$staff_id = absint($staff_id);
		if ($staff_id <= 0) {
			return new WP_Error('staff_cert_missing', __('Requested file is not available.', 'backstage-venue-manager'));
		}

		$file_id = isset($row['attachment_id']) ? absint($row['attachment_id']) : 0;
		if ($file_id <= 0) {
			return new WP_Error('staff_cert_missing', __('Requested file is not available.', 'backstage-venue-manager'));
		}

		$storage_kind = isset($row['storage_kind']) ? sanitize_key((string) $row['storage_kind']) : '';
		if ($storage_kind === 'private_file') {
			$private_row = vms_private_file_get($file_id);
			if (!is_array($private_row)) {
				return new WP_Error('staff_cert_missing', __('Requested file is not available.', 'backstage-venue-manager'));
			}

			$path = vms_private_file_path((string) ($private_row['stored_filename'] ?? ''));
			if ($path === '' || !vms_private_files_path_is_safe($path)) {
				return new WP_Error('staff_cert_missing', __('Requested file is not available.', 'backstage-venue-manager'));
			}

			return array(
				'path' => $path,
				'mime' => (string) ($private_row['mime_type'] ?? 'application/octet-stream'),
				'filename' => (string) ($private_row['original_filename'] ?? 'certificate-upload'),
				'storage_kind' => 'private_file',
				'file_id' => $file_id,
			);
		}

		$attachment = vms_private_files_attachment_payload($file_id);
		if (is_wp_error($attachment)) {
			return $attachment;
		}

		$attachment['storage_kind'] = 'attachment';
		$attachment['file_id'] = $file_id;
		return $attachment;
	}
}

if (!function_exists('vms_private_staff_cert_download_handler')) {
	function vms_private_staff_cert_download_handler(): void
	{
		$staff_id = isset($_GET['staff_id']) && !is_array($_GET['staff_id']) ? absint($_GET['staff_id']) : 0;
		$qualification_id = isset($_GET['qualification_id']) && !is_array($_GET['qualification_id'])
			? sanitize_key((string) wp_unslash($_GET['qualification_id']))
			: '';
		if ($staff_id <= 0 || $qualification_id === '') {
			wp_die(esc_html__('Requested file is not available.', 'backstage-venue-manager'));
		}

		check_admin_referer('vms_private_staff_cert_download_' . $staff_id . '_' . $qualification_id);
		if (!vms_private_staff_cert_user_can_download($staff_id)) {
			wp_die(esc_html__('You do not have permission to download this file.', 'backstage-venue-manager'));
		}

		$rows = function_exists('vms_staffing_get_staff_qualifications')
			? (array) vms_staffing_get_staff_qualifications($staff_id)
			: array();
		$match = null;
		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}
			if (sanitize_key((string) ($row['id'] ?? '')) !== $qualification_id) {
				continue;
			}
			$match = $row;
			break;
		}
		if (!is_array($match)) {
			wp_die(esc_html__('Requested file is not available.', 'backstage-venue-manager'));
		}

		$payload = vms_private_staff_cert_file_payload($staff_id, $match);
		if (is_wp_error($payload)) {
			wp_die(esc_html($payload->get_error_message()));
		}

		$mime = trim((string) ($payload['mime'] ?? ''));
		$allowed_mimes = array_values(vms_private_staff_cert_allowed_mimes());
		if (!in_array($mime, $allowed_mimes, true)) {
			$mime = 'application/octet-stream';
		}

		vms_private_files_stream_path(
			(string) ($payload['path'] ?? ''),
			(string) ($payload['filename'] ?? 'certificate-upload'),
			$mime
		);
	}
}
add_action('admin_post_vms_private_staff_cert_download', 'vms_private_staff_cert_download_handler');
