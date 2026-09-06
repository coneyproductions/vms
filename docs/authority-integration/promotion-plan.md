# Future controlled normal-local promotion — plan only

This task performs no promotion. The target source will be the exact green integration commit and its preserved Financial/Staffing ancestry, never the original dirty development branch. Normal installed plugins are deployment copies, not Git worktrees.

1. Record fresh explicit promotion authorization, exact accepted source commit, target basename/path and companion versions. Read the latest orphan-forensics receipt. This document never authorizes promotion by itself.
2. Freeze staffing/date/time/reschedule writers for a consistent gate and subsequent window. From the candidate checkout, run the standalone **read-only** CLI below against the local Unix socket. It loads no WordPress configuration, plugins, MU plugins, themes, activation hooks or migration runner. Never replace source merely to obtain the preflight function.
3. Require process exit `0` AND JSON `ok: true`. Any orphan, active duplicate, invalid confirmed window, overlapping commitment, incompatible lifecycle addition, non-InnoDB/missing transaction table, SQL failure or malformed receipt stops the workflow **before backup/source replacement/migration**. Inactive duplicate history remains evidence and is preserved. A missing additive column/index means `lifecycle_migration_required`, which is allowed only when every data/engine gate passes. Unresolved tentative windows and broader unassigned-slot/rollup history still require the separately documented operator review; this narrowly scoped gate does not certify all historical site data.
4. Only after the gate passes, preserve exact installed source and targeted schema/data/options/auto-increment before-images outside webroot. Verify restoration in a disposable fixture. Capture activation, cron, companions and business-boundary hashes. Use a targeted rollback plan that cannot overwrite unrelated concurrent business activity; never rely on an unconditional full-site restore.
5. Under the same writer freeze, replace only the authorized canonical BVM source from the green integration commit. Record the manifest and source equivalence. Do not include companion changes, legacy VMS, docs/tests/Git, or deactivate/reactivate plugins by inference.
6. Separately invoke the explicit `bvmgr_staffing_migrate_lifecycle()` as the authorized administrator under the approved migration window. It independently repeats the shared gate under the staffing advisory lock **before any ALTER** and returns `preflight_blocked` without DDL/DML if the data has become invalid. Inspect `ok` and STOP on failure. Never put preflight and migration in one unconditional command block; shell `set -e` alone cannot interpret successful `wp eval` output containing a blocked JSON receipt. This task does not supply an automatic promotion command.
7. Run post-migration acceptance and an explicit second migration for idempotence. Verify exactly three additive columns and two indexes, default-zero revisions, nullable historical audit identities, unchanged old-column rows/options, no orphan or active duplicate assignments, correct lifecycle/Financial/companion behavior, and complete test residue removal. Only then restore writer access. Retain the exact receipt and rollback evidence.

Read-only gate (parameters must be the verified local target; password is supplied through `MYSQL_PWD`, never in a checked-in script):

```sh
php scripts/staffing-lifecycle-preflight.php \
  --socket=/absolute/verified/local/mysql.sock \
  --database=local --prefix=wp_ --user=root > preflight.json
```

Run this as a standalone step and inspect both its exit code and receipt before doing anything else. The CLI targets the standard table suffixes; custom staffing suffix constants require a matching reviewed reader before promotion. Exit `1` means blocked data/schema; `2` means usage/connection/execution failure. Neither permits promotion. Do not run the candidate plugin bootstrap on normal Local during steps 1–3: generic BVM and companion on-load migrations/operational hooks are outside this SQL-only boundary. Lifecycle installation itself has no on-load or activation migration hook.

## Rollback constraints

If source acceptance fails, preserve migration/audit evidence and keep staffing writers stopped. Retain additive columns/indexes during source rollback; dropping them destroys provenance and cannot be justified by a file rollback. The prior matrix writer restores known lifecycle gaps, so a code-only rollback is not a safe resumption policy. Prefer a compatible forward fix. Any database restore must deliberately account for post-backup business/audit writes; do not overwrite them with a broad restore by default. Rollback execution needs its own exact approved target/scope within the future promotion task.
