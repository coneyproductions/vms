<?php
declare(strict_types=1);

function vms_test_event_feedback_assert(bool $condition, string $message): void
{
    if ($condition) {
        return;
    }

    throw new RuntimeException($message);
}

function vms_test_event_feedback_assert_contains(string $needle, string $haystack, string $message): void
{
    vms_test_event_feedback_assert(
        strpos($haystack, $needle) !== false,
        $message . ' Missing substring: ' . $needle
    );
}

function vms_test_event_feedback_assert_not_contains(string $needle, string $haystack, string $message): void
{
    vms_test_event_feedback_assert(
        strpos($haystack, $needle) === false,
        $message . ' Unexpected substring: ' . $needle
    );
}

function vms_test_event_feedback_find_matching_brace(string $code, int $openBracePos): int
{
    $depth = 0;
    $length = strlen($code);
    for ($index = $openBracePos; $index < $length; $index++) {
        $char = $code[$index];
        if ($char === '{') {
            $depth++;
            continue;
        }

        if ($char === '}') {
            $depth--;
            if ($depth === 0) {
                return $index;
            }
        }
    }

    throw new RuntimeException('Matching brace not found.');
}

function vms_test_event_feedback_extract_named_function(string $path, string $functionName): string
{
    $code = (string) file_get_contents($path);
    $marker = 'function ' . $functionName . '(';
    $functionPos = strpos($code, $marker);
    if ($functionPos === false) {
        throw new RuntimeException('Function not found: ' . $functionName);
    }

    $bracePos = strpos($code, '{', $functionPos);
    if ($bracePos === false) {
        throw new RuntimeException('Function brace not found: ' . $functionName);
    }

    $endPos = vms_test_event_feedback_find_matching_brace($code, $bracePos);
    return substr($code, $functionPos, $endPos - $functionPos + 1);
}

function wp_unslash($value)
{
    if (is_array($value)) {
        return array_map('wp_unslash', $value);
    }

    if (is_string($value)) {
        return stripslashes($value);
    }

    return $value;
}

function wp_salt(string $scheme): string
{
    $GLOBALS['vms_test_event_feedback_wp_salt_calls'][] = $scheme;
    return (string) ($GLOBALS['vms_test_event_feedback_wp_salt_values'][$scheme] ?? 'vms-default-salt');
}

function vms_test_event_feedback_reset_runtime(): void
{
    $_SERVER = array();
    $GLOBALS['vms_test_event_feedback_wp_salt_calls'] = array();
    $GLOBALS['vms_test_event_feedback_wp_salt_values'] = array(
        'logged_in' => 'wp-salt-logged-in',
    );
}

function vms_test_event_feedback_expected_hash(string $ip, string $userAgent, string $language, string $salt): string
{
    return substr(hash_hmac('sha256', strtolower($ip) . '|' . $userAgent . '|' . $language, $salt), 0, 32);
}

function vms_test_event_feedback_capture_request_hash(array $server): array
{
    vms_test_event_feedback_reset_runtime();
    $_SERVER = $server;

    $warnings = array();
    set_error_handler(
        static function (int $severity, string $message) use (&$warnings): bool {
            $warnings[] = array(
                'severity' => $severity,
                'message' => $message,
            );
            return true;
        }
    );

    try {
        $hash = vms_feedback_request_hash();
    } finally {
        restore_error_handler();
    }

    return array(
        'hash' => $hash,
        'warnings' => $warnings,
        'salt_calls' => $GLOBALS['vms_test_event_feedback_wp_salt_calls'],
    );
}

function vms_test_event_feedback_assert_warning_count(array $warnings, int $expectedCount, string $message): void
{
    vms_test_event_feedback_assert(
        count($warnings) === $expectedCount,
        $message . ' Expected ' . $expectedCount . ' warnings, got ' . count($warnings) . '.'
    );
}

function vms_test_event_feedback_assert_array_to_string_warnings(array $warnings, int $expectedCount, string $message): void
{
    vms_test_event_feedback_assert_warning_count($warnings, $expectedCount, $message);
    foreach ($warnings as $warning) {
        $warningMessage = isset($warning['message']) ? (string) $warning['message'] : '';
        vms_test_event_feedback_assert(
            strpos($warningMessage, 'Array to string conversion') !== false,
            $message . ' Unexpected warning message: ' . $warningMessage
        );
    }
}

function vms_test_event_feedback_assert_hex_hash(string $hash, string $message): void
{
    vms_test_event_feedback_assert(
        strlen($hash) === 32 && ctype_xdigit($hash),
        $message . ' Expected a 32-character hexadecimal hash, got ' . var_export($hash, true)
    );
}

function vms_test_event_feedback_assert_request_hash_case(
    string $label,
    array $server,
    string $expectedIp,
    string $expectedUserAgent,
    string $expectedLanguage,
    int $expectedWarnings = 0
): array {
    $result = vms_test_event_feedback_capture_request_hash($server);
    $expectedHash = vms_test_event_feedback_expected_hash(
        $expectedIp,
        $expectedUserAgent,
        $expectedLanguage,
        'wp-salt-logged-in'
    );

    vms_test_event_feedback_assert(
        $result['hash'] === $expectedHash,
        $label . ' should preserve the exact request hash. Expected '
        . $expectedHash . ', got ' . $result['hash'] . '.'
    );
    vms_test_event_feedback_assert_hex_hash($result['hash'], $label . ' should preserve the truncated HMAC shape.');
    vms_test_event_feedback_assert(
        $result['salt_calls'] === array('logged_in'),
        $label . ' should use wp_salt("logged_in") exactly once. Got '
        . json_encode($result['salt_calls'], JSON_UNESCAPED_SLASHES) . '.'
    );

    if ($expectedWarnings === 0) {
        vms_test_event_feedback_assert_warning_count($result['warnings'], 0, $label . ' should not warn.');
    } else {
        vms_test_event_feedback_assert_array_to_string_warnings(
            $result['warnings'],
            $expectedWarnings,
            $label . ' should preserve array-to-string coercion warnings.'
        );
    }

    return $result;
}

function vms_test_event_feedback_run_subprocess(
    string $functionSource,
    array $server,
    bool $defineWpSalt,
    ?string $wpSaltValue,
    ?string $loggedInSaltConstant
): array {
    $tempPath = tempnam(sys_get_temp_dir(), 'vms-ef-hash-');
    if (!is_string($tempPath) || $tempPath === '') {
        throw new RuntimeException('Unable to allocate a temporary file.');
    }

    $code = "<?php\n";
    $code .= "declare(strict_types=1);\n";
    if ($loggedInSaltConstant !== null) {
        $code .= "define('LOGGED_IN_SALT', " . var_export($loggedInSaltConstant, true) . ");\n";
    }
    if ($defineWpSalt) {
        $code .= "function wp_salt(string \$scheme): string { return " . var_export((string) $wpSaltValue, true) . "; }\n";
    }
    $code .= <<<'PHP'
function wp_unslash($value)
{
    if (is_array($value)) {
        return array_map('wp_unslash', $value);
    }

    if (is_string($value)) {
        return stripslashes($value);
    }

    return $value;
}

PHP;
    $code .= $functionSource . "\n";
    $code .= '$_SERVER = ' . var_export($server, true) . ";\n";
    $code .= <<<'PHP'
$warnings = array();
set_error_handler(
    static function (int $severity, string $message) use (&$warnings): bool {
        $warnings[] = array(
            'severity' => $severity,
            'message' => $message,
        );
        return true;
    }
);

try {
    $hash = vms_feedback_request_hash();
} finally {
    restore_error_handler();
}

echo json_encode(
    array(
        'hash' => $hash,
        'warnings' => $warnings,
    ),
    JSON_UNESCAPED_SLASHES
), "\n";
PHP;

    file_put_contents($tempPath, $code);

    try {
        $descriptors = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        );
        $process = proc_open(array(PHP_BINARY, $tempPath), $descriptors, $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start subprocess.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        vms_test_event_feedback_assert(
            $exitCode === 0,
            'Subprocess should exit successfully. Stderr: ' . trim((string) $stderr)
        );

        $decoded = json_decode((string) $stdout, true);
        vms_test_event_feedback_assert(
            is_array($decoded),
            'Subprocess should return JSON. Output: ' . trim((string) $stdout)
        );

        return $decoded;
    } finally {
        unlink($tempPath);
    }
}

$pluginRoot = dirname(__DIR__);
$livePluginRoot = dirname(dirname($pluginRoot)) . '/vms';
$mirrorCorePath = $pluginRoot . '/includes/core/event-feedback.php';
$liveCorePath = $livePluginRoot . '/includes/core/event-feedback.php';
$mirrorPublicPath = $pluginRoot . '/includes/public/event-feedback.php';
$livePublicPath = $livePluginRoot . '/includes/public/event-feedback.php';

$mirrorCoreSource = (string) file_get_contents($mirrorCorePath);
$liveCoreSource = (string) file_get_contents($liveCorePath);
$mirrorPublicSource = (string) file_get_contents($mirrorPublicPath);
$livePublicSource = (string) file_get_contents($livePublicPath);

vms_test_event_feedback_assert($mirrorCoreSource !== '', 'Mirror core Event Feedback source should be readable.');
vms_test_event_feedback_assert($liveCoreSource !== '', 'Live core Event Feedback source should be readable.');
vms_test_event_feedback_assert($mirrorPublicSource !== '', 'Mirror public Event Feedback source should be readable.');
vms_test_event_feedback_assert($livePublicSource !== '', 'Live public Event Feedback source should be readable.');

$mirrorRequestHashSource = vms_test_event_feedback_extract_named_function($mirrorCorePath, 'vms_feedback_request_hash');
$liveRequestHashSource = vms_test_event_feedback_extract_named_function($liveCorePath, 'vms_feedback_request_hash');

vms_test_event_feedback_assert(
    $mirrorRequestHashSource === $liveRequestHashSource,
    'Mirror/live request-hash helpers should remain aligned.'
);

foreach (
    array(
        'Mirror core Event Feedback' => $mirrorCoreSource,
        'Live core Event Feedback' => $liveCoreSource,
    ) as $label => $source
) {
    vms_test_event_feedback_assert_contains(
        "array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR')",
        $source,
        $label . ' should preserve CF > XFF > REMOTE precedence.'
    );
    vms_test_event_feedback_assert_contains(
        "trim(explode(',', \$raw)[0])",
        $source,
        $label . ' should preserve first-element XFF parsing.'
    );
    vms_test_event_feedback_assert_contains(
        "substr((string) wp_unslash(\$_SERVER['HTTP_USER_AGENT']), 0, 255)",
        $source,
        $label . ' should preserve the raw capped UA boundary.'
    );
    vms_test_event_feedback_assert_contains(
        "substr((string) wp_unslash(\$_SERVER['HTTP_ACCEPT_LANGUAGE']), 0, 80)",
        $source,
        $label . ' should preserve the raw capped Accept-Language boundary.'
    );
    vms_test_event_feedback_assert_contains(
        "wp_salt('logged_in')",
        $source,
        $label . ' should preserve the wp_salt() priority.'
    );
    vms_test_event_feedback_assert_contains(
        "LOGGED_IN_SALT",
        $source,
        $label . ' should preserve the LOGGED_IN_SALT fallback.'
    );
    vms_test_event_feedback_assert_contains(
        "'vms-feedback-request'",
        $source,
        $label . ' should preserve the literal salt fallback.'
    );
    vms_test_event_feedback_assert_contains(
        "hash_hmac('sha256', strtolower(\$ip) . '|' . \$user_agent . '|' . \$language, \$salt)",
        $source,
        $label . ' should preserve the exact HMAC input format.'
    );
    vms_test_event_feedback_assert_contains(
        "return substr(hash_hmac('sha256', strtolower(\$ip) . '|' . \$user_agent . '|' . \$language, \$salt), 0, 32);",
        $source,
        $label . ' should preserve the 32-character hash truncation.'
    );
}

foreach (
    array(
        'Mirror request-hash helper' => $mirrorRequestHashSource,
        'Live request-hash helper' => $liveRequestHashSource,
    ) as $label => $helperSource
) {
    vms_test_event_feedback_assert_not_contains(
        'vms_request_server_value(',
        $helperSource,
        $label . ' should not migrate to shared server helpers yet.'
    );
    vms_test_event_feedback_assert_not_contains(
        'vms_request_remote_addr(',
        $helperSource,
        $label . ' should not migrate to the remote-address helper yet.'
    );
    vms_test_event_feedback_assert_not_contains(
        'vms_request_user_agent(',
        $helperSource,
        $label . ' should not migrate to the capped UA helper yet.'
    );
}

foreach (
    array(
        'Mirror public Event Feedback' => $mirrorPublicSource,
        'Live public Event Feedback' => $livePublicSource,
    ) as $label => $source
) {
    vms_test_event_feedback_assert_contains(
        '$request_hash = function_exists(\'vms_feedback_request_hash\') ? vms_feedback_request_hash() : \'\';',
        $source,
        $label . ' should still compute request_hash up front.'
    );
    vms_test_event_feedback_assert_contains(
        "vms_feedback_existing_recent_duplicate(\$event_plan_id, \$duplicate_fingerprint, \$request_hash)",
        $source,
        $label . ' should preserve the duplicate lookup tuple.'
    );
    vms_test_event_feedback_assert_contains(
        "hash('sha256', \$event_plan_id . '|' . \$duplicate_fingerprint . '|' . \$request_hash)",
        $source,
        $label . ' should preserve the request submission lock identity.'
    );
    vms_test_event_feedback_assert_contains(
        "update_post_meta(\$response_id, vms_feedback_meta_key('duplicate_fingerprint'), \$duplicate_fingerprint);",
        $source,
        $label . ' should persist the duplicate fingerprint.'
    );
    vms_test_event_feedback_assert_contains(
        "update_post_meta(\$response_id, vms_feedback_meta_key('request_hash'), \$request_hash);",
        $source,
        $label . ' should persist the request hash.'
    );
    vms_test_event_feedback_assert_contains(
        "vms_feedback_dedupe_redirect(\$redirect, 'fingerprint');",
        $source,
        $label . ' should preserve the fingerprint dedupe reason.'
    );
    vms_test_event_feedback_assert_not_contains(
        'HTTP_CF_CONNECTING_IP',
        $source,
        $label . ' should not persist raw Cloudflare headers.'
    );
    vms_test_event_feedback_assert_not_contains(
        'HTTP_X_FORWARDED_FOR',
        $source,
        $label . ' should not persist raw XFF headers.'
    );
    vms_test_event_feedback_assert_not_contains(
        'REMOTE_ADDR',
        $source,
        $label . ' should not persist raw remote addresses.'
    );
    vms_test_event_feedback_assert_not_contains(
        'HTTP_USER_AGENT',
        $source,
        $label . ' should not persist raw user agents.'
    );
    vms_test_event_feedback_assert_not_contains(
        'HTTP_ACCEPT_LANGUAGE',
        $source,
        $label . ' should not persist raw Accept-Language values.'
    );
}

vms_test_event_feedback_assert_contains(
    "function vms_feedback_existing_recent_duplicate(int \$event_plan_id, string \$duplicate_fingerprint, string \$request_hash, int \$window_seconds = 7200): int",
    $mirrorCoreSource,
    'Mirror core Event Feedback should preserve the 7200-second duplicate window default.'
);
vms_test_event_feedback_assert(
    strpos($mirrorPublicSource, '$request_hash = function_exists(\'vms_feedback_request_hash\') ? vms_feedback_request_hash() : \'\';')
    < strpos($mirrorPublicSource, "vms_feedback_existing_recent_duplicate(\$event_plan_id, \$duplicate_fingerprint, \$request_hash)"),
    'Mirror public Event Feedback should compute request_hash before the duplicate lookup.'
);
vms_test_event_feedback_assert(
    strpos($mirrorPublicSource, "vms_feedback_existing_recent_duplicate(\$event_plan_id, \$duplicate_fingerprint, \$request_hash)")
    < strpos($mirrorPublicSource, "hash('sha256', \$event_plan_id . '|' . \$duplicate_fingerprint . '|' . \$request_hash)"),
    'Mirror public Event Feedback should derive the request lock after the duplicate lookup.'
);

eval($mirrorRequestHashSource);

$proxyUserAgent = 'Browser/1.0';
$proxyLanguage = 'en-US,en;q=0.9';

$proxyCases = array(
    'no_proxy_remote_addr' => array(
        'server' => array(
            'REMOTE_ADDR' => '198.51.100.10',
            'HTTP_USER_AGENT' => $proxyUserAgent,
            'HTTP_ACCEPT_LANGUAGE' => $proxyLanguage,
        ),
        'expected_ip' => '198.51.100.10',
    ),
    'cloudflare_present' => array(
        'server' => array(
            'HTTP_CF_CONNECTING_IP' => '203.0.113.7',
            'REMOTE_ADDR' => '198.51.100.10',
            'HTTP_USER_AGENT' => $proxyUserAgent,
            'HTTP_ACCEPT_LANGUAGE' => $proxyLanguage,
        ),
        'expected_ip' => '203.0.113.7',
    ),
    'xff_present' => array(
        'server' => array(
            'HTTP_X_FORWARDED_FOR' => '198.51.100.5',
            'REMOTE_ADDR' => '198.51.100.10',
            'HTTP_USER_AGENT' => $proxyUserAgent,
            'HTTP_ACCEPT_LANGUAGE' => $proxyLanguage,
        ),
        'expected_ip' => '198.51.100.5',
    ),
    'cloudflare_and_xff_present' => array(
        'server' => array(
            'HTTP_CF_CONNECTING_IP' => '203.0.113.7',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.5, 198.51.100.6',
            'REMOTE_ADDR' => '198.51.100.10',
            'HTTP_USER_AGENT' => $proxyUserAgent,
            'HTTP_ACCEPT_LANGUAGE' => $proxyLanguage,
        ),
        'expected_ip' => '203.0.113.7',
    ),
    'all_three_present' => array(
        'server' => array(
            'HTTP_CF_CONNECTING_IP' => '203.0.113.7',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.5, 198.51.100.6',
            'REMOTE_ADDR' => '192.0.2.44',
            'HTTP_USER_AGENT' => $proxyUserAgent,
            'HTTP_ACCEPT_LANGUAGE' => $proxyLanguage,
        ),
        'expected_ip' => '203.0.113.7',
    ),
    'empty_cloudflare_xff_present' => array(
        'server' => array(
            'HTTP_CF_CONNECTING_IP' => '',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.5, 203.0.113.9',
            'REMOTE_ADDR' => '192.0.2.44',
            'HTTP_USER_AGENT' => $proxyUserAgent,
            'HTTP_ACCEPT_LANGUAGE' => $proxyLanguage,
        ),
        'expected_ip' => '198.51.100.5',
    ),
    'empty_cloudflare_empty_xff_remote_present' => array(
        'server' => array(
            'HTTP_CF_CONNECTING_IP' => '',
            'HTTP_X_FORWARDED_FOR' => '',
            'REMOTE_ADDR' => '192.0.2.44',
            'HTTP_USER_AGENT' => $proxyUserAgent,
            'HTTP_ACCEPT_LANGUAGE' => $proxyLanguage,
        ),
        'expected_ip' => '192.0.2.44',
    ),
    'all_sources_empty' => array(
        'server' => array(
            'HTTP_CF_CONNECTING_IP' => '',
            'HTTP_X_FORWARDED_FOR' => '',
            'REMOTE_ADDR' => '',
            'HTTP_USER_AGENT' => $proxyUserAgent,
            'HTTP_ACCEPT_LANGUAGE' => $proxyLanguage,
        ),
        'expected_ip' => '',
    ),
    'comma_separated_xff_chain' => array(
        'server' => array(
            'HTTP_X_FORWARDED_FOR' => '198.51.100.5, 203.0.113.9, 192.0.2.1',
            'REMOTE_ADDR' => '192.0.2.44',
            'HTTP_USER_AGENT' => $proxyUserAgent,
            'HTTP_ACCEPT_LANGUAGE' => $proxyLanguage,
        ),
        'expected_ip' => '198.51.100.5',
    ),
    'xff_first_element_whitespace' => array(
        'server' => array(
            'HTTP_X_FORWARDED_FOR' => ' 198.51.100.5 , 203.0.113.9 ',
            'REMOTE_ADDR' => '192.0.2.44',
            'HTTP_USER_AGENT' => $proxyUserAgent,
            'HTTP_ACCEPT_LANGUAGE' => $proxyLanguage,
        ),
        'expected_ip' => '198.51.100.5',
    ),
    'cloudflare_value_whitespace' => array(
        'server' => array(
            'HTTP_CF_CONNECTING_IP' => ' 203.0.113.7 ',
            'REMOTE_ADDR' => '192.0.2.44',
            'HTTP_USER_AGENT' => $proxyUserAgent,
            'HTTP_ACCEPT_LANGUAGE' => $proxyLanguage,
        ),
        'expected_ip' => '203.0.113.7',
    ),
    'mixed_case_ip_like_string' => array(
        'server' => array(
            'REMOTE_ADDR' => 'ABCD:EF::1',
            'HTTP_USER_AGENT' => $proxyUserAgent,
            'HTTP_ACCEPT_LANGUAGE' => $proxyLanguage,
        ),
        'expected_ip' => 'ABCD:EF::1',
    ),
);

$proxyResults = array();
foreach ($proxyCases as $label => $case) {
    $proxyResults[$label] = vms_test_event_feedback_assert_request_hash_case(
        $label,
        $case['server'],
        $case['expected_ip'],
        $proxyUserAgent,
        $proxyLanguage
    );
}

$mixedCaseRemoteHash = $proxyResults['mixed_case_ip_like_string']['hash'];
$lowerCaseRemoteHash = vms_test_event_feedback_assert_request_hash_case(
    'lowercase_ip_equivalent',
    array(
        'REMOTE_ADDR' => 'abcd:ef::1',
        'HTTP_USER_AGENT' => $proxyUserAgent,
        'HTTP_ACCEPT_LANGUAGE' => $proxyLanguage,
    ),
    'abcd:ef::1',
    $proxyUserAgent,
    $proxyLanguage
)['hash'];
vms_test_event_feedback_assert(
    $mixedCaseRemoteHash === $lowerCaseRemoteHash,
    'IP lowercasing should remain hash-significant only at composition time.'
);

$ua255 = str_repeat('A', 255);
$ua256 = $ua255 . 'B';
$uaLong = $ua255 . str_repeat('C', 80);
$uaQuotedInput = "Browser\\\\Slash \\\"Tablet\\\"";
$uaQuotedExpected = "Browser\\Slash \"Tablet\"";
$uaHtmlControl = "<b>Tablet</b>\x00\x01";

$uaCases = array(
    'ua_missing' => array('server' => array('REMOTE_ADDR' => '198.51.100.10', 'HTTP_ACCEPT_LANGUAGE' => $proxyLanguage), 'expected' => '', 'warnings' => 0),
    'ua_empty' => array('server' => array('REMOTE_ADDR' => '198.51.100.10', 'HTTP_USER_AGENT' => '', 'HTTP_ACCEPT_LANGUAGE' => $proxyLanguage), 'expected' => '', 'warnings' => 0),
    'ua_ordinary' => array('server' => array('REMOTE_ADDR' => '198.51.100.10', 'HTTP_USER_AGENT' => 'Mozilla/5.0', 'HTTP_ACCEPT_LANGUAGE' => $proxyLanguage), 'expected' => 'Mozilla/5.0', 'warnings' => 0),
    'ua_mixed_case' => array('server' => array('REMOTE_ADDR' => '198.51.100.10', 'HTTP_USER_AGENT' => 'MoZiLLa/5.0', 'HTTP_ACCEPT_LANGUAGE' => $proxyLanguage), 'expected' => 'MoZiLLa/5.0', 'warnings' => 0),
    'ua_whitespace' => array('server' => array('REMOTE_ADDR' => '198.51.100.10', 'HTTP_USER_AGENT' => '  Browser/1.0 Tablet  ', 'HTTP_ACCEPT_LANGUAGE' => $proxyLanguage), 'expected' => '  Browser/1.0 Tablet  ', 'warnings' => 0),
    'ua_exactly_255' => array('server' => array('REMOTE_ADDR' => '198.51.100.10', 'HTTP_USER_AGENT' => $ua255, 'HTTP_ACCEPT_LANGUAGE' => $proxyLanguage), 'expected' => $ua255, 'warnings' => 0),
    'ua_256_bytes' => array('server' => array('REMOTE_ADDR' => '198.51.100.10', 'HTTP_USER_AGENT' => $ua256, 'HTTP_ACCEPT_LANGUAGE' => $proxyLanguage), 'expected' => $ua255, 'warnings' => 0),
    'ua_substantially_longer' => array('server' => array('REMOTE_ADDR' => '198.51.100.10', 'HTTP_USER_AGENT' => $uaLong, 'HTTP_ACCEPT_LANGUAGE' => $proxyLanguage), 'expected' => $ua255, 'warnings' => 0),
    'ua_quotes_and_slashes' => array('server' => array('REMOTE_ADDR' => '198.51.100.10', 'HTTP_USER_AGENT' => $uaQuotedInput, 'HTTP_ACCEPT_LANGUAGE' => $proxyLanguage), 'expected' => $uaQuotedExpected, 'warnings' => 0),
    'ua_html_and_control_content' => array('server' => array('REMOTE_ADDR' => '198.51.100.10', 'HTTP_USER_AGENT' => $uaHtmlControl, 'HTTP_ACCEPT_LANGUAGE' => $proxyLanguage), 'expected' => $uaHtmlControl, 'warnings' => 0),
    'ua_malformed_non_scalar' => array('server' => array('REMOTE_ADDR' => '198.51.100.10', 'HTTP_USER_AGENT' => array('Tablet'), 'HTTP_ACCEPT_LANGUAGE' => $proxyLanguage), 'expected' => 'Array', 'warnings' => 1),
);

$uaResults = array();
foreach ($uaCases as $label => $case) {
    $uaResults[$label] = vms_test_event_feedback_assert_request_hash_case(
        $label,
        $case['server'],
        '198.51.100.10',
        $case['expected'],
        $proxyLanguage,
        $case['warnings']
    );
}

vms_test_event_feedback_assert(
    $uaResults['ua_exactly_255']['hash'] === $uaResults['ua_256_bytes']['hash'],
    'Values beyond the 255-byte UA cap should not affect the hash.'
);
vms_test_event_feedback_assert(
    $uaResults['ua_exactly_255']['hash'] === $uaResults['ua_substantially_longer']['hash'],
    'Substantially longer UA values should still hash from the first 255 bytes only.'
);
vms_test_event_feedback_assert(
    $uaResults['ua_ordinary']['hash'] !== $uaResults['ua_mixed_case']['hash'],
    'UA case should remain hash-significant.'
);
vms_test_event_feedback_assert(
    $uaResults['ua_ordinary']['hash'] !== $uaResults['ua_whitespace']['hash'],
    'UA leading/trailing whitespace should remain hash-significant.'
);

$lang80 = str_repeat('L', 80);
$lang81 = $lang80 . 'Z';
$langLong = $lang80 . str_repeat('Q', 60);
$ordinaryLanguage = 'en-US,en;q=0.9';

$languageCases = array(
    'language_missing' => array('server' => array('REMOTE_ADDR' => '198.51.100.10', 'HTTP_USER_AGENT' => $proxyUserAgent), 'expected' => '', 'warnings' => 0),
    'language_empty' => array('server' => array('REMOTE_ADDR' => '198.51.100.10', 'HTTP_USER_AGENT' => $proxyUserAgent, 'HTTP_ACCEPT_LANGUAGE' => ''), 'expected' => '', 'warnings' => 0),
    'language_ordinary' => array('server' => array('REMOTE_ADDR' => '198.51.100.10', 'HTTP_USER_AGENT' => $proxyUserAgent, 'HTTP_ACCEPT_LANGUAGE' => $ordinaryLanguage), 'expected' => $ordinaryLanguage, 'warnings' => 0),
    'language_mixed_case' => array('server' => array('REMOTE_ADDR' => '198.51.100.10', 'HTTP_USER_AGENT' => $proxyUserAgent, 'HTTP_ACCEPT_LANGUAGE' => 'En-US,en;q=0.9'), 'expected' => 'En-US,en;q=0.9', 'warnings' => 0),
    'language_ordering_difference' => array('server' => array('REMOTE_ADDR' => '198.51.100.10', 'HTTP_USER_AGENT' => $proxyUserAgent, 'HTTP_ACCEPT_LANGUAGE' => 'fr-CA,fr;q=0.8,en;q=0.5'), 'expected' => 'fr-CA,fr;q=0.8,en;q=0.5', 'warnings' => 0),
    'language_q_value_difference' => array('server' => array('REMOTE_ADDR' => '198.51.100.10', 'HTTP_USER_AGENT' => $proxyUserAgent, 'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.8'), 'expected' => 'en-US,en;q=0.8', 'warnings' => 0),
    'language_whitespace' => array('server' => array('REMOTE_ADDR' => '198.51.100.10', 'HTTP_USER_AGENT' => $proxyUserAgent, 'HTTP_ACCEPT_LANGUAGE' => '  en-US,en;q=0.9  '), 'expected' => '  en-US,en;q=0.9  ', 'warnings' => 0),
    'language_exactly_80' => array('server' => array('REMOTE_ADDR' => '198.51.100.10', 'HTTP_USER_AGENT' => $proxyUserAgent, 'HTTP_ACCEPT_LANGUAGE' => $lang80), 'expected' => $lang80, 'warnings' => 0),
    'language_81_bytes' => array('server' => array('REMOTE_ADDR' => '198.51.100.10', 'HTTP_USER_AGENT' => $proxyUserAgent, 'HTTP_ACCEPT_LANGUAGE' => $lang81), 'expected' => $lang80, 'warnings' => 0),
    'language_substantially_longer' => array('server' => array('REMOTE_ADDR' => '198.51.100.10', 'HTTP_USER_AGENT' => $proxyUserAgent, 'HTTP_ACCEPT_LANGUAGE' => $langLong), 'expected' => $lang80, 'warnings' => 0),
    'language_malformed_non_scalar' => array('server' => array('REMOTE_ADDR' => '198.51.100.10', 'HTTP_USER_AGENT' => $proxyUserAgent, 'HTTP_ACCEPT_LANGUAGE' => array('en-US')), 'expected' => 'Array', 'warnings' => 1),
);

$languageResults = array();
foreach ($languageCases as $label => $case) {
    $languageResults[$label] = vms_test_event_feedback_assert_request_hash_case(
        $label,
        $case['server'],
        '198.51.100.10',
        $proxyUserAgent,
        $case['expected'],
        $case['warnings']
    );
}

vms_test_event_feedback_assert(
    $languageResults['language_ordinary']['hash'] !== $languageResults['language_ordering_difference']['hash'],
    'Accept-Language ordering should remain hash-significant.'
);
vms_test_event_feedback_assert(
    $languageResults['language_ordinary']['hash'] !== $languageResults['language_q_value_difference']['hash'],
    'Accept-Language q= values should remain hash-significant.'
);
vms_test_event_feedback_assert(
    $languageResults['language_ordinary']['hash'] !== $languageResults['language_mixed_case']['hash'],
    'Accept-Language case should remain hash-significant.'
);
vms_test_event_feedback_assert(
    $languageResults['language_ordinary']['hash'] !== $languageResults['language_whitespace']['hash'],
    'Accept-Language leading/trailing whitespace should remain hash-significant.'
);
vms_test_event_feedback_assert(
    $languageResults['language_exactly_80']['hash'] === $languageResults['language_81_bytes']['hash'],
    'Values beyond the 80-byte Accept-Language cap should not affect the hash.'
);
vms_test_event_feedback_assert(
    $languageResults['language_exactly_80']['hash'] === $languageResults['language_substantially_longer']['hash'],
    'Substantially longer Accept-Language values should still hash from the first 80 bytes only.'
);

$baselineHash = $proxyResults['no_proxy_remote_addr']['hash'];
$sameInputsHash = vms_test_event_feedback_assert_request_hash_case(
    'same_inputs_repeat',
    array(
        'REMOTE_ADDR' => '198.51.100.10',
        'HTTP_USER_AGENT' => $proxyUserAgent,
        'HTTP_ACCEPT_LANGUAGE' => $proxyLanguage,
    ),
    '198.51.100.10',
    $proxyUserAgent,
    $proxyLanguage
)['hash'];
vms_test_event_feedback_assert(
    $baselineHash === $sameInputsHash,
    'Equivalent inputs should produce identical hashes.'
);

$changedIpHash = vms_test_event_feedback_assert_request_hash_case(
    'changed_ip_only',
    array(
        'REMOTE_ADDR' => '198.51.100.11',
        'HTTP_USER_AGENT' => $proxyUserAgent,
        'HTTP_ACCEPT_LANGUAGE' => $proxyLanguage,
    ),
    '198.51.100.11',
    $proxyUserAgent,
    $proxyLanguage
)['hash'];
$changedUaHash = vms_test_event_feedback_assert_request_hash_case(
    'changed_ua_only',
    array(
        'REMOTE_ADDR' => '198.51.100.10',
        'HTTP_USER_AGENT' => 'Browser/2.0',
        'HTTP_ACCEPT_LANGUAGE' => $proxyLanguage,
    ),
    '198.51.100.10',
    'Browser/2.0',
    $proxyLanguage
)['hash'];
$changedLanguageHash = vms_test_event_feedback_assert_request_hash_case(
    'changed_language_only',
    array(
        'REMOTE_ADDR' => '198.51.100.10',
        'HTTP_USER_AGENT' => $proxyUserAgent,
        'HTTP_ACCEPT_LANGUAGE' => 'fr-CA,fr;q=0.8',
    ),
    '198.51.100.10',
    $proxyUserAgent,
    'fr-CA,fr;q=0.8'
)['hash'];

vms_test_event_feedback_assert($baselineHash !== $changedIpHash, 'Changing only IP should change the request hash.');
vms_test_event_feedback_assert($baselineHash !== $changedUaHash, 'Changing only UA should change the request hash.');
vms_test_event_feedback_assert($baselineHash !== $changedLanguageHash, 'Changing only Accept-Language should change the request hash.');

$wpSaltFallbackHash = vms_test_event_feedback_run_subprocess(
    $mirrorRequestHashSource,
    array(
        'REMOTE_ADDR' => '198.51.100.10',
        'HTTP_USER_AGENT' => $proxyUserAgent,
        'HTTP_ACCEPT_LANGUAGE' => $proxyLanguage,
    ),
    false,
    null,
    'logged-in-salt-constant'
);
vms_test_event_feedback_assert(
    isset($wpSaltFallbackHash['hash']) && $wpSaltFallbackHash['hash'] === vms_test_event_feedback_expected_hash('198.51.100.10', $proxyUserAgent, $proxyLanguage, 'logged-in-salt-constant'),
    'LOGGED_IN_SALT fallback should preserve the same HMAC format.'
);
vms_test_event_feedback_assert_warning_count(
    isset($wpSaltFallbackHash['warnings']) && is_array($wpSaltFallbackHash['warnings']) ? $wpSaltFallbackHash['warnings'] : array(),
    0,
    'LOGGED_IN_SALT fallback should not warn.'
);
vms_test_event_feedback_assert_hex_hash(
    (string) ($wpSaltFallbackHash['hash'] ?? ''),
    'LOGGED_IN_SALT fallback should preserve the truncated HMAC shape.'
);

$literalFallbackHash = vms_test_event_feedback_run_subprocess(
    $mirrorRequestHashSource,
    array(
        'REMOTE_ADDR' => '198.51.100.10',
        'HTTP_USER_AGENT' => $proxyUserAgent,
        'HTTP_ACCEPT_LANGUAGE' => $proxyLanguage,
    ),
    false,
    null,
    null
);
vms_test_event_feedback_assert(
    isset($literalFallbackHash['hash']) && $literalFallbackHash['hash'] === vms_test_event_feedback_expected_hash('198.51.100.10', $proxyUserAgent, $proxyLanguage, 'vms-feedback-request'),
    'Literal salt fallback should preserve the same HMAC format.'
);
vms_test_event_feedback_assert_warning_count(
    isset($literalFallbackHash['warnings']) && is_array($literalFallbackHash['warnings']) ? $literalFallbackHash['warnings'] : array(),
    0,
    'Literal salt fallback should not warn.'
);
vms_test_event_feedback_assert_hex_hash(
    (string) ($literalFallbackHash['hash'] ?? ''),
    'Literal fallback should preserve the truncated HMAC shape.'
);

fwrite(STDOUT, "Event Feedback request hash characterization OK.\n");
