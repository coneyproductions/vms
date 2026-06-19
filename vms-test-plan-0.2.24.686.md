# VMS 0.2.24.686 Test Plan — Prior Event Orders for Add-On Qualification

## A. Version Markers

1. Upload/activate the package as the canonical `vms` plugin folder.
2. Confirm the active plugin header reports `0.2.24.686`.
3. Confirm `VMS_VERSION` reports `0.2.24.686`.
4. Confirm `/wp-content/plugins/vms/vms-build.txt` returns `0.2.24.686`.

Expected: all markers match `0.2.24.686`.

## B. Returning Customer Can Unlock Add-Ons From Prior Ticket Orders

Use a shopper/account that already completed an order for qualifying tickets on a live event that has reserved add-ons.

1. Open the event page with that same shopper identity available to Woo.
2. Do not add any new tickets yet.
3. Try expanding/selecting the add-on controls.

Expected: the add-on limit math reflects the previously purchased qualifying tickets for that same event, and add-ons that should be unlocked are available immediately.

## C. Prior Add-On Usage Still Counts Against the Pool

Use a shopper/account that already purchased both qualifying tickets and at least one reserved add-on for the same event.

1. Reopen the same event page as that shopper.
2. Attempt to add more add-ons than the total event allowance should permit.

Expected: prior add-on purchases reduce the remaining allowance, and the UI/server block any overage across separate orders.

## D. Current Selection Still Stacks With Prior Orders

1. On the same event page, add one or more new qualifying tickets.
2. Observe the add-on allowance messaging and limits again.

Expected: the page uses `prior qualifying purchases + cart qualifying qty + current page selection` for the add-on math.

## E. Server Enforcement Matches the UI

1. Add the maximum allowed add-ons and submit.
2. Repeat with one more than allowed.

Expected: the valid case adds to cart successfully; the over-limit case is rejected by Woo/VMS validation with a qualification/pool message instead of silently over-adding.

## F. Syntax Checks

Run:

```bash
php -l includes/integrations/ticketing-rules-v2.php
node --check assets/vms-ticketing-front.js
node --check assets/vms-ticketing-front-fallback.js
```

Expected: all commands pass.
