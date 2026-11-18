<?php
declare(strict_types=1);

require __DIR__ . '/issue102-function-loader.php';
$root = $argv[1] ?? dirname(__DIR__) . '/backstage-venue-manager';
load_functions($root . '/includes/ticketing/ticket-mutation-audit.php', [
    'bvmgr_ticket_mutation_audit_record_post_meta_write',
    'bvmgr_ticket_mutation_audit_build_summary',
    'bvmgr_ticket_mutation_audit_change_type_label',
]);
function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return (string) $value; }
function sanitize_text_field($value) { return (string) $value; }
function __($value, ...$args) { return $value; }
function wp_json_encode($value) { return json_encode($value); }
function get_current_user_id() { return 1; }
function get_the_title($id) { return 'Synthetic plan'; }
function metadata_exists($type, $id, $key) { return $GLOBALS['config_remains']; }
function bvmgr_ticketing_v2_k($kind) { return 'config'; }
function bvmgr_ticket_mutation_audit_is_relevant_meta_write(...$args) { return true; }
function bvmgr_ticket_mutation_audit_guard_decision(...$args) { return ['allowed' => true]; }
function bvmgr_ticket_mutation_audit_trace(...$args) {}
function bvmgr_ticket_mutation_audit_dequeue_pending(...$args) {
    return ['before_snapshot' => $GLOBALS['before'], 'context' => $GLOBALS['context'],
        'source_hook' => 'fixture', 'source_function' => 'fixture'];
}
function bvmgr_ticket_mutation_audit_build_snapshot($id) { return $GLOBALS['after']; }
function bvmgr_ticket_mutation_audit_current_context() { return $GLOBALS['context']; }
function bvmgr_ticket_mutation_audit_trigger_source(...$args) { return 'manual_action'; }
function bvmgr_ticket_mutation_audit_infer_change_type($key, $context, ...$args) { return $context['change_type']; }
function bvmgr_ticket_mutation_audit_snapshot_hash($snapshot) { return hash('sha256', serialize($snapshot)); }
function bvmgr_ticket_mutation_audit_normalize_result_status($status) { return $status; }
function bvmgr_ticket_mutation_audit_insert($row) { $GLOBALS['recorded'] = $row; }
function expect($condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS $message
";
}

$before = ['capacity' => 10];
foreach ([true, false] as $mapping_changed) {
    $after = ['capacity' => $mapping_changed ? 11 : 10];
    foreach ([true, false] as $config_remains) {
        $context = ['change_type' => 'ticket_config_cleared', 'requested_result_status' => 'success',
            'summary_text' => 'Cleared the saved Ticketing v2 config for this event.'];
        bvmgr_ticket_mutation_audit_record_post_meta_write('delete', 901, 'config');
        if ($config_remains) {
            expect($recorded['change_type'] === 'ticket_config_clear_incomplete' &&
                $recorded['result_status'] === 'partial' &&
                $recorded['summary_text'] === 'Clear Config did not complete. The saved configuration is still present.',
                'surviving config has an incomplete partial audit with mapping change ' . (int) $mapping_changed);
        } else {
            expect($recorded['change_type'] === 'ticket_config_cleared' &&
                $recorded['result_status'] === ($mapping_changed ? 'success' : 'no_op'),
                'completed clear retains prior audit classification with mapping change ' . (int) $mapping_changed);
        }
    }
}
expect(bvmgr_ticket_mutation_audit_change_type_label('ticket_config_clear_incomplete') === 'Ticket config clear incomplete',
    'operator-facing audit label does not claim the config was cleared');

$config_remains = true;
$after = ['capacity' => 11];
$context['change_type'] = 'ticket_config_saved';
bvmgr_ticket_mutation_audit_record_post_meta_write('update', 901, 'config');
expect($recorded['change_type'] === 'ticket_config_saved' && $recorded['result_status'] === 'success',
    'ordinary save audit retains its existing result');
$context['change_type'] = 'ticket_config_cleared';
bvmgr_ticket_mutation_audit_record_post_meta_write('delete', 901, 'legacy_image');
expect($recorded['change_type'] === 'ticket_config_cleared' && $recorded['result_status'] === 'success',
    'the correction is scoped to the configuration key');
