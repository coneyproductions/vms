<?php
defined('ABSPATH') || exit;

const VMS_DT_CAP_IMPORT_VENDORS = 'vms_import_vendors';

function vms_dt_register_capabilities(): void {
	// This function exists so we have one place to define capability names.
	// Actual granting to roles happens on activation and can also be run later via grant function.
}

function vms_dt_grant_default_capabilities(): void {
	$role = get_role('administrator');
	if ($role instanceof WP_Role) {
		$role->add_cap(VMS_DT_CAP_IMPORT_VENDORS);
	}
}
