<?php
// Original Express Bar hooks executed against in-memory WC fixtures only.
define('ABSPATH', __DIR__);$checks=[];$hooks=[];
function ck($v,$name){global$checks;if(!$v)throw new RuntimeException($name);$checks[]=$name;}
function add_filter(...$x){$GLOBALS['hooks'][]=$x;}function add_action(...$x){$GLOBALS['hooks'][]=$x;}function add_shortcode(...$x){$GLOBALS['hooks'][]=$x;}
function get_post_meta($id,$key,$single=true){return ['_vms_express_bar_enabled'=>'1','_vms_express_bar_product_ids'=>'900','_vms_express_bar_headline'=>'Express Bar','_vms_express_bar_pickup_instructions'=>'Collect at bar'][$key]??'';}
function absint($v){return abs((int)$v);}function sanitize_text_field($v){return strip_tags($v);}function wp_unslash($v){return $v;}function get_post_type($id){return$id===2?'vms_event_plan':'other';}function get_the_title($id){return'Synthetic event';}
function bvmgr_request_read_absint($a,$k,$default=0){return isset($a[$k])&&!is_array($a[$k])?absint($a[$k]):$default;}
function bvmgr_nonce_action_for_value($nonce,$action){return$action;}function wp_verify_nonce($nonce,$action){return$nonce==='synthetic'&&$action==='bvmgr_express_bar_add_900';}
function __($s,$domain){return$s;}function wc_clean($s){return$s;}function wc_add_notice($s,$kind){$GLOBALS['notices'][]=$kind;}
class WC_Order{public $meta=[];function update_meta_data($key,$value){$this->meta[$key]=$value;}}
class WC_Order_Item_Product{public $meta=[];function add_meta_data($k,$v,$unique){$this->meta[$k]=$v;}}
class FixtureCart{public $items=[];function get_cart(){return$this->items;}}
$GLOBALS['wc']=(object)['cart'=>new FixtureCart];function WC(){return$GLOBALS['wc'];}
require($argv[1]??getcwd()).'/includes/public/express-bar.php';
ck(count($hooks)>=6,'Original Express Bar hooks still register');
$_POST=[];$ticket=['ticket'=>'keep'];ck(bvmgr_express_bar_capture_cart_item_data($ticket,101,0)===$ticket,'Ticket-only metadata remains untouched');ck(bvmgr_express_bar_validate_add_to_cart(true,101,1),'Ticket-only admission is not intercepted');
$_POST=['vms_express_bar'=>'1','vms_express_bar_event_plan_id'=>'2','bvmgr_express_bar_nonce'=>'wrong'];ck(bvmgr_express_bar_capture_cart_item_data([],900,0)===[],'Invalid nonce cannot stamp bar metadata');ck(!bvmgr_express_bar_validate_add_to_cart(true,900,1),'Invalid Express Bar nonce remains rejected');
$_POST=['vms_express_bar'=>'1','vms_express_bar_event_plan_id'=>'2','bvmgr_express_bar_nonce'=>'synthetic','vms_express_bar_quantity'=>'2','vms_express_bar_pickup_name'=>'Synthetic pickup'];
$bar=bvmgr_express_bar_capture_cart_item_data([],900,0);ck($_POST['quantity']===2&&$_REQUEST['quantity']===2,'Native Express Bar quantity remains two');ck($bar['_vms_express_bar_event_plan_id']===2&&$bar['_vms_express_bar_pickup_name']==='Synthetic pickup','Native event/pickup attribution preserved');ck(bvmgr_express_bar_validate_add_to_cart(true,900,2),'Valid native bar addition remains accepted');
WC()->cart->items=[$ticket,$bar];$order=new WC_Order;bvmgr_express_bar_add_order_meta($order,[]);ck($order->meta['_vms_express_bar_order']==='1'&&$order->meta['_vms_express_bar_event_plan_ids']==='2','Mixed order keeps bar attribution');ck($order->meta['_vms_express_bar_queue_status']==='new'&&$order->meta['_vms_express_bar_id_verified']==='0','Bar fulfillment/verification defaults retained');
$item=new WC_Order_Item_Product;bvmgr_express_bar_add_order_line_item_meta($item,'synthetic',$bar,$order);ck($item->meta['_vms_express_bar']==='1'&&$item->meta['_vms_express_bar_pickup_name']==='Synthetic pickup','Mixed order bar line keeps pickup metadata');$nativeTicket=new WC_Order_Item_Product;bvmgr_express_bar_add_order_line_item_meta($nativeTicket,'ticket',$ticket,$order);ck($nativeTicket->meta===[],'Bar metadata does not leak onto ticket line');
ck(count(bvmgr_express_bar_cart_item_data([],$bar))===2,'Native cart still displays event/pickup details');
echo json_encode(['status'=>'PASS','checks'=>count($checks),'records'=>$checks,'scope'=>'Original native BVM Express Bar PHP; in-memory Woo fixtures, no order/provider writes'],JSON_PRETTY_PRINT)."\n";
