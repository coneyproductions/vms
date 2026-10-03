# VMS Express Bar 0.6.17 Release Notes

## Fix
- Updated the dedicated `/express-bar/` auto-selection behavior so it chooses the current/today/next non-cancelled Event Plan by actual event date/time, instead of only considering Event Plans where the per-event Express Bar checkbox was already enabled.
- The permanent Express Bar page can now use the current/next Event Plan as its ordering context even if the operator has not toggled Express Bar on that specific Event Plan yet.
- Event-page CTAs and explicit event-specific shortcodes still respect the per-event Express Bar enabled checkbox.

## Why
The live page was still selecting the first Express Bar-enabled Event Plan in the admin list, which could be the wrong date. The dedicated page is intended to be the one customer-facing URL that follows today's/next event automatically.
