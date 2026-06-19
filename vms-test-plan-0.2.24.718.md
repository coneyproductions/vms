# VMS 0.2.24.718 Test Plan — Event Details compact/sidebar-ready polish

## Scope

This build follows 0.2.24.716 and reduces the visible Event Details section prominence while keeping the Google-readable Event JSON-LD foundation.

## Expected behavior

- TEC single-event pages still print one VMS Event JSON-LD block in the document head.
- Auto-rendered Event Details section is visually quieter:
  - one modest `Event Details` heading,
  - no eyebrow/casual heading,
  - no CTA button row,
  - no four large inner cards,
  - compact list rows for Date & Time, Location, Tickets, and Questions.
- Shortcode placement remains available:
  - `[vms_plan_your_visit]`
  - `[vms_plan_your_visit layout="sidebar"]`
  - `[vms_plan_your_visit layout="inline"]`
- The shortcode default layout is sidebar-ready for use under the Meet the Band / Meet the Food Truck cards.
- If shortcode placement renders first, the existing once-per-event guard should prevent a duplicate auto-render later on the page.
- Filters are available for the next placement pass:
  - `vms_event_details_auto_render_card`
  - `vms_event_details_auto_render_layout`

## Staging checks

1. Install on staging only.
2. Open a normal upcoming TEC event page.
3. Confirm Event Details is quieter and no longer visually competes with tickets.
4. Confirm the section appears below the ticket area when auto-rendered.
5. Test shortcode placement in the narrow/sidebar column if the page builder/template allows it.
6. Confirm no duplicate Event Details section appears if shortcode placement is used.
7. Open page source and confirm one `.vms-event-json-ld` script exists.
8. Validate the event URL in Google's Rich Results Test.
9. Test a cancelled event and confirm cancelled schema/status behavior is preserved.

## Notes

This does not hard-code a theme-specific sidebar insertion point because the current package does not include the active staging/production theme template. The intended final placement remains: desktop sidebar under vendor cards, mobile below tickets/content.

## 0.2.24.718 follow-up
- Verify `[vms_plan_your_visit layout="sidebar"]` renders when placed in a real event-page sidebar/widget area.
- Verify the shortcode can resolve the current TEC event through queried object context even when rendered outside the main loop.
- Verify the shortcode still supports explicit placement with `event_id`, `id`, or `event` attributes.
- Confirm fallback inline rendering still appears where no working sidebar placement exists.
