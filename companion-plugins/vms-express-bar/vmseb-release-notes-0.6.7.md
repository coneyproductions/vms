# VMS Express Bar 0.6.7 — browse-only window wording polish

## Summary

This pass tightens the dedicated Express Bar page behavior after live staging verification. The page can still show the full menu before ordering opens, but the customer-facing status no longer implies a strange midnight opening time when an exact ordering window has not been configured.

## Changes

- Changed the default event-day fallback so Express Bar does **not** invent a midnight opening time.
- If no opening time is configured, the public page remains browse-only and says ordering opens during the event.
- Status card now supports a clearer `Browse Only` label instead of showing `Closed` while the menu is visible for pre-event browsing.
- After the event/order window has passed, browse messaging no longer says customers can come back before ordering opens.
- Updated Event Plan metabox helper text so blank opening time means browse-only until an opening time is set.
- Preserved the dedicated `/express-bar/` page behavior, event-page CTA handoff, and disabled quantities during browse-only mode.

## Files changed

- `vms-express-bar.php`
- `vms-build.txt`
- `README.txt`
- `includes/helpers.php`
- `includes/public.php`
- `includes/admin.php`

## Notes

For checkout/orders to become active, set an explicit **Ordering opens** time on the Event Plan Express Bar box. If **Ordering closes** is left blank, the plugin will close at the end of the event date once ordering has opened.
