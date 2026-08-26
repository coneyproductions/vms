<?php
declare(strict_types=1);

/**
 * Reference state machine for later prefix persistence batches.
 *
 * B1 does not load this in production and does not migrate data. Later batches
 * can move the proven contract into runtime when their persistence scope opens.
 */
final class BVMGR_Prefix_Migration_State
{
	public const VERSION_OPTION = 'bvmgr_prefix_migration_version';
	public const JOURNAL_OPTION = 'bvmgr_prefix_migration_state';

	/**
	 * @param array<string, callable> $steps
	 * @param callable(int,string,mixed):mixed $readOption
	 * @param callable(int,string,mixed):bool $writeOption
	 */
	public static function runSite(
		int $siteId,
		int $targetVersion,
		array $steps,
		callable $readOption,
		callable $writeOption
	): array {
		if ($siteId <= 0 || $targetVersion <= 0) {
			throw new InvalidArgumentException('Site ID and target version must be positive.');
		}
		$currentVersion = (int) $readOption($siteId, self::VERSION_OPTION, 0);
		$journal = self::normalizeJournal(
			$readOption($siteId, self::JOURNAL_OPTION, array()),
			$targetVersion
		);
		if ($currentVersion > $targetVersion) {
			$journal['status'] = 'complete';
			return array('status' => 'complete', 'already_complete' => true, 'journal' => $journal);
		}
		if ($currentVersion >= $targetVersion && $journal['status'] === 'complete') {
			return array('status' => 'complete', 'already_complete' => true, 'journal' => $journal);
		}

		foreach ($steps as $stepId => $step) {
			$stepId = (string) $stepId;
			if (in_array($stepId, $journal['completed_steps'], true)) {
				continue;
			}
			if (!is_callable($step)) {
				throw new InvalidArgumentException('Migration step is not callable: ' . $stepId);
			}

			$journal['status'] = 'running';
			$journal['current_step'] = $stepId;
			$journal['attempts'][$stepId] = ((int) ($journal['attempts'][$stepId] ?? 0)) + 1;
			self::mustWrite($writeOption, $siteId, self::JOURNAL_OPTION, $journal);

			try {
				$result = $step($siteId, $journal);
			} catch (Throwable $throwable) {
				$journal['status'] = 'interrupted';
				$journal['last_error_class'] = get_class($throwable);
				self::mustWrite($writeOption, $siteId, self::JOURNAL_OPTION, $journal);
				return array('status' => 'interrupted', 'already_complete' => false, 'journal' => $journal);
			}

			$normalized = is_array($result) ? $result : array('complete' => (bool) $result);
			if (empty($normalized['complete'])) {
				$journal['status'] = 'interrupted';
				$journal['cursor'] = $normalized['cursor'] ?? null;
				self::mustWrite($writeOption, $siteId, self::JOURNAL_OPTION, $journal);
				return array('status' => 'interrupted', 'already_complete' => false, 'journal' => $journal);
			}

			$journal['completed_steps'][] = $stepId;
			$journal['completed_steps'] = array_values(array_unique($journal['completed_steps']));
			$journal['current_step'] = null;
			$journal['cursor'] = null;
			$journal['last_error_class'] = null;
			self::mustWrite($writeOption, $siteId, self::JOURNAL_OPTION, $journal);
		}

		self::mustWrite($writeOption, $siteId, self::VERSION_OPTION, $targetVersion);
		$journal['status'] = 'complete';
		$journal['current_step'] = null;
		$journal['cursor'] = null;
		self::mustWrite($writeOption, $siteId, self::JOURNAL_OPTION, $journal);

		return array('status' => 'complete', 'already_complete' => false, 'journal' => $journal);
	}

	/**
	 * @param int[] $siteIds
	 * @param array<string, callable> $steps
	 */
	public static function runNetwork(
		array $siteIds,
		int $targetVersion,
		array $steps,
		callable $readOption,
		callable $writeOption
	): array {
		$results = array();
		foreach (array_values(array_unique(array_map('intval', $siteIds))) as $siteId) {
			if ($siteId <= 0) {
				continue;
			}
			$results[$siteId] = self::runSite($siteId, $targetVersion, $steps, $readOption, $writeOption);
		}
		ksort($results, SORT_NUMERIC);
		return $results;
	}

	/**
	 * Copy a legacy value only when canonical storage is absent, then verify it.
	 * The legacy key is never deleted here.
	 */
	public static function copyBeforeCutover(
		int $siteId,
		string $legacyKey,
		string $canonicalKey,
		callable $exists,
		callable $read,
		callable $write
	): array {
		if ($exists($siteId, $canonicalKey)) {
			return array('status' => 'canonical-present', 'copied' => false);
		}
		if (!$exists($siteId, $legacyKey)) {
			return array('status' => 'legacy-absent', 'copied' => false);
		}
		$value = $read($siteId, $legacyKey, null);
		if (!$write($siteId, $canonicalKey, $value)) {
			throw new RuntimeException('Canonical copy write failed for ' . $canonicalKey);
		}
		if (!$exists($siteId, $canonicalKey) || $read($siteId, $canonicalKey, null) !== $value) {
			throw new RuntimeException('Canonical copy verification failed for ' . $canonicalKey);
		}
		return array('status' => 'copied-and-verified', 'copied' => true);
	}

	public static function dualRead(
		int $siteId,
		string $canonicalKey,
		string $legacyKey,
		callable $exists,
		callable $read,
		$default = null
	) {
		if ($exists($siteId, $canonicalKey)) {
			return $read($siteId, $canonicalKey, $default);
		}
		if ($exists($siteId, $legacyKey)) {
			return $read($siteId, $legacyKey, $default);
		}
		return $default;
	}

	public static function canonicalWrite(
		int $siteId,
		string $canonicalKey,
		string $legacyKey,
		$value,
		callable $write,
		bool $mirrorRollbackSafeWrite = false
	): bool {
		if (!$write($siteId, $canonicalKey, $value)) {
			return false;
		}
		return !$mirrorRollbackSafeWrite || $write($siteId, $legacyKey, $value);
	}

	private static function normalizeJournal($journal, int $targetVersion): array
	{
		$journal = is_array($journal) ? $journal : array();
		if ((int) ($journal['target_version'] ?? 0) !== $targetVersion) {
			$journal = array();
		}
		return array(
			'target_version' => $targetVersion,
			'status' => in_array($journal['status'] ?? '', array('pending', 'running', 'interrupted', 'complete'), true)
				? (string) $journal['status']
				: 'pending',
			'current_step' => isset($journal['current_step']) ? (string) $journal['current_step'] : null,
			'completed_steps' => array_values(array_filter(array_map('strval', (array) ($journal['completed_steps'] ?? array())))),
			'attempts' => is_array($journal['attempts'] ?? null) ? $journal['attempts'] : array(),
			'cursor' => $journal['cursor'] ?? null,
			'last_error_class' => isset($journal['last_error_class']) ? (string) $journal['last_error_class'] : null,
			'legacy_values_retained' => true,
		);
	}

	private static function mustWrite(callable $writeOption, int $siteId, string $key, $value): void
	{
		if (!$writeOption($siteId, $key, $value)) {
			throw new RuntimeException('Migration-state write failed for ' . $key);
		}
	}
}

final class BVMGR_Prefix_Compatibility_Map
{
	private array $byLegacy = array();

	public function __construct(array $manifest)
	{
		foreach ((array) ($manifest['symbols'] ?? array()) as $entries) {
			foreach ((array) $entries as $entry) {
				$this->add($entry);
			}
		}
		foreach ((array) ($manifest['public_extension_apis'] ?? array()) as $entry) {
			$this->add($entry);
		}
	}

	public static function fromFile(string $path): self
	{
		$json = is_file($path) ? (string) file_get_contents($path) : '';
		$manifest = json_decode($json, true);
		if (!is_array($manifest)) {
			throw new RuntimeException('Invalid prefix manifest: ' . $path);
		}
		return new self($manifest);
	}

	public function canonicalFor(string $legacy): ?string
	{
		return isset($this->byLegacy[$legacy]) ? (string) $this->byLegacy[$legacy]['canonical_target'] : null;
	}

	public function policyFor(string $legacy): ?array
	{
		return $this->byLegacy[$legacy] ?? null;
	}

	public static function readOrder(string $canonical, string $legacy): array
	{
		return array($canonical, $legacy);
	}

	public static function writeTargets(string $canonical, string $legacy, bool $rollbackMirror): array
	{
		return $rollbackMirror ? array($canonical, $legacy) : array($canonical);
	}

	public static function fireOrder(string $canonical, string $legacy): array
	{
		return array($canonical, $legacy);
	}

	private function add(array $entry): void
	{
		$legacy = (string) ($entry['current_identifier'] ?? '');
		$canonical = $entry['canonical_target'] ?? null;
		if ($legacy === '' || !is_string($canonical) || $canonical === '') {
			return;
		}
		$this->byLegacy[$legacy] = $entry;
	}
}
