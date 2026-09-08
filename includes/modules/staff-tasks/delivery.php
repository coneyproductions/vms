<?php
/** Existing task templates/provider/notification ledger; never staffing-assignment mail. */
defined('ABSPATH') || exit;
function bvmgr_tasks_delivery_record(array $work,string $status,string $phase,string $error=''): bool
{
    $work['phase']=$phase;
    return bvmgr_notify_insert_log(array('source'=>'vms_staff_tasks','event_key'=>$work['key'],'recipient_user_id'=>$work['user'],
        'recipient_address'=>$work['email']??'','channel'=>'email','template_key'=>$work['template'],'provider'=>'core_email','status'=>$status,'error_message'=>$error,'payload'=>$work));
}
function bvmgr_tasks_delivery_latest(string $key): ?array
{
    global $wpdb; return $wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE source='vms_staff_tasks' AND event_key=%s ORDER BY id DESC LIMIT 1",bvmgr_notify_log_table_name(),$key),ARRAY_A)?:null;
}
function bvmgr_tasks_delivery_work(array $row,string $kind,string $key): array
{
    $templates=array('assigned'=>'staff_tasks.task_assigned','due_soon'=>'staff_tasks.task_due_soon','overdue'=>'staff_tasks.task_overdue','digest'=>'staff_tasks.task_digest_daily');
    $u=get_userdata((int)$row['assignee_user_id']);
    return array('key'=>$key,'kind'=>$kind,'template'=>$templates[$kind],'user'=>(int)$row['assignee_user_id'],'email'=>$u?strtolower(trim($u->user_email)):'',
        'tasks'=>array(array('id'=>(int)$row['id'],'revision'=>(int)$row['revision'])),'vars'=>bvmgr_tasks_notification_context($row),'attempts'=>0);
}
function bvmgr_tasks_delivery_send(array $latest): bool
{
    $work=json_decode($latest['payload_json'],true);
    if (!$work || in_array($latest['status'],array('sent','skipped'),true) || ($work['phase']??'')==='attempt' || ($latest['status']==='failed' && ((int)$work['attempts']>=3 || time()-strtotime($latest['created_at'].' UTC')<60))) return false;
    foreach ($work['tasks'] as $task) {
        $row=bvmgr_tasks_get_instance((int)$task['id']);
        if (!$row || $row['status']!=='open' || (int)$row['revision']!==(int)$task['revision'] || (int)$row['assignee_user_id']!==(int)$work['user']) { bvmgr_tasks_delivery_record($work,'skipped','terminal','superseded_task'); return false; }
        $projection=bvmgr_tasks_sync_record((int)$row['id']);
        if ($projection['review']!=='') { bvmgr_tasks_delivery_record($work,'skipped','terminal','task_requires_review'); return false; }
    }
    $work['attempts']++; $user=get_userdata((int)$work['user']); $email=$user?strtolower(trim($user->user_email)):'';
    if (!bvmgr_tasks_person_valid((int)$work['user']) || !$user || !is_email($email) || sanitize_email($email)!==$email) { bvmgr_tasks_delivery_record($work,'failed','terminal','recipient_unavailable'); return false; }
    if (!empty($work['email']) && $work['email']!==$email) { bvmgr_tasks_delivery_record($work,'skipped','terminal','recipient_changed'); return false; }
    $work['email']=$email;
    if (!bvmgr_notify_user_channel_enabled((int)$work['user'],'email')) { bvmgr_tasks_delivery_record($work,'skipped','terminal','email_disabled'); return false; }
    try { $message=bvmgr_notify_resolve_template_payload('vms_task_'.$work['kind'],$work['template'],bvmgr_notify_user_locale((int)$work['user']),$work['vars'],(int)$work['user']); if (is_wp_error($message)) throw new RuntimeException('template_failed'); }
    catch (Throwable $e) { bvmgr_tasks_delivery_record($work,'failed','terminal','template_failed'); return false; }
    if (!bvmgr_tasks_delivery_record($work,'queued','attempt','delivery_outcome_unknown')) return false;
    try { $sent=bvmgr_notify_provider_core_email_send(array_merge($message,array('to'=>$email))); }
    catch (Throwable $e) { return true; }
    bvmgr_tasks_delivery_record($work,!empty($sent['success'])?'sent':'failed','terminal',(string)($sent['error_message']??'')); return true;
}
function bvmgr_tasks_delivery_tick(string $scan=''): void
{
    global $wpdb;
    if (!bvmgr_tasks_authority_ready() || !empty($GLOBALS['bvmgr_tasks_transaction']) || bvmgr_staffing_transaction_active() || get_class($wpdb)!=='wpdb') return;
    $floor=get_option('bvmgr_tasks_delivery_after_log',null); if (!is_numeric($floor)) return;
    $original=$wpdb; $original->flush(); $wpdb=new BVMGR_Staffing_Transaction_DB($original); $locked=false;
    $lock='bvm_task_delivery_'.substr(hash('sha256',$wpdb->dbname.':'.$wpdb->prefix),0,40);
    try {
        if ((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)',$lock))!==1) return; $locked=true; $settings=bvmgr_tasks_get_settings();
        if (!empty($settings['notify_assignment_alerts'])) {
            $logs=(array)$wpdb->get_results($wpdb->prepare("SELECT l.* FROM %i l WHERE l.id>%d AND l.task_instance_id>0 AND l.action IN ('created_ad_hoc','created_from_template','created_from_recurrence','assigned','event_reconciled') AND NOT EXISTS (SELECT 1 FROM %i n WHERE n.source='vms_staff_tasks' AND n.event_key=CONCAT('task_assignment_',l.id)) ORDER BY l.id LIMIT 100",bvmgr_tasks_table_name('task_logs'),(int)$floor,bvmgr_notify_log_table_name()),ARRAY_A);
            foreach ($logs as $log) {
                $detail=json_decode($log['details'],true); $after=$detail['after']??array(); if (empty($after['id'])) continue;
                $work=bvmgr_tasks_delivery_work($after,'assigned','task_assignment_'.$log['id']);
                if (!$work['user'] || (!empty($detail['before']) && (int)$detail['before']['assignee_user_id']===$work['user'])) bvmgr_tasks_delivery_record($work,'skipped','terminal','no_assignment_notification_required');
                else bvmgr_tasks_delivery_record($work,'queued','work');
            }
        }
        if ($scan!=='') {
            $cursor=0; $queued=0; $grouped=array(); $now=time();
            do {
                $rows=(array)$wpdb->get_results($wpdb->prepare("SELECT * FROM %i WHERE id>%d AND status='open' AND revision>0 AND assignee_user_id>0 ORDER BY id LIMIT 200",bvmgr_tasks_table_name('task_instances'),$cursor),ARRAY_A);
                foreach ($rows as $row) {
                    $cursor=(int)$row['id']; $projection=bvmgr_tasks_sync_record($cursor); if ($projection['review']!=='' || empty($projection['timing']['due_utc'])) continue;
                    $due=strtotime($projection['timing']['due_utc'].' UTC'); $kind='';
                    if ($scan==='reminders' && !empty($settings['notify_due_soon_alerts']) && $due>=$now && $due<=$now+bvmgr_tasks_notifications_default_due_soon_window_minutes()*60) $kind='due_soon';
                    if ($scan==='reminders' && !empty($settings['notify_overdue_alerts']) && $due<$now) $kind='overdue';
                    if ($scan==='digest' && !empty($settings['notify_daily_digest']) && wp_date('H:i')>=($settings['notify_digest_time']??'08:00')) {
                        $days=array('today'=>1,'next3'=>3,'next7'=>7)[$settings['notify_digest_window']??'next3']??3;
                        if ($due>=$now && $due<=$now+$days*DAY_IN_SECONDS) $grouped[(int)$row['assignee_user_id']][]=$row;
                    }
                    if ($kind==='') continue;
                    $key='task_'.$kind.'_'.$cursor.'_r'.$row['revision']; if (bvmgr_tasks_delivery_latest($key)) continue;
                    bvmgr_tasks_delivery_record(bvmgr_tasks_delivery_work($row,$kind,$key),'queued','work'); if (++$queued>=100) break;
                }
            } while (count($rows)===200 && $queued<100);
            foreach ($grouped as $user=>$rows) {
                $key='task_digest_'.$user.'_'.wp_date('Ymd'); if (bvmgr_tasks_delivery_latest($key)) continue;
                $work=bvmgr_tasks_delivery_work($rows[0],'digest',$key); $work['tasks']=array(); $work['vars']=array('task_count'=>count($rows),'tasks'=>array(),'task_url'=>bvmgr_tasks_notification_task_url(0,$user));
                foreach ($rows as $row) { $work['tasks'][]=array('id'=>(int)$row['id'],'revision'=>(int)$row['revision']); $work['vars']['tasks'][]=bvmgr_tasks_notification_context($row); }
                bvmgr_tasks_delivery_record($work,'queued','work');
            }
        }
        $pending=(array)$wpdb->get_results($wpdb->prepare("SELECT n.* FROM %i n JOIN (SELECT event_key,MAX(id) id FROM %i WHERE source='vms_staff_tasks' GROUP BY event_key) latest ON latest.id=n.id WHERE n.status IN ('queued','failed') ORDER BY n.id",bvmgr_notify_log_table_name(),bvmgr_notify_log_table_name()),ARRAY_A);
        $sent=0; foreach ($pending as $latest) if (bvmgr_tasks_delivery_send($latest) && ++$sent>=100) break;
    } catch (Throwable $e) { error_log('[BVM Staff Tasks] Delivery processing deferred; inspect task notification history.'); }
    finally { if ($locked) { try { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock)); } catch (Throwable $ignored) {} } $wpdb=$original; }
}
