<?php
defined('ABSPATH') || exit;

function vms_dt_core_meta_keys(): array {
	return [
		'website_url',
		'facebook_url',
		'instagram_url',
		'epk_url',
		'stage_plot_url',
		'rate_default',
		'travel_radius_miles',
		'tags',
		'notes_internal',
		'notes_public',
	];
}

/**
 * Save meta from row headers "meta:*".
 */
function vms_dt_vendor_save_meta_from_row(int $vendor_id, array $row): array {
	$meta = [];

	foreach ($row as $k => $v) {
		if (strpos($k, 'meta:') !== 0) {
			continue;
		}
		$key = trim(substr($k, 5));
		if ($key === '' || $v === '') {
			continue;
		}
		$meta[$key] = (string) $v;
	}

	if (empty($meta)) {
		return ['ok' => true, 'saved' => 0];
	}

	$core_keys = vms_dt_core_meta_keys();

	$saved = 0;
	foreach ($meta as $key => $val) {
		$store_key = in_array($key, $core_keys, true) ? $key : 'custom_' . $key;

		// Prefer Core meta API if available
		if (function_exists('vms_vendor_set_meta')) {
			vms_vendor_set_meta($vendor_id, $store_key, $val);
			$saved++;
			continue;
		}

		// DB fallback
		global $wpdb;
		$t_meta = $wpdb->prefix . 'vms_vendor_meta';

		$exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($t_meta)));
		if (!$exists) {
			return [
				'ok' => false,
				'error' => vms_dt_err('vendor_meta_table_missing', 'Vendor meta table does not exist: ' . $t_meta),
			];
		}

		// Upsert
		$wpdb->replace(
			$t_meta,
			[
				'vendor_id'  => $vendor_id,
				'meta_key'   => $store_key,
				'meta_value' => $val,
				'updated_at' => current_time('mysql'),
			],
			['%d','%s','%s','%s']
		);
		$saved++;
	}

	return ['ok' => true, 'saved' => $saved];
}
