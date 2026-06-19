# VMS 0.2.24.658 — Ticketing V2 Minimal Save Response + Save Profile Visibility

## Purpose

This build follows 0.2.24.657 after staging confirmed that the Ticketing V2 save-config backend work is fast, but the browser can still wait 30–50 seconds for the response.

The goal is to separate and reduce three possible causes:

1. full WordPress/admin-ajax request bootstrap delay before the VMS handler runs;
2. oversized save-config response payloads and DOM re-rendering after no-op saves;
3. PHP shutdown/output buffering after the Ticketing V2 work has already completed.

## Changes

### Ticketing V2 Save Config

- Save config now defaults to a minimal AJAX response.
- The response no longer returns the full normalized Ticketing V2 config unless explicitly requested with `return_config=1`.
- Admin JS now saves with `return_config: 0` and uses the already-read UI config as the saved baseline when the server omits the full config.
- Admin JS skips a full Ticketing V2 re-render after save when the server response intentionally omits config.
- Save config response includes diagnostics:
  - `minimal_response`
  - `config_omitted`
  - `server_adjusted_config`
  - `handler_elapsed_ms`
  - `request_age_at_handler_ms`
  - `raw_config_bytes`
- Fast AJAX responses now set `Content-Length` and `Connection: close` headers in addition to `X-VMS-Fast-Ajax`.

### Ticketing V2 Preview Sync

- Preview success responses now use the same fast AJAX sender.
- Preview responses include an `ajax_timing` object with:
  - `preview_elapsed_ms`
  - `handler_elapsed_ms`
  - `request_age_at_handler_ms`
  - `fast_response`
- Header should include `X-VMS-Fast-Ajax: ticketing-v2-preview-sync` on successful preview responses.

### Event Plan Save Profiler Visibility

- Adds a small wp-admin side metabox: **VMS Save Profile**.
- Shows the latest `_vms_last_save_profile` without requiring database access.
- Displays elapsed time, status transition, request action, meta write counts, ticket config/sync writes, queue/hook notes, and top meta keys touched.

## What this does not change

- Does not alter ticket pricing logic.
- Does not alter public cart/checkout runtime pricing.
- Does not alter Commit behavior.
- Does not broaden Publish behavior.
- Does not remove Ticket Integrity checks from cancellation/published flows.

## Testing notes

If staging still shows a 30+ second Save Config wait after this build, compare:

- browser wait time;
- `handler_elapsed_ms`;
- `request_age_at_handler_ms`;
- `raw_config_bytes`;
- response size;
- `X-VMS-Fast-Ajax` header.

Interpretation:

- Large `request_age_at_handler_ms` means delay is before the VMS handler, likely WordPress/admin-ajax bootstrap, plugin load, cron spawn, or host/server contention.
- Low `request_age_at_handler_ms` and low `handler_elapsed_ms` with high browser wait means response transfer/output buffering/proxy timing remains suspect.
- Large `raw_config_bytes` suggests the browser is posting too much ticket config data.

## Codex/local testing safety

Use staging credentials or the normal Codex/browser testing environment. Do not request Apple Events access or control the user's local authenticated Chrome session unless the user explicitly approves that method.

If test harness files are modified during testing, summarize those separately and do not treat them as VMS plugin changes.
