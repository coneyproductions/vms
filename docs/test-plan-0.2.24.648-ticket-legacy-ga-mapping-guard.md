# VMS 0.2.24.648 Test Plan — Ticket Legacy GA Mapping Guard

## Purpose

Protect existing ticket sales when applying a new Ticketing v2 template that adds an Early General Admission row before the existing General Admission row.

## Changes Under Test

- Legacy single-GA product mapping should only attach to the actual General Admission row.
- A newly inserted Early General Admission row must not inherit the existing GA Woo/TEC product merely because it is first in the template.
- Preview/Commit must block if two ticket rows try to control the same Woo product ID.
- Existing 0.2.24.647 fixes remain in place:
  - Plain-text labels such as `(<12yo)` render/save as entered instead of `(&lt;12yo)`.
  - Numbered enabled add-ons warn when a sequence item appears missing, such as Fire Table #05.

## Staging Regression Steps

1. Install/update to VMS 0.2.24.648.
2. Open an Event Plan that already has a committed General Admission ticket product.
3. Apply a ticket template that contains both:
   - Early General Admission
   - General Admission
4. Save config and run **Preview sync**.
5. Confirm Early General Admission does **not** preview against the same product ID as General Admission.
   - Expected when Early GA is enabled and has no existing product: `CREATE "Early General Admission"`.
   - Expected when Early GA is disabled and has no existing product: `SKIP "Early General Admission" — Ticket is disabled and has no mapped product to unpublish.`
6. Confirm General Admission still maps to the existing GA product and previews as UPDATE/SKIP as appropriate.
7. Confirm no preview line shows both Early General Admission and General Admission using the same Woo product ID.
8. If a deliberate duplicate product mapping is created, confirm Preview shows a blocked issue and Commit is not allowed.
9. Confirm `Children's Admission (<12yo)` displays as entered in Preview.
10. Confirm the Fire Table #05 sequence warning appears only when enabled Fire Table labels skip #05.

## Commit Safety Verification

Only run **Commit sync** after Preview shows:

- No duplicate Woo product ID shared by different ticket rows.
- Existing sold tickets are UPDATE/SKIP, not CREATE replacements.
- Disabled ticket rows do not unpublish a product that another enabled row still controls.

## Rollback

If anything unexpected appears in Preview, do not commit. Reinstall the previous known-good plugin zip and re-preview before trying again.
