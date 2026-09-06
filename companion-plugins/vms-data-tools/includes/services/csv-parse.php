<?php
defined('ABSPATH') || exit;

/**
 * Parse an uploaded CSV file into rows keyed by header.
 *
 * @return array{ok:bool, headers?:string[], rows?:array<int, array<string,string>>, errors?:array}
 */

function vms_dt_csv_header_aliases(): array
{
	return [
		// Core required
		'display_name' => [
			'display_name',
			'name',
			'vendor_name',
			'band_name',
			'artist_name',
		],
		'vendor_type' => [
			'vendor_type',
			'type',
			'category',
			'vendor_category',
		],

		// Matching keys
		'external_ref' => [
			'external_ref',
			'external_id',
			'vendor_id',
			'ref',
		],
		'primary_email' => [
			'primary_email',
			'email',
			'email_address',
		],
		'primary_phone' => [
			'primary_phone',
			'phone',
			'phone_number',
			'mobile',
		],

		// Address
		'city'        => ['city', 'town'],
		'state'       => ['state', 'province', 'region'],
		'postal_code' => ['postal_code', 'zip', 'zip_code'],
		'country'     => ['country'],
	];
}

function vms_dt_csv_parse_uploaded_file(array $file): array
{
	if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
		return vms_dt_result_error('file_missing', 'No uploaded file found.');
	}

	$fh = fopen($file['tmp_name'], 'rb');
	if ($fh === false) {
		return vms_dt_result_error('file_open_failed', 'Failed to open uploaded file.');
	}

	// Read first line as headers.
	$raw_headers = fgetcsv($fh);
	if (!is_array($raw_headers) || count($raw_headers) === 0) {
		fclose($fh);
		return vms_dt_result_error('csv_no_headers', 'CSV headers row is missing or invalid.');
	}

	$headers = vms_dt_csv_normalize_headers($raw_headers);
	$headers = vms_dt_csv_apply_header_aliases($headers);
	if (count($headers) === 0) {
		fclose($fh);
		return vms_dt_result_error('csv_headers_empty', 'CSV headers resolved to empty values.');
	}

	$rows = [];
	$row_num = 1; // header row = 1

	while (($fields = fgetcsv($fh)) !== false) {
		$row_num++;

		// Skip fully empty lines.
		if (!is_array($fields) || vms_dt_csv_row_is_empty($fields)) {
			continue;
		}

		$row = [];
		foreach ($headers as $i => $key) {
			$val = isset($fields[$i]) ? (string) $fields[$i] : '';
			$row[$key] = vms_dt_csv_clean_cell($val);
		}

		$rows[] = [
			'__row_num' => (string) $row_num,
		] + $row;
	}

	fclose($fh);

	return [
		'ok'      => true,
		'headers' => $headers,
		'rows'    => $rows,
	];
}

/**
 * Normalize header cells:
 * - strip UTF-8 BOM on first header
 * - trim
 * - keep original case? We choose lowercase for stability
 */
function vms_dt_csv_normalize_headers(array $raw_headers): array
{
	$headers = [];

	foreach ($raw_headers as $i => $h) {
		$h = (string) $h;

		// Strip UTF-8 BOM if present on the first header.
		if ($i === 0) {
			$h = preg_replace('/^\xEF\xBB\xBF/', '', $h) ?? $h;
		}

		$h = trim($h);

		// Normalize to lowercase for stable matching.
		$h = strtolower($h);

		$headers[] = $h;
	}

	// Remove empty header names (but keep index alignment by leaving them out is dangerous).
	// Instead, we keep empties but they won’t be used; validation will catch missing required columns.
	return $headers;
}

function vms_dt_csv_row_is_empty(array $fields): bool
{
	foreach ($fields as $v) {
		if (trim((string) $v) !== '') {
			return false;
		}
	}
	return true;
}

function vms_dt_csv_clean_cell(string $val): string
{
	$val = trim($val);

	// Normalize newlines and remove invisible non-breaking spaces.
	$val = str_replace(["\r\n", "\r"], "\n", $val);
	$val = str_replace("\xC2\xA0", ' ', $val);

	return trim($val);
}

function vms_dt_csv_apply_header_aliases(array $headers): array
{
	$aliases = vms_dt_csv_header_aliases();
	$resolved = [];

	foreach ($headers as $h) {
		$h = strtolower(trim($h));
		$mapped = null;

		foreach ($aliases as $canonical => $list) {
			if (in_array($h, $list, true)) {
				$mapped = $canonical;
				break;
			}
		}

		$resolved[] = $mapped ?? $h;
	}

	return $resolved;
}
