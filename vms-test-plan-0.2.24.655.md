# VMS 0.2.24.655 Test Plan — Fresh Event Plan Default Template Button Binding

## Target behavior

Fresh Event Plans with Ticketing V2 enabled and a default Ticketing V2 template configured must have working admin buttons after the default template auto-apply path runs.

This specifically verifies that 0.2.24.654's early-pricing feature still works while fixing the JavaScript initializer regression found during staging testing.

## Pre-test setup

1. Use staging.
2. Confirm VMS reports `0.2.24.655` in `vms-build.txt` and plugin/admin asset cache is cleared.
3. Ensure Ticketing V2 has a default template configured.
4. The default template should include one public **General Admission** ticket with:
   - Regular price: `25`
   - Early price: `20`
   - Early ends: a future date/time

## Fresh-plan UI blocker regression

1. Create a fresh Event Plan.
2. Enable/use Ticketing V2 so the default template auto-applies.
3. Open browser DevTools Network tab and filter for `admin-ajax.php`.
4. Click **Save config**.
   - Expected: a `vms_ticketing_v2_save_config` request fires.
   - Expected: the button is not dead/no-op.
5. Click **Preview sync**.
   - Expected: a `vms_ticketing_v2_save_config` request fires, followed by a `vms_ticketing_v2_preview_sync` request.
   - Expected: preview renders or shows a real validation message.
6. If preview is clean, click **Commit sync**.
   - Expected: `vms_ticketing_v2_commit_sync` requests fire.
   - Expected: TEC event/ticket sync proceeds through the normal batching flow.

## Guardrail branch regression

Use a default template whose Sales end date is stale relative to the new Event Plan show date/time.

1. Create a fresh Event Plan that triggers the default-template Sales end guardrail.
2. Confirm the guardrail appears.
3. Without reloading, click **Save config**.
   - Expected: Save config still fires a `vms_ticketing_v2_save_config` request.
4. Use **Apply template and reset Sales end**.
   - Expected: template applies.
5. Click **Preview sync**.
   - Expected: preview request fires and works normally.

## Early-pricing smoke regression

1. On the same event/product, configure:
   - Regular price: `25`
   - Early price: `20`
   - Early ends: a future date/time
2. Save config, Preview sync, and Commit sync.
3. Confirm the same GA product resolves at `$20` during the early window.
4. Move Early ends to a past date/time, Save/Preview/Commit again.
5. Confirm the same GA product resolves at `$25` after the early window.

## Guardrail smoke regression

Preview sync should still block:

- early price equal to regular price
- early price higher than regular price
- early price without Early ends
- Early starts after Early ends

## Public event-page verification

If the test account can publish the created TEC event:

1. Publish the linked TEC event.
2. Open the public event URL in a clean/private browser.
3. Confirm one public **General Admission** row appears.
4. Confirm active price matches the current early/regular window.
5. Add to cart and confirm cart/checkout price matches.

If the test account cannot publish the TEC event, record this as blocked by staging permissions rather than a VMS failure.

## Regression checks

- Existing Event Plans with saved Ticketing V2 config still load and save.
- Existing events without early price still use regular price.
- Disabled-ticket public hiding from 0.2.24.650–0.2.24.653 still works.
- Legacy GA public visibility guard from 0.2.24.653 still works.
- Direct authenticated AJAX calls for save/preview/commit still succeed as they did in 0.2.24.654 testing.

## Pass criteria

- Fresh Event Plans using the default-template auto-apply path no longer have dead Save config or Preview sync buttons.
- Early/regular single-ticket pricing behavior from 0.2.24.654 remains intact.
- Invalid early-price setups remain blocked before managed sync.
