# Wave 3B-1 Retry — Calendar Feeds 0.1.4 Controlled Local Promotion

Date: 2026-09-06  
Scope: local-only acceptance-runner repair, same-basename Calendar Feeds promotion, and acceptance

## Decision

Backstage Calendar Feeds `0.1.4` is **ACTIVE / CANONICAL / ACCEPTED LOCALLY** at `backstage-calendar-feeds/backstage-calendar-feeds.php`.

Data Tools remains active at `0.5.54`. Its `0.5.55` candidate remains ready for a separately authorized controlled local promotion and was not promoted here.

## A–D. Preflight and acceptance-runner repair

Required preflight retained branch `work/unreleased-2026-06-18`, HEAD `79da784f5bfcc66bd058c0a7f54e08d7b15bb5d5`, local-ref upstream relation `0/0`, an empty index, the accepted dirty baseline, and protected stash `d08e726804712dc233f0e37b217abd6389963863` named `WPORG-16D preserve unrelated sidebar+doc work`.

The prior Wave 3B-1 failure was test infrastructure, not a Calendar product failure. Installed WP-CLI `2.12.0` implements ordinary `eval-file` by calling `eval('?>' . $file_contents)` from inside `EvalFile_Command::execute_eval()`. A file-level `declare(strict_types=1)` in that input is therefore not the first statement of its evaluated compilation context and PHP rejects it. The repaired `tests/calendar-feeds-normal-local-acceptance.php` omits that incompatible declaration without weakening any product assertion.

The first pre-promotion diagnostic exposed one additional runner-only defect: global code called the namespaced `bcf_get_profile()` without its namespace. The call was qualified as `ConeyProductions\BackstageCalendarFeeds\bcf_get_profile()`. This happened while active Calendar remained exact `0.1.3`; the containment guard and lock cleaned up successfully and no promotion occurred.

The corrected diagnostic then passed `72` assertions under PHP `8.3.33` against active `0.1.3`, proving WordPress/BVM bootstrap, exact source and activation identity, safe existing-token validation, DRM health, read-only feed rendering, ICS routing, accepted add-on versions, assertion output, and cleanup. An additional candidate diagnostic loaded the retained exact `0.1.4` source outside the web root and passed `87` assertions, including the complete BVM registry/menu/direct-page branch: one registry entry, one physical submenu, zero duplicate top-level menu, a callable page rendered through the BVM shell, valid feed/token/DRM behavior, and no database movement. Both diagnostics used `--skip-plugins --skip-themes` and manually loaded only canonical BVM, the DRM chain, and Calendar Feeds so unrelated add-on bookkeeping could not run.

## E–F. Candidate and rollback authority

The canonical candidate and the failed-but-intact prior candidate tree were byte-identical: version `0.1.4`, `17` files, normalized SHA-256 `20f2607ec61c62f2d7120cd99b657b46e15bc169f6d92d14f7951540e33fc20e`. Pre-promotion validation passed the Calendar `109`-assertion unit suite, BVM navigation/load-order/standalone coverage, targeted source/ownership and P0 checks, candidate PHP lint, and the hardened additional ecosystem matrix at `52/52` with blocked HTTP/process execution, residue removal, unchanged normal state, and clean teardown.

The existing complete `0.1.3` archive was reused only after revalidation. It remains mode `0600`, `160,220` bytes, SHA-256 `f917472a481c566fd4b5e88eb555767e2a179766aeb2706a9aeda13d08fe949a`; tar listing passed and its retained extraction exactly matched then-active `0.1.3`, `17` files, normalized tree `c732bb48971360e892fd17834d4a1b018a1de27e58777635a12164e58eb5e1e8`.

## G–O. Promotion and normal-local acceptance

The accepted candidate was copied to a retry-specific staging directory outside the web root, rehashed, compared byte-for-byte, and installed under the existing canonical basename with two atomic same-filesystem directory renames while the normal Local guard and per-site lock were active. The exact old tree was retained outside the web root. There was no deactivate/reactivate cycle, basename change, mixed source tree, or activation mutation.

The promoted tree reports version `0.1.4`, contains `17` files, exactly matches normalized SHA-256 `20f2607ec61c62f2d7120cd99b657b46e15bc169f6d92d14f7951540e33fc20e`, matches the accepted candidate byte-for-byte, and passes PHP lint. It contains no JavaScript files.

The pre-proven normal-local acceptance runner passed `87` assertions against the installed tree under PHP `8.3.33`:

- canonical activation entry exactly once;
- one `backstage` page registered through BVM under `vms-dashboard`;
- zero duplicate top-level `backstage` menus and one physical submenu;
- registry identity/source/section/capability/shell flags exact;
- direct `admin.php?page=backstage` hook callable and page rendered through the BVM shell;
- existing encrypted token decrypts, has the expected 43-character capability shape, and validates without being printed or regenerated;
- tokenized ICS route and destination exact;
- read-only feed build returns a valid VCALENDAR envelope;
- DRM Intake contract `2` and source-discovery contract `1` healthy;
- Intake `0.2.4`, Router `0.1.3`, Bridge `0.2.2`, Data Tools `0.5.54`, Weather `0.1.12`, Commerce `0.2.13`, and Sponsorships `0.1.28` preserved;
- BVM P0 and reporting-provider functions available, while unpromoted Data Tools registers no `0.5.55` provider.

Normal `active_plugins` remained the exact 34-entry, 1,760-byte serialization at raw SHA-256 `350ea69718f7946020940a98d081b061225c76a720f4fdcda9287403781b3afe` and emitted-JSON SHA-256 `27115e8644f764f4be9403d7aa0c356c2b98a5543a1ef2fe079b0231d7fff98c`. Calendar state remained only `bcf_feed_profile_secrets`, 379 serialized bytes, safe fingerprint `38e69cf57a7963d050018d3c9dc369851aaf8967d771e453e34341ccbaac0ce0`, autoload `off`; the publication ledger and lock options remained absent.

The guarded promotion window was byte-stable before/after: cron `db4b9a7f3bb73333a6f64a5f1ca11ad9f361258eec61fd249c09c3f67b018752`, `821` options at `f74c59dc7ec32fc1ae0dfaede7eeadb230328405f3078fcd0edcbf34141839dc`, and `246` tables at `4d8bd291d2488f87d9fef0ed9edf7b51641295ff9f2d09a12a257cc577bf33f8`. Weather settings/log/snapshot receipts and `wp-config.php` were also identical. The acceptance process attempted no HTTP request.

## P–T. Post-promotion compatibility and containment

Focused post-promotion tests passed Calendar `109`, navigation, BVM provider, Data Tools provider/decoupling, P0 consistency, three staffing SQL suites, ticketing core/claims SQL, Commerce business contracts, two Weather suites, Intake source/venue, Router policy/candidate/storage, and Bridge legacy/provider coverage. The Wave 3A Data Tools decoupling test's now-stale assertion that Calendar must remain unpromoted at `0.1.3` was updated to assert the newly accepted `0.1.4`; no runtime behavior was changed.

The official-five matrix passed `19/19`. The additional ecosystem passed `52/52` both before promotion against the accepted candidate and after promotion using the active source. Every run reports database/runtime cleanup, outbound HTTP blocked before transport, independent Local process blocked, disposable residue removed and asserted, unchanged normal state and activation, guard cleanup, and lock release. The additional runs also preserve the Bridge forensic worktree.

Every successful diagnostic, precheck, promotion, and postcheck window shared the same normal activation, cron, option, and table hashes recorded above. No between-run bookkeeping movement occurred. Normal-local database changes were **none**, including no Calendar option, token, cron, business-table, or unrelated bookkeeping mutation.

## U–Z. Preservation, rollback, Git, and boundary

Rollback remains immediately available from both the verified original archive and the retry-specific direct preserved `0.1.3` tree at `/Users/treyconey/Local Sites/serenade-range-local-test-site/app/bvm-local-rollbacks/wave3b1-retry-20260906T064832Z/calendar-feeds/backstage-calendar-feeds-0.1.3-preserved-tree`. The prior failed-but-intact `0.1.4` evidence tree and all earlier rollback material were retained.

BVM provider files remain byte-identical between mirror and active canonical BVM: provider contract `6ce14e7d600abd982e8ca3a0ea004b426e68fdd51ac1b35404bfffb3a8e2a686`, core load `aa344226ff63799140ea5e2fd152c0b41c262075a29cc4829fb529afb711dc78`, Event Command Center `c56accb8ba26b4757c93b867f454ef4b7bbc791a846eeb4264c8817d47ea57b5`, and Vendor Portal `e88051421dd0fbf6bbe30f9bf0822ab4f9b51b0b871ba3392a5caa4d0836403e`. Accepted P0 hashes remain unchanged.

Data Tools remains active `0.5.54`, `78` files/tree `7f997b79f675b2534188d44f750afb4bd4eec9d03db9f75b6020b0afe02e9039`. Its separately authorized future candidate remains `0.5.55`, `81` files/tree `8e36b9b6cf1e337627a86a782f2eafc49681d09b8711665e54ab78d6b005988b`. Classification: **STILL READY FOR SEPARATE CONTROLLED LOCAL PROMOTION**.

Repository changes for this retry are the normal-local acceptance runner, the two newly current test/source-authority descriptions, this report, and the remediation-ledger entry. Active runtime source changed only in the canonical Calendar Feeds directory. No BVM, legacy VMS, Data Tools, Weather, Commerce, DRM, or Sponsorships source changed.

The work was local only. There was no staging or production access, SSH, remote WP-CLI, external HTTP escape, deployment, upload, package/ZIP/tag/release, communication, payment/business action, WordPress.org action, commit, push, pull, fetch, `ls-remote`, checkout, reset, rebase, or stash operation.

Evidence root: `/Users/treyconey/Local Sites/serenade-range-local-test-site/app/bvm-local-rollbacks/wave3b1-retry-20260906T064832Z/calendar-feeds`.
