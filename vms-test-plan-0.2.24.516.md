# VMS Test Plan — 0.2.24.516

## Focus
Event Command Center cleanup pass.

## Verify
1. Open **VMS → Event Command Center** for a populated Event Plan.
2. Confirm the top event switcher is now compact once a plan is open.
3. Confirm the header pills are simplified:
   - plan status
   - health status
   - at most two meaningful warning pills
4. Confirm the page no longer shows a weather-install alert in **Alerts** or **Next Actions** when the weather add-on is absent.
5. Confirm **Next Actions** only shows real red/yellow follow-up items.
6. Confirm **Marketing Snapshot** no longer uses the large “Builder available” row and instead shows cleaner event-focused summary plus action buttons.
7. Confirm **Weather / Venue Conditions** reads as muted helper text when live weather tracking is unavailable.
8. Confirm **Schedule / Timeline** no longer includes staffing summary rows.
9. Confirm bottom-row cards no longer stretch awkwardly to match neighboring card height.
10. Confirm Event Plans list still shows the **Command Center** row action and the Event Plan editor still shows **Open Command Center** in the Publish box.

## Regression smoke
- Event Command Center opens from list row action.
- Event Command Center opens from Event Plan editor.
- No PHP fatal / critical error on the Command Center screen.
- Existing Event Plan edit screens still load normally.
