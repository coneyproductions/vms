<?php
defined('ABSPATH') || exit;

/**
 * Assign Vendor Type taxonomy term from a CSV string.
 */
function vms_dt_vendor_set_type_term(int $vendor_id, string $type_name): void {
	$type_name = trim($type_name);
	if ($type_name === '') return;

	$taxonomy = 'vms_vendor_type';

	$term = term_exists($type_name, $taxonomy);
	if (!$term) {
		$term = wp_insert_term($type_name, $taxonomy);
	}
	if (is_wp_error($term)) return;

	$term_id = is_array($term) ? (int) $term['term_id'] : (int) $term;
	wp_set_object_terms($vendor_id, [$term_id], $taxonomy, false);
}
