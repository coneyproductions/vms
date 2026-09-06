<?php
require __DIR__.'/runtime.php';
function race(array $jobs): array {
    global $wpdb;
    $dir=sys_get_temp_dir().'/bvm-staffing-race-'.bin2hex(random_bytes(6));mkdir($dir,0700);
    $lock=bvmgr_staffing_lock_name();check((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,5)',$lock))===1,'parent barrier lock');
    $processes=array();$pipes=array();$results=array();
    try {
        foreach($jobs as $i=>$job){file_put_contents("$dir/job$i",json_encode($job));$processes[$i]=proc_open(array(PHP_BINARY,__DIR__.'/worker.php',"$dir/job$i","$dir/ready$i"),array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')),$pipes[$i]);fclose($pipes[$i][0]);}
        $deadline=microtime(true)+10;
        while((!is_file("$dir/ready0")||!is_file("$dir/ready1"))&&microtime(true)<$deadline)usleep(10000);
        check(is_file("$dir/ready0")&&is_file("$dir/ready1"),'both independent workers ready');
        check(file_get_contents("$dir/ready0")!==file_get_contents("$dir/ready1"),'distinct DB connection IDs');
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));
        foreach($processes as $i=>$process){$out=stream_get_contents($pipes[$i][1]);$err=stream_get_contents($pipes[$i][2]);fclose($pipes[$i][1]);fclose($pipes[$i][2]);check(proc_close($process)===0,'worker exit '.$err);$results[]=json_decode(trim($out),true,512,JSON_THROW_ON_ERROR);}
    } finally {$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));foreach(glob($dir.'/*') as $file)unlink($file);rmdir($dir);}
    return $results;
}
$f=fixture();$now=bvmgr_staffing_now_mysql_utc();$wpdb->insert(bvmgr_staffing_table_name('event_slots'),array('event_plan_id'=>$f['plan'],'role_id'=>$f['role'],'headcount_needed'=>1,'shift_start_local'=>'13:00','shift_end_local'=>'19:00','created_at'=>$now,'updated_at'=>$now));$slot2=(int)$wpdb->insert_id;
check(bvmgr_staffing_atomic(static function()use($slot2,$f){bvmgr_staffing_matrix_proposals($slot2,array($f['staff']));return array('ok'=>true);})['ok'],'second proposed slot');
$id2=(int)$wpdb->get_var($wpdb->prepare('SELECT assignment_id FROM %i WHERE slot_id=%d',bvmgr_staffing_table_name('assignments'),$slot2));$f2=array_merge($f,array('id'=>$id2,'slot'=>$slot2));
$before=audits($f['id'])+audits($id2);
$results=race(array(array('kind'=>'confirm','id'=>$f['id'],'options'=>options($f)),array('kind'=>'confirm','id'=>$id2,'options'=>options($f2))));
check(count(array_filter($results,static fn($r)=>$r['result']['ok']))===1,'one concurrent confirmation succeeds');
check(count(array_filter($results,static fn($r)=>($r['result']['error']??'')==='confirmed_overlap'))===1,'other concurrent confirmation rejected');
$concurrent_confirmation_safe = count(array_filter($results,static fn($r)=>$r['result']['ok']))===1 && count(array_filter($results,static fn($r)=>($r['result']['error']??'')==='confirmed_overlap'))===1;
check(audits($f['id'])+audits($id2)===$before+1,'exactly one committed transition audit');
check(max(array_column($results,'elapsed'))>=0.55,'server lock serialized overlapping processes');
$wpdb->insert(bvmgr_staffing_table_name('event_slots'),array('event_plan_id'=>$f['plan'],'role_id'=>$f['role'],'headcount_needed'=>1,'shift_start_local'=>'20:00','shift_end_local'=>'21:00','created_at'=>$now,'updated_at'=>$now));$slot3=(int)$wpdb->insert_id;
$job=array('kind'=>'propose','slot'=>$slot3,'staff'=>$f['staff']);$results=race(array($job,$job));check($results[0]['result']['ok']&&$results[1]['result']['ok'],'concurrent proposal requests succeed safely');
check((int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE slot_id=%d',bvmgr_staffing_table_name('assignments'),$slot3))===1,'one pair after concurrent creation');
$concurrent_duplicate_safe = (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE slot_id=%d',bvmgr_staffing_table_name('assignments'),$slot3))===1;
echo 'PASS concurrency; '.$checks." total database assertions\n";
// Whole matrix saves compete with staff/operator decisions using separate processes.
foreach(array('confirmed','declined','canceled') as $target){
    $f=fixture();$user=wp_insert_user(array('user_login'=>'race-'.wp_generate_uuid4(),'user_pass'=>'disposable','role'=>'subscriber'));update_user_meta($user,'_vms_staff_id',$f['staff']);
    $context=$target==='canceled'?'operator':'staff';$actor=$context==='operator'?1:$user;
    $decision=array('kind'=>'transition','id'=>$f['id'],'target'=>$target,'user'=>$actor,'options'=>options($f,array('context'=>$context)));
    $matrix=array('kind'=>'matrix','plan'=>$f['plan'],'role'=>$f['role'],'staff'=>$f['staff']);
    $results=race(array($decision,$matrix));
    check($results[0]['result']['ok']&&$results[1]['result']['ok'],'whole matrix/decision both complete '.$target.' '.json_encode($results));
    check(bvmgr_staffing_lifecycle_row($f['id'])['status']===$target,'matrix race preserves '.$target);
    check(audits($f['id'])===2,'matrix race creates exactly one decision audit '.$target);
}
// Competing Accept/Decline on one revision commits exactly one outcome.
$f=fixture();$user=wp_insert_user(array('user_login'=>'respond-'.wp_generate_uuid4(),'user_pass'=>'disposable','role'=>'subscriber'));update_user_meta($user,'_vms_staff_id',$f['staff']);
$jobs=array();foreach(array('confirmed','declined') as $target)$jobs[]=array('kind'=>'transition','id'=>$f['id'],'target'=>$target,'user'=>$user,'options'=>options($f,array('context'=>'staff')));
$results=race($jobs);check(count(array_filter($results,static fn($r)=>$r['result']['ok']))===1,'competing responses commit once');check(count(array_filter($results,static fn($r)=>($r['result']['error']??'')==='stale_assignment'))===1,'losing response gets stale revision');check(audits($f['id'])===2,'competing responses retain one decision audit');
echo 'PASS extended concurrency; '.$checks." total database assertions\n";
