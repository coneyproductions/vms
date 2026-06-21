# WordPress.org Plugin Check Heatmap 1.0.0

Date: 2026-06-21

## Scope

- Scan target: extracted packaged directory from `dist/wporg-04q/vms-1.0.0-public-release.zip` under a disposable temp path outside the local site tree
- Artifact SHA-256: `bdb050f722c55de68a34c1690a7f8143f024801e638a7f00f1a14975c96d3671`
- Raw output: `docs/plugin-check-1.0.0-raw.txt`
- Tool: `wp --skip-plugins=event-tickets,event-tickets-plus,the-events-calendar,woocommerce,woocommerce-square,vms plugin check <extracted-package-dir> --slug=vms --mode=new --format=json`

## Current Result

- `3255` total findings
- `1031` errors
- `2224` warnings

Comparison to the prior packaged RC from `WPORG-04P`:

- `3268` -> `3255` total (`-13`)
- `1043` -> `1031` errors (`-12`)
- `2225` -> `2224` warnings (`-1`)

## WPORG-04Q Batch

- 04Q candidate scan summary
  - `includes/core/lineup-schedule.php` - `12` total / `12` errors / `0` warnings - dominant `WordPress.WP.I18n.MissingTranslatorsComment` - risk `low` - selected because all twelve findings were mechanical placeholder-comment fixes in a shared helper with no save, query, or auth behavior changes
  - `includes/core/vendor-user-links.php` - `68` total / `39` errors / `29` warnings - dominant `MissingTranslatorsComment` plus DB/SQL warnings - risk `medium` - skipped because the file is shared runtime code with interleaved query findings
  - `includes/admin/schedule.php` - `52` total / `30` errors / `22` warnings - dominant `date()`, escaping, and nonce/input - risk `medium/high` - skipped because admin action and save flows are mixed through the file
  - `includes/modules/availability-date-dispatch/admin-ui.php` - `30` total / `21` errors / `9` warnings - dominant escaping, nonce/input, and i18n - risk `high` - skipped because the availability runtime/save area is explicitly sensitive
  - `includes/core/event-credits.php` - `23` total / `16` errors / `7` warnings - dominant `MissingTranslatorsComment` - risk `high` - skipped because refund, cancellation, and credit behavior is out of scope for this batch
- `includes/core/lineup-schedule.php`
  - `12` -> `0`
  - `12` -> `0` errors
  - `0` -> `0` warnings
  - selected because it was the only low-risk 10+ error candidate in the scan whose findings were entirely translator-comment omissions
  - limited the pass to adding `translators:` comments above the existing `_n()` and `__()` placeholder strings only
- focused validation
  - no focused lineup-schedule regression exists in `tests/`
  - the packaged rerun targeted an extracted packaged directory outside the local site tree, leaving the local `vms/` install untouched
  - the rerun also stopped emitting the pre-existing `plugin_header_nonexistent_domain_path` warning even though no domain-path or packaging change was made in this batch

Files touched:

- `includes/core/lineup-schedule.php`

Findings intentionally deferred:

- all remaining high-risk Event Plans runtime request/save hardening
- all publish validation, vendor assignment, staffing mutation, and live refund request flows
- all ticketing, checkout, refund, cancellation, and TEC publish/resync paths
- all portal/profile-save, upload, availability-save, and link-request input hardening outside this i18n-only helper batch
- all broader SQL, nonce/input, escaping, and shared-runtime follow-up outside `lineup-schedule.php`

Risk notes:

- selected file is a shared lineup helper, but the chosen changes stayed on translator comments only and did not alter any string content or scheduling logic
- Event Plans runtime logic, vendor-linking flows, availability/save paths, ticketing, refunds, and other mutation-heavy surfaces were intentionally untouched

Net effect of the selected batch:

- `WordPress.WP.I18n.MissingTranslatorsComment`: `634` -> `622` (`-12`)
- observed packaged rerun-only delta outside the selected file scope: `plugin_header_nonexistent_domain_path`: `1` -> `0` (`-1`)
- no new Plugin Check code categories appeared

## Highest-Density Files

| File | Total | Errors | Warnings | Primary pressure |
| --- | ---: | ---: | ---: | --- |
| `includes/cpt/event-plans.php` | `241` | `108` | `133` | nonce/input + i18n + escaping |
| `includes/modules/admissions/pass-claims.php` | `173` | `23` | `150` | DB/SQL |
| `includes/core/staffing.php` | `153` | `38` | `115` | DB/SQL |
| `includes/integrations/ticketing-verifications.php` | `102` | `40` | `62` | nonce/input + i18n |
| `includes/modules/availability-date-dispatch/helpers.php` | `96` | `19` | `77` | DB/SQL |
| `includes/integrations/ticketing-rules-v2.php` | `94` | `65` | `29` | i18n |
| `includes/modules/staff-tasks/store.php` | `90` | `17` | `73` | DB/SQL |
| `includes/vendor-applications.php` | `90` | `15` | `75` | nonce/input |
| `includes/modules/admissions/vendor-guest-portal.php` | `75` | `36` | `39` | escaping + DB/SQL |
| `includes/social-share/queue-repo.php` | `73` | `7` | `66` | DB/SQL |
| `includes/core/vendor-user-links.php` | `68` | `39` | `29` | escaping + i18n |
| `includes/integrations/ticketing-claims-admin.php` | `66` | `1` | `65` | nonce/input |
| `includes/modules/admissions/rest.php` | `65` | `11` | `54` | logging + nonce/input |
| `includes/portal/vendor-portal.php` | `63` | `0` | `63` | portal mutation input + DB/SQL |
| `includes/portal/staff-portal.php` | `59` | `25` | `34` | escaping + nonce/input |

## Category Hotspots

| Category | Current count | Highest-density files |
| --- | ---: | --- |
| Nonce and input handling | `1198` | `includes/cpt/event-plans.php`, `includes/vendor-applications.php`, `includes/integrations/ticketing-claims-admin.php`, `includes/integrations/ticketing-verifications.php` |
| Database and SQL safety | `1101` | `includes/modules/admissions/pass-claims.php`, `includes/core/staffing.php`, `includes/modules/staff-tasks/store.php`, `includes/modules/availability-date-dispatch/helpers.php` |
| Escaping and output safety | `183` `EscapeOutput` findings | `includes/portal/staff-portal.php`, `includes/modules/admissions/vendor-guest-portal.php`, `includes/cpt/event-plans.php`, `includes/modules/availability-date-dispatch/admin-ui.php` |
| I18n placeholder comments and ordering | `638` | `includes/cpt/event-plans.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/integrations/ticketing-verifications.php`, `includes/core/vendor-document-alerts.php` |
| Date/time API usage | `44` | `includes/admin/schedule.php`, `includes/modules/staff-tasks/notifications.php`, `includes/core/staffing.php` |
| Development logging | `43` findings (`42` `error_log()` + `1` `debug_backtrace()`) | `includes/vendor-applications.php`, `includes/modules/admissions/rest.php`, `includes/cpt/event-plans.php` |

## Next Recommended Batch

- `WPORG-04R`
- Scope:
  - repeat the deliberate hotspot scan before selecting the next file
  - prefer another i18n-only or read-only display/helper slice outside Event Plans, ticketing, refunds, portal-save, availability-save, and vendor-linking runtime
  - `includes/core/vendor-user-links.php` is the leading medium-risk candidate only if the pass can stay strictly on translator comments and avoid its DB/query branches
