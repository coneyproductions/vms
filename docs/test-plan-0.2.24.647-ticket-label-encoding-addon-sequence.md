# VMS 0.2.24.647 Test Plan — Ticket Label Encoding + Add-on Sequence Warning

## Scope
- Ticketing v2 admin labels should preserve plain-text comparison characters such as `<12yo`.
- Existing encoded values such as `&lt;12yo` should display and sync as `<12yo` after the config is saved/applied again.
- Real HTML tags in labels should still be stripped by server-side sanitization.
- Preview should warn when enabled numbered add-ons appear to skip an item, such as Fire Table #05 between #04 and #06.

## Smoke test
1. Upload/activate VMS 0.2.24.647.
2. Open an Event Plan with Ticketing v2 enabled.
3. Apply the ticket template containing `Children's Admission (<12yo)`.
4. Confirm the ticket editor row displays `Children's Admission (<12yo)`, not `Children's Admission (&lt;12yo)`.
5. Click **Save config**.
6. Reload the Event Plan.
7. Confirm the same ticket label still displays with the literal `<12yo` text.

## Preview / Commit safety test
1. Click **Preview sync**.
2. Confirm the preview action for the child ticket says `Children's Admission (<12yo)`.
3. Confirm no existing mapped products are shown as delete/recreate actions.
4. If enabled add-ons include Fire Table #01, #02, #03, #04, and #06 but no #05, confirm Preview shows a non-blocking warning that the enabled add-on labels appear to skip Fire Table #05.
5. Do not Commit until the missing add-on is either intentionally accepted or corrected in the saved config/template.

## HTML-stripping regression
1. Temporarily add a test ticket title like `<strong>VIP</strong> Test` or `<script>alert(1)</script> Test`.
2. Save config.
3. Confirm real tags are stripped from the stored/displayed label and no executable HTML renders in the admin UI.
4. Remove the temporary test row before using the event publicly.

## Syntax checks
- `php -l vms/includes/integrations/ticketing-phase-b.php`
- `node --check vms/assets/admin-ticketing.js`
