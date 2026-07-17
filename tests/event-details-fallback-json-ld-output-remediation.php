<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

final class WP_Post
{
	public $ID;
	public $post_type;
	public $post_status;
	public $post_content;

	public function __construct(int $id, string $postType = 'tribe_events', string $postStatus = 'publish', string $postContent = '')
	{
		$this->ID = $id;
		$this->post_type = $postType;
		$this->post_status = $postStatus;
		$this->post_content = $postContent;
	}
}

$GLOBALS['vms_event_details_test_actions'] = array();
$GLOBALS['vms_event_details_test_filters'] = array();
$GLOBALS['vms_event_details_test_apply_filters'] = array();
$GLOBALS['vms_event_details_test_context'] = array();
$GLOBALS['vms_event_details_test_cost'] = '';
$GLOBALS['vms_event_details_test_excerpt'] = '';
$GLOBALS['vms_event_details_test_json_encode_calls'] = array();
$GLOBALS['vms_event_details_test_post_content'] = '';
$GLOBALS['vms_event_details_test_post_type'] = 'tribe_events';
$GLOBALS['vms_event_details_test_queried_object_id'] = 123;
$GLOBALS['vms_event_details_test_title'] = 'Fallback Event';
$GLOBALS['vms_event_details_test_is_admin'] = false;
$GLOBALS['vms_event_details_test_is_singular'] = true;

$assert = static function (bool $condition, string $message): void {
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
};

$assertSame = static function ($expected, $actual, string $message): void {
	if ($expected === $actual) {
		return;
	}

	throw new RuntimeException(
		$message
		. "\nExpected: " . var_export($expected, true)
		. "\nActual: " . var_export($actual, true)
	);
};

$readFile = static function (string $path) use ($assert): string {
	$contents = @file_get_contents($path);
	$assert(is_string($contents) && $contents !== '', 'Expected readable source file: ' . $path);
	return $contents;
};

$sortedKeys = static function (array $value): array {
	$keys = array_keys($value);
	sort($keys);
	return $keys;
};

function add_action(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): bool
{
	if (!isset($GLOBALS['vms_event_details_test_actions'][$hook]) || !is_array($GLOBALS['vms_event_details_test_actions'][$hook])) {
		$GLOBALS['vms_event_details_test_actions'][$hook] = array();
	}

	$GLOBALS['vms_event_details_test_actions'][$hook][] = array(
		'callback' => $callback,
		'priority' => $priority,
		'accepted_args' => $acceptedArgs,
	);

	return true;
}

function add_shortcode(string $tag, $callback): bool
{
	unset($tag, $callback);
	return true;
}

function add_filter(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): bool
{
	if (!isset($GLOBALS['vms_event_details_test_filters'][$hook]) || !is_array($GLOBALS['vms_event_details_test_filters'][$hook])) {
		$GLOBALS['vms_event_details_test_filters'][$hook] = array();
	}

	$GLOBALS['vms_event_details_test_filters'][$hook][] = array(
		'callback' => $callback,
		'priority' => $priority,
		'accepted_args' => $acceptedArgs,
	);

	return true;
}

function apply_filters(string $hook, $value)
{
	$args = func_get_args();
	if (array_key_exists($hook, $GLOBALS['vms_event_details_test_apply_filters'])) {
		$override = $GLOBALS['vms_event_details_test_apply_filters'][$hook];
		return is_callable($override) ? $override(...$args) : $override;
	}

	return $args[1] ?? $value;
}

function __(string $text, string $domain = ''): string
{
	unset($domain);
	return $text;
}

function absint($value): int
{
	return max(0, (int) $value);
}

function sanitize_key(string $value): string
{
	return strtolower((string) preg_replace('/[^a-z0-9_\-]/', '', $value));
}

function wp_strip_all_tags(string $value): string
{
	return trim(strip_tags($value));
}

function strip_shortcodes(string $value): string
{
	return (string) preg_replace('/\[[^\]]+\]/', '', $value);
}

function number_format_i18n(float $number, int $decimals = 0): string
{
	return number_format($number, $decimals, '.', ',');
}

function get_post_type($postId = null): string
{
	unset($postId);
	return (string) ($GLOBALS['vms_event_details_test_post_type'] ?? 'tribe_events');
}

function get_permalink($postId): string
{
	unset($postId);
	return 'https://example.test/events/fallback-event';
}

function get_the_title($postId): string
{
	unset($postId);
	return (string) ($GLOBALS['vms_event_details_test_title'] ?? '');
}

function get_the_excerpt($postId): string
{
	unset($postId);
	return (string) ($GLOBALS['vms_event_details_test_excerpt'] ?? '');
}

function get_post($postId)
{
	return new WP_Post(
		absint($postId),
		(string) ($GLOBALS['vms_event_details_test_post_type'] ?? 'tribe_events'),
		'publish',
		(string) ($GLOBALS['vms_event_details_test_post_content'] ?? '')
	);
}

function home_url(string $path = ''): string
{
	return 'https://example.test' . $path;
}

function trailingslashit(string $value): string
{
	return rtrim($value, '/') . '/';
}

function has_filter(string $hook)
{
	$filters = $GLOBALS['vms_event_details_test_filters'][$hook] ?? array();
	if (empty($filters)) {
		return false;
	}

	return $filters[0]['priority'] ?? true;
}

function is_admin(): bool
{
	return (bool) ($GLOBALS['vms_event_details_test_is_admin'] ?? false);
}

function is_singular(string $postType = ''): bool
{
	if (!(bool) ($GLOBALS['vms_event_details_test_is_singular'] ?? false)) {
		return false;
	}

	return $postType === '' || $postType === 'tribe_events';
}

function get_queried_object_id(): int
{
	return (int) ($GLOBALS['vms_event_details_test_queried_object_id'] ?? 0);
}

function wp_json_encode($value, int $flags = 0)
{
	$GLOBALS['vms_event_details_test_json_encode_calls'][] = array(
		'value' => $value,
		'flags' => $flags,
	);

	return json_encode($value, $flags);
}

function tribe_get_cost($eventId, bool $withCurrency = false): string
{
	unset($eventId, $withCurrency);
	return (string) ($GLOBALS['vms_event_details_test_cost'] ?? '');
}

function vms_tec_is_cancelled_event(int $eventId): bool
{
	unset($eventId);
	return false;
}

function vms_event_details_context(int $eventId): array
{
	unset($eventId);
	return (array) ($GLOBALS['vms_event_details_test_context'] ?? array());
}

$pluginRoot = dirname(__DIR__);
$sourcePath = $pluginRoot . '/includes/public/event-details.php';
$source = $readFile($sourcePath);

$assert(strpos($source, "add_action('wp_head', 'vms_event_details_print_json_ld', 30);") !== false, 'Fallback JSON-LD should stay registered on wp_head at priority 30.');
$assert(strpos($source, "add_filter('tribe_json_ld_event_object', 'vms_event_details_filter_tec_event_schema', 99, 3);") !== false, 'TEC Event schema filter registration should remain unchanged.');
$assert(strpos($source, "add_filter('tribe_json_ld_markup', 'vms_event_details_filter_tec_json_ld_markup', 99);") !== false, 'TEC JSON-LD markup filter registration should remain unchanged.');
$assert(strpos($source, '$schema = vms_event_details_schema($event_id);') !== false, 'Fallback emitter should continue to use vms_event_details_schema() as its producer.');
$assert(strpos($source, '$json = vms_event_details_encode_fallback_json_ld($schema);') !== false, 'Fallback emitter should route output through the narrow fallback encoder.');
$assert(strpos($source, 'JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE') !== false, 'Fallback encoder should use the explicit script-safe JSON flag set.');
$assert(strpos($source, 'wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)') === false, 'Fallback emitter should no longer use the old unescaped-slash encoding flags.');
$assert(strpos($source, '<script type="application/ld+json" class="vms-event-json-ld" data-vms-schema-mode="fallback">') !== false, 'Fallback emitter should preserve the exact fixed script attributes.');

$chunkStart = strpos($source, 'function vms_event_details_encode_fallback_json_ld(array $schema): string');
$chunkEnd = strpos($source, "if (!function_exists('vms_event_details_tec_schema_filters_available'))");
$assert($chunkStart !== false && $chunkEnd !== false && $chunkEnd > $chunkStart, 'Failed to isolate the fallback JSON-LD encoder/emitter source.');
$fallbackChunk = substr($source, (int) $chunkStart, (int) $chunkEnd - (int) $chunkStart);
foreach (array('wp_kses(', 'wp_kses_post(', 'allowed_html', 'esc_html($json)', 'esc_attr($json)') as $forbidden) {
	$assert(strpos($fallbackChunk, $forbidden) === false, 'Fallback JSON-LD sink should not add a broad sanitizer or HTML allowlist: ' . $forbidden);
}

require $sourcePath;

try {
	$wpHeadRegistrations = array_values(array_filter(
		(array) ($GLOBALS['vms_event_details_test_actions']['wp_head'] ?? array()),
		static function (array $registration): bool {
			return $registration['callback'] === 'vms_event_details_print_json_ld';
		}
	));
	$assertSame(1, count($wpHeadRegistrations), 'Fallback JSON-LD should register exactly one wp_head callback.');
	$assertSame(30, $wpHeadRegistrations[0]['priority'], 'Fallback JSON-LD wp_head callback should keep priority 30.');

	$tecEventRegistrations = array_values(array_filter(
		(array) ($GLOBALS['vms_event_details_test_filters']['tribe_json_ld_event_object'] ?? array()),
		static function (array $registration): bool {
			return $registration['callback'] === 'vms_event_details_filter_tec_event_schema';
		}
	));
	$assertSame(1, count($tecEventRegistrations), 'TEC Event schema callback registration should remain singular.');
	$assertSame(99, $tecEventRegistrations[0]['priority'], 'TEC Event schema callback should keep priority 99.');
	$assertSame(3, $tecEventRegistrations[0]['accepted_args'], 'TEC Event schema callback should keep three accepted args.');

	$tecMarkupRegistrations = array_values(array_filter(
		(array) ($GLOBALS['vms_event_details_test_filters']['tribe_json_ld_markup'] ?? array()),
		static function (array $registration): bool {
			return $registration['callback'] === 'vms_event_details_filter_tec_json_ld_markup';
		}
	));
	$assertSame(1, count($tecMarkupRegistrations), 'TEC JSON-LD markup callback registration should remain singular.');
	$assertSame(99, $tecMarkupRegistrations[0]['priority'], 'TEC JSON-LD markup callback should keep priority 99.');
	$assertSame(1, $tecMarkupRegistrations[0]['accepted_args'], 'TEC JSON-LD markup callback should keep its default accepted-args contract.');

	$hostilePerformer = '</script><script>alert(1)</script> "Ampersand & Apostrophe\' <angle> Cafe - 東京';
	$GLOBALS['vms_event_details_test_context'] = array(
		'url' => 'https://example.test/events/cafe-night?view=all&lang=en',
		'title' => 'Caf&eacute; Nights &#8211; L&#039;&eacute;t&eacute; d&#039;Andr&eacute;',
		'description' => '&lt;p&gt;Doors &amp; dancing all night.&lt;/p&gt;',
		'venue_name' => 'Pena Hall',
		'address' => '123 Main St',
		'address_2' => 'Suite B',
		'city' => 'Montreal',
		'state' => 'QC',
		'zip' => 'H2X 1Y4',
		'country' => 'CA',
		'performer_name' => $hostilePerformer,
		'status' => 'scheduled',
		'tickets_url' => 'https://tickets.example.test/buy?event=123&src=web',
		'start' => new DateTimeImmutable('2026-08-01T20:00:00-05:00'),
		'end' => new DateTimeImmutable('2026-08-01T22:00:00-05:00'),
		'min_ticket_price' => 15.00,
		'image_url' => 'https://cdn.example.test/images/cafe-night.png?size=hero&lang=en',
	);
	$GLOBALS['vms_event_details_test_apply_filters'] = array();
	$GLOBALS['vms_event_details_test_json_encode_calls'] = array();

	ob_start();
	vms_event_details_print_json_ld();
	$defaultOutput = (string) ob_get_clean();
	$assertSame('', $defaultOutput, 'Fallback JSON-LD should remain disabled by default when TEC JSON-LD filters are present.');
	$assertSame(array(), $GLOBALS['vms_event_details_test_json_encode_calls'], 'Fallback JSON-LD should not encode anything when its default-print filter vetoes output.');

	$GLOBALS['vms_event_details_test_apply_filters']['vms_event_details_print_json_ld'] = true;
	$expectedSchema = vms_event_details_schema(123);
	$assertSame(
		array('@context', '@id', '@type', 'description', 'endDate', 'eventAttendanceMode', 'eventStatus', 'image', 'location', 'name', 'offers', 'organizer', 'performer', 'startDate', 'url'),
		$sortedKeys($expectedSchema),
		'Representative fallback schema should preserve the expected Event key vocabulary.'
	);
	$assertSame(array('@type', 'address', 'name'), $sortedKeys($expectedSchema['location']), 'Representative fallback schema should preserve the expected Place key vocabulary.');
	$assertSame(array('@type', 'addressCountry', 'addressLocality', 'addressRegion', 'postalCode', 'streetAddress'), $sortedKeys($expectedSchema['location']['address']), 'Representative fallback schema should preserve the expected PostalAddress key vocabulary.');
	$assertSame(array('@type', 'name', 'url'), $sortedKeys($expectedSchema['organizer']), 'Representative fallback schema should preserve the expected Organization key vocabulary.');
	$assertSame(array('@type', 'name'), $sortedKeys($expectedSchema['performer']), 'Representative fallback schema should preserve the expected MusicGroup key vocabulary.');
	$assertSame(array('@type', 'availability', 'price', 'priceCurrency', 'url', 'validFrom'), $sortedKeys($expectedSchema['offers']), 'Representative fallback schema should preserve the expected Offer key vocabulary.');

	$GLOBALS['vms_event_details_test_json_encode_calls'] = array();
	ob_start();
	vms_event_details_print_json_ld();
	$output = (string) ob_get_clean();
	$assert($output !== '', 'Fallback JSON-LD should emit output when explicitly enabled.');

	$expectedFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
	$assertSame(1, count($GLOBALS['vms_event_details_test_json_encode_calls']), 'Fallback JSON-LD should encode the schema exactly once.');
	$assertSame($expectedSchema, $GLOBALS['vms_event_details_test_json_encode_calls'][0]['value'], 'Fallback JSON-LD should encode the schema produced by vms_event_details_schema().');
	$assertSame($expectedFlags, $GLOBALS['vms_event_details_test_json_encode_calls'][0]['flags'], 'Fallback JSON-LD should use the explicit script-safe JSON flag set.');

	$expectedPrefix = "\n" . '<script type="application/ld+json" class="vms-event-json-ld" data-vms-schema-mode="fallback">';
	$expectedSuffix = '</script>' . "\n";
	$assertSame($expectedPrefix, substr($output, 0, strlen($expectedPrefix)), 'Fallback JSON-LD should preserve the exact opening script element and leading newline.');
	$assertSame($expectedSuffix, substr($output, -strlen($expectedSuffix)), 'Fallback JSON-LD should preserve the exact closing script element and trailing newline.');

	$previousLibxml = libxml_use_internal_errors(true);
	$dom = new DOMDocument('1.0', 'UTF-8');
	$loaded = $dom->loadHTML('<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div id="root">' . $output . '</div></body></html>');
	$errors = libxml_get_errors();
	libxml_clear_errors();
	libxml_use_internal_errors($previousLibxml);
	$assert($loaded, 'Fallback JSON-LD output should parse as HTML.');
	$assertSame(0, count($errors), 'Fallback JSON-LD output should parse without libxml errors.');

	$xpath = new DOMXPath($dom);
	$rootChildren = $xpath->query('//*[@id="root"]/*');
	$assertSame(1, $rootChildren instanceof DOMNodeList ? $rootChildren->length : 0, 'Fallback JSON-LD output should contain exactly one top-level element.');

	$scriptNodes = $xpath->query('//*[@id="root"]/script');
	$assertSame(1, $scriptNodes instanceof DOMNodeList ? $scriptNodes->length : 0, 'Fallback JSON-LD output should emit exactly one script element.');
	$script = $scriptNodes instanceof DOMNodeList ? $scriptNodes->item(0) : null;
	$assert($script instanceof DOMElement, 'Fallback JSON-LD output should expose the script element in the parsed DOM.');

	$scriptAttributes = array();
	foreach ($script->attributes as $attribute) {
		if ($attribute instanceof DOMAttr) {
			$scriptAttributes[$attribute->name] = $attribute->value;
		}
	}
	ksort($scriptAttributes);
	$assertSame(
		array(
			'class' => 'vms-event-json-ld',
			'data-vms-schema-mode' => 'fallback',
			'type' => 'application/ld+json',
		),
		$scriptAttributes,
		'Fallback JSON-LD script should keep exactly the fixed attributes and no extras.'
	);

	$decoded = json_decode($script->textContent, true);
	$assert(is_array($decoded), 'Fallback JSON-LD script body should decode as JSON.');
	$assertSame($expectedSchema, $decoded, 'Fallback JSON-LD script body should preserve the decoded schema object exactly.');
	$assertSame("Café Nights – L'été d'André", $decoded['name'], 'Fallback JSON-LD should preserve normalized Unicode event names.');
	$assertSame('Doors & dancing all night.', $decoded['description'], 'Fallback JSON-LD should preserve decoded description text.');
	$assertSame('https://example.test/events/cafe-night?view=all&lang=en', $decoded['url'], 'Fallback JSON-LD should preserve the decoded event URL.');
	$assertSame('https://tickets.example.test/buy?event=123&src=web', $decoded['offers']['url'], 'Fallback JSON-LD should preserve the decoded Offer URL.');
	$assertSame('https://cdn.example.test/images/cafe-night.png?size=hero&lang=en', $decoded['image'][0], 'Fallback JSON-LD should preserve the decoded image URL.');
	$assertSame($hostilePerformer, $decoded['performer']['name'], 'Fallback JSON-LD should preserve hostile text as inert decoded data.');

	$assert(strpos($output, '</script><script>alert(1)</script>') === false, 'Fallback JSON-LD output should not contain a literal hostile closing sequence.');
	$assert(strpos($output, '\u003C\/script\u003E\u003Cscript\u003Ealert(1)\u003C\/script\u003E') !== false, 'Fallback JSON-LD output should hex-encode the hostile closing sequence inside the script body.');

	$allScripts = $xpath->query('//script');
	$assertSame(1, $allScripts instanceof DOMNodeList ? $allScripts->length : 0, 'Hostile fallback schema text should not create a second script node.');
	foreach ($xpath->query('//*[@id="root"]//*') as $element) {
		if (!$element instanceof DOMElement) {
			continue;
		}

		foreach ($element->attributes as $attribute) {
			if (!$attribute instanceof DOMAttr) {
				continue;
			}

			$assert(strpos(strtolower($attribute->name), 'on') !== 0, 'Fallback JSON-LD output should not emit inline event-handler attributes.');
		}
	}

	$GLOBALS['vms_event_details_test_context'] = array();
	$GLOBALS['vms_event_details_test_json_encode_calls'] = array();
	ob_start();
	vms_event_details_print_json_ld();
	$emptyOutput = (string) ob_get_clean();
	$assertSame('', $emptyOutput, 'Empty fallback schema data should keep the no-output behavior.');
	$assertSame(array(), $GLOBALS['vms_event_details_test_json_encode_calls'], 'Empty fallback schema data should not reach wp_json_encode().');

	fwrite(STDOUT, "event details fallback json ld output remediation: PASS\n");
} catch (Throwable $e) {
	fwrite(STDERR, 'event details fallback json ld output remediation: FAIL - ' . $e->getMessage() . "\n");
	exit(1);
}
