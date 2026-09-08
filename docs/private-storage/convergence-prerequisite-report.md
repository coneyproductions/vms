# Phase 3B prerequisite — private-storage convergence and database write forensics

Status: **READY TO RESUME PHASE 3B LOCAL ACCEPTANCE**.

Phase 3B itself remains unaccepted, uncommitted and unpromoted. No Phase 3B browser acceptance or real mail occurred. This prerequisite forwards and locally accepts only the qualified private-storage security delta.

## Authority and isolation

Accepted base: `6a8ffcdc2b04d2ff0faa3e4278a29aa3193c22b7`. New branch: `work/private-storage-convergence-20260907`. Worktree: `/private/tmp/bvm-private-convergence-20260907/packages/vms-github-reconcile`; synchronized isolated sibling: `/private/tmp/bvm-private-convergence-20260907/vms`.

The original dirty `work/unreleased-2026-06-18` at `79da784` is excluded. Its 37 modified tracked paths and 36 untracked entries were neither incorporated nor changed. Isolation resolves the specifically authorized preflight exception; the new worktree passed clean preflight before editing. Normal canonical runtime was independently compared to the accepted base: all shared files matched, across its 397-file pre-change tree. Legacy `vms`, companion trees, the existing Phase 3B candidate and protected stash are preservation boundaries.

Frozen release: `04caf99ae7a98ac507c067a7535d2a672efa457b`, branch `release/wporg-readiness-2026-09-07`. Both the candidate-10 ZIP and submission-ready ZIP remain SHA-256 `2c2a488395d32d419741a99a1211c18fe649f73e567c1cff8de749d44d08c8e3`, with 396 files. Neither was rebuilt or modified.

## A–C. Exact security delta and reconciliation

History identifies four security commits:

- `21f7367002b5abf739735f8799411cfdd6ee85a6`: verified non-public storage, explicit migration, fail-closed callers, read-only resolution and host documentation.
- `7dd74c7a53c97c0d62a5605082ec93c15d5be87e`: WP-CLI DOCUMENT_ROOT compatibility and containment of filesystem error details.
- `d3a5b1bd133398aabe4a1dc69cc02c18ddbafe58`: removal recovery, source-digest mismatch coverage and empty owned directory cleanup.
- `04caf99ae7a98ac507c067a7535d2a672efa457b`: case-sensitive filesystem boundaries.

`git diff 21f7367^ 04caf99 -- <selected paths>` reconstructed the delta; `git apply --check` passed without conflicts on `6a8ffcd`. No release branch merge or wholesale replacement occurred. The pre-security release versions of the four modified runtime paths were identical to the accepted base, so the patch required no speculative reconciliation. All six resulting runtime files are byte-identical to the qualified final release.

| Path | Prior classification | Required behavior |
| --- | --- | --- |
| `includes/core/private-storage.php` | Absent | Explicit host roots; canonical path, overlap, alias, symlink, hardlink, case and site checks; no public fallback; safe read resolution. |
| `includes/core/private-storage-migration.php` | Absent | Administrator/capability/nonce-gated migration; exclusive verified copy, durable receipt, remove owned plaintext, recovery and idempotence. |
| `includes/core/private-files.php` | Present differently | Wire broker to secure storage; no storage creation on schema reads; fail-closed upload/delete; authorized streaming and private attachment resolution. |
| `includes/core/staffing.php` | Present differently | Four-line suppression of migrated attachment proof URL only; accepted staffing calculation/lifecycle retained. |
| `includes/integrations/ticketing-verifications.php` | Present differently | Secure proof resolution/deletion, retaining associations on failed deletion. |
| `includes/services/event-plan-import/event-plan-import-engine.php` | Present differently | Secure import roots and read resolution without creating directories or falling back to plaintext. |
| `readme.txt` | Present differently | Only the qualified private-storage configuration/migration section was inserted. |

Existing W-9, tech-document, certification and proof download authorization remains in the callers. The vendor portal and Event Plans use the shared broker; their accepted source was not replaced. Agreements are separately managed companion functionality and were not imported or changed. ECC 2.0, Phase 3A cancellation/reporting/purchase code, staffing read-only architecture and all other accepted runtime paths remain intact.

The new security contract, boundary/integration suites, and qualified private-file/import test changes were reused. One additional already-qualified test-only repair from `590f11d` updates the old import upload fixture to the canonical nonce compatibility API. Its old version fails identically on accepted Phase 3A; the updated fixture passes without changing runtime nonce behavior.

Excluded as unrelated or release-only: fresh-install staffing/dbDelta and strict-date fixes, public mock-provider removal, driver license/readme changes, release packaging/exclusion/pipeline assertions, release scan reports, and every reversal/difference affecting newer cancellation/ECC functionality. No security runtime conflict remains uncertain. Full per-file base/converged/qualified SHA-256 values are in `convergence-source-map.json`; normal seven-path payload hashes are retained in the external rollback receipt.

## D. Private-storage acceptance

The qualified tests were reused against a direct installed copy of reconciled source, with only the external disposable-root guard adapter changed. No ZIP was built. MySQL was supervised on a new private socket, with networking disabled; WordPress, mail/HTTP interception and synthetic documents were disposable. All owned processes stopped and database residue was removed.

- 16 filesystem configuration cases pass, including missing/unsafe roots, declared public aliases, relocated content/uploads, subdirectory WordPress, case-sensitive boundaries, traversal, symlinks, hardlinks and cross-site denial.
- Real database/filesystem integration passes: stable indexed identity, referenced attachment and registered derivative migration, historical absolute import/proof references, deletion interruption, destination conflict, persisted digest mismatch, recovery and idempotence.
- The reused 22 Nginx/PHP HTTP probes pass on both security-only and compatibility-installed trees: synthetic plaintext 200 before explicit migration and 404 afterward; exact authorized old/new W-9 and attachment bytes; unauthorized and invalid nonce denial; real multipart private upload; no public storage URL/path leakage; private/no-store/nosniff headers; missing/misleading HTTP DOCUMENT_ROOT yields 503.
- Ordinary private admin/payload reads and migration reruns have zero SQL writes and unchanged filesystem hashes/mtimes/modes.
- Deactivate/reactivate succeeds with host-dependent fail-closed upload behavior preserved.

HTTP results describe controlled installed-source probes, not Phase 3B browser acceptance. Normal-local has separately verified actual Apache mapping, installed source parity, secure-root configuration, migrated file hashes, absent legacy plaintext and read containment. No HTTP response containing meaningful historical local imports was requested.

## E–G. Original twelve-table drift

The original consistent snapshots span 2026-09-08 02:47:00–02:53:58 UTC (September 7, 21:47–21:53 CDT). Retained MySQL ROW/FULL binlog `binlog.000056` contains exact before/after images. Apache access logs show profile/login requests, the `as_async_request_queue_runner` POST, `wp-cron.php`, Woo privacy cleanup and logged-in requests during the write burst. Scheduler log rows explicitly name Async Request and WP Cron execution. Request-to-connection association uses timestamp correlation; row values and scheduler hook attribution come directly from the binlog and persisted scheduler logs.

784 row events across 14 tables occurred in the snapshot window. Twelve tables differed at the end; two more were transiently modified and restored. Every original table count delta reconciles to its inserted/deleted rows. For all 87 changed option names, both before and after value SHA-256 comparisons match the original manifests: **174/174**. Exact row IDs, named columns, literal before/after values, UTC commit time, connection ID, binlog position, owner, triggering hook/context and classification are in the mode-0600 external `twelve-table-row-evidence.json`. No sensitive row values enter this repository.

| Original table | Rows before → after | Row events | Classification | Proven writer/context |
| --- | --- | --- | --- | --- |
| wp_actionscheduler_actions | 3283 → 3299 | 69 | EXPECTED PLATFORM WRITE | Action Scheduler |
| wp_actionscheduler_claims | 0 → 0 | 8 | EXPECTED PLATFORM WRITE | Action Scheduler |
| wp_actionscheduler_logs | 9812 → 9861 | 53 | EXPECTED PLATFORM WRITE | Action Scheduler |
| wp_mailpoet_subscribers | 83 → 83 | 1 | EXPECTED PLATFORM WRITE | MailPoet logged-in subscriber activity tracking |
| wp_options | 824 → 781 | 358 | BASELINE NOISE, EXPECTED PLATFORM WRITE | BVM/companion cron, cache or diagnostics; Weather Risk scheduled refresh/cache; WordPress/Woo/TEC cron, scheduler, cache, updates |
| wp_postmeta | 83504 → 83504 | 72 | BASELINE NOISE, EXPECTED PLATFORM WRITE | BVM Square sync firewall on scheduled Woo product save; Weather Risk cron refresh; WooCommerce scheduled sales / Events Calendar ticket cost refresh |
| wp_posts | 3877 → 3877 | 2 | EXPECTED PLATFORM WRITE | Woo scheduled sales product modified timestamps |
| wp_tec_kv_cache | 2 → 2 | 5 | EXPECTED PLATFORM WRITE | TEC ticket view invalidation and rebuilding |
| wp_usermeta | 1540 → 1541 | 5 | EXPECTED PLATFORM WRITE | WordPress login session and Woo customer/cart hooks |
| wp_vms_social_audit | 4868 → 4869 | 1 | BASELINE NOISE | BVM social queue cron runner_tick, skipped/processed=0 |
| wp_wc_admin_note_actions | 106 → 106 | 154 | EXPECTED PLATFORM WRITE | Woo admin inbox refresh |
| wp_woocommerce_sessions | 46 → 4 | 42 | EXPECTED PLATFORM WRITE | Woo scheduled expired session cleanup |

The claims table is the twelfth difference despite zero rows at both endpoints: four claim inserts/deletes advance AUTO_INCREMENT. Woo category lookup regeneration executes TRUNCATE and ten inserts, leaving the same final contents; Woo product lookup has four temporary price updates and returns to its original hash. Coarse equality alone missed these writes.

Notable BVM/companion activity: Square firewall metadata during scheduled Woo sale saves, 36 Weather Risk snapshot refreshes, cache/diagnostic/cron options, and one social `runner_tick` with `processed=0`, `skipped=true`. These are BASELINE NOISE established independently while the Phase 3B candidate was absent from the installed runtime. They are not explicit Phase 3B action writes. Platform cleanup removed 42 expired Woo sessions and expired transient options; Woo inbox actions were rebuilt; login updated session/cart/subscriber activity. No tech-document metadata, private-file index, staffing assignment, notification ledger or migration receipt was changed by that prior run.

Original window classification: INTENDED TEST WRITE = none (no normal fixtures existed); EXPECTED BVM WRITE caused by Phase 3B = none; EXPECTED PLATFORM WRITE and BASELINE NOISE account for every material mutation; DEFECT attributable to Phase 3B/private reads = none identified; UNRESOLVED = none. No historical row was restored to make the comparison pass. The previous report's concern was valid; these independently active writers required containment, not cosmetic hash exclusions.

The characterization harness now records row/binlog evidence as well as table snapshots, logs suppressed scheduler and option attempts, and uses a bounded process guard. Normal background PHP/cron and unrelated CLI processes are blocked before normal plugins load. The authorized probe uses a database READ ONLY session and rejects unexpected DML/DDL; only the explicit migration temporarily permits the exact private-receipt option writes. HTTP/mail are intercepted. The legacy characterization was rerun on the reconciled normal runtime. A separate read window preserves **all 246 tables and schemas and the exact binlog position**; an independently launched process was demonstrably blocked.

Containment is temporary test infrastructure. It suppresses Action Scheduler, option/cache refresh and diagnostics during the window, and uses in-memory object caching; these differences are logged. Operational cron settings and activation state were not changed. Both guard files were removed after each run. Ordinary normal-local background activity can resume after the bounded window.

## F. Phase 3B compatibility rerun and read surfaces

The existing Phase 3B candidate remained untouched. A separate compatibility copy combines the accepted security source with its six candidate runtime files. All **64 existing assertions pass unchanged**. No candidate adaptation was required and no notification code is included in the convergence commit or normal promotion.

Additional real WordPress/storage tests cover nine read surfaces: Event Plan technical-document metabox, document view, status, refresh, authorized payload, notification history, confirmed-recipient display, secure document snapshot and proposed-recipient display. Each compares every one of the disposable database's 52 tables plus filesystem fingerprints. All are equal; zero SQL writes and zero mail attempts. The real broker is used here, supplementing the original 64-test suite's stubs. This is read containment, not Phase 3B operational/browser acceptance.

Surrounding checks pass: cancellation 58, staffing/financial 127, financial authority 234, ECC context 35, ECC semantics 103, private/import upload and operations, verification normalization, authorization and nonce normalization. Changed PHP files lint; diff checks and own source review pass. Initial fixture-layout failure is retained: a recursive copy exclusion accidentally omitted runtime `includes/docs`; correcting only the disposable copy allowed activation and the full qualification to pass. No product workaround was introduced.

## H. Normal-local promotion, migration and rollback

Actual host mapping: Local Apache serves the site's `app/public`; loaded configuration has no additional filesystem alias. Nine symlinks beneath that root were inventoried; none exposes the new dedicated `app/bvm-private-documents`. The root is outside the published tree, mode 0700, and wp-config declares it plus the complete local public-root array. Normal `BVMGR_PRIVATE_STORAGE_ROOT` and `BVMGR_PRIVATE_STORAGE_WEB_ROOTS` now validate. These declarations are local host configuration, not portable defaults.

Inventory before migration: zero indexed private documents; zero legacy attachment references; three existing staff-qualification fixture records with no attached files; six legacy guard files; **15 historical import artifacts**. Their filenames/content classification was not used to treat them as disposable. All were preserved as potentially meaningful operational data before migration. No production/staging data was accessed.

The payload is exactly six PHP files plus the private-storage readme section, installed under canonical `backstage-venue-manager`; the two new PHP paths were previously absent. Shared changed runtime bytes equal the isolated source and qualified release. The original mirror, inactive legacy tree and companion directories remain unchanged.

All 15 historical imports were copied, verified and migrated through the real explicit service. Hashes match the saved originals, old paths are absent, old absolute references resolve securely, and repeated migration reports zero migrated items without write permission. The only retained database change is **16 new migration options (15 per-file receipts plus the qualified batch summary)**, produced by 31 INSERT/UPDATE statements; all other 245 tables and structural schemas are equal (the options AUTO_INCREMENT advances). Final review caught the first normal harness suppressing `bvmgr_private_storage_migration_result` while allowing per-file receipts. The allowlist was corrected, the exact returned original batch result was persisted as one controlled bookkeeping repair, and the full 246-table/binlog read window was repeated successfully. No document associations or source IDs changed. Normal Phase 3B functions remain absent.

Complete rollback authority is outside the web root: `phase-3b-prerequisite/normal-rollback/` in the durable evidence directory below. It contains the full pre-change 397-file canonical plugin, exact wp-config, all 15 imports, a 39,579,929-byte consistent database dump, seven-path before/after hashes and a complete SHA-256 manifest. Prefer surgical rollback; do not overwrite later unrelated database activity with the full dump. Keep the process guard active, verify current hashes against the receipt, restore the old four PHP paths and readme, remove only the two added PHP paths, and restore the exact pre-change config. Keep migrated private objects and receipts as recovery evidence. If reverting document storage too, restore only the 15 archived legacy files under the guard and remove only this run's 16 unchanged receipt/summary options; this intentionally restores the older local plaintext layout and must not be mistaken for secure acceptance. Remove containment only after verifying the intended rollback state. Detailed `RESTORE.md` accompanies the artifacts.

## I–J. Preservation and closeout

Final preservation verifies the old dirty worktree content/index/status/HEAD, unchanged Phase 3B candidate, legacy tree, all monitored companions, release branch/source and both exact ZIP copies, and protected stash `d08e726804712dc233f0e37b217abd6389963863`. There is no Phase 3B promotion, no real email, no Phase 3C work, and no staging/production, remote Git, submission, package build or reviewer reply.

The separate local security-convergence commit is recorded in the external final receipt. This prerequisite is complete; Phase 3B may resume its separately bounded local acceptance, using fresh database baselines and the documented process guard.

Durable evidence: `/Users/treyconey/Documents/BVM Ecosystem Audit 2026-09-07/phase-3b-prerequisite/`. Reproducible source artifacts: `convergence-source-map.json`, `database-forensic-matrix.json`, and the qualified tests in this repository. Detailed commands, adapter scripts, logs, HTTP receipts, original-row evidence, rollback hashes and preservation receipt are retained privately in that evidence directory.
