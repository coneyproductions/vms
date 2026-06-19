# VMS 0.2.24.653 Test Plan — Legacy GA Public Visibility Guard

## Target issue

General Admission can disappear from the public event page when a newer ticket template inserts disabled **Early General Admission** before the real GA row and the event still uses an older single-GA sync map.

## Pre-test setup

Use an event with:

- Early General Admission row present and disabled
- General Admission row enabled
- GA mapped to the existing Woo/TEC product through the legacy GA sync map or preview showing `TICKET: UPDATE "General Admission" (product #...)`
- Qualified/free tickets enabled
- Public progressive ticket UI enabled

## Steps

1. Open the Event Plan ticket config.
2. Confirm Early General Admission is disabled.
3. Confirm General Admission is enabled, priced, has inventory, has a valid sales window, and `Who can purchase?` is `Anyone`.
4. Click **Preview sync**.
5. Confirm preview says Early GA is skipped or has no product to unpublish.
6. Confirm preview says General Admission updates or skips its existing mapped product, not disabled.
7. Open the public event page in a clean/private browser session.
8. Confirm the public ticket UI shows General Admission.
9. Confirm qualified/free tickets still require registration/approval as expected.
10. Select GA quantity and confirm the add-to-cart button enables.
11. Add GA to cart and confirm the correct product/name/price appears in cart.
12. Clear any page/object cache and retest if the first public reload still shows old ticket output.

## Regression checks

- Disable a verified/free ticket that has a known mapped product, save config without commit, and confirm it is hidden/blocked publicly until sync is committed.
- Confirm the disabled-ticket guard still blocks stale disabled qualified tickets server-side at add-to-cart/cart/checkout.
- Confirm a truly disabled legacy GA row with no enabled GA replacement does not remain purchasable.
- Confirm existing ticket sales/history are not deleted or remapped.

## Pass criteria

- Disabled Early GA does not hide enabled General Admission.
- General Admission appears publicly and can be added to cart.
- Disabled qualified/free tickets remain protected during the Save Config → Commit window.
- No duplicate product mapping warning appears in Preview sync.

