# VMS 0.2.24.572 Test Plan - Public Calendar Destination Setting

🚨 **Repair/versioning protocol:** If Codex makes even a minimal code repair while testing this build, Codex must update all relevant version markers and packaging docs in the same pass before returning a replacement zip. At minimum this includes the plugin header version if present, `VMS_VERSION`, `vms-build.txt`, changelog/revision notes, this test plan or follow-up test notes, Codex handoff notes, and the package filename. Do not return a modified build with stale versioning/docs.

## Build Under Test

- Package: `vms-0.2.24.572-public-calendar-destination-setting.zip`
- Baseline: `0.2.24.571-cancellation-notification-polish`
- Scope: add an operator-selectable public events/calendar destination and use it for cancelled-event public notices.

## Install / Version Checks

1. Install/replace VMS Core with `vms-0.2.24.572-public-calendar-destination-setting.zip`.
2. Confirm WordPress shows VMS version `0.2.24.572`.
3. Confirm `vms/vms-build.txt` reads `0.2.24.572`.
4. Confirm `vendor-management-system.php` header and `includes/core/registry/constants.php` define version `0.2.24.572`.

## Syntax / Smoke Checks

1. Run PHP lint on all VMS PHP files.
2. Activate VMS Core.
3. Open **VMS → Settings** and confirm the page loads without fatal errors or layout breakage.

## Settings UI

1. In **VMS → Settings → Calendar (Core) → Public Calendar**, confirm a **Customer-facing event calendar link** area appears.
2. Confirm the **WordPress page** dropdown includes published WordPress pages and an **Auto-detect** option.
3. Confirm the **Advanced custom URL override** field appears and accepts site-relative paths such as `/events-calendar`.
4. Confirm the settings screen shows a **Resolved link** preview after saving.

## Resolver Behavior

1. Leave both fields blank / Auto-detect.
2. Save settings.
3. Confirm the resolved link points to the stored VMS public calendar page if `vms_page_public_calendar` is configured and published.
4. If the stored option is missing, confirm Auto-detect can find the published `events-calendar` page or a published page containing `[vms_public_calendar]`.
5. If no VMS public calendar page can be detected, confirm the resolver falls back to the TEC events archive when TEC is active.
6. If TEC is unavailable, confirm the resolver falls back to the site home page.

## Selected Page Behavior

1. Select the WordPress page that hosts the VMS public calendar, such as `/events-calendar`.
2. Save settings.
3. Confirm the resolved link matches that page permalink.
4. Rename the page slug in WordPress.
5. Reopen settings and confirm the resolved link follows the selected page ID rather than preserving the old slug.

## Custom URL Override Behavior

1. Enter `/events-calendar` in **Advanced custom URL override** and save.
2. Confirm the resolved link becomes the current site URL plus `/events-calendar`.
3. Enter a full URL, such as an external events landing page, and save.
4. Confirm the resolved link uses the full URL.
5. Clear the custom URL and save.
6. Confirm the selected page or Auto-detect behavior resumes.

## Public Cancelled Event Banner

1. Open a TEC single event page linked to a cancelled VMS Event Plan.
2. Confirm the cancelled-event public banner still appears.
3. Confirm the **Browse upcoming events** link uses the selected/resolved VMS public calendar destination instead of forcing TEC `/events`.
4. If a rescheduled replacement event is present, confirm **View New Date** still links to the replacement date and **Browse upcoming events** still uses the new resolver.
5. Confirm ticket/RSVP suppression and other cancellation behavior from `0.2.24.571` still works.

## Regression Checks

1. Confirm VMS public calendar rendering still works with `[vms_public_calendar]`.
2. Confirm the public calendar settings for vendor lines, open slots, and hide-past behavior still save correctly.
3. Confirm existing cancellation notification email behavior from `0.2.24.571` still works and did not regress.
4. Confirm public calendar cancellation ribbons remain removed from the VMS calendar view as intended in `0.2.24.571`.