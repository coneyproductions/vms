<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__) . '/');
}

function wp_json_encode($value): string { return (string) json_encode($value); }

require_once dirname(__DIR__) . '/includes/modules/admission-offers/domain.php';
require_once dirname(__DIR__) . '/includes/modules/admission-offers/capacity.php';

class BVMGR_Admission_Offer_Test_Capacity_Store implements BVMGR_Admission_Offer_Capacity_Store_Interface
{
	/** @var array<int,array<string,mixed>> */
	public array $offers = array();
	/** @var array<int,array<string,mixed>> */
	public array $claims = array();
	/** @var array<int,array<string,mixed>> */
	public array $reservations = array();
	public int $begins = 0;
	public int $commits = 0;
	public int $rollbacks = 0;
	public int $offer_locks = 0;
	public int $claim_locks = 0;
	public int $next_id = 1;

	public function begin(): void { $this->begins++; }
	public function commit(): void { $this->commits++; }
	public function rollback(): void { $this->rollbacks++; }

	public function lock_offer(int $offer_id): ?array
	{
		$this->offer_locks++;
		return $this->offers[$offer_id] ?? null;
	}

	public function lock_claim(int $claim_id): ?array
	{
		$this->claim_locks++;
		return $this->claims[$claim_id] ?? null;
	}

	public function expire_stale(int $offer_id, string $now): int
	{
		$count = 0;
		foreach ($this->reservations as &$reservation) {
			if ((int) $reservation['offer_id'] === $offer_id && $reservation['state'] === 'held' && $reservation['expires_at'] <= $now) {
				$reservation['state'] = 'expired';
				$reservation['expired_at'] = $now;
				$count++;
			}
		}
		unset($reservation);
		return $count;
	}

	public function find_by_idempotency(int $offer_id, string $idempotency_key_hash): ?array
	{
		foreach ($this->reservations as $reservation) {
			if ((int) $reservation['offer_id'] === $offer_id && $reservation['idempotency_key_hash'] === $idempotency_key_hash) {
				return $reservation;
			}
		}
		return null;
	}

	public function capacity_used(int $offer_id, string $now): int
	{
		$used = 0;
		foreach ($this->reservations as $reservation) {
			if ((int) $reservation['offer_id'] !== $offer_id) continue;
			if (($reservation['state'] === 'held' && $reservation['expires_at'] > $now)
				|| in_array($reservation['state'], array('order_attached', 'consumed'), true)) {
				$used += (int) $reservation['quantity'];
			}
		}
		return $used;
	}

	public function insert_hold(array $reservation): array
	{
		foreach ($this->reservations as $existing) {
			if ((int) $existing['offer_id'] === (int) $reservation['offer_id'] && $existing['idempotency_key_hash'] === $reservation['idempotency_key_hash']) {
				throw new BVMGR_Admission_Offer_Domain_Exception('reservation_insert_failed');
			}
		}
		$reservation['id'] = $this->next_id++;
		$this->reservations[$reservation['id']] = $reservation;
		return $reservation;
	}

	public function renew_hold(int $reservation_id, string $expires_at, string $now): array
	{
		if (!isset($this->reservations[$reservation_id]) || $this->reservations[$reservation_id]['state'] !== 'held') {
			throw new BVMGR_Admission_Offer_Domain_Exception('reservation_renewal_failed');
		}
		$this->reservations[$reservation_id]['expires_at'] = $expires_at;
		$this->reservations[$reservation_id]['updated_at'] = $now;
		$this->reservations[$reservation_id]['state_version'] = (int) ($this->reservations[$reservation_id]['state_version'] ?? 1) + 1;
		return $this->reservations[$reservation_id];
	}
}

final class BVMGR_Admission_Offer_Retry_Capacity_Store extends BVMGR_Admission_Offer_Test_Capacity_Store
{
	public int $transient_failures = 1;
	public function lock_offer(int $offer_id): ?array
	{
		if ($this->transient_failures > 0) {
			$this->transient_failures--;
			throw new BVMGR_Admission_Offer_Transient_Transaction_Exception('capacity_lock_deadlock');
		}
		return parent::lock_offer($offer_id);
	}
}

final class BVMGR_Admission_Offer_Concurrent_State
{
	/** @var array<int,array<string,mixed>> */
	public array $offers = array();
	/** @var array<int,array<string,mixed>> */
	public array $claims = array();
	/** @var array<int,array<string,mixed>> */
	public array $reservations = array();
	public ?string $offer_lock_owner = null;
	public int $next_id = 1;
}

final class BVMGR_Admission_Offer_Concurrent_Store implements BVMGR_Admission_Offer_Capacity_Store_Interface
{
	private BVMGR_Admission_Offer_Concurrent_State $state;
	private string $session;
	private bool $yield_after_first_lock;

	public function __construct(BVMGR_Admission_Offer_Concurrent_State $state, string $session, bool $yield_after_first_lock = false)
	{
		$this->state = $state;
		$this->session = $session;
		$this->yield_after_first_lock = $yield_after_first_lock;
	}

	public function begin(): void {}

	public function commit(): void
	{
		if ($this->state->offer_lock_owner === $this->session) {
			$this->state->offer_lock_owner = null;
		}
	}

	public function rollback(): void
	{
		if ($this->state->offer_lock_owner === $this->session) {
			$this->state->offer_lock_owner = null;
		}
	}

	public function lock_offer(int $offer_id): ?array
	{
		while ($this->state->offer_lock_owner !== null && $this->state->offer_lock_owner !== $this->session) {
			Fiber::suspend('waiting_for_offer_lock');
		}
		$this->state->offer_lock_owner = $this->session;
		if ($this->yield_after_first_lock) {
			$this->yield_after_first_lock = false;
			Fiber::suspend('offer_lock_acquired');
		}
		return $this->state->offers[$offer_id] ?? null;
	}

	public function lock_claim(int $claim_id): ?array
	{
		return $this->state->claims[$claim_id] ?? null;
	}

	public function expire_stale(int $offer_id, string $now): int
	{
		$count = 0;
		foreach ($this->state->reservations as &$reservation) {
			if ((int) $reservation['offer_id'] === $offer_id && $reservation['state'] === 'held' && $reservation['expires_at'] <= $now) {
				$reservation['state'] = 'expired';
				$count++;
			}
		}
		unset($reservation);
		return $count;
	}

	public function find_by_idempotency(int $offer_id, string $idempotency_key_hash): ?array
	{
		foreach ($this->state->reservations as $reservation) {
			if ((int) $reservation['offer_id'] === $offer_id && $reservation['idempotency_key_hash'] === $idempotency_key_hash) {
				return $reservation;
			}
		}
		return null;
	}

	public function capacity_used(int $offer_id, string $now): int
	{
		$used = 0;
		foreach ($this->state->reservations as $reservation) {
			if ((int) $reservation['offer_id'] === $offer_id
				&& (($reservation['state'] === 'held' && $reservation['expires_at'] > $now)
					|| in_array($reservation['state'], array('order_attached', 'consumed'), true))) {
				$used += (int) $reservation['quantity'];
			}
		}
		return $used;
	}

	public function insert_hold(array $reservation): array
	{
		$reservation['id'] = $this->state->next_id++;
		$this->state->reservations[$reservation['id']] = $reservation;
		return $reservation;
	}

	public function renew_hold(int $reservation_id, string $expires_at, string $now): array
	{
		$this->state->reservations[$reservation_id]['expires_at'] = $expires_at;
		$this->state->reservations[$reservation_id]['updated_at'] = $now;
		return $this->state->reservations[$reservation_id];
	}
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
	$assertions++;
	if (!$condition) throw new RuntimeException($message);
};
$rejects = static function (callable $callback, string $code) use ($assert): void {
	try { $callback(); } catch (BVMGR_Admission_Offer_Domain_Exception $error) {
		$assert($error->getMessage() === $code, 'Unexpected rejection: ' . $error->getMessage() . ', expected ' . $code);
		return;
	}
	throw new RuntimeException('Expected rejection: ' . $code);
};

$now = '2026-09-28 12:00:00';
$store = new BVMGR_Admission_Offer_Test_Capacity_Store();
$store->offers[10] = array(
	'id' => 10,
	'status' => 'active',
	'max_qty_per_claim' => 2,
	'capacity_total' => 2,
	'reservation_ttl_seconds' => 1200,
	'claim_expires_at' => null,
);
$store->claims[101] = array('id' => 101, 'offer_id' => 10, 'event_plan_id' => 501, 'quantity' => 2, 'status' => 'claimed', 'expires_at' => null);
$store->claims[102] = array('id' => 102, 'offer_id' => 10, 'event_plan_id' => 501, 'quantity' => 1, 'status' => 'claimed', 'expires_at' => null);
$service = new BVMGR_Admission_Offer_Capacity_Service($store, static function (): void {});

$first = $service->acquire(10, 101, 501, 2, 'first-concurrent-attempt', $now);
$assert($first['state'] === 'held' && $first['quantity'] === 2, 'First contender must acquire the remaining global capacity.');
$rejects(static fn() => $service->acquire(10, 102, 501, 1, 'second-concurrent-attempt', $now), 'offer_capacity_exhausted');
$assert(count($store->reservations) === 1, 'Serialized contenders must never oversubscribe the Offer.');
$assert($store->begins === 2 && $store->offer_locks === 2, 'Every contender must begin a transaction and lock the Offer row.');
$assert($store->commits === 1 && $store->rollbacks === 1, 'Successful capacity acquisition commits and rejected contention rolls back.');

$renewed = $service->acquire(10, 101, 501, 2, 'first-concurrent-attempt', '2026-09-28 12:05:00');
$assert((int) $renewed['id'] === (int) $first['id'], 'Idempotent retry must renew the existing reservation, not insert another.');
$assert($renewed['expires_at'] === '2026-09-28 12:25:00', 'Renewal must extend TTL from current activity.');
$assert(count($store->reservations) === 1, 'Renewal must preserve one idempotent reservation row.');
$rejects(static fn() => $service->acquire(10, 102, 501, 1, 'first-concurrent-attempt', '2026-09-28 12:06:00'), 'idempotency_key_conflict');

$stale_store = new BVMGR_Admission_Offer_Test_Capacity_Store();
$stale_store->offers[20] = array(
	'id' => 20,
	'status' => 'active',
	'max_qty_per_claim' => 2,
	'capacity_total' => 2,
	'reservation_ttl_seconds' => 1200,
	'claim_expires_at' => null,
);
$stale_store->claims[201] = array('id' => 201, 'offer_id' => 20, 'event_plan_id' => 601, 'quantity' => 2, 'status' => 'claimed', 'expires_at' => null);
$stale_store->reservations[1] = array(
	'id' => 1,
	'public_id' => 'ar_' . str_repeat('1', 32),
	'offer_id' => 20,
	'claim_id' => 200,
	'event_plan_id' => 601,
	'quantity' => 2,
	'state' => 'held',
	'idempotency_key_hash' => bvmgr_admission_offer_idempotency_hash('old-stale-attempt'),
	'expires_at' => '2026-09-28 11:59:59',
);
$stale_store->next_id = 2;
$stale_service = new BVMGR_Admission_Offer_Capacity_Service($stale_store, static function (): void {});
$fresh = $stale_service->acquire(20, 201, 601, 2, 'fresh-after-expiry', $now);
$assert($stale_store->reservations[1]['state'] === 'expired', 'Stale held reservation must lazily expire under the Offer lock.');
$assert($fresh['state'] === 'held' && $fresh['id'] === 2, 'Lazily released capacity must be atomically reusable.');
$assert($stale_store->capacity_used(20, $now) === 2, 'Expired hold must not count against global capacity.');

$concurrent_state = new BVMGR_Admission_Offer_Concurrent_State();
$concurrent_state->offers[30] = array(
	'id' => 30,
	'status' => 'active',
	'max_qty_per_claim' => 2,
	'capacity_total' => 2,
	'reservation_ttl_seconds' => 1200,
	'claim_expires_at' => null,
);
$concurrent_state->claims[301] = array('id' => 301, 'offer_id' => 30, 'event_plan_id' => 701, 'quantity' => 2, 'status' => 'claimed', 'expires_at' => null);
$concurrent_state->claims[302] = array('id' => 302, 'offer_id' => 30, 'event_plan_id' => 701, 'quantity' => 1, 'status' => 'claimed', 'expires_at' => null);
$concurrent_a = new BVMGR_Admission_Offer_Capacity_Service(new BVMGR_Admission_Offer_Concurrent_Store($concurrent_state, 'A', true), static function (): void {});
$concurrent_b = new BVMGR_Admission_Offer_Capacity_Service(new BVMGR_Admission_Offer_Concurrent_Store($concurrent_state, 'B'), static function (): void {});
$fiber_a = new Fiber(static fn() => $concurrent_a->acquire(30, 301, 701, 2, 'fiber-capacity-a', $now));
$fiber_b = new Fiber(static function () use ($concurrent_b, $now): string {
	try {
		$concurrent_b->acquire(30, 302, 701, 1, 'fiber-capacity-b', $now);
		return 'unexpected_success';
	} catch (BVMGR_Admission_Offer_Domain_Exception $error) {
		return $error->getMessage();
	}
});
$assert($fiber_a->start() === 'offer_lock_acquired', 'First concurrent request must pause while holding the Offer lock.');
$assert($fiber_b->start() === 'waiting_for_offer_lock', 'Second concurrent request must wait for the same Offer lock.');
$fiber_a->resume();
$assert($fiber_a->isTerminated(), 'First concurrent request must commit before the waiter continues.');
$fiber_b->resume();
$assert($fiber_b->isTerminated(), 'Second concurrent request must finish after lock release.');
$assert($fiber_b->getReturn() === 'offer_capacity_exhausted', 'Waiting contender must re-read committed capacity and fail closed.');
$assert(count($concurrent_state->reservations) === 1 && (int) reset($concurrent_state->reservations)['quantity'] === 2, 'True interleaving must not oversubscribe capacity.');

$retry_store = new BVMGR_Admission_Offer_Retry_Capacity_Store();
$retry_store->offers[40] = array('id' => 40, 'status' => 'active', 'max_qty_per_claim' => 1, 'capacity_total' => 1, 'reservation_ttl_seconds' => 1200, 'claim_expires_at' => null);
$retry_store->claims[401] = array('id' => 401, 'offer_id' => 40, 'event_plan_id' => 801, 'quantity' => 1, 'status' => 'claimed', 'expires_at' => null);
$retry_delays = 0;
$retry_service = new BVMGR_Admission_Offer_Capacity_Service($retry_store, static function () use (&$retry_delays): void { $retry_delays++; }, 3);
$retried = $retry_service->acquire(40, 401, 801, 1, 'deadlock-retry-key', $now);
$assert($retried['state'] === 'held' && count($retry_store->reservations) === 1, 'Transient retry must eventually create exactly one idempotent reservation.');
$assert($retry_store->begins === 2 && $retry_store->rollbacks === 1 && $retry_store->commits === 1 && $retry_delays === 1, 'Deadlock retry must be bounded and roll back the failed attempt.');

$source = (string) file_get_contents(dirname(__DIR__) . '/includes/modules/admission-offers/capacity.php');
foreach (array('START TRANSACTION', 'FOR UPDATE', 'COMMIT', 'ROLLBACK') as $transaction_token) {
	$assert(str_contains($source, $transaction_token), 'WPDB capacity store must contain ' . $transaction_token . '.');
}
$assert(!str_contains($source, 'set_transient('), 'Capacity must never rely on transients.');

echo "Admission Offers capacity: PASS ({$assertions} assertions)\n";
