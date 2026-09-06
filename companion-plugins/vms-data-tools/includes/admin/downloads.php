<?php
if (!defined('ABSPATH')) {
	exit;
}

function vms_dt_clean_output_buffers(): void
{
	while (ob_get_level() > 0) {
		ob_end_clean();
	}
}

function vms_dt_download_csv_response(string $filename, string $csv): void
{
	vms_dt_clean_output_buffers();

	nocache_headers();
	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename="' . $filename . '"');
	header('X-Content-Type-Options: nosniff');
	echo $csv;
	exit;
}

function vms_dt_adminpost_download_holidays_sample_csv()
{
	if (!function_exists('vms_dt_current_user_can_import') || !vms_dt_current_user_can_import()) {
		wp_die('You do not have permission to download this file.');
	}

	check_admin_referer('vms_dt_holidays_sample_csv');

	// Pick a real venue if possible, so operators can use the sample without hunting for slugs.
	$example_venue_name = 'YOUR VENUE NAME HERE';
	$example_venue_slug = 'your-venue-slug';
	$example_venue_id = '';

	$venues = get_posts(array(
		'post_type'      => 'vms_venue',
		'post_status'    => array('publish', 'draft', 'private'),
		'posts_per_page' => 1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	));

	if (!empty($venues) && is_object($venues[0])) {
		if (!empty($venues[0]->post_title)) {
			$example_venue_name = (string) $venues[0]->post_title;
		}
		if (!empty($venues[0]->post_name)) {
			$example_venue_slug = (string) $venues[0]->post_name;
		}
		if (!empty($venues[0]->ID)) {
			$example_venue_id = (string) ((int) $venues[0]->ID);
		}
	}

	$fh = fopen('php://temp', 'w+');

	// You may fill ONE of: venue_name OR venue_slug OR venue_id.
	fputcsv($fh, array(
		'venue_name',
		'venue_slug',
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

	// Example using venue_name (recommended for humans).
	fputcsv($fh, array(
		$example_venue_name,
		'',
		'',
		'2026-12-25',
		'Christmas Day',
		'closed',
		'flat_fee',
		'250',
		'',
		'fixed_christmas',
		'',
		'',
	));

	// Example using venue_slug.
	fputcsv($fh, array(
		'',
		$example_venue_slug,
		'',
		'2026-01-01',
		"New Year's Day",
		'open',
		'door_split',
		'',
		'80',
		'fixed_new_year',
		'upsert',
		'',
	));

	// Example using venue_id.
	fputcsv($fh, array(
		'',
		'',
		$example_venue_id,
		'2026-07-04',
		'Independence Day',
		'open',
		'',
		'',
		'',
		'',
		'upsert',
		'{"vendor":{"structure":"flat_fee_door_split","flat_fee_amount":200,"door_split_percent":80}}',
	));

	// Explicit delete.
	fputcsv($fh, array(
		$example_venue_name,
		'',
		'',
		'2026-02-14',
		'',
		'',
		'',
		'',
		'',
		'',
		'delete',
		'',
	));

	rewind($fh);
	$csv = stream_get_contents($fh);
	fclose($fh);

	vms_dt_download_csv_response('vms-holidays-sample.csv', $csv);
}

add_action('admin_post_vms_dt_download_holidays_sample_csv', 'vms_dt_adminpost_download_holidays_sample_csv');

function vms_dt_adminpost_download_holidays_backup_csv()
{
	if (!function_exists('vms_dt_current_user_can_import') || !vms_dt_current_user_can_import()) {
		wp_die('You do not have permission to download this file.');
	}

	check_admin_referer('vms_dt_holidays_backup_csv');

	$backup_key = isset($_GET['k']) ? (string) $_GET['k'] : '';
	$backup_key = trim($backup_key);
	if ($backup_key === '') {
		wp_die('Missing backup key.');
	}

	if (!function_exists('vms_dt_holidays_import_load_backup')) {
		wp_die('Backup loader not available.');
	}

	$payload = vms_dt_holidays_import_load_backup($backup_key);
	if (!is_array($payload) || empty($payload['csv'])) {
		wp_die('Backup not found or expired. Preview again to generate a new backup.');
	}

	$user_id = get_current_user_id();
	if (!empty($payload['user_id']) && (int) $payload['user_id'] !== (int) $user_id) {
		wp_die('You do not have permission to download this backup.');
	}

	$filename = !empty($payload['filename']) ? (string) $payload['filename'] : 'vms-holidays-backup.csv';
	$csv = (string) $payload['csv'];

	vms_dt_download_csv_response($filename, $csv);
}

add_action('admin_post_vms_dt_download_holidays_backup_csv', 'vms_dt_adminpost_download_holidays_backup_csv');
