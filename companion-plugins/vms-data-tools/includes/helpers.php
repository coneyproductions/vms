<?php
defined('ABSPATH') || exit;

function vms_dt_current_user_can_import(): bool {
	if (defined('VMS_DT_CAP_IMPORT_VENDORS') && is_string(VMS_DT_CAP_IMPORT_VENDORS) && VMS_DT_CAP_IMPORT_VENDORS !== '') {
		return current_user_can((string) VMS_DT_CAP_IMPORT_VENDORS);
	}
	return current_user_can('manage_options');
}

function vms_dt_manage_capability(): string {
	$core_capability = vms_dt_core_constant('VMS_CAP_MANAGE_DATA_TOOLS', '');
	if (is_string($core_capability) && $core_capability !== '') {
		return $core_capability;
	}
	if (defined('VMS_DT_CAP_IMPORT_VENDORS') && is_string(VMS_DT_CAP_IMPORT_VENDORS) && VMS_DT_CAP_IMPORT_VENDORS !== '') {
		return (string) VMS_DT_CAP_IMPORT_VENDORS;
	}
	return 'manage_options';
}

function vms_dt_current_user_can_manage_tools(): bool {
	return current_user_can(vms_dt_manage_capability()) || current_user_can('manage_options');
}

function vms_dt_admin_url(string $page_slug): string {
	return admin_url('admin.php?page=' . urlencode($page_slug));
}

function vms_dt_get_menu_slug_data_tools(): string {
	return 'vms-data-tools';
}

function vms_dt_get_menu_slug_vendor_import(): string {
	return 'vms-data-tools-vendor-import';
}

function vms_dt_get_menu_slug_events_import(): string {
	return 'vms-dt-events-import';
}

function vms_dt_get_menu_slug_holidays_import(): string {
	return 'vms-dt-holidays-import';
}

function vms_dt_get_menu_slug_payables_export(): string {
	return 'vms-dt-payables-export';
}


function vms_dt_get_menu_slug_ticket_revenue_export(): string {
	return 'vms-dt-ticket-revenue-export';
}

function vms_dt_get_menu_slug_square_ticket_merge(): string {
	return 'vms-dt-square-ticket-merge';
}

function vms_dt_get_menu_slug_revenue_intelligence(): string {
	return 'vms-dt-revenue-intelligence';
}

function vms_dt_get_menu_slug_vendor_invites(): string {
	return 'vms-dt-vendor-invites';
}


function vms_dt_get_menu_slug_reporting_single_event(): string {
    return 'vms-dt-report-single-event';
}

function vms_dt_get_menu_slug_reporting_compare_events(): string {
    return 'vms-dt-report-compare-events';
}

function vms_dt_get_menu_slug_reporting_season_year(): string {
    return 'vms-dt-report-season-year';
}

function vms_dt_get_menu_slug_reporting_performer_payouts(): string {
    return 'vms-dt-report-performer-payouts';
}

function vms_dt_get_menu_slug_reporting_profitability(): string {
    return 'vms-dt-report-profitability';
}

function vms_dt_get_menu_slug_reporting_ticket_pace(): string {
    return 'vms-dt-report-ticket-pace';
}

function vms_dt_get_menu_slug_reporting_audit_tools(): string {
    return vms_dt_get_menu_slug_revenue_intelligence();
}

function vms_dt_rest_namespace(): string {
	return 'vms-data-tools/v1';
}
