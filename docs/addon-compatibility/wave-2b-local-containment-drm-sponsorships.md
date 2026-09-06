# Wave 2B — Local Test Containment, DRM Events Bridge, and Sponsorships

Date: 2026-09-05

## A. Preflight

The required preflight ran before task work. The repository remained on `work/unreleased-2026-06-18` at `79da784f5bfcc66bd058c0a7f54e08d7b15bb5d5`, equal to `origin/work/unreleased-2026-06-18` at `0/0`, with an empty index. The full accepted dirty/untracked baseline was retained. Its initial status SHA-256 was `17942f7d12a83af65f099a6957d0836177760f95d5dab74f7cb1366f3ce3b86d`; its initial unstaged binary-diff SHA-256 was `c72b91500b5bd93fa7ce4f58423e419bf3c8bfe9a35d43695f450ca01bb0e7e3`.

Protected stash `d08e726804712dc233f0e37b217abd6389963863`, named `WPORG-16D preserve unrelated sidebar+doc work`, remained present and untouched. Mirror/live P0 source hashes remained exact: Command Center `5349ad16c626043072dbd29334d131c4ba531c03fe8e10e8617521afc53c63d4`, profitability `e6f2f6989b7d7d4174d1a5f3c93719c75fafa80f627322aa93e830d81135d55a`, staffing `2c1a90986e409f1f5f44c52d724993b6be89f4b514b68daaed90185b6c7a45e7`, Event Plans `1c505140b509c5f53954beb06a8d40d0774173931305e3fd058463a24ccc5500`, Pass Claims `ebcf893380bea92a14ffb40de3606140eab8b4033eb8eb9b89430c10d992d436`, ticket resolver `be4273c6ad822d8a48185f84a78d9471f0e6b78e640e86e2ea24b4c890f31b9f`, and ticket revenue `d8298e39112230ac2348ec466e4967fccf27e66e4417d09b3c58e2f2c028e035`.

The accepted active Weather tree remained `35` files at `e019619ce1bd5bef851dbdb4573fd527332983cda34004bd5fc531ae50cc5c0b`; Commerce remained `27` files at `03bcb58b402f22048f63a9ee0876f06dda929d9af00923ef7550e60d7c8cc3f8`. Normal `active_plugins` contained `34` entries with emitted JSON SHA-256 `f3063de78a9b7cb3f8ca360d4246d5b6c3e6b94a271dc782bc646d3d33098479`. The recorded first-party cron inventory included Calendar Intake automatic sync, Social queue, Tasks notifications, ticket gateway health, Meta Ads budget sync, email follow-ups, notification digest, and Weather refresh.

## B. Prior HTTP containment escape

The prior harness blocked HTTP only through constants/environment in the disposable runtime's copied `wp-config.php`. Local's independently managed FPM/web cron process booted the normal site and did not inherit either the initiating shell environment or the disposable config. WordPress's directly requested `wp-cron.php` also does not treat `DISABLE_WP_CRON` as a ban on direct cron execution. Local router/nginx evidence showed an independent `POST /wp-cron.php?doing_wp_cron=...`; that process could therefore reach the Weather provider despite the CLI-scoped block.

## C. Prior disposable-database residue

The old teardown considered a successful `wp db drop` sufficient and never queried `INFORMATION_SCHEMA` for schema absence. It had no deliberate residue canary, and fixture-level deletion was bypassable when a probe aborted or fatally exited. The prior eight orphan postmeta rows, six WooCommerce sessions, four ticket-audit rows, and one Square mirror row proved that cleanup had been assumed rather than asserted. They were produced by earlier harness/diagnostic behavior against the normal root, not by a dropped disposable schema somehow leaking into the normal database.

## D. Containment changes

`tests/addon-compatibility/test-containment-mu-plugin.php` is a test-only, time-limited cross-process guard. It accepts only a random-token initiating CLI process and the disposable runtime, returns HTTP 503 for independently launched normal-site web/FPM/direct-cron requests, blocks WordPress HTTP at `pre_http_request` before transport, and returns no ready cron jobs as a second defense. Its state records only bounded roots, expiry, and hashed identifiers; no credential or requested URL is logged.

`scripts/lib/bvm-test-containment.sh` adds a per-site lock, validates exact paths, installs/removes exact guard bytes, waits for pre-existing cron quiescence, captures complete normal-state option/table/cron/Weather manifests with plugins and themes skipped, creates a deliberate `.invalid` HTTP canary and disposable table canary, and requires `INFORMATION_SCHEMA` to prove the test schema absent after drop. Both compatibility harnesses use EXIT/signal traps and repeat guarded cleanup on failure. `scripts/test-bvm-test-containment.sh` forces exit `86` after the canaries to exercise that path. The test-only files remain excluded from public packaging by `release-public-excludes.txt`.

An additional hardening defect was found during development: normal-root `wp db prefix`/`wp db tables` commands booted BVM and appended its bounded operational `vms_resource_fingerprint_log`. The helper now reads `table_prefix` through `wp config get --type=variable` and adds `--skip-plugins --skip-themes` to inventory queries. Early diagnostic attempts appended only this non-business telemetry before that fix; exact prior capped history was unavailable and was not guessed or rewritten. Final repeated runs produce no normal-state change.

## E. Containment self-test

The deliberate-failure self-test passed. The inner run reached both canaries and exited `86`; the outer test proved disposable database absence, residue-canary absence, runtime-tree removal, normal-site HTTP/process blocking, unchanged normal state, exact guard removal, absence of the normal guard/state files, absence of the temporary runtime, and lock release. Final evidence directory: `/var/folders/33/ltvj2kb927dcmnpdb1x8qd0h0000gn/T/bvm-test-containment-self-test.SBVftT`.

## F. Repeated compatibility cleanliness

The current additional ecosystem ran twice and passed `49/49` both times at `bvm-addon-compat-report.n6UNhJ` and `bvm-addon-compat-report.Q7KsIV`. The official-five suite ran twice and passed `18/18` at `bvm-addon-compat-report.jDIg33` and `bvm-addon-compat-report.qKHtAP`. In all four runs, before/after normal-state manifest SHA-256 was `8433d6c292514ed6b51efd8192eee4730909f1c4c0e7ac2d86ff03694ad45897`, option-manifest SHA-256 was `52621896cd1c014c7288f9ad99610ddb20a2e72c9e6e255c04274f5ca381254e`, and all-table manifest SHA-256 was `8b1634a08446f10b6ea728d52feee1baccc81df446ee3e49bd29f80c2af249d9`. Each run recorded `12` fail-closed HTTP canaries, one deliberate normal-web-process 503, zero escaped cron processes, zero escaped unrelated CLI processes, no remaining `bvm_compat_*` database, and all database/runtime/residue/normal-state/guard/lock cleanup checks passing.

## G. DRM 0.2.1 to 0.2.2 authority

The active Bridge filesystem is byte-equivalent to committed 0.2.1 `fc78595451e32e1e23cd243ce32306f81812e721`, Git tree `7c7ed53114283cfe839f44b6c0a1737ba5cf7d6b`, with normalized nine-file manifest `10ba884e93d511e546a475cbfab8155c590020c489f618494ee95692bb680278`. The accepted successor is local commit `b1efcc974233a3b43c2a9efa30533c6688f87320`, Git tree `3e2c1e49411c6811065f7f8eacca8fdaf736ed60`. The exact 0.2.1-to-0.2.2 delta is five paths and `29` insertions/`14` deletions: `AGENTS.md`, `README.md`, `drm-events-bridge.php`, `includes/rest.php`, and `tests/router-provider-probe.php`.

The known release ZIP receipt is SHA-256 `d1f536c199a4583ed4c3f7a189e615ba5667bd1d1e1e06c16d3c7eda354f30fd`; no artifact was recreated. The dirty active Bridge repository retained status fingerprint `6cdc2a1cde71acbab832a682aa0fb3d6f784d6c51fdacec93d2cab55396126c6` and was not cleaned or edited. No later local source supersedes 0.2.2.

Calendar Intake is clean at version 0.2.4/commit `590f2ac40e346a5a1aae384a9e90b37d72b4cf80`. Installed Router 0.1.3 is byte-identical to commit `21fadbd00e2ccebeb42ef8cb0334160af6e8b288`, Git tree `52b6e45421e0809526c46c34c7997ec024cc56df`, normalized 12-file manifest `a1a2bcbd2f1f000eac07376a42eacbdc57092ec53577f1cd938b7e26d15fad7f`, and public contract version 2.

## H. DRM candidate identity

The reconciled candidate is the immutable Bridge 0.2.2 Git object above, extracted only into disposable runtime trees. Its normalized nine-file manifest is `075878dc3628d5f6f26ce96e51ea328c0ce040ddf2b7036e3c18136029d979b3`; bootstrap SHA-256 is `90ded5e059fe0974d465ae9627f8b12d36154b2b83a384b85238d6767028efed`. It was not installed or activated on the normal Local site.

## I. DRM tests

Bridge pure contract tests passed `56` assertions; the legacy admin guard passed. Router 0.1.3 policy passed `85` assertions, candidate normalization passed `42`, and storage proof performed one read with zero writes. The disposable WordPress matrix proved a fresh empty provider, missing Router, invalid presentation, Router exceptions, the exact 13-field normalized record including `presentation`, future/published filtering through Router authority, one public `/wp-json/drm-events/v1/upcoming` route, provider filter `dalene_richelle_site_public_event_provider` once at priority `5`, public permission behavior, both load orders, BVM present/absent, and no HTTP escape. Failure modes return an empty public feed rather than legacy BVM fallback or partial data.

## J. DRM promotion readiness and plan

Classification: `READY FOR CONTROLLED LOCAL PROMOTION`; no promotion occurred. A separately authorized run should record active-plugin/options/tree/status receipts, preserve the complete dirty 0.2.1 directory intact outside the web root, independently extract and reverify the exact 0.2.2 commit, contain public/cron execution, atomically replace the directory at the unchanged plugin basename, and verify version, tree manifest, Router/Intake readiness, provider, REST route/schema, 13 fields, public/private behavior, and both failure paths. Rollback is the inverse atomic directory move followed by the same receipts. No activation/deactivation or database mutation is expected, but the promotion must stop if that assumption fails.

## K. Dālene consumer classification

Classification: `EXTERNAL CONSUMER EXPECTED`. Local search found Bridge documentation, provider hook, REST adapter, and tests, but no downstream Dālene presentation consumer implementation. The available contract explicitly assigns site-side HTML/CSS presentation to the Dālene consumer. Bridge can be tested independently and therefore is not blocked by that external consumer's absence.

## L. Sponsorships same-version drift

Active installed `vms-sponsorships` remained labeled 0.1.27 at normalized 17-file manifest `02115019f565f226251d8f4d8d2a0944f43f56a43f877c49152aef825209c3b1`. The clean repository base is commit `86e911f42e0d242ecb022453e4753ece6cbc1a84`, Git tree `f76aedf4f7a3b80ac25fbdf207f44c916a5fd6a7`. Relative to that base, active source adds `includes/core-compat.php`, lacks repository-only `.gitignore`, and changes the bootstrap, admin class, and shortcode class. The frozen `vms-sponsorships-0.1.27-rc1.zip` remains SHA-256 `4e77159f4802be78f80e7a70ef9d3e442b579e1229f859204fe40f33e56ea6ee`.

Accepted active hashes are bootstrap `a1fb94361f315f6bd58f5e7db4f217bd39196d03a8fd1be56ef11aebca182def`, admin `d2d69953b62aee237127312c74e08215cf68a2ff0be097563f6a69488a61448e`, shortcodes `1aebc3867222ac8ba86ad44880a2d06ad35e499f8f534b597b8f03d41c7585c2`, and compatibility helper `049a4e3fed08f3250a8e97dbfec84dbca891ca03a15ce248c44876fe5c28b7cc`. Base hashes for the first three were respectively `7a606b193b918ef18c772244259eba6ec2c91449ff4e86e39d5e980566334a87`, `cf1e0710d9b698258e7eee6af5e13554bf0ee21faa6b02b8ef4e6d183473981e`, and `0980eb0a3dddd79df24530485814d67fe55b502e702b59b1c6bd9908c2f86347`.

## M. Sponsorships authority conclusion

Conclusion: `A — accepted successor work accidentally not version-bumped`. The 2026-09-03 ledger and passing 49/49 evidence identify these exact package-local canonical-first compatibility/registry changes as accepted, narrowly scoped work. Active source contains no additional experimental runtime delta. The successor therefore carries those accepted bytes forward under a new version rather than treating either materially different tree as canonical 0.1.27.

## N. Sponsorships successor

The clean candidate at `../vms-sponsorships` is version 0.1.28, based on clean commit `86e911f42e0d242ecb022453e4753ece6cbc1a84`. It changes only `README.md`, `docs/CHANGELOG.md`, `includes/class-vms-sponsorships-admin.php`, `includes/class-vms-sponsorships-shortcodes.php`, and `vms-sponsorships.php`, and adds `includes/core-compat.php`. Its exact status contains no other path. The normalized 18-file manifest is `a1ec835bc577d86955ea010789319a9edd638e1d79bdb731e837a6276a9e47f5`; bootstrap SHA-256 is `205aa0031faf791b57442e3f628d56338037c712ce3b638ffc7fd988cdfe07d1`; compatibility helper SHA-256 is `049a4e3fed08f3250a8e97dbfec84dbca891ca03a15ce248c44876fe5c28b7cc`. Every accepted runtime file is byte-identical to active 0.1.27 except the two required 0.1.28 bootstrap version markers; documentation records the successor.

## O. Sponsorships tests

All candidate PHP files lint. The disposable matrix proves BVM-only runtime, legacy VMS absent, all seven canonical registry pages with no duplicate fallback page, Event Plan meta-box/save callbacks exactly once, the dead retained `vms_event_plan_after_modules` attachment not revived as a BVM emitter, capability and nonce rejection/acceptance, all nine public shortcodes, public application and assigned-sponsor rendering, and the complete synthetic package/application/assignment/fulfillment/metric flow. Invalid application data and a duplicate assignment fail closed. Exact synthetic rows are deleted in-probe, then the containing disposable database and residue canary are independently proven absent. Dependency, both-load-order, coexistence, and no-external-HTTP cases pass.

## P. Sponsorships promotion readiness and plan

Classification: `READY FOR CONTROLLED LOCAL PROMOTION`; no promotion occurred. A separately authorized run must record active-plugin state, all Sponsorships options/tables/checksums, and the 0.1.27 tree; preserve that directory intact outside the web root; stage and reverify the exact 0.1.28 manifest; contain public/cron/submission traffic; and atomically swap the unchanged basename. Unlike Bridge, Sponsorships calls `maybe_upgrade()` on load: version 0.1.28 will run its additive `dbDelta`/default-package path and update `vms_sponsorships_version`. That database mutation requires explicit later authorization, a verified backup, and before/after table/option receipts. Verification must cover seven pages/no duplicates, Event Plan, public rendering, and package/application/assignment flows using only synthetic disposable data. Rollback restores the 0.1.27 tree and recorded database/options state.

## Q. Combined candidate compatibility

The `49/49` repeated additional runs combined accepted BVM P0, Weather 0.1.12, Commerce 0.2.13, Data Tools 0.5.54, Intake 0.2.4, Router 0.1.3, Bridge 0.2.2, and Sponsorships 0.1.28 in both meaningful coexistence orders. There were no fatals, duplicate classes/hooks/routes/providers, cross-owned notices, or Event Plan/Command Center failures. HTTP, cron, disposable database, residue, runtime, normal-state, and guard cleanup assertions all passed.

## R. Source, test, and documentation changes

Containment changes are `scripts/lib/bvm-test-containment.sh`, `scripts/test-bvm-test-containment.sh`, both compatibility shell harnesses, `tests/addon-compatibility/test-containment-mu-plugin.php`, both report builders, and the official/additional source-manifest, runtime-contract, and runtime-probe files needed to emit and assert containment evidence. `tests/addon-compatibility/wave2b-source-authority.php` freezes authority. The additional contract/probe stages Router 0.1.3, Bridge 0.2.2, and Sponsorships 0.1.28 and adds the stated chain/business coverage. Documentation changes are this report, `docs/addon-compatibility/bvm-only-runtime-harness.md`, and the remediation ledger. The separate Sponsorships repository contains only the six reviewed 0.1.28 paths listed in section N. No BVM runtime, active Bridge, active Sponsorships, Weather, Commerce, Data Tools, legacy VMS, or rollback file changed.

## S. Normal-local database changes

Normal-local business-data changes: `NONE`. Final contained runs leave the complete normal database manifest identical before/after. During early hardening diagnostics only, BVM's operational `vms_resource_fingerprint_log` option appended process observations because old normal-root inventory commands booted active plugins; no event, order, ticket, customer, admission, staffing, Sponsorships, Weather, Commerce, or other business row changed. That capped telemetry history was not guessed or restored. The corrected inventory path is write-free.

## T. Active plugins

Before and after remained the identical `34`-entry list with emitted JSON SHA-256 `f3063de78a9b7cb3f8ca360d4246d5b6c3e6b94a271dc782bc646d3d33098479`. Neither Bridge nor Sponsorships was activated, deactivated, or source-swapped on the normal Local site.

## U. Cron, Weather, and business-data preservation

Every passing contained suite had identical full normal-state, exact-option, and all-table manifests before/after (`8433d6c2…5897`, `52621896…254e`, and `8b1634a0…49d9`), including byte-identical cron serialization inside each bounded run. After guard removal, normal Local scheduling resumed and its wall-clock cron serialization was allowed to advance normally; that later movement is not attributed to the contained suites. Weather settings/log raw JSON SHA-256 values remained `c448412096570301f364eb386aac5b6675eb90a21d74644027b848825677c6ca` and `0cf61a97dfed240c90cae3d22466f909604bda2358b70d45c184f5cb15379321`; the snapshot inventory remained `369` rows at ordered-row SHA-256 `3602225b2e34cb0443b8e1c97c22b12b3ac160819a58e42dbf5a610203a5666c`. No provider request or schedule movement escaped during a contained run, and no `bvm_compat_*` schema remained.

## V. Git diff and status

The repository remains deliberately uncommitted. Final status/diff fingerprints and `git diff --check` are captured at closeout after this report. The separate Sponsorships repository remains on base commit `86e911f42e0d242ecb022453e4753ece6cbc1a84` with exactly five modified paths plus new `includes/core-compat.php`; its diff is also uncommitted. No index entry was created.

## W. Protected stash and unrelated work

All pre-existing P0/Wave 1/Wave 2A and unrelated dirty/untracked work was preserved. The protected stash was not applied, popped, dropped, rewritten, or recreated. The dirty Bridge forensic worktree, inactive legacy VMS, temporary Commerce directory, dormant/duplicate plugin directories, and all rollback archives were left intact.

## X. Environment and network boundary

Work was local only. No staging or production host, SSH, remote WP-CLI, deployment, upload, communication, payment/order operation, real provider contact, external API mutation, package, ZIP creation, tag, release, WordPress.org action, reviewer reply, commit, push, pull, fetch, checkout, reset, rebase, or stash operation occurred. The only deliberate web request targeted the normal Local loopback origin to prove the 503 process guard; reserved `.invalid` requests were intercepted inside WordPress before transport.
