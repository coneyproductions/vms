<?php
/** Regression for actual Google event response normalization. No transport or WordPress state. */
define('ABSPATH', __DIR__);
function add_action(...$args): void {}
require dirname(__DIR__,2).'/includes/modules/staff-tasks/google/sync.php';
$checks=0;
function representation_check(bool $ok,string $label):void {global $checks;if(!$ok)throw new RuntimeException($label);$checks++;}
$owned=['start'=>['dateTime'=>'2030-10-20T10:00:00-05:00','timeZone'=>'America/Chicago'],'end'=>['dateTime'=>'2030-10-20T11:00:00-05:00','timeZone'=>'America/Chicago'],'transparency'=>'opaque','extendedProperties'=>['private'=>['bvm_owner'=>'owner','bvm_task'=>'1']]];
$remote=$owned;$remote['start']['dateTime']='2030-10-20T15:00:00Z';$remote['end']['dateTime']='2030-10-20T16:00:00.000Z';unset($remote['transparency']);
representation_check(bvmgr_google_fields_match($owned,$remote),'UTC and omitted opaque are equivalent');
$remote['colorId']='7';$remote['extendedProperties']['private']['user_extra']='keep';representation_check(bvmgr_google_fields_match($owned,$remote),'unowned properties preserved');
foreach(['time'=>'2030-10-20T15:01:00Z','fraction'=>'2030-10-20T15:00:00.000001Z','no-offset'=>'2030-10-20T15:00:00','relative'=>'now','invalid'=>'2030-02-30T15:00:00Z'] as $label=>$value){$changed=$remote;$changed['start']['dateTime']=$value;representation_check(!bvmgr_google_fields_match($owned,$changed),'different/invalid instant '.$label);}
$changed=$remote;$changed['start']['timeZone']='UTC';representation_check(!bvmgr_google_fields_match($owned,$changed),'IANA zone change remains owned');
$changed=$remote;$changed['extendedProperties']['private']['bvm_owner']='other';representation_check(!bvmgr_google_fields_match($owned,$changed),'ownership remains strict');
$changed=$remote;$changed['transparency']='transparent';representation_check(!bvmgr_google_fields_match($owned,$changed),'busy/free change remains owned');
$changed=$owned;$changed['transparency']='transparent';representation_check(!bvmgr_google_fields_match($changed,$remote),'missing opaque cannot satisfy transparent');
$changed=$remote;$changed['transparency']=null;representation_check(!bvmgr_google_fields_match($owned,$changed),'explicit null is not missing default');
representation_check(!bvmgr_google_fields_match(['start'=>['date'=>'2030-10-20']],['start'=>['dateTime'=>'2030-10-20T00:00:00Z']]),'all-day and timed shapes differ');
representation_check(!bvmgr_google_same_instant('2030-11-03T01:30:00-05:00','2030-11-03T01:30:00-06:00'),'DST fold instants remain distinct');
representation_check(bvmgr_google_same_instant('2030-01-01T05:45:00+05:45','2030-01-01T00:00:00Z'),'fractional-hour offset equivalence');
representation_check(!bvmgr_google_same_instant(null,'2030-01-01T00:00:00Z'),'non-string rejected');
echo "PASS $checks Google remote representation assertions\n";
