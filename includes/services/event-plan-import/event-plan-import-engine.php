<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_event_plan_import_meta_key')) {
	/**
	 * Resolve Event Plan meta keys from registry with fallback.
	 */
	function vms_event_plan_import_meta_key(string $field, string $fallback): string
	{
		if (function_exists('vms_meta_key')) {
			$key = (string) vms_meta_key('event_plan', $field);
			if ($key !== '') {
				return $key;
			}
		}
		return $fallback;
	}
}

if (!function_exists('vms_event_plan_import_meta_key_import_key')) {
	function vms_event_plan_import_meta_key_import_key(): string
	{
		return vms_event_plan_import_meta_key('import_key', '_vms_import_event_key');
	}
}

if (!function_exists('vms_event_plan_import_preview_ttl_seconds')) {
	function vms_event_plan_import_preview_ttl_seconds(): int
	{
		return 30 * MINUTE_IN_SECONDS;
	}
}

if (!function_exists('vms_event_plan_import_make_token')) {
	function vms_event_plan_import_make_token(): string
	{
		return sanitize_key('epcsv_' . strtolower(wp_generate_password(16, false, false)));
	}
}

if (!function_exists('vms_event_plan_import_notice_transient_key')) {
	function vms_event_plan_import_notice_transient_key(int $user_id): string
	{
		return 'vms_epcsv_notice_' . max(0, $user_id);
	}
}

if (!function_exists('vms_event_plan_import_preview_transient_key')) {
	function vms_event_plan_import_preview_transient_key(int $user_id, string $token): string
	{
		return 'vms_epcsv_preview_' . max(0, $user_id) . '_' . sanitize_key($token);
	}
}

if (!function_exists('vms_event_plan_import_audit_option_key')) {
	function vms_event_plan_import_audit_option_key(): string
	{
		return 'vms_event_plan_import_audit_runs_v1';
	}
}

if (!function_exists('vms_event_plan_import_set_notice')) {
	function vms_event_plan_import_set_notice(string $type, string $message): void
	{
		$user_id = (int) get_current_user_id();
		if ($user_id <= 0) {
			return;
		}

		set_transient(
			vms_event_plan_import_notice_transient_key($user_id),
			array(
				'type' => sanitize_key($type),
				'message' => sanitize_text_field($message),
			),
			10 * MINUTE_IN_SECONDS
		);
	}
}

if (!function_exists('vms_event_plan_import_pop_notice')) {
	function vms_event_plan_import_pop_notice(): array
	{
		$user_id = (int) get_current_user_id();
		if ($user_id <= 0) {
			return array();
		}

		$key = vms_event_plan_import_notice_transient_key($user_id);
		$value = get_transient($key);
		delete_transient($key);

		return is_array($value) ? $value : array();
	}
}

if (!function_exists('vms_event_plan_import_get_preview_payload')) {
	function vms_event_plan_import_get_preview_payload(string $token, int $user_id = 0): array
	{
		$user_id = $user_id > 0 ? $user_id : (int) get_current_user_id();
		if ($user_id <= 0) {
			return array();
		}

		$key = vms_event_plan_import_preview_transient_key($user_id, $token);
		$value = get_transient($key);

		return is_array($value) ? $value : array();
	}
}

if (!function_exists('vms_event_plan_import_set_preview_payload')) {
	function vms_event_plan_import_set_preview_payload(string $token, array $payload, int $user_id = 0): void
	{
		$user_id = $user_id > 0 ? $user_id : (int) get_current_user_id();
		if ($user_id <= 0) {
			return;
		}

		$key = vms_event_plan_import_preview_transient_key($user_id, $token);
		set_transient($key, $payload, vms_event_plan_import_preview_ttl_seconds());
	}
}

if (!function_exists('vms_event_plan_import_delete_preview_payload')) {
	function vms_event_plan_import_delete_preview_payload(string $token, int $user_id = 0): void
	{
		$user_id = $user_id > 0 ? $user_id : (int) get_current_user_id();
		if ($user_id <= 0) {
			return;
		}

		$key = vms_event_plan_import_preview_transient_key($user_id, $token);
		$payload = get_transient($key);
		if (is_array($payload) && function_exists('vms_event_plan_import_delete_stored_file')) {
			foreach (array('source_csv_storage_key', 'rows_json_storage_key', 'report_csv_storage_key') as $storage_key_field) {
				if (!empty($payload[$storage_key_field])) {
					vms_event_plan_import_delete_stored_file((string) $payload[$storage_key_field]);
				}
			}
			foreach (array('source_csv_path', 'rows_json_path', 'report_csv_path') as $legacy_path_field) {
				if (!empty($payload[$legacy_path_field])) {
					vms_event_plan_import_delete_stored_file((string) $payload[$legacy_path_field]);
				}
			}
		}

		delete_transient($key);
	}
}

if (!function_exists('vms_event_plan_import_storage_bucket')) {
	function vms_event_plan_import_storage_bucket(): string
	{
		return 'event-plan-imports';
	}
}

if (!function_exists('vms_event_plan_import_allowed_mimes')) {
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
}

if (!function_exists('vms_event_plan_import_max_bytes')) {
	function vms_event_plan_import_max_bytes(): int
	{
		$configured = 5 * 1024 * 1024;
		$wp_limit = (int) wp_max_upload_size();
		if ($wp_limit > 0) {
			return max(1, min($configured, $wp_limit));
		}

		return $configured;
	}
}

if (!function_exists('vms_event_plan_import_legacy_upload_roots')) {
	function vms_event_plan_import_legacy_upload_roots(): array
	{
		$upload = wp_upload_dir(null, false);
		$base_dir = isset($upload['basedir']) ? trim((string) $upload['basedir']) : '';
		if ($base_dir === '') {
			return array();
		}

		return array(
			trailingslashit($base_dir) . 'vms-event-plan-imports',
		);
	}
}

if (!function_exists('vms_event_plan_import_upload_root')) {
	/**
	 * @return array<string,string>|WP_Error
	 */
	function vms_event_plan_import_upload_root()
	{
		if (!function_exists('vms_private_files_ensure_dir') || !function_exists('vms_private_files_bucket_dir')) {
			return new WP_Error('upload_dir_missing', __('Upload directory is unavailable.', 'backstage-venue-manager'));
		}

		$bucket = vms_event_plan_import_storage_bucket();
		if (!vms_private_files_ensure_dir($bucket)) {
			return new WP_Error('upload_dir_create_failed', __('Could not create import directory in uploads.', 'backstage-venue-manager'));
		}
		$dir = vms_private_files_bucket_dir($bucket);
		if ($dir === '' || !is_dir($dir)) {
			return new WP_Error('upload_dir_create_failed', __('Could not create import directory in uploads.', 'backstage-venue-manager'));
		}

		return array(
			'dir' => $dir,
			'url' => '',
		);
	}
}

if (!function_exists('vms_event_plan_import_path_is_safe')) {
	function vms_event_plan_import_path_is_safe(string $path): bool
	{
		$real_path = realpath($path);
		if (!is_string($real_path) || $real_path === '') {
			return false;
		}

		$roots = array();
		$root = vms_event_plan_import_upload_root();
		if (!is_wp_error($root)) {
			$roots[] = (string) $root['dir'];
		}
		$roots = array_merge($roots, vms_event_plan_import_legacy_upload_roots());

		foreach ($roots as $root_dir) {
			$real_root = realpath($root_dir);
			if (!is_string($real_root) || $real_root === '') {
				continue;
			}
			if (strpos(wp_normalize_path($real_path), trailingslashit(wp_normalize_path($real_root))) === 0) {
				return true;
			}
		}

		return false;
	}
}

if (!function_exists('vms_event_plan_import_storage_path')) {
	function vms_event_plan_import_storage_path(string $reference): string
	{
		$reference = trim($reference);
		if ($reference === '') {
			return '';
		}

		if (file_exists($reference) && vms_event_plan_import_path_is_safe($reference)) {
			return $reference;
		}
		if (!function_exists('vms_private_files_validate_storage_key') || !function_exists('vms_private_files_absolute_path')) {
			return '';
		}

		$storage_key = vms_private_files_validate_storage_key($reference);
		$bucket = vms_event_plan_import_storage_bucket() . '/';
		if ($storage_key === '' || strpos($storage_key, $bucket) !== 0) {
			return '';
		}

		$path = vms_private_files_absolute_path($storage_key);
		if ($path === '' || !vms_event_plan_import_path_is_safe($path)) {
			return '';
		}

		return $path;
	}
}

if (!function_exists('vms_event_plan_import_delete_stored_file')) {
	function vms_event_plan_import_delete_stored_file(string $reference): void
	{
		$path = vms_event_plan_import_storage_path($reference);
		if ($path !== '' && file_exists($path) && is_file($path)) {
			@unlink($path);
		}
	}
}

if (!function_exists('vms_event_plan_import_prepare_generated_path')) {
	/**
	 * @return array<string,string>|WP_Error
	 */
	function vms_event_plan_import_prepare_generated_path(string $extension, string $token, string $suffix)
	{
		$root = vms_event_plan_import_upload_root();
		if (is_wp_error($root)) {
			return $root;
		}

		$token = sanitize_file_name($token);
		$suffix = sanitize_file_name($suffix);
		$extension = sanitize_key(ltrim($extension, '.'));
		if ($token === '' || $suffix === '' || $extension === '') {
			return new WP_Error('upload_dir_create_failed', __('Could not prepare import file storage.', 'backstage-venue-manager'));
		}

		$storage_key = vms_event_plan_import_storage_bucket() . '/' . $token . '-' . $suffix . '.' . $extension;
		$path = function_exists('vms_private_files_absolute_path')
			? vms_private_files_absolute_path($storage_key)
			: '';
		if ($path === '') {
			return new WP_Error('upload_dir_create_failed', __('Could not prepare import file storage.', 'backstage-venue-manager'));
		}

		$dir = dirname($path);
		if (!is_dir($dir) && !wp_mkdir_p($dir)) {
			return new WP_Error('upload_dir_create_failed', __('Could not prepare import file storage.', 'backstage-venue-manager'));
		}
		if (function_exists('vms_private_files_write_hardening_files')) {
			vms_private_files_write_hardening_files($dir);
		}

		return array(
			'storage_key' => $storage_key,
			'path' => $path,
		);
	}
}

if (!function_exists('vms_event_plan_import_ci_key')) {
	function vms_event_plan_import_ci_key(string $value): string
	{
		$value = trim($value);
		if ($value === '') {
			return '';
		}
		return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
	}
}

if (!function_exists('vms_event_plan_import_raw_post_title')) {
	function vms_event_plan_import_raw_post_title(int $post_id): string
	{
		$post_id = absint($post_id);
		if ($post_id <= 0) {
			return '';
		}

		$title = get_post_field('post_title', $post_id, 'raw');
		if (!is_string($title)) {
			$title = get_post_field('post_title', $post_id);
		}

		return trim((string) $title);
	}
}

if (!function_exists('vms_event_plan_import_normalize_apostrophes')) {
	function vms_event_plan_import_normalize_apostrophes(string $value): string
	{
		$normalized = preg_replace('/[\x{0060}\x{00B4}\x{02BC}\x{2018}\x{2019}\x{201A}\x{201B}\x{2032}]/u', "'", $value);
		return is_string($normalized) ? $normalized : $value;
	}
}

if (!function_exists('vms_event_plan_import_match_key')) {
	function vms_event_plan_import_match_key(string $value): string
	{
		$value = trim($value);
		if ($value === '') {
			return '';
		}

		$decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		if (is_string($decoded) && $decoded !== '') {
			$value = $decoded;
		}

		$value = vms_event_plan_import_normalize_apostrophes($value);

		$normalized_spaces = preg_replace('/[\x{00A0}\x{1680}\x{2000}-\x{200A}\x{202F}\x{205F}\x{3000}]/u', ' ', $value);
		if (is_string($normalized_spaces) && $normalized_spaces !== '') {
			$value = $normalized_spaces;
		}

		$normalized_dashes = preg_replace('/[\x{2010}-\x{2015}\x{2212}]/u', '-', $value);
		if (is_string($normalized_dashes) && $normalized_dashes !== '') {
			$value = $normalized_dashes;
		}

		$collapsed = preg_replace('/\s+/u', ' ', $value);
		if (is_string($collapsed) && $collapsed !== '') {
			$value = $collapsed;
		}

		return vms_event_plan_import_ci_key($value);
	}
}

if (!function_exists('vms_event_plan_import_normalize_header')) {
	function vms_event_plan_import_normalize_header(string $header): string
	{
		$header = trim($header);
		$header = function_exists('mb_strtolower') ? mb_strtolower($header, 'UTF-8') : strtolower($header);
		$header = str_replace(array('-', ' '), '_', $header);
		return preg_replace('/[^a-z0-9_]/', '', $header);
	}
}

if (!function_exists('vms_event_plan_import_required_columns')) {
	/**
	 * @return string[]
	 */
	function vms_event_plan_import_required_columns(): array
	{
		if (function_exists('vms_csv_event_plan_required_columns')) {
			return (array) vms_csv_event_plan_required_columns();
		}

		return array(
			'event_key',
			'event_date',
			'venue_name',
			'primary_vendor_name',
		);
	}
}

if (!function_exists('vms_event_plan_import_allowed_comp_structures')) {
	/**
	 * @return string[]
	 */
	function vms_event_plan_import_allowed_comp_structures(): array
	{
		if (function_exists('vms_comp_supported_structures')) {
			return (array) vms_comp_supported_structures();
		}

		return array('flat_fee', 'door_split', 'flat_fee_door_split', 'attendance_bonus');
	}
}

if (!function_exists('vms_event_plan_import_parse_date')) {
	function vms_event_plan_import_parse_date(string $value): string
	{
		$value = trim($value);
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
			return '';
		}

		$parts = explode('-', $value);
		$y = isset($parts[0]) ? (int) $parts[0] : 0;
		$m = isset($parts[1]) ? (int) $parts[1] : 0;
		$d = isset($parts[2]) ? (int) $parts[2] : 0;
		if (!checkdate($m, $d, $y)) {
			return '';
		}

		return $value;
	}
}

if (!function_exists('vms_event_plan_import_parse_time')) {
	function vms_event_plan_import_parse_time(string $value, string $fallback): string
	{
		$value = trim($value);
		if ($value === '') {
			return $fallback;
		}

		if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $value, $m)) {
			return '';
		}

		$h = (int) $m[1];
		$i = (int) $m[2];
		return sprintf('%02d:%02d', $h, $i);
	}
}

if (!function_exists('vms_event_plan_import_parse_numeric')) {
	/**
	 * @return array{ok:bool,value:float,message:string}
	 */
	function vms_event_plan_import_parse_numeric(string $value): array
	{
		$value = trim($value);
		if ($value === '') {
			return array('ok' => true, 'value' => 0.0, 'message' => '');
		}

		$sanitized = preg_replace('/[^0-9.\-]/', '', $value);
		if ($sanitized === '' || $sanitized === '-' || $sanitized === '.' || $sanitized === '-.') {
			return array('ok' => false, 'value' => 0.0, 'message' => __('Not a numeric value.', 'backstage-venue-manager'));
		}

		$numeric = (float) $sanitized;
		return array('ok' => true, 'value' => $numeric, 'message' => '');
	}
}

if (!function_exists('vms_event_plan_import_parse_nonnegative_int')) {
	/**
	 * @return array{ok:bool,value:int,message:string}
	 */
	function vms_event_plan_import_parse_nonnegative_int(string $value, int $min = 0): array
	{
		$parsed = vms_event_plan_import_parse_numeric($value);
		if (empty($parsed['ok'])) {
			return array('ok' => false, 'value' => 0, 'message' => (string) ($parsed['message'] ?? __('Not a numeric value.', 'backstage-venue-manager')));
		}

		$numeric = (float) ($parsed['value'] ?? 0);
		if ($numeric < $min) {
			return array('ok' => false, 'value' => 0, 'message' => __('Value is below the minimum.', 'backstage-venue-manager'));
		}
		if (floor($numeric) !== $numeric) {
			return array('ok' => false, 'value' => 0, 'message' => __('Value must be a whole number.', 'backstage-venue-manager'));
		}

		return array('ok' => true, 'value' => (int) $numeric, 'message' => '');
	}
}

if (!function_exists('vms_event_plan_import_build_post_title_lookup')) {
	/**
	 * @return array<string,mixed>
	 */
	function vms_event_plan_import_build_post_title_lookup(string $post_type): array
	{
		$ids = get_posts(array(
			'post_type' => $post_type,
			'post_status' => array('publish', 'private', 'draft', 'pending', 'future'),
			'posts_per_page' => -1,
			'fields' => 'ids',
			'orderby' => 'ID',
			'order' => 'ASC',
			'no_found_rows' => true,
		));

		$exact = array();
		$ci = array();
		$match = array();

		foreach ((array) $ids as $id) {
			$id = absint($id);
			if ($id <= 0) {
				continue;
			}

			$title = vms_event_plan_import_raw_post_title($id);
			if ($title === '') {
				continue;
			}

			if (!isset($exact[$title])) {
				$exact[$title] = array();
			}
			$exact[$title][] = $id;

			$ci_key = vms_event_plan_import_ci_key($title);
			if ($ci_key === '') {
				continue;
			}
			if (!isset($ci[$ci_key])) {
				$ci[$ci_key] = array();
			}
			$ci[$ci_key][] = $id;

			$match_key = vms_event_plan_import_match_key($title);
			if ($match_key === '') {
				continue;
			}
			if (!isset($match[$match_key])) {
				$match[$match_key] = array();
			}
			$match[$match_key][] = $id;
		}

		return array(
			'exact' => $exact,
			'ci' => $ci,
			'match' => $match,
		);
	}
}

if (!function_exists('vms_event_plan_import_lookup_title_ids')) {
	/**
	 * @param array<string,mixed> $lookup
	 * @return array{ids:int[],match:string}
	 */
	function vms_event_plan_import_lookup_title_ids(array $lookup, string $name): array
	{
		$name = trim($name);
		if ($name === '') {
			return array('ids' => array(), 'match' => '');
		}

		$exact = isset($lookup['exact']) && is_array($lookup['exact']) ? $lookup['exact'] : array();
		$ci = isset($lookup['ci']) && is_array($lookup['ci']) ? $lookup['ci'] : array();
		$match = isset($lookup['match']) && is_array($lookup['match']) ? $lookup['match'] : array();

		if (isset($exact[$name]) && is_array($exact[$name]) && !empty($exact[$name])) {
			$ids = array_values(array_unique(array_map('absint', (array) $exact[$name])));
			$ids = array_values(array_filter($ids, static function ($id): bool {
				return $id > 0;
			}));
			return array('ids' => $ids, 'match' => 'exact');
		}

		$ci_key = vms_event_plan_import_ci_key($name);
		if ($ci_key !== '' && isset($ci[$ci_key]) && is_array($ci[$ci_key]) && !empty($ci[$ci_key])) {
			$ids = array_values(array_unique(array_map('absint', (array) $ci[$ci_key])));
			$ids = array_values(array_filter($ids, static function ($id): bool {
				return $id > 0;
			}));
			return array('ids' => $ids, 'match' => 'case_insensitive');
		}

		$match_key = vms_event_plan_import_match_key($name);
		if ($match_key !== '' && isset($match[$match_key]) && is_array($match[$match_key]) && !empty($match[$match_key])) {
			$ids = array_values(array_unique(array_map('absint', (array) $match[$match_key])));
			$ids = array_values(array_filter($ids, static function ($id): bool {
				return $id > 0;
			}));
			return array('ids' => $ids, 'match' => 'normalized');
		}

		return array('ids' => array(), 'match' => '');
	}
}

if (!function_exists('vms_event_plan_import_build_vendor_type_lookup')) {
	/**
	 * @return array<string,mixed>
	 */
	function vms_event_plan_import_build_vendor_type_lookup(): array
	{
		$terms = get_terms(array(
			'taxonomy' => 'vms_vendor_type',
			'hide_empty' => false,
		));

		$by_slug = array();
		$by_ci_name = array();
		if (!is_wp_error($terms)) {
			foreach ((array) $terms as $term) {
				if (!($term instanceof WP_Term)) {
					continue;
				}

				$slug = sanitize_title((string) $term->slug);
				if ($slug === '') {
					continue;
				}
				$by_slug[$slug] = (int) $term->term_id;

				$name_key = vms_event_plan_import_ci_key((string) $term->name);
				if ($name_key !== '' && !isset($by_ci_name[$name_key])) {
					$by_ci_name[$name_key] = $slug;
				}
			}
		}

		return array(
			'by_slug' => $by_slug,
			'by_ci_name' => $by_ci_name,
		);
	}
}

if (!function_exists('vms_event_plan_import_resolve_vendor_type')) {
	/**
	 * @param array<string,mixed> $lookup
	 * @return array{slug:string,term_id:int,message:string}
	 */
	function vms_event_plan_import_resolve_vendor_type(string $raw, array $lookup): array
	{
		$raw = trim($raw);
		if ($raw === '') {
			return array('slug' => '', 'term_id' => 0, 'message' => '');
		}

		$by_slug = isset($lookup['by_slug']) && is_array($lookup['by_slug']) ? $lookup['by_slug'] : array();
		$by_ci_name = isset($lookup['by_ci_name']) && is_array($lookup['by_ci_name']) ? $lookup['by_ci_name'] : array();

		$slug = sanitize_title($raw);
		if ($slug !== '' && isset($by_slug[$slug])) {
			return array(
				'slug' => $slug,
				'term_id' => (int) $by_slug[$slug],
				'message' => '',
			);
		}

		$ci = vms_event_plan_import_ci_key($raw);
		if ($ci !== '' && isset($by_ci_name[$ci])) {
			$name_slug = sanitize_title((string) $by_ci_name[$ci]);
			if ($name_slug !== '' && isset($by_slug[$name_slug])) {
				return array(
					'slug' => $name_slug,
					'term_id' => (int) $by_slug[$name_slug],
					'message' => sprintf(
						/* translators: %s: vendor type input */
						__('Vendor type "%s" matched by name.', 'backstage-venue-manager'),
						$raw
					),
				);
			}
		}

		return array(
			'slug' => '',
			'term_id' => 0,
			'message' => sprintf(
				/* translators: %s: vendor type input */
				__('Vendor type "%s" was not found.', 'backstage-venue-manager'),
				$raw
			),
		);
	}
}

if (!function_exists('vms_event_plan_import_vendor_has_type')) {
	function vms_event_plan_import_vendor_has_type(int $vendor_id, string $type_slug): bool
	{
		$vendor_id = absint($vendor_id);
		$type_slug = function_exists('vms_vendor_type_normalize_slug')
			? vms_vendor_type_normalize_slug($type_slug)
			: sanitize_title($type_slug);
		if ($vendor_id <= 0 || $type_slug === '') {
			return false;
		}

		return function_exists('vms_vendor_has_type') ? vms_vendor_has_type($vendor_id, $type_slug) : (function_exists('has_term') ? has_term($type_slug, 'vms_vendor_type', $vendor_id) : false);
	}
}

if (!function_exists('vms_event_plan_import_resolve_vendor_id')) {
	/**
	 * @param array<string,mixed> $lookup
	 * @return array{id:int,match:string,message:string}
	 */
	function vms_event_plan_import_resolve_vendor_id(string $name, array $lookup, string $required_type_slug = ''): array
	{
		$name = trim($name);
		if ($name === '') {
			return array('id' => 0, 'match' => '', 'message' => __('Vendor name is blank.', 'backstage-venue-manager'));
		}

		$found = vms_event_plan_import_lookup_title_ids($lookup, $name);
		$ids = isset($found['ids']) && is_array($found['ids']) ? $found['ids'] : array();
		$match = isset($found['match']) ? (string) $found['match'] : '';
		if (empty($ids)) {
			return array('id' => 0, 'match' => '', 'message' => '');
		}

		if ($required_type_slug !== '') {
			foreach ($ids as $id) {
				$id = absint($id);
				if ($id <= 0) {
					continue;
				}
				if (vms_event_plan_import_vendor_has_type($id, $required_type_slug)) {
					$message = '';
					if (count($ids) > 1) {
						$message = sprintf(
							/* translators: %s: vendor name */
							__('Multiple vendors matched "%s"; using the first vendor matching the selected type.', 'backstage-venue-manager'),
							$name
						);
					}
					return array('id' => $id, 'match' => $match, 'message' => $message);
				}
			}

			return array(
				'id' => 0,
				'match' => $match,
				'message' => sprintf(
					/* translators: %s: vendor name */
					__('Vendor "%s" exists but does not match the selected vendor type.', 'backstage-venue-manager'),
					$name
				),
			);
		}

		$selected = absint($ids[0]);
		$message = '';
		if (count($ids) > 1) {
			$message = sprintf(
				/* translators: %s: vendor name */
				__('Multiple vendors matched "%s"; using the first match by ID.', 'backstage-venue-manager'),
				$name
			);
		}
		return array('id' => $selected, 'match' => $match, 'message' => $message);
	}
}

if (!function_exists('vms_event_plan_import_find_existing_plan_lookup')) {
	/**
	 * @return array<string,int>
	 */
	function vms_event_plan_import_find_existing_plan_lookup(): array
	{
		$import_key_meta = vms_event_plan_import_meta_key_import_key();
		$ids = get_posts(array(
			'post_type' => 'vms_event_plan',
			'post_status' => array('publish', 'private', 'draft', 'pending', 'future'),
			'posts_per_page' => -1,
			'fields' => 'ids',
			'no_found_rows' => true,
			'meta_query' => array(
				array(
					'key' => $import_key_meta,
					'compare' => 'EXISTS',
				),
			),
		));

		$map = array();
		foreach ((array) $ids as $id) {
			$id = absint($id);
			if ($id <= 0) {
				continue;
			}

			$key = trim((string) get_post_meta($id, $import_key_meta, true));
			if ($key === '' || isset($map[$key])) {
				continue;
			}
			$map[$key] = $id;
		}

		return $map;
	}
}

if (!function_exists('vms_event_plan_import_detect_columns')) {
	/**
	 * @param string[] $normalized_headers
	 * @return array<string,mixed>
	 */
	function vms_event_plan_import_detect_columns(array $normalized_headers): array
	{
		$headers = array_values(array_filter(array_map('strval', $normalized_headers), static function ($header): bool {
			return $header !== '';
		}));
		$header_map = array_fill_keys($headers, true);

		$secondary_cols = array();
		foreach ($headers as $header) {
			if (preg_match('/^secondary_vendor_(\d+)$/', $header)) {
				$secondary_cols[] = $header;
			}
		}
		sort($secondary_cols, SORT_NATURAL);

		$has_comp_structure = isset($header_map['comp_structure']);
		$has_flat = isset($header_map['flat_fee_amount']);
		$has_split = isset($header_map['door_split_percent']);
		$has_bonus_mode = isset($header_map['attendance_bonus_mode']);
		$has_bonus_start = isset($header_map['attendance_bonus_start_count']);
		$has_bonus_step_size = isset($header_map['attendance_bonus_step_size']);
		$has_bonus_step_bonus = isset($header_map['attendance_bonus_step_bonus']);
		$has_bonus_per_ticket = isset($header_map['attendance_bonus_per_ticket_rate']);
		$has_bonus_max = isset($header_map['attendance_bonus_max_bonus']);
		$has_attendance_bonus_columns = ($has_bonus_mode || $has_bonus_start || $has_bonus_step_size || $has_bonus_step_bonus || $has_bonus_per_ticket || $has_bonus_max);

		return array(
			'headers' => $headers,
			'has_event_title' => isset($header_map['event_title']),
			'has_start_time' => isset($header_map['start_time']),
			'has_end_time' => isset($header_map['end_time']),
			'has_agenda_text' => isset($header_map['agenda_text']),
			'has_comp_structure' => $has_comp_structure,
			'has_flat_fee_amount' => $has_flat,
			'has_door_split_percent' => $has_split,
			'has_attendance_bonus_mode' => $has_bonus_mode,
			'has_attendance_bonus_start_count' => $has_bonus_start,
			'has_attendance_bonus_step_size' => $has_bonus_step_size,
			'has_attendance_bonus_step_bonus' => $has_bonus_step_bonus,
			'has_attendance_bonus_per_ticket_rate' => $has_bonus_per_ticket,
			'has_attendance_bonus_max_bonus' => $has_bonus_max,
			'has_attendance_bonus_columns' => $has_attendance_bonus_columns,
			'has_comp_columns' => ($has_comp_structure || $has_flat || $has_split || $has_attendance_bonus_columns),
			'has_secondary_vendor_type' => isset($header_map['secondary_vendor_type']),
			'secondary_vendor_columns' => $secondary_cols,
			'has_secondary_columns' => (isset($header_map['secondary_vendor_type']) || !empty($secondary_cols)),
		);
	}
}

if (!function_exists('vms_event_plan_import_report_row_to_csv')) {
	/**
	 * @param resource $fh
	 * @param array<string,mixed> $row
	 */
	function vms_event_plan_import_report_row_to_csv($fh, array $row): void
	{
		$messages = array();
		if (!empty($row['messages']) && is_array($row['messages'])) {
			foreach ($row['messages'] as $message) {
				$text = trim((string) $message);
				if ($text !== '') {
					$messages[] = $text;
				}
			}
		}

		fputcsv($fh, array(
			(int) ($row['row_number'] ?? 0),
			(string) ($row['event_key'] ?? ''),
			(int) ($row['plan_id'] ?? 0),
			(string) ($row['action'] ?? ''),
			implode(' | ', $messages),
		));
	}
}

if (!function_exists('vms_event_plan_import_build_preview_from_csv')) {
	/**
	 * @param array<string,mixed> $options
	 * @return array<string,mixed>|WP_Error
	 */
	function vms_event_plan_import_build_preview_from_csv(string $csv_path, string $source_name, array $options, string $token, string $source_storage_key = '')
	{
		$csv_path = trim($csv_path);
		if ($csv_path === '' || !file_exists($csv_path)) {
			return new WP_Error('csv_missing', __('Uploaded CSV file is missing.', 'backstage-venue-manager'));
		}

		$fh = fopen($csv_path, 'rb');
		if (!is_resource($fh)) {
			return new WP_Error('csv_open_failed', __('Could not open CSV file for preview.', 'backstage-venue-manager'));
		}

		$raw_headers = fgetcsv($fh);
		if (!is_array($raw_headers) || empty($raw_headers)) {
			fclose($fh);
			return new WP_Error('csv_header_missing', __('CSV header row is missing.', 'backstage-venue-manager'));
		}

		$normalized_headers = array_map(static function ($header): string {
			return vms_event_plan_import_normalize_header((string) $header);
		}, $raw_headers);

		$required = vms_event_plan_import_required_columns();
		$missing = array();
		foreach ($required as $required_header) {
			if (!in_array($required_header, $normalized_headers, true)) {
				$missing[] = $required_header;
			}
		}
		if (!empty($missing)) {
			fclose($fh);
			return new WP_Error(
				'csv_required_missing',
				sprintf(
					/* translators: %s: comma-separated headers */
					__('CSV is missing required columns: %s', 'backstage-venue-manager'),
					implode(', ', $missing)
				)
			);
		}

		$columns = vms_event_plan_import_detect_columns($normalized_headers);
		$venue_lookup = vms_event_plan_import_build_post_title_lookup('vms_venue');
		$vendor_lookup = vms_event_plan_import_build_post_title_lookup('vms_vendor');
		$type_lookup = vms_event_plan_import_build_vendor_type_lookup();
		$plan_lookup = vms_event_plan_import_find_existing_plan_lookup();

		$allow_update_locked = !empty($options['allow_update_locked_plans']);
		$auto_create_vendors = !empty($options['auto_create_missing_vendors']);

		$summary = array(
			'total_rows' => 0,
			'create' => 0,
			'update' => 0,
			'skip' => 0,
			'errors' => 0,
			'warnings' => 0,
		);

		$seen_event_keys = array();
		$rows_for_commit = array();
		$report_rows = array();
		$row_number = 1;

		while (($cells = fgetcsv($fh)) !== false) {
			$row_number += 1;
			$summary['total_rows'] += 1;

			$row_data = array();
			foreach ($normalized_headers as $idx => $header) {
				if ($header === '') {
					continue;
				}
				$row_data[$header] = isset($cells[$idx]) ? trim((string) $cells[$idx]) : '';
			}

			$is_blank = true;
			foreach ($row_data as $value) {
				if (trim((string) $value) !== '') {
					$is_blank = false;
					break;
				}
			}
			if ($is_blank) {
				$report_rows[] = array(
					'row_number' => $row_number,
					'event_key' => '',
					'plan_id' => 0,
					'action' => 'skip',
					'messages' => array(__('Blank row skipped.', 'backstage-venue-manager')),
				);
				$summary['skip'] += 1;
				continue;
			}

			$errors = array();
			$warnings = array();
			$messages = array();
			$action = '';

			$event_key = trim((string) ($row_data['event_key'] ?? ''));
			if ($event_key === '') {
				$errors[] = __('event_key is required.', 'backstage-venue-manager');
			} elseif (isset($seen_event_keys[$event_key])) {
				$errors[] = sprintf(
					/* translators: %1$s: event key, %2$d: row number */
					__('Duplicate event_key "%1$s" already appeared on row %2$d.', 'backstage-venue-manager'),
					$event_key,
					(int) $seen_event_keys[$event_key]
				);
			} else {
				$seen_event_keys[$event_key] = $row_number;
			}

			$event_date = vms_event_plan_import_parse_date((string) ($row_data['event_date'] ?? ''));
			if ($event_date === '') {
				$errors[] = __('event_date must be in YYYY-MM-DD format.', 'backstage-venue-manager');
			}

			$start_time = vms_event_plan_import_parse_time((string) ($row_data['start_time'] ?? ''), '19:00');
			if ($start_time === '') {
				$errors[] = __('start_time must be HH:MM in 24-hour format.', 'backstage-venue-manager');
			}

			$end_time = vms_event_plan_import_parse_time((string) ($row_data['end_time'] ?? ''), '22:00');
			if ($end_time === '') {
				$errors[] = __('end_time must be HH:MM in 24-hour format.', 'backstage-venue-manager');
			}

			$venue_name = trim((string) ($row_data['venue_name'] ?? ''));
			if ($venue_name === '') {
				$errors[] = __('venue_name is required.', 'backstage-venue-manager');
			}

			$primary_vendor_name = trim((string) ($row_data['primary_vendor_name'] ?? ''));
			if ($primary_vendor_name === '') {
				$errors[] = __('primary_vendor_name is required.', 'backstage-venue-manager');
			}

			$event_title = trim((string) ($row_data['event_title'] ?? ''));
			if ($event_title === '') {
				$event_title = $primary_vendor_name;
			}
			if ($event_title === '') {
				$event_title = __('Untitled Event Plan', 'backstage-venue-manager');
			}

			$agenda_text = (string) ($row_data['agenda_text'] ?? '');

			$plan_id = 0;
			$current_status = '';
			if ($event_key !== '' && isset($plan_lookup[$event_key])) {
				$plan_id = (int) $plan_lookup[$event_key];
			}
			if ($plan_id > 0) {
				$current_status = function_exists('vms_event_plan_get_status')
					? (string) vms_event_plan_get_status($plan_id, 'event_list')
					: sanitize_key((string) get_post_meta($plan_id, vms_event_plan_import_meta_key('status', '_vms_event_plan_status'), true));
			}

			$is_locked = in_array($current_status, array('published', 'cancelled'), true);
			if ($plan_id > 0 && $is_locked && !$allow_update_locked) {
				$action = 'skip';
				$messages[] = sprintf(
					/* translators: %s: status */
					__('Existing plan is %s and updates are disabled.', 'backstage-venue-manager'),
					$current_status
				);
			}

			$venue_id = 0;
			if ($venue_name !== '') {
				$venue_match = vms_event_plan_import_lookup_title_ids($venue_lookup, $venue_name);
				if (!empty($venue_match['ids'])) {
					$venue_id = (int) $venue_match['ids'][0];
					if (count((array) $venue_match['ids']) > 1) {
						$warnings[] = sprintf(
							/* translators: %s: venue name */
							__('Multiple venues matched "%s"; using the first match.', 'backstage-venue-manager'),
							$venue_name
						);
					}
					} else {
						$errors[] = sprintf(
							/* translators: %s: venue name */
							__('Venue "%s" was not found in VMS Venues.', 'backstage-venue-manager'),
							$venue_name
						);
					}
			}

			$primary_vendor_id = 0;
			$create_primary_vendor = false;
			if ($primary_vendor_name !== '') {
				$primary_match = vms_event_plan_import_resolve_vendor_id($primary_vendor_name, $vendor_lookup);
				$primary_vendor_id = (int) ($primary_match['id'] ?? 0);
				$primary_note = trim((string) ($primary_match['message'] ?? ''));
				if ($primary_note !== '') {
					$warnings[] = $primary_note;
				}

				if ($primary_vendor_id <= 0) {
					if ($auto_create_vendors) {
						$create_primary_vendor = true;
						$warnings[] = sprintf(
							/* translators: %s: vendor name */
							__('Primary vendor "%s" will be auto-created during commit.', 'backstage-venue-manager'),
							$primary_vendor_name
						);
					} else {
						$errors[] = sprintf(
							/* translators: %s: vendor name */
							__('Primary vendor "%s" was not found.', 'backstage-venue-manager'),
							$primary_vendor_name
						);
					}
				}
			}

			$comp_structure = '';
			if (!empty($columns['has_comp_structure'])) {
				$comp_structure = sanitize_key((string) ($row_data['comp_structure'] ?? ''));
				if ($comp_structure !== '' && !in_array($comp_structure, vms_event_plan_import_allowed_comp_structures(), true)) {
					$errors[] = sprintf(
						/* translators: %s: comp structure */
						__('Unsupported comp_structure "%s".', 'backstage-venue-manager'),
						$comp_structure
					);
				}
			}

			$flat_fee_amount = null;
			if (!empty($columns['has_flat_fee_amount'])) {
				$flat_raw = (string) ($row_data['flat_fee_amount'] ?? '');
				if ($flat_raw !== '') {
					$parsed_flat = vms_event_plan_import_parse_numeric($flat_raw);
					if (empty($parsed_flat['ok'])) {
						$errors[] = __('flat_fee_amount must be numeric.', 'backstage-venue-manager');
					} else {
						$flat_fee_amount = (float) $parsed_flat['value'];
					}
				}
			}

			$door_split_percent = null;
			if (!empty($columns['has_door_split_percent'])) {
				$split_raw = (string) ($row_data['door_split_percent'] ?? '');
				if ($split_raw !== '') {
					$parsed_split = vms_event_plan_import_parse_numeric($split_raw);
					if (empty($parsed_split['ok'])) {
						$errors[] = __('door_split_percent must be numeric.', 'backstage-venue-manager');
					} else {
						$door_split_percent = (float) $parsed_split['value'];
						if ($door_split_percent < 0 || $door_split_percent > 100) {
							$errors[] = __('door_split_percent must be between 0 and 100.', 'backstage-venue-manager');
						}
					}
				}
			}

			$attendance_bonus_mode = '';
			if (!empty($columns['has_attendance_bonus_mode'])) {
				$attendance_bonus_mode = sanitize_key((string) ($row_data['attendance_bonus_mode'] ?? ''));
				if ($attendance_bonus_mode !== '' && !in_array($attendance_bonus_mode, array('step', 'continuous'), true)) {
					$errors[] = sprintf(
						/* translators: %s: progression mode */
						__('Unsupported attendance_bonus_mode "%s".', 'backstage-venue-manager'),
						$attendance_bonus_mode
					);
				}
			}

			$attendance_bonus_start_count = null;
			if (!empty($columns['has_attendance_bonus_start_count'])) {
				$bonus_start_raw = (string) ($row_data['attendance_bonus_start_count'] ?? '');
				if ($bonus_start_raw !== '') {
					$parsed_bonus_start = vms_event_plan_import_parse_nonnegative_int($bonus_start_raw, 0);
					if (empty($parsed_bonus_start['ok'])) {
						$errors[] = __('attendance_bonus_start_count must be a whole number that is 0 or greater.', 'backstage-venue-manager');
					} else {
						$attendance_bonus_start_count = (int) $parsed_bonus_start['value'];
					}
				}
			}

			$attendance_bonus_step_size = null;
			if (!empty($columns['has_attendance_bonus_step_size'])) {
				$bonus_step_size_raw = (string) ($row_data['attendance_bonus_step_size'] ?? '');
				if ($bonus_step_size_raw !== '') {
					$parsed_bonus_step_size = vms_event_plan_import_parse_nonnegative_int($bonus_step_size_raw, 1);
					if (empty($parsed_bonus_step_size['ok'])) {
						$errors[] = __('attendance_bonus_step_size must be a whole number that is at least 1.', 'backstage-venue-manager');
					} else {
						$attendance_bonus_step_size = (int) $parsed_bonus_step_size['value'];
					}
				}
			}

			$attendance_bonus_step_bonus = null;
			if (!empty($columns['has_attendance_bonus_step_bonus'])) {
				$bonus_step_bonus_raw = (string) ($row_data['attendance_bonus_step_bonus'] ?? '');
				if ($bonus_step_bonus_raw !== '') {
					$parsed_bonus_step_bonus = vms_event_plan_import_parse_numeric($bonus_step_bonus_raw);
					if (empty($parsed_bonus_step_bonus['ok'])) {
						$errors[] = __('attendance_bonus_step_bonus must be numeric.', 'backstage-venue-manager');
					} else {
						$attendance_bonus_step_bonus = (float) $parsed_bonus_step_bonus['value'];
						if ($attendance_bonus_step_bonus < 0) {
							$errors[] = __('attendance_bonus_step_bonus must be 0 or greater.', 'backstage-venue-manager');
						}
					}
				}
			}

			$attendance_bonus_per_ticket_rate = null;
			if (!empty($columns['has_attendance_bonus_per_ticket_rate'])) {
				$bonus_per_ticket_raw = (string) ($row_data['attendance_bonus_per_ticket_rate'] ?? '');
				if ($bonus_per_ticket_raw !== '') {
					$parsed_bonus_per_ticket = vms_event_plan_import_parse_numeric($bonus_per_ticket_raw);
					if (empty($parsed_bonus_per_ticket['ok'])) {
						$errors[] = __('attendance_bonus_per_ticket_rate must be numeric.', 'backstage-venue-manager');
					} else {
						$attendance_bonus_per_ticket_rate = (float) $parsed_bonus_per_ticket['value'];
						if ($attendance_bonus_per_ticket_rate < 0) {
							$errors[] = __('attendance_bonus_per_ticket_rate must be 0 or greater.', 'backstage-venue-manager');
						}
					}
				}
			}

			$attendance_bonus_max_bonus = null;
			if (!empty($columns['has_attendance_bonus_max_bonus'])) {
				$bonus_max_raw = (string) ($row_data['attendance_bonus_max_bonus'] ?? '');
				if ($bonus_max_raw !== '') {
					$parsed_bonus_max = vms_event_plan_import_parse_numeric($bonus_max_raw);
					if (empty($parsed_bonus_max['ok'])) {
						$errors[] = __('attendance_bonus_max_bonus must be numeric.', 'backstage-venue-manager');
					} else {
						$attendance_bonus_max_bonus = (float) $parsed_bonus_max['value'];
						if ($attendance_bonus_max_bonus < 0) {
							$errors[] = __('attendance_bonus_max_bonus must be 0 or greater.', 'backstage-venue-manager');
						}
					}
				}
			}

			$has_attendance_bonus_values = (
				$attendance_bonus_mode !== ''
				|| $attendance_bonus_start_count !== null
				|| $attendance_bonus_step_size !== null
				|| $attendance_bonus_step_bonus !== null
				|| $attendance_bonus_per_ticket_rate !== null
				|| $attendance_bonus_max_bonus !== null
			);

			if ($has_attendance_bonus_values && $comp_structure !== 'attendance_bonus') {
				$errors[] = __('Attendance bonus import columns require comp_structure=attendance_bonus.', 'backstage-venue-manager');
			}

			if ($comp_structure === 'attendance_bonus') {
				if ($flat_fee_amount === null) {
					$errors[] = __('flat_fee_amount is required for attendance_bonus comp_structure.', 'backstage-venue-manager');
				} elseif ($flat_fee_amount < 0) {
					$errors[] = __('flat_fee_amount must be 0 or greater for attendance_bonus comp_structure.', 'backstage-venue-manager');
				}

				if ($attendance_bonus_mode === '') {
					$errors[] = __('attendance_bonus_mode is required for attendance_bonus comp_structure.', 'backstage-venue-manager');
				}

				if ($attendance_bonus_start_count === null) {
					$errors[] = __('attendance_bonus_start_count is required for attendance_bonus comp_structure.', 'backstage-venue-manager');
				}

				if ($attendance_bonus_mode === 'step') {
					if ($attendance_bonus_step_size === null) {
						$errors[] = __('attendance_bonus_step_size is required for step attendance bonus mode.', 'backstage-venue-manager');
					}
					if ($attendance_bonus_step_bonus === null) {
						$errors[] = __('attendance_bonus_step_bonus is required for step attendance bonus mode.', 'backstage-venue-manager');
					}
				} elseif ($attendance_bonus_mode === 'continuous') {
					if ($attendance_bonus_per_ticket_rate === null) {
						$errors[] = __('attendance_bonus_per_ticket_rate is required for continuous attendance bonus mode.', 'backstage-venue-manager');
					}
				}
			}

			$secondary_type_raw = (string) ($row_data['secondary_vendor_type'] ?? '');
			$secondary_type = vms_event_plan_import_resolve_vendor_type($secondary_type_raw, $type_lookup);
			$secondary_type_slug = (string) ($secondary_type['slug'] ?? '');
			$secondary_type_term_id = (int) ($secondary_type['term_id'] ?? 0);
			$secondary_type_message = trim((string) ($secondary_type['message'] ?? ''));

			$secondary_names = array();
			$secondary_columns = isset($columns['secondary_vendor_columns']) && is_array($columns['secondary_vendor_columns'])
				? $columns['secondary_vendor_columns']
				: array();
			foreach ($secondary_columns as $secondary_column) {
				$name = trim((string) ($row_data[$secondary_column] ?? ''));
				if ($name !== '') {
					$secondary_names[] = $name;
				}
			}

			$secondary_vendor_ids = array();
			$secondary_vendor_create_names = array();
			$apply_secondary = !empty($columns['has_secondary_columns']);

			if (!empty($secondary_names) && $secondary_type_slug === '') {
				if ($secondary_type_raw === '') {
					$warnings[] = __('Secondary vendor names were provided without secondary_vendor_type. Names will be ignored.', 'backstage-venue-manager');
				} else {
					$warnings[] = $secondary_type_message !== '' ? $secondary_type_message : __('Secondary vendor type was not recognized. Secondary names will be ignored.', 'backstage-venue-manager');
				}
				$secondary_names = array();
			}

			if ($secondary_type_slug !== '' && $secondary_type_message !== '') {
				$warnings[] = $secondary_type_message;
			}

			if ($secondary_type_slug !== '' && !empty($secondary_names)) {
				foreach ($secondary_names as $secondary_name) {
					$secondary_match = vms_event_plan_import_resolve_vendor_id($secondary_name, $vendor_lookup, $secondary_type_slug);
					$secondary_id = (int) ($secondary_match['id'] ?? 0);
					$secondary_note = trim((string) ($secondary_match['message'] ?? ''));
					if ($secondary_note !== '') {
						$warnings[] = $secondary_note;
					}

					if ($secondary_id > 0) {
						$secondary_vendor_ids[] = $secondary_id;
						continue;
					}

					if ($auto_create_vendors) {
						$secondary_vendor_create_names[] = $secondary_name;
						$warnings[] = sprintf(
							/* translators: %s: vendor name */
							__('Secondary vendor "%s" will be auto-created during commit.', 'backstage-venue-manager'),
							$secondary_name
						);
					} else {
						$warnings[] = sprintf(
							/* translators: %s: vendor name */
							__('Secondary vendor "%s" was not found and will be skipped.', 'backstage-venue-manager'),
							$secondary_name
						);
					}
				}
			}

			$secondary_vendor_ids = array_values(array_unique(array_filter(array_map('absint', $secondary_vendor_ids), static function ($id): bool {
				return $id > 0;
			})));
			$secondary_vendor_create_names = array_values(array_unique(array_filter(array_map('trim', $secondary_vendor_create_names), static function ($name): bool {
				return $name !== '';
			})));

			if (!empty($errors)) {
				$action = 'error';
				$messages = array_merge($messages, $errors);
			} elseif ($action !== 'skip') {
				$action = ($plan_id > 0) ? 'update' : 'create';
				if ($plan_id > 0 && $is_locked && $allow_update_locked) {
					$warnings[] = sprintf(
						/* translators: %s: status */
						__('Existing plan is %s and will be updated because override is enabled.', 'backstage-venue-manager'),
						$current_status
					);
				}
			}

			if (!empty($warnings)) {
				$summary['warnings'] += 1;
				$messages = array_merge($messages, $warnings);
			}

			if ($action === 'create') {
				$summary['create'] += 1;
			} elseif ($action === 'update') {
				$summary['update'] += 1;
			} elseif ($action === 'skip') {
				$summary['skip'] += 1;
			} else {
				$summary['errors'] += 1;
			}

			$report_row = array(
				'row_number' => $row_number,
				'event_key' => $event_key,
				'plan_id' => $plan_id,
				'action' => $action,
				'messages' => $messages,
			);
			$report_rows[] = $report_row;

			$rows_for_commit[] = array(
				'row_number' => $row_number,
				'preview_action' => $action,
				'event_key' => $event_key,
				'event_date' => $event_date,
				'start_time' => $start_time,
				'end_time' => $end_time,
				'venue_name' => $venue_name,
				'venue_id' => $venue_id,
				'event_title' => $event_title,
				'agenda_text' => $agenda_text,
				'existing_plan_id' => $plan_id,
				'existing_plan_status' => $current_status,
				'primary_vendor_name' => $primary_vendor_name,
				'primary_vendor_id' => $primary_vendor_id,
				'create_primary_vendor' => $create_primary_vendor,
				'comp_structure' => $comp_structure,
				'flat_fee_amount' => $flat_fee_amount,
				'door_split_percent' => $door_split_percent,
				'attendance_bonus_mode' => $attendance_bonus_mode,
				'attendance_bonus_start_count' => $attendance_bonus_start_count,
				'attendance_bonus_step_size' => $attendance_bonus_step_size,
				'attendance_bonus_step_bonus' => $attendance_bonus_step_bonus,
				'attendance_bonus_per_ticket_rate' => $attendance_bonus_per_ticket_rate,
				'attendance_bonus_max_bonus' => $attendance_bonus_max_bonus,
				'secondary_vendor_type_slug' => $secondary_type_slug,
				'secondary_vendor_type_term_id' => $secondary_type_term_id,
				'secondary_vendor_ids' => $secondary_vendor_ids,
				'secondary_vendor_create_names' => $secondary_vendor_create_names,
				'warnings' => $warnings,
				'errors' => $errors,
			);
		}
		fclose($fh);

		$rows_json_file = vms_event_plan_import_prepare_generated_path('json', $token, 'rows');
		if (is_wp_error($rows_json_file)) {
			return $rows_json_file;
		}
		$report_csv_file = vms_event_plan_import_prepare_generated_path('csv', $token, 'preview-report');
		if (is_wp_error($report_csv_file)) {
			return $report_csv_file;
		}

		$rows_json_path = (string) ($rows_json_file['path'] ?? '');
		$rows_json_storage_key = (string) ($rows_json_file['storage_key'] ?? '');
		$report_csv_path = (string) ($report_csv_file['path'] ?? '');
		$report_csv_storage_key = (string) ($report_csv_file['storage_key'] ?? '');
		if ($rows_json_path === '' || $rows_json_storage_key === '' || $report_csv_path === '' || $report_csv_storage_key === '') {
			return new WP_Error('report_csv_write_failed', __('Could not prepare preview files.', 'backstage-venue-manager'));
		}

		$json_payload = array(
			'columns' => $columns,
			'rows' => $rows_for_commit,
		);
		$json_written = file_put_contents($rows_json_path, wp_json_encode($json_payload, JSON_PRETTY_PRINT));
		if ($json_written === false) {
			vms_event_plan_import_delete_stored_file($rows_json_storage_key);
			vms_event_plan_import_delete_stored_file($report_csv_storage_key);
			return new WP_Error('rows_json_write_failed', __('Could not write preview row cache.', 'backstage-venue-manager'));
		}

		$report_fh = fopen($report_csv_path, 'wb');
		if (!is_resource($report_fh)) {
			vms_event_plan_import_delete_stored_file($rows_json_storage_key);
			vms_event_plan_import_delete_stored_file($report_csv_storage_key);
			return new WP_Error('report_csv_write_failed', __('Could not write preview report CSV.', 'backstage-venue-manager'));
		}
		fputcsv($report_fh, array('row_number', 'event_key', 'plan_id', 'action', 'messages'));
		foreach ($report_rows as $report_row) {
			vms_event_plan_import_report_row_to_csv($report_fh, $report_row);
		}
		fclose($report_fh);

		$source_hash = sha1_file($csv_path);
		if (!is_string($source_hash) || $source_hash === '') {
			$source_hash = sha1($token . '|' . filesize($csv_path));
		}

		return array(
			'token' => $token,
			'user_id' => (int) get_current_user_id(),
			'created_at' => time(),
			'source_csv_name' => sanitize_file_name($source_name),
			'source_csv_storage_key' => $source_storage_key !== '' ? $source_storage_key : '',
			'source_file_hash' => $source_hash,
			'rows_json_storage_key' => $rows_json_storage_key,
			'report_csv_storage_key' => $report_csv_storage_key,
			'options' => array(
				'auto_create_missing_vendors' => $auto_create_vendors ? 1 : 0,
				'allow_update_locked_plans' => $allow_update_locked ? 1 : 0,
			),
			'columns' => $columns,
			'summary' => $summary,
			'report_rows_sample' => array_slice($report_rows, 0, 75),
		);
	}
}

if (!function_exists('vms_event_plan_import_read_rows_json')) {
	/**
	 * @return array<string,mixed>|WP_Error
	 */
	function vms_event_plan_import_read_rows_json(string $rows_json_reference)
	{
		$rows_json_path = vms_event_plan_import_storage_path($rows_json_reference);
		if ($rows_json_path === '' || !file_exists($rows_json_path)) {
			return new WP_Error('rows_json_missing', __('Preview rows cache is missing. Please run Preview again.', 'backstage-venue-manager'));
		}
		if (!vms_event_plan_import_path_is_safe($rows_json_path)) {
			return new WP_Error('rows_json_unsafe', __('Preview rows cache path is invalid.', 'backstage-venue-manager'));
		}

		$raw = file_get_contents($rows_json_path);
		if (!is_string($raw) || $raw === '') {
			return new WP_Error('rows_json_empty', __('Preview rows cache is empty.', 'backstage-venue-manager'));
		}

		$decoded = json_decode($raw, true);
		if (!is_array($decoded)) {
			return new WP_Error('rows_json_invalid', __('Preview rows cache is not valid JSON.', 'backstage-venue-manager'));
		}

		return $decoded;
	}
}

if (!function_exists('vms_event_plan_import_get_audit_runs')) {
	/**
	 * @return array<int,array<string,mixed>>
	 */
	function vms_event_plan_import_get_audit_runs(): array
	{
		$raw = get_option(vms_event_plan_import_audit_option_key(), array());
		if (!is_array($raw)) {
			return array();
		}

		$runs = array();
		foreach ($raw as $run) {
			if (!is_array($run)) {
				continue;
			}
			$runs[] = $run;
		}
		return $runs;
	}
}

if (!function_exists('vms_event_plan_import_save_audit_runs')) {
	/**
	 * @param array<int,array<string,mixed>> $runs
	 */
	function vms_event_plan_import_save_audit_runs(array $runs): void
	{
		update_option(vms_event_plan_import_audit_option_key(), array_values($runs), false);
	}
}

if (!function_exists('vms_event_plan_import_append_audit_run')) {
	/**
	 * @param array<string,mixed> $run
	 */
	function vms_event_plan_import_append_audit_run(array $run): void
	{
		$runs = vms_event_plan_import_get_audit_runs();
		array_unshift($runs, $run);
		$runs = array_slice($runs, 0, 10);
		vms_event_plan_import_save_audit_runs($runs);
	}
}

if (!function_exists('vms_event_plan_import_latest_revertible_run')) {
	/**
	 * @return array<string,mixed>
	 */
	function vms_event_plan_import_latest_revertible_run(): array
	{
		$runs = vms_event_plan_import_get_audit_runs();
		foreach ($runs as $run) {
			$snapshot = trim((string) ($run['snapshot_storage_key'] ?? ($run['snapshot_path'] ?? '')));
			$reverted_at = trim((string) ($run['reverted_at'] ?? ''));
			if ($snapshot !== '' && $reverted_at === '') {
				return $run;
			}
		}
		return array();
	}
}

if (!function_exists('vms_event_plan_import_mark_run_reverted')) {
	/**
	 * @param array<string,mixed> $update
	 */
	function vms_event_plan_import_mark_run_reverted(string $run_id, array $update): void
	{
		$run_id = trim($run_id);
		if ($run_id === '') {
			return;
		}

		$runs = vms_event_plan_import_get_audit_runs();
		$changed = false;
		foreach ($runs as $idx => $run) {
			if (!is_array($run)) {
				continue;
			}
			if ((string) ($run['run_id'] ?? '') !== $run_id) {
				continue;
			}
			$runs[$idx] = array_merge($run, $update);
			$changed = true;
			break;
		}

		if ($changed) {
			vms_event_plan_import_save_audit_runs($runs);
		}
	}
}

if (!function_exists('vms_event_plan_import_snapshot_meta_keys')) {
	/**
	 * @return string[]
	 */
	function vms_event_plan_import_snapshot_meta_keys(): array
	{
		return array(
			vms_event_plan_import_meta_key_import_key(),
			vms_event_plan_import_meta_key('date', '_vms_event_date'),
			'_vms_start_time',
			'_vms_end_time',
			vms_event_plan_import_meta_key('venue_id', '_vms_venue_id'),
			'_vms_agenda_text',
			vms_event_plan_import_meta_key('band_vendor_id', '_vms_band_vendor_id'),
			vms_event_plan_import_meta_key('comp_structure', '_vms_comp_structure'),
			vms_event_plan_import_meta_key('flat_fee_amount', '_vms_flat_fee_amount'),
			vms_event_plan_import_meta_key('door_split_percent', '_vms_door_split_percent'),
			vms_event_plan_import_meta_key('attendance_bonus_mode', '_vms_attendance_bonus_mode'),
			vms_event_plan_import_meta_key('attendance_bonus_start_count', '_vms_attendance_bonus_start_count'),
			vms_event_plan_import_meta_key('attendance_bonus_step_size', '_vms_attendance_bonus_step_size'),
			vms_event_plan_import_meta_key('attendance_bonus_step_bonus', '_vms_attendance_bonus_step_bonus'),
			vms_event_plan_import_meta_key('attendance_bonus_per_ticket_rate', '_vms_attendance_bonus_per_ticket_rate'),
			vms_event_plan_import_meta_key('attendance_bonus_max_bonus', '_vms_attendance_bonus_max_bonus'),
			vms_event_plan_import_meta_key('secondary_vendor_type', '_vms_secondary_vendor_type'),
			vms_event_plan_import_meta_key('secondary_vendor_ids', '_vms_secondary_vendor_ids'),
			vms_event_plan_import_meta_key('secondary_vendor_id', '_vms_secondary_vendor_id'),
			vms_event_plan_import_meta_key('secondary_vendor_unqualified', '_vms_secondary_vendor_unqualified'),
			vms_event_plan_import_meta_key('secondary_vendor_unqualified_ids', '_vms_secondary_vendor_unqualified_ids'),
		);
	}
}

if (!function_exists('vms_event_plan_import_capture_snapshot')) {
	/**
	 * @return array<string,mixed>
	 */
	function vms_event_plan_import_capture_snapshot(int $plan_id): array
	{
		$plan_id = absint($plan_id);
		$meta = array();
		$multi_key = vms_event_plan_import_meta_key('secondary_vendor_id', '_vms_secondary_vendor_id');

		foreach (vms_event_plan_import_snapshot_meta_keys() as $key) {
			$key = (string) $key;
			if ($key === '') {
				continue;
			}

			if ($key === $multi_key) {
				$values = get_post_meta($plan_id, $key, false);
				$exists = !empty($values);
				$meta[$key] = array(
					'exists' => $exists ? 1 : 0,
					'multi' => 1,
					'value' => is_array($values) ? $values : array(),
				);
				continue;
			}

			$exists = function_exists('metadata_exists') ? metadata_exists('post', $plan_id, $key) : false;
			$value = get_post_meta($plan_id, $key, true);
			$meta[$key] = array(
				'exists' => $exists ? 1 : 0,
				'multi' => 0,
				'value' => $value,
			);
		}

		return array(
			'plan_id' => $plan_id,
			'post_title' => vms_event_plan_import_raw_post_title($plan_id),
			'post_content' => (string) get_post_field('post_content', $plan_id),
			'meta' => $meta,
		);
	}
}

if (!function_exists('vms_event_plan_import_restore_snapshot_entry')) {
	/**
	 * @param array<string,mixed> $entry
	 */
	function vms_event_plan_import_restore_snapshot_entry(array $entry): bool
	{
		$plan_id = absint($entry['plan_id'] ?? 0);
		if ($plan_id <= 0) {
			return false;
		}

		$post = get_post($plan_id);
		if (!($post instanceof WP_Post) || $post->post_type !== 'vms_event_plan') {
			return false;
		}

		$title = isset($entry['post_title']) ? (string) $entry['post_title'] : '';
		$content = isset($entry['post_content']) ? (string) $entry['post_content'] : '';
		wp_update_post(array(
			'ID' => $plan_id,
			'post_title' => $title,
			'post_content' => $content,
		));

		$meta = isset($entry['meta']) && is_array($entry['meta']) ? $entry['meta'] : array();
		foreach ($meta as $key => $row) {
			$key = (string) $key;
			if ($key === '' || !is_array($row)) {
				continue;
			}

			$exists = !empty($row['exists']);
			$is_multi = !empty($row['multi']);
			$value = $row['value'] ?? null;

			if ($is_multi) {
				delete_post_meta($plan_id, $key);
				if ($exists && is_array($value)) {
					foreach ($value as $item) {
						add_post_meta($plan_id, $key, $item, false);
					}
				}
				continue;
			}

			if (!$exists) {
				delete_post_meta($plan_id, $key);
				continue;
			}

			update_post_meta($plan_id, $key, $value);
		}

		return true;
	}
}

if (!function_exists('vms_event_plan_import_resolve_or_create_vendor')) {
	/**
	 * @param array<string,mixed> $vendor_lookup
	 * @return array{id:int,message:string}
	 */
	function vms_event_plan_import_resolve_or_create_vendor(string $vendor_name, array &$vendor_lookup, bool $auto_create, string $type_slug = '', int $type_term_id = 0): array
	{
		$vendor_name = trim($vendor_name);
		if ($vendor_name === '') {
			return array('id' => 0, 'message' => __('Vendor name is blank.', 'backstage-venue-manager'));
		}

		$resolved = vms_event_plan_import_resolve_vendor_id($vendor_name, $vendor_lookup, $type_slug);
		$vendor_id = (int) ($resolved['id'] ?? 0);
		$note = trim((string) ($resolved['message'] ?? ''));
		if ($vendor_id > 0) {
			return array('id' => $vendor_id, 'message' => $note);
		}
		if (!$auto_create) {
			/* translators: %s: human-readable value used in this message. */
			return array('id' => 0, 'message' => $note !== '' ? $note : sprintf(__('Vendor "%s" was not found.', 'backstage-venue-manager'), $vendor_name));
		}

		$new_vendor_id = wp_insert_post(array(
			'post_type' => 'vms_vendor',
			'post_status' => 'publish',
			'post_title' => $vendor_name,
		), true);
		if (is_wp_error($new_vendor_id)) {
			return array(
				'id' => 0,
				'message' => sprintf(
					/* translators: %1$s: vendor name, %2$s: error */
					__('Could not auto-create vendor "%1$s": %2$s', 'backstage-venue-manager'),
					$vendor_name,
					$new_vendor_id->get_error_message()
				),
			);
		}

		$new_vendor_id = absint($new_vendor_id);
		if ($new_vendor_id <= 0) {
			return array(
				'id' => 0,
				'message' => sprintf(
					/* translators: %s: vendor name */
					__('Could not auto-create vendor "%s".', 'backstage-venue-manager'),
					$vendor_name
				),
			);
		}

		if ($type_slug !== '' && $type_term_id > 0 && function_exists('wp_set_object_terms')) {
			wp_set_object_terms($new_vendor_id, array($type_term_id), 'vms_vendor_type', true);
		}

		$vendor_lookup = vms_event_plan_import_build_post_title_lookup('vms_vendor');
		return array(
			'id' => $new_vendor_id,
			'message' => sprintf(
				/* translators: %s: vendor name */
				__('Auto-created vendor "%s".', 'backstage-venue-manager'),
				$vendor_name
			),
		);
	}
}

if (!function_exists('vms_event_plan_import_update_secondary_meta')) {
	/**
	 * @param int[] $secondary_ids
	 */
	function vms_event_plan_import_update_secondary_meta(int $plan_id, string $type_slug, array $secondary_ids, int $band_vendor_id = 0): void
	{
		$secondary_ids = array_values(array_unique(array_filter(array_map('absint', $secondary_ids), static function ($id): bool {
			return $id > 0;
		})));

		if ($band_vendor_id > 0) {
			$secondary_ids = array_values(array_filter($secondary_ids, static function ($id) use ($band_vendor_id): bool {
				return (int) $id !== (int) $band_vendor_id;
			}));
		}

		$k_secondary_ids = vms_event_plan_import_meta_key('secondary_vendor_ids', '_vms_secondary_vendor_ids');
		$k_secondary_idx = vms_event_plan_import_meta_key('secondary_vendor_id', '_vms_secondary_vendor_id');
		$k_secondary_type = vms_event_plan_import_meta_key('secondary_vendor_type', '_vms_secondary_vendor_type');
		$k_secondary_unq = vms_event_plan_import_meta_key('secondary_vendor_unqualified', '_vms_secondary_vendor_unqualified');
		$k_secondary_unq_ids = vms_event_plan_import_meta_key('secondary_vendor_unqualified_ids', '_vms_secondary_vendor_unqualified_ids');

		$type_slug = function_exists('vms_vendor_type_normalize_slug')
			? vms_vendor_type_normalize_slug($type_slug)
			: sanitize_title($type_slug);

		if (function_exists('vms_event_plan_write_secondary_vendor_assignments')) {
			$current_assignments = function_exists('vms_event_plan_get_secondary_vendor_assignments')
				? (array) vms_event_plan_get_secondary_vendor_assignments($plan_id, array(
					'primary_vendor_id' => $band_vendor_id,
				))
				: array();

			if ($type_slug !== '') {
				$current_assignments[$type_slug] = array(
					'mode' => (string) ($current_assignments[$type_slug]['mode'] ?? (function_exists('vms_event_plan_secondary_vendor_default_mode') ? vms_event_plan_secondary_vendor_default_mode($type_slug) : 'standard')),
					'slot_limit' => array_key_exists($type_slug, $current_assignments)
						? ($current_assignments[$type_slug]['slot_limit'] ?? null)
						: (function_exists('vms_event_plan_secondary_vendor_default_slot_limit')
							? vms_event_plan_secondary_vendor_default_slot_limit($type_slug)
							: null),
					'vendor_ids' => $secondary_ids,
				);
			} elseif (empty($secondary_ids) && empty($current_assignments)) {
				$current_assignments = array();
			} else {
				return;
			}

			vms_event_plan_write_secondary_vendor_assignments($plan_id, $current_assignments);
			return;
		}

		if ($type_slug !== '') {
			update_post_meta($plan_id, $k_secondary_type, $type_slug);
		} else {
			delete_post_meta($plan_id, $k_secondary_type);
		}

		if (!empty($secondary_ids)) {
			update_post_meta($plan_id, $k_secondary_ids, $secondary_ids);
		} else {
			delete_post_meta($plan_id, $k_secondary_ids);
		}

		delete_post_meta($plan_id, $k_secondary_idx);
		foreach ($secondary_ids as $secondary_id) {
			add_post_meta($plan_id, $k_secondary_idx, (int) $secondary_id, false);
		}

		$unqualified = array();
		if (function_exists('vms_secondary_vendor_is_qualified')) {
			foreach ($secondary_ids as $secondary_id) {
				$qualified = vms_secondary_vendor_is_qualified((int) $secondary_id, array(
					'context' => 'event_plan_csv_import',
					'plan_id' => $plan_id,
					'type_slug' => $type_slug,
				));
				if (!$qualified) {
					$unqualified[] = (int) $secondary_id;
				}
			}
		}

		if (!empty($unqualified)) {
			update_post_meta($plan_id, $k_secondary_unq, '1');
			update_post_meta($plan_id, $k_secondary_unq_ids, $unqualified);
		} else {
			delete_post_meta($plan_id, $k_secondary_unq);
			delete_post_meta($plan_id, $k_secondary_unq_ids);
		}
	}
}

if (!function_exists('vms_event_plan_import_ticketing_config_missing')) {
	function vms_event_plan_import_ticketing_config_missing(int $plan_id): bool
	{
		$key = function_exists('vms_ticketing_v2_k')
			? (string) vms_ticketing_v2_k('config')
			: vms_event_plan_import_meta_key('ticketing_config_v2', '_vms_ticketing_config_v2');

		if ($key === '') {
			$key = '_vms_ticketing_config_v2';
		}

		$exists = function_exists('metadata_exists') ? metadata_exists('post', $plan_id, $key) : false;
		if (!$exists) {
			return true;
		}

		$raw = get_post_meta($plan_id, $key, true);
		if ($raw === '' || $raw === null) {
			return true;
		}
		if (is_array($raw) && empty($raw)) {
			return true;
		}

		return false;
	}
}

if (!function_exists('vms_event_plan_import_apply_default_template_if_needed')) {
	/**
	 * @return array{applied:bool,warning:string}
	 */
	function vms_event_plan_import_apply_default_template_if_needed(int $plan_id): array
	{
		if (!vms_event_plan_import_ticketing_config_missing($plan_id)) {
			return array('applied' => false, 'warning' => '');
		}

		if (!function_exists('vms_ticketing_v2_get_default_template_id') || !function_exists('vms_ticketing_v2_templates_apply_to_plan')) {
			return array(
				'applied' => false,
				'warning' => __('Ticketing template not applied (template helpers unavailable).', 'backstage-venue-manager'),
			);
		}

		$template_id = (string) vms_ticketing_v2_get_default_template_id();
		if ($template_id === '') {
			return array(
				'applied' => false,
				'warning' => __('Ticketing template not applied (no default template is set).', 'backstage-venue-manager'),
			);
		}

		$result = vms_ticketing_v2_templates_apply_to_plan($plan_id, $template_id);
		if (empty($result['ok'])) {
			$message = sanitize_text_field((string) ($result['message'] ?? 'template_apply_failed'));
			return array(
				'applied' => false,
				'warning' => sprintf(
					/* translators: %s: error key */
					__('Ticketing template not applied (%s).', 'backstage-venue-manager'),
					$message
				),
			);
		}

		return array('applied' => true, 'warning' => '');
	}
}

if (!function_exists('vms_event_plan_import_find_plan_id_by_key')) {
	/**
	 * @param array<string,int> $plan_lookup
	 */
	function vms_event_plan_import_find_plan_id_by_key(string $event_key, array &$plan_lookup): int
	{
		$event_key = trim($event_key);
		if ($event_key === '') {
			return 0;
		}

		if (isset($plan_lookup[$event_key])) {
			return (int) $plan_lookup[$event_key];
		}

		$meta_key = vms_event_plan_import_meta_key_import_key();
		$found = get_posts(array(
			'post_type' => 'vms_event_plan',
			'post_status' => array('publish', 'private', 'draft', 'pending', 'future'),
			'posts_per_page' => 1,
			'fields' => 'ids',
			'no_found_rows' => true,
			'meta_query' => array(
				array(
					'key' => $meta_key,
					'value' => $event_key,
					'compare' => '=',
				),
			),
		));
		if (!empty($found)) {
			$plan_id = absint($found[0]);
			if ($plan_id > 0) {
				$plan_lookup[$event_key] = $plan_id;
				return $plan_id;
			}
		}

		return 0;
	}
}

if (!function_exists('vms_event_plan_import_run_commit')) {
	/**
	 * @param array<string,mixed> $preview_payload
	 * @param array<string,mixed> $commit_options
	 * @return array<string,mixed>|WP_Error
	 */
	function vms_event_plan_import_run_commit(array $preview_payload, array $commit_options = array())
	{
		$rows_json_reference = trim((string) ($preview_payload['rows_json_storage_key'] ?? ($preview_payload['rows_json_path'] ?? '')));
		$rows_payload = vms_event_plan_import_read_rows_json($rows_json_reference);
		if (is_wp_error($rows_payload)) {
			return $rows_payload;
		}

		$rows = isset($rows_payload['rows']) && is_array($rows_payload['rows']) ? $rows_payload['rows'] : array();
		$columns = isset($rows_payload['columns']) && is_array($rows_payload['columns']) ? $rows_payload['columns'] : array();
		$options = isset($preview_payload['options']) && is_array($preview_payload['options']) ? $preview_payload['options'] : array();

		$auto_create_vendors = !empty($options['auto_create_missing_vendors']);
		$allow_update_locked = !empty($options['allow_update_locked_plans']);
		$commit_scope = sanitize_key((string) ($commit_options['scope'] ?? 'all'));
		if (!in_array($commit_scope, array('all', 'selected'), true)) {
			$commit_scope = 'all';
		}
		$selected_row_numbers = isset($commit_options['selected_rows']) && is_array($commit_options['selected_rows'])
			? array_values(array_unique(array_filter(array_map('absint', $commit_options['selected_rows']), static function ($row_number): bool {
				return $row_number > 0;
			})))
			: array();
		$selected_row_lookup = array_fill_keys($selected_row_numbers, true);

		if ($commit_scope === 'selected' && empty($selected_row_lookup)) {
			return new WP_Error('no_rows_selected', __('No rows were selected for commit.', 'backstage-venue-manager'));
		}

		$vendor_lookup = vms_event_plan_import_build_post_title_lookup('vms_vendor');
		$type_lookup = vms_event_plan_import_build_vendor_type_lookup();
		$plan_lookup = vms_event_plan_import_find_existing_plan_lookup();

		$summary = array(
			'total_rows' => count($rows),
			'commit_scope' => $commit_scope,
			'selected_rows_requested' => ($commit_scope === 'selected') ? count($selected_row_lookup) : 0,
			'selected_rows_committed' => 0,
			'create' => 0,
			'update' => 0,
			'skip' => 0,
			'errors' => 0,
			'warnings' => 0,
			'template_applied' => 0,
			'template_not_applied' => 0,
		);

		$created_plan_ids = array();
		$updated_plan_ids = array();
		$before_snapshots = array();

		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}

			$row_number = absint($row['row_number'] ?? 0);
			$preview_action = sanitize_key((string) ($row['preview_action'] ?? ''));
			if ($preview_action === 'error') {
				$summary['errors'] += 1;
				continue;
			}
			if ($preview_action === 'skip') {
				$summary['skip'] += 1;
				continue;
			}
			if (!in_array($preview_action, array('create', 'update'), true)) {
				$summary['skip'] += 1;
				continue;
			}

			if ($commit_scope === 'selected') {
				if ($row_number <= 0 || !isset($selected_row_lookup[$row_number])) {
					$summary['skip'] += 1;
					continue;
				}
			}

			$event_key = trim((string) ($row['event_key'] ?? ''));
			if ($event_key === '') {
				$summary['errors'] += 1;
				continue;
			}

			$plan_id = vms_event_plan_import_find_plan_id_by_key($event_key, $plan_lookup);
			$is_update = ($plan_id > 0);
			$current_status = '';
			if ($is_update) {
				$current_status = function_exists('vms_event_plan_get_status')
					? (string) vms_event_plan_get_status($plan_id, 'event_list')
					: sanitize_key((string) get_post_meta($plan_id, vms_event_plan_import_meta_key('status', '_vms_event_plan_status'), true));
			}

			if ($is_update && in_array($current_status, array('published', 'cancelled'), true) && !$allow_update_locked) {
				$summary['skip'] += 1;
				continue;
			}

			$venue_id = absint($row['venue_id'] ?? 0);
			if ($venue_id <= 0) {
				$summary['errors'] += 1;
				continue;
			}

			$primary_vendor_name = trim((string) ($row['primary_vendor_name'] ?? ''));
			$primary_vendor_id = absint($row['primary_vendor_id'] ?? 0);
			if ($primary_vendor_id <= 0 || !get_post($primary_vendor_id)) {
				$primary_resolution = vms_event_plan_import_resolve_or_create_vendor(
					$primary_vendor_name,
					$vendor_lookup,
					$auto_create_vendors
				);
				$primary_vendor_id = (int) ($primary_resolution['id'] ?? 0);
				$primary_note = trim((string) ($primary_resolution['message'] ?? ''));
				if ($primary_note !== '') {
					$summary['warnings'] += 1;
				}
			}

			if ($primary_vendor_id <= 0) {
				$summary['errors'] += 1;
				continue;
			}

			$event_title = trim((string) ($row['event_title'] ?? ''));
			if ($event_title === '') {
				$event_title = $primary_vendor_name !== '' ? $primary_vendor_name : __('Untitled Event Plan', 'backstage-venue-manager');
			}

			$apply_agenda = !empty($columns['has_agenda_text']);
			$agenda_text = (string) ($row['agenda_text'] ?? '');
			$event_date = (string) ($row['event_date'] ?? '');
			$start_time = (string) ($row['start_time'] ?? '19:00');
			$end_time = (string) ($row['end_time'] ?? '22:00');

			if ($is_update) {
				$before_snapshots[] = vms_event_plan_import_capture_snapshot($plan_id);

				$post_update = array(
					'ID' => $plan_id,
					'post_title' => $event_title,
				);
				if ($apply_agenda) {
					$post_update['post_content'] = $agenda_text;
				}
				wp_update_post($post_update);
			} else {
				$post_insert = array(
					'post_type' => 'vms_event_plan',
					'post_status' => 'draft',
					'post_title' => $event_title,
					'post_content' => $apply_agenda ? $agenda_text : '',
				);
				$new_plan_id = wp_insert_post($post_insert, true);
				if (is_wp_error($new_plan_id) || absint($new_plan_id) <= 0) {
					$summary['errors'] += 1;
					continue;
				}
				$plan_id = absint($new_plan_id);
				$plan_lookup[$event_key] = $plan_id;
				update_post_meta($plan_id, vms_event_plan_import_meta_key('status', '_vms_event_plan_status'), 'draft');
			}

			update_post_meta($plan_id, vms_event_plan_import_meta_key_import_key(), $event_key);
			update_post_meta($plan_id, vms_event_plan_import_meta_key('date', '_vms_event_date'), $event_date);
			update_post_meta($plan_id, '_vms_start_time', $start_time);
			update_post_meta($plan_id, '_vms_end_time', $end_time);
			update_post_meta($plan_id, vms_event_plan_import_meta_key('venue_id', '_vms_venue_id'), $venue_id);
			update_post_meta($plan_id, vms_event_plan_import_meta_key('band_vendor_id', '_vms_band_vendor_id'), $primary_vendor_id);

			if ($apply_agenda) {
				if (trim($agenda_text) === '') {
					delete_post_meta($plan_id, '_vms_agenda_text');
				} else {
					update_post_meta($plan_id, '_vms_agenda_text', wp_kses_post($agenda_text));
				}
			}

			$k_comp_structure = vms_event_plan_import_meta_key('comp_structure', '_vms_comp_structure');
			$k_flat_fee_amount = vms_event_plan_import_meta_key('flat_fee_amount', '_vms_flat_fee_amount');
			$k_door_split_percent = vms_event_plan_import_meta_key('door_split_percent', '_vms_door_split_percent');
			$k_bonus_mode = vms_event_plan_import_meta_key('attendance_bonus_mode', '_vms_attendance_bonus_mode');
			$k_bonus_start = vms_event_plan_import_meta_key('attendance_bonus_start_count', '_vms_attendance_bonus_start_count');
			$k_bonus_step_size = vms_event_plan_import_meta_key('attendance_bonus_step_size', '_vms_attendance_bonus_step_size');
			$k_bonus_step_bonus = vms_event_plan_import_meta_key('attendance_bonus_step_bonus', '_vms_attendance_bonus_step_bonus');
			$k_bonus_per_ticket = vms_event_plan_import_meta_key('attendance_bonus_per_ticket_rate', '_vms_attendance_bonus_per_ticket_rate');
			$k_bonus_max = vms_event_plan_import_meta_key('attendance_bonus_max_bonus', '_vms_attendance_bonus_max_bonus');

			$comp_structure_for_row = !empty($columns['has_comp_structure'])
				? sanitize_key((string) ($row['comp_structure'] ?? ''))
				: sanitize_key((string) get_post_meta($plan_id, $k_comp_structure, true));

			if (!empty($columns['has_comp_structure'])) {
				if ($comp_structure_for_row === '') {
					delete_post_meta($plan_id, $k_comp_structure);
				} else {
					update_post_meta($plan_id, $k_comp_structure, $comp_structure_for_row);
				}
			}
			if (!empty($columns['has_flat_fee_amount'])) {
				$flat_fee_amount = $row['flat_fee_amount'] ?? null;
				if ($flat_fee_amount === null || $flat_fee_amount === '') {
					delete_post_meta($plan_id, $k_flat_fee_amount);
				} else {
					update_post_meta($plan_id, $k_flat_fee_amount, (float) $flat_fee_amount);
				}
			}
			if (!empty($columns['has_door_split_percent'])) {
				$door_split_percent = $row['door_split_percent'] ?? null;
				if ($door_split_percent === null || $door_split_percent === '') {
					delete_post_meta($plan_id, $k_door_split_percent);
				} else {
					update_post_meta($plan_id, $k_door_split_percent, (float) $door_split_percent);
				}
			}

			if (!empty($columns['has_attendance_bonus_columns'])) {
				$bonus_mode = sanitize_key((string) ($row['attendance_bonus_mode'] ?? ''));
				$bonus_start = $row['attendance_bonus_start_count'] ?? null;
				$bonus_step_size = $row['attendance_bonus_step_size'] ?? null;
				$bonus_step_bonus = $row['attendance_bonus_step_bonus'] ?? null;
				$bonus_per_ticket = $row['attendance_bonus_per_ticket_rate'] ?? null;
				$bonus_max = $row['attendance_bonus_max_bonus'] ?? null;

				if ($bonus_mode === '') {
					delete_post_meta($plan_id, $k_bonus_mode);
				} else {
					update_post_meta($plan_id, $k_bonus_mode, $bonus_mode);
				}

				if ($bonus_start === null || $bonus_start === '') {
					delete_post_meta($plan_id, $k_bonus_start);
				} else {
					update_post_meta($plan_id, $k_bonus_start, (int) $bonus_start);
				}

				if ($bonus_max === null || $bonus_max === '') {
					delete_post_meta($plan_id, $k_bonus_max);
				} else {
					update_post_meta($plan_id, $k_bonus_max, (float) $bonus_max);
				}

				if ($bonus_mode === 'step') {
					if ($bonus_step_size === null || $bonus_step_size === '') {
						delete_post_meta($plan_id, $k_bonus_step_size);
					} else {
						update_post_meta($plan_id, $k_bonus_step_size, (int) $bonus_step_size);
					}

					if ($bonus_step_bonus === null || $bonus_step_bonus === '') {
						delete_post_meta($plan_id, $k_bonus_step_bonus);
					} else {
						update_post_meta($plan_id, $k_bonus_step_bonus, (float) $bonus_step_bonus);
					}

					delete_post_meta($plan_id, $k_bonus_per_ticket);
				} elseif ($bonus_mode === 'continuous') {
					if ($bonus_per_ticket === null || $bonus_per_ticket === '') {
						delete_post_meta($plan_id, $k_bonus_per_ticket);
					} else {
						update_post_meta($plan_id, $k_bonus_per_ticket, (float) $bonus_per_ticket);
					}

					delete_post_meta($plan_id, $k_bonus_step_size);
					delete_post_meta($plan_id, $k_bonus_step_bonus);
				} else {
					delete_post_meta($plan_id, $k_bonus_step_size);
					delete_post_meta($plan_id, $k_bonus_step_bonus);
					delete_post_meta($plan_id, $k_bonus_per_ticket);
				}
			}

			if ($comp_structure_for_row === 'attendance_bonus') {
				delete_post_meta($plan_id, $k_door_split_percent);
			} elseif (!empty($columns['has_comp_structure']) && $comp_structure_for_row !== 'attendance_bonus') {
				delete_post_meta($plan_id, $k_bonus_mode);
				delete_post_meta($plan_id, $k_bonus_start);
				delete_post_meta($plan_id, $k_bonus_step_size);
				delete_post_meta($plan_id, $k_bonus_step_bonus);
				delete_post_meta($plan_id, $k_bonus_per_ticket);
				delete_post_meta($plan_id, $k_bonus_max);
				if (!in_array($comp_structure_for_row, array('door_split', 'flat_fee_door_split'), true)) {
					delete_post_meta($plan_id, $k_door_split_percent);
				}
			}

			if (!empty($columns['has_secondary_columns'])) {
				$type_slug = sanitize_title((string) ($row['secondary_vendor_type_slug'] ?? ''));
				$type_term_id = absint($row['secondary_vendor_type_term_id'] ?? 0);
				if ($type_slug === '' || $type_term_id <= 0) {
					$type_raw = trim((string) ($row['secondary_vendor_type_slug'] ?? ''));
					$type_resolution = vms_event_plan_import_resolve_vendor_type($type_raw, $type_lookup);
					$type_slug = (string) ($type_resolution['slug'] ?? '');
					$type_term_id = (int) ($type_resolution['term_id'] ?? 0);
				}

				$secondary_ids = isset($row['secondary_vendor_ids']) && is_array($row['secondary_vendor_ids'])
					? array_map('absint', $row['secondary_vendor_ids'])
					: array();
				$secondary_create_names = isset($row['secondary_vendor_create_names']) && is_array($row['secondary_vendor_create_names'])
					? $row['secondary_vendor_create_names']
					: array();

				foreach ($secondary_create_names as $secondary_name) {
					$secondary_name = trim((string) $secondary_name);
					if ($secondary_name === '' || $type_slug === '' || $type_term_id <= 0) {
						continue;
					}
					$secondary_resolution = vms_event_plan_import_resolve_or_create_vendor(
						$secondary_name,
						$vendor_lookup,
						$auto_create_vendors,
						$type_slug,
						$type_term_id
					);
					$secondary_id = (int) ($secondary_resolution['id'] ?? 0);
					if ($secondary_id > 0) {
						$secondary_ids[] = $secondary_id;
					} else {
						$summary['warnings'] += 1;
					}
				}

				$secondary_ids = array_values(array_unique(array_filter(array_map('absint', $secondary_ids), static function ($id): bool {
					return $id > 0;
				})));

				if ($type_slug === '') {
					$secondary_ids = array();
				}

				vms_event_plan_import_update_secondary_meta($plan_id, $type_slug, $secondary_ids, $primary_vendor_id);
			}

			$template_result = vms_event_plan_import_apply_default_template_if_needed($plan_id);
			if (!empty($template_result['applied'])) {
				$summary['template_applied'] += 1;
			} elseif (trim((string) ($template_result['warning'] ?? '')) !== '') {
				$summary['template_not_applied'] += 1;
				$summary['warnings'] += 1;
			}

			if ($is_update) {
				$summary['update'] += 1;
				$updated_plan_ids[] = $plan_id;
			} else {
				$summary['create'] += 1;
				$created_plan_ids[] = $plan_id;
			}
			if ($commit_scope === 'selected') {
				$summary['selected_rows_committed'] += 1;
			}
		}

		$snapshot_storage_key = '';
		if (!empty($before_snapshots)) {
			$snapshot_file = vms_event_plan_import_prepare_generated_path('json', (string) ($preview_payload['token'] ?? 'preview'), 'before-snapshot');
			if (!is_wp_error($snapshot_file)) {
				$snapshot_path = (string) ($snapshot_file['path'] ?? '');
				$snapshot_storage_key = (string) ($snapshot_file['storage_key'] ?? '');
				if ($snapshot_path !== '' && $snapshot_storage_key !== '') {
					file_put_contents($snapshot_path, wp_json_encode(array(
						'created_at' => time(),
						'entries' => $before_snapshots,
					), JSON_PRETTY_PRINT));
				} else {
					$snapshot_storage_key = '';
				}
			}
		}

		$run_id = 'run_' . gmdate('Ymd_His') . '_' . substr(sha1(wp_generate_password(12, false, false) . '|' . microtime(true)), 0, 8);
		$run = array(
			'run_id' => $run_id,
			'created_at_gmt' => gmdate('c'),
			'created_at_local' => function_exists('wp_date') ? wp_date('Y-m-d H:i:s', time(), wp_timezone()) : gmdate('Y-m-d H:i:s'),
			'user_id' => (int) get_current_user_id(),
			'summary' => $summary,
			'source_file_hash' => (string) ($preview_payload['source_file_hash'] ?? ''),
			'source_csv_name' => (string) ($preview_payload['source_csv_name'] ?? ''),
			'source_csv_storage_key' => (string) ($preview_payload['source_csv_storage_key'] ?? ''),
			'preview_token' => (string) ($preview_payload['token'] ?? ''),
			'report_csv_storage_key' => (string) ($preview_payload['report_csv_storage_key'] ?? ''),
			'snapshot_storage_key' => $snapshot_storage_key,
			'commit_scope' => $commit_scope,
			'selected_rows_requested' => ($commit_scope === 'selected') ? count($selected_row_lookup) : 0,
			'selected_rows_committed' => (int) ($summary['selected_rows_committed'] ?? 0),
			'created_plan_ids' => array_values(array_unique(array_map('absint', $created_plan_ids))),
			'updated_plan_ids' => array_values(array_unique(array_map('absint', $updated_plan_ids))),
		);
		vms_event_plan_import_append_audit_run($run);

		return $run;
	}
}

if (!function_exists('vms_event_plan_import_revert_last_run')) {
	/**
	 * @return array<string,mixed>|WP_Error
	 */
	function vms_event_plan_import_revert_last_run()
	{
		$run = vms_event_plan_import_latest_revertible_run();
		if (empty($run)) {
			return new WP_Error('revert_nothing', __('No import run with a reversible snapshot was found.', 'backstage-venue-manager'));
		}

		$run_id = (string) ($run['run_id'] ?? '');
		$snapshot_path = vms_event_plan_import_storage_path((string) ($run['snapshot_storage_key'] ?? ($run['snapshot_path'] ?? '')));
		if ($snapshot_path === '' || !file_exists($snapshot_path)) {
			return new WP_Error('snapshot_missing', __('Snapshot file for the latest import is missing.', 'backstage-venue-manager'));
		}
		if (!vms_event_plan_import_path_is_safe($snapshot_path)) {
			return new WP_Error('snapshot_unsafe', __('Snapshot file path is not allowed.', 'backstage-venue-manager'));
		}

		$raw = file_get_contents($snapshot_path);
		if (!is_string($raw) || $raw === '') {
			return new WP_Error('snapshot_read_failed', __('Could not read snapshot file.', 'backstage-venue-manager'));
		}

		$decoded = json_decode($raw, true);
		if (!is_array($decoded)) {
			return new WP_Error('snapshot_invalid', __('Snapshot file is invalid JSON.', 'backstage-venue-manager'));
		}

		$entries = isset($decoded['entries']) && is_array($decoded['entries']) ? $decoded['entries'] : array();
		if (empty($entries)) {
			return new WP_Error('snapshot_empty', __('Snapshot file does not contain any restorable entries.', 'backstage-venue-manager'));
		}

		$restored = 0;
		$failed = 0;
		foreach ($entries as $entry) {
			if (!is_array($entry)) {
				continue;
			}
			$ok = vms_event_plan_import_restore_snapshot_entry($entry);
			if ($ok) {
				$restored += 1;
			} else {
				$failed += 1;
			}
		}

		vms_event_plan_import_mark_run_reverted($run_id, array(
			'reverted_at' => function_exists('wp_date') ? wp_date('Y-m-d H:i:s', time(), wp_timezone()) : gmdate('Y-m-d H:i:s'),
			'reverted_by' => (int) get_current_user_id(),
			'revert_restored' => $restored,
			'revert_failed' => $failed,
		));

		return array(
			'run_id' => $run_id,
			'restored' => $restored,
			'failed' => $failed,
		);
	}
}
