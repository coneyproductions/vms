<?php
defined('ABSPATH') || exit;

/**
 * STATUS-02 adapters
 *
 * Provider-side execution is intentionally conservative:
 * - Stop ticket sales via product stock/visibility controls.
 * - Keep Event visibility decisions out of this first adapter pass.
 */

if (!function_exists('vms_cancellation_refund_product_roles')) {
	/**
	 * Product roles VMS can safely refund when an Event Plan is cancelled.
	 *
	 * This list intentionally excludes generic Woo products. A product still needs
	 * an Event Plan, TEC event, ticket, or sync-map link before it is considered.
	 */
	function vms_cancellation_refund_product_roles(): array
	{
		$roles = array('ticket', 'ga_ticket', 'legacy_ticket', 'entitlement', 'addon');
		$roles = array_values(array_unique(array_filter(array_map('sanitize_key', $roles))));
		return (array) apply_filters('vms_cancellation_refund_product_roles', $roles);
	}
}

if (!function_exists('vms_cancellation_refund_product_meta_key')) {
	function vms_cancellation_refund_product_meta_key(string $which): string
	{
		if (function_exists('vms_ticketing_v2_product_meta_key')) {
			$key = vms_ticketing_v2_product_meta_key($which);
			if (is_string($key) && $key !== '') {
				return $key;
			}
		}

		if (function_exists('bvmgr_meta_key')) {
			$key = bvmgr_meta_key('product', $which);
			if (is_string($key) && $key !== '') {
				return $key;
			}
		}

		switch ($which) {
			case 'event_plan_id':
				return '_vms_event_plan_id';
			case 'tec_event_id':
				return '_vms_tec_event_id';
			case 'product_role':
				return '_vms_product_role';
			case 'ticketing_entitlement_id':
				return '_vms_ticketing_entitlement_id';
			default:
				return '';
		}
	}
}

if (!function_exists('vms_cancellation_refund_product_role')) {
	function vms_cancellation_refund_product_role(int $product_id): string
	{
		$product_id = absint($product_id);
		if ($product_id <= 0) {
			return '';
		}

		$role_key = vms_cancellation_refund_product_meta_key('product_role');
		$role = $role_key !== '' ? sanitize_key((string) get_post_meta($product_id, $role_key, true)) : '';
		if ($role !== '') {
			return $role;
		}

		// Legacy/adopted VMS add-on markers.
		$legacy_addon_type = sanitize_key((string) get_post_meta($product_id, '_sr_addon_type', true));
		$legacy_required = trim((string) get_post_meta($product_id, '_sr_required_qualifiers_per_unit', true));
		$legacy_unit = trim((string) get_post_meta($product_id, '_sr_addon_unit_label', true));
		if ($legacy_addon_type !== '' || $legacy_required !== '' || $legacy_unit !== '') {
			return 'addon';
		}

		$legacy_qualifier = sanitize_key((string) get_post_meta($product_id, '_sr_addon_qualifier', true));
		if ($legacy_qualifier === 'yes') {
			return 'ga_ticket';
		}

		if (absint(get_post_meta($product_id, '_tribe_wooticket_for_event', true)) > 0) {
			return 'ticket';
		}

		return '';
	}
}

if (!function_exists('vms_cancellation_refund_collect_sync_product_ids')) {
	function vms_cancellation_refund_collect_sync_product_ids(int $event_plan_id): array
	{
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0 || !function_exists('vms_ticketing_v2_get_sync')) {
			return array();
		}

		$sync = vms_ticketing_v2_get_sync($event_plan_id);
		if (!is_array($sync)) {
			return array();
		}
		$map = isset($sync['map']) && is_array($sync['map']) ? $sync['map'] : $sync;

		if (function_exists('vms_ticketing_v2_collect_sync_map_product_ids')) {
			$ids = vms_ticketing_v2_collect_sync_map_product_ids($map);
			return array_values(array_unique(array_filter(array_map('absint', (array) $ids))));
		}

		$ids = array();
		foreach (array('tickets', 'entitlements') as $bucket) {
			$rows = isset($map[$bucket]) && is_array($map[$bucket]) ? $map[$bucket] : array();
			foreach ($rows as $row) {
				if (!is_array($row)) {
					continue;
				}
				$pid = absint($row['woo_product_id'] ?? 0);
				if ($pid > 0) {
					$ids[] = $pid;
				}
			}
		}
		if (isset($map['ga']) && is_array($map['ga'])) {
			$pid = absint($map['ga']['woo_product_id'] ?? 0);
			if ($pid > 0) {
				$ids[] = $pid;
			}
		}

		$ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
		sort($ids, SORT_NUMERIC);
		return $ids;
	}
}

if (!function_exists('vms_cancellation_get_event_refundable_product_ids')) {
	/**
	 * Return Woo product IDs that VMS can prove are tied to this cancelled Event Plan.
	 *
	 * This includes TEC ticket products and VMS-managed event add-ons/entitlements.
	 * It intentionally does not include unrelated products that happen to be in the same order.
	 */
	function vms_cancellation_get_event_refundable_product_ids(int $event_plan_id, int $tec_event_id = 0): array
	{
		$event_plan_id = absint($event_plan_id);
		$tec_event_id = absint($tec_event_id);
		if ($event_plan_id <= 0 && $tec_event_id <= 0) {
			return array();
		}

		$event_plan_key = vms_cancellation_refund_product_meta_key('event_plan_id');
		$tec_event_key = vms_cancellation_refund_product_meta_key('tec_event_id');
		$roles = vms_cancellation_refund_product_roles();
		$ids = array();
		$ticket_ids = array();
		$sync_ids = array();

		if ($tec_event_id > 0 && function_exists('bvmgr_get_ticket_product_ids_for_event')) {
			$ticket_ids = array_values(array_unique(array_filter(array_map('absint', (array) bvmgr_get_ticket_product_ids_for_event($tec_event_id)))));
			$ids = array_merge($ids, $ticket_ids);
		}

		if ($event_plan_id > 0) {
			$sync_ids = vms_cancellation_refund_collect_sync_product_ids($event_plan_id);
			$ids = array_merge($ids, $sync_ids);
		}

		if (function_exists('get_posts')) {
			$meta_query = array('relation' => 'OR');
			if ($tec_event_id > 0) {
				$meta_query[] = array(
					'key' => '_tribe_wooticket_for_event',
					'value' => (string) $tec_event_id,
					'compare' => '=',
				);
				if ($tec_event_key !== '') {
					$meta_query[] = array(
						'key' => $tec_event_key,
						'value' => (string) $tec_event_id,
						'compare' => '=',
					);
				}
			}
			if ($event_plan_id > 0 && $event_plan_key !== '') {
				$meta_query[] = array(
					'key' => $event_plan_key,
					'value' => (string) $event_plan_id,
					'compare' => '=',
				);
			}

			if (count($meta_query) > 1) {
				$found = get_posts(array(
					'post_type' => 'product',
					'posts_per_page' => -1,
					'post_status' => array('publish', 'future', 'draft', 'pending', 'private'),
					'fields' => 'ids',
					'meta_query' => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Cancellation discovery intentionally retrieves every product ID linked to the Event Plan or TEC event so refund and sales-stop processing cannot omit products.
				));
				$ids = array_merge($ids, array_map('absint', (array) $found));
			}
		}

		$ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
		$ticket_lookup = array_fill_keys($ticket_ids, true);
		$sync_lookup = array_fill_keys($sync_ids, true);
		$out = array();

		foreach ($ids as $product_id) {
			$product_id = absint($product_id);
			if ($product_id <= 0) {
				continue;
			}

			$is_tec_ticket = isset($ticket_lookup[$product_id]);
			$is_sync_product = isset($sync_lookup[$product_id]);
			$role = vms_cancellation_refund_product_role($product_id);
			$role_allowed = ($role !== '' && in_array($role, $roles, true));
			$product_plan_id = $event_plan_key !== '' ? absint(get_post_meta($product_id, $event_plan_key, true)) : 0;
			$product_tec_id = $tec_event_key !== '' ? absint(get_post_meta($product_id, $tec_event_key, true)) : 0;
			$tribe_event_id = absint(get_post_meta($product_id, '_tribe_wooticket_for_event', true));

			$linked_to_plan = ($event_plan_id > 0 && $product_plan_id === $event_plan_id);
			$linked_to_tec = ($tec_event_id > 0 && ($product_tec_id === $tec_event_id || $tribe_event_id === $tec_event_id));

			if ($is_tec_ticket || ($is_sync_product && ($role === '' || $role_allowed)) || (($linked_to_plan || $linked_to_tec) && $role_allowed)) {
				$out[] = $product_id;
			}
		}

		$out = array_values(array_unique(array_filter(array_map('absint', $out))));
		sort($out, SORT_NUMERIC);
		return (array) apply_filters('vms_cancellation_event_refundable_product_ids', $out, $event_plan_id, $tec_event_id);
	}
}

if (!function_exists('vms_cancellation_refund_order_item_meta_first')) {
	function vms_cancellation_refund_order_item_meta_first($item, array $keys): string
	{
		if (!is_object($item) || !method_exists($item, 'get_meta')) {
			return '';
		}

		foreach ($keys as $key) {
			$key = (string) $key;
			if ($key === '') {
				continue;
			}
			$value = $item->get_meta($key, true);
			if (is_array($value) || is_object($value)) {
				continue;
			}
			$value = trim((string) $value);
			if ($value !== '') {
				return $value;
			}
		}

		return '';
	}
}

if (!function_exists('vms_cancellation_refund_match_order_item')) {
	/**
	 * Decide whether a Woo order line is safe to refund for the cancelled Event Plan.
	 */
	function vms_cancellation_refund_match_order_item($item, int $event_plan_id, int $tec_event_id, array $product_lookup): array
	{
		$event_plan_id = absint($event_plan_id);
		$tec_event_id = absint($tec_event_id);
		$product_ids = array();

		if (is_object($item) && method_exists($item, 'get_product_id')) {
			$product_ids[] = absint($item->get_product_id());
		}
		if (is_object($item) && method_exists($item, 'get_variation_id')) {
			$product_ids[] = absint($item->get_variation_id());
		}
		$product_ids = array_values(array_unique(array_filter(array_map('absint', $product_ids))));

		foreach ($product_ids as $product_id) {
			if (isset($product_lookup[$product_id])) {
				return array(
					'matched' => true,
					'source' => 'event_product_lookup',
					'product_id' => $product_id,
					'product_role' => vms_cancellation_refund_product_role($product_id),
				);
			}
		}

		$item_plan_id = absint(vms_cancellation_refund_order_item_meta_first($item, array('_vms_event_plan_id', 'vms_event_plan_id')));
		$item_tec_event_id = absint(vms_cancellation_refund_order_item_meta_first($item, array('_vms_tec_event_post_id', '_vms_tec_event_id', '_tribe_wooticket_for_event')));
		if (($event_plan_id > 0 && $item_plan_id === $event_plan_id) || ($tec_event_id > 0 && $item_tec_event_id === $tec_event_id)) {
			$product_id = !empty($product_ids) ? absint($product_ids[0]) : 0;
			return array(
				'matched' => true,
				'source' => 'order_item_event_snapshot',
				'product_id' => $product_id,
				'product_role' => $product_id > 0 ? vms_cancellation_refund_product_role($product_id) : '',
			);
		}

		$event_plan_key = vms_cancellation_refund_product_meta_key('event_plan_id');
		$tec_event_key = vms_cancellation_refund_product_meta_key('tec_event_id');
		$roles = vms_cancellation_refund_product_roles();

		foreach ($product_ids as $product_id) {
			$role = vms_cancellation_refund_product_role($product_id);
			$role_allowed = ($role !== '' && in_array($role, $roles, true));
			$tribe_event_id = absint(get_post_meta($product_id, '_tribe_wooticket_for_event', true));
			if ($tec_event_id > 0 && $tribe_event_id === $tec_event_id) {
				return array(
					'matched' => true,
					'source' => 'tribe_ticket_product_meta',
					'product_id' => $product_id,
					'product_role' => $role,
				);
			}

			$product_plan_id = $event_plan_key !== '' ? absint(get_post_meta($product_id, $event_plan_key, true)) : 0;
			$product_tec_event_id = $tec_event_key !== '' ? absint(get_post_meta($product_id, $tec_event_key, true)) : 0;
			if ($role_allowed && (($event_plan_id > 0 && $product_plan_id === $event_plan_id) || ($tec_event_id > 0 && $product_tec_event_id === $tec_event_id))) {
				return array(
					'matched' => true,
					'source' => 'vms_product_event_meta',
					'product_id' => $product_id,
					'product_role' => $role,
				);
			}
		}

		return array(
			'matched' => false,
			'source' => '',
			'product_id' => !empty($product_ids) ? absint($product_ids[0]) : 0,
			'product_role' => '',
		);
	}
}

add_filter('vms_cancellation_run_step', function ($result, $event_plan_id, $policy, $step_key, $summary) {
	$event_plan_id = absint($event_plan_id);
	$policy = sanitize_key((string) $policy);
	$step_key = sanitize_key((string) $step_key);

	if ($event_plan_id <= 0) {
		return $result;
	}

	if ($step_key !== 'provider_sales_stop') {
		// continue below for other adapter steps
	} else {
		if ($policy === 'status_only') {
			return array(
				'status' => 'done',
				'message' => 'skipped_by_policy',
				'data' => array(),
			);
		}

		$k_tec_event_id = function_exists('bvmgr_meta_key')
			? (bvmgr_meta_key('event_plan', 'tec_event_id') ?: '_vms_tec_event_id')
			: '_vms_tec_event_id';
		$k_ticketing_override = function_exists('bvmgr_meta_key')
			? (bvmgr_meta_key('event_plan', 'ticketing_enabled_override') ?: '_vms_ticketing_enabled_override')
			: '_vms_ticketing_enabled_override';

		$tec_event_id = (int) get_post_meta($event_plan_id, $k_tec_event_id, true);
		$data = array(
			'event_plan_id' => $event_plan_id,
			'tec_event_id' => $tec_event_id,
			'ticketing_override' => 'off',
			'updated_products' => array(),
			'failed_products' => array(),
			'rsvp_tickets_disabled' => array(),
			'failed_rsvp_tickets' => array(),
		);

		// Ensure new ticketing automation paths are disabled for this plan.
		update_post_meta($event_plan_id, $k_ticketing_override, 'off');

		if ($tec_event_id <= 0) {
			return array(
				'status' => 'blocked',
				'message' => 'missing_tec_event_link',
				'data' => $data,
			);
		}

		$product_ids = function_exists('vms_cancellation_get_event_refundable_product_ids')
			? array_values(array_unique(array_filter(array_map('absint', (array) vms_cancellation_get_event_refundable_product_ids($event_plan_id, $tec_event_id)))))
			: (function_exists('bvmgr_get_ticket_product_ids_for_event') ? array_values(array_unique(array_filter(array_map('absint', (array) bvmgr_get_ticket_product_ids_for_event($tec_event_id))))) : array());
		if (empty($product_ids)) {
			$data['note'] = 'no_woo_event_products_found';
		} else {

		foreach ($product_ids as $product_id) {
			$product_id = absint($product_id);
			if ($product_id <= 0) {
				continue;
			}

			$ok = false;
			$err = '';

				if (function_exists('wc_get_product')) {
					$product = wc_get_product($product_id);
					if ($product && is_object($product)) {
						try {
							if (function_exists('vms_ticketing_v2_push_inventory_write_context')) {
								vms_ticketing_v2_push_inventory_write_context(array(
									'trigger_source' => 'manual_action',
									'source_function' => 'vms_tec_cancel_event_adapter',
									'derivation_source' => 'authoritative_zero_capacity',
									'confidence_level' => 'authoritative',
									'expected_effect' => 'close',
									'reason_text' => __('Cancellation closeout intentionally hid this Woo event product and forced stock to 0 / outofstock.', 'backstage-venue-manager'),
									'writer_branch' => 'cancellation_closeout',
									'result_health' => 'expected_closed_state',
								));
							} elseif (function_exists('vms_ticket_mutation_audit_push_context')) {
								vms_ticket_mutation_audit_push_context(array(
									'trigger_source' => 'manual_action',
									'source_function' => 'vms_tec_cancel_event_adapter',
									'derivation_source' => 'authoritative_zero_capacity',
									'confidence_level' => 'authoritative',
									'expected_effect' => 'close',
									'reason_text' => __('Cancellation closeout intentionally hid this Woo event product and forced stock to 0 / outofstock.', 'backstage-venue-manager'),
									'writer_branch' => 'cancellation_closeout',
									'result_health' => 'expected_closed_state',
								));
							}
							if (method_exists($product, 'set_catalog_visibility')) {
								$product->set_catalog_visibility('hidden');
							}
							if (method_exists($product, 'set_stock_status')) {
								$product->set_stock_status('outofstock');
						}
						if (method_exists($product, 'set_manage_stock')) {
							$product->set_manage_stock(true);
						}
							if (method_exists($product, 'set_stock_quantity')) {
								$product->set_stock_quantity(0);
							}
							$product->save();
							$ok = true;
						} catch (Throwable $e) {
							$err = $e->getMessage();
						} finally {
							if (function_exists('vms_ticketing_v2_pop_inventory_write_context')) {
								vms_ticketing_v2_pop_inventory_write_context();
							} elseif (function_exists('vms_ticket_mutation_audit_pop_context')) {
								vms_ticket_mutation_audit_pop_context();
							}
						}
					} else {
					$err = 'product_not_found';
				}
			} else {
				// Fallback when Woo object layer is unavailable: draft the product post.
				$updated = wp_update_post(array(
					'ID' => $product_id,
					'post_status' => 'draft',
				), true);
				if (is_wp_error($updated)) {
					$err = $updated->get_error_message();
				} else {
					$ok = true;
				}
			}

			if ($ok) {
				$data['updated_products'][] = $product_id;
			} else {
				$data['failed_products'][] = array(
					'product_id' => $product_id,
					'error' => (string) $err,
				);
			}
		}

				}


		// Also disable any TEC RSVP tickets (these are not Woo products).
		// RSVPs are stored as custom posts (default post type 'tribe_rsvp_tickets') related via meta key '_tribe_rsvp_for_event'.
		$found_rsvp_ids = array();
		$rsvp_post_type = 'tribe_rsvp_tickets';
		$rsvp_event_key = '_tribe_rsvp_for_event';

		// Preferred: ask Event Tickets for all event tickets, then filter to RSVP ticket posts.
		if (class_exists('Tribe__Tickets__Tickets') && method_exists('Tribe__Tickets__Tickets', 'get_event_tickets')) {
			try {
				$all_tickets = (array) Tribe__Tickets__Tickets::get_event_tickets($tec_event_id);
			} catch (Throwable $e) {
				$all_tickets = array();
			}

			foreach ($all_tickets as $t) {
				if (!is_object($t)) {
					continue;
				}
				$ticket_id = isset($t->ID) ? absint($t->ID) : 0;
				if ($ticket_id <= 0) {
					continue;
				}

				// Most reliable: the ticket CPT itself.
				$pt = get_post_type($ticket_id);
				if ($pt === $rsvp_post_type) {
					$found_rsvp_ids[] = $ticket_id;
					continue;
				}

				// Secondary: provider class markers.
				$provider_class = '';
				if (isset($t->provider_class)) {
					$provider_class = (string) $t->provider_class;
				} elseif (method_exists($t, 'provider_class')) {
					try {
						$provider_class = (string) $t->provider_class();
					} catch (Throwable $e) {
						$provider_class = '';
					}
				}
				if ($provider_class !== '' && stripos($provider_class, 'Tickets__RSVP') !== false) {
					$found_rsvp_ids[] = $ticket_id;
				}
			}
		}

		// Fallback: query RSVP ticket CPTs directly by post_parent and/or the RSVP event meta key.
		if (empty($found_rsvp_ids)) {
			$by_parent = (array) get_posts(array(
				'post_type' => $rsvp_post_type,
				'posts_per_page' => -1,
				'post_status' => array('publish', 'future', 'draft', 'pending', 'private'),
				'post_parent' => $tec_event_id,
				'fields' => 'ids',
			));
			$by_meta = (array) get_posts(array(
				'post_type' => $rsvp_post_type,
				'posts_per_page' => -1,
				'post_status' => array('publish', 'future', 'draft', 'pending', 'private'),
				'fields' => 'ids',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- When the provider API yields no RSVP tickets, cancellation fallback intentionally retrieves every RSVP ticket ID for the exact TEC event before disabling it.
					array(
						'key' => $rsvp_event_key,
						'value' => (string) $tec_event_id,
						'compare' => '=',
					),
				),
			));
			$found_rsvp_ids = array_merge($by_parent, $by_meta);
		}

		$found_rsvp_ids = array_values(array_unique(array_filter(array_map('absint', (array) $found_rsvp_ids))));

		foreach ($found_rsvp_ids as $rsvp_ticket_id) {
			$updated = wp_update_post(array(
				'ID' => $rsvp_ticket_id,
				'post_status' => 'draft',
			), true);
			if (is_wp_error($updated)) {
				$data['failed_rsvp_tickets'][] = array(
					'ticket_id' => $rsvp_ticket_id,
					'error' => (string) $updated->get_error_message(),
				);
			} else {
				$data['rsvp_tickets_disabled'][] = $rsvp_ticket_id;
			}
		}
if (!empty($data['failed_products']) || !empty($data['failed_rsvp_tickets'])) {
			return array(
				'status' => 'failed',
				'message' => 'sales_stop_partial_failure',
				'data' => $data,
			);
		}

		return array(
			'status' => 'done',
			'message' => 'sales_stopped',
			'data' => $data,
		);
	}

	if ($step_key !== 'refund_discovery') {
		return $result;
	}

	$refund_policies = array(
		'stop_sales_queue_refunds',
		'stop_sales_auto_refund',
		'stop_sales_auto_refund_remove_attendees',
	);
	if (!in_array($policy, $refund_policies, true)) {
		return array(
			'status' => 'done',
			'message' => 'skipped_by_policy',
			'data' => array(),
		);
	}

	if (!function_exists('wc_get_orders') || !function_exists('wc_get_order')) {
		return array(
			'status' => 'blocked',
			'message' => 'woo_unavailable',
			'data' => array('event_plan_id' => $event_plan_id),
		);
	}

	$k_tec_event_id = function_exists('bvmgr_meta_key')
		? (bvmgr_meta_key('event_plan', 'tec_event_id') ?: '_vms_tec_event_id')
		: '_vms_tec_event_id';
	$tec_event_id = (int) get_post_meta($event_plan_id, $k_tec_event_id, true);
	if ($tec_event_id <= 0) {
		return array(
			'status' => 'blocked',
			'message' => 'missing_tec_event_link',
			'data' => array('event_plan_id' => $event_plan_id, 'tec_event_id' => 0),
		);
	}

	$ticket_product_ids = function_exists('bvmgr_get_ticket_product_ids_for_event')
		? array_values(array_unique(array_filter(array_map('absint', (array) bvmgr_get_ticket_product_ids_for_event($tec_event_id)))))
		: array();
	$product_ids = function_exists('vms_cancellation_get_event_refundable_product_ids')
		? array_values(array_unique(array_filter(array_map('absint', (array) vms_cancellation_get_event_refundable_product_ids($event_plan_id, $tec_event_id)))))
		: $ticket_product_ids;
	if (empty($product_ids)) {
		return array(
			'status' => 'done',
			'message' => 'no_refundable_event_products_found',
			'data' => array(
				'event_plan_id' => $event_plan_id,
				'tec_event_id' => $tec_event_id,
				'ticket_product_ids' => $ticket_product_ids,
				'refundable_product_ids' => array(),
				'candidates' => array(),
			),
		);
	}

	$scan_limit = (int) apply_filters('vms_cancellation_refund_discovery_scan_limit', 500, $event_plan_id, $policy);
	if ($scan_limit < 1) {
		$scan_limit = 500;
	}
	$scan_limit = min($scan_limit, 2000);
	$per_page = 100;
	$page = 1;
	$pages_scanned = 0;
	$scanned = 0;
	$scan_limit_reached = false;
	$product_lookup = array_fill_keys($product_ids, true);
	$candidates_by_order = array();

	$paid_statuses = function_exists('wc_get_is_paid_statuses')
		? (array) wc_get_is_paid_statuses()
		: array('processing', 'completed');
	if (!in_array('on-hold', $paid_statuses, true)) {
		$paid_statuses[] = 'on-hold';
	}

	while ($scanned < $scan_limit) {
		$orders = wc_get_orders(array(
			'type' => 'shop_order',
			'limit' => $per_page,
			'page' => $page,
			'paginate' => false,
			'status' => $paid_statuses,
			'orderby' => 'date',
			'order' => 'DESC',
		));

		if (empty($orders) || !is_array($orders)) {
			break;
		}
		$pages_scanned++;

		foreach ($orders as $order) {
			if (!is_object($order) || !method_exists($order, 'get_id') || !method_exists($order, 'get_items')) {
				continue;
			}
			$scanned++;
			if ($scanned > $scan_limit) {
				$scanned = $scan_limit;
				$scan_limit_reached = true;
				break 2;
			}

			$order_id = (int) $order->get_id();
			if ($order_id <= 0) {
				continue;
			}

			foreach ($order->get_items('line_item') as $item_id => $item) {
				if (!is_object($item) || !method_exists($item, 'get_product_id') || !method_exists($item, 'get_quantity')) {
					continue;
				}

				$match = function_exists('vms_cancellation_refund_match_order_item')
					? (array) vms_cancellation_refund_match_order_item($item, $event_plan_id, $tec_event_id, $product_lookup)
					: array('matched' => false, 'source' => '', 'product_id' => (int) $item->get_product_id(), 'product_role' => '');
				if (empty($match['matched'])) {
					continue;
				}

				$product_id = absint($match['product_id'] ?? 0);
				if ($product_id <= 0) {
					$product_id = (int) $item->get_product_id();
				}

				$qty = (float) $item->get_quantity();
				$qty_refunded = (float) $order->get_qty_refunded_for_item($item_id);
				$refundable_qty = max(0.0, $qty - abs($qty_refunded));
				if ($refundable_qty <= 0) {
					continue;
				}

				$line_subtotal = (float) $item->get_total();
				$line_tax_total = (float) $item->get_total_tax();
				$line_total = $line_subtotal + $line_tax_total;
				$line_taxes = array();
				if (method_exists($item, 'get_taxes')) {
					$taxes = $item->get_taxes();
					$taxes = is_array($taxes) && isset($taxes['total']) && is_array($taxes['total']) ? $taxes['total'] : array();
					foreach ($taxes as $tax_id => $tax_amount) {
						$tax_amount = (float) $tax_amount;
						if ($tax_amount <= 0) {
							continue;
						}
						$line_taxes[(string) $tax_id] = $tax_amount;
					}
				}

				if (!isset($candidates_by_order[$order_id])) {
					$candidates_by_order[$order_id] = array(
						'order_id' => $order_id,
						'order_number' => method_exists($order, 'get_order_number') ? (string) $order->get_order_number() : (string) $order_id,
						'currency' => method_exists($order, 'get_currency') ? (string) $order->get_currency() : '',
						'status' => (string) $order->get_status(),
						'payment_method' => method_exists($order, 'get_payment_method') ? (string) $order->get_payment_method() : '',
						'payment_method_title' => method_exists($order, 'get_payment_method_title') ? (string) $order->get_payment_method_title() : '',
						'total' => (float) $order->get_total(),
						'total_refunded' => (float) $order->get_total_refunded(),
						'line_items' => array(),
					);
				}

				$candidates_by_order[$order_id]['line_items'][] = array(
					'item_id' => (int) $item_id,
					'product_id' => $product_id,
					'product_role' => sanitize_key((string) ($match['product_role'] ?? '')),
					'match_source' => sanitize_key((string) ($match['source'] ?? '')),
					'name' => (string) $item->get_name(),
					'qty' => (float) $qty,
					'qty_refunded' => (float) abs($qty_refunded),
					'refundable_qty' => (float) $refundable_qty,
					'line_subtotal' => (float) $line_subtotal,
					'line_tax_total' => (float) $line_tax_total,
					'line_total' => (float) $line_total,
					'taxes' => $line_taxes,
				);
			}
		}

		$page++;
	}

	$candidates = array_values($candidates_by_order);
	$price_decimals = function_exists('wc_get_price_decimals') ? max(0, (int) wc_get_price_decimals()) : 2;
	$eligible_count = 0;
	$manual_review_count = 0;
	foreach ($candidates as $index => $candidate) {
		if (!is_array($candidate)) {
			continue;
		}

		$order_id = absint($candidate['order_id'] ?? 0);
		$remaining_total = max(0.0, ((float) ($candidate['total'] ?? 0.0)) - ((float) ($candidate['total_refunded'] ?? 0.0)));
		$estimated_refund_total = 0.0;
		$line_items = isset($candidate['line_items']) && is_array($candidate['line_items']) ? $candidate['line_items'] : array();
		foreach ($line_items as $line_index => $line) {
			if (!is_array($line)) {
				continue;
			}
			$qty = (float) ($line['qty'] ?? 0.0);
			$refundable_qty = (float) ($line['refundable_qty'] ?? 0.0);
			$qty_ratio = ($qty > 0) ? min(1.0, max(0.0, $refundable_qty / $qty)) : 0.0;
			$line_subtotal = (float) ($line['line_subtotal'] ?? 0.0);
			$line_tax_total = (float) ($line['line_tax_total'] ?? 0.0);
			$line_estimated_refund_total = round(($line_subtotal + $line_tax_total) * $qty_ratio, $price_decimals);
			$candidates[$index]['line_items'][$line_index]['estimated_refund_total'] = (float) $line_estimated_refund_total;
			$estimated_refund_total += $line_estimated_refund_total;
		}
		$estimated_refund_total = min(round($estimated_refund_total, $price_decimals), round($remaining_total, $price_decimals));
		$candidates[$index]['estimated_refund_total'] = (float) $estimated_refund_total;

		$order = $order_id > 0 ? wc_get_order($order_id) : null;
		$can_refund = function_exists('wc_can_refund_order')
			? (bool) ($order ? wc_can_refund_order($order) : false)
			: ($remaining_total > 0);
		$gateway_supports_refunds = null;
		if ($order && function_exists('wc_get_payment_gateway_by_order')) {
			$gateway = wc_get_payment_gateway_by_order($order);
			if ($gateway && is_object($gateway) && method_exists($gateway, 'supports')) {
				$gateway_supports_refunds = (bool) $gateway->supports('refunds');
			}
		}

		$manual_review_reason = '';
		if ($estimated_refund_total <= 0) {
			$manual_review_reason = 'no_refundable_amount';
		} elseif (!$can_refund) {
			$manual_review_reason = 'order_not_auto_refundable';
		} elseif ($gateway_supports_refunds === false) {
			$manual_review_reason = 'gateway_does_not_support_refunds';
		}

		$candidates[$index]['can_refund'] = (bool) $can_refund;
		$candidates[$index]['gateway_supports_refunds'] = $gateway_supports_refunds;
		$candidates[$index]['auto_refund_eligible'] = ($manual_review_reason === '');
		if ($manual_review_reason !== '') {
			$candidates[$index]['manual_review_reason'] = $manual_review_reason;
			$manual_review_count++;
		} else {
			$eligible_count++;
		}
	}
	$data = array(
		'event_plan_id' => $event_plan_id,
		'tec_event_id' => $tec_event_id,
		'policy' => $policy,
		'ticket_product_ids' => $ticket_product_ids,
		'refundable_product_ids' => $product_ids,
		'orders_scanned' => $scanned,
		'pages_scanned' => $pages_scanned,
		'scan_limit' => $scan_limit,
		'scan_limit_reached' => $scan_limit_reached,
		'candidate_order_count' => count($candidates),
		'auto_refund_eligible_count' => $eligible_count,
		'manual_review_count' => $manual_review_count,
		'candidates' => $candidates,
	);
	if ($scan_limit_reached) {
		$data['requires_operator_review'] = true;
		return array(
			'status' => 'blocked',
			'message' => 'refund_discovery_scan_limit_reached',
			'data' => $data,
		);
	}

	if (empty($candidates)) {
		return array(
			'status' => 'done',
			'message' => 'no_refund_candidates_found',
			'data' => $data,
		);
	}

	return array(
		'status' => 'done',
		'message' => 'refund_candidates_discovered',
		'data' => $data,
	);
}, 10, 5);

add_filter('vms_cancellation_run_step', function ($result, $event_plan_id, $policy, $step_key, $summary) {
	$event_plan_id = absint($event_plan_id);
	$policy = sanitize_key((string) $policy);
	$step_key = sanitize_key((string) $step_key);

	if ($event_plan_id <= 0 || $step_key !== 'refund_execution') {
		return $result;
	}

	$queue_only_policies = array('stop_sales_queue_refunds');
	$auto_policies = array('stop_sales_auto_refund', 'stop_sales_auto_refund_remove_attendees');
	if (!in_array($policy, array_merge($queue_only_policies, $auto_policies), true)) {
		return array(
			'status' => 'done',
			'message' => 'skipped_by_policy',
			'data' => array(),
		);
	}

	$steps = isset($summary['steps']) && is_array($summary['steps']) ? $summary['steps'] : array();
	$discovery = null;
	$discovery_status = '';
	$discovery_message = '';
	foreach ($steps as $step) {
		if (!is_array($step)) {
			continue;
		}
		if (sanitize_key((string) ($step['key'] ?? '')) !== 'refund_discovery') {
			continue;
		}
		$discovery_status = sanitize_key((string) ($step['status'] ?? 'pending'));
		$discovery_message = sanitize_text_field((string) ($step['message'] ?? ''));
		$discovery = isset($step['data']) && is_array($step['data']) ? $step['data'] : array();
		break;
	}

	if ($discovery_status === '') {
		return array(
			'status' => 'blocked',
			'message' => 'refund_discovery_step_missing',
			'data' => array(
				'event_plan_id' => $event_plan_id,
				'policy' => $policy,
				'requires_operator_review' => true,
			),
		);
	}

	if ($discovery_status !== 'done') {
		return array(
			'status' => 'blocked',
			'message' => 'refund_discovery_not_completed',
			'data' => array(
				'event_plan_id' => $event_plan_id,
				'policy' => $policy,
				'discovery_status' => $discovery_status,
				'discovery_message' => $discovery_message,
				'requires_operator_review' => true,
			),
		);
	}

	$candidates = isset($discovery['candidates']) && is_array($discovery['candidates']) ? $discovery['candidates'] : array();
	if (empty($candidates)) {
		return array(
			'status' => 'done',
			'message' => 'no_refunds_to_execute',
			'data' => array(
				'event_plan_id' => $event_plan_id,
				'policy' => $policy,
				'refunds_created' => array(),
				'failed_orders' => array(),
				'skipped_orders' => array(),
			),
		);
	}

	$data = array(
		'event_plan_id' => $event_plan_id,
		'policy' => $policy,
		'candidate_order_count' => count($candidates),
		'refunds_created' => array(),
		'failed_orders' => array(),
		'skipped_orders' => array(),
		'queued_orders' => array(),
	);

	if (in_array($policy, $queue_only_policies, true)) {
		foreach ($candidates as $cand) {
			if (!is_array($cand)) {
				continue;
			}
			$order_id = absint($cand['order_id'] ?? 0);
			if ($order_id <= 0) {
				continue;
			}
			$data['queued_orders'][] = array(
				'order_id' => $order_id,
				'order_number' => (string) ($cand['order_number'] ?? (string) $order_id),
				'line_item_count' => is_array($cand['line_items'] ?? null) ? count((array) $cand['line_items']) : 0,
			);
		}
		$data['requires_operator_review'] = true;

		return array(
			'status' => 'done',
			'message' => 'refunds_queued_manual_review',
			'data' => $data,
		);
	}

	$guard = function_exists('vms_cancellation_auto_refund_guard')
		? (array) vms_cancellation_auto_refund_guard($event_plan_id, $policy, $summary, array('user_id' => get_current_user_id()))
		: array(
			'allowed' => true,
			'dry_run' => false,
			'reason' => 'guard_unavailable',
			'environment' => function_exists('wp_get_environment_type') ? (string) wp_get_environment_type() : 'production',
			'required_capability' => '',
			'has_capability' => true,
			'allow_non_dev' => true,
			'user_id' => absint(get_current_user_id()),
		);
	$guard_allowed = !empty($guard['allowed']);
	$guard_dry_run = !empty($guard['dry_run']);
	$data['auto_refund_guard'] = $guard;

	if (!$guard_allowed && !$guard_dry_run) {
		$data['requires_operator_review'] = true;
		return array(
			'status' => 'blocked',
			'message' => 'auto_refund_capability_guard_blocked',
			'data' => $data,
		);
	}

	if ($guard_dry_run) {
		foreach ($candidates as $cand) {
			if (!is_array($cand)) {
				continue;
			}
			$order_id = absint($cand['order_id'] ?? 0);
			if ($order_id <= 0) {
				continue;
			}
			$data['queued_orders'][] = array(
				'order_id' => $order_id,
				'order_number' => (string) ($cand['order_number'] ?? (string) $order_id),
				'line_item_count' => is_array($cand['line_items'] ?? null) ? count((array) $cand['line_items']) : 0,
				'reason' => 'auto_refund_dry_run_guard',
			);
		}
		$data['requires_operator_review'] = true;
		return array(
			'status' => 'done',
			'message' => 'auto_refund_dry_run_queued_for_review',
			'data' => $data,
		);
	}

	if (!function_exists('wc_get_order') || !function_exists('wc_create_refund')) {
		return array(
			'status' => 'blocked',
			'message' => 'woo_refund_api_unavailable',
			'data' => $data + array('requires_operator_review' => true),
		);
	}

	$job_id = sanitize_text_field((string) ($summary['job_id'] ?? ''));
	$price_decimals = function_exists('wc_get_price_decimals') ? max(0, (int) wc_get_price_decimals()) : 2;
	foreach ($candidates as $cand) {
		if (!is_array($cand)) {
			continue;
		}

		$order_id = absint($cand['order_id'] ?? 0);
		if ($order_id <= 0) {
			continue;
		}

		if (isset($cand['auto_refund_eligible']) && empty($cand['auto_refund_eligible'])) {
			$data['queued_orders'][] = array(
				'order_id' => $order_id,
				'order_number' => (string) ($cand['order_number'] ?? (string) $order_id),
				'line_item_count' => is_array($cand['line_items'] ?? null) ? count((array) $cand['line_items']) : 0,
				'reason' => (string) ($cand['manual_review_reason'] ?? 'manual_review_required'),
			);
			continue;
		}

		$order = wc_get_order($order_id);
		if (!$order || !is_object($order) || !method_exists($order, 'get_total') || !method_exists($order, 'get_total_refunded')) {
			$data['failed_orders'][] = array(
				'order_id' => $order_id,
				'error' => 'order_not_found',
			);
			continue;
		}

		$marker_key = '_vms_cancel_refund_' . ($job_id !== '' ? md5($job_id) : md5((string) $event_plan_id));
		$existing_refund_marker = metadata_exists('post', $order_id, $marker_key)
			? get_post_meta($order_id, $marker_key, true)
			: null;

		if (function_exists('wc_can_refund_order') && !wc_can_refund_order($order)) {
			$data['queued_orders'][] = array(
				'order_id' => $order_id,
				'order_number' => (string) ($cand['order_number'] ?? (string) $order_id),
				'line_item_count' => is_array($cand['line_items'] ?? null) ? count((array) $cand['line_items']) : 0,
				'reason' => 'order_not_auto_refundable',
			);
			continue;
		}

		$line_items = isset($cand['line_items']) && is_array($cand['line_items']) ? $cand['line_items'] : array();
		$refund_lines = array();
		$refund_amount = 0.0;

		foreach ($line_items as $line) {
			if (!is_array($line)) {
				continue;
			}
			$item_id = absint($line['item_id'] ?? 0);
			$qty = (float) ($line['qty'] ?? 0.0);
			$refundable_qty_raw = (float) ($line['refundable_qty'] ?? 0.0);
			$qty_to_refund = min((int) round($qty), max(0, (int) floor($refundable_qty_raw + 0.00001)));
			if (method_exists($order, 'get_qty_refunded_for_item')) {
				$live_qty_refunded = (float) $order->get_qty_refunded_for_item($item_id);
				$live_refundable_qty = max(0.0, $qty - abs($live_qty_refunded));
				$qty_to_refund = min($qty_to_refund, max(0, (int) floor($live_refundable_qty + 0.00001)));
			}
			$line_subtotal = (float) ($line['line_subtotal'] ?? 0.0);
			$line_tax_total = (float) ($line['line_tax_total'] ?? 0.0);
			if ($item_id <= 0 || $qty <= 0 || $qty_to_refund <= 0 || ($line_subtotal + $line_tax_total) <= 0) {
				continue;
			}

			$qty_ratio = min(1.0, max(0.0, $qty_to_refund / $qty));
			$line_refund_subtotal = round($line_subtotal * $qty_ratio, $price_decimals);
			$line_tax_map = array();
			$line_refund_tax_total = 0.0;
			$taxes = isset($line['taxes']) && is_array($line['taxes']) ? $line['taxes'] : array();
			foreach ($taxes as $tax_id => $tax_amount) {
				$tax_amount = (float) $tax_amount;
				if ($tax_amount <= 0) {
					continue;
				}
				$refund_tax_amount = round($tax_amount * $qty_ratio, $price_decimals);
				if ($refund_tax_amount <= 0) {
					continue;
				}
				$line_tax_map[(string) $tax_id] = $refund_tax_amount;
				$line_refund_tax_total += $refund_tax_amount;
			}

			if ($line_refund_subtotal <= 0 && $line_refund_tax_total <= 0) {
				continue;
			}

			$refund_lines[$item_id] = array(
				'qty' => $qty_to_refund,
				'refund_total' => (float) $line_refund_subtotal,
			);
			if (!empty($line_tax_map)) {
				$refund_lines[$item_id]['refund_tax'] = $line_tax_map;
			}
			$refund_amount += $line_refund_subtotal + $line_refund_tax_total;
		}

		$remaining_total = max(0.0, ((float) $order->get_total()) - ((float) $order->get_total_refunded()));
		$refund_amount = min(round($refund_amount, $price_decimals), round($remaining_total, $price_decimals));

		if ($refund_amount <= 0 || empty($refund_lines)) {
			$data['skipped_orders'][] = array(
				'order_id' => $order_id,
				'reason' => 'no_refundable_amount',
			);
			continue;
		}

		$reason = sprintf('Backstage Venue Manager cancellation refund for Event Plan #%d', $event_plan_id);
		$refund = wc_create_refund(array(
			'order_id' => $order_id,
			'amount' => $refund_amount,
			'reason' => $reason,
			'line_items' => $refund_lines,
			'refund_payment' => true,
			'restock_items' => false,
		));

		if (is_wp_error($refund)) {
			$data['failed_orders'][] = array(
				'order_id' => $order_id,
				'error' => (string) $refund->get_error_message(),
			);
			continue;
		}

		$refund_id = is_object($refund) && method_exists($refund, 'get_id') ? absint($refund->get_id()) : 0;
		update_post_meta($order_id, $marker_key, array(
			'event_plan_id' => $event_plan_id,
			'job_id' => $job_id,
			'refund_id' => $refund_id,
			'amount' => $refund_amount,
			'line_item_ids' => array_values(array_map('absint', array_keys($refund_lines))),
			'previous_marker_found' => !empty($existing_refund_marker),
			'at_gmt' => gmdate('Y-m-d H:i:s'),
		));

		$data['refunds_created'][] = array(
			'order_id' => $order_id,
			'refund_id' => $refund_id,
			'amount' => $refund_amount,
			'line_item_ids' => array_values(array_map('absint', array_keys($refund_lines))),
			'previous_marker_found' => !empty($existing_refund_marker),
		);
	}

	if (!empty($data['queued_orders'])) {
		$data['requires_operator_review'] = true;
	}

	if (!empty($data['failed_orders'])) {
		$data['requires_operator_review'] = true;
		return array(
			'status' => 'failed',
			'message' => 'refund_execution_partial_failure',
			'data' => $data,
		);
	}

	return array(
		'status' => 'done',
		'message' => 'refunds_executed',
		'data' => $data,
	);
}, 10, 5);

if (!function_exists('vms_cancellation_notification_kind_label')) {
	function vms_cancellation_notification_kind_label(string $kind): string
	{
		$kind = sanitize_key($kind);
		switch ($kind) {
			case 'vendor_primary':
				return __('Primary Vendor', 'backstage-venue-manager');
			case 'vendor_secondary':
				return __('Secondary Vendor', 'backstage-venue-manager');
			case 'vendor_lineup':
			case 'assigned_vendor':
				return __('Vendor', 'backstage-venue-manager');
			case 'staff':
				return __('Staff', 'backstage-venue-manager');
			default:
				return __('Recipient', 'backstage-venue-manager');
		}
	}
}

if (!function_exists('vms_cancellation_notification_kind_group')) {
	function vms_cancellation_notification_kind_group(string $kind): string
	{
		$kind = sanitize_key($kind);
		if ($kind === 'vendor_secondary') {
			return 'secondary_vendor';
		}
		if ($kind === 'staff') {
			return 'staff';
		}
		if (strpos($kind, 'vendor_') === 0 || $kind === 'assigned_vendor') {
			return 'vendor';
		}
		return 'other';
	}
}

if (!function_exists('vms_cancellation_collect_modern_staff_assignment_map')) {
	function vms_cancellation_collect_modern_staff_assignment_map(int $event_plan_id): array
	{
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0 || !function_exists('bvmgr_staffing_get_event_slots')) {
			return array();
		}

		$assigned = array();
		$slots = (array) bvmgr_staffing_get_event_slots($event_plan_id, true);
		foreach ($slots as $slot_row) {
			if (!is_array($slot_row)) {
				continue;
			}
			$role_id = isset($slot_row['role_id']) ? absint($slot_row['role_id']) : 0;
			if ($role_id <= 0) {
				continue;
			}
			$assignment_rows = isset($slot_row['assignments']) && is_array($slot_row['assignments']) ? $slot_row['assignments'] : array();
			foreach ($assignment_rows as $assignment_row) {
				if (!is_array($assignment_row)) {
					continue;
				}
				$status = isset($assignment_row['status']) ? sanitize_key((string) $assignment_row['status']) : '';
				if (!in_array($status, array('proposed', 'confirmed'), true)) {
					continue;
				}
				$staff_id = isset($assignment_row['staff_id']) ? absint($assignment_row['staff_id']) : 0;
				if ($staff_id <= 0) {
					continue;
				}
				if (!isset($assigned[$role_id])) {
					$assigned[$role_id] = array();
				}
				$assigned[$role_id][] = $staff_id;
			}
		}

		foreach ($assigned as $role_id => $staff_ids) {
			$assigned[$role_id] = array_values(array_unique(array_filter(array_map('absint', (array) $staff_ids))));
		}

		return $assigned;
	}
}

if (!function_exists('vms_cancellation_collect_legacy_staff_assignment_map')) {
	function vms_cancellation_collect_legacy_staff_assignment_map(int $event_plan_id): array
	{
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0) {
			return array();
		}

		$legacy = get_post_meta($event_plan_id, '_vms_staff_assignments', true);
		if (!is_array($legacy)) {
			return array();
		}

		$assigned = array();
		foreach ($legacy as $role_id => $staff_ids) {
			$role_id = absint($role_id);
			if ($role_id <= 0 || !is_array($staff_ids)) {
				continue;
			}
			$assigned[$role_id] = array_values(array_unique(array_filter(array_map('absint', $staff_ids))));
		}

		return $assigned;
	}
}

if (!function_exists('vms_cancellation_resolve_staff_notification_recipient')) {
	function vms_cancellation_resolve_staff_notification_recipient(int $staff_id): array
	{
		$staff_id = absint($staff_id);
		if ($staff_id <= 0) {
			return array(
				'staff_id' => 0,
				'post_id' => 0,
				'user_id' => 0,
				'label' => '',
				'email' => '',
				'email_source' => '',
			);
		}

		$resolve_user_email = static function (int $user_id): array {
			$user_id = absint($user_id);
			if ($user_id <= 0) {
				return array(
					'user' => null,
					'user_id' => 0,
					'email' => '',
				);
			}

			$user = get_user_by('id', $user_id);
			if (!($user instanceof WP_User)) {
				return array(
					'user' => null,
					'user_id' => 0,
					'email' => '',
				);
			}

			$email = sanitize_email((string) $user->user_email);
			if ($email === '' || !is_email($email)) {
				return array(
					'user' => $user,
					'user_id' => absint($user->ID),
					'email' => '',
				);
			}

			return array(
				'user' => $user,
				'user_id' => absint($user->ID),
				'email' => $email,
			);
		};

		$linked_user_id = absint(get_post_meta($staff_id, '_vms_linked_user_id', true));
		$linked_user = $resolve_user_email($linked_user_id);

		$meta_user = array(
			'user' => null,
			'user_id' => 0,
			'email' => '',
		);
		if ($linked_user['email'] === '') {
			$user_ids = get_users(array(
				'meta_key' => '_vms_staff_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Staff notification resolution performs one single-user fallback by the canonical Staff-link meta key only when the direct link has no usable email.
				'meta_value' => (string) $staff_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Staff notification resolution performs one single-user fallback by the exact Staff ID only when the direct link has no usable email.
				'number' => 1,
				'fields' => 'ids',
			));
			$fallback_user_id = !empty($user_ids) ? absint($user_ids[0]) : 0;
			$meta_user = $resolve_user_email($fallback_user_id);
		}

		$user = ($linked_user['user'] instanceof WP_User)
			? $linked_user['user']
			: (($meta_user['user'] instanceof WP_User) ? $meta_user['user'] : null);
		$user_id = ($user instanceof WP_User) ? absint($user->ID) : 0;
		$post_title = trim((string) get_the_title($staff_id));
		$user_label = ($user instanceof WP_User) ? trim((string) $user->display_name) : '';
		$label = $user_label !== '' ? $user_label : $post_title;
		if ($label === '') {
			/* translators: %d: staff ID. */
			$label = sprintf(__('Staff #%d', 'backstage-venue-manager'), $staff_id);
		}

		$candidates = array(
			'linked_user_meta' => (string) ($linked_user['email'] ?? ''),
			'user_staff_meta' => (string) ($meta_user['email'] ?? ''),
			'staff_contact_email' => (string) get_post_meta($staff_id, '_vms_contact_email', true),
			'staff_primary_email' => (string) get_post_meta($staff_id, '_vms_vendor_primary_email', true),
			'staff_legacy_email' => (string) get_post_meta($staff_id, '_vms_vendor_email', true),
		);

		$email = '';
		$email_source = '';
		foreach ($candidates as $source => $email_raw) {
			$email_candidate = sanitize_email((string) $email_raw);
			if ($email_candidate !== '' && is_email($email_candidate)) {
				$email = $email_candidate;
				$email_source = sanitize_key((string) $source);
				break;
			}
		}

		return array(
			'staff_id' => $staff_id,
			'post_id' => $staff_id,
			'user_id' => $user_id,
			'label' => $label,
			'email' => $email,
			'email_source' => $email_source,
		);
	}
}

add_filter('vms_cancellation_run_step', function ($result, $event_plan_id, $policy, $step_key, $summary) {
	$event_plan_id = absint($event_plan_id);
	$policy = sanitize_key((string) $policy);
	$step_key = sanitize_key((string) $step_key);

	if ($event_plan_id <= 0 || $step_key !== 'notifications') {
		return $result;
	}

	$data = array(
		'event_plan_id' => $event_plan_id,
		'policy' => $policy,
		'recipients' => array(),
		'sent' => array(),
		'failed' => array(),
		'skipped' => array(),
		'staff_assignment_source' => 'none',
		'staff_assignment_map' => array(),
	);

	$recipients = array();
	$recipients_by_email = array();
	$skipped_row_index = array();
	$discoverable_missing_email_count = 0;
	$group_labels = array(
		'vendor' => __('Vendors', 'backstage-venue-manager'),
		'secondary_vendor' => __('Secondary vendors', 'backstage-venue-manager'),
		'staff' => __('Staff', 'backstage-venue-manager'),
		'other' => __('Other recipients', 'backstage-venue-manager'),
	);

	$build_row = static function (string $kind, int $entity_id, string $label, string $email, array $extra = array()): array {
		$kind = sanitize_key($kind);
		$entity_id = absint($entity_id);
		$label = trim(sanitize_text_field($label));
		$email = sanitize_email($email);
		$group = isset($extra['group']) ? sanitize_key((string) $extra['group']) : '';
		if ($group === '') {
			$group = function_exists('vms_cancellation_notification_kind_group')
				? vms_cancellation_notification_kind_group($kind)
				: 'other';
		}
		$kind_label = isset($extra['kind_label']) ? sanitize_text_field((string) $extra['kind_label']) : '';
		if ($kind_label === '') {
			$kind_label = function_exists('vms_cancellation_notification_kind_label')
				? vms_cancellation_notification_kind_label($kind)
				: __('Recipient', 'backstage-venue-manager');
		}
		$recipient_type = isset($extra['recipient_type']) ? sanitize_key((string) $extra['recipient_type']) : '';
		if ($recipient_type === '') {
			$recipient_type = ($kind === 'staff') ? 'staff' : (($group === 'vendor' || $group === 'secondary_vendor') ? 'vendor' : 'recipient');
		}
		$role_ids = isset($extra['role_ids']) && is_array($extra['role_ids'])
			? array_values(array_unique(array_filter(array_map('absint', $extra['role_ids']))))
			: array();
		$groups = isset($extra['groups']) && is_array($extra['groups'])
			? array_values(array_unique(array_filter(array_map('sanitize_key', $extra['groups']))))
			: array();
		if (empty($groups) && $group !== '') {
			$groups[] = $group;
		}

		return array(
			'email' => $email,
			'kind' => $kind,
			'kind_label' => $kind_label,
			'group' => $group,
			'groups' => $groups,
			'recipient_type' => $recipient_type,
			'entity_id' => $entity_id,
			'label' => $label,
			'display_name' => $label,
			'vendor_id' => isset($extra['vendor_id']) ? absint($extra['vendor_id']) : 0,
			'staff_id' => isset($extra['staff_id']) ? absint($extra['staff_id']) : 0,
			'post_id' => isset($extra['post_id']) ? absint($extra['post_id']) : 0,
			'user_id' => isset($extra['user_id']) ? absint($extra['user_id']) : 0,
			'role_ids' => $role_ids,
			'staff_assignment_source' => isset($extra['staff_assignment_source']) ? sanitize_key((string) $extra['staff_assignment_source']) : '',
			'email_source' => isset($extra['email_source']) ? sanitize_key((string) $extra['email_source']) : '',
			'aliases' => isset($extra['aliases']) && is_array($extra['aliases']) ? $extra['aliases'] : array(),
		);
	};

	$append_alias = static function (array &$recipient_row, array $alias_row): void {
		$alias_key = sanitize_key((string) ($alias_row['kind'] ?? '')) . ':' . absint($alias_row['entity_id'] ?? 0);
		$primary_key = sanitize_key((string) ($recipient_row['kind'] ?? '')) . ':' . absint($recipient_row['entity_id'] ?? 0);
		if ($alias_key !== '' && $alias_key !== $primary_key) {
			$aliases = isset($recipient_row['aliases']) && is_array($recipient_row['aliases']) ? $recipient_row['aliases'] : array();
			$existing_alias_keys = array();
			foreach ($aliases as $existing_alias) {
				if (!is_array($existing_alias)) {
					continue;
				}
				$existing_alias_keys[] = sanitize_key((string) ($existing_alias['kind'] ?? '')) . ':' . absint($existing_alias['entity_id'] ?? 0);
			}
			if (!in_array($alias_key, $existing_alias_keys, true)) {
				$aliases[] = $alias_row;
				$recipient_row['aliases'] = $aliases;
			}
		}

		$groups = isset($recipient_row['groups']) && is_array($recipient_row['groups']) ? $recipient_row['groups'] : array();
		$alias_groups = isset($alias_row['groups']) && is_array($alias_row['groups']) ? $alias_row['groups'] : array();
		$alias_group = sanitize_key((string) ($alias_row['group'] ?? ''));
		if ($alias_group !== '') {
			$alias_groups[] = $alias_group;
		}
		$recipient_row['groups'] = array_values(array_unique(array_filter(array_map('sanitize_key', array_merge($groups, $alias_groups)))));

		$role_ids = isset($recipient_row['role_ids']) && is_array($recipient_row['role_ids']) ? $recipient_row['role_ids'] : array();
		$alias_role_ids = isset($alias_row['role_ids']) && is_array($alias_row['role_ids']) ? $alias_row['role_ids'] : array();
		$recipient_row['role_ids'] = array_values(array_unique(array_filter(array_map('absint', array_merge($role_ids, $alias_role_ids)))));
	};

	$record_skip = function (string $kind, int $entity_id, string $label, string $email, string $reason, array $extra = array()) use (&$data, &$skipped_row_index, &$discoverable_missing_email_count, $build_row) {
		$row = $build_row($kind, $entity_id, $label, $email, $extra);
		$row['reason'] = sanitize_key($reason);
		$skip_key = $row['email'] !== ''
			? strtolower($row['email']) . '|' . $row['reason']
			: $row['recipient_type'] . '|' . $row['entity_id'] . '|' . $row['reason'];
		if ($skip_key === '') {
			$skip_key = 'skip_' . md5(wp_json_encode($row));
		}
		if (isset($skipped_row_index[$skip_key])) {
			return;
		}
		$skipped_row_index[$skip_key] = true;
		$data['skipped'][] = $row;
		if (in_array($row['reason'], array('missing_email', 'invalid_email'), true)) {
			$discoverable_missing_email_count++;
		}
	};

	$add_recipient = function (string $kind, int $entity_id, string $label, string $email, array $extra = array()) use (&$recipients, &$recipients_by_email, $build_row, $append_alias, $record_skip) {
		$row = $build_row($kind, $entity_id, $label, $email, $extra);
		if ($row['email'] === '' || !is_email($row['email'])) {
			$record_skip(
				$kind,
				$entity_id,
				$label,
				$email,
				$row['email'] === '' ? 'missing_email' : 'invalid_email',
				$extra
			);
			return;
		}

		$key = strtolower($row['email']);
		if (isset($recipients_by_email[$key])) {
			$existing_index = absint($recipients_by_email[$key]);
			if (isset($recipients[$existing_index]) && is_array($recipients[$existing_index])) {
				$append_alias($recipients[$existing_index], $row);
				if ((string) ($recipients[$existing_index]['display_name'] ?? '') === '' && $row['display_name'] !== '') {
					$recipients[$existing_index]['display_name'] = $row['display_name'];
					$recipients[$existing_index]['label'] = $row['label'];
				}
			}
			return;
		}

		$recipients[] = $row;
		$recipients_by_email[$key] = count($recipients) - 1;
	};

	$finalize_data = function () use (&$data, &$recipients, &$discoverable_missing_email_count, $group_labels): void {
		$data['recipients'] = $recipients;
		$data['deliverable_recipient_count'] = count($recipients);
		$data['recipient_count'] = $data['deliverable_recipient_count'] + $discoverable_missing_email_count;
		$data['sent_count'] = count(isset($data['sent']) && is_array($data['sent']) ? $data['sent'] : array());
		$data['failed_count'] = count(isset($data['failed']) && is_array($data['failed']) ? $data['failed'] : array());
		$data['skipped_count'] = count(isset($data['skipped']) && is_array($data['skipped']) ? $data['skipped'] : array());

		$group_totals = array();
		foreach ($group_labels as $group_key => $label) {
			$group_totals[$group_key] = array(
				'label' => $label,
				'recipient_count' => 0,
				'sent' => 0,
				'failed' => 0,
				'skipped' => 0,
			);
		}

		$tally_rows = static function (array $rows, string $bucket) use (&$group_totals): void {
			foreach ($rows as $row) {
				if (!is_array($row)) {
					continue;
				}
				$row_groups = isset($row['groups']) && is_array($row['groups'])
					? array_values(array_unique(array_filter(array_map('sanitize_key', $row['groups']))))
					: array();
				if (empty($row_groups)) {
					$fallback_group = sanitize_key((string) ($row['group'] ?? ''));
					if ($fallback_group !== '') {
						$row_groups[] = $fallback_group;
					}
				}
				foreach ($row_groups as $group_key) {
					if ($group_key === '' || !isset($group_totals[$group_key])) {
						continue;
					}
					$group_totals[$group_key][$bucket]++;
				}
			}
		};

		$tally_rows($recipients, 'recipient_count');
		$tally_rows(isset($data['sent']) && is_array($data['sent']) ? $data['sent'] : array(), 'sent');
		$tally_rows(isset($data['failed']) && is_array($data['failed']) ? $data['failed'] : array(), 'failed');
		$tally_rows(isset($data['skipped']) && is_array($data['skipped']) ? $data['skipped'] : array(), 'skipped');

		foreach ($group_totals as $group_key => $totals) {
			if (
				absint($totals['recipient_count']) === 0
				&& absint($totals['sent']) === 0
				&& absint($totals['failed']) === 0
				&& absint($totals['skipped']) === 0
			) {
				unset($group_totals[$group_key]);
			}
		}
		$data['group_totals'] = $group_totals;

		$skip_reasons = array();
		foreach (isset($data['skipped']) && is_array($data['skipped']) ? $data['skipped'] : array() as $row) {
			if (!is_array($row)) {
				continue;
			}
			$reason = sanitize_key((string) ($row['reason'] ?? ''));
			if ($reason === '') {
				continue;
			}
			if (!isset($skip_reasons[$reason])) {
				$skip_reasons[$reason] = 0;
			}
			$skip_reasons[$reason]++;
		}
		$data['skip_reasons'] = $skip_reasons;

		if (!empty($skip_reasons['missing_email']) || !empty($skip_reasons['invalid_email'])) {
			$data['requires_operator_review'] = true;
		}
	};

	$enabled = (bool) apply_filters('vms_cancellation_notifications_enabled', true, $event_plan_id, $policy, $summary);
	if (!$enabled) {
		$finalize_data();
		return array(
			'status' => 'done',
			'message' => 'notifications_disabled',
			'data' => $data,
		);
	}

	if (!function_exists('wp_mail')) {
		$data['requires_operator_review'] = true;
		$finalize_data();
		return array(
			'status' => 'blocked',
			'message' => 'wp_mail_unavailable',
			'data' => $data,
		);
	}

	$get_vendor_email = function (int $vendor_id): string {
		$vendor_id = absint($vendor_id);
		if ($vendor_id <= 0) {
			return '';
		}
		$k_primary = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('vendor', 'primary_email') ?: '_vms_vendor_primary_email') : '_vms_vendor_primary_email';
		$k_email = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('vendor', 'email') ?: '_vms_vendor_email') : '_vms_vendor_email';
		$k_contact = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('vendor', 'contact_email') ?: '_vms_contact_email') : '_vms_contact_email';

		$candidates = array(
			(string) get_post_meta($vendor_id, $k_primary, true),
			(string) get_post_meta($vendor_id, $k_email, true),
			(string) get_post_meta($vendor_id, $k_contact, true),
		);
		foreach ($candidates as $email_raw) {
			$email = sanitize_email((string) $email_raw);
			if ($email !== '' && is_email($email)) {
				return $email;
			}
		}
		return '';
	};

	$k_band_vendor_id = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'band_vendor_id') ?: '_vms_band_vendor_id') : '_vms_band_vendor_id';
	$band_vendor_id = absint(get_post_meta($event_plan_id, $k_band_vendor_id, true));
	if ($band_vendor_id > 0) {
		$add_recipient(
			'vendor_primary',
			$band_vendor_id,
			(string) get_the_title($band_vendor_id),
			$get_vendor_email($band_vendor_id),
			array(
				'recipient_type' => 'vendor',
				'group' => 'vendor',
				'vendor_id' => $band_vendor_id,
			)
		);
	}

	$lineup_vendor_ids = function_exists('vms_get_event_plan_lineup_vendor_ids')
		? (array) vms_get_event_plan_lineup_vendor_ids($event_plan_id)
		: array();
	$lineup_primary_vendor_id = 0;
	if (function_exists('vms_get_event_plan_lineup_primary_entry')) {
		$lineup_primary = (array) vms_get_event_plan_lineup_primary_entry($event_plan_id);
		$lineup_primary_vendor_id = absint($lineup_primary['vendor_id'] ?? 0);
	}
	foreach ($lineup_vendor_ids as $vendor_id) {
		$vendor_id = absint($vendor_id);
		if ($vendor_id <= 0) {
			continue;
		}
		$kind = ($vendor_id === $lineup_primary_vendor_id || $vendor_id === $band_vendor_id) ? 'vendor_primary' : 'vendor_lineup';
		$add_recipient(
			$kind,
			$vendor_id,
			(string) get_the_title($vendor_id),
			$get_vendor_email($vendor_id),
			array(
				'recipient_type' => 'vendor',
				'group' => function_exists('vms_cancellation_notification_kind_group')
					? vms_cancellation_notification_kind_group($kind)
					: 'vendor',
				'vendor_id' => $vendor_id,
			)
		);
	}

	$k_secondary_vendor_ids = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'secondary_vendor_ids') ?: '_vms_secondary_vendor_ids') : '_vms_secondary_vendor_ids';
	$secondary_vendor_ids = get_post_meta($event_plan_id, $k_secondary_vendor_ids, true);
	if (!is_array($secondary_vendor_ids)) {
		$secondary_vendor_ids = array();
	}
	$secondary_vendor_ids = array_values(array_unique(array_filter(array_map('absint', $secondary_vendor_ids))));
	foreach ($secondary_vendor_ids as $vendor_id) {
		if ($vendor_id <= 0) {
			continue;
		}
		$add_recipient(
			'vendor_secondary',
			$vendor_id,
			(string) get_the_title($vendor_id),
			$get_vendor_email($vendor_id),
			array(
				'recipient_type' => 'vendor',
				'group' => 'secondary_vendor',
				'vendor_id' => $vendor_id,
			)
		);
	}

	$staff_assignment_map = function_exists('vms_cancellation_collect_modern_staff_assignment_map')
		? (array) vms_cancellation_collect_modern_staff_assignment_map($event_plan_id)
		: array();
	$staff_assignment_source = !empty($staff_assignment_map) ? 'modern' : 'none';
	if (empty($staff_assignment_map)) {
		$staff_assignment_map = function_exists('vms_cancellation_collect_legacy_staff_assignment_map')
			? (array) vms_cancellation_collect_legacy_staff_assignment_map($event_plan_id)
			: array();
		if (!empty($staff_assignment_map)) {
			$staff_assignment_source = 'legacy';
		}
	}
	$data['staff_assignment_source'] = $staff_assignment_source;
	$data['staff_assignment_map'] = $staff_assignment_map;

	$staff_role_ids_by_staff = array();
	foreach ($staff_assignment_map as $role_id => $staff_ids) {
		$role_id = absint($role_id);
		if ($role_id <= 0 || !is_array($staff_ids)) {
			continue;
		}
		foreach ($staff_ids as $staff_id) {
			$staff_id = absint($staff_id);
			if ($staff_id <= 0) {
				continue;
			}
			if (!isset($staff_role_ids_by_staff[$staff_id])) {
				$staff_role_ids_by_staff[$staff_id] = array();
			}
			$staff_role_ids_by_staff[$staff_id][] = $role_id;
		}
	}

	foreach ($staff_role_ids_by_staff as $staff_id => $role_ids) {
		$staff_context = function_exists('vms_cancellation_resolve_staff_notification_recipient')
			? (array) vms_cancellation_resolve_staff_notification_recipient((int) $staff_id)
			: array(
				'staff_id' => absint($staff_id),
				'post_id' => absint($staff_id),
				'user_id' => 0,
				'label' => (string) get_the_title($staff_id),
				'email' => '',
				'email_source' => '',
			);
		$add_recipient(
			'staff',
			(int) $staff_id,
			(string) ($staff_context['label'] ?? ''),
			(string) ($staff_context['email'] ?? ''),
			array(
				'recipient_type' => 'staff',
				'group' => 'staff',
				'staff_id' => absint($staff_context['staff_id'] ?? $staff_id),
				'post_id' => absint($staff_context['post_id'] ?? $staff_id),
				'user_id' => absint($staff_context['user_id'] ?? 0),
				'role_ids' => array_values(array_unique(array_filter(array_map('absint', $role_ids)))),
				'staff_assignment_source' => $staff_assignment_source,
				'email_source' => sanitize_key((string) ($staff_context['email_source'] ?? '')),
			)
		);
	}

	$already_sent_by_email = array();
	$mark_previously_sent = function (array $rows) use (&$already_sent_by_email) {
		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}
			$email = sanitize_email((string) ($row['email'] ?? ''));
			if ($email === '' || !is_email($email)) {
				continue;
			}
			$already_sent_by_email[strtolower($email)] = true;
		}
	};
	$steps = isset($summary['steps']) && is_array($summary['steps']) ? $summary['steps'] : array();
	foreach ($steps as $step) {
		if (!is_array($step)) {
			continue;
		}
		if (sanitize_key((string) ($step['key'] ?? '')) !== 'notifications') {
			continue;
		}
		$step_data = isset($step['data']) && is_array($step['data']) ? $step['data'] : array();
		$mark_previously_sent(isset($step_data['sent']) && is_array($step_data['sent']) ? $step_data['sent'] : array());
		$previous = isset($step['previous_result']['data']) && is_array($step['previous_result']['data']) ? $step['previous_result']['data'] : array();
		$mark_previously_sent(isset($previous['sent']) && is_array($previous['sent']) ? $previous['sent'] : array());
	}
	$data['already_sent_count'] = count($already_sent_by_email);

	$finalize_data();
	if (empty($recipients)) {
		return array(
			'status' => 'done',
			'message' => 'no_notification_recipients',
			'data' => $data,
		);
	}

	$event_title = (string) get_the_title($event_plan_id);
	$event_date = (string) get_post_meta($event_plan_id, '_vms_event_date', true);

	$vendor_message = isset($summary['vendor_message']) ? sanitize_textarea_field((string) $summary['vendor_message']) : '';
	if ($vendor_message === '') {
		$k_cancel_vendor_message = function_exists('bvmgr_meta_key')
			? (bvmgr_meta_key('event_plan', 'cancel_vendor_message') ?: '_vms_cancel_vendor_message')
			: '_vms_cancel_vendor_message';
		$vendor_message = sanitize_textarea_field((string) get_post_meta($event_plan_id, $k_cancel_vendor_message, true));
	}

	$event_title_label = $event_title !== '' ? $event_title : ('Event Plan #' . $event_plan_id);
	$event_date_label = $event_date !== '' ? $event_date : '(date not set)';
	if ($event_date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date)) {
		$ts = strtotime($event_date . ' 00:00:00');
		if ($ts) {
			$event_date_label = date_i18n((string) get_option('date_format', 'F j, Y'), $ts);
		}
	}

	$recipient_has_kind = static function (array $row, string $target_kind): bool {
		$target_kind = sanitize_key($target_kind);
		if ($target_kind === '') {
			return false;
		}
		if (sanitize_key((string) ($row['kind'] ?? '')) === $target_kind) {
			return true;
		}
		$aliases = isset($row['aliases']) && is_array($row['aliases']) ? $row['aliases'] : array();
		foreach ($aliases as $alias) {
			if (is_array($alias) && sanitize_key((string) ($alias['kind'] ?? '')) === $target_kind) {
				return true;
			}
		}
		return false;
	};

	$build_body = static function (array $row) use ($event_plan_id, $event_title_label, $event_date_label, $vendor_message, $recipient_has_kind): string {
		$label = trim(sanitize_text_field((string) ($row['display_name'] ?? ($row['label'] ?? ''))));
		$kind = sanitize_key((string) ($row['kind'] ?? ''));
		$is_primary_vendor = $recipient_has_kind($row, 'vendor_primary');
		$is_staff = ($kind === 'staff') || $recipient_has_kind($row, 'staff');
		$variant = $is_primary_vendor ? 'primary_vendor' : ($is_staff ? 'staff' : 'assigned_vendor');

		$lines = array();
		$lines[] = $label !== '' ? ('Hello ' . $label . ',') : 'Hello,';
		$lines[] = '';
		$lines[] = 'We wanted to let you know that the following event has been cancelled:';
		$lines[] = '';
		$lines[] = 'Event: ' . $event_title_label;
		$lines[] = 'Date: ' . $event_date_label;
		$lines[] = '';

		if ($variant === 'primary_vendor') {
			if ($vendor_message !== '') {
				$lines[] = $vendor_message;
			} else {
				$lines[] = 'We are cancelling this event and wanted to notify you as early as possible. Please do not proceed with this booking unless we contact you with updated arrangements.';
			}
		} elseif ($variant === 'staff') {
			$lines[] = 'You were assigned to work this event, so this is an automatic notice that your assignment is no longer needed for this date.';
		} else {
			$lines[] = 'You were connected to this event, so this is an automatic notice that the event is no longer moving forward for this date.';
		}

		$lines[] = '';
		$lines[] = 'Reference: Event Plan #' . $event_plan_id;
		$lines[] = '';
		$lines[] = 'This automated notice was sent by Backstage Venue Manager.';

		return implode("\n", $lines);
	};

	$subject = sprintf('Event Cancelled: %s', $event_title_label);
	$headers = array(
		'Content-Type: text/plain; charset=UTF-8',
		'X-VMS-Email-Type: cancellation_notification',
		'X-VMS-Event-Plan-ID: ' . $event_plan_id,
	);

	$dry_run = (bool) apply_filters('vms_cancellation_notifications_dry_run', false, $event_plan_id, $policy, $summary, $recipients);
	if ($dry_run) {
		$data['queued_recipients'] = $recipients;
		$data['requires_operator_review'] = true;
		$finalize_data();
		return array(
			'status' => 'done',
			'message' => 'notifications_dry_run_only',
			'data' => $data,
		);
	}

	foreach ($recipients as $row) {
		if (!is_array($row)) {
			continue;
		}
		$email = sanitize_email((string) ($row['email'] ?? ''));
		if ($email === '' || !is_email($email)) {
			$record_skip(
				(string) ($row['kind'] ?? ''),
				absint($row['entity_id'] ?? 0),
				(string) ($row['display_name'] ?? ($row['label'] ?? '')),
				$email,
				'invalid_email',
				$row
			);
			continue;
		}
		if (isset($already_sent_by_email[strtolower($email)])) {
			$skip_row = $row;
			$skip_row['reason'] = 'already_sent_for_job';
			$data['skipped'][] = $skip_row;
			continue;
		}

		$body = $build_body($row);
		$message_variant = $recipient_has_kind($row, 'vendor_primary')
			? 'primary_vendor'
			: (($recipient_has_kind($row, 'staff') || sanitize_key((string) ($row['kind'] ?? '')) === 'staff') ? 'staff' : 'assigned_vendor');

		$result_row = $row;
		$result_row['message_variant'] = $message_variant;
		$sent = wp_mail($email, $subject, $body, $headers);
		if ($sent) {
			$data['sent'][] = $result_row;
		} else {
			$result_row['error'] = 'wp_mail_failed';
			$data['failed'][] = $result_row;
		}
	}

	$finalize_data();
	if (!empty($data['failed'])) {
		$data['requires_operator_review'] = true;
		return array(
			'status' => 'failed',
			'message' => 'notifications_partial_failure',
			'data' => $data,
		);
	}

	$message = 'notifications_sent';
	if ($data['sent_count'] === 0 && $data['failed_count'] === 0 && $data['already_sent_count'] > 0) {
		$message = 'notifications_already_sent';
	}

	return array(
		'status' => 'done',
		'message' => $message,
		'data' => $data,
	);
}, 10, 5);
