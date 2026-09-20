<?php
/** Exact three-hook shipping-source inventory; read-only, no package creation. */
declare(strict_types=1);
$root = dirname(__DIR__);
require_once $root . '/scripts/lib/public-release.php';
$patterns = (new ReflectionMethod(VMS_Public_Release_Tooling::class, 'loadExcludeManifest'))->invoke(null, $root . '/release-public-excludes.txt');
$match = new ReflectionMethod(VMS_Public_Release_Tooling::class, 'firstMatchingPattern');
$hooks = array('allowed_tabs' => 'apply_filters', 'nav_links' => 'do_action', 'render_custom_tab' => 'apply_filters');
$counts = array_fill_keys(array_keys($hooks), array('producer' => 0, 'consumer' => 0));
$files = array();
$iterator = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    static function (SplFileInfo $file) use ($root, $patterns, $match): bool {
        $relative = substr($file->getPathname(), strlen($root) + 1);
        return !$file->isLink() && $match->invoke(null, $relative . ($file->isDir() ? '/' : ''), $patterns) === null;
    }
));
foreach ($iterator as $file) {
    if (!$file->isFile()) { continue; }
    $relative = substr($file->getPathname(), strlen($root) + 1);
    $files[] = $relative;
    $source = file_get_contents($file->getPathname());
    foreach ($hooks as $suffix => $api) {
        if (str_contains($source, 'vms_vendor_portal_' . $suffix)) {
            throw new RuntimeException('Legacy same-name occurrence in shipping source: ' . $relative . ' / ' . $suffix);
        }
    }
    if (pathinfo($relative, PATHINFO_EXTENSION) !== 'php') { continue; }
    $tokens = token_get_all($source, TOKEN_PARSE);
    foreach ($tokens as $index => $token) {
        if (!is_array($token) || $token[0] !== T_STRING) { continue; }
        $api = $token[1];
        if (!in_array($api, array('do_action', 'do_action_ref_array', 'apply_filters', 'apply_filters_ref_array', 'add_action', 'add_filter'), true)) { continue; }
        $next = $index + 1;
        while (isset($tokens[$next]) && is_array($tokens[$next]) && in_array($tokens[$next][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) { ++$next; }
        if (($tokens[$next] ?? null) !== '(') { continue; }
        ++$next;
        while (isset($tokens[$next]) && is_array($tokens[$next]) && in_array($tokens[$next][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) { ++$next; }
        $arg = $tokens[$next] ?? null;
        if (!is_array($arg) || $arg[0] !== T_CONSTANT_ENCAPSED_STRING) { continue; }
        $value = substr($arg[1], 1, -1);
        foreach ($hooks as $suffix => $producer_api) {
            if ($value !== 'bvmgr_vendor_portal_' . $suffix) { continue; }
            $kind = in_array($api, array('add_action', 'add_filter'), true) ? 'consumer' : 'producer';
            $expected_file = $kind === 'producer' ? 'includes/portal/vendor-portal.php' : 'includes/modules/admissions/vendor-guest-portal.php';
            if ($relative !== $expected_file || ($kind === 'producer' && $api !== $producer_api)) {
                throw new RuntimeException('Unexpected canonical hook scope/API: ' . $relative . ':' . $token[2]);
            }
            ++$counts[$suffix][$kind];
        }
    }
}
foreach ($counts as $suffix => $count) {
    if ($count !== array('producer' => 1, 'consumer' => 1)) { throw new RuntimeException('Wrong hook counts: ' . $suffix); }
}
sort($files);
echo json_encode(array('result' => 'PASS', 'shipping_file_count' => count($files), 'hooks' => $counts, 'legacy_shipping_occurrences' => 0, 'files' => $files), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
