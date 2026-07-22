# Public Release Packaging

This document defines the canonical Backstage Venue Manager public-release build and explains how it differs from the existing deployment workflow.

## Canonical command

Run this from the mirror plugin root, for example `packages/vms-github-reconcile/`:

```bash
php scripts/build-public-release.php
```

That command:

- stages a clean public-release copy of the current checkout
- reads [`release-public-excludes.txt`](../release-public-excludes.txt) directly
- runs the release preflight/regression checks
- builds a ZIP with one top-level `backstage-venue-manager/` directory
- validates the finished ZIP
- writes both a text report and a JSON report

Common options:

```bash
php scripts/build-public-release.php --output-dir ./dist
php scripts/build-public-release.php --output-dir "/tmp/VMS Release Output"
php scripts/build-public-release.php --provenance-manifest ./docs/provenance/releases/0.2.24.747.json
php scripts/build-public-release.php --provenance-manifest ./docs/provenance/releases/0.2.24.747.json --skip-release-tests
php scripts/build-public-release.php --force
php scripts/build-public-release.php --dev-build
php scripts/build-public-release.php --allow-dirty
```

`--force` is required if the target ZIP or report files already exist.

When a provenance manifest is supplied, the builder also:

- normalizes staged runtime mtimes from the manifest before zipping
- verifies the staged package contents against the manifest file list and digests
- verifies the finished ZIP filename and SHA-256 against the manifest

`--skip-release-tests` is intended for standalone repository clones that are not sitting inside a WordPress install. It skips the regression scripts that expect `wp-load.php`, while still preserving the packaging, integrity, and provenance checks.

## Output

Default output directory:

```text
./dist
```

Default filenames:

- `dist/backstage-venue-manager-<version>-public-release.zip`
- `dist/backstage-venue-manager-<version>-public-release.report.txt`
- `dist/backstage-venue-manager-<version>-public-release.report.json`

The ZIP is not uploaded or deployed by this command.

## Public package identity

- Public slug, public ZIP root, and plugin header `Text Domain`: `backstage-venue-manager`
- Public bootstrap path inside the ZIP: `backstage-venue-manager/vendor-management-system.php`
- Internal compatibility identifiers may remain `vms`, including `VMS_PLUGIN_SLUG`, `vms-build.txt`, and the sibling live local plugin folder `../../vms`
- The source checkout folder does not determine the public package root

## Release-path inventory

### Public release packaging and validation

These files are now authoritative for public customer ZIPs:

- [`scripts/build-public-release.php`](../scripts/build-public-release.php)
- [`scripts/verify-public-release-provenance.php`](../scripts/verify-public-release-provenance.php)
- [`scripts/lib/public-release.php`](../scripts/lib/public-release.php)
- [`tests/check-package-integrity.php`](../tests/check-package-integrity.php)
- [`release-public-excludes.txt`](../release-public-excludes.txt)
- `docs/provenance/releases/*.json`

Related audit/history references:

- [`docs/public-release-audit-2026-06-18.md`](./public-release-audit-2026-06-18.md)
- root `BUILD-NOTES-*.md`
- root `vms-test-plan-*.md`

The build notes and test plans remain historical references. They are not packaging automation.

### Remote deployment and environment-copy workflow

These remain authoritative for staging/production path discovery and rsync deployment, not for public ZIP creation:

- [`../OPERATIONS.md`](../../OPERATIONS.md)
- [`../../scripts/check-remote`](../../scripts/check-remote)
- [`../../scripts/check-staging`](../../scripts/check-staging)
- [`../../scripts/check-prod`](../../scripts/check-prod)
- [`../../scripts/deploy`](../../scripts/deploy)
- [`../../.codex/project-context.yml`](../../.codex/project-context.yml)
- [`../../.codex/deploy-excludes.txt`](../../.codex/deploy-excludes.txt)

### Historical artifacts and ad hoc packaging traces

These exist in the workspace but are not authoritative build entry points:

- `VMS ZIP ARCHIVES/`
- `prod-deploy-artifacts-*`
- `tmp-vms-*`
- `tmp-prod-package-*`
- `vms-local-backup-*`

## Public excludes vs deploy excludes

The two exclusion manifests are intentionally different.

`release-public-excludes.txt`:

- defines what is allowed in a customer-facing plugin ZIP
- excludes docs, tests, build scripts, provenance records, archives, temp files, editor metadata, and release output
- is consumed directly by the canonical public-release builder

`.codex/deploy-excludes.txt`:

- defines what should not be copied during remote rsync deployment from the full `wp-content/plugins` workspace
- excludes workspace-only tooling such as `.codex/`, `scripts/`, and top-level operational files
- is consumed by `scripts/deploy`

Do not make them interchangeable. A deployment sync may legitimately copy files that must never ship in a public ZIP.

## Preflight checks

The canonical build runs these checks automatically.

### Metadata and consistency

- plugin header version
- `VMS_VERSION`
- `vms-build.txt`
- public package slug/text-domain alignment
- vendor-core migration pointer alignment between `includes/db/migrations.php` and `includes/activation.php`
- version-matched `BUILD-NOTES-<version>.md`
- readme stable tag, if a readme exists
- plugin header `Requires PHP` / `Requires at least` metadata, if declared

The build fails on real inconsistencies. It does not treat intentionally independent schema markers as plugin-version mismatches.

### Regression and syntax checks

- `php -l` across staged distributable PHP files
- `node --check` across staged distributable JavaScript files when Node is available
- `tests/admissions-rest-permissions.php`
- `tests/ticket-claims-assignee-validation.php`
- `tests/ticket-checkout-safety-hardening.php`
- `tests/event-plan-legacy-ticketing-integration-smoke.php`
- `tests/event-plan-ticket-ui-overrides-isolated.php`

### Package integrity checks

The staged package tree and the finished ZIP are both checked for:

- one top-level `backstage-venue-manager/` root
- required runtime files and directories
- manifest-excluded paths
- path traversal entries
- developer-machine path leaks
- obvious local-environment URLs
- high-confidence credential markers
- zero-byte runtime PHP/JS files
- nested archives
- symlink entries

### Non-destructive WordPress load smokes

When both `wp` and a local `wp-load.php` are available, the builder also runs staged-package load smokes through WP-CLI while skipping selected dependency plugins:

- baseline public package load
- without WooCommerce
- without The Events Calendar/Event Tickets stack
- without optional ticketing add-ons

These checks are non-destructive and do not execute activation hooks.

## Dirty-tree behavior

If the plugin lives inside a git worktree:

- clean tree: build proceeds
- dirty tree: build fails by default
- dirty tree with `--allow-dirty`: build proceeds and records a warning
- dirty tree with `--dev-build`: build proceeds, appends `-dev` to the artifact name, and records a warning

If the source is not inside a git worktree:

- the build records git state as `unknown`
- the artifact report warns that dirty/clean status could not be verified

## Validating an already-built ZIP

Validate a finished release ZIP directly:

```bash
php tests/check-package-integrity.php dist/backstage-venue-manager-0.2.24.746-public-release.zip
```

Validate a staged package directory:

```bash
php tests/check-package-integrity.php /path/to/staged/backstage-venue-manager
```

Do not point `tests/check-package-integrity.php` at the live source tree if you expect a PASS. The source tree intentionally contains docs, tests, and scripts that must be excluded from the public package.

Verify a ZIP or extracted package against a recorded provenance manifest:

```bash
php scripts/verify-public-release-provenance.php \
  --target dist/backstage-venue-manager-0.2.24.747-public-release.zip \
  --manifest docs/provenance/releases/0.2.24.747.json
```

That provenance check is stricter than the package-integrity scan. It proves that the artifact matches a specific recorded release, not merely that it is a structurally safe package.

## Optional compatibility matrix

Activation, upgrade, uninstall, and dependency-compatibility checks are intentionally separate from the static packaging builder.

Run the disposable compatibility harness from the mirror plugin root:

```bash
php scripts/test-release-compatibility.php \
  --artifact=dist/backstage-venue-manager-0.2.24.746-public-release.zip \
  --baseline-artifact=../vms-0.2.24.725-checkout-hot-path.zip \
  --expected-sha256=e55302b89eb56a7f2808d94e901c8fb16f96b069352cee5fe0b961883724224d \
  --wordpress-source="/path/to/wordpress-root" \
  --output-dir=./test-results \
  --force
```

That command:

- creates disposable WordPress sites and disposable databases
- installs the packaged ZIP rather than the source-tree plugin
- exercises dependency and lifecycle scenarios
- writes both a text report and a JSON report

The compatibility harness is opt-in because it mutates disposable databases, installs plugins, and may take several minutes.

The packaged ZIP is expected to install as `backstage-venue-manager/vendor-management-system.php`. The compatibility harness still recognizes historical/internal `vms/vendor-management-system.php` baselines and local live installs when comparing lifecycle state.

It does not:

- deploy anything
- touch the primary local database when configured correctly
- change the normal packaging command

Document the exact compatibility report that was executed alongside any externally shared ZIP. The static package report and the compatibility report should be reviewed separately.

## Plugin Check path examples

Packaged or extracted public package:

```bash
wp plugin check /path/to/extracted/backstage-venue-manager --slug=backstage-venue-manager
```

Local installed live tree:

```bash
wp plugin check vms --slug=backstage-venue-manager
```

## Result semantics

- `PASS`: required check succeeded
- `FAIL`: required check failed; the build stops and exits nonzero
- `WARN`: build may continue, but the report calls out a manual-review item
- `SKIP`: the check was intentionally not run because the prerequisite surface does not exist in the current environment

## Manual release checklist

Before sharing a ZIP externally:

1. Run `php scripts/build-public-release.php`.
2. Read the generated `.report.txt` file.
3. Resolve every `FAIL`.
4. Review every `WARN`.
5. Confirm the artifact name, SHA-256, and plugin version match expectations.
6. If activation-hook behavior, upgrade behavior, or uninstall policy must be proven, run `php scripts/test-release-compatibility.php` against the finished ZIP and review the generated compatibility report.
7. Review any compatibility `FAIL`, `WARN`, `SKIP`, or `BLOCKED` results before distribution.
8. Only after that manual review should the ZIP be distributed.

## Rollback / safety

This workflow does not deploy anything.

If a build fails:

- keep the previous known-good ZIP
- fix the reported issue
- rerun the build

If an artifact should be discarded:

- delete the generated files from `dist/` (or the chosen output directory)

No SSH, rsync, staging sync, or production mutation is performed by the public-release builder.
