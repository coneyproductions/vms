# VMS Data Tools 0.5.41 Test Plan — Ticket Pace Today Row + Projection Guardrail

## Scope

This build updates the Ticket Pace report only.

Changed files:

- `vms-data-tools.php`
- `vms-build.txt`
- `includes/admin/page-reporting-module.php`
- `includes/admin/vms-dt-admin.css`
- `docs/TEST-PLAN-0.5.41.md`

## Install / version checks

1. Install `vms-data-tools-0.5.41-ticket-pace-today-row-projection-guardrail.zip` by uploading and replacing the existing VMS Data Tools plugin.
2. Confirm WordPress shows VMS Data Tools version `0.5.41`.
3. Confirm `vms-build.txt` shows build `0.5.41` and slug `ticket-pace-today-row-projection-guardrail`.

## Ticket Pace checks

Open **VMS → Reports → Ticket Pace** and choose an upcoming event that is currently between milestone checkpoints, such as 5 or 6 days out.

Expected behavior:

1. The KPI cards still show current paid quantity and current ticket sales.
2. The milestone table still shows true checkpoint dates for 30 / 14 / 7 / 3 / 1 / 0 days out.
3. Future standard checkpoints remain `Not reached yet` and do not repeat the current total.
4. A highlighted `Today / current (N days out)` row appears between the surrounding standard milestones when today is not exactly one of the standard checkpoint days.
5. The Today row shows the current cumulative paid ticket quantity and current cumulative ticket sales.
6. The Today row compares against the historical average at today’s days-out checkpoint.
7. The Pace trend chart includes today’s current point in the solid line.
8. If today exactly equals a standard milestone, no duplicate Today row is added because the standard milestone row already represents today.

## Projection checks

Expected behavior:

1. The Projected Finish KPI no longer uses the high-side multiplier as the main value.
2. The main projection uses: current total + average historical remaining tickets/sales from today’s days-out checkpoint.
3. The `How this compares` box explains that the main projection is intentionally conservative.
4. The old multiplier-style projection appears only as `High-side pace math` context and is labeled cautiously.

## Regression checks

1. Confirm the Daily Pace table still renders and totals match the prior build.
2. Confirm free/comp website tickets remain excluded from paid-ticket pace math.
3. Confirm historical average final quantity and sales still render.
4. Confirm the report does not show PHP warnings/notices in WordPress admin.

## Repair protocol for Codex / live-site testing

🚨 If any code repair is made during testing, even a tiny fix, update all relevant version markers and package docs before returning the zip. At minimum, check/update the plugin header version, `VMS_DT_VERSION`, `vms-build.txt`, this test plan or a new test plan, changelog/build notes if present, and the package filename. Do not return a modified build with stale versioning or stale docs.

## Rollback

Rollback to `vms-data-tools-0.5.40-ticket-pace-checkpoint-date-fix.zip` if the Ticket Pace page fails to render or if daily cumulative totals differ from the prior build.
