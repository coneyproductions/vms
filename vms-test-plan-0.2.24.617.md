# VMS 0.2.24.617 Test Plan — Public Calendar Mobile List Hotfix

## Purpose
Prevent mobile visitors from landing on the public calendar month grid and having to scroll through blank weekday/day cells before discovering actual event cards.

## Files touched
- `includes/public/venue-calendar-shortcode.php`
- `assets/css/vms-ui.css`
- `assets/js/vms-public-calendar.js`
- version/build markers

## Manual tests
1. Install/activate VMS 0.2.24.617 on staging.
2. Open the public calendar page on a phone-width viewport or real phone with no `view` parameter.
   - Expected: event cards appear in List view; no blank weekday/month cells appear above them.
3. Open the public calendar page on phone-width viewport with `?view=month`.
   - Expected: page still shows List view on mobile.
4. Change Month and tap Go on mobile.
   - Expected: submission preserves the chosen month and uses List view.
5. Open desktop-width calendar.
   - Expected: Month view still works on desktop, including event popovers.
6. Resize desktop Month view down below 782px.
   - Expected: month grid is hidden and the mobile list fallback appears, protecting against cache/resize cases.

## Regression notes
- Legacy `[vms_venue_calendar]` remains month-oriented for existing internal/legacy usage.
- Canonical `[vms_public_calendar]` is the public-facing shortcode targeted by this fix.
