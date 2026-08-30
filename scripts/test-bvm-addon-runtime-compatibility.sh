#!/bin/sh

set -eu

repo_root=$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)
wordpress_source_root=${BVM_COMPAT_WP_ROOT:-$(CDPATH= cd -- "$repo_root/../../../.." && pwd)}
source_plugins_root=${BVM_COMPAT_ADDON_ROOT:-$wordpress_source_root/wp-content/plugins}
php_bin=${BVM_COMPAT_PHP_BIN:-$(command -v php)}
wp_cli_bin=${BVM_COMPAT_WP_CLI_BIN:-$(command -v wp)}
temp_base=${TMPDIR:-/tmp}
runtime_root=$(mktemp -d "$temp_base/bvm-addon-compat-runtime.XXXXXX")
if [ -n "${BVM_COMPAT_OUTPUT_DIR:-}" ]; then
	output_dir=$BVM_COMPAT_OUTPUT_DIR
	mkdir -p "$output_dir"
else
	output_dir=$(mktemp -d "$temp_base/bvm-addon-compat-report.XXXXXX")
fi

probe_file=$repo_root/tests/addon-compatibility/runtime-probe.php
preload_file=$repo_root/tests/addon-compatibility/runtime-preload.php
report_builder=$repo_root/tests/addon-compatibility/build-report.php
source_manifest_builder=$repo_root/tests/addon-compatibility/source-manifest.php
source_manifest=$output_dir/source-manifest.json
scenario_index=$output_dir/scenarios.tsv
debug_log=$runtime_root/wp-content/bvm-compat-debug.log
database_name=bvm_compat_$($php_bin -r 'echo bin2hex(random_bytes(6));')
database_created=no
database_cleanup=pending
runtime_cleanup=pending

validate_database_name() {
	case "$database_name" in
		bvm_compat_*) ;;
		*) printf 'Refusing unsafe disposable database name: %s\n' "$database_name" >&2; return 1 ;;
	esac
	case "$database_name" in
		*[!a-z0-9_]*) printf 'Refusing unsafe disposable database name: %s\n' "$database_name" >&2; return 1 ;;
		*) ;;
	esac
}

safe_remove_runtime() {
	case "$runtime_root" in
		"$temp_base"/bvm-addon-compat-runtime.*)
			if [ -n "$runtime_root" ] && [ "$runtime_root" != "/" ] && [ -e "$runtime_root" ]; then
				rm -rf -- "$runtime_root"
			fi
			;;
		*) printf 'Refusing unsafe runtime cleanup target: %s\n' "$runtime_root" >&2; return 1 ;;
	esac
}

wp_fixture() {
	"$php_bin" "$wp_cli_bin" --path="$runtime_root" "$@"
}

wp_source() {
	"$php_bin" "$wp_cli_bin" --path="$wordpress_source_root" "$@"
}

cleanup_on_exit() {
	exit_code=$?
	if [ "$database_created" = yes ]; then
		if validate_database_name && wp_fixture db drop --yes --quiet >/dev/null 2>&1; then
			database_created=no
			database_cleanup=pass
		else
			printf 'DISPOSABLE DATABASE CLEANUP FAILED: %s\n' "$database_name" >&2
			database_cleanup=fail
		fi
	fi
	if [ -e "$runtime_root" ]; then
		if safe_remove_runtime; then
			runtime_cleanup=pass
		else
			runtime_cleanup=fail
		fi
	fi
	if [ "$exit_code" -ne 0 ]; then
		printf 'Harness evidence preserved at: %s\n' "$output_dir" >&2
	fi
}
trap cleanup_on_exit EXIT HUP INT TERM

for required_path in \
	"$wordpress_source_root/wp-settings.php" \
	"$wordpress_source_root/wp-config.php" \
	"$repo_root/vendor-management-system.php" \
	"$source_plugins_root/vms-events-slider/vms-events-slider.php" \
	"$source_plugins_root/vms-fill-dates/vms-fill-dates.php" \
	"$source_plugins_root/vms-data-tools/vms-data-tools.php" \
	"$source_plugins_root/vms-express-bar/vms-express-bar.php" \
	"$source_plugins_root/vms-refer-a-friend/vms-refer-a-friend.php" \
	"$source_plugins_root/woocommerce/woocommerce.php" \
	"$source_plugins_root/the-events-calendar/the-events-calendar.php" \
	"$probe_file" \
	"$preload_file" \
	"$report_builder" \
	"$source_manifest_builder"
do
	if [ ! -r "$required_path" ]; then
		printf 'Required compatibility source is missing or unreadable: %s\n' "$required_path" >&2
		exit 2
	fi
done

normal_active_plugins_hash() {
	wp_source option get active_plugins --format=json --skip-plugins --skip-themes --quiet \
		| "$php_bin" -r '$value = stream_get_contents(STDIN); echo hash("sha256", trim((string) $value));'
}

normal_before=$(normal_active_plugins_hash)
normal_config_before=$("$php_bin" -r 'echo hash_file("sha256", $argv[1]);' "$wordpress_source_root/wp-config.php")

db_name_source=$(wp_source config get DB_NAME --type=constant --quiet)
db_user=$(wp_source config get DB_USER --type=constant --quiet)
db_password=$(wp_source config get DB_PASSWORD --type=constant --quiet)
db_host=$(wp_source config get DB_HOST --type=constant --quiet)
db_charset=$(wp_source config get DB_CHARSET --type=constant --quiet)

if [ "$database_name" = "$db_name_source" ]; then
	printf 'Disposable database name unexpectedly matches the normal site database.\n' >&2
	exit 2
fi
validate_database_name

rsync -a --exclude='wp-content/' --exclude='wp-config.php' "$wordpress_source_root/" "$runtime_root/"
mkdir -p "$runtime_root/wp-content/plugins" "$runtime_root/wp-content/themes" "$runtime_root/wp-content/mu-plugins"
if [ -d "$wordpress_source_root/wp-content/themes" ]; then
	rsync -a "$wordpress_source_root/wp-content/themes/" "$runtime_root/wp-content/themes/"
fi

rsync -a \
	--exclude='/.git/' \
	--exclude='/.github/' \
	--exclude='/.gitignore' \
	--exclude='/.gitattributes' \
	--exclude='/AGENTS.md' \
	--exclude='/release-public-excludes.txt' \
	--exclude='/.codex/' \
	--exclude='/.codex-temp/' \
	--exclude='/tests/' \
	--exclude='/test-results/' \
	--exclude='/docs/' \
	--exclude='/scripts/' \
	--exclude='/dist/' \
	--exclude='/_attic/' \
	--exclude='/includes/safety/' \
	--exclude='/BUILD-NOTES-*.md' \
	--exclude='/local-event-plan-perf-*.md' \
	--exclude='/vms-test-plan-*.md' \
	--exclude='*.zip' \
	--exclude='*.log' \
	"$repo_root/" \
	"$runtime_root/wp-content/plugins/backstage-venue-manager/"

for plugin_slug in vms-events-slider vms-fill-dates vms-data-tools vms-express-bar vms-refer-a-friend woocommerce the-events-calendar
do
	rsync -a "$source_plugins_root/$plugin_slug/" "$runtime_root/wp-content/plugins/$plugin_slug/"
done

if [ -e "$runtime_root/wp-content/plugins/vms/vendor-management-system.php" ] \
	|| [ -e "$runtime_root/wp-content/plugins/vms.php" ] \
	|| [ -e "$runtime_root/wp-content/plugins/vms/vms.php" ] \
	|| [ -e "$runtime_root/wp-content/plugins/backstage-venue-manager.php" ]; then
	printf 'A prohibited historical or nonexistent BVM bootstrap identity entered the fixture.\n' >&2
	exit 2
fi

"$php_bin" "$wp_cli_bin" config create \
	--path="$runtime_root" \
	--dbname="$database_name" \
	--dbuser="$db_user" \
	--dbpass="$db_password" \
	--dbhost="$db_host" \
	--dbcharset="$db_charset" \
	--skip-salts \
	--force \
	--quiet

wp_fixture config set WP_DEBUG true --raw --quiet
wp_fixture config set WP_DEBUG_LOG "$debug_log" --quiet
wp_fixture config set WP_DEBUG_DISPLAY false --raw --quiet
wp_fixture config set SCRIPT_DEBUG true --raw --quiet
wp_fixture config set WP_ENVIRONMENT_TYPE local --quiet
wp_fixture config set WP_MEMORY_LIMIT 512M --quiet
wp_fixture config set WP_MAX_MEMORY_LIMIT 512M --quiet
wp_fixture config set DISABLE_WP_CRON true --raw --quiet
wp_fixture config set AUTOMATIC_UPDATER_DISABLED true --raw --quiet
wp_fixture config set WP_HTTP_BLOCK_EXTERNAL true --raw --quiet
wp_fixture db create --quiet
database_created=yes
wp_fixture core install \
	--url='http://bvm-compat.test' \
	--title='BVM Compatibility Fixture' \
	--admin_user='bvm_compat_admin' \
	--admin_password="$($php_bin -r 'echo bin2hex(random_bytes(16));')" \
	--admin_email='bvm-compat@example.test' \
	--skip-email \
	--quiet

# Run activation hooks only inside the empty disposable database so plugin
# schemas/capabilities match a normal installation before load-order testing.
wp_fixture plugin activate \
	backstage-venue-manager \
	woocommerce \
	the-events-calendar \
	vms-events-slider \
	vms-fill-dates \
	vms-data-tools \
	vms-express-bar \
	vms-refer-a-friend \
	--quiet >"$output_dir/activation-setup.log" 2>&1
wp_fixture plugin deactivate \
	backstage-venue-manager \
	woocommerce \
	the-events-calendar \
	vms-events-slider \
	vms-fill-dates \
	vms-data-tools \
	vms-express-bar \
	vms-refer-a-friend \
	--quiet >>"$output_dir/activation-setup.log" 2>&1
wp_fixture transient delete _wc_activation_redirect --skip-plugins --skip-themes --quiet >/dev/null 2>&1 || true
wp_fixture transient delete _tribe_events_activation_redirect --skip-plugins --skip-themes --quiet >/dev/null 2>&1 || true

for capability in manage_woocommerce vms_manage_data_tools vms_import_vendors vms_manage_vendors
do
	wp_fixture cap add administrator "$capability" --skip-plugins --skip-themes --quiet
done

"$php_bin" "$source_manifest_builder" "$runtime_root" "$source_manifest" >/dev/null
: > "$scenario_index"

bvm_plugin=backstage-venue-manager/vendor-management-system.php
events_plugin=vms-events-slider/vms-events-slider.php
fill_dates_plugin=vms-fill-dates/vms-fill-dates.php
data_tools_plugin=vms-data-tools/vms-data-tools.php
express_bar_plugin=vms-express-bar/vms-express-bar.php
raf_plugin=vms-refer-a-friend/vms-refer-a-friend.php
woocommerce_plugin=woocommerce/woocommerce.php
tec_plugin=the-events-calendar/the-events-calendar.php

run_scenario() {
	scenario_id=$1
	scenario_addon=$2
	core_expected=$3
	woocommerce_expected=$4
	load_order=$5
	request_page=$6
	shift 6

	plugins_json=$("$php_bin" -r 'echo json_encode(array_slice($argv, 1), JSON_UNESCAPED_SLASHES);' -- "$@")
	wp_fixture option update active_plugins "$plugins_json" --format=json --skip-plugins --skip-themes --quiet >/dev/null

	raw_path=$output_dir/$scenario_id.raw.log
	scenario_debug_path=$output_dir/$scenario_id.debug.log
	: > "$debug_log"
	set +e
	BVM_COMPAT_REQUEST_PAGE=$request_page \
		"$php_bin" "$wp_cli_bin" \
		--path="$runtime_root" \
		--require="$preload_file" \
		eval-file "$probe_file" \
		"$scenario_id" "$scenario_addon" "$core_expected" "$woocommerce_expected" "$load_order" \
		>"$raw_path" 2>&1
	scenario_exit=$?
	set -e
	if [ -f "$debug_log" ]; then
		cp "$debug_log" "$scenario_debug_path"
	else
		: > "$scenario_debug_path"
	fi
	printf '%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\n' \
		"$scenario_id" "$scenario_addon" "$core_expected" "$woocommerce_expected" "$load_order" "$scenario_exit" "$raw_path" "$scenario_debug_path" \
		>> "$scenario_index"
}

run_scenario bvm-only-events-slider-core-first events-slider yes no core-first '' \
	"$bvm_plugin" "$tec_plugin" "$events_plugin"
run_scenario bvm-only-events-slider-addon-first events-slider yes no addon-first '' \
	"$events_plugin" "$tec_plugin" "$bvm_plugin"
run_scenario bvm-only-fill-dates-core-first fill-dates yes no core-first vms-fill-dates \
	"$bvm_plugin" "$fill_dates_plugin"
run_scenario bvm-only-fill-dates-addon-first fill-dates yes no addon-first vms-fill-dates \
	"$fill_dates_plugin" "$bvm_plugin"
run_scenario bvm-only-data-tools-core-first data-tools yes yes core-first vms-data-tools \
	"$bvm_plugin" "$woocommerce_plugin" "$data_tools_plugin"
run_scenario bvm-only-data-tools-addon-first data-tools yes yes addon-first vms-data-tools \
	"$data_tools_plugin" "$woocommerce_plugin" "$bvm_plugin"
run_scenario bvm-only-express-bar-core-first express-bar yes yes core-first vms-express-bar \
	"$bvm_plugin" "$woocommerce_plugin" "$express_bar_plugin"
run_scenario bvm-only-express-bar-addon-first express-bar yes yes addon-first vms-express-bar \
	"$express_bar_plugin" "$woocommerce_plugin" "$bvm_plugin"
run_scenario bvm-only-refer-a-friend-core-first refer-a-friend yes yes core-first vms-raf \
	"$bvm_plugin" "$woocommerce_plugin" "$raf_plugin"
run_scenario bvm-only-refer-a-friend-addon-first refer-a-friend yes yes addon-first vms-raf \
	"$raf_plugin" "$woocommerce_plugin" "$bvm_plugin"

run_scenario bvm-all-official-five-core-first all yes yes core-first vms-data-tools \
	"$bvm_plugin" "$woocommerce_plugin" "$tec_plugin" "$events_plugin" "$fill_dates_plugin" "$data_tools_plugin" "$express_bar_plugin" "$raf_plugin"
run_scenario bvm-all-official-five-addons-first all yes yes addons-first vms-data-tools \
	"$events_plugin" "$fill_dates_plugin" "$data_tools_plugin" "$express_bar_plugin" "$raf_plugin" "$woocommerce_plugin" "$tec_plugin" "$bvm_plugin"

run_scenario core-absent-events-slider events-slider no no n/a '' \
	"$tec_plugin" "$events_plugin"
run_scenario core-absent-fill-dates fill-dates no no n/a vms-fill-dates \
	"$fill_dates_plugin"
run_scenario core-absent-data-tools data-tools no yes n/a vms-data-tools \
	"$woocommerce_plugin" "$data_tools_plugin"
run_scenario core-absent-express-bar express-bar no yes n/a vms-express-bar \
	"$woocommerce_plugin" "$express_bar_plugin"
run_scenario core-absent-refer-a-friend refer-a-friend no yes n/a vms-raf \
	"$woocommerce_plugin" "$raf_plugin"
run_scenario bvm-without-woocommerce-express-bar express-bar yes no core-first vms-express-bar \
	"$bvm_plugin" "$express_bar_plugin"

wp_fixture option update active_plugins '[]' --format=json --skip-plugins --skip-themes --quiet >/dev/null
if validate_database_name && wp_fixture db drop --yes --quiet >/dev/null; then
	database_created=no
	database_cleanup=pass
else
	database_cleanup=fail
	printf 'DISPOSABLE DATABASE CLEANUP FAILED: %s\n' "$database_name" >&2
fi

if safe_remove_runtime; then
	runtime_cleanup=pass
else
	runtime_cleanup=fail
fi

normal_after=$(normal_active_plugins_hash)
normal_config_after=$("$php_bin" -r 'echo hash_file("sha256", $argv[1]);' "$wordpress_source_root/wp-config.php")
if [ "$normal_config_before" != "$normal_config_after" ]; then
	printf 'Normal wp-config.php changed during the isolated harness.\n' >&2
	exit 1
fi

report_json=$output_dir/bvm-addon-runtime-compatibility.report.json
report_text=$output_dir/bvm-addon-runtime-compatibility.report.txt
set +e
"$php_bin" "$report_builder" \
	"$scenario_index" \
	"$source_manifest" \
	"$normal_before" \
	"$normal_after" \
	"$database_cleanup" \
	"$runtime_cleanup" \
	"$report_json" \
	"$report_text"
report_exit=$?
set -e

printf 'Harness evidence: %s\n' "$output_dir"
printf 'JSON report: %s\n' "$report_json"
printf 'Text report: %s\n' "$report_text"
exit "$report_exit"
