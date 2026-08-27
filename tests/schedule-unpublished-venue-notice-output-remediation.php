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
    'get_the_title' => 0,
    'get_edit_post_link' => 0,
    'admin_url' => 0,
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
$GLOBALS['vms_test_post_type_map'] = array();
$GLOBALS['vms_test_post_status_map'] = array();
$GLOBALS['vms_test_post_title_map'] = array();
$GLOBALS['vms_test_edit_link_map'] = array();

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
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        if (preg_match('~^\s*javascript:~i', $url) === 1) {
            return '';
        }

        return htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($value): string
    {
        return sanitize_text_field($value);
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($value): string
    {
        return sanitize_text_field($value);
    }
}

if (!function_exists('bvmgr_request_read_scalar')) {
    function bvmgr_request_read_scalar(array $source, string $key): string
    {
        if (!array_key_exists($key, $source) || !is_scalar($source[$key])) {
            return '';
        }

        $value = wp_unslash($source[$key]);
        return is_scalar($value) ? trim((string) $value) : '';
    }
}

if (!function_exists('bvmgr_request_read_text_field')) {
    function bvmgr_request_read_text_field(array $source, string $key): string
    {
        $value = bvmgr_request_read_scalar($source, $key);
        return $value === '' ? '' : sanitize_text_field($value);
    }
}

if (!function_exists('bvmgr_request_read_key')) {
    function bvmgr_request_read_key(array $source, string $key): string
    {
        $value = bvmgr_request_read_scalar($source, $key);
        return $value === '' ? '' : sanitize_key($value);
    }
}

if (!function_exists('bvmgr_request_read_absint')) {
    function bvmgr_request_read_absint(array $source, string $key): int
    {
        $value = bvmgr_request_read_scalar($source, $key);
        return $value === '' ? 0 : absint($value);
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
        $GLOBALS['vms_test_runtime_calls']['get_post_type']++;
        $post_id = is_scalar($post) ? (int) $post : 0;
        return (string) ($GLOBALS['vms_test_post_type_map'][$post_id] ?? 'vms_venue');
    }
}

if (!function_exists('get_post_status')) {
    function get_post_status($post): string
    {
        $GLOBALS['vms_test_runtime_calls']['get_post_status']++;
        $post_id = is_scalar($post) ? (int) $post : 0;
        return (string) ($GLOBALS['vms_test_post_status_map'][$post_id] ?? 'publish');
    }
}

if (!function_exists('get_the_title')) {
    function get_the_title($post = 0): string
    {
        $GLOBALS['vms_test_runtime_calls']['get_the_title']++;
        $post_id = is_scalar($post) ? (int) $post : 0;
        return (string) ($GLOBALS['vms_test_post_title_map'][$post_id] ?? '');
    }
}

if (!function_exists('get_edit_post_link')) {
    function get_edit_post_link($post = 0, string $context = 'display')
    {
        unset($context);
        $GLOBALS['vms_test_runtime_calls']['get_edit_post_link']++;
        $post_id = is_scalar($post) ? (int) $post : 0;
        return $GLOBALS['vms_test_edit_link_map'][$post_id] ?? '';
    }
}

if (!function_exists('admin_url')) {
    function admin_url(string $path = ''): string
    {
        $GLOBALS['vms_test_runtime_calls']['admin_url']++;
        return 'https://example.test/wp-admin/' . ltrim($path, '/');
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

if (!function_exists('bvmgr_render_current_venue_selector')) {
    function bvmgr_render_current_venue_selector(): void
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
        'get_the_title' => 0,
        'get_edit_post_link' => 0,
        'admin_url' => 0,
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
    $GLOBALS['vms_test_post_type_map'] = array();
    $GLOBALS['vms_test_post_status_map'] = array();
    $GLOBALS['vms_test_post_title_map'] = array();
    $GLOBALS['vms_test_edit_link_map'] = array();
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

$assertNeedleOrder = static function (string $source, array $needles, string $message) use ($assert): void {
    $offset = -1;
    foreach ($needles as $needle) {
        $position = strpos($source, $needle);
        $assert($position !== false, $message . ' Missing source marker: ' . $needle);
        $assert($position > $offset, $message . ' Source order changed around marker: ' . $needle);
        $offset = $position;
    }
};

$assertNoticeDom = static function (string $html, array $expected, string $label) use ($assert, $assertSame, $parseWrappedHtml): void {
    $document = $parseWrappedHtml($html, $label);
    $xpath = new DOMXPath($document);
    $root = $xpath->query('//*[@id="vms-root"]')->item(0);
    $assert($root instanceof DOMElement, $label . ' should have a wrapper root.');
    $assertSame(1, $root->childNodes->length, $label . ' should emit exactly one outer node.');

    $noticeNode = $root->firstChild;
    $assert($noticeNode instanceof DOMElement && $noticeNode->tagName === 'div', $label . ' should use one outer div.');
    $assertSame('notice notice-error', $noticeNode->getAttribute('class'), $label . ' should preserve the exact outer class.');
    $assertSame(1, $noticeNode->attributes->length, $label . ' should not introduce extra outer attributes.');

    $directParagraphs = array();
    foreach ($noticeNode->childNodes as $childNode) {
        if ($childNode instanceof DOMElement) {
            $directParagraphs[] = $childNode;
        }
    }
    $assertSame(2, count($directParagraphs), $label . ' should contain exactly two direct paragraph children.');
    foreach ($directParagraphs as $paragraphNode) {
        $assert($paragraphNode->tagName === 'p', $label . ' should keep direct children limited to paragraphs.');
        $assertSame(0, $paragraphNode->attributes->length, $label . ' paragraphs should not introduce attributes.');
    }

    $headingNode = $xpath->query('//*[@id="vms-root"]/div/p[1]/strong')->item(0);
    $assert($headingNode instanceof DOMElement, $label . ' should contain one heading strong node.');
    $assertSame($expected['heading'], trim((string) $headingNode->textContent), $label . ' should preserve the exact heading copy.');
    $assertSame(0, $headingNode->attributes->length, $label . ' heading strong node should remain attribute-free.');

    $titleNodes = $xpath->query('//*[@id="vms-root"]/div/p[1]/span[@class="vms-muted"][1]');
    $statusNodes = $xpath->query('//*[@id="vms-root"]/div/p[1]/span[@class="vms-muted"][last()]');
    $expectedTitleCount = $expected['title_visible'] ? 1 : 0;
    $assertSame($expectedTitleCount, $titleNodes->length, $label . ' should preserve title span visibility.');
    if ($expected['title_visible']) {
        $titleNode = $titleNodes->item(0);
        $assert($titleNode instanceof DOMElement, $label . ' should contain the visible title span.');
        $assertSame($expected['title'], trim((string) $titleNode->textContent), $label . ' should preserve the exact title text.');
        $assertSame(1, $titleNode->attributes->length, $label . ' title span should only expose its class attribute.');
    }

    $assertSame(1, $statusNodes->length, $label . ' should preserve exactly one status span.');
    $statusNode = $statusNodes->item(0);
    $assert($statusNode instanceof DOMElement, $label . ' should contain the status span.');
    $assertSame('(' . $expected['status'] . ')', trim((string) $statusNode->textContent), $label . ' should preserve the exact status formatting.');
    $assertSame(1, $statusNode->attributes->length, $label . ' status span should only expose its class attribute.');

    $firstParagraphText = trim(preg_replace('/\s+/', ' ', (string) $directParagraphs[0]->textContent));
    $expectedFirstParagraphText = trim(preg_replace('/\s+/', ' ', (string) $expected['first_paragraph_text']));
    $assertSame($expectedFirstParagraphText, $firstParagraphText, $label . ' should preserve the exact visible first-paragraph text.');

    $anchorNodes = $xpath->query('//*[@id="vms-root"]/div/p[2]/a');
    $assertSame(1, $anchorNodes->length, $label . ' should contain exactly one action link.');
    $anchorNode = $anchorNodes->item(0);
    $assert($anchorNode instanceof DOMElement, $label . ' should contain the action link element.');
    $assertSame('button button-primary', $anchorNode->getAttribute('class'), $label . ' should preserve the exact button classes.');
    $assertSame($expected['href'], $anchorNode->getAttribute('href'), $label . ' should preserve the exact action href.');
    $assertSame($expected['button'], trim((string) $anchorNode->textContent), $label . ' should preserve the exact button label.');
    $assertSame(2, $anchorNode->attributes->length, $label . ' action link should expose only href and class.');

    $allElements = $xpath->query('//*[@id="vms-root"]//*');
    $assert($allElements instanceof DOMNodeList, $label . ' should have a queryable element list.');
    foreach ($allElements as $elementNode) {
        $assert($elementNode instanceof DOMElement, $label . ' should only contain element nodes.');
        $assert(in_array($elementNode->tagName, array('div', 'p', 'strong', 'span', 'a'), true), $label . ' should not introduce extra elements like ' . $elementNode->tagName . '.');
        foreach ($elementNode->attributes as $attributeNode) {
            $attributeName = $attributeNode->nodeName;
            $assert(strpos($attributeName, 'on') !== 0, $label . ' should not introduce inline event-handler attributes.');
            $assert(!in_array($attributeName, array('id', 'style', 'target', 'rel', 'role'), true), $label . ' should not introduce forbidden attributes.');
            $assert(strpos($attributeName, 'data-') !== 0, $label . ' should not introduce data attributes.');
            $assert(strpos($attributeName, 'aria-') !== 0, $label . ' should not introduce ARIA attributes.');
        }
    }
};

try {
    $pluginRoot = dirname(__DIR__);
    $scheduleSource = file_get_contents($pluginRoot . '/includes/admin/schedule.php');
    $assert(is_string($scheduleSource) && $scheduleSource !== '', 'Schedule source should be readable.');

    $assert(function_exists('vms_schedule_get_unpublished_venue_notice_context'), 'Schedule should define the unpublished-venue notice context builder.');
    $assert(function_exists('vms_schedule_render_unpublished_venue_notice'), 'Schedule should define the unpublished-venue notice renderer.');
    $assert(function_exists('vms_render_schedule_page_content'), 'Schedule should still define the page-content renderer.');

    $builderSource = $functionSource('vms_schedule_get_unpublished_venue_notice_context');
    $rendererSource = $functionSource('vms_schedule_render_unpublished_venue_notice');
    $pageContentSource = $functionSource('vms_render_schedule_page_content');

    $assert(strpos($builderSource, "in_array(\$variant, array('single_unpublished', 'selected_unpublished'), true)") !== false, 'Schedule unpublished builder should normalize the finite variant vocabulary.');
    $assert(strpos($builderSource, "\$show = \$show && \$variant !== '';") !== false, 'Schedule unpublished builder should hide unsupported variants.');
    $assert(strpos($builderSource, "'show_title' => \$show && \$title !== ''") !== false, 'Schedule unpublished builder should normalize title visibility separately from title text.');
    $assert(strpos($builderSource, "'title' => \$show ? \$title : ''") !== false, 'Schedule unpublished builder should collapse hidden titles to an empty finite value.');
    $assert(strpos($builderSource, "'status' => \$show ? \$status : ''") !== false, 'Schedule unpublished builder should collapse hidden statuses to an empty finite value.');
    $assert(strpos($builderSource, "'edit_url' => \$show ? \$edit_url : ''") !== false, 'Schedule unpublished builder should collapse hidden edit URLs to an empty finite value.');
    foreach (array('get_post_status(', 'get_the_title(', 'get_edit_post_link(', 'admin_url(', 'current_user_can(', 'get_option(', 'get_post_meta(', 'update_user_meta(', 'update_option(', 'update_post_meta(', 'delete_post_meta(', 'wp_create_nonce(') as $forbiddenToken) {
        $assert(strpos($builderSource, $forbiddenToken) === false, 'Schedule unpublished builder should not perform reads, URL construction, nonce work, or mutations: ' . $forbiddenToken);
    }

    foreach (array(
        "esc_html__('Action required:', 'backstage-venue-manager')",
        "esc_html__('Your only venue is not published, so Schedule cannot load availability.', 'backstage-venue-manager')",
        "esc_html__('Venue is not published:', 'backstage-venue-manager')",
        "esc_html__('Publish this venue to enable schedule availability.', 'backstage-venue-manager')",
        "esc_html__('Open venue to publish', 'backstage-venue-manager')",
    ) as $requiredRendererToken) {
        $assert(strpos($rendererSource, $requiredRendererToken) !== false, 'Schedule unpublished renderer should preserve the exact translated copy token: ' . $requiredRendererToken);
    }
    foreach (array('vms_sch_get_schedule_venue_candidates(', 'get_post_status(', 'get_the_title(', 'get_edit_post_link(', 'admin_url(', 'current_user_can(', 'get_option(', 'get_post_meta(', 'update_user_meta(', 'update_option(', 'update_post_meta(', 'delete_post_meta(', 'wp_create_nonce(') as $forbiddenToken) {
        $assert(strpos($rendererSource, $forbiddenToken) === false, 'Schedule unpublished renderer should stay read-free and mutation-free: ' . $forbiddenToken);
    }

    $assert(substr_count($scheduleSource, 'vms_schedule_render_unpublished_venue_notice($unpublished_notice_context);') === 2, 'Schedule should route both unpublished branches through the shared page-local renderer.');
    $assert(strpos($pageContentSource, "vms_schedule_get_unpublished_venue_notice_context(true, 'single_unpublished', \$title, \$only_unpublished_status, \$edit);") !== false, 'Schedule page content should preserve the exact single-candidate builder call.');
    $assert(strpos($pageContentSource, "vms_schedule_get_unpublished_venue_notice_context(true, 'selected_unpublished', \$title, \$selected_status, \$edit);") !== false, 'Schedule page content should preserve the exact selected-venue builder call.');
    $assert(strpos($pageContentSource, "if (count(\$venue_candidates) === 1)") !== false, 'Schedule page content should preserve the exact single-candidate condition.');
    $assert(strpos($pageContentSource, "if (\$scope === 'venue' && (int) \$venue_id > 0)") !== false, 'Schedule page content should preserve the exact selected-venue condition.');
    $assert(substr_count($pageContentSource, "echo '</div>';") >= 2 && substr_count($pageContentSource, 'return;') >= 2, 'Schedule unpublished branches should still close the wrapper and return immediately.');

    $singleBranchMatched = preg_match("~if\\s*\\(\\s*\\\$only_unpublished_id\\s*>\\s*0\\s*\\)\\s*\\{(?P<body>.*?)echo '</div>';\\s*return;\\s*\\}~s", $pageContentSource, $singleBranchMatch);
    $assertSame(1, $singleBranchMatched, 'Failed to isolate the single-candidate unpublished branch source.');
    $singleBranchSource = (string) ($singleBranchMatch[0] ?? '');
    $assertNeedleOrder(
        $singleBranchSource,
        array(
            '$title = (string) get_the_title($only_unpublished_id);',
            '$edit  = get_edit_post_link($only_unpublished_id, \'raw\');',
            'if (empty($edit)) {',
            '$edit = admin_url(\'post.php?post=\' . (int) $only_unpublished_id . \'&action=edit\');',
            '$unpublished_notice_context = vms_schedule_get_unpublished_venue_notice_context(true, \'single_unpublished\', $title, $only_unpublished_status, $edit);',
            'vms_schedule_render_unpublished_venue_notice($unpublished_notice_context);',
            "echo '</div>';",
            'return;',
        ),
        'Schedule single-candidate unpublished branch should preserve read order, fallback order, wrapper close, and return.'
    );

    $selectedBranchMatched = preg_match("~if\\s*\\(\\s*\\\$scope === 'venue' && \\(int\\) \\\$venue_id > 0\\s*\\)\\s*\\{(?P<body>.*?)echo '</div>';\\s*return;\\s*\\}~s", $pageContentSource, $selectedBranchMatch);
    $assertSame(1, $selectedBranchMatched, 'Failed to isolate the selected-venue unpublished branch source.');
    $selectedBranchSource = (string) ($selectedBranchMatch[0] ?? '');
    $assertNeedleOrder(
        $selectedBranchSource,
        array(
            '$selected_status = (string) get_post_status((int) $venue_id);',
            'if (!in_array($selected_status, array(\'publish\', \'private\'), true)) {',
            '$title = (string) get_the_title((int) $venue_id);',
            '$edit  = get_edit_post_link((int) $venue_id, \'raw\');',
            'if (empty($edit)) {',
            '$edit = admin_url(\'post.php?post=\' . (int) $venue_id . \'&action=edit\');',
            '$unpublished_notice_context = vms_schedule_get_unpublished_venue_notice_context(true, \'selected_unpublished\', $title, $selected_status, $edit);',
            'vms_schedule_render_unpublished_venue_notice($unpublished_notice_context);',
            "echo '</div>';",
            'return;',
        ),
        'Schedule selected-venue unpublished branch should preserve read order, fallback order, wrapper close, and return.'
    );

    $visibleSingleContext = vms_schedule_get_unpublished_venue_notice_context(true, 'single_unpublished', 'Only Venue', 'draft', 'https://example.test/wp-admin/post.php?post=41&action=edit');
    $visibleSelectedContext = vms_schedule_get_unpublished_venue_notice_context(true, 'selected_unpublished', '', 'pending', 'https://example.test/custom-edit.php?post=41&action=edit');
    $invalidVariantContext = vms_schedule_get_unpublished_venue_notice_context(true, '<script>alert(1)</script>', 'Injected', 'draft', 'javascript:alert(1)');
    $emptyVariantContext = vms_schedule_get_unpublished_venue_notice_context(true, '', 'Injected', 'draft', 'javascript:alert(1)');

    $assertSame(array('show', 'variant', 'show_title', 'title', 'status', 'edit_url'), array_keys($visibleSingleContext), 'Schedule unpublished builder should return only the finite context keys.');
    $assertSame(
        array(
            'show' => true,
            'variant' => 'single_unpublished',
            'show_title' => true,
            'title' => 'Only Venue',
            'status' => 'draft',
            'edit_url' => 'https://example.test/wp-admin/post.php?post=41&action=edit',
        ),
        $visibleSingleContext,
        'Schedule unpublished builder should preserve the visible single-candidate context exactly.'
    );
    $assertSame(
        array(
            'show' => true,
            'variant' => 'selected_unpublished',
            'show_title' => false,
            'title' => '',
            'status' => 'pending',
            'edit_url' => 'https://example.test/custom-edit.php?post=41&action=edit',
        ),
        $visibleSelectedContext,
        'Schedule unpublished builder should preserve the visible selected-venue context exactly.'
    );
    $assertSame(
        array(
            'show' => false,
            'variant' => '',
            'show_title' => false,
            'title' => '',
            'status' => '',
            'edit_url' => '',
        ),
        $invalidVariantContext,
        'Schedule unpublished builder should collapse malformed variants to hidden empty finite values.'
    );
    $assertSame($invalidVariantContext, $emptyVariantContext, 'Schedule unpublished builder should treat empty and malformed variants the same way.');

    $beforeBuilderCounters = $snapshotCounters();
    vms_schedule_get_unpublished_venue_notice_context(true, 'single_unpublished', 'Only Venue', 'draft', 'https://example.test/wp-admin/post.php?post=41&action=edit');
    $afterBuilderCounters = $snapshotCounters();
    $assertSame($beforeBuilderCounters, $afterBuilderCounters, 'Schedule unpublished builder should not perform reads or mutations.');

    $hiddenRendererBefore = $snapshotCounters();
    $hiddenRendererHtml = $captureOutput(static function (): void {
        vms_schedule_render_unpublished_venue_notice(array('show' => false, 'variant' => 'single_unpublished'));
    });
    $hiddenRendererAfter = $snapshotCounters();
    $assertSame('', $hiddenRendererHtml, 'Schedule unpublished renderer should emit nothing for hidden context.');
    $assertSame($hiddenRendererBefore, $hiddenRendererAfter, 'Schedule unpublished renderer should not perform reads or mutations for hidden context.');

    $invalidRendererBefore = $snapshotCounters();
    $invalidRendererHtml = $captureOutput(static function (): void {
        vms_schedule_render_unpublished_venue_notice(array('show' => true, 'variant' => 'bad_variant', 'title' => 'Ignored', 'status' => 'draft', 'edit_url' => 'https://example.test/wp-admin/post.php?post=999&action=edit'));
    });
    $invalidRendererAfter = $snapshotCounters();
    $assertSame('', $invalidRendererHtml, 'Schedule unpublished renderer should emit nothing for invalid visible variants.');
    $assertSame($invalidRendererBefore, $invalidRendererAfter, 'Schedule unpublished renderer should not perform reads or mutations for invalid visible variants.');

    $expectedSingleHtml = '<div class="notice notice-error"><p><strong>Action required:</strong> Your only venue is not published, so Schedule cannot load availability. <span class="vms-muted">Only Venue</span> <span class="vms-muted">(draft)</span></p><p><a class="button button-primary" href="https://example.test/wp-admin/post.php?post=41&amp;action=edit">Open venue to publish</a></p></div>';
    $expectedSelectedHtml = '<div class="notice notice-error"><p><strong>Venue is not published:</strong> <span class="vms-muted">Selected Venue</span> <span class="vms-muted">(pending)</span> Publish this venue to enable schedule availability.</p><p><a class="button button-primary" href="https://example.test/custom-edit.php?post=41&amp;action=edit">Open venue to publish</a></p></div>';
    $expectedSelectedNoTitleHtml = '<div class="notice notice-error"><p><strong>Venue is not published:</strong> <span class="vms-muted">(pending)</span> Publish this venue to enable schedule availability.</p><p><a class="button button-primary" href="https://example.test/custom-edit.php?post=41&amp;action=edit">Open venue to publish</a></p></div>';

    $singleRendererBefore = $snapshotCounters();
    $singleRendererHtml = $captureOutput(static function (): void {
        vms_schedule_render_unpublished_venue_notice(array(
            'show' => true,
            'variant' => 'single_unpublished',
            'show_title' => true,
            'title' => 'Only Venue',
            'status' => 'draft',
            'edit_url' => 'https://example.test/wp-admin/post.php?post=41&action=edit',
            'heading' => '<script>alert(1)</script>',
            'body' => '<strong>ignored</strong>',
            'button' => 'Bad label',
            'class' => 'notice notice-warning',
        ));
    });
    $singleRendererAfter = $snapshotCounters();
    $assertSame($expectedSingleHtml, $singleRendererHtml, 'Schedule unpublished renderer should preserve the exact single-candidate rich notice markup.');
    $assertSame($singleRendererBefore, $singleRendererAfter, 'Schedule unpublished renderer should not perform reads or mutations for the visible single-candidate variant.');
    $assert(strpos($singleRendererHtml, '<script>') === false && strpos($singleRendererHtml, 'Bad label') === false && strpos($singleRendererHtml, 'notice-warning') === false, 'Schedule unpublished renderer should ignore arbitrary context strings for the single-candidate variant.');

    $selectedRendererBefore = $snapshotCounters();
    $selectedRendererHtml = $captureOutput(static function (): void {
        vms_schedule_render_unpublished_venue_notice(array(
            'show' => true,
            'variant' => 'selected_unpublished',
            'show_title' => true,
            'title' => 'Selected Venue',
            'status' => 'pending',
            'edit_url' => 'https://example.test/custom-edit.php?post=41&action=edit',
        ));
    });
    $selectedRendererAfter = $snapshotCounters();
    $assertSame($expectedSelectedHtml, $selectedRendererHtml, 'Schedule unpublished renderer should preserve the exact selected-venue rich notice markup.');
    $assertSame($selectedRendererBefore, $selectedRendererAfter, 'Schedule unpublished renderer should not perform reads or mutations for the visible selected-venue variant.');

    $selectedNoTitleRendererHtml = $captureOutput(static function (): void {
        vms_schedule_render_unpublished_venue_notice(array(
            'show' => true,
            'variant' => 'selected_unpublished',
            'show_title' => false,
            'title' => 'Ignored title',
            'status' => 'pending',
            'edit_url' => 'https://example.test/custom-edit.php?post=41&action=edit',
        ));
    });
    $assertSame($expectedSelectedNoTitleHtml, $selectedNoTitleRendererHtml, 'Schedule unpublished renderer should omit the title span when the finite context hides it.');

    $escapedRendererHtml = $captureOutput(static function (): void {
        vms_schedule_render_unpublished_venue_notice(array(
            'show' => true,
            'variant' => 'single_unpublished',
            'show_title' => true,
            'title' => 'Only Venue </span><script>alert(1)</script>',
            'status' => 'dr<aft',
            'edit_url' => 'javascript:alert(1)',
        ));
    });
    $assert(strpos($escapedRendererHtml, '<script>') === false, 'Schedule unpublished renderer should keep hostile title content inert.');
    $assert(strpos($escapedRendererHtml, 'javascript:alert(1)') === false, 'Schedule unpublished renderer should not emit a hostile javascript URL.');
    $assert(strpos($escapedRendererHtml, 'href=""') !== false, 'Schedule unpublished renderer should reduce a hostile URL to an inert empty href.');

    $assertNoticeDom(
        $singleRendererHtml,
        array(
            'heading' => 'Action required:',
            'body' => 'Your only venue is not published, so Schedule cannot load availability.',
            'title_visible' => true,
            'title' => 'Only Venue',
            'status' => 'draft',
            'first_paragraph_text' => 'Action required: Your only venue is not published, so Schedule cannot load availability. Only Venue (draft)',
            'href' => 'https://example.test/wp-admin/post.php?post=41&action=edit',
            'button' => 'Open venue to publish',
        ),
        'Schedule single-candidate unpublished renderer output'
    );
    $assertNoticeDom(
        $selectedRendererHtml,
        array(
            'heading' => 'Venue is not published:',
            'body' => 'Publish this venue to enable schedule availability.',
            'title_visible' => true,
            'title' => 'Selected Venue',
            'status' => 'pending',
            'first_paragraph_text' => 'Venue is not published: Selected Venue (pending) Publish this venue to enable schedule availability.',
            'href' => 'https://example.test/custom-edit.php?post=41&action=edit',
            'button' => 'Open venue to publish',
        ),
        'Schedule selected-venue unpublished renderer output'
    );

    $resetState();
    $GLOBALS['vms_test_user_meta'][77]['_vms_schedule_scope'] = 'venue';
    $GLOBALS['vms_test_user_meta'][77]['_vms_current_venue_id'] = '99';
    $GLOBALS['vms_test_schedule_venue_candidates'] = array(41);
    $GLOBALS['vms_test_post_status_map'] = array(
        41 => 'draft',
        99 => 'publish',
    );
    $GLOBALS['vms_test_post_title_map'] = array(
        41 => 'Only Venue',
    );
    $GLOBALS['vms_test_edit_link_map'] = array(
        41 => '',
    );
    $singleBranchBefore = $snapshotCounters();
    $singleBranchHtml = $captureOutput(static function (): void {
        vms_render_schedule_page_content();
    });
    $singleBranchAfter = $snapshotCounters();
    $assert(strpos($singleBranchHtml, '<div class="vms-admin-schedule-content">') !== false, 'Schedule single-candidate branch should remain nested inside the schedule content wrapper.');
    $assert(strpos($singleBranchHtml, 'vms-test-current-venue-selector') !== false, 'Schedule single-candidate branch should preserve the current-venue selector position.');
    $assert(strpos($singleBranchHtml, $expectedSingleHtml) !== false, 'Schedule single-candidate branch should emit the shared rich notice fragment.');
    $assert(strpos($singleBranchHtml, 'vms-sch-scope-venue') === false && strpos($singleBranchHtml, 'vms-sch-scope-all') === false && strpos($singleBranchHtml, 'vms-sch-list') === false && strpos($singleBranchHtml, 'vms-panel-month') === false, 'Schedule single-candidate branch should return before view-body markup appears.');
    $assertSame($singleBranchBefore['mutation_calls'], $singleBranchAfter['mutation_calls'], 'Schedule single-candidate branch should not introduce new mutations when a current venue is already stored.');
    $assertSame(0, $singleBranchAfter['runtime_calls']['vms_sch_get_all_venue_ids'], 'Schedule single-candidate branch should return before all-venue reads.');
    $assertSame(1, $singleBranchAfter['runtime_calls']['vms_sch_get_schedule_venue_candidates'], 'Schedule single-candidate branch should preserve the exact candidate-provider call count when a current venue already exists.');
    $assertSame(2, $singleBranchAfter['runtime_calls']['get_post_status'], 'Schedule single-candidate branch should preserve the current-venue validity check plus the unpublished-candidate status read.');
    $assertSame(1, $singleBranchAfter['runtime_calls']['get_the_title'], 'Schedule single-candidate branch should read the candidate title exactly once.');
    $assertSame(1, $singleBranchAfter['runtime_calls']['get_edit_post_link'], 'Schedule single-candidate branch should read the candidate edit link exactly once.');
    $assertSame(1, $singleBranchAfter['runtime_calls']['admin_url'], 'Schedule single-candidate branch should preserve the fallback admin_url() construction when the raw edit link is empty.');

    $resetState();
    $GLOBALS['vms_test_user_meta'][77]['_vms_schedule_scope'] = 'venue';
    $GLOBALS['vms_test_user_meta'][77]['_vms_current_venue_id'] = '41';
    $GLOBALS['vms_test_schedule_venue_candidates'] = array(41, 99);
    $GLOBALS['vms_test_post_status_map'] = array(
        41 => 'pending',
        99 => 'publish',
    );
    $GLOBALS['vms_test_post_title_map'] = array(
        41 => 'Selected Venue',
    );
    $GLOBALS['vms_test_edit_link_map'] = array(
        41 => 'https://example.test/custom-edit.php?post=41&action=edit',
    );
    $selectedBranchBefore = $snapshotCounters();
    $selectedBranchHtml = $captureOutput(static function (): void {
        vms_render_schedule_page_content();
    });
    $selectedBranchAfter = $snapshotCounters();
    $assert(strpos($selectedBranchHtml, '<div class="vms-admin-schedule-content">') !== false, 'Schedule selected-venue branch should remain nested inside the schedule content wrapper.');
    $assert(strpos($selectedBranchHtml, $expectedSelectedHtml) !== false, 'Schedule selected-venue branch should emit the shared rich notice fragment.');
    $assert(strpos($selectedBranchHtml, $expectedSingleHtml) === false, 'Schedule selected-venue branch should not emit the single-candidate notice.');
    $assert(strpos($selectedBranchHtml, 'vms-sch-scope-venue') === false && strpos($selectedBranchHtml, 'vms-sch-scope-all') === false && strpos($selectedBranchHtml, 'vms-sch-list') === false && strpos($selectedBranchHtml, 'vms-panel-month') === false, 'Schedule selected-venue branch should return before any downstream view-body markup appears.');
    $assertSame($selectedBranchBefore['mutation_calls'], $selectedBranchAfter['mutation_calls'], 'Schedule selected-venue branch should not introduce mutations for an already-selected venue.');
    $assertSame(0, $selectedBranchAfter['runtime_calls']['vms_sch_get_all_venue_ids'], 'Schedule selected-venue branch should return before all-venue reads.');
    $assertSame(1, $selectedBranchAfter['runtime_calls']['vms_sch_get_schedule_venue_candidates'], 'Schedule selected-venue branch should preserve the guardrail candidate probe after the stored venue path.');
    $assertSame(2, $selectedBranchAfter['runtime_calls']['get_post_status'], 'Schedule selected-venue branch should preserve the stored-venue validity check plus the unpublished selected-status read.');
    $assertSame(1, $selectedBranchAfter['runtime_calls']['get_the_title'], 'Schedule selected-venue branch should read the selected venue title exactly once.');
    $assertSame(1, $selectedBranchAfter['runtime_calls']['get_edit_post_link'], 'Schedule selected-venue branch should read the selected venue edit link exactly once.');
    $assertSame(0, $selectedBranchAfter['runtime_calls']['admin_url'], 'Schedule selected-venue branch should preserve the no-fallback path when the raw edit link is already available.');

    $assert(strpos($scheduleSource, "function vms_schedule_get_invalid_bounds_notice_context(bool \$show): array") !== false, 'Schedule should preserve the separate invalid-bounds builder family.');
    $assert(strpos($scheduleSource, "function vms_schedule_render_invalid_bounds_notice(array \$context): void") !== false, 'Schedule should preserve the separate invalid-bounds renderer family.');
    $assert(strpos($scheduleSource, "function vms_schedule_get_scope_warning_notice_context(bool \$show, string \$variant): array") !== false, 'Schedule should preserve the separate scope warning builder family.');
    $assert(strpos($scheduleSource, "function vms_schedule_render_scope_warning_notice(array \$context): void") !== false, 'Schedule should preserve the separate scope warning renderer family.');
    $assert(strpos($scheduleSource, "'notices_callback' =>") === false, 'Schedule unpublished rich notices should remain content-local and outside the Administrator shell.');

    fwrite(STDOUT, "schedule unpublished venue notice output remediation: PASS\n");
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
