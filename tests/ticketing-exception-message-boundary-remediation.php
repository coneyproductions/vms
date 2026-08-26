<?php
declare(strict_types=1);

function vms_test_fail(string $message): void
{
	throw new RuntimeException($message);
}

function vms_test_assert_true(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	vms_test_fail($message);
}

/**
 * @param mixed $expected
 * @param mixed $actual
 */
function vms_test_assert_same($expected, $actual, string $message): void
{
	if ($expected === $actual) {
		return;
	}

	vms_test_fail(
		$message
		. "\nExpected: " . var_export($expected, true)
		. "\nActual: " . var_export($actual, true)
	);
}

function vms_test_assert_contains(string $needle, string $haystack, string $message): void
{
	vms_test_assert_true(strpos($haystack, $needle) !== false, $message . "\nMissing: " . $needle);
}

function vms_test_assert_not_contains(string $needle, string $haystack, string $message): void
{
	vms_test_assert_true(strpos($haystack, $needle) === false, $message . "\nUnexpected: " . $needle);
}

function vms_test_read_file(string $path): string
{
	$contents = file_get_contents($path);
	if (!is_string($contents) || $contents === '') {
		vms_test_fail('Failed to read source file: ' . $path);
	}

	return $contents;
}

function vms_test_extract_function(string $source, string $name): string
{
	$pattern = '~function\s+' . preg_quote($name, '~') . '\s*\(~';
	if (!preg_match($pattern, $source, $matches, PREG_OFFSET_CAPTURE)) {
		vms_test_fail('Unable to locate function ' . $name . '.');
	}
	$start = (int) $matches[0][1];

	$brace = strpos($source, '{', $start);
	if ($brace === false) {
		vms_test_fail('Unable to locate opening brace for ' . $name . '.');
	}

	$depth = 1;
	$length = strlen($source);
	for ($i = $brace + 1; $i < $length; $i++) {
		$char = $source[$i];
		if ($char === '{') {
			$depth++;
			continue;
		}
		if ($char === '}') {
			$depth--;
			if ($depth === 0) {
				return substr($source, $start, ($i - $start) + 1);
			}
		}
	}

	vms_test_fail('Unable to locate closing brace for ' . $name . '.');
}

/**
 * @param mixed $value
 */
function absint($value): int
{
	return abs((int) $value);
}

/**
 * @param mixed $value
 */
function sanitize_text_field($value): string
{
	return trim(strip_tags((string) $value));
}

/**
 * @param mixed $value
 */
function wp_strip_all_tags($value): string
{
	return trim(strip_tags((string) $value));
}

/**
 * @param mixed $value
 * @return mixed
 */
function wp_unslash($value)
{
	return $value;
}

function __(string $text, string $domain = ''): string
{
	return $text;
}

function vms_test_reset_wc_state(): void
{
	$GLOBALS['vms_test_wc_notices'] = array();
	$GLOBALS['vms_test_checkout_blocker_seed'] = array();
	$GLOBALS['vms_test_store_api_request_is_checkout'] = true;
	$GLOBALS['vms_test_validate_add_to_cart_return'] = true;
	$GLOBALS['vms_test_validate_add_to_cart_notices'] = array();
	$GLOBALS['vms_test_validate_add_to_cart_calls'] = array();
}

/**
 * @return array<int,mixed>|array<string,array<int,mixed>>
 */
function wc_get_notices(string $type = '')
{
	$notices = $GLOBALS['vms_test_wc_notices'] ?? array();
	if ($type === '') {
		return $notices;
	}

	$type_notices = $notices[$type] ?? array();
	return is_array($type_notices) ? $type_notices : array();
}

/**
 * @param array<string,array<int,mixed>> $notices
 */
function wc_set_notices(array $notices): void
{
	$GLOBALS['vms_test_wc_notices'] = $notices;
}

function wc_clear_notices(): void
{
	$GLOBALS['vms_test_wc_notices'] = array();
}

/**
 * @param mixed $message
 */
function wc_add_notice($message, string $type = 'success'): void
{
	if (!isset($GLOBALS['vms_test_wc_notices'][$type]) || !is_array($GLOBALS['vms_test_wc_notices'][$type])) {
		$GLOBALS['vms_test_wc_notices'][$type] = array();
	}

	$GLOBALS['vms_test_wc_notices'][$type][] = array('notice' => (string) $message);
}

function WC(): object
{
	static $wc = null;
	if ($wc === null) {
		$wc = (object) array('cart' => (object) array());
	}

	return $wc;
}

function vms_ticketing_v2_enforce_live_event_items_in_cart(): void
{
	foreach ((array) ($GLOBALS['vms_test_checkout_blocker_seed']['live'] ?? array()) as $message) {
		wc_add_notice($message, 'error');
	}
}

function vms_ticketing_v2_enforce_early_price_caps_in_cart(): void
{
	foreach ((array) ($GLOBALS['vms_test_checkout_blocker_seed']['early_caps'] ?? array()) as $message) {
		wc_add_notice($message, 'error');
	}
}

function vms_ticketing_v2_enforce_ticket_max_qtys_in_cart(): void
{
	foreach ((array) ($GLOBALS['vms_test_checkout_blocker_seed']['max_qty'] ?? array()) as $message) {
		wc_add_notice($message, 'error');
	}
}

function vms_ticketing_v2_enforce_verified_ticket_limits_in_cart(): void
{
	foreach ((array) ($GLOBALS['vms_test_checkout_blocker_seed']['verified_limits'] ?? array()) as $message) {
		wc_add_notice($message, 'error');
	}
}

function vms_ticketing_v2_enforce_ticket_ratio_rules_in_cart(): void
{
	foreach ((array) ($GLOBALS['vms_test_checkout_blocker_seed']['ratio'] ?? array()) as $message) {
		wc_add_notice($message, 'error');
	}
}

function vms_ticketing_v2_enforce_claim_assignments_in_cart(): void
{
	foreach ((array) ($GLOBALS['vms_test_checkout_blocker_seed']['claim_assignments'] ?? array()) as $message) {
		wc_add_notice($message, 'error');
	}
}

function vms_ticketing_v2_enforce_ticket_visibility_rules(): void
{
	foreach ((array) ($GLOBALS['vms_test_checkout_blocker_seed']['visibility'] ?? array()) as $message) {
		wc_add_notice($message, 'error');
	}
}

function vms_ticketing_v2_enforce_cart_rules(): void
{
	foreach ((array) ($GLOBALS['vms_test_checkout_blocker_seed']['cart_rules'] ?? array()) as $message) {
		wc_add_notice($message, 'error');
	}
}

function vms_ticketing_v2_store_api_request_is_checkout(): bool
{
	return !empty($GLOBALS['vms_test_store_api_request_is_checkout']);
}

/**
 * @param mixed $passed
 * @param mixed $product_id
 * @param mixed $quantity
 * @param mixed $variation_id
 * @param mixed $variations
 * @param mixed $cart_item_data
 * @return mixed
 */
function vms_ticketing_v2_validate_add_to_cart($passed, $product_id, $quantity, $variation_id = 0, $variations = array(), $cart_item_data = array())
{
	$GLOBALS['vms_test_validate_add_to_cart_calls'][] = array(
		'passed' => $passed,
		'product_id' => $product_id,
		'quantity' => $quantity,
		'variation_id' => $variation_id,
		'variations' => $variations,
		'cart_item_data' => $cart_item_data,
	);

	foreach ((array) ($GLOBALS['vms_test_validate_add_to_cart_notices'] ?? array()) as $message) {
		wc_add_notice($message, 'error');
	}

	return $GLOBALS['vms_test_validate_add_to_cart_return'];
}

class WP_Error
{
	/** @var array<string,array<int,string>> */
	public $errors = array();

	public function add(string $code, string $message): void
	{
		if (!isset($this->errors[$code])) {
			$this->errors[$code] = array();
		}

		$this->errors[$code][] = $message;
	}

	/**
	 * @return string[]
	 */
	public function get_error_messages(): array
	{
		$messages = array();
		foreach ($this->errors as $code_messages) {
			foreach ($code_messages as $message) {
				$messages[] = $message;
			}
		}

		return $messages;
	}
}

try {
	$pluginRoot = dirname(__DIR__);
	$ticketingRulesPath = $pluginRoot . '/includes/integrations/ticketing-rules-v2.php';
	$webhookPath = $pluginRoot . '/includes/social-share/providers/class-provider-webhook.php';
	$queueRunnerPath = $pluginRoot . '/includes/social-share/queue-runner.php';
	$queueRepoPath = $pluginRoot . '/includes/social-share/queue-repo.php';
	$socialAdminPath = $pluginRoot . '/includes/social-share/admin.php';
	$socialEventPanelPath = $pluginRoot . '/includes/social-share/event-plan-panel.php';
	$socialAuditPath = $pluginRoot . '/includes/social-share/audit.php';
	$ticketingRulesSource = vms_test_read_file($ticketingRulesPath);
	$webhookSource = vms_test_read_file($webhookPath);
	$queueRunnerSource = vms_test_read_file($queueRunnerPath);
	$queueRepoSource = vms_test_read_file($queueRepoPath);
	$socialAdminSource = vms_test_read_file($socialAdminPath);
	$socialEventPanelSource = vms_test_read_file($socialEventPanelPath);
	$socialAuditSource = vms_test_read_file($socialAuditPath);

	$ignoreToken = 'phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped';
	vms_test_assert_same(
		2,
		substr_count($ticketingRulesSource, $ignoreToken),
		'Ticketing Store API exception remediation should keep exactly two line-specific ExceptionNotEscaped suppressions.'
	);
	vms_test_assert_same(
		2,
		substr_count($webhookSource, $ignoreToken),
		'Webhook exception findings should remain limited to the two completed WPORG-28R-C2 boundaries.'
	);

	$webhookPublishSource = vms_test_extract_function($webhookSource, 'publish');
	vms_test_assert_same(
		2,
		substr_count($webhookPublishSource, $ignoreToken),
		'Accepted webhook exception findings should remain inside BVMGR_Social_Provider_Webhook::publish().'
	);
	vms_test_assert_contains(
		"throw new RuntimeException((string) \$response->get_error_message()); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal plain-text webhook transport diagnostic; the queue runner sanitizes it for storage and downstream sinks escape or JSON-encode it contextually.",
		$webhookPublishSource,
		'Webhook transport diagnostics should keep the exact WPORG-28R-C2 bounded exception boundary.'
	);
	vms_test_assert_contains(
		"throw new RuntimeException('Webhook returned HTTP ' . \$code); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal plain-text webhook status diagnostic; the queue runner sanitizes it for storage and downstream sinks escape or JSON-encode it contextually.",
		$webhookPublishSource,
		'Webhook status diagnostics should keep the exact WPORG-28R-C2 bounded exception boundary.'
	);
	vms_test_assert_contains(
		"\$message = sanitize_text_field((string) (\$class['message'] ?? \$error->getMessage()));",
		$queueRunnerSource,
		'Webhook exception diagnostics should still be sanitized by the queue runner before storage.'
	);
	vms_test_assert_contains(
		"if (\$k === 'last_error_message' || \$k === 'destination_id' || \$k === 'platform_post_id') {\n\t\t\t\t\$v = sanitize_text_field((string) \$v);",
		$queueRepoSource,
		'Webhook exception diagnostics should still be sanitized by the queue repository update boundary.'
	);
	vms_test_assert_contains(
		"echo '<td>' . esc_html((string) \$row['last_error_message']) . '</td>';",
		$socialAdminSource,
		'Webhook exception diagnostics should remain escaped in the Social Sharing admin queue table.'
	);
	vms_test_assert_contains(
		"echo '<p class=\"description\">' . esc_html((string) \$last_queue['last_error_message']) . '</p>';",
		$socialEventPanelSource,
		'Webhook exception diagnostics should remain escaped in the Social Sharing event panel.'
	);
	vms_test_assert_contains(
		"\$details_json = wp_json_encode(\$sanitized_details);",
		$socialAuditSource,
		'Webhook exception diagnostics should remain JSON-encoded in Social Sharing audit details.'
	);

	$checkoutUpdateSource = vms_test_extract_function($ticketingRulesSource, 'vms_ticketing_v2_store_api_checkout_update_order_meta');
	vms_test_assert_contains(
		'throw new Exception(implode("\\n", $messages)); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped',
		$checkoutUpdateSource,
		'Checkout blocker Store API throw should keep the bounded line-specific suppression on the plain-text exception sink.'
	);

	$validateAddSource = vms_test_extract_function($ticketingRulesSource, 'vms_ticketing_v2_store_api_validate_add_to_cart');
	vms_test_assert_contains(
		'throw new Exception($message); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped',
		$validateAddSource,
		'Store API add-to-cart throw should keep the bounded line-specific suppression on the plain-text exception sink.'
	);

	eval(vms_test_extract_function($ticketingRulesSource, 'vms_ticketing_v2_atomic_error_notices'));
	eval(vms_test_extract_function($ticketingRulesSource, 'vms_ticketing_v2_capture_checkout_blocker_error_messages'));
	eval(vms_test_extract_function($ticketingRulesSource, 'vms_ticketing_v2_store_api_add_checkout_blocker_errors'));
	eval($checkoutUpdateSource);
	eval($validateAddSource);

	vms_test_reset_wc_state();
	$GLOBALS['vms_test_wc_notices'] = array(
		'error' => array(array('notice' => 'Existing cart error')),
		'success' => array(array('notice' => 'Existing success notice')),
	);
	$GLOBALS['vms_test_checkout_blocker_seed'] = array(
		'live' => array('<strong>Seat 1</strong> assignment missing'),
		'ratio' => array('Seat 1 assignment missing'),
		'claim_assignments' => array(' Buyer <em>must</em> verify '),
	);

	$messages = vms_ticketing_v2_capture_checkout_blocker_error_messages();
	vms_test_assert_same(
		array('Seat 1 assignment missing', 'Buyer must verify'),
		$messages,
		'Checkout blocker capture should normalize Woo notices into deduplicated plain-text strings.'
	);
	vms_test_assert_same(
		array(
			'error' => array(array('notice' => 'Existing cart error')),
			'success' => array(array('notice' => 'Existing success notice')),
		),
		wc_get_notices(),
		'Checkout blocker capture should restore the prior Woo notice stack after sampling validation errors.'
	);

	$wpError = new WP_Error();
	vms_ticketing_v2_store_api_add_checkout_blocker_errors($wpError, null);
	vms_test_assert_same(
		array('Seat 1 assignment missing', 'Buyer must verify'),
		$wpError->get_error_messages(),
		'Store API cart errors should receive the same plain-text blocker messages that later surface through REST JSON responses.'
	);

	try {
		vms_ticketing_v2_store_api_checkout_update_order_meta((object) array('id' => 99));
		vms_test_fail('Checkout update should throw when plain-text blocker messages exist.');
	} catch (Exception $exception) {
		vms_test_assert_same(
			"Seat 1 assignment missing\nBuyer must verify",
			$exception->getMessage(),
			'Checkout update exceptions should carry the unescaped plain-text validation summary for the REST error payload.'
		);
	}

	vms_test_reset_wc_state();
	$GLOBALS['vms_test_wc_notices'] = array(
		'notice' => array(array('notice' => 'Preserved notice')),
	);
	$GLOBALS['vms_test_validate_add_to_cart_return'] = false;
	$GLOBALS['vms_test_validate_add_to_cart_notices'] = array('<span>Buyer <em>must</em> log in</span>');

	try {
		vms_ticketing_v2_store_api_validate_add_to_cart(null, array('id' => 77, 'quantity' => 2));
		vms_test_fail('Store API add-to-cart validation should throw when the underlying Woo notice stack contains an error.');
	} catch (Exception $exception) {
		vms_test_assert_same(
			'Buyer must log in',
			$exception->getMessage(),
			'Store API add-to-cart exceptions should pass through the first normalized plain-text validation notice without HTML escaping.'
		);
	}

	vms_test_assert_same(
		array(
			'notice' => array(array('notice' => 'Preserved notice')),
		),
		wc_get_notices(),
		'Store API add-to-cart validation should restore the prior Woo notice stack after harvesting plain-text errors.'
	);
	vms_test_assert_same(
		1,
		count($GLOBALS['vms_test_validate_add_to_cart_calls']),
		'Store API add-to-cart validation should call the existing ticketing validation boundary exactly once.'
	);

	vms_test_reset_wc_state();
	$GLOBALS['vms_test_validate_add_to_cart_return'] = false;
	$GLOBALS['vms_test_validate_add_to_cart_notices'] = array();

	try {
		vms_ticketing_v2_store_api_validate_add_to_cart(null, array('id' => 55));
		vms_test_fail('Store API add-to-cart validation should throw its fallback message when validation fails without a harvested notice.');
	} catch (Exception $exception) {
		vms_test_assert_same(
			'This item could not be added to cart.',
			$exception->getMessage(),
			'Store API add-to-cart validation should retain the existing plain-text fallback message for empty notice stacks.'
		);
	}

	echo "ticketing exception message boundary remediation: PASS\n";
} catch (Throwable $throwable) {
	fwrite(STDERR, $throwable->getMessage() . "\n");
	exit(1);
}
