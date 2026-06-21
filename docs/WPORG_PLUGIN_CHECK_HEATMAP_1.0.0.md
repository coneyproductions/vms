# WordPress.org Plugin Check Heatmap 1.0.0

Date: 2026-06-21

## Scope

- Scan target: extracted packaged directory from `dist/wporg-04s/vms-1.0.0-public-release.zip` under a disposable temp path outside the local site tree
- Artifact SHA-256: `efd8df8bbbd0c823fcbc4aa5dfc999e7166d25c89ee163aaa59779198603886b`
- Raw output: `docs/plugin-check-1.0.0-raw.txt`
- Tool: `wp --skip-plugins=event-tickets,event-tickets-plus,the-events-calendar,woocommerce,woocommerce-square,vms plugin check <extracted-package-dir> --slug=vms --mode=new --format=json`

## Current Result

- `3205` total findings
- `980` errors
- `2225` warnings

Comparison to the prior packaged RC from `WPORG-04R`:

- `3224` -> `3205` total (`-19`)
- `999` -> `980` errors (`-19`)
- `2225` -> `2225` warnings (`0`)

## WPORG-04S Batch

- 04S candidate scan summary
  - `includes/core/event-plan-review.php` - `21` total / `19` errors / `2` warnings / `19` i18n - dominant `MissingTranslatorsComment` plus `NonceVerification.Missing` - risk `medium` - selected because all nineteen current i18n errors were confined to review-summary and render helper strings around lines `163-808`, allowing a translator-comment-only pass that left the save-hook warning pair untouched
  - `includes/core/staffing.php` - `153` total / `38` errors / `115` warnings / `31` i18n - dominant `MissingTranslatorsComment`, `UnorderedPlaceholdersText`, and DB/SQL codes - risk `high` - skipped because the i18n findings are mixed through staffing qualification notifications, assignment reporting, and mutation-adjacent helpers
  - `includes/core/event-credits.php` - `23` total / `16` errors / `7` warnings / `15` i18n - dominant `MissingTranslatorsComment`, `UnorderedPlaceholdersText`, plus input/date findings - risk `high` - skipped because the strings sit inside cancellation, refund, event-credit issuance, and customer-email flows
  - `includes/core/vendor-application-confirmation.php` - `53` total / `19` errors / `34` warnings / `11` i18n - dominant `MissingTranslatorsComment`, `UnorderedPlaceholdersText`, nonce/input, DB/SQL, and escaping codes - risk `high` - skipped because the strings live inside request, auth, user-resolution, and email-confirmation flows
  - `includes/vendor-applications.php` - `90` total / `15` errors / `75` warnings / `12` i18n - dominant `MissingTranslatorsComment` plus nonce/input and logging - risk `high` - skipped because the findings are mixed through application submission and admin-mutation handling
- `includes/core/event-plan-review.php`
  - `21` -> `2`
  - `19` -> `0` errors
  - `2` -> `2` warnings
  - selected because it was the highest-yield non-ticketing candidate whose current i18n errors stayed inside read-only Event Plans review labels, summaries, and banner metadata strings rather than the save hook itself
  - limited the pass to adding adjacent `translators:` comments above the existing placeholder strings only
- focused validation
  - no focused event-plan-review regression exists in `tests/`
  - the packaged rerun targeted an extracted packaged directory outside the local site tree, leaving the local `vms/` install untouched
  - WP-CLI emitted the known phar deprecation line during the packaged rerun; the cleaned raw findings stayed in `docs/plugin-check-1.0.0-raw.txt`, and stderr was captured in `test-results/wporg-04s-plugin-check.stderr.txt`
  - the rerun preserved the pre-existing `plugin_header_nonexistent_domain_path` warning outside the selected file scope without introducing any new Plugin Check code categories

Files touched:

- `includes/core/event-plan-review.php`

Findings intentionally deferred:

- all remaining high-risk Event Plans runtime request/save hardening
- all publish validation, vendor assignment, staffing mutation, and live refund request flows
- all ticketing, checkout, refund, cancellation, and TEC publish/resync paths
- all portal/profile-save, upload, availability-save, and link-request input hardening outside this i18n-only helper batch
- all broader SQL, nonce/input, escaping, and shared-runtime follow-up outside `event-plan-review.php`

Risk notes:

- selected file shares the Event Plans review/save surface, but the chosen changes stayed on translator comments for read-only labels and summaries only and did not alter any save logic, hooks, or string meaning
- Event Plans save behavior, ticketing, refunds, vendor applications, staffing mutations, and other request-heavy surfaces were intentionally untouched

Net effect of the selected batch:

- `WordPress.WP.I18n.MissingTranslatorsComment`: `590` -> `571` (`-19`)
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
| Escaping and output safety | `183` `EscapeOutput` findings | `includes/portal/staff-portal.php`, `includes/modules/admissions/vendor-guest-portal.php`, `includes/cpt/event-plans.php`, `includes/modules/availability-date-dispatch/admin-ui.php` |
| I18n placeholder comments and ordering | `587` | `includes/cpt/event-plans.php`, `includes/integrations/ticketing-rules-v2.php`, `includes/integrations/ticketing-verifications.php`, `includes/core/staffing.php` |
| Date/time API usage | `44` | `includes/admin/schedule.php`, `includes/modules/staff-tasks/notifications.php`, `includes/core/staffing.php` |
| Development logging | `43` findings (`42` `error_log()` + `1` `debug_backtrace()`) | `includes/vendor-applications.php`, `includes/modules/admissions/rest.php`, `includes/cpt/event-plans.php` |

## Next Recommended Batch

- `WPORG-04T`
- Scope:
  - repeat the deliberate hotspot scan from the `WPORG-04S` packaged baseline and prefer another isolated translator-comment batch only if the placeholder findings stay out of request, auth, refund, and ticketing flows
  - `includes/core/vendor-application-confirmation.php` remains a possible follow-up only if the pass can stay strictly on translator comments in the existing email/admin-label strings and avoid its request, auth, user-create, and escaping branches
  - if packaging-warning cleanup is preferred over another code batch, handle the unchanged `plugin_header_nonexistent_domain_path` warning in a dedicated metadata micro-batch instead of widening runtime scope
