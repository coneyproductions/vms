# VMS 0.2.24.742 Test Plan — HPOS Refund-Aware My Tickets Notice

1. Confirm version markers show `0.2.24.742` in the plugin header, `VMS_VERSION`, and `vms-build.txt`.
2. PHP lint `vendor-management-system.php`, `includes/core/registry/constants.php`, and `includes/integrations/ticketing-rules-v2.php`. Run `node --check assets/vms-ticketing-front.js` where Node is available.
3. On an HPOS-enabled staging site, create a test event/ticket fixture and user with a completed paid ticket order. Confirm the event page server HTML shows the native active ticket notice.
4. Partially refund the order. Confirm the event page server HTML and localized `myActiveTicketCount` both show the net active ticket count.
5. Fully refund the order. Confirm the event page server HTML no longer contains `You have X Ticket(s) for this Event`, and localized `myActiveTicketCount` is `0`.
6. Confirm historical attendee/order/refund records remain intact.
7. Re-run the 0.2.24.741 sale-cap smoke sections to make sure the hotfix did not affect Early Bird cap pricing, sale availability display, or checkout repricing.
