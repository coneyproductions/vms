# Wave 4 accepted local reconciliation checkpoint

Date: 2026-09-06

## Scope and safety

This local-only checkpoint reconstructs the accepted BVM and add-on state through Wave 3B-2 from the unchanged historical BVM parent `79da784f5bfcc66bd058c0a7f54e08d7b15bb5d5`. It does not imply a release or deployment. No remote Git operation, staging/production action, package, tag, activation change, or normal-local database mutation was used to construct it.

The original dirty BVM and Sponsorships worktrees were not checked out, reset, stashed, staged, or rewritten. The protected stash `WPORG-16D preserve unrelated sidebar+doc work` remained object `d08e726804712dc233f0e37b217abd6389963863`.

## Repository discovery

| Authority | Initial branch / HEAD | Local upstream relation | Initial worktree | Accepted authority |
| --- | --- | --- | --- | --- |
| BVM mirror | `work/unreleased-2026-06-18` / `79da784f5bfcc66bd058c0a7f54e08d7b15bb5d5` | `0/0` | dirty, empty index | reconstructed checkpoint branch |
| VMS Sponsorships | `main` / `86e911f42e0d242ecb022453e4753ece6cbc1a84` | `0/0` | five modified plus `includes/core-compat.php`, empty index | reconstructed 0.1.28 checkpoint branch |
| DRM Calendar Intake | `main` / `590f2ac40e346a5a1aae384a9e90b37d72b4cf80` | `0/0` | clean | existing accepted 0.2.4 commit |
| DRM Event Router | `main` / `72acfd22209cf51359e9485c3b7c8ae5c6fef3ba` | `0/4` | clean | immutable accepted 0.1.3 commit `21fadbd00e2ccebeb42ef8cb0334160af6e8b288` |
| DRM Events Bridge preserved Git tree | `main` / `7300161eba1bd053061192e0000812801b1aa4d2` | `0/3` | six modified, one deleted, two untracked; empty index | immutable accepted 0.2.2 commit `b1efcc974233a3b43c2a9efa30533c6688f87320` |

The `.git` entry under `plugins/g14-reconstruct/packages/vms-github-reconcile` is a clean detached linked worktree of the BVM repository, not an independent authority. The accepted Weather, Commerce, Calendar Feeds, and Data Tools directories under `companion-plugins/` are source mirrors owned by the BVM repository. Normal installed plugin directories are deployment copies. The active Bridge directory is a deployment copy; its Git authority remains the accepted commit in the preserved repository. Local-config remote URLs were inspected without contacting any remote.

## Reconstructed campaign-start baseline

The first read-only ecosystem audit recorded exactly 19 modified paths, 12 top-level untracked paths expanding to 60 files, an empty index, status fingerprint `6b4b613a89b371def543ec10c02c5e977d9427851b65e1a08064a47b14349dbe`, tracked-diff fingerprint `bedf4fae75993f81f438cf041a2f2fa63ae1b88ac4f0135b2d706cd9ac7c9c54`, and untracked-content fingerprint `3e6b77fe6faf7e5f83c098f9301948a1ced28721bb866eabed7ac39f01e01a04`.

The 19 modified paths were:

- `docs/wporg-remediation-ledger.md`
- `includes/admin-ui/shell.php`
- `includes/modules/admissions/pass-claims.php`
- `release-public-excludes.txt`
- `scripts/test-bvm-additional-runtime-compatibility.sh`
- `scripts/test-bvm-addon-runtime-compatibility.sh`
- `tests/addon-compatibility/additional-build-report.php`
- `tests/addon-compatibility/additional-runtime-contracts-test.php`
- `tests/addon-compatibility/additional-runtime-contracts.php`
- `tests/addon-compatibility/additional-runtime-probe.php`
- `tests/addon-compatibility/additional-source-manifest.php`
- `tests/addon-compatibility/build-report.php`
- `tests/addon-compatibility/runtime-contracts-test.php`
- `tests/addon-compatibility/runtime-contracts.php`
- `tests/addon-compatibility/runtime-probe.php`
- `tests/addon-compatibility/source-manifest.php`
- `tests/fill-dates-admin-notice-placement.php`
- `tests/fill-dates-menu-hook-compatibility.php`
- `tests/pass-claims-public-status-output-remediation.php`

The 60 untracked files comprised 14 files under `companion-plugins/backstage-outreach/`, 35 files under `companion-plugins/vmsx-weather-risk/`, three Outreach/Guest Pass documents, `tests/additional-suite-targeted-remediation.php`, six Outreach tests, and `tests/fill-dates-canonical-bvm-compatibility.php`.

## Classification and hunk resolution

### A — accepted reconciliation work

- BVM runtime: `includes/admin/event-command-center.php`, `includes/admin/event-profitability-report.php`, `includes/core/load.php`, `includes/core/staffing.php`, `includes/cpt/event-plans.php`, `includes/portal/vendor-portal.php`, and new `includes/core/reporting-providers.php`.
- Accepted modified tests: `tests/addon-compatibility/commerce-square-activation-regression.php`; the three `tests/event-plan-*-remediation.php` paths in the checkpoint diff; four `tests/staffing*-remediation.php` paths; `tests/ticketing-{claims,core}-repository-sql-remediation.php`; and `tests/vendor-portal-bonus-progress-paid-basis.php`.
- Accepted source trees: `companion-plugins/vms-commerce-discounts/` (27 files), `companion-plugins/backstage-calendar-feeds/` (17 files), and `companion-plugins/vms-data-tools/` (81 files).
- Accepted new infrastructure and tests: `scripts/lib/bvm-test-containment.sh`, the containment self-test, the Data Tools normal-local runner, the Sponsorships current-shape migration wrapper, and the 13 new non-baseline test files retained by this checkpoint.
- Sponsorships: `README.md`, `docs/CHANGELOG.md`, `includes/class-vms-sponsorships-admin.php`, `includes/class-vms-sponsorships-shortcodes.php`, `includes/core-compat.php`, and `vms-sponsorships.php` in its separate repository.

### B — pre-existing unrelated work, excluded

Tracked modifications excluded in full:

- `includes/admin-ui/shell.php`
- `includes/modules/admissions/pass-claims.php`
- `tests/fill-dates-admin-notice-placement.php`
- `tests/fill-dates-menu-hook-compatibility.php`
- `tests/pass-claims-public-status-output-remediation.php`

Untracked paths excluded in full:

- all 14 files under `companion-plugins/backstage-outreach/`
- `docs/backstage-outreach-production-deployment-plan.md`
- `docs/guest-pass-outreach-local-acceptance.md`
- `docs/guest-pass-outreach-recovery.md`
- `tests/backstage-outreach-bootstrap.php`
- `tests/backstage-outreach-bvm-integration.php`
- `tests/backstage-outreach-legacy-guard.php`
- `tests/backstage-outreach-local-stabilization.php`
- `tests/backstage-outreach-nonce-dom.php`
- `tests/backstage-outreach-recovery.php`
- `tests/fill-dates-canonical-bvm-compatibility.php`

### C — pre-existing work later accepted or incorporated

- all 35 files of the Weather 0.1.12 candidate, whose exact source was promoted and accepted in Wave 2A
- `tests/additional-suite-targeted-remediation.php`
- `release-public-excludes.txt`
- both compatibility shell harnesses and the ten pre-existing modified compatibility report/contract/probe/manifest files; their final forms became the accepted 19/52 matrix authority and were repeatedly revalidated through Wave 3B-2

### D — durable evidence versioned here

- the accepted-only ledger tail beginning at `BVM P0 Source Consistency Repair`; earlier dirty ledger additions were not copied
- `docs/addon-compatibility/bvm-only-runtime-harness.md`
- the five Wave 2B through Wave 3B-2 reports
- `docs/bvm-staffing-lifecycle-follow-up.md`
- this checkpoint report

### E — local/generated artifacts excluded

All `app/bvm-local-rollbacks/` contents, OS-temporary reports/databases, acceptance evidence, generated archives, the linked source adapters used for out-of-tree tests, installed runtime trees, logs, caches, tokens, and browser/session artifacts remain outside Git.

### F — ambiguous

None. The mixed ledger file was resolved at hunk level by applying only the accepted P0-through-3B-2 tail to the historical committed ledger. No other included file required inseparable accepted/unrelated hunk attribution.

## Checkpoint content

Before this report, the BVM checkpoint contained 217 paths relative to its parent: 32 tracked modifications and 185 additions. The additions comprise 160 accepted companion-source files, five Wave reports, the staffing lifecycle document, one provider source, four maintained scripts, and 14 maintained tests. This report is the 218th path in the checkpoint diff.

No Bridge or Router source was duplicated into the BVM repository. Their exact accepted Git commits are separate authorities. Intake likewise remains in its clean separate repository. This avoids a fake monorepo commit.

## Source equivalence

| Component | Files | Normalized SHA-256 | Result |
| --- | ---: | --- | --- |
| Weather 0.1.12 | 35 | `e019619ce1bd5bef851dbdb4573fd527332983cda34004bd5fc531ae50cc5c0b` | checkpoint = active runtime |
| Commerce 0.2.13 | 27 | `03bcb58b402f22048f63a9ee0876f06dda929d9af00923ef7550e60d7c8cc3f8` | checkpoint = active runtime |
| Calendar Feeds 0.1.4 | 17 | `20f2607ec61c62f2d7120cd99b657b46e15bc169f6d92d14f7951540e33fc20e` | checkpoint = active runtime |
| Data Tools 0.5.55 | 81 | `8e36b9b6cf1e337627a86a782f2eafc49681d09b8711665e54ab78d6b005988b` | checkpoint = active runtime |
| Sponsorships 0.1.28 | 18 | `a1ec835bc577d86955ea010789319a9edd638e1d79bdb731e837a6276a9e47f5` | separate checkpoint = active runtime |
| Calendar Intake 0.2.4 | 24 | `1cf481a7834076b61c838a3e44d87db8d0efba8cf04793e4509e59a8dfa7e670` | clean accepted repository = active runtime |
| Event Router 0.1.3 | 12 | `a1a2bcbd2f1f000eac07376a42eacbdc57092ec53577f1cd938b7e26d15fad7f` | accepted commit = active runtime |
| Events Bridge 0.2.2 | 9 | `075878dc3628d5f6f26ce96e51ea328c0ce040ddf2b7036e3c18136029d979b3` | accepted commit = active runtime |

The nine accepted BVM authority files compared byte-for-byte with the active BVM runtime: Event Command Center `c56accb8…57b5`, profitability `e6f2f698…d55a`, core load `aa344226…c78`, reporting providers `6ce14e7d…a686`, staffing `2c1a9098…45e7`, ticket sales resolver `be4273c6…b9f`, ticket revenue `d8298e39…e035`, Event Plans `1c505140…5500`, and Vendor Portal `e8805142…403e`. The active shell and Pass Claims files intentionally differ because their pre-campaign Outreach work was excluded.

## Validation

- PHP 8.3.33 lint: 662 BVM/checkpoint PHP files and 12 Sponsorships PHP files passed.
- JavaScript syntax: all four checkpoint companion JavaScript files passed `node --check`.
- Shell syntax: all eight repository shell scripts passed `sh -n`.
- Focused source suites: 21/21 passed, including Calendar's 109 assertions, Router's 85 assertions, Bridge's 56 assertions, P0 staffing/ticketing, provider/Data Tools, Commerce, Weather, Intake, and Vendor Portal coverage.
- Sponsorships current-shape migration: two disposable loads passed; exact comparison and database cleanup passed.
- Containment forced-failure self-test: passed with database/runtime/residue/guard/lock cleanup and unchanged normal state.
- Official-five hardened matrix: 19/19 passed with blocked HTTP/process escape, cleanup, and unchanged normal activation/cron/Weather/database state.
- Additional hardened ecosystem: 52/52 passed with the same containment guarantees and an unchanged Bridge forensic worktree.
- Data Tools checkpoint candidate normal-local diagnostic: 154/154 passed; normal state and guard/lock cleanup passed.
- Sponsorships passes default `git diff --cached --check`. The BVM tracked diff passed before staging, but the full staged check exposes 13 inherited exact-source findings: 11 trailing/EOF whitespace findings in the accepted Data Tools/Weather trees and two Markdown hard-break lines in accepted Wave reports. Those bytes are preserved deliberately because changing them would break the accepted normalized source hashes. This checkpoint-authored report and all other newly authored/reconstructed changes are whitespace-clean.

All mutable tests used checkpoint source or disposable runtime/database state. Read-only installed paths were used only for immutable external dependency/archive evidence. Out-of-tree relative-path checks used generated symlink adapters outside both Git worktrees. An initial Sponsorships disposable run correctly rejected an expired copied containment token and cleaned its disposable state; a fresh temporary token then produced the recorded passing run. No normal-local option, table, activation, cron, capability, post, order, admission, staffing, or customer record was intentionally changed.

## Parallel-worktree boundary

The checkpoint is suitable for independent local worktrees after its commits and companion branch pointers are recorded. Staffing lifecycle, financial authority, remaining dormant/add-on inventory, and Event Command Center UX can be investigated in separate BVM worktrees. Staffing and financial work overlap Event Command Center/Event Plan authority files and therefore should not be merged blindly; dormant inventory is primarily read-only and can run fully in parallel; UX should consume the established provider/staffing contracts and avoid simultaneous normal-site mutation. Normal-local installed plugins remain deployment copies, not agent worktrees.
