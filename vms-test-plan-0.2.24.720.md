# VMS 0.2.24.720 Test Plan — TEC Schema Consolidation

## Scope

This build keeps the compact Event Details shortcode/sidebar card from 0.2.24.719 and changes the Google schema strategy:

- TEC remains the base Event JSON-LD provider.
- VMS enriches TEC's `tribe_json_ld_event_object` output instead of printing a duplicate full Event object by default.
- VMS fallback JSON-LD remains available through the existing `vms_event_details_print_json_ld` filter when TEC schema filters are unavailable or explicitly requested.

## Expected visible behavior

1. Single TEC event pages should still render the sidebar shortcode:
   ```text
   [vms_plan_your_visit layout="sidebar"]
   ```
2. The shortcode should show compact Event Details: Date & Time, Location, Tickets, Questions.
3. VMS should not auto-render a second large details card in the main content unless `vms_event_details_auto_render_card` is filtered true.
4. Existing CSS/snippet-based TEC details suppression remains outside this build.

## Expected schema behavior

On a production-like TEC event with tickets:

1. Page source should contain TEC's normal `application/ld+json` Event block.
2. Page source should not contain a second VMS full Event block by default:
   ```html
   class="vms-event-json-ld"
   ```
3. TEC's Event object should be enriched/cleaned by VMS:
   - `organizer.@type` should be `Organization`.
   - Bogus string performer value `"Organization"` should be removed unless VMS has an actual performer name.
   - `offers` should be reduced to one public paid offer, when a paid public price is available.
   - Qualified/free `$0` offers should not be included in the public Google Event schema.
   - `validThrough` should no longer expire at midnight at the beginning of the event date; it should use the event end time when available.
   - A comment should appear before TEC JSON-LD on single events:
     ```html
     <!-- VMS Google schema: TEC enriched -->
     ```

## Local/staging smoke checks

1. Install package on local or staging.
2. Open one real upcoming event with paid + qualified/free tickets.
3. View source and search for `application/ld+json`.
4. Confirm only the TEC Event JSON-LD is present by default.
5. Confirm the offer list no longer includes multiple `$0` offers.
6. Confirm organizer is `Organization`, not `Person`.
7. Confirm event page layout and sidebar shortcode still look correct on desktop and mobile preview.
8. Check a cancelled event, if available, and verify the offer availability is `SoldOut` and event status remains cancelled.

## Rollback

Reinstall 0.2.24.719 if TEC schema enrichment causes unexpected JSON-LD output.
