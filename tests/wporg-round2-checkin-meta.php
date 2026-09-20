<?php
define('ABSPATH', __DIR__ . '/');
$meta = array(1 => array('_checkin_close_at' => 'legacy'), 2 => array(), 3 => array('_checkin_close_at' => 'foreign', '_bvmgr_checkin_close_at' => 'ignored'));
$writes = array();
function add_filter($hook, $callback, $priority, $args) { if ($hook !== 'get_post_metadata' || $args !== 4) throw new RuntimeException('Unexpected hook'); }
function get_post_type($id) { return array(1 => 'vms_event_plan', 2 => 'tribe_events', 3 => 'post')[$id] ?? ''; }
function metadata_exists($type, $id, $key) { global $meta; return array_key_exists($key, $meta[$id] ?? array()); }
function get_post_meta($id, $key, $single = true) { global $meta; $override = bvmgr_checkin_close_legacy_meta_read(null,$id,$key,$single); if ($override !== null) return $single && is_array($override) ? $override[0] : $override; $value = $meta[$id][$key] ?? ''; return $single ? $value : (isset($meta[$id][$key]) ? array($value) : array()); }
function wp_cache_get($id, $group) { return false; }
function update_meta_cache($type, $ids) { global $meta; return array($ids[0] => array_map(static function ($value) { return array($value); }, $meta[$ids[0]] ?? array())); }
function maybe_unserialize($value) { return $value; }
function update_post_meta($id, $key, $value) { global $meta,$writes; $writes[] = $key; $meta[$id][$key] = $value; }
function delete_post_meta($id, $key) { global $meta; unset($meta[$id][$key]); }
function absint($value) { return abs((int) $value); }
function wp_timezone() { return new DateTimeZone('UTC'); }
function expect($condition, $label) { if (!$condition) throw new RuntimeException($label); echo "PASS $label\n"; }
require dirname(__DIR__) . '/includes/helpers/checkin-close.php';
expect(get_post_meta(1,'_checkin_close_at',true) === 'legacy' && !$writes, 'legacy read remains unchanged and write-free');
expect(get_post_meta(1,'_bvmgr_checkin_close_at',true) === 'legacy' && get_post_meta(1,'_bvmgr_checkin_close_at',false) === array('legacy') && !$writes, 'registry-based canonical readers retain legacy-only records without writes');
$meta[1]['_bvmgr_checkin_close_at'] = 'canonical';
expect(get_post_meta(1,'_checkin_close_at',true) === 'canonical' && get_post_meta(1,'_checkin_close_at',false) === array('canonical'), 'legacy readers see canonical scalar/list values');
$meta[1]['_bvmgr_checkin_close_at'] = '';
expect(get_post_meta(1,'_checkin_close_at',true) === '', 'canonical empty value does not resurrect legacy');
expect(get_post_meta(3,'_checkin_close_at',true) === 'foreign', 'unrelated post type is untouched');
expect(bvmgr_checkin_close_legacy_meta_read('prior',1,'_checkin_close_at',true) === 'prior', 'prior metadata filter result is preserved');
$meta[1]['_vms_event_plan_start_datetime'] = '2026-09-09 18:00:00';
$meta[1]['_vms_event_plan_end_datetime'] = '2026-09-09 20:00:00';
$r = bvmgr_event_plan_sync_checkin_close_meta_to_tec(1,2);
expect($writes === array('_bvmgr_checkin_close_at','_bvmgr_checkin_close_at'), 'Event Plan and linked TEC writes are prefixed');
expect(get_post_meta(2,'_checkin_close_at',true) === $r['checkin_close_at'] && $meta[1]['_checkin_close_at'] === 'legacy', 'Ops Console legacy read contract preserved without rewriting historical data');
unset($meta[1]['_vms_event_plan_start_datetime']);
bvmgr_event_plan_sync_checkin_close_meta_to_tec(1,2);
expect(get_post_meta(1,'_checkin_close_at',true) === '' && get_post_meta(2,'_checkin_close_at',true) === '', 'explicit removal clears both representations');
