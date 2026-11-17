<?php
defined('ABSPATH') || exit;

if (!function_exists('bvmgr_social_manage_capability')) {
	function bvmgr_social_manage_capability(): string
	{
		return defined('BVMGR_CAP_SOCIAL_MANAGE') ? (string) BVMGR_CAP_SOCIAL_MANAGE : 'vms_social_manage';
	}
}

if (!function_exists('bvmgr_social_operator_capability')) {
	function bvmgr_social_operator_capability(): string
	{
		$cap = apply_filters('vms_social_operator_capability', 'edit_posts');
		return is_string($cap) && $cap !== '' ? $cap : 'edit_posts';
	}
}

if (!function_exists('bvmgr_social_ensure_capability_mapping')) {
	function bvmgr_social_ensure_capability_mapping(): void
	{
		$cap = bvmgr_social_manage_capability();
		$role = get_role('administrator');
		if ($role instanceof WP_Role && !$role->has_cap($cap)) {
			$role->add_cap($cap);
		}
	}
}
add_action('init', 'bvmgr_social_ensure_capability_mapping', 20);

function bvmgr_social_current_user_can_configure(): bool
{
	return current_user_can(bvmgr_social_manage_capability()) || current_user_can('manage_options');
}

function bvmgr_social_require_configuration_capability(): void
{
	if (!bvmgr_social_current_user_can_configure()) {
		wp_die(esc_html__('You do not have permission to configure social sharing.', 'backstage-venue-manager'), '', array('response' => 403));
	}
}

function bvmgr_social_require_post_request(): void
{
	if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
		wp_die(esc_html__('Invalid request method.', 'backstage-venue-manager'), '', array('response' => 405));
	}
	if (!isset($_REQUEST['_wpnonce']) || !is_string($_REQUEST['_wpnonce'])) {
		wp_die(esc_html__('Security check failed.', 'backstage-venue-manager'), '', array('response' => 403));
	}
	foreach ($_POST as $value) {
		if (!is_string($value) && !is_int($value)) {
			wp_die(esc_html__('Invalid request data.', 'backstage-venue-manager'));
		}
	}
}

function bvmgr_social_require_event_permission($event_plan_id): int
{
	$id = (is_int($event_plan_id) || is_string($event_plan_id))
		? filter_var($event_plan_id, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)))
		: false;
	if (!$id || get_post_type($id) !== 'vms_event_plan') {
		wp_die(esc_html__('Invalid event plan.', 'backstage-venue-manager'));
	}
	if (!current_user_can('edit_post', $id)) {
		wp_die(esc_html__('You do not have permission to edit this event plan.', 'backstage-venue-manager'), '', array('response' => 403));
	}
	return $id;
}

function bvmgr_social_require_queue_permission($queue_id): array
{
	$id = (is_int($queue_id) || is_string($queue_id))
		? filter_var($queue_id, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)))
		: false;
	$row = $id ? bvmgr_social_queue_get($id) : null;
	if (!is_array($row)) {
		wp_die(esc_html__('Invalid queue item.', 'backstage-venue-manager'));
	}
	// The stored event owns the queue item; a submitted event ID is not authority.
	bvmgr_social_require_event_permission($row['event_plan_id'] ?? 0);
	return $row;
}

if (!function_exists('bvmgr_social_webhook_url_is_safe')) {
	function bvmgr_social_webhook_url_is_safe($url): bool
	{
		if (!is_string($url) || !wp_http_validate_url($url)) {
			return false;
		}

		$parts = wp_parse_url($url);
		if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || empty($parts['host'])) {
			return false;
		}
		if (isset($parts['port']) && (int) $parts['port'] !== 443) {
			return false;
		}

		$host = strtolower(rtrim((string) $parts['host'], '.'));
		if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) {
			return false;
		}

		$addresses = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
			? array($host)
			: gethostbynamel($host);
		if (!is_array($addresses) || empty($addresses)) {
			return false;
		}

		foreach ($addresses as $address) {
			if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
				return false;
			}
		}

		return true;
	}
}

if (!function_exists('bvmgr_social_current_user_can_manage')) {
	function bvmgr_social_current_user_can_manage(): bool
	{
		$cap = bvmgr_social_manage_capability();
		if (current_user_can($cap)) {
			return true;
		}

		// Backward compatibility for existing operator flows that still rely on manage_options.
		if (current_user_can('manage_options')) {
			return true;
		}

		// Operator parity fallback: users who can edit Event Plans should retain access.
		return current_user_can(bvmgr_social_operator_capability());
	}
}

if (!function_exists('bvmgr_social_require_manage_capability')) {
	function bvmgr_social_require_manage_capability(): void
	{
		if (bvmgr_social_current_user_can_manage()) {
			return;
		}

		wp_die(esc_html__('You do not have permission to manage social sharing.', 'backstage-venue-manager'));
	}
}
