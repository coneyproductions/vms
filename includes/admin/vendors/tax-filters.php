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

function bvmgr_vendor_tax_filter_ui()
{
	global $typenow;
	if ($typenow !== BVMGR_CPT_VENDOR) return;

	$current = isset($_GET['vms_tax_status']) ? sanitize_text_field(wp_unslash($_GET['vms_tax_status'])) : '';

	echo '<select name="vms_tax_status">';
	echo '<option value=""' . selected($current, '', false) . '>All Tax Statuses</option>';
	echo '<option value="complete"' . selected($current, 'complete', false) . '>Tax Complete</option>';
	echo '<option value="incomplete"' . selected($current, 'incomplete', false) . '>Tax Incomplete</option>';
	echo '</select>';
}
add_action('restrict_manage_posts', 'bvmgr_vendor_tax_filter_ui');

function bvmgr_vendor_tax_filter_query($query)
{
	if (!is_admin() || !$query->is_main_query()) return;

	$post_type = $query->get('post_type');
	if ($post_type !== BVMGR_CPT_VENDOR) return;

	$status = isset($_GET['vms_tax_status']) ? sanitize_text_field(wp_unslash($_GET['vms_tax_status'])) : '';
	if ($status === '') return;

	$k_done = bvmgr_meta_key('vendor', 'tax_profile_completed_at');

	if ($status === 'complete') {
		$query->set('meta_query', array(
			array('key' => $k_done, 'compare' => 'EXISTS'),
		));
	}

	if ($status === 'incomplete') {
		$query->set('meta_query', array(
			array('key' => $k_done, 'compare' => 'NOT EXISTS'),
		));
	}
}
add_action('pre_get_posts', 'bvmgr_vendor_tax_filter_query');
