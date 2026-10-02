<?php

namespace App\Models\Concerns;

trait HasUid
{
    protected function ensureUid(array $data): array
    {
        if (! isset($data['data']) || ! is_array($data['data'])) {
            return $data;
        }

        if (empty($data['data']['uid'])) {
            $data['data']['uid'] = $this->generateUid();
        }

        return $data;
    }

    public function findByUid(string $uid): ?array
    {
        $row = $this->where('uid', $uid)->first();

        return is_array($row) ? $row : null;
    }

    public function uidToId(string $uid): ?int
    {
        $row = $this->select($this->primaryKey)
            ->where('uid', $uid)
            ->first();

        return is_array($row) ? (int) $row[$this->primaryKey] : null;
    }

    protected function generateUid(): string
    {
        $prefix = property_exists($this, 'uidPrefix') ? $this->uidPrefix : 'uid';

        return $prefix . '_' . bin2hex(random_bytes(12));
    }
}
