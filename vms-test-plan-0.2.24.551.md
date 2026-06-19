# VMS Test Plan — 0.2.24.551 Universal Admission QR Foundation

## What changed

- Renamed Passes UI labels to Guest Passes while preserving existing slugs/database names.
- Fixed Guest Pass batch Preview → Commit safety so Commit uses the exact previewed payload.
- Repopulates the Guest Pass batch form after Preview.
- Adds native VMS admission scan tokens to admission entries.
- Adds a VMS admissions scan REST endpoint.
- Updates the door check-in field to support scanning a VMS admission URL/token or searching by name/phone/email.
- Guest Pass claims now generate a VMS admission scan URL and email it when the claimant provides an email.

## Critical manual test list

1. Go to VMS → Guest Passes → Sources and confirm existing sources still show.
2. Create or use an existing source.
3. Go to Batches and enter a batch with non-default values.
4. Click Preview.
5. Confirm the form fields remain populated exactly as entered.
6. Confirm the Preview Samples area shows a summary of the exact data Commit will use.
7. Click Commit + Generate Guest Passes.
8. Confirm generated passes match the previewed values.
9. Claim one Guest Pass with an email address.
10. Confirm the claim success screen shows a Gate Pass URL.
11. Confirm the claimant receives the email if site mail is working.
12. Open the door check-in page, select the event, paste/scan the Gate Pass URL into the search field, and click Scan / Check In.
13. Confirm the admission checks in once and shows Already checked in on repeat scan.
14. Add a manual guest list entry and confirm it appears in the same door check-in screen and can be searched by name/phone.

## Notes

- This pass adds the VMS-native QR/token foundation. Actual QR image rendering can be layered on top; the scannable URL/token is now present and accepted by the VMS gate scanner.
- Existing TEC/Woo paid-ticket scanning behavior is not removed or changed by this pass.
- Existing vendor guest TEC bridge behavior is not removed in this pass; VMS native admission tokens are now added alongside it for unification groundwork.
