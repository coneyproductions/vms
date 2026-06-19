# CODEX HANDOFF - VMS Core 0.2.24.568 Final Payment Terms

## Package to test
`vms-0.2.24.568-final-payment-terms.zip`

## Install order
1. Install/replace VMS Core first.
2. If also testing Agreements, install `vms-agreements-0.3.4-final-payment-summary-cleanup.zip` after Core is active.

## Primary purpose
Verify Event Plan compensation now supports expected final payment timing and payment method, including `ACH / Direct Deposit`, and that those terms are saved, rendered, snapshotted, and included in compensation drift/hash behavior.

## Required test plan
Run:

- `docs/test-plan-0.2.24.568-final-payment-terms.md`
- `vms-test-plan-0.2.24.568.md`

Also perform a quick smoke check that no unrelated Event Plan, ticketing, vendor portal, cancellation, refund, or public calendar behavior changed.

## Failure protocol for Codex
If any test fails:

1. Stop and document the failing step, expected result, actual result, URL/screen, and any console/PHP/WP debug log errors.
2. Fix the smallest durable root cause. Do not bypass the test and do not hide failures with CSS or wording-only changes unless the issue is truly presentation-only.
3. Re-run the failed test and at least one adjacent regression test.
4. If any PHP/code files are edited, update all version/build markers in the same pass.
5. If only documentation is edited, do not bump the runtime plugin version unless a code behavior changed.
6. Package a complete replacement zip. Do not provide partial files.
7. Report exactly what changed, what was tested, what still could not be tested, and whether production install is recommended.

## Required version/build updates if Codex edits code
If Codex changes code, update at minimum:

- `vendor-management-system.php` plugin header `Version`
- `includes/core/registry/constants.php` `VMS_VERSION`
- `vms-build.txt`
- `docs/05-revision-log.md`
- `docs/01-project-handoff.md`
- `docs/06-test-plan.md` if the main test pipeline changes
- add or update the relevant build-specific test plan under `docs/`
- package filename

Use the next appropriate patch version, for example `0.2.24.569`, unless the maintainer gives a different target.

## Rollback rule
If the update causes a critical error or the site goes down, immediately roll back to the previous working VMS Core zip before further diagnosis.

## Success criteria
The build is acceptable only if:

- Final payment timing persists across save/reload.
- Final payment method persists across save/reload, including `ACH / Direct Deposit`.
- Locked Pay summary includes final payment timing/method.
- Compensation hash/drift detection changes when final payment terms change.
- Re-applying compensation packages does not wipe event-level deposit/final-payment terms unexpectedly.
- No unrelated ticketing, refund, cancellation, vendor portal, or public calendar regressions are observed.
