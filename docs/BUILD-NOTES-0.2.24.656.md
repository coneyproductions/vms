# VMS 0.2.24.656 — Event Plan Save Pressure + Ticketing V2 No-Op Guard

## Why this patch exists

After 0.2.24.655 fixed the fresh Event Plan Ticketing V2 dead-button regression, staging still showed two operational concerns:

1. Fresh plans could inherit stale default-template `sales_end` dates from an older template source event.
2. Saving Event Plans as Draft/Ready/Update was still creating heavy server pressure, even before a full ticket publish/commit.

This patch is intentionally narrow. It does **not** redesign Ticketing V2 publishing and does **not** change the confirmed 0.2.24.655 listener fix.

## What changed

### 1. Ticketing V2 config saves now skip unchanged writes

`vms_ticketing_v2_set_config()` now compares the normalized current config hash to the normalized incoming config hash before calling `update_post_meta()`.

This matters because WordPress fires metadata filters before its own no-op check. VMS ticket mutation audit uses those metadata filters to build before/after snapshots, which can be expensive on large ticket/add-on configurations.

When the config hash is unchanged, VMS now returns before triggering the metadata/audit chain.

### 2. AJAX Save config returns timing/debug details

`vms_ticketing_v2_save_config` now returns:

- `config_changed`
- `had_saved_config`
- `image_sync_count`
- `elapsed_ms`

This should make it easier for Codex/browser testing to distinguish a real save from a no-op save.

### 3. Ticket mutation audit now skips identical meta writes earlier

`vms_ticket_mutation_audit_capture_pre_meta_write()` now checks whether a single existing meta value is identical to the pending update value before building snapshots.

This is a second safety net for no-op ticketing meta updates outside the normal Ticketing V2 config save path.

### 4. Default Ticketing V2 template application resets stale `sales_end`

When `vms_ticketing_v2_default_config()` uses a saved default template for a fresh plan, it now:

- normalizes the template for the current Event Plan,
- hydrates missing sales windows from the current Event Plan,
- resets stale ticket `sales_end` values to the current Event Plan show datetime.

This prevents a saved template from carrying an old event-specific sales end date like `2026-04-10` into new events.

### 5. Ticket Integrity spot-scan queue logging is deduped

Ticket Integrity already avoided scheduling duplicate spot scans, but it could still write duplicate “spot scan queued” logs while a scan for the same plan was already pending.

Now it only writes the queue log when it actually schedules a new spot scan.

### 6. Lightweight Event Plan save profiler added

A new file was added:

- `includes/core/event-plan-save-profiler.php`

It records a compact profile for slow Event Plan saves, including:

- elapsed milliseconds,
- status at start/end of the save hook,
- requested VMS action,
- total meta writes,
- ticket config writes,
- ticket sync writes,
- top touched meta keys,
- notes such as whether Ticket Integrity scheduled or skipped a duplicate spot scan.

By default, profiles are stored only when the save takes at least 2 seconds. The profile is saved to:

- `_vms_last_save_profile`

If `WP_DEBUG` is enabled, the same profile is also written to the debug log.

Developer controls:

- `VMS_EVENT_PLAN_SAVE_PROFILER_THRESHOLD` — override threshold seconds.
- `VMS_EVENT_PLAN_SAVE_PROFILER_ALWAYS` — record every Event Plan save.
- `VMS_EVENT_PLAN_SAVE_PROFILER_DISABLED` — disable the profiler.
- `vms_event_plan_save_profiler_threshold_seconds` filter.
- `vms_event_plan_save_profiler_enabled` filter.

## Files changed

- `includes/integrations/ticketing-phase-b.php`
- `includes/ticketing/ticket-mutation-audit.php`
- `includes/ticketing/ticket-integrity-cron.php`
- `includes/core/event-plan-save-profiler.php`
- `includes/core/load.php`
- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `docs/BUILD-NOTES-0.2.24.656.md`
- `vms-test-plan-0.2.24.656.md`

## Static validation performed

- `php -l` on changed PHP files
- Full PHP lint across all plugin PHP files
- `node --check assets/admin-ticketing.js`
- Package top-level folder verified as `vms/`

## Live-test status

Not live-tested in WordPress during packaging. Codex should run the included 0.2.24.656 test plan on staging.

## Codex note

Codex may make small, directly-related code repairs when feasible during testing/troubleshooting. If code changes are made, Codex must update the VMS version/build number consistently in:

- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- related build notes/test plan files
