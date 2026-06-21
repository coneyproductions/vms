# WordPress.org Plugin Check Heatmap 1.0.0

Date: 2026-06-21

## Scope

- Scan target: extracted packaged directory from `dist/wporg-04r/vms-1.0.0-public-release.zip` under a disposable temp path outside the local site tree
- Artifact SHA-256: `4f336a3eb71714ac703633ca0b8f7222ed371881372416779b9159ca9203dd5d`
- Raw output: `docs/plugin-check-1.0.0-raw.txt`
- Tool: `wp --skip-plugins=event-tickets,event-tickets-plus,the-events-calendar,woocommerce,woocommerce-square,vms plugin check <extracted-package-dir> --slug=vms --mode=new --format=json`

## Current Result

- `3224` total findings
- `999` errors
- `2225` warnings

Comparison to the prior packaged RC from `WPORG-04Q`:

- `3255` -> `3224` total (`-31`)
- `1031` -> `999` errors (`-32`)
- `2224` -> `2225` warnings (`+1`)

## WPORG-04R Batch

- 04R candidate scan summary
  - `includes/core/vendor-user-links.php` - `68` total / `39` errors / `29` warnings - dominant `MissingTranslatorsComment` plus DB/SQL warnings - risk `medium` - selected because all thirty-two current i18n errors were confined to actor-label and admin-notification strings around lines `670-862`, allowing a translator-comment-only pass without touching SQL, linking logic, or runtime flow
  - `includes/integrations/ticketing-rules-v2.php` - `94` total / `65` errors / `29` warnings - dominant `MissingTranslatorsComment` plus unordered placeholders - risk `high` - skipped because ticketing and checkout runtime are explicitly out of scope, and the file's i18n findings sit inside purchase-limit paths
  - `includes/core/staffing.php` - `153` total / `38` errors / `115` warnings - dominant DB/SQL plus i18n - risk `high` - skipped because the i18n findings are mixed through staffing qualification notification and review flows
- `includes/core/vendor-user-links.php`
  - `68` -> `36`
  - `39` -> `7` errors
  - `29` -> `29` warnings
  - selected because it was the highest-yield non-ticketing, non-Event-Plans i18n-heavy file whose current placeholder findings stayed inside notification and label formatting helpers
  - limited the pass to adding `translators:` comments and extracting nested fallback user labels so each placeholder string kept an adjacent comment
- focused validation
  - no focused vendor-user-links regression exists in `tests/`
  - the packaged rerun targeted an extracted packaged directory outside the local site tree, leaving the local `vms/` install untouched
  - WP-CLI emitted the known phar deprecation line during the packaged rerun; the cleaned raw findings stayed in `docs/plugin-check-1.0.0-raw.txt`, and stderr was captured in `test-results/wporg-04r-plugin-check.stderr.txt`
  - the rerun also reintroduced the previously observed `plugin_header_nonexistent_domain_path` warning even though no domain-path or packaging change was made in this batch

Files touched:

- `includes/core/vendor-user-links.php`

Findings intentionally deferred:

- all remaining high-risk Event Plans runtime request/save hardening
- all publish validation, vendor assignment, staffing mutation, and live refund request flows
- all ticketing, checkout, refund, cancellation, and TEC publish/resync paths
- all portal/profile-save, upload, availability-save, and link-request input hardening outside this i18n-only helper batch
- all broader SQL, nonce/input, escaping, and shared-runtime follow-up outside `vendor-user-links.php`

Risk notes:

- selected file is a shared vendor-linking runtime helper, but the chosen changes stayed on translator comments and fallback label extraction only and did not alter any string meaning, SQL, or link behavior
- Event Plans runtime logic, vendor-linking flows, availability/save paths, ticketing, refunds, and other mutation-heavy surfaces were intentionally untouched

Net effect of the selected batch:

- `WordPress.WP.I18n.MissingTranslatorsComment`: `622` -> `590` (`-32`)
- observed packaged rerun-only delta outside the selected file scope: `plugin_header_nonexistent_domain_path`: `0` -> `1` (`+1`, reappeared)
- no previously unseen Plugin Check code categories appeared

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
| `includes/integrations/ticketing-claims-admin.php` | `66` | `1` | `65` | nonce/input |
| `includes/modules/admissions/rest.php` | `65` | `11` | `54` | logging + nonce/input |
| `includes/portal/vendor-portal.php` | `63` | `0` | `63` | portal mutation input + DB/SQL |
| `includes/portal/staff-portal.php` | `59` | `25` | `34` | escaping + nonce/input |
| `includes/modules/staff-tasks/admin-ui.php` | `56` | `8` | `48` | nonce/input + escaping |

## Category Hotspots

| Category | Current count | Highest-density files |
| --- | ---: | --- |
| Nonce and input handling | `1198` | `includes/cpt/event-plans.php`, `includes/vendor-applications.php`, `includes/integrations/ticketing-claims-admin.php`, `includes/integrations/ticketing-verifications.php` |
| Database and SQL safety | `1101` | `includes/modules/admissions/pass-claims.php`, `includes/core/staffing.php`, `includes/modules/staff-tasks/store.php`, `includes/modules/availability-date-dispatch/helpers.php` |
| Escaping and output safety | `183` `EscapeOutput` findings | `includes/portal/staff-portal.php`, `includes/modules/admissions/vendor-guest-portal.php`, `includes/cpt/event-plans.php`, `includes/modules/availability-date-dispatch/admin-ui.php` |
| I18n placeholder comments and ordering | `606` | `includes/cpt/event-plans.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/integrations/ticketing-verifications.php`, `includes/core/vendor-document-alerts.php` |
| Date/time API usage | `44` | `includes/admin/schedule.php`, `includes/modules/staff-tasks/notifications.php`, `includes/core/staffing.php` |
| Development logging | `43` findings (`42` `error_log()` + `1` `debug_backtrace()`) | `includes/vendor-applications.php`, `includes/modules/admissions/rest.php`, `includes/cpt/event-plans.php` |

## Next Recommended Batch

- `WPORG-04S`
- Scope:
  - stabilize the resurfaced `plugin_header_nonexistent_domain_path` warning in a dedicated, behavior-neutral packaging metadata micro-batch
  - then repeat the deliberate hotspot scan before selecting the next file
  - `includes/core/vendor-application-confirmation.php` is a possible follow-up only if the pass can stay strictly on translator comments and avoid its DB/request branches
