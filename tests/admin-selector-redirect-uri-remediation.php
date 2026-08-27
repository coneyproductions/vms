<?php
declare(strict_types=1);

function vms_test_admin_selector_redirect_assert(bool $condition, string $message): void
{
    if ($condition) {
        return;
    }

    throw new RuntimeException($message);
}

function vms_test_admin_selector_redirect_find_matching_brace(string $code, int $openBracePos): int
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

function vms_test_admin_selector_redirect_extract_named_function(string $path, string $functionName): string
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

    $endPos = vms_test_admin_selector_redirect_find_matching_brace($code, $bracePos);
    return substr($code, $functionPos, $endPos - $functionPos + 1);
}

function vms_test_admin_selector_redirect_extract_closure(string $path, string $marker): string
{
    $code = (string) file_get_contents($path);
    $closurePos = strpos($code, $marker);
    if ($closurePos === false) {
        throw new RuntimeException('Closure marker not found: ' . $marker);
    }

    $bracePos = strpos($code, '{', $closurePos);
    if ($bracePos === false) {
        throw new RuntimeException('Closure brace not found for marker: ' . $marker);
    }

    $endPos = vms_test_admin_selector_redirect_find_matching_brace($code, $bracePos);
    return substr($code, $closurePos, $endPos - $closurePos + 1);
}

function vms_test_admin_selector_redirect_assert_contains(string $needle, string $haystack, string $message): void
{
    vms_test_admin_selector_redirect_assert(
        strpos($haystack, $needle) !== false,
        $message . ' Missing substring: ' . $needle
    );
}

function vms_test_admin_selector_redirect_assert_not_contains(string $needle, string $haystack, string $message): void
{
    vms_test_admin_selector_redirect_assert(
        strpos($haystack, $needle) === false,
        $message . ' Unexpected substring: ' . $needle
    );
}

$pluginRoot = dirname(__DIR__);
$livePluginRoot = dirname(dirname($pluginRoot)) . '/vms';

$mirrorHelpersPath = $pluginRoot . '/includes/helpers.php';
$liveHelpersPath = $livePluginRoot . '/includes/helpers.php';
$mirrorVenueContextPath = $pluginRoot . '/includes/admin/venue-context.php';
$liveVenueContextPath = $livePluginRoot . '/includes/admin/venue-context.php';

$mirrorHelpersSource = (string) file_get_contents($mirrorHelpersPath);
$liveHelpersSource = (string) file_get_contents($liveHelpersPath);
$mirrorVenueContextSource = (string) file_get_contents($mirrorVenueContextPath);
$liveVenueContextSource = (string) file_get_contents($liveVenueContextPath);

vms_test_admin_selector_redirect_assert($mirrorHelpersSource !== '', 'Mirror helpers source should be readable.');
vms_test_admin_selector_redirect_assert($liveHelpersSource !== '', 'Live helpers source should be readable.');
vms_test_admin_selector_redirect_assert($mirrorVenueContextSource !== '', 'Mirror venue-context source should be readable.');
vms_test_admin_selector_redirect_assert($liveVenueContextSource !== '', 'Live venue-context source should be readable.');

foreach (
    array(
        'Mirror helpers' => $mirrorHelpersSource,
        'Live helpers' => $liveHelpersSource,
        'Mirror venue-context' => $mirrorVenueContextSource,
        'Live venue-context' => $liveVenueContextSource,
    ) as $label => $source
) {
    vms_test_admin_selector_redirect_assert_not_contains('$_SERVER', $source, $label . ' should not retain direct $_SERVER reads.');
}

$mirrorDashboardSelector = vms_test_admin_selector_redirect_extract_named_function($mirrorHelpersPath, 'bvmgr_dash_render_venue_selector');
$liveDashboardSelector = vms_test_admin_selector_redirect_extract_named_function($liveHelpersPath, 'bvmgr_dash_render_venue_selector');
$mirrorScheduleSelector = vms_test_admin_selector_redirect_extract_named_function($mirrorVenueContextPath, 'bvmgr_render_current_venue_selector');
$liveScheduleSelector = vms_test_admin_selector_redirect_extract_named_function($liveVenueContextPath, 'bvmgr_render_current_venue_selector');
$mirrorScheduleConsumer = vms_test_admin_selector_redirect_extract_closure($mirrorVenueContextPath, "add_action('admin_post_vms_set_current_venue', function () {");
$liveScheduleConsumer = vms_test_admin_selector_redirect_extract_closure($liveVenueContextPath, "add_action('admin_post_vms_set_current_venue', function () {");
$mirrorDashboardConsumer = vms_test_admin_selector_redirect_extract_closure($mirrorVenueContextPath, "add_action('admin_post_vms_set_dashboard_venue', function () {");
$liveDashboardConsumer = vms_test_admin_selector_redirect_extract_closure($liveVenueContextPath, "add_action('admin_post_vms_set_dashboard_venue', function () {");

foreach (
    array(
        'Mirror dashboard selector' => $mirrorDashboardSelector,
        'Live dashboard selector' => $liveDashboardSelector,
    ) as $label => $source
) {
    vms_test_admin_selector_redirect_assert(substr_count($source, 'vms_request_current_uri()') === 1, $label . ' should call vms_request_current_uri() exactly once.');
    vms_test_admin_selector_redirect_assert_contains("admin_url('admin.php?page=vms-dashboard')", $source, $label . ' should preserve the dashboard fallback.');
    vms_test_admin_selector_redirect_assert_contains('vms_request_local_redirect(', $source, $label . ' should preserve local redirect validation.');
    vms_test_admin_selector_redirect_assert_contains('redirect_to', $source, $label . ' should preserve the redirect_to field.');
    vms_test_admin_selector_redirect_assert_contains("admin-post.php", $source, $label . ' should preserve the admin-post form action.');
    vms_test_admin_selector_redirect_assert_contains('vms_set_dashboard_venue', $source, $label . ' should preserve the dashboard admin-post route.');
    vms_test_admin_selector_redirect_assert_contains('esc_attr($current_redirect)', $source, $label . ' should preserve redirect output escaping.');
    vms_test_admin_selector_redirect_assert_not_contains('wp_parse_url(', $source, $label . ' should keep the full current URI candidate instead of path-only parsing.');
}

foreach (
    array(
        'Mirror schedule selector' => $mirrorScheduleSelector,
        'Live schedule selector' => $liveScheduleSelector,
    ) as $label => $source
) {
    vms_test_admin_selector_redirect_assert(substr_count($source, 'vms_request_current_uri()') === 1, $label . ' should call vms_request_current_uri() exactly once.');
    vms_test_admin_selector_redirect_assert_contains("admin_url('admin.php?page=vms-schedule')", $source, $label . ' should preserve the schedule fallback.');
    vms_test_admin_selector_redirect_assert_contains('vms_request_local_redirect(', $source, $label . ' should preserve local redirect validation.');
    vms_test_admin_selector_redirect_assert_contains('redirect_to', $source, $label . ' should preserve the redirect_to field.');
    vms_test_admin_selector_redirect_assert_contains("admin-post.php", $source, $label . ' should preserve the admin-post form action.');
    vms_test_admin_selector_redirect_assert_contains('vms_set_current_venue', $source, $label . ' should preserve the schedule admin-post route.');
    vms_test_admin_selector_redirect_assert_contains('esc_attr($current_redirect)', $source, $label . ' should preserve redirect output escaping.');
    vms_test_admin_selector_redirect_assert_not_contains('wp_parse_url(', $source, $label . ' should keep the full current URI candidate instead of path-only parsing.');
}

foreach (
    array(
        'Mirror schedule consumer' => $mirrorScheduleConsumer,
        'Live schedule consumer' => $liveScheduleConsumer,
    ) as $label => $source
) {
    vms_test_admin_selector_redirect_assert_contains("current_user_can('manage_options')", $source, $label . ' should preserve the capability check.');
    vms_test_admin_selector_redirect_assert_contains('wp_verify_nonce($nonce, \'vms_set_current_venue\')', $source, $label . ' should preserve the schedule nonce check.');
    vms_test_admin_selector_redirect_assert(
        strpos($source, "\$_POST['redirect_to'] ?? ''") !== false
        || strpos($source, "vms_request_read_scalar(\$_POST, 'redirect_to')") !== false,
        $label . ' should continue reading submitted redirect_to through the validated local redirect path.'
    );
    vms_test_admin_selector_redirect_assert_contains('vms_request_local_redirect(', $source, $label . ' should preserve local redirect validation.');
    vms_test_admin_selector_redirect_assert_contains("wp_get_referer() ?: admin_url('admin.php?page=vms-schedule')", $source, $label . ' should preserve the schedule POST fallback.');
    vms_test_admin_selector_redirect_assert_contains("add_query_arg('venue_id', (string) \$venue_id, \$redirect)", $source, $label . ' should preserve schedule venue_id addition.');
    vms_test_admin_selector_redirect_assert_contains("remove_query_arg('venue_id', \$redirect)", $source, $label . ' should preserve schedule venue_id removal.');
    vms_test_admin_selector_redirect_assert_contains('wp_safe_redirect($redirect);', $source, $label . ' should preserve the safe redirect sink.');
}

foreach (
    array(
        'Mirror dashboard consumer' => $mirrorDashboardConsumer,
        'Live dashboard consumer' => $liveDashboardConsumer,
    ) as $label => $source
) {
    vms_test_admin_selector_redirect_assert_contains("current_user_can('manage_options')", $source, $label . ' should preserve the capability check.');
    vms_test_admin_selector_redirect_assert_contains('wp_verify_nonce($nonce, \'vms_set_dashboard_venue\')', $source, $label . ' should preserve the dashboard nonce check.');
    vms_test_admin_selector_redirect_assert(
        strpos($source, "\$_POST['redirect_to'] ?? ''") !== false
        || strpos($source, "vms_request_read_scalar(\$_POST, 'redirect_to')") !== false,
        $label . ' should continue reading submitted redirect_to through the validated local redirect path.'
    );
    vms_test_admin_selector_redirect_assert_contains('vms_request_local_redirect(', $source, $label . ' should preserve local redirect validation.');
    vms_test_admin_selector_redirect_assert_contains("wp_get_referer() ?: admin_url('admin.php?page=vms-dashboard')", $source, $label . ' should preserve the dashboard POST fallback.');
    vms_test_admin_selector_redirect_assert_contains('wp_safe_redirect($redirect);', $source, $label . ' should preserve the safe redirect sink.');
}

vms_test_admin_selector_redirect_assert(
    substr_count($mirrorHelpersSource, 'vms_request_current_uri()') === 1,
    'Mirror helpers should own one helper-backed selector URI fallback.'
);
vms_test_admin_selector_redirect_assert(
    substr_count($liveHelpersSource, 'vms_request_current_uri()') === 1,
    'Live helpers should own one helper-backed selector URI fallback.'
);
vms_test_admin_selector_redirect_assert(
    substr_count($mirrorVenueContextSource, 'vms_request_current_uri()') === 1,
    'Mirror venue-context should own one helper-backed selector URI fallback.'
);
vms_test_admin_selector_redirect_assert(
    substr_count($liveVenueContextSource, 'vms_request_current_uri()') === 1,
    'Live venue-context should own one helper-backed selector URI fallback.'
);

fwrite(STDOUT, "Admin selector redirect URI remediation OK.\n");
