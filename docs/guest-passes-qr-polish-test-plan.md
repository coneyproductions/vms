# Guest Passes QR Polish Test Plan

## Tests

1. Create a Guest Pass batch for Any Event. Confirm preview preserves all values.
2. Claim a pass and confirm the event dropdown only shows published upcoming events, not past events.
3. Enter an email while claiming. Confirm the confirmation page shows a QR-style gate pass and email copy note.
4. Confirm the public pass page uses the WordPress theme header/footer.
5. In VMS > Guest Passes, confirm claimed passes show View Pass and Resend Email when an email exists.
6. Confirm unclaimed passes can still be voided/restored.
7. Confirm the pass can still be found by the door/check-in flow by URL/token or by name/phone search.

## Notes

- Editing claimed pass details is intentionally not included in this pass. Use void/reissue or a future controlled correction workflow with audit trail.
