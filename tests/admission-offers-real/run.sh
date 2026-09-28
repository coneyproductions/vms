#!/bin/sh
set -eu

if [ "${BVM_DISPOSABLE_DB_GUARDED:-}" != "1" ] || [ -z "${BVM_DISPOSABLE_DB_SOCKET:-}" ]; then
	echo "guarded disposable database required" >&2
	exit 2
fi

runtime_root='/private/tmp/bvm-authority-integration-20260906/runtime/admission-offers-certification'
db_password=${BVM_DISPOSABLE_DB_PASSWORD:-}
db_password_b64=$(printf '%s' "$db_password" | /usr/bin/base64)
mysql_command='/opt/homebrew/bin/mysql'
if [ -e "$runtime_root" ]; then
	echo "exclusive certification runtime already exists" >&2
	exit 2
fi
mkdir -m 700 "$runtime_root"
cleanup() {
	MYSQL_PWD="$db_password" "$mysql_command" --no-defaults --protocol=socket --socket="$BVM_DISPOSABLE_DB_SOCKET" -uroot -e 'DROP DATABASE IF EXISTS bvm_admission_offers_cert' >/dev/null 2>&1 || true
	rm -rf "$runtime_root"
}
trap cleanup EXIT HUP INT TERM

repo_root=$(CDPATH= cd -- "$(dirname "$0")/../.." && pwd)
public_root=${BVM_ADMISSION_OFFERS_PUBLIC_ROOT:-$(CDPATH= cd -- "$repo_root/../../../.." && pwd)}
pre_phase_root=${BVM_ADMISSION_OFFERS_PRE_PHASE_ROOT:-$(CDPATH= cd -- "$repo_root/../vms-github-reconcile" && pwd)}
plugin_root=${BVM_ADMISSION_OFFERS_PLUGIN_ROOT:-$repo_root}
case "$plugin_root" in
	"$repo_root"|"$public_root/wp-content/plugins/vms") ;;
	*) echo "certification plugin root is outside the approved mirror/live pair" >&2; exit 2 ;;
esac
if [ -n "$(git -C "$pre_phase_root" status --short)" ] || [ -e "$pre_phase_root/includes/modules/admission-offers" ] || grep -q "admission-offers/admission-offers.php" "$pre_phase_root/includes/modules/load.php"; then
	echo "pre-Phase-A rollback source mismatch" >&2
	exit 2
fi

wordpress_root="$runtime_root/wordpress"
mkdir "$wordpress_root"
rsync -a --exclude='wp-content' --exclude='wp-config.php' "$public_root/" "$wordpress_root/"
mkdir -p "$wordpress_root/wp-content/plugins"
ln -s "$plugin_root" "$wordpress_root/wp-content/plugins/bvm"
cat > "$wordpress_root/wp-config.php" <<EOF
<?php
define('DB_NAME', 'bvm_admission_offers_cert');
define('DB_USER', 'root');
define('DB_PASSWORD', base64_decode('${db_password_b64}'));
define('DB_HOST', 'localhost:${BVM_DISPOSABLE_DB_SOCKET}');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');
define('AUTH_KEY', 'disposable-auth-key');
define('SECURE_AUTH_KEY', 'disposable-secure-auth-key');
define('LOGGED_IN_KEY', 'disposable-logged-in-key');
define('NONCE_KEY', 'disposable-nonce-key');
define('AUTH_SALT', 'disposable-auth-salt');
define('SECURE_AUTH_SALT', 'disposable-secure-auth-salt');
define('LOGGED_IN_SALT', 'disposable-logged-in-salt');
define('NONCE_SALT', 'disposable-nonce-salt');
define('WP_DEBUG', false);
define('DISABLE_WP_CRON', true);
\$table_prefix = 'wp_';
require_once ABSPATH . 'wp-settings.php';
EOF

MYSQL_PWD="$db_password" "$mysql_command" --no-defaults --protocol=socket --socket="$BVM_DISPOSABLE_DB_SOCKET" -uroot -e 'CREATE DATABASE bvm_admission_offers_cert CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci'
php83='/opt/homebrew/opt/php@8.3/bin/php'
"$php83" /opt/homebrew/bin/wp core install --path="$wordpress_root" --url='http://admission-offers.invalid' --title='Admission Offers Certification' --admin_user='cert-admin' --admin_password='cert-password-123!' --admin_email='cert@example.invalid' --skip-email --quiet
"$php83" /opt/homebrew/bin/wp plugin activate bvm --path="$wordpress_root" --quiet
"$php83" "$repo_root/tests/admission-offers-real/certify.php" "$wordpress_root" > "$runtime_root/certification.json"

rollback_expected=$("$php83" -r '$j=json_decode(file_get_contents($argv[1]),true); echo base64_encode(json_encode($j["rollback_expected"]));' "$runtime_root/certification.json")
rm "$wordpress_root/wp-content/plugins/bvm"
ln -s "$pre_phase_root" "$wordpress_root/wp-content/plugins/bvm"
rollback_result=$("$php83" "$repo_root/tests/admission-offers-real/rollback-check.php" "$wordpress_root" "$rollback_expected")
result_json=$("$php83" -r '$j=json_decode(file_get_contents($argv[1]),true); $j["rollback"] = json_decode($argv[2],true); echo json_encode($j, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), "\n";' "$runtime_root/certification.json" "$rollback_result")
if [ -n "${BVM_ADMISSION_OFFERS_RESULT:-}" ]; then
	case "$BVM_ADMISSION_OFFERS_RESULT" in
		/private/tmp/bvm-authority-integration-20260906/*) ;;
		*) echo "result path is outside disposable evidence root" >&2; exit 2 ;;
	esac
	if [ -e "$BVM_ADMISSION_OFFERS_RESULT" ]; then
		echo "result evidence already exists" >&2
		exit 2
	fi
	printf '%s\n' "$result_json" > "$BVM_ADMISSION_OFFERS_RESULT"
fi
printf '%s\n' "$result_json"
