<?php
defined('ABSPATH') || exit;

// Vendor list columns are owned by: includes/admin/vendor-list-ui.php.
// Do not load legacy column injectors here or they will duplicate/conflict.

/**
 * Admin-only includes.
 *
 * Keep this file as the canonical admin bootstrap.
 * Prefer normalized local paths for files that live in includes/admin/.
 */
require_once __DIR__ . '/../admin-ui/helpers.php';
require_once __DIR__ . '/../admin-ui/context.php';
require_once __DIR__ . '/../admin-ui/shell.php';
require_once __DIR__ . '/../admin-ui/nav.php';
require_once __DIR__ . '/../admin-ui/assets.php';

require_once __DIR__ . '/menu.php';
require_once __DIR__ . '/settings-page.php';
require_once __DIR__ . '/settings/class-vms-settings-notifications.php';
require_once __DIR__ . '/settings/notifications-user-profile.php';
require_once __DIR__ . '/integrity-venue-reconcile.php';
require_once __DIR__ . '/integrity-calendar-reconcile.php';
require_once __DIR__ . '/budget-calculator.php';
require_once __DIR__ . '/event-profitability-report.php';
require_once __DIR__ . '/event-feedback.php';
require_once __DIR__ . '/cancelled-event-cost-review.php';
require_once __DIR__ . '/goals-forecast.php';
require_once __DIR__ . '/season-dates.php';
require_once __DIR__ . '/season-board.php';
require_once __DIR__ . '/continuity-binder.php';
require_once __DIR__ . '/ticket-integrity-page.php';
require_once __DIR__ . '/../ticketing/ticket-integrity-tours.php';
require_once __DIR__ . '/docs-page.php';

require_once __DIR__ . '/dashboard.php';
require_once __DIR__ . '/due-dates.php';
require_once __DIR__ . '/approvals-review-queue.php';
require_once __DIR__ . '/schedule.php';
require_once __DIR__ . '/admin-notices.php';
require_once __DIR__ . '/venue-context.php';
// Express Bar moved to standalone VMS Express Bar module.
require_once __DIR__ . '/holidays.php';

require_once __DIR__ . '/vendor-comp-packages.php';
require_once __DIR__ . '/vendor-details.php';
require_once __DIR__ . '/vendor-command-center.php';
require_once __DIR__ . '/event-command-center.php';
require_once __DIR__ . '/vendor-booking-onboarding.php';
require_once __DIR__ . '/vendor-availability.php';
require_once __DIR__ . '/staffing.php';
require_once __DIR__ . '/staff-certifications.php';

require_once __DIR__ . '/staff-list-columns.php';
require_once __DIR__ . '/staff-tax-sidebar.php';
require_once __DIR__ . '/staff-worker-type.php';
require_once __DIR__ . '/staff-user-link.php';
require_once __DIR__ . '/vendor-staff-link.php';
require_once __DIR__ . '/staff-vendor-link.php';
require_once __DIR__ . '/tax-profile-admin-metabox.php';
require_once __DIR__ . '/tax-bypass.php';
require_once __DIR__ . '/tax-bypass-ajax.php';
require_once __DIR__ . '/venue-comp-defaults.php';
require_once __DIR__ . '/venue-duplicate.php';
require_once __DIR__ . '/venue-calendar.php';
require_once __DIR__ . '/venue-health-check.php';
require_once __DIR__ . '/pages-tools.php';
require_once __DIR__ . '/vendor-user-link.php';
require_once __DIR__ . '/woo-product-event-columns.php';
require_once __DIR__ . '/square-sync-protection.php';
require_once __DIR__ . '/square-ticket-mirror.php';
require_once __DIR__ . '/addons/class-vms-addons-logger.php';
require_once __DIR__ . '/addons/class-vms-addons-manifest.php';
require_once __DIR__ . '/addons/class-vms-addons-installer.php';
require_once __DIR__ . '/addons/class-vms-addons-licensing.php';
require_once __DIR__ . '/addons/class-vms-addons-health.php';
require_once __DIR__ . '/addons/class-vms-admin-addons.php';

require_once __DIR__ . '/vendors/tax-bulk-actions.php';
require_once __DIR__ . '/vendors/tax-metabox.php';
require_once __DIR__ . '/vendors/tax-filters.php';
require_once __DIR__ . '/vendors/tax-export-csv.php';

require_once __DIR__ . '/vendor-list-ui.php';


$vms_ref_keys_map = __DIR__ . '/reference/keys-map.php';
if (file_exists($vms_ref_keys_map)) {
    require_once $vms_ref_keys_map;
}
 
add_action('admin_post_vms_save_continuity_binder', 'vms_admin_post_save_continuity_binder');
