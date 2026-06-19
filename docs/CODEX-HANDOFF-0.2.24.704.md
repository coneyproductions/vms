# VMS 0.2.24.704 — Event Plan Performance Packaging

## What changed

- Finalized the Event Plan performance work from patches 1 through 10 into a production-ready package.
- Kept the Event Plan save/update hardening intact:
  - auto-title no-op guard
  - secondary-vendor dirty check
  - staffing dirty check
- Kept the Event Plan edit-screen optimizations intact:
  - Staff lazy-load shell
  - Secondary Vendors summary-first/lazy details
  - Readiness details summary-first/lazy details
  - Ticketing summary-first admin rendering
  - supporting-vendor shared option hydration
  - Command Center ticket card summary-first rendering on Event Plan edit

## Production safety

- The Event Plan performance trace remains disabled by default.
- Query fingerprint capture, memory checkpoints, and temporary `SAVEQUERIES` enabling only activate when `VMS_EP_PERF_TRACE` is explicitly enabled.
- The plugin package does not include or depend on local `wp-config.php` changes.
- Local perf reports and trace artifacts are excluded from the production zip.

## Ticket reporting source of truth

- This package does **not** replace the full ticket-reporting source of truth with a TEC-only shortcut.
- The Event Plan edit-screen Command Center ticket card now shows a cheap summary on initial load.
- The full ticket snapshot/report remains available via the full Event Command Center and still uses the existing heavier reporting path when explicitly opened.

## Local verification summary

- Open-edit Event Plan query count stayed near the Patch 10 win and did not return to the older `600`-query edit-open path.
- Plain no-change Update remained clean with one `save_post` pass, zero internal Event Plan `wp_update_post()` calls, vendor/staffing skips on unchanged saves, and no unexpected ticket/Woo/Action Scheduler churn.
- Browser/admin smoke confirmed:
  - Command Center ticket card summary still appears
  - full ticket report still opens
  - Ticketing summary-first still works
  - Staff lazy-load/save still works
  - Secondary Vendors lazy-load still works
  - Readiness details lazy-load still works

## Version markers updated

- Plugin header: `0.2.24.704`
- `VMS_VERSION`: `0.2.24.704`
- `vms-build.txt`: `0.2.24.704`
- Build notes: `BUILD-NOTES-0.2.24.704.md`
- Test plan: `vms-test-plan-0.2.24.704.md`
