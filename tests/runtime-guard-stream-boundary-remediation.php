<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

function vms_test_runtime_guard_assert(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
}

function vms_test_runtime_guard_assert_same($expected, $actual, string $message): void
{
	vms_test_runtime_guard_assert(
		$expected === $actual,
		$message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . '.'
	);
}

function vms_test_runtime_guard_capture(callable $callback): array
{
	$warnings = array();
	set_error_handler(
		static function (int $severity, string $message, string $file = '', int $line = 0) use (&$warnings): bool {
			if ((error_reporting() & $severity) === 0) {
				return false;
			}

			$warnings[] = array(
				'severity' => $severity,
				'message' => $message,
				'file' => $file,
				'line' => $line,
			);
			return true;
		}
	);

	try {
		$value = $callback();
	} finally {
		restore_error_handler();
	}

	return array(
		'value' => $value,
		'warnings' => $warnings,
	);
}

function vms_test_runtime_guard_assert_no_warnings(array $warnings, string $message): void
{
	vms_test_runtime_guard_assert_same(0, count($warnings), $message . ' should not leak unsuppressed warnings.');
}

/**
 * @param array<int,array<string,mixed>> $events
 * @return array<int,string>
 */
function vms_test_runtime_guard_event_summary(array $events): array
{
	$summary = array();
	foreach ($events as $event) {
		$type = (string) ($event['type'] ?? '');
		$scenario = (string) ($event['scenario'] ?? '');
		if ($type === 'open') {
			$summary[] = $type . ':' . $scenario . ':' . (string) ($event['mode'] ?? '');
			continue;
		}

		$summary[] = $type . ':' . $scenario;
	}

	return $summary;
}

function add_action(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): bool
{
	unset($hook, $callback, $priority, $acceptedArgs);
	return true;
}

function add_filter(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): bool
{
	unset($hook, $callback, $priority, $acceptedArgs);
	return true;
}

function apply_filters(string $hook, $value)
{
	unset($hook);
	return $value;
}

function is_admin(): bool
{
	return false;
}

final class VMS_Test_Runtime_Guard_Stream_Wrapper
{
	public $context;

	/** @var array<string,array{data:string,open_fail:bool,read_fail:bool}> */
	public static array $scenarios = array();

	/** @var array<int,array<string,mixed>> */
	public static array $events = array();

	private string $scenario = '';
	private int $offset = 0;
	private bool $didReadFail = false;

	public static function setScenario(string $name, string $data = '', bool $openFail = false, bool $readFail = false): string
	{
		self::$scenarios[$name] = array(
			'data' => $data,
			'open_fail' => $openFail,
			'read_fail' => $readFail,
		);
		self::$events = array();

		return 'vmsrt://' . $name;
	}

	/**
	 * @param resource|string $openedPath
	 */
	public function stream_open(string $path, string $mode, int $options, &$openedPath): bool
	{
		unset($options, $openedPath);

		$this->scenario = (string) parse_url($path, PHP_URL_HOST);
		self::$events[] = array(
			'type' => 'open',
			'scenario' => $this->scenario,
			'path' => $path,
			'mode' => $mode,
		);

		$scenario = self::$scenarios[$this->scenario] ?? null;
		if (!is_array($scenario) || !empty($scenario['open_fail'])) {
			return false;
		}

		$this->offset = 0;
		$this->didReadFail = false;

		return true;
	}

	public function stream_read(int $count)
	{
		self::$events[] = array(
			'type' => 'read',
			'scenario' => $this->scenario,
			'count' => $count,
		);

		$scenario = self::$scenarios[$this->scenario] ?? array(
			'data' => '',
			'open_fail' => false,
			'read_fail' => false,
		);

		if (!empty($scenario['read_fail']) && !$this->didReadFail) {
			$this->didReadFail = true;
			return false;
		}

		$chunk = substr((string) $scenario['data'], $this->offset, $count);
		$this->offset += strlen($chunk);

		return $chunk;
	}

	public function stream_eof(): bool
	{
		$scenario = self::$scenarios[$this->scenario] ?? array(
			'data' => '',
			'open_fail' => false,
			'read_fail' => false,
		);

		return $this->offset >= strlen((string) $scenario['data']);
	}

	public function stream_stat(): array
	{
		return array();
	}

	public function stream_close(): void
	{
		self::$events[] = array(
			'type' => 'close',
			'scenario' => $this->scenario,
		);
	}
}

try {
	$repoRoot = dirname(__DIR__);
	$runtimePath = $repoRoot . '/includes/runtime-guards.php';
	$ticketingPath = $repoRoot . '/includes/integrations/ticketing-rules-v2.php';
	$runtimeSource = (string) file_get_contents($runtimePath);
	$ticketingSource = (string) file_get_contents($ticketingPath);

	vms_test_runtime_guard_assert($runtimeSource !== '', 'Runtime guards source should be readable.');
	vms_test_runtime_guard_assert($ticketingSource !== '', 'Ticketing V2 source should be readable.');

	$extractFunction = static function (string $source, string $functionName): string {
		$needle = 'function ' . $functionName;
		$start = strpos($source, $needle);
		if ($start === false) {
			throw new RuntimeException('Could not find function ' . $functionName . '().');
		}

		$braceStart = strpos($source, '{', $start);
		if ($braceStart === false) {
			throw new RuntimeException('Could not find opening brace for ' . $functionName . '().');
		}

		$length = strlen($source);
		$depth = 0;
		for ($offset = $braceStart; $offset < $length; $offset++) {
			$character = $source[$offset];
			if ($character === '{') {
				$depth++;
				continue;
			}
			if ($character === '}') {
				$depth--;
				if ($depth === 0) {
					return substr($source, $start, $offset - $start + 1);
				}
			}
		}

		throw new RuntimeException('Could not extract function body for ' . $functionName . '().');
	};

	$readLimitedStream = $extractFunction($runtimeSource, 'bvmgr_read_limited_stream');

	vms_test_runtime_guard_assert_same(
		1,
		substr_count($runtimeSource, 'phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen'),
		'F6 should contain exactly one line-specific fopen() suppression.'
	);
	vms_test_runtime_guard_assert_same(
		1,
		substr_count($runtimeSource, 'phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread'),
		'F6 should contain exactly one line-specific fread() suppression.'
	);
	vms_test_runtime_guard_assert_same(
		2,
		substr_count($runtimeSource, 'phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose'),
		'F6 should contain exactly two line-specific fclose() suppressions.'
	);
	vms_test_runtime_guard_assert(strpos($runtimeSource, 'phpcs:disable') === false, 'F6 should not introduce phpcs:disable.');
	vms_test_runtime_guard_assert(strpos($runtimeSource, 'phpcs:enable') === false, 'F6 should not introduce phpcs:enable.');

	vms_test_runtime_guard_assert(
		strpos($readLimitedStream, "@fopen(\$stream_uri, 'rb'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen") !== false,
		'The bounded stream helper should retain only a line-specific fopen() suppression.'
	);
	vms_test_runtime_guard_assert(
		strpos($readLimitedStream, '$chunk = fread($handle, min(8192, $remaining)); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread') !== false,
		'The bounded stream helper should retain only a line-specific fread() suppression.'
	);
	vms_test_runtime_guard_assert(
		substr_count($readLimitedStream, 'phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose') === 2,
		'The bounded stream helper should retain only the two line-specific fclose() suppressions.'
	);
	vms_test_runtime_guard_assert(
		strpos($readLimitedStream, 'file_get_contents(') === false,
		'The bounded stream helper should not switch to a whole-buffer file_get_contents() read.'
	);
	vms_test_runtime_guard_assert(
		strpos($readLimitedStream, 'WP_Filesystem(') === false && strpos($readLimitedStream, 'get_contents(') === false,
		'The bounded stream helper should not initialize or invoke WP_Filesystem.'
	);
	vms_test_runtime_guard_assert(
		strpos($readLimitedStream, '$remaining = ($max_bytes + 1) - strlen($data);') !== false,
		'The bounded stream helper should retain the max-bytes-plus-one guard.'
	);
	vms_test_runtime_guard_assert(
		strpos($readLimitedStream, 'min(8192, $remaining)') !== false,
		'The bounded stream helper should retain its 8 KB capped chunk reads.'
	);
	vms_test_runtime_guard_assert(
		strpos($ticketingSource, "function bvmgr_ticketing_v2_read_json_request_payload(int \$max_bytes): array") !== false
			&& strpos($ticketingSource, "bvmgr_read_limited_stream('php://input', \$max_bytes)") !== false,
		'The JSON request reader should keep the hardcoded php://input caller.'
	);
	vms_test_runtime_guard_assert_same(
		2,
		substr_count($ticketingSource, 'bvmgr_ticketing_v2_read_json_request_payload(65536)'),
		'The ticketing AJAX handlers should keep the 65,536-byte request-body cap.'
	);

	if (!in_array('vmsrt', stream_get_wrappers(), true)) {
		stream_wrapper_register('vmsrt', VMS_Test_Runtime_Guard_Stream_Wrapper::class);
	}

	require $runtimePath;

	$successCapture = vms_test_runtime_guard_capture(
		static function (): array {
			$uri = VMS_Test_Runtime_Guard_Stream_Wrapper::setScenario('success', 'hello world');
			return bvmgr_read_limited_stream($uri, 20);
		}
	);
	vms_test_runtime_guard_assert_no_warnings($successCapture['warnings'], 'Successful bounded stream read');
	vms_test_runtime_guard_assert_same(
		array(
			'ok' => true,
			'data' => 'hello world',
			'too_large' => false,
		),
		$successCapture['value'],
		'Successful bounded stream read should return the original payload.'
	);
	vms_test_runtime_guard_assert_same(
		array('open:success:rb', 'read:success', 'close:success'),
		vms_test_runtime_guard_event_summary(VMS_Test_Runtime_Guard_Stream_Wrapper::$events),
		'Successful bounded stream read should open rb, read once, and close the stream exactly once.'
	);
	vms_test_runtime_guard_assert_same('vmsrt://success', (string) (VMS_Test_Runtime_Guard_Stream_Wrapper::$events[0]['path'] ?? ''), 'Successful bounded stream read should open the expected stream path.');

	$oversizedCapture = vms_test_runtime_guard_capture(
		static function (): array {
			$uri = VMS_Test_Runtime_Guard_Stream_Wrapper::setScenario('oversized', 'abcdefgh');
			return bvmgr_read_limited_stream($uri, 5);
		}
	);
	vms_test_runtime_guard_assert_no_warnings($oversizedCapture['warnings'], 'Oversized bounded stream read');
	vms_test_runtime_guard_assert_same(true, !empty($oversizedCapture['value']['ok']), 'Oversized bounded stream read should still report a successful open/read.');
	vms_test_runtime_guard_assert_same(true, !empty($oversizedCapture['value']['too_large']), 'Oversized bounded stream read should set the too_large flag.');
	vms_test_runtime_guard_assert_same(6, strlen((string) $oversizedCapture['value']['data']), 'Oversized bounded stream read should cap the returned payload to max_bytes + 1 bytes.');
	vms_test_runtime_guard_assert_same(
		array('open:oversized:rb', 'read:oversized', 'close:oversized'),
		vms_test_runtime_guard_event_summary(VMS_Test_Runtime_Guard_Stream_Wrapper::$events),
		'Oversized bounded stream read should still close the stream exactly once.'
	);
	vms_test_runtime_guard_assert_same('vmsrt://oversized', (string) (VMS_Test_Runtime_Guard_Stream_Wrapper::$events[0]['path'] ?? ''), 'Oversized bounded stream read should open the expected stream path.');

	$readFailCapture = vms_test_runtime_guard_capture(
		static function (): array {
			$uri = VMS_Test_Runtime_Guard_Stream_Wrapper::setScenario('read-fail', 'abcdef', false, true);
			return bvmgr_read_limited_stream($uri, 5);
		}
	);
	vms_test_runtime_guard_assert_no_warnings($readFailCapture['warnings'], 'Read-failure bounded stream read');
	vms_test_runtime_guard_assert_same(
		array(
			'ok' => false,
			'data' => '',
			'too_large' => false,
		),
		$readFailCapture['value'],
		'Read-failure bounded stream read should return the safe failure payload.'
	);
	vms_test_runtime_guard_assert_same(
		array('open:read-fail:rb', 'read:read-fail', 'close:read-fail'),
		vms_test_runtime_guard_event_summary(VMS_Test_Runtime_Guard_Stream_Wrapper::$events),
		'Read-failure bounded stream read should still close the opened stream exactly once before returning.'
	);
	vms_test_runtime_guard_assert_same('vmsrt://read-fail', (string) (VMS_Test_Runtime_Guard_Stream_Wrapper::$events[0]['path'] ?? ''), 'Read-failure bounded stream read should open the expected stream path.');

	$openFailCapture = vms_test_runtime_guard_capture(
		static function (): array {
			$uri = VMS_Test_Runtime_Guard_Stream_Wrapper::setScenario('open-fail', '', true);
			return bvmgr_read_limited_stream($uri, 5);
		}
	);
	vms_test_runtime_guard_assert_no_warnings($openFailCapture['warnings'], 'Open-failure bounded stream read');
	vms_test_runtime_guard_assert_same(
		array(
			'ok' => false,
			'data' => '',
			'too_large' => false,
		),
		$openFailCapture['value'],
		'Open-failure bounded stream read should return the safe failure payload.'
	);
	vms_test_runtime_guard_assert_same(
		array('open:open-fail:rb'),
		vms_test_runtime_guard_event_summary(VMS_Test_Runtime_Guard_Stream_Wrapper::$events),
		'Open-failure bounded stream read should not close a stream that never opened.'
	);
	vms_test_runtime_guard_assert_same('vmsrt://open-fail', (string) (VMS_Test_Runtime_Guard_Stream_Wrapper::$events[0]['path'] ?? ''), 'Open-failure bounded stream read should target the expected stream path.');

	fwrite(STDOUT, "runtime-guard-stream-boundary-remediation: OK\n");
} finally {
	if (in_array('vmsrt', stream_get_wrappers(), true)) {
		stream_wrapper_unregister('vmsrt');
	}
}
