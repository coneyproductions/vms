<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__) . '/');
}

final class BVMGR_Admission_Offer_Schema_Test_DB
{
	public string $prefix = 'wp_';

	public function get_charset_collate(): string
	{
		return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci';
	}
}

$GLOBALS['wpdb'] = new BVMGR_Admission_Offer_Schema_Test_DB();
$GLOBALS['bvmgr_admission_offer_test_actions'] = array();
$GLOBALS['bvmgr_admission_offer_test_filters'] = array();

function wp_json_encode($value, int $flags = 0): string
{
	return (string) json_encode($value, $flags | JSON_UNESCAPED_SLASHES);
}

function add_action($hook, $callback, $priority = 10, $accepted_args = 1): bool
{
	$GLOBALS['bvmgr_admission_offer_test_actions'][] = array($hook, $callback, $priority, $accepted_args);
	return true;
}

function add_filter($hook, $callback, $priority = 10, $accepted_args = 1): bool
{
	$GLOBALS['bvmgr_admission_offer_test_filters'][] = array($hook, $callback, $priority, $accepted_args);
	return true;
}

require_once dirname(__DIR__) . '/includes/modules/admission-offers/contracts.php';
require_once dirname(__DIR__) . '/includes/modules/admission-offers/domain.php';
require_once dirname(__DIR__) . '/includes/modules/admission-offers/keys.php';
require_once dirname(__DIR__) . '/includes/modules/admission-offers/schema.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
	$assertions++;
	if (!$condition) {
		throw new RuntimeException($message);
	}
};
$rejects = static function (callable $callback, string $code) use ($assert): void {
	try {
		$callback();
	} catch (BVMGR_Admission_Offer_Domain_Exception $error) {
		$assert($error->getMessage() === $code, 'Unexpected rejection: ' . $error->getMessage() . ', expected ' . $code);
		return;
	}
	throw new RuntimeException('Expected domain rejection: ' . $code);
};

$base = array(
	'name' => 'Phase A Test Offer',
	'max_qty_per_claim' => 4,
	'capacity_total' => 20,
	'reservation_ttl_seconds' => 1200,
	'identity_policy' => array('types' => array('email')),
	'identity_policy_version' => 1,
);

$complimentary = BVMGR_Admission_Offer_Value::from_array($base + array('offer_type' => 'complimentary'));
$assert($complimentary->to_array()['percent_basis_points'] === null, 'Complimentary Offer must not carry percent value.');
$assert($complimentary->to_array()['fixed_amount_minor'] === null, 'Complimentary Offer must not carry fixed value.');
$rejects(static fn() => BVMGR_Admission_Offer_Value::from_array($base + array('offer_type' => 'complimentary', 'fixed_amount_minor' => 0)), 'complimentary_monetary_value_forbidden');
$rejects(static fn() => BVMGR_Admission_Offer_Value::from_array($base + array('offer_type' => 'complimentary', 'currency' => 'USD')), 'complimentary_monetary_value_forbidden');

$percent = BVMGR_Admission_Offer_Value::from_array($base + array('offer_type' => 'percent', 'percent_basis_points' => 1));
$assert($percent->to_array()['percent_basis_points'] === 1, 'One basis point must be valid.');
$percent_max = BVMGR_Admission_Offer_Value::from_array($base + array('offer_type' => 'percent', 'percent_basis_points' => 9999));
$assert($percent_max->to_array()['percent_basis_points'] === 9999, '9999 basis points must be valid.');
$rejects(static fn() => BVMGR_Admission_Offer_Value::from_array($base + array('offer_type' => 'percent', 'percent_basis_points' => 0)), 'invalid_percent_value');
$rejects(static fn() => BVMGR_Admission_Offer_Value::from_array($base + array('offer_type' => 'percent', 'percent_basis_points' => 10000)), 'invalid_percent_value');
$rejects(static fn() => BVMGR_Admission_Offer_Value::from_array($base + array('offer_type' => 'percent', 'percent_basis_points' => 5000, 'fixed_amount_minor' => 100)), 'invalid_percent_value');

$fixed = BVMGR_Admission_Offer_Value::from_array($base + array('offer_type' => 'fixed', 'fixed_amount_minor' => 1500, 'currency' => 'USD'), 'USD');
$assert($fixed->to_array()['fixed_amount_minor'] === 1500, 'Fixed amount must retain integer minor units.');
$assert($fixed->to_array()['currency'] === 'USD', 'Fixed Offer must retain store currency.');
$rejects(static fn() => BVMGR_Admission_Offer_Value::from_array($base + array('offer_type' => 'fixed', 'fixed_amount_minor' => 0, 'currency' => 'USD'), 'USD'), 'invalid_fixed_value');
$rejects(static fn() => BVMGR_Admission_Offer_Value::from_array($base + array('offer_type' => 'fixed', 'fixed_amount_minor' => 100, 'currency' => 'CAD'), 'USD'), 'fixed_currency_must_match_store_currency');
$rejects(static fn() => BVMGR_Admission_Offer_Value::from_array($base + array('offer_type' => 'fixed', 'fixed_amount_minor' => 100, 'currency' => 'USD')), 'fixed_currency_must_match_store_currency');
$assert($fixed->validate_discounted_subtotal(100, 99), 'Paid discount may leave one minor unit.');
$assert(!$fixed->validate_discounted_subtotal(100, 100), 'Paid discount must never reduce subtotal below one minor unit.');
$assert(!$complimentary->validate_discounted_subtotal(100, 0), 'Complimentary Offer is not a paid pricing authority.');

$public_ids = array();
for ($index = 0; $index < 128; $index++) {
	$public_id = bvmgr_admission_offer_generate_public_id('ao');
	$assert(!isset($public_ids[$public_id]), 'Generated Offer public IDs must be unique.');
	$public_ids[$public_id] = true;
}

$eligibilities = array(
	array('scope_type' => 'event_plan', 'event_plan_id' => 101),
	array('scope_type' => 'venue', 'venue_id' => 12, 'mode' => 'exclude'),
	array('scope_type' => 'date_window', 'start_date' => '2026-10-01', 'end_date' => '2026-10-31'),
	array('scope_type' => 'season', 'season_key' => 'fall-2026'),
	array('scope_type' => 'any_event'),
);
foreach ($eligibilities as $eligibility) {
	$value = BVMGR_Admission_Offer_Eligibility_Value::from_array($eligibility)->to_array();
	$assert(strlen((string) $value['eligibility_key']) === 64, 'Eligibility must have a deterministic unique key.');
}
$rejects(static fn() => BVMGR_Admission_Offer_Eligibility_Value::from_array(array('scope_type' => 'date_window', 'start_date' => '2026-11-01', 'end_date' => '2026-10-01')), 'invalid_date_window_eligibility');
$rejects(static fn() => BVMGR_Admission_Offer_Eligibility_Value::from_array(array('scope_type' => 'any_event', 'venue_id' => 1)), 'invalid_any_event_eligibility');

$assert(bvmgr_admission_offer_normalize_scope_key(null) === 'offer', 'Default identity scope must represent Offer-wide uniqueness.');
$assert(bvmgr_admission_offer_normalize_scope_key('Provider:Campaign:ABC') === 'provider:campaign:abc', 'Provider scope must remain opaque while normalizing deterministically.');
$identity_key = str_repeat('k', 32);
$same_offer = bvmgr_admission_offer_identity_hash('offer', 'email', 'Guest@Example.com', $identity_key);
$same_offer_again = bvmgr_admission_offer_identity_hash('offer', 'email', 'guest@example.com', $identity_key);
$other_scope = bvmgr_admission_offer_identity_hash('provider:campaign:2', 'email', 'guest@example.com', $identity_key);
$assert(hash_equals($same_offer, $same_offer_again), 'Normalized identity must be stable inside one scope.');
$assert(!hash_equals($same_offer, $other_scope), 'The same identity must have a distinct enforcement key under another approved scope.');
$assert(bvmgr_admission_offer_normalize_identity('phone', '+1 (615) 555-0100') === '16155550100', 'Phone normalization must be canonical.');
$rejects(static fn() => bvmgr_admission_offer_normalize_identity('household_key', 'short'), 'invalid_explicit_household_key');
$assert(bvmgr_admission_offer_normalize_identity('household_key', 'explicit-family-key') === 'explicit-family-key', 'Explicit household key may be accepted without inference.');
$canonical_a = bvmgr_admission_offer_canonical_json_encode(array('z' => 2, 'nested' => array('b' => true, 'a' => 1.0), 'a' => '1'));
$canonical_b = bvmgr_admission_offer_canonical_json_encode(array('a' => '1', 'nested' => array('a' => 1.0, 'b' => true), 'z' => 2));
$assert($canonical_a === $canonical_b, 'Canonical JSON must recursively ignore associative insertion order.');
$assert(str_contains($canonical_a, '1.0') && str_contains($canonical_a, '"1"'), 'Canonical JSON must preserve numeric and string representation.');
$policy_a = BVMGR_Admission_Offer_Value::from_array($base + array(
	'offer_type' => 'complimentary',
	'identity_policy' => array('z' => 1, 'a' => array('y' => 2, 'x' => 1)),
));
$policy_b = BVMGR_Admission_Offer_Value::from_array($base + array(
	'offer_type' => 'complimentary',
	'identity_policy' => array('a' => array('x' => 1, 'y' => 2), 'z' => 1),
));
$assert($policy_a->to_array()['identity_policy_json'] === $policy_b->to_array()['identity_policy_json'], 'Equivalent policy data must persist identical canonical JSON.');
$assert(bvmgr_admission_offer_idempotency_hash('stable-key-123456') === bvmgr_admission_offer_idempotency_hash('stable-key-123456'), 'Idempotency hashing must be deterministic without WordPress salts.');

$assert(bvmgr_admission_offer_state_transition_allowed('offer', 'draft', 'active'), 'Draft Offer must be activatable.');
$assert(!bvmgr_admission_offer_state_transition_allowed('offer', 'ended', 'active'), 'Ended Offer must not reactivate.');
$assert(bvmgr_admission_offer_state_transition_allowed('reservation', 'held', 'expired'), 'Held reservation must support expiry.');
$assert(!bvmgr_admission_offer_state_transition_allowed('reservation', 'expired', 'held'), 'Expired reservation must require a new intent/key.');
$assert(bvmgr_admission_offer_state_transition_allowed('checkout', 'payment_pending', 'paid'), 'Checkout may become paid.');
$assert(bvmgr_admission_offer_state_transition_allowed('fulfillment', 'fulfilled', 'used'), 'Fulfillment may become used.');

$schemas = bvmgr_admission_offers_schema_sql();
$assert(count($schemas) === 9, 'Phase A must preserve all nine lifecycle tables.');
$schema_text = implode("\n", $schemas);
foreach (array(
	'vms_admission_offers',
	'vms_admission_offer_eligibility',
	'vms_admission_offer_claims',
	'vms_admission_offer_claim_identities',
	'vms_admission_offer_reservations',
	'vms_admission_offer_checkouts',
	'vms_admission_offer_order_allocations',
	'vms_admission_offer_fulfillments',
	'vms_admission_offer_events',
) as $table_suffix) {
	$assert(str_contains($schema_text, $table_suffix), 'Missing schema table: ' . $table_suffix);
}
$assert(substr_count($schema_text, 'ENGINE=InnoDB') === 9, 'Every Admission Offers table must use InnoDB.');
$assert(str_contains($schemas['offers'], 'UNIQUE KEY public_id (public_id)'), 'Offer public ID uniqueness must be database-enforced.');
$assert(str_contains($schemas['identities'], 'hash_key_version SMALLINT(5) UNSIGNED NOT NULL'), 'Identity rows must record their durable key version.');
$assert(str_contains($schemas['identities'], 'UNIQUE KEY scoped_identity (offer_id, identity_scope_key, identity_type, hash_key_version, identity_hash)'), 'Scoped identity uniqueness must include key version.');
$assert(str_contains($schemas['claims'], 'KEY distribution_campaign (distribution_provider, campaign_ref)'), 'Campaign lookups must have a direct composite index.');
$assert(str_contains($schemas['claims'], 'KEY distribution_subject (distribution_provider, distribution_subject_ref)'), 'Distribution subject lookups must have a direct composite index.');
$assert(str_contains($schemas['allocations'], 'UNIQUE KEY order_item_claim (order_provider, order_ref, order_item_ref, claim_id)'), 'Allocation idempotency must compare complete opaque references.');
$assert(str_contains($schemas['reservations'], 'UNIQUE KEY offer_idempotency (offer_id, idempotency_key_hash)'), 'Reservation idempotency must be database-enforced.');
$assert(str_contains($schemas['events'], 'UNIQUE KEY entity_event (entity_type, entity_id, event_key)'), 'Event retries must be idempotent.');

$assert(interface_exists('BVMGR_Admission_Offer_Distribution_Provider_Interface'), 'Distribution provider contract must load.');
$assert(interface_exists('BVMGR_Admission_Offer_Pricing_Adapter_Interface'), 'Pricing adapter contract must load.');
$assert(interface_exists('BVMGR_Admission_Offer_Fulfillment_Provider_Interface'), 'Fulfillment provider contract must load.');

$runtime_dir = dirname(__DIR__) . '/includes/modules/admission-offers';
$runtime_source = '';
foreach (glob($runtime_dir . '/*.php') ?: array() as $path) {
	if (in_array(basename($path), array('woo-checkout-service.php', 'woo-cart-adapter.php'), true)) {
		continue;
	}
	$runtime_source .= "\n" . (string) file_get_contents($path);
}
foreach (array('register_rest_route(', 'add_menu_page(', 'add_submenu_page(', 'add_shortcode(', 'wp_insert_post(', 'wc_create_order(', 'tribe_', 'vms_pass_') as $forbidden) {
	$assert(stripos($runtime_source, $forbidden) === false, 'Admission Offers core contains forbidden runtime surface: ' . $forbidden);
}
$assert(stripos($runtime_source, 'Backstage Outreach') === false, 'Core foundation must not depend on Backstage Outreach.');
$assert(stripos($runtime_source, 'Commerce Discounts') === false, 'Core foundation must not depend on Commerce Discounts.');
$loader_source = (string) file_get_contents($runtime_dir . '/admission-offers.php');
$assert(substr_count($loader_source, 'add_action(') === 1, 'Loader may register only its schema readiness hook directly.');
require_once $runtime_dir . '/admission-offers.php';
$registered_actions = array_column($GLOBALS['bvmgr_admission_offer_test_actions'], 1);
$registered_filters = array_column($GLOBALS['bvmgr_admission_offer_test_filters'], 1);
$assert(in_array('bvmgr_admission_offer_observe_native_audit', $registered_actions, true)
	&& in_array('bvmgr_admission_offers_foundation_boot', $registered_actions, true), 'Phase B lifecycle and schema readiness hooks must remain registered.');
$assert(in_array('bvmgr_admission_offer_woo_store_validate_add', $registered_actions, true)
	&& in_array('bvmgr_admission_offer_woo_classic_checkout_barrier', $registered_actions, true)
	&& in_array('bvmgr_admission_offer_woo_store_api_add_to_cart_data', $registered_filters, true)
	&& in_array('bvmgr_admission_offer_woo_add_to_cart_validation', $registered_filters, true), 'Phase C2 must register both classic and Store API validation boundaries.');
$assert(!in_array('woocommerce_before_calculate_totals', array_column($GLOBALS['bvmgr_admission_offer_test_actions'], 0), true)
	&& !in_array('woocommerce_cart_calculate_fees', array_column($GLOBALS['bvmgr_admission_offer_test_actions'], 0), true)
	&& !in_array('woocommerce_checkout_create_order', array_column($GLOBALS['bvmgr_admission_offer_test_actions'], 0), true), 'Phase C2 must register no price, discount, or order-creation hook.');

echo "Admission Offers domain/schema: PASS ({$assertions} assertions)\n";
