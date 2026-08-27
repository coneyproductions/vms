<?php

/**
 * VMS Admin Menu Slugs
 */

defined('ABSPATH') || exit;

/**
 * Return canonical admin menu slugs used across VMS.
 *
 * @return array<string,string>
 */
function bvmgr_admin_menu_slugs(): array
{
  $slugs = [
    // Parent/top menu slug
    'top'          => 'vms-dashboard',

    // Core pages
    'dashboard'    => 'vms-dashboard',
    'vendors'      => 'vms-vendors',
    'staff'        => 'vms-staff',
    'event_plans'  => 'vms-event-plans',
    'ratings'      => 'vms-ratings',
    'applications' => 'vms-applications',
    'schedule'     => 'vms-schedule',
    'season_dates' => 'vms-season-dates',
    'settings'     => 'vms-settings',
    'status_notices' => 'vms-status-notices',
    'passes'       => 'vms-passes',
    'ticket_integrity' => 'vms-ticket-integrity',
    'docs'         => 'vms-docs',

    // Add-on slot (Locations/Venues) — only used if add-on is active
    'locations'    => 'vms-locations',

    // Keep these if used elsewhere
    'data_tools'   => 'vms_data_tools',
    'admin_cal'    => 'vms_admin_calendar',
  ];

  return apply_filters('vms_admin_menu_slugs', $slugs);
}

/**
 * Convenience getter so code reads cleanly:
 * vms_admin_menu_slug('data_tools')
 */
function bvmgr_admin_menu_slug(string $key): string
{
	$slugs = bvmgr_admin_menu_slugs();
	return isset($slugs[$key]) ? (string) $slugs[$key] : '';
}
