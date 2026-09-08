<?php
defined('ABSPATH') || exit;

/** A pure, allowlisted projection. Never send task instructions, reasons or private paths. */
function bvmgr_google_projection(?array $task, int $user, string $site, bool $existing = false): array
{
    if (!$task || (int)$task['assignee_user_id']!==$user) return array('state'=>'not_eligible','retire'=>true);
    if (($task['review'] ?? '')!=='' || !is_array($task['timing'])) {
        $terminal=array('done'=>'Completed','skipped'=>'Skipped','canceled'=>'Canceled','superseded'=>'Superseded');
        if ($existing && isset($terminal[$task['status']])) {
            // A review flag freezes timing, but cannot leave terminal work visually active.
            $body=array('summary'=>'['.$terminal[$task['status']].'] '.mb_substr(wp_strip_all_tags($task['title']),0,500),
                'description'=>'Managed by Backstage Venue Manager. Task #'.$task['task_id'].'. Change this task in BVM. Timing requires review; previous calendar timing is retained.',
                'transparency'=>'transparent','status'=>'confirmed',
                'extendedProperties'=>array('private'=>array('bvm_owner'=>$site,'bvm_task'=>(string)$task['task_id'],'bvm_user'=>(string)$user,'bvm_status'=>$task['status'],'bvm_revision'=>(string)$task['revision'])));
            return array('state'=>'pending','body'=>$body,'hash'=>hash('sha256',wp_json_encode($body)),'review_block'=>true);
        }
        return array('state'=>'blocked_timing_review');
    }
    $t=$task['timing']; $start=$end=null; $kind=''; $transparent=true;
    try {
        $zone = new DateTimeZone($t['timezone']);
        if (!empty($t['start_utc'])) {
            $start=new DateTimeImmutable($t['start_utc'],new DateTimeZone('UTC'));
            if (!empty($t['end_utc'])) { $end=new DateTimeImmutable($t['end_utc'],new DateTimeZone('UTC')); $kind='Scheduled'; $transparent=false; }
            else { $end=$start->modify('+1 minute'); $kind='Start marker (end unspecified)'; }
        } elseif (($t['due_kind'] ?? '')==='date') {
            $date=DateTimeImmutable::createFromFormat('!Y-m-d',$t['due_value'],$zone);
            if (!$date || $date->format('Y-m-d')!==$t['due_value']) throw new RuntimeException('invalid_date');
            $start=array('date'=>$date->format('Y-m-d')); $end=array('date'=>$date->modify('+1 day')->format('Y-m-d')); $kind='Due date';
        } elseif (!empty($t['due_utc']) && $t['due_kind']==='datetime') {
            $start=new DateTimeImmutable($t['due_utc'],new DateTimeZone('UTC')); $end=$start->modify('+1 minute'); $kind='Deadline';
        } else return array('state'=>'not_eligible','retire'=>$existing);
        if ($start instanceof DateTimeImmutable) {
            if ($end <= $start) throw new RuntimeException('invalid_end');
            $start=array('dateTime'=>$start->setTimezone($zone)->format(DateTimeInterface::RFC3339),'timeZone'=>$t['timezone']);
            $end=array('dateTime'=>$end->setTimezone($zone)->format(DateTimeInterface::RFC3339),'timeZone'=>$t['timezone']);
        }
    } catch (Throwable $e) { return array('state'=>'blocked_timing_review'); }
    $statuses=array('open'=>'','done'=>'Completed','skipped'=>'Skipped','canceled'=>'Canceled','superseded'=>'Superseded');
    if (!array_key_exists($task['status'],$statuses)) return array('state'=>'permanent_failure');
    if (!$existing && in_array($task['status'],array('skipped','canceled','superseded'),true)) return array('state'=>'not_eligible');
    // Initial connection: all open work, or completed work whose timing is within 30 days/future.
    $instant=$t['end_utc'] ?: ($t['start_utc'] ?: ($t['due_utc'] ?? ''));
    if (!$existing && $task['status']==='done' && (!$instant || strtotime($instant.' UTC')<time()-30*DAY_IN_SECONDS)) return array('state'=>'not_eligible');
    $label=$statuses[$task['status']];
    $description='Managed by Backstage Venue Manager. Change this task in BVM; Google edits do not update BVM.';
    $description.="\nOpen in BVM: ".add_query_arg(array('page'=>'vms-task-detail','task_id'=>$task['task_id']),admin_url('admin.php'));
    $description.="\nTask #".$task['task_id'].' · '.$kind.' · Status: '.$task['status'];
    if ($kind==='Deadline') $description.="\nOne-minute deadline marker beginning at the exact due instant; this is not a work-duration block.";
    if ($kind==='Due date') $description.="\nDate-only deadline, not reserved work time.";
    if ($kind==='Start marker (end unspecified)') $description.="\nOne-minute start marker; BVM has no scheduled end.";
    if (!empty($t['due_utc'])) $description.="\nDue: ".($t['due_kind']==='date'?$t['due_value']:$t['due_utc'].' UTC').' ('.$t['timezone'].')';
    if ($task['event_plan_id']) $description.="\nEvent Plan #".$task['event_plan_id'];
    $description.="\nTiming authority: ".$t['schedule_source'].' / '.$t['due_source'];
    $body=array('summary'=>($label!==''?'['.$label.'] ':'').'['.$kind.'] '.mb_substr(wp_strip_all_tags($task['title']),0,500),
        'description'=>$description,'start'=>$start,'end'=>$end,'status'=>'confirmed',
        'transparency'=>$transparent || $task['status']!=='open'?'transparent':'opaque',
        'extendedProperties'=>array('private'=>array('bvm_owner'=>$site,'bvm_task'=>(string)$task['task_id'],'bvm_user'=>(string)$user,
            'bvm_revision'=>(string)$task['revision'],'bvm_status'=>$task['status'],'bvm_template'=>(string)$task['template_id'],
            'bvm_root'=>(string)$task['recurrence_root_id'],'bvm_modified'=>(string)$task['modified_utc'])));
    return array('state'=>'pending','body'=>$body,'hash'=>hash('sha256',wp_json_encode($body)));
}
function bvmgr_google_event_id(string $site, int $task): string
{
    // Hex is a subset of Google's base32hex alphabet; revision and connection session never enter identity.
    return 'b'.hash('sha256',$site.':staff-task:'.$task);
}
