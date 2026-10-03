<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class NormalizeUserPhoneNumbers extends Migration
{
    public function up()
    {
        $users = $this->db
            ->table('users')
            ->select('id, phone, email, created_at')
            ->where('phone IS NOT NULL', null, false)
            ->get()
            ->getResultArray();

        $groups = [];

        foreach ($users as $user) {
            $phone = $this->normalizePhone((string) $user['phone']);

            if ($phone === '') {
                continue;
            }

            $groups[$phone][] = $user;
        }

        foreach ($groups as $phone => $records) {
            $keeper = $this->keeper($records);
            $duplicateIds = array_values(array_filter(
                array_column($records, 'id'),
                static fn ($id): bool => (int) $id !== (int) $keeper['id'],
            ));

            foreach ($duplicateIds as $duplicateId) {
                $this->moveReferences((int) $duplicateId, (int) $keeper['id']);
            }

            if ($duplicateIds !== []) {
                $this->db->table('users')->whereIn('id', $duplicateIds)->delete();
            }

            $this->db->table('users')
                ->where('id', (int) $keeper['id'])
                ->update(['phone' => $phone]);
        }
    }

    public function down()
    {
        // Phone normalization is intentionally not reversible.
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', trim($phone)) ?? '';
        $digits = ltrim($digits, '0');

        if (strlen($digits) > 10) {
            return substr($digits, -10);
        }

        return $digits;
    }

    /**
     * Keep the most valuable user row: related orders first, then email, then earliest account.
     */
    private function keeper(array $records): array
    {
        usort($records, function (array $left, array $right): int {
            $leftScore = $this->score($left);
            $rightScore = $this->score($right);

            if ($leftScore !== $rightScore) {
                return $rightScore <=> $leftScore;
            }

            return strcmp((string) $left['created_at'], (string) $right['created_at']);
        });

        return $records[0];
    }

    private function score(array $user): int
    {
        $score = $this->referenceCount((int) $user['id']) * 10;

        if (! empty($user['email'])) {
            $score += 5;
        }

        return $score;
    }

    private function referenceCount(int $userId): int
    {
        $count = 0;
        $references = [
            ['orders', 'user_id'],
            ['user_addresses', 'user_id'],
            ['order_status_history', 'changed_by'],
            ['delivery_assignments', 'delivery_user_id'],
            ['delivery_assignments', 'assigned_by'],
        ];

        foreach ($references as [$table, $column]) {
            $row = $this->db->query(
                "SELECT COUNT(*) AS total FROM {$table} WHERE {$column} = ?",
                [$userId],
            )->getRowArray();

            $count += (int) ($row['total'] ?? 0);
        }

        return $count;
    }

    private function moveReferences(int $fromUserId, int $toUserId): void
    {
        $references = [
            ['orders', 'user_id'],
            ['user_addresses', 'user_id'],
            ['order_status_history', 'changed_by'],
            ['delivery_assignments', 'delivery_user_id'],
            ['delivery_assignments', 'assigned_by'],
        ];

        foreach ($references as [$table, $column]) {
            $this->db->table($table)
                ->where($column, $fromUserId)
                ->update([$column => $toUserId]);
        }
    }
}
