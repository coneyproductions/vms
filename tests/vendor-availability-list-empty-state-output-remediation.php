<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

$GLOBALS['vms_test_filters'] = array();
$GLOBALS['vms_test_provider_reads'] = array(
    'get_option' => 0,
    'get_post_meta' => 0,
    'get_posts' => 0,
    'current_user_can' => 0,
);
$GLOBALS['vms_test_mutation_calls'] = array(
    'update_option' => 0,
    'update_post_meta' => 0,
    'delete_post_meta' => 0,
);

if (!function_exists('add_filter')) {
    function add_filter(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
    {
        $GLOBALS['vms_test_filters'][] = array(
            'hook' => $hook,
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $accepted_args,
        );
        return true;
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

if (!function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = ''): string
    {
        return esc_html(__($text, $domain));
    }
}

if (!function_exists('date_i18n')) {
    function date_i18n(string $format, $timestamp = false, bool $gmt = false): string
    {
        unset($format, $timestamp, $gmt);
        return 'July 17, 2026';
    }
}

if (!function_exists('get_option')) {
    function get_option(string $option, $default = false)
    {
        $GLOBALS['vms_test_provider_reads']['get_option']++;
        if ($option === 'date_format') {
            return 'F j, Y';
        }
        return $default;
    }
}

if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, string $key = '', bool $single = false)
    {
        unset($post_id, $key, $single);
        $GLOBALS['vms_test_provider_reads']['get_post_meta']++;
        return '';
    }
}

if (!function_exists('get_posts')) {
    function get_posts(array $args = array()): array
    {
        unset($args);
        $GLOBALS['vms_test_provider_reads']['get_posts']++;
        return array();
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can(string $capability, ...$args): bool
    {
        unset($capability, $args);
        $GLOBALS['vms_test_provider_reads']['current_user_can']++;
        return true;
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

require_once dirname(__DIR__) . '/includes/admin/vendor-availability.php';

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
        'provider_reads' => $GLOBALS['vms_test_provider_reads'],
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
    $plugin_root = dirname(__DIR__);
    $source = file_get_contents($plugin_root . '/includes/admin/vendor-availability.php');
    $assert(is_string($source) && $source !== '', 'Vendor Availability source should be readable.');

    $assert(function_exists('vms_vendor_availability_get_list_empty_state_notice_context'), 'Vendor Availability should define the empty-state notice context builder.');
    $assert(function_exists('vms_vendor_availability_render_list_empty_state_notice'), 'Vendor Availability should define the empty-state notice renderer.');
    $assert(function_exists('vms_render_vendor_availability_list_view'), 'Vendor Availability should still define the list-view renderer.');

    $builder_source = $functionSource('vms_vendor_availability_get_list_empty_state_notice_context');
    $renderer_source = $functionSource('vms_vendor_availability_render_list_empty_state_notice');
    $list_source = $functionSource('vms_render_vendor_availability_list_view');

    $assert(strpos($builder_source, "'show' => empty(\$rows)") !== false, 'Vendor Availability empty-state builder should preserve the exact empty($rows) condition.');
    $assert(strpos($builder_source, 'get_option(') === false && strpos($builder_source, 'get_post_meta(') === false && strpos($builder_source, 'get_posts(') === false && strpos($builder_source, 'current_user_can(') === false, 'Vendor Availability empty-state builder should not perform provider or capability reads.');
    $assert(strpos($builder_source, 'update_option(') === false && strpos($builder_source, 'update_post_meta(') === false && strpos($builder_source, 'delete_post_meta(') === false, 'Vendor Availability empty-state builder should not perform mutations.');

    $assert(strpos($renderer_source, "esc_html__('No vendors matched the current filters for this date.', 'backstage-venue-manager')") !== false, 'Vendor Availability empty-state renderer should preserve the exact translated message.');
    $assert(strpos($renderer_source, 'get_option(') === false && strpos($renderer_source, 'get_post_meta(') === false && strpos($renderer_source, 'get_posts(') === false && strpos($renderer_source, 'current_user_can(') === false, 'Vendor Availability empty-state renderer should not perform provider or capability reads.');
    $assert(strpos($renderer_source, 'update_option(') === false && strpos($renderer_source, 'update_post_meta(') === false && strpos($renderer_source, 'delete_post_meta(') === false, 'Vendor Availability empty-state renderer should not perform mutations.');

    $assert(strpos($list_source, '$empty_state_notice_context = vms_vendor_availability_get_list_empty_state_notice_context($rows);') !== false, 'Vendor Availability list view should resolve the empty-state context from the existing rows input.');
    $assert(strpos($list_source, 'vms_vendor_availability_render_list_empty_state_notice($empty_state_notice_context);') !== false, 'Vendor Availability list view should render the empty-state notice through the page-local renderer.');
    $assert(strpos($list_source, "echo '<div class=\"vms-va-table-wrap\">';") !== false, 'Vendor Availability list view should still retain the non-empty table path.');

    $visible_context = vms_vendor_availability_get_list_empty_state_notice_context(array());
    $hidden_context = vms_vendor_availability_get_list_empty_state_notice_context(array(array('vendor_id' => 44)));
    $assertSame(array('show' => true), $visible_context, 'Vendor Availability empty-state builder should show only when rows are empty.');
    $assertSame(array('show' => false), $hidden_context, 'Vendor Availability empty-state builder should hide when rows are present.');

    $before_hidden_renderer = $snapshotCounters();
    $hidden_html = $captureOutput(static function (): void {
        vms_vendor_availability_render_list_empty_state_notice(array('show' => false));
    });
    $after_hidden_renderer = $snapshotCounters();
    $assertSame('', $hidden_html, 'Vendor Availability empty-state renderer should emit nothing for hidden context.');
    $assertSame($before_hidden_renderer, $after_hidden_renderer, 'Vendor Availability empty-state renderer should not perform provider reads or mutations for hidden context.');

    $before_visible_renderer = $snapshotCounters();
    $visible_html = $captureOutput(static function (): void {
        vms_vendor_availability_render_list_empty_state_notice(array(
            'show' => true,
            'message' => '</p><script>alert(1)</script><p>',
            'notice_class' => 'notice notice-danger inline',
            'extra' => '<strong>ignored</strong>',
        ));
    });
    $after_visible_renderer = $snapshotCounters();
    $expected_visible_html = '<div class="notice notice-info inline"><p>No vendors matched the current filters for this date.</p></div>';
    $assertSame($expected_visible_html, $visible_html, 'Vendor Availability empty-state renderer should emit the exact finite fragment for visible context.');
    $assertSame($before_visible_renderer, $after_visible_renderer, 'Vendor Availability empty-state renderer should not perform provider reads or mutations for visible context.');
    $assert(strpos($visible_html, '<script>') === false && strpos($visible_html, '<strong>') === false && strpos($visible_html, 'notice notice-danger inline') === false, 'Vendor Availability empty-state renderer should ignore malformed context markup and attributes.');

    $renderer_document = $parseWrappedHtml($visible_html, 'Vendor Availability empty-state renderer output');
    $renderer_xpath = new DOMXPath($renderer_document);
    $renderer_root = $renderer_xpath->query('//*[@id="vms-root"]')->item(0);
    $assert($renderer_root instanceof DOMElement, 'Vendor Availability renderer DOM root should exist.');
    $assert($renderer_root->childNodes->length === 1, 'Vendor Availability renderer output should contain exactly one outer node.');
    $notice_node = $renderer_root->firstChild;
    $assert($notice_node instanceof DOMElement && $notice_node->tagName === 'div', 'Vendor Availability renderer output should use one outer div.');
    $assertSame('notice notice-info inline', $notice_node->getAttribute('class'), 'Vendor Availability renderer output should preserve the exact class contract.');
    $assert($notice_node->attributes->length === 1, 'Vendor Availability renderer output should not introduce extra attributes.');
    $paragraph_nodes = $notice_node->getElementsByTagName('p');
    $assert($paragraph_nodes->length === 1, 'Vendor Availability renderer output should contain exactly one paragraph.');
    $paragraph_node = $paragraph_nodes->item(0);
    $assert($paragraph_node instanceof DOMElement && $paragraph_node->parentNode === $notice_node, 'Vendor Availability renderer output should keep one direct paragraph child.');
    $assertSame('No vendors matched the current filters for this date.', trim((string) $paragraph_node->textContent), 'Vendor Availability renderer output should preserve the exact visible message.');
    $assert($notice_node->getElementsByTagName('*')->length === 1, 'Vendor Availability renderer output should contain no elements beyond the direct paragraph.');

    $before_list_render = $snapshotCounters();
    $list_empty_html = $captureOutput(static function (): void {
        vms_render_vendor_availability_list_view(array(), '2026-07-17', 'list', array());
    });
    $after_list_render = $snapshotCounters();
    $assert($after_list_render['provider_reads']['get_option'] === $before_list_render['provider_reads']['get_option'] + 1, 'Vendor Availability list empty-state lifecycle should preserve the single date-format option read for the heading.');
    $assertSame($before_list_render['provider_reads']['get_post_meta'], $after_list_render['provider_reads']['get_post_meta'], 'Vendor Availability list empty-state lifecycle should not add meta reads.');
    $assertSame($before_list_render['provider_reads']['get_posts'], $after_list_render['provider_reads']['get_posts'], 'Vendor Availability list empty-state lifecycle should not add provider reads.');
    $assertSame($before_list_render['provider_reads']['current_user_can'], $after_list_render['provider_reads']['current_user_can'], 'Vendor Availability list empty-state lifecycle should not add capability reads.');
    $assertSame($before_list_render['mutation_calls'], $after_list_render['mutation_calls'], 'Vendor Availability list empty-state lifecycle should not add mutations.');
    $assert(strpos($list_empty_html, '<div class="vms-va-table-wrap">') === false, 'Vendor Availability empty-state lifecycle should still return before the non-empty table path.');
    $assert(strpos($list_empty_html, $expected_visible_html) !== false, 'Vendor Availability empty-state lifecycle should include the exact nested notice fragment.');

    $list_document = $parseWrappedHtml($list_empty_html, 'Vendor Availability list-view empty-state output');
    $list_xpath = new DOMXPath($list_document);
    $list_root = $list_xpath->query('//*[@id="vms-root"]/div')->item(0);
    $assert($list_root instanceof DOMElement, 'Vendor Availability list-view output should contain the outer list wrapper.');
    $assertSame('vms-va-list', $list_root->getAttribute('class'), 'Vendor Availability list-view output should preserve the exact outer list class in list mode.');
    $assertSame('vendor-availability.list', $list_root->getAttribute('data-vms-tour'), 'Vendor Availability list-view output should preserve the existing tour anchor.');
    $nested_notice = $list_xpath->query('.//div[@class="notice notice-info inline"]', $list_root)->item(0);
    $assert($nested_notice instanceof DOMElement, 'Vendor Availability list-view output should keep the empty-state notice nested inside .vms-va-list.');
    $assert($nested_notice->parentNode === $list_root, 'Vendor Availability list-view output should keep the notice as a direct child of .vms-va-list after the section head.');
    $list_heading = $list_xpath->query('.//div[contains(concat(" ", normalize-space(@class), " "), " vms-va-section-head ")]', $list_root)->item(0);
    $assert($list_heading instanceof DOMElement, 'Vendor Availability list-view output should still render the section heading before the notice.');
    $assert($list_heading->nextSibling === $nested_notice || ($list_heading->nextSibling instanceof DOMText && $list_heading->nextSibling->nextSibling === $nested_notice), 'Vendor Availability list-view output should preserve the heading-then-notice ordering.');

    fwrite(STDOUT, "vendor availability list empty-state output remediation: PASS\n");
} catch (Throwable $e) {
    fwrite(STDERR, 'vendor availability list empty-state output remediation: FAIL - ' . $e->getMessage() . "\n");
    exit(1);
}
