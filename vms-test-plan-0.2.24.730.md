# VMS 0.2.24.730 Test Plan — Event Feedback Public Questionnaire Update

## Pre-checks

1. Activate VMS `0.2.24.730`.
2. Confirm version markers:
   - plugin header shows `0.2.24.730`
   - `VMS_VERSION` is `0.2.24.730`
   - `vms/vms-build.txt` begins with `0.2.24.730`
3. Confirm three local feedback fixtures are available:
   - one event with no secondary vendors
   - one event with one secondary vendor
   - one event with two secondary vendors
4. Confirm an existing feedback invite URL generated before this patch still loads.

## No-vendor event

1. Open a feedback link for an event with no secondary vendors.
2. Confirm the page loads successfully.
3. Confirm the website section appears before any vendor sections.
4. Confirm no secondary-vendor blocks appear.

## Website section conditional behavior

1. Open the feedback form and leave `Did you use our website...` set to `No, I did not use the website`.
2. Confirm all website follow-up fields stay hidden.
3. Submit the form.
4. Confirm submission succeeds without website-detail validation errors.

1. Re-open a fresh feedback form and choose `Yes, I bought tickets online`.
2. Confirm the website detail block appears immediately.
3. Fill the website ratings plus `No issues`.
4. Submit the form and confirm success.

1. Re-open a fresh feedback form and choose `I tried, but had an issue`.
2. Confirm the website detail block appears.
3. Choose a payment issue option and add website comments.
4. Change the selector back to `No, I did not use the website`.
5. Confirm the detail block hides again.
6. Submit and confirm the hidden website fields are not required and are not stored as active detail responses.

## One-secondary-vendor event

1. Open a feedback link for an event with one secondary vendor.
2. Confirm the vendor block shows the vendor name and `Did you order from them?`.
3. Confirm the detailed vendor questions are hidden by default.
4. Choose `No`.
5. Confirm the detailed vendor block stays hidden.
6. Submit and confirm success.

1. Re-open a fresh feedback form for the same event.
2. Choose `Yes`.
3. Confirm the detailed vendor block expands immediately.
4. Fill the ratings, bring-back answer, and comment.
5. Submit and confirm success.

## Two-secondary-vendor event

1. Open a feedback link for an event with two secondary vendors.
2. Set Truck A to `Yes` and complete the detail fields.
3. Set Truck B to `No`.
4. Confirm Truck B detail fields remain hidden the entire time.
5. Submit and confirm success.

## Admin/reporting regression

1. Open the Event Feedback admin summary for the tested event.
2. Confirm website usage counts appear.
3. Confirm website issue counts appear.
4. Confirm website rating averages only use respondents who did not answer `No, I did not use the website`.
5. Confirm each secondary vendor now shows `Did you order from them?` counts.
6. Confirm detailed secondary-vendor averages count only responses where `Did you order from them? = Yes`.
7. Confirm blank hidden fields are not treated as low or negative ratings.

## Backward compatibility

1. Open an older Event Feedback response created before `0.2.24.730`.
2. Confirm the admin detail view still renders without warnings or missing-structure errors.
3. Confirm older vendor detail rows still render sensibly even if they do not include the new `did_order` choice value.
