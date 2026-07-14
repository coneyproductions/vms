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

$pluginRoot = dirname(__DIR__);
$shellSource = file_get_contents($pluginRoot . '/includes/admin-ui/shell.php');
$statusSource = file_get_contents($pluginRoot . '/includes/modules/status-notices/admin-ui.php');
$continuitySource = file_get_contents($pluginRoot . '/includes/admin/continuity-binder.php');
$dueDatesSource = file_get_contents($pluginRoot . '/includes/admin/due-dates.php');
$squareSyncProtectionSource = file_get_contents($pluginRoot . '/includes/admin/square-sync-protection.php');
$staffCertificationsSource = file_get_contents($pluginRoot . '/includes/admin/staff-certifications.php');
$socialSource = file_get_contents($pluginRoot . '/includes/social-share/admin.php');
$emailFollowupsSource = file_get_contents($pluginRoot . '/includes/modules/email-followups/admin-ui.php');
$eventPlanImportSource = file_get_contents($pluginRoot . '/includes/admin/data-tools/page-event-plan-import.php');
$eventPlanImportActionsSource = file_get_contents($pluginRoot . '/includes/admin/data-tools/actions-event-plan-import.php');
$eventPlanImportEngineSource = file_get_contents($pluginRoot . '/includes/services/event-plan-import/event-plan-import-engine.php');
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
$assert(is_string($eventPlanImportSource) && $eventPlanImportSource !== '', 'Event Plan Import source should be readable.');
$assert(is_string($eventPlanImportActionsSource) && $eventPlanImportActionsSource !== '', 'Event Plan Import actions source should be readable.');
$assert(is_string($eventPlanImportEngineSource) && $eventPlanImportEngineSource !== '', 'Event Plan Import engine source should be readable.');
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
$assert(function_exists('vms_admin_ui_header_actions_allowed_html'), 'Header actions allowlist helper should be defined.');
$assert(
	$normalizeAllowedHtml(vms_admin_ui_explicit_notice_allowed_html()) === $normalizeAllowedHtml($expectedAllowed),
	'Explicit notice allowlist should contain only div[class] and p.'
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
	preg_match('~echo\s+[\'"]<div class="vms-admin-shell__actions">[\'"]\s*\.\s*wp_kses\s*\(\s*\$actions_html\s*,\s*vms_admin_ui_header_actions_allowed_html\s*\(\s*\)\s*\)\s*\.\s*[\'"]</div>[\'"]\s*;~s', $shellSource) === 1,
	'Admin shell should apply the dedicated allowlist at the final header-actions sink.'
);
$assert(strpos($shellSource, 'echo $explicit_notices_html;') === false, 'Admin shell should not leave a raw explicit notice echo sink.');
$assert(strpos($shellSource, 'esc_html($explicit_notices_html') === false, 'Admin shell should not text-escape the explicit notice fragment.');
$assert(strpos($shellSource, 'wp_kses_post($explicit_notices_html') === false, 'Admin shell should not use wp_kses_post() for the explicit notice sink.');
$assert(!preg_match('~wp_kses_allowed_html\s*\(\s*[\'"]post[\'"]\s*\)~', $shellSource), 'Admin shell should not use the post allowlist for the explicit notice sink.');
$assert(strpos($shellSource, 'echo \'<div class="vms-admin-shell__actions">\' . $actions_html . \'</div>\';') === false, 'Admin shell should not leave a raw header-actions echo sink.');
$assert(strpos($shellSource, 'esc_html($actions_html') === false, 'Admin shell should not text-escape the header-actions fragment.');
$assert(strpos($shellSource, 'wp_kses_post($actions_html') === false, 'Admin shell should not use wp_kses_post() for the header-actions sink.');
$assert(strpos($shellSource, 'echo $captured_notices_html;') !== false, 'Captured notice sink should remain untouched.');
$assert(strpos($shellSource, 'echo $content_html;') !== false, 'Shell content sink should remain untouched.');
$assert(strpos($shellSource, 'wp_kses($actions_html, vms_admin_ui_header_actions_allowed_html())') !== false, 'Dedicated header-actions allowlist should be applied only to actions.');
$assert(strpos($shellSource, 'wp_kses($captured_notices_html') === false, 'Dedicated explicit notice allowlist should not be applied to captured notices.');
$assert(strpos($shellSource, 'wp_kses($content_html') === false, 'Dedicated explicit notice allowlist should not be applied to shell content.');
$assert(strpos($bootstrapSource, "require_once __DIR__ . '/tours/tours.php';") !== false, 'Canonical bootstrap should load the shared tours helper file.');
$assert(strpos($bootstrapSource, 'class-vms-tours.php') === false, 'Canonical bootstrap should not directly load the legacy core tours help-button file.');

$allIncludeSource = '';
$actionCallerFiles = array();
$noticesCallbackFiles = array();
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
}

$noticesCallbackCount = preg_match_all('~[\'"]notices_callback[\'"]\s*=>~', $allIncludeSource, $unusedMatches);
$statusNoticeCallbackCount = preg_match_all('~[\'"]notices_callback[\'"]\s*=>\s*[\'"]vms_status_notice_notice_bar[\'"]~', $allIncludeSource, $unusedStatusMatches);
$continuityNoticeCallbackCount = preg_match_all('~[\'"]notices_callback[\'"]\s*=>\s*[\'"]vms_continuity_binder_render_updated_notice[\'"]~', $allIncludeSource, $unusedContinuityMatches);
$dueDatesNoticeCallbackCount = preg_match_all('~[\'"]notices_callback[\'"]\s*=>\s*[\'"]vms_due_render_admin_notices[\'"]~', $allIncludeSource, $unusedDueMatches);
$squareSyncNoticeCallbackCount = preg_match_all('~[\'"]notices_callback[\'"]\s*=>\s*[\'"]vms_square_sync_protection_render_admin_notice[\'"]~', $allIncludeSource, $unusedSquareMatches);
$staffCertificationsNoticeCallbackCount = preg_match_all('~[\'"]notices_callback[\'"]\s*=>\s*function\s*\(\)\s*use\s*\(\s*\$pending\s*\)\s*:\s*void~', $staffCertificationsSource, $unusedStaffMatches);
$emailFollowupsNoticeCallbackCount = preg_match_all('~[\'"]notices_callback[\'"]\s*=>\s*\$render_notices~', $emailFollowupsSource, $unusedEmailFollowupsMatches);
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
	'admin/square-sync-protection.php',
	'admin/staff-certifications.php',
	'modules/email-followups/admin-ui.php',
	'modules/status-notices/admin-ui.php',
	'social-share/admin.php',
);
sort($actionCallerFiles);
sort($expectedActionCallerFiles);
$noticesCallbackFiles = array_values(array_unique($noticesCallbackFiles));
sort($noticesCallbackFiles);
sort($expectedNoticesCallbackFiles);
$assert($noticesCallbackCount === 9, 'Only nine production notices_callback assignments should exist.');
$assert($statusNoticeCallbackCount === 2, 'Status Notices should still contribute exactly two production notices_callback callers.');
$assert($continuityNoticeCallbackCount === 1, 'Continuity Binder should contribute exactly one production notices_callback caller.');
$assert($dueDatesNoticeCallbackCount === 1, 'Due Dates should contribute exactly one production notices_callback caller.');
$assert($squareSyncNoticeCallbackCount === 1, 'Square Sync Protection should contribute exactly one production notices_callback caller.');
$assert($staffCertificationsNoticeCallbackCount === 1, 'Staff Certifications should contribute exactly one production notices_callback caller.');
$assert($emailFollowupsNoticeCallbackCount === 1, 'Email Follow-Ups should contribute exactly one production notices_callback caller.');
$assert($eventPlanImportNoticeCallbackCount === 1, 'Event Plan Import should contribute exactly one production notices_callback caller.');
$assert($socialNoticeCallbackCount === 1, 'Social Sharing should contribute exactly one production notices_callback caller.');
$assert($actionCallerFiles === $expectedActionCallerFiles, 'Header-actions caller inventory should stay limited to the inspected production files.');
$assert($noticesCallbackFiles === $expectedNoticesCallbackFiles, 'Explicit notice callbacks should remain limited to Status Notices, Continuity Binder, Event Plan Import, Due Dates, Square Sync Protection, Staff Certifications, Email Follow-Ups, and Social Sharing.');
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
