<?php
defined('ABSPATH') || exit;

interface BVMGR_Admission_Offer_Identity_Keyring_Interface
{
	public function active_version(): int;

	/** @return array<int,string> Raw binary keys indexed by positive version. */
	public function keys(): array;
}

final class BVMGR_Admission_Offer_Identity_Keyring implements BVMGR_Admission_Offer_Identity_Keyring_Interface
{
	private const OPTION_KEY = 'vms_admission_offers_identity_keyring';

	/** @var array<int,string> */
	private array $keys;
	private int $active_version;

	/** @param array<int,string> $keys */
	public function __construct(array $keys, int $active_version)
	{
		if ($active_version < 1 || $active_version > 65535 || !isset($keys[$active_version])) {
			throw new BVMGR_Admission_Offer_Domain_Exception('identity_keyring_corrupt');
		}
		foreach ($keys as $version => $key) {
			if ((int) $version < 1 || (int) $version > 65535 || !is_string($key) || strlen($key) !== 32) {
				throw new BVMGR_Admission_Offer_Domain_Exception('identity_keyring_corrupt');
			}
		}
		ksort($keys, SORT_NUMERIC);
		$this->keys = $keys;
		$this->active_version = $active_version;
	}

	public function active_version(): int
	{
		return $this->active_version;
	}

	public function keys(): array
	{
		return $this->keys;
	}

	public static function load_or_create($db = null): self
	{
		$stored = get_option(self::OPTION_KEY, null);
		if ($stored !== null && $stored !== false) {
			return self::from_option($stored);
		}
		if ($db === null) {
			global $wpdb;
			$db = $wpdb;
		}
		$table = bvmgr_admission_offers_table('identities');
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Missing key material must fail closed if enforcement rows already exist.
		$count = (int) $db->get_var("SELECT COUNT(*) FROM {$table}");
		if ($count > 0) {
			throw new BVMGR_Admission_Offer_Domain_Exception('identity_keyring_missing');
		}
		$option = array(
			'format_version' => 1,
			'active_version' => 1,
			'keys' => array('1' => base64_encode(random_bytes(32))),
		);
		if (!add_option(self::OPTION_KEY, $option, '', false)) {
			$option = get_option(self::OPTION_KEY, null);
		}
		return self::from_option($option);
	}

	public static function rotate(): self
	{
		$current = self::from_option(get_option(self::OPTION_KEY, null));
		$keys = $current->keys();
		$version = max(array_keys($keys)) + 1;
		if ($version > 65535) {
			throw new BVMGR_Admission_Offer_Domain_Exception('identity_key_version_exhausted');
		}
		$encoded = array();
		foreach ($keys as $key_version => $key) {
			$encoded[(string) $key_version] = base64_encode($key);
		}
		$encoded[(string) $version] = base64_encode(random_bytes(32));
		$option = array('format_version' => 1, 'active_version' => $version, 'keys' => $encoded);
		if (!update_option(self::OPTION_KEY, $option, false)) {
			$reloaded = get_option(self::OPTION_KEY, null);
			if ($reloaded !== $option) {
				throw new BVMGR_Admission_Offer_Domain_Exception('identity_keyring_rotation_failed');
			}
		}
		return self::from_option($option);
	}

	private static function from_option($option): self
	{
		if (!is_array($option) || ($option['format_version'] ?? null) !== 1 || !is_array($option['keys'] ?? null)) {
			throw new BVMGR_Admission_Offer_Domain_Exception('identity_keyring_corrupt');
		}
		$keys = array();
		foreach ($option['keys'] as $version => $encoded) {
			if (!is_string($encoded)) {
				throw new BVMGR_Admission_Offer_Domain_Exception('identity_keyring_corrupt');
			}
			$key = base64_decode($encoded, true);
			if (!is_string($key) || strlen($key) !== 32 || !ctype_digit((string) $version)) {
				throw new BVMGR_Admission_Offer_Domain_Exception('identity_keyring_corrupt');
			}
			$keys[(int) $version] = $key;
		}
		return new self($keys, (int) ($option['active_version'] ?? 0));
	}
}
