# VMS 0.2.24.719 Test Plan — Event Details Sidebar-Only Default

## Purpose

Keep the new VMS Event Details card focused on the preferred sidebar/widget placement while leaving existing TEC details suppression/relocation logic untouched. Preserve the VMS-generated Event JSON-LD for Google event readability.

## Scope

- Visible Event Details card remains available through shortcode/widget placement.
- Automatic below-content fallback is disabled by default.
- Existing TEC Details output is not suppressed, moved, or styled by this release.
- VMS Event JSON-LD remains printed on single TEC event pages unless filtered off.

## Files changed

- `includes/public/event-details.php`
- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `vms-test-plan-0.2.24.719.md`

## Local/staging checks

1. Install/update package on local or staging.
2. Confirm version shows `0.2.24.719`.
3. Open a TEC single-event page with no Event Details shortcode in the selected sidebar.
   - Expected: no VMS Event Details card auto-renders below content/meta.
   - Existing TEC Details behavior should be unchanged.
4. Add this shortcode to the event page sidebar/widget area:
   - `[vms_plan_your_visit layout="sidebar"]`
5. Open a TEC single-event page using the sidebar layout.
   - Expected: compact Event Details card appears in the sidebar.
   - Expected fields: Date & Time, Location, Tickets, Questions.
   - Expected links: Get directions, View common questions.
6. Check mobile preview.
   - Expected: sidebar/card placement follows the theme's sidebar behavior and does not interrupt the ticket selection path.
7. View page source and find `class="vms-event-json-ld"`.
   - Expected: JSON-LD remains present.
   - Expected: no duplicate visible VMS Event Details card is created by default.
8. Test a cancelled event if available.
   - Expected: schema eventStatus uses EventCancelled.
   - Expected: visible card, if shortcode is present, uses cancelled state text/styling.

## Optional filters

- Restore auto fallback rendering if ever needed:
  - `add_filter( 'vms_event_details_auto_render_card', '__return_true' );`
- Change automatic fallback layout if enabled:
  - `add_filter( 'vms_event_details_auto_render_layout', fn() => 'sidebar' );`
- Change Questions URL:
  - `add_filter( 'vms_event_details_questions_url', fn() => home_url( '/questions/' ) );`

## Notes

This release intentionally does not alter TEC template/details output. Use the site's existing snippet/plugin for TEC Details suppression to avoid overlapping responsibilities.
