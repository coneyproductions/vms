<?php
/** Exercise actual accessors with legacy, canonical, empty and unrelated-post fixtures. */
$source = file_get_contents(__DIR__ . '/../includes/integrations/ticketing-verifications.php');
$begin = strpos($source, '/** Map only metadata owned');
$end = strpos($source, "if (!function_exists('bvmgr_ticketing_verification_upload_settings_option_key'))", $begin);
eval(substr($source, $begin, $end - $begin));
$meta = array(1 => array('program' => 'legacy', 'proof_file_path' => '/legacy/proof'), 2 => array());
$writes = 0;
function bvmgr_ticketing_verification_request_post_types() { return array('vms_verify_req', 'vms_verification_request'); }
function get_post_type($id) { return $id === 1 ? 'vms_verify_req' : 'post'; }
function metadata_exists($kind, $id, $key) { global $meta; return array_key_exists($key, $meta[$id]); }
function get_post_meta($id, $key, $single = true) { global $meta; return $meta[$id][$key] ?? ''; }
function update_post_meta($id, $key, $value) { global $meta, $writes; ++$writes; $meta[$id][$key] = $value; return true; }
function delete_post_meta($id, $key) { global $meta, $writes; ++$writes; $had = isset($meta[$id][$key]); unset($meta[$id][$key]); return $had; }
function expect($condition, $label) { if (!$condition) throw new RuntimeException($label); echo "PASS $label\n"; }
expect(bvmgr_verification_meta_get(1, 'program') === 'legacy' && $writes === 0, 'legacy read performs zero writes');
expect(bvmgr_verification_meta_update(1, 'program', 'new'), 'owned record writes');
expect($meta[1]['bvmgr_verification_program'] === 'new' && $meta[1]['program'] === 'legacy', 'new writes are prefixed; historical value preserved');
expect(bvmgr_verification_meta_get(1, 'program') === 'new', 'canonical value wins');
bvmgr_verification_meta_update(1, 'program', '');
expect(bvmgr_verification_meta_get(1, 'program') === '', 'empty canonical value does not resurrect legacy');
bvmgr_verification_meta_update(1, 'proof_file_path', 'new-key');
bvmgr_verification_meta_delete(1, 'proof_file_path');
expect(bvmgr_verification_meta_get(1, 'proof_file_path') === '' && !isset($meta[1]['proof_file_path']), 'explicit deletion removes both references');
$before = $writes;
expect(!bvmgr_verification_meta_update(2, 'program', 'bad') && !bvmgr_verification_meta_delete(2, 'program') && $writes === $before, 'unrelated post cannot be mutated');
expect(!bvmgr_verification_meta_update(1, '_other_plugin', 'bad') && $writes === $before, 'unknown keys cannot be mutated');
$q = bvmgr_verification_meta_query('user_id', '7');
expect($q['relation'] === 'OR' && $q[0]['key'] === 'bvmgr_verification_user_id' && $q[1][0]['compare'] === 'NOT EXISTS' && $q[1][1]['key'] === 'user_id', 'query fallback requires absent canonical value');
