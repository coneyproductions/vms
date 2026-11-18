# BVM 1.3.21 cumulative source preservation

**Source-only integration proposal; no deployment, source designation or issue closure.**

| Identity | Value |
| --- | --- |
| Version | `1.3.21-cumulative.20261117.61021431` |
| Complete frozen ZIP SHA-256 | `e31cf6ece94470995b0e89dfadb19d0019dba6eaeb2d373112509be7e9efdc93` |
| Private approved source commit (identifier only) | `bd49d97ba4c486f68c315751fedbc737b3911acd` |
| Full private 432-file BVM tree | `6e3797f45ad5eb8663f30e86a35472536f636582` |
| Public 430-file BVM tree | `43a319980f6555c6863696fb7aa7e3a6ac1fa415` |
| Public integration base | `58ad78d0826200b5875a6f744065d4866b6f37c8` |

The new public export commit is the commit introducing this file and its exact
`backstage-venue-manager/` tree. Record that commit and any separately approved
merge commit in the issue after publication/integration. This private source hash
is a provenance identifier; its 16-commit reachable history is not imported.

The source combines the accepted #102 `.1026` persistence/revision correction and
qualified #143 complimentary-disclosure restoration on the complete `.1411`
Production baseline. Seven functional file bodies match those accepted inputs;
four release/version markers identify this cumulative version. The other 421 full
release file bodies are baseline-identical. This import changes no BVM source bytes.

[Complete file manifest](content-manifest.tsv) ·
[Public source/blob/mode manifest](public-source-manifest.tsv) ·
[Accepted test identities](accepted-tests.tsv) ·
[Machine-readable provenance and private-asset hashes](provenance.json) ·
[Source baseline and complete-package procedure](../../../../BVM-SOURCE-BASELINE.md)

The two excluded assets are `artifacts/business-discount-qr/customer-offer.png`
and `artifacts/business-discount-qr/operator-distribution.png`. Their exact hashes
and sizes remain recorded; their bytes and any containing private ancestors are
absent from this import. No alternative 430-file deployment ZIP is supplied.

## Qualification and permanent evidence

- [Final #102 Local acceptance](https://github.com/coneyproductions/vms/issues/102#issuecomment-6965737123).
- [Independent cumulative source review](https://github.com/coneyproductions/vms/issues/6#issuecomment-6966184039).
- [Bounded Staging findings](https://github.com/coneyproductions/vms/issues/6#issuecomment-6967727986).
- [Staging smoke accepted for Production preflight](https://github.com/coneyproductions/vms/issues/6#issuecomment-6968182535).
- [Exact Production authorization](https://github.com/coneyproductions/vms/issues/6#issuecomment-6968830742).
- [Production deployment verification and independent active-version check](https://github.com/coneyproductions/vms/issues/6#issuecomment-6969206721).

The operator verified 432 installed files, effective sampled admin/frontend code,
protected guards/companions and bounded nonmutating browser checks. The independent
review confirmed the active Production version and active Ops/Express Bar status;
it did not independently repeat all file, web-worker or backup checks. Owner
acceptance is recommended in that comment; completion/closure is not inferred here.

Actual upcoming/historical ordinary linked-vendor coverage, provider/category/count
parity and transactional browser paths remain partially unverified as explicitly
accepted for the release decision. Historical admin-preview disclosures and available
read-only controls passed within their scope. The existing-account payment-preparation
notice was observed without order/ticket action or diagnosis; its generating script
is source-identical to `.1411`. King George's historical paid-count discrepancy is
separate and unresolved. Do not infer new losses, payout accuracy or full Production
certification. Preserve the original 134-issue Phase 1 snapshot unchanged.

## Existing synthetic regression entry points

Run only in an isolated local checkout with PHP/Node, never as a live WordPress
operation. Two helper files are included by the tests; they are not standalone suites.
No new framework/dependencies or test redevelopment is introduced.

```sh
php tests/issue102-save-config.php
php tests/issue102-template-clear.php
php tests/issue102-atomic-delete.php
php tests/issue102-clear-audit.php
node tests/issue102-editor-save.cjs
node tests/issue102-editor-template-clear.cjs
php tests/issue143-vendor-comp-breakdown.php
```

The tests are preserved unchanged. Existing accepted Local/native/cumulative evidence
is reused for this import; no hosted or new standalone #102 test cycle is claimed.
