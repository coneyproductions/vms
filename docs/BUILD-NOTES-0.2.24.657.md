# VMS 0.2.24.657 Build Notes

## Purpose

This patch follows the 0.2.24.656 staging test where PHP reported Ticketing V2 save work completing quickly, but wp-admin/Chrome still waited long enough to trip the browser timeout. It also reduces repeated Event Plan save queue churn while the larger publishing-efficiency audit continues.

## Changes

### Ticketing V2 save-config AJAX fast response

`vms_ticketing_v2_ajax_save_config()` now returns its success payload through a small fast-response helper.

The payload still includes:

- `config`
- `config_hash`
- `config_changed`
- `had_saved_config`
- `image_sync_count`
- `elapsed_ms`

New marker:

- `fast_response: true`

The response header also includes:

- `X-VMS-Fast-Ajax: ticketing-v2-save-config`

This is intended to prevent the browser from waiting on unrelated PHP shutdown/admin-ajax tail work after the Ticketing V2 save payload is already ready.

### Ticketing V2 admin timeout buffer

Ticketing V2 save-config client calls now default to a 60-second timeout instead of 20 seconds. The main fix is the fast response helper, but the longer timeout keeps the UI from declaring false failure too aggressively on slow shared hosting.

### Default template stale-date warning cleanup

Fresh Event Plans were correctly receiving repaired `sales_end` values from the server, but the admin UI still displayed the stale default-template review warning because it was checking the raw template guardrail summary.

The fresh-plan auto-default path now checks the rendered/repaired config first. If the rendered config no longer has stale Sales end values, it saves the repaired default config directly and does not show the stale warning.

### Event Plan save queue pressure reduction

Ordinary Draft/Ready Event Plan saves no longer queue an immediate Ticket Integrity spot scan just because the plan was saved. Ticketing config/sync meta changes are still watched separately, and explicit publish/cancellation actions can still queue a spot scan.

Published/cancelled plan-save spot scans are throttled for 5 minutes by default to avoid repeated queue/meta churn during back-to-back edits.

### Staff task queue dedupe

Repeated Event Plan saves with the same staff-task signature now skip re-queuing staff task generation. If a queue item is already pending, VMS avoids rewriting the same task queue metadata rows on every editor save.

## Files changed

- `vendor-management-system.php`
- `vms-build.txt`
- `assets/admin-ticketing.js`
- `includes/core/registry/constants.php`
- `includes/integrations/ticketing-phase-b.php`
- `includes/modules/staff-tasks/generator.php`
- `includes/ticketing/ticket-integrity-cron.php`
- `docs/BUILD-NOTES-0.2.24.657.md`
- `vms-test-plan-0.2.24.657.md`

## Static checks

- PHP lint passed across plugin PHP files.
- `node --check assets/admin-ticketing.js` passed.

## Notes for Codex

Codex may make small, directly related repairs during testing if they are necessary to complete the stated validation. If code is changed, update the VMS version/build marker consistently in `vendor-management-system.php`, `includes/core/registry/constants.php`, and `vms-build.txt`, and document the change.

Use staging credentials or the normal Codex/browser testing environment. Do not request Apple Events access or control the user's local authenticated Chrome session unless the user explicitly approves that method.
