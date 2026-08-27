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

$GLOBALS['vms_event_details_test_cost'] = '';
$GLOBALS['vms_event_details_test_title'] = 'ABBA tribute &#8211; Super Trouper';
$GLOBALS['vms_event_details_test_excerpt'] = '';
$GLOBALS['vms_event_details_test_post_content'] = '';
$GLOBALS['vms_event_details_test_context'] = array();

function vms_event_details_schema_assert_true(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
}

function vms_event_details_schema_assert_same($expected, $actual, string $message): void
{
	if ($expected === $actual) {
		return;
	}

	throw new RuntimeException(
		$message
		. "\nExpected: " . var_export($expected, true)
		. "\nActual: " . var_export($actual, true)
	);
}

function vms_event_details_schema_assert_null($actual, string $message): void
{
	if ($actual === null) {
		return;
	}

	throw new RuntimeException(
		$message
		. "\nExpected: null"
		. "\nActual: " . var_export($actual, true)
	);
}

function add_action(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): bool
{
	unset($hook, $callback, $priority, $acceptedArgs);
	return true;
}

function add_shortcode(string $tag, $callback): bool
{
	unset($tag, $callback);
	return true;
}

function add_filter(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): bool
{
	unset($hook, $callback, $priority, $acceptedArgs);
	return true;
}

function apply_filters(string $hook, $value)
{
	unset($hook);
	$args = func_get_args();
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
	return 'tribe_events';
}

function get_permalink($postId): string
{
	unset($postId);
	return 'https://example.test/events/abba-tribute';
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
	return new WP_Post(absint($postId), 'tribe_events', 'publish', (string) ($GLOBALS['vms_event_details_test_post_content'] ?? ''));
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
	unset($hook);
	return 1;
}

function is_admin(): bool
{
	return false;
}

function is_singular(string $postType = ''): bool
{
	return $postType === '' || $postType === 'tribe_events';
}

function get_queried_object_id(): int
{
	return 123;
}

function wp_json_encode($value, int $flags = 0)
{
	return json_encode($value, $flags);
}

function tribe_get_cost($eventId, bool $withCurrency = false): string
{
	unset($eventId, $withCurrency);
	return (string) ($GLOBALS['vms_event_details_test_cost'] ?? '');
}

function bvmgr_tec_is_cancelled_event(int $eventId): bool
{
	unset($eventId);
	return false;
}

function bvmgr_event_details_context(int $eventId): array
{
	unset($eventId);
	return (array) ($GLOBALS['vms_event_details_test_context'] ?? array());
}

require dirname(__DIR__) . '/includes/public/event-details.php';

try {
	$priceCases = array(
		array('&#36;20.00', 20.00),
		array('&#036;10.00', 10.00),
		array('&#036;15.00', 15.00),
		array('&dollar;15.00', 15.00),
		array('$1,250.00', 1250.00),
		array('$15.00 – $30.00', 15.00),
		array('Free – &#036;30.00', 30.00),
		array('Free', 0.00),
	);

	foreach ($priceCases as $case) {
		$GLOBALS['vms_event_details_test_cost'] = $case[0];
		$context = bvmgr_event_details_ticket_context(123, 0);
		vms_event_details_schema_assert_same($case[1], $context['min_price'], 'Unexpected minimum price for cost string: ' . $case[0]);
	}

	$GLOBALS['vms_event_details_test_cost'] = '';
	vms_event_details_schema_assert_null(bvmgr_event_details_ticket_context(123, 0)['min_price'], 'Empty cost string should not fabricate a price.');
	$GLOBALS['vms_event_details_test_cost'] = 'Price TBA';
	vms_event_details_schema_assert_null(bvmgr_event_details_ticket_context(123, 0)['min_price'], 'Malformed cost string should not fabricate a price.');

	vms_event_details_schema_assert_same(
		'ABBA tribute – Super Trouper',
		bvmgr_event_details_normalize_schema_name('ABBA tribute &#8211; Super Trouper'),
		'Schema name normalization should decode entities and preserve Unicode punctuation.'
	);

	$GLOBALS['vms_event_details_test_excerpt'] = "&lt;p&gt;Description&lt;/p&gt;\n";
	$GLOBALS['vms_event_details_test_post_content'] = '';
	vms_event_details_schema_assert_same(
		'Description',
		bvmgr_event_details_plain_description(123),
		'Plain description should decode encoded markup before stripping tags.'
	);

	$sharedContext = array(
		'url' => 'https://example.test/events/abba-tribute',
		'title' => 'ABBA tribute &#8211; Super Trouper',
		'description' => "&lt;p&gt;Description text&lt;/p&gt;\n",
		'venue_name' => 'Range Hall',
		'address' => '123 Main St',
		'address_2' => '',
		'city' => 'Whitehouse',
		'state' => 'TX',
		'zip' => '75791',
		'country' => 'US',
		'performer_name' => '',
		'status' => 'scheduled',
		'tickets_url' => 'https://example.test/events/abba-tribute',
		'start' => new DateTimeImmutable('2026-08-01T20:00:00-05:00'),
		'end' => new DateTimeImmutable('2026-08-01T22:00:00-05:00'),
		'min_ticket_price' => null,
		'image_url' => 'https://example.test/images/abba.jpg',
	);
	$GLOBALS['vms_event_details_test_context'] = $sharedContext;

	$inputEvent = array(
		'@context' => 'https://schema.org',
		'@type' => 'Event',
		'@id' => 'https://example.test/events/abba-tribute/#event',
		'name' => 'ABBA tribute &#8211; Super Trouper',
		'description' => "&lt;p&gt;Description text&lt;/p&gt;\n",
		'url' => 'https://example.test/events/abba-tribute',
		'startDate' => '2026-08-01T20:00:00-05:00',
		'endDate' => '2026-08-01T22:00:00-05:00',
		'eventStatus' => 'https://schema.org/EventScheduled',
		'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
		'image' => array('https://example.test/images/abba.jpg'),
		'location' => array(
			'@type' => 'Place',
			'name' => 'Range Hall',
			'address' => array(
				'@type' => 'PostalAddress',
				'streetAddress' => '123 Main St',
				'addressLocality' => 'Whitehouse',
				'addressRegion' => 'TX',
				'postalCode' => '75791',
				'addressCountry' => 'US',
			),
		),
		'organizer' => array(
			'@type' => 'Organization',
			'name' => 'Serenade Range',
			'url' => 'https://example.test/',
		),
		'offers' => array(
			array(
				'@type' => 'Offer',
				'url' => 'https://example.test/events/abba-tribute',
				'price' => '&#036;15.00',
				'priceCurrency' => 'USD',
				'availability' => 'https://schema.org/InStock',
				'validFrom' => '2026-07-01T00:00:00-05:00',
				'validThrough' => '2026-08-01T22:00:00-05:00',
			),
		),
	);

	$filtered = bvmgr_event_details_filter_tec_event_schema($inputEvent, array(), new WP_Post(123));
	$filteredJson = wp_json_encode($filtered, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	vms_event_details_schema_assert_true(is_string($filteredJson) && $filteredJson !== '', 'Filtered schema should encode as JSON.');
	vms_event_details_schema_assert_true(is_array(json_decode((string) $filteredJson, true)), 'Filtered schema JSON should decode successfully.');
	vms_event_details_schema_assert_same('ABBA tribute – Super Trouper', $filtered['name'], 'Filtered schema name should be normalized.');
	vms_event_details_schema_assert_same('Description text', $filtered['description'], 'Filtered schema description should be normalized.');
	vms_event_details_schema_assert_same('15.00', $filtered['offers'][0]['price'], 'Filtered schema should preserve the lowest valid numeric price.');
	vms_event_details_schema_assert_same($inputEvent['startDate'], $filtered['startDate'], 'Filtered schema should leave startDate unchanged.');
	vms_event_details_schema_assert_same($inputEvent['endDate'], $filtered['endDate'], 'Filtered schema should leave endDate unchanged.');
	vms_event_details_schema_assert_same($inputEvent['url'], $filtered['url'], 'Filtered schema should leave url unchanged.');
	vms_event_details_schema_assert_same($inputEvent['image'], $filtered['image'], 'Filtered schema should leave image unchanged.');
	vms_event_details_schema_assert_same($inputEvent['location'], $filtered['location'], 'Filtered schema should leave location unchanged.');
	vms_event_details_schema_assert_same($inputEvent['organizer'], $filtered['organizer'], 'Filtered schema should leave organizer unchanged.');
	vms_event_details_schema_assert_same('https://schema.org/InStock', $filtered['offers'][0]['availability'], 'Filtered schema should leave offer availability unchanged.');
	vms_event_details_schema_assert_same('USD', $filtered['offers'][0]['priceCurrency'], 'Filtered schema should leave priceCurrency unchanged.');

	$expectedFilteredKeys = array_keys($inputEvent);
	$actualFilteredKeys = array_keys($filtered);
	sort($expectedFilteredKeys);
	sort($actualFilteredKeys);
	vms_event_details_schema_assert_same($expectedFilteredKeys, $actualFilteredKeys, 'Filtered schema should not add or remove unrelated top-level fields.');

	$GLOBALS['vms_event_details_test_context'] = array_merge($sharedContext, array(
		'min_ticket_price' => 15.00,
	));
	$fallbackSchema = bvmgr_event_details_schema(123);
	$fallbackJson = wp_json_encode($fallbackSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	vms_event_details_schema_assert_true(is_string($fallbackJson) && $fallbackJson !== '', 'Fallback schema should encode as JSON.');
	vms_event_details_schema_assert_true(is_array(json_decode((string) $fallbackJson, true)), 'Fallback schema JSON should decode successfully.');
	vms_event_details_schema_assert_same('ABBA tribute – Super Trouper', $fallbackSchema['name'], 'Fallback schema name should be normalized.');
	vms_event_details_schema_assert_same('Description text', $fallbackSchema['description'], 'Fallback schema description should be normalized.');
	vms_event_details_schema_assert_same('15.00', $fallbackSchema['offers']['price'], 'Fallback schema offer price should stay schema-safe.');
	vms_event_details_schema_assert_same('USD', $fallbackSchema['offers']['priceCurrency'], 'Fallback schema priceCurrency should stay unchanged.');
	vms_event_details_schema_assert_same('https://schema.org/InStock', $fallbackSchema['offers']['availability'], 'Fallback schema availability should stay unchanged.');
	vms_event_details_schema_assert_same('2026-08-01T20:00:00-05:00', $fallbackSchema['startDate'], 'Fallback schema should leave startDate unchanged.');
	vms_event_details_schema_assert_same('2026-08-01T22:00:00-05:00', $fallbackSchema['endDate'], 'Fallback schema should leave endDate unchanged.');
	vms_event_details_schema_assert_same('https://example.test/events/abba-tribute', $fallbackSchema['url'], 'Fallback schema should leave url unchanged.');
	vms_event_details_schema_assert_same(array('https://example.test/images/abba.jpg'), $fallbackSchema['image'], 'Fallback schema should leave image unchanged.');
	vms_event_details_schema_assert_same('Range Hall', $fallbackSchema['location']['name'], 'Fallback schema should leave location name unchanged.');
	vms_event_details_schema_assert_same('Serenade Range', $fallbackSchema['organizer']['name'], 'Fallback schema should leave organizer name unchanged.');

	fwrite(STDOUT, "event details schema normalization: PASS\n");
} catch (Throwable $e) {
	fwrite(STDERR, 'event details schema normalization: FAIL - ' . $e->getMessage() . "\n");
	exit(1);
}
