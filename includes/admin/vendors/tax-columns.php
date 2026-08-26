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

function vms_admin_vendor_tax_columns_add($cols)
{
	$new = array();

	foreach ($cols as $k => $v) {
		$new[$k] = $v;
		if ($k === 'title') {
			$new['vms_tax_provider'] = 'Tax Provider';
			$new['vms_tax_status']   = 'Tax Status';
			$new['vms_tax_done']     = 'Tax Completed';
			$new['vms_w9_file']      = 'W-9 File';
		}
	}

	// Fallback if 'title' column not present
	if (!isset($new['vms_tax_provider'])) {
		$new['vms_tax_provider'] = 'Tax Provider';
		$new['vms_tax_status']   = 'Tax Status';
		$new['vms_tax_done']     = 'Tax Completed';
		$new['vms_w9_file']      = 'W-9 File';
	}

	return $new;
}
add_filter('manage_edit-' . BVMGR_CPT_VENDOR . '_columns', 'vms_admin_vendor_tax_columns_add', 20);

function vms_admin_vendor_tax_columns_render($col, $post_id)
{
	if (get_post_type($post_id) !== BVMGR_CPT_VENDOR) return;

	$k_done     = vms_meta_key('vendor', 'tax_profile_completed_at');
	$k_provider = vms_meta_key('vendor', 'w9_provider');
	$k_upload   = vms_meta_key('vendor', 'w9_upload_id');

	if ($col === 'vms_tax_provider') {
		$provider = (string) get_post_meta($post_id, $k_provider, true);
		if ($provider === '') $provider = 'upload';

		echo esc_html(
			$provider === 'quickbooks_email' ? 'QuickBooks Online' :
			($provider === 'tax1099_email' ? 'Tax1099' : 'Upload')
		);
		return;
	}

	if ($col === 'vms_tax_status') {
		$done_at = (int) get_post_meta($post_id, $k_done, true);

		echo $done_at > 0
			? '<span class="vms-vendor-tax-cols-pill vms-vendor-tax-cols-pill-complete">Complete</span>'
			: '<span class="vms-vendor-tax-cols-pill vms-vendor-tax-cols-pill-incomplete">Incomplete</span>';
		return;
	}

	if ($col === 'vms_tax_done') {
		$done_at = (int) get_post_meta($post_id, $k_done, true);
		echo $done_at > 0 ? esc_html(date_i18n('Y-m-d', $done_at)) : '—';
		return;
	}

	if ($col === 'vms_w9_file') {
		$attach_id = (int) get_post_meta($post_id, $k_upload, true);
		if ($attach_id > 0) {
			$url = wp_get_attachment_url($attach_id);
			echo $url ? '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">View</a>' : 'On file';
		} else {
			echo '—';
		}
		return;
	}
}
add_action('manage_' . BVMGR_CPT_VENDOR . '_posts_custom_column', 'vms_admin_vendor_tax_columns_render', 10, 2);
