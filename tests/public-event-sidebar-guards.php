<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

$GLOBALS['vms_test_event_id'] = 0;

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

function vms_public_event_sidebar_guard_assert(bool $condition, string $message): void
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

function sanitize_html_class(string $value): string
{
	return (string) preg_replace('/[^A-Za-z0-9_\-]/', '', $value);
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

function is_singular(string $post_type = ''): bool
{
	return $post_type === 'tribe_events';
}

function get_queried_object_id(): int
{
	return (int) ($GLOBALS['vms_test_event_id'] ?? 0);
}

function get_post_type($post_id = null): string
{
	unset($post_id);
	return 'tribe_events';
}

function get_post($post_id)
{
	$post_id = absint($post_id);
	if ($post_id <= 0) {
		return null;
	}

	return new WP_Post($post_id, 'tribe_events', 'publish');
}

function esc_attr(string $value): string
{
	return $value;
}

function esc_html(string $value): string
{
	return $value;
}

function esc_url(string $value): string
{
	return $value;
}

function esc_html_e(string $text, string $domain = ''): void
{
	unset($domain);
	echo $text;
}

function bvmgr_event_details_context(int $event_id): array
{
	return array(
		'event_id' => $event_id,
		'date_label' => 'June 18, 2026',
		'time_label' => '8:00 PM',
		'gates_label' => '7:00 PM',
		'venue_name' => 'Range Hall',
		'address_lines' => array('123 Main St'),
		'ticket_label' => 'On sale now',
		'directions_url' => 'https://example.test/directions',
		'calendar_url' => '',
		'questions_url' => 'https://example.test/questions',
		'status' => 'scheduled',
	);
}

function bvmgr_vendor_profiles_render_event_vendor_sidebar(int $event_id): string
{
	unset($event_id);
	return '';
}

require dirname(__DIR__) . '/includes/public/event-details.php';
require dirname(__DIR__) . '/includes/public/event-sidebar.php';

$GLOBALS['vms_test_event_id'] = 101;
$manualMarkup = bvmgr_event_details_shortcode(array('layout' => 'sidebar'));
vms_public_event_sidebar_guard_assert($manualMarkup !== '', 'Manual current-event sidebar shortcode should render.');
$autoAfterManual = bvmgr_public_event_sidebar_render_stack(101);
vms_public_event_sidebar_guard_assert($autoAfterManual === '', 'Auto sidebar should skip Event Details after a manual current-event sidebar render.');

unset($GLOBALS['bvmgr_event_details_sidebar_rendered'], $GLOBALS['bvmgr_event_details_sidebar_manual_rendered'], $GLOBALS['bvmgr_public_event_sidebar_active_indexes']);
$GLOBALS['vms_test_event_id'] = 202;
$autoMarkup = bvmgr_public_event_sidebar_render_stack(202);
vms_public_event_sidebar_guard_assert($autoMarkup !== '', 'Auto sidebar should render Event Details when no manual sidebar render happened first.');
$manualAfterAuto = bvmgr_event_details_shortcode(array('layout' => 'sidebar'));
vms_public_event_sidebar_guard_assert($manualAfterAuto !== '', 'Explicit manual sidebar shortcode should still render outside the target sidebar after auto render.');

unset($GLOBALS['bvmgr_event_details_sidebar_rendered'], $GLOBALS['bvmgr_event_details_sidebar_manual_rendered'], $GLOBALS['bvmgr_public_event_sidebar_active_indexes']);
$GLOBALS['vms_test_event_id'] = 303;
$autoMarkup = bvmgr_public_event_sidebar_render_stack(303);
vms_public_event_sidebar_guard_assert($autoMarkup !== '', 'Auto sidebar should render for duplicate-suppression test.');
bvmgr_public_event_sidebar_track_context_before('sidebar-primary', true);
$duplicateTargetSidebar = bvmgr_event_details_shortcode(array('layout' => 'sidebar'));
bvmgr_public_event_sidebar_track_context_after('sidebar-primary', true);
vms_public_event_sidebar_guard_assert($duplicateTargetSidebar === '', 'Target sidebar shortcode should stay suppressed after the auto sidebar already rendered.');

fwrite(STDOUT, "Public event sidebar guards OK.\n");
