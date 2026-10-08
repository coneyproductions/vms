# BVM 1.3.5 Ticketing Amenities Restoration Test Plan

Use staging and disposable Event Plans/products only. Keep mail delivery blocked.

## Configuration and fallbacks

1. Save a global Amenities heading, subtext, help text, and `#dda6a6` heading background.
2. Verify a Progressive event without overrides uses the saved global values.
3. Add Event Plan heading/subtext overrides and verify they take precedence.
4. Clear overrides, then clear global heading/subtext values, and verify built-in fallbacks.
5. Disable global add-on help with no override, then add an Event Plan help override; verify the existing visibility rules in both states.

## Public behavior

1. With eligible Amenities and zero selected quantities, verify the section starts expanded.
2. Collapse it with the keyboard and pointer, change a ticket quantity to trigger re-enhancement, and verify it stays collapsed.
3. Reopen it and verify add-on/rental controls remain usable.
4. Verify `#dda6a6` uses readable dark foreground text and focus remains visible.
5. Verify an event with no eligible Amenities exposes no empty section.

## Regression and layout

1. Exercise ticket increment/decrement, paid-ticket eligibility, child/ratio minimums, fire/table minimums, add-on increment/decrement, rental terms, subtotal, and cart handoff.
2. Verify no changes to prices, quantities, cart payload, checkout, claims, or inventory.
3. Repeat at desktop and `390px`; confirm no horizontal overflow or browser-console errors.
4. Confirm public CSS source/assembled copies match and all assets use the 1.3.5 cache key.

## Cleanup and rollback

Delete disposable Event Plans/products and restore temporary global settings.
If acceptance fails, restore the captured staging BVM runtime and database backups,
then clear page/object caches. Do not deploy to production without separate approval.
