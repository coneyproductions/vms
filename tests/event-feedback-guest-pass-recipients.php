<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);

final class WP_Post
{
	public int $ID;
	public string $post_type = 'vms_event_plan';
	public string $post_status = 'publish';

	public function __construct(int $id)
	{
		$this->ID = $id;
	}
}

final class FeedbackGuestPassWpdb
{
	/** @var array<int,array<string,mixed>> */
	public array $claims = array();
	public int $query_count = 0;

	public function prepare(string $query, ...$args): array
	{
		return array('query' => $query, 'args' => $args);
	}

	/** @return array<int,array<string,mixed>> */
	public function get_results(array $prepared, string $format): array
	{
		unset($format);
		$this->query_count++;
		$event_plan_id = (int) end($prepared['args']);
		return array_values(array_filter($this->claims, static function (array $row) use ($event_plan_id): bool {
			return (int) ($row['event_plan_id'] ?? 0) === $event_plan_id;
		}));
	}
}

$wpdb = new FeedbackGuestPassWpdb();
$GLOBALS['feedback_paid_rows'] = array();

function __(string $text, string $domain = ''): string { unset($domain); return $text; }
function absint($value): int { return abs((int) $value); }
function sanitize_key(string $value): string { return strtolower((string) preg_replace('/[^a-z0-9_\-]/i', '', $value)); }
function sanitize_text_field(string $value): string { return trim(strip_tags($value)); }
function sanitize_email(string $value): string { return filter_var(trim($value), FILTER_VALIDATE_EMAIL) ? trim($value) : ''; }
function is_email(string $value): bool { return filter_var($value, FILTER_VALIDATE_EMAIL) !== false; }
function esc_url_raw(string $value): string { return $value; }
function get_post(int $id): WP_Post { return new WP_Post($id); }
function get_post_meta(int $id, string $key, bool $single = true) { unset($id, $single); return $key === '_vms_event_date' ? '2026-10-10' : ($key === '_vms_tec_event_id' ? 9010 : ''); }
function get_the_title(int $id): string { return 'Event ' . $id; }
function get_bloginfo(string $key): string { return $key === 'name' ? 'Test Venue' : 'UTF-8'; }
function get_permalink(int $id): string { return 'https://example.test/event/' . $id; }
function home_url(string $path = '/'): string { return 'https://example.test' . $path; }
function wp_timezone(): DateTimeZone { return new DateTimeZone('America/Chicago'); }
function wp_date(string $format, ?int $timestamp = null, ?DateTimeZone $timezone = null): string { unset($timezone); return date($format, $timestamp ?? time()); }
function bvmgr_ticket_revenue_plan_tec_meta_key(): string { return '_vms_tec_event_id'; }
function bvmgr_meta_key(string $group, string $key): string { unset($group); return $key === 'status' ? '_vms_event_plan_status' : '_' . $key; }
function bvmgr_get_ticket_sales_rows(array $args): array { return $GLOBALS['feedback_paid_rows'][(int) ($args['event_plan_id'] ?? 0)] ?? array(); }
function bvmgr_admission_table_pass_claims(): string { return 'wp_vms_pass_claims'; }
function bvmgr_admission_table_pass_tokens(): string { return 'wp_vms_pass_tokens'; }
function bvmgr_admission_table_entries(): string { return 'wp_vms_admission_entries'; }

require dirname(__DIR__) . '/includes/modules/email-followups/recipients.php';

$failures = 0;
$assert = static function (bool $condition, string $message) use (&$failures): void {
	if ($condition) {
		echo "PASS: {$message}\n";
		return;
	}
	$failures++;
	echo "FAIL: {$message}\n";
};
$paid = static function (string $email, int $qty, int $refunded = 0, int $order_id = 10, int $line_total_cents = 1000): array {
	return array(
		'customer_email' => $email,
		'customer_name' => 'Paid Buyer',
		'qty' => $qty,
		'refunded_qty' => $refunded,
		'order_id' => $order_id,
		'order_number' => 'ORDER-' . $order_id,
		'line_total_cents' => $line_total_cents,
	);
};
$claim = static function (int $id, int $event_plan_id, string $email, int $admissions, string $token_status = 'claimed'): array {
	return array(
		'claim_id' => $id,
		'event_plan_id' => $event_plan_id,
		'first_name' => 'Guest',
		'last_name' => 'Claimant',
		'email' => $email,
		'token_status' => $token_status,
		'admissions_net' => $admissions,
	);
};

// Paid-only behavior remains unchanged and no Guest Pass query runs for other templates.
$GLOBALS['feedback_paid_rows'][101] = array($paid('paid@example.test', 3, 1));
$wpdb->claims = array($claim(1, 101, 'guest@example.test', 1));
$wpdb->query_count = 0;
$paid_only = bvmgr_email_followups_event_recipients(101, 'know_before');
$assert(count($paid_only['recipients']) === 1 && $paid_only['recipients'][0]['email'] === 'paid@example.test', 'Paid-only recipient selection is preserved.');
$assert((int) $paid_only['counts']['tickets_net'] === 2 && (int) $paid_only['recipients'][0]['qty'] === 2, 'Paid ticket net quantity remains refund-aware.');
$assert($wpdb->query_count === 0, 'Guest Pass claims are not added to non-feedback follow-up templates.');

// A valid claim is eligible without using the optional marketing opt-in flag.
$GLOBALS['feedback_paid_rows'][102] = array();
$wpdb->claims = array($claim(2, 102, 'guest-only@example.test', 1));
$guest_only = bvmgr_email_followups_event_recipients(102, 'post_event');
$assert(count($guest_only['recipients']) === 1 && $guest_only['recipients'][0]['name'] === 'Guest Claimant', 'Guest Pass-only claimant is included with claimant identity.');
$assert((int) $guest_only['counts']['guest_pass_recipients'] === 1 && (int) $guest_only['counts']['guest_pass_admissions_net'] === 1, 'Guest Pass-only preview counts are reported.');

// Paid and Guest Pass recipients coexist.
$GLOBALS['feedback_paid_rows'][103] = array($paid('paid-mixed@example.test', 1));
$wpdb->claims = array($claim(3, 103, 'guest-mixed@example.test', 1));
$mixed = bvmgr_email_followups_event_recipients(103, 'post_event');
$assert(count($mixed['recipients']) === 2, 'Mixed paid and Guest Pass recipients are both included.');
$assert((int) $mixed['counts']['paid_ticket_recipients'] === 1 && (int) $mixed['counts']['guest_pass_recipients'] === 1, 'Mixed-source preview breakdown is accurate.');

// Existing zero-value ticket recipients remain eligible and are identified as other complimentary guests.
$GLOBALS['feedback_paid_rows'][107] = array($paid('comp@example.test', 2, 0, 11, 0));
$wpdb->claims = array();
$complimentary = bvmgr_email_followups_event_recipients(107, 'post_event');
$assert(count($complimentary['recipients']) === 1 && (int) $complimentary['counts']['other_complimentary_recipients'] === 1, 'Existing zero-value ticket recipients are shown as other complimentary guests.');
$assert((int) $complimentary['counts']['tickets_net'] === 2, 'Other complimentary ticket quantities remain in the established net-ticket count.');

// Normalized email deduplication spans paid rows, multiple claims, and multi-admission claims.
$GLOBALS['feedback_paid_rows'][104] = array($paid('Same@Example.Test', 1));
$wpdb->claims = array(
	$claim(4, 104, 'same@example.test', 2),
	$claim(5, 104, 'SAME@example.test', 1),
);
$deduped = bvmgr_email_followups_event_recipients(104, 'post_event');
$assert(count($deduped['recipients']) === 1, 'Duplicate normalized email appears once across paid tickets and Guest Pass claims.');
$assert((int) $deduped['recipients'][0]['paid_ticket_qty'] === 1 && (int) $deduped['recipients'][0]['guest_pass_qty'] === 3, 'Source quantities merge without creating duplicate survey recipients.');
$assert((int) $deduped['recipients'][0]['qty'] === 4, 'Two-admission claims contribute admissions but still only one email recipient.');

// Missing-email, canceled/invalid, and wrong-event claims remain excluded.
$GLOBALS['feedback_paid_rows'][105] = array();
$wpdb->claims = array(
	$claim(6, 105, '', 1),
	$claim(7, 105, 'canceled@example.test', 0),
	$claim(8, 105, 'invalid@example.test', 1, 'void'),
	$claim(9, 106, 'wrong-event@example.test', 1),
);
$excluded = bvmgr_email_followups_event_recipients(105, 'post_event');
$assert(count($excluded['recipients']) === 0, 'Missing-email, canceled, invalid-token, and wrong-event claims are excluded.');
$assert((int) $excluded['counts']['guest_pass_invalid_email'] === 1 && (int) $excluded['counts']['guest_pass_ineligible'] === 2, 'Excluded Guest Pass claim reasons are counted without exposing identities.');

$sender_source = (string) file_get_contents(dirname(__DIR__) . '/includes/modules/email-followups/sender.php');
$assert(strpos($sender_source, 'guest_pass_feedback_no_mailpoet_sync') !== false, 'Guest Pass feedback delivery does not silently enroll a guest-only claimant in MailPoet.');

exit($failures > 0 ? 1 : 0);
