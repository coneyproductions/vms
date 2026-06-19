# VMS Test Plan 0.2.24.658 — Ticketing V2 Minimal AJAX Response + Save Profile Diagnostics

## Safety note for Codex

Use staging credentials or the normal Codex/browser testing environment. Do not request Apple Events access or control the user's local authenticated Chrome session unless the user explicitly approves that method.

If you modify test harness files, summarize those separately. Do not modify VMS plugin code unless explicitly instructed.

## A. Version marker

1. Confirm `/wp-content/plugins/vms/vms-build.txt` returns `0.2.24.658`.
2. Confirm wp-admin loaded VMS assets use `0.2.24.658` cache-busting/version markers.

Expected: both show `0.2.24.658`.

## B. Ticketing V2 Save Config no-op response timing

Use an existing Event Plan with a saved Ticketing V2 config.

1. Open DevTools/Network and filter `admin-ajax.php`.
2. Click **Save config** without changing ticket rows.
3. Repeat twice.
4. Inspect the `vms_ticketing_v2_save_config` response.

Expected response data:

- `success: true`
- `config_changed: false`
- `fast_response: true`
- `minimal_response: true`
- `config_omitted: true`
- `X-VMS-Fast-Ajax: ticketing-v2-save-config`
- `handler_elapsed_ms` present
- `request_age_at_handler_ms` present
- `raw_config_bytes` present

Record:

- browser wait time;
- `elapsed_ms`;
- `handler_elapsed_ms`;
- `request_age_at_handler_ms`;
- response transfer size.

Pass expectation: browser wait time should be materially lower than 0.2.24.657's 31–35s no-op saves. If it is not lower, use the timing fields to identify whether the delay is before the handler or after the handler.

## C. Fresh-plan default template stale-date warning

1. Create a fresh Event Plan with a future show date/time.
2. Confirm Ticketing V2 initializes from the default template.
3. Confirm ticket `sales_end` values resolve to the new show datetime, not stale 2026-04-10 values.
4. Confirm no stale-date warning appears when the server-repaired values are already in the live config.

Expected: fresh-plan default-template repair still passes as it did in 0.2.24.657.

## D. Preview chain timing

1. On the fresh plan or existing test plan, click **Preview sync**.
2. Confirm the browser first sends `vms_ticketing_v2_save_config` and then `vms_ticketing_v2_preview_sync`.
3. Inspect both responses.

Expected save response:

- `minimal_response: true`
- `config_omitted: true`
- `fast_response: true`

Expected preview response:

- `success: true` for valid configs, or real validation error for invalid configs;
- `X-VMS-Fast-Ajax: ticketing-v2-preview-sync` on successful preview responses;
- `ajax_timing.preview_elapsed_ms` present;
- `ajax_timing.handler_elapsed_ms` present;
- `ajax_timing.request_age_at_handler_ms` present.

Record browser wait time versus server timing fields.

## E. Normal Event Plan save profile visibility

1. Make a small non-ticketing edit, such as appending ` test` to the Event Plan title and saving as Draft or normal Update.
2. Record the client-side save duration.
3. Reload the edit screen if needed.
4. Inspect the **VMS Save Profile** side metabox.

Expected:

- A slow save profile is visible if the save exceeded the profiler threshold.
- The profile shows elapsed time, status, meta writes, ticket config writes, ticket sync writes, notes, and top meta keys.
- Ordinary Draft/Ready saves should not queue a new immediate Ticket Integrity spot scan.

## F. Cancellation / safer integrity path

1. Use a staging Event Plan where cancellation/safer integrity checks are acceptable.
2. Trigger the safer cancellation path.
3. Confirm Ticket Integrity is queued once for the cancellation/published-type path.
4. Immediately perform a normal core Update on the cancelled plan.

Expected:

- Cancellation queues the appropriate integrity follow-up once.
- Immediate normal Update does not add duplicate integrity queue/audit rows.

## G. Invalid preview validation

1. Create or force an invalid early pricing window, such as early start after early end.
2. Click **Preview sync**.

Expected:

- Preview is blocked with the real validation message.
- The failure is a validation result, not a browser timeout.

If direct DOM manipulation is used to create invalid state because Playwright cannot fill a hidden input, label this as backend/automation-forced validation testing rather than a pure user-click UI-path test.

## H. Public sanity checks, if staging content permits

1. Check a public future event with Ticketing V2 enabled.
2. Confirm no disabled/legacy Early General Admission row leaks publicly.
3. Confirm early/regular runtime pricing still behaves as expected in cart/checkout.

If staging event status or permissions block public visibility, record as blocked, not failed.
