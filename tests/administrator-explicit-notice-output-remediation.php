<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__) . '/');
}

if (!defined('MINUTE_IN_SECONDS')) {
	define('MINUTE_IN_SECONDS', 60);
}

if (!class_exists('WP_Post')) {
	class WP_Post
	{
		/** @var int */
		public $ID = 0;

		/** @var string */
		public $post_type = 'vms_event_plan';

		public function __construct(int $id = 0)
		{
			$this->ID = $id;
		}
	}
}

if (!class_exists('WP_Error')) {
	class WP_Error
	{
		/** @var string */
		private $error_code;

		/** @var string */
		private $error_message;

		public function __construct(string $code = '', string $message = '')
		{
			$this->error_code = $code;
			$this->error_message = $message;
		}

		public function get_error_code(): string
		{
			return $this->error_code;
		}

		public function get_error_message(): string
		{
			return $this->error_message;
		}
	}
}

if (!function_exists('add_action')) {
	function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
	{
		unset($hook, $callback, $priority, $accepted_args);
		return true;
	}
}

if (!function_exists('add_filter')) {
	function add_filter(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
	{
		unset($hook, $callback, $priority, $accepted_args);
		return true;
	}
}

if (!function_exists('__')) {
	function __(string $text, string $domain = ''): string
	{
		unset($domain);
		return $text;
	}
}

if (!function_exists('_n')) {
	function _n(string $single, string $plural, int $number, string $domain = ''): string
	{
		unset($domain);
		return $number === 1 ? $single : $plural;
	}
}

if (!function_exists('sanitize_key')) {
	function sanitize_key($value): string
	{
		if (!is_scalar($value)) {
			return '';
		}

		$sanitized = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value));
		return is_string($sanitized) ? $sanitized : '';
	}
}

if (!function_exists('sanitize_text_field')) {
	function sanitize_text_field($value): string
	{
		if (!is_scalar($value)) {
			return '';
		}

		$sanitized = preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string) $value));
		return is_string($sanitized) ? trim($sanitized) : '';
	}
}

if (!function_exists('absint')) {
	function absint($value): int
	{
		return abs((int) $value);
	}
}

if (!function_exists('wp_unslash')) {
	function wp_unslash($value)
	{
		if (is_array($value)) {
			return array_map('wp_unslash', $value);
		}

		return is_string($value) ? stripslashes($value) : $value;
	}
}

if (!function_exists('admin_url')) {
	function admin_url(string $path = ''): string
	{
		return 'https://example.test/wp-admin/' . ltrim($path, '/');
	}
}

if (!function_exists('get_option')) {
	function get_option(string $option, $default = false)
	{
		return array_key_exists($option, $GLOBALS['vms_test_options']) ? $GLOBALS['vms_test_options'][$option] : $default;
	}
}

if (!function_exists('add_query_arg')) {
	function add_query_arg($args, string $url = ''): string
	{
		$base = $url !== '' ? $url : admin_url('admin.php');
		$parts = parse_url($base);
		$query = array();

		if (!empty($parts['query'])) {
			parse_str($parts['query'], $query);
		}

		foreach ((array) $args as $key => $value) {
			$query[(string) $key] = (string) $value;
		}

		$rebuilt = '';
		if (!empty($parts['scheme'])) {
			$rebuilt .= $parts['scheme'] . '://';
		}
		if (!empty($parts['host'])) {
			$rebuilt .= $parts['host'];
		}
		if (!empty($parts['path'])) {
			$rebuilt .= $parts['path'];
		}
		if ($query !== array()) {
			$rebuilt .= '?' . http_build_query($query);
		}

		return $rebuilt;
	}
}

if (!function_exists('esc_html')) {
	function esc_html($text): string
	{
		return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}

if (!function_exists('esc_html__')) {
	function esc_html__(string $text, string $domain = ''): string
	{
		return esc_html(__($text, $domain));
	}
}

if (!function_exists('esc_attr')) {
	function esc_attr($text): string
	{
		return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}

if (!function_exists('esc_attr__')) {
	function esc_attr__(string $text, string $domain = ''): string
	{
		return esc_attr(__($text, $domain));
	}
}

if (!function_exists('esc_url')) {
	function esc_url($url): string
	{
		return htmlspecialchars((string) $url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}

if (!function_exists('esc_url_raw')) {
	function esc_url_raw($url): string
	{
		return (string) $url;
	}
}

if (!function_exists('esc_textarea')) {
	function esc_textarea($text): string
	{
		return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}

if (!function_exists('sanitize_email')) {
	function sanitize_email($email): string
	{
		return trim((string) $email);
	}
}

if (!function_exists('is_email')) {
	function is_email($email): bool
	{
		return filter_var((string) $email, FILTER_VALIDATE_EMAIL) !== false;
	}
}

if (!function_exists('sanitize_html_class')) {
	function sanitize_html_class($class): string
	{
		$sanitized = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $class);
		return is_string($sanitized) ? trim($sanitized, '-') : '';
	}
}

if (!function_exists('current_user_can')) {
	function current_user_can(string $capability): bool
	{
		unset($capability);
		return !empty($GLOBALS['vms_test_current_user_can']);
	}
}

if (!function_exists('wp_die')) {
	function wp_die($message = ''): void
	{
		throw new RuntimeException((string) $message);
	}
}

if (!function_exists('get_current_user_id')) {
	function get_current_user_id(): int
	{
		return (int) ($GLOBALS['vms_test_current_user_id'] ?? 0);
	}
}

if (!function_exists('checked')) {
	function checked($checked, $current = true, bool $display = true): string
	{
		$result = ((string) $checked === (string) $current) ? 'checked="checked"' : '';
		if ($display) {
			echo $result;
		}

		return $result;
	}
}

if (!function_exists('selected')) {
	function selected($selected, $current = true, bool $display = true): string
	{
		$result = ((string) $selected === (string) $current) ? 'selected="selected"' : '';
		if ($display) {
			echo $result;
		}

		return $result;
	}
}

if (!function_exists('wp_nonce_field')) {
	function wp_nonce_field(string $action = '', string $name = '_wpnonce', bool $referer = true, bool $display = true): string
	{
		unset($action, $referer);
		$field = '<input type="hidden" name="' . esc_attr($name) . '" value="nonce" />';
		if ($display) {
			echo $field;
		}

		return $field;
	}
}

if (!function_exists('submit_button')) {
	function submit_button($text = null, string $type = 'primary', string $name = 'submit', bool $wrap = true, $other_attributes = null): void
	{
		unset($other_attributes);
		$button = '<button type="submit" name="' . esc_attr((string) $name) . '" class="' . esc_attr(trim('button ' . $type)) . '">' . esc_html((string) $text) . '</button>';
		if ($wrap) {
			$button = '<p class="submit">' . $button . '</p>';
		}
		echo $button;
	}
}

if (!function_exists('wp_kses_post')) {
	function wp_kses_post($html): string
	{
		return (string) $html;
	}
}

if (!function_exists('wp_nonce_url')) {
	function wp_nonce_url(string $url, string $action): string
	{
		return add_query_arg(array('_wpnonce' => sanitize_key($action)), $url);
	}
}

if (!function_exists('wp_json_encode')) {
	function wp_json_encode($value): string
	{
		$encoded = json_encode($value);
		return is_string($encoded) ? $encoded : 'null';
	}
}

if (!function_exists('get_transient')) {
	function get_transient(string $name)
	{
		$GLOBALS['vms_test_transient_get_calls']++;
		$GLOBALS['vms_test_transient_get_keys'][] = $name;
		return $GLOBALS['vms_test_transients'][$name] ?? false;
	}
}

if (!function_exists('set_transient')) {
	function set_transient(string $name, $value, int $expiration = 0): bool
	{
		$GLOBALS['vms_test_transient_set_calls']++;
		$GLOBALS['vms_test_transient_set_payloads'][] = array($name, $value, $expiration);
		$GLOBALS['vms_test_transients'][$name] = $value;
		return true;
	}
}

if (!function_exists('delete_transient')) {
	function delete_transient(string $name): bool
	{
		$GLOBALS['vms_test_transient_delete_calls']++;
		$GLOBALS['vms_test_transient_delete_keys'][] = $name;
		unset($GLOBALS['vms_test_transients'][$name]);
		return true;
	}
}

if (!function_exists('wp_timezone')) {
	function wp_timezone(): DateTimeZone
	{
		return new DateTimeZone('UTC');
	}
}

if (!function_exists('is_wp_error')) {
	function is_wp_error($thing): bool
	{
		return $thing instanceof WP_Error;
	}
}

if (!function_exists('wp_date')) {
	function wp_date(string $format, int $timestamp, ?DateTimeZone $timezone = null): string
	{
		$date = new DateTimeImmutable('@' . $timestamp);
		if ($timezone instanceof DateTimeZone) {
			$date = $date->setTimezone($timezone);
		}

		return $date->format($format);
	}
}

if (!function_exists('vms_staffing_staff_qualification_review_url')) {
	function vms_staffing_staff_qualification_review_url(int $staff_id): string
	{
		return admin_url('post.php?post=' . $staff_id . '&action=edit');
	}
}

$GLOBALS['vms_test_staff_certifications_pending_items'] = array();
$GLOBALS['vms_test_staff_certifications_provider_calls'] = 0;
$GLOBALS['vms_test_staff_certifications_provider_statuses'] = array();
$GLOBALS['vms_test_options'] = array();
$GLOBALS['vms_test_current_user_can'] = true;
$GLOBALS['vms_test_current_user_id'] = 7;
$GLOBALS['vms_test_transients'] = array();
$GLOBALS['vms_test_transient_get_calls'] = 0;
$GLOBALS['vms_test_transient_get_keys'] = array();
$GLOBALS['vms_test_transient_set_calls'] = 0;
$GLOBALS['vms_test_transient_set_payloads'] = array();
$GLOBALS['vms_test_transient_delete_calls'] = 0;
$GLOBALS['vms_test_transient_delete_keys'] = array();
$GLOBALS['vms_test_event_plan_import_preview_payload_calls'] = 0;
$GLOBALS['vms_test_event_plan_import_preview_payload_tokens'] = array();
$GLOBALS['vms_test_event_plan_import_preview_payload_value'] = array();
$GLOBALS['vms_test_event_plan_import_audit_runs_calls'] = 0;
$GLOBALS['vms_test_event_plan_import_audit_runs_value'] = array();
$GLOBALS['vms_test_event_plan_import_latest_revertible_run_calls'] = 0;
$GLOBALS['vms_test_event_plan_import_latest_revertible_run_value'] = array();
$GLOBALS['vms_test_event_plan_import_read_rows_json_calls'] = 0;
$GLOBALS['vms_test_event_plan_import_read_rows_json_references'] = array();
$GLOBALS['vms_test_event_plan_import_read_rows_json_value'] = array();
$GLOBALS['vms_test_email_followups_settings_calls'] = 0;
$GLOBALS['vms_test_email_followups_mailpoet_status_calls'] = 0;
$GLOBALS['vms_test_email_followups_due_items_calls'] = 0;
$GLOBALS['vms_test_email_followups_event_choices_calls'] = 0;
$GLOBALS['vms_test_email_followups_event_choices_args'] = array();
$GLOBALS['vms_test_email_followups_event_choice_posts'] = array();
$GLOBALS['vms_test_email_followups_event_choice_labels'] = array();
$GLOBALS['vms_test_email_followups_template_definitions_calls'] = 0;
$GLOBALS['vms_test_email_followups_event_context_calls'] = 0;
$GLOBALS['vms_test_email_followups_event_context_value'] = array(
	'valid' => true,
	'event_plan_id' => 0,
	'event_name' => 'Example Event',
	'event_date' => '2026-08-01',
	'event_date_label' => 'Saturday, August 1, 2026',
	'post_status' => 'publish',
	'plan_status' => 'scheduled',
);
$GLOBALS['vms_test_email_followups_event_recipients_calls'] = 0;
$GLOBALS['vms_test_email_followups_event_recipients_value'] = array(
	'recipients' => array(
		array(
			'email' => 'buyer@example.test',
			'name' => 'Buyer Example',
			'qty' => 2,
			'order_numbers' => array('1001'),
		),
	),
	'counts' => array(
		'tickets_net' => 2,
	),
);
$GLOBALS['vms_test_email_followups_render_message_calls'] = 0;
$GLOBALS['vms_test_email_followups_render_message_value'] = array(
	'subject' => 'Know Before You Go',
	'body_html' => '<p>Rendered preview body.</p>',
	'tokens' => array(
		'{feedback_url}' => '',
	),
);
$GLOBALS['vms_test_email_followups_scheduled_timestamp_calls'] = 0;
$GLOBALS['vms_test_email_followups_scheduled_timestamp_value'] = 0;
$GLOBALS['vms_test_email_followups_context_allows_send_calls'] = 0;
$GLOBALS['vms_test_email_followups_context_allows_send_value'] = array(true, 'ok');
$GLOBALS['vms_test_email_followups_manual_batch_size_calls'] = 0;
$GLOBALS['vms_test_ticket_integrity_get_settings_calls'] = 0;
$GLOBALS['vms_test_ticket_integrity_get_settings_value'] = array();
$GLOBALS['vms_test_ticket_integrity_get_results_store_calls'] = 0;
$GLOBALS['vms_test_ticket_integrity_get_results_store_value'] = array('summary' => array(), 'last_scan' => array());
$GLOBALS['vms_test_ticket_integrity_prepare_payment_gateway_health_calls'] = 0;
$GLOBALS['vms_test_ticket_integrity_prepare_payment_gateway_health_args'] = array();
$GLOBALS['vms_test_ticket_integrity_prepare_payment_gateway_health_value'] = array();
$GLOBALS['vms_test_ticket_integrity_get_sorted_events_calls'] = 0;
$GLOBALS['vms_test_ticket_integrity_get_sorted_events_value'] = array();
$GLOBALS['vms_test_ticket_integrity_get_logs_calls'] = 0;
$GLOBALS['vms_test_ticket_integrity_get_logs_value'] = array();
$GLOBALS['vms_test_integrity_venue_issue_calls'] = 0;
$GLOBALS['vms_test_integrity_venue_issue_limits'] = array();
$GLOBALS['vms_test_integrity_venue_issues_value'] = array();
$GLOBALS['vms_test_integrity_venue_get_posts_calls'] = 0;
$GLOBALS['vms_test_integrity_venue_get_posts_args'] = array();
$GLOBALS['vms_test_integrity_venue_get_posts_value'] = array();
$GLOBALS['vms_test_integrity_venue_titles'] = array();

if (!function_exists('vms_staffing_get_staff_qualification_review_items')) {
	/**
	 * @return array<int|string,mixed>
	 */
	function vms_staffing_get_staff_qualification_review_items(string $status): array
	{
		$GLOBALS['vms_test_staff_certifications_provider_calls']++;
		$GLOBALS['vms_test_staff_certifications_provider_statuses'][] = $status;
		return $GLOBALS['vms_test_staff_certifications_pending_items'];
	}
}

if (!function_exists('vms_email_followups_settings')) {
	function vms_email_followups_settings(): array
	{
		$GLOBALS['vms_test_email_followups_settings_calls']++;
		return array(
			'enabled' => true,
			'auto_send_enabled' => false,
			'mailpoet_sync_enabled' => false,
			'mailpoet_list_id' => 42,
			'from_name' => 'Example Team',
			'from_email' => 'from@example.test',
			'reply_to_email' => 'reply@example.test',
			'test_recipient' => 'test@example.test',
			'reminder_window_hours' => 24,
			'signature' => "Regards,\nExample Team",
			'templates' => array(),
			'templates_enabled' => array(),
		);
	}
}

if (!function_exists('vms_event_plan_import_get_preview_payload')) {
	function vms_event_plan_import_get_preview_payload(string $token, int $user_id = 0): array
	{
		unset($user_id);
		$GLOBALS['vms_test_event_plan_import_preview_payload_calls']++;
		$GLOBALS['vms_test_event_plan_import_preview_payload_tokens'][] = $token;
		return is_array($GLOBALS['vms_test_event_plan_import_preview_payload_value'])
			? $GLOBALS['vms_test_event_plan_import_preview_payload_value']
			: array();
	}
}

if (!function_exists('vms_event_plan_import_get_audit_runs')) {
	function vms_event_plan_import_get_audit_runs(): array
	{
		$GLOBALS['vms_test_event_plan_import_audit_runs_calls']++;
		return is_array($GLOBALS['vms_test_event_plan_import_audit_runs_value'])
			? $GLOBALS['vms_test_event_plan_import_audit_runs_value']
			: array();
	}
}

if (!function_exists('vms_event_plan_import_latest_revertible_run')) {
	function vms_event_plan_import_latest_revertible_run(): array
	{
		$GLOBALS['vms_test_event_plan_import_latest_revertible_run_calls']++;
		return is_array($GLOBALS['vms_test_event_plan_import_latest_revertible_run_value'])
			? $GLOBALS['vms_test_event_plan_import_latest_revertible_run_value']
			: array();
	}
}

if (!function_exists('vms_event_plan_import_read_rows_json')) {
	function vms_event_plan_import_read_rows_json(string $reference)
	{
		$GLOBALS['vms_test_event_plan_import_read_rows_json_calls']++;
		$GLOBALS['vms_test_event_plan_import_read_rows_json_references'][] = $reference;
		return $GLOBALS['vms_test_event_plan_import_read_rows_json_value'];
	}
}

if (!function_exists('vms_email_followups_mailpoet_status')) {
	function vms_email_followups_mailpoet_status(): array
	{
		$GLOBALS['vms_test_email_followups_mailpoet_status_calls']++;
		return array(
			'message' => 'Connected',
			'setup_complete' => true,
		);
	}
}

if (!function_exists('vms_email_followups_due_items')) {
	function vms_email_followups_due_items(): array
	{
		$GLOBALS['vms_test_email_followups_due_items_calls']++;
		return array('first', 'second');
	}
}

if (!function_exists('vms_email_followups_event_choices')) {
	function vms_email_followups_event_choices(int $limit = 0, int $selected_id = 0): array
	{
		$GLOBALS['vms_test_email_followups_event_choices_calls']++;
		$GLOBALS['vms_test_email_followups_event_choices_args'][] = array($limit, $selected_id);
		return array_slice($GLOBALS['vms_test_email_followups_event_choice_posts'], 0, $limit > 0 ? $limit : null);
	}
}

if (!function_exists('vms_email_followups_event_choice_label')) {
	function vms_email_followups_event_choice_label(WP_Post $plan): string
	{
		return (string) ($GLOBALS['vms_test_email_followups_event_choice_labels'][$plan->ID] ?? ('Event ' . $plan->ID));
	}
}

if (!function_exists('vms_email_followups_template_definitions')) {
	function vms_email_followups_template_definitions(): array
	{
		$GLOBALS['vms_test_email_followups_template_definitions_calls']++;
		return array(
			'know_before' => array(
				'label' => 'Know Before',
			),
			'post_event' => array(
				'label' => 'Post Event',
			),
		);
	}
}

if (!function_exists('vms_email_followups_event_context')) {
	function vms_email_followups_event_context(int $event_plan_id): array
	{
		$GLOBALS['vms_test_email_followups_event_context_calls']++;
		$context = $GLOBALS['vms_test_email_followups_event_context_value'];
		$context['event_plan_id'] = $event_plan_id;
		return $context;
	}
}

if (!function_exists('vms_email_followups_event_recipients')) {
	function vms_email_followups_event_recipients(int $event_plan_id): array
	{
		unset($event_plan_id);
		$GLOBALS['vms_test_email_followups_event_recipients_calls']++;
		return $GLOBALS['vms_test_email_followups_event_recipients_value'];
	}
}

if (!function_exists('vms_email_followups_render_message')) {
	function vms_email_followups_render_message(string $email_key, int $event_plan_id, array $recipient = array()): array
	{
		unset($email_key, $event_plan_id, $recipient);
		$GLOBALS['vms_test_email_followups_render_message_calls']++;
		return $GLOBALS['vms_test_email_followups_render_message_value'];
	}
}

if (!function_exists('vms_email_followups_scheduled_timestamp')) {
	function vms_email_followups_scheduled_timestamp(int $event_plan_id, string $email_key): int
	{
		unset($event_plan_id, $email_key);
		$GLOBALS['vms_test_email_followups_scheduled_timestamp_calls']++;
		return (int) $GLOBALS['vms_test_email_followups_scheduled_timestamp_value'];
	}
}

if (!function_exists('vms_email_followups_context_allows_send')) {
	function vms_email_followups_context_allows_send(array $context): array
	{
		unset($context);
		$GLOBALS['vms_test_email_followups_context_allows_send_calls']++;
		return $GLOBALS['vms_test_email_followups_context_allows_send_value'];
	}
}

if (!function_exists('vms_email_followups_manual_batch_size')) {
	function vms_email_followups_manual_batch_size(): int
	{
		$GLOBALS['vms_test_email_followups_manual_batch_size_calls']++;
		return 50;
	}
}

if (!function_exists('vms_ticket_integrity_get_settings')) {
	function vms_ticket_integrity_get_settings(): array
	{
		$GLOBALS['vms_test_ticket_integrity_get_settings_calls']++;
		return is_array($GLOBALS['vms_test_ticket_integrity_get_settings_value'] ?? null) ? $GLOBALS['vms_test_ticket_integrity_get_settings_value'] : array();
	}
}

if (!function_exists('vms_ticket_integrity_get_results_store')) {
	function vms_ticket_integrity_get_results_store(): array
	{
		$GLOBALS['vms_test_ticket_integrity_get_results_store_calls']++;
		return is_array($GLOBALS['vms_test_ticket_integrity_get_results_store_value'] ?? null) ? $GLOBALS['vms_test_ticket_integrity_get_results_store_value'] : array();
	}
}

if (!function_exists('vms_ticket_integrity_prepare_payment_gateway_health')) {
	function vms_ticket_integrity_prepare_payment_gateway_health(string $context = '', int $ttl = 0): array
	{
		$GLOBALS['vms_test_ticket_integrity_prepare_payment_gateway_health_calls']++;
		$GLOBALS['vms_test_ticket_integrity_prepare_payment_gateway_health_args'][] = array($context, $ttl);
		return is_array($GLOBALS['vms_test_ticket_integrity_prepare_payment_gateway_health_value'] ?? null) ? $GLOBALS['vms_test_ticket_integrity_prepare_payment_gateway_health_value'] : array();
	}
}

if (!function_exists('vms_ticket_integrity_get_sorted_events')) {
	function vms_ticket_integrity_get_sorted_events(): array
	{
		$GLOBALS['vms_test_ticket_integrity_get_sorted_events_calls']++;
		return is_array($GLOBALS['vms_test_ticket_integrity_get_sorted_events_value'] ?? null) ? $GLOBALS['vms_test_ticket_integrity_get_sorted_events_value'] : array();
	}
}

if (!function_exists('vms_ticket_integrity_get_logs')) {
	function vms_ticket_integrity_get_logs(): array
	{
		$GLOBALS['vms_test_ticket_integrity_get_logs_calls']++;
		return is_array($GLOBALS['vms_test_ticket_integrity_get_logs_value'] ?? null) ? $GLOBALS['vms_test_ticket_integrity_get_logs_value'] : array();
	}
}

if (!function_exists('vms_integrity_list_event_plans_with_venue_issues')) {
	function vms_integrity_list_event_plans_with_venue_issues(int $limit): array
	{
		$GLOBALS['vms_test_integrity_venue_issue_calls']++;
		$GLOBALS['vms_test_integrity_venue_issue_limits'][] = $limit;
		return is_array($GLOBALS['vms_test_integrity_venue_issues_value'] ?? null) ? $GLOBALS['vms_test_integrity_venue_issues_value'] : array();
	}
}

if (!function_exists('get_posts')) {
	function get_posts(array $args = array()): array
	{
		$GLOBALS['vms_test_integrity_venue_get_posts_calls']++;
		$GLOBALS['vms_test_integrity_venue_get_posts_args'][] = $args;
		return is_array($GLOBALS['vms_test_integrity_venue_get_posts_value'] ?? null) ? $GLOBALS['vms_test_integrity_venue_get_posts_value'] : array();
	}
}

if (!function_exists('get_edit_post_link')) {
	function get_edit_post_link(int $post_id, string $context = 'display'): string
	{
		unset($context);
		return admin_url('post.php?post=' . $post_id . '&action=edit');
	}
}

if (!function_exists('get_the_title')) {
	function get_the_title(int $post_id): string
	{
		return (string) ($GLOBALS['vms_test_integrity_venue_titles'][$post_id] ?? ('Post ' . $post_id));
	}
}

if (!function_exists('vms_ticket_integrity_format_datetime')) {
	function vms_ticket_integrity_format_datetime(int $timestamp_gmt): string
	{
		if ($timestamp_gmt <= 0) {
			return 'Never';
		}

		return gmdate('Y-m-d H:i:s T', $timestamp_gmt);
	}
}

if (!function_exists('vms_ticket_integrity_status_css_class')) {
	function vms_ticket_integrity_status_css_class(string $status): string
	{
		$status = sanitize_html_class($status);
		return 'vms-ticket-integrity__status vms-ticket-integrity__status--' . ($status !== '' ? $status : 'unknown');
	}
}

if (!function_exists('vms_ticket_integrity_status_label')) {
	function vms_ticket_integrity_status_label(string $status): string
	{
		$status = sanitize_key($status);
		return $status !== '' ? ucwords(str_replace('_', ' ', $status)) : 'Unknown';
	}
}

if (!function_exists('wp_kses')) {
	function wp_kses($html, $allowed_html): string
	{
		return (string) preg_replace_callback(
			'~<(/?)([a-zA-Z][a-zA-Z0-9]*)([^>]*)>~',
			static function (array $matches) use ($allowed_html): string {
				$closing = $matches[1] === '/';
				$tag = strtolower((string) $matches[2]);
				if (!array_key_exists($tag, $allowed_html)) {
					return '';
				}

				if ($closing) {
					return '</' . $tag . '>';
				}

				$attrs = '';
				$allowed_attrs = is_array($allowed_html[$tag]) ? $allowed_html[$tag] : array();
				if ($allowed_attrs !== array()) {
					preg_match_all(
						'~\s+([a-zA-Z0-9:-]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+)))?~',
						(string) ($matches[3] ?? ''),
						$attr_matches,
						PREG_SET_ORDER
					);

					foreach ($attr_matches as $attr_match) {
						$name = strtolower((string) $attr_match[1]);
						if (!array_key_exists($name, $allowed_attrs)) {
							continue;
						}

						$value = '';
						if (array_key_exists(2, $attr_match) && $attr_match[2] !== '') {
							$value = (string) $attr_match[2];
						} elseif (array_key_exists(3, $attr_match) && $attr_match[3] !== '') {
							$value = (string) $attr_match[3];
						} elseif (array_key_exists(4, $attr_match) && $attr_match[4] !== '') {
							$value = (string) $attr_match[4];
						}

						if ($name === 'href' && preg_match('~^\s*(?:javascript|data|vbscript):~i', html_entity_decode($value, ENT_QUOTES, 'UTF-8'))) {
							continue;
						}

						$attrs .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
					}
				}

				return '<' . $tag . $attrs . '>';
			},
			(string) $html
		);
	}
}

require_once dirname(__DIR__) . '/includes/admin-ui/shell.php';
require_once dirname(__DIR__) . '/includes/modules/status-notices/admin-ui.php';
require_once dirname(__DIR__) . '/includes/admin/continuity-binder.php';
require_once dirname(__DIR__) . '/includes/admin/due-dates.php';
require_once dirname(__DIR__) . '/includes/admin/square-sync-protection.php';
require_once dirname(__DIR__) . '/includes/admin/staff-certifications.php';
require_once dirname(__DIR__) . '/includes/services/event-plan-import/event-plan-import-engine.php';
require_once dirname(__DIR__) . '/includes/admin/data-tools/page-event-plan-import.php';
require_once dirname(__DIR__) . '/includes/modules/email-followups/admin-ui.php';
require_once dirname(__DIR__) . '/includes/social-share/admin.php';
require_once dirname(__DIR__) . '/includes/admin/event-feedback.php';
require_once dirname(__DIR__) . '/includes/admin/ticket-integrity-page.php';
require_once dirname(__DIR__) . '/includes/admin/settings-page.php';
require_once dirname(__DIR__) . '/includes/admin/integrity-venue-reconcile.php';

$pluginRoot = dirname(__DIR__);
$shellSource = file_get_contents($pluginRoot . '/includes/admin-ui/shell.php');
$statusSource = file_get_contents($pluginRoot . '/includes/modules/status-notices/admin-ui.php');
$continuitySource = file_get_contents($pluginRoot . '/includes/admin/continuity-binder.php');
$dueDatesSource = file_get_contents($pluginRoot . '/includes/admin/due-dates.php');
$squareSyncProtectionSource = file_get_contents($pluginRoot . '/includes/admin/square-sync-protection.php');
$staffCertificationsSource = file_get_contents($pluginRoot . '/includes/admin/staff-certifications.php');
$socialSource = file_get_contents($pluginRoot . '/includes/social-share/admin.php');
$emailFollowupsSource = file_get_contents($pluginRoot . '/includes/modules/email-followups/admin-ui.php');
$eventFeedbackSource = file_get_contents($pluginRoot . '/includes/admin/event-feedback.php');
$ticketIntegritySource = file_get_contents($pluginRoot . '/includes/admin/ticket-integrity-page.php');
$settingsSource = file_get_contents($pluginRoot . '/includes/admin/settings-page.php');
$venueReconcileSource = file_get_contents($pluginRoot . '/includes/admin/integrity-venue-reconcile.php');
$calendarReconcileSource = file_get_contents($pluginRoot . '/includes/admin/integrity-calendar-reconcile.php');
$toursAdminSource = file_get_contents($pluginRoot . '/includes/tours/class-vms-tours-admin.php');
$scheduleSource = file_get_contents($pluginRoot . '/includes/admin/schedule.php');
$eventPlanImportSource = file_get_contents($pluginRoot . '/includes/admin/data-tools/page-event-plan-import.php');
$eventPlanImportActionsSource = file_get_contents($pluginRoot . '/includes/admin/data-tools/actions-event-plan-import.php');
$eventPlanImportEngineSource = file_get_contents($pluginRoot . '/includes/services/event-plan-import/event-plan-import-engine.php');
$vendorAvailabilitySource = file_get_contents($pluginRoot . '/includes/admin/vendor-availability.php');
$bootstrapSource = file_get_contents($pluginRoot . '/includes/bootstrap.php');

$assert = static function (bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
};

$assert(is_string($shellSource) && $shellSource !== '', 'Admin shell source should be readable.');
$assert(is_string($statusSource) && $statusSource !== '', 'Status Notices admin UI source should be readable.');
$assert(is_string($continuitySource) && $continuitySource !== '', 'Continuity Binder source should be readable.');
$assert(is_string($dueDatesSource) && $dueDatesSource !== '', 'Due Dates source should be readable.');
$assert(is_string($squareSyncProtectionSource) && $squareSyncProtectionSource !== '', 'Square Sync Protection source should be readable.');
$assert(is_string($staffCertificationsSource) && $staffCertificationsSource !== '', 'Staff Certifications source should be readable.');
$assert(is_string($socialSource) && $socialSource !== '', 'Social Sharing source should be readable.');
$assert(is_string($emailFollowupsSource) && $emailFollowupsSource !== '', 'Email Follow-Ups source should be readable.');
$assert(is_string($eventFeedbackSource) && $eventFeedbackSource !== '', 'Event Feedback source should be readable.');
$assert(is_string($ticketIntegritySource) && $ticketIntegritySource !== '', 'Ticket Integrity source should be readable.');
$assert(is_string($settingsSource) && $settingsSource !== '', 'Settings source should be readable.');
$assert(is_string($venueReconcileSource) && $venueReconcileSource !== '', 'Venue Reconciliation source should be readable.');
$assert(is_string($calendarReconcileSource) && $calendarReconcileSource !== '', 'Calendar Reconciliation source should be readable.');
$assert(is_string($toursAdminSource) && $toursAdminSource !== '', 'Guided Tours admin source should be readable.');
$assert(is_string($scheduleSource) && $scheduleSource !== '', 'Schedule source should be readable.');
$assert(is_string($eventPlanImportSource) && $eventPlanImportSource !== '', 'Event Plan Import source should be readable.');
$assert(is_string($eventPlanImportActionsSource) && $eventPlanImportActionsSource !== '', 'Event Plan Import actions source should be readable.');
$assert(is_string($eventPlanImportEngineSource) && $eventPlanImportEngineSource !== '', 'Event Plan Import engine source should be readable.');
$assert(is_string($vendorAvailabilitySource) && $vendorAvailabilitySource !== '', 'Vendor Availability source should be readable.');
$assert(is_string($bootstrapSource) && $bootstrapSource !== '', 'Bootstrap source should be readable.');

$normalizeAllowedHtml = static function (array $allowed_html): array {
	ksort($allowed_html);
	foreach ($allowed_html as $tag => $attrs) {
		if (is_array($attrs)) {
			ksort($attrs);
			$allowed_html[$tag] = $attrs;
		}
	}

	return $allowed_html;
};

$expectedAllowed = array(
	'div' => array(
		'class' => true,
	),
	'p' => array(),
);
$expectedRichAllowed = array(
	'div' => array(
		'class' => true,
	),
	'p' => array(),
	'strong' => array(),
);
$expectedHeaderActionsAllowed = array(
	'a' => array(
		'class' => true,
		'href' => true,
	),
	'button' => array(
		'class' => true,
		'data-vms-tour' => true,
		'data-vms-tour-start' => true,
		'type' => true,
	),
	'div' => array(
		'class' => true,
		'data-vms-tour' => true,
	),
);

$assert(function_exists('vms_admin_ui_explicit_notice_allowed_html'), 'Explicit notice allowlist helper should be defined.');
$assert(function_exists('vms_admin_ui_rich_explicit_notice_allowed_html'), 'Rich explicit notice allowlist helper should be defined.');
$assert(function_exists('vms_admin_ui_header_actions_allowed_html'), 'Header actions allowlist helper should be defined.');
$assert(
	$normalizeAllowedHtml(vms_admin_ui_explicit_notice_allowed_html()) === $normalizeAllowedHtml($expectedAllowed),
	'Explicit notice allowlist should contain only div[class] and p.'
);
$assert(
	$normalizeAllowedHtml(vms_admin_ui_rich_explicit_notice_allowed_html()) === $normalizeAllowedHtml($expectedRichAllowed),
	'Rich explicit notice allowlist should contain only div[class], p, and strong.'
);
$assert(
	$normalizeAllowedHtml(vms_admin_ui_header_actions_allowed_html()) === $normalizeAllowedHtml($expectedHeaderActionsAllowed),
	'Header actions allowlist should contain only the discovered action elements and attributes.'
);
$assert(
	preg_match('~echo\s+wp_kses\s*\(\s*\$explicit_notices_html\s*,\s*vms_admin_ui_explicit_notice_allowed_html\s*\(\s*\)\s*\)\s*;~s', $shellSource) === 1,
	'Admin shell should apply the dedicated allowlist at the final explicit notice sink.'
);
$assert(
	preg_match('~echo\s+wp_kses\s*\(\s*\$rich_explicit_notices_html\s*,\s*vms_admin_ui_rich_explicit_notice_allowed_html\s*\(\s*\)\s*\)\s*;~s', $shellSource) === 1,
	'Admin shell should apply the dedicated allowlist at the final rich explicit notice sink.'
);
$assert(
	preg_match('~echo\s+[\'"]<div class="vms-admin-shell__actions">[\'"]\s*\.\s*wp_kses\s*\(\s*\$actions_html\s*,\s*vms_admin_ui_header_actions_allowed_html\s*\(\s*\)\s*\)\s*\.\s*[\'"]</div>[\'"]\s*;~s', $shellSource) === 1,
	'Admin shell should apply the dedicated allowlist at the final header-actions sink.'
);
$assert(strpos($shellSource, 'echo $explicit_notices_html;') === false, 'Admin shell should not leave a raw explicit notice echo sink.');
$assert(strpos($shellSource, 'echo $rich_explicit_notices_html;') === false, 'Admin shell should not leave a raw rich explicit notice echo sink.');
$assert(strpos($shellSource, 'esc_html($explicit_notices_html') === false, 'Admin shell should not text-escape the explicit notice fragment.');
$assert(strpos($shellSource, 'esc_html($rich_explicit_notices_html') === false, 'Admin shell should not text-escape the rich explicit notice fragment.');
$assert(strpos($shellSource, 'wp_kses_post($explicit_notices_html') === false, 'Admin shell should not use wp_kses_post() for the explicit notice sink.');
$assert(strpos($shellSource, 'wp_kses_post($rich_explicit_notices_html') === false, 'Admin shell should not use wp_kses_post() for the rich explicit notice sink.');
$assert(!preg_match('~wp_kses_allowed_html\s*\(\s*[\'"]post[\'"]\s*\)~', $shellSource), 'Admin shell should not use the post allowlist for the explicit notice sink.');
$assert(strpos($shellSource, 'echo \'<div class="vms-admin-shell__actions">\' . $actions_html . \'</div>\';') === false, 'Admin shell should not leave a raw header-actions echo sink.');
$assert(strpos($shellSource, 'esc_html($actions_html') === false, 'Admin shell should not text-escape the header-actions fragment.');
$assert(strpos($shellSource, 'wp_kses_post($actions_html') === false, 'Admin shell should not use wp_kses_post() for the header-actions sink.');
$assert(strpos($shellSource, 'echo $captured_notices_html;') !== false, 'Captured notice sink should remain untouched.');
$assert(strpos($shellSource, 'echo $content_html;') !== false, 'Shell content sink should remain untouched.');
$assert(strpos($shellSource, 'wp_kses($actions_html, vms_admin_ui_header_actions_allowed_html())') !== false, 'Dedicated header-actions allowlist should be applied only to actions.');
$assert(strpos($shellSource, 'wp_kses($rich_explicit_notices_html, vms_admin_ui_explicit_notice_allowed_html())') === false, 'Rich explicit notice sink should not reuse the simple notice allowlist.');
$assert(strpos($shellSource, 'wp_kses($explicit_notices_html, vms_admin_ui_rich_explicit_notice_allowed_html())') === false, 'Simple explicit notice sink should not reuse the rich allowlist.');
$assert(strpos($shellSource, 'wp_kses($captured_notices_html') === false, 'Dedicated explicit notice allowlist should not be applied to captured notices.');
$assert(strpos($shellSource, 'wp_kses($content_html') === false, 'Dedicated explicit notice allowlist should not be applied to shell content.');
$assert(strpos($bootstrapSource, "require_once __DIR__ . '/tours/tours.php';") !== false, 'Canonical bootstrap should load the shared tours helper file.');
$assert(strpos($bootstrapSource, 'class-vms-tours.php') === false, 'Canonical bootstrap should not directly load the legacy core tours help-button file.');

$allIncludeSource = '';
$actionCallerFiles = array();
$noticesCallbackFiles = array();
$richNoticesCallbackFiles = array();
$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($pluginRoot . '/includes', FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
	if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
		continue;
	}

	$source = file_get_contents($file->getPathname());
	$assert(is_string($source), 'Production include source should be readable: ' . $file->getPathname());
	$allIncludeSource .= "\n" . $source;
	if (preg_match('~[\'"]actions_html[\'"]\s*=>~', $source) === 1) {
		$actionCallerFiles[] = str_replace($pluginRoot . '/includes/', '', $file->getPathname());
	}
		if (preg_match('~[\'"]notices_callback[\'"]\s*=>~', $source) === 1) {
			$noticesCallbackFiles[] = str_replace($pluginRoot . '/includes/', '', $file->getPathname());
		}
		if (preg_match('~[\'"]rich_notices_callback[\'"]\s*=>~', $source) === 1) {
			$richNoticesCallbackFiles[] = str_replace($pluginRoot . '/includes/', '', $file->getPathname());
		}
	}

$noticesCallbackCount = preg_match_all('~[\'"]notices_callback[\'"]\s*=>~', $allIncludeSource, $unusedMatches);
$richNoticesCallbackCount = preg_match_all('~[\'"]rich_notices_callback[\'"]\s*=>~', $allIncludeSource, $unusedRichMatches);
$statusNoticeCallbackCount = preg_match_all('~[\'"]notices_callback[\'"]\s*=>\s*[\'"]vms_status_notice_notice_bar[\'"]~', $allIncludeSource, $unusedStatusMatches);
$venueRichNoticeCallbackCount = preg_match_all('~[\'"]rich_notices_callback[\'"]\s*=>\s*[\'"]vms_render_integrity_venue_reconcile_notice[\'"]~', $allIncludeSource, $unusedVenueRichMatches);
$continuityNoticeCallbackCount = preg_match_all('~[\'"]notices_callback[\'"]\s*=>\s*[\'"]vms_continuity_binder_render_updated_notice[\'"]~', $allIncludeSource, $unusedContinuityMatches);
$dueDatesNoticeCallbackCount = preg_match_all('~[\'"]notices_callback[\'"]\s*=>\s*[\'"]vms_due_render_admin_notices[\'"]~', $allIncludeSource, $unusedDueMatches);
$squareSyncNoticeCallbackCount = preg_match_all('~[\'"]notices_callback[\'"]\s*=>\s*[\'"]vms_square_sync_protection_render_admin_notice[\'"]~', $allIncludeSource, $unusedSquareMatches);
$staffCertificationsNoticeCallbackCount = preg_match_all('~[\'"]notices_callback[\'"]\s*=>\s*function\s*\(\)\s*use\s*\(\s*\$pending\s*\)\s*:\s*void~', $staffCertificationsSource, $unusedStaffMatches);
$emailFollowupsNoticeCallbackCount = preg_match_all('~[\'"]notices_callback[\'"]\s*=>\s*\$render_notices~', $emailFollowupsSource, $unusedEmailFollowupsMatches);
$eventFeedbackNoticeCallbackCount = preg_match_all('~[\'"]notices_callback[\'"]\s*=>\s*[\'"]vms_feedback_admin_render_notices[\'"]~', $eventFeedbackSource, $unusedEventFeedbackMatches);
$ticketIntegrityNoticeCallbackCount = preg_match_all('~[\'"]notices_callback[\'"]\s*=>\s*[\'"]vms_ticket_integrity_render_notice_from_query[\'"]~', $ticketIntegritySource, $unusedTicketIntegrityMatches);
$settingsNoticeCallbackCount = preg_match_all('~[\'"]notices_callback[\'"]\s*=>\s*[\'"]vms_render_settings_page_notices[\'"]~', $settingsSource, $unusedSettingsMatches);
$eventPlanImportNoticeCallbackCount = preg_match_all('~[\'"]notices_callback[\'"]\s*=>\s*\$render_notice~', $eventPlanImportSource, $unusedEventPlanImportMatches);
$socialNoticeCallbackCount = preg_match_all('~[\'"]notices_callback[\'"]\s*=>\s*[\'"]vms_social_render_notices[\'"]~', $allIncludeSource, $unusedSocialMatches);
$expectedActionCallerFiles = array(
	'admin/event-command-center.php',
	'admin/integrity-calendar-reconcile.php',
	'admin/integrity-venue-reconcile.php',
	'admin/schedule.php',
	'admin/ticket-integrity-page.php',
	'admin/vendor-availability.php',
	'admin/vendor-command-center.php',
	'modules/availability-date-dispatch/admin-ui.php',
	'modules/status-notices/admin-ui.php',
	'safety/admin.php',
);
$expectedNoticesCallbackFiles = array(
	'admin/continuity-binder.php',
	'admin/data-tools/page-event-plan-import.php',
	'admin/due-dates.php',
	'admin/event-feedback.php',
	'admin/settings-page.php',
	'admin/square-sync-protection.php',
	'admin/staff-certifications.php',
	'admin/ticket-integrity-page.php',
	'modules/email-followups/admin-ui.php',
	'modules/status-notices/admin-ui.php',
	'social-share/admin.php',
);
$expectedRichNoticesCallbackFiles = array(
	'admin/integrity-venue-reconcile.php',
);
sort($actionCallerFiles);
sort($expectedActionCallerFiles);
$noticesCallbackFiles = array_values(array_unique($noticesCallbackFiles));
sort($noticesCallbackFiles);
sort($expectedNoticesCallbackFiles);
$richNoticesCallbackFiles = array_values(array_unique($richNoticesCallbackFiles));
sort($richNoticesCallbackFiles);
sort($expectedRichNoticesCallbackFiles);
$assert($noticesCallbackCount === 12, 'Only twelve production notices_callback assignments should exist.');
$assert($richNoticesCallbackCount === 1, 'Only one production rich_notices_callback assignment should exist.');
$assert($statusNoticeCallbackCount === 2, 'Status Notices should still contribute exactly two production notices_callback callers.');
$assert($venueRichNoticeCallbackCount === 1, 'Venue Reconciliation should contribute exactly one production rich_notices_callback caller.');
$assert($continuityNoticeCallbackCount === 1, 'Continuity Binder should contribute exactly one production notices_callback caller.');
$assert($dueDatesNoticeCallbackCount === 1, 'Due Dates should contribute exactly one production notices_callback caller.');
$assert($eventFeedbackNoticeCallbackCount === 1, 'Event Feedback should contribute exactly one production notices_callback caller.');
$assert($ticketIntegrityNoticeCallbackCount === 1, 'Ticket Integrity should contribute exactly one production notices_callback caller.');
$assert($settingsNoticeCallbackCount === 1, 'Settings should contribute exactly one production notices_callback caller.');
$assert($squareSyncNoticeCallbackCount === 1, 'Square Sync Protection should contribute exactly one production notices_callback caller.');
$assert($staffCertificationsNoticeCallbackCount === 1, 'Staff Certifications should contribute exactly one production notices_callback caller.');
$assert($emailFollowupsNoticeCallbackCount === 1, 'Email Follow-Ups should contribute exactly one production notices_callback caller.');
$assert($eventPlanImportNoticeCallbackCount === 1, 'Event Plan Import should contribute exactly one production notices_callback caller.');
$assert($socialNoticeCallbackCount === 1, 'Social Sharing should contribute exactly one production notices_callback caller.');
$assert($actionCallerFiles === $expectedActionCallerFiles, 'Header-actions caller inventory should stay limited to the inspected production files.');
$assert($noticesCallbackFiles === $expectedNoticesCallbackFiles, 'Explicit notice callbacks should remain limited to Status Notices, Continuity Binder, Event Plan Import, Due Dates, Event Feedback, Settings, Ticket Integrity, Square Sync Protection, Staff Certifications, Email Follow-Ups, and Social Sharing.');
$assert($richNoticesCallbackFiles === $expectedRichNoticesCallbackFiles, 'Rich explicit notice callbacks should remain limited to Venue Reconciliation.');

$GLOBALS['vms_test_rich_notice_callback_calls'] = 0;
ob_start();
vms_admin_ui_render_shell(
	array(
		'title' => 'Rich Shell Test',
		'rich_notices_callback' => static function (): void {
			$GLOBALS['vms_test_rich_notice_callback_calls']++;
			echo '<div class="notice notice-warning"><p><strong>Heads up.</strong> Continue.</p></div>';
		},
	),
	static function (): void {
		echo '<p>Plain body</p>';
	}
);
$richShellOutput = (string) ob_get_clean();
$assert($GLOBALS['vms_test_rich_notice_callback_calls'] === 1, 'Rich explicit notice callback should execute exactly once.');
$assert(strpos($richShellOutput, '<div class="notice notice-warning below-h2 vms-shell-notice"><p><strong>Heads up.</strong> Continue.</p></div>') !== false, 'Rich explicit notice output should be normalized into the shell notice region.');
$assert(strpos($richShellOutput, 'Heads up.') < strpos($richShellOutput, 'Plain body'), 'Rich explicit notice output should render before ordinary content.');

$GLOBALS['vms_test_rich_notice_callback_calls'] = 0;
ob_start();
vms_admin_ui_render_shell(
	array(
		'title' => 'Rich Shell Empty Test',
		'rich_notices_callback' => static function (): void {
			$GLOBALS['vms_test_rich_notice_callback_calls']++;
		},
	),
	static function (): void {
		echo '<p>Silent body</p>';
	}
);
$richShellEmptyOutput = (string) ob_get_clean();
$assert($GLOBALS['vms_test_rich_notice_callback_calls'] === 1, 'Empty rich explicit notice callback should still execute exactly once.');
$assert(strpos($richShellEmptyOutput, 'vms-shell-notice') === false, 'Empty rich explicit notice output should render nothing in the notice region.');
$assert(strpos($richShellEmptyOutput, 'Silent body') !== false, 'Empty rich explicit notice output should not block ordinary content rendering.');

$assert(strpos($venueReconcileSource, 'function vms_render_integrity_venue_reconcile_notice(): void') !== false, 'Venue Reconciliation should expose a dedicated rich notice callback.');
$venueNoticeStart = strpos($venueReconcileSource, 'function vms_render_integrity_venue_reconcile_notice(): void');
$venueNoticeEnd = strpos($venueReconcileSource, 'function vms_render_integrity_venue_reconcile_page_intro(): void');
$venuePageStart = strpos($venueReconcileSource, 'function vms_render_integrity_venue_reconcile_page(): void');
$venuePageEnd = strpos($venueReconcileSource, 'function vms_render_integrity_venue_reconcile_page_content(): void');
$venueContentStart = strpos($venueReconcileSource, 'function vms_render_integrity_venue_reconcile_page_content(): void');
$venueContentEnd = strpos($venueReconcileSource, 'function vms_render_integrity_venue_reconcile_page_sections(): void');
$venueSectionsStart = strpos($venueReconcileSource, 'function vms_render_integrity_venue_reconcile_page_sections(): void');
$assert($venueNoticeStart !== false && $venueNoticeEnd !== false && $venueNoticeEnd > $venueNoticeStart, 'Venue Reconciliation rich notice callback body should be locatable.');
$assert($venuePageStart !== false && $venuePageEnd !== false && $venuePageEnd > $venuePageStart, 'Venue Reconciliation page renderer body should be locatable.');
$assert($venueContentStart !== false && $venueContentEnd !== false && $venueContentEnd > $venueContentStart, 'Venue Reconciliation content callback body should be locatable.');
$assert($venueSectionsStart !== false, 'Venue Reconciliation page sections helper should be locatable.');
$venueNoticeSource = substr($venueReconcileSource, (int) $venueNoticeStart, (int) $venueNoticeEnd - (int) $venueNoticeStart);
$venuePageSource = substr($venueReconcileSource, (int) $venuePageStart, (int) $venuePageEnd - (int) $venuePageStart);
$venueContentSource = substr($venueReconcileSource, (int) $venueContentStart, (int) $venueContentEnd - (int) $venueContentStart);
$venueSectionsSource = substr($venueReconcileSource, (int) $venueSectionsStart);
$assert(strpos($venuePageSource, "'rich_notices_callback' => 'vms_render_integrity_venue_reconcile_notice'") !== false, 'Venue Reconciliation shell call should route the rich notice family through the dedicated rich callback.');
$assert(strpos($venuePageSource, "'notices_callback' =>") === false, 'Venue Reconciliation should not reuse the simple explicit notice callback path.');
$assert(strpos($calendarReconcileSource, "'rich_notices_callback' =>") === false, 'Calendar Reconciliation should remain unchanged and should not opt into the rich notice callback path in this pass.');
$assert(strpos($calendarReconcileSource, '<div class="notice notice-success"><p><strong>Action complete.</strong> Changed: ') !== false, 'Calendar Reconciliation should retain its original rich notice family unchanged.');
$venueFallbackHeadingPos = strpos($venuePageSource, "echo '<div class=\"wrap\"><h1>' . esc_html__('Integrity: Venue Links', 'backstage-venue-manager') . '</h1>';");
$venueFallbackIntroPos = strpos($venuePageSource, 'vms_render_integrity_venue_reconcile_page_intro();');
$venueFallbackNoticePos = strpos($venuePageSource, 'vms_render_integrity_venue_reconcile_notice();');
$venueFallbackSectionsPos = strpos($venuePageSource, 'vms_render_integrity_venue_reconcile_page_sections();');
$assert($venueFallbackHeadingPos !== false && $venueFallbackIntroPos !== false && $venueFallbackNoticePos !== false && $venueFallbackSectionsPos !== false && $venueFallbackHeadingPos < $venueFallbackIntroPos && $venueFallbackIntroPos < $venueFallbackNoticePos && $venueFallbackNoticePos < $venueFallbackSectionsPos, 'Venue Reconciliation no-shell fallback should preserve heading, intro, notice, then ordinary content ordering.');
$assert(strpos($venueContentSource, 'vms_render_integrity_venue_reconcile_intro') === false, 'Venue Reconciliation should use the dedicated intro helper name consistently.');
$assert(strpos($venueContentSource, 'vms_render_integrity_venue_reconcile_page_intro();') !== false && strpos($venueContentSource, 'vms_render_integrity_venue_reconcile_page_sections();') !== false, 'Venue Reconciliation content callback should render the intro and ordinary page sections.');
$assert(strpos($venueContentSource, 'vms_msg') === false && strpos($venueContentSource, 'vms_changed') === false, 'Venue Reconciliation content callback should no longer read the moved notice query parameters.');
$assert(strpos($venueContentSource, 'Confirmation required.') === false && strpos($venueContentSource, 'Nothing selected.') === false && strpos($venueContentSource, 'Action complete.') === false, 'Venue Reconciliation content callback should no longer emit the moved rich notice family.');
$assert(strpos($venueNoticeSource, "sanitize_key((string) \$_GET['vms_msg'])") !== false, 'Venue Reconciliation rich notice callback should preserve sanitize_key() normalization for vms_msg.');
$assert(strpos($venueNoticeSource, "(int) \$_GET['vms_changed']") !== false, 'Venue Reconciliation rich notice callback should preserve integer normalization for vms_changed.');
$assert(strpos($venueNoticeSource, '<div class="notice notice-warning"><p><strong>Confirmation required.</strong> Check the confirmation box before running an action.</p></div>') !== false, 'Venue Reconciliation rich notice callback should preserve the confirmation-required warning fragment.');
$assert(strpos($venueNoticeSource, '<div class="notice notice-warning"><p><strong>Nothing selected.</strong> Select one or more Event Plans first.</p></div>') !== false, 'Venue Reconciliation rich notice callback should preserve the nothing-selected warning fragment.');
$assert(strpos($venueNoticeSource, '<div class="notice notice-success"><p><strong>Action complete.</strong> Changed: ') !== false, 'Venue Reconciliation rich notice callback should preserve the success fragment.');
$assert(strpos($venueNoticeSource, '<a ') === false && strpos($venueNoticeSource, '<span') === false && strpos($venueNoticeSource, '<em') === false && strpos($venueNoticeSource, '<button') === false && strpos($venueNoticeSource, '<ul') === false && strpos($venueNoticeSource, '<ol') === false && strpos($venueNoticeSource, '<li') === false && strpos($venueNoticeSource, 'data-') === false && strpos($venueNoticeSource, 'role=') === false && strpos($venueNoticeSource, 'style=') === false, 'Venue Reconciliation rich notice callback should remain limited to div[class], p, strong, and text nodes.');
$assert(strpos($venueNoticeSource, 'get_option(') === false && strpos($venueNoticeSource, 'get_transient(') === false && strpos($venueNoticeSource, 'set_transient(') === false && strpos($venueNoticeSource, 'delete_transient(') === false && strpos($venueNoticeSource, 'get_posts(') === false && strpos($venueNoticeSource, 'vms_integrity_list_event_plans_with_venue_issues(') === false, 'Venue Reconciliation rich notice callback should not add provider or storage reads or mutations.');
$assert(strpos($venueNoticeSource, 'apply_filters(') === false && strpos($venueNoticeSource, 'do_action(') === false && strpos($venueNoticeSource, 'settings_errors(') === false && strpos($venueNoticeSource, 'add_settings_error(') === false, 'Venue Reconciliation rich notice callback should remain package-owned and outside hooks or Settings API notice ownership.');
$assert(strpos($venueSectionsSource, 'Review Event Plans that reference Venues') === false, 'Venue Reconciliation page sections helper should not duplicate the intro copy.');

$_GET = array(
	'vms_msg' => 'confirm_required',
);
ob_start();
vms_render_integrity_venue_reconcile_notice();
$venueConfirmNotice = (string) ob_get_clean();
$assert($venueConfirmNotice === '<div class="notice notice-warning"><p><strong>Confirmation required.</strong> Check the confirmation box before running an action.</p></div>', 'Venue Reconciliation rich notice callback should preserve the confirmation-required fragment.');
$assert(wp_kses($venueConfirmNotice, vms_admin_ui_rich_explicit_notice_allowed_html()) === $venueConfirmNotice, 'The rich explicit notice allowlist should admit the confirmation-required Venue Reconciliation notice unchanged.');

$_GET = array(
	'vms_msg' => 'nothing_selected',
);
ob_start();
vms_render_integrity_venue_reconcile_notice();
$venueNothingSelectedNotice = (string) ob_get_clean();
$assert($venueNothingSelectedNotice === '<div class="notice notice-warning"><p><strong>Nothing selected.</strong> Select one or more Event Plans first.</p></div>', 'Venue Reconciliation rich notice callback should preserve the nothing-selected fragment.');

$_GET = array(
	'vms_msg' => 'done',
	'vms_changed' => '9<script>alert(1)</script>',
);
ob_start();
vms_render_integrity_venue_reconcile_notice();
$venueDoneNotice = (string) ob_get_clean();
$assert($venueDoneNotice === '<div class="notice notice-success"><p><strong>Action complete.</strong> Changed: 9</p></div>', 'Venue Reconciliation rich notice callback should preserve the success fragment with integer-normalized changed counts.');
$assert(strpos($venueDoneNotice, '<script') === false, 'Venue Reconciliation changed counts should not become markup.');
$assert(wp_kses($venueDoneNotice, vms_admin_ui_rich_explicit_notice_allowed_html()) === $venueDoneNotice, 'The rich explicit notice allowlist should admit the Venue Reconciliation success notice unchanged.');

$_GET = array(
	'vms_msg' => 'done',
);
ob_start();
vms_render_integrity_venue_reconcile_notice();
$venueDoneMissingCountNotice = (string) ob_get_clean();
$assert($venueDoneMissingCountNotice === '<div class="notice notice-success"><p><strong>Action complete.</strong> Changed: 0</p></div>', 'Venue Reconciliation rich notice callback should default missing changed counts to zero.');

$_GET = array();
ob_start();
vms_render_integrity_venue_reconcile_notice();
$venueMissingNotice = (string) ob_get_clean();
$assert($venueMissingNotice === '', 'Venue Reconciliation rich notice callback should stay silent when vms_msg is absent.');

$_GET = array(
	'vms_msg' => '',
);
ob_start();
vms_render_integrity_venue_reconcile_notice();
$venueEmptyNotice = (string) ob_get_clean();
$assert($venueEmptyNotice === '', 'Venue Reconciliation rich notice callback should stay silent when vms_msg is empty.');

$_GET = array(
	'vms_msg' => 'not_real',
);
ob_start();
vms_render_integrity_venue_reconcile_notice();
$venueUnknownNotice = (string) ob_get_clean();
$assert($venueUnknownNotice === '', 'Venue Reconciliation rich notice callback should stay silent for unknown vms_msg values.');

$_GET = array(
	'vms_msg' => 'Confirm_Required!!!',
);
ob_start();
vms_render_integrity_venue_reconcile_notice();
$venueNormalizedNotice = (string) ob_get_clean();
$assert($venueNormalizedNotice === '<div class="notice notice-warning"><p><strong>Confirmation required.</strong> Check the confirmation box before running an action.</p></div>', 'Venue Reconciliation rich notice callback should preserve sanitize_key() normalization when malformed input collapses into a known slug.');

$GLOBALS['vms_test_integrity_venue_issue_calls'] = 0;
$GLOBALS['vms_test_integrity_venue_issue_limits'] = array();
$GLOBALS['vms_test_integrity_venue_issues_value'] = array(
	'trashed' => array(
		array(
			'plan_id' => 11,
			'venue_id' => 21,
			'plan_title' => 'Summer Fest',
			'venue_title' => 'Old Hall',
		),
	),
	'missing' => array(),
	'unpublished' => array(),
);
$GLOBALS['vms_test_integrity_venue_get_posts_calls'] = 0;
$GLOBALS['vms_test_integrity_venue_get_posts_args'] = array();
$GLOBALS['vms_test_integrity_venue_get_posts_value'] = array(77);
$GLOBALS['vms_test_integrity_venue_titles'] = array(
	77 => 'Replacement Hall',
);
$_GET = array(
	'vms_msg' => 'done',
	'vms_changed' => '12',
	'limit' => '25',
);
ob_start();
vms_render_integrity_venue_reconcile_page();
$venueShellPage = (string) ob_get_clean();
$assert($GLOBALS['vms_test_integrity_venue_issue_calls'] === 1, 'Venue Reconciliation shell render should resolve issue rows exactly once.');
$assert($GLOBALS['vms_test_integrity_venue_issue_limits'] === array(25), 'Venue Reconciliation shell render should preserve the integer-normalized limit parameter.');
$assert($GLOBALS['vms_test_integrity_venue_get_posts_calls'] === 1, 'Venue Reconciliation shell render should resolve replacement venues exactly once.');
$assert(strpos($venueShellPage, '<div class="notice notice-success below-h2 vms-shell-notice"><p><strong>Action complete.</strong> Changed: 12</p></div>') !== false, 'Venue Reconciliation shell render should move the rich notice into the notice region with preserved strong markup and shell notice classes.');
$assert(substr_count($venueShellPage, 'Action complete.') === 1, 'Venue Reconciliation shell render should emit the rich notice exactly once.');
$assert(strpos($venueShellPage, 'Action complete.') < strpos($venueShellPage, 'Review Event Plans that reference Venues in the Trash'), 'Venue Reconciliation shell render should preserve notice-before-content ordering.');
$assert(strpos($venueShellPage, 'Review Event Plans that reference Venues in the Trash') < strpos($venueShellPage, 'Total affected Event Plans:'), 'Venue Reconciliation shell render should preserve the intro before the ordinary page body.');

$GLOBALS['vms_test_integrity_venue_issue_calls'] = 0;
$GLOBALS['vms_test_integrity_venue_issue_limits'] = array();
$GLOBALS['vms_test_integrity_venue_get_posts_calls'] = 0;
$GLOBALS['vms_test_integrity_venue_get_posts_args'] = array();
$_GET = array(
	'vms_msg' => 'confirm_required',
);
ob_start();
vms_render_integrity_venue_reconcile_page_content();
$venueContentOnly = (string) ob_get_clean();
$assert($GLOBALS['vms_test_integrity_venue_issue_calls'] === 1, 'Venue Reconciliation content callback should still resolve issue rows exactly once.');
$assert($GLOBALS['vms_test_integrity_venue_get_posts_calls'] === 1, 'Venue Reconciliation content callback should still resolve replacement venues exactly once.');
$assert(strpos($venueContentOnly, 'Review Event Plans that reference Venues in the Trash') !== false, 'Venue Reconciliation content callback should still render the intro copy.');
$assert(strpos($venueContentOnly, 'Confirmation required.') === false, 'Venue Reconciliation content callback should no longer emit the moved rich notice directly.');

$assert(strpos($settingsSource, 'function vms_render_settings_page_notices(): void') !== false, 'Settings should expose a dedicated explicit notice callback for the fixed default-venue redirect notice.');
$settingsNoticeStart = strpos($settingsSource, 'function vms_render_settings_page_notices(): void');
$settingsNoticeEnd = strpos($settingsSource, 'function vms_render_settings_page()');
$assert($settingsNoticeStart !== false && $settingsNoticeEnd !== false && $settingsNoticeEnd > $settingsNoticeStart, 'Settings explicit notice callback body should be locatable.');
$settingsNoticeSource = substr($settingsSource, (int) $settingsNoticeStart, (int) $settingsNoticeEnd - (int) $settingsNoticeStart);
$settingsPageStart = strpos($settingsSource, 'function vms_render_settings_page()');
$settingsPageEnd = strpos($settingsSource, 'function vms_render_settings_page_content()');
$assert($settingsPageStart !== false && $settingsPageEnd !== false && $settingsPageEnd > $settingsPageStart, 'Settings page renderer body should be locatable.');
$settingsPageSource = substr($settingsSource, (int) $settingsPageStart, (int) $settingsPageEnd - (int) $settingsPageStart);
$settingsContentStart = strpos($settingsSource, 'function vms_render_settings_page_content()');
$settingsContentEnd = strpos($settingsSource, "\n  // Ticketing");
$assert($settingsContentStart !== false && $settingsContentEnd !== false && $settingsContentEnd > $settingsContentStart, 'Settings content callback pre-ticketing body should be locatable.');
$settingsContentPreludeSource = substr($settingsSource, (int) $settingsContentStart, (int) $settingsContentEnd - (int) $settingsContentStart);
$assert(strpos($settingsPageSource, "'notices_callback' => 'vms_render_settings_page_notices'") !== false, 'Settings shell call should route the fixed default-venue redirect notice through the explicit notice callback.');
$assert(strpos($settingsPageSource, "echo '<div class=\"wrap\"><h1>' . esc_html__('VMS Settings', 'backstage-venue-manager') . '</h1>';") !== false, 'Settings no-shell fallback heading should remain locatable.');
$settingsFallbackHeadingPos = strpos($settingsPageSource, "echo '<div class=\"wrap\"><h1>' . esc_html__('VMS Settings', 'backstage-venue-manager') . '</h1>';");
$settingsFallbackNoticePos = strpos($settingsPageSource, 'vms_render_settings_page_notices();');
$settingsFallbackContentPos = strpos($settingsPageSource, 'vms_render_settings_page_content();');
$assert($settingsFallbackHeadingPos !== false && $settingsFallbackNoticePos !== false && $settingsFallbackContentPos !== false && $settingsFallbackHeadingPos < $settingsFallbackNoticePos && $settingsFallbackNoticePos < $settingsFallbackContentPos, 'Settings no-shell fallback should preserve heading, fixed redirect notice, then content ordering.');
$assert(strpos($settingsContentPreludeSource, 'default_venue_set') === false, 'Settings content callback should no longer emit the moved fixed redirect notice directly.');
$assert(strpos($settingsContentPreludeSource, 'Default venue updated.') === false, 'Settings content callback should no longer contain the moved fixed redirect notice copy.');
$assert(strpos($settingsNoticeSource, "isset(\$_GET['vms_notice']) && (string) \$_GET['vms_notice'] === 'default_venue_set'") !== false, 'Settings explicit notice callback should preserve the exact raw redirect-status comparison.');
$assert(strpos($settingsNoticeSource, "<div class=\"notice notice-success\"><p>") !== false, 'Settings explicit notice callback should preserve the fixed simple notice fragment.');
$assert(strpos($settingsNoticeSource, 'Default venue updated.') !== false, 'Settings explicit notice callback should preserve the fixed translated notice copy.');
$assert(strpos($settingsNoticeSource, '<strong>') === false && strpos($settingsNoticeSource, '<a ') === false && strpos($settingsNoticeSource, '<button') === false && strpos($settingsNoticeSource, '<span') === false, 'Settings explicit notice callback should stay within the simple fragment contract.');
$assert(strpos($settingsNoticeSource, 'get_option(') === false && strpos($settingsNoticeSource, 'get_transient(') === false && strpos($settingsNoticeSource, 'set_transient(') === false && strpos($settingsNoticeSource, 'delete_transient(') === false, 'Settings explicit notice callback should not introduce provider or storage reads or mutations.');
$assert(strpos($settingsNoticeSource, 'apply_filters(') === false && strpos($settingsNoticeSource, 'do_action(') === false && strpos($settingsNoticeSource, 'settings_errors(') === false && strpos($settingsNoticeSource, 'add_settings_error(') === false, 'Settings explicit notice callback should remain page-local and outside Settings API notice ownership.');
$assert(strpos($settingsSource, 'settings_errors(') === false && strpos($settingsSource, 'add_settings_error(') === false, 'Settings page should not define a Settings API notice family for this pass.');
$assert(strpos($settingsSource, 'vms-settings-default-venue-alert') !== false, 'Settings page should preserve the richer nested default-venue alert family in ordinary content.');
$assert(strpos($settingsSource, '<strong>Entitlement image sync complete.</strong>') !== false, 'Settings page should preserve the richer entitlement-image-sync notice family in ordinary content.');
$assert(strpos($settingsSource, '<strong>Integrity scan complete.</strong>') !== false, 'Settings page should preserve the richer integrity-scan notice family in ordinary content.');
$assert(strpos($toursAdminSource, '<div class="vms-tours-admin-page" data-vms-tour="guided-tours.settings">') !== false, 'Guided Tours reset notice should remain nested inside the page wrapper.');
$assert(strpos($toursAdminSource, '<div class="notice notice-success is-dismissible" data-vms-tour="guided-tours.reset-notice">') !== false, 'Guided Tours reset notice should remain the extra-attribute nested content boundary and stay unmigrated.');
$assert(strpos($toursAdminSource, "'notices_callback' =>") === false, 'Guided Tours should remain without an explicit notice callback in this pass.');
$assert(strpos($scheduleSource, "echo '<div class=\"vms-admin-schedule-content\">';") !== false, 'Schedule notice families should remain nested inside the schedule content wrapper.');
$assert(strpos($scheduleSource, "echo '<div class=\"notice notice-error\"><p><strong>' . esc_html__('Action required:', 'backstage-venue-manager') . '</strong> ';") !== false, 'Schedule should preserve the richer action-required notice family in ordinary content.');
$assert(strpos($scheduleSource, '<div class="notice notice-warning"><p>Select a venue to view its schedule.</p></div>') !== false, 'Schedule should preserve the nested venue-selection empty-state notice.');
$assert(strpos($scheduleSource, '<div class="notice notice-warning"><p>No venues found to display.</p></div>') !== false, 'Schedule should preserve the nested no-venues empty-state notice.');
$assert(strpos($scheduleSource, "'notices_callback' =>") === false, 'Schedule should remain without an explicit notice callback in this pass.');

$_GET = array(
	'vms_notice' => 'default_venue_set',
);
ob_start();
vms_render_settings_page_notices();
$settingsDefaultVenueNotice = (string) ob_get_clean();
$assert(
	$settingsDefaultVenueNotice === '<div class="notice notice-success"><p>Default venue updated.</p></div>',
	'Settings explicit notice callback should preserve the fixed default-venue notice fragment.'
);
$assert(
	wp_kses($settingsDefaultVenueNotice, vms_admin_ui_explicit_notice_allowed_html()) === $settingsDefaultVenueNotice,
	'The explicit notice allowlist should admit the Settings default-venue notice unchanged.'
);

$_GET = array();
ob_start();
vms_render_settings_page_notices();
$settingsMissingNotice = (string) ob_get_clean();
$assert($settingsMissingNotice === '', 'Settings explicit notice callback should stay silent when the redirect-status flag is absent.');

$_GET = array(
	'vms_notice' => '',
);
ob_start();
vms_render_settings_page_notices();
$settingsEmptyNotice = (string) ob_get_clean();
$assert($settingsEmptyNotice === '', 'Settings explicit notice callback should stay silent when the redirect-status flag is empty.');

$_GET = array(
	'vms_notice' => 'not_real',
);
ob_start();
vms_render_settings_page_notices();
$settingsUnknownNotice = (string) ob_get_clean();
$assert($settingsUnknownNotice === '', 'Settings explicit notice callback should stay silent for unknown redirect-status values.');

$_GET = array(
	'vms_notice' => '<strong>default_venue_set</strong>',
);
ob_start();
vms_render_settings_page_notices();
$settingsMalformedNotice = (string) ob_get_clean();
$assert($settingsMalformedNotice === '', 'Settings explicit notice callback should stay silent for malformed redirect-status values that do not exactly match the fixed slug.');
$assert(strpos($statusSource, 'function vms_status_notice_notice_bar(): void') !== false, 'Status Notices explicit fragment owner should keep a void callback signature.');
$assert(substr_count($statusSource, "'notices_callback' => 'vms_status_notice_notice_bar'") === 2, 'Status Notices list and edit screens should both supply the explicit notice callback.');
$noticeBarStart = strpos($statusSource, 'function vms_status_notice_notice_bar(): void');
$noticeBarEnd = strpos($statusSource, "if (!function_exists('vms_status_notice_render_list_screen'))");
$assert($noticeBarStart !== false && $noticeBarEnd !== false && $noticeBarEnd > $noticeBarStart, 'Status Notice callback body should be locatable.');
$noticeBarSource = substr($statusSource, (int) $noticeBarStart, (int) $noticeBarEnd - (int) $noticeBarStart);
$assert(strpos($noticeBarSource, 'apply_filters(') === false && strpos($noticeBarSource, 'do_action(') === false, 'Status Notice callback should not hand off explicit notice markup through hooks or filters.');
$assert(strpos($noticeBarSource, 'esc_html($message)') !== false, 'Status Notice callback should keep contextual escaping for notice text.');
$assert(strpos($noticeBarSource, '<div class="notice notice-success is-dismissible"><p>') !== false, 'Status Notice callback should keep the fixed explicit notice fragment shape.');

$_GET = array(
	'vms_status_notice_result' => 'saved',
);
ob_start();
vms_status_notice_notice_bar();
$savedNotice = (string) ob_get_clean();
$assert(
	$savedNotice === '<div class="notice notice-success is-dismissible"><p>Status Notice saved.</p></div>',
	'Status Notice callback should preserve the saved notice fragment.'
);
$assert(
	wp_kses($savedNotice, vms_admin_ui_explicit_notice_allowed_html()) === $savedNotice,
	'The explicit notice allowlist should admit the current saved notice fragment unchanged.'
);

$_GET = array(
	'vms_status_notice_result' => 'bulk_updated',
	'bulk_count' => '2',
);
ob_start();
vms_status_notice_notice_bar();
$bulkNotice = (string) ob_get_clean();
$assert(
	$bulkNotice === '<div class="notice notice-success is-dismissible"><p>2 notices updated.</p></div>',
	'Status Notice callback should preserve the bulk-updated notice fragment with inert dynamic text.'
);

$assert(strpos($continuitySource, 'function vms_continuity_binder_render_updated_notice(): void') !== false, 'Continuity Binder should expose a dedicated explicit notice callback.');
$assert(substr_count($continuitySource, "'notices_callback' => 'vms_continuity_binder_render_updated_notice'") === 1, 'Continuity Binder shell call should supply its explicit notice callback exactly once.');
$assert(substr_count($continuitySource, 'notice notice-success is-dismissible') === 1, 'Continuity Binder success notice should have exactly one production emission path.');
$assert(strpos($continuitySource, 'notice notice-warning') !== false, 'Continuity Binder warning notice should remain in the content callback.');
$assert(strpos($continuitySource, 'notice notice-warning"><p><strong>') !== false, 'Continuity Binder warning notice should remain a richer captured family outside the explicit contract.');
$assert(strpos($continuitySource, 'apply_filters(') === false && strpos($continuitySource, 'do_action(') === false, 'Continuity Binder notice paths should not hand off markup through hooks or filters.');
$assert(strpos($continuitySource, 'settings_errors(') === false && strpos($continuitySource, 'do_settings_sections(') === false && strpos($continuitySource, 'wp_editor(') === false, 'Continuity Binder should not route notice markup through Settings API or editor callbacks.');
$updatedNoticeStart = strpos($continuitySource, 'function vms_continuity_binder_render_updated_notice(): void');
$updatedNoticeEnd = strpos($continuitySource, 'function vms_render_continuity_binder_page()');
$assert($updatedNoticeStart !== false && $updatedNoticeEnd !== false && $updatedNoticeEnd > $updatedNoticeStart, 'Continuity Binder explicit notice callback body should be locatable.');
$updatedNoticeSource = substr($continuitySource, (int) $updatedNoticeStart, (int) $updatedNoticeEnd - (int) $updatedNoticeStart);
$contentStart = strpos($continuitySource, 'function vms_render_continuity_binder_page_content()');
$contentEnd = strpos($continuitySource, 'function vms_admin_post_save_continuity_binder()');
$assert($contentStart !== false && $contentEnd !== false && $contentEnd > $contentStart, 'Continuity Binder content callback body should be locatable.');
$continuityContentSource = substr($continuitySource, (int) $contentStart, (int) $contentEnd - (int) $contentStart);
$assert(strpos($updatedNoticeSource, '$_GET[\'updated\'] !== \'1\'') !== false, 'Continuity Binder explicit notice callback should preserve the existing exact display condition.');
$assert(strpos($updatedNoticeSource, 'esc_html__(\'Binder updated.\'') !== false, 'Continuity Binder explicit notice callback should keep contextual escaping for notice text.');
$assert(strpos($updatedNoticeSource, '<div class="notice notice-success is-dismissible"><p>') !== false, 'Continuity Binder explicit notice callback should keep the fixed simple notice fragment.');
$assert(strpos($updatedNoticeSource, '<strong>') === false && strpos($updatedNoticeSource, '<a ') === false && strpos($updatedNoticeSource, '<button') === false, 'Continuity Binder explicit notice callback should not introduce richer markup.');
$assert(strpos($continuityContentSource, 'notice notice-success is-dismissible') === false, 'Continuity Binder content callback should no longer emit the moved success notice.');
$assert(strpos($continuityContentSource, 'notice notice-warning') !== false, 'Continuity Binder content callback should still emit the remaining warning notice.');

$_GET = array(
	'updated' => '1',
);
ob_start();
vms_continuity_binder_render_updated_notice();
$continuityUpdatedNotice = (string) ob_get_clean();
$assert(
	$continuityUpdatedNotice === '<div class="notice notice-success is-dismissible"><p>Binder updated.</p></div>',
	'Continuity Binder explicit notice callback should preserve the updated notice fragment.'
);
$assert(
	wp_kses($continuityUpdatedNotice, vms_admin_ui_explicit_notice_allowed_html()) === $continuityUpdatedNotice,
	'The explicit notice allowlist should admit the Continuity Binder updated notice unchanged.'
);

$_GET = array(
	'updated' => '0',
);
ob_start();
vms_continuity_binder_render_updated_notice();
$continuityNoNotice = (string) ob_get_clean();
$assert($continuityNoNotice === '', 'Continuity Binder explicit notice callback should stay silent when the exact updated flag is absent.');

$assert(strpos($dueDatesSource, 'function vms_due_render_admin_notices(): void') !== false, 'Due Dates should expose a dedicated explicit notice callback.');
$assert(substr_count($dueDatesSource, "'notices_callback' => 'vms_due_render_admin_notices'") === 1, 'Due Dates shell call should supply its explicit notice callback exactly once.');
$assert(strpos($dueDatesSource, 'apply_filters(') === false && strpos($dueDatesSource, 'do_action(') === false, 'Due Dates notice path should not hand off markup through hooks or filters.');
$assert(strpos($dueDatesSource, 'settings_errors(') === false && strpos($dueDatesSource, 'do_settings_sections(') === false && strpos($dueDatesSource, 'wp_editor(') === false, 'Due Dates should not route notice markup through Settings API or editor callbacks.');
$dueNoticeStart = strpos($dueDatesSource, 'function vms_due_render_admin_notices(): void');
$dueNoticeEnd = strpos($dueDatesSource, 'function vms_render_due_dates_admin_page(): void');
$assert($dueNoticeStart !== false && $dueNoticeEnd !== false && $dueNoticeEnd > $dueNoticeStart, 'Due Dates explicit notice callback body should be locatable.');
$dueNoticeSource = substr($dueDatesSource, (int) $dueNoticeStart, (int) $dueNoticeEnd - (int) $dueNoticeStart);
$dueContentStart = strpos($dueDatesSource, 'function vms_render_due_dates_admin_page_content(): void');
$assert($dueContentStart !== false, 'Due Dates content callback body should be locatable.');
$dueDatesContentSource = substr($dueDatesSource, (int) $dueContentStart);
$assert(strpos($dueNoticeSource, 'sanitize_key(vms_due_admin_query_arg(\'vms_due_msg\'))') !== false, 'Due Dates explicit notice callback should preserve the existing sanitized message source.');
$assert(strpos($dueNoticeSource, 'strpos($msg, \'error\') !== false') !== false, 'Due Dates explicit notice callback should preserve the severity mapping.');
$assert(strpos($dueNoticeSource, 'esc_html(str_replace(\'_\', \' \', $msg))') !== false, 'Due Dates explicit notice callback should preserve contextual escaping and wording normalization.');
$assert(strpos($dueNoticeSource, 'notice ' . "' . (\$is_error ? 'notice-error' : 'notice-success') . ' is-dismissible") !== false, 'Due Dates explicit notice callback should keep the exact class family.');
$assert(strpos($dueNoticeSource, '<strong>') === false && strpos($dueNoticeSource, '<a ') === false && strpos($dueNoticeSource, '<button') === false && strpos($dueNoticeSource, '<span') === false, 'Due Dates explicit notice callback should not introduce richer markup.');
$assert(strpos($dueDatesContentSource, 'notice-error') === false && strpos($dueDatesContentSource, 'notice-success') === false && strpos($dueDatesContentSource, 'is-dismissible') === false, 'Due Dates content callback should no longer emit the migrated simple notices.');

$_GET = array(
	'vms_due_msg' => 'payee_added',
);
ob_start();
vms_due_render_admin_notices();
$dueSuccessNotice = (string) ob_get_clean();
$assert(
	$dueSuccessNotice === '<div class="notice notice-success is-dismissible"><p>payee added</p></div>',
	'Due Dates explicit notice callback should preserve the success notice fragment and normalized message text.'
);
$assert(
	wp_kses($dueSuccessNotice, vms_admin_ui_explicit_notice_allowed_html()) === $dueSuccessNotice,
	'The explicit notice allowlist should admit the Due Dates success notice unchanged.'
);

$_GET = array(
	'vms_due_msg' => 'due_complete_error_unknown',
);
ob_start();
vms_due_render_admin_notices();
$dueErrorNotice = (string) ob_get_clean();
$assert(
	$dueErrorNotice === '<div class="notice notice-error is-dismissible"><p>due complete error unknown</p></div>',
	'Due Dates explicit notice callback should preserve the error notice fragment and severity mapping.'
);

$_GET = array(
	'vms_due_msg' => '',
);
ob_start();
vms_due_render_admin_notices();
$dueNoNotice = (string) ob_get_clean();
$assert($dueNoNotice === '', 'Due Dates explicit notice callback should stay silent when no message slug is present.');

$assert(strpos($squareSyncProtectionSource, 'function vms_square_sync_protection_render_admin_notice(): void') !== false, 'Square Sync Protection should expose a dedicated explicit notice callback.');
$assert(substr_count($squareSyncProtectionSource, "'notices_callback' => 'vms_square_sync_protection_render_admin_notice'") === 1, 'Square Sync Protection shell call should supply its explicit notice callback exactly once.');
$assert(strpos($squareSyncProtectionSource, 'apply_filters(') === false && strpos($squareSyncProtectionSource, 'do_action(') === false, 'Square Sync Protection notice path should not hand off explicit notice markup through hooks or filters.');
$assert(strpos($squareSyncProtectionSource, 'settings_errors(') === false && strpos($squareSyncProtectionSource, 'do_settings_sections(') === false && strpos($squareSyncProtectionSource, 'wp_editor(') === false, 'Square Sync Protection should not route notice markup through Settings API or editor callbacks.');
$squareNoticeStart = strpos($squareSyncProtectionSource, 'function vms_square_sync_protection_render_admin_notice(): void');
$squareNoticeEnd = strpos($squareSyncProtectionSource, 'function vms_render_square_sync_protection_page_content(): void');
$assert($squareNoticeStart !== false && $squareNoticeEnd !== false && $squareNoticeEnd > $squareNoticeStart, 'Square Sync Protection explicit notice callback body should be locatable.');
$squareNoticeSource = substr($squareSyncProtectionSource, (int) $squareNoticeStart, (int) $squareNoticeEnd - (int) $squareNoticeStart);
$squareContentStart = strpos($squareSyncProtectionSource, 'function vms_render_square_sync_protection_page_content(): void');
$squareContentEnd = strpos($squareSyncProtectionSource, 'function vms_render_square_sync_protection_page(): void');
$assert($squareContentStart !== false && $squareContentEnd !== false && $squareContentEnd > $squareContentStart, 'Square Sync Protection content callback body should be locatable.');
$squareContentSource = substr($squareSyncProtectionSource, (int) $squareContentStart, (int) $squareContentEnd - (int) $squareContentStart);
$assert(strpos($squareNoticeSource, 'sanitize_key((string) $_GET[\'vms_square_notice\'])') !== false, 'Square Sync Protection explicit notice callback should preserve the existing sanitized notice source.');
$assert(strpos($squareNoticeSource, 'scan_done') !== false && strpos($squareNoticeSource, 'repair_done') !== false, 'Square Sync Protection explicit notice callback should preserve the existing notice conditions.');
$assert(strpos($squareNoticeSource, '<div class="notice notice-info"><p>') !== false, 'Square Sync Protection explicit notice callback should preserve the scan info notice fragment.');
$assert(strpos($squareNoticeSource, '<div class="notice notice-success"><p>') !== false, 'Square Sync Protection explicit notice callback should preserve the repair success notice fragment.');
$assert(strpos($squareNoticeSource, 'esc_html__(\'Square Sync Protection scan complete.\'') !== false, 'Square Sync Protection explicit notice callback should preserve the scan-complete message text.');
$assert(strpos($squareNoticeSource, 'esc_html__(\'Square Sync Protection repair complete.\'') !== false, 'Square Sync Protection explicit notice callback should preserve the repair-complete message text.');
$assert(strpos($squareNoticeSource, '<strong>') === false && strpos($squareNoticeSource, '<a ') === false && strpos($squareNoticeSource, '<button') === false && strpos($squareNoticeSource, '<span') === false, 'Square Sync Protection explicit notice callback should not introduce richer markup.');
$assert(strpos($squareContentSource, 'Square Sync Protection scan complete.') === false && strpos($squareContentSource, 'Square Sync Protection repair complete.') === false, 'Square Sync Protection content callback should no longer emit the migrated simple notices.');

$_GET = array(
	'vms_square_notice' => 'scan_done',
);
ob_start();
vms_square_sync_protection_render_admin_notice();
$squareScanNotice = (string) ob_get_clean();
$assert(
	$squareScanNotice === '<div class="notice notice-info"><p>Square Sync Protection scan complete.</p></div>',
	'Square Sync Protection explicit notice callback should preserve the scan-complete notice fragment.'
);
$assert(
	wp_kses($squareScanNotice, vms_admin_ui_explicit_notice_allowed_html()) === $squareScanNotice,
	'The explicit notice allowlist should admit the Square Sync Protection scan notice unchanged.'
);

$_GET = array(
	'vms_square_notice' => 'repair_done',
);
ob_start();
vms_square_sync_protection_render_admin_notice();
$squareRepairNotice = (string) ob_get_clean();
$assert(
	$squareRepairNotice === '<div class="notice notice-success"><p>Square Sync Protection repair complete.</p></div>',
	'Square Sync Protection explicit notice callback should preserve the repair-complete notice fragment.'
);

$_GET = array(
	'vms_square_notice' => '',
);
ob_start();
vms_square_sync_protection_render_admin_notice();
$squareNoNotice = (string) ob_get_clean();
$assert($squareNoNotice === '', 'Square Sync Protection explicit notice callback should stay silent when no notice slug is present.');

$assert(strpos($staffCertificationsSource, 'function vms_staff_certifications_get_pending_review_items(): array') !== false, 'Staff Certifications should expose a dedicated pending-review loader helper.');
$assert(strpos($staffCertificationsSource, "vms_staffing_get_staff_qualification_review_items('pending_verification')") !== false, 'Staff Certifications should preserve the exact pending-review provider call and argument.');
$assert(substr_count($staffCertificationsSource, "vms_staffing_get_staff_qualification_review_items('pending_verification')") === 1, 'Staff Certifications pending-review provider should appear exactly once in production source.');
$assert(strpos($staffCertificationsSource, 'function vms_staff_certifications_render_empty_state_notice(array $pending): void') !== false, 'Staff Certifications should expose a dedicated empty-state explicit notice helper.');
$assert(strpos($staffCertificationsSource, "'notices_callback' => function () use (\$pending): void {") !== false, 'Staff Certifications shell call should supply a page-local explicit notice closure using the resolved pending dataset.');
$assert(strpos($staffCertificationsSource, "function () use (\$pending): void {\n                    vms_render_staff_certifications_admin_page_content(\$pending);") !== false, 'Staff Certifications shell content callback should use the same resolved pending dataset.');
$assert(strpos($staffCertificationsSource, 'vms_render_staff_certifications_admin_page_content(?array $pending = null): void') !== false, 'Staff Certifications content callback should accept an already-resolved pending dataset.');
$assert(strpos($staffCertificationsSource, 'vms_staff_certifications_render_empty_state_notice($pending);') !== false, 'Staff Certifications fallback renderer should reuse the same pending dataset for the empty-state notice.');
$assert(strpos($staffCertificationsSource, 'if (empty($pending)) {') !== false, 'Staff Certifications should preserve the exact empty-state condition.');
$assert(strpos($staffCertificationsSource, '<div class="notice notice-success inline"><p>') !== false, 'Staff Certifications explicit notice helper should preserve the exact inline success notice fragment.');
$assert(strpos($staffCertificationsSource, 'esc_html__(\'No staff certifications are waiting for review.\'') !== false, 'Staff Certifications explicit notice helper should preserve the exact message translation and escaping function.');
$assert(strpos($staffCertificationsSource, "add_action('admin_notices', function (): void {") !== false, 'Staff Certifications should keep the separate rich warning family on the admin_notices hook.');
$assert(strpos($staffCertificationsSource, '$screen && isset($screen->id) && $screen->id === \'vms_page_vms-staff-certifications\'') !== false, 'Staff Certifications rich warning family should keep its existing screen visibility guard.');
$assert(strpos($staffCertificationsSource, 'notice notice-warning is-dismissible vms-staff-certifications-admin-notice') !== false, 'Staff Certifications should retain the richer global warning notice family markup unchanged.');
$assert(strpos($staffCertificationsSource, '<p><strong>') !== false && strpos($staffCertificationsSource, '<a href="') !== false, 'Staff Certifications rich warning family should remain outside the explicit notice contract.');

ob_start();
vms_staff_certifications_render_empty_state_notice(array());
$staffEmptyNotice = (string) ob_get_clean();
$assert(
	$staffEmptyNotice === '<div class="notice notice-success inline"><p>No staff certifications are waiting for review.</p></div>',
	'Staff Certifications explicit notice helper should preserve the exact inline empty-state notice fragment.'
);
$assert(
	wp_kses($staffEmptyNotice, vms_admin_ui_explicit_notice_allowed_html()) === $staffEmptyNotice,
	'The explicit notice allowlist should admit the Staff Certifications empty-state notice unchanged.'
);

ob_start();
vms_staff_certifications_render_empty_state_notice(
	array(
		array(
			'staff_id' => 17,
		),
	)
);
$staffNonemptyNotice = (string) ob_get_clean();
$assert($staffNonemptyNotice === '', 'Staff Certifications explicit notice helper should stay silent when pending items exist.');

ob_start();
vms_render_staff_certifications_admin_page_content(array());
$staffEmptyContent = (string) ob_get_clean();
$assert(strpos($staffEmptyContent, 'No staff certifications are waiting for review.') === false, 'Staff Certifications content callback should no longer emit the moved empty-state notice.');
$assert(strpos($staffEmptyContent, 'Pending Review') !== false, 'Staff Certifications content callback should still render the summary content for the empty state.');

ob_start();
vms_render_staff_certifications_admin_page_content(
	array(
		array(
			'staff_id' => 29,
			'staff_name' => 'Alex Example',
			'row' => array(
				'name' => 'TABC',
				'submitted_at' => 1710000000,
				'expiration_date' => '2026-12-31',
				'proof_download_url' => 'https://example.test/proof.pdf',
			),
		),
	)
);
$staffNonemptyContent = (string) ob_get_clean();
$assert(strpos($staffNonemptyContent, 'No staff certifications are waiting for review.') === false, 'Staff Certifications content callback should not emit the empty-state notice when pending items exist.');
$assert(strpos($staffNonemptyContent, 'Alex Example') !== false && strpos($staffNonemptyContent, 'TABC') !== false, 'Staff Certifications content callback should keep rendering nonempty review rows from the resolved dataset.');

$GLOBALS['vms_test_staff_certifications_pending_items'] = array();
$GLOBALS['vms_test_staff_certifications_provider_calls'] = 0;
$GLOBALS['vms_test_staff_certifications_provider_statuses'] = array();
ob_start();
vms_render_staff_certifications_admin_page();
$staffEmptyPage = (string) ob_get_clean();
$assert($GLOBALS['vms_test_staff_certifications_provider_calls'] === 1, 'Staff Certifications page renderer should resolve the pending-review dataset exactly once for the empty state.');
$assert($GLOBALS['vms_test_staff_certifications_provider_statuses'] === array('pending_verification'), 'Staff Certifications page renderer should use the existing pending-review provider argument exactly once.');
$assert(substr_count($staffEmptyPage, 'No staff certifications are waiting for review.') === 1, 'Staff Certifications page renderer should emit the empty-state notice exactly once when the resolved dataset is empty.');
$assert(strpos($staffEmptyPage, 'notice notice-success inline') !== false, 'Staff Certifications rendered empty-state notice should preserve the inline class.');
$assert(strpos($staffEmptyPage, 'Alex Example') === false, 'Staff Certifications empty-state render should not leak nonempty-row content.');

$GLOBALS['vms_test_staff_certifications_pending_items'] = array(
	array(
		'staff_id' => 31,
		'staff_name' => 'Jamie Queue',
		'row' => array(
			'name' => 'Food Handler',
			'submitted_at' => 1710001234,
			'expiration_date' => '2027-01-15',
			'proof_download_url' => 'https://example.test/queue-proof.pdf',
		),
	),
);
$GLOBALS['vms_test_staff_certifications_provider_calls'] = 0;
$GLOBALS['vms_test_staff_certifications_provider_statuses'] = array();
ob_start();
vms_render_staff_certifications_admin_page();
$staffNonemptyPage = (string) ob_get_clean();
$assert($GLOBALS['vms_test_staff_certifications_provider_calls'] === 1, 'Staff Certifications page renderer should still resolve the pending-review dataset exactly once for the nonempty state.');
$assert($GLOBALS['vms_test_staff_certifications_provider_statuses'] === array('pending_verification'), 'Staff Certifications nonempty render should keep the existing provider argument.');
$assert(strpos($staffNonemptyPage, 'No staff certifications are waiting for review.') === false, 'Staff Certifications page renderer should emit no empty-state notice when pending items exist.');
$assert(strpos($staffNonemptyPage, 'Jamie Queue') !== false && strpos($staffNonemptyPage, 'Food Handler') !== false, 'Staff Certifications page renderer should keep using the resolved dataset for nonempty page content.');
$assert(strpos($staffNonemptyPage, '>1</strong> certification needs review') !== false, 'Staff Certifications nonempty summary should still use the same resolved dataset count.');

$assert(strpos($socialSource, 'function vms_social_render_notices(): void') !== false, 'Social Sharing should expose a dedicated explicit notice callback.');
$assert(substr_count($socialSource, "'notices_callback' => 'vms_social_render_notices'") === 1, 'Social Sharing shell call should supply its explicit notice callback exactly once.');
$socialNoticeStart = strpos($socialSource, 'function vms_social_render_notices(): void');
$socialNoticeEnd = strpos($socialSource, "if (!function_exists('vms_social_render_admin_page'))");
$assert($socialNoticeStart !== false && $socialNoticeEnd !== false && $socialNoticeEnd > $socialNoticeStart, 'Social Sharing explicit notice callback body should be locatable.');
$socialNoticeSource = substr($socialSource, (int) $socialNoticeStart, (int) $socialNoticeEnd - (int) $socialNoticeStart);
$socialPageStart = strpos($socialSource, 'function vms_social_render_admin_page(): void');
$socialPageEnd = strpos($socialSource, "if (!function_exists('vms_social_render_admin_page_content'))");
$assert($socialPageStart !== false && $socialPageEnd !== false && $socialPageEnd > $socialPageStart, 'Social Sharing page renderer body should be locatable.');
$socialPageSource = substr($socialSource, (int) $socialPageStart, (int) $socialPageEnd - (int) $socialPageStart);
$socialContentStart = strpos($socialSource, 'function vms_social_render_admin_page_content(): void');
$socialContentEnd = strpos($socialSource, "if (!function_exists('vms_social_render_overview_tab'))");
$assert($socialContentStart !== false && $socialContentEnd !== false && $socialContentEnd > $socialContentStart, 'Social Sharing content callback body should be locatable.');
$socialContentSource = substr($socialSource, (int) $socialContentStart, (int) $socialContentEnd - (int) $socialContentStart);
$assert(strpos($socialNoticeSource, 'sanitize_text_field(vms_social_admin_query_arg(\'vms_social_notice\'))') !== false, 'Social Sharing explicit notice callback should preserve the sanitized notice message source.');
$assert(strpos($socialNoticeSource, 'sanitize_key(vms_social_admin_query_arg(\'vms_social_notice_type\'))') !== false, 'Social Sharing explicit notice callback should preserve the sanitized notice type source.');
$assert(strpos($socialNoticeSource, "array('error', 'warning', 'success', 'info')") !== false, 'Social Sharing explicit notice callback should preserve the existing notice type allowlist.');
$assert(strpos($socialNoticeSource, '<div class="notice notice-') !== false && strpos($socialNoticeSource, 'is-dismissible') !== false, 'Social Sharing explicit notice callback should preserve the dismissible notice class family.');
$assert(strpos($socialNoticeSource, 'esc_attr($class)') !== false && strpos($socialNoticeSource, 'esc_html($notice)') !== false, 'Social Sharing explicit notice callback should preserve contextual escaping.');
$assert(strpos($socialNoticeSource, '<strong>') === false && strpos($socialNoticeSource, '<a ') === false && strpos($socialNoticeSource, '<button') === false && strpos($socialNoticeSource, '<span') === false, 'Social Sharing explicit notice callback should stay within the simple fragment contract.');
$assert(strpos($socialPageSource, "'notices_callback' => 'vms_social_render_notices'") !== false, 'Social Sharing page renderer should pass the explicit notice callback through the Administrator shell.');
$assert(strpos($socialPageSource, "echo '<h1>'") !== false && strpos($socialPageSource, 'vms_social_render_notices();') !== false, 'Social Sharing no-shell fallback should preserve the pre-content notice ordering.');
$assert(strpos($socialContentSource, 'vms_social_render_notices();') === false, 'Social Sharing content callback should no longer emit the moved page-local notice family.');

$_GET = array(
	'vms_social_notice' => 'Accounts synced.',
	'vms_social_notice_type' => 'warning',
);
ob_start();
vms_social_render_notices();
$socialWarningNotice = (string) ob_get_clean();
$assert(
	$socialWarningNotice === '<div class="notice notice-warning is-dismissible"><p>Accounts synced.</p></div>',
	'Social Sharing explicit notice callback should preserve the warning notice fragment.'
);
$assert(
	wp_kses($socialWarningNotice, vms_admin_ui_explicit_notice_allowed_html()) === $socialWarningNotice,
	'The explicit notice allowlist should admit the Social Sharing warning notice unchanged.'
);

$_GET = array(
	'vms_social_notice' => '<strong>Queue</strong> run complete.',
	'vms_social_notice_type' => 'danger',
);
ob_start();
vms_social_render_notices();
$socialFallbackNotice = (string) ob_get_clean();
$assert(
	$socialFallbackNotice === '<div class="notice notice-success is-dismissible"><p>Queue run complete.</p></div>',
	'Social Sharing explicit notice callback should sanitize the notice text and fall back unknown types to success.'
);

$_GET = array(
	'vms_social_notice' => '',
	'vms_social_notice_type' => 'info',
);
ob_start();
vms_social_render_notices();
$socialNoNotice = (string) ob_get_clean();
$assert($socialNoNotice === '', 'Social Sharing explicit notice callback should stay silent when no notice text is present.');

$assert(strpos($emailFollowupsSource, 'function vms_email_followups_render_notices(): void') !== false, 'Email Follow-Ups should preserve the dedicated primary redirect notice callback.');
$emailNoticeStart = strpos($emailFollowupsSource, 'function vms_email_followups_render_notices(): void');
$emailNoticeEnd = strpos($emailFollowupsSource, "if (!function_exists('vms_email_followups_render_tabs'))");
$assert($emailNoticeStart !== false && $emailNoticeEnd !== false && $emailNoticeEnd > $emailNoticeStart, 'Email Follow-Ups primary redirect notice callback body should be locatable.');
$emailNoticeSource = substr($emailFollowupsSource, (int) $emailNoticeStart, (int) $emailNoticeEnd - (int) $emailNoticeStart);
$emailPageStart = strpos($emailFollowupsSource, 'function vms_email_followups_render_admin_page(): void');
$emailPageEnd = strpos($emailFollowupsSource, "if (!function_exists('vms_email_followups_render_overview_tab'))");
$assert($emailPageStart !== false && $emailPageEnd !== false && $emailPageEnd > $emailPageStart, 'Email Follow-Ups page renderer body should be locatable.');
$emailPageSource = substr($emailFollowupsSource, (int) $emailPageStart, (int) $emailPageEnd - (int) $emailPageStart);
$emailSelectedPlanStart = strpos($emailFollowupsSource, 'function vms_email_followups_selected_plan_id(?array $event_choices = null): int');
$emailSelectedPlanEnd = strpos($emailFollowupsSource, "if (!function_exists('vms_email_followups_resolve_preview_state'))");
$assert($emailSelectedPlanStart !== false && $emailSelectedPlanEnd !== false && $emailSelectedPlanEnd > $emailSelectedPlanStart, 'Email Follow-Ups selected-plan helper body should be locatable.');
$emailSelectedPlanSource = substr($emailFollowupsSource, (int) $emailSelectedPlanStart, (int) $emailSelectedPlanEnd - (int) $emailSelectedPlanStart);
$emailPreviewStateStart = strpos($emailFollowupsSource, 'function vms_email_followups_resolve_preview_state(): array');
$emailPreviewStateEnd = strpos($emailFollowupsSource, "if (!function_exists('vms_email_followups_render_preview_empty_state_notice'))");
$assert($emailPreviewStateStart !== false && $emailPreviewStateEnd !== false && $emailPreviewStateEnd > $emailPreviewStateStart, 'Email Follow-Ups preview-state resolver body should be locatable.');
$emailPreviewStateSource = substr($emailFollowupsSource, (int) $emailPreviewStateStart, (int) $emailPreviewStateEnd - (int) $emailPreviewStateStart);
$emailPreviewWarningStart = strpos($emailFollowupsSource, 'function vms_email_followups_render_preview_empty_state_notice(array $preview_state): void');
$emailPreviewWarningEnd = strpos($emailFollowupsSource, "if (!function_exists('vms_email_followups_render_page_notices'))");
$assert($emailPreviewWarningStart !== false && $emailPreviewWarningEnd !== false && $emailPreviewWarningEnd > $emailPreviewWarningStart, 'Email Follow-Ups preview warning helper body should be locatable.');
$emailPreviewWarningSource = substr($emailFollowupsSource, (int) $emailPreviewWarningStart, (int) $emailPreviewWarningEnd - (int) $emailPreviewWarningStart);
$emailPageNoticesStart = strpos($emailFollowupsSource, 'function vms_email_followups_render_page_notices(string $tab, array $preview_state = array()): void');
$emailPageNoticesEnd = strpos($emailFollowupsSource, "if (!function_exists('vms_email_followups_render_preview_tab'))");
$assert($emailPageNoticesStart !== false && $emailPageNoticesEnd !== false && $emailPageNoticesEnd > $emailPageNoticesStart, 'Email Follow-Ups page-notices helper body should be locatable.');
$emailPageNoticesSource = substr($emailFollowupsSource, (int) $emailPageNoticesStart, (int) $emailPageNoticesEnd - (int) $emailPageNoticesStart);
$emailPreviewTabStart = strpos($emailFollowupsSource, 'function vms_email_followups_render_preview_tab(array $preview_state = array()): void');
$emailPreviewTabEnd = strpos($emailFollowupsSource, "if (!function_exists('vms_email_followups_render_logs_tab'))");
$assert($emailPreviewTabStart !== false && $emailPreviewTabEnd !== false && $emailPreviewTabEnd > $emailPreviewTabStart, 'Email Follow-Ups preview renderer body should be locatable.');
$emailPreviewTabSource = substr($emailFollowupsSource, (int) $emailPreviewTabStart, (int) $emailPreviewTabEnd - (int) $emailPreviewTabStart);
$assert(strpos($emailNoticeSource, 'sanitize_text_field(wp_unslash((string) $_GET[\'vms_efu_notice\']))') !== false, 'Email Follow-Ups primary redirect notice callback should preserve the sanitized redirect notice source.');
$assert(strpos($emailNoticeSource, 'sanitize_key((string) $_GET[\'vms_efu_notice_type\'])') !== false, 'Email Follow-Ups primary redirect notice callback should preserve the sanitized redirect notice type source.');
$assert(strpos($emailNoticeSource, "array('success', 'error', 'warning', 'info')") !== false, 'Email Follow-Ups primary redirect notice callback should preserve the existing severity allowlist.');
$assert(strpos($emailNoticeSource, '<div class="notice notice-') !== false && strpos($emailNoticeSource, 'is-dismissible') !== false, 'Email Follow-Ups primary redirect notice callback should preserve the dismissible notice class family.');
$assert(strpos($emailNoticeSource, 'esc_attr($type)') !== false && strpos($emailNoticeSource, 'esc_html($notice)') !== false, 'Email Follow-Ups primary redirect notice callback should preserve contextual escaping.');
$assert(strpos($emailNoticeSource, '<strong>') === false && strpos($emailNoticeSource, '<a ') === false && strpos($emailNoticeSource, '<button') === false && strpos($emailNoticeSource, '<span') === false, 'Email Follow-Ups primary redirect notice callback should stay within the simple fragment contract.');
$assert(strpos($emailNoticeSource, 'vms_email_followups_settings(') === false && strpos($emailNoticeSource, 'vms_email_followups_due_items(') === false && strpos($emailNoticeSource, 'vms_email_followups_mailpoet_status(') === false, 'Email Follow-Ups primary redirect notice callback should not resolve page providers or stored state.');
$assert(strpos($emailSelectedPlanSource, 'isset($_GET[\'event_plan_id\']) ? absint($_GET[\'event_plan_id\']) : 0') !== false, 'Email Follow-Ups selected-plan helper should preserve the existing event plan input sanitation.');
$assert(strpos($emailSelectedPlanSource, 'vms_email_followups_event_choices(1)') !== false || strpos($emailSelectedPlanSource, 'vms_email_followups_upcoming_event_choices(1)') !== false, 'Email Follow-Ups selected-plan helper should preserve the original default-choice fallback path.');
$assert(strpos($emailPreviewStateSource, 'isset($_GET[\'event_plan_id\']) ? absint($_GET[\'event_plan_id\']) : 0') !== false, 'Email Follow-Ups preview-state resolver should preserve the existing event selection sanitation.');
$assert(strpos($emailPreviewStateSource, 'vms_email_followups_event_choices(120, $selected_event_plan_id)') !== false, 'Email Follow-Ups preview-state resolver should preserve the full preview choice provider call.');
$assert(strpos($emailPreviewStateSource, 'vms_email_followups_selected_plan_id($event_choices)') !== false, 'Email Follow-Ups preview-state resolver should derive the selected plan from the shared choice list.');
$assert(strpos($emailPreviewStateSource, 'isset($_GET[\'email_key\']) ? sanitize_key((string) $_GET[\'email_key\']) : \'know_before\'') !== false, 'Email Follow-Ups preview-state resolver should preserve the existing email-key sanitation.');
$assert(strpos($emailPreviewStateSource, 'vms_email_followups_template_definitions()') !== false, 'Email Follow-Ups preview-state resolver should preserve the template-definition provider.');
$assert(strpos($emailPreviewStateSource, 'if (!isset($template_definitions[$email_key])) {') !== false, 'Email Follow-Ups preview-state resolver should preserve the unknown-template fallback.');
$assert(strpos($emailPreviewWarningSource, 'if ($event_plan_id > 0) {') !== false, 'Email Follow-Ups preview warning helper should preserve the exact empty-state condition inverse.');
$assert(strpos($emailPreviewWarningSource, '<div class="notice notice-warning inline"><p>') !== false, 'Email Follow-Ups preview warning helper should preserve the exact inline warning fragment.');
$assert(strpos($emailPreviewWarningSource, 'esc_html__(\'No Event Plans found for preview/testing.\'') !== false, 'Email Follow-Ups preview warning helper should preserve the exact message translation and escaping.');
$assert(strpos($emailPageNoticesSource, 'vms_email_followups_render_notices();') !== false, 'Email Follow-Ups page-notices helper should keep the primary redirect notices first.');
$assert(strpos($emailPageNoticesSource, 'if ($tab !== \'preview\') {') !== false, 'Email Follow-Ups page-notices helper should keep the preview warning preview-tab-specific.');
$assert(strpos($emailPageNoticesSource, 'vms_email_followups_render_preview_empty_state_notice($preview_state);') !== false, 'Email Follow-Ups page-notices helper should compose the preview warning after the primary redirect notices.');
$assert(preg_match('~\$preview_state\s*=\s*array\(\);\s*if\s*\(\s*\$tab\s*===\s*[\'"]preview[\'"]\s*\)\s*\{\s*\$preview_state\s*=\s*vms_email_followups_resolve_preview_state\(\);\s*\}~s', $emailPageSource) === 1, 'Email Follow-Ups page renderer should resolve preview state lazily only on the preview tab.');
$assert(preg_match('~\$render_notices\s*=\s*static function \(\) use \(\$tab,\s*\$preview_state\): void \{\s*vms_email_followups_render_page_notices\(\$tab,\s*\$preview_state\);~s', $emailPageSource) === 1, 'Email Follow-Ups page renderer should route page notices through a page-local composed callback.');
$assert(preg_match('~\$render\s*=\s*static function \(\) use \(\$tab,\s*\$preview_state\): void \{.*?elseif \(\$tab === [\'"]preview[\'"]\) \{\s*vms_email_followups_render_preview_tab\(\$preview_state\);~s', $emailPageSource) === 1, 'Email Follow-Ups page renderer should pass the same resolved preview state into the preview content path.');
$assert(strpos($emailPageSource, "'notices_callback' => \$render_notices") !== false, 'Email Follow-Ups shell call should pass the composed page-local notices callback.');
$assert(preg_match('~echo\s+[\'"]<div class="wrap" id="vms-email-followups-admin"><h1>[\'"].*?\$render_notices\(\);\s*\$render\(\);~s', $emailPageSource) === 1, 'Email Follow-Ups no-shell fallback should preserve heading, notices, then content ordering.');
$assert(strpos($emailPreviewTabSource, 'if (empty($preview_state)) {') !== false && strpos($emailPreviewTabSource, 'vms_email_followups_resolve_preview_state();') !== false, 'Email Follow-Ups preview renderer should preserve standalone compatibility by resolving preview state when none is passed.');
$assert(preg_match('~\$template_definitions\s*=\s*isset\(\$preview_state\[\'template_definitions\'\]\)\s*&&\s*is_array\(\$preview_state\[\'template_definitions\'\]\)\s*\?\s*\$preview_state\[\'template_definitions\'\]\s*:\s*vms_email_followups_template_definitions\(\);~s', $emailPreviewTabSource) === 1, 'Email Follow-Ups preview renderer should prefer the shared preview state data when it is provided.');
$assert(strpos($emailPreviewTabSource, 'vms_email_followups_render_preview_empty_state_notice') === false, 'Email Follow-Ups preview renderer should not emit the moved empty-state warning directly.');
$assert(strpos($emailPreviewTabSource, 'No Event Plans found for preview/testing.') === false, 'Email Follow-Ups preview renderer should no longer emit the moved warning message from the content path.');
$assert(strpos($emailPreviewTabSource, 'if ($event_plan_id <= 0) {') !== false && strpos($emailPreviewTabSource, 'return;') !== false, 'Email Follow-Ups preview renderer should preserve the exact empty-state early return without the moved warning emission.');

$_GET = array(
	'vms_efu_notice' => 'Email follow-up settings saved.',
	'vms_efu_notice_type' => 'success',
);
ob_start();
vms_email_followups_render_notices();
$emailSuccessNotice = (string) ob_get_clean();
$assert(
	$emailSuccessNotice === '<div class="notice notice-success is-dismissible"><p>Email follow-up settings saved.</p></div>',
	'Email Follow-Ups primary redirect notice callback should preserve the success notice fragment.'
);
$assert(
	wp_kses($emailSuccessNotice, vms_admin_ui_explicit_notice_allowed_html()) === $emailSuccessNotice,
	'The explicit notice allowlist should admit the Email Follow-Ups success notice unchanged.'
);

$_GET = array(
	'vms_efu_notice' => 'Test send failed.',
	'vms_efu_notice_type' => 'error',
);
ob_start();
vms_email_followups_render_notices();
$emailErrorNotice = (string) ob_get_clean();
$assert(
	$emailErrorNotice === '<div class="notice notice-error is-dismissible"><p>Test send failed.</p></div>',
	'Email Follow-Ups primary redirect notice callback should preserve the error notice fragment.'
);

$_GET = array(
	'vms_efu_notice' => 'Manual send was not confirmed, so no recipient emails were sent.',
	'vms_efu_notice_type' => 'warning',
);
ob_start();
vms_email_followups_render_notices();
$emailWarningNotice = (string) ob_get_clean();
$assert(
	$emailWarningNotice === '<div class="notice notice-warning is-dismissible"><p>Manual send was not confirmed, so no recipient emails were sent.</p></div>',
	'Email Follow-Ups primary redirect notice callback should preserve the warning notice fragment.'
);

$_GET = array(
	'vms_efu_notice' => 'Preview batch ready.',
	'vms_efu_notice_type' => 'info',
);
ob_start();
vms_email_followups_render_notices();
$emailInfoNotice = (string) ob_get_clean();
$assert(
	$emailInfoNotice === '<div class="notice notice-info is-dismissible"><p>Preview batch ready.</p></div>',
	'Email Follow-Ups primary redirect notice callback should preserve the info notice fragment.'
);

$_GET = array(
	'vms_efu_notice' => '<strong>Queue</strong> run complete.',
	'vms_efu_notice_type' => 'danger',
);
ob_start();
vms_email_followups_render_notices();
$emailFallbackNotice = (string) ob_get_clean();
$assert(
	$emailFallbackNotice === '<div class="notice notice-success is-dismissible"><p>Queue run complete.</p></div>',
	'Email Follow-Ups primary redirect notice callback should sanitize the notice text and fall back unknown types to success.'
);

$_GET = array(
	'vms_efu_notice' => '',
	'vms_efu_notice_type' => 'warning',
);
ob_start();
vms_email_followups_render_notices();
$emailNoNotice = (string) ob_get_clean();
$assert($emailNoNotice === '', 'Email Follow-Ups primary redirect notice callback should stay silent when no notice text is present.');

ob_start();
vms_email_followups_render_preview_empty_state_notice(array('event_plan_id' => 0));
$emailPreviewWarningNotice = (string) ob_get_clean();
$assert(
	$emailPreviewWarningNotice === '<div class="notice notice-warning inline"><p>No Event Plans found for preview/testing.</p></div>',
	'Email Follow-Ups preview warning helper should preserve the exact warning fragment.'
);
$assert(
	wp_kses($emailPreviewWarningNotice, vms_admin_ui_explicit_notice_allowed_html()) === $emailPreviewWarningNotice,
	'The explicit notice allowlist should admit the Email Follow-Ups preview warning unchanged.'
);

ob_start();
vms_email_followups_render_preview_empty_state_notice(array('event_plan_id' => 44));
$emailNoPreviewWarning = (string) ob_get_clean();
$assert($emailNoPreviewWarning === '', 'Email Follow-Ups preview warning helper should stay silent when the preview state is nonempty.');

$_GET = array(
	'vms_efu_notice' => 'Email follow-up settings saved.',
	'vms_efu_notice_type' => 'success',
);
ob_start();
vms_email_followups_render_page_notices('preview', array('event_plan_id' => 0));
$emailComposedPreviewNotices = (string) ob_get_clean();
$assert(
	$emailComposedPreviewNotices === '<div class="notice notice-success is-dismissible"><p>Email follow-up settings saved.</p></div><div class="notice notice-warning inline"><p>No Event Plans found for preview/testing.</p></div>',
	'Email Follow-Ups page-notices helper should preserve redirect-notice-before-preview-warning ordering.'
);

ob_start();
vms_email_followups_render_page_notices('overview', array('event_plan_id' => 0));
$emailComposedOverviewNotices = (string) ob_get_clean();
$assert(
	$emailComposedOverviewNotices === '<div class="notice notice-success is-dismissible"><p>Email follow-up settings saved.</p></div>',
	'Email Follow-Ups page-notices helper should not emit the preview warning on non-preview tabs.'
);

$GLOBALS['vms_test_email_followups_event_choice_posts'] = array(
	new WP_Post(321),
);
$GLOBALS['vms_test_email_followups_event_choice_labels'] = array(
	321 => '2026-08-01 - Summer Fest',
);
$GLOBALS['vms_test_email_followups_event_choices_calls'] = 0;
$GLOBALS['vms_test_email_followups_event_choices_args'] = array();
$GLOBALS['vms_test_email_followups_template_definitions_calls'] = 0;
$_GET = array(
	'tab' => 'preview',
	'email_key' => 'not-real',
);
$emailPreviewState = vms_email_followups_resolve_preview_state();
$assert($GLOBALS['vms_test_email_followups_event_choices_calls'] === 1, 'Email Follow-Ups preview-state resolver should resolve event choices exactly once.');
$assert($GLOBALS['vms_test_email_followups_event_choices_args'] === array(array(120, 0)), 'Email Follow-Ups preview-state resolver should preserve the original empty selection provider arguments.');
$assert($GLOBALS['vms_test_email_followups_template_definitions_calls'] === 1, 'Email Follow-Ups preview-state resolver should resolve template definitions exactly once.');
$assert($emailPreviewState['event_plan_id'] === 321, 'Email Follow-Ups preview-state resolver should preserve the first available choice as the default event plan.');
$assert($emailPreviewState['email_key'] === 'know_before', 'Email Follow-Ups preview-state resolver should preserve the unknown template fallback.');

$GLOBALS['vms_test_email_followups_event_choices_calls'] = 0;
$GLOBALS['vms_test_email_followups_event_choices_args'] = array();
$GLOBALS['vms_test_email_followups_template_definitions_calls'] = 0;
$GLOBALS['vms_test_email_followups_event_context_calls'] = 0;
$GLOBALS['vms_test_email_followups_event_recipients_calls'] = 0;
$GLOBALS['vms_test_email_followups_render_message_calls'] = 0;
$GLOBALS['vms_test_email_followups_scheduled_timestamp_calls'] = 0;
$GLOBALS['vms_test_email_followups_context_allows_send_calls'] = 0;
$GLOBALS['vms_test_email_followups_manual_batch_size_calls'] = 0;
$GLOBALS['vms_test_email_followups_settings_calls'] = 0;
$GLOBALS['vms_test_email_followups_mailpoet_status_calls'] = 0;
$GLOBALS['vms_test_email_followups_due_items_calls'] = 0;
$_GET = array(
	'tab' => 'overview',
	'vms_efu_notice' => 'Email follow-up settings saved.',
	'vms_efu_notice_type' => 'success',
);
ob_start();
vms_email_followups_render_admin_page();
$emailOverviewPage = (string) ob_get_clean();
$assert($GLOBALS['vms_test_email_followups_settings_calls'] === 1, 'Email Follow-Ups overview render should resolve settings exactly once.');
$assert($GLOBALS['vms_test_email_followups_mailpoet_status_calls'] === 1, 'Email Follow-Ups overview render should resolve MailPoet status exactly once.');
$assert($GLOBALS['vms_test_email_followups_due_items_calls'] === 1, 'Email Follow-Ups overview render should resolve due items exactly once.');
$assert($GLOBALS['vms_test_email_followups_event_choices_calls'] === 0, 'Email Follow-Ups overview render should not resolve preview event choices.');
$assert($GLOBALS['vms_test_email_followups_template_definitions_calls'] === 0, 'Email Follow-Ups overview render should not resolve preview template definitions.');
$assert($GLOBALS['vms_test_email_followups_event_context_calls'] === 0 && $GLOBALS['vms_test_email_followups_event_recipients_calls'] === 0 && $GLOBALS['vms_test_email_followups_render_message_calls'] === 0, 'Email Follow-Ups overview render should not invoke preview-only providers.');
$assert(substr_count($emailOverviewPage, 'Email follow-up settings saved.') === 1, 'Email Follow-Ups overview render should emit the primary redirect notice exactly once.');
$assert(strpos($emailOverviewPage, 'No Event Plans found for preview/testing.') === false, 'Email Follow-Ups overview render should not emit the preview warning.');
$assert(strpos($emailOverviewPage, 'Email follow-up settings saved.') < strpos($emailOverviewPage, 'vms-email-followups-tabs'), 'Email Follow-Ups overview shell output should keep the primary redirect notice before tabs.');

$GLOBALS['vms_test_email_followups_event_choice_posts'] = array();
$GLOBALS['vms_test_email_followups_event_choice_labels'] = array();
$GLOBALS['vms_test_email_followups_event_choices_calls'] = 0;
$GLOBALS['vms_test_email_followups_event_choices_args'] = array();
$GLOBALS['vms_test_email_followups_template_definitions_calls'] = 0;
$GLOBALS['vms_test_email_followups_event_context_calls'] = 0;
$GLOBALS['vms_test_email_followups_event_recipients_calls'] = 0;
$GLOBALS['vms_test_email_followups_render_message_calls'] = 0;
$GLOBALS['vms_test_email_followups_scheduled_timestamp_calls'] = 0;
$GLOBALS['vms_test_email_followups_context_allows_send_calls'] = 0;
$GLOBALS['vms_test_email_followups_manual_batch_size_calls'] = 0;
$GLOBALS['vms_test_email_followups_settings_calls'] = 0;
$_GET = array(
	'tab' => 'preview',
	'vms_efu_notice' => 'Email follow-up settings saved.',
	'vms_efu_notice_type' => 'success',
	'email_key' => 'know_before',
);
ob_start();
vms_email_followups_render_admin_page();
$emailEmptyPreviewPage = (string) ob_get_clean();
$assert($GLOBALS['vms_test_email_followups_event_choices_calls'] === 1, 'Email Follow-Ups empty preview render should resolve event choices exactly once.');
$assert($GLOBALS['vms_test_email_followups_event_choices_args'] === array(array(120, 0)), 'Email Follow-Ups empty preview render should keep the original preview choice provider arguments.');
$assert($GLOBALS['vms_test_email_followups_template_definitions_calls'] === 1, 'Email Follow-Ups empty preview render should resolve template definitions exactly once.');
$assert($GLOBALS['vms_test_email_followups_settings_calls'] === 1, 'Email Follow-Ups empty preview render should resolve settings exactly once.');
$assert($GLOBALS['vms_test_email_followups_event_context_calls'] === 0 && $GLOBALS['vms_test_email_followups_event_recipients_calls'] === 0 && $GLOBALS['vms_test_email_followups_render_message_calls'] === 0 && $GLOBALS['vms_test_email_followups_scheduled_timestamp_calls'] === 0 && $GLOBALS['vms_test_email_followups_context_allows_send_calls'] === 0 && $GLOBALS['vms_test_email_followups_manual_batch_size_calls'] === 0, 'Email Follow-Ups empty preview render should not invoke nonempty preview providers.');
$assert(substr_count($emailEmptyPreviewPage, 'Email follow-up settings saved.') === 1, 'Email Follow-Ups empty preview render should preserve the primary redirect notice exactly once.');
$assert(substr_count($emailEmptyPreviewPage, 'No Event Plans found for preview/testing.') === 1, 'Email Follow-Ups empty preview render should emit the preview warning exactly once.');
$assert(strpos($emailEmptyPreviewPage, 'Email follow-up settings saved.') < strpos($emailEmptyPreviewPage, 'No Event Plans found for preview/testing.'), 'Email Follow-Ups empty preview shell output should keep the primary redirect notice before the preview warning.');
$assert(strpos($emailEmptyPreviewPage, 'No Event Plans found for preview/testing.') < strpos($emailEmptyPreviewPage, 'vms-email-followups-tabs'), 'Email Follow-Ups empty preview shell output should place the preview warning before tabs.');
$assert(strpos($emailEmptyPreviewPage, 'vms-email-followups-tabs') < strpos($emailEmptyPreviewPage, 'vms-efu-filter-form'), 'Email Follow-Ups empty preview shell output should preserve tabs before the preview filter form.');
$assert(strpos($emailEmptyPreviewPage, 'notice notice-warning inline below-h2 vms-shell-notice') !== false, 'Email Follow-Ups empty preview shell output should preserve the warning classes after shell normalization.');

$GLOBALS['vms_test_email_followups_event_choice_posts'] = array(
	new WP_Post(321),
);
$GLOBALS['vms_test_email_followups_event_choice_labels'] = array(
	321 => '2026-08-01 - Summer Fest',
);
$GLOBALS['vms_test_email_followups_event_context_value'] = array(
	'valid' => true,
	'event_plan_id' => 321,
	'event_name' => 'Summer Fest',
	'event_date' => '2026-08-01',
	'event_date_label' => 'Saturday, August 1, 2026',
	'post_status' => 'publish',
	'plan_status' => 'scheduled',
);
$GLOBALS['vms_test_email_followups_event_recipients_value'] = array(
	'recipients' => array(
		array(
			'email' => 'buyer@example.test',
			'name' => 'Buyer Example',
			'qty' => 2,
			'order_numbers' => array('1001'),
		),
	),
	'counts' => array(
		'tickets_net' => 2,
	),
);
$GLOBALS['vms_test_email_followups_render_message_value'] = array(
	'subject' => 'Know Before You Go',
	'body_html' => '<p>Rendered preview body.</p>',
	'tokens' => array(
		'{feedback_url}' => '',
	),
);
$GLOBALS['vms_test_email_followups_scheduled_timestamp_value'] = 0;
$GLOBALS['vms_test_email_followups_context_allows_send_value'] = array(true, 'ok');
$GLOBALS['vms_test_email_followups_event_choices_calls'] = 0;
$GLOBALS['vms_test_email_followups_event_choices_args'] = array();
$GLOBALS['vms_test_email_followups_template_definitions_calls'] = 0;
$GLOBALS['vms_test_email_followups_event_context_calls'] = 0;
$GLOBALS['vms_test_email_followups_event_recipients_calls'] = 0;
$GLOBALS['vms_test_email_followups_render_message_calls'] = 0;
$GLOBALS['vms_test_email_followups_scheduled_timestamp_calls'] = 0;
$GLOBALS['vms_test_email_followups_context_allows_send_calls'] = 0;
$GLOBALS['vms_test_email_followups_manual_batch_size_calls'] = 0;
$GLOBALS['vms_test_email_followups_settings_calls'] = 0;
$_GET = array(
	'tab' => 'preview',
	'email_key' => 'know_before',
);
ob_start();
vms_email_followups_render_admin_page();
$emailNonemptyPreviewPage = (string) ob_get_clean();
$assert($GLOBALS['vms_test_email_followups_event_choices_calls'] === 1, 'Email Follow-Ups nonempty preview render should resolve event choices exactly once.');
$assert($GLOBALS['vms_test_email_followups_template_definitions_calls'] === 1, 'Email Follow-Ups nonempty preview render should resolve template definitions exactly once.');
$assert($GLOBALS['vms_test_email_followups_settings_calls'] === 1, 'Email Follow-Ups nonempty preview render should resolve settings exactly once.');
$assert($GLOBALS['vms_test_email_followups_event_context_calls'] === 1, 'Email Follow-Ups nonempty preview render should resolve the event context exactly once.');
$assert($GLOBALS['vms_test_email_followups_event_recipients_calls'] === 1, 'Email Follow-Ups nonempty preview render should resolve recipients exactly once.');
$assert($GLOBALS['vms_test_email_followups_render_message_calls'] === 1, 'Email Follow-Ups nonempty preview render should render the message exactly once.');
$assert($GLOBALS['vms_test_email_followups_scheduled_timestamp_calls'] === 1, 'Email Follow-Ups nonempty preview render should resolve the scheduled timestamp exactly once.');
$assert($GLOBALS['vms_test_email_followups_context_allows_send_calls'] === 1, 'Email Follow-Ups nonempty preview render should evaluate the send guard exactly once.');
$assert($GLOBALS['vms_test_email_followups_manual_batch_size_calls'] === 1, 'Email Follow-Ups nonempty preview render should preserve the manual batch size call count.');
$assert(strpos($emailNonemptyPreviewPage, 'No Event Plans found for preview/testing.') === false, 'Email Follow-Ups nonempty preview render should not emit the moved preview warning.');
$assert(strpos($emailNonemptyPreviewPage, 'Recipient Preview') !== false && strpos($emailNonemptyPreviewPage, 'Rendered Email') !== false, 'Email Follow-Ups nonempty preview render should preserve the main preview output sections.');
$assert(strpos($emailNonemptyPreviewPage, 'Know Before You Go') !== false && strpos($emailNonemptyPreviewPage, 'Rendered preview body.') !== false, 'Email Follow-Ups nonempty preview render should preserve the rendered message output.');
$assert(strpos($emailNonemptyPreviewPage, 'buyer@example.test') !== false && strpos($emailNonemptyPreviewPage, 'Buyer Example') !== false, 'Email Follow-Ups nonempty preview render should preserve the recipient preview output.');
$assert(strpos($emailNonemptyPreviewPage, 'value="321" selected="selected"') !== false, 'Email Follow-Ups nonempty preview render should preserve the selected event choice in the preview form.');

$assert(strpos($ticketIntegritySource, 'function vms_ticket_integrity_render_notice_from_query(): void') !== false, 'Ticket Integrity should preserve the dedicated top-level query notice helper.');
$ticketIntegrityNoticeStart = strpos($ticketIntegritySource, 'function vms_ticket_integrity_render_notice_from_query(): void');
$ticketIntegrityNoticeEnd = strpos($ticketIntegritySource, 'function vms_ticket_integrity_render_summary_cards(array $summary, array $last_scan): void');
$assert($ticketIntegrityNoticeStart !== false && $ticketIntegrityNoticeEnd !== false && $ticketIntegrityNoticeEnd > $ticketIntegrityNoticeStart, 'Ticket Integrity notice helper body should be locatable.');
$ticketIntegrityNoticeSource = substr($ticketIntegritySource, (int) $ticketIntegrityNoticeStart, (int) $ticketIntegrityNoticeEnd - (int) $ticketIntegrityNoticeStart);
$ticketIntegrityPageStart = strpos($ticketIntegritySource, 'function vms_ticket_integrity_render_admin_page(): void');
$assert($ticketIntegrityPageStart !== false, 'Ticket Integrity page renderer body should be locatable.');
$ticketIntegrityPageSource = substr($ticketIntegritySource, (int) $ticketIntegrityPageStart);
$ticketIntegrityContentStart = strpos($ticketIntegrityPageSource, 'static function () use (');
$ticketIntegrityContentEnd = strpos($ticketIntegrityPageSource, "echo '<div class=\"wrap\"><h1>'");
$assert($ticketIntegrityContentStart !== false && $ticketIntegrityContentEnd !== false && $ticketIntegrityContentEnd > $ticketIntegrityContentStart, 'Ticket Integrity shell content closure body should be locatable.');
$ticketIntegrityContentSource = substr($ticketIntegrityPageSource, (int) $ticketIntegrityContentStart, (int) $ticketIntegrityContentEnd - (int) $ticketIntegrityContentStart);
$ticketIntegrityRebuildStart = strpos($ticketIntegritySource, 'function vms_ticket_integrity_handle_rebuild(): void');
$ticketIntegrityRebuildEnd = strpos($ticketIntegritySource, "function vms_ticket_integrity_find_ticket_config_row(array \$context, string \$ticket_key, string \$title_token = ''): array");
$assert($ticketIntegrityRebuildStart !== false && $ticketIntegrityRebuildEnd !== false && $ticketIntegrityRebuildEnd > $ticketIntegrityRebuildStart, 'Ticket Integrity rebuild handler body should be locatable.');
$ticketIntegrityRebuildSource = substr($ticketIntegritySource, (int) $ticketIntegrityRebuildStart, (int) $ticketIntegrityRebuildEnd - (int) $ticketIntegrityRebuildStart);
$ticketIntegrityDuplicateStart = strpos($ticketIntegritySource, 'function vms_ticket_integrity_handle_duplicate_cleanup(): void');
$ticketIntegrityDuplicateEnd = strpos($ticketIntegritySource, 'function vms_ticket_integrity_report_text_value($value): string');
$assert($ticketIntegrityDuplicateStart !== false && $ticketIntegrityDuplicateEnd !== false && $ticketIntegrityDuplicateEnd > $ticketIntegrityDuplicateStart, 'Ticket Integrity duplicate-cleanup handler body should be locatable.');
$ticketIntegrityDuplicateSource = substr($ticketIntegritySource, (int) $ticketIntegrityDuplicateStart, (int) $ticketIntegrityDuplicateEnd - (int) $ticketIntegrityDuplicateStart);
$assert(strpos($ticketIntegrityPageSource, "'notices_callback' => 'vms_ticket_integrity_render_notice_from_query'") !== false, 'Ticket Integrity shell call should route the full notice helper through the explicit notice callback.');
$assert(strpos($ticketIntegrityContentSource, 'vms_ticket_integrity_render_notice_from_query();') === false, 'Ticket Integrity shell content closure should no longer call the moved notice helper directly.');
$assert(strpos($ticketIntegrityContentSource, "echo '<section class=\"vms-ticket-integrity__panel\" data-vms-tour=\"ticket-integrity.run\">'") !== false, 'Ticket Integrity shell content closure should still begin with the run panel after the moved notice boundary.');
$assert(strpos($ticketIntegrityPageSource, "echo '<div class=\"wrap\"><h1>' . esc_html__('Ticket Integrity', 'backstage-venue-manager') . '</h1></div>';") !== false, 'Ticket Integrity no-shell fallback should preserve the historical heading-only output path.');
$assert(strpos($ticketIntegrityNoticeSource, "sanitize_key(vms_ticket_integrity_query_arg('tim_notice'))") !== false, 'Ticket Integrity notice helper should preserve selector sanitization.');
$assert(strpos($ticketIntegrityNoticeSource, "sanitize_text_field(vms_ticket_integrity_query_arg('detail'))") !== false, 'Ticket Integrity notice helper should preserve detail sanitization.');
$assert(strpos($ticketIntegrityNoticeSource, "sanitize_email(vms_ticket_integrity_query_arg('recipient'))") !== false, 'Ticket Integrity notice helper should preserve recipient sanitization.');
$assert(strpos($ticketIntegrityNoticeSource, "absint(vms_ticket_integrity_query_arg('red'))") !== false && strpos($ticketIntegrityNoticeSource, "absint(vms_ticket_integrity_query_arg('yellow'))") !== false, 'Ticket Integrity notice helper should preserve red/yellow count normalization.');
$assert(substr_count($ticketIntegrityNoticeSource, "message .= ' ' . \$detail;") === 9, 'Ticket Integrity notice helper should preserve every detail-text append branch.');
$assert(substr_count($ticketIntegrityNoticeSource, "message .= ' ' . \$notice_recipient;") === 1, 'Ticket Integrity notice helper should preserve the single recipient-text append branch.');
$assert(strpos($ticketIntegrityNoticeSource, "echo '<div class=\"notice ' . esc_attr(\$class) . '\"><p>' . esc_html(\$message) . '</p></div>';") !== false, 'Ticket Integrity notice helper should preserve the exact non-inline, non-dismissible simple notice markup.');
$assert(strpos($ticketIntegrityNoticeSource, '<strong>') === false && strpos($ticketIntegrityNoticeSource, '<a ') === false && strpos($ticketIntegrityNoticeSource, '<button') === false && strpos($ticketIntegrityNoticeSource, '<span') === false, 'Ticket Integrity notice helper should stay within the simple fragment contract.');
$assert(strpos($ticketIntegrityNoticeSource, 'apply_filters(') === false && strpos($ticketIntegrityNoticeSource, 'do_action(') === false && strpos($ticketIntegrityNoticeSource, 'settings_errors(') === false, 'Ticket Integrity notice helper should stay package-owned and nonextensible.');
$assert(strpos($ticketIntegrityNoticeSource, 'get_transient(') === false && strpos($ticketIntegrityNoticeSource, 'set_transient(') === false && strpos($ticketIntegrityNoticeSource, 'delete_transient(') === false, 'Ticket Integrity notice helper should not perform storage reads or mutations.');
$assert(strpos($vendorAvailabilitySource, "echo '<div class=\"notice notice-info inline\"><p>' . esc_html__('No vendors matched the current filters for this date.', 'backstage-venue-manager') . '</p></div>';") !== false, 'Vendor Availability nested empty-state notice should remain content-local and unchanged.');

$ticketIntegrityExpectedStatuses = array(
	'daily_report_dry_run_ready',
	'daily_report_failed',
	'daily_report_preview_ready',
	'daily_report_sent',
	'daily_report_test_sent',
	'duplicate_cleanup_blocked',
	'duplicate_cleanup_complete',
	'duplicate_cleanup_failed',
	'duplicate_cleanup_partial',
	'event_scan_complete',
	'event_scan_failed',
	'rebuild_blocked',
	'rebuild_complete',
	'rebuild_failed',
	'rebuild_no_change',
	'rebuild_partial',
	'scan_complete',
	'scan_failed',
	'settings_saved',
);
$ticketIntegrityExpectedQueryArgs = array('detail', 'recipient', 'red', 'tim_notice', 'yellow');
preg_match_all("~vms_ticket_integrity_query_arg\\('([a-z_]+)'\\)~", $ticketIntegrityNoticeSource, $ticketIntegrityQueryArgMatches);
$ticketIntegrityQueryArgs = array_values(array_unique($ticketIntegrityQueryArgMatches[1]));
sort($ticketIntegrityQueryArgs);
sort($ticketIntegrityExpectedQueryArgs);
$assert($ticketIntegrityQueryArgs === $ticketIntegrityExpectedQueryArgs, 'Ticket Integrity notice helper should keep the exact query-argument inventory.');
preg_match_all("~case '([a-z_]+)':~", $ticketIntegrityNoticeSource, $ticketIntegrityRenderStatusMatches);
$ticketIntegrityRenderStatuses = array_values(array_unique($ticketIntegrityRenderStatusMatches[1]));
sort($ticketIntegrityRenderStatuses);
$expectedRenderStatuses = $ticketIntegrityExpectedStatuses;
sort($expectedRenderStatuses);
$assert($ticketIntegrityRenderStatuses === $expectedRenderStatuses, 'Ticket Integrity notice helper should preserve the complete recognized selector vocabulary.');
preg_match_all("~vms_ticket_integrity_admin_redirect\\(\\s*'([a-z_]+)'~", $ticketIntegritySource, $ticketIntegrityDirectWriterMatches);
preg_match_all('~\\$notice\\s*=\\s*\'(rebuild_[a-z_]+)\'~', $ticketIntegrityRebuildSource, $ticketIntegrityRebuildWriterMatches);
preg_match_all('~\\$notice\\s*=\\s*\'(duplicate_cleanup_[a-z_]+)\'~', $ticketIntegrityDuplicateSource, $ticketIntegrityDuplicateWriterMatches);
$ticketIntegrityWriterStatuses = array_values(array_unique(array_merge(
	$ticketIntegrityDirectWriterMatches[1],
	$ticketIntegrityRebuildWriterMatches[1],
	$ticketIntegrityDuplicateWriterMatches[1]
)));
sort($ticketIntegrityWriterStatuses);
$expectedWriterStatuses = $ticketIntegrityExpectedStatuses;
sort($expectedWriterStatuses);
$assert($ticketIntegrityWriterStatuses === $expectedWriterStatuses, 'Ticket Integrity redirect/action writers should preserve the same status vocabulary as the renderer.');

$ticketIntegrityRenderCases = array(
	array(
		'label' => 'Ticket Integrity scan_complete branch',
		'query' => array('tim_notice' => 'scan_complete', 'red' => '3', 'yellow' => '2'),
		'expected' => '<div class="notice notice-success"><p>Ticket integrity scan completed. Red: 3. Yellow: 2.</p></div>',
	),
	array(
		'label' => 'Ticket Integrity normalized malformed selector branch',
		'query' => array('tim_notice' => 'SCAN_COMPLETE!!', 'red' => '7', 'yellow' => '0'),
		'expected' => '<div class="notice notice-success"><p>Ticket integrity scan completed. Red: 7. Yellow: 0.</p></div>',
	),
	array(
		'label' => 'Ticket Integrity event_scan_complete branch',
		'query' => array('tim_notice' => 'event_scan_complete'),
		'expected' => '<div class="notice notice-success"><p>Event integrity scan completed.</p></div>',
	),
	array(
		'label' => 'Ticket Integrity settings_saved branch',
		'query' => array('tim_notice' => 'settings_saved'),
		'expected' => '<div class="notice notice-success"><p>Ticket Integrity settings saved.</p></div>',
	),
	array(
		'label' => 'Ticket Integrity daily_report_sent branch',
		'query' => array('tim_notice' => 'daily_report_sent'),
		'expected' => '<div class="notice notice-success"><p>State of the Range email sent.</p></div>',
	),
	array(
		'label' => 'Ticket Integrity daily_report_preview_ready branch',
		'query' => array('tim_notice' => 'daily_report_preview_ready'),
		'expected' => '<div class="notice notice-success"><p>State of the Range preview rendered successfully.</p></div>',
	),
	array(
		'label' => 'Ticket Integrity daily_report_dry_run_ready branch',
		'query' => array('tim_notice' => 'daily_report_dry_run_ready'),
		'expected' => '<div class="notice notice-success"><p>State of the Range dry-run diagnostic completed without sending email.</p></div>',
	),
	array(
		'label' => 'Ticket Integrity daily_report_test_sent branch',
		'query' => array('tim_notice' => 'daily_report_test_sent', 'recipient' => 'ops@example.test'),
		'expected' => '<div class="notice notice-success"><p>State of the Range admin test email sent. ops@example.test</p></div>',
	),
	array(
		'label' => 'Ticket Integrity daily_report_failed branch',
		'query' => array('tim_notice' => 'daily_report_failed', 'detail' => '<strong>Mailer</strong> down'),
		'expected' => '<div class="notice notice-error"><p>State of the Range email failed to send. Mailer down</p></div>',
	),
	array(
		'label' => 'Ticket Integrity rebuild_complete branch',
		'query' => array('tim_notice' => 'rebuild_complete', 'detail' => 'Mapped 3 products.'),
		'expected' => '<div class="notice notice-success"><p>Repair completed and the event was re-scanned. Mapped 3 products.</p></div>',
	),
	array(
		'label' => 'Ticket Integrity rebuild_no_change branch',
		'query' => array('tim_notice' => 'rebuild_no_change', 'detail' => 'No drift remained.'),
		'expected' => '<div class="notice notice-info"><p>No mapping changes were needed and the event was re-scanned. No drift remained.</p></div>',
	),
	array(
		'label' => 'Ticket Integrity rebuild_partial branch',
		'query' => array('tim_notice' => 'rebuild_partial', 'detail' => '2 warnings remain.'),
		'expected' => '<div class="notice notice-warning"><p>Repair made changes, but unresolved conflicts still remain. 2 warnings remain.</p></div>',
	),
	array(
		'label' => 'Ticket Integrity rebuild_blocked branch',
		'query' => array('tim_notice' => 'rebuild_blocked', 'detail' => 'Sold SKU mismatch.'),
		'expected' => '<div class="notice notice-warning"><p>Repair could not proceed safely. Sold SKU mismatch.</p></div>',
	),
	array(
		'label' => 'Ticket Integrity duplicate_cleanup_complete branch',
		'query' => array('tim_notice' => 'duplicate_cleanup_complete', 'detail' => 'Retired 2 duplicates.'),
		'expected' => '<div class="notice notice-success"><p>Duplicate legacy ticket cleanup completed and the event was re-scanned. Retired 2 duplicates.</p></div>',
	),
	array(
		'label' => 'Ticket Integrity duplicate_cleanup_partial branch',
		'query' => array('tim_notice' => 'duplicate_cleanup_partial', 'detail' => '1 sold path still needs review.'),
		'expected' => '<div class="notice notice-warning"><p>Duplicate legacy ticket cleanup made progress, but warnings remain. 1 sold path still needs review.</p></div>',
	),
	array(
		'label' => 'Ticket Integrity duplicate_cleanup_blocked branch',
		'query' => array('tim_notice' => 'duplicate_cleanup_blocked', 'detail' => 'Legacy sold product retained.'),
		'expected' => '<div class="notice notice-warning"><p>Duplicate legacy ticket cleanup was blocked for one or more sold paths. Legacy sold product retained.</p></div>',
	),
	array(
		'label' => 'Ticket Integrity scan_failed branch',
		'query' => array('tim_notice' => 'scan_failed', 'detail' => 'scan_helper_missing'),
		'expected' => '<div class="notice notice-error"><p>Ticket Integrity action failed. scan_helper_missing</p></div>',
	),
	array(
		'label' => 'Ticket Integrity event_scan_failed branch',
		'query' => array('tim_notice' => 'event_scan_failed', 'detail' => 'event_scan_failed'),
		'expected' => '<div class="notice notice-error"><p>Ticket Integrity action failed. event_scan_failed</p></div>',
	),
	array(
		'label' => 'Ticket Integrity rebuild_failed branch',
		'query' => array('tim_notice' => 'rebuild_failed', 'detail' => 'repair_helper_missing'),
		'expected' => '<div class="notice notice-error"><p>Ticket Integrity action failed. repair_helper_missing</p></div>',
	),
	array(
		'label' => 'Ticket Integrity duplicate_cleanup_failed branch',
		'query' => array('tim_notice' => 'duplicate_cleanup_failed', 'detail' => 'duplicate_cleanup_helper_missing'),
		'expected' => '<div class="notice notice-error"><p>Ticket Integrity action failed. duplicate_cleanup_helper_missing</p></div>',
	),
);
foreach ($ticketIntegrityRenderCases as $ticketIntegrityRenderCase) {
	$_GET = $ticketIntegrityRenderCase['query'];
	ob_start();
	vms_ticket_integrity_render_notice_from_query();
	$ticketIntegrityNoticeHtml = (string) ob_get_clean();
	$assert(
		$ticketIntegrityNoticeHtml === $ticketIntegrityRenderCase['expected'],
		$ticketIntegrityRenderCase['label'] . ' should preserve the exact notice fragment.'
	);
	$assert(
		wp_kses($ticketIntegrityNoticeHtml, vms_admin_ui_explicit_notice_allowed_html()) === $ticketIntegrityNoticeHtml,
		$ticketIntegrityRenderCase['label'] . ' should remain within the explicit notice contract.'
	);
}

$_GET = array('tim_notice' => 'daily_report_failed');
ob_start();
vms_ticket_integrity_render_notice_from_query();
$ticketIntegrityNoDetailNotice = (string) ob_get_clean();
$assert($ticketIntegrityNoDetailNotice === '<div class="notice notice-error"><p>State of the Range email failed to send.</p></div>', 'Ticket Integrity daily_report_failed should preserve its base message when detail is absent.');

$_GET = array('tim_notice' => 'daily_report_test_sent');
ob_start();
vms_ticket_integrity_render_notice_from_query();
$ticketIntegrityNoRecipientNotice = (string) ob_get_clean();
$assert($ticketIntegrityNoRecipientNotice === '<div class="notice notice-success"><p>State of the Range admin test email sent.</p></div>', 'Ticket Integrity daily_report_test_sent should preserve its base message when recipient is absent.');

$_GET = array();
ob_start();
vms_ticket_integrity_render_notice_from_query();
$ticketIntegrityNoStatusNotice = (string) ob_get_clean();
$assert($ticketIntegrityNoStatusNotice === '', 'Ticket Integrity notice helper should stay silent when the selector is absent.');

$_GET = array('tim_notice' => '');
ob_start();
vms_ticket_integrity_render_notice_from_query();
$ticketIntegrityEmptyStatusNotice = (string) ob_get_clean();
$assert($ticketIntegrityEmptyStatusNotice === '', 'Ticket Integrity notice helper should stay silent when the selector is empty.');

$_GET = array('tim_notice' => 'not_real');
ob_start();
vms_ticket_integrity_render_notice_from_query();
$ticketIntegrityUnknownStatusNotice = (string) ob_get_clean();
$assert($ticketIntegrityUnknownStatusNotice === '', 'Ticket Integrity notice helper should stay silent for unknown selectors.');

$_GET = array('tim_notice' => '<strong>bogus</strong>');
ob_start();
vms_ticket_integrity_render_notice_from_query();
$ticketIntegrityMalformedStatusNotice = (string) ob_get_clean();
$assert($ticketIntegrityMalformedStatusNotice === '', 'Ticket Integrity notice helper should stay silent when a malformed selector sanitizes to an unrecognized value.');

$GLOBALS['vms_test_ticket_integrity_get_settings_calls'] = 0;
$GLOBALS['vms_test_ticket_integrity_get_settings_value'] = array();
$GLOBALS['vms_test_ticket_integrity_get_results_store_calls'] = 0;
$GLOBALS['vms_test_ticket_integrity_get_results_store_value'] = array('summary' => array(), 'last_scan' => array());
$GLOBALS['vms_test_ticket_integrity_prepare_payment_gateway_health_calls'] = 0;
$GLOBALS['vms_test_ticket_integrity_prepare_payment_gateway_health_args'] = array();
$GLOBALS['vms_test_ticket_integrity_prepare_payment_gateway_health_value'] = array();
$GLOBALS['vms_test_ticket_integrity_get_sorted_events_calls'] = 0;
$GLOBALS['vms_test_ticket_integrity_get_sorted_events_value'] = array();
$GLOBALS['vms_test_ticket_integrity_get_logs_calls'] = 0;
$GLOBALS['vms_test_ticket_integrity_get_logs_value'] = array();
$GLOBALS['vms_test_current_user_id'] = 0;
$GLOBALS['vms_test_transients'] = array();
$GLOBALS['vms_test_transient_get_calls'] = 0;
$GLOBALS['vms_test_transient_get_keys'] = array();
$GLOBALS['vms_test_transient_set_calls'] = 0;
$GLOBALS['vms_test_transient_set_payloads'] = array();
$GLOBALS['vms_test_transient_delete_calls'] = 0;
$GLOBALS['vms_test_transient_delete_keys'] = array();
$GLOBALS['vms_test_options'] = array('admin_email' => 'admin@example.test');
$_GET = array(
	'tim_notice' => 'daily_report_failed',
	'detail' => '<strong>Mailer</strong> down',
);
ob_start();
vms_ticket_integrity_render_admin_page();
$ticketIntegrityRenderedPage = (string) ob_get_clean();
$assert($GLOBALS['vms_test_ticket_integrity_get_settings_calls'] === 1, 'Ticket Integrity page render should resolve settings exactly once.');
$assert($GLOBALS['vms_test_ticket_integrity_get_results_store_calls'] === 1, 'Ticket Integrity page render should resolve the results store exactly once.');
$assert($GLOBALS['vms_test_ticket_integrity_prepare_payment_gateway_health_calls'] === 1, 'Ticket Integrity page render should resolve payment gateway health exactly once.');
$assert($GLOBALS['vms_test_ticket_integrity_prepare_payment_gateway_health_args'] === array(array('admin_page', 20 * MINUTE_IN_SECONDS)), 'Ticket Integrity page render should preserve the existing payment-gateway-health arguments.');
$assert($GLOBALS['vms_test_ticket_integrity_get_sorted_events_calls'] === 1, 'Ticket Integrity page render should resolve sorted events exactly once.');
$assert($GLOBALS['vms_test_ticket_integrity_get_logs_calls'] === 1, 'Ticket Integrity page render should resolve audit logs exactly once.');
$assert($GLOBALS['vms_test_transient_get_calls'] === 1, 'Ticket Integrity page render should preserve the existing daily-report preview lookup exactly once.');
$assert($GLOBALS['vms_test_transient_get_keys'] === array('vms_ticket_integrity_daily_report_preview_0'), 'Ticket Integrity page render should preserve the existing daily-report preview transient key lookup.');
$assert($GLOBALS['vms_test_transient_set_calls'] === 0 && $GLOBALS['vms_test_transient_delete_calls'] === 0, 'Ticket Integrity page render should not introduce transient writes or deletes while routing notices through the explicit sink.');
$assert(substr_count($ticketIntegrityRenderedPage, 'State of the Range email failed to send. Mailer down') === 1, 'Ticket Integrity page render should emit the moved notice exactly once.');
$assert(strpos($ticketIntegrityRenderedPage, 'State of the Range email failed to send. Mailer down') < strpos($ticketIntegrityRenderedPage, 'Run Ticket Integrity Check Now'), 'Ticket Integrity shell output should keep the moved notice before the run panel.');
$assert(strpos($ticketIntegrityRenderedPage, 'Run Ticket Integrity Check Now') < strpos($ticketIntegrityRenderedPage, 'Monitor Settings'), 'Ticket Integrity shell output should preserve the original content ordering after the moved notice.');

$assert(strpos($eventFeedbackSource, 'function vms_feedback_admin_render_notices(): void') !== false, 'Event Feedback should expose a dedicated explicit notice callback.');
$assert(substr_count($eventFeedbackSource, "'notices_callback' => 'vms_feedback_admin_render_notices'") === 1, 'Event Feedback shell call should supply its explicit notice callback exactly once.');
$eventFeedbackNoticeStart = strpos($eventFeedbackSource, 'function vms_feedback_admin_render_notices(): void');
$eventFeedbackNoticeEnd = strpos($eventFeedbackSource, "if (!function_exists('vms_feedback_admin_render_notification_settings'))");
$assert($eventFeedbackNoticeStart !== false && $eventFeedbackNoticeEnd !== false && $eventFeedbackNoticeEnd > $eventFeedbackNoticeStart, 'Event Feedback explicit notice callback body should be locatable.');
$eventFeedbackNoticeSource = substr($eventFeedbackSource, (int) $eventFeedbackNoticeStart, (int) $eventFeedbackNoticeEnd - (int) $eventFeedbackNoticeStart);
$eventFeedbackContentStart = strpos($eventFeedbackSource, 'function vms_feedback_admin_render_content(): void');
$eventFeedbackContentEnd = strpos($eventFeedbackSource, "if (!function_exists('vms_render_event_feedback_admin_page'))");
$assert($eventFeedbackContentStart !== false && $eventFeedbackContentEnd !== false && $eventFeedbackContentEnd > $eventFeedbackContentStart, 'Event Feedback content callback body should be locatable.');
$eventFeedbackContentSource = substr($eventFeedbackSource, (int) $eventFeedbackContentStart, (int) $eventFeedbackContentEnd - (int) $eventFeedbackContentStart);
$eventFeedbackPageStart = strpos($eventFeedbackSource, 'function vms_render_event_feedback_admin_page(): void');
$eventFeedbackPageEnd = strpos($eventFeedbackSource, "if (!function_exists('vms_feedback_add_event_plan_metabox'))");
$assert($eventFeedbackPageStart !== false && $eventFeedbackPageEnd !== false && $eventFeedbackPageEnd > $eventFeedbackPageStart, 'Event Feedback page renderer body should be locatable.');
$eventFeedbackPageSource = substr($eventFeedbackSource, (int) $eventFeedbackPageStart, (int) $eventFeedbackPageEnd - (int) $eventFeedbackPageStart);
$assert(strpos($eventFeedbackNoticeSource, '!empty($_GET[\'vms_feedback_settings_saved\'])') !== false, 'Event Feedback explicit notice callback should preserve the existing saved-settings presence check.');
$assert(strpos($eventFeedbackNoticeSource, 'sanitize_key((string) $_GET[\'vms_feedback_deleted\'])') !== false, 'Event Feedback explicit notice callback should preserve the sanitized delete-status source.');
$assert(substr_count($eventFeedbackNoticeSource, 'notice notice-success is-dismissible') === 2, 'Event Feedback explicit notice callback should preserve both success notice branches.');
$assert(substr_count($eventFeedbackNoticeSource, 'notice notice-error is-dismissible') === 2, 'Event Feedback explicit notice callback should preserve both error notice branches.');
$assert(strpos($eventFeedbackNoticeSource, 'Event Feedback notification settings saved.') !== false, 'Event Feedback explicit notice callback should preserve the saved-settings notice copy.');
$assert(strpos($eventFeedbackNoticeSource, 'Feedback response deleted.') !== false, 'Event Feedback explicit notice callback should preserve the delete-success notice copy.');
$assert(strpos($eventFeedbackNoticeSource, 'Feedback response could not be found.') !== false, 'Event Feedback explicit notice callback should preserve the missing-response notice copy.');
$assert(strpos($eventFeedbackNoticeSource, 'Feedback response could not be deleted.') !== false, 'Event Feedback explicit notice callback should preserve the delete-failure notice copy.');
$assert(strpos($eventFeedbackNoticeSource, '<strong>') === false && strpos($eventFeedbackNoticeSource, '<a ') === false && strpos($eventFeedbackNoticeSource, '<button') === false && strpos($eventFeedbackNoticeSource, '<span') === false, 'Event Feedback explicit notice callback should stay within the simple fragment contract.');
$assert(strpos($eventFeedbackSource, 'wp_safe_redirect(add_query_arg(\'vms_feedback_settings_saved\', \'1\'') !== false, 'Event Feedback save handler should preserve the redirect-status notice source.');
$assert(strpos($eventFeedbackSource, 'wp_safe_redirect(add_query_arg(\'vms_feedback_deleted\', \'missing\'') !== false, 'Event Feedback delete handler should preserve the missing-response redirect path.');
$assert(strpos($eventFeedbackSource, 'wp_safe_redirect(add_query_arg(\'vms_feedback_deleted\', $deleted ? \'1\' : \'0\'') !== false, 'Event Feedback delete handler should preserve the success/failure redirect-status path.');
$assert(strpos($eventFeedbackPageSource, "'notices_callback' => 'vms_feedback_admin_render_notices'") !== false, 'Event Feedback page renderer should pass the explicit notice callback through the Administrator shell.');
$eventFeedbackFallbackHeadingPos = strpos($eventFeedbackPageSource, "echo '<div class=\"wrap\"><h1>'");
$eventFeedbackFallbackNoticePos = strpos($eventFeedbackPageSource, 'vms_feedback_admin_render_notices();');
$eventFeedbackFallbackContentPos = strpos($eventFeedbackPageSource, 'vms_feedback_admin_render_content();');
$assert($eventFeedbackFallbackHeadingPos !== false && $eventFeedbackFallbackNoticePos !== false && $eventFeedbackFallbackContentPos !== false && $eventFeedbackFallbackHeadingPos < $eventFeedbackFallbackNoticePos && $eventFeedbackFallbackNoticePos < $eventFeedbackFallbackContentPos, 'Event Feedback no-shell fallback should preserve heading, notices, then content ordering.');
$assert(strpos($eventFeedbackContentSource, 'vms_feedback_admin_render_notices();') === false, 'Event Feedback content callback should no longer emit the moved redirect notice family.');
$assert(strpos($eventFeedbackContentSource, 'That Event Plan could not be found.') !== false, 'Event Feedback content callback should preserve the separate missing-plan notice in the ordinary content path.');

$_GET = array(
	'vms_feedback_settings_saved' => '1',
);
ob_start();
vms_feedback_admin_render_notices();
$eventFeedbackSavedNotice = (string) ob_get_clean();
$assert(
	$eventFeedbackSavedNotice === '<div class="notice notice-success is-dismissible"><p>Event Feedback notification settings saved.</p></div>',
	'Event Feedback explicit notice callback should preserve the saved-settings notice fragment.'
);
$assert(
	wp_kses($eventFeedbackSavedNotice, vms_admin_ui_explicit_notice_allowed_html()) === $eventFeedbackSavedNotice,
	'The explicit notice allowlist should admit the Event Feedback saved-settings notice unchanged.'
);

$_GET = array(
	'vms_feedback_deleted' => '1',
);
ob_start();
vms_feedback_admin_render_notices();
$eventFeedbackDeletedNotice = (string) ob_get_clean();
$assert(
	$eventFeedbackDeletedNotice === '<div class="notice notice-success is-dismissible"><p>Feedback response deleted.</p></div>',
	'Event Feedback explicit notice callback should preserve the delete-success notice fragment.'
);

$_GET = array(
	'vms_feedback_deleted' => 'missing',
);
ob_start();
vms_feedback_admin_render_notices();
$eventFeedbackMissingNotice = (string) ob_get_clean();
$assert(
	$eventFeedbackMissingNotice === '<div class="notice notice-error is-dismissible"><p>Feedback response could not be found.</p></div>',
	'Event Feedback explicit notice callback should preserve the missing-response notice fragment.'
);

$_GET = array(
	'vms_feedback_deleted' => '0',
);
ob_start();
vms_feedback_admin_render_notices();
$eventFeedbackFailedNotice = (string) ob_get_clean();
$assert(
	$eventFeedbackFailedNotice === '<div class="notice notice-error is-dismissible"><p>Feedback response could not be deleted.</p></div>',
	'Event Feedback explicit notice callback should preserve the delete-failure notice fragment.'
);

$_GET = array(
	'vms_feedback_deleted' => '<strong>unexpected</strong>',
);
ob_start();
vms_feedback_admin_render_notices();
$eventFeedbackMalformedNotice = (string) ob_get_clean();
$assert(
	$eventFeedbackMalformedNotice === '<div class="notice notice-error is-dismissible"><p>Feedback response could not be deleted.</p></div>',
	'Event Feedback explicit notice callback should preserve the delete-failure fallback for malformed delete-status values.'
);

$_GET = array(
	'vms_feedback_settings_saved' => '1',
	'vms_feedback_deleted' => 'missing',
);
ob_start();
vms_feedback_admin_render_notices();
$eventFeedbackCombinedNotice = (string) ob_get_clean();
$assert(
	$eventFeedbackCombinedNotice === '<div class="notice notice-success is-dismissible"><p>Event Feedback notification settings saved.</p></div><div class="notice notice-error is-dismissible"><p>Feedback response could not be found.</p></div>',
	'Event Feedback explicit notice callback should preserve the saved-then-delete notice ordering when both query flags are present.'
);

$assert(strpos($eventPlanImportSource, 'function vms_event_plan_import_notice_class(string $type): string') !== false, 'Event Plan Import should preserve the notice-class mapper.');
$assert(strpos($eventPlanImportSource, 'function vms_event_plan_import_render_notice(array $notice): void') !== false, 'Event Plan Import should expose a dedicated explicit notice renderer.');
$assert(strpos($eventPlanImportSource, 'function vms_event_plan_import_render_intro(): void') !== false, 'Event Plan Import should expose a dedicated intro renderer for the shared page copy.');
$assert(strpos($eventPlanImportSource, 'function vms_event_plan_import_render_main_content(array $preview, string $preview_token, array $latest_run, array $revertible_run): void') !== false, 'Event Plan Import should expose a dedicated renderer for the remaining page content.');
$eventPlanImportNoticeStart = strpos($eventPlanImportSource, 'function vms_event_plan_import_render_notice(array $notice): void');
$eventPlanImportNoticeEnd = strpos($eventPlanImportSource, "if (!function_exists('vms_event_plan_import_render_summary_cards'))");
$assert($eventPlanImportNoticeStart !== false && $eventPlanImportNoticeEnd !== false && $eventPlanImportNoticeEnd > $eventPlanImportNoticeStart, 'Event Plan Import explicit notice renderer body should be locatable.');
$eventPlanImportNoticeSource = substr($eventPlanImportSource, (int) $eventPlanImportNoticeStart, (int) $eventPlanImportNoticeEnd - (int) $eventPlanImportNoticeStart);
$eventPlanImportIntroStart = strpos($eventPlanImportSource, 'function vms_event_plan_import_render_intro(): void');
$eventPlanImportIntroEnd = strpos($eventPlanImportSource, "if (!function_exists('vms_event_plan_import_render_summary_cards'))");
$assert($eventPlanImportIntroStart !== false && $eventPlanImportIntroEnd !== false && $eventPlanImportIntroEnd > $eventPlanImportIntroStart, 'Event Plan Import intro renderer body should be locatable.');
$eventPlanImportIntroSource = substr($eventPlanImportSource, (int) $eventPlanImportIntroStart, (int) $eventPlanImportIntroEnd - (int) $eventPlanImportIntroStart);
$eventPlanImportMainContentStart = strpos($eventPlanImportSource, 'function vms_event_plan_import_render_main_content(array $preview, string $preview_token, array $latest_run, array $revertible_run): void');
$eventPlanImportMainContentEnd = strpos($eventPlanImportSource, "if (!function_exists('vms_event_plan_import_render_admin_page'))");
$assert($eventPlanImportMainContentStart !== false && $eventPlanImportMainContentEnd !== false && $eventPlanImportMainContentEnd > $eventPlanImportMainContentStart, 'Event Plan Import main-content renderer body should be locatable.');
$eventPlanImportMainContentSource = substr($eventPlanImportSource, (int) $eventPlanImportMainContentStart, (int) $eventPlanImportMainContentEnd - (int) $eventPlanImportMainContentStart);
$eventPlanImportPageStart = strpos($eventPlanImportSource, 'function vms_event_plan_import_render_admin_page(): void');
$assert($eventPlanImportPageStart !== false, 'Event Plan Import page renderer body should be locatable.');
$eventPlanImportPageSource = substr($eventPlanImportSource, (int) $eventPlanImportPageStart);
$eventPlanImportContentStart = strpos($eventPlanImportPageSource, '$render_content = static function () use (');
$eventPlanImportContentEnd = strpos($eventPlanImportPageSource, "if (function_exists('vms_admin_ui_render_shell')) {");
$assert($eventPlanImportContentStart !== false && $eventPlanImportContentEnd !== false && $eventPlanImportContentEnd > $eventPlanImportContentStart, 'Event Plan Import content callback body should be locatable.');
$eventPlanImportContentSource = substr($eventPlanImportPageSource, (int) $eventPlanImportContentStart, (int) $eventPlanImportContentEnd - (int) $eventPlanImportContentStart);
$eventPlanImportSetStart = strpos($eventPlanImportEngineSource, 'function vms_event_plan_import_set_notice(string $type, string $message): void');
$eventPlanImportSetEnd = strpos($eventPlanImportEngineSource, "if (!function_exists('vms_event_plan_import_pop_notice'))");
$assert($eventPlanImportSetStart !== false && $eventPlanImportSetEnd !== false && $eventPlanImportSetEnd > $eventPlanImportSetStart, 'Event Plan Import notice-set helper body should be locatable.');
$eventPlanImportSetSource = substr($eventPlanImportEngineSource, (int) $eventPlanImportSetStart, (int) $eventPlanImportSetEnd - (int) $eventPlanImportSetStart);
$eventPlanImportPopStart = strpos($eventPlanImportEngineSource, 'function vms_event_plan_import_pop_notice(): array');
$eventPlanImportPopEnd = strpos($eventPlanImportEngineSource, "if (!function_exists('vms_event_plan_import_get_preview_payload'))");
$assert($eventPlanImportPopStart !== false && $eventPlanImportPopEnd !== false && $eventPlanImportPopEnd > $eventPlanImportPopStart, 'Event Plan Import notice-pop helper body should be locatable.');
$eventPlanImportPopSource = substr($eventPlanImportEngineSource, (int) $eventPlanImportPopStart, (int) $eventPlanImportPopEnd - (int) $eventPlanImportPopStart);
$eventPlanImportCapabilityCheckPos = strpos($eventPlanImportPageSource, "current_user_can('manage_options')");
$eventPlanImportPopPos = strpos($eventPlanImportPageSource, '$notice = vms_event_plan_import_pop_notice();');
$assert($eventPlanImportCapabilityCheckPos !== false && $eventPlanImportPopPos !== false && $eventPlanImportCapabilityCheckPos < $eventPlanImportPopPos, 'Event Plan Import should preserve the capability gate before the destructive notice pop.');
$assert(strpos($eventPlanImportSetSource, 'vms_event_plan_import_notice_transient_key($user_id)') !== false, 'Event Plan Import notice storage should stay keyed by the current user.');
$assert(strpos($eventPlanImportSetSource, 'sanitize_key($type)') !== false, 'Event Plan Import notice storage should preserve type sanitization.');
$assert(strpos($eventPlanImportSetSource, 'sanitize_text_field($message)') !== false, 'Event Plan Import notice storage should preserve text-only message sanitization.');
$assert(strpos($eventPlanImportPopSource, 'get_transient($key);') !== false && strpos($eventPlanImportPopSource, 'delete_transient($key);') !== false, 'Event Plan Import notice pop should remain a destructive transient read-and-delete operation.');
$assert(strpos($eventPlanImportIntroSource, 'Upload a CSV, preview changes, then commit. This importer only writes VMS Event Plan data and does not create or update TEC/Woo records.') !== false, 'Event Plan Import intro renderer should preserve the original introductory copy.');
$assert(strpos($eventPlanImportMainContentSource, 'name="event_plan_csv_file"') !== false, 'Event Plan Import main-content renderer should preserve the first import-form marker after the moved notice boundary.');
$assert(strpos($eventPlanImportNoticeSource, 'vms_event_plan_import_notice_class($type)') !== false, 'Event Plan Import explicit notice renderer should preserve the existing severity mapper.');
$assert(strpos($eventPlanImportNoticeSource, 'esc_html($message)') !== false, 'Event Plan Import explicit notice renderer should preserve contextual escaping for text.');
$assert(strpos($eventPlanImportNoticeSource, '<div class="') !== false && strpos($eventPlanImportNoticeSource, ' inline"><p>') !== false, 'Event Plan Import explicit notice renderer should preserve the inline notice fragment shape.');
$assert(strpos($eventPlanImportNoticeSource, '<strong>') === false && strpos($eventPlanImportNoticeSource, '<a ') === false && strpos($eventPlanImportNoticeSource, '<button') === false, 'Event Plan Import explicit notice renderer should stay within the simple fragment contract.');
$assert(preg_match('~\$render_notice\s*=\s*static function \(\) use \(\$notice\): void \{\s*vms_event_plan_import_render_notice\(\$notice\);~s', $eventPlanImportPageSource) === 1, 'Event Plan Import should route the popped notice through a page-local explicit notice callback.');
$assert(preg_match('~\$render_intro\s*=\s*static function \(\): void \{\s*vms_event_plan_import_render_intro\(\);~s', $eventPlanImportPageSource) === 1, 'Event Plan Import page renderer should expose the shared intro renderer through a page-local callable.');
$assert(preg_match('~\$render_main_content\s*=\s*static function \(\) use \(\$preview,\s*\$preview_token,\s*\$latest_run,\s*\$revertible_run\): void \{\s*vms_event_plan_import_render_main_content\(\$preview,\s*\$preview_token,\s*\$latest_run,\s*\$revertible_run\);~s', $eventPlanImportPageSource) === 1, 'Event Plan Import page renderer should expose the remaining page content through a page-local callable.');
$assert(preg_match('~\$render_content\s*=\s*static function \(\) use \(\$render_intro,\s*\$render_main_content\): void \{\s*\$render_intro\(\);\s*\$render_main_content\(\);~s', $eventPlanImportPageSource) === 1, 'Event Plan Import shell content callback should still render introduction before the remaining content.');
$assert(preg_match('~[\'"]notices_callback[\'"]\s*=>\s*\$render_notice~', $eventPlanImportPageSource) === 1, 'Event Plan Import shell call should supply the page-local explicit notice callback.');
$assert(preg_match('~echo\s+[\'"]<div class=\"wrap\"><h1>[\'"].*?\$render_intro\(\);\s*\$render_notice\(\);\s*\$render_main_content\(\);~s', $eventPlanImportPageSource) === 1, 'Event Plan Import no-shell fallback should preserve heading, intro, notice, then remaining content ordering.');
$assert(substr_count($eventPlanImportSource, 'vms_event_plan_import_pop_notice();') === 1, 'Event Plan Import page source should keep exactly one destructive notice pop call.');
$assert(strpos($eventPlanImportContentSource, 'vms_event_plan_import_pop_notice();') === false, 'Event Plan Import content callback should no longer pop notice state directly.');
$assert(strpos($eventPlanImportContentSource, 'vms_event_plan_import_render_notice(') === false, 'Event Plan Import content callback should no longer emit the moved primary notice.');
$assert(strpos($eventPlanImportMainContentSource, 'notice notice-error inline') !== false, 'Event Plan Import should preserve the separate inline rows-payload error branch in the remaining content renderer.');
$assert(strpos($eventPlanImportActionsSource, 'function vms_event_plan_import_handle_preview_action(): void') !== false && strpos($eventPlanImportActionsSource, 'function vms_event_plan_import_handle_commit_action(): void') !== false && strpos($eventPlanImportActionsSource, 'function vms_event_plan_import_handle_revert_last_action(): void') !== false, 'Event Plan Import should preserve the existing notice-writing action handlers.');
$assert(preg_match_all('~vms_event_plan_import_set_notice\s*\(~', $eventPlanImportActionsSource, $unusedEventPlanImportSetNoticeMatches) === 13, 'Event Plan Import action handlers should preserve every existing notice-writing path.');
$assert(strpos($eventPlanImportActionsSource, 'Preview ready. Create: %1$d, Update: %2$d, Skip: %3$d, Errors: %4$d.') !== false, 'Event Plan Import preview action should preserve the summary success message source.');
$assert(strpos($eventPlanImportActionsSource, 'Import committed. Create: %1$d, Update: %2$d, Skip: %3$d, Errors: %4$d.') !== false, 'Event Plan Import commit action should preserve the summary success message source.');
$assert(strpos($eventPlanImportActionsSource, 'Revert complete. Restored: %1$d, Failed: %2$d.') !== false, 'Event Plan Import revert action should preserve the summary success message source.');
$assert(strpos($eventPlanImportActionsSource, 'get_error_message()') !== false, 'Event Plan Import actions should still surface provider error messages through the stored notice path.');

$resetEventPlanImportHarness = static function (): void {
	$_GET = array();
	$GLOBALS['vms_test_current_user_can'] = true;
	$GLOBALS['vms_test_current_user_id'] = 7;
	$GLOBALS['vms_test_transients'] = array();
	$GLOBALS['vms_test_transient_get_calls'] = 0;
	$GLOBALS['vms_test_transient_get_keys'] = array();
	$GLOBALS['vms_test_transient_set_calls'] = 0;
	$GLOBALS['vms_test_transient_set_payloads'] = array();
	$GLOBALS['vms_test_transient_delete_calls'] = 0;
	$GLOBALS['vms_test_transient_delete_keys'] = array();
	$GLOBALS['vms_test_event_plan_import_preview_payload_calls'] = 0;
	$GLOBALS['vms_test_event_plan_import_preview_payload_tokens'] = array();
	$GLOBALS['vms_test_event_plan_import_preview_payload_value'] = array();
	$GLOBALS['vms_test_event_plan_import_audit_runs_calls'] = 0;
	$GLOBALS['vms_test_event_plan_import_audit_runs_value'] = array();
	$GLOBALS['vms_test_event_plan_import_latest_revertible_run_calls'] = 0;
	$GLOBALS['vms_test_event_plan_import_latest_revertible_run_value'] = array();
	$GLOBALS['vms_test_event_plan_import_read_rows_json_calls'] = 0;
	$GLOBALS['vms_test_event_plan_import_read_rows_json_references'] = array();
	$GLOBALS['vms_test_event_plan_import_read_rows_json_value'] = array();
};

$assert(vms_event_plan_import_notice_transient_key(7) === 'vms_epcsv_notice_7', 'Event Plan Import notice transient keys should remain user-scoped.');

ob_start();
vms_event_plan_import_render_notice(array('type' => 'error', 'message' => 'Import failed.'));
$eventPlanImportErrorNotice = (string) ob_get_clean();
$assert($eventPlanImportErrorNotice === '<div class="notice notice-error inline"><p>Import failed.</p></div>', 'Event Plan Import explicit notice renderer should preserve the error notice fragment.');

ob_start();
vms_event_plan_import_render_notice(array('type' => 'critical', 'message' => 'Critical rollback failure.'));
$eventPlanImportCriticalNotice = (string) ob_get_clean();
$assert($eventPlanImportCriticalNotice === '<div class="notice notice-error inline"><p>Critical rollback failure.</p></div>', 'Event Plan Import explicit notice renderer should preserve the critical-to-error mapping.');

ob_start();
vms_event_plan_import_render_notice(array('type' => 'warning', 'message' => 'Review selected rows.'));
$eventPlanImportWarningNotice = (string) ob_get_clean();
$assert($eventPlanImportWarningNotice === '<div class="notice notice-warning inline"><p>Review selected rows.</p></div>', 'Event Plan Import explicit notice renderer should preserve the warning notice fragment.');

ob_start();
vms_event_plan_import_render_notice(array('type' => 'info', 'message' => 'Preview queued.'));
$eventPlanImportInfoNotice = (string) ob_get_clean();
$assert($eventPlanImportInfoNotice === '<div class="notice notice-info inline"><p>Preview queued.</p></div>', 'Event Plan Import explicit notice renderer should preserve the info notice fragment.');

ob_start();
vms_event_plan_import_render_notice(array('type' => 'unexpected', 'message' => 'Fallback to success.'));
$eventPlanImportFallbackBranchNotice = (string) ob_get_clean();
$assert($eventPlanImportFallbackBranchNotice === '<div class="notice notice-success inline"><p>Fallback to success.</p></div>', 'Event Plan Import explicit notice renderer should preserve the unknown-type fallback.');
$assert(wp_kses($eventPlanImportFallbackBranchNotice, vms_admin_ui_explicit_notice_allowed_html()) === $eventPlanImportFallbackBranchNotice, 'The explicit notice allowlist should admit the Event Plan Import notice fragment unchanged.');

ob_start();
vms_event_plan_import_render_notice(array('type' => 'success', 'message' => ''));
$eventPlanImportEmptyNotice = (string) ob_get_clean();
$assert($eventPlanImportEmptyNotice === '', 'Event Plan Import explicit notice renderer should stay silent when the message is empty.');

$resetEventPlanImportHarness();
vms_event_plan_import_set_notice('Bad Type<script>', 'Queue <strong>run complete.</strong>');
$assert($GLOBALS['vms_test_transient_set_calls'] === 1, 'Event Plan Import notice storage should write exactly one transient value.');
$assert(
	$GLOBALS['vms_test_transient_set_payloads'] === array(
		array(
			'vms_epcsv_notice_7',
			array(
				'type' => 'badtypescript',
				'message' => 'Queue run complete.',
			),
			600,
		),
	),
	'Event Plan Import notice storage should preserve sanitized text-only payloads and the existing expiration.'
);
$eventPlanImportFirstPop = vms_event_plan_import_pop_notice();
$eventPlanImportSecondPop = vms_event_plan_import_pop_notice();
$assert($eventPlanImportFirstPop === array('type' => 'badtypescript', 'message' => 'Queue run complete.'), 'Event Plan Import notice pop should return the sanitized stored payload.');
$assert($eventPlanImportSecondPop === array(), 'Event Plan Import notice pop should be destructive and empty on the second read.');
$assert($GLOBALS['vms_test_transient_get_calls'] === 2 && $GLOBALS['vms_test_transient_delete_calls'] === 2, 'Event Plan Import notice pop should keep one transient read and delete per pop call.');
ob_start();
vms_event_plan_import_render_notice($eventPlanImportFirstPop);
$eventPlanImportSanitizedNotice = (string) ob_get_clean();
$assert($eventPlanImportSanitizedNotice === '<div class="notice notice-success inline"><p>Queue run complete.</p></div>', 'Event Plan Import explicit notice renderer should preserve sanitized text and unknown-type fallback from stored payloads.');
$assert(strpos($eventPlanImportSanitizedNotice, '<strong>') === false && strpos($eventPlanImportSanitizedNotice, '<script') === false, 'Event Plan Import stored text should not become executable markup.');

$resetEventPlanImportHarness();
vms_event_plan_import_set_notice('success', 'Preview ready.');
$GLOBALS['vms_test_current_user_can'] = false;
try {
	vms_event_plan_import_render_admin_page();
	$assert(false, 'Event Plan Import should block unauthorized page renders.');
} catch (RuntimeException $exception) {
	$assert($exception->getMessage() === 'Insufficient permissions.', 'Event Plan Import should preserve the capability failure message.');
}
$assert($GLOBALS['vms_test_transient_get_calls'] === 0 && $GLOBALS['vms_test_transient_delete_calls'] === 0, 'Event Plan Import should not consume the notice before the capability gate succeeds.');
$assert(isset($GLOBALS['vms_test_transients']['vms_epcsv_notice_7']), 'Event Plan Import should preserve stored notice state when authorization fails.');
$assert($GLOBALS['vms_test_event_plan_import_preview_payload_calls'] === 0 && $GLOBALS['vms_test_event_plan_import_audit_runs_calls'] === 0 && $GLOBALS['vms_test_event_plan_import_latest_revertible_run_calls'] === 0, 'Event Plan Import should not resolve page providers when authorization fails.');

$resetEventPlanImportHarness();
vms_event_plan_import_set_notice('warning', 'Manual <em>review</em> required.');
ob_start();
vms_event_plan_import_render_admin_page();
$eventPlanImportPrimaryPage = (string) ob_get_clean();
$assert($GLOBALS['vms_test_transient_get_calls'] === 1 && $GLOBALS['vms_test_transient_delete_calls'] === 1, 'Event Plan Import page render should pop the primary notice exactly once.');
$assert($GLOBALS['vms_test_event_plan_import_preview_payload_calls'] === 0, 'Event Plan Import page render should not resolve preview payloads without a preview token.');
$assert($GLOBALS['vms_test_event_plan_import_audit_runs_calls'] === 1 && $GLOBALS['vms_test_event_plan_import_latest_revertible_run_calls'] === 1, 'Event Plan Import page render should preserve the existing audit-run provider calls.');
$assert(substr_count($eventPlanImportPrimaryPage, 'Manual review required.') === 1, 'Event Plan Import page render should emit the primary notice exactly once.');
$assert(strpos($eventPlanImportPrimaryPage, 'notice notice-warning inline below-h2 vms-shell-notice') !== false, 'Event Plan Import shell output should preserve the warning notice classes after shell normalization.');
$assert(strpos($eventPlanImportPrimaryPage, 'Manual review required.') < strpos($eventPlanImportPrimaryPage, 'Upload a CSV, preview changes, then commit.'), 'Event Plan Import shell output should place the explicit notice before ordinary page content.');
$assert(strpos($eventPlanImportPrimaryPage, 'Upload a CSV, preview changes, then commit.') < strpos($eventPlanImportPrimaryPage, 'name="event_plan_csv_file"'), 'Event Plan Import shell output should keep the introductory paragraph before the first import-form marker.');
$assert(empty($GLOBALS['vms_test_transients']), 'Event Plan Import page render should delete the popped transient after output.');

$resetEventPlanImportHarness();
vms_event_plan_import_set_notice('warning', 'Manual <em>review</em> required.');
$eventPlanImportFallbackNotice = vms_event_plan_import_pop_notice();
ob_start();
echo '<div class="wrap"><h1>' . esc_html__('Import Event Plans (CSV)', 'backstage-venue-manager') . '</h1>';
vms_event_plan_import_render_intro();
vms_event_plan_import_render_notice($eventPlanImportFallbackNotice);
vms_event_plan_import_render_main_content(array(), '', array(), array());
echo '</div>';
$eventPlanImportFallbackPage = (string) ob_get_clean();
$assert($GLOBALS['vms_test_transient_get_calls'] === 1 && $GLOBALS['vms_test_transient_delete_calls'] === 1, 'Event Plan Import fallback composition should pop the primary notice exactly once.');
$assert(substr_count($eventPlanImportFallbackPage, 'Manual review required.') === 1, 'Event Plan Import fallback composition should emit the primary notice exactly once.');
$assert(strpos($eventPlanImportFallbackPage, 'Import Event Plans (CSV)') < strpos($eventPlanImportFallbackPage, 'Upload a CSV, preview changes, then commit.'), 'Event Plan Import fallback output should place the heading before the introductory paragraph.');
$assert(strpos($eventPlanImportFallbackPage, 'Upload a CSV, preview changes, then commit.') < strpos($eventPlanImportFallbackPage, 'Manual review required.'), 'Event Plan Import fallback output should place the introductory paragraph before the primary notice.');
$assert(strpos($eventPlanImportFallbackPage, 'Manual review required.') < strpos($eventPlanImportFallbackPage, 'name="event_plan_csv_file"'), 'Event Plan Import fallback output should place the primary notice before the first import-form marker.');
$assert(strpos($eventPlanImportFallbackPage, 'notice notice-warning inline') !== false, 'Event Plan Import fallback output should preserve the raw inline notice classes without shell normalization.');
$assert(strpos($eventPlanImportFallbackPage, 'notice notice-warning inline below-h2 vms-shell-notice') === false, 'Event Plan Import fallback output should not receive shell notice normalization.');

$resetEventPlanImportHarness();
vms_event_plan_import_set_notice('success', 'Preview ready.');
$GLOBALS['vms_test_event_plan_import_preview_payload_value'] = array(
	'summary' => array(
		'total_rows' => 3,
	),
	'source_csv_name' => 'example.csv',
	'rows_json_storage_key' => 'rows-key',
);
$GLOBALS['vms_test_event_plan_import_read_rows_json_value'] = new WP_Error('rows_missing', 'Preview rows payload is unavailable.');
$_GET = array(
	'preview_token' => 'preview-token-1',
);
ob_start();
vms_event_plan_import_render_admin_page();
$eventPlanImportRowsErrorPage = (string) ob_get_clean();
$assert($GLOBALS['vms_test_transient_get_calls'] === 1 && $GLOBALS['vms_test_transient_delete_calls'] === 1, 'Event Plan Import rows-error render should still pop the primary notice exactly once.');
$assert($GLOBALS['vms_test_event_plan_import_preview_payload_calls'] === 1, 'Event Plan Import rows-error render should resolve the preview payload exactly once.');
$assert($GLOBALS['vms_test_event_plan_import_preview_payload_tokens'] === array('preview-token-1'), 'Event Plan Import rows-error render should preserve the sanitized preview token input.');
$assert($GLOBALS['vms_test_event_plan_import_read_rows_json_calls'] === 1, 'Event Plan Import rows-error render should resolve the rows payload exactly once.');
$assert($GLOBALS['vms_test_event_plan_import_read_rows_json_references'] === array('rows-key'), 'Event Plan Import rows-error render should preserve the rows payload storage reference.');
$assert(substr_count($eventPlanImportRowsErrorPage, 'Preview ready.') === 1, 'Event Plan Import rows-error render should preserve the primary notice exactly once.');
$assert(substr_count($eventPlanImportRowsErrorPage, 'Preview rows payload is unavailable.') === 1, 'Event Plan Import rows-error render should preserve the separate rows-payload error exactly once.');
$assert(strpos($eventPlanImportRowsErrorPage, 'Preview ready.') < strpos($eventPlanImportRowsErrorPage, 'Preview rows payload is unavailable.'), 'Event Plan Import shell output should keep the explicit primary notice before the separate rows-payload error.');
$assert(strpos($eventPlanImportRowsErrorPage, 'Upload a CSV, preview changes, then commit.') < strpos($eventPlanImportRowsErrorPage, 'Preview rows payload is unavailable.'), 'Event Plan Import rows-payload error should remain in the content path after the ordinary page intro.');
$assert(strpos($eventPlanImportRowsErrorPage, 'Preview Results') < strpos($eventPlanImportRowsErrorPage, 'Preview rows payload is unavailable.'), 'Event Plan Import rows-payload error should remain inside the preview results section.');
$assert(strpos($eventPlanImportRowsErrorPage, 'notice notice-error inline') !== false, 'Event Plan Import rows-payload error should preserve its original inline notice classes in the content path.');
$assert(strpos($eventPlanImportRowsErrorPage, 'notice notice-error inline below-h2 vms-shell-notice') === false, 'Event Plan Import rows-payload error should not be normalized by the shell explicit/captured notice preparation path.');
$assert(strpos($eventPlanImportRowsErrorPage, 'Preview Results') !== false, 'Event Plan Import rows-error render should preserve the preview results content path.');

$allowedHeaderActions = '<a class="button button-primary" href="https://example.test/wp-admin/post-new.php?post_type=vms_event_plan">New Event Plan</a><a class="button" href="https://example.test/wp-admin/edit.php?post_type=vms_event_plan">Event Plans</a><div class="vms-ticket-integrity__header-actions" data-vms-tour="ticket-integrity.help"><button type="button" class="button button-secondary vms-tour-help-trigger" data-vms-tour-start="vms.ticket_integrity.monitor" data-vms-tour="ticket-integrity.help">Start Guided Tour</button></div>';
$assert(
	wp_kses($allowedHeaderActions, vms_admin_ui_header_actions_allowed_html()) === $allowedHeaderActions,
	'Header actions allowlist should preserve the current anchor, wrapper, and guided-tour button fragments.'
);

$unsafeHeaderActions = '<div class="vms-help-menu" style="display:inline-block" data-vms-tour="ticket-integrity.help" data-vms-help-action="quick_tips"><details class="vms-help-menu" style="display:inline-block"><summary class="button button-secondary">Help</summary></details><button type="button" class="button" data-vms-tour-start="vms.ticket_integrity.monitor" data-vms-tour="ticket-integrity.help" data-vms-help-action="quick_tips" data-vms-help-open="1" onclick="alert(1)">Quick Tips</button><a class="button" href="javascript:alert(1)" target="_blank">Bad</a><script>alert(1)</script></div>';
$sanitizedHeaderActions = wp_kses($unsafeHeaderActions, vms_admin_ui_header_actions_allowed_html());
$assert(strpos($sanitizedHeaderActions, '<div class="vms-help-menu" data-vms-tour="ticket-integrity.help">') !== false, 'Header actions allowlist should preserve approved wrapper attributes.');
$assert(strpos($sanitizedHeaderActions, '<button type="button" class="button" data-vms-tour-start="vms.ticket_integrity.monitor" data-vms-tour="ticket-integrity.help">Quick Tips</button>') !== false, 'Header actions allowlist should preserve approved button hooks.');
$assert(strpos($sanitizedHeaderActions, '<a class="button">Bad</a>') !== false, 'Header actions allowlist should strip unsafe href protocols while preserving approved anchor markup.');
$assert(strpos($sanitizedHeaderActions, '<details') === false && strpos($sanitizedHeaderActions, '</details>') === false, 'Legacy details markup should not survive the canonical header-actions contract.');
$assert(strpos($sanitizedHeaderActions, '<summary') === false && strpos($sanitizedHeaderActions, '</summary>') === false, 'Legacy summary markup should not survive the canonical header-actions contract.');
$assert(strpos($sanitizedHeaderActions, '<script') === false && strpos($sanitizedHeaderActions, '</script>') === false, 'Header actions contract should reject script tags.');
$assert(stripos($sanitizedHeaderActions, 'style=') === false, 'Header actions contract should reject style attributes.');
$assert(stripos($sanitizedHeaderActions, 'target=') === false, 'Header actions contract should reject unapproved anchor attributes.');
$assert(stripos($sanitizedHeaderActions, 'data-vms-help-action=') === false, 'Header actions contract should reject unapproved data attributes.');
$assert(stripos($sanitizedHeaderActions, 'data-vms-help-open=') === false, 'Header actions contract should reject undiscovered helper attributes.');
$assert(preg_match('~<[^>]+\son[a-z]+\s*=~i', $sanitizedHeaderActions) === 0, 'Header actions contract should reject inline event-handler attributes.');

$unsafeRichHtml = '<div class="notice notice-success is-dismissible" style="color:red" data-track="1" role="alert" onclick="alert(1)"><p class="bad" aria-live="assertive"><strong class="bad" data-bad="1">Saved</strong><span> now</span><a href="https://example.test">link</a><script>alert(1)</script></p></div>';
$sanitizedRichHtml = wp_kses($unsafeRichHtml, vms_admin_ui_rich_explicit_notice_allowed_html());
$assert(strpos($sanitizedRichHtml, '<div class="notice notice-success is-dismissible">') !== false, 'Rich explicit notice allowlist should preserve the allowed notice wrapper and class attribute.');
$assert(strpos($sanitizedRichHtml, '<p><strong>Saved</strong>') !== false, 'Rich explicit notice allowlist should preserve strong markup inside the notice paragraph.');
$assert(strpos($sanitizedRichHtml, ' now') !== false && strpos($sanitizedRichHtml, 'link') !== false, 'Rich explicit notice allowlist should preserve text nodes while stripping disallowed tags.');
$assert(strpos($sanitizedRichHtml, '<span') === false && strpos($sanitizedRichHtml, '</span>') === false, 'Rich explicit notice allowlist should reject span tags.');
$assert(strpos($sanitizedRichHtml, '<a ') === false && strpos($sanitizedRichHtml, '</a>') === false, 'Rich explicit notice allowlist should reject anchor tags.');
$assert(strpos($sanitizedRichHtml, '<script') === false && strpos($sanitizedRichHtml, '</script>') === false, 'Rich explicit notice allowlist should reject script tags.');
$assert(stripos($sanitizedRichHtml, 'style=') === false, 'Rich explicit notice allowlist should reject style attributes.');
$assert(stripos($sanitizedRichHtml, 'data-track=') === false && stripos($sanitizedRichHtml, 'data-bad=') === false, 'Rich explicit notice allowlist should reject data attributes.');
$assert(stripos($sanitizedRichHtml, 'role=') === false && stripos($sanitizedRichHtml, 'aria-live=') === false, 'Rich explicit notice allowlist should reject role and aria attributes.');
$assert(preg_match('~<p[^>]+\s(?:class|id|role|aria-[a-z-]+)=~i', $sanitizedRichHtml) === 0, 'Rich explicit notice allowlist should reject attributes on p.');
$assert(preg_match('~<strong[^>]+\s(?:class|id|role|aria-[a-z-]+|data-[a-z-]+)=~i', $sanitizedRichHtml) === 0, 'Rich explicit notice allowlist should reject attributes on strong.');
$assert(preg_match('~<[^>]+\son[a-z]+\s*=~i', $sanitizedRichHtml) === 0, 'Rich explicit notice allowlist should reject inline event-handler attributes.');

$unsafeHtml = '<div class="notice notice-success is-dismissible" style="color:red" data-track="1" role="alert" onclick="alert(1)"><p class="bad" aria-live="assertive" style="font-weight:bold">Saved<script>alert(1)</script><iframe src="https://example.test"></iframe><object data="bad"></object><embed src="bad"><form action="#"><input type="text" value="x"></form><a href="https://example.test">link</a><button type="button">button</button></p></div>';
$sanitizedHtml = wp_kses($unsafeHtml, vms_admin_ui_explicit_notice_allowed_html());
$assert(strpos($sanitizedHtml, '<div class="notice notice-success is-dismissible">') !== false, 'Allowed div tag and class attribute should survive.');
$assert(strpos($sanitizedHtml, '<p>') !== false, 'Allowed p tag should survive.');
$assert(strpos($sanitizedHtml, 'Saved') !== false, 'Notice text should survive sanitization.');
$assert(strpos($sanitizedHtml, '<script') === false && strpos($sanitizedHtml, '</script>') === false, 'Script tags should not survive.');
$assert(strpos($sanitizedHtml, '<iframe') === false && strpos($sanitizedHtml, '</iframe>') === false, 'Iframe tags should not survive.');
$assert(strpos($sanitizedHtml, '<object') === false && strpos($sanitizedHtml, '</object>') === false, 'Object tags should not survive.');
$assert(strpos($sanitizedHtml, '<embed') === false, 'Embed tags should not survive.');
$assert(strpos($sanitizedHtml, '<form') === false && strpos($sanitizedHtml, '</form>') === false, 'Form tags should not survive.');
$assert(strpos($sanitizedHtml, '<input') === false, 'Input tags should not survive.');
$assert(strpos($sanitizedHtml, '<a ') === false && strpos($sanitizedHtml, '</a>') === false, 'Anchor tags should not survive.');
$assert(strpos($sanitizedHtml, '<button') === false && strpos($sanitizedHtml, '</button>') === false, 'Button tags should not survive.');
$assert(preg_match('~<[^>]+\son[a-z]+\s*=~i', $sanitizedHtml) === 0, 'Inline event-handler attributes should not survive.');
$assert(stripos($sanitizedHtml, 'style=') === false, 'Style attributes should not survive.');
$assert(stripos($sanitizedHtml, 'data-track=') === false, 'Data attributes should not survive.');
$assert(stripos($sanitizedHtml, 'role=') === false, 'Role attributes should not survive.');
$assert(stripos($sanitizedHtml, 'aria-live=') === false, 'ARIA attributes should not survive.');
$assert(preg_match('~<p[^>]+\s(?:class|id|role|aria-[a-z-]+)=~i', $sanitizedHtml) === 0, 'Unapproved p attributes should not survive.');

$malformedHtml = '<div class="notice notice-success is-dismissible" data-bad="1"><p title="&quot; onmouseover=&quot;alert(1)">Broken</p></div>';
$sanitizedMalformedHtml = wp_kses($malformedHtml, vms_admin_ui_explicit_notice_allowed_html());
$assert(strpos($sanitizedMalformedHtml, 'onmouseover=') === false, 'Malformed attributes should not escape into executable attributes.');
$assert(strpos($sanitizedMalformedHtml, 'title=') === false, 'Malformed p attributes should not survive.');
$assert(strpos($sanitizedMalformedHtml, 'data-bad=') === false, 'Malformed div data attributes should not survive.');
$assert(strpos($sanitizedMalformedHtml, '<div class="notice notice-success is-dismissible"><p>Broken</p></div>') !== false, 'Malformed markup should remain inside the intended fragment contract.');

fwrite(STDOUT, "Administrator shell output remediation OK.\n");
