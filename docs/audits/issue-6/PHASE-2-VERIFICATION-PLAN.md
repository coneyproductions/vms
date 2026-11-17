# Phase 2 verification plan

Status for review · Execute only after separate authorization

## Baseline and containment

Use the captured .1411 subtree and accepted ZIP as the reproducible reference. Start by validating the installed-capture, Git-subtree and ZIP identities against the 432-file manifest. Use existing disposable Local WordPress fixtures, isolated PHP tests, intercepted browser tests and recorded acceptance. Keep code and configuration outside active worktrees. Pin the companion versions and test only the affected interface when an existing accepted artifact supplies the rest of the composition.

Default to synthetic identities and an offline application fixture with outgoing mail, provider requests and asynchronous side effects intercepted. Assert zero outbound activity for the payment, refund, ticket and email cases. A test that cannot demonstrate containment is deferred. This plan has no hosted installation, live-data replay, purchase, refund, message or real ticket operation.

## Prioritized work

| Order | Scope | Minimum verification and decision |
| --- | --- | --- |
| 1 · P0 | Financial integrity and ownership (#84, #119, #124, #133) | Use the existing isolated Square and checkout harnesses with provider calls stubbed. Check ordinary and discounted line totals, paid-state monotonicity, repeated failed/cancelled attempts and duplicate prevention. Exercise individual and cancellation refunds only via synthetic recorded responses. Reuse .6 checkout hashes and the original #84 acceptance where the owner and input match. Route a refund discrepancy to the existing #133 workstream. |
| 2 · P0 | Ticketing, rentals and publishing (#5, #7, #8, #88, #124) | Reuse the exact R23/R24 hashes and historical dispatch receipts. Run one interrupted CREATE/retry and one outbox-only deferred publish replay on synthetic Local plans. Check that retries preserve exactly one mapping and product. Render Safe Mode and Progressive on desktop/mobile with empty amenities, restricted tickets, and rental pointer/quantity behavior. Intercept cart submission and compare only the expected payload. |
| 3 · P1 | Vendor reporting (#10, #143) | Reuse the captured .1411 failing normalization and the candidate’s passing isolated normalization. Test the exact #143 artifact only under the existing #143 scope if it advances. Follow one synthetic provider payload through both vendor cards and compare category sum, paid labels, authorization and fallback. Review the historical paid-split variance separately with existing captured evidence and synthetic classification fixtures. Confirm which numbers are sales, admission or payout inputs before recording a financial regression. |
| 4 · P1 | Event Plans and reservations (#6, #51, #91, #102, #135) | Run the recovered print and identity/count fixture with synthetic admissions and rentals. Verify both launchers and authorized/denied access in the disposable browser. Check new-plan health separately from actual published-plan alerts. Verify a staffing-only save and a descriptive or selector-only ticket configuration save through reload, with no TEC publication. Record #51 as unintegrated unless an accepted promotion is recovered. |
| 5 · P1 | Staff Portal and show-night interfaces (#38, #41, #53, #56, #58, #104, #106, #137) | Reuse matching #104 files and the 17 passing portal assertions. Limit additional cases to ordinary staff, denied role, committed/draft/scheduled assignments, relative shifts crossing midnight, doc access and Ops/Rentals entry points. Use existing physical-device receipts for the same composition. Treat an untested interface or device window explicitly as unverified. Keep any email capture in the Local fixture. |
| 6 · P1/P2 | Remaining accepted boundaries (#9, #42, #48, #60, #63, #80, #81, #96, #105, #107, #134, #140) | Target changed or unproven seams. Check ticket grant grandfathering and one-ticket defaults, Square reporting names with synthetic mappings, Guest Pass/Feedback provider handoffs, scheduling and sender eligibility, contract view-state/materiality, pending video review and availability persistence. Reuse exact #140 artifact acceptance where bytes and scope match. Check other matrix rows only where evidence indicates drift, a missing contract, or an active dependency. |

## Stop and review

- Cap the first pass to the cases above. Log a disposition for each case as expected pass, reproduced failure, evidence reuse or unverified, with the exact input, environment identity, and fixture or prior receipt.
- Stop the affected case for a source mismatch, an unexpected write or outbound request, a breached permission boundary, or a financial, publication, or data-integrity failure. Preserve the evidence and send the affected workstream the minimal reproduction using synthetic data.
- Keep #143, #133, #33, #66 and #106 independent. For the November 21 show, use existing Ops and Express Bar acceptance records and escalate only a material readiness finding. This plan makes no change to the working show stack.
- Deliver the bounded verification findings for review before repairs, integration, consolidated packaging, or any environment action. Record release-closure evidence only when the requirements in the Phase 1 report are met.

[Phase 1 findings](PHASE-1-REPORT.md) · [Issue matrix](RECONCILIATION-MATRIX.md) · [Source preservation proposal](SOURCE-PRESERVATION-PROPOSAL.md)
