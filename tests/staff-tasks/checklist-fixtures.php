<?php
/** Canonical definition writes and explicitly corrupt legacy JSON input, in disposable real SQL. */
require dirname(__DIR__) . '/helpers/current-wordpress-fixture.php';
bvmgr_tasks_install_authority();
$checks = 0;
function checklist_check($ok, string $label): void {
    global $checks;
    if (!$ok) throw new RuntimeException($label);
    $checks++;
}
$template = bvmgr_tasks_upsert_task_template(array('title'=>'Fixture gates','is_active'=>1,'scope'=>'event','assignment_mode'=>'person','due_mode'=>'none'));
$other = bvmgr_tasks_upsert_task_template(array('title'=>'Fixture general','is_active'=>1,'scope'=>'general','assignment_mode'=>'person','due_mode'=>'none'));
$list = bvmgr_tasks_upsert_checklist_template(array('name'=>'Fixture list','is_active'=>1,'scope'=>'event','apply_mode'=>'default_all_events'));
checklist_check(!is_wp_error($template) && !is_wp_error($other) && !is_wp_error($list), 'Canonical definitions created');
$items = array(array('task_template_id'=>$template,'sort_order'=>9,'overrides'=>array('required_default'=>0,'priority'=>'HIGH','assignment_mode'=>'person','due_offset_minutes'=>-30)), array('task_template_id'=>$other));
checklist_check(bvmgr_tasks_replace_checklist_items($list,$items) === true, 'Canonical replacement commits');
$rows = bvmgr_tasks_get_checklist_items($list);
checklist_check(count($rows) === 1 && (int)$rows[0]['task_template_id'] === $template, 'Cross-scope template excluded');
checklist_check((int)$rows[0]['sort_order'] === 9 && $rows[0]['overrides']['priority'] === 'high' && $rows[0]['overrides']['required_default'] === 0, 'Normalized overrides persist');
$logs = (int)$wpdb->get_var('SELECT COUNT(*) FROM '.bvmgr_tasks_table_name('task_logs'));
checklist_check(bvmgr_tasks_replace_checklist_items($list,$items) === true, 'Repeated replacement succeeds');
checklist_check((int)$wpdb->get_var('SELECT COUNT(*) FROM '.bvmgr_tasks_table_name('task_logs')) === $logs, 'Repeated normalized definition adds no audit');
$plan = wp_insert_post(array('post_type'=>'vms_event_plan','post_status'=>'draft','post_title'=>'Fixture checklist event'),true);
checklist_check(!is_wp_error($plan), 'Synthetic event created');
foreach(array('_vms_event_date'=>'2030-10-20','_vms_start_time'=>'20:00','_vms_end_time'=>'23:00','_vms_event_plan_status'=>'ready') as $key=>$value) update_post_meta($plan,$key,$value);
// This row deliberately represents corrupt persisted legacy JSON, not canonical creation.
$wpdb->update(bvmgr_tasks_table_name('checklist_items'),array('overrides_json'=>'{"priority":"urgent"}'),array('id'=>$rows[0]['id']));
$result = bvmgr_tasks_generate_for_event($plan);
checklist_check(!is_wp_error($result) && $result['instances_created'] === 0 && count($result['warnings']) > 0, 'Corrupt legacy overrides fail closed before generation');
checklist_check(bvmgr_tasks_get_instances_for_event($plan) === array(), 'Invalid input creates no task');
$wpdb->update(bvmgr_tasks_table_name('checklist_items'),array('overrides_json'=>null),array('id'=>$rows[0]['id']));
$result = bvmgr_tasks_generate_for_event($plan);
checklist_check(!is_wp_error($result) && $result['instances_created'] === 1, 'Missing legacy overrides use canonical template defaults');
$result = bvmgr_tasks_generate_for_event($plan);
checklist_check(!is_wp_error($result) && $result['instances_created'] === 0 && count(bvmgr_tasks_get_instances_for_event($plan)) === 1, 'Repeat generation preserves one identity');
echo "PASS $checks canonical checklist fixture assertions; all state owned by disposable database supervisor\n";
