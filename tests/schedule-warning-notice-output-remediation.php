<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

$GLOBALS['vms_test_runtime_calls'] = array(
    'current_user_can' => 0,
    'get_current_user_id' => 0,
    'get_user_meta' => 0,
    'metadata_exists' => 0,
    'add_query_arg' => 0,
    'remove_query_arg' => 0,
    'get_post_type' => 0,
    'get_post_status' => 0,
    'vms_sch_get_schedule_venue_candidates' => 0,
    'vms_sch_pick_single_venue_candidate' => 0,
    'vms_sch_get_all_venue_ids' => 0,
);
$GLOBALS['vms_test_mutation_calls'] = array(
    'update_user_meta' => 0,
    'update_option' => 0,
    'update_post_meta' => 0,
    'delete_post_meta' => 0,
);
$GLOBALS['vms_test_user_id'] = 77;
$GLOBALS['vms_test_user_meta'] = array();
$GLOBALS['vms_test_schedule_venue_candidates'] = array();
$GLOBALS['vms_test_all_venue_ids'] = array();

if (!function_exists('add_action')) {
    function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
    {
        unset($hook, $callback, $priority, $accepted_args);
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

if (!function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = ''): string
    {
        unset($domain);
        return esc_html($text);
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

if (!function_exists('current_user_can')) {
    function current_user_can(string $capability, ...$args): bool
    {
        unset($capability, $args);
        $GLOBALS['vms_test_runtime_calls']['current_user_can']++;
        return true;
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id(): int
    {
        $GLOBALS['vms_test_runtime_calls']['get_current_user_id']++;
        return (int) $GLOBALS['vms_test_user_id'];
    }
}

if (!function_exists('get_user_meta')) {
    function get_user_meta(int $user_id, string $key = '', bool $single = false)
    {
        unset($single);
        $GLOBALS['vms_test_runtime_calls']['get_user_meta']++;
        return $GLOBALS['vms_test_user_meta'][$user_id][$key] ?? '';
    }
}

if (!function_exists('update_user_meta')) {
    function update_user_meta(int $user_id, string $meta_key, $meta_value, $prev_value = ''): bool
    {
        unset($prev_value);
        $GLOBALS['vms_test_mutation_calls']['update_user_meta']++;
        if (!isset($GLOBALS['vms_test_user_meta'][$user_id]) || !is_array($GLOBALS['vms_test_user_meta'][$user_id])) {
            $GLOBALS['vms_test_user_meta'][$user_id] = array();
        }
        $GLOBALS['vms_test_user_meta'][$user_id][$meta_key] = (string) $meta_value;
        return true;
    }
}

if (!function_exists('metadata_exists')) {
    function metadata_exists(string $meta_type, int $object_id, string $meta_key): bool
    {
        unset($meta_type);
        $GLOBALS['vms_test_runtime_calls']['metadata_exists']++;
        return isset($GLOBALS['vms_test_user_meta'][$object_id]) && array_key_exists($meta_key, (array) $GLOBALS['vms_test_user_meta'][$object_id]);
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

if (!function_exists('get_post_type')) {
    function get_post_type($post = null): string
    {
        unset($post);
        $GLOBALS['vms_test_runtime_calls']['get_post_type']++;
        return 'vms_venue';
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

if (!function_exists('vms_sch_get_schedule_venue_candidates')) {
    function vms_sch_get_schedule_venue_candidates(int $limit = -1): array
    {
        unset($limit);
        $GLOBALS['vms_test_runtime_calls']['vms_sch_get_schedule_venue_candidates']++;
        return array_values(array_map('intval', (array) $GLOBALS['vms_test_schedule_venue_candidates']));
    }
}

if (!function_exists('vms_sch_pick_single_venue_candidate')) {
    function vms_sch_pick_single_venue_candidate(array $candidate_ids): int
    {
        $GLOBALS['vms_test_runtime_calls']['vms_sch_pick_single_venue_candidate']++;
        $ids = array_values(array_unique(array_filter(array_map('intval', $candidate_ids))));
        return count($ids) === 1 ? (int) $ids[0] : 0;
    }
}

if (!function_exists('vms_sch_get_all_venue_ids')) {
    function vms_sch_get_all_venue_ids(): array
    {
        $GLOBALS['vms_test_runtime_calls']['vms_sch_get_all_venue_ids']++;
        return array_values(array_map('intval', (array) $GLOBALS['vms_test_all_venue_ids']));
    }
}

if (!function_exists('vms_render_current_venue_selector')) {
    function vms_render_current_venue_selector(): void
    {
        echo '<div class="vms-test-current-venue-selector"></div>';
    }
}

if (!function_exists('wp_die')) {
    function wp_die($message = ''): void
    {
        throw new RuntimeException('Unexpected wp_die: ' . (string) $message);
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

$resetState = static function (): void {
    $GLOBALS['vms_test_runtime_calls'] = array(
        'current_user_can' => 0,
        'get_current_user_id' => 0,
        'get_user_meta' => 0,
        'metadata_exists' => 0,
        'add_query_arg' => 0,
        'remove_query_arg' => 0,
        'get_post_type' => 0,
        'get_post_status' => 0,
        'vms_sch_get_schedule_venue_candidates' => 0,
        'vms_sch_pick_single_venue_candidate' => 0,
        'vms_sch_get_all_venue_ids' => 0,
    );
    $GLOBALS['vms_test_mutation_calls'] = array(
        'update_user_meta' => 0,
        'update_option' => 0,
        'update_post_meta' => 0,
        'delete_post_meta' => 0,
    );
    $GLOBALS['vms_test_user_meta'] = array();
    $GLOBALS['vms_test_schedule_venue_candidates'] = array();
    $GLOBALS['vms_test_all_venue_ids'] = array();
    $_GET = array();
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

    $expectedNoSelectionHtml = '<div class="notice notice-warning"><p>Select a venue to view its schedule.</p></div>';
    $expectedNoVenuesHtml = '<div class="notice notice-warning"><p>No venues found to display.</p></div>';

    $assert(function_exists('vms_schedule_get_scope_warning_notice_context'), 'Schedule should define the scope warning notice context builder.');
    $assert(function_exists('vms_schedule_render_scope_warning_notice'), 'Schedule should define the scope warning notice renderer.');
    $assert(function_exists('vms_schedule_get_unpublished_venue_notice_context'), 'Schedule should define the unpublished-venue notice context builder.');
    $assert(function_exists('vms_schedule_render_unpublished_venue_notice'), 'Schedule should define the unpublished-venue notice renderer.');
    $assert(function_exists('vms_render_schedule_page_content'), 'Schedule should still define the page-content renderer.');

    $builderSource = $functionSource('vms_schedule_get_scope_warning_notice_context');
    $rendererSource = $functionSource('vms_schedule_render_scope_warning_notice');
    $pageContentSource = $functionSource('vms_render_schedule_page_content');

    $assert(strpos($builderSource, "'show' => \$show") !== false, 'Schedule scope warning builder should preserve the finite show flag.');
    $assert(strpos($builderSource, "'variant' => \$variant") !== false, 'Schedule scope warning builder should preserve the finite variant key.');
    $assert(strpos($builderSource, "in_array(\$variant, array('no_selection', 'no_venues'), true)") !== false, 'Schedule scope warning builder should normalize the finite warning variants.');
    $assert(strpos($builderSource, 'get_option(') === false && strpos($builderSource, 'get_posts(') === false && strpos($builderSource, 'get_post_meta(') === false && strpos($builderSource, 'current_user_can(') === false, 'Schedule scope warning builder should not perform provider or capability reads.');
    $assert(strpos($builderSource, 'admin_url(') === false && strpos($builderSource, 'get_edit_post_link(') === false && strpos($builderSource, 'wp_create_nonce(') === false, 'Schedule scope warning builder should not perform URL or nonce work.');
    $assert(strpos($builderSource, 'update_option(') === false && strpos($builderSource, 'update_post_meta(') === false && strpos($builderSource, 'delete_post_meta(') === false, 'Schedule scope warning builder should not perform mutations.');

    $assert(strpos($rendererSource, $expectedNoSelectionHtml) !== false, 'Schedule scope warning renderer should preserve the exact no-selection fragment.');
    $assert(strpos($rendererSource, $expectedNoVenuesHtml) !== false, 'Schedule scope warning renderer should preserve the exact no-venues fragment.');
    $assert(strpos($rendererSource, 'esc_html__(') === false && strpos($rendererSource, '__(') === false, 'Schedule scope warning renderer should preserve the lack of translation wrappers.');
    $assert(strpos($rendererSource, 'get_option(') === false && strpos($rendererSource, 'get_posts(') === false && strpos($rendererSource, 'get_post_meta(') === false && strpos($rendererSource, 'current_user_can(') === false, 'Schedule scope warning renderer should not perform provider or capability reads.');
    $assert(strpos($rendererSource, 'get_post_status(') === false && strpos($rendererSource, 'get_the_title(') === false && strpos($rendererSource, 'get_edit_post_link(') === false, 'Schedule scope warning renderer should stay separate from the richer unpublished-venue reads.');
    $assert(strpos($rendererSource, 'admin_url(') === false && strpos($rendererSource, 'wp_create_nonce(') === false, 'Schedule scope warning renderer should not perform URL or nonce work.');
    $assert(strpos($rendererSource, 'update_option(') === false && strpos($rendererSource, 'update_post_meta(') === false && strpos($rendererSource, 'delete_post_meta(') === false, 'Schedule scope warning renderer should not perform mutations.');

    $assert(substr_count($scheduleSource, $expectedNoSelectionHtml) === 1, 'Schedule should keep only one direct no-selection warning fragment after normalization.');
    $assert(substr_count($scheduleSource, $expectedNoVenuesHtml) === 1, 'Schedule should keep only one direct no-venues warning fragment after normalization.');
    $assert(substr_count($scheduleSource, 'vms_schedule_render_scope_warning_notice($scope_warning_notice_context);') === 2, 'Schedule should route both scope warning branches through the shared page-local renderer.');
    $assert(substr_count($scheduleSource, 'vms_schedule_render_unpublished_venue_notice($unpublished_notice_context);') === 2, 'Schedule should keep routing both richer unpublished-venue branches through their separate shared renderer.');
    $assert(strpos($pageContentSource, "vms_schedule_get_scope_warning_notice_context(\$scope === 'venue' && (int) \$venue_id <= 0, 'no_selection');") !== false, 'Schedule page content should preserve the exact no-selection condition.');
    $assert(strpos($pageContentSource, "vms_schedule_get_scope_warning_notice_context(empty(\$venue_ids), 'no_venues');") !== false, 'Schedule page content should preserve the exact no-venues condition after the all-venue read.');
    $assert(strpos($scheduleSource, "echo '<div class=\"notice notice-error\"><p><strong>' . esc_html__('Action required:', 'backstage-venue-manager') . '</strong> ';") !== false, 'Schedule should preserve the richer unpublished-venue notice family outside the scope warning renderer.');
    $assert(strpos($scheduleSource, 'vms_schedule_render_invalid_bounds_notice($invalid_bounds_notice_context);') !== false, 'Schedule should preserve the separate invalid-bounds renderer family.');
    $assert(strpos($scheduleSource, "'notices_callback' =>") === false, 'Schedule scope warnings should remain content-local and outside the Administrator shell.');

    $visibleNoSelectionContext = vms_schedule_get_scope_warning_notice_context(true, 'no_selection');
    $hiddenNoSelectionContext = vms_schedule_get_scope_warning_notice_context(false, 'no_selection');
    $visibleNoVenuesContext = vms_schedule_get_scope_warning_notice_context(true, 'no_venues');
    $invalidVariantContext = vms_schedule_get_scope_warning_notice_context(true, '<script>alert(1)</script>');

    $assertSame(array('show', 'variant'), array_keys($visibleNoSelectionContext), 'Schedule scope warning builder should return only the finite show and variant keys.');
    $assertSame(array('show' => true, 'variant' => 'no_selection'), $visibleNoSelectionContext, 'Schedule scope warning builder should preserve the visible no-selection context exactly.');
    $assertSame(array('show' => false, 'variant' => 'no_selection'), $hiddenNoSelectionContext, 'Schedule scope warning builder should preserve the hidden no-selection context exactly.');
    $assertSame(array('show' => true, 'variant' => 'no_venues'), $visibleNoVenuesContext, 'Schedule scope warning builder should preserve the visible no-venues context exactly.');
    $assertSame(array('show' => true, 'variant' => ''), $invalidVariantContext, 'Schedule scope warning builder should collapse invalid variants to an empty finite value.');

    $beforeHiddenRenderer = $snapshotCounters();
    $hiddenHtml = $captureOutput(static function (): void {
        vms_schedule_render_scope_warning_notice(array('show' => false, 'variant' => 'no_selection'));
    });
    $afterHiddenRenderer = $snapshotCounters();
    $assertSame('', $hiddenHtml, 'Schedule scope warning renderer should emit nothing for hidden context.');
    $assertSame($beforeHiddenRenderer, $afterHiddenRenderer, 'Schedule scope warning renderer should not perform reads or mutations for hidden context.');

    foreach (array(
        'no_selection' => $expectedNoSelectionHtml,
        'no_venues' => $expectedNoVenuesHtml,
    ) as $variant => $expectedHtml) {
        $beforeVisibleRenderer = $snapshotCounters();
        $visibleHtml = $captureOutput(static function () use ($variant): void {
            vms_schedule_render_scope_warning_notice(array(
                'show' => true,
                'variant' => $variant,
                'message' => '</p><script>alert(1)</script><p>',
                'class' => 'notice notice-danger',
                'extra' => '<strong>ignored</strong>',
            ));
        });
        $afterVisibleRenderer = $snapshotCounters();

        $assertSame($expectedHtml, $visibleHtml, 'Schedule scope warning renderer should emit the exact finite fragment for variant ' . $variant . '.');
        $assertSame($beforeVisibleRenderer, $afterVisibleRenderer, 'Schedule scope warning renderer should not perform reads or mutations for visible variant ' . $variant . '.');
        $assert(strpos($visibleHtml, '<script>') === false && strpos($visibleHtml, '<strong>') === false && strpos($visibleHtml, 'notice-danger') === false, 'Schedule scope warning renderer should ignore malformed context markup and attributes for variant ' . $variant . '.');

        $rendererDocument = $parseWrappedHtml($visibleHtml, 'Schedule scope warning renderer output for ' . $variant);
        $rendererXPath = new DOMXPath($rendererDocument);
        $rendererRoot = $rendererXPath->query('//*[@id="vms-root"]')->item(0);
        $assert($rendererRoot instanceof DOMElement, 'Schedule scope warning renderer DOM root should exist for variant ' . $variant . '.');
        $assert($rendererRoot->childNodes->length === 1, 'Schedule scope warning renderer output should contain exactly one outer node for variant ' . $variant . '.');
        $noticeNode = $rendererRoot->firstChild;
        $assert($noticeNode instanceof DOMElement && $noticeNode->tagName === 'div', 'Schedule scope warning renderer output should use one outer div for variant ' . $variant . '.');
        $assertSame('notice notice-warning', $noticeNode->getAttribute('class'), 'Schedule scope warning renderer output should preserve the exact class contract for variant ' . $variant . '.');
        $assert($noticeNode->attributes->length === 1, 'Schedule scope warning renderer output should not introduce extra attributes for variant ' . $variant . '.');
        $paragraphNodes = $noticeNode->getElementsByTagName('p');
        $assert($paragraphNodes->length === 1, 'Schedule scope warning renderer output should contain exactly one paragraph for variant ' . $variant . '.');
        $paragraphNode = $paragraphNodes->item(0);
        $assert($paragraphNode instanceof DOMElement && $paragraphNode->parentNode === $noticeNode, 'Schedule scope warning renderer output should keep one direct paragraph child for variant ' . $variant . '.');
        $assertSame(trim(strip_tags($expectedHtml)), trim((string) $paragraphNode->textContent), 'Schedule scope warning renderer output should preserve the exact visible message for variant ' . $variant . '.');
        $assert($noticeNode->getElementsByTagName('*')->length === 1, 'Schedule scope warning renderer output should contain no elements beyond the direct paragraph for variant ' . $variant . '.');
    }

    $beforeInvalidVariantRenderer = $snapshotCounters();
    $invalidVariantHtml = $captureOutput(static function (): void {
        vms_schedule_render_scope_warning_notice(array('show' => true, 'variant' => 'bad_variant'));
    });
    $afterInvalidVariantRenderer = $snapshotCounters();
    $assertSame('', $invalidVariantHtml, 'Schedule scope warning renderer should emit nothing for an invalid visible variant.');
    $assertSame($beforeInvalidVariantRenderer, $afterInvalidVariantRenderer, 'Schedule scope warning renderer should not perform reads or mutations for an invalid visible variant.');

    $resetState();
    $GLOBALS['vms_test_user_meta'][77]['_vms_schedule_scope'] = 'venue';
    $noSelectionBefore = $snapshotCounters();
    $noSelectionPageHtml = $captureOutput(static function (): void {
        vms_render_schedule_page_content();
    });
    $noSelectionAfter = $snapshotCounters();
    $assert(strpos($noSelectionPageHtml, '<div class="vms-admin-schedule-content">') !== false, 'Schedule no-selection branch should remain nested inside the schedule content wrapper.');
    $assert(strpos($noSelectionPageHtml, 'vms-test-current-venue-selector') !== false, 'Schedule no-selection branch should preserve the current-venue selector position.');
    $assert(strpos($noSelectionPageHtml, $expectedNoSelectionHtml) !== false, 'Schedule no-selection branch should emit the shared warning fragment.');
    $assert(strpos($noSelectionPageHtml, $expectedNoVenuesHtml) === false, 'Schedule no-selection branch should not emit the no-venues fragment.');
    $assert(strpos($noSelectionPageHtml, 'vms-sch-scope-venue') === false && strpos($noSelectionPageHtml, 'vms-sch-list') === false && strpos($noSelectionPageHtml, 'vms-panel-month') === false, 'Schedule no-selection branch should return before any venue-scope body markup appears.');
    $assertSame($noSelectionBefore['mutation_calls'], $noSelectionAfter['mutation_calls'], 'Schedule no-selection branch should not perform mutations when no incoming state changes are requested.');
    $assertSame(0, $noSelectionAfter['runtime_calls']['vms_sch_get_all_venue_ids'], 'Schedule no-selection branch should return before reading all-venue IDs.');
    $assertSame(2, $noSelectionAfter['runtime_calls']['vms_sch_get_schedule_venue_candidates'], 'Schedule no-selection branch should preserve the unpublished-guard candidate probe plus the single-candidate fallback probe before the warning.');
    $assertSame(0, $noSelectionAfter['runtime_calls']['get_post_status'], 'Schedule no-selection branch should not enter the unpublished-venue guards.');

    $resetState();
    $GLOBALS['vms_test_user_meta'][77]['_vms_schedule_scope'] = 'all';
    $GLOBALS['vms_test_all_venue_ids'] = array();
    $noVenuesBefore = $snapshotCounters();
    $noVenuesPageHtml = $captureOutput(static function (): void {
        vms_render_schedule_page_content();
    });
    $noVenuesAfter = $snapshotCounters();
    $assert(strpos($noVenuesPageHtml, '<div class="vms-admin-schedule-content">') !== false, 'Schedule no-venues branch should remain nested inside the schedule content wrapper.');
    $assert(strpos($noVenuesPageHtml, $expectedNoSelectionHtml) === false, 'Schedule no-venues branch should not emit the no-selection fragment.');
    $assert(strpos($noVenuesPageHtml, $expectedNoVenuesHtml) !== false, 'Schedule no-venues branch should emit the shared no-venues warning fragment.');
    $assert(strpos($noVenuesPageHtml, 'vms-sch-scope-all') === false && strpos($noVenuesPageHtml, 'vms-sch-list-all') === false && strpos($noVenuesPageHtml, 'vms-panel-month') === false, 'Schedule no-venues branch should return before any all-venues body markup appears.');
    $assertSame($noVenuesBefore['mutation_calls'], $noVenuesAfter['mutation_calls'], 'Schedule no-venues branch should not perform mutations when no incoming state changes are requested.');
    $assertSame(1, $noVenuesAfter['runtime_calls']['vms_sch_get_all_venue_ids'], 'Schedule no-venues branch should preserve the single all-venue read before the warning.');
    $assertSame(2, $noVenuesAfter['runtime_calls']['vms_sch_get_schedule_venue_candidates'], 'Schedule no-venues branch should preserve the unpublished-guard candidate probe plus the single-candidate fallback probe before the all-venues warning.');
    $assertSame(0, $noVenuesAfter['runtime_calls']['get_post_status'], 'Schedule no-venues branch should not enter the unpublished-venue guards.');

    fwrite(STDOUT, "schedule warning notice output remediation: PASS\n");
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
