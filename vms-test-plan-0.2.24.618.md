# VMS 0.2.24.618 Test Plan — Public Calendar Mobile/Tablet List View

## Purpose
Expand the 0.2.24.617 mobile calendar fix to tablets, including iPad portrait and landscape, so customers see event cards immediately instead of scrolling through blank weekday/month-grid space.

## Files touched
- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `includes/public/venue-calendar-shortcode.php`
- `assets/js/vms-public-calendar.js`
- `assets/css/vms-ui.css`
- `vms-build.txt`

## Expected behavior
1. On phones, the public calendar defaults to List view.
2. On tablets/iPads, including landscape, the public calendar presents the larger card/list layout instead of the month grid.
3. The View dropdown is hidden on phone/tablet breakpoints so customers are not offered the blank month grid.
4. If cached desktop/month HTML is served to a phone/tablet, CSS hides the month grid and shows the list fallback.
5. Submitting the month filter from a phone/tablet preserves `view=list`.
6. Desktop calendar behavior remains unchanged.

## Manual checks
- Visit the public calendar on desktop width above 1180px and confirm Month view remains available.
- Resize below 1180px and confirm the month grid disappears and event cards appear immediately.
- Test iPad portrait and landscape; the first event card should be discoverable without scrolling through blank weekday placeholders.
- Change the Month field and submit; confirm the resulting view is still List/card view on tablet.
- Confirm previous/next month links still work.

## Packaging checks
- PHP syntax lint the plugin files.
- JS syntax check `assets/js/vms-public-calendar.js`.
- Confirm plugin header, `VMS_VERSION`, and `vms-build.txt` all read `0.2.24.618`.
- Confirm zip installs into the canonical `vms/` plugin folder.
