<?php
defined('ABSPATH') || exit;

require_once __DIR__ . '/plugin.php';
require_once __DIR__ . '/vendor-user-links.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/private-files.php';
require_once __DIR__ . '/../helpers/schedule-helpers.php';
require_once __DIR__ . '/../helpers/checkin-close.php';
require_once __DIR__ . '/event-reschedule.php';
require_once __DIR__ . '/../helpers/image-normalization.php';
require_once __DIR__ . '/../calendar-queries.php';
require_once __DIR__ . '/../activation.php';
require_once __DIR__ . '/secondary-vendor-assignments.php';
require_once __DIR__ . '/../vendor-applications.php';
require_once __DIR__ . '/vendor-application-confirmation.php';
require_once __DIR__ . '/event-plan-performance.php';
require_once __DIR__ . '/slow-request-logger.php';

require_once __DIR__ . '/validation/vendor.php';

/**
 * CPT
 */
require_once __DIR__ . '/../cpt/vendors.php';
require_once __DIR__ . '/../cpt/venues.php';
require_once __DIR__ . '/../cpt/event-plans.php';
require_once __DIR__ . '/event-plan-save-profiler.php';
require_once __DIR__ . '/../cpt/ratings.php';
require_once __DIR__ . '/../cpt/staff.php';

require_once __DIR__ . '/event-plan-inclusion.php';
require_once __DIR__ . '/calendar-feed.php';
require_once __DIR__ . '/calendar-ticket-counts.php';
require_once __DIR__ . '/cancellation.php';
require_once __DIR__ . '/cancellation-adapters.php';
require_once __DIR__ . '/event-credits.php';
require_once __DIR__ . '/goals-forecast.php';

require_once __DIR__ . '/tax-bypass.php';

require_once __DIR__ . '/payables.php';
require_once __DIR__ . '/ticket-revenue.php';
require_once __DIR__ . '/ticket-sales-resolver.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/vendor-document-alerts.php';
require_once __DIR__ . '/lineup-schedule.php';
require_once __DIR__ . '/event-plan-review.php';
require_once __DIR__ . '/event-feedback.php';
require_once __DIR__ . '/vendor-booking-onboarding.php';

// Due Dates / Compliance Obligations (dashboard + admin)
require_once __DIR__ . '/due-dates.php';

// Staffing roles / slots / assignments / rollups (STAFF-01 Phase A)
require_once __DIR__ . '/staffing.php';

if (defined('WP_CLI') && WP_CLI) {
	require_once __DIR__ . '/cli/stale-check.php';
	require_once __DIR__ . '/cli/state-of-range.php';
	require_once __DIR__ . '/cli/square-ticket-mirror.php';
	require_once __DIR__ . '/cli/event-reschedule.php';
}

do_action('vms_core_loaded');
