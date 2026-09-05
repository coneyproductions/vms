# VMS 0.2.24.748

## Purpose
Stabilize the unreleased public event sidebar and vendor profile follow-up work before PR #1 leaves draft by fixing the current-event sidebar guard, enforcing Vendor Type requirements at runtime, retiring the orphaned legacy Square nightly sync hook with bounded safe-context cleanup, surfacing explicit Admissions session-expired guidance in the admin UI for both VMS and native WordPress REST nonce failures, and documenting the canceled-entry Admissions restore defect that live browser validation exposed and this branch fixed.

## Changes
- Updated `includes/public/event-details.php` and `includes/public/event-sidebar.php` so automatic sidebar insertion only suppresses duplicates inside the real target sidebar, manual current-event sidebar shortcode placement still blocks later auto insertion when it renders first, and explicit shortcode output outside the target sidebar remains available.
- Updated `includes/helpers.php` and `includes/cpt/vendors.php` so stale enabled public-profile meta no longer exposes typeless vendors on the public site and the Vendor editor now explains when a saved profile remains blocked until a Vendor Type is assigned.
- Updated `includes/activation.php` so `vms_square_nightly_sync` now has a retired no-op callback, removes all WP-Cron argument variants, cancels pending Action Scheduler rows, deletes failed/canceled retired-hook history in bounded batches, fails closed when the cron or Action Scheduler query APIs report an error, retries only in safe maintenance contexts, and stores the completion marker only after the full cleanup verifies that no retired-hook cron/action rows remain.
- Updated `includes/modules/admissions/rest.php` and `assets/js/vms-admissions-admin.js` so expired Admissions REST sessions normalize to the exact operator-facing message `Your Admissions session expired. Refresh this page, then try again.` for both `vms_admission_bad_nonce` and native `rest_cookie_invalid_nonce`, while preserving ordinary validation/authorization errors and non-JSON/network failure handling.
- Updated `includes/modules/admissions/rest.php` so a canceled guest-list entry may be restored only through a status-only transition back to `active`, while simultaneous restore-plus-field-edit requests and all other canceled-entry edits remain blocked.
- Added focused regression coverage in `tests/public-event-sidebar-guards.php`, `tests/legacy-square-sync-cleanup.php`, `tests/admissions-js-normalization.js`, and `tests/admissions-rest-patch-restore.php`, and extended `tests/admissions-rest-permissions.php` to keep the server-side expired-session guidance covered.

## Files changed
- `BUILD-NOTES-0.2.24.748.md`
- `assets/js/vms-admissions-admin.js`
- `docs/05-revision-log.md`
- `includes/activation.php`
- `includes/core/registry/constants.php`
- `includes/cpt/vendors.php`
- `includes/helpers.php`
- `includes/modules/admissions/rest.php`
- `includes/public/event-details.php`
- `includes/public/event-sidebar.php`
- `includes/public/load.php`
- `includes/public/vendor-profiles.php`
- `tests/admissions-js-normalization.js`
- `tests/admissions-rest-patch-restore.php`
- `tests/admissions-rest-permissions.php`
- `tests/legacy-square-sync-cleanup.php`
- `tests/public-event-sidebar-guards.php`
- `vendor-management-system.php`
- `vms-build.txt`

## Live local validation
- Environment type: `local`
- Event Plan used: `1430`
- TEC event used: `2881`
- Vendor used: `2581`
- Exact expired-session message observed: `Your Admissions session expired. Refresh this page, then try again.`
- Sidebar DOM counts were one stack, one details card, and one vendor-groups block in all intended scenarios.
- Vendor profile behavior returned `200` with a Vendor Type, `404` without a Vendor Type, and `200` again after Vendor Type reassignment.
- All modified local content, options, widgets, terms, admissions rows, audit rows, users, cron fixtures, and Action Scheduler fixtures were restored or removed after validation.
- Residual limitation: no standalone HTML validator was run.
- Existing TEC/Event Tickets deprecation noise remained in local logs, but no new VMS fatal was observed.

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
- `php tests/admissions-rest-patch-restore.php`
- `node tests/admissions-js-normalization.js`
- `php tests/public-event-sidebar-guards.php`
- `php tests/legacy-square-sync-cleanup.php`
- `php tests/runtime-stub-guards.php`
- completed live local retired-hook cleanup validation
- completed Admissions expired-session browser validation
- completed Admissions add/edit/check-in/undo/void/restore lifecycle
- completed public sidebar auto/manual/target-widget scenarios
- completed Vendor Type profile gating scenarios
- no new VMS fatal in the live-validation logs
- no Square request observed
- no email send observed
- all temporary local fixtures restored or deleted
- local active plugin tree restored after testing
- no production access, deployment, merge, or package build
- version consistency checks for `vendor-management-system.php`, `includes/core/registry/constants.php`, `vms-build.txt`, `docs/05-revision-log.md`, and `BUILD-NOTES-0.2.24.748.md`
- `git diff --check`
- final diff review against `origin/main`
- package integrity verification against the supplied production `0.2.24.747` ZIP

## Package
- No new package built in this pass.
- Production `0.2.24.747` provenance records and ZIP remained untouched.
