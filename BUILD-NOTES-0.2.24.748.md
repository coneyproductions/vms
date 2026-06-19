# VMS 0.2.24.748

## Purpose
Stabilize the unreleased public event sidebar and vendor profile follow-up work before PR #1 leaves draft by fixing the current-event sidebar guard, enforcing Vendor Type requirements at runtime, retiring the orphaned legacy Square nightly sync hook with bounded safe-context cleanup, and surfacing explicit Admissions session-expired guidance in the admin UI for both VMS and native WordPress REST nonce failures.

## Changes
- Updated `includes/public/event-details.php` and `includes/public/event-sidebar.php` so automatic sidebar insertion only suppresses duplicates inside the real target sidebar, manual current-event sidebar shortcode placement still blocks later auto insertion when it renders first, and explicit shortcode output outside the target sidebar remains available.
- Updated `includes/helpers.php` and `includes/cpt/vendors.php` so stale enabled public-profile meta no longer exposes typeless vendors on the public site and the Vendor editor now explains when a saved profile remains blocked until a Vendor Type is assigned.
- Updated `includes/activation.php` so `vms_square_nightly_sync` now has a retired no-op callback, removes all WP-Cron argument variants, cancels pending Action Scheduler rows, deletes failed/canceled retired-hook history in bounded batches, fails closed when the cron or Action Scheduler query APIs report an error, retries only in safe maintenance contexts, and stores the completion marker only after the full cleanup verifies that no retired-hook cron/action rows remain.
- Updated `includes/modules/admissions/rest.php` and `assets/js/vms-admissions-admin.js` so expired Admissions REST sessions normalize to the exact operator-facing message `Your Admissions session expired. Refresh this page, then try again.` for both `vms_admission_bad_nonce` and native `rest_cookie_invalid_nonce`, while preserving ordinary validation/authorization errors and non-JSON/network failure handling.
- Added focused regression coverage in `tests/public-event-sidebar-guards.php`, `tests/legacy-square-sync-cleanup.php`, and `tests/admissions-js-normalization.js`, and extended `tests/admissions-rest-permissions.php` to keep the server-side expired-session guidance covered.

## Files changed
- `assets/js/vms-admissions-admin.js`
- `includes/activation.php`
- `includes/cpt/vendors.php`
- `includes/helpers.php`
- `includes/modules/admissions/rest.php`
- `includes/public/event-details.php`
- `includes/public/event-sidebar.php`
- `tests/admissions-rest-permissions.php`
- `tests/admissions-js-normalization.js`
- `tests/legacy-square-sync-cleanup.php`
- `tests/public-event-sidebar-guards.php`
- `docs/05-revision-log.md`
- `BUILD-NOTES-0.2.24.748.md`

## Validation
- `php -l includes/public/event-details.php`
- `php -l includes/public/event-sidebar.php`
- `php -l includes/helpers.php`
- `php -l includes/cpt/vendors.php`
- `php -l includes/activation.php`
- `php -l includes/modules/admissions/rest.php`
- `php -l tests/admissions-rest-permissions.php`
- `php -l tests/public-event-sidebar-guards.php`
- `php -l tests/legacy-square-sync-cleanup.php`
- `node --check assets/js/vms-admissions-admin.js`
- `node --check tests/admissions-js-normalization.js`
- `php tests/admissions-rest-permissions.php`
- `node tests/admissions-js-normalization.js`
- `php tests/public-event-sidebar-guards.php`
- `php tests/legacy-square-sync-cleanup.php`
- `php tests/runtime-stub-guards.php`
- version consistency checks for `vendor-management-system.php`, `includes/core/registry/constants.php`, `vms-build.txt`, `docs/05-revision-log.md`, and `BUILD-NOTES-0.2.24.748.md`
- `git diff --check`
- final diff review against `origin/main`
- `php tests/check-package-integrity.php /Users/treyconey/Local\\ Sites/serenade-range-local-test-site/app/public/wp-content/plugins/packages/vms-final-0.2.24.747-artifacts/vms-0.2.24.747-public-release.zip`

## Package
- No new package built in this pass.
- Production `0.2.24.747` provenance records and ZIP remained untouched.
