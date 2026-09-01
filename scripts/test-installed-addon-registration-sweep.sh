#!/bin/sh

set -eu

repo_root=$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)
wordpress_source_root=${BVM_COMPAT_WP_ROOT:-$(CDPATH= cd -- "$repo_root/../../../.." && pwd)}
source_plugins_root=${BVM_COMPAT_ADDON_ROOT:-$wordpress_source_root/wp-content/plugins}
public_bvm_root=${BVM_SWEEP_BVM_ROOT:-}
release_root=${BVM_SWEEP_RELEASE_ROOT:-}
baseline_root=${BVM_SWEEP_BASELINE_ROOT:-}
php_bin=${BVM_COMPAT_PHP_BIN:-$(command -v php)}
wp_cli_bin=${BVM_COMPAT_WP_CLI_BIN:-$(command -v wp)}
temp_base=${TMPDIR:-/tmp}
runtime_root=$(mktemp -d "$temp_base/bvm-installed-addon-sweep.XXXXXX")
output_dir=${BVM_SWEEP_OUTPUT_DIR:-$repo_root/test-results/installed-addon-registration-sweep}
database_name=bvm_sweep_$($php_bin -r 'echo bin2hex(random_bytes(6));')
database_created=no

if [ -z "$public_bvm_root" ] || [ -z "$release_root" ] || [ -z "$baseline_root" ]; then
	printf '%s\n' 'BVM_SWEEP_BVM_ROOT, BVM_SWEEP_RELEASE_ROOT, and BVM_SWEEP_BASELINE_ROOT are required.' >&2
	exit 2
fi

validate_database_name() {
	case "$database_name" in
		bvm_sweep_[a-z0-9]*) ;;
		*) printf 'Refusing unsafe disposable database name: %s\n' "$database_name" >&2; return 1 ;;
	esac
}

safe_remove_runtime() {
	case "$runtime_root" in
		"$temp_base"/bvm-installed-addon-sweep.*)
			[ -n "$runtime_root" ] && [ "$runtime_root" != / ] && rm -rf -- "$runtime_root"
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
		validate_database_name && wp_fixture db drop --yes --quiet >/dev/null 2>&1 || true
	fi
	if [ -e "$runtime_root" ]; then
		safe_remove_runtime || true
	fi
	exit "$exit_code"
}
trap cleanup_on_exit EXIT HUP INT TERM

for required_path in \
	"$wordpress_source_root/wp-settings.php" \
	"$wordpress_source_root/wp-config.php" \
	"$public_bvm_root/backstage-venue-manager.php" \
	"$release_root/vms-data-tools/vms-data-tools.php" \
	"$release_root/vms-events-slider/vms-events-slider.php" \
	"$release_root/vms-meta-ads/vms-meta-ads.php" \
	"$release_root/vms-refer-a-friend/vms-refer-a-friend.php" \
	"$release_root/vms-investor-portal/vms-investor-portal.php" \
	"$release_root/vms-ops-console-premium/vms-ops-console-premium.php" \
	"$baseline_root/vms-agreements/vms-agreements.php" \
	"$baseline_root/vms-express-bar/vms-express-bar.php" \
	"$baseline_root/vms-sponsorships/vms-sponsorships.php" \
	"$baseline_root/event-venue-map-modal/event-venue-map-modal.php" \
	"$source_plugins_root/woocommerce/woocommerce.php" \
	"$source_plugins_root/the-events-calendar/the-events-calendar.php" \
	"$repo_root/tests/addon-compatibility/runtime-preload.php" \
	"$repo_root/tests/addon-compatibility/installed-addon-runtime-probe.php"
do
	if [ ! -r "$required_path" ]; then
		printf 'Required sweep source is missing: %s\n' "$required_path" >&2
		exit 2
	fi
done

mkdir -p "$output_dir"
normal_before=$(wp_source option get active_plugins --format=json --skip-plugins --skip-themes --quiet | "$php_bin" -r '$v=stream_get_contents(STDIN); echo hash("sha256", trim((string)$v));')
normal_config_before=$($php_bin -r 'echo hash_file("sha256", $argv[1]);' "$wordpress_source_root/wp-config.php")

db_name_source=$(wp_source config get DB_NAME --type=constant --quiet)
db_user=$(wp_source config get DB_USER --type=constant --quiet)
db_password=$(wp_source config get DB_PASSWORD --type=constant --quiet)
db_host=$(wp_source config get DB_HOST --type=constant --quiet)
db_charset=$(wp_source config get DB_CHARSET --type=constant --quiet)
validate_database_name
[ "$database_name" != "$db_name_source" ] || { printf '%s\n' 'Disposable database unexpectedly matches normal database.' >&2; exit 2; }

rsync -a --exclude='wp-content/' --exclude='wp-config.php' "$wordpress_source_root/" "$runtime_root/"
mkdir -p "$runtime_root/wp-content/plugins" "$runtime_root/wp-content/themes" "$runtime_root/wp-content/mu-plugins"
if [ -d "$wordpress_source_root/wp-content/themes" ]; then
	rsync -a "$wordpress_source_root/wp-content/themes/" "$runtime_root/wp-content/themes/"
fi

rsync -a --exclude='*.log' --exclude='error_log' "$public_bvm_root/" "$runtime_root/wp-content/plugins/backstage-venue-manager/"
for plugin in vms-data-tools vms-events-slider vms-meta-ads vms-refer-a-friend vms-investor-portal vms-ops-console-premium
do
	rsync -a --exclude='*.log' --exclude='error_log' "$release_root/$plugin/" "$runtime_root/wp-content/plugins/$plugin/"
done
for plugin in vms-agreements vms-express-bar vms-sponsorships event-venue-map-modal
do
	rsync -a --exclude='*.log' --exclude='error_log' "$baseline_root/$plugin/" "$runtime_root/wp-content/plugins/$plugin/"
done
for plugin in woocommerce the-events-calendar
do
	rsync -a --exclude='*.log' --exclude='error_log' "$source_plugins_root/$plugin/" "$runtime_root/wp-content/plugins/$plugin/"
done

if [ -e "$runtime_root/wp-content/plugins/vms/vendor-management-system.php" ]; then
	printf '%s\n' 'Historical VMS source entered the disposable fixture.' >&2
	exit 2
fi

"$php_bin" "$wp_cli_bin" config create \
	--path="$runtime_root" \
	--dbname="$database_name" \
	--dbuser="$db_user" \
	--dbpass="$db_password" \
	--dbhost="$db_host" \
	--dbcharset="$db_charset" \
	--skip-salts --force --quiet
wp_fixture config set WP_DEBUG true --raw --quiet
wp_fixture config set WP_DEBUG_LOG "$runtime_root/wp-content/debug.log" --quiet
wp_fixture config set WP_DEBUG_DISPLAY false --raw --quiet
wp_fixture config set WP_ENVIRONMENT_TYPE local --quiet
wp_fixture config set DISABLE_WP_CRON true --raw --quiet
wp_fixture config set WP_HTTP_BLOCK_EXTERNAL true --raw --quiet
wp_fixture db create --quiet
database_created=yes
wp_fixture core install \
	--url='http://bvm-installed-addon-sweep.test' \
	--title='BVM Installed Add-on Sweep' \
	--admin_user='bvm_sweep_admin' \
	--admin_password="$($php_bin -r 'echo bin2hex(random_bytes(16));')" \
	--admin_email='bvm-sweep@example.test' \
	--skip-email --quiet

wp_fixture plugin activate \
	woocommerce the-events-calendar backstage-venue-manager \
	vms-agreements vms-data-tools vms-events-slider vms-express-bar \
	vms-investor-portal vms-meta-ads vms-ops-console-premium \
	vms-refer-a-friend vms-sponsorships event-venue-map-modal \
	--quiet >"$output_dir/activation.log" 2>&1
wp_fixture transient delete _wc_activation_redirect --skip-plugins --skip-themes --quiet >/dev/null 2>&1 || true
wp_fixture transient delete _tribe_events_activation_redirect --skip-plugins --skip-themes --quiet >/dev/null 2>&1 || true

for capability in manage_woocommerce vms_manage_data_tools vms_import_vendors vms_manage_vendors vms_manage_investor_financials vms_social_manage vms_ops_manage_members_admin
do
	wp_fixture cap add administrator "$capability" --skip-plugins --skip-themes --quiet
done

run_scenario() {
	scenario=$1
	shift
	plugins_json=$($php_bin -r 'echo json_encode(array_slice($argv, 1), JSON_UNESCAPED_SLASHES);' -- "$@")
	wp_fixture option update active_plugins "$plugins_json" --format=json --skip-plugins --skip-themes --quiet >/dev/null
	: > "$runtime_root/wp-content/debug.log"
	BVM_COMPAT_REQUEST_PAGE=vms-data-tools \
		"$php_bin" "$wp_cli_bin" --path="$runtime_root" \
		--require="$repo_root/tests/addon-compatibility/runtime-preload.php" \
		eval-file "$repo_root/tests/addon-compatibility/installed-addon-runtime-probe.php" "$scenario" \
		>"$output_dir/$scenario.json"
	cp "$runtime_root/wp-content/debug.log" "$output_dir/$scenario.debug.log"
}

woocommerce=woocommerce/woocommerce.php
tec=the-events-calendar/the-events-calendar.php
bvm=backstage-venue-manager/backstage-venue-manager.php
agreements=vms-agreements/vms-agreements.php
data=vms-data-tools/vms-data-tools.php
slider=vms-events-slider/vms-events-slider.php
express=vms-express-bar/vms-express-bar.php
investor=vms-investor-portal/vms-investor-portal.php
mab=vms-meta-ads/vms-meta-ads.php
ops=vms-ops-console-premium/vms-ops-console-premium.php
raf=vms-refer-a-friend/vms-refer-a-friend.php
sponsorships=vms-sponsorships/vms-sponsorships.php
venue_map=event-venue-map-modal/event-venue-map-modal.php

run_scenario core-first "$woocommerce" "$tec" "$bvm" "$agreements" "$data" "$slider" "$express" "$investor" "$mab" "$ops" "$raf" "$sponsorships" "$venue_map"
run_scenario addons-first "$woocommerce" "$tec" "$agreements" "$data" "$slider" "$express" "$investor" "$mab" "$ops" "$raf" "$sponsorships" "$venue_map" "$bvm"

normal_after=$(wp_source option get active_plugins --format=json --skip-plugins --skip-themes --quiet | "$php_bin" -r '$v=stream_get_contents(STDIN); echo hash("sha256", trim((string)$v));')
normal_config_after=$($php_bin -r 'echo hash_file("sha256", $argv[1]);' "$wordpress_source_root/wp-config.php")
[ "$normal_before" = "$normal_after" ] || { printf '%s\n' 'Normal active plugin state changed.' >&2; exit 1; }
[ "$normal_config_before" = "$normal_config_after" ] || { printf '%s\n' 'Normal wp-config.php changed.' >&2; exit 1; }

printf 'PASS: installed add-on registration sweep runtime scenarios\n'
printf 'Evidence: %s\n' "$output_dir"
