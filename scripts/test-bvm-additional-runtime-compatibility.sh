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

probe_file=$repo_root/tests/addon-compatibility/additional-runtime-probe.php
preload_file=$repo_root/tests/addon-compatibility/runtime-preload.php
report_builder=$repo_root/tests/addon-compatibility/additional-build-report.php
source_manifest_builder=$repo_root/tests/addon-compatibility/additional-source-manifest.php
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

commerce_archive=$source_plugins_root/vms-commerce-discounts-0.2.11.zip
weather_archive=$source_plugins_root/VMS\ WEATHER\ RISK\ ZIP\ ARCHIVES/vmsx-weather-risk-0.1.12-title-cleanup.zip

for required_path in \
	"$wordpress_source_root/wp-settings.php" \
	"$wordpress_source_root/wp-config.php" \
	"$repo_root/vendor-management-system.php" \
	"$source_plugins_root/drm-calendar-intake/drm-calendar-intake.php" \
	"$source_plugins_root/vms-investor-portal/vms-investor-portal.php" \
	"$source_plugins_root/vms-meta-ads/vms-meta-ads.php" \
	"$source_plugins_root/vms-ops-console-premium/vms-ops-console-premium.php" \
	"$source_plugins_root/vms-safety-pro/vms-safety-pro.php" \
	"$source_plugins_root/vms-season-passes/vms-season-passes.php" \
	"$source_plugins_root/vms-sponsorships/vms-sponsorships.php" \
	"$source_plugins_root/vmsx-checkout-policies/vmsx-checkout-policies.php" \
	"$source_plugins_root/vms-events-slider/vms-events-slider.php" \
	"$source_plugins_root/vms-fill-dates/vms-fill-dates.php" \
	"$source_plugins_root/vms-data-tools/vms-data-tools.php" \
	"$source_plugins_root/vms-express-bar/vms-express-bar.php" \
	"$source_plugins_root/vms-refer-a-friend/vms-refer-a-friend.php" \
	"$source_plugins_root/woocommerce/woocommerce.php" \
	"$source_plugins_root/woocommerce-square/woocommerce-square.php" \
	"$source_plugins_root/the-events-calendar/the-events-calendar.php" \
	"$source_plugins_root/event-tickets/event-tickets.php" \
	"$source_plugins_root/event-tickets-plus/event-tickets-plus.php" \
	"$commerce_archive" \
	"$weather_archive" \
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

if [ -n "$(git -C "$source_plugins_root/drm-calendar-intake" status --short)" ]; then
	printf 'DRM Calendar Intake changed or is dirty; refusing to freeze an ambiguous source snapshot.\n' >&2
	exit 2
fi
calendar_intake_head_before=$(git -C "$source_plugins_root/drm-calendar-intake" rev-parse HEAD)

# DRM Events Bridge is deliberately not staged. Its authoritative repository
# was found dirty and behind its remote, so Phase 4 records it as BLOCKED.
if [ ! -d "$source_plugins_root/drm-events-bridge/.git" ]; then
	printf 'Expected authoritative DRM Events Bridge Git worktree is missing.\n' >&2
	exit 2
fi

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

# This is the same public BVM fixture boundary as Phase 3. In particular, the
# dormant source-only Safety prototype remains excluded.
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

for plugin_slug in \
	vms-events-slider vms-fill-dates vms-data-tools vms-express-bar vms-refer-a-friend \
	woocommerce woocommerce-square the-events-calendar event-tickets event-tickets-plus \
	drm-calendar-intake vms-investor-portal vms-meta-ads vms-ops-console-premium \
	vms-safety-pro vms-season-passes vms-sponsorships vmsx-checkout-policies
do
	rsync -a \
		--exclude='/.git/' \
		--exclude='/AGENTS.md' \
		--exclude='/tests/' \
		"$source_plugins_root/$plugin_slug/" \
		"$runtime_root/wp-content/plugins/$plugin_slug/"
done

unzip -q "$commerce_archive" -d "$runtime_root/wp-content/plugins"
unzip -q "$weather_archive" -d "$runtime_root/wp-content/plugins"

calendar_intake_head_after=$(git -C "$source_plugins_root/drm-calendar-intake" rev-parse HEAD)
if [ "$calendar_intake_head_before" != "$calendar_intake_head_after" ] || [ -n "$(git -C "$source_plugins_root/drm-calendar-intake" status --short)" ]; then
	printf 'DRM Calendar Intake moved while its fixture was being frozen.\n' >&2
	exit 2
fi

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
	--admin_password="$("$php_bin" -r 'echo bin2hex(random_bytes(16));')" \
	--admin_email='bvm-compat@example.test' \
	--skip-email \
	--quiet

fixture_foundation_plugins='backstage-venue-manager woocommerce the-events-calendar event-tickets event-tickets-plus vms-events-slider vms-fill-dates vms-data-tools vms-express-bar vms-refer-a-friend'
fixture_additional_plugins='drm-calendar-intake vms-investor-portal vms-meta-ads vms-ops-console-premium vms-safety-pro vms-season-passes vms-sponsorships vmsx-checkout-policies vmsx-weather-risk'
all_fixture_plugins="$fixture_foundation_plugins woocommerce-square $fixture_additional_plugins vms-commerce-discounts"
# Activate in separate fresh processes. Commerce Discounts declares a subclass
# of a WooCommerce Square class while its activation files load, so Square's
# already-active autoloader is a genuine prerequisite for valid setup.
# shellcheck disable=SC2086 -- the fixed lists intentionally expand to WP-CLI arguments.
wp_fixture plugin activate $fixture_foundation_plugins --quiet >"$output_dir/activation-setup.log" 2>&1
# Preserve the activation-time missing-Square failure as explicit evidence.
commerce_missing_square_activation_log=$output_dir/third-party-activation-absent-square-vms-commerce-discounts.raw.log
set +e
wp_fixture plugin activate vms-commerce-discounts --quiet >"$commerce_missing_square_activation_log" 2>&1
commerce_missing_square_activation_exit=$?
set -e
if [ "$commerce_missing_square_activation_exit" -eq 0 ]; then
	printf 'Commerce Discounts unexpectedly activated without WooCommerce Square.\n' >&2
	exit 2
fi
wp_fixture plugin activate woocommerce-square --quiet >>"$output_dir/activation-setup.log" 2>&1
# shellcheck disable=SC2086 -- the fixed lists intentionally expand to WP-CLI arguments.
wp_fixture plugin activate $fixture_additional_plugins --quiet >>"$output_dir/activation-setup.log" 2>&1
wp_fixture plugin activate vms-commerce-discounts --quiet >>"$output_dir/activation-setup.log" 2>&1
# shellcheck disable=SC2086 -- the fixed list intentionally expands to WP-CLI arguments.
wp_fixture plugin deactivate $all_fixture_plugins --quiet >>"$output_dir/activation-setup.log" 2>&1
wp_fixture transient delete _wc_activation_redirect --skip-plugins --skip-themes --quiet >/dev/null 2>&1 || true
wp_fixture transient delete _tribe_events_activation_redirect --skip-plugins --skip-themes --quiet >/dev/null 2>&1 || true
wp_fixture option update wc_square_show_wizard_on_activation 1 --skip-plugins --skip-themes --quiet >/dev/null

for capability in manage_woocommerce vms_manage_data_tools vms_import_vendors vms_manage_vendors vms_manage_investor_financials vms_view_investor_portal vms_manage_safety_templates
do
	wp_fixture cap add administrator "$capability" --skip-plugins --skip-themes --quiet
done

"$php_bin" "$source_manifest_builder" "$runtime_root" "$source_manifest" >/dev/null
: > "$scenario_index"
commerce_missing_square_debug_log=$output_dir/third-party-activation-absent-square-vms-commerce-discounts.debug.log
: > "$commerce_missing_square_debug_log"
printf '%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\n' \
	'third-party-activation-absent-square-vms-commerce-discounts' 'vms-commerce-discounts' 'yes' 'yes' 'missing-woocommerce-square' 'activation' "$commerce_missing_square_activation_exit" "$commerce_missing_square_activation_log" "$commerce_missing_square_debug_log" \
	>> "$scenario_index"

bvm_plugin=backstage-venue-manager/vendor-management-system.php
woocommerce_plugin=woocommerce/woocommerce.php
square_plugin=woocommerce-square/woocommerce-square.php
tec_plugin=the-events-calendar/the-events-calendar.php
tickets_plugin=event-tickets/event-tickets.php
tickets_plus_plugin=event-tickets-plus/event-tickets-plus.php
events_plugin=vms-events-slider/vms-events-slider.php
fill_dates_plugin=vms-fill-dates/vms-fill-dates.php
data_tools_plugin=vms-data-tools/vms-data-tools.php
express_bar_plugin=vms-express-bar/vms-express-bar.php
raf_plugin=vms-refer-a-friend/vms-refer-a-friend.php
calendar_plugin=drm-calendar-intake/drm-calendar-intake.php
commerce_plugin=vms-commerce-discounts/vms-commerce-discounts.php
investor_plugin=vms-investor-portal/vms-investor-portal.php
meta_ads_plugin=vms-meta-ads/vms-meta-ads.php
ops_plugin=vms-ops-console-premium/vms-ops-console-premium.php
safety_plugin=vms-safety-pro/vms-safety-pro.php
season_plugin=vms-season-passes/vms-season-passes.php
sponsorships_plugin=vms-sponsorships/vms-sponsorships.php
checkout_plugin=vmsx-checkout-policies/vmsx-checkout-policies.php
weather_plugin=vmsx-weather-risk/vmsx-weather-risk.php

run_scenario() {
	scenario_id=$1
	scenario_addon=$2
	core_expected=$3
	woocommerce_expected=$4
	companion_state=$5
	load_order=$6
	request_page=$7
	shift 7

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
		"$scenario_id" "$scenario_addon" "$core_expected" "$woocommerce_expected" "$companion_state" "$load_order" \
		>"$raw_path" 2>&1
	scenario_exit=$?
	set -e
	if [ -f "$debug_log" ]; then
		cp "$debug_log" "$scenario_debug_path"
	else
		: > "$scenario_debug_path"
	fi
	printf '%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\n' \
		"$scenario_id" "$scenario_addon" "$core_expected" "$woocommerce_expected" "$companion_state" "$load_order" "$scenario_exit" "$raw_path" "$scenario_debug_path" \
		>> "$scenario_index"
}

run_scenario additional-drm-calendar-intake-core-first drm-calendar-intake yes no normal core-first drm-calendar-intake-settings "$bvm_plugin" "$calendar_plugin"
run_scenario additional-drm-calendar-intake-addon-first drm-calendar-intake yes no normal addon-first drm-calendar-intake-settings "$calendar_plugin" "$bvm_plugin"
run_scenario additional-vms-commerce-discounts-core-first vms-commerce-discounts yes yes normal core-first vms-commerce-discounts "$bvm_plugin" "$woocommerce_plugin" "$square_plugin" "$tec_plugin" "$tickets_plugin" "$tickets_plus_plugin" "$commerce_plugin"
run_scenario additional-vms-commerce-discounts-addon-first vms-commerce-discounts yes yes normal addon-first vms-commerce-discounts "$commerce_plugin" "$woocommerce_plugin" "$square_plugin" "$tec_plugin" "$tickets_plugin" "$tickets_plus_plugin" "$bvm_plugin"
run_scenario additional-vms-investor-portal-core-first vms-investor-portal yes no normal core-first vms-investor-portal "$bvm_plugin" "$investor_plugin"
run_scenario additional-vms-investor-portal-addon-first vms-investor-portal yes no normal addon-first vms-investor-portal "$investor_plugin" "$bvm_plugin"
run_scenario additional-vms-meta-ads-core-first vms-meta-ads yes no normal core-first vms-ma-ads-builder "$bvm_plugin" "$meta_ads_plugin"
run_scenario additional-vms-meta-ads-addon-first vms-meta-ads yes no normal addon-first vms-ma-ads-builder "$meta_ads_plugin" "$bvm_plugin"
run_scenario additional-vms-ops-console-premium-core-first vms-ops-console-premium yes no normal core-first vms-ops-console-members "$bvm_plugin" "$ops_plugin"
run_scenario additional-vms-ops-console-premium-addon-first vms-ops-console-premium yes no normal addon-first vms-ops-console-members "$ops_plugin" "$bvm_plugin"
run_scenario additional-vms-safety-pro-core-first vms-safety-pro yes no normal core-first vms-safety "$bvm_plugin" "$safety_plugin"
run_scenario additional-vms-safety-pro-addon-first vms-safety-pro yes no normal addon-first vms-safety "$safety_plugin" "$bvm_plugin"
run_scenario additional-vms-season-passes-core-first vms-season-passes yes no normal core-first vms-season-passes "$bvm_plugin" "$season_plugin"
run_scenario additional-vms-season-passes-addon-first vms-season-passes yes no normal addon-first vms-season-passes "$season_plugin" "$bvm_plugin"
run_scenario additional-vms-sponsorships-core-first vms-sponsorships yes no normal core-first vms-sponsorships "$bvm_plugin" "$sponsorships_plugin"
run_scenario additional-vms-sponsorships-addon-first vms-sponsorships yes no normal addon-first vms-sponsorships "$sponsorships_plugin" "$bvm_plugin"
run_scenario additional-vmsx-checkout-policies-core-first vmsx-checkout-policies yes yes normal core-first vms-settings "$bvm_plugin" "$woocommerce_plugin" "$checkout_plugin"
run_scenario additional-vmsx-checkout-policies-addon-first vmsx-checkout-policies yes yes normal addon-first vms-settings "$checkout_plugin" "$woocommerce_plugin" "$bvm_plugin"
run_scenario additional-vmsx-weather-risk-core-first vmsx-weather-risk yes no normal core-first vms-weather-risk "$bvm_plugin" "$weather_plugin"
run_scenario additional-vmsx-weather-risk-addon-first vmsx-weather-risk yes no normal addon-first vms-weather-risk "$weather_plugin" "$bvm_plugin"

run_scenario additional-core-absent-drm-calendar-intake drm-calendar-intake no no normal n/a drm-calendar-intake-settings "$calendar_plugin"
run_scenario additional-core-absent-vms-commerce-discounts vms-commerce-discounts no yes normal n/a vms-commerce-discounts "$woocommerce_plugin" "$square_plugin" "$tec_plugin" "$tickets_plugin" "$tickets_plus_plugin" "$commerce_plugin"
run_scenario additional-core-absent-vms-investor-portal vms-investor-portal no no normal n/a vms-investor-portal "$investor_plugin"
run_scenario additional-core-absent-vms-meta-ads vms-meta-ads no no normal n/a vms-ma-ads-builder "$meta_ads_plugin"
run_scenario additional-core-absent-vms-ops-console-premium vms-ops-console-premium no no normal n/a vms-ops-console-members "$ops_plugin"
run_scenario additional-core-absent-vms-safety-pro vms-safety-pro no no normal n/a vms-safety "$safety_plugin"
run_scenario additional-core-absent-vms-season-passes vms-season-passes no no normal n/a vms-season-passes "$season_plugin"
run_scenario additional-core-absent-vms-sponsorships vms-sponsorships no no normal n/a vms-sponsorships "$sponsorships_plugin"
run_scenario additional-core-absent-vmsx-checkout-policies vmsx-checkout-policies no yes normal n/a vmsx-checkout-policies "$woocommerce_plugin" "$checkout_plugin"
run_scenario additional-core-absent-vmsx-weather-risk vmsx-weather-risk no no normal n/a vms-weather-risk "$weather_plugin"

run_scenario third-party-absent-vms-commerce-discounts vms-commerce-discounts yes no missing-woocommerce core-first vms-commerce-discounts "$bvm_plugin" "$commerce_plugin"
run_scenario third-party-absent-square-vms-commerce-discounts vms-commerce-discounts yes yes missing-woocommerce-square core-first vms-commerce-discounts "$bvm_plugin" "$woocommerce_plugin" "$commerce_plugin"
run_scenario third-party-absent-vmsx-checkout-policies vmsx-checkout-policies yes no missing-woocommerce core-first vms-settings "$bvm_plugin" "$checkout_plugin"
run_scenario third-party-absent-vms-season-passes vms-season-passes yes no missing-woocommerce core-first vms-season-passes "$bvm_plugin" "$season_plugin"
run_scenario third-party-absent-vms-sponsorships vms-sponsorships yes no missing-tec core-first vms-sponsorships "$bvm_plugin" "$sponsorships_plugin"

run_scenario additional-coexistence-core-first all yes yes full core-first vms-dashboard \
	"$bvm_plugin" "$woocommerce_plugin" "$square_plugin" "$tec_plugin" "$tickets_plugin" "$tickets_plus_plugin" \
	"$events_plugin" "$fill_dates_plugin" "$data_tools_plugin" "$express_bar_plugin" "$raf_plugin" \
	"$calendar_plugin" "$commerce_plugin" "$investor_plugin" "$meta_ads_plugin" "$ops_plugin" \
	"$season_plugin" "$sponsorships_plugin" "$checkout_plugin" "$weather_plugin"
run_scenario additional-coexistence-addons-first all yes yes full addons-first vms-dashboard \
	"$calendar_plugin" "$commerce_plugin" "$investor_plugin" "$meta_ads_plugin" "$ops_plugin" \
	"$season_plugin" "$sponsorships_plugin" "$checkout_plugin" "$weather_plugin" \
	"$events_plugin" "$fill_dates_plugin" "$data_tools_plugin" "$express_bar_plugin" "$raf_plugin" \
	"$woocommerce_plugin" "$square_plugin" "$tec_plugin" "$tickets_plugin" "$tickets_plus_plugin" "$bvm_plugin"

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

report_json=$output_dir/bvm-additional-runtime-compatibility.report.json
report_text=$output_dir/bvm-additional-runtime-compatibility.report.txt
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
