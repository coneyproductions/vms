<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap-wordpress.php';
vms_tests_require_wordpress(__DIR__);

if (!class_exists('BVMGR_Admin_Event_Plans')) {
	require_once dirname(__DIR__) . '/vendor-management-system.php';
}

$assert = static function (bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
};

$created_posts = array();
$created_orders = array();
$original_post = $_POST ?? array();
$original_get = $_GET ?? array();
$original_request = $_REQUEST ?? array();
$original_wp_query = $GLOBALS['wp_query'] ?? null;
$original_wp_the_query = $GLOBALS['wp_the_query'] ?? null;
$original_global_post = $GLOBALS['post'] ?? null;
$sponsorship_mode_option = 'vms_sponsorships_event_page_placement_mode';
$missing_option_marker = '__vms_external_ticketing_missing_option__';
$original_sponsorship_mode = get_option($sponsorship_mode_option, $missing_option_marker);
$had_sponsor_render_state = array_key_exists('vms_sponsorships_event_page_banner_rendered', $GLOBALS);
$original_sponsor_render_state = $GLOBALS['vms_sponsorships_event_page_banner_rendered'] ?? null;
$had_external_render_state = array_key_exists('bvmgr_external_ticketing_panel_rendered', $GLOBALS);
$original_external_render_state = $GLOBALS['bvmgr_external_ticketing_panel_rendered'] ?? null;

$cleanup = static function () use (&$created_posts, &$created_orders, &$original_post, &$original_get, &$original_request, &$original_wp_query, &$original_wp_the_query, &$original_global_post, &$sponsorship_mode_option, &$missing_option_marker, &$original_sponsorship_mode, &$had_sponsor_render_state, &$original_sponsor_render_state, &$had_external_render_state, &$original_external_render_state): void {
	if (function_exists('WC') && WC() && isset(WC()->cart) && WC()->cart) {
		WC()->cart->empty_cart();
	}
	foreach ($created_orders as $order) {
		if (is_object($order) && method_exists($order, 'delete')) {
			$order->delete(true);
		}
	}
	foreach (array_reverse($created_posts) as $post_id) {
		wp_delete_post((int) $post_id, true);
	}
	$_POST = $original_post;
	$_GET = $original_get;
	$_REQUEST = $original_request;
	$GLOBALS['wp_query'] = $original_wp_query;
	$GLOBALS['wp_the_query'] = $original_wp_the_query;
	$GLOBALS['post'] = $original_global_post;
	if ($original_sponsorship_mode === $missing_option_marker) {
		delete_option($sponsorship_mode_option);
	} else {
		update_option($sponsorship_mode_option, $original_sponsorship_mode);
	}
	if ($had_sponsor_render_state) {
		$GLOBALS['vms_sponsorships_event_page_banner_rendered'] = $original_sponsor_render_state;
	} else {
		unset($GLOBALS['vms_sponsorships_event_page_banner_rendered']);
	}
	if ($had_external_render_state) {
		$GLOBALS['bvmgr_external_ticketing_panel_rendered'] = $original_external_render_state;
	} else {
		unset($GLOBALS['bvmgr_external_ticketing_panel_rendered']);
	}
	if (function_exists('wp_reset_postdata')) {
		wp_reset_postdata();
	}
};

try {
	wp_set_current_user(1);
	$assert(function_exists('bvmgr_event_plan_get_ticket_destination'), 'Canonical ticket destination helper is not loaded.');
	$assert(function_exists('bvmgr_ticketing_v2_validate_product_sale_context'), 'Ticket sale guard is not loaded.');
	$assert(function_exists('bvmgr_build_tec_event_args'), 'TEC event payload builder is not loaded.');
	$assert(post_type_exists('tribe_events'), 'The Events Calendar post type is unavailable.');
	$assert(class_exists('WC_Product_Simple'), 'WooCommerce is unavailable.');
	$assert(class_exists('VMS_Sponsorships_Public_Renderer'), 'The generic VMS Sponsorships event-page renderer is unavailable.');
	$assert(class_exists('Tribe__Tickets_Plus__Commerce__WooCommerce__Main'), 'The native Event Tickets WooCommerce provider is unavailable.');
	update_option($sponsorship_mode_option, VMS_Sponsorships_Public_Renderer::EVENT_PAGE_PLACEMENT_AUTOMATIC);

	$register_post = static function (int $post_id) use (&$created_posts): int {
		$created_posts[] = $post_id;
		return $post_id;
	};

	$future_start = strtotime('+30 days 7:00pm');
	$future_end = strtotime('+30 days 10:00pm');
	$event_date = wp_date('Y-m-d', $future_start);
	$external_url = add_query_arg('vms_external_ticket_proof', '1', home_url('/'));
	$presenter_url = 'https://presenter.example.test/rehearsal-room-tyler';
	$legitimate_event_website = 'https://event.example.test/official-information';
	$fixture_suffix = strtolower(wp_generate_password(8, false, false));

	$vendor_id = wp_insert_post(array(
		'post_type' => 'vms_vendor',
		'post_status' => 'publish',
		'post_title' => 'External Ticketing Test Performer',
	), true);
	$assert(!is_wp_error($vendor_id) && (int) $vendor_id > 0, 'Could not create performer fixture.');
	$vendor_id = $register_post((int) $vendor_id);

	$plan_id = wp_insert_post(array(
		'post_type' => 'vms_event_plan',
		'post_status' => 'publish',
		'post_title' => 'External Ticketing Integration Proof',
		'post_content' => 'A safe local test event for external ticket routing.',
	), true);
	$assert(!is_wp_error($plan_id) && (int) $plan_id > 0, 'Could not create Event Plan fixture.');
	$plan_id = $register_post((int) $plan_id);

	$event_id = wp_insert_post(array(
		'post_type' => 'tribe_events',
		'post_status' => 'publish',
		'post_title' => 'External Ticketing Public Event Proof ' . $fixture_suffix,
		'post_content' => 'Public event body.',
	), true);
	$assert(!is_wp_error($event_id) && (int) $event_id > 0, 'Could not create TEC event fixture.');
	$event_id = $register_post((int) $event_id);

	$status_key = function_exists('bvmgr_meta_key') ? (string) (bvmgr_meta_key('event_plan', 'status') ?: '_vms_event_plan_status') : '_vms_event_plan_status';
	$tec_key = function_exists('bvmgr_meta_key') ? (string) (bvmgr_meta_key('event_plan', 'tec_event_id') ?: '_vms_tec_event_id') : '_vms_tec_event_id';
	update_post_meta($plan_id, $status_key, 'published');
	update_post_meta($plan_id, '_vms_event_date', $event_date);
	update_post_meta($plan_id, '_vms_start_time', wp_date('H:i', $future_start));
	update_post_meta($plan_id, '_vms_end_time', wp_date('H:i', $future_end));
	update_post_meta($plan_id, '_vms_comp_structure', 'flat_fee');
	update_post_meta($plan_id, '_vms_flat_fee_amount', '100');
	update_post_meta($plan_id, '_vms_band_vendor_id', $vendor_id);
	update_post_meta($plan_id, '_vms_ticketing_enabled_override', 'on');
	update_post_meta($plan_id, $tec_key, $event_id);
	update_post_meta($plan_id, '_vms_tec_event_url', get_permalink($event_id));
	update_post_meta($event_id, '_EventStartDate', wp_date('Y-m-d H:i:s', $future_start));
	update_post_meta($event_id, '_EventEndDate', wp_date('Y-m-d H:i:s', $future_end));

	$product = new WC_Product_Simple();
	$product->set_name('Preserved Native Ticket');
	$product->set_status('publish');
	$product->set_regular_price('20.00');
	$product->set_price('20.00');
	$product->set_virtual(true);
	$product->set_catalog_visibility('hidden');
	$product->set_manage_stock(true);
	$product->set_stock_quantity(25);
	$product_id = $register_post((int) $product->save());
	$assert($product_id > 0, 'Could not create native ticket product fixture.');
	$plan_marker_key = function_exists('bvmgr_ticketing_v2_product_meta_key') ? bvmgr_ticketing_v2_product_meta_key('event_plan_id') : '_vms_event_plan_id';
	$role_marker_key = function_exists('bvmgr_ticketing_v2_product_meta_key') ? bvmgr_ticketing_v2_product_meta_key('product_role') : '_vms_product_role';
	update_post_meta($product_id, $plan_marker_key, $plan_id);
	update_post_meta($product_id, $role_marker_key, 'ga_ticket');
	update_post_meta($product_id, '_tribe_wooticket_for_event', $event_id);
	update_post_meta($event_id, '_tribe_default_ticket_provider', 'Tribe__Tickets_Plus__Commerce__WooCommerce__Main');
	update_post_meta($product_id, '_vms_ticket_key', 'ga');
	update_post_meta($plan_id, '_vms_wc_product_map', array('ga' => $product_id));
	update_post_meta($plan_id, '_vms_ticket_product_ids_v1', array($product_id));
	$native_config = array('mode' => 'read_only', 'tickets' => array(array('enabled' => true, 'ticket_key' => 'ga', 'title' => 'General Admission', 'price' => '20', 'visibility_mode' => 'public')));
	update_post_meta($plan_id, '_vms_ticketing_config_v2', $native_config);
	$make_native_ticket_available = static function () use ($product_id): void {
		delete_post_meta($product_id, '_ticket_start_date');
		delete_post_meta($product_id, '_ticket_end_date');
		$product = wc_get_product($product_id);
		if ($product instanceof WC_Product) {
			$product->set_date_on_sale_from(null);
			$product->set_date_on_sale_to(null);
			$product->save();
		}
	};

	$set_event_query = static function (int $event_id): void {
		$query = new WP_Query();
		$query->is_singular = true;
		$query->is_single = true;
		$query->in_the_loop = true;
		$query->queried_object = get_post($event_id);
		$query->queried_object_id = $event_id;
		$GLOBALS['wp_query'] = $query;
		$GLOBALS['wp_the_query'] = $query;
		$GLOBALS['post'] = get_post($event_id);
	};
	$set_event_query($event_id);

	$commerce_hook = bvmgr_event_details_commerce_hook();
	$assert($commerce_hook === bvmgr_event_details_external_ticketing_commerce_hook(), 'External ticketing no longer uses the canonical BVM commerce location.');
	$assert(bvmgr_event_details_before_commerce_priority() < bvmgr_event_details_commerce_priority(), 'The canonical sponsor priority must precede commerce.');
	$native_provider = Tribe__Tickets_Plus__Commerce__WooCommerce__Main::get_instance();
	if (has_action($commerce_hook, array($native_provider, 'maybe_add_front_end_tickets_form')) === false) {
		$native_provider->hook();
	}
	$assert(has_action($commerce_hook, array($native_provider, 'maybe_add_front_end_tickets_form')) === bvmgr_event_details_commerce_priority(), 'Native Event Tickets commerce is not mounted at the canonical commerce priority.');
	$assert(has_action($commerce_hook, 'bvmgr_event_details_render_external_ticketing_at_commerce_location') === bvmgr_event_details_commerce_priority(), 'External ticketing is not mounted at the canonical commerce priority.');

	$find_callback_priority = static function (string $hook, string $class_name, string $method_name): ?int {
		global $wp_filter;
		foreach (($wp_filter[$hook]->callbacks ?? array()) as $priority => $callbacks) {
			foreach ($callbacks as $callback) {
				$callable = $callback['function'] ?? null;
				if (is_array($callable) && is_object($callable[0] ?? null) && get_class($callable[0]) === $class_name && ($callable[1] ?? '') === $method_name) {
					return (int) $priority;
				}
			}
		}
		return null;
	};
	$assert(
		$find_callback_priority($commerce_hook, 'VMS_Sponsorships_Shortcodes', 'render_automatic_event_page_banner') === bvmgr_event_details_before_commerce_priority(),
		'The automatic sponsor placement is not mounted immediately before canonical commerce.'
	);

	$render_commerce_state = static function () use ($commerce_hook, $event_id, $set_event_query, $native_provider): string {
		$set_event_query($event_id);
		$native_provider->clear_ticket_cache_for_post($event_id);
		$GLOBALS['vms_sponsorships_event_page_banner_rendered'] = array();
		$GLOBALS['bvmgr_external_ticketing_panel_rendered'] = array();
		ob_start();
		do_action($commerce_hook);
		return (string) ob_get_clean();
	};
	$assert_native_order = static function (string $markup, string $state) use ($assert, $product_id, $event_id, $native_provider): void {
		$sponsor_position = strpos($markup, 'data-vms-sponsor-banner=');
		$commerce_position = strpos($markup, 'id="tribe-tickets__tickets-form"');
		$native_form_count = substr_count($markup, 'id="tribe-tickets__tickets-form"');
		$assert(substr_count($markup, 'data-vms-sponsor-banner=') === 1, $state . ': sponsor placement did not render exactly once.');
		$assert($native_form_count === 1, $state . ': native ticket commerce did not render exactly once (count ' . $native_form_count . ', output ' . substr(wp_strip_all_tags($markup), 0, 300) . ').');
		$assert(substr_count($markup, 'data-vms-external-ticketing-panel="1"') === 0, $state . ': external commerce rendered in native mode.');
		$assert($sponsor_position !== false && $commerce_position !== false && $sponsor_position < $commerce_position, $state . ': sponsor does not precede native commerce in semantic output.');
		$assert(strpos($markup, 'tribe-tickets__tickets-item') !== false, $state . ': native ticket selector omitted its ticket control.');
		$ticket = $native_provider->get_ticket($event_id, $product_id);
		$ticket_debug = $ticket ? array('price' => $ticket->price ?? null, 'start_date' => $ticket->start_date ?? null, 'end_date' => $ticket->end_date ?? null) : array();
		$assert(strpos(wp_strip_all_tags($markup), '20.00') !== false, $state . ': native $20 price is missing from commerce output (' . substr(preg_replace('/\s+/', ' ', wp_strip_all_tags($markup)), 0, 1200) . '; ticket ' . wp_json_encode($ticket_debug) . ').');
	};
	$assert_external_order = static function (string $markup, string $state) use ($assert, $external_url, $presenter_url): void {
		$sponsor_position = strpos($markup, 'data-vms-sponsor-banner=');
		$commerce_position = strpos($markup, 'data-vms-external-ticketing-panel="1"');
		$assert(substr_count($markup, 'data-vms-sponsor-banner=') === 1, $state . ': sponsor placement did not render exactly once.');
		$assert(substr_count($markup, 'data-vms-external-ticketing-panel="1"') === 1, $state . ': external ticket commerce did not render exactly once.');
		$assert(substr_count($markup, 'id="tribe-tickets__tickets-form"') === 0, $state . ': native ticket selector rendered in external mode.');
		$assert($sponsor_position !== false && $commerce_position !== false && $sponsor_position < $commerce_position, $state . ': sponsor does not precede external commerce in semantic output.');
		$assert(strpos($markup, $external_url) !== false && strpos($markup, 'Buy Tickets') !== false, $state . ': external CTA is incomplete.');
		$assert(strpos($markup, 'Hosted at Serenade Range') !== false && strpos($markup, 'Presented by') !== false, $state . ': hosted presentation is incomplete.');
		$assert(strpos($markup, 'href="' . esc_url($presenter_url) . '"') !== false, $state . ': presenter link is missing.');
		$assert(strpos(wp_strip_all_tags($markup), '20.00') === false, $state . ': stale native $20 price leaked into external output.');
	};

	// Legacy/native compatibility before any new metadata exists.
	$assert(bvmgr_event_plan_get_ticketing_sales_mode($plan_id) === 'serenade_range', 'Missing mode metadata must resolve to native Serenade Range ticketing.');
	$assert(bvmgr_event_plan_is_ticketing_enabled($plan_id), 'A native plan with ticketing enabled should remain enabled.');
	$native_destination = bvmgr_event_plan_get_ticket_destination($plan_id, (string) get_permalink($event_id));
	$assert(empty($native_destination['is_external']) && $native_destination['url'] === get_permalink($event_id), 'Native destination behavior changed unexpectedly.');
	$native_sale = bvmgr_ticketing_v2_validate_product_sale_context($product_id, $plan_id, $event_id, 'ga_ticket');
	$assert(!empty($native_sale['ok']), 'Existing native ticket sale context should remain valid.');
	$native_args = bvmgr_build_tec_event_args($plan_id, $event_id);
	$assert(strpos((string) ($native_args['EventCost'] ?? ''), '$20') !== false, 'Native TEC payload did not expose the expected $20 price.');
	$assert(bvmgr_publish_event_to_calendar($plan_id, get_post($plan_id)), 'Initial native Event Plan sync failed.');
	$make_native_ticket_available();
	$assert(strpos((string) tribe_get_cost($event_id, true), '20') !== false, 'Initial native public cost did not expose $20.');
	$assert_native_order($render_commerce_state(), 'Initial native state');

	if (function_exists('wc_load_cart') && (!function_exists('WC') || !WC() || !isset(WC()->cart) || !WC()->cart)) {
		wc_load_cart();
	}
	$cart_item_key = '';
	if (function_exists('WC') && WC() && isset(WC()->cart) && WC()->cart) {
		WC()->cart->empty_cart();
		$cart_item_key = (string) WC()->cart->add_to_cart($product_id, 1);
		$assert($cart_item_key !== '', 'Native ticket add-to-cart should still work before switching modes.');
	}

	$historical_order_item_count = null;
	$historical_order_total = null;
	$order = function_exists('wc_create_order') ? wc_create_order() : null;
	if ($order instanceof WC_Order) {
		$order->add_product(wc_get_product($product_id), 1);
		$order->calculate_totals();
		$order->save();
		$created_orders[] = $order;
		$historical_order_item_count = count($order->get_items());
		$historical_order_total = (string) $order->get_total();
	}

	// Persist external mode through the real nonce/capability-protected Event Plan save path.
	$_POST = array(
		'bvmgr_event_plan_details_nonce' => wp_create_nonce('bvmgr_save_event_plan_details'),
		'post_ID' => $plan_id,
		'original_post_status' => 'publish',
		'vms_event_plan_action' => 'save_draft',
		'vms_staffing_lazy_unloaded' => '1',
		'vms_secondary_vendors_lazy_unloaded' => '1',
		'vms_ticketing_sales_mode' => 'external',
		'vms_external_ticket_url' => '',
		'vms_external_ticket_provider' => 'Test Tickets',
		'vms_event_relationship' => 'hosted_third_party',
		'vms_external_event_producer' => 'Rehearsal Room Tyler',
		'vms_external_event_producer_website' => 'javascript:alert(1)',
	);
	$_GET = array();
	$_REQUEST = $_POST;
	$reflection = new ReflectionClass('BVMGR_Admin_Event_Plans');
	$admin = $reflection->newInstanceWithoutConstructor();
	$admin->save_event_plan_meta($plan_id, get_post($plan_id));
	clean_post_cache($plan_id);

	$assert(bvmgr_event_plan_is_externally_ticketed($plan_id), 'External sales mode did not persist.');
	$assert(bvmgr_event_plan_get_external_ticket_provider($plan_id) === 'Test Tickets', 'Provider label did not persist.');
	$assert(bvmgr_event_plan_get_relationship($plan_id) === 'hosted_third_party', 'Hosted relationship did not persist.');
	$assert(bvmgr_event_plan_get_external_event_producer($plan_id) === 'Rehearsal Room Tyler', 'Producer label did not persist.');
	$assert(bvmgr_event_plan_get_external_event_producer_website($plan_id) === '', 'Invalid presenter website was not rejected by the Event Plan save path.');
	$assert(bvmgr_event_plan_get_external_ticket_url($plan_id) === '', 'Incomplete external draft should retain an empty destination.');
	$assert(get_post_meta($plan_id, '_vms_wc_product_map', true) === array('ga' => $product_id), 'Switching external mutated the native product map.');
	$assert(get_post_meta($plan_id, '_vms_ticket_product_ids_v1', true) === array($product_id), 'Switching external mutated native ticket records.');
	$assert(get_post_meta($plan_id, '_vms_ticketing_config_v2', true) === $native_config, 'Switching external mutated native ticket definitions.');
	if ($order instanceof WC_Order) {
		$reloaded_order = wc_get_order($order->get_id());
		$assert($reloaded_order instanceof WC_Order && count($reloaded_order->get_items()) === $historical_order_item_count && (string) $reloaded_order->get_total() === $historical_order_total, 'Switching modes altered a historical order.');
	}

	$assert(bvmgr_event_plan_sanitize_external_ticket_url('javascript:alert(1)') === '', 'Unsafe external URL scheme was accepted.');
	$assert(bvmgr_event_plan_sanitize_external_ticket_url('/relative/tickets') === '', 'Relative external URL was accepted.');
	$assert(bvmgr_event_plan_sanitize_external_event_producer_website('javascript:alert(1)') === '', 'Unsafe presenter URL scheme was accepted.');
	$readiness_errors = bvmgr_validate_event_plan($plan_id);
	$assert((bool) array_filter($readiness_errors, static fn($message): bool => stripos((string) $message, 'External Ticketing requires') !== false), 'Ready/publish validation did not reject a missing external URL.');

	update_post_meta($plan_id, '_vms_external_ticket_url', $external_url);
	update_post_meta($plan_id, $status_key, 'published');
	$readiness_errors = bvmgr_validate_event_plan($plan_id);
	$assert(!(bool) array_filter($readiness_errors, static fn($message): bool => stripos((string) $message, 'External Ticketing requires') !== false), 'A valid external URL did not satisfy the readiness destination requirement.');
	$assert(!(bool) array_filter($readiness_errors, static fn($message): bool => stripos((string) $message, 'presenter') !== false && stripos((string) $message, 'website') !== false), 'Optional presenter website incorrectly became a readiness requirement.');
	update_post_meta($plan_id, '_vms_external_event_producer_website', $presenter_url);
	$assert(bvmgr_event_plan_get_external_event_producer_website($plan_id) === $presenter_url, 'Valid presenter website did not persist.');

	$destination = bvmgr_event_plan_get_ticket_destination($plan_id, (string) get_permalink($event_id));
	$assert(!empty($destination['is_external']) && $destination['url'] === $external_url, 'Canonical destination did not resolve externally.');
	$assert(!bvmgr_event_plan_native_ticket_purchasing_allowed($plan_id), 'External mode still allows native ticket purchasing.');

	// Simulate the earlier implementation having written the checkout URL into TEC Website.
	update_post_meta($event_id, '_EventURL', $external_url);
	delete_post_meta($event_id, bvmgr_tec_managed_event_url_meta_key());
	$tec_args = bvmgr_build_tec_event_args($plan_id, $event_id);
	$assert(array_key_exists('EventURL', $tec_args) && $tec_args['EventURL'] === '', 'Stale VMS-written checkout URL was not scheduled for ownership-aware clearing.');
	$assert(array_key_exists('EventCost', $tec_args) && $tec_args['EventCost'] === array(), 'External TEC sync did not explicitly clear the native cost.');
	$assert(bvmgr_publish_event_to_calendar($plan_id, get_post($plan_id)), 'External Event Plan did not update through the normal public-event publishing path.');
	$assert(absint(get_post_meta($plan_id, $tec_key, true)) === $event_id && get_post_status($event_id) === 'publish', 'Normal public-event publishing replaced or unpublished the linked event.');
	$assert(!metadata_exists('post', $event_id, '_EventURL'), 'Stale external checkout URL remained in TEC Event Website after resync.');
	$assert(!metadata_exists('post', $event_id, '_EventCost') && tribe_get_cost($event_id, true) === '', 'First external switch retained a public native price.');
	$assert_external_order($render_commerce_state(), 'First external state');

	// Independently entered TEC website data must not be treated as a VMS checkout URL.
	update_post_meta($event_id, '_EventURL', $legitimate_event_website);
	$legitimate_website_args = bvmgr_build_tec_event_args($plan_id, $event_id);
	$assert(!array_key_exists('EventURL', $legitimate_website_args), 'External sync attempted to overwrite an independent TEC event website.');
	$assert(bvmgr_publish_event_to_calendar($plan_id, get_post($plan_id)), 'External resync with an independent event website failed.');
	$assert(get_post_meta($event_id, '_EventURL', true) === $legitimate_event_website, 'Independent TEC event website was not preserved.');
	delete_post_meta($event_id, '_EventURL');

	if (function_exists('bvmgr_calendar_feed_cache_bust')) bvmgr_calendar_feed_cache_bust();
	$calendar_events = bvmgr_get_calendar_events(array(
		'context' => 'public',
		'start_date' => wp_date('Y-m-d', strtotime('+29 days')),
		'end_date' => wp_date('Y-m-d', strtotime('+31 days')),
		'include_past' => false,
		'include_open_close_shading' => false,
	));
	$calendar_row = null;
	foreach ($calendar_events as $row) {
		if (absint($row['event_plan_id'] ?? 0) === $plan_id) { $calendar_row = $row; break; }
	}
	$assert(is_array($calendar_row), 'External Event Plan disappeared from the normal public calendar feed.');
	$assert(($calendar_row['public_url'] ?? '') === get_permalink($event_id), 'Calendar title/details destination should remain the Serenade Range event page.');
	$assert(($calendar_row['ticket_url'] ?? '') === $external_url && !empty($calendar_row['ticket_is_external']), 'Calendar ticket CTA did not resolve externally.');
	$list_markup = bvmgr_public_calendar_render_list_view(array($calendar_row), false, true, false);
	$assert(strpos($list_markup, $external_url) !== false && strpos($list_markup, 'Buy Tickets') !== false, 'Public calendar/list markup did not use the external CTA.');
	$assert(strpos($list_markup, (string) get_permalink($event_id)) !== false, 'Public calendar title/media links no longer point to the event page.');
	$photo_cta = bvmgr_events_photo_cta_context($calendar_row, array('is_cancelled' => false, 'is_rescheduled' => false));
	$assert(($photo_cta['url'] ?? '') === $external_url && ($photo_cta['label'] ?? '') === 'Buy Tickets' && !empty($photo_cta['is_external']), 'Photo/archive CTA did not use the external destination.');

	$event_context = bvmgr_event_details_context($event_id);
	$assert(!empty($event_context['ticket_is_external']) && ($event_context['tickets_url'] ?? '') === $external_url, 'Event details context did not resolve the external destination.');
	$assert(($event_context['event_relationship'] ?? '') === 'hosted_third_party', 'Hosted relationship was not exposed to public presentation.');
	$external_schema = bvmgr_event_details_clean_tec_offers_schema(array('offers' => array(array('url' => 'https://native.invalid', 'price' => '25.00'))), $event_context, $event_id);
	$schema_offer = (array) (($external_schema['offers'][0] ?? array()));
	$assert(($schema_offer['url'] ?? '') === $external_url && !isset($schema_offer['price']), 'External Event Offer schema retained a native URL or price.');

	delete_post_meta($plan_id, '_vms_external_ticket_provider');
	$fallback_ticket_context = bvmgr_event_details_ticket_context($event_id, $plan_id);
	$assert(stripos((string) ($fallback_ticket_context['label'] ?? ''), 'external ticket provider') !== false, 'Provider fallback copy is blank or awkward.');
	update_post_meta($plan_id, '_vms_external_ticket_provider', 'Test Tickets');

	$set_event_query($event_id);
	$hosted_panel = bvmgr_event_details_render_external_ticketing_panel($event_id, $plan_id);
	$assert(substr_count($hosted_panel, 'data-vms-external-ticketing-panel="1"') === 1, 'External mode did not produce exactly one purchase card.');
	$assert(strpos($hosted_panel, 'Hosted at Serenade Range') !== false && strpos($hosted_panel, 'Presented by') !== false && strpos($hosted_panel, 'Rehearsal Room Tyler') !== false, 'Hosted event presentation is incomplete: ' . substr(wp_strip_all_tags($hosted_panel), 0, 300));
	$assert(strpos($hosted_panel, 'href="' . esc_url($presenter_url) . '"') !== false && strpos($hosted_panel, 'rel="noopener noreferrer external"') !== false, 'Hosted presenter name was not linked safely to its website.');
	$assert(strpos($hosted_panel, $external_url) !== false && strpos($hosted_panel, 'Buy Tickets') !== false, 'External event panel lacks its purchase CTA.');
	$description_output = apply_filters('the_content', 'Event body without commerce');
	$assert(strpos($description_output, 'data-vms-external-ticketing-panel') === false, 'External purchase card is still tied to event-description output.');
	$assert(has_action($commerce_hook, 'bvmgr_event_details_render_external_ticketing_at_commerce_location') !== false, 'External purchase card is not registered at the configured Event Tickets commerce location.');
	$GLOBALS['bvmgr_external_ticketing_panel_rendered'] = array();
	ob_start();
	do_action($commerce_hook);
	do_action($commerce_hook);
	$mounted_output = (string) ob_get_clean();
	$assert(substr_count($mounted_output, 'data-vms-external-ticketing-panel="1"') === 1, 'Commerce-location action did not mount exactly one external purchase card.');

	delete_post_meta($plan_id, '_vms_external_event_producer_website');
	$plain_presenter_panel = bvmgr_event_details_render_external_ticketing_panel($event_id, $plan_id);
	$assert(strpos($plain_presenter_panel, 'Rehearsal Room Tyler') !== false && strpos($plain_presenter_panel, $presenter_url) === false, 'Presenter without a website did not remain plain text.');
	update_post_meta($plan_id, '_vms_external_event_producer_website', $presenter_url);
	delete_post_meta($plan_id, '_vms_external_event_producer');
	$orphan_presenter_panel = bvmgr_event_details_render_external_ticketing_panel($event_id, $plan_id);
	$assert(strpos($orphan_presenter_panel, $presenter_url) === false, 'Presenter website rendered publicly without a presenter name.');
	update_post_meta($plan_id, '_vms_external_event_producer', 'Rehearsal Room Tyler');

	update_post_meta($plan_id, '_vms_event_relationship', 'serenade_range_produced');
	$produced_panel = bvmgr_event_details_render_external_ticketing_panel($event_id, $plan_id);
	$assert(strpos($produced_panel, 'Hosted at Serenade Range') === false && strpos($produced_panel, 'Rehearsal Room Tyler') === false && strpos($produced_panel, 'Buy Tickets') !== false, 'Serenade Range-produced external event incorrectly shows hosted/presenter presentation.');
	update_post_meta($plan_id, '_vms_event_relationship', 'hosted_third_party');

	$query_args = bvmgr_tec_suppress_tickets_for_cancelled_events(array('post_parent' => $event_id));
	$assert(($query_args['post__in'] ?? null) === array(0), 'Native TEC ticket selector query was not suppressed.');
	$assert(bvmgr_ticketing_v2_render_entitlements_block($event_id, $plan_id) === '', 'Native ticket/add-on widget output was not suppressed.');
	$external_sale = bvmgr_ticketing_v2_validate_product_sale_context($product_id, $plan_id, $event_id, 'ga_ticket');
	$assert(($external_sale['code'] ?? '') === 'external_ticketing' && strpos((string) ($external_sale['message'] ?? ''), $external_url) !== false, 'Direct native sale guard did not block with the external destination.');
	if (function_exists('wc_clear_notices') && function_exists('WC') && WC() && isset(WC()->cart) && WC()->cart) {
		wc_clear_notices();
		$direct_add = bvmgr_ticketing_v2_validate_add_to_cart(true, $product_id, 1, 0, array(), array());
		$assert($direct_add === false, 'Direct native add-to-cart was not blocked in external mode.');
		bvmgr_ticketing_v2_enforce_no_cancelled_event_items_in_cart();
		$error_notices = wc_get_notices('error');
		$assert(!empty($error_notices), 'Cart/checkout revalidation did not block a ticket retained across the mode switch.');
	}
	$preview = bvmgr_ticketing_v2_preview_sync($plan_id);
	$assert(($preview['message'] ?? '') === 'external_ticketing', 'External mode did not skip native sync preview.');
	$commit = bvmgr_ticketing_v2_commit_sync($plan_id, 'external-proof-preview');
	$assert(($commit['code'] ?? $commit['error'] ?? '') === 'external_ticketing' || strpos(wp_json_encode($commit), 'external_ticketing') !== false, 'External mode did not skip native ticket commit.');
	$link_result = bvmgr_ticketing_v2_ensure_tec_event_link($plan_id);
	$assert(($link_result['code'] ?? '') === 'external_ticketing', 'Ticket synchronization still attempted TEC-link mutation in external mode.');

	// Switching back restores the existing native path without rebuilding records.
	update_post_meta($plan_id, $status_key, 'published');
	global $wpdb;
	$wpdb->update($wpdb->posts, array('post_status' => 'publish'), array('ID' => $plan_id), array('%s'), array('%d'));
	clean_post_cache($plan_id);
	update_post_meta($plan_id, '_vms_ticketing_sales_mode', 'serenade_range');
	$assert(bvmgr_event_plan_is_ticketing_enabled($plan_id), 'Switching back did not restore native ticketing: mode=' . get_post_meta($plan_id, '_vms_ticketing_sales_mode', true) . ', override=' . get_post_meta($plan_id, '_vms_ticketing_enabled_override', true));
	$assert(get_post_meta($plan_id, '_vms_wc_product_map', true) === array('ga' => $product_id), 'Switching back lost the preserved native product map.');
	$restored_sale = bvmgr_ticketing_v2_validate_product_sale_context($product_id, $plan_id, $event_id, 'ga_ticket');
	$assert(!empty($restored_sale['ok']), 'Switching back did not restore native sale validation: ' . wp_json_encode($restored_sale));
	$restored_query = bvmgr_tec_suppress_tickets_for_cancelled_events(array('post_parent' => $event_id));
	$assert(($restored_query['post__in'] ?? null) !== array(0), 'Switching back still suppresses native ticket queries.');
	$assert(bvmgr_tec_suppress_ticket_forms_for_cancelled_event($commerce_hook, null) === $commerce_hook, 'Switching back did not restore the configured native ticket-form hook.');
	$assert(bvmgr_event_details_render_external_ticketing_panel($event_id, $plan_id) === '', 'Native mode still rendered the external purchase card.');
	$native_again_args = bvmgr_build_tec_event_args($plan_id, $event_id);
	$assert(strpos((string) ($native_again_args['EventCost'] ?? ''), '$20') !== false, 'Native price did not return in the second native TEC payload.');
	$assert(bvmgr_publish_event_to_calendar($plan_id, get_post($plan_id)), 'Second native Event Plan sync failed.');
	$make_native_ticket_available();
	$assert(strpos((string) tribe_get_cost($event_id, true), '20') !== false, 'Switching back to native did not restore the $20 public cost.');
	$assert_native_order($render_commerce_state(), 'Restored native state');
	if (function_exists('WC') && WC() && isset(WC()->cart) && WC()->cart) {
		WC()->cart->empty_cart();
		$restored_cart_item_key = (string) WC()->cart->add_to_cart($product_id, 1);
		$assert($restored_cart_item_key !== '', 'Switching back to native did not restore the native add-to-cart path.');
		WC()->cart->empty_cart();
	}

	// Complete the required native -> external -> native -> external cycle.
	update_post_meta($plan_id, '_vms_ticketing_sales_mode', 'external');
	$external_again_args = bvmgr_build_tec_event_args($plan_id, $event_id);
	$assert(array_key_exists('EventCost', $external_again_args) && $external_again_args['EventCost'] === array(), 'Second external switch did not request a TEC cost clear.');
	$assert(bvmgr_publish_event_to_calendar($plan_id, get_post($plan_id)), 'Second external Event Plan sync failed.');
	$assert(!metadata_exists('post', $event_id, '_EventCost') && tribe_get_cost($event_id, true) === '', 'Second external switch leaked the restored $20 native price.');
	$assert(get_post_meta($event_id, '_EventURL', true) !== $external_url, 'Second external switch exposed the checkout URL as TEC Event Website.');
	$external_again_query = bvmgr_tec_suppress_tickets_for_cancelled_events(array('post_parent' => $event_id));
	$assert(($external_again_query['post__in'] ?? null) === array(0), 'Second external switch restored the native selector query.');
	$final_panel = bvmgr_event_details_render_external_ticketing_panel($event_id, $plan_id);
	$assert(substr_count($final_panel, 'data-vms-external-ticketing-panel="1"') === 1 && strpos($final_panel, $external_url) !== false && strpos($final_panel, $presenter_url) !== false, 'Final external card/CTA/presenter link is incomplete.');
	$assert_external_order($render_commerce_state(), 'Final external state');
	$final_context = bvmgr_event_details_context($event_id);
	$final_schema = bvmgr_event_details_clean_tec_offers_schema(array('offers' => array(array('url' => get_permalink($product_id), 'price' => '20.00'))), $final_context, $event_id);
	$final_offer = (array) ($final_schema['offers'][0] ?? array());
	$assert(($final_offer['url'] ?? '') === $external_url && !isset($final_offer['price']) && strpos(wp_json_encode($final_schema), '20.00') === false, 'Final external schema retained stale native price data.');
	$checkout_response = wp_remote_get($external_url, array('timeout' => 15, 'sslverify' => false));
	$assert(!is_wp_error($checkout_response) && (int) wp_remote_retrieve_response_code($checkout_response) === 200, 'Final Buy Tickets destination did not resolve in Local.');
	$preserved_product = wc_get_product($product_id);
	$assert($preserved_product instanceof WC_Product && (float) $preserved_product->get_price() === 20.0, 'Mode switching changed the preserved native product price.');
	if ($order instanceof WC_Order) {
		$reloaded_order = wc_get_order($order->get_id());
		$assert($reloaded_order instanceof WC_Order && count($reloaded_order->get_items()) === $historical_order_item_count && (string) $reloaded_order->get_total() === $historical_order_total, 'Full mode-switch cycle altered historical order data.');
	}

	if (getenv('VMS_TEST_KEEP_FIXTURES') === '1') {
		$keep_mode = sanitize_key((string) getenv('VMS_TEST_KEEP_FIXTURES_MODE'));
		if ($keep_mode === 'native') {
			update_post_meta($plan_id, '_vms_ticketing_sales_mode', 'serenade_range');
			$assert(bvmgr_publish_event_to_calendar($plan_id, get_post($plan_id)), 'Could not leave the retained HTTP fixture in native mode.');
			$make_native_ticket_available();
			$assert_native_order($render_commerce_state(), 'Retained native HTTP fixture');
		}
		$order_ids = array_values(array_filter(array_map(static function ($created_order): int {
			return is_object($created_order) && method_exists($created_order, 'get_id') ? (int) $created_order->get_id() : 0;
		}, $created_orders)));
		fwrite(STDOUT, 'external-ticketing-live-fixture: ' . wp_json_encode(array(
			'post_ids' => array_values(array_map('intval', $created_posts)),
			'order_ids' => $order_ids,
			'event_id' => $event_id,
			'plan_id' => $plan_id,
			'product_id' => $product_id,
			'event_url' => get_permalink($event_id),
			'external_url' => $external_url,
			'presenter_url' => $presenter_url,
			'commerce_hook' => $commerce_hook,
			'fixture_mode' => $keep_mode === 'native' ? 'native' : 'external',
			'original_sponsorship_mode' => $original_sponsorship_mode,
		)) . "\n");
	} else {
		$cleanup();
	}
	fwrite(STDOUT, "external-ticketing-event-plan: OK\n");
	exit(0);
} catch (Throwable $e) {
	$cleanup();
	fwrite(STDERR, "external-ticketing-event-plan: FAIL\n" . $e->getMessage() . "\n");
	exit(1);
}
