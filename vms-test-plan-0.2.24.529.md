# VMS Test Plan — 0.2.24.529 Safari-Safe Live Refund Action

1. Install `vms-0.2.24.529-live-refund-safari-safe-link-routing.zip`.
2. Confirm the plugin reports **0.2.24.529** in the Plugins screen, VMS admin footer/build stamp, and `vms-build.txt`.
3. Open an already-cancelled Event Plan with a visible **Run Live Refunds Now** button on desktop and on iPhone/Safari if available.
4. Click **Run Live Refunds Now** and choose **Cancel** in the confirmation prompt. Confirm nothing runs.
5. Click **Run Live Refunds Now** again and choose **OK**. Confirm the page navigates without a post save and without any “request form is missing” error.
6. Confirm the page returns to the same cancelled Event Plan and shows refund-only result messaging.
7. Verify at least one eligible Woo order shows a real refund entry/order note when a live refund is expected.
8. Re-run on the same event and confirm already-refunded lines are not refunded twice.

🚨 This touches live money flow. Test first on a safe cancelled event with known eligible orders.
