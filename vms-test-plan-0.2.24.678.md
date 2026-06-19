# VMS 0.2.24.678 Test Plan — Public Ticket Sale + Limited Ticket Guidance Polish

## A. Version Markers

1. Upload/activate the package as the canonical `vms` plugin folder.
2. Confirm the active plugin header reports `0.2.24.678`.
3. Confirm `VMS_VERSION` reports `0.2.24.678`.
4. Confirm `/wp-content/plugins/vms/vms-build.txt` returns `0.2.24.678`.

Expected: all markers match `0.2.24.678`.

## B. Public Sale Badge / Price Display

Use an event with an active early/sale price where the regular price is higher than the active sale price.

1. Open the public event page logged out or in an incognito window.
2. Locate the sale-priced ticket row.
3. Confirm the `On Sale` badge is visibly larger than before.
4. Confirm the regular price remains crossed out.
5. Confirm the sale price is visually stronger/more eye-catching.
6. Confirm the sale end date/time appears near the badge when the ticket row has an early-price end value.

Expected: the sale state, sale price, and sale deadline are all obvious without relying on checkout.

## C. Limited Ticket Guidance With 0 Qualifying Tickets

Use a ticket setup where a youth/child-style ticket requires qualifying/adult/GA ticket quantity.

1. Open the public event page logged out or in an incognito window.
2. Leave qualifying ticket quantity at `0`.
3. Locate the ratio-limited ticket row.
4. Confirm a visible inline note explains the requirement before add-to-cart.
5. Try increasing the limited ticket row quantity.

Expected: the row explains the requirement, the plus control is blocked/disabled where supported, and the quantity does not remain above the allowed amount.

## D. Limited Ticket Guidance With Qualifying Tickets Selected

1. Increase qualifying ticket quantity to a valid amount, for example `4`.
2. Confirm the ratio-limited ticket row note updates to show how many are allowed.
3. Increase the limited ticket quantity up to the allowed amount.
4. Try increasing beyond the allowed amount.

Expected: the note updates based on current selected qualifying tickets, the allowed quantity matches the configured ratio rule, and the limited ticket cannot remain above the allowed quantity.

## E. Shared Ratio Group Regression

Use an event/template with more than one limited ticket row drawing from the same shared ratio group, such as Youth and Children's tickets.

1. Select qualifying tickets.
2. Add quantity across more than one limited ticket row.
3. Confirm the total across the shared limited rows cannot exceed the shared allowance.
4. Reduce one limited row and confirm another row can use the newly available allowance.

Expected: shared allowance behavior remains consistent with `0.2.24.676` server-side rules, while the public UI now explains and guides it earlier.

## F. Server-Side Guard Regression

1. Attempt to bypass the UI by manipulating quantities or using stale cart/session state if possible.
2. Try add-to-cart/cart/checkout with too many limited tickets.

Expected: existing Woo/VMS cart/checkout validation still rejects invalid limited-ticket combinations.

## G. Add-On Regression Smoke

1. Select qualifying tickets that unlock a fire pit/table add-on.
2. Confirm add-on requirement messages still update normally.
3. Reduce qualifying tickets below the add-on requirement.

Expected: add-on qualification behavior is unchanged.

## H. Syntax / Packaging

Run:

```bash
php -l vendor-management-system.php
php -l includes/core/registry/constants.php
php -l includes/integrations/ticketing-rules-v2.php
node --check assets/vms-ticketing-front.js
zip -T VMS_678_ticket_public_ui_polish.zip
```

Expected: all commands pass.
