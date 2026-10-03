# VMS Express Bar 0.6.14 - dedicated page shortcode override fix

## Summary

This pass fixes an edge case where the dedicated `/express-bar/` page could remain pinned to an old event if the page shortcode had an `event_plan_id` attribute saved in the page content.

## Changes

- On the dedicated Express Bar page, no-query loads now ignore a stale shortcode `event_plan_id` and use automatic event selection instead.
- Direct links with `?event_plan_id=...` still override automatic selection.
- Event-specific shortcodes on normal event pages or other pages still honor their explicit `event_plan_id`.
- Bumped plugin header, `VMSEB_VERSION`, and `vms-build.txt` to `0.6.14`.

## Why

The dedicated `/express-bar/` URL should behave as a stable customer-facing page that chooses today's active event or the next eligible Express Bar event. A legacy hardcoded shortcode on that page should not pin customers to the wrong event.
