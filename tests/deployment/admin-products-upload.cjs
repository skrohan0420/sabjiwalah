// Genuine multipart uploads on a loopback server with temporary SQL and storage.
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const os = require('node:os');
const path = require('node:path');
const {spawn} = require('node:child_process');
(async () => {
  const root = path.resolve(__dirname, '../..').replaceAll('\\', '/');
  const directory = await fs.mkdtemp(path.join(os.tmpdir(), 'sabjiwalah-product-upload-'));
  for (const name of ['logs','cache','session','uploads']) await fs.mkdir(path.join(directory, 'writable', name), {recursive:true});
  const router = `<?php
if (PHP_SAPI !== 'cli-server' || !in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1','::1'], true)) exit;
define('ENVIRONMENT', 'development'); define('FCPATH', '${root}/public/');
require '${root}/app/Config/Paths.php'; $paths = new Config\\Paths(); $paths->writableDirectory = __DIR__ . '/writable';
require $paths->systemDirectory . '/Boot.php'; CodeIgniter\\Boot::bootWorker($paths);
$db = db_connect(); if ($db->DBDriver !== 'MySQLi' || $db->DBPrefix !== '') throw new RuntimeException('Use local MySQL without prefix');
$ddl = $db->query('SHOW CREATE TABLE products')->getRowArray()['Create Table']; $db->query(str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', $ddl));
$products = new App\\Models\\ProductModel(); $id = $products->skipValidation(true)->insert(['name'=>'Upload fixture','slug'=>'upload-fixture','price'=>10,'unit'=>'kg','stock_quantity'=>1,'is_active'=>1]); $uid = $products->find($id)['uid'];
$request = new CodeIgniter\\HTTP\\IncomingRequest(config('App'), new CodeIgniter\\HTTP\\URI('http://localhost/'), null, new CodeIgniter\\HTTP\\UserAgent());
$controller = new App\\Controllers\\Api\\V1\\Admin\\ProductController(); $controller->initController($request,new CodeIgniter\\HTTP\\Response(config('App')),service('logger'));
$response = $controller->uploadImage($uid); $data = json_decode($response->getBody(),true); $stored = $data['data']['product']['image'] ?? null;
if ($stored) {
  try {
    $media = new App\\Controllers\\ProductMediaController(); $media->initController($request,new CodeIgniter\\HTTP\\Response(config('App')),service('logger'));
    $image = $media->show(basename($stored)); $data['media_type'] = $image->getHeaderLine('Content-Type'); $data['nosniff'] = $image->getHeaderLine('X-Content-Type-Options'); $data['image_hash'] = hash('sha256',$image->getBody());
    $removed = $controller->removeImage($uid); $data['removed'] = json_decode($removed->getBody(),true)['data']['product']['image'] === null;
  } finally { (new App\\Services\\ProductImageService())->remove($stored); }
}
http_response_code($response->getStatusCode()); header('Content-Type: application/json'); echo json_encode($data);`;
  await fs.writeFile(path.join(directory,'router.php'),router);
  const server = spawn(process.env.PHP_BINARY || 'D:/xampp/php/php.exe', ['-S','127.0.0.1:8767',path.join(directory,'router.php')], {cwd:root, windowsHide:true, stdio:['ignore','ignore','pipe']});
  let diagnostics=''; server.stderr.on('data', chunk=>{diagnostics += chunk.toString();});
  try {
    let ready=false;
    for(let attempt=0;attempt<40;attempt++) {
      try {await fetch('http://127.0.0.1:8767/', {method:'POST'}); ready=true; break;} catch {await new Promise(resolve=>setTimeout(resolve,100));}
    }
    assert.ok(ready, diagnostics);
    const png=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aF9sAAAAASUVORK5CYII=','base64');
    async function upload(bytes, name, type) {
      const body=new FormData(); body.append('image',new Blob([bytes],{type}),name);
      const response=await fetch('http://127.0.0.1:8767/',{method:'POST',body});
      return [response.status,await response.json()];
    }
    const [status,data]=await upload(png,'../../danger.php','application/x-php');
    assert.equal(status,200,JSON.stringify(data)); assert.match(data.data.product.image,/^media\/products\/[a-f0-9]{48}\.png$/);
    assert.match(data.media_type,/image\/png/); assert.equal(data.nosniff,'nosniff'); assert.equal(data.removed,true);
    assert.equal(data.image_hash,require('node:crypto').createHash('sha256').update(png).digest('hex'));
    for(const [bytes,name,type] of [[Buffer.from('<?php echo 1;'),'fake.png','image/png'],[Buffer.from('<svg></svg>'),'image.svg','image/svg+xml'],[Buffer.alloc(2097153),'huge.png','image/png']]) {
      const [rejected]=await upload(bytes,name,type); assert.equal(rejected,422,name);
    }
    console.log('PASS: genuine multipart upload, content validation, safe random filename, controlled media response, remove image, executable/SVG/oversize rejection. Temporary SQL and storage only.');
  } finally {
    server.kill(); await new Promise(resolve=>server.once('exit',resolve));
    const resolved=path.resolve(directory); assert.ok(resolved.startsWith(path.resolve(os.tmpdir())+path.sep) && path.basename(resolved).startsWith('sabjiwalah-product-upload-'));
    await fs.rm(resolved,{recursive:true,force:true});
  }
})().catch(error=>{console.error(error);process.exitCode=1;});
