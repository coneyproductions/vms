<?php
/**
 * Narrow runtime contract for optional event ticket-sales reporting providers.
 */

defined('ABSPATH') || exit;

if (!function_exists('bvmgr_reporting_provider_contract_version')) {
	function bvmgr_reporting_provider_contract_version(): int
	{
		return 1;
	}
}

if (!function_exists('bvmgr_reporting_empty_event_ticket_sales_result')) {
	/** @return array<string,mixed> */
	function bvmgr_reporting_empty_event_ticket_sales_result(): array
	{
		return array(
			'available' => false,
			'calculated' => false,
			'provider_id' => '',
			'provider_label' => '',
			'provider_version' => '',
			'provider_contract_version' => 0,
			'source' => '',
			'source_label' => '',
			'source_mode' => '',
			'label' => '',
			'updated_label' => '',
			'paid_qty' => 0,
			'free_qty' => 0,
			'total_qty' => 0,
			'revenue_cents' => 0,
			'headcount' => 0,
			'online_qty' => 0,
			'online_net_cents' => 0,
			'door_qty' => 0,
			'door_paid_qty' => 0,
			'door_free_qty' => 0,
			'door_gross_cents' => 0,
			'sales_cents' => 0,
			'excluded_free_online_qty' => 0,
			'excluded_free_ticket_qty_total' => 0,
			'paid_ticket_qty_total' => 0,
			'free_ticket_qty_total' => 0,
			'ticketed_attendance_qty' => 0,
			'has_countable_data' => false,
			'freshness' => array(),
			'warnings' => array(),
			'errors' => array(),
			'provider_attempts' => array(),
		);
	}
}

if (!function_exists('bvmgr_reporting_normalize_messages')) {
	/** @param mixed $messages @return string[] */
	function bvmgr_reporting_normalize_messages($messages): array
	{
		if (!is_array($messages)) {
			$messages = ($messages === null || $messages === '') ? array() : array($messages);
		}

		$out = array();
		foreach ($messages as $message) {
			if (!is_scalar($message)) {
				continue;
			}
			$message = trim(wp_strip_all_tags((string) $message));
			if ($message !== '') {
				$out[] = $message;
			}
		}

		return array_values(array_unique($out));
	}
}

if (!function_exists('bvmgr_reporting_register_provider')) {
	/**
	 * Register one active provider implementation.
	 *
	 * Required: id, version, contract_version, capabilities, callback.
	 * Duplicate provider IDs are rejected instead of silently replacing ownership.
	 *
	 * @param array<string,mixed> $provider
	 */
	function bvmgr_reporting_register_provider(array $provider): bool
	{
		$id = sanitize_key((string) ($provider['id'] ?? ''));
		$version = trim((string) ($provider['version'] ?? ''));
		$contract_version = (int) ($provider['contract_version'] ?? 0);
		$capabilities = array_values(array_unique(array_filter(array_map('sanitize_key', (array) ($provider['capabilities'] ?? array())))));
		$callback = $provider['callback'] ?? null;

		if (
			$id === ''
			|| $version === ''
			|| $contract_version !== bvmgr_reporting_provider_contract_version()
			|| !in_array('event_ticket_sales', $capabilities, true)
			|| !is_callable($callback)
		) {
			return false;
		}

		if (!isset($GLOBALS['bvmgr_reporting_providers']) || !is_array($GLOBALS['bvmgr_reporting_providers'])) {
			$GLOBALS['bvmgr_reporting_providers'] = array();
		}
		if (isset($GLOBALS['bvmgr_reporting_providers'][$id])) {
			return false;
		}

		$GLOBALS['bvmgr_reporting_providers'][$id] = array(
			'id' => $id,
			'label' => trim(wp_strip_all_tags((string) ($provider['label'] ?? $id))),
			'version' => $version,
			'contract_version' => $contract_version,
			'capabilities' => $capabilities,
			'priority' => (int) ($provider['priority'] ?? 100),
			'callback' => $callback,
		);

		return true;
	}
}

if (!function_exists('bvmgr_reporting_get_registered_providers')) {
	/** @return array<string,array<string,mixed>> */
	function bvmgr_reporting_get_registered_providers(): array
	{
		$providers = isset($GLOBALS['bvmgr_reporting_providers']) && is_array($GLOBALS['bvmgr_reporting_providers'])
			? $GLOBALS['bvmgr_reporting_providers']
			: array();

		uasort($providers, static function (array $left, array $right): int {
			$priority = ((int) $left['priority']) <=> ((int) $right['priority']);
			return $priority !== 0 ? $priority : strcmp((string) $left['id'], (string) $right['id']);
		});

		return $providers;
	}
}

if (!function_exists('bvmgr_reporting_normalize_event_ticket_sales_result')) {
	/**
	 * @param array<string,mixed> $raw
	 * @param array<string,mixed> $provider
	 * @return array<string,mixed>
	 */
	function bvmgr_reporting_normalize_event_ticket_sales_result(array $raw, array $provider): array
	{
		$result = bvmgr_reporting_empty_event_ticket_sales_result();
		$result['available'] = !empty($raw['available']);
		$result['calculated'] = !empty($raw['calculated']);
		$result['provider_id'] = (string) $provider['id'];
		$result['provider_label'] = (string) $provider['label'];
		$result['provider_version'] = (string) $provider['version'];
		$result['provider_contract_version'] = (int) $provider['contract_version'];

		foreach (array('source', 'source_mode') as $key) {
			$result[$key] = sanitize_key((string) ($raw[$key] ?? ''));
		}
		foreach (array('source_label', 'label', 'updated_label') as $key) {
			$result[$key] = trim(wp_strip_all_tags((string) ($raw[$key] ?? '')));
		}

		$integer_fields = array(
			'paid_qty',
			'free_qty',
			'total_qty',
			'revenue_cents',
			'headcount',
			'online_qty',
			'online_net_cents',
			'door_qty',
			'door_paid_qty',
			'door_free_qty',
			'door_gross_cents',
			'sales_cents',
			'excluded_free_online_qty',
			'excluded_free_ticket_qty_total',
			'paid_ticket_qty_total',
			'free_ticket_qty_total',
			'ticketed_attendance_qty',
		);
		foreach ($integer_fields as $key) {
			$result[$key] = max(0, (int) ($raw[$key] ?? 0));
		}

		$result['has_countable_data'] = !empty($raw['has_countable_data']);
		$result['freshness'] = isset($raw['freshness']) && is_array($raw['freshness']) ? $raw['freshness'] : array();
		$result['warnings'] = bvmgr_reporting_normalize_messages($raw['warnings'] ?? array());
		$result['errors'] = bvmgr_reporting_normalize_messages($raw['errors'] ?? array());

		return $result;
	}
}

if (!function_exists('bvmgr_reporting_resolve_event_ticket_sales')) {
	/**
	 * Resolve event ticket-sales truth without loading any provider's source.
	 *
	 * @param array<string,mixed> $context
	 * @return array<string,mixed>
	 */
	function bvmgr_reporting_resolve_event_ticket_sales(int $event_plan_id, array $context = array()): array
	{
		$result = bvmgr_reporting_empty_event_ticket_sales_result();
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0) {
			return $result;
		}

		foreach (bvmgr_reporting_get_registered_providers() as $provider) {
			if (!in_array('event_ticket_sales', (array) $provider['capabilities'], true)) {
				continue;
			}

			$attempt = array(
				'provider_id' => (string) $provider['id'],
				'provider_version' => (string) $provider['version'],
				'contract_version' => (int) $provider['contract_version'],
				'status' => 'unavailable',
			);

			try {
				$raw = call_user_func($provider['callback'], $event_plan_id, $context);
				if (function_exists('is_wp_error') && is_wp_error($raw)) {
					$attempt['status'] = 'error';
					$attempt['error_code'] = (string) $raw->get_error_code();
					$result['errors'][] = trim(wp_strip_all_tags((string) $raw->get_error_message()));
				} elseif (!is_array($raw)) {
					$attempt['status'] = 'invalid';
					$result['errors'][] = sprintf(__('Reporting provider %s returned an invalid response.', 'backstage-venue-manager'), (string) $provider['label']);
				} else {
					$normalized = bvmgr_reporting_normalize_event_ticket_sales_result($raw, $provider);
					$attempt['status'] = ($normalized['available'] && $normalized['calculated']) ? 'calculated' : 'unavailable';
					$result['warnings'] = array_merge($result['warnings'], $normalized['warnings']);
					$result['errors'] = array_merge($result['errors'], $normalized['errors']);
					if ($normalized['available'] && $normalized['calculated']) {
						$normalized['provider_attempts'] = array_merge($result['provider_attempts'], array($attempt));
						$normalized['warnings'] = bvmgr_reporting_normalize_messages(array_merge($result['warnings'], $normalized['warnings']));
						$normalized['errors'] = bvmgr_reporting_normalize_messages(array_merge($result['errors'], $normalized['errors']));
						return $normalized;
					}
				}
			} catch (Throwable $error) {
				$attempt['status'] = 'exception';
				$result['errors'][] = sprintf(__('Reporting provider %1$s failed: %2$s', 'backstage-venue-manager'), (string) $provider['label'], $error->getMessage());
			}

			$result['provider_attempts'][] = $attempt;
		}

		$result['warnings'] = bvmgr_reporting_normalize_messages($result['warnings']);
		$result['errors'] = bvmgr_reporting_normalize_messages($result['errors']);
		return $result;
	}
}
