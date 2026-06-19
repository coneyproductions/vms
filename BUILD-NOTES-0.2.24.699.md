# VMS 0.2.24.699

- Added explicit Event Plan `_checkin_close_at` persistence derived from the normal schedule and post-show scan buffer.
- Syncs that explicit close timestamp to linked `tribe_events` records during publish and re-sync.
- Keeps future event creation from silently omitting explicit scanner close windows that Ops now expects and prefers.
