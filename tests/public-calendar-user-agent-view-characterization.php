<?php
declare(strict_types=1);

function vms_test_public_calendar_assert(bool $condition, string $message): void
{
    if ($condition) {
        return;
    }

    throw new RuntimeException($message);
}

function vms_test_public_calendar_find_matching_brace(string $code, int $openBracePos): int
{
    $depth = 0;
    $length = strlen($code);
    for ($i = $openBracePos; $i < $length; $i++) {
        $char = $code[$i];
        if ($char === '{') {
            $depth++;
            continue;
        }

        if ($char === '}') {
            $depth--;
            if ($depth === 0) {
                return $i;
            }
        }
    }

    throw new RuntimeException('Matching brace not found.');
}

function vms_test_public_calendar_extract_named_function(string $path, string $functionName): string
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

    $endPos = vms_test_public_calendar_find_matching_brace($code, $bracePos);
    return substr($code, $functionPos, $endPos - $functionPos + 1);
}

function vms_test_public_calendar_assert_contains(string $needle, string $haystack, string $message): void
{
    vms_test_public_calendar_assert(
        strpos($haystack, $needle) !== false,
        $message . ' Missing substring: ' . $needle
    );
}

function vms_test_public_calendar_assert_not_contains(string $needle, string $haystack, string $message): void
{
    vms_test_public_calendar_assert(
        strpos($haystack, $needle) === false,
        $message . ' Unexpected substring: ' . $needle
    );
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

function sanitize_key($value): string
{
    if (!is_scalar($value)) {
        return '';
    }

    $value = strtolower((string) $value);
    $value = preg_replace('/[^a-z0-9_\-]/', '', $value);
    return is_string($value) ? $value : '';
}

function sanitize_text_field($value): string
{
    if (!is_scalar($value)) {
        return '';
    }

    $filtered = (string) $value;
    $filtered = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/', '', $filtered);
    $filtered = is_string($filtered) ? $filtered : '';

    if (strpos($filtered, '<') !== false) {
        $filtered = preg_replace('/<[^>]*>/', '', $filtered);
        $filtered = is_string($filtered) ? $filtered : '';
        $filtered = str_replace("<\n", "&lt;\n", $filtered);
    }

    $filtered = preg_replace('/[\r\n\t ]+/', ' ', $filtered);
    $filtered = is_string($filtered) ? trim($filtered) : '';

    while ($filtered !== '' && preg_match('/%[a-f0-9]{2}/i', $filtered, $matches) === 1) {
        $filtered = str_replace($matches[0], '', $filtered);
    }

    if ($filtered !== '') {
        $filtered = trim((string) preg_replace('/ +/', ' ', $filtered));
    }

    return $filtered;
}

function bvmgr_request_server_value(string $key): string
{
    if (!isset($_SERVER[$key]) || !is_scalar($_SERVER[$key])) {
        return '';
    }

    $value = wp_unslash($_SERVER[$key]);
    if (!is_scalar($value)) {
        return '';
    }

    return trim((string) $value);
}

function wp_is_mobile(): bool
{
    return !empty($GLOBALS['vms_test_public_calendar_is_mobile']);
}

function vms_test_public_calendar_reset_runtime(): void
{
    $_GET = array();
    $_SERVER = array();
    $GLOBALS['vms_test_public_calendar_is_mobile'] = false;
}

function vms_test_public_calendar_capture_current_user_agent_from_globals(): array
{
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
        $normalized = bvmgr_public_calendar_get_request_user_agent();
    } finally {
        restore_error_handler();
    }

    return array(
        'normalized' => $normalized,
        'warnings' => $warnings,
    );
}

function vms_test_public_calendar_capture_current_user_agent(bool $setHeader, $header): array
{
    vms_test_public_calendar_reset_runtime();
    if ($setHeader) {
        $_SERVER['HTTP_USER_AGENT'] = $header;
    }

    return vms_test_public_calendar_capture_current_user_agent_from_globals();
}

function vms_test_public_calendar_expected_user_agent(bool $setHeader, $header): string
{
    vms_test_public_calendar_reset_runtime();
    if ($setHeader) {
        $_SERVER['HTTP_USER_AGENT'] = $header;
    }

    $userAgent = bvmgr_request_server_value('HTTP_USER_AGENT');
    if ($userAgent === '') {
        return '';
    }

    return strtolower(sanitize_text_field($userAgent));
}

function vms_test_public_calendar_detects_token(string $normalizedUserAgent, string $token): bool
{
    return strpos($normalizedUserAgent, $token) !== false;
}

function vms_test_public_calendar_mixed_case(string $value): string
{
    $chars = str_split($value);
    foreach ($chars as $index => $char) {
        $chars[$index] = ($index % 2 === 0) ? strtoupper($char) : strtolower($char);
    }

    return implode('', $chars);
}

function vms_test_public_calendar_resolve_current_view(
    bool $isLegacy,
    string $settingsDefaultView,
    string $shortcodeView,
    ?string $requestView,
    bool $isMobile,
    bool $setHeader,
    $header
): array {
    vms_test_public_calendar_reset_runtime();
    if ($requestView !== null) {
        $_GET['view'] = $requestView;
    }
    if ($setHeader) {
        $_SERVER['HTTP_USER_AGENT'] = $header;
    }
    $GLOBALS['vms_test_public_calendar_is_mobile'] = $isMobile;

    $defaultView = $isLegacy
        ? 'month'
        : bvmgr_public_calendar_normalize_view((string) $settingsDefaultView);

    $view = trim((string) $shortcodeView) !== ''
        ? bvmgr_public_calendar_normalize_view((string) $shortcodeView)
        : $defaultView;

    $requestedView = bvmgr_public_calendar_get_requested_view();
    if ($requestedView !== '') {
        $view = bvmgr_public_calendar_normalize_view($requestedView);
    }

    $userAgentResult = vms_test_public_calendar_capture_current_user_agent_from_globals();
    $userAgent = $userAgentResult['normalized'];

    $isTabletCalendarRequest = !$isLegacy && (
        wp_is_mobile()
        || strpos($userAgent, 'ipad') !== false
        || strpos($userAgent, 'tablet') !== false
        || strpos($userAgent, 'kindle') !== false
        || strpos($userAgent, 'silk/') !== false
        || strpos($userAgent, 'playbook') !== false
    );

    if ($view === 'auto') {
        $effectiveView = $isTabletCalendarRequest ? 'list' : 'month';
    } elseif ($isTabletCalendarRequest && $view === 'month') {
        $effectiveView = 'list';
    } else {
        $effectiveView = $view;
    }

    return array(
        'default_view' => $defaultView,
        'view' => $view,
        'requested_view' => $requestedView,
        'effective_view' => $effectiveView,
        'is_tablet_calendar_request' => $isTabletCalendarRequest,
        'user_agent' => $userAgent,
        'warnings' => $userAgentResult['warnings'],
    );
}

$pluginRoot = dirname(__DIR__);
$livePluginRoot = dirname(dirname($pluginRoot)) . '/vms';
$mirrorCalendarPath = $pluginRoot . '/includes/public/venue-calendar-shortcode.php';
$liveCalendarPath = $livePluginRoot . '/includes/public/venue-calendar-shortcode.php';
$mirrorRuntimeGuardsPath = $pluginRoot . '/includes/runtime-guards.php';
$liveRuntimeGuardsPath = $livePluginRoot . '/includes/runtime-guards.php';

$mirrorCalendarSource = (string) file_get_contents($mirrorCalendarPath);
$liveCalendarSource = (string) file_get_contents($liveCalendarPath);
$mirrorRuntimeGuardsSource = (string) file_get_contents($mirrorRuntimeGuardsPath);
$liveRuntimeGuardsSource = (string) file_get_contents($liveRuntimeGuardsPath);

vms_test_public_calendar_assert($mirrorCalendarSource !== '', 'Mirror public calendar source should be readable.');
vms_test_public_calendar_assert($liveCalendarSource !== '', 'Live public calendar source should be readable.');
vms_test_public_calendar_assert($mirrorRuntimeGuardsSource !== '', 'Mirror runtime guards source should be readable.');
vms_test_public_calendar_assert($liveRuntimeGuardsSource !== '', 'Live runtime guards source should be readable.');

$mirrorUaHelper = vms_test_public_calendar_extract_named_function($mirrorCalendarPath, 'bvmgr_public_calendar_get_request_user_agent');
$liveUaHelper = vms_test_public_calendar_extract_named_function($liveCalendarPath, 'bvmgr_public_calendar_get_request_user_agent');
$mirrorRequestedViewHelper = vms_test_public_calendar_extract_named_function($mirrorCalendarPath, 'bvmgr_public_calendar_get_requested_view');
$liveRequestedViewHelper = vms_test_public_calendar_extract_named_function($liveCalendarPath, 'bvmgr_public_calendar_get_requested_view');
$mirrorNormalizeViewHelper = vms_test_public_calendar_extract_named_function($mirrorCalendarPath, 'bvmgr_public_calendar_normalize_view');
$liveNormalizeViewHelper = vms_test_public_calendar_extract_named_function($liveCalendarPath, 'bvmgr_public_calendar_normalize_view');

vms_test_public_calendar_assert($mirrorUaHelper === $liveUaHelper, 'Mirror/live public calendar UA helpers should stay aligned.');
vms_test_public_calendar_assert($mirrorRequestedViewHelper === $liveRequestedViewHelper, 'Mirror/live requested-view helpers should stay aligned.');
vms_test_public_calendar_assert($mirrorNormalizeViewHelper === $liveNormalizeViewHelper, 'Mirror/live view-normalization helpers should stay aligned.');

foreach (
    array(
        'Mirror public calendar' => $mirrorCalendarSource,
        'Live public calendar' => $liveCalendarSource,
    ) as $label => $source
) {
    vms_test_public_calendar_assert_not_contains("\$_SERVER['HTTP_USER_AGENT']", $source, $label . ' should remove direct HTTP_USER_AGENT reads.');
    vms_test_public_calendar_assert_contains("vms_request_server_value('HTTP_USER_AGENT')", $source, $label . ' should source the request UA through the shared server-value helper.');
    vms_test_public_calendar_assert_contains('strtolower(sanitize_text_field($user_agent))', $source, $label . ' should preserve lowercase sanitize_text_field() normalization.');
    vms_test_public_calendar_assert_contains('vms_public_calendar_get_request_user_agent()', $source, $label . ' should preserve the local UA helper and its call site.');
    vms_test_public_calendar_assert_not_contains('vms_request_user_agent()', $source, $label . ' should not migrate to the capped shared UA helper.');
    vms_test_public_calendar_assert_contains("strpos(\$user_agent, 'ipad') !== false", $source, $label . ' should preserve the ipad marker.');
    vms_test_public_calendar_assert_contains("strpos(\$user_agent, 'tablet') !== false", $source, $label . ' should preserve the tablet marker.');
    vms_test_public_calendar_assert_contains("strpos(\$user_agent, 'kindle') !== false", $source, $label . ' should preserve the kindle marker.');
    vms_test_public_calendar_assert_contains("strpos(\$user_agent, 'silk/') !== false", $source, $label . ' should preserve the silk/ marker.');
    vms_test_public_calendar_assert_contains("strpos(\$user_agent, 'playbook') !== false", $source, $label . ' should preserve the playbook marker.');
    vms_test_public_calendar_assert_contains("trim((string) \$atts['view']) !== ''", $source, $label . ' should preserve shortcode-view precedence.');
    vms_test_public_calendar_assert_contains('$requested_view = vms_public_calendar_get_requested_view();', $source, $label . ' should preserve request-view precedence.');
    vms_test_public_calendar_assert_contains("if (\$view === 'auto') {", $source, $label . ' should preserve the auto-view branch.');
    vms_test_public_calendar_assert_contains("} elseif (\$is_tablet_calendar_request && \$view === 'month') {", $source, $label . ' should preserve month-to-list coercion on mobile/tablet.');
    vms_test_public_calendar_assert_contains('$base_args[\'view\'] = $view;', $source, $label . ' should preserve requested-view navigation state.');
    vms_test_public_calendar_assert_contains('data-vms-effective-view="<?php echo esc_attr($effective_view); ?>"', $source, $label . ' should preserve effective-view data attributes.');
    vms_test_public_calendar_assert_contains('vms-public-cal--view-<?php echo esc_attr($effective_view); ?>', $source, $label . ' should preserve effective-view wrapper classes.');
    vms_test_public_calendar_assert_contains('vms_public_calendar_render_list_view($events, $show_vendors, $show_images, $show_open_closed)', $source, $label . ' should preserve the list-view branch.');
    vms_test_public_calendar_assert_contains('vms_public_calendar_render_compact_view($rendered_months, $events, $state_venue_id, $show_open_closed)', $source, $label . ' should preserve the compact-view branch.');
    vms_test_public_calendar_assert_contains('vms_public_calendar_render_month_grid($month, $days, $day_states)', $source, $label . ' should preserve the month-grid branch.');
    vms_test_public_calendar_assert_contains('vms-public-cal-mobile-list-fallback', $source, $label . ' should preserve the hidden mobile list fallback.');
    vms_test_public_calendar_assert_contains('Mobile and tablet list view', $source, $label . ' should preserve the mobile-list accessibility text.');
    vms_test_public_calendar_assert_contains('Compact view shows up to three event-bearing months in weekend-focused chunks and skips empty months.', $source, $label . ' should preserve the compact-view note.');
}

foreach (
    array(
        'Mirror UA helper' => $mirrorUaHelper,
        'Live UA helper' => $liveUaHelper,
    ) as $label => $helperSource
) {
    vms_test_public_calendar_assert_not_contains("\$_SERVER['HTTP_USER_AGENT']", $helperSource, $label . ' should not access HTTP_USER_AGENT directly.');
    vms_test_public_calendar_assert_contains("vms_request_server_value('HTTP_USER_AGENT')", $helperSource, $label . ' should call the shared server-value helper.');
    vms_test_public_calendar_assert_contains('sanitize_text_field($user_agent)', $helperSource, $label . ' should retain sanitize_text_field().');
    vms_test_public_calendar_assert_contains('strtolower(sanitize_text_field($user_agent))', $helperSource, $label . ' should retain lowercase normalization.');
    vms_test_public_calendar_assert_not_contains('vms_request_user_agent()', $helperSource, $label . ' should not use the capped UA helper.');
    vms_test_public_calendar_assert_not_contains('substr(', $helperSource, $label . ' should not add a length cap.');
}

vms_test_public_calendar_assert_contains("return substr(sanitize_text_field(\$user_agent), 0, 255);", $mirrorRuntimeGuardsSource, 'Mirror runtime guards should preserve the capped shared UA helper outside this calendar path.');
vms_test_public_calendar_assert_contains("return substr(sanitize_text_field(\$user_agent), 0, 255);", $liveRuntimeGuardsSource, 'Live runtime guards should preserve the capped shared UA helper outside this calendar path.');
vms_test_public_calendar_assert_contains("vms_request_server_value('HTTP_USER_AGENT')", $mirrorRuntimeGuardsSource, 'Mirror runtime guards should preserve the helper-backed UA source.');
vms_test_public_calendar_assert_contains("vms_request_server_value('HTTP_USER_AGENT')", $liveRuntimeGuardsSource, 'Live runtime guards should preserve the helper-backed UA source.');

eval($mirrorRequestedViewHelper);
eval($mirrorUaHelper);
eval($mirrorNormalizeViewHelper);

$uaCases = array(
    'missing_header' => array(
        'set_header' => false,
        'header' => null,
        'expected_current' => '',
        'expected_warning_count' => 0,
    ),
    'empty_string' => array(
        'set_header' => true,
        'header' => '',
        'expected_current' => '',
        'expected_warning_count' => 0,
    ),
    'ordinary_browser' => array(
        'set_header' => true,
        'header' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        'expected_current' => 'mozilla/5.0 (windows nt 10.0; win64; x64)',
        'expected_warning_count' => 0,
    ),
    'mixed_case' => array(
        'set_header' => true,
        'header' => 'MoZiLLa/5.0 (IPad; CPU OS 17_0)',
        'expected_current' => 'mozilla/5.0 (ipad; cpu os 17_0)',
        'expected_warning_count' => 0,
    ),
    'leading_trailing_whitespace' => array(
        'set_header' => true,
        'header' => "  Tablet Device \n ",
        'expected_current' => 'tablet device',
        'expected_warning_count' => 0,
    ),
    'html_like_content' => array(
        'set_header' => true,
        'header' => '<b>Tablet</b><script>Playbook</script>',
        'expected_current' => 'tabletplaybook',
        'expected_warning_count' => 0,
    ),
    'control_characters' => array(
        'set_header' => true,
        'header' => "Tab\x00let\r\nDevice",
        'expected_current' => 'tablet device',
        'expected_warning_count' => 0,
    ),
    'quotes_and_slashes' => array(
        'set_header' => true,
        'header' => " Browser\\\\Slash \\\"Tablet\\\" ",
        'expected_current' => 'browser\\slash "tablet"',
        'expected_warning_count' => 0,
    ),
    'malformed_non_scalar_input' => array(
        'set_header' => true,
        'header' => array('tablet'),
        'expected_current' => '',
        'expected_warning_count' => 0,
    ),
    'exactly_255_bytes' => array(
        'set_header' => true,
        'header' => str_repeat('A', 255),
        'expected_current' => str_repeat('a', 255),
        'expected_warning_count' => 0,
    ),
    'two_hundred_fifty_six_bytes' => array(
        'set_header' => true,
        'header' => str_repeat('B', 256),
        'expected_current' => str_repeat('b', 256),
        'expected_warning_count' => 0,
    ),
    'substantially_longer_malformed_input' => array(
        'set_header' => true,
        'header' => str_repeat('A', 280) . '<b>TABLET</b>',
        'expected_current' => str_repeat('a', 280) . 'tablet',
        'expected_warning_count' => 0,
    ),
    'repeated_tracked_tokens' => array(
        'set_header' => true,
        'header' => 'tablet start ' . str_repeat('x', 240) . ' TABLET',
        'expected_current' => 'tablet start ' . str_repeat('x', 240) . ' tablet',
        'expected_warning_count' => 0,
    ),
);

foreach ($uaCases as $label => $case) {
    $current = vms_test_public_calendar_capture_current_user_agent($case['set_header'], $case['header']);
    $expected = vms_test_public_calendar_expected_user_agent($case['set_header'], $case['header']);

    vms_test_public_calendar_assert(
        $current['normalized'] === $case['expected_current'],
        $label . ' should preserve the accepted UA normalization output. Got ' . json_encode($current['normalized']) . '.'
    );
    vms_test_public_calendar_assert(
        count($current['warnings']) === $case['expected_warning_count'],
        $label . ' should preserve the accepted warning count. Got ' . count($current['warnings']) . '.'
    );
    vms_test_public_calendar_assert(
        $expected === $case['expected_current'],
        $label . ' should preserve the modeled shared-helper output. Got ' . json_encode($expected) . '.'
    );
}

$malformedWarnings = vms_test_public_calendar_capture_current_user_agent(true, array('tablet'))['warnings'];
vms_test_public_calendar_assert(
    $malformedWarnings === array(),
    'Malformed non-scalar UA input should normalize to an empty string without warnings.'
);

$trackedTokens = array('ipad', 'tablet', 'kindle', 'silk/', 'playbook');
foreach ($trackedTokens as $token) {
    $beforePayload = str_repeat('a', 12) . strtoupper($token) . '-tail';
    $endAt255Payload = str_repeat('a', 255 - strlen($token)) . strtoupper($token);
    $startAt255Payload = str_repeat('a', 255) . strtoupper($token);
    $after255Payload = str_repeat('a', 256) . strtoupper($token);
    $repeatedPayload = strtoupper($token) . str_repeat('x', 260) . strtoupper($token);
    $mixedCasePayload = 'noise-' . vms_test_public_calendar_mixed_case($token) . '-tail';

    $matrix = array(
        'before_255' => array('payload' => $beforePayload, 'expected' => true),
        'ends_at_255' => array('payload' => $endAt255Payload, 'expected' => true),
        'starts_at_255' => array('payload' => $startAt255Payload, 'expected' => true),
        'starts_after_255' => array('payload' => $after255Payload, 'expected' => true),
        'repeated' => array('payload' => $repeatedPayload, 'expected' => true),
        'mixed_case' => array('payload' => $mixedCasePayload, 'expected' => true),
    );

    foreach ($matrix as $caseLabel => $row) {
        $current = vms_test_public_calendar_capture_current_user_agent(true, $row['payload']);
        $expected = vms_test_public_calendar_expected_user_agent(true, $row['payload']);
        $currentDetected = vms_test_public_calendar_detects_token($current['normalized'], $token);
        $expectedDetected = vms_test_public_calendar_detects_token($expected, $token);

        $context = json_encode(
            array(
                'token' => $token,
                'case' => $caseLabel,
                'current_normalized' => $current['normalized'],
                'expected_normalized' => $expected,
                'current_detected' => $currentDetected,
                'expected_detected' => $expectedDetected,
            ),
            JSON_UNESCAPED_SLASHES
        );

        vms_test_public_calendar_assert(
            $currentDetected === $row['expected'],
            'Accepted token detection should stay characterized for ' . $context
        );
        vms_test_public_calendar_assert(
            $expectedDetected === $row['expected'],
            'Modeled shared-helper token detection should stay characterized for ' . $context
        );
    }
}

$legacyDefault = vms_test_public_calendar_resolve_current_view(true, 'auto', '', null, false, false, null);
vms_test_public_calendar_assert(
    $legacyDefault['default_view'] === 'month' && $legacyDefault['view'] === 'month' && $legacyDefault['effective_view'] === 'month',
    'Legacy mode should preserve its current month default when no overrides are present.'
);

$shortcodeOverridesSettings = vms_test_public_calendar_resolve_current_view(false, 'list', 'month', null, false, false, null);
vms_test_public_calendar_assert(
    $shortcodeOverridesSettings['default_view'] === 'list' && $shortcodeOverridesSettings['view'] === 'month' && $shortcodeOverridesSettings['effective_view'] === 'month',
    'Shortcode view attributes should override settings before mobile/tablet coercion.'
);

$requestOverridesBoth = vms_test_public_calendar_resolve_current_view(false, 'month', 'compact', 'list', false, false, null);
vms_test_public_calendar_assert(
    $requestOverridesBoth['requested_view'] === 'list' && $requestOverridesBoth['view'] === 'list' && $requestOverridesBoth['effective_view'] === 'list',
    'Request/query view should override settings and shortcode view.'
);

$autoDesktop = vms_test_public_calendar_resolve_current_view(false, 'auto', '', null, false, false, null);
vms_test_public_calendar_assert(
    $autoDesktop['view'] === 'auto' && $autoDesktop['effective_view'] === 'month' && !$autoDesktop['is_tablet_calendar_request'],
    'Auto view should resolve to month on non-mobile, non-tablet requests.'
);

$autoMobile = vms_test_public_calendar_resolve_current_view(false, 'auto', '', null, true, false, null);
vms_test_public_calendar_assert(
    $autoMobile['view'] === 'auto' && $autoMobile['effective_view'] === 'list' && $autoMobile['is_tablet_calendar_request'],
    'Auto view should resolve to list on mobile requests.'
);

$autoLateTabletToken = vms_test_public_calendar_resolve_current_view(false, 'auto', '', null, false, true, str_repeat('a', 256) . 'TABLET');
vms_test_public_calendar_assert(
    $autoLateTabletToken['view'] === 'auto' && $autoLateTabletToken['effective_view'] === 'list' && $autoLateTabletToken['is_tablet_calendar_request'],
    'Auto view should preserve list coercion when the tablet marker appears after byte 255.'
);

$explicitMonthTablet = vms_test_public_calendar_resolve_current_view(false, 'auto', 'month', null, true, false, null);
vms_test_public_calendar_assert(
    $explicitMonthTablet['view'] === 'month' && $explicitMonthTablet['effective_view'] === 'list',
    'Explicit month view should preserve list coercion on mobile/tablet requests.'
);

$explicitListMobile = vms_test_public_calendar_resolve_current_view(false, 'auto', 'list', null, true, false, null);
vms_test_public_calendar_assert(
    $explicitListMobile['view'] === 'list' && $explicitListMobile['effective_view'] === 'list',
    'Explicit list view should remain list on mobile/tablet requests.'
);

$explicitCompactMobile = vms_test_public_calendar_resolve_current_view(false, 'auto', 'compact', null, true, false, null);
vms_test_public_calendar_assert(
    $explicitCompactMobile['view'] === 'compact' && $explicitCompactMobile['effective_view'] === 'compact',
    'Explicit compact view should remain compact on mobile/tablet requests.'
);

$requestedVersusEffective = vms_test_public_calendar_resolve_current_view(false, 'auto', '', 'month', true, false, null);
vms_test_public_calendar_assert(
    $requestedVersusEffective['requested_view'] === 'month'
    && $requestedVersusEffective['view'] === 'month'
    && $requestedVersusEffective['effective_view'] === 'list',
    'Requested month view should stay distinct from the effective list rendering on mobile/tablet requests.'
);

$requestSanitization = vms_test_public_calendar_resolve_current_view(false, 'auto', '', ' COMPACT ', false, false, null);
vms_test_public_calendar_assert(
    $requestSanitization['requested_view'] === 'compact'
    && $requestSanitization['view'] === 'compact'
    && $requestSanitization['effective_view'] === 'compact',
    'Requested view should preserve current sanitize_key() normalization.'
);

fwrite(STDOUT, "Public calendar UA view characterization OK.\n");
