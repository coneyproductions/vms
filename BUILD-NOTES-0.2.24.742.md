# VMS 0.2.24.742

## Purpose
Hotfix for the 0.2.24.741 staging smoke failure in refund-aware `You have X Tickets for this Event` notices.

## Changes
- Updated `vms_ticketing_v2_purchased_ticket_qty_for_user()` so refund subtraction works on HPOS sites where the refund row type is stored in `wp_wc_orders.type = shop_order_refund` and `wp_posts` only contains `shop_order_placehold`.
- Added `vms_ticketing_v2_event_ticket_product_ids_for_event()` and `vms_ticketing_v2_active_ticket_count_for_event_user()` so the active-count calculation can be shared by localized front-end data and the server-rendered event-page notice correction.
- Added a `template_redirect` output-buffer pass that removes TEC's native My Tickets notice when the net active count is zero and rewrites the visible count for partial refunds.
- Hardened the client-side notice sync with broader matching, delayed retries, and a short-lived mutation observer fallback.

## Files changed
- `includes/integrations/ticketing-rules-v2.php`
- `assets/vms-ticketing-front.js`
- `includes/core/registry/constants.php`
- `vendor-management-system.php`
- `vms-build.txt`
- `docs/05-revision-log.md`

## Validation
- `php -l vendor-management-system.php`
- `php -l includes/core/registry/constants.php`
- `php -l includes/integrations/ticketing-rules-v2.php`
- `node --check assets/vms-ticketing-front.js`

## Package
- `vms-0.2.24.742-refund-aware-ticket-notice-hpos-hotfix.zip`
