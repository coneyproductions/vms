# VMS 0.2.24.642 Test Plan — Progressive Help Copy + Add-On Heading Overrides

## Scope

This patch verifies that Progressive ticket UI help text appears reliably and that the add-on section heading/subtext can be edited per event.

## Preconditions

- Deploy `VMS_642_progressive_help_addon_heading_patch.zip` to staging.
- Confirm `/wp-content/plugins/vms/vms-build.txt` starts with `0.2.24.642`.
- Use an event with Progressive ticket UI enabled, GA tickets, at least one verified/free ticket, and at least one add-on/fire table.

## 1. Existing quantity hotfix smoke test

1. Add 6 GA tickets.
2. Go to cart and reduce 6 → 4.
3. Wait, refresh, and confirm quantity remains 4.
4. Increase 4 → 7.
5. Wait, refresh, and confirm quantity remains 7.

Expected: no VMS max-qty blocker appears for normal public/GA tickets.

## 2. Ticket help override appears in Progressive layout

1. Open the Event Plan admin screen.
2. In Ticketing → Public help copy overrides, enter obvious text in **Ticket help override**, such as `TEST ticket instructions appear here`.
3. Save/update the Event Plan.
4. Open the public event page in a fresh/private browser window.
5. Confirm the text appears directly under the **Tickets** heading and above the ticket rows.
6. Refresh the page and confirm it still appears.
7. Change a ticket quantity and confirm the text does not disappear after the UI refreshes.

Expected: the ticket help block remains visible in Progressive mode.

## 3. Global help toggle does not suppress explicit event instructions

1. In VMS Settings → Ticketing, temporarily turn off **Show ticket help above Tickets**.
2. Keep the Event Plan **Ticket help override** populated.
3. Reload the public event page.

Expected: the event-specific override still appears because the event has explicit instructions.

4. Re-enable the global setting after this check if it should remain on.

## 4. Per-event add-on heading/subtext override

1. Open the Event Plan admin screen.
2. In Ticketing → Public help copy overrides, set:
   - **Add-on section heading override**: `Reserved Extras`
   - **Add-on section subtext override**: `Click here to add optional reserved extras.`
3. Save/update the Event Plan.
4. Reload the public event page.
5. Confirm the collapsed add-on section shows the new heading and subtext.
6. Expand the add-on section and confirm add-on rows still render and can be selected when eligible.

Expected: event-specific heading/subtext take precedence over the global defaults.

## 5. Inheritance fallback

1. Clear the Event Plan add-on heading and subtext override fields.
2. Save/update the Event Plan.
3. Reload the public event page.

Expected: Progressive add-ons fall back to the global VMS Settings values, then to `Fire Pits & Tables` and `Click here to add a fire pit or table to your order.` if global values are blank.

## 6. Verified/free ticket smoke

1. While logged out, set a verified/free ticket quantity to 1.
2. Confirm the selected-row registration/login guidance still appears.
3. Confirm unapproved users cannot add verified/free tickets directly.

Expected: existing verified-ticket guardrails still work.
