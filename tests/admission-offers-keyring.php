<?php
declare(strict_types=1);

if (!defined('ABSPATH')) define('ABSPATH', dirname(__DIR__) . '/');

$GLOBALS['bvmgr_keyring_options'] = array();
function get_option(string $key, $default = false) { return $GLOBALS['bvmgr_keyring_options'][$key] ?? $default; }
function add_option(string $key, $value, string $deprecated = '', $autoload = true): bool
{
	if (array_key_exists($key, $GLOBALS['bvmgr_keyring_options'])) return false;
	$GLOBALS['bvmgr_keyring_options'][$key] = $value;
	return true;
}
function update_option(string $key, $value, $autoload = null): bool
{
	$changed = !array_key_exists($key, $GLOBALS['bvmgr_keyring_options']) || $GLOBALS['bvmgr_keyring_options'][$key] !== $value;
	$GLOBALS['bvmgr_keyring_options'][$key] = $value;
	return $changed;
}

final class BVMGR_Admission_Offer_Keyring_Test_DB
{
	public string $prefix = 'wp_';
	public function __construct(public int $identity_count) {}
	public function get_var(string $sql): int { return $this->identity_count; }
}

require_once dirname(__DIR__) . '/includes/modules/admission-offers/domain.php';
require_once dirname(__DIR__) . '/includes/modules/admission-offers/schema.php';
require_once dirname(__DIR__) . '/includes/modules/admission-offers/keys.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
	$assertions++;
	if (!$condition) throw new RuntimeException($message);
};
$rejects = static function (callable $callback, string $code) use ($assert): void {
	try { $callback(); } catch (BVMGR_Admission_Offer_Domain_Exception $error) {
		$assert($error->getMessage() === $code, 'Unexpected keyring rejection: ' . $error->getMessage());
		return;
	}
	throw new RuntimeException('Expected keyring rejection: ' . $code);
};

$GLOBALS['wpdb'] = new BVMGR_Admission_Offer_Keyring_Test_DB(0);
$first = BVMGR_Admission_Offer_Identity_Keyring::load_or_create($GLOBALS['wpdb']);
$assert($first->active_version() === 1, 'Fresh installation must start at identity key version 1.');
$assert(strlen($first->keys()[1]) === 32, 'Fresh identity key must contain 256 random bits.');
$reloaded = BVMGR_Admission_Offer_Identity_Keyring::load_or_create($GLOBALS['wpdb']);
$assert(hash_equals($first->keys()[1], $reloaded->keys()[1]), 'Stored keyring must be durable across requests.');
$rotated = BVMGR_Admission_Offer_Identity_Keyring::rotate();
$assert($rotated->active_version() === 2 && count($rotated->keys()) === 2, 'Rotation must append and activate a new version.');
$assert(hash_equals($first->keys()[1], $rotated->keys()[1]), 'Rotation must retain historical key material.');
$assert(!hash_equals($rotated->keys()[1], $rotated->keys()[2]), 'Rotation must generate independent key material.');

$saved = $GLOBALS['bvmgr_keyring_options'];
$GLOBALS['bvmgr_keyring_options']['vms_admission_offers_identity_keyring']['keys']['1'] = 'corrupt';
$rejects(static fn() => BVMGR_Admission_Offer_Identity_Keyring::load_or_create($GLOBALS['wpdb']), 'identity_keyring_corrupt');
$GLOBALS['bvmgr_keyring_options'] = array();
$existing_db = new BVMGR_Admission_Offer_Keyring_Test_DB(1);
$rejects(static fn() => BVMGR_Admission_Offer_Identity_Keyring::load_or_create($existing_db), 'identity_keyring_missing');
$GLOBALS['bvmgr_keyring_options'] = $saved;

echo "Admission Offers identity keyring: PASS ({$assertions} assertions)\n";
