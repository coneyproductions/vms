# VMS Test Plan — 0.2.24.515

## Scope
New Event Command Center admin page for Event Plans, including Event Plan list-row access and editor quick-link access.

## Verify
1. Go to **VMS → Event Command Center** and confirm the page opens inside the normal VMS admin shell.
2. Confirm the event picker appears at the top and switching the selected Event Plan reloads the page cleanly.
3. From **VMS → Event Plans**, confirm each Event Plan row now shows a **Command Center** action link.
4. Open an Event Plan editor and confirm the Publish box shows an **Open Command Center** button.
5. On the Command Center page, confirm the top section shows the correct:
   - event title
   - event date
   - event time
   - venue
   - status
   - days-until label
6. Confirm the first-row cards render without fatal errors:
   - Show Health
   - Ticket Snapshot
   - Financial Snapshot
   - Alerts
7. Confirm the middle cards render without fatal errors:
   - Schedule / Timeline
   - Lineup & Participants
8. Confirm the lower cards render without fatal errors:
   - Marketing Snapshot
   - Weather / Venue Conditions
   - Next Actions
   - Internal Notes
   - Recent Activity
9. Test one event with known unpublished changes and confirm the Command Center surfaces a review/alert item for those tracked changes.
10. Test one event with staffing gaps and confirm the Command Center shows the staffing shortfall in both the snapshot/alerts area and the Staff list.
11. Test one event with lineup entries and confirm supporting acts appear in the Lineup & Participants card and timed lineup rows appear in Schedule / Timeline when set times exist.
12. Test one event with ticket sales and confirm sold count / gross sales populate in Ticket Snapshot.
13. Confirm the page degrades gracefully when optional modules are missing:
   - weather card should not fatal if the weather add-on is absent
   - marketing card should still render even if Meta Ads Builder is not active
14. Confirm no PHP fatal/errors occur when opening the Command Center for:
   - a Draft Event Plan
   - a Published Event Plan
   - an Event Plan with little or no optional data filled in
