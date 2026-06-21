# WordPress.org Plugin Check Heatmap 1.0.0

Date: 2026-06-21

## Scope

- Scan target: extracted packaged directory from `dist/wporg-04w/vms-1.0.0-public-release.zip` under a disposable temp path outside the local site tree
- Artifact SHA-256: `e77856b986a347babea86fb1b4c381e2714d6d31674d2eb72c1193016d324902`
- Raw output: `docs/plugin-check-1.0.0-raw.txt`
- Tool: `wp --skip-plugins=event-tickets,event-tickets-plus,the-events-calendar,woocommerce,woocommerce-square,vms plugin check <extracted-package-dir> --slug=vms --mode=new --format=json`

## Current Result

- `3158` total findings
- `933` errors
- `2225` warnings

Comparison to the prior packaged RC from `WPORG-04V`:

- `3163` -> `3158` total (`-5`)
- `938` -> `933` errors (`-5`)
- `2225` -> `2225` warnings (`0`)

## WPORG-04W Batch

- 04W candidate scan summary
  - `includes/admin/menu.php` - `8` total / `5` errors / `3` warnings - dominant `WordPress.Security.EscapeOutput.OutputNotEscaped` (`4`) plus `WordPress.WP.I18n.MissingTranslatorsComment` (`1`) - isolated to dashboard filter attr rendering, checkbox attr output, guided-tour helper HTML, and one settings-link translator comment - risk `medium` - selected because the clearable findings stayed entirely on final render output without any admin menu registration or routing changes
  - `includes/admin-ui/shell.php` - `4` total / `4` errors / `0` warnings - dominant `WordPress.Security.EscapeOutput.OutputNotEscaped` (`4`) - isolated shared admin-shell output, but every fix would define shared HTML policy for callback-provided fragments across multiple screens - risk `medium` - skipped as the broader render boundary
  - `includes/public/calendar-ics.php` - `8` total / `5` errors / `3` warnings - dominant `WordPress.WP.I18n.MissingTranslatorsComment` (`4`) plus `WordPress.Security.EscapeOutput.OutputNotEscaped` (`1`) - mixed with read-only request parsing and raw ICS response output - risk `medium` - skipped
  - `includes/helpers.php` - `15` total / `6` errors / `9` warnings - dominant `WordPress.DateTime.RestrictedFunctions.date_date` (`3`), `WordPress.WP.I18n.MissingTranslatorsComment` (`2`), and `WordPress.Security.EscapeOutput.OutputNotEscaped` (`1`) - findings are mixed through shared helper/date utilities, query helpers, and dashboard form output - risk `high` - skipped
  - `includes/core/vendor-document-alerts.php` - `8` total / `8` errors / `0` warnings - dominant `WordPress.WP.I18n.MissingTranslatorsComment` (`8`) - isolated i18n only, but notification-behavior-adjacent - risk `high` - skipped
  - `includes/modules/staff-tasks/notifications.php` - `5` total / `5` errors / `0` warnings - dominant `WordPress.DateTime.RestrictedFunctions.date_date` (`5`) - technically isolated, but directly in notification scheduling logic - risk `high` - skipped
- `includes/admin/menu.php`
  - `8` -> `3`
  - `5` -> `0` errors
  - `3` -> `3` warnings
  - cleared the file's `1` translator-comment error and `4` final-output escaping errors while leaving the existing logging warning plus the two nonce recommendations untouched
  - limited the pass to explicit final attr escaping for dashboard filter markup, direct `checked()` output at the checkbox boundary, a narrow `wp_kses()` allowlist for the guided-tour button markup, and the missing settings-link translator comment
- focused validation
  - no focused menu/dashboard regression exists in `tests/`
  - `php -l includes/admin/menu.php` passed
  - `git diff --check` passed
  - `php scripts/build-public-release.php --output-dir dist/wporg-04w --force --allow-dirty` passed
  - packaged ZIP still contains root `readme.txt` and `LICENSE.txt`
  - the packaged rerun targeted an extracted packaged directory outside the local site tree, leaving the local `vms/` install untouched
  - WP-CLI emitted the known phar deprecation line during the packaged rerun; the cleaned raw findings stayed in `docs/plugin-check-1.0.0-raw.txt`, and that noise was mirrored in `test-results/wporg-04w-plugin-check.stderr.txt`
  - the rerun preserved the pre-existing `plugin_header_nonexistent_domain_path` warning outside the selected file scope without introducing any new Plugin Check code categories

Files touched:

- `includes/admin/menu.php`

Findings intentionally deferred:

- all remaining high-risk Event Plans runtime request/save hardening
- all shared admin-shell, calendar ICS payload, ticket-integrity, availability-dispatch, and portal/auth render work that is still mixed into broader runtime flows
- all publish validation, vendor assignment, staffing mutation, and live refund request flows
- all ticketing, checkout, refund, cancellation, and TEC publish/resync paths
- all portal/profile-save, upload, availability-save, and link-request input hardening outside this dashboard-render batch
- all broader SQL, nonce/input, and shared-runtime follow-up outside `includes/admin/menu.php`

Risk notes:

- selected file is an admin dashboard renderer, and the chosen changes stayed on explicit final attr output, guided-tour helper HTML sanitization, and a translator comment without altering admin menu registration, routing, capabilities, or request handling
- shared admin shell, calendar ICS output, ticketing, vendor confirmation, portal/auth, Event Plans runtime, refunds, helpers, and other broader surfaces were intentionally untouched

Net effect of the selected batch:

- `WordPress.Security.EscapeOutput.OutputNotEscaped`: `165` -> `161` (`-4`)
- `WordPress.WP.I18n.MissingTranslatorsComment`: `564` -> `563` (`-1`)
- `I18n placeholder comments and ordering`: `580` -> `579` (`-1`)
- `includes/admin/menu.php`: `8` -> `3` (`-5`)
- observed packaged rerun-only non-target steady state outside the selected file scope: `plugin_header_nonexistent_domain_path`: `1` -> `1` (`0`, unchanged)
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
| Escaping and output safety | `161` `EscapeOutput` findings | `includes/portal/staff-portal.php`, `includes/modules/admissions/vendor-guest-portal.php`, `includes/cpt/event-plans.php`, `includes/modules/availability-date-dispatch/admin-ui.php` |
| I18n placeholder comments and ordering | `579` | `includes/cpt/event-plans.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/integrations/ticketing-verifications.php`, `includes/core/staffing.php` |
| Date/time API usage | `27` | `includes/modules/staff-tasks/notifications.php`, `includes/helpers.php`, `includes/ticketing/ticket-integrity-monitor.php` |
| Development logging | `43` findings (`42` `error_log()` + `1` `debug_backtrace()`) | `includes/vendor-applications.php`, `includes/modules/admissions/rest.php`, `includes/cpt/event-plans.php` |

## Next Recommended Batch

- `WPORG-04X`
- Scope:
  - repeat the deliberate hotspot scan from the `WPORG-04W` packaged baseline and prefer another isolated admin-only render or final-escaping slice before widening into request, auth, refund, ticketing, or availability-save flows
  - `includes/admin-ui/shell.php` is the next logical render candidate only if a clearly local shared HTML allowlist can be defined without changing callback semantics; otherwise prefer the metadata micro-batch for `plugin_header_nonexistent_domain_path`
  - keep avoiding notification/date logic, shared helpers, and raw ICS output unless a later pass can prove they are display-only
