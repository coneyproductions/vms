# VMS 0.2.24.715 Test Plan — Public Event Details + Google Event Schema

## Purpose
Restore Google- and guest-readable event facts on TEC single-event pages without bringing back the unattractive default TEC details block.

This build adds:

- A new public `Plan Your Visit` card rendered after the TEC event meta area.
- A `[vms_plan_your_visit]` shortcode for manual placement if the theme/sidebar needs it later.
- VMS-generated `Event` JSON-LD on TEC single-event pages.
- A scoped public stylesheet for the card.

## Files Changed

- `includes/public/load.php`
- `includes/public/event-details.php`
- `assets/css/vms-event-details.css`
- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`

## Manual Smoke

### 1. Plugin/package sanity

- Install/update the package on local or staging.
- Confirm VMS reports version `0.2.24.715`.
- Confirm the plugin activates without fatal errors.

### 2. Event page public render

Open a published TEC event linked to a VMS Event Plan.

Expected:

- A `Plan Your Visit` card appears near the bottom of the event page, after the TEC meta position.
- The card shows:
  - Date and time
  - Gates-open time, defaulting to one hour before show start
  - Venue name/address
  - Ticket summary
  - Good-to-know copy
  - Get Directions / Add to Calendar / Buy Tickets buttons when data is available
- Mobile stacks the details into one column and does not interrupt the main ticket selection area.

### 3. Cancelled event behavior

Open a cancelled event linked to a cancelled VMS Event Plan.

Expected:

- Existing VMS cancelled-event banner behavior remains unchanged.
- The `Plan Your Visit` card uses cancelled styling.
- Ticket text says ticket sales are closed.
- The Buy Tickets button is not shown.
- JSON-LD uses `https://schema.org/EventCancelled`.

### 4. JSON-LD validation

View page source on a TEC single-event page.

Expected:

- One VMS JSON-LD script exists with class `vms-event-json-ld`.
- The schema contains:
  - `@type: Event`
  - `name`
  - `url`
  - `startDate`
  - `endDate`
  - `eventStatus`
  - `eventAttendanceMode`
  - `location.name`
  - `location.address`
  - `organizer.name = Serenade Range`
  - `image`, when the event has a featured image
  - `offers`, when a ticket price is detected

Recommended external check after install:

- Run the event URL through Google Rich Results Test and Schema Markup Validator.

### 5. Shortcode fallback

Add `[vms_plan_your_visit]` to a temporary test location on a TEC event page.

Expected:

- The card renders for the current event.
- No duplicate card appears from the shortcode itself.

## Notes / Known Limits

- This does not change the theme-level hiding of the native TEC details block, if that hiding lives outside VMS.
- Desktop sidebar placement is not forced in this build because the active two-column event layout appears theme/template-controlled. The shortcode gives us a safe manual placement hook if we decide to move it into the narrow column later.
- Google Business Profile posting and Google Ads campaign creation are not included in this build.
