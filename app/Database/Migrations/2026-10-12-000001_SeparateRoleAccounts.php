<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use App\Services\OtpService;

/** Preserves every user ID and all foreign keys. Never merges conflicting accounts. */
class SeparateRoleAccounts extends Migration
{
    public function up()
    {
        $rows = $this->db->table('users')->select('id, role, phone, email')->get()->getResultArray();
        $seen = [];
        foreach ($rows as &$row) {
            $row['phone'] = trim((string) $row['phone']) === '' ? null : OtpService::normalizePhone($row['phone']);
            $row['email'] = strtolower(trim((string) $row['email'])) ?: null;
            foreach (['phone', 'email'] as $field) {
                if ($row[$field] === null) continue;
                $key = $field . ':' . $row['role'] . ':' . $row[$field];
                if (isset($seen[$key])) throw new \RuntimeException('Role account normalization conflict: users ' . $seen[$key] . ' and ' . $row['id'] . '. Resolve explicitly; no accounts were merged.');
                $seen[$key] = $row['id'];
            }
        }
        unset($row);
        $table = $this->db->protectIdentifiers($this->db->prefixTable('users'));
        $indexes = $this->db->query('SHOW INDEX FROM ' . $table)->getResultArray();
        $groups = [];
        foreach ($indexes as $index) if ((int) $index['Non_unique'] === 0) $groups[$index['Key_name']][] = $index['Column_name'];
        $changes = [];
        foreach ($groups as $name => $columns) {
            if ($columns === ['phone'] || $columns === ['email']) $changes[] = 'DROP INDEX ' . $this->db->protectIdentifiers($name);
        }
        if (!isset($groups['users_phone_role_unique'])) $changes[] = 'ADD UNIQUE KEY users_phone_role_unique (phone, role)';
        if (!isset($groups['users_email_role_unique'])) $changes[] = 'ADD UNIQUE KEY users_email_role_unique (email, role)';
        if ($changes) $this->db->query('ALTER TABLE ' . $table . ' ' . implode(', ', $changes));
        $this->db->transStart();
        foreach ($rows as $row) $this->db->table('users')->where('id', $row['id'])->update(['phone' => $row['phone'], 'email' => $row['email']]);
        $this->db->transComplete();
        if (!$this->db->transStatus()) throw new \RuntimeException('Account normalization failed; rerun after resolving the conflict.');
    }
    public function down()
    {
        throw new \RuntimeException('Role accounts cannot be safely collapsed. Restore uniqueness only after an explicit account review.');
    }
}
