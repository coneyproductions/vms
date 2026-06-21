# WordPress.org Plugin Check Heatmap 1.0.0

Date: 2026-06-21

## Scope

- Scan target: extracted packaged directory from `dist/wporg-04x/vms-1.0.0-public-release.zip` under a disposable temp path outside the local site tree
- Artifact SHA-256: `b3eaf4abe3129cc1bf9f8185d3db27d545b7afea06d3248236d100d116fbf004`
- Raw output: `docs/plugin-check-1.0.0-raw.txt`
- Tool: `wp --skip-plugins=event-tickets,event-tickets-plus,the-events-calendar,woocommerce,woocommerce-square,vms plugin check <extracted-package-dir> --slug=vms --mode=new --format=json`

## Current Result

- `3150` total findings
- `925` errors
- `2225` warnings

Comparison to the prior packaged RC from `WPORG-04W`:

- `3158` -> `3150` total (`-8`)
- `933` -> `925` errors (`-8`)
- `2225` -> `2225` warnings (`0`)

## WPORG-04X Batch

- 04X candidate scan summary
  - `includes/core/vendor-document-alerts.php` - `8` total / `8` errors / `0` warnings - dominant `WordPress.WP.I18n.MissingTranslatorsComment` (`8`) - isolated to vendor fallback text plus notification subject/body payload strings, so the cleanup is runtime no-op comment-only i18n work - risk `medium` - selected because it offered the highest isolated error-only yield without touching request, routing, capability, or notification behavior
  - `includes/admin-ui/shell.php` - `4` total / `4` errors / `0` warnings - dominant `WordPress.Security.EscapeOutput.OutputNotEscaped` (`4`) - isolated shared admin-shell output, but every fix would define shared HTML policy for callback-provided fragments across multiple screens - risk `medium` - skipped as the broader render boundary
  - `includes/admin/settings/class-vms-settings-notifications.php` - `2` total / `2` errors / `0` warnings - dominant `WordPress.WP.I18n.MissingTranslatorsComment` (`2`) - isolated admin settings placeholder strings - risk `low` - skipped because the vendor-alert file offered higher isolated yield
  - `includes/admin/cancelled-event-cost-review.php` - `3` total / `3` errors / `0` warnings - dominant `WordPress.WP.I18n.MissingTranslatorsComment` (`3`) - isolated admin metabox placeholder strings - risk `low` - skipped because the vendor-alert file offered higher isolated yield
  - `includes/public/event-details.php` - `2` total / `2` errors / `0` warnings - dominant `WordPress.WP.I18n.MissingTranslatorsComment` (`2`) - isolated public-facing placeholder strings - risk `medium` - skipped because this batch stayed on admin/ops-adjacent strings first
  - `includes/modules/email-followups/templates.php` - `3` total / `3` errors / `0` warnings - dominant `WordPress.WP.I18n.MissingTranslatorsComment` (`3`) - isolated template-copy placeholder strings, but directly in follow-up notification templates - risk `medium` - skipped
  - `includes/admin/staff-certifications.php` - `1` total / `1` errors / `0` warnings - dominant `WordPress.WP.I18n.MissingTranslatorsComment` (`1`) - isolated admin count string - risk `low` - skipped because the vendor-alert file offered higher isolated yield
- `includes/core/vendor-document-alerts.php`
  - `8` -> `0`
  - `8` -> `0` errors
  - `0` -> `0` warnings
  - cleared the file's `8` placeholder-comment errors by adding `translators:` comments only to the existing vendor fallback label plus the notification subject/body payload lines
- focused validation
  - no focused vendor-alert regression exists in `tests/`
  - `php -l includes/core/vendor-document-alerts.php` passed
  - `git diff --check` passed
  - `php scripts/build-public-release.php --output-dir dist/wporg-04x --force --allow-dirty` passed
  - packaged ZIP still contains root `readme.txt` and `LICENSE.txt`
  - the packaged rerun targeted an extracted packaged directory outside the local site tree, leaving the local `vms/` install untouched
  - WP-CLI emitted the known phar deprecation line during the packaged rerun; the cleaned raw findings stayed in `docs/plugin-check-1.0.0-raw.txt`, and that noise was mirrored in `test-results/wporg-04x-plugin-check.stderr.txt`
  - the rerun preserved the pre-existing `plugin_header_nonexistent_domain_path` and `PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound` warnings outside the selected file scope without introducing any new Plugin Check code categories

Files touched:

- `includes/core/vendor-document-alerts.php`

Findings intentionally deferred:

- all remaining high-risk Event Plans runtime request/save hardening
- all shared admin-shell, calendar ICS payload, ticket-integrity, availability-dispatch, follow-up template, and portal/auth render work that is still mixed into broader runtime flows
- all publish validation, vendor assignment, staffing mutation, and live refund request flows
- all ticketing, checkout, refund, cancellation, and TEC publish/resync paths
- all portal/profile-save, upload, availability-save, and link-request input hardening outside this comment-only i18n batch
- all broader SQL, nonce/input, and shared-runtime follow-up outside `includes/core/vendor-document-alerts.php`

Risk notes:

- selected file is notification-behavior-adjacent, but the chosen changes stayed on `translators:` comments for existing placeholder strings only and did not alter notification routing, recipients, payload assembly, or dispatch behavior
- shared admin shell, calendar ICS output, ticketing, vendor confirmation, portal/auth, Event Plans runtime, refunds, helpers, and other broader surfaces were intentionally untouched

Net effect of the selected batch:

- `WordPress.WP.I18n.MissingTranslatorsComment`: `563` -> `555` (`-8`)
- `I18n placeholder comments and ordering`: `579` -> `571` (`-8`)
- `includes/core/vendor-document-alerts.php`: `8` -> `0` (`-8`)
- observed packaged rerun-only root-file steady state outside the selected file scope: `plugin_header_nonexistent_domain_path`: `1` -> `1`, `PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound`: `1` -> `1`
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
| I18n placeholder comments and ordering | `571` | `includes/cpt/event-plans.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/integrations/ticketing-verifications.php`, `includes/core/staffing.php` |
| Date/time API usage | `27` | `includes/modules/staff-tasks/notifications.php`, `includes/helpers.php`, `includes/ticketing/ticket-integrity-monitor.php` |
| Development logging | `43` findings (`42` `error_log()` + `1` `debug_backtrace()`) | `includes/vendor-applications.php`, `includes/modules/admissions/rest.php`, `includes/cpt/event-plans.php` |

## Next Recommended Batch

- `WPORG-04Y`
- Scope:
  - repeat the deliberate hotspot scan from the `WPORG-04X` packaged baseline and prefer another isolated translator-comment or final-output slice before widening into request, auth, refund, ticketing, or availability-save flows
  - `includes/admin/settings/class-vms-settings-notifications.php` and `includes/admin/cancelled-event-cost-review.php` are the next low-risk i18n-only admin candidates; reach for `includes/admin-ui/shell.php` only if a clearly local shared HTML allowlist can be defined without changing callback semantics
  - keep avoiding notification/date logic beyond comment-only changes, shared helpers, and raw ICS output unless a later pass can prove they are display-only
