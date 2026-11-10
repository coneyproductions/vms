<?php
// Display-only unit fixture. Never bootstraps WordPress or a payment gateway.
$source=file_get_contents(($argv[1]??getcwd()).'/includes/integrations/ticketing-rules-v2.php');
$start=strpos($source,'function bvmgr_ticketing_v2_current_cart_summary(): array');
$end=strpos($source,'function bvmgr_ticketing_v2_enqueue_front_bundle(): void',$start);
if($start===false||$end===false)throw new RuntimeException('Summary function missing');
function wp_strip_all_tags($s){return strip_tags($s);}
$wc=null;function WC(){return $GLOBALS['wc'];}
eval(substr($source,$start,$end-$start));
$passes=0;function check_summary($ok,$message){if(!$ok)throw new RuntimeException($message);$GLOBALS['passes']++;echo 'PASS '.$message."\n";}
check_summary(bvmgr_ticketing_v2_current_cart_summary()===[],'missing WooCommerce yields no invented total');
$wc=(object)['cart'=>null];check_summary(bvmgr_ticketing_v2_current_cart_summary()===[],'uninitialized cart yields no invented total');
final class ReadOnlyCart {public $calls=0;public $formatted='';public function get_total(){$this->calls++;return $this->formatted;}public function __call($n,$args){throw new RuntimeException('Unexpected cart operation '.$n);}}
$cart=new ReadOnlyCart();$wc=(object)['cart'=>$cart];
foreach(['<span class="amount">&#36;1.08</span>'=>'$1.08','<span>$2.17</span>'=>'$2.17','<span>€&nbsp;3,40</span>'=>"€\u{00a0}3,40",'<span>$0.00</span>'=>'$0.00'] as $input=>$expected){$cart->formatted=$input;check_summary(bvmgr_ticketing_v2_current_cart_summary()===['total'=>$expected],'Woo formatted total preserved: '.$expected);}
check_summary($cart->calls===4,'reads existing total only, with no tax/pricing/order/payment mutation');
echo json_encode(['passes'=>$passes,'scope'=>'local read-only stubs, no WP or gateway'])."\n";
