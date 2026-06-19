# VMS Test Plan — 0.2.24.528 Live Refund Standalone Form Hardening

1. Install `vms-0.2.24.528-live-refund-standalone-form-hardening.zip`.
2. Confirm the plugin reports **0.2.24.528** in the Plugins screen, VMS admin footer/build stamp, and `vms-build.txt`.
3. Open an already-cancelled Event Plan with a visible **Run Live Refunds Now** button.
4. Click **Run Live Refunds Now** and choose **Cancel** in the confirmation prompt. Confirm no post save occurs and no refund notices appear.
5. Click **Run Live Refunds Now** again and choose **OK**. Confirm the page redirects back to the same Event Plan without showing unrelated post-validation notices like staffing errors or “Post updated.”
6. Confirm the Cancellation Job panel updates with refund-only results.
7. Verify at least one eligible Woo order shows a real refund entry/order note when a live refund is expected.
8. Verify already-refunded or unsafe/manual-review orders are skipped and not double-refunded.

🚨 This pass touches live money flow. Test with a safe known order/event first.
