# VMS 0.2.24.704

## Scope

- Production-package the Event Plan performance hardening work from patches 1 through 10.
- Preserve the confirmed save/update wins, the summary-first Event Command Center ticket card, and the lazy/shell admin rendering changes for heavy Event Plan sections.
- Keep the local Event Plan performance trace tooling available for future diagnostics while ensuring it is disabled by default and excluded from normal production runtime.

## Included Event Plan performance changes

- No-op guard for Event Plan auto-title sync so unchanged saves do not trigger an internal `wp_update_post()` pass.
- Secondary-vendor dirty checks so unchanged vendor state skips rebuilds and vendor/calendar maintenance queueing.
- Staffing dirty checks so unchanged staffing state skips matrix writes, availability/conflict dirtying, queue-meta churn, and seed queueing.
- Summary-first and lazy-loaded admin sections for Staff, Secondary Vendors, Readiness details, Ticketing, and the Event Command Center ticket module.
- Shared supporting-vendor option hydration and admin boot batching/memoization for lighter edit-screen rendering.
- Summary-first Event Command Center ticket card on Event Plan edit, with the full source-of-truth report deferred to the full Command Center page.

## Production-safety cleanup

- `VMS_EP_PERF_TRACE` remains opt-in only and is not defined anywhere inside the plugin.
- Query fingerprint capture and memory checkpoints only run when `VMS_EP_PERF_TRACE` is explicitly enabled.
- Temporary `SAVEQUERIES` enabling only occurs inside the trace helper path and remains dormant when tracing is off.
- Local `wp-config.php` changes were used only for local verification and are not part of the plugin package.
- The release zip excludes local perf reports, perf trace logs, `/tmp` runners, `.DS_Store`, and editor temp files.

## Files changed for this packaging pass

- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `docs/05-revision-log.md`
- `BUILD-NOTES-0.2.24.704.md`
- `vms-test-plan-0.2.24.704.md`
- `docs/CODEX-HANDOFF-0.2.24.704.md`

## Local verification performed

- `php -l vms/vendor-management-system.php`
- `php -l vms/includes/core/registry/constants.php`
- `php -l vms/includes/core/event-plan-performance.php`
- `php -l vms/includes/admin/event-command-center.php`
- `node --check vms/assets/js/vms-lineup-schedule-admin.js`
- `php /tmp/vms_ep_perf_runner.php`
- Browser/admin smoke on Event Plan `76` covering Ticketing summary-first, Staff lazy load/save, Secondary Vendors lazy load, Readiness details lazy load, and Command Center full ticket report access.

## Package

- Production-bound package slug: `vms-0.2.24.704.zip`
