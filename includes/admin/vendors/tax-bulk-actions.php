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

function bvmgr_vendor_register_tax_bulk_actions($bulk_actions)
{
	$bulk_actions['vms_tax_mark_complete']   = 'Mark Tax Profile Complete';
	$bulk_actions['vms_tax_mark_incomplete'] = 'Mark Tax Profile Incomplete';
	return $bulk_actions;
}
add_filter('bulk_actions-edit-' . BVMGR_CPT_VENDOR, 'bvmgr_vendor_register_tax_bulk_actions');

function bvmgr_vendor_handle_tax_bulk_actions($redirect_url, $action, $post_ids)
{
	if (!in_array($action, array('vms_tax_mark_complete', 'vms_tax_mark_incomplete'), true)) {
		return $redirect_url;
	}

	$k_done    = bvmgr_meta_key('vendor', 'tax_profile_completed_at');
	$k_attest  = bvmgr_meta_key('vendor', 'w9_attested_at');
	$k_prov    = bvmgr_meta_key('vendor', 'w9_provider');

	$now = time();
	$changed = 0;

	foreach ((array) $post_ids as $vendor_id) {
		$vendor_id = (int) $vendor_id;
		if ($vendor_id <= 0) continue;

		if ($action === 'vms_tax_mark_complete') {
			update_post_meta($vendor_id, $k_done, $now);
			$changed++;
		}

		if ($action === 'vms_tax_mark_incomplete') {
			delete_post_meta($vendor_id, $k_done);
			delete_post_meta($vendor_id, $k_attest);
			delete_post_meta($vendor_id, $k_prov);
			$changed++;
		}
	}

	return add_query_arg(array(
		'vms_tax_bulk_done' => $changed,
		'vms_tax_bulk_action' => $action,
	), $redirect_url);
}
add_filter('handle_bulk_actions-edit-' . BVMGR_CPT_VENDOR, 'bvmgr_vendor_handle_tax_bulk_actions', 10, 3);

function bvmgr_vendor_tax_bulk_admin_notice()
{
	if (empty($_REQUEST['vms_tax_bulk_done'])) return;

	$count  = (int) $_REQUEST['vms_tax_bulk_done'];
	$action = isset($_REQUEST['vms_tax_bulk_action']) ? sanitize_text_field(wp_unslash($_REQUEST['vms_tax_bulk_action'])) : '';

	if ($count <= 0) return;

	if ($action === 'vms_tax_mark_complete') {
		$msg = sprintf('%d vendor tax profile(s) marked complete.', $count);
	} elseif ($action === 'vms_tax_mark_incomplete') {
		$msg = sprintf('%d vendor tax profile(s) marked incomplete.', $count);
	} else {
		return;
	}

	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($msg) . '</p></div>';
}
add_action('admin_notices', 'bvmgr_vendor_tax_bulk_admin_notice');
