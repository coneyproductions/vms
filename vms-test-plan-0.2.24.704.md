# VMS 0.2.24.704 Test Plan — Event Plan Performance Packaging Regression

## Pre-checks

1. Install/activate VMS `0.2.24.704`.
2. Confirm version markers:
   - Plugin page shows `0.2.24.704`.
   - `vms/includes/core/registry/constants.php` defines `VMS_VERSION` as `0.2.24.704`.
   - `vms/vms-build.txt` begins with `0.2.24.704`.
3. Confirm a heavy local Event Plan equivalent to plan `76` is available with ticketing, supporting acts, primary/secondary vendors, staffing, linked TEC event, and a featured image.

## Event Plan open/edit regression

1. Open the heavy Event Plan edit screen.
2. Expected:
   - Command Center ticket card renders without the prior heavy upfront ticket-report query path.
   - Ticketing section still shows summary-first behavior.
   - Staff section still lazy-loads on demand.
   - Secondary Vendors section still lazy-loads on demand.
   - Readiness details still load on demand.
   - Supporting vendor cards still expand/collapse correctly.

## Event Plan save/update regression

1. Save/Update with no changes.
2. Save after changing only a basic field.
3. Save after changing featured image.
4. Save after changing vendor/staffing-related fields.
5. Publish/republish.
6. Extra staffing-only save.
7. Extra vendor-only save.
8. Expected:
   - no-change Update stays on a single `save_post` pass
   - no internal Event Plan `wp_update_post()` pass on no-op title sync
   - unchanged vendor data skips secondary-vendor rebuild and vendor calendar maintenance
   - unchanged staffing data skips staffing heavy work
   - publish/republish still preserves deferred calendar publish and ticket integrity spot-scan behavior

## Browser/admin smoke

1. Open Event Plan `76`.
2. Confirm:
   - Command Center ticket card still appears with essential summary/status
   - `Open Full Ticket Report` opens the full Command Center ticket snapshot/report
   - current primary vendor displays correctly
   - supporting vendor selects hydrate correctly
   - no PHP warnings/notices on the page
   - no new browser console errors
3. Note:
   - the pre-existing unrelated `404` on `/wp-includes/css/jquery-ui.min.css?ver=1.13.2` may still appear and does not block this package

## Local automated checks

1. Run:
   - `php -l vms/vendor-management-system.php`
   - `php -l vms/includes/core/registry/constants.php`
   - `php -l vms/includes/core/event-plan-performance.php`
   - `php -l vms/includes/admin/event-command-center.php`
   - `node --check vms/assets/js/vms-lineup-schedule-admin.js`
2. Re-run the local Event Plan perf scenario suite with `php /tmp/vms_ep_perf_runner.php`.
3. Expected:
   - syntax checks pass
   - no-change Update remains clean
   - open-edit behavior stays close to Patch 10 and does not regress toward the earlier `600` query path
