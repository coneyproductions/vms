# VMS Express Bar 0.6.16 Release Notes

## Fix
- Improved dedicated `/express-bar/` automatic Event Plan selection so it no longer requires `_vms_event_date` to exist on the Event Plan query itself.
- The selector now fetches all Express Bar-enabled Event Plans, ignores WordPress list order, reads a broader set of VMS/TEC date keys, and can fall back to linked event post date meta when available.
- Explicit `?event_plan_id=123` links continue to win.

## Why
Some installs store Event Plan dates under different keys or only on linked TEC/event posts. The previous selector could therefore miss the correct same-day event and keep choosing the first enabled Event Plan.
