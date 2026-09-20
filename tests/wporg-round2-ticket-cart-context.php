<?php
/** Run the actual scoped cart loop with empty and throwing product fixtures. */
$source = (string) file_get_contents(dirname(__DIR__) . '/includes/integrations/ticketing-rules-v2.php');
$start = strpos($source, "    $" . "bvmgr_had_atomic_context = array_key_exists(");
$end = strpos($source, "    if (empty($" . "errors) && $" . "event_plan_id > 0)", $start);
if ($start === false || $end === false) throw new RuntimeException('Actual atomic cart scope missing');
$block = substr($source, $start, $end - $start);
function absint($value) { return abs((int) $value); }
function bvmgr_ticketing_v2_product_is_entitlement($id) {
    if (($GLOBALS['bvmgr_ticketing_v2_atomic_add_in_progress'] ?? null) !== true) throw new RuntimeException('Cart scope was not active');
    throw $GLOBALS['expected_error'];
}
$key = 'bvmgr_ticketing_v2_atomic_add_in_progress'; $checks = 0;
foreach (array('absent', null, false, true, 'outer') as $before) {
    foreach (array(null, new RuntimeException('fixture exception'), new Error('fixture error')) as $error) {
        if ($before === 'absent') unset($GLOBALS[$key]); else $GLOBALS[$key] = $before;
        $GLOBALS['expected_error'] = $error;
        $ticket_lines = $error === null ? array() : array(array('product_id' => 1, 'qty' => 1));
        try { eval($block); if ($error !== null) throw new RuntimeException('Exception disappeared'); }
        catch (Throwable $caught) { if ($caught !== $error) throw $caught; }
        if ($before === 'absent' ? array_key_exists($key, $GLOBALS) : (!array_key_exists($key, $GLOBALS) || $GLOBALS[$key] !== $before)) throw new RuntimeException('Atomic add context was not restored');
        $checks++;
    }
}
echo "PASS $checks cart-scope completion, exception/error, nested-value and absent-state cases\n";
