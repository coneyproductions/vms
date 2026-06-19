# VMS 0.2.24.679 Test Plan — Sale Price Emphasis Follow-up

## A. Version Markers

1. Upload/activate the package as the canonical `vms` plugin folder.
2. Confirm the active plugin header reports `0.2.24.679`.
3. Confirm `VMS_VERSION` reports `0.2.24.679`.
4. Confirm `/wp-content/plugins/vms/vms-build.txt` returns `0.2.24.679`.

Expected: all markers match `0.2.24.679`.

## B. Public Sale Price Emphasis Regression

Use the same public event surface from the `0.2.24.678` test pass with an active early/sale price.

1. Open the public event page logged out or in an incognito window.
2. Locate the sale-priced ticket row.
3. Confirm the `On Sale` badge remains visible/larger.
4. Confirm the sale deadline still renders, for example `Sale ends May 20`.
5. Inspect the regular and sale price computed styles after hydration.

Expected:

- Regular price remains visibly crossed out.
- Sale price has `vms-ticket-sale-price` on the active price node or its wrapper.
- Sale price is visually stronger than the regular price, with larger font size and heavier weight.
- Sale price no longer computes the same `20px / 700 / rgb(15,23,42)` style observed in the failed `0.2.24.678` B.5 check.

## C. Limited Ticket Guidance Smoke

1. Leave qualifying ticket quantity at `0`.
2. Confirm youth/children ratio-limited rows still show requirement notes and clamp at `0`.
3. Increase qualifying tickets to `4`.
4. Confirm youth/children notes and shared ratio clamping still work.

Expected: no regression from the `0.2.24.678` C/D/E passes.

## D. Add-On Guidance Smoke

1. Select qualifying tickets that unlock a fire pit/table add-on.
2. Confirm add-on guidance still changes from blocked to allowed.
3. Reduce qualifying tickets below the add-on requirement.

Expected: no regression from the `0.2.24.678` G pass.

## E. Syntax / Packaging

Run:

```bash
php -l vendor-management-system.php
php -l includes/core/registry/constants.php
node --check assets/vms-ticketing-front.js
zip -T VMS_679_ticket_sale_price_emphasis.zip
```

Expected: all commands pass.
