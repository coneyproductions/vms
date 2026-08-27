<?php
if (!defined('ABSPATH')) exit;

/**
 * ⚠️ ARCHITECTURE RULE (DO NOT VIOLATE)
 *
 * This file MUST NOT reference raw meta keys (e.g. '_vms_*').
 * All meta keys MUST come from meta-keys.php via vms_meta_key().
 * If a required key is missing, STOP and add it to meta-keys.php first.
 */

require_once __DIR__ . '/../../core/registry/meta-keys.php';
require_once __DIR__ . '/../../core/registry/constants.php';

function vms_vendor_tax_export_button()
{
	global $typenow;
	if ($typenow !== BVMGR_CPT_VENDOR) return;
	if (!current_user_can('manage_options')) return;

	$url = wp_nonce_url(
		add_query_arg(array('action' => 'vms_vendor_tax_export_csv'), admin_url('admin-post.php')),
		'vms_vendor_tax_export_csv'
	);

	echo '<a class="button vms-vendor-tax-export-btn" href="' . esc_url($url) . '">Export 1099-ready CSV</a>';
}
add_action('restrict_manage_posts', 'vms_vendor_tax_export_button', 20);

function vms_vendor_tax_export_csv_adminpost()
{
	if (!current_user_can('manage_options')) wp_die('Permission denied.');
	check_admin_referer('vms_vendor_tax_export_csv');

	$k_done = bvmgr_meta_key('vendor', 'tax_profile_completed_at');

	$vendor_ids = get_posts(array(
		'post_type'      => BVMGR_CPT_VENDOR,
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The nonce- and capability-gated 1099 export intentionally enumerates every Vendor with completed tax-profile metadata so the CSV is complete.
			array('key' => $k_done, 'compare' => 'EXISTS'),
		),
		'fields'         => 'ids',
	));

	$filename = 'vms-1099-ready-vendors-' . date('Y-m-d') . '.csv';

	nocache_headers();
	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename=' . $filename);

	$out = fopen('php://output', 'w');

	fputcsv($out, array(
		'vendor_id',
		'vendor_name',
		'payee_legal_name',
		'dba',
		'entity_type',
		'address1',
		'address2',
		'city',
		'state',
		'zip',
		'tax_provider',
		'vendor_confirmed_date',
		'tax_profile_completed_date',
		'w9_file_on_site',
	));

	$k_legal  = bvmgr_meta_key('vendor', 'payee_legal_name');
	$k_dba    = bvmgr_meta_key('vendor', 'payee_dba');
	$k_entity = bvmgr_meta_key('vendor', 'entity_type');

	$k_addr1  = bvmgr_meta_key('vendor', 'addr1');
	$k_addr2  = bvmgr_meta_key('vendor', 'addr2');
	$k_city   = bvmgr_meta_key('vendor', 'city');
	$k_state  = bvmgr_meta_key('vendor', 'state');
	$k_zip    = bvmgr_meta_key('vendor', 'zip');

	$k_attest = bvmgr_meta_key('vendor', 'w9_attested_at');
	$k_prov   = bvmgr_meta_key('vendor', 'w9_provider');
	$k_upload = bvmgr_meta_key('vendor', 'w9_upload_id');

	foreach ((array) $vendor_ids as $vendor_id) {
		$vendor_id = (int) $vendor_id;
		if ($vendor_id <= 0) continue;

		$done_at   = (int) get_post_meta($vendor_id, $k_done, true);
		$attest_at = (int) get_post_meta($vendor_id, $k_attest, true);

		$provider = (string) get_post_meta($vendor_id, $k_prov, true);
		if ($provider === '') $provider = 'upload';

		$provider_label = 'Upload';
		if ($provider === 'quickbooks_email') $provider_label = 'QuickBooks Online';
		if ($provider === 'tax1099_email')    $provider_label = 'Tax1099';

		$w9_upload_id = (int) get_post_meta($vendor_id, $k_upload, true);
		$w9_on_site = ($w9_upload_id > 0) ? 'yes' : 'no';

		fputcsv($out, array(
			$vendor_id,
			get_the_title($vendor_id),
			(string) get_post_meta($vendor_id, $k_legal, true),
			(string) get_post_meta($vendor_id, $k_dba, true),
			(string) get_post_meta($vendor_id, $k_entity, true),
			(string) get_post_meta($vendor_id, $k_addr1, true),
			(string) get_post_meta($vendor_id, $k_addr2, true),
			(string) get_post_meta($vendor_id, $k_city, true),
			(string) get_post_meta($vendor_id, $k_state, true),
			(string) get_post_meta($vendor_id, $k_zip, true),
			$provider_label,
			$attest_at > 0 ? date('Y-m-d', $attest_at) : '',
			$done_at > 0 ? date('Y-m-d', $done_at) : '',
			$w9_on_site,
		));
	}

	fclose($out);
	exit;
}
add_action('admin_post_vms_vendor_tax_export_csv', 'vms_vendor_tax_export_csv_adminpost');
