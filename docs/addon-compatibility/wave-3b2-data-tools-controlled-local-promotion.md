# Wave 3B-2 — VMS Data Tools 0.5.55 Controlled Local Promotion

Date: 2026-09-06
Scope: local-only same-basename Data Tools promotion and BVM reporting-provider acceptance

## Decision

VMS Data Tools `0.5.55` is **ACTIVE / CANONICAL / ACCEPTED LOCALLY** at `vms-data-tools/vms-data-tools.php`.

The exact predecessor, complete archive, targeted database backups, and all evidence remain outside the web root at `/Users/treyconey/Local Sites/serenade-range-local-test-site/app/bvm-local-rollbacks/wave3b2-20260906T125640Z/data-tools`.

## A–D. Preflight, source authority, and expected mutation contract

The required preflight completed with only the expected accepted dirty/untracked-tree warning on branch `work/unreleased-2026-06-18` at HEAD `79da784f5bfcc66bd058c0a7f54e08d7b15bb5d5`; its path, branch, HEAD, stash, and diff checks passed. Local-ref upstream relation was `0/0`, the index was empty, and `git diff --check` was clean. Protected stash object `d08e726804712dc233f0e37b217abd6389963863`, named `WPORG-16D preserve unrelated sidebar+doc work`, remained `stash@{0}`. The starting tracked-diff fingerprint was `8c504c95aadd12748174baa1d2817d57d46c6af82f06e107a1de65fe6a1716ac`; the full porcelain fingerprint was `4f4677698f31f4ea3ab4b9381cfd5446cf5210e1b8cd0d5602e2f2bc847709c9`.

All WordPress acceptance used `/opt/homebrew/opt/php@8.3/bin/php`, PHP `8.3.33`, with WP-CLI `2.12.0`. Default shell PHP was not treated as site authority. Normal activation began with exactly one canonical Data Tools entry in the 34-entry, 1,760-byte `active_plugins` serialization, raw SHA-256 `350ea69718f7946020940a98d081b061225c76a720f4fdcda9287403781b3afe` and emitted-JSON SHA-256 `27115e8644f764f4be9403d7aa0c356c2b98a5543a1ef2fe079b0231d7fff98c`.

The exact active baseline was `0.5.54`, 78 files, normalized tree SHA-256 `7f997b79f675b2534188d44f750afb4bd4eec9d03db9f75b6020b0afe02e9039`. The accepted candidate was `0.5.55`, 81 files, normalized tree SHA-256 `8e36b9b6cf1e337627a86a782f2eafc49681d09b8711665e54ab78d6b005988b`.

The complete source comparison found only version/build metadata, two new build/test notes, one bootstrap `require_once`, and the new `includes/integrations/bvm-reporting-provider.php`. Activation/deactivation hooks, capability setup, every schema installer and `dbDelta` path, version checks, `maybe_upgrade` logic, cron scheduling, imports, vendor-invite behavior, and Square behavior are unchanged. The provider registers in memory immediately when the BVM contract is available and again idempotently at `plugins_loaded`; its reporting dependencies are loaded lazily only on provider invocation.

The installed database already satisfied the unchanged schema/version gates. Therefore the recorded contract was:

> EXPECTED PERSISTENT DB MUTATIONS: NONE

No migration routine needed to run. The minimum controlled runtime was a read-only, plugin-skipping PHP 8.3 WordPress load that manually required only canonical BVM and the installed Data Tools entry.

## E–H. Rollback, baseline, and pre-promotion validation

The complete `0.5.54` source archive is mode `0600`, 300,408 bytes, SHA-256 `5d45c4a99c2b1bbe57aeae880473716cd1f23302b223aa469f69f7f295174dfb`, at `source-rollback/vms-data-tools-0.5.54-complete.tgz`. Its 91-entry listing passed; extracting it produced the exact 78-file predecessor tree and a byte-clean comparison. The old directory is also retained directly at `vms-data-tools-0.5.54-preserved-tree`.

Targeted database rollback materials are mode `0600` and use a compare-first, restore-only-verified-task-created-state policy:

- Data Tools/options/Square/activation/cron/roles: 30,637 bytes, SHA-256 `8766a54aa77c372695cf3f9b28ace2ee630e4488512a16249de48f94c17dbdd8`;
- eight owned tables with schema and contents: 219,423 bytes, SHA-256 `bcad28318960721e8bbac86db7c53a84ed380e3ee4d62a96438f54c1f4b943a9`;
- Data Tools Action Scheduler rows: 7,580 bytes, SHA-256 `bb69d794a0f7444e8bfe883768e760e96e02830cc8c4c46bbc61d4b68ca24235`.

Safe manifests separately record seven Data Tools options, nine WooCommerce Square options without disclosing values, `wp_user_roles`, the empty Data Tools cron-hook inventory, the `vms_import_vendors` administrator capability, table schemas/counts/auto-increments/checksums, and `vms_square_nightly_sync` at 12 complete plus one pending action. An initial WP-CLI export passed `--no-create-info` without an explicit value; WP-CLI translated it incorrectly and produced an empty retained evidence file. The corrected `--no-create-info=true` targeted export succeeded without a database write.

The proven `0.5.54` normal-local baseline passed 124 assertions: exact source and activation authority, canonical BVM/P0 availability, no Data Tools provider registration, and byte-equivalent protected state. The `0.5.55` candidate diagnostic then passed 154 assertions while loaded from outside the web root, including exactly one provider and the complete ECC/Vendor Portal Website/Square fixture path. Both ended with clean guard/lock/runtime teardown and no stderr.

The first baseline wrapper revision used WP-CLI's `--context=admin`. WooCommerce entered an incomplete admin context before the runner and failed because menu arrays were unavailable. This was runner infrastructure, not a Data Tools failure. Its guarded window also observed two concurrent updates to user 1's WordPress `session_tokens`; local binlog evidence identifies normal browser/session bookkeeping, not Data Tools or task activity. The evidence is retained mode `0600`, SHA-256 `2ed95bdccf648d6cda4ec259c4e4b59688b2b286724d2a941d1d621a882bcae0`, at `provider-baseline/failed-admin-context-binlog.txt`. It was not guessed or overwritten. The wrapper was corrected to the established admin preload with plugins/themes skipped, after which the baseline and candidate diagnostics passed.

Before promotion, 21 focused suites passed: Calendar 109/navigation, BVM provider contract, Data Tools provider/decoupling, Vendor Portal paid-basis behavior, P0 source consistency, three staffing SQL suites, ticketing core/claims, Commerce business rules, two Weather suites, additional targeted ownership, Intake source/venue, Router, and both Bridge probes. The hardened official-five matrix passed `19/19`; the additional ecosystem passed `52/52`. Both proved blocked outbound HTTP, independent Local process containment, disposable residue removal, database/runtime/guard/lock cleanup, and unchanged normal state.

## I–L. Atomic replacement and persistent-state result

An exact candidate copy was staged outside the web root on the same filesystem, rehashed, and compared byte-for-byte. With the normal Local cross-process guard and per-site lock active, two same-filesystem directory renames moved exact `0.5.54` to the preserved location and exact `0.5.55` to `wp-content/plugins/vms-data-tools`. The basename stayed `vms-data-tools/vms-data-tools.php`; no deactivate/reactivate occurred, and no mixed-version tree was exposed.

A first command-only promotion precheck stopped before copy or rename because its shell assertion compared a tab escape literally. Active source remained exact `0.5.54`; its cleanup receipt was isolated at `promotion-precheck-command-correction`. The field-wise precheck then passed. This was neither a product failure nor a promotion attempt.

Immediately after the renames, the installed source reported `0.5.55`, 81 files, and normalized tree `8e36b9b6cf1e337627a86a782f2eafc49681d09b8711665e54ab78d6b005988b`; recursive candidate comparison was empty. All installed PHP passed lint under 8.3, all JavaScript passed `node --check`, and the provider integration source was present at SHA-256 `4ffc724bfcb3b8ad10a7fede71fb284c5802c885c1d20a84548e5e6a67b5aae8`.

Actual persistent Data Tools-owned mutation was **NONE**, exactly matching the expected receipt. The immediate guarded promotion window was byte-equivalent before/after across all 820 WordPress options and all 246 tables. `active_plugins`, cron, `wp-config.php`, seven Data Tools options, nine Square configuration options, all eight Data Tools-owned table checksums, Action Scheduler rows, roles/capabilities, and Weather state were unchanged. No business row was created, updated, or deleted.

Normal Local cron and unrelated background bookkeeping resumed between separate contained runs, so whole-site aggregate cron/options/table hashes moved between windows. The observed between-window rows were ordinary theme-pattern transients, Intake lock timing, BVM operational fingerprints/ticket-integrity status, Action Scheduler bookkeeping, and social audit—not promotion-window or Data Tools-owned mutations. Every individual guarded window was exact before/after. Final comparison against the original targeted receipt confirms all Data Tools/Square options, all eight owned tables, and the `vms_square_nightly_sync` inventory still match the pre-promotion baseline.

## M–S. Provider and negative-contract acceptance

The installed-source normal-local runner passed all 154 assertions during promotion and again at final closeout. It proved exactly one `vms-data-tools` provider with version `0.5.55`, contract version `1`, priority `20`, capability `event_ticket_sales`, and callback `vms_dt_bvm_reporting_provider`. Re-registration is idempotent and a duplicate provider ID is rejected.

Event Command Center resolution returned provider `vms-data-tools`, version `0.5.55`, source provenance `dt_reporting_model`, and the expected calculated ticket quantities/revenue with full-day Square scope. The Event Plan/ECC provider path, valid-zero semantics, unavailable/error/exception isolation, and Woo/core fallback all passed the provider suites.

Vendor Portal resolution returned source `data_tools_merged_ticket_sales`, preserved provider/version provenance, combined Website and Square quantities/revenue, passed Website row filtering to the provider-owned rollup, and retained full-day Square scope. BVM source contains no direct Data Tools implementation include, `VMS_DT_ADMIN_DIR` dependency, or direct `vms_dt_reporting_*` invocation. Data Tools continues to own and lazily load its Website/Square reporting internals.

The inactive-but-files-present contract passed without deactivating normal Data Tools: isolated BVM source loaded with Data Tools files on disk did not execute any Data Tools PHP, define its constant/bootstrap/provider function, register a provider/hook/cron action, or write state, and BVM used its fallback. The physically absent `bvm-data-tools-directory-absent` scenario also passed without fatal and returned the safe fallback/unavailable contract. Data Tools-first and BVM-first both registered exactly one provider; deterministic priority, valid nonzero, valid zero, unavailable, exception, duplicate rejection, and provenance assertions passed.

## T–X. Matrices, preservation, containment, and normal state

Post-promotion focused coverage passed the same 21 suites. Two source-authority assertions were updated from the now-historical `0.5.54 remains unpromoted` description to accepted `0.5.55`; the Calendar normal-local helper's accepted-version metadata was updated while retaining its deliberately skipped-plugin/no-provider assertion. No runtime behavior was changed by these test-only updates.

The post-promotion official-five report passed all `19/19` scenarios using the exact installed Data Tools tree. The additional report passed all `52/52` scenarios and preserved its Bridge forensic worktree. Both load orders and the coexistence cases passed with no fatal, duplicate class/hook/provider/route/menu, or dependency-absent regression. Both reports record database and runtime cleanup, external HTTP blocked before transport, an independently launched Local web process blocked, residue removed and asserted, exact normal state, guard cleanup, and lock release.

Accepted first-party trees remain exact and active: Calendar Feeds `0.1.4`/17 files/`20f2607ec61c62f2d7120cd99b657b46e15bc169f6d92d14f7951540e33fc20e`; Weather `0.1.12`/35/`e019619ce1bd5bef851dbdb4573fd527332983cda34004bd5fc531ae50cc5c0b`; Commerce `0.2.13`/27/`03bcb58b402f22048f63a9ee0876f06dda929d9af00923ef7550e60d7c8cc3f8`; Intake `0.2.4`/24/`1cf481a7834076b61c838a3e44d87db8d0efba8cf04793e4509e59a8dfa7e670`; Router `0.1.3`/12/`a1a2bcbd2f1f000eac07376a42eacbdc57092ec53577f1cd938b7e26d15fad7f`; Bridge `0.2.2`/9/`075878dc3628d5f6f26ce96e51ea328c0ce040ddf2b7036e3c18136029d979b3`; and Sponsorships `0.1.28`/18/`a1ec835bc577d86955ea010789319a9edd638e1d79bdb731e837a6276a9e47f5`.

Canonical BVM remains `1.2.0`, 384 files/tree `268e1e956bf8b92990cda5b82f7debee4352032a2815ad71a3f16e45a609c54f`. Its provider contract, core load, Event Command Center, and Vendor Portal are mirror/live byte-identical at SHA-256 `6ce14e7d600abd982e8ca3a0ea004b426e68fdd51ac1b35404bfffb3a8e2a686`, `aa344226ff63799140ea5e2fd152c0b41c262075a29cc4829fb529afb711dc78`, `c56accb8ba26b4757c93b867f454ef4b7bbc791a846eeb4264c8817d47ea57b5`, and `e88051421dd0fbf6bbe30f9bf0822ab4f9b51b0b871ba3392a5caa4d0836403e`. Accepted P0 staffing/ticket source hashes and all focused tests remain unchanged and green.

Normal activation finishes at the same 34-entry, 1,760-byte serialization and contains exactly one canonical active Data Tools entry. No legacy VMS entry became active. No plugin activation, Data Tools cron hook, Square configuration, capability, Action Scheduler inventory, or business data changed. Promotion and final acceptance attempted no HTTP; the disposable matrices' deliberate `.invalid` calls were intercepted before transport, and their only direct web requests were loopback Local process-boundary probes.

## Y–Z. Rollback readiness, Git, and boundary

Rollback is immediately available by first preserving forensic evidence, then atomically moving installed `0.5.55` out and the direct exact `0.5.54` tree back under the canonical basename. The archive independently reconstructs the same exact predecessor. Database restore is unnecessary for the successful promotion; if later forensic comparison identifies a task-created Data Tools mutation, the targeted SQL receipts permit only that verified state to be restored without overwriting concurrent WordPress changes.

Final preflight returned only the same expected dirty/untracked-tree warning; source hashes, self-diff review, PHP/shell syntax, and `git diff --check` passed. Branch, HEAD, upstream relation, and protected stash identity remain unchanged; the index remains empty and no commit was created. Existing P0/Waves/unrelated dirty work remains intact. Task-owned repository changes are the Data Tools normal-local runner and wrapper, the current-version test/source descriptions, this report, the harness note, and the remediation-ledger entry. Active runtime source changed only by installing the exact accepted Data Tools tree; legacy VMS and every other add-on source were untouched.

The work stayed local. There was no staging/production access, SSH, remote WP-CLI, external HTTP escape, deployment, upload, package/ZIP/tag/release, WordPress.org/reviewer action, communication, payment/business action, commit, push, pull, fetch, `ls-remote`, checkout, reset, rebase, or stash operation.

Evidence root: `/Users/treyconey/Local Sites/serenade-range-local-test-site/app/bvm-local-rollbacks/wave3b2-20260906T125640Z/data-tools`.
