<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap-wordpress.php';
vms_tests_require_wordpress(__DIR__);

$plugin_root_env = getenv('BVMGR_TEST_PLUGIN_ROOT');
$plugin_root = is_string($plugin_root_env) && $plugin_root_env !== '' ? realpath($plugin_root_env) : dirname(__DIR__);
if (!is_string($plugin_root) || !is_dir($plugin_root)) {
	throw new RuntimeException('BVMGR_TEST_PLUGIN_ROOT must identify the exact plugin package under test.');
}
if (!function_exists('bvmgr_ticketing_v2_get_config')) {
	require_once $plugin_root . '/vendor-management-system.php';
}
require_once $plugin_root . '/includes/core/event-reschedule.php';
require_once $plugin_root . '/includes/core/event-communications.php';
require_once $plugin_root . '/includes/admin/event-communications.php';
require_once $plugin_root . '/includes/admin/event-reschedule.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
	$assertions++;
	if (!$condition) {
		throw new RuntimeException('Assertion ' . $assertions . ' failed: ' . $message);
	}
};

$invoke_communication_action = static function (array $request): void {
	$saved_post = $_POST;
	$saved_request = $_REQUEST;
	$redirect_trapped = false;
	$trap_redirect = static function (string $location, int $status): string {
		unset($location, $status);
		throw new RuntimeException('bvmgr_test_redirect');
	};
	$_POST = $request;
	$_REQUEST = array_merge($saved_request, $request);
	add_filter('wp_redirect', $trap_redirect, PHP_INT_MAX, 2);
	try {
		bvmgr_event_communication_admin_handle_action();
	} catch (RuntimeException $exception) {
		if ($exception->getMessage() !== 'bvmgr_test_redirect') {
			throw $exception;
		}
		$redirect_trapped = true;
	} finally {
		remove_filter('wp_redirect', $trap_redirect, PHP_INT_MAX);
		$_POST = $saved_post;
		$_REQUEST = $saved_request;
	}
	if (!$redirect_trapped) {
		throw new RuntimeException('Communication action did not reach its protected redirect boundary.');
	}
};

$created_posts = array();
$created_orders = array();
$register_post = static function (int $post_id) use (&$created_posts): int {
	$created_posts[] = $post_id;
	return $post_id;
};
$register_order = static function ($order) use (&$created_orders) {
	if (is_object($order) && method_exists($order, 'get_id')) {
		$created_orders[] = (int) $order->get_id();
	}
	return $order;
};

$create_event = static function (string $title, string $date) use ($register_post): int {
	$event_id = wp_insert_post(array('post_type' => 'tribe_events', 'post_status' => 'publish', 'post_title' => $title), true);
	if (is_wp_error($event_id) || (int) $event_id <= 0) {
		throw new RuntimeException('Could not create calendar event fixture.');
	}
	$event_id = $register_post((int) $event_id);
	update_post_meta($event_id, '_EventStartDate', $date . ' 19:00:00');
	update_post_meta($event_id, '_EventEndDate', $date . ' 21:00:00');
	update_post_meta($event_id, '_EventStartDateUTC', get_gmt_from_date($date . ' 19:00:00'));
	update_post_meta($event_id, '_EventEndDateUTC', get_gmt_from_date($date . ' 21:00:00'));
	update_post_meta($event_id, '_EventTimezone', wp_timezone_string());
	return $event_id;
};

$create_plan = static function (string $title, int $event_id, string $date) use ($register_post): int {
	$plan_id = wp_insert_post(array('post_type' => 'vms_event_plan', 'post_status' => 'publish', 'post_title' => $title), true);
	if (is_wp_error($plan_id) || (int) $plan_id <= 0) {
		throw new RuntimeException('Could not create Event Plan fixture.');
	}
	$plan_id = $register_post((int) $plan_id);
	update_post_meta($plan_id, '_vms_event_date', $date);
	update_post_meta($plan_id, '_vms_start_time', '19:00');
	update_post_meta($plan_id, '_vms_end_time', '21:00');
	update_post_meta($plan_id, '_vms_event_plan_start_datetime', $date . ' 19:00:00');
	update_post_meta($plan_id, '_vms_event_plan_end_datetime', $date . ' 21:00:00');
	update_post_meta($plan_id, '_vms_tec_event_id', $event_id);
	update_post_meta($plan_id, '_vms_event_plan_status', 'published');
	return $plan_id;
};

$create_product = static function (string $label, int $plan_id, int $event_id, string $role, string $old_date) use ($register_post): int {
	$product = new WC_Product_Simple();
	$product->set_name($old_date . ' 19:00 - ' . $label);
	$product->set_status('publish');
	$product->set_regular_price($role === 'entitlement' ? '25' : '15');
	$product->set_price($role === 'entitlement' ? '25' : '15');
	$product->set_virtual(true);
	$product->set_catalog_visibility('hidden');
	$product_id = $register_post((int) $product->save());
	update_post_meta($product_id, '_vms_event_plan_id', $plan_id);
	update_post_meta($product_id, '_vms_tec_event_id', $event_id);
	update_post_meta($product_id, '_vms_product_role', $role);
	return $product_id;
};

$create_order = static function (string $first, string $last, string $email) use ($register_order) {
	$order = $register_order(wc_create_order());
	$order->set_status('completed');
	$order->set_billing_first_name($first);
	$order->set_billing_last_name($last);
	$order->set_billing_email($email);
	return $order;
};

$add_item = static function ($order, int $product_id, int $quantity, string $snapshot_date): WC_Order_Item_Product {
	$product = wc_get_product($product_id);
	$item = new WC_Order_Item_Product();
	$item->set_product($product);
	$item->set_name((string) $product->get_name());
	$item->set_quantity($quantity);
	$item->set_subtotal((string) ((float) $product->get_price() * $quantity));
	$item->set_total((string) ((float) $product->get_price() * $quantity));
	$item->add_meta_data('_vms_event_plan_id', (string) get_post_meta($product_id, '_vms_event_plan_id', true), true);
	$item->add_meta_data('_vms_tec_event_post_id', (string) get_post_meta($product_id, '_vms_tec_event_id', true), true);
	$item->add_meta_data('_vms_event_date_snapshot', $snapshot_date, true);
	$item->add_meta_data('_vms_event_when_snapshot', 'Sat, Sep 19, 2026 7:00pm', true);
	$item->add_meta_data('_vms_event_title_snapshot', 'Historical communication fixture', true);
	$order->add_item($item);
	return $item;
};

$save_order = static function ($order): void {
	$order->calculate_totals(false);
	$order->save();
};

$cleanup = static function () use (&$created_orders, &$created_posts): void {
	remove_all_filters('bvmgr_event_communication_mail_transport');
	foreach (array_reverse($created_orders) as $order_id) {
		$order = function_exists('wc_get_order') ? wc_get_order((int) $order_id) : null;
		if ($order && method_exists($order, 'delete')) {
			$order->delete(true);
		} else {
			wp_delete_post((int) $order_id, true);
		}
	}
	foreach (array_reverse($created_posts) as $post_id) {
		wp_delete_post((int) $post_id, true);
	}
};

try {
	wp_set_current_user(1);
	$assert(class_exists('WC_Product_Simple') && function_exists('wc_create_order'), 'WooCommerce fixtures are unavailable.');

	$old_date = '2026-09-19';
	$new_date = '2026-09-12';
	$event_id = $create_event('Customer communications calendar fixture', $new_date);
	$plan_id = $create_plan('Customer communications Event Plan', $event_id, $new_date);
	$ticket_id = $create_product('General Admission', $plan_id, $event_id, 'ga_ticket', $old_date);
	$reservation_id = $create_product('Fire Table #01', $plan_id, $event_id, 'entitlement', $old_date);

	$order_a = $create_order('Purchaser', 'A', 'purchaser-a@example.test');
	$item_a = $add_item($order_a, $ticket_id, 2, $old_date);
	$save_order($order_a);

	$order_b1 = $create_order('Purchaser', 'B', 'purchaser-b@example.test');
	$item_b1 = $add_item($order_b1, $ticket_id, 1, $old_date);
	$save_order($order_b1);
	$order_b2 = $create_order('Purchaser', 'B', 'purchaser-b@example.test');
	$item_b2 = $add_item($order_b2, $reservation_id, 1, $old_date);
	$save_order($order_b2);

	$order_c = $create_order('Purchaser', 'C', 'purchaser-c@example.test');
	$item_c = $add_item($order_c, $reservation_id, 1, $old_date);
	$save_order($order_c);

	$order_d = $create_order('Purchaser', 'D', '');
	$item_d = $add_item($order_d, $ticket_id, 1, $old_date);
	$save_order($order_d);

	$automatic_mail = array();
	$no_automatic_mail = static function ($pre, array $message) use (&$automatic_mail): array {
		$automatic_mail[] = $message;
		return array('success' => true, 'provider' => 'fixture');
	};
	add_filter('bvmgr_event_communication_mail_transport', $no_automatic_mail, 10, 2);
	$preview = bvmgr_event_occurrence_preview($plan_id, $old_date . ' 19:00', $new_date . ' 19:00', 'date_correction');
	$assert(!empty($preview['allowed']) && (int) $preview['counts']['customers'] === 4, 'Future reschedule preview did not deduplicate the four affected customers.');
	$assert(count((array) $preview['notification_rows']) === 4, 'Future preview recipient table did not include all affected customers.');
	$block_ledger_write = static function ($check, int $object_id, string $meta_key) use ($plan_id) {
		return $object_id === $plan_id && strpos($meta_key, '_vms_event_communication_v1_') === 0 ? false : $check;
	};
	add_filter('add_post_metadata', $block_ledger_write, 10, 3);
	$blocked_apply = bvmgr_event_occurrence_apply($plan_id, $old_date . ' 19:00', $new_date . ' 19:00', 'date_correction', 1, bvmgr_event_occurrence_preview_fingerprint($preview));
	remove_filter('add_post_metadata', $block_ledger_write, 10);
	$assert(empty($blocked_apply['ok']) && !empty($blocked_apply['rolled_back']) && strpos((string) ($blocked_apply['message'] ?? ''), 'communication audience persistence failed') !== false, 'Mandatory audience persistence failure did not fail and roll back the occurrence operation.');
	$assert((string) wc_get_order_item_meta((int) $item_a->get_id(), '_vms_effective_event_start_local', true) === '' && bvmgr_event_occurrence_history($plan_id) === array(), 'Mandatory audience persistence failure left occurrence or history state behind.');
	$assert(bvmgr_event_communication_get_ledger($plan_id, (string) ($blocked_apply['operation_id'] ?? '')) === array() && empty($automatic_mail), 'Failed audience persistence left a ledger or sent email.');
	$preview = bvmgr_event_occurrence_preview($plan_id, $old_date . ' 19:00', $new_date . ' 19:00', 'date_correction');
	$applied = bvmgr_event_occurrence_apply($plan_id, $old_date . ' 19:00', $new_date . ' 19:00', 'date_correction', 1, bvmgr_event_occurrence_preview_fingerprint($preview));
	remove_filter('bvmgr_event_communication_mail_transport', $no_automatic_mail, 10);
	$assert(!empty($applied['ok']) && empty($automatic_mail), 'Occurrence APPLY sent customer email automatically or failed: ' . (string) ($applied['message'] ?? ''));
	$assert(strpos((string) ($applied['message'] ?? ''), '4 affected customers need written notice') !== false, 'Completion state did not expose unresolved written notice.');

	$operation_id = (string) ($applied['operation_id'] ?? '');
	$ledger = bvmgr_event_communication_get_ledger($plan_id, $operation_id);
	$summary = bvmgr_event_communication_summary($ledger);
	$assert(!empty($ledger) && bvmgr_event_communication_verify_audience($ledger), 'Automatic audience ledger was not durably created or failed its immutable fingerprint.');
	$assert((int) $summary['recipient_count'] === 4 && (int) $summary['order_count'] === 5 && (int) $summary['pending'] === 4, 'Automatic audience summary changed.');
	$assert(count((array) ($ledger['audience_order_item_ids'] ?? array())) === 5, 'Automatic audience lost an affected order item.');

	$ledger_before_panel_render = $ledger;
	$history_before_panel_render = bvmgr_event_occurrence_history($plan_id);
	ob_start();
	bvmgr_event_communication_render_admin_section($plan_id, $history_before_panel_render, $operation_id);
	$pending_panel_markup = (string) ob_get_clean();
	ob_start();
	do_action('admin_footer-post.php');
	$pending_footer_markup = (string) ob_get_clean();
	$assert(strpos($pending_panel_markup, '<details id="bvmgr-event-communications" class="vms-ep-card vms-mt-12" open>') !== false && strpos($pending_panel_markup, '4 recipients need review') !== false, 'A newly actionable pending communication operation did not default expanded with an attention summary.');
	$assert(strpos($pending_panel_markup, '<form') === false && strpos($pending_panel_markup, '</form>') === false, 'Customer communications still emitted a form inside the WordPress Event Plan post form.');
	$required_controls = array();
	preg_match_all('/<(?:input|textarea)\b[^>]*\brequired\b[^>]*>/i', $pending_panel_markup, $required_controls);
	$assert(!empty($required_controls[0]), 'Customer communication action controls unexpectedly lost action-specific required validation.');
	foreach ($required_controls[0] as $required_control) {
		$form_match = array();
		$has_form_owner = preg_match('/\bform="([^"]+)"/', $required_control, $form_match) === 1;
		$assert($has_form_owner && strpos($pending_footer_markup, '<form id="' . (string) ($form_match[1] ?? '') . '"') !== false, 'A required communication control is not owned by its detached action form.');
	}
	$submit_controls = array();
	preg_match_all('/<button\b[^>]*\btype="submit"[^>]*>/i', $pending_panel_markup, $submit_controls);
	foreach ($submit_controls[0] as $submit_control) {
		$form_match = array();
		$has_form_owner = preg_match('/\bform="([^"]+)"/', $submit_control, $form_match) === 1;
		$assert($has_form_owner && strpos($pending_footer_markup, '<form id="' . (string) ($form_match[1] ?? '') . '"') !== false, 'A communication submit control is not owned by its detached action form.');
	}
	$assert(strpos($pending_footer_markup, 'name="action" value="bvmgr_event_communication_action"') !== false && strpos($pending_footer_markup, 'name="bvmgr_event_communication_nonce"') !== false, 'Detached communication forms lost their admin-post action or nonce.');
	$assert(bvmgr_event_communication_get_ledger($plan_id, $operation_id) === $ledger_before_panel_render && bvmgr_event_occurrence_history($plan_id) === $history_before_panel_render, 'Rendering or expanding/collapsing Customer communications mutated the ledger or occurrence history.');

	$assert(function_exists('bvmgr_admission_save_vendor_guest_config') && function_exists('bvmgr_admission_vendor_guest_meta_key'), 'Vendor-managed guest Event Plan save boundary is unavailable.');
	$ordinary_save_mail = array();
	$ordinary_save_transport = static function ($pre, array $message) use (&$ordinary_save_mail): array {
		unset($pre);
		$ordinary_save_mail[] = $message;
		return array('success' => true, 'provider' => 'fixture');
	};
	add_filter('bvmgr_event_communication_mail_transport', $ordinary_save_transport, 10, 2);
	$saved_post = $_POST;
	$_POST = array(
		'bvmgr_event_plan_details_nonce' => wp_create_nonce('bvmgr_save_event_plan_details'),
		'vms_vendor_guest_rules' => array('enabled' => '1'),
	);
	bvmgr_admission_save_vendor_guest_config($plan_id, get_post($plan_id));
	$_POST = $saved_post;
	remove_filter('bvmgr_event_communication_mail_transport', $ordinary_save_transport, 10);
	$vendor_guest_rules = get_post_meta($plan_id, bvmgr_admission_vendor_guest_meta_key(), true);
	$assert(is_array($vendor_guest_rules) && (int) ($vendor_guest_rules['enabled'] ?? 0) === 1, 'An unrelated vendor-managed guest Event Plan field did not save while communication review remained unchecked.');
	$assert(bvmgr_event_communication_get_ledger($plan_id, $operation_id) === $ledger_before_panel_render && bvmgr_event_occurrence_history($plan_id) === $history_before_panel_render && empty($ordinary_save_mail), 'An unrelated Event Plan save dispatched communication or changed ledger/history/audit state.');

	$blocked_handler_mail = array();
	$block_handler_transport = static function ($pre, array $message) use (&$blocked_handler_mail): array {
		unset($pre);
		$blocked_handler_mail[] = $message;
		return array('success' => true, 'provider' => 'fixture');
	};
	add_filter('bvmgr_event_communication_mail_transport', $block_handler_transport, 10, 2);
	$invoke_communication_action(array(
		'event_plan_id' => (string) $plan_id,
		'operation_id' => $operation_id,
		'communication_action' => 'send_bulk',
		'send_mode' => 'pending',
		'subject' => 'Unchecked review subject',
		'body' => 'Unchecked review body',
		'bvmgr_event_communication_nonce' => wp_create_nonce('bvmgr_event_communication_' . $plan_id . '_' . $operation_id),
	));
	remove_filter('bvmgr_event_communication_mail_transport', $block_handler_transport, 10);
	$blocked_send_notice = get_transient(bvmgr_event_communication_admin_notice_key($plan_id));
	$assert(empty($blocked_handler_mail) && bvmgr_event_communication_get_ledger($plan_id, $operation_id) === $ledger_before_panel_render && is_array($blocked_send_notice) && empty($blocked_send_notice['ok']) && strpos((string) ($blocked_send_notice['message'] ?? ''), 'Review confirmation is required') !== false, 'Explicit customer send did not require the server-side review acknowledgment.');
	delete_transient(bvmgr_event_communication_admin_notice_key($plan_id));
	$reviewed_handler_mail = array();
	$reviewed_handler_transport = static function ($pre, array $message) use (&$reviewed_handler_mail): array {
		unset($pre);
		$reviewed_handler_mail[] = $message;
		return array('success' => true, 'provider' => 'fixture', 'provider_message_id' => 'reviewed-' . count($reviewed_handler_mail));
	};
	add_filter('bvmgr_event_communication_mail_transport', $reviewed_handler_transport, 10, 2);
	$invoke_communication_action(array(
		'event_plan_id' => (string) $plan_id,
		'operation_id' => $operation_id,
		'communication_action' => 'send_bulk',
		'send_mode' => 'pending',
		'subject' => 'Reviewed subject',
		'body' => 'Reviewed message body',
		'confirm_send' => '1',
		'bvmgr_event_communication_nonce' => wp_create_nonce('bvmgr_event_communication_' . $plan_id . '_' . $operation_id),
	));
	remove_filter('bvmgr_event_communication_mail_transport', $reviewed_handler_transport, 10);
	$reviewed_handler_ledger = bvmgr_event_communication_get_ledger($plan_id, $operation_id);
	$reviewed_handler_summary = bvmgr_event_communication_summary($reviewed_handler_ledger);
	$reviewed_send_notice = get_transient(bvmgr_event_communication_admin_notice_key($plan_id));
	$assert(count($reviewed_handler_mail) === 3 && (int) $reviewed_handler_summary['sent_bvm'] === 3 && (int) $reviewed_handler_summary['pending'] === 1 && is_array($reviewed_send_notice) && !empty($reviewed_send_notice['ok']), 'A reviewed explicit customer-send action did not reach only the eligible fake transport recipients.');
	$assert(bvmgr_event_communication_save_mutation($plan_id, $operation_id, $reviewed_handler_ledger, $ledger_before_panel_render), 'Could not restore the communication fixture after reviewed handler dispatch coverage.');
	delete_transient(bvmgr_event_communication_admin_notice_key($plan_id));

	$recipient_by_name = array();
	foreach ((array) $ledger['audience'] as $recipient_id_key => $recipient) {
		$recipient_by_name[(string) ($recipient['customer_name'] ?? '')] = (string) $recipient_id_key;
		$assert(!empty($ledger['recipient_states'][$recipient_id_key]['included']), 'Affected recipient did not default Include=ON.');
		$assert((string) ($ledger['recipient_states'][$recipient_id_key]['written_notice']['status'] ?? '') === 'pending', 'Affected recipient did not default Pending.');
	}
	$b_id = (string) ($recipient_by_name['Purchaser B'] ?? '');
	$c_id = (string) ($recipient_by_name['Purchaser C'] ?? '');
	$d_id = (string) ($recipient_by_name['Purchaser D'] ?? '');
	$assert($b_id !== '' && count((array) $ledger['audience'][$b_id]['orders']) === 2, 'Multi-order purchaser was not one recipient with both orders.');
	$assert($c_id !== '' && strpos(bvmgr_event_communication_entitlement_text((array) $ledger['audience'][$c_id]), 'Fire Table #01') !== false, 'Reservation-only purchaser was omitted.');
	$assert($d_id !== '' && empty($ledger['audience'][$d_id]['email_valid']), 'Missing-email purchaser was omitted or not flagged for manual contact.');

	$audience_fingerprint = (string) $ledger['audience_fingerprint'];
	$later_order = $create_order('Later', 'Purchaser', 'later@example.test');
	$later_item = $add_item($later_order, $ticket_id, 1, $new_date);
	$save_order($later_order);
	$ledger_after_later_order = bvmgr_event_communication_get_ledger($plan_id, $operation_id);
	$assert(hash_equals($audience_fingerprint, (string) $ledger_after_later_order['audience_fingerprint']), 'A later order changed the historical operation audience.');
	$assert(!in_array((int) $later_item->get_id(), (array) $ledger_after_later_order['audience_order_item_ids'], true), 'A later order entered the historical audience.');

	$phone = bvmgr_event_communication_mutate_recipient($plan_id, $operation_id, $b_id, 'contact', 1, array('method' => 'phone', 'note' => 'Reached by phone.'));
	$ledger = bvmgr_event_communication_get_ledger($plan_id, $operation_id);
	$assert(!empty($phone['ok']) && !empty($ledger['recipient_states'][$b_id]['included']) && (string) $ledger['recipient_states'][$b_id]['written_notice']['status'] === 'pending', 'Phone contact changed Include or resolved written notice.');
	$in_person = bvmgr_event_communication_mutate_recipient($plan_id, $operation_id, (string) $recipient_by_name['Purchaser A'], 'contact', 1, array('method' => 'in_person'));
	$assert(!empty($in_person['ok']) && (int) bvmgr_event_communication_operation_summary($plan_id, $operation_id)['unresolved'] === 4, 'In-person contact resolved written notice.');
	ob_start();
	bvmgr_event_communication_render_ledger($plan_id, $operation_id, bvmgr_event_communication_get_ledger($plan_id, $operation_id));
	$unresolved_markup = (string) ob_get_clean();
	$assert(strpos($unresolved_markup, 'Written notice remains unresolved') !== false && strpos($unresolved_markup, 'does not clear this reminder') !== false, 'Persistent unresolved warning disappeared after informal contact.');
	$assert(strpos($unresolved_markup, 'name="include_recipient"') !== false && strpos($unresolved_markup, 'To exclude, uncheck Include') !== false, 'Included pending recipients do not expose the deliberate Include checkbox workflow.');

	$exclude_without_reason = bvmgr_event_communication_mutate_recipient($plan_id, $operation_id, $c_id, 'exclude', 1, array('confirmed' => true, 'reason' => ''));
	$assert(empty($exclude_without_reason['ok']), 'Exclusion without a reason did not fail closed.');
	$excluded = bvmgr_event_communication_mutate_recipient($plan_id, $operation_id, $c_id, 'exclude', 1, array('confirmed' => true, 'reason' => 'Customer requested no email.'));
	$ledger = bvmgr_event_communication_get_ledger($plan_id, $operation_id);
	$assert(!empty($excluded['ok']) && empty($ledger['recipient_states'][$c_id]['included']) && (string) $ledger['recipient_states'][$c_id]['written_notice']['status'] === 'excluded', 'Deliberate exclusion was not stored.');
	$assert((string) $ledger['recipient_states'][$c_id]['written_notice']['exclusion_reason'] === 'Customer requested no email.' && (int) $ledger['recipient_states'][$c_id]['written_notice']['actor_user_id'] === 1, 'Exclusion audit actor/reason changed.');
	$manual_d = bvmgr_event_communication_mutate_recipient($plan_id, $operation_id, $d_id, 'manual', 1, array('channel' => 'other_written', 'note' => 'Written letter provided.'));
	$assert(!empty($manual_d['ok']) && (string) bvmgr_event_communication_get_ledger($plan_id, $operation_id)['recipient_states'][$d_id]['written_notice']['status'] === 'sent_manual', 'Manual outside-BVM notice did not resolve the recipient.');

	$ledger = bvmgr_event_communication_get_ledger($plan_id, $operation_id);
	$correction_message = bvmgr_event_communication_default_message($ledger);
	$assert(strpos($correction_message['body'], 'incorrectly listed') !== false && strpos($correction_message['body'], 'September 19') !== false && strpos($correction_message['body'], 'September 12') !== false, 'Date-correction template wording or dates changed.');
	$rescheduled_ledger = $ledger;
	$rescheduled_ledger['reason'] = 'rescheduled';
	$rescheduled_message = bvmgr_event_communication_default_message($rescheduled_ledger);
	$assert(strpos($rescheduled_message['body'], 'moved from') !== false && strpos($rescheduled_message['body'], 'incorrectly listed') === false, 'Rescheduled template used correction wording.');

	$sent_messages = array();
	$fail_c_once = true;
	$fake_transport = static function ($pre, array $message) use (&$sent_messages, &$fail_c_once): array {
		$sent_messages[] = $message;
		if ($fail_c_once && (string) ($message['to'] ?? '') === 'purchaser-c@example.test') {
			$fail_c_once = false;
			return array('success' => false, 'provider' => 'fixture_mail', 'error' => 'synthetic_failure');
		}
		return array('success' => true, 'provider' => 'fixture_mail', 'provider_message_id' => 'fixture-' . count($sent_messages));
	};
	add_filter('bvmgr_event_communication_mail_transport', $fake_transport, 10, 2);
	$bulk = bvmgr_event_communication_send_bulk($plan_id, $operation_id, 1, $correction_message['subject'], $correction_message['body'], 'pending');
	$assert((int) $bulk['attempted'] === 2 && (int) $bulk['accepted'] === 2 && (int) $bulk['failed'] === 0, 'Bulk pending send included an excluded/manual/missing-email recipient.');
	$assert(count($sent_messages) === 2 && !in_array('purchaser-c@example.test', array_column($sent_messages, 'to'), true), 'Excluded recipient was sent by normal bulk send.');
	foreach ($sent_messages as $sent_message) {
		$assert(is_string($sent_message['to']) && strpos((string) $sent_message['to'], ',') === false, 'Customer recipients were exposed to one another.');
	}
	$reincluded = bvmgr_event_communication_mutate_recipient($plan_id, $operation_id, $c_id, 'reinclude', 1);
	$assert(!empty($reincluded['ok']) && (string) bvmgr_event_communication_get_ledger($plan_id, $operation_id)['recipient_states'][$c_id]['written_notice']['status'] === 'pending', 'Re-inclusion did not restore Pending.');
	$message_count_before_invalid_resend = count($sent_messages);
	$invalid_resend = bvmgr_event_communication_send_recipient($plan_id, $operation_id, $c_id, 1, $correction_message['subject'], $correction_message['body'], true);
	$assert(empty($invalid_resend['ok']) && count($sent_messages) === $message_count_before_invalid_resend, 'Explicit resend bypassed the normal Pending workflow.');
	$failed_bulk = bvmgr_event_communication_send_bulk($plan_id, $operation_id, 1, $correction_message['subject'], $correction_message['body'], 'pending');
	$assert((int) $failed_bulk['attempted'] === 1 && (int) $failed_bulk['accepted'] === 0 && (int) $failed_bulk['failed'] === 1, 'Bulk pending send did not retain a re-included recipient failure.');
	$ledger = bvmgr_event_communication_get_ledger($plan_id, $operation_id);
	$assert((string) $ledger['recipient_states'][$c_id]['written_notice']['status'] === 'failed' && (string) $ledger['recipient_states'][$c_id]['written_notice']['error_information'] === 'synthetic_failure', 'Failed recipient was marked sent or lost its safe error.');
	$assert((string) $ledger['recipient_states'][$b_id]['written_notice']['subject'] === $correction_message['subject'] && (string) $ledger['recipient_states'][$b_id]['written_notice']['body'] === $correction_message['body'], 'Exact final BVM subject/body was not stored.');
	$failed_ledger_before_panel = $ledger;
	$failed_history_before_panel = bvmgr_event_occurrence_history($plan_id);
	ob_start();
	bvmgr_event_communication_render_admin_section($plan_id, $failed_history_before_panel, $operation_id);
	$failed_panel_markup = (string) ob_get_clean();
	$assert(strpos($failed_panel_markup, '<details id="bvmgr-event-communications" class="vms-ep-card vms-mt-12" open>') !== false && strpos($failed_panel_markup, '1 failed') !== false, 'A failed communication state did not default expanded with a failed summary.');
	$assert(bvmgr_event_communication_get_ledger($plan_id, $operation_id) === $failed_ledger_before_panel && bvmgr_event_occurrence_history($plan_id) === $failed_history_before_panel, 'Rendering the failed communication panel mutated ledger/history/audit state.');

	$messages_before_unconfirmed_retry = count($sent_messages);
	$invoke_communication_action(array(
		'event_plan_id' => (string) $plan_id,
		'operation_id' => $operation_id,
		'communication_action' => 'send_bulk',
		'send_mode' => 'failed',
		'subject' => $correction_message['subject'],
		'body' => $correction_message['body'],
		'bvmgr_event_communication_nonce' => wp_create_nonce('bvmgr_event_communication_' . $plan_id . '_' . $operation_id),
	));
	$retry_blocked_ledger = bvmgr_event_communication_get_ledger($plan_id, $operation_id);
	$retry_blocked_notice = get_transient(bvmgr_event_communication_admin_notice_key($plan_id));
	$assert(count($sent_messages) === $messages_before_unconfirmed_retry && $retry_blocked_ledger === $failed_ledger_before_panel && is_array($retry_blocked_notice) && empty($retry_blocked_notice['ok']), 'Explicit Retry Failed did not retain its server-side review acknowledgment and no-send guard.');
	delete_transient(bvmgr_event_communication_admin_notice_key($plan_id));

	$messages_before_confirmed_retry = count($sent_messages);
	$invoke_communication_action(array(
		'event_plan_id' => (string) $plan_id,
		'operation_id' => $operation_id,
		'communication_action' => 'send_bulk',
		'send_mode' => 'failed',
		'subject' => $correction_message['subject'],
		'body' => $correction_message['body'],
		'confirm_send' => '1',
		'bvmgr_event_communication_nonce' => wp_create_nonce('bvmgr_event_communication_' . $plan_id . '_' . $operation_id),
	));
	$confirmed_retry_ledger = bvmgr_event_communication_get_ledger($plan_id, $operation_id);
	$confirmed_retry_notice = get_transient(bvmgr_event_communication_admin_notice_key($plan_id));
	$assert(count($sent_messages) === $messages_before_confirmed_retry + 1 && (string) $confirmed_retry_ledger['recipient_states'][$c_id]['written_notice']['status'] === 'sent_bvm' && is_array($confirmed_retry_notice) && !empty($confirmed_retry_notice['ok']), 'Reviewed Retry Failed did not target and resolve only the failed recipient through the protected handler.');
	delete_transient(bvmgr_event_communication_admin_notice_key($plan_id));
	$duplicate = bvmgr_event_communication_send_bulk($plan_id, $operation_id, 1, $correction_message['subject'], $correction_message['body'], 'pending');
	$assert((int) $duplicate['attempted'] === 0, 'Normal sending duplicated sent/manual/excluded recipients.');
	$before_attempts = count((array) bvmgr_event_communication_get_ledger($plan_id, $operation_id)['recipient_states'][$b_id]['attempts']);
	$resend = bvmgr_event_communication_send_recipient($plan_id, $operation_id, $b_id, 1, $correction_message['subject'], $correction_message['body'], true);
	$after_attempts = count((array) bvmgr_event_communication_get_ledger($plan_id, $operation_id)['recipient_states'][$b_id]['attempts']);
	$assert(!empty($resend['accepted']) && $after_attempts === $before_attempts + 1, 'Explicit resend did not append a new attempt record.');
	remove_filter('bvmgr_event_communication_mail_transport', $fake_transport, 10);

	$summary = bvmgr_event_communication_operation_summary($plan_id, $operation_id);
	$assert((int) $summary['resolved'] === 4 && (int) $summary['unresolved'] === 0, 'All-resolved communication state did not clear the persistent warning condition.');
	$manual_overwrite = bvmgr_event_communication_mutate_recipient($plan_id, $operation_id, $b_id, 'manual', 1, array('channel' => 'email_outside_bvm'));
	$exclude_overwrite = bvmgr_event_communication_mutate_recipient($plan_id, $operation_id, $b_id, 'exclude', 1, array('confirmed' => true, 'reason' => 'Invalid overwrite attempt.'));
	$assert(empty($manual_overwrite['ok']) && empty($exclude_overwrite['ok']) && (string) bvmgr_event_communication_get_ledger($plan_id, $operation_id)['recipient_states'][$b_id]['written_notice']['status'] === 'sent_bvm', 'Resolved written notice was overwritten by manual or exclusion state.');
	$export = bvmgr_event_communication_export_rows(bvmgr_event_communication_get_ledger($plan_id, $operation_id));
	$assert(count($export) === 4 && array_keys($export[0]) === array('name', 'email', 'affected_orders', 'affected_entitlements', 'written_notice_status'), 'CSV export rows include unexpected or missing fields.');
	$resolved_without_unfinished_attempt = bvmgr_event_communication_get_ledger($plan_id, $operation_id);
	$unfinished_attempt_ledger = $resolved_without_unfinished_attempt;
	$unfinished_attempt_ledger['recipient_states'][$b_id]['attempts'][] = array(
		'attempt_id' => wp_generate_uuid4(),
		'type' => 'explicit_resend',
		'started_at_utc' => gmdate('Y-m-d H:i:s'),
		'completed_at_utc' => '',
		'result' => 'initiated',
	);
	$assert(bvmgr_event_communication_save_mutation($plan_id, $operation_id, $resolved_without_unfinished_attempt, $unfinished_attempt_ledger), 'Could not create the synthetic unfinished-attempt attention fixture.');
	$unfinished_before_panel = bvmgr_event_communication_get_ledger($plan_id, $operation_id);
	ob_start();
	bvmgr_event_communication_render_admin_section($plan_id, bvmgr_event_occurrence_history($plan_id), $operation_id);
	$unfinished_panel_markup = (string) ob_get_clean();
	$assert(strpos($unfinished_panel_markup, '<details id="bvmgr-event-communications" class="vms-ep-card vms-mt-12" open>') !== false && strpos($unfinished_panel_markup, '1 send attempt needs review') !== false, 'An unfinished communication attempt did not default expanded for explicit administrator review.');
	$assert(bvmgr_event_communication_get_ledger($plan_id, $operation_id) === $unfinished_before_panel, 'Rendering an unfinished-attempt attention state mutated communication state.');
	$assert(bvmgr_event_communication_save_mutation($plan_id, $operation_id, $unfinished_before_panel, $resolved_without_unfinished_attempt), 'Could not restore the fully resolved communication fixture after unfinished-attempt coverage.');
	$resolved_ledger_before_panel = bvmgr_event_communication_get_ledger($plan_id, $operation_id);
	$resolved_history_before_panel = bvmgr_event_occurrence_history($plan_id);
	$saved_get = $_GET;
	$_GET['bvmgr_communication_operation'] = $operation_id;
	ob_start();
	bvmgr_event_communication_render_admin_section($plan_id, $resolved_history_before_panel, $operation_id);
	$markup = (string) ob_get_clean();
	$_GET = $saved_get;
	$assert(strpos($markup, 'Review &amp; Send Notification') !== false && strpos($markup, 'Every affected recipient has a resolved written-notice state') !== false && strpos($markup, 'Written notice remains unresolved') === false, 'Resolved recipient ledger UI or reminder state changed.');
	$assert(strpos($markup, '<details id="bvmgr-event-communications" class="vms-ep-card vms-mt-12">') !== false && strpos($markup, 'Customer communications</strong> — 4 of 4 complete') !== false, 'A fully resolved communication ledger did not default collapsed with a completed summary.');
	$assert(strpos($markup, 'bvmgr-event-communications__body') !== false && strpos($markup, 'overflow-x:auto') !== false, 'The communication ledger lost its narrow-screen overflow boundary.');
	$assert(bvmgr_event_communication_get_ledger($plan_id, $operation_id) === $resolved_ledger_before_panel && bvmgr_event_occurrence_history($plan_id) === $resolved_history_before_panel, 'Rendering the collapsed resolved panel mutated recipients, sent states, attempts, audit data, or history.');
	ob_start();
	bvmgr_event_occurrence_render_admin_panel($plan_id);
	$history_markup = (string) ob_get_clean();
	$assert(strpos($history_markup, '5 orders / 4 customers') !== false && strpos($history_markup, '4 of 4 written notices completed') !== false && strpos($history_markup, 'Open recipient ledger') !== false, 'Operation history did not expose the communication aggregate and durable ledger action.');

	// Synthetic historical operation for deterministic retroactive bootstrap.
	$bootstrap_event_id = $create_event('Bootstrap calendar fixture', $new_date);
	$bootstrap_plan_id = $create_plan('Bootstrap Event Plan', $bootstrap_event_id, $new_date);
	$bootstrap_ticket_id = $create_product('Youth Ticket', $bootstrap_plan_id, $bootstrap_event_id, 'ga_ticket', $new_date);
	$bootstrap_reservation_id = $create_product('Kiddie Pool', $bootstrap_plan_id, $bootstrap_event_id, 'entitlement', $new_date);
	$bootstrap_operation_id = wp_generate_uuid4();
	$bootstrap_orders = array();
	$bootstrap_items = array();
	foreach (array(
		array('Retro', 'Customer', 'retro@example.test', $bootstrap_ticket_id),
		array('Retro', 'Customer', 'retro@example.test', $bootstrap_reservation_id),
		array('Reservation', 'Only', 'reservation-only@example.test', $bootstrap_reservation_id),
	) as $fixture) {
		$order = $create_order($fixture[0], $fixture[1], $fixture[2]);
		$item = $add_item($order, (int) $fixture[3], 1, $old_date);
		$save_order($order);
		$item_id = (int) $item->get_id();
		wc_update_order_item_meta($item_id, '_vms_occurrence_operation_id', $bootstrap_operation_id);
		wc_update_order_item_meta($item_id, '_vms_effective_event_start_local', $new_date . ' 19:00:00');
		wc_update_order_item_meta($item_id, '_vms_effective_event_end_local', $new_date . ' 21:00:00');
		$bootstrap_orders[] = (int) $order->get_id();
		$bootstrap_items[] = $item_id;
	}
	$unrelated_order = $create_order('Unrelated', 'Later', 'unrelated@example.test');
	$unrelated_item = $add_item($unrelated_order, $bootstrap_ticket_id, 1, $new_date);
	$save_order($unrelated_order);
	update_post_meta($bootstrap_plan_id, '_vms_event_occurrence_history_v1', array(array(
		'operation_id' => $bootstrap_operation_id,
		'mode' => 'repair',
		'reason' => 'date_correction',
		'old_start_local' => $old_date . ' 19:00:00',
		'old_end_local' => $old_date . ' 21:00:00',
		'old_start_utc' => get_gmt_from_date($old_date . ' 19:00:00'),
		'old_end_utc' => get_gmt_from_date($old_date . ' 21:00:00'),
		'new_start_local' => $new_date . ' 19:00:00',
		'new_end_local' => $new_date . ' 21:00:00',
		'new_start_utc' => get_gmt_from_date($new_date . ' 19:00:00'),
		'new_end_utc' => get_gmt_from_date($new_date . ' 21:00:00'),
		'timezone' => wp_timezone_string(),
		'actor_user_id' => 1,
		'created_at_utc' => gmdate('Y-m-d H:i:s'),
		'impact_counts' => array('orders' => 3, 'line_items' => 3, 'customers' => 2, 'custom_admission_rows' => 0),
		'order_ids' => $bootstrap_orders,
	)));
	$bootstrap_history_before_panel = bvmgr_event_occurrence_history($bootstrap_plan_id);
	ob_start();
	bvmgr_event_communication_render_admin_section($bootstrap_plan_id, $bootstrap_history_before_panel, $bootstrap_operation_id);
	$missing_ledger_panel_markup = (string) ob_get_clean();
	$assert(strpos($missing_ledger_panel_markup, '<details id="bvmgr-event-communications" class="vms-ep-card vms-mt-12" open>') !== false && strpos($missing_ledger_panel_markup, 'ledger review required') !== false, 'An affected historical operation with a missing communication ledger did not default expanded for administrator attention.');
	$assert(bvmgr_event_occurrence_history($bootstrap_plan_id) === $bootstrap_history_before_panel && bvmgr_event_communication_get_ledger($bootstrap_plan_id, $bootstrap_operation_id) === array(), 'Rendering a missing-ledger attention state mutated operation history or created communication state.');
	$bootstrap_preview = bvmgr_event_communication_bootstrap_preview($bootstrap_plan_id, $bootstrap_operation_id);
	$assert(!empty($bootstrap_preview['allowed']) && (int) $bootstrap_preview['counts']['customers'] === 2 && (int) $bootstrap_preview['counts']['orders'] === 3 && (int) $bootstrap_preview['counts']['line_items'] === 3, 'Retroactive preview did not reconstruct the exact operation audience.');
	$bootstrap_apply = bvmgr_event_communication_bootstrap_apply($bootstrap_plan_id, $bootstrap_operation_id, 1, (string) $bootstrap_preview['fingerprint']);
	$assert(!empty($bootstrap_apply['ok']) && empty($bootstrap_apply['noop']), 'Retroactive bootstrap apply failed.');
	$bootstrap_ledger = bvmgr_event_communication_get_ledger($bootstrap_plan_id, $bootstrap_operation_id);
	$assert(!in_array((int) $unrelated_item->get_id(), (array) $bootstrap_ledger['audience_order_item_ids'], true), 'Retroactive bootstrap included an unrelated later order.');
	$bootstrap_repeat = bvmgr_event_communication_bootstrap_apply($bootstrap_plan_id, $bootstrap_operation_id, 1, (string) $bootstrap_preview['fingerprint']);
	$assert(!empty($bootstrap_repeat['ok']) && !empty($bootstrap_repeat['noop']) && (int) bvmgr_event_communication_operation_summary($bootstrap_plan_id, $bootstrap_operation_id)['recipient_count'] === 2, 'Retroactive bootstrap was not idempotent.');
	$manual_all = bvmgr_event_communication_mark_manual_bulk($bootstrap_plan_id, $bootstrap_operation_id, 1, 'email_outside_bvm', 'Synthetic prior manual notice.');
	$bootstrap_summary = bvmgr_event_communication_operation_summary($bootstrap_plan_id, $bootstrap_operation_id);
	$assert(!empty($manual_all['ok']) && (int) $manual_all['updated'] === 2 && (int) $bootstrap_summary['resolved'] === 2 && (int) $bootstrap_summary['unresolved'] === 0, 'Retroactive manual-notice closeout failed or sent email.');

	$source = (string) file_get_contents($plugin_root . '/includes/core/event-communications.php');
	$admin_source = (string) file_get_contents($plugin_root . '/includes/admin/event-communications.php');
	$cli_source = (string) file_get_contents($plugin_root . '/includes/core/cli/event-reschedule.php');
	$assert(strpos($source, 'bvmgr_event_communication_persist_for_operation') !== false && strpos($source, 'audience_fingerprint') !== false && strpos($source, 'START TRANSACTION') !== false, 'Durable transaction/bootstrap service contract is incomplete.');
	$assert(strpos($admin_source, 'check_admin_referer') !== false && strpos($admin_source, 'confirm_exclusion') !== false && strpos($admin_source, 'bvmgr_event_communication_admin_export') !== false, 'Admin security, exclusion, or CSV contract is incomplete.');
	$assert(strpos($cli_source, "WP_CLI::add_command('bvmgr event communication'") !== false && strpos($cli_source, 'BOOTSTRAP-COMMUNICATIONS') !== false && strpos($cli_source, 'MARK-MANUAL') !== false, 'Communication CLI control surface is incomplete.');
	$shadow_root = dirname($plugin_root, 2) . '/vms';
	if (is_dir($shadow_root)) {
		$legacy_transform = static function (string $canonical): string {
			return str_replace(array('BVMGR_', 'bvmgr_', "'backstage-venue-manager'"), array('VMS_', 'vms_', "'vms'"), $canonical);
		};
		$shadow_core = is_file($shadow_root . '/includes/core/event-communications.php') ? (string) file_get_contents($shadow_root . '/includes/core/event-communications.php') : '';
		$shadow_admin = is_file($shadow_root . '/includes/admin/event-communications.php') ? (string) file_get_contents($shadow_root . '/includes/admin/event-communications.php') : '';
		$assert($shadow_core === $legacy_transform($source) && $shadow_admin === $legacy_transform($admin_source), 'Disposable legacy sibling communication services lost exact logical parity.');
		$shadow_reschedule = is_file($shadow_root . '/includes/core/event-reschedule.php') ? (string) file_get_contents($shadow_root . '/includes/core/event-reschedule.php') : '';
		$shadow_cli = is_file($shadow_root . '/includes/core/cli/event-reschedule.php') ? (string) file_get_contents($shadow_root . '/includes/core/cli/event-reschedule.php') : '';
		$assert(strpos($shadow_reschedule, 'vms_event_communication_persist_for_operation') !== false && strpos($shadow_cli, "WP_CLI::add_command('vms event communication'") !== false, 'Disposable legacy sibling is missing the automatic ledger or controlled CLI integration.');
	}

	fwrite(STDOUT, 'PASS: ' . $assertions . " reschedule customer communication assertions.\n");
} finally {
	$cleanup();
}
