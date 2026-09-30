<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap-wordpress.php';
vms_tests_require_wordpress(__DIR__);

if (!function_exists('bvmgr_event_plan_get_public_ticket_state')) {
	require_once dirname(__DIR__) . '/backstage-venue-manager.php';
}

$assert = static function (bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
};

$created_posts = array();
$register_post = static function (int $post_id) use (&$created_posts): int {
	$created_posts[] = $post_id;
	return $post_id;
};
$cleanup = static function () use (&$created_posts): void {
	foreach (array_reverse($created_posts) as $post_id) {
		wp_delete_post((int) $post_id, true);
	}
	if (function_exists('bvmgr_calendar_feed_cache_bust')) {
		bvmgr_calendar_feed_cache_bust(true);
	}
};

try {
	wp_set_current_user(1);
	$assert(function_exists('bvmgr_event_plan_get_public_ticket_state'), 'Canonical public ticket state helper is unavailable.');
	$assert(function_exists('bvmgr_event_plan_get_ticket_destination'), 'Canonical ticket destination helper is unavailable.');
	$assert(function_exists('bvmgr_event_details_ticket_context'), 'Event details ticket context helper is unavailable.');
	$assert(function_exists('bvmgr_event_details_clean_tec_offers_schema'), 'Event offer schema cleaner is unavailable.');
	$assert(function_exists('bvmgr_events_photo_cta_context'), 'Photo-card CTA helper is unavailable.');
	$assert(function_exists('bvmgr_public_calendar_render_list_view'), 'Public calendar renderer is unavailable.');

	$fixture_suffix = strtolower(wp_generate_password(8, false, false));
	$event_date = wp_date('Y-m-d', strtotime('+60 days 7:00pm'));
	$plan_id = wp_insert_post(array(
		'post_type' => 'vms_event_plan',
		'post_status' => 'publish',
		'post_title' => 'No-ticket public eligibility proof ' . $fixture_suffix,
	), true);
	$assert(!is_wp_error($plan_id) && (int) $plan_id > 0, 'Could not create the Event Plan fixture.');
	$plan_id = $register_post((int) $plan_id);

	$event_id = wp_insert_post(array(
		'post_type' => 'tribe_events',
		'post_status' => 'publish',
		'post_title' => 'No-ticket public event proof ' . $fixture_suffix,
	), true);
	$assert(!is_wp_error($event_id) && (int) $event_id > 0, 'Could not create the TEC event fixture.');
	$event_id = $register_post((int) $event_id);

	update_post_meta($plan_id, '_vms_event_plan_status', 'published');
	update_post_meta($plan_id, '_vms_event_date', $event_date);
	update_post_meta($plan_id, '_vms_start_time', '19:00');
	update_post_meta($plan_id, '_vms_end_time', '22:00');
	update_post_meta($plan_id, '_vms_tec_event_id', $event_id);
	update_post_meta($plan_id, '_vms_tec_event_url', get_permalink($event_id));
	update_post_meta($event_id, '_vms_event_plan_id', $plan_id);
	update_post_meta($event_id, '_EventStartDate', $event_date . ' 19:00:00');
	update_post_meta($event_id, '_EventEndDate', $event_date . ' 22:00:00');
	delete_post_meta($plan_id, '_vms_ticketing_config_v2');
	if (function_exists('bvmgr_calendar_feed_cache_bust')) {
		bvmgr_calendar_feed_cache_bust(true);
	}

	$default_config = bvmgr_ticketing_v2_get_config($plan_id);
	$assert(!empty($default_config['tickets']), 'Fresh-plan editor defaults should remain available to operators.');
	$assert(bvmgr_ticketing_v2_get_saved_config($plan_id) === array(), 'The no-ticket fixture must not have saved ticket configuration.');

	$state = bvmgr_event_plan_get_public_ticket_state($plan_id);
	$assert(empty($state['eligible']) && ($state['state'] ?? '') === 'none', 'Unsaved defaults must not create a public ticket offering.');
	$destination = bvmgr_event_plan_get_ticket_destination($plan_id, (string) get_permalink($event_id));
	$assert(($destination['url'] ?? '') === '', 'A no-ticket event must not expose its event permalink as a purchase destination.');

	$ticket_context = bvmgr_event_details_ticket_context($event_id, $plan_id);
	$assert(($ticket_context['label'] ?? '') === '', 'A no-ticket event must not render ticket availability copy.');
	$assert(($ticket_context['min_price'] ?? null) === null, 'Unsaved default ticket price must not enter public event context.');
	$schema = bvmgr_event_details_clean_tec_offers_schema(
		array('offers' => array(array('@type' => 'Offer', 'price' => '20.00', 'availability' => 'https://schema.org/InStock'))),
		array('ticket_has_public_offering' => false, 'ticket_is_external' => false, 'status' => 'scheduled'),
		$event_id
	);
	$assert(!isset($schema['offers']), 'No-ticket schema cleanup must remove stale/default offers.');

	$feed_event = array(
		'event_plan_id' => $plan_id,
		'title' => get_the_title($plan_id),
		'date_key' => $event_date,
		'start_local' => $event_date . 'T19:00:00',
		'end_local' => $event_date . 'T22:00:00',
		'public_url' => get_permalink($event_id),
		'ticket_url' => null,
		'ticket_is_external' => false,
		'plan_status' => 'published',
	);
	$photo_cta = bvmgr_events_photo_cta_context($feed_event, array());
	$assert(($photo_cta['label'] ?? '') === 'View Details', 'No-ticket photo cards must use a neutral details CTA.');
	$assert(($photo_cta['url'] ?? '') === get_permalink($event_id), 'No-ticket photo cards should retain the event details destination.');
	$calendar_markup = bvmgr_public_calendar_render_list_view(array($feed_event), true, false, false);
	$assert(strpos($calendar_markup, 'Get Tickets') === false && strpos($calendar_markup, 'Buy Tickets') === false, 'No-ticket calendar entries must not render a ticket CTA.');

	$product_id = wp_insert_post(array(
		'post_type' => 'product',
		'post_status' => 'publish',
		'post_title' => 'Native ticket eligibility proof ' . $fixture_suffix,
		'meta_input' => array('_tribe_wooticket_for_event' => $event_id),
	), true);
	$assert(!is_wp_error($product_id) && (int) $product_id > 0, 'Could not create the native ticket product fixture.');
	$product_id = $register_post((int) $product_id);
	$native_state = bvmgr_event_plan_get_public_ticket_state($plan_id);
	$assert(!empty($native_state['eligible']) && ($native_state['state'] ?? '') === 'native', 'A Published linked ticket product must preserve native public ticketing.');
	$native_destination = bvmgr_event_plan_get_ticket_destination($plan_id, (string) get_permalink($event_id));
	$assert(($native_destination['url'] ?? '') === get_permalink($event_id), 'Native ticketing must retain the event-page purchase destination.');
	$feed_event['ticket_url'] = $native_destination['url'];
	$native_photo_cta = bvmgr_events_photo_cta_context($feed_event, array());
	$assert(($native_photo_cta['label'] ?? '') === 'Get Tickets', 'Native ticket photo cards must retain their ticket CTA.');

	fwrite(STDOUT, "public ticket eligibility: PASS\n");
} catch (Throwable $e) {
	fwrite(STDERR, 'public ticket eligibility: FAIL - ' . $e->getMessage() . "\n");
	$cleanup();
	exit(1);
}

$cleanup();
