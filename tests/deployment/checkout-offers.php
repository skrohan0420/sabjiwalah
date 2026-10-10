<?php
// Real MySQL/services on connection-local temporary tables; no persistent orders or stock are changed.
define('ENVIRONMENT', 'development'); define('FCPATH', dirname(__DIR__, 2) . '/public/');
require dirname(__DIR__, 2) . '/app/Config/Paths.php'; $paths = new Config\Paths(); require $paths->systemDirectory . '/Boot.php';
CodeIgniter\Boot::bootConsole($paths); service('session'); session_start(); date_default_timezone_set(config('App')->appTimezone);
$db = db_connect(); if ($db->DBDriver !== 'MySQLi' || $db->DBPrefix !== '') throw new RuntimeException('Use local MySQL without prefix.');
foreach (['users','products','offers','promotions','orders','order_items','order_status_history','offer_redemptions','operational_settings','operational_setting_history'] as $table) {
    $ddl = $db->query('SHOW CREATE TABLE ' . $table)->getRowArray()['Create Table'];
    $ddl = str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', $ddl);
    $ddl = preg_replace('/^\s*CONSTRAINT .* FOREIGN KEY .*\n/m', '', $ddl);
    $db->query(preg_replace('/,\s*\)/', ')', $ddl));
}
$db->table('operational_settings')->insert(['id'=>1,'shop_open'=>1,'orders_paused'=>0,'delivery_charge'=>40,'free_delivery_minimum'=>499,'minimum_order_amount'=>0,'revision'=>1,'updated_at'=>date('Y-m-d H:i:s')]);
function offerCheck(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function offerReject(callable $action, string $type): void {
    $caught = null; try { $action(); } catch (Throwable $e) { $caught = $e; }
    if (!$caught || !($caught instanceof $type)) throw new RuntimeException('Expected ' . $type . ', received ' . ($caught ? get_class($caught) . ': ' . $caught->getMessage() : 'success'));
}
$user = (new App\Models\UserModel())->insert(['name'=>'Fixture','phone'=>'9876550000','role'=>'customer','status'=>'active']);
session()->set(['user_id'=>$user,'user_role'=>'customer','is_logged_in'=>true]);
$products = new App\Models\ProductModel();
$id = $products->insert(['name'=>'Sample product','slug'=>'sample-offer-product','price'=>'100.00','sale_price'=>'80.00','unit'=>'kg','stock_quantity'=>20,'is_active'=>1]);
$uid = $products->find($id)['uid']; $cart = new App\Services\CartService(); $cart->add($uid, 3);
$offers = new App\Models\OfferModel();
$offerId = $offers->insert(['name'=>'Sample','code'=>'SAVE10','type'=>'percentage','value'=>'10.00','minimum_order_amount'=>'200.00','maximum_discount'=>'20.00','usage_limit'=>1,'is_active'=>1]);
$service = new App\Services\OfferService(); $summary = new App\Services\CheckoutSummaryService();
offerCheck($service->evaluate('save10', 240)['discount_amount'] === 20.0, 'Percent cap failed.');
offerReject(fn()=>$service->evaluate('SAVE10', 100), InvalidArgumentException::class);
offerReject(fn()=>$service->evaluate('MISSING', 240), InvalidArgumentException::class);
foreach ([['is_active'=>0],['starts_at'=>date('Y-m-d H:i:s',time()+10)],['ends_at'=>date('Y-m-d H:i:s')],['value'=>101],['maximum_discount'=>0]] as $change) {
    $offers->skipValidation(true)->update($offerId,$change); offerReject(fn()=>$service->evaluate('SAVE10',240),InvalidArgumentException::class);
    $offers->skipValidation(true)->update($offerId,['is_active'=>1,'starts_at'=>null,'ends_at'=>null,'value'=>10,'maximum_discount'=>20]);
}
$fixed = $offers->insert(['name'=>'Fixed','code'=>'FIXED','type'=>'fixed','value'=>300,'is_active'=>1]);
offerCheck($service->evaluate('FIXED',100)['discount_amount']===100.0,'Subtotal clamp failed.');
$fraction = $offers->insert(['name'=>'Fraction','code'=>'FRACTION','type'=>'percentage','value'=>'12.50','is_active'=>1]);
offerCheck($service->evaluate('FRACTION',0.05)['discount_amount']===0.01,'Cent rounding failed.');
session()->set('checkout_offer','SAVE10'); $quote = $summary->summary();
offerCheck($quote['subtotal']===240.0 && $quote['discount_amount']===20.0 && $quote['delivery_charge']===40.0 && $quote['total_amount']===260.0,'Checkout financial totals incorrect.');
offerCheck(!isset($quote['offer']) && !str_contains(json_encode($quote),'"offer_id"'),'Private offer fields leaked.');
$data = ['customer_name'=>'Fixture','customer_phone'=>'9876550000','address_line'=>'Test','city'=>'Sample','postal_code'=>'700001','quote_token'=>$quote['quote_token']];
$orders = new App\Services\OrderService();
offerReject(fn()=>$orders->createFromCart($user,array_replace($data,['quote_token'=>str_repeat('0',64)])),App\Services\OrderConflictException::class);
offerCheck($db->table('orders')->countAllResults()===0 && (int)$products->find($id)['stock_quantity']===20,'Stale quote altered records.');
$offers->update($offerId,['value'=>5]); offerReject(fn()=>$orders->createFromCart($user,$data),App\Services\OrderConflictException::class); $offers->update($offerId,['value'=>10]);
$products->update($id,['sale_price'=>90]); offerReject(fn()=>$orders->createFromCart($user,$data),App\Services\OrderConflictException::class); $products->update($id,['sale_price'=>80]);
// Fail after redemption insertion: every write, including stock, order and usage, must roll back.
$db->query('ALTER TABLE order_status_history CHANGE notes fixture_notes TEXT NULL');
try { offerReject(fn()=>$orders->createFromCart($user,$data),Throwable::class);
    offerCheck($db->table('orders')->countAllResults()===0 && $db->table('order_items')->countAllResults()===0 && $db->table('offer_redemptions')->countAllResults()===0
        && (int)$products->find($id)['stock_quantity']===20 && $cart->count()===3,'Partial checkout committed.');
} finally { $db->resetTransStatus(); $db->query('ALTER TABLE order_status_history CHANGE fixture_notes notes TEXT NULL'); }
$order = $orders->createFromCart($user,$data);
offerCheck((float)$order['discount_amount']===20.0 && (float)$order['total_amount']===260.0 && $order['payment_method']==='cod','Persisted totals wrong.');
$redemption = $db->table('offer_redemptions')->get()->getRowArray();
offerCheck($redemption['code']==='SAVE10' && (float)$redemption['discount_amount']===20.0 && (int)$redemption['order_id']===(int)$order['id'],'Redemption snapshot missing.');
offerCheck($cart->count()===0 && !session('checkout_offer') && (int)$products->find($id)['stock_quantity']===17,'Post-commit session/stock incorrect.');
offerReject(fn()=>$service->evaluate('SAVE10',240),InvalidArgumentException::class);
$orders->changeStatus((int)$order['id'],'cancelled',$user);
offerReject(fn()=>$service->evaluate('SAVE10',240),InvalidArgumentException::class);
$cart->add($uid,3);session()->set('checkout_offer','SAVE10');$unavailable=$summary->summary();
offerCheck($unavailable['quote_token']===null && $unavailable['offer_error'] && $unavailable['discount_amount']===0.0,'Exhausted selected coupon silently ignored.');
offerReject(fn()=>$orders->createFromCart($user,$data),InvalidArgumentException::class);
offerCheck($db->table('orders')->countAllResults()===1,'Exhausted coupon produced order.');
session()->remove('checkout_offer'); $cart->update($uid, 7);
offerCheck($summary->summary()['delivery_charge']===0.0,'Free delivery before discounts changed.');
$cart->update($uid,999);offerReject(fn()=>$orders->createFromCart($user,array_diff_key($data,['quote_token'=>true])),InvalidArgumentException::class);
$cart->update($uid,1);$plain=$orders->createFromCart($user,array_diff_key($data,['quote_token'=>true]));
offerCheck((float)$plain['discount_amount']===0.0 && (float)$plain['total_amount']===120.0,'Legacy no-coupon checkout changed.');
// Public promotions must obey scheduling and reject unsafe pre-existing URLs.
$promotions = new App\Models\PromotionModel();
foreach ([['title'=>'Visible <script>','position'=>'home','image'=>null,'link'=>'/products','is_active'=>1],
    ['title'=>'Disabled','position'=>'home','is_active'=>0],['title'=>'Other placement','position'=>'sidebar','is_active'=>1],
    ['title'=>'Future','position'=>'home','is_active'=>1,'starts_at'=>date('Y-m-d H:i:s',time()+60)],
    ['title'=>'Expired','position'=>'home','is_active'=>1,'ends_at'=>date('Y-m-d H:i:s')],
    ['title'=>'Unsafe legacy','position'=>'home','is_active'=>1,'link'=>'javascript:alert(1)']] as $row) $promotions->skipValidation(true)->insert($row);
$visible=(new App\Services\PromotionService())->home();offerCheck(count($visible)===1 && $visible[0]['title']==='Visible <script>' && !isset($visible[0]['id']),'Promotion eligibility/privacy failed.');
helper('deployment');$html=view('client/home',['products'=>[],'promotions'=>$visible]);offerCheck(str_contains($html,'Visible &lt;script&gt;') && !str_contains($html,'Unsafe legacy'),'Promotion output escaped incorrectly.');
echo "PASS: server discount arithmetic/caps/rounding, dates/minimum/usage, stale quotes, atomic rollback/order/stock/redemption, cancellation usage retention, no-coupon compatibility and scheduled safe promotions. Temporary SQL only.\n";
