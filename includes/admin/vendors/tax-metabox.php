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
 
function vms_vendor_tax_metabox_register()
{
	add_meta_box(
		'vms_vendor_tax_status',
		'Tax Status',
		'vms_vendor_tax_metabox_render',
		VMS_CPT_VENDOR,
		'side',
		'high'
	);
}
add_action('add_meta_boxes', 'vms_vendor_tax_metabox_register');

function vms_vendor_tax_provider_label($provider)
{
	if ($provider === 'quickbooks_email') return 'QuickBooks Online';
	if ($provider === 'tax1099_email') return 'Tax1099';
	return 'Upload';
}

function vms_vendor_tax_metabox_render($post)
{
	if (!$post || $post->post_type !== VMS_CPT_VENDOR) return;

	$vendor_id = (int) $post->ID;

	$k_done    = vms_meta_key('vendor', 'tax_profile_completed_at');
	$k_attest  = vms_meta_key('vendor', 'w9_attested_at');
	$k_prov    = vms_meta_key('vendor', 'w9_provider');
	$k_upload  = vms_meta_key('vendor', 'w9_upload_id');

	$done_at    = (int) get_post_meta($vendor_id, $k_done, true);
	$attest_at  = (int) get_post_meta($vendor_id, $k_attest, true);

	// Provider semantics:
	// - The global setting controls what workflow is used going forward.
	// - A vendor's stored provider is treated as historical (which workflow they completed under).
	//   If the vendor is not yet complete, we show the current global provider to avoid confusion.
	$settings = get_option('vms_settings', array());
	$settings = is_array($settings) ? $settings : array();
	$global_provider = isset($settings['tax_w9_provider']) ? (string) $settings['tax_w9_provider'] : 'upload';
	if (!in_array($global_provider, array('upload', 'quickbooks_email', 'tax1099_email'), true)) {
		$global_provider = 'upload';
	}

	$stored_provider = (string) get_post_meta($vendor_id, $k_prov, true);
	if (!in_array($stored_provider, array('upload', 'quickbooks_email', 'tax1099_email'), true)) {
		$stored_provider = '';
	}

	$effective_provider = ($done_at > 0 && $stored_provider !== '') ? $stored_provider : $global_provider;

	$upload_id  = (int) get_post_meta($vendor_id, $k_upload, true);
	$upload_url = $upload_id && function_exists('vms_private_w9_download_url') ? vms_private_w9_download_url($vendor_id) : '';
	$upload_label = $upload_id && function_exists('vms_private_w9_file_label') ? vms_private_w9_file_label($vendor_id) : '';

	echo '<div class="vms-tax-box">';

	$tz = wp_timezone();

	if ($done_at > 0) {
		echo '<div class="vms-tax-pill-wrap"><span class="vms-tax-pill vms-tax-pill--complete">Complete</span></div>';
	} else {
		echo '<div class="vms-tax-pill-wrap"><span class="vms-tax-pill vms-tax-pill--incomplete">Incomplete</span></div>';
	}

	echo '<p class="vms-tax-row"><strong>Provider:</strong> ' . esc_html(vms_vendor_tax_provider_label($effective_provider)) . '</p>';
	if ($done_at <= 0) {
		echo '<p class="vms-tax-subrow">Uses current global setting</p>';
	} elseif ($stored_provider !== '' && $stored_provider !== $global_provider) {
		echo '<p class="vms-tax-subrow">Current global setting: ' . esc_html(vms_vendor_tax_provider_label($global_provider)) . '</p>';
	}

	echo '<p class="vms-tax-row"><strong>Completed:</strong> ' . ($done_at > 0 ? esc_html(wp_date('Y-m-d', $done_at, $tz)) : '—') . '</p>';
	echo '<p class="vms-tax-row"><strong>Vendor confirmed:</strong> ' . ($attest_at > 0 ? esc_html(wp_date('Y-m-d', $attest_at, $tz)) : '—') . '</p>';

	if ($upload_url) {
		echo '<p class="vms-tax-row vms-tax-row--file"><strong>W-9 File:</strong> <a href="' . esc_url($upload_url) . '" target="_blank" rel="noopener">' . esc_html($upload_label !== '' ? $upload_label : __('Download', 'backstage-venue-manager')) . '</a></p>';
	} else {
		echo '<p class="vms-tax-row vms-tax-row--file"><strong>W-9 File:</strong> —</p>';
	}

	$complete_url = wp_nonce_url(
		add_query_arg(array('action' => 'vms_vendor_tax_mark_complete', 'vendor_id' => $vendor_id), admin_url('admin-post.php')),
		'vms_vendor_tax_mark_complete_' . $vendor_id
	);

	$incomplete_url = wp_nonce_url(
		add_query_arg(array('action' => 'vms_vendor_tax_mark_incomplete', 'vendor_id' => $vendor_id), admin_url('admin-post.php')),
		'vms_vendor_tax_mark_incomplete_' . $vendor_id
	);

	echo '<p class="vms-tax-actions"><a class="button button-primary" href="' . esc_url($complete_url) . '">Mark Complete</a></p>';
	echo '<p class="vms-tax-actions"><a class="button" href="' . esc_url($incomplete_url) . '">Mark Incomplete</a></p>';

	echo '</div>';
}

function vms_vendor_tax_adminpost_mark_complete()
{
	$vendor_id = isset($_GET['vendor_id']) ? (int) $_GET['vendor_id'] : 0;
	if ($vendor_id <= 0) wp_die('Invalid vendor.');
	if (!current_user_can('edit_post', $vendor_id)) wp_die('Permission denied.');

	check_admin_referer('vms_vendor_tax_mark_complete_' . $vendor_id);

	$k_done = vms_meta_key('vendor', 'tax_profile_completed_at');
	$k_recv = vms_meta_key('vendor', 'w9_received_date');
	$k_prov = vms_meta_key('vendor', 'w9_provider');

	update_post_meta($vendor_id, $k_done, time());

	// Marking complete implies the signed W-9 has been received through the configured workflow.
	$recv = (string) get_post_meta($vendor_id, $k_recv, true);
	if (trim($recv) === '') {
		update_post_meta($vendor_id, $k_recv, wp_date('Y-m-d', time(), wp_timezone()));
	}

	// Persist the global provider for clarity in the UI.
	$settings = get_option('vms_settings', array());
	$settings = is_array($settings) ? $settings : array();
	$global = isset($settings['tax_w9_provider']) ? (string) $settings['tax_w9_provider'] : '';
	if (!in_array($global, array('upload', 'quickbooks_email', 'tax1099_email'), true)) {
		$global = 'upload';
	}
	update_post_meta($vendor_id, $k_prov, $global);

	wp_safe_redirect(add_query_arg(array('post' => $vendor_id, 'action' => 'edit', 'vms_tax_notice' => 'complete'), admin_url('post.php')));
	exit;
}

add_action('admin_post_vms_vendor_tax_mark_complete', 'vms_vendor_tax_adminpost_mark_complete');

function vms_vendor_tax_adminpost_mark_incomplete()
{
	$vendor_id = isset($_GET['vendor_id']) ? (int) $_GET['vendor_id'] : 0;
	if ($vendor_id <= 0) wp_die('Invalid vendor.');
	if (!current_user_can('edit_post', $vendor_id)) wp_die('Permission denied.');

	check_admin_referer('vms_vendor_tax_mark_incomplete_' . $vendor_id);

	$k_done   = vms_meta_key('vendor', 'tax_profile_completed_at');
	$k_attest = vms_meta_key('vendor', 'w9_attested_at');
	$k_prov   = vms_meta_key('vendor', 'w9_provider');

	delete_post_meta($vendor_id, $k_done);
	delete_post_meta($vendor_id, $k_attest);
	delete_post_meta($vendor_id, $k_prov);

	wp_safe_redirect(add_query_arg(array('post' => $vendor_id, 'action' => 'edit', 'vms_tax_notice' => 'incomplete'), admin_url('post.php')));
	exit;
}
add_action('admin_post_vms_vendor_tax_mark_incomplete', 'vms_vendor_tax_adminpost_mark_incomplete');

function vms_vendor_tax_metabox_admin_notice()
{
	if (empty($_GET['vms_tax_notice'])) return;
	$v = sanitize_text_field(wp_unslash($_GET['vms_tax_notice']));

	if ($v === 'complete') {
		echo '<div class="notice notice-success is-dismissible"><p>Vendor tax profile marked complete.</p></div>';
	} elseif ($v === 'incomplete') {
		echo '<div class="notice notice-success is-dismissible"><p>Vendor tax profile marked incomplete.</p></div>';
	}
}
add_action('admin_notices', 'vms_vendor_tax_metabox_admin_notice');
