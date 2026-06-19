# VMS 0.2.24.657 Test Plan

## Scope

Validate the Ticketing V2 save-config latency fix, fresh-plan default-template UX cleanup, and Event Plan save queue-pressure reductions introduced after the 0.2.24.656 staging findings.

## Access / safety

Use staging credentials or the normal Codex/browser testing environment. Do not request Apple Events access or control the user's local authenticated Chrome session unless the user explicitly approves that method.

Codex may make small, directly related repairs during testing if necessary. If code is changed, update the VMS version/build marker consistently and document the repair.

## A. Build marker

1. Open `/wp-content/plugins/vms/vms-build.txt` on staging.
2. Confirm the first line is `0.2.24.657`.
3. Confirm plugin header/admin asset versions also report `0.2.24.657` where visible.

Expected: staging is serving VMS 0.2.24.657.

## B. Ticketing V2 Save config response timing

Use an existing Event Plan with a Ticketing V2 config, preferably the same/similar plan used for the 0.2.24.656 timeout test.

1. Open the Event Plan in wp-admin.
2. Open DevTools Network and filter `admin-ajax.php`.
3. Click **Save config** without changing anything.
4. Inspect the `vms_ticketing_v2_save_config` response.

Expected:

- The request returns success.
- Response includes `config_changed: false` for unchanged config.
- Response includes `fast_response: true`.
- Response header includes `X-VMS-Fast-Ajax: ticketing-v2-save-config` when visible.
- Browser request duration should no longer wait 30-50 seconds when server `elapsed_ms` is small.
- The UI should not show a false browser timeout.

Repeat Save config twice to confirm repeated no-op saves remain prompt.

## C. Fresh Event Plan default template stale-date UX

1. Create a new Event Plan on staging with a future event date/time.
2. Let Ticketing V2 default-template initialization run.
3. Inspect Ticketing V2 ticket rows.
4. Check whether the old stale `sales_end` warning appears.

Expected:

- Ticket rows use the fresh Event Plan show datetime for repaired Sales end values.
- The old default-template stale-date warning should not appear when the rendered config has already been repaired.
- If the UI auto-saves the repaired default config, `vms_ticketing_v2_save_config` should return success and `fast_response: true`.

## D. Preview sync after save

On the same fresh Event Plan:

1. Click **Preview sync**.
2. Watch Network requests.

Expected:

- `vms_ticketing_v2_save_config` fires first and returns promptly.
- `vms_ticketing_v2_preview_sync` fires after save-config succeeds.
- Preview should either produce valid actions or a real validation block, not a save-config timeout.

## E. Event Plan normal save pressure

Use a fresh or existing staging Event Plan.

1. Make a harmless title/body edit.
2. Click the normal WordPress **Update** button.
3. Record total browser wait time.
4. Repeat a second harmless edit/save.

Expected:

- Total save time should improve compared with the prior ~64-second staging observation, especially on the second save.
- No immediate Ticket Integrity spot scan should be queued solely for ordinary Draft/Ready saves.
- Staff task queue should not rewrite the same queue metadata on repeated saves with the same event signature.

If available, inspect `_vms_last_save_profile` for notes such as:

- `ticket_integrity_plan_save: skipped_workflow_*`
- `ticket_integrity_plan_save: skipped_throttled`
- `staff_tasks_queue: skipped_unchanged_signature`
- `staff_tasks_queue: already_scheduled`

## F. Published/cancelled safety check

On a staging-only test plan where it is safe to do so:

1. Exercise a publish or cancellation-related action that should still warrant integrity follow-up.
2. Confirm VMS can still queue Ticket Integrity where appropriate.

Expected:

- Publish/cancellation paths are not blocked by the new Draft/Ready skip.
- Spot scan queueing is throttled rather than repeated on rapid back-to-back saves.

## G. Regression checks from 0.2.24.655/656

Confirm the following are not broken:

- Fresh Event Plan Save config button still fires AJAX.
- Preview sync button still fires save-config then preview-sync.
- Early/regular runtime pricing still resolves correctly for a synced ticket product.
- Invalid preview cases still block with real validation messages.
- Disabled/legacy Early GA rows do not leak onto public pages.

## H. Report results

Report:

- Build marker observed.
- Save config request duration vs response `elapsed_ms`.
- Whether `fast_response: true` appeared.
- Whether stale default-template warning is gone when repaired config is valid.
- Normal Event Plan save duration on first and repeated saves.
- Any `_vms_last_save_profile` notes found.
- Any remaining timeout/hang path, including exact action, plan ID, and browser/server timings.
