# WordPress.org Remediation Ledger

Update this ledger only from repository evidence. If evidence is incomplete or uncertain, use `audited` or `blocked` instead of guessing. Historical `PASS` sections, checklist labels, commit subjects, or conversation memory are not sufficient on their own to justify `verified`.

Allowed status values:

- `not started`
- `audited`
- `in progress`
- `implemented`
- `verified`
- `not applicable`
- `blocked`

Status semantics used in this ledger:

- `implemented`: code or test remediation was completed historically and repository evidence supports its existence, but it has not yet been freshly re-certified by the current final audit.
- `verified`: current-tree inspection plus all applicable focused verification commands were freshly run and passed in the current task.
- `audited`: the issue was investigated, but implementation or proof remains incomplete.
- `blocked`: the issue cannot safely proceed without a decision, missing source, or dependency.
- `not started`, `in progress`, and `not applicable` keep their ordinary plain-language meaning.

Original reviewer correspondence status:

- As of `2026-07-15`, the original WordPress.org review email or a faithful sanitized transcription was not found locally during the setup audit. Do not create `docs/wporg-review-source.md` until that source exists.

## Imported Evidence Baseline

| Issue ID | Family | Status | Current repository evidence | Focused tests on record | Verification state in this pass | Commit SHA on record | Remaining verification or follow-up | Most recent focused verification on record |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `WPORG-17B` | `A` | `implemented` | Historical evidence remains `docs/WPORG_PREREVIEW_REMEDIATION.md` `WPORG-17B Result`.<br>Current mirror no longer contains `assets/admin/addons/manifest-addons.json`, `includes/admin/addons/class-vms-admin-addons.php`, or `includes/admin/addons/class-vms-addons-licensing.php`.<br>This correction pass also found no `Premium Add-ons`, `Freemius`, `freemius`, or `license key` matches under `includes/` or `assets/`. | No dedicated current focused test file was located; historical result section only. | Spot-checked current mirror tree only. No fresh closeout audit was rerun, so historical PASS does not retain `verified`. | none imported | Fresh mirror/live re-audit and reviewer-source closeout are still required before submission. | `2026-07-10` |
| `WPORG-18A` | `J` | `implemented` | Historical evidence remains `docs/WPORG_PREREVIEW_REMEDIATION.md` `WPORG-18A Result`.<br>Current `includes/core/registry/admin-menu.php` still defines the compatibility shim `vms_i18n_runtime()` and current `includes/core/registry/statuses.php` uses literal gettext call sites instead of wrapper-mediated runtime strings. | `tests/runtime-stub-guards.php`<br>`tests/release-compatibility-harness.php`<br>`tests/public-release-build-pipeline.php` | This correction pass spot-checked the current wrapper and literal call sites, but did not rerun parser or extraction gates. | none imported | Fresh parser, extraction, and reviewer-source verification are still required before any final resubmission audit. | `2026-07-10` |
| `WPORG-18B` | `J` | `implemented` | Historical evidence remains `docs/WPORG_PREREVIEW_REMEDIATION.md` `WPORG-18B Result`.<br>Current repository still contains the named i18n harnesses and the registry files audited in `WPORG-18A`, but this pass did not rebuild the full translator-comment inventory from scratch. | `tests/runtime-stub-guards.php`<br>`tests/release-compatibility-harness.php`<br>`tests/public-release-build-pipeline.php` | No fresh mirror extraction or live `wp plugin check ... --checks=i18n_usage` run was performed in this correction pass. | none imported | Translator-comment ordering, extraction, and mirror/live alignment still require a focused rerun before this can return to `verified`. | `2026-07-10` |
| `WPORG-18D` | `J` | `implemented` | Historical evidence remains `docs/WPORG_PREREVIEW_REMEDIATION.md` `WPORG-18D Result`.<br>The combined `WPORG-18` closeout proof still depends on the historical parser and extraction reruns documented there. | historical result section only | This correction pass did not rerun the final semantic translator-comment audit, parser gates, or extraction gates. | none imported | Future submission work still needs the actual reviewer source plus a fresh semantic closeout audit. | `2026-07-10` |
| `WPORG-19A` | `C` | `implemented` | Historical evidence remains `docs/WPORG_PREREVIEW_REMEDIATION.md` `WPORG-19A Result`.<br>Current repository still contains the shared nonce and request guard surfaces in `includes/runtime-guards.php`. | `tests/nonce-input-normalization.php`<br>`tests/admissions-rest-permissions.php`<br>`tests/runtime-stub-guards.php`<br>`tests/release-compatibility-harness.php`<br>`tests/public-release-build-pipeline.php` | This correction pass did not rerun the nonce normalization suite or the release harnesses. | none imported | Object-level authorization follow-up remains tracked by `WPORG-19B`; both batches still need fresh final-audit certification. | `2026-07-10` |
| `WPORG-19B` | `C` | `implemented` | Historical evidence remains `docs/WPORG_PREREVIEW_REMEDIATION.md` `WPORG-19B Result`.<br>Current repository still contains `tests/authorization-boundary-hardening.php`, `tests/admissions-rest-permissions.php`, and the shared runtime guards used by the audited handlers. | `tests/authorization-boundary-hardening.php`<br>`tests/admissions-rest-permissions.php`<br>`tests/request-input-sanitization.php` | This correction pass did not rerun the authorization inventory or its focused tests. | none imported | Future audits should confirm no new direct-dispatch paths were added outside the historical inventory. | `2026-07-10` |
| `WPORG-20A` | `D` | `implemented` | Historical evidence remains `docs/WPORG_PREREVIEW_REMEDIATION.md` `WPORG-20A Result`.<br>Current repository still contains `includes/runtime-guards.php`, and this correction pass found no `FILTER_UNSAFE_RAW` matches under `includes/` or `tests/`. | `tests/request-input-sanitization.php`<br>`tests/runtime-stub-guards.php`<br>`tests/release-compatibility-harness.php`<br>`tests/public-release-build-pipeline.php` | This correction pass spot-checked current helper inventory only. It did not rerun the historical sanitization or release verification commands. | none imported | Upload handling and decoded JSON validation still stay in `WPORG-20B` and `WPORG-20C`; all three batches still require fresh certification for `verified`. | `2026-07-11` |
| `WPORG-20B` | `D`, `H` | `implemented` | Historical evidence remains `docs/WPORG_PREREVIEW_REMEDIATION.md` `WPORG-20B Result`.<br>Current repository still contains `vms_upload_read_file()` and `vms_validate_uploaded_file()` in `includes/runtime-guards.php`, plus current consumers in `includes/core/private-files.php`, `includes/admin/data-tools/actions-event-plan-import.php`, `includes/portal/vendor-portal.php`, and `includes/integrations/ticketing-verifications.php`. | `tests/upload-validation-guards.php` | This correction pass confirmed the helper and test files still exist, but did not rerun the focused upload validation suite or any broader release gates. | `d1cdfbd80b05c8254cdc413d0e1bbb821ca13492` | Future audits should confirm no new raw path persistence or trust-on-client-MIME paths were introduced, and mirror/live alignment was not freshly rediffed here. | `2026-07-11` |
| `WPORG-20C` | `D` | `implemented` | Historical evidence remains `docs/WPORG_PREREVIEW_REMEDIATION.md` `WPORG-20C Result`.<br>Current repository still contains `vms_json_top_level_token()` and `vms_json_decode_associative()` in `includes/runtime-guards.php`, with current consumers in `includes/rest/class-vms-rest-tours.php`, `includes/services/event-plan-import/event-plan-import-engine.php`, and `includes/integrations/ticketing-rules-v2.php`. | `tests/decoded-json-validation.php` | This correction pass confirmed the helper and test files still exist, but did not rerun decoded-JSON validation or route-specific payload tests. | none imported | Route-specific field validation still belongs to each handler and should be rechecked when payload shapes change. | `2026-07-11` |
| `WPORG-21` | `H` | `implemented` | Historical evidence remains `docs/WPORG_PREREVIEW_REMEDIATION.md` `WPORG-21 Result`.<br>Current repository still reflects the upload-hardening helpers and tests carried by `WPORG-20B`, but no separate implementation artifact exists beyond that bookkeeping. | relies on `WPORG-20B` test evidence, especially `tests/upload-validation-guards.php` | This correction pass did not rerun the historical upload-finding reconciliation. | `d1cdfbd80b05c8254cdc413d0e1bbb821ca13492` via `WPORG-20B` | Treat this as historical bookkeeping unless a concrete upload regression reopens it. | `2026-07-11` |
| `WPORG-22` | `B` | `implemented` | Historical evidence remains the `WPORG-22` result sections in `docs/WPORG_PREREVIEW_REMEDIATION.md`.<br>Current repository still contains the externalized asset owners and tests, including `assets/css/admin-ticket-integrity.css`, `assets/js/vms-event-plan-secondary-vendors.js`, `assets/js/vms-event-plan-staff.js`, `tests/ticket-integrity-inline-css-remediation.php`, `tests/ticketing-server-controls-inline-js-remediation.php`, `tests/vendor-portal-modal-inline-js-remediation.php`, and `tests/event-plan-shell-controller-inline-js-remediation.php`. | `tests/ticket-integrity-inline-css-remediation.php`<br>`tests/ticketing-server-controls-inline-js-remediation.php`<br>`tests/vendor-portal-modal-inline-js-remediation.php`<br>`tests/event-plan-shell-controller-inline-js-remediation.php` and related `tests/event-plan-*-inline-js-remediation.php` files | This correction pass confirmed current assets and focused test files exist, but did not rerun the slice-specific suites. | `8096682beaea18a91650f37e26675810f1a341ff` for `B4`; other sub-batches remain documented in their result sections | Future edits must preserve externalized asset ownership and avoid reintroducing executable inline controllers. Fresh mirror/live verification is still required for `verified`. | `2026-07-12` |
| `WPORG-23` | `K` | `implemented` | Historical evidence remains `docs/WPORG_PREREVIEW_REMEDIATION.md` `WPORG-23 Result`.<br>Current repository still contains `vms_admin_ui_is_admin_notice_screen()` in `includes/admin-ui/context.php` and current call sites in `includes/admin/admin-notices.php`, `includes/runtime-guards.php`, and `includes/ticketing/ticket-integrity-payment-gateway-health.php`. | `tests/admin-notice-scope-remediation.php`<br>`tests/request-input-sanitization.php`<br>`tests/vendor-apply-admin-css-remediation.php`<br>`tests/ticket-integrity-inline-css-remediation.php` | This correction pass spot-checked the helper and current call sites, but did not rerun the focused notice-scope suite. | none imported | Notice placement polish remains outside `WPORG-23` and should not be conflated with this completed scope. Fresh final-audit certification is still required. | `2026-07-12` |
| `WPORG-24` | `E` | `audited` | Current repository evidence now shows multiple historical implementation slices rather than prereview-only work.<br>`docs/WPORG_PREREVIEW_REMEDIATION.md` sections `302-1021`, current Git history through `b048fcb05c8c6bdff4238b46e7988d04808dbf54`, and current `includes/` / `tests/` files support the detailed breakdown below. | see the `WPORG-24` breakdown below | This correction pass rebuilt current-repository evidence only. It did not rerun a complete focused `WPORG-24` verification suite or close the remaining boundaries, so the parent issue remains `audited`. | multiple; see the `WPORG-24` breakdown below | Do not treat `WPORG-24` as prereview-only anymore, but do not claim overall completion. Remaining shared-shell, Settings structured-result, and Event Plan output-contract follow-up work stays open until freshly verified. | `2026-07-15` |
| `WPORG-25` | `I` | `audited` | Evidence is still limited to prereview findings `I1` and `I2` in `docs/WPORG_PREREVIEW_REMEDIATION.md`, plus current `includes/integrations/load.php`, `includes/integrations/ticketing.php`, and `includes/integrations/ticketing-rules-v2.php`. | none yet | This correction pass did not add implementation proof or focused verification for buffering and output-lifecycle safety. | none imported | Treat this as an investigated but incomplete architecture review until a focused current-tree audit lands. | `2026-07-09` |
| `WPORG-28` | `M` | `blocked` | Current repository still proves the mismatch: mirror `vendor-management-system.php`, `readme.txt`, and `vms-build.txt` remain `1.0.0`, while the sibling live tree `../../vms/vendor-management-system.php`, `../../vms/readme.txt`, and `../../vms/vms-build.txt` remain `1.1.0`.<br>`docs/WPORG_PREREVIEW_REMEDIATION.md` finding `M1` matches that current state. | none in this correction pass | This correction pass reconfirmed the current mirror/live metadata mismatch, but no release-state decision was authorized. | none imported | Cannot be closed safely until the desired public and live version state is explicitly reconciled. | `2026-07-15` |

## `WPORG-24` Current Breakdown

### `WPORG-24` Summary

- Overall status: `audited`
- Current repository evidence now proves that `WPORG-24` is not merely a prereview placeholder. Multiple historical slices landed in Git and still have current source and test files at HEAD.
- Latest commit evidence currently tied to this family reaches `b048fcb05c8c6bdff4238b46e7988d04808dbf54 Normalize Event Plan secondary-vendor save HTML response`.
- This correction pass rebuilt the ledger from current Git history, current mirror source files, current focused test filenames, and `docs/WPORG_PREREVIEW_REMEDIATION.md`. It did not rerun every slice-specific command suite or perform a complete fresh sink inventory, so the parent issue does not qualify for `verified`.
- Remaining open boundaries still called out by the tracker include shared Administrator-shell raw `$captured_notices_html` and `$content_html` families, deferred Settings structured-result work, and broader Event Plan output-contract follow-up work beyond the slices below.

### `WPORG-24A` Portal, docs, and ADD output-contract slices

- Scope or output family: portal notice sinks outside vendor portal, public documentation Markdown rendering, ADD dispatch pills, Staff Portal safe HTML, vendor application confirmation, and ADD public shell output.
- Current status: `implemented`
- Relevant commits: `2a96fc359df03fd2f0f8551a811c91cd9c386515 Normalize portal notice sinks outside vendor portal`; `8025b819c6311a85834fbb07b0dbbabff9c1f13b Normalize public docs Markdown output contract`; `f446aca4016941863ab971af2fc19044dd57c851 Normalize ADD dispatch pill output sinks`; `78dcf887bcf16f02485a3e04b1b0561bddf87713 Normalize staff portal safe HTML sinks`; `6706784bc66b4445ba0d8a50b2094464f4f3afd0 Normalize vendor application confirmation output sink`; `c19c14d30cc451a287ab76b8bd385221ea230384 Normalize ADD public shell output sinks`.
- Current source paths: `includes/modules/admissions/vendor-guest-portal.php`; `includes/portal/vendor-tax-profile.php`; `includes/docs-public.php`; `includes/docs-render.php`; `includes/modules/availability-date-dispatch/admin-ui.php`; `includes/portal/staff-portal.php`; `includes/core/vendor-application-confirmation.php`; `includes/modules/availability-date-dispatch/public.php`.
- Focused test paths on record: `tests/portal-notice-sink-remediation.php`; `tests/docs-public-markdown-output-remediation.php`; `tests/add-dispatch-pill-output-remediation.php`; `tests/staff-portal-safe-html-output-remediation.php`; `tests/vendor-application-confirmation-output-remediation.php`; `tests/add-dispatch-public-shell-output-remediation.php`.
- Mirror/live synchronization: not freshly proven in this correction pass. These are shared runtime slices, but this task rebuilt evidence from mirror Git history and current mirror files only.
- Remaining verification or remaining slices: no fresh rerun of the slice-specific tests was performed here; broader Administrator-shell, Pass Claims, and Event Plan follow-up work remains outside this subentry.

### `WPORG-24B` Administrator shell base sink reductions

- Scope or output family: Administrator shell explicit status-notice sink and header-action sink reductions.
- Current status: `implemented`
- Relevant commits: `185c27c39c18f0d67b070e909ea62d8a3ba8289b Normalize administrator explicit notice output sink`; `2c3ecf001cf484304985b7f36e26fa32c7513753 Normalize administrator header action output sink`.
- Current source paths: `includes/admin-ui/shell.php`.
- Focused test paths on record: `tests/administrator-explicit-notice-output-remediation.php`.
- Mirror/live synchronization: not freshly proven in this correction pass. The shared shell file is present in the mirror, but no fresh mirror/live diff was run.
- Remaining verification or remaining slices: the tracker still leaves captured-notice fragments and full page content as separate follow-up work after these base sink reductions.

### `WPORG-24C` Administrator captured-notice and rich-notice slice reductions

- Scope or output family: Continuity Binder, Due Dates, Square Sync Protection, Staff Certifications empty-state, Social Sharing, Email Follow-Ups, Ticket Integrity, Settings, and Venue or Calendar Reconciliation notice families routed through explicit sinks.
- Current status: `implemented`
- Relevant commits: `33cdbfa5299ca8817cbd2cad31d85b71435d0963 Route Continuity Binder notice through explicit sink`; `a099f1976cd5f5fab1f6d4e1ff7453ce96de4335 Route Due Dates notices through explicit sink`; `88d8d7a77da96e570964edffb2e2372fb199f078 Route Square Sync Protection notices through explicit sink`; `ac7d300a9d0dbd95265abc3df598b18391f7eb70 Route Staff Certifications empty-state notice through explicit sink`; `d54bef4ef5975b1aef049bba9ddadcfbc60edf4b Route Social Sharing notice through explicit sink`; `c1595cb3c5db46af99cd98956c5c49c6937d5b5f Route Email Follow-ups notices through explicit sink`; `ce28f703545db4a61b902b7ea83c8979ec26fcf7 Route Email Follow-ups preview warning through explicit sink`; `97782f3bc69c9599efcef12989fb263904482798 Route Ticket Integrity notices through explicit sink`; `ae525a527472322737152fc06830eba40ed68748 Route Settings notice through explicit sink`; `f34207f71e9bffd8f004efd030accd1763066b1b Route Venue Reconciliation notices through rich explicit sink`; `436537b3b3b74422df7818cbc5a3eb225f7c5f6e Route Calendar Reconciliation notices through rich explicit sink`; `bcff7dc7687237f2b2d6c02732886a5dc75d7059 Route Settings ticketing stock notices through explicit sink`.
- Current source paths: `includes/admin/continuity-binder.php`; `includes/admin/due-dates.php`; `includes/admin/square-sync-protection.php`; `includes/admin/staff-certifications.php`; `includes/social-share/admin.php`; `includes/modules/email-followups/admin-ui.php`; `includes/admin/ticket-integrity-page.php`; `includes/admin/settings-page.php`; `includes/admin/integrity-venue-reconcile.php`; `includes/admin/integrity-calendar-reconcile.php`; `includes/admin-ui/shell.php`.
- Focused test paths on record: `tests/administrator-explicit-notice-output-remediation.php`.
- Mirror/live synchronization: not freshly proven in this correction pass. These are shared runtime slices, but this task did not re-diff the sibling live tree.
- Remaining verification or remaining slices: shared raw `$captured_notices_html` and `$content_html`, unknown or extensible notice sources, and page-family follow-up work remain open in the tracker.

### `WPORG-24D` Pass Claims admin and public output-contract slices

- Scope or output family: Pass Claims administrator notices, public status screens, already-claimed card, success confirmation, public form, and public shell caller reduction.
- Current status: `implemented`
- Relevant commits: `f9c28be364ca2c767cf4475aa02df17c0ae15618 Route Pass Claims admin notices through explicit sink`; `724923fc2076cda7d37c9df5f2fb10bc92f156c1 Normalize Pass Claims public status sink`; `1f76565c77bf4c03b96c1d640bac2eecd8d8f0bd Normalize Pass Claims already-claimed card sink`; `c1436c1df77d30c064ca8fef64d3aaeb6aa20bad Normalize Pass Claims success confirmation sink`; `230e46fee9ec93d6ef2e1bbcf6bb4e353621710e Normalize Pass Claims public form sink`; `dd05dee1ec102b716dddb55d61c91bd619e47ce1 Replace Pass Claims public shell raw content sink`.
- Current source paths: `includes/modules/admissions/pass-claims.php`.
- Focused test paths on record: `tests/administrator-explicit-notice-output-remediation.php`; `tests/pass-claims-public-status-output-remediation.php`; `tests/pass-claims-public-claimed-card-output-remediation.php`; `tests/pass-claims-public-success-output-remediation.php`; `tests/pass-claims-public-form-output-remediation.php`; `tests/pass-claims-public-shell-output-remediation.php`.
- Mirror/live synchronization: not freshly proven in this correction pass. The Pass Claims runtime file is shared, but this task rebuilt evidence from mirror history and current mirror files only.
- Remaining verification or remaining slices: the historical tracker treats the Pass Claims public-output inventory as closed for this slice family, but this correction pass did not rerun those tests and the broader non-Pass-Claims `WPORG-24` boundaries still remain open.

### `WPORG-24E` Event Plan Import and Event Feedback shared-shell reductions

- Scope or output family: Event Plan Import notices, Event Plan Import rows-payload preview error sink, Event Feedback redirect notices, and Event Feedback missing-plan notice.
- Current status: `implemented`
- Relevant commits: `e9bc4a620fb67dea73d81f8919d70da8b4c0ed6c Route Event Plan Import notices through explicit sink`; `82b3c660e15157105a47a7733f30a6a1db43874b Route Event Feedback notices through explicit sink`; `b645954645e7235c3e751dfa7f7815afd43f20a3 Route Event Feedback missing-plan notice through explicit sink`; `5b7b0375691d123e9c9f995307ca6e09999cef07 Normalize Event Plan Import rows-payload error sink`.
- Current source paths: `includes/admin/data-tools/page-event-plan-import.php`; `includes/admin/event-feedback.php`.
- Focused test paths on record: `tests/administrator-explicit-notice-output-remediation.php`; `tests/event-plan-import-rows-payload-output-remediation.php`.
- Mirror/live synchronization: not freshly proven in this correction pass. These are shared runtime slices, but this task did not re-diff the sibling live tree.
- Remaining verification or remaining slices: the broader shared Administrator-shell raw sink boundaries and separate Event Plan partial or AJAX output families remain open after these reductions.

### `WPORG-24F` Event Plan partial and AJAX HTML response families

- Scope or output family: Event Plan compensation-options, supporting-vendor options, readiness-details, staff, Secondary Vendors lazy-load, and Secondary Vendors save-response HTML families.
- Current status: `implemented`
- Relevant commits: `0a767272c992b3317ad1291e3c6cec4c8f75563c Normalize Event Plan compensation-options HTML response`; `25217c0361dfa4d8cea11e45c0f07eca43062651 Normalize Event Plan supporting-vendor HTML response`; `79f2abee5854509c98d0c3c3066b081b3f6761b4 Normalize Event Plan readiness-details HTML response`; `6a54b96c85d3742c9366bd2e468a6d64ec74fa9f Normalize Event Plan staff HTML response`; `6625757dd4c91382d030313e11bb91a4b400229a Normalize Event Plan secondary-vendor lazy-load HTML response`; `b048fcb05c8c6bdff4238b46e7988d04808dbf54 Normalize Event Plan secondary-vendor save HTML response`.
- Current source paths: `includes/cpt/event-plans.php`; `includes/cpt/event-plans/partials/compensation.php`.
- Focused test paths on record: `tests/event-plan-comp-options-output-remediation.php`; `tests/event-plan-supporting-vendor-options-output-remediation.php`; `tests/event-plan-readiness-details-output-remediation.php`; `tests/event-plan-staff-output-remediation.php`; `tests/event-plan-secondary-vendors-lazy-load-output-remediation.php`; `tests/event-plan-secondary-vendors-save-output-remediation.php`; `tests/event-plan-dead-editor-scripts-partial-removal.php`; `tests/event-plan-shell-controller-inline-js-remediation.php`; `tests/event-plan-secondary-vendor-inline-js-remediation.php`.
- Mirror/live synchronization: not freshly proven in this correction pass. These are shared runtime slices, but this task rebuilt evidence from mirror Git history and current mirror files only.
- Remaining verification or remaining slices: the tracker still says broader Event Plan output-contract follow-up work remains separate even after `b048fcb05c8c6bdff4238b46e7988d04808dbf54`, so this family is implemented but not fully re-certified.

## Update Rule For New Entries

Every completed remediation task must append or update the relevant row with:

- selected reviewer issue identifier
- issue family
- status
- current repository evidence
- focused tests on record
- verification state in the current task
- commit SHA when applicable
- remaining risks or follow-up
- most recent focused verification on record
