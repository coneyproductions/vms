# VMS 0.2.24.654 Test Plan — Single-Ticket Early/Regular Price Windows

## Target behavior

A single public ticket product can carry an early/advance price for a configured period and then return to its regular price without using separate Early/Regular ticket products.

## Pre-test setup

Use a staging Event Plan with Ticketing v2 enabled and progressive public ticket UI enabled.

Recommended test ticket:

- Ticket title: `General Admission`
- Who can purchase: `Anyone`
- Regular price: `25`
- Early price: `20`
- Early starts: blank or a past date/time
- Early ends: a future date/time
- Inventory total: any safe staging quantity

## Admin UI checks

1. Open the Event Plan ticket config.
2. Confirm the existing price field is labeled **Regular price**.
3. Enter an early price lower than the regular price.
4. Enter an early end date/time.
5. Save config.
6. Reload the Event Plan.
7. Confirm early price, early starts, and early ends persist.
8. Confirm the ticket row summary reads like `Early $20 / Regular $25` when early price and early end are configured.

## Preview/Commit checks

1. Click **Preview sync**.
2. Confirm Preview detects the ticket change and does not require creating a separate Early GA product.
3. Commit/push the ticket changes.
4. Confirm the ticket maps to one Woo/TEC product for General Admission.
5. Confirm the mapped Woo product has:
   - regular price = `25`
   - sale price = `20`
   - sale end date matching the configured early end

## Public purchase checks during early window

1. Open the public event page in a clean/private browser.
2. Confirm only one public **General Admission** row appears.
3. Confirm the visible/current price is the early price.
4. Select a quantity and confirm the progressive UI total uses the early price.
5. Add the ticket to cart.
6. Confirm cart/checkout line item uses the early price.

## Price cutoff checks

Use a safe staging event/product.

1. Change **Early ends** to a time that has already passed.
2. Save config and Preview/Commit.
3. Clear page/object cache if needed.
4. Open the public event page in a clean/private browser.
5. Confirm General Admission still appears as the same product.
6. Confirm the visible/current price is now the regular price.
7. Add to cart and confirm cart/checkout uses the regular price.

## Guardrail checks

1. Set early price equal to regular price and Preview sync.
   - Expected: Preview blocks/warns because early price must be lower than regular price.
2. Set early price higher than regular price and Preview sync.
   - Expected: Preview blocks/warns.
3. Set early price but leave Early ends blank and Preview sync.
   - Expected: Preview blocks/warns because an end date is required.
4. Set Early starts after Early ends and Preview sync.
   - Expected: Preview blocks/warns.

## Regression checks

- Existing events with no early price continue using the regular price.
- Qualified/free tickets still enforce registration/approval.
- Disabled-ticket public hiding from 0.2.24.650–0.2.24.653 still works.
- Legacy GA public visibility from 0.2.24.653 still works when disabled specialized rows exist before the real GA row.
- Ticket/add-on unlock totals still use the current effective ticket price but qualification remains based on configured qualifying ticket quantity, not price.
- Existing ticket sales/history are not deleted or remapped.

## Pass criteria

- One public GA ticket can sell at early price before the cutoff and regular price after the cutoff.
- No duplicate Early GA product is needed.
- Invalid early-price setup is blocked before managed sync.
- Public ticket UI, cart, and checkout agree on the active price.
