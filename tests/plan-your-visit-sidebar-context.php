<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

final class WP_Post
{
	public $ID;
	public $post_type;
	public $post_status;

	public function __construct(int $id, string $postType, string $postStatus)
	{
		$this->ID = $id;
		$this->post_type = $postType;
		$this->post_status = $postStatus;
	}
}

$GLOBALS['vms_test_posts'] = array(
	101 => new WP_Post(101, 'tribe_events', 'publish'),
	102 => new WP_Post(102, 'tribe_events', 'draft'),
	103 => new WP_Post(103, 'page', 'publish'),
	104 => new WP_Post(104, 'tribe_events', 'publish'),
);
$GLOBALS['vms_test_queried_object_id'] = 0;
$GLOBALS['vms_test_singular_post_type'] = '';
$GLOBALS['vms_test_render_calls'] = array();
$GLOBALS['post'] = null;

function vms_plan_your_visit_sidebar_assert(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
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

function shortcode_atts(array $pairs, array $atts, string $shortcode = ''): array
{
	unset($shortcode);
	return array_merge($pairs, $atts);
}

function is_singular(string $postType = ''): bool
{
	$current = (string) ($GLOBALS['vms_test_singular_post_type'] ?? '');
	if ($postType === '') {
		return $current !== '';
	}

	return $current === $postType;
}

function get_queried_object_id(): int
{
	return (int) ($GLOBALS['vms_test_queried_object_id'] ?? 0);
}

function get_post($postId)
{
	$postId = absint($postId);
	return $GLOBALS['vms_test_posts'][$postId] ?? null;
}

function get_post_type($postId = null): string
{
	if ($postId instanceof WP_Post) {
		return (string) $postId->post_type;
	}

	$postId = absint($postId);
	if ($postId <= 0) {
		return '';
	}

	$post = get_post($postId);
	return $post instanceof WP_Post ? (string) $post->post_type : '';
}

function vms_public_event_sidebar_is_rendering_target(int $eventId): bool
{
	unset($eventId);
	return false;
}

function vms_event_details_render_card(int $eventId, bool $guardOnce = true, string $headingOverride = '', string $layout = 'inline'): string
{
	$GLOBALS['vms_test_render_calls'][] = array(
		'event_id' => $eventId,
		'guard_once' => $guardOnce,
		'heading' => $headingOverride,
		'layout' => $layout,
	);

	return sprintf('rendered:%d:%s', $eventId, $layout);
}

require dirname(__DIR__) . '/includes/public/event-details.php';

$resetContext = static function (string $singularPostType, int $queriedObjectId, $globalPost = null): void {
	$GLOBALS['vms_test_singular_post_type'] = $singularPostType;
	$GLOBALS['vms_test_queried_object_id'] = $queriedObjectId;
	$GLOBALS['vms_test_render_calls'] = array();
	$GLOBALS['post'] = $globalPost;
	unset(
		$GLOBALS['bvmgr_event_details_sidebar_rendered'],
		$GLOBALS['bvmgr_event_details_sidebar_manual_rendered'],
		$GLOBALS['bvmgr_public_event_sidebar_active_indexes']
	);
};

try {
	$resetContext('tribe_events', 101);
	$output = vms_event_details_shortcode(array('layout' => 'sidebar'));
	vms_plan_your_visit_sidebar_assert($output === 'rendered:101:sidebar', 'Published single Event request should render automatically.');
	vms_plan_your_visit_sidebar_assert(count($GLOBALS['vms_test_render_calls']) === 1, 'Published single Event request should render exactly once.');

	$resetContext('', 0, $GLOBALS['vms_test_posts'][101]);
	$output = vms_event_details_shortcode(array('layout' => 'sidebar'));
	vms_plan_your_visit_sidebar_assert($output === '', 'Event archive context should not fall back to the incidental global event post.');

	$resetContext('', 101, $GLOBALS['vms_test_posts'][101]);
	$output = vms_event_details_shortcode(array('layout' => 'sidebar'));
	vms_plan_your_visit_sidebar_assert($output === '', 'Event taxonomy/category archive context should not auto-render an arbitrary event.');

	$resetContext('', 777, $GLOBALS['vms_test_posts'][104]);
	$output = vms_event_details_shortcode(array('layout' => 'sidebar'));
	vms_plan_your_visit_sidebar_assert($output === '', 'Venue archive or other non-single event context should not auto-render an arbitrary event.');

	$resetContext('page', 103, $GLOBALS['vms_test_posts'][101]);
	$output = vms_event_details_shortcode(array('layout' => 'sidebar'));
	vms_plan_your_visit_sidebar_assert($output === '', 'Unrelated singular pages should not auto-render Event sidebar output.');

	$resetContext('tribe_events', 103);
	$output = vms_event_details_shortcode(array('layout' => 'sidebar'));
	vms_plan_your_visit_sidebar_assert($output === '', 'Non-event queried objects should not auto-render.');

	$resetContext('tribe_events', 102);
	$output = vms_event_details_shortcode(array('layout' => 'sidebar'));
	vms_plan_your_visit_sidebar_assert($output === '', 'Unpublished events should not auto-render.');

	$resetContext('', 0);
	$output = vms_event_details_shortcode(array('layout' => 'sidebar', 'event_id' => '101'));
	vms_plan_your_visit_sidebar_assert($output === 'rendered:101:sidebar', 'Explicit event_id targeting should still render outside single-event context.');

	$resetContext('', 0);
	$output = vms_event_details_shortcode(array('layout' => 'sidebar', 'id' => '101'));
	vms_plan_your_visit_sidebar_assert($output === 'rendered:101:sidebar', 'Explicit id targeting should still render outside single-event context.');

	$resetContext('', 0);
	$output = vms_event_details_shortcode(array('layout' => 'sidebar', 'event' => '101'));
	vms_plan_your_visit_sidebar_assert($output === 'rendered:101:sidebar', 'Explicit event targeting should still render outside single-event context.');

	$resetContext('tribe_events', 101);
	$output = vms_event_details_shortcode(array('layout' => 'sidebar', 'event_id' => '999'));
	vms_plan_your_visit_sidebar_assert($output === '', 'Invalid explicit event IDs should be rejected without falling back to the queried event.');

	$resetContext('tribe_events', 101);
	$output = vms_event_details_shortcode(array('layout' => 'sidebar', 'event_id' => '102'));
	vms_plan_your_visit_sidebar_assert($output === '', 'Explicit unpublished events should be rejected.');

	$resetContext('', 0);
	$output = vms_event_details_shortcode(array('layout' => 'sidebar', 'id' => '103'));
	vms_plan_your_visit_sidebar_assert($output === '', 'Explicit non-event targets should be rejected.');

	fwrite(STDOUT, "plan your visit sidebar context: PASS\n");
} catch (Throwable $e) {
	fwrite(STDERR, 'plan your visit sidebar context: FAIL - ' . $e->getMessage() . "\n");
	exit(1);
}
