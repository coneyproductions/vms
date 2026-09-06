<?php
declare(strict_types=1);

$sourceRoot = dirname(__DIR__, 2) . '/companion-plugins/vmsx-weather-risk';
foreach (array(
	'includes/providers/interface-provider.php',
	'includes/providers/provider-openmeteo.php',
	'includes/providers/provider-openweather.php',
	'includes/providers/provider-weatherapi.php',
	'includes/services/weather-normalizer.php',
	'includes/settings.php',
	'includes/cache/scheduler.php',
	'includes/admin/page-settings.php',
	'includes/admin/page-event-risk.php',
) as $relative) {
	if (!is_file($sourceRoot . '/' . $relative)) {
		fwrite(STDERR, "Missing Weather candidate source: {$relative}\n");
		exit(2);
	}
}

define('ABSPATH', dirname($sourceRoot) . '/');
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);
define('VMSX_WR_VERSION', '0.1.12');
define('VMSX_WR_OPTION_SETTINGS', 'vmsx_weather_risk_settings');
define('VMSX_WR_CRON_HOOK', 'vmsx_weather_risk_refresh_cron');

$GLOBALS['weather_options'] = array();
$GLOBALS['weather_writes'] = array();
$GLOBALS['weather_hook_sequence'] = array();
$GLOBALS['weather_scheduled'] = false;
$GLOBALS['weather_remote_queue'] = array();
$GLOBALS['weather_remote_calls'] = array();

class WP_Error
{
	public function __construct(private string $code, private string $message)
	{
	}

	public function get_error_message(): string
	{
		return $this->message;
	}
}

function is_wp_error($value): bool
{
	return $value instanceof WP_Error;
}

function __(string $text, string $domain = 'default'): string
{
	return $text;
}

function esc_html__(string $text, string $domain = 'default'): string
{
	return $text;
}

function sanitize_text_field(string $value): string
{
	return trim(strip_tags($value));
}

function sanitize_key(string $value): string
{
	return strtolower((string) preg_replace('/[^a-z0-9_\-]/i', '', $value));
}

function wp_parse_args($args, array $defaults = array()): array
{
	return array_merge($defaults, is_array($args) ? $args : array());
}

function get_option(string $key, $default = false)
{
	return array_key_exists($key, $GLOBALS['weather_options']) ? $GLOBALS['weather_options'][$key] : $default;
}

function add_option(string $key, $value, string $deprecated = '', bool $autoload = true): bool
{
	$GLOBALS['weather_options'][$key] = $value;
	$GLOBALS['weather_writes'][] = array('add_option', $key);
	return true;
}

function update_option(string $key, $value, bool $autoload = true): bool
{
	$GLOBALS['weather_options'][$key] = $value;
	$GLOBALS['weather_writes'][] = array('update_option', $key);
	return true;
}

function add_action(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): void
{
	$GLOBALS['weather_hook_sequence'][] = array('action', $hook, $priority);
}

function add_filter(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): void
{
	$GLOBALS['weather_hook_sequence'][] = array('filter', $hook, $priority);
}

function wp_next_scheduled(string $hook)
{
	return $GLOBALS['weather_scheduled'] ? 123456789 : false;
}

function wp_schedule_event(int $timestamp, string $recurrence, string $hook): bool
{
	$GLOBALS['weather_hook_sequence'][] = array('schedule', $recurrence, $hook);
	$GLOBALS['weather_scheduled'] = true;
	$GLOBALS['weather_writes'][] = array('schedule', $hook);
	return true;
}

function wp_clear_scheduled_hook(string $hook): int
{
	$GLOBALS['weather_scheduled'] = false;
	$GLOBALS['weather_writes'][] = array('clear_schedule', $hook);
	return 1;
}

function add_query_arg(array $args, string $url): string
{
	return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($args);
}

function home_url(string $path = ''): string
{
	return 'https://fixture.invalid/' . ltrim($path, '/');
}

function esc_html(string $value): string
{
	return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function esc_attr(string $value): string
{
	return esc_html($value);
}

function esc_url(string $value): string
{
	return $value;
}

function checked($checked, $current = true, bool $echo = true): string
{
	$result = $checked == $current ? 'checked="checked"' : '';
	if ($echo) {
		echo $result;
	}
	return $result;
}

function selected($selected, $current = true, bool $echo = true): string
{
	$result = $selected == $current ? 'selected="selected"' : '';
	if ($echo) {
		echo $result;
	}
	return $result;
}

function submit_button(string $text, string $type = 'primary', string $name = 'submit', bool $wrap = true): void
{
	echo '<button type="submit">' . esc_html($text) . '</button>';
}

function wp_nonce_field(string $action): void
{
	echo '<input type="hidden" value="nonce-' . esc_attr($action) . '">';
}

function admin_url(string $path = ''): string
{
	return 'https://fixture.invalid/wp-admin/' . ltrim($path, '/');
}

function absint($value): int
{
	return abs((int) $value);
}

final class VMSX_Weather_Risk_Helpers
{
	public static function remote_get_json(string $url, array $args = array())
	{
		$GLOBALS['weather_remote_calls'][] = array($url, $args);
		return array_shift($GLOBALS['weather_remote_queue']);
	}

	public static function format_local_timestamp(int $timestamp): string
	{
		return gmdate('Y-m-d H:i', $timestamp) . ' UTC';
	}

	public static function yes_no($value): int
	{
		return in_array($value, array(1, '1', true, 'yes', 'on'), true) ? 1 : 0;
	}

	public static function clamp_int($value, int $min, int $max): int
	{
		return max($min, min($max, (int) $value));
	}

	public static function sanitize_money_to_cents($value): int
	{
		return (int) round((float) $value * 100);
	}

	public static function sanitize_float_string($value, int $decimals = 6): string
	{
		if ($value === '' || !is_numeric($value)) {
			return '';
		}
		return number_format((float) $value, $decimals, '.', '');
	}

	public static function contains_keywords(string $haystack, array $needles): bool
	{
		foreach ($needles as $needle) {
			if (stripos($haystack, (string) $needle) !== false) {
				return true;
			}
		}
		return false;
	}

	public static function recent_event_plan_options(int $limit): array
	{
		return array(array('id' => 501, 'label' => 'Fixture Event'));
	}

	public static function get_event_summary(int $eventPlanId): array
	{
		return array('title' => 'Fixture Event', 'date' => '2026-09-10', 'venue_name' => 'Fixture Venue');
	}

	public static function clean_display_text(string $value): string
	{
		return sanitize_text_field($value);
	}

	public static function band_css_class(string $label): string
	{
		return sanitize_key($label);
	}

	public static function format_money_cents(?int $cents): string
	{
		return $cents === null ? 'N/A' : '$' . number_format($cents / 100, 2);
	}

	public static function format_log_context(array $context): string
	{
		return '';
	}
}

final class VMSX_Weather_Risk_Capabilities
{
	public static function can_manage_settings(): bool
	{
		return true;
	}

	public static function can_view_event(int $eventPlanId): bool
	{
		return $eventPlanId === 501;
	}
}

final class VMSX_Weather_Risk_Compatibility
{
	public static function core_function(string $legacyName): string
	{
		return '';
	}
}

final class VMSX_Weather_Risk_Admin_Menu
{
	public const DETAILS_SLUG = 'vms-weather-risk';

	public static function details_url(int $eventPlanId = 0): string
	{
		$url = admin_url('admin.php?page=vms-weather-risk');
		return $eventPlanId > 0 ? $url . '&event_plan_id=' . $eventPlanId : $url;
	}

	public static function settings_url(): string
	{
		return admin_url('admin.php?page=vms-weather-risk-settings');
	}
}

final class VMSX_Weather_Risk_Advisory_Engine
{
	public static ?array $snapshot = null;
	public static int $refreshCalls = 0;

	public static function get_snapshot(int $eventPlanId): ?array
	{
		return self::$snapshot;
	}

	public static function refresh_snapshot(int $eventPlanId, bool $force = false, string $source = 'manual'): array
	{
		self::$refreshCalls++;
		return self::$snapshot ?? array();
	}
}

final class VMSX_Weather_Risk_Logging
{
	public static function recent(int $limit, int $eventPlanId): array
	{
		return array();
	}
}

require $sourceRoot . '/includes/providers/interface-provider.php';
require $sourceRoot . '/includes/services/weather-normalizer.php';
require $sourceRoot . '/includes/providers/provider-openmeteo.php';
require $sourceRoot . '/includes/providers/provider-openweather.php';
require $sourceRoot . '/includes/providers/provider-weatherapi.php';
require $sourceRoot . '/includes/settings.php';
require $sourceRoot . '/includes/cache/scheduler.php';
require $sourceRoot . '/includes/admin/page-settings.php';
require $sourceRoot . '/includes/admin/page-event-risk.php';

$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
	if (!$condition) {
		$failures[] = $message;
	}
};

$defaults = VMSX_Weather_Risk_Settings::defaults();
$assert(($defaults['preferred_provider'] ?? '') === 'openmeteo' && ($defaults['provider_openmeteo_enabled'] ?? 0) === 1, 'Open-Meteo is not the enabled no-credential default.');
$assert(($defaults['provider_noaa_enabled'] ?? 0) === 1, 'The accepted free-provider baseline no longer includes NOAA.');

$windowStart = strtotime('2026-09-10 18:00:00 UTC');
$windowEnd = strtotime('2026-09-10 20:00:00 UTC');
$window = array('window_start_utc' => $windowStart, 'window_end_utc' => $windowEnd);
$location = array('label' => 'Fixture Venue', 'latitude' => 40.7128, 'longitude' => -74.0060);
$GLOBALS['weather_remote_queue'][] = array(
	'hourly' => array(
		'time' => array('2026-09-10T17:00', '2026-09-10T18:00', '2026-09-10T19:00', '2026-09-10T21:00'),
		'precipitation_probability' => array(10, 40, 80, 90),
		'precipitation' => array(0.0, 0.10, 0.25, 0.5),
		'windspeed_10m' => array(5, 12, 20, 25),
		'windgusts_10m' => array(8, 18, 31, 35),
		'temperature_2m' => array(72, 70, 68, 65),
		'weathercode' => array(0, 61, 95, 99),
	),
);
$openMeteo = new VMSX_Weather_Risk_Provider_OpenMeteo();
$success = $openMeteo->fetch($location, $window, $defaults);
$assert(($success['status'] ?? '') === 'success' && count($success['hours'] ?? array()) === 2, 'Open-Meteo success did not normalize and window-filter hourly rows.');
$assert(($success['summary']['window_precip_probability_max'] ?? 0) === 80, 'Open-Meteo summary lost maximum precipitation probability.');
$assert(($success['summary']['window_wind_max_mph'] ?? 0) === 31.0 && !empty($success['summary']['lightning_any']), 'Open-Meteo gust/lightning risk normalization changed.');
$openMeteoUrl = (string) ($GLOBALS['weather_remote_calls'][0][0] ?? '');
$assert(str_starts_with($openMeteoUrl, 'https://api.open-meteo.com/') && !str_contains($openMeteoUrl, 'key='), 'Open-Meteo did not use its keyless HTTPS path.');

$GLOBALS['weather_remote_queue'][] = new WP_Error('blocked', 'stubbed provider failure');
$failed = $openMeteo->fetch($location, $window, $defaults);
$assert(($failed['status'] ?? '') === 'error' && ($failed['error_message'] ?? '') === 'stubbed provider failure', 'A stubbed Open-Meteo transport failure did not fail closed.');
$missingLocation = $openMeteo->fetch(array('label' => 'Unknown'), $window, $defaults);
$assert(($missingLocation['status'] ?? '') === 'error' && str_contains((string) ($missingLocation['error_message'] ?? ''), 'latitude and longitude'), 'Open-Meteo did not reject missing coordinates before transport.');

$openWeather = new VMSX_Weather_Risk_Provider_OpenWeather();
$weatherApi = new VMSX_Weather_Risk_Provider_WeatherAPI();
$remoteCountBeforeCredentials = count($GLOBALS['weather_remote_calls']);
$missingOpenWeather = $openWeather->fetch($location, $window, array('provider_openweather_enabled' => 1, 'provider_openweather_api_key' => ''));
$missingWeatherApi = $weatherApi->fetch($location, $window, array('provider_weatherapi_enabled' => 1, 'provider_weatherapi_api_key' => ''));
$assert(($missingOpenWeather['status'] ?? '') === 'skipped' && ($missingWeatherApi['status'] ?? '') === 'skipped', 'Credentialed providers did not report missing keys as skipped.');
$assert(count($GLOBALS['weather_remote_calls']) === $remoteCountBeforeCredentials, 'A credentialed provider attempted transport without a key.');

$sanitized = VMSX_Weather_Risk_Settings::sanitize(
	array('provider_mode' => 'invalid', 'preferred_provider' => 'invalid', 'watch_window_days' => 99, 'provider_openweather_api_key' => ''),
	array_merge($defaults, array('provider_openweather_api_key' => 'preserved-secret'))
);
$assert(($sanitized['provider_mode'] ?? '') === 'conservative' && ($sanitized['preferred_provider'] ?? '') === 'noaa', 'Settings enum fallback changed.');
$assert(($sanitized['watch_window_days'] ?? 0) === 14, 'Settings watch-window bounds changed.');
$assert(($sanitized['provider_openweather_api_key'] ?? '') === 'preserved-secret', 'A blank settings submission erased the existing provider key.');

$GLOBALS['weather_writes'] = array();
VMSX_Weather_Risk_Settings::seed_defaults();
VMSX_Weather_Risk_Scheduler::activate();
$assert(($GLOBALS['weather_writes'][0] ?? null) === array('add_option', VMSX_WR_OPTION_SETTINGS), 'Disposable activation did not seed settings exactly once.');
$filterIndex = array_search(array('filter', 'cron_schedules', 10), $GLOBALS['weather_hook_sequence'], true);
$scheduleIndex = array_search(array('schedule', 'vmsx_weather_risk_hourly', VMSX_WR_CRON_HOOK), $GLOBALS['weather_hook_sequence'], true);
$assert(is_int($filterIndex) && is_int($scheduleIndex) && $filterIndex < $scheduleIndex, 'Activation did not register the custom cron interval before scheduling it.');
$assert($GLOBALS['weather_scheduled'] === true, 'Disposable activation did not schedule refresh.');
VMSX_Weather_Risk_Scheduler::deactivate();
$assert($GLOBALS['weather_scheduled'] === false && end($GLOBALS['weather_writes']) === array('clear_schedule', VMSX_WR_CRON_HOOK), 'Disposable deactivation did not clear only its scheduled hook.');

$snapshot = array(
	'event' => VMSX_Weather_Risk_Helpers::get_event_summary(501),
	'window' => $window,
	'location' => $location,
	'provider_health' => array('expected' => 2, 'responded' => 1, 'skipped' => 1, 'label' => 'Partial'),
	'advisory' => array('label' => 'Monitor Closely', 'score' => 40),
	'weather_risk' => array('score' => 40, 'band' => 'Watch'),
	'sales_risk' => array('score' => 20, 'band' => 'Low'),
	'financial_exposure' => array('score' => 10, 'band' => 'Low'),
	'providers' => array($success, $failed),
	'sales_context' => array('sold_qty' => 10, 'gross_cents' => 20000, 'provider_label' => 'BVM'),
	'dt_context' => array('available' => false, 'note' => 'Optional Data Tools unavailable.'),
	'reasons' => array('Fixture reason'),
	'warnings' => array(),
	'computed_at_local' => '2026-09-05 12:00 UTC',
	'confidence_band' => 'Medium',
	'decision_checkpoint' => 'Review at event window.',
);
VMSX_Weather_Risk_Advisory_Engine::$snapshot = $snapshot;
$GLOBALS['weather_writes'] = array();
$writesBeforePages = $GLOBALS['weather_writes'];
$_GET = array();
ob_start();
VMSX_Weather_Risk_Page_Settings::render();
$settingsHtml = (string) ob_get_clean();
$_GET = array('event_plan_id' => '501');
ob_start();
VMSX_Weather_Risk_Page_Event_Risk::render();
$eventHtml = (string) ob_get_clean();
$assert($GLOBALS['weather_writes'] === $writesBeforePages, 'Loading read-only Weather admin pages performed an option, schedule, or destructive persistence write.');
$assert(VMSX_Weather_Risk_Advisory_Engine::$refreshCalls === 0, 'Loading an Event Plan workspace with a current snapshot triggered a provider refresh.');
$assert(str_contains($settingsHtml, 'Enable Open-Meteo') && str_contains($eventHtml, 'Fixture Event'), 'Weather settings/Event Plan workspace did not render in the isolated admin fixture.');

$settingsSource = (string) file_get_contents($sourceRoot . '/includes/admin/page-settings.php');
$eventPageSource = (string) file_get_contents($sourceRoot . '/includes/admin/page-event-risk.php');
$destructiveCalls = preg_match('/\b(?:delete_option|delete_post_meta|wp_delete_post|wp_trash_post)\s*\(/', $settingsSource . "\n" . $eventPageSource) === 1;
$assert(!$destructiveCalls, 'A Weather admin page directly contains a destructive persistence call.');
$assert(str_contains($eventPageSource, "refresh_snapshot(\$event_plan_id, false, 'details_initial')"), 'The bounded initial-page cache population path changed without review.');

if ($failures !== array()) {
	fwrite(STDERR, "Weather 0.1.12 provider/persistence-contract failures:\n- " . implode("\n- ", $failures) . "\n");
	exit(1);
}

echo "Weather 0.1.12 provider success/failure/credentials/Open-Meteo/cron/admin-read contracts passed with network stubbed.\n";
