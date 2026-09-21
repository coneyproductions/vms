# P1 Disposable Fixture Contract

The P1 A–D suites run only through the guarded disposable database supervisor and `scripts/test-debt-wordpress.py --p1-fixtures`. They must not use the normal Local database, an installed BVM source tree, staging, or production.

## Pinned retained inputs

- `docs/addon-compatibility/artifacts/vms-data-tools-0.5.54.zip` — SHA-256 `d3dc1d9ed7f74aca0c09c9f8f2602a72de4714e97f6e70b1c231f161f5cae6aa`
- `docs/addon-compatibility/artifacts/vms-agreements-0.3.48.zip` — SHA-256 `dbdb32b93d37b8a52868d14da704e1ea758c29edd093d0caeac7fa7a2477b24b`

The runner verifies both hashes, rejects absolute/traversal/symlink archive entries, extracts them beneath its owned temporary directory, and passes explicit source roots to each of the three P1-C scenarios. It does not activate either companion.

## Invocation

From the repository root, substitute only fresh disposable tool/input paths:

```text
python3 scripts/lib/bvm-disposable-db.py --server MYSQLD_BINARY --receipt EVIDENCE/supervisor.json -- python3 scripts/test-debt-wordpress.py --evidence EVIDENCE --php PHP_BINARY --mysql MYSQL_CLIENT --wp-cli WP_CLI_PHAR --core CLEAN_WORDPRESS_CORE --addons DISPOSABLE_PREREQUISITE_SOURCE --label UNIQUE_LABEL --p1-fixtures wporg-round2-vendor-tax-authorization wporg-round2-webhook-safety wporg-round2-portal-hook-cutover wporg-round2-admission-qr-privacy
```

The runner creates a fresh `bvm_integration_source` schema, installs WordPress at its fixed guarded root, intercepts mail and non-owned HTTP, runs WP-CLI with plugins/themes skipped for the P1 cases, records before/after database censuses, then removes its WordPress tree, companion extracts, private fixtures, server process, and schema through the supervisor. P1-D writes QR evidence only to the explicit evidence directory. No real email, webhook, operational database, or external environment is used.
