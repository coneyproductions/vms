<?php
defined('ABSPATH') || exit;

/**
 * Build a preview response for Vendor Import (Basic).
 *
 * @return array Preview payload
 */
function vms_dt_vendor_import_preview(array $file): array
{
	$parsed = vms_dt_csv_parse_uploaded_file($file);
	if (empty($parsed['ok'])) {
		return $parsed;
	}

	$headers = $parsed['headers'];
	$rows    = $parsed['rows'];

	// Required headers for v1 (we validate row-level too, but this catches “totally wrong file”)
	// We do not hard-fail for missing optional columns.
	$required_headers = ['display_name', 'vendor_type'];
	foreach ($required_headers as $rh) {
		if (!in_array($rh, $headers, true)) {
			return vms_dt_result_error('missing_required_column', 'Missing required column: ' . $rh);
		}
	}

	$created_count = 0;
	$updated_count = 0;
	$skipped_count = 0;

	$all_errors = [];
	$all_warnings = [];
	$preview_rows = [];

	foreach ($rows as $raw_row) {
		$row_num = isset($raw_row['__row_num']) ? (int) $raw_row['__row_num'] : 0;

		$norm = vms_dt_normalize_row($raw_row);
		$val  = vms_dt_validate_row($norm);

		$errors = $val['errors'];
		$warnings = $val['warnings'];

		$match = vms_dt_match_vendor($norm);

		$action = 'skip';
		$match_reason = $match['match_reason'] ?? 'none';
		$matched_vendor_id = isset($match['vendor_id']) ? (int) $match['vendor_id'] : 0;

		// Handle ambiguous match
		if (($match['status'] ?? '') === 'ambiguous') {
			$errors[] = vms_dt_err('ambiguous_match', 'Row matches multiple vendors; cannot decide safely.');
		}

		// Apply vendor_type fallback
		if ($norm['vendor_type'] !== '' && !in_array($norm['vendor_type'], vms_dt_allowed_vendor_types(), true)) {
			$norm['vendor_type'] = 'vendor';
		}

		// Default status
		if ($norm['status'] === '') {
			$norm['status'] = 'active';
		}

		$is_blocked = (count($errors) > 0);

		// Create vs update decision
		if (!$is_blocked) {
			if (($match['status'] ?? '') === 'matched') {
				$action = 'update';
				$updated_count++;
			} else {
				// Create path: require at least one matching key
				$has_key = ($norm['external_ref'] !== '' || $norm['primary_email'] !== '' || $norm['primary_phone'] !== '');
				if (!$has_key) {
					$action = 'error';
					$errors[] = vms_dt_err('missing_matching_key', 'At least one of external_ref, primary_email, or primary_phone is required for new vendors.');
				} else {
					$action = 'create';
					$created_count++;
				}
			}
		} else {
			$action = 'error';
		}

		// If we added an error after create decision, correct counts
		if ($action === 'error') {
			// We only counted create/update when we set those actions.
			// If a row flips to error after, we don’t decrement here because the above logic avoids that.
		}

		// Collect row-level errors/warnings (annotated with row number)
		foreach ($errors as $e) {
			$all_errors[] = ['row_num' => $row_num] + $e;
		}
		foreach ($warnings as $w) {
			$all_warnings[] = ['row_num' => $row_num] + $w;
		}

		$preview_rows[] = [
			'row_num'      => $row_num,
			'action'       => $action,
			'match_reason' => $match_reason,
			'vendor_id'    => $matched_vendor_id,
			'vendor_snapshot' => vms_dt_vendor_snapshot_from_row($norm),
		];
	}

	if ($action === 'skip' || $action === 'error') {
		$skipped_count++;
	}

	$ok = (count($all_errors) === 0);

	return [
		'ok'            => $ok,
		'stage'         => 'preview',
		'created_count' => $created_count,
		'updated_count' => $updated_count,
		'skipped_count' => $skipped_count,
		'errors'        => $all_errors,
		'warnings'      => $all_warnings,
		'rows'          => $preview_rows,
	];
}

function vms_dt_vendor_snapshot_from_row(array $row): array
{
	// Extract standard vendor fields
	$snapshot = [
		'display_name'  => $row['display_name'],
		'vendor_type'   => $row['vendor_type'],
		'status'        => $row['status'],
		'external_ref'  => $row['external_ref'],
		'primary_email' => $row['primary_email'],
		'primary_phone' => $row['primary_phone'],
		'city'          => $row['city'],
		'state'         => $row['state'],
		'postal_code'   => $row['postal_code'],
		'country'       => $row['country'],
	];

	// Contact snapshot (optional)
	$contact_any = ($row['contact_name'] !== '' || $row['contact_email'] !== '' || $row['contact_phone'] !== '' || $row['contact_role'] !== '');
	if ($contact_any) {
		$snapshot['primary_contact'] = [
			'name'  => $row['contact_name'],
			'role'  => $row['contact_role'],
			'email' => $row['contact_email'],
			'phone' => $row['contact_phone'],
		];
	}

	// Meta snapshot: headers starting with meta:
	$meta = [];
	foreach ($row as $k => $v) {
		if (strpos($k, 'meta:') === 0) {
			$key = substr($k, 5);
			$key = trim((string) $key);
			if ($key !== '' && $v !== '') {
				$meta[$key] = $v;
			}
		}
	}
	if (!empty($meta)) {
		$snapshot['meta'] = $meta;
	}

	return $snapshot;
}
