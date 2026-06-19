# VMS 0.2.24.741

## Scope

- Add capped Early Bird pricing to VMS Ticketing V2 so one public ticket can carry an early price that expires by deadline, quantity cap, or whichever condition happens first.
- Separate total ticket availability display from capped sale availability display.
- Make the front-end customer-owned ticket notice refund-aware so fully refunded test purchases no longer show as active tickets.

## What changed

- Added `early_price_cap` to V2 ticket config normalization, hashing, admin editing, GA back-compat conversion, and product price payloads.
- Added refund-aware net sold calculations for ticket products using Woo order line items minus refund line items before falling back to lookup-table behavior.
- Added Early Bird price state evaluation that supports:
  - normal early price windows with start/end dates
  - capped Early Bird pools
  - cap-only Early Bird pools when no hard end date is set
  - automatic regular-price fallback after the cap is exhausted
- Added runtime Woo product price, sale price, and sale badge behavior that re-evaluates Early Bird cap state instead of trusting stale product meta.
- Added server-side add-to-cart/cart/checkout guards so carts cannot claim more active Early Bird discount quantity than remains.
- Added global public Ticket UI settings:
  - Display total ticket availability: always, low only, or hidden
  - Total availability low threshold
  - Display sale quantity remaining: when capped, low only, or hidden
  - Sale quantity low threshold
- Added Event Plan-level display overrides for total availability and sale availability.
- Updated public ticket sale copy so capped Early Bird rows can show messages such as `Early Bird: 42 available at $15 • Ends Aug 19` or `Only 8 Early Bird tickets left • Ends Aug 19`.
- Added front-end availability hiding for total ticket inventory when the global/event setting says to hide it or only show it when low.
- Added a refund-aware active ticket count for the current event and current user, then hides or corrects the TEC `You have X Tickets for this Event` notice.

## Defaults

- Total ticket availability defaults to `Only show when low`.
- Total availability low threshold defaults to `25`.
- Sale quantity remaining defaults to `Show when a capped sale is active`.
- Sale low threshold defaults to `10`.

## Intentionally not changed

- No TEC ticket record deletion or refund-history deletion.
- No change to historical reporting storage.
- No change to child/youth/veteran ratio rules except that their displayed total availability can now be suppressed by the new display settings.
- No production deployment performed by this package.

## Files changed

- `includes/integrations/ticketing-phase-b.php`
- `includes/integrations/ticketing-rules-v2.php`
- `includes/admin/settings-page.php`
- `includes/cpt/event-plans.php`
- `includes/cpt/event-plans/partials/ticketing-v2.php`
- `assets/admin-ticketing.js`
- `assets/vms-ticketing-front.js`
- `assets/css/vms-ticketing-front.css`
- `assets/css/ticketing-front/30-ticket-polish.css`
- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `includes/core/registry/vms-keys-map.php`
- `vms-build.txt`
- `docs/05-revision-log.md`
- `docs/CODEX-HANDOFF-0.2.24.741.md`
- `BUILD-NOTES-0.2.24.741.md`
- `vms-test-plan-0.2.24.741.md`

## Local verification summary

- `php -l` passed for:
  - `includes/integrations/ticketing-phase-b.php`
  - `includes/integrations/ticketing-rules-v2.php`
  - `includes/admin/settings-page.php`
  - `includes/cpt/event-plans.php`
  - `includes/cpt/event-plans/partials/ticketing-v2.php`
- `node --check` passed for:
  - `assets/admin-ticketing.js`
  - `assets/vms-ticketing-front.js`

## Package

- Package slug: `vms-0.2.24.741-early-bird-caps-sale-availability-refund-count.zip`
