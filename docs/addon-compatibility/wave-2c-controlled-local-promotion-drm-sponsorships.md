# BVM Wave 2C — Controlled Local Promotion of DRM Bridge and Sponsorships

Date: 2026-09-05 (work completed 2026-09-06 UTC)

Scope: normal-local promotion only. DRM Events Bridge was promoted from 0.2.1
to 0.2.2, then Sponsorships was promoted from 0.1.27 to 0.1.28. The second
promotion authorized only the exact, pre-recorded `maybe_upgrade()` mutation.

## A. Preflight

- Required preflight passed on `work/unreleased-2026-06-18` at
  `79da784f5bfcc66bd058c0a7f54e08d7b15bb5d5`, using local refs only at
  upstream ahead/behind `0/0`, with an empty index and only the accepted dirty
  baseline.
- Protected stash object
  `d08e726804712dc233f0e37b217abd6389963863` remained present and untouched.
- Normal `active_plugins` contained 34 entries. Its exact serialized value was
  1,760 bytes at SHA-256
  `350ea69718f7946020940a98d081b061225c76a720f4fdcda9287403781b3afe`;
  sorted emitted JSON was
  `f3063de78a9b7cb3f8ca360d4246d5b6c3e6b94a271dc782bc646d3d33098479`.
- The accepted Wave 2B source-authority test passed all 21 assertions before
  either promotion. DRM candidate commit/tree/manifest were
  `b1efcc974233a3b43c2a9efa30533c6688f87320`,
  `3e2c1e49411c6811065f7f8eacca8fdaf736ed60`, and
  `075878dc3628d5f6f26ce96e51ea328c0ce040ddf2b7036e3c18136029d979b3`.
  Sponsorships candidate manifest was
  `a1ec835bc577d86955ea010789319a9edd638e1d79bdb731e837a6276a9e47f5`.

## B. DRM 0.2.1 rollback archive

- The complete nine-file dirty Git worktree was captured outside web root at
  `app/bvm-local-rollbacks/wave2c-20260906T023120Z/drm-events-bridge/drm-events-bridge-0.2.1-complete.tgz`.
- Archive: 93,005 bytes, mode `0600`, SHA-256
  `5ef4a81d9ff5cfaa650b13b5ffcff0af96637aa70e7259ca8d8753433d57af94`.
  Listing/read verification passed. The old worktree is also retained directly,
  including `.git`, as `drm-events-bridge-0.2.1-preserved-tree`.

## C. DRM pre-promotion validation

- The Bridge pure suite passed 56 assertions, the legacy-admin guard passed,
  and Router public/storage contracts passed 42 assertions with one read and
  zero writes.
- Hardened contained additional compatibility passed 49/49 while preserving
  exact normal-state, option, and table manifests
  `301421e1b26b7b86ee6438c9daf3042b4a596a41d395789a798b0bfed8feb30f`,
  `706a12057c41b4f40292f414b273794f99f0e229fb4edc1f7359857183748722`,
  and `705fca27c697c88967407c2aa1829b4cae955a7f807ef793d64d2047f321323d`.

## D. DRM replacement method

- The exact candidate was prepared and verified outside plugin discovery. The
  complete old active directory was atomically renamed to the outside-web-root
  rollback area, and the prepared candidate was atomically renamed into the
  unchanged `drm-events-bridge` basename.
- No deactivate/reactivate was needed or performed. No unrelated plugin source
  or activation state was changed, and no mixed-version directory existed.

## E. DRM before/after source

- Before: version 0.2.1, nine-file normalized manifest
  `10ba884e93d511e546a475cbfab8155c590020c489f618494ee95692bb680278`.
- After: version 0.2.2, exact nine-file accepted manifest
  `075878dc3628d5f6f26ce96e51ea328c0ce040ddf2b7036e3c18136029d979b3`.
- Final basename remained `drm-events-bridge/drm-events-bridge.php`.

## F. DRM database and activation effects

- The successful replacement changed neither `active_plugins` nor normal-local
  database state. No activation hook ran.
- The Bridge-owned `drm_events_bridge_vendor_map` option remained exact at
  manifest SHA-256
  `ffa045582a47e80cc474f91050c5d3233c28ba10c81aa2884739ed8776b2c844`.
- Two conservative acceptance probes initially made incorrect test assumptions
  (the Weather constant name, then non-admin Command Center loading/REST server
  initialization). Each triggered the planned source rollback. Incidental
  operational options written by a full WordPress boot—three transient pairs,
  the bounded BVM resource fingerprint log, and associated option-table
  sequencing—were restored from exact local binlog before-images. Source was
  then retried only after normal state matched its baseline byte-for-byte.

## G. DRM acceptance

- The corrected normal-local probe verified Bridge 0.2.2, Intake 0.2.4, Router
  0.1.3, one REST registration hook, one provider filter, one route endpoint,
  Router compatibility, BVM/Event Plan/Command Center health, and pre-transport
  blocking of `.invalid` HTTP.
- Post-promotion Bridge, legacy-guard, Router, and contained 49/49 suites passed.
  Final classification: **ACTIVE / CANONICAL / ACCEPTED LOCALLY**.

## H. Sponsorships 0.1.27 rollback receipts

- Complete 17-file source archive:
  `app/bvm-local-rollbacks/wave2c-20260906T023120Z/sponsorships/vms-sponsorships-0.1.27-complete.tgz`,
  91,421 bytes, mode `0600`, SHA-256
  `97bf698342b811be391a15498b79adf62a27ec560b5f75504346f5be079cb890`.
  Its listing passed, and the direct old source remains as
  `vms-sponsorships-0.1.27-preserved-tree`.
- Exact 27-option backup: `sponsorship-options-backup.json`, 7,045 bytes,
  mode `0600`, SHA-256
  `055d753d684126d4d3217acda50033909a36a15c0905f090d731f2b0aa924a31`.
- Exact six-table schema/data backup: `sponsorship-tables-backup.sql`, 25,050
  bytes, mode `0600`, SHA-256
  `d16ffa58fb44dd5236fd704133b3ce7b4e69c760268679a90e7c96731f28dd54`.
- Baseline rows were packages 5, applications 3, assignments 8, assets 3,
  metrics 16, and fulfillment items 28. Schema, checksums, auto-increments,
  16 Event Plan metadata rows, 1,538 Event Plans, roles/capabilities, cron,
  hooks, and all nine shortcodes were receipted.

## I. Expected Sponsorships mutation

- Source inspection showed no missing default package, no refresh-eligible
  legacy package, no missing default option, and no eligible banner migration.
- The complete expected mutation was only option `vms_sponsorships_version`:
  `0.1.27` to `0.1.28`, preserving its option ID and autoload setting.
  Table schema/rows, every other Sponsorships option, roles/capabilities, cron,
  Event Plan metadata, and `active_plugins` had to remain unchanged.

## J. Sponsorships pre-promotion migration tests

- The exact six-table SQL and all 27 exact options were restored into a fresh
  disposable database. Requiring only the candidate and calling
  `VMS_Sponsorships::instance()` exercised `maybe_upgrade()` without activation.
- First load changed only the version option. All schema, rows, checksums,
  auto-increments, other options, and activation state were unchanged. A second
  load was byte-identical and therefore idempotent. The comparison passed with
  SHA-256
  `c64512215e609a2c0cd5deb2ad3cbab25927ea3c157421707e074ae55bfc5621`.
- External HTTP was blocked, the disposable database was dropped, runtime
  residue was removed, and normal-local manifests remained exact.

## K. Sponsorships replacement method

- The complete old active directory was atomically moved to the rollback area,
  then the independently prepared and reverified candidate was atomically moved
  into `vms-sponsorships`. The basename stayed
  `vms-sponsorships/vms-sponsorships.php`; no deactivate/reactivate occurred.
- A minimal `wp --skip-plugins --skip-themes` load required only the active
  plugin and called its singleton to run `maybe_upgrade()`. No activation hook
  or unrelated plugin boot was used.

## L. Actual Sponsorships mutation

- The first controlled load changed only `vms_sponsorships_version` from
  `0.1.27` to `0.1.28`; option ID and autoload were preserved. A second load
  was idempotent.
- At whole-normal-state scope, the only option-manifest difference was that
  version option and the only table checksum difference was its containing
  `wp_options` table. Post-upgrade state hashes were normal
  `f650f3f0fbf178d9bf0b85abf672a3a3e725a41ebbd654cedfe5410b0c19850b`,
  options
  `6314ce869910cb253c4f36adbc5e1e50b594cadcb3c06d6233330970c4d0d5c8`,
  and tables
  `84231158b083d2aed779d0503e0e010355363b34afeec2a8f0caa57c6b72df02`.

## M. Expected versus actual

- `normal-migration-comparison.json` and
  `normal-full-state-mutation-comparison.json` both record `passed: true`.
  Actual behavior exactly matched the pre-promotion receipt; no Sponsorships
  business row, schema, Event Plan metadata, capability, cron, or activation
  state changed.

## N. Sponsorships acceptance

- Normal-local acceptance passed 0.1.28 source/version, the complete version
  stack, BVM/Event Plan/Command Center health, all nine shortcodes, singular
  registry/Event Plan/dead-hook attachments, all seven canonical registry
  entries, and no duplicate physical menus. Contained acceptance supplied the
  final registry/menu proof.
- Two admin-context probes caused only unrelated WordPress operational
  bookkeeping: expired transient cleanup and an Action Scheduler lock. The
  exact prior IDs/values and option-table sequencing were restored from local
  binlog before-images after each probe. Final state again matched the immediate
  post-upgrade hashes above; no Sponsorships/business state was affected.
- Final classification: **ACTIVE / CANONICAL / ACCEPTED LOCALLY**.

## O. Activation state

- `active_plugins` remained the exact same 34-entry serialization before and
  after both source replacements: 1,760 bytes, SHA-256
  `350ea69718f7946020940a98d081b061225c76a720f4fdcda9287403781b3afe`;
  sorted JSON SHA-256
  `f3063de78a9b7cb3f8ca360d4246d5b6c3e6b94a271dc782bc646d3d33098479`.
- The pre-existing stale `vms/backstage-venue-manager.php` activation entry was
  observed but deliberately left untouched; the sibling legacy tree and all
  other plugin activation entries were not altered.

## P. Combined compatibility

- Post-promotion official-five passed 18/18 at
  `/var/folders/33/ltvj2kb927dcmnpdb1x8qd0h0000gn/T/bvm-addon-compat-report.AMnJcm`.
- Post-promotion additional ecosystem passed 49/49 at
  `/var/folders/33/ltvj2kb927dcmnpdb1x8qd0h0000gn/T/bvm-addon-compat-report.vhwKHA`.
- Focused P0 staffing/ticketing, Commerce business, Weather Command Center and
  provider/persistence, DRM/Router, legacy integration, and source-consistency
  suites passed. One extra repository-SQL test retains a pre-existing hardcoded
  legacy-tree assumption: the legacy sibling omits a ticketing symbol present
  in both canonical BVM trees. It was not introduced or changed by Wave 2C.

## Q. Containment proof

- Failure-path self-test passed at
  `/var/folders/33/ltvj2kb927dcmnpdb1x8qd0h0000gn/T/bvm-test-containment-self-test.ZRtEnR`.
- Both final matrices report database/runtime cleanup, blocked external HTTP,
  process-boundary enforcement, residue removal and absence, guard cleanup,
  lock release, and exact preservation of the post-upgrade normal, option, and
  table hashes. No test guard or disposable runtime/database remains.

## R. Weather, Commerce, and Data Tools preservation

- Weather remains active/accepted at 0.1.12 with its accepted 35-file tree and
  one expected cron. Commerce remains active/canonical at 0.2.13 with installed
  tree SHA-256
  `03bcb58b402f22048f63a9ee0876f06dda929d9af00923ef7550e60d7c8cc3f8`;
  its temporary 0.2.9 tree remains installed and inactive. Data Tools remains
  0.5.54. Their source/activation state was not changed.

## S. BVM P0 preservation

- Mirror, canonical active BVM, and the accepted hashes remain exact for Event
  Command Center
  `5349ad16c626043072dbd29334d131c4ba531c03fe8e10e8617521afc53c63d4`,
  profitability
  `e6f2f6989b7d7d4174d1a5f3c93719c75fafa80f627322aa93e830d81135d55a`,
  staffing
  `2c1a90986e409f1f5f44c52d724993b6be89f4b514b68daaed90185b6c7a45e7`,
  Event Plans
  `1c505140b509c5f53954beb06a8d40d0774173931305e3fd058463a24ccc5500`,
  and Pass Claims
  `ebcf893380bea92a14ffb40de3606140eab8b4033eb8eb9b89430c10d992d436`.
- P0 source consistency and relevant staffing/ticket behavior suites passed.

## T. Source and database changes

- Normal runtime source changes are confined to the two active plugin trees:
  DRM 0.2.2 and Sponsorships 0.1.28. Old trees and evidence were added only
  under the outside-web-root Wave 2C rollback directory.
- Repository work is confined to Wave 2B/2C containment tests and harness
  documentation: normal-process telemetry/preservation filters, the optional
  preserved-state harness controls, the Bridge repository override, this audit
  document, the ledger, and the current-shape Sponsorships migration test.
- The only retained normal database mutation is the authorized Sponsorships
  version option transition. Incidental operational writes from rejected
  acceptance probes were exactly restored; no business data was changed.

## U. Rollback readiness

- Both verified archives and both directly preserved old trees remain outside
  web root. DRM rollback is an atomic replacement with the preserved 0.2.1
  tree. Sponsorships rollback is an atomic replacement with the preserved
  0.1.27 tree plus restoration of only the version option from the verified
  option receipt if necessary.
- The six-table and 27-option Sponsorships backups are retained for targeted
  recovery. No rollback material was deleted.

## V. Git closeout

- This task made no commit. The index remained empty, the branch and HEAD were
  unchanged, local-ref upstream relation remained 0/0, and final
  `git diff --check` passed. The dirty worktree is expected and includes work
  accepted by earlier tasks; the final status/diff was inspected rather than
  normalized or stashed.

## W. Protected and unrelated work

- The protected stash was not applied, popped, dropped, rewritten, or recreated.
  Existing P0, Wave 1, Wave 2A/2B, Outreach, inactive legacy VMS, dormant
  plugin directories, rollback archives, and unrelated dirty work were
  preserved. No destructive Git operation was used.

## X. Environment boundary

- Work was local only. No staging/production access, SSH, remote WP-CLI,
  deployment, upload, provider/API mutation, communication, email, payment,
  real order/customer/event/ticket/admission/staffing mutation, package/ZIP/tag,
  WordPress.org/reviewer action, commit, push, pull, fetch, `ls-remote`,
  checkout, reset, rebase, or stash operation occurred.
- Deliberate web proof was limited to Local loopback. `.invalid` calls were
  intercepted before transport.

## Final classifications

- DRM Bridge: **ACTIVE / CANONICAL / ACCEPTED LOCALLY**
- Sponsorships: **ACTIVE / CANONICAL / ACCEPTED LOCALLY**
