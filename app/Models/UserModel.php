<?php

namespace App\Models;

use App\Models\Concerns\HasUid;
use CodeIgniter\Model;

class UserModel extends Model
{
    use HasUid;

    protected $table = 'users';
    protected $primaryKey = 'id';
    protected string $uidPrefix = 'usr';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $beforeInsert = ['ensureUid', 'normalizeAccount'];
    protected $beforeUpdate = ['normalizeAccount'];
    protected $allowedFields = [
        'uid',
        'name',
        'email',
        'phone',
        'password_hash',
        'role',
        'status',
    ];

    protected $validationRules = [
        'name'          => 'required|max_length[120]',
        'email'         => 'permit_empty|valid_email|max_length[190]',
        'phone'         => 'permit_empty|max_length[30]',
        'password_hash' => 'permit_empty|max_length[255]',
        'role'          => 'required|in_list[customer,admin,delivery]',
        'status'        => 'required|in_list[active,inactive]',
    ];

    protected function normalizeAccount(array $event): array
    {
        foreach (['phone', 'email'] as $field) {
            if (!array_key_exists($field, $event['data'])) continue;
            $value = trim((string) $event['data'][$field]);
            $event['data'][$field] = $value === '' ? null : ($field === 'phone'
                ? \App\Services\OtpService::normalizePhone($value) : strtolower($value));
        }
        return $event;
    }

    public function update($id = null, $row = null): bool
    {
        // Account editing must validate against the persisted role, never a caller-supplied exclusion ID.
        if (!is_scalar($id) || !is_array($row)) throw new \InvalidArgumentException('Update one account by ID.');
        $existing = $this->find($id);
        if (!$existing) return false;
        $row = array_merge(array_intersect_key($existing, array_flip(['role', 'phone', 'email'])), $row);
        $row['id'] = $id;
        return parent::update($id, $row);
    }

    public function validate($row): bool
    {
        $this->ensureValidation();
        try { $row = $this->normalizeAccount(['data' => (array) $row])['data']; }
        catch (\InvalidArgumentException $e) { $this->validation->setError('phone', $e->getMessage()); return false; }
        if (!parent::validate($row)) return false;
        foreach (['phone', 'email'] as $field) {
            if (empty($row[$field])) continue;
            $query = $this->db->table($this->table)->where($field, $row[$field])->where('role', $row['role'] ?? 'customer');
            if (!empty($row['id'])) $query->where('id !=', $row['id']);
            if ($query->countAllResults() > 0) {
                $this->validation->setError($field, ucfirst($field) . ' already belongs to an account in this role.');
                return false;
            }
        }
        return true;
    }

    public function insertBatch(?array $set = null, ?bool $escape = null, int $batchSize = 100, bool $testing = false)
    {
        if ($set !== null) foreach ($set as &$row) $row = $this->normalizeAccount($this->ensureUid(['data' => $row]))['data'];
        unset($row);
        return parent::insertBatch($set, $escape, $batchSize, $testing);
    }

    public function updateBatch(?array $set = null, ?string $index = null, int $batchSize = 100, bool $returnSQL = false)
    {
        // Account changes need the persisted role and exclusion ID for each row.
        if ($index !== 'id' || $returnSQL || $set === null) throw new \InvalidArgumentException('Update accounts individually by ID.');
        $this->db->transStart();
        foreach ($set as $row) {
            if (!$this->update($row['id'] ?? null, $row)) { $this->db->transRollback(); return false; }
        }
        $this->db->transComplete();
        return $this->db->transStatus() ? count($set) : false;
    }
}
