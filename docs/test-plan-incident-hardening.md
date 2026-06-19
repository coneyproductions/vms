# VMS Incident Hardening Test Plan

## Scope

Validate the missing-migrations guard, cron cleanup, schedule hardening, public-request boundaries, and Data Tools translation-load cleanup.

## Build gate

1. Run `php tests/check-package-integrity.php vms`.
2. Build the release zip.
3. Run `php tests/check-package-integrity.php /path/to/vms-release.zip`.
4. Expected:
   - Both commands return `Package integrity OK.`
   - If `vms/includes/db/migrations.php` is missing from the source tree or zip, the command exits non-zero and names the missing file.

## Disabled-state regression

1. Deactivate VMS core.
2. Load the homepage, one event page, `/wp-cron.php`, and the WP admin dashboard.
3. Expected:
   - No VMS fatal.
   - No new `invalid_schedule` entries for `vms_social_process_queue`, `vms_tasks_notifications_tick`, or other `vms_*` hooks.
   - Existing VMS recurring and single cron events are removed after deactivation.

## Enabled-state smoke test

1. Activate VMS core on staging.
2. Visit:
   - Homepage
   - One public event page
   - Cart
   - Checkout
   - `/wp-cron.php`
3. Expected:
   - No fatal errors.
   - Missing `includes/db/migrations.php` no longer hard-fails the request. If the file is intentionally removed in a non-production test, the site stays up and an admin-only diagnostic appears on the next admin request.

## Cron and schedule hardening

1. With VMS active, visit the homepage as a logged-out user.
2. Confirm recurring VMS jobs are not newly scheduled from that public request alone.
3. Visit an admin page or trigger `wp-cron.php`.
4. Expected:
   - Required recurring VMS jobs are present after admin/cron runtime.
   - Custom schedules exist before Social and Staff Tasks recurring hooks are scheduled.

## Debug-log noise check

1. Clear `wp-content/debug.log`.
2. Load 20 mixed requests:
   - 10 public pages
   - 5 admin VMS pages
   - 5 Data Tools/Vendor Invites pages
3. Expected:
   - No early translation-loading notices from VMS Data Tools or VMS tours.
   - No WooCommerce text-domain or dependency notices caused by early Data Tools boot.
   - `debug.log` stays small and does not grow with repeated `invalid_schedule` noise.

## Performance spot check

1. Capture before/after metrics for:
   - Query count
   - Total load time
2. Compare on:
   - Homepage
   - Public event page
   - Cart
   - Checkout
3. Expected:
   - No regression from the hardening pass.
   - Public requests should avoid unnecessary VMS admin-only/email-follow-up/runtime-maintenance work.
