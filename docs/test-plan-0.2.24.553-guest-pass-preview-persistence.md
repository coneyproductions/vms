# VMS Test Plan — 0.2.24.553 Guest Pass Preview Persistence Hotfix

1. Install/activate VMS 0.2.24.553.
2. Open VMS → Guest Passes → Batches.
3. Fill every Create Batch field, then intentionally leave Single Event blank while Validity Type is Single Event.
4. Click Preview. Confirm the validation notice appears and all entered values remain in the form.
5. Select a published Event Plan, then click Preview again. Confirm the Preview Samples section appears and the form values remain populated.
6. Click Commit + Generate Guest Passes. Confirm the generated batch uses the previewed values exactly.
