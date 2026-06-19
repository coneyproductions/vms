# VMS 0.2.24.666 — Cancelled Event Public Safety Hardening

## Purpose

This build addresses public-facing cancellation gaps observed after marking an Event Plan cancelled. It preserves the 0.2.24.665 save-path/profile cleanup while tightening customer-facing cancellation behavior.

## Changes

- Changes the `[vms_events_photo]` / `[vms_events_photo_grid]` default so cancelled events are **not included by default** in front-page/photo-card event views. Operators may still opt in with `include_cancelled="1"` where they intentionally want that behavior.
- Keeps internal cancellation reason code and internal cancellation notes private on public TEC event pages. Public banners now show safe generic cancellation/reschedule copy only.
- Adds a cancelled-event body class for public TEC event pages.
- Adds a fail-closed final HTML cleanup pass for cancelled TEC event pages to remove known TEC/VMS ticket purchase containers even if a provider or custom ticket UI bypasses the normal Event Tickets hooks.
- Blocks add-to-cart for Woo products tied to a cancelled TEC event/VMS Event Plan, including free/children/qualified tickets and reserved add-ons.
- Adds cart/checkout validation so stale cancelled-event items already in cart block checkout with a clear cancellation notice.

## Notes

- Staff/vendor cancellation notifications already exist in the cancellation notification adapter; this build does not rewrite the email system. The test plan asks Codex to verify staff recipients are included when staff assignments exist.
- The full public calendar shortcode still has its own explicit cancellation behavior. This build only changes the front-page/photo-card shortcode default because the immediate issue was cancelled shows remaining in the front-page view.
