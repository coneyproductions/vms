<?php
defined('ABSPATH') || exit;

function vms_dt_vendor_import_commit(array $file): array {
	$parsed = vms_dt_csv_parse_uploaded_file($file);
	if (empty($parsed['ok'])) {
		return $parsed;
	}

	$headers = $parsed['headers'];
	$rows    = $parsed['rows'];

	// Require these headers
	foreach (['display_name','vendor_type'] as $rh) {
		if (!in_array($rh, $headers, true)) {
			return vms_dt_result_error('missing_required_column', 'Missing required column: ' . $rh);
		}
	}

	$created = 0;
	$updated = 0;
	$skipped = 0;

	$errors = [];
	$warnings = [];
	$out_rows = [];

	foreach ($rows as $raw_row) {
		$row_num = isset($raw_row['__row_num']) ? (int) $raw_row['__row_num'] : 0;

		$norm = vms_dt_normalize_row($raw_row);
		$val  = vms_dt_validate_row($norm);

		$row_errors = $val['errors'];
		$row_warnings = $val['warnings'];

		// vendor_type fallback
		if ($norm['vendor_type'] !== '' && !in_array($norm['vendor_type'], vms_dt_allowed_vendor_types(), true)) {
			$norm['vendor_type'] = 'vendor';
		}
		if ($norm['status'] === '') {
			$norm['status'] = 'active';
		}

		$match = vms_dt_match_vendor($norm);

		if (($match['status'] ?? '') === 'ambiguous') {
			$row_errors[] = vms_dt_err('ambiguous_match', 'Row matches multiple vendors; cannot decide safely.');
		}

		// Determine create/update
		$matched_vendor_id = isset($match['vendor_id']) ? (int) $match['vendor_id'] : 0;
		$is_update = (($match['status'] ?? '') === 'matched');

		if (!$is_update) {
			$has_key = ($norm['external_ref'] !== '' || $norm['primary_email'] !== '' || $norm['primary_phone'] !== '');
			if (!$has_key) {
				$row_errors[] = vms_dt_err('missing_matching_key', 'At least one of external_ref, primary_email, or primary_phone is required for new vendors.');
			}
		}

		if (!empty($row_errors)) {
			$skipped++;
			foreach ($row_errors as $e) {
				$errors[] = ['row_num' => $row_num] + $e;
			}
			foreach ($row_warnings as $w) {
				$warnings[] = ['row_num' => $row_num] + $w;
			}

			$out_rows[] = [
				'row_num' => $row_num,
				'action'  => 'error',
			];
			continue;
		}

		// Upsert vendor
		$up = vms_dt_vendor_upsert($norm, $matched_vendor_id);
		if (empty($up['ok'])) {
			$skipped++;
			$errors[] = ['row_num' => $row_num] + ($up['error'] ?? vms_dt_err('upsert_failed', 'Upsert failed.'));
			$out_rows[] = [
				'row_num' => $row_num,
				'action'  => 'error',
			];
			continue;
		}

		$vendor_id = (int) $up['vendor_id'];
		$action = $up['action'] ?? ($is_update ? 'updated' : 'created');

		if ($action === 'created') {
			$created++;
		} else {
			$updated++;
		}

		// Meta
		$meta_res = vms_dt_vendor_save_meta_from_row($vendor_id, $raw_row);
		if (empty($meta_res['ok'])) {
			$warnings[] = ['row_num' => $row_num] + ($meta_res['error'] ?? vms_dt_warn('meta_failed', 'Meta save failed.'));
		}

		// Primary contact
		$contact_res = vms_dt_vendor_upsert_primary_contact($vendor_id, $norm);
		if (empty($contact_res['ok'])) {
			$warnings[] = ['row_num' => $row_num] + ($contact_res['error'] ?? vms_dt_warn('contact_failed', 'Contact save failed.'));
		}

		foreach ($row_warnings as $w) {
			$warnings[] = ['row_num' => $row_num] + $w;
		}

		$out_rows[] = [
			'row_num'   => $row_num,
			'action'    => $action,
			'vendor_id' => $vendor_id,
		];
	}

	return [
		'ok'            => true,
		'stage'         => 'commit',
		'created_count' => $created,
		'updated_count' => $updated,
		'skipped_count' => $skipped,
		'errors'        => $errors,
		'warnings'      => $warnings,
		'rows'          => $out_rows,
	];
}

