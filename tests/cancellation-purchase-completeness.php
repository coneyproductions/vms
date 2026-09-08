<?php
/** Deterministic in-memory integration: actual BVM discovery, runner, acceptance and report. */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/no-wordpress/');
define('MINUTE_IN_SECONDS', 60);
set_error_handler(static function($severity,$message,$file,$line){throw new ErrorException($message,0,$severity,$file,$line);});
$GLOBALS['hooks'] = array(); $GLOBALS['meta'] = array(); $GLOBALS['orders'] = array(); $GLOBALS['writes'] = 0; $GLOBALS['calls'] = array(); $GLOBALS['allow'] = true;
function add_filter($h, $f, $p=10, $n=1) { $GLOBALS['hooks'][$h][$p][] = array($f,$n); }
function add_action(...$a) { add_filter(...$a); }
function apply_filters($h,$v,...$a) { $hooks=$GLOBALS['hooks'][$h]??array(); ksort($hooks); foreach($hooks as $rows) foreach($rows as [$f,$n]) $v=$f(...array_slice(array_merge([$v],$a),0,$n)); return $v; }
function do_action($h,...$a) { $hooks=$GLOBALS['hooks'][$h]??array(); ksort($hooks); foreach($hooks as $rows) foreach($rows as [$f,$n]) $f(...array_slice($a,0,$n)); }
function is_admin() { return false; }
function absint($v) { return abs((int)$v); }
function sanitize_key($v) { return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$v)); }
function sanitize_text_field($v) { return trim(strip_tags((string)$v)); }
function sanitize_textarea_field($v) { return sanitize_text_field($v); }
function __($v,...$a) { return $v; }
function esc_html__($v,...$a) { return esc_html($v); }
function esc_html($v) { return htmlspecialchars((string)$v, ENT_QUOTES); }
function esc_attr($v) { return esc_html($v); }
function esc_url($v) { return esc_html($v); }
function wp_unslash($v) { return $v; }
function wp_json_encode($v) { return json_encode($v); }
function wp_generate_uuid4() { static $n=0; return 'synthetic-'.++$n; }
function get_current_user_id() { return 7; }
function current_user_can(...$a) { return $GLOBALS['allow']; }
function get_post_type($id) { return 'vms_event_plan'; }
function get_post_meta($id,$key,$single=true) { return $GLOBALS['meta'][$id][$key]??''; }
function update_post_meta($id,$key,$value,$previous=null) { if($previous!==null && get_post_meta($id,$key)!==$previous) return false; if(get_post_meta($id,$key)===$value) return false; $GLOBALS['writes']++; $GLOBALS['meta'][$id][$key]=$value; return true; }
function delete_post_meta($id,$key) { $GLOBALS['writes']++; unset($GLOBALS['meta'][$id][$key]); }
function metadata_exists($type,$id,$key) { return isset($GLOBALS['meta'][$id][$key]); }
function get_posts($args) { return $GLOBALS['products']??array(101); }
function get_option($key,$default=false) { return $default; }
function get_the_title($id) { return 'Synthetic Event'; }
function get_edit_post_link($id,...$a) { return 'https://example.invalid/wp-admin/post.php?post='.$id.'&action=edit'; }
function admin_url($path) { return 'https://example.invalid/wp-admin/'.$path; }
function add_query_arg($a,$url) { return $url.'?'.http_build_query($a); }
function wp_nonce_field(...$a) { echo '<input type="hidden" name="_wpnonce" value="fake">'; }
function wp_die($message,...$a) { throw new RuntimeException((string)$message); }
function bvmgr_get_ticket_product_ids_for_event($id) { return $GLOBALS['products']??array(101); }
function bvmgr_cancellation_auto_refund_guard(...$a) { return ['allowed'=>true,'dry_run'=>false]; }
function bvmgr_event_credit_find_existing($event,$order) { return $GLOBALS['credit'][$order]??0; }
function wc_get_price_decimals() { return 2; }
function wc_get_is_paid_statuses() { return ['processing','completed']; }
function wc_get_order_statuses() { return ['wc-processing'=>'','wc-completed'=>'','wc-refunded'=>'','wc-on-hold'=>'']; }
function wc_get_payment_gateway_by_order($order) { return new class { function supports($x) { return true; } }; }
function wc_get_orders($args) { $all=array_values($GLOBALS['orders']); return (object)['orders'=>array_slice($all,($args['page']-1)*100,100),'total'=>count($all),'max_num_pages'=>(int)ceil(count($all)/100)]; }
function wc_get_order($id) { return isset($GLOBALS['orders'][$id])?clone $GLOBALS['orders'][$id]:null; }
class WP_Error { function __construct(public string $message) {} function get_error_message() { return $this->message; } }
function is_wp_error($v) { return $v instanceof WP_Error; }
function wp_mail(...$a) { throw new RuntimeException('Unexpected email'); }
function wc_create_refund($args) {
    $GLOBALS['calls'][]=$args;
    if(!empty($GLOBALS['woo_fail'][$args['order_id']])) return new WP_Error('Synthetic Woo failure');
    $order=$GLOBALS['orders'][$args['order_id']];
    foreach($args['line_items'] as $id=>$line) {
        if(($GLOBALS['omit_item']??0)===$id) continue;
        $order->refunded[$id]=($order->refunded[$id]??0)+$line['refund_total'];
        $order->qty_refunded[$id]=($order->qty_refunded[$id]??0)+$line['qty'];
        foreach(($line['refund_tax']??[]) as $tax=>$amount) $order->tax_refunded[$id][$tax]=($order->tax_refunded[$id][$tax]??0)+$amount;
    }
    return new class { function get_id() { return 999001; } };
}
class Item {
    function __construct(public int $pid,public float $qty,public float $total,public array $meta=[],public array $taxes=[],public string $name='Synthetic component',public string $type='line_item') {}
    function get_type(){return $this->type;}
    function get_product_id(){return $this->pid;} function get_variation_id(){return 0;} function get_meta($key,...$a){return $this->meta[$key]??'';}
    function get_meta_data(){return array_map(static function($k,$v){return new class($k,$v){function __construct(public $key,public $value){} function get_data(){return ['key'=>$this->key,'value'=>$this->value];}};},array_keys($this->meta),array_values($this->meta));}
    function get_name(){return $this->name;} function get_quantity(){return $this->qty;} function get_total(){return $this->total;} function get_taxes(){return ['total'=>$this->taxes];}
}
class Order {
    public array $refunded=[],$qty_refunded=[],$tax_refunded=[],$meta=[]; public float $unallocated=0; public string $status='completed'; public string $currency='USD';
    function __construct(public int $id,public array $items){}
    function get_id(){return $this->id;} function get_items(...$a){return $this->items;} function get_order_number(){return 'SYNTHETIC-'.$this->id;}
    function get_qty_refunded_for_item($id){return -($this->qty_refunded[$id]??0);} function get_total_refunded_for_item($id){return $this->refunded[$id]??0;} function get_tax_refunded_for_item($id,$tax){return $this->tax_refunded[$id][$tax]??0;}
    function get_total(){return array_sum(array_map(static fn($i)=>$i->total+array_sum($i->taxes),$this->items));}
    function get_total_refunded(){return array_sum($this->refunded)+array_sum(array_map('array_sum',$this->tax_refunded))+$this->unallocated;}
    function get_remaining_refund_amount(){return max(0,$this->get_total()-$this->get_total_refunded());}
    function get_status(){return $this->status;} function get_currency(){return $this->currency;} function get_formatted_billing_full_name(){return 'Fixture Customer';} function get_billing_email(){return 'fixture@example.invalid';}
    function get_meta($key,...$a){return $GLOBALS['orders'][$this->id]->meta[$key]??'';} function update_meta_data($k,$v){$this->meta[$k]=$v;} function save_meta_data(){$GLOBALS['writes']++;$GLOBALS['orders'][$this->id]->meta=$this->meta;}
    function get_edit_order_url(){return 'https://example.invalid/wp-admin/admin.php?page=wc-orders&id='.$this->id;}
}
require dirname(__DIR__).'/includes/runtime-guards.php';
require dirname(__DIR__).'/includes/core/cancellation.php';
require dirname(__DIR__).'/includes/core/cancellation-adapters.php';
require dirname(__DIR__).'/includes/admin/cancellation-report.php';
add_filter('vms_cancellation_auto_run_enabled',static fn()=>false);
$checks=[];
function check($name,$condition){global $checks;if(!$condition)throw new RuntimeException('FAIL: '.$name);$checks[]=$name;}
function reset_fixture($bar=true){$GLOBALS['meta']=[100=>['_vms_tec_event_id'=>200,'_vms_event_date'=>'2026-09-20'],101=>['_tribe_wooticket_for_event'=>200]];$GLOBALS['products']=[101];$GLOBALS['orders']=[97555=>new Order(97555,[501=>new Item(101,2,20,[],[],'GA tickets')])];if($bar)$GLOBALS['orders'][97555]->items[502]=new Item(202,6,36,['_vms_express_bar_event_plan_id'=>100,'_vms_express_bar'=>1],[],'Six Corona preorders');$GLOBALS['calls']=[];$GLOBALS['woo_fail']=[];$GLOBALS['credit']=[];$GLOBALS['omit_item']=0;$GLOBALS['allow']=true;}
function prepare_job(){ $job=bvmgr_cancellation_create_job(100,['policy'=>'stop_sales_auto_refund','auto_refund_confirmed'=>true]);$s=get_post_meta(100,'_vms_cancel_job_summary');foreach($s['steps'] as &$step)if(in_array($step['key'],['provider_sales_stop','notifications'],true))$step['status']='done';unset($step);update_post_meta(100,'_vms_cancel_job_summary',$s);bvmgr_cancellation_run_job(100);return get_post_meta(100,'_vms_cancel_job_summary'); }
function accept_job($reasons=[]){$s=get_post_meta(100,'_vms_cancel_job_summary');$scope=bvmgr_cancel_purchase_discover(100);return bvmgr_cancel_accept_preflight(100,$s['job_id'],bvmgr_cancel_scope_hash($scope),$reasons);}
function execute_job(){bvmgr_cancellation_run_job(100);return get_post_meta(100,'_vms_cancel_job_summary')['purchase_report'];}
check('optional wc_can_refund_order helper may be absent',!function_exists('wc_can_refund_order'));
reset_fixture(false);$s=prepare_job();check('preflight blocks automatic execution',count($GLOBALS['calls'])===0&&$s['final_state']==='planned');check('tickets preflight accepted',accept_job()['ok']);$r=execute_job();check('tickets only success',$r['status']==='completed_successfully'&&$r['totals']['USD']['actual']===20.0);
reset_fixture();$s=prepare_job();$d=bvmgr_cancel_step_scope($s);check('synthetic #7555: both GA and six Corona discovered',array_column($d['candidates'][0]['line_items'],'item_id')===[501,502]);check('mixed accepted',accept_job()['ok']);$r=execute_job();check('synthetic #7555: complete 56 refund, zero residual',$r['totals']['USD']['actual']===56.0&&$r['totals']['USD']['residual']===0.0&&count($GLOBALS['calls'][0]['line_items'])===2);$saved=get_post_meta(100,'_vms_cancel_job_summary');bvmgr_cancellation_run_job(100);check('idempotent completed run',count($GLOBALS['calls'])===1);$retry=bvmgr_cancel_execute_scope(100,$saved);check('idempotent direct executor',count($GLOBALS['calls'])===1&&$retry['status']==='done');
$writes=$GLOBALS['writes'];$_GET=['event_plan_id'=>100];ob_start();bvmgr_cancel_report_page();$html=ob_get_clean();check('report rendering zero writes',$writes===$GLOBALS['writes']&&count($GLOBALS['calls'])===1);check('persistent report includes customer bar and back link',str_contains($html,'Six Corona')&&str_contains($html,'Back to Event Plan')&&str_contains($html,'Fixture Customer'));check('report persisted exactly',bvmgr_cancel_report_select($saved,$saved['job_id'],'')['purchase_report']===$r);$run=end($saved['runs']);check('historical run projection',bvmgr_cancel_report_select($saved,$saved['job_id'],$run['run_id'])['purchase_report']===$r);ob_start();bvmgr_cancel_report_links(100,$saved);$links=ob_get_clean();check('Event Plan reentry',str_contains($links,'bvm-cancellation-report'));
reset_fixture();foreach(['table','fire_pit','pool','numbered_reservation'] as $i=>$name){$pid=300+$i;$GLOBALS['orders'][97555]->items[600+$i]=new Item($pid,1,10,['_vms_event_plan_id'=>100],[],$name);$GLOBALS['meta'][$pid]=['_vms_product_role'=>'addon'];}prepare_job();accept_job();$r=execute_job();check('tickets plus multiple add-on providers aggregated',count($r['orders'])===1&&count($r['orders'][0]['line_items'])===6&&$r['totals']['USD']['actual']===96.0);
reset_fixture();$GLOBALS['orders'][97555]->refunded[501]=5; $GLOBALS['orders'][97555]->qty_refunded[501]=0;prepare_job();accept_job();$r=execute_job();check('monetary partial refund does not over-refund',$r['totals']['USD']['actual']===51.0&&$GLOBALS['calls'][0]['line_items'][501]['refund_total']===15.0);
reset_fixture(false);$GLOBALS['orders'][97555]->items[501]->taxes=[1=>2,2=>1];$GLOBALS['orders'][97555]->refunded[501]=10;$GLOBALS['orders'][97555]->qty_refunded[501]=1;$GLOBALS['orders'][97555]->tax_refunded[501]=[1=>0.5,2=>1];prepare_job();accept_job();$r=execute_job();check('prior quantity and tax refund use Woo balances',$r['totals']['USD']['actual']===11.5&&$GLOBALS['calls'][0]['line_items'][501]['refund_tax']===[1=>1.5,2=>0.0]);
reset_fixture();prepare_job();check('explicit exclusion accepted',accept_job(['97555:502'=>'Preorder already fulfilled; operator reviewed'])['ok']);$r=execute_job();check('exclusion distinguished from omission',$r['status']==='completed_with_exclusions'&&$r['totals']['USD']['excluded']===36.0&&$r['totals']['USD']['residual']===0.0&&$r['orders'][0]['line_items'][1]['exclusion']['actor']===7);
reset_fixture();$GLOBALS['orders'][97555]->items[503]=new Item(303,1,9,['_future_event_context'=>999]);check('known other event in unknown event field stays outside scope',count(bvmgr_cancel_purchase_discover(100)['candidates'][0]['line_items'])===2);
reset_fixture();$GLOBALS['orders'][97555]->items[503]=new Item(303,1,9,['_future_event_context'=>100]);prepare_job();check('unsupported component blocks',!accept_job()['ok']);$d=bvmgr_cancel_purchase_discover(100);check('unsupported line visible',count($d['candidates'][0]['line_items'])===3&&$d['candidates'][0]['line_items'][2]['state']==='unresolved');
reset_fixture();$GLOBALS['orders'][97555]->items[502]->meta['_vms_event_plan_id']=999;prepare_job();check('conflicting snapshots block',!accept_job()['ok']);
reset_fixture();$GLOBALS['orders'][97555]->items[503]=new Item(101,1,10,['_vms_event_plan_id'=>999]);$d=bvmgr_cancel_purchase_discover(100);check('other-event snapshot outranks shared product',count($d['candidates'][0]['line_items'])===2);
reset_fixture();$GLOBALS['products']=[];unset($GLOBALS['meta'][100]['_vms_tec_event_id']);unset($GLOBALS['orders'][97555]->items[501]);$d=bvmgr_cancel_purchase_discover(100);check('bar-only without TEC or ticket catalog',count($d['candidates'])===1&&$d['candidates'][0]['line_items'][0]['provider']==='express_bar');
reset_fixture();add_filter('vms_cancellation_purchase_providers',static function($p){if(!empty($GLOBALS['provider_fail']))$p['broken']=['match'=>static function(){throw new RuntimeException('synthetic provider failure');}];return $p;});$GLOBALS['provider_fail']=true;prepare_job();check('provider failure visible and blocked',!accept_job()['ok']&&get_post_meta(100,'_vms_cancel_job_summary')['final_state']==='attention_required');$GLOBALS['provider_fail']=false;
reset_fixture();$GLOBALS['woo_fail'][97555]=true;prepare_job();accept_job();$r=execute_job();check('Woo refund failure attention required',$r['status']==='attention_required'&&$r['totals']['USD']['residual']===56.0);$saved=get_post_meta(100,'_vms_cancel_job_summary');bvmgr_cancel_execute_scope(100,$saved);check('uncertain attempt cannot duplicate transport',count($GLOBALS['calls'])===1);
reset_fixture();$GLOBALS['orders'][97556]=new Order(97556,[601=>new Item(101,1,10)]);$GLOBALS['woo_fail'][97556]=true;prepare_job();accept_job();$r=execute_job();check('mixed successful and failed orders',$r['status']==='attention_required'&&$r['counts']['success']===1&&$r['counts']['failure']===1);
reset_fixture();$GLOBALS['omit_item']=502;prepare_job();accept_job();$r=execute_job();check('independent reconciliation detects #7555 omission',$r['status']==='attention_required'&&$r['totals']['USD']['actual']===20.0&&$r['totals']['USD']['residual']===36.0);
reset_fixture(false);$GLOBALS['orders'][97555]->items[501]->total=0;prepare_job();accept_job();$r=execute_job();check('zero value deliberate without transport',$r['status']==='completed_successfully'&&!$GLOBALS['calls']);
reset_fixture(false);for($i=1;$i<=501;$i++)$GLOBALS['orders'][$i]=new Order($i,[1=>new Item(800,1,1)]);$d=bvmgr_cancel_purchase_discover(100);check('pagination beyond 500 completes',$d['orders_scanned']===502&&$d['coverage_complete']);add_filter('vms_cancellation_purchase_max_pages',static fn($n)=>$GLOBALS['cap_pages']??$n);$GLOBALS['cap_pages']=5;$d=bvmgr_cancel_purchase_discover(100);check('exact scan exhaustion visible',!$d['coverage_complete']&&in_array('incomplete_order_scan',$d['warnings']));unset($GLOBALS['cap_pages']);
reset_fixture();$s=prepare_job();$hash=bvmgr_cancel_scope_hash(bvmgr_cancel_purchase_discover(100));$GLOBALS['orders'][97555]->items[502]->total=40;check('stale preflight rejected',!bvmgr_cancel_accept_preflight(100,$s['job_id'],$hash,[])['ok']);$GLOBALS['allow']=false;check('acceptance permission denied',!accept_job()['ok']);$writes=$GLOBALS['writes'];try{$_GET=['event_plan_id'=>100];bvmgr_cancel_report_page();check('report capability denial',false);}catch(RuntimeException $e){check('report capability denial',$GLOBALS['writes']===$writes);} $GLOBALS['allow']=true;
reset_fixture();$s=prepare_job();$writes=$GLOBALS['writes'];$_GET=['event_plan_id'=>100];ob_start();bvmgr_cancel_report_page();$html=ob_get_clean();check('preflight GET zero writes',$writes===$GLOBALS['writes']&&!$GLOBALS['calls']);check('preflight form and component audit reasons',str_contains($html,'form="bvm-cancel-accept"')&&str_contains($html,'scope_hash')&&str_contains($html,'exclusions[97555:502]'));
$new=bvmgr_cancellation_create_job(100,['policy'=>'stop_sales_auto_refund']);$history=get_post_meta(100,'_vms_cancel_job_summary');check('new job preserves earlier report',isset($history['previous_jobs'][$s['job_id']]));check('legacy run explicitly unverified',bvmgr_cancel_job_report(100,['steps'=>[]])['status']==='attention_required');
reset_fixture();$GLOBALS['orders'][97555]->unallocated=4;prepare_job();check('unallocated historical refund blocks',!accept_job()['ok']);reset_fixture();$GLOBALS['credit'][97555]=123;prepare_job();check('existing event credit blocks double compensation',!accept_job()['ok']);

reset_fixture();prepare_job();accept_job();$accepted=get_post_meta(100,'_vms_cancel_job_summary')['purchase_preflight']['scope'];$GLOBALS['orders'][97556]=new Order(97556,[601=>new Item(202,1,6,['_vms_express_bar_event_plan_id'=>100])]);$r=bvmgr_cancel_reconcile($accepted,bvmgr_cancel_purchase_discover(100));check('new affected order counted as unexplained residual',count($r['orders'])===2&&$r['totals']['USD']['residual']===62.0&&$r['status']==='attention_required');
reset_fixture();prepare_job();accept_job();$GLOBALS['orders'][97555]->items[502]->total=40;$r=execute_job();check('changes after acceptance prevent transport',!$GLOBALS['calls']&&$r['status']==='attention_required');
reset_fixture(false);$GLOBALS['orders'][97555]->qty_refunded[501]=2;prepare_job();accept_job();$r=execute_job();check('zero remaining quantity does not erase refundable money',$r['totals']['USD']['actual']===20.0&&(float)$GLOBALS['calls'][0]['line_items'][501]['qty']===0.0);
reset_fixture();$GLOBALS['orders'][97555]->items[502]->meta=['_vms_express_bar_event_plan_id'=>'bad'];$d=bvmgr_cancel_purchase_discover(100);check('malformed snapshot remains visibly unresolved',$d['candidates'][0]['line_items'][1]['state']==='unresolved');
reset_fixture();$GLOBALS['orders'][97556]=new Order(97556,[601=>new Item(202,6,36,['_vms_express_bar_event_plan_id'=>999])]);prepare_job();accept_job();execute_job();check('shared Express Bar SKU other event never refunded',count($GLOBALS['calls'])===1&&$GLOBALS['calls'][0]['order_id']===97555);
reset_fixture();for($i=1;$i<=13;$i++)$GLOBALS['orders'][97600+$i]=new Order(97600+$i,[1=>new Item(101,1,10)]);prepare_job();accept_job();$r=execute_job();ob_start();bvmgr_cancel_report_render($r);$all=ob_get_clean();check('all orders rendered beyond old twelve-row preview',count($r['orders'])===14&&str_contains($all,'SYNTHETIC-97613'));
reset_fixture();$writes=$GLOBALS['writes'];$_GET=['event_plan_id'=>100];ob_start();bvmgr_cancel_report_page();$nojob=ob_get_clean();check('missing job GET creates no envelope',$writes===$GLOBALS['writes']&&str_contains($nojob,'No saved cancellation run'));
reset_fixture();$s=prepare_job();$GLOBALS['meta'][100]['_vms_cancel_job_state']='running';check('concurrent acceptance denied',!accept_job()['ok']);$GLOBALS['meta'][100]['_vms_cancel_job_state']='planned';
add_filter('vms_cancellation_purchase_providers',static function($p){if(!empty($GLOBALS['external']))$p['reservation_ledger']=['discover'=>static fn($context)=>['coverage_complete'=>true,'components'=>[['id'=>'reservation-1','remaining'=>12,'original_amount'=>12,'currency'=>'USD','name'=>'External reservation']]]];return $p;});reset_fixture();$GLOBALS['external']=true;prepare_job();check('external provider financial component blocks',!accept_job()['ok']);check('external provider explicit exclusion accepted',accept_job(['provider:reservation_ledger:reservation-1'=>'Handled by reservation provider; reference fixture-1'])['ok']);$r=execute_job();check('external provider included in persistent report',$r['status']==='completed_with_exclusions'&&$r['totals']['USD']['excluded']===12.0);$GLOBALS['external']=false;
check('cancellation save redirect targets report',str_contains(apply_filters('redirect_post_location','https://example.invalid/old',100),'bvm-cancellation-report'));
reset_fixture(false);$GLOBALS['orders'][97555]->items[700]=new Item(0,1,3,['_vms_event_plan_id'=>100],[1=>0.3],'Event fee','fee');$GLOBALS['orders'][97555]->items[701]=new Item(0,1,5,['_vms_event_plan_id'=>100],[],'Event shipping','shipping');prepare_job();accept_job();$r=execute_job();check('explicit event-scoped fee and shipping included',$r['totals']['USD']['actual']===28.3&&count($GLOBALS['calls'][0]['line_items'])===3);
reset_fixture();prepare_job();$scope=bvmgr_cancel_purchase_discover(100);check('normal preflight has planned order semantics',bvmgr_cancel_preflight_report($scope)['counts']['planned']===1);$current=$scope;$current['candidates'][0]['line_items'][0]['previously_refunded']=25.0;$current['candidates'][0]['line_items'][0]['remaining']=0.0;$current['candidates'][0]['line_items'][1]['previously_refunded']=36.0;$current['candidates'][0]['line_items'][1]['remaining']=0.0;check('unexpected excess refund cannot be called successful',bvmgr_cancel_reconcile($scope,$current)['status']==='attention_required');
reset_fixture();prepare_job();$summary=get_post_meta(100,'_vms_cancel_job_summary');foreach($summary['steps'] as &$step)if($step['key']==='provider_sales_stop'){$step['status']='blocked';$step['message']='synthetic sales stop failure';}unset($step);update_post_meta(100,'_vms_cancel_job_summary',$summary);accept_job();$r=execute_job();check('sales-stop failure blocks execution and remains prominent',!$GLOBALS['calls']&&$r['status']==='attention_required');
if (!empty($argv[1])) {
    $out=$argv[1]; if(!is_dir($out))mkdir($out,0700,true);
    reset_fixture();prepare_job();$_GET=['event_plan_id'=>100];ob_start();bvmgr_cancel_report_page();$pre=ob_get_clean();
    $GLOBALS['omit_item']=502;accept_job();execute_job();ob_start();bvmgr_cancel_report_page();$partial=ob_get_clean();
    $css='body{font:14px system-ui;background:#f0f0f1;color:#1d2327;margin:20px}.wrap{max-width:1400px}table{border-collapse:collapse;background:white;width:100%}th,td{padding:10px;text-align:left;vertical-align:top;border-bottom:1px solid #ddd}th{font-weight:600}.notice{background:white;border-left:4px solid #dba617;padding:4px 12px;margin:12px 0}.notice-error{border-color:#d63638}.button{display:inline-block;padding:8px 12px;border:1px solid #2271b1;background:#fff;color:#2271b1;text-decoration:none}a{color:#2271b1}textarea{font:inherit}';
    foreach(['preflight'=>$pre,'partial'=>$partial] as $name=>$markup)file_put_contents($out.'/'.$name.'.html','<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Cancellation fixture</title><style>'.$css.'</style>'.$markup.'</html>');
}
echo json_encode(['scope'=>'Pure in-memory; no WordPress bootstrap, database, mail, gateway or real order','passed'=>count($checks),'checks'=>$checks],JSON_PRETTY_PRINT)."\n";
