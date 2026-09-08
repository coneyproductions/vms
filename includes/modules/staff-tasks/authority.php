<?php
/** Canonical commands and read projections for the existing Staff Tasks repository. */
defined('ABSPATH') || exit;

function bvmgr_tasks_authority_ready(): bool
{
    global $wpdb;
    if (get_option(bvmgr_tasks_db_option_key(), '') !== '1.3.0' || !bvmgr_tasks_db_ready()) return false;
    foreach (array('task_instances'=>array('revision','generation_key','timing_json'),'task_logs'=>array('operation_key'),'task_templates'=>array('timing_json')) as $kind=>$required) {
        $columns=(array)$wpdb->get_col($wpdb->prepare('SHOW COLUMNS FROM %i',bvmgr_tasks_table_name($kind)));
        if (array_diff($required,$columns)) return false;
    }
    foreach (array('task_instances'=>'generation_identity','task_logs'=>'task_operation') as $kind=>$key) {
        $indexes=(array)$wpdb->get_results($wpdb->prepare('SHOW INDEX FROM %i',bvmgr_tasks_table_name($kind)),ARRAY_A);
        $found=array_values(array_filter($indexes,static fn($r)=>$r['Key_name']===$key && (int)$r['Non_unique']===0 && $r['Sub_part']===null));
        if (count($found)!==1 || $found[0]['Column_name']!==($kind==='task_instances'?'generation_key':'operation_key')) return false;
    }
    return true;
	}
function bvmgr_tasks_atomic(callable $operation)
{
    global $wpdb;
    if (!empty($GLOBALS['bvmgr_tasks_transaction'])) return $operation();
    if (!bvmgr_tasks_authority_ready() || bvmgr_staffing_transaction_active() || get_class($wpdb) !== 'wpdb') return new WP_Error('tasks_unavailable', 'Staff Tasks requires its explicit database update and an independent transaction.');
    $original = $wpdb; $original->flush(); $wpdb = new BVMGR_Staffing_Transaction_DB($original);
    $lock = 'bvm_tasks_' . substr(hash('sha256', $wpdb->dbname . ':' . $wpdb->prefix), 0, 48);
    $locked = $started = $committing = false; $result = null;
    $guard = static function ($sql) {
        if (!empty($GLOBALS['bvmgr_tasks_transaction']) && empty($GLOBALS['bvmgr_tasks_transaction_control']) && preg_match('/^\s*(ALTER|CREATE|DROP|TRUNCATE|RENAME|LOCK|UNLOCK|BEGIN|START|COMMIT|ROLLBACK|SAVEPOINT|RELEASE|SET)\b/i', $sql)) throw new RuntimeException('nested_or_ddl_transaction');
        if (!empty($GLOBALS['bvmgr_tasks_transaction']) && preg_match('/^\s*(?:INSERT(?: IGNORE)? INTO|REPLACE INTO|UPDATE|DELETE FROM)\s+(`[^`]+`|[a-zA-Z0-9_]+)/i',$sql,$match) && !in_array(trim($match[1],'`'),array_map('bvmgr_tasks_table_name',array('task_instances','task_logs','task_templates','checklist_templates','checklist_items')),true)) throw new RuntimeException('task_write_outside_authority');
        return $sql;
    };
    try {
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,5)', $lock)) !== 1) throw new RuntimeException('tasks_busy');
        $locked = true;
        foreach (array('task_instances','task_logs','task_templates','checklist_templates','checklist_items') as $kind) {
            $engine = $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', bvmgr_tasks_table_name($kind)));
            if (strtolower((string) $engine) !== 'innodb') throw new RuntimeException('transactional_engine_required');
        }
        if ((int) $wpdb->get_var('SELECT @@autocommit') !== 1) throw new RuntimeException('external_transaction');
        $wpdb->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED'); $wpdb->query('START TRANSACTION'); $started = true;
        $GLOBALS['bvmgr_tasks_transaction'] = true; add_filter('query', $guard, PHP_INT_MAX);
        $result = $operation();
        if (is_wp_error($result)) throw new RuntimeException($result->get_error_code());
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT IS_USED_LOCK(%s)=CONNECTION_ID()', $lock)) !== 1) throw new RuntimeException('tasks_lock_lost');
        $committing = true; $GLOBALS['bvmgr_tasks_transaction_control'] = true; $wpdb->finish_transaction('COMMIT'); $started = $committing = false;
    } catch (Throwable $e) {
        if ($started) { try { $GLOBALS['bvmgr_tasks_transaction_control'] = true; $wpdb->finish_transaction('ROLLBACK'); } catch (Throwable $ignored) {} }
        $result = new WP_Error($committing ? 'commit_outcome_unknown' : $e->getMessage(), $committing ? 'Save outcome unknown. Retry the same request to check its recorded result.' : 'Task change was not saved: ' . $e->getMessage());
    } finally {
        remove_filter('query', $guard, PHP_INT_MAX); unset($GLOBALS['bvmgr_tasks_transaction'], $GLOBALS['bvmgr_tasks_transaction_control']);
        if ($locked) { try { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); } catch (Throwable $ignored) {} }
        $wpdb = $original;
    }
    if (!is_wp_error($result) && function_exists('bvmgr_tasks_delivery_tick')) bvmgr_tasks_delivery_tick();
    return $result;
}

/** Strict local clock: nonexistent and repeated DST clocks require another unambiguous time. */
function bvmgr_tasks_parse_clock(string $local, string $timezone): DateTimeImmutable
{
    $local = str_replace('T', ' ', trim($local)); if (strlen($local) === 16) $local .= ':00';
    $tz = new DateTimeZone($timezone); $utc = new DateTimeZone('UTC');
    $wall = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $local, $utc);
    if (!$wall || $wall->format('Y-m-d H:i:s') !== $local) throw new RuntimeException('invalid_local_time');
    $offsets = array(); $transitions = $tz->getTransitions($wall->getTimestamp()-172800, $wall->getTimestamp()+172800);
    foreach ($transitions ?: array(array('offset'=>$tz->getOffset($wall))) as $t) $offsets[(int) $t['offset']] = true;
    $matches = array(); foreach (array_keys($offsets) as $offset) { $dt = $wall->setTimestamp($wall->getTimestamp()-$offset)->setTimezone($tz); if ($dt->format('Y-m-d H:i:s') === $local) $matches[] = $dt; }
    if (count($matches) !== 1) throw new RuntimeException('ambiguous_or_nonexistent_local_time');
    return $matches[0];
}
function bvmgr_tasks_event_authority(int $plan): ?array
{
    if (get_post_type($plan) !== 'vms_event_plan' || in_array(get_post_status($plan), array('trash','auto-draft'), true)) return null;
    $occurrence = bvmgr_event_occurrence_for_plan($plan);
    if (empty($occurrence['valid'])) return null;
    try { $start = bvmgr_tasks_parse_clock($occurrence['start']->format('Y-m-d H:i:s'), wp_timezone_string()); }
    catch (Throwable $e) { return null; }
    $tec = (int) get_post_meta($plan, '_vms_tec_event_id', true);
    return array('event_id'=>$plan, 'event_title'=>get_the_title($plan), 'venue_id'=>(int)get_post_meta($plan,'_vms_venue_id',true),
        'event_type'=>sanitize_key((string)get_post_meta($plan,'_vms_event_type',true)), 'date_ymd'=>$start->format('Y-m-d'),
        'event_start_local'=>$start->format('Y-m-d H:i:s'), 'event_start_ts'=>$start->getTimestamp(), 'timezone'=>wp_timezone_string(),
        'status'=>bvmgr_event_plan_get_status($plan), 'calendar_warning'=>$tec <= 0 || get_post_meta($tec,'_EventStartDate',true) !== $start->format('Y-m-d H:i:s') ? 'calendar_occurrence_unavailable_or_stale' : '');
}
function bvmgr_tasks_timing(array $input, int $plan = 0): array
{
    $out = array('version'=>1,'timezone'=>(string)($input['timezone'] ?? wp_timezone_string()),'due_kind'=>(string)($input['due_kind'] ?? 'none'),
        'due_source'=>(string)($input['due_source'] ?? 'fixed'),'due_value'=>(string)($input['due_value'] ?? ''),'due_offset'=>(int)($input['due_offset'] ?? 0),
        'schedule_source'=>(string)($input['schedule_source'] ?? 'none'),'start'=>(string)($input['start'] ?? ''),'end'=>(string)($input['end'] ?? ''),
        'start_offset'=>(int)($input['start_offset'] ?? 0),'duration_minutes'=>(int)($input['duration_minutes'] ?? 0),
        'cancellation_policy'=>(string)($input['cancellation_policy'] ?? 'review'),'review'=>'');
    new DateTimeZone($out['timezone']);
    if (!in_array($out['due_kind'],array('none','date','datetime'),true) || !in_array($out['due_source'],array('fixed','event_offset','event_date'),true)
        || !in_array($out['schedule_source'],array('none','fixed','event_offset'),true) || !in_array($out['cancellation_policy'],array('review','retain','cancel'),true)) throw new RuntimeException('invalid_timing_policy');
    if (abs($out['due_offset']) > 525600 || abs($out['start_offset']) > 525600 || $out['duration_minutes'] < 0 || $out['duration_minutes'] > 10080) throw new RuntimeException('invalid_timing_range');
    if ($out['due_kind']==='none') { $out['due_source']='fixed'; $out['due_value']=''; $out['due_offset']=0; }
    if ($out['schedule_source']==='none') { $out['start']=$out['end']=''; $out['start_offset']=$out['duration_minutes']=0; }
    $relative = $out['due_source'] !== 'fixed' || $out['schedule_source'] === 'event_offset';
    $event = $plan > 0 ? bvmgr_tasks_event_authority($plan) : null;
    if ($relative && !$event) throw new RuntimeException('event_occurrence_unavailable');
    if ($relative) { $out['timezone'] = $event['timezone']; $out['occurrence_start_utc'] = gmdate('Y-m-d H:i:s', $event['event_start_ts']); }
    $tz = new DateTimeZone($out['timezone']); $due = null;
    if ($out['due_kind'] !== 'none') {
        if ($out['due_source'] === 'event_offset') {
            if ($out['due_kind'] !== 'datetime') throw new RuntimeException('relative_offset_requires_datetime');
            $due = (new DateTimeImmutable('@'.($event['event_start_ts']+$out['due_offset']*60)))->setTimezone($tz); $out['due_value']=$due->format('Y-m-d H:i:s');
        } else {
            if ($out['due_source'] === 'event_date') $out['due_value'] = $event['date_ymd'] . ($out['due_kind']==='datetime' ? ' '.(string)($input['due_clock'] ?? '10:00') : '');
            if ($out['due_source'] === 'event_date') $out['due_clock']=(string)($input['due_clock'] ?? '10:00');
            if ($out['due_kind'] === 'date') { if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$out['due_value'])) throw new RuntimeException('invalid_due_date'); $due=bvmgr_tasks_parse_clock($out['due_value'].' 23:59:59',$out['timezone']); }
            else { $due=bvmgr_tasks_parse_clock($out['due_value'],$out['timezone']); $out['due_value']=$due->format('Y-m-d H:i:s'); }
        }
    }
    $out['due_utc']=$due ? $due->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s') : null;
    $out['due_local']=$due ? $due->setTimezone(wp_timezone())->format('Y-m-d H:i:s') : null; // Existing list/digest compatibility; source timezone is explicit above.
    $out['start_utc']=$out['end_utc']=null;
    if ($out['schedule_source'] !== 'none') {
        $start=$out['schedule_source']==='event_offset' ? (new DateTimeImmutable('@'.($event['event_start_ts']+$out['start_offset']*60)))->setTimezone($tz) : bvmgr_tasks_parse_clock($out['start'],$out['timezone']);
        $end=$out['schedule_source']==='event_offset' ? ($out['duration_minutes'] ? $start->modify('+'.$out['duration_minutes'].' minutes') : null) : ($out['end']!=='' ? bvmgr_tasks_parse_clock($out['end'],$out['timezone']) : null);
        if ($end && $end <= $start) throw new RuntimeException('invalid_schedule_end');
        $out['start']=$start->format('Y-m-d H:i:s'); $out['end']=$end ? $end->format('Y-m-d H:i:s') : '';
        $out['start_utc']=$start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'); $out['end_utc']=$end ? $end->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s') : null;
    }
    if ($plan && !$event) $out['review']='event_occurrence_unavailable';
    elseif ($event && in_array($event['status'],array('cancelled','archived'),true) && $out['cancellation_policy']==='review') $out['review']='event_'.$event['status'];
    return $out;
}

/** Existing user identity stays canonical; staff links, where present, must be reciprocal and active. */
function bvmgr_tasks_person_valid(int $user): bool
{
    if (!$user) return true;
    $u=get_userdata($user); if (!$u || (int)$u->user_status !== 0) return false;
    $staff=(int)get_user_meta($user,'_vms_staff_id',true);
    if (!$staff) return true;
    if (get_post_type($staff)!=='vms_staff' || get_post_status($staff)!=='publish') return false;
    global $wpdb;
    $users=(array)$wpdb->get_col($wpdb->prepare('SELECT DISTINCT user_id FROM %i WHERE meta_key=%s AND meta_value=%s',$wpdb->usermeta,'_vms_staff_id',(string)$staff));
    $staff_links=(array)$wpdb->get_col($wpdb->prepare('SELECT DISTINCT post_id FROM %i WHERE meta_key=%s AND meta_value=%s',$wpdb->postmeta,'_vms_linked_user_id',(string)$user));
    if (count($users)!==1 || (int)$users[0]!==$user || count($staff_links)>1 || ($staff_links && (int)$staff_links[0]!==$staff)) return false;
    $identity=bvmgr_tech_doc_staff_identity($staff);
    return (int)$identity['user_id']===$user && !in_array($identity['error'],array('ambiguous_staff_user','invalid_staff','invalid_linked_user','ambiguous_user_link'),true);
}
function bvmgr_tasks_scheduled_person(int $plan,string $role): array
{
    global $wpdb;
    $term=get_term_by('slug',$role,'vms_staff_role'); $staff=array();
    if ($term instanceof WP_Term && !empty(bvmgr_staffing_role_meta_get((int)$term->term_id)['is_active'])) $staff=(array)$wpdb->get_col($wpdb->prepare("SELECT DISTINCT a.staff_id FROM %i a JOIN %i s ON s.slot_id=a.slot_id WHERE s.event_plan_id=%d AND s.role_id=%d AND s.status='active' AND a.status='confirmed'",bvmgr_staffing_table_name('assignments'),bvmgr_staffing_table_name('event_slots'),$plan,$term->term_id));
    $out=array('status'=>count($staff)>1?'multiple':'none','assignee_user_id'=>0,'staff_ids'=>array_map('intval',$staff));
    if (count($staff)===1) { $identity=bvmgr_tech_doc_staff_identity((int)$staff[0]); $user=(int)$identity['user_id']; if ($user && bvmgr_tasks_person_valid($user) && !empty(bvmgr_staffing_staff_candidate_status_for_role((int)$staff[0],(int)$term->term_id)['eligible'])) $out=array_merge($out,array('status'=>'single','assignee_user_id'=>$user)); }
    return $out;
}
function bvmgr_tasks_audit(array $before,array $after,string $action,string $operation='',string $fingerprint=''): void
{
    global $wpdb;
    if (empty($GLOBALS['bvmgr_tasks_transaction'])) throw new RuntimeException('task_audit_requires_transaction');
    $wpdb->insert(bvmgr_tasks_table_name('task_logs'),array('task_instance_id'=>$after['id'],'action'=>$action,'actor_user_id'=>get_current_user_id()?:null,
        'details'=>wp_json_encode(array('before'=>$before,'after'=>$after,'fingerprint'=>$fingerprint)), 'operation_key'=>$operation?:null,'created_at'=>bvmgr_tasks_now_utc_mysql()));
}
function bvmgr_tasks_apply_row(array $before,array $changes,string $action,string $operation='',string $fingerprint=''): array
{
    global $wpdb; $different=false;
    foreach ($changes as $k=>$v) if ((string)($before[$k]??'') !== (string)($v??'')) { $different=true; break; }
    if (!$different) return $before;
    $changes['revision']=(int)$before['revision']+1; $changes['updated_at']=bvmgr_tasks_now_utc_mysql();
    if ($wpdb->update(bvmgr_tasks_table_name('task_instances'),$changes,array('id'=>$before['id'],'revision'=>$before['revision']))!==1) throw new RuntimeException('stale_task');
    $after=bvmgr_tasks_get_instance((int)$before['id']); bvmgr_tasks_audit($before,$after,$action,$operation,$fingerprint); return $after;
}
/** One command authority. Internal generation/reconciliation runs inside this same boundary. */
function bvmgr_tasks_command(string $action,int $id,array $input=array(),?int $revision=null,string $operation='')
{
    if (!bvmgr_tasks_current_user_can_manage_all() && $action!=='transition') return new WP_Error('forbidden','Task manager permission required.');
    if ($operation!=='' && !preg_match('/^[a-zA-Z0-9_-]{8,64}$/D',$operation)) return new WP_Error('invalid_operation','Invalid request identity.');
    $fingerprint=hash('sha256',wp_json_encode(array($action,$id,$input,$revision,get_current_user_id())));
    return bvmgr_tasks_atomic(static function()use($action,$id,$input,$revision,$operation,$fingerprint){
        global $wpdb;
        $before=$id ? bvmgr_tasks_get_instance($id) : array();
        if ($id && !$before) throw new RuntimeException('task_missing');
        $manager=bvmgr_tasks_current_user_can_manage_all();
        if (!$manager && (!bvmgr_tasks_current_user_can_complete_self() || (int)$before['assignee_user_id']!==get_current_user_id() || !in_array($input['status']??'',array('done','skipped'),true))) throw new RuntimeException('forbidden');
        if ($operation!=='') {
            $log=$wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE operation_key=%s',bvmgr_tasks_table_name('task_logs'),$operation),ARRAY_A);
            if ($log) { $d=json_decode($log['details'],true); if (($d['fingerprint']??'')!==$fingerprint) throw new RuntimeException('operation_mismatch'); return (array)$d['after']; }
        }
        if ($id && $revision!==null && (int)$before['revision']!==$revision) throw new RuntimeException('stale_task');
        if ($action==='create') {
            if (array_key_exists('repeatable_checklist_id',$input)) {
                $template=array_merge($input,array('scope'=>empty($input['event_id'])?'general':'event','is_active'=>1,'required_default'=>!empty($input['is_required']),'due_mode'=>empty($input['due_at_local'])?'none':'fixed_datetime','due_time_local'=>empty($input['due_at_local'])?'':substr($input['due_at_local'],11,5),'due_offset_minutes'=>''));
                $template_id=bvmgr_tasks_upsert_task_template($template); if (is_wp_error($template_id)) throw new RuntimeException($template_id->get_error_code());
                $input['task_template_id']=(int)$template_id; $input['origin_checklist_id']=(int)$input['repeatable_checklist_id'];
                $checklist=(int)$input['repeatable_checklist_id'];
                if ($checklist) { if (!bvmgr_tasks_get_checklist_template($checklist)) throw new RuntimeException('checklist_missing'); $items=bvmgr_tasks_get_checklist_items($checklist); $items[]=array('task_template_id'=>$template_id,'sort_order'=>count($items)+1); $replaced=bvmgr_tasks_replace_checklist_items($checklist,$items); if (is_wp_error($replaced)) throw new RuntimeException($replaced->get_error_code()); }
            }
            return bvmgr_tasks_create_record($input,$operation,$fingerprint);
        }
        if ($before['status']==='superseded') throw new RuntimeException('superseded_read_only');
        if ($action==='transition') {
            $target=(string)($input['status']??''); $current=$before['status']; $reason=sanitize_text_field((string)($input['reason']??''));
            if ($target===$current) return $before;
            if (!(($current==='open' && in_array($target,array('done','skipped','canceled'),true)) || (in_array($current,array('done','skipped','canceled'),true) && $target==='open' && $manager))) throw new RuntimeException('invalid_transition');
            if (in_array($target,array('skipped','canceled'),true) && $reason==='') throw new RuntimeException('reason_required');
            $change=array('status'=>$target,'skip_reason'=>$target==='skipped'?$reason:null,'cancel_reason'=>$target==='canceled'?$reason:null,
                'completed_by_user_id'=>$target==='done'?get_current_user_id():null,'completed_at_local'=>$target==='done'?bvmgr_tasks_now_local_mysql():null);
            $after=bvmgr_tasks_apply_row($before,$change,array('done'=>'marked_done','skipped'=>'marked_skipped','canceled'=>'marked_canceled','open'=>'reopened')[$target],$operation,$fingerprint);
            if (in_array($target,array('done','skipped'),true)) bvmgr_tasks_recurrence_successor($after);
            return $after;
        }
        if ($before['status']!=='open') throw new RuntimeException('reopen_before_editing');
        if ($action==='assignment') {
            $mode=(string)($input['assignment_mode']??$before['assignment_mode']); $role=sanitize_key((string)($input['role_key']??$before['role_key']));
            if (!in_array($mode,array('person','role','scheduled_role'),true) || ($mode!=='person' && !get_term_by('slug',$role,'vms_staff_role')) || ($mode==='scheduled_role' && empty($before['event_id']))) throw new RuntimeException('invalid_assignment_rule');
            $user=(int)($input['assignee_user_id']??0); $locked=!empty($input['assignment_locked']);
            if ($mode==='scheduled_role' && !$locked) $user=(int)bvmgr_tasks_scheduled_person((int)$before['event_id'],$role)['assignee_user_id'];
            if ($mode==='role') $user=0;
            if (!bvmgr_tasks_person_valid($user)) throw new RuntimeException('assignee_unavailable_or_ambiguous');
            return bvmgr_tasks_apply_row($before,array('assignment_mode'=>$mode,'role_key'=>$mode==='person'?'':$role,'assignee_user_id'=>$user?:null,'assignment_locked'=>$locked?1:0),'assigned',$operation,$fingerprint);
        }
        if ($action==='timing') { $timing=bvmgr_tasks_timing($input,(int)$before['event_id']); return bvmgr_tasks_apply_row($before,array('timing_json'=>wp_json_encode($timing),'due_at_local'=>$timing['due_local']),'timing_changed',$operation,$fingerprint); }
        throw new RuntimeException('unknown_task_command');
    });
}
function bvmgr_tasks_create_record(array $input,string $operation='',string $fingerprint=''): array
{
    global $wpdb;
    if (empty($GLOBALS['bvmgr_tasks_transaction'])) throw new RuntimeException('task_create_requires_transaction');
    $key=(string)($input['generation_key']??'');
    if ($key!=='') { $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE generation_key=%s',bvmgr_tasks_table_name('task_instances'),$key),ARRAY_A); if ($row) return $row; }
    $plan=(int)($input['event_id']??0); if ($plan && get_post_type($plan)!=='vms_event_plan') throw new RuntimeException('event_missing');
    $user=(int)($input['assignee_user_id']??0); $mode=(string)($input['assignment_mode']??'person');
    if (!in_array($mode,array('person','role','scheduled_role'),true) || ($mode!=='person' && !get_term_by('slug',(string)($input['role_key']??''),'vms_staff_role')) || ($mode==='scheduled_role' && !$plan)) throw new RuntimeException('invalid_assignment_rule');
    $input['assignment_mode']=$mode; if ($mode==='person') $input['role_key']='';
    foreach (array('completed_by_user_id','completed_at_local','skip_reason','cancel_reason','superseded_by_instance_id') as $field) $input[$field]=null;
    if ($mode==='role') $input['assignee_user_id']=$user=0;
    if ($mode==='scheduled_role' && empty($input['assignment_locked'])) $input['assignee_user_id']=$user=(int)bvmgr_tasks_scheduled_person($plan,(string)($input['role_key']??''))['assignee_user_id'];
    if (!bvmgr_tasks_person_valid($user)) throw new RuntimeException('assignee_unavailable_or_ambiguous');
    $timing=bvmgr_tasks_timing((array)($input['timing']??array('due_kind'=>empty($input['due_at_local'])?'none':'datetime','due_value'=>$input['due_at_local']??'')),$plan);
    $input['due_at_local']=$timing['due_local']; $input['status']='open';
    $id=bvmgr_tasks_insert_instance_row($input); if (is_wp_error($id)) throw new RuntimeException($id->get_error_code());
    $wpdb->update(bvmgr_tasks_table_name('task_instances'),array('timing_json'=>wp_json_encode($timing),'generation_key'=>$key?:($operation!==''?'manual:'.$operation:null),'revision'=>1),array('id'=>$id));
    $after=bvmgr_tasks_get_instance($id); bvmgr_tasks_audit(array(),$after,!empty($input['recurrence_root_instance_id'])?'created_from_recurrence':(!empty($input['task_template_id'])?'created_from_template':'created_ad_hoc'),$operation,$fingerprint); return $after;
}
function bvmgr_tasks_recurrence_successor(array $row): int
{

    global $wpdb;
    if (!empty($row['event_id']) || ($row['recurrence_pattern']??'none')==='none') return 0;
    $key='recurrence:'.$row['id']; $found=(int)$wpdb->get_var($wpdb->prepare('SELECT id FROM %i WHERE generation_key=%s',bvmgr_tasks_table_name('task_instances'),$key)); if ($found) return $found;
    $timing=json_decode($row['timing_json']??'',true); if (!$timing || empty($timing['due_utc'])) throw new RuntimeException('review_recurrence_timing');
    $root=bvmgr_tasks_get_instance((int)($row['recurrence_root_instance_id']?:$row['id']));
    $root_timing=json_decode($root['timing_json']??'',true); if (!$root_timing || empty($root_timing['due_utc'])) throw new RuntimeException('review_recurrence_root');
    $tz=new DateTimeZone($timing['timezone']);
    $base=(new DateTimeImmutable($timing['due_utc'],new DateTimeZone('UTC')))->setTimezone($tz);
    $anchor=(new DateTimeImmutable($root_timing['due_utc'],new DateTimeZone('UTC')))->setTimezone($tz);
    $months=array('monthly'=>1,'quarterly'=>3,'semi_annual'=>6,'annual'=>12); $pattern=$row['recurrence_pattern'];
    if (isset($months[$pattern])) { $first=$base->modify('first day of this month')->modify('+'.$months[$pattern].' months'); $next=$first->setDate((int)$first->format('Y'),(int)$first->format('m'),min((int)$anchor->format('d'),(int)$first->format('t'))); }
    else { $days=$pattern==='weekly'?7:($pattern==='daily'?1:(int)bvmgr_tasks_normalize_recurrence_every_n_days($pattern,$row['recurrence_every_n_days'])); if ($days<1) throw new RuntimeException('invalid_recurrence'); $next=$base->modify('+'.$days.' days'); }
    $next=bvmgr_tasks_parse_clock($next->format('Y-m-d').' '.$base->format('H:i:s'),$timing['timezone']);
    $timing['due_value']=$next->format($timing['due_kind']==='date'?'Y-m-d':'Y-m-d H:i:s');
    // Advance each scheduled wall clock by the same calendar-day displacement, preserving local hours across DST.
    $days=(int)(new DateTimeImmutable($base->format('Y-m-d'),new DateTimeZone('UTC')))->diff(new DateTimeImmutable($next->format('Y-m-d'),new DateTimeZone('UTC')))->format('%r%a');
    foreach (array('start','end') as $field) if (!empty($timing[$field.'_utc'])) {
        $clock=(new DateTimeImmutable($timing[$field.'_utc'],new DateTimeZone('UTC')))->setTimezone($tz);
        $timing[$field]=bvmgr_tasks_parse_clock($clock->modify('+'.$days.' days')->format('Y-m-d').' '.$clock->format('H:i:s'),$timing['timezone'])->format('Y-m-d H:i:s');
    }
    $input=$row; unset($input['id']); $input['timing']=$timing; $input['generation_key']=$key; $input['recurrence_root_instance_id']=(int)$root['id'];
    $after=bvmgr_tasks_create_record($input); return (int)$after['id'];

	}

/** Stable read-only adapter contract; no Google IDs, repair writes or external calls. */
function bvmgr_tasks_sync_record(int $id): ?array
{
    $row=bvmgr_tasks_get_instance($id); if (!$row) return null;
    $timing=json_decode((string)($row['timing_json']??''),true); $event=(int)$row['event_id']>0?bvmgr_tasks_event_authority((int)$row['event_id']):null;
    $review=$timing['review']??'legacy_timing_requires_review';
    if (empty($row['generation_key']) && !empty($row['task_template_id'])) { global $wpdb; $duplicates=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE event_id=%d AND task_template_id=%d',bvmgr_tasks_table_name('task_instances'),$row['event_id'],$row['task_template_id'])); if ($duplicates>1) $review='legacy_generation_identity_requires_review'; }
    if ((int)$row['assignee_user_id']>0 && !bvmgr_tasks_person_valid((int)$row['assignee_user_id'])) $review='assignee_unavailable_or_ambiguous';
    if ($row['status']==='open' && $event && !empty($timing['occurrence_start_utc']) && $timing['occurrence_start_utc']!==gmdate('Y-m-d H:i:s',$event['event_start_ts'])) $review='event_timing_reconciliation_pending';
    if ($row['status']==='open' && $row['assignment_mode']==='scheduled_role' && empty($row['assignment_locked']) && (int)bvmgr_tasks_scheduled_person((int)$row['event_id'],(string)$row['role_key'])['assignee_user_id']!==(int)$row['assignee_user_id']) $review='staffing_reconciliation_pending';
    if ((int)$row['event_id']>0 && !$event) $review='event_occurrence_unavailable';
    if ($event && in_array($event['status'],array('cancelled','archived'),true) && ($timing['cancellation_policy']??'review')==='review') $review='event_'.$event['status'];
    return array('contract_version'=>1,'task_id'=>(int)$row['id'],'revision'=>(int)($row['revision']??0),'status'=>$row['status'],
        'assignee_user_id'=>(int)$row['assignee_user_id'],'title'=>$row['title'],'instructions'=>$row['instructions'],'priority'=>$row['priority'],
        'timing'=>$timing,'legacy_due_at_local'=>$timing?null:$row['due_at_local'],'event_plan_id'=>(int)$row['event_id'],'event'=>$event,
        'template_id'=>(int)$row['task_template_id'],'checklist_id'=>(int)$row['origin_checklist_id'],'generation_key'=>$row['generation_key']??null,
        'recurrence_root_id'=>(int)($row['recurrence_root_instance_id']?:$row['id']),'modified_utc'=>$row['updated_at'],'review'=>$review,
        'overdue'=>$row['status']==='open' && !empty($timing['due_utc']) && strtotime($timing['due_utc'].' UTC')<time(),
        'scheduled'=>$row['status']==='open' && !empty($timing['start_utc']));
}

function bvmgr_tasks_request_revision(): ?int { return $GLOBALS['bvmgr_tasks_request']['revision']??null; }
function bvmgr_tasks_request_operation(): string { return $GLOBALS['bvmgr_tasks_request']['operation']??''; }
function bvmgr_tasks_insert_instance(array $payload)
{
    if (!empty($GLOBALS['bvmgr_tasks_request']) && !empty($_POST['make_repeatable_now'])) $payload['repeatable_checklist_id']=(int)($_POST['repeatable_checklist_id']??0);
    $r=bvmgr_tasks_command('create',0,$payload,null,bvmgr_tasks_request_operation()); return is_wp_error($r)?$r:(int)$r['id'];
}
function bvmgr_tasks_upsert_task_template(array $payload,int $template_id=0) { return bvmgr_tasks_save_definition('task_templates',$payload,$template_id); }
function bvmgr_tasks_upsert_checklist_template(array $payload,int $checklist_id=0) { return bvmgr_tasks_save_definition('checklist_templates',$payload,$checklist_id); }

/** Explicit admin/controlled CLI setup. Never invoked from ordinary bootstrap or a read. */
function bvmgr_tasks_install_authority(): void
{
    global $wpdb;
    if (!current_user_can('manage_options') || bvmgr_staffing_transaction_active() || !empty($GLOBALS['bvmgr_tasks_transaction'])) throw new RuntimeException('forbidden_schema_update');
    $lock='bvm_tasks_'.substr(hash('sha256',$wpdb->dbname.':'.$wpdb->prefix),0,48);
    if ((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,5)',$lock))!==1) throw new RuntimeException('tasks_busy');
    try {
    bvmgr_tasks_maybe_upgrade_schema();
    if (!bvmgr_tasks_authority_ready()) throw new RuntimeException('task_schema_verification_failed');
    bvmgr_tasks_ensure_capability_mapping();
    if (get_option('bvmgr_tasks_delivery_after_log',null)===null) {
        $floor=(int)$wpdb->get_var($wpdb->prepare('SELECT MAX(id) FROM %i',bvmgr_tasks_table_name('task_logs')));
        add_option('bvmgr_tasks_delivery_after_log',$floor,'',false);
    }
    bvmgr_tasks_schedule_nightly_generator(); bvmgr_tasks_notifications_ensure_cron();
    } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock)); }
}
