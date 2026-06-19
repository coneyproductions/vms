# CODEX HANDOFF - VMS Core 0.2.24.572 Public Calendar Destination Setting

## Build

- Plugin: `vms`
- Version: `0.2.24.572`
- Package target: `vms-0.2.24.572-public-calendar-destination-setting.zip`
- Baseline: `0.2.24.571-cancellation-notification-polish`

## What Changed

This pass adds a universal operator setting for the customer-facing events/calendar destination used by public cancellation notices and future public-facing VMS links.

1. **Settings UI**
   - Added a **Customer-facing event calendar link** area inside **VMS → Settings → Calendar (Core) → Public Calendar**.
   - Added a WordPress Page dropdown so operators can choose the public events/calendar page by page ID.
   - Added an advanced custom URL override for TEC archives, external calendars, or unusual landing pages.
   - Added a resolved-link preview so operators can see the active destination after saving.

2. **URL Resolver**
   - Added `vms_get_public_event_calendar_url()` in `includes/helpers.php`.
   - Resolver order:
     1. `vms_settings[public_calendar_custom_url]`
     2. `vms_settings[public_calendar_page_id]`
     3. `vms_page_public_calendar`
     4. auto-detected `events-calendar` / `[vms_public_calendar]` page
     5. TEC archive via `tribe_get_events_link()`
     6. site home page
   - Added `vms_public_event_calendar_url` filter for future add-ons/operators.

3. **Cancelled Event Public Banner**
   - Updated `vms_tec_prepend_cancelled_notice()` so **Browse upcoming events** uses the new resolver instead of hardcoding TEC's `/events` archive.
   - Operators who prefer TEC can still leave Auto-detect active or enter their TEC archive/custom URL.

## Files Changed

- `vendor-management-system.php`
- `vms-build.txt`
- `includes/core/registry/constants.php`
- `includes/helpers.php`
- `includes/admin/settings-page.php`
- `includes/integrations/ticketing-rules-v2.php`
- `docs/05-revision-log.md`
- `docs/test-plan-0.2.24.572-public-calendar-destination-setting.md`
- `docs/CODEX-HANDOFF-0.2.24.572.md`
- `vms-test-plan-0.2.24.572.md`

## Guardrails Preserved

- No ticket/refund/cancellation job orchestration was changed.
- No cancellation email recipient behavior was changed.
- No public calendar shortcode rendering logic was changed.
- No TEC publishing, ticket sync, or stock logic was changed.
- The setting stores a WordPress page ID where possible so slug changes do not break the customer-facing link.
- Custom URL remains an explicit advanced override, not the primary/default UX.

## Testing Focus

Run `docs/test-plan-0.2.24.572-public-calendar-destination-setting.md`.

Pay special attention to:

- VMS → Settings page rendering.
- Saving the new page dropdown.
- Saving and clearing the advanced custom URL.
- The resolved-link preview.
- The cancelled-event public banner's **Browse upcoming events** link.
- Auto-detect behavior with and without a VMS public calendar page.
- TEC fallback behavior for operators who still want TEC as their public calendar.

## Repair / Versioning Protocol

🚨 If Codex makes even a minimal code repair during testing, update all relevant version markers and packaging docs before returning a replacement zip. At minimum:

- plugin header version if present
- `VMS_VERSION`
- `vms-build.txt`
- revision/changelog/build notes
- test plan or follow-up test notes
- Codex handoff notes
- package filename

Do not return a modified build with stale versioning/docs.