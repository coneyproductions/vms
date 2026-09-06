<?php
/**
 * The prior commit preserves the eleven original checkpoint characterizations.
 * Their replacements exercise real WordPress/SQL and genuinely concurrent workers.
 * Explicit disposable root is required by the shared bootstrap.
 */
require __DIR__ . '/staffing-lifecycle/concurrency.php';
$forensics = 0;
function forensic($condition, string $label): void {
    global $forensics;
    if (!$condition) throw new RuntimeException('Forensic regression: '.$label);
    $forensics++;
    echo 'PASS forensic '.$forensics.': '.$label."\n";
}
function matrix(array $f, array $staff): array {
    return bvmgr_staffing_save_event_roles_matrix($f['plan'], array($f['role']=>1), array($f['role']=>$staff), array($f['role']=>'absolute'), array($f['role']=>'12:00'), array($f['role']=>'18:00'));
}
$f=fixture();
forensic(bvmgr_staffing_lifecycle_row($f['id'])['status']==='proposed','proposal creation remains Proposed');
$r=matrix($f,array($f['staff']));
forensic($r['ok']&&audits($f['id'])===1,'proposal has exactly one lifecycle audit, matrix does not duplicate it');
check(bvmgr_staffing_transition_assignment($f['id'],'confirmed',options($f))['ok'],'forensic confirm setup');
$r=matrix($f,array($f['staff']));forensic($r['ok']&&bvmgr_staffing_lifecycle_row($f['id'])['status']==='confirmed','accepted P0 confirmation retention');
$writes=0;$counter=static function($sql)use(&$writes){if(preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE)\b/i',$sql))$writes++;return $sql;};
add_filter('query',$counter);$r=matrix($f,array($f['staff']));remove_filter('query',$counter);
forensic(!empty($r['noop'])&&$writes===0,'unchanged matrix performs no writes');
$stale=options($f);check(bvmgr_staffing_transition_assignment($f['id'],'canceled',options($f))['ok'],'forensic cancel setup');
$r=bvmgr_staffing_transition_assignment($f['id'],'proposed',$stale);forensic(!$r['ok']&&$r['error']==='stale_assignment','stale lifecycle revision cannot overwrite a newer commitment decision');
// The selected checkbox is identical regardless of the current lifecycle state.
$r=matrix($f,array($f['staff']));forensic($r['ok']&&bvmgr_staffing_lifecycle_row($f['id'])['status']==='canceled','stale selected checkbox preserves Canceled');
check(bvmgr_staffing_transition_assignment($f['id'],'proposed',options($f))['ok'],'forensic reproposal setup');
$user=wp_insert_user(array('user_login'=>'forensic-'.wp_generate_uuid4(),'user_pass'=>'disposable','role'=>'subscriber'));update_user_meta($user,'_vms_staff_id',$f['staff']);wp_set_current_user($user);
check(bvmgr_staffing_transition_assignment($f['id'],'declined',options($f,array('context'=>'staff')))['ok'],'forensic decline setup');wp_set_current_user(1);
$r=matrix($f,array($f['staff']));forensic($r['ok']&&bvmgr_staffing_lifecycle_row($f['id'])['status']==='declined','stale selected checkbox preserves Declined');
$r=matrix($f,array());forensic($r['ok']&&bvmgr_staffing_lifecycle_row($f['id'])['status']==='declined','unrelated omission preserves Declined history');
forensic($concurrent_duplicate_safe,'simultaneous creation keeps one association');
$before=bvmgr_staffing_lifecycle_row($f['id']);$n=audits($f['id']);$table=bvmgr_staffing_table_name('audit');
$fail=static function($sql)use($table){return strpos($sql,'INSERT INTO `'.$table.'`')===0?'INVALID SQL FORENSIC AUDIT FAILURE':$sql;};
add_filter('query',$fail);$r=bvmgr_staffing_transition_assignment($f['id'],'proposed',options($f));remove_filter('query',$fail);
forensic(!$r['ok']&&$before===bvmgr_staffing_lifecycle_row($f['id'])&&audits($f['id'])===$n,'audit failure rolls back status and audit together');
forensic($concurrent_confirmation_safe,'independent concurrent confirmations cannot both commit');
echo "PASS all {$forensics} updated forensic regressions\n";
