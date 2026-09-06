<?php
defined('ABSPATH') || exit;

final class VMS_Vendor_Import_Engine
{
	private const TOKEN_TTL_SECONDS = 60 * 30; // 30 minutes
	private const RAW_PREVIEW_CAP   = 500;

	public function handle_preview(?array $file)
	{
		$upload = $this->handle_upload($file);
		if (is_wp_error($upload)) {
			return $upload;
		}

		$batch_id = $this->new_batch_id();
		$parsed   = $this->parse_csv($upload['path']);
		if (is_wp_error($parsed)) {
			return $parsed;
		}

		$report = $this->run_import($parsed, $batch_id, $upload['name'], true);

		// Read-only raw CSV preview, indexed by the SAME spreadsheet row numbers the engine uses.
		$report['raw_preview'] = $this->build_raw_preview($parsed);

		// Save parsed data for commit.
		$token = $this->new_token();
		set_transient($this->token_key($token), [
			'upload'   => $upload,
			'parsed'   => $parsed,
			'batch_id' => $batch_id,
		], self::TOKEN_TTL_SECONDS);

		$report['token'] = $token;
		return $report;
	}

	public function handle_commit(string $token, array $exclude_row_numbers = [], array $overrides = [])
	{
		$payload = get_transient($this->token_key($token));
		if (!is_array($payload) || empty($payload['parsed']) || empty($payload['batch_id'])) {
			return new WP_Error('vms_vendor_import_token', 'Commit token expired or invalid. Please preview again.');
		}

		$upload   = $payload['upload'] ?? ['name' => ''];
		$batch_id = (string) $payload['batch_id'];
		$parsed   = $payload['parsed'];

		// Build exclusion set: spreadsheet row numbers (2..n) matching parsed rows _row_number.
		$exclude_set = [];
		foreach ($exclude_row_numbers as $n) {
			$n = (int) $n;
			if ($n > 0) {
				$exclude_set[$n] = true;
			}
		}

		$excluded_applied = 0;

		if (!empty($exclude_set) && isset($parsed['rows']) && is_array($parsed['rows'])) {
			$filtered = [];
			foreach ($parsed['rows'] as $r) {
				if (!is_array($r)) {
					continue;
				}

				$rn = (int) ($r['_row_number'] ?? 0);
				if ($rn > 0 && isset($exclude_set[$rn])) {
					$excluded_applied++;
					continue;
				}

				$filtered[] = $r;
			}
			$parsed['rows'] = $filtered;
		}

		
		$override_audit = ['total' => 0, 'applied' => 0, 'skipped' => 0, 'entries' => []];

		if (!empty($overrides) && is_array($overrides)) {
			$override_audit = $this->apply_overrides_to_parsed($parsed, $overrides);
			if (is_wp_error($override_audit)) {
				return $override_audit;
			}
		}

delete_transient($this->token_key($token));

		$report = $this->run_import($parsed, $batch_id, (string) ($upload['name'] ?? ''), false);

		if (is_array($report)) {
			$excluded_rows = array_keys($exclude_set);
			sort($excluded_rows);

			$report['selection'] = [
				'excluded_rows'    => $excluded_rows,
				'excluded_count'   => count($excluded_rows),
				'excluded_applied' => (int) $excluded_applied,
			];
			$report['overrides'] = $override_audit;
		}

		return $report;
	}


	private function is_sensitive_header(string $header): bool
	{
		$h = strtolower(trim($header));

		return (
			strpos($h, 'ssn') !== false ||
			strpos($h, 'ein') !== false ||
			strpos($h, 'tin') !== false ||
			strpos($h, 'tax id') !== false ||
			strpos($h, 'taxid') !== false
		);
	}

	private function apply_overrides_to_parsed(array &$parsed, array $overrides)
	{
		$headers = isset($parsed['headers']) && is_array($parsed['headers']) ? $parsed['headers'] : [];
		$rows    = isset($parsed['rows']) && is_array($parsed['rows']) ? $parsed['rows'] : [];

		$audit = ['total' => 0, 'applied' => 0, 'skipped' => 0, 'entries' => []];

		if (empty($headers) || empty($rows) || empty($overrides)) {
			return $audit;
		}

		$total_cells = 0;
		foreach ($overrides as $row_key => $cols) {
			if (!is_array($cols)) {
				continue;
			}
			foreach ($cols as $col_key => $chg) {
				$total_cells++;
			}
		}

		if ($total_cells > 500) {
			return new WP_Error('vms_vendor_import_overrides_cap', 'Too many overrides (max 500 cells). Please reduce edits and try again.');
		}

		// Hard whitelist: only allow overrides for a small set of safe canonical fields.
		// This keeps Preview edits focused and avoids unexpected column mutations.
		$allowed_list = [];
		$allowed_fields = [
			'display_name',
			'primary_email',
			'primary_phone',
			'email',
			'phone',
			'website',
			'notes',
		];

		$schema_registry = vms_dt_core_class('VMS_Vendor_Schema_Registry');
		if ($schema_registry !== '' && method_exists($schema_registry, 'resolve_fields')) {
			$resolved = $schema_registry::resolve_fields($headers);
			if (is_array($resolved)) {
				foreach ($allowed_fields as $f) {
					$h = isset($resolved[$f]) ? (string) $resolved[$f] : '';
					$h = trim($h);
					if ($h !== '' && !$this->is_sensitive_header($h)) {
						$allowed_list[] = $h;
					}
				}
			}
		}

		$allowed_list = array_values(array_unique($allowed_list));

		if (function_exists('apply_filters')) {
			$filtered = apply_filters('vms_dt_vi_override_allowed_columns', $allowed_list, $headers);
			if (is_array($filtered)) {
				$allowed_list = [];
				foreach ($filtered as $h) {
					$h = trim((string) $h);
					if ($h !== '' && !$this->is_sensitive_header($h)) {
						$allowed_list[] = $h;
					}
				}
				$allowed_list = array_values(array_unique($allowed_list));
			}
		}

		$allowed = [];
		foreach ($allowed_list as $h) {
			$allowed[(string) $h] = true;
		}

		$row_map = [];
		foreach ($rows as $idx => $r) {
			if (!is_array($r)) {
				continue;
			}
			$rn = (int) ($r['_row_number'] ?? 0);
			if ($rn > 0) {
				$row_map[(string) $rn] = (int) $idx;
			}
		}

		$entries = [];
		$applied = 0;
		$skipped = 0;

		foreach ($overrides as $row_key => $cols) {
			$row_num = (int) $row_key;
			if ($row_num <= 0 || !is_array($cols)) {
				continue;
			}

			$row_idx = $row_map[(string) $row_num] ?? null;

			foreach ($cols as $header => $chg) {
				$header = (string) $header;

				$old = '';
				$new = '';
				$has_old = false;

				if (is_array($chg)) {
					$has_old = array_key_exists('old', $chg);
					$old = (string) ($chg['old'] ?? '');
					$new = (string) ($chg['new'] ?? '');
				} else {
					$new = (string) $chg;
				}

				$entry = [
					'row' => $row_num,
					'header' => $header,
					'old' => $old,
					'new' => $new,
					'applied' => false,
					'reason' => '',
				];

				if ($header === '' || !isset($allowed[$header])) {
					$entry['reason'] = 'header_not_allowed';
					$skipped++;
					$entries[] = $entry;
					continue;
				}

				if ($row_idx === null || !isset($rows[$row_idx]) || !is_array($rows[$row_idx])) {
					$entry['reason'] = 'row_not_found';
					$skipped++;
					$entries[] = $entry;
					continue;
				}

				$current = isset($rows[$row_idx][$header]) ? (string) $rows[$row_idx][$header] : '';

				if ($has_old && $current !== $old) {
					$entry['reason'] = 'old_value_mismatch';
					$skipped++;
					$entries[] = $entry;
					continue;
				}

				if (strlen($new) > 2000) {
					$entry['reason'] = 'new_value_too_long';
					$skipped++;
					$entries[] = $entry;
					continue;
				}

				$rows[$row_idx][$header] = $new;

				$entry['applied'] = true;
				$applied++;
				$entries[] = $entry;
			}
		}

		$parsed['rows'] = $rows;

		$audit['total'] = $applied + $skipped;
		$audit['applied'] = $applied;
		$audit['skipped'] = $skipped;
		$audit['entries'] = $entries;

		return $audit;
	}

	private function build_raw_preview(array $parsed): array
	{
		$headers = isset($parsed['headers']) && is_array($parsed['headers']) ? $parsed['headers'] : [];
		$rows    = isset($parsed['rows']) && is_array($parsed['rows']) ? $parsed['rows'] : [];

		$out_rows = [];
		$k = 0;

		if (!empty($headers) && !empty($rows)) {
			foreach ($rows as $r) {
				if ($k >= self::RAW_PREVIEW_CAP) {
					break;
				}
				if (!is_array($r)) {
					continue;
				}

				$rn = (int) ($r['_row_number'] ?? 0);
				if ($rn <= 0) {
					$rn = $k + 2; // fallback: header is row 1, first data row is row 2
				}

				$data = [];
				foreach ($headers as $h) {
					$h = (string) $h;
					$data[$h] = isset($r[$h]) ? (string) $r[$h] : '';
				}

				$out_rows[] = [
					'row_number' => $rn,
					'data'       => $data,
				];

				$k++;
			}
		}

		return [
			'headers' => $headers,
			'rows'    => $out_rows,
			'total'   => count($rows),
			'cap'     => self::RAW_PREVIEW_CAP,
		];
	}

	private function run_import(array $parsed, string $batch_id, string $filename, bool $dry_run): array
	{
		if (!vms_dt_has_core_function('vms_normalize_email_cell')) {
			return [
				'batch_id' => $batch_id,
				'filename' => $filename,
				'summary'  => ['processed' => 0, 'create' => 0, 'update' => 0, 'skip' => 0, 'fail' => 1],
				'rows'     => [[
					'row'      => 0,
					'level'    => 'fail',
					'action'   => 'init',
					'identity' => '',
					'field'    => 'helpers',
					'header'   => '',
					'cell'     => '',
					'reason'   => 'helpers_missing',
					'message'  => 'VMS normalizers not available (vms_normalize_email_cell()). Core bootstrap must load /core/registry/normalizers.php.',
				]],
			];
		}

		if (!vms_dt_has_core_function('vms_vendor_schema')) {
			return [
				'batch_id' => $batch_id,
				'filename' => $filename,
				'summary'  => ['processed' => 0, 'create' => 0, 'update' => 0, 'skip' => 0, 'fail' => 1],
				'rows'     => [[
					'row'      => 0,
					'level'    => 'fail',
					'action'   => 'init',
					'identity' => '',
					'field'    => '',
					'header'   => '',
					'cell'     => '',
					'reason'   => 'schema_missing',
					'message'  => 'VMS core vendor schema not available (vms_vendor_schema()).',
				]],
			];
		}

		$schema_registry = vms_dt_core_class('VMS_Vendor_Schema_Registry');
		if ($schema_registry === '') {
			return [
				'batch_id' => $batch_id,
				'filename' => $filename,
				'summary'  => ['processed' => 0, 'create' => 0, 'update' => 0, 'skip' => 0, 'fail' => 1],
				'rows'     => [[
					'row'      => 0,
					'level'    => 'fail',
					'action'   => 'init',
					'identity' => '',
					'field'    => '',
					'header'   => '',
					'cell'     => '',
					'reason'   => 'adapter_missing',
					'message'  => 'CSV adapter not available (VMS_Vendor_Schema_Registry).',
				]],
			];
		}

		$schema       = vms_dt_call_core_function('vms_vendor_schema');
		$headers      = $parsed['headers'];
		$header_index = $this->header_index_map($headers);

		$resolved = $schema_registry::resolve_fields($headers);
		$contract = $schema_registry::get('v1');
		$preferred_lang_header = $this->find_preferred_language_header($headers);

		$summary     = ['processed' => 0, 'create' => 0, 'update' => 0, 'skip' => 0, 'fail' => 0];
		$rows_report = [];

		// Track duplicate identities within the same upload after normalization.
		$seen_emails = []; // normalized primary_email => first row number seen

		foreach ($parsed['rows'] as $i => $row) {
			$row_num = (int) ($row['_row_number'] ?? 0); // spreadsheet row number: header is 1, first data row is 2
			if ($row_num <= 0) {
				$row_num = $i + 2;
			}

			$summary['processed']++;

			$canon = $this->row_to_canonical($row, $resolved, $contract);
			$preferred_lang_raw = '';
			$preferred_lang = '';
			if ($preferred_lang_header !== '') {
				$preferred_lang_raw = trim((string) ($row[$preferred_lang_header] ?? ''));
				$preferred_lang = $this->sanitize_preferred_language($preferred_lang_raw);
				if ($preferred_lang_raw !== '' && $preferred_lang === '') {
					$rows_report[] = $this->row_issue(
						$row_num,
						'warn',
						$dry_run ? 'preview' : 'commit',
						(string) ($canon['display_name'] ?? ''),
						'vms_vendor_preferred_lang',
						$preferred_lang_header,
						$this->cell_ref($preferred_lang_header, $header_index, $row_num),
						'invalid_preferred_language',
						'Preferred language value is invalid and will be ignored (expected values like en or es).'
					);
				}
			}

			// Coalesce emails (adapter rules)
			$canon = $this->apply_coalesce($canon, $contract);

			// Bridge legacy/Tax1099-style name parts into modern payee + entity fields.
			$canon = $this->derive_tax_profile_fields($canon);

			$primary_email = (string) ($canon['primary_email'] ?? '');
			$display_name  = (string) ($canon['display_name'] ?? '');

			// Normalize/salvage primary_email (comma/semicolon/space separated, @@ typos, pasted junk)
			$h_email    = $resolved['primary_email'] ?? '';
			$cell_email = $this->cell_ref($h_email, $header_index, $row_num);

			$email_norm    = vms_dt_call_core_function('vms_normalize_email_cell', $primary_email);
			$primary_email = (string) ($email_norm['email'] ?? '');

			// Enforce canonical normalization for matching + reporting.
			$primary_email = strtolower(trim($primary_email));
			$canon['primary_email'] = $primary_email;

			// Block duplicate normalized emails inside the same file.
			if ($primary_email !== '') {
				if (isset($seen_emails[$primary_email])) {
					$first_row = (int) $seen_emails[$primary_email];

					$summary['fail']++;
					$rows_report[] = $this->row_issue(
						$row_num,
						'fail',
						$dry_run ? 'preview' : 'commit',
						$primary_email,
						'primary_email',
						$h_email,
						$cell_email,
						'duplicate_identity_in_file',
						'Duplicate Primary Email in this file after normalization; first seen on row ' . $first_row . ': ' . $primary_email
					);
					continue;
				}

				$seen_emails[$primary_email] = $row_num;
			}

			// Warn if we had to salvage/normalize (include header + cell ref)
			if (!empty($email_norm['warning'])) {
				$rows_report[] = $this->row_issue(
					$row_num,
					'warn',
					$dry_run ? 'preview' : 'commit',
					$primary_email !== '' ? $primary_email : $display_name,
					'primary_email',
					$h_email,
					$cell_email,
					'email_normalized',
					$this->normalize_email_warning_message((string) $email_norm['warning'], $primary_email)
				);
			}

			// If the email cell had content but resulted in NO valid email, and name is blank, skip.
			if ($h_email !== '') {
				$raw_email_cell = trim((string) ($row[$h_email] ?? ''));
				if ($raw_email_cell !== '' && $primary_email === '' && trim($display_name) === '') {
					$summary['skip']++;
					$rows_report[] = $this->row_issue(
						$row_num,
						'skip',
						$dry_run ? 'preview' : 'commit',
						'',
						'identity',
						$h_email,
						$cell_email,
						'invalid_identity_email',
						'Primary Email cell contained no valid email and Vendor Name was blank; row skipped.'
					);
					continue;
				}
			}

			$identity = trim($primary_email) !== '' ? $primary_email : $display_name;

			// Identity enforcement
			if (trim($primary_email) === '' && trim($display_name) === '') {
				$summary['skip']++;
				$rows_report[] = $this->row_issue(
					$row_num,
					'skip',
					$dry_run ? 'preview' : 'commit',
					$identity,
					'identity',
					'',
					'',
					'missing_identity',
					'Missing Vendor Name and Primary Email; row skipped.'
				);
				continue;
			}

			// Placeholder title
			$placeholder_used = false;
			if (trim($display_name) === '' && trim($primary_email) !== '') {
				$display_name = (string) ($contract['identity']['title_placeholder_prefix'] ?? 'Vendor: ') . $primary_email;
				$canon['display_name'] = $display_name;
				$placeholder_used = true;
			}

			$existing_id = $this->find_vendor_id($primary_email, $display_name);
			$action      = $existing_id ? 'update' : 'create';

			if ($dry_run) {
				$summary[$action]++;
				if ($placeholder_used) {
					$rows_report[] = $this->row_issue(
						$row_num,
						'warn',
						$action,
						$identity,
						'display_name',
						'',
						'',
						'placeholder_title',
						'Vendor Name missing; using email-based placeholder title.'
					);
				}
				continue;
			}

			$post_id = $existing_id ?: $this->create_vendor_post($display_name);
			if (is_wp_error($post_id)) {
				$summary['fail']++;
				$rows_report[] = $this->row_issue(
					$row_num,
					'fail',
					$action,
					$identity,
					'post',
					'',
					'',
					'post_write_failed',
					'Failed to create/update vendor post.'
				);
				continue;
			}

			// Write fields according to MASTER schema
			foreach ($canon as $key => $value) {
				if (!isset($schema[$key])) {
					continue;
				}

				$def = $schema[$key];

				// Never persist sensitive/non-persisted fields
				if (($def['persist'] ?? true) === false) {
					continue;
				}
				if (($def['sensitive'] ?? false) === true) {
					continue;
				}

				$storage = (string) ($def['storage'] ?? '');
				if ($storage === 'post_title') {
					// Already set via create/update, but keep synced if a real name comes in later.
					wp_update_post(['ID' => (int) $post_id, 'post_title' => (string) $value]);
					continue;
				}

				if ($storage === 'meta') {
					$meta_key = (string) ($def['meta_key'] ?? '');
					if ($meta_key === '') {
						continue;
					}

					update_post_meta((int) $post_id, $meta_key, $value);

					// Legacy bridge: also write legacy email key if present in schema and this is primary_email.
					if ($key === 'primary_email') {
						update_post_meta((int) $post_id, '_vms_vendor_email', (string) $value);
					}
					continue;
				}

				if ($storage === 'taxonomy') {
					$taxonomy = (string) ($def['taxonomy'] ?? '');
					if ($taxonomy === '' || $value === '' || !taxonomy_exists($taxonomy)) {
						continue;
					}

					$raw = is_array($value) ? implode(',', $value) : (string) $value;
					$parts = preg_split('/[;,|]+/', $raw);
					$term_names = array();
					foreach ((array) $parts as $p) {
						$t = trim((string) $p);
						if ($t !== '') {
							$term_names[] = $t;
						}
					}

					if (empty($term_names)) {
						continue;
					}

					$term_ids = array();
					foreach ($term_names as $term_name) {
						$existing = term_exists($term_name, $taxonomy);
						if (is_array($existing) && isset($existing['term_id'])) {
							$term_ids[] = (int) $existing['term_id'];
							continue;
						}

						if (is_int($existing) && $existing > 0) {
							$term_ids[] = (int) $existing;
							continue;
						}

						$created = wp_insert_term($term_name, $taxonomy);
						if (!is_wp_error($created) && is_array($created) && isset($created['term_id'])) {
							$term_ids[] = (int) $created['term_id'];
						}
					}

					if (!empty($term_ids)) {
						wp_set_object_terms((int) $post_id, $term_ids, $taxonomy, false);
					}
					continue;
				}
			}

			// Placeholder flags
			if ($placeholder_used) {
				update_post_meta((int) $post_id, '_vms_vendor_display_name_placeholder', '1');
				update_post_meta((int) $post_id, '_vms_vendor_display_name_placeholder_source', 'primary_email');
			} else {
				// Clear placeholder flags if a real name arrived.
				delete_post_meta((int) $post_id, '_vms_vendor_display_name_placeholder');
				delete_post_meta((int) $post_id, '_vms_vendor_display_name_placeholder_source');
			}

			// Optional VIO language import bridge.
			if ($preferred_lang !== '') {
				update_post_meta((int) $post_id, 'vms_vendor_preferred_lang', $preferred_lang);
			}

			// Ensure imported vendors default to unclaimed when portal status is missing.
			$portal_status = (string) get_post_meta((int) $post_id, 'vms_vendor_portal_status', true);
			if (trim($portal_status) === '') {
				$linked_user_id = (int) get_post_meta((int) $post_id, '_vms_vendor_user_id', true);
				update_post_meta((int) $post_id, 'vms_vendor_portal_status', $linked_user_id > 0 ? 'claimed' : 'unclaimed');
			}

			$summary[$action]++;
		}

		return [
			'batch_id' => $batch_id,
			'filename' => $filename,
			'summary'  => $summary,
			'rows'     => $rows_report,
		];
	}

	private function normalize_email_warning_message(string $warning, string $normalized_email): string
	{
		$warning = trim($warning);
		if ($warning === '' || $normalized_email === '') {
			return $warning;
		}

		if (stripos($warning, 'using first valid email:') !== false) {
			$rewritten = preg_replace(
				'/(using first valid email:\s*)(.+)$/i',
				'$1' . $normalized_email,
				$warning
			);
			return is_string($rewritten) ? $rewritten : $warning;
		}

		return $warning . ' (normalized: ' . $normalized_email . ')';
	}

	private function handle_upload(?array $file)
	{
		if (empty($file) || empty($file['tmp_name'])) {
			return new WP_Error('vms_vendor_import_no_file', 'No CSV file uploaded.');
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$overrides = ['test_form' => false, 'mimes' => ['csv' => 'text/csv', 'txt' => 'text/plain']];
		$move = wp_handle_upload($file, $overrides);

		if (!is_array($move) || empty($move['file'])) {
			return new WP_Error('vms_vendor_import_upload', 'Failed to upload CSV.');
		}

		return [
			'path' => (string) $move['file'],
			'name' => (string) ($file['name'] ?? ''),
		];
	}

	private function parse_csv(string $path)
	{
		$handle = fopen($path, 'r');
		if (!$handle) {
			return new WP_Error('vms_vendor_import_open', 'Unable to read uploaded CSV.');
		}

		$first = fgets($handle);
		if ($first === false) {
			fclose($handle);
			return new WP_Error('vms_vendor_import_empty', 'CSV file is empty.');
		}

		$delim = $this->detect_delimiter($first);
		rewind($handle);

		$headers = fgetcsv($handle, 0, $delim);
		if (!is_array($headers) || empty($headers)) {
			fclose($handle);
			return new WP_Error('vms_vendor_import_headers', 'CSV header row could not be read.');
		}

		$rows = [];
		$row_num = 1; // header row is row 1

		while (($data = fgetcsv($handle, 0, $delim)) !== false) {
			$row_num++;
			if (!is_array($data)) {
				continue;
			}

			$assoc = ['_row_number' => $row_num];
			foreach ($headers as $idx => $h) {
				$assoc[(string) $h] = isset($data[$idx]) ? $data[$idx] : '';
			}

			// Skip fully blank rows
			$non_empty = false;
			foreach ($assoc as $k => $v) {
				if ($k === '_row_number') {
					continue;
				}
				if (trim((string) $v) !== '') {
					$non_empty = true;
					break;
				}
			}

			if ($non_empty) {
				$rows[] = $assoc;
			}
		}

		fclose($handle);

		if (empty($rows)) {
			return new WP_Error('vms_vendor_import_no_rows', 'CSV contains no data rows.');
		}

		return ['headers' => $headers, 'rows' => $rows];
	}

	private function detect_delimiter(string $line): string
	{
		$candidates = [",", "\t", ";", "|"];
		$best = ",";
		$bestCount = 0;

		foreach ($candidates as $d) {
			$count = substr_count($line, $d);
			if ($count > $bestCount) {
				$bestCount = $count;
				$best = $d;
			}
		}
		return $best;
	}

	private function find_preferred_language_header(array $headers): string
	{
		$candidates = array(
			'Preferred Language',
			'Vendor Preferred Language',
			'Portal Language',
			'Language',
			'Lang',
		);

		$normalized = array();
		foreach ($headers as $header) {
			$raw = (string) $header;
			$key = strtolower(trim(preg_replace('/\s+/', ' ', $raw) ?? ''));
			if ($key !== '' && !isset($normalized[$key])) {
				$normalized[$key] = $raw;
			}
		}

		foreach ($candidates as $candidate) {
			$key = strtolower(trim(preg_replace('/\s+/', ' ', $candidate) ?? ''));
			if ($key !== '' && isset($normalized[$key])) {
				return (string) $normalized[$key];
			}
		}

		return '';
	}

	private function sanitize_preferred_language(string $raw): string
	{
		$raw = trim(strtolower($raw));
		if ($raw === '') {
			return '';
		}

		$raw = str_replace('_', '-', $raw);
		$parts = explode('-', $raw);
		$base = sanitize_key((string) ($parts[0] ?? ''));

		if ($base === '' || !preg_match('/^[a-z]{2,5}$/', $base)) {
			return '';
		}

		return $base;
	}

	private function row_to_canonical(array $row, array $resolved, array $contract): array
	{
		$out = [];
		$fields = $contract['fields'] ?? [];

		foreach ($resolved as $canonical_key => $csv_header) {
			$val = isset($row[$csv_header]) ? (string) $row[$csv_header] : '';
			$val = trim($val);

			if ($val === '') {
				continue;
			}

			$cfg = $fields[$canonical_key] ?? [];
			$cb = $cfg['sanitize_cb'] ?? null;

			// Email fields: do not run generic sanitizers here.
			$email_keys = ['primary_email', 'recipient_email'];

			if (!in_array($canonical_key, $email_keys, true)) {
				if (is_string($cb) && is_callable($cb)) {
					$val = (string) call_user_func($cb, $val);
				}
			}

			$out[$canonical_key] = $val;
		}

		return $out;
	}

	private function derive_tax_profile_fields(array $canon): array
	{
		$payee = trim((string) ($canon['payee_legal_name'] ?? ''));
		$dba   = trim((string) ($canon['payee_dba'] ?? ''));
		$entity = trim((string) ($canon['entity_type'] ?? ''));

		$tax_type = trim((string) ($canon['tax_profile_type'] ?? ''));
		$tax_first = trim((string) ($canon['tax_first_name'] ?? ''));
		$tax_last_or_business = trim((string) ($canon['tax_business_or_last_name'] ?? ''));
		$tax_business_name = trim((string) ($canon['tax_business_name'] ?? ''));

		// Derive entity_type from older "tax_profile_type" when possible.
		if ($entity === '' && $tax_type !== '') {
			$lower = strtolower($tax_type);
			if (strpos($lower, 'individual') !== false) {
				$entity = 'individual';
			} elseif (strpos($lower, 'single') !== false && strpos($lower, 'llc') !== false) {
				$entity = 'single_llc';
			} elseif (strpos($lower, 'llc') !== false) {
				$entity = 'llc';
			} elseif (strpos($lower, 'partnership') !== false) {
				$entity = 'partnership';
			} elseif (strpos($lower, 's corp') !== false || strpos($lower, 's-corp') !== false || strpos($lower, 's corporation') !== false) {
				$entity = 's_corp';
			} elseif (strpos($lower, 'c corp') !== false || strpos($lower, 'c-corp') !== false || strpos($lower, 'c corporation') !== false) {
				$entity = 'c_corp';
			} elseif (strpos($lower, 'nonprofit') !== false || strpos($lower, 'non-profit') !== false) {
				$entity = 'nonprofit';
			} else {
				$entity = 'other';
			}
		}

		if ($entity !== '' && trim((string) ($canon['entity_type'] ?? '')) === '') {
			$canon['entity_type'] = $entity;
		}

		// Derive payee legal name (used by booking eligibility + payables export).
		if ($payee === '') {
			if ($entity === 'individual') {
				if ($tax_first !== '' && $tax_last_or_business !== '') {
					$payee = trim($tax_first . ' ' . $tax_last_or_business);
				} elseif ($tax_last_or_business !== '') {
					$payee = $tax_last_or_business;
				}
			} else {
				if ($tax_business_name !== '') {
					$payee = $tax_business_name;
				} elseif ($tax_last_or_business !== '') {
					$payee = $tax_last_or_business;
				}
			}

			if ($payee === '') {
				$payee = trim((string) ($canon['display_name'] ?? ''));
			}
		}

		if ($payee !== '' && trim((string) ($canon['payee_legal_name'] ?? '')) === '') {
			$canon['payee_legal_name'] = $payee;
		}

		// Derive DBA if it looks present.
		if ($dba === '' && $tax_business_name !== '' && $tax_business_name !== $payee) {
			$canon['payee_dba'] = $tax_business_name;
		}

		// Normalize W-9 provider to known values when present.
		if (isset($canon['w9_provider'])) {
			$prov = strtolower(trim((string) $canon['w9_provider']));
			if ($prov === 'qb' || $prov === 'quickbooks') {
				$canon['w9_provider'] = 'quickbooks_email';
			} elseif ($prov === 'tax1099' || $prov === 'tax 1099') {
				$canon['w9_provider'] = 'tax1099_email';
			}
		}

		// Convert "W9 Attested At" into an epoch timestamp if provided.
		$att = trim((string) ($canon['w9_attested_at'] ?? ''));
		if ($att !== '') {
			if (ctype_digit($att)) {
				$canon['w9_attested_at'] = (int) $att;
			} else {
				$ts = strtotime($att);
				if ($ts !== false) {
					$canon['w9_attested_at'] = (int) $ts;
				}
			}
		}

		return $canon;
	}

	private function apply_coalesce(array $canon, array $contract): array
	{
		$rules = $contract['coalesce'] ?? [];
		foreach ($rules as $r) {
			$from = (string) ($r['from'] ?? '');
			$to   = (string) ($r['to'] ?? '');
			$when = (string) ($r['when'] ?? '');

			if ($from === '' || $to === '') {
				continue;
			}

			$fromVal = (string) ($canon[$from] ?? '');
			$toVal   = (string) ($canon[$to] ?? '');

			if ($when === 'to_empty' && $toVal === '' && $fromVal !== '') {
				$canon[$to] = $fromVal;
			}
		}
		return $canon;
	}

	private function find_vendor_id(string $primary_email, string $display_name): int
	{
		$primary_email = trim($primary_email);

		if ($primary_email !== '') {
			$q = new WP_Query([
				'post_type'      => 'vms_vendor',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'meta_query'     => [[
					'key'   => '_vms_vendor_primary_email',
					'value' => $primary_email,
				]],
				'fields' => 'ids',
			]);
			if (!empty($q->posts[0])) {
				return (int) $q->posts[0];
			}

			$q = new WP_Query([
				'post_type'      => 'vms_vendor',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'meta_query'     => [[
					'key'   => '_vms_vendor_email',
					'value' => $primary_email,
				]],
				'fields' => 'ids',
			]);
			if (!empty($q->posts[0])) {
				return (int) $q->posts[0];
			}
		}

		$display_name = trim($display_name);
		if ($display_name !== '') {
			$q = new WP_Query([
				'post_type'      => 'vms_vendor',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'title'          => $display_name,
				'fields'         => 'ids',
			]);
			if (!empty($q->posts[0])) {
				return (int) $q->posts[0];
			}
		}

		return 0;
	}

	private function create_vendor_post(string $title)
	{
		return wp_insert_post([
			'post_type'   => 'vms_vendor',
			'post_status' => 'publish',
			'post_title'  => $title,
		], true);
	}

	private function row_issue(int $row, string $level, string $action, string $identity, string $field, string $header, string $cell, string $reason, string $message): array
	{
		return [
			'row'      => $row,
			'level'    => $level,
			'action'   => $action,
			'identity' => $identity,
			'field'    => $field,
			'header'   => $header,
			'cell'     => $cell,
			'reason'   => $reason,
			'message'  => $message,
		];
	}

	private function header_index_map(array $headers): array
	{
		$map = [];
		foreach ($headers as $i => $h) {
			$map[(string) $h] = (int) $i; // 0-based
		}
		return $map;
	}

	private function cell_ref(string $header, array $header_index, int $row_num): string
	{
		if ($header === '' || !isset($header_index[$header])) {
			return '';
		}
		$col = (int) $header_index[$header]; // 0-based
		return $this->col_letter($col) . $row_num;
	}

	private function col_letter(int $col): string
	{
		$col += 1;
		$letters = '';
		while ($col > 0) {
			$mod = ($col - 1) % 26;
			$letters = chr(65 + $mod) . $letters;
			$col = intdiv(($col - 1), 26);
		}
		return $letters;
	}

	private function new_batch_id(): string
	{
		return 'vmsimp_' . wp_generate_password(12, false, false);
	}

	private function new_token(): string
	{
		return wp_generate_password(20, false, false);
	}

	private function token_key(string $token): string
	{
		return 'vms_vendor_import_' . $token;
	}
}
