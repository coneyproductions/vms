# VMS Test Plan — 0.2.24.611 — Incident Hardening

🚨 **Staging validation required before production.** This pass changes boot behavior, cron lifecycle, and public-request boundaries.

## Version checks

1. Confirm plugin header reports `0.2.24.611`.
2. Confirm `includes/core/registry/constants.php` defines `VMS_VERSION` as `0.2.24.611`.
3. Confirm `vms-build.txt` begins with `0.2.24.611`.

## Primary checklist

Use `docs/test-plan-incident-hardening.md` as the detailed execution checklist for:

- package/build integrity verification
- disabled-state cron cleanup regression checks
- enabled-state smoke coverage for homepage, event page, cart, checkout, and `wp-cron.php`
- schedule registration hardening
- debug-log noise validation after 20 mixed requests
- before/after query-count and load-time spot checks

## Expected outcomes

1. Missing `includes/db/migrations.php` is caught by the build gate or reduced to an admin-only diagnostic, not a public fatal.
2. With VMS disabled, stale `vms_*` hooks stop producing `invalid_schedule` spam.
3. With VMS enabled, normal public routes load without migrations/admin-shell/reporting/email-follow-up boot regressions.
4. `debug.log` stays small and does not refill with early translation or stale-cron noise.
