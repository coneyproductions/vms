<?php
defined('ABSPATH') || exit;

/**
 * Dependency check for Backstage Venue Manager or legacy VMS Core.
 *
 * We use a simple “capability/feature function exists” check instead of relying on plugin slug,
 * because your core may move or be renamed.
 */
function vms_dt_is_vms_core_active(): bool {
	/**
	 * Choose ONE canonical function to represent “VMS Core loaded”.
	 * Update this when core exposes its public API.
	 */
	if (vms_dt_has_core_function('vms_core')) {
		return true;
	}

	// Alternate “public API” function names you may introduce in core:
	if (vms_dt_has_core_function('vms_vendor_upsert')) {
		return true;
	}

	return false;
}

function vms_dt_admin_notice_missing_core(): void {
	if (!current_user_can('activate_plugins')) {
		return;
	}

	?>
	<div class="notice notice-warning">
		<p>
			<strong>VMS Data Tools</strong> is installed but inactive by dependency:
			Backstage Venue Manager or legacy VMS Core is not detected. Activate the venue-management core to enable Data Tools.
		</p>
	</div>
	<?php
}
