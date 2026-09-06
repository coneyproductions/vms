<?php
/**
 * VMS Data Tools — Holidays Import (Procedural)
 *
 * CSV columns (flexible order; unknown columns ignored):
 *  venue_id, venue_slug, venue_name, date_ymd, name, status,
 *  vendor_structure, vendor_flat_fee_amount, vendor_door_split_percent, template_id,
 *  action (upsert|delete), rules_json (JSON object for payload rules)
 *
 * Write target:
 *  wp_options: vms_holidays[venue_id][date_ymd] = array(
 *      'name'   => string,
 *      'status' => 'open'|'closed',
 *      'rules'  => array( 'vendor' => array(…) ),
 *      'template_id' => string (optional)
 *  )
 *
 * Modes:
 *  - merge: upsert rows add or update; delete rows delete; no auto-deletes
 *  - overwrite: for venues present in CSV, missing dates become auto-delete plan rows
 *
 * Guardrails:
 *  - Preview builds a deterministic plan: ADD, UPDATE, DELETE, NOOP, ERROR
 *  - Commit enforces "old-value match" for UPDATE and DELETE (and "still missing" for ADD)
 *  - If data changed since Preview, Commit fails and user must re-preview
 */

if (!defined('ABSPATH')) {
	exit;
}

const VMS_DT_HOLIDAYS_IMPORT_PREVIEW_TTL_SECONDS = 60 * 30; // 30 minutes
const VMS_DT_HOLIDAYS_IMPORT_MAX_ROWS = 2000;

function vms_dt_holidays_import_get_allowed_headers(): array
{
	return array(
		'venue_id',
		'venue_slug',
		'venue_name',
		'date_ymd',
		'name',
		'status',
		'vendor_structure',
		'vendor_flat_fee_amount',
		'vendor_door_split_percent',
		'template_id',
		'action',
		'rules_json',
	);
}

function vms_dt_holidays_import_get_required_headers(): array
{
	// date_ymd is always needed; name is required for upsert rows and validated per row.
	return array('date_ymd');
}

function vms_dt_holidays_import_normalize_header(string $h): string
{
	$h = strtolower(trim($h));
	$h = str_replace(array(' ', '-'), '_', $h);
	return $h;
}

/**
 * Normalize venue name keys to reduce false "not found" cases.
 * - trims
 * - normalizes common dash variants to hyphen
 * - collapses whitespace
 */
function vms_dt_holidays_import_normalize_venue_key(string $s): string
{
	$s = trim($s);
	if ($s === '') {
		return '';
	}

	$s = str_replace(array('–', '—', '−'), '-', $s);
	$s = preg_replace('/\s+/', ' ', $s);

	return $s;
}

function vms_dt_holidays_import_sanitize_money(string $raw): string
{
	$raw = trim($raw);
	if ($raw === '') {
		return '';
	}

	$raw = str_replace(array('$', ',', ' '), '', $raw);
	$raw = preg_replace('/[^0-9.]/', '', $raw);
	if ($raw === '') {
		return '';
	}

	return (string) (0 + $raw);
}

function vms_dt_holidays_import_sanitize_percent(string $raw): string
{
	$raw = trim($raw);
	if ($raw === '') {
		return '';
	}

	$raw = str_replace(array('%', ',', ' '), '', $raw);
	$raw = preg_replace('/[^0-9.]/', '', $raw);
	if ($raw === '') {
		return '';
	}

	return (string) (0 + $raw);
}

/**
 * Resolve venue_id using venue_id OR venue_slug OR venue_name.
 * Returns: ['ok'=>bool,'venue_id'=>int,'venue_title'=>string,'message'=>string]
 */
function vms_dt_holidays_import_resolve_venue_id(array $data): array
{
	if (isset($data['venue_id']) && trim((string) $data['venue_id']) !== '') {
		$venue_id = absint($data['venue_id']);
		if ($venue_id > 0 && get_post_type($venue_id) === 'vms_venue') {
			return array(
				'ok' => true,
				'venue_id' => $venue_id,
				'venue_title' => (string) get_the_title($venue_id),
				'message' => '',
			);
		}

		return array('ok' => false, 'venue_id' => 0, 'venue_title' => '', 'message' => 'venue_id is present but invalid.');
	}

	if (isset($data['venue_slug']) && trim((string) $data['venue_slug']) !== '') {
		$slug = sanitize_title((string) $data['venue_slug']);
		if ($slug !== '') {
			$q = get_posts(array(
				'post_type' => 'vms_venue',
				'post_status' => array('publish', 'draft', 'private'),
				'name' => $slug,
				'fields' => 'ids',
				'posts_per_page' => 2,
			));

			if (!empty($q) && count($q) === 1) {
				$venue_id = (int) $q[0];
				return array(
					'ok' => true,
					'venue_id' => $venue_id,
					'venue_title' => (string) get_the_title($venue_id),
					'message' => '',
				);
			}

			if (!empty($q) && count($q) > 1) {
				return array('ok' => false, 'venue_id' => 0, 'venue_title' => '', 'message' => 'venue_slug matched multiple venues. Use venue_id.');
			}

			return array('ok' => false, 'venue_id' => 0, 'venue_title' => '', 'message' => 'venue_slug not found.');
		}
	}

	if (isset($data['venue_name']) && trim((string) $data['venue_name']) !== '') {
		$want = vms_dt_holidays_import_normalize_venue_key((string) $data['venue_name']);
		$needle = strtolower($want);

		$found = get_page_by_title($want, OBJECT, 'vms_venue');
		if ($found && !empty($found->ID)) {
			$venue_id = (int) $found->ID;
			return array(
				'ok' => true,
				'venue_id' => $venue_id,
				'venue_title' => (string) get_the_title($venue_id),
				'message' => '',
			);
		}

		$venues = get_posts(array(
			'post_type' => 'vms_venue',
			'post_status' => array('publish', 'draft', 'private'),
			'posts_per_page' => -1,
		));

		$matches = array();
		foreach ($venues as $v) {
			if (!is_object($v) || empty($v->ID)) {
				continue;
			}
			$title = vms_dt_holidays_import_normalize_venue_key((string) $v->post_title);
			if (strtolower($title) === $needle) {
				$matches[] = (int) $v->ID;
			}
		}

		$matches = array_values(array_unique($matches));

		if (count($matches) === 1) {
			$venue_id = (int) $matches[0];
			return array(
				'ok' => true,
				'venue_id' => $venue_id,
				'venue_title' => (string) get_the_title($venue_id),
				'message' => '',
			);
		}

		if (count($matches) > 1) {
			return array('ok' => false, 'venue_id' => 0, 'venue_title' => '', 'message' => 'venue_name matched multiple venues. Use venue_id or venue_slug.');
		}

		return array('ok' => false, 'venue_id' => 0, 'venue_title' => '', 'message' => 'venue_name not found.');
	}

	return array('ok' => false, 'venue_id' => 0, 'venue_title' => '', 'message' => 'You must provide venue_id, venue_slug, or venue_name.');
}

function vms_dt_holidays_import_mode_normalize(string $mode): string
{
	$mode = strtolower(trim($mode));
	return ($mode === 'overwrite') ? 'overwrite' : 'merge';
}

function vms_dt_holidays_import_action_normalize(string $raw): string
{
	$a = strtolower(trim($raw));
	if ($a === '' || $a === 'upsert') {
		return 'upsert';
	}
	if ($a === 'delete') {
		return 'delete';
	}
	return 'invalid';
}

function vms_dt_holidays_import_payload_normalize($payload)
{
	if ($payload === null) {
		return null;
	}
	if (!is_array($payload)) {
		return array();
	}

	$out = $payload;

	if (!isset($out['rules']) || !is_array($out['rules'])) {
		$out['rules'] = array();
	}

	if (isset($out['rules']['vendor']) && is_array($out['rules']['vendor'])) {
		$v = $out['rules']['vendor'];

		if (isset($v['flat_fee_amount'])) {
			$v['flat_fee_amount'] = (float) $v['flat_fee_amount'];
		}
		if (isset($v['door_split_percent'])) {
			$v['door_split_percent'] = (float) $v['door_split_percent'];
		}

		ksort($v);
		$out['rules']['vendor'] = $v;
	}

	ksort($out['rules']);
	ksort($out);

	return $out;
}

function vms_dt_holidays_import_payload_equal($a, $b): bool
{
	$na = vms_dt_holidays_import_payload_normalize($a);
	$nb = vms_dt_holidays_import_payload_normalize($b);

	return wp_json_encode($na) === wp_json_encode($nb);
}

function vms_dt_holidays_import_build_payload(array $data, array $messages): array
{
	$name = isset($data['name']) ? trim((string) $data['name']) : '';
	$status = isset($data['status']) ? strtolower(trim((string) $data['status'])) : '';
	$template_id = isset($data['template_id']) ? trim((string) $data['template_id']) : '';

	if ($status === '') {
		$status = 'open';
	}

	$payload = array(
		'name' => $name,
		'status' => in_array($status, array('open', 'closed'), true) ? $status : 'open',
		'rules' => array(),
	);

	$rules = array();
	$rules_raw = isset($data['rules_json']) ? trim((string) $data['rules_json']) : '';
	if ($rules_raw !== '') {
		$decoded = json_decode($rules_raw, true);
		if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
			$messages[] = 'rules_json must be valid JSON (object).';
		} else {
			$rules = $decoded;
		}
	}

	$vendor_structure = isset($data['vendor_structure']) ? strtolower(trim((string) $data['vendor_structure'])) : '';
	$flat_fee_raw = isset($data['vendor_flat_fee_amount']) ? trim((string) $data['vendor_flat_fee_amount']) : '';
	$door_pct_raw = isset($data['vendor_door_split_percent']) ? trim((string) $data['vendor_door_split_percent']) : '';

	$flat_fee = ($flat_fee_raw !== '') ? vms_dt_holidays_import_sanitize_money($flat_fee_raw) : '';
	$door_pct = ($door_pct_raw !== '') ? vms_dt_holidays_import_sanitize_percent($door_pct_raw) : '';

	$has_flat = ($flat_fee !== '' && is_numeric($flat_fee));
	$has_pct = ($door_pct !== '' && is_numeric($door_pct));

	if ($vendor_structure !== '' || $has_flat || $has_pct) {
		if (!in_array($vendor_structure, array('flat_fee', 'door_split', 'flat_fee_door_split'), true)) {
			$messages[] = 'vendor_structure must be flat_fee, door_split, flat_fee_door_split, or blank.';
		} else {
			if (!$has_flat && !$has_pct) {
				$messages[] = 'If vendor_structure is set, enter vendor_flat_fee_amount and or vendor_door_split_percent.';
			}

			if (!isset($rules['vendor']) || !is_array($rules['vendor'])) {
				$rules['vendor'] = array();
			}

			$rules['vendor']['structure'] = $vendor_structure;

			if ($has_flat) {
				if ((float) $flat_fee < 0) {
					$messages[] = 'vendor_flat_fee_amount must be a number ≥ 0.';
				} else {
					$rules['vendor']['flat_fee_amount'] = (float) $flat_fee;
				}
			}

			if ($has_pct) {
				$pct = (float) $door_pct;
				if ($pct < 0 || $pct > 100) {
					$messages[] = 'vendor_door_split_percent must be between 0 and 100.';
				} else {
					$rules['vendor']['door_split_percent'] = (float) $pct;
				}
			}
		}
	}

	if (!empty($rules)) {
		$payload['rules'] = $rules;
	}

	if ($template_id !== '') {
		$payload['template_id'] = $template_id;
	}

	return array('payload' => $payload, 'messages' => $messages);
}

function vms_dt_holidays_import_validate_and_plan_row(array $data, array $existing_holidays, array &$seen_keys): array
{
	$messages = array();

	$resolved = vms_dt_holidays_import_resolve_venue_id($data);
	$venue_id = 0;
	$venue_title = '';

	if (!$resolved['ok']) {
		$messages[] = (string) $resolved['message'];
	} else {
		$venue_id = (int) $resolved['venue_id'];
		$venue_title = (string) $resolved['venue_title'];
	}

	$date = isset($data['date_ymd']) ? trim((string) $data['date_ymd']) : '';
	if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
		$messages[] = 'date_ymd must be YYYY-MM-DD.';
	} else {
		$ts = strtotime($date . ' 00:00:00');
		if (!$ts) {
			$messages[] = 'date_ymd is not a valid date.';
		}
	}

	$action = vms_dt_holidays_import_action_normalize(isset($data['action']) ? (string) $data['action'] : '');
	if ($action === 'invalid') {
		$messages[] = 'action must be upsert or delete (or blank).';
		$action = 'upsert';
	}

	$name = isset($data['name']) ? trim((string) $data['name']) : '';
	if ($action === 'upsert' && $name === '') {
		$messages[] = 'name is required for upsert rows.';
	}

	$status = isset($data['status']) ? strtolower(trim((string) $data['status'])) : '';
	if ($status !== '' && !in_array($status, array('open', 'closed'), true)) {
		$messages[] = 'status must be open or closed (or blank).';
	}

	$key = ($venue_id > 0 && $date !== '') ? ($venue_id . '|' . $date) : '';
	if ($key !== '') {
		if (isset($seen_keys[$key])) {
			$messages[] = 'Duplicate venue/date found in CSV. Keep only one row per venue/date.';
		} else {
			$seen_keys[$key] = true;
		}
	}

	$before = null;
	if ($venue_id > 0 && $date !== '') {
		if (isset($existing_holidays[$venue_id]) && is_array($existing_holidays[$venue_id]) && isset($existing_holidays[$venue_id][$date])) {
			$before = $existing_holidays[$venue_id][$date];
		}
	}

	if (!empty($messages)) {
		return array(
			'status' => 'ERROR',
			'messages' => $messages,
			'venue_id' => $venue_id,
			'venue_title' => $venue_title,
			'date_ymd' => $date,
			'action' => $action,
			'before' => $before,
			'after' => null,
		);
	}

	if ($action === 'delete') {
		if ($before === null) {
			return array(
				'status' => 'NOOP',
				'messages' => array('No holiday exists for this venue/date, so nothing will be deleted.'),
				'venue_id' => $venue_id,
				'venue_title' => $venue_title,
				'date_ymd' => $date,
				'action' => $action,
				'before' => null,
				'after' => null,
			);
		}

		return array(
			'status' => 'DELETE',
			'messages' => array(),
			'venue_id' => $venue_id,
			'venue_title' => $venue_title,
			'date_ymd' => $date,
			'action' => $action,
			'before' => $before,
			'after' => null,
		);
	}

	$built = vms_dt_holidays_import_build_payload($data, array());
	$payload = $built['payload'];
	$messages = $built['messages'];

	if (!empty($messages)) {
		return array(
			'status' => 'ERROR',
			'messages' => $messages,
			'venue_id' => $venue_id,
			'venue_title' => $venue_title,
			'date_ymd' => $date,
			'action' => $action,
			'before' => $before,
			'after' => null,
		);
	}

	if ($before === null) {
		return array(
			'status' => 'ADD',
			'messages' => array(),
			'venue_id' => $venue_id,
			'venue_title' => $venue_title,
			'date_ymd' => $date,
			'action' => $action,
			'before' => null,
			'after' => $payload,
		);
	}

	if (vms_dt_holidays_import_payload_equal($before, $payload)) {
		return array(
			'status' => 'NOOP',
			'messages' => array(),
			'venue_id' => $venue_id,
			'venue_title' => $venue_title,
			'date_ymd' => $date,
			'action' => $action,
			'before' => $before,
			'after' => $payload,
		);
	}

	$diff = array();
	$before_name = isset($before['name']) ? (string) $before['name'] : '';
	$before_status = isset($before['status']) ? (string) $before['status'] : '';
	$after_name = isset($payload['name']) ? (string) $payload['name'] : '';
	$after_status = isset($payload['status']) ? (string) $payload['status'] : '';

	if ($before_name !== $after_name) {
		$diff[] = 'name will change.';
	}
	if ($before_status !== $after_status) {
		$diff[] = 'status will change.';
	}

	$before_rules = (isset($before['rules']) && is_array($before['rules'])) ? $before['rules'] : array();
	$after_rules = (isset($payload['rules']) && is_array($payload['rules'])) ? $payload['rules'] : array();
	if (wp_json_encode($before_rules) !== wp_json_encode($after_rules)) {
		$diff[] = 'rules will change.';
	}

	return array(
		'status' => 'UPDATE',
		'messages' => $diff,
		'venue_id' => $venue_id,
		'venue_title' => $venue_title,
		'date_ymd' => $date,
		'action' => $action,
		'before' => $before,
		'after' => $payload,
	);
}

function vms_dt_holidays_import_plan_autodeletes(array &$plan_rows, array $existing_holidays, array $venues_in_csv, array $dates_in_csv_by_venue, array &$seen_keys): void
{
	foreach ($venues_in_csv as $venue_id) {
		$venue_id = (int) $venue_id;
		if ($venue_id <= 0) {
			continue;
		}

		$existing_dates = isset($existing_holidays[$venue_id]) && is_array($existing_holidays[$venue_id]) ? array_keys($existing_holidays[$venue_id]) : array();
		$keep = isset($dates_in_csv_by_venue[$venue_id]) && is_array($dates_in_csv_by_venue[$venue_id]) ? $dates_in_csv_by_venue[$venue_id] : array();

		$keep_set = array();
		foreach ($keep as $d) {
			$keep_set[(string) $d] = true;
		}

		foreach ($existing_dates as $date) {
			$date = (string) $date;
			if ($date === '' || isset($keep_set[$date])) {
				continue;
			}

			$key = $venue_id . '|' . $date;
			if (isset($seen_keys[$key])) {
				continue;
			}
			$seen_keys[$key] = true;

			$before = $existing_holidays[$venue_id][$date];

			$plan_rows[] = array(
				'rownum' => 'AUTO',
				'status' => 'DELETE',
				'messages' => array('Overwrite mode: this date is not present in the CSV, so it will be deleted.'),
				'data' => array(),
				'venue_id' => $venue_id,
				'venue_title' => (string) get_the_title($venue_id),
				'date_ymd' => $date,
				'action' => 'delete',
				'before' => $before,
				'after' => null,
				'is_auto' => true,
			);
		}
	}
}

function vms_dt_holidays_import_parse_csv_file(string $file_path, string $mode = 'merge'): array
{
	$result = array(
		'ok' => false,
		'error' => '',
		'rows' => array(),
		'meta' => array(),
		'counts' => array(),
		'mode' => vms_dt_holidays_import_mode_normalize($mode),
		'venues_in_csv' => array(),
		'has_destructive' => false,
		'backup_key' => '',
		'backup_filename' => '',
	);

	if (!file_exists($file_path) || !is_readable($file_path)) {
		$result['error'] = 'CSV file missing or unreadable.';
		return $result;
	}

	$fh = fopen($file_path, 'r');
	if (!$fh) {
		$result['error'] = 'Unable to open CSV file.';
		return $result;
	}

	$raw_headers = fgetcsv($fh);
	if (!is_array($raw_headers) || empty($raw_headers)) {
		fclose($fh);
		$result['error'] = 'CSV header row missing.';
		return $result;
	}

	$headers = array();
	foreach ($raw_headers as $h) {
		$headers[] = vms_dt_holidays_import_normalize_header((string) $h);
	}

	$required = vms_dt_holidays_import_get_required_headers();
	foreach ($required as $req) {
		if (!in_array($req, $headers, true)) {
			fclose($fh);
			$result['error'] = 'Missing required column: ' . $req;
			return $result;
		}
	}

	$allowed = vms_dt_holidays_import_get_allowed_headers();

	$header_to_index = array();
	foreach ($headers as $idx => $h) {
		if (!isset($header_to_index[$h])) {
			$header_to_index[$h] = $idx;
		}
	}

	$existing = get_option('vms_holidays', array());
	if (!is_array($existing)) {
		$existing = array();
	}

	$mode = $result['mode'];

	$rownum = 1; // header is row 1
	$seen_keys = array();
	$venues_in_csv = array();
	$dates_in_csv_by_venue = array();

	while (($cols = fgetcsv($fh)) !== false) {
		$rownum++;

		if ($rownum > (VMS_DT_HOLIDAYS_IMPORT_MAX_ROWS + 2)) {
			$result['error'] = 'CSV too large. Reduce rows and try again.';
			fclose($fh);
			return $result;
		}

		$data = array();
		foreach ($header_to_index as $key => $idx) {
			if (!in_array($key, $allowed, true)) {
				continue;
			}
			$data[$key] = isset($cols[$idx]) ? trim((string) $cols[$idx]) : '';
		}

		$all_empty = true;
		foreach ($data as $v) {
			if ($v !== '') {
				$all_empty = false;
				break;
			}
		}
		if ($all_empty) {
			continue;
		}


		$planned = vms_dt_holidays_import_validate_and_plan_row($data, $existing, $seen_keys);

		$row = array(
			'rownum' => (string) $rownum,
			'status' => $planned['status'],
			'messages' => isset($planned['messages']) && is_array($planned['messages']) ? $planned['messages'] : array(),
			'data' => $data,
			'venue_id' => (int) $planned['venue_id'],
			'venue_title' => (string) $planned['venue_title'],
			'date_ymd' => (string) $planned['date_ymd'],
			'action' => (string) $planned['action'],
			'before' => $planned['before'],
			'after' => $planned['after'],
			'is_auto' => false,
		);

		if ($row['status'] !== 'ERROR' && $row['venue_id'] > 0 && $row['date_ymd'] !== '') {
			$vid = $row['venue_id'];
			if (!in_array($vid, $venues_in_csv, true)) {
				$venues_in_csv[] = $vid;
			}

			if (!isset($dates_in_csv_by_venue[$vid])) {
				$dates_in_csv_by_venue[$vid] = array();
			}
			if (!in_array($row['date_ymd'], $dates_in_csv_by_venue[$vid], true)) {
				$dates_in_csv_by_venue[$vid][] = $row['date_ymd'];
			}
		}

		$result['rows'][] = $row;
	}

	fclose($fh);

	if ($mode === 'overwrite') {
		vms_dt_holidays_import_plan_autodeletes($result['rows'], $existing, $venues_in_csv, $dates_in_csv_by_venue, $seen_keys);
	}

	$counts = array('ADD' => 0, 'UPDATE' => 0, 'DELETE' => 0, 'NOOP' => 0, 'ERROR' => 0);
	$has_destructive = false;

	foreach ($result['rows'] as $r) {
		$st = isset($r['status']) ? (string) $r['status'] : 'ERROR';
		if (isset($counts[$st])) {
			$counts[$st]++;
		} else {
			$counts['ERROR']++;
		}

		if ($st === 'UPDATE' || $st === 'DELETE') {
			$has_destructive = true;
		}
	}

	$result['ok'] = true;
	$result['counts'] = $counts;
	$result['venues_in_csv'] = $venues_in_csv;
	$result['has_destructive'] = $has_destructive;
	$result['meta'] = array(
		'headers' => $headers,
		'total' => count($result['rows']),
	);

	if ($has_destructive && !empty($venues_in_csv)) {
		$backup = vms_dt_holidays_import_build_backup_csv($venues_in_csv, $existing);
		if ($backup['ok']) {
			$result['backup_key'] = $backup['key'];
			$result['backup_filename'] = $backup['filename'];
		}
	}

	return $result;
}

function vms_dt_holidays_import_extract_vendor_fields_from_payload(array $payload): array
{
	$structure = '';
	$flat_fee = '';
	$door_pct = '';

	$rules = isset($payload['rules']) && is_array($payload['rules']) ? $payload['rules'] : array();
	$vendor = isset($rules['vendor']) && is_array($rules['vendor']) ? $rules['vendor'] : array();

	if (isset($vendor['structure'])) {
		$structure = (string) $vendor['structure'];
	}
	if (isset($vendor['flat_fee_amount'])) {
		$flat_fee = (string) $vendor['flat_fee_amount'];
	}
	if (isset($vendor['door_split_percent'])) {
		$door_pct = (string) $vendor['door_split_percent'];
	}

	return array($structure, $flat_fee, $door_pct);
}

function vms_dt_holidays_import_build_backup_csv(array $venue_ids, array $existing_holidays): array
{
	$venue_ids = array_values(array_unique(array_map('intval', $venue_ids)));
	$venue_ids = array_filter($venue_ids, function ($v) { return $v > 0; });

	if (empty($venue_ids)) {
		return array('ok' => false, 'key' => '', 'filename' => '', 'csv' => '');
	}

	$fh = fopen('php://temp', 'w+');

	fputcsv($fh, array(
		'venue_id',
		'date_ymd',
		'name',
		'status',
		'vendor_structure',
		'vendor_flat_fee_amount',
		'vendor_door_split_percent',
		'template_id',
		'action',
		'rules_json',
	));

	foreach ($venue_ids as $venue_id) {
		if (!isset($existing_holidays[$venue_id]) || !is_array($existing_holidays[$venue_id])) {
			continue;
		}

		$dates = array_keys($existing_holidays[$venue_id]);
		sort($dates);

		foreach ($dates as $date) {
			$payload = $existing_holidays[$venue_id][$date];
			if (!is_array($payload)) {
				continue;
			}

			$name = isset($payload['name']) ? (string) $payload['name'] : '';
			$status = isset($payload['status']) ? (string) $payload['status'] : '';
			$template_id = isset($payload['template_id']) ? (string) $payload['template_id'] : '';

			list($structure, $flat_fee, $door_pct) = vms_dt_holidays_import_extract_vendor_fields_from_payload($payload);

			$rules = isset($payload['rules']) && is_array($payload['rules']) ? $payload['rules'] : array();
			$rules_json = empty($rules) ? '' : wp_json_encode($rules);

			fputcsv($fh, array(
				(string) $venue_id,
				(string) $date,
				$name,
				$status,
				$structure,
				$flat_fee,
				$door_pct,
				$template_id,
				'',
				$rules_json,
			));
		}
	}

	rewind($fh);
	$csv = stream_get_contents($fh);
	fclose($fh);

	$user_id = get_current_user_id();
	$rand = wp_generate_password(12, false, false);
	$key = 'vms_dt_holidays_backup_' . $user_id . '_' . $rand;

	$filename = 'vms-holidays-backup-' . gmdate('Ymd-His') . '.csv';

	set_transient($key, array(
		'user_id' => $user_id,
		'filename' => $filename,
		'csv' => $csv,
		'stored_gmt' => gmdate('Y-m-d H:i:s'),
	), VMS_DT_HOLIDAYS_IMPORT_PREVIEW_TTL_SECONDS);

	return array('ok' => true, 'key' => $key, 'filename' => $filename, 'csv' => $csv);
}

function vms_dt_holidays_import_make_storage_key(): string
{
	$user_id = get_current_user_id();
	$rand = wp_generate_password(12, false, false);
	return 'vms_dt_holidays_import_' . $user_id . '_' . $rand;
}

function vms_dt_holidays_import_store_preview(string $storage_key, array $parsed, array $source_meta): void
{
	$payload = array(
		'stored_gmt' => gmdate('Y-m-d H:i:s'),
		'parsed' => $parsed,
		'source' => $source_meta,
	);
	set_transient($storage_key, $payload, VMS_DT_HOLIDAYS_IMPORT_PREVIEW_TTL_SECONDS);
}

function vms_dt_holidays_import_load_preview(string $storage_key)
{
	$payload = get_transient($storage_key);
	return is_array($payload) ? $payload : null;
}

function vms_dt_holidays_import_delete_preview(string $storage_key): void
{
	delete_transient($storage_key);
}

function vms_dt_holidays_import_load_backup(string $backup_key)
{
	$payload = get_transient($backup_key);
	return is_array($payload) ? $payload : null;
}

function vms_dt_holidays_import_audit_log_append(array $entry): void
{
	$log = get_option('vms_dt_audit_log', array());
	if (!is_array($log)) {
		$log = array();
	}

	$log[] = $entry;

	if (count($log) > 200) {
		$log = array_slice($log, -200);
	}

	update_option('vms_dt_audit_log', $log, false);
}

function vms_dt_holidays_import_commit_plan(array $plan_rows, array $source_meta, string $mode, bool $confirmed_delete): array
{
	$mode = vms_dt_holidays_import_mode_normalize($mode);

	$out = array(
		'ok' => false,
		'error' => '',
		'counts' => array(
			'added' => 0,
			'updated' => 0,
			'deleted' => 0,
			'noop' => 0,
			'errors' => 0,
		),
		'audit' => array(
			'mode' => $mode,
			'changes' => array(),
		),
	);

	$has_error = false;
	$has_delete = false;

	foreach ($plan_rows as $r) {
		$st = isset($r['status']) ? (string) $r['status'] : 'ERROR';
		if ($st === 'ERROR') {
			$has_error = true;
		}
		if ($st === 'DELETE') {
			$has_delete = true;
		}
	}

	if ($has_error) {
		$out['error'] = 'Preview contains ERROR rows. Fix the CSV and preview again.';
		return $out;
	}

	if ($has_delete && !$confirmed_delete) {
		$out['error'] = 'This commit includes deletes. Check the confirmation box and commit again.';
		return $out;
	}

	$current = get_option('vms_holidays', array());
	if (!is_array($current)) {
		$current = array();
	}

	foreach ($plan_rows as $r) {
		$st = isset($r['status']) ? (string) $r['status'] : 'ERROR';
		if (!in_array($st, array('ADD', 'UPDATE', 'DELETE'), true)) {
			continue;
		}

		$venue_id = isset($r['venue_id']) ? (int) $r['venue_id'] : 0;
		$date = isset($r['date_ymd']) ? (string) $r['date_ymd'] : '';
		$before = $r['before'] ?? null;

		$current_before = null;
		if ($venue_id > 0 && $date !== '' && isset($current[$venue_id]) && is_array($current[$venue_id]) && isset($current[$venue_id][$date])) {
			$current_before = $current[$venue_id][$date];
		}

		if ($st === 'ADD') {
			if ($current_before !== null) {
				$out['error'] = 'Data changed since preview (a holiday now exists for ' . $venue_id . ' ' . $date . '). Please preview again.';
				return $out;
			}
			continue;
		}

		if (!vms_dt_holidays_import_payload_equal($before, $current_before)) {
			$out['error'] = 'Data changed since preview for venue ' . $venue_id . ' on ' . $date . '. Please preview again.';
			return $out;
		}
	}

	$modified_venues = array();

	foreach ($plan_rows as $r) {
		$st = isset($r['status']) ? (string) $r['status'] : 'ERROR';

		if ($st === 'NOOP') {
			$out['counts']['noop']++;
			continue;
		}

		if ($st === 'ADD' || $st === 'UPDATE') {
			$venue_id = (int) ($r['venue_id'] ?? 0);
			$date = (string) ($r['date_ymd'] ?? '');
			$after = $r['after'] ?? null;

			if ($venue_id <= 0 || $date === '' || !is_array($after)) {
				$out['counts']['errors']++;
				continue;
			}

			if (!isset($current[$venue_id]) || !is_array($current[$venue_id])) {
				$current[$venue_id] = array();
			}

			$before = $r['before'] ?? null;
			$current[$venue_id][$date] = $after;
			$modified_venues[$venue_id] = true;

			$out['audit']['changes'][] = array(
				'type' => ($st === 'ADD') ? 'add' : 'update',
				'venue_id' => $venue_id,
				'date_ymd' => $date,
				'is_auto' => !empty($r['is_auto']),
				'before' => $before,
				'after' => $after,
			);

			if ($st === 'ADD') {
				$out['counts']['added']++;
			} else {
				$out['counts']['updated']++;
			}

			continue;
		}

		if ($st === 'DELETE') {
			$venue_id = (int) ($r['venue_id'] ?? 0);
			$date = (string) ($r['date_ymd'] ?? '');
			if ($venue_id <= 0 || $date === '') {
				$out['counts']['errors']++;
				continue;
			}

			$before = $r['before'] ?? null;

			if (isset($current[$venue_id]) && is_array($current[$venue_id]) && isset($current[$venue_id][$date])) {
				unset($current[$venue_id][$date]);
				$modified_venues[$venue_id] = true;
			}

			if (isset($current[$venue_id]) && is_array($current[$venue_id]) && empty($current[$venue_id])) {
				unset($current[$venue_id]);
			}

			$out['audit']['changes'][] = array(
				'type' => 'delete',
				'venue_id' => $venue_id,
				'date_ymd' => $date,
				'is_auto' => !empty($r['is_auto']),
				'before' => $before,
				'after' => null,
			);

			$out['counts']['deleted']++;
			continue;
		}

		if ($st === 'ERROR') {
			$out['counts']['errors']++;
		}
	}

	foreach (array_keys($modified_venues) as $venue_id) {
		$venue_id = (int) $venue_id;
		if (isset($current[$venue_id]) && is_array($current[$venue_id])) {
			ksort($current[$venue_id]);
		}
	}

	update_option('vms_holidays', $current, false);

	$audit_entry = array(
		'ts_gmt' => gmdate('Y-m-d H:i:s'),
		'user_id' => get_current_user_id(),
		'action' => 'holidays_import_v2',
		'mode' => $mode,
		'counts' => $out['counts'],
		'source' => is_array($source_meta) ? $source_meta : array(),
		'changes' => $out['audit']['changes'],
	);

	vms_dt_holidays_import_audit_log_append($audit_entry);

	$out['ok'] = true;
	return $out;
}
