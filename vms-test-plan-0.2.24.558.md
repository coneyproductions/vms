# VMS 0.2.24.558 Test Plan — Guest Pass Claim Guardrails

## Install
1. Install the zip over 0.2.24.557.
2. Confirm the VMS build stamp shows `0.2.24.558`.
3. Open **VMS → Guest Passes → Batches** once so the admissions table upgrade can run.

## Batch Creation
1. Create a source if needed.
2. Create a batch using:
   - Number of Claim Links: `10`
   - Admissions per Claimed Link: `4`
   - Total Admission Cap: leave `0`
   - Max Claims per Phone: `1`
   - Max Claims per Email: `1`
3. Preview and confirm the summary shows:
   - Claim Links: 10
   - Admissions per Claimed Link: 4
   - Total Admission Cap: 40
   - Max Claims per Phone / Email values
4. Commit and confirm 10 claim links are created.

## Claim Guardrails
1. Open one claim link and confirm the party-size field max is 4.
2. Claim it for 4 people.
3. Reopen the same claim link and confirm it cannot be claimed again.
4. Open a second claim link with the same phone/email and confirm limits block a second claim when max is 1.

## Admission Cap
1. Create a small test batch: 2 links, 4 admissions per link, total cap 5.
2. Claim the first link for 4 people.
3. Attempt to claim the second link for 4 people.
4. Confirm the system blocks it because only 1 admission remains.

## Regression
1. Confirm printable pass link still opens.
2. Confirm QR still appears on confirmation and printable pass pages.
3. Confirm existing paid ticket scanning is unchanged.
