# VMS 0.2.24.654 — Single-Ticket Early/Regular Price Windows

## Why this patch exists

Operators should be able to schedule one public admission ticket far in advance with an early/advance price that automatically changes to the normal price later.

The preferred model is:

- One public ticket product, such as **General Admission**
- Optional early price window
- Existing ticket price treated as the regular price

This avoids customer confusion and the technical/reporting risk of separate **Early General Admission** and **General Admission** ticket products for the same admission type.

## What changed

- Ticketing v2 ticket rows now support:
  - **Regular price** — the existing `price` field, renamed in the admin UI
  - **Early price** — optional lower price
  - **Early starts** — optional start date/time
  - **Early ends** — required when early price is used
- The ticket config normalizer preserves early-price fields for saved configs, template application, legacy GA mirrors, and tier-like sync payloads.
- Preview sync now blocks invalid managed configurations where:
  - early price is not lower than regular price
  - early price has no end date
  - early start is after early end
- Woo/TEC ticket sync now writes regular/sale/scheduled-sale metadata for the same product instead of requiring a separate early ticket product.
- Runtime Woo price filters resolve active pricing from the saved VMS config so the public price can switch from early to regular even if Woo scheduled-sale cron has not refreshed the cached product price yet.
- Public ticket pricing maps use the active VMS effective price so progressive UI totals follow the current early/regular window.
- Ticket change hashes include early-price fields, so changing an early price/date is detected by Preview/Commit.

## Expected result

For a single **General Admission** ticket configured as:

- Regular price: `$25`
- Early price: `$20`
- Early ends: a future cutoff date/time

The same public GA product should show and sell at `$20` during the early window, then `$25` after the cutoff, without creating a separate **Early General Admission** product.

## Files changed

- `includes/integrations/ticketing-phase-b.php`
- `includes/integrations/ticketing-rules-v2.php`
- `assets/admin-ticketing.js`
- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `docs/BUILD-NOTES-0.2.24.654.md`
- `vms-test-plan-0.2.24.654.md`
- `docs/test-plan-0.2.24.654-single-ticket-early-price-windows.md`

## Static validation performed

- `php -l includes/integrations/ticketing-phase-b.php`
- `php -l includes/integrations/ticketing-rules-v2.php`
- `node --check assets/admin-ticketing.js`

## Live-test status

Not live-tested in WordPress during packaging. Use the included test plan before deploying to production.
