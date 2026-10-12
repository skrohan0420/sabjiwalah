<?php
// Real migration/model regressions; only connection-local temporary tables are modified.
define('ENVIRONMENT', 'development');
define('FCPATH', dirname(__DIR__, 2) . '/public/');
require dirname(__DIR__, 2) . '/app/Config/Paths.php';
$paths = new Config\Paths(); require $paths->systemDirectory . '/Boot.php';
CodeIgniter\Boot::bootConsole($paths);
$db = db_connect();
if ($db->DBDriver !== 'MySQLi' || $db->DBPrefix !== '' || !in_array($db->hostname, ['localhost','127.0.0.1','::1'], true)) throw new RuntimeException('Local MySQL required.');
foreach (['users','orders','delivery_assignments','order_status_history'] as $table) {
    $ddl = $db->query('SHOW CREATE TABLE ' . $table)->getRowArray()['Create Table'];
    $ddl = str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', $ddl);
    $ddl = preg_replace('/^\s*CONSTRAINT .* FOREIGN KEY .*\n/m', '', $ddl);
    $db->query(preg_replace('/,\s*\)/', ')', $ddl));
}
function roleCheck(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$users = new App\Models\UserModel();
$id = $users->insert(['name'=>'Existing Shopper','phone'=>'9876543201','email'=>'old@example.test','role'=>'customer','status'=>'active']);
$original = $users->find($id);
$db->table('orders')->insert(['id'=>1,'uid'=>'ord_existing','order_number'=>'EXISTING','user_id'=>$id,'subtotal'=>10,'total_amount'=>10,'customer_name'=>'Shopper','customer_phone'=>'9876543201','address_line'=>'Test','city'=>'Test','postal_code'=>'700001']);
$db->table('delivery_assignments')->insert(['id'=>1,'uid'=>'das_existing','order_id'=>1,'delivery_user_id'=>$id,'assigned_by'=>$id,'assigned_at'=>date('Y-m-d H:i:s')]);
$db->table('order_status_history')->insert(['uid'=>'osh_existing','order_id'=>1,'new_status'=>'pending','changed_by'=>$id]);
$refs = []; foreach (['orders','delivery_assignments','order_status_history'] as $table) $refs[$table] = $db->table($table)->get()->getResultArray();
require dirname(__DIR__,2).'/app/Database/Migrations/2026-10-12-000001_SeparateRoleAccounts.php';
$migration = new App\Database\Migrations\SeparateRoleAccounts(); $migration->up(); $migration->up();
roleCheck($users->find($id) === $original, 'Existing identity changed.');
foreach ($refs as $table=>$rows) roleCheck($db->table($table)->get()->getResultArray() === $rows, 'References changed.');
$ids=[]; $uids=[];
foreach (['customer','admin','delivery'] as $role) {
    $id=$users->insert(['name'=>'Shared Phone','phone'=>'+91 9876543210','email'=>' SHARED@example.test ','role'=>$role,'status'=>'active']);
    roleCheck((bool)$id, 'Cross-role reuse failed: '.json_encode($users->errors()));
    $row=$users->find($id);$ids[]=$id;$uids[]=$row['uid'];
    roleCheck($row['phone']==='9876543210' && $row['email']==='shared@example.test', 'Normalization failed.');
    roleCheck(!$users->insert(['name'=>'Duplicate','phone'=>'00919876543210','role'=>$role,'status'=>'active']), 'Duplicate phone allowed.');
    roleCheck(!$users->insert(['name'=>'Duplicate','phone'=>'9876543211','email'=>'shared@example.test','role'=>$role,'status'=>'active']), 'Duplicate email allowed.');
    roleCheck($users->update($id,['name'=>'Edited']), 'Partial edit failed.');
}
roleCheck(count(array_unique($ids))===3 && count(array_unique($uids))===3,'Identities were shared.');
roleCheck(!$users->update($ids[1], ['role'=>'customer']), 'Role change bypassed uniqueness.');
roleCheck(!$users->update($original['id'], ['email'=>'shared@example.test']), 'Email editing bypassed uniqueness.');
foreach (['9876543220','9876543221'] as $phone) roleCheck((bool)$users->insert(['name'=>'No Email','phone'=>$phone,'email'=>'','role'=>'customer','status'=>'active']), 'Empty emails collided.');
$batch = [];
foreach (['customer','admin','delivery'] as $role) $batch[] = ['name'=>'Import','phone'=>'+91 9876543230','email'=>' IMPORT@example.test ','role'=>$role,'status'=>'active'];
roleCheck($users->insertBatch($batch) === 3, 'Batch import failed.');
roleCheck($users->where('phone','9876543230')->where('email','import@example.test')->countAllResults() === 3, 'Batch normalization failed.');
roleCheck($users->updateBatch([['id'=>$ids[0],'email'=>'edited@example.test'],['id'=>$ids[1],'email'=>'edited@example.test']], 'id') === 2, 'Batch editing failed.');
foreach (['phone','email'] as $field) {
    $data=['uid'=>'usr_collision_'.$field,'name'=>'Raw Collision','role'=>'customer','status'=>'active',$field=>$field==='phone'?'9876543210':'edited@example.test'];
    $rejected=false;try { $db->table('users')->insert($data); } catch (Throwable) { $rejected=true; }
    roleCheck($rejected,'Database did not enforce '.$field.' uniqueness.');
}
$db->table('users')->insert(['uid'=>'usr_normalization_conflict','name'=>'Conflict','phone'=>'+91 9876543210','role'=>'customer','status'=>'active']);
$before = $db->table('users')->get()->getResultArray();
$conflict = false;
try { $migration->up(); } catch (RuntimeException $e) { $conflict = str_contains($e->getMessage(), 'normalization conflict'); }
roleCheck($conflict && $db->table('users')->get()->getResultArray() === $before, 'Migration did not refuse normalization conflict without changing data.');
echo "PASS: additive migration, preserved IDs/UIDs/references, three-role reuse, normalized phone/email, same-role model/database rejection, partial edits and role changes, nullable emails. Temporary SQL only.\n";
