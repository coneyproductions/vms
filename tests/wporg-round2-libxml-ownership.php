<?php
/** Actual libxml mode/diagnostic ownership, plus notice/content relocation. */
define('ABSPATH', __DIR__ . '/');
require dirname(__DIR__) . '/includes/admin-ui/shell.php';
$checks = 0;
foreach (array(false, true) as $mode) {
    libxml_use_internal_errors(true);
    libxml_clear_errors();
    if ($mode) { $seed = new DOMDocument(); $seed->loadXML('<owned-by-another-parser>'); }
    libxml_use_internal_errors($mode);
    $before = libxml_get_errors();
    $warnings = array();
    set_error_handler(static function ($level, $message) use (&$warnings) { $warnings[] = $message; return true; });
    try {
        $notices = '';
        $content = bvmgr_admin_ui_extract_notice_markup('<div class="notice notice-info"><p>Kept notice</p></div><section><input value="safe &amp; intact"><video></video></section>', $notices);
    } finally { restore_error_handler(); }
    if (libxml_use_internal_errors() !== $mode) throw new RuntimeException('Host libxml error mode changed');
    $checks++;
    if (array_slice(libxml_get_errors(), 0, count($before)) != $before) throw new RuntimeException('Another parser’s diagnostics were cleared');
    $checks++;
    if ($warnings) throw new RuntimeException('Owned HTML parse leaked PHP warnings: ' . implode(';', $warnings));
    $checks++;
    if (!str_contains($notices, 'Kept notice') || str_contains($content, 'Kept notice') || !str_contains($content, '<input') || !str_contains($content, 'safe &amp; intact')) throw new RuntimeException('Notice relocation or form content changed');
    $checks++;
}
libxml_clear_errors(); libxml_use_internal_errors(false);
echo "PASS $checks libxml mode, prior-diagnostic, scoped-warning and rendered-content checks\n";
