<?php
// Controller and SQL tests use connection-local temporary tables only.
define('ENVIRONMENT', 'development');
define('FCPATH', dirname(__DIR__, 2) . '/public/');
require dirname(__DIR__, 2) . '/app/Config/Paths.php';
$paths = new Config\Paths();
require $paths->systemDirectory . '/Boot.php';
CodeIgniter\Boot::bootConsole($paths);
$db = db_connect();
if ($db->DBDriver !== 'MySQLi' || $db->DBPrefix !== '') throw new RuntimeException('Use local MySQL without a prefix.');
foreach (['products', 'order_items'] as $table) {
    $definition = $db->query("SHOW CREATE TABLE {$table}")->getRowArray()['Create Table'];
    $definition = str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', $definition);
    $definition = preg_replace('/^\s*CONSTRAINT .* FOREIGN KEY .*\n/m', '', $definition);
    $db->query(preg_replace('/,\s*\)/', ')', $definition));
}
function productCheck(bool $value, string $message): void { if (! $value) throw new RuntimeException($message); }
function productAction(string $action, array $body = [], ?string $uid = null, array $query = []): array {
    service('validation')->reset();
    $request = new CodeIgniter\HTTP\IncomingRequest(config('App'), new CodeIgniter\HTTP\URI('http://localhost/'), json_encode($body), new CodeIgniter\HTTP\UserAgent());
    $request->setGlobal('get', $query);
    $controller = new App\Controllers\Api\V1\Admin\ProductController();
    $controller->initController($request, new CodeIgniter\HTTP\Response(config('App')), service('logger'));
    $response = $uid === null ? $controller->$action() : $controller->$action($uid);
    return [$response->getStatusCode(), json_decode($response->getBody(), true)];
}
$body = ['name' => 'Fixture Tomato', 'slug' => 'fixture-tomato', 'price' => '80.00', 'sale_price' => '', 'unit' => 'kg', 'stock_quantity' => 10, 'is_active' => 1, 'uid' => 'prd_injected', 'image' => 'evil.php'];
[$status,$payload] = productAction('create', $body);
productCheck($status === 201, 'Create failed: ' . json_encode($payload));
$product = $payload['data']['product']; $uid = $product['uid'];
productCheck($uid !== 'prd_injected' && $product['image'] === null && $product['sale_price'] === null, 'Protected fields / empty sale normalization failed.');
foreach (['price' => '-1', 'stock_quantity' => '-1', 'sale_price' => '81', 'slug' => '../evil', 'unit' => '', 'name' => '', 'description' => str_repeat('x',10001)] as $field => $invalid) {
    [$status] = productAction('update', [$field => $invalid], $uid);
    productCheck($status === 422, 'Invalid ' . $field . ' accepted.');
}
[$status] = productAction('update', ['price' => '10.999'], $uid); productCheck($status === 422, 'Excess precision accepted.');
[$status] = productAction('update', ['stock_quantity' => '4294967296'], $uid); productCheck($status === 422, 'Stock overflow accepted.');
[$status] = productAction('create', $body); productCheck($status === 422, 'Duplicate slug accepted.');
[$status,$payload] = productAction('update', ['sale_price' => '50', 'stock_quantity' => 12, 'expected_stock_quantity' => 10], $uid);
productCheck($status === 200 && (float) $payload['data']['product']['sale_price'] === 50.0 && $payload['data']['product']['stock_quantity'] === 12, 'Valid price / stock edit failed: ' . json_encode($payload));
[$status] = productAction('update', ['stock_quantity' => 20, 'expected_stock_quantity' => 10], $uid); productCheck($status === 409, 'Stale stock accepted.');
[$status,$payload] = productAction('update', ['is_active' => 0], $uid);
productCheck($status === 200 && $payload['data']['product']['stock_quantity'] === 12, 'Partial availability update overwrote stock.');
productAction('update', ['is_active' => 1, 'sale_price' => ''], $uid);
$stored = (new App\Models\ProductModel())->findByUid($uid);
$db->table('order_items')->insert(['uid' => 'itm_fixture', 'order_id' => 1, 'product_id' => $stored['id'], 'product_name' => 'Historical Tomato', 'unit' => 'kg', 'quantity' => 2, 'unit_price' => 30, 'total_price' => 60]);
[$status] = productAction('update', ['name' => 'Renamed Tomato', 'price' => '99'], $uid);
productCheck($status === 200, 'Rename/price update failed.');
[$status] = productAction('delete', [], $uid); productCheck($status === 200, 'Archive failed.');
$archived = (new App\Models\ProductModel())->findByUid($uid);
productCheck($archived && ! $archived['is_active'], 'Archive must retain the product.');
$item = $db->table('order_items')->get()->getRowArray();
productCheck($item['product_name'] === 'Historical Tomato' && (float) $item['unit_price'] === 30.0 && (int) $item['product_id'] === (int) $stored['id'], 'Archive altered historical items.');
[$status,$payload] = productAction('index', [], null, ['active' => '0', 'search' => 'Tomato', 'per_page' => '1']);
productCheck($status === 200 && $payload['data']['pager']['total'] === 1, 'Filtered pagination failed.');
[$status] = productAction('show', [], 'prd_missing'); productCheck($status === 404, 'Missing product accepted.');
$images = new App\Services\ProductImageService();
$file = tempnam(sys_get_temp_dir(), 'product-image-');
try {
    file_put_contents($file, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aF9sAAAAASUVORK5CYII='));
    productCheck($images->inspect($file, filesize($file)) === 'png', 'Valid PNG rejected.');
    foreach ([0,2097153] as $size) { try { $images->inspect($file,$size); throw new RuntimeException('Invalid size accepted'); } catch (InvalidArgumentException) {} }
    $oversizeDimensions = substr_replace(file_get_contents($file), pack('N', 4097), 16, 4);
    file_put_contents($file, $oversizeDimensions);
    try { $images->inspect($file, filesize($file)); throw new RuntimeException('Oversize dimensions accepted'); } catch (InvalidArgumentException) {}
    file_put_contents($file, '<?php echo "executable";');
    try { $images->inspect($file,filesize($file)); throw new RuntimeException('Executable upload accepted'); } catch (InvalidArgumentException) {}
    file_put_contents($file, '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
    try { $images->inspect($file,filesize($file)); throw new RuntimeException('SVG upload accepted'); } catch (InvalidArgumentException) {}
} finally { unlink($file); }
echo "PASS: create/edit, protected fields, numeric validation, duplicate slugs, partial updates, stock conflicts, archive/snapshots, search/pagination and image type/size validation. Persistent records unchanged.\n";
