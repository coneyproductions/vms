<?php
defined('ABSPATH') || exit;

class BVMGR_Admission_Offer_Domain_Exception extends RuntimeException
{
}

final class BVMGR_Admission_Offer_Transient_Transaction_Exception extends BVMGR_Admission_Offer_Domain_Exception
{
}

final class BVMGR_Admission_Offer_Value
{
	/** @var array<string,mixed> */
	private array $data;

	/** @param array<string,mixed> $data */
	private function __construct(array $data)
	{
		$this->data = $data;
	}

	/**
	 * @param array<string,mixed> $input
	 */
	public static function from_array(array $input, ?string $store_currency = null): self
	{
		$type = strtolower(trim((string) ($input['offer_type'] ?? '')));
		if (!in_array($type, array('complimentary', 'percent', 'fixed'), true)) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_offer_type');
		}

		$name = trim((string) ($input['name'] ?? ''));
		if ($name === '' || strlen($name) > 190) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_offer_name');
		}

		$status = strtolower(trim((string) ($input['status'] ?? 'draft')));
		if (!in_array($status, array('draft', 'active', 'paused', 'ended', 'revoked'), true)) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_offer_status');
		}

		$percent = self::nullable_integer($input, 'percent_basis_points');
		$fixed = self::nullable_integer($input, 'fixed_amount_minor');
		$currency = strtoupper(trim((string) ($input['currency'] ?? '')));
		$store_currency = strtoupper(trim((string) $store_currency));

		if ($type === 'complimentary') {
			if ($percent !== null || $fixed !== null || $currency !== '') {
				throw new BVMGR_Admission_Offer_Domain_Exception('complimentary_monetary_value_forbidden');
			}
		} elseif ($type === 'percent') {
			if ($percent === null || $percent < 1 || $percent > 9999 || $fixed !== null || $currency !== '') {
				throw new BVMGR_Admission_Offer_Domain_Exception('invalid_percent_value');
			}
		} else {
			if ($fixed === null || $fixed < 1 || $percent !== null || !preg_match('/^[A-Z]{3}$/', $currency)) {
				throw new BVMGR_Admission_Offer_Domain_Exception('invalid_fixed_value');
			}
			if (!preg_match('/^[A-Z]{3}$/', $store_currency) || $currency !== $store_currency) {
				throw new BVMGR_Admission_Offer_Domain_Exception('fixed_currency_must_match_store_currency');
			}
		}

		$public_id = trim((string) ($input['public_id'] ?? ''));
		if ($public_id === '') {
			$public_id = bvmgr_admission_offer_generate_public_id('ao');
		}
		if (!preg_match('/^ao_[a-f0-9]{32}$/', $public_id)) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_offer_public_id');
		}

		$max_qty = (int) ($input['max_qty_per_claim'] ?? 1);
		if ($max_qty < 1 || $max_qty > 65535) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_max_qty_per_claim');
		}

		$capacity = self::nullable_integer($input, 'capacity_total');
		if ($capacity !== null && $capacity < 1) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_capacity_total');
		}

		$ttl = (int) ($input['reservation_ttl_seconds'] ?? 1200);
		if ($ttl < 60 || $ttl > 2700) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_reservation_ttl');
		}

		$stacking = strtolower(trim((string) ($input['stacking_policy'] ?? 'exclusive')));
		if (!in_array($stacking, array('exclusive', 'allow_compatible'), true)) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_stacking_policy');
		}

		$identity_policy = $input['identity_policy'] ?? array();
		if (!is_array($identity_policy)) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_identity_policy');
		}
		$identity_policy_version = (int) ($input['identity_policy_version'] ?? 1);
		if ($identity_policy_version < 1) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_identity_policy_version');
		}

		return new self(array(
			'public_id' => $public_id,
			'name' => $name,
			'offer_type' => $type,
			'percent_basis_points' => $percent,
			'fixed_amount_minor' => $fixed,
			'currency' => $currency !== '' ? $currency : null,
			'status' => $status,
			'source_id' => isset($input['source_id']) && (int) $input['source_id'] > 0 ? (int) $input['source_id'] : null,
			'max_qty_per_claim' => $max_qty,
			'capacity_total' => $capacity,
			'reservation_ttl_seconds' => $ttl,
			'claim_expires_at' => self::nullable_datetime($input['claim_expires_at'] ?? null),
			'stacking_policy' => $stacking,
			'identity_policy_json' => bvmgr_admission_offer_canonical_json_encode($identity_policy),
			'identity_policy_version' => $identity_policy_version,
		));
	}

	/** @return array<string,mixed> */
	public function to_array(): array
	{
		return $this->data;
	}

	public function type(): string
	{
		return (string) $this->data['offer_type'];
	}

	public function validate_discounted_subtotal(int $eligible_subtotal_minor, int $discount_minor): bool
	{
		if ($this->type() === 'complimentary') {
			return false;
		}

		return $eligible_subtotal_minor > 0
			&& $discount_minor >= 0
			&& ($eligible_subtotal_minor - $discount_minor) >= 1;
	}

	/** @param array<string,mixed> $input */
	private static function nullable_integer(array $input, string $key): ?int
	{
		if (!array_key_exists($key, $input) || $input[$key] === null || $input[$key] === '') {
			return null;
		}
		if (is_bool($input[$key]) || filter_var($input[$key], FILTER_VALIDATE_INT) === false) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_integer_' . $key);
		}

		return (int) $input[$key];
	}

	private static function nullable_datetime($value): ?string
	{
		if ($value === null || $value === '') {
			return null;
		}
		$value = trim((string) $value);
		$date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
		if (!$date || $date->format('Y-m-d H:i:s') !== $value) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_datetime');
		}

		return $value;
	}
}

final class BVMGR_Admission_Offer_Eligibility_Value
{
	/** @var array<string,mixed> */
	private array $data;

	/** @param array<string,mixed> $data */
	private function __construct(array $data)
	{
		$this->data = $data;
	}

	/** @param array<string,mixed> $input */
	public static function from_array(array $input): self
	{
		$scope_type = strtolower(trim((string) ($input['scope_type'] ?? '')));
		$mode = strtolower(trim((string) ($input['mode'] ?? 'include')));
		if (!in_array($scope_type, array('event_plan', 'venue', 'date_window', 'season', 'any_event'), true)) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_eligibility_scope');
		}
		if (!in_array($mode, array('include', 'exclude'), true)) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_eligibility_mode');
		}

		$event_plan_id = isset($input['event_plan_id']) ? (int) $input['event_plan_id'] : 0;
		$venue_id = isset($input['venue_id']) ? (int) $input['venue_id'] : 0;
		$start_date = trim((string) ($input['start_date'] ?? ''));
		$end_date = trim((string) ($input['end_date'] ?? ''));
		$season_key = trim((string) ($input['season_key'] ?? ''));

		if ($scope_type === 'event_plan' && ($event_plan_id < 1 || $venue_id !== 0 || $start_date !== '' || $end_date !== '' || $season_key !== '')) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_event_plan_eligibility');
		}
		if ($scope_type === 'venue' && ($venue_id < 1 || $event_plan_id !== 0 || $start_date !== '' || $end_date !== '' || $season_key !== '')) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_venue_eligibility');
		}
		if ($scope_type === 'date_window') {
			if ($event_plan_id !== 0 || $venue_id !== 0 || $season_key !== '' || !self::valid_date($start_date) || !self::valid_date($end_date) || $start_date > $end_date) {
				throw new BVMGR_Admission_Offer_Domain_Exception('invalid_date_window_eligibility');
			}
		}
		if ($scope_type === 'season' && ($season_key === '' || strlen($season_key) > 120 || $event_plan_id !== 0 || $venue_id !== 0 || $start_date !== '' || $end_date !== '')) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_season_eligibility');
		}
		if ($scope_type === 'any_event' && ($event_plan_id !== 0 || $venue_id !== 0 || $start_date !== '' || $end_date !== '' || $season_key !== '')) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_any_event_eligibility');
		}

		$data = array(
			'scope_type' => $scope_type,
			'mode' => $mode,
			'event_plan_id' => $event_plan_id > 0 ? $event_plan_id : null,
			'venue_id' => $venue_id > 0 ? $venue_id : null,
			'start_date' => $start_date !== '' ? $start_date : null,
			'end_date' => $end_date !== '' ? $end_date : null,
			'season_key' => $season_key !== '' ? $season_key : null,
		);
		$data['eligibility_key'] = hash('sha256', bvmgr_admission_offer_canonical_json_encode($data));

		return new self($data);
	}

	/** @return array<string,mixed> */
	public function to_array(): array
	{
		return $this->data;
	}

	private static function valid_date(string $date): bool
	{
		$parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
		return $parsed !== false && $parsed->format('Y-m-d') === $date;
	}
}

if (!function_exists('bvmgr_admission_offer_generate_public_id')) {
	function bvmgr_admission_offer_generate_public_id(string $prefix): string
	{
		if (!in_array($prefix, array('ao', 'ac', 'ar', 'ax', 'af'), true)) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_public_id_prefix');
		}

		return $prefix . '_' . bin2hex(random_bytes(16));
	}
}

if (!function_exists('bvmgr_admission_offer_json_encode')) {
	function bvmgr_admission_offer_json_encode($value): string
	{
		$encoded = function_exists('wp_json_encode') ? wp_json_encode($value) : json_encode($value);
		if (!is_string($encoded)) {
			throw new BVMGR_Admission_Offer_Domain_Exception('json_encode_failed');
		}

		return $encoded;
	}
}

if (!function_exists('bvmgr_admission_offer_canonicalize')) {
	function bvmgr_admission_offer_canonicalize($value)
	{
		if (!is_array($value)) {
			return $value;
		}
		$is_list = array_keys($value) === range(0, count($value) - 1);
		if (!$is_list) {
			ksort($value, SORT_STRING);
		}
		foreach ($value as $key => $item) {
			$value[$key] = bvmgr_admission_offer_canonicalize($item);
		}
		return $value;
	}
}

if (!function_exists('bvmgr_admission_offer_canonical_json_encode')) {
	function bvmgr_admission_offer_canonical_json_encode($value): string
	{
		$value = bvmgr_admission_offer_canonicalize($value);
		$flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION;
		$encoded = function_exists('wp_json_encode') ? wp_json_encode($value, $flags) : json_encode($value, $flags);
		if (!is_string($encoded)) {
			throw new BVMGR_Admission_Offer_Domain_Exception('json_encode_failed');
		}
		return $encoded;
	}
}

if (!function_exists('bvmgr_admission_offer_nullable_datetime')) {
	function bvmgr_admission_offer_nullable_datetime($value): ?string
	{
		if ($value === null || $value === '') {
			return null;
		}
		$value = trim((string) $value);
		$date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
		if (!$date || $date->format('Y-m-d H:i:s') !== $value) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_datetime');
		}
		return $value;
	}
}

if (!function_exists('bvmgr_admission_offer_integer_value')) {
	function bvmgr_admission_offer_integer_value($value, string $error_code): int
	{
		if (is_bool($value) || filter_var($value, FILTER_VALIDATE_INT) === false) {
			throw new BVMGR_Admission_Offer_Domain_Exception($error_code);
		}

		return (int) $value;
	}
}

if (!function_exists('bvmgr_admission_offer_normalize_scope_key')) {
	function bvmgr_admission_offer_normalize_scope_key(?string $scope_key): string
	{
		$scope_key = strtolower(trim((string) $scope_key));
		if ($scope_key === '') {
			return 'offer';
		}
		if (strlen($scope_key) > 191 || !preg_match('/^[a-z0-9][a-z0-9._:-]*$/', $scope_key)) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_identity_scope_key');
		}

		return $scope_key;
	}
}

if (!function_exists('bvmgr_admission_offer_normalize_identity')) {
	function bvmgr_admission_offer_normalize_identity(string $type, string $value): string
	{
		$type = strtolower(trim($type));
		if (!in_array($type, array('email', 'phone', 'account', 'household_key'), true)) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_identity_type');
		}

		$value = trim($value);
		if ($type === 'email') {
			$value = strtolower($value);
			if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
				throw new BVMGR_Admission_Offer_Domain_Exception('invalid_identity_email');
			}
		} elseif ($type === 'phone') {
			$value = preg_replace('/\D+/', '', $value) ?? '';
			if (strlen($value) < 7 || strlen($value) > 15) {
				throw new BVMGR_Admission_Offer_Domain_Exception('invalid_identity_phone');
			}
		} elseif ($type === 'account') {
			if (!preg_match('/^[1-9][0-9]{0,19}$/', $value)) {
				throw new BVMGR_Admission_Offer_Domain_Exception('invalid_identity_account');
			}
		} else {
			// household_key is accepted only when explicitly supplied. BVM never
			// derives it from names, postal addresses, or other claimant fields.
			if (strlen($value) < 8 || strlen($value) > 190) {
				throw new BVMGR_Admission_Offer_Domain_Exception('invalid_explicit_household_key');
			}
		}

		return $value;
	}
}

if (!function_exists('bvmgr_admission_offer_hash_secret')) {
	function bvmgr_admission_offer_hash_secret(string $secret): string
	{
		if (strlen($secret) < 32) {
			throw new BVMGR_Admission_Offer_Domain_Exception('secret_too_short');
		}
		return hash('sha256', "bvmgr-claim-access-v1\0" . $secret);
	}
}

if (!function_exists('bvmgr_admission_offer_hmac')) {
	function bvmgr_admission_offer_hmac(string $value, string $key): string
	{
		if (strlen($key) < 32) {
			throw new BVMGR_Admission_Offer_Domain_Exception('identity_hash_key_invalid');
		}
		return hash_hmac('sha256', $value, $key);
	}
}

if (!function_exists('bvmgr_admission_offer_identity_hash')) {
	function bvmgr_admission_offer_identity_hash(string $scope_key, string $type, string $raw_value, string $key): string
	{
		$scope_key = bvmgr_admission_offer_normalize_scope_key($scope_key);
		$type = strtolower(trim($type));
		$normalized = bvmgr_admission_offer_normalize_identity($type, $raw_value);

		return bvmgr_admission_offer_hmac("bvmgr-identity-v1\0" . $scope_key . "\0" . $type . "\0" . $normalized, $key);
	}
}

if (!function_exists('bvmgr_admission_offer_idempotency_hash')) {
	function bvmgr_admission_offer_idempotency_hash(string $key): string
	{
		return hash('sha256', "bvmgr-reservation-idempotency-v1\0" . $key);
	}
}

if (!function_exists('bvmgr_admission_offer_db_error_is_transient')) {
	function bvmgr_admission_offer_db_error_is_transient($db): bool
	{
		$errno = isset($db->last_errno) ? (int) $db->last_errno : 0;
		$error = isset($db->last_error) ? strtolower((string) $db->last_error) : '';
		return in_array($errno, array(1205, 1213), true)
			|| str_contains($error, 'deadlock')
			|| str_contains($error, 'lock wait timeout');
	}
}

if (!function_exists('bvmgr_admission_offer_state_transition_allowed')) {
	function bvmgr_admission_offer_state_transition_allowed(string $entity_type, string $from, string $to): bool
	{
		$maps = array(
			'offer' => array(
				'draft' => array('active', 'revoked'),
				'active' => array('paused', 'ended', 'revoked'),
				'paused' => array('active', 'ended', 'revoked'),
				'ended' => array(),
				'revoked' => array(),
			),
			'claim' => array(
				'claimed' => array('reserved', 'fulfilled', 'expired', 'canceled', 'revoked'),
				'reserved' => array('checkout_started', 'fulfilled', 'expired', 'canceled', 'revoked'),
				'checkout_started' => array('payment_pending', 'abandoned', 'failed', 'canceled'),
				'payment_pending' => array('paid', 'failed', 'canceled'),
				'paid' => array('fulfilled', 'refunded', 'exception'),
				'fulfilled' => array('partially_used', 'used', 'revoked', 'refunded'),
				'partially_used' => array('used', 'revoked', 'refunded'),
				'used' => array('refunded'),
				'expired' => array(), 'canceled' => array(), 'revoked' => array(),
				'abandoned' => array(), 'failed' => array(), 'refunded' => array(), 'exception' => array(),
			),
			'reservation' => array(
				'held' => array('order_attached', 'consumed', 'released', 'expired', 'exception'),
				'order_attached' => array('consumed', 'released', 'exception'),
				'consumed' => array('exception'),
				'released' => array(), 'expired' => array(), 'exception' => array(),
			),
			'checkout' => array(
				'checkout_started' => array('payment_pending', 'paid', 'abandoned', 'failed', 'canceled'),
				'payment_pending' => array('paid', 'failed', 'canceled'),
				'paid' => array('refunded', 'exception'),
				'abandoned' => array(), 'failed' => array(), 'canceled' => array(), 'refunded' => array(), 'exception' => array(),
			),
			'fulfillment' => array(
				'pending' => array('fulfilled', 'failed', 'exception'),
				'fulfilled' => array('partially_used', 'used', 'revoked', 'exception'),
				'partially_used' => array('used', 'revoked', 'exception'),
				'used' => array(), 'revoked' => array(), 'failed' => array(), 'exception' => array(),
			),
		);

		return isset($maps[$entity_type][$from]) && in_array($to, $maps[$entity_type][$from], true);
	}
}
