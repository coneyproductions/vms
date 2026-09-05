# SR.com Show Readiness — Installed BVM Add-on Registration Sweep Closeout

Date: 2026-09-01

Overall status: **PASS WITH DEBT**. Every installed production companion add-on is now either compatible with public Backstage Venue Manager 1.2.0 or intentionally inactive. No installed add-on remains blocked on the historical VMS core. The debt is evidence/packaging debt, not an open runtime incompatibility: Sponsorships 0.1.7.1 has no exact local release archive, older VMS wording remains, and one deliberately isolated production WP-CLI verification mistake added one TypeError entry to the site error log before the corrected probe passed.

## 1. Scope and controls

- Production core stayed on active `backstage-venue-manager/backstage-venue-manager.php` 1.2.0.
- Historical `vms/vendor-management-system.php` 1.1.0 stayed inactive on production and was never reactivated.
- No BVM core runtime file or sibling live BVM file changed.
- Campaign, ticketing, checkout, Express Bar ordering, authentication, authorization, nonce, REST response shape, AJAX, and stored business behavior were not redesigned.
- The protected stash `WPORG-16D preserve unrelated sidebar+doc work` remained present and untouched.
- The expected prior Express Bar/show-readiness ledger change was committed separately as `fc06ad8115a6e21f284a57442743555361bf6615` before this sweep began.

## 2. Production installed-state inventory and final classification

| Add-on/core | Production before | State | Status before | Root cause/finding | Production after | Repair | Staging result | Production result | Final status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Backstage Venue Manager | 1.2.0 | Active | PASS — public runtime | Public `BVMGR_*`/`bvmgr_*` runtime available; legacy PHP symbols absent | 1.2.0 | No change | Active 1.2.0, legacy VMS inactive | Active 1.2.0, legacy VMS inactive | PASS |
| Historical VMS core | 1.1.0 | Inactive | INACTIVE BY DESIGN | Retired compatibility core | 1.1.0 | No change; never reactivated | Inactive after production-like core switch | Inactive | INACTIVE BY DESIGN |
| Agreements | 0.3.47 | Active | PASS — compatibility bridge/fallback | Existing public BVM bridge already correct | 0.3.47 | No change | Menu/runtime regression passed | Agreement Packets screen and BVM shell passed | PASS |
| Data Tools | 0.5.53 | Active but dependency-suppressed | FAIL — false dependency detection and partial initialization | Dependency check recognized only legacy VMS; 33 function, one class, and three constant consumers still selected legacy symbols directly | 0.5.54 | Public-first function/class/constant resolver with legacy fallback; false notice removed | 23/23 runtime checks passed at 0.5.54 | Menu, reports/import surfaces, REST routes, and dependency state passed | PASS — compatibility bridge/fallback |
| Events Slider | 1.0.9 | Active | FAIL — partial initialization | Nine status/calendar/reschedule/cancellation/ticketing calls and one cache-bust constant never selected public BVM APIs; raw fallbacks masked some failures | 1.0.10 | Public-first API/constant resolution with legacy fallback | Exact homepage returned two identical 200 responses with slider markup | Exact canonical homepage returned two identical cached 200 responses with slider markup | PASS — compatibility bridge/fallback |
| Express Bar / Bar Menu | 0.6.24 | Active | PASS — compatibility bridge/fallback | Existing BVM bridge and cache repair already correct | 0.6.24 | No change | Menu/runtime and homepage marker passed | Admin screens, event CTA, dedicated page, and open state passed | PASS |
| Investor Portal | 0.2.2 | Active | FAIL — partial initialization | Admin registry plus 19 event-plan/reporting/ticketing/staffing/goals/admissions APIs and one path constant selected only legacy symbols | 0.2.3 | Public-first registry/API/path resolver with legacy fallback | Registry/runtime passed | Portal/BVM shell passed; read-only Event Plan 2534 calculation resolved through public BVM to the Data Tools reporting model | PASS — compatibility bridge/fallback |
| Meta Ads Builder | 0.1.105 | Active but locked | FAIL — false dependency detection / complete suppression | Gate required legacy `VMS_VERSION`, `vms_core`, module-registry functions, and legacy path/URL/capability constants | 0.1.105.1 | Public module registry and public-first runtime resolver with legacy fallback; no campaign behavior change | Gate enabled; builder/menu/REST/cron checks passed | Marketing & Social builder, performance, and settings surfaces loaded; false reactivation notice absent | PASS — compatibility bridge/fallback |
| Ops Console Premium | 0.1.65 | Active | FAIL — partial initialization | Ten event-plan/venue/admission/pass/admin calls and one venue constant selected only legacy symbols | 0.1.65.1 | Public-first API/constant resolver with legacy fallback | Canonical staging lineage 0.1.67 passed and retained pre-existing 0.1.66 PWA work | Members screen, BVM navigation, REST routes, and integrations passed at 0.1.65.1 | PASS — compatibility bridge/fallback |
| Refer-a-Friend | 0.2.5 | Active | FAIL — partial initialization | Admin-page registry, BVM shell, and public calendar URL called only three legacy APIs | 0.2.6 | Public-first resolver with legacy fallback | Registry/runtime passed | Existing page/data remained visible in the BVM shell; no error | PASS — compatibility bridge/fallback |
| Sponsorships | 0.1.7.1 | Active | PASS — independent/standalone fallback | Uses WordPress/TEC-compatible fallback and does not require historical VMS | 0.1.7.1 | No runtime change | Menu/shortcode regression passed | Sponsorship page returned 200 with package/inquiry surface; stored row counts preserved | PASS |
| Event Venue Map Modal | 1.2.4 | Active | PASS — independent/standalone | TEC/content integration has no VMS-core dependency | 1.2.4 | No change | Class/hook regression passed | The Alternatives page contained card, modal, and asset markers | PASS |
| Fill Dates (`vms-fill-dates-0.1.4` basename) | 0.1.8 | Inactive | INACTIVE BY DESIGN | Previously released BVM-compatible package intentionally not active on SR.com | 0.1.8 | No change; not activated | Not part of core switch | Remained inactive | INACTIVE BY DESIGN |
| Commerce Discounts | 0.2.12 | Inactive | INACTIVE BY DESIGN | Previously released package intentionally not active on SR.com | 0.2.12 | No change; not activated | Activation state preserved | Remained inactive | INACTIVE BY DESIGN |
| Checkout Policies | 0.1.8 | Inactive | INACTIVE BY DESIGN | Installed but not part of the active SR.com runtime | 0.1.8 | No change; not activated | Activation state preserved | Remained inactive | INACTIVE BY DESIGN |

No remaining installed production add-on is classified `FAIL` or `BLOCKED`.

## 3. Source and release reconciliation

- Agreements 0.3.47, Data Tools 0.5.53, Events Slider 1.0.9, Investor Portal 0.2.2, Refer-a-Friend 0.2.5, Event Venue Map 1.2.4, Checkout Policies 0.1.8, Fill Dates 0.1.8, and Commerce Discounts 0.2.12 matched their corresponding local source or tracked release artifact before this sweep.
- Express Bar 0.6.24 matched canonical runtime source; production retained two unrelated older remote-only documentation files.
- Meta Ads production 0.1.105 matched `vms-meta-ads-0.1.105-final-verification.zip`. Production received the narrow 0.1.105.1 patch. The public-BVM repair was independently carried from the unrelated local 0.1.106 head to canonical 0.1.107; 0.1.107 was not deployed to production.
- Ops production 0.1.65 matched `vms-ops-console-premium-0.1.65.zip`. Production received the narrow 0.1.65.1 patch. The same repair was independently carried from the pre-existing staging/local 0.1.66 PWA head to canonical 0.1.67; staging retained that lineage and production did not receive the unrelated PWA change.
- Production Sponsorships 0.1.7.1 is its older 0.1.7 package plus four deployed hotfix files; no exact 0.1.7.1 local archive exists. Its runtime was compatible and remained unchanged. This is the remaining packaging debt.

## 4. Patch and package evidence

All eight patches apply to their stated untouched baselines and reproduce the candidate trees byte-for-byte.

| Release line | Patch SHA-256 | ZIP SHA-256 | Files | Result |
| --- | --- | --- | ---: | --- |
| Data Tools 0.5.53 → 0.5.54 | `8bb121b0f4cc6a5eedf402bd65d2a6dde5c6a9a349ca555fdd1b1e6a2dea0d91` | `d3dc1d9ed7f74aca0c09c9f8f2602a72de4714e97f6e70b1c231f161f5cae6aa` | 78 | Integrity/reproducibility PASS |
| Events Slider 1.0.9 → 1.0.10 | `88097fda99455c97fac901f6910a072bae48106c7df2e44d08b40c1db3f1769a` | `0e770dddb184b5a05365de58a6e7967a0a670a730b6ab078acd16d055c84d1ab` | 5 | Integrity/reproducibility PASS |
| Meta Ads production 0.1.105 → 0.1.105.1 | `3a1ba66cdc3d480cb088ff7d4c7880165754b7584e6de92b9df47c782214ca9f` | `5107a2ab5f1bc59829b45e1b8051d3084019ab1ff4e685656ca79e6ca8b0cd48` | 144 | Integrity/reproducibility PASS |
| Meta Ads canonical 0.1.106 → 0.1.107 | `a26bea1b46a4838711f9947bbdb70e27f1364ac2ebf021c15413b3e81f0326d8` | `32bfec48d6daba86e0a731914e94da4cf1da8bb3a63cfe895cf9700151659e1b` | 144 | Integrity/reproducibility PASS; not deployed to production |
| Refer-a-Friend 0.2.5 → 0.2.6 | `8b1814bb18788a3d1740f50ddd47204b25a1ff5d9b2ddf90a791279374f760a6` | `6aa2ae48daab610284df5175bd9ef94592ea7fa926fd26b6dde5c00eb3ef0820` | 17 | Integrity/reproducibility PASS |
| Investor Portal 0.2.2 → 0.2.3 | `35bbb5dae70e6fcaafd61be6e3c2702401161072dc6000ddcf5c44db4f1e047d` | `22269b67de3128281ebe33a08b16d5ce4aa61821e3e0176de329a6f09b6aeb65` | 8 | Integrity/reproducibility PASS |
| Ops production 0.1.65 → 0.1.65.1 | `631ae3667871f377e52b1c4691b86adc008eae95238b6adf7d5f883158c3267f` | `7d08311b9483f52f55a619736e342c5b28c4e8ed7fce4ac46a10476f713fe0b0` | 61 | Integrity/reproducibility PASS |
| Ops canonical 0.1.66 → 0.1.67 | `e29905d194f36114633898766851539cae3a83ac363850ea806be0bf96098da7` | `746d46964fb11df0b246a2cbdffa7ae4e2fd3ecbd4746174a047bdba808b4c05` | 61 | Integrity/reproducibility PASS; deployed to staging only |

Every ZIP uses a canonical top-level plugin directory, passed `unzip -t`, and was reproduced byte-for-byte from its exact source tree in a second normalized build.

## 5. Test and deployment evidence

- Static public-runtime resolution scan: PASS for both production candidates and canonical heads. It covered 91 legacy function consumers, one legacy class consumer, and ten legacy constant consumers across the six repaired add-ons and confirmed every selected `bvmgr_*`/`BVMGR_*` target exists in the exact production BVM 1.2.0 tree.
- Disposable WordPress/PHP 8.3 load-order suite: PASS in core-first and add-ons-first order. Each final scenario passed 23 checks, including lifecycle, public BVM identity, historical VMS absence, MAB, Data Tools, Slider, RAF, Investor, Ops, Agreements, Express Bar, Sponsorships, Event Venue Map, notices, menus, routes, cron, and captured runtime errors.
- Known isolated fixture noise: early translation notices from WooCommerce, BVM, and Ops plus three WordPress update warnings caused by deliberately blocked external HTTP. The probe excludes only those known harness conditions; no add-on fatal, parse error, unhandled exception, or business-runtime warning remained.
- Staging: exact deployed bytes passed; staging switched from its pre-existing active legacy VMS 1.1.0 to exact public BVM 1.2.0, leaving all 32 activation slots unchanged except that one core basename replacement. Final staging probe passed 23/23. The exact homepage returned two identical 200 bodies, SHA-256 `e00c27990bb2ad7926f3e016e33393992db2b1ab4c357033d50483373c6b1c94`, with Slider and Express Bar markers.
- Production: all six candidate trees matched the deployed bytes, all 137 PHP files passed syntax checks, and the active-plugin serialization SHA-256 remained `f48e2af9fa228b6397e532845cebef999bd468b648b9a89ea22af34bb702c908`. Final production probe passed 23/23.
- Signed-in production UI: MAB, Marketing & Social, Data Tools, Agreements, Referrals, Investor Financials, Ops Members, Express Bar, and Bar Menu loaded without critical errors. MAB and Data Tools no longer emitted their legacy-core notices. RAF, Investor, and Ops rendered through BVM navigation/registry paths.
- Investor clarification: the visible selected event says its cached snapshot has never been refreshed, so some stored display values correctly remain “Not Available.” A read-only live calculation for Event Plan 2534 resolved `bvmgr_event_command_center_get_ticket_reporting_truth`, returned an available Data Tools reporting model, and found non-empty ticket quantity and revenue. No metrics refresh or financial write was performed.

## 6. Data and activation preservation

- MAB settings serialized SHA-256 stayed `e249c1c3add9fc41f8a61a8aab50176627ede6038aed8a99e02ca05d00713872`; MAB DB-version SHA-256 stayed `4b227777d4dd1fc61c6f884f48641d02b4d121d3fd328cb08b5531fcacdabf8a`.
- MAB row counts remained builds 44, templates 5, tiers 46, and audit 1472.
- Investor financial rows remained 1; Ops presence rows remained 1231.
- Sponsorship rows remained applications 2, packages 5, assignments 0, and assets 0.
- RAF remained visible with its existing record set; post-deployment counts were codes 150, referrals 1, credits 0, rewards 1, and claims 0. Its expected version marker advanced with the in-place update.
- Production active/inactive state did not change. Staging changed only its venue-management core basename as explicitly required for a production-like BVM-only verification.

## 7. Public and cache verification

- Only `/wp-content/cache/supercache/serenaderange.com/index-https.html` was removed. The 16 child URL cache directories were left untouched; no cache-wide purge occurred.
- The first exact anonymous `https://serenaderange.com/` request regenerated the homepage and the second returned the same body. Both were HTTP 200, SHA-256 `9c3f83cd1da039ce79af35818f4df5b4bc46babef11eec75cb64845cbf39cd3d`, with Slider and Express Bar markers.
- Exact `https://serenaderange.com/event/the-alternatives/` returned 200 with `.vmseb-event-cta`, “Skip the line at the bar.”, Event Plan 2534, the venue-map card/modal/assets, and unchanged ticket/event rendering.
- Exact `https://serenaderange.com/express-bar/?event_plan_id=2534` returned 200, identified The Alternatives, and showed Express Bar Status Open, opening Mon Aug 31 at 7:42 AM and last call Sat Sep 5 at 6:00 PM.
- Exact `https://serenaderange.com/sponsorship-opportunities/` returned 200 with the sponsorship package/inquiry surface.

## 8. Logs and rollback

- Production `wp-content/debug.log` stayed absent.
- The MAB-local `error_log` stayed byte-identical at 5,294 bytes, SHA-256 `32f89a3c23665f5a54a26659d3521c66a8bac67f93333d1dfff71419ff951df9`.
- The site error log baseline was 15,078,286 bytes. One first-attempt WP-CLI read-only Investor calculation was run without its required admin preload and appended one 2,185-byte `call_user_func` TypeError/stack entry. It was confined to WP-CLI; the corrected admin-preloaded calculation passed. The remaining 501 appended bytes were four expected `[VMS DT TRACE]` bootstrap lines. There were no additional fatal, warning, notice, or deprecation markers and no request-facing reproduction. Final site log size was 15,080,972 bytes, SHA-256 `b1cfe2fa270885822ec5735da7b0205b2d4445d032cc88fc78b2edd49c905728`.
- Owner-only rollback material is outside both web roots at `/home/coney/bvm-addon-registration-sweep-rollbacks/2026-09-01-show-readiness`. Directory mode is 0700; all archives/receipts are 0600; 13 tar archives and two JSON receipts passed integrity checks. No rollback was required.

## 9. Repository boundary and remaining debt

- The non-Git canonical add-on trees contain the released repairs. This repository records reproducible patches, deterministic ZIPs, the static/runtime harnesses, and this closeout evidence.
- No BVM mirror/live synchronization was needed because no BVM runtime file changed.
- No push, tag, WordPress.org submission, reviewer reply, cache-wide purge, order, campaign action, financial refresh, or production activation-state change occurred.
- Remaining non-blocking debt: create an exact canonical Sponsorships 0.1.7.1 release archive; retire stale VMS-facing copy where separately authorized; retain the previously recorded Express Bar returned-hook and Data Tools menu-ownership hardening candidates. None is an installed BVM-recognition blocker.

Final conclusion: **the installed SR.com companion ecosystem recognizes public BVM 1.2.0 before the September 5 show; no historical VMS reactivation is required.**
