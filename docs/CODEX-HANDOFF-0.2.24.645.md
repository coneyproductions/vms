# Codex Handoff — VMS 0.2.24.645

## Issue
A logged-out visitor could see add-ons on one event but not another active event. The affected event still had ticket UI/content, but the reserved add-on block was not rendered for public users.

## Change
Removed the public rendering suppression that hid reserved add-ons whenever `vms_ticketing_v2_ga_is_on_sale_now()` returned false. That helper can be stale or misaligned with live TEC/Woo ticket rendering, especially on day-of events. Add-on visibility is now based on active event status and mapped entitlement products.

## Safety boundaries
- Cancelled events still suppress add-ons.
- Past events still suppress active add-ons.
- Events with no enabled/mapped entitlement products still do not render an empty add-on section.
- Qualification, stock, and checkout/add-to-cart validation remain unchanged.

## Test focus
Use the event that failed publicly plus a known-working event. Confirm the Fire Pits & Tables section appears logged out, and that add-on qualification still blocks under-qualified carts.
