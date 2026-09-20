<?php
/** Exercise actual responders and a throwing renderer without booting WordPress. */
define('ABSPATH', '/synthetic/');
function bvmgr_round2_load_function(string $path, string $name): void {
    $tokens = token_get_all(file_get_contents($path));
    for ($i = 0; $i < count($tokens); $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) continue;
        $j = $i + 1;
        while (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
        if (!is_array($tokens[$j]) || $tokens[$j][1] !== $name) continue;
        $code = ''; $depth = 0; $opened = false;
        for (; $i < count($tokens); $i++) {
            $t = $tokens[$i]; $code .= is_array($t) ? $t[1] : $t;
            if ($t === '{') { $depth++; $opened = true; }
            if ($t === '}' && --$depth === 0 && $opened) { eval($code); return; }
        }
    }
    throw new RuntimeException('Function missing: ' . $name);
}
function wp_send_json_success(...$args) { throw new RuntimeException('success:' . json_encode($args)); }
function wp_send_json_error(...$args) { throw new RuntimeException('error:' . json_encode($args)); }
function sanitize_html_class($s) { return $s; }
$root = dirname(__DIR__);
foreach (array('bvmgr_ticketing_ajax_attach_noise', 'bvmgr_ticketing_ajax_send_success', 'bvmgr_ticketing_ajax_send_error', 'bvmgr_ticketing_v2_ajax_send_success', 'bvmgr_ticketing_v2_ajax_send_error') as $name) bvmgr_round2_load_function($root . '/includes/integrations/ticketing.php', $name);
bvmgr_round2_load_function($root . '/includes/admin-ui/shell.php', 'bvmgr_admin_ui_render_shell');
$checks = 0;
foreach (array('bvmgr_ticketing_ajax_send_success', 'bvmgr_ticketing_ajax_send_error', 'bvmgr_ticketing_v2_ajax_send_success', 'bvmgr_ticketing_v2_ajax_send_error') as $name) {
    ob_start(); echo 'HOST BUFFER'; $level = ob_get_level();
    try { $name(array('message' => 'VALIDATION OR SUCCESS'), 422); } catch (RuntimeException $e) {}
    if (ob_get_level() !== $level || ob_get_contents() !== 'HOST BUFFER') throw new RuntimeException('Responder changed caller buffer');
    ob_end_clean(); $checks++;
}
foreach (array(new RuntimeException('synthetic exception'), new Error('synthetic error')) as $error) {
    ob_start(); echo 'HOST BUFFER'; $level = ob_get_level();
    try { bvmgr_admin_ui_render_shell(array(), static function () use ($error) { echo 'PARTIAL RENDER'; throw $error; }); } catch (Throwable $e) { if ($e !== $error) throw $e; }
    if (ob_get_level() !== $level || ob_get_contents() !== 'HOST BUFFER') throw new RuntimeException('Renderer leaked buffer');
    ob_end_clean(); $checks++;
}
$loader = file_get_contents($root . '/includes/integrations/load.php');
if (strpos($loader, 'ob_start') !== false || strpos($loader, 'DOING_AJAX') !== false) throw new RuntimeException('Unrelated AJAX still intercepted');
echo "PASS $checks output ownership/exception scenarios and unrelated AJAX loader gate\n";
