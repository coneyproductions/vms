# VMS 0.2.24.680 Test Plan — Sale Styling + Template Replacement Cleanup

## A. Version Markers

1. Upload/activate the package as the canonical `vms` plugin folder.
2. Confirm the active plugin header reports `0.2.24.680`.
3. Confirm `VMS_VERSION` reports `0.2.24.680`.
4. Confirm `/wp-content/plugins/vms/vms-build.txt` returns `0.2.24.680`.

Expected: all markers match `0.2.24.680`.

## B. Public Sale Styling

Use a public event with an active early/sale price.

1. Open the public event page logged out or in an incognito window.
2. Locate the sale-priced ticket row.
3. Confirm the `On Sale` badge is visible, slimmer than `0.2.24.679`, and still readable.
4. Confirm the sale deadline appears next to the badge, not on a separate line that adds extra vertical height.
5. Confirm the regular price remains crossed out.
6. Confirm the sale price is red and only slightly stronger/larger than the normal price, not oversized.

Expected: sale styling is readable but not visually ridiculous, and the sale metadata consumes less vertical space.

## C. Current Ratio / Add-On Guidance Regression

1. With qualifying ticket qty `0`, confirm youth/child ratio-limited tickets show requirement notes and clamp at `0`.
2. Increase qualifying tickets to an unlocking quantity.
3. Confirm ratio-limited tickets unlock and shared ratio groups still clamp correctly.
4. Confirm fire-pit/table add-on guidance still changes from blocked to allowed and relocks when qualifying quantity drops.

Expected: no regression from the `0.2.24.679` pass.

## D. Template Replacement Cleanup

Use a local/staging event plan with VMS-managed ticketing and an already-linked TEC event.

1. Seed or use an existing ticket config that has at least one published ticket product on the TEC event.
2. Apply a different ticket template/config that removes at least one old ticket row completely from the current `tickets` payload.
3. Run Ticketing Preview.
4. Confirm Preview includes a cleanup action for the stale VMS-owned ticket product, with scope `ticket_cleanup` and action `retire_unmapped`.
5. Commit the sync.
6. Confirm the stale product is set to `draft` and hidden catalog visibility.
7. Open the public event page logged out/incognito.

Expected: the stale ticket no longer appears publicly, while current tickets still appear normally.

## E. Non-VMS-Owned Ticket Safety

1. Attach or simulate a ticket product linked to the TEC event that does not have VMS source markers for the current Event Plan.
2. Remove it from the current VMS ticket config if necessary.
3. Run Preview.

Expected: VMS leaves the not-clearly-owned product alone and reports a warning rather than retiring it.

## F. Syntax / Packaging

Run:

```bash
php -l vendor-management-system.php
php -l includes/core/registry/constants.php
php -l includes/integrations/ticketing-phase-b.php
node --check assets/vms-ticketing-front.js
zip -T VMS_680_ticket_template_cleanup_sale_polish.zip
```

Expected: all commands pass.
