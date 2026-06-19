# VMS Test Plan — 0.2.24.536

## Scope
Test only the new homepage photo-grid full-calendar CTA option and the public calendar month navigation labels / bottom navigation.

## Test A — photo-grid footer CTA
1. Edit the page using the VMS native photo-grid shortcode.
2. Use a shortcode such as `[vms_events_photo limit="6" more_link="1"]`.
3. Confirm a centered `View Full Concert Calendar` button appears under the grid.
4. Click it and confirm it opens the public concert calendar page.

## Test B — calendar month navigation labels
1. Open the public concert calendar page.
2. Confirm the top previous/next links use month names like `March` / `May`, not raw `2026-03` / `2026-05`.
3. Confirm the same previous/next navigation also appears at the bottom of the calendar.
4. Click both top and bottom links and confirm month navigation still works.

Report PASS or FAIL only, with brief evidence.
