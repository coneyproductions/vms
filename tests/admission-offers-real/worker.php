<?php
declare(strict_types=1);

[$script, $wordpress_root, $encoded] = $argv + array(null, null, null);
if (!$wordpress_root || !$encoded) {
	fwrite(STDERR, "worker arguments missing\n");
	exit(2);
}
$payload = json_decode((string) base64_decode($encoded, true), true, 32, JSON_THROW_ON_ERROR);
require rtrim($wordpress_root, '/') . '/wp-load.php';

if (($payload['mode'] ?? '') === 'paid_claim') {
	global $wpdb;
	if (!empty($payload['lock_wait_timeout'])) {
		$wpdb->query('SET SESSION innodb_lock_wait_timeout = ' . (int) $payload['lock_wait_timeout']);
	}
	$connection_id = (int) $wpdb->get_var('SELECT CONNECTION_ID()');
	$interrupt = static function (string $point) use ($payload): void {
		if ($point !== 'after_offer_lock' || empty($payload['marker'])) return;
		file_put_contents((string) $payload['marker'], 'locked');
		$deadline = microtime(true) + 15;
		while (!is_file((string) $payload['release'])) {
			if (microtime(true) > $deadline) throw new RuntimeException('release_timeout');
			usleep(20000);
		}
	};
	$service = new BVMGR_Admission_Offer_Paid_Claim_Service(
		$wpdb,
		static fn(): string => (string) $payload['now'],
		static function (): void {},
		static fn(): bool => true,
		$interrupt,
		'USD'
	);
	try {
		$result = $service->claim((array) $payload['request']);
		echo json_encode(array(
			'ok' => true,
			'connection_id' => $connection_id,
			'claim_id' => (int) $result['claim']['id'],
			'reservation_id' => (int) $result['reservation']['id'],
		), JSON_THROW_ON_ERROR);
	} catch (Throwable $error) {
		echo json_encode(array('ok' => false, 'connection_id' => $connection_id, 'error_class' => get_class($error), 'error' => $error->getMessage()), JSON_THROW_ON_ERROR);
	}
	exit(0);
}

if (($payload['mode'] ?? '') === 'woocommerce_checkout') {
	global $wpdb;
	wp_set_current_user(0);
	$session = new WC_Session_Handler();
	$session->init_session_cookie();
	$cart = new WC_Cart();
	WC()->session = $session;
	WC()->cart = $cart;
	$connection_id = (int) $wpdb->get_var('SELECT CONNECTION_ID()');
	$interrupt = static function (string $point) use ($payload): void {
		if ($point !== 'after_claim_lock' || empty($payload['marker'])) return;
		file_put_contents((string) $payload['marker'], 'locked');
		$deadline = microtime(true) + 15;
		while (!is_file((string) $payload['release'])) {
			if (microtime(true) > $deadline) throw new RuntimeException('release_timeout');
			usleep(20000);
		}
	};
	$service = new BVMGR_Admission_Offer_Woo_Checkout_Service(
		$wpdb,
		static fn(): string => (string) $payload['now'],
		static fn() => $session,
		static fn() => $cart,
		null,
		$interrupt
	);
	try {
		$result = $service->activate((array) $payload['request']);
		echo json_encode(array(
			'ok' => true,
			'connection_id' => $connection_id,
			'session_id' => (string) $session->get_customer_id(),
			'checkout_id' => (int) $result['checkout']['id'],
		), JSON_THROW_ON_ERROR);
	} catch (Throwable $error) {
		echo json_encode(array(
			'ok' => false,
			'connection_id' => $connection_id,
			'session_id' => (string) $session->get_customer_id(),
			'error_class' => get_class($error),
			'error' => $error->getMessage(),
		), JSON_THROW_ON_ERROR);
	}
	exit(0);
}

final class BVMGR_Admission_Offer_Real_Pause_Store implements BVMGR_Admission_Offer_Capacity_Store_Interface
{
	private BVMGR_Admission_Offer_WPDB_Capacity_Store $inner;
	/** @param array<string,mixed> $payload */
	public function __construct(private array $payload)
	{
		$this->inner = new BVMGR_Admission_Offer_WPDB_Capacity_Store();
	}
	public function begin(): void { $this->inner->begin(); }
	public function commit(): void { $this->inner->commit(); }
	public function rollback(): void { $this->inner->rollback(); }
	public function lock_offer(int $offer_id): ?array
	{
		$row = $this->inner->lock_offer($offer_id);
		if (!empty($this->payload['marker'])) {
			file_put_contents((string) $this->payload['marker'], 'locked');
			$deadline = microtime(true) + 15;
			while (!is_file((string) $this->payload['release'])) {
				if (microtime(true) > $deadline) throw new RuntimeException('release_timeout');
				usleep(20000);
			}
		}
		return $row;
	}
	public function lock_claim(int $claim_id): ?array { return $this->inner->lock_claim($claim_id); }
	public function expire_stale(int $offer_id, string $now): int { return $this->inner->expire_stale($offer_id, $now); }
	public function find_by_idempotency(int $offer_id, string $hash): ?array { return $this->inner->find_by_idempotency($offer_id, $hash); }
	public function capacity_used(int $offer_id, string $now): int { return $this->inner->capacity_used($offer_id, $now); }
	public function insert_hold(array $reservation): array
	{
		$row = $this->inner->insert_hold($reservation);
		if (!empty($this->payload['fail_after_insert'])) {
			throw new BVMGR_Admission_Offer_Domain_Exception('injected_after_insert');
		}
		return $row;
	}
	public function renew_hold(int $id, string $expires_at, string $now): array { return $this->inner->renew_hold($id, $expires_at, $now); }
}

global $wpdb;
if (!empty($payload['lock_wait_timeout'])) {
	$wpdb->query('SET SESSION innodb_lock_wait_timeout = ' . (int) $payload['lock_wait_timeout']);
}
$connection_id = (int) $wpdb->get_var('SELECT CONNECTION_ID()');
$store = new BVMGR_Admission_Offer_Real_Pause_Store($payload);
$service = new BVMGR_Admission_Offer_Capacity_Service($store, static function (): void {}, (int) ($payload['max_attempts'] ?? 3));
try {
	$row = $service->acquire(
		(int) $payload['offer_id'],
		(int) $payload['claim_id'],
		(int) $payload['event_plan_id'],
		(int) $payload['quantity'],
		(string) $payload['idempotency_key'],
		(string) $payload['now']
	);
	echo json_encode(array('ok' => true, 'connection_id' => $connection_id, 'reservation_id' => (int) $row['id']), JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
	echo json_encode(array('ok' => false, 'connection_id' => $connection_id, 'error_class' => get_class($error), 'error' => $error->getMessage()), JSON_THROW_ON_ERROR);
}
