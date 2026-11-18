<?php
defined('ABSPATH') || exit;

function bvmgr_tasks_template_timing(array $template): array
{
    if (!empty($template['timing_json'])) {
        $timing=json_decode($template['timing_json'],true); if (!is_array($timing)) throw new RuntimeException('invalid_template_timing'); return $timing;
    }
    $mode=$template['due_mode']??'none';
    return array('due_kind'=>$mode==='none'?'none':'datetime','due_source'=>$mode==='event_offset'?'event_offset':($mode==='fixed_datetime'?'event_date':'fixed'),
        'due_offset'=>(int)($template['due_offset_minutes']??0),'due_clock'=>($template['due_time_local']??'')?:'10:00','schedule_source'=>'none','cancellation_policy'=>'review');
}
function bvmgr_tasks_reconcile_event_records(int $plan): array
{
    $event=bvmgr_tasks_event_authority($plan); $result=array('event_id'=>$plan,'resolved'=>0,'multiple'=>0,'none'=>0,'timing_updated'=>0);
    foreach (bvmgr_tasks_get_instances_for_event($plan) as $row) {
        if ($row['status']!=='open') continue;
        $change=array(); $timing=json_decode((string)($row['timing_json']??''),true);
        if ($timing) {
            $next=$timing;
            if (!$event) $next['review']='event_occurrence_unavailable';
            elseif (in_array($event['status'],array('cancelled','archived'),true)) {
                if ($event['status']==='cancelled' && ($timing['cancellation_policy']??'review')==='cancel') $change=array('status'=>'canceled','cancel_reason'=>'Event canceled; explicit task policy');
                $next['review']=($timing['cancellation_policy']??'review')==='review'?'event_'.$event['status']:'';
            } else {
                try { $next=bvmgr_tasks_timing($timing,$plan); } catch (Throwable $e) { $next['review']=$e->getMessage(); }
            }
            $change['timing_json']=wp_json_encode($next); $change['due_at_local']=$next['due_local'];
        }
        if ($row['assignment_mode']==='scheduled_role' && empty($row['assignment_locked'])) {
            $resolved=bvmgr_tasks_scheduled_person($plan,(string)$row['role_key']);
            $result[$resolved['status']==='single'?'resolved':$resolved['status']]++;
            $change['assignee_user_id']=$event && !in_array($event['status'],array('cancelled','archived'),true) ? ($resolved['assignee_user_id']?:null) : null;
        }
        if ($change) { $after=bvmgr_tasks_apply_row($row,$change,'event_reconciled'); if ((int)$after['revision']!==(int)$row['revision']) $result['timing_updated']++; }
    }
    return $result;
}
function bvmgr_tasks_generate_committed_event(int $plan,array $args=array())
{
    return bvmgr_tasks_atomic(static function()use($plan,$args){
        global $wpdb;
        bvmgr_tasks_reconcile_event_records($plan);
        $event=bvmgr_tasks_event_authority($plan);
        if (!$event) throw new RuntimeException('event_occurrence_unavailable');
        $summary=array('event_id'=>$plan,'events_checked'=>1,'instances_created'=>0,'instances_superseded'=>0,'assignment_resolutions_applied'=>0,'duplicate_suppressed'=>0,'warnings'=>array(),'allow_supersede'=>0);
        if (!in_array($event['status'],array('ready','published','tentative','confirmed'),true)) { $summary['warnings'][]='Event is not eligible for new task generation.'; return $summary; }
        $seen=array();
        foreach (bvmgr_tasks_get_applicable_checklists((int)$event['venue_id'],$event['event_type']) as $checklist) {
            foreach (bvmgr_tasks_get_checklist_items((int)$checklist['id']) as $item) {
                $template_id=(int)$item['task_template_id'];
                if (($item['overrides_state']??'missing')==='invalid') { $summary['warnings'][]='Invalid checklist overrides; generation skipped.'; continue; }
                if (isset($seen[$template_id])) { $summary['duplicate_suppressed']++; continue; } $seen[$template_id]=true;
                $existing=(array)$wpdb->get_results($wpdb->prepare('SELECT id FROM %i WHERE event_id=%d AND task_template_id=%d ORDER BY id',bvmgr_tasks_table_name('task_instances'),$plan,$template_id),ARRAY_A);
                if ($existing) { $summary['duplicate_suppressed']++; if (count($existing)>1) $summary['warnings'][]='Historical duplicate template instances require review; no new task created.'; continue; }
                $template=bvmgr_tasks_get_task_template($template_id); if (!$template || empty($template['is_active']) || $template['scope']!=='event') continue;
                $effective=bvmgr_tasks_merge_template_with_overrides($template,(array)($item['overrides']??array()));
                $assignment=bvmgr_tasks_resolve_assignment_for_instance($plan,$effective);
                $timing=bvmgr_tasks_template_timing(array_merge($effective,array('timing_json'=>$template['timing_json']??null)));
                if (array_key_exists('due_offset_minutes',(array)($item['overrides']??array()))) $timing['due_offset']=(int)$effective['due_offset_minutes'];
                $input=array_merge($effective,$assignment,array('task_template_id'=>$template_id,'origin_checklist_id'=>(int)$checklist['id'],'event_id'=>$plan,
                    'venue_id'=>$event['venue_id'],'event_type'=>$event['event_type'],'timing'=>$timing,'generation_key'=>'event:'.$plan.':template:'.$template_id));
                bvmgr_tasks_create_record($input); $summary['instances_created']++;
            }
        }
        return $summary;
    });
}
function bvmgr_tasks_reconcile_event(int $plan): array
{
    if (!bvmgr_tasks_authority_ready() || bvmgr_staffing_transaction_active()) return array('event_id'=>$plan,'deferred'=>true);
    $result=bvmgr_tasks_atomic(static fn()=>bvmgr_tasks_reconcile_event_records($plan));
    return is_wp_error($result)?array('event_id'=>$plan,'error'=>$result->get_error_code()):$result;
}

/** Template definitions are serialized with generation; instances are snapshots, never rewritten here. */
function bvmgr_tasks_save_definition(string $kind,array $payload,int $id=0)
{
    $allowed=$kind==='task_templates'?bvmgr_tasks_current_user_can_manage_templates():bvmgr_tasks_current_user_can_manage_checklists();
    if (!$allowed) return new WP_Error('forbidden','Template manager permission required.');
    return bvmgr_tasks_atomic(static function()use($kind,$payload,$id){
        global $wpdb; $table=bvmgr_tasks_table_name($kind);
        $before=$id?$wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d',$table,$id),ARRAY_A):array();
        if ($id && !$before) throw new RuntimeException('template_missing');
        $hash_before=$before; if ($before && $kind==='checklist_templates') $hash_before['_items']=bvmgr_tasks_get_checklist_items($id);
        if (isset($payload['expected_hash']) && !hash_equals(hash('sha256',wp_json_encode($hash_before)),(string)$payload['expected_hash'])) throw new RuntimeException('stale_template');
        $saved=$kind==='task_templates'?bvmgr_tasks_upsert_task_template_row($payload,$id):bvmgr_tasks_upsert_checklist_template_row($payload,$id);
        if (is_wp_error($saved)) return $saved;
        if ($kind==='task_templates') {
            $prior_timing=json_decode((string)($before['timing_json']??''),true)?:array();
            $due=bvmgr_tasks_template_timing(array_merge($before?:array(),$payload,array('timing_json'=>null)));
            unset($due['schedule_source'],$due['cancellation_policy']);
            $timing=array_merge(array('schedule_source'=>'none','cancellation_policy'=>'review'),$prior_timing,$due,(array)($payload['timing']??array()));
            // Relative template rules are validated against an event on generation; reject invalid shape now.
            if (!in_array($timing['due_source']??'fixed',array('fixed','event_offset','event_date'),true) || !in_array($timing['schedule_source']??'none',array('none','fixed','event_offset'),true)) throw new RuntimeException('invalid_template_timing');
            $wpdb->update($table,array('timing_json'=>wp_json_encode($timing)),array('id'=>$saved));
        }
        $after=$wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d',$table,$saved),ARRAY_A);
        $comparison=$after; if ($before) $comparison['updated_at']=$before['updated_at'];
        if ($before && $before===$comparison) { $wpdb->update($table,array('updated_at'=>$before['updated_at']),array('id'=>$saved)); return $saved; }
        if ($before!==$after) $wpdb->insert(bvmgr_tasks_table_name('task_logs'),array('task_instance_id'=>0,'action'=>'template_saved','actor_user_id'=>get_current_user_id(),
            'details'=>wp_json_encode(array('entity'=>$kind,'id'=>$saved,'before'=>$before,'after'=>$after)),'created_at'=>bvmgr_tasks_now_utc_mysql()));
        return $saved;
    });
}

add_action('vms_cancellation_job_created', 'bvmgr_tasks_reconcile_event', 20, 1);
add_action('vms_cancellation_job_ran', 'bvmgr_tasks_reconcile_event', 20, 1);

// Individual staffing transitions publish this only after their transaction commits.
add_action('vms_staffing_assignment_transitioned', static function(array $event): void {
    if (!empty($event['event_plan_id'])) bvmgr_tasks_reconcile_event((int)$event['event_plan_id']);
}, 20, 1);

// This save hook runs after canonical Event Plan metadata writers. Reads never invoke it.
add_action('save_post_vms_event_plan',static function(int $id): void {
    if (wp_is_post_revision($id) || wp_is_post_autosave($id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) return;
    bvmgr_tasks_reconcile_event($id);
}, 100, 1);
