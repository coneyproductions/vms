# VMS Express Bar 0.6.15 Release Notes

## Fixes
- Corrected dedicated `/express-bar/` default event selection so it uses VMS event date/time metadata instead of falling back to WordPress post order or the first Event Plan admin-list row.
- The public page now ranks Express Bar-enabled Event Plans by active/current event, same-day event, then next upcoming event.
- Excludes canceled/cancelled Event Plans from automatic public selection.
- Keeps explicit `?event_plan_id=123` links unchanged.

## Technical Notes
- Added canonical VMS date/time helper reads for `_vms_event_date`, `_vms_start_time`, and `_vms_end_time`, with compatibility fallbacks.
- Retained fallback support for explicit Express Bar open/close windows on unusual installs where VMS event date metadata is missing.
