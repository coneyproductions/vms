<?php
defined('ABSPATH') || exit;

if (!function_exists('bvmgr_email_followups_log_option_key')) {
	function bvmgr_email_followups_log_option_key(): string
	{
		return 'vms_email_followups_log';
	}
}

if (!function_exists('bvmgr_email_followups_get_logs')) {
	function bvmgr_email_followups_get_logs(int $limit = 100): array
	{
		$rows = get_option(bvmgr_email_followups_log_option_key(), array());
		if (!is_array($rows)) {
			$rows = array();
		}
		$rows = array_values(array_filter($rows, 'is_array'));
		$limit = max(1, min(500, $limit));
		return array_slice($rows, 0, $limit);
	}
}

if (!function_exists('bvmgr_email_followups_log')) {
	function bvmgr_email_followups_log(array $entry): void
	{
		$logs = bvmgr_email_followups_get_logs(500);
		$entry = array_merge(array(
			'id' => wp_generate_uuid4(),
			'created_at' => current_time('mysql'),
			'action' => '',
			'email_key' => '',
			'event_plan_id' => 0,
			'recipient' => '',
			'status' => 'info',
			'message' => '',
			'meta' => array(),
		), $entry);
		$entry['action'] = sanitize_key((string) $entry['action']);
		$entry['email_key'] = sanitize_key((string) $entry['email_key']);
		$entry['event_plan_id'] = absint($entry['event_plan_id']);
		$entry['recipient'] = sanitize_email((string) $entry['recipient']);
		$entry['status'] = sanitize_key((string) $entry['status']);
		$entry['message'] = sanitize_text_field((string) $entry['message']);
		if (!is_array($entry['meta'])) {
			$entry['meta'] = array();
		}

		array_unshift($logs, $entry);
		$logs = array_slice($logs, 0, 500);
		update_option(bvmgr_email_followups_log_option_key(), $logs, false);
	}
}

if (!function_exists('bvmgr_email_followups_clear_logs')) {
	function bvmgr_email_followups_clear_logs(): void
	{
		delete_option(bvmgr_email_followups_log_option_key());
	}
}

if (!function_exists('bvmgr_email_followups_was_sent')) {
	function bvmgr_email_followups_was_sent(int $event_plan_id, string $email_key, string $recipient = ''): bool
	{
		$event_plan_id = absint($event_plan_id);
		$email_key = sanitize_key($email_key);
		$recipient = sanitize_email($recipient);
		foreach (bvmgr_email_followups_get_logs(500) as $row) {
			if ((int) ($row['event_plan_id'] ?? 0) !== $event_plan_id) {
				continue;
			}
			if (sanitize_key((string) ($row['email_key'] ?? '')) !== $email_key) {
				continue;
			}
			if (sanitize_key((string) ($row['status'] ?? '')) !== 'sent') {
				continue;
			}
			if ($recipient !== '' && strtolower((string) ($row['recipient'] ?? '')) !== strtolower($recipient)) {
				continue;
			}
			return true;
		}
		return false;
	}
}
