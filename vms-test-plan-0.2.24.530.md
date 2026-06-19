# VMS Test Plan — 0.2.24.530 Live Refund ID Resolution Hardening

1. Install `vms-0.2.24.530-live-refund-id-resolution-hardening.zip`.
2. Confirm the plugin reports **0.2.24.530** in the Plugins screen, VMS footer/build stamp, and `vms-build.txt`.
3. Open an already-cancelled Event Plan that previously showed **Run Live Refunds Now**.
4. Click **Run Live Refunds Now** on desktop. Confirm:
   - the post is **not** saved
   - no unrelated editor validation notices appear
   - the action returns to the Cancellation Job panel with refund-only messaging
5. Repeat the same test on mobile Safari/iPhone. Confirm the button navigates into the standalone action and no longer throws:
   - “standalone refund request form is missing”
   - “Invalid Event Plan for live refund action.”
6. Verify one affected Woo order:
   - a real refund note appears if the order is auto-eligible
   - already-refunded lines are not refunded twice
7. Verify mixed/unsafe/manual-only orders remain queued for manual review instead of being force-refunded.
