#!/bin/sh

set -eu

repo_root=$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)
wordpress_source_root=${BVM_COMPAT_WP_ROOT:-$(CDPATH= cd -- "$repo_root/../../../.." && pwd)}
php_bin=${BVM_COMPAT_PHP_BIN:-$(command -v php)}
wp_cli_bin=${BVM_COMPAT_WP_CLI_BIN:-$(command -v wp)}
evidence_dir=${BVM_WAVE2C_EVIDENCE_DIR:?BVM_WAVE2C_EVIDENCE_DIR is required}
prepared_dir=${BVM_WAVE2C_PREPARED_SPONSOR_DIR:?BVM_WAVE2C_PREPARED_SPONSOR_DIR is required}
helper=$repo_root/tests/addon-compatibility/wave2c-sponsorships-migration.php
state_file=$evidence_dir/bvm-test-containment-state.json
options_backup=$evidence_dir/sponsorship-options-backup.json
tables_backup=$evidence_dir/sponsorship-tables-backup.sql
temp_base=${TMPDIR:-/tmp}
runtime_root=$(mktemp -d "$temp_base/bvm-wave2c-sponsor-runtime.XXXXXX")
database_name=bvm_wave2c_sponsor_$("$php_bin" -r 'echo bin2hex(random_bytes(6));')
database_created=no

safe_remove_runtime() {
	case "$runtime_root" in
		"$temp_base"/bvm-wave2c-sponsor-runtime.*)
			if [ -d "$runtime_root" ]; then
				find "$runtime_root" -depth -delete
			fi
			;;
		*) printf 'Refusing unsafe runtime cleanup target: %s\n' "$runtime_root" >&2; return 1 ;;
	esac
}

cleanup_on_exit() {
	exit_code=$?
	trap - EXIT HUP INT TERM
	set +e
	if [ "$database_created" = yes ]; then
		"$php_bin" "$wp_cli_bin" --path="$runtime_root" db drop --yes --quiet >/dev/null 2>&1
	fi
	safe_remove_runtime || exit_code=1
	exit "$exit_code"
}
trap cleanup_on_exit EXIT HUP INT TERM

for required in "$wordpress_source_root/wp-config.php" "$prepared_dir/vms-sponsorships.php" "$helper" "$state_file" "$options_backup" "$tables_backup"; do
	[ -r "$required" ] || { printf 'Missing migration input: %s\n' "$required" >&2; exit 2; }
done
case "$database_name" in
	bvm_wave2c_sponsor_[a-f0-9][a-f0-9]*) ;;
	*) printf 'Unsafe disposable database name: %s\n' "$database_name" >&2; exit 2 ;;
esac

export BVM_TEST_CONTAINMENT_TOKEN=$("$php_bin" -r '$s=json_decode(file_get_contents($argv[1]),true); echo $s["token"];' "$state_file")
db_name_source=$("$php_bin" "$wp_cli_bin" --path="$wordpress_source_root" config get DB_NAME --type=constant --quiet)
db_user=$("$php_bin" "$wp_cli_bin" --path="$wordpress_source_root" config get DB_USER --type=constant --quiet)
db_password=$("$php_bin" "$wp_cli_bin" --path="$wordpress_source_root" config get DB_PASSWORD --type=constant --quiet)
db_host=$("$php_bin" "$wp_cli_bin" --path="$wordpress_source_root" config get DB_HOST --type=constant --quiet)
db_charset=$("$php_bin" "$wp_cli_bin" --path="$wordpress_source_root" config get DB_CHARSET --type=constant --quiet)
[ "$database_name" != "$db_name_source" ]

rsync -a --exclude='wp-content/' --exclude='wp-config.php' "$wordpress_source_root/" "$runtime_root/"
mkdir -p "$runtime_root/wp-content/plugins/vms-sponsorships" "$runtime_root/wp-content/themes" "$runtime_root/wp-content/mu-plugins"
rsync -a "$prepared_dir/" "$runtime_root/wp-content/plugins/vms-sponsorships/"
cp "$repo_root/tests/addon-compatibility/test-containment-mu-plugin.php" "$runtime_root/wp-content/mu-plugins/bvm-test-containment-runtime.php"
cp "$state_file" "$runtime_root/wp-content/mu-plugins/bvm-test-containment-state.json"

"$php_bin" "$wp_cli_bin" config create --path="$runtime_root" --dbname="$database_name" --dbuser="$db_user" --dbpass="$db_password" --dbhost="$db_host" --dbcharset="$db_charset" --skip-salts --force --quiet
"$php_bin" "$wp_cli_bin" --path="$runtime_root" config set WP_ENVIRONMENT_TYPE local --quiet
"$php_bin" "$wp_cli_bin" --path="$runtime_root" config set DISABLE_WP_CRON true --raw --quiet
"$php_bin" "$wp_cli_bin" --path="$runtime_root" config set AUTOMATIC_UPDATER_DISABLED true --raw --quiet
"$php_bin" "$wp_cli_bin" --path="$runtime_root" config set WP_HTTP_BLOCK_EXTERNAL true --raw --quiet
"$php_bin" "$wp_cli_bin" --path="$runtime_root" db create --quiet
database_created=yes
"$php_bin" "$wp_cli_bin" --path="$runtime_root" core install --url='http://bvm-wave2c-sponsor.test' --title='Wave 2C Sponsor Migration' --admin_user='wave2c_admin' --admin_password="$("$php_bin" -r 'echo bin2hex(random_bytes(16));')" --admin_email='wave2c@example.test' --skip-email --quiet
"$php_bin" "$wp_cli_bin" --path="$runtime_root" db import "$tables_backup" --quiet

export BVM_WAVE2C_SPONSOR_OPTIONS_BACKUP=$options_backup
export BVM_WAVE2C_SPONSOR_OPERATION=import-options
"$php_bin" "$wp_cli_bin" --path="$runtime_root" eval-file "$helper" --skip-plugins --skip-themes --quiet

capture() {
	export BVM_WAVE2C_SPONSOR_OPERATION=capture
	export BVM_WAVE2C_CAPTURE_TARGET=$1
	"$php_bin" "$wp_cli_bin" --path="$runtime_root" eval-file "$helper" --skip-plugins --skip-themes --quiet
}

before=$evidence_dir/disposable-current-shape-before.json
after_one=$evidence_dir/disposable-current-shape-after-one.json
after_two=$evidence_dir/disposable-current-shape-after-two.json
comparison=$evidence_dir/disposable-migration-comparison.json
capture "$before"

export BVM_WAVE2C_SPONSOR_OPERATION=migrate
export BVM_WAVE2C_ASSERT_HTTP=yes
"$php_bin" "$wp_cli_bin" --path="$runtime_root" eval-file "$helper" --skip-plugins --skip-themes --quiet
capture "$after_one"

export BVM_WAVE2C_SPONSOR_OPERATION=migrate
export BVM_WAVE2C_ASSERT_HTTP=no
"$php_bin" "$wp_cli_bin" --path="$runtime_root" eval-file "$helper" --skip-plugins --skip-themes --quiet
capture "$after_two"

export BVM_WAVE2C_SPONSOR_OPERATION=compare
export BVM_WAVE2C_BEFORE=$before
export BVM_WAVE2C_AFTER_ONE=$after_one
export BVM_WAVE2C_AFTER_TWO=$after_two
export BVM_WAVE2C_COMPARISON=$comparison
"$php_bin" "$helper"

"$php_bin" "$wp_cli_bin" --path="$runtime_root" db drop --yes --quiet
database_created=no
safe_remove_runtime
trap - EXIT HUP INT TERM

if "$php_bin" "$wp_cli_bin" --path="$wordpress_source_root" db query "SHOW DATABASES LIKE '$database_name'" --skip-column-names --batch --raw --quiet --skip-plugins --skip-themes | grep -q .; then
	printf 'Disposable migration database remains: %s\n' "$database_name" >&2
	exit 1
fi

printf 'WAVE2C_SPONSOR_DISPOSABLE_MIGRATION_CLEANUP_PASS db=%s\n' "$database_name"
