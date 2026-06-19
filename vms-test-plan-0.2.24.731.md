# VMS 0.2.24.731 Test Plan — Event Feedback Label Clarity Follow-Up

## Pre-checks

1. Activate VMS `0.2.24.731`.
2. Confirm version markers:
   - plugin header shows `0.2.24.731`
   - `VMS_VERSION` is `0.2.24.731`
   - `vms/vms-build.txt` begins with `0.2.24.731`
3. Confirm at least one valid local feedback link still loads.

## Public form clarity

1. Open a public Event Feedback link.
2. Confirm the Bar elaboration summary reads `Additional bar feedback`.
3. Confirm the Bar prompt reads `What stood out about the bar? Select anything that applies.`
4. Confirm the Bar options are the new sentiment-specific choices.

1. Confirm the Bathroom elaboration summary reads `Additional bathroom feedback`.
2. Confirm the Bathroom prompt reads `What stood out about the bathrooms? Select anything that applies.`
3. Confirm the Bathroom options are the new sentiment-specific choices.

## New-response storage and admin display

1. Submit a disposable response with mixed Bar selections such as:
   - `Fast service / short wait`
   - `Staff could have been friendlier`
   - `Pricing felt high`
2. Submit mixed Bathroom selections such as:
   - `Clean and well-kept`
   - `Supplies were low or missing`
   - `Long line / wait`
3. Confirm the response saves successfully.
4. Confirm the admin response view shows those same human-readable labels, not raw internal keys.

## Website display

1. Submit a response with `website_used = No, I did not use the website`.
2. Confirm the stored/admin website section shows only that usage answer and does not show hidden website fields as `--`.

1. Submit another response with website detail values.
2. Confirm the admin response view shows only the relevant/populated website detail rows.

## Vendor display follow-up

1. Submit a two-vendor response where:
   - Vendor A = `Yes` with detailed ratings
   - Vendor B = `No`
2. Confirm the admin response view:
   - shows Vendor A detailed ratings
   - shows Vendor B with the `Ordered?` answer
   - does not show Vendor B detail rows full of `--`
   - instead shows the skipped-status message

## Legacy backward compatibility

1. Open an older response created before `0.2.24.731`, or create a disposable legacy fixture that stores the old ambiguous bar/bathroom keys.
2. Confirm the admin response view renders without errors.
3. Confirm the old ambiguous values are shown as `Legacy selections: ...` instead of being mislabeled with the new sentiment-specific meanings.
4. Confirm the old comments still display.
