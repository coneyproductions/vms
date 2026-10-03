# VMS Express Bar 0.6.13 - dedicated page event auto-selection

## Summary

This pass fixes the dedicated `/express-bar/` page so it chooses the correct Express Bar-enabled event when no `event_plan_id` is supplied.

## Changes

- Replaced the single meta-query default event resolver with a ranked PHP resolver.
- The dedicated page now prefers an active/current Express Bar event, then a same-day event, then the next upcoming Express Bar-enabled event.
- Past events and events without Express Bar enabled are excluded from automatic selection.
- Added resilient date/time parsing across explicit Express Bar windows, VMS event dates/times, and common TEC-style start/end meta.
- Direct links with `?event_plan_id=...` continue to override automatic selection.
- Bumped plugin header, `VMSEB_VERSION`, and `vms-build.txt` to `0.6.13`.

## Why

The public `/express-bar/` page could select an unexpected event when it was loaded without an explicit event ID. Customers need one stable Express Bar URL that automatically attaches to today's event when active, or the closest upcoming enabled event otherwise.
