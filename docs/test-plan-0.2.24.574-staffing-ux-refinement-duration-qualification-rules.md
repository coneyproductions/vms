# VMS 0.2.24.574 Test Plan - Staffing UX Refinement, Duration Logic, and Qualification Rule Polish

🚨 **Repair/versioning protocol:** If Codex makes even a minimal code repair while testing this build, Codex must update all relevant version markers and packaging docs in the same pass before returning a replacement zip. At minimum this includes the plugin header version if present, `VMS_VERSION`, `vms-build.txt`, changelog/revision notes, this test plan or follow-up test notes, Codex handoff notes, and the package filename. Do not return a modified build with stale versioning/docs.

## Build Under Test

- Package: `vms-0.2.24.574-staffing-ux-refinement-duration-qualification-rules.zip`
- Baseline: `0.2.24.572-public-calendar-destination-setting`
- Scope: make staffing templates usable from normal Event Plans, add attendance-band template recommendation, expose relative staffing timing properly, and add staff qualification checks.

## Install / Version Checks

1. Install/replace VMS Core with `vms-0.2.24.574-staffing-ux-refinement-duration-qualification-rules.zip`.
2. Confirm WordPress shows VMS version `0.2.24.574`.
3. Confirm `vms/vms-build.txt` reads `0.2.24.574`.
4. Confirm `vendor-management-system.php` header and `includes/core/registry/constants.php` define version `0.2.24.574`.

## Syntax / Smoke Checks

1. Run PHP lint on all VMS PHP files.
2. Activate VMS Core.
3. Open **VMS → Staffing** and confirm the Staff Roles / Staffing Templates screen loads without fatal errors.
4. Open a **Staff** post and confirm the new **Qualifications / Licenses** box renders.
5. Open an existing **Event Plan → Staff** section and confirm the staffing template card renders without layout breakage.

## Migration Checks

1. Update from a pre-`0.2.24.574` build.
2. Confirm activation/update completes without database errors.
3. Create or edit a staffing template and confirm attendance band values can save.
4. Confirm older templates with blank attendance band values still load normally.

## Staff Role Qualification Rules

1. In **Staff Roles**, edit a role such as Bartender.
2. Enter one or more required qualifications, for example `TABC Certified`.
3. Test each **Qualification check behavior** mode: Warn only, Soft block with warning, Hard block invalid assignments.
4. Save and reopen the role to confirm values persist.

## Staff Profile Qualifications

1. Open a staff profile.
2. Add one or more qualification rows with authority, issue/expiration dates, and status.
3. Save and reopen the profile.
4. Confirm all rows persist and blank rows are ignored.
5. Set one qualification to an expired date and confirm it is treated as expired when assigning staff later.

## Staffing Template Editor

1. Create or edit a staffing template.
2. Confirm the template shows **Attendance band** Min / Max inputs.
3. Add a slot using **Time mode = Relative**.
4. Confirm the template row exposes start anchor, start offset, end anchor, end offset, and duration.
5. Save and reopen the template.
6. Confirm attendance band and relative timing values persist.

## Event Plan Manual Template Apply

1. Open an Event Plan created outside the Schedule calendar flow.
2. In **Staff**, confirm a **Staffing template** card appears with:
   - applied template summary
   - recommended template summary
   - template dropdown
   - apply mode selector
   - apply button
3. Choose a template and use **Merge missing roles only**.
4. Save/update the Event Plan.
5. Confirm missing staffing roles/slots are added without wiping existing roles already on the event.
6. Repeat with **Replace staffing from template** and confirm the event's staffing rows are replaced.

## Generic Auto-Seed Behavior

1. Create a new Event Plan through a non-Schedule path.
2. Set venue/date/event type so a template can match.
3. Save the event with no staffing roles yet.
4. Confirm a matching template is auto-seeded even though the event was not created from the Schedule create flow.
5. Confirm saving an event that already has staffing rows does not silently wipe/reseed those rows.

## Attendance Band Recommendation / Alerts

1. Create at least two staffing templates for the same venue/day/type with different attendance bands, such as `0-74` and `75-149`.
2. Open an Event Plan and confirm the template dropdown labels include the attendance band.
3. With current wired headcount below the higher band, confirm the smaller template is recommended.
4. Increase wired headcount so the event crosses into the next band.
5. Confirm the Event Plan staffing card now recommends the higher template.
6. Confirm the top staffing alert area flags when:
   - the applied template is above/below its attendance band
   - another template is recommended now
   - the next staffing threshold is within 10 headcount

## Relative Timing Persistence on Event Plans

1. On an Event Plan staff role row, switch to **Relative** time mode.
2. Set anchors/offsets/duration and save.
3. Reopen the Event Plan and confirm those values persist.
4. Switch back to **Absolute** and confirm start/end fields still save correctly.
5. Confirm older absolute-only staffing rows still render normally.

## Qualification Assignment Behavior

1. Assign a staff member who does **not** meet a role's required qualification.
2. In **Warn only** mode, confirm the assignment can remain checked but a warning notice appears after save.
3. In **Hard block invalid assignments** mode, confirm the invalid assignment is removed/blocked and an admin notice explains why.
4. Assign a qualified staff member and confirm the role shows a positive qualification badge.
5. Confirm staff lacking qualification for a hard-blocked role appear disabled in the picker UI.

## Regression Checks

1. Confirm the existing staff role activation threshold warnings/highlights still update as headcount changes.
2. Confirm staffing rollups still refresh after staffing edits/template apply.
3. Confirm existing staffing assignments, pay types/rates, and absolute shift times still save correctly.
4. Confirm no unrelated Event Plan save sections regress when saving staffing changes.

## Known Scope Boundary

- This build adds current-headcount staffing alerts and template recommendation changes, but it does **not** yet add true pace-based predictive staffing forecasts.

## Staffing UX wording

1. Open an Event Plan → Staff section.
2. Confirm the role controls now read **Staff needed** and **Activate at attendance**.
3. Confirm the state pill / helper copy uses **attendance** wording instead of the older repeated headcount wording.

## Duration behavior on Event Plans

1. On an Event Plan role, set **Time mode = Absolute**.
2. Enter **Shift start** and **Duration**, but leave **Shift end** blank.
3. Save. Confirm there is no false validation warning requiring Shift end.
4. Reopen the Event Plan and confirm the role still resolves/save-loads correctly.
5. Repeat with **Time mode = Relative** using Start anchor/offset plus **Duration** while leaving the relative end fields hidden/unused.
6. Confirm Duration hides the current-mode end fields while populated, and clearing Duration brings those end fields back.

## Staff profile qualification layout

1. Open a Staff profile.
2. Confirm each qualification row renders as a clearer multi-row card layout rather than one long horizontal strip.
3. Add at least two qualifications and verify the layout remains readable on a normal desktop width.

## Role qualification rules

1. Open **Staff Roles** and edit a role.
2. Add at least two qualification requirements with different enforcement levels, for example:
   - `TABC Certified` → Hard block
   - `Internal bar training` → Warn only
3. Save and reopen the role.
4. Confirm each rule persists with its own enforcement mode.
5. On an Event Plan, assign a staff member missing only the warn-only requirement and confirm it warns but is not hard blocked.
6. Assign a staff member missing the hard-block qualification and confirm the assignment is blocked.
