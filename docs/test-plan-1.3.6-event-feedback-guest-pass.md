# BVM 1.3.6 Event Feedback Guest Pass Test Plan

Use staging or disposable synthetic fixtures only. Keep outbound mail blocked and
do not invoke test, manual, scheduled, or campaign sends.

## Recipient resolution

1. Verify paid-only feedback recipients and refund-aware quantities remain unchanged.
2. Verify a claimed Guest Pass with an active admission appears for its exact Event Plan.
3. Verify paid and Guest Pass addresses coexist and normalized duplicate addresses appear once.
4. Verify a multi-admission claim contributes its admission count but only one recipient.
5. Verify missing/invalid email, non-claimed token, canceled admission, and wrong-event records are excluded.
6. Verify zero-value ticket orders remain eligible and appear under Other Complimentary guests.

## Preview and safety

1. Confirm post-event preview counts for Paid Tickets, Guest Passes, and Other Complimentary guests.
2. Confirm other follow-up templates do not add Guest Pass recipients.
3. Confirm no survey mail is sent and no MailPoet subscriber or tag is created.
4. Confirm existing duplicate-send and manual recipient-selection controls remain unchanged.

## Cleanup and rollback

Remove any synthetic fixtures and verify their absence. If acceptance fails, restore
the captured staging BVM 1.3.5 runtime and database backups, then clear caches. Do
not deploy to production without separate approval.
