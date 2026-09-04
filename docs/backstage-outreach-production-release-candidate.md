# Backstage Outreach Production Release Candidate

Date: 2026-09-03

Status: immutable local release candidate. Production and staging were not accessed or modified.

## Source lineage and isolation

- Baseline: `ec9934131a951faae2ac4250915cb8bb2683cdfe`
- Release branch: `release/backstage-outreach-1.0.0`
- Isolated worktree: `/private/tmp/bvm-backstage-outreach-release.WicaAh/repo`
- BVM prerequisite commit: `b9838902479165007b686be8647ccdd938d06dc9`
- Outreach runtime commit: `553b9d31565b748bc2f5d24ccb32bfd7b3fe13a6`
- Original dirty-worktree status fingerprint: `618bbe1952d2c0e072a15fdbf24046746b21f78392801828dd667777c834f520`
- Original dirty-worktree content fingerprint: `8bb9a88391c9221e969348bf1d9aa6e72ccdd91abd6034190c0de10c395be4ac`
- Protected stash commit/tree: `d08e726804712dc233f0e37b217abd6389963863` / `10e460ae3da87bd32fbc633e100c6d2fef7eda3d`

The first runtime commit contains only `includes/admin-ui/shell.php` and `includes/modules/admissions/pass-claims.php`. The second contains only the 14 files below `companion-plugins/backstage-outreach/`. No other BVM runtime, add-on, compatibility-harness, legacy-VMS, test, documentation, or package file is present in either runtime commit.

## Accepted runtime identity

- `includes/admin-ui/shell.php`: `9c41808eca46ff43908db80f62049de2ffb4474b3423c9201363ad4adaacedc8`
- `includes/modules/admissions/pass-claims.php`: `ebcf893380bea92a14ffb40de3606140eab8b4033eb8eb9b89430c10d992d436`
- Backstage Outreach: exactly 14 files; canonical sorted-manifest SHA-256 `84f6e7cbe4e4b3c5dcc7c5b3641a03be0f7fd4dbd89e707278a803e548b7b0f2`

These values match the staging-accepted source bytes and were reproduced from the isolated commits and both extracted release artifacts.

## Focused verification

PHP `8.3.33` produced the accepted results:

- bootstrap/data: `10` assertions
- legacy conflict guard: `3` assertions
- recovery/security: `55` assertions
- BVM integration: `34` assertions
- stabilization: `37` assertions
- nonce DOM/security: `220` assertions
- nonce-input normalization, authorization boundaries, Admissions REST permissions, runtime stubs, Admissions read-only request state, Admissions REST patch/restore, Admissions JavaScript normalization, and maintained public claimed-card/shell/status/success regressions: pass
- all 13 runtime PHP files and all seven evidence PHP files: lint pass
- Outreach JavaScript: syntax pass
- `git diff --check`: pass

Two broader historical controls are not release-candidate failures and were not repaired here. `tests/admissions-claim-state-query-boundaries-remediation.php` contains a stale pre-prefix G9 inventory unrelated to Outreach. `tests/pass-claims-public-form-output-remediation.php` requires the hard-coded excluded `../../vms` sibling and fails against that intentionally untouched legacy tree. Pulling the adjacent prefix/legacy synchronization campaign into this release would violate the exact-payload boundary.

The accepted compatibility reports remain unchanged and available:

- official-five: overall `PASS`, 18/18 scenarios, JSON SHA-256 `9cffb605f68578e15c628c2ffa756ab37c7b25d5ddca1c04899b9e10c6a52ca9`
- additional suite: overall `PASS`, 49/49 scenarios, JSON SHA-256 `ecbb247ce04b7c41177e6c4921196c79286b2ea65d0f70fe7a68457324f73d1a`

The clean runtime branch intentionally does not import the uncommitted compatibility-harness remediation used to produce those reports.

## Immutable artifacts

Artifact directory: `/private/tmp/bvm-backstage-outreach-release.WicaAh/repo/dist/backstage-outreach-production-rc-553b9d31565b`

- `backstage-venue-manager-outreach-prerequisites-b983890.zip`: 28,504 bytes; SHA-256 `13c70d2c90b56698f5e70eb786d290661acb99b62184e56c1e3e7401608ba921`; exactly two files at their BVM-relative destinations.
- `backstage-outreach-1.0.0-553b9d3.zip`: 133,584 bytes; SHA-256 `7f10c8218289b5f3fee8e8ebe96acf329bedbcd3bc40faf1eb2b29a07eb042c6`; exactly 14 files below `backstage-outreach/`.

Independent rebuilds from the two runtime commits were byte-identical to both ZIPs. Disposable extraction proved 2/2 BVM hashes, the exact 14-file Outreach tree hash, expected file/directory modes, no prohibited metadata or junk, and `16/16 MATCH` against the original staging-accepted source files.

## Evidence isolation and boundary

The evidence commit contains only the six Outreach-specific test files, the one directly adjusted Pass Claims public-status assertion, the recovery/local-acceptance/production-plan reports, this release-candidate report, and this task's remediation-ledger entry. Existing detailed Outreach ledger additions remain in the original accumulated worktree rather than importing adjacent uncommitted compatibility-campaign ledger changes.

The ignored release directory contains the deployable ZIPs, disposable build/extraction evidence, and the final machine-readable/human-readable release manifests. It does not enter either runtime commit or the production payload.

No production or staging connection, backup, upload, file or database write, activation, cache/cron change, queue operation, email, tag, force push, rebase, history rewrite, or protected-stash operation occurred.
