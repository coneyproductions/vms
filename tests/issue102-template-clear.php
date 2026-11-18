<?php
declare(strict_types=1);

require __DIR__ . '/issue102-save-config.php';
require __DIR__ . '/issue102-atomic-delete-fixture.php';
load_functions($phase_b, array_map(static fn($name) => 'bvmgr_ticketing_v2_' . $name, [
    'ajax_apply_template', 'templates_apply_to_plan', 'ajax_clear_config', 'delete_config_if_unchanged',
]));

function bvmgr_ticketing_v2_templates_get_all() {
    return ['fixture' => ['config' => $GLOBALS['template_config']]];
}
function bvmgr_ticketing_v2_resolve_template_apply_show_datetime(...$args) { return ''; }
function bvmgr_ticketing_v2_ajax_send_success($data) { $GLOBALS['success'] = $data; }
function metadata_exists($type, $id, $key) { return array_key_exists($key, $GLOBALS['meta']); }
function delete_post_meta($id, $key, $value = '') {
    $GLOBALS['deletes'][] = $key;
    if ($key === 'config') {
        if ($GLOBALS['delete_mode'] === 'reject') return false;
        if ($GLOBALS['delete_mode'] === 'pretend') return true;
        if ($GLOBALS['delete_mode'] === 'competing') $GLOBALS['meta'][$key] = $GLOBALS['competing'];
    }
    $value = wp_unslash($value);
    if ($value !== '' && ($GLOBALS['meta'][$key] ?? '') !== $value) return false;
    unset($GLOBALS['meta'][$key]);
    return true;
}
function editor_operation(string $operation, ?string $revision): void {
    $GLOBALS['success'] = $GLOBALS['error'] = null;
    $_POST = ['plan_id' => 901, 'template_id' => 'fixture'];
    if ($revision !== null) $_POST['config_revision'] = $revision;
    $handler = 'bvmgr_ticketing_v2_ajax_' . $operation;
    $handler();
}

$template_config = $baseline;
$template_config['entitlements'][0]['more_info'] = 'Template replacement';
foreach (['apply_template', 'clear_config'] as $operation) {
    $meta = ['config' => $baseline, '_vms_ticketing_ga_image_id' => 88];
    $writes = $legacy_image_sync = $image_sync = 0;
    $deletes = $cache_purges = [];
    $write_mode = $delete_mode = 'normal';
    $editor_a = bvmgr_ticketing_v2_get_admin_config_snapshot(901);
    $editor_b = $editor_a;
    $editor_b['config']['entitlements'][0]['short_desc'] = 'Saved by editor B';
    save_config($editor_b['config'], 0, $editor_b['revision']);
    $saved_b = $meta;
    $counts = [$writes, $legacy_image_sync, $image_sync, $cache_purges];
    editor_operation($operation, $editor_a['revision']);
    expect($success === null && $error[0] === 409 && $error[1]['code'] === 'config_revision_conflict',
        "$operation rejects editor A after editor B saves");
    expect($meta === $saved_b && $deletes === [] &&
        [$writes, $legacy_image_sync, $image_sync, $cache_purges] === $counts,
        "$operation stale rejection preserves stored config and side-effect counts");
    expect(str_contains($error[1]['message'], 'another tab'), "$operation conflict gives comparison instructions");
    editor_operation($operation, null);
    expect($success === null && $error[0] === 409 && $meta === $saved_b && $deletes === [],
        "$operation requires a revision");
    editor_operation($operation, bvmgr_ticketing_v2_get_admin_config_snapshot(901)['revision']);
    if ($operation === 'apply_template') {
        expect($error === null && $success['config'] === $meta['config'] &&
            $success['config_revision'] === bvmgr_ticketing_v2_config_revision($meta['config']) &&
            $meta['config']['entitlements'][0]['more_info'] === 'Template replacement',
            'current template apply returns the verified config revision');
        editor_operation('apply_template', $success['config_revision']);
        expect($error === null && $success['config_revision'] === bvmgr_ticketing_v2_config_revision($meta['config']),
            'repeated current template apply succeeds');
    } else {
        expect($error === null && !array_key_exists('config', $meta) &&
            !array_key_exists('_vms_ticketing_ga_image_id', $meta) &&
            $success['config_revision'] === bvmgr_ticketing_v2_config_revision(get_post_meta(901, 'config', true)),
            'current clear verifies config absence and returns the readback revision');
        $deletes = [];
        editor_operation('clear_config', $success['config_revision']);
        expect($error === null && !in_array('config', $deletes, true),
            'already absent clear avoids an unconditional config delete');
    }
}

foreach (['reject', 'pretend', 'competing'] as $delete_mode) {
    $meta = ['config' => $baseline, '_vms_ticketing_ga_image_id' => 88];
    $deletes = [];
    $competing = $baseline;
    $competing['entitlements'][0]['more_info'] = 'Intervening save';
    editor_operation('clear_config', bvmgr_ticketing_v2_config_revision($baseline));
    expect($success === null && $error[0] === 409 && $error[1]['code'] === 'config_storage_unverified' &&
        $meta['config'] === ($delete_mode === 'competing' ? $competing : $baseline) &&
        $meta['_vms_ticketing_ga_image_id'] === 88 && $deletes === ['config'],
        "$delete_mode clear cannot acknowledge success or remove legacy image metadata");
}
$delete_mode = 'normal';
$escaped = $baseline;
$escaped['entitlements'][0]['more_info'] = "Guest's path C:\\fixture";
$meta = ['config' => $escaped];
editor_operation('clear_config', bvmgr_ticketing_v2_config_revision($escaped));
expect($error === null && !array_key_exists('config', $meta), 'conditional clear preserves metadata slashing semantics');

$meta = [];
$write_mode = 'normal';
$initialized = bvmgr_ticketing_v2_templates_apply_to_plan(901, 'fixture');
expect($initialized['ok'] && $initialized['config'] === $meta['config'],
    'internal default-template initialization still works without an editor token');
$meta = [];
editor_operation('apply_template', bvmgr_ticketing_v2_config_revision(''));
expect($error === null && $success['config'] === $meta['config'],
    'editor default-template apply works from an absent config revision');

$meta = ['config' => $baseline];
$write_mode = 'competing';
$competing = $baseline;
$competing['entitlements'][0]['short_desc'] = 'Intervening template save';
editor_operation('apply_template', bvmgr_ticketing_v2_config_revision($baseline));
expect($success === null && $error[0] === 409 && $meta['config'] === $competing,
    'template apply preserves an intervening conditional-write conflict');
