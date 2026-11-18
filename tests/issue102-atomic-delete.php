<?php
declare(strict_types=1);

require __DIR__ . '/issue102-template-clear.php';

$write_mode = $delete_mode = 'normal';
$wpdb = new Issue102MetadataDatabase();
$meta = ['config' => $baseline, '_vms_ticketing_ga_image_id' => 88];
$metadata_hook_events = $deletes = $cache_purges = [];
$competing = $baseline;
// A case-only edit must remain different even with a case-insensitive SQL collation.
$competing['entitlements'][0]['short_desc'] = 'ORIGINAL A';
$verified_b = null;
$atomic_delete_interleave = function() use ($competing, $baseline, &$verified_b) {
    $verified_b = bvmgr_ticketing_v2_set_config(901, $competing, bvmgr_ticketing_v2_config_revision($baseline));
};
editor_operation('clear_config', bvmgr_ticketing_v2_config_revision($baseline));
expect($verified_b !== null && $verified_b['changed'] && $verified_b['config'] === $competing,
    'intervening setter commits and verifies its edit after deletion IDs are selected');
expect($success === null && $error[0] === 409 && $meta['config'] === $competing,
    'atomic deletion preserves the committed competing edit and returns 409');
$completed = array_values(array_filter($metadata_hook_events, static fn($event) => str_starts_with($event[0], 'deleted_')));
expect(!$completed && $meta['_vms_ticketing_ga_image_id'] === 88 && !$cache_purges,
    'conflicting clear emits no deletion completion hooks or legacy-image cleanup');
expect(count($wpdb->queries) === 2, 'conflicting clear reaches the conditional SQL mutation');
unset($atomic_delete_interleave);

$wpdb = new Issue102MetadataDatabase();
$meta = ['config' => $baseline, '_vms_ticketing_ga_image_id' => 88];
$metadata_hook_events = [];
editor_operation('clear_config', bvmgr_ticketing_v2_config_revision($baseline));
expect($success !== null && !array_key_exists('config', $meta), 'matching conditional SQL deletion succeeds');
expect(array_column($metadata_hook_events, 0) === [
    'delete_post_metadata', 'delete_post_meta', 'delete_postmeta', 'deleted_post_meta', 'deleted_postmeta',
], 'matching deletion preserves WordPress filter and legacy/current hook order');
expect($metadata_hook_events[0][1] === [901, 'config', $baseline, false] &&
    $metadata_hook_events[3][1] === [['1'], 901, 'config', $baseline],
    'deletion filters and completion hooks retain native raw-value and ID payloads');

$wpdb = new Issue102MetadataDatabase();
$wpdb->rows = [1 => $baseline, 2 => $baseline];
$meta = ['config' => $baseline, '_vms_ticketing_ga_image_id' => 88];
$metadata_hook_events = [];
$atomic_delete_interleave = function() use ($wpdb, $competing) {
    $wpdb->rows[2] = $competing;
    $wpdb->sync_meta($wpdb->rows);
};
editor_operation('clear_config', bvmgr_ticketing_v2_config_revision($baseline));
expect($success === null && $error[0] === 409 && $wpdb->rows === [2 => $competing],
    'duplicate-row conflict preserves the changed row while deleting only unchanged bytes');
$completed = array_values(array_filter($metadata_hook_events, static fn($event) => $event[0] === 'deleted_post_meta'));
expect(count($completed) === 1 && $completed[0][1][0] === ['1'] &&
    $meta['_vms_ticketing_ga_image_id'] === 88,
    'duplicate-row completion hooks identify only rows actually deleted');
unset($atomic_delete_interleave);

$wpdb = new Issue102MetadataDatabase();
$wpdb->fail_id = 1;
$meta = ['config' => $baseline, '_vms_ticketing_ga_image_id' => 88];
$metadata_hook_events = [];
editor_operation('clear_config', bvmgr_ticketing_v2_config_revision($baseline));
expect($success === null && $error[0] === 409 && $meta['config'] === $baseline,
    'conditional SQL failure reports error without changing stored configuration');
expect(!array_filter($metadata_hook_events, static fn($event) => str_starts_with($event[0], 'deleted_')),
    'failed SQL emits no deletion completion hooks');

$wpdb = new Issue102MetadataDatabase();
$meta = ['config' => '', '_vms_ticketing_ga_image_id' => 88];
$atomic_delete_interleave = function() use ($competing) {
    bvmgr_ticketing_v2_set_config(901, $competing, bvmgr_ticketing_v2_config_revision(''));
};
editor_operation('clear_config', bvmgr_ticketing_v2_config_revision(''));
expect($success === null && $error[0] === 409 && $meta['config'] === $competing,
    'an empty stored value remains an exact conditional comparison instead of a wildcard');
unset($atomic_delete_interleave);
