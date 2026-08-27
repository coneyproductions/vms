# WordPress.org Prefix Migration — Phase B3

Status: authorized and in progress  
Authorized baseline: `634211d1d5bbd250fc13b19d02f39acd4a4bc96b`  
Branch: `codex/wporg-reviewer-identity-alignment`  
Scope: plugin-owned procedural PHP functions only

## Controlling artifacts

The immutable mapping authority is `docs/wporg-prefix-b3-function-map.json`. It freezes all 4,521 `vms_*` function identities, their 4,541 declaration sites, the 20 exact two-site duplicate families, public-extension classification, known-add-on consumers, B2.5 scanner rows, and dynamic literal/callback sites. Every target is the unique, collision-free `bvmgr_<suffix>` counterpart.

The supporting control artifacts are:

- `docs/wporg-prefix-b3-dependency-graph.json`: direct-call and exact function-literal relationships derived from the frozen map.
- `docs/wporg-prefix-b3-waves.json`: exact, deterministic membership for all 11 waves.
- `docs/wporg-prefix-b3-progress.json`: regenerated hybrid-state ratchet after each wave.

These artifacts are generated and verified by `scripts/generate-wporg-prefix-b3.php` and `tests/wporg-prefix-b3-guardrails.php`. The frozen map cannot be overwritten by the generator.

## Non-negotiable invariants

- Rename only the 4,521 frozen plugin-owned procedural functions from `vms_*` to `bvmgr_*`.
- Do not introduce legacy wrappers, aliases, trampolines, or dual declarations.
- Keep both declaration sites of every duplicate family in one wave.
- Update all frozen direct-call and exact function-name literal references for a selected symbol across public PHP in the same wave.
- Preserve hooks, option/meta/transient keys, database identifiers, REST routes and namespaces, action/filter names, AJAX actions, shortcode tags, script/style handles, nonce actions, filenames, class names, constants, and other retained contracts unless separately authorized.
- Keep authorization, authentication, capability, nonce, escaping, sanitization, REST/AJAX, and public behavior unchanged.
- Cut over all five known add-ons together in W2 using disposable copies only. Installed/local-live copies remain untouched.
- Stop on a mapping collision, unresolved dynamic reference, split duplicate, stale/forward reference, add-on mismatch, failed focused test, strict packaged scan regression, or installed-tree hash drift.

## Numbered execution plan

| Wave | Dependency domain | Functions | Sites | Duplicate families |
|---|---|---:|---:|---:|
| W1 | Activation and plugin-basename pilot | 35 | 35 | 0 |
| W2 | Atomic five-add-on and public-extension API boundary | 66 | 68 | 2 |
| W3 | Shared runtime, registries, admin UI, docs, and tours | 457 | 460 | 3 |
| W4 | Event Plan editor, review, import, performance, cancellation, and feedback | 640 | 647 | 7 |
| W5 | Ticketing runtime, rules, claims, and verifications | 616 | 616 | 0 |
| W6 | Ticket integrity, forensics, mutation, Square, and revenue | 533 | 533 | 0 |
| W7 | Vendor and venue applications, onboarding, registry, taxonomies, and admin | 419 | 419 | 0 |
| W8 | Portals, availability, calendar, and public vendor profiles | 524 | 524 | 0 |
| W9 | Staffing, staff tasks, schedule, season, and due dates | 441 | 445 | 4 |
| W10 | Admissions, pass claims, email, status notices, and public event details | 428 | 428 | 0 |
| W11 | Settings, reporting, social, tax bypass, and remaining admin | 362 | 366 | 4 |
| **Total** |  | **4,521** | **4,541** | **20** |

`docs/wporg-prefix-b3-waves.json` is the exact membership authority. It lists every function, declaration file, duplicate family, focused test group, and aggregated cross-wave dependency. W2 is selected semantically as the exact union of 55 known-add-on-consumed functions and 12 public-extension function APIs, with one overlap, rather than as whole-file ownership.

## Per-wave gate

Each wave is mechanically transformed from its frozen function list, reviewed as a narrow diff, and must pass before its isolated commit:

1. PHP syntax for every changed PHP file and `git diff --check`.
2. `php scripts/generate-wporg-prefix-b3.php --write-progress` and `php tests/wporg-prefix-b3-guardrails.php`.
3. Phase-aware manifest/scanner regeneration and their guardrails.
4. The wave's focused runtime/behavior tests from `docs/wporg-prefix-b3-waves.json`.
5. Existing identity, runtime-stub, release-compatibility, and public-release pipeline regressions.
6. A strict packaged scanner/Plugin Check checkpoint at high-risk boundaries and the final gate.
7. Verification that protected and installed/live trees retain their recorded baseline hashes.

The W2 gate additionally requires fresh disposable provenance for all five add-ons and an explicit harness whose core and add-on roots resolve only inside the disposable workspace.

## Final acceptance

B3 is complete only when the progress ratchet reports 4,521 migrated unique functions, 4,541 migrated declaration sites, zero remaining prohibited `vms_*` function declarations, zero stale or forward function references, and zero unexpected scanner rows. Final acceptance also requires a clean strict packaged Plugin Check result with warnings/errors non-increasing from the B2.5 baseline, real activation/deactivation/reactivation lifecycle proof, required plugin-load/runtime smokes, five-add-on disposable integration proof, unchanged installed/live tree hashes, and the remediation ledger evidence.

This phase does not authorize B4–B8 work, pushing, deployment, packaging for distribution, tagging, WordPress.org submission, or a reviewer reply.

## Wave checkpoint record

### W1 — activation and plugin-basename pilot

Status: committed at `c0c72a0507e5415bb5800dd25f2d1e9beb21b624`.

- Exact cutover: `35` unique functions / `35` declaration sites / no duplicate family; all declarations and all frozen direct-call and exact function-literal references moved atomically.
- Ratchet: `35` migrated / `4,486` legacy functions remaining; scanner B3 rows `4,541 -> 4,506`; no stale/forward reference, unexpected finding, unmapped finding, or wrapper.
- Focused behavior: B2 foundation, plugin identity, activation public-page ownership, retired Square cleanup, runtime stubs, and the compatibility self-test pass.
- Disposable package: `/private/tmp/bvm-wporg-b3-w1-checkpoint/backstage-venue-manager-1.2.0-public-release-dev.zip`, SHA-256 `fbe187297234a4a3da309b21b6af910950fdac817f8335814d5fa52525f98225`. The package build passed after staging `374` files, linting `271` PHP files, and checking `55` JavaScript files; release regressions were run separately because three default tests require an explicit WordPress root.
- Real lifecycle: the exact dev ZIP passed the VMS-only disposable dependency matrix, activation, deactivation, reactivation, repeated activation, baseline upgrade, interrupted-migration resume, fixture preservation, and uninstall preservation. Report SHA-256: `afd9ff4c0b1ce90a368564996b1677e169951582f50870795b3af306e6481098`; `WARN` is limited to captured PHP/dependency deprecations.
- Strict packaged scan: `5,239` total = historical `125` errors + `5,114` phase-aware warnings. B3 function rows are exactly `4,506`; B7 `182`; method-scope `420`; external/core `6`. The migration-aware gate passes with exactly `35` authoritative B3 findings removed and zero unexpected/unmapped rows. Normalized JSON SHA-256: `ee65a3b1a7c7ac1f428f867d920b489958a31629dcd472d88ea691fdd3d8b54f`.
- Untouched boundaries: `docs/wporg-prefix-b3-untouched-tree-baseline.json` freezes reproducible content hashes for the installed/live core and all five installed add-ons. No installed/live source was modified.

### W2 — atomic five-add-on and public-extension API boundary

Status: committed at `bc14239e03f351e61ce8b237f4c558609191144f`.

- Exact cutover: `66` unique functions / `68` declaration sites, including both sites of `vms_event_plan_set_secondary_vendors` and `vms_render_help_button`; all frozen direct calls and proven function-resolution literals moved atomically.
- Ratchet: `101` migrated / `4,420` legacy functions remaining and `4,438` B3 declaration rows remaining; no stale or forward reference, unexpected finding, unmapped finding, completed-batch residual, or wrapper.
- Controlled recovery: the first transformation exposed six retained contracts that share function-like spelling. The failed tracked attempt is preserved at `/private/tmp/bvm-wporg-b3-w2-failed-exact-literal.patch` (SHA-256 `f77ad24f05e9a699d63cd3bfa6fb8b20a4eaea1d4bbbc3d785ed8d06a184cedb`). W2 was restored to its clean W1 checkpoint and reapplied with the transformer restricted to proven function-resolution contexts; the retained admin hooks, option value, and notification-source values remain unchanged.
- Disposable add-ons: `/private/tmp/bvm-wporg-b3-addon-isolation` contains source snapshots, pre-B3 copies, migrated workspaces, provenance, and deterministic patches for all five known add-ons. The explicit isolated-root compatibility harness passes with `63` callable entries / `53` unique core functions; the two excluded Fill Dates entries are retained hook-name homonyms rather than callable dependencies. Installed add-on contract regressions also pass against their untouched legacy callers.
- Focused verification: phase-aware B3, manifest, scanner, B2 foundation, B2.5 runtime, migration-state, installed/disposable add-on, runtime-stub, plugin-identity, Event Plan review/secondary-vendor, guided-tour, social-panel/queue/provider/webhook, changed-PHP lint, release-compatibility self-test, public-release pipeline self-test, and diff gates pass.
- Disposable package: `/private/tmp/bvm-wporg-b3-w2-checkpoint/backstage-venue-manager-1.2.0-public-release-dev.zip`, SHA-256 `c0217f9b2a1dbaf9184786dd05ffeeb90d4839801f4f0f14240aa186d78782fe`. The build staged `374` files, linted `271` PHP files, checked `55` JavaScript files, and passed package integrity.
- Real lifecycle: the exact W2 dev ZIP completed the VMS-only matrix, activation, deactivation, reactivation, repeated activation, baseline upgrade, interruption recovery, fixture preservation, and uninstall preservation. Report SHA-256: `b581ef24cba9b8a5531c928652c7cd8639ecfec31fecf695843b51a90f0a85f9`; `WARN` is limited to captured PHP/dependency deprecations.
- Strict packaged scan: `5,171` total = historical `125` errors + `5,046` phase-aware warnings. B3 function rows are exactly `4,438`; B7 `182`; method-scope `420`; external/core `6`. The migration-aware gate passes with zero unexpected/unmapped/completed rows. Normalized JSON SHA-256: `8991509152b0ed4ee70fd063b036cfec5df21b3722b54aafffef8ff999276896`.
- Boundaries: no B4-B8 identifier family changed. Installed/live core and add-on trees retain their recorded hashes; no push, merge, upload, tag, deployment, staging/production change, WordPress.org action, reviewer reply, or protected-stash mutation occurred.

### W3 — shared runtime, registries, admin UI, docs, and tours

Status: committed at `bf89b7f674af4a21e89ce8e1663036e0ae72790a`.

- Exact cutover: `457` unique functions / `460` declaration sites, including both sites of each of the three duplicate families; all frozen direct calls and proven function-resolution literals moved atomically without a wrapper, alias, trampoline, or dual declaration.
- Ratchet: cumulative B3 progress is `558 / 4,521` functions and `563 / 4,541` declaration sites; `3,963` legacy functions / `3,978` B3 scanner rows remain. Phase-aware manifest, frozen-map, and scanner gates report no stale/forward, unexpected, unmapped, or completed-wave residual.
- Literal authority: `docs/wporg-prefix-b3-literal-decisions.json` classifies every W3 exact-only literal as `13` callable/source-code identities to rename and `30` retained hooks, option keys, or other contracts. The test-literal transformer keeps explicit retained hook/AJAX/safety-prototype sites legacy while updating isolated source-introspection expectations.
- Focused verification: all primary B3/manifest/scanner, B2/B2.5, installed/disposable add-on, identity, runtime-stub, release-compatibility, and public-release pipeline gates pass. `51` applicable changed-test harnesses pass; installed/live byte-parity checks, historical-artifact fixtures, explicit-WordPress-root harnesses, and unrelated pre-existing environment/branding expectations were excluded rather than weakened. Changed-PHP lint and diff checks pass.
- Disposable package: `/private/tmp/bvm-wporg-b3-w3-checkpoint/backstage-venue-manager-1.2.0-public-release-dev.zip`, SHA-256 `1b95ad6c84c794a2687e5a41823d834a393435348be7508845d3217eadebabe9`. The build staged `374` files, linted `271` PHP files, syntax-checked `55` JavaScript files, and passed package integrity.
- Real lifecycle: the first full run recorded a transient supported-stack HTTP-process failure after activation while all plugin activation checks passed; its report SHA-256 is `8b458da979cc8ff7d4c8c42d93aa5cf9f8c87efeabd6956187d4dbc3744c0b86`. An immediate isolated rerun of that exact scenario completed without a fatal (report SHA-256 `777ce8dd47e13bd38d4bcdd7d148305b67748ef30eae41da9fd73e8afa1a5ac6`). The subsequent full exact-ZIP rerun completed all seven dependency scenarios, activation/deactivation/reactivation, baseline upgrade, interruption recovery, fixture preservation, and uninstall preservation without a plugin fatal. Its report SHA-256 is `4e5c6dd63ae690d9db7bd78ae476e640e616b5f095ab43449ef12a85233b7740`; overall `WARN` is limited to captured dependency/PHP deprecations and non-authoritative direct-login probes whose downstream requests succeeded.
- Strict packaged scan: `4,711` total = historical `125` errors + `4,586` warnings, categorized exactly as B3 `3,978`, B7 `182`, method scope `420`, and external/core `6`. The gate records exactly `563` authoritative B3 findings removed with zero unexpected, unmapped, or completed-wave rows. Normalized JSON SHA-256: `81a831916c37518352de55bf415a160e95859cce9a34b6c51169070bb31acde0`.
- Boundaries: installed/live core and all five add-on trees remain read-only and unchanged. No B4-B8 identifier family changed; no push, merge, upload, tag, deployment, live sync, staging/production change, WordPress.org action, reviewer reply, or protected-stash mutation occurred.

### W4 — Event Plan editor, review, import, performance, cancellation, and feedback

Status: verified for the isolated W4 commit.

- Exact cutover: `640` unique functions / `647` declaration sites, including both sites of all seven duplicate families, moved atomically without a wrapper, alias, trampoline, or dual declaration.
- Ratchet: cumulative B3 progress is `1,198 / 4,521` functions and `1,210 / 4,541` declaration sites; `3,323` legacy functions / `3,331` B3 scanner rows remain. All phase-aware gates report zero stale/forward, unexpected, unmapped, or completed-wave residual.
- Literal authority: the W4 exact-only split is `7` executable callback identities renamed and `32` retained WordPress hooks, admin-post actions, audit/provenance labels, and performance telemetry labels. Nine explicit W4 test sites continue asserting the retained hook/action or B2 global-slot identities rather than being mechanically canonicalized.
- Focused verification: Event Plan review JSON, performance request ID, secondary-vendor bootstrap/inline/lazy-load/save, import file/upload/rows output, Administrator-shell continuity, integrity output, authorization/request sanitization, and applicable repository tests pass. Mirror/live byte-parity tests, unavailable historical-artifact fixtures, and WordPress-root-dependent integration scripts were excluded rather than weakened because the installed/live tree remains intentionally untouched. Changed-PHP lint, diff checks, and all primary B3/B2/B2.5/add-on/identity/runtime/release self-tests pass.
- Disposable package: `/private/tmp/bvm-wporg-b3-w4-checkpoint/backstage-venue-manager-1.2.0-public-release-dev.zip`, SHA-256 `d025f12d156855992c5065e0a334a933f6b678a39b5047cb23704f188de938fb`; the build staged `374` files, linted `271` PHP files, syntax-checked `55` JavaScript files, and passed package integrity.
- Supported-stack smoke: the exact W4 ZIP completed activation and authenticated/public smoke requests with WooCommerce, The Events Calendar, Event Tickets, and Event Tickets Plus without a plugin fatal or duplicate scheduled-work owner. Report SHA-256: `800e0a99f658271c17b6e80acea538f5e798b4c9b2a4eb8ccd574b85018bf061`; `WARN` is limited to captured dependency/PHP deprecations.
- Strict packaged scan: `4,064` total = historical `125` errors + `3,939` warnings, categorized exactly as B3 `3,331`, B7 `182`, method scope `420`, and external/core `6`. The gate records `1,210` authoritative B3 findings removed and no regression. Strict JSON SHA-256: `03222e7c742c982abf93c8b34c39d3e5f19d4353b202735f7eb1dba38890ac46`.
- Boundaries: installed/live core and all five add-on trees remain unchanged. No B4-B8 identifier family changed; no push, merge, upload, tag, deployment, live sync, staging/production change, WordPress.org action, reviewer reply, or protected-stash mutation occurred.
