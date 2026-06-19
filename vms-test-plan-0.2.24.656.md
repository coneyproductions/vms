# VMS 0.2.24.656 Test Plan — Save-Path Pressure + Ticketing V2 No-Op Guard

## Target behavior

This patch should make repeated Event Plan Draft/Ready/Update and Ticketing V2 Save config actions less expensive without breaking the confirmed 0.2.24.655 Ticketing V2 listener fix or the 0.2.24.654 early/regular pricing behavior.

## Pre-test setup

1. Use staging.
2. Confirm `/wp-content/plugins/vms/vms-build.txt` reports `0.2.24.656`.
3. Clear plugin/page/object cache if staging uses caching.
4. Open browser DevTools Network tab and filter for `admin-ajax.php`.
5. If possible, enable `WP_DEBUG_LOG` so Event Plan save profiles can be reviewed.

Codex may make small, directly-related code repairs when feasible during testing/troubleshooting. If code changes are made, Codex must update the VMS version/build number consistently.

## A. Fresh Event Plan default-template stale date check

1. Create a fresh Event Plan with a future show date/time.
2. Let Ticketing V2 default-template auto-apply.
3. Inspect the Ticketing rows before saving.

Expected:

- The fresh plan should not inherit a stale old `sales_end` date such as `2026-04-10`.
- Ticket sales end should resolve to the current Event Plan show datetime when the template had a stale earlier `sales_end`.
- Save config and Preview sync buttons remain clickable and fire requests.

## B. Save config no-op check

1. On a fresh or existing Event Plan, open Ticketing V2.
2. Click **Save config** once.
3. Click **Save config** again without changing anything.
4. Inspect the second AJAX response.

Expected on the second save:

- `vms_ticketing_v2_save_config` returns success.
- Response includes `config_changed: false`.
- Response includes a reasonable `elapsed_ms` value.
- No visible editor error.

Expected on the first save for a truly new unsaved config:

- `config_changed: true` is acceptable because the config is being persisted for the first time.

## C. Existing guardrail/existing-plan save reliability

Re-test the branch that previously appeared inconsistent/hung.

1. Use an existing Event Plan with Ticketing V2 config.
2. Trigger or use a stale Sales end guardrail case if available.
3. Click **Save config**.
4. Use **Apply template and reset Sales end** if the guardrail appears.
5. Click **Save config** again.
6. Click **Preview sync**.

Expected:

- `vms_ticketing_v2_save_config` returns promptly.
- No request hangs indefinitely.
- Preview sync still returns either a valid preview or a real validation message.

## D. Event Plan Draft/Ready/Update save profiler

1. Save an Event Plan as Draft.
2. Save the same Event Plan as Ready.
3. Use normal WordPress **Update** on an existing Event Plan.
4. If any save feels slow, inspect `_vms_last_save_profile` on that Event Plan or the debug log.

Expected:

- Slow saves store a compact `_vms_last_save_profile` array.
- Profile includes `elapsed_ms`, `meta_writes`, `ticket_config_writes`, `ticket_sync_writes`, and `top_meta_keys`.
- Ticket Integrity note should show `scheduled` once and `already_scheduled` on repeated saves while the same spot scan is pending.
- No customer/order payloads or full ticket config JSON should be stored in the profile.

Optional forced-profiler check:

- Temporarily define `VMS_EVENT_PLAN_SAVE_PROFILER_ALWAYS` as true on staging.
- Save an Event Plan.
- Confirm `_vms_last_save_profile` updates even for a fast save.
- Remove the temporary define after testing.

## E. Ticket Integrity duplicate queue-log reduction

1. Save the same Event Plan several times within 90 seconds.
2. Review Ticket Integrity logs if available.

Expected:

- Only the first save should write a fresh “spot scan queued” log while a spot scan is pending.
- Repeated saves should not create duplicate queue log spam for the same pending scan.

## F. Early/regular pricing regression smoke

1. Configure a public General Admission ticket with:
   - Regular price: `25`
   - Early price: `20`
   - Early ends: a future date/time
2. Save config, Preview sync, and Commit sync.
3. Confirm the public/cart price is `$20` during the early window.
4. Change Early ends to a past date/time.
5. Save/Preview/Commit again.
6. Confirm the public/cart price is `$25` after the early window.

## G. 0.2.24.655 listener regression smoke

1. Create another fresh Event Plan.
2. Let the default template auto-apply.
3. Click **Save config**.
4. Click **Preview sync**.

Expected:

- Neither button is dead.
- AJAX requests fire as expected.

## H. Public ticket visibility regression checks

For an event that can be publicly viewed:

1. Confirm enabled public GA appears for logged-out visitors.
2. Confirm disabled ticket rows do not appear publicly.
3. Confirm disabled qualified/free ticket products cannot become public/free just because config was saved but not pushed.
4. Confirm no legacy **Early General Admission** row leaks publicly when only current GA should appear.

## Pass criteria

- Save config no-op returns success with `config_changed: false` on repeated unchanged saves.
- Fresh default-template plans do not inherit stale old `sales_end` dates.
- Existing/guardrail save-config paths do not hang.
- Event Plan saves are profiled when slow enough to matter.
- Ticket Integrity does not spam duplicate queue logs while a spot scan is already scheduled.
- Early/regular pricing and public visibility regressions remain clean.
