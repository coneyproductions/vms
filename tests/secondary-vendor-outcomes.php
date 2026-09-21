<?php
declare(strict_types=1);
// This integration test must run only in the disposable compatibility database.
if (!getenv('VMS_TEST_WP_LOAD') || getenv('BVM_DISPOSABLE_DB_GUARDED') !== '1') {
 throw new RuntimeException('Use a contained disposable WordPress fixture.');
}
require_once __DIR__ . '/bootstrap-wordpress.php';
vms_tests_require_wordpress(__DIR__);
if (DB_NAME !== 'bvm_integration_source' || DB_HOST !== 'localhost:' . getenv('BVM_DISPOSABLE_DB_SOCKET')) throw new RuntimeException('Disposable database required');
set_exception_handler(static function (Throwable $e): void { fwrite(STDERR, $e->getMessage() . '\n' . $e->getTraceAsString() . '\n'); exit(1); });
wp_set_current_user(1);
$assertions = 0;
$check = static function ($ok, string $message) use (&$assertions): void { ++$assertions; if (!$ok) throw new RuntimeException($message); };
$plan = wp_insert_post(array('post_type' => 'vms_event_plan', 'post_status' => 'draft', 'post_title' => 'Sep 12 participation fixture'));
$vendor = wp_insert_post(array('post_type' => 'vms_vendor', 'post_status' => 'publish', 'post_title' => 'Fixture food truck'));
$event = wp_insert_post(array('post_type' => 'tribe_events', 'post_status' => 'publish', 'post_title' => 'Sep 12 public fixture'));
$term = term_exists('food_truck', 'vms_vendor_type') ?: wp_insert_term('Food Vendor', 'vms_vendor_type', array('slug' => 'food_truck'));
wp_set_object_terms($vendor, array((int) $term['term_id']), 'vms_vendor_type');
update_post_meta($vendor, bvmgr_meta_key('vendor', 'public_profile_enabled'), '1');
update_post_meta($plan, '_vms_event_date', '2026-09-12');
update_post_meta($plan, '_vms_tec_event_id', $event);
update_post_meta($event, '_vms_event_plan_id', $plan);
$result = bvmgr_event_plan_write_secondary_vendor_assignments($plan, array('food_truck' => array('type_slug' => 'food_truck', 'mode' => 'standard', 'slot_limit' => 1, 'vendor_ids' => array($vendor))));
$check(!is_wp_error($result), 'Assignment fixture setup failed');
$check(bvmgr_vendor_profiles_get_event_plan_for_tec_event($event) === $plan, 'TEC fixture association failed');
$settings = get_option('vms_settings', array());
$settings['calendar_public_show_vendors'] = true;
$settings['calendar_public_show_vendors_by_type'] = array('food_truck' => true);
update_option('vms_settings', $settings);
$assignmentBefore = get_post_meta($plan, '_vms_secondary_vendor_assignments_v1', true);
$flatBefore = get_post_meta($plan, '_vms_secondary_vendor_ids', true);
$indexBefore = get_post_meta($plan, '_vms_secondary_vendor_id', false);
$adminBefore = bvmgr_calendar_prepare_vendor_groups($plan, 0, 'admin', 0);
$vendorBefore = bvmgr_calendar_prepare_vendor_groups($plan, 0, 'vendor', $vendor);
$historical = wp_insert_post(array('post_type' => 'vms_feedback', 'post_status' => 'private', 'post_title' => 'Historical feedback fixture'));
update_post_meta($historical, '_fixture_feedback', array('vendor_id' => $vendor, 'comment' => 'Historical response remains intact'));
$historyBefore = get_post_meta($historical);
$names = static function (array $groups): array {
 $ids = array(); foreach ($groups as $group) foreach (($group['vendors'] ?? $group['cards'] ?? array()) as $row) $ids[] = (int) ($row['vendor_id'] ?? 0); return $ids;
};
$eventRow = array('event_plan_id' => $plan, 'title' => 'Fixture', 'public_url' => get_permalink($event));
foreach (array('legacy' => true, 'scheduled' => true, 'attended' => true, 'cancelled' => false, 'no_show' => false) as $outcome => $visible) {
 if ($outcome !== 'legacy') {
  $cacheBefore = get_option(BVMGR_CALENDAR_FEED_CACHE_BUST_OPTION);
  usleep(2000);
  $saved = bvmgr_event_plan_save_secondary_vendor_outcomes($plan, array($vendor => $outcome));
  $check(!is_wp_error($saved) && $saved['changed'], $outcome . ': save failed');
  $check(get_option(BVMGR_CALENDAR_FEED_CACHE_BUST_OPTION) !== $cacheBefore, $outcome . ': cache not invalidated');
 }
 $check(bvmgr_event_plan_secondary_vendor_outcome($plan, $vendor) === ($outcome === 'legacy' ? 'scheduled' : $outcome), $outcome . ': read failed');
 $check(bvmgr_event_plan_vendor_customer_eligible($plan, $vendor) === $visible, $outcome . ': eligibility incorrect');
 $context = bvmgr_feedback_get_event_context($plan, true);
 $check(in_array($vendor, array_column($context['secondary_vendors'], 'id'), true) === $visible, $outcome . ': survey context incorrect');
 $check(in_array($vendor, array_column(bvmgr_feedback_get_event_context($plan)['secondary_vendors'], 'id'), true), $outcome . ': internal context lost vendor');
 ob_start(); bvmgr_feedback_render_public_survey($plan, bvmgr_feedback_public_token($plan)); $survey = ob_get_clean();
 $check(str_contains($survey, 'Fixture food truck') === $visible, $outcome . ': questionnaire incorrect');
 $check(str_contains($survey, 'Website / Ticket Purchase Experience'), 'Website questions removed');
 $check(str_contains($survey, 'data-vms-feedback-vendor-details') === $visible, $outcome . ': vendor conditional fields changed');
 $check(in_array($vendor, bvmgr_vendor_profiles_get_secondary_vendors_for_tec_event($event), true) === $visible, $outcome . ': public IDs incorrect');
 $check(in_array($vendor, bvmgr_vendor_profiles_get_secondary_vendors_for_tec_event($event, 'food_truck'), true) === $visible, $outcome . ': typed public IDs incorrect');
 $check(in_array($vendor, $names(bvmgr_vendor_profiles_build_event_vendor_groups($event)), true) === $visible, $outcome . ': event cards incorrect');
 $check(in_array($vendor, $names(bvmgr_calendar_prepare_vendor_groups($plan, 0, 'public', 0)), true) === $visible, $outcome . ': public feed incorrect');
 $slots = bvmgr_public_calendar_vendor_slots($eventRow);
 $check(in_array('Fixture food truck', array_column($slots, 'name'), true) === $visible, $outcome . ': public calendar slots incorrect');
 if (!$visible) {
  $check(bvmgr_vendor_profiles_render_event_teaser($vendor, $event) === '', $outcome . ': explicit vendor teaser leaked');
  $staleRow = $eventRow + array('vendor_groups' => array(array('vendors' => array(array('vendor_id' => $vendor, 'display_name' => 'Fixture food truck')))));
  $check(!in_array('Fixture food truck', array_column(bvmgr_public_calendar_vendor_slots($staleRow), 'name'), true), 'Stale calendar fallback leaked');
 }
 $check(bvmgr_calendar_prepare_vendor_groups($plan, 0, 'admin', 0) === $adminBefore, 'Internal assignment/capacity changed');
 $check(bvmgr_calendar_prepare_vendor_groups($plan, 0, 'vendor', $vendor) === $vendorBefore, 'Vendor calendar/availability changed');
 $check(get_post_meta($plan, '_vms_secondary_vendor_assignments_v1', true) === $assignmentBefore && get_post_meta($plan, '_vms_secondary_vendor_ids', true) === $flatBefore && get_post_meta($plan, '_vms_secondary_vendor_id', false) === $indexBefore, 'Outcome changed assignment membership/index');
 $check(get_post_meta($historical) === $historyBefore, 'Historical feedback changed');
 ob_start(); bvmgr_event_plan_render_secondary_vendor_outcomes($plan); $panel = ob_get_clean();
 $check(str_contains($panel, 'Fixture food truck') && str_contains($panel, 'Save Event Participation'), 'Internal outcome control missing');
 $check(!str_contains($panel, 'name="vms_secondary_vendor_assignments'), 'Outcome control entangled with assignment serialization');
}
$before = get_post_meta($plan, '_vms_secondary_vendor_outcomes_v1', true);
foreach (array(array($vendor => 'removed'), array(999999 => 'cancelled'), array($vendor => array('cancelled'))) as $bad) {
 $check(is_wp_error(bvmgr_event_plan_save_secondary_vendor_outcomes($plan, $bad)), 'Invalid outcome/assignment accepted');
 $check(get_post_meta($plan, '_vms_secondary_vendor_outcomes_v1', true) === $before, 'Invalid write partially applied');
}
$check(!bvmgr_event_plan_save_secondary_vendor_outcomes($plan, array($vendor => 'no_show'))['changed'], 'Unchanged outcome marked dirty');
$check(bvmgr_event_plan_vendor_customer_eligible($plan + 10000, $vendor), 'Outcome leaked across event');
// Legacy assignment-only storage still participates without migration.
delete_post_meta($plan, '_vms_secondary_vendor_assignments_v1');
$check(!bvmgr_event_plan_vendor_customer_eligible($plan, $vendor), 'Legacy assignment fallback lost outcome');
update_post_meta($plan, '_vms_secondary_vendor_outcomes_v1', array($vendor => 'future_unknown_value'));
$check(bvmgr_event_plan_secondary_vendor_outcome($plan, $vendor) === 'scheduled', 'Unknown stored value not backward compatible');
// Exercise the actual authenticated outcome endpoint and its nonce/capability gates.
if (!defined('DOING_AJAX')) define('DOING_AJAX', true);
class BvmOutcomeAjaxEnd extends RuntimeException {}
add_filter('wp_die_ajax_handler', static function () { return static function ($message = ''): void { throw new BvmOutcomeAjaxEnd((string) $message); }; });
$ajax = static function (array $post): array {
 $_POST = $post; $_REQUEST = $post;
 ob_start();
 try { bvmgr_event_plan_save_secondary_vendor_outcomes_ajax(); } catch (BvmOutcomeAjaxEnd $e) { $body = ob_get_clean(); return array('json' => json_decode($body, true), 'die' => $e->getMessage()); }
 ob_end_clean(); throw new RuntimeException('AJAX did not terminate');
};
$nonce = wp_create_nonce('bvmgr_secondary_vendor_outcomes');
$response = $ajax(array('post_id' => $plan, 'nonce' => $nonce, 'outcomes' => array($vendor => 'cancelled')));
$check(!empty($response['json']['success']) && bvmgr_event_plan_secondary_vendor_outcome($plan, $vendor) === 'cancelled', 'Authorized AJAX save failed');
$before = get_post_meta($plan, '_vms_secondary_vendor_outcomes_v1', true);
$response = $ajax(array('post_id' => $plan, 'nonce' => 'invalid', 'outcomes' => array($vendor => 'attended')));
$check($response['die'] === '-1' && get_post_meta($plan, '_vms_secondary_vendor_outcomes_v1', true) === $before, 'Invalid nonce changed outcome');
wp_set_current_user(0);
$response = $ajax(array('post_id' => $plan, 'nonce' => $nonce, 'outcomes' => array($vendor => 'attended')));
$check(empty($response['json']['success']) && get_post_meta($plan, '_vms_secondary_vendor_outcomes_v1', true) === $before, 'Unauthorized AJAX changed outcome');
$check(!has_action('wp_ajax_nopriv_vms_secondary_vendor_outcomes'), 'Outcome endpoint exposed unauthenticated hook');
echo "Secondary vendor outcomes: {$assertions} assertions passed in disposable WordPress.\n";
