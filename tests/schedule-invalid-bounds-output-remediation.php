<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

$GLOBALS['vms_test_actions'] = array();
$GLOBALS['vms_test_runtime_calls'] = array(
    'get_option' => 0,
    'get_posts' => 0,
    'get_post_meta' => 0,
    'current_user_can' => 0,
    'admin_url' => 0,
    'add_query_arg' => 0,
    'remove_query_arg' => 0,
    'wp_create_nonce' => 0,
    'current_time' => 0,
    'get_post_status' => 0,
    'get_the_title' => 0,
    'get_edit_post_link' => 0,
    'vms_sch_get_holidays_for_date' => 0,
    'vms_sch_holiday_forces_open' => 0,
    'vms_sch_season_get_blackout_notes_map' => 0,
    'vms_get_all_venue_ids' => 0,
);
$GLOBALS['vms_test_mutation_calls'] = array(
    'update_option' => 0,
    'update_post_meta' => 0,
    'delete_post_meta' => 0,
);

if (!function_exists('add_action')) {
    function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
    {
        $GLOBALS['vms_test_actions'][] = array(
            'hook' => $hook,
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $accepted_args,
        );
        return true;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters(string $hook, $value, ...$args)
    {
        unset($hook, $args);
        return $value;
    }
}

if (!function_exists('__')) {
    function __(string $text, string $domain = ''): string
    {
        unset($domain);
        return $text;
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url): string
    {
        return htmlspecialchars((string) $url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $sanitized = preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string) $value));
        return is_string($sanitized) ? trim($sanitized) : '';
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $sanitized = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value));
        return is_string($sanitized) ? $sanitized : '';
    }
}

if (!function_exists('selected')) {
    function selected($selected, $current = true, bool $display = true): string
    {
        unset($display);
        return (string) $selected === (string) $current ? ' selected="selected"' : '';
    }
}

if (!function_exists('checked')) {
    function checked($checked, $current = true, bool $display = true): string
    {
        unset($display);
        return (string) $checked === (string) $current ? ' checked="checked"' : '';
    }
}

if (!function_exists('absint')) {
    function absint($value): int
    {
        return abs((int) $value);
    }
}

if (!function_exists('wp_kses')) {
    function wp_kses(string $content, array $allowed_html, array $allowed_protocols = array()): string
    {
        unset($allowed_html, $allowed_protocols);
        return $content;
    }
}

if (!function_exists('get_option')) {
    function get_option(string $option, $default = false)
    {
        $GLOBALS['vms_test_runtime_calls']['get_option']++;
        if ($option === 'vms_settings') {
            return array(
                'sch_hide_past_default' => 0,
            );
        }
        return $default;
    }
}

if (!function_exists('get_posts')) {
    function get_posts(array $args = array()): array
    {
        unset($args);
        $GLOBALS['vms_test_runtime_calls']['get_posts']++;
        return array();
    }
}

if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, string $key = '', bool $single = false)
    {
        unset($post_id, $key, $single);
        $GLOBALS['vms_test_runtime_calls']['get_post_meta']++;
        return '';
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can(string $capability, ...$args): bool
    {
        unset($capability, $args);
        $GLOBALS['vms_test_runtime_calls']['current_user_can']++;
        return true;
    }
}

if (!function_exists('admin_url')) {
    function admin_url(string $path = ''): string
    {
        $GLOBALS['vms_test_runtime_calls']['admin_url']++;
        return 'https://example.test/wp-admin/' . ltrim($path, '/');
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg($args = null, $url = null): string
    {
        $GLOBALS['vms_test_runtime_calls']['add_query_arg']++;
        $base = (is_string($url) && $url !== '')
            ? $url
            : 'https://example.test/wp-admin/admin.php?page=vms-schedule';

        if ($args === null) {
            return $base;
        }

        $parts = parse_url($base);
        $query = array();
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        foreach ((array) $args as $key => $value) {
            $query[(string) $key] = (string) $value;
        }

        $rebuilt = '';
        if (!empty($parts['scheme'])) {
            $rebuilt .= $parts['scheme'] . '://';
        }
        if (!empty($parts['host'])) {
            $rebuilt .= $parts['host'];
        }
        if (!empty($parts['path'])) {
            $rebuilt .= $parts['path'];
        }
        if ($query !== array()) {
            $rebuilt .= '?' . http_build_query($query);
        }

        return $rebuilt;
    }
}

if (!function_exists('remove_query_arg')) {
    function remove_query_arg($keys, $url = null): string
    {
        $GLOBALS['vms_test_runtime_calls']['remove_query_arg']++;
        $base = (is_string($url) && $url !== '')
            ? $url
            : 'https://example.test/wp-admin/admin.php?page=vms-schedule';
        $parts = parse_url($base);
        $query = array();
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        foreach ((array) $keys as $key) {
            unset($query[(string) $key]);
        }

        $rebuilt = '';
        if (!empty($parts['scheme'])) {
            $rebuilt .= $parts['scheme'] . '://';
        }
        if (!empty($parts['host'])) {
            $rebuilt .= $parts['host'];
        }
        if (!empty($parts['path'])) {
            $rebuilt .= $parts['path'];
        }
        if ($query !== array()) {
            $rebuilt .= '?' . http_build_query($query);
        }

        return $rebuilt;
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce(string $action = ''): string
    {
        unset($action);
        $GLOBALS['vms_test_runtime_calls']['wp_create_nonce']++;
        return 'schedule-test-nonce';
    }
}

if (!function_exists('current_time')) {
    function current_time(string $type, bool $gmt = false)
    {
        unset($gmt);
        $GLOBALS['vms_test_runtime_calls']['current_time']++;
        if ($type === 'Y-m-01') {
            return '2026-07-01';
        }
        if ($type === 'Y-m-t') {
            return '2026-07-31';
        }
        return '2026-07-17';
    }
}

if (!function_exists('wp_timezone')) {
    function wp_timezone(): DateTimeZone
    {
        return new DateTimeZone('UTC');
    }
}

if (!function_exists('wp_date')) {
    function wp_date(string $format, $timestamp = null, ?DateTimeZone $timezone = null): string
    {
        $timestamp = is_numeric($timestamp) ? (int) $timestamp : strtotime('2026-07-17 00:00:00 UTC');
        $timezone = $timezone instanceof DateTimeZone ? $timezone : wp_timezone();
        $dt = new DateTimeImmutable('@' . $timestamp);
        return $dt->setTimezone($timezone)->format($format);
    }
}

if (!function_exists('get_post_status')) {
    function get_post_status($post): string
    {
        unset($post);
        $GLOBALS['vms_test_runtime_calls']['get_post_status']++;
        return 'publish';
    }
}

if (!function_exists('get_the_title')) {
    function get_the_title($post = 0): string
    {
        unset($post);
        $GLOBALS['vms_test_runtime_calls']['get_the_title']++;
        return 'Example Venue';
    }
}

if (!function_exists('get_edit_post_link')) {
    function get_edit_post_link($post = 0, $context = 'display')
    {
        unset($post, $context);
        $GLOBALS['vms_test_runtime_calls']['get_edit_post_link']++;
        return 'https://example.test/wp-admin/post.php?post=1&action=edit';
    }
}

if (!function_exists('update_option')) {
    function update_option(string $option, $value, $autoload = null): bool
    {
        unset($option, $value, $autoload);
        $GLOBALS['vms_test_mutation_calls']['update_option']++;
        return true;
    }
}

if (!function_exists('update_post_meta')) {
    function update_post_meta($post_id, $meta_key, $meta_value, $prev_value = ''): bool
    {
        unset($post_id, $meta_key, $meta_value, $prev_value);
        $GLOBALS['vms_test_mutation_calls']['update_post_meta']++;
        return true;
    }
}

if (!function_exists('delete_post_meta')) {
    function delete_post_meta($post_id, $meta_key, $meta_value = ''): bool
    {
        unset($post_id, $meta_key, $meta_value);
        $GLOBALS['vms_test_mutation_calls']['delete_post_meta']++;
        return true;
    }
}

if (!function_exists('vms_get_all_venue_ids')) {
    function vms_get_all_venue_ids(): array
    {
        $GLOBALS['vms_test_runtime_calls']['vms_get_all_venue_ids']++;
        return array();
    }
}

if (!function_exists('vms_sch_get_holidays_for_date')) {
    function vms_sch_get_holidays_for_date(int $venue_id, string $ymd): array
    {
        unset($venue_id, $ymd);
        $GLOBALS['vms_test_runtime_calls']['vms_sch_get_holidays_for_date']++;
        return array();
    }
}

if (!function_exists('vms_sch_holiday_forces_open')) {
    function vms_sch_holiday_forces_open(int $venue_id, string $ymd): bool
    {
        unset($venue_id, $ymd);
        $GLOBALS['vms_test_runtime_calls']['vms_sch_holiday_forces_open']++;
        return false;
    }
}

if (!function_exists('vms_sch_season_get_blackout_notes_map')) {
    function vms_sch_season_get_blackout_notes_map(int $venue_id, string $from_ymd, string $to_ymd): array
    {
        unset($venue_id, $from_ymd, $to_ymd);
        $GLOBALS['vms_test_runtime_calls']['vms_sch_season_get_blackout_notes_map']++;
        return array();
    }
}

require_once dirname(__DIR__) . '/includes/admin/schedule.php';

$assert = static function (bool $condition, string $message): void {
    if ($condition) {
        return;
    }

    throw new RuntimeException($message);
};

$assertSame = static function ($expected, $actual, string $message) use ($assert): void {
    $assert($expected === $actual, $message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . '.');
};

$captureOutput = static function (callable $callback): string {
    ob_start();
    $callback();
    return (string) ob_get_clean();
};

$snapshotCounters = static function (): array {
    return array(
        'runtime_calls' => $GLOBALS['vms_test_runtime_calls'],
        'mutation_calls' => $GLOBALS['vms_test_mutation_calls'],
    );
};

$functionSource = static function (string $function_name): string {
    $reflection = new ReflectionFunction($function_name);
    $lines = file($reflection->getFileName());
    if ($lines === false) {
        throw new RuntimeException('Unable to read function source for ' . $function_name . '.');
    }

    return implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
};

$parseWrappedHtml = static function (string $html, string $label): DOMDocument {
    $document = new DOMDocument('1.0', 'UTF-8');
    $wrapped = '<div id="vms-root">' . $html . '</div>';
    $loaded = @$document->loadHTML('<?xml encoding="utf-8" ?>' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    if (!$loaded) {
        throw new RuntimeException('Failed to parse ' . $label . '.');
    }

    return $document;
};

try {
    $pluginRoot = dirname(__DIR__);
    $scheduleSource = file_get_contents($pluginRoot . '/includes/admin/schedule.php');
    $assert(is_string($scheduleSource) && $scheduleSource !== '', 'Schedule source should be readable.');

    $expectedNoticeHtml = '<div class="notice notice-error"><p>Schedule window bounds were invalid.</p></div>';

    $assert(function_exists('vms_schedule_get_invalid_bounds_notice_context'), 'Schedule should define the invalid-bounds notice context builder.');
    $assert(function_exists('vms_schedule_render_invalid_bounds_notice'), 'Schedule should define the invalid-bounds notice renderer.');
    $assert(function_exists('vms_schedule_get_unpublished_venue_notice_context'), 'Schedule should define the unpublished-venue notice context builder.');
    $assert(function_exists('vms_schedule_render_unpublished_venue_notice'), 'Schedule should define the unpublished-venue notice renderer.');
    $assert(function_exists('vms_render_schedule_list_view'), 'Schedule should still define the selected-venue list view renderer.');
    $assert(function_exists('vms_render_schedule_calendar_view'), 'Schedule should still define the selected-venue calendar view renderer.');
    $assert(function_exists('vms_render_schedule_list_view_all'), 'Schedule should still define the all-venues list view renderer.');
    $assert(function_exists('vms_render_schedule_calendar_view_all'), 'Schedule should still define the all-venues calendar view renderer.');

    $builderSource = $functionSource('vms_schedule_get_invalid_bounds_notice_context');
    $rendererSource = $functionSource('vms_schedule_render_invalid_bounds_notice');
    $selectedListSource = $functionSource('vms_render_schedule_list_view');
    $selectedCalendarSource = $functionSource('vms_render_schedule_calendar_view');
    $allListSource = $functionSource('vms_render_schedule_list_view_all');
    $allCalendarSource = $functionSource('vms_render_schedule_calendar_view_all');

    $assert(strpos($builderSource, "'show' => \$show") !== false, 'Schedule invalid-bounds builder should return only the finite show flag.');
    $assert(strpos($builderSource, 'vms_sch_parse_ymd(') === false && strpos($builderSource, 'strtotime(') === false, 'Schedule invalid-bounds builder should not perform parsing.');
    $assert(strpos($builderSource, 'get_option(') === false && strpos($builderSource, 'get_posts(') === false && strpos($builderSource, 'get_post_meta(') === false && strpos($builderSource, 'current_user_can(') === false, 'Schedule invalid-bounds builder should not perform provider or capability reads.');
    $assert(strpos($builderSource, 'admin_url(') === false && strpos($builderSource, 'get_edit_post_link(') === false && strpos($builderSource, 'wp_create_nonce(') === false, 'Schedule invalid-bounds builder should not perform URL or nonce work.');
    $assert(strpos($builderSource, 'update_option(') === false && strpos($builderSource, 'update_post_meta(') === false && strpos($builderSource, 'delete_post_meta(') === false, 'Schedule invalid-bounds builder should not perform mutations.');

    $assert(strpos($rendererSource, $expectedNoticeHtml) !== false, 'Schedule invalid-bounds renderer should preserve the exact finite fragment.');
    $assert(strpos($rendererSource, 'esc_html__(') === false && strpos($rendererSource, '__(') === false, 'Schedule invalid-bounds renderer should preserve the lack of a translation wrapper.');
    $assert(strpos($rendererSource, 'vms_sch_parse_ymd(') === false && strpos($rendererSource, 'strtotime(') === false, 'Schedule invalid-bounds renderer should not perform parsing.');
    $assert(strpos($rendererSource, 'get_option(') === false && strpos($rendererSource, 'get_posts(') === false && strpos($rendererSource, 'get_post_meta(') === false && strpos($rendererSource, 'current_user_can(') === false, 'Schedule invalid-bounds renderer should not perform provider or capability reads.');
    $assert(strpos($rendererSource, 'admin_url(') === false && strpos($rendererSource, 'get_edit_post_link(') === false && strpos($rendererSource, 'wp_create_nonce(') === false, 'Schedule invalid-bounds renderer should not perform URL or nonce work.');
    $assert(strpos($rendererSource, 'update_option(') === false && strpos($rendererSource, 'update_post_meta(') === false && strpos($rendererSource, 'delete_post_meta(') === false, 'Schedule invalid-bounds renderer should not perform mutations.');

    $assert(substr_count($scheduleSource, $expectedNoticeHtml) === 1, 'Schedule should keep exactly one direct invalid-bounds fragment source after normalization.');
    $assert(substr_count($scheduleSource, 'vms_schedule_render_invalid_bounds_notice(') === 5, 'Schedule should use the invalid-bounds renderer exactly four times plus its own declaration.');
    $assert(substr_count($scheduleSource, 'vms_schedule_render_unpublished_venue_notice($unpublished_notice_context);') === 2, 'Schedule should keep routing the richer unpublished-venue branches through their separate shared renderer.');
    $assert(strpos($scheduleSource, "'notices_callback' =>") === false, 'Schedule invalid-bounds output should remain content-local and outside the Administrator shell.');

    $invalidBranchPattern = '~if\s*\(\s*!empty\(\$invalid_bounds_notice_context\[\'show\'\]\)\s*\)\s*\{\s*vms_schedule_render_invalid_bounds_notice\(\$invalid_bounds_notice_context\);\s*return;\s*\}~s';
    foreach (array(
        'selected list' => $selectedListSource,
        'selected calendar' => $selectedCalendarSource,
        'all list' => $allListSource,
        'all calendar' => $allCalendarSource,
    ) as $label => $source) {
        $assert(preg_match($invalidBranchPattern, $source) === 1, 'Schedule ' . $label . ' invalid branch should render through the shared renderer and return immediately.');
    }

    $assert(strpos($selectedListSource, '$start_dt = vms_sch_parse_ymd($start_ymd);') !== false && strpos($selectedListSource, '$end_dt   = vms_sch_parse_ymd($end_ymd);') !== false, 'Selected-venue list view should preserve its vms_sch_parse_ymd() validation path.');
    $assert(strpos($selectedListSource, 'vms_schedule_get_invalid_bounds_notice_context(!$start_dt || !$end_dt);') !== false, 'Selected-venue list view should preserve the exact invalid-bounds condition.');
    $assert(strpos($selectedCalendarSource, '$start_ts = strtotime($start_ymd);') !== false && strpos($selectedCalendarSource, '$end_ts   = strtotime($end_ymd);') !== false, 'Selected-venue calendar view should preserve its strtotime() validation path.');
    $assert(strpos($selectedCalendarSource, 'vms_schedule_get_invalid_bounds_notice_context(!$start_ts || !$end_ts);') !== false, 'Selected-venue calendar view should preserve the exact invalid-bounds condition.');
    $assert(strpos($allListSource, '$start_dt = vms_sch_parse_ymd($start_ymd);') !== false && strpos($allListSource, '$end_dt   = vms_sch_parse_ymd($end_ymd);') !== false, 'All-venues list view should preserve its vms_sch_parse_ymd() validation path.');
    $assert(strpos($allListSource, 'vms_schedule_get_invalid_bounds_notice_context(!$start_dt || !$end_dt);') !== false, 'All-venues list view should preserve the exact invalid-bounds condition.');
    $assert(strpos($allCalendarSource, '$start_dt = vms_sch_parse_ymd($start_ymd);') !== false && strpos($allCalendarSource, '$end_dt   = vms_sch_parse_ymd($end_ymd);') !== false, 'All-venues calendar view should preserve its existing vms_sch_parse_ymd() validation path.');
    $assert(strpos($allCalendarSource, 'vms_schedule_get_invalid_bounds_notice_context(!$start_dt || !$end_dt);') !== false, 'All-venues calendar view should preserve the exact invalid-bounds condition.');

    $assert(strpos($scheduleSource, 'Select a venue to view its schedule.') !== false, 'Schedule should preserve the untouched no-selection warning family.');
    $assert(strpos($scheduleSource, 'No venues found to display.') !== false, 'Schedule should preserve the untouched no-venues warning family.');
    $assert(strpos($scheduleSource, 'Action required:') !== false && strpos($scheduleSource, 'Venue is not published:') !== false, 'Schedule should preserve the untouched richer unpublished-venue notice families.');
    $assert(strpos($rendererSource, 'Select a venue to view its schedule.') === false && strpos($rendererSource, 'No venues found to display.') === false && strpos($rendererSource, 'Action required:') === false && strpos($rendererSource, 'Venue is not published:') === false, 'Schedule invalid-bounds renderer should remain isolated from other Schedule notice families.');

    $visibleContext = vms_schedule_get_invalid_bounds_notice_context(true);
    $hiddenContext = vms_schedule_get_invalid_bounds_notice_context(false);
    $assertSame(array('show'), array_keys($visibleContext), 'Schedule invalid-bounds builder should return only the finite show key.');
    $assertSame(array('show' => true), $visibleContext, 'Schedule invalid-bounds builder should preserve visible context exactly.');
    $assertSame(array('show' => false), $hiddenContext, 'Schedule invalid-bounds builder should preserve hidden context exactly.');

    $beforeHiddenRenderer = $snapshotCounters();
    $hiddenHtml = $captureOutput(static function (): void {
        vms_schedule_render_invalid_bounds_notice(array('show' => false));
    });
    $afterHiddenRenderer = $snapshotCounters();
    $assertSame('', $hiddenHtml, 'Schedule invalid-bounds renderer should emit nothing for hidden context.');
    $assertSame($beforeHiddenRenderer, $afterHiddenRenderer, 'Schedule invalid-bounds renderer should not perform reads or mutations for hidden context.');

    $beforeVisibleRenderer = $snapshotCounters();
    $visibleHtml = $captureOutput(static function (): void {
        vms_schedule_render_invalid_bounds_notice(array(
            'show' => true,
            'message' => '</p><script>alert(1)</script><p>',
            'class' => 'notice notice-danger',
            'extra' => '<strong>ignored</strong>',
        ));
    });
    $afterVisibleRenderer = $snapshotCounters();
    $assertSame($expectedNoticeHtml, $visibleHtml, 'Schedule invalid-bounds renderer should emit the exact finite fragment for visible context.');
    $assertSame($beforeVisibleRenderer, $afterVisibleRenderer, 'Schedule invalid-bounds renderer should not perform reads or mutations for visible context.');
    $assert(strpos($visibleHtml, '<script>') === false && strpos($visibleHtml, '<strong>') === false && strpos($visibleHtml, 'notice-danger') === false, 'Schedule invalid-bounds renderer should ignore malformed context markup and attributes.');

    $rendererDocument = $parseWrappedHtml($visibleHtml, 'Schedule invalid-bounds renderer output');
    $rendererXPath = new DOMXPath($rendererDocument);
    $rendererRoot = $rendererXPath->query('//*[@id="vms-root"]')->item(0);
    $assert($rendererRoot instanceof DOMElement, 'Schedule invalid-bounds renderer DOM root should exist.');
    $assert($rendererRoot->childNodes->length === 1, 'Schedule invalid-bounds renderer output should contain exactly one outer node.');
    $noticeNode = $rendererRoot->firstChild;
    $assert($noticeNode instanceof DOMElement && $noticeNode->tagName === 'div', 'Schedule invalid-bounds renderer output should use one outer div.');
    $assertSame('notice notice-error', $noticeNode->getAttribute('class'), 'Schedule invalid-bounds renderer output should preserve the exact class contract.');
    $assert($noticeNode->attributes->length === 1, 'Schedule invalid-bounds renderer output should not introduce extra attributes.');
    $paragraphNodes = $noticeNode->getElementsByTagName('p');
    $assert($paragraphNodes->length === 1, 'Schedule invalid-bounds renderer output should contain exactly one paragraph.');
    $paragraphNode = $paragraphNodes->item(0);
    $assert($paragraphNode instanceof DOMElement && $paragraphNode->parentNode === $noticeNode, 'Schedule invalid-bounds renderer output should keep one direct paragraph child.');
    $assertSame('Schedule window bounds were invalid.', trim((string) $paragraphNode->textContent), 'Schedule invalid-bounds renderer output should preserve the exact visible message.');
    $assert($noticeNode->getElementsByTagName('*')->length === 1, 'Schedule invalid-bounds renderer output should contain no elements beyond the direct paragraph.');

    $invalidCases = array(
        'selected list' => array(
            'render' => static function (): void {
                vms_render_schedule_list_view(7, 'invalid-start', '2026-07-17', array(), array(), '2026-07-17', '2026-07-17');
            },
            'body_markers' => array('vms-sch-past-toggle', 'vms-sch-list'),
        ),
        'selected calendar' => array(
            'render' => static function (): void {
                vms_render_schedule_calendar_view(7, 'invalid-start', '2026-07-17', array(), array(), '2026-07-17', '2026-07-17');
            },
            'body_markers' => array('vms-panel-month', 'data-vms-scope="single"'),
        ),
        'all list' => array(
            'render' => static function (): void {
                vms_render_schedule_list_view_all('invalid-start', '2026-07-17', array(), array());
            },
            'body_markers' => array('vms-sch-list-all', 'vms-col-venue'),
        ),
        'all calendar' => array(
            'render' => static function (): void {
                vms_render_schedule_calendar_view_all('invalid-start', '2026-07-17', array(), array());
            },
            'body_markers' => array('vms-av-method vms-sch-month', 'data-vms-scope="all"'),
        ),
    );
    foreach ($invalidCases as $label => $case) {
        $invalidHtml = $captureOutput($case['render']);
        $assertSame($expectedNoticeHtml, $invalidHtml, 'Schedule ' . $label . ' invalid-bounds branch should emit only the shared notice fragment.');
        foreach ($case['body_markers'] as $marker) {
            $assert(strpos($invalidHtml, $marker) === false, 'Schedule ' . $label . ' invalid-bounds branch should return before view-body markup appears.');
        }
    }

    $validSelectedListHtml = $captureOutput(static function (): void {
        vms_render_schedule_list_view(7, '2026-07-17', '2026-07-17', array(), array(), '2026-07-17', '2026-07-17');
    });
    $assert(strpos($validSelectedListHtml, 'vms-sch-past-toggle') !== false && strpos($validSelectedListHtml, 'vms-sch-list') !== false, 'Selected-venue list view should continue into the existing body path for valid bounds.');

    $validSelectedCalendarHtml = $captureOutput(static function (): void {
        vms_render_schedule_calendar_view(7, '2026-07-17', '2026-07-17', array(), array(), '2026-07-17', '2026-07-17');
    });
    $assert(strpos($validSelectedCalendarHtml, 'vms-panel-month') !== false && strpos($validSelectedCalendarHtml, 'data-vms-scope="single"') !== false, 'Selected-venue calendar view should continue into the existing body path for valid bounds.');

    $validAllListHtml = $captureOutput(static function (): void {
        vms_render_schedule_list_view_all('2026-07-17', '2026-07-17', array(), array());
    });
    $assert(strpos($validAllListHtml, 'vms-sch-list-all') !== false && strpos($validAllListHtml, 'vms-col-venue') !== false, 'All-venues list view should continue into the existing body path for valid bounds.');

    $validAllCalendarHtml = $captureOutput(static function (): void {
        vms_render_schedule_calendar_view_all('2026-07-17', '2026-07-17', array(), array());
    });
    $assert(strpos($validAllCalendarHtml, 'vms-av-method vms-sch-month') !== false && strpos($validAllCalendarHtml, 'data-vms-scope="all"') !== false, 'All-venues calendar view should continue into the existing body path for valid bounds.');

    fwrite(STDOUT, "schedule invalid bounds output remediation: PASS\n");
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
