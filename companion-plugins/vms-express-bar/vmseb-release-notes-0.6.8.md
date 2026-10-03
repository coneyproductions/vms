# VMS Express Bar 0.6.8 — browse tooltip and category header polish

## Changes

- Added a hover/focus tooltip to disabled quantity steppers when the Express Bar menu is visible in browse-only or closed mode.
- Tooltip text reuses the public ordering-window message, prefixed as an Express Bar message when appropriate.
- Fixed category accordion header styling so text remains visible without requiring hover.
- Added explicit category header hover/focus/open colors to avoid theme button styles turning the row into white-on-white or blue-on-white unexpectedly.
- Bumped plugin header, `VMSEB_VERSION`, and `vms-build.txt` to `0.6.8`.

## Notes

This pass does not change Woo/Square sync behavior or cart-building rules. It is a customer-facing polish pass for the dedicated Express Bar menu page.
