<?php
/**
 * Isolated BVM event ticket-sales provider contract tests.
 *
 * Run with: php tests/reporting-provider-contract.php
 */

declare(strict_types=1);

define('ABSPATH', __DIR__);

function sanitize_key($value): string
{
	return (string) preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value));
}

function wp_strip_all_tags($value): string
{
	return strip_tags((string) $value);
}

function absint($value): int
{
	return abs((int) $value);
}

function __(string $text, string $domain = ''): string
{
	unset($domain);
	return $text;
}

final class WP_Error
{
	private string $code;
	private string $message;

	public function __construct(string $code, string $message)
	{
		$this->code = $code;
		$this->message = $message;
	}

	public function get_error_code(): string
	{
		return $this->code;
	}

	public function get_error_message(): string
	{
		return $this->message;
	}
}

function is_wp_error($value): bool
{
	return $value instanceof WP_Error;
}

function reporting_provider_assert($condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function reporting_provider_same($expected, $actual, string $message): void
{
	reporting_provider_assert(
		$expected === $actual,
		$message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)
	);
}

function reporting_provider_reset(): void
{
	$GLOBALS['bvmgr_reporting_providers'] = array();
	$GLOBALS['reporting_provider_calls'] = array();
}

require_once dirname(__DIR__) . '/includes/core/reporting-providers.php';

reporting_provider_same(1, bvmgr_reporting_provider_contract_version(), 'Contract version changed.');
reporting_provider_reset();
reporting_provider_assert(!bvmgr_reporting_register_provider(array()), 'Invalid provider should be rejected.');

$unavailable = static function (int $plan_id, array $context): array {
	$GLOBALS['reporting_provider_calls'][] = 'unavailable:' . $plan_id . ':' . ($context['scope'] ?? '');
	return array(
		'available' => false,
		'calculated' => false,
		'warnings' => array('Primary provider has no result.'),
	);
};
$valid = static function (int $plan_id, array $context): array {
	$GLOBALS['reporting_provider_calls'][] = 'valid:' . $plan_id . ':' . ($context['scope'] ?? '');
	return array(
		'available' => true,
		'calculated' => true,
		'source' => 'fixture_truth',
		'source_label' => 'Fixture truth',
		'paid_qty' => 17,
		'free_qty' => 3,
		'total_qty' => 20,
		'revenue_cents' => 34567,
		'door_paid_qty' => -8,
		'has_countable_data' => true,
		'warnings' => array('<b>Provider warning</b>', 'Provider warning'),
	);
};

reporting_provider_assert(bvmgr_reporting_register_provider(array(
	'id' => 'later-valid',
	'label' => 'Later valid',
	'version' => '2.4.6',
	'contract_version' => 1,
	'capabilities' => array('event_ticket_sales'),
	'priority' => 20,
	'callback' => $valid,
)), 'Valid provider should register.');
reporting_provider_assert(bvmgr_reporting_register_provider(array(
	'id' => 'first-unavailable',
	'label' => 'First unavailable',
	'version' => '1.0.0',
	'contract_version' => 1,
	'capabilities' => array('event_ticket_sales'),
	'priority' => 10,
	'callback' => $unavailable,
)), 'Unavailable provider descriptor should register.');
reporting_provider_assert(!bvmgr_reporting_register_provider(array(
	'id' => 'later-valid',
	'label' => 'Duplicate',
	'version' => '9.9.9',
	'contract_version' => 1,
	'capabilities' => array('event_ticket_sales'),
	'callback' => $valid,
)), 'Duplicate provider ID should be rejected.');

$resolved = bvmgr_reporting_resolve_event_ticket_sales(2534, array('scope' => 'event_command_center'));
reporting_provider_same(array('unavailable:2534:event_command_center', 'valid:2534:event_command_center'), $GLOBALS['reporting_provider_calls'], 'Providers should resolve deterministically by priority.');
reporting_provider_assert($resolved['available'] && $resolved['calculated'], 'Valid result should resolve.');
reporting_provider_same('later-valid', $resolved['provider_id'], 'Provider identity should remain observable.');
reporting_provider_same('2.4.6', $resolved['provider_version'], 'Provider version should remain observable.');
reporting_provider_same(1, $resolved['provider_contract_version'], 'Provider contract version should remain observable.');
reporting_provider_same('fixture_truth', $resolved['source'], 'Source provenance should survive normalization.');
reporting_provider_same(17, $resolved['paid_qty'], 'Paid quantity changed.');
reporting_provider_same(0, $resolved['door_paid_qty'], 'Negative quantities should normalize to zero.');
reporting_provider_same(array('Primary provider has no result.', 'Provider warning'), $resolved['warnings'], 'Provider warnings should be normalized and retained.');
reporting_provider_same(array('unavailable', 'calculated'), array_column($resolved['provider_attempts'], 'status'), 'Attempt diagnostics changed.');

reporting_provider_reset();
reporting_provider_assert(bvmgr_reporting_register_provider(array(
	'id' => 'valid-empty',
	'label' => 'Valid empty',
	'version' => '1.0.0',
	'contract_version' => 1,
	'capabilities' => array('event_ticket_sales'),
	'callback' => static function (): array {
		return array('available' => true, 'calculated' => true, 'source' => 'empty_truth');
	},
)), 'Valid-empty provider should register.');
$empty = bvmgr_reporting_resolve_event_ticket_sales(2534);
reporting_provider_assert($empty['available'] && $empty['calculated'], 'A calculated zero-result must remain valid.');
reporting_provider_same(0, $empty['total_qty'], 'Valid-empty total should remain zero.');
reporting_provider_same('empty_truth', $empty['source'], 'Valid-empty provenance changed.');

reporting_provider_reset();
reporting_provider_assert(bvmgr_reporting_register_provider(array(
	'id' => 'wp-error',
	'label' => 'WP error provider',
	'version' => '1.0.0',
	'contract_version' => 1,
	'capabilities' => array('event_ticket_sales'),
	'priority' => 10,
	'callback' => static function (): WP_Error {
		return new WP_Error('fixture_unavailable', 'Fixture provider unavailable.');
	},
)), 'WP_Error provider should register.');
reporting_provider_assert(bvmgr_reporting_register_provider(array(
	'id' => 'throwing',
	'label' => 'Throwing provider',
	'version' => '1.0.0',
	'contract_version' => 1,
	'capabilities' => array('event_ticket_sales'),
	'priority' => 20,
	'callback' => static function (): array {
		throw new RuntimeException('Synthetic failure.');
	},
)), 'Throwing provider should register.');
$failed = bvmgr_reporting_resolve_event_ticket_sales(2534);
reporting_provider_assert(!$failed['available'] && !$failed['calculated'], 'All-provider failure should fail safely.');
reporting_provider_same(array('error', 'exception'), array_column($failed['provider_attempts'], 'status'), 'Failure statuses changed.');
reporting_provider_assert(count($failed['errors']) === 2, 'Provider errors should be isolated and observable.');

reporting_provider_reset();
$absent = bvmgr_reporting_resolve_event_ticket_sales(2534);
reporting_provider_assert(!$absent['available'] && $absent['provider_attempts'] === array(), 'Missing provider should return the safe empty contract.');
reporting_provider_assert(!bvmgr_reporting_resolve_event_ticket_sales(0)['available'], 'Invalid Event Plan should fail safely.');

echo "Reporting provider contract PASS\n";
