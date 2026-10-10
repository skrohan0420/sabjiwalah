<?php
// Connection-local temporary tables: no persistent campaign records are created or changed.
define('ENVIRONMENT', 'development');
define('FCPATH', dirname(__DIR__, 2) . '/public/');
require dirname(__DIR__, 2) . '/app/Config/Paths.php';
$paths = new Config\Paths(); require $paths->systemDirectory . '/Boot.php';
CodeIgniter\Boot::bootConsole($paths);
date_default_timezone_set(config('App')->appTimezone);
$db = db_connect();
if ($db->DBDriver !== 'MySQLi' || $db->DBPrefix !== '') throw new RuntimeException('Use local MySQL without prefix.');
foreach (['offers', 'promotions'] as $table) {
    $ddl = $db->query('SHOW CREATE TABLE ' . $table)->getRowArray()['Create Table'];
    $db->query(str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', $ddl));
}
function campaignCheck(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function campaignAction(string $kind, string $method, array $data = [], ?string $uid = null, array $query = []): array {
    // Each real HTTP request starts with a new connection transaction state.
    db_connect()->resetTransStatus();
    service('validation')->reset();
    $request = new CodeIgniter\HTTP\IncomingRequest(config('App'), new CodeIgniter\HTTP\URI('http://localhost/'), json_encode($data), new CodeIgniter\HTTP\UserAgent());
    $request->setGlobal('get', $query);
    $controller = $kind === 'offers' ? new App\Controllers\Api\V1\Admin\OfferController() : new App\Controllers\Api\V1\Admin\PromotionController();
    $controller->initController($request, new CodeIgniter\HTTP\Response(config('App')), service('logger'));
    $response = $uid === null ? $controller->$method() : $controller->$method($uid);
    return [$response->getStatusCode(), json_decode($response->getBody(), true)];
}
$valid = ['name' => '<script>Sample offer</script>', 'code' => ' fresh10 ', 'type' => 'percentage', 'value' => '10.50',
    'minimum_order_amount' => '200', 'maximum_discount' => '80', 'usage_limit' => '5', 'starts_at' => '2026-10-01T00:00:00+05:30',
    'ends_at' => '2026-10-20T23:59:59+05:30', 'is_active' => 1];
$invalids = [['value' => '100.01'], ['value' => '0'], ['value' => 'NaN'], ['value' => '1e3'], ['value' => '1.001'],
    ['value' => []], ['value' => true], ['minimum_order_amount' => '-1'], ['maximum_discount' => '0'], ['usage_limit' => '0'],
    ['usage_limit' => '1.5'], ['usage_limit' => '4294967296'], ['is_active' => 'yes'], ['code' => ' '], ['code' => 'bad code'],
    ['name' => ' '], ['type' => 'other'], ['starts_at' => '2026-02-30T01:00:00Z'], ['starts_at' => '2026-10-01T00:00'],
    ['starts_at' => '2026-10-01T00:00:00+99:99'], ['starts_at' => '1000-01-01T00:00:00+05:30'],
    ['ends_at' => '2026-10-01T00:00:00+05:30'], ['uid' => 'off_injected'], ['discount_amount' => '99'], ['created_at' => 'x']];
foreach ($invalids as $change) {
    campaignCheck(campaignAction('offers', 'create', array_replace($valid, $change))[0] === 422, 'Invalid offer accepted: ' . json_encode($change));
}
campaignCheck((new App\Models\OfferModel())->countAllResults() === 0, 'Rejected writes created rows.');
[$status, $payload] = campaignAction('offers', 'create', $valid);
campaignCheck($status === 201, 'Offer creation failed: ' . json_encode($payload));
$offer = $payload['data']; $uid = $offer['uid'];
campaignCheck(str_starts_with($uid, 'off_') && $offer['code'] === 'FRESH10' && !isset($offer['id']) && strlen($offer['revision']) === 64, 'UID/code/privacy failed.');
campaignCheck((new App\Models\OfferModel())->where('uid', $uid)->first()['starts_at'] === '2026-09-30 18:30:00', 'Timezone conversion failed.');
campaignCheck(campaignAction('offers', 'create', $valid)[0] === 422, 'Duplicate code accepted.');
campaignCheck(campaignAction('offers', 'create', array_replace($valid, ['code' => 'new', 'type' => 'fixed', 'value' => '200']))[0] === 201, 'Fixed amount over 100 rejected.');
campaignCheck(campaignAction('offers', 'show', [], $uid)[0] === 200, 'Details failed.');
campaignCheck(campaignAction('promotions', 'show', [], $uid)[0] === 404, 'Campaign type crossed.');
campaignCheck(campaignAction('offers', 'show', [], 'off_missing')[0] === 404, 'Missing details accepted.');
campaignCheck(campaignAction('offers', 'update', $valid, $uid)[0] === 422, 'Missing revision accepted.');
$edit = array_replace($valid, ['is_active' => 0, 'expected_revision' => $offer['revision']]);
[$status, $payload] = campaignAction('offers', 'update', $edit, $uid);
campaignCheck($status === 200 && $payload['data']['status'] === 'disabled', 'Deactivation failed.');
campaignCheck(campaignAction('offers', 'update', $edit, $uid)[0] === 409, 'Stale edit accepted.');
campaignCheck(campaignAction('offers', 'update', $edit, 'off_missing')[0] === 404, 'Missing update accepted.');
campaignCheck(campaignAction('offers', 'update', array_replace($edit, ['code' => 'NEW', 'expected_revision' => $payload['data']['revision']]), $uid)[0] === 422, 'Duplicate edit accepted.');
campaignCheck(campaignAction('offers', 'show', [], $uid)[1]['data']['code'] === 'FRESH10', 'Rejected edit changed row.');
foreach ([['page' => '0'], ['per_page' => '101'], ['status' => 'bad'], ['search' => ['x']]] as $query)
    campaignCheck(campaignAction('offers', 'index', [], null, $query)[0] === 422, 'Invalid query accepted.');
$listing = campaignAction('offers', 'index', [], null, ['search' => 'fresh10', 'per_page' => '1'])[1]['data'];
campaignCheck($listing['pager']['total'] === 1 && count($listing['items']) === 1, 'Code search/pagination failed.');
$promotion = ['title' => 'Sample', 'image' => '/assets/images/banner.jpg', 'link' => 'https://example.test/products', 'position' => 'home',
    'starts_at' => null, 'ends_at' => null, 'is_active' => 1];
foreach (['javascript:alert(1)', '//example.test', 'http://example.test', 'https://user:secret@example.test', '/\\example.test', '/a b', "https://example.test/\nx"] as $url)
    foreach (['image', 'link'] as $field) campaignCheck(campaignAction('promotions', 'create', array_replace($promotion, [$field => $url]))[0] === 422, 'Unsafe URL accepted.');
[$status, $payload] = campaignAction('promotions', 'create', $promotion);
campaignCheck($status === 201 && str_starts_with($payload['data']['uid'], 'pro_'), 'Promotion creation failed.');
$promotionUid = $payload['data']['uid'];
$service = new App\Services\AdminCampaignService('promotions');
$row = (new App\Models\PromotionModel())->where('uid', $promotionUid)->first();
$future = date('Y-m-d\TH:i:s\Z', time() + 3600); $past = date('Y-m-d\TH:i:s\Z', time() - 3600);
foreach ([['starts_at' => $future, 'ends_at' => null, 'is_active' => 1, 'status' => 'scheduled'],
    ['starts_at' => null, 'ends_at' => $past, 'is_active' => 1, 'status' => 'expired'],
    ['starts_at' => null, 'ends_at' => null, 'is_active' => 1, 'status' => 'enabled'],
    ['starts_at' => null, 'ends_at' => null, 'is_active' => 0, 'status' => 'disabled']] as $state) {
    $expected = $state['status']; unset($state['status']);
    $current = $service->details($promotionUid);
    $saved = $service->save(array_replace($promotion, $state, ['expected_revision' => $current['revision']]), $promotionUid);
    campaignCheck($saved['status'] === $expected, 'Derived schedule status failed.');
    campaignCheck($service->listing(['page' => 1, 'per_page' => 20, 'status' => $expected])['pager']['total'] === 1, 'Schedule filter failed.');
}
// Read-only fields are never mass assigned. Both tables retain records after disabling.
campaignCheck($db->table('offers')->countAllResults() === 2 && $db->table('promotions')->countAllResults() === 1, 'Unexpected record deletion.');
echo "PASS: campaign validation, duplicate codes, UID/privacy, timezone conversion, schedule filters, create/edit/deactivate and stale edits. Temporary SQL only.\n";
