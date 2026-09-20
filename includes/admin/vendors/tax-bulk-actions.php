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

	if (!isset($_REQUEST['_wpnonce']) || !is_string($_REQUEST['_wpnonce'])) {
		wp_die(esc_html__('Security check failed.', 'backstage-venue-manager'), '', array('response' => 403));
	}
	check_admin_referer('bulk-posts');

	if (!is_array($post_ids)) {
		wp_die(esc_html__('Invalid vendor selection.', 'backstage-venue-manager'));
	}

	// Validate the entire selection before writing, including mixed-ownership batches.
	$vendor_ids = array();
	foreach ($post_ids as $post_id) {
		$vendor_id = (is_int($post_id) || is_string($post_id))
			? filter_var($post_id, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)))
			: false;
		if (!$vendor_id || get_post_type($vendor_id) !== BVMGR_CPT_VENDOR) {
			wp_die(esc_html__('Invalid vendor selection.', 'backstage-venue-manager'));
		}
		if (!current_user_can('edit_post', $vendor_id)) {
			wp_die(esc_html__('Permission denied.', 'backstage-venue-manager'), '', array('response' => 403));
		}
		$vendor_ids[] = $vendor_id;
	}

	$k_done    = bvmgr_meta_key('vendor', 'tax_profile_completed_at');
	$k_attest  = bvmgr_meta_key('vendor', 'w9_attested_at');
	$k_prov    = bvmgr_meta_key('vendor', 'w9_provider');

	$now = time();
	$changed = 0;

	foreach ($vendor_ids as $vendor_id) {
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
