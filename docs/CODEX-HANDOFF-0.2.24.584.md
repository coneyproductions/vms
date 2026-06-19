# CODEX HANDOFF — VMS 0.2.24.584

## Focus
Add a Square Sync Protection firewall so VMS/TEC ticket, admission, pass, and event add-on Woo products cannot be accidentally managed by Square catalog or Square inventory sync.

## What changed
- Added `includes/integrations/square-sync-firewall.php`.
- Added `includes/admin/square-sync-protection.php`.
- Added **VMS > Square Sync Protection** admin page.
- Added a scan/repair workflow that finds protected VMS/TEC products, forces `Sync with Square = no`, and clears stale Square item/variation/image/version metadata.
- Added CSV export for the latest scan/repair report.
- Added runtime guards on product saves and VMS ticketing marker writes.
- Added generic Square/Woo product-sync filter guards for plugin-version compatibility.
- Hardened the existing ticketing reporting-category Square bridge so protected VMS products are protected instead of queued for Square sync.
- Added a small automatic admin-side backfill that protects products in batches, with the explicit repair page available for immediate cleanup.

## Protection scope
Products are protected when they match ticket/admission/event-control signals such as:
- SKU beginning with `VMS-` or `VMS_`.
- VMS product roles like `ga_ticket`, `ticket`, `legacy_ticket`, `entitlement`, `addon`, `pass`, `admission`, or `rsvp`.
- VMS ticketing entitlement markers.
- Product categories `online-ticket`, `online-addon`, or `tickets`.
- TEC Woo ticket event linkage.
- VMS ticketing marker/source metadata.

Normal reusable catalog items are intentionally skipped unless they match those protected signals. This allows Square to remain the source of truth for future bar/menu/merch inventory while VMS remains the source of truth for tickets, passes, event credits, comps, and event add-ons.

## Highest-risk areas to test
1. VMS/TEC ticket and add-on products must remain `Sync with Square = no` after Event Plan ticketing commits.
2. Repair must remove stale Square item IDs only from protected VMS/TEC products.
3. Square-owned catalog items such as shirts, eggs, or express bar products must not be protected merely because they are sold during an event.
4. Ticketing v2, qualified tickets, add-on eligibility, subtotal math, checkout, and Ticket Integrity screens must still work.
5. WooCommerce Square payments must continue to work; the change is about product/catalog sync, not payment authorization.

## Files changed / added
- `includes/integrations/load.php`
- `includes/integrations/square-sync-firewall.php`
- `includes/integrations/ticketing-phase-b.php`
- `includes/admin/load.php`
- `includes/admin/square-sync-protection.php`
- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `includes/modules/email-followups/email-followups.php`
- `vms-build.txt`
- `docs/05-revision-log.md`
- `docs/06-test-plan.md`
- `docs/test-plan-0.2.24.584-square-sync-firewall.md`
- `docs/CODEX-HANDOFF-0.2.24.584.md`
- `vms-test-plan-0.2.24.584.md`

## Testing completed in package build
- `php -l` passed for the new Square Sync Firewall file.
- `php -l` passed for the new Square Sync Protection admin file.
- `php -l` passed for touched loaders and ticketing integration file.

## Production caution
🚨 Run on staging first when possible. After installing, use **VMS > Square Sync Protection > Scan protected products** before using **Repair protected products**. Repair is designed to prevent Square from owning event/admission products and should not touch normal Square-owned catalog products.
