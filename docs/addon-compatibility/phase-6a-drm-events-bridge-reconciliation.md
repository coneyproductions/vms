# BVM Add-on Compatibility Phase 6A — DRM Events Bridge Reconciliation

Date: 2026-08-31

## A. Baseline

The BVM compatibility repository started clean on branch `work/unreleased-2026-06-18` at `8833dd2fb9314fb9921e7680a62e47542da6057d`. The protected `stash@{0}` named `WPORG-16D preserve unrelated sidebar+doc work` was present and was not touched. Repository preflight passed.

The DRM Events Bridge repository remained on `main` at `7300161eba1bd053061192e0000812801b1aa4d2`. Its index was clean and its worktree had these nine dirty paths:

| Path | Worktree SHA-256 | State |
| --- | --- | --- |
| `AGENTS.md` | `58ee93c559fce1a9bef6c8b86f2ef4c6b89a497010d69c8f13e3febba0d0e6a8` | modified |
| `README.md` | `92e8bfc22e1238a1b02e18cb8cac47f398e12cedfb40af44d40c0cbeb5e54eba` | modified |
| `drm-events-bridge.php` | `05b02b79c1ccbe4cc38da70d4cab383012aa3320a1cf0c997a85daccb037d5e8` | modified; version 0.2.1 |
| `includes/admin.php` | `3576daf8a233dfaca8e3494719687c9fdbc4e686d3f6fecf28d017d378123639` | modified |
| `includes/registry.php` | `24659a286c388a88bd757ddb39b105d2956d084056d955eb4c6e7e55383e45ba` | modified |
| `includes/rest.php` | `6381891ee4adc4bd135d7a3b5f231f8c4e7c9dde30d2305fa0e2145103e4e643` | modified |
| `tests/status-policy-probe.php` | absent | deleted |
| `tests/legacy-admin-guard-probe.php` | `717eaf58ffc5edb80479644de8c7dcdc66c6a4061b34d0b02f705e2b8b2fd36f` | untracked |
| `tests/router-provider-probe.php` | `0f14528238e499abbda9640a7f15416e0c214f22a05648b21e1cee64767c26d2` | untracked |

The Bridge status fingerprint was `6cdc2a1cde71acbab832a682aa0fb3d6f784d6c51fdacec93d2cab55396126c6` before and after both runtime runs. No Bridge index or worktree file was changed.

## B. Remote and upstream reconciliation

- Local HEAD: `7300161eba1bd053061192e0000812801b1aa4d2`
- Local `origin/main`: `b1efcc974233a3b43c2a9efa30533c6688f87320`
- Actual `refs/heads/main` from a read-only `git ls-remote`: `b1efcc974233a3b43c2a9efa30533c6688f87320`
- Relationship: local HEAD is zero ahead and three commits behind remote main.
- Configured remote: `https://github.com/coneyproductions/drm-events-bridge.git`

The three intervening commits are:

| Commit | Date | Subject | Version | Changed files | Runtime relevance |
| --- | --- | --- | ---: | --- | --- |
| `12fa767c810443067bc267f9a37c722bc462871b` | 2026-08-14 | Use Router for public event feed | 0.2.0 | `AGENTS.md`, `README.md`, bootstrap, `admin.php`, `registry.php`, `rest.php`; add two probes; delete status-policy probe | Major architecture change: Router becomes authoritative, VMS becomes optional legacy/future enrichment, and missing Router fails closed. |
| `fc78595451e32e1e23cd243ce32306f81812e721` | 2026-08-15 | Expose trusted public venue fields | 0.2.1 | `README.md`, bootstrap, `rest.php`, Router probe | Adds bounded trusted public venue fields and advances the Router-backed contract. |
| `b1efcc974233a3b43c2a9efa30533c6688f87320` | 2026-08-29 | Expose event presentation in public contract | 0.2.2 | `AGENTS.md`, `README.md`, bootstrap, `rest.php`, Router probe | Adds the exact `normal` / `private_event` presentation field and its fail-closed validation. This is the tested/deployed 0.2.2 release. |

## C. Dirty-work classification

Every dirty path is upstream-derived. There is no `UNIQUE-LOCAL` or `OVERLAPPING/CONFLICTING` content.

| Path | Classification against HEAD and remote main |
| --- | --- |
| `AGENTS.md` | `UPSTREAM-EQUIVALENT` to the 0.2.0/0.2.1 line; `STALE` versus 0.2.2; `DOCUMENTATION-ONLY`. |
| `README.md` | `UPSTREAM-EQUIVALENT` to 0.2.1; `STALE` versus 0.2.2; `DOCUMENTATION-ONLY`. |
| `drm-events-bridge.php` | `UPSTREAM-EQUIVALENT` to 0.2.1; `STALE` version marker versus 0.2.2. |
| `includes/admin.php` | `UPSTREAM-EQUIVALENT` to 0.2.0 and byte-identical to 0.2.2. |
| `includes/registry.php` | `UPSTREAM-EQUIVALENT` to 0.2.0 and byte-identical to 0.2.2. |
| `includes/rest.php` | `UPSTREAM-EQUIVALENT` to 0.2.1; `STALE` versus the 0.2.2 presentation contract. |
| deleted `tests/status-policy-probe.php` | `UPSTREAM-EQUIVALENT` to 0.2.0 and 0.2.2; `TEST-ONLY`. |
| `tests/legacy-admin-guard-probe.php` | `UPSTREAM-EQUIVALENT` to 0.2.0 and byte-identical to 0.2.2; `TEST-ONLY`. |
| `tests/router-provider-probe.php` | `UPSTREAM-EQUIVALENT` to 0.2.1; `STALE` versus 0.2.2; `TEST-ONLY`. |

Taken together, the dirty worktree is an exact upstream 0.2.1 source snapshot represented by clean commit `fc78595451e32e1e23cd243ce32306f81812e721`, not unique development work.

## D. Deployment state

### Production

Fresh authenticated, read-only WordPress plugin-editor inspection showed DRM Events Bridge active at version 0.2.2. All eight editor-visible files matched commit `b1efcc9` byte-for-byte. WordPress omits `.gitignore` from the editor; the preserved 0.2.2 deployment receipt verifies all nine release files, including that dotfile, against the same commit.

| Commit file | SHA-256 |
| --- | --- |
| `.gitignore` | `741cf4c60ecda05968aaa9c41043709f4b0cc68935eecb4e10475a729f779ee0` |
| `AGENTS.md` | `a00168e30cbd6c62135b729fd562aadd60537afb74d2f46f448d172b52965923` |
| `README.md` | `4cfe072c80c8eba28dfe878c32f5fc6dd38c036f176eb92c5c34ea4b5c2c1514` |
| `drm-events-bridge.php` | `90ded5e059fe0974d465ae9627f8b12d36154b2b83a384b85238d6767028efed` |
| `includes/admin.php` | `3576daf8a233dfaca8e3494719687c9fdbc4e686d3f6fecf28d017d378123639` |
| `includes/registry.php` | `24659a286c388a88bd757ddb39b105d2956d084056d955eb4c6e7e55383e45ba` |
| `includes/rest.php` | `0b93b3f774e1746034239987afb8deb5fe1ba81d90a7fe54703d43a82dc4171e` |
| `tests/legacy-admin-guard-probe.php` | `717eaf58ffc5edb80479644de8c7dcdc66c6a4061b34d0b02f705e2b8b2fd36f` |
| `tests/router-provider-probe.php` | `b74a924bf82b8135bcd26afda653449e8bb678ce388ffe4b6d7ad6058db59c5e` |

The normalized nine-file source manifest SHA-256 used by the compatibility harness is `075878dc3628d5f6f26ce96e51ea328c0ce040ddf2b7036e3c18136029d979b3`. No extra editor-visible source file was present. The current public endpoint returned HTTP 200 with 50 records, the exact 13-field 0.2.2 allowlist, 44 `normal`, six `private_event`, zero invalid shapes, and zero unsafe private records. No event payload is retained in this evidence.

### Staging

The preserved deployment receipt records the same exact 0.2.2 release package and commit on staging. A fresh authenticated filesystem/editor rehash was unavailable because the staging administrator endpoint required a new login; no credentials were transmitted. A fresh public endpoint check still returned HTTP 200 with the exact 13-field 0.2.2 contract, 50 records, the same 44/6 presentation split, zero invalid shapes, and zero unsafe private records.

Accordingly, production is a fresh `EXACT COMMIT MATCH` with the dotfile completed by the preserved 9/9 receipt. Staging remains an `EXACT PACKAGE MATCH` by its preserved deployment receipt, corroborated by a fresh runtime signature, rather than a newly completed authenticated byte rehash.

The known 0.2.2 release ZIP SHA-256 is `d1f536c199a4583ed4c3f7a189e615ba5667bd1d1e1e06c16d3c7eda354f30fd`. A reproducible `git archive --format=tar --prefix=drm-events-bridge/ b1efcc9` has SHA-256 `adc256a785ae9df4a99adc3fbe7e6e01d91c1665e3fa8ee5032b35592761b8cc`.

## E. Authoritative snapshot decision

Conclusion A applies: a clean authoritative snapshot is identified.

- Version: 0.2.2
- Commit: `b1efcc974233a3b43c2a9efa30533c6688f87320`
- Git tree: `3e2c1e49411c6811065f7f8eacca8fdaf736ed60`
- Bootstrap SHA-256: `90ded5e059fe0974d465ae9627f8b12d36154b2b83a384b85238d6767028efed`
- Evidence order: exact deployed bytes, clean committed release, preserved release/deployment receipts, and current remote main all agree.

The disposable harness extracted this commit with `git archive`. It never copied the dirty Bridge worktree.

The authoritative Router fixture was likewise extracted from production-matched commit `7a4232ee327e2b5385e257cd7d20bf48e19d67fd`, version 0.1.4, Git tree `a9c3fb75bb59b6b24426ea3a93a3b581c21aebba`, bootstrap SHA-256 `e5cb839c969756fafc04f1ec6e8ec1e1b658ef3f08b6fc7e3ea7ed90480d8372`.

## F. Runtime matrix

The Phase 6A mode extended the existing additional-first-party harness. It used WordPress 7.1, PHP 8.3.30, BVM 1.2.0, Calendar Intake 0.2.4, Router 0.1.4, Bridge 0.2.2, Commerce Discounts 0.2.12, exact existing companion snapshots, a unique disposable database, a temporary WordPress tree, and blocked external HTTP.

| Required scenario | Harness scenario | Result |
| --- | --- | --- |
| A — normal DRM architecture, BVM absent | `additional-core-absent-drm-events-bridge` | PASS |
| B — public BVM identity present | `additional-drm-events-bridge-public-bvm` | PASS |
| C — BVM-first lifecycle | `additional-drm-events-bridge-core-first` | PASS |
| D — provider/Bridge-first lifecycle | `additional-drm-events-bridge-addon-first` | PASS |
| E — missing Router | `additional-provider-absent-drm-events-bridge` | PASS; empty fail-closed feed |
| F — ecosystem coexistence, core first | `additional-coexistence-core-first` | PASS |
| F — ecosystem coexistence, add-ons first | `additional-coexistence-addons-first` | PASS |

The probe also passed the exact Router contract/version check, one synthetic 13-field mapping, strict presentation rejection, provider-exception fail-closed behavior, REST route uniqueness, provider-filter priority, Settings-menu uniqueness, BVM-absent initialization, and the absence of false Bridge/BVM dependency notices. No sync, posting, email, webhook, booking, or business mutation ran.

## G. BVM public-identity proof

- Active basename: `backstage-venue-manager/vendor-management-system.php`
- BVM version: 1.2.0
- `VMS_PLUGIN_FILE`: resolves to the public basename above
- `vms/vendor-management-system.php`: absent and inactive
- Other nonexistent bootstrap candidates: absent
- Bridge BVM detection: none; it requires neither public nor historical BVM basename. Three `vms_*` APIs are guarded optional enrichment helpers.

## H. DRM architecture answer

**NO**

DaleneRichelle.com does not require local BVM for DRM Events Bridge. Router contract v2 is the public-eligibility authority. Bridge sanitizes/adapts Router-approved candidates and fails closed when Router is missing, incompatible, throws, or returns no records. It does not fall back to BVM data.

BVM supplies only legacy/future optional enrichment UI/data: `vms_event_plan`, `vms_vendor`, `_vms_event_plan_status`, `_drm_publish_to_dalene_site`, the vendor-map option, and three guarded helper calls. Production also currently serves the Router-backed contract while local VMS/BVM is inactive.

The observed “VMS does not appear active” wording belongs to DRM Calendar Intake's quarantine/promotion advisory. It is advisory only, uses stale VMS branding, and would be architecturally misleading if interpreted as a Bridge public-feed dependency. It is not a Bridge dependency warning and was not changed.

## I. Functional defects

No Bridge/BVM functional defect was reproduced for 0.2.2. The first completed development run exposed a harness-only double `rest_api_init` call around `rest_get_server()`; correcting that probe lifecycle produced one Bridge route in every isolated scenario and retained one route in both coexistence cases.

## J. Dirty future-work disposition

The dirty local Bridge tree remains untouched. It contains no unique local work: it is the committed upstream 0.2.1 state represented by `fc78595`, now stale versus released 0.2.2. A later owner may reconcile that worktree deliberately, but this task did not stash, reset, restore, checkout, commit, pull, fetch, merge, rebase, or delete anything there.

Future unreleased Bridge changes must be retested after they are committed and selected for release. They do not affect this 0.2.2 classification.

## K. Repeatability and evidence

Two clean Phase 6A runs were byte-identical after normalization:

- JSON report SHA-256: `3c67fd55a48ae01d5ad5230358176b603c7927fdf7ffd6ac525444e9e9ea13d1`
- Text report SHA-256: `d8824209664891625a9a68b69aed3181077ff761a5575dd49ef0766503d8ae1e`
- Source manifest SHA-256: `62867f4c90a801a015e188a97d6322a021807678b82956f872dcd672b33b7111`
- Normal-site `active_plugins` before/after SHA-256: `24c5bdcdfcf8e0759ffcdefce52810291dc4389fb9ad8d69d5a106a9dbd02088`
- Disposable database cleanup: PASS on both runs
- Disposable runtime cleanup: PASS on both runs
- Bridge forensic status unchanged: PASS on both runs

Durable evidence:

- `test-results/bvm-drm-events-bridge-phase6a-runtime.report.json`
- `test-results/bvm-drm-events-bridge-phase6a-runtime.report.txt`
- `test-results/bvm-drm-events-bridge-phase6a-source-manifest.json`

## L. Prior baseline preservation

- Phase 3 official five: fresh complete matrix PASS. JSON/text hashes remained the canonical `3b9d63787c2048771491b82664e74c38d1f9c4bed9b782294ac79f8fb44218d2` / `979ae95aa5687fd7666c77bcd6a6330adbadbe615856b47a3ce562cacd8da587`.
- Phase 4 compatible integrations: both Phase 6A coexistence orders exercised all compatible Phase 4 integrations and passed. Historical Phase 4 evidence was not rewritten.
- Commerce Discounts Phase 5A: 0.2.12 activation scenario passed in both Phase 6A runs; the focused no-Square / Square / no-WooCommerce deterministic regression also passed.
- Fill Dates Phase 2A/2B: returned-menu-hook and native-notice-placement focused tests passed.
- Safety Pro: remained excluded and retired; it was neither staged nor activated in Phase 6A.

## M. Final Bridge classification

`PASS — NOT LOCALLY BVM-DEPENDENT; BVM IS OPTIONAL LEGACY/FUTURE ENRICHMENT ONLY`

Classified release: DRM Events Bridge 0.2.2 at `b1efcc974233a3b43c2a9efa30533c6688f87320`.

## N. Compatibility campaign status

**BVM direct first-party add-on compatibility campaign: FUNCTIONALLY COMPLETE**

No supported direct first-party BVM integration remains functionally broken. Express Bar returned-hook debt, Data Tools menu-ownership debt, stale VMS wording, retired Safety Pro, indirect interoperability work, and unreleased dirty Bridge work are not counted as functional blockers.

## O. Repository and environment integrity

- No BVM runtime source changed.
- No Bridge or Router source repository changed.
- No sibling live BVM file changed or synchronized.
- No normal Local, staging, or production activation/database/source state changed.
- No deployment, push, package, ZIP, tag, release, submission, or reviewer reply occurred.
- External HTTP remained blocked inside disposable runtime scenarios.
- All disposable databases and runtime trees from successful runs were removed.
- The protected stash remained unchanged.
