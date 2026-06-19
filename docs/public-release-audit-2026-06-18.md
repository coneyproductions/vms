# Public Release Audit — 2026-06-18

## Fixed defects

| Surface | Source | Issue | Result |
| --- | --- | --- | --- |
| Admissions REST routes | `includes/modules/admissions/rest.php` | Routes were registered with public `permission_callback` values and relied on callback internals for access control. | Added route-level capability callbacks plus REST nonce validation for admissions read/manage/check-in surfaces. |
| Qualified-ticket assignee validation AJAX | `includes/integrations/ticketing-claims-customer.php` | Anonymous callers could probe assignee eligibility and logged-in callers received internal IDs/counters that the UI did not need. | Anonymous callers now get `401 login_required`; buyer-facing payloads no longer expose internal IDs, grant IDs, rule paths, or eligibility counters. |
| Public release packaging | `tests/check-package-integrity.php`, `release-public-excludes.txt` | No dedicated public-release exclusion contract existed for tests/docs/build notes/local artifacts. | Added a release exclusion manifest, documented the packaging rule, and made ZIP integrity checks fail on excluded contents. |

## Intentionally public endpoints

| Surface | Source | Auth model | Notes |
| --- | --- | --- | --- |
| Event feedback submit | `includes/public/event-feedback.php` | Public token + form nonce | Also includes honeypot and duplicate-submission locks. |
| Vendor application resend confirmation | `includes/core/vendor-application-confirmation.php` | Public application reference + nonce | Intended for applicant email-confirmation recovery. |
| Ticketing cart AJAX (`silent_add`, `atomic_add_to_cart`, `cart_context`) | `includes/integrations/ticketing-rules-v2.php` | Storefront AJAX | `atomic_add_to_cart` hard-requires a nonce; `silent_add` and `cart_context` still allow missing nonces for cached-page compatibility. |

## Remaining review item

| Priority | Surface | Source | Note |
| --- | --- | --- | --- |
| P2 | Cached-page ticketing helpers | `includes/integrations/ticketing-rules-v2.php` | `silent_add` and `cart_context` still accept missing nonces to tolerate stale cached HTML. Left unchanged pending browser QA because tightening them may break event-page storefront flows. |

## Verification

- `php -l` on touched runtime and test files
- `php tests/admissions-rest-permissions.php`
- `php tests/ticket-claims-assignee-validation.php`
- `php tests/ticket-checkout-safety-hardening.php`
- `php tests/check-package-integrity.php vms`
- Built a temp ZIP from a staged `vms/` copy using `release-public-excludes.txt`, then ran `php tests/check-package-integrity.php <temp-zip>`
